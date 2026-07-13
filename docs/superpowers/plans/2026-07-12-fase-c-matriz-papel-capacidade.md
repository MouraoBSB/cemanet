# Fase C — Matriz papel×capacidade + atribuição de vínculos — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ligar a autorização (inerte desde A/B): uma Página Filament atribui as 20 capacidades aos papéis `trabalhador`/`diretor` (matriz), e dois pontos de UI atribuem os departamentos ao usuário e ao conteúdo — sem tocar policy/trait/pivot.

**Architecture:** Uma constante de rótulos (`GlossarioCapacidades`) e a lista de papéis-coluna (`GlossarioUsuarios::PAPEIS_EDITAVEIS`) alimentam uma Página Filament nova (`MatrizCapacidades`), grade `Grid`+`Toggle` com estado `data.<papel>.<recurso>.<acao>` que persiste via `Role::syncPermissions` (1º escritor de `role_has_permissions`). Um `Select` de `departamentos` entra no `UserResource` (Peça 2) e nos 4 forms de conteúdo com `->required()` (Peça 3); o `required` obriga a atualizar os testes de resource existentes (O1). A autorização resultante (presidente, DECOM) é provada por teste, sem código de policy novo.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 (`Filament\Schemas\Schema`) · spatie/laravel-permission 6.25 (guard `web`, teams OFF, wildcard OFF) · MySQL 8 (dev via Docker; testes com `RefreshDatabase`). **0 migrations** (schema todo de A/B).

**Fonte da verdade:** [SPEC — Fase C](../specs/2026-07-12-fase-c-matriz-papel-capacidade.md) (aprovado no passe adversarial: F5=`required`, F10=sem `forget`, demais endossados).

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, comentários, mensagens, commits).
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12` no topo de todo PHP **novo** (após `<?php`, antes do `namespace`). Views Blade e migrations não levam (não há migration nesta fase).
- **Guard:** papéis e permissions sempre no guard **`web`**. Resolver `Role`/`Permission` no guard `web` (`findByName($slug, 'web')`).
- **Papéis FIXOS:** a matriz só liga/desliga capacidades — **nunca** cria/apaga papel. Usar **`Role::findByName(...)->syncPermissions(...)`**, **jamais** `Role::create`/`updateOrCreate` (zeraria a coluna custom `nivel`, default 0).
- **Salvar toca SÓ `trabalhador`/`diretor`:** `administrador` é onipotente via `Gate::before` (permission irrelevante), mas **`frequentador` NÃO é curto-circuitado** — atribuir-lhe permission concederia edição real. Iterar apenas `GlossarioUsuarios::PAPEIS_EDITAVEIS`.
- **Cache do spatie:** **NÃO** chamar `forgetCachedPermissions()` — `Role::syncPermissions` já limpa o cache (inclusive com array vazio). Decisão F10.
- **`->required()` no Select de conteúdo** (F5): quebra os testes de resource que criam/editam conteúdo sem departamento (O1) — atualizar **todos** eles, não só adicionar o método novo.
- **Molde da Página v5 ipsis litteris** (F1): método `salvar()` (não `save`); `content()` com `Form::make([EmbeddedSchema::make('form')])->id('form')->livewireSubmitHandler('salvar')->footer([Actions::make([Action::make('salvar')->submit('salvar')])])`; a Blade renderiza `{{ $this->content }}` (não `{{ $this->form }}`).
- **Namespaces v5** (F3): `Grid`/`Section`/`Form`/`EmbeddedSchema`/`Actions` em `Filament\Schemas\Components\*`; `Toggle`/`Select` em `Filament\Forms\Components\*`; `Schema` em `Filament\Schemas\Schema`; `Action` em `Filament\Actions\Action`.
- **Admin-only automático:** a Página em `app/Filament/Pages` é auto-descoberta e admin-only pelo portão do painel (`User::canAccessPanel` → `hasRole('administrador')`). **Não** adicionar `canAccess()`. Sem `navigationGroup` (status quo — F7).
- **Banco:** MySQL só por migrations incrementais. 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e seed destrutivo no dev (apagam 127 palestras, 45 posts etc.). Nesta fase não há migration; no dev só `restart` do container. Testes: `RefreshDatabase` (banco isolado).
- **Ferramentas no container:** `docker compose exec -T app php artisan ...` e `docker compose exec -T app ./vendor/bin/pint`. Editar PHP no dev exige `docker compose restart app worker` (OPcache `validate_timestamps=0`). Sem front/build (nada de npm/Vite).
- **Pint** limpo antes do push; suíte no container; **commits atômicos** pt-BR na branch **`fase-c-matriz-capacidade`** (criada a partir de `main`).

---

### Task 1: Fontes de dados — rótulos de capacidade + papéis-coluna

**Files:**
- Modify: `app/Support/Autorizacao/GlossarioCapacidades.php` (const `RECURSOS_ROTULOS`/`ACOES_ROTULOS` + `rotuloRecurso()`/`rotuloAcao()`)
- Modify: `app/Importacao/GlossarioUsuarios.php` (const `PAPEIS_EDITAVEIS`)
- Test: `tests/Feature/Autorizacao/GlossarioRotulosTest.php`

**Interfaces:**
- Produces: `GlossarioCapacidades::rotuloRecurso(string): string` e `::rotuloAcao(string): string` (rótulo legível com fallback `ucfirst`); `GlossarioUsuarios::PAPEIS_EDITAVEIS = ['trabalhador','diretor']`. Consumidos pela Página da matriz (Task 2).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Autorizacao/GlossarioRotulosTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Autorizacao;

use App\Importacao\GlossarioUsuarios;
use App\Support\Autorizacao\GlossarioCapacidades;
use Tests\TestCase;

class GlossarioRotulosTest extends TestCase
{
    public function test_rotulos_de_recurso_cobrem_os_casos_slug_diferente_do_model(): void
    {
        $this->assertSame('Agenda do Dia', GlossarioCapacidades::rotuloRecurso('agenda'));
        $this->assertSame('Palestrante', GlossarioCapacidades::rotuloRecurso('palestrante'));
        $this->assertSame('Evento', GlossarioCapacidades::rotuloRecurso('evento'));
        $this->assertSame('Xyz', GlossarioCapacidades::rotuloRecurso('xyz')); // fallback ucfirst
    }

    public function test_rotulos_de_acao(): void
    {
        $this->assertSame('Ver', GlossarioCapacidades::rotuloAcao('ver'));
        $this->assertSame('Editar', GlossarioCapacidades::rotuloAcao('editar'));
        $this->assertSame('Excluir', GlossarioCapacidades::rotuloAcao('excluir'));
    }

    public function test_papeis_editaveis_sao_trabalhador_e_diretor(): void
    {
        $this->assertSame(['trabalhador', 'diretor'], GlossarioUsuarios::PAPEIS_EDITAVEIS);
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=GlossarioRotulosTest`
Expected: FAIL (`Call to undefined method ...rotuloRecurso()` / `Undefined constant ...PAPEIS_EDITAVEIS`).

