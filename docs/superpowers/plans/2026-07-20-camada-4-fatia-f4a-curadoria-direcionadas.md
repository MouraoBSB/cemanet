# Camada 4 · Fatia F4a — Curadoria de Direcionadas no /admin (campo de destinatários) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir ao admin criar/editar uma mensagem **direcionada COMPLETA** pelo `/admin` — acrescentando o único campo que falta, **Destinatários**, condicional ao nível, com a integridade "direcionada ⇔ tem destinatário" (≥1 obrigatório; não-direcionada ⇒ pivô vazio) e um contador/filtro na tabela. **Sem migration** (o pivô `mensagem_destinatario` veio da 3A).

**Architecture:** Uma **Section própria "Destinatários"** no `MensagemResource`, com a Section inteira `->visible(fn (Get) => nivel===Direcionada)` (O3) e o campo `Select destinatarios` (`->options` de `User`, `->required(fn (Get))` condicional + `->minItems(1)` — O4). O nível vira `->live()`. A sincronização do pivô é **manual e determinística** (O1 = Opção B): um trait `SincronizaDestinatarios` (molde `SincronizaRelacionadas`) que **captura** `destinatarios` fora do `$data` com um **guard de nível** (só direcionada carrega destinatário; qualquer outro nível ⇒ conjunto vazio) e **aplica** via `->sync()` no after-hook; no edit, `mutateFormDataBeforeFill` hidrata o Select do pivô. Um `TextColumn destinatarios_count` + filtro "tem destinatário" (O2) na tabela. **Só escreve** o pivô que a 3A/3C já leem — não toca resolvedor, front, `/minha-conta`, sitemap, Autores nem o importador.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 (v5.6) · Livewire · MySQL. Docker (`docker compose exec -T app php artisan ...` — o projeto **não** usa Sail). **Sem** Node no container (npm/Vite no host — mas esta fatia **não** tem asset novo).

**Spec:** [docs/superpowers/specs/2026-07-20-camada-4-fatia-f4a-curadoria-direcionadas-admin.md](../specs/2026-07-20-camada-4-fatia-f4a-curadoria-direcionadas-admin.md) (✅ aprovada no passe do consultor; O1–O4 fechados). Todas as referências `§N`/`I#` abaixo são a esse spec.

**PR único (sem split):** F4a é cirúrgica no `MensagemResource` + páginas Create/Edit + 1 trait + testes; sem migration, sem CSS, sem front. Um só PR: `feat(camada-4-fatia-f4a): campo de destinatários no /admin (direcionada completa)`.

**Passe interno do plano (20/jul):** 3 verificadores adversariais (APIs de teste v5 · código de produção/fronteiras · cobertura/RED) rechecaram este plano contra o código real **e o vendor do Filament v5** — veredito **sólido, zero bloqueador**. Provado no vendor que **todas** as APIs de teste usadas existem (`assertFormFieldVisible`/`Hidden` refletem a Section oculta via `getFlatFields(withHidden:false)`; `assertFormSet` compara `getState()`; `filterTable`/`assertCanSeeTableRecords`; `->counts()`→`destinatarios_count`), com moldes verdes no repo (`PalestraResourceTest::test_edit_preenche_selects_a_partir_do_pivo`, `AssuntoResource` `children_count`). Confirmado que a Section oculta **poda** os filhos da validação (garante I5 + neutralidade dos testes 2A), que o harness de classe anônima do guard compila (método `protected` chamado no mesmo escopo), e que o `pluck('users.id')` do fill está certo. Ajuste incorporado: I5 cobre `publico`+`trabalhadores` (o `null` fica na regressão 2A). Segue para o **passe do consultor** antes da execução.

## Global Constraints

