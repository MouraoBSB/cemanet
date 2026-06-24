# Fase 1 · Plano 1 — Camada de Dados (Palestras) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar o schema e os models Eloquent do módulo Palestras (palestrantes, assuntos, palestras, pivôs e destaques) com relações e a regra de cardinalidade, tudo coberto por testes.

**Architecture:** Migrations definem 6 tabelas com FKs; models Eloquent expõem as relações (incluindo o pivô `palestra_pessoa` com `papel` e o escopo de visibilidade `ativo`); a regra de cardinalidade vive numa classe de domínio isolada e testável, consumida depois pelo admin e pela importação.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL 8 (dev/prod), SQLite in-memory (testes, já configurado em `phpunit.xml`), Pest/PHPUnit.

## Global Constraints

- Todos os comandos rodam no container: `docker compose exec -T app php artisan ...` (não há PHP no host).
- Idioma pt-BR em identificadores de domínio, comentários, mensagens e **mensagens de commit**.
- Banco só por **migrations** (nunca schema na mão); **FKs sempre**.
- Cardinalidade de palestra: **1–2 `palestrante` (obrigatório) e 0–1 `diretor` (opcional)**.
- `palestrantes.ativo` controla **visibilidade pública** (ativo = aparece; inativo = não aparece).
- Cabeçalho de autoria no topo de cada model novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24`.
- Testes que tocam o banco usam `use Illuminate\Foundation\Testing\RefreshDatabase;`.
- Rodar a suíte: `docker compose exec -T app php artisan test`. Um teste específico:
  `docker compose exec -T app php artisan test --filter=NomeDoTeste`.

---

### Task 1: Model Palestrante (cadastro único de palestrante/diretor)

**Files:**
- Create: `database/migrations/XXXX_create_palestrantes_table.php`
- Create: `app/Models/Palestrante.php`
- Create: `database/factories/PalestranteFactory.php`
- Test: `tests/Feature/Models/PalestranteTest.php`

**Interfaces:**
- Produces: `App\Models\Palestrante` com `$fillable` (`nome, slug, foto, bio, email, telefone, mostrar_email, mostrar_telefone, ativo`), casts dos booleans, `scopeAtivo($query)`. `PalestranteFactory` com `ativo()`/`inativo()` states.

- [ ] **Step 1: Gerar o esqueleto (model + migration + factory)**

Run: `docker compose exec -T app php artisan make:model Palestrante -mf`
Expected: cria `app/Models/Palestrante.php`, a migration e `database/factories/PalestranteFactory.php`.

- [ ] **Step 2: Escrever o teste (falha primeiro)**

Create `tests/Feature/Models/PalestranteTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestranteTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_palestrante_com_atributos(): void
    {
        $p = Palestrante::create([
            'nome' => 'Moisés Andrade',
            'slug' => 'moises-andrade',
            'bio' => '<p>Bio</p>',
            'email' => 'moises@cema.org.br',
            'telefone' => '61999990000',
            'mostrar_email' => true,
            'mostrar_telefone' => false,
            'ativo' => true,
        ]);

        $this->assertDatabaseHas('palestrantes', ['slug' => 'moises-andrade']);
        $this->assertTrue($p->mostrar_email);
        $this->assertFalse($p->mostrar_telefone);
        $this->assertTrue($p->ativo);
    }

    public function test_escopo_ativo_filtra_inativos(): void
    {
        Palestrante::factory()->ativo()->create();
        Palestrante::factory()->inativo()->create();

        $this->assertCount(2, Palestrante::all());
        $this->assertCount(1, Palestrante::ativo()->get());
    }
}
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteTest`
Expected: FAIL (tabela `palestrantes` não existe / método `ativo` ausente).

- [ ] **Step 4: Escrever a migration**

In the generated `..._create_palestrantes_table.php`, set `up()`:

```php
public function up(): void
{
    Schema::create('palestrantes', function (Blueprint $table) {
        $table->id();
        $table->string('nome');
        $table->string('slug')->unique();
        $table->string('foto')->nullable();
        $table->longText('bio')->nullable();
        $table->string('email')->nullable();
        $table->string('telefone')->nullable();
        $table->boolean('mostrar_email')->default(false);
        $table->boolean('mostrar_telefone')->default(false);
        $table->boolean('ativo')->default(true);
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Escrever o model**

`app/Models/Palestrante.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Palestrante extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome', 'slug', 'foto', 'bio', 'email', 'telefone',
        'mostrar_email', 'mostrar_telefone', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'mostrar_email' => 'boolean',
            'mostrar_telefone' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }
}
```

- [ ] **Step 6: Escrever a factory**

`database/factories/PalestranteFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PalestranteFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->name();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'bio' => '<p>'.fake()->paragraph().'</p>',
            'email' => fake()->safeEmail(),
            'telefone' => fake()->numerify('61#########'),
            'mostrar_email' => false,
            'mostrar_telefone' => false,
            'ativo' => true,
        ];
    }

    public function ativo(): static
    {
        return $this->state(fn () => ['ativo' => true]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
```

- [ ] **Step 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteTest`
Expected: PASS (2 testes).

- [ ] **Step 8: Commit**

```bash
git add app/Models/Palestrante.php database/migrations database/factories/PalestranteFactory.php tests/Feature/Models/PalestranteTest.php
git commit -m "feat(palestras): model Palestrante com slug, contato e escopo ativo"
```

---

### Task 2: Model Assunto (taxonomia hierárquica)

**Files:**
- Create: `database/migrations/XXXX_create_assuntos_table.php`
- Create: `app/Models/Assunto.php`
- Create: `database/factories/AssuntoFactory.php`
- Test: `tests/Feature/Models/AssuntoTest.php`

**Interfaces:**
- Produces: `App\Models\Assunto` com `$fillable` (`nome, slug, parent_id`), relações `parent()` (belongsTo self) e `children()` (hasMany self).

- [ ] **Step 1: Gerar o esqueleto**

Run: `docker compose exec -T app php artisan make:model Assunto -mf`

- [ ] **Step 2: Escrever o teste (falha primeiro)**

Create `tests/Feature/Models/AssuntoTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Assunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssuntoTest extends TestCase
{
    use RefreshDatabase;

    public function test_assunto_tem_pai_e_filhos(): void
    {
        $pai = Assunto::create(['nome' => 'Espiritismo', 'slug' => 'espiritismo']);
        $filho = Assunto::create(['nome' => 'Fé', 'slug' => 'fe', 'parent_id' => $pai->id]);

        $this->assertTrue($filho->parent->is($pai));
        $this->assertTrue($pai->children->contains($filho));
        $this->assertNull($pai->parent);
    }
}
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AssuntoTest`
Expected: FAIL.

- [ ] **Step 4: Escrever a migration**

```php
public function up(): void
{
    Schema::create('assuntos', function (Blueprint $table) {
        $table->id();
        $table->string('nome');
        $table->string('slug')->unique();
        $table->foreignId('parent_id')->nullable()->constrained('assuntos')->nullOnDelete();
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Escrever o model**

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assunto extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'slug', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Assunto::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Assunto::class, 'parent_id');
    }
}
```

- [ ] **Step 6: Escrever a factory**

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssuntoFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->unique()->words(2, true);

        return [
            'nome' => ucfirst($nome),
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'parent_id' => null,
        ];
    }
}
```

- [ ] **Step 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AssuntoTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Assunto.php database/migrations database/factories/AssuntoFactory.php tests/Feature/Models/AssuntoTest.php
git commit -m "feat(palestras): model Assunto (taxonomia hierárquica)"
```

---

### Task 3: Model Palestra (campos e casts)

**Files:**
- Create: `database/migrations/XXXX_create_palestras_table.php`
- Create: `app/Models/Palestra.php`
- Create: `database/factories/PalestraFactory.php`
- Test: `tests/Feature/Models/PalestraTest.php`

**Interfaces:**
- Produces: `App\Models\Palestra` com `$fillable` (`titulo, slug, subtitulo, resumo, descricao, data_da_palestra, online, link_youtube, cor_fundo, publico_online, publico_presencial, publico_total, status`), casts (`data_da_palestra` datetime, `online` bool, públicos int), `scopePublicado`. Const `STATUS_PUBLICADO = 'publicado'`, `STATUS_RASCUNHO = 'rascunho'`.

- [ ] **Step 1: Gerar o esqueleto**

Run: `docker compose exec -T app php artisan make:model Palestra -mf`

- [ ] **Step 2: Escrever o teste (falha primeiro)**

Create `tests/Feature/Models/PalestraTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_palestra_com_casts(): void
    {
        $p = Palestra::create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'subtitulo' => 'Precisamos fazer a nossa parte',
            'descricao' => '<p>Corpo</p>',
            'data_da_palestra' => '2026-05-31 19:00:00',
            'online' => true,
            'link_youtube' => 'https://youtube.com/live/abc',
            'cor_fundo' => '#89ab98',
            'publico_total' => 120,
            'status' => Palestra::STATUS_PUBLICADO,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $p->data_da_palestra);
        $this->assertTrue($p->online);
        $this->assertSame(120, $p->publico_total);
    }

    public function test_escopo_publicado(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['status' => Palestra::STATUS_RASCUNHO]);

        $this->assertCount(1, Palestra::publicado()->get());
    }
}
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestraTest`
Expected: FAIL.

- [ ] **Step 4: Escrever a migration**

```php
public function up(): void
{
    Schema::create('palestras', function (Blueprint $table) {
        $table->id();
        $table->string('titulo');
        $table->string('slug')->unique();
        $table->string('subtitulo')->nullable();
        $table->text('resumo')->nullable();
        $table->longText('descricao')->nullable();
        $table->dateTime('data_da_palestra');
        $table->boolean('online')->default(false);
        $table->string('link_youtube')->nullable();
        $table->string('cor_fundo')->nullable();
        $table->integer('publico_online')->nullable();
        $table->integer('publico_presencial')->nullable();
        $table->integer('publico_total')->nullable();
        $table->string('status')->default('publicado');
        $table->timestamps();

        $table->index('data_da_palestra');
        $table->index('status');
    });
}
```

- [ ] **Step 5: Escrever o model**

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Palestra extends Model
{
    use HasFactory;

    public const STATUS_PUBLICADO = 'publicado';
    public const STATUS_RASCUNHO = 'rascunho';

    protected $fillable = [
        'titulo', 'slug', 'subtitulo', 'resumo', 'descricao', 'data_da_palestra',
        'online', 'link_youtube', 'cor_fundo', 'publico_online', 'publico_presencial',
        'publico_total', 'status',
    ];

    protected function casts(): array
    {
        return [
            'data_da_palestra' => 'datetime',
            'online' => 'boolean',
            'publico_online' => 'integer',
            'publico_presencial' => 'integer',
            'publico_total' => 'integer',
        ];
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }
}
```

