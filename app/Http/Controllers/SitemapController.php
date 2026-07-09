<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Http\Controllers;

use App\Models\AgendaDia;
use App\Models\Categoria;
use App\Models\Evento;
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

        $agendaDias = AgendaDia::publicado()
            ->orderBy('data')
            ->get(['data', 'updated_at']);

        // visiveisPara(null) = só o que um visitante anônimo vê (só Público) — nada restrito vaza no sitemap.
        $eventos = Evento::publicado()
            ->visiveisPara(null)
            ->orderByDesc('data_inicio')
            ->get(['slug', 'updated_at']);

        return response()
            ->view('sitemap', compact('posts', 'categorias', 'agendaDias', 'eventos'))
            ->header('Content-Type', 'application/xml');
    }
}
