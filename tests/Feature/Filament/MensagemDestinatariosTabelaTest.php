<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosTabelaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_tabela_tem_coluna_contador(): void
    {
        Livewire::test(ListMensagens::class)
            ->assertTableColumnExists('destinatarios_count');
    }

    public function test_filtro_tem_destinatario_restringe_a_lista(): void
    {
        $direcionada = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create(['titulo' => 'Com destino']);
        $direcionada->destinatarios()->sync([User::factory()->create()->id, User::factory()->create()->id]);
        $publica = Mensagem::factory()->publica()->create(['titulo' => 'Sem destino']);

        Livewire::test(ListMensagens::class)
            ->filterTable('com_destinatarios')
            ->assertCanSeeTableRecords([$direcionada])
            ->assertCanNotSeeTableRecords([$publica]);
    }
}
