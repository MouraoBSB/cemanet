# Camada 4 · Fatia 0 — Integridade de papéis no cadastro de usuário

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Impedir, **server-side no save**, duas combinações inválidas no cadastro de usuário — setor com papel abaixo de Trabalhador (R1) e cargo com papel abaixo de Diretor (R2) — e apagar os 4 usuários sem papel do dev (lixo de teste).

**Architecture:** Uma peça pura + agnóstica de página — `App\Support\Usuarios\IntegridadePapel` (`violacoes()` tabela-verdade + `assegurar()` que lê o estado real sincronizado por query fresca e lança `ValidationException`) — chamada **como 1ª linha** de `CreateUser::afterCreate` e `EditUser::afterSave`. Como esses ganchos rodam **depois** do `saveRelationships`, a garantia exige a transação do Filament **ligada nas duas páginas** (`$hasDatabaseTransactions = true`); sem ela o rollback é no-op e a trava vaza. Zero migration, zero schema novo.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-activitylog · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-17-camada-4-fatia-0-integridade-papeis.md`](../specs/2026-07-17-camada-4-fatia-0-integridade-papeis.md) (aprovado após o bloqueador O1; dois passes registrados no §13).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-4-fatia-0-integridade-papeis` a partir de `origin/main` (= `cfe3873`). **Nunca** trabalhar direto na `main`. O PR leva código **e** os commits de docs (SPEC + passe + este plano) juntos, como fizeram a Fase C e a Camada 1.
- **O invariante DURO é "nada persiste".** O gate do merge é `assertDatabaseMissing`/pivô intacto pós-abort (I1/I2/I3/I4/I5). O `assertHasFormErrors(['roles'])` é **secundário** (UX): se a chave errar, a mensagem some do campo, mas o dado segue **não gravado** (segurança mantida). Testar os **dois**; não confundir qual trava o merge. **Nos testes de "nada persiste", asserte o gate duro PRIMEIRO e SEM encadear** ao `call()`; só **depois** o `assertHasFormErrors`. Se encadeado, um erro na chave `data.roles` faria o `assertHasFormErrors` estourar antes e **mascarar** o gate duro — e `data.roles → ['roles']` é o único elo não reverificado pelo passe externo.
- **A flag `$hasDatabaseTransactions = true` é load-bearing (I0).** Ligada **só** nas duas páginas — **nunca** no `AdminPanelProvider` (afetaria Eventos/Palestras/Agenda/Matriz). Sem ela a trava vaza silenciosamente ([[filament-transacao-opt-in-off]]).
- **Ler nível por QUERY fresca:** `$registro->roles()->max('nivel')` — **nunca** `nivelMaximo()` (lê a coleção cacheada, que no `afterSave` é a de antes do sync).
- **Reuso obrigatório (cobertura):** a trava cobre **só** o `UserResource`. Qualquer superfície futura que grave papel/setor/cargo (ex.: importador, uma tela não-admin da Camada 4) **terá de chamar** `IntegridadePapel::assegurar` — não herda a trava. Ciência, não tarefa desta fatia.
- **Zero migrations, zero schema.** Nada de `Schema::*`. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo — o dev tem 152 usuários + 123 AgendaDia + 127 Palestras + 45 Posts + 59 Palestrantes + mídia importados. A conexão `legado` é **read-only**.
- **Aceite:** suíte verde (**857 + novos**) e **nenhuma asserção de teste existente muda de cor** (§3.7 do spec — nenhum teste existente monta setor/cargo com papel baixo).
- **Comandos:** `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). Pint por task: `docker compose exec -T app ./vendor/bin/pint` **antes** de cada commit (o CI roda `pint --test` antes dos testes). Depois de editar PHP, `docker compose restart app worker` (OPcache `validate_timestamps=0`) antes de exercitar no navegador.
- **Autoria** em arquivo novo relevante: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17`.
- **Transação aninhada nos testes:** `RefreshDatabase` já abre uma transação por teste; a transação da página vira **savepoint** (SQLite e MySQL suportam) — o `rollBack` da trava desfaz até o savepoint, e `assertDatabaseMissing` enxerga o estado revertido. É o que torna o teste de "nada persiste" válido.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-4-fatia-0-integridade-papeis origin/main
git log --oneline -1
```

Esperado: HEAD em `cfe3873` (merge do PR #34 da Camada 1). Os commits de docs (SPEC + passe + este plano) entram junto ao longo da fatia; o PR leva código **e** docs.

---

### Task 1: `IntegridadePapel` — o validador puro + o assegurador

**Files:**
- Create: `app/Support/Usuarios/IntegridadePapel.php`
- Test: `tests/Unit/Usuarios/IntegridadePapelTest.php` (tabela-verdade, pura)
- Test: `tests/Feature/Usuarios/IntegridadePapelAsseguraTest.php` (assegurar com models reais)

**Interfaces:**
- Consumes: `GlossarioUsuarios::PAPEIS` (níveis), `App\Models\User`.
- Produces:
  - `IntegridadePapel::violacoes(int $nivel, bool $temSetor, bool $temCargo): array` — `list<string>` de mensagens pt-BR (vazio = íntegro). **Pura.**
  - `IntegridadePapel::assegurar(User $registro): void` — lê o estado real por query fresca; lança `Illuminate\Validation\ValidationException` (chave `data.roles`) se violar R1/R2.

**Contexto:** níveis vêm do glossário, sem número mágico — `NIVEL_MIN_SETOR = PAPEIS['trabalhador']` (20), `NIVEL_MIN_CARGO = PAPEIS['diretor']` (30) ([GlossarioUsuarios.php:10-15](../../../app/Importacao/GlossarioUsuarios.php#L10-L15)). `assegurar` usa `roles()->max('nivel')`/`setores()->exists()`/`cargos()->exists()` (queries frescas) — **não** `nivelMaximo()`, que leria a coleção cacheada ([User.php:92-96](../../../app/Models/User.php#L92-L96)). A chave `data.roles` é o statePath `data` + campo `roles`; o assert usa `assertHasFormErrors(['roles'])` (o Filament prefixa `data.` sozinho — §8.2 do spec).

- [ ] **Passo 1: Escrever o teste unitário que falha**

Criar `tests/Unit/Usuarios/IntegridadePapelTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Unit\Usuarios;

