# Agenda Reforma Íntima — Plano de Implementação

> **Para workers agênticos:** SUB-SKILL OBRIGATÓRIA — use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para implementar task a task. Os passos usam checkbox (`- [ ]`) para rastreio.

**Goal:** Construir o módulo **Agenda Reforma Íntima** (devocional diário) ponta a ponta — dados → importação read-only do legado → admin Filament → front público SSR crawlável com SEO de fundação.

**Architecture:** Duas tabelas de conteúdo (`agenda_metas_mes` = título fixo do mês; `agenda_dias` = campos diários) + `agenda_slugs_legado` (mapa de 301). Front **Controller + Blade SSR** (cada data é uma URL real `/agenda-reforma-intima/AAAA-MM-DD`; navegação em `<a href wire:navigate>` crawlável; conteúdo do dia SSR), com o "hoje" ancorado no **fuso do visitante** (URL nua = hoje de Brasília no servidor; script navega para a URL datada local). Importação idempotente espelhando o pipeline de Palestras.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 + Blade (SSR) · Tailwind v4 · MySQL 8 · Docker (app no container `cema-app`).

**Spec de referência:** [`docs/superpowers/specs/2026-07-02-agenda-reforma-intima-design.md`](../specs/2026-07-02-agenda-reforma-intima-design.md).

## Global Constraints

(Toda task herda implicitamente esta seção.)

- **Idioma:** pt-BR em tudo — código (identificadores de domínio), comentários, UI, mensagens, commits.
- **Branch:** trabalhar em `fase-agenda-reforma-intima` — **já criada a partir de `main`, com a spec já commitada nela**. NÃO criar outra branch nem voltar para `main` (ignore qualquer "Step 0" de criação de branch nas tasks abaixo).
- **Banco (dev):** **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo — apagariam os dados importados (123 palestras, 44 posts). Só `php artisan migrate` **incremental**.
- **Execução:** o app roda no container `cema-app` — rodar artisan/pint/test via `docker exec cema-app <cmd>`.
- **Tokens Tailwind v4:** usar as classes utilitárias do `@theme` de `resources/css/app.css` (os 23 tokens já existem); não hardcodar hex quando houver token.
- **Sanitização HTML:** apenas no **mutator do model** via `clean($valor, 'conteudo')` (perfil já existe em `config/purifier.php`) — cobre admin **e** importação; nunca sanitizar só no Resource.
- **Legado:** conexão `legado` é **somente leitura** (SELECT); nunca escrever no WordPress vivo.
- **Testes:** PHPUnit — `extends Tests\TestCase`, `use RefreshDatabase`, métodos `test_...(): void` em pt-BR. Rodar: `docker exec cema-app php artisan test --filter=...`.
- **Pint antes de commit/push:** `docker exec cema-app ./vendor/bin/pint <arquivos>` (o CI roda `pint --test` e aborta em drift).
- **Autoria:** todo arquivo PHP novo relevante começa com `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02`.
- **Verificação real:** além dos testes, abrir a página no `localhost` e conferir o comportamento antes de declarar pronto.
- **Merge:** só após o CI fechar **verde no último commit** do PR.

## Notas de integração entre tasks (ler antes de executar)

- **Ordem:** as tasks estão em ordem de dependência (1→14): dados (1-3) → importação (4-6) → admin (7-8) → front (9-12) → SEO (13-14).
- **Dono do `<x-slot:head>` de SEO:** o slot de SEO (`<link rel="canonical">`, `<meta robots noindex>` condicional e o `<script application/ld+json>` com `Article` + `BreadcrumbList`) é **de responsabilidade da Task 13**. Ao executar a **Task 11**, criar a casca `resources/views/agenda/index.blade.php` **sem** esse slot (apenas hero, breadcrumb, `@include _dia`, `@include _calendario`, "Sobre o projeto", "Veja também" e o **script de fuso**). **Ignore** o `<x-slot:head>` de SEO que aparece embutido no exemplo de código da Task 11 — ele é reimplementado de forma canônica na Task 13 (que usa a variável `$urlCanonica`). Isso evita slot duplicado e divergência de nomes de variável (`$canonical` vs `$urlCanonica`).
- **Variáveis da view** (fornecidas pelo `AgendaController`, Task 9): `$dia, $metaMes, $matriz, $diaAnterior, $diaProximo, $mesAnterior, $mesProximo, $ehUrlNua, $hojeBrasilia, $dataAtual, $temConteudo`.
- **Preâmbulos dos agentes:** cada fase pode começar com uma frase de contexto do rascunho ("Tenho tudo o que preciso…", "Mapeamento completo…") — é ruído inofensivo; siga direto para as tasks.

---

I have everything I need. Here are the three Fase A tasks, drafted to match the real molds (migration style from `create_posts_table.php`, model mutators/scope from `Palestra.php`/`Post.php`, factory style from `PostFactory.php`, test style from `BlogSchemaTest`/`SanitizacaoHtmlTest`/`PalestraTest`). Confirmed live: `clean()` helper (mews/purifier) + profile `'conteudo'` exist; `Carbon::setLocale('pt_BR')` in `AppServiceProvider::boot()` and `APP_LOCALE=pt_BR` (so `translatedFormat` renders pt-BR); latest migration is `2026_07_01_000001`, so the new ones use `2026_07_02_00000X`; no `agenda_*` tables exist yet.

---

## Fase A — Dados (models + migrations + importação-ready)

### Task 1: Migrations das 3 tabelas da Agenda + teste-smoke de schema

**Files:**
- Create `database/migrations/2026_07_02_000001_create_agenda_metas_mes_table.php`
- Create `database/migrations/2026_07_02_000002_create_agenda_dias_table.php`
- Create `database/migrations/2026_07_02_000003_create_agenda_slugs_legado_table.php`
- Test `tests/Feature/Models/AgendaSchemaTest.php`

**Interfaces:**
- Consumes: nada (fundação). Roda no container `cema-app`; migração **incremental** (`php artisan migrate`), NUNCA `migrate:fresh/refresh/wipe/reset`.
- Produces: tabelas `agenda_metas_mes` (`id, ano usmallint, mes utinyint, titulo string, timestamps, unique(ano,mes)`), `agenda_dias` (`id, data date unique, reflexao/meta_mes_texto/meta_dia_texto/prece text null, meta_dia_titulo string null, status string default 'publicado', wp_id ubigint null unique, timestamps, index status`), `agenda_slugs_legado` (`id, slug string unique, data date, index data` — **sem timestamps**).

Passos:

- [ ] **Step 0: Confirmar que está na branch `fase-agenda-reforma-intima`** (já criada a partir de `main`, com a spec commitada). NÃO criar outra branch nem voltar para `main`.
```bash
git branch --show-current   # deve imprimir: fase-agenda-reforma-intima
```

- [ ] **Step 1: Escrever o teste-smoke de schema (vai falhar — tabelas não existem).** Criar `tests/Feature/Models/AgendaSchemaTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_da_agenda_existem_com_as_colunas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('agenda_metas_mes'));
        $this->assertTrue(Schema::hasTable('agenda_dias'));
        $this->assertTrue(Schema::hasTable('agenda_slugs_legado'));

        $this->assertTrue(Schema::hasColumns('agenda_metas_mes', [
            'id', 'ano', 'mes', 'titulo', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('agenda_dias', [
            'id', 'data', 'reflexao', 'meta_mes_texto', 'meta_dia_titulo',
            'meta_dia_texto', 'prece', 'status', 'wp_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('agenda_slugs_legado', [
            'id', 'slug', 'data',
        ]));
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaSchemaTest`
  Expected: **FALHA** — `Failed asserting that false is true` (o `RefreshDatabase` migra o banco de teste, mas as migrations ainda não existem, então `Schema::hasTable('agenda_metas_mes')` retorna `false`).

- [ ] **Step 3: Criar a migration `agenda_metas_mes`.** `database/migrations/2026_07_02_000001_create_agenda_metas_mes_table.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('agenda_metas_mes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->string('titulo');
            $table->timestamps();
            $table->unique(['ano', 'mes']);
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_metas_mes');
    }
};
```

- [ ] **Step 4: Criar a migration `agenda_dias`.** `database/migrations/2026_07_02_000002_create_agenda_dias_table.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('agenda_dias', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();               // chave natural / idempotência
            $table->text('reflexao')->nullable();          // HTML (Evangelho, ref. embutida)
            $table->text('meta_mes_texto')->nullable();    // HTML (citação diária)
            $table->string('meta_dia_titulo')->nullable(); // dura vários dias
            $table->text('meta_dia_texto')->nullable();    // HTML
            $table->text('prece')->nullable();             // HTML
            $table->string('status')->default('publicado');
            $table->unsignedBigInteger('wp_id')->nullable()->unique(); // rastreio do legado
            $table->timestamps();
            $table->index('status');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_dias');
    }
};
```

- [ ] **Step 5: Criar a migration `agenda_slugs_legado` (sem timestamps — mapa de 301).** `database/migrations/2026_07_02_000003_create_agenda_slugs_legado_table.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Executa a migration. */
    public function up(): void
    {
        Schema::create('agenda_slugs_legado', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // post_name real (numérico OU de data)
            $table->date('data');             // destino do 301 (N slugs → 1 data)
            $table->index('data');
        });
    }

    /** Reverte a migration. */
    public function down(): void
    {
        Schema::dropIfExists('agenda_slugs_legado');
    }
};
```

- [ ] **Step 6: Aplicar as migrations no banco de dev (incremental) e rodar o smoke.**
  Run: `docker exec cema-app php artisan migrate` **e** `docker exec cema-app php artisan test --filter=AgendaSchemaTest`
  Expected: `migrate` mostra as 3 migrations `DONE` (nenhuma tabela pré-existente tocada); o teste passa — `OK (1 test, 7 assertions)`.
  ⚠️ NUNCA `migrate:fresh/refresh/wipe/reset` — apagariam as 123 palestras/44 posts importados.

- [ ] **Step 7: Pint + commit.**
```bash
docker exec cema-app ./vendor/bin/pint database/migrations/2026_07_02_000001_create_agenda_metas_mes_table.php database/migrations/2026_07_02_000002_create_agenda_dias_table.php database/migrations/2026_07_02_000003_create_agenda_slugs_legado_table.php tests/Feature/Models/AgendaSchemaTest.php
git add database/migrations/2026_07_02_000001_create_agenda_metas_mes_table.php database/migrations/2026_07_02_000002_create_agenda_dias_table.php database/migrations/2026_07_02_000003_create_agenda_slugs_legado_table.php tests/Feature/Models/AgendaSchemaTest.php
git commit -m "feat(agenda): migrations agenda_metas_mes, agenda_dias e agenda_slugs_legado + smoke de schema"
```

---

### Task 2: Models `AgendaMetaMes` e `AgendaSlugLegado` + factories + testes

**Files:**
- Create `app/Models/AgendaMetaMes.php`
- Create `app/Models/AgendaSlugLegado.php`
- Create `database/factories/AgendaMetaMesFactory.php`
- Create `database/factories/AgendaSlugLegadoFactory.php`
- Test `tests/Feature/Models/AgendaMetaMesTest.php`
- Test `tests/Feature/Models/AgendaSlugLegadoTest.php`

**Interfaces:**
- Consumes: tabelas da Task 1.
- Produces:
  - `App\Models\AgendaMetaMes` — `$table='agenda_metas_mes'`, `$fillable=['ano','mes','titulo']`, casts `ano:int, mes:int`. Factory `AgendaMetaMesFactory`.
  - `App\Models\AgendaSlugLegado` — `$table='agenda_slugs_legado'`, `public $timestamps=false`, `$fillable=['slug','data']`, cast `data:'date'`. Factory `AgendaSlugLegadoFactory`.

Passos:

- [ ] **Step 1: Escrever `AgendaMetaMesTest` (falha — model não existe).** `tests/Feature/Models/AgendaMetaMesTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaMetaMes;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaMetaMesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_meta_mes_com_casts_inteiros(): void
    {
        $meta = AgendaMetaMes::create([
            'ano' => 2026,
            'mes' => 6,
            'titulo' => 'Combater o egoísmo: indiferença e ingratidão',
        ]);

        $this->assertSame(2026, $meta->fresh()->ano);
        $this->assertSame(6, $meta->fresh()->mes);
        $this->assertDatabaseHas('agenda_metas_mes', ['ano' => 2026, 'mes' => 6]);
    }

    public function test_par_ano_mes_e_unico(): void
    {
        AgendaMetaMes::create(['ano' => 2026, 'mes' => 6, 'titulo' => 'Primeiro']);

        $this->expectException(QueryException::class);

        AgendaMetaMes::create(['ano' => 2026, 'mes' => 6, 'titulo' => 'Duplicado']);
    }

    public function test_factory_gera_registro_valido(): void
    {
        $meta = AgendaMetaMes::factory()->create(['ano' => 2026, 'mes' => 8]);

        $this->assertSame(2026, $meta->fresh()->ano);
        $this->assertSame(8, $meta->fresh()->mes);
    }
}
```

- [ ] **Step 2: Escrever `AgendaSlugLegadoTest` (falha — model não existe).** `tests/Feature/Models/AgendaSlugLegadoTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaSlugLegado;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaSlugLegadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_slug_com_cast_de_data(): void
    {
        $slug = AgendaSlugLegado::create([
            'slug' => '02-de-julho-de-2026',
            'data' => '2026-07-02',
        ]);

        $this->assertInstanceOf(Carbon::class, $slug->fresh()->data);
        $this->assertSame('2026-07-02', $slug->fresh()->data->format('Y-m-d'));
    }

    public function test_slug_e_unico(): void
    {
        // slug numérico (maio) = post ID
        AgendaSlugLegado::create(['slug' => '27057', 'data' => '2026-05-01']);

        $this->expectException(QueryException::class);

        AgendaSlugLegado::create(['slug' => '27057', 'data' => '2026-05-02']);
    }

    public function test_varios_slugs_apontam_para_a_mesma_data(): void
    {
        // duplicatas históricas que o Google indexou (N:1)
        AgendaSlugLegado::create(['slug' => '05-de-agosto-de-2026', 'data' => '2026-08-05']);
        AgendaSlugLegado::create(['slug' => '05-de-agosto-de-2026-2', 'data' => '2026-08-05']);

        $this->assertSame(2, AgendaSlugLegado::where('data', '2026-08-05')->count());
    }
}
```

- [ ] **Step 3: Rodar os dois testes e ver falhar.**
  Run: `docker exec cema-app php artisan test --filter="AgendaMetaMesTest|AgendaSlugLegadoTest"`
  Expected: **FALHA** — `Class "App\Models\AgendaMetaMes" not found` / `Class "App\Models\AgendaSlugLegado" not found`.

