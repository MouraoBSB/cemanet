<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Assuntos;

use App\Filament\Resources\Assuntos\Pages\CreateAssunto;
use App\Filament\Resources\Assuntos\Pages\EditAssunto;
use App\Filament\Resources\Assuntos\Pages\ListAssuntos;
use App\Models\Assunto;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AssuntoResource extends Resource
{
    protected static ?string $model = Assunto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Assunto';

    protected static ?string $pluralModelLabel = 'Assuntos';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
                ->unique(table: 'assuntos', column: 'slug', ignoreRecord: true),

            Select::make('parent_id')
                ->label('Assunto pai (opcional)')
                ->nullable()
                ->searchable()
                ->options(fn (?Assunto $record) => Assunto::query()
                    ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                    ->orderBy('nome')
                    ->pluck('nome', 'id')
                ),
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

                TextColumn::make('parent.nome')
                    ->label('Pai')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('children_count')
                    ->label('Filhos')
                    ->counts('children')
                    ->sortable(),
            ])
            ->defaultSort('nome')
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListAssuntos::route('/'),
            'create' => CreateAssunto::route('/create'),
            'edit' => EditAssunto::route('/{record}/edit'),
        ];
    }
}
