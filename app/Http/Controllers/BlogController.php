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

        return view('blog.show', compact('post'));
    }
}
