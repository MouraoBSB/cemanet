# Listagem Pública de Palestras (archive) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reconstruir o front-end da listagem pública de palestras (`/palestra_publica`) fiel ao `design_handoff_palestras-archive`, evoluindo o componente `App\Livewire\Palestras\Lista` sem alterar o schema nem quebrar o contrato de testes.

**Architecture:** Abordagem A — evolução *in place*. O componente Livewire existente ganha os parâmetros `ano`/`video`/`visao` e ordenação `az`; as views (card grade, nova linha lista, container e index) são redesenhadas com os tokens já em `@theme`; o formato (Online/Presencial) vira accessor derivado do booleano `online`. Uma tarefa de preparação renomeia a rota `.ics` e registra o stub canônico `palestras.calendario`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Filament 5 · Tailwind v4 · MySQL 8 (dev) / SQLite `:memory:` (testes) · Docker.

**Branch:** `fase-2-palestras-archive` (já criada a partir de `main`; o spec já está commitado).

**Spec:** `docs/superpowers/specs/2026-06-30-listagem-palestras-archive-design.md`

## Global Constraints

- **Sem migração / sem mudança de schema** nesta fatia (formato é accessor; `ano`/`video`/`visao` são query params sobre colunas existentes).
- **Não renomear** propriedades/aliases existentes do `Lista`: `q`(q), `assunto`(assunto), `palestrante`(palestrante), `dataDe`(**de**), `dataAte`(**ate**), `ordenar`(ordenar, `except:'recente'`). Só adicionar `ano`(ano), `video`(video), `visao`(visao, `except:'grid'`).
- **`visao` e troca de página NÃO chamam `resetPage()`** — só os filtros de resultado (`q, assunto, palestrante, dataDe, dataAte, ordenar, ano, video`) resetam.
- **Card sem vídeo NUNCA emite `i.ytimg.com/vi/`** (contrato `PalestrasListagemTest:37`) e mantém o fallback `logo-icone`.
- Só `publicado()` visível; só palestrante `ativo()` + papel PALESTRANTE. Preservar os **301** de `/palestras` e `/palestras/{slug}`.
- Filtro de ano **portável (SQLite)**: `whereYear(...)`; dropdown de anos por **distinct em PHP** (nunca `YEAR()`/`selectRaw`). Filtro de vídeo: `whereNull`/`whereNotNull('link_youtube')` (é `NULL` sem vídeo). `ordenar='az'` → `orderBy('titulo')`.
- Busca fica em `LIKE`; **nenhum teste** exige acento-insensível no SQLite.
- Paginação **9 por página**.
- Tokens via classes utilitárias / `var(--color-*)` (**nunca** `theme()`); build `npm run build` no host.
- Testes por `docker compose exec -T app php artisan test`; **Pint antes de cada commit**; após editar Blade/PHP no dev, `docker compose restart app worker` (OPcache `validate_timestamps=0`).
- **PROIBIDO** `migrate:fresh/refresh/wipe/reset/db:wipe`/seed destrutivo. pt-BR em tudo.

**Comandos de referência:**
- Rodar um arquivo de teste: `docker compose exec -T app php artisan test --filter=NomeDaClasse`
- Suíte completa: `docker compose exec -T app php artisan test`
- Lint: `docker compose exec -T app ./vendor/bin/pint` (auto-fix) / `--test` (checar)
- Refletir Blade/PHP no localhost: `docker compose restart app worker`

---

## Estrutura de arquivos

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `routes/web.php` | Modificar | Rename `.ics`→`palestras.evento-ics`; stub `palestras.calendario`; constraint no `show`. |
| `app/Http/Controllers/CalendarioController.php` | Criar | Stub da página Calendário (lista próximas). |
| `resources/views/pages/calendario.blade.php` | Criar | Casca mínima e real do Calendário. |
| `app/Models/Palestra.php` | Modificar | Accessor `formato` (rótulo+cor a partir de `online`). |
| `resources/views/components/palestra/badge-formato.blade.php` | Criar | Badge de formato (variantes sólido/claro). |
| `app/Livewire/Palestras/Lista.php` | Modificar | `ano`/`video`/`az`/`paginate(9)`/`anos` (T3); `visao`/chips/`limparFiltros`/`removerFiltro`/`alternarVisao` (T4). |
| `resources/views/components/palestra/card.blade.php` | Reescrever | Card-pôster 16:10 (grade). |
| `resources/views/components/palestra/linha.blade.php` | Criar | Linha da visão lista. |
| `resources/views/livewire/palestras/lista.blade.php` | Reescrever | Barra de filtros, chips, resultados, switch grade/lista, vazio, paginação. |
| `resources/views/palestras/index.blade.php` | Modificar | Hero, breadcrumb, banner "Próxima palestra" (com countdown), "Veja também", JSON-LD. |
| `resources/css/palestras-archive.css` | Criar | Card hover, overlay do pôster, 8 gradientes, partículas. |
| `resources/css/app.css` | Modificar | `@import './palestras-archive.css';`. |
| `tests/Feature/Front/CalendarioPalestraTest.php` | Modificar | Rota renomeada `palestras.evento-ics`. |
| `tests/Feature/Front/PalestrasDestaqueTest.php` | Modificar | Cabeçalho "Próxima palestra". |
| `tests/Feature/Front/*` e `tests/Feature/Livewire/*` | Criar | Testes novos (por tarefa). |

---

## Task 1: Preparação de rota do Calendário (rename `.ics` + stub + constraint)

**Files:**
- Modify: `routes/web.php:1-21`
- Create: `app/Http/Controllers/CalendarioController.php`
- Create: `resources/views/pages/calendario.blade.php`
- Modify: `tests/Feature/Front/CalendarioPalestraTest.php:25,43,54`
- Test: `tests/Feature/Front/CalendarioStubTest.php`

**Interfaces:**
- Produces: rota nomeada `palestras.calendario` (GET `/palestra_publica/calendario`); rota `.ics` renomeada para `palestras.evento-ics`; `CalendarioController@index(): View`.

- [ ] **Step 1: Atualizar `CalendarioPalestraTest` para a rota renomeada e criar o teste do stub**

Em `tests/Feature/Front/CalendarioPalestraTest.php`, trocar as 3 ocorrências de `route('palestras.calendario', …)` por `route('palestras.evento-ics', …)` (linhas 25, 43, 54).

