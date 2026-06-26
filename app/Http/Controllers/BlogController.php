<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Http\Controllers;

use App\Models\Post;

class BlogController extends Controller
{
    public function index()
    {
        return view('blog.index');
    }

    public function show(string $slug)
    {
        $post = Post::publicado()
            ->where('slug', $slug)
            ->with(['categorias', 'tags', 'faqs', 'imagens', 'categoriaPrincipal'])
            ->firstOrFail();

        // Incrementa visualização apenas 1× por sessão
        $chave = 'post_visto_'.$post->id;
        if (! session()->has($chave)) {
            $post->increment('visualizacoes');
            session()->put($chave, true);
        }

        // Posts relacionados: mesma categoria principal, excluindo o atual
        $relacionados = Post::publicado()
            ->where('id', '!=', $post->id)
            ->when(
                $post->categoria_principal_id,
                fn ($q) => $q->whereHas(
                    'categorias',
                    fn ($c) => $c->where('categorias.id', $post->categoria_principal_id)
                )
            )
            ->latest('data_publicacao')
            ->take(3)
            ->with('categoriaPrincipal')
            ->get();

        // Navegação anterior/próxima por data de publicação
        $anterior = Post::publicado()
            ->where('data_publicacao', '<', $post->data_publicacao)
            ->latest('data_publicacao')
            ->first();

        $proxima = Post::publicado()
            ->where('data_publicacao', '>', $post->data_publicacao)
            ->oldest('data_publicacao')
            ->first();

        return view('blog.show', compact('post', 'relacionados', 'anterior', 'proxima'));
    }
}
