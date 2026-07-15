<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Http\Controllers;

use App\Models\Palestra;
use App\Support\Conta\AbaAgenda;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class ContaController extends Controller
{
    public function painel(): View
    {
        auth()->user()->perfil()->firstOrCreate([]);

        $proximas = Palestra::publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', Carbon::today())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(4)
            ->get();

        return view('conta.painel', compact('proximas'));
    }

    public function perfil(): View
    {
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);
        $user->load(['setores', 'cargos', 'roles']);

        return view('conta.perfil', compact('user', 'perfil'));
    }

    public function agenda(): View
    {
        abort_unless(AbaAgenda::visivelPara(auth()->user()), 403);

        return view('conta.agenda');
    }
}