- [ ] **Step 3: Adicionar os rótulos ao `GlossarioCapacidades`**

Em `app/Support/Autorizacao/GlossarioCapacidades.php`, após a constante `ACOES` (linha 15) e mantendo `permissions()`, adicionar as constantes e os dois métodos:

```php
    /** Rótulos legíveis dos recursos (slug ≠ model em 'agenda' e 'palestrante'). */
    public const RECURSOS_ROTULOS = [
        'evento' => 'Evento',
        'palestra' => 'Palestra',
        'post' => 'Post',
        'agenda' => 'Agenda do Dia',
        'palestrante' => 'Palestrante',
    ];

    /** Rótulos legíveis das ações. */
    public const ACOES_ROTULOS = [
        'ver' => 'Ver',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
    ];

    public static function rotuloRecurso(string $recurso): string
    {
        return self::RECURSOS_ROTULOS[$recurso] ?? ucfirst($recurso);
    }

    public static function rotuloAcao(string $acao): string
    {
        return self::ACOES_ROTULOS[$acao] ?? ucfirst($acao);
    }
```

- [ ] **Step 4: Adicionar `PAPEIS_EDITAVEIS` ao `GlossarioUsuarios`**

Em `app/Importacao/GlossarioUsuarios.php`, logo após a constante `PAPEIS` (linha 15), adicionar:

```php
    /** Papéis que aparecem como colunas editáveis na matriz de capacidades (Fase C). */
    public const PAPEIS_EDITAVEIS = ['trabalhador', 'diretor'];
```

- [ ] **Step 5: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=GlossarioRotulosTest`
Expected: PASS.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Autorizacao app/Importacao tests/Feature/Autorizacao
git add app/Support/Autorizacao/GlossarioCapacidades.php app/Importacao/GlossarioUsuarios.php tests/Feature/Autorizacao/GlossarioRotulosTest.php
git commit -m "feat(capacidades): rótulos de recurso/ação + PAPEIS_EDITAVEIS (fontes da matriz)"
```

---

### Task 2: Página da matriz papel×capacidade (Peça 1) + teste E2E

**Files:**
- Create: `app/Filament/Pages/MatrizCapacidades.php`
- Create: `resources/views/filament/pages/matriz-capacidades.blade.php`
- Test: `tests/Feature/Filament/MatrizCapacidadesTest.php`

