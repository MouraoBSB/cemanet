<?php

namespace App\Filament\Resources\Cargos\Pages;

use App\Filament\Resources\Cargos\CargoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCargo extends CreateRecord
{
    protected static string $resource = CargoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['institucional'])) {
            $data['departamento_id'] = null;
        }

        return $data;
    }
}
