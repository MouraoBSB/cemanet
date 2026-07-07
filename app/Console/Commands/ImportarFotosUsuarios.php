<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Console\Commands;

use App\Importacao\ImportadorFotosUsuarios;
use App\Importacao\LeitorUsuarios;
use App\Importacao\LeitorUsuariosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarFotosUsuarios extends Command
{
    protected $signature = 'cema:importar-fotos-usuarios';

    protected $description = 'Migra as fotos de perfil dos usuários do legado (somente leitura) para a Media Library.';

    public function handle(LeitorUsuarios $leitor, ImportadorFotosUsuarios $importador): int
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

        $r = $importador->importar(fn (string $m) => $this->line($m));

        $this->newLine();
        $this->info("Concluído: {$r['anexadas']} anexadas, {$r['puladas']} puladas, {$r['sem_candidata']} sem candidata, {$r['falhas']} falhas.");
        foreach ($r['avisos'] as $aviso) {
            $this->warn('  - '.$aviso);
        }

        return self::SUCCESS;
    }
}
