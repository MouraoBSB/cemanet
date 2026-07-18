<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais;

use App\Filament\Resources\AutoresEspirituais\Pages\CreateAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\EditAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\ListAutoresEspirituais;
use App\Filament\Support\ComponentesImagem;
use App\Models\AutorEspiritual;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AutorEspiritualResource extends Resource
{
    protected static ?string $model = AutorEspiritual::class;

    // Sem $slug o Laravel geraria 'autor-espirituals' (pluralizador inglês) — travamos a rota pt-BR.
    protected static ?string $slug = 'autores-espirituais';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Autores Espirituais';

    protected static ?string $modelLabel = 'Autor Espiritual';

    protected static ?string $pluralModelLabel = 'Autores Espirituais';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->columnSpan(2),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Toggle::make('ativo')
                            ->label('Autor ativo')
                            ->default(true),

                        TextInput::make('chamada')
                            ->label('Chamada (frase do hero)')
                            ->helperText('Frase curta exibida no topo do perfil. Opcional.')
                            ->maxLength(180)
                            ->columnSpan(2),
                    ]),

                Section::make('Foto')
                    ->schema([
                        ComponentesImagem::upload('foto', AutorEspiritual::COLECAO_FOTO)
                            ->label('Foto do autor espiritual'),
                    ]),

                Section::make('Biografia')
                    ->schema([
                        RichEditor::make('bio')
                            ->label('Biografia')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('foto')
                    ->label('Foto')
                    ->collection(AutorEspiritual::COLECAO_FOTO)
                    ->conversion('thumb')
                    ->circular(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nome')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionados'),
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
            'index' => ListAutoresEspirituais::route('/'),
            'create' => CreateAutorEspiritual::route('/create'),
            'edit' => EditAutorEspiritual::route('/{record}/edit'),
        ];
    }
}
