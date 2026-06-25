<?php

use App\Http\Controllers\PalestraController;
use App\Http\Controllers\PalestranteController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestras', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestras/{slug}', [PalestraController::class, 'show'])->name('palestras.show');

Route::get('/palestrantes', [PalestranteController::class, 'index'])->name('palestrantes.index');
Route::get('/palestrantes/{slug}', [PalestranteController::class, 'show'])->name('palestrantes.show');
