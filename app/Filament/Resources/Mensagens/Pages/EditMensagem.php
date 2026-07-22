<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use App\Models\Mensagem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagem extends EditRecord
{
    use PublicaMensagem;
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    /**
     * A reasserção lança DEPOIS de getState() já ter gravado autores e mídia em
     * saveRelationships(); sem esta flag o begin/rollback do Filament é no-op (opt-in, default
     * off) e a recusa deixaria meio-save. Precedente: CreateUser/EditUser.
     */
    protected ?bool $hasDatabaseTransactions = true;

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
        $this->publicandoAgora = $this->record->status !== Mensagem::STATUS_PUBLICADO
            && ($data['status'] ?? null) === Mensagem::STATUS_PUBLICADO;

        $data = $this->reasserirRegraDePublicacao($data);   // ANTES de capturarDestinatarios

        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterSave(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
        $this->carimbarAutoriaSePublicando($this->record);
    }
}
