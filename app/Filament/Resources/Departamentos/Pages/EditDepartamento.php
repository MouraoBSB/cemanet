<?php

namespace App\Filament\Resources\Departamentos\Pages;

use App\Filament\Resources\Departamentos\DepartamentoResource;
use App\Models\Departamento;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartamento extends EditRecord
{
    protected static string $resource = DepartamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesmo guarda da listagem: a FK é restrict, e sem isto o DELETE estoura como 500.
            DeleteAction::make()
                ->before(function (Departamento $record, DeleteAction $action): void {
                    DepartamentoResource::barrarSeResponsavel($record, $action);
                }),
        ];
    }
}