- [ ] **Step 4: Criar o model `AgendaMetaMes`.** `app/Models/AgendaMetaMes.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaMetaMes extends Model
{
    use HasFactory;

    protected $table = 'agenda_metas_mes';

    protected $fillable = ['ano', 'mes', 'titulo'];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
        ];
    }
}
```

- [ ] **Step 5: Criar o model `AgendaSlugLegado` (sem timestamps).** `app/Models/AgendaSlugLegado.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgendaSlugLegado extends Model
{
    use HasFactory;

    protected $table = 'agenda_slugs_legado';

    // A tabela não tem created_at/updated_at (mapa raso de 301).
    public $timestamps = false;

    protected $fillable = ['slug', 'data'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }
}
```

- [ ] **Step 6: Criar a factory `AgendaMetaMesFactory`.** `database/factories/AgendaMetaMesFactory.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaMetaMes;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgendaMetaMesFactory extends Factory
{
    protected $model = AgendaMetaMes::class;

    public function definition(): array
    {
        return [
            'ano' => 2026,
            'mes' => fake()->unique()->numberBetween(1, 12), // respeita unique(ano,mes)
            'titulo' => fake()->sentence(4),
        ];
    }
}
```

- [ ] **Step 7: Criar a factory `AgendaSlugLegadoFactory`.** `database/factories/AgendaSlugLegadoFactory.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaSlugLegado;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AgendaSlugLegadoFactory extends Factory
{
    protected $model = AgendaSlugLegado::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'data' => Carbon::create(2026, 5, 1)->addDays(fake()->numberBetween(0, 200))->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 8: Rodar os dois testes e ver passar.**
  Run: `docker exec cema-app php artisan test --filter="AgendaMetaMesTest|AgendaSlugLegadoTest"`
  Expected: `OK (6 tests, ...)` — todos verdes.

- [ ] **Step 9: Pint + commit.**
```bash
docker exec cema-app ./vendor/bin/pint app/Models/AgendaMetaMes.php app/Models/AgendaSlugLegado.php database/factories/AgendaMetaMesFactory.php database/factories/AgendaSlugLegadoFactory.php tests/Feature/Models/AgendaMetaMesTest.php tests/Feature/Models/AgendaSlugLegadoTest.php
git add app/Models/AgendaMetaMes.php app/Models/AgendaSlugLegado.php database/factories/AgendaMetaMesFactory.php database/factories/AgendaSlugLegadoFactory.php tests/Feature/Models/AgendaMetaMesTest.php tests/Feature/Models/AgendaSlugLegadoTest.php
git commit -m "feat(agenda): models AgendaMetaMes e AgendaSlugLegado com factories e testes"
```

---

### Task 3: Model `AgendaDia` (mutators, scope, accessors) + factory + testes

**Files:**
- Create `app/Models/AgendaDia.php`
- Create `database/factories/AgendaDiaFactory.php`
- Test `tests/Feature/Models/AgendaDiaTest.php`

**Interfaces:**
- Consumes: tabela `agenda_dias` (Task 1); `App\Models\AgendaMetaMes` (Task 2); helper `clean($v, 'conteudo')` (mews/purifier, perfil já existente); `Carbon::setLocale('pt_BR')` (AppServiceProvider::boot).
- Produces: `App\Models\AgendaDia` com
  `$fillable=['data','reflexao','meta_mes_texto','meta_dia_titulo','meta_dia_texto','prece','status','wp_id']`; cast `data:'date'`; `const STATUS_PUBLICADO='publicado'`, `STATUS_RASCUNHO='rascunho'`; `scopePublicado(Builder): Builder`; 4 mutators `Attribute` (`reflexao`, `meta_mes_texto`, `meta_dia_texto`, `prece`) via `clean($v,'conteudo')` null-safe; `metaMes(): ?AgendaMetaMes` (memoizado), `tituloExtenso(): string`, `descricaoSeo(): string`. Factory `AgendaDiaFactory` (`status` publicado, `data` única) + state `rascunho()`.

Passos:

- [ ] **Step 1: Escrever `AgendaDiaTest` (falha — model não existe).** `tests/Feature/Models/AgendaDiaTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgendaDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_escopo_publicado_filtra_rascunho(): void
    {
        AgendaDia::factory()->create(['data' => '2026-06-01']);
        AgendaDia::factory()->rascunho()->create(['data' => '2026-06-02']);

        $this->assertCount(1, AgendaDia::publicado()->get());
    }

    public function test_mutator_remove_script_e_preserva_formatacao(): void
    {
        $dia = AgendaDia::factory()->create([
            'reflexao' => '<p>Paz <strong>e amor</strong></p><script>alert(1)</script>',
            'prece' => '<p>Prece</p><script>alert(2)</script>',
        ]);

        $this->assertStringNotContainsString('<script', (string) $dia->fresh()->reflexao);
        $this->assertStringContainsString('<strong>e amor</strong>', (string) $dia->fresh()->reflexao);
        $this->assertStringNotContainsString('<script', (string) $dia->fresh()->prece);
    }

    public function test_mutator_valor_nulo_permanece_nulo(): void
    {
        $dia = AgendaDia::factory()->create(['prece' => null]);

        $this->assertNull($dia->fresh()->prece);
    }

    public function test_meta_mes_resolve_por_ano_e_mes(): void
    {
        $meta = AgendaMetaMes::factory()->create([
            'ano' => 2026,
            'mes' => 6,
            'titulo' => 'Combater o egoísmo: indiferença e ingratidão',
        ]);
        $dia = AgendaDia::factory()->create(['data' => '2026-06-15']);

        $this->assertTrue($meta->is($dia->metaMes()));
        $this->assertSame('Combater o egoísmo: indiferença e ingratidão', $dia->metaMes()->titulo);
    }

    public function test_meta_mes_ausente_retorna_null(): void
    {
        $dia = AgendaDia::factory()->create(['data' => '2026-07-15']);

        $this->assertNull($dia->metaMes());
    }

    public function test_titulo_extenso_em_ptbr_capitalizado(): void
    {
        $dia = AgendaDia::factory()->create(['data' => '2026-06-15']);

        $this->assertSame('Segunda-feira, 15 de junho de 2026', $dia->tituloExtenso());
    }

    public function test_descricao_seo_limita_155_sem_tags(): void
    {
        $dia = AgendaDia::factory()->create([
            'reflexao' => '<p>'.str_repeat('a', 300).'</p>',
        ]);

        $seo = $dia->descricaoSeo();

        $this->assertStringNotContainsString('<', $seo);
        $this->assertStringEndsWith('...', $seo);
        $this->assertLessThanOrEqual(158, Str::length($seo)); // 155 + reticências
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaDiaTest`
  Expected: **FALHA** — `Class "App\Models\AgendaDia" not found`.

- [ ] **Step 3: Criar o model `AgendaDia`.** `app/Models/AgendaDia.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgendaDia extends Model
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    protected $table = 'agenda_dias';

    protected $fillable = [
        'data',
        'reflexao',
        'meta_mes_texto',
        'meta_dia_titulo',
        'meta_dia_texto',
        'prece',
        'status',
        'wp_id',
    ];

    /** Cache por instância da Meta do Mês resolvida (evita nova query a cada acesso). */
    private ?AgendaMetaMes $metaMesCache = null;

    private bool $metaMesResolvida = false;

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    protected function reflexao(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function metaMesTexto(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function metaDiaTexto(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    protected function prece(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    /** Resolve o tema fixo do mês (ano+mes da data), memoizado por instância. */
    public function metaMes(): ?AgendaMetaMes
    {
        if (! $this->metaMesResolvida) {
            $this->metaMesCache = $this->data
                ? AgendaMetaMes::query()
                    ->where('ano', $this->data->year)
                    ->where('mes', $this->data->month)
                    ->first()
                : null;
            $this->metaMesResolvida = true;
        }

        return $this->metaMesCache;
    }

    /** Data por extenso em pt-BR, capitalizada (ex.: "Segunda-feira, 15 de junho de 2026"). */
    public function tituloExtenso(): string
    {
        return Str::ucfirst($this->data->translatedFormat('l, d \d\e F \d\e Y'));
    }

    /** Trecho da reflexão sem HTML para <meta description>, limitado a 155 caracteres. */
    public function descricaoSeo(): string
    {
        return Str::limit(trim(strip_tags((string) $this->reflexao)), 155);
    }
}
```

- [ ] **Step 4: Criar a factory `AgendaDiaFactory`.** `database/factories/AgendaDiaFactory.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Database\Factories;

use App\Models\AgendaDia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AgendaDiaFactory extends Factory
{
    protected $model = AgendaDia::class;

    public function definition(): array
    {
        return [
            // offset único de dias → data única (respeita agenda_dias.data unique)
            'data' => Carbon::create(2026, 5, 1)->addDays(fake()->unique()->numberBetween(0, 200))->format('Y-m-d'),
            'reflexao' => '<p>'.fake()->paragraph().'</p>',
            'meta_mes_texto' => '<p>'.fake()->sentence().'</p>',
            'meta_dia_titulo' => fake()->sentence(3),
            'meta_dia_texto' => '<p>'.fake()->sentence().'</p>',
            'prece' => '<p>'.fake()->sentence().'</p>',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ];
    }

    /** Dia salvo como rascunho (não entra no scope publicado). */
    public function rascunho(): static
    {
        return $this->state(['status' => AgendaDia::STATUS_RASCUNHO]);
    }
}
```

- [ ] **Step 5: Rodar o teste e ver passar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaDiaTest`
  Expected: `OK (7 tests, ...)` — todos verdes (mutators removem `<script>` via perfil `conteudo`; `metaMes()` casa por ano+mes; `tituloExtenso()` sai `Segunda-feira, 15 de junho de 2026` pelo locale pt_BR do Carbon; `descricaoSeo()` sem tags e ≤ 158 chars).

- [ ] **Step 6: Rodar a suíte da Agenda inteira para regressão.**
  Run: `docker exec cema-app php artisan test --filter="AgendaSchemaTest|AgendaMetaMesTest|AgendaSlugLegadoTest|AgendaDiaTest"`
  Expected: todos verdes (`OK`), sem tocar dados existentes.

- [ ] **Step 7: Pint + commit.**
```bash
docker exec cema-app ./vendor/bin/pint app/Models/AgendaDia.php database/factories/AgendaDiaFactory.php tests/Feature/Models/AgendaDiaTest.php
git add app/Models/AgendaDia.php database/factories/AgendaDiaFactory.php tests/Feature/Models/AgendaDiaTest.php
git commit -m "feat(agenda): model AgendaDia com sanitizacao, scope publicado, metaMes/tituloExtenso/descricaoSeo e factory"
```

---

Notas de fidelidade aos moldes (para o implementador):
- `clean($v, 'conteudo')` e o perfil `'conteudo'` já existem em `config/purifier.php` (linha 117) — mesmo padrão de `Palestra::descricao()` e `Post::conteudo()`.
- `AgendaSlugLegado` **precisa** de `public $timestamps = false;` porque a tabela não tem `created_at/updated_at` (spec §4); sem isso os inserts do model/factory quebram.
- `tituloExtenso()` depende de `Carbon::setLocale('pt_BR')` (já em `AppServiceProvider::boot()`, confirmado) e de `APP_LOCALE=pt_BR` (`.env`); em testes o boot roda, então o pt-BR é garantido. `2026-06-15` cai numa segunda-feira (conferido).
- Migrations sem `migrate:fresh/refresh/wipe/reset` — só `php artisan migrate` incremental; a Task 1 usa timestamps `2026_07_02_00000X` (posteriores ao último existente `2026_07_01_000001`).

---

I have everything I need. Here is the plan text for Fase B (Importação), Tasks 4-6.

---

## Fase B — Importação (`cema:importar-agenda`)

> Depende da Fase A (Task 1–3): models `App\Models\AgendaDia`, `App\Models\AgendaMetaMes`, `App\Models\AgendaSlugLegado` (com casts, scopes, mutators e const de status) e suas factories já criados e migrados. Molde de referência do pipeline: `ImportarPalestras` → `ImportadorPalestras` + `LeitorLegado`/`LeitorLegadoMysql` + bind em `AppServiceProvider::register()`.

### Task 4: Interface `LeitorAgenda` + `GlossarioAgenda` (mapa de maio + `resolver()`)

**Files:**
- Create: `app/Importacao/LeitorAgenda.php`
- Create: `app/Importacao/GlossarioAgenda.php`
- Test: `tests/Feature/Importacao/GlossarioAgendaTest.php`

**Interfaces:**
- Produces: `interface App\Importacao\LeitorAgenda { public function entradas(): array; }` — cada item é `array{data:string,wp_id:int,post_name:string,reflexao:?string,mes_titulo:?string,mes_texto:?string,meta_dia_titulo:?string,meta_dia_texto:?string,prece:?string,avisos:string[]}`.
- Produces: `App\Importacao\GlossarioAgenda` — `const MAPA` (spec §3) + `public static function resolver(?string $valor): array{valor:?string,aviso:?string}`.
- Consumes: nada (lógica pura).

Passos:

- [ ] **Step 1: Escrever o teste (que falha) do `GlossarioAgenda`.**
```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\GlossarioAgenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlossarioAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chave_conhecida_resolve_para_o_texto(): void
    {
        $this->assertSame(
            ['valor' => 'Desenvolver abnegação, renúncia e solidariedade', 'aviso' => null],
            GlossarioAgenda::resolver('maio_2026'),
        );
        $this->assertSame(
            ['valor' => 'Desenvolver a Renúncia', 'aviso' => null],
            GlossarioAgenda::resolver('meta_dia_maio_2026_02'),
        );
    }

    public function test_chave_2026_desconhecida_grava_null_e_avisa(): void
    {
        $resultado = GlossarioAgenda::resolver('setembro_2026');
        $this->assertNull($resultado['valor']);
        $this->assertNotNull($resultado['aviso']);
        $this->assertStringContainsString('setembro_2026', $resultado['aviso']);

        // chave de meta do dia não mapeada também cai no null + aviso
        $meta = GlossarioAgenda::resolver('meta_dia_setembro_2026_01');
        $this->assertNull($meta['valor']);
        $this->assertNotNull($meta['aviso']);
    }

    public function test_texto_normal_e_null_passam_sem_alteracao(): void
    {
        $this->assertSame(
            ['valor' => 'Combater o egoísmo: indiferença e ingratidão', 'aviso' => null],
            GlossarioAgenda::resolver('Combater o egoísmo: indiferença e ingratidão'),
        );
        $this->assertSame(['valor' => null, 'aviso' => null], GlossarioAgenda::resolver(null));
    }
}
```
  Run: `docker exec cema-app php artisan test --filter=GlossarioAgendaTest`
  Expected: falha — `Error: Class "App\Importacao\GlossarioAgenda" not found` (a classe ainda não existe).

- [ ] **Step 2: Criar a interface `LeitorAgenda` (contrato consumido pelas Tasks 5 e 6).**
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

interface LeitorAgenda
{
    /**
     * Uma linha por post válido (publish/future) do CPT agenda-reforma, já normalizada.
     *
     * @return array<int, array{
     *     data: string, wp_id: int, post_name: string, reflexao: ?string,
     *     mes_titulo: ?string, mes_texto: ?string, meta_dia_titulo: ?string,
     *     meta_dia_texto: ?string, prece: ?string, avisos: string[]
     * }>
     */
    public function entradas(): array;
}
```

- [ ] **Step 3: Criar o `GlossarioAgenda` (mapa fixo da spec §3 + `resolver()`).**
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

class GlossarioAgenda
{
    /**
     * Resolução das chaves cruas do glossary do JetEngine (só maio de 2026 as usa).
     * Jun/jul/ago já trazem texto puro. Fonte: introspecção do legado (spec §3).
     */
    public const MAPA = [
        'maio_2026' => 'Desenvolver abnegação, renúncia e solidariedade',
        'meta_dia_maio_2026_01' => 'Desenvolver Abnegação',
        'meta_dia_maio_2026_02' => 'Desenvolver a Renúncia',
        'meta_dia_maio_2026_03' => 'Desenvolver Renúncia no Lar',
        'meta_dia_maio_2026_04' => 'Desenvolver a Solidariedade',
    ];

    /**
     * Resolve uma chave de glossary para o texto final.
     * - chave conhecida  -> texto do MAPA, sem aviso;
     * - chave crua "*_2026[_NN]" não mapeada -> null + aviso (nunca grava a chave crua);
     * - texto puro (ou null) -> passa como está.
     *
     * @return array{valor: ?string, aviso: ?string}
     */
    public static function resolver(?string $valor): array
    {
        if ($valor === null) {
            return ['valor' => null, 'aviso' => null];
        }

        if (array_key_exists($valor, self::MAPA)) {
            return ['valor' => self::MAPA[$valor], 'aviso' => null];
        }

        // parece uma chave crua de 2026 (ex.: "setembro_2026", "meta_dia_x_2026_03") não mapeada
        if (preg_match('/_2026(_\d+)?$/', $valor) === 1) {
            return [
                'valor' => null,
                'aviso' => "Chave de glossary não resolvida: '{$valor}' (gravado null).",
            ];
        }

        return ['valor' => $valor, 'aviso' => null];
    }
}
```
  Run: `docker exec cema-app php artisan test --filter=GlossarioAgendaTest`
  Expected: OK (3 testes, todas as assertivas passam).

- [ ] **Step 4: Commit.**
```bash
git add app/Importacao/LeitorAgenda.php app/Importacao/GlossarioAgenda.php tests/Feature/Importacao/GlossarioAgendaTest.php
git commit -m "feat(agenda/import): interface LeitorAgenda e GlossarioAgenda (mapa de maio + resolver)"
```

---

### Task 5: `ImportadorAgenda` (idempotente — dedupe por data, metas do mês, slugs 301)

**Files:**
- Create: `app/Importacao/ImportadorAgenda.php`
- Test: `tests/Feature/Importacao/ImportadorAgendaTest.php`

**Interfaces:**
- Produces: `App\Importacao\ImportadorAgenda { public function __construct(private LeitorAgenda $leitor) {} public function importar(?callable $log = null): array; }` — retorno `['metas'=>int,'dias'=>int,'slugs'=>int,'avisos'=>string[]]`.
- Consumes: `App\Importacao\LeitorAgenda` (Task 4); models `App\Models\AgendaMetaMes` (`updateOrCreate(['ano','mes'],['titulo'])`), `App\Models\AgendaDia` (`updateOrCreate(['data'],[...])`, `const STATUS_PUBLICADO`), `App\Models\AgendaSlugLegado` (`updateOrCreate(['slug'],['data'])`).

Passos:

- [ ] **Step 1: Escrever o teste (que falha) do `ImportadorAgenda`, com leitor fake anônimo.**
```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorAgenda;
use App\Importacao\LeitorAgenda;
use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportadorAgendaTest extends TestCase
{
    use RefreshDatabase;

    private function leitorFake(): LeitorAgenda
    {
        return new class implements LeitorAgenda
        {
            public function entradas(): array
            {
                return [
                    // maio: slug numérico (= post ID), glossary já resolvido pelo leitor
                    [
                        'data' => '2026-05-01', 'wp_id' => 27057, 'post_name' => '27057',
                        'reflexao' => '<p>Reflexão de 1º de maio</p>',
                        'mes_titulo' => 'Desenvolver abnegação, renúncia e solidariedade',
                        'mes_texto' => '<p>Citação do dia (maio)</p>',
                        'meta_dia_titulo' => 'Desenvolver Abnegação',
                        'meta_dia_texto' => '<p>Meta do dia</p>',
                        'prece' => '<p>Prece de maio</p>',
                        'avisos' => [],
                    ],
                    // agosto: original (ID menor, slug limpo)
                    [
                        'data' => '2026-08-05', 'wp_id' => 30001, 'post_name' => '05-de-agosto-de-2026',
                        'reflexao' => '<p>Reflexão de 5 de agosto (A)</p>',
                        'mes_titulo' => 'Desenvolver a caridade moral',
                        'mes_texto' => '<p>Citação A</p>',
                        'meta_dia_titulo' => 'Caridade',
                        'meta_dia_texto' => '<p>Meta A</p>',
                        'prece' => '<p>Prece A</p>',
                        'avisos' => [],
                    ],
                    // agosto DUPLICADO na mesma data -> dedupe: 1 AgendaDia, 2 slugs 301
                    [
                        'data' => '2026-08-05', 'wp_id' => 30002, 'post_name' => '05-de-agosto-de-2026-2',
                        'reflexao' => '<p>Reflexão duplicada (B)</p>',
                        'mes_titulo' => 'Desenvolver a caridade moral',
                        'mes_texto' => '<p>Citação B</p>',
                        'meta_dia_titulo' => 'Caridade',
                        'meta_dia_texto' => '<p>Meta B</p>',
                        'prece' => '<p>Prece B</p>',
                        'avisos' => [],
                    ],
                    // glossary não resolvido pelo leitor -> mes_titulo/meta_dia_titulo null + aviso carregado
                    [
                        'data' => '2026-09-01', 'wp_id' => 31000, 'post_name' => 'setembro-2026-slug',
                        'reflexao' => '<p>Reflexão de setembro</p>',
                        'mes_titulo' => null,
                        'mes_texto' => '<p>Citação de setembro</p>',
                        'meta_dia_titulo' => null,
                        'meta_dia_texto' => '<p>Meta de setembro</p>',
                        'prece' => '<p>Prece de setembro</p>',
                        'avisos' => ["[setembro-2026-slug] Chave de glossary não resolvida: 'setembro_2026' (gravado null)."],
                    ],
                ];
            }
        };
    }

    public function test_importa_e_e_idempotente_com_dedupe_glossary_e_meta_do_mes(): void
    {
        $importador = new ImportadorAgenda($this->leitorFake());

        // roda 2x -> idempotência (contagens não duplicam)
        $importador->importar();
        $resumo = $importador->importar();

        // 3 datas (05/08 deduplicado), 2 metas de mês, 4 slugs
        $this->assertSame(3, AgendaDia::count());
        $this->assertSame(2, AgendaMetaMes::count());
        $this->assertSame(4, AgendaSlugLegado::count());
        $this->assertSame(['metas' => 2, 'dias' => 3, 'slugs' => 4], [
            'metas' => $resumo['metas'], 'dias' => $resumo['dias'], 'slugs' => $resumo['slugs'],
        ]);

        // dedupe: 05/08 = 1 dia (conteúdo do slug limpo/original) e 2 slugs para a mesma data
        $this->assertSame(1, AgendaDia::where('data', '2026-08-05')->count());
        $this->assertSame(2, AgendaSlugLegado::where('data', '2026-08-05')->count());
        $this->assertStringContainsString('(A)', AgendaDia::where('data', '2026-08-05')->value('reflexao'));

        // meta do mês criada por ano+mes
        $this->assertSame(
            'Desenvolver abnegação, renúncia e solidariedade',
            AgendaMetaMes::where('ano', 2026)->where('mes', 5)->value('titulo'),
        );

        // glossary não resolvido: sem meta de setembro; meta_dia_titulo gravado null
        $this->assertFalse(AgendaMetaMes::where('ano', 2026)->where('mes', 9)->exists());
        $this->assertNull(AgendaDia::where('data', '2026-09-01')->value('meta_dia_titulo'));

        // avisos: o carregado pelo leitor (glossary) + o do dedupe estão no resumo
        $this->assertTrue(collect($resumo['avisos'])->contains(fn ($a) => str_contains($a, 'glossary não resolvida')));
        $this->assertTrue(collect($resumo['avisos'])->contains(fn ($a) => str_contains($a, '[dedupe]')));

        // status default publicado; conteúdo HTML sanitizado pelo mutator do model, texto preservado
        $this->assertSame(AgendaDia::STATUS_PUBLICADO, AgendaDia::where('data', '2026-05-01')->value('status'));
        $this->assertStringContainsString('1º de maio', AgendaDia::where('data', '2026-05-01')->value('reflexao'));
    }
}
```
  Run: `docker exec cema-app php artisan test --filter=ImportadorAgendaTest`
  Expected: falha — `Error: Class "App\Importacao\ImportadorAgenda" not found`.

- [ ] **Step 2: Implementar o `ImportadorAgenda` (dedupe por data, metas do mês, upsert de dias e slugs, transação, avisos).**
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportadorAgenda
{
    /** @var string[] */
    private array $avisos = [];

    public function __construct(private LeitorAgenda $leitor) {}

    /**
     * Importa a Agenda Reforma Íntima do legado (via leitor injetado), de forma idempotente.
     *
     * @return array{metas: int, dias: int, slugs: int, avisos: string[]}
     */
    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $entradas = $this->leitor->entradas();

        // Dedupe defensivo por data: uma AgendaDia por data (mantém o 1º = slug limpo/ID menor);
        // TODOS os slugs continuam preservados para o mapa de 301.
        $porData = [];
        foreach ($entradas as $e) {
            foreach ($e['avisos'] ?? [] as $aviso) {
                $this->avisos[] = $aviso;
            }

            $data = $e['data'];
            if (isset($porData[$data])) {
                $mantido = $porData[$data]['post_name'];
                $this->avisos[] = "[dedupe] {$data}: conteúdo mantido de '{$mantido}'; '{$e['post_name']}' entra só como 301.";

                continue;
            }
            $porData[$data] = $e;
        }

        $nMetas = 0;
        $nDias = 0;
        $nSlugs = 0;

        DB::transaction(function () use ($entradas, $porData, &$nMetas, &$nDias, &$nSlugs, $log) {
            // Metas do mês (título fixo por mês) a partir dos dias canônicos.
            $log('Gravando metas de mês...');
            $metas = [];
            foreach ($porData as $e) {
                if (($e['mes_titulo'] ?? null) === null) {
                    continue;
                }
                $d = Carbon::parse($e['data']);
                $metas["{$d->year}-{$d->month}"] = ['ano' => $d->year, 'mes' => $d->month, 'titulo' => $e['mes_titulo']];
            }
            foreach ($metas as $m) {
                AgendaMetaMes::updateOrCreate(
                    ['ano' => $m['ano'], 'mes' => $m['mes']],
                    ['titulo' => $m['titulo']],
                );
            }
            $nMetas = count($metas);

            // Dias (uma entrada por data). HTML cru: o mutator do model sanitiza.
            $log('Gravando dias...');
            foreach ($porData as $e) {
                AgendaDia::updateOrCreate(['data' => $e['data']], [
                    'reflexao' => $e['reflexao'] ?? null,
                    'meta_mes_texto' => $e['mes_texto'] ?? null,
                    'meta_dia_titulo' => $e['meta_dia_titulo'] ?? null,
                    'meta_dia_texto' => $e['meta_dia_texto'] ?? null,
                    'prece' => $e['prece'] ?? null,
                    'status' => AgendaDia::STATUS_PUBLICADO,
                    'wp_id' => $e['wp_id'] ?? null,
                ]);
            }
            $nDias = count($porData);

            // Slugs de 301 — TODOS os posts válidos (N:1 com a data).
            $log('Gravando slugs de 301...');
            $slugs = [];
            foreach ($entradas as $e) {
                AgendaSlugLegado::updateOrCreate(['slug' => $e['post_name']], ['data' => $e['data']]);
                $slugs[$e['post_name']] = true;
            }
            $nSlugs = count($slugs);
        });

        return ['metas' => $nMetas, 'dias' => $nDias, 'slugs' => $nSlugs, 'avisos' => $this->avisos];
    }
}
```
  Run: `docker exec cema-app php artisan test --filter=ImportadorAgendaTest`
  Expected: OK (1 teste; contagens estáveis nas duas execuções, dedupe/glossary/meta cobertos).

- [ ] **Step 3: Commit.**
```bash
git add app/Importacao/ImportadorAgenda.php tests/Feature/Importacao/ImportadorAgendaTest.php
git commit -m "feat(agenda/import): ImportadorAgenda idempotente (dedupe por data, metas do mês, slugs 301)"
```

---

### Task 6: `LeitorAgendaMysql` + bind + comando `cema:importar-agenda`

**Files:**
- Create: `app/Importacao/LeitorAgendaMysql.php`
- Create: `app/Console/Commands/ImportarAgenda.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind `LeitorAgenda` → `LeitorAgendaMysql` em `register()`)
- Test: `tests/Feature/Importacao/ImportarAgendaCommandTest.php`

**Interfaces:**
- Produces: `App\Importacao\LeitorAgendaMysql implements LeitorAgenda` — `DB::connection('legado')` no construtor; `entradas(): array` (SELECT read-only). Command `App\Console\Commands\ImportarAgenda` (`signature='cema:importar-agenda'`, `handle(LeitorAgenda $leitor, ImportadorAgenda $importador): int`).
- Consumes: `App\Importacao\LeitorAgenda`, `App\Importacao\GlossarioAgenda`, `App\Importacao\ImportadorAgenda`, `App\Importacao\TransformadorLegado::unixParaData()`.

> Nota (como no molde): `LeitorAgendaMysql` **não** tem teste unitário — bate no banco `legado` vivo. Cobre-se o pipeline via **fake anônimo** rebindado no container, exatamente como `ImportarPalestrasCommandTest`.

Passos:

- [ ] **Step 1: Escrever o teste (que falha) do comando, rebindando a interface com fake.**
```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorAgenda;
use App\Models\AgendaDia;
use App\Models\AgendaMetaMes;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarAgendaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_usando_o_leitor_injetado(): void
    {
        // injeta um leitor fake no container (evita depender do legado vivo)
        $this->app->bind(LeitorAgenda::class, fn () => new class implements LeitorAgenda
        {
            public function entradas(): array
            {
                return [[
                    'data' => '2026-06-15', 'wp_id' => 28000, 'post_name' => '15-de-junho-de-2026',
                    'reflexao' => '<p>Reflexão de junho</p>',
                    'mes_titulo' => 'Combater o egoísmo: indiferença e ingratidão',
                    'mes_texto' => '<p>Citação</p>',
                    'meta_dia_titulo' => 'Vencer a indiferença',
                    'meta_dia_texto' => '<p>Meta</p>',
                    'prece' => '<p>Prece</p>',
                    'avisos' => [],
                ]];
            }
        });

        $this->artisan('cema:importar-agenda')
            ->expectsOutputToContain('Importação concluída')
            ->assertExitCode(0);

        $this->assertSame(1, AgendaDia::count());
        $this->assertSame(1, AgendaMetaMes::count());
        $this->assertSame(1, AgendaSlugLegado::count());
        $this->assertSame('2026-06-15', AgendaDia::first()->data->format('Y-m-d'));
        $this->assertSame(
            'Combater o egoísmo: indiferença e ingratidão',
            AgendaMetaMes::where('ano', 2026)->where('mes', 6)->value('titulo'),
        );
    }
}
```
  Run: `docker exec cema-app php artisan test --filter=ImportarAgendaCommandTest`
  Expected: falha — comando inexistente (`Symfony\Component\Console\Exception\CommandNotFoundException: The command "cema:importar-agenda" does not exist`).

- [ ] **Step 2: Criar o `LeitorAgendaMysql` (SELECT read-only no `legado`, ignora draft/`__trashed`, deriva data, cruza `_dia_agenda`, resolve glossary).**
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorAgendaMysql implements LeitorAgenda
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function entradas(): array
    {
        // Somente publish/future; ignora sobras de lixeira (slug terminando em __trashed).
        // ORDER BY ID ASC => o post original (ID menor) vem primeiro; o dedupe do
        // importador mantém esse (slug limpo) e trata a cópia (-2) só como 301.
        $rows = $this->db->select(
            "SELECT ID, post_name, post_status, DATE(post_date) AS data_post
             FROM wp_posts
             WHERE post_type = 'agenda-reforma'
               AND post_status IN ('publish', 'future')
               AND post_name NOT LIKE '%\\_\\_trashed' ESCAPE '\\'
             ORDER BY ID ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID;
            $meta = $this->metasDe($id);
            $dataPost = $r->data_post; // 'AAAA-MM-DD'
            $avisos = [];

            // Cruza DATE(post_date) x _dia_agenda (unix -> data). Diverge? avisa (usa post_date).
            $dataUnix = TransformadorLegado::unixParaData($meta['_dia_agenda'] ?? null);
            if ($dataUnix !== null && $dataUnix->format('Y-m-d') !== $dataPost) {
                $avisos[] = "[{$r->post_name}] data divergente: post_date={$dataPost} vs _dia_agenda={$dataUnix->format('Y-m-d')} (usado post_date).";
            }

            // Resolve chaves de glossary (maio); chave crua *_2026 não mapeada -> null + aviso.
            $mes = GlossarioAgenda::resolver($meta['_mes_titulo'] ?? null);
            if ($mes['aviso'] !== null) {
                $avisos[] = "[{$r->post_name}] {$mes['aviso']}";
            }
            $metaDia = GlossarioAgenda::resolver($meta['_titulo_meta_dia'] ?? null);
            if ($metaDia['aviso'] !== null) {
                $avisos[] = "[{$r->post_name}] {$metaDia['aviso']}";
            }

            $out[] = [
                'data' => $dataPost,
                'wp_id' => $id,
                'post_name' => $r->post_name,
                'reflexao' => $meta['_reflexao'] ?? null,
                'mes_titulo' => $mes['valor'],
                'mes_texto' => $meta['_mes_texto'] ?? null,
                'meta_dia_titulo' => $metaDia['valor'],
                'meta_dia_texto' => $meta['_dia'] ?? null,
                'prece' => $meta['_prece'] ?? null,
                'avisos' => $avisos,
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (primeiro valor) */
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
}
```

- [ ] **Step 3: Registrar o bind da interface em `AppServiceProvider::register()`.**
  No arquivo `app/Providers/AppServiceProvider.php`, adicionar o `use` e a linha de bind (junto aos binds existentes):
```php
use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
```
```php
    public function register(): void
    {
        $this->app->bind(LeitorLegado::class, LeitorLegadoMysql::class);
        $this->app->bind(LeitorBlog::class, LeitorBlogMysql::class);
        $this->app->bind(LeitorAgenda::class, LeitorAgendaMysql::class);
        $this->app->bind(FonteReflexao::class, ReflexaoConfig::class);
    }
```

- [ ] **Step 4: Criar o comando `cema:importar-agenda` (guarda de túnel + log + resumo/avisos).**
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Console\Commands;

use App\Importacao\ImportadorAgenda;
use App\Importacao\LeitorAgenda;
use App\Importacao\LeitorAgendaMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarAgenda extends Command
{
    protected $signature = 'cema:importar-agenda';

    protected $description = 'Importa a Agenda Reforma Íntima do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorAgenda $leitor, ImportadorAgenda $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorAgendaMysql) {
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
        $this->info("Importação concluída: {$resumo['metas']} metas de mês, {$resumo['dias']} dias, {$resumo['slugs']} slugs.");
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
  Run: `docker exec cema-app php artisan test --filter=ImportarAgendaCommandTest`
  Expected: OK (1 teste; o comando importa via fake, imprime "Importação concluída" e sai com código 0).

- [ ] **Step 5: Rodar a suíte de importação inteira da agenda (regressão das Tasks 4–6).**
  Run: `docker exec cema-app php artisan test --filter="GlossarioAgendaTest|ImportadorAgendaTest|ImportarAgendaCommandTest"`
  Expected: OK — 5 testes, sem falhas.

- [ ] **Step 6: Commit.**
```bash
git add app/Importacao/LeitorAgendaMysql.php app/Console/Commands/ImportarAgenda.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportarAgendaCommandTest.php
git commit -m "feat(agenda/import): LeitorAgendaMysql, bind e comando cema:importar-agenda"
```

---

Observações de fidelidade aos moldes (referências reais lidas):
- `metasDe()`, a guarda de túnel (`DB::connection('legado')->getPdo()` em try/catch) e o padrão de resumo/`warn` de avisos são copiados de `LeitorLegadoMysql`/`ImportarPalestras`.
- `TransformadorLegado::unixParaData()` (já existente) é reutilizado para o cruzamento `_dia_agenda`, mantendo a mesma semântica de fuso do molde.
- Rebind da interface via `$this->app->bind(...)` no teste do comando segue exatamente `ImportarPalestrasCommandTest`.
- A escolha de `DB::transaction` única (em vez de por-entrada) é deliberada: a Agenda é catálogo raso sem pivôs, então uma transação atômica é mais simples e segura que o padrão por-entrada de Palestras.

---

Confirmed all Filament 5 namespaces and mold patterns. Here are Task 7 and Task 8.

---

### Task 7: `AgendaMetaMesResource` (admin do tema fixo do mês)

**Files:**
- Create `app/Filament/Resources/Agenda/AgendaMetaMesResource.php`
- Create `app/Filament/Resources/Agenda/Pages/ListAgendaMetasMes.php`
- Create `app/Filament/Resources/Agenda/Pages/CreateAgendaMetaMes.php`
- Create `app/Filament/Resources/Agenda/Pages/EditAgendaMetaMes.php`
- Test `tests/Feature/Filament/AgendaMetaMesResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\AgendaMetaMes` (table `agenda_metas_mes`; fillable `['ano','mes','titulo']`; casts `ano:int,mes:int`; factory `AgendaMetaMesFactory`); painel gateado por `User::canAccessPanel` (local/testing); auto-discovery de `app/Filament/Resources`.
- Produces: Resource Filament CRUD com unicidade composta `(ano, mes)`; página `App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes` (usada nos testes Livewire).

Passos:

- [ ] **Step 1: escrever o teste que falha (cria + rejeita duplicado).** Cria `tests/Feature/Filament/AgendaMetaMesResourceTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes;
use App\Models\AgendaMetaMes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaMetaMesResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_meta_mes(): void
    {
        Livewire::test(CreateAgendaMetaMes::class)
            ->fillForm([
                'ano' => 2026,
                'mes' => 7,
                'titulo' => 'Combater o egoísmo: inveja, ciúme e maledicência',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('agenda_metas_mes', [
            'ano' => 2026,
            'mes' => 7,
            'titulo' => 'Combater o egoísmo: inveja, ciúme e maledicência',
        ]);
    }

    public function test_rejeita_ano_mes_duplicado(): void
    {
        AgendaMetaMes::factory()->create([
            'ano' => 2026,
            'mes' => 7,
            'titulo' => 'Tema já existente',
        ]);

        Livewire::test(CreateAgendaMetaMes::class)
            ->fillForm([
                'ano' => 2026,
                'mes' => 7,
                'titulo' => 'Outro tema para o mesmo mês',
            ])
            ->call('create')
            ->assertHasFormErrors(['mes']);

        $this->assertDatabaseMissing('agenda_metas_mes', [
            'titulo' => 'Outro tema para o mesmo mês',
        ]);
    }
}
```

- [ ] **Step 2: rodar o teste e ver falhar (classe de página inexistente).**
  Run: `docker exec cema-app php artisan test --filter=AgendaMetaMesResourceTest`
  Expected: erro `Class "App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes" not found` (nenhum teste passa).

- [ ] **Step 3: criar o Resource com form, table e unicidade composta.** Cria `app/Filament/Resources/Agenda/AgendaMetaMesResource.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda;

use App\Filament\Resources\Agenda\Pages\CreateAgendaMetaMes;
use App\Filament\Resources\Agenda\Pages\EditAgendaMetaMes;
use App\Filament\Resources\Agenda\Pages\ListAgendaMetasMes;
use App\Models\AgendaMetaMes;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AgendaMetaMesResource extends Resource
{
    protected static ?string $model = AgendaMetaMes::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Tema do mês';

    protected static ?string $pluralModelLabel = 'Temas do mês';

    protected static ?string $recordTitleAttribute = 'titulo';

    // Rótulos pt-BR dos meses; reaproveitado no Select e na coluna da tabela.
    protected static function meses(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('ano')
                    ->label('Ano')
                    ->required()
                    ->numeric()
                    ->minValue(2000)
                    ->maxValue(2100),
                Select::make('mes')
                    ->label('Mês')
                    ->required()
                    ->options(self::meses())
                    // Unicidade composta (ano, mes): a regra roda na coluna 'mes'
                    // restrita ao 'ano' informado; ignora o próprio registro na edição.
                    ->rules([
                        fn (Get $get, ?Model $record) => Rule::unique('agenda_metas_mes', 'mes')
                            ->where('ano', $get('ano'))
                            ->ignore($record),
                    ])
                    ->validationMessages([
                        'unique' => 'Já existe um tema cadastrado para este mês e ano.',
                    ]),
            ]),
            TextInput::make('titulo')
                ->label('Título')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ano')
                    ->label('Ano')
                    ->sortable(),
                TextColumn::make('mes')
                    ->label('Mês')
                    ->formatStateUsing(fn (int $state) => self::meses()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->limit(60),
            ])
            // defaultSort não faz ordenação composta; garantimos ano desc, mês desc na query.
            ->modifyQueryUsing(fn (Builder $query) => $query->orderByDesc('ano')->orderByDesc('mes'))
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListAgendaMetasMes::route('/'),
            'create' => CreateAgendaMetaMes::route('/create'),
            'edit' => EditAgendaMetaMes::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: criar as três Pages (List/Create/Edit).**
  `app/Filament/Resources/Agenda/Pages/ListAgendaMetasMes.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaMetaMesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgendaMetasMes extends ListRecords
{
    protected static string $resource = AgendaMetaMesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```
  `app/Filament/Resources/Agenda/Pages/CreateAgendaMetaMes.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaMetaMesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgendaMetaMes extends CreateRecord
{
    protected static string $resource = AgendaMetaMesResource::class;
}
```
  `app/Filament/Resources/Agenda/Pages/EditAgendaMetaMes.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaMetaMesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgendaMetaMes extends EditRecord
{
    protected static string $resource = AgendaMetaMesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 5: rodar o teste e ver passar (verde).**
  Run: `docker exec cema-app php artisan test --filter=AgendaMetaMesResourceTest`
  Expected: `OK (2 tests, ...)` — `test_cria_meta_mes` e `test_rejeita_ano_mes_duplicado` passam.

- [ ] **Step 6: Pint + commit.**
  Run: `docker exec cema-app ./vendor/bin/pint app/Filament/Resources/Agenda tests/Feature/Filament/AgendaMetaMesResourceTest.php`
  Expected: sem erros de estilo. Depois:
```
git add app/Filament/Resources/Agenda/AgendaMetaMesResource.php \
        app/Filament/Resources/Agenda/Pages/ListAgendaMetasMes.php \
        app/Filament/Resources/Agenda/Pages/CreateAgendaMetaMes.php \
        app/Filament/Resources/Agenda/Pages/EditAgendaMetaMes.php \
        tests/Feature/Filament/AgendaMetaMesResourceTest.php
git commit -m "feat(agenda/admin): AgendaMetaMesResource com unicidade composta ano+mes"
```

---

### Task 8: `AgendaDiaResource` (admin da entrada diária)

**Files:**
- Create `app/Filament/Resources/Agenda/AgendaDiaResource.php`
- Create `app/Filament/Resources/Agenda/Pages/ListAgendaDias.php`
- Create `app/Filament/Resources/Agenda/Pages/CreateAgendaDia.php`
- Create `app/Filament/Resources/Agenda/Pages/EditAgendaDia.php`
- Test `tests/Feature/Filament/AgendaDiaResourceTest.php`

**Interfaces:**
- Consumes: `App\Models\AgendaDia` (table `agenda_dias`; fillable `['data','reflexao','meta_mes_texto','meta_dia_titulo','meta_dia_texto','prece','status','wp_id']`; cast `data:'date'`; consts `STATUS_PUBLICADO='publicado'`, `STATUS_RASCUNHO='rascunho'`; mutators de sanitização via `clean($v,'conteudo')`; factory `AgendaDiaFactory` — status publicado, data única); `Filament\Forms\Components\DatePicker` (novo no repo, padrão Filament 5); RichEditor simples no estilo `PalestraResource::descricao`.
- Produces: Resource Filament CRUD; página `App\Filament\Resources\Agenda\Pages\CreateAgendaDia` (usada nos testes Livewire).

Passos:

- [ ] **Step 1: escrever o teste que falha (cria dia + rejeita data duplicada).** Cria `tests/Feature/Filament/AgendaDiaResourceTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Resources\Agenda\Pages\CreateAgendaDia;
use App\Models\AgendaDia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaDiaResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_cria_dia(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-01',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Reflexão do dia.</p>',
                'meta_dia_titulo' => 'Desenvolver Abnegação',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('agenda_dias', [
            'data' => '2026-05-01',
            'status' => AgendaDia::STATUS_PUBLICADO,
            'meta_dia_titulo' => 'Desenvolver Abnegação',
        ]);
    }

    public function test_rejeita_data_duplicada(): void
    {
        AgendaDia::factory()->create(['data' => '2026-05-01']);

        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-01',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Outra reflexão para a mesma data.</p>',
            ])
            ->call('create')
            ->assertHasFormErrors(['data']);
    }
}
```

- [ ] **Step 2: rodar o teste e ver falhar (classe de página inexistente).**
  Run: `docker exec cema-app php artisan test --filter=AgendaDiaResourceTest`
  Expected: erro `Class "App\Filament\Resources\Agenda\Pages\CreateAgendaDia" not found` (nenhum teste passa).

- [ ] **Step 3: criar o Resource com DatePicker, RichEditors, status e tabela.** Cria `app/Filament/Resources/Agenda/AgendaDiaResource.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda;

use App\Filament\Resources\Agenda\Pages\CreateAgendaDia;
use App\Filament\Resources\Agenda\Pages\EditAgendaDia;
use App\Filament\Resources\Agenda\Pages\ListAgendaDias;
use App\Models\AgendaDia;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AgendaDiaResource extends Resource
{
    protected static ?string $model = AgendaDia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'Dia da agenda';

    protected static ?string $pluralModelLabel = 'Dias da agenda';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                DatePicker::make('data')
                    ->label('Data')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->unique(table: 'agenda_dias', column: 'data', ignoreRecord: true),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options([
                        AgendaDia::STATUS_PUBLICADO => 'Publicado',
                        AgendaDia::STATUS_RASCUNHO => 'Rascunho',
                    ])
                    ->default(AgendaDia::STATUS_PUBLICADO),
            ]),
            // HTML cru; a sanitização (clean $v,'conteudo') vem do mutator do model.
            RichEditor::make('reflexao')
                ->label('Reflexão e Vivência (Evangelho)')
                ->columnSpanFull(),
            RichEditor::make('meta_mes_texto')
                ->label('Meta do Mês — citação do dia')
                ->columnSpanFull(),
            TextInput::make('meta_dia_titulo')
                ->label('Meta do Dia — título')
                ->maxLength(255)
                ->columnSpanFull(),
            RichEditor::make('meta_dia_texto')
                ->label('Meta do Dia — texto')
                ->columnSpanFull(),
            RichEditor::make('prece')
                ->label('Sugestão de Prece')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('meta_dia_titulo')
                    ->label('Meta do Dia')
                    ->placeholder('—')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === AgendaDia::STATUS_PUBLICADO ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        AgendaDia::STATUS_PUBLICADO => 'Publicado',
                        AgendaDia::STATUS_RASCUNHO => 'Rascunho',
                    ]),
            ])
            ->defaultSort('data', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListAgendaDias::route('/'),
            'create' => CreateAgendaDia::route('/create'),
            'edit' => EditAgendaDia::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: criar as três Pages (List/Create/Edit).**
  `app/Filament/Resources/Agenda/Pages/ListAgendaDias.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaDiaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAgendaDias extends ListRecords
{
    protected static string $resource = AgendaDiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```
  `app/Filament/Resources/Agenda/Pages/CreateAgendaDia.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaDiaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgendaDia extends CreateRecord
{
    protected static string $resource = AgendaDiaResource::class;
}
```
  `app/Filament/Resources/Agenda/Pages/EditAgendaDia.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Resources\Agenda\Pages;

use App\Filament\Resources\Agenda\AgendaDiaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgendaDia extends EditRecord
{
    protected static string $resource = AgendaDiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 5: rodar o teste e ver passar (verde).**
  Run: `docker exec cema-app php artisan test --filter=AgendaDiaResourceTest`
  Expected: `OK (2 tests, ...)` — `test_cria_dia` e `test_rejeita_data_duplicada` passam.

- [ ] **Step 6: Pint + commit.**
  Run: `docker exec cema-app ./vendor/bin/pint app/Filament/Resources/Agenda tests/Feature/Filament/AgendaDiaResourceTest.php`
  Expected: sem erros de estilo. Depois:
```
git add app/Filament/Resources/Agenda/AgendaDiaResource.php \
        app/Filament/Resources/Agenda/Pages/ListAgendaDias.php \
        app/Filament/Resources/Agenda/Pages/CreateAgendaDia.php \
        app/Filament/Resources/Agenda/Pages/EditAgendaDia.php \
        tests/Feature/Filament/AgendaDiaResourceTest.php
git commit -m "feat(agenda/admin): AgendaDiaResource com DatePicker, RichEditors e status"
```

---

Notas de implementação (verificadas nos moldes reais):
- Namespaces Filament 5 confirmados nos moldes: `Filament\Schemas\Schema`, `Filament\Schemas\Components\{Grid,Utilities\Get}`, `Filament\Forms\Components\{TextInput,Select,RichEditor,DatePicker}`, `Filament\Tables\{Table,Columns\TextColumn,Filters\SelectFilter}`, `Filament\Actions\{EditAction,DeleteAction,BulkActionGroup,DeleteBulkAction,CreateAction}`, `Filament\Resources\Resource`, `Filament\Resources\Pages\{ListRecords,CreateRecord,EditRecord}`, `Filament\Support\Icons\Heroicon`.
- Resources são auto-descobertos via `->discoverResources(in: app_path('Filament/Resources'), ...)` em `app/Providers/Filament/AdminPanelProvider.php:58` — a subpasta `Agenda/` é varrida sem registro manual.
- Unicidade composta `(ano, mes)`: a regra `Rule::unique('agenda_metas_mes', 'mes')->where('ano', $get('ano'))->ignore($record)` é aplicada no campo `mes` (por isso o erro de formulário aparece na chave `mes`, e o teste usa `assertHasFormErrors(['mes'])`). O `?Model $record` é injetado pelo `evaluate()` do Filament (mesmo mecanismo do `Get $get` visto em `PalestraResource.php:96`); import `Illuminate\Database\Eloquent\Model` e `Illuminate\Validation\Rule`. Complementa o índice `unique(ano,mes)` da migration (Fase A).
- `defaultSort` só ordena por uma coluna; para `ano desc, mes desc` uso `->modifyQueryUsing(fn (Builder $query) => ...)` (import `Illuminate\Database\Eloquent\Builder`). Em `AgendaDiaResource` basta `->defaultSort('data','desc')`.
- `DatePicker->unique(table:, column:, ignoreRecord:true)` segue exatamente a assinatura já usada em `PalestraResource.php:67` para o slug; erro de formulário na chave `data`.
- RichEditors "simples" replicam `PalestraResource::descricao` (só `->label()` + `->columnSpanFull()`, sem toolbar custom). A sanitização não fica no Resource: vem dos mutators `clean($v,'conteudo')` do `AgendaDia` (Fase A).
- `setUp()` dos testes espelha `AssuntoResourceTest.php` (`actingAs(User::factory()->create())`), coerente com o gate `User::canAccessPanel` liberado em local/testing.
- Autoria: incluí o cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02` em todos os arquivos PHP novos (Resources, Pages e testes), conforme regra de output do brief; os moldes de Pages não têm cabeçalho, mas a regra do brief é explícita e prevalece.

---

I have all the molds. Here are the four tasks for Fase D.

---

## Fase D — Front público SSR crawlável (Agenda Reforma Íntima)

> **Ordem de execução dentro da fatia:** a Task 9 (controller) consome `App\Support\Agenda\CalendarioAgenda::matriz()`, que é criada na **Task 10**. Portanto, ao executar, faça a **Task 10 antes do passo verde da Task 9** (ou crie o arquivo `CalendarioAgenda.php` da Task 10 primeiro). As Tasks 9 e 10 são individualmente TDD; só o encadeamento pede essa ordem.
> **Pré-requisitos das fases A–C (já entregues):** models `App\Models\AgendaDia` (scope `publicado()`, `metaMes()`, `tituloExtenso()`, `descricaoSeo()`, mutators, cast `data:date`, consts de status), `AgendaMetaMes`, `AgendaSlugLegado` e respectivas factories.

---

### Task 9: Rotas + `AgendaController` + ativação do item de nav "Agenda"

**Files:**
- Create: `app/Http/Controllers/AgendaController.php`
- Create (stub, substituída na Task 11): `resources/views/agenda/index.blade.php`
- Modify: `routes/web.php` (rotas `agenda.index`, `agenda.show` + 301 do arquivo e por-slug)
- Modify: `config/navegacao.php` (ativar item 'Agenda', linha ~27)
- Test: `tests/Feature/Front/AgendaRotaTest.php`

**Interfaces:**
- Consumes: `App\Models\AgendaDia` (scope `publicado()`, cast `data:date`, `metaMes()`, `descricaoSeo()`), `App\Models\AgendaSlugLegado` (`where('slug',…)->value('data')` → `?Carbon`), `App\Support\Agenda\CalendarioAgenda::matriz(int,int,Carbon,?string,array): array` (Task 10).
- Produces: `AgendaController::index(): View` (hoje Brasília, `ehUrlNua=true`), `AgendaController::show(string $data): View` (valida data real → `abort(404)` se inválida). Ambos renderizam `view('agenda.index', […])` com `$dia,$metaMes,$matriz,$diaAnterior,$diaProximo,$mesAnterior,$mesProximo,$ehUrlNua,$hojeBrasilia,$dataAtual,$temConteudo`. Rotas nomeadas `agenda.index` (GET `/agenda-reforma-intima`) e `agenda.show` (GET `/agenda-reforma-intima/{data}` where `\d{4}-\d{2}-\d{2}`).

**Passos:**

- [ ] **Step 1: Escrever o teste de rotas/301 que falha.** Cria `tests/Feature/Front/AgendaRotaTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use App\Models\AgendaSlugLegado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaRotaTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_url_nua_resolve_para_a_rota_esperada(): void
    {
        $this->assertSame(url('/agenda-reforma-intima'), route('agenda.index'));
    }

    public function test_url_nua_responde_200(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão de hoje</p>']);

        $this->get('/agenda-reforma-intima')->assertOk();
    }

    public function test_show_exibe_o_dia_com_a_reflexao_no_html(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Semeai a paz no coração</p>']);

        $this->get('/agenda-reforma-intima/2026-07-10')
            ->assertOk()
            ->assertSee('Semeai a paz no coração');
    }

    public function test_dia_futuro_com_conteudo_e_legivel(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-12-25', 'reflexao' => '<p>Natal de luz</p>']);

        $this->get('/agenda-reforma-intima/2026-12-25')
            ->assertOk()
            ->assertSee('Natal de luz');
    }

    public function test_data_valida_sem_conteudo_responde_200(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));

        $this->get('/agenda-reforma-intima/2026-07-11')->assertOk();
    }

    public function test_data_invalida_retorna_404(): void
    {
        $this->get('/agenda-reforma-intima/2026-13-45')->assertNotFound();
    }

    public function test_url_antiga_do_arquivo_redireciona_301(): void
    {
        $this->get('/agenda-reforma')->assertStatus(301)->assertRedirect('/agenda-reforma-intima');
    }

    public function test_slug_legado_numerico_redireciona_301(): void
    {
        AgendaSlugLegado::factory()->create(['slug' => '27057', 'data' => '2026-05-10']);

        $this->get('/agenda-reforma/27057')
            ->assertStatus(301)
            ->assertRedirect('/agenda-reforma-intima/2026-05-10');
    }

    public function test_slug_legado_de_data_redireciona_301(): void
    {
        AgendaSlugLegado::factory()->create(['slug' => '02-de-julho-de-2026', 'data' => '2026-07-02']);

        $this->get('/agenda-reforma/02-de-julho-de-2026')
            ->assertStatus(301)
            ->assertRedirect('/agenda-reforma-intima/2026-07-02');
    }

    public function test_slug_legado_inexistente_retorna_404(): void
    {
        $this->get('/agenda-reforma/nao-existe')->assertNotFound();
    }
}
```

  Run: `docker exec cema-app php artisan test --filter=AgendaRotaTest`
  Expected: FAIL — `Route [agenda.index] not defined` / 404 nas rotas ainda inexistentes.

- [ ] **Step 2: Criar o `AgendaController` (index/show + montagem da página).** Cria `app/Http/Controllers/AgendaController.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Http\Controllers;

use App\Models\AgendaDia;
use App\Support\Agenda\CalendarioAgenda;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    /** URL nua = "hoje" de Brasília (evergreen, canônica de si). Habilita o script de fuso. */
    public function index(): View
    {
        return $this->montarPagina(Carbon::today(), ehUrlNua: true);
    }

    /** Página datada. Valida a data de verdade (a regex aceita 2026-13-45) → 404 em vez de 500. */
    public function show(string $data): View
    {
        $dataAtual = rescue(fn () => Carbon::createFromFormat('!Y-m-d', $data), null, false);
        abort_if($dataAtual === null || $dataAtual->format('Y-m-d') !== $data, 404);

        return $this->montarPagina($dataAtual, ehUrlNua: false);
    }

    /** Monta o payload SSR compartilhado por index/show (card do dia + matriz + navegação). */
    private function montarPagina(Carbon $dataAtual, bool $ehUrlNua): View
    {
        $hojeBrasilia = Carbon::today();
        $ymd = $dataAtual->format('Y-m-d');

        $dia = AgendaDia::publicado()->where('data', $ymd)->first();
        $metaMes = $dia?->metaMes();
        $temConteudo = $dia !== null;

        $inicioMes = $dataAtual->copy()->startOfMonth();
        $fimMes = $dataAtual->copy()->endOfMonth();

        $datasComConteudo = AgendaDia::publicado()
            ->whereBetween('data', [$inicioMes->toDateString(), $fimMes->toDateString()])
            ->get(['data'])
            ->map(fn (AgendaDia $d) => $d->data->format('Y-m-d'))
            ->all();

        $matriz = CalendarioAgenda::matriz(
            (int) $dataAtual->year,
            (int) $dataAtual->month,
            $hojeBrasilia,
            $ymd,
            $datasComConteudo,
        );

        // Dia anterior/próximo COM conteúdo (navegação de setas do card).
        $diaAnterior = AgendaDia::publicado()->where('data', '<', $ymd)
            ->orderByDesc('data')->first()?->data->format('Y-m-d');
        $diaProximo = AgendaDia::publicado()->where('data', '>', $ymd)
            ->orderBy('data')->first()?->data->format('Y-m-d');

        // Mês anterior/próximo: primeiro dia COM conteúdo de cada.
        $refMesAnt = AgendaDia::publicado()->where('data', '<', $inicioMes->toDateString())
            ->orderByDesc('data')->first();
        $mesAnterior = $refMesAnt
            ? AgendaDia::publicado()
                ->whereYear('data', $refMesAnt->data->year)
                ->whereMonth('data', $refMesAnt->data->month)
                ->orderBy('data')->first()?->data->format('Y-m-d')
            : null;
        // Ordenado asc, o primeiro dia > fimMes já é o 1º dia com conteúdo do próximo mês com agenda.
        $mesProximo = AgendaDia::publicado()->where('data', '>', $fimMes->toDateString())
            ->orderBy('data')->first()?->data->format('Y-m-d');

        return view('agenda.index', [
            'dia' => $dia,
            'metaMes' => $metaMes,
            'matriz' => $matriz,
            'diaAnterior' => $diaAnterior,
            'diaProximo' => $diaProximo,
            'mesAnterior' => $mesAnterior,
            'mesProximo' => $mesProximo,
            'ehUrlNua' => $ehUrlNua,
            'hojeBrasilia' => $hojeBrasilia,
            'dataAtual' => $dataAtual,
            'temConteudo' => $temConteudo,
        ]);
    }
}
```

- [ ] **Step 3: Criar a view stub `agenda/index.blade.php` (substituída na Task 11).** Cria `resources/views/agenda/index.blade.php`:

```blade
{{-- Stub da Agenda Reforma Íntima — a casca rica vem na Task 11. --}}
<x-layout.app
    title="Agenda Reforma Íntima — {{ $dataAtual->format('d/m/Y') }}"
    :description="$dia?->descricaoSeo()">
    <x-slot:head>
        @unless ($temConteudo)
            <meta name="robots" content="noindex">
        @endunless
    </x-slot:head>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        @if ($temConteudo)
            <article>{!! $dia->reflexao !!}</article>
        @else
            <p>Não há reflexão publicada para esta data.</p>
            <a href="{{ route('agenda.index') }}" wire:navigate>Voltar para hoje</a>
        @endif
    </section>
</x-layout.app>
```

- [ ] **Step 4: Registrar as rotas + 301 em `routes/web.php`.** Adiciona o import do controller no topo e o bloco de rotas **antes** do catch-all `/{slug}` (a rota estática `/agenda-reforma-intima` já precede `{data}`). Import:

```php
use App\Http\Controllers\AgendaController;
```

  Bloco de rotas (inserir após as rotas do blog, antes do sitemap/catch-all):

```php
// Agenda Reforma Íntima (devocional diário). Estáticas antes de {data}.
Route::get('/agenda-reforma-intima', [AgendaController::class, 'index'])->name('agenda.index');
Route::get('/agenda-reforma-intima/{data}', [AgendaController::class, 'show'])
    ->name('agenda.show')
    ->where('data', '\d{4}-\d{2}-\d{2}');

// Compat: URLs antigas do WP → 301 para as URLs datadas novas.
Route::permanentRedirect('/agenda-reforma', '/agenda-reforma-intima');
Route::get('/agenda-reforma/{slug}', function (string $slug) {
    $data = \App\Models\AgendaSlugLegado::where('slug', $slug)->value('data');
    abort_if($data === null, 404);

    return redirect()->route('agenda.show', $data->format('Y-m-d'), 301);
})->where('slug', '[a-z0-9-]+'); // slug numérico (maio) OU de data (jun-ago)
```

- [ ] **Step 5: Ativar o item 'Agenda' no menu global.** Em `config/navegacao.php` (linha ~27), trocar:

```php
        ['rotulo' => 'Agenda', 'ativo' => false, 'itens' => []],
```

  por:

```php
        ['rotulo' => 'Agenda', 'rota' => 'agenda.index', 'ativo' => true, 'itens' => []],
```

- [ ] **Step 6: Rodar o teste e ver passar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaRotaTest`
  Expected: OK (10 passed) — index/show 200, futuro 200, sem-conteúdo 200, `2026-13-45` → 404, 301 do arquivo e dos slugs numérico/de-data, slug inexistente 404.

- [ ] **Step 7: Commit.**
  Run: `git add app/Http/Controllers/AgendaController.php resources/views/agenda/index.blade.php routes/web.php config/navegacao.php tests/Feature/Front/AgendaRotaTest.php && git commit -m "feat(agenda/front): rotas agenda.index/show, controller SSR e 301 do legado"`

---

### Task 10: `App\Support\Agenda\CalendarioAgenda::matriz()` + teste unitário

**Files:**
- Create: `app/Support/Agenda/CalendarioAgenda.php`
- Test: `tests/Unit/Support/Agenda/CalendarioAgendaTest.php`

**Interfaces:**
- Consumes: `\Carbon\Carbon $hoje`, `?string $selecionada`, `array $datasComConteudo` (set de `'Y-m-d'`).
- Produces: `CalendarioAgenda::matriz(int $ano, int $mes, Carbon $hoje, ?string $selecionada, array $datasComConteudo): array` → `['diasVazios'=>int, 'dias'=>list<['dia'=>int,'ymd'=>string,'temConteudo'=>bool,'hoje'=>bool,'selecionado'=>bool]>]`. `diasVazios` = offset do 1º dia com semana iniciando no domingo (`dayOfWeek`).

**Passos:**

- [ ] **Step 1: Escrever o teste unitário que falha.** Cria `tests/Unit/Support/Agenda/CalendarioAgendaTest.php`:

```php
<?php

namespace Tests\Unit\Support\Agenda;

use App\Support\Agenda\CalendarioAgenda;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_offset_do_primeiro_dia_do_mes(): void
    {
        // 01/07/2026 é uma quarta-feira → offset 3 (domingo=0).
        $matriz = CalendarioAgenda::matriz(2026, 7, Carbon::create(2026, 7, 15), null, []);

        $this->assertSame(3, $matriz['diasVazios']);
        $this->assertCount(31, $matriz['dias']);
    }

    public function test_offset_zero_quando_mes_comeca_no_domingo(): void
    {
        // 01/02/2026 é domingo → offset 0.
        $matriz = CalendarioAgenda::matriz(2026, 2, Carbon::create(2026, 2, 10), null, []);

        $this->assertSame(0, $matriz['diasVazios']);
        $this->assertCount(28, $matriz['dias']);
    }

    public function test_ymd_e_estrutura_de_cada_celula(): void
    {
        $matriz = CalendarioAgenda::matriz(2026, 7, Carbon::create(2026, 7, 15), null, []);
        $primeiro = $matriz['dias'][0];

        $this->assertSame(1, $primeiro['dia']);
        $this->assertSame('2026-07-01', $primeiro['ymd']);
        $this->assertArrayHasKey('temConteudo', $primeiro);
        $this->assertArrayHasKey('hoje', $primeiro);
        $this->assertArrayHasKey('selecionado', $primeiro);
    }

    public function test_marca_hoje_selecionado_e_conteudo(): void
    {
        $matriz = CalendarioAgenda::matriz(
            2026, 7,
            Carbon::create(2026, 7, 15),
            '2026-07-10',
            ['2026-07-10', '2026-07-20'],
        );

        $dias = collect($matriz['dias'])->keyBy('dia');

        $this->assertTrue($dias[10]['temConteudo']);
        $this->assertTrue($dias[10]['selecionado']);
        $this->assertFalse($dias[10]['hoje']);

        $this->assertTrue($dias[15]['hoje']);
        $this->assertFalse($dias[15]['selecionado']);
        $this->assertFalse($dias[15]['temConteudo']);

        $this->assertTrue($dias[20]['temConteudo']);
        $this->assertFalse($dias[11]['temConteudo']);
    }

    public function test_nao_marca_hoje_quando_o_mes_exibido_nao_e_o_corrente(): void
    {
        // Exibindo agosto/2026, mas "hoje" é 15/07/2026 → nenhuma célula é hoje.
        $matriz = CalendarioAgenda::matriz(2026, 8, Carbon::create(2026, 7, 15), null, []);

        $this->assertEmpty(collect($matriz['dias'])->firstWhere('hoje', true));
    }
}
```

  Run: `docker exec cema-app php artisan test --filter=CalendarioAgendaTest`
  Expected: FAIL — `Class "App\Support\Agenda\CalendarioAgenda" not found`.

- [ ] **Step 2: Implementar `CalendarioAgenda::matriz()`.** Cria `app/Support/Agenda/CalendarioAgenda.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Support\Agenda;

