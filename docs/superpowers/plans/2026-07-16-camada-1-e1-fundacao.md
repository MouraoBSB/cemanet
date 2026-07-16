# Camada 1 · E1 — Fundação da Configuração de acesso por tipo

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir o modelo de dados, o vocabulário, o serviço e a tela da "Configuração de acesso por tipo" — **sem que nada leia a config ainda**, de modo que o acesso do sistema fique byte a byte como está hoje.

**Architecture:** Uma tabela `tipos_conteudo` (1 linha por recurso do `GlossarioCapacidades`, com `regime` + departamentos responsáveis via pivô `departamento_tipo_conteudo`) alimentada por um seeder **insert-only** e editada **só** pela página `MatrizCapacidades` (que vira "Configuração de acesso por tipo"). Um serviço `AcessoPorTipo` (**`scoped`**) responde a **pergunta única** "sou responsável por este tipo?" — escrito e testado nesta fase, **consumido só em E2**. Toda mudança da config é auditada como a matriz (`log_name` `'autorizacao'`).

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-activitylog · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md`](../specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md) (aprovado, commits `5266fe7` + `858095d`).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-1-e1-fundacao` a partir de `origin/main` (= `995f54e`). **Nunca** trabalhar direto na `main`.
- **Banco:** só `php artisan migrate` **incremental**. 🚫 **PROIBIDO** `migrate:fresh`, `migrate:refresh`, `db:wipe`, `migrate:reset` e qualquer seed/factory destrutivo — apagam os 123 AgendaDia, 127 Palestras, 45 Posts e 59 Palestrantes importados do legado.
- **E1 é comportamento-neutro em acesso.** Não tocar em: policies, `AutorizaPorDepartamento`, `scopeNoEscopoDe`, `AbaAgenda`, `AgendaConta`, `AgendaMantenedores`, forms dos 4 conteúdos.
- **Aceite:** suíte verde (`798 + novos`) e **nenhuma asserção de teste existente muda de cor**. A única alteração permitida em teste existente é somar `$this->seed(TiposConteudoSeeder::class)` ao `setUp` de `MatrizCapacidadesTest` e `AuditoriaMatrizTest` (Task 6).
- **Comandos:** `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). npm/Vite rodam no **host**; artisan/pint/composer no **container**.
- **Pint por task:** `docker compose exec -T app ./vendor/bin/pint` antes de cada commit — o CI roda `pint --test` **antes** dos testes e aborta o job.
- **Dev:** depois de editar PHP/Blade, `docker compose restart app worker` (OPcache `validate_timestamps=0`).
- **Autoria** em arquivo novo relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16`.
- **Qualificar `pluck`** em `BelongsToMany`: `pluck('departamentos.id')`, nunca `pluck('id')` sobre a *query* — os pivôs têm `id` próprio e o SQL fica ambíguo (SQLite **e** MySQL). Sobre uma **coleção já carregada** (`$tipo->departamentos->pluck('id')`) é seguro.
- **`scoped`, nunca `singleton`** para serviço memoizado (§6.5 do spec).

---

### Task 0: Branch

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git checkout -b camada-1-e1-fundacao origin/main
git branch --show-current
```

Esperado: `camada-1-e1-fundacao`.

---

### Task 1: Vocabulário — enum `RegimeAcesso` + mapa recurso↔model

**Files:**
- Create: `app/Enums/RegimeAcesso.php`
- Modify: `app/Support/Autorizacao/GlossarioCapacidades.php` (somar `RECURSOS_MODELS` + `modelDe()`)
- Test: `tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php`

**Interfaces:**
- Produces: `App\Enums\RegimeAcesso` (backed string: `DoTipo = 'do_tipo'`, `PorRegistro = 'por_registro'`), com `rotulo(): string` e `opcoes(): array`. `GlossarioCapacidades::RECURSOS_MODELS` (array `recurso => FQCN`) e `GlossarioCapacidades::modelDe(string $recurso): ?string`.
- Consumes: nada.

**Contexto:** o molde de enum do projeto é [`app/Enums/VisibilidadeEvento.php`](../../../app/Enums/VisibilidadeEvento.php) (único enum existente): backed string + `rotulo()` + `opcoes()` para o Select do Filament. `GlossarioCapacidades:13` já tem `RECURSOS` e o comentário `:17` avisa que **slug ≠ model** em `'agenda'` → `AgendaDia` e `'palestrante'` → `Palestrante`.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Unit\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\AgendaDia;
use App\Models\Palestrante;
use App\Support\Autorizacao\GlossarioCapacidades;
use PHPUnit\Framework\TestCase;

class GlossarioCapacidadesMapaTest extends TestCase
{
    public function test_mapa_cobre_exatamente_os_recursos_do_glossario(): void
    {
        $this->assertSame(
            GlossarioCapacidades::RECURSOS,
            array_keys(GlossarioCapacidades::RECURSOS_MODELS),
        );
    }

    public function test_mapa_resolve_os_dois_casos_em_que_slug_difere_do_model(): void
    {
        $this->assertSame(AgendaDia::class, GlossarioCapacidades::modelDe('agenda'));
        $this->assertSame(Palestrante::class, GlossarioCapacidades::modelDe('palestrante'));
    }

    public function test_model_de_recurso_inexistente_devolve_null(): void
    {
        $this->assertNull(GlossarioCapacidades::modelDe('inexistente'));
    }

    public function test_todo_model_do_mapa_existe(): void
    {
        foreach (GlossarioCapacidades::RECURSOS_MODELS as $recurso => $model) {
            $this->assertTrue(class_exists($model), "Model do recurso '{$recurso}' não existe: {$model}");
        }
    }

    public function test_regime_tem_os_dois_casos_e_rotulos_em_pt_br(): void
    {
        $this->assertSame('do_tipo', RegimeAcesso::DoTipo->value);
        $this->assertSame('por_registro', RegimeAcesso::PorRegistro->value);
        $this->assertSame('Departamentos fixos do tipo', RegimeAcesso::DoTipo->rotulo());
        $this->assertSame('Departamentos definidos em cada registro', RegimeAcesso::PorRegistro->rotulo());
    }

    public function test_opcoes_devolve_mapa_value_rotulo_para_o_select(): void
    {
        $this->assertSame([
            'do_tipo' => 'Departamentos fixos do tipo',
            'por_registro' => 'Departamentos definidos em cada registro',
        ], RegimeAcesso::opcoes());
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=GlossarioCapacidadesMapaTest
```

Esperado: **FAIL** — `Class "App\Enums\RegimeAcesso" not found`.

- [ ] **Passo 3: Criar o enum**

`app/Enums/RegimeAcesso.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Enums;

/**
 * Onde mora a verdade do escopo de um tipo de conteúdo: no TIPO (deptos fixos, configurados) ou
 * em CADA REGISTRO (o pivô departamento_<x> do objeto). Não confundir com "como" o escopo chega
 * lá (auto-atribuição pelo autor não existe e está fora da Camada 1).
 */
enum RegimeAcesso: string
{
    case DoTipo = 'do_tipo';
    case PorRegistro = 'por_registro';

    public function rotulo(): string
    {
        return match ($this) {
            self::DoTipo => 'Departamentos fixos do tipo',
            self::PorRegistro => 'Departamentos definidos em cada registro',
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

- [ ] **Passo 4: Somar o mapa ao glossário**

Em `app/Support/Autorizacao/GlossarioCapacidades.php`, somar os imports e, **depois** de `RECURSOS_ROTULOS` (`:24`):

```php
use App\Models\AgendaDia;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
```

```php
    /** Mapa canônico recurso => model (fonte única). Slug ≠ model em 'agenda' e 'palestrante' — ver :17. */
    public const RECURSOS_MODELS = [
        'evento' => Evento::class,
        'palestra' => Palestra::class,
        'post' => Post::class,
        'agenda' => AgendaDia::class,
        'palestrante' => Palestrante::class,
    ];
