# Fase B — Departamento nos Conteúdos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Departamentalizar os conteúdos (Palestra, Post, AgendaDia, Palestrante) — tirar as policies do fail-closed e semear os vínculos — completando o eixo CAPACIDADE da Fase A, sem mudar o `/admin` nem a visibilidade.

**Architecture:** O vocabulário ganha `palestrante` (16→20 permissions, derivadas da constante declarativa). Quatro pivots N:N (`departamento_palestra`, `departamento_post`, `departamento_palestrante`, `departamento_agenda_dia`) e os quatro models passam a `implements TemDepartamento`. As três policies fail-closed (`Palestra`/`Post`/`AgendaDia`) são reescritas para o molde real da `EventoPolicy` (permissão via `hasPermissionTo` + escopo pelo trait `AutorizaPorDepartamento`), e `Palestrante` ganha uma `PalestrantePolicy` nova no mesmo molde. Dois comandos artisan idempotentes semeiam os vínculos: `cema:departamentalizar-conteudos` (conteúdo→departamento que o mantém) e `cema:vincular-diretores-departamento` (usuário→departamento do cargo). Tudo provado por teste; o `/admin` é coberto pela suíte de resource-tests existente.

**Tech Stack:** PHP 8.3 · Laravel 13 · spatie/laravel-permission (guard `web`, teams OFF, wildcard OFF) · Filament 5 (não consome as abilities pt-BR das policies) · MySQL 8 (dev via Docker; testes com `RefreshDatabase`).

**Fonte da verdade:** [SPEC — Fase B](../specs/2026-07-11-fase-b-departamento-conteudos.md) (aprovado no passe adversarial do dono/consultor).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, comentários, mensagens, commits).
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11` no topo de todo PHP **novo** (após `<?php`, antes do `namespace`) — **exceto migrations** (classe anônima, sem cabeçalho, seguindo as existentes).
- **Guard:** roles e permissions sempre no guard **`web`**.
- **`hasPermissionTo`, NUNCA `can()`** dentro das policies (decisão 3.0 da Fase A: com `register_permission_check_method` OFF, `can('recurso.acao')` não resolve nomes de permissão). Não "consertar" trocando por `can`.
- **Fail-closed sempre:** objeto sem departamento, usuário sem vínculo, ou ausência da permissão ⇒ negar. Só o admin passa (antes, no `Gate::before` da Fase A).
- **Ordem que importa (SPEC §9):** o **vocabulário (20) é semeado ANTES** de qualquer policy chamar `hasPermissionTo` — senão o Spatie lança `PermissionDoesNotExist` para não-admin. O `PalestranteResourceTest` **não** pega isso (roda como admin ⇒ passa no `Gate::before`); a guarda real é o teste de capacidade (Task 4), cujo `setUp` semeia as 20.
- **`/admin` intocado:** o painel é admin-only e o admin passa no `Gate::before`; o Filament v5 não usa strict authorization. A não-regressão é provada pela **suíte de resource-tests existente** — `EventoResourceTest`, `PostResourceTest`, `PalestraResourceTest`, `AgendaDiaResourceTest` **e `PalestranteResourceTest`** (este já existe; entra na guarda porque `Palestrante` ganha policy).
- **Visibilidade intocada:** `podeSerVistoPor`/`scopeVisiveisPara`/scopes de publicação não são tocados. As novas policies têm **só** capacidade (`ver`/`criar`/`editar`/`excluir`), **sem** `view`/`viewAny`.
- **Banco:** MySQL só por migrations **incrementais**. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo no dev — apagam os dados importados (123+ palestras, 44+ posts). No dev, só `php artisan migrate` + os dois comandos de backfill (idempotentes, `syncWithoutDetaching`). Nos testes, `RefreshDatabase` é seguro (banco de teste isolado).
- **Ferramentas no container:** `docker compose exec -T app php artisan ...` e `docker compose exec -T app ./vendor/bin/pint`. Esta fase não tem front/build (nada de npm/Vite).
- **Pint** limpo antes do push; suíte no container; **commits atômicos** pt-BR na branch **`fase-b-departamento`** (criada a partir de `main`).
- **Gotchas travados (SPEC):** (1) `departamento_palestrante` usa `palestrante_id` (FK nova) — **não** reaproveitar `pessoa_id` do pivot legado `palestra_pessoa`; (2) `departamento_agenda_dia` (base `agenda_dias`) — a relação nomeia tabela e chaves explicitamente (a convenção nativa daria `agenda_dia_departamento`); (3) backfill de diretores gera **≤7 vínculos reais** (DAS sem ocupante) e filtra por `cargos.departamento_id IS NOT NULL` (não pelo slug `diretor_*`); (4) o `PoliciesFailClosedTest` é **reescrito+renomeado** (não deletado), preservando a guarda de auto-discovery.
- **Escopo travado:** NÃO atribuir permissions a papéis (Fase C), NÃO fazer tela de atribuição (Fase C), NÃO tocar visibilidade, NÃO mexer em `Biblioteca` (singleton) nem `AgendaMetaMes` (metadado). O recurso `agenda` mapeia a **`AgendaDia`**.

---

### Task 1: Vocabulário — 20 permissions

**Files:**
- Modify: `app/Support/Autorizacao/GlossarioCapacidades.php` (`+ 'palestrante'` em `RECURSOS`; docblocks)
- Modify: `database/seeders/CapacidadesSeeder.php` (docblock "16" → "20")
- Modify: `tests/Feature/Autorizacao/CapacidadesSeederTest.php` (16 → 20)

**Interfaces:**
- Produces: `GlossarioCapacidades::RECURSOS` = `['evento','palestra','post','agenda','palestrante']`; `::permissions()` = 20 strings `"recurso.acao"`; `CapacidadesSeeder` cria as 20 (guard `web`). Consumido pelos testes das Tasks 4 (capacidade) e por qualquer `setUp` que semeie o catálogo.

- [ ] **Step 1: Atualizar o teste para os 20 nomes (falha primeiro)**

Substituir o método em `tests/Feature/Autorizacao/CapacidadesSeederTest.php`:

```php
    public function test_semeia_os_20_nomes_exatos_e_e_idempotente(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $this->seed(CapacidadesSeeder::class); // 2ª vez não duplica

        $esperados = [
            'evento.ver', 'evento.criar', 'evento.editar', 'evento.excluir',
            'palestra.ver', 'palestra.criar', 'palestra.editar', 'palestra.excluir',
            'post.ver', 'post.criar', 'post.editar', 'post.excluir',
            'agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir',
            'palestrante.ver', 'palestrante.criar', 'palestrante.editar', 'palestrante.excluir',
        ];

        $this->assertSame(20, Permission::where('guard_name', 'web')->count());
        foreach ($esperados as $nome) {
            $this->assertDatabaseHas('permissions', ['name' => $nome, 'guard_name' => 'web']);
        }
    }
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadesSeederTest`
Expected: FAIL (`Failed asserting that 16 is identical to 20` — o glossário ainda gera 16).

- [ ] **Step 3: Adicionar `palestrante` ao glossário + atualizar docblocks**

Em `app/Support/Autorizacao/GlossarioCapacidades.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Support\Autorizacao;

