# Eventos — Fase 1 (Dados + Admin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a base de dados do módulo Eventos (tabelas `categorias`, `eventos`, pivot `departamento_evento`, cor/ícone em `departamentos`) e o admin Filament (CRUD de Evento e Categoria), deixando o dono capaz de cadastrar eventos com data início/fim, hora início/fim, categoria, departamentos e visibilidade.

**Architecture:** Fatia vertical espelhando o molde de Palestras. Datas via mutator `Attribute` (Carbon↔`Y-m-d`, portável SQLite×MySQL); horas como `string(5)` `HH:MM` normalizadas no mutator; visibilidade como enum PHP nativo com nível mínimo; mídia (flyer + galeria) via trait `RegistraImagensPadrao`; validação de período numa classe pura `App\Support\Eventos\PeriodoEvento`. Categoria (belongsTo) e Departamentos (belongsToMany) usam o binding nativo de relacionamento do Filament (sem trait de sync).

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) · SQLite (testes) · spatie/laravel-medialibrary · spatie/laravel-permission · mews/purifier (`clean()`).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, labels, mensagens, comentários, commits). Sintaxe/APIs de terceiros no original.
- **Banco:** só `php artisan migrate` **incremental**. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e seed/factory destrutivo (apagam os 123 palestras/44 posts importados). Conferir tabela/coluna existente antes de criar.
- **Datas:** colunas `date` usam mutator `Attribute` (get→Carbon, set→`Y-m-d` string); consultar/comparar por string `Y-m-d`. **NUNCA** cast nativo `date`.
- **Horas:** `string(5)` `HH:MM` zero-padded; validar `^([01]\d|2[0-3]):[0-5]\d$`.
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08` em todo arquivo PHP novo.
- **Ferramentas:** `artisan`/`pint`/`composer` rodam **no container** (`docker compose exec -T app ...`); o projeto **não** usa Sail. `npm`/Vite rodam no host (irrelevante nesta fase).
- **Qualidade:** rodar `./vendor/bin/pint` antes de qualquer push (o CI faz `pint --test` e aborta em drift); suíte de testes no container.
- **Commits:** atômicos, descritivos, em pt-BR, na branch `modulo-eventos`.

---

### Task 1: Tabela `categorias` + model `Categoria` + seeder

**Files:**
- Create: `database/migrations/2026_07_08_000001_create_categorias_table.php`
- Create: `app/Models/Categoria.php`
- Create: `database/seeders/CategoriaSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (registrar `CategoriaSeeder`)
- Test: `tests/Feature/Eventos/CategoriaSeederTest.php`

