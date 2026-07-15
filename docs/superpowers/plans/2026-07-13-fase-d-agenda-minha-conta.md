# Fase D — Edição da Agenda no /minha-conta — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Abrir o CRUD da Agenda da Reforma Íntima (`AgendaDia`) a não-admin pela superfície do site (`/minha-conta`), com um Filament Form embutido, respeitando capacidade + filtro de departamento (A/B/C), auditoria e escalonamento server-side — o piloto do "não-admin edita pelo site".

**Architecture:** Um schema único (`AgendaDiaForm::schema()`) alimenta o `/admin` e um componente Livewire do site. O componente lista `AgendaDia` escopado ao departamento do usuário (Blade+Livewire), embute o form (create/edit) num tema Filament escopado à página, e força os campos privilegiados (`departamentos`, `status`) no servidor. A autorização é a policy pronta de A/B/C; a auditoria é o trait `LogsActivity` + o helper `AuditoriaAutorizacao` já existentes.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5.6.7 · Livewire 4.3.2 · Blade SSR · Tailwind v4 · MySQL. Docker (`docker compose exec -T app php artisan ...`). npm/Vite **no host** (o container não tem Node).

**Spec:** [docs/superpowers/specs/2026-07-13-fase-d-agenda-minha-conta.md](../specs/2026-07-13-fase-d-agenda-minha-conta.md) (aprovado no passe; O1/O2 aplicados). Todas as referências `§N` abaixo são a esse spec.

## Global Constraints

- **pt-BR em tudo**: código (identificadores de domínio), comentários, mensagens de UI/erro, commits.
- **Cabeçalho de autoria** em todo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15`.
- **Guard `web`** em toda checagem de permissão/papel (o projeto é single-guard `web`).
- **0 migrations** nesta fase — todo o schema já existe (§2.10). **Nunca** `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo (o dev tem 123 palestras/agenda + 44 posts + mídia importados). Conexão `legado` é **read-only**.
- **Fonte única**: `AgendaDiaForm::schema()` é consumido pelo Resource **e** pelo componente do site; nada é duplicado.
- **Nunca confiar no POST**: `departamentos` e `status` são forçados/validados no servidor (§7).
- **Testes**: `docker compose exec -T app php artisan test --filter=<Nome>` por task; suíte inteira nos fechamentos de fase. **Pint antes de qualquer commit de PR**: `docker compose exec -T app vendor/bin/pint`.
- **Cada commit** termina com o trailer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **Split (decidido — refina §14)**: **D1** = fundação (schema + tema + superfície com *create seguro*, provado no browser) → PR próprio → passe. **D2** = vertical completa (editar/excluir + auditoria + 3 correções + E2E) → PR próprio → passe. D1 já ship a *create policy-gated com campos forçados* (não uma superfície insegura): o "hello world" do §14 é ampliado para o mínimo **seguro**; a leitura/edição/auditoria/correções ficam em D2.

---

# FASE D1 — Fundação (schema + tema + create seguro)

> Fecha o risco de CSS/tema num PR pequeno e verificável. Ao fim de D1: um editor de DECOM cria um `AgendaDia` pelo site, com o form estilizado e os campos privilegiados forçados. **PR D1 → passe → merge → D2.**

## Task 1: Extrair `AgendaDiaForm::schema(bool $comDepartamentos = true)`

**Files:**
- Create: `app/Filament/Schemas/AgendaDiaForm.php`
- Modify: `app/Filament/Resources/Agenda/AgendaDiaResource.php:38-83` (form passa a consumir o schema)
- Test: `tests/Feature/Filament/AgendaDiaResourceTest.php` (regressão — já existe) + `tests/Feature/Filament/AgendaDiaFormSchemaTest.php` (novo — omissão do campo)

**Interfaces:**
- Produces: `App\Filament\Schemas\AgendaDiaForm::schema(bool $comDepartamentos = true): array` — array de componentes Filament. Com `true` inclui o `Select('departamentos')`; com `false` o campo é **ausente** do array.

- [ ] **Step 1: Escrever o teste que falha (schema omite `departamentos` no site)**

Create `tests/Feature/Filament/AgendaDiaFormSchemaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Filament;

use App\Filament\Schemas\AgendaDiaForm;
use Filament\Forms\Components\Select;
use Tests\TestCase;

class AgendaDiaFormSchemaTest extends TestCase
{
    /** O Select 'departamentos' é um componente de topo do array (não aninhado no Grid). */
    private function temSelectDepartamentos(array $schema): bool
    {
        return collect($schema)->contains(
            fn ($c) => $c instanceof Select && $c->getName() === 'departamentos'
        );
    }

    public function test_schema_padrao_inclui_departamentos(): void
    {
        $this->assertTrue($this->temSelectDepartamentos(AgendaDiaForm::schema()));
    }

    public function test_schema_do_site_omite_departamentos(): void
    {
        $comDeptos = AgendaDiaForm::schema(comDepartamentos: true);
        $semDeptos = AgendaDiaForm::schema(comDepartamentos: false);

        // O site tem exatamente 1 componente a menos (o Select departamentos) e não o contém.
        $this->assertCount(count($comDeptos) - 1, $semDeptos);
        $this->assertFalse($this->temSelectDepartamentos($semDeptos), 'O schema do site NÃO deve incluir departamentos.');
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AgendaDiaFormSchemaTest`
Expected: FAIL — `Class "App\Filament\Schemas\AgendaDiaForm" not found`.

- [ ] **Step 3: Criar `AgendaDiaForm` (move o array inline do Resource)**

Create `app/Filament/Schemas/AgendaDiaForm.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Filament\Schemas;

use App\Models\AgendaDia;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

/**
 * Fonte única dos CAMPOS do formulário de AgendaDia: rótulos, componentes e regras de campo.
 * Consumido pelo painel (AgendaDiaResource) e pelo componente do site (App\Livewire\Conta\AgendaConta).
 *
 * O campo `departamentos` é PRIVILEGIADO (§5/§7 do spec): no site ele é AUSENTE do schema
 * (comDepartamentos: false) e o servidor força o valor (DED+DECOM na criação; preservado na edição).
 * A sanitização de HTML dos textos já vive no model (mutators clean()), não aqui.
 *
 * @return array<Component>
 */
class AgendaDiaForm
{
    /** @return array<Component> */
    public static function schema(bool $comDepartamentos = true): array
    {
        $campos = [
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
        ];

        if ($comDepartamentos) {
            $campos[] = Select::make('departamentos')
                ->label('Departamentos')
                ->relationship('departamentos', 'nome')
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull();
        }

        return $campos;
    }
}
```

- [ ] **Step 4: Fazer o Resource consumir o schema**

Modify `app/Filament/Resources/Agenda/AgendaDiaResource.php`. Substituir o corpo do `form()` (linhas 40-82) por:

```php
    public static function form(Schema $schema): Schema
    {
        // Schema vindo da fonte única (App\Filament\Schemas\AgendaDiaForm), reaproveitada
        // pelo componente de edição embutido no site (/minha-conta).
        return $schema->components(AgendaDiaForm::schema());
    }
```

Adicionar o import no topo do Resource (junto aos demais `use`): `use App\Filament\Schemas\AgendaDiaForm;`. Remover os imports que ficaram órfãos **somente se** deixarem de ser usados no arquivo (`DatePicker`, `RichEditor`, `Select`, `TextInput`, `Grid` — a `table()` ainda usa `TextColumn`/`SelectFilter`; conferir com Pint no Step 6).

- [ ] **Step 5: Rodar os testes (novo + regressão do Resource)**

Run: `docker compose exec -T app php artisan test --filter=AgendaDiaFormSchemaTest`
Expected: PASS (2 testes).

Run: `docker compose exec -T app php artisan test --filter=AgendaDiaResourceTest`
Expected: PASS — o painel continua criando/editando `AgendaDia` (regressão da extração).

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Filament/Schemas/AgendaDiaForm.php app/Filament/Resources/Agenda/AgendaDiaResource.php tests/Feature/Filament/AgendaDiaFormSchemaTest.php`

```bash
git add app/Filament/Schemas/AgendaDiaForm.php app/Filament/Resources/Agenda/AgendaDiaResource.php tests/Feature/Filament/AgendaDiaFormSchemaTest.php
git commit -m "feat(agenda): AgendaDiaForm::schema() como fonte única (site omite departamentos)"
```

## Task 2: Tema Filament escopado do site (o maior risco — provado no browser)

**Files:**
- Create: `resources/css/filament/site/theme.css`
- Modify: `vite.config.js` (adicionar a entrada ao `input`)
- Verify: navegador (não há teste PHPUnit — é CSS/build; a prova é visual, como o critério 4 do spike)

**Interfaces:**
- Produces: um asset Vite `resources/css/filament/site/theme.css` carregável via `@vite('resources/css/filament/site/theme.css')` no slot `headTop` da página da agenda. Entrega o CSS dos componentes `fi-*` (DatePicker, RichEditor, Select, TextInput, Grid) **sem preflight** e **sem a fonte Inter**.

> **Por que é uma task própria (§4.2):** `@filamentStyles` **não** entrega o CSS dos componentes — eles vivem no tema compilado (spike §2.9). Reusar o tema do painel custa 609 KB e traz preflight + Inter. Este é o item que **quase reprovou o spike**; isolá-lo aqui e prová-lo no browser antes de empilhar a lógica.

- [ ] **Step 1: Criar o primeiro corte do tema (sem preflight, sem Inter, @source restrito)**

Create `resources/css/filament/site/theme.css`:

```css
/* Tema Filament ENXUTO e ESCOPADO para os formulários do site (/minha-conta).
   Diferente do tema do painel (resources/css/filament/admin/theme.css): SEM preflight
   (o site já tem o seu reset) e SEM a fonte Inter (o site usa a própria tipografia).
   Carregado só na página da agenda, via slot headTop, ANTES do app.css.
   Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15 */

