<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaMetaMes;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaMetaMesFactory extends Factory
{
    protected $model = AgendaMetaMes::class;

    public function definition(): array
    {
        return [
            'ano' => 2026,
            'mes' => fake()->unique()->numberBetween(1, 12), // respeita unique(ano,mes)
            'titulo' => fake()->sentence(4),
        ];
    }
}
