<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class OcorrenciaCalendario
{
    /** Ordem de desempate quando duas ocorrências começam no mesmo instante. */
    private const ORDEM_TIPO = ['palestra' => 0, 'evento' => 1, 'agenda' => 2];

    public function __construct(
        public string $tipo,
        public string $chave,
        public string $titulo,
        public string $url,
        public CarbonInterface $inicio,
        public ?CarbonInterface $fim, // fim para o SPAN de dias no grid — é uma DATA, não o instante de término
        public bool $temHora,
        public ?string $subtitulo,
        public string $corAcento,
        public array $selo,
        public ?array $seloVisibilidade,
        public ?string $imagem = null,
        public ?string $iniciais = null,
    ) {}

    /**
     * Dias (1..N) que a ocorrência cobre DENTRO de (ano,mês) — multi-dia acende vários,
     * recortando na virada do mês.
     *
     * @return list<int>
     */
    public function diasNoMes(int $ano, int $mes): array
    {
        // Fuso explícito: não depender de config('app.timezone') — se virar UTC, o último dia
        // de um evento multi-dia sumiria silenciosamente (o startOfDay ficaria 3h à frente).
        $primeiro = Carbon::create($ano, $mes, 1, 0, 0, 0, 'America/Sao_Paulo');
        $ultimo = $primeiro->copy()->endOfMonth();

        $ini = $this->inicio->copy()->setTimezone('America/Sao_Paulo')->startOfDay();
        $fim = ($this->fim ?? $this->inicio)->copy()->setTimezone('America/Sao_Paulo')->startOfDay();

        $de = $ini->lt($primeiro) ? $primeiro->copy() : $ini;
        $ate = $fim->gt($ultimo) ? $ultimo->copy() : $fim;

        if ($de->gt($ate)) {
            return [];
        }

        $dias = [];
        for ($d = $de->copy(); $d->lte($ate); $d->addDay()) {
            $dias[] = (int) $d->day;
        }

        return $dias;
    }

    /** Ordena por início; empate → palestra antes de evento (determinístico). */
    public static function ordenar(Collection $ocorrencias): Collection
    {
        return $ocorrencias
            ->sort(fn (self $a, self $b) => [$a->inicio->getTimestamp(), self::ORDEM_TIPO[$a->tipo] ?? 9]
                <=> [$b->inicio->getTimestamp(), self::ORDEM_TIPO[$b->tipo] ?? 9])
            ->values();
    }
}