/* Ordem das camadas fixada explicitamente (receita v4): sem esta linha, a 1ª aparição definiria
   a ordem (theme, utilities antes de base/components do index.css), pondo components acima de
   utilities e sobrepondo utilitários da marcação. */
@layer theme, base, components, utilities;

/* Tailwind v4 SEM preflight: importa tokens (@theme) e utilitários, mas NÃO o preflight
   (que resetaria header/footer do site). source(none): sem varredura automática — as
   classes vêm do @source restrito abaixo. */
@import 'tailwindcss/theme.css' layer(theme) source(none);
@import 'tailwindcss/utilities.css' layer(utilities) source(none);

/* Estilos dos componentes Filament (fi-*). É o índice do tema-base do painel, sem o
   `@import 'tailwindcss'` (que traria o preflight). */
@import '../../../../vendor/filament/filament/resources/css/index.css';

/* @source restrito: só os arquivos que emitem classes fi-* usadas no site. */
@source '../../../../app/Filament/Schemas/AgendaDiaForm.php';
@source '../../../../resources/views/livewire/conta/**/*';

/* Fonte do site (NÃO Inter). O Filament injeta inline `:root { --font-family: 'Inter...' }`;
   html:root (especificidade 0,1,1) vence esse :root (0,1,0) por especificidade. */
html:root {
    --font-family: 'Poppins';
}
```

- [ ] **Step 2: Registrar a entrada no Vite**

Modify `vite.config.js` — adicionar `'resources/css/filament/site/theme.css'` ao array `input` (junto das demais entradas):

```js
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/cropper-perfil.js',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/site/theme.css',
            ],
```

- [ ] **Step 3: Compilar (no HOST — o container não tem Node)**

Run (no host, na raiz do projeto): `npm run build`
Expected: build sem erro; o manifest passa a conter `resources/css/filament/site/theme.css`. Se o import de `tailwindcss/theme.css`/`utilities.css` ou `index.css` falhar, ajustar o caminho/forma do import (é o ponto de iteração desta task) e recompilar.

- [ ] **Step 4: Provar no browser (a verificação desta task)**

> Esta prova depende da página da agenda (Task 4/5). Se executar as tasks em ordem, rodar este Step **após** a Task 5. Alternativamente, provar cedo com uma rota temporária que embute `AgendaDiaForm::schema()` num `<x-layout.conta>` com o slot `headTop` (descartar a rota depois).

Com `npm run dev` (host) e o container servindo, autenticar como um editor de DECOM e abrir `/minha-conta/agenda`, acionar "Novo dia" e conferir **visualmente**:
- ✅ O `DatePicker` abre o painel flutuante estilizado (não um `<input>` cru).
- ✅ Os `RichEditor` mostram a toolbar (negrito/itálico/lista), não um textarea cru.
- ✅ O header/footer do site **não** quebram (sem duplo-preflight): comparar com `/minha-conta/perfil`.
- ✅ **Sem vazamento**: abrir `/minha-conta` e `/minha-conta/perfil` e confirmar que o HTML **não** contém `filament/site/theme` (o `@vite` do tema só está na view da agenda) e que o visual dessas páginas é idêntico ao de antes (hash/olho, molde do critério 4 do spike).

Se algum item falhar: iterar o `theme.css` (o caso mais provável é o preflight — se o header quebrar, confirmar que `tailwindcss/preflight.css` **não** está sendo importado; se os `fi-*` sumirem, o `index.css` do vendor pode exigir os tokens do `@import 'tailwindcss'` — nesse caso importar `tailwindcss` com exclusão explícita do preflight). Recompilar e reconferir até os 4 itens passarem.

- [ ] **Step 5: Commit**

```bash
git add resources/css/filament/site/theme.css vite.config.js
git commit -m "feat(agenda): tema Filament escopado do site (sem preflight/Inter, @source restrito)"
```

## Task 3: Escopo por departamento + acesso da aba + mantenedores da Agenda

**Files:**
- Modify: `app/Models/AgendaDia.php` (novo scope `noEscopoDe`)
- Create: `app/Support/Conta/AbaAgenda.php`
- Create: `app/Support/Agenda/AgendaMantenedores.php`
- Test: `tests/Feature/Conta/AbaAgendaTest.php`

**Interfaces:**
- Produces: `App\Models\AgendaDia::scopeNoEscopoDe(Builder $query, User $user): Builder` — filtra `AgendaDia` cujos departamentos intersectam os do usuário; fail-closed (usuário sem depto ⇒ nenhum). Uso: `AgendaDia::noEscopoDe($user)`.
- Produces: `App\Support\Conta\AbaAgenda::visivelPara(User $user): bool` — `agenda.ver` **E** existe `AgendaDia` no escopo.
- Produces: `App\Support\Agenda\AgendaMantenedores::ids(): array` — ids dos departamentos mantenedores da Agenda (**DED + DECOM** por sigla). Usado para forçar `departamentos` na criação (§7 O1).

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/AbaAgendaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Agenda\AgendaMantenedores;
use App\Support\Conta\AbaAgenda;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbaAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        Permission::findOrCreate('agenda.ver', 'web');
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver']);
    }

    private function editorDe(string $sigla): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', $sigla)->value('id')]);

        return $user;
    }

    public function test_mantenedores_sao_ded_e_decom(): void
    {
        $esperado = Departamento::whereIn('sigla', ['DED', 'DECOM'])->pluck('id')->sort()->values()->all();

        $this->assertSame($esperado, collect(AgendaMantenedores::ids())->sort()->values()->all());
    }

    public function test_scope_no_escopo_filtra_por_departamento(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');

        $noEscopo = AgendaDia::factory()->create();
        $noEscopo->departamentos()->sync([$decom]);
        $foraEscopo = AgendaDia::factory()->create();
        $foraEscopo->departamentos()->sync([$ded]);

        $user = $this->editorDe('DECOM');
        $ids = AgendaDia::noEscopoDe($user)->pluck('id');

        $this->assertTrue($ids->contains($noEscopo->id));
        $this->assertFalse($ids->contains($foraEscopo->id));
    }

    public function test_scope_fail_closed_para_usuario_sem_departamento(): void
    {
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        $semDepto = User::factory()->create();
        $semDepto->assignRole('diretor');

        $this->assertSame(0, AgendaDia::noEscopoDe($semDepto)->count());
    }

    public function test_aba_visivel_com_capacidade_e_registro_no_escopo(): void
    {
        $user = $this->editorDe('DECOM');
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->assertTrue(AbaAgenda::visivelPara($user));
    }

    public function test_aba_oculta_sem_registro_no_escopo(): void
    {
        $user = $this->editorDe('DECOM'); // tem agenda.ver, mas nenhum AgendaDia no DECOM

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }

    public function test_aba_oculta_sem_capacidade(): void
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador'); // sem agenda.ver
        $user->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        AgendaDia::factory()->create()->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }

    public function test_nao_quebra_quando_a_capacidade_nao_esta_no_catalogo(): void
    {
        // Simula ambiente/teste SEM CapacidadesSeeder: a permission não existe no catálogo.
        // A nav renderiza em toda página de conta — visivelPara deve devolver false, não estourar.
        Permission::where('name', 'agenda.ver')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole('frequentador');

        $this->assertFalse(AbaAgenda::visivelPara($user));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AbaAgendaTest`
Expected: FAIL — `Call to undefined method ...::noEscopoDe()` / classe `AbaAgenda`/`AgendaMantenedores` inexistente.

- [ ] **Step 3: Adicionar o scope ao model**

Modify `app/Models/AgendaDia.php` — adicionar o método após `scopePublicado()` (por volta de `:45`):

```php
    /**
     * AgendaDia cujos departamentos intersectam os do usuário (filtro de objeto A/B).
     * Fail-closed: usuário sem departamento ⇒ nenhum registro.
     */
    public function scopeNoEscopoDe(Builder $query, User $user): Builder
    {
        $ids = $user->departamentos()->pluck('departamentos.id')->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('departamentos', fn (Builder $q) => $q->whereIn('departamentos.id', $ids));
    }
```

Adicionar `use App\Models\User;` ao topo do model (junto aos demais `use`).

- [ ] **Step 4: Criar `AgendaMantenedores`**

Create `app/Support/Agenda/AgendaMantenedores.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Support\Agenda;

use App\Models\Departamento;

/**
 * Departamentos que MANTÊM a Agenda da Reforma Íntima = DED + DECOM (§7 O1 do spec).
 * Todo AgendaDia criado pelo site nasce vinculado a ESTES departamentos, independente do
 * autor — para que DED e DECOM editem TODA a Agenda (decisão 6). Resolvidos por sigla
 * (determinístico), não por id numérico.
 */
class AgendaMantenedores
{
    public const SIGLAS = ['DED', 'DECOM'];

    /** @return array<int> ids dos departamentos mantenedores. */
    public static function ids(): array
    {
        return Departamento::whereIn('sigla', self::SIGLAS)->pluck('id')->all();
    }
}
```

- [ ] **Step 5: Criar `AbaAgenda`**

