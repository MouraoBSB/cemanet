<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Filament\Resources\Mensagens\Pages;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Mensagens\RegraPublicacao;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Illuminate\Validation\ValidationException;

/**
 * Reasserção server-side da regra de publicação + carimbo de autoria, para os caminhos do
 * /admin (Select `status` no Edit e criação). Molde EXATO dos traits irmãos
 * SincronizaDestinatarios/SincronizaRelacionadas: expõe HELPERS, nunca declara hook — as pages
 * já declaram os hooks `mutateFormDataBefore…` e `after…` na classe, e método de classe vence
 * método de trait sem erro nem aviso (o hook do trait seria no-op silencioso).
 */
trait PublicaMensagem
{
    /** Só a TRANSIÇÃO para publicado carimba autoria (nunca o estado). */
    protected bool $publicandoAgora = false;

    /**
     * Chamar ANTES de capturarDestinatarios(), que faz unset($data['destinatarios']): depois
     * dele toda direcionada seria lida como "sem destinatário".
     */
    protected function reasserirRegraDePublicacao(array $data): array
    {
        if (($data['status'] ?? null) !== Mensagem::STATUS_PUBLICADO) {
            return $data;
        }

        // efetivos(), NUNCA filtrarPorNivel(): é o filtro de `ativo` que impede publicar uma
        // direcionada visível para ninguém.
        $idsEfetivos = SincronizadorDestinatarios::efetivos(
            $data['nivel'] ?? null,
            $data['destinatarios'] ?? []
        );

        $erros = RegraPublicacao::erros([
            'nivel' => $data['nivel'] ?? null,
            'destinatarios' => $idsEfetivos,
        ]);

        if ($erros === []) {
            return $data;
        }

        // Com o conjunto EFETIVO já filtrado, só sobra erro de destinatário quando o nível em
        // si é 'direcionada' (válido); senão o erro é do nível (molde CuradoriaConta:157-163).
        $ehDirecionada = ($data['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
        $chave = $ehDirecionada ? 'data.destinatarios' : 'data.nivel';
        $mensagem = $ehDirecionada ? $erros[0] : MensagemForm::MSG_NIVEL_OBRIGATORIO;

        throw ValidationException::withMessages([$chave => $mensagem]);
    }

    protected function carimbarAutoriaSePublicando(Mensagem $registro): void
    {
        if (! $this->publicandoAgora) {
            return;
        }

        $registro->publicado_em = now();
        $registro->publicado_por_id = auth()->id();
        $registro->save();
    }
}
