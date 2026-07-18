<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

use App\Models\AutorEspiritual;
use Illuminate\Support\Facades\DB;

class ImportadorAutoresEspirituais
{
    private array $avisos = [];

    private array $contadores = [];

    public function __construct(
        private LeitorAutoresEspirituais $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];
        $this->contadores = ['com_foto' => 0, 'sem_thumbnail' => 0, 'falha_foto' => 0];

        $processados = 0;

        foreach ($this->leitor->autores() as $d) {
            DB::transaction(function () use ($d, $log) {
                // Chave = slug (não há wp_id). NÃO setar chamada/ativo: são do admin (I10) —
                // chamada nasce null, ativo default true; ambos preservados num re-import.
                $autor = AutorEspiritual::updateOrCreate(
                    ['slug' => $d['slug']],
                    ['nome' => $d['nome'], 'bio' => $d['bio'] ?? null],
                );

                // O1: o legado sobrescreve a foto SÓ quando tem thumbnail; e o clearMediaCollection roda
                // SÓ após um download bem-sucedido. Assim autor sem thumbnail (ou download falho) preserva
                // a foto posta no /admin. Idempotente: mesmo thumbnail => mesma foto reanexada (1 mídia).
                if (! empty($d['foto_url'])) {
                    $bytes = $this->baixador->baixarCapado($d['foto_url'], 2000);
                    if ($bytes !== null) {
                        $autor->clearMediaCollection(AutorEspiritual::COLECAO_FOTO);
                        $autor->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($d['foto_url'], PHP_URL_PATH) ?? 'foto.jpg'))
                            ->withCustomProperties(['url_legado' => $d['foto_url']])
                            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
                        $this->contadores['com_foto']++;
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar foto (mídia existente preservada)";
                        $this->contadores['falha_foto']++;
                    }
                } else {
                    $this->contadores['sem_thumbnail']++;
                }

                $log("Autor importado: {$d['slug']}");
            });

            $processados++;
        }

        return ['autores' => $processados, 'avisos' => $this->avisos, 'contadores' => $this->contadores];
    }
}