Create `app/Support/Conta/AbaAgenda.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Support\Conta;

use App\Models\AgendaDia;
use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use WeakMap;

/**
 * Fonte única do acesso à aba/rota "Agenda" no /minha-conta (§6.1 do spec).
 * Usada por: a nav (mostrar/ocultar), o ContaController@agenda (abort_unless) e o mount do
 * componente. Aba visível ⇔ capacidade de ver + registro no escopo (decisão 1) — senão apareceria
 * vazia para todo diretor. Capacidade checada ANTES da query (curto-circuito em memória).
 *
 * Memoizada por request via WeakMap pelo objeto User (a nav renderiza em TODA página /minha-conta;
 * auth()->user() devolve a mesma instância no request). WeakMap não sofre reuso de spl_object_id.
 *
 * FAIL-CLOSED se a capacidade nem existe no catálogo: sem CapacidadesSeeder (ambiente/testes que
 * não semeiam as permissions), hasPermissionTo lançaria PermissionDoesNotExist e QUEBRARIA a nav
 * de todas as páginas de conta. O catch devolve false — a aba some, a nav não quebra.
 */
class AbaAgenda
{
    private static ?WeakMap $cache = null;

    public static function visivelPara(User $user): bool
    {
        self::$cache ??= new WeakMap();

        return self::$cache[$user] ??= self::calcular($user);
    }

    private static function calcular(User $user): bool
    {
        try {
            if (! $user->hasPermissionTo('agenda.ver')) {
                return false;
            }
        } catch (PermissionDoesNotExist) {
            return false;
        }

        return AgendaDia::noEscopoDe($user)->exists();
    }
}
```

- [ ] **Step 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AbaAgendaTest`
Expected: PASS (6 testes).

- [ ] **Step 7: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Models/AgendaDia.php app/Support/Conta/AbaAgenda.php app/Support/Agenda/AgendaMantenedores.php tests/Feature/Conta/AbaAgendaTest.php`

```bash
git add app/Models/AgendaDia.php app/Support/Conta/AbaAgenda.php app/Support/Agenda/AgendaMantenedores.php tests/Feature/Conta/AbaAgendaTest.php
git commit -m "feat(agenda): scope noEscopoDe + AbaAgenda (acesso) + AgendaMantenedores (DED+DECOM)"
```

## Task 4: Rota `conta.agenda` + controller + view + nav condicional

**Files:**
- Modify: `routes/web.php:40-43` (nova rota no grupo `conta.`)
- Modify: `app/Http/Controllers/ContaController.php` (método `agenda()`)
- Create: `resources/views/conta/agenda.blade.php`
- Modify: `resources/views/components/conta/nav.blade.php:3-8` (item condicional)
- Test: `tests/Feature/Conta/AcessoAgendaContaTest.php`

**Interfaces:**
- Consumes: `AbaAgenda::visivelPara(User): bool` (Task 3).
- Produces: rota nomeada `conta.agenda` (`GET /minha-conta/agenda`); a view `conta.agenda` embute `<livewire:conta.agenda-conta />` (Task 5) com os slots `headTop` (tema) e `scripts` (`@filamentScripts`).

- [ ] **Step 1: Escrever o teste que falha**

Create `tests/Feature/Conta/AcessoAgendaContaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AcessoAgendaContaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        Permission::findOrCreate('agenda.ver', 'web');
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver']);
    }

    private function editorDecomComAgenda(): User
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create()->departamentos()->sync([$decom]);

        return $user;
    }

    public function test_editor_no_escopo_acessa_a_rota(): void
    {
        $this->actingAs($this->editorDecomComAgenda())
            ->get(route('conta.agenda'))
            ->assertOk();
    }

    public function test_usuario_sem_capacidade_recebe_403(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('frequentador');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create()->departamentos()->sync([$decom]);

        $this->actingAs($user)->get(route('conta.agenda'))->assertForbidden();
    }

    public function test_editor_sem_registro_no_escopo_recebe_403(): void
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', 'DECOM')->value('id')]);
        // nenhum AgendaDia criado

        $this->actingAs($user)->get(route('conta.agenda'))->assertForbidden();
    }

    public function test_visitante_anonimo_e_redirecionado_ao_login(): void
    {
        $this->get(route('conta.agenda'))->assertRedirect(route('login'));
    }

    public function test_nav_mostra_aba_para_editor_no_escopo(): void
    {
        $this->actingAs($this->editorDecomComAgenda())
            ->get(route('conta.perfil'))
            ->assertSee('Agenda');
    }

    public function test_nav_oculta_aba_para_quem_nao_tem_acesso(): void
    {
        $user = User::factory()->create();
        $user->assignRole('frequentador');

        $this->actingAs($user)->get(route('conta.perfil'))->assertDontSee(route('conta.agenda'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AcessoAgendaContaTest`
Expected: FAIL — rota `conta.agenda` não definida.

- [ ] **Step 3: Adicionar a rota**

Modify `routes/web.php` — dentro do grupo `conta.` (`:40-43`), após a rota `perfil`:

```php
    Route::get('/agenda', [ContaController::class, 'agenda'])->name('agenda');
```

- [ ] **Step 4: Adicionar o método ao controller**

Modify `app/Http/Controllers/ContaController.php` — adicionar o método (e `use App\Support\Conta\AbaAgenda;` no topo):

```php
    public function agenda(): View
    {
        abort_unless(AbaAgenda::visivelPara(auth()->user()), 403);

        return view('conta.agenda');
    }
```

- [ ] **Step 5: Criar a view da agenda (com os slots do tema)**

Create `resources/views/conta/agenda.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15 --}}
<x-layout.conta titulo="Agenda da Reforma Íntima" ativo="agenda">
    {{-- Tema Filament escopado ANTES do app.css (o site vence a cascata); JS dos componentes
         DEPOIS do Livewire. Só nesta página — não vaza para o resto do /minha-conta. --}}
    <x-slot:headTop>@vite('resources/css/filament/site/theme.css')</x-slot:headTop>
    <x-slot:scripts>@filamentScripts</x-slot:scripts>

    <livewire:conta.agenda-conta />
</x-layout.conta>
```

- [ ] **Step 6: Tornar o item da nav condicional**

Modify `resources/views/components/conta/nav.blade.php` — substituir o bloco `@php` (`:3-8`) por:

```blade
@php
    $itens = [
        ['chave' => 'painel', 'rotulo' => 'Painel', 'rota' => 'conta.painel'],
        ['chave' => 'perfil', 'rotulo' => 'Meu Perfil', 'rota' => 'conta.perfil'],
    ];
    if (\App\Support\Conta\AbaAgenda::visivelPara(auth()->user())) {
        $itens[] = ['chave' => 'agenda', 'rotulo' => 'Agenda', 'rota' => 'conta.agenda'];
    }
@endphp
```

> ⚠️ **Regressão evitada (verificação adversarial):** esta chamada roda em **TODA** página `/minha-conta`.
> `AbaAgenda::visivelPara` (Task 3) é **fail-closed** e blinda `PermissionDoesNotExist` (catch → false), para
> não quebrar a nav em ambientes/testes sem `CapacidadesSeeder` (os testes de conta existentes não semeiam
> `agenda.ver`). O passo de **suíte inteira** (Task 6 Step 4) confirma que `PainelTest`/`PerfilViewTest`/
> `EditarPerfilTest`/`AcessoContaTest`/`HeaderAuthTest` seguem verdes.

- [ ] **Step 7: Rodar e ver passar**

> A view embute `<livewire:conta.agenda-conta />`, que só existe na Task 5. Para esta task passar isolada, criar o **stub** do componente antes de rodar (a Task 5 o completa): `docker compose exec -T app php artisan make:livewire Conta/AgendaConta` e deixar `render()` retornando uma view mínima `resources/views/livewire/conta/agenda-conta.blade.php` com `<div></div>`. Se preferir executar 4→5 sem stub, rodar este Step ao fim da Task 5.

Run: `docker compose exec -T app php artisan test --filter=AcessoAgendaContaTest`
Expected: PASS (6 testes).

