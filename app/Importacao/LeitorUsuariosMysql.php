<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Importacao;

use Illuminate\Support\Collection;
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
                'fotos_urls' => $this->candidatasFoto($meta, $conn),
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

    /**
     * URLs candidatas de foto do usuário, em ordem de prioridade (deduplicadas, sem vazias).
     * Parse defensivo: dado corrompido ou id sem guid → candidata ausente, sem interromper.
     */
    private function candidatasFoto(Collection $meta, $conn): array
    {
        $urls = [];

        // 1) _foto_de_perfil: serializado {id, url}
        $fp = @unserialize((string) ($meta['_foto_de_perfil'] ?? ''), ['allowed_classes' => false]);
        if (is_array($fp)) {
            if (! empty($fp['url']) && is_string($fp['url'])) {
                $urls[] = $fp['url'];
            }
            if (! empty($fp['id'])) {
                $urls[] = $this->guidDoAttachment($conn, (int) $fp['id']);
            }
        }

        // 2) wp_user_avatar: attachment id
        if (! empty($meta['wp_user_avatar'])) {
            $urls[] = $this->guidDoAttachment($conn, (int) $meta['wp_user_avatar']);
        }

        // remove vazias/nulas e deduplica preservando a ordem
        return array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && $u !== '')));
    }

    private function guidDoAttachment($conn, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        $row = $conn->table('posts')->where('ID', $id)->where('post_type', 'attachment')->first();

        return $row->guid ?? null;
    }
}