```

E o acessor, junto dos outros helpers:

```php
    /** Model do recurso, ou null se o slug não está no catálogo. */
    public static function modelDe(string $recurso): ?string
    {
        return self::RECURSOS_MODELS[$recurso] ?? null;
    }
```

⚠️ A ordem das chaves de `RECURSOS_MODELS` **deve** ser a mesma de `RECURSOS` (`['evento','palestra','post','agenda','palestrante']`) — o primeiro teste compara `array_keys` posicionalmente.

- [ ] **Passo 5: Rodar o teste e ver passar**

```bash
docker compose exec -T app php artisan test --filter=GlossarioCapacidadesMapaTest
```

Esperado: **PASS** (6 testes).

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Enums/RegimeAcesso.php app/Support/Autorizacao/GlossarioCapacidades.php tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php
git commit -m "feat(camada-1): enum RegimeAcesso + mapa canônico recurso->model no glossário"
```

---

### Task 2: Modelo de dados — `tipos_conteudo` + pivô + model + inversa

**Files:**
- Create: `database/migrations/2026_07_16_000001_create_tipos_conteudo_table.php`
- Create: `database/migrations/2026_07_16_000002_create_departamento_tipo_conteudo_table.php`
- Create: `app/Models/TipoConteudo.php`
- Modify: `app/Models/Departamento.php` (somar a inversa `tiposConteudo()`)
- Test: `tests/Feature/Autorizacao/TipoConteudoTest.php`

**Interfaces:**
- Consumes: `RegimeAcesso` (Task 1).
- Produces: `App\Models\TipoConteudo` com `$fillable = ['recurso','regime']`, `$casts = ['regime' => RegimeAcesso::class]` e `departamentos(): BelongsToMany`. `Departamento::tiposConteudo(): BelongsToMany`.

**Contexto:** molde do pivô = `2026_07_11_000005_create_departamento_agenda_dia_table.php` (`id()` + 2 `foreignId` + `unique`). **Divergência consciente:** o `departamento_id` daqui usa `restrictOnDelete`, **não** `cascadeOnDelete` — cascade faria do DELETE de departamento um 2º escritor da config, sem tela e sem trilha (fura I7/I8). O efeito do cascade seria *fail-closed* (I1 negaria), então o `restrict` protege **trilha e disponibilidade**, não contra acesso indevido.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Autorizacao/TipoConteudoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipoConteudoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // 8 departamentos
    }

    public function test_regime_e_castado_para_o_enum(): void
    {
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);

        $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', 'agenda')->first()->regime);
    }

    public function test_recurso_e_unique(): void
    {
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);

        $this->expectException(QueryException::class);
        TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::PorRegistro]);
    }

    public function test_relacao_departamentos_e_a_inversa_conversam(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();

        $tipo->departamentos()->sync([$ded->id]);

        $this->assertSame(['DED'], $tipo->fresh()->departamentos->pluck('sigla')->all());
        $this->assertSame(['agenda'], $ded->fresh()->tiposConteudo->pluck('recurso')->all());
    }

    public function test_pivo_nao_duplica_o_mesmo_departamento(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();

        $tipo->departamentos()->sync([$ded->id, $ded->id]);

        $this->assertSame(1, $tipo->departamentos()->count());
    }

    public function test_excluir_departamento_responsavel_e_barrado_pela_fk(): void
    {
        $tipo = TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
        $ded = Departamento::where('sigla', 'DED')->first();
        $tipo->departamentos()->sync([$ded->id]);

        $this->expectException(QueryException::class);
        $ded->delete();
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=TipoConteudoTest
```

Esperado: **FAIL** — `Class "App\Models\TipoConteudo" not found`.

- [ ] **Passo 3: Criar as duas migrations**

`database/migrations/2026_07_16_000001_create_tipos_conteudo_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_conteudo', function (Blueprint $table) {
            $table->id();
            $table->string('recurso')->unique();   // slug de GlossarioCapacidades::RECURSOS
            $table->string('regime');              // App\Enums\RegimeAcesso
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_conteudo');
    }
};
```

`database/migrations/2026_07_16_000002_create_departamento_tipo_conteudo_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_tipo_conteudo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_conteudo_id')->constrained('tipos_conteudo')->cascadeOnDelete();
            // restrictOnDelete diverge do molde dos 6 pivôs DE PROPÓSITO: esta é tabela de
            // autorização. Cascade faria do DELETE de um departamento um segundo escritor da
            // config — sem passar pela tela e sem trilha de auditoria (fura I7/I8 do spec).
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();

            $table->unique(['tipo_conteudo_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_tipo_conteudo');
    }
};
```

- [ ] **Passo 4: Criar o model e a inversa**

`app/Models/TipoConteudo.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Models;

use App\Enums\RegimeAcesso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Configuração de acesso de um TIPO de conteúdo (1 linha por recurso do GlossarioCapacidades):
 * o regime + os departamentos responsáveis. Escrita SÓ pela página MatrizCapacidades (I8); o
 * TiposConteudoSeeder é insert-only e apenas semeia a linha ausente.
 *
 * NÃO implementa TemDepartamento: não é conteúdo — o contrato existe para o trait de policy.
 */
class TipoConteudo extends Model
{
    protected $table = 'tipos_conteudo';

    protected $fillable = ['recurso', 'regime'];

    protected function casts(): array
    {
        return ['regime' => RegimeAcesso::class];
    }

    /** Departamentos responsáveis pelo tipo (lidos só no regime "do tipo"). */
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Departamento::class,
            'departamento_tipo_conteudo',
            'tipo_conteudo_id',
            'departamento_id',
        );
    }
}
```

Em `app/Models/Departamento.php`, somar **depois** de `eventos()` (`:30-33`):

```php
    /** Tipos de conteúdo pelos quais este departamento responde (inversa da config de acesso). */
    public function tiposConteudo(): BelongsToMany
    {
        return $this->belongsToMany(
            TipoConteudo::class,
            'departamento_tipo_conteudo',
            'departamento_id',
            'tipo_conteudo_id',
        );
    }
```

- [ ] **Passo 5: Rodar a migration e o teste**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --filter=TipoConteudoTest
```

Esperado: `migrate` cria as 2 tabelas (**nunca** `migrate:fresh`); teste **PASS** (5 testes).

⚠️ Se `test_excluir_departamento_responsavel_e_barrado_pela_fk` falhar, confirmar que o SQLite dos testes tem FK ligada — `phpunit.xml` deve ter o driver com `foreign_keys=true` (padrão do Laravel 13). Se estiver desligada, o teste do `restrict` é vacuoso; reportar antes de seguir.

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add database/migrations/2026_07_16_000001_create_tipos_conteudo_table.php database/migrations/2026_07_16_000002_create_departamento_tipo_conteudo_table.php app/Models/TipoConteudo.php app/Models/Departamento.php tests/Feature/Autorizacao/TipoConteudoTest.php
git commit -m "feat(camada-1): tabela tipos_conteudo + pivô (restrict) + model TipoConteudo e a inversa"
```

---

### Task 3: `TiposConteudoSeeder` insert-only (a semente)

**Files:**
- Create: `database/seeders/TiposConteudoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (encadear após `EstruturaCemaSeeder`)
- Test: `tests/Feature/Autorizacao/TiposConteudoSeederTest.php`

**Interfaces:**
- Consumes: `TipoConteudo`, `RegimeAcesso`, `GlossarioCapacidades::RECURSOS`.
- Produces: `Database\Seeders\TiposConteudoSeeder` — usado no `setUp` de quase todo teste de E2 e no cutover.

