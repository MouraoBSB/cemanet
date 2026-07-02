<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Console\Commands;

use App\Importacao\ImportadorAgenda;
use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarAgenda extends Command
{
    protected $signature = 'cema:importar-agenda';

    protected $description = 'Importa a Agenda Reforma Íntima do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorAgenda $leitor, ImportadorAgenda $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorAgendaMysql) {
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
        $this->info("Importação concluída: {$resumo['metas']} metas de mês, {$resumo['dias']} dias, {$resumo['slugs']} slugs.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