use App\Support\Usuarios\IntegridadePapel;
use PHPUnit\Framework\TestCase;

class IntegridadePapelTest extends TestCase
{
    // Níveis: sem-papel 0, frequentador 10, trabalhador 20, diretor 30, admin 100.

    public function test_sem_setor_e_sem_cargo_e_integro_em_qualquer_nivel(): void
    {
        foreach ([0, 10, 20, 30, 100] as $nivel) {
            $this->assertSame([], IntegridadePapel::violacoes($nivel, false, false), "nível {$nivel}");
        }
    }

    public function test_r1_setor_exige_trabalhador_ou_acima(): void
    {
        $this->assertCount(1, IntegridadePapel::violacoes(0, true, false));   // sem-papel + setor
        $this->assertCount(1, IntegridadePapel::violacoes(10, true, false));  // frequentador + setor
        $this->assertSame([], IntegridadePapel::violacoes(20, true, false));  // trabalhador + setor: ok
        $this->assertSame([], IntegridadePapel::violacoes(30, true, false));  // diretor + setor: ok
    }

    public function test_r2_cargo_exige_diretor(): void
    {
        $this->assertCount(1, IntegridadePapel::violacoes(0, false, true));   // sem-papel + cargo
        $this->assertCount(1, IntegridadePapel::violacoes(10, false, true));  // frequentador + cargo
        $this->assertCount(1, IntegridadePapel::violacoes(20, false, true));  // trabalhador + cargo
        $this->assertSame([], IntegridadePapel::violacoes(30, false, true));  // diretor + cargo: ok
    }

    public function test_frequentador_com_setor_e_cargo_acumula_duas_violacoes(): void
    {
        $this->assertCount(2, IntegridadePapel::violacoes(10, true, true));
    }

    public function test_admin_passa_com_setor_e_cargo(): void
    {
        $this->assertSame([], IntegridadePapel::violacoes(100, true, true));
    }