**Interfaces:**
- Produces: `App\Models\Categoria` (`$fillable` = nome, slug, cor, cor_texto, icone, ordem, ativo; `casts` ativo→bool, ordem→int; `scopeAtivo(Builder): Builder`; `eventos(): HasMany`). `CategoriaSeeder::CATEGORIAS` (const `slug => [nome, cor, cor_texto]`). Tabela `categorias`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Models\Categoria;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_semeia_as_cinco_categorias_e_e_idempotente(): void
    {
        $this->seed(CategoriaSeeder::class);
        $this->seed(CategoriaSeeder::class); // 2ª vez não duplica

        $this->assertSame(5, Categoria::count());

        $brecho = Categoria::where('slug', 'brecho')->first();
        $this->assertSame('Brechó Solidário', $brecho->nome);
        $this->assertSame('#89AB98', $brecho->cor);
        $this->assertSame('#26242E', $brecho->cor_texto);
        $this->assertTrue($brecho->ativo);
    }

    public function test_scope_ativo_filtra_inativas(): void
    {
        $this->seed(CategoriaSeeder::class);
        Categoria::where('slug', 'estudo')->update(['ativo' => false]);

        $this->assertSame(4, Categoria::ativo()->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CategoriaSeederTest`
Expected: FAIL (classe `Categoria`/`CategoriaSeeder` inexistente; tabela `categorias` ausente).

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_08_000001_create_categorias_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('cor', 7);
            $table->string('cor_texto', 7)->nullable();
            $table->string('icone')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
```

- [ ] **Step 4: Create the `Categoria` model**

`app/Models/Categoria.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $fillable = ['nome', 'slug', 'cor', 'cor_texto', 'icone', 'ordem', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'ordem' => 'integer'];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
```

- [ ] **Step 5: Create the seeder and register it**

`database/seeders/CategoriaSeeder.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /** As 5 categorias públicas de evento: slug => [nome, cor de fundo, cor do texto]. */
    public const CATEGORIAS = [
        'brecho' => ['Brechó Solidário', '#89AB98', '#26242E'],
        'feirao' => ['Feirão de Livros', '#6E9FCB', '#26242E'],
        'familia' => ['Encontro & Família', '#E79048', '#26242E'],
        'campanha' => ['Campanha', '#F2A81E', '#3A3266'],
        'estudo' => ['Estudo & Curso', '#4E4483', '#FFFFFF'],
    ];

    public function run(): void
    {
        $ordem = 0;
        foreach (self::CATEGORIAS as $slug => [$nome, $cor, $corTexto]) {
            Categoria::updateOrCreate(
                ['slug' => $slug],
                ['nome' => $nome, 'cor' => $cor, 'cor_texto' => $corTexto, 'ordem' => $ordem++],
            );
        }
    }
}
```

Em `database/seeders/DatabaseSeeder.php`, dentro de `run()`, adicionar a chamada (depois das existentes):

```php
$this->call(CategoriaSeeder::class);
```

- [ ] **Step 6: Run migration and test to verify PASS**

Run: `docker compose exec -T app php artisan migrate && docker compose exec -T app php artisan test --filter=CategoriaSeederTest`
Expected: migration `2026_07_08_000001_create_categorias_table` aplicada; testes PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_08_000001_create_categorias_table.php app/Models/Categoria.php database/seeders/CategoriaSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Eventos/CategoriaSeederTest.php
git commit -m "feat(eventos): tabela categorias + model + seeder das 5 categorias"
```

---

### Task 2: Enum `VisibilidadeEvento`

**Files:**
- Create: `app/Enums/VisibilidadeEvento.php`
- Test: `tests/Unit/Enums/VisibilidadeEventoTest.php`

**Interfaces:**
- Produces: `App\Enums\VisibilidadeEvento` (string enum: `Publico='publico'`, `Logados='logados'`, `Trabalhadores='trabalhadores'`, `Diretoria='diretoria'`; `nivelMinimo(): int`; `rotulo(): string`; `opcoes(): array` value→rótulo). Consumido pelo cast de `Evento::visibilidade` (Task 5), pelo `EventoResource` (Task 6) e pela autorização (Fase 3).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Enums;

use App\Enums\VisibilidadeEvento;
use PHPUnit\Framework\TestCase;

class VisibilidadeEventoTest extends TestCase
{
    public function test_niveis_minimos_sao_a_hierarquia_de_papeis(): void
    {
        $this->assertSame(0, VisibilidadeEvento::Publico->nivelMinimo());
        $this->assertSame(10, VisibilidadeEvento::Logados->nivelMinimo());
        $this->assertSame(20, VisibilidadeEvento::Trabalhadores->nivelMinimo());
        $this->assertSame(30, VisibilidadeEvento::Diretoria->nivelMinimo());
    }

    public function test_opcoes_mapeia_valor_para_rotulo(): void
    {
        $opcoes = VisibilidadeEvento::opcoes();

        $this->assertSame('Público', $opcoes['publico']);
        $this->assertSame('Somente diretoria', $opcoes['diretoria']);
        $this->assertCount(4, $opcoes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeEventoTest`
Expected: FAIL (enum inexistente).

- [ ] **Step 3: Create the enum**

`app/Enums/VisibilidadeEvento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Enums;

enum VisibilidadeEvento: string
{
    case Publico = 'publico';
    case Logados = 'logados';
    case Trabalhadores = 'trabalhadores';
    case Diretoria = 'diretoria';

    /** Nível mínimo (roles.nivel) exigido para ver o evento; 0 = qualquer visitante. */
    public function nivelMinimo(): int
    {
        return match ($this) {
            self::Publico => 0,
            self::Logados => 10,
            self::Trabalhadores => 20,
            self::Diretoria => 30,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Publico => 'Público',
            self::Logados => 'Somente logados',
            self::Trabalhadores => 'Trabalhadores e diretoria',
            self::Diretoria => 'Somente diretoria',
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

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeEventoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums/VisibilidadeEvento.php tests/Unit/Enums/VisibilidadeEventoTest.php
git commit -m "feat(eventos): enum VisibilidadeEvento com nivel minimo"
```

---

### Task 3: Classe pura `App\Support\Eventos\PeriodoEvento`

**Files:**
- Create: `app/Support/Eventos/PeriodoEvento.php`
- Test: `tests/Unit/Support/Eventos/PeriodoEventoTest.php`

**Interfaces:**
- Produces:
  - `PeriodoEvento::erros(?string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): array` — mensagens pt-BR (vazio = válido). Consumido pela importação (Fase 2) e como referência de validação.
  - `PeriodoEvento::formata(string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): string` — período por extenso pt-BR. Consumido por `Evento::getPeriodoAttribute()` (Task 5).
  - `PeriodoEvento::horaValida(string $hora): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Support\Eventos;

use App\Support\Eventos\PeriodoEvento;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PeriodoEventoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setLocale('pt_BR');
    }

    public function test_hora_valida(): void
    {
        $this->assertTrue(PeriodoEvento::horaValida('08:30'));
        $this->assertTrue(PeriodoEvento::horaValida('23:59'));
        $this->assertFalse(PeriodoEvento::horaValida('8:30'));   // sem zero à esquerda
        $this->assertFalse(PeriodoEvento::horaValida('25:00'));  // hora inválida
        $this->assertFalse(PeriodoEvento::horaValida('12:60'));  // minuto inválido
    }

    public function test_erros_exige_data_inicio(): void
    {
        $this->assertNotEmpty(PeriodoEvento::erros(null, null, null, null));
        $this->assertSame([], PeriodoEvento::erros('2026-06-27', null, null, null));
    }

    public function test_erros_data_fim_anterior(): void
    {
        $erros = PeriodoEvento::erros('2026-06-27', null, '2026-06-25', null);
        $this->assertContains('A data de término não pode ser anterior à data de início.', $erros);
    }

    public function test_erros_hora_fim_antes_no_mesmo_dia(): void
    {
        $erros = PeriodoEvento::erros('2026-06-27', '10:00', '2026-06-27', '09:00');
        $this->assertContains('No mesmo dia, a hora de término deve ser posterior à de início.', $erros);
    }

    public function test_erros_hora_formato_invalido(): void
    {
        $this->assertNotEmpty(PeriodoEvento::erros('2026-06-27', '8:30', null, null));
    }

    public function test_formata_dia_unico_com_hora(): void
    {
        $this->assertSame('27 de junho de 2026 · 8h30 – 12h',
            PeriodoEvento::formata('2026-06-27', '08:30', null, '12:00'));
    }

    public function test_formata_dia_unico_sem_hora_e_dia_inteiro(): void
    {
        $this->assertSame('27 de junho de 2026',
            PeriodoEvento::formata('2026-06-27', null, '2026-06-27', null));
    }

    public function test_formata_multi_dia_mesmo_mes(): void
    {
        $this->assertSame('27 a 29 de junho de 2026',
            PeriodoEvento::formata('2026-06-27', null, '2026-06-29', null));
    }

    public function test_formata_multi_dia_meses_diferentes(): void
    {
        $this->assertSame('30 de junho a 2 de julho de 2026',
            PeriodoEvento::formata('2026-06-30', null, '2026-07-02', null));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=PeriodoEventoTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Create the class**

`app/Support/Eventos/PeriodoEvento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Support\Eventos;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Regras de período de um evento (data/hora início–fim), em classe pura e testável.
 * Datas comparadas como string Y-m-d (portável); horas como string HH:MM zero-padded.
 */
class PeriodoEvento
{
    public static function horaValida(string $hora): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora);
    }

    /** Mensagens de erro de validação (vazio = válido). */
    public static function erros(?string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): array
    {
        $erros = [];

        if ($dataInicio === null || $dataInicio === '') {
            $erros[] = 'A data de início é obrigatória.';

            return $erros; // sem início, nada a comparar
        }

        foreach (['hora de início' => $horaInicio, 'hora de término' => $horaFim] as $rotulo => $hora) {
            if ($hora !== null && $hora !== '' && ! self::horaValida($hora)) {
                $erros[] = "A {$rotulo} deve estar no formato HH:MM (00:00–23:59).";
            }
        }

        if ($dataFim !== null && $dataFim !== '' && $dataFim < $dataInicio) {
            $erros[] = 'A data de término não pode ser anterior à data de início.';
        }

        $mesmoDia = ($dataFim === null || $dataFim === '' || $dataFim === $dataInicio);
        if ($mesmoDia
            && $horaInicio !== null && $horaInicio !== '' && self::horaValida($horaInicio)
            && $horaFim !== null && $horaFim !== '' && self::horaValida($horaFim)
            && $horaFim <= $horaInicio) {
            $erros[] = 'No mesmo dia, a hora de término deve ser posterior à de início.';
        }

        return $erros;
    }

    /** Período por extenso em pt-BR (ex.: "27 de junho de 2026 · 8h30 – 12h"). */
    public static function formata(string $dataInicio, ?string $horaInicio, ?string $dataFim, ?string $horaFim): string
    {
        $inicio = Carbon::parse($dataInicio);
        $fim = ($dataFim !== null && $dataFim !== '') ? Carbon::parse($dataFim) : $inicio;

        if ($inicio->isSameDay($fim)) {
            $data = self::dataExtenso($inicio);
            $faixa = self::faixaHoraria($horaInicio, $horaFim);

            return $faixa !== '' ? "{$data} · {$faixa}" : $data;
        }

        return self::intervaloDatas($inicio, $fim);
    }

    private static function dataExtenso(Carbon $d): string
    {
        return Str::ucfirst($d->translatedFormat('j \d\e F \d\e Y'));
    }

    private static function faixaHoraria(?string $horaInicio, ?string $horaFim): string
    {
        if ($horaInicio === null || $horaInicio === '') {
            return '';
        }

        $inicio = self::horaBr($horaInicio);

        return ($horaFim !== null && $horaFim !== '')
            ? "{$inicio} – ".self::horaBr($horaFim)
            : $inicio;
    }

    /** "08:30" → "8h30"; "12:00" → "12h". */
    private static function horaBr(string $hora): string
    {
        [$h, $m] = explode(':', $hora);
        $h = (int) $h;

        return $m === '00' ? "{$h}h" : "{$h}h{$m}";
    }

    private static function intervaloDatas(Carbon $i, Carbon $f): string
    {
        if ($i->year !== $f->year) {
            return Str::ucfirst($i->translatedFormat('j \d\e F \d\e Y')).' a '.$f->translatedFormat('j \d\e F \d\e Y');
        }

        if ($i->month !== $f->month) {
            return Str::ucfirst($i->translatedFormat('j \d\e F')).' a '.$f->translatedFormat('j \d\e F \d\e Y');
        }

        return Str::ucfirst($i->translatedFormat('j')).' a '.$f->translatedFormat('j \d\e F \d\e Y');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=PeriodoEventoTest`
Expected: PASS (9 testes).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Eventos/PeriodoEvento.php tests/Unit/Support/Eventos/PeriodoEventoTest.php
git commit -m "feat(eventos): PeriodoEvento (validacao + formatacao pt-BR do periodo)"
```

---

### Task 4: `departamentos` ganha cor/ícone + relação com eventos

**Files:**
- Create: `database/migrations/2026_07_08_000002_add_cor_icone_to_departamentos_table.php`
- Modify: `app/Models/Departamento.php`
- Test: `tests/Feature/Eventos/DepartamentoCorTest.php`

**Interfaces:**
- Consumes: `App\Models\Departamento` (existente).
- Produces: colunas `departamentos.cor` (string 7, null) e `departamentos.icone` (string, null); `Departamento::$fillable` inclui `cor`,`icone`; `Departamento::eventos(): BelongsToMany` (via `departamento_evento`; a tabela pivot vem na Task 5 — o método só é exercitado a partir da Task 5).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartamentoCorTest extends TestCase
{
    use RefreshDatabase;

    public function test_departamento_persiste_cor_e_icone(): void
    {
        $depto = Departamento::create([
            'sigla' => 'DEPRO', 'nome' => 'Promoções e Eventos', 'slug' => 'depro',
            'cor' => '#4E4483', 'icone' => 'calendar',
        ]);

        $this->assertSame('#4E4483', $depto->fresh()->cor);
        $this->assertSame('calendar', $depto->fresh()->icone);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=DepartamentoCorTest`
Expected: FAIL (colunas `cor`/`icone` inexistentes; não estão no `$fillable`).

- [ ] **Step 3: Create the migration**

`database/migrations/2026_07_08_000002_add_cor_icone_to_departamentos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('cor', 7)->nullable()->after('descricao');
            $table->string('icone')->nullable()->after('cor');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn(['cor', 'icone']);
        });
    }
};
```

- [ ] **Step 4: Modify the `Departamento` model**

Em `app/Models/Departamento.php`: (a) incluir `cor` e `icone` no `$fillable`; (b) adicionar a relação `eventos()`. Resultado:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $fillable = ['sigla', 'nome', 'slug', 'descricao', 'cor', 'icone', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'departamento_evento', 'departamento_id', 'evento_id');
    }
}
```

