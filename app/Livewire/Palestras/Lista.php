<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestras;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'assunto', except: '')]
    public string $assunto = '';

    #[Url(as: 'palestrante', except: '')]
    public string $palestrante = '';

    #[Url(as: 'de', except: '')]
    public string $dataDe = '';

    #[Url(as: 'ate', except: '')]
    public string $dataAte = '';

    #[Url(as: 'ordenar', except: 'recente')]
    public string $ordenar = 'recente';

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar']);
        $this->resetPage();
    }

    public function render()
    {
        $palestras = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->when($this->q !== '', function (Builder $query) {
                $termo = '%'.$this->q.'%';
                $query->where(function (Builder $q) use ($termo) {
                    $q->where('titulo', 'like', $termo)
                        ->orWhere('subtitulo', 'like', $termo)
                        ->orWhere('resumo', 'like', $termo);
                });
            })
            ->when($this->assunto !== '', fn (Builder $query) => $query->whereHas('assuntos', fn (Builder $a) => $a->where('slug', $this->assunto)))
            ->when($this->palestrante !== '', fn (Builder $query) => $query->whereHas('palestrantesAtivos', fn (Builder $p) => $p->where('palestrantes.slug', $this->palestrante)))
            ->when($this->dataDe !== '' && Carbon::hasFormat($this->dataDe, 'Y-m-d'), fn (Builder $query) => $query->whereDate('data_da_palestra', '>=', $this->dataDe))
            ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte, 'Y-m-d'), fn (Builder $query) => $query->whereDate('data_da_palestra', '<=', $this->dataAte))
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra '.($this->ordenar === 'antiga' ? 'asc' : 'desc'))
            ->paginate(12);

        return view('livewire.palestras.lista', [
            'palestras' => $palestras,
            'palestrantes' => Palestrante::ativo()->orderBy('nome')->get(['nome', 'slug']),
            'assuntos' => Assunto::whereHas('palestras', fn (Builder $q) => $q->publicado())->orderBy('nome')->get(['nome', 'slug']),
        ]);
    }
}
