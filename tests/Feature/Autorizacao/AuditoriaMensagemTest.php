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

    /**
     * Achado Important da revisão da Task 1: nenhum teste da suíte exercitava um valor NULL em
     * corpo/contexto — trocar array_key_exists por isset no laço de redação não reprovaria nenhum
     * teste, porque isset(string não vazia) é sempre true. Aqui o valor NOVO é null: isset(null) é
     * false e pularia a redação (o campo ficaria null, em vez de virar '[texto não registrado]'),
     * enquanto array_key_exists redige do mesmo jeito, porque é a CHAVE — não a verdade do valor —
     * que decide.
     */
    public function test_editar_contexto_para_null_redige_o_campo_mesmo_com_o_valor_novo_nulo(): void
    {
        $m = Mensagem::factory()->create(['contexto' => 'SENTINELA-ANTES-DE-NULL']);
        Activity::query()->delete();

        $m->update(['contexto' => null]);

        $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
        $this->assertArrayHasKey('contexto', $props['attributes']); // a CHAVE sobrevive mesmo com valor novo null
        $this->assertSame('[texto não registrado]', $props['attributes']['contexto']); // isset(null) pularia a redação

        $json = Activity::where('log_name', 'mensagem')->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-ANTES-DE-NULL', $json);
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