    public function test_mensagens_sao_pt_br_e_orientam(): void
    {
        $r1 = IntegridadePapel::violacoes(10, true, false)[0];
        $r2 = IntegridadePapel::violacoes(20, false, true)[0];
        $this->assertStringContainsString('Trabalhador', $r1);
        $this->assertStringContainsString('Diretor', $r2);
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=IntegridadePapelTest
```

Esperado: **FAIL** — `Class "App\Support\Usuarios\IntegridadePapel" not found`.

- [ ] **Passo 3: Criar a classe**

`app/Support/Usuarios/IntegridadePapel.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Support\Usuarios;

use App\Importacao\GlossarioUsuarios;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Trava de integridade papel × estrutura no cadastro de usuário.
 * R1: ter setor exige papel >= Trabalhador. R2: ter cargo exige papel >= Diretor.
 *
 * A GARANTIA é server-side: assegurar() lê o estado REAL gravado (pós-sync) e aborta o save. Vale
 * nos dois sentidos (rebaixar papel × adicionar setor/cargo), porque avalia o estado final combinado.
 */
final class IntegridadePapel
{
    private const NIVEL_MIN_SETOR = GlossarioUsuarios::PAPEIS['trabalhador']; // 20
    private const NIVEL_MIN_CARGO = GlossarioUsuarios::PAPEIS['diretor'];     // 30

    /** @return list<string> mensagens de violação (vazio = íntegro). Pura — testável sem banco. */
    public static function violacoes(int $nivel, bool $temSetor, bool $temCargo): array
    {
        $violacoes = [];

        if ($temSetor && $nivel < self::NIVEL_MIN_SETOR) {
            $violacoes[] = 'Um usuário com setor precisa ter papel Trabalhador ou acima. '
                .'Remova os setores ou eleve o papel.';
        }

        if ($temCargo && $nivel < self::NIVEL_MIN_CARGO) {
            $violacoes[] = 'Um usuário com cargo precisa ter papel Diretor. '
                .'Remova os cargos ou eleve o papel.';
        }

        return $violacoes;
    }

    /**
     * Lê o estado REAL sincronizado (queries frescas — nunca a coleção cacheada de nivelMaximo,
     * que no afterSave é a de antes do sync) e aborta o save se ferir R1/R2.
     */
    public static function assegurar(User $registro): void
    {
        $violacoes = self::violacoes(
            (int) $registro->roles()->max('nivel'),
            $registro->setores()->exists(),
            $registro->cargos()->exists(),
        );

        if ($violacoes !== []) {
            // Repropagada pelo catch(Throwable) do Filament => rollback do save (com a transação
            // ligada, §8.3 do spec) + erro no campo. Chave completa 'data.roles' (statePath + campo).
            throw ValidationException::withMessages(['data.roles' => $violacoes]);
        }
    }
}
```

- [ ] **Passo 4: Rodar o unitário e ver passar**

```bash
docker compose exec -T app php artisan test --filter=IntegridadePapelTest
```

Esperado: **PASS** (6 testes).

- [ ] **Passo 5: Escrever o teste de `assegurar()` (models reais) que falha**

Criar `tests/Feature/Usuarios/IntegridadePapelAsseguraTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use App\Support\Usuarios\IntegridadePapel;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IntegridadePapelAsseguraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
    }

    private function medium(): Setor
    {
        return Setor::where('slug', 'medium')->firstOrFail();
    }

    private function cargoDiretorDed(): Cargo
    {
        return Cargo::where('nome', 'Diretor do DED')->firstOrFail();
    }

    public function test_assegurar_lanca_para_frequentador_com_setor(): void
    {
        $u = User::factory()->create();
        $u->assignRole('frequentador');
        $u->setores()->attach($this->medium()->id);

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }

    public function test_assegurar_lanca_para_trabalhador_com_cargo(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->cargos()->attach($this->cargoDiretorDed()->id);

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }

    public function test_assegurar_e_silencioso_para_trabalhador_com_setor(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id);

