<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestrantes;

use App\Filament\Resources\Palestrantes\Pages\CreatePalestrante;
use App\Filament\Resources\Palestrantes\Pages\EditPalestrante;
use App\Filament\Resources\Palestrantes\Pages\ListPalestrantes;
use App\Filament\Resources\Palestrantes\Schemas\PalestranteForm;
use App\Filament\Resources\Palestrantes\Tables\PalestrantesTable;
use App\Models\Palestrante;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

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
        return PalestranteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PalestrantesTable::configure($table);
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