- [ ] **Step 6: Escrever a factory**

```php
<?php

namespace Database\Factories;

use App\Models\Palestra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PalestraFactory extends Factory
{
    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(3);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 99999),
            'subtitulo' => fake()->sentence(),
            'descricao' => '<p>'.fake()->paragraph().'</p>',
            'data_da_palestra' => fake()->dateTimeBetween('-2 years', 'now'),
            'online' => fake()->boolean(),
            'link_youtube' => 'https://youtube.com/live/'.fake()->lexify('???????'),
            'cor_fundo' => fake()->hexColor(),
            'publico_total' => fake()->numberBetween(0, 300),
            'status' => Palestra::STATUS_PUBLICADO,
        ];
    }
}
```

- [ ] **Step 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestraTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Palestra.php database/migrations database/factories/PalestraFactory.php tests/Feature/Models/PalestraTest.php
git commit -m "feat(palestras): model Palestra com casts e escopo publicado"
```

---

### Task 4: Pivôs e relações (palestra_pessoa, assunto_palestra)

**Files:**
- Create: `database/migrations/XXXX_create_palestra_pessoa_table.php`
- Create: `database/migrations/XXXX_create_assunto_palestra_table.php`
- Modify: `app/Models/Palestra.php` (relações)
- Modify: `app/Models/Palestrante.php` (relação)
- Test: `tests/Feature/Models/RelacoesPalestraTest.php`

**Interfaces:**
- Consumes: `Palestra`, `Palestrante`, `Assunto` (Tasks 1–3).
- Produces: em `Palestra` — `palestrantes()` (belongsToMany com `withPivot('papel')`), `assuntos()` (belongsToMany), `palestrantesAtivos()` (papel=palestrante + pessoa ativa), `diretor()` (primeira pessoa papel=diretor, ou null). Em `Palestrante` — `palestras()` (belongsToMany com papel). Const `PAPEL_PALESTRANTE = 'palestrante'`, `PAPEL_DIRETOR = 'diretor'` em `Palestra`.

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Feature/Models/RelacoesPalestraTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelacoesPalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_palestrantes_diretor_e_assuntos(): void
    {
        $palestra = Palestra::factory()->create();
        $ativo = Palestrante::factory()->ativo()->create();
        $inativo = Palestrante::factory()->inativo()->create();
        $diretor = Palestrante::factory()->inativo()->create();
        $assunto = Assunto::factory()->create();

        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($inativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->assuntos()->attach($assunto);

        $this->assertCount(3, $palestra->palestrantes);
        $this->assertCount(1, $palestra->palestrantesAtivos); // só o ativo com papel palestrante
        $this->assertTrue($palestra->diretor->is($diretor));
        $this->assertTrue($palestra->assuntos->contains($assunto));
        $this->assertSame(
            Palestra::PAPEL_PALESTRANTE,
            $ativo->palestras->first()->pivot->papel
        );
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=RelacoesPalestraTest`
Expected: FAIL.