use Carbon\Carbon;

class CalendarioAgenda
{
    /**
     * Monta a grade mensal (semana iniciando no domingo) para o SSR do calendário.
     *
     * @param  array<int, string>  $datasComConteudo  set de 'Y-m-d' dos dias publicados no mês
     * @return array{diasVazios:int, dias:list<array{dia:int, ymd:string, temConteudo:bool, hoje:bool, selecionado:bool}>}
     */
    public static function matriz(int $ano, int $mes, Carbon $hoje, ?string $selecionada, array $datasComConteudo): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $diasVazios = $primeiro->dayOfWeek; // 0=domingo … 6=sábado

        $comConteudo = array_flip($datasComConteudo); // lookup O(1) por 'Y-m-d'
        $ehMesCorrente = (int) $hoje->year === $ano && (int) $hoje->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $ymd = sprintf('%04d-%02d-%02d', $ano, $mes, $d);
            $dias[] = [
                'dia' => $d,
                'ymd' => $ymd,
                'temConteudo' => isset($comConteudo[$ymd]),
                'hoje' => $ehMesCorrente && (int) $hoje->day === $d,
                'selecionado' => $selecionada === $ymd,
            ];
        }

        return ['diasVazios' => $diasVazios, 'dias' => $dias];
    }
}
```

- [ ] **Step 3: Rodar o teste e ver passar.**
  Run: `docker exec cema-app php artisan test --filter=CalendarioAgendaTest`
  Expected: OK (5 passed).

- [ ] **Step 4: Commit.**
  Run: `git add app/Support/Agenda/CalendarioAgenda.php tests/Unit/Support/Agenda/CalendarioAgendaTest.php && git commit -m "feat(agenda/front): helper CalendarioAgenda::matriz (offset domingo, hoje/selecionado/conteudo)"`

---

### Task 11: Casca rica + parciais `_dia`/`_calendario` + `agenda.css` (SSR crawlável)

**Files:**
- Modify (substitui o stub): `resources/views/agenda/index.blade.php`
- Create: `resources/views/agenda/_dia.blade.php`
- Create: `resources/views/agenda/_calendario.blade.php`
- Create: `resources/css/agenda.css`
- Modify: `resources/css/app.css` (`@import './agenda.css';`)
- Test: `tests/Feature/Front/AgendaCrawlavelTest.php`

**Interfaces:**
- Consumes (da view): `$dia,$metaMes,$matriz,$diaAnterior,$diaProximo,$mesAnterior,$mesProximo,$ehUrlNua,$hojeBrasilia,$dataAtual,$temConteudo` (Task 9); `<x-layout.app :title :description>` + `<x-slot:head>`; `<x-ui.particulas>`.
- Produces: HTML SSR onde células com conteúdo e setas são `<a href="{{ route('agenda.show',$ymd) }}" wire:navigate>` (crawláveis) e dias sem conteúdo são `<span>` inertes; link do nav 'Agenda' no header.

**Passos:**

- [ ] **Step 1: Escrever o teste de crawlabilidade + link de nav que falha.** Cria `tests/Feature/Front/AgendaCrawlavelTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaCrawlavelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dia_com_conteudo_vira_link_e_dia_sem_conteudo_fica_inerte(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão do dia</p>']);

        $resp = $this->get('/agenda-reforma-intima/2026-07-10')->assertOk();

        // Célula com conteúdo = âncora crawlável para a URL datada, com wire:navigate.
        $resp->assertSee('/agenda-reforma-intima/2026-07-10', false);
        $resp->assertSee('wire:navigate', false);

        // Dia 11 (sem conteúdo) NÃO gera link (é <span> inerte).
        $resp->assertDontSee('/agenda-reforma-intima/2026-07-11', false);
    }

    public function test_link_da_agenda_presente_no_header(): void
    {
        $this->get('/')->assertSee('href="'.route('agenda.index').'"', false);
    }
}
```

  Run: `docker exec cema-app php artisan test --filter=AgendaCrawlavelTest`
  Expected: FAIL — o stub não renderiza o calendário, então `/agenda-reforma-intima/2026-07-10` (como célula) não aparece.

- [ ] **Step 2: Criar o parcial do calendário `agenda/_calendario.blade.php`.**

```blade
{{-- Calendário mensal SSR: célula com conteúdo = <a> crawlável; sem conteúdo = <span> inerte. --}}
@php($tituloMes = \Illuminate\Support\Str::ucfirst($dataAtual->translatedFormat('F \d\e Y')))
<section class="agenda-cal rounded-2xl border border-border-muted bg-white p-4 shadow-card" aria-label="Calendário do mês">
    <div class="mb-3 flex items-center justify-between gap-2">
        @if ($mesAnterior)
            <a href="{{ route('agenda.show', $mesAnterior) }}" wire:navigate aria-label="Mês anterior"
               class="agenda-cal-seta grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary">‹</a>
        @else
            <span class="grid size-9 place-items-center rounded-full border border-border-muted text-text-muted opacity-40" aria-hidden="true">‹</span>
        @endif

        <h2 class="font-display font-semibold text-text-ink">{{ $tituloMes }}</h2>

        @if ($mesProximo)
            <a href="{{ route('agenda.show', $mesProximo) }}" wire:navigate aria-label="Próximo mês"
               class="agenda-cal-seta grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary">›</a>
        @else
            <span class="grid size-9 place-items-center rounded-full border border-border-muted text-text-muted opacity-40" aria-hidden="true">›</span>
        @endif
    </div>

    <div class="grid grid-cols-7 gap-1 text-center">
        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $i => $inicial)
            <span class="py-1 font-mono text-[11px] font-semibold text-text-muted" aria-hidden="true">{{ $inicial }}</span>
        @endforeach

        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
            <span aria-hidden="true"></span>
        @endfor

        @foreach ($matriz['dias'] as $celula)
            @if ($celula['temConteudo'])
                <a href="{{ route('agenda.show', $celula['ymd']) }}" wire:navigate
                   @class([
                       'agenda-dia agenda-dia--conteudo',
                       'agenda-dia--sel' => $celula['selecionado'],
                       'agenda-dia--hoje' => $celula['hoje'],
                   ])
                   @if ($celula['hoje']) aria-current="date" @endif
                   aria-label="{{ $celula['dia'] }} — ver reflexão">{{ $celula['dia'] }}</a>
            @else
                <span @class([
                          'agenda-dia agenda-dia--vazio',
                          'agenda-dia--hoje' => $celula['hoje'],
                      ])
                      @if ($celula['hoje']) aria-current="date" @endif>{{ $celula['dia'] }}</span>
            @endif
        @endforeach
    </div>

    <div class="mt-3 flex items-center gap-4 text-[11px] text-text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full bg-gold"></span> Com reflexão</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full ring-2 ring-primary"></span> Hoje</span>
    </div>
