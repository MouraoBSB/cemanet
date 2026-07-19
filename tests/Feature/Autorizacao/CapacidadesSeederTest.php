<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CapacidadesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_semeia_os_28_nomes_exatos_e_e_idempotente(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $this->seed(CapacidadesSeeder::class); // 2ª vez não duplica

        $esperados = [
            'evento.ver', 'evento.criar', 'evento.editar', 'evento.excluir',
            'palestra.ver', 'palestra.criar', 'palestra.editar', 'palestra.excluir',
            'post.ver', 'post.criar', 'post.editar', 'post.excluir',
            'agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir',
            'palestrante.ver', 'palestrante.criar', 'palestrante.editar', 'palestrante.excluir',
            'autor_espiritual.ver', 'autor_espiritual.criar', 'autor_espiritual.editar', 'autor_espiritual.excluir',
            'mensagem.ver', 'mensagem.criar', 'mensagem.editar', 'mensagem.excluir',
        ];

        $this->assertSame(28, Permission::where('guard_name', 'web')->count());
        foreach ($esperados as $nome) {
            $this->assertDatabaseHas('permissions', ['name' => $nome, 'guard_name' => 'web']);
        }
    }
}
