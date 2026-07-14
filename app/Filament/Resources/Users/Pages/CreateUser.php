<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, [], $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, [], $deptosDepois);
    }
}
