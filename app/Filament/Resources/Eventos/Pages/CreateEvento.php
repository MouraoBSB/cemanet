<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Eventos\Pages;

use App\Filament\Resources\Eventos\EventoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvento extends CreateRecord
{
    use ValidaPeriodoEvento;

    protected static string $resource = EventoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->validarPeriodo($data);
    }
}