- [ ] **Step 8: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint routes/web.php app/Http/Controllers/ContaController.php tests/Feature/Conta/AcessoAgendaContaTest.php`

```bash
git add routes/web.php app/Http/Controllers/ContaController.php resources/views/conta/agenda.blade.php resources/views/components/conta/nav.blade.php tests/Feature/Conta/AcessoAgendaContaTest.php
git commit -m "feat(agenda): rota conta.agenda + nav condicional (acesso via AbaAgenda)"
```

## Task 5: Componente Livewire `AgendaConta` — lista escopada + criar (campos forçados)

**Files:**
- Create/Complete: `app/Livewire/Conta/AgendaConta.php`
- Create/Complete: `resources/views/livewire/conta/agenda-conta.blade.php`
- Test: `tests/Feature/Conta/AgendaContaCriarTest.php`

**Interfaces:**
- Consumes: `AgendaDiaForm::schema(comDepartamentos: false)` (Task 1), `AgendaDia::noEscopoDe($user)` (Task 3), `AgendaMantenedores::ids()` (Task 3), `AbaAgenda::visivelPara` (Task 3), `AgendaDiaPolicy` (existente).
- Produces: `App\Livewire\Conta\AgendaConta` com propriedades públicas `?array $data`, `?int $editandoId`, `bool $mostrandoForm`; ações `novo()`, `cancelar()`, `salvar()`; a criação **força** `departamentos = AgendaMantenedores::ids()` e `status = rascunho` para quem não tem `agenda.editar`. `editar()`/`excluir()` chegam na Task 7.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/AgendaContaCriarTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgendaContaCriarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        foreach (['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
    }

    private function editorDecom(array $capacidades): User
    {
        Role::findByName('diretor', 'web')->syncPermissions($capacidades);
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        // um AgendaDia no escopo p/ a aba/rota abrir (data fixa longe das datas de teste, evita colisão de unique)
        AgendaDia::factory()->create(['data' => '2020-01-01'])->departamentos()->sync([$decom]);

        return $user;
    }

    public function test_criar_forca_departamentos_ded_e_decom(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        $mantenedores = Departamento::whereIn('sigla', ['DED', 'DECOM'])->pluck('id')->sort()->values()->all();

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm([
                'data' => '2027-03-15',
                'status' => AgendaDia::STATUS_PUBLICADO,
                'reflexao' => '<p>Reflexão do dia.</p>',
                'meta_dia_titulo' => 'Perseverança',
            ])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $novo = AgendaDia::whereDate('data', '2027-03-15')->firstOrFail();
        $this->assertSame($mantenedores, $novo->departamentos()->pluck('departamentos.id')->sort()->values()->all());

        // §10.7: como nasce DED+DECOM, um diretor de DED edita o registro criado pelo editor de DECOM.
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        $diretorDed = User::factory()->create();
        $diretorDed->assignRole('diretor');
        $diretorDed->departamentos()->sync([Departamento::where('sigla', 'DED')->value('id')]);
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($diretorDed)->check('editar', $novo));
    }

    public function test_status_fora_do_enum_e_rejeitado(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-06-06', 'status' => 'invalido', 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasFormErrors(['status']); // o enum do Select barra no getState (belt server-side)
    }

    public function test_criar_sem_editar_forca_rascunho(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar']); // sem agenda.editar

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-04-01', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(AgendaDia::STATUS_RASCUNHO, AgendaDia::whereDate('data', '2027-04-01')->value('status'));
    }

    public function test_criar_com_editar_respeita_status_publicado(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-04-02', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $this->assertSame(AgendaDia::STATUS_PUBLICADO, AgendaDia::whereDate('data', '2027-04-02')->value('status'));
    }

    public function test_criar_rejeita_data_duplicada(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        AgendaDia::factory()->create(['data' => '2027-05-05']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-05-05', 'status' => AgendaDia::STATUS_RASCUNHO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasFormErrors(['data']);
    }

    public function test_lista_mostra_so_o_escopo_do_usuario(): void
    {
        $user = $this->editorDecom(['agenda.ver', 'agenda.criar']);
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');

        $meu = AgendaDia::factory()->create(['meta_dia_titulo' => 'MeuDoDecom']);
        $meu->departamentos()->sync([$decom]);
        $alheio = AgendaDia::factory()->create(['meta_dia_titulo' => 'AlheioDoDed']);
        $alheio->departamentos()->sync([$ded]);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->assertSee('MeuDoDecom')
            ->assertDontSee('AlheioDoDed');
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AgendaContaCriarTest`
Expected: FAIL — componente `AgendaConta` inexistente/incompleto.

- [ ] **Step 3: Escrever o componente (create)**

Create/replace `app/Livewire/Conta/AgendaConta.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Livewire\Conta;

use App\Filament\Schemas\AgendaDiaForm;
use App\Models\AgendaDia;
use App\Support\Agenda\AgendaMantenedores;
use App\Support\Conta\AbaAgenda;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

class AgendaConta extends Component implements HasForms
{
    use AuthorizesRequests, InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $editandoId = null;

    public bool $mostrandoForm = false;

    public function mount(): void
    {
        abort_unless(AbaAgenda::visivelPara(auth()->user()), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(AgendaDiaForm::schema(comDepartamentos: false))
            ->model($this->editandoId ? AgendaDia::find($this->editandoId) : AgendaDia::class)
            ->statePath('data')
            ->operation($this->editandoId ? 'edit' : 'create');
    }

    public function novo(): void
    {
        $this->authorize('criar', AgendaDia::class);
        $this->editandoId = null;
        $this->form->fill();
        $this->mostrandoForm = true;
    }

    public function cancelar(): void
    {
        $this->mostrandoForm = false;
        $this->editandoId = null;
        $this->form->fill();
    }

    public function salvar(): void
    {
        $user = auth()->user();
        $this->authorize('criar', AgendaDia::class); // agenda.criar + departamentos()->exists()

        $dados = $this->form->getState(); // valida required + unique('data') do schema

        // Belt server-side do unique('data') por string Y-m-d (portátil — [[padrao-data-mutator-portavel]]).
        $dataYmd = Carbon::parse($dados['data'])->format('Y-m-d');
        if ($this->dataJaUsada($dataYmd)) {
            $this->addError('data', 'Já existe um dia de agenda nessa data.');

            return;
        }

        // Campo privilegiado STATUS: enum reasserido no servidor + quem não tem agenda.editar não
        // publica na criação (D-F9).
        $dados['status'] = $this->statusValido($dados['status']);
        if (! $user->hasPermissionTo('agenda.editar')) {
            $dados['status'] = AgendaDia::STATUS_RASCUNHO;
        }

        $registro = AgendaDia::create($dados);

        // Campo privilegiado DEPARTAMENTOS forçado: todo novo AgendaDia nasce DED+DECOM (O1).
        $registro->departamentos()->sync(AgendaMantenedores::ids());

        session()->flash('status', 'Dia da agenda criado.');
        $this->redirect(route('conta.agenda'), navigate: true);
    }

    /** Belt server-side do unique('data'): consulta por string Y-m-d (portátil), podendo ignorar um id. */
    private function dataJaUsada(string $dataYmd, ?int $ignorarId = null): bool
    {
        return AgendaDia::query()
            ->where('data', $dataYmd)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    /** Belt do enum de status: nunca confia no POST — valor fora do enum vira rascunho. */
    private function statusValido(?string $status): string
    {
        return in_array($status, [AgendaDia::STATUS_PUBLICADO, AgendaDia::STATUS_RASCUNHO], true)
            ? $status
            : AgendaDia::STATUS_RASCUNHO;
    }

    public function render(): View
    {
        $itens = AgendaDia::noEscopoDe(auth()->user())
            ->orderBy('data', 'desc')
            ->get();

        return view('livewire.conta.agenda-conta', compact('itens'));
    }
}
```

- [ ] **Step 4: Escrever a view do componente (lista + form)**

Create/replace `resources/views/livewire/conta/agenda-conta.blade.php`:

```blade
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15 --}}
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="font-display text-xl font-semibold text-primary">Agenda da Reforma Íntima</h2>
        <button type="button" wire:click="novo"
                class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
            Novo dia
        </button>
    </div>

    @if ($mostrandoForm)
        <section class="rounded-lg bg-white p-6 shadow-card">
            <form wire:submit="salvar" class="space-y-4">
                {{ $this->form }}
                <div class="flex gap-2">
                    <button type="submit" class="rounded-pill bg-primary px-4 py-2 text-sm font-medium text-white">Salvar</button>
                    <button type="button" wire:click="cancelar" class="rounded-pill bg-surface px-4 py-2 text-sm text-text">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    <div class="grid gap-3">
        @forelse ($itens as $item)
            <article class="flex items-center justify-between rounded-lg bg-white p-4 shadow-card">
                <div>
                    <p class="font-medium text-text">{{ $item->data?->format('d/m/Y') }}</p>
                    <p class="text-sm text-text-muted">{{ $item->meta_dia_titulo ?: '—' }}</p>
                </div>
                <span @class([
                    'rounded-pill px-2.5 py-0.5 text-xs font-medium capitalize',
                    'bg-accent/15 text-success' => $item->status === \App\Models\AgendaDia::STATUS_PUBLICADO,
                    'bg-border-muted text-text-secondary' => $item->status !== \App\Models\AgendaDia::STATUS_PUBLICADO,
                ])>{{ $item->status }}</span>
            </article>
        @empty
            <p class="text-sm text-text-muted">Nenhum dia de agenda no seu departamento ainda.</p>
        @endforelse
    </div>
</div>
```

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AgendaContaCriarTest`
Expected: PASS (5 testes).

Run também os testes de acesso (a view real agora existe): `docker compose exec -T app php artisan test --filter=AcessoAgendaContaTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Livewire/Conta/AgendaConta.php tests/Feature/Conta/AgendaContaCriarTest.php`

```bash
git add app/Livewire/Conta/AgendaConta.php resources/views/livewire/conta/agenda-conta.blade.php tests/Feature/Conta/AgendaContaCriarTest.php
git commit -m "feat(agenda): componente AgendaConta — lista escopada + criar com campos forçados"
```

## Task 6: Prova no browser da fundação D1 (DatePicker + RichEditor interativos)

**Files:**
- Verify: navegador (fecha a lacuna do spike — §2.9 problema 3 — que provou save/validação server-side, mas **não** a interação real do DatePicker/RichEditor).

**Interfaces:** nenhuma nova — prova o que as Tasks 2/5 produziram.

- [ ] **Step 1: Subir o ambiente e preparar um editor**

Run (host): `npm run dev`
Garantir um usuário editor de DECOM com `agenda.ver`+`agenda.criar` ligados na matriz (ou, no dev, `docker compose exec -T app php artisan tinker` para `Role::findByName('diretor','web')->syncPermissions([...])` + `assignRole` + `departamentos()->sync` num usuário de teste — **sem** comando destrutivo).

- [ ] **Step 2: Provar a interação no browser**

Autenticar, abrir `/minha-conta/agenda`, "Novo dia" e, **clicando como usuário real**:
- ✅ Abrir o `DatePicker` (painel flutuante `native(false)`), escolher uma data — o campo preenche.
- ✅ Digitar na `Reflexão` (RichEditor), aplicar **negrito** e uma **lista** pela toolbar — formata.
- ✅ Escolher `status`, preencher `meta_dia_titulo`, "Salvar".
- ✅ Voltar à lista e ver o novo dia; conferir no banco (tinker) que nasceu com **DED+DECOM** e `status` conforme a capacidade.
- ✅ Repetir os 4 checks visuais da Task 2 Step 4 (form estilizado; header/footer intactos; sem vazamento).

- [ ] **Step 3: Registrar a evidência (sem screenshots versionados)**

Anotar no PR de D1 o resultado (os screenshots do spike nunca foram versionados — §2.9; registrar em texto o que foi observado). **Não** commitar imagens.

> **Fim de D1.** Rodar a suíte inteira + Pint, abrir o **PR D1** (`feat(fase-d): D1 — fundação da edição da Agenda no site`), **parar para o passe adversarial**. Só seguir para D2 após o merge de D1.

- [ ] **Step 4: Suíte inteira + Pint (fechamento de D1)**

Run: `docker compose exec -T app vendor/bin/pint --test`
Run: `docker compose exec -T app php artisan test`
Expected: verde (ciência [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga — se passam isolados, não é regressão desta fase).

---

# FASE D2 — Vertical completa (editar/excluir + auditoria + correções)

> Empilha sobre D1 (já mergeado). Sem novo risco de CSS. Ao fim: CRUD completo + auditoria (porta 'perfil' + log de depto) + as 3 correções de dados + E2E completo. **PR D2 → passe final → merge.**

## Task 7: Editar + excluir no componente (policy authorize, preserva departamentos)

**Files:**
- Modify: `app/Livewire/Conta/AgendaConta.php` (ações `editar`, `excluir`; ramo de edição no `salvar`)
- Modify: `resources/views/livewire/conta/agenda-conta.blade.php` (botões Editar/Excluir)
- Test: `tests/Feature/Conta/AgendaContaEditarExcluirTest.php`

**Interfaces:**
- Consumes: tudo da Task 5.
- Produces: `AgendaConta::editar(int $id): void`, `AgendaConta::excluir(int $id): void`; `salvar()` passa a tratar create **e** edit; a edição **preserva** os `departamentos` do registro (não sincroniza).

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Conta/AgendaContaEditarExcluirTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Conta;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgendaContaEditarExcluirTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        foreach (['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir']);
    }

    private function editorDe(string $sigla): User
    {
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([Departamento::where('sigla', $sigla)->value('id')]);

        return $user;
    }

    private function agendaEm(array $siglas, array $attrs = []): AgendaDia
    {
        $ag = AgendaDia::factory()->create($attrs);
        $ag->departamentos()->sync(Departamento::whereIn('sigla', $siglas)->pluck('id')->all());

        return $ag;
    }

    public function test_editar_altera_conteudo_e_preserva_departamentos(): void
    {
        $user = $this->editorDe('DECOM');
        $ag = $this->agendaEm(['DED', 'DECOM'], ['meta_dia_titulo' => 'Antigo', 'status' => AgendaDia::STATUS_RASCUNHO]);
        $deptosAntes = $ag->departamentos()->pluck('departamentos.id')->sort()->values()->all();

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $ag->id)
            ->fillForm(['meta_dia_titulo' => 'Novo', 'status' => AgendaDia::STATUS_PUBLICADO])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $ag->refresh();
        $this->assertSame('Novo', $ag->meta_dia_titulo);
        $this->assertSame(AgendaDia::STATUS_PUBLICADO, $ag->status);
        $this->assertSame($deptosAntes, $ag->departamentos()->pluck('departamentos.id')->sort()->values()->all());
    }

    public function test_editar_registro_de_outro_departamento_e_negado(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']); // registro no próprio escopo → mount() passa; o 403 vem do authorize() da AÇÃO
        $alheio = $this->agendaEm(['DED']); // sem interseção com DECOM

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $alheio->id)
            ->assertForbidden();
    }

    public function test_excluir_remove_registro_do_escopo(): void
    {
        $user = $this->editorDe('DECOM');
        $ag = $this->agendaEm(['DED', 'DECOM']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('excluir', $ag->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('agenda_dias', ['id' => $ag->id]);
    }

    public function test_excluir_registro_de_outro_departamento_e_negado(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']); // registro no próprio escopo → mount() passa; o 403 vem do authorize() da AÇÃO
        $alheio = $this->agendaEm(['DED']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('excluir', $alheio->id)
            ->assertForbidden();

        $this->assertDatabaseHas('agenda_dias', ['id' => $alheio->id]);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AgendaContaEditarExcluirTest`
