<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Autorizacao;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaMensagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_editar_titulo_gera_uma_entrada_com_porta_no_properties(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete(); // ignora o 'created' do factory

        $m->update(['titulo' => 'Novo']);

        $atividade = Activity::where('log_name', 'mensagem')->latest('id')->first();
        $this->assertSame(1, Activity::where('log_name', 'mensagem')->count());
        $this->assertNotNull($atividade);
        $this->assertSame('updated', $atividade->event);
        $this->assertArrayHasKey('porta', $atividade->properties->toArray());
    }

    public function test_save_sem_mudanca_nao_gera_entrada(): void
    {
        $m = Mensagem::factory()->create();
        Activity::query()->delete();

        $m->save(); // nada dirty

        $this->assertSame(0, Activity::where('log_name', 'mensagem')->count());
    }

    /** I27/P2: a CHAVE 'corpo' sobrevive na trilha, mas o TEXTO (nenhuma das duas sentinelas) nunca aparece. */
    public function test_editar_corpo_registra_a_chave_mas_nunca_o_texto(): void
    {
        $m = Mensagem::factory()->create(['corpo' => '<p>SENTINELA-ANTIGA-XYZ</p>']);
        Activity::query()->delete();

        $m->update(['corpo' => '<p>SENTINELA-NOVA-XYZ</p>']);

        $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
        $this->assertArrayHasKey('corpo', $props['attributes']); // a CHAVE sobrevive
        $this->assertArrayHasKey('corpo', $props['old']);

        $json = Activity::where('log_name', 'mensagem')->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-ANTIGA-XYZ', $json); // o TEXTO não
        $this->assertStringNotContainsString('SENTINELA-NOVA-XYZ', $json);
    }

    /** I27/P2: idem para 'contexto'. */
    public function test_editar_contexto_registra_a_chave_mas_nunca_o_texto(): void
    {
        $m = Mensagem::factory()->create(['contexto' => 'SENTINELA-ANTIGA-CTX']);
        Activity::query()->delete();

        $m->update(['contexto' => 'SENTINELA-NOVA-CTX']);

        $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
        $this->assertArrayHasKey('contexto', $props['attributes']);
        $this->assertArrayHasKey('contexto', $props['old']);

        $json = Activity::where('log_name', 'mensagem')->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-ANTIGA-CTX', $json);
        $this->assertStringNotContainsString('SENTINELA-NOVA-CTX', $json);
    }

    /** A redação é cirúrgica: só corpo/contexto são trocados — titulo continua com o valor real. */
    public function test_editar_titulo_mantem_o_valor_no_properties(): void
    {
        $m = Mensagem::factory()->create(['titulo' => 'Título antigo']);
        Activity::query()->delete();

        $m->update(['titulo' => 'Título novo']);

        $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
        $this->assertSame('Título novo', $props['attributes']['titulo']);
        $this->assertSame('Título antigo', $props['old']['titulo']);
    }
}