</section>
```

- [ ] **Step 3: Criar o parcial do card do dia `agenda/_dia.blade.php`.**

```blade
{{-- Card do dia: cabeçalho com setas prev/próximo (crawláveis), 4 blocos SSR e compartilhar. --}}
@php
    $ehHoje = $dataAtual->toDateString() === $hojeBrasilia->toDateString();
    $urlAtual = $ehUrlNua ? route('agenda.index') : route('agenda.show', $dataAtual->format('Y-m-d'));
    $textoCompartilhar = trim(implode("\n\n", array_filter([
        'Agenda Reforma Íntima — '.$dia->tituloExtenso(),
        trim(strip_tags((string) $dia->reflexao)),
        filled($dia->meta_dia_texto) ? trim(strip_tags((string) $dia->meta_dia_texto)) : null,
        filled($dia->prece) ? trim(strip_tags((string) $dia->prece)) : null,
        'CEMA — Centro Espírita Maria Madalena',
    ])));
@endphp
<article class="agenda-card overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
    <header class="agenda-card-topo flex items-center justify-between gap-4 bg-gradient-to-br from-primary to-footer-bg px-6 py-5 text-white">
        @if ($diaAnterior)
            <a href="{{ route('agenda.show', $diaAnterior) }}" wire:navigate aria-label="Dia anterior com conteúdo"
               class="grid size-10 shrink-0 place-items-center rounded-full border border-white/20 text-xl transition hover:bg-white/10">‹</a>
        @else
            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-white/10 text-xl opacity-40" aria-hidden="true">‹</span>
        @endif

        <div class="min-w-0 text-center">
            <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-gold">Devocional do dia</p>
            <h2 class="mt-1 font-display text-xl font-semibold leading-tight sm:text-2xl">{{ $dia->tituloExtenso() }}</h2>
            @unless ($ehHoje)
                <a href="{{ route('agenda.index') }}" wire:navigate
                   class="mt-2 inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-[11px] font-semibold text-[#3a2f00] transition hover:opacity-90">↺ Voltar para hoje</a>
            @endunless
        </div>

        @if ($diaProximo)
            <a href="{{ route('agenda.show', $diaProximo) }}" wire:navigate aria-label="Próximo dia com conteúdo"
               class="grid size-10 shrink-0 place-items-center rounded-full border border-white/20 text-xl transition hover:bg-white/10">›</a>
        @else
            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-white/10 text-xl opacity-40" aria-hidden="true">›</span>
        @endif
    </header>

    <div class="agenda-card-corpo px-6 py-6 sm:px-8">
        @if (filled($dia->reflexao))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Reflexão e Vivência do Evangelho</h3>
                <div class="agenda-prosa">{!! $dia->reflexao !!}</div>
            </section>
        @endif

        @if ($metaMes || filled($dia->meta_mes_texto))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Meta do Mês</h3>
                @if ($metaMes)
                    <p class="agenda-subtitulo">{{ $metaMes->titulo }}</p>
                @endif
                @if (filled($dia->meta_mes_texto))
                    <div class="agenda-prosa">{!! $dia->meta_mes_texto !!}</div>
                @endif
            </section>
        @endif

        @if (filled($dia->meta_dia_titulo) || filled($dia->meta_dia_texto))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Meta do Dia</h3>
                @if (filled($dia->meta_dia_titulo))
                    <p class="agenda-subtitulo">{{ $dia->meta_dia_titulo }}</p>
                @endif
                @if (filled($dia->meta_dia_texto))
                    <div class="agenda-prosa">{!! $dia->meta_dia_texto !!}</div>
                @endif
            </section>
        @endif

        @if (filled($dia->prece))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Sugestão de Prece</h3>
                <div class="agenda-prosa">{!! $dia->prece !!}</div>
            </section>
        @endif
    </div>

    <footer class="agenda-compartilhar border-t border-border-muted bg-cream px-6 py-5 sm:px-8"
            x-data="{ url: @js($urlAtual), texto: @js($textoCompartilhar), copiado: false,
                copiar() { navigator.clipboard.writeText(this.url).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000); }); },
                nativo() { if (navigator.share) { navigator.share({ title: 'Agenda Reforma Íntima', text: this.texto, url: this.url }); } } }">
        <p class="mb-3 text-sm text-text-muted">Compartilhar:</p>
        <div class="flex flex-wrap items-center gap-2.5">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white" aria-hidden="true">f</span> Facebook
            </a>
            <a href="https://wa.me/?text={{ urlencode($textoCompartilhar."\n".$urlAtual) }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white" aria-hidden="true">W</span> WhatsApp
            </a>
            <button type="button" @click="copiar()"
                    class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
            </button>
            <button type="button" x-cloak x-show="navigator.share" @click="nativo()"
                    class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">Compartilhar…</button>
        </div>
    </footer>
