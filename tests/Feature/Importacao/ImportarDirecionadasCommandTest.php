<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorDirecionadasMensagem;
use App\Importacao\LeitorDirecionadasMensagemMysql;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarDirecionadasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_roda_com_fake_e_popula(): void
    {
        Mensagem::factory()->create(['wp_id' => 100, 'slug' => 'm100', 'nivel' => 'direcionada']);
        User::factory()->create(['origem_legado_id' => 900]);

        $this->app->instance(LeitorDirecionadasMensagem::class, new class implements LeitorDirecionadasMensagem
        {
            public function direcionadas(): array
            {
                return [['wp_id' => 100, 'destinatarios_wp_ids' => [900]]];
            }
        });

        $this->artisan('cema:importar-direcionadas')->assertSuccessful();
        $this->assertSame(1, Mensagem::firstWhere('wp_id', 100)->destinatarios()->count());
    }

    public function test_bind_resolve_o_leitor_real_sem_tocar_o_legado(): void
    {
        // Guarda C7: sem fake, o container devolve o leitor Mysql (só resolve; não chama direcionadas()).
        $this->assertInstanceOf(
            LeitorDirecionadasMensagemMysql::class,
            $this->app->make(LeitorDirecionadasMensagem::class),
        );
    }
}
