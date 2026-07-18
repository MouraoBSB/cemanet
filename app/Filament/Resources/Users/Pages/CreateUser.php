<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Usuarios\IntegridadePapel;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Liga a transação SÓ nesta página: sem ela o rollback de assegurar() é no-op e a trava vaza
    // (a transação do Filament é opt-in/default off). Não ligar no painel.
    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        // 1ª linha: aborta+reverte (dentro da transação) se o estado gravado ferir R1/R2.
        IntegridadePapel::assegurar($this->record);

        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, [], $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, [], $deptosDepois);
    }
}
