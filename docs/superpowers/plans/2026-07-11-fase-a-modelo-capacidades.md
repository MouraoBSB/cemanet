# Fase A — Modelo de Capacidades — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Instituir o eixo CAPACIDADE (quem edita), separado da VISIBILIDADE (quem vê), como fundação server-side da edição por trabalhadores/diretores fora do `/admin` (Fase D) — sem mudar o comportamento do painel.

**Architecture:** 16 permissions `recurso.acao` (spatie, guard `web`) nascem de uma constante declarativa e são semeadas por um seeder dedicado, **sem** atribuição a papéis (a matriz é Fase C). Um `Gate::before` autoral deixa o `administrador` passar por tudo; `register_permission_check_method` é **desligado** para que nomes crus de permissão deixem de ser abilities de gate (fecha o bypass do escopo). O escopo por departamento vive num vínculo dedicado `departamento_usuario` (fonte única `User::departamentos()`) e é aplicado por um trait `AutorizaPorDepartamento` (contrato `TemDepartamento`), consumido pela `EventoPolicy` (filtro real). `Palestra`/`Post`/`AgendaDia` recebem policies **fail-closed** (negam direto) até ganharem departamento na Fase B. Tudo provado por **teste de unidade** (`Gate::forUser`); o `/admin` é coberto pela suíte de resource-tests existente.

**Tech Stack:** PHP 8.3 · Laravel 13 · spatie/laravel-permission (guard `web`, teams OFF, wildcard OFF) · Filament 5 (não consome as abilities pt-BR das policies) · MySQL 8 (dev via Docker; testes com `RefreshDatabase`).

**Fonte da verdade:** [SPEC — Fase A](../specs/2026-07-11-fase-a-modelo-capacidades.md) (aprovado no passe adversarial do dono).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, comentários, mensagens, commits).
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11` no topo de todo PHP **novo** (após `<?php`, antes do `namespace`) — **exceto migrations**, que seguem a convenção do projeto (as 24 migrations existentes não têm cabeçalho; a classe é anônima).
- **Guard:** roles e permissions sempre no guard **`web`**.
- **Decisão 3.0 (flag OFF):** `config/permission.php` → `register_permission_check_method => false`. Em consequência, **dentro das policies** a posse da permissão é checada por **`$user->hasPermissionTo('recurso.acao')`** (método direto do trait), **NUNCA** por `$user->can(...)` (que deixa de resolver nomes de permissão).
- **Fail-closed sempre:** objeto sem departamento, usuário sem vínculo, ou ausência da permissão ⇒ negar. Só o admin passa (antes, no `Gate::before`).
- **`/admin` intocado:** o painel é admin-only e o admin passa no `Gate::before`; o Filament v5 não usa strict authorization, então as abilities pt-BR das policies são inertes para ele. A não-regressão do painel é provada pela **suíte de resource-tests existente** (`EventoResourceTest`, `PostResourceTest`, `PalestraResourceTest`, `AgendaDiaResourceTest`), não pelo Gate cru.
- **Banco:** MySQL só por migrations **incrementais**. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo no dev — apagam os dados importados. No dev, só `php artisan migrate`. Nos testes, `RefreshDatabase` é seguro (banco de teste isolado).
- **Ferramentas no container:** `docker compose exec -T app php artisan ...` e `docker compose exec -T app ./vendor/bin/pint`. Esta fase não tem front/build (nada de npm/Vite).
- **Pint** limpo antes do push; suíte no container; **commits atômicos** pt-BR na branch **`fase-a-modelo-capacidades`** (criada a partir de `main`).
- **Escopo travado:** NÃO atribuir permissions a papéis (Fase C), NÃO fazer backfill/tela de vínculo (Fase B/C), NÃO tocar no eixo de visibilidade (`podeSerVistoPor`/`scopeVisiveisPara` intactos). O recurso `agenda` mapeia a **`AgendaDia`**.

---

### Task 1: Vocabulário — `GlossarioCapacidades` + `CapacidadesSeeder`

**Files:**
- Create: `app/Support/Autorizacao/GlossarioCapacidades.php`
- Create: `database/seeders/CapacidadesSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (registrar o seeder)
- Test: `tests/Feature/Autorizacao/CapacidadesSeederTest.php`

