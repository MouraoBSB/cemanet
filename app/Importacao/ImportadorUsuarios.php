<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use App\Models\Atributo;
use App\Models\Cargo;
use App\Models\CursoRealizado;
use App\Models\PerfilMembro;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportadorUsuarios
{
    public function __construct(
        private LeitorUsuarios $leitor,
        private TransformadorUsuarios $transformador,
    ) {}

    public function importar(callable $log): array
    {
        $setoresPorSlug = Setor::pluck('id', 'slug');
        $cargosPorSlug = Cargo::pluck('id', 'slug');
        $socioId = Atributo::where('slug', 'socio')->value('id');

        $usuarios = 0;
        $ignorados = 0;
        $avisos = [];

        foreach ($this->leitor->usuarios() as $bruto) {
            $papel = $this->transformador->papelDe($bruto['roles']);
            if ($papel === null || $papel === 'administrador') {
                $ignorados++;
                $avisos[] = "Ignorado (papel {$this->join($bruto['roles'])}): {$bruto['email']}";

                continue;
            }
            if (! str_starts_with($bruto['senha'], '$wp') && ! str_starts_with($bruto['senha'], '$P$')) {
                $ignorados++;
                $avisos[] = "Ignorado (hash não reconhecido): {$bruto['email']}";

                continue;
            }

            DB::transaction(function () use ($bruto, $papel, $setoresPorSlug, $cargosPorSlug, $socioId, &$avisos) {
                $existe = User::where('origem_legado_id', $bruto['origem_id'])->first();

                $dados = [
                    'name' => $this->transformador->nomeTitulo($bruto['nome']),
                    'email' => mb_strtolower(trim($bruto['email'])),
                    'ativo' => true,
                    'socio' => $this->transformador->flagTresEstados($bruto['socio']) === true,
                    'email_verified_at' => $bruto['registrado'] ?? now(),
                ];

                // senha = hash bruto (o cast 'hashed' foi removido do User): só grava no
                // create ou enquanto o hash ainda for legado; nunca sobrescreve bcrypt modernizado.
                $atual = $existe?->password;
                if (! $atual || str_starts_with($atual, '$wp') || str_starts_with($atual, '$P$')) {
                    $dados['password'] = $bruto['senha'];
                }

                $user = User::updateOrCreate(['origem_legado_id' => $bruto['origem_id']], $dados);

                $user->syncRoles([$papel]);

                $setores = [];
                foreach ($this->transformador->resolverSetores($bruto['setores']) as $s) {
                    if (isset($setoresPorSlug[$s['slug']])) {
                        $setores[$setoresPorSlug[$s['slug']]] = ['funcao' => $s['funcao']];
                    } else {
                        $avisos[] = "Setor não resolvido '{$s['slug']}' para {$bruto['email']}";
                    }
                }
                $user->setores()->sync($setores);

                $cargos = [];
                foreach ($this->transformador->resolverCargos($bruto['cargos']) as $slug) {
                    if (isset($cargosPorSlug[$slug])) {
                        $cargos[] = $cargosPorSlug[$slug];
                    } else {
                        $avisos[] = "Cargo não resolvido '{$slug}' para {$bruto['email']}";
                    }
                }
                $user->cargos()->sync($cargos);

                $user->atributos()->sync(
                    $user->socio && $socioId ? [$socioId] : []
                );

                $this->perfil($user, $bruto['meta']);
            });

            $usuarios++;
            $log("Importado: {$bruto['email']}");
        }

        return ['usuarios' => $usuarios, 'ignorados' => $ignorados, 'avisos' => $avisos];
    }

    private function perfil(User $user, array $meta): void
    {
        PerfilMembro::updateOrCreate(
            ['user_id' => $user->id],
            [
                'whatsapp' => $meta['whatsapp'] ?? null,
                'whatsapp_publico' => $this->transformador->flagTresEstados($meta['whatsapp_publico'] ?? null) === true,
                'data_nascimento' => $this->data($meta['nascimento'] ?? null),
                'endereco' => $meta['endereco'] ?? null,
            ],
        );

        // cursos_realizados (repeater serializado) → delete + recriação ordenada
        $user->cursos()->delete();
        $cursos = @unserialize($meta['cursos'] ?? '', ['allowed_classes' => false]);
        if (is_array($cursos)) {
            $ordem = 0;
            foreach ($cursos as $item) {
                if (! is_array($item) || empty($item['nome_do_curso'])) {
                    continue;
                }
                CursoRealizado::create([
                    'user_id' => $user->id,
                    'nome' => $item['nome_do_curso'],
                    'ano' => is_numeric($item['ano_de_conclusao'] ?? null) ? (int) $item['ano_de_conclusao'] : null,
                    'local' => $item['local_de_conclusao'] ?? null,
                    'ordem' => $ordem++,
                ]);
            }
        }
    }

    private function data(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }
        $valor = trim($valor);

        // Unix timestamp (inteiro, pode ser negativo para nascimentos antes de 1970)
        // Converte timestamp Unix usando o timezone da app (APP_TIMEZONE) — datas em nível de dia.
        if (preg_match('/^-?\d+$/', $valor)) {
            $ano = (int) date('Y', (int) $valor);
            if ($ano < 1900 || $ano > (int) date('Y')) {
                return null; // descarta timestamps absurdos
            }

            return date('Y-m-d', (int) $valor);
        }

        // já em Y-m-d (defensivo)
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            return substr($valor, 0, 10);
        }

        return null;
    }

    private function join(array $roles): string
    {
        return implode(',', $roles) ?: '(nenhum)';
    }
}
