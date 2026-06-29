<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestrantes;

use App\Filament\Resources\Palestrantes\Pages\CreatePalestrante;
use App\Filament\Resources\Palestrantes\Pages\EditPalestrante;
use App\Filament\Resources\Palestrantes\Pages\ListPalestrantes;
use App\Filament\Support\ComponentesImagem;
use App\Models\Palestrante;
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

class PalestranteResource extends Resource
{
    protected static ?string $model = Palestrante::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Palestrantes';

    protected static ?string $modelLabel = 'Palestrante';

    protected static ?string $pluralModelLabel = 'Palestrantes';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados pessoais')
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

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                    ]),

                Section::make('Foto')
                    ->schema([
                        ComponentesImagem::upload('foto', Palestrante::COLECAO_FOTO)
                            ->label('Foto do palestrante'),
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

                Section::make('Contato e exibição')
                    ->columns(2)
                    ->schema([
                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(20),

                        Toggle::make('mostrar_email')
                            ->label('Exibir e-mail no site'),

                        Toggle::make('mostrar_telefone')
                            ->label('Exibir telefone no site'),

                        Toggle::make('ativo')
                            ->label('Palestrante ativo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('foto')
                    ->label('Foto')
                    ->collection(Palestrante::COLECAO_FOTO)
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

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(),

                IconColumn::make('mostrar_email')
                    ->label('Exibe e-mail')
                    ->boolean(),

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
            'index' => ListPalestrantes::route('/'),
            'create' => CreatePalestrante::route('/create'),
            'edit' => EditPalestrante::route('/{record}/edit'),
        ];
    }
}
