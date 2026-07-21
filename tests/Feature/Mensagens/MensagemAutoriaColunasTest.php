<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MensagemAutoriaColunasTest extends TestCase
{
    use RefreshDatabase;

    /** I21: a FK de medium_id/publicado_por_id aponta para 'users' — nunca 'media' (Str::plural('medium')). */
    public function test_fk_medium_id_e_publicado_por_id_apontam_para_users(): void
    {
        $chaves = collect(DB::select("PRAGMA foreign_key_list('mensagens')"))
            ->keyBy('from');

        $this->assertSame('users', $chaves->get('medium_id')?->table);
        $this->assertSame('users', $chaves->get('publicado_por_id')?->table);
    }

    public function test_relacao_medium_resolve_o_user(): void
    {
        $medium = User::factory()->create();
        $mensagem = Mensagem::factory()->create(['medium_id' => $medium->id]);

        $this->assertTrue($mensagem->medium()->exists());
        $this->assertSame($medium->id, $mensagem->medium->id);
    }

    public function test_relacao_publicado_por_resolve_o_user(): void
    {
        $diretor = User::factory()->create();
        $mensagem = Mensagem::factory()->create(['publicado_por_id' => $diretor->id]);

        $this->assertTrue($mensagem->publicadoPor()->exists());
        $this->assertSame($diretor->id, $mensagem->publicadoPor->id);
    }

    public function test_apagar_o_medium_poe_medium_id_null_e_preserva_a_mensagem(): void
    {
        $medium = User::factory()->create();
        $mensagem = Mensagem::factory()->create(['medium_id' => $medium->id]);

        $medium->delete();

        $this->assertTrue(Mensagem::query()->whereKey($mensagem->id)->exists());
        $this->assertNull($mensagem->fresh()->medium_id);
    }

    public function test_apagar_o_publicador_poe_publicado_por_id_null_e_preserva_a_mensagem(): void
    {
        $diretor = User::factory()->create();
        $mensagem = Mensagem::factory()->create(['publicado_por_id' => $diretor->id]);

        $diretor->delete();

        $this->assertTrue(Mensagem::query()->whereKey($mensagem->id)->exists());
        $this->assertNull($mensagem->fresh()->publicado_por_id);
    }

    /** D8: autoria nunca sai em toArray()/wire:snapshot — só por relação. */
    public function test_hidden_esconde_os_tres_campos_de_autoria_do_toarray(): void
    {
        $medium = User::factory()->create();
        $mensagem = Mensagem::factory()->create([
            'medium_id' => $medium->id,
            'publicado_por_id' => $medium->id,
            'publicado_em' => now(),
        ]);

        $chaves = array_keys($mensagem->toArray());

        $this->assertNotContains('medium_id', $chaves);
        $this->assertNotContains('publicado_por_id', $chaves);
        $this->assertNotContains('publicado_em', $chaves);
    }
}
