<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies\Concerns;

use App\Enums\RegimeAcesso;
use App\Models\Contracts\TemDepartamento;
use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;

/**
 * Escopo por departamento das policies de capacidade (fonte única), por REGIME (Camada 1):
 * - "do tipo": vale quem está num departamento responsável pelo TIPO (a config da tela). O
 *   objeto NÃO é consultado — o pivô departamento_<x> está congelado (§6.4 do spec).
 * - "por registro": o filtro de sempre (objeto ∈ deptos do usuário), byte a byte. Só o Evento.
 *
 * Fail-closed em todos os caminhos: recurso sem linha em tipos_conteudo ⇒ nega (I1/I2).
 * 🚫 `null` NUNCA tem fallback — nem para PorRegistro, nem para regime default (§12.8).
 */
trait AutorizaPorDepartamento
{
    /** Slug do recurso no GlossarioCapacidades — o MESMO literal que a policy usa em 'x.ver'. */
    abstract protected function recurso(): string;

    /** ver/editar/excluir: o escopo depende do regime. */
    protected function noEscopo(User $user, TemDepartamento $objeto): bool
    {
        $acesso = app(AcessoPorTipo::class);

        return match ($acesso->regime($this->recurso())) {
            RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, $this->recurso()),
            RegimeAcesso::PorRegistro => $this->objetoNoDepartamentoDoUsuario($user, $objeto),
            null => false,
        };
    }

    /**
     * criar: não há objeto — a pergunta é sempre sobre o TIPO. Uma linha, de propósito:
     * usuarioHabilitadoNoTipo JÁ ramifica por regime (DoTipo ⇒ responsável = I3;
     * PorRegistro ⇒ tem algum depto = I4 intacto; null ⇒ false = I1/I2). Repetir o match aqui
     * criaria uma SEGUNDA implementação da pergunta única — o que §6.2 proíbe.
     */
    protected function podeCriarNoEscopo(User $user): bool
    {
        return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, $this->recurso());
    }

    /**
     * Regime "por registro": intacto (I4). PRIVATE de propósito — só o braço PorRegistro do
     * match acima pode alcançá-lo. Se aparecer chamada fora dali, o congelamento é ficção.
     */
    private function objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool
    {
        $idsUsuario = $user->departamentos()->pluck('departamentos.id')->all();

        if ($idsUsuario === []) {
            return false;
        }

        return $objeto->departamentos()
            ->whereIn('departamentos.id', $idsUsuario)
            ->exists();
    }
}
