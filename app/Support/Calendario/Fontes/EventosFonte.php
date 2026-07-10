<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario\Fontes;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use App\Support\Calendario\FonteCalendario;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EventosFonte implements FonteCalendario
{
    public function tipo(): string
    {
        return 'evento';
    }

    public function meses(string $modo, ?User $u): array
    {
        // Um evento pode cobrir vários meses; junta os meses cobertos por cada intervalo visível.
        return $this->query($modo, $u)->orderBy('data_inicio')
            ->get(['data_inicio', 'data_fim'])
            ->flatMap(fn (Evento $e) => $this->mesesDoIntervalo(
                $e->getRawOriginal('data_inicio'),
                $e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio')
            ))
            ->unique()->sort()->values()->all();
    }

    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection
    {
        $primeiro = Carbon::create($ano, $mes, 1)->toDateString();
        $ultimo = Carbon::create($ano, $mes, 1)->endOfMonth()->toDateString();

        return $this->query($modo, $u)
            // overlap: começa até o fim do mês E termina (coalesce) no primeiro dia ou depois
            ->where('data_inicio', '<=', $ultimo)
            ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$primeiro])
            ->with(['categoria', 'media'])
            ->orderBy('data_inicio')
            ->get()
            ->map(fn (Evento $e) => $this->paraOcorrencia($e, $u));
    }

    public function proxima(?User $u): ?OcorrenciaCalendario
    {
        $e = $this->query('proximas', $u)
            ->with(['categoria', 'media'])
            ->orderBy('data_inicio')->first();

        return $e ? $this->paraOcorrencia($e, $u) : null;
    }

    /** Eventos publicados VISÍVEIS ao usuário, filtrados pelo modo (data, não instante). */
    private function query(string $modo, ?User $u): Builder
    {
        $hoje = now('America/Sao_Paulo')->toDateString();
        $q = Evento::query()->publicado()->visiveisPara($u);

        return $modo === 'realizadas'
            ? $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje])
            : $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje]);
    }

    /** @return list<string> meses 'Y-m' cobertos por [inicio,fim] (strings Y-m-d). */
    private function mesesDoIntervalo(string $inicio, string $fim): array
    {
        $cursor = Carbon::parse($inicio)->startOfMonth();
        $ate = Carbon::parse($fim)->startOfMonth();
        $meses = [];
        while ($cursor->lte($ate)) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $meses;
    }

    private function paraOcorrencia(Evento $e, ?User $u): OcorrenciaCalendario
    {
        $restrito = $e->visibilidade !== VisibilidadeEvento::Publico;

        return new OcorrenciaCalendario(
            tipo: 'evento',
            chave: 'evento-'.$e->id,
            titulo: $e->titulo,
            url: route('eventos.show', $e->slug),
            inicio: $e->inicioUtc()->setTimezone('America/Sao_Paulo'),
            // fim = DATA crua no fuso da casa (span de dias no grid, não instante de término).
            fim: Carbon::parse($e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio'), 'America/Sao_Paulo'),
            temHora: $e->temHora(),
            subtitulo: $e->local ?: null,
            corAcento: $e->categoria?->cor ?? '#89AB98',
            selo: $e->status_selo, // ['rotulo','cor','cor_texto']
            seloVisibilidade: $restrito
                ? ['rotulo' => $e->visibilidade->rotulo(), 'cor' => $e->visibilidade->cor()]
                : null,
            imagem: $e->flyerUrl,
            iniciais: null,
        );
    }
}