**Interfaces:**
- Produces: `App\Support\Autorizacao\GlossarioCapacidades::RECURSOS` (`['evento','palestra','post','agenda']`), `::ACOES` (`['ver','criar','editar','excluir']`), `::permissions(): array` (16 strings `"recurso.acao"`). `Database\Seeders\CapacidadesSeeder` cria as 16 permissions (guard `web`). Consumido pelos testes das Tasks 2, 4 e 5 (`$this->seed(CapacidadesSeeder::class)`).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/CapacidadesSeederTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CapacidadesSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_semeia_os_16_nomes_exatos_e_e_idempotente(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $this->seed(CapacidadesSeeder::class); // 2ª vez não duplica

        $esperados = [
            'evento.ver', 'evento.criar', 'evento.editar', 'evento.excluir',
            'palestra.ver', 'palestra.criar', 'palestra.editar', 'palestra.excluir',
            'post.ver', 'post.criar', 'post.editar', 'post.excluir',
            'agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir',
        ];

        $this->assertSame(16, Permission::where('guard_name', 'web')->count());
        foreach ($esperados as $nome) {
            $this->assertDatabaseHas('permissions', ['name' => $nome, 'guard_name' => 'web']);
        }
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadesSeederTest`
Expected: FAIL (`Class "Database\Seeders\CapacidadesSeeder" not found`).

- [ ] **Step 3: Criar `GlossarioCapacidades`**

`app/Support/Autorizacao/GlossarioCapacidades.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Support\Autorizacao;

/**
 * Fonte única do vocabulário de CAPACIDADE (quem edita). Mesmo padrão declarativo de
 * App\Importacao\GlossarioUsuarios, em local próprio. Palestrante/Biblioteca ficam FORA
 * (decisão pendente do "Meu Perfil").
 */
class GlossarioCapacidades
{
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda'];

    public const ACOES = ['ver', 'criar', 'editar', 'excluir'];

    /** @return list<string> os 16 nomes "recurso.acao". */
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

- [ ] **Step 4: Criar `CapacidadesSeeder`**

`database/seeders/CapacidadesSeeder.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Database\Seeders;

use App\Support\Autorizacao\GlossarioCapacidades;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

/**
 * Semeia as 16 permissions de capacidade (guard web), idempotente. NÃO atribui a papéis:
 * a matriz papel→permissão é a Fase C. Ver App\Support\Autorizacao\GlossarioCapacidades.
 */
class CapacidadesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (GlossarioCapacidades::permissions() as $nome) {
            Permission::updateOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }
    }
}
```

- [ ] **Step 5: Registrar no `DatabaseSeeder`**

Em `database/seeders/DatabaseSeeder.php`, dentro de `run()`, adicionar a chamada logo após `EstruturaCemaSeeder`:

```php
        $this->call(CategoriaSeeder::class);
        $this->call(EstruturaCemaSeeder::class);
        $this->call(CapacidadesSeeder::class);
```

- [ ] **Step 6: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=CapacidadesSeederTest`
Expected: PASS.

- [ ] **Step 7: Semear no dev e conferir (incremental, não destrutivo)**

Run: `docker compose exec -T app php artisan db:seed --class=CapacidadesSeeder`
Run: `docker compose exec -T app php artisan tinker --execute="echo Spatie\Permission\Models\Permission::where('guard_name','web')->whereIn('name', App\Support\Autorizacao\GlossarioCapacidades::permissions())->count();"`
Expected: imprime `16`.

- [ ] **Step 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Autorizacao database/seeders tests/Feature/Autorizacao
git add app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/CapacidadesSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php
git commit -m "feat(capacidades): vocabulário de 16 permissions + CapacidadesSeeder"
```

---

### Task 2: `Gate::before` do admin + desligar `register_permission_check_method`

**Files:**
- Modify: `config/permission.php` (`register_permission_check_method => false`)
- Modify: `app/Providers/AppServiceProvider.php` (`Gate::before` no `boot()`)
- Test: `tests/Feature/Autorizacao/GateFundacaoTest.php`

**Interfaces:**
- Produces: um `Gate::before` que devolve `true` para `administrador`, `null` para os demais. O flag OFF garante que nomes crus de permissão (`evento.editar`) **não** são mais abilities de gate. Consumido pelas Tasks 4 e 5 (admin passa; nome cru nega).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/GateFundacaoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateFundacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_passa_em_qualquer_ability(): void
    {
        Role::findOrCreate('administrador', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        // Ability arbitrária, sem policy: só o Gate::before do admin pode aprovar.
        $this->assertTrue(Gate::forUser($admin)->allows('ability-arbitraria-sem-policy'));
    }

    public function test_nao_admin_nao_passa_por_ability_sem_policy(): void
    {
        $u = User::factory()->create();

        $this->assertFalse(Gate::forUser($u)->allows('ability-arbitraria-sem-policy'));
    }

    public function test_nome_cru_de_permissao_nao_e_ability_com_flag_off(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $u = User::factory()->create();      // não-admin
        $u->givePermissionTo('evento.editar'); // tem a permissão...

        // ...mas com o flag OFF o nome cru não resolve como ability de gate.
        $this->assertFalse(Gate::forUser($u)->allows('evento.editar'));
        // A posse em si continua verdadeira pelo método direto do trait:
        $this->assertTrue($u->hasPermissionTo('evento.editar'));
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=GateFundacaoTest`
Expected: FAIL — `test_admin_passa...` falha (sem `Gate::before`, a ability sem policy nega) e `test_nome_cru...` falha (com o flag ainda ON, o `before` do spatie aprova o nome cru).

- [ ] **Step 3: Desligar o flag no config**

Em `config/permission.php`, alterar a linha `register_permission_check_method`:

```php
    'register_permission_check_method' => false,
```

- [ ] **Step 4: Adicionar o `Gate::before` do admin**

Em `app/Providers/AppServiceProvider.php`, adicionar os imports e a linha no `boot()`.

Imports (junto aos demais `use`):

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;
```

No fim de `boot()`:

```php
        // Portão do admin: administrador passa em qualquer ability; os demais caem nas policies.
        // (register_permission_check_method está OFF, então este é o único Gate::before do sistema.)
        Gate::before(fn (User $usuario) => $usuario->hasRole('administrador') ? true : null);
```

- [ ] **Step 5: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=GateFundacaoTest`
Expected: PASS (3 testes).

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint config app/Providers tests/Feature/Autorizacao
git add config/permission.php app/Providers/AppServiceProvider.php tests/Feature/Autorizacao/GateFundacaoTest.php
git commit -m "feat(capacidades): Gate::before do admin + desliga register_permission_check_method"
```

---

### Task 3: Vínculo editorial `departamento_usuario`

**Files:**
- Create: `database/migrations/2026_07_11_000001_create_departamento_usuario_table.php`
- Modify: `app/Models/User.php` (relação `departamentos()`)
- Test: `tests/Feature/Autorizacao/DepartamentoUsuarioTest.php`

**Interfaces:**
- Produces: tabela `departamento_usuario` (`user_id`, `departamento_id`, únicos) e `User::departamentos(): BelongsToMany` — **fonte única** do "departamento do usuário". Consumido pela Task 4 (trait + `EventoPolicy::criar`).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/DepartamentoUsuarioTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartamentoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_pivot_nasce_vazia_e_relaciona_usuario_a_departamentos(): void
    {
        $this->assertSame(0, DB::table('departamento_usuario')->count()); // nasce vazia

        $user = User::factory()->create();
        $ded = Departamento::create(['sigla' => 'DED', 'nome' => 'Estudos Doutrinários', 'slug' => 'ded']);
        $depro = Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        $user->departamentos()->attach([$ded->id, $depro->id]);

        $this->assertSame(2, $user->departamentos()->count());
        $this->assertTrue($user->departamentos()->where('departamentos.id', $ded->id)->exists());

        $user->departamentos()->detach($ded->id);
        $this->assertSame(1, $user->departamentos()->count());
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=DepartamentoUsuarioTest`
Expected: FAIL (`Base table or view not found: ... departamento_usuario` / `Call to undefined method ...::departamentos()`).

- [ ] **Step 3: Criar a migration (incremental, nasce vazia)**

`database/migrations/2026_07_11_000001_create_departamento_usuario_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['user_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_usuario');
    }
};
```

- [ ] **Step 4: Adicionar `User::departamentos()`**

Em `app/Models/User.php`, adicionar o método junto às demais relações (ao lado de `cargos()`; `BelongsToMany` já está importado):

```php
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_usuario');
    }
```

- [ ] **Step 5: Rodar a migration no dev (incremental) e o teste**

Run: `docker compose exec -T app php artisan migrate`
Expected: roda `2026_07_11_000001_create_departamento_usuario_table` (`DONE`).

Run: `docker compose exec -T app php artisan test --filter=DepartamentoUsuarioTest`
Expected: PASS.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint database/migrations app/Models/User.php tests/Feature/Autorizacao
git add database/migrations/2026_07_11_000001_create_departamento_usuario_table.php app/Models/User.php tests/Feature/Autorizacao/DepartamentoUsuarioTest.php
git commit -m "feat(capacidades): pivot departamento_usuario + User::departamentos()"
```

---

### Task 4: `EventoPolicy` de capacidade (contrato + trait + filtro real)

**Files:**
- Create: `app/Models/Contracts/TemDepartamento.php`
- Create: `app/Policies/Concerns/AutorizaPorDepartamento.php`
- Modify: `app/Models/Evento.php` (`implements ... TemDepartamento`)
- Modify: `app/Policies/EventoPolicy.php` (adicionar `ver`/`criar`/`editar`/`excluir`)
- Test: `tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php`

**Interfaces:**
- Consumes: `User::departamentos()` (Task 3), as permissions `evento.*` (Task 1), o `Gate::before` do admin (Task 2).
- Produces: `App\Models\Contracts\TemDepartamento` (interface com `departamentos(): BelongsToMany`); `App\Policies\Concerns\AutorizaPorDepartamento` com `protected objetoNoDepartamentoDoUsuario(User, TemDepartamento): bool` (fail-closed); `EventoPolicy::ver/criar/editar/excluir(User, [Evento])` checáveis via `Gate::forUser($u)->check('<acao>', $evento|Evento::class)`.

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Enums\VisibilidadeEvento;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoPolicyCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
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

    private function evento(string $slug, array $departamentoIds = []): Evento
    {
        $e = Evento::create([
            'titulo' => 'E', 'slug' => $slug, 'data_inicio' => '2026-08-15',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_RASCUNHO,
        ]);
        $e->departamentos()->sync($departamentoIds);

        return $e;
    }

    public function test_permite_ver_editar_excluir_quando_ha_intersecao(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.ver', 'evento.editar', 'evento.excluir'], [$ded->id]);
        $e = $this->evento('interseccao', [$ded->id]);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $e), $acao);
        }
    }

    public function test_nega_no_caso_disjunto(): void
    {
        $ded = $this->depto('DED');
        $depro = $this->depto('DEPRO');
        $u = $this->usuario(['evento.editar'], [$ded->id]);   // usuário no DED
        $e = $this->evento('disjunto', [$depro->id]);          // evento no DEPRO

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_nega_sem_vinculo_de_departamento(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], []);
        $e = $this->evento('sem-vinculo', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_objeto_sem_departamento_so_admin(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], [$ded->id]);
        $e = $this->evento('objeto-sem-depto', []);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
        $this->assertTrue(Gate::forUser($this->admin())->check('editar', $e));
    }

    public function test_nega_sem_a_permissao(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario([], [$ded->id]);                   // vínculo, mas sem a permissão
        $e = $this->evento('sem-permissao', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->check('editar', $e));
    }

    public function test_criar_invocado_com_a_classe(): void
    {
        $ded = $this->depto('DED');
        $comDepto = $this->usuario(['evento.criar'], [$ded->id]);
        $semDepto = $this->usuario(['evento.criar'], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', Evento::class));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', Evento::class));
    }

    public function test_nome_cru_nega_mas_ability_da_policy_permite(): void
    {
        $ded = $this->depto('DED');
        $u = $this->usuario(['evento.editar'], [$ded->id]);
        $e = $this->evento('nome-cru', [$ded->id]);

        $this->assertFalse(Gate::forUser($u)->allows('evento.editar', $e)); // nome cru NEGA
        $this->assertTrue(Gate::forUser($u)->check('editar', $e));          // ability de policy PERMITE
    }

    public function test_visitante_anonimo_negado(): void
    {
        $ded = $this->depto('DED');
        $e = $this->evento('anonimo', [$ded->id]);

        $this->assertFalse(Gate::forUser(null)->check('editar', $e));
    }

    public function test_admin_passa_em_todas_as_acoes(): void
    {
        $admin = $this->admin();
        $e = $this->evento('admin', []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $e), $acao);
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', Evento::class));
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=EventoPolicyCapacidadeTest`
Expected: FAIL (as abilities `editar`/`criar`/… ainda não existem na `EventoPolicy` ⇒ negadas; casos positivos falham).

- [ ] **Step 3: Criar o contrato `TemDepartamento`**

`app/Models/Contracts/TemDepartamento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Model que pertence a departamentos — habilita o escopo editorial das policies. */
interface TemDepartamento
{
    public function departamentos(): BelongsToMany;
}
```

- [ ] **Step 4: Criar o trait `AutorizaPorDepartamento`**

`app/Policies/Concerns/AutorizaPorDepartamento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies\Concerns;

