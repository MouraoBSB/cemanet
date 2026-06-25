<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestrantes\Pages;

use App\Filament\Resources\Palestrantes\PalestranteResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePalestrante extends CreateRecord
{
    protected static string $resource = PalestranteResource::class;
}