- **pt-BR em tudo**: código (identificadores de domínio), comentários, mensagens de UI, commits.
- **Cabeçalho de autoria** em todo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20`.
- **0 migrations** — o pivô `mensagem_destinatario` já existe (3A). **Nunca** `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed/factory destrutivo (o dev tem mensagens + 73 vínculos + palestras/posts/mídia importados — [[nunca-migrate-fresh-no-dev]]). Conexão `legado` é **read-only** (SELECT). Todo brief de subagente que rode `artisan` reafirma isto.
- **F4a é admin-only**: **toda** autorização por papel/setor é F4b. **Não** tocar `/minha-conta` (rotas `conta.*`, `ContaController`, `AbaDirecionadas`), o resolvedor 3A (`podeSerVistoPor`/`visiveisPara`/`visibilidade`/enum), a aba/lista da 3C, a barreira/single da 3B, o front público, o sitemap, Autores, o importador `cema:importar-direcionadas`, o `Mensagem` model (a relação `destinatarios()` já existe), nem o núcleo de capacidade (policies/matriz — inerte).
- **O1 = Opção B (sync MANUAL):** trait `SincronizaDestinatarios` (`capturar` com guard de nível + `aplicar` via `->sync()`). **Não** usar `->relationship()` no campo (um `->relationship()` oculto **não** esvazia o pivô — vendor `.../BelongsToModel.php:23,51-53`).
- **O4 = `->required(fn (Get))` condicional + `->minItems(1)`** no campo; **O3 = Section própria** com `->visible(fn (Get))` na Section inteira; **O2 = coluna `destinatarios_count` + filtro "tem destinatário"** na tabela (entregues, não opcionais).
- **`Get`** = `Filament\Schemas\Components\Utilities\Get` (namespace do Filament v5 — confirmado em `EventoForm`/`PalestraResource`). **Não** confundir com `Filament\Forms\Get`.
- **APIs de teste Filament v5 (confirmar no 1º RED de cada task):** `assertFormFieldExists('x', fn (Select $f) => $f->isMultiple()/$f->isLive())`; `assertFormFieldVisible`/`assertFormFieldHidden` (preferir estes aos `...IsVisible`/`...IsHidden` — o `...IsVisible` é `@deprecated`); `assertFormSet([...])`; `assertHasFormErrors([...])`/`assertHasNoFormErrors`; tabela: `assertTableColumnExists`, `filterTable`, `assertCanSeeTableRecords`/`assertCanNotSeeTableRecords`.
- **Testes**: `docker compose exec -T app php artisan test --filter=<Nome>` por task; **suíte inteira** no fechamento. **Pint antes de qualquer commit**: `docker compose exec -T app vendor/bin/pint <arquivos>` ([[pint-antes-de-push]]).
- **Cada commit** termina com o trailer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **Ordem (§9.0)**: form condicional (Task 1) → guard unit (Task 2) → plugagem+persistência (Task 3) → coluna/filtro (Task 4) → suíte+browser+PR (Task 5). **Dois testes-do-vermelho não-vacuous:** I2 (salvar direcionada sem destinatário reprova **sem** o `->required`) e I4-guard (o trait devolve `[]` para não-direcionada **sem** o guard de nível).
- **`SincronizaDestinatarios` é um trait de Page** (`namespace App\Filament\Resources\Mensagens\Pages`) — molde `SincronizaRelacionadas`. Seus métodos são `protected` ⇒ o teste-unidade usa **harness** (classe anônima que `use` o trait e expõe o método).

---

## Task 1: Section "Destinatários" condicional + `->live()` no nível (o form)

**Files:**
- Modify: `app/Filament/Resources/Mensagens/MensagemResource.php` (`+->live()` no Select `nivel` `:115-118`; `+Section "Destinatários"` após a Section "Autoria e relações" `:159`; `+imports` `VisibilidadeMensagem`, `Get`)
- Test: `tests/Feature/Filament/MensagemDestinatariosFormTest.php`

