<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario\Fontes;

use App\Models\Palestra;
use App\Models\User;
use App\Support\Calendario\FonteCalendario;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PalestrasFonte implements FonteCalendario
{
    public const COR = '#F4C24B'; // dourado (acento de palestra)

    public function tipo(): string
    {
        return 'palestra';
    }

    public function meses(string $modo, ?User $u): array
    {
        return $this->query($modo)->orderBy('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()->values()->all();
    }

    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection
    {
        $agora = now();
        $proxima = $this->query('proximas')->orderBy('data_da_palestra')->first();

        return $this->query($modo)
            ->whereYear('data_da_palestra', $ano)
            ->whereMonth('data_da_palestra', $mes)
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->get()
            ->map(fn (Palestra $p) => $this->paraOcorrencia($p, $agora, $proxima));
    }

    public function proxima(?User $u): ?OcorrenciaCalendario
    {
        $agora = now();
        $p = $this->query('proximas')->orderBy('data_da_palestra')
            ->with(['palestrantesAtivos', 'assuntos'])->first();

        return $p ? $this->paraOcorrencia($p, $agora, $p) : null;
    }

    /** Palestras publicadas com data, filtradas pelo modo. */
    private function query(string $modo): Builder
    {
        $agora = now();
        $q = Palestra::query()->publicado()->whereNotNull('data_da_palestra');

        return $modo === 'realizadas'
            ? $q->where('data_da_palestra', '<', $agora)
            : $q->where('data_da_palestra', '>=', $agora);
    }

    private function paraOcorrencia(Palestra $p, Carbon $agora, ?Palestra $proxima): OcorrenciaCalendario
    {
        $ehProxima = $proxima !== null && $p->id === $proxima->id;
        $ehRealizada = $p->data_da_palestra->lt($agora);

        $palestrantes = $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ');
        $tema = optional($p->assuntos->first())->nome;
        $formato = $p->online ? 'Online' : 'Presencial';
        $subtitulo = trim(implode(' · ', array_filter([
            $palestrantes !== '' ? 'com '.$palestrantes : null,
            $tema,
            $formato,
        ])));

        $pa = $p->palestrantesAtivos->first();

        return new OcorrenciaCalendario(
            tipo: 'palestra',
            chave: 'palestra-'.$p->id,
            titulo: $p->titulo,
            url: route('palestras.show', $p->slug),
            inicio: $p->data_da_palestra->copy(),
            fim: null,
            temHora: true,
            subtitulo: $subtitulo !== '' ? $subtitulo : null,
            corAcento: self::COR,
            selo: $this->selo($ehProxima, $ehRealizada),
            seloVisibilidade: null,
            imagem: $pa?->foto_thumb_url,
            iniciais: $pa ? $this->iniciais($pa->nome) : 'CEMA',
        );
    }

    /** @return array{rotulo:string,cor:string,cor_texto:string} */
    private function selo(bool $ehProxima, bool $ehRealizada): array
    {
        if ($ehProxima) {
            return ['rotulo' => 'Próxima', 'cor' => '#F4C24B', 'cor_texto' => '#3a2f00'];
        }
        if ($ehRealizada) {
            return ['rotulo' => 'Realizada', 'cor' => '#EFEDF5', 'cor_texto' => '#6a6390'];
        }

        return ['rotulo' => 'Agendada', 'cor' => '#EAF1EC', 'cor_texto' => '#3a6b4e'];
    }

    private function iniciais(string $nome): string
    {
        return collect(explode(' ', $nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('');
    }
}
