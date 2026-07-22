<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Models;

use App\Enums\FormatoMensagem;
use App\Models\AutorEspiritual;
use App\Models\Contracts\TemDepartamento;
use App\Models\Departamento;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class MensagemTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_grava_na_tabela_mensagens(): void
    {
        $this->assertSame('mensagens', (new Mensagem)->getTable());
    }

    public function test_colunas_esperadas_e_podadas(): void
    {
        foreach (['titulo', 'slug', 'corpo', 'contexto', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('mensagens', $coluna), "coluna esperada ausente: {$coluna}");
        }
        foreach (['origem_da_mensagem', 'grupo_mediunico', 'casa_espirita'] as $coluna) {
            $this->assertFalse(Schema::hasColumn('mensagens', $coluna), "coluna podada presente: {$coluna}");
        }
    }

    public function test_fillable_exato(): void
    {
        $this->assertSame(
            ['titulo', 'slug', 'corpo', 'contexto', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'],
            (new Mensagem)->getFillable(),
        );
    }

    public function test_formato_reidrata_como_enum(): void
    {
        $m = Mensagem::factory()->create(['formato' => 'psicofonia']);
        $this->assertInstanceOf(FormatoMensagem::class, $m->fresh()->formato);
        $this->assertSame(FormatoMensagem::Psicofonia, $m->fresh()->formato);
    }

    public function test_liberar_download_e_boolean(): void
    {
        $m = Mensagem::factory()->create(['liberar_download' => 1]);
        $this->assertIsBool($m->fresh()->liberar_download);
        $this->assertTrue($m->fresh()->liberar_download);
    }

    public function test_corpo_e_sanitizado(): void
    {
        $m = Mensagem::factory()->create(['corpo' => '<p>Paz</p><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script', (string) $m->corpo);
        $this->assertStringContainsString('Paz', (string) $m->corpo);
    }

    public function test_contexto_e_texto_puro_persistido(): void
    {
        $m = Mensagem::factory()->create(['contexto' => 'Recebida na reunião pública de quarta.']);
        $this->assertSame('Recebida na reunião pública de quarta.', $m->fresh()->contexto);
    }

    public function test_data_recebimento_round_trip_carbon(): void
    {
        $m = Mensagem::factory()->create(['data_recebimento' => '2024-08-05']);
        $this->assertInstanceOf(Carbon::class, $m->fresh()->data_recebimento);
        $this->assertSame('2024-08-05', $m->fresh()->data_recebimento->format('Y-m-d'));
    }

    public function test_link_arquivo_normalizado_via_link_drive(): void
    {
        // R-A: o mutator normaliza tanto o import quanto um link colado no /admin.
        $m = Mensagem::factory()->create(['link_arquivo' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view']);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMnOpQrStUv', $m->fresh()->link_arquivo);
    }

    public function test_scope_publica_filtra_status_e_nivel(): void
    {
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'publico']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores']);
        Mensagem::factory()->create(['status' => 'pendente', 'nivel' => 'publico']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null]);

        $publicas = Mensagem::publica()->get();
        $this->assertCount(1, $publicas);
        $this->assertSame('publico', $publicas->first()->nivel);
    }

    public function test_implementa_contratos_de_midia_e_departamento(): void
    {
        $m = new Mensagem;
        $this->assertInstanceOf(HasMedia::class, $m);
        $this->assertInstanceOf(TemDepartamento::class, $m);
    }

    public function test_departamentos_pelo_pivo(): void
    {
        $m = Mensagem::factory()->create();
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);

        $m->departamentos()->sync([$depto->id]);

        $this->assertTrue($m->fresh()->departamentos->contains('id', $depto->id));
        $this->assertDatabaseHas('departamento_mensagem', ['mensagem_id' => $m->id, 'departamento_id' => $depto->id]);
    }

    public function test_autores_n_n_pelo_pivo(): void
    {
        $m = Mensagem::factory()->create();
        $autor = AutorEspiritual::factory()->create(['slug' => 'bezerra-de-menezes']);

        $m->autores()->sync([$autor->id]);

        $this->assertTrue($m->fresh()->autores->contains('id', $autor->id));
        $this->assertDatabaseHas('mensagem_autor_espiritual', ['mensagem_id' => $m->id, 'autor_espiritual_id' => $autor->id]);
    }

    public function test_pictografia_multi_registra_conversoes(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->create();

        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('a.png')->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('b.png')->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);

        // multi-arquivo: a coleção guarda as 2 (não é singleFile).
        $this->assertSame(2, $m->fresh()->getMedia(Mensagem::COLECAO_PICTOGRAFIA)->count());
    }

    public function test_relacionadas_e_simetrica(): void
    {
        $a = Mensagem::factory()->create();
        $b = Mensagem::factory()->create();

        $a->sincronizarRelacionadas([$b->id]);

        $this->assertTrue($a->fresh()->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $a->id), 'relação não refletiu no outro sentido');
    }

    public function test_remover_relacionada_reflete_nos_dois_lados(): void
    {
        $a = Mensagem::factory()->create();
        $b = Mensagem::factory()->create();
        $a->sincronizarRelacionadas([$b->id]);

        $a->sincronizarRelacionadas([]);   // remove

        $this->assertCount(0, $a->fresh()->relacionadas);
        $this->assertCount(0, $b->fresh()->relacionadas, 'o outro lado ainda enxerga a relação removida');
    }

    public function test_relacionadas_nao_permite_auto_relacao(): void
    {
        $a = Mensagem::factory()->create();

        $a->sincronizarRelacionadas([$a->id]);

        $this->assertCount(0, $a->fresh()->relacionadas);
        $this->assertDatabaseMissing('mensagem_relacionada', ['mensagem_id' => $a->id, 'relacionada_id' => $a->id]);
    }

    public function test_resumo_e_redigido_na_trilha_de_auditoria(): void
    {
        $m = Mensagem::factory()->create(['resumo' => 'SENTINELA-RESUMO-ANTIGO']);

        $m->update(['resumo' => 'SENTINELA-RESUMO-NOVO']);

        $props = $m->activities()->latest('id')->first()->properties;

        $this->assertSame('[texto não registrado]', $props['attributes']['resumo']);
        $this->assertSame('[texto não registrado]', $props['old']['resumo']);

        // Sentinelas em ASCII de propósito: json_encode escapa acento (conteúdo → conteúdo),
        // e uma busca por texto acentuado passaria SEMPRE, provando nada.
        $json = $m->activities()->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-RESUMO-ANTIGO', $json);
        $this->assertStringNotContainsString('SENTINELA-RESUMO-NOVO', $json);
    }
}
