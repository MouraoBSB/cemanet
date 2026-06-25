<?php

namespace App\Filament\Resources\Palestras\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PalestrasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('subtitulo')
                    ->searchable(),
                TextColumn::make('data_da_palestra')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('online')
                    ->boolean(),
                TextColumn::make('link_youtube')
                    ->searchable(),
                TextColumn::make('cor_fundo')
                    ->searchable(),
                TextColumn::make('publico_online')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('publico_presencial')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('publico_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
