<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Support\Palestras\CardinalidadePalestra;
use Illuminate\Support\Facades\DB;

class ImportadorPalestras
{
    private array $avisos = [];

    public function __construct(
        private LeitorLegado $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $log('Importando assuntos...');
        $nAssuntos = $this->importarAssuntos();

        $log('Importando palestrantes...');
        $nPalestrantes = $this->importarPalestrantes();

        $log('Importando palestras...');
        $nPalestras = $this->importarPalestras();

        return [
            'assuntos' => $nAssuntos,
            'palestrantes' => $nPalestrantes,
            'palestras' => $nPalestras,
            'avisos' => $this->avisos,
        ];
    }

    private function importarAssuntos(): int
    {
        $dados = $this->leitor->assuntos();

        return DB::transaction(function () use ($dados) {
            // 1ª passada: upsert sem parent_id para garantir que todos existam
            foreach ($dados as $a) {
                Assunto::updateOrCreate(
                    ['slug' => $a['slug']],
                    ['nome' => $a['nome']],
                );
            }

            // 2ª passada: resolve parent_id por slug
            foreach ($dados as $a) {
                if (! empty($a['parent_slug'])) {
                    $pai = Assunto::where('slug', $a['parent_slug'])->first();
                    if ($pai) {
                        Assunto::where('slug', $a['slug'])->update(['parent_id' => $pai->id]);
                    }
                }
            }

            return count($dados);
        });
    }

    private function importarPalestrantes(): int
    {
        $dados = $this->leitor->palestrantes();

        foreach ($dados as $p) {
            $foto = $this->baixador->baixar($p['foto_url'] ?? null, $p['slug']);

            $palestrante = Palestrante::updateOrCreate(['slug' => $p['slug']], [
                'nome' => $p['nome'],
                'bio' => $p['bio'] ?? null,
                'email' => $p['email'] ?? null,
                'telefone' => $p['telefone'] ?? null,
                'mostrar_email' => $p['mostrar_email'] ?? false,
                'mostrar_telefone' => $p['mostrar_telefone'] ?? false,
                'ativo' => $p['ativo'] ?? true,
            ]);

            // Foto vai para a Media Library (coleção 'foto'); singleFile substitui a anterior.
            if ($foto !== null && \Illuminate\Support\Facades\Storage::disk('public')->exists($foto)) {
                $palestrante->addMedia(\Illuminate\Support\Facades\Storage::disk('public')->path($foto))
                    ->toMediaCollection(Palestrante::COLECAO_FOTO);
            }
        }

        return count($dados);
    }

    private function importarPalestras(): int
    {
        $dados = $this->leitor->palestras();

        foreach ($dados as $d) {
            DB::transaction(function () use ($d) {
                $palestra = Palestra::updateOrCreate(
                    ['slug' => $d['slug']],
                    [
                        'titulo' => $d['titulo'],
                        'subtitulo' => $d['subtitulo'] ?? null,
                        'resumo' => $d['resumo'] ?? null,
                        'descricao' => $d['descricao'] ?? null,
                        'data_da_palestra' => $d['data_da_palestra'],
                        'online' => $d['online'] ?? false,
                        'link_youtube' => $d['link_youtube'] ?? null,
                        'cor_fundo' => $d['cor_fundo'] ?? null,
                        'publico_online' => $d['publico_online'] ?? null,
                        'publico_presencial' => $d['publico_presencial'] ?? null,
                        'publico_total' => $d['publico_total'] ?? null,
                        'status' => $d['status'] ?? 'publicado',
                    ]
                );

                // monta pivô palestra_pessoa (sync substitui sem duplicar).
                // Assunção (válida nos dados do legado): uma pessoa não é, ao mesmo tempo,
                // palestrante E diretor da MESMA palestra. Como o mapa é indexado por pessoa_id,
                // esse caso (inexistente hoje) sobrescreveria um papel pelo outro.
                $sync = [];
                foreach ($d['palestrantes_slugs'] as $slug) {
                    $pid = Palestrante::where('slug', $slug)->value('id');
                    if ($pid) {
                        $sync[$pid] = ['papel' => Palestra::PAPEL_PALESTRANTE];
                    }
                }
                if (! empty($d['diretor_slug'])) {
                    $did = Palestrante::where('slug', $d['diretor_slug'])->value('id');
                    if ($did) {
                        $sync[$did] = ['papel' => Palestra::PAPEL_DIRETOR];
                    }
                }
                $palestra->palestrantes()->sync($sync);

                // valida cardinalidade; violações viram aviso, não exception
                $nPal = collect($sync)->where('papel', Palestra::PAPEL_PALESTRANTE)->count();
                $nDir = collect($sync)->where('papel', Palestra::PAPEL_DIRETOR)->count();
                foreach (CardinalidadePalestra::erros($nPal, $nDir) as $erro) {
                    $this->avisos[] = "[{$d['slug']}] {$erro}";
                }

                // assuntos N:N — sync garante idempotência
                $assuntoIds = Assunto::whereIn('slug', $d['assuntos_slugs'])->pluck('id')->all();
                $palestra->assuntos()->sync($assuntoIds);

                // destaques: delete + recreate (ordem pode mudar entre importações)
                $palestra->destaques()->delete();
                foreach ($d['destaques'] as $dest) {
                    $palestra->destaques()->create($dest);
                }
            });
        }

        return count($dados);
    }
}