**Contexto:** a semente é **o que cada tipo já tem hoje**, e a medição no dev confirmou 100%: 123 AgendaDia `DECOM+DED`, 127 Palestra `DED`, 45 Post `DECOM`, 59 Palestrante `DED`, Evento por registro. **Insert-only** (I8): `updateOrCreate` + `sync` incondicional faria `db:seed` desfazer a config da tela.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Autorizacao/TiposConteudoSeederTest.php`:

```php
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
        $this->expectExceptionMessage("recurso_fantasma");

        $seeder->run();
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=TiposConteudoSeederTest
```

Esperado: **FAIL** — `Class "Database\Seeders\TiposConteudoSeeder" not found`.

- [ ] **Passo 3: Criar o seeder**

`database/seeders/TiposConteudoSeeder.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Database\Seeders;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\GlossarioCapacidades;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Semeia a configuração de acesso por tipo — 1 linha por recurso do glossário. A semente é o que
 * cada tipo JÁ TEM hoje (medido no dev: 123 AgendaDia DED+DECOM, 127 Palestra DED, 45 Post DECOM,
 * 59 Palestrante DED), então ligar a Camada 1 não muda o acesso de ninguém.
 *
 * INSERT-ONLY (I8): a tela é a dona da config. Linha existente NUNCA é tocada — nem o regime, nem
 * os responsáveis. Reexecutar db:seed preserva integralmente o que o admin configurou.
 */
class TiposConteudoSeeder extends Seeder
{
    private const SEMENTE = [
        'agenda' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED', 'DECOM']],
        'palestra' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED']],
        'palestrante' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DED']],
        'post' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DECOM']],
        'evento' => ['regime' => RegimeAcesso::PorRegistro, 'siglas' => []],
    ];

    public function run(): void
    {
        foreach ($this->recursos() as $recurso) {
            $semente = self::SEMENTE[$recurso] ?? throw new RuntimeException(
                "Recurso '{$recurso}' do glossário não tem semente em TiposConteudoSeeder."
            );

            $tipo = TipoConteudo::firstOrCreate(
                ['recurso' => $recurso],
                ['regime' => $semente['regime']],
            );

            // Insert-only: linha existente é config da tela (I8) — o seeder não a reescreve.
            if ($tipo->wasRecentlyCreated) {
                $tipo->departamentos()->sync($this->idsPorSigla($semente['siglas']));
            }
        }
    }

    /** Ponto de extensão dos testes (o catálogo real é sempre o glossário). */
    protected function recursos(): array
    {
        return GlossarioCapacidades::RECURSOS;
    }

    private function idsPorSigla(array $siglas): array
    {
        if ($siglas === []) {
            return [];
        }

        return Departamento::whereIn('sigla', $siglas)->pluck('departamentos.id')->all();
    }
}
```

- [ ] **Passo 4: Encadear no `DatabaseSeeder`**

Em `database/seeders/DatabaseSeeder.php`, somar **depois** de `CapacidadesSeeder` (precisa dos departamentos do `EstruturaCemaSeeder`):

```php
        $this->call(CapacidadesSeeder::class);
        $this->call(TiposConteudoSeeder::class);   // config de acesso por tipo (insert-only)
```

- [ ] **Passo 5: Rodar o teste e ver passar**

```bash
docker compose exec -T app php artisan test --filter=TiposConteudoSeederTest
```

Esperado: **PASS** (6 testes).

- [ ] **Passo 6: Semear o dev e conferir contra a medição do spec**

```bash
docker compose exec -T app php artisan db:seed --class=TiposConteudoSeeder
docker compose exec -T app php artisan tinker --execute="foreach (App\Models\TipoConteudo::with('departamentos')->get() as \$t) { echo \$t->recurso.' => '.\$t->regime->value.' ['.\$t->departamentos->pluck('sigla')->implode(',').']'.PHP_EOL; }"
```

Esperado, exatamente:

```
evento => por_registro []
palestra => do_tipo [DED]
post => do_tipo [DECOM]
agenda => do_tipo [DED,DECOM]
palestrante => do_tipo [DED]
```

(A ordem das linhas segue o id; as siglas de `agenda` podem vir em qualquer ordem.) 🚫 **Não** rodar `db:seed` sem `--class`.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add database/seeders/TiposConteudoSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Autorizacao/TiposConteudoSeederTest.php
git commit -m "feat(camada-1): TiposConteudoSeeder insert-only com a semente medida no dev"
```

---

### Task 4: O serviço `AcessoPorTipo` — a pergunta única (`scoped`)

**Files:**
- Create: `app/Support/Autorizacao/AcessoPorTipo.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding `scoped` no `register()`)
- Test: `tests/Feature/Autorizacao/AcessoPorTipoTest.php`

**Interfaces:**
- Consumes: `TipoConteudo`, `RegimeAcesso`, `User`.
- Produces: `App\Support\Autorizacao\AcessoPorTipo` com `regime(string $recurso): ?RegimeAcesso`, `departamentosResponsaveis(string $recurso): array` (list<int>) e **`usuarioHabilitadoNoTipo(User $user, string $recurso): bool`** — a pergunta única que E2 consome na aba, no `criar` e no filtro.

**Contexto:** **ninguém chama este serviço em E1** — é fundação. `scoped`, **nunca** `singleton`: o `cema-worker` (`queue:work`) não reconstrói o container entre jobs; ele chama `forgetScopedInstances()` (`QueueServiceProvider.php:263`), e `Container.php:1719-1729` percorre só `$this->scopedInstances` ⇒ `singleton` sobreviveria e o memo viraria cache persistente de config de acesso.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Autorizacao/AcessoPorTipoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcessoPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    private function usuarioEm(string ...$siglas): User
    {
        $u = User::factory()->create();
        $u->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $u;
    }

    private function tipo(string $recurso, RegimeAcesso $regime, array $siglas = []): TipoConteudo
    {
        $tipo = TipoConteudo::create(['recurso' => $recurso, 'regime' => $regime]);
        $tipo->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $tipo;
    }

    private function servico(): AcessoPorTipo
    {
        return app(AcessoPorTipo::class);
    }

    // --- I2: recurso sem linha ⇒ fecha, não explode ---

    public function test_recurso_sem_linha_devolve_regime_null_e_nao_lanca(): void
    {
        $this->assertNull($this->servico()->regime('agenda'));
    }

    public function test_recurso_sem_linha_nao_habilita_ninguem(): void
    {
        $usuario = $this->usuarioEm('DED');

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($usuario, 'agenda'));
    }

    public function test_recurso_sem_linha_devolve_lista_vazia_de_responsaveis(): void
    {
        $this->assertSame([], $this->servico()->departamentosResponsaveis('agenda'));
    }

    // --- I1: config vazia nunca permite ---

    public function test_do_tipo_sem_responsaveis_nao_habilita_ninguem(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, []);
        $usuario = $this->usuarioEm('DED');

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($usuario, 'agenda'));
    }

    // --- "do tipo": só quem está num depto responsável ---

    public function test_do_tipo_habilita_quem_esta_em_depto_responsavel(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $this->assertTrue($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DED'), 'agenda'));
    }

    public function test_do_tipo_nega_usuario_de_depto_disjunto(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DEPRO'), 'agenda'));
    }

    public function test_do_tipo_nega_usuario_sem_nenhum_departamento(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo(User::factory()->create(), 'agenda'));
    }

    // --- I4: "por registro" = tem algum depto (o filtro do objeto é do trait, não daqui) ---

    public function test_por_registro_habilita_quem_tem_algum_departamento(): void
    {
        $this->tipo('evento', RegimeAcesso::PorRegistro, []);

        $this->assertTrue($this->servico()->usuarioHabilitadoNoTipo($this->usuarioEm('DEPRO'), 'evento'));
    }

    public function test_por_registro_nega_quem_nao_tem_departamento(): void
    {
        $this->tipo('evento', RegimeAcesso::PorRegistro, []);

        $this->assertFalse($this->servico()->usuarioHabilitadoNoTipo(User::factory()->create(), 'evento'));
    }

    public function test_responsaveis_devolve_os_ids(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $ids = $this->servico()->departamentosResponsaveis('agenda');
        sort($ids);
        $esperado = [$this->idDe('DECOM'), $this->idDe('DED')];
        sort($esperado);

        $this->assertSame($esperado, $ids);
    }

    // --- §6.5: memo por escopo, e o escopo morre ---

    public function test_memo_evita_reconsultar_o_banco_no_mesmo_escopo(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);
        $servico = $this->servico();
        $servico->regime('agenda');   // aquece o memo

        DB::enableQueryLog();
        $servico->regime('agenda');
        $servico->departamentosResponsaveis('agenda');

        $this->assertCount(0, DB::getQueryLog(), 'o memo não segurou: reconsultou o banco');
        DB::disableQueryLog();
    }

    public function test_recurso_ausente_tambem_e_memoizado(): void
    {
        // o serviço é scoped: app() devolve a MESMA instância dentro do teste
        $this->servico()->regime('agenda');   // null

        DB::enableQueryLog();
        $this->servico()->regime('agenda');

        $this->assertCount(0, DB::getQueryLog(), 'o null não foi memoizado (usou ??= em vez de array_key_exists?)');
        DB::disableQueryLog();
    }

    /** É o teste que reprova o binding singleton (o worker preservaria a instância entre jobs). */
    public function test_o_memo_morre_com_o_escopo(): void
    {
        $this->tipo('agenda', RegimeAcesso::DoTipo, ['DED']);
        $primeiro = $this->servico();
        $this->assertSame(RegimeAcesso::DoTipo, $primeiro->regime('agenda'));

        TipoConteudo::where('recurso', 'agenda')->first()->update(['regime' => RegimeAcesso::PorRegistro]);

        app()->forgetScopedInstances();
        $segundo = $this->servico();

        $this->assertNotSame($primeiro, $segundo, 'a instância sobreviveu ao escopo — binding é singleton?');
        $this->assertSame(RegimeAcesso::PorRegistro, $segundo->regime('agenda'));
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=AcessoPorTipoTest
```