/**
 * Fonte única do vocabulário de CAPACIDADE (quem edita). Mesmo padrão declarativo de
 * App\Importacao\GlossarioUsuarios, em local próprio. Biblioteca fica FORA (singleton admin-only).
 */
class GlossarioCapacidades
{
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante'];

    public const ACOES = ['ver', 'criar', 'editar', 'excluir'];

    /** @return list<string> os 20 nomes "recurso.acao". */
    public static function permissions(): array
    {
        $nomes = [];
        foreach (self::RECURSOS as $recurso) {
            foreach (self::ACOES as $acao) {
                $nomes[] = "{$recurso}.{$acao}";
            }
        }

        return $nomes;
    }
}
```

- [ ] **Step 4: Atualizar o docblock do seeder (sem mudança de lógica)**

Em `database/seeders/CapacidadesSeeder.php`, ajustar o docblock da classe:

```php
/**
 * Semeia as 20 permissions de capacidade (guard web), idempotente. NÃO atribui a papéis:
 * a matriz papel→permissão é a Fase C. Ver App\Support\Autorizacao\GlossarioCapacidades.
 */
```

(O corpo `run()` — `foreach (GlossarioCapacidades::permissions() ...)` — **não** muda.)

- [ ] **Step 5: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadesSeederTest`
Expected: PASS.

- [ ] **Step 6: Semear no dev (incremental, não destrutivo)**

Run: `docker compose exec -T app php artisan db:seed --class=CapacidadesSeeder`
Run: `docker compose exec -T app php artisan tinker --execute="echo Spatie\Permission\Models\Permission::where('guard_name','web')->count();"`
Expected: imprime `20`.

