<?php

use App\Http\Controllers\PalestraController;
use App\Http\Controllers\PalestranteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])->name('palestras.show');

// Compat: URLs antigas (WP/divulgação) → 301 para as novas, preservando o slug.
Route::permanentRedirect('/palestras', '/palestra_publica');
Route::get('/palestras/{slug}', fn (string $slug) => redirect()->route('palestras.show', ['slug' => $slug], 301));

Route::get('/palestrantes', [PalestranteController::class, 'index'])->name('palestrantes.index');
Route::get('/palestrantes/{slug}', [PalestranteController::class, 'show'])->name('palestrantes.show');