Esperado: **FAIL** — `Target class [App\Support\Autorizacao\AcessoPorTipo] does not exist`.

- [ ] **Passo 3: Criar o serviço**

`app/Support/Autorizacao/AcessoPorTipo.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Support\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\TipoConteudo;
use App\Models\User;

/**
 * Fonte única da pergunta "o usuário está habilitado a tocar neste TIPO?" — consumida pela aba do
 * /minha-conta, pelo criar das policies e pelo filtro do trait. Três implementações da mesma
 * pergunta é como nasce divergência de acesso: é aqui, e só aqui.
 *
 * FAIL-CLOSED em todos os caminhos: recurso sem linha (I2), tipo "do tipo" sem responsáveis (I1) e
 * usuário sem departamento negam. Config vazia NUNCA permite.
 *
 * Registrado como SCOPED (AppServiceProvider) — jamais singleton: o worker não reconstrói o
 * container entre jobs (só chama forgetScopedInstances), e o memo viraria cache persistente de
 * config de acesso. Nunca usar cache persistente aqui: invalidação stale = furo que ninguém vê.
 */
final class AcessoPorTipo
{
    /** @var array<string, TipoConteudo|null> memo por escopo (request ou job) */
    private array $memo = [];

    public function regime(string $recurso): ?RegimeAcesso
    {
        return $this->tipo($recurso)?->regime;
    }

    /** @return list<int> ids dos departamentos responsáveis; [] se o tipo não existe ou não tem responsáveis. */
    public function departamentosResponsaveis(string $recurso): array
    {
        return $this->tipo($recurso)?->departamentos->pluck('id')->all() ?? [];
    }

    /** A PERGUNTA ÚNICA. Fail-closed: regime desconhecido ⇒ nega. */
    public function usuarioHabilitadoNoTipo(User $user, string $recurso): bool
    {
        return match ($this->regime($recurso)) {
            RegimeAcesso::DoTipo => $this->usuarioResponsavel($user, $recurso),
            // "Por registro": o escopo do OBJETO é do trait; aqui a pergunta do tipo é só "tem
            // algum departamento?" — o filtro atual, inalterado (I4).
            RegimeAcesso::PorRegistro => $user->departamentos()->exists(),
            null => false,   // I1/I2: sem linha ⇒ nega, não explode
        };
    }

    private function usuarioResponsavel(User $user, string $recurso): bool
    {
        $ids = $this->departamentosResponsaveis($recurso);

        if ($ids === []) {
            return false;   // I1
        }

        // 'departamentos.id' QUALIFICADO: o pivô tem id próprio e o SQL ficaria ambíguo.
        return $user->departamentos()->whereIn('departamentos.id', $ids)->exists();
    }

    private function tipo(string $recurso): ?TipoConteudo
    {
        // array_key_exists (e não ??=): memoiza TAMBÉM o null, senão o caminho I2 refaz a query a
        // cada checagem de policy.
        if (! array_key_exists($recurso, $this->memo)) {
            $this->memo[$recurso] = TipoConteudo::with('departamentos')
                ->where('recurso', $recurso)
                ->first();
        }

        return $this->memo[$recurso];
    }
}
```

- [ ] **Passo 4: Registrar o binding `scoped`**

Em `app/Providers/AppServiceProvider.php`, dentro de `register()` (**não** `boot()`), somando o import `use App\Support\Autorizacao\AcessoPorTipo;`:

```php
        // SCOPED, nunca singleton: o worker (queue:work) não reconstrói o container entre jobs —
        // só chama forgetScopedInstances (QueueServiceProvider:263), que preserva singletons. Um
        // memo de config de ACESSO em singleton viraria cache persistente dentro do worker.
        $this->app->scoped(AcessoPorTipo::class, fn (): AcessoPorTipo => new AcessoPorTipo);
```

- [ ] **Passo 5: Rodar o teste e ver passar**

```bash
docker compose exec -T app php artisan test --filter=AcessoPorTipoTest
```

Esperado: **PASS** (13 testes).

- [ ] **Passo 6: Provar que E1 não mudou acesso nenhum**

```bash
docker compose exec -T app php artisan test --filter="Capacidade|Policy|AbaAgenda|AgendaConta"
```

