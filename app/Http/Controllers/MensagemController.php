<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MensagemController extends Controller
{
    public function index(): View
    {
        return view('mensagens.index');   // corpo real na Task 3
    }

    public function show(string $slug): View
    {
        abort(501);   // corpo real na Task 4 (single) — placeholder para a rota resolver
    }
}
