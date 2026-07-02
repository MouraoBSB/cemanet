<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportadorAgenda
{
    /** @var string[] */
    private array $avisos = [];

    public function __construct(private LeitorAgenda $leitor) {}

    /**
     * Importa a Agenda Reforma Íntima do legado (via leitor injetado), de forma idempotente.
     *
     * @return array{metas: int, dias: int, slugs: int, avisos: string[]}
     */
    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $entradas = $this->leitor->entradas();

        // Dedupe defensivo por data: uma AgendaDia por data (mantém o 1º = slug limpo/ID menor);
        // TODOS os slugs continuam preservados para o mapa de 301.
        $porData = [];
        foreach ($entradas as $e) {
            foreach ($e['avisos'] ?? [] as $aviso) {
                $this->avisos[] = $aviso;
            }

            $data = $e['data'];
            if (isset($porData[$data])) {
                $mantido = $porData[$data]['post_name'];
                $this->avisos[] = "[dedupe] {$data}: conteúdo mantido de '{$mantido}'; '{$e['post_name']}' entra só como 301.";

                continue;
            }
            $porData[$data] = $e;
        }

        $nMetas = 0;
        $nDias = 0;
        $nSlugs = 0;

        DB::transaction(function () use ($entradas, $porData, &$nMetas, &$nDias, &$nSlugs, $log) {
            // Metas do mês (título fixo por mês) a partir dos dias canônicos.
            $log('Gravando metas de mês...');
            $metas = [];
            foreach ($porData as $e) {
                if (($e['mes_titulo'] ?? null) === null) {
                    continue;
                }
                $d = Carbon::parse($e['data']);
                $metas["{$d->year}-{$d->month}"] = ['ano' => $d->year, 'mes' => $d->month, 'titulo' => $e['mes_titulo']];
            }
            foreach ($metas as $m) {
                AgendaMetaMes::updateOrCreate(
                    ['ano' => $m['ano'], 'mes' => $m['mes']],
                    ['titulo' => $m['titulo']],
                );
            }
            $nMetas = count($metas);

            // Dias (uma entrada por data). HTML cru: o mutator do model sanitiza.
            $log('Gravando dias...');
            foreach ($porData as $e) {
                AgendaDia::updateOrCreate(['data' => $e['data']], [
                    'reflexao' => $e['reflexao'] ?? null,
                    'meta_mes_texto' => $e['mes_texto'] ?? null,
                    'meta_dia_titulo' => $e['meta_dia_titulo'] ?? null,
                    'meta_dia_texto' => $e['meta_dia_texto'] ?? null,
                    'prece' => $e['prece'] ?? null,
                    'status' => AgendaDia::STATUS_PUBLICADO,
                    'wp_id' => $e['wp_id'] ?? null,
                ]);
            }
            $nDias = count($porData);

            // Slugs de 301 — TODOS os posts válidos (N:1 com a data).
            $log('Gravando slugs de 301...');
            $slugs = [];
            foreach ($entradas as $e) {
                AgendaSlugLegado::updateOrCreate(['slug' => $e['post_name']], ['data' => $e['data']]);
                $slugs[$e['post_name']] = true;
            }
            $nSlugs = count($slugs);
        });

        return ['metas' => $nMetas, 'dias' => $nDias, 'slugs' => $nSlugs, 'avisos' => $this->avisos];
    }
}
