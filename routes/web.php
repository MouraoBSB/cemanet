<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\MidiaController;
use App\Http\Controllers\PalestraController;
use App\Http\Controllers\PalestranteController;
use App\Http\Controllers\SitemapController;
use App\Models\AgendaSlugLegado;
use App\Models\Post;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');

// Stub da página Calendário (nome canônico; a fatia do Calendário preenche o corpo depois).
// DEVE vir ANTES de palestras.show para não ser capturada por {slug}.
Route::get('/palestra_publica/calendario', [CalendarioController::class, 'index'])->name('palestras.calendario');

// Feed .ics agregado das próximas palestras. DEVE vir ANTES de palestras.show.
Route::get('/palestra_publica/calendario.ics', [CalendarioController::class, 'feed'])->name('palestras.calendario-ics');

Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])
    ->name('palestras.show')
    ->where('slug', '[a-z0-9-]+');
Route::get('/palestra_publica/{slug}/calendario.ics', [PalestraController::class, 'calendario'])
    ->name('palestras.evento-ics')
    ->where('slug', '[a-z0-9-]+');

// Compat: URLs antigas (WP/divulgação) → 301 para as novas, preservando o slug.
Route::permanentRedirect('/palestras', '/palestra_publica');
Route::get('/palestras/{slug}', fn (string $slug) => redirect()->route('palestras.show', ['slug' => $slug], 301));

Route::get('/palestrantes', [PalestranteController::class, 'index'])->name('palestrantes.index');
Route::get('/palestrantes/{slug}', [PalestranteController::class, 'show'])->name('palestrantes.show');

// Blog "Sementeira de Luz"
Route::get('/sementeira', [BlogController::class, 'index'])->name('blog.index');
Route::get('/sementeira/{slug}', [BlogController::class, 'show'])->name('blog.show');

// Agenda Reforma Íntima (devocional diário). Estáticas antes de {data}.
Route::get('/agenda-reforma-intima', [AgendaController::class, 'index'])->name('agenda.index');
Route::get('/agenda-reforma-intima/{data}', [AgendaController::class, 'show'])
    ->name('agenda.show')
    ->where('data', '\d{4}-\d{2}-\d{2}');

// Compat: URLs antigas do WP → 301 para as URLs datadas novas.
Route::permanentRedirect('/agenda-reforma', '/agenda-reforma-intima');
Route::get('/agenda-reforma/{slug}', function (string $slug) {
    $data = AgendaSlugLegado::where('slug', $slug)->value('data');
    abort_if($data === null, 404);

    return redirect()->route('agenda.show', $data->format('Y-m-d'), 301);
})->where('slug', '[a-z0-9-]+'); // slug numérico (maio) OU de data (jun-ago)

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
    abort_unless(Post::where('slug', $slug)->exists(), 404);

    return redirect()->route('blog.show', ['slug' => $slug], 301);
})->where('slug', '[a-z0-9-]+');
