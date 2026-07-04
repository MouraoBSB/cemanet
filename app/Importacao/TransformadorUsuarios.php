<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use Illuminate\Support\Str;

class TransformadorUsuarios
{
    private const PREPOSICOES = ['de', 'da', 'do', 'das', 'dos', 'e', 'di', 'du'];

    public function nomeTitulo(string $nome): string
    {
        $nome = trim(preg_replace('/\s+/u', ' ', $nome));
        if ($nome === '') {
            return '';
        }
        $palavras = explode(' ', mb_strtolower($nome, 'UTF-8'));
        $resultado = [];
        foreach ($palavras as $i => $palavra) {
            if ($palavra === '') {
                continue;
            }
            $resultado[] = ($i > 0 && in_array($palavra, self::PREPOSICOES, true))
                ? $palavra
                : mb_convert_case($palavra, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $resultado);
    }

    public function flagTresEstados(?string $valor): ?bool
    {
        if ($valor === null) {
            return null;
        }
        $v = mb_strtolower(trim($valor), 'UTF-8');
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['true', 'on', '1', 'sim', 'yes'], true)) {
            return true;
        }
        if (in_array($v, ['false', '0', 'nao', 'não', 'no'], true)) {
            return false;
        }

        return null;
    }

    public function papelDe(array $rolesWp): ?string
    {
        $candidatos = [];
        foreach ($rolesWp as $role) {
            if (isset(GlossarioUsuarios::PAPEIS[$role])) {
                $candidatos[$role] = GlossarioUsuarios::PAPEIS[$role];
            }
        }
        if ($candidatos === []) {
            return null;
        }
        arsort($candidatos);

        return array_key_first($candidatos);
    }

    /** @return array<int, array{slug:string, funcao:string}> */
    public function resolverSetores(array $slugsLegado): array
    {
        $resultado = [];
        foreach ($slugsLegado as $slugLegado) {
            $map = GlossarioUsuarios::SETORES[$slugLegado] ?? null;
            if ($map === null) {
                continue; // não resolvido — o Importador loga
            }
            [$nome, , $funcao] = $map;
            $slug = Str::slug($nome);
            // se o mesmo setor-base já veio, coordenador prevalece sobre membro
            if (isset($resultado[$slug]) && $funcao === 'membro') {
                continue;
            }
            $resultado[$slug] = ['slug' => $slug, 'funcao' => $funcao];
        }

        return array_values($resultado);
    }

    /** @return array<int, string> slugs de cargo resolvidos */
    public function resolverCargos(array $slugsLegado): array
    {
        $resultado = [];
        foreach ($slugsLegado as $slugLegado) {
            $map = GlossarioUsuarios::CARGOS[$slugLegado] ?? null;
            if ($map === null) {
                continue;
            }
            $resultado[] = Str::slug($map[0]);
        }

        return array_values(array_unique($resultado));
    }
}
