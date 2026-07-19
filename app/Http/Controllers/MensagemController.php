<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\Mensagem;
use Illuminate\Contracts\View\View;

class MensagemController extends Controller
{
    public function index(): View
    {
        return view('mensagens.index');   // corpo real na Task 3
    }

    public function show(string $slug): View
    {
        $mensagem = Mensagem::query()
            ->publica()
            ->with(['autores', 'media', 'relacionadas' => fn ($q) => $q->publica()->with('autores')])
            ->where('slug', $slug)
            ->firstOrFail();

        // "Recebidas no mesmo dia": outras públicas com a mesma data (só se houver data).
        $mesmoDia = $mensagem->data_recebimento
            ? Mensagem::query()->publica()->with('autores')
                ->whereDate('data_recebimento', $mensagem->data_recebimento->format('Y-m-d'))
                ->where('id', '!=', $mensagem->id)
                ->orderBy('titulo')
                ->get()
            : collect();

        return view('mensagens.show', [
            'mensagem' => $mensagem,
            'mesmoDia' => $mesmoDia,
            'relacionadas' => $mensagem->relacionadas,
        ]);
    }
}