Expected: FAIL — métodos `editar`/`excluir` inexistentes.

- [ ] **Step 3: Adicionar `editar`/`excluir` e o ramo de edição no `salvar`**

Modify `app/Livewire/Conta/AgendaConta.php` — adicionar os métodos e ajustar `salvar()`:

```php
    public function editar(int $id): void
    {
        $registro = AgendaDia::findOrFail($id);
        $this->authorize('editar', $registro); // agenda.editar + interseção de departamento

        $this->editandoId = $registro->id;
        $this->form->fill($registro->attributesToArray());
        $this->mostrandoForm = true;
    }

    public function excluir(int $id): void
    {
        $registro = AgendaDia::findOrFail($id);
        $this->authorize('excluir', $registro); // agenda.excluir + interseção de departamento

        $registro->delete();

        session()->flash('status', 'Dia da agenda excluído.');
        $this->redirect(route('conta.agenda'), navigate: true);
    }
```

Substituir o corpo de `salvar()` para tratar create **e** edit (o ramo de edição **preserva** departamentos):

```php
    public function salvar(): void
    {
        $user = auth()->user();

        if ($this->editandoId) {
            $registro = AgendaDia::findOrFail($this->editandoId);
            $this->authorize('editar', $registro);

            $dados = $this->form->getState();

            // Belt do unique('data') TAMBÉM na edição (ignora o próprio registro).
            $dataYmd = Carbon::parse($dados['data'])->format('Y-m-d');
            if ($this->dataJaUsada($dataYmd, $registro->id)) {
                $this->addError('data', 'Já existe um dia de agenda nessa data.');

                return;
            }

            $dados['status'] = $this->statusValido($dados['status']); // enum reasserido no servidor
            $registro->update($dados);                                // departamentos PRESERVADOS

            session()->flash('status', 'Dia da agenda atualizado.');
            $this->redirect(route('conta.agenda'), navigate: true);

            return;
        }

        // --- Criação (idem Task 5, via os mesmos belts privados) ---
        $this->authorize('criar', AgendaDia::class);
        $dados = $this->form->getState();

        $dataYmd = Carbon::parse($dados['data'])->format('Y-m-d');
        if ($this->dataJaUsada($dataYmd)) {
            $this->addError('data', 'Já existe um dia de agenda nessa data.');

            return;
        }

        $dados['status'] = $this->statusValido($dados['status']);
        if (! $user->hasPermissionTo('agenda.editar')) {
            $dados['status'] = AgendaDia::STATUS_RASCUNHO;
        }

        $registro = AgendaDia::create($dados);
        $registro->departamentos()->sync(AgendaMantenedores::ids());

        session()->flash('status', 'Dia da agenda criado.');
        $this->redirect(route('conta.agenda'), navigate: true);
    }
```

- [ ] **Step 4: Adicionar os botões Editar/Excluir na view**

Modify `resources/views/livewire/conta/agenda-conta.blade.php` — no `<article>` da lista, envolver o `<span>` do status num contêiner com os botões:

```blade
                <div class="flex items-center gap-3">
                    <span @class([
                        'rounded-pill px-2.5 py-0.5 text-xs font-medium capitalize',
                        'bg-accent/15 text-success' => $item->status === \App\Models\AgendaDia::STATUS_PUBLICADO,
                        'bg-border-muted text-text-secondary' => $item->status !== \App\Models\AgendaDia::STATUS_PUBLICADO,
                    ])>{{ $item->status }}</span>
                    <button type="button" wire:click="editar({{ $item->id }})" class="text-sm text-primary hover:underline">Editar</button>
                    <button type="button" wire:click="excluir({{ $item->id }})" wire:confirm="Excluir este dia da agenda?" class="text-sm text-danger hover:underline">Excluir</button>
                </div>
```

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AgendaContaEditarExcluirTest`
Expected: PASS (4 testes). Rodar também `--filter=AgendaContaCriarTest` (regressão do `salvar`): PASS.

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Livewire/Conta/AgendaConta.php tests/Feature/Conta/AgendaContaEditarExcluirTest.php`

```bash
git add app/Livewire/Conta/AgendaConta.php resources/views/livewire/conta/agenda-conta.blade.php tests/Feature/Conta/AgendaContaEditarExcluirTest.php
git commit -m "feat(agenda): editar/excluir no site (policy + preserva departamentos)"
```

## Task 8: Trait `LogsActivity` no `AgendaDia` (7 campos + tapActivity)

**Files:**
- Modify: `app/Models/AgendaDia.php` (trait + `getActivitylogOptions` + `tapActivity`)
- Test: `tests/Feature/Autorizacao/AuditoriaAgendaDiaTest.php`