use App\Models\Contracts\TemDepartamento;
use App\Models\User;

/**
 * Molde do escopo por departamento das policies de capacidade (fonte única).
 * Fail-closed: usuário sem departamento OU objeto sem departamento ⇒ false.
 */
trait AutorizaPorDepartamento
{
    protected function objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool
    {
        $idsUsuario = $user->departamentos()->pluck('departamentos.id')->all();

        if ($idsUsuario === []) {
            return false;
        }

        return $objeto->departamentos()
            ->whereIn('departamentos.id', $idsUsuario)
            ->exists();
    }
}
```

- [ ] **Step 5: `Evento implements TemDepartamento`**

Em `app/Models/Evento.php`: adicionar o import e o contrato na declaração da classe (a relação `departamentos()` já existe).

Import (junto aos demais `use`):

```php
use App\Models\Contracts\TemDepartamento;
```

Declaração da classe:

```php
class Evento extends Model implements HasMedia, TemDepartamento
```

- [ ] **Step 6: Adicionar as abilities de capacidade à `EventoPolicy`**

Substituir o conteúdo de `app/Policies/EventoPolicy.php` por (mantém `view`/`viewAny`, adiciona as 4 capacidades):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Policy de Evento nos DOIS eixos:
 * - VISIBILIDADE (quem vê o publicado): view/viewAny, delegadas a podeSerVistoPor / scopeVisiveisPara.
 *   $user é null-safe (visitante anônimo passa por Gate::forUser(null)).
 * - CAPACIDADE (quem edita): ver/criar/editar/excluir — permissão (hasPermissionTo, NUNCA can()) +
 *   escopo de departamento. User NÃO-nulável: o Gate pula o método p/ visitante ⇒ deny limpo.
 * O admin nunca chega às capacidades (passa antes no Gate::before). Filament não usa strict
 * authorization, então a policy parcial de visibilidade segue segura no /admin.
 */
class EventoPolicy
{
    use AutorizaPorDepartamento;

    public function view(?User $user, Evento $evento): bool
    {
        return $evento->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
    }

    public function ver(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.ver') && $this->objetoNoDepartamentoDoUsuario($user, $evento);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('evento.criar') && $user->departamentos()->exists();
    }

    public function editar(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.editar') && $this->objetoNoDepartamentoDoUsuario($user, $evento);
    }

    public function excluir(User $user, Evento $evento): bool
    {
        return $user->hasPermissionTo('evento.excluir') && $this->objetoNoDepartamentoDoUsuario($user, $evento);
    }
}
```

