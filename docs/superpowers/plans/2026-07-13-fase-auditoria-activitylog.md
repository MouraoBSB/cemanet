# Fase de Auditoria — Trilha do núcleo de autorização (spatie/laravel-activitylog) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Instalar `spatie/laravel-activitylog` e instrumentar o núcleo de autorização com uma trilha append-only: um trait automático no `User` (5 colunas) e log manual com diff antes/depois nos 3 pivôs de autorização (matriz papel×capacidade, papel do usuário, vínculo editorial) — antes da Fase D.

**Architecture:** Dois mecanismos alimentam **um helper único** (`App\Support\Autorizacao\AuditoriaAutorizacao`, métodos `static`, fonte única de porta+IP+user-agent). **M1 (automático):** trait `LogsActivity` no `User` com `logOnly` das 5 colunas + `tapActivity()` injetando o contexto → cobre painel/console/seeder/fila (é model-event). **M2 (manual):** nos 3 pontos de escrita lê-se o estado **antes**, deixa o `sync` rodar, e loga-se **só o diff** — a matriz em `MatrizCapacidades::salvar()`, e o papel/departamentos do usuário via hooks novos em `CreateUser`/`EditUser`. A "porta" (admin×sistema; `perfil` na Fase D) sai de `Filament::getCurrentPanel()?->getId()`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5.6 · spatie/laravel-permission 6.25 (guard `web`) · **spatie/laravel-activitylog `^4.12`** (a v5 exige PHP ^8.4; o projeto é 8.3 → 4.x; a 4.12.3 declara `illuminate/* ^13`) · MySQL 8 (dev via Docker; testes SQLite `:memory:` com `RefreshDatabase`).