**Interfaces:**
- Consumes: `GlossarioCapacidades::RECURSOS`/`ACOES`/`rotuloRecurso()`/`rotuloAcao()` (Task 1), `GlossarioUsuarios::PAPEIS_EDITAVEIS` (Task 1), `Role::findByName`/`permissions`/`syncPermissions` (spatie), o molde `ConfiguracoesBlog`.
- Produces: rota `/admin/matriz-capacidades`; método público `salvar()` que escreve `role_has_permissions` só para `trabalhador`/`diretor`; estado `data.<papel>.<recurso>.<acao>` (bool). Consumido pela policy A/B via `hasPermissionTo` (não tocada).

- [ ] **Step 1: Escrever o teste que falha**

`tests/Feature/Filament/MatrizCapacidadesTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Filament;

use App\Filament\Pages\MatrizCapacidades;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatrizCapacidadesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // 4 papéis (web) + 8 departamentos + cargos
        $this->seed(CapacidadesSeeder::class); // as 20 permissions (web)
        $this->actingAsAdmin();
    }

    public function test_renderiza(): void
    {
        $this->get('/admin/matriz-capacidades')->assertOk();
    }

    public function test_salvar_atribui_e_remove_permissao_do_papel(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertTrue(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));

        // desmarca e salva de novo => syncPermissions([]) faz o detach
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => false])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertFalse(Role::findByName('diretor', 'web')->hasPermissionTo('palestra.editar'));
    }

    public function test_abre_com_pre_marca_do_estado_atual(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('post.criar');

        Livewire::test(MatrizCapacidades::class)
            ->assertFormSet([
                'diretor.post.criar' => true,
                'diretor.post.editar' => false,
            ]);
    }

    public function test_salvar_nao_toca_admin_nem_frequentador(): void
    {
        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true, 'trabalhador.post.criar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(0, Role::findByName('frequentador', 'web')->permissions()->count());
        $this->assertSame(0, Role::findByName('administrador', 'web')->permissions()->count());
    }

    public function test_salvar_concede_capacidade_que_a_policy_consome(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->first();

        Livewire::test(MatrizCapacidades::class)
            ->fillForm(['diretor.palestra.editar' => true])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $diretor = User::factory()->create();
        $diretor->assignRole('diretor');
        $diretor->departamentos()->sync([$decom->id]);

        $palestra = Palestra::factory()->create();
        $palestra->departamentos()->sync([$decom->id]);

        $this->assertTrue(Gate::forUser($diretor)->check('editar', $palestra));
    }
}
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MatrizCapacidadesTest`
Expected: FAIL (`Class "App\Filament\Pages\MatrizCapacidades" does not exist`).

- [ ] **Step 3: Criar a Página**

`app/Filament/Pages/MatrizCapacidades.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace App\Filament\Pages;

use App\Importacao\GlossarioUsuarios;
use App\Support\Autorizacao\GlossarioCapacidades;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

/**
 * Matriz papel×capacidade (Fase C): liga/desliga as 20 capacidades para os papéis
 * trabalhador e diretor. Único escritor de role_has_permissions. Admin-only pelo portão
 * do painel. syncPermissions já limpa o cache do spatie (não chamar forget).
 */
class MatrizCapacidades extends Page
{
    protected string $view = 'filament.pages.matriz-capacidades';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Matriz de capacidades';

    protected static ?string $title = 'Matriz de capacidades';

    protected static ?string $slug = 'matriz-capacidades';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $estado = [];

        foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
            $nomes = Role::findByName($papel, 'web')->permissions()->pluck('name')->all();

            foreach (GlossarioCapacidades::RECURSOS as $recurso) {
                foreach (GlossarioCapacidades::ACOES as $acao) {
                    $estado[$papel][$recurso][$acao] = in_array("{$recurso}.{$acao}", $nomes, true);
                }
            }
        }

        $this->form->fill($estado);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->secoesPorRecurso());
    }

    /** @return array<Component> uma Section por recurso; toggles = ação × papel. */
    private function secoesPorRecurso(): array
    {
        $secoes = [];

        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $toggles = [];

            foreach (GlossarioCapacidades::ACOES as $acao) {
                foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
                    $toggles[] = Toggle::make("{$papel}.{$recurso}.{$acao}")
                        ->label(GlossarioCapacidades::rotuloAcao($acao).' — '.ucfirst($papel))
                        ->inline(false);
                }
            }

            $secoes[] = Section::make(GlossarioCapacidades::rotuloRecurso($recurso))
                ->columns(count(GlossarioUsuarios::PAPEIS_EDITAVEIS))
                ->schema($toggles);
        }

        return $secoes;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('salvar')
                ->footer([
                    Actions::make([
                        Action::make('salvar')
                            ->label('Salvar')
                            ->submit('salvar'),
                    ]),
                ]),
        ]);
    }

    public function salvar(): void
    {
        $estado = $this->form->getState();

        foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
            $marcados = [];

            foreach (GlossarioCapacidades::RECURSOS as $recurso) {
                foreach (GlossarioCapacidades::ACOES as $acao) {
                    if (($estado[$papel][$recurso][$acao] ?? false) === true) {
                        $marcados[] = "{$recurso}.{$acao}";
                    }
                }
            }

            // findByName + syncPermissions (nunca recriar o papel: zeraria 'nivel').
            // syncPermissions já limpa o cache do spatie — não chamar forget (F10).
            Role::findByName($papel, 'web')->syncPermissions($marcados);
        }

        Notification::make()
            ->title('Matriz de capacidades salva com sucesso.')
            ->success()
            ->send();
    }
}
```

