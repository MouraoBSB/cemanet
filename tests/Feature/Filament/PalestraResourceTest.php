<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Palestras\Pages\CreatePalestra;
use App\Filament\Resources\Palestras\Pages\EditPalestra;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestraResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_palestra_com_assuntos_destaques_e_um_palestrante(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();
        $assunto = Assunto::factory()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Auxílios do Invisível',
                'slug' => 'auxilios-do-invisivel',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'assuntos' => [$assunto->id],
                'destaques' => [
                    ['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'auxilios-do-invisivel')->first();
        $this->assertNotNull($palestra);
        $this->assertTrue($palestra->assuntos->contains($assunto));
        $this->assertCount(1, $palestra->destaques);
    }

    public function test_lista_renderiza(): void
    {
        Palestra::factory()->count(3)->create();

        $this->get('/admin/palestras')->assertOk();
    }

    public function test_pivo_grava_papel_correto_para_palestrante_e_diretor(): void
    {
        $pal = Palestrante::factory()->ativo()->create();
        $dir = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Com Diretor',
                'slug' => 'com-diretor',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$pal->id],
                'id_diretor' => $dir->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'com-diretor')->first();
        $this->assertEqualsCanonicalizing(
            [$pal->id],
            $palestra->palestrantes()->wherePivot('papel', Palestra::PAPEL_PALESTRANTE)->pluck('palestrantes.id')->all()
        );
        $this->assertSame(
            $dir->id,
            $palestra->palestrantes()->wherePivot('papel', Palestra::PAPEL_DIRETOR)->value('palestrantes.id')
        );
    }

    public function test_rejeita_zero_palestrantes(): void
    {
        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Sem Palestrante',
                'slug' => 'sem-palestrante',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['ids_palestrantes']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'sem-palestrante']);
    }

    public function test_rejeita_tres_palestrantes(): void
    {
        $tres = Palestrante::factory()->ativo()->count(3)->create()->pluck('id')->all();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Três Palestrantes',
                'slug' => 'tres-palestrantes',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => $tres,
            ])
            ->call('create')
            ->assertHasFormErrors(['ids_palestrantes']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'tres-palestrantes']);
    }

    public function test_edit_preenche_selects_a_partir_do_pivo(): void
    {
        $palestra = Palestra::factory()->create();
        $pal = Palestrante::factory()->ativo()->create();
        $dir = Palestrante::factory()->ativo()->create();
        $palestra->palestrantes()->attach($pal, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($dir, ['papel' => Palestra::PAPEL_DIRETOR]);

        Livewire::test(EditPalestra::class, ['record' => $palestra->getRouteKey()])
            ->assertFormSet([
                'ids_palestrantes' => [$pal->id],
                'id_diretor' => $dir->id,
            ]);
    }

    public function test_edit_troca_palestrante_e_ressincroniza_pivo(): void
    {
        $antigo = Palestrante::factory()->ativo()->create();
        $novo = Palestrante::factory()->ativo()->create();

        $palestra = Palestra::factory()->create();
        $palestra->palestrantes()->attach($antigo, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        Livewire::test(EditPalestra::class, ['record' => $palestra->getRouteKey()])
            ->fillForm(['ids_palestrantes' => [$novo->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $palestra->refresh();
        $ids = $palestra->palestrantes()
            ->wherePivot('papel', Palestra::PAPEL_PALESTRANTE)
            ->pluck('palestrantes.id')
            ->all();

        $this->assertContains($novo->id, $ids, 'O novo palestrante deve estar no pivô');
        $this->assertNotContains($antigo->id, $ids, 'O antigo palestrante não deve estar mais no pivô');
    }

    public function test_rejeita_mesma_pessoa_como_palestrante_e_diretor(): void
    {
        $x = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Pessoa Dupla',
                'slug' => 'pessoa-dupla',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$x->id],
                'id_diretor' => $x->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['id_diretor']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'pessoa-dupla']);
    }

    public function test_rejeita_cor_fundo_invalido(): void
    {
        $palestrante = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Cor Inválida',
                'slug' => 'cor-invalida',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$palestrante->id],
                'cor_fundo' => 'vermelho',
            ])
            ->call('create')
            ->assertHasFormErrors(['cor_fundo']);

        $this->assertDatabaseMissing('palestras', ['slug' => 'cor-invalida']);
    }

    public function test_aceita_cor_fundo_hex_valido(): void
    {
        $palestrante = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Cor Válida',
                'slug' => 'cor-valida',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$palestrante->id],
                'cor_fundo' => '#4e4483',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('palestras', [
            'slug' => 'cor-valida',
            'cor_fundo' => '#4e4483',
        ]);
    }

    public function test_cria_palestra_com_slide_duracao_e_referencias(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Com Slide',
                'slug' => 'com-slide',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
                'duracao' => '≈1h10',
                'referencias_evangelicas' => 'João 14.',
                'referencias' => [
                    ['obra' => 'O Livro dos Espíritos', 'autor' => 'Allan Kardec', 'nota' => 'Conclusão.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'com-slide')->first();
        $this->assertSame('≈1h10', $palestra->duracao);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1ABCdefg_hij', $palestra->slide_download_url);
        $this->assertCount(1, $palestra->referencias);
        $this->assertSame('O Livro dos Espíritos', $palestra->referencias->first()->obra);
    }
}