        IntegridadePapel::assegurar($u);
        $this->assertTrue(true); // não lançou
    }

    public function test_assegurar_e_silencioso_para_diretor_com_cargo(): void
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $u->cargos()->attach($this->cargoDiretorDed()->id);

        IntegridadePapel::assegurar($u);
        $this->assertTrue(true);
    }

    public function test_assegurar_e_silencioso_para_sem_papel_sem_estrutura(): void
    {
        // Os 4 sem-papel do dev não têm setor/cargo => não violam R1/R2.
        IntegridadePapel::assegurar(User::factory()->create());
        $this->assertTrue(true);
    }

    public function test_assegurar_le_o_nivel_por_query_fresca_nao_o_cache(): void
    {
        // Papel diretor em memória (cacheado), mas rebaixado no banco para frequentador: assegurar
        // deve enxergar o nível FRESCO (10) e lançar por causa do setor.
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $u->setores()->attach($this->medium()->id);
        $u->load('roles'); // aquece a coleção cacheada com 'diretor'

        $u->syncRoles(['frequentador']); // muda no banco; a coleção carregada segue 'diretor'

        $this->expectException(ValidationException::class);
        IntegridadePapel::assegurar($u);
    }
}
```

- [ ] **Passo 6: Rodar e ver passar** (a classe já existe do Passo 3)

```bash
docker compose exec -T app php artisan test --filter=IntegridadePapelAsseguraTest
```

Esperado: **PASS** (6 testes). Se `test_assegurar_le_o_nivel_por_query_fresca_nao_o_cache` falhar, a implementação está usando `nivelMaximo()`/coleção em vez de `roles()->max('nivel')`.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Support/Usuarios/IntegridadePapel.php tests/Unit/Usuarios/IntegridadePapelTest.php tests/Feature/Usuarios/IntegridadePapelAsseguraTest.php
git commit -m "feat(camada-4): IntegridadePapel — validador puro R1/R2 + assegurador por query fresca"
```

---

### Task 2: Ligar a trava nas páginas (flag + gancho) e provar que morde

**Files:**
- Modify: `app/Filament/Resources/Users/Pages/CreateUser.php` (flag + `assegurar` no topo do `afterCreate`)
- Modify: `app/Filament/Resources/Users/Pages/EditUser.php` (flag + `assegurar` no topo do `afterSave`)
- Test: `tests/Feature/Usuarios/IntegridadePapelCadastroTest.php`

**Interfaces:**
- Consumes: `IntegridadePapel::assegurar` (Task 1).
- Produces: as duas páginas passam a **abortar** o save (rollback real) quando o estado gravado fere R1/R2.

**Contexto:** os ganchos rodam **depois** do `saveRelationships`, dentro do `try` transacional do Filament — mas a transação é **opt-in e default OFF** ([[filament-transacao-opt-in-off]]); por isso a flag `protected ?bool $hasDatabaseTransactions = true;` é **peça obrigatória**, ligada só nestas páginas. `assegurar` entra como **1ª linha**, **acrescentada acima** da auditoria da Fase D — **não** substituir o método (senão somem `registrarPapelUsuario`/`registrarDepartamentosUsuario`). Molde do teste de página: [AuditoriaUserResourceTest](../../../tests/Feature/Autorizacao/AuditoriaUserResourceTest.php) (`Livewire::test(CreateUser/EditUser)` + `EstruturaCemaSeeder` + `actingAsAdmin` + `setCurrentPanel('admin')`).

- [ ] **Passo 1: Escrever o teste de página que falha**