Esperado: **PASS**, sem nenhum teste alterado — ninguém consome `AcessoPorTipo` ainda.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Support/Autorizacao/AcessoPorTipo.php app/Providers/AppServiceProvider.php tests/Feature/Autorizacao/AcessoPorTipoTest.php
git commit -m "feat(camada-1): serviço AcessoPorTipo (scoped) com a pergunta única, fail-closed"
```

---

### Task 5: Auditoria da config — dois helpers

**Files:**
- Modify: `app/Support/Autorizacao/AuditoriaAutorizacao.php` (somar 2 métodos; **não** tocar no `registrar()` privado)
- Test: `tests/Feature/Autorizacao/AuditoriaTipoConteudoHelpersTest.php`

**Interfaces:**
- Consumes: `TipoConteudo`, `AuditoriaAutorizacao::diff()`, `AuditoriaAutorizacao::registrar()`.
- Produces: `AuditoriaAutorizacao::registrarRegimeTipo(TipoConteudo $tipo, ?string $antes, string $depois): void` e `AuditoriaAutorizacao::registrarDepartamentosTipo(TipoConteudo $tipo, array $antes, array $depois): void` (ambos `[id => nome]` nos arrays).

**Contexto — a armadilha:** `registrar()` (`:111-122`) é **no-op** quando `$diff['adicionados']` e `$diff['removidos']` estão vazios. Um diff de outro formato (ex.: `['regime' => ..., 'departamentos' => ...]`) cairia nesse `empty()` e viraria **no-op silencioso** — auditaria sem gravar. Por isso são **dois** métodos, cada um no formato `{adicionados, removidos}` que o privado já entende. Molde do diff por id: `registrarDepartamentosUsuario()` (`:71-88`).

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Autorizacao/AuditoriaTipoConteudoHelpersTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaTipoConteudoHelpersTest extends TestCase
{
    use RefreshDatabase;

    private function tipo(): TipoConteudo
    {
        return TipoConteudo::create(['recurso' => 'agenda', 'regime' => RegimeAcesso::DoTipo]);
    }

    private function ultimaEntrada(): array
    {
        return Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
    }

    public function test_regime_alterado_gera_entrada_com_diff_de_nomes(): void
    {
        $tipo = $this->tipo();

        AuditoriaAutorizacao::registrarRegimeTipo($tipo, 'do_tipo', 'por_registro');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'regime do tipo agenda alterado',
            'subject_type' => $tipo->getMorphClass(),
            'subject_id' => $tipo->id,
        ]);

        $props = $this->ultimaEntrada();
        $this->assertSame(['por_registro'], $props['diff']['adicionados']);
        $this->assertSame(['do_tipo'], $props['diff']['removidos']);
        $this->assertArrayHasKey('porta', $props);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_regime_sem_mudanca_nao_gera_entrada(): void
    {
        AuditoriaAutorizacao::registrarRegimeTipo($this->tipo(), 'do_tipo', 'do_tipo');

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_regime_de_linha_nova_registra_so_a_adicao(): void
    {
        AuditoriaAutorizacao::registrarRegimeTipo($this->tipo(), null, 'do_tipo');

        $props = $this->ultimaEntrada();
        $this->assertSame(['do_tipo'], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }

    public function test_responsaveis_alterados_geram_diff_por_id_com_nome(): void
    {
        $tipo = $this->tipo();

        AuditoriaAutorizacao::registrarDepartamentosTipo($tipo, [3 => 'Estudos Doutrinários'], [3 => 'Estudos Doutrinários', 8 => 'Comunicação e Multimídia']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'departamentos responsáveis do tipo agenda alterados',
            'subject_id' => $tipo->id,
        ]);

        $props = $this->ultimaEntrada();
        $this->assertSame([['id' => 8, 'nome' => 'Comunicação e Multimídia']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }

    public function test_responsavel_removido_carrega_o_nome_de_antes(): void
    {
        AuditoriaAutorizacao::registrarDepartamentosTipo($this->tipo(), [3 => 'Estudos Doutrinários'], []);

        $props = $this->ultimaEntrada();
        $this->assertSame([['id' => 3, 'nome' => 'Estudos Doutrinários']], $props['diff']['removidos']);
        $this->assertSame([], $props['diff']['adicionados']);
    }

    public function test_responsaveis_sem_mudanca_nao_geram_entrada(): void
    {
        AuditoriaAutorizacao::registrarDepartamentosTipo($this->tipo(), [3 => 'Estudos Doutrinários'], [3 => 'Estudos Doutrinários']);

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=AuditoriaTipoConteudoHelpersTest
```

Esperado: **FAIL** — `Call to undefined method ...::registrarRegimeTipo()`.

- [ ] **Passo 3: Somar os dois helpers**

Em `app/Support/Autorizacao/AuditoriaAutorizacao.php`, somar o import `use App\Models\TipoConteudo;` e, **depois** de `registrarDepartamentosConteudo()` (`:97-108`):

```php
    /**
     * Regime do tipo: subject = TipoConteudo; diff de nomes. Método SEPARADO do de responsáveis de
     * propósito — o registrar() privado é no-op se {adicionados, removidos} vierem vazios, então um
     * diff de outro formato (ex.: ['regime' => ..., 'departamentos' => ...]) viraria no-op
     * silencioso: auditaria sem gravar.
     *
     * @param  ?string  $antes  value do regime antes (null se a linha acabou de nascer)
     */
    public static function registrarRegimeTipo(TipoConteudo $tipo, ?string $antes, string $depois): void
    {
        self::registrar(
            $tipo,
            "regime do tipo {$tipo->recurso} alterado",
            self::diff($antes === null ? [] : [$antes], [$depois]),
        );
    }

    /**
     * Responsáveis do tipo: subject = TipoConteudo. Diff por id, itens {id, nome} (estável a rename).
     *
     * @param  array<int, string>  $antes  [id => nome] antes do sync
     * @param  array<int, string>  $depois  [id => nome] depois do sync
     */
    public static function registrarDepartamentosTipo(TipoConteudo $tipo, array $antes, array $depois): void
    {
        $idsAdicionados = array_diff(array_keys($depois), array_keys($antes));
        $idsRemovidos = array_diff(array_keys($antes), array_keys($depois));

        $diff = [
            'adicionados' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $depois[$id]], $idsAdicionados)),
            'removidos' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $antes[$id]], $idsRemovidos)),
        ];

        self::registrar($tipo, "departamentos responsáveis do tipo {$tipo->recurso} alterados", $diff);
    }
```

- [ ] **Passo 4: Rodar o teste e ver passar**

```bash
docker compose exec -T app php artisan test --filter=AuditoriaTipoConteudoHelpersTest
```

Esperado: **PASS** (6 testes).

- [ ] **Passo 5: Confirmar que a trilha existente não mudou**

```bash
docker compose exec -T app php artisan test --filter=Auditoria
```