- [ ] **Step 3: Migration do pivô palestra_pessoa**

Run: `docker compose exec -T app php artisan make:migration create_palestra_pessoa_table`
Set `up()`:

```php
public function up(): void
{
    Schema::create('palestra_pessoa', function (Blueprint $table) {
        $table->id();
        $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
        $table->foreignId('pessoa_id')->constrained('palestrantes')->cascadeOnDelete();
        $table->enum('papel', ['palestrante', 'diretor']);
        $table->timestamps();

        $table->unique(['palestra_id', 'pessoa_id', 'papel']);
    });
}
```

- [ ] **Step 4: Migration do pivô assunto_palestra**

Run: `docker compose exec -T app php artisan make:migration create_assunto_palestra_table`
Set `up()`:

```php
public function up(): void
{
    Schema::create('assunto_palestra', function (Blueprint $table) {
        $table->id();
        $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
        $table->foreignId('assunto_id')->constrained('assuntos')->cascadeOnDelete();
        $table->unique(['palestra_id', 'assunto_id']);
    });
}
```

- [ ] **Step 5: Relações em Palestra**

Add to `app/Models/Palestra.php` (constants near the top, methods in the body):

```php
    public const PAPEL_PALESTRANTE = 'palestrante';
    public const PAPEL_DIRETOR = 'diretor';

    public function palestrantes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Palestrante::class, 'palestra_pessoa', 'palestra_id', 'pessoa_id')
            ->withPivot('papel')
            ->withTimestamps();
    }

    public function palestrantesAtivos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->palestrantes()
            ->wherePivot('papel', self::PAPEL_PALESTRANTE)
            ->where('ativo', true);
    }

    public function assuntos(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Assunto::class, 'assunto_palestra', 'palestra_id', 'assunto_id');
    }

    public function getDiretorAttribute(): ?Palestrante
    {
        return $this->palestrantes->firstWhere('pivot.papel', self::PAPEL_DIRETOR);
    }
```

