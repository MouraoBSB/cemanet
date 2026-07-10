<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Filament\Resources\Eventos\Pages\CreateEvento;
use App\Filament\Resources\Eventos\Pages\EditEvento;
use App\Filament\Resources\Eventos\Pages\ListEventos;
use App\Filament\Schemas\EventoForm;
use App\Models\Evento;
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

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventos';

    public static function form(Schema $schema): Schema
    {
        // O schema vem da fonte única (App\Filament\Schemas\EventoForm),
        // reaproveitável por formulários de Evento embutidos fora do painel.
        return $schema->components(EventoForm::schema());
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
                TextColumn::make('periodo')
                    ->label('Período'),
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === Evento::STATUS_PUBLICADO ? 'success' : 'gray'),
                TextColumn::make('visibilidade')
                    ->label('Visibilidade')
                    ->badge()
                    ->formatStateUsing(fn (VisibilidadeEvento $state) => $state->rotulo()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Evento::STATUS_PUBLICADO => 'Publicado',
                        Evento::STATUS_RASCUNHO => 'Rascunho',
                    ]),
                SelectFilter::make('categoria_evento_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome'),
                SelectFilter::make('visibilidade')
                    ->options(VisibilidadeEvento::opcoes()),
            ])
            ->defaultSort('data_inicio', 'desc')
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
            'index' => ListEventos::route('/'),
            'create' => CreateEvento::route('/create'),
            'edit' => EditEvento::route('/{record}/edit'),
        ];
    }
}