> Se `Heroicon::OutlinedShieldCheck` não existir na versão instalada, trocar por `Heroicon::OutlinedKey` (ambos são ícones do Heroicons v2). Verificar em `Filament\Support\Icons\Heroicon` se o teste de render reclamar do ícone.

- [ ] **Step 4: Criar a view**

`resources/views/filament/pages/matriz-capacidades.blade.php`:

```blade
<x-filament-panels::page>
    {{ $this->content }}
</x-filament-panels::page>
```

- [ ] **Step 5: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=MatrizCapacidadesTest`
Expected: PASS (5 métodos). Se `test_renderiza` falhar por ícone inexistente, aplicar a nota do Step 3 e repetir.

- [ ] **Step 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Pages tests/Feature/Filament
git add app/Filament/Pages/MatrizCapacidades.php resources/views/filament/pages/matriz-capacidades.blade.php tests/Feature/Filament/MatrizCapacidadesTest.php
git commit -m "feat(capacidades): Página da matriz papel×capacidade (grade + syncPermissions)"
```

---

### Task 3: Autorização resultante — casos presidente e DECOM (via papel)

**Files:**
- Test: `tests/Feature/Autorizacao/CapacidadeViaPapelTest.php`

**Interfaces:**
- Consumes: papéis + 8 departamentos (`EstruturaCemaSeeder`), 20 permissions (`CapacidadesSeeder`), `Role::givePermissionTo`/`syncPermissions`, as policies A/B (não tocadas), `AutorizaPorDepartamento` (interseção `whereIn`).
- Produces: nenhum código de app — cobre a **lacuna** de multi-departamento (caso DECOM) e o caminho **por papel** (não `givePermissionTo` direto no usuário, como faz A/B).

> **Nota TDD:** o mecanismo já existe (policies A/B + interseção). Estes testes provam **comportamento** (presidente/DECOM/via-papel) que hoje não tem cobertura — podem passar assim que escritos. É um teste de garantia da lacuna, não de código novo. Escrever, rodar, ver verde, commitar.

- [ ] **Step 1: Escrever o teste**

`tests/Feature/Autorizacao/CapacidadeViaPapelTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace Tests\Feature\Autorizacao;

use App\Importacao\GlossarioUsuarios;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapacidadeViaPapelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();   // papéis + 8 departamentos
        $this->seed(CapacidadesSeeder::class); // 20 permissions
    }

    private function diretorNos(array $siglas): User
    {
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $ids = Departamento::whereIn('sigla', $siglas)->pluck('id')->all();
        $u->departamentos()->sync($ids);

        return $u;
    }

    private function palestraNos(array $siglas): Palestra
    {
        $p = Palestra::factory()->create();
        $ids = Departamento::whereIn('sigla', $siglas)->pluck('id')->all();
        $p->departamentos()->sync($ids);

        return $p;
    }

    public function test_usuario_do_papel_ganha_e_perde_capacidade(): void
    {
        $diretor = $this->diretorNos(['DECOM']);
        $palestra = $this->palestraNos(['DECOM']);

        // sem permission no papel ⇒ negado
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz concede ao papel ⇒ permitido
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');
        $this->assertTrue(Gate::forUser($diretor->fresh())->check('editar', $palestra));

        // matriz revoga ⇒ negado
        Role::findByName('diretor', 'web')->syncPermissions([]);
        $this->assertFalse(Gate::forUser($diretor->fresh())->check('editar', $palestra));
    }

    public function test_presidente_diretor_com_8_deptos_edita_qualquer_departamento(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $presidente = $this->diretorNos(array_keys(GlossarioUsuarios::DEPARTAMENTOS)); // 8 vínculos

        foreach (['DED', 'DECOM', 'DEPRO'] as $sigla) {
            $palestra = $this->palestraNos([$sigla]);
            $this->assertTrue(Gate::forUser($presidente)->check('editar', $palestra), $sigla);
        }
    }

    public function test_decom_edita_palestra_com_dois_departamentos_por_intersecao(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $diretorDecom = $this->diretorNos(['DECOM']);

        // caso DECOM: palestra pertence a DOIS departamentos (DED + DECOM)
        $doisDeptos = $this->palestraNos(['DED', 'DECOM']);
        $this->assertTrue(Gate::forUser($diretorDecom)->check('editar', $doisDeptos));

        // disjunto: palestra só em DED ⇒ diretor do DECOM é negado
        $soDed = $this->palestraNos(['DED']);
        $this->assertFalse(Gate::forUser($diretorDecom)->check('editar', $soDed));
    }
}
```