- [ ] **Step 7: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=EventoPolicyCapacidadeTest`
Expected: PASS (9 testes).

- [ ] **Step 8: Rodar a suíte de visibilidade (não-regressão do eixo intocado)**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeEventoAcessoTest`
Expected: PASS (o `view`/`viewAny` de visibilidade seguem intactos).

- [ ] **Step 9: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models app/Policies tests/Feature/Autorizacao
git add app/Models/Contracts/TemDepartamento.php app/Policies/Concerns/AutorizaPorDepartamento.php app/Models/Evento.php app/Policies/EventoPolicy.php tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php
git commit -m "feat(capacidades): EventoPolicy com filtro de departamento (contrato + trait)"
```

---

### Task 5: Policies fail-closed — `Palestra` / `Post` / `AgendaDia`

**Files:**
- Create: `app/Policies/PalestraPolicy.php`
- Create: `app/Policies/PostPolicy.php`
- Create: `app/Policies/AgendaDiaPolicy.php`
- Test: `tests/Feature/Autorizacao/PoliciesFailClosedTest.php`

**Interfaces:**
- Consumes: o `Gate::before` do admin (Task 2), as permissions (Task 1).
- Produces: `PalestraPolicy`/`PostPolicy`/`AgendaDiaPolicy` com `ver/criar/editar/excluir` que **retornam `false`** para não-admin (auto-descobertas por convenção). Sem o trait `AutorizaPorDepartamento` (os models não são `TemDepartamento` — passá-los daria TypeError).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/PoliciesFailClosedTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Palestra;
use App\Models\Post;
use App\Models\User;
use App\Policies\AgendaDiaPolicy;
use App\Policies\PalestraPolicy;
use App\Policies\PostPolicy;
use Database\Seeders\CapacidadesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PoliciesFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
    }

    /** @return array<int, array{class-string, string}> */
    public static function recursos(): array
    {
        return [
            [Post::class, 'post'],
            [Palestra::class, 'palestra'],
            [AgendaDia::class, 'agenda'],
        ];
    }

    public function test_policies_sao_resolvidas_por_auto_discovery(): void
    {
        // Falha-primeiro genuíno: sem as classes de policy, getPolicyFor devolve null.
        $this->assertInstanceOf(PostPolicy::class, Gate::getPolicyFor(Post::class));
        $this->assertInstanceOf(PalestraPolicy::class, Gate::getPolicyFor(Palestra::class));
        $this->assertInstanceOf(AgendaDiaPolicy::class, Gate::getPolicyFor(AgendaDia::class));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('recursos')]
    public function test_nao_admin_negado_mesmo_com_a_permissao(string $model, string $recurso): void
    {
        $u = User::factory()->create();
        foreach (['ver', 'criar', 'editar', 'excluir'] as $acao) {
            $u->givePermissionTo("{$recurso}.{$acao}");
        }
        $obj = $model::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('ver', $obj), "{$recurso}.ver");
        $this->assertFalse(Gate::forUser($u)->check('editar', $obj), "{$recurso}.editar");
        $this->assertFalse(Gate::forUser($u)->check('excluir', $obj), "{$recurso}.excluir");
        $this->assertFalse(Gate::forUser($u)->check('criar', $model), "{$recurso}.criar");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('recursos')]
    public function test_admin_passa(string $model, string $recurso): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $obj = $model::factory()->create();

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $obj), "{$recurso}.{$acao}");
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', $model), "{$recurso}.criar");
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PoliciesFailClosedTest`
Expected: FAIL — `test_policies_sao_resolvidas_por_auto_discovery` falha porque `Gate::getPolicyFor(...)` devolve `null` (as classes de policy ainda não existem). Este é o teste que ancora o fail-primeiro: sem as policies, o *comportamento* de negar o não-admin já ocorreria por ausência de ability, mas a **intenção explícita e testada** de fail-closed é o que estas classes entregam (e o ponto de partida da evolução na Fase B).

