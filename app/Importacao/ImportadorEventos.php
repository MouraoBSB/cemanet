<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use App\Enums\VisibilidadeEvento;
use App\Models\CategoriaEvento;
use App\Models\Departamento;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;

class ImportadorEventos
{
    private array $avisos = [];

    private array $contadores = [];

    public function __construct(
        private LeitorEventos $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];
        $this->contadores = ['publicos' => 0, 'diretoria' => 0, 'sem_categoria' => 0, 'deptos_nao_resolvidos' => 0];

        $processados = 0;

        foreach ($this->leitor->eventos() as $d) {
            $dataHora = TransformadorLegado::unixParaData($d['data_do_evento'] ?? null);

            // Defensivo: (int) de uma string de data ("2026-...") vira ano-inteiro e gera 1970.
            // O legado é TS Unix confirmado; um resultado anterior a 2000 é lixo → pula com aviso.
            if ($dataHora === null || $dataHora->year < 2000) {
                $this->avisos[] = "[{$d['slug']}] sem data_do_evento válida — evento pulado";

                continue;
            }

            DB::transaction(function () use ($d, $dataHora, $log) {
                // Hora: ausente = mostra (default-ON do legado); presente-e-falsy = esconde.
                $mostraHora = ($d['mostrar_horario_definido'] ?? false)
                    ? TransformadorLegado::statusParaAtivo($d['mostrar_horario'] ?? null)
                    : true;

                // Visibilidade: público (true) senão fail-closed em diretoria.
                $publico = TransformadorLegado::statusParaAtivo($d['evento_publico'] ?? null);
                $visibilidade = $publico ? VisibilidadeEvento::Publico : VisibilidadeEvento::Diretoria;
                if ($publico) {
                    $this->contadores['publicos']++;
                } else {
                    $this->contadores['diretoria']++;
                    $this->avisos[] = "[{$d['slug']}] evento não-público → visibilidade=diretoria (revisar)";
                }

                // Categoria (heurística pelo título).
                $catSlug = ClassificadorCategoria::paraSlug((string) ($d['titulo'] ?? ''));
                $categoriaId = $catSlug ? CategoriaEvento::where('slug', $catSlug)->value('id') : null;
                if ($categoriaId === null) {
                    $this->contadores['sem_categoria']++;
                    $this->avisos[] = "[{$d['slug']}] categoria não inferida (revisar no admin)";
                }

                $evento = Evento::updateOrCreate(['slug' => $d['slug']], [
                    'titulo' => $d['titulo'],
                    'resumo' => $d['resumo'] ?? null,
                    'conteudo' => $d['conteudo'] ?? null,
                    'data_inicio' => $dataHora->format('Y-m-d'),
                    'hora_inicio' => $mostraHora ? $dataHora->format('H:i') : null,
                    'data_fim' => null,
                    'hora_fim' => null,
                    'local' => $d['local'] ?? null,
                    'categoria_evento_id' => $categoriaId,
                    'visibilidade' => $visibilidade,
                    'status' => Evento::STATUS_PUBLICADO,
                    'wp_id' => $d['wp_id'],
                ]);

                // Idempotência de mídia: limpa antes de reanexar.
                $evento->clearMediaCollection(Evento::COLECAO_FLYER);
                $evento->clearMediaCollection(Evento::COLECAO_GALERIA);

                if ($d['flyer_url'] ?? null) {
                    $bytes = $this->baixador->baixarCapado($d['flyer_url'], 2000);
                    if ($bytes !== null) {
                        $evento->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($d['flyer_url'], PHP_URL_PATH) ?? 'flyer.jpg'))
                            ->withCustomProperties(['url_legado' => $d['flyer_url']])
                            ->toMediaCollection(Evento::COLECAO_FLYER);
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar flyer";
                    }
                }

                foreach ($d['galeria_urls'] ?? [] as $url) {
                    $bytes = $this->baixador->baixarCapado($url, 2000);
                    if ($bytes === null) {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem da galeria";

                        continue;
                    }
                    $evento->addMediaFromString($bytes)
                        ->usingFileName(basename(parse_url($url, PHP_URL_PATH) ?? 'galeria.jpg'))
                        ->withCustomProperties(['url_legado' => $url])
                        ->toMediaCollection(Evento::COLECAO_GALERIA);
                }

                // Departamentos por sigla.
                $siglas = $d['departamentos_siglas'] ?? [];
                $departamentos = Departamento::whereIn('sigla', $siglas)->get();
                foreach (array_diff($siglas, $departamentos->pluck('sigla')->all()) as $sigla) {
                    $this->contadores['deptos_nao_resolvidos']++;
                    $this->avisos[] = "[{$d['slug']}] departamento não resolvido: {$sigla}";
                }
                $evento->departamentos()->sync($departamentos->pluck('id')->all());

                $log("Evento importado: {$d['slug']}");
            });

            $processados++;
        }

        return ['eventos' => $processados, 'avisos' => $this->avisos, 'contadores' => $this->contadores];
    }
}
