<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaDia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AgendaDiaFactory extends Factory
{
    protected $model = AgendaDia::class;

    public function definition(): array
    {
        return [
            // offset único de dias → data única (respeita agenda_dias.data unique)
            'data' => Carbon::create(2026, 5, 1)->addDays(fake()->unique()->numberBetween(0, 200))->format('Y-m-d'),
            'reflexao' => '<p>'.fake()->paragraph().'</p>',
            'meta_mes_texto' => '<p>'.fake()->sentence().'</p>',
            'meta_dia_titulo' => fake()->sentence(3),
            'meta_dia_texto' => '<p>'.fake()->sentence().'</p>',
            'prece' => '<p>'.fake()->sentence().'</p>',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ];
    }

    /** Dia salvo como rascunho (não entra no scope publicado). */
    public function rascunho(): static
    {
        return $this->state(['status' => AgendaDia::STATUS_RASCUNHO]);
    }
}