Esperado: **PASS** — nenhum teste de auditoria existente alterado.

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Support/Autorizacao/AuditoriaAutorizacao.php tests/Feature/Autorizacao/AuditoriaTipoConteudoHelpersTest.php
git commit -m "feat(camada-1): auditoria da config por tipo (regime e responsáveis, entradas separadas)"
```

---

### Task 6: A tela — "Configuração de acesso por tipo"

**Files:**
- Modify: `app/Filament/Pages/MatrizCapacidades.php` (título, `mount()`, `secoesPorRecurso()`, `salvar()`)
- Modify: `tests/Feature/Filament/MatrizCapacidadesTest.php:23-29` (só o `setUp`)
- Modify: `tests/Feature/Autorizacao/AuditoriaMatrizTest.php:22-29` (só o `setUp`)
- Test: `tests/Feature/Filament/ConfiguracaoAcessoPorTipoTest.php`

**Interfaces:**
- Consumes: `RegimeAcesso`, `TipoConteudo`, `Departamento`, os 2 helpers da Task 5.
- Produces: a tela é a **única escritora** da config (I8).

**Contexto crítico:**
1. O `$estado` dos toggles é `$estado[$papel][$recurso][$acao]`; o da config é `$estado[$recurso]['regime'|'departamentos']`. Convivem porque `PAPEIS_EDITAVEIS` (`trabalhador`, `diretor`) não colide com `RECURSOS`.
2. **`->disabled()` + `->dehydrated(true)`, nunca `->visible()`**: componente oculto **não desidrata** (`schemas/src/Components/Concerns/HasState.php:774-783`) ⇒ o `salvar()` receberia `[]` e apagaria os responsáveis. E `disabled()` sozinho também não desidrata (`CanBeDisabled.php:25`).
3. **O cinto server-side é obrigatório**: com `dehydrated(true)` o valor **vem do cliente** — o próprio vendor avisa (`CanBeDisabled.php:20-24`). O `if ($regime === DoTipo)` no `salvar()` é o que preserva os responsáveis **por construção**.
4. **Os 2 `setUp` existentes ganham a semente**: o `required()` do Select entra no schema que `salvar()` valida (`MatrizCapacidades.php:109` → `schemas/src/Concerns/HasState.php:450`); sem linha, `regime => null` reprova e caem `MatrizCapacidadesTest:49,57,85,98` e `AuditoriaMatrizTest:36,67,79`. 🚫 **Proibido remover o `required()`** para restaurar o verde.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Filament/ConfiguracaoAcessoPorTipoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Filament;

use App\Enums\RegimeAcesso;
use App\Filament\Pages\MatrizCapacidades;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfiguracaoAcessoPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->seed(TiposConteudoSeeder::class);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin'));   // porta = admin
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    private function siglasDe(string $recurso): array
    {
        return TipoConteudo::where('recurso', $recurso)->first()
            ->departamentos->pluck('sigla')->sort()->values()->all();
    }

    public function test_abre_com_a_config_atual_pre_carregada(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->assertFormSet([
                'agenda.regime' => RegimeAcesso::DoTipo->value,
                'evento.regime' => RegimeAcesso::PorRegistro->value,
            ]);
    }

    public function test_as_duas_arvores_do_state_convivem(): void
    {
        // toggles ($estado[papel][recurso][acao]) e config ($estado[recurso][...]) no mesmo data
        Livewire::test(MatrizCapacidades::class)
            ->fillForm([
                'diretor.palestra.editar' => true,
                'palestra.regime' => RegimeAcesso::DoTipo->value,
                'palestra.departamentos' => [$this->idDe('DECOM')],
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertTrue(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));
        $this->assertSame(['DECOM'], $this->siglasDe('palestra'));
    }

    public function test_salvar_grava_regime_e_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.departamentos' => [$this->idDe('DED')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DED'], $this->siglasDe('agenda'));
    }

    public function test_regime_vazio_reprova_e_nao_grava(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => null])
            ->call('salvar')
            ->assertHasFormErrors(['agenda.regime']);

        $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', 'agenda')->first()->regime);
        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
    }

    /** Round-trip: trocar de regime e voltar PRESERVA os responsáveis (reprova o ->visible()). */
    public function test_round_trip_de_regime_preserva_os_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::PorRegistro->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'), 'os responsáveis foram apagados ao sair do "do tipo"');

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::DoTipo->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'));
    }

    /** O cinto server-side: no "por registro" o POST não manda nos responsáveis. */
    public function test_post_forjado_no_por_registro_nao_apaga_os_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm([
                'agenda.regime' => RegimeAcesso::PorRegistro->value,
                'agenda.departamentos' => [],   // forja: o cliente manda vazio
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(['DECOM', 'DED'], $this->siglasDe('agenda'), 'o POST forjado apagou os responsáveis');
    }

    // --- I7: auditoria pela página ---

    public function test_trocar_so_o_regime_gera_1_entrada_e_nenhuma_de_responsaveis(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.regime' => RegimeAcesso::PorRegistro->value])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
        $this->assertDatabaseHas('activity_log', ['description' => 'regime do tipo agenda alterado']);
    }

    public function test_trocar_so_os_responsaveis_gera_1_entrada_com_diff_por_id(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['agenda.departamentos' => [$this->idDe('DED'), $this->idDe('DECOM'), $this->idDe('DIJ')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(1, DB::table('activity_log')->where('log_name', 'autorizacao')->count());

        $props = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();

        $this->assertSame([['id' => $this->idDe('DIJ'), 'nome' => 'Infância e Juventude']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
        $this->assertSame('admin', $props['porta']);
    }

    /** Reprova a leitura tardia do "antes": se o antes for lido DEPOIS do sync, o diff vem vazio. */
    public function test_o_antes_e_lido_do_banco_antes_do_sync(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['palestra.departamentos' => [$this->idDe('DED'), $this->idDe('DECOM')]])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $props = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();

        $this->assertSame([['id' => $this->idDe('DECOM'), 'nome' => 'Comunicação e Multimídia']], $props['diff']['adicionados']);
    }

    public function test_salvar_sem_mudar_nada_nao_loga(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_nao_admin_nao_acessa(): void
    {
        $diretor = \App\Models\User::factory()->create();
        $diretor->assignRole('diretor');

        $this->actingAs($diretor)->get('/admin/matriz-capacidades')->assertForbidden();
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=ConfiguracaoAcessoPorTipoTest
```

Esperado: **FAIL** — os campos `agenda.regime`/`agenda.departamentos` não existem no schema.

- [ ] **Passo 3: Editar a página**

Em `app/Filament/Pages/MatrizCapacidades.php`, somar os imports:

```php
use App\Enums\RegimeAcesso;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
```

Trocar o docblock da classe e o título (`:23-38`):

```php
/**
 * Configuração de acesso por tipo (Camada 1) — evolução da matriz papel×capacidade (Fase C).
 * Por tipo: as 20 capacidades × papel (toggles) + o REGIME + os DEPARTAMENTOS RESPONSÁVEIS.
 * Única escritora de role_has_permissions E da config de acesso (tipos_conteudo) — I8.
 * Admin-only pelo portão do painel. syncPermissions já limpa o cache do spatie (não chamar forget).
 *
 * O slug segue 'matriz-capacidades': trocar a rota quebraria links e não traz benefício.
 */
class MatrizCapacidades extends Page
{
    protected string $view = 'filament.pages.matriz-capacidades';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Configuração de acesso por tipo';

    protected static ?string $title = 'Configuração de acesso por tipo';

    protected static ?string $slug = 'matriz-capacidades';
```

No `mount()`, **depois** do laço dos papéis e **antes** do `form->fill($estado)` (`:57`):

```php
        // Config de acesso por tipo — namespace separado dos toggles ($estado[papel][recurso][acao]):
        // aqui é $estado[recurso][regime|departamentos]. Não colidem (PAPEIS_EDITAVEIS ∌ RECURSOS).
        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $tipo = TipoConteudo::with('departamentos')->where('recurso', $recurso)->first();

            // Recurso sem linha: regime null (o required reprova o submit) e nenhum responsável.
            // A tela escreve a config EXISTENTE — quem semeia o catálogo é o TiposConteudoSeeder (I8).
            $estado[$recurso]['regime'] = $tipo?->regime?->value;
            $estado[$recurso]['departamentos'] = $tipo?->departamentos->pluck('id')->all() ?? [];
        }
```

Reescrever `secoesPorRecurso()` (`:68-89`):

```php
    /** @return array<Component> uma Section por recurso: regime + responsáveis + toggles (ação × papel). */
    private function secoesPorRecurso(): array
    {
        $secoes = [];
        // Hoistado: dentro do laço seriam 5 queries idênticas (uma por Section).
        $departamentos = Departamento::orderBy('sigla')->pluck('nome', 'id');

        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $campos = [
                Select::make("{$recurso}.regime")
                    ->label('Regime de acesso')
                    ->options(RegimeAcesso::opcoes())
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Select::make("{$recurso}.departamentos")
                    ->label('Departamentos responsáveis')
                    ->options($departamentos)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    // disabled + dehydrated(true), NUNCA visible/hidden: componente oculto não
                    // desidrata (HasState.php:774-783) e o salvar() receberia [] — apagaria os
                    // responsáveis. disabled() também não desidrata sozinho (CanBeDisabled.php:25).
                    // A integridade real é o cinto do salvar(), não isto aqui: com dehydrated(true)
                    // o valor vem do CLIENTE.
                    ->disabled(fn (Get $get): bool => $get("{$recurso}.regime") !== RegimeAcesso::DoTipo->value)
                    ->dehydrated(true)
                    ->helperText(fn (Get $get): string => $get("{$recurso}.regime") === RegimeAcesso::DoTipo->value
                        ? 'Quem responde por este tipo. A responsabilidade só chega ao usuário pelo vínculo com o departamento, em Usuários.'
                        : 'Regime "em cada registro": estes responsáveis ficam guardados, mas não são lidos. Voltar ao "do tipo" os restaura.')
                    ->columnSpanFull(),
            ];

            foreach (GlossarioCapacidades::ACOES as $acao) {
                foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
                    $campos[] = Toggle::make("{$papel}.{$recurso}.{$acao}")
                        ->label(GlossarioCapacidades::rotuloAcao($acao).' — '.ucfirst($papel))
                        ->inline(false);
                }
            }

            $secoes[] = Section::make(GlossarioCapacidades::rotuloRecurso($recurso))
                ->columns(count(GlossarioUsuarios::PAPEIS_EDITAVEIS))
                ->schema($campos);
        }

        return $secoes;
    }
```

