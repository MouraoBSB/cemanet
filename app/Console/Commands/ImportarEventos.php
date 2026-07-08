<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Console\Commands;

use App\Importacao\ImportadorEventos;
use App\Importacao\LeitorEventos;
use App\Importacao\LeitorEventosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarEventos extends Command
{
    protected $signature = 'cema:importar-eventos';

    protected $description = 'Importa os eventos (_evento) do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorEventos $leitor, ImportadorEventos $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorEventosMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (\Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->info($m));

        $this->newLine();
        $this->info("Importação concluída: {$resumo['eventos']} eventos.");
        $c = $resumo['contadores'];
        $this->line("  Públicos: {$c['publicos']} · Diretoria: {$c['diretoria']} · Sem categoria: {$c['sem_categoria']} · Deptos não resolvidos: {$c['deptos_nao_resolvidos']}");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
