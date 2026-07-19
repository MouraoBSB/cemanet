<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMensagem extends CreateRecord
{
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->capturarRelacionadas($data);
    }

    protected function afterCreate(): void
    {
        $this->aplicarRelacionadas($this->record);
    }
}