Criar `tests/Feature/Front/CalendarioStubTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioStubTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_calendario_responde_200(): void
    {
        $this->get('/palestra_publica/calendario')
            ->assertOk()
            ->assertSee('Calendário de Palestras');
    }

    public function test_rota_nomeada_aponta_para_a_pagina(): void
    {
        $this->assertSame(url('/palestra_publica/calendario'), route('palestras.calendario'));
    }

    public function test_single_ainda_responde_com_slug(): void
    {
        Palestra::factory()->create(['slug' => 'uma-palestra', 'status' => Palestra::STATUS_PUBLICADO]);

        $this->get('/palestra_publica/uma-palestra')->assertOk();
    }

    public function test_calendario_lista_palestra_futura(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra Futura',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(3),
        ]);

        $this->get('/palestra_publica/calendario')->assertSee('Palestra Futura');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter="CalendarioStubTest|CalendarioPalestraTest"`
Expected: FAIL — `palestras.evento-ics` inexistente e `/palestra_publica/calendario` cai no `show` (erro/404).

- [ ] **Step 3: Ajustar as rotas**

Em `routes/web.php`, adicionar o import e substituir o bloco de rotas de palestras (linhas 13-17) por:

```php
use App\Http\Controllers\CalendarioController;
```

```php
Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');

// Stub da página Calendário (nome canônico; a fatia do Calendário preenche o corpo depois).
// DEVE vir ANTES de palestras.show para não ser capturada por {slug}.
Route::get('/palestra_publica/calendario', [CalendarioController::class, 'index'])->name('palestras.calendario');

Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])
    ->name('palestras.show')
    ->where('slug', '[a-z0-9-]+');
Route::get('/palestra_publica/{slug}/calendario.ics', [PalestraController::class, 'calendario'])
    ->name('palestras.evento-ics')
    ->where('slug', '[a-z0-9-]+');
```

- [ ] **Step 4: Criar o controller do stub**

`app/Http/Controllers/CalendarioController.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-30

namespace App\Http\Controllers;

use App\Models\Palestra;
use Illuminate\Contracts\View\View;

class CalendarioController extends Controller
{
    /**
     * Stub da página de Calendário: lista as próximas palestras publicadas.
     * A fatia do módulo Calendário substitui o corpo por <livewire:palestras.calendario />.
     */
    public function index(): View
    {
        $proximas = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with('palestrantesAtivos')
            ->orderBy('data_da_palestra')
            ->get();

        return view('pages.calendario', ['proximas' => $proximas]);
    }
}
```

- [ ] **Step 5: Criar a casca da página**

`resources/views/pages/calendario.blade.php`:

```blade
<x-layout.app title="Calendário de Palestras" description="Agenda das próximas palestras públicas do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-14">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Agenda</p>
            <h1 class="mt-2 font-display text-3xl font-semibold sm:text-4xl">Calendário de Palestras</h1>
            <p class="mt-3 max-w-xl text-white/85">Próximas palestras públicas do CEMA.</p>
        </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        @if ($proximas->isEmpty())
            <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">
                Nenhuma palestra futura agendada no momento.
            </p>
        @else
            <ul class="flex flex-col gap-3">
                @foreach ($proximas as $palestra)
                    <li>
                        <a href="{{ route('palestras.show', $palestra->slug) }}"
                           class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-border-muted bg-white px-5 py-4 shadow-card transition hover:border-primary">
                            <time datetime="{{ $palestra->data_da_palestra->toIso8601String() }}"
                                  class="font-mono text-sm text-primary">{{ $palestra->data_da_palestra->translatedFormat('d \d\e M Y · H\hi') }}</time>
                            <span class="font-display font-semibold text-text-ink">{{ $palestra->titulo }}</span>
                            <span class="rounded-pill bg-surface px-2.5 py-0.5 text-xs text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout.app>
```

- [ ] **Step 6: Rodar e confirmar que passa**

