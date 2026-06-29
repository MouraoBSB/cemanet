<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace Tests\Feature\Filament;

use App\Filament\Resources\Palestrantes\Pages\CreatePalestrante;
use App\Filament\Resources\Palestrantes\Pages\EditPalestrante;
use App\Filament\Resources\Palestrantes\Pages\ListPalestrantes;
use App\Models\Palestrante;
use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestranteResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_pagina_listagem_renderiza(): void
    {
        Livewire::test(ListPalestrantes::class)
            ->assertSuccessful();
    }

    public function test_palestrante_aparece_na_tabela(): void
    {
        Palestrante::factory()->create(['nome' => 'João da Silva']);

        Livewire::test(ListPalestrantes::class)
            ->assertSee('João da Silva');
    }

    public function test_formulario_create_tem_campo_nome_obrigatorio(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->assertFormFieldExists('nome', fn (TextInput $field) => $field->isRequired());
    }

    public function test_formulario_create_tem_rich_editor_bio(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->assertFormFieldExists('bio', fn (RichEditor $field) => true);
    }

    public function test_form_usa_upload_de_media_library_para_foto(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->assertFormFieldExists('foto', fn (SpatieMediaLibraryFileUpload $campo): bool =>
                $campo->getCollection() === Palestrante::COLECAO_FOTO);
    }

    public function test_pode_criar_palestrante_via_formulario(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Maria Espírita',
                'slug' => 'maria-espirita',
                'bio' => '<p>Palestrante veterana.</p>',
                'email' => 'maria@cema.org.br',
                'mostrar_email' => false,
                'mostrar_telefone' => false,
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('palestrantes', ['slug' => 'maria-espirita']);
    }

    public function test_pode_editar_palestrante_via_formulario(): void
    {
        $palestrante = Palestrante::factory()->create(['nome' => 'Nome Antigo', 'slug' => 'nome-antigo']);

        Livewire::test(EditPalestrante::class, ['record' => $palestrante->getRouteKey()])
            ->fillForm(['nome' => 'Nome Atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('palestrantes', ['slug' => 'nome-antigo', 'nome' => 'Nome Atualizado']);
    }

    public function test_cria_palestrante_com_slug_auto_e_bio_sanitizada(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Maria das Dores',
                'slug' => 'maria-das-dores',
                'ativo' => true,
                'bio' => '<p>Bio</p><script>alert(1)</script>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pessoa = Palestrante::where('slug', 'maria-das-dores')->first();
        $this->assertNotNull($pessoa);
        $this->assertStringNotContainsString('<script', (string) $pessoa->bio);
    }

    public function test_lista_renderiza(): void
    {
        Palestrante::factory()->count(3)->create();

        $this->get('/admin/palestrantes')->assertOk();
    }
}
