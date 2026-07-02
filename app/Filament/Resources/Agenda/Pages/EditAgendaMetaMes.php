<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaMetaMesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgendaMetaMes extends EditRecord
{
    protected static string $resource = AgendaMetaMesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