Run: `docker compose exec -T app php artisan test --filter="CalendarioStubTest|CalendarioPalestraTest"`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add routes/web.php app/Http/Controllers/CalendarioController.php resources/views/pages/calendario.blade.php tests/Feature/Front/CalendarioStubTest.php tests/Feature/Front/CalendarioPalestraTest.php
git commit -m "feat(palestras/rota): stub palestras.calendario + rename .ics para palestras.evento-ics"
```

---

## Task 2: Accessor `formato` + componente de badge

**Files:**
- Modify: `app/Models/Palestra.php:104-109` (adicionar accessor perto de `slideDownloadUrl`)
- Create: `resources/views/components/palestra/badge-formato.blade.php`
- Test: `tests/Feature/Models/PalestraFormatoTest.php`

**Interfaces:**
- Produces: `Palestra->formato` → `array{slug:string, rotulo:string, cor:string}` (`cor` ∈ `secondary|accent`); `<x-palestra.badge-formato :palestra="…" variante="solido|claro" />`.

- [ ] **Step 1: Escrever o teste do accessor**

`tests/Feature/Models/PalestraFormatoTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraFormatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_gera_rotulo_e_cor(): void
    {
        $palestra = Palestra::factory()->make(['online' => true]);

        $this->assertSame('Online', $palestra->formato['rotulo']);
        $this->assertSame('secondary', $palestra->formato['cor']);
    }

    public function test_presencial_gera_rotulo_e_cor(): void
    {
        $palestra = Palestra::factory()->make(['online' => false]);

        $this->assertSame('Presencial', $palestra->formato['rotulo']);
        $this->assertSame('accent', $palestra->formato['cor']);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter=PalestraFormatoTest`
Expected: FAIL — `formato` indefinido.

- [ ] **Step 3: Adicionar o accessor**

Em `app/Models/Palestra.php`, após o método `slideDownloadUrl()` (linha ~109), adicionar:

```php
    /** Formato de exibição derivado do booleano `online` (sem coluna nova). */
    protected function formato(): Attribute
    {
        return Attribute::get(fn (): array => $this->online
            ? ['slug' => 'online', 'rotulo' => 'Online', 'cor' => 'secondary']
            : ['slug' => 'presencial', 'rotulo' => 'Presencial', 'cor' => 'accent']);
    }
```

(`Illuminate\Database\Eloquent\Casts\Attribute` já está importado — linha 9.)

- [ ] **Step 4: Criar o componente de badge**

`resources/views/components/palestra/badge-formato.blade.php`:

```blade
@props(['palestra', 'variante' => 'solido'])

@php($f = $palestra->formato)
@if ($variante === 'claro')
    <span class="inline-flex items-center gap-1.5 rounded-pill px-2.5 py-0.5 text-[11px] font-semibold"
          style="color: var(--color-{{ $f['cor'] }}); background-color: color-mix(in srgb, var(--color-{{ $f['cor'] }}) 14%, transparent);">
        {{ $f['rotulo'] }}
    </span>
@else
    <span class="inline-flex items-center gap-1.5 rounded-pill bg-white/20 px-2.5 py-0.5 text-[10px] font-semibold text-white backdrop-blur">
        {{ $f['rotulo'] }}
    </span>
@endif
```

> **Nota:** a cor usa `var(--color-secondary|accent)` inline (não `text-secondary`), porque classe Tailwind dinâmica seria purgada no v4.

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `docker compose exec -T app php artisan test --filter=PalestraFormatoTest`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Models/Palestra.php resources/views/components/palestra/badge-formato.blade.php tests/Feature/Models/PalestraFormatoTest.php
git commit -m "feat(palestra): accessor formato (Online/Presencial) + badge de formato"
```

---

## Task 3: `Lista` — filtros `ano`/`video`, ordenação `az`, paginação 9, `anos`

**Files:**
- Modify: `app/Livewire/Palestras/Lista.php:35-76`
- Test: `tests/Feature/Livewire/PalestrasFiltrosAvancadosTest.php`

**Interfaces:**
- Consumes: modelo `Palestra` (`publicado`, `data_da_palestra` cast datetime, `link_youtube`, `titulo`).
- Produces: props `$ano`(as `ano`), `$video`(as `video`); `ordenar` aceita `az`; `render()` pagina 9 e passa `anos` (Collection de anos desc); método público `anosDisponiveis(): Collection`.

- [ ] **Step 1: Escrever os testes**

`tests/Feature/Livewire/PalestrasFiltrosAvancadosTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasFiltrosAvancadosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_ano(): void
    {
        Palestra::factory()->create(['titulo' => 'De 2024', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2024-05-10 19:00:00']);
        Palestra::factory()->create(['titulo' => 'De 2026', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-05-10 19:00:00']);

        Livewire::test(Lista::class)
            ->set('ano', '2026')
            ->assertSee('De 2026')
            ->assertDontSee('De 2024');
    }

    public function test_filtra_por_video_com(): void
    {
        Palestra::factory()->create(['titulo' => 'Com Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);
        Palestra::factory()->create(['titulo' => 'Sem Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        Livewire::test(Lista::class)
            ->set('video', 'com')
            ->assertSee('Com Video')
            ->assertDontSee('Sem Video');
    }

    public function test_filtra_por_video_sem(): void
    {
        Palestra::factory()->create(['titulo' => 'Com Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);
        Palestra::factory()->create(['titulo' => 'Sem Video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        Livewire::test(Lista::class)
            ->set('video', 'sem')
            ->assertSee('Sem Video')
            ->assertDontSee('Com Video');
    }

    public function test_ordena_az(): void
    {
        $z = Palestra::factory()->create(['titulo' => 'Zelo e Fe', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2020-01-01 19:00:00']);
        $a = Palestra::factory()->create(['titulo' => 'Amor ao Proximo', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-01-01 19:00:00']);

        $ids = Livewire::test(Lista::class)
            ->set('ordenar', 'az')
            ->viewData('palestras')
            ->pluck('id')
            ->all();

        $this->assertSame([$a->id, $z->id], $ids);
    }

    public function test_anos_disponiveis_distintos_desc(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2024-05-10 19:00:00']);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-05-10 19:00:00']);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => '2026-08-10 19:00:00']);

        $anos = Livewire::test(Lista::class)->viewData('anos');

        $this->assertSame([2026, 2024], $anos->all());
    }

    public function test_pagina_traz_no_maximo_nove(): void
    {
        Palestra::factory()->count(11)->create(['status' => Palestra::STATUS_PUBLICADO]);

        $palestras = Livewire::test(Lista::class)->viewData('palestras');

        $this->assertCount(9, $palestras->items());
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter=PalestrasFiltrosAvancadosTest`
Expected: FAIL — props/anos inexistentes; paginação em 12.

- [ ] **Step 3: Adicionar props, `whereYear`/`video`/`az`, `paginate(9)`, `anos`**

Em `app/Livewire/Palestras/Lista.php`:

1. Após a prop `$ordenar` (linha 36), adicionar:

```php
    #[Url(as: 'ano', except: '')]
    public string $ano = '';

    #[Url(as: 'video', except: '')]
    public string $video = '';
```

2. No `updated()`, incluir `ano` e `video` na lista:

```php
    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar', 'ano', 'video'], true)) {
            $this->resetPage();
        }
    }
```

3. Adicionar o método `anosDisponiveis()` (antes de `render()`):

```php
    /** Anos distintos (desc) das palestras publicadas, para o filtro. Distinct em PHP (portável). */
    public function anosDisponiveis(): \Illuminate\Support\Collection
    {
        return Palestra::publicado()
            ->whereNotNull('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->year)
            ->unique()
            ->sortDesc()
            ->values();
    }
```

4. No `render()`, inserir os `when` de `ano`/`video` e substituir a ordenação/paginação:

```php
            ->when($this->dataAte !== '' && Carbon::hasFormat($this->dataAte, 'Y-m-d'), fn (Builder $query) => $query->whereDate('data_da_palestra', '<=', $this->dataAte))
            ->when($this->ano !== '' && ctype_digit($this->ano), fn (Builder $query) => $query->whereYear('data_da_palestra', (int) $this->ano))
            ->when($this->video === 'com', fn (Builder $query) => $query->whereNotNull('link_youtube'))
            ->when($this->video === 'sem', fn (Builder $query) => $query->whereNull('link_youtube'))
            ->when($this->ordenar === 'az',
                fn (Builder $query) => $query->orderBy('titulo'),
                fn (Builder $query) => $query->orderByRaw('data_da_palestra IS NULL, data_da_palestra '.($this->ordenar === 'antiga' ? 'asc' : 'desc')))
            ->paginate(9);
```

5. Passar `anos` para a view:

```php
        return view('livewire.palestras.lista', [
            'palestras' => $palestras,
            'palestrantes' => Palestrante::ativo()->orderBy('nome')->get(['nome', 'slug']),
            'assuntos' => Assunto::whereHas('palestras', fn (Builder $q) => $q->publicado())->orderBy('nome')->get(['nome', 'slug']),
            'anos' => $this->anosDisponiveis(),
        ]);
```

- [ ] **Step 4: Rodar e confirmar que passa (+ não regrediu)**

Run: `docker compose exec -T app php artisan test --filter="PalestrasFiltrosAvancadosTest|PalestrasFiltrosTest|PalestrasListaTest"`
Expected: PASS (os filtros antigos continuam verdes).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Livewire/Palestras/Lista.php tests/Feature/Livewire/PalestrasFiltrosAvancadosTest.php
git commit -m "feat(palestras/lista): filtros ano e video, ordenar A-Z, paginacao 9"
```

---

## Task 4: `Lista` — `visao`, chips, `limparFiltros`/`removerFiltro`/`alternarVisao`

**Files:**
- Modify: `app/Livewire/Palestras/Lista.php` (prop `visao`, métodos, `filtrosAtivos` no `render`)
- Test: `tests/Feature/Livewire/PalestrasVisaoChipsTest.php`

**Interfaces:**
- Consumes: props de filtro da Task 3.
- Produces: prop `$visao`(as `visao`, `except:'grid'`); `alternarVisao(string): void` (sem resetPage); `removerFiltro(string): void`; `limparFiltros(): void` (mantém `visao`); `filtrosAtivos(): array` (lista de `{chave, rotulo}`); `render()` passa `filtrosAtivos`.

- [ ] **Step 1: Escrever os testes**

`tests/Feature/Livewire/PalestrasVisaoChipsTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasVisaoChipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_visao_padrao_e_grid(): void
    {
        Livewire::test(Lista::class)->assertSet('visao', 'grid');
    }

    public function test_alternar_visao_para_lista(): void
    {
        Livewire::test(Lista::class)
            ->call('alternarVisao', 'list')
            ->assertSet('visao', 'list');
    }

    public function test_trocar_visao_nao_reseta_pagina(): void
    {
        Palestra::factory()->count(11)->create(['status' => Palestra::STATUS_PUBLICADO]);

        $c = Livewire::test(Lista::class)->call('gotoPage', 2);
        $this->assertSame(2, $c->viewData('palestras')->currentPage());

        $c->call('alternarVisao', 'list');
        $this->assertSame(2, $c->viewData('palestras')->currentPage());
    }

    public function test_filtro_gera_chip_e_remover_limpa(): void
    {
        $c = Livewire::test(Lista::class)->set('video', 'com');

        $chips = collect($c->instance()->filtrosAtivos());
        $this->assertTrue($chips->contains(fn ($chip) => $chip['chave'] === 'video'));

        $c->call('removerFiltro', 'video')->assertSet('video', '');
    }

    public function test_limpar_filtros_mantem_visao(): void
    {
        Livewire::test(Lista::class)
            ->set('visao', 'list')
            ->set('assunto', 'mediunidade')
            ->call('limparFiltros')
            ->assertSet('assunto', '')
            ->assertSet('visao', 'list');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter=PalestrasVisaoChipsTest`
Expected: FAIL — `visao`/métodos/`filtrosAtivos` inexistentes.

- [ ] **Step 3: Adicionar prop, métodos e `filtrosAtivos`**

Em `app/Livewire/Palestras/Lista.php`:

1. Após a prop `$video` (Task 3), adicionar:

```php
    #[Url(as: 'visao', except: 'grid')]
    public string $visao = 'grid';
```

2. Substituir `limparFiltros()` por:

```php
    public function limparFiltros(): void
    {
        // 'visao' preservada de propósito (preferência de exibição, não filtro).
        $this->reset(['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar', 'ano', 'video']);
        $this->resetPage();
    }

    public function removerFiltro(string $chave): void
    {
        $mapa = [
            'q' => 'q', 'assunto' => 'assunto', 'palestrante' => 'palestrante',
            'de' => 'dataDe', 'ate' => 'dataAte', 'ano' => 'ano', 'video' => 'video',
        ];

        if (isset($mapa[$chave])) {
            $this->{$mapa[$chave]} = '';
            $this->resetPage();
        }
    }

    public function alternarVisao(string $visao): void
    {
        // NÃO chama resetPage(): trocar a visão não deve voltar para a página 1.
        $this->visao = in_array($visao, ['grid', 'list'], true) ? $visao : 'grid';
    }

    public function filtrosAtivos(): array
    {
        $chips = [];

        if ($this->q !== '') {
            $chips[] = ['chave' => 'q', 'rotulo' => 'Título: “'.$this->q.'”'];
        }
        if ($this->assunto !== '') {
            $chips[] = ['chave' => 'assunto', 'rotulo' => 'Tema: '.(Assunto::where('slug', $this->assunto)->value('nome') ?? $this->assunto)];
        }
        if ($this->palestrante !== '') {
            $chips[] = ['chave' => 'palestrante', 'rotulo' => 'Palestrante: '.(Palestrante::where('slug', $this->palestrante)->value('nome') ?? $this->palestrante)];
        }
        if ($this->dataDe !== '') {
            $chips[] = ['chave' => 'de', 'rotulo' => 'De: '.$this->dataDe];
        }
        if ($this->dataAte !== '') {
            $chips[] = ['chave' => 'ate', 'rotulo' => 'Até: '.$this->dataAte];
        }
        if ($this->ano !== '') {
            $chips[] = ['chave' => 'ano', 'rotulo' => 'Ano: '.$this->ano];
        }
        if ($this->video !== '') {
            $chips[] = ['chave' => 'video', 'rotulo' => $this->video === 'com' ? 'Com vídeo' : 'Sem vídeo'];
        }

        return $chips;
    }
```

3. No `return view(...)` do `render()`, adicionar `filtrosAtivos`:

```php
            'anos' => $this->anosDisponiveis(),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
```

- [ ] **Step 4: Rodar e confirmar que passa**

Run: `docker compose exec -T app php artisan test --filter=PalestrasVisaoChipsTest`
Expected: PASS.

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Livewire/Palestras/Lista.php tests/Feature/Livewire/PalestrasVisaoChipsTest.php
git commit -m "feat(palestras/lista): visao grade/lista, chips de filtros ativos, limpar/remover"
```

---

## Task 5: Card-pôster (grade) + CSS da archive

**Files:**
- Rewrite: `resources/views/components/palestra/card.blade.php`
- Create: `resources/css/palestras-archive.css`
- Modify: `resources/css/app.css` (adicionar `@import`)
- Test: `tests/Feature/Front/PalestraCardArchiveTest.php`

**Interfaces:**
- Consumes: `<x-palestra.badge-formato>` (Task 2); `Palestra->youtube_thumb_hq` (existente, hqdefault); `palestrantesAtivos`, `assuntos`.
- Produces: card grade com eyebrow "Palestra Pública", badge de formato, título overlay, chip de palestrante, rodapé data+tema+CTA "Ver".

- [ ] **Step 1: Escrever o teste do card**

`tests/Feature/Front/PalestraCardArchiveTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraCardArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_com_video_usa_hqdefault(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Palestra Com Video',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtu.be/ABCdef12345',
        ]);

        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('hqdefault.jpg', false)
            ->assertSee('Palestra Pública', false);
    }

    public function test_card_sem_video_nao_emite_capa_youtube(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('logo-icone', false)
            ->assertDontSee('i.ytimg.com/vi/', false);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter=PalestraCardArchiveTest`
Expected: FAIL — card ainda usa `mqdefault` (thumb) e não tem o eyebrow.

- [ ] **Step 3: Reescrever o card**

`resources/views/components/palestra/card.blade.php`:

```blade
@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb_hq;
    $data = $palestra->data_da_palestra;
    $palestrante = $palestra->palestrantesAtivos->first();
    $tema = $palestra->assuntos->first();
    $grad = $palestra->id % 8;
@endphp
<article {{ $attributes->class(['cema-talk-card group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        {{-- Pôster 16:10 --}}
        <div class="cema-poster cema-grad-{{ $grad }} relative aspect-[16/10] overflow-hidden">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy" width="480" height="360"
                     class="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @endif
            <div class="cema-poster__overlay absolute inset-0 flex flex-col justify-between p-4">
                <div class="flex items-start justify-between gap-2">
                    <span class="font-mono text-[9.5px] font-medium uppercase tracking-[0.14em] text-white/80">Palestra Pública</span>
                    <x-palestra.badge-formato :palestra="$palestra" />
                </div>
                <div>
                    <h3 class="cema-poster__titulo font-display text-lg font-bold leading-tight text-white">{{ $palestra->titulo }}</h3>
                    @if ($palestrante)
                        <span class="mt-2 inline-flex items-center gap-2 rounded-pill bg-black/25 py-1 pl-1 pr-3 backdrop-blur-sm">
                            <span class="flex size-6 items-center justify-center overflow-hidden rounded-full bg-white/90">
                                @if ($palestrante->foto_thumb_url)
                                    <img src="{{ $palestrante->foto_thumb_url }}" alt="" class="size-full object-cover">
                                @else
                                    <span class="font-display text-[10px] font-semibold text-primary">{{ collect(explode(' ', $palestrante->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') }}</span>
                                @endif
                            </span>
                            <span class="text-[11.5px] font-medium text-white">{{ $palestrante->nome }}</span>
                        </span>
                    @endif
                </div>
            </div>
            @unless ($thumb)
                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" aria-hidden="true"
                     class="pointer-events-none absolute right-3 top-3 h-6 w-auto opacity-70">
            @endunless
        </div>
        {{-- Rodapé --}}
        <div class="flex flex-1 items-center justify-between gap-2 px-4 py-3.5">
            <div class="flex flex-wrap items-center gap-2 text-[11px] text-text-muted">
                @if ($data)
                    <time datetime="{{ $data->toIso8601String() }}" class="inline-flex items-center gap-1">
                        <span aria-hidden="true">📅</span>{{ $data->translatedFormat('d \d\e M Y') }}
                    </time>
                @endif
                @if ($tema)
                    <span class="rounded-pill bg-[#EFEBF7] px-2 py-0.5 text-[11px] text-[#6a6390]">{{ $tema->nome }}</span>
                @endif
            </div>
            <span class="cema-talk-cta inline-flex items-center gap-1.5 rounded-pill bg-cream px-3.5 py-1.5 text-[12.5px] font-medium text-primary transition">Ver</span>
        </div>
    </a>
</article>
```

- [ ] **Step 4: Criar o CSS da archive e importar no app.css**

`resources/css/palestras-archive.css`:

```css
/* Palestras — listagem (archive). Thiago Mourão — https://github.com/MouraoBSB — 2026-06-30 */

.cema-talk-card { transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
.cema-talk-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(46, 41, 82, .18); border-color: #E2D9C2; }
.cema-talk-cta:hover,
.cema-talk-card:hover .cema-talk-cta { background-color: var(--color-primary); color: #fff; }

.cema-poster { background: var(--grad, linear-gradient(140deg, var(--color-primary), var(--color-footer-bg))); }
.cema-poster__overlay { background: linear-gradient(180deg, rgba(0,0,0,.12) 0%, rgba(0,0,0,.04) 40%, rgba(0,0,0,.55) 100%); }
.cema-poster__titulo { text-shadow: 0 2px 12px rgba(0,0,0,.32); text-wrap: balance; }

.cema-grad-0 { --grad: linear-gradient(140deg, #4E4483, #6c5fae); }
.cema-grad-1 { --grad: linear-gradient(140deg, #6E9FCB, #3f6fa0); }
.cema-grad-2 { --grad: linear-gradient(140deg, #89AB98, #5e8770); }
.cema-grad-3 { --grad: linear-gradient(140deg, #3a3266, #5b5191); }
.cema-grad-4 { --grad: linear-gradient(140deg, #C87FB0, #9c5688); }
.cema-grad-5 { --grad: linear-gradient(140deg, #D98A6A, #b5663f); }
.cema-grad-6 { --grad: linear-gradient(140deg, #5d6bb0, #3a4585); }
.cema-grad-7 { --grad: linear-gradient(140deg, #2c7a6b, #185c4f); }

/* Hero da archive: brilho decorativo sutil */
.cema-archive-particles {
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(circle at 78% 22%, rgba(110,159,203,.25), transparent 45%),
        radial-gradient(circle at 12% 80%, rgba(242,168,30,.12), transparent 40%);
}

@keyframes cemaFadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
.cema-fade-up { animation: cemaFadeUp .4s ease both; }

@media (prefers-reduced-motion: reduce) {
    .cema-talk-card, .cema-fade-up { transition: none; animation: none; }
    .cema-talk-card:hover { transform: none; }
}
```

Em `resources/css/app.css`, logo após a linha `@import './conteudo.css';`, adicionar:

```css
@import './palestras-archive.css';
```

- [ ] **Step 5: Build dos assets + refletir Blade**

```bash
npm run build
docker compose restart app worker
```

- [ ] **Step 6: Rodar e confirmar que passa (+ contrato intacto)**

Run: `docker compose exec -T app php artisan test --filter="PalestraCardArchiveTest|PalestrasListagemTest|PalestrasListaTest"`
Expected: PASS — inclusive `logo-icone` e ausência de `i.ytimg.com/vi/` sem vídeo, título e palestrante ativo.

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/components/palestra/card.blade.php resources/css/palestras-archive.css resources/css/app.css tests/Feature/Front/PalestraCardArchiveTest.php
git commit -m "feat(palestras/card): card-poster 16:10 (grade) + CSS da archive"
```

---

## Task 6: Container da lista + componente linha (visão grade/lista)

**Files:**
- Rewrite: `resources/views/livewire/palestras/lista.blade.php`
- Create: `resources/views/components/palestra/linha.blade.php`
- Test: `tests/Feature/Livewire/PalestrasListaViewTest.php`

**Interfaces:**
- Consumes: props/`filtrosAtivos`/`anos` do `Lista` (T3/T4); `<x-palestra.card>` (T5); `<x-palestra.badge-formato>` (T2).
- Produces: barra de filtros completa; chips; linha de resultados; switch grade↔lista; estado vazio; paginação.

- [ ] **Step 1: Escrever os testes de view**

`tests/Feature/Livewire/PalestrasListaViewTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasListaViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_barra_tem_ordenar_az_e_filtros_novos(): void
    {
        Livewire::test(Lista::class)
            ->assertSee('Título (A–Z)')
            ->assertSee('Com vídeo')
            ->assertSeeHtml('wire:model.live="ano"');
    }

    public function test_visao_grade_renderiza_card(): void
    {
        Palestra::factory()->create(['titulo' => 'Palestra Y', 'status' => Palestra::STATUS_PUBLICADO]);

        Livewire::test(Lista::class)->assertSee('Palestra Pública');
    }

    public function test_visao_lista_renderiza_linha(): void
    {
        Palestra::factory()->create(['titulo' => 'Palestra X', 'status' => Palestra::STATUS_PUBLICADO]);

        Livewire::test(Lista::class)
            ->set('visao', 'list')
            ->assertSee('Ver palestra')
            ->assertDontSee('Palestra Pública');
    }

    public function test_chip_e_limpar_tudo(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => 'https://youtu.be/ABCdef12345']);

        Livewire::test(Lista::class)
            ->set('video', 'com')
            ->assertSee('Filtros ativos:')
            ->assertSee('Com vídeo')
            ->assertSee('Limpar tudo');
    }

    public function test_estado_vazio(): void
    {
        Livewire::test(Lista::class)
            ->set('q', 'inexistente-xyz')
            ->assertSee('Nenhuma palestra encontrada');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter=PalestrasListaViewTest`
Expected: FAIL — a view atual não tem ordenar A–Z, filtro vídeo, chips nem visão lista.

- [ ] **Step 3: Criar o componente linha**

`resources/views/components/palestra/linha.blade.php`:

```blade
@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb_hq;
    $data = $palestra->data_da_palestra;
    $palestrante = $palestra->palestrantesAtivos->first();
    $tema = $palestra->assuntos->first();
    $grad = $palestra->id % 8;
@endphp
<article {{ $attributes->class(['cema-talk-card group flex overflow-hidden rounded-[14px] border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex w-full items-stretch">
        <div class="cema-poster cema-grad-{{ $grad }} relative w-[130px] shrink-0 overflow-hidden sm:w-[150px]">
            @if ($thumb)
                <img src="{{ $thumb }}" alt="" loading="lazy" width="150" height="110" class="absolute inset-0 size-full object-cover">
            @else
                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" aria-hidden="true"
                     class="absolute left-1/2 top-1/2 h-8 w-auto -translate-x-1/2 -translate-y-1/2 opacity-80">
            @endif
        </div>
        <div class="flex flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-[12px] text-text-muted">
                    @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                    <x-palestra.badge-formato :palestra="$palestra" variante="claro" />
                    @if ($tema)<span class="rounded-pill bg-[#EFEBF7] px-2 py-0.5 text-[11px] text-[#6a6390]">{{ $tema->nome }}</span>@endif
                </div>
                <h3 class="mt-1 font-display text-[16.5px] font-semibold leading-snug text-text-ink group-hover:text-primary">{{ $palestra->titulo }}</h3>
                @if ($palestrante)<p class="mt-0.5 text-[13px] text-text-muted">com {{ $palestrante->nome }}</p>@endif
            </div>
            <span class="cema-talk-cta inline-flex shrink-0 items-center gap-1.5 rounded-pill bg-cream px-4 py-2 text-[13px] font-medium text-primary transition">Ver palestra</span>
        </div>
    </a>
</article>
```

- [ ] **Step 4: Reescrever o container da lista**

`resources/views/livewire/palestras/lista.blade.php`:

```blade
<div>
    {{-- Barra de filtros --}}
    <div class="rounded-2xl border border-border-muted bg-white p-5 shadow-card sm:px-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 class="inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                Filtrar palestras
                <span class="rounded-pill bg-cream px-2.5 py-0.5 text-xs font-medium text-primary">Total {{ $palestras->total() }}</span>
            </h2>
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-xs text-text-muted">
                    Ordenar:
                    <select wire:model.live="ordenar" class="rounded-md border border-border-muted bg-surface px-2.5 py-1.5 text-sm text-text-ink">
                        <option value="recente">Mais recentes</option>
                        <option value="antiga">Mais antigas</option>
                        <option value="az">Título (A–Z)</option>
                    </select>
                </label>
                <div class="inline-flex overflow-hidden rounded-md border border-border-muted" role="group" aria-label="Modo de exibição">
                    <button type="button" wire:click="alternarVisao('grid')" aria-label="Grade" aria-pressed="{{ $visao === 'grid' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center text-sm', 'bg-white text-primary shadow-sm' => $visao === 'grid', 'bg-transparent text-text-muted' => $visao !== 'grid'])>▦</button>
                    <button type="button" wire:click="alternarVisao('list')" aria-label="Lista" aria-pressed="{{ $visao === 'list' ? 'true' : 'false' }}"
                            @class(['flex size-8 items-center justify-center text-sm', 'bg-white text-primary shadow-sm' => $visao === 'list', 'bg-transparent text-text-muted' => $visao !== 'list'])>☰</button>
                </div>
            </div>
        </div>

        <div class="grid gap-3.5 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            <div>
                <label for="f-de" class="mb-1 block text-xs text-text-muted">De</label>
                <input id="f-de" type="date" wire:model.live="dataDe" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ate" class="mb-1 block text-xs text-text-muted">Até</label>
                <input id="f-ate" type="date" wire:model.live="dataAte" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ano" class="mb-1 block text-xs text-text-muted">Ano</label>
                <select id="f-ano" wire:model.live="ano" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($anos as $a)
                        <option value="{{ $a }}">{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-palestrante" class="mb-1 block text-xs text-text-muted">Palestrante</label>
                <select id="f-palestrante" wire:model.live="palestrante" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($palestrantes as $p)
                        <option value="{{ $p->slug }}">{{ $p->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-tema" class="mb-1 block text-xs text-text-muted">Tema</label>
                <select id="f-tema" wire:model.live="assunto" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($assuntos as $a)
                        <option value="{{ $a->slug }}">{{ $a->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-video" class="mb-1 block text-xs text-text-muted">Vídeo</label>
                <select id="f-video" wire:model.live="video" class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="com">Com vídeo</option>
                    <option value="sem">Sem vídeo</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="f-titulo" class="mb-1 block text-xs text-text-muted">Título</label>
                <input id="f-titulo" type="search" wire:model.live.debounce.350ms="q" placeholder="Buscar por título…"
                       class="w-full rounded-[9px] border border-border-muted bg-surface px-3 py-2 text-sm">
            </div>
        </div>

        @if (count($filtrosAtivos) > 0)
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-border-muted pt-4">
                <span class="text-xs text-text-muted">Filtros ativos:</span>
                @foreach ($filtrosAtivos as $chip)
                    <span class="inline-flex items-center gap-1.5 rounded-pill bg-cream py-1 pl-3 pr-1 text-[12.5px] text-[#4a4663]">
                        {{ $chip['rotulo'] }}
                        <button type="button" wire:click="removerFiltro('{{ $chip['chave'] }}')" aria-label="Remover filtro {{ $chip['rotulo'] }}"
                                class="flex size-[18px] items-center justify-center rounded-full bg-primary/10 text-[13px] text-primary hover:bg-primary/20">×</button>
                    </span>
                @endforeach
                <button type="button" wire:click="limparFiltros" class="ml-auto text-[13px] font-medium text-secondary hover:underline">Limpar tudo</button>
            </div>
        @endif
    </div>

    @if ($palestras->total() > 0)
        <p class="mb-5 mt-6 text-[13.5px] text-text-muted">Mostrando {{ $palestras->firstItem() }}–{{ $palestras->lastItem() }} de {{ $palestras->total() }} palestra(s)</p>
    @endif

    @if ($palestras->isEmpty())
        <div class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex size-16 items-center justify-center rounded-full bg-cream text-2xl" aria-hidden="true">🔍</div>
            <h3 class="font-display text-lg font-semibold text-primary">Nenhuma palestra encontrada</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-text-muted">Ajuste o período, o tema ou a busca para ver mais resultados.</p>
            <button type="button" wire:click="limparFiltros" class="mt-5 rounded-pill bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary/90">Limpar filtros</button>
        </div>
    @elseif ($visao === 'list')
        <div class="flex flex-col gap-3.5">
            @foreach ($palestras as $palestra)
                <x-palestra.linha :palestra="$palestra" wire:key="linha-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $palestras->onEachSide(1)->links() }}</div>
    @else
        <div class="grid gap-[22px] sm:grid-cols-2 desktop-sm:grid-cols-3">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="card-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-9">{{ $palestras->onEachSide(1)->links() }}</div>
    @endif
</div>
```

- [ ] **Step 5: Refletir Blade no dev**

```bash
docker compose restart app worker
```

- [ ] **Step 6: Rodar e confirmar que passa (+ contrato de filtros/listagem)**

Run: `docker compose exec -T app php artisan test --filter="PalestrasListaViewTest|PalestrasFiltrosTest|PalestrasFiltrosAvancadosTest|PalestrasVisaoChipsTest|PalestrasListagemTest"`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/livewire/palestras/lista.blade.php resources/views/components/palestra/linha.blade.php tests/Feature/Livewire/PalestrasListaViewTest.php
git commit -m "feat(palestras/lista): barra de filtros redesenhada, chips, visao lista e vazio"
```

---

## Task 7: Index (hero + breadcrumb + banner "Próxima palestra" + "Veja também" + JSON-LD)

**Files:**
- Modify: `resources/views/palestras/index.blade.php` (reescrever)
- Modify: `tests/Feature/Front/PalestrasDestaqueTest.php:22`
- Test: `tests/Feature/Front/PalestrasArchiveSeoTest.php`

**Interfaces:**
- Consumes: `$proxima` (do `PalestraController@index`, com `palestrantesAtivos`); `<x-ui.countdown :data="…" />` (não compacta → aria-label "Contagem regressiva para a palestra"); rotas `palestras.calendario` (T1), `palestrantes.index`, `blog.index` (existentes); `<x-slot:head>` do `x-layout.app`.
- Produces: página `palestras.index` redesenhada com breadcrumb JSON-LD.

- [ ] **Step 1: Ajustar o teste de destaque e escrever o de SEO**

Em `tests/Feature/Front/PalestrasDestaqueTest.php`, linha 22, trocar:

```php
$resp->assertSeeText('Próximas Palestras');
```
por:
```php
$resp->assertSeeText('Próxima palestra');
```

Criar `tests/Feature/Front/PalestrasArchiveSeoTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrasArchiveSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_tem_breadcrumb_jsonld(): void
    {
        $this->get(route('palestras.index'))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_veja_tambem_aponta_rotas_reais(): void
    {
        $resp = $this->get(route('palestras.index'));

        $resp->assertSee(route('palestrantes.index'), false);
        $resp->assertSee(route('blog.index'), false);
        $resp->assertSee(route('palestras.calendario'), false);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `docker compose exec -T app php artisan test --filter="PalestrasArchiveSeoTest|PalestrasDestaqueTest"`
Expected: FAIL — sem JSON-LD/"Veja também"; cabeçalho ainda "Próximas Palestras".

- [ ] **Step 3: Reescrever a index**

`resources/views/palestras/index.blade.php`:

```blade
<x-layout.app title="Palestras Públicas" description="Palestras públicas do Centro Espírita Maria Madalena (CEMA): reflexões à luz do Espiritismo, abertas a todos.">
    <x-slot:head>
        <script type="application/ld+json">
            @json([
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Palestras', 'item' => url('/palestra_publica')],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => 'Palestras Públicas'],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-[#0b1030] to-footer-bg text-white">
        <div class="cema-archive-particles" aria-hidden="true"></div>
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-[#9db8e0]">Centro Espírita Maria Madalena</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Palestras Públicas</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Reflexões à luz do Espiritismo, abertas a todos.</p>
            </div>
            <a href="{{ route('palestras.calendario') }}"
               class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="text-2xl text-gold" aria-hidden="true">📅</span>
                <span class="font-display font-semibold">Calendário de Palestras</span>
            </a>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('palestras.index') }}" class="hover:text-primary">Palestras</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary">Palestras Públicas</span>
        </div>
    </nav>

    {{-- Destaque: Próxima palestra --}}
    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        @php($ptema = $proxima->assuntos->first())
        <section class="mx-auto max-w-[1240px] px-6 pt-12" aria-label="Próxima palestra">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próxima palestra
            </p>
            <div class="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#3a3266] via-primary to-[#5b4f92] p-6 text-white sm:p-8">
                <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-7">
                    <span class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/15 ring-4 ring-white/20">
                        @if ($pp?->foto_thumb_url)
                            <img src="{{ $pp->foto_thumb_url }}" alt="{{ $pp->nome }}" width="96" height="96" class="size-full object-cover">
                        @elseif ($pp)
                            <span class="font-display text-2xl font-semibold">{{ collect(explode(' ', $pp->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') }}</span>
                        @endif
                    </span>
                    <div class="flex-1 text-center sm:text-left">
                        @if ($proxima->data_da_palestra)
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-xs font-semibold text-[#3a2f00]">
                                {{ $proxima->data_da_palestra->translatedFormat('d \d\e M') }} · {{ $proxima->data_da_palestra->format('H\hi') }}
                            </span>
                        @endif
                        <h2 class="mt-3 font-display text-2xl font-semibold">{{ $proxima->titulo }}</h2>
                        @if ($pp || $ptema)
                            <p class="mt-1 text-white/80">@if ($pp)com {{ $pp->nome }}@endif@if ($pp && $ptema) · @endif@if ($ptema){{ $ptema->nome }}@endif</p>
                        @endif
                        <div class="mt-4 flex justify-center sm:justify-start">
                            <x-ui.countdown :data="$proxima->data_da_palestra" />
                        </div>
                    </div>
                    <a href="{{ route('palestras.show', $proxima->slug) }}"
                       class="shrink-0 rounded-pill bg-white px-6 py-3 font-semibold text-primary transition hover:bg-cream">Ver palestra</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Listagem --}}
    <section class="mx-auto max-w-[1240px] px-6 py-12">
        <livewire:palestras.lista />
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestrantes', route('palestrantes.index')], ['Calendário de Palestras', route('palestras.calendario')], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layout.app>
```

> **Nota:** `$proxima->assuntos` é acessado sem eager-load (1 consulta para 1 registro — não é N+1). Se preferir, o `PalestraController@index` pode carregar `assuntos` no `$proxima`; não é obrigatório para esta fatia.

- [ ] **Step 4: Refletir Blade no dev**

```bash
docker compose restart app worker
```

- [ ] **Step 5: Rodar e confirmar que passa**

Run: `docker compose exec -T app php artisan test --filter="PalestrasArchiveSeoTest|PalestrasDestaqueTest"`
Expected: PASS — JSON-LD BreadcrumbList presente; "Veja também" com rotas reais; cabeçalho "Próxima palestra"; countdown mantido (linhas 33/43 da destaque).

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/palestras/index.blade.php tests/Feature/Front/PalestrasDestaqueTest.php tests/Feature/Front/PalestrasArchiveSeoTest.php
git commit -m "feat(palestras/index): hero, breadcrumb, banner Proxima palestra, Veja tambem + JSON-LD"
```

---

## Task 8: Verificação final (suíte completa + Pint + visual)

**Files:** nenhum novo (fecha a fatia).

- [ ] **Step 1: Suíte COMPLETA**

Run: `docker compose exec -T app php artisan test`
Expected: PASS — sem regressão de publicadas/rascunho, palestrante ativo, `logo-icone`/`i.ytimg.com/vi/`, slug/301, rota renomeada `.ics`, filtros, chips, visão, SEO.

Se algum teste de contrato quebrar por markup (não por semântica), ajustar o **seletor** do teste mantendo a **asserção** e re-rodar.

- [ ] **Step 2: Lint do repositório inteiro**

Run: `docker compose exec -T app ./vendor/bin/pint --test`
Se acusar, `docker compose exec -T app ./vendor/bin/pint` e commitar as correções.

- [ ] **Step 3: Build + reflect + verificação visual no localhost**

```bash
npm run build
docker compose restart app worker
```

Conferir em `http://localhost:8000/palestra_publica` (hard refresh Ctrl+Shift+R):
- Hero roxo + atalho "Calendário" (leva a `/palestra_publica/calendario`, 200).
- Breadcrumb.
- Banner "Próxima palestra" com contagem regressiva (quando há futura).
- Barra de filtros: De/Até/Ano/Palestrante/Tema/Vídeo/Título; Ordenar (inclui A–Z); toggle grade/lista.
- Chips ao filtrar + "Limpar tudo"; "Mostrando N–M de T".
- Alternar grade ↔ lista; card-pôster 16:10 (gradiente quando sem vídeo, thumb quando há); 9 por página.
- Estado vazio (busca sem resultado).
- "Veja também" com 3 links reais.
- **Mobile** (DevTools ~390px): grade 1 coluna, filtros empilham, lista vira compacta.

- [ ] **Step 4: Commit final (se houve ajuste de Pint/seletor)**

```bash
git add -A
git commit -m "chore(palestras/archive): ajustes finais de lint e seletores de teste"
```

---

## Self-Review (do autor do plano)

**Cobertura do spec:** hero+breadcrumb+banner+Veja também+JSON-LD (T7); barra de filtros+chips+resultados+vazio+paginação 9+grade/lista (T3,T4,T6); card 16:10 + linha (T5,T6); accessor formato (T2); rename `.ics`+stub calendário+constraint (T1); testes de contrato ajustados + novos (todas as tasks) + suíte completa (T8). Sem lacunas.

**Sem placeholders:** todos os steps de código mostram o código completo; comandos e expectativas explícitos.

**Consistência de tipos/nomes:** props `dataDe`(de)/`dataAte`(ate)/`ano`/`video`/`visao`/`ordenar` idênticas entre T3/T4/T6; `formato` = `{slug,rotulo,cor}` usado por `badge-formato` (T2) no card/linha (T5/T6); `filtrosAtivos` (`{chave,rotulo}`) produzido em T4 e consumido em T6; `youtube_thumb_hq` (existente) usado em T5/T6; rota `palestras.calendario` (stub, T1) consumida em T7.
