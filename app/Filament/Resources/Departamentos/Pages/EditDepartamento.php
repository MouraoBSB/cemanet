<?php

namespace App\Filament\Resources\Departamentos\Pages;

use App\Filament\Resources\Departamentos\DepartamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartamento extends EditRecord
{
    protected static string $resource = DepartamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
