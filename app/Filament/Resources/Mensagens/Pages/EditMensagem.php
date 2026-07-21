<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagem extends EditRecord
{
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['relacionadas'] = $this->record->relacionadas()->pluck('mensagens.id')->all();
        $data['destinatarios'] = $this->record->destinatarios()->pluck('users.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterSave(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
    }
}
