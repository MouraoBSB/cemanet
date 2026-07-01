<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Support\Palestrantes;

use App\Models\Palestra;
use Illuminate\Support\Collection;

/**
 * Resumo do perfil de um palestrante, calculado em PHP (portável SQLite/MySQL)
 * a partir da coleção de palestras publicadas ministradas: estatísticas e as
 * áreas de atuação (assuntos distintos, com contagem e índice de cor).
 */
class ResumoPerfil
{
    /** Nº máximo de chips de área exibidos no hero. */
    public const CHIPS_HERO = 8;

    /** @param Collection<int, Palestra> $palestras */
    public function __construct(private Collection $palestras) {}

    public function totalPalestras(): int
    {
        return $this->palestras->count();
    }

    public function totalTemas(): int
    {
        return $this->areas()->count();
    }

    /** Menor ano de `data_da_palestra`; null quando não há data. */
    public function anoAtivoDesde(): ?int
    {
        return $this->palestras
            ->pluck('data_da_palestra')
            ->filter()
            ->map(fn ($d) => (int) $d->year)
            ->min();
    }

    /** Percentual de palestras online (0–100); null quando não há palestras. */
    public function percentualOnline(): ?int
    {
        $total = $this->palestras->count();

        if ($total === 0) {
            return null;
        }

        $online = $this->palestras->where('online', true)->count();

        return (int) round($online / $total * 100);
    }

    /**
     * Áreas de atuação: assuntos distintos, com contagem e índice de cor (id % 8),
     * ordenadas por frequência (desc).
     *
     * @return Collection<int, array{slug: string, nome: string, count: int, cor: int}>
     */
    public function areas(): Collection
    {
        return $this->palestras
            ->flatMap(fn (Palestra $p) => $p->assuntos)
            ->groupBy('id')
            ->map(function (Collection $grupo) {
                $assunto = $grupo->first();

                return [
                    'slug' => $assunto->slug,
                    'nome' => $assunto->nome,
                    'count' => $grupo->count(),
                    'cor' => $assunto->id % 8,
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /** Subconjunto de `areas()` para os chips do hero (top-N por frequência). */
    public function areasHero(): Collection
    {
        return $this->areas()->take(self::CHIPS_HERO);
    }
}
