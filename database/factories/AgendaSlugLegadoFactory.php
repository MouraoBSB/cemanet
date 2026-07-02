<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaSlugLegado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AgendaSlugLegadoFactory extends Factory
{
    protected $model = AgendaSlugLegado::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'data' => Carbon::create(2026, 5, 1)->addDays(fake()->numberBetween(0, 200))->format('Y-m-d'),
        ];
    }
}
