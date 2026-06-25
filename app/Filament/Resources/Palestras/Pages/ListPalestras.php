<?php

namespace App\Filament\Resources\Palestras\Pages;

use App\Filament\Resources\Palestras\PalestraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPalestras extends ListRecords
{
    protected static string $resource = PalestraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
