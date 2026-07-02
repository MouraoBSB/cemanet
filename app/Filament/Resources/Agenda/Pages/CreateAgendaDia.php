<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaDiaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgendaDia extends CreateRecord
{
    protected static string $resource = AgendaDiaResource::class;
}
