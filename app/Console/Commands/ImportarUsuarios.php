<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Console\Commands;

use App\Importacao\ImportadorUsuarios;
use App\Importacao\LeitorUsuarios;
use App\Importacao\LeitorUsuariosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarUsuarios extends Command
{
    protected $signature = 'cema:importar-usuarios';

    protected $description = 'Importa os usuários do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorUsuarios $leitor, ImportadorUsuarios $importador): int
    {
        if ($leitor instanceof LeitorUsuariosMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (\Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->line($m));

        $this->newLine();
        $this->info("Concluído: {$resumo['usuarios']} usuários importados, {$resumo['ignorados']} ignorados.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
