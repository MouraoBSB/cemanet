<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportarResumosMensagensTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{wp_id: int, resumo: ?string}> $linhas */
    private function fakeLeitor(array $linhas): void
    {
        $this->app->bind(LeitorResumosMensagens::class, fn () => new class($linhas) implements LeitorResumosMensagens
        {
            public function __construct(private array $linhas) {}

            public function resumos(): array
            {
                return $this->linhas;
            }
        });
    }

    private const TEXTO = 'A mensagem responde a uma pergunta sobre a continuidade dos projetos.';

    public function test_preenche_resumo_vazio(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame(self::TEXTO, $m->fresh()->resumo);
    }

    public function test_nao_sobrescreve_resumo_ja_preenchido(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => 'Curadoria do diretor.']);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame('Curadoria do diretor.', $m->fresh()->resumo);
    }

    /** I3: uma coluna só. O comando não é um importador de mensagens. */
    public function test_nao_altera_titulo_corpo_slug_nivel_nem_status(): void
    {
        $m = Mensagem::factory()->create([
            'wp_id' => 21724, 'resumo' => null,
            'titulo' => 'Título Original', 'corpo' => '<p>Corpo original.</p>',
            'slug' => 'slug-original', 'nivel' => 'publico', 'status' => Mensagem::STATUS_PENDENTE,
        ]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $f = $m->fresh();
        $this->assertSame('Título Original', $f->titulo);
        $this->assertStringContainsString('Corpo original.', (string) $f->corpo);
        $this->assertSame('slug-original', $f->slug);
        $this->assertSame('publico', $f->nivel);
        $this->assertSame(Mensagem::STATUS_PENDENTE, $f->status);
    }

    /** I2: o histórico do DEPAE não pode encher de "mensagem atualizada" por causa do backfill. */
    public function test_nao_gera_linha_em_activity_log(): void
    {
        Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $antes = DB::table('activity_log')->count();
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame($antes, DB::table('activity_log')->count());
    }

    /** Discriminante do teste acima: sem withoutLogs, um update DESTES gera linha. */
    public function test_guarda_do_discriminante_update_normal_gera_linha(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $antes = DB::table('activity_log')->count();

        $m->update(['resumo' => self::TEXTO]);

        $this->assertGreaterThan($antes, DB::table('activity_log')->count());
    }

    /** I4: excerpt órfão não cria mensagem — por isso firstWhere, nunca firstOrNew. */
    public function test_ignora_excerpt_sem_mensagem_no_banco(): void
    {
        $this->fakeLeitor([['wp_id' => 99999, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame(0, Mensagem::count(), 'excerpt órfão não pode criar mensagem');
    }

    /** I4: a mensagem nascida no site (1 das 180) fica de fora por construção. */
    public function test_nao_toca_mensagem_sem_wp_id(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => null, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertNull($m->fresh()->resumo);
    }

    /** I5: os 3 lixos do legado (".", ".", "......") não podem virar meta description. */
    public function test_descarta_excerpt_curto_e_o_lista(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21762, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21762, 'resumo' => '.']]);

        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('21762')
            ->assertSuccessful();

        $this->assertNull($m->fresh()->resumo, 'o lixo foi gravado');
    }

    /** §8-7: com o leitor REAL e sem túnel, aborta limpo (FAILURE + instrução), sem stack trace. */
    public function test_sem_tunel_o_comando_falha_com_instrucao(): void
    {
        $this->app->bind(LeitorResumosMensagens::class, LeitorResumosMensagensMysql::class);
        config(['database.connections.legado.host' => '127.0.0.1', 'database.connections.legado.port' => 1]);
        DB::purge('legado');

        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('O túnel SSH está ativo?')
            ->assertFailed();
    }

    /** I5b: contadores mutuamente exclusivos — a soma fecha com o total lido. */
    public function test_contadores_sao_mutuamente_exclusivos(): void
    {
        Mensagem::factory()->create(['wp_id' => 1, 'resumo' => null]);              // atualizada
        Mensagem::factory()->create(['wp_id' => 2, 'resumo' => 'Já tenho resumo.']); // ja_tinha
        $this->fakeLeitor([
            ['wp_id' => 1, 'resumo' => self::TEXTO],
            ['wp_id' => 2, 'resumo' => self::TEXTO],
            ['wp_id' => 3, 'resumo' => self::TEXTO],   // sem_mensagem
            ['wp_id' => 4, 'resumo' => '...'],         // curta
        ]);

        // ⚠️ UMA asserção com a linha INTEIRA, não 4 substrings. Cada expectsOutputToContain
        // vira uma expectativa `doWrite`+`withArgs` (PendingCommand.php:614-621); quando várias
        // casam com a MESMA chamada — e os 4 contadores saem numa linha só — o Mockery consome
        // apenas a primeira, e as outras nunca esvaziam `expectedOutputSubstrings`. Com a linha
        // completa é 1 expectativa para 1 chamada, e ainda prova mais: o formato exato.
        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('Atualizadas: 1 · Já tinham resumo: 1 · Sem mensagem no banco: 1 · Descartadas por serem curtas: 1')
            ->assertSuccessful();
    }
}
