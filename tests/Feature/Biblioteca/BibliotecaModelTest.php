<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Biblioteca;

use App\Models\Biblioteca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class BibliotecaModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_instance_e_singleton(): void
    {
        $a = Biblioteca::instance();
        $b = Biblioteca::instance();

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, Biblioteca::count());
    }

    public function test_biblioteca_implementa_has_media(): void
    {
        $this->assertInstanceOf(HasMedia::class, Biblioteca::instance());
    }

    public function test_colecao_constante(): void
    {
        $this->assertSame('biblioteca', Biblioteca::COLECAO);
    }
}
