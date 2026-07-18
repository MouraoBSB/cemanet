<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AutorEspiritualFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->name();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'bio' => '<p>'.fake()->paragraph().'</p>',
            'ativo' => true,
        ];
    }

    public function ativo(): static
    {
        return $this->state(fn () => ['ativo' => true]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