- [ ] **Step 5: Run migration and test to verify PASS**

Run: `docker compose exec -T app php artisan migrate && docker compose exec -T app php artisan test --filter=DepartamentoCorTest`
Expected: migration `..._add_cor_icone_to_departamentos_table` aplicada; teste PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_08_000002_add_cor_icone_to_departamentos_table.php app/Models/Departamento.php tests/Feature/Eventos/DepartamentoCorTest.php
git commit -m "feat(eventos): cor/icone em departamentos + relacao eventos"
```

---

### Task 5: Tabela `eventos` + pivot `departamento_evento` + model `Evento`

**Files:**
- Create: `database/migrations/2026_07_08_000003_create_eventos_table.php`
- Create: `database/migrations/2026_07_08_000004_create_departamento_evento_table.php`
- Create: `app/Models/Evento.php`
- Test: `tests/Feature/Eventos/EventoModelTest.php`

**Interfaces:**
- Consumes: `Categoria` (Task 1), `Departamento` (Task 4), `VisibilidadeEvento` (Task 2), `PeriodoEvento` (Task 3), trait `RegistraImagensPadrao`.
- Produces: `App\Models\Evento implements HasMedia` — `$fillable` (titulo, slug, resumo, conteudo, data_inicio, hora_inicio, data_fim, hora_fim, local, categoria_id, visibilidade, status, wp_id); consts `STATUS_PUBLICADO`/`STATUS_RASCUNHO`, `COLECAO_FLYER='flyer'`/`COLECAO_GALERIA='galeria'`; cast `visibilidade => VisibilidadeEvento`; mutators de `data_inicio`/`data_fim` (Carbon↔`Y-m-d`), `hora_inicio`/`hora_fim` (normaliza `HH:MM`), `conteudo` (sanitiza); `scopePublicado`; relações `categoria(): BelongsTo`, `departamentos(): BelongsToMany`; accessor `periodo`; accessor `flyerUrl`. Tabelas `eventos` e `departamento_evento`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventoModelTest extends TestCase
{
    use RefreshDatabase;

    private function eventoBase(array $overrides = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó Solidário',
            'slug' => 'brecho-solidario',
            'data_inicio' => '2026-06-27',
            'status' => Evento::STATUS_PUBLICADO,
        ], $overrides));
    }

    public function test_data_inicio_grava_string_e_le_carbon(): void
    {
        $evento = $this->eventoBase(['data_inicio' => Carbon::parse('2026-06-27 15:00')]);

        // grava só a data (Y-m-d), sem hora
        $this->assertSame('2026-06-27', $evento->getRawOriginal('data_inicio'));
        $this->assertInstanceOf(Carbon::class, $evento->fresh()->data_inicio);
    }

    public function test_hora_e_normalizada_para_hh_mm(): void
    {
        $evento = $this->eventoBase(['hora_inicio' => '8:30', 'hora_fim' => '12:00:00']);

        $this->assertSame('08:30', $evento->fresh()->hora_inicio);
        $this->assertSame('12:00', $evento->fresh()->hora_fim);
    }

    public function test_conteudo_e_sanitizado(): void
    {
        $evento = $this->eventoBase(['conteudo' => '<p>Oi</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', (string) $evento->conteudo);
        $this->assertStringContainsString('Oi', (string) $evento->conteudo);
    }

    public function test_visibilidade_e_enum(): void
    {
        $evento = $this->eventoBase(['visibilidade' => VisibilidadeEvento::Diretoria]);

        $this->assertSame(VisibilidadeEvento::Diretoria, $evento->fresh()->visibilidade);
    }

    public function test_relacoes_categoria_e_departamentos(): void
    {
        $cat = Categoria::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $dep = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $evento = $this->eventoBase(['categoria_id' => $cat->id]);
        $evento->departamentos()->sync([$dep->id]);

        $this->assertSame('brecho', $evento->fresh()->categoria->slug);
        $this->assertTrue($evento->fresh()->departamentos->contains($dep));
    }

    public function test_scope_publicado(): void
    {
        $this->eventoBase(['slug' => 'a', 'status' => Evento::STATUS_PUBLICADO]);
        $this->eventoBase(['slug' => 'b', 'status' => Evento::STATUS_RASCUNHO]);

        $this->assertSame(1, Evento::publicado()->count());
    }

    public function test_accessor_periodo(): void
    {
        Carbon::setLocale('pt_BR');
        $evento = $this->eventoBase(['data_inicio' => '2026-06-27', 'hora_inicio' => '08:30', 'hora_fim' => '12:00']);

        $this->assertSame('27 de junho de 2026 · 8h30 – 12h', $evento->periodo);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventoModelTest`