- [ ] **Step 3: Criar `PostPolicy`**

`app/Policies/PostPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Fail-closed: Post ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (Post não é TemDepartamento).
 */
class PostPolicy
{
    public function ver(User $user, Post $post): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, Post $post): bool
    {
        return false;
    }

    public function excluir(User $user, Post $post): bool
    {
        return false;
    }
}
```

- [ ] **Step 4: Criar `PalestraPolicy`**

`app/Policies/PalestraPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\Palestra;
use App\Models\User;

/**
 * Fail-closed: Palestra ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (Palestra não é TemDepartamento).
 */
class PalestraPolicy
{
    public function ver(User $user, Palestra $palestra): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, Palestra $palestra): bool
    {
        return false;
    }

    public function excluir(User $user, Palestra $palestra): bool
    {
        return false;
    }
}
```

- [ ] **Step 5: Criar `AgendaDiaPolicy`**

`app/Policies/AgendaDiaPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies;

use App\Models\AgendaDia;
use App\Models\User;

/**
 * Fail-closed: AgendaDia ainda não tem departamento (Fase B). Nega toda capacidade a não-admin;
 * o admin passa antes no Gate::before. Não usa AutorizaPorDepartamento (AgendaDia não é TemDepartamento).
 */
class AgendaDiaPolicy
{
    public function ver(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }

    public function criar(User $user): bool
    {
        return false;
    }

    public function editar(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }

    public function excluir(User $user, AgendaDia $agendaDia): bool
    {
        return false;
    }
}
```

