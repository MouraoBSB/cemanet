<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Http\Controllers;

use App\Enums\VisibilidadeMensagem;
use App\Models\Palestra;
use App\Support\Conta\AbaAgenda;
use App\Support\Conta\AbaCuradoria;
use App\Support\Conta\AbaDirecionadas;
use App\Support\Conta\AbaMensagens;
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

    public function mensagens(): View
    {
        abort_unless(AbaMensagens::visivelPara(auth()->user()), 403);

        return view('conta.mensagens');
    }

    public function direcionadas(): View
    {
        $user = auth()->user();
        abort_unless(AbaDirecionadas::visivelPara($user), 403);

        $direcionadas = $user->mensagensDirecionadas()
            ->publicado()
            ->where('nivel', VisibilidadeMensagem::Direcionada->value)   // blindagem O5 (I7): só direcionadas
            ->with('autores', 'media')          // eager-load: autor (card) + media (miniatura pictografia) — sem N+1
            ->orderByDesc('data_recebimento')
            ->get();

        return view('conta.direcionadas', compact('direcionadas'));
    }

    public function curadoria(): View
    {
        abort_unless(AbaCuradoria::visivelPara(auth()->user()), 403);

        return view('conta.curadoria');
    }
}