No `salvar()`, **depois** do laço dos papéis e **antes** da `Notification` (`:130`):

```php
        $this->salvarConfigPorTipo($estado);
```

E o método novo, logo abaixo de `salvar()`:

```php
    /** Escreve a config de acesso por tipo. Só toca linha existente (o catálogo é do seeder — I8). */
    private function salvarConfigPorTipo(array $estado): void
    {
        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $tipo = TipoConteudo::where('recurso', $recurso)->first();

            if ($tipo === null) {
                continue;   // recurso sem linha: a tela não cria catálogo
            }

            $regime = RegimeAcesso::tryFrom($estado[$recurso]['regime'] ?? '');

            if ($regime === null) {
                continue;   // o required() já reprovou; belt contra valor fora do enum
            }

            $regimeAntes = $tipo->regime->value;
            $tipo->update(['regime' => $regime]);
            AuditoriaAutorizacao::registrarRegimeTipo($tipo, $regimeAntes, $regime->value);

            // CINTO SERVER-SIDE: no "em cada registro" os responsáveis NÃO são sincronizados — são
            // preservados POR CONSTRUÇÃO, não pela hidratação do form. Com dehydrated(true) o valor
            // vem do cliente, e o vendor avisa (CanBeDisabled.php:20-24) que disabled() não é
            // barreira. Sem este if, um POST forjado com departamentos=[] apagaria a config.
            if ($regime !== RegimeAcesso::DoTipo) {
                continue;
            }

            $ids = array_map('intval', $estado[$recurso]['departamentos'] ?? []);
            $antes = $tipo->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();   // ANTES do sync
            $tipo->departamentos()->sync($ids);
            $depois = Departamento::whereIn('id', $ids)->pluck('nome', 'id')->all();

            AuditoriaAutorizacao::registrarDepartamentosTipo($tipo, $antes, $depois);
        }
    }
```

⚠️ `pluck('departamentos.nome', 'departamentos.id')` **qualificado** — o pivô tem `id` próprio e sem qualificar dá "ambiguous column: id" (mordeu no PR #28). O `$depois` é sobre `Departamento::whereIn(...)`, uma query simples: `pluck('nome','id')` sem qualificar está correto ali.

- [ ] **Passo 4: Somar a semente aos 2 `setUp` existentes**

Em `tests/Feature/Filament/MatrizCapacidadesTest.php:27` e `tests/Feature/Autorizacao/AuditoriaMatrizTest.php:26`, somar **depois** do `CapacidadesSeeder`:

```php
        $this->seed(TiposConteudoSeeder::class);   // o Select de regime é required (Camada 1/E1)
```

E o import `use Database\Seeders\TiposConteudoSeeder;` em cada um. **Nenhuma asserção muda** — só o `setUp`.

- [ ] **Passo 5: Rodar os três testes**

```bash
docker compose exec -T app php artisan test --filter="ConfiguracaoAcessoPorTipoTest|MatrizCapacidadesTest|AuditoriaMatrizTest"
```

Esperado: **PASS**. Se `MatrizCapacidadesTest`/`AuditoriaMatrizTest` falharem em `assertHasNoFormErrors`, a semente do Passo 4 não entrou. 🚫 Não remover o `required()`.

- [ ] **Passo 6: Conferir a tela no navegador**

```bash
docker compose restart app worker
```

Abrir `http://localhost/admin/matriz-capacidades` como admin e confirmar:
- o título e o item de menu dizem **"Configuração de acesso por tipo"**;
- cada Section (Evento, Palestra, Post, Agenda do Dia, Palestrante) tem **Regime** + **Departamentos responsáveis** acima dos toggles;
- Agenda abre com **DED + DECOM**, Palestra com **DED**, Post com **DECOM**, Palestrante com **DED**;
- **Evento** abre em "Departamentos definidos em cada registro" e o multiselect está **cinza (desabilitado)**, com o texto de ajuda correspondente;
- trocar o regime de Agenda para "em cada registro" **desabilita** o multiselect **sem apagar** as siglas exibidas;
- salvar mostra a notificação de sucesso.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Pages/MatrizCapacidades.php tests/Feature/Filament/ConfiguracaoAcessoPorTipoTest.php tests/Feature/Filament/MatrizCapacidadesTest.php tests/Feature/Autorizacao/AuditoriaMatrizTest.php
git commit -m "feat(camada-1): matriz vira Configuração de acesso por tipo (regime + responsáveis, com cinto no salvar)"
```

---

### Task 7: UX do `restrict` — excluir departamento responsável é barrado

**Files:**
- Modify: `app/Filament/Resources/Departamentos/DepartamentoResource.php:142-149`
- Modify: `app/Filament/Resources/Departamentos/Pages/EditDepartamento.php` (a `DeleteAction::make()` do `getHeaderActions()`)
- Test: `tests/Feature/Filament/ExcluirDepartamentoResponsavelTest.php`

**Interfaces:**
- Consumes: `Departamento::tiposConteudo()` (Task 2), `GlossarioCapacidades::rotuloRecurso()`.
- Produces: nada para tasks futuras.

**Contexto:** a FK é `restrictOnDelete` (Task 2) ⇒ sem guarda, o DELETE estoura como **erro 500**. **`Notification` sozinha NÃO aborta** — é preciso `$action->cancel()` (`vendor/filament/actions/src/Action.php:677`), e **não** `halt()` (`:682`, que manteria o modal aberto). ⚠️ `DeleteAction::before()` recebe **`$record`**; `DeleteBulkAction::before()` recebe **`$records`** (Collection) — assinaturas diferentes.

- [ ] **Passo 1: Escrever o teste que falha**

Criar `tests/Feature/Filament/ExcluirDepartamentoResponsavelTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Filament;

use App\Filament\Resources\Departamentos\Pages\ListDepartamentos;
use App\Models\Departamento;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExcluirDepartamentoResponsavelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->seed(TiposConteudoSeeder::class);   // DED responde por agenda/palestra/palestrante
        $this->actingAsAdmin();
    }

    public function test_nao_exclui_departamento_que_responde_por_um_tipo(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();

        Livewire::test(ListDepartamentos::class)
            ->callTableAction('delete', $ded);

        $this->assertDatabaseHas('departamentos', ['id' => $ded->id]);
    }

    public function test_exclui_departamento_que_nao_responde_por_nenhum_tipo(): void
    {
        // DIJ não está na semente de nenhum tipo
        $dij = Departamento::where('sigla', 'DIJ')->first();
        $dij->setores()->delete();
        $dij->cargos()->delete();

        Livewire::test(ListDepartamentos::class)
            ->callTableAction('delete', $dij);

        $this->assertDatabaseMissing('departamentos', ['id' => $dij->id]);
    }

    public function test_bulk_nao_exclui_se_algum_responde_por_tipo(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();
        $dij = Departamento::where('sigla', 'DIJ')->first();

        Livewire::test(ListDepartamentos::class)
            ->callTableBulkAction('delete', [$ded->id, $dij->id]);

        $this->assertDatabaseHas('departamentos', ['id' => $ded->id]);
        $this->assertDatabaseHas('departamentos', ['id' => $dij->id]);
    }
}
```

⚠️ Se o nome da página de listagem não for `ListDepartamentos`, ajustar o import — confirmar com
`ls app/Filament/Resources/Departamentos/Pages/`. Se `test_exclui_departamento_que_nao_responde_por_nenhum_tipo` falhar por FK de `setores`/`cargos`/`departamento_usuario`, é limitação do fixture, **não** do guarda: simplificar criando um `Departamento::create([...])` novo e sem vínculos em vez de usar o DIJ.

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=ExcluirDepartamentoResponsavelTest
```

Esperado: **FAIL** em `test_nao_exclui_departamento_que_responde_por_um_tipo` — `QueryException` (FK) ou o registro sumindo.

- [ ] **Passo 3: Somar o guarda ao Resource**

