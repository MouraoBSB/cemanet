<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Livewire\Palestras;

use App\Models\Palestra;
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

    public function mount(): void
    {
        $this->normalizaModo();
        $meses = $this->mesesModoAsc();
        if ($this->mes === null || ! in_array($this->mes, $meses, true)) {
            $this->mes = $this->mesPadrao($meses);
        }
    }

    public function updatedModo(): void
    {
        $this->normalizaModo();
        $this->mes = $this->mesPadrao($this->mesesModoAsc());
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

        $proxima = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', $agora)
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->first();

        $mesesAsc = $this->mesesModoAsc($agora);
        $mesesExib = $this->modo === 'realizadas' ? array_reverse($mesesAsc) : $mesesAsc;
        $anos = collect($mesesExib)->map(fn ($m) => substr($m, 0, 4))->unique()->values()->all();

        $mesFoco = in_array($this->mes, $mesesAsc, true) ? $this->mes : $this->mesPadrao($mesesAsc);

        $palestrasDoMes = new Collection;
        $matriz = ['diasVazios' => 0, 'dias' => []];
        $temAnterior = false;
        $temProximo = false;

        if ($mesFoco !== null) {
            [$ano, $mesNum] = array_map('intval', explode('-', $mesFoco));

            $palestrasDoMes = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
                ->whereYear('data_da_palestra', $ano)
                ->whereMonth('data_da_palestra', $mesNum)
                ->with(['palestrantesAtivos', 'assuntos'])
                ->orderBy('data_da_palestra')
                ->get()
                ->each(function (Palestra $p) use ($agora, $proxima) {
                    $p->eh_proxima = $proxima !== null && $p->id === $proxima->id;
                    $p->eh_realizada = $p->data_da_palestra->lt($agora);
                    $p->tem_gravacao = $p->eh_realizada && ! empty($p->link_youtube);
                });

            $i = array_search($mesFoco, $mesesAsc, true);
            $temAnterior = $i !== false && $i > 0;
            $temProximo = $i !== false && $i < count($mesesAsc) - 1;

            $matriz = $this->matriz($ano, $mesNum, $palestrasDoMes, $agora);
        }

        return view('livewire.palestras.calendario', [
            'proxima' => $proxima,
            'modo' => $this->modo,
            'mesFoco' => $mesFoco,
            'anos' => $anos,
            'palestrasDoMes' => $palestrasDoMes,
            'matriz' => $matriz,
            'agora' => $agora,
            'temAnterior' => $temAnterior,
            'temProximo' => $temProximo,
        ]);
    }

    private function normalizaModo(): void
    {
        if (! in_array($this->modo, ['proximas', 'realizadas'], true)) {
            $this->modo = 'proximas';
        }
    }

    /** Meses ('Y-m') com palestra no modo atual, em ordem CRONOLÓGICA ASCENDENTE. */
    private function mesesModoAsc(?Carbon $agora = null): array
    {
        $agora ??= now();
        $q = Palestra::query()->publicado()->whereNotNull('data_da_palestra');
        $q = $this->modo === 'realizadas'
            ? $q->where('data_da_palestra', '<', $agora)
            : $q->where('data_da_palestra', '>=', $agora);

        return $q->orderBy('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    /** Mês default do modo: proximas → 1º (mais próximo); realizadas → último (mais recente). */
    private function mesPadrao(array $mesesAsc): ?string
    {
        if ($mesesAsc === []) {
            return null;
        }

        return $this->modo === 'realizadas' ? end($mesesAsc) : $mesesAsc[0];
    }

    /**
     * @return array{diasVazios:int, dias:list<array{dia:int, palestra:?array{slug:string,titulo:string}, hoje:bool}>}
     */
    private function matriz(int $ano, int $mes, Collection $palestrasDoMes, Carbon $agora): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $offset = $primeiro->dayOfWeek; // 0=domingo … 6=sábado (semana começa no domingo)

        $porDia = [];
        foreach ($palestrasDoMes as $p) {
            $d = (int) $p->data_da_palestra->day;
            if (! isset($porDia[$d])) {
                $porDia[$d] = ['slug' => $p->slug, 'titulo' => $p->titulo];
            }
        }

        $ehMesCorrente = (int) $agora->year === $ano && (int) $agora->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dias[] = [
                'dia' => $d,
                'palestra' => $porDia[$d] ?? null,
                'hoje' => $ehMesCorrente && (int) $agora->day === $d,
            ];
        }

        return ['diasVazios' => $offset, 'dias' => $dias];
    }
}
