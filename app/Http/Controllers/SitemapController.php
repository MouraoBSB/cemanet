<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Post;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $posts = Post::publicado()
            ->orderByDesc('data_publicacao')
            ->get(['slug', 'updated_at']);

        $categorias = Categoria::orderBy('ordem')->get(['slug']);

        return response()
            ->view('sitemap', compact('posts', 'categorias'))
            ->header('Content-Type', 'application/xml');
    }
}
