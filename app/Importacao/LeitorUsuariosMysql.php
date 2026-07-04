<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use Illuminate\Support\Facades\DB;

class LeitorUsuariosMysql implements LeitorUsuarios
{
    public function usuarios(): iterable
    {
        $conn = DB::connection('legado');

        $users = $conn->table('users')->select('ID', 'user_login', 'display_name', 'user_email', 'user_pass', 'user_registered')->get();

        foreach ($users as $u) {
            $meta = $conn->table('usermeta')->where('user_id', $u->ID)
                ->pluck('meta_value', 'meta_key');

            yield [
                'origem_id' => (int) $u->ID,
                'login' => $u->user_login,
                'nome' => $u->display_name,
                'email' => $u->user_email,
                'senha' => $u->user_pass,
                'registrado' => $u->user_registered,
                'roles' => $this->tokensRole($meta['wp_capabilities'] ?? ''),
                'setores' => $this->itens($meta['locais_de_trabalho_trabalhador'] ?? ''),
                'cargos' => $this->itens($meta['locais_de_trabalho_diretor'] ?? ''),
                'socio' => $meta['_socio'] ?? null,
                'meta' => [
                    'whatsapp' => $meta['_whatsapp'] ?? null,
                    'whatsapp_publico' => $meta['_liberar_whatsapp_publico'] ?? null,
                    'nascimento' => $meta['data_de_nascimento'] ?? null,
                    'endereco' => $meta['_endereco'] ?? null,
                    'cursos' => $meta['cursos_realizados'] ?? null,
                ],
            ];
        }
    }

    /** Extrai os slugs de role de um wp_capabilities serializado. */
    private function tokensRole(string $serializado): array
    {
        if (preg_match_all('/"([a-z_]+)";b:1/', $serializado, $m)) {
            return $m[1];
        }

        return [];
    }

    /** Desserializa um array PHP de slugs (locais_de_trabalho_*). */
    private function itens(string $serializado): array
    {
        if ($serializado === '') {
            return [];
        }
        $a = @unserialize($serializado, ['allowed_classes' => false]);

        return is_array($a) ? array_values(array_filter($a, fn ($x) => is_string($x) && $x !== '')) : [];
    }
}
