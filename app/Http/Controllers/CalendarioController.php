<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-30

namespace App\Http\Controllers;

use App\Models\Palestra;
use Illuminate\Contracts\View\View;

class CalendarioController extends Controller
{
    /**
     * Stub da página de Calendário: lista as próximas palestras publicadas.
     * A fatia do módulo Calendário substitui o corpo por <livewire:palestras.calendario />.
     */
    public function index(): View
    {
        $proximas = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->orderBy('data_da_palestra')
            ->get();

        return view('pages.calendario', ['proximas' => $proximas]);
    }
}
