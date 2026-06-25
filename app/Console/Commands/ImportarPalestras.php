<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Console\Commands;

use App\Importacao\ImportadorPalestras;
use App\Importacao\LeitorLegado;
use App\Importacao\LeitorLegadoMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarPalestras extends Command
{
    protected $signature = 'cema:importar-palestras';

    protected $description = 'Importa as palestras do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorLegado $leitor, ImportadorPalestras $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorLegadoMysql) {
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
        $this->info("Importação concluída: {$resumo['assuntos']} assuntos, {$resumo['palestrantes']} palestrantes, {$resumo['palestras']} palestras.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos de cardinalidade ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
