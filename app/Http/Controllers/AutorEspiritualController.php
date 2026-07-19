<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class AutorEspiritualController extends Controller
{
    public function index(): View
    {
        abort(501);   // corpo real na Task 5
    }

    public function show(string $slug): View
    {
        abort(501);   // corpo real na Task 6
    }
}