Expected: FAIL (model/tabelas inexistentes).

- [ ] **Step 3: Create the `eventos` migration**

`database/migrations/2026_07_08_000003_create_eventos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('slug')->unique();
            $table->text('resumo')->nullable();
            $table->longText('conteudo')->nullable();
            $table->date('data_inicio');
            $table->string('hora_inicio', 5)->nullable();
            $table->date('data_fim')->nullable();
            $table->string('hora_fim', 5)->nullable();
            $table->string('local')->nullable();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('visibilidade')->default('publico');
            $table->string('status')->default('publicado');
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->timestamps();

            $table->index('data_inicio');
            $table->index('data_fim');
            $table->index('status');
            $table->index('visibilidade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
```

- [ ] **Step 4: Create the pivot migration**

`database/migrations/2026_07_08_000004_create_departamento_evento_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['evento_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_evento');
    }
};
```

- [ ] **Step 5: Create the `Evento` model**

`app/Models/Evento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Models;

use App\Enums\VisibilidadeEvento;
use App\Models\Concerns\RegistraImagensPadrao;
use App\Support\Eventos\PeriodoEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Evento extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao;

    public const STATUS_PUBLICADO = 'publicado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const COLECAO_FLYER = 'flyer';

    public const COLECAO_GALERIA = 'galeria';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'conteudo',
        'data_inicio', 'hora_inicio', 'data_fim', 'hora_fim',
        'local', 'categoria_id', 'visibilidade', 'status', 'wp_id',
    ];

    protected function casts(): array
    {
        return [
            'visibilidade' => VisibilidadeEvento::class,
        ];
    }

    public function registerMediaCollections(): void
    {
        // Flyer/capa (1 imagem) + galeria (N imagens), tratamento padrão do sistema.
        $this->registrarColecaoImagem(self::COLECAO_FLYER);
        $this->registrarColecaoImagem(self::COLECAO_GALERIA, unica: false, larguraWeb: 1920);
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLICADO);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_evento', 'evento_id', 'departamento_id');
    }

    /** data_inicio: string Y-m-d na escrita (portável), Carbon na leitura. */
    protected function dataInicio(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function dataFim(): Attribute
    {
        return Attribute::make(
            get: fn (?string $v) => $v !== null ? Carbon::parse($v) : null,
            set: fn ($v) => $v !== null ? Carbon::parse($v)->format('Y-m-d') : null,
        );
    }

    protected function horaInicio(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => self::normalizaHora($v));
    }

    protected function conteudo(): Attribute
    {
        return Attribute::make(
            set: fn (?string $v) => $v !== null ? clean($v, 'conteudo') : null,
        );
    }

    /** Período por extenso (via classe pura). Usa os valores crus Y-m-d. */
    public function getPeriodoAttribute(): string
    {
        $inicio = $this->attributes['data_inicio'] ?? null;
        if ($inicio === null) {
            return '';
        }

        return PeriodoEvento::formata($inicio, $this->hora_inicio, $this->attributes['data_fim'] ?? null, $this->hora_fim);
    }

    /** URL do flyer (WebP web) via Media Library, ou null. */
    protected function flyerUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FLYER, 'web') ?: null);
    }

    /** Normaliza hora para 'HH:MM' zero-padded; aceita 'H:i' ou 'H:i:s'. Inválido passa cru p/ validação acusar. */
    private static function normalizaHora(?string $v): ?string
    {
        if ($v === null || trim($v) === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($v), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return trim($v);
    }
}
```

