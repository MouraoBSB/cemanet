<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Importacao;

use App\Models\Categoria;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class ImportadorBlog
{
    private array $avisos = [];

    public function __construct(
        private LeitorBlog $leitor,
        private BaixadorImagem $baixador,
        private ReescritorImagensConteudo $reescritor,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $posts = $this->leitor->posts();
        $processados = 0;

        foreach ($posts as $d) {
            DB::transaction(function () use ($d, $log) {
                // 1. Converter colunas Gutenberg antes de qualquer uso
                $conteudoLimpo = TransformadorBlog::limparGutenberg($d['conteudo'] ?? '');

                // 2. Montar campos SEM imagem_destacada e og_imagem (vão para a ML)
                $campos = [
                    'titulo' => $d['titulo'],
                    'resumo' => $d['resumo'] ?? null,
                    'conteudo' => $conteudoLimpo,
                    'data_publicacao' => $d['data_publicacao'],
                    'status' => $d['status'],
                    'wp_id' => $d['wp_id'],
                    'imagem_destacada_alt' => $d['imagem_alt'] ?? null,
                    'criado_por_id' => null,
                    'tempo_leitura_min' => TransformadorBlog::tempoLeitura($conteudoLimpo),
                    'seo_titulo' => $d['seo']['titulo'] ?? null,
                    'seo_descricao' => $d['seo']['descricao'] ?? null,
                    'seo_keyword' => $d['seo']['keyword'] ?? null,
                ];

                // 3. Persistir / atualizar o post
                $post = Post::updateOrCreate(['slug' => $d['slug']], $campos);

                // 4. Idempotência: limpa coleções que serão reprocessadas
                $post->clearMediaCollection(Post::COLECAO_DESTACADA);
                $post->clearMediaCollection(Post::COLECAO_OG);
                $post->clearMediaCollection(Post::COLECAO_GALERIA);

                // 5. Imagem destacada
                $urlDestacada = $d['imagem_url'] ?? null;
                if ($urlDestacada) {
                    $bytes = $this->baixador->baixarCapado($urlDestacada, 2000);
                    if ($bytes !== null) {
                        $post->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($urlDestacada, PHP_URL_PATH) ?? 'capa.jpg'))
                            ->withCustomProperties([
                                'alt' => $d['imagem_alt'] ?? null,
                                'url_legado' => $urlDestacada,
                            ])
                            ->toMediaCollection(Post::COLECAO_DESTACADA);
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem destacada";
                    }
                }

                // 6. Imagem OG
                $urlOg = $d['seo']['og_imagem'] ?? null;
                if ($urlOg) {
                    $bytes = $this->baixador->baixarCapado($urlOg, 1200);
                    if ($bytes !== null) {
                        $post->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($urlOg, PHP_URL_PATH) ?? 'og.jpg'))
                            ->withCustomProperties(['url_legado' => $urlOg])
                            ->toMediaCollection(Post::COLECAO_OG);
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem og";
                    }
                }

                // 7. Galeria (ordem respeitada pela inserção sequencial)
                foreach ($d['galeria'] ?? [] as $item) {
                    $bytes = $this->baixador->baixarCapado($item['url'], 2000);
                    if ($bytes === null) {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem da galeria (ordem {$item['ordem']})";

                        continue;
                    }
                    $post->addMediaFromString($bytes)
                        ->usingFileName(basename(parse_url($item['url'], PHP_URL_PATH) ?? 'galeria.jpg'))
                        ->withCustomProperties([
                            'alt' => $item['alt'] ?? null,
                            'url_legado' => $item['url'],
                        ])
                        ->toMediaCollection(Post::COLECAO_GALERIA);
                }

                // 8. Corpo: reescreve imagens internas e atualiza o conteúdo final
                $conteudoFinal = $this->reescritor->reescrever($conteudoLimpo, $d['slug'], $post);
                if ($conteudoFinal !== $post->conteudo) {
                    $post->update(['conteudo' => $conteudoFinal]);
                }

                // 9. Categorias
                $slugsConhecidos = $d['categorias_slugs'] ?? [];
                $categorias = Categoria::whereIn('slug', $slugsConhecidos)->get();
                $slugsEncontrados = $categorias->pluck('slug')->all();
                foreach (array_diff($slugsConhecidos, $slugsEncontrados) as $slug) {
                    $this->avisos[] = "[{$d['slug']}] categoria desconhecida: {$slug}";
                }
                $ids = $categorias->pluck('id')->all();
                $post->categorias()->sync($ids);

                // categoria_principal_id
                $principalSlug = $d['categoria_principal_slug'] ?? null;
                $principalId = null;
                if ($principalSlug) {
                    $principalId = $categorias->firstWhere('slug', $principalSlug)?->id;
                }
                if ($principalId === null && ! empty($ids)) {
                    $principalId = $ids[0];
                }
                $post->update(['categoria_principal_id' => $principalId]);

                // 10. Tags
                $tagIds = [];
                foreach ($d['tags'] ?? [] as $t) {
                    $tag = Tag::firstOrCreate(['slug' => $t['slug']], ['nome' => $t['nome']]);
                    $tagIds[] = $tag->id;
                }
                $post->tags()->sync($tagIds);

                // 11. FAQs (delete + recreate)
                $post->faqs()->delete();
                foreach ($d['faqs'] ?? [] as $faq) {
                    $post->faqs()->create($faq);
                }

                $log("Post importado: {$d['slug']}");
            });

            $processados++;
        }

        return [
            'posts' => $processados,
            'avisos' => $this->avisos,
        ];
    }
}