- [ ] **Step 6: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=PoliciesFailClosedTest`
Expected: PASS (6 casos: 3 recursos × 2 métodos de teste).

- [ ] **Step 7: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Policies tests/Feature/Autorizacao
git add app/Policies/PostPolicy.php app/Policies/PalestraPolicy.php app/Policies/AgendaDiaPolicy.php tests/Feature/Autorizacao/PoliciesFailClosedTest.php
git commit -m "feat(capacidades): policies fail-closed para Palestra/Post/AgendaDia"
```

---

### Task 6: Verificação de não-regressão do `/admin` + suíte completa

**Files:** nenhuma alteração de código — é o gate de fechamento da fase.

**Interfaces:**
- Consumes: todas as tasks anteriores.
- Produces: prova de que o `/admin` não regrediu (suíte de resource-tests) e de que a fase não quebrou nada (suíte inteira verde, Pint limpo).

- [ ] **Step 1: Resource-tests do painel (a guarda de regressão do `/admin`, §9.7 do SPEC)**

Run: `docker compose exec -T app php artisan test --filter="EventoResourceTest|PostResourceTest|PalestraResourceTest|AgendaDiaResourceTest"`
Expected: PASS — o admin cria/edita/lista os quatro recursos no painel exatamente como antes (o Filament não consome as abilities pt-BR; adicioná-las é inerte para o painel).

