<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Database\Factories;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoriaFactory extends Factory
{
    protected $model = Categoria::class;

    public function definition(): array
    {
        $nome = fake()->unique()->words(3, true);

        return [
            'nome' => ucfirst($nome),
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'cor' => fake()->hexColor(),
            'ordem' => fake()->numberBetween(1, 99),
        ];
    }
}
