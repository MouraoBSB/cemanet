<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfilFotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_coluna_foto_perfil_foi_removida(): void
    {
        $this->assertFalse(Schema::hasColumn('perfis_membro', 'foto_perfil'));
    }

    public function test_foto_armazena_via_media_library_com_conversoes(): void
    {
        Storage::fake('public');
        $perfil = User::factory()->create()->perfil()->create([]);

        $perfil->addMedia(UploadedFile::fake()->image('foto.jpg', 800, 800))
            ->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $this->assertNotNull($perfil->fresh()->foto_url);
        $this->assertNotNull($perfil->fresh()->foto_thumb_url);
    }
}