**Interfaces:**
- Consumes: `AuditoriaAutorizacao::contexto()` (existente).
- Produces: `AgendaDia` passa a logar (log_name `agenda`) os **7 campos** em toda criação/edição/exclusão, com porta/ip/ua no `properties`.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Autorizacao/AuditoriaAgendaDiaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditoriaAgendaDiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_editar_campo_gera_entrada_com_os_7_campos_no_escopo(): void
    {
        $ag = AgendaDia::factory()->create(['status' => AgendaDia::STATUS_RASCUNHO]);
        Activity::query()->delete(); // ignora o 'created' do factory

        $ag->update(['status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>nova</p>']);

        $atividade = Activity::where('log_name', 'agenda')->latest('id')->first();
        $this->assertNotNull($atividade);
        $this->assertSame('updated', $atividade->event);
        $this->assertArrayHasKey('status', $atividade->changes()['attributes']);
        $this->assertArrayHasKey('reflexao', $atividade->changes()['attributes']);
    }

    public function test_save_sem_mudanca_nao_gera_entrada(): void
    {
        $ag = AgendaDia::factory()->create();
        Activity::query()->delete();

        $ag->save(); // nada dirty

        $this->assertSame(0, Activity::where('log_name', 'agenda')->count());
    }

    public function test_entrada_carrega_porta_no_properties(): void
    {
        $ag = AgendaDia::factory()->create();

        $atividade = Activity::where('log_name', 'agenda')->first();
        $this->assertNotNull($atividade);
        $this->assertArrayHasKey('porta', $atividade->properties->toArray());
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaAgendaDiaTest`
Expected: FAIL — `log_name='agenda'` não existe (trait ausente).

- [ ] **Step 3: Adicionar o trait ao model (molde 1:1 do `User`)**

Modify `app/Models/AgendaDia.php`:

Imports (topo):

```php
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
```

Trait na classe:

```php
class AgendaDia extends Model implements TemDepartamento
{
    use HasFactory, LogsActivity;
```

Métodos (ao final da classe):

```php
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('agenda')
            ->logOnly(['data', 'status', 'reflexao', 'meta_mes_texto', 'meta_dia_titulo', 'meta_dia_texto', 'prece'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'dia da agenda criado',
                'updated' => 'dia da agenda atualizado',
                'deleted' => 'dia da agenda excluído',
                default => "dia da agenda {$evento}",
            });
    }

    /** IP + user-agent + porta em toda entrada automática (fonte única: o helper). */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge(AuditoriaAutorizacao::contexto());
    }
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaAgendaDiaTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Models/AgendaDia.php tests/Feature/Autorizacao/AuditoriaAgendaDiaTest.php`

```bash
git add app/Models/AgendaDia.php tests/Feature/Autorizacao/AuditoriaAgendaDiaTest.php
git commit -m "feat(agenda): trait LogsActivity no AgendaDia (7 campos, log_name agenda)"
```

## Task 9: Porta 'perfil' (override + boot) + log manual do depto (`log_name='agenda'`)

**Files:**
- Modify: `app/Support/Autorizacao/AuditoriaAutorizacao.php` (override de porta + `registrar()` com `$logName` + `registrarDepartamentosConteudo`)
- Modify: `app/Livewire/Conta/AgendaConta.php` (`boot()` seta a porta; `salvar()` create loga o depto)
- Modify: `tests/TestCase.php` (tearDown reseta a porta estática — blinda a suíte contra vazamento entre testes)
- Test: `tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php`

**Interfaces:**
- Consumes: `AuditoriaAutorizacao` (existente), `AgendaConta` (Task 5/7).
- Produces: `AuditoriaAutorizacao::usarPorta(?string $porta): void`; `AuditoriaAutorizacao::registrarDepartamentosConteudo(Model $conteudo, array $antes, array $depois): void` (log_name `agenda`); `porta()` lê o override primeiro. O componente da agenda marca `porta='perfil'` no `boot()`.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Autorizacao;

use App\Livewire\Conta\AgendaConta;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditoriaAgendaPortaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
        foreach (['agenda.ver', 'agenda.criar', 'agenda.editar'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        Role::findByName('diretor', 'web')->syncPermissions(['agenda.ver', 'agenda.criar', 'agenda.editar']);
        AuditoriaAutorizacao::usarPorta(null); // isolamento entre testes
    }

    protected function tearDown(): void
    {
        AuditoriaAutorizacao::usarPorta(null);
        parent::tearDown();
    }

    public function test_porta_default_e_sistema_fora_do_painel(): void
    {
        $this->assertSame('sistema', AuditoriaAutorizacao::porta());
    }

    public function test_override_de_porta_vence_o_default(): void
    {
        AuditoriaAutorizacao::usarPorta('perfil');
        $this->assertSame('perfil', AuditoriaAutorizacao::porta());
    }

    public function test_criar_pelo_site_grava_porta_perfil_e_log_de_depto(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        AgendaDia::factory()->create(['data' => '2020-01-01'])->departamentos()->sync([$decom]); // p/ a aba abrir

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('novo')
            ->fillForm(['data' => '2027-07-07', 'status' => AgendaDia::STATUS_PUBLICADO, 'reflexao' => '<p>x</p>'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $novo = AgendaDia::whereDate('data', '2027-07-07')->firstOrFail();

        // Entrada automática do trait (atributos) com porta perfil:
        $auto = Activity::where('log_name', 'agenda')->where('subject_id', $novo->id)->where('event', 'created')->first();
        $this->assertNotNull($auto);
        $this->assertSame('perfil', $auto->properties['porta']);

        // Entrada manual do vínculo de depto, no MESMO log_name 'agenda':
        $manual = Activity::where('log_name', 'agenda')
            ->where('subject_id', $novo->id)
            ->whereNull('event')
            ->first();
        $this->assertNotNull($manual, 'Deve haver a entrada manual do depto (log_name agenda).');
        $this->assertSame('perfil', $manual->properties['porta']);

        // §10.12: os adicionados são exatamente DED+DECOM (mantenedores).
        $adicionados = collect($manual->properties['diff']['adicionados']);
        $idsMantenedores = Departamento::whereIn('sigla', ['DED', 'DECOM'])->pluck('id')->sort()->values()->all();
        $this->assertSame($idsMantenedores, $adicionados->pluck('id')->sort()->values()->all());
    }

    public function test_editar_nao_gera_log_manual_de_depto(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$decom]);
        $ag = AgendaDia::factory()->create(['data' => '2020-02-02']);
        $ag->departamentos()->sync([$ded, $decom]); // no escopo do user (interseção DECOM)

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $ag->id)
            ->fillForm(['meta_dia_titulo' => 'Editado'])
            ->call('salvar')
            ->assertHasNoFormErrors();

        // Edição NÃO mexe em depto ⇒ nenhuma entrada manual (só a 'updated' automática do trait).
        $manual = Activity::where('log_name', 'agenda')->where('subject_id', $ag->id)->whereNull('event')->count();
        $this->assertSame(0, $manual);
    }

    public function test_porta_reflete_o_painel_quando_sem_override(): void
    {
        AuditoriaAutorizacao::usarPorta(null);
        $painel = \Filament\Facades\Filament::getDefaultPanel(); // painel /admin
        \Filament\Facades\Filament::setCurrentPanel($painel);

        $this->assertSame($painel->getId(), AuditoriaAutorizacao::porta()); // 'admin'
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaAgendaPortaTest`
Expected: FAIL — `usarPorta`/`registrarDepartamentosConteudo` inexistentes; porta não vira 'perfil'.

- [ ] **Step 3: Estender o helper (override + logName + registrarDepartamentosConteudo)**

Modify `app/Support/Autorizacao/AuditoriaAutorizacao.php`:

Adicionar a propriedade + setter e ajustar `porta()`:

```php
    /** Porta forçada pelo contexto (ex.: 'perfil' no /minha-conta, que não é painel Filament). */
    private static ?string $portaForcada = null;

    /** Marca a porta corrente (setado no boot() do componente do site). Reset com null. */
    public static function usarPorta(?string $porta): void
    {
        self::$portaForcada = $porta;
    }

    /** Painel corrente: override > painel Filament > 'sistema'. Nunca cai no default por acidente. */
    public static function porta(): string
    {
        return self::$portaForcada ?? Filament::getCurrentPanel()?->getId() ?? 'sistema';
    }
```

(Remover a versão antiga de `porta()`.)

Adicionar o método público para conteúdo (subject = o conteúdo, log_name 'agenda'):

```php
    /**
     * Vínculo depto↔conteúdo: subject = o conteúdo; log_name = 'agenda' (mesma trilha do trait,
     * §8.3 do spec). Diff por id, itens {id, nome} (estável a rename).
     *
     * @param  array<int, string>  $antes  [id => nome] antes do sync
     * @param  array<int, string>  $depois  [id => nome] depois do sync
     */
    public static function registrarDepartamentosConteudo(Model $conteudo, array $antes, array $depois): void
    {
        $idsAdicionados = array_diff(array_keys($depois), array_keys($antes));
        $idsRemovidos = array_diff(array_keys($antes), array_keys($depois));

        $diff = [
            'adicionados' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $depois[$id]], $idsAdicionados)),
            'removidos' => array_values(array_map(fn (int $id): array => ['id' => $id, 'nome' => $antes[$id]], $idsRemovidos)),
        ];

        self::registrar($conteudo, 'departamentos do conteúdo alterados', $diff, logName: 'agenda');
    }
```

Ajustar a assinatura do `registrar()` privado para aceitar `$logName` (default preserva os callers de usuário):

```php
    /** Escreve 1 entrada; no-op se o diff for vazio. */
    private static function registrar(Model $subject, string $descricao, array $diff, string $logName = self::LOG): void
    {
        if (empty($diff['adicionados']) && empty($diff['removidos'])) {
            return;
        }

        activity($logName)
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->withProperties(['diff' => $diff] + self::contexto())
            ->log($descricao);
    }
```

- [ ] **Step 4: Marcar a porta no `boot()` e logar o depto no create**

Modify `app/Livewire/Conta/AgendaConta.php`:

Adicionar `use App\Models\Departamento;` e o `boot()` (roda em mount **e** hydration — cobre o `/livewire/update` do save):

```php
    public function boot(): void
    {
        // /minha-conta não é painel Filament → porta cairia em 'sistema'. Marcar 'perfil'
        // explicitamente, em toda requisição do componente (inclui o /livewire/update do save).
        AuditoriaAutorizacao::usarPorta('perfil');
    }
```

No ramo de **criação** do `salvar()`, após o `sync` dos mantenedores, registrar o vínculo:

```php
        $registro = AgendaDia::create($dados);

        $idsMantenedores = AgendaMantenedores::ids();
        $registro->departamentos()->sync($idsMantenedores);

        // Log manual do vínculo depto↔conteúdo (o trait não captura N:N), log_name 'agenda'.
        $depois = Departamento::whereIn('id', $idsMantenedores)->pluck('nome', 'id')->all();
        AuditoriaAutorizacao::registrarDepartamentosConteudo($registro, antes: [], depois: $depois);
```

