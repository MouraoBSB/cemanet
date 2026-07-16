<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\GlossarioCapacidades;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TiposConteudoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    private function siglasDe(string $recurso): array
    {
        return TipoConteudo::where('recurso', $recurso)->first()
            ->departamentos->pluck('sigla')->sort()->values()->all();
    }

    public function test_semeia_todos_os_recursos_do_glossario(): void
    {
        $this->seed(TiposConteudoSeeder::class);

        $this->assertSame(count(GlossarioCapacidades::RECURSOS), TipoConteudo::count());
        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $this->assertDatabaseHas('tipos_conteudo', ['recurso' => $recurso]);
        }
    }

    public function test_a_semente_bate_com_o_que_cada_tipo_ja_tem_hoje(): void
    {
        $this->seed(TiposConteudoSeeder::class);

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
        $this->assertSame(['DED'], $this->siglasDe('palestra'));
        $this->assertSame(['DED'], $this->siglasDe('palestrante'));
        $this->assertSame(['DECOM'], $this->siglasDe('post'));
        $this->assertSame([], $this->siglasDe('evento'));
    }

    public function test_regimes_da_semente(): void
    {
        $this->seed(TiposConteudoSeeder::class);

        foreach (['agenda', 'palestra', 'palestrante', 'post'] as $recurso) {
            $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', $recurso)->first()->regime);
        }

        $this->assertSame(RegimeAcesso::PorRegistro, TipoConteudo::where('recurso', 'evento')->first()->regime);
    }

    public function test_e_idempotente(): void
    {
        $this->seed(TiposConteudoSeeder::class);
        $this->seed(TiposConteudoSeeder::class);

        $this->assertSame(count(GlossarioCapacidades::RECURSOS), TipoConteudo::count());
        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
    }

    /** I8: o seeder é insert-only — reexecutar db:seed NÃO desfaz o que o admin configurou na tela. */
    public function test_nao_sobrescreve_a_config_feita_na_tela(): void
    {
        $this->seed(TiposConteudoSeeder::class);

        // o admin, pela tela: Agenda passa a responsabilidade só do DECOM e vira "por registro"
        $agenda = TipoConteudo::where('recurso', 'agenda')->first();
        $agenda->update(['regime' => RegimeAcesso::PorRegistro]);
        $agenda->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->seed(TiposConteudoSeeder::class);

        $this->assertSame(RegimeAcesso::PorRegistro, $agenda->fresh()->regime, 'o seeder reescreveu o regime');
        $this->assertSame(['DECOM'], $this->siglasDe('agenda'), 'o seeder reescreveu os responsáveis (DED voltou)');
    }

    public function test_recurso_do_glossario_sem_semente_falha_explicitamente(): void
    {
        $seeder = new class extends TiposConteudoSeeder
        {
            protected function recursos(): array
            {
                return ['recurso_fantasma'];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('recurso_fantasma');

        $seeder->run();
    }
}
