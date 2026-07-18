<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Filament;

use App\Filament\Resources\AutoresEspirituais\Pages\CreateAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\EditAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\ListAutoresEspirituais;
use App\Models\AutorEspiritual;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutorEspiritualResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_lista_renderiza(): void
    {
        Livewire::test(ListAutoresEspirituais::class)->assertSuccessful();
    }

    public function test_autor_aparece_na_tabela(): void
    {
        AutorEspiritual::factory()->create(['nome' => 'Bezerra de Menezes']);

        Livewire::test(ListAutoresEspirituais::class)->assertSee('Bezerra de Menezes');
    }

    public function test_form_nome_obrigatorio(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('nome', fn (TextInput $f) => $f->isRequired());
    }

    public function test_form_tem_rich_editor_bio(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('bio', fn (RichEditor $f) => true);
    }

    public function test_form_usa_media_library_para_foto(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('foto', fn (SpatieMediaLibraryFileUpload $c): bool => $c->getCollection() === AutorEspiritual::COLECAO_FOTO);
    }

    public function test_chamada_opcional(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('chamada', fn (TextInput $f): bool => ! $f->isRequired());
    }

    public function test_form_nao_tem_campos_de_contato(): void
    {
        // A ausência é garantida no schema da tabela (ver AutorEspiritualTest). Aqui provamos no form.
        // Se `assertFormFieldDoesNotExist` não existir na sua versão do Filament, remova estas 4 linhas —
        // a garantia dura é o teste de tabela (Task 1) + o corte da seção no form.
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldDoesNotExist('email')
            ->assertFormFieldDoesNotExist('telefone')
            ->assertFormFieldDoesNotExist('mostrar_email')
            ->assertFormFieldDoesNotExist('mostrar_telefone');
    }

    public function test_cria_autor_com_chamada_e_bio_sanitizada(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->fillForm([
                'nome' => 'Irmã Cecília',
                'slug' => 'irma-cecilia',
                'chamada' => 'Servindo na seara.',
                'ativo' => true,
                'bio' => '<p>Espírito de luz.</p><script>alert(1)</script>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $autor = AutorEspiritual::where('slug', 'irma-cecilia')->first();
        $this->assertNotNull($autor);
        $this->assertSame('Servindo na seara.', $autor->chamada);
        $this->assertStringNotContainsString('<script', (string) $autor->bio);
    }

    public function test_edita_autor(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Nome Antigo', 'slug' => 'nome-antigo']);

        Livewire::test(EditAutorEspiritual::class, ['record' => $autor->getRouteKey()])
            ->fillForm(['nome' => 'Nome Atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('autores_espirituais', ['slug' => 'nome-antigo', 'nome' => 'Nome Atualizado']);
    }

    public function test_rota_de_listagem_responde_ok(): void
    {
        AutorEspiritual::factory()->count(3)->create();

        $this->get('/admin/autores-espirituais')->assertOk();
    }
}