Criar `tests/Feature/Usuarios/IntegridadePapelCadastroTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Usuarios;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Cargo;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegridadePapelCadastroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->actingAsAdmin();
        Filament::setCurrentPanel(Filament::getPanel('admin')); // porta = admin
    }

    private function papel(string $slug): Role
    {
        return Role::findByName($slug, 'web');
    }

    private function medium(): Setor
    {
        return Setor::where('slug', 'medium')->firstOrFail();
    }

    private function cargo(): Cargo
    {
        return Cargo::where('nome', 'Diretor do DED')->firstOrFail();
    }

    // --- I0: a transação está ligada nas duas páginas (guardrail do bloqueador O1) ---

    public function test_ambas_paginas_ligam_a_transacao(): void
    {
        foreach ([CreateUser::class, EditUser::class] as $pagina) {
            $default = (new \ReflectionClass($pagina))->getDefaultProperties()['hasDatabaseTransactions'] ?? null;
            $this->assertTrue($default, "{$pagina} deve declarar \$hasDatabaseTransactions = true (senão a trava vaza).");
        }
    }

    // --- I1/I2 (create): reprova E nada persiste (o gate é o assertDatabaseMissing) ---

    public function test_create_frequentador_com_setor_e_abortado_e_nada_persiste(): void
    {
        $c = Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Inválido R1',
                'email' => 'r1@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('frequentador')->id],
                'setores' => [$this->medium()->id],
            ])
            ->call('create');

        // GATE DURO primeiro, SEM encadear: o inválido NÃO foi gravado (rollback real — depende de I0).
        // Se a chave 'data.roles' estiver errada, é o assertHasFormErrors (abaixo) que falha, não este.
        $this->assertDatabaseMissing('users', ['email' => 'r1@teste.com']);

        $c->assertHasFormErrors(['roles']); // SECUNDÁRIO (UX)
    }

    public function test_create_trabalhador_com_cargo_e_abortado_e_nada_persiste(): void
    {
        $c = Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Inválido R2',
                'email' => 'r2@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('trabalhador')->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'r2@teste.com']); // gate duro 1º, sem encadear

        $c->assertHasFormErrors(['roles']);
    }

    // --- I6/I7: casos válidos salvam (sem falso-positivo) ---

    public function test_create_admin_com_setor_e_cargo_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Admin Completo',
                'email' => 'admin2@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('administrador')->id],
                'setores' => [$this->medium()->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'admin2@teste.com']);
    }

    public function test_create_trabalhador_com_setor_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Trabalhador Médium',
                'email' => 'trab@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('trabalhador')->id],
                'setores' => [$this->medium()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $u = User::where('email', 'trab@teste.com')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->setores->contains($this->medium()));
    }

    public function test_create_diretor_com_cargo_salva(): void
    {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Diretor com Cargo',
                'email' => 'dir@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$this->papel('diretor')->id],
                'cargos' => [$this->cargo()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['email' => 'dir@teste.com']);
    }

    // --- I3: morde ao REBAIXAR; o pivô setor_usuario não fica stale ---

    public function test_edit_rebaixar_trabalhador_com_setor_e_abortado_pivo_intacto(): void
    {
        $u = User::factory()->create();
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id); // estado inicial VÁLIDO

        $c = Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['roles' => [$this->papel('frequentador')->id]]) // rebaixa, mantém o setor
            ->call('save');

        // GATE DURO primeiro, sem encadear: papel NÃO virou frequentador e o setor continua no pivô.
        $this->assertTrue($u->fresh()->hasRole('trabalhador'));
        $this->assertFalse($u->fresh()->hasRole('frequentador'));
        $this->assertDatabaseHas('setor_usuario', ['user_id' => $u->id, 'setor_id' => $this->medium()->id]);

        $c->assertHasFormErrors(['roles']);
    }

    // --- I4: morde ao ADICIONAR ---

    public function test_edit_adicionar_setor_a_frequentador_e_abortado(): void
    {
        $u = User::factory()->create();
        $u->assignRole('frequentador'); // sem setor/cargo (válido)

        $c = Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm(['setores' => [$this->medium()->id]]) // tenta ganhar setor sendo frequentador
            ->call('save');

        $this->assertDatabaseMissing('setor_usuario', ['user_id' => $u->id, 'setor_id' => $this->medium()->id]);

        $c->assertHasFormErrors(['roles']);
    }

    // --- I5: POST forjado (UI "desligada") não fura ---

    public function test_estado_forjado_com_ui_desligada_nao_fura(): void
    {
        // Seta o estado direto (como um POST forjado que ignora qualquer reação de UI).
        $c = Livewire::test(CreateUser::class)
            ->set('data.name', 'Forjado')
            ->set('data.email', 'forjado@teste.com')
            ->set('data.password', 'senha-super-forte-2026')
            ->set('data.roles', [$this->papel('frequentador')->id])
            ->set('data.setores', [$this->medium()->id])
            ->call('create');

        $this->assertDatabaseMissing('users', ['email' => 'forjado@teste.com']); // gate duro 1º, sem encadear

        $c->assertHasFormErrors(['roles']);
    }

    // --- I8: auditoria atômica — o abort não deixa log órfão (prova a conexão do activity_log) ---

    public function test_edit_abortado_nao_deixa_log_orfao(): void
    {
        $u = User::factory()->create(['name' => 'Original']);
        $u->assignRole('trabalhador');
        $u->setores()->attach($this->medium()->id);
        $logsAntes = DB::table('activity_log')->where('subject_id', $u->id)->count();

        Livewire::test(EditUser::class, ['record' => $u->getKey()])
            ->fillForm([
                'name' => 'Alterado',                       // dispara o auto-log (LogsActivity) do User
                'roles' => [$this->papel('frequentador')->id], // rebaixa com setor => R1 => abort
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        // O auto-log do 'name' rodou dentro da transação; o rollback o desfez. Se activity_log
        // estivesse em conexão separada, teria sobrado um log órfão aqui.
        $this->assertSame('Original', $u->fresh()->name);
        $this->assertSame($logsAntes, DB::table('activity_log')->where('subject_id', $u->id)->count());
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=IntegridadePapelCadastroTest
```

