<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

// Substituídas pelo controller nas Tasks 4 e 5.
Route::get('/palestras', fn () => abort(404))->name('palestras.index');
Route::get('/palestras/{slug}', fn () => abort(404))->name('palestras.show');
