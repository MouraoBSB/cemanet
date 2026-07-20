<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;
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

    public function show(Request $request, string $slug): Response
    {
        $usuario = $request->user();

        // 404 real: inexistente OU não-publicada (status). Ainda NÃO filtra por nível.
        $mensagem = Mensagem::query()->publicado()->where('slug', $slug)->firstOrFail();

        if (! $mensagem->podeSerVistoPor($usuario)) {
            // Grava url.intended na sessão (efeito colateral) ANTES de qualquer login — sobrevive ao regenerate e ao
            // round-trip do Google. NÃO "corrigir" para `return redirect()->...`: o retorno é descartado DE PROPÓSITO.
            redirect()->setIntendedUrl(url()->current());

            return response()
                ->view('mensagens.barreira', ['modo' => $usuario === null ? 'login' : 'sem-permissao'])
                ->header('Cache-Control', 'private, no-store'); // barreira nunca é cacheável por proxy
        }

        // AUTORIZADO: carrega o resto por visiveisPara($usuario) (mesmoDia/relacionadas não vazam).
        $mensagem->load(['autores', 'media', 'relacionadas' => fn ($q) => $q->publicado()->visiveisPara($usuario)]);

        $mesmoDia = $mensagem->data_recebimento
            ? Mensagem::query()->publicado()->visiveisPara($usuario)
                ->whereDate('data_recebimento', $mensagem->data_recebimento->format('Y-m-d'))
                ->where('id', '!=', $mensagem->id)
                ->orderBy('titulo')
                ->get()
            : collect();

        // Nota "Direcionada a você" (Task 6): só se ESTE usuário é destinatário (não a um bypass admin/presidente).
        $ehDestinatario = $usuario !== null
            && $mensagem->visibilidade() === VisibilidadeMensagem::Direcionada
            && $mensagem->destinatarios()->whereKey($usuario->id)->exists();

        $resposta = response()->view('mensagens.show', [
            'mensagem' => $mensagem,
            'mesmoDia' => $mesmoDia,
            'relacionadas' => $mensagem->relacionadas,
            'ehDestinatario' => $ehDestinatario,
        ]);

        if ($mensagem->visibilidade() !== VisibilidadeMensagem::Publico) {
            $resposta->header('Cache-Control', 'private, no-store'); // restrito (ou null) não é cacheável
        }

        return $resposta;
    }
}
