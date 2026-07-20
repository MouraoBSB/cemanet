<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Importacao;

use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportadorDirecionadasMensagens
{
    public function __construct(private LeitorDirecionadasMensagem $leitor) {}

    /**
     * @return array{direcionadas:int, vinculos:int, destinatarios_distintos:int,
     *               mensagem_nao_encontrada:int, user_nao_encontrado:int, avisos:array<int,string>}
     */
    public function importar(callable $log): array
    {
        $direcionadas = 0;
        $vinculos = 0;
        $mensagemNaoEncontrada = 0;
        $userNaoEncontrado = 0;
        $distintos = [];
        $avisos = [];

        foreach ($this->leitor->direcionadas() as $item) {
            $mensagem = Mensagem::where('wp_id', $item['wp_id'])->first();
            if ($mensagem === null) {
                $mensagemNaoEncontrada++;
                $avisos[] = "Mensagem wp_id {$item['wp_id']} não encontrada (direcionada ignorada).";

                continue;
            }

            $ids = [];
            foreach ($item['destinatarios_wp_ids'] as $wpUserId) {
                $user = User::where('origem_legado_id', $wpUserId)->first();
                if ($user === null) {
                    $userNaoEncontrado++;
                    $avisos[] = "Destinatário wp_user_id {$wpUserId} sem User novo (vínculo omitido) — msg {$item['wp_id']}.";

                    continue;
                }
                $ids[] = $user->id;
                $distintos[$user->id] = true;
            }

            DB::transaction(fn () => $mensagem->destinatarios()->sync($ids));
            $vinculos += count($ids);
            $direcionadas++;
            $log("Direcionada wp_id {$item['wp_id']}: ".count($ids).' destinatário(s).');
        }

        return [
            'direcionadas' => $direcionadas,
            'vinculos' => $vinculos,
            'destinatarios_distintos' => count($distintos),
            'mensagem_nao_encontrada' => $mensagemNaoEncontrada,
            'user_nao_encontrado' => $userNaoEncontrado,
            'avisos' => $avisos,
        ];
    }
}
