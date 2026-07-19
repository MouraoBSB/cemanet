# Camada 4 · Fatia 2B — Front público das Mensagens (listagem/detalhe + lista/perfil de autores)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recriar na stack as **4 páginas públicas** das Mensagens Mediúnicas — lista (`/mensagens-mediunicas`), single (`/mensagens-mediunicas/{slug}`), lista de autores (`/autores-espirituais`) e perfil de autor (`/autores-espirituais/{slug}`) — consumindo **só a camada Públicas** (`Mensagem::publica()`) e os autores ativos **com ≥1 pública** (`AutorEspiritual::ativo()->whereHas('mensagens', publica())`), recriando os `design_handoff_*` (tema claro). Sem migration/seeder: é apresentação. **Sem F3** (níveis/cadeado/PII → não-pública = 404) e **sem F5** (favoritar/lida/curtir).

**Architecture:** Uma mudança de domínio (`AutorEspiritual::mensagens()` — relação inversa; o pivô `mensagem_autor_espiritual` já existe da 2A). Duas superfícies: (a) **mensagens** — `MensagemController` (`index` embute o Livewire `Mensagens\Lista`; `show` por slug, `firstOrFail`→404) + views + 3 parciais de corpo (**psicofonia = prosa**, O1) + Alpine de leitura; (b) **autores** — `AutorEspiritualController` (`index` grade controller-puro; `show` por slug com `ResumoAutor` + grade client-side) + views. SEO: `SitemapController` ganha `Mensagem::publica()` + autores-com-pública; meta/canonical/OG por slot `head` (molde `palestrantes/show`); 301 do CPT WP `mensagem-mediunicas`. Molds clonados intactos: `Palestras\Lista`, `PalestranteController`, `ResumoPerfil`, `SitemapController`.