- [ ] **Step 7: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Autorizacao database/seeders tests/Feature/Autorizacao
git add app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/CapacidadesSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php
git commit -m "feat(departamentos): vocabulário de capacidade ganha palestrante (16→20 permissions)"
```

---

### Task 2: Pivots de departamento + os 4 models `TemDepartamento`

**Files:**
- Create: `database/migrations/2026_07_11_000002_create_departamento_palestra_table.php`
- Create: `database/migrations/2026_07_11_000003_create_departamento_post_table.php`
- Create: `database/migrations/2026_07_11_000004_create_departamento_palestrante_table.php`
- Create: `database/migrations/2026_07_11_000005_create_departamento_agenda_dia_table.php`
- Modify: `app/Models/Palestra.php`, `Post.php`, `AgendaDia.php`, `Palestrante.php`
- Test: `tests/Feature/Autorizacao/ConteudosTemDepartamentoTest.php`

**Interfaces:**
- Consumes: `App\Models\Contracts\TemDepartamento` (Fase A), tabela `departamentos` (Fase A).
- Produces: tabelas `departamento_palestra`/`departamento_post`/`departamento_palestrante`/`departamento_agenda_dia`; `Palestra`/`Post`/`AgendaDia`/`Palestrante` `implements TemDepartamento` com `departamentos(): BelongsToMany`. Consumido pelas Tasks 3 (backfill) e 4 (policies).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/ConteudosTemDepartamentoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Contracts\TemDepartamento;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConteudosTemDepartamentoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string<Model>, string}> */
    public static function conteudos(): array
    {
        return [
            'palestra' => [Palestra::class, 'departamento_palestra'],
            'post' => [Post::class, 'departamento_post'],
            'palestrante' => [Palestrante::class, 'departamento_palestrante'],
            'agenda_dia' => [AgendaDia::class, 'departamento_agenda_dia'],
        ];
    }

    #[DataProvider('conteudos')]
    public function test_model_implementa_contrato_e_pivot_nasce_vazia(string $model, string $pivot): void
    {
        $this->assertInstanceOf(TemDepartamento::class, new $model);
        $this->assertSame(0, DB::table($pivot)->count());
    }

    #[DataProvider('conteudos')]
    public function test_relaciona_e_desrelaciona_departamentos(string $model, string $pivot): void
    {
        $obj = $model::factory()->create();
        $ded = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);
        $decom = Departamento::create(['sigla' => 'DECOM', 'nome' => 'DECOM', 'slug' => 'decom']);

        $obj->departamentos()->attach([$ded->id, $decom->id]);
        $this->assertSame(2, $obj->departamentos()->count());

        $obj->departamentos()->detach($ded->id);
        $this->assertSame(1, $obj->departamentos()->count());
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ConteudosTemDepartamentoTest`
Expected: FAIL (`Base table or view not found: ... departamento_palestra` / `... instanceof TemDepartamento` falha).

- [ ] **Step 3: Criar as 4 migrations (incrementais, nascem vazias)**

`database/migrations/2026_07_11_000002_create_departamento_palestra_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_palestra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['palestra_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_palestra');
    }
};
```

`database/migrations/2026_07_11_000003_create_departamento_post_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['post_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_post');
    }
};
```

`database/migrations/2026_07_11_000004_create_departamento_palestrante_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_palestrante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestrante_id')->constrained('palestrantes')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['palestrante_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_palestrante');
    }
};
```

`database/migrations/2026_07_11_000005_create_departamento_agenda_dia_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_agenda_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_dia_id')->constrained('agenda_dias')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['agenda_dia_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_agenda_dia');
    }
};
```

- [ ] **Step 4: `Palestra implements TemDepartamento` + `departamentos()`**

Em `app/Models/Palestra.php`, adicionar o import (junto aos demais `use`; `BelongsToMany` já está importado):

```php
use App\Models\Contracts\TemDepartamento;
```

Declaração da classe:

```php
class Palestra extends Model implements TemDepartamento
```

Adicionar o método junto às demais relações:

```php
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_palestra', 'palestra_id', 'departamento_id');
    }
```

- [ ] **Step 5: `Post implements ... TemDepartamento` + `departamentos()`**

Em `app/Models/Post.php`, adicionar o import (`BelongsToMany` já está importado):

```php
use App\Models\Contracts\TemDepartamento;
```

Declaração da classe (acrescentar à lista existente):

```php
class Post extends Model implements HasMedia, HasRichContent, TemDepartamento
```

Adicionar o método junto às demais relações:

```php
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_post', 'post_id', 'departamento_id');
    }
```

- [ ] **Step 6: `AgendaDia implements TemDepartamento` + `departamentos()`**

Em `app/Models/AgendaDia.php`, adicionar os imports (**`BelongsToMany` NÃO está importado** neste model — adicionar os dois):

```php
use App\Models\Contracts\TemDepartamento;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
```

Declaração da classe:

```php
class AgendaDia extends Model implements TemDepartamento
```

Adicionar o método junto às demais relações:

```php
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_agenda_dia', 'agenda_dia_id', 'departamento_id');
    }
```

- [ ] **Step 7: `Palestrante implements ... TemDepartamento` + `departamentos()`**

Em `app/Models/Palestrante.php`, adicionar o import (`BelongsToMany` já está importado):

```php
use App\Models\Contracts\TemDepartamento;
```

Declaração da classe (acrescentar à lista existente):

