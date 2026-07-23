<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorMensagens;
use App\Importacao\LeitorMensagens;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ImportadorMensagensTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    private function leitor(array $mensagens): LeitorMensagens
    {
        return new class($mensagens) implements LeitorMensagens
        {
            public function __construct(private array $mensagens) {}

            public function mensagens(): array
            {
                return $this->mensagens;
            }
        };
    }

    private function baixador(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixarCapado(?string $url, int $teto = 2000): ?string
            {
                return $url ? base64_decode(ImportadorMensagensTest::pngBytes()) : null;
            }
        };
    }

    public static function pngBytes(): string
    {
        return self::PNG_1X1;
    }

    private function mensagemLegado(array $overrides = []): array
    {
        return array_merge([
            'wp_id' => 21694,
            'titulo' => 'Instruções para o atendimento',
            'slug' => 'instrucoes-para-o-atendimento',
            'corpo' => '<p>Servi sempre.</p>',
            'formato' => 'psicografia',
            'data_recebimento' => '1722902400',   // 2024-08-06 (unix ts, meia-noite UTC)
            'nivel' => 'publico',
            'autores_slugs' => [],
            'fotos_urls' => [],
            'link_arquivo' => null,
            'liberar_download' => 'false',
            'status' => 'publicado',
        ], $overrides);
    }

    private function importar(array $mensagens): array
    {
        return (new ImportadorMensagens($this->leitor($mensagens), $this->baixador()))->importar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_mapeia_campos_do_legado(): void
    {
        $this->importar([$this->mensagemLegado()]);

        $m = Mensagem::firstWhere('wp_id', 21694);
        $this->assertNotNull($m);
        $this->assertSame('Instruções para o atendimento', $m->titulo);
        $this->assertStringContainsString('Servi sempre', (string) $m->corpo);
        $this->assertSame('psicografia', $m->formato->value);
        $this->assertSame('2024-08-06', $m->data_recebimento->format('Y-m-d'));
        $this->assertSame('publico', $m->nivel);
        $this->assertSame('publicado', $m->status);
        $this->assertSame('CEMA', $m->casa);       // constante — poda de casa_espirita
        $this->assertNull($m->resumo);              // texto editorial da curadoria — o import de mensagens não escreve
    }

    public function test_nivel_ausente_vira_null(): void
    {
        $this->importar([$this->mensagemLegado(['wp_id' => 26021, 'nivel' => null])]);

        $this->assertNull(Mensagem::firstWhere('wp_id', 26021)->nivel);
    }

    public function test_gera_slug_unico_para_pending_sem_post_name(): void
    {
        $this->importar([
            $this->mensagemLegado(['wp_id' => 30001, 'titulo' => 'Sem slug um', 'slug' => '', 'status' => 'pendente']),
            $this->mensagemLegado(['wp_id' => 30002, 'titulo' => 'Sem slug dois', 'slug' => '', 'status' => 'pendente']),
        ]);

        $this->assertSame('sem-slug-um-30001', Mensagem::firstWhere('wp_id', 30001)->slug);
        $this->assertSame('sem-slug-dois-30002', Mensagem::firstWhere('wp_id', 30002)->slug);
    }

    public function test_slug_gerado_nao_colide_com_publish(): void
    {
        // Um publish com slug 'obra' e um pending sem slug cujo título geraria 'obra' — o sufixo wp_id evita colisão.
        $this->importar([
            $this->mensagemLegado(['wp_id' => 40001, 'titulo' => 'Obra', 'slug' => 'obra', 'status' => 'publicado']),
            $this->mensagemLegado(['wp_id' => 40002, 'titulo' => 'Obra', 'slug' => '', 'status' => 'pendente']),
        ]);

        $this->assertSame('obra', Mensagem::firstWhere('wp_id', 40001)->slug);
        $this->assertSame('obra-40002', Mensagem::firstWhere('wp_id', 40002)->slug);
        $this->assertSame(2, Mensagem::count());
    }

    public function test_autor_por_slug_sincroniza_n_n(): void
    {
        AutorEspiritual::factory()->create(['slug' => 'bezerra-de-menezes', 'nome' => 'Bezerra de Menezes']);

        $this->importar([$this->mensagemLegado(['autores_slugs' => ['bezerra-de-menezes']])]);

        $m = Mensagem::firstWhere('wp_id', 21694);
        $this->assertTrue($m->autores->contains('slug', 'bezerra-de-menezes'));
    }

    public function test_autor_slug_inexistente_vira_aviso_sem_quebrar(): void
    {
        $resumo = $this->importar([$this->mensagemLegado(['autores_slugs' => ['fantasma-inexistente']])]);

        $m = Mensagem::firstWhere('wp_id', 21694);
        $this->assertCount(0, $m->autores);
        $this->assertNotEmpty($resumo['avisos']);
        $this->assertStringContainsString('fantasma-inexistente', implode("\n", $resumo['avisos']));
    }

    public function test_pictografia_multi_anexa_todas(): void
    {
        $this->importar([$this->mensagemLegado([
            'formato' => 'pictografia',
            'fotos_urls' => ['https://legado.example/a.jpg', 'https://legado.example/b.jpg'],
        ])]);

        $m = Mensagem::firstWhere('wp_id', 21694);
        $this->assertSame(2, $m->getMedia(Mensagem::COLECAO_IMAGENS)->count());
    }

    public function test_reimport_sem_pictografia_preserva_upload_do_admin(): void
    {
        // O1: mensagem sem _fotos_mensagem no legado NÃO tem a pictografia limpa num re-import.
        $this->importar([$this->mensagemLegado(['fotos_urls' => []])]);
        $m = Mensagem::firstWhere('wp_id', 21694);

        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('manual.png')->toMediaCollection(Mensagem::COLECAO_IMAGENS);
        $this->assertTrue($m->fresh()->hasMedia(Mensagem::COLECAO_IMAGENS));

        $this->importar([$this->mensagemLegado(['fotos_urls' => []])]);   // re-import sem fotos

        $this->assertTrue($m->fresh()->hasMedia(Mensagem::COLECAO_IMAGENS), 'a pictografia do /admin foi apagada (clobber O1)');
    }

    public function test_download_drive_vira_link_direto(): void
    {
        $this->importar([$this->mensagemLegado([
            'link_arquivo' => 'https://drive.google.com/uc?export=download&amp;id=1tcPovMIenZvogAU48gugNZKDVQ1S4Bp7',
            'liberar_download' => 'true',
        ])]);

        $m = Mensagem::firstWhere('wp_id', 21694);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1tcPovMIenZvogAU48gugNZKDVQ1S4Bp7', $m->link_arquivo);
        $this->assertTrue($m->liberar_download);
    }

    public function test_liberar_falsy_nao_expoe_download(): void
    {
        $this->importar([$this->mensagemLegado(['liberar_download' => 'false'])]);

        $this->assertFalse(Mensagem::firstWhere('wp_id', 21694)->liberar_download);
    }

    public function test_e_idempotente_por_wp_id(): void
    {
        $dados = $this->mensagemLegado(['fotos_urls' => ['https://legado.example/a.jpg']]);
        $this->importar([$dados]);
        $this->importar([$dados]);

        $this->assertSame(1, Mensagem::count());
        $this->assertSame(1, Mensagem::firstWhere('wp_id', 21694)->getMedia(Mensagem::COLECAO_IMAGENS)->count());
    }

    public function test_nao_sincroniza_departamentos(): void
    {
        $this->importar([$this->mensagemLegado()]);

        $this->assertSame(0, Mensagem::firstWhere('wp_id', 21694)->departamentos()->count());
    }

    public function test_nao_popula_relacionadas(): void
    {
        $this->importar([
            $this->mensagemLegado(['wp_id' => 21694]),
            $this->mensagemLegado(['wp_id' => 21695, 'titulo' => 'Outra', 'slug' => 'outra']),
        ]);

        $this->assertSame(0, Mensagem::firstWhere('wp_id', 21694)->relacionadas()->count());
    }

    public function test_publish_sem_nivel_conta_no_resumo(): void
    {
        $resumo = $this->importar([$this->mensagemLegado(['status' => 'publicado', 'nivel' => null])]);

        $this->assertSame(1, $resumo['contadores']['publish_sem_nivel']);
    }

    public function test_reimport_preserva_curadoria_do_admin(): void
    {
        // Curadoria = slug/status/nivel (create-only) + resumo (nunca) + relacionadas (nunca).
        $this->importar([$this->mensagemLegado(['nivel' => null])]);
        $m = Mensagem::firstWhere('wp_id', 21694);
        $outra = Mensagem::factory()->create();

        $m->update(['nivel' => 'publico', 'status' => 'despublicada', 'resumo' => 'nota do admin']);
        $m->sincronizarRelacionadas([$outra->id]);

        $this->importar([$this->mensagemLegado(['nivel' => null])]);   // re-import (legado sem termo)

        $m->refresh();
        $this->assertSame('publico', $m->nivel, 'nível classificado pelo admin foi zerado');
        $this->assertSame('despublicada', $m->status, 'status do admin foi sobrescrito');
        $this->assertSame('nota do admin', $m->resumo, 'resumo foi tocado pelo import');
        $this->assertTrue($m->relacionadas->contains('id', $outra->id), 'relacionadas foi tocada pelo import');
    }

    /**
     * I17 (Fatia F4b, Task 12): reimportar o MESMO lote não pode inflar a trilha (`activity_log`,
     * `log_name = 'mensagem'`) — o `LogsActivity` do model usa `logOnlyDirty()`, então um segundo
     * import só é silencioso se os mutators forem determinísticos sobre o MESMO dado bruto do
     * legado. Fixture que MORDE (molde do `link_arquivo` em test_download_drive_vira_link_direto,
     * acima): `link_arquivo` com `&amp;` cru (LinkDrive::paraDownload decodifica+normaliza), um
     * `corpo` com `<script>` que o `clean()` reescreve (HTMLPurifier remove a tag fora da allow-list
     * do perfil 'conteudo') e `data_recebimento` em unix (TransformadorLegado::unixParaData +
     * mutator Carbon↔string). Se qualquer um desses três não for idempotente, o segundo import
     * marcaria o atributo como dirty e geraria uma entrada espúria — é isso que este teste prova.
     */
    public function test_i17_reimport_do_mesmo_lote_nao_gera_entradas_na_trilha(): void
    {
        $dados = $this->mensagemLegado([
            'corpo' => '<p>Servi <script>alert(1)</script>sempre.</p>',
            'link_arquivo' => 'https://drive.google.com/uc?export=download&amp;id=1tcPovMIenZvogAU48gugNZKDVQ1S4Bp7',
            'liberar_download' => 'true',
        ]);

        $this->importar([$dados]);
        Activity::query()->delete(); // ignora a(s) entrada(s) 'created' do primeiro import

        $this->importar([$dados]); // MESMO lote, mesmo dado bruto

        $this->assertSame(0, Activity::where('log_name', 'mensagem')->count());
    }
}
