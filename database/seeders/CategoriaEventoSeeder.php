<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Database\Seeders;

use App\Models\CategoriaEvento;
use Illuminate\Database\Seeder;

class CategoriaEventoSeeder extends Seeder
{
    /** As 5 categorias públicas de evento: slug => [nome, cor de fundo, cor do texto]. */
    public const CATEGORIAS = [
        'brecho' => ['Brechó Solidário', '#89AB98', '#26242E'],
        'feirao' => ['Feirão de Livros', '#6E9FCB', '#26242E'],
        'familia' => ['Encontro & Família', '#E79048', '#26242E'],
        'campanha' => ['Campanha', '#F2A81E', '#3A3266'],
        'estudo' => ['Estudo & Curso', '#4E4483', '#FFFFFF'],
    ];

    public function run(): void
    {
        $ordem = 0;
        foreach (self::CATEGORIAS as $slug => [$nome, $cor, $corTexto]) {
            CategoriaEvento::updateOrCreate(
                ['slug' => $slug],
                ['nome' => $nome, 'cor' => $cor, 'cor_texto' => $corTexto, 'ordem' => $ordem++],
            );
        }
    }
}
