<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MensagemDestinatarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivo_anexa_e_le_nos_dois_sentidos(): void
    {
        $m = Mensagem::factory()->create();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $m->destinatarios()->sync([$u1->id, $u2->id]);

        $this->assertSame(2, $m->destinatarios()->count());
        $this->assertTrue($u1->mensagensDirecionadas()->where('mensagens.id', $m->id)->exists());
        $this->assertSame(1, $u1->mensagensDirecionadas()->count());
    }

    public function test_cascade_ao_deletar_mensagem_ou_usuario(): void
    {
        $m = Mensagem::factory()->create();
        $u = User::factory()->create();
        $m->destinatarios()->sync([$u->id]);
        $this->assertSame(1, DB::table('mensagem_destinatario')->count());

        $m->delete();
        $this->assertSame(0, DB::table('mensagem_destinatario')->count());

        $m2 = Mensagem::factory()->create();
        $m2->destinatarios()->sync([$u->id]);
        $u->delete();
        $this->assertSame(0, DB::table('mensagem_destinatario')->count());
    }
}
