<?php

namespace Tests\Feature\Usuarios;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PivosTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_em_varios_setores_com_funcao(): void
    {
        $user = User::factory()->create();
        $brecho = Setor::create(['nome' => 'Brechó', 'slug' => 'brecho']);
        $campanha = Setor::create(['nome' => 'Campanha Auta de Souza', 'slug' => 'campanha-auta-de-souza']);

        $user->setores()->attach([
            $brecho->id => ['funcao' => 'membro'],
            $campanha->id => ['funcao' => 'coordenador'],
        ]);

        $this->assertCount(2, $user->setores);
        $this->assertSame('coordenador', $user->setores()->find($campanha->id)->pivot->funcao);
    }
}