</article>
```

- [ ] **Step 4: Substituir a casca `agenda/index.blade.php` pela versão rica.**

```blade
{{-- Agenda Reforma Íntima — casca SSR (card do dia + calendário navegável). --}}
@php
    $tituloPagina = 'Agenda Reforma Íntima — '.$dataAtual->format('d/m/Y');
    $canonical = $ehUrlNua ? route('agenda.index') : route('agenda.show', $dataAtual->format('Y-m-d'));

    if ($temConteudo) {
        $org = ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'];
        $graph = [
            array_filter([
                '@type' => 'Article',
                'headline' => $tituloPagina,
                'datePublished' => $dia->data->toIso8601String(),
                'dateModified' => $dia->updated_at?->toIso8601String(),
                'articleBody' => trim(strip_tags((string) $dia->reflexao)) ?: null,
                'inLanguage' => 'pt-BR',
                'author' => $org,
                'publisher' => $org,
                'mainEntityOfPage' => $canonical,
                'description' => $dia->descricaoSeo() ?: null,
            ], fn ($v) => $v !== null),
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Agenda Reforma Íntima', 'item' => route('agenda.index')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $dia->tituloExtenso(), 'item' => $canonical],
                ],
            ],
        ];
        $jsonLd = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
        );
    }
@endphp

