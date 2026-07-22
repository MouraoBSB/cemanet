<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens;

use App\Enums\FormatoMensagem;
use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MensagemResource extends Resource
{
    protected static ?string $model = Mensagem::class;

    // Sem $slug o Laravel geraria 'mensagems' (pluralizador inglês) — travamos a rota pt-BR.
    protected static ?string $slug = 'mensagens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Mensagens Mediúnicas';

    protected static ?string $modelLabel = 'Mensagem';

    protected static ?string $pluralModelLabel = 'Mensagens';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        // Schema vindo da fonte única (App\Filament\Schemas\MensagemForm) — extração literal (I20).
        return $schema->components(MensagemForm::schemaAdmin());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('formato')
                    ->label('Formato')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof FormatoMensagem ? $state->rotulo() : (string) $state),

                TextColumn::make('data_recebimento')
                    ->label('Recebida em')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nivel')
                    ->label('Nível')
                    ->formatStateUsing(fn (?string $state): string => VisibilidadeMensagem::tryFrom((string) $state)?->rotulo() ?? '— (sem nível)')
                    // nivel=null é state "blank": o TextColumn pula formatStateUsing e cai direto no placeholder.
                    ->placeholder('— (sem nível)')
                    ->toggleable(),

                TextColumn::make('medium.name')
                    ->label('Lançada por')
                    ->placeholder('Importada do legado')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Mensagem::STATUS_PUBLICADO => 'success',
                        Mensagem::STATUS_PENDENTE => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('liberar_download')
                    ->label('Download')
                    ->boolean()
                    ->toggleable(),

                SpatieMediaLibraryImageColumn::make('imagens')
                    ->label('Imagens')
                    ->collection(Mensagem::COLECAO_IMAGENS)
                    ->conversion('thumb')
                    ->toggleable(),

                TextColumn::make('destinatarios_count')
                    ->label('Destinatários')
                    ->counts('destinatarios')
                    ->badge()
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('medium:id,name'))
            ->defaultSort('data_recebimento', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    Mensagem::STATUS_PUBLICADO => 'Publicada',
                    Mensagem::STATUS_PENDENTE => 'Pendente',
                    Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                ]),
                SelectFilter::make('formato')->options(FormatoMensagem::opcoes()),
                Filter::make('com_destinatarios')
                    ->label('Tem destinatário')
                    ->query(fn (Builder $query): Builder => $query->has('destinatarios')),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionadas'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMensagens::route('/'),
            'create' => CreateMensagem::route('/create'),
            'edit' => EditMensagem::route('/{record}/edit'),
        ];
    }
}