- [ ] **Step 2: Rodar o teste**

Run: `docker compose exec -T app php artisan test --filter=CapacidadeViaPapelTest`
Expected: PASS (3 métodos). Se algum falhar, é bug real na cadeia papel→permissão→interseção — investigar antes de prosseguir (não "consertar" o teste).

- [ ] **Step 3: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Autorizacao
git add tests/Feature/Autorizacao/CapacidadeViaPapelTest.php
git commit -m "test(capacidades): autorização via papel — casos presidente e DECOM (interseção 2 deptos)"
```

---

### Task 4: Peça 2 — `Select` de departamentos no UserResource

**Files:**
- Modify: `app/Filament/Resources/Users/UserResource.php` (Select em 'Papel e estrutura', após cargos)
- Modify: `tests/Feature/Usuarios/UsuarioResourceTest.php` (método de regressão)

**Interfaces:**
- Consumes: `User::departamentos()` (belongsToMany `departamento_usuario`, Fase A), `Departamento` (coluna `nome`).
- Produces: campo `departamentos` (multi, opcional) no form de usuário — grava `departamento_usuario` via sync do Filament.

- [ ] **Step 1: Escrever o teste que falha**

Em `tests/Feature/Usuarios/UsuarioResourceTest.php`, adicionar o import e o método (o `setUp` já roda `EstruturaCemaSeeder`, que cria os 8 departamentos):

```php
use App\Models\Departamento;   // adicionar aos imports
```

```php
    public function test_form_do_admin_salva_departamentos(): void
    {
        $trabalhador = Role::findByName('trabalhador', 'web');
        $decom = Departamento::where('sigla', 'DECOM')->first();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Diretor do DECOM',
                'email' => 'decom@teste.com',
                'password' => 'senha-super-forte-2026',
                'roles' => [$trabalhador->id],
                'departamentos' => [$decom->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'decom@teste.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->departamentos->contains($decom));
    }
```

- [ ] **Step 2: Rodar o teste para ver falhar**

Run: `docker compose exec -T app php artisan test --filter="UsuarioResourceTest::test_form_do_admin_salva_departamentos"`
Expected: FAIL — o campo `departamentos` não existe no form, o vínculo não é gravado (`Failed asserting that ... contains`).

- [ ] **Step 3: Adicionar o `Select` ao UserResource**

Em `app/Filament/Resources/Users/UserResource.php`, dentro da `Section::make('Papel e estrutura')`, após o `Select::make('cargos')` (que fecha na linha 105), adicionar (o import `Select` já existe, linha 17):

```php
                        Select::make('departamentos')
                            ->label('Departamentos')
                            ->relationship('departamentos', 'nome')
                            ->multiple()
                            ->preload(),
```

- [ ] **Step 4: Rodar o teste para ver passar**

Run: `docker compose exec -T app php artisan test --filter=UsuarioResourceTest`
Expected: PASS (3 métodos — os 2 existentes + o novo).

- [ ] **Step 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Resources/Users tests/Feature/Usuarios
git add app/Filament/Resources/Users/UserResource.php tests/Feature/Usuarios/UsuarioResourceTest.php
git commit -m "feat(capacidades): Select de departamentos no UserResource (Peça 2)"
```

---

### Task 5: Peça 3 — `Select` de departamentos (required) nos 4 conteúdos + O1

**Files:**
- Modify: `app/Filament/Resources/Palestras/PalestraResource.php`, `Posts/PostResource.php`, `Agenda/AgendaDiaResource.php`, `Palestrantes/PalestranteResource.php`
- Modify: `tests/Feature/Filament/PalestraResourceTest.php`, `PostResourceTest.php`, `AgendaDiaResourceTest.php`, `PalestranteResourceTest.php`

**Interfaces:**
- Consumes: os 4 models `implements TemDepartamento` com `departamentos()` (Fase B), `Departamento` (coluna `nome`).
- Produces: campo `departamentos` **required** (multi, searchable) nos 4 forms — grava o pivô via sync. Todos os testes de resource que salvam conteúdo passam a fornecer `departamentos`.

> **Padrão O1 (aplicado a cada um dos 4 testes):** no `setUp`, criar `$this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);` (import `App\Models\Departamento`; propriedade `private Departamento $departamento;`). Em **todo** `fillForm` que chega a `->call('create')` ou `->call('save')` (sucesso **ou** rejeição), acrescentar a linha `'departamentos' => [$this->departamento->id],`. Isso mantém o create/edit passando (sucesso) e garante que os testes de rejeição reprovem **só** pelo campo sob teste (não por departamento faltando). Cada resource ganha ainda 2 métodos novos: `test_salva_departamento` (vínculo) e `test_exige_departamento` (prova o `required`).

- [ ] **Step 1: Escrever/ajustar o teste do Palestra (falha primeiro)**

Em `tests/Feature/Filament/PalestraResourceTest.php`:

1. Import + propriedade + `setUp`:

```php
use App\Models\Departamento;   // adicionar aos imports
```
```php
    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);
    }
```

2. Acrescentar `'departamentos' => [$this->departamento->id],` ao `fillForm` destes métodos (todos que chamam create/save): `test_cria_palestra_com_assuntos_destaques_e_um_palestrante`, `test_pivo_grava_papel_correto_para_palestrante_e_diretor`, `test_rejeita_zero_palestrantes`, `test_rejeita_tres_palestrantes`, `test_edit_troca_palestrante_e_ressincroniza_pivo` (no `fillForm` do `EditPalestra`), `test_rejeita_mesma_pessoa_como_palestrante_e_diretor`, `test_rejeita_cor_fundo_invalido`, `test_aceita_cor_fundo_hex_valido`, `test_cria_palestra_com_slide_duracao_e_referencias`. (Exemplo, no primeiro — os demais seguem igual:)

```php
            ->fillForm([
                'titulo' => 'Auxílios do Invisível',
                'slug' => 'auxilios-do-invisivel',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'assuntos' => [$assunto->id],
                'departamentos' => [$this->departamento->id],
                'destaques' => [
                    ['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.'],
                ],
            ])
```

3. Adicionar os 2 métodos novos:

```php
    public function test_salva_departamento(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Com Departamento',
                'slug' => 'com-departamento',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = Palestra::where('slug', 'com-departamento')->first();
        $this->assertTrue($palestra->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        $p1 = Palestrante::factory()->ativo()->create();

        Livewire::test(CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Sem Departamento',
                'slug' => 'sem-departamento',
                'status' => Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
```

- [ ] **Step 2: Rodar para ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest`
Expected: FAIL — `test_salva_departamento`/`test_exige_departamento` falham (campo inexistente); os métodos de sucesso ainda passam (o campo extra no fillForm é ignorado até existir). É o fail-primeiro esperado.

- [ ] **Step 3: Adicionar o `Select` ao PalestraResource**

Em `app/Filament/Resources/Palestras/PalestraResource.php`, dentro da `Tabs\Tab::make('Assuntos e destaques')`, após o `Select::make('assuntos')` (fecha na linha 157) e antes do `Repeater::make('destaques')`, adicionar (o import `Select` já existe, linha 21):

```php
                    Select::make('departamentos')
                        ->label('Departamentos')
                        ->relationship('departamentos', 'nome')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
```

- [ ] **Step 4: Rodar para ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest`
Expected: PASS (todos — os existentes atualizados + os 2 novos).

- [ ] **Step 5: Post — ajustar teste, adicionar Select, testar**

Em `tests/Feature/Filament/PostResourceTest.php`: import `use App\Models\Departamento;`, propriedade `private Departamento $departamento;`, e no `setUp` (após `actingAsAdmin`) `$this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);`. Acrescentar `'departamentos' => [$this->departamento->id],` ao `fillForm` de: `test_cria_post_simples`, `test_cria_rascunho_sem_data_de_publicacao`, `test_publicar_sem_data_usa_o_instante_atual`, `test_agendar_exige_data_de_publicacao`, `test_cria_post_com_categorias_e_faqs`, `test_cria_post_com_tags`, `test_slug_e_obrigatorio`, `test_cria_post_com_imagem_destacada_na_colecao_ml`. (Os testes de toolbar/upload/`ConfiguracoesBlog` não chamam create/save de Post — não mexer.) Adicionar os 2 métodos novos:

```php
    public function test_salva_departamento(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Post com Departamento',
                'slug' => 'post-com-departamento',
                'status' => Post::STATUS_PUBLICADO,
                'data_publicacao' => now()->format('Y-m-d H:i'),
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'post-com-departamento')->first();
        $this->assertTrue($post->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        Livewire::test(CreatePost::class)
            ->fillForm([
                'titulo' => 'Post sem Departamento',
                'slug' => 'post-sem-departamento',
                'status' => Post::STATUS_RASCUNHO,
                'data_publicacao' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
```

Em `app/Filament/Resources/Posts/PostResource.php`, dentro da `Tabs\Tab::make('Taxonomia e Publicação')`, após o `Select::make('tags')` (fecha na linha 228) e antes do `Grid::make(3)` (linha 229), adicionar (import `Select` já existe, linha 25):

```php
                    Select::make('departamentos')
                        ->label('Departamentos')
                        ->relationship('departamentos', 'nome')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
```

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Expected: PASS.

- [ ] **Step 6: AgendaDia — ajustar teste, adicionar Select (columnSpanFull), testar**

Em `tests/Feature/Filament/AgendaDiaResourceTest.php`: import `use App\Models\Departamento;`, propriedade + `setUp` com `$this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);`. Acrescentar `'departamentos' => [$this->departamento->id],` ao `fillForm` de `test_cria_dia` e `test_rejeita_data_duplicada`. Adicionar:

```php
    public function test_salva_departamento(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-02',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $dia = AgendaDia::where('data', '2026-05-02')->first();
        $this->assertTrue($dia->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        Livewire::test(CreateAgendaDia::class)
            ->fillForm([
                'data' => '2026-05-03',
                'status' => AgendaDia::STATUS_PUBLICADO,
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
```

Em `app/Filament/Resources/Agenda/AgendaDiaResource.php`, ao final do `->components([...])` do form, após o `RichEditor::make('prece')` (linha 73), adicionar (form flat ⇒ `columnSpanFull()`; import `Select` já existe, linha 18):

```php
            Select::make('departamentos')
                ->label('Departamentos')
                ->relationship('departamentos', 'nome')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull(),
```

Run: `docker compose exec -T app php artisan test --filter=AgendaDiaResourceTest`
Expected: PASS.

- [ ] **Step 7: Palestrante — ajustar teste, adicionar Section+Select, testar**

Em `tests/Feature/Filament/PalestranteResourceTest.php`: import `use App\Models\Departamento;`, propriedade + no `setUp` (após `$this->admin = $this->actingAsAdmin();`) `$this->departamento = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);`. Acrescentar `'departamentos' => [$this->departamento->id],` ao `fillForm` de `test_pode_criar_palestrante_via_formulario`, `test_pode_editar_palestrante_via_formulario` (fillForm do `EditPalestrante`), `test_cria_palestrante_com_slug_auto_e_bio_sanitizada`, `test_cria_palestrante_com_chamada`. Adicionar:

```php
    public function test_salva_departamento(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Com Departamento',
                'slug' => 'com-departamento',
                'ativo' => true,
                'departamentos' => [$this->departamento->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pessoa = Palestrante::where('slug', 'com-departamento')->first();
        $this->assertTrue($pessoa->departamentos->contains($this->departamento));
    }

    public function test_exige_departamento(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Sem Departamento',
                'slug' => 'sem-departamento',
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['departamentos']);
    }
```

Em `app/Filament/Resources/Palestrantes/PalestranteResource.php`, após a `Section::make('Contato e exibição')` (fecha na linha 123), adicionar uma Section nova (import `Select` NÃO existe neste arquivo — adicionar `use Filament\Forms\Components\Select;` junto aos demais `use Filament\Forms\Components\*`):

```php
                Section::make('Departamentos')
                    ->schema([
                        Select::make('departamentos')
                            ->label('Departamentos')
                            ->relationship('departamentos', 'nome')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
```

Run: `docker compose exec -T app php artisan test --filter=PalestranteResourceTest`
Expected: PASS.

- [ ] **Step 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Resources tests/Feature/Filament
git add app/Filament/Resources/Palestras/PalestraResource.php app/Filament/Resources/Posts/PostResource.php app/Filament/Resources/Agenda/AgendaDiaResource.php app/Filament/Resources/Palestrantes/PalestranteResource.php tests/Feature/Filament/PalestraResourceTest.php tests/Feature/Filament/PostResourceTest.php tests/Feature/Filament/AgendaDiaResourceTest.php tests/Feature/Filament/PalestranteResourceTest.php
git commit -m "feat(capacidades): Select de departamentos (required) nos 4 conteúdos + testes O1 (Peça 3)"
```

---

### Task 6: Verificação de não-regressão + dev + PR

**Files:** nenhuma alteração de código — gate de fechamento da fase.

**Interfaces:**
- Consumes: todas as tasks anteriores.
- Produces: prova de que a fase não quebrou nada (suíte verde, Pint limpo) e de que a matriz funciona no dev.

- [ ] **Step 1: Suíte inteira no container**

Run: `docker compose exec -T app php artisan test`
Expected: PASS — o total anterior **mais** os testes novos desta fase (Task 1: 3; Task 2: 5; Task 3: 3; Task 4: +1; Task 5: +2 por resource + os create/edit-tests atualizados). (Ver a memória `flaky-importadorblog-gd-cap-imagem`: 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados, não é regressão desta fase.)

- [ ] **Step 2: Pint (o CI roda `pint --test` antes dos testes)**

Run: `docker compose exec -T app ./vendor/bin/pint --test`
Expected: PASS (nenhum arquivo com drift de estilo).

- [ ] **Step 3: Conferir a matriz no dev (localhost)**

```bash
docker compose restart app worker
```
Abrir `http://localhost/admin/matriz-capacidades` como admin: a grade lista os 5 recursos (Evento, Palestra, Post, Agenda do Dia, Palestrante) × 4 ações × 2 papéis (Trabalhador, Diretor); marcar algumas capacidades do `diretor`, Salvar, recarregar e confirmar que a pré-marca reflete o salvo. Abrir o form de uma Palestra/Post/AgendaDia/Palestrante e confirmar o campo **Departamentos (obrigatório)**; abrir um Usuário e confirmar o campo **Departamentos**.

> Ciência (dev): `role_has_permissions` no dev nasce vazia; esta é a primeira vez que recebe dados. Nenhum comando/seed — a atribuição é feita pela própria tela. Os conteúdos importados (Fase B) já têm departamento pelo backfill; o `required` só afeta **novas** edições no painel.

- [ ] **Step 4: Push e PR**

```bash
git push -u origin fase-c-matriz-capacidade
```

Abrir PR contra `main` (título: `feat(capacidades): Fase C — matriz papel×capacidade + vínculos`), corpo resumindo o SPEC e apontando que: a matriz é o 1º escritor de `role_has_permissions` (via `syncPermissions`, sem `forget` — F10); o departamento passou a ser **obrigatório** nos 4 conteúdos (F5), com todos os resource-tests atualizados (O1); as Peças 2/3 usam o molde do `Select` do Evento; o `/admin` segue admin-only e as policies A/B intactas. Aguardar o CI verde no commit final antes do merge.

---

## Notas de execução (para o worker)

- **Ordem obrigatória:** Task 1 → 2 → 3 → 4 → 5 → 6. A Task 2 depende de 1 (rótulos + `PAPEIS_EDITAVEIS`). As Tasks 3/4/5 são independentes entre si, mas rodam após a 2 por coesão. A Task 6 é o gate final.
- **`salvar()` (pt-BR), não `save()`** — na Página e nos testes de página (`->call('salvar')`). Resources usam `->call('create')`/`->call('save')`.
- **`syncPermissions` já limpa o cache** — não adicionar `forgetCachedPermissions` (F10). Não usar `Role::create`/`updateOrCreate` na matriz (zeraria `nivel`) — só `findByName`+`syncPermissions`.
- **Salvar toca só `trabalhador`/`diretor`** — iterar `GlossarioUsuarios::PAPEIS_EDITAVEIS`; jamais tocar `admin`/`frequentador`.
- **statePath aninhado** `data.<papel>.<recurso>.<acao>` — o `Toggle::make("{$papel}.{$recurso}.{$acao}")` gera essa árvore; o `fillForm`/`assertFormSet` do teste usa a mesma dot-notation (`'diretor.palestra.editar' => true`).
- **O1 — `required` quebra create E edit-save** de registros sem departamento (ex.: `test_edit_troca_palestrante`, `test_pode_editar_palestrante`). Atualizar **todo** `fillForm` que chega a `create`/`save`, inclusive os de rejeição (para isolar o motivo). O `$this->departamento` do `setUp` de cada teste é o `Departamento::create(['sigla'=>'DED','nome'=>'DED','slug'=>'ded'])`.
- **`Evento` NÃO entra na Peça 3** — já tem o `Select` (Fase B). Não tocar `EventoForm`/`EventoResource`.
- **Namespaces v5** — `Grid`/`Section`/`Form`/`EmbeddedSchema`/`Actions` de `Filament\Schemas\Components\*`; `Toggle`/`Select` de `Filament\Forms\Components\*`; `Action` de `Filament\Actions\Action`. Import de `Select` já existe em Palestra/Post/AgendaDia/User; **falta** em Palestrante (adicionar).
- **Pint por-task é OBRIGATÓRIO** — os novos `use` entram sem posição exata e o `ordered_imports` do preset laravel reordena; commitar antes do Pint faz o CI (`pint --test`) reprovar.
- **Nada destrutivo no banco de dev:** não há migration; só `restart app worker`. Nunca `fresh`/`refresh`/`wipe`/`reset`.
