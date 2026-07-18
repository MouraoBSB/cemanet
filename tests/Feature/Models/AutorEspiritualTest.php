<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Models;

use App\Models\AutorEspiritual;
use App\Models\Contracts\TemDepartamento;
use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class AutorEspiritualTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_grava_na_tabela_autores_espirituais(): void
    {
        $this->assertSame('autores_espirituais', (new AutorEspiritual)->getTable());
    }

    public function test_tabela_nao_tem_colunas_de_contato_nem_foto_string(): void
    {
        foreach (['email', 'telefone', 'mostrar_email', 'mostrar_telefone', 'foto', 'curtidas', 'wp_id'] as $coluna) {
            $this->assertFalse(Schema::hasColumn('autores_espirituais', $coluna), "coluna indevida: {$coluna}");
        }
        foreach (['nome', 'slug', 'chamada', 'bio', 'ativo'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('autores_espirituais', $coluna), "coluna esperada ausente: {$coluna}");
        }
    }

    public function test_fillable_e_exatamente_os_cinco_campos(): void
    {
        $this->assertSame(['nome', 'slug', 'chamada', 'bio', 'ativo'], (new AutorEspiritual)->getFillable());
    }

    public function test_ativo_e_boolean_e_scope_filtra(): void
    {
        AutorEspiritual::factory()->create(['ativo' => true]);
        AutorEspiritual::factory()->create(['ativo' => false]);

        $ativos = AutorEspiritual::ativo()->get();
        $this->assertCount(1, $ativos);
        $this->assertIsBool($ativos->first()->ativo);
        $this->assertTrue($ativos->first()->ativo);
    }

    public function test_bio_e_sanitizada(): void
    {
        $autor = AutorEspiritual::factory()->create(['bio' => '<p>Legítimo</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script', (string) $autor->bio);
        $this->assertStringContainsString('Legítimo', (string) $autor->bio);
    }

    public function test_implementa_contratos_de_midia_e_departamento(): void
    {
        $autor = new AutorEspiritual;
        $this->assertInstanceOf(HasMedia::class, $autor);
        $this->assertInstanceOf(TemDepartamento::class, $autor);
    }

    public function test_departamentos_anexa_e_le_pelo_pivo(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);

        $autor->departamentos()->sync([$depto->id]);

        $this->assertTrue($autor->fresh()->departamentos->contains('id', $depto->id));
        $this->assertDatabaseHas('departamento_autor_espiritual', [
            'autor_espiritual_id' => $autor->id, 'departamento_id' => $depto->id,
        ]);
    }

    public function test_foto_registra_conversoes_web_e_thumb(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create();

        $autor->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('foto.png')
            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);

        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO));
    }
}