<x-layout.app :title="$tituloPagina" :description="$dia?->descricaoSeo()">
    <x-slot:head>
        <link rel="canonical" href="{{ $canonical }}">
        @unless ($temConteudo)
            <meta name="robots" content="noindex">
        @endunless
        @if ($temConteudo)
            <script type="application/ld+json">{!! $jsonLd !!}</script>
        @endif
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Devocional diário</p>
            <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Agenda Reforma Íntima</h1>
            <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
            <p class="mt-4 max-w-xl font-light text-[#d7def0]">Uma reflexão à luz do Evangelho para cada dia — com meta do mês, meta do dia e sugestão de prece.</p>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Agenda Reforma Íntima</span>
        </div>
    </nav>

    {{-- Card do dia + calendário --}}
    <section class="bg-surface">
        <div class="mx-auto grid max-w-[1240px] gap-8 px-6 py-12 desktop-sm:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                @if ($temConteudo)
                    @include('agenda._dia')
                @else
                    <div class="agenda-vazio rounded-2xl border border-dashed border-border-muted bg-white px-6 py-16 text-center">
                        <p class="text-4xl" aria-hidden="true">🕊️</p>
                        <p class="mt-3 text-lg font-semibold text-text-secondary">Não há reflexão publicada para {{ \Illuminate\Support\Str::ucfirst($dataAtual->translatedFormat('l, d \d\e F \d\e Y')) }}.</p>
                        <a href="{{ route('agenda.index') }}" wire:navigate
                           class="mt-4 inline-flex rounded-pill bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90">Voltar para hoje</a>
                    </div>
                @endif
            </div>
            <aside class="min-w-0">
                @include('agenda._calendario')
            </aside>
        </div>
    </section>

    {{-- Sobre o projeto --}}
    <section class="bg-cream">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <h2 class="font-display text-lg font-semibold text-primary">Sobre a Agenda Reforma Íntima</h2>
            <p class="mt-3 max-w-3xl font-serif text-[15px] leading-[1.8] text-[#3a3553]">A Agenda Reforma Íntima é um devocional diário editado pela Editora Auta de Sousa. A cada data, uma reflexão à luz do Evangelho, uma meta do mês, uma meta do dia e uma sugestão de prece — um roteiro simples para o trabalho de autotransformação moral.</p>
        </div>
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16 pt-12">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestras Públicas', route('palestras.index')], ['Calendário de Palestras', route('palestras.calendario')], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Fuso do visitante (só na URL nua): navega para a URL datada local, sem trocar o conteúdo da nua. --}}
    @if ($ehUrlNua)
        <script>
            (function () {
                var local = new Date().toLocaleDateString('en-CA'); // 'AAAA-MM-DD' no fuso do navegador
                var brasilia = @json($hojeBrasilia->format('Y-m-d'));
                if (local !== brasilia) {
                    location.replace(@json(url('/agenda-reforma-intima')) + '/' + local);
                }
            })();
        </script>
    @endif
