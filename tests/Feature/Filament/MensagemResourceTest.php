<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Models\Mensagem;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_lista_renderiza(): void
    {
        Livewire::test(ListMensagens::class)->assertSuccessful();
    }

    public function test_mensagem_aparece_na_tabela(): void
    {
        Mensagem::factory()->create(['titulo' => 'Instruções para o atendimento']);

        Livewire::test(ListMensagens::class)->assertSee('Instruções para o atendimento');
    }

    public function test_form_titulo_obrigatorio(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('titulo', fn (TextInput $f) => $f->isRequired());
    }

    public function test_form_tem_rich_editor_corpo(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('corpo', fn (RichEditor $f) => true);
    }

    public function test_form_tem_textarea_contexto(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('contexto', fn (Textarea $f) => true);
    }

    public function test_form_usa_media_library_para_pictografia(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('pictografia', fn (SpatieMediaLibraryFileUpload $c): bool => $c->getCollection() === Mensagem::COLECAO_PICTOGRAFIA);
    }

    public function test_form_tem_select_nivel_com_publico_e_aceita_null(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => array_key_exists('publico', $f->getOptions()) && ! $f->isRequired());
    }

    public function test_form_tem_selects_de_relacao(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('autores', fn (Select $f) => $f->isMultiple())
            ->assertFormFieldExists('relacionadas', fn (Select $f) => $f->isMultiple());
    }

    public function test_form_nao_tem_campos_podados(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldDoesNotExist('origem_da_mensagem')
            ->assertFormFieldDoesNotExist('grupo_mediunico')
            ->assertFormFieldDoesNotExist('casa_espirita');
    }

    public function test_cria_mensagem_com_corpo_sanitizado(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Paz e amor',
                'slug' => 'paz-e-amor',
                'corpo' => '<p>Sede bons.</p><script>alert(1)</script>',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'paz-e-amor')->first();
        $this->assertNotNull($m);
        $this->assertStringNotContainsString('<script', (string) $m->corpo);
    }

    public function test_edita_mensagem(): void
    {
        $m = Mensagem::factory()->create(['titulo' => 'Título Antigo', 'slug' => 'titulo-antigo']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Título Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mensagens', ['slug' => 'titulo-antigo', 'titulo' => 'Título Novo']);
    }

    public function test_criar_com_relacionadas_espelha_nos_dois_lados(): void
    {
        $b = Mensagem::factory()->create(['titulo' => 'Mensagem B']);

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Mensagem A',
                'slug' => 'mensagem-a',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
                'relacionadas' => [$b->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Mensagem::where('slug', 'mensagem-a')->first();
        $this->assertTrue($a->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $a->id), 'a relação não espelhou no lado B');
    }

    public function test_rota_de_listagem_responde_ok(): void
    {
        Mensagem::factory()->count(3)->create();

        $this->get('/admin/mensagens')->assertOk();
    }
}