**Fonte da verdade:** [SPEC — Fase de Auditoria](../specs/2026-07-13-fase-auditoria-activitylog.md) (aprovado no passe adversarial: B1 corrigido — snapshot em `save()` sobrescrito; R1 = `^4.12`; R3 = `{id,nome}` no diff de departamentos; A2/A3/A8 = verificações do 1º commit).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, comentários, `description` do log, commits).
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13` no topo de todo PHP **novo** (após `<?php`, antes do `namespace`). **Não** levam cabeçalho: `config/activitylog.php` e as migrations (arquivos publicados do vendor).
- **Escopo v1 = núcleo de autorização.** NÃO auditar conteúdos (`Post/Evento/Palestra/AgendaDia/Palestrante`), o vínculo depto↔conteúdo, nem `setores`/`cargos` do usuário — são de fases/domínios fora daqui.
- **Append-only:** sem UI de exclusão; **NÃO** registrar nenhum Filament Resource de `Activity` nem viewer. Retenção **INDEFINIDA**: `delete_records_older_than_days` fica alto (3650) e **inerte** — **NUNCA** agendar `activitylog:clean` no scheduler.
- **Porta:** `Filament::getCurrentPanel()?->getId() ?? 'sistema'`. **NÃO** usar `getCurrentOrDefaultPanel()`/`Filament::getId()` (caem no default `admin` → falso positivo em console/fila). Valores na v1: `admin` (painel) e `sistema` (sem painel); `perfil` só na Fase D.
- **Sem eventos do spatie:** manter `config/permission.php` `events_enabled => false`. Os pivôs são logados **manualmente** no ponto de escrita (o `syncPermissions()` faz `detach()` direto e o `sync()` do relacionamento não passa por `syncRoles()` → eventos perderiam remoções). Escrita de pivô por console/tinker é **fronteira conhecida** (não logada; há teste-guarda).
- **B1 (crítico) — snapshot do "antes" no `EditUser`:** o `saveRelationships()` roda **dentro** de `form->getState()` (`filament/schemas/src/Concerns/HasState.php:497`), **antes** de `mutateFormDataBeforeSave`/`afterSave`. Portanto capturar o "antes" **sobrescrevendo `save()`** (antes do `parent::save()`), sempre com **query fresca** (`->roles()`/`->departamentos()`, com parênteses — nunca a propriedade cacheada); logar em `afterSave()`. **NÃO** usar `mutateFormDataBeforeSave`. No `CreateRecord`, `saveRelationships()` é explícito em `CreateRecord.php:115`, antes de `afterCreate` → `afterCreate` está correto.
- **Propriedades Livewire tipadas:** as propriedades de snapshot no `EditUser` são `public array $papelAntes = []` e `public array $deptosAntes = []` (sempre inicializadas com `[]`). **Nunca** uma `public` tipada não-inicializada (quebra a hidratação: "Typed property must not be accessed before initialization").
- **Escopo das asserções de teste por `log_name`:** o trait do `User` (M1) grava entradas `log_name='usuario'` a cada `create`/`update`/`delete` (inclusive o admin do `actingAsAdmin()` e os usuários de factory). Toda asserção de **contagem/consulta** sobre entradas de pivô deve ser **escopada por `log_name='autorizacao'`** (ou por `event`/`description`), nunca `assertDatabaseCount('activity_log', 0)` cru — senão as entradas `usuario` a fazem falhar (e isso muda entre tarefas, conforme o trait entra).
- **Porta nos testes:** para asserir `porta='admin'`, setar o painel no arrange — `Filament::setCurrentPanel(Filament::getPanel('admin'))` — antes da ação; para `porta='sistema'`, **não** setar painel (estado default do teste).
- **Banco:** MySQL só por migrations **incrementais**. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo no dev (apagam 127 palestras, 45 posts, mídia). Publicar a migration do pacote e **conferir o schema antes** de `php artisan migrate`. Testes usam `RefreshDatabase` (banco isolado).
- **Ferramentas no container:** `docker compose exec -T app composer ...`, `docker compose exec -T app php artisan ...`, `docker compose exec -T app ./vendor/bin/pint`. Node/Vite não se aplicam (sem front nesta fase). Editar PHP no dev só exige `restart` para visualização no navegador (FPM, OPcache `validate_timestamps=0`); **os testes rodam no CLI do container e leem o código fresco** — sem restart entre passos de teste. Esta fase **não tem UI visível** para conferir no navegador.
- **Verificações do 1º commit (SPEC §14 A2/A3/A8), já embutidas na Task 1/3:** (A2) o pacote publica as migrations `create_activity_log_table` + `add_event_column` + `add_batch_uuid_column`, tabela `activity_log`; (A3) `tapActivity()` roda só no fluxo model-driven (M1), por isso os manuais levam contexto via `withProperties()`; (A8) `logOnly`+`dontSubmitEmptyLogs` não emite ao mudar só coluna fora das 5.
- **Pint** limpo antes do push; suíte completa no container; **commits atômicos** pt-BR na branch **`fase-auditoria-activitylog`** (já criada a partir de `main`).

---

### Task 1: Instalar o pacote, publicar/inspecionar/migrar, retenção inerte + teste de infra

**Files:**
- Modify: `composer.json` / `composer.lock` (via `composer require`)
- Create (publicados): `config/activitylog.php`; `database/migrations/*_create_activity_log_table.php` (+ `add_event_column`, `add_batch_uuid_column`)
- Test: `tests/Feature/Autorizacao/AuditoriaInfraTest.php`

**Interfaces:**
- Produces: tabela `activity_log` + model `Spatie\Activitylog\Models\Activity` disponíveis; `config('activitylog.delete_records_older_than_days') === 3650`. Consumidos por todas as tarefas seguintes.

- [ ] **Step 1: Instalar o pacote (constraint cravada)**

Run:
```bash
docker compose exec -T app composer require "spatie/laravel-activitylog:^4.12"
```
Expected: resolve/instala **4.12.3** (auto-discovery registra o service provider). Se o resolver recusar por PHP/Laravel, é bloqueador — parar e reportar.

- [ ] **Step 2: Escrever o teste de infra que falha**

`tests/Feature/Autorizacao/AuditoriaInfraTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaInfraTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabela_activity_log_existe_com_colunas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('activity_log'));
        $this->assertTrue(Schema::hasColumns('activity_log', [
            'log_name', 'description', 'subject_type', 'subject_id',
            'causer_type', 'causer_id', 'properties', 'event', 'batch_uuid',
        ]));
    }

    public function test_entrada_ida_e_volta(): void
    {
        activity('teste')->log('ping');

        $this->assertDatabaseHas('activity_log', ['log_name' => 'teste', 'description' => 'ping']);
    }

    public function test_retencao_inerte_e_nao_agendada(): void
    {
        $this->assertSame(3650, config('activitylog.delete_records_older_than_days'));
    }

    public function test_painel_admin_nao_registra_resource_de_activity(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $this->assertNotSame(Activity::class, $resource::getModel());
        }
    }
}
```

- [ ] **Step 3: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaInfraTest`
Expected: FAIL (`activity_log` não existe — migration ainda não publicada; e `config('activitylog...')` nulo).

- [ ] **Step 4: Publicar config + migrations do pacote**

Run:
```bash
docker compose exec -T app php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
```
Expected: cria `config/activitylog.php` e as 3 migrations em `database/migrations/`.

- [ ] **Step 5: Inspecionar a migration publicada (regra do projeto) — confirma A2**

Abrir `database/migrations/*_create_activity_log_table.php` e confirmar: `Schema::create('activity_log', ...)` (nome da tabela = `activity_log`) e as colunas `log_name`, `description`, `subject_type/subject_id`, `causer_type/causer_id`, `properties` (json), `created_at/updated_at`; e as migrations complementares `add_event_column` e `add_batch_uuid_column`. Se o nome da tabela divergir de `activity_log`, ajustar o teste do Step 2 e as demais tarefas — **parar e reportar** antes de migrar.

- [ ] **Step 6: Retenção inerte no `config/activitylog.php`**

Em `config/activitylog.php`, trocar o valor de `delete_records_older_than_days` para `3650` e anotar (comentário curto pt-BR):

```php
    /*
     * Retenção INDEFINIDA (append-only): valor alto e INERTE — o comando
     * activitylog:clean NÃO é agendado no scheduler. Salvaguarda apenas.
     */
    'delete_records_older_than_days' => 3650,
