<?php

namespace App\Filament\Resources\Palestrantes\Pages;

use App\Filament\Resources\Palestrantes\PalestranteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPalestrantes extends ListRecords
{
    protected static string $resource = PalestranteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