- [ ] **Step 6: Run migrations and test to verify PASS**

Run: `docker compose exec -T app php artisan migrate && docker compose exec -T app php artisan test --filter=EventoModelTest`
Expected: migrations `..._create_eventos_table` e `..._create_departamento_evento_table` aplicadas; testes PASS (7).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_08_000003_create_eventos_table.php database/migrations/2026_07_08_000004_create_departamento_evento_table.php app/Models/Evento.php tests/Feature/Eventos/EventoModelTest.php
git commit -m "feat(eventos): tabela eventos + pivot departamento_evento + model Evento"
```

---

### Task 6: `EventoResource` (Filament) + Pages

**Files:**
- Create: `app/Filament/Resources/Eventos/EventoResource.php`
- Create: `app/Filament/Resources/Eventos/Pages/ListEventos.php`
- Create: `app/Filament/Resources/Eventos/Pages/CreateEvento.php`
- Create: `app/Filament/Resources/Eventos/Pages/EditEvento.php`
- Test: `tests/Feature/Filament/EventoResourceTest.php`

**Interfaces:**
- Consumes: `Evento`, `Categoria`, `Departamento`, `VisibilidadeEvento`, `ComponentesImagem::upload`.
- Produces: Resource Filament com form em Tabs (Conteúdo, Data & Local, Classificação, Publicação), tabela com período/categoria/status/visibilidade, e Pages `ListEventos`/`CreateEvento`/`EditEvento`. Categoria e Departamentos gravados pelo binding nativo de `->relationship()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Filament;

