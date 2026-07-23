<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use App\Models\Mensagem;
use Filament\Resources\Pages\CreateRecord;

class CreateMensagem extends CreateRecord
{
    use PublicaMensagem;
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    /**
     * Aqui o saveRelationships() roda DEPOIS do mutate (CreateRecord::create:115), então a
     * recusa não deixa meio-save de relações. A flag entra assim mesmo: torna atômico o par
     * create + pivôs do afterCreate.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->publicandoAgora = ($data['status'] ?? null) === Mensagem::STATUS_PUBLICADO; // sem estado anterior

        $data = $this->reasserirRegraDePublicacao($data);

        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterCreate(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
        $this->carimbarAutoriaSePublicando($this->record);
    }
}
