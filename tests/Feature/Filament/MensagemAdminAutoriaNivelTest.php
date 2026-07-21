<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Models\Mensagem;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemAdminAutoriaNivelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /** O2: o Select de nível tem os 6 níveis do enum — inclui 'diretor-depae', ausente da const antiga. */
    public function test_select_nivel_tem_as_seis_opcoes_do_enum(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => count($f->getOptions()) === 6
                && array_key_exists('diretor-depae', $f->getOptions()));
    }

    public function test_coluna_nivel_mostra_diretor_depae_e_sem_nivel_no_null(): void
    {
        Mensagem::factory()->comNivel(VisibilidadeMensagem::DiretorDepae)->create(['titulo' => 'Recado do DEPAE']);
        Mensagem::factory()->create(['titulo' => 'Mensagem sem nível', 'nivel' => null]);

        Livewire::test(ListMensagens::class)
            ->assertSee('Diretor do DEPAE')
            ->assertSee('— (sem nível)');
    }

    /** M3: coluna "Lançada por" existe e é lida por RELAÇÃO (medium_id está no $hidden do model). */
    public function test_coluna_lancada_por_existe(): void
    {
        Livewire::test(ListMensagens::class)
            ->assertTableColumnExists('medium.name');
    }

    public function test_coluna_lancada_por_mostra_o_nome_do_medium(): void
    {
        $medium = User::factory()->create(['name' => 'Médium Fulana de Tal']);
        Mensagem::factory()->create(['titulo' => 'Lançada pela médium', 'medium_id' => $medium->id]);

        Livewire::test(ListMensagens::class)
            ->assertSee('Médium Fulana de Tal');
    }

    public function test_coluna_lancada_por_mostra_placeholder_quando_importada_do_legado(): void
    {
        Mensagem::factory()->create(['titulo' => 'Mensagem legada', 'medium_id' => null]);

        Livewire::test(ListMensagens::class)
            ->assertSee('Importada do legado');
    }
}