use App\Filament\Resources\Eventos\Pages\CreateEvento;
use App\Models\Categoria;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('administrador', 'web');
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_cria_evento_com_categoria_e_departamentos(): void
    {
        $cat = Categoria::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $dep = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Brechó de Junho',
                'slug' => 'brecho-de-junho',
                'data_inicio' => '2026-06-27',
                'categoria_id' => $cat->id,
                'departamentos' => [$dep->id],
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $evento = Evento::firstWhere('slug', 'brecho-de-junho');
        $this->assertNotNull($evento);
        $this->assertSame($cat->id, $evento->categoria_id);
        $this->assertTrue($evento->departamentos->contains($dep));
    }

    public function test_bloqueia_data_fim_anterior_a_inicio(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateEvento::class)
            ->fillForm([
                'titulo' => 'Evento inválido',
                'slug' => 'evento-invalido',
                'data_inicio' => '2026-06-27',
                'data_fim' => '2026-06-25',
                'visibilidade' => 'publico',
                'status' => Evento::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasFormErrors(['data_fim']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventoResourceTest`
Expected: FAIL (Resource/Pages inexistentes).

- [ ] **Step 3: Create the Resource**

`app/Filament/Resources/Eventos/EventoResource.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Filament\Resources\Eventos\Pages\CreateEvento;
use App\Filament\Resources\Eventos\Pages\EditEvento;
use App\Filament\Resources\Eventos\Pages\ListEventos;
use App\Filament\Support\ComponentesImagem;
use App\Models\Evento;
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
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventoResource extends Resource
{
    protected static ?string $model = Evento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Evento';

    protected static ?string $pluralModelLabel = 'Eventos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Evento')->columnSpanFull()->tabs([
                Tabs\Tab::make('Conteúdo')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'eventos', column: 'slug', ignoreRecord: true),
                    ]),
                    Textarea::make('resumo')
                        ->label('Resumo (chamada / SEO)')
                        ->rows(3)
                        ->columnSpanFull(),
                    RichEditor::make('conteudo')
                        ->label('Conteúdo')
                        ->columnSpanFull(),
                    ComponentesImagem::upload('flyer', Evento::COLECAO_FLYER)
                        ->label('Flyer (capa)'),
                    ComponentesImagem::upload('galeria', Evento::COLECAO_GALERIA, multiplas: true)
                        ->label('Galeria de imagens'),
                ]),
                Tabs\Tab::make('Data & Local')->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('data_inicio')
                            ->label('Data de início')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        TimePicker::make('hora_inicio')
                            ->label('Hora de início (deixe vazio para "dia inteiro")')
                            ->seconds(false),
                        DatePicker::make('data_fim')
                            ->label('Data de término (opcional)')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->afterOrEqual('data_inicio'),
                        TimePicker::make('hora_fim')
                            ->label('Hora de término (opcional)')
                            ->seconds(false)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $di = $get('data_inicio');
                                    $df = $get('data_fim');
                                    $hi = $get('hora_inicio');
                                    $mesmoDia = ! $df || $df === $di;
                                    if ($mesmoDia && $hi && $value && $value <= $hi) {
                                        $fail('No mesmo dia, a hora de término deve ser posterior à de início.');
                                    }
                                },
                            ]),
                    ]),
                    TextInput::make('local')
                        ->label('Local')
                        ->maxLength(255),
                ]),
                Tabs\Tab::make('Classificação')->schema([
                    Select::make('categoria_id')
                        ->label('Categoria')
                        ->relationship('categoria', 'nome')
                        ->searchable()
                        ->preload(),
                    Select::make('departamentos')
                        ->label('Departamentos organizadores')
                        ->relationship('departamentos', 'nome')
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ]),
                Tabs\Tab::make('Publicação')->schema([
                    Grid::make(2)->schema([
                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                Evento::STATUS_PUBLICADO => 'Publicado',
                                Evento::STATUS_RASCUNHO => 'Rascunho',
                            ])
                            ->default(Evento::STATUS_RASCUNHO),
                        Select::make('visibilidade')
                            ->label('Visibilidade')
                            ->required()
                            ->options(VisibilidadeEvento::opcoes())
                            ->default(VisibilidadeEvento::Publico->value),
                    ]),
                ]),
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
                TextColumn::make('periodo')
                    ->label('Período'),
                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => $state === Evento::STATUS_PUBLICADO ? 'success' : 'gray'),
                TextColumn::make('visibilidade')
                    ->label('Visibilidade')
                    ->badge()
                    ->formatStateUsing(fn (VisibilidadeEvento $state) => $state->rotulo()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Evento::STATUS_PUBLICADO => 'Publicado',
                        Evento::STATUS_RASCUNHO => 'Rascunho',
                    ]),
                SelectFilter::make('categoria_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome'),
                SelectFilter::make('visibilidade')
                    ->options(VisibilidadeEvento::opcoes()),
            ])
            ->defaultSort('data_inicio', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventos::route('/'),
            'create' => CreateEvento::route('/create'),
            'edit' => EditEvento::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the three Pages**

`app/Filament/Resources/Eventos/Pages/ListEventos.php`:

```php
<?php

namespace App\Filament\Resources\Eventos\Pages;

use App\Filament\Resources\Eventos\EventoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventos extends ListRecords
{
    protected static string $resource = EventoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/Eventos/Pages/CreateEvento.php`:

```php
<?php

namespace App\Filament\Resources\Eventos\Pages;

use App\Filament\Resources\Eventos\EventoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvento extends CreateRecord
{
    protected static string $resource = EventoResource::class;
}
```

`app/Filament/Resources/Eventos/Pages/EditEvento.php`:

```php
<?php

namespace App\Filament\Resources\Eventos\Pages;

use App\Filament\Resources\Eventos\EventoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvento extends EditRecord
{
    protected static string $resource = EventoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=EventoResourceTest`
Expected: PASS (2). Se `Heroicon::OutlinedCalendarDays` não existir na versão instalada, usar `Heroicon::OutlinedCalendar` (conferir `vendor/filament/support/src/Icons/Heroicon.php`).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Eventos tests/Feature/Filament/EventoResourceTest.php
git commit -m "feat(eventos): EventoResource (Filament) com data/hora, classificacao e visibilidade"
```

---

### Task 7: `CategoriaResource` + cor/ícone no `DepartamentoResource`

**Files:**
- Create: `app/Filament/Resources/Categorias/CategoriaResource.php`
- Create: `app/Filament/Resources/Categorias/Pages/ListCategorias.php`
- Create: `app/Filament/Resources/Categorias/Pages/CreateCategoria.php`
- Create: `app/Filament/Resources/Categorias/Pages/EditCategoria.php`
- Modify: `app/Filament/Resources/Departamentos/DepartamentoResource.php` (adicionar `cor` + `icone` ao form)
- Test: `tests/Feature/Filament/CategoriaResourceTest.php`

**Interfaces:**
- Consumes: `Categoria`, `ColorPicker`.
- Produces: CRUD Filament de Categoria; campos `cor`/`icone` no form de Departamento.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Filament;

use App\Filament\Resources\Categorias\Pages\CreateCategoria;
use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoriaResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_categoria_com_cor(): void
    {
        Role::findOrCreate('administrador', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);

        Livewire::test(CreateCategoria::class)
            ->fillForm([
                'nome' => 'Vigília',
                'slug' => 'vigilia',
                'cor' => '#123456',
                'ordem' => 9,
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('#123456', Categoria::firstWhere('slug', 'vigilia')->cor);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CategoriaResourceTest`
Expected: FAIL (Resource/Pages inexistentes).

- [ ] **Step 3: Create the `CategoriaResource`**

`app/Filament/Resources/Categorias/CategoriaResource.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Filament\Resources\Categorias;

use App\Filament\Resources\Categorias\Pages\CreateCategoria;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Models\Categoria;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Categoria de evento')->columns(2)->schema([
                TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                ColorPicker::make('cor')
                    ->label('Cor do selo')
                    ->required()
                    ->rules(['regex:/^#[0-9A-Fa-f]{6}$/']),
                ColorPicker::make('cor_texto')
                    ->label('Cor do texto (contraste)')
                    ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),
                TextInput::make('icone')
                    ->label('Ícone (opcional)')
                    ->maxLength(255),
                TextInput::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('ativo')
                    ->label('Ativa')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')->label('Nome')->searchable()->sortable(),
                ColorColumn::make('cor')->label('Cor'),
                IconColumn::make('ativo')->label('Ativa')->boolean(),
                TextColumn::make('ordem')->label('Ordem')->numeric()->sortable(),
            ])
            ->defaultSort('ordem')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategorias::route('/'),
            'create' => CreateCategoria::route('/create'),
            'edit' => EditCategoria::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the three Categoria Pages**

`app/Filament/Resources/Categorias/Pages/ListCategorias.php`:

```php
<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategorias extends ListRecords
{
    protected static string $resource = CategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

`app/Filament/Resources/Categorias/Pages/CreateCategoria.php`:

```php
<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoria extends CreateRecord
{
    protected static string $resource = CategoriaResource::class;
}
```

`app/Filament/Resources/Categorias/Pages/EditCategoria.php`:

```php
<?php

namespace App\Filament\Resources\Categorias\Pages;

use App\Filament\Resources\Categorias\CategoriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategoria extends EditRecord
{
    protected static string $resource = CategoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 5: Add cor/ícone to `DepartamentoResource`**

Em `app/Filament/Resources/Departamentos/DepartamentoResource.php`: (a) importar `use Filament\Forms\Components\ColorPicker;`; (b) dentro do `Section` do form, após o campo `descricao`, adicionar:

```php
ColorPicker::make('cor')
    ->label('Cor')
    ->rules(['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']),

TextInput::make('icone')
    ->label('Ícone (opcional)')
    ->maxLength(255),
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=CategoriaResourceTest`
Expected: PASS. Se `Heroicon::OutlinedTag`/`ColorColumn` não existirem na versão, conferir alternativas em `vendor/filament/` (`Heroicon::OutlinedTag` costuma existir; `ColorColumn` é `Filament\Tables\Columns\ColorColumn`).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/Categorias app/Filament/Resources/Departamentos/DepartamentoResource.php tests/Feature/Filament/CategoriaResourceTest.php
git commit -m "feat(eventos): CategoriaResource + cor/icone no DepartamentoResource"
```

---

### Fechamento da Fase 1 (verificação)

- [ ] **Step 1: Rodar a suíte completa**

Run: `docker compose exec -T app php artisan test`
Expected: verde. (Os 2 testes flaky de cap de imagem do blog sob GD podem falhar isoladamente sob carga — reexecutar; não são regressão desta fase.)

- [ ] **Step 2: Pint**

Run: `docker compose exec -T app ./vendor/bin/pint`
Expected: sem drift (ou aplica o formato). Commitar se houver ajuste: `git commit -am "style: pint na fase 1 de eventos"`.

- [ ] **Step 3: Conferência real no admin**

Após editar Blade/PHP no dev, `docker compose restart app worker` (OPcache com `validate_timestamps=0`). Abrir `/admin`, criar um Evento (dia único com hora; multi-dia sem hora = dia inteiro; um restrito=diretoria), uma Categoria, e conferir cor no Departamento. Confirmar que salvam e listam corretamente e que a validação de data_fim < data_inicio bloqueia.

---

## Notas de verificação do plano (self-review)

- **Cobertura do spec (Fase 1 = §4 modelo, §8 admin, parte de §9):** `categorias` (Task 1), `eventos` (Task 5), pivot `departamento_evento` (Task 5), cor/ícone em `departamentos` (Task 4), mídia flyer+galeria (Task 5), `VisibilidadeEvento` (Task 2), `PeriodoEvento` validação/formatação (Task 3), `EventoResource`/`CategoriaResource`/Departamento (Tasks 6–7). **Fora desta fase (fases seguintes):** `StatusEvento`, `FeedIcs`, autorização (`podeSerVistoPor`/`scopeVisiveisPara`/Policy), front público, importador, `config('cema.endereco')`, tokens Tailwind, sitemap, navegação pública.
- **Ordem de migrations:** `000001` categorias → `000002` altera departamentos → `000003` eventos (FK categoria) → `000004` pivot (FK eventos+departamentos). Cada `migrate` incremental só vê migrations até a task corrente; sem referência a tabela inexistente.
- **Sync de relações:** categoria (`categoria_id`) e departamentos (`->relationship()->multiple()`) usam o binding nativo do Filament — **sem** trait de sync (diferente de Palestras, que precisava por causa do pivot com `papel`).
- **Riscos de API do Filament v5:** confirmar nomes de ícones (`Heroicon::OutlinedCalendarDays`/`OutlinedTag`) e `ColorColumn` na versão instalada (fallbacks anotados nas Tasks 6/7).
