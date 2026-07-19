<?php

namespace Database\Factories;

use App\Models\Mensagem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MensagemFactory extends Factory
{
    protected $model = Mensagem::class;

    public function definition(): array
    {
        $titulo = fake()->sentence(4);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'corpo' => '<p>'.fake()->paragraph().'</p>',
            'contexto' => null,
            'formato' => fake()->randomElement(['psicografia', 'psicofonia', 'pictografia']),
            'data_recebimento' => fake()->date('Y-m-d'),
            'casa' => 'CEMA',
            'link_arquivo' => null,
            'liberar_download' => false,
            'nivel' => null,
            'status' => Mensagem::STATUS_PUBLICADO,
            'wp_id' => null,
        ];
    }

    public function publicada(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PUBLICADO]);
    }

    public function pendente(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PENDENTE]);
    }

    /** Pública = publicada E nível 'publico' (aparece no scope publica()). */
    public function publica(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => Mensagem::NIVEL_PUBLICO]);
    }
}