```

Confirmar que **não** há `activitylog:clean` em `bootstrap/app.php` (`->withSchedule(...)`) nem em `routes/console.php` — e **não** adicionar.

- [ ] **Step 7: Rodar o teste de infra para ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaInfraTest`
Expected: PASS (o `RefreshDatabase` já cria `activity_log` a partir da migration publicada).

- [ ] **Step 8: Migrar o banco de DEV (incremental)**

Run: `docker compose exec -T app php artisan migrate`
Expected: roda só as 3 migrations novas do activitylog; **nenhuma** tabela existente é tocada. (🚫 nunca `fresh`/`refresh`/`wipe`/`reset`.)

- [ ] **Step 9: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add composer.json composer.lock config/activitylog.php database/migrations tests/Feature/Autorizacao/AuditoriaInfraTest.php
git commit -m "feat(auditoria): instala spatie/laravel-activitylog (4.12) com retenção inerte e teste de infra"
```

---

### Task 2: Helper único `AuditoriaAutorizacao` (porta + contexto + diff + registrar)

**Files:**
- Create: `app/Support/Autorizacao/AuditoriaAutorizacao.php`
- Test: `tests/Feature/Autorizacao/AuditoriaHelperTest.php`

**Interfaces:**
- Consumes: model `Activity` e helper `activity()` (Task 1).
- Produces:
  - `AuditoriaAutorizacao::porta(): string` — `'admin'|'sistema'` (`perfil` futuro).
  - `AuditoriaAutorizacao::contexto(): array` — `['porta'=>string, 'ip'=>?string, 'user_agent'=>?string]`.
  - `AuditoriaAutorizacao::diff(array $antes, array $depois): array` — `['adicionados'=>list, 'removidos'=>list]`.
  - `AuditoriaAutorizacao::registrarPapelCapacidades(Role $papel, array $antes, array $depois): void` — subject Role, `description='capacidades do papel {name} alteradas'`.
  - `AuditoriaAutorizacao::registrarPapelUsuario(User $u, array $antes, array $depois): void` — subject User, `description='papel do usuário alterado'`; `$antes`/`$depois` = listas de nomes de papel.
  - `AuditoriaAutorizacao::registrarDepartamentosUsuario(User $u, array $antes, array $depois): void` — subject User, `description='departamentos do usuário alterados'`; `$antes`/`$depois` = mapas `[id => nome]`; itens do diff = `{id, nome}`.
  - Todas as entradas usam `log_name='autorizacao'` e no-op se o diff for vazio.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/AuditoriaHelperTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_diff_calcula_adicionados_e_removidos(): void
    {
        $diff = AuditoriaAutorizacao::diff(['a', 'b'], ['b', 'c']);

        $this->assertSame(['c'], $diff['adicionados']);
        $this->assertSame(['a'], $diff['removidos']);
    }

    public function test_porta_e_sistema_sem_painel_corrente(): void
    {
        $this->assertSame('sistema', AuditoriaAutorizacao::porta());
    }

    public function test_porta_e_admin_com_painel_admin_corrente(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame('admin', AuditoriaAutorizacao::porta());
    }

    public function test_registrar_papel_usuario_sem_mudanca_nao_loga(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarPapelUsuario($u, ['diretor'], ['diretor']);

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_registrar_papel_usuario_loga_diff_e_contexto(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarPapelUsuario($u, ['diretor'], ['trabalhador']);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'papel do usuário alterado',
            'subject_type' => $u->getMorphClass(),
            'subject_id' => $u->id,
        ]);

        $props = Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
        $this->assertSame(['trabalhador'], $props['diff']['adicionados']);
        $this->assertSame(['diretor'], $props['diff']['removidos']);
        $this->assertSame('sistema', $props['porta']);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_registrar_departamentos_usa_id_e_nome(): void
    {
        $u = User::factory()->create();

        AuditoriaAutorizacao::registrarDepartamentosUsuario($u, [3 => 'DECOM'], [3 => 'DECOM', 5 => 'DAS']);

        $props = Activity::query()->where('log_name', 'autorizacao')->latest('id')->first()->properties->toArray();
        $this->assertSame([['id' => 5, 'nome' => 'DAS']], $props['diff']['adicionados']);
        $this->assertSame([], $props['diff']['removidos']);
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaHelperTest`
Expected: FAIL (`Class "App\Support\Autorizacao\AuditoriaAutorizacao" not found`).

