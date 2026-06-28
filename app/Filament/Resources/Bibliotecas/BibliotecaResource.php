<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Filament\Resources\Bibliotecas;

use App\Filament\Resources\Bibliotecas\Pages\ListBibliotecas;
use App\Models\Biblioteca;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BibliotecaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Biblioteca';

    protected static ?string $modelLabel = 'Mídia';

    protected static ?string $pluralModelLabel = 'Biblioteca de mídia';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('collection_name', Biblioteca::COLECAO);
    }

    /**
     * Conta os posts que referenciam a mídia pelo ID com barra final,
     * evitando falsos positivos: /midia/12/ não casa /midia/123/.
     */
    public static function postsQueUsam(int $id): int
    {
        return Post::whereRaw('conteudo LIKE ?', ["%/midia/{$id}/%"])->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('thumb')
                    ->label('Imagem')
                    ->getStateUsing(fn (Media $record): string => route('midia.serve', [$record->id, 'thumb']))
                    ->height(56),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('size')
                    ->label('Tamanho')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 0, ',', '.') . ' KB')
                    ->sortable(),

                TextColumn::make('custom_properties.alt')
                    ->label('Alt')
                    ->getStateUsing(fn (Media $record): ?string => $record->getCustomProperty('alt'))
                    ->toggleable()
                    ->limit(40),

                TextColumn::make('created_at')
                    ->label('Adicionada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('subir')
                    ->label('Subir mídia')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->form([
                        FileUpload::make('arquivo')
                            ->label('Imagem')
                            ->image()
                            ->required()
                            ->disk('local')
                            ->directory('biblioteca-tmp')
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth('2000')
                            ->imageResizeUpscale(false),

                        TextInput::make('alt')
                            ->label('Texto alternativo (alt)')
                            ->helperText('Descreve a imagem para leitores de tela e SEO.')
                            ->maxLength(255),

                        TextInput::make('legenda')
                            ->label('Legenda')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $caminho = Storage::disk('local')->path($data['arquivo']);

                        app(\App\Support\Biblioteca\RegistraMidiaBiblioteca::class)->aPartirDoCaminho(
                            $caminho,
                            basename($data['arquivo']),
                            ['alt' => $data['alt'] ?? null, 'legenda' => $data['legenda'] ?? null],
                        );

                        Storage::disk('local')->delete($data['arquivo']);

                        Notification::make()
                            ->success()
                            ->title('Mídia adicionada à biblioteca')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('editar')
                    ->label('Editar')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->fillForm(fn (Media $record): array => [
                        'alt'       => $record->getCustomProperty('alt'),
                        'legenda'   => $record->getCustomProperty('legenda'),
                        'titulo'    => $record->getCustomProperty('titulo'),
                        'descricao' => $record->getCustomProperty('descricao'),
                    ])
                    ->form([
                        TextInput::make('alt')
                            ->label('Texto alternativo (alt)')
                            ->maxLength(255),

                        TextInput::make('legenda')
                            ->label('Legenda')
                            ->maxLength(255),

                        TextInput::make('titulo')
                            ->label('Título')
                            ->maxLength(255),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(2),
                    ])
                    ->action(function (Media $record, array $data): void {
                        foreach (['alt', 'legenda', 'titulo', 'descricao'] as $campo) {
                            $record->setCustomProperty($campo, $data[$campo] ?? null);
                        }
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Metadados atualizados')
                            ->send();
                    }),

                DeleteAction::make()
                    ->label('Excluir')
                    ->before(function (Media $record, DeleteAction $action): void {
                        $usos = static::postsQueUsam($record->id);

                        if ($usos > 0) {
                            Notification::make()
                                ->danger()
                                ->title('Imagem em uso')
                                ->body("Usada em {$usos} post(s). Remova as referências antes de excluir.")
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBibliotecas::route('/'),
        ];
    }
}
