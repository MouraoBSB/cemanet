<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Livewire\Calendario;

use App\Support\Calendario\FonteCalendario;
use App\Support\Calendario\Fontes\EventosFonte;
use App\Support\Calendario\Fontes\PalestrasFonte;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Calendario extends Component
{
    #[Url(as: 'modo', except: 'proximas')]
    public string $modo = 'proximas';

    #[Url(as: 'mes')]
    public ?string $mes = null;

    #[Url(as: 'tipo', except: 'todos')]
    public string $tipo = 'todos';

    public function mount(): void
    {
        $this->normaliza();
        $this->garanteMesValido();
    }

    public function updatedModo(): void
    {
        $this->normaliza();
        $this->garanteMesValido();
    }

    public function updatedTipo(): void
    {
        $this->normaliza();
        $this->garanteMesValido();
    }

    /** Mantém o mês focado se ainda existe no conjunto atual; senão cai no padrão. */
    private function garanteMesValido(): void
    {
        $meses = $this->mesesModoAsc();
        if ($this->mes === null || ! in_array($this->mes, $meses, true)) {
            $this->mes = $this->mesPadrao($meses);
        }
    }

    public function mesAnterior(): void
    {
        $meses = $this->mesesModoAsc();
        $i = array_search($this->mes, $meses, true);
        if ($i !== false && $i > 0) {
            $this->mes = $meses[$i - 1];
        }
    }

    public function mesProximo(): void
    {
        $meses = $this->mesesModoAsc();
        $i = array_search($this->mes, $meses, true);
        if ($i !== false && $i < count($meses) - 1) {
            $this->mes = $meses[$i + 1];
        }
    }

    public function irParaAno($ano): void
    {
        foreach ($this->mesesModoAsc() as $m) {
            if (str_starts_with($m, (string) $ano.'-')) {
                $this->mes = $m;

                return;
            }
        }
    }

    public function render(): View
    {
        $agora = now();
        $usuario = auth()->user();

        $proxima = OcorrenciaCalendario::ordenar(
            collect($this->fontesAtivas())->map(fn ($f) => $f->proxima($usuario))->filter()->values()
        )->first();

        $mesesAsc = $this->mesesModoAsc();
        $mesesExib = $this->modo === 'realizadas' ? array_reverse($mesesAsc) : $mesesAsc;
        $anos = collect($mesesExib)->map(fn ($m) => substr($m, 0, 4))->unique()->values()->all();

        $mesFoco = in_array($this->mes, $mesesAsc, true) ? $this->mes : $this->mesPadrao($mesesAsc);

        $ocorrenciasDoMes = new Collection;
        $matriz = ['diasVazios' => 0, 'dias' => []];
        $temAnterior = $temProximo = false;

        if ($mesFoco !== null) {
            [$ano, $mesNum] = array_map('intval', explode('-', $mesFoco));

            $ocorrenciasDoMes = OcorrenciaCalendario::ordenar(
                collect($this->fontesAtivas())
                    ->flatMap(fn ($f) => $f->ocorrencias($ano, $mesNum, $this->modo, $usuario)->all())
                    ->pipe(fn ($c) => new Collection($c))
            );

            $i = array_search($mesFoco, $mesesAsc, true);
            $temAnterior = $i !== false && $i > 0;
            $temProximo = $i !== false && $i < count($mesesAsc) - 1;

            $matriz = $this->matriz($ano, $mesNum, $ocorrenciasDoMes, $agora);
        }

        return view('livewire.calendario.calendario', [
            'proxima' => $proxima,
            'modo' => $this->modo,
            'tipo' => $this->tipo,
            'mesFoco' => $mesFoco,
            'anos' => $anos,
            'ocorrenciasDoMes' => $ocorrenciasDoMes,
            'contagem' => $ocorrenciasDoMes->count(),
            'matriz' => $matriz,
            'agora' => $agora,
            'temAnterior' => $temAnterior,
            'temProximo' => $temProximo,
            'feedsAssinar' => match ($this->tipo) {
                'palestras' => [['rotulo' => 'Palestras', 'url' => route('palestras.calendario-ics')]],
                'eventos' => [['rotulo' => 'Eventos', 'url' => route('eventos.feed-ics')]],
                default => [
                    ['rotulo' => 'Palestras', 'url' => route('palestras.calendario-ics')],
                    ['rotulo' => 'Eventos', 'url' => route('eventos.feed-ics')],
                ],
            },
        ]);
    }

    /** @return list<FonteCalendario> */
    private function fontesAtivas(): array
    {
        return match ($this->tipo) {
            'palestras' => [new PalestrasFonte],
            'eventos' => [new EventosFonte],
            default => [new PalestrasFonte, new EventosFonte],
        };
    }

    private function normaliza(): void
    {
        if (! in_array($this->modo, ['proximas', 'realizadas'], true)) {
            $this->modo = 'proximas';
        }
        if (! in_array($this->tipo, ['todos', 'palestras', 'eventos'], true)) {
            $this->tipo = 'todos';
        }
    }

    /** União (ordenada ASC) dos meses das fontes ativas, no modo atual. */
    private function mesesModoAsc(): array
    {
        $usuario = auth()->user();

        return collect($this->fontesAtivas())
            ->flatMap(fn ($f) => $f->meses($this->modo, $usuario))
            ->unique()->sort()->values()->all();
    }

    private function mesPadrao(array $mesesAsc): ?string
    {
        if ($mesesAsc === []) {
            return null;
        }

        return $this->modo === 'realizadas' ? end($mesesAsc) : $mesesAsc[0];
    }

    /**
     * @param  Collection<int,OcorrenciaCalendario>  $ocorrencias
     * @return array{diasVazios:int, dias:list<array{dia:int, ocorrencias:list<array{tipo:string,cor:string,titulo:string}>, ancora:?string, hoje:bool}>}
     */
    private function matriz(int $ano, int $mes, Collection $ocorrencias, Carbon $agora): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $offset = $primeiro->dayOfWeek; // 0=domingo

        $porDia = [];
        $ancoraDia = [];
        foreach ($ocorrencias as $oc) {
            foreach ($oc->diasNoMes($ano, $mes) as $d) {
                $porDia[$d][] = ['tipo' => $oc->tipo, 'cor' => $oc->corAcento, 'titulo' => $oc->titulo];
                $ancoraDia[$d] ??= $oc->chave; // 1ª ocorrência do dia = alvo do scroll
            }
        }

        $ehMesCorrente = (int) $agora->year === $ano && (int) $agora->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dias[] = [
                'dia' => $d,
                'ocorrencias' => $porDia[$d] ?? [],
                'ancora' => $ancoraDia[$d] ?? null,
                'hoje' => $ehMesCorrente && (int) $agora->day === $d,
            ];
        }

        return ['diasVazios' => $offset, 'dias' => $dias];
    }
}
