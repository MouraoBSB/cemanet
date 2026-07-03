<?php

namespace App\Filament\Resources\Cargos\Pages;

use App\Filament\Resources\Cargos\CargoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCargo extends EditRecord
{
    protected static string $resource = CargoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