Esperado: **FAIL** — I0 falha (`hasDatabaseTransactions` é `null`) e I1/I2/I3/I4/I5/I8 falham (sem a trava o inválido persiste; sem a flag o rollback é no-op). I6/I7 (válidos) já passam.

- [ ] **Passo 3: Ligar a flag + o gancho no `CreateUser`**

Substituir `app/Filament/Resources/Users/Pages/CreateUser.php` por:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Usuarios\IntegridadePapel;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Liga a transação SÓ nesta página: sem ela o rollback de assegurar() é no-op e a trava vaza
    // (a transação do Filament é opt-in/default off). Não ligar no painel.
    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        // 1ª linha: aborta+reverte (dentro da transação) se o estado gravado ferir R1/R2.
        IntegridadePapel::assegurar($this->record);

        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, [], $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, [], $deptosDepois);
    }
}
```

- [ ] **Passo 4: Ligar a flag + o gancho no `EditUser`**

Substituir `app/Filament/Resources/Users/Pages/EditUser.php` por:

```php
<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Usuarios\IntegridadePapel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Liga a transação SÓ nesta página (ver CreateUser). No EDIT é ainda mais crítico: o sync das
    // relações roda dentro de getState(), antes de qualquer hook — só a transação reverte.
    protected ?bool $hasDatabaseTransactions = true;

    protected array $papelAntes = [];

    protected array $deptosAntes = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // B1: o saveRelationships roda dentro de getState() (antes de afterSave); capturar o "antes" AQUI,
        // por query fresca (->roles()/->departamentos()), antes do parent::save().
        $this->papelAntes = $this->record->roles()->pluck('name')->all();
        $this->deptosAntes = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    protected function afterSave(): void
    {
        // 1ª linha: aborta+reverte (dentro da transação) se o estado gravado ferir R1/R2.
        IntegridadePapel::assegurar($this->record);

        $papelDepois = $this->record->roles()->pluck('name')->all();
        $deptosDepois = $this->record->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();

        AuditoriaAutorizacao::registrarPapelUsuario($this->record, $this->papelAntes, $papelDepois);
        AuditoriaAutorizacao::registrarDepartamentosUsuario($this->record, $this->deptosAntes, $deptosDepois);
    }
}
```

- [ ] **Passo 5: Rodar e ver passar**

```bash
docker compose restart app worker
docker compose exec -T app php artisan test --filter=IntegridadePapelCadastroTest
```

Esperado: **PASS** (10 testes). Se `test_edit_abortado_nao_deixa_log_orfao` falhar com log sobrando, o `activity_log` está numa conexão separada da transação (checar `config/activitylog.php` / `ACTIVITY_LOGGER_DB_CONNECTION` no `.env`) — hoje usa a default, então deve passar.

- [ ] **Passo 6: Regressão dos testes existentes do UserResource (I-neutro)**

```bash
docker compose exec -T app php artisan test --filter="UsuarioResourceTest|AuditoriaUserResourceTest"
```

Esperado: **PASS**, sem editar nenhum teste existente (nenhum monta setor/cargo com papel baixo; ligar a flag só adiciona atomicidade a saves válidos).

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Resources/Users/Pages/CreateUser.php app/Filament/Resources/Users/Pages/EditUser.php tests/Feature/Usuarios/IntegridadePapelCadastroTest.php
git commit -m "feat(camada-4): trava de integridade papel×setor/cargo no cadastro (flag de transação + assegurar)"
```

---

### Task 3: Verificação no navegador + suíte cheia

**Files:** nenhum (verificação).

- [ ] **Passo 1: Suíte completa + Pint (o gate do merge)**

```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app php artisan test
```

