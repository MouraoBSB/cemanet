<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace Tests\Feature\Biblioteca;

use App\Models\Biblioteca;
use App\Support\Biblioteca\RegistraMidiaBiblioteca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DedupMidiaTest extends TestCase
{
    use RefreshDatabase;

    private string $arquivo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        // Arquivo ≤ teto (não capa → hash de entrada == hash canônico).
        $this->arquivo = sys_get_temp_dir() . '/dedup-' . uniqid() . '.png';
        file_put_contents($this->arquivo, UploadedFile::fake()->image('x.png', 800, 600)->getContent());
    }

    protected function tearDown(): void
    {
        @unlink($this->arquivo);
        parent::tearDown();
    }

    public function test_mesmo_arquivo_registra_uma_unica_midia(): void
    {
        $svc = new RegistraMidiaBiblioteca();

        $a = $svc->aPartirDoCaminho($this->arquivo, 'x.png');
        $b = $svc->aPartirDoCaminho($this->arquivo, 'x.png');

        $this->assertSame($a->id, $b->id);
        $this->assertCount(1, Biblioteca::instance()->getMedia(Biblioteca::COLECAO));
        $this->assertNotEmpty($a->getCustomProperty('sha256'));
    }

    public function test_guarda_e_preserva_metadados(): void
    {
        $svc = new RegistraMidiaBiblioteca();

        $a = $svc->aPartirDoCaminho($this->arquivo, 'x.png', ['alt' => 'Descrição A', 'legenda' => 'Leg']);
        $this->assertSame('Descrição A', $a->getCustomProperty('alt'));
        $this->assertSame('Leg', $a->getCustomProperty('legenda'));

        // Duplicata com alt diferente → mantém o original (não sobrescreve a curadoria).
        $b = $svc->aPartirDoCaminho($this->arquivo, 'x.png', ['alt' => 'Outro alt']);
        $this->assertSame($a->id, $b->id);
        $this->assertSame('Descrição A', $b->fresh()->getCustomProperty('alt'));
    }
}
