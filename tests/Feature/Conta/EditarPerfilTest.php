<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Conta;

use App\Livewire\Conta\EditarPerfil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EditarPerfilTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function membro(): User
    {
        $u = User::factory()->create(['name' => 'Nome Antigo', 'ativo' => true, 'socio' => false]);
        $u->assignRole('frequentador');
        $u->perfil()->create([]);

        return $u;
    }

    public function test_salva_dados_pessoais_e_contato(): void
    {
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('name', 'Nome Novo')
            ->set('data_nascimento', '1990-05-10')
            ->set('endereco', 'Rua Nova, 42')
            ->set('whatsapp', '61988887777')
            ->set('whatsapp_publico', true)
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertRedirect(route('conta.perfil'));

        $user->refresh();
        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame('Rua Nova, 42', $user->perfil->endereco);
        $this->assertSame('61988887777', $user->perfil->whatsapp);
        $this->assertTrue($user->perfil->whatsapp_publico);
        $this->assertSame('1990-05-10', $user->perfil->data_nascimento->format('Y-m-d'));
    }

    public function test_upload_de_foto_grava_na_media_library(): void
    {
        Storage::fake('public');
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('foto', UploadedFile::fake()->image('avatar.jpg', 800, 800))
            ->call('salvar')
            ->assertHasNoErrors();

        $this->assertNotNull($user->perfil->fresh()->foto_url);
    }

    public function test_foto_rejeita_arquivo_nao_imagem(): void
    {
        $user = $this->membro();

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('foto', UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'))
            ->call('salvar')
            ->assertHasErrors(['foto']);
    }

    public function test_nao_permite_editar_papel_socio_ou_setor(): void
    {
        $user = $this->membro();
        $this->assertFalse($user->socio);

        Livewire::actingAs($user)->test(EditarPerfil::class)
            ->set('name', 'Só o Nome')
            ->call('salvar');

        $user->refresh();
        $this->assertFalse($user->socio);                     // socio intocado
        $this->assertTrue($user->hasRole('frequentador'));    // papel intocado
        $this->assertCount(0, $user->setores);                // setores intocados
    }
}
