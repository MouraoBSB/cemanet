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

    private function idsPorSigla(array $siglas): array
    {
        if ($siglas === []) {
            return [];
        }

        return Departamento::whereIn('sigla', $siglas)->pluck('departamentos.id')->all();
    }
}
