<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestrantes;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'ordenar', except: 'mais')]
    public string $ordenar = 'mais';

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'ordenar'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['q', 'ordenar']);
        $this->resetPage();
    }

    /** @return list<array{chave:string, rotulo:string}> */
    public function filtrosAtivos(): array
    {
        $chips = [];
        if ($this->q !== '') {
            $chips[] = ['chave' => 'q', 'rotulo' => 'Nome: “'.$this->q.'”'];
        }

        return $chips;
    }

    public function render()
    {
        $query = Palestrante::query()
            ->ativo()
            ->when($this->q !== '', fn (Builder $q) => $q->where('nome', 'like', '%'.$this->q.'%'))
            ->withCount(['palestras as palestras_ministradas_count' => function (Builder $q) {
                $q->where('palestra_pessoa.papel', Palestra::PAPEL_PALESTRANTE)
                    ->where('palestras.status', Palestra::STATUS_PUBLICADO);
            }]);

        match ($this->ordenar) {
            'za' => $query->orderBy('nome', 'desc'),
            'mais' => $query->orderByDesc('palestras_ministradas_count')->orderBy('nome'),
            'menos' => $query->orderBy('palestras_ministradas_count')->orderBy('nome'),
            default => $query->orderBy('nome'), // az + qualquer valor inválido via URL
        };

        return view('livewire.palestrantes.lista', [
            'palestrantes' => $query->paginate(12),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
}