Esperado: **857 + os novos** verdes; `pint --test` sem drift. Ciência [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga — se passam isolados/no CI, não é regressão desta fatia.

- [ ] **Passo 2: Exercitar no `/admin` de verdade (fechar a lacuna do "só teste")**

```bash
docker compose restart app worker
```

No navegador, logado como admin, em `/admin/users/create`:
1. Papel **Frequentador** + um **Setor** → salvar ⇒ **barra** com a mensagem "Um usuário com setor precisa ter papel Trabalhador ou acima…", e o usuário **não** aparece na listagem.
2. Papel **Trabalhador** + um **Cargo** → salvar ⇒ **barra** (mensagem de cargo/Diretor).
3. Papel **Trabalhador** + **Setor** → salva normal.
4. Editar um trabalhador que tem setor, rebaixar para **Frequentador** → **barra**; reabrir o registro ⇒ ainda **Trabalhador** com o setor.

Confirmar que casos válidos (diretor com cargo, admin com tudo) salvam sem atrito.

- [ ] **Passo 3: Commit da verificação (se algum ajuste de doc surgir; senão, nada a commitar)**

Sem código novo — este passo é o gate manual antes do merge. Nenhum commit se tudo passou.

---

### Task 4: Higiene do dev — apagar os 4 sem-papel (NÃO versionada, NÃO na suíte)

**Files:** nenhum (operação pontual no dev; decisão do dono, §6.6 do spec).

> ⚠️ Operação **manual e pontual** na conexão **padrão (dev)**. Não é comando `cema:*`, não entra em git, não entra na suíte (prod não tem usuário sem papel). 🚫 Nada de `migrate:*` destrutivo / `db:wipe` / factory destrutivo.

- [ ] **Passo 1: Reconferir a lista imediatamente antes (read-only)**

```bash
docker compose exec -T app php artisan tinker --execute="foreach (App\Models\User::doesntHave('roles')->get() as \$u) { echo \$u->id.' | '.\$u->name.' | '.\$u->email.' | setores='.\$u->setores()->count().' | cargos='.\$u->cargos()->count().PHP_EOL; }"
```

Esperado: **exatamente** os 4 fixtures (`debug@x.com`, `debug2@x.com`, `roma63@example.net`, `leila.becker@example.org`), todos com `setores=0 cargos=0`. ⚠️ Se aparecer um **5º** que não seja fixture de teste, **PARAR** e reavaliar com o dono.

- [ ] **Passo 2: Apagar só esses 4, por e-mail (idempotente)**

```bash
docker compose exec -T app php artisan tinker --execute="\$emails=['debug@x.com','debug2@x.com','roma63@example.net','leila.becker@example.org']; \$n=App\Models\User::doesntHave('roles')->whereIn('email',\$emails)->delete(); echo 'apagados: '.\$n.PHP_EOL; echo 'sem-papel restantes: '.App\Models\User::doesntHave('roles')->count().PHP_EOL;"
```

Esperado: `apagados: 4`, `sem-papel restantes: 0`. (O `whereIn('email', ...)` + `doesntHave('roles')` é um cinto: só apaga quem está na lista **e** continua sem papel.)

- [ ] **Passo 3: Confirmar a base coerente**

```bash
docker compose exec -T app php artisan tinker --execute="echo 'total='.App\Models\User::count().' sem-papel='.App\Models\User::doesntHave('roles')->count().PHP_EOL;"
```

Esperado: `total=148 sem-papel=0`. Nada a commitar (operação de dev).

---

## Self-Review (cobertura vs. spec)

- **R1/R2 puras** → Task 1 (unit, tabela-verdade). **assegurar (query fresca, throw)** → Task 1 (feature).
- **I0 (flag/transação)** → Task 2 (reflection) + o próprio "nada persiste". **I1/I2 create** → Task 2. **I3 rebaixar+pivô** → Task 2. **I4 adicionar** → Task 2. **I5 forjado** → Task 2. **I6 admin** → Task 2. **I7 caso feliz** → Task 2. **I8 auditoria atômica + conexão do activity_log** → Task 2.
- **I-neutro (857 verde, nada muda de cor)** → Task 2 Passo 6 + Task 3 Passo 1.
- **4 sem-papel = apagar** (decisão do dono) → Task 4 (dev-only, não versionada).
- **Reuso obrigatório em superfícies futuras** → Global Constraints (ciência, não tarefa).
- **Verificação real no navegador** → Task 3 Passo 2 (fecha a lacuna do "só teste").
