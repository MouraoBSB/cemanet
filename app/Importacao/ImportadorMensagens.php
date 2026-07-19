<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportadorMensagens
{
    private array $avisos = [];

    private array $contadores = [];

    public function __construct(
        private LeitorMensagens $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];
        $this->contadores = [
            'com_autor' => 0, 'sem_autor' => 0, 'com_pictografia' => 0,
            'com_download' => 0, 'publish_sem_nivel' => 0, 'falha_foto' => 0,
        ];

        $processadas = 0;

        foreach ($this->leitor->mensagens() as $d) {
            DB::transaction(function () use ($d, $log) {
                $mensagem = Mensagem::firstOrNew(['wp_id' => $d['wp_id']]);
                $novo = ! $mensagem->exists;

                // Conteúdo do legado — SEMPRE atualizado (inclusive no re-import).
                $mensagem->fill([
                    'titulo' => $d['titulo'],
                    'corpo' => $d['corpo'] ?? null,
                    'formato' => $d['formato'] ?? null,
                    'data_recebimento' => TransformadorLegado::unixParaData($d['data_recebimento']),
                    'link_arquivo' => $d['link_arquivo'] ?? null,   // normalizado pelo mutator do model (LinkDrive) — R-A
                    'liberar_download' => TransformadorLegado::statusParaAtivo($d['liberar_download'] ?? null),
                ]);

                // Curadoria — SÓ no create; preservada no re-import (I13). O admin renomeia slug,
                // muda status e classifica os sem-nível pela tela; um re-import não desfaz isso.
                // casa (default 'CEMA') e contexto (manual) NUNCA são setados pelo import.
                if ($novo) {
                    $mensagem->slug = $this->slugUnico($d);
                    $mensagem->status = $d['status'];
                    $mensagem->nivel = $d['nivel'];   // pode ser null — o admin classifica depois
                }

                $mensagem->save();

                // Autores por SLUG (child post_name -> AutorEspiritual.slug). Não resolvido = aviso.
                $ids = [];
                foreach ($d['autores_slugs'] ?? [] as $slugAutor) {
                    $autor = AutorEspiritual::firstWhere('slug', $slugAutor);
                    if ($autor) {
                        $ids[] = $autor->id;
                    } else {
                        $this->avisos[] = "[{$mensagem->slug}] autor não encontrado por slug: {$slugAutor}";
                    }
                }
                $mensagem->autores()->sync($ids);
                $ids ? $this->contadores['com_autor']++ : $this->contadores['sem_autor']++;

                // Pictografia MULTI + O1: baixa todas; só limpa a coleção se ≥1 download deu certo
                // (mensagem sem foto no legado preserva o upload posto no /admin).
                $urls = $d['fotos_urls'] ?? [];
                if (! empty($urls)) {
                    $baixadas = [];
                    foreach ($urls as $url) {
                        $bytes = $this->baixador->baixarCapado($url, 2000);
                        if ($bytes !== null) {
                            $baixadas[] = ['bytes' => $bytes, 'url' => $url];
                        } else {
                            $this->avisos[] = "[{$mensagem->slug}] falha ao baixar pictografia: {$url}";
                            $this->contadores['falha_foto']++;
                        }
                    }
                    if (! empty($baixadas)) {
                        $mensagem->clearMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
                        foreach ($baixadas as $img) {
                            $mensagem->addMediaFromString($img['bytes'])
                                ->usingFileName(basename(parse_url($img['url'], PHP_URL_PATH) ?? 'pictografia.jpg'))
                                ->withCustomProperties(['url_legado' => $img['url']])
                                ->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
                        }
                        $this->contadores['com_pictografia']++;
                    }
                }

                if (! empty($d['link_arquivo']) && TransformadorLegado::statusParaAtivo($d['liberar_download'] ?? null)) {
                    $this->contadores['com_download']++;
                }

                if (($d['status'] ?? null) === Mensagem::STATUS_PUBLICADO && empty($d['nivel'])) {
                    $this->contadores['publish_sem_nivel']++;
                }

                $log("Mensagem importada: {$mensagem->slug}");
            });

            $processadas++;
        }

        return ['mensagens' => $processadas, 'avisos' => $this->avisos, 'contadores' => $this->contadores];
    }

    /** Slug determinístico e único. 39 pending vêm sem post_name: base no título + sufixo wp_id. */
    private function slugUnico(array $d): string
    {
        $base = trim((string) ($d['slug'] ?? ''));
        if ($base !== '') {
            return $base;   // publish/pending com post_name (medido único, 0 dups)
        }

        $slug = Str::slug($d['titulo']).'-'.$d['wp_id'];
        $sufixo = 2;
        while (Mensagem::where('slug', $slug)->exists()) {   // guarda defensiva contra colisão residual
            $slug = Str::slug($d['titulo']).'-'.$d['wp_id'].'-'.$sufixo++;
        }

        return $slug;
    }
}