```php
class Palestrante extends Model implements HasMedia, TemDepartamento
```

Adicionar o método junto às demais relações:

```php
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_palestrante', 'palestrante_id', 'departamento_id');
    }
```

- [ ] **Step 8: Rodar a migration no dev (incremental) e o teste**

Run: `docker compose exec -T app php artisan migrate`
Expected: roda as 4 migrations `2026_07_11_000002..000005` (`DONE`).

Run: `docker compose exec -T app php artisan test --filter=ConteudosTemDepartamentoTest`
Expected: PASS (8 casos: 4 conteúdos × 2 métodos).

- [ ] **Step 9: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint database/migrations app/Models tests/Feature/Autorizacao
git add database/migrations/2026_07_11_000002_create_departamento_palestra_table.php database/migrations/2026_07_11_000003_create_departamento_post_table.php database/migrations/2026_07_11_000004_create_departamento_palestrante_table.php database/migrations/2026_07_11_000005_create_departamento_agenda_dia_table.php app/Models/Palestra.php app/Models/Post.php app/Models/AgendaDia.php app/Models/Palestrante.php tests/Feature/Autorizacao/ConteudosTemDepartamentoTest.php
git commit -m "feat(departamentos): 4 pivots + Palestra/Post/AgendaDia/Palestrante implementam TemDepartamento"
```

---

### Task 3: Backfill dos conteúdos — `cema:departamentalizar-conteudos`

**Files:**
- Create: `app/Console/Commands/DepartamentalizarConteudos.php`
- Test: `tests/Feature/Autorizacao/DepartamentalizarConteudosTest.php`

**Interfaces:**
- Consumes: `departamentos` (sigla DED/DECOM), as relações `departamentos()` (Task 2).
- Produces: comando `cema:departamentalizar-conteudos` que faz `syncWithoutDetaching([$deptoId])` por registro (Palestra/Palestrante→DED, Post/AgendaDia→DECOM). Idempotente.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/DepartamentalizarConteudosTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentalizarConteudosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // cria os 8 departamentos (DED, DECOM, ...)
    }

    public function test_vincula_cada_conteudo_ao_departamento_que_mantem_e_e_idempotente(): void
    {
        $palestra = Palestra::factory()->create();
        $palestrante = Palestrante::factory()->create();
        $post = Post::factory()->create();
        $agenda = AgendaDia::factory()->create();

        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful();
        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful(); // 2ª vez não duplica

        $ded = Departamento::where('sigla', 'DED')->first();
        $decom = Departamento::where('sigla', 'DECOM')->first();

        $this->assertSame([$ded->id], $palestra->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$ded->id], $palestrante->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$decom->id], $post->fresh()->departamentos()->pluck('departamentos.id')->all());
        $this->assertSame([$decom->id], $agenda->fresh()->departamentos()->pluck('departamentos.id')->all());
    }

    public function test_preserva_vinculo_manual_extra(): void
    {
        $palestra = Palestra::factory()->create();
        $decom = Departamento::where('sigla', 'DECOM')->first();
        $palestra->departamentos()->attach($decom->id); // vínculo manual pré-existente (fora do critério)

        $this->artisan('cema:departamentalizar-conteudos')->assertSuccessful();

        $ded = Departamento::where('sigla', 'DED')->first();
        $ids = $palestra->fresh()->departamentos()->pluck('departamentos.id')->all();

        $this->assertContains($ded->id, $ids);    // recebeu o DED (critério)
        $this->assertContains($decom->id, $ids);  // preservou o DECOM manual (syncWithoutDetaching)
        $this->assertCount(2, $ids);
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=DepartamentalizarConteudosTest`
Expected: FAIL (`The command "cema:departamentalizar-conteudos" does not exist`).

- [ ] **Step 3: Criar o comando**

`app/Console/Commands/DepartamentalizarConteudos.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Console\Commands;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Backfill idempotente do departamento que MANTÉM cada conteúdo (não o tema):
 * Palestra→DED, Palestrante→DED, Post→DECOM, AgendaDia→DECOM. Só o vínculo; a permissão é a Fase C.
 * syncWithoutDetaching preserva vínculos manuais e a unique do pivot impede duplicação.
 */
class DepartamentalizarConteudos extends Command
{
    protected $signature = 'cema:departamentalizar-conteudos';

    protected $description = 'Vincula cada conteúdo (Palestra/Palestrante→DED, Post/AgendaDia→DECOM) ao departamento que o mantém, de forma idempotente.';

    public function handle(): int
    {
        $ded = Departamento::where('sigla', 'DED')->first();
        $decom = Departamento::where('sigla', 'DECOM')->first();

        if ($ded === null || $decom === null) {
            $this->error('Departamentos DED/DECOM não encontrados. Rode o EstruturaCemaSeeder antes.');

            return self::FAILURE;
        }

        $vinculos = [
            [Palestra::class, $ded],
            [Palestrante::class, $ded],
            [Post::class, $decom],
            [AgendaDia::class, $decom],
        ];

        foreach ($vinculos as [$model, $departamento]) {
            $total = 0;
            $model::query()->chunkById(200, function ($registros) use ($departamento, &$total) {
                foreach ($registros as $registro) {
                    $registro->departamentos()->syncWithoutDetaching([$departamento->id]);
                    $total++;
                }
            });
            $this->info(sprintf('%s: %d registro(s) vinculado(s) a %s.', class_basename($model), $total, $departamento->sigla));
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=DepartamentalizarConteudosTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Console/Commands tests/Feature/Autorizacao
git add app/Console/Commands/DepartamentalizarConteudos.php tests/Feature/Autorizacao/DepartamentalizarConteudosTest.php
git commit -m "feat(departamentos): comando cema:departamentalizar-conteudos (backfill idempotente)"
```

