<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestras;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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

    #[Url(as: 'ano', except: '')]
    public string $ano = '';

    #[Url(as: 'video', except: '')]
    public string $video = '';

    #[Url(as: 'visao', except: 'grid')]
    public string $visao = 'grid';

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar', 'ano', 'video'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        // 'visao' preservada de propósito (preferência de exibição, não filtro).
        $this->reset(['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar', 'ano', 'video']);
        $this->resetPage();
    }

    public function removerFiltro(string $chave): void
    {
        $mapa = [
            'q' => 'q', 'assunto' => 'assunto', 'palestrante' => 'palestrante',
            'de' => 'dataDe', 'ate' => 'dataAte', 'ano' => 'ano', 'video' => 'video',
        ];

        if (isset($mapa[$chave])) {
            $this->{$mapa[$chave]} = '';
            $this->resetPage();
        }
    }

    public function alternarVisao(string $visao): void
    {
        // NÃO chama resetPage(): trocar a visão não deve voltar para a página 1.
        $this->visao = in_array($visao, ['grid', 'list'], true) ? $visao : 'grid';
    }

    public function filtrosAtivos(): array
    {
        $chips = [];

        if ($this->q !== '') {
            $chips[] = ['chave' => 'q', 'rotulo' => 'Título: “'.$this->q.'”'];
        }
        if ($this->assunto !== '') {
            $chips[] = ['chave' => 'assunto', 'rotulo' => 'Tema: '.(Assunto::where('slug', $this->assunto)->value('nome') ?? $this->assunto)];
        }
        if ($this->palestrante !== '') {
            $chips[] = ['chave' => 'palestrante', 'rotulo' => 'Palestrante: '.(Palestrante::where('slug', $this->palestrante)->value('nome') ?? $this->palestrante)];
        }
        if ($this->dataDe !== '') {
            $chips[] = ['chave' => 'de', 'rotulo' => 'De: '.$this->dataDe];
        }
        if ($this->dataAte !== '') {
            $chips[] = ['chave' => 'ate', 'rotulo' => 'Até: '.$this->dataAte];
        }
        if ($this->ano !== '') {
            $chips[] = ['chave' => 'ano', 'rotulo' => 'Ano: '.$this->ano];
        }
        if ($this->video !== '') {
            $chips[] = ['chave' => 'video', 'rotulo' => $this->video === 'com' ? 'Com vídeo' : 'Sem vídeo'];
        }

        return $chips;
    }

    /** Anos distintos (desc) das palestras publicadas, para o filtro. Distinct em PHP (portável). */
    public function anosDisponiveis(): Collection
    {
        return Palestra::publicado()
            ->whereNotNull('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->year)
            ->unique()
            ->sortDesc()
            ->values();
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
            ->when($this->ano !== '' && ctype_digit($this->ano), fn (Builder $query) => $query->whereYear('data_da_palestra', (int) $this->ano))
            ->when($this->video === 'com', fn (Builder $query) => $query->whereNotNull('link_youtube'))
            ->when($this->video === 'sem', fn (Builder $query) => $query->whereNull('link_youtube'))
            ->when($this->ordenar === 'az',
                fn (Builder $query) => $query->orderBy('titulo'),
                fn (Builder $query) => $query->orderByRaw('data_da_palestra IS NULL, data_da_palestra '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
            ->paginate(9);

        return view('livewire.palestras.lista', [
            'palestras' => $palestras,
            'palestrantes' => Palestrante::ativo()->orderBy('nome')->get(['nome', 'slug']),
            'assuntos' => Assunto::whereHas('palestras', fn (Builder $q) => $q->publicado())->orderBy('nome')->get(['nome', 'slug']),
            'anos' => $this->anosDisponiveis(),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
}
