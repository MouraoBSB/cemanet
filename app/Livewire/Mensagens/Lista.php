<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Livewire\Mensagens;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'de', except: '')]
    public string $dataDe = '';

    #[Url(as: 'ate', except: '')]
    public string $dataAte = '';

    #[Url(as: 'autor', except: '')]
    public string $autor = '';   // slug do autor OU o sentinela 'sem-assinatura'

    #[Url(as: 'ordenar', except: 'recente')]
    public string $ordenar = 'recente';   // recente | antiga | az

    #[Url(as: 'visao', except: 'grid')]
    public string $visao = 'grid';         // grid | list

    public function updated(string $name): void
    {
        if (in_array($name, ['dataDe', 'dataAte', 'autor', 'ordenar'], true)) {
            $this->resetPage();   // 'visao' fora: trocar visão não reseta paginação.
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['dataDe', 'dataAte', 'autor', 'ordenar']);   // 'visao' preservada (preferência, não filtro).
        $this->resetPage();
    }

    public function removerFiltro(string $chave): void
    {
        $mapa = ['de' => 'dataDe', 'ate' => 'dataAte', 'autor' => 'autor'];
        if (isset($mapa[$chave])) {
            $this->reset($mapa[$chave]);
            $this->resetPage();
        }
    }

    public function alternarVisao(string $visao): void
    {
        // NÃO reseta página: trocar a visão não deve voltar para a página 1.
        $this->visao = in_array($visao, ['grid', 'list'], true) ? $visao : 'grid';
    }

    /** @return array<int, array{chave: string, rotulo: string}> */
    public function filtrosAtivos(): array
    {
        $chips = [];
        if ($this->dataDe !== '') {
            $chips[] = ['chave' => 'de', 'rotulo' => 'De: '.$this->dataDe];
        }
        if ($this->dataAte !== '') {
            $chips[] = ['chave' => 'ate', 'rotulo' => 'Até: '.$this->dataAte];
        }
        if ($this->autor === 'sem-assinatura') {
            $chips[] = ['chave' => 'autor', 'rotulo' => 'Autor: Sem assinatura'];
        } elseif ($this->autor !== '') {
            $chips[] = ['chave' => 'autor', 'rotulo' => 'Autor: '.(AutorEspiritual::where('slug', $this->autor)->value('nome') ?? $this->autor)];
        }

        return $chips;
    }

    public function render()
    {
        $mensagens = Mensagem::query()
            ->publica()
            ->with('autores')
            ->when($this->dataDe !== '' && Carbon::hasFormat($this->dataDe, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '>=', $this->dataDe))
            ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '<=', $this->dataAte))
            ->when($this->autor === 'sem-assinatura', fn (Builder $q) => $q->whereDoesntHave('autores'))
            ->when($this->autor !== '' && $this->autor !== 'sem-assinatura',
                fn (Builder $q) => $q->whereHas('autores', fn (Builder $a) => $a->where('autores_espirituais.slug', $this->autor)))
            ->when($this->ordenar === 'az',
                fn (Builder $q) => $q->orderBy('titulo'),
                fn (Builder $q) => $q->orderByRaw('data_recebimento IS NULL, data_recebimento '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
            ->paginate(9);

        return view('livewire.mensagens.lista', [
            'mensagens' => $mensagens,
            'autores' => AutorEspiritual::whereHas('mensagens', fn (Builder $q) => $q->publica())->orderBy('nome')->get(['nome', 'slug']),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
}
