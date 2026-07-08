<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use Illuminate\Support\Str;

/**
 * Infere a categoria pública de um evento a partir do título (o legado não tem
 * campo de categoria). Sem correspondência → null (revisar manualmente no admin).
 */
class ClassificadorCategoria
{
    public static function paraSlug(string $titulo): ?string
    {
        $t = Str::ascii(Str::lower($titulo));

        return match (true) {
            str_contains($t, 'brecho') => 'brecho',
            str_contains($t, 'feirao') || str_contains($t, 'livros') => 'feirao',
            str_contains($t, 'encontro') || str_contains($t, 'familia') => 'familia',
            str_contains($t, 'campanha') => 'campanha',
            str_contains($t, 'curso') || str_contains($t, 'estudo') || str_contains($t, 'cemart') => 'estudo',
            default => null,
        };
    }
}
