<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $papelAntes = [];

    protected array $deptosAntes = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // B1: o saveRelationships roda dentro de getState() (antes de afterSave); capturar o "antes" AQUI,
        // por query fresca (->roles()/->departamentos()), antes do parent::save().
        $this->papelAntes = $this->record->roles()->pluck('name')->all();
        $this->deptosAntes = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function afterSave(): void
    {
        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, $this->papelAntes, $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, $this->deptosAntes, $deptosDepois);
    }
}
