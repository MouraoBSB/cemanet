<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Mensagens;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Prova a SEMÂNTICA da migration de fusão (F4c-D, I1/I2). O CI roda `migrate` contra um MySQL
 * VAZIO: exit 0 não prova cópia alguma — um WHERE errado copia 0 linhas em silêncio. Aqui a
 * coluna `contexto` (já dropada pela migration seguinte) é recriada, o dado é inserido por
 * DB::table (o model não a enxerga mais) e o up() roda de verdade.
 *
 * ⚠️ DEPENDE DE DDL TRANSACIONAL — só funciona em SQLite. O `Schema::table` do cenario() roda
 * dentro da transação do RefreshDatabase; o SQLite reverte o DDL junto, o MySQL NÃO (lá o DDL
 * causa commit implícito e a coluna recriada vazaria para os testes seguintes). Hoje isso é
 * seguro porque o phpunit.xml força sqlite/:memory:; se um dia a suíte migrar para MySQL, este
 * teste precisa de outro desenho.
 */
class FusaoContextoResumoTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_07_23_000001_funde_contexto_em_resumo_nas_mensagens.php');
    }

    /** Recria a coluna dropada e devolve os ids das 4 linhas de fixture. */
    private function cenario(): array
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            $tabela->text('contexto')->nullable();
        });

        $base = [
            'formato' => 'psicografia', 'casa' => 'CEMA', 'status' => 'pendente',
            'created_at' => now(), 'updated_at' => now(),
        ];

        return [
            'so_contexto' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Só contexto', 'slug' => 'so-contexto',
                'contexto' => 'Texto que precisa sobreviver.', 'resumo' => null,
            ]),
            'resumo_vazio' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Resumo vazio', 'slug' => 'resumo-vazio',
                'contexto' => 'Também precisa sobreviver.', 'resumo' => '',
            ]),
            'os_dois' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Os dois', 'slug' => 'os-dois',
                'contexto' => 'Texto do contexto.', 'resumo' => 'Texto do resumo.',
            ]),
            'sem_contexto' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Sem contexto', 'slug' => 'sem-contexto',
                'contexto' => null, 'resumo' => 'Resumo intacto.',
            ]),
            'sem_contexto_resumo_vazio' => DB::table('mensagens')->insertGetId($base + [
                'titulo' => 'Sem contexto e resumo vazio', 'slug' => 'sem-contexto-resumo-vazio',
                'contexto' => null, 'resumo' => '',
            ]),
        ];
    }

    private function resumo(int $id): ?string
    {
        return DB::table('mensagens')->where('id', $id)->value('resumo');
    }

    /** I1: onde o resumo estava vazio (NULL ou ''), o texto do contexto passa a ser o resumo. */
    public function test_i1_copia_o_contexto_quando_o_resumo_esta_vazio(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();

        $this->assertSame('Texto que precisa sobreviver.', $this->resumo($ids['so_contexto']));
        $this->assertSame('Também precisa sobreviver.', $this->resumo($ids['resumo_vazio']));
    }

    /** I2: com os dois preenchidos, o resumo VENCE — precedência explícita, não acidente de ordem. */
    public function test_i2_resumo_preenchido_nao_e_sobrescrito(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();

        $this->assertSame('Texto do resumo.', $this->resumo($ids['os_dois']));
        $this->assertSame('Resumo intacto.', $this->resumo($ids['sem_contexto']));
    }

    /**
     * Discrimina o `where(function ...)` agrupado de um `orWhere` solto: sem o agrupamento, o SQL
     * vira `(contexto IS NOT NULL AND contexto <> '' AND resumo IS NULL) OR (resumo = '')` e esta
     * linha (contexto NULO, resumo já '') seria pega pelo `resumo = ''` isolado — o UPDATE
     * sobrescreveria o resumo com o `contexto` nulo. As outras 4 fixtures não distinguem as duas
     * variantes; só esta prova que a closure é essencial.
     */
    public function test_linha_sem_contexto_nao_e_tocada_mesmo_com_resumo_vazio(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();

        $this->assertSame('', $this->resumo($ids['sem_contexto_resumo_vazio']));
    }

    /** Idempotência: rodar duas vezes não muda nada (é o que torna o par de migrations seguro). */
    public function test_rodar_duas_vezes_e_no_op(): void
    {
        $ids = $this->cenario();

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('Texto que precisa sobreviver.', $this->resumo($ids['so_contexto']));
        $this->assertSame('Texto do resumo.', $this->resumo($ids['os_dois']));
    }
}
