<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Filament\Resources\Palestrantes\Pages;

use App\Filament\Resources\Palestrantes\PalestranteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPalestrante extends EditRecord
{
    protected static string $resource = PalestranteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