- [ ] **Step 6: Relação em Palestrante**

Add to `app/Models/Palestrante.php`:

```php
    public function palestras(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Palestra::class, 'palestra_pessoa', 'pessoa_id', 'palestra_id')
            ->withPivot('papel')
            ->withTimestamps();
    }
```

- [ ] **Step 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=RelacoesPalestraTest`
Expected: PASS. (Se `diretor` falhar por lazy-load, garanta `$palestra->load('palestrantes')` no acessor já está coberto pois o acessor lê `$this->palestrantes`.)

- [ ] **Step 8: Commit**

```bash
git add app/Models/Palestra.php app/Models/Palestrante.php database/migrations tests/Feature/Models/RelacoesPalestraTest.php
git commit -m "feat(palestras): pivôs palestra_pessoa/assunto_palestra e relações (papel, ativos, diretor)"
```

---

### Task 5: Model PalestraDestaque (repeater ordenado)

**Files:**
- Create: `database/migrations/XXXX_create_palestra_destaques_table.php`
- Create: `app/Models/PalestraDestaque.php`
- Modify: `app/Models/Palestra.php` (relação hasMany)
- Test: `tests/Feature/Models/PalestraDestaqueTest.php`

**Interfaces:**
- Consumes: `Palestra` (Task 3).
- Produces: `App\Models\PalestraDestaque` (`$fillable = ['palestra_id','destaque','texto','ordem']`); em `Palestra` — `destaques()` (hasMany, ordenado por `ordem`).

- [ ] **Step 1: Gerar o esqueleto**

Run: `docker compose exec -T app php artisan make:model PalestraDestaque -m`

- [ ] **Step 2: Escrever o teste (falha primeiro)**

Create `tests/Feature/Models/PalestraDestaqueTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\PalestraDestaque;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraDestaqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_destaques_vem_ordenados(): void
    {
        $palestra = Palestra::factory()->create();
        PalestraDestaque::create(['palestra_id' => $palestra->id, 'destaque' => 'B', 'texto' => 't', 'ordem' => 1]);
        PalestraDestaque::create(['palestra_id' => $palestra->id, 'destaque' => 'A', 'texto' => 't', 'ordem' => 0]);

        $this->assertSame(['A', 'B'], $palestra->destaques->pluck('destaque')->all());
    }
}
```

- [ ] **Step 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestraDestaqueTest`
Expected: FAIL.

