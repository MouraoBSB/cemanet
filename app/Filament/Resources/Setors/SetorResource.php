<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Filament\Resources\Setors;

use App\Filament\Resources\Setors\Pages\CreateSetor;
use App\Filament\Resources\Setors\Pages\EditSetor;
use App\Filament\Resources\Setors\Pages\ListSetors;
use App\Models\Setor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SetorResource extends Resource
{
    protected static ?string $model = Setor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Setores';

    protected static ?string $modelLabel = 'Setor';

    protected static ?string $pluralModelLabel = 'Setores';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do setor')
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
                            }),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('departamento_id')
                            ->label('Departamento')
                            ->relationship('departamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Deixe vazio para setor sem departamento (ex.: PAMANA).'),

                        Toggle::make('provisorio')
                            ->label('Provisório'),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('departamento.nome')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Sem departamento'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('provisorio')
                    ->label('Provisório')
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
            'index' => ListSetors::route('/'),
            'create' => CreateSetor::route('/create'),
            'edit' => EditSetor::route('/{record}/edit'),
        ];
    }
}
