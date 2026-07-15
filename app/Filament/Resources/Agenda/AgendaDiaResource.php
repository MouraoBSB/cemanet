<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda;

use App\Filament\Resources\Agenda\Pages\CreateAgendaDia;
use App\Filament\Resources\Agenda\Pages\EditAgendaDia;
use App\Filament\Resources\Agenda\Pages\ListAgendaDias;
use App\Filament\Schemas\AgendaDiaForm;
use App\Models\AgendaDia;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgendaDiaResource extends Resource
{
    protected static ?string $model = AgendaDia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'Dia da agenda';

    protected static ?string $pluralModelLabel = 'Dias da agenda';

    public static function form(Schema $schema): Schema
    {
        // Schema vindo da fonte única (App\Filament\Schemas\AgendaDiaForm), reaproveitada
        // pelo componente de edição embutido no site (/minha-conta).
        return $schema->components(AgendaDiaForm::schema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('meta_dia_titulo')
                    ->label('Meta do Dia')
                    ->placeholder('—')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === AgendaDia::STATUS_PUBLICADO ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        AgendaDia::STATUS_PUBLICADO => 'Publicado',
                        AgendaDia::STATUS_RASCUNHO => 'Rascunho',
                    ]),
            ])
            ->defaultSort('data', 'desc')
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
            'index' => ListAgendaDias::route('/'),
            'create' => CreateAgendaDia::route('/create'),
            'edit' => EditAgendaDia::route('/{record}/edit'),
        ];
    }
}
