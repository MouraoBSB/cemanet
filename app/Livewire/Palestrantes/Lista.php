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

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $palestrantes = Palestrante::query()
            ->ativo()
            ->when($this->q !== '', fn (Builder $query) => $query->where('nome', 'like', '%'.$this->q.'%'))
            ->withCount(['palestras as palestras_ministradas_count' => function (Builder $query) {
                $query->where('palestra_pessoa.papel', Palestra::PAPEL_PALESTRANTE)
                    ->where('palestras.status', Palestra::STATUS_PUBLICADO);
            }])
            ->orderBy('nome')
            ->paginate(12);

        return view('livewire.palestrantes.lista', ['palestrantes' => $palestrantes]);
    }
}
