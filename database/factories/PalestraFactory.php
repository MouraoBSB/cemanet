<?php

namespace Database\Factories;

use App\Models\Palestra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PalestraFactory extends Factory
{
    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(3);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'subtitulo' => fake()->sentence(),
            'descricao' => '<p>'.fake()->paragraph().'</p>',
            'data_da_palestra' => fake()->dateTimeBetween('-2 years', 'now'),
            'online' => fake()->boolean(),
            'link_youtube' => 'https://youtube.com/live/'.fake()->lexify('???????'),
            'cor_fundo' => fake()->hexColor(),
            'publico_total' => fake()->numberBetween(0, 300),
            'status' => Palestra::STATUS_PUBLICADO,
        ];
    }
}
