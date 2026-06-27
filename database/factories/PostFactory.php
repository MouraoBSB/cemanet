<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(4);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'conteudo' => '<p>'.fake()->paragraph().'</p>',
            'data_publicacao' => now(),
            'status' => 'publicado',
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
            'status' => 'publicado',
            'data_publicacao' => now()->addDays(7),
        ]);
    }

    /**
     * Anexa uma imagem fictícia (100×100 px) à coleção 'destacada' após criar o post.
     * O teste que usar este state deve chamar Storage::fake('public') antes de criar.
     */
    public function comImagemDestacada(): static
    {
        return $this->afterCreating(function (Post $post) {
            $bytes = UploadedFile::fake()->image('destacada.jpg', 100, 100)->getContent();

            $post->addMediaFromString($bytes)
                ->usingFileName('destacada.jpg')
                ->toMediaCollection(Post::COLECAO_DESTACADA);
        });
    }

    /**
     * Anexa $n imagens fictícias (100×100 px) à coleção 'galeria' após criar o post.
     * O teste que usar este state deve chamar Storage::fake('public') antes de criar.
     *
     * @param  int  $n  Número de imagens (padrão 2)
     */
    public function comGaleria(int $n = 2): static
    {
        return $this->afterCreating(function (Post $post) use ($n) {
            for ($i = 1; $i <= $n; $i++) {
                $bytes = UploadedFile::fake()->image("galeria-{$i}.jpg", 100, 100)->getContent();

                $post->addMediaFromString($bytes)
                    ->usingFileName("galeria-{$i}.jpg")
                    ->toMediaCollection(Post::COLECAO_GALERIA);
            }
        });
    }
}
