<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\CategoriasEvento\Pages;

use App\Filament\Resources\CategoriasEvento\CategoriaEventoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaEvento extends EditRecord
{
    protected static string $resource = CategoriaEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
