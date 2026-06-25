<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanitizacaoHtmlTest extends TestCase
{
    use RefreshDatabase;

    public function test_descricao_remove_script_e_preserva_formatacao(): void
    {
        $p = Palestra::factory()->create([
            'descricao' => '<p>Olá <strong>mundo</strong></p><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $p->fresh()->descricao);
        $this->assertStringContainsString('<strong>mundo</strong>', $p->fresh()->descricao);
    }

    public function test_descricao_remove_handler_onerror(): void
    {
        $p = Palestra::factory()->create([
            'descricao' => '<img src=x onerror="alert(1)">',
        ]);

        $this->assertStringNotContainsString('onerror', (string) $p->fresh()->descricao);
    }

    public function test_bio_do_palestrante_e_sanitizada(): void
    {
        $pessoa = Palestrante::factory()->create([
            'bio' => '<p>Bio</p><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $pessoa->fresh()->bio);
        $this->assertStringContainsString('<p>Bio</p>', (string) $pessoa->fresh()->bio);
    }

    public function test_descricao_nula_permanece_nula(): void
    {
        $p = Palestra::factory()->create(['descricao' => null]);

        $this->assertNull($p->fresh()->descricao);
    }

    public function test_bio_nula_permanece_nula(): void
    {
        $pessoa = Palestrante::factory()->create(['bio' => null]);

        $this->assertNull($pessoa->fresh()->bio);
    }
}
