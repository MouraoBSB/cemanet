<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Database\Seeders;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\GlossarioCapacidades;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Semeia a configuração de acesso por tipo — 1 linha por recurso do glossário. A semente é o que
 * cada tipo JÁ TEM hoje (medido no dev: 123 AgendaDia DED+DECOM, 127 Palestra DED, 45 Post DECOM,
 * 59 Palestrante DED), então ligar a Camada 1 não muda o acesso de ninguém.
 *
 * INSERT-ONLY (I8): a tela é a dona da config. Linha existente NUNCA é tocada — nem o regime, nem
 * os responsáveis. Reexecutar db:seed preserva integralmente o que o admin configurou.
 */
class TiposConteudoSeeder extends Seeder
{
    private const SEMENTE = [
        'agenda' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED', 'DECOM']],
        'palestra' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED']],
        'palestrante' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED']],
        'post' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DECOM']],
        'evento' => ['regime' => RegimeAcesso::PorRegistro, 'siglas' => []],
        'autor_espiritual' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DEPAE', 'DECOM']],
    ];

    public function run(): void
    {
        foreach ($this->recursos() as $recurso) {
            $semente = self::SEMENTE[$recurso] ?? throw new RuntimeException(
                "Recurso '{$recurso}' do glossário não tem semente em TiposConteudoSeeder."
            );

            $tipo = TipoConteudo::firstOrCreate(
                ['recurso' => $recurso],
                ['regime' => $semente['regime']],
            );

            // Insert-only: linha existente é config da tela (I8) — o seeder não a reescreve.
            if ($tipo->wasRecentlyCreated) {
                $tipo->departamentos()->sync($this->idsPorSigla($semente['siglas']));
            }
        }
    }

    /** Ponto de extensão dos testes (o catálogo real é sempre o glossário). */
    protected function recursos(): array
    {
        return GlossarioCapacidades::RECURSOS;
    }

    /**
     * Resolve as siglas da semente. Sigla que não existe é BUG DE AMBIENTE e explode aqui — o
     * lugar de explodir é o seeder, não a autorização (mesmo princípio do "recurso sem semente").
     *
     * Sem esta guarda o whereIn devolveria [] em silêncio ⇒ sync([]) ⇒ tipo com zero responsáveis
     * ⇒ e o insert-only CONGELA esse estado (resemear não repara; só a tela). Como o cutover de
     * prod roda este seeder, rodá-lo antes de os departamentos existirem — ou depois de alguém
     * renomear uma sigla no /admin — gravaria fail-closed sem um erro no log: "ninguém edita
     * nada", irreparável por reseed.
     */
    private function idsPorSigla(array $siglas): array
    {
        if ($siglas === []) {
            return [];
        }

        $encontrados = Departamento::whereIn('sigla', $siglas)->pluck('sigla')->all();

        if (count($encontrados) !== count($siglas)) {
            $ausentes = implode(', ', array_diff($siglas, $encontrados));

            throw new RuntimeException(
                "Sigla(s) da semente ausente(s) em departamentos: {$ausentes}. ".
                'Rode o EstruturaCemaSeeder antes — semear assim gravaria o tipo sem responsáveis, '.
                'e o insert-only impediria o reparo por reseed.'
            );
        }

        return Departamento::whereIn('sigla', $siglas)->pluck('departamentos.id')->all();
    }
}