- [ ] **Step 3: Criar o helper**

`app/Support/Autorizacao/AuditoriaAutorizacao.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace App\Support\Autorizacao;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class AuditoriaAutorizacao
{
    /** log_name das entradas manuais dos 3 pivôs de autorização. */
    public const LOG = 'autorizacao';

    /** Painel corrente: 'admin' | 'sistema' ('perfil' na Fase D). Nunca cai no default. */
    public static function porta(): string
    {
        return Filament::getCurrentPanel()?->getId() ?? 'sistema';
    }

    /** Contexto comum a toda entrada: porta + IP + user-agent (null fora de request HTTP). */
    public static function contexto(): array
    {
        $request = request();

        return [
            'porta' => self::porta(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];
    }

    /** Diff de duas listas de nomes: {adicionados, removidos} reindexados. */
    public static function diff(array $antes, array $depois): array
    {
        return [
            'adicionados' => array_values(array_diff($depois, $antes)),
            'removidos' => array_values(array_diff($antes, $depois)),
        ];
    }

    /** Matriz papel×capacidade: subject = Role; diff de nomes de permission. */
    public static function registrarPapelCapacidades(Role $papel, array $antes, array $depois): void
    {
        self::registrar($papel, "capacidades do papel {$papel->name} alteradas", self::diff($antes, $depois));
    }

    /** Papel do usuário: subject = User; diff de nomes de papel. */
    public static function registrarPapelUsuario(User $usuario, array $antes, array $depois): void
    {
        self::registrar($usuario, 'papel do usuário alterado', self::diff($antes, $depois));
    }

    /**
     * Vínculo editorial: subject = User; diff por id, itens {id, nome} (estável a rename).
     *
     * @param  array<int, string>  $antes   [id => nome] antes do sync
     * @param  array<int, string>  $depois  [id => nome] depois do sync
     */
    public static function registrarDepartamentosUsuario(User $usuario, array $antes, array $depois): void
    {
        $idsAdicionados = array_diff(array_keys($depois), array_keys($antes));
        $idsRemovidos = array_diff(array_keys($antes), array_keys($depois));

        $diff = [
            'adicionados' => array_values(array_map(
                fn (int $id): array => ['id' => $id, 'nome' => $depois[$id]],
                $idsAdicionados,
            )),
            'removidos' => array_values(array_map(
                fn (int $id): array => ['id' => $id, 'nome' => $antes[$id]],
                $idsRemovidos,
            )),
        ];

        self::registrar($usuario, 'departamentos do usuário alterados', $diff);
    }

    /** Escreve 1 entrada 'autorizacao'; no-op se o diff for vazio. */
    private static function registrar(Model $subject, string $descricao, array $diff): void
    {
        if (empty($diff['adicionados']) && empty($diff['removidos'])) {
            return;
        }

        activity(self::LOG)
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties(['diff' => $diff] + self::contexto())
            ->log($descricao);
    }
}
```

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaHelperTest`
Expected: PASS (6 casos verdes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add app/Support/Autorizacao/AuditoriaAutorizacao.php tests/Feature/Autorizacao/AuditoriaHelperTest.php
git commit -m "feat(auditoria): helper AuditoriaAutorizacao (porta, contexto, diff, registrar)"
```

