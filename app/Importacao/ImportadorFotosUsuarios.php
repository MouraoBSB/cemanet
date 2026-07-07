<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Importacao;

use App\Models\PerfilMembro;
use App\Models\User;

class ImportadorFotosUsuarios
{
    public function __construct(
        private LeitorUsuarios $leitor,
        private BaixadorImagem $baixador,
    ) {}

    /** @return array{anexadas:int, puladas:int, sem_candidata:int, falhas:int, avisos:string[]} */
    public function importar(callable $log): array
    {
        $anexadas = $puladas = $semCandidata = $falhas = 0;
        $avisos = [];

        foreach ($this->leitor->usuarios() as $bruto) {
            $candidatas = $bruto['fotos_urls'] ?? [];
            if (empty($candidatas)) {
                $semCandidata++;

                continue;
            }

            $user = User::where('origem_legado_id', $bruto['origem_id'])->first();
            if (! $user) {
                continue; // não importado (admin/hash ignorado)
            }

            $perfil = PerfilMembro::firstOrCreate(['user_id' => $user->id]);
            if (! $perfil->podeAutoPopularFoto()) {
                $puladas++;

                continue;
            }

            $anexou = false;
            foreach ($candidatas as $url) {
                $bytes = $this->baixador->baixarCapado($url, 2000);
                if ($bytes === null) {
                    continue;
                }
                $perfil->addMediaFromString($bytes)
                    ->usingFileName(basename(parse_url($url, PHP_URL_PATH) ?? 'foto.jpg') ?: 'foto.jpg')
                    ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
                $anexou = true;
                $anexadas++;
                $log("Foto anexada: {$user->email}");
                break;
            }

            if (! $anexou) {
                $falhas++;
                $avisos[] = "Nenhuma URL baixou para {$user->email}";
            }
        }

        return [
            'anexadas' => $anexadas,
            'puladas' => $puladas,
            'sem_candidata' => $semCandidata,
            'falhas' => $falhas,
            'avisos' => $avisos,
        ];
    }
}
