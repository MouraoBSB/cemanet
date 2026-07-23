<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Console\Commands;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarResumosMensagens extends Command
{
    /** Piso medido: o legado tem 3 excerpts que são só pontuação (".", ".", "......"). */
    private const MINIMO_CARACTERES = 20;

    protected $signature = 'cema:importar-resumos';

    protected $description = 'Importa o post_excerpt das mensagens do WordPress legado para a coluna resumo (só preenche o que está vazio, SELECT-only, idempotente).';

    public function handle(LeitorResumosMensagens $leitor): int
    {
        // Só exige a conexão legado com o leitor real (o teste injeta fake) — molde ImportarMensagens:21-32.
        if ($leitor instanceof LeitorResumosMensagensMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $linhas = $leitor->resumos();
        $contadores = ['atualizadas' => 0, 'ja_tinha' => 0, 'sem_mensagem' => 0, 'curtas' => 0];
        $descartadas = [];

        // UM envelope para o laço inteiro: withoutLogs recebe Closure, tem finally que
        // re-habilita e guarda de reentrância; o ActivityLogStatus é scoped. Sem isto, cada
        // linha vira "mensagem atualizada" no histórico que o diretor do DEPAE lê na curadoria.
        activity()->withoutLogs(function () use ($linhas, &$contadores, &$descartadas): void {
            foreach ($linhas as $linha) {
                $texto = trim((string) ($linha['resumo'] ?? ''));

                // Os quatro contadores são mutuamente exclusivos (os `continue` garantem isso):
                // curtas + sem_mensagem + ja_tinha + atualizadas == total lido.
                if (mb_strlen($texto) < self::MINIMO_CARACTERES) {
                    $contadores['curtas']++;
                    $descartadas[] = "wp_id {$linha['wp_id']}: \"{$texto}\"";

                    continue;
                }

                // firstWhere, NUNCA firstOrNew/updateOrCreate: excerpt órfão não cria mensagem.
                $mensagem = Mensagem::firstWhere('wp_id', $linha['wp_id']);

                if ($mensagem === null) {
                    $contadores['sem_mensagem']++;

                    continue;
                }

                // blank() cobre null E '' — o critério "vazio" é estável entre execuções.
                if (! blank($mensagem->resumo)) {
                    $contadores['ja_tinha']++;

                    continue;
                }

                $mensagem->resumo = $texto;
                $mensagem->save();
                $contadores['atualizadas']++;
            }
        });

        $this->newLine();
        $this->info('Importação de resumos concluída.');
        $this->line("  Atualizadas: {$contadores['atualizadas']} · Já tinham resumo: {$contadores['ja_tinha']} · Sem mensagem no banco: {$contadores['sem_mensagem']} · Descartadas por serem curtas: {$contadores['curtas']}");

        if ($descartadas !== []) {
            $this->warn('  Descartadas (confira se alguma é sinopse legítima):');
            foreach ($descartadas as $item) {
                $this->line('    - '.$item);
            }
        }

        return self::SUCCESS;
    }
}
