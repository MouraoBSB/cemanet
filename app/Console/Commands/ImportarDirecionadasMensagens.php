<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Console\Commands;

use App\Importacao\ImportadorDirecionadasMensagens;
use App\Importacao\LeitorDirecionadasMensagem;
use App\Importacao\LeitorDirecionadasMensagemMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarDirecionadasMensagens extends Command
{
    protected $signature = 'cema:importar-direcionadas';

    protected $description = 'Importa os destinatários das mensagens direcionadas do legado (rel 38, SELECT-only, idempotente).';

    public function handle(LeitorDirecionadasMensagem $leitor, ImportadorDirecionadasMensagens $importador): int
    {
        // Só exige a conexão legado com o leitor real (o teste injeta fake).
        if ($leitor instanceof LeitorDirecionadasMensagemMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (Throwable $e) {
                $this->error('Sem conexão com o legado (túnel SSH). '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->line($m));

        $this->info("Direcionadas: {$resumo['direcionadas']} · vínculos: {$resumo['vinculos']} · destinatários distintos: {$resumo['destinatarios_distintos']}");
        $this->info("Mensagens não encontradas: {$resumo['mensagem_nao_encontrada']} · destinatários sem User: {$resumo['user_nao_encontrado']}");
        foreach ($resumo['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        return self::SUCCESS;
    }
}
