<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\MensagemResource;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Mensagens\RegraPublicacao;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            Action::make('publicar')
                ->label('Publicar')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->modalHeading('Publicar esta mensagem?')
                ->modalDescription('A mensagem passa a valer no site, com o nível de acesso escolhido no formulário.')
                ->visible(fn (): bool => $this->record->status !== Mensagem::STATUS_PUBLICADO)
                ->action(function (): void {
                    DB::transaction(function (): void {
                        $registro = $this->record;

                        // Defesa em profundidade — NÃO exercitável pela UI (visible false já
                        // impede o mount). Impede sobrescrever a autoria de outra pessoa.
                        abort_if($registro->status === Mensagem::STATUS_PUBLICADO, 403);

                        $dados = $this->form->getState();  // valida + saveRelationships(), DENTRO da transação

                        // Reasserção dos 3 campos privilegiados: hoje redundante (não são
                        // fillable e getState() já poda), mas explícita — DATA-MODEL.md.
                        unset($dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em']);

                        $ids = SincronizadorDestinatarios::efetivos($dados['nivel'] ?? null, $dados['destinatarios'] ?? []);
                        $erros = RegraPublicacao::erros(['nivel' => $dados['nivel'] ?? null, 'destinatarios' => $ids]);

                        if ($erros !== []) {
                            $ehDirecionada = ($dados['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
                            $chave = $ehDirecionada ? 'data.destinatarios' : 'data.nivel';
                            $mensagem = $ehDirecionada ? $erros[0] : MensagemForm::MSG_NIVEL_OBRIGATORIO;

                            throw ValidationException::withMessages([$chave => $mensagem]);
                        }

                        $idsRelacionadas = $dados['relacionadas'] ?? [];
                        // Nenhum dos dois é coluna: fill() os descartaria em SILÊNCIO.
                        unset($dados['destinatarios'], $dados['relacionadas']);

                        $registro->fill($dados);
                        // NÃO regenerar o slug: aqui ele é campo de tela (contrato inverso ao
                        // de CuradoriaConta:169, onde o slug nasce do rascunho do médium).
                        $registro->status = Mensagem::STATUS_PUBLICADO;
                        $registro->publicado_por_id = auth()->id();
                        $registro->publicado_em = now();
                        $registro->save();

                        SincronizadorDestinatarios::sincronizar($registro, $ids);
                        $registro->sincronizarRelacionadas($idsRelacionadas);
                    });

                    // Sem isto, $this->data['status'] segue "pendente" e o próximo
                    // "Salvar alterações" despublica em silêncio.
                    $this->refreshFormData(['status']);

                    Notification::make()->success()->title('Mensagem publicada.')->send();
                }),

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
