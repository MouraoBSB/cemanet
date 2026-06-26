<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(4);

        return [
            'titulo'           => $titulo,
            'slug'             => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'conteudo'         => '<p>'.fake()->paragraph().'</p>',
            'data_publicacao'  => now(),
            'status'           => 'publicado',
            'tempo_leitura_min' => 1,
        ];
    }

    /** Post salvo como rascunho (sem data pública futura). */
    public function rascunho(): static
    {
        return $this->state(['status' => 'rascunho']);
    }

    /** Post agendado: status publicado mas data no futuro. */
    public function agendado(): static
    {
        return $this->state([
            'status'          => 'publicado',
            'data_publicacao' => now()->addDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
