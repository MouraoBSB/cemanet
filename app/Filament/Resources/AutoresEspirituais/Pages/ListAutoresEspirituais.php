<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais\Pages;

use App\Filament\Resources\AutoresEspirituais\AutorEspiritualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutoresEspirituais extends ListRecords
{
    protected static string $resource = AutorEspiritualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
