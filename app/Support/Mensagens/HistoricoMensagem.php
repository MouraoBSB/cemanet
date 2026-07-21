<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Support\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Primeiro leitor de `activity_log` do projeto (Fatia F4b, Task 11): monta o histórico do item
 * aberto na curadoria — QUEM fez, QUANDO e QUAIS CAMPOS mudaram, NUNCA os valores. `properties`
 * carrega valor (ex.: o do `titulo`) — a regra "só nomes de campo" é de RENDERIZAÇÃO, aplicada
 * aqui na leitura: esta classe nunca devolve o conteúdo bruto de `attributes`/`old`/`diff`, só os
 * rótulos (via GlossarioCamposMensagem) dos campos que mudaram.
 *
 * `activity_log` usa morphs: `subject_id` é compartilhado entre TODOS os models auditados — toda
 * query filtra `subject_type` E `log_name` (B3), senão um User id=7 marcaria a Mensagem id=7.
 */
class HistoricoMensagem
{
    private const LOG_NAME = 'mensagem';

    /**
     * Linhas do histórico do item, mais recente primeiro. `latest('id')` (a PRIMARY; `created_at`
     * não tem índice), `limit($limite)`. R4: `causer` já vem resolvido a `->name` (ou "Sistema") —
     * nunca o objeto User inteiro.
     *
     * @return list<array{quando: ?Carbon, quem: string, descricao: string, campos: list<string>}>
     */
    public static function linhas(Mensagem $mensagem, int $limite = 20): array
    {
        return self::query($mensagem)
            ->with('causer')
            ->latest('id')
            ->limit($limite)
            ->get()
            ->map(fn (Activity $atividade): array => self::linha($atividade))
            ->all();
    }

    /** Existe pelo menos +1 entrada além das $limite já mostradas (R3: "mostrando as N mais recentes"). */
    public static function haMaisQue(Mensagem $mensagem, int $limite = 20): bool
    {
        return self::query($mensagem)->skip($limite)->limit(1)->exists();
    }

    /**
     * Ids das mensagens da coleção com ≥1 evento `updated` cujo causer é o PRÓPRIO médium autor
     * (aviso "editada pelo autor após o lançamento" na fila). UMA query para a coleção inteira —
     * nunca por item. Legada (`medium_id` null) nunca marca; `created` não marca (só `updated`).
     *
     * @param  Collection<int, Mensagem>  $mensagens
     * @return list<int>
     */
    public static function editadasPeloAutor(Collection $mensagens): array
    {
        /** @var Collection<int, int> $pares [mensagem_id => medium_id], só as que têm autor */
        $pares = $mensagens
            ->filter(fn (Mensagem $m): bool => $m->medium_id !== null)
            ->pluck('medium_id', 'id');

        if ($pares->isEmpty()) {
            return [];
        }

        // Candidatas por ids (subject_id/causer_id) — o pareamento EXATO subject_id↔medium_id é
        // conferido em PHP logo abaixo, para não marcar um id por coincidência entre pares.
        $candidatas = Activity::query()
            ->where('subject_type', (new Mensagem)->getMorphClass())
            ->where('log_name', self::LOG_NAME)
            ->where('event', 'updated')
            ->where('causer_type', (new User)->getMorphClass())
            ->whereIn('subject_id', $pares->keys())
            ->whereIn('causer_id', $pares->unique()->values())
            ->get(['subject_id', 'causer_id']);

        return $candidatas
            ->filter(fn (Activity $a): bool => $pares->get($a->subject_id) === $a->causer_id)
            ->pluck('subject_id')
            ->unique()
            ->values()
            ->all();
    }

    /** Query base — SEMPRE subject_type + subject_id + log_name (B3: subject_id é compartilhado entre models). */
    private static function query(Mensagem $mensagem): Builder
    {
        return Activity::query()
            ->where('subject_type', $mensagem->getMorphClass())
            ->where('subject_id', $mensagem->getKey())
            ->where('log_name', self::LOG_NAME);
    }

    private static function linha(Activity $atividade): array
    {
        return [
            'quando' => $atividade->created_at,
            'quem' => $atividade->causer?->name ?? 'Sistema',
            'descricao' => self::descricao($atividade),
            'campos' => self::camposAlterados($atividade),
        ];
    }

    /** A description do model já vem em pt-BR (getActivitylogOptions); "publicada" é um rótulo mais específico. */
    private static function descricao(Activity $atividade): string
    {
        $atributos = $atividade->properties?->get('attributes');

        if ($atividade->event === 'updated'
            && is_array($atributos)
            && array_key_exists('status', $atributos)
            && $atributos['status'] === Mensagem::STATUS_PUBLICADO) {
            return 'publicada';
        }

        return $atividade->description;
    }

    /**
     * União das chaves de `attributes` e `old` (created não tem `old`, deleted não tem `attributes`,
     * uma mudança null→valor só aparece porque a chave existe), filtrada pela lista branca — NUNCA
     * os valores. `array_key_exists` via array_keys(), nunca `isset`/`array_filter` sobre valores
     * (um campo virando null/false/0/'' sumiria). Entrada manual (chave `diff`) devolve [].
     *
     * @return list<string>
     */
    private static function camposAlterados(Activity $atividade): array
    {
        $atributos = $atividade->properties?->get('attributes');
        $antigos = $atividade->properties?->get('old');

        if (! is_array($atributos) && ! is_array($antigos)) {
            return [];
        }

        $chaves = array_values(array_unique(array_merge(
            array_keys($atributos ?? []),
            array_keys($antigos ?? []),
        )));

        return array_values(array_filter(array_map(
            fn (string $campo): ?string => GlossarioCamposMensagem::rotulo($campo),
            $chaves,
        )));
    }
}
