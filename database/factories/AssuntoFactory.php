<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssuntoFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);

        return [
            'nome' => ucfirst($nome),
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'parent_id' => null,
        ];
    }
}