- [ ] **Step 4: Escrever a migration**

```php
public function up(): void
{
    Schema::create('palestra_destaques', function (Blueprint $table) {
        $table->id();
        $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
        $table->string('destaque');
        $table->text('texto');
        $table->unsignedInteger('ordem')->default(0);
        $table->timestamps();
    });
}
```

- [ ] **Step 5: Escrever o model**

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalestraDestaque extends Model
{
    protected $fillable = ['palestra_id', 'destaque', 'texto', 'ordem'];

    public function palestra(): BelongsTo
    {
        return $this->belongsTo(Palestra::class);
    }
}
```

- [ ] **Step 6: Relação em Palestra**

Add to `app/Models/Palestra.php`:

```php
    public function destaques(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PalestraDestaque::class)->orderBy('ordem');
    }
```

- [ ] **Step 7: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestraDestaqueTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/PalestraDestaque.php app/Models/Palestra.php database/migrations tests/Feature/Models/PalestraDestaqueTest.php
git commit -m "feat(palestras): model PalestraDestaque (repeater ordenado)"
```

---

### Task 6: Regra de cardinalidade (1–2 palestrantes / 0–1 diretor)

**Files:**
- Create: `app/Support/Palestras/CardinalidadePalestra.php`
- Test: `tests/Unit/Palestras/CardinalidadePalestraTest.php`

**Interfaces:**
- Produces: `App\Support\Palestras\CardinalidadePalestra` com `public static function erros(int $palestrantes, int $diretores): array` — retorna array de mensagens (vazio = válido). Será consumida pelo FormRequest do Filament e pelo comando de importação (planos seguintes).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Palestras/CardinalidadePalestraTest.php`:

```php
<?php

namespace Tests\Unit\Palestras;

use App\Support\Palestras\CardinalidadePalestra;
use PHPUnit\Framework\TestCase;

class CardinalidadePalestraTest extends TestCase
{
    public function test_um_ou_dois_palestrantes_e_ate_um_diretor_e_valido(): void
    {
        $this->assertSame([], CardinalidadePalestra::erros(1, 0));
        $this->assertSame([], CardinalidadePalestra::erros(2, 1));
    }

    public function test_zero_palestrantes_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(0, 0));
    }

    public function test_tres_palestrantes_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(3, 0));
    }

    public function test_dois_diretores_e_invalido(): void
    {
        $this->assertNotEmpty(CardinalidadePalestra::erros(1, 2));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CardinalidadePalestraTest`
Expected: FAIL (classe não existe).

- [ ] **Step 3: Escrever a classe de domínio**

`app/Support/Palestras/CardinalidadePalestra.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Support\Palestras;

class CardinalidadePalestra
{
    /**
     * Valida a regra de negócio: 1–2 palestrantes (obrigatório) e 0–1 diretor (opcional).
     * Retorna as mensagens de erro (vazio = válido).
     */
    public static function erros(int $palestrantes, int $diretores): array
    {
        $erros = [];

        if ($palestrantes < 1 || $palestrantes > 2) {
            $erros[] = 'A palestra deve ter 1 ou 2 palestrantes.';
        }

        if ($diretores > 1) {
            $erros[] = 'A palestra pode ter no máximo 1 diretor.';
        }

        return $erros;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=CardinalidadePalestraTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Rodar a suíte inteira (regressão)**

Run: `docker compose exec -T app php artisan test`
Expected: PASS (todos — models + cardinalidade + os ExampleTests).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Palestras/CardinalidadePalestra.php tests/Unit/Palestras/CardinalidadePalestraTest.php
git commit -m "feat(palestras): regra de cardinalidade (1-2 palestrantes / 0-1 diretor)"
```

---

## Verificação final do Plano 1

- [ ] `docker compose exec -T app php artisan migrate:fresh` roda sem erro no MySQL de dev (valida as migrations fora do SQLite de teste).
- [ ] `docker compose exec -T app php artisan test` verde.
- [ ] `docker compose exec -T app ./vendor/bin/pint --test` sem violações de estilo.

> **Próximo plano:** Importação (`cema:importar-palestras`) — lê o banco `legado`, faz upsert por slug,
> resolve relações Jet (107/108, direções opostas), `unserialize` do repeater e baixa as imagens.
