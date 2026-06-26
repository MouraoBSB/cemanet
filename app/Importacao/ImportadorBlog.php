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
                // 1. Reescrever conteúdo (imagens internas)
                $conteudo = $this->reescritor->reescrever($d['conteudo'] ?? '', $d['slug']);

                // 2. Baixar imagem destacada
                $imagem = $this->baixador->baixarPara($d['imagem_url'] ?? null, 'blog/destacada', $d['slug']);

                // 3. updateOrCreate pelo slug
                $campos = [
                    'titulo'               => $d['titulo'],
                    'resumo'               => $d['resumo'] ?? null,
                    'conteudo'             => $conteudo,
                    'data_publicacao'      => $d['data_publicacao'],
                    'status'               => $d['status'],
                    'wp_id'                => $d['wp_id'],
                    'imagem_destacada_alt' => $d['imagem_alt'] ?? null,
                    'criado_por_id'        => null,
                    'tempo_leitura_min'    => TransformadorBlog::tempoLeitura($conteudo),
                    'seo_titulo'           => $d['seo']['titulo'] ?? null,
                    'seo_descricao'        => $d['seo']['descricao'] ?? null,
                    'seo_keyword'          => $d['seo']['keyword'] ?? null,
                    'og_imagem'            => $d['seo']['og_imagem'] ?? null,
                ];

                // só sobrescreve imagem_destacada se o download teve sucesso
                if ($imagem !== null) {
                    $campos['imagem_destacada'] = $imagem;
                }

                $post = Post::updateOrCreate(['slug' => $d['slug']], $campos);

                // 4. Categorias
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

                // 5. Tags
                $tagIds = [];
                foreach ($d['tags'] ?? [] as $t) {
                    $tag = Tag::firstOrCreate(['slug' => $t['slug']], ['nome' => $t['nome']]);
                    $tagIds[] = $tag->id;
                }
                $post->tags()->sync($tagIds);

                // 6. FAQs (delete + recreate)
                $post->faqs()->delete();
                foreach ($d['faqs'] ?? [] as $faq) {
                    $post->faqs()->create($faq);
                }

                // 7. Galeria (delete + recreate)
                $post->imagens()->delete();
                foreach ($d['galeria'] ?? [] as $item) {
                    $ordem = $item['ordem'];
                    $caminho = $this->baixador->baixarPara($item['url'], 'blog/galeria', $d['slug'].'-'.$ordem);
                    if ($caminho === null) {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem da galeria (ordem {$ordem})";

                        continue;
                    }
                    $post->imagens()->create([
                        'caminho'    => $caminho,
                        'url_legado' => $item['url'],
                        'ordem'      => $ordem,
                    ]);
                }

                $log("Post importado: {$d['slug']}");
            });

            $processados++;
        }

        return [
            'posts'  => $processados,
            'avisos' => $this->avisos,
        ];
    }
}
