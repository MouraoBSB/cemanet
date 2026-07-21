<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\AutorEspiritualController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\MensagemController;
use App\Http\Controllers\MidiaController;
use App\Http\Controllers\PalestraController;
use App\Http\Controllers\PalestranteController;
use App\Http\Controllers\SitemapController;
use App\Models\AgendaSlugLegado;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::get('/', fn () => view('pages.inicio'))->name('home');

// Autenticação pública de membro (Fortify headless — rotas pt-BR, nomes preservados).
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/entrar', [AuthenticatedSessionController::class, 'store']);

    Route::get('/cadastro', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/cadastro', [RegisteredUserController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/esqueci-a-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-a-senha', [PasswordResetLinkController::class, 'store'])->name('password.email')->middleware('throttle:6,1');

    Route::get('/redefinir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/redefinir-senha', [NewPasswordController::class, 'store'])->name('password.update');
});

// Área do membro (self-service) — sob autenticação.
Route::middleware('auth')->prefix('minha-conta')->name('conta.')->group(function () {
    Route::get('/', [ContaController::class, 'painel'])->name('painel');
    Route::get('/perfil', [ContaController::class, 'perfil'])->name('perfil');
    Route::get('/agenda', [ContaController::class, 'agenda'])->name('agenda');
    Route::get('/mensagens', [ContaController::class, 'mensagens'])->name('mensagens');
    Route::get('/direcionadas', [ContaController::class, 'direcionadas'])->name('direcionadas');
});

Route::post('/sair', [AuthenticatedSessionController::class, 'destroy'])->name('logout')->middleware('auth');

// Login social via Google (controller implementado na Task 5 — rotas já registradas aqui).
Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');

Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');

// Calendário unificado (Palestras + Eventos). Rota de topo, sem colisão de {slug}.
Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');

// Página do calendário migrou para /calendario (unificado). 301 preserva SEO/links antigos.
// DEVE vir ANTES de palestras.show para não ser capturada por {slug}.
Route::permanentRedirect('/palestra_publica/calendario', '/calendario');

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

// Eventos. Estáticas antes de {slug}.
Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');

// Feed .ics agregado (públicos, não encerrados). DEVE vir ANTES de eventos.show.
Route::get('/eventos/calendario.ics', [EventoController::class, 'feed'])->name('eventos.feed-ics');

Route::get('/eventos/{slug}', [EventoController::class, 'show'])
    ->name('eventos.show')->where('slug', '[a-z0-9-]+');
Route::get('/eventos/{slug}/calendario.ics', [EventoController::class, 'calendario'])
    ->name('eventos.evento-ics')->where('slug', '[a-z0-9-]+');

// Compat 301 das URLs antigas do WP (/_evento e /_evento/{slug}).
Route::permanentRedirect('/_evento', '/eventos');
Route::get('/_evento/{slug}', fn (string $slug) => redirect()->route('eventos.show', ['slug' => $slug], 301))
    ->where('slug', '[a-z0-9-]+');

// Mensagens Mediúnicas (front público — só Públicas). Estáticas antes de {slug}.
Route::get('/mensagens-mediunicas', [MensagemController::class, 'index'])->name('mensagens.index');
Route::get('/mensagens-mediunicas/{slug}', [MensagemController::class, 'show'])
    ->name('mensagens.show')->where('slug', '[a-z0-9-]+');

// Compat 301 do CPT WP 'mensagem-mediunicas' (singular) → base nova (plural).
Route::permanentRedirect('/mensagem-mediunicas', '/mensagens-mediunicas');
Route::get('/mensagem-mediunicas/{slug}', fn (string $slug) => redirect()->route('mensagens.show', ['slug' => $slug], 301))
    ->where('slug', '[a-z0-9-]+');

// Autores Espirituais (perfil por slug, sem .ics).
Route::get('/autores-espirituais', [AutorEspiritualController::class, 'index'])->name('autores.index');
Route::get('/autores-espirituais/{slug}', [AutorEspiritualController::class, 'show'])
    ->name('autores.show')->where('slug', '[a-z0-9-]+');

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

// Fallback: avaliado SEMPRE por último. Slug de post no root → /sementeira/{slug} (301); senão 404.
Route::fallback(function (Request $request) {
    $slug = ltrim($request->path(), '/');
    if (preg_match('/^[a-z0-9-]+$/', $slug) && Post::where('slug', $slug)->exists()) {
        return redirect()->route('blog.show', ['slug' => $slug], 301);
    }
    abort(404);
});