**Tech Stack:** PHP 8.3 · Laravel 13 · **Livewire 4** (`^4.3`) · Blade · Tailwind v4 (tokens `@theme`) · Alpine.js · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-medialibrary · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-19-camada-4-fatia-2b-front-publico-mensagens.md`](../specs/2026-07-19-camada-4-fatia-2b-front-publico-mensagens.md) (aprovada pelo Consultor: **✅ APROVADA**, zero bloqueador; obrigatórios O1/O5a/coerência-do-card aplicados; O6 confirmado; passe registrado no §13).

## Global Constraints

- **Idioma:** todo código, comentário, string de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-4-fatia-2b-front-mensagens` a partir de `origin/main` (= **`161b502`**, PR #37, Fatia 2A mesclada). **Nunca** na `main`. O PR leva código **e** os commits de docs (SPEC + este plano) juntos.
- **Cabeçalho de autoria** em todo arquivo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19`. (Factories não levam cabeçalho — mas esta fatia não cria factory.)
- **🚫 Banco (dev):** a 2B **não** tem migration nem seeder. **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo (o dev tem 152 usuários + 179 mensagens + 19 autores + mídia importada). A conexão **`legado` é READ-ONLY**.
- **I1/I2 — filtro público FIXO:** toda leitura de mensagem parte de `Mensagem::publica()` ([Mensagem.php:63](../../../app/Models/Mensagem.php#L63) — `status='publicado' AND nivel='publico'`). Single não-pública = **404** via `firstOrFail` **depois** do scope (molde [PalestranteController:31-34](../../../app/Http/Controllers/PalestranteController.php#L31-L34)); **nunca 403**, nunca vaza existência.
- **I14 — zero F3/F5 na saída:** nenhum badge de nível, cadeado, legenda de bolinhas, card/lista de destinatários (PII), favoritar, ícone lida/não-lida ou curtir no HTML público. **Nunca** usar `x-ui.selo-visibilidade` (F3). **Nunca** reusar `Palestras\Curtir` (F5).
- **O1 — psicofonia = PROSA:** os 3 corpos saem do **mesmo** `Mensagem::corpo` (HTML já saneado por `clean('conteudo')`); psicografia **e** psicofonia = `{!! $corpo !!}` em `.cema-msg-prose` (psicofonia + nota "Transcrição por psicofonia"). Pictografia = galeria da MediaLibrary. **Não** criar infra de balões P&R (0/40 no legado têm `table/div`). `contexto` **sempre** `{{ }}` (escapado — I4).
- **O5a — autor sem pública OCULTO:** a grade de autores e o sitemap listam só `ativo()->whereHas('mensagens', fn($q)=>$q->publica())`. O **perfil** por URL direta a autor ativo sem pública segue **200** (grade vazia/stats zerados), **não** 404.
- **Coerência do card:** `x-mensagem.card` recebe `:variante` (`'lista'` sem miniatura/2 linhas · `'perfil'` com miniatura de pictografia/3 linhas/data dourada). Reusar sem variante quebra um dos dois.
- **🚫 Pluralização de "mensagem" (B1):** **NUNCA** `Str::plural('mensagem', …)` — o pluralizador inglês gera **"mensagems"** (o próprio projeto documenta em [Mensagem.php:25](../../../app/Models/Mensagem.php#L25); os `Str::plural('palestra'/'palestrante')` das outras views só funcionam por acaso, terminam em vogal). **Todo** lugar que conte mensagens (card de autor, contador da lista, sidebar do single) usa **ternário pt-BR explícito**: `{{ $n === 1 ? 'mensagem' : 'mensagens' }}`.
- **R3 — factory default não é pública:** `MensagemFactory` nasce com `nivel => null` ([:27](../../../database/factories/MensagemFactory.php#L27)) ⇒ `factory()->create()` **sem state** NÃO passa em `publica()`. Usar `->publica()` para públicas e `nivel` explícito para restritas (os testes já fazem) — **não** presumir que o default é público.
- **N+1 (I6):** a lista de mensagens eager-carrega `autores` (`->with('autores')`); a grade de autores usa `withCount(['mensagens' => fn($q)=>$q->publica()])`.
- **Portabilidade SQLite×MySQL:** contagens/distintos/ordenação de "recentes" em **PHP** (molde `ResumoPerfil`/`PalestranteController::show`), não `selectRaw`/`YEAR()`.
- **Guardrails de front:** mobile-first; grades `auto-fill,minmax(...)`; sidebar deixa de ser `sticky` abaixo de `desktop-sm`; `prefers-reduced-motion` desliga progresso/envelope/partículas; `text-text-ink` (não `text-text`); tokens `var(--color-*)` (nunca `theme()`); imagens WebP `web`/`thumb` com `loading="lazy"`; SSR + Alpine leve.
- **Aceite:** suíte verde (**~972 + novos**; medir baseline com `artisan test --list-tests`) e **nenhuma asserção existente muda de cor** (a 2B só **adiciona**).
- **Comandos:** testes focados por task `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint <arquivos>` ([[pint-antes-de-push]]). **Vite no HOST:** `npm run build` **fora** do container ([[npm-vite-no-host]]). Se um teste/página rodar código **stale** após editar Blade/PHP existente, `docker compose restart app worker` (OPcache `validate_timestamps=0` — [[dev-opcache-restart-app-worker]]) e repita.
- **Ciência de flaky:** [[flaky-importadorblog-gd-cap-imagem]] — 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados/no CI, não é regressão desta fatia.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-4-fatia-2b-front-mensagens origin/main
git log --oneline -1
```

Esperado: HEAD em `161b502` (merge do PR #37 — Fatia 2A). Os commits de docs (SPEC + este plano) entram junto; o PR leva código **e** docs.

- [ ] **Passo 2: Confirmar os states de factory da 2A (R4)**

```bash
docker compose exec -T app php artisan tinker --execute="echo get_class(App\Models\Mensagem::factory()->publica()->make()); echo PHP_EOL; echo App\Models\Mensagem::factory()->pendente()->make()->status;"
```

Esperado: a factory resolve `->publica()` (nivel `publico`) e `->pendente()` (status `pendente`) — existem desde a 2A ([factory](../../../database/factories/MensagemFactory.php)). Se **faltarem**, criar antes de prosseguir (os testes §9 dependem delas).

---

### Task 1: Relação inversa `AutorEspiritual::mensagens()` (a única mudança de domínio)

**Files:**
- Modify: `app/Models/AutorEspiritual.php` (+1 método)
- Test: `tests/Feature/Models/AutorEspiritualMensagensTest.php`

**Interfaces:**
- Consumes: `App\Models\Mensagem` (2A), pivô `mensagem_autor_espiritual`.
- Produces: `AutorEspiritual::mensagens(): BelongsToMany` — espelho de `Mensagem::autores()` ([Mensagem.php:75-78](../../../app/Models/Mensagem.php#L75-L78)); `->publica()` encadeia.

**Contexto:** o pivô já existe (2A); **sem migration**. `BelongsToMany` já está importado no model ([AutorEspiritual.php:14](../../../app/Models/AutorEspiritual.php#L14)); `Mensagem` é o mesmo namespace `App\Models` (sem `use`).

- [ ] **Passo 1: Escrever o teste que falha**

`tests/Feature/Models/AutorEspiritualMensagensTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Models;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorEspiritualMensagensTest extends TestCase
{
    use RefreshDatabase;

    public function test_mensagens_pelo_pivo_e_publica_encadeia(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $pub1 = Mensagem::factory()->publica()->create();
        $pub2 = Mensagem::factory()->publica()->create();
        $pendente = Mensagem::factory()->pendente()->create();

        $autor->mensagens()->sync([$pub1->id, $pub2->id, $pendente->id]);

        // a relação lê as 3 vinculadas...
        $this->assertSame(3, $autor->fresh()->mensagens()->count());
        // ...e o scope publica() encadeia (só as 2 públicas).
        $this->assertSame(2, $autor->fresh()->mensagens()->publica()->count());
    }

    public function test_simetria_com_mensagem_autores(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $m = Mensagem::factory()->create();

        $m->autores()->sync([$autor->id]);

        $this->assertTrue($autor->fresh()->mensagens->contains('id', $m->id));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualMensagensTest`
Esperado: FAIL — `Call to undefined method App\Models\AutorEspiritual::mensagens()`.

- [ ] **Passo 3: Adicionar a relação**

Em `app/Models/AutorEspiritual.php`, após `departamentos()` (é o espelho de `Mensagem::autores()`):

```php
    public function mensagens(): BelongsToMany
    {
        return $this->belongsToMany(Mensagem::class, 'mensagem_autor_espiritual', 'autor_espiritual_id', 'mensagem_id');
    }
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualMensagensTest`
Esperado: PASS.

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/AutorEspiritual.php tests/Feature/Models/AutorEspiritualMensagensTest.php
git add app/Models/AutorEspiritual.php tests/Feature/Models/AutorEspiritualMensagensTest.php
git commit -m "feat(camada-4-fatia-2b): AutorEspiritual::mensagens() (relacao inversa N:N)"
```

---

### Task 2: Rotas + 301 do WP + esqueletos de controller (para a base compilar)

**Files:**
- Modify: `routes/web.php` (imports + bloco de rotas + 301)
- Create: `app/Http/Controllers/MensagemController.php` (esqueleto `index`/`show`)
- Create: `app/Http/Controllers/AutorEspiritualController.php` (esqueleto `index`/`show`)
- Test: `tests/Feature/Front/MensagemUrlCompatTest.php`

**Interfaces:**
- Produces: rotas `mensagens.index`/`mensagens.show`/`autores.index`/`autores.show`; 301 `/mensagem-mediunicas[/{slug}]` → base nova. Controllers ganham corpo real nas Tasks 3/5/6 — aqui nascem como esqueleto que retorna uma view mínima (ou `abort(501)` no que ainda não existe), só para as rotas resolverem.

**Contexto:** molde de rota por slug **sem `.ics`** = Palestrantes ([routes/web.php:75-76](../../../routes/web.php#L75-L76)); a 2B **usa** `->where('slug','[a-z0-9-]+')` (mais seguro). Molde do 301 = Eventos ([:93-96](../../../routes/web.php#L93-L96)): `permanentRedirect` no archive + closure `redirect()->route(...,301)` no single. O CPT WP é `mensagem-mediunicas` (§13/O6 confirmado); a base nova (`/mensagens-mediunicas`) **difere** da antiga → sem colisão com o `{slug}`.

- [ ] **Passo 1: Escrever o teste de compat 301 que falha**

`tests/Feature/Front/MensagemUrlCompatTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemUrlCompatTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_antigo_301_para_a_base_nova(): void
    {
        $this->get('/mensagem-mediunicas')
            ->assertStatus(301)
            ->assertRedirect('/mensagens-mediunicas');
    }

    public function test_single_antigo_301_preserva_o_slug(): void
    {
        $this->get('/mensagem-mediunicas/paz-e-luz')
            ->assertStatus(301)
            ->assertRedirect(route('mensagens.show', 'paz-e-luz'));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemUrlCompatTest`
Esperado: FAIL — 404 (as rotas não existem).

- [ ] **Passo 3: Criar os esqueletos de controller**

`app/Http/Controllers/MensagemController.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MensagemController extends Controller
{
    public function index(): View
    {
        return view('mensagens.index');   // corpo real na Task 3
    }

    public function show(string $slug): View
    {
        abort(501);   // corpo real na Task 4 (single) — placeholder para a rota resolver
    }
}
```

`app/Http/Controllers/AutorEspiritualController.php` (análogo: `index()`/`show(string $slug)`, ambos `abort(501)` até as Tasks 5/6).

- [ ] **Passo 4: Adicionar as rotas + 301**

Em `routes/web.php`, imports no topo (ordem alfabética):
```php
use App\Http\Controllers\AutorEspiritualController;
use App\Http\Controllers\MensagemController;
```
Bloco de rotas (junto aos demais recursos públicos, ex. após Eventos):
```php
// Mensagens Mediúnicas (front público — só Públicas). Estáticas antes de {slug}.
Route::get('/mensagens-mediunicas', [MensagemController::class, 'index'])->name('mensagens.index');
Route::get('/mensagens-mediunicas/{slug}', [MensagemController::class, 'show'])
    ->name('mensagens.show')->where('slug', '[a-z0-9-]+');

// Compat 301 do CPT WP 'mensagem-mediunicas' (singular) → base nova (plural).
Route::permanentRedirect('/mensagem-mediunicas', '/mensagens-mediunicas');
Route::get('/mensagem-mediunicas/{slug}', fn (string $slug) => redirect()->route('mensagens.show', ['slug' => $slug], 301))
    ->where('slug', '[a-z0-9-]+');

// Autores Espirituais (perfil por slug, sem .ics).
Route::get('/autores-espirituais', [AutorEspiritualController::class, 'index'])->name('autores.index');
Route::get('/autores-espirituais/{slug}', [AutorEspiritualController::class, 'show'])
    ->name('autores.show')->where('slug', '[a-z0-9-]+');
```

- [ ] **Passo 5: Criar a view mínima `mensagens/index` (só para a rota resolver)**

`resources/views/mensagens/index.blade.php` (mínima; ganha corpo na Task 3):
```blade
<x-layout.app title="Mensagens Mediúnicas">
    <main></main>
</x-layout.app>
```

- [ ] **Passo 6: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=MensagemUrlCompatTest`
Esperado: PASS (os 301 resolvem; `route('mensagens.show', ...)` existe).

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/MensagemController.php app/Http/Controllers/AutorEspiritualController.php tests/Feature/Front/MensagemUrlCompatTest.php
git add routes/web.php app/Http/Controllers/MensagemController.php app/Http/Controllers/AutorEspiritualController.php resources/views/mensagens/index.blade.php tests/Feature/Front/MensagemUrlCompatTest.php
git commit -m "feat(camada-4-fatia-2b): rotas mensagens/autores + 301 do CPT mensagem-mediunicas"
```

---

### Task 3: Lista de mensagens — `Mensagens\Lista` (Livewire) + view + componentes de card

**Files:**
- Create: `app/Livewire/Mensagens/Lista.php`
- Create: `resources/views/livewire/mensagens/lista.blade.php`
- Rewrite: `resources/views/mensagens/index.blade.php` (hero + envelope + livewire + "Veja também")
- Create: `resources/views/components/mensagem/card.blade.php` (prop `:variante`)
- Create: `resources/views/components/mensagem/linha.blade.php`
- Create: `resources/views/components/mensagem/selo-formato.blade.php`
- Create: `resources/views/components/mensagem/envelope-hero.blade.php`
- Modify: `resources/css/app.css` (ou o tema): `.cema-msg-prose`, keyframes do envelope, hover do card
- Test: `tests/Feature/Front/MensagemListaTest.php`

**Interfaces:**
- Consumes: `App\Models\{Mensagem,AutorEspiritual}`, `App\Enums\FormatoMensagem`.
- Produces: `App\Livewire\Mensagens\Lista` (props `#[Url]` `de`/`ate`/`autor`/`ordenar`/`visao`; `render()` = `Mensagem::publica()->with('autores')` paginado 9); componentes `x-mensagem.{card,linha,selo-formato,envelope-hero}`.

**Contexto:** clone enxuto de [Palestras\Lista](../../../app/Livewire/Palestras/Lista.php) — **sem** busca textual (o handoff da lista de mensagens não tem `q`; SPEC §4.1). `updated()`/`limparFiltros()`/`removerFiltro()`/`alternarVisao()`/`filtrosAtivos()` no molde ([:48-108](../../../app/Livewire/Palestras/Lista.php#L48-L108)); **valida a entrada antes do SQL** (`Carbon::hasFormat`). O card é **compartilhado** com o perfil via `:variante` (SPEC §6.6). Opções de autor = autores com ≥1 pública **+** "Sem assinatura" (O5b). A recriação visual segue SPEC §4.1 + `design_handoff_mensagens_lista/` (tema claro; **não** copiar o HTML; F3/F5 fora).

- [ ] **Passo 1: Escrever o teste da lista que falha**

`tests/Feature/Front/MensagemListaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Livewire\Mensagens\Lista;
use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_so_lista_publicas(): void
    {
        $pub = Mensagem::factory()->publica()->create(['titulo' => 'Mensagem Pública']);
        Mensagem::factory()->pendente()->create(['titulo' => 'Mensagem Pendente']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Mensagem Restrita']);

        Livewire::test(Lista::class)
            ->assertSee('Mensagem Pública')
            ->assertDontSee('Mensagem Pendente')
            ->assertDontSee('Mensagem Restrita');
    }

    public function test_filtra_por_autor_por_slug(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Bezerra de Menezes', 'slug' => 'bezerra-de-menezes']);
        $doAutor = Mensagem::factory()->publica()->create(['titulo' => 'Do Autor']);
        $doAutor->autores()->sync([$autor->id]);
        Mensagem::factory()->publica()->create(['titulo' => 'Sem Autor']);

        Livewire::test(Lista::class)
            ->set('autor', 'bezerra-de-menezes')
            ->assertSee('Do Autor')
            ->assertDontSee('Sem Autor');
    }

    public function test_filtra_sem_assinatura(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $comAutor = Mensagem::factory()->publica()->create(['titulo' => 'Com Autor']);
        $comAutor->autores()->sync([$autor->id]);
        Mensagem::factory()->publica()->create(['titulo' => 'Anônima']);

        Livewire::test(Lista::class)
            ->set('autor', 'sem-assinatura')
            ->assertSee('Anônima')
            ->assertDontSee('Com Autor');
    }

    public function test_filtra_por_periodo(): void
    {
        Mensagem::factory()->publica()->create(['titulo' => 'Antiga', 'data_recebimento' => '2020-01-01']);
        Mensagem::factory()->publica()->create(['titulo' => 'Recente', 'data_recebimento' => '2025-01-01']);

        Livewire::test(Lista::class)
            ->set('dataDe', '2024-01-01')
            ->assertSee('Recente')
            ->assertDontSee('Antiga');
    }

    public function test_alternar_visao_nao_reseta_pagina(): void
    {
        Mensagem::factory()->publica()->count(12)->create();

        // R2: se viewData()->currentPage() se comportar diferente no Livewire 4, cair para o padrão
        // da casa (PalestrasListaTest usa ->html()/assertSee do conteúdo da página 2).
        $c = Livewire::test(Lista::class)->call('setPage', 2);
        $c->call('alternarVisao', 'list')->assertSet('visao', 'list');
        $this->assertSame(2, $c->viewData('mensagens')->currentPage());
    }

    public function test_estado_vazio_quando_sem_publicas(): void
    {
        Mensagem::factory()->pendente()->create();

        Livewire::test(Lista::class)->assertSee('Nenhuma mensagem'); // texto do estado vazio (SPEC §4.1)
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=MensagemListaTest`
Esperado: FAIL — `Class "App\Livewire\Mensagens\Lista" not found`.

- [ ] **Passo 3: Criar o componente Livewire**

`app/Livewire/Mensagens/Lista.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace App\Livewire\Mensagens;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'de', except: '')]
    public string $dataDe = '';

    #[Url(as: 'ate', except: '')]
    public string $dataAte = '';

    #[Url(as: 'autor', except: '')]
    public string $autor = '';   // slug do autor OU o sentinela 'sem-assinatura'

    #[Url(as: 'ordenar', except: 'recente')]
    public string $ordenar = 'recente';   // recente | antiga | az

    #[Url(as: 'visao', except: 'grid')]
    public string $visao = 'grid';         // grid | list

    public function updated(string $name): void
    {
        if (in_array($name, ['dataDe', 'dataAte', 'autor', 'ordenar'], true)) {
            $this->resetPage();   // 'visao' fora: trocar visão não reseta paginação.
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['dataDe', 'dataAte', 'autor', 'ordenar']);   // 'visao' preservada (preferência, não filtro).
        $this->resetPage();
    }

    public function removerFiltro(string $chave): void
    {
        $mapa = ['de' => 'dataDe', 'ate' => 'dataAte', 'autor' => 'autor', 'ordenar' => 'ordenar'];
        if (isset($mapa[$chave])) {
            $this->reset($mapa[$chave]);
            $this->resetPage();
        }
    }

    public function alternarVisao(string $visao): void
    {
        // NÃO reseta página: trocar a visão não deve voltar para a página 1.
        $this->visao = in_array($visao, ['grid', 'list'], true) ? $visao : 'grid';
    }

    /** @return array<int, array{chave: string, rotulo: string}> */
    public function filtrosAtivos(): array
    {
        $chips = [];
        if ($this->dataDe !== '') {
            $chips[] = ['chave' => 'de', 'rotulo' => 'De: '.$this->dataDe];
        }
        if ($this->dataAte !== '') {
            $chips[] = ['chave' => 'ate', 'rotulo' => 'Até: '.$this->dataAte];
        }
        if ($this->autor === 'sem-assinatura') {
            $chips[] = ['chave' => 'autor', 'rotulo' => 'Autor: Sem assinatura'];
        } elseif ($this->autor !== '') {
            $chips[] = ['chave' => 'autor', 'rotulo' => 'Autor: '.(AutorEspiritual::where('slug', $this->autor)->value('nome') ?? $this->autor)];
        }

        return $chips;
    }

    public function render()
    {
        $mensagens = Mensagem::query()
            ->publica()
            ->with('autores')
            ->when($this->dataDe !== '' && Carbon::hasFormat($this->dataDe, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '>=', $this->dataDe))
            ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte, 'Y-m-d'),
                fn (Builder $q) => $q->whereDate('data_recebimento', '<=', $this->dataAte))
            ->when($this->autor === 'sem-assinatura', fn (Builder $q) => $q->whereDoesntHave('autores'))
            ->when($this->autor !== '' && $this->autor !== 'sem-assinatura',
                fn (Builder $q) => $q->whereHas('autores', fn (Builder $a) => $a->where('autores_espirituais.slug', $this->autor)))
            ->when($this->ordenar === 'az',
                fn (Builder $q) => $q->orderBy('titulo'),
                fn (Builder $q) => $q->orderByRaw('data_recebimento IS NULL, data_recebimento '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
            ->paginate(9);

        return view('livewire.mensagens.lista', [
            'mensagens' => $mensagens,
            'autores' => AutorEspiritual::whereHas('mensagens', fn (Builder $q) => $q->publica())->orderBy('nome')->get(['nome', 'slug']),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
}
```

- [ ] **Passo 4: Criar a view do Livewire + os componentes de card + o hero da index + CSS**

Recriar visualmente conforme **SPEC §4.1** + `design_handoff_mensagens_lista/` (tema claro; **não** copiar o HTML; F3/F5 fora). Peças:
- `resources/views/livewire/mensagens/lista.blade.php` — card de filtros (De/Até `type=date`, select Autor com `<option value="sem-assinatura">Sem assinatura</option>` + `@foreach($autores)`, select Ordenar, toggle grade/lista via `wire:click="alternarVisao('grid'|'list')"`), chips `@foreach($filtrosAtivos)` com `wire:click="removerFiltro('{{ $c['chave'] }}')"` + "Limpar tudo" (`wire:click="limparFiltros"`), contador (`$mensagens->total()`), grade↔lista (`@if($visao==='grid')` `<x-mensagem.card :mensagem="$m" variante="lista"/>` `@else` `<x-mensagem.linha :mensagem="$m"/>`), `@forelse/@empty` (estado vazio "Nenhuma mensagem encontrada" + botão Limpar), `{{ $mensagens->links() }}`.
- `resources/views/components/mensagem/card.blade.php` — `@props(['mensagem','variante' => 'lista'])`. Comum: título, trecho `{{ Str::limit(strip_tags($mensagem->corpo), 160) }}` (O2), autor (`@forelse($mensagem->autores as $a)` avatar/nome `@empty` "Sem assinatura"), data `{{ $mensagem->data_recebimento?->translatedFormat('d M Y') }}`, `<x-mensagem.selo-formato :formato="$mensagem->formato" />`, CTA `route('mensagens.show', $mensagem->slug)`. **Variante `perfil`:** liga miniatura da pictografia (`@if($mensagem->formato === \App\Enums\FormatoMensagem::Pictografia && $mensagem->getFirstMediaUrl('pictografia','web'))`), trecho 3 linhas, data mono dourada (§4.4). **Variante `lista`:** sem miniatura, trecho 2 linhas (§4.1/C-A).
- `resources/views/components/mensagem/linha.blade.php` — `@props(['mensagem'])`, layout horizontal (faixa lateral, meta formato+data, título, "por {Autor}"/"Sem assinatura", CTA "Ler ›").
- `resources/views/components/mensagem/selo-formato.blade.php` — `@props(['formato'])`, pílula + ícone (pena/ondas/moldura) + `{{ $formato->rotulo() }}` + cores AA por formato (§4.4). **Compartilhado** (lista/single/perfil).
- `resources/views/components/mensagem/envelope-hero.blade.php` — o SVG animado do envelope (`aria-hidden`), CSS movido ao tema, `@media(max-width:1280px){display:none}` + `prefers-reduced-motion`.
- `resources/views/mensagens/index.blade.php` (rewrite) — `<x-layout.app title="Mensagens Mediúnicas" description="Mensagens psicografadas, psicofônicas e pictográficas recebidas na mediunidade do CEMA.">` → hero (`<x-mensagem.envelope-hero/>` + `<x-ui.particulas/>` + breadcrumb `Início › Mensagens Mediúnicas` + card contador `{{ \App\Models\Mensagem::publica()->count() }}`) + `<livewire:mensagens.lista/>` + "Veja também" (**só** links de páginas que existem: `autores.index`, `palestras.index`, `blog.index`, `agenda.index`).
- CSS (`resources/css/app.css` ou tema): `.cema-msg-prose` (Roboto Slab 300, line-height alto), keyframes do envelope (com `prefers-reduced-motion`), hover do card (`translateY(-5px)`).

- [ ] **Passo 5: Build do front (HOST) + testes**

```bash
npm run build   # NO HOST (o container não tem Node)
```
Run: `docker compose exec -T app php artisan test --filter=MensagemListaTest`
Esperado: PASS. (Se stale, `docker compose restart app worker` e repita.)

- [ ] **Passo 6: Conferência visual + Pint + commit**

Abrir `http://localhost/mensagens-mediunicas` e conferir contra `design_handoff_mensagens_lista/screenshots/` (hero, grade, lista, filtros, vazio). **Sem** badge de nível/cadeado/lida-não-lida (I14).
```bash
docker compose exec -T app ./vendor/bin/pint app/Livewire/Mensagens/Lista.php tests/Feature/Front/MensagemListaTest.php
git add app/Livewire/Mensagens/Lista.php resources/views/livewire/mensagens/lista.blade.php resources/views/mensagens/index.blade.php resources/views/components/mensagem/ resources/css/app.css tests/Feature/Front/MensagemListaTest.php
git commit -m "feat(camada-4-fatia-2b): lista de mensagens (Livewire + cards + hero) — so Publicas"
```

---

### Task 4: Single de mensagem — `MensagemController::show` + view + 3 corpos + Alpine

**Files:**
- Modify: `app/Http/Controllers/MensagemController.php` (`show` real)
- Create: `resources/views/mensagens/show.blade.php`
- Create: `resources/views/mensagens/corpos/{psicografia,psicofonia,pictografia}.blade.php`
- Modify: `resources/js/app.js` (Alpine `mensagemLeitura()`)
- Modify: `resources/css/app.css` (balões/prose já do Task 3; ajustes do single)
- Test: `tests/Feature/Front/MensagemShowTest.php`

**Interfaces:**
- Consumes: `App\Models\Mensagem` (`publica`, `autores`, `relacionadas`, `getMedia('pictografia')`, `link_arquivo`, `liberar_download`, `contexto`, `data_recebimento`), `App\Enums\FormatoMensagem`.
- Produces: `MensagemController::show(string $slug): View` com `mensagem`, `mesmoDia`, meta/OG na view.

**Contexto:** molde [PalestranteController::show](../../../app/Http/Controllers/PalestranteController.php#L29-L69) (scope antes do `firstOrFail`; array associativo explícito) + [PalestraController](../../../app/Http/Controllers/PalestraController.php) (relacionadas). **O1:** psicografia **e** psicofonia = `{!! $corpo !!}` em `.cema-msg-prose` (o `corpo` já é saneado — [Mensagem.php:122-127](../../../app/Models/Mensagem.php#L122-L127)); psicofonia + nota; **sem** balões. **I4:** `contexto` com `{{ }}`. **I7:** download só se `liberar_download && link_arquivo`. **I8:** pictografia = `getMedia('pictografia')`. Recriar visual por SPEC §4.2 + `design_handoff_mensagem_single/`. F3 (screenshot 05-direcionada/destinatários) e F5 (favoritar/lida) **fora**.

- [ ] **Passo 1: Escrever o teste do single que falha**

`tests/Feature/Front/MensagemShowTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_publica_renderiza_200(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'paz-e-luz', 'titulo' => 'Paz e Luz']);

        $this->get(route('mensagens.show', $m->slug))->assertOk()->assertSee('Paz e Luz');
    }

    public function test_pendente_e_restrita_dao_404_nunca_403(): void
    {
        $pendente = Mensagem::factory()->pendente()->create(['slug' => 'pendente-x']);
        $restrita = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'restrita-x', 'titulo' => 'Segredo dos Diretores']);

        $this->get(route('mensagens.show', 'pendente-x'))->assertNotFound();
        $r = $this->get(route('mensagens.show', 'restrita-x'));
        $r->assertNotFound();
        $r->assertDontSee('Segredo dos Diretores');   // não vaza existência
    }

    public function test_contexto_e_escapado(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'ctx', 'contexto' => 'Nota <script>alert(1)</script> final']);

        $res = $this->get(route('mensagens.show', 'ctx'));
        $res->assertSee('Nota &lt;script&gt;', false);   // escapado
        $res->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_sem_autor_mostra_sem_assinatura(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'sa']);

        $this->get(route('mensagens.show', 'sa'))->assertSee('Sem assinatura');
    }

    public function test_dois_autores_aparecem(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'dois']);
        $a1 = AutorEspiritual::factory()->create(['nome' => 'Emmanuel']);
        $a2 = AutorEspiritual::factory()->create(['nome' => 'André Luiz']);
        $m->autores()->sync([$a1->id, $a2->id]);

        $this->get(route('mensagens.show', 'dois'))->assertSee('Emmanuel')->assertSee('André Luiz');
    }

    public function test_download_so_quando_liberado(): void
    {
        $com = Mensagem::factory()->publica()->create(['slug' => 'com-dl', 'liberar_download' => true, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);
        $sem = Mensagem::factory()->publica()->create(['slug' => 'sem-dl', 'liberar_download' => false, 'link_arquivo' => 'https://drive.google.com/file/d/1AbC/view']);

        $this->get(route('mensagens.show', 'com-dl'))->assertSee('Baixar arquivo');
        $this->get(route('mensagens.show', 'sem-dl'))->assertDontSee('Baixar arquivo');
    }

    public function test_recebidas_no_mesmo_dia_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'hoje', 'data_recebimento' => '2025-03-10']);
        Mensagem::factory()->publica()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Irmã do mesmo dia']);
        Mensagem::factory()->pendente()->create(['data_recebimento' => '2025-03-10', 'titulo' => 'Pendente do mesmo dia']);

        $res = $this->get(route('mensagens.show', 'hoje'));
        $res->assertSee('Irmã do mesmo dia');
        $res->assertDontSee('Pendente do mesmo dia');
    }

    public function test_relacionadas_so_publicas(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'rel']);
        $pub = Mensagem::factory()->publica()->create(['titulo' => 'Relacionada Pública']);
        $rest = Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores', 'titulo' => 'Relacionada Restrita']);
        $m->sincronizarRelacionadas([$pub->id, $rest->id]);

        $res = $this->get(route('mensagens.show', 'rel'));
        $res->assertSee('Relacionada Pública');
        $res->assertDontSee('Relacionada Restrita');
    }

    public function test_sem_f3_e_f5(): void
    {
        $m = Mensagem::factory()->publica()->create(['slug' => 'limpa']);

        $res = $this->get(route('mensagens.show', 'limpa'));
        foreach (['Nível de acesso', 'Mensagem direcionada', 'Favoritar', 'Curtir'] as $proibido) {
            $res->assertDontSee($proibido);
        }
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar** — `--filter=MensagemShowTest` ⇒ FAIL (`abort(501)`).

- [ ] **Passo 3: Implementar o `show`**

Em `app/Http/Controllers/MensagemController.php`:

```php
    public function show(string $slug): View
    {
        $mensagem = Mensagem::query()
            ->publica()
            ->with(['autores', 'media', 'relacionadas' => fn ($q) => $q->publica()->with('autores')])
            ->where('slug', $slug)
            ->firstOrFail();

        // "Recebidas no mesmo dia": outras públicas com a mesma data (só se houver data).
        $mesmoDia = $mensagem->data_recebimento
            ? Mensagem::query()->publica()->with('autores')
                ->whereDate('data_recebimento', $mensagem->data_recebimento->format('Y-m-d'))
                ->where('id', '!=', $mensagem->id)
                ->orderBy('titulo')
                ->get()
            : collect();

        return view('mensagens.show', [
            'mensagem' => $mensagem,
            'mesmoDia' => $mesmoDia,
            'relacionadas' => $mensagem->relacionadas,
        ]);
    }
```
(Adicionar `use App\Models\Mensagem;` ao controller.)

- [ ] **Passo 4: Criar a view + os 3 corpos + Alpine + SEO**

`resources/views/mensagens/show.blade.php` (recriar por SPEC §4.2 + handoff single; F3/F5 fora):
```blade
@php $url = route('mensagens.show', $mensagem->slug); $ogImg = $mensagem->getFirstMediaUrl('pictografia', 'web') ?: null; @endphp
<x-layout.app :title="$mensagem->titulo"
              :description="\Illuminate\Support\Str::limit(strip_tags($mensagem->contexto ?: $mensagem->corpo), 155)">
    <x-slot:head>
        <link rel="canonical" href="{{ $url }}">
        @if ($ogImg)<meta property="og:image" content="{{ $ogImg }}">@endif
        <script type="application/ld+json">
        @php echo json_encode(array_filter([
            '@context' => 'https://schema.org', '@type' => 'CreativeWork',
            'name' => $mensagem->titulo, 'url' => $url,
            'datePublished' => $mensagem->data_recebimento?->toDateString(),
            'author' => $mensagem->autores->pluck('nome')->all() ?: null,
        ], fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); @endphp
        </script>
    </x-slot:head>

    <div x-data="mensagemLeitura()">
        {{-- barra de progresso, hero (kicker/badge FIXO "Pública" sem cadeado/H1/chips autor N:N/data/formato),
             faixa contexto ({{ $mensagem->contexto }} ESCAPADO), toolbar (A-/A+/Copiar/Compartilhar — SEM favoritar) --}}
        @switch($mensagem->formato)
            @case(\App\Enums\FormatoMensagem::Psicografia) @include('mensagens.corpos.psicografia') @break
            @case(\App\Enums\FormatoMensagem::Psicofonia)  @include('mensagens.corpos.psicofonia')  @break
            @case(\App\Enums\FormatoMensagem::Pictografia) @include('mensagens.corpos.pictografia') @break
        @endswitch
        {{-- card autor(es) (@forelse ... @empty "Sem assinatura"), download (@if liberar_download && link_arquivo),
             sidebar: "Recebidas no mesmo dia" ($mesmoDia) + "Relacionadas" ($relacionadas) --}}
    </div>
</x-layout.app>
```
Corpos:
- `corpos/psicografia.blade.php` — `<div class="cema-msg-prose">{!! $mensagem->corpo !!}</div>` + assinatura (autor(es) + `{{ $mensagem->casa }}` + data).
- `corpos/psicofonia.blade.php` — **idêntico** + nota "Transcrição por psicofonia" (O1: prosa, sem balões).
- `corpos/pictografia.blade.php` — `@foreach ($mensagem->getMedia('pictografia') as $i => $img)` `<figure><img src="{{ $img->getUrl('web') }}" loading="lazy" alt="{{ $mensagem->titulo }}"><a href="{{ $img->getUrl() }}" download="{{ Str::slug($mensagem->titulo) }}-{{ $i+1 }}.jpg">Baixar</a></figure>` (R3).

`resources/js/app.js` — Alpine `mensagemLeitura()`: `progresso` (scroll+`requestAnimationFrame`), `passo`/tamanhos `[15.5,17,18.5,20]` persistidos em `localStorage`, `copiar()` (`navigator.clipboard.writeText`), `compartilhar()` (`navigator.share` + fallback `wa.me`), `toast`. Respeitar `prefers-reduced-motion` no progresso.

- [ ] **Passo 5: Build (HOST) + testes** — `npm run build`; `--filter=MensagemShowTest` ⇒ PASS.

- [ ] **Passo 6: Conferência visual + Pint + commit**

Abrir 3 slugs reais (uma de cada formato) em `http://localhost/mensagens-mediunicas/{slug}`; conferir contra `design_handoff_mensagem_single/screenshots/` (01-hero, 02-psicografia, 03-psicofonia, 04-pictografia). **404** numa pendente. Sem F3/F5.
```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/MensagemController.php tests/Feature/Front/MensagemShowTest.php
git add app/Http/Controllers/MensagemController.php resources/views/mensagens/show.blade.php resources/views/mensagens/corpos/ resources/js/app.js resources/css/app.css tests/Feature/Front/MensagemShowTest.php
git commit -m "feat(camada-4-fatia-2b): single de mensagem (3 corpos, psicofonia=prosa, download, sidebar) — 404 nao-publica"
```

---

### Task 5: Lista de autores — `AutorEspiritualController::index` + view + card de autor

**Files:**
- Modify: `app/Http/Controllers/AutorEspiritualController.php` (`index` real)
- Create: `resources/views/autores/index.blade.php`
- Create: `resources/views/components/autor/card.blade.php`
- Create: `resources/views/components/ui/onda-hero.blade.php` (se ainda não existir componente da onda)
- Test: `tests/Feature/Front/AutoresIndexTest.php`

**Interfaces:**
- Consumes: `App\Models\{AutorEspiritual,Mensagem}`, `FormatoMensagem`.
- Produces: `AutorEspiritualController::index(): View` com autores `ativo()` **com ≥1 pública** (O5a) + `withCount(publica)`, selos de formato distintos das públicas, mini-stats, destaque (O3).

**Contexto:** molde [Palestrantes\Lista](../../../app/Livewire/Palestrantes/Lista.php) (grade `withCount` alias+closure), mas **controller puro** (sem filtro/paginação — O7). Selos de formato = distinct das públicas. Mini-stats: total autores (com pública) + total mensagens públicas. Destaque (O3): autor com mais públicas (desempate por nome). Recriar visual por SPEC §4.3 + `design_handoff_autores_lista/` (tema claro; F5/dark fora).

- [ ] **Passo 1: Escrever o teste que falha**

`tests/Feature/Front/AutoresIndexTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoresIndexTest extends TestCase
{
    use RefreshDatabase;

    private function autorComPublicas(string $nome, int $qtd): AutorEspiritual
    {
        $autor = AutorEspiritual::factory()->create(['nome' => $nome, 'ativo' => true]);
        Mensagem::factory()->publica()->count($qtd)->create()->each(fn ($m) => $m->autores()->sync([$autor->id]));

        return $autor;
    }

    public function test_lista_so_ativo_com_publica(): void
    {
        $this->autorComPublicas('Emmanuel', 3);
        // ativo sem pública: OCULTO (O5a)
        AutorEspiritual::factory()->create(['nome' => 'Autor Vazio', 'ativo' => true]);
        // inativo: fora
        $inativo = AutorEspiritual::factory()->create(['nome' => 'Autor Inativo', 'ativo' => false]);
        Mensagem::factory()->publica()->create()->autores()->sync([$inativo->id]);

        $res = $this->get(route('autores.index'));
        $res->assertOk()->assertSee('Emmanuel');
        $res->assertDontSee('Autor Vazio');
        $res->assertDontSee('Autor Inativo');
    }

    public function test_contagem_so_das_publicas(): void
    {
        $autor = $this->autorComPublicas('Bezerra', 3);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'trabalhadores'])->autores()->sync([$autor->id], false);

        // "3 mensagens" (só públicas), não 4.
        $this->get(route('autores.index'))->assertSee('3 mensagens');
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar** — `--filter=AutoresIndexTest` ⇒ FAIL (`abort(501)`).

- [ ] **Passo 3: Implementar o `index`**

```php
    public function index(): View
    {
        $autores = AutorEspiritual::query()
            ->ativo()
            ->whereHas('mensagens', fn (Builder $q) => $q->publica())   // O5a: sem pública, some da grade
            ->withCount(['mensagens as mensagens_publicas_count' => fn (Builder $q) => $q->publica()])
            ->orderBy('nome')
            ->get();

        $totalMensagensPublicas = Mensagem::publica()->count();
        $destaque = $autores->sortByDesc('mensagens_publicas_count')->first();   // O3

        return view('autores.index', [
            'autores' => $autores,
            'totalAutores' => $autores->count(),
            'totalMensagensPublicas' => $totalMensagensPublicas,
            'destaque' => $destaque,
        ]);
    }
```
(Imports: `use App\Models\AutorEspiritual; use App\Models\Mensagem; use Illuminate\Contracts\View\View; use Illuminate\Database\Eloquent\Builder;`.) Os **selos de formato** por autor (distinct das públicas) podem ser resolvidos na view via `$autor->mensagens()->publica()->distinct()->pluck('formato')` ou pré-carregados no controller (evitar N+1 — pré-carregar recomendado).

- [ ] **Passo 4: Criar a view + card + onda**

`resources/views/autores/index.blade.php` — `<x-layout.app title="Autores Espirituais" description="Os espíritos que, pela mediunidade do CEMA, partilham mensagens de consolo e instrução.">` (R1) → hero (`<x-ui.onda-hero/>` + `<x-ui.particulas/>` + breadcrumb) + grade `<x-autor.card :autor="$a"/>` + sidebar (card institucional **estático** + mini-stats `$totalAutores`/`$totalMensagensPublicas` + destaque `$destaque`). `resources/views/components/autor/card.blade.php` — `@props(['autor'])`: foto 3:4 (`$autor->foto_url`) **ou** iniciais (`$autor->iniciais`) + `cema-grad-{{ $autor->id % 8 }}` (O4); nome; chamada; `{{ $autor->mensagens_publicas_count }} {{ $autor->mensagens_publicas_count === 1 ? 'mensagem' : 'mensagens' }}` (**B1 — nunca `Str::plural`**); pontinhos de formato. **Sem** curtir (F5).

- [ ] **Passo 5: Build (HOST) + testes** — `npm run build`; `--filter=AutoresIndexTest` ⇒ PASS.

- [ ] **Passo 6: Conferência visual + Pint + commit**

`http://localhost/autores-espirituais` vs `design_handoff_autores_lista/screenshots/` (tema claro; sem dark/curtir).
```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/AutorEspiritualController.php tests/Feature/Front/AutoresIndexTest.php
git add app/Http/Controllers/AutorEspiritualController.php resources/views/autores/index.blade.php resources/views/components/autor/ resources/views/components/ui/onda-hero.blade.php tests/Feature/Front/AutoresIndexTest.php
git commit -m "feat(camada-4-fatia-2b): lista de autores (so ativo com publica) + mini-stats + destaque"
```

---

### Task 6: Perfil de autor — `AutorEspiritualController::show` + `ResumoAutor` + view + Alpine

**Files:**
- Modify: `app/Http/Controllers/AutorEspiritualController.php` (`show` real)
- Create: `app/Support/AutoresEspirituais/ResumoAutor.php`
- Create: `resources/views/autores/show.blade.php`
- Modify: `resources/js/app.js` (Alpine `autorMensagens()` — filtro+ordenação)
- Test: `tests/Feature/Front/AutorShowTest.php`, `tests/Feature/Support/ResumoAutorTest.php` (molde [ResumoPerfilTest](../../../tests/Feature/Support/ResumoPerfilTest.php) — B2)

**Interfaces:**
- Consumes: `AutorEspiritual::mensagens()->publica()` (Task 1), `FormatoMensagem`, `ResumoAutor`.
- Produces: `AutorEspiritualController::show(string $slug): View` (404 inativo, **200 ativo-sem-pública**), `App\Support\AutoresEspirituais\ResumoAutor` (total/última/porFormato/predominante/selos).

**Contexto:** molde [PalestranteController::show](../../../app/Http/Controllers/PalestranteController.php#L29-L69) (ordena "recentes" em PHP; `$itensFiltro` p/ o Alpine) + [ResumoPerfil](../../../app/Support/Palestrantes/ResumoPerfil.php) (agregação em PHP, sem query na classe). **O5a:** `ativo()->firstOrFail()` (404 só inativo/inexistente); autor ativo **sem** pública → 200, grade vazia, stats zerados. Alpine estende o padrão de [palestranteDetalhe (app.js:5-30)](../../../resources/js/app.js#L5-L30) para **filtrar (chips) + ordenar**. Recriar por SPEC §4.4 + `design_handoff_autor_espiritual_perfil/`. **Sem** curtir/tile-curtidas (F5); rodapé de login **estático** (F3 fora).

- [ ] **Passo 1: Escrever os testes que falham**

`tests/Feature/Support/ResumoAutorTest.php` (B2 — molde `ResumoPerfilTest`: **Feature** + `RefreshDatabase` + factory; a classe `ResumoAutor` segue agregação **pura em PHP**, só o teste ganha o app):
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Support;

use App\Enums\FormatoMensagem;
use App\Models\Mensagem;
use App\Support\AutoresEspirituais\ResumoAutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumoAutorTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_predominante_e_ultima(): void
    {
        $mensagens = collect([
            Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'data_recebimento' => '2024-01-01']),
            Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'data_recebimento' => '2025-06-01']),
            Mensagem::factory()->publica()->create(['formato' => 'psicofonia', 'data_recebimento' => '2023-01-01']),
        ]);
        $r = new ResumoAutor($mensagens);

        $this->assertSame(3, $r->total());
        $this->assertSame(FormatoMensagem::Psicografia, $r->predominante());
        $this->assertSame('2025-06-01', $r->ultimaMensagem()->format('Y-m-d'));
    }
}
```

`tests/Feature/Front/AutorShowTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\AutorEspiritual;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutorShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inativo_da_404(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => false, 'slug' => 'inativo']);

        $this->get(route('autores.show', 'inativo'))->assertNotFound();
    }

    public function test_ativo_sem_publica_da_200(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'vazio', 'nome' => 'Autor Vazio']);

        $this->get(route('autores.show', 'vazio'))->assertOk()->assertSee('Autor Vazio');
    }

    public function test_grade_e_stats_so_das_publicas(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'emmanuel', 'nome' => 'Emmanuel']);
        Mensagem::factory()->publica()->create(['titulo' => 'Pública do Autor'])->autores()->sync([$a->id]);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'titulo' => 'Restrita do Autor'])->autores()->sync([$a->id], false);

        $res = $this->get(route('autores.show', 'emmanuel'));
        $res->assertSee('Pública do Autor');
        $res->assertDontSee('Restrita do Autor');
    }

    public function test_sem_curtir_e_com_link_login(): void
    {
        $a = AutorEspiritual::factory()->create(['ativo' => true, 'slug' => 'x']);

        $res = $this->get(route('autores.show', 'x'));
        $res->assertDontSee('Curtir');   // F5 fora (tile e botão)
        $res->assertSee(route('login'), false);   // rodapé estático de login
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar** — `--filter="AutorShowTest|ResumoAutorTest"` ⇒ FAIL (classes ainda não existem).

- [ ] **Passo 3: Criar `ResumoAutor` (clone de `ResumoPerfil`)**

`app/Support/AutoresEspirituais/ResumoAutor.php` (molde [ResumoPerfil](../../../app/Support/Palestrantes/ResumoPerfil.php)) — `__construct(private Collection $mensagens)`; `total(): int` (`->count()`); `ultimaMensagem(): ?Carbon` (`pluck('data_recebimento')->filter()->max()`); `porFormato(): Collection` (`groupBy(fn ($m) => $m->formato->value)->map(count + cor)->sortByDesc('count')`); `predominante(): ?FormatoMensagem` (`porFormato()` vazio → `null`; senão `FormatoMensagem::from($primeiraChave)`); `selos(): Collection` (formatos distintos p/ o hero). Agregação **em PHP**, sem query (o teste B2 injeta os modelos via factory; com o app booted o cast de `formato` já entrega o enum).

- [ ] **Passo 4: Implementar o `show`**

```php
    public function show(string $slug): View
    {
        $autor = AutorEspiritual::query()->ativo()->where('slug', $slug)->firstOrFail();   // 404 só inativo/inexistente

        $publicas = $autor->mensagens()->publica()->with('media')->get()
            ->sortByDesc(fn (Mensagem $m) => $m->data_recebimento?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoAutor($publicas);

        $itensFiltro = $publicas->map(fn (Mensagem $m) => [
            'id' => $m->id, 'titulo' => $m->titulo,
            'ts' => $m->data_recebimento?->getTimestamp(),
            'formato' => $m->formato?->value,
        ])->values();

        return view('autores.show', [
            'autor' => $autor,
            'mensagens' => $publicas,
            'resumo' => $resumo,
            'destaque' => $publicas->first(),   // mais recente pública (ou null)
            'itensFiltro' => $itensFiltro,
        ]);
    }
```

- [ ] **Passo 5: Criar a view + Alpine de filtro/ordenação**

`resources/views/autores/show.blade.php` — `<x-layout.app :title="$autor->nome" :description="\Illuminate\Support\Str::limit(strip_tags($autor->chamada ?: $autor->bio), 155) ?: 'Autor espiritual do CEMA'">` + slot `head` (canonical `route('autores.show',$autor->slug)`; og:image `$autor->foto_url` condicional; JSON-LD `Person`). Hero (foto/iniciais 3:4 + `cema-grad-{id%8}`; chamada; selos `$resumo->selos()`; CTA "Ver mensagens ↓") + **3 tiles** (`$resumo->total()`/`$resumo->predominante()?->rotulo()`/`$resumo->ultimaMensagem()?->translatedFormat('M/Y')` — **sem** curtidas) + grade (`x-data="autorMensagens({itens})"`, chips formato + select ordenar, `<x-mensagem.card :mensagem="$m" variante="perfil"/>` com `x-show`/`:style="{order}"`) + sidebar (destaque `$destaque`; formatos `$resumo->porFormato()`; compartilhar copiar-link) + **rodapé estático** ("Somente mensagens públicas… `<a href="{{ route('login') }}">entre</a>` para vê-los" — sem lógica de nível). `resources/js/app.js` — `autorMensagens({itens})`: estado `formato:'todos'`/`ordem:'recente'`; `visivel(id)` (chip) + `ordem(id)` (CSS `order`), no molde de `palestranteDetalhe` estendido para filtrar.

- [ ] **Passo 6: Build (HOST) + testes** — `npm run build`; `--filter="AutorShowTest|ResumoAutorTest"` ⇒ PASS.

- [ ] **Passo 7: Conferência visual + Pint + commit**

`http://localhost/autores-espirituais/{slug}` vs `design_handoff_autor_espiritual_perfil/screenshots/` (3 tiles sem curtidas; chips filtram+ordenam; rodapé login). Autor ativo sem pública → 200 (grade vazia). 
```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/AutorEspiritualController.php app/Support/AutoresEspirituais/ResumoAutor.php tests/Feature/Front/AutorShowTest.php tests/Feature/Support/ResumoAutorTest.php
git add app/Http/Controllers/AutorEspiritualController.php app/Support/AutoresEspirituais/ResumoAutor.php resources/views/autores/show.blade.php resources/js/app.js tests/Feature/Front/AutorShowTest.php tests/Feature/Support/ResumoAutorTest.php
git commit -m "feat(camada-4-fatia-2b): perfil de autor (grade publica, 3 tiles sem curtidas, ResumoAutor)"
```

---

### Task 7: Sitemap — Mensagens públicas + autores com pública

**Files:**
- Modify: `app/Http/Controllers/SitemapController.php` (2 coleções)
- Modify: `resources/views/sitemap.blade.php` (2 blocos)
- Test: `tests/Feature/Front/MensagemSitemapTest.php`, `tests/Feature/Front/AutorSitemapTest.php`

**Interfaces:**
- Consumes: `Mensagem::publica()`, `AutorEspiritual::ativo()->whereHas('mensagens', publica())`.
- Produces: `<url>` de `mensagens.index`/`autores.index` + os singles no `sitemap.xml`.

**Contexto:** molde [SitemapController:15-36](../../../app/Http/Controllers/SitemapController.php#L15-L36) (scope por tipo, `compact`, header xml) + o bloco de Eventos da [sitemap.blade.php](../../../resources/views/sitemap.blade.php) (`<url>` da listagem + `@foreach`). Teste no molde de [EventoSitemapTest](../../../tests/Feature/Front/EventoSitemapTest.php) (público entra, restrito fora). `Mensagem` **não** tem `data_publicacao` → lastmod = `updated_at`.

- [ ] **Passo 1: Escrever os testes que falham**

`tests/Feature/Front/MensagemSitemapTest.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Front;

use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclui_publica_e_exclui_nao_publica(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pub-msg']);
        Mensagem::factory()->pendente()->create(['slug' => 'pend-msg']);
        Mensagem::factory()->create(['status' => 'publicado', 'nivel' => 'diretores', 'slug' => 'rest-msg']);

        $res = $this->get('/sitemap.xml');
        $res->assertOk()->assertHeader('Content-Type', 'application/xml');
        $res->assertSee(route('mensagens.index'), false);
        $res->assertSee('/mensagens-mediunicas/pub-msg', false);
        $res->assertDontSee('/mensagens-mediunicas/pend-msg', false);
        $res->assertDontSee('/mensagens-mediunicas/rest-msg', false);
    }
}
```
`tests/Feature/Front/AutorSitemapTest.php` (autor ativo-com-pública entra; inativo e ativo-sem-pública fora — O5a).

- [ ] **Passo 2: Rodar e ver falhar** — FAIL (URLs ausentes).

- [ ] **Passo 3: Somar as coleções ao controller**

Em `SitemapController::index`, imports `use App\Models\Mensagem; use App\Models\AutorEspiritual; use Illuminate\Database\Eloquent\Builder;` e antes do `return`:
```php
$mensagens = Mensagem::publica()->orderByDesc('data_recebimento')->get(['slug', 'updated_at', 'data_recebimento']);
$autores = AutorEspiritual::ativo()
    ->whereHas('mensagens', fn (Builder $q) => $q->publica())   // O5a
    ->orderBy('nome')->get(['slug', 'updated_at']);
```
Incluir `'mensagens', 'autores'` no `compact(...)`.

- [ ] **Passo 4: Somar os blocos à view**

Em `resources/views/sitemap.blade.php`, no molde do bloco de Eventos: `<url>` de `route('mensagens.index')` (`changefreq weekly`, `priority 0.8`) + `@foreach ($mensagens as $m) <loc>{{ route('mensagens.show', $m->slug) }}</loc><lastmod>{{ $m->updated_at->toAtomString() }}</lastmod> ...`; idem `autores.index`/`autores.show`.

- [ ] **Passo 5: Testes** — `--filter="MensagemSitemapTest|AutorSitemapTest|EventoSitemapTest"` ⇒ PASS (o de Evento **não** muda de cor).

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/SitemapController.php tests/Feature/Front/MensagemSitemapTest.php tests/Feature/Front/AutorSitemapTest.php
git add app/Http/Controllers/SitemapController.php resources/views/sitemap.blade.php tests/Feature/Front/MensagemSitemapTest.php tests/Feature/Front/AutorSitemapTest.php
git commit -m "feat(camada-4-fatia-2b): sitemap com mensagens publicas + autores com publica"
```

---

### Task 8: SEO (canonical/OG/JSON-LD) — asserções dedicadas + regressão + suíte

**Files:**
- Test: `tests/Feature/Front/MensagemSeoTest.php`, `tests/Feature/Front/AutorSeoTest.php`
- (nenhum código novo — as views dos singles já injetam o slot `head` nas Tasks 4/6; esta task **prova** o SEO e fecha a suíte.)

**Interfaces:** valida I13 (canonical presente; og:image condicional; JSON-LD parseável).

**Contexto:** molde [BlogSeoTest](../../../tests/Feature/Front/BlogSeoTest.php). Se algum assert reprovar, ajustar a view do single (Task 4/6), não o teste.

- [ ] **Passo 1: Escrever os testes de SEO**

`tests/Feature/Front/MensagemSeoTest.php` — canonical = `route('mensagens.show', $slug)`; og:image **presente** numa pictografia com mídia / **ausente** numa psicografia sem mídia; `<script type="application/ld+json">` presente e `json_decode` do conteúdo é array com `@type CreativeWork`. `AutorSeoTest` — canonical do perfil; og:image = `foto_url` condicional; JSON-LD `Person`.

- [ ] **Passo 2: Rodar** — `--filter="MensagemSeoTest|AutorSeoTest"`. Se reprovar, corrigir a view do single. PASS.

- [ ] **Passo 3: Suíte completa + Pint global**

```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app php artisan test
```
Esperado: **~972 + novos** verdes; **nenhuma** asserção existente muda de cor; `pint --test` limpo. (Flaky do blog: [[flaky-importadorblog-gd-cap-imagem]] — se falhar isolado sob carga, revalidar isolado/CI.)

- [ ] **Passo 4: Conferência final no localhost (DoD)**

`npm run build` + `docker compose restart app worker`. Navegar as 4 páginas (lista, single dos 3 formatos, lista de autores, perfil), o 301 (`/mensagem-mediunicas/{slug}`), o `sitemap.xml`, e conferir **mobile/tablet/desktop** + `prefers-reduced-motion`. Zero F3/F5 visível.

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/MensagemSeoTest.php tests/Feature/Front/AutorSeoTest.php
git add tests/Feature/Front/MensagemSeoTest.php tests/Feature/Front/AutorSeoTest.php
git commit -m "test(camada-4-fatia-2b): SEO (canonical/OG/JSON-LD) + regressao verde"
```

---

### Task 9: PR

**Files:** nenhum (git/gh).

- [ ] **Passo 1: Push + abrir o PR**

```bash
git push -u origin camada-4-fatia-2b-front-mensagens
gh pr create --base main --title "Camada 4 · Fatia 2B — front público das Mensagens (4 páginas + 301 + sitemap)" --body "..."
```
Corpo do PR: objetivo (4 páginas públicas só-Públicas), o que **não** entra (F3/F5), invariantes I1–I14, cutover (§8 da SPEC — sem migration; `npm run build` + restart), e o link da SPEC/plano. **Rodapé:** 🤖 Generated with [Claude Code](https://claude.com/claude-code).

- [ ] **Passo 2: Merge só com CI verde no ÚLTIMO commit + go do dono**

Aguardar o CI fechar **verde no último commit** ([[merge-so-com-ci-verde-no-commit-final]]) e o **go do dono**. **Sem** cutover de dados (é front); no deploy: `git pull` + `npm run build` (host) + `php artisan optimize:clear` + `docker compose restart app worker`.

---

## Checkpoints (para o controller do subagente-driven-development)

Rodar a **suíte completa** nos checkpoints (não só o `--filter`), pois um teste de front pode tocar o layout global:
- **CP-1** (fim da Task 4): lista + single de mensagem no ar (`MensagemListaTest`, `MensagemShowTest`, `MensagemUrlCompatTest`, `AutorEspiritualMensagensTest`).
- **CP-2** (fim da Task 6): autores lista + perfil (`AutoresIndexTest`, `AutorShowTest`, `ResumoAutorTest`).
- **CP-3** (fim da Task 8): SEO + sitemap + **suíte completa verde** + conferência visual das 4 páginas.

Todo brief de subagente que rodar `artisan` **DEVE** proibir `migrate:fresh/refresh/wipe/reset` e seed destrutivo, e reafirmar `legado` como read-only ([[nunca-migrate-fresh-no-dev]]).