---

### Task 4: Policies departamentalizadas + teste parametrizado (reescreve o fail-closed)

**Files:**
- Create: `app/Policies/PalestrantePolicy.php`
- Modify: `app/Policies/PalestraPolicy.php`, `PostPolicy.php`, `AgendaDiaPolicy.php` (fail-closed → molde real)
- Rename+rewrite: `tests/Feature/Autorizacao/PoliciesFailClosedTest.php` → `tests/Feature/Autorizacao/CapacidadeConteudosTest.php`

**Interfaces:**
- Consumes: as permissions `<recurso>.*` (Task 1), as relações `departamentos()` (Task 2), o trait `AutorizaPorDepartamento` e o `Gate::before` do admin (Fase A).
- Produces: `PalestraPolicy`/`PostPolicy`/`AgendaDiaPolicy`/`PalestrantePolicy` com `ver`/`criar`/`editar`/`excluir` (permissão + escopo). `criar` objectless (`check('criar', <Model>::class)`).

- [ ] **Step 1: Renomear o teste antigo e reescrevê-lo (falha primeiro)**

Renomear preservando o histórico:

```bash
git mv tests/Feature/Autorizacao/PoliciesFailClosedTest.php tests/Feature/Autorizacao/CapacidadeConteudosTest.php
```

