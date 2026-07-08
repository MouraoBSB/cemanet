<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\CategoriasEvento;

use App\Filament\Resources\CategoriasEvento\Pages\CreateCategoriaEvento;
use App\Filament\Resources\CategoriasEvento\Pages\EditCategoriaEvento;
use App\Filament\Resources\CategoriasEvento\Pages\ListCategoriaEventos;
use App\Models\CategoriaEvento;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoriaEventoResource extends Resource
{
    protected static ?string $model = CategoriaEvento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Categoria de evento';

    protected static ?string $pluralModelLabel = 'Categorias de evento';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Categoria de evento')->columns(2)->schema([
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
                ColorPicker::make('cor')
                    ->label('Cor do selo')
                    ->required()
                    ->rules(['regex:/^#[0-9A-Fa-f]{6}$/']),
                ColorPicker::make('cor_texto')
                    ->label('Cor do texto (contraste)')
                    ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                TextInput::make('icone')
                    ->label('Ícone (opcional)')
                    ->maxLength(255),
                TextInput::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('ativo')
                    ->label('Ativa')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                ColorColumn::make('cor')->label('Cor'),
                IconColumn::make('ativo')->label('Ativa')->boolean(),
                TextColumn::make('ordem')->label('Ordem')->numeric()->sortable(),
            ])
            ->defaultSort('ordem')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoriaEventos::route('/'),
            'create' => CreateCategoriaEvento::route('/create'),
            'edit' => EditCategoriaEvento::route('/{record}/edit'),
        ];
    }
}
