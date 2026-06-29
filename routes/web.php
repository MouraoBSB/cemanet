<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\MidiaController;
use App\Http\Controllers\PalestraController;
use App\Http\Controllers\PalestranteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])->name('palestras.show');

// Compat: URLs antigas (WP/divulgação) → 301 para as novas, preservando o slug.
Route::permanentRedirect('/palestras', '/palestra_publica');
Route::get('/palestras/{slug}', fn (string $slug) => redirect()->route('palestras.show', ['slug' => $slug], 301));

Route::get('/palestrantes', [PalestranteController::class, 'index'])->name('palestrantes.index');
Route::get('/palestrantes/{slug}', [PalestranteController::class, 'show'])->name('palestrantes.show');

// Blog "Sementeira de Luz"
Route::get('/sementeira', [BlogController::class, 'index'])->name('blog.index');
Route::get('/sementeira/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Sitemap (antes do catch-all)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Compat: URL antiga de categoria → listagem filtrada (301).
Route::get('/categoria/{slug}', fn (string $slug) => redirect()->to('/sementeira?categoria='.$slug, 301));

// Mídia da biblioteca por rota estável/portável (antes do catch-all).
Route::get('/midia/{media}/{conversao?}', [MidiaController::class, 'serve'])
    ->name('midia.serve')
    ->where('media', '[0-9]+')
    ->where('conversao', '[a-z]+');

// Catch-all raiz: redireciona slugs de posts existentes → /sementeira/{slug} (301).
// DEVE ser a última rota do arquivo.
Route::get('/{slug}', function (string $slug) {
    abort_unless(\App\Models\Post::where('slug', $slug)->exists(), 404);

    return redirect()->route('blog.show', ['slug' => $slug], 301);
})->where('slug', '[a-z0-9-]+');