Substituir todo o conteúdo de `tests/Feature/Autorizacao/CapacidadeConteudosTest.php` por (parametrizado nos 4 models; preserva a guarda de auto-discovery, agora com `PalestrantePolicy`; adiciona os cenários positivos):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use App\Models\User;
use App\Policies\AgendaDiaPolicy;
use App\Policies\PalestraPolicy;
use App\Policies\PalestrantePolicy;
use App\Policies\PostPolicy;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapacidadeConteudosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class); // as 20 permissions (inclui palestrante.*)
    }

    /** @return array<string, array{class-string<Model>, string}> */
    public static function recursos(): array
    {
        return [
            'palestra' => [Palestra::class, 'palestra'],
            'post' => [Post::class, 'post'],
            'agenda' => [AgendaDia::class, 'agenda'],
            'palestrante' => [Palestrante::class, 'palestrante'],
        ];
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

    private function depto(string $sigla): Departamento
    {
        return Departamento::create(['sigla' => $sigla, 'nome' => $sigla, 'slug' => strtolower($sigla)]);
    }

    private function objeto(string $model, array $departamentoIds = []): Model
    {
        $obj = $model::factory()->create();
        $obj->departamentos()->sync($departamentoIds);

        return $obj;
    }

    public function test_policies_resolvidas_por_auto_discovery(): void
    {
        $this->assertInstanceOf(PalestraPolicy::class, Gate::getPolicyFor(Palestra::class));
        $this->assertInstanceOf(PostPolicy::class, Gate::getPolicyFor(Post::class));
        $this->assertInstanceOf(AgendaDiaPolicy::class, Gate::getPolicyFor(AgendaDia::class));
        $this->assertInstanceOf(PalestrantePolicy::class, Gate::getPolicyFor(Palestrante::class));
    }

    #[DataProvider('recursos')]
    public function test_permite_ver_editar_excluir_com_intersecao(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]);
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_caso_disjunto(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $depro = $this->depto('DEPRO');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]); // usuário no DED
        $obj = $this->objeto($model, [$depro->id]);               // objeto no DEPRO

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_sem_vinculo(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir", "{$recurso}.criar"], []);
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar"); // sem vínculo ⇒ não cria
    }

    #[DataProvider('recursos')]
    public function test_objeto_sem_departamento_so_admin(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$ded->id]);
        $obj = $this->objeto($model, []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
            $this->assertTrue(Gate::forUser($this->admin())->check($acao, $obj), "admin {$recurso}.{$acao}");
        }
    }

    #[DataProvider('recursos')]
    public function test_nega_sem_a_permissao(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario([], [$ded->id]); // vínculo, mas nenhuma permissão
        $obj = $this->objeto($model, [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar");
    }

    #[DataProvider('recursos')]
    public function test_criar_com_e_sem_departamento(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $comDepto = $this->usuario(["{$recurso}.criar"], [$ded->id]);
        $semDepto = $this->usuario(["{$recurso}.criar"], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', $model));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', $model));
    }

    #[DataProvider('recursos')]
    public function test_nome_cru_nega_mas_ability_permite(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(["{$recurso}.editar"], [$ded->id]);
        $obj = $this->objeto($model, [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->allows("{$recurso}.editar", $obj)); // nome cru NEGA
        $this->assertTrue(Gate::forUser($u)->check('editar', $obj));              // ability PERMITE
    }

    #[DataProvider('recursos')]
    public function test_visitante_anonimo_negado(string $model, string $recurso): void
    {
        $ded = $this->depto('DED');
        $obj = $this->objeto($model, [$ded->id]);

        $this->assertFalse(Gate::forUser(null)->check('editar', $obj));
    }

    #[DataProvider('recursos')]
    public function test_admin_passa_em_tudo(string $model, string $recurso): void
    {
        $admin = $this->admin();
        $obj = $this->objeto($model, []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', $model));
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadeConteudosTest`
Expected: FAIL — `test_policies_resolvidas_por_auto_discovery` **erra** porque `assertInstanceOf(PalestrantePolicy::class, ...)` referencia `App\Policies\PalestrantePolicy`, que só nasce no Step 6: o PHPUnit lança `Class "App\Policies\PalestrantePolicy" does not exist`. **É o fail-primeiro esperado, não erro do worker.** Os casos positivos das 3 policies ainda fail-closed também falham. Tudo fica verde após os Steps 3–6.

- [ ] **Step 3: Reescrever `PalestraPolicy`**

Substituir todo o conteúdo de `app/Policies/PalestraPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestra;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Palestra: permissão palestra.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável ⇒ o Gate pula p/ visitante (deny limpo). O admin passa antes no
 * Gate::before. Fase B: saiu do fail-closed ao Palestra ganhar departamento (implements TemDepartamento).
 */
class PalestraPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.ver') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('palestra.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.editar') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }

    public function excluir(User $user, Palestra $palestra): bool
    {
        return $user->hasPermissionTo('palestra.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $palestra);
    }
}
```

- [ ] **Step 4: Reescrever `PostPolicy`**

Substituir todo o conteúdo de `app/Policies/PostPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Post: permissão post.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Fase B: saiu do fail-closed.
 */
class PostPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.ver') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('post.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.editar') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }

    public function excluir(User $user, Post $post): bool
    {
        return $user->hasPermissionTo('post.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $post);
    }
}
```

- [ ] **Step 5: Reescrever `AgendaDiaPolicy`**

Substituir todo o conteúdo de `app/Policies/AgendaDiaPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\AgendaDia;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de AgendaDia: permissão agenda.* (hasPermissionTo, NUNCA can()) + escopo de
 * departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Fase B: saiu do fail-closed.
 */
class AgendaDiaPolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.ver') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('agenda.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.editar') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }

    public function excluir(User $user, AgendaDia $agendaDia): bool
    {
        return $user->hasPermissionTo('agenda.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
    }
}
```

- [ ] **Step 6: Criar `PalestrantePolicy`**

`app/Policies/PalestrantePolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestrante;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de Palestrante: permissão palestrante.* (hasPermissionTo, NUNCA can()) + escopo
 * de departamento (trait). User NÃO-nulável. O admin passa antes no Gate::before. Palestrante é o cadastro
 * único (serve palestrante E diretor-de-palestra via papel no pivot palestra_pessoa); o departamento é de
 * POSSE do cadastro (DED), não do papel.
 */
class PalestrantePolicy
{
    use AutorizaPorDepartamento;

    public function ver(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.ver') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('palestrante.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.editar') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }

    public function excluir(User $user, Palestrante $palestrante): bool
    {
        return $user->hasPermissionTo('palestrante.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $palestrante);
    }
}
```

- [ ] **Step 7: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadeConteudosTest`
Expected: PASS (auto-discovery + 8 métodos parametrizados × 4 recursos).

- [ ] **Step 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Policies tests/Feature/Autorizacao
# O git mv já registrou o rename PoliciesFailClosedTest→CapacidadeConteudosTest; adicionar os diretórios
# captura o rename + as edições sem citar o path removido (evita erro de pathspec).
git add app/Policies tests/Feature/Autorizacao
git commit -m "feat(departamentos): policies de capacidade (Palestra/Post/AgendaDia saem do fail-closed + PalestrantePolicy)"
```

---

### Task 5: Backfill do vínculo dos diretores — `cema:vincular-diretores-departamento`

**Files:**
- Create: `app/Console/Commands/VincularDiretoresDepartamento.php`
- Test: `tests/Feature/Autorizacao/VincularDiretoresDepartamentoTest.php`

**Interfaces:**
- Consumes: `User::cargos()` (belongsToMany `cargo_usuario`), `Cargo::departamento_id`, `User::departamentos()` (pivot `departamento_usuario` da Fase A).
- Produces: comando `cema:vincular-diretores-departamento` que, para cada usuário com cargo de `departamento_id` não-nulo, faz `syncWithoutDetaching` do(s) departamento(s) em `departamento_usuario`. Idempotente.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/VincularDiretoresDepartamentoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VincularDiretoresDepartamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // departamentos + cargos (diretor_* e institucionais)
    }

    private function cargo(string $slug): Cargo
    {
        return Cargo::where('slug', $slug)->firstOrFail();
    }

    public function test_diretor_de_departamento_recebe_o_vinculo_e_e_idempotente(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();
        $user = User::factory()->create();
        $user->cargos()->attach($this->cargo('diretor-do-ded')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();
        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful(); // idempotente

        $this->assertSame([$ded->id], $user->fresh()->departamentos()->pluck('departamentos.id')->all());
    }

    public function test_cargo_institucional_sem_departamento_nao_gera_vinculo(): void
    {
        $user = User::factory()->create();
        // diretor_presidente → nome 'Presidente' → slug 'presidente'; departamento_id null.
        $user->cargos()->attach($this->cargo('presidente')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertSame(0, $user->fresh()->departamentos()->count());
    }

    public function test_das_fica_sem_vinculo_por_nao_ter_ocupante(): void
    {
        // O cargo 'diretor-do-das' existe (CARGOS_EXTRA), mas ninguém o ocupa.
        $das = Departamento::where('sigla', 'DAS')->first();
        $user = User::factory()->create();
        $user->cargos()->attach($this->cargo('diretor-do-ded')->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertSame(0, DB::table('departamento_usuario')->where('departamento_id', $das->id)->count());
    }

    public function test_invariante_cargo_nao_diretor_com_departamento_tambem_vincula(): void
    {
        // O filtro é "cargo com departamento", NÃO o slug 'diretor_*'.
        $depro = Departamento::where('sigla', 'DEPRO')->first();
        $coordenador = Cargo::create([
            'nome' => 'Coordenador de Eventos', 'slug' => 'coordenador-de-eventos',
            'departamento_id' => $depro->id, 'institucional' => false,
        ]);
        $user = User::factory()->create();
        $user->cargos()->attach($coordenador->id);

        $this->artisan('cema:vincular-diretores-departamento')->assertSuccessful();

        $this->assertTrue($user->fresh()->departamentos()->where('departamentos.id', $depro->id)->exists());
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=VincularDiretoresDepartamentoTest`
Expected: FAIL (`The command "cema:vincular-diretores-departamento" does not exist`).

- [ ] **Step 3: Criar o comando**

`app/Console/Commands/VincularDiretoresDepartamento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Backfill idempotente do VÍNCULO editorial dos diretores: cada usuário que ocupa um cargo COM
 * departamento (cargos.departamento_id NOT NULL) recebe esse departamento em departamento_usuario.
 * O filtro é semântico ("cargo com departamento" = candidato a editor), não o slug "diretor_*".
 * Só o vínculo; a permissão é a Fase C. O DAS pode ficar sem diretor (cargo sem ocupante) — esperado.
 */
class VincularDiretoresDepartamento extends Command
{
    protected $signature = 'cema:vincular-diretores-departamento';

    protected $description = 'Vincula cada usuário ao(s) departamento(s) do(s) seu(s) cargo(s) com departamento (departamento_usuario), idempotente.';

    public function handle(): int
    {
        $usuarios = User::whereHas('cargos', fn ($q) => $q->whereNotNull('departamento_id'))
            ->with(['cargos' => fn ($q) => $q->whereNotNull('departamento_id')])
            ->get();

        $totalVinculos = 0;
        foreach ($usuarios as $usuario) {
            $departamentoIds = $usuario->cargos->pluck('departamento_id')->unique()->all();
            $usuario->departamentos()->syncWithoutDetaching($departamentoIds);
            $totalVinculos += count($departamentoIds);
        }

        $this->info(sprintf('%d diretor(es) vinculado(s) — %d vínculo(s) de departamento.', $usuarios->count(), $totalVinculos));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=VincularDiretoresDepartamentoTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Console/Commands tests/Feature/Autorizacao
git add app/Console/Commands/VincularDiretoresDepartamento.php tests/Feature/Autorizacao/VincularDiretoresDepartamentoTest.php
git commit -m "feat(departamentos): comando cema:vincular-diretores-departamento (vínculo dos diretores)"
```

---

### Task 6: Backfill no dev + verificação de não-regressão + PR

**Files:** nenhuma alteração de código — é o gate de fechamento da fase.

**Interfaces:**
- Consumes: todas as tasks anteriores.
- Produces: os vínculos populados no dev; prova de que o `/admin` não regrediu e de que a fase não quebrou nada (suíte verde, Pint limpo).

- [ ] **Step 1: Rodar os backfills no dev (incremental, não destrutivo)**

Run: `docker compose exec -T app php artisan cema:departamentalizar-conteudos`
Expected: loga `Palestra: N`, `Palestrante: N`, `Post: N`, `AgendaDia: N` vinculados (referência: DED ← palestras+palestrantes; DECOM ← posts+agenda-dias).

Run: `docker compose exec -T app php artisan cema:vincular-diretores-departamento`
Expected: loga os diretores vinculados (≤7 departamentos com ocupante; DAS sem vínculo).

Run: `docker compose exec -T app php artisan tinker --execute="echo App\Models\Palestra::whereHas('departamentos')->count().'/'.App\Models\Palestra::count();"`
Expected: imprime `N/N` (todas as palestras com departamento).

- [ ] **Step 2: Resource-tests do painel (guarda de regressão do `/admin`)**

Run: `docker compose exec -T app php artisan test --filter="EventoResourceTest|PostResourceTest|PalestraResourceTest|AgendaDiaResourceTest|PalestranteResourceTest"`
Expected: PASS — o admin cria/edita/lista os cinco recursos no painel exatamente como antes (o Filament não consome as abilities pt-BR; as policies departamentalizadas são inertes para o painel, pois o admin passa no `Gate::before`).

- [ ] **Step 3: Suíte inteira no container**

Run: `docker compose exec -T app php artisan test`
Expected: PASS — o total anterior **mais** os testes novos desta fase. (Ver a memória `flaky-importadorblog-gd-cap-imagem`: 2 testes de cap de imagem do blog podem falhar sob carga; se isolarem/repetirem verde, não é regressão desta fase.)

- [ ] **Step 4: Pint (o CI roda `pint --test` antes dos testes)**

Run: `docker compose exec -T app ./vendor/bin/pint --test`
Expected: PASS (nenhum arquivo com drift de estilo).

- [ ] **Step 5: Push e PR**

```bash
git push -u origin fase-b-departamento
```

Abrir PR contra `main` (título: `feat(departamentos): Fase B — departamento nos conteúdos`), corpo resumindo o SPEC e apontando que: o `/admin` fica intocado (provado pelos resource-tests, incl. `PalestranteResourceTest`); as policies saíram do fail-closed para o molde real; os vínculos foram semeados por dois comandos idempotentes; as permissions seguem **sem** atribuição a papéis (matriz = Fase C). Aguardar o CI verde no commit final antes do merge.

---

## Notas de execução (para o worker)

- **Ordem obrigatória:** Task 1 → 2 → 3 → 4 → 5 → 6. A Task 4 depende de 1 (permissions `<recurso>.*`) e 2 (relações `departamentos()`). A Task 3 depende de 2. A Task 5 depende só da Fase A (`departamento_usuario`, `cargos`), mas roda após a 4 por coesão.
- **Vocabulário antes das policies:** o `setUp` do teste da Task 4 semeia o `CapacidadesSeeder` (20). Sem `palestrante.*` no catálogo, `hasPermissionTo('palestrante.*')` lançaria `PermissionDoesNotExist` — por isso a Task 1 vem primeiro.
- **`hasPermissionTo` vs `can`:** dentro das policies use **sempre** `hasPermissionTo` (flag OFF da Fase A). Não "conserte" trocando por `can`.
- **`criar` é objectless:** sempre invocado com a **classe** (`check('criar', Palestra::class)`), nunca com instância.
- **`syncWithoutDetaching`** nos dois backfills: idempotente e não-destrutivo (preserva vínculos manuais).
- **Nomes de tabela/chaves explícitos** nas relações `departamentos()` (todas nomeiam pivot + FKs) — obrigatório em `AgendaDia` (a convenção nativa daria `agenda_dia_departamento`) e correto nas demais.
- **Nada de destrutivo no banco de dev:** só `php artisan migrate` incremental e os dois comandos de backfill. Nunca `fresh`/`refresh`/`wipe`/`reset`.
- **Pint por-task é OBRIGATÓRIO (não pular):** os novos `use` entram "junto aos demais" sem posição exata, e o `ordered_imports` do preset laravel reordena. Commitar antes do Pint faz o CI (`pint --test`) reprovar. Cada task já roda Pint antes do commit — mantenha.