**Interfaces:**
- Consumes: `MensagemResource::NIVEIS` (tem `'direcionada'`), `VisibilidadeMensagem::Direcionada->value` (`'direcionada'`), `\App\Models\User`.
- Produces: no form, um campo `destinatarios` (`Select` múltiplo) dentro de uma Section `->visible` ao nível Direcionada; o `Select` `nivel` agora `->live()`; `->required(fn (Get))` condicional no campo. **Ainda sem persistência** (a sincronização é da Task 3).

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Filament/MensagemDestinatariosFormTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Models\Mensagem;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_nivel_e_live_e_destinatarios_e_multiplo(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => $f->isLive())
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => $f->isMultiple());
    }

    public function test_destinatarios_visivel_so_quando_direcionada(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldVisible('destinatarios')
            ->fillForm(['nivel' => 'publico'])
            ->assertFormFieldHidden('destinatarios')
            ->fillForm(['nivel' => null])
            ->assertFormFieldHidden('destinatarios');
    }

    /** VERMELHO #1 (I2): salvar direcionada SEM destinatário reprova o required condicional (não persiste). */
    public function test_direcionada_sem_destinatario_reprova(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'A direcionar',
                'slug' => 'a-direcionar',
                'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO,
                'nivel' => 'direcionada',
                'destinatarios' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertDatabaseMissing('mensagens', ['slug' => 'a-direcionar']);
    }

    /**
     * I5: qualquer nível NÃO-direcionado sem destinatário salva (required é condicional).
     * Cobre 'publico' e 'trabalhadores' (§9.2); o caso nivel=null já é coberto pela regressão
     * do MensagemResourceTest (os creates existentes nascem com nivel default null).
     */
    public function test_nao_direcionada_sem_destinatario_salva(): void
    {
        foreach (['publico', 'trabalhadores'] as $nivel) {
            Livewire::test(CreateMensagem::class)
                ->fillForm([
                    'titulo' => "Sem destino {$nivel}",
                    'slug' => "sem-destino-{$nivel}",
                    'formato' => 'psicografia',
                    'status' => Mensagem::STATUS_PUBLICADO,
                    'nivel' => $nivel,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('mensagens', ['slug' => "sem-destino-{$nivel}", 'nivel' => $nivel]);
        }
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosFormTest`
Expected: FAIL — `destinatarios` não existe no form (`assertFormFieldExists`/`assertFormFieldVisible` falham) e `test_direcionada_sem_destinatario_reprova` **não** vê erro em `destinatarios` (o `->required` ainda não existe). Confirma os nomes exatos dos asserts de visibilidade do Filament v5 aqui (`assertFormFieldVisible`/`assertFormFieldHidden`); se a API divergir, ajustar antes de seguir.

- [ ] **Step 3: Implementar as mudanças no form**

Modify `app/Filament/Resources/Mensagens/MensagemResource.php`:

**(a)** Adicionar os imports (junto aos demais `use`):

```php
use App\Enums\VisibilidadeMensagem;
use Filament\Schemas\Components\Utilities\Get;
```

**(b)** Tornar o Select `nivel` `->live()` (`:115-118`):

```php
                        Select::make('nivel')
                            ->label('Nível de acesso')
                            ->options(self::NIVEIS)
                            ->live() // pré-requisito do visible da Section Destinatários / required condicional
                            ->helperText('Só as Públicas aparecem no site (por ora). A visibilidade rica virá na próxima fase.'),
```

**(c)** Acrescentar a Section "Destinatários" **após** a Section "Autoria e relações" (após o `]),` que fecha essa Section, `:159`, e **antes** da Section "Pictografia" `:161`):

```php
                Section::make('Destinatários')
                    ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
                    ->visible(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
                    ->schema([
                        Select::make('destinatarios')
                            ->label('Destinatários')
                            ->helperText('Obrigatório para mensagens de nível "Direcionada".')
                            ->options(fn () => \App\Models\User::orderBy('name')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->required(fn (Get $get): bool => $get('nivel') === VisibilidadeMensagem::Direcionada->value)
                            ->minItems(1)
                            ->columnSpanFull(),
                    ]),
```

> `Section` e `Select` **já estão importados** no arquivo. Não usar `->relationship()` (O1). O `->options()` sobre ~145 users é client-side (molde `ids_palestrantes`).

- [ ] **Step 4: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosFormTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Filament/Resources/Mensagens/MensagemResource.php tests/Feature/Filament/MensagemDestinatariosFormTest.php`

```bash
git add app/Filament/Resources/Mensagens/MensagemResource.php tests/Feature/Filament/MensagemDestinatariosFormTest.php
git commit -m "feat(camada-4-fatia-f4a): Section Destinatarios condicional + nivel ->live() no form"
```

---

## Task 2: Trait `SincronizaDestinatarios` — o guard determinístico (unit)

**Files:**
- Create: `app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php`
- Test: `tests/Feature/Filament/MensagemDestinatariosGuardTest.php`

**Interfaces:**
- Consumes: `VisibilidadeMensagem::Direcionada->value`, `Mensagem::destinatarios()` (só no `aplicar`, não exercitado aqui).
- Produces: `trait SincronizaDestinatarios` com `protected capturarDestinatarios(array $data): array` (remove `destinatarios` do `$data`, seta `$this->idsDestinatarios` **com guard de nível**) e `protected aplicarDestinatarios(Mensagem $m): void` (`$m->destinatarios()->sync(...)`). Usado pelas páginas na Task 3.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Filament/MensagemDestinatariosGuardTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\SincronizaDestinatarios;
use Tests\TestCase;

/**
 * Testa o GUARD do trait isoladamente (sem UI, sem DB). capturarDestinatarios é `protected`;
 * o harness é uma classe anônima que `use` o trait e expõe o método + o estado idsDestinatarios.
 */
class MensagemDestinatariosGuardTest extends TestCase
{
    private function harness(): object
    {
        return new class
        {
            use SincronizaDestinatarios;

            /** @return array{data: array, ids: array} */
            public function exec(array $data): array
            {
                $limpo = $this->capturarDestinatarios($data);

                return ['data' => $limpo, 'ids' => $this->idsDestinatarios];
            }
        };
    }

    public function test_direcionada_preserva_os_destinatarios_do_payload(): void
    {
        $r = $this->harness()->exec([
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [7, 9],
        ]);

        $this->assertSame([7, 9], $r['ids']);
        $this->assertArrayNotHasKey('destinatarios', $r['data']); // sempre sai do $data (fora do auto-sync)
    }

    /** VERMELHO #2 (I4-guard, DISCRIMINANTE): nível != direcionada ⇒ conjunto VAZIO, ainda que o payload traga ids. */
    public function test_nao_direcionada_zera_mesmo_com_payload(): void
    {
        $r = $this->harness()->exec([
            'nivel' => VisibilidadeMensagem::Publico->value,
            'destinatarios' => [7, 9],
        ]);

        $this->assertSame([], $r['ids'], 'o guard vence o payload — não confia na UI');
    }

    public function test_nivel_ausente_zera(): void
    {
        $r = $this->harness()->exec(['destinatarios' => [7]]);

        $this->assertSame([], $r['ids']);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED — não vacuous)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosGuardTest`
Expected: FAIL — `Trait "App\Filament\Resources\Mensagens\Pages\SincronizaDestinatarios" not found`.

> **Prova de não-vacuidade:** `test_nao_direcionada_zera_mesmo_com_payload` **reprova** contra um trait ingênuo que fizesse `$this->idsDestinatarios = $data['destinatarios'] ?? []` **sem** o guard de nível — o guard é exercido, não é premissa.

- [ ] **Step 3: Criar o trait**

Create `app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Filament\Resources\Mensagens\Pages;

use App\Enums\VisibilidadeMensagem;
use App\Models\Mensagem;

/**
 * Extrai o campo `destinatarios` (fora de coluna) do form antes do save e sincroniza o pivô
 * mensagem_destinatario no after-hook. Fora do auto-sync do Filament de propósito (molde
 * SincronizaRelacionadas): o GUARD DE NÍVEL decide o conjunto no servidor — só nível 'direcionada'
 * carrega destinatário; qualquer outro nível ⇒ conjunto VAZIO (limpa o pivô), sem confiar na UI.
 * Determinístico e independente de a UI ter escondido o campo (um Select->relationship() oculto
 * NÃO esvaziaria — vendor). O "≥1 obrigatório" da direcionada é garantido pelo ->required do form.
 */
trait SincronizaDestinatarios
{
    /** @var array<int, int|string> */
    protected array $idsDestinatarios = [];

    protected function capturarDestinatarios(array $data): array
    {
        $ehDirecionada = ($data['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
        $this->idsDestinatarios = $ehDirecionada ? ($data['destinatarios'] ?? []) : [];
        unset($data['destinatarios']); // nunca chega ao model (destinatarios não é coluna)

        return $data;
    }

    protected function aplicarDestinatarios(Mensagem $mensagem): void
    {
        $mensagem->destinatarios()->sync(
            collect($this->idsDestinatarios)->map(fn ($id) => (int) $id)->unique()->values()->all()
        );
    }
}
```

- [ ] **Step 4: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosGuardTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php tests/Feature/Filament/MensagemDestinatariosGuardTest.php`

```bash
git add app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php tests/Feature/Filament/MensagemDestinatariosGuardTest.php
git commit -m "feat(camada-4-fatia-f4a): trait SincronizaDestinatarios (guard de nivel + sync do pivo)"
```

---

## Task 3: Plugar o sync nas páginas Create/Edit + persistência (anexar, fill, preservação, re-sync, required-no-edit, clear, ponte)

**Files:**
- Modify: `app/Filament/Resources/Mensagens/Pages/CreateMensagem.php` (`use` do trait + chain nos hooks)
- Modify: `app/Filament/Resources/Mensagens/Pages/EditMensagem.php` (`use` do trait + fill/capture/aplicar)
- Test: `tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php`

**Interfaces:**
- Consumes: `SincronizaDestinatarios` (Task 2), o campo do form (Task 1), `Mensagem::factory()->comNivel(...)`/`->pendente()`, `Mensagem::destinatarios()`/`podeSerVistoPor()`, `User::mensagensDirecionadas()`.
- Produces: pivô sincronizado no create/edit (anexar quando direcionada; esvaziar quando não-direcionada); Select pré-preenchido no edit. Nenhuma nova assinatura pública.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosPersistenciaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function direcionadaCom(array $ids, array $attrs = []): Mensagem
    {
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create($attrs);
        $m->destinatarios()->sync($ids);

        return $m;
    }

    private function pivo(Mensagem $m): array
    {
        return DB::table('mensagem_destinatario')->where('mensagem_id', $m->id)
            ->pluck('user_id')->sort()->values()->all();
    }

    /** I2 (sucesso) — criar direcionada com ≥1 anexa o pivô certo. */
    public function test_cria_direcionada_anexa_destinatarios(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Recado', 'slug' => 'recado', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'direcionada',
                'destinatarios' => [$u1->id, $u2->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'recado')->firstOrFail();
        $this->assertSame([$u1->id, $u2->id], $this->pivo($m));
    }

    /** I3-fill — abrir o edit de uma direcionada pré-preenche o Select (prova o mutateFormDataBeforeFill). */
    public function test_edit_pre_preenche_destinatarios(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id, $u2->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormSet(['destinatarios' => [$u1->id, $u2->id]]);
    }

    /** I3-preservação — editar SÓ o título não apaga o pivô (sem o fill, o sync([]) apagaria). */
    public function test_edit_so_titulo_preserva_pivo(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id], ['titulo' => 'Antigo']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$u1->id], $this->pivo($m->fresh()));
    }

    /** I3-resync — trocar o conjunto reflete no pivô. */
    public function test_edit_re_sincroniza_conjunto(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id, $u2->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$u2->id, $u3->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$u2->id, $u3->id], $this->pivo($m->fresh()));
    }

    /** I2-edit — remover TODOS os destinatários de uma direcionada reprova e NÃO apaga o pivô. */
    public function test_edit_remover_todos_reprova_e_preserva(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => []])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertSame([$u1->id], $this->pivo($m->fresh())); // required barra o save → pivô intacto
    }

    /** I4-clear — trocar o nível para 'publico' esvazia o pivô (determinístico). */
    public function test_edit_troca_para_publico_esvazia_pivo(): void
    {
        $u1 = User::factory()->create();
        $m = $this->direcionadaCom([$u1->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $this->pivo($m->fresh()));
    }

    /** I6 — ponte F4a→3A→3C: o dado escrito pela página é exatamente o que o resolvedor 3A e a 3C leem. */
    public function test_ponte_para_resolvedor_e_3c(): void
    {
        $u = User::factory()->create();      // factory pura: nivelMaximo()=0, não-presidente (sem bypass veTudo)
        $outro = User::factory()->create();

        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Ponte', 'slug' => 'ponte', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'direcionada',
                'destinatarios' => [$u->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'ponte')->firstOrFail();
        $this->assertTrue($m->podeSerVistoPor($u));
        $this->assertFalse($m->podeSerVistoPor($outro));
        $this->assertTrue(
            $u->mensagensDirecionadas()->publicado()
                ->where('nivel', VisibilidadeMensagem::Direcionada->value)->exists()
        );
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosPersistenciaTest`
Expected: FAIL — o pivô não é sincronizado (as páginas ainda não plugam o trait): `test_cria_direcionada_anexa_destinatarios` vê `[]`; `test_edit_pre_preenche_destinatarios` vê o Select vazio; etc.

- [ ] **Step 3: Plugar o trait no CreateMensagem**

Modify `app/Filament/Resources/Mensagens/Pages/CreateMensagem.php` (arquivo completo):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMensagem extends CreateRecord
{
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterCreate(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
    }
}
```

- [ ] **Step 4: Plugar o trait no EditMensagem**

Modify `app/Filament/Resources/Mensagens/Pages/EditMensagem.php` (arquivo completo):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18

namespace App\Filament\Resources\Mensagens\Pages;

use App\Filament\Resources\Mensagens\MensagemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMensagem extends EditRecord
{
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['relacionadas'] = $this->record->relacionadas()->pluck('mensagens.id')->all();
        $data['destinatarios'] = $this->record->destinatarios()->pluck('users.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterSave(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
    }
}
```

- [ ] **Step 5: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosPersistenciaTest`
Expected: PASS (7 testes).

> Se `test_edit_pre_preenche_destinatarios` falhar por **ordem** dos ids (o `pluck` do pivô vs. `[$u1,$u2]`), o `assertFormSet` compara por igualdade; os ids são criados em ordem crescente e sincronizados nessa ordem, então batem. Se algum ambiente reordenar, ordenar ambos os lados no assert.

- [ ] **Step 6: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Filament/Resources/Mensagens/Pages/CreateMensagem.php app/Filament/Resources/Mensagens/Pages/EditMensagem.php tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php`

```bash
git add app/Filament/Resources/Mensagens/Pages/CreateMensagem.php app/Filament/Resources/Mensagens/Pages/EditMensagem.php tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php
git commit -m "feat(camada-4-fatia-f4a): sync do pivo nas paginas Create/Edit (anexar/fill/preservacao/clear)"
```

---

## Task 4: Coluna-contador + filtro "tem destinatário" na tabela (O2)

**Files:**
- Modify: `app/Filament/Resources/Mensagens/MensagemResource.php` (`+TextColumn destinatarios_count` em `table()->columns`; `+Filter com_destinatarios` em `->filters`; `+imports` `Builder`, `Filter`)
- Test: `tests/Feature/Filament/MensagemDestinatariosTabelaTest.php`

**Interfaces:**
- Consumes: `Mensagem::destinatarios()` (para `->counts()`/`has()`).
- Produces: coluna `destinatarios_count` (contagem do pivô) + filtro toggle `com_destinatarios` (`has('destinatarios')`) na tabela do Resource.

- [ ] **Step 1: Escrever os testes que falham**

Create `tests/Feature/Filament/MensagemDestinatariosTabelaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\ListMensagens;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemDestinatariosTabelaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_tabela_tem_coluna_contador(): void
    {
        Livewire::test(ListMensagens::class)
            ->assertTableColumnExists('destinatarios_count');
    }

    public function test_filtro_tem_destinatario_restringe_a_lista(): void
    {
        $direcionada = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create(['titulo' => 'Com destino']);
        $direcionada->destinatarios()->sync([User::factory()->create()->id, User::factory()->create()->id]);
        $publica = Mensagem::factory()->publica()->create(['titulo' => 'Sem destino']);

        Livewire::test(ListMensagens::class)
            ->filterTable('com_destinatarios')
            ->assertCanSeeTableRecords([$direcionada])
            ->assertCanNotSeeTableRecords([$publica]);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar (RED)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosTabelaTest`
Expected: FAIL — coluna `destinatarios_count` inexistente e filtro `com_destinatarios` não definido. **Confirmar aqui** os nomes exatos dos métodos de teste de tabela do Filament v5 (`assertTableColumnExists`, `filterTable`, `assertCanSeeTableRecords`/`assertCanNotSeeTableRecords`); se algum divergir, ajustar o teste antes de implementar.

- [ ] **Step 3: Implementar a coluna e o filtro**

Modify `app/Filament/Resources/Mensagens/MensagemResource.php`:

**(a)** Imports (junto aos demais `use`):

```php
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
```

**(b)** Na `table()->columns([...])`, acrescentar (ex.: após a coluna `pictografia`):

```php
                TextColumn::make('destinatarios_count')
                    ->label('Destinatários')
                    ->counts('destinatarios')
                    ->badge()
                    ->toggleable(),
```

**(c)** Em `->filters([...])`, acrescentar:

```php
                Filter::make('com_destinatarios')
                    ->label('Tem destinatário')
                    ->query(fn (Builder $query): Builder => $query->has('destinatarios')),
```

- [ ] **Step 4: Rodar e ver passar (GREEN)**

Run: `docker compose exec -T app php artisan test --filter=MensagemDestinatariosTabelaTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + commit**

Run: `docker compose exec -T app vendor/bin/pint app/Filament/Resources/Mensagens/MensagemResource.php tests/Feature/Filament/MensagemDestinatariosTabelaTest.php`

```bash
git add app/Filament/Resources/Mensagens/MensagemResource.php tests/Feature/Filament/MensagemDestinatariosTabelaTest.php
git commit -m "feat(camada-4-fatia-f4a): coluna destinatarios_count + filtro 'tem destinatario' na tabela"
```

---

## Task 5: Fechamento — suíte inteira + Pint + prova no /admin + PR

**Files:**
- Verify: suíte completa (regressão I-reg) + navegador (`/admin/mensagens`).

**Interfaces:** nenhuma nova — prova o que as Tasks 1–4 produziram e a **neutralidade** (nenhum teste de leitura muda de cor).

- [ ] **Step 1: Suíte inteira + Pint**

Run: `docker compose exec -T app vendor/bin/pint --test`
Run: `docker compose exec -T app php artisan test`
Expected: verde. Baseline **~1081** → **~1081 + 16 novos** (4 `MensagemDestinatariosFormTest` + 3 `MensagemDestinatariosGuardTest` + 7 `MensagemDestinatariosPersistenciaTest` + 2 `MensagemDestinatariosTabelaTest`). Os testes existentes do `MensagemResourceTest` (2A) **não** mudam de cor: eles criam com `nivel=null` (factory default) ⇒ a Section "Destinatários" fica oculta e o `->required` não dispara; `test_form_tem_select_nivel_com_publico_e_aceita_null` segue verde (o `nivel` continua não-obrigatório; só ganhou `->live()`). Ciência [[flaky-importadorblog-gd-cap-imagem]]: 2 testes de cap de imagem do blog podem falhar sob carga — se passam isolados, **não** é regressão desta fatia.

- [ ] **Step 2: Prova no /admin (o que os testes não cobrem)**

**Sem `npm run build`** (form de painel — nenhum asset novo). Recompilar o PHP: `docker compose exec -T app php artisan optimize:clear` + `docker compose restart app worker` ([[dev-opcache-restart-app-worker]]).

Logar como **admin** e conferir em `/admin/mensagens`:
- ✅ Criar mensagem, escolher **nível "Direcionada"** → a **Section "Destinatários"** aparece; escolher outro nível → a Section **some**.
- ✅ Tentar salvar direcionada **sem** destinatário → erro de validação (não salva).
- ✅ Salvar direcionada com 1–2 destinatários → grava; reabrir o edit → o Select vem **pré-preenchido**.
- ✅ Editar só o título de uma direcionada → destinatários **preservados** (Adminer: `mensagem_destinatario` intacto).
- ✅ Editar trocando o nível para "Público" → salva e o pivô é **esvaziado** (Adminer: 0 linhas para aquela mensagem).
- ✅ Na tabela: a coluna **Destinatários** mostra a contagem; o filtro **"Tem destinatário"** restringe às direcionadas com pivô.
- ✅ **Neutralidade:** logar como um **destinatário real** (dev) e ver a mensagem no single (3B) e na aba "Minhas Direcionadas" (3C) — a F4a alimentou o pivô que elas leem, sem mudá-las.

- [ ] **Step 3: Abrir o PR e PARAR**

> **Fim da execução.** Este plano vai ao **passe do consultor** ANTES da execução (o dono cravou: escrever o plano → passe do plano → execução → PR → passe do PR). **NÃO implementar** sem o go. Quando executado e verde, abrir o PR único:

```bash
git push -u origin camada-4-fatia-f4a-curadoria-direcionadas
gh pr create --base main --title "feat(camada-4-fatia-f4a): campo de destinatários no /admin (direcionada completa)" --body "<resumo: Section Destinatários condicional; sync manual determinístico (guard de nível); required≥1 + minItems; coluna/filtro; I1–I7 verdes; sem migration; admin-only>"
```

Mesclar **só** com o CI verde no **último** commit ([[merge-so-com-ci-verde-no-commit-final]]).

**Cutover de PROD (do dono):** deploy só de PHP (§8 do spec) — `git pull` → `php artisan optimize:clear` + `docker compose restart app worker` (**sem** `npm run build`; **sem** migration).

---

## Cobertura dos invariantes (rastreabilidade)

| Invariante | Onde é provado |
|---|---|
| **I1** campo condicional + nível `->live()` | Task 1 (`nivel_e_live_e_destinatarios_e_multiplo`, `destinatarios_visivel_so_quando_direcionada`) |
| **I2** direcionada exige ≥1 (create **e** edit) | Task 1 (`direcionada_sem_destinatario_reprova` — VERMELHO) + Task 3 (`cria_direcionada_anexa_destinatarios`, `edit_remover_todos_reprova_e_preserva`) |
| **I3** anexa / auto-fill / preservação / re-sync | Task 3 (`edit_pre_preenche_destinatarios`, `edit_so_titulo_preserva_pivo`, `edit_re_sincroniza_conjunto`) |
| **I4** clear determinístico + guard server-side | Task 2 (`nao_direcionada_zera_mesmo_com_payload` — VERMELHO/guard) + Task 3 (`edit_troca_para_publico_esvazia_pivo`) |
| **I5** não-direcionada não obriga destinatário | Task 1 (`nao_direcionada_sem_destinatario_salva` — publico+trabalhadores; null via regressão 2A) |
| **I6** ponte F4a→3A→3C | Task 3 (`ponte_para_resolvedor_e_3c`) |
| **I7** coluna-contador + filtro (O2) | Task 4 (`tabela_tem_coluna_contador`, `filtro_tem_destinatario_restringe_a_lista`) |
| **I-reg** neutralidade / suíte / Pint | Task 5 |

---
