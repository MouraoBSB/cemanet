<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $cats = [
            ['nome' => 'Reflexões e Espiritualidade', 'slug' => 'reflexoes-e-espiritualidade', 'cor' => '#4E4483', 'ordem' => 1],
            ['nome' => 'Estudando a Mediunidade',     'slug' => 'estudando-a-mediunidade',     'cor' => '#6E9FCB', 'ordem' => 2],
            ['nome' => 'Prática do Amor ao Próximo',  'slug' => 'pratica-do-amor-ao-proximo',  'cor' => '#89AB98', 'ordem' => 3],
            ['nome' => 'Datas Comemorativas',         'slug' => 'datas-comemorativas',         'cor' => '#F2A81E', 'ordem' => 4],
            ['nome' => 'CEMA em Ação',                'slug' => 'cema-em-acao',                'cor' => '#E79048', 'ordem' => 5],
            ['nome' => 'Sem categoria',               'slug' => 'sem-categoria',               'cor' => '#7A8A8A', 'ordem' => 99],
        ];

        foreach ($cats as $c) {
            Categoria::updateOrCreate(['slug' => $c['slug']], $c);
        }
    }
}
