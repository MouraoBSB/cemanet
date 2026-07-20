<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Models\Mensagem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MensagemController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $resposta = response()->view('mensagens.index', [
            'total' => Mensagem::publicado()->visiveisPara($usuario)->count(),
            'logado' => $usuario !== null,
        ]);

        if ($usuario !== null) {
            $resposta->header('Cache-Control', 'private, no-store'); // R2: contagem/lista variam por usuário
        }

        return $resposta;
    }

    public function show(string $slug): View
    {
        $mensagem = Mensagem::query()
            ->publica()
            ->with(['autores', 'media', 'relacionadas' => fn ($q) => $q->publica()])
            ->where('slug', $slug)
            ->firstOrFail();

        // "Recebidas no mesmo dia": outras públicas com a mesma data (só se houver data).
        // Sidebar dessa seção e de "relacionadas" só mostra título/formato/data — sem with('autores').
        $mesmoDia = $mensagem->data_recebimento
            ? Mensagem::query()->publica()
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