</x-layout.app>
```

- [ ] **Step 5: Criar `resources/css/agenda.css`.**

```css
/* Agenda Reforma Íntima — front público. Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02 */

@layer components {
    /* Célula do calendário */
    .agenda-dia {
        display: grid;
        place-items: center;
        width: 36px;
        height: 36px;
        margin: 0 auto;
        border-radius: 9999px;
        font-size: 13px;
        color: var(--color-text-secondary);
    }
    .agenda-dia--vazio {
        color: var(--color-text-muted);
    }
    .agenda-dia--conteudo {
        color: #3a2f00;
        font-weight: 700;
        background: radial-gradient(circle at 30% 30%, #f7c24e, var(--color-gold));
        box-shadow: 0 2px 8px rgba(242, 168, 30, 0.4);
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .agenda-dia--conteudo:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(242, 168, 30, 0.55);
    }
    .agenda-dia--hoje {
        box-shadow: inset 0 0 0 2px var(--color-primary);
    }
    .agenda-dia--sel {
        background: var(--color-primary);
        color: #fff;
        font-weight: 700;
    }

    /* Blocos do card do dia */
    .agenda-bloco {
        padding-block: 22px;
        border-top: 1px solid var(--color-border-muted);
    }
    .agenda-bloco:first-child {
        padding-top: 0;
        border-top: 0;
    }
    .agenda-bloco-titulo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: var(--font-display, 'Work Sans', sans-serif);
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--color-primary);
    }
    .agenda-tick {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 9999px;
        background: var(--color-gold);
        box-shadow: 0 0 0 3px rgba(242, 168, 30, 0.18);
    }
    .agenda-subtitulo {
        margin-top: 8px;
        font-family: var(--font-display, 'Work Sans', sans-serif);
        font-weight: 600;
        color: var(--color-text-ink);
    }
    .agenda-prosa {
        margin-top: 8px;
        font-family: var(--font-serif, 'Roboto Slab', serif);
        font-size: 16px;
        line-height: 1.82;
        color: #3a3553;
        text-align: justify;
        hyphens: auto;
    }
    .agenda-prosa p {
        margin-bottom: 16px;
    }
    .agenda-prosa a {
        color: var(--color-secondary);
        text-decoration: underline;
    }

    @media (prefers-reduced-motion: reduce) {
        .agenda-dia--conteudo {
            transition: none;
        }
        .agenda-dia--conteudo:hover {
            transform: none;
        }
    }
}
```

- [ ] **Step 6: Importar o CSS em `resources/css/app.css`.** Adicionar após `@import './palestras-calendario.css';`:

```css
@import './agenda.css';
```

- [ ] **Step 7: Rodar o teste (e recompilar assets/limpar view cache).** Como a app roda com OPcache `validate_timestamps=0`, reinicie o app worker após editar Blade.
  Run: `docker exec cema-app php artisan test --filter=AgendaCrawlavelTest`
  Expected: OK (2 passed) — âncora `/agenda-reforma-intima/2026-07-10` + `wire:navigate` presentes; `2026-07-11` ausente; link do nav 'Agenda' no header.

- [ ] **Step 8: Commit.**
  Run: `git add resources/views/agenda/index.blade.php resources/views/agenda/_dia.blade.php resources/views/agenda/_calendario.blade.php resources/css/agenda.css resources/css/app.css tests/Feature/Front/AgendaCrawlavelTest.php && git commit -m "feat(agenda/front): casca SSR rica, card do dia e calendario crawlavel (wire:navigate)"`

---

### Task 12: Testes de A11y/responsivo verificáveis via HTML

**Files:**
- Test: `tests/Feature/Front/AgendaAcessibilidadeTest.php`

**Interfaces:**
- Consumes: HTML SSR das Tasks 9/11 — `aria-current="date"` no dia de hoje; `aria-label` nas setas de dia e de mês; estado vazio com `<meta name="robots" content="noindex">` + link "Voltar para hoje".
- Produces: nenhuma mudança de código de produção (teste de regressão de A11y). Se um assert falhar, corrigir a view correspondente da Task 11.

**Passos:**

- [ ] **Step 1: Escrever o teste de A11y/estado vazio.** Cria `tests/Feature/Front/AgendaAcessibilidadeTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaAcessibilidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dia_de_hoje_marcado_com_aria_current_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Reflexão de hoje</p>']);

        $this->get('/agenda-reforma-intima')
            ->assertOk()
            ->assertSee('aria-current="date"', false);
    }

    public function test_setas_de_dia_e_de_mes_tem_aria_label(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        // Conteúdo em três meses garante setas de mês e de dia (prev/next) ativas.
        AgendaDia::factory()->create(['data' => '2026-06-20', 'reflexao' => '<p>Junho</p>']);
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Julho</p>']);
        AgendaDia::factory()->create(['data' => '2026-08-05', 'reflexao' => '<p>Agosto</p>']);

        $resp = $this->get('/agenda-reforma-intima/2026-07-10')->assertOk();

        $resp->assertSee('aria-label="Mês anterior"', false);
        $resp->assertSee('aria-label="Próximo mês"', false);
        $resp->assertSee('aria-label="Dia anterior com conteúdo"', false);
        $resp->assertSee('aria-label="Próximo dia com conteúdo"', false);
    }

    public function test_estado_vazio_tem_noindex_e_link_para_hoje(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        // Data válida SEM AgendaDia publicada → estado vazio.
        $resp = $this->get('/agenda-reforma-intima/2026-07-11')->assertOk();

        $resp->assertSee('name="robots"', false);
        $resp->assertSee('content="noindex"', false);
        $resp->assertSee('href="'.route('agenda.index').'"', false);
        $resp->assertSee('Voltar para hoje');
    }

    public function test_url_datada_com_conteudo_nao_e_noindex(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 12, 0, 0, 'America/Sao_Paulo'));
        AgendaDia::factory()->create(['data' => '2026-07-10', 'reflexao' => '<p>Conteúdo real</p>']);

        $this->get('/agenda-reforma-intima/2026-07-10')
            ->assertOk()
            ->assertDontSee('content="noindex"', false);
    }
}
```

  Run: `docker exec cema-app php artisan test --filter=AgendaAcessibilidadeTest`
  Expected: OK (4 passed) se as views da Task 11 estiverem corretas. Se algum assert falhar (ex.: falta `aria-label` numa seta), ajustar o parcial correspondente da Task 11 e reexecutar até verde.

- [ ] **Step 2: Rodar a suíte de front da Agenda inteira + Pint antes do commit.**
  Run: `docker exec cema-app ./vendor/bin/pint app/Http/Controllers/AgendaController.php app/Support/Agenda/CalendarioAgenda.php && docker exec cema-app php artisan test --filter=Agenda`
  Expected: Pint sem drift; testes `AgendaRotaTest`, `CalendarioAgendaTest`, `AgendaCrawlavelTest`, `AgendaAcessibilidadeTest` todos verdes.

- [ ] **Step 3: Commit.**
  Run: `git add tests/Feature/Front/AgendaAcessibilidadeTest.php && git commit -m "test(agenda/front): A11y (aria-current/aria-label) e estado vazio noindex + voltar para hoje"`

---

**Observações de verificação manual (fora do TDD, para o `localhost`):** abrir `/agenda-reforma-intima` (hoje), navegar dias/meses pelos links (`wire:navigate`), conferir o botão "Voltar para hoje" quando em data ≠ hoje, testar os botões de compartilhar (Facebook/WhatsApp/copiar) e confirmar o redirect de fuso (simular fuso do navegador ≠ Brasília). Após editar Blade/PHP no container, rodar `restart app worker` (OPcache `validate_timestamps=0`).

---

Now I have all the molds I need. Here are the two tasks for Fase E (SEO).

---

### Task 13: SEO on-page da Agenda — canonical + JSON-LD (Article + BreadcrumbList) + noindex condicional no `<x-slot:head>`

**Files:**
- Modify: `resources/views/agenda/index.blade.php` (casca criada na Fase D — adicionar o bloco `@php` do JSON-LD e o `<x-slot:head>`; fixar `:title`/`:description`).
- Create (test): `tests/Feature/Front/AgendaSeoTest.php`

**Interfaces:**
- **Consumes:** variáveis da view vindas do `AgendaController` — `$dia` (`?App\Models\AgendaDia`), `$ehUrlNua` (bool), `$dataAtual` (`Carbon\Carbon`), `$temConteudo` (bool). Métodos do model: `AgendaDia::tituloExtenso(): string`, `descricaoSeo(): string`, propriedades `->data` (Carbon), `->reflexao` (?string), `->updated_at`. Rotas `route('agenda.index')` e `route('agenda.show', string $data)`. Componente `<x-layout.app :title :description>` com slot nomeado `head` (o layout injeta `{{ $head ?? '' }}` no `<head>`; já emite `og:type=website` e `og:url=url()->current()`).
- **Produces:** dentro da casca, um `<x-slot:head>` com `<link rel="canonical">` (data ou URL nua), um `<script type="application/ld+json">` (`@graph` = `Article` quando há dia + `BreadcrumbList` sempre) e `<meta name="robots" content="noindex">` quando `!$temConteudo`. **Não** emite `og:type`/`og:url` (evita duplicar o layout).

Passos:

- [ ] **Step 1: Escrever o teste de SEO que falha.** Criar `tests/Feature/Front/AgendaSeoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_tem_jsonld_article_breadcrumb_e_canonical(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-15',
            'reflexao' => '<p>Reflexão do dia sobre caridade e renúncia.</p>',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-15'));

        $resp->assertOk();
        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Article"', false);
        $resp->assertSee('"@type":"BreadcrumbList"', false);
        $resp->assertSee('rel="canonical"', false);
        // Organization CEMA como author/publisher do Article.
        $resp->assertSee('Centro Espírita Maria Madalena', false);
    }

    public function test_show_nao_duplica_og_type(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-16',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-16'));

        // O layout já emite og:type=website; a agenda não pode emitir og:type=article.
        $resp->assertDontSee('content="article"', false);
    }

    public function test_dia_sem_conteudo_tem_noindex(): void
    {
        // Data válida (futuro) sem AgendaDia publicado → 200 + noindex.
        $resp = $this->get(route('agenda.show', '2026-12-25'));

        $resp->assertOk();
        $resp->assertSee('name="robots" content="noindex"', false);
    }

    public function test_dia_publicado_nao_tem_noindex(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-17',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get(route('agenda.show', '2026-07-17'));

        $resp->assertDontSee('content="noindex"', false);
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaSeoTest`
  Expected: FAIL — `test_show_tem_jsonld_article_breadcrumb_e_canonical` falha em `assertSee('"@type":"Article"')` (a casca ainda não possui `<x-slot:head>`); `test_dia_sem_conteudo_tem_noindex` falha por não encontrar `noindex`.

- [ ] **Step 3: Adicionar o bloco `@php` do JSON-LD no topo da casca.** Em `resources/views/agenda/index.blade.php`, **imediatamente antes** da tag `<x-layout.app ...>` (após o comentário de autoria, no padrão de `blog/show.blade.php:2-53`), inserir:

```blade
@php
    $org = ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'];

    // A URL nua é canônica de si mesma ("hoje" evergreen); cada data é canônica de si.
    $urlCanonica = $ehUrlNua
        ? route('agenda.index')
        : route('agenda.show', $dataAtual->format('Y-m-d'));

    $graph = [];

    // Article só existe quando há conteúdo publicado para a data.
    if ($dia !== null) {
        // array_filter descarta chaves nulas (ex.: articleBody vazio) — inválidas no schema.org.
        $graph[] = array_filter([
            '@type'            => 'Article',
            'headline'         => 'Agenda Reforma Íntima — '.$dia->tituloExtenso(),
            'datePublished'    => $dia->data->toIso8601String(),
            'dateModified'     => $dia->updated_at?->toIso8601String(),
            'articleBody'      => trim(strip_tags((string) $dia->reflexao)) ?: null,
            'inLanguage'       => 'pt-BR',
            'author'           => $org,
            'publisher'        => $org,
            'mainEntityOfPage' => $urlCanonica,
        ], fn ($v) => $v !== null);
    }

    $graph[] = [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Agenda Reforma Íntima', 'item' => route('agenda.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $dataAtual->format('d/m/Y'), 'item' => $urlCanonica],
        ],
    ];

    // JSON_HEX_TAG neutraliza </script> injetado em conteúdo (mesmo vetor de blog/show).
    $jsonLd = json_encode(
        ['@context' => 'https://schema.org', '@graph' => $graph],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
    );
