<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Support\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\TipoConteudo;
use App\Models\User;

/**
 * Fonte única da pergunta "o usuário está habilitado a tocar neste TIPO?" — consumida pela aba do
 * /minha-conta, pelo criar das policies e pelo filtro do trait. Três implementações da mesma
 * pergunta é como nasce divergência de acesso: é aqui, e só aqui.
 *
 * FAIL-CLOSED em todos os caminhos: recurso sem linha (I2), tipo "do tipo" sem responsáveis (I1) e
 * usuário sem departamento negam. Config vazia NUNCA permite.
 *
 * Registrado como SCOPED (AppServiceProvider) — jamais singleton: o worker não reconstrói o
 * container entre jobs (só chama forgetScopedInstances), e o memo viraria cache persistente de
 * config de acesso. Nunca usar cache persistente aqui: invalidação stale = furo que ninguém vê.
 */
final class AcessoPorTipo
{
    /** @var array<string, TipoConteudo|null> memo por escopo (request ou job) */
    private array $memo = [];

    public function regime(string $recurso): ?RegimeAcesso
    {
        return $this->tipo($recurso)?->regime;
    }

    /** @return list<int> ids dos departamentos responsáveis; [] se o tipo não existe ou não tem responsáveis. */
    public function departamentosResponsaveis(string $recurso): array
    {
        return $this->tipo($recurso)?->departamentos->pluck('id')->all() ?? [];
    }

    /** A PERGUNTA ÚNICA. Fail-closed: regime desconhecido ⇒ nega. */
    public function usuarioHabilitadoNoTipo(User $user, string $recurso): bool
    {
        return match ($this->regime($recurso)) {
            RegimeAcesso::DoTipo => $this->usuarioResponsavel($user, $recurso),
            // "Por registro": o escopo do OBJETO é do trait; aqui a pergunta do tipo é só "tem
            // algum departamento?" — o filtro atual, inalterado (I4).
            RegimeAcesso::PorRegistro => $user->departamentos()->exists(),
            null => false,   // I1/I2: sem linha ⇒ nega, não explode
        };
    }

    private function usuarioResponsavel(User $user, string $recurso): bool
    {
        $ids = $this->departamentosResponsaveis($recurso);

        if ($ids === []) {
            return false;   // I1
        }

        // 'departamentos.id' QUALIFICADO: o pivô tem id próprio e o SQL ficaria ambíguo.
        return $user->departamentos()->whereIn('departamentos.id', $ids)->exists();
    }

    private function tipo(string $recurso): ?TipoConteudo
    {
        // array_key_exists (e não ??=): memoiza TAMBÉM o null, senão o caminho I2 refaz a query a
        // cada checagem de policy.
        if (! array_key_exists($recurso, $this->memo)) {
            $this->memo[$recurso] = TipoConteudo::with('departamentos')
                ->where('recurso', $recurso)
                ->first();
        }

        return $this->memo[$recurso];
    }
}