- [ ] **Step 2: Suíte inteira no container**

Run: `docker compose exec -T app php artisan test`
Expected: PASS — o total anterior **mais** os testes novos desta fase (Tasks 1–5). Zero falhas. (Ver a memória `flaky-importadorblog-gd-cap-imagem`: 2 testes de cap de imagem do blog podem falhar sob carga; se isolarem/repetirem verde, não é regressão desta fase.)

- [ ] **Step 3: Pint (o CI roda `pint --test` antes dos testes)**

Run: `docker compose exec -T app ./vendor/bin/pint --test`
Expected: PASS (nenhum arquivo com drift de estilo).

- [ ] **Step 4: Conferência de fundação no dev (opcional, leitura)**

Run: `docker compose exec -T app php artisan tinker --execute="echo config('permission.register_permission_check_method') === false ? 'flag OFF ok' : 'FLAG AINDA ON';"`
Expected: `flag OFF ok`.

- [ ] **Step 5: Push e PR**

```bash
git push -u origin fase-a-modelo-capacidades
```

Abrir PR contra `main` (título: `feat(capacidades): Fase A — modelo de capacidades`), corpo resumindo o SPEC e apontando que o `/admin` fica intocado (provado pelos resource-tests) e que as permissions nascem sem atribuição a papéis (matriz = Fase C). Aguardar o CI verde no commit final antes do merge.

---

## Notas de execução (para o worker)

- **Ordem obrigatória:** Task 1 → 2 → 3 → 4 → 5 → 6. A Task 4 depende de 1 (permissions), 2 (Gate::before) e 3 (`User::departamentos()`).
- **Auto-discovery de policies:** o projeto **não** tem `AuthServiceProvider`; `App\Models\X` mapeia para `App\Policies\XPolicy` automaticamente (Laravel 11+). Nenhum registro manual.
- **`hasPermissionTo` vs `can`:** dentro das policies use **sempre** `hasPermissionTo` — com o flag OFF, `can('recurso.acao')` não resolve mais o nome de permissão. Isto é a decisão 3.0; não “conserte” trocando por `can`.
- **`criar` é objectless:** sempre invocado com a **classe** (`check('criar', Evento::class)`), nunca com instância.
- **Nada de destrutivo no banco de dev:** só `php artisan migrate` incremental e `db:seed --class=CapacidadesSeeder`.
