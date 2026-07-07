<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PerfilMembroFotoGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_pode_auto_popular_quando_sem_foto_e_flag_false(): void
    {
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);

        $this->assertFalse($perfil->foto_definida_pelo_membro);
        $this->assertTrue($perfil->podeAutoPopularFoto());
    }

    public function test_nao_auto_popula_quando_flag_true(): void
    {
        // O flag NÃO está no $fillable (blindagem) → mass-assignment o descartaria em
        // silêncio (app sem strict mode). Setar sempre pela via controlada.
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        $this->assertTrue($perfil->fresh()->foto_definida_pelo_membro);
        $this->assertFalse($perfil->podeAutoPopularFoto());
    }

    public function test_nao_auto_popula_quando_ja_ha_foto(): void
    {
        $perfil = PerfilMembro::create(['user_id' => User::factory()->create()->id]);
        // addMediaFromString + getContent(): padrão já usado nos demais testes de mídia do
        // projeto (ex. PostMediaTest) — addMedia(...->getRealPath()) falha no container aqui.
        $perfil->addMediaFromString(UploadedFile::fake()->image('f.jpg')->getContent())
            ->usingFileName('f.jpg')->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $this->assertTrue($perfil->fresh()->foto_definida_pelo_membro === false);
        $this->assertFalse($perfil->fresh()->podeAutoPopularFoto());
    }
}