---

### Task 3: Mecanismo 1 — trait `LogsActivity` no `User`

**Files:**
- Modify: `app/Models/User.php` (import + `use LogsActivity` na L25; `getActivitylogOptions()`; `tapActivity()`)
- Test: `tests/Feature/Autorizacao/AuditoriaUsuarioTest.php`

**Interfaces:**
- Consumes: `AuditoriaAutorizacao::contexto()` (Task 2).
- Produces: toda escrita de `User` (`created`/`updated`/`deleted`) que toque uma das 5 colunas gera entrada `log_name='usuario'` com `properties.attributes`/`old` (diff campo a campo) + `porta`/`ip`/`user_agent`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/AuditoriaUsuarioTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_atualizar_coluna_auditada_gera_entrada_usuario(): void
    {
        $u = User::factory()->create(['ativo' => true]);
        $u->update(['ativo' => false]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario',
            'event' => 'updated',
            'description' => 'usuário atualizado',
            'subject_type' => $u->getMorphClass(),
            'subject_id' => $u->id,
        ]);

        $props = Activity::query()->where('log_name', 'usuario')->where('event', 'updated')
            ->latest('id')->first()->properties->toArray();
        $this->assertFalse((bool) $props['attributes']['ativo']);
        $this->assertTrue((bool) $props['old']['ativo']);
        $this->assertSame('sistema', $props['porta']);
    }

    public function test_criacao_e_exclusao_geram_entradas(): void
    {
        $u = User::factory()->create();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario', 'event' => 'created', 'subject_id' => $u->id,
        ]);

        $id = $u->id;
        $u->delete();
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'usuario', 'event' => 'deleted', 'subject_id' => $id,
        ]);
    }

    public function test_mudar_so_coluna_fora_das_cinco_nao_loga(): void
    {
        $u = User::factory()->create();
        Activity::query()->delete(); // limpa a entrada 'created'

        $u->update(['email_verified_at' => now()]);

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_porta_admin_quando_no_painel(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $u = User::factory()->create();

        $props = Activity::query()->where('log_name', 'usuario')->where('event', 'created')
            ->latest('id')->first()->properties->toArray();
        $this->assertSame('admin', $props['porta']);
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaUsuarioTest`
Expected: FAIL (nenhuma entrada `usuario` — o trait ainda não existe).

- [ ] **Step 3: Adicionar o trait ao `User`**

Em `app/Models/User.php`, adicionar os imports (junto aos demais `use` de topo):

```php
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
```

Incluir `LogsActivity` na lista de traits da classe (L25):

```php
    use HasFactory, HasRoles, LogsActivity, Notifiable, TemIniciais;
```

E adicionar os dois métodos ao corpo da classe:

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('usuario')
            ->logOnly(['name', 'email', 'google_id', 'socio', 'ativo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'usuário criado',
                'updated' => 'usuário atualizado',
                'deleted' => 'usuário excluído',
                default => "usuário {$evento}",
            });
    }

    /** IP + user-agent + porta em toda entrada automática (fonte única: o helper). */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge(AuditoriaAutorizacao::contexto());
    }
```

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaUsuarioTest`
Expected: PASS (4 casos verdes).

- [ ] **Step 5: Rodar a suíte de autorização (regressão do escopo por log_name)**

Run: `docker compose exec -T app php artisan test --filter=Auditoria`
Expected: PASS (Infra + Helper + Usuario — confirma que o trait não quebrou as asserções escopadas por `log_name` da Task 2).

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add app/Models/User.php tests/Feature/Autorizacao/AuditoriaUsuarioTest.php
git commit -m "feat(auditoria): trait LogsActivity no User (5 colunas, porta/ip/ua via tapActivity)"
```

---

### Task 4: Mecanismo 2a — diff antes/depois na `MatrizCapacidades::salvar()`

**Files:**
- Modify: `app/Filament/Pages/MatrizCapacidades.php` (`salvar()`: relê "antes" + chama o helper por papel; import do helper)
- Test: `tests/Feature/Autorizacao/AuditoriaMatrizTest.php`

**Interfaces:**
- Consumes: `AuditoriaAutorizacao::registrarPapelCapacidades()` (Task 2).
- Produces: cada salvamento da matriz gera 0..2 entradas `autorizacao` (uma por papel com diff não-vazio), subject = Role, `description='capacidades do papel {name} alteradas'`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/AuditoriaMatrizTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Filament\Pages\MatrizCapacidades;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaMatrizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin')); // porta = admin
    }

    public function test_salvar_capacidade_loga_adicao_com_porta_admin(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = Role::findByName('diretor', 'web');
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'autorizacao',
            'description' => 'capacidades do papel diretor alteradas',
            'subject_type' => $diretor->getMorphClass(),
            'subject_id' => $diretor->id,
        ]);

        $props = Activity::query()->where('log_name', 'autorizacao')
            ->where('subject_id', $diretor->id)->latest('id')->first()->properties->toArray();
        $this->assertContains('palestra.editar', $props['diff']['adicionados']);
        $this->assertSame('admin', $props['porta']);
        $this->assertArrayHasKey('ip', $props);
        $this->assertArrayHasKey('user_agent', $props);
    }

    public function test_salvar_desmarcando_loga_remocao(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => false])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = Role::findByName('diretor', 'web');
        $props = Activity::query()->where('log_name', 'autorizacao')
            ->where('subject_id', $diretor->id)->latest('id')->first()->properties->toArray();
        $this->assertContains('palestra.editar', $props['diff']['removidos']);
    }

    public function test_salvar_sem_mudanca_nao_loga(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }

    public function test_pivo_por_console_nao_loga_autorizacao(): void
    {
        // Escrita direta (fora da Página) NÃO passa pelo log manual — fronteira conhecida do SPEC §8.
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $this->assertSame(0, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaMatrizTest`
Expected: FAIL (nenhuma entrada `autorizacao` — a `salvar()` ainda não loga).

- [ ] **Step 3: Instrumentar `salvar()`**

Em `app/Filament/Pages/MatrizCapacidades.php`, adicionar o import:

```php
use App\Support\Autorizacao\AuditoriaAutorizacao;
```

E, dentro do `foreach` de `salvar()`, capturar o "antes" e logar (substituir a linha única do `syncPermissions`):

```php
            $role = Role::findByName($papel, 'web');
            $antes = $role->permissions()->pluck('name')->all();   // ANTES (relê do banco)
            $role->syncPermissions($marcados);
            AuditoriaAutorizacao::registrarPapelCapacidades($role, $antes, $marcados);
```

(Manter o restante do método: montagem de `$marcados`, a iteração por `PAPEIS_EDITAVEIS`, e a `Notification` de sucesso.)

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaMatrizTest`
Expected: PASS (4 casos verdes).

- [ ] **Step 5: Rodar o teste da matriz pré-existente (regressão)**

Run: `docker compose exec -T app php artisan test --filter=MatrizCapacidadesTest`
Expected: PASS (a instrumentação não muda o comportamento de `syncPermissions`).

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add app/Filament/Pages/MatrizCapacidades.php tests/Feature/Autorizacao/AuditoriaMatrizTest.php
git commit -m "feat(auditoria): log manual do diff de capacidades na matriz (subject Role)"
```

---

### Task 5: Mecanismo 2b/2c — hooks de papel e departamentos no `UserResource`

**Files:**
- Modify: `app/Filament/Resources/Users/Pages/CreateUser.php` (`afterCreate()`)
- Modify: `app/Filament/Resources/Users/Pages/EditUser.php` (props `$papelAntes`/`$deptosAntes`; `save()` sobrescrito; `afterSave()`)
- Test: `tests/Feature/Autorizacao/AuditoriaUserResourceTest.php`

**Interfaces:**
- Consumes: `AuditoriaAutorizacao::registrarPapelUsuario()` e `::registrarDepartamentosUsuario()` (Task 2).
- Produces: criar/editar usuário no `/admin` gera 0..2 entradas `autorizacao` por save (papel; departamentos), subject = User, `porta='admin'`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/AuditoriaUserResourceTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-13

namespace Tests\Feature\Autorizacao;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaUserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin')); // porta = admin
    }

    public function test_criar_usuario_com_papel_e_departamento_loga_duas_entradas(): void
    {
        $diretor = Role::findByName('diretor', 'web');
        $decom = Departamento::where('sigla', 'DECOM')->first();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Fulano de Teste',
                'email' => 'fulano@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$diretor->id],
                'departamentos' => [$decom->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $u = User::where('email', 'fulano@teste.com')->first();

        $papel = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'papel do usuário alterado')->first();
        $this->assertNotNull($papel);
        $this->assertSame(['diretor'], $papel->properties->toArray()['diff']['adicionados']);
        $this->assertSame('admin', $papel->properties->toArray()['porta']);

        $deptos = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'departamentos do usuário alterados')->first();
        $this->assertNotNull($deptos);
        $this->assertSame(
            [['id' => $decom->id, 'nome' => $decom->nome]],
            $deptos->properties->toArray()['diff']['adicionados'],
        );
    }

    public function test_editar_troca_papel_loga_diff(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $trabalhador = Role::findByName('trabalhador', 'web');

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['roles' => [$trabalhador->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $props = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'papel do usuário alterado')->latest('id')->first()->properties->toArray();
        $this->assertSame(['trabalhador'], $props['diff']['adicionados']);
        $this->assertSame(['diretor'], $props['diff']['removidos']);
    }

    public function test_editar_troca_departamento_loga_diff_id_nome(): void
    {
        // Fecha a rede: exercita registrarDepartamentosUsuario pelo fluxo do B1 (save() override), o mais arriscado.
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $decom = Departamento::where('sigla', 'DECOM')->first();
        $outro = Departamento::where('id', '!=', $decom->id)->first();
        $u->departamentos()->sync([$decom->id]); // estado inicial: DECOM

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['departamentos' => [$outro->id]]) // troca DECOM -> outro
            ->call('save')
            ->assertHasNoFormErrors();

        $props = Activity::query()->where('log_name', 'autorizacao')->where('subject_id', $u->id)
            ->where('description', 'departamentos do usuário alterados')->latest('id')->first()->properties->toArray();
        $this->assertSame([['id' => $outro->id, 'nome' => $outro->nome]], $props['diff']['adicionados']);
        $this->assertSame([['id' => $decom->id, 'nome' => $decom->nome]], $props['diff']['removidos']);
    }

    public function test_editar_sem_mudar_papel_ou_departamento_nao_loga_autorizacao(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $antes = DB::table('activity_log')->where('log_name', 'autorizacao')->count();

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['name' => 'Nome Alterado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($antes, DB::table('activity_log')->where('log_name', 'autorizacao')->count());
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaUserResourceTest`
Expected: FAIL (nenhuma entrada `autorizacao` — hooks ainda não existem). Em especial, `test_editar_troca_papel_loga_diff` é o **guard do B1**.

- [ ] **Step 3: Hook no `CreateUser`**

`app/Filament/Resources/Users/Pages/CreateUser.php` — adicionar o import e o `afterCreate()`:

```php
use App\Support\Autorizacao\AuditoriaAutorizacao;
```

```php
    protected function afterCreate(): void
    {
        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('nome', 'id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, [], $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, [], $deptosDepois);
    }
```

- [ ] **Step 4: Hooks no `EditUser`**

`app/Filament/Resources/Users/Pages/EditUser.php` — adicionar o import, as duas propriedades, o `save()` sobrescrito (snapshot **antes** do `parent::save()`, query fresca) e o `afterSave()` (log pós-sync). Manter o `getHeaderActions()` com `DeleteAction` já existente:

```php
use App\Support\Autorizacao\AuditoriaAutorizacao;
```

```php
    public array $papelAntes = [];

    public array $deptosAntes = [];

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // B1: o saveRelationships roda dentro de getState() (antes de afterSave); capturar o "antes" AQUI,
        // por query fresca (->roles()/->departamentos()), antes do parent::save().
        $this->papelAntes = $this->record->roles()->pluck('name')->all();
        $this->deptosAntes = $this->record->departamentos()->pluck('nome', 'id')->all();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function afterSave(): void
    {
        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('nome', 'id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, $this->papelAntes, $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, $this->deptosAntes, $deptosDepois);
    }
```

- [ ] **Step 5: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaUserResourceTest`
Expected: PASS (4 casos verdes — incluindo o guard do B1 no papel e no departamento).

- [ ] **Step 6: Rodar o teste de UserResource pré-existente (regressão)**

Run: `docker compose exec -T app php artisan test --filter=UsuarioResourceTest`
Expected: PASS (os hooks não alteram o salvamento do papel/departamentos).

- [ ] **Step 7: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add app/Filament/Resources/Users/Pages/CreateUser.php app/Filament/Resources/Users/Pages/EditUser.php tests/Feature/Autorizacao/AuditoriaUserResourceTest.php
git commit -m "feat(auditoria): log manual de papel e departamentos no UserResource (snapshot em save())"
```

---

### Task 6: Fecho — suíte completa + Pint verdes

**Files:** nenhum novo (verificação final).

- [ ] **Step 1: Pint em todo o projeto**

Run: `docker compose exec -T app ./vendor/bin/pint`
Expected: sem drift (ou aplica e revisa o diff).

- [ ] **Step 2: Suíte completa no container**

Run: `docker compose exec -T app php artisan test`
Expected: verde. (Ciência `flaky-importadorblog-gd-cap-imagem`: 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados, não é regressão desta fase.)

- [ ] **Step 3: Commit final (se Pint tiver ajustado algo)**

```bash
git add -A
git commit -m "chore(auditoria): Pint e suíte completa verdes"
```

---

## Self-Review (writing-plans)

- **Cobertura do SPEC:** §4 (trait M1) → Task 3; §5a (matriz) → Task 4; §5b/c (UserResource, B1) → Task 5; §6 (porta) → helper Task 2 + testes de porta (Tasks 2/3/4/5); §7 (log_name/description/properties/helper) → Task 2; §8 (fronteira console) → Task 4 (`test_pivo_por_console_nao_loga`); instalação/config/retenção/append-only (§3.1/§9) → Task 1; testes §10.1-13 → distribuídos (Infra=1; Usuário 3-6=3; Matriz 7-9+13=4; UserResource 10-12=5, com papel **e** departamento no edit — §10.11 completo). **Sem lacuna.**
- **Placeholders:** nenhum — todo passo tem código/comando/expected reais.
- **Consistência de tipos:** `registrarPapelCapacidades/PapelUsuario/DepartamentosUsuario`, `porta()`, `contexto()`, `diff()` idênticos entre a definição (Task 2) e os consumidores (Tasks 3-5); `save(bool, bool): void` casa a assinatura do vendor (`EditRecord.php:159`); `$papelAntes`/`$deptosAntes` = `array` com default `[]`.

## Execução

Entregável: **este PLANO**. Conforme travado com o dono, **PARAR para o passe adversarial do plano** (o consultor confere o código das tasks contra o real) **antes** da execução. Só depois: escolher execução subagent-driven (recomendada) ou inline; abrir PR; passe final; merge.
