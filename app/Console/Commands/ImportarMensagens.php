<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Console\Commands;

use App\Importacao\ImportadorMensagens;
use App\Importacao\LeitorMensagens;
use App\Importacao\LeitorMensagensMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarMensagens extends Command
{
    protected $signature = 'cema:importar-mensagens';

    protected $description = 'Importa as mensagens mediúnicas (CPT mensagem-mediunicas) do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorMensagens $leitor, ImportadorMensagens $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorMensagensMysql) {
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
        $this->info("Importação concluída: {$resumo['mensagens']} mensagens.");
        $c = $resumo['contadores'];
        $this->line("  Com autor: {$c['com_autor']} · Sem autor: {$c['sem_autor']} · Com pictografia: {$c['com_pictografia']} · Com download: {$c['com_download']} · Publish sem nível: {$c['publish_sem_nivel']} · Falha de foto: {$c['falha_foto']}");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