Adicionar `use App\Support\Autorizacao\AuditoriaAutorizacao;` no topo.

- [ ] **Step 4b: Blindar a suíte — resetar a porta estática no `tearDown()` base**

A porta é estado **estático** (`$portaForcada`); a recriação da app entre testes **não** limpa estático, e o
`boot()` do `AgendaConta` seta `'perfil'` — que vazaria para testes seguintes (bomba por ordem: quebra com
`executionOrder=random`/paratest). Resetar no `tearDown()` da **base** cobre a suíte inteira (1 lugar; não
confiar na ordem de descoberta).

Modify `tests/TestCase.php` — adicionar/ajustar `tearDown()` para resetar **antes** do `parent::tearDown()`:

```php
    protected function tearDown(): void
    {
        \App\Support\Autorizacao\AuditoriaAutorizacao::usarPorta(null);

        parent::tearDown();
    }
```

(Se `tests/TestCase.php` já tem `tearDown`, inserir a linha de reset no início; senão, adicionar o método.)

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AuditoriaAgendaPortaTest`
Expected: PASS (3 testes de porta/log + os 2 acrescidos = 5 no total desta classe).

Rodar a regressão do helper e do trait: `docker compose exec -T app php artisan test --filter=AuditoriaHelperTest --filter=AuditoriaAgendaDiaTest`
Expected: PASS (o `$logName` default preserva os callers de usuário).

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Support/Autorizacao/AuditoriaAutorizacao.php app/Livewire/Conta/AgendaConta.php tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php`

```bash
git add app/Support/Autorizacao/AuditoriaAutorizacao.php app/Livewire/Conta/AgendaConta.php tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php
git commit -m "feat(agenda): porta 'perfil' via boot() + log manual do depto (log_name agenda)"
```

## Task 10: Comando `cema:corrigir-papel-diretores` (correção a)

**Files:**
- Create: `app/Console/Commands/CorrigirPapelDiretores.php`
- Test: `tests/Feature/Console/CorrigirPapelDiretoresTest.php`

**Interfaces:**
- Produces: comando `cema:corrigir-papel-diretores` — promove a `diretor` quem ocupa cargo de diretor com departamento (`cargos.departamento_id NOT NULL`, `institucional=false`) e ainda tem papel `trabalhador`. Idempotente; audita via `registrarPapelUsuario`.

- [ ] **Step 1: Escrever o teste que falha**

Create `tests/Feature/Console/CorrigirPapelDiretoresTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\Cargo;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrigirPapelDiretoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_promove_trabalhador_com_cargo_de_diretor_para_diretor(): void
    {
        $cargoDed = Cargo::where('slug', 'diretor-do-ded')->firstOrFail(); // departamento_id NOT NULL, institucional false
        $user = User::factory()->create();
        $user->assignRole('trabalhador');
        $user->cargos()->sync([$cargoDed->id]);

        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('diretor'));
        $this->assertFalse($user->hasRole('trabalhador'));
    }

    public function test_idempotente_e_nao_promove_quem_nao_deve(): void
    {
        $cargoDed = Cargo::where('slug', 'diretor-do-ded')->firstOrFail();
        $alvo = User::factory()->create();
        $alvo->assignRole('trabalhador');
        $alvo->cargos()->sync([$cargoDed->id]);

        $intocado = User::factory()->create(); // trabalhador sem cargo de diretor
        $intocado->assignRole('trabalhador');

        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful();
        $this->artisan('cema:corrigir-papel-diretores')->assertSuccessful(); // 2ª vez: no-op

        $this->assertTrue($alvo->refresh()->hasRole('diretor'));
        $this->assertFalse($alvo->hasRole('trabalhador'));
        $this->assertTrue($intocado->refresh()->hasRole('trabalhador'));
        $this->assertFalse($intocado->hasRole('diretor'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CorrigirPapelDiretoresTest`
Expected: FAIL — comando `cema:corrigir-papel-diretores` não existe.

- [ ] **Step 3: Criar o comando**

Create `app/Console/Commands/CorrigirPapelDiretores.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9a): quem ocupa cargo de DIRETOR com departamento (cargos.departamento_id
 * NOT NULL, institucional=false) mas ainda tem papel 'trabalhador' é promovido a 'diretor'.
 * Filtro SEMÂNTICO (não hardcode de nome). No dev, o único desalinhado é o Valdemarques (§3.1).
 * Idempotente; audita a troca de papel. NUNCA destrutivo.
 */
class CorrigirPapelDiretores extends Command
{
    protected $signature = 'cema:corrigir-papel-diretores';

    protected $description = 'Promove a diretor quem tem cargo de diretor com departamento mas ainda é trabalhador (idempotente).';

    public function handle(): int
    {
        $usuarios = User::role('trabalhador')
            ->whereHas('cargos', fn ($q) => $q->whereNotNull('departamento_id')->where('institucional', false))
            ->get();

        $corrigidos = 0;
        foreach ($usuarios as $usuario) {
            $antes = $usuario->roles->pluck('name')->all();
            $usuario->syncRoles(['diretor']);
            AuditoriaAutorizacao::registrarPapelUsuario($usuario, $antes, ['diretor']);
            $corrigidos++;
        }

        $this->info(sprintf('%d usuário(s) promovido(s) a diretor.', $corrigidos));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=CorrigirPapelDiretoresTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Console/Commands/CorrigirPapelDiretores.php tests/Feature/Console/CorrigirPapelDiretoresTest.php`

```bash
git add app/Console/Commands/CorrigirPapelDiretores.php tests/Feature/Console/CorrigirPapelDiretoresTest.php
git commit -m "feat(agenda): comando cema:corrigir-papel-diretores (correção a, idempotente)"
```

## Task 11: Comando `cema:somar-ded-agenda` (correção b)

**Files:**
- Create: `app/Console/Commands/SomarDedAgenda.php`
- Test: `tests/Feature/Console/SomarDedAgendaTest.php`

**Interfaces:**
- Produces: comando `cema:somar-ded-agenda` — para cada `AgendaDia` que já tem DECOM, **soma** DED (`syncWithoutDetaching`), preservando DECOM. Idempotente. **Não** auditado (dado de cutover — §9/D-F4).

- [ ] **Step 1: Escrever o teste que falha**

Create `tests/Feature/Console/SomarDedAgendaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\AgendaDia;
use App\Models\Departamento;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SomarDedAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_soma_ded_preservando_decom(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $comDecom = AgendaDia::factory()->create();
        $comDecom->departamentos()->sync([$decom]);

        $this->artisan('cema:somar-ded-agenda')->assertSuccessful();

        $ids = $comDecom->departamentos()->pluck('departamentos.id')->all();
        $this->assertContains($decom, $ids);
        $this->assertContains($ded, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_idempotente_e_ignora_quem_nao_tem_decom(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $das = Departamento::where('sigla', 'DAS')->value('id');
        $comDecom = AgendaDia::factory()->create();
        $comDecom->departamentos()->sync([$decom]);
        $semDecom = AgendaDia::factory()->create();
        $semDecom->departamentos()->sync([$das]);

        $this->artisan('cema:somar-ded-agenda')->assertSuccessful();
        $this->artisan('cema:somar-ded-agenda')->assertSuccessful(); // 2ª vez: sem duplicar

        $this->assertSame([$ded, $decom], $comDecom->departamentos()->orderByRaw('departamentos.id')->pluck('departamentos.id')->sort()->values()->all());
        $this->assertSame([$das], $semDecom->departamentos()->pluck('departamentos.id')->all()); // intocado
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=SomarDedAgendaTest`
Expected: FAIL — comando inexistente.

- [ ] **Step 3: Criar o comando**

Create `app/Console/Commands/SomarDedAgenda.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\AgendaDia;
use App\Models\Departamento;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9b): os 123 AgendaDia estão só em DECOM. Este comando SOMA o DED
 * (syncWithoutDetaching) a cada AgendaDia que já tem DECOM — preserva o DECOM, não migra.
 * Assim DED e DECOM editam TODA a Agenda (decisão 6). Idempotente. Dado de cutover: NÃO auditado.
 */
class SomarDedAgenda extends Command
{
    protected $signature = 'cema:somar-ded-agenda';

    protected $description = 'Soma o DED ao N:N de cada AgendaDia que já tem DECOM (preserva DECOM), idempotente.';

    public function handle(): int
    {
        $decomId = Departamento::where('sigla', 'DECOM')->value('id');
        $dedId = Departamento::where('sigla', 'DED')->value('id');

        if (! $decomId || ! $dedId) {
            $this->error('Departamentos DED/DECOM não encontrados — rode o EstruturaCemaSeeder.');

            return self::FAILURE;
        }

        $registros = AgendaDia::whereHas('departamentos', fn ($q) => $q->where('departamentos.id', $decomId))->get();

        foreach ($registros as $registro) {
            $registro->departamentos()->syncWithoutDetaching([$dedId]);
        }

        $this->info(sprintf('%d dia(s) da agenda com DED somado (DECOM preservado).', $registros->count()));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=SomarDedAgendaTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Console/Commands/SomarDedAgenda.php tests/Feature/Console/SomarDedAgendaTest.php`

```bash
git add app/Console/Commands/SomarDedAgenda.php tests/Feature/Console/SomarDedAgendaTest.php
git commit -m "feat(agenda): comando cema:somar-ded-agenda (correção b, soma DED preservando DECOM)"
```

## Task 12: Comando `cema:vincular-presidentes-departamentos` (correção c)

**Files:**
- Create: `app/Console/Commands/VincularPresidentesDepartamentos.php`
- Test: `tests/Feature/Console/VincularPresidentesDepartamentosTest.php`

