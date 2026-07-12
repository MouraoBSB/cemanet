<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Console\Commands;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Backfill idempotente do departamento que MANTÉM cada conteúdo (não o tema):
 * Palestra→DED, Palestrante→DED, Post→DECOM, AgendaDia→DECOM. Só o vínculo; a permissão é a Fase C.
 * syncWithoutDetaching preserva vínculos manuais e a unique do pivot impede duplicação.
 */
class DepartamentalizarConteudos extends Command
{
    protected $signature = 'cema:departamentalizar-conteudos';

    protected $description = 'Vincula cada conteúdo (Palestra/Palestrante→DED, Post/AgendaDia→DECOM) ao departamento que o mantém, de forma idempotente.';

    public function handle(): int
    {
        $ded = Departamento::where('sigla', 'DED')->first();
        $decom = Departamento::where('sigla', 'DECOM')->first();

        if ($ded === null || $decom === null) {
            $this->error('Departamentos DED/DECOM não encontrados. Rode o EstruturaCemaSeeder antes.');

            return self::FAILURE;
        }

        $vinculos = [
            [Palestra::class, $ded],
            [Palestrante::class, $ded],
            [Post::class, $decom],
            [AgendaDia::class, $decom],
        ];

        foreach ($vinculos as [$model, $departamento]) {
            $total = 0;
            $model::query()->chunkById(200, function ($registros) use ($departamento, &$total) {
                foreach ($registros as $registro) {
                    $registro->departamentos()->syncWithoutDetaching([$departamento->id]);
                    $total++;
                }
            });
            $this->info(sprintf('%s: %d registro(s) vinculado(s) a %s.', class_basename($model), $total, $departamento->sigla));
        }

        return self::SUCCESS;
    }
}
