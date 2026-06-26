<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $nome = fake()->unique()->word();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