**Interfaces:**
- Produces: comando `cema:vincular-presidentes-departamentos` — usuários com o cargo `diretor_presidente` recebem os **8** departamentos (`syncWithoutDetaching`). Idempotente; audita via `registrarDepartamentosUsuario`.

- [ ] **Step 1: Escrever o teste que falha**

Create `tests/Feature/Console/VincularPresidentesDepartamentosTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VincularPresidentesDepartamentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_vincula_presidente_aos_8_departamentos(): void
    {
        $presidente = User::factory()->create();
        $presidente->cargos()->sync([Cargo::where('slug', 'presidente')->value('id')]);

        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();

        $this->assertSame(Departamento::count(), $presidente->departamentos()->count());
        $this->assertSame(8, $presidente->departamentos()->count());
    }

    public function test_idempotente_e_ignora_nao_presidentes(): void
    {
        $presidente = User::factory()->create();
        $presidente->cargos()->sync([Cargo::where('slug', 'presidente')->value('id')]);
        $outro = User::factory()->create();
        $outro->cargos()->sync([Cargo::where('slug', 'diretor-do-ded')->value('id')]);

        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();
        $this->artisan('cema:vincular-presidentes-departamentos')->assertSuccessful();

        $this->assertSame(8, $presidente->refresh()->departamentos()->count());
        $this->assertSame(0, $outro->refresh()->departamentos()->count()); // não é presidente
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=VincularPresidentesDepartamentosTest`
Expected: FAIL — comando inexistente.

- [ ] **Step 3: Criar o comando**

Create `app/Console/Commands/VincularPresidentesDepartamentos.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\User;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use Illuminate\Console\Command;

/**
 * Correção de dado (§9c): materializa "presidente edita tudo" (decisão de Fase C). Usuários
 * com o cargo institucional 'diretor_presidente' (slug 'presidente') recebem os 8 departamentos
 * (syncWithoutDetaching). O backfill VincularDiretoresDepartamento não os alcança (cargo sem
 * departamento_id). Idempotente; audita o vínculo. NUNCA destrutivo.
 */
class VincularPresidentesDepartamentos extends Command
{
    protected $signature = 'cema:vincular-presidentes-departamentos';

    protected $description = 'Vincula os presidentes (cargo diretor_presidente) aos 8 departamentos (idempotente).';

    public function handle(): int
    {
        $todosIds = Departamento::pluck('id')->all();
        $todosNomes = Departamento::pluck('nome', 'id')->all();

        $presidentes = User::whereHas('cargos', fn ($q) => $q->where('cargos.slug', 'presidente'))->get();

        foreach ($presidentes as $presidente) {
            $antes = $presidente->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();
            $presidente->departamentos()->syncWithoutDetaching($todosIds);
            AuditoriaAutorizacao::registrarDepartamentosUsuario($presidente, $antes, $todosNomes);
        }

        $this->info(sprintf('%d presidente(s) vinculado(s) aos %d departamentos.', $presidentes->count(), count($todosIds)));

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=VincularPresidentesDepartamentosTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Console/Commands/VincularPresidentesDepartamentos.php tests/Feature/Console/VincularPresidentesDepartamentosTest.php`

```bash
git add app/Console/Commands/VincularPresidentesDepartamentos.php tests/Feature/Console/VincularPresidentesDepartamentosTest.php
git commit -m "feat(agenda): comando cema:vincular-presidentes-departamentos (correção c, idempotente)"
```

## Task 13: Fechamento de D2 — E2E editar/excluir no browser + suíte + cutover

**Files:**
- Verify: navegador (editar/excluir interativos) + suíte inteira + Pint.

**Interfaces:** nenhuma nova.

- [ ] **Step 1: Rodar as 3 correções no dev (idempotentes, não destrutivas)**

Run: `docker compose exec -T app php artisan cema:corrigir-papel-diretores`
Run: `docker compose exec -T app php artisan cema:somar-ded-agenda`
Run: `docker compose exec -T app php artisan cema:vincular-presidentes-departamentos`
Expected: cada uma reporta a contagem; rodar 2× confirma idempotência (segunda execução não muda nada).

- [ ] **Step 2: Cutover manual da matriz (precondição de runtime — §2.4/D-F5)**

No `/admin/matriz-capacidades`, ligar `agenda.ver`/`agenda.criar`/`agenda.editar`/`agenda.excluir` para os papéis `diretor` e `trabalhador`. **Sem** este passo o piloto não morde (não há 4º comando — ligar a matriz por código violaria a invariante da Fase C "a matriz é o único escritor de `role_has_permissions`"). Registrar no PR que é passo de cutover.

- [ ] **Step 3: Provar editar/excluir no browser**

Como editor de DECOM (agora com DED somado à agenda), abrir `/minha-conta/agenda`:
- ✅ Editar um dia: mudar `meta_dia_titulo` e `status`, salvar; conferir que os departamentos **não** mudaram (tinker) e que a auditoria gravou `porta='perfil'`.
- ✅ Excluir um dia (com o `wire:confirm`); some da lista e do banco.
- ✅ Tentar (via URL/estado forjado) editar um dia de outro departamento ⇒ negado (403).

- [ ] **Step 4: Suíte inteira + Pint (fechamento de D2)**

Run: `docker compose exec -T app vendor/bin/pint --test`
Run: `docker compose exec -T app php artisan test`
Expected: verde (ciência [[flaky-importadorblog-gd-cap-imagem]]).

- [ ] **Step 5: Abrir o PR D2 e parar para o passe final**

Abrir o **PR D2** (`feat(fase-d): D2 — vertical completa da edição da Agenda no site`), documentando o passo de cutover manual da matriz (Step 2). **Parar para o passe adversarial final** antes do merge ([[merge-so-com-ci-verde-no-commit-final]]: só mesclar com o CI verde no último commit).

---

## Self-Review (checklist do autor)

**1. Cobertura do spec** (§ → task):
- §4.1 (schema fonte única) → Task 1. §4.2 (tema escopado) → Task 2. §5 (rota/nav/componente) → Tasks 4, 5. §6 (escopo/acesso/policy) → Tasks 3, 4, 5, 7. §7 (campos privilegiados: departamentos DED+DECOM O1, status D-F9, unique belt) → Tasks 5, 7. §8 (auditoria: trait + porta + log depto O2) → Tasks 8, 9. §9 (3 correções) → Tasks 10, 11, 12. §10 (testes 1-16) → distribuídos (1→T1, 4/5→T4, 6→T5, 7→T5/T7, 8→T5, 10→T8, 11→T9, 12→T9, 13-15→T10-12, 3/16→T2/T6/T13). Cutover da matriz (§2.4/D-F5) → Task 13 Step 2. **Sem lacuna.**
- Fora de escopo (§11) respeitado: sem Filament Table, sem viewer de auditoria, sem tocar policies/trait/pivôs/Evento.

**2. Placeholders:** nenhum "TODO"/"similar a"/"tratar erros" — todo step tem código real ou procedimento de browser concreto. As duas tasks de browser (2, 6, 13) têm verificação explícita item-a-item (não é placeholder; é o tipo de prova correto para CSS/JS).

**3. Consistência de tipos/nomes:** `AgendaDiaForm::schema(bool $comDepartamentos = true)` (T1) usado com `comDepartamentos: false` em T5. `AgendaDia::noEscopoDe($user)` (T3) usado em T5/render e AbaAgenda. `AgendaMantenedores::ids()` (T3) usado em T5/T9. `AbaAgenda::visivelPara` (T3) usado em T4/T5. `AuditoriaAutorizacao::usarPorta`/`registrarDepartamentosConteudo` (T9) usados no boot/salvar. `dataJaUsada`/`statusValido` (privados, T5) reusados no ramo de edição (T7). `salvar()` create (T5) reescrito em T7 para create+edit — mesma assinatura pública. Nav chama `AbaAgenda::visivelPara` (T4) — mesma classe de T3.

**4. Verificação adversarial (workflow, pré-entrega):** 11/11 suposições de API confirmadas OK contra o vendor (Livewire `assertForbidden` em ação que dá `authorize()` falho; `Select::getName()`; `attributesToArray()`→DatePicker round-trip; `@filamentScripts` fora do painel; `activity($logName)…log()` grava `log_name` e deixa `event` NULL; `changes()['attributes']`; `User::role()` scope; slugs de cargo `presidente`/`diretor-do-ded`; `syncRoles`). O tema (T2) ficou INCERTO (plausível, prova no browser) → adicionada a linha `@layer …` para fixar a cascata. **Correções aplicadas após o crítico de cobertura:** (a) **regressão da nav** — `AbaAgenda` blinda `PermissionDoesNotExist` (fail-closed) + memoiza por request (WeakMap); (b) **testes "negado" da T7** — registro no próprio escopo p/ o `mount()` passar (403 vem do `authorize()` da ação); (c) **belt do `unique('data')`** agora no create **e** no edit, por `where('data',$ymd)` portátil ([[padrao-data-mutator-portavel]]); (d) **belt do enum de `status`** + teste §10.8; (e) testes de auditoria reforçados (§10.11 porta `admin`; §10.12 adicionados==DED+DECOM e no-op na edição) e §10.7 (Gate cross-departamento). Sem inconsistência de nome/assinatura (cruzado contra o código real).

## Handoff

Este plano vai ao **passe adversarial** antes da execução (fluxo do projeto: SPEC ✅ → plano → **passe** → execução → PR → passe final → merge). **Não** inicio a execução agora.

Após o passe aprovar o plano, a execução recomendada é **subagent-driven** (superpowers:subagent-driven-development): um subagente por task, com review entre tasks; o controller roda a suíte completa nos checkpoints (subagentes rodam `--filter` focado — [[sdd-subagente-teste-focado]]). D1 é um PR; D2 é outro.