@endphp
```

- [ ] **Step 4: Fixar `:title`/`:description` e adicionar o `<x-slot:head>` como primeiro filho da casca.** Garantir que a tag de abertura da casca esteja assim (ajustando as props que a Fase D deixou):

```blade
<x-layout.app
    :title="'Agenda Reforma Íntima — '.$dataAtual->format('d/m/Y')"
    :description="$dia?->descricaoSeo()">

    <x-slot:head>
        {{-- Canonical: data própria, ou a URL nua quando "hoje" evergreen. --}}
        <link rel="canonical" href="{{ $urlCanonica }}">

        {{-- Dias sem conteúdo publicado não devem ser indexados (estado vazio). --}}
        @unless ($temConteudo)
            <meta name="robots" content="noindex">
        @endunless

        {{-- Sem og:type/og:url aqui: o layout já emite og:type=website + og:url. --}}

        {{-- JSON-LD: Article (quando há dia) + BreadcrumbList. --}}
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    </x-slot:head>
```

  (Manter o restante do corpo da casca — hero, `agenda/_dia`, `agenda/_calendario`, "Sobre o projeto", "Veja também" e o script de fuso — intacto após o slot.)

- [ ] **Step 5: Recarregar o worker e rodar o teste até passar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaSeoTest`
  Expected: OK — 4 passed (se editar Blade não refletir, `docker exec cema-app php artisan queue:restart` / restart app worker antes de reexecutar; OPcache `validate_timestamps=0` no dev).

- [ ] **Step 6: Pint e commit.**
  Run: `docker exec cema-app ./vendor/bin/pint resources/views/agenda/index.blade.php tests/Feature/Front/AgendaSeoTest.php`
  Then:

```bash
git add resources/views/agenda/index.blade.php tests/Feature/Front/AgendaSeoTest.php
git commit -m "feat(agenda/seo): canonical, JSON-LD (Article+BreadcrumbList) e noindex condicional no <x-slot:head>"
```

---

### Task 14: Agenda no `/sitemap.xml` — URL nua + dias publicados

**Files:**
- Modify: `app/Http/Controllers/SitemapController.php` (carregar `AgendaDia::publicado()->get(['data','updated_at'])` no `compact`).
- Modify: `resources/views/sitemap.blade.php` (adicionar a URL nua da agenda + `@foreach` das URLs datadas).
- Modify (test): `tests/Feature/Front/BlogSeoTest.php` **ou** Create `tests/Feature/Front/AgendaSitemapTest.php`. Usar arquivo novo para não misturar domínios.

**Interfaces:**
- **Consumes:** `App\Models\AgendaDia::publicado()` (scope) → coleção com `->data` (Carbon) e `->updated_at`; `route('agenda.index')`, `route('agenda.show', string)`. View `sitemap` recebe `$agendaDias` via `compact`.
- **Produces:** no `<urlset>`, uma `<url>` da rota nua (`changefreq daily`, `priority 0.9`) e uma `<url>` por dia publicado (`lastmod` = `updated_at->toAtomString()`, `changefreq monthly`, `priority 0.7`). Header `Content-Type: application/xml` preservado.

Passos:

- [ ] **Step 1: Escrever o teste que falha.** Criar `tests/Feature/Front/AgendaSitemapTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Front;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contem_url_nua_da_agenda(): void
    {
        $resp = $this->get('/sitemap.xml');

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/xml');
        // <loc> exata evita falso-positivo (as URLs datadas têm a nua como prefixo).
        $resp->assertSee('<loc>'.route('agenda.index').'</loc>', false);
    }

    public function test_sitemap_contem_url_datada_de_dia_publicado(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-20',
            'status' => AgendaDia::STATUS_PUBLICADO,
        ]);

        $resp = $this->get('/sitemap.xml');

        $resp->assertSee('<loc>'.route('agenda.show', '2026-07-20').'</loc>', false);
    }

    public function test_sitemap_nao_contem_dia_em_rascunho(): void
    {
        AgendaDia::factory()->create([
            'data' => '2026-07-21',
            'status' => AgendaDia::STATUS_RASCUNHO,
        ]);

        $resp = $this->get('/sitemap.xml');

        $resp->assertDontSee('<loc>'.route('agenda.show', '2026-07-21').'</loc>', false);
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar.**
  Run: `docker exec cema-app php artisan test --filter=AgendaSitemapTest`
  Expected: FAIL — `test_sitemap_contem_url_nua_da_agenda` falha em `assertSee('<loc>.../agenda-reforma-intima</loc>')` (o sitemap ainda não lista a agenda).

- [ ] **Step 3: Carregar os dias publicados no controller.** Em `app/Http/Controllers/SitemapController.php`, adicionar o `use` do model e a consulta, incluindo `$agendaDias` no `compact`:

```php
use App\Models\AgendaDia;
```

  e no corpo de `index()`, antes do `return`:

```php
        $agendaDias = AgendaDia::publicado()
            ->orderBy('data')
            ->get(['data', 'updated_at']);
```

  e trocar o `compact` do retorno para:

```php
        return response()
            ->view('sitemap', compact('posts', 'categorias', 'agendaDias'))
            ->header('Content-Type', 'application/xml');
```

- [ ] **Step 4: Emitir as URLs da agenda na view do sitemap.** Em `resources/views/sitemap.blade.php`, **antes** do fechamento `</urlset>`, adicionar:

```blade
    {{-- Agenda Reforma Íntima — URL nua ("hoje" evergreen) --}}
    <url>
        <loc>{{ route('agenda.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>

    {{-- Agenda Reforma Íntima — dias publicados --}}
    @foreach ($agendaDias as $agendaDia)
    <url>
        <loc>{{ route('agenda.show', $agendaDia->data->format('Y-m-d')) }}</loc>
        <lastmod>{{ $agendaDia->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
```

- [ ] **Step 5: Rodar o teste até passar (e a suíte de SEO existente para não regredir).**
  Run: `docker exec cema-app php artisan test --filter=AgendaSitemapTest`
  Expected: OK — 3 passed.
  Run: `docker exec cema-app php artisan test --filter=BlogSeoTest`
  Expected: OK — o sitemap do blog segue intacto (nenhum teste do blog quebra).

- [ ] **Step 6: Pint e commit.**
  Run: `docker exec cema-app ./vendor/bin/pint app/Http/Controllers/SitemapController.php tests/Feature/Front/AgendaSitemapTest.php`
  Then:

```bash
git add app/Http/Controllers/SitemapController.php resources/views/sitemap.blade.php tests/Feature/Front/AgendaSitemapTest.php
git commit -m "feat(agenda/seo): incluir a Agenda Reforma Íntima no sitemap.xml (URL nua + dias publicados)"
```

---

Notas de fidelidade aos moldes (para o executor):
- O `@php` de JSON-LD e o `<x-slot:head>` replicam `resources/views/blog/show.blade.php:2-85` (mesmo `array_filter`, mesmas flags `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG`, mesmo padrão de `<link rel="canonical">` e `<script type="application/ld+json">`).
- O layout `resources/views/components/layout/app.blade.php:16-17,22` já emite `og:type=website` + `og:url=url()->current()` e injeta o slot via `{{ $head ?? '' }}` — por isso a Task 13 **não** repete OG.
- O sitemap segue `resources/views/sitemap.blade.php` (mesmo `<loc>/<lastmod>/<changefreq>/<priority>` e `updated_at->toAtomString()`) e o `SitemapController` mantém o `->header('Content-Type', 'application/xml')` já coberto por `BlogSeoTest::test_sitemap_retorna_200_com_content_type_xml`.
