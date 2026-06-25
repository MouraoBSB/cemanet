<?php

namespace App\Filament\Resources\Palestras\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PalestraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titulo')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('subtitulo'),
                Textarea::make('resumo')
                    ->columnSpanFull(),
                Textarea::make('descricao')
                    ->columnSpanFull(),
                DateTimePicker::make('data_da_palestra'),
                Toggle::make('online')
                    ->required(),
                TextInput::make('link_youtube'),
                TextInput::make('cor_fundo'),
                TextInput::make('publico_online')
                    ->numeric(),
                TextInput::make('publico_presencial')
                    ->numeric(),
                TextInput::make('publico_total')
                    ->numeric(),
                TextInput::make('status')
                    ->required()
                    ->default('publicado'),
            ]);
    }
}
