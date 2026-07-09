<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Livewire\Eventos;

use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'mes', except: '')]
    public string $mes = ''; // 'AAAA-MM'

    #[Url(as: 'categoria', except: '')]
    public string $categoria = '';

    #[Url(as: 'aba', except: 'proximos')]
    public string $aba = 'proximos'; // proximos | anteriores

    /** id do evento em destaque (excluído da grade de "próximos" para não duplicar). */
    public ?int $destaqueId = null;

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'mes', 'categoria', 'aba'], true)) {
            $this->resetPage();
        }
    }

    private function baseVisivel(): Builder
    {
        return Evento::query()->publicado()->visiveisPara(auth()->user());
    }

    public function render()
    {
        $hoje = now('America/Sao_Paulo')->toDateString();

        $eventos = $this->baseVisivel()
            ->with(['categoria', 'media'])
            ->when($this->aba === 'anteriores',
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje])->orderByDesc('data_inicio'),
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje])
                    ->when($this->destaqueId, fn (Builder $b) => $b->where('id', '!=', $this->destaqueId))
                    ->orderBy('data_inicio'))
            ->when($this->q !== '', fn (Builder $q) => $q->where('titulo', 'like', '%'.$this->q.'%'))
            ->when($this->categoria !== '', fn (Builder $q) => $q->whereHas('categoria', fn (Builder $c) => $c->where('slug', $this->categoria)))
            ->when($this->mes !== '' && preg_match('/^\d{4}-\d{2}$/', $this->mes),
                fn (Builder $q) => $q->where('data_inicio', 'like', $this->mes.'-%'))
            ->paginate(9);

        return view('livewire.eventos.lista', [
            'eventos' => $eventos,
            'categorias' => CategoriaEvento::ativo()->orderBy('ordem')->get(['nome', 'slug', 'cor', 'cor_texto']),
            'meses' => $this->mesesDisponiveis(),
        ]);
    }

    /** Meses 'AAAA-MM' distintos existentes NA ABA corrente (o <select> não oferece mês que dá 0 resultado). */
    private function mesesDisponiveis(): array
    {
        $hoje = now('America/Sao_Paulo')->toDateString();

        return $this->baseVisivel()
            ->when($this->aba === 'anteriores',
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje]),
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje]))
            ->when($this->aba !== 'anteriores' && $this->destaqueId,
                fn (Builder $q) => $q->where('id', '!=', $this->destaqueId))
            ->pluck('data_inicio')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()->sortDesc()->values()->all();
    }
}
