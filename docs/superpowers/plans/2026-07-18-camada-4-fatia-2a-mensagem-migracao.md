# Camada 4 · Fatia 2A — Mensagem + migração (o GATE, camada de dados)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a entidade `Mensagem` (model + enum + 4 migrations, `HasMedia` + `TemDepartamento`, regime DoTipo da Camada 1, Policy inerte) com CRUD no `/admin` e migrar as 179 mensagens do CPT `mensagem-mediunicas` do WordPress legado (132 publish→`publicado`, 47 pending→`pendente`). Front público das 4 páginas fica na Fatia 2B.

**Architecture:** Model `Mensagem` sobre `mensagens` + 3 pivôs (`departamento_mensagem` inerte por paridade DoTipo; `mensagem_autor_espiritual` N:N com `AutorEspiritual` casado **por slug** na importação; `mensagem_relacionada` **auto-referente simétrico**, nasce vazio, curado no `/admin`). A Camada 1 é **data-driven**: somar `'mensagem'` ao `GlossarioCapacidades` propaga as 4 permissions (24→28) e a section da matriz; o `TiposConteudoSeeder` recebe a semente `DoTipo`+`['DEPAE']`. `MensagemPolicy` troca só `recurso()`. A importação clona a cadeia dos Autores/Eventos (Leitor interface + Mysql + Importador + Command + **bind de container obrigatório**), reusando `TransformadorLegado` (unix→data, truthy) e `LinkDrive` (Drive `&amp;`), com **chave `wp_id`**, **slug determinístico** para os 39 pending sem `post_name`, **pictografia multi-arquivo + preservação O1**, e **sem** sincronizar departamentos (DoTipo). A autorização nasce **inerte** (só admin edita via `/admin`; `Gate::before`); Fatias 3/4/5 a ligam.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-medialibrary · mews/purifier (`clean()`) · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-18-camada-4-fatia-2a-mensagem-migracao.md`](../specs/2026-07-18-camada-4-fatia-2a-mensagem-migracao.md) (aprovada pelo Consultor: **SÓLIDA**, com o obrigatório OA — campo `contexto` — aplicado; dois passes registrados no §13).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-4-fatia-2a-mensagem` a partir de `origin/main` (= **`ef8841b`**, PR #36, Fatia 1 mesclada). **Nunca** na `main`. O PR leva código **e** os commits de docs (SPEC + este plano) juntos.
- **Cabeçalho de autoria** em todo arquivo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18` (o factory do molde não tem cabeçalho — manter como o `AutorEspiritualFactory`).
- **🚫 Banco (dev):** só `php artisan migrate` **incremental**. **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo — o dev tem 152 usuários + 123 AgendaDia + Palestras + Posts + 19 autores + mídia. A conexão **`legado` é READ-ONLY** (só `SELECT`).
- **I2 — pluralização:** o model **DEVE** ter `protected $table = 'mensagens'` (o pluralizador do Laravel geraria `mensagems`) e o Resource **DEVE** ter `protected static ?string $slug = 'mensagens'`. Os pivôs referenciam `mensagens`/`autores_espirituais`/`departamentos` com `constrained('<tabela>')` **explícito**.
- **I17 — nomes de unique explícitos:** os 3 pivôs usam nome de índice explícito. O auto de `mensagem_autor_espiritual` daria **exatos 64 chars** (margem zero p/ o limite do MySQL; o SQLite dos testes não pega) — por isso `'mensagem_autor_espiritual_unique'` (32). Idem `'departamento_mensagem_unique'` e `'mensagem_relacionada_unique'`.
- **I6 — ordem de `RECURSOS`:** anexar `'mensagem'` ao **FINAL** de `GlossarioCapacidades::RECURSOS` (depois de `'autor_espiritual'`). Antes de `'palestra'` deixaria [TiposConteudoSeederTest:117](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L117) vermelho (a mensagem de erro deixaria de citar `DED`).
- **§9.0 — ordenação TDD:** o [GlossarioCapacidadesMapaTest:35-40](../../../tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php#L35-L40) faz `class_exists` sobre `RECURSOS_MODELS` ⇒ o model `Mensagem` **DEVE** existir (Task 1) **antes** do edit `'mensagem'=>Mensagem::class` (Task 2). A ordem das tasks respeita isso.
- **I12 — pictografia multi + O1:** o legado sobrescreve a pictografia **só quando tem `_fotos_mensagem`**; o `clearMediaCollection` roda **só após ≥1 download bem-sucedido**. Mensagem sem foto no legado (ou download falho) **preserva** o upload posto no `/admin`. **Nunca** um `clearMediaCollection` incondicional.
- **I11 — autor por SLUG:** o child `post_name` da rel 37 casa com `AutorEspiritual::firstWhere('slug', …)`; slug não resolvido vira **aviso** (não quebra o import).
- **I13 — import não toca depto, contexto nem clobber:** o Importador **não** chama `departamentos()->sync()` (DoTipo), **não** seta `casa`/`contexto` no `updateOrCreate` (`casa` default `'CEMA'`; `contexto` é manual), e **não** popula `relacionadas` (nasce vazia).
- **I16 — bind de container obrigatório:** o command e o Importador type-hintam a **interface** `LeitorMensagens`; sem `bind(LeitorMensagens::class, LeitorMensagensMysql::class)` no `AppServiceProvider`, `cema:importar-mensagens` quebra em **prod** com a **suíte verde**. Task 5 tem o teste-guarda.
- **Aceite:** suíte verde (**915 + novos**) e **nenhuma asserção de teste existente muda de cor**, exceto a edição deliberada de `CapacidadesSeederTest` (24→28) e os asserts aditivos de `TiposConteudoSeederTest`.
- **Comandos:** testes focados por task `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint <arquivos>` (o CI roda `pint --test` antes dos testes — [[pint-antes-de-push]]). Migrations no dev: `docker compose exec -T app php artisan migrate`. Se um teste rodar código aparentemente **stale** após editar um arquivo existente, `docker compose restart app worker` (OPcache `validate_timestamps=0`) e rode de novo.
- **Ciência de flaky:** [[flaky-importadorblog-gd-cap-imagem]] — 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados/no CI, não é regressão desta fatia.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-4-fatia-2a-mensagem origin/main
git log --oneline -1
```

Esperado: HEAD em `ef8841b` (merge do PR #36 — Fatia 1). Os commits de docs (SPEC + este plano) entram junto; o PR leva código **e** docs.

---

### Task 1: Fundação de dados — enum, 4 migrations, model, factory

**Files:**
- Create: `app/Enums/FormatoMensagem.php`
- Create: `database/migrations/2026_07_18_000001_create_mensagens_table.php`
- Create: `database/migrations/2026_07_18_000002_create_departamento_mensagem_table.php`
- Create: `database/migrations/2026_07_18_000003_create_mensagem_autor_espiritual_table.php`
- Create: `database/migrations/2026_07_18_000004_create_mensagem_relacionada_table.php`
- Create: `app/Models/Mensagem.php`
- Create: `database/factories/MensagemFactory.php`
- Test: `tests/Unit/Enums/FormatoMensagemTest.php`
- Test: `tests/Feature/Models/MensagemTest.php`

**Interfaces:**
- Consumes: `App\Models\{AutorEspiritual,Departamento}`, `App\Models\Concerns\RegistraImagensPadrao`, `App\Models\Contracts\TemDepartamento`, `App\Support\Palestras\LinkDrive` (mutator do `link_arquivo`).
- Produces:
  - `App\Enums\FormatoMensagem: string` — casos `Psicografia`/`Psicofonia`/`Pictografia`, `rotulo(): string`, `static opcoes(): array`.
  - `App\Models\Mensagem` — `$table='mensagens'`, `COLECAO_PICTOGRAFIA='pictografia'`, constantes `STATUS_PUBLICADO='publicado'`/`STATUS_PENDENTE='pendente'`/`STATUS_DESPUBLICADA='despublicada'`, `$fillable=[titulo,slug,corpo,contexto,formato,data_recebimento,casa,link_arquivo,liberar_download,nivel,status,wp_id]`, casts `formato=>FormatoMensagem`/`liberar_download=>bool`, mutators `corpo`/`data_recebimento`, `scopePublica(Builder): Builder`, `departamentos()`/`autores()`/`relacionadas(): BelongsToMany`, `sincronizarRelacionadas(array $ids): void`.
  - `Database\Factories\MensagemFactory` com estados `publicada()`/`pendente()`/`publica()`.

**Contexto:** o model clona o **enxuto** do `Post`/`AutorEspiritual`. `data_recebimento` é coluna **`date`** com mutator portável (molde [AgendaDia::data():95-101](../../../app/Models/AgendaDia.php#L95-L101), [[padrao-data-mutator-portavel]]) — o legado é dia-granular (§4 da SPEC). `formato` é enum em cast (molde [Evento:40-45](../../../app/Models/Evento.php#L40-L45)). A pictografia é coleção **multi-arquivo** (`unica:false`, molde [Evento:51](../../../app/Models/Evento.php#L51)). `relacionadas()` é o **1º pivô auto-referente do projeto**; `sincronizarRelacionadas` grava os **2 sentidos** numa transação (Consultor/O3). Ordem das migrations: base (`000001`) antes dos pivôs (`000002-4`).

- [ ] **Passo 1: Escrever o teste do enum que falha**

`tests/Unit/Enums/FormatoMensagemTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Unit\Enums;

use App\Enums\FormatoMensagem;
use PHPUnit\Framework\TestCase;

class FormatoMensagemTest extends TestCase
{
    public function test_tem_os_tres_formatos(): void
    {
        $values = array_map(fn (FormatoMensagem $f) => $f->value, FormatoMensagem::cases());
        $this->assertSame(['psicografia', 'psicofonia', 'pictografia'], $values);
    }

    public function test_opcoes_mapeia_value_para_rotulo(): void
    {
        $opcoes = FormatoMensagem::opcoes();
        $this->assertSame('Psicografia', $opcoes['psicografia']);
        $this->assertSame('Psicofonia', $opcoes['psicofonia']);
        $this->assertSame('Pictografia', $opcoes['pictografia']);
    }
}
```

- [ ] **Passo 2: Escrever o teste de model que falha**

`tests/Feature/Models/MensagemTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Models;

use App\Enums\FormatoMensagem;
use App\Models\Departamento;
use App\Models\Mensagem;
use App\Models\Contracts\TemDepartamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class MensagemTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_grava_na_tabela_mensagens(): void
    {
        $this->assertSame('mensagens', (new Mensagem)->getTable());
    }

    public function test_colunas_esperadas_e_podadas(): void
    {
        foreach (['titulo', 'slug', 'corpo', 'contexto', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('mensagens', $coluna), "coluna esperada ausente: {$coluna}");
        }
        foreach (['origem_da_mensagem', 'grupo_mediunico', 'casa_espirita'] as $coluna) {
            $this->assertFalse(Schema::hasColumn('mensagens', $coluna), "coluna podada presente: {$coluna}");
        }
    }

    public function test_fillable_exato(): void
    {
        $this->assertSame(
            ['titulo', 'slug', 'corpo', 'contexto', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'],
            (new Mensagem)->getFillable(),
        );
    }

    public function test_formato_reidrata_como_enum(): void
    {
        $m = Mensagem::factory()->create(['formato' => 'psicofonia']);
        $this->assertInstanceOf(FormatoMensagem::class, $m->fresh()->formato);
        $this->assertSame(FormatoMensagem::Psicofonia, $m->fresh()->formato);
    }

    public function test_liberar_download_e_boolean(): void
    {
        $m = Mensagem::factory()->create(['liberar_download' => 1]);
        $this->assertIsBool($m->fresh()->liberar_download);
        $this->assertTrue($m->fresh()->liberar_download);
    }

    public function test_corpo_e_sanitizado(): void
    {
        $m = Mensagem::factory()->create(['corpo' => '<p>Paz</p><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script', (string) $m->corpo);
        $this->assertStringContainsString('Paz', (string) $m->corpo);
    }

    public function test_contexto_e_texto_puro_persistido(): void
    {
        $m = Mensagem::factory()->create(['contexto' => 'Recebida na reunião pública de quarta.']);
        $this->assertSame('Recebida na reunião pública de quarta.', $m->fresh()->contexto);
    }

    public function test_data_recebimento_round_trip_carbon(): void
    {
        $m = Mensagem::factory()->create(['data_recebimento' => '2024-08-05']);
        $this->assertInstanceOf(Carbon::class, $m->fresh()->data_recebimento);
        $this->assertSame('2024-08-05', $m->fresh()->data_recebimento->format('Y-m-d'));
    }

    public function test_link_arquivo_normalizado_via_link_drive(): void
    {
        // R-A: o mutator normaliza tanto o import quanto um link colado no /admin.
        $m = Mensagem::factory()->create(['link_arquivo' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUv/view']);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1AbCdEfGhIjKlMnOpQrStUv', $m->fresh()->link_arquivo);
    }

    public function test_scope_publica_filtra_status_e_nivel(): void
    {
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'publico']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores']);
        Mensagem::factory()->create(['status' => 'pendente', 'nivel' => 'publico']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => null]);

        $publicas = Mensagem::publica()->get();
        $this->assertCount(1, $publicas);
        $this->assertSame('publico', $publicas->first()->nivel);
    }

    public function test_implementa_contratos_de_midia_e_departamento(): void
    {
        $m = new Mensagem;
        $this->assertInstanceOf(HasMedia::class, $m);
        $this->assertInstanceOf(TemDepartamento::class, $m);
    }

    public function test_departamentos_pelo_pivo(): void
    {
        $m = Mensagem::factory()->create();
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);

        $m->departamentos()->sync([$depto->id]);

        $this->assertTrue($m->fresh()->departamentos->contains('id', $depto->id));
        $this->assertDatabaseHas('departamento_mensagem', ['mensagem_id' => $m->id, 'departamento_id' => $depto->id]);
    }

    public function test_autores_n_n_pelo_pivo(): void
    {
        $m = Mensagem::factory()->create();
        $autor = \App\Models\AutorEspiritual::factory()->create(['slug' => 'bezerra-de-menezes']);

        $m->autores()->sync([$autor->id]);

        $this->assertTrue($m->fresh()->autores->contains('id', $autor->id));
        $this->assertDatabaseHas('mensagem_autor_espiritual', ['mensagem_id' => $m->id, 'autor_espiritual_id' => $autor->id]);
    }

    public function test_pictografia_multi_registra_conversoes(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->create();

        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('a.png')->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('b.png')->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);

        // multi-arquivo: a coleção guarda as 2 (não é singleFile).
        $this->assertSame(2, $m->fresh()->getMedia(Mensagem::COLECAO_PICTOGRAFIA)->count());
    }

    public function test_relacionadas_e_simetrica(): void
    {
        $a = Mensagem::factory()->create();
        $b = Mensagem::factory()->create();

        $a->sincronizarRelacionadas([$b->id]);

        $this->assertTrue($a->fresh()->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $a->id), 'relação não refletiu no outro sentido');
    }

    public function test_remover_relacionada_reflete_nos_dois_lados(): void
    {
        $a = Mensagem::factory()->create();
        $b = Mensagem::factory()->create();
        $a->sincronizarRelacionadas([$b->id]);

        $a->sincronizarRelacionadas([]);   // remove

        $this->assertCount(0, $a->fresh()->relacionadas);
        $this->assertCount(0, $b->fresh()->relacionadas, 'o outro lado ainda enxerga a relação removida');
    }

    public function test_relacionadas_nao_permite_auto_relacao(): void
    {
        $a = Mensagem::factory()->create();

        $a->sincronizarRelacionadas([$a->id]);

        $this->assertCount(0, $a->fresh()->relacionadas);
        $this->assertDatabaseMissing('mensagem_relacionada', ['mensagem_id' => $a->id, 'relacionada_id' => $a->id]);
    }
}
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="FormatoMensagemTest|MensagemTest"`
Esperado: FAIL — `Class "App\Enums\FormatoMensagem" not found` / `Class "App\Models\Mensagem" not found`.

- [ ] **Passo 4: Criar o enum**

`app/Enums/FormatoMensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Enums;

enum FormatoMensagem: string
{
    case Psicografia = 'psicografia';
    case Psicofonia = 'psicofonia';
    case Pictografia = 'pictografia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Psicografia => 'Psicografia',
            self::Psicofonia => 'Psicofonia',
            self::Pictografia => 'Pictografia',
        };
    }

    /** Mapa value => rótulo, para o Select do Filament. */
    public static function opcoes(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
```

- [ ] **Passo 5: Criar a migration da tabela base**

`database/migrations/2026_07_18_000001_create_mensagens_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();   // idempotência do legado
            $table->string('titulo');
            $table->string('slug')->unique();                            // 39 pending sem slug → gerado no import
            $table->longText('corpo')->nullable();
            $table->text('contexto')->nullable();                        // OA: faixa editorial manual (não IA); nasce null
            $table->string('formato')->nullable();                       // enum FormatoMensagem
            $table->date('data_recebimento')->nullable();                // dia-granular; nullable de origem
            $table->string('casa')->default('CEMA');
            $table->string('link_arquivo', 500)->nullable();             // M-A: alinha com o maxLength(500) do form
            $table->boolean('liberar_download')->default(false);
            $table->string('nivel')->nullable();                         // BRUTO (slug da taxonomia); 49/179 null
            $table->string('status')->default('publicado');              // publicado | pendente | despublicada
            $table->timestamps();

            $table->index('status');
            $table->index('data_recebimento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagens');
    }
};
```

- [ ] **Passo 6: Criar a migration do pivô de departamentos**

`database/migrations/2026_07_18_000002_create_departamento_mensagem_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_mensagem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['mensagem_id', 'departamento_id'], 'departamento_mensagem_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_mensagem');
    }
};
```

- [ ] **Passo 7: Criar a migration do pivô de autores (N:N)**

`database/migrations/2026_07_18_000003_create_mensagem_autor_espiritual_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_autor_espiritual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('autor_espiritual_id')->constrained('autores_espirituais')->cascadeOnDelete();

            // Nome EXPLÍCITO: o auto do Laravel daria exatos 64 chars (margem zero p/ o limite do MySQL).
            $table->unique(['mensagem_id', 'autor_espiritual_id'], 'mensagem_autor_espiritual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_autor_espiritual');
    }
};
```

- [ ] **Passo 8: Criar a migration do pivô auto-referente (relacionadas)**

`database/migrations/2026_07_18_000004_create_mensagem_relacionada_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensagem_relacionada', function (Blueprint $table) {
            $table->id();
            // Auto-referente: AMBAS as FKs apontam p/ 'mensagens' — o nome da tabela é OBRIGATÓRIO
            // (o Laravel inferiria a tabela pelo nome da coluna e erraria em 'relacionada_id').
            $table->foreignId('mensagem_id')->constrained('mensagens')->cascadeOnDelete();
            $table->foreignId('relacionada_id')->constrained('mensagens')->cascadeOnDelete();

            $table->unique(['mensagem_id', 'relacionada_id'], 'mensagem_relacionada_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensagem_relacionada');
    }
};
```

- [ ] **Passo 9: Criar o model**

`app/Models/Mensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Models;

use App\Enums\FormatoMensagem;
use App\Models\Concerns\RegistraImagensPadrao;
use App\Models\Contracts\TemDepartamento;
use App\Support\Palestras\LinkDrive;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Mensagem extends Model implements HasMedia, TemDepartamento
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    // Pluralização pt-BR: o pluralizador do Laravel geraria 'mensagems'.
    protected $table = 'mensagens';

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_PENDENTE = 'pendente';

    public const STATUS_DESPUBLICADA = 'despublicada';

    // Slug do termo "Público" da taxonomia nivel-de-acesso (nível BRUTO — a semântica rica é da Fatia 3).
    public const NIVEL_PUBLICO = 'publico';

    public const COLECAO_PICTOGRAFIA = 'pictografia';

    protected $fillable = [
        'titulo',
        'slug',
        'corpo',   // saneado pelo mutator corpo()
        'contexto', // texto puro (manual, não-IA); exibido escapado no front
        'formato',
        'data_recebimento',
        'casa',
        'link_arquivo',
        'liberar_download',
        'nivel',
        'status',
        'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'formato' => FormatoMensagem::class,
            'liberar_download' => 'boolean',
        ];
    }

    /** Só as Públicas publicadas — filtro FIXO (nunca um scope de visibilidade por papel; isso é Fatia 3). */
    public function scopePublica(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLICADO)
            ->where('nivel', self::NIVEL_PUBLICO);
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_mensagem', 'mensagem_id', 'departamento_id');
    }

    public function autores(): BelongsToMany
    {
        return $this->belongsToMany(AutorEspiritual::class, 'mensagem_autor_espiritual', 'mensagem_id', 'autor_espiritual_id');
    }

    public function relacionadas(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mensagem_relacionada', 'mensagem_id', 'relacionada_id');
    }

    /**
     * Sincroniza as mensagens relacionadas de forma SIMÉTRICA (A↔B): grava os dois sentidos
     * numa transação e nunca cria auto-relação. Substitui completamente o conjunto de vínculos
     * desta mensagem. O Select do /admin chama isto (fora do auto-sync do Filament — I15/O3).
     *
     * @param  array<int, int|string>  $ids
     */
    public function sincronizarRelacionadas(array $ids): void
    {
        $ids = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $this->id || $id === 0)
            ->unique()
            ->values();

        DB::transaction(function () use ($ids) {
            // remove os DOIS sentidos que envolvem esta mensagem
            DB::table('mensagem_relacionada')
                ->where('mensagem_id', $this->id)
                ->orWhere('relacionada_id', $this->id)
                ->delete();

            foreach ($ids as $id) {
                DB::table('mensagem_relacionada')->insert([
                    ['mensagem_id' => $this->id, 'relacionada_id' => $id],
                    ['mensagem_id' => $id, 'relacionada_id' => $this->id],
                ]);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        // Pictografia: MÚLTIPLAS imagens (o legado tem mensagem com 2). WebP web + miniatura pelo trait.
        $this->registrarColecaoImagem(self::COLECAO_PICTOGRAFIA, unica: false);
    }

    protected function corpo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }

    /**
     * `data_recebimento` como coluna `date` portável (Carbon↔string Y-m-d): o cast nativo `date`
     * diverge entre SQLite (testes) e MySQL (prod). Mesmo molde de AgendaDia::data().
     */
    protected function dataRecebimento(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value) : null,
            set: fn ($value) => $value !== null ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }

    /**
     * Normaliza o link para download direto (Drive `uc?export=download&id=...`) no SET — vale
     * tanto para a importação quanto para um link colado no /admin (R-A). Não-Drive fica intacto.
     */
    protected function linkArquivo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => LinkDrive::paraDownload($value),
        );
    }
}
```

- [ ] **Passo 10: Criar a factory**

`database/factories/MensagemFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Mensagem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MensagemFactory extends Factory
{
    protected $model = Mensagem::class;

    public function definition(): array
    {
        $titulo = fake()->sentence(4);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'corpo' => '<p>'.fake()->paragraph().'</p>',
            'contexto' => null,
            'formato' => fake()->randomElement(['psicografia', 'psicofonia', 'pictografia']),
            'data_recebimento' => fake()->date('Y-m-d'),
            'casa' => 'CEMA',
            'link_arquivo' => null,
            'liberar_download' => false,
            'nivel' => null,
            'status' => Mensagem::STATUS_PUBLICADO,
            'wp_id' => null,
        ];
    }

    public function publicada(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PUBLICADO]);
    }

    public function pendente(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PENDENTE]);
    }

    /** Pública = publicada E nível 'publico' (aparece no scope publica()). */
    public function publica(): static
    {
        return $this->state(fn () => ['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => Mensagem::NIVEL_PUBLICO]);
    }
}
```

- [ ] **Passo 11: Aplicar as migrations no dev (incremental)**

Run: `docker compose exec -T app php artisan migrate`
Esperado: as 4 migrations `2026_07_18_0000{1..4}_*` rodam `DONE`. **Nenhuma** outra migration roda (incremental).

- [ ] **Passo 12: Rodar os testes e ver passar**

Run: `docker compose exec -T app php artisan test --filter="FormatoMensagemTest|MensagemTest"`
Esperado: PASS (todos os métodos, incluindo os 3 de simetria/auto-relação das relacionadas).

- [ ] **Passo 13: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Enums/FormatoMensagem.php app/Models/Mensagem.php database/factories/MensagemFactory.php database/migrations/2026_07_18_0000*.php tests/Unit/Enums/FormatoMensagemTest.php tests/Feature/Models/MensagemTest.php
git add app/Enums/FormatoMensagem.php app/Models/Mensagem.php database/factories/MensagemFactory.php database/migrations/2026_07_18_0000*.php tests/Unit/Enums/FormatoMensagemTest.php tests/Feature/Models/MensagemTest.php
git commit -m "feat(camada-4-fatia-2a): model Mensagem + enum FormatoMensagem + 4 migrations + factory"
```

---

### Task 2: Camada 1 data-driven — glossário + semente + testes de contagem

**Files:**
- Modify: `app/Support/Autorizacao/GlossarioCapacidades.php` (3 mapas + 1 `use` + docblock)
- Modify: `database/seeders/TiposConteudoSeeder.php` (1 linha em `SEMENTE`)
- Modify: `tests/Feature/Autorizacao/CapacidadesSeederTest.php` (24→28 + 4 nomes)
- Modify: `tests/Feature/Autorizacao/TiposConteudoSeederTest.php` (asserts aditivos de `mensagem`)

**Interfaces:**
- Consumes: `App\Models\Mensagem` (Task 1), `App\Enums\RegimeAcesso`.
- Produces: recurso `'mensagem'` no catálogo ⇒ permissions `mensagem.{ver,criar,editar,excluir}`; tipo `mensagem` regime `DoTipo` responsáveis `['DEPAE']`.

**Contexto:** somar `'mensagem'` ao **FINAL** de `RECURSOS` (I6) propaga a `CapacidadesSeeder`, `MatrizCapacidades` e `GlossarioCapacidadesMapaTest` sozinho; o `TiposConteudoSeeder` **exige** a semente (senão `RuntimeException`). O `GlossarioCapacidadesMapaTest` fica verde por construção (o model existe da Task 1 — §9.0). `MatrizCapacidadesTest` não conta Sections — não muda de cor.

- [ ] **Passo 1: Atualizar `CapacidadesSeederTest` (o teste guia — vai falhar)**

Em `tests/Feature/Autorizacao/CapacidadesSeederTest.php`, ajustar o método de contagem para **28** + os 4 nomes de `mensagem` (renomear se citar "24"):

```php
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
```

- [ ] **Passo 2: Atualizar `TiposConteudoSeederTest` (asserts aditivos — vão falhar)**

Em `tests/Feature/Autorizacao/TiposConteudoSeederTest.php`, no teste que confere as siglas por tipo (`test_a_semente_bate...`) acrescentar a linha do `mensagem`:

```php
        $this->assertSame(['DEPAE'], $this->siglasDe('mensagem'));
```

E no `test_regimes_da_semente`, incluir `'mensagem'` no laço DoTipo (junto de `autor_espiritual`):

```php
        foreach (['agenda', 'palestra', 'palestrante', 'post', 'autor_espiritual', 'mensagem'] as $recurso) {
            $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', $recurso)->first()->regime);
        }
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="CapacidadesSeederTest|TiposConteudoSeederTest"`
Esperado: FAIL — `28 !== 24`; e `null` / `['DEPAE'] !== ...` (o recurso `mensagem` ainda não está no glossário nem na semente).

- [ ] **Passo 4: Adicionar `'mensagem'` ao glossário**

Em `app/Support/Autorizacao/GlossarioCapacidades.php`:

1. `use App\Models\Mensagem;` — inserir em ordem alfabética (entre `use App\Models\Evento;` e `use App\Models\Palestra;`, p/ o `ordered_imports` do Pint).
2. `RECURSOS` — anexar ao **final** (I6):

```php
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante', 'autor_espiritual', 'mensagem'];
```

3. `RECURSOS_ROTULOS` — acrescentar (após `'autor_espiritual' => 'Autor Espiritual',`):

```php
        'mensagem' => 'Mensagem',
```

4. `RECURSOS_MODELS` — acrescentar (após `'autor_espiritual' => AutorEspiritual::class,`):

```php
        'mensagem' => Mensagem::class,
```

5. Atualizar o docblock de `permissions()` de "os 24 nomes" para "os 28 nomes".

- [ ] **Passo 5: Adicionar a semente do tipo**

Em `database/seeders/TiposConteudoSeeder.php`, dentro de `SEMENTE`, acrescentar (após a linha do `autor_espiritual`):

```php
        'mensagem' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DEPAE']],
```

- [ ] **Passo 6: Rodar os testes tocados + os vizinhos data-driven**

Run: `docker compose exec -T app php artisan test --filter="CapacidadesSeederTest|TiposConteudoSeederTest|GlossarioCapacidadesMapaTest|MatrizCapacidadesTest"`
Esperado: PASS. (Se algum rodar código stale, `docker compose restart app worker` e repita.)

- [ ] **Passo 7: Semear o dev (idempotente, insert-only)**

```bash
docker compose exec -T app php artisan db:seed --class=CapacidadesSeeder
docker compose exec -T app php artisan db:seed --class=TiposConteudoSeeder
```
Esperado: sem erro. Cria `mensagem.*` (4 permissions) e o tipo `mensagem` (DoTipo, DEPAE) se ainda não existirem; **não** toca config já feita na tela.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/TiposConteudoSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php tests/Feature/Autorizacao/TiposConteudoSeederTest.php
git add app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/TiposConteudoSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php tests/Feature/Autorizacao/TiposConteudoSeederTest.php
git commit -m "feat(camada-4-fatia-2a): recurso mensagem no glossário + semente DoTipo DEPAE"
```

---

### Task 3: Policy de capacidade (DoTipo, inerte)

**Files:**
- Create: `app/Policies/MensagemPolicy.php`
- Test: `tests/Feature/Autorizacao/MensagemPolicyCapacidadeTest.php`

**Interfaces:**
- Consumes: `App\Policies\Concerns\AutorizaPorDepartamento` (trait), `App\Models\{Mensagem,User}`.
- Produces: `MensagemPolicy` com `recurso() => 'mensagem'` e abilities pt-BR `ver/criar/editar/excluir`. Auto-descoberta (convenção `Models\X → Policies\XPolicy` — sem registro manual).

**Contexto:** clone da [AutorEspiritualPolicy](../../../app/Policies/AutorEspiritualPolicy.php) trocando só `recurso()` e os tipos. Regime **DoTipo**: `noEscopo` cai em `usuarioHabilitadoNoTipo` — responsável pelo **tipo**; o **objeto NÃO é consultado**. O admin passa antes no [Gate::before](../../../app/Providers/AppServiceProvider.php#L68). Responsável = **DEPAE** (semente); `DECOM` é disjunto. **`mensagem.publicar`/`definir-nivel` NÃO existem** — são Fatia 4. A Policy nasce **inerte** (só admin edita via `/admin`; sem edição pelo site nesta fatia).

- [ ] **Passo 1: Escrever o teste de Policy que falha**

`tests/Feature/Autorizacao/MensagemPolicyCapacidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Autorizacao;

use App\Models\Mensagem;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MensagemPolicyCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
        (new EstruturaCemaSeeder)->run();   // DEPAE/DECOM antes da semente da config
        $this->seed(TiposConteudoSeeder::class);
    }

    private function depto(string $sigla): Departamento
    {
        return Departamento::where('sigla', $sigla)->firstOrFail();
    }

    private function usuario(array $permissoes = [], array $departamentoIds = []): User
    {
        $u = User::factory()->create();
        foreach ($permissoes as $p) {
            $u->givePermissionTo($p);
        }
        $u->departamentos()->sync($departamentoIds);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('administrador');

        return $u;
    }

    public function test_responsavel_edita_mesma_mensagem_sem_departamento(): void
    {
        // DoTipo: o objeto NÃO é consultado — mensagem sem depto é editável pelo responsável do TIPO.
        $u = $this->usuario(['mensagem.editar'], [$this->depto('DEPAE')->id]);
        $mensagem = Mensagem::factory()->create();

        $this->assertTrue(Gate::forUser($u)->check('editar', $mensagem));
    }

    public function test_depto_disjunto_nega(): void
    {
        $u = $this->usuario(['mensagem.editar'], [$this->depto('DECOM')->id]);   // DECOM não responde por mensagem
        $mensagem = Mensagem::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $mensagem));
    }

    public function test_sem_permissao_nega(): void
    {
        $u = $this->usuario([], [$this->depto('DEPAE')->id]);
        $mensagem = Mensagem::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $mensagem));
    }

    public function test_sem_departamento_nega(): void
    {
        $u = $this->usuario(['mensagem.editar'], []);
        $mensagem = Mensagem::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $mensagem));
    }

    public function test_recurso_sem_linha_nega_ate_responsavel(): void
    {
        TipoConteudo::where('recurso', 'mensagem')->delete();   // fail-closed
        $u = $this->usuario(['mensagem.editar'], [$this->depto('DEPAE')->id]);
        $mensagem = Mensagem::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $mensagem));
    }

    public function test_criar_invocado_com_a_classe(): void
    {
        $comDepto = $this->usuario(['mensagem.criar'], [$this->depto('DEPAE')->id]);
        $semDepto = $this->usuario(['mensagem.criar'], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', Mensagem::class));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', Mensagem::class));
    }

    public function test_admin_passa_em_todas_as_acoes(): void
    {
        $admin = $this->admin();
        $mensagem = Mensagem::factory()->create();

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $mensagem), $acao);
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', Mensagem::class));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemPolicyCapacidadeTest`
Esperado: FAIL — sem a Policy, os casos `assertTrue` de responsável falham (não há Policy que conceda; o admin passa no `Gate::before`, mas o não-admin não).

- [ ] **Passo 3: Criar a Policy**

`app/Policies/MensagemPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Policies;

use App\Models\Mensagem;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Mensagem: permissão mensagem.* (hasPermissionTo, NUNCA can())
 * + escopo por regime (trait). Regime DoTipo (semente DEPAE): o responsável é quem está num depto
 * responsável pelo TIPO; o objeto NÃO é consultado. O admin passa antes no Gate::before.
 * Nasce INERTE (só admin edita via /admin nesta fatia). O eixo de autoria do médium
 * (mensagem.publicar / definir-nivel) é outro eixo — Fatia 4.
 */
class MensagemPolicy
{
    use AutorizaPorDepartamento;

    protected function recurso(): string
    {
        return 'mensagem';
    }

    public function ver(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.ver') && $this->noEscopo($user, $mensagem);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('mensagem.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.editar') && $this->noEscopo($user, $mensagem);
    }

    public function excluir(User $user, Mensagem $mensagem): bool
    {
        return $user->hasPermissionTo('mensagem.excluir') && $this->noEscopo($user, $mensagem);
    }
}
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemPolicyCapacidadeTest`
Esperado: PASS.

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Policies/MensagemPolicy.php tests/Feature/Autorizacao/MensagemPolicyCapacidadeTest.php
git add app/Policies/MensagemPolicy.php tests/Feature/Autorizacao/MensagemPolicyCapacidadeTest.php
git commit -m "feat(camada-4-fatia-2a): MensagemPolicy (DoTipo inerte, clone da AutorEspiritualPolicy)"
```

---

### Task 4: Resource `/admin` + Pages + sincronização simétrica de relacionadas

**Files:**
- Create: `app/Filament/Resources/Mensagens/MensagemResource.php`
- Create: `app/Filament/Resources/Mensagens/Pages/SincronizaRelacionadas.php` (trait)
- Create: `app/Filament/Resources/Mensagens/Pages/CreateMensagem.php`
- Create: `app/Filament/Resources/Mensagens/Pages/EditMensagem.php`
- Create: `app/Filament/Resources/Mensagens/Pages/ListMensagens.php`
- Test: `tests/Feature/Filament/MensagemResourceTest.php`

**Interfaces:**
- Consumes: `App\Filament\Support\ComponentesImagem`, `App\Enums\FormatoMensagem`, `App\Models\Mensagem` (incl. `sincronizarRelacionadas`, Task 1).
- Produces: Resource auto-descoberto (rota `/admin/mensagens` via `$slug`); form com `autores` (Select N:N via `->relationship`) e `relacionadas` (Select **fora** do auto-sync, sincronizado nos hooks das Pages).

**Contexto:** clone da [AutorEspiritualResource](../../../app/Filament/Resources/AutoresEspirituais/AutorEspiritualResource.php). `autores` usa `->relationship('autores','nome')->multiple()` (auto-sync N:N padrão). `relacionadas` é **auto-referente simétrico** — o `->relationship` do Filament só gravaria um sentido; por isso é um `Select` de **opções** (fora do auto-sync) capturado nos hooks e aplicado por `Mensagem::sincronizarRelacionadas` (Task 1), **exatamente** como a [PalestraResource sincroniza pessoas](../../../app/Filament/Resources/Palestras/Pages/SincronizaPessoas.php) (trait `capturar`/`sincronizar` + `mutateFormDataBeforeFill`/`afterCreate`/`afterSave`). `casa` **não** é exposta (constante). Auto-discovery via [AdminPanelProvider:85](../../../app/Providers/Filament/AdminPanelProvider.php#L85).

- [ ] **Passo 1: Escrever o teste do Resource que falha**

`tests/Feature/Filament/MensagemResourceTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Models\Mensagem;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_lista_renderiza(): void
    {
        Livewire::test(ListMensagens::class)->assertSuccessful();
    }

    public function test_mensagem_aparece_na_tabela(): void
    {
        Mensagem::factory()->create(['titulo' => 'Instruções para o atendimento']);

        Livewire::test(ListMensagens::class)->assertSee('Instruções para o atendimento');
    }

    public function test_form_titulo_obrigatorio(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('titulo', fn (TextInput $f) => $f->isRequired());
    }

    public function test_form_tem_rich_editor_corpo(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('corpo', fn (RichEditor $f) => true);
    }

    public function test_form_tem_textarea_contexto(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('contexto', fn (Textarea $f) => true);
    }

    public function test_form_usa_media_library_para_pictografia(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('pictografia', fn (SpatieMediaLibraryFileUpload $c): bool => $c->getCollection() === Mensagem::COLECAO_PICTOGRAFIA);
    }

    public function test_form_tem_select_nivel_com_publico_e_aceita_null(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => array_key_exists('publico', $f->getOptions()) && ! $f->isRequired());
    }

    public function test_form_tem_selects_de_relacao(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('autores', fn (Select $f) => $f->isMultiple())
            ->assertFormFieldExists('relacionadas', fn (Select $f) => $f->isMultiple());
    }

    public function test_form_nao_tem_campos_podados(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldDoesNotExist('origem_da_mensagem')
            ->assertFormFieldDoesNotExist('grupo_mediunico')
            ->assertFormFieldDoesNotExist('casa_espirita');
    }

    public function test_cria_mensagem_com_corpo_sanitizado(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Paz e amor',
                'slug' => 'paz-e-amor',
                'corpo' => '<p>Sede bons.</p><script>alert(1)</script>',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'paz-e-amor')->first();
        $this->assertNotNull($m);
        $this->assertStringNotContainsString('<script', (string) $m->corpo);
    }

    public function test_edita_mensagem(): void
    {
        $m = Mensagem::factory()->create(['titulo' => 'Título Antigo', 'slug' => 'titulo-antigo']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Título Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mensagens', ['slug' => 'titulo-antigo', 'titulo' => 'Título Novo']);
    }

    public function test_criar_com_relacionadas_espelha_nos_dois_lados(): void
    {
        $b = Mensagem::factory()->create(['titulo' => 'Mensagem B']);

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Mensagem A',
                'slug' => 'mensagem-a',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
                'relacionadas' => [$b->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Mensagem::where('slug', 'mensagem-a')->first();
        $this->assertTrue($a->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $a->id), 'a relação não espelhou no lado B');
    }

    public function test_rota_de_listagem_responde_ok(): void
    {
        Mensagem::factory()->count(3)->create();

        $this->get('/admin/mensagens')->assertOk();
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemResourceTest`
Esperado: FAIL — classes de Page/Resource inexistentes.

- [ ] **Passo 3: Criar o trait de sincronização das relacionadas**

`app/Filament/Resources/Mensagens/Pages/SincronizaRelacionadas.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Models\Mensagem;

/**
 * Extrai o campo `relacionadas` (fora de coluna) do form antes do save e aplica a sincronização
 * SIMÉTRICA (dual-espelhada) no after-hook. Fora do auto-sync do Filament de propósito: o
 * ->relationship() gravaria só um sentido. Mesmo molde de SincronizaPessoas (Palestra).
 */
trait SincronizaRelacionadas
{
    /** @var array<int, int|string> */
    protected array $idsRelacionadas = [];

    protected function capturarRelacionadas(array $data): array
    {
        $this->idsRelacionadas = $data['relacionadas'] ?? [];
        unset($data['relacionadas']);

        return $data;
    }

    protected function aplicarRelacionadas(Mensagem $mensagem): void
    {
        $mensagem->sincronizarRelacionadas($this->idsRelacionadas);
    }
}
```

- [ ] **Passo 4: Criar o Resource**

`app/Filament/Resources/Mensagens/MensagemResource.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens;

use App\Enums\FormatoMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Filament\Support\ComponentesImagem;
use App\Models\Mensagem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MensagemResource extends Resource
{
    protected static ?string $model = Mensagem::class;

    // Sem $slug o Laravel geraria 'mensagems' (pluralizador inglês) — travamos a rota pt-BR.
    protected static ?string $slug = 'mensagens';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Mensagens Mediúnicas';

    protected static ?string $modelLabel = 'Mensagem';

    protected static ?string $pluralModelLabel = 'Mensagens';

    protected static ?string $recordTitleAttribute = 'titulo';

    /** Níveis de acesso BRUTOS (slugs da taxonomia legada). A semântica rica é da Fatia 3. */
    public const NIVEIS = [
        'publico' => 'Público',
        'trabalhadores' => 'Trabalhadores',
        'mediuns-trabalhadores' => 'Médiuns',
        'direcionada' => 'Direcionada',
        'diretores' => 'Diretores',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conteúdo')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->columnSpan(2),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(2),

                        Textarea::make('contexto')
                            ->label('Contexto (faixa editorial — manual)')
                            ->helperText('Texto curto de contexto exibido acima da mensagem. Opcional.')
                            ->rows(3)
                            ->columnSpan(2),

                        RichEditor::make('corpo')
                            ->label('Corpo da mensagem')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike', 'link',
                                'bulletList', 'orderedList', 'blockquote', 'h2', 'h3',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Classificação e download')
                    ->columns(2)
                    ->schema([
                        Select::make('formato')
                            ->label('Formato')
                            ->options(FormatoMensagem::opcoes())
                            ->required(),

                        DatePicker::make('data_recebimento')
                            ->label('Data de recebimento')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('nivel')
                            ->label('Nível de acesso')
                            ->options(self::NIVEIS)
                            ->helperText('Só as Públicas aparecem no site (por ora). A visibilidade rica virá na próxima fase.'),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                Mensagem::STATUS_PUBLICADO => 'Publicada',
                                Mensagem::STATUS_PENDENTE => 'Pendente',
                                Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                            ])
                            ->default(Mensagem::STATUS_PUBLICADO)
                            ->required(),

                        Toggle::make('liberar_download')
                            ->label('Liberar download do arquivo'),

                        TextInput::make('link_arquivo')
                            ->label('Link do arquivo (Google Drive)')
                            ->url()
                            ->maxLength(500)
                            ->columnSpan(2),
                    ]),

                Section::make('Autoria e relações')
                    ->columns(2)
                    ->schema([
                        Select::make('autores')
                            ->label('Autores espirituais')
                            ->relationship('autores', 'nome')
                            ->multiple()
                            ->preload()
                            ->searchable(),

                        Select::make('relacionadas')
                            ->label('Mensagens relacionadas')
                            ->multiple()
                            ->searchable()
                            ->options(fn (?Mensagem $record) => Mensagem::query()
                                ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                ->orderBy('titulo')
                                ->pluck('titulo', 'id'))
                            ->helperText('Relação simétrica: ao relacionar A→B, B também passa a listar A.'),
                    ]),

                Section::make('Pictografia')
                    ->schema([
                        ComponentesImagem::upload('pictografia', Mensagem::COLECAO_PICTOGRAFIA, multiplas: true)
                            ->label('Imagens (pictografia)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('formato')
                    ->label('Formato')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof FormatoMensagem ? $state->rotulo() : (string) $state),

                TextColumn::make('data_recebimento')
                    ->label('Recebida em')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('nivel')
                    ->label('Nível')
                    ->formatStateUsing(fn (?string $state): string => self::NIVEIS[$state] ?? '—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Mensagem::STATUS_PUBLICADO => 'success',
                        Mensagem::STATUS_PENDENTE => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('liberar_download')
                    ->label('Download')
                    ->boolean()
                    ->toggleable(),

                SpatieMediaLibraryImageColumn::make('pictografia')
                    ->label('Pictografia')
                    ->collection(Mensagem::COLECAO_PICTOGRAFIA)
                    ->conversion('thumb')
                    ->toggleable(),
            ])
            ->defaultSort('data_recebimento', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    Mensagem::STATUS_PUBLICADO => 'Publicada',
                    Mensagem::STATUS_PENDENTE => 'Pendente',
                    Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                ]),
                SelectFilter::make('formato')->options(FormatoMensagem::opcoes()),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionadas'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMensagens::route('/'),
            'create' => CreateMensagem::route('/create'),
            'edit' => EditMensagem::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Passo 5: Criar as 3 Pages**

`app/Filament/Resources/Mensagens/Pages/CreateMensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMensagem extends CreateRecord
{
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->capturarRelacionadas($data);
    }

    protected function afterCreate(): void
    {
        $this->aplicarRelacionadas($this->record);
    }
}
```

`app/Filament/Resources/Mensagens/Pages/EditMensagem.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagem extends EditRecord
{
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['relacionadas'] = $this->record->relacionadas()->pluck('mensagens.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturarRelacionadas($data);
    }

    protected function afterSave(): void
    {
        $this->aplicarRelacionadas($this->record);
    }
}
```

`app/Filament/Resources/Mensagens/Pages/ListMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMensagens extends ListRecords
{
    protected static string $resource = MensagemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemResourceTest`
Esperado: PASS (incl. `test_criar_com_relacionadas_espelha_nos_dois_lados` — a prova do wiring simétrico). Se `assertFormFieldDoesNotExist` não existir na versão do Filament, remover `test_form_nao_tem_campos_podados` — a ausência das colunas já é provada no `MensagemTest` (Task 1).

- [ ] **Passo 7: Conferir no navegador (real)**

`docker compose restart app worker` e abrir `http://localhost/admin/mensagens` (logado como admin): a lista renderiza; "Nova" abre o form com Conteúdo/Classificação/Autoria/Pictografia, **sem** campos podados; o Select de relacionadas não lista a própria mensagem na edição.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Resources/Mensagens tests/Feature/Filament/MensagemResourceTest.php
git add app/Filament/Resources/Mensagens tests/Feature/Filament/MensagemResourceTest.php
git commit -m "feat(camada-4-fatia-2a): MensagemResource + Pages (/admin) com relacionadas simétrica"
```

---

### Task 5: Cadeia de importação — Leitor + Importador + Command + bind

**Files:**
- Create: `app/Importacao/LeitorMensagens.php` (interface)
- Create: `app/Importacao/LeitorMensagensMysql.php`
- Create: `app/Importacao/ImportadorMensagens.php`
- Create: `app/Console/Commands/ImportarMensagens.php`
- Modify: `app/Providers/AppServiceProvider.php` (1 bind + 2 `use`)
- Test: `tests/Feature/Importacao/ImportadorMensagensTest.php`
- Test: `tests/Feature/Importacao/ImportarMensagensCommandTest.php`

**Interfaces:**
- Consumes: `App\Importacao\{BaixadorImagem,TransformadorLegado}`, `App\Models\{Mensagem,AutorEspiritual}`, conexão `legado`. (O `link_arquivo` é normalizado pelo **mutator do model** — R-A —, não pelo importador.)
- Produces:
  - `LeitorMensagens::mensagens(): array` — cada item `['wp_id','titulo','slug','corpo','formato','data_recebimento','nivel','autores_slugs','fotos_urls','link_arquivo','liberar_download','status']`.
  - `ImportadorMensagens::__construct(LeitorMensagens $leitor, BaixadorImagem $baixador)` + `importar(?callable $log=null): array` → `['mensagens'=>int, 'avisos'=>list<string>, 'contadores'=>['com_autor','sem_autor','com_pictografia','com_download','publish_sem_nivel','falha_foto']]`.
  - Command `cema:importar-mensagens`.
  - Bind `LeitorMensagens → LeitorMensagensMysql` no `AppServiceProvider` (I16).

**Contexto:** clone da cadeia dos Autores/Eventos. **Chave = `wp_id`** (a Mensagem tem). **Regra de escrita (honra I13 + a ciência dos 49 sem-nível):**
- **Conteúdo do legado — sempre atualizado** no re-import: `titulo`, `corpo`, `formato`, `data_recebimento`, `link_arquivo` (normalizado pelo mutator do model — R-A), `liberar_download` (via `statusParaAtivo`), `autores` (sync por slug), `pictografia` (com O1).
- **Curadoria — só no CREATE, preservada no re-import:** `slug` (o admin pode renomear), `status`, `nivel` (o admin classifica os 49 sem-termo; um re-import **não** os zera).
- **Nunca setados:** `casa` (default `'CEMA'`), `contexto` (manual), `relacionadas` (nascem vazias).
Por isso o importador usa **`firstOrNew`** (não `updateOrCreate`) — precisa distinguir create de re-import. **Slug determinístico** para os 39 pending sem `post_name` (`Str::slug(titulo).'-'.wp_id`, com guarda de colisão). **Autor por slug** (child `post_name` → `AutorEspiritual::firstWhere('slug')`), não resolvido vira aviso. **Pictografia multi + O1** (`clearMediaCollection` só após ≥1 download OK). **Sem** `departamentos()->sync()` (DoTipo). **Bind obrigatório** (I16). Medição do legado (§4 da SPEC): 179 (132 publish + 47 pending), 8 downloads, 2 pictografias, 96 com autor, 49 sem nível.

- [ ] **Passo 1: Escrever o teste do Importador que falha**

`tests/Feature/Importacao/ImportadorMensagensTest.php`:

```php
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
            'data_recebimento' => '1722902400',   // 2024-08-05 (unix ts, meia-noite)
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
        $this->assertSame('2024-08-05', $m->data_recebimento->format('Y-m-d'));
        $this->assertSame('publico', $m->nivel);
        $this->assertSame('publicado', $m->status);
        $this->assertSame('CEMA', $m->casa);       // constante — poda de casa_espirita
        $this->assertNull($m->contexto);            // manual — não migra
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
        $this->assertSame(2, $m->getMedia(Mensagem::COLECAO_PICTOGRAFIA)->count());
    }

    public function test_reimport_sem_pictografia_preserva_upload_do_admin(): void
    {
        // O1: mensagem sem _fotos_mensagem no legado NÃO tem a pictografia limpa num re-import.
        $this->importar([$this->mensagemLegado(['fotos_urls' => []])]);
        $m = Mensagem::firstWhere('wp_id', 21694);

        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('manual.png')->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
        $this->assertTrue($m->fresh()->hasMedia(Mensagem::COLECAO_PICTOGRAFIA));

        $this->importar([$this->mensagemLegado(['fotos_urls' => []])]);   // re-import sem fotos

        $this->assertTrue($m->fresh()->hasMedia(Mensagem::COLECAO_PICTOGRAFIA), 'a pictografia do /admin foi apagada (clobber O1)');
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
        $this->assertSame(1, Mensagem::firstWhere('wp_id', 21694)->getMedia(Mensagem::COLECAO_PICTOGRAFIA)->count());
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
        // Curadoria = slug/status/nivel (create-only) + contexto (nunca) + relacionadas (nunca).
        $this->importar([$this->mensagemLegado(['nivel' => null])]);
        $m = Mensagem::firstWhere('wp_id', 21694);
        $outra = Mensagem::factory()->create();

        $m->update(['nivel' => 'publico', 'status' => 'despublicada', 'contexto' => 'nota do admin']);
        $m->sincronizarRelacionadas([$outra->id]);

        $this->importar([$this->mensagemLegado(['nivel' => null])]);   // re-import (legado sem termo)

        $m->refresh();
        $this->assertSame('publico', $m->nivel, 'nível classificado pelo admin foi zerado');
        $this->assertSame('despublicada', $m->status, 'status do admin foi sobrescrito');
        $this->assertSame('nota do admin', $m->contexto, 'contexto foi tocado pelo import');
        $this->assertTrue($m->relacionadas->contains('id', $outra->id), 'relacionadas foi tocada pelo import');
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorMensagensTest`
Esperado: FAIL — `interface App\Importacao\LeitorMensagens not found`.

- [ ] **Passo 3: Criar a interface do Leitor**

`app/Importacao/LeitorMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

interface LeitorMensagens
{
    /**
     * Mensagens mediúnicas lidas do legado, normalizadas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function mensagens(): array;
}
```

- [ ] **Passo 4: Criar o Importador**

`app/Importacao/ImportadorMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportadorMensagens
{
    private array $avisos = [];

    private array $contadores = [];

    public function __construct(
        private LeitorMensagens $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];
        $this->contadores = [
            'com_autor' => 0, 'sem_autor' => 0, 'com_pictografia' => 0,
            'com_download' => 0, 'publish_sem_nivel' => 0, 'falha_foto' => 0,
        ];

        $processadas = 0;

        foreach ($this->leitor->mensagens() as $d) {
            DB::transaction(function () use ($d, $log) {
                $mensagem = Mensagem::firstOrNew(['wp_id' => $d['wp_id']]);
                $novo = ! $mensagem->exists;

                // Conteúdo do legado — SEMPRE atualizado (inclusive no re-import).
                $mensagem->fill([
                    'titulo' => $d['titulo'],
                    'corpo' => $d['corpo'] ?? null,
                    'formato' => $d['formato'] ?? null,
                    'data_recebimento' => TransformadorLegado::unixParaData($d['data_recebimento']),
                    'link_arquivo' => $d['link_arquivo'] ?? null,   // normalizado pelo mutator do model (LinkDrive) — R-A
                    'liberar_download' => TransformadorLegado::statusParaAtivo($d['liberar_download'] ?? null),
                ]);

                // Curadoria — SÓ no create; preservada no re-import (I13). O admin renomeia slug,
                // muda status e classifica os sem-nível pela tela; um re-import não desfaz isso.
                // casa (default 'CEMA') e contexto (manual) NUNCA são setados pelo import.
                if ($novo) {
                    $mensagem->slug = $this->slugUnico($d);
                    $mensagem->status = $d['status'];
                    $mensagem->nivel = $d['nivel'];   // pode ser null — o admin classifica depois
                }

                $mensagem->save();

                // Autores por SLUG (child post_name -> AutorEspiritual.slug). Não resolvido = aviso.
                $ids = [];
                foreach ($d['autores_slugs'] ?? [] as $slugAutor) {
                    $autor = AutorEspiritual::firstWhere('slug', $slugAutor);
                    if ($autor) {
                        $ids[] = $autor->id;
                    } else {
                        $this->avisos[] = "[{$mensagem->slug}] autor não encontrado por slug: {$slugAutor}";
                    }
                }
                $mensagem->autores()->sync($ids);
                $ids ? $this->contadores['com_autor']++ : $this->contadores['sem_autor']++;

                // Pictografia MULTI + O1: baixa todas; só limpa a coleção se ≥1 download deu certo
                // (mensagem sem foto no legado preserva o upload posto no /admin).
                $urls = $d['fotos_urls'] ?? [];
                if (! empty($urls)) {
                    $baixadas = [];
                    foreach ($urls as $url) {
                        $bytes = $this->baixador->baixarCapado($url, 2000);
                        if ($bytes !== null) {
                            $baixadas[] = ['bytes' => $bytes, 'url' => $url];
                        } else {
                            $this->avisos[] = "[{$mensagem->slug}] falha ao baixar pictografia: {$url}";
                            $this->contadores['falha_foto']++;
                        }
                    }
                    if (! empty($baixadas)) {
                        $mensagem->clearMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
                        foreach ($baixadas as $img) {
                            $mensagem->addMediaFromString($img['bytes'])
                                ->usingFileName(basename(parse_url($img['url'], PHP_URL_PATH) ?? 'pictografia.jpg'))
                                ->withCustomProperties(['url_legado' => $img['url']])
                                ->toMediaCollection(Mensagem::COLECAO_PICTOGRAFIA);
                        }
                        $this->contadores['com_pictografia']++;
                    }
                }

                if (! empty($d['link_arquivo']) && TransformadorLegado::statusParaAtivo($d['liberar_download'] ?? null)) {
                    $this->contadores['com_download']++;
                }

                if (($d['status'] ?? null) === Mensagem::STATUS_PUBLICADO && empty($d['nivel'])) {
                    $this->contadores['publish_sem_nivel']++;
                }

                $log("Mensagem importada: {$mensagem->slug}");
            });

            $processadas++;
        }

        return ['mensagens' => $processadas, 'avisos' => $this->avisos, 'contadores' => $this->contadores];
    }

    /** Slug determinístico e único. 39 pending vêm sem post_name: base no título + sufixo wp_id. */
    private function slugUnico(array $d): string
    {
        $base = trim((string) ($d['slug'] ?? ''));
        if ($base !== '') {
            return $base;   // publish/pending com post_name (medido único, 0 dups)
        }

        $slug = Str::slug($d['titulo']).'-'.$d['wp_id'];
        $sufixo = 2;
        while (Mensagem::where('slug', $slug)->exists()) {   // guarda defensiva contra colisão residual
            $slug = Str::slug($d['titulo']).'-'.$d['wp_id'].'-'.$sufixo++;
        }

        return $slug;
    }
}
```

- [ ] **Passo 5: Rodar o teste do Importador e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorMensagensTest`
Esperado: PASS (inclui O1, slug determinístico, autor por slug, download, idempotência e preservação da curadoria).

- [ ] **Passo 6: Escrever o teste do Command (+ guardas do bind) que falha**

`tests/Feature/Importacao/ImportarMensagensCommandTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorMensagens;
use App\Importacao\LeitorMensagens;
use App\Importacao\LeitorMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarMensagensCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        Storage::fake('public');

        // fake do leitor no container (sem tocar o legado; sem imagens p/ determinismo)
        $this->app->bind(LeitorMensagens::class, fn () => new class implements LeitorMensagens
        {
            public function mensagens(): array
            {
                return [[
                    'wp_id' => 1, 'titulo' => 'Paz', 'slug' => 'paz', 'corpo' => '<p>Paz.</p>',
                    'formato' => 'psicografia', 'data_recebimento' => '1722902400', 'nivel' => 'publico',
                    'autores_slugs' => [], 'fotos_urls' => [], 'link_arquivo' => null,
                    'liberar_download' => 'false', 'status' => 'publicado',
                ]];
            }
        });

        $this->artisan('cema:importar-mensagens')->assertSuccessful();

        $this->assertSame(1, Mensagem::count());
        $this->assertSame('Paz', Mensagem::firstWhere('wp_id', 1)->titulo);
    }

    /** Guarda I16: sem bind manual, resolver a INTERFACE devolve o ...Mysql (bind do AppServiceProvider). */
    public function test_interface_do_leitor_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(
            LeitorMensagensMysql::class,
            app(LeitorMensagens::class),
        );
    }

    /** Guarda I16: o Importador resolve pelo container (constrói a cadeia real) sem bind manual. */
    public function test_importador_resolve_pelo_container(): void
    {
        $this->assertInstanceOf(
            ImportadorMensagens::class,
            app(ImportadorMensagens::class),
        );
    }
}
```

- [ ] **Passo 7: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportarMensagensCommandTest`
Esperado: FAIL — `command "cema:importar-mensagens" is not defined` e/ou `Target [App\Importacao\LeitorMensagens] is not instantiable` (interface sem bind).

- [ ] **Passo 8: Criar o Leitor Mysql**

`app/Importacao/LeitorMensagensMysql.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorMensagensMysql implements LeitorMensagens
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function mensagens(): array
    {
        // publish + pending (exclui o auto-draft). O prefixo wp_ é literal aqui: o select() cru
        // do Laravel NÃO aplica o 'prefix' da conexão (só o query builder aplicaria).
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content, post_status
             FROM wp_posts
             WHERE post_type = 'mensagem-mediunicas' AND post_status IN ('publish', 'pending')"
        );

        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);

            $out[] = [
                'wp_id' => (int) $p->ID,
                'titulo' => $p->post_title,
                'slug' => $p->post_name,                                  // pode vir '' (39 pending)
                'corpo' => $p->post_content ?: null,
                'formato' => $meta['_formato'] ?? null,
                'data_recebimento' => $meta['data_recebimento'] ?? null,  // unix ts
                'nivel' => $this->nivelDe((int) $p->ID),
                'autores_slugs' => $this->autoresSlugsDe((int) $p->ID),
                'fotos_urls' => $this->fotosDe($meta['_fotos_mensagem'] ?? null),
                'link_arquivo' => $meta['link_do_arquivo_mensagem'] ?? null,
                'liberar_download' => $meta['liberar_download_mensagem'] ?? null,
                'status' => $p->post_status === 'publish' ? 'publicado' : 'pendente',
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (1º valor por chave) */
    private function metasDe(int $postId): array
    {
        $rows = $this->db->select('SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?', [$postId]);
        $m = [];
        foreach ($rows as $r) {
            if (! array_key_exists($r->meta_key, $m)) {
                $m[$r->meta_key] = $r->meta_value;
            }
        }

        return $m;
    }

    /** Slug do único termo da taxonomia nivel-de-acesso, ou null (49/179 sem termo). */
    private function nivelDe(int $postId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT t.slug
             FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'nivel-de-acesso'
             LIMIT 1",
            [$postId]
        );

        return $row->slug ?? null;
    }

    /**
     * Slugs (post_name) dos autores espirituais via wp_jet_rel_default, rel_id=37 (parent=mensagem).
     *
     * @return array<int, string>
     */
    private function autoresSlugsDe(int $postId): array
    {
        $rows = $this->db->select(
            "SELECT autor.post_name AS slug
             FROM wp_jet_rel_default r
             JOIN wp_posts autor ON autor.ID = r.child_object_id
             WHERE r.rel_id = '37' AND r.parent_object_id = ? AND autor.post_type = 'autores-espirituais'",
            [$postId]
        );

        return array_values(array_filter(array_map(fn ($r) => $r->slug ?: null, $rows)));
    }

    /**
     * URLs das imagens do repeater _fotos_mensagem (PHP serializado, pode ter várias).
     *
     * @return array<int, string>
     */
    private function fotosDe(?string $serializado): array
    {
        if (empty($serializado)) {
            return [];
        }

        set_error_handler(static fn () => true);
        try {
            $dados = unserialize($serializado, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }

        if (! is_array($dados)) {
            return [];
        }

        $urls = [];
        foreach ($dados as $item) {
            if (is_array($item) && ! empty($item['url'])) {
                $urls[] = (string) $item['url'];
            }
        }

        return $urls;
    }
}
```

- [ ] **Passo 9: Criar o Command**

`app/Console/Commands/ImportarMensagens.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Console\Commands;

use App\Importacao\ImportadorMensagens;
use App\Importacao\LeitorMensagens;
use App\Importacao\LeitorMensagensMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarMensagens extends Command
{
    protected $signature = 'cema:importar-mensagens';

    protected $description = 'Importa as mensagens mediúnicas (CPT mensagem-mediunicas) do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorMensagens $leitor, ImportadorMensagens $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorMensagensMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (\Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->info($m));

        $this->newLine();
        $this->info("Importação concluída: {$resumo['mensagens']} mensagens.");
        $c = $resumo['contadores'];
        $this->line("  Com autor: {$c['com_autor']} · Sem autor: {$c['sem_autor']} · Com pictografia: {$c['com_pictografia']} · Com download: {$c['com_download']} · Publish sem nível: {$c['publish_sem_nivel']} · Falha de foto: {$c['falha_foto']}");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Passo 10: Adicionar o bind no `AppServiceProvider` (I16)**

Em `app/Providers/AppServiceProvider.php`, acrescentar os 2 `use` (ordem alfabética junto dos leitores) e a linha de bind ao lado dos outros no `register()` (após o bind de `LeitorAutoresEspirituais`):

```php
use App\Importacao\LeitorMensagens;
use App\Importacao\LeitorMensagensMysql;
```

```php
        $this->app->bind(LeitorMensagens::class, LeitorMensagensMysql::class);
```

- [ ] **Passo 11: Rodar o teste do Command e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportarMensagensCommandTest`
Esperado: PASS (incl. as duas guardas do bind I16).

- [ ] **Passo 12: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Importacao/LeitorMensagens.php app/Importacao/LeitorMensagensMysql.php app/Importacao/ImportadorMensagens.php app/Console/Commands/ImportarMensagens.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorMensagensTest.php tests/Feature/Importacao/ImportarMensagensCommandTest.php
git add app/Importacao/LeitorMensagens.php app/Importacao/LeitorMensagensMysql.php app/Importacao/ImportadorMensagens.php app/Console/Commands/ImportarMensagens.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorMensagensTest.php tests/Feature/Importacao/ImportarMensagensCommandTest.php
git commit -m "feat(camada-4-fatia-2a): importação das mensagens (leitor+importador+command+bind)"
```

---

### Task 6: Verificação end-to-end e fechamento

**Files:** nenhum de código.

- [ ] **Passo 1: Rodar a suíte completa + Pint**

```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app php artisan test
```
Esperado: **Pint** limpo; suíte **verde** (915 + os novos testes das Tasks 1–5). Se 2 testes de cap de imagem do blog falharem sob carga, rodar isolados p/ confirmar que não é regressão desta fatia ([[flaky-importadorblog-gd-cap-imagem]]).

- [ ] **Passo 2: Revalidar o leitor real contra o legado vivo (R3 — antes de fechar)**

Com o túnel SSH ativo, importar as 179 de verdade no dev (idempotente). **Pré-requisito:** os 19 autores já importados (Fatia 1) — o casamento é por slug.

```bash
docker compose exec -T app php artisan cema:importar-mensagens
```
Esperado: `Importação concluída: 179 mensagens.` com `Com autor: 96 · Com pictografia: 2 · Com download: 8 · Publish sem nível: 2` (os 47 pending não têm nível ⇒ contam só os publish no `publish_sem_nivel`; conferir o número real). Conferir no `/admin/mensagens` que aparecem 179, com os formatos/níveis certos. Rodar **de novo** e confirmar que continua **179** (idempotência por `wp_id`, sem duplicar mídia). ⚠️ Se o túnel estiver caído, o command falha com a mensagem de diagnóstico — subir o túnel e repetir ([[verificar-leitor-legado-contra-banco-real]]).

- [ ] **Passo 3: Checar a psicofonia contra o perfil `conteudo` (ciência §12 da SPEC)**

Com o túnel ativo, confirmar que o `clean('conteudo')` (que roda no import, no mutator do `corpo`) não achata a estrutura Pergunta/Resposta de nenhuma psicofonia (o perfil remove `table`/`div`):

```bash
docker compose exec -T app php artisan tinker --execute='
$ids = collect(DB::connection("legado")->select("SELECT p.ID FROM wp_posts p JOIN wp_postmeta m ON m.post_id=p.ID AND m.meta_key=\"_formato\" AND m.meta_value=\"psicofonia\" WHERE p.post_type=\"mensagem-mediunicas\" AND p.post_status=\"publish\""))->pluck("ID");
$suspeitas = 0;
foreach ($ids as $id) {
    $raw = DB::connection("legado")->select("SELECT post_content c FROM wp_posts WHERE ID=?", [$id])[0]->c;
    if (preg_match("/<(table|div)\b/i", $raw)) { $suspeitas++; echo "psicofonia com table/div: post $id".PHP_EOL; }
}
echo "psicofonias com layout em table/div: $suspeitas".PHP_EOL;
'
```
Esperado: `0`. Se `> 0`, decidir **antes** do import definitivo: extender o perfil `conteudo` (permitir `table`/`div` seguros) ou criar um perfil `mensagem` dedicado — e re-rodar o import.

- [ ] **Passo 4: Commitar os docs e abrir o PR**

```bash
git add docs/superpowers/specs/2026-07-18-camada-4-fatia-2a-mensagem-migracao.md docs/superpowers/plans/2026-07-18-camada-4-fatia-2a-mensagem-migracao.md
git commit -m "docs(camada-4-fatia-2a): SPEC + plano + passes das mensagens mediúnicas"
git push -u origin camada-4-fatia-2a-mensagem
gh pr create --base main --title "Camada 4 · Fatia 2A — Mensagem + migração" --body "Entidade Mensagem (model/enum/4 migrations, DoTipo inerte), CRUD /admin e importação das 179 do CPT mensagem-mediunicas (autor por slug, pictografia multi, download Drive, relacionadas simétrica). Front público = Fatia 2B. SPEC/plano em docs/superpowers. Cutover de prod no §8 da SPEC."
```

- [ ] **Passo 5: Mesclar só com o CI verde no ÚLTIMO commit** ([[merge-so-com-ci-verde-no-commit-final]])

Aguardar o CI fechar verde no último commit antes do merge — não mesclar com check pending.

---

## Cutover de produção (do dono, no deploy — não é tarefa de código)

Sequência idempotente (§8 da SPEC): `php artisan migrate` → `db:seed --class=CapacidadesSeeder` → `db:seed --class=TiposConteudoSeeder` → `cema:importar-autores-espirituais` (garantir os 19) → `cema:importar-mensagens` (túnel ativo — as 179). A capacidade nasce **inerte** — ligar `mensagem.*` para DEPAE na tela `/admin/matriz-capacidades` é cutover das Fatias 4/5, quando houver edição pelo site.

## Self-Review (feita ao escrever o plano)

- **Cobertura da SPEC:** enum/model/4 migrations/factory (Task 1) · glossário+semente (Task 2) + bind (Task 5) · Policy DoTipo inerte (Task 3) · Resource+Pages+relacionadas simétrica (Task 4) · cadeia de importação (Task 5) · invariantes I1–I17 mapeados a testes concretos · cutover (§8) documentado. Sem lacuna.
- **Sem placeholders:** todo passo de código traz o código completo; todo teste traz asserções reais.
- **Consistência de tipos/nomes:** `mensagens` (tabela); pivôs `departamento_mensagem`/`mensagem_autor_espiritual`/`mensagem_relacionada` (unique explícito); `COLECAO_PICTOGRAFIA='pictografia'`; `STATUS_PUBLICADO/PENDENTE/DESPUBLICADA`; `NIVEL_PUBLICO='publico'`; `Mensagem::sincronizarRelacionadas(array)`; trait `SincronizaRelacionadas` (`capturarRelacionadas`/`aplicarRelacionadas`); `LeitorMensagens::mensagens()` → `['wp_id','titulo','slug','corpo','formato','data_recebimento','nivel','autores_slugs','fotos_urls','link_arquivo','liberar_download','status']`; `ImportadorMensagens(LeitorMensagens, BaixadorImagem)`; `cema:importar-mensagens` — usados de forma idêntica entre tasks.
- **Refinamento vs. SPEC (create-only):** `status`/`nivel`/`slug` são setados só no CREATE (o §6.6 da SPEC mostrava `updateOrCreate`; o plano usa `firstOrNew` para honrar o I13 e a ciência §12 — a classificação dos 49 sem-nível pelo admin sobrevive a um re-import). SPEC §6.6/I13 reconciliados.

