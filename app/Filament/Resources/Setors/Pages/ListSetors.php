<?php

namespace App\Filament\Resources\Setors\Pages;

use App\Filament\Resources\Setors\SetorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSetors extends ListRecords
{
    protected static string $resource = SetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