Em `app/Filament/Resources/Departamentos/DepartamentoResource.php`, somar os imports:

```php
use App\Support\Autorizacao\GlossarioCapacidades;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
```

E trocar as duas ações (`:143-149`):

```php
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Excluir')
                    // A FK de departamento_tipo_conteudo é restrictOnDelete: sem este guarda o
                    // DELETE estoura como 500. Notification NÃO aborta — quem aborta é cancel().
                    ->before(function (Departamento $record, DeleteAction $action): void {
                        self::barrarSeResponsavel($record, $action);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Excluir selecionados')
                        // ATENÇÃO: o bulk recebe $records (Collection), não $record.
                        ->before(function (Collection $records, DeleteBulkAction $action): void {
                            foreach ($records as $record) {
                                self::barrarSeResponsavel($record, $action);
                            }
                        }),
                ]),
            ]);
    }

    /** Aborta a exclusão se o departamento responde por algum tipo (a config é dona; I8). Public: o EditDepartamento consome. */
    public static function barrarSeResponsavel(Departamento $departamento, Action $acao): void
    {
        $recursos = $departamento->tiposConteudo()->pluck('recurso');

        if ($recursos->isEmpty()) {
            return;
        }

        $rotulos = $recursos->map(fn (string $r): string => GlossarioCapacidades::rotuloRecurso($r))->implode(', ');

        Notification::make()
            ->title('Departamento em uso na configuração de acesso')
            ->body("O departamento {$departamento->sigla} responde por: {$rotulos}. Remova-o em Configuração de acesso por tipo antes de excluir.")
            ->danger()
            ->send();

        $acao->cancel();
    }
```

Somar também o import `use Filament\Actions\Action;` (o tipo comum do parâmetro `$acao`).

- [ ] **Passo 4: Somar o mesmo guarda ao `EditDepartamento`**

O método `barrarSeResponsavel()` do Passo 3 deve ser **`public static`** (não `private`) — o `EditDepartamento` o consome. Ajustar a assinatura lá antes de seguir.

`app/Filament/Resources/Departamentos/Pages/EditDepartamento.php` — a `DeleteAction::make()` do `getHeaderActions()` ganha o mesmo guarda:

```php
<?php

namespace App\Filament\Resources\Departamentos\Pages;

use App\Filament\Resources\Departamentos\DepartamentoResource;
use App\Models\Departamento;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDepartamento extends EditRecord
{
    protected static string $resource = DepartamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesmo guarda da listagem: a FK é restrict, e sem isto o DELETE estoura como 500.
            DeleteAction::make()
                ->before(function (Departamento $record, DeleteAction $action): void {
                    DepartamentoResource::barrarSeResponsavel($record, $action);
                }),
        ];
    }
}
```

- [ ] **Passo 5: Rodar o teste e ver passar**

```bash
docker compose exec -T app php artisan test --filter=ExcluirDepartamentoResponsavelTest
```

Esperado: **PASS** (3 testes).

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Resources/Departamentos/ tests/Feature/Filament/ExcluirDepartamentoResponsavelTest.php
git commit -m "feat(camada-1): barra exclusão de departamento responsável por tipo (guarda do restrict)"
```

---

### Task 8: Fechamento — suíte completa e o aceite de E1

- [ ] **Passo 1: Pint em tudo**

```bash
docker compose exec -T app ./vendor/bin/pint --test
```

Esperado: **PASS** (o CI roda isto **antes** dos testes e aborta o job se falhar).

- [ ] **Passo 2: Suíte completa**

```bash
docker compose exec -T app php artisan test
```

Esperado: **verde**, com `798 + ~39 novos` testes. **Nenhuma asserção existente alterada** — só os 2 `setUp` da Task 6.

> Se `ImportadorBlogTest` acusar 2 falhas de cap de imagem (GD), é a flakiness conhecida sob carga no container — rodar isolado para confirmar; não é regressão desta fase.

- [ ] **Passo 3: Provar o aceite — E1 não mudou acesso nenhum**

```bash
git diff origin/main --stat -- app/Policies app/Models/AgendaDia.php app/Support/Conta app/Livewire/Conta app/Support/Agenda app/Filament/Schemas
```

Esperado: **saída vazia**. Qualquer arquivo listado aqui significa que E1 vazou para E2.

- [ ] **Passo 4: Conferir o dev de ponta a ponta**

```bash
docker compose exec -T app php artisan migrate --pretend | tail -3
docker compose exec -T app php artisan tinker --execute="echo 'tipos: '.App\Models\TipoConteudo::count().' | pivo: '.DB::table('departamento_tipo_conteudo')->count().PHP_EOL; echo 'AgendaDia: '.App\Models\AgendaDia::count().' | Palestra: '.App\Models\Palestra::count().' | Post: '.App\Models\Post::count().' | Palestrante: '.App\Models\Palestrante::count().PHP_EOL;"
```

Esperado: `migrate --pretend` sem migrations pendentes; `tipos: 5 | pivo: 5`; e as contagens **intactas**: `AgendaDia: 123 | Palestra: 127 | Post: 45 | Palestrante: 59`.

- [ ] **Passo 5: Push e PR**

```bash
git push -u origin camada-1-e1-fundacao
gh pr create --title "feat(camada-1): E1 — fundação da Configuração de acesso por tipo" --body "$(cat <<'CORPO'
Fundação da Camada 1. **Comportamento-neutro em acesso**: nada lê a config ainda.

Spec: `docs/superpowers/specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md` (§8, E1).
Plano: `docs/superpowers/plans/2026-07-16-camada-1-e1-fundacao.md`.

## Entrega
- `tipos_conteudo` + `departamento_tipo_conteudo` (FK `restrict` no departamento — divergência consciente do molde dos 6 pivôs: cascade faria do DELETE um 2º escritor da config, sem tela e sem trilha)
- enum `RegimeAcesso` (`do_tipo` / `por_registro`) + mapa canônico recurso→model no glossário
- `TiposConteudoSeeder` **insert-only** com a semente medida no dev (Agenda DED+DECOM · Palestra DED · Palestrante DED · Post DECOM · Evento por registro) — reexecutar `db:seed` não desfaz a config da tela (I8)
- serviço `AcessoPorTipo` (**`scoped`**, não singleton) com a pergunta única, fail-closed (I1/I2) — **escrito e testado, ninguém chama ainda**
- a matriz vira **"Configuração de acesso por tipo"**: regime + responsáveis por Section, com auditoria (I7) e cinto server-side no `salvar()`
- guarda que barra excluir departamento responsável

## Aceite
- suíte verde; **nenhuma asserção existente muda de cor**
- os 2 `setUp` de `MatrizCapacidadesTest`/`AuditoriaMatrizTest` ganham a semente (o Select de regime é `required` e entra no schema que `salvar()` valida) — mudança de *setup*, não de cor
- `git diff origin/main` não toca policies, trait, scope, `AbaAgenda`, `AgendaConta` nem os forms dos conteúdos

## Próximo
E2 — troca do filtro (é onde o acesso muda).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
CORPO
)"
```

- [ ] **Passo 6: Só mesclar com o CI verde no ÚLTIMO commit**

```bash
gh pr checks --watch
```

**Não** mesclar com check `pending`.

---

## Notas para o executor

**O que E1 NÃO faz** (é E2, e vazar aqui reprova o PR): ler a config em policy/trait/scope/aba; tirar o campo `departamentos` dos forms; deletar o `AgendaMantenedores`; mexer no `AgendaConta`.

**Se um teste existente de autorização mudar de cor em E1, pare e reporte** — o desenho garante que isso é impossível (nada consome `AcessoPorTipo`).

**Se algo do plano divergir do código real** (nome de página, assinatura de `before()`), pare e reporte em vez de improvisar: o SPEC cita arquivo:linha e foi verificado, mas o Filament v5 tem cantos e o plano assume o que foi lido em 16/07.
