<?php

use App\Http\Controllers\PalestraController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

Route::get('/palestras', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestras/{slug}', [PalestraController::class, 'show'])->name('palestras.show');
