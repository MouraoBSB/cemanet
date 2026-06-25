<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestras;

use App\Models\Palestra;
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

    // Livewire 4: resetar a paginação quando o filtro muda (hooks updated*).
    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedAssunto(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $palestras = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->when($this->q !== '', function ($query) {
                $termo = '%'.$this->q.'%';
                $query->where(function ($q) use ($termo) {
                    $q->where('titulo', 'like', $termo)
                        ->orWhere('subtitulo', 'like', $termo)
                        ->orWhere('resumo', 'like', $termo);
                });
            })
            ->when($this->assunto !== '', function ($query) {
                $query->whereHas('assuntos', fn ($a) => $a->where('slug', $this->assunto));
            })
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->paginate(9);

        return view('livewire.palestras.lista', ['palestras' => $palestras]);
    }
}
