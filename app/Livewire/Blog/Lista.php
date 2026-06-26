<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Livewire\Blog;

use App\Models\Categoria;
use App\Models\Post;
use App\Support\Blog\FonteReflexao;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'categoria', except: '')]
    public string $categoria = '';

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'ordenar', except: 'recente')]
    public string $ordenar = 'recente';

    public function updated(string $name): void
    {
        if (in_array($name, ['categoria', 'q', 'ordenar'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $posts = Post::publicado()
            ->with('categoriaPrincipal')
            ->when(
                $this->categoria !== '',
                fn (Builder $q) => $q->whereHas(
                    'categorias',
                    fn (Builder $c) => $c->where('slug', $this->categoria)
                )
            )
            ->when(
                $this->q !== '',
                fn (Builder $q) => $q->where(
                    fn (Builder $w) => $w
                        ->where('titulo', 'like', "%{$this->q}%")
                        ->orWhere('resumo', 'like', "%{$this->q}%")
                )
            )
            ->orderByDesc('data_publicacao')
            ->paginate(9);

        $categorias = Categoria::query()
            ->withCount([
                'posts as posts_publicados_count' => fn (Builder $q) => $q->publicado(),
            ])
            ->orderBy('ordem')
            ->get();

        $maisLidas = Post::maisLidas()->take(5)->get();

        $destaque = Post::publicado()
            ->where('destaque', true)
            ->latest('data_publicacao')
            ->first()
            ?? Post::publicado()->latest('data_publicacao')->first();

        $reflexao = app(FonteReflexao::class)->doDia();

        return view('livewire.blog.lista', compact(
            'posts',
            'categorias',
            'maisLidas',
            'destaque',
            'reflexao',
        ));
    }
}
