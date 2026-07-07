<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Jobs;

use App\Importacao\BaixadorImagem;
use App\Jobs\CapturarAvatarGoogleJob;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CapturarAvatarGoogleJobTest extends TestCase
{
    use RefreshDatabase;

    private function comBaixador(?string $bytes): void
    {
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->andReturn($bytes);
        $this->app->instance(BaixadorImagem::class, $m);
    }

    public function test_anexa_avatar_quando_perfil_sem_foto(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg', 800, 800)->get());
        $user = User::factory()->create();
        PerfilMembro::create(['user_id' => $user->id]);

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_respeita_flag_do_membro(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg')->get());
        $user = User::factory()->create();
        // flag fora do $fillable → setar pela via controlada (mass-assignment o descartaria)
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertFalse($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_cria_perfil_se_ausente(): void
    {
        $this->comBaixador(UploadedFile::fake()->image('g.jpg', 800, 800)->get());
        $user = User::factory()->create(); // sem perfil

        (new CapturarAvatarGoogleJob($user->id, 'https://lh3/g.jpg'))->handle(app(BaixadorImagem::class));

        $this->assertNotNull($user->perfil()->first());
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
