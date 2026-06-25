<?php

use App\Http\Controllers\PalestraController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestras', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestras/{slug}', [PalestraController::class, 'show'])->name('palestras.show');

// Substituídas pelo controller nas Tasks 2 e 3.
Route::get('/palestrantes', fn () => abort(404))->name('palestrantes.index');
Route::get('/palestrantes/{slug}', fn () => abort(404))->name('palestrantes.show');
