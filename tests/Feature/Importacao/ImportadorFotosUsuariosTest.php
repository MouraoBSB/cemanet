<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorFotosUsuarios;
use App\Importacao\LeitorUsuariosFake;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class ImportadorFotosUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function bytesImagem(): string
    {
        return UploadedFile::fake()->image('a.jpg', 800, 800)->get();
    }

    /** @param array<int, array<string,mixed>> $itens */
    private function importador(array $itens, ?BaixadorImagem $baixador = null): ImportadorFotosUsuarios
    {
        return new ImportadorFotosUsuarios(new LeitorUsuariosFake($itens), $baixador ?? app(BaixadorImagem::class));
    }

    private function baixadorQueRetorna(?string $bytes): BaixadorImagem
    {
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->andReturn($bytes);

        return $m;
    }

    public function test_anexa_foto_quando_perfil_sem_foto(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => $user->id]);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['anexadas']);
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_idempotente_nao_reanexa(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        // Semear foto via addMediaFromString(->get()): fake()->getRealPath() sozinho tem o tmpfile
        // GC'd antes do Spatie ler (bug de lifetime determinístico). Padrão do projeto + constraint §18.
        $perfil->addMediaFromString(UploadedFile::fake()->image('ja.jpg')->get())
            ->usingFileName('ja.jpg')->toMediaCollection(PerfilMembro::COLECAO_FOTO);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['puladas']);
        $this->assertSame(1, $perfil->fresh()->getMedia(PerfilMembro::COLECAO_FOTO)->count());
    }

    public function test_respeita_flag_do_membro(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        // flag fora do $fillable → setar pela via controlada (mass-assignment o descartaria)
        $perfil = PerfilMembro::create(['user_id' => $user->id]);
        $perfil->foto_definida_pelo_membro = true;
        $perfil->save();

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/a.jpg']]],
            $this->baixadorQueRetorna($this->bytesImagem()),
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['puladas']);
        $this->assertFalse($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_tenta_candidatas_em_ordem_ate_uma_funcionar(): void
    {
        $user = User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => $user->id]);

        $bytes = $this->bytesImagem();
        $m = Mockery::mock(BaixadorImagem::class);
        $m->shouldReceive('baixarCapado')->once()->with('https://x/quebrada.jpg', Mockery::any())->andReturn(null);
        $m->shouldReceive('baixarCapado')->once()->with('https://x/ok.jpg', Mockery::any())->andReturn($bytes);

        $resumo = $this->importador(
            [['origem_id' => 77, 'fotos_urls' => ['https://x/quebrada.jpg', 'https://x/ok.jpg']]],
            $m,
        )->importar(fn ($m) => null);

        $this->assertSame(1, $resumo['anexadas']);
        $this->assertTrue($user->perfil->fresh()->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function test_sem_candidata_ou_sem_user_local_nao_faz_nada(): void
    {
        // usuário sem User local (origem 999 não existe) + usuário sem fotos_urls
        User::factory()->create(['origem_legado_id' => 77]);
        PerfilMembro::create(['user_id' => User::where('origem_legado_id', 77)->value('id')]);

        $resumo = $this->importador([
            ['origem_id' => 999, 'fotos_urls' => ['https://x/a.jpg']],
            ['origem_id' => 77, 'fotos_urls' => []],
        ], $this->baixadorQueRetorna($this->bytesImagem()))->importar(fn ($m) => null);

        $this->assertSame(0, $resumo['anexadas']);
        $this->assertSame(1, $resumo['sem_candidata']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
