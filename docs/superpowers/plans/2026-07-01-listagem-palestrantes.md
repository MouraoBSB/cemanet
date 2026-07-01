# Redesign da Listagem de Palestrantes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesenhar a listagem pública de `/palestrantes` na nova identidade — hero + grade de cards (avatar da foto ou **iniciais** em gradiente + badge de contagem), busca reativa + ordenação, sidebar (stats reais + "Em destaque"), estado vazio e paginação — reusando o back-end existente, **sem** filtro de área.

**Architecture:** Casca Blade (`palestrantes/index.blade.php`) = hero + breadcrumb + `<livewire:palestrantes.lista />` + sidebar (estática, dados do controller). O componente Livewire `Palestrantes\Lista` (estendido) cuida da grade/busca/ordenação/paginação. O card `x-palestrante.card` é redesenhado (único consumidor). CSS próprio reusa os 8 gradientes `cema-grad-*` da archive. Tudo SSR (Livewire), sem dependências novas, sem migração.

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire v4.3.2 · Filament 5 · Tailwind v4 · Vite · MySQL 8 (dev) / SQLite `:memory:` (testes) · Docker.

## Global Constraints

Toda tarefa herda estas regras (valores do spec §14):

- **Sem dependências novas. Sem migração/schema. Escopo SEM área** — nenhuma coluna/enum/campo Filament de área; sem chips/dots/"Explorar por área"/linha de área no card.
- **Reusar (não recriar):** scope `->ativo()` (boolean); alias `palestras_ministradas_count` (papel=palestrante + status publicado) via `withCount`; accessors Spatie `foto_url`/`foto_thumb_url` (não `foto_path`); gradientes `cema-grad-{n}` (n = `id % 8`); `<x-ui.particulas>`, `<x-layout.app title description>` + `<x-slot:head>`; paginação `->onEachSide(1)->links()` (Tailwind default, sem view custom).
- **Estado espelha a archive** (`Palestras\Lista`): `#[Url(as:'q', except:'')] $q`; `#[Url(as:'ordenar', except:'az')] $ordenar` valores `az|za|mais|menos`; `updated(string $name)` agrupando `resetPage()`; `limparFiltros()` via `$this->reset([...])`; `filtrosAtivos(): array`; `WithPagination` `paginate(12)`; **grade só** (sem toggle grid/list).
- **Ordenação:** `az`→`orderBy('nome')`; `za`→`orderBy('nome','desc')`; `mais`→`orderByDesc('palestras_ministradas_count')->orderBy('nome')`; `menos`→`orderBy('palestras_ministradas_count')->orderBy('nome')`. Via `match` com `default => orderBy('nome')` (cobre `az` + inválido).
- **Big-bang:** stats da sidebar e "Em destaque" vêm de query real; **"Em destaque" sem fallback** (some quando não há próxima futura — padrão `$proxima` do Calendário). Nada de "em breve"/placeholder.
- **Rota inalterada:** `{slug}` + `firstOrFail()->ativo()` (NÃO `{palestrante:slug}`); cards só linkam para `palestrantes.show` (já existe).
- **Portabilidade SQLite:** `where … like`, `whereYear/whereMonth`, distinct/agrupamento em PHP; **nada** de `selectRaw`/`YEAR()`/`DATE_FORMAT()`. Testes de busca usam substring **sem acento**.
- **`wire:key`** estável em todo `@foreach`/`@forelse` (`palestrante-{id}`).
- Tokens via utilitários/`var(--color-*)` (nunca `theme()`); `@media (prefers-reduced-motion)` nas animações. Build `npm run build` (host); `docker compose restart app worker` no dev (OPcache).
- Testes por `docker compose exec -T app php artisan test` (**suíte completa no fecho**; CI já roda tudo). **Pint** antes de cada commit. 🚫 PROIBIDO `migrate:fresh`/`migrate:refresh`/`db:wipe`/`migrate:reset`/seed destrutivo (dev tem 127 palestras + 44 posts + palestrantes).
- **Livewire v4.3.2:** testes usam `viewData($key)` + `assertSet`/`assertSee`; **não existe** `assertViewHas`.
- pt-BR com acentos; cabeçalho de autoria `Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01` nos arquivos **PHP** novos relevantes (componentes/views Blade seguem a convenção do projeto: sem header). Commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

**Base da branch:** `fase-4-palestrantes` a partir de `main` (merge commit do calendário `1cd5c4c`).

---

### Task 1: Accessor `iniciais` no model `Palestrante`

Adiciona o accessor `iniciais` (1ª letra das 2 primeiras palavras do nome, maiúsculas) — fallback do avatar quando não há foto.

**Files:**
- Modify: `app/Models/Palestrante.php`
- Create (test): `tests/Feature/Models/PalestranteIniciaisTest.php`

**Interfaces:**
- Consumes: `Palestrante->nome` (fillable).
- Produces: `Palestrante->iniciais` (string; nunca vazio — `'?'` quando o nome é vazio).

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Models/PalestranteIniciaisTest.php`

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Tests\TestCase;

class PalestranteIniciaisTest extends TestCase
{
    public function test_pega_iniciais_das_duas_primeiras_palavras(): void
    {
        $this->assertSame('KM', (new Palestrante(['nome' => 'Kátia Malaquias']))->iniciais);
    }

    public function test_nome_de_uma_palavra_gera_uma_letra(): void
    {
        $this->assertSame('W', (new Palestrante(['nome' => 'Wagner']))->iniciais);
    }

    public function test_ignora_espacos_extras_e_pega_so_as_duas_primeiras(): void
    {
        $this->assertSame('AB', (new Palestrante(['nome' => '  Ana   Beatriz Costa ']))->iniciais);
    }

    public function test_nome_vazio_gera_interrogacao(): void
    {
        $this->assertSame('?', (new Palestrante(['nome' => '   ']))->iniciais);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteIniciaisTest`
Expected: FAIL (accessor `iniciais` inexistente → retorna null).

- [ ] **Step 3: Implementar o accessor** — `app/Models/Palestrante.php`

Adicionar após o accessor `fotoThumbUrl()` (antes de `bio()`):

```php
    /** Iniciais (1ª letra das 2 primeiras palavras do nome), maiúsculas — fallback do avatar. */
    protected function iniciais(): Attribute
    {
        return Attribute::get(function (): string {
            $palavras = preg_split('/\s+/', trim((string) $this->nome), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $letras = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($palavras, 0, 2));

            return $letras === [] ? '?' : implode('', $letras);
        });
    }
```

(`Attribute` já está importado no model.)

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteIniciaisTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/Palestrante.php tests/Feature/Models/PalestranteIniciaisTest.php
git add app/Models/Palestrante.php tests/Feature/Models/PalestranteIniciaisTest.php
git commit -m "$(cat <<'EOF'
feat(palestrantes): accessor iniciais (fallback de avatar)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `Palestrantes\Lista` — ordenação + `updated`/`limparFiltros`/`filtrosAtivos`

Estende o componente atual (que já tem `q` + `withCount`) com o estado de ordenação e as ações, espelhando `Palestras\Lista`.

**Files:**
- Modify: `app/Livewire/Palestrantes/Lista.php`
- Modify (test): `tests/Feature/Livewire/PalestrantesListaTest.php` (adiciona métodos; preserva os 2 existentes)

**Interfaces:**
- Consumes: `Palestrante::ativo()`, alias `palestras_ministradas_count` (já no `withCount`), `Palestra::PAPEL_PALESTRANTE`/`STATUS_PUBLICADO`/`STATUS_RASCUNHO`.
- Produces: props públicas `$q`, `$ordenar`; métodos `updated(string $name)`, `limparFiltros()`, `filtrosAtivos(): array`. `render()` passa à view `palestrantes` (Paginator, 12/pág) e `filtrosAtivos` (array).

- [ ] **Step 1: Escrever os testes que falham** — adicionar a `tests/Feature/Livewire/PalestrantesListaTest.php`

Adicionar estes métodos dentro da classe existente `PalestrantesListaTest` (mantém `test_busca_filtra_por_nome` e `test_busca_nunca_traz_inativo_mesmo_com_nome_correspondente`). Garanta os `use` no topo: `use App\Models\Palestra;`.

```php
    public function test_default_ordena_az_e_paginacao_12(): void
    {
        Palestrante::factory()->create(['nome' => 'Bruno']);
        Palestrante::factory()->create(['nome' => 'Ana']);
        Palestrante::factory()->create(['nome' => 'Carlos']);

        $pag = Livewire::test(Lista::class)->viewData('palestrantes');

        $this->assertSame(['Ana', 'Bruno', 'Carlos'], collect($pag->items())->pluck('nome')->all());
        $this->assertSame(12, $pag->perPage());
    }

    public function test_paginacao_limita_a_12_por_pagina(): void
    {
        Palestrante::factory()->count(13)->create();

        $pag = Livewire::test(Lista::class)->viewData('palestrantes');

        $this->assertSame(13, $pag->total());
        $this->assertCount(12, $pag->items());
    }

    public function test_ordenar_za_inverte(): void
    {
        Palestrante::factory()->create(['nome' => 'Ana']);
        Palestrante::factory()->create(['nome' => 'Bruno']);

        $pag = Livewire::test(Lista::class)->set('ordenar', 'za')->viewData('palestrantes');

        $this->assertSame(['Bruno', 'Ana'], collect($pag->items())->pluck('nome')->all());
    }

    public function test_ordenar_mais_e_menos_por_contagem_ignorando_diretor_e_rascunho(): void
    {
        $ana = Palestrante::factory()->create(['nome' => 'Ana']);   // 2 palestras publicadas como palestrante
        $bruno = Palestrante::factory()->create(['nome' => 'Bruno']); // 0 que contam

        $ana->palestras()->attach([
            Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id,
            Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id,
        ], ['papel' => Palestra::PAPEL_PALESTRANTE]);

        // Bruno: uma como DIRETOR (não conta) e uma RASCUNHO como palestrante (não conta) → count 0
        $bruno->palestras()->attach(Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO])->id, ['papel' => Palestra::PAPEL_DIRETOR]);
        $bruno->palestras()->attach(Palestra::factory()->create(['status' => Palestra::STATUS_RASCUNHO])->id, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $mais = Livewire::test(Lista::class)->set('ordenar', 'mais')->viewData('palestrantes');
        $this->assertSame(['Ana', 'Bruno'], collect($mais->items())->pluck('nome')->all());
        $this->assertSame(0, collect($mais->items())->firstWhere('nome', 'Bruno')->palestras_ministradas_count);
        $this->assertSame(2, collect($mais->items())->firstWhere('nome', 'Ana')->palestras_ministradas_count);

        $menos = Livewire::test(Lista::class)->set('ordenar', 'menos')->viewData('palestrantes');
        $this->assertSame(['Bruno', 'Ana'], collect($menos->items())->pluck('nome')->all());
    }

    public function test_updated_reseta_pagina(): void
    {
        Palestrante::factory()->count(20)->create();

        // updated('q') → resetPage(): estava na pág. 2, volta para a 1.
        $c = Livewire::test(Lista::class)->set('page', 2)->set('q', 'z');

        $this->assertSame(1, $c->viewData('palestrantes')->currentPage());
    }

    public function test_limpar_filtros_zera_q_e_ordenar(): void
    {
        Livewire::test(Lista::class)
            ->set('q', 'algo')
            ->set('ordenar', 'za')
            ->call('limparFiltros')
            ->assertSet('q', '')
            ->assertSet('ordenar', 'az');
    }

    public function test_filtros_ativos_reflete_a_busca(): void
    {
        $c = Livewire::test(Lista::class);
        $this->assertSame([], $c->viewData('filtrosAtivos'));

        $c->set('q', 'ana');
        $ativos = $c->viewData('filtrosAtivos');
        $this->assertCount(1, $ativos);
        $this->assertSame('q', $ativos[0]['chave']);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantesListaTest`
Expected: FAIL (sem prop `ordenar`, sem `limparFiltros`/`filtrosAtivos`; `viewData('filtrosAtivos')` inexistente).

- [ ] **Step 3: Reescrever o componente** — `app/Livewire/Palestrantes/Lista.php`

Substituir o corpo da classe (mantendo os `use` + acrescentando `Illuminate\Support\Collection` não é necessário):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestrantes;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'ordenar', except: 'az')]
    public string $ordenar = 'az';

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'ordenar'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['q', 'ordenar']);
        $this->resetPage();
    }

    /** @return list<array{chave:string, rotulo:string}> */
    public function filtrosAtivos(): array
    {
        $chips = [];
        if ($this->q !== '') {
            $chips[] = ['chave' => 'q', 'rotulo' => 'Nome: “'.$this->q.'”'];
        }

        return $chips;
    }

    public function render()
    {
        $query = Palestrante::query()
            ->ativo()
            ->when($this->q !== '', fn (Builder $q) => $q->where('nome', 'like', '%'.$this->q.'%'))
            ->withCount(['palestras as palestras_ministradas_count' => function (Builder $q) {
                $q->where('palestra_pessoa.papel', Palestra::PAPEL_PALESTRANTE)
                    ->where('palestras.status', Palestra::STATUS_PUBLICADO);
            }]);

        match ($this->ordenar) {
            'za' => $query->orderBy('nome', 'desc'),
            'mais' => $query->orderByDesc('palestras_ministradas_count')->orderBy('nome'),
            'menos' => $query->orderBy('palestras_ministradas_count')->orderBy('nome'),
            default => $query->orderBy('nome'), // az + qualquer valor inválido via URL
        };

        return view('livewire.palestrantes.lista', [
            'palestrantes' => $query->paginate(12),
            'filtrosAtivos' => $this->filtrosAtivos(),
        ]);
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantesListaTest`
Expected: PASS (2 antigos + 7 novos = 9). A view atual (antiga) continua renderizando (usa `$palestrantes`; ignora `$filtrosAtivos`).

- [ ] **Step 5: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Livewire/Palestrantes/Lista.php tests/Feature/Livewire/PalestrantesListaTest.php
git add app/Livewire/Palestrantes/Lista.php tests/Feature/Livewire/PalestrantesListaTest.php
git commit -m "$(cat <<'EOF'
feat(palestrantes/lista): ordenacao az/za/mais/menos + limparFiltros/filtrosAtivos

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Redesign do card `x-palestrante.card`

Reescreve o card (único consumidor é a lista): avatar 188px (foto ou gradiente + iniciais) + badge de contagem + nome + botão "Ver palestras". Card inteiro é `<a>` → perfil.

**Files:**
- Modify (rewrite): `resources/views/components/palestrante/card.blade.php`
- Create (test): `tests/Feature/Front/PalestranteCardTest.php`

**Interfaces:**
- Consumes: `Palestrante->foto_url`, `Palestrante->iniciais` (Task 1), `Palestrante->palestras_ministradas_count` (alias do render; ausente → 0), `route('palestrantes.show', slug)`, classe `cema-grad-{id%8}` (CSS na Task 6), `.cema-spk-*` (CSS na Task 6).
- Produces: componente `<x-palestrante.card :palestrante="$p" />`.

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Front/PalestranteCardTest.php`

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestranteCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_foto_renderiza_iniciais_e_gradiente_por_indice(): void
    {
        // slug explícito (sem número) para que o '12' asserido seja só o do badge, não o do slug.
        $p = Palestrante::factory()->create(['nome' => 'Divino Gabriel', 'slug' => 'divino-gabriel']);
        $p->palestras_ministradas_count = 12; // atributo dinâmico (alias do render) p/ o badge

        $view = $this->blade('<x-palestrante.card :palestrante="$p" />', ['p' => $p]);

        $view->assertSee('DG', false);                       // iniciais (fallback)
        $view->assertSee('cema-grad-'.($p->id % 8), false);  // gradiente rotacionado por índice
        $view->assertSee('12', false);                        // badge de contagem
        $view->assertSee(route('palestrantes.show', $p->slug), false); // link para o perfil
        $view->assertDontSee('<img', false);                  // sem foto → não emite <img>
    }

    public function test_badge_zero_quando_sem_contagem(): void
    {
        $p = Palestrante::factory()->create(['nome' => 'Ana Sem Palestra']);

        $view = $this->blade('<x-palestrante.card :palestrante="$p" />', ['p' => $p]);

        $view->assertSee('Ana Sem Palestra', false);
        $view->assertSee('Ver palestras', false);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteCardTest`
Expected: FAIL (o card atual usa fallback "CEMA"/bio, não "DG"/`cema-grad-*`; sem badge; ainda pode emitir `<img>`/estrutura antiga).

- [ ] **Step 3: Reescrever o card** — `resources/views/components/palestrante/card.blade.php`

```blade
@props(['palestrante'])

@php($contagem = $palestrante->palestras_ministradas_count ?? 0)
<a href="{{ route('palestrantes.show', $palestrante->slug) }}"
   class="cema-spk-card group flex flex-col overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
    {{-- Topo: foto ou gradiente + iniciais --}}
    <div class="cema-spk-avatar cema-grad-{{ $palestrante->id % 8 }} relative h-[188px] w-full overflow-hidden">
        @if ($palestrante->foto_url)
            <img src="{{ $palestrante->foto_url }}" alt="" loading="lazy" width="212" height="188"
                 class="size-full object-cover">
        @else
            <span class="flex size-full items-center justify-center font-display text-[54px] font-semibold text-white/90" aria-hidden="true">{{ $palestrante->iniciais }}</span>
        @endif
        <span class="absolute right-2.5 top-2.5 inline-flex items-center gap-1 rounded-pill bg-black/[0.28] px-2.5 py-1 text-xs font-semibold text-white backdrop-blur">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 15a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3Zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.92V22h2v-3.08A7 7 0 0 0 19 12h-2Z"/></svg>
            {{ $contagem }}
        </span>
    </div>
    {{-- Corpo: nome + botão --}}
    <div class="flex flex-1 flex-col gap-3 p-4">
        <h3 class="font-display text-[16.5px] font-semibold text-text-ink">{{ $palestrante->nome }}</h3>
        <span class="cema-spk-cta mt-auto inline-flex w-fit items-center gap-1.5 rounded-pill bg-cream px-4 py-2 text-sm font-semibold text-primary transition">
            Ver palestras
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
    </div>
</a>
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteCardTest`
Expected: PASS (2 testes).

- [ ] **Step 5: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/PalestranteCardTest.php
git add resources/views/components/palestrante/card.blade.php tests/Feature/Front/PalestranteCardTest.php
git commit -m "$(cat <<'EOF'
feat(palestrantes/card): avatar (foto ou iniciais em gradiente) + badge de contagem + botao

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: View da lista — toolbar + resultados + grade + vazio + paginação

Reescreve a view do componente com a toolbar (busca + ordenar), a linha de resultados (contagem + "Limpar filtros"), a grade de cards, o estado vazio e a paginação.

**Files:**
- Modify (rewrite): `resources/views/livewire/palestrantes/lista.blade.php`
- Modify (test): `tests/Feature/Livewire/PalestrantesListaTest.php` (adiciona 3 testes de markup)

**Interfaces:**
- Consumes: `$palestrantes` (Paginator) e `$filtrosAtivos` (array) do `render()` (Task 2); `<x-palestrante.card>` (Task 3); ações `limparFiltros`, `$set`.
- Produces: markup da lista (toolbar/grade/vazio/paginação) com `wire:key="palestrante-{id}"`.

- [ ] **Step 1: Escrever os testes que falham** — adicionar a `tests/Feature/Livewire/PalestrantesListaTest.php`

```php
    public function test_view_tem_toolbar_grade_e_wire_key(): void
    {
        Palestrante::factory()->create(['nome' => 'Ana Souza']);

        Livewire::test(Lista::class)
            ->assertSee('Buscar palestrante')                 // label/placeholder da busca
            ->assertSee('Ordenar')                            // rótulo do select
            ->assertSee('Ana Souza')                          // card na grade
            ->assertSeeHtml('wire:key="palestrante-');        // wire:key no @foreach
    }

    public function test_view_conta_resultados(): void
    {
        Palestrante::factory()->count(3)->create();

        Livewire::test(Lista::class)->assertSee('3 palestrantes');
    }

    public function test_view_estado_vazio_com_limpar(): void
    {
        Livewire::test(Lista::class)
            ->set('q', 'zzznaoexistequalquercoisa')
            ->assertSee('Nenhum palestrante encontrado')
            ->assertSee('Limpar filtros');
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="PalestrantesListaTest::test_view"`
Expected: FAIL (a view antiga tem "Buscar palestrante…" mas não "Ordenar", nem a contagem "N palestrantes", nem "Limpar filtros"; o vazio antigo diz "Nenhum palestrante encontrado." com ponto e sem botão).

- [ ] **Step 3: Reescrever a view** — `resources/views/livewire/palestrantes/lista.blade.php`

```blade
<div>
    {{-- Toolbar --}}
    <div class="rounded-2xl border border-border-muted bg-white p-[18px] shadow-card">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-md">
                <label for="busca-palestrantes" class="sr-only">Buscar palestrante pelo nome</label>
                <svg class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                <input id="busca-palestrantes" type="search" wire:model.live.debounce.300ms="q"
                       placeholder="Buscar palestrante pelo nome…"
                       class="w-full rounded-pill border border-border bg-surface py-2.5 pl-11 pr-10 font-sans text-sm text-text outline-none focus:border-primary">
                @if ($q !== '')
                    <button type="button" wire:click="$set('q', '')" aria-label="Limpar busca"
                            class="absolute right-3 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-full text-text-muted transition hover:bg-surface hover:text-text-ink">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                    </button>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <label for="ordenar-palestrantes" class="text-sm text-text-muted">Ordenar:</label>
                <select id="ordenar-palestrantes" wire:model.live="ordenar"
                        class="rounded-[10px] border border-border bg-white px-3 py-2 text-sm text-text-secondary outline-none focus:border-primary">
                    <option value="az">Nome (A–Z)</option>
                    <option value="za">Nome (Z–A)</option>
                    <option value="mais">Mais palestras</option>
                    <option value="menos">Menos palestras</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Linha de resultados --}}
    <div class="mt-4 flex items-center justify-between">
        <p class="text-sm text-text-muted">{{ $palestrantes->total() }} {{ \Illuminate\Support\Str::plural('palestrante', $palestrantes->total()) }}</p>
        @if (! empty($filtrosAtivos))
            <button type="button" wire:click="limparFiltros" class="text-sm font-medium text-secondary transition hover:text-primary">Limpar filtros</button>
        @endif
    </div>

    {{-- Grade / estado vazio --}}
    @if ($palestrantes->isEmpty())
        <div class="mt-6 rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-16 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-cream text-primary" aria-hidden="true">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
            </span>
            <p class="mt-3 text-lg font-semibold text-text-secondary">Nenhum palestrante encontrado</p>
            <p class="mt-1 text-sm text-text-muted">Tente outro nome ou limpe a busca.</p>
            <button type="button" wire:click="limparFiltros" class="mt-4 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Limpar filtros</button>
        </div>
    @else
        <div class="mt-6 grid grid-cols-[repeat(auto-fill,minmax(212px,1fr))] gap-[18px]">
            @foreach ($palestrantes as $palestrante)
                <x-palestrante.card :palestrante="$palestrante" wire:key="palestrante-{{ $palestrante->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestrantes->onEachSide(1)->links() }}</div>
    @endif
</div>
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantesListaTest`
Expected: PASS (todos — 2 antigos + 7 da Task 2 + 3 de markup = 12). Os testes de busca antigos seguem verdes (a busca continua funcionando).

- [ ] **Step 5: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Livewire/PalestrantesListaTest.php
git add resources/views/livewire/palestrantes/lista.blade.php tests/Feature/Livewire/PalestrantesListaTest.php
git commit -m "$(cat <<'EOF'
feat(palestrantes/lista): toolbar (busca+ordenar) + grade + estado vazio + paginacao

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Casca da página + `PalestranteController@index` (hero + breadcrumb + sidebar + JSON-LD)

Reescreve a casca com hero na nova identidade, breadcrumb, o Livewire, a sidebar (intro + stats reais + "Em destaque" sem fallback) e o JSON-LD. O controller passa os dados da sidebar.

**Files:**
- Modify: `app/Http/Controllers/PalestranteController.php` (`index()`)
- Modify (rewrite): `resources/views/palestrantes/index.blade.php`
- Modify (test): `tests/Feature/Front/PalestrantesListagemTest.php` (adiciona hero/stats/destaque/JSON-LD; preserva o existente)

**Interfaces:**
- Consumes: `<livewire:palestrantes.lista />` (Tasks 2/4), `<x-ui.particulas>`, `<x-layout.app>`/`<x-slot:head>`, `route('palestras.calendario')`/`palestras.show`.
- Produces: `index()` passa `totalColaboradores` (int), `totalAcervo` (int), `proxima` (`?Palestra`); a casca renderiza tudo.

- [ ] **Step 1: Escrever os testes que falham** — adicionar a `tests/Feature/Front/PalestrantesListagemTest.php`

Garanta os `use` no topo: `use App\Models\Palestra;` e `use Illuminate\Support\Carbon;`.

```php
    public function test_index_tem_hero_stats_e_jsonld(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Wagner Alberto']);

        $resp = $this->get(route('palestrantes.index'));

        $resp->assertOk();
        $resp->assertSee('Palestrantes');                        // H1 do hero
        $resp->assertSee('Wagner Alberto');                       // grade (livewire)
        $resp->assertSee('Colaboradores');                        // stat 1
        $resp->assertSee('Palestras no acervo');                  // stat 2
        $resp->assertSee('"@type":"BreadcrumbList"', false);      // JSON-LD
    }

    public function test_index_destaque_some_sem_proxima_e_aparece_com(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Wagner Alberto']);

        // sem palestra futura → "Em destaque" não aparece (sem fallback)
        $this->get(route('palestrantes.index'))->assertDontSee('Em destaque');

        Palestra::factory()->create([
            'titulo' => 'Palestra Bem Futura',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestrantes.index'));
        $resp->assertSee('Em destaque');
        $resp->assertSee('Palestra Bem Futura');
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantesListagemTest`
Expected: FAIL (a casca atual não tem stats, "Em destaque" nem JSON-LD; H1 atual é "Quem leva a palavra").

- [ ] **Step 3: Reescrever `index()`** — `app/Http/Controllers/PalestranteController.php`

Adicionar `use App\Models\Palestra;` no topo e substituir `index()`:

```php
    public function index(): View
    {
        $proxima = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->first(); // sem fallback (pode ser null)

        return view('palestrantes.index', [
            'totalColaboradores' => Palestrante::ativo()->count(),
            'totalAcervo' => Palestra::publicado()->count(),
            'proxima' => $proxima,
        ]);
    }
```

(`show()` permanece intacto.)

- [ ] **Step 4: Reescrever a casca** — `resources/views/palestrantes/index.blade.php`

```blade
<x-layout.app title="Palestrantes" description="Conheça os palestrantes do CEMA — colaboradores que partilham as reflexões do Evangelho à luz da Doutrina Espírita.">
    @php
        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Palestras', 'item' => url('/palestra_publica')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Palestrantes'],
            ],
        ];
    @endphp
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div class="max-w-xl">
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-[#9db8e0]">Palestras Públicas · CEMA</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Palestrantes</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 font-light text-white/85">Colaboradores incansáveis que, com simplicidade e fraternidade, partilham conosco as reflexões do Evangelho à luz da Doutrina Espírita.</p>
            </div>
            <a href="{{ route('palestras.calendario') }}"
               class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold text-[#3a2f00]" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                </span>
                <span>
                    <span class="block font-display font-semibold">Calendário de Palestras</span>
                    <span class="block text-sm text-white/75">Veja a programação completa →</span>
                </span>
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
            <span class="text-text-secondary" aria-current="page">Palestrantes</span>
        </div>
    </nav>

    {{-- Conteúdo + sidebar --}}
    <section class="bg-surface">
        <div class="mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-12 desktop-sm:flex-row desktop-sm:items-start">
            <div class="min-w-0 flex-1">
                <livewire:palestrantes.lista />
            </div>
            <aside class="w-full shrink-0 desktop-sm:w-[340px]">
                {{-- Os Palestrantes --}}
                <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
                    <h2 class="font-display text-lg font-semibold text-primary">Os Palestrantes</h2>
                    <p class="mt-3 text-sm text-text-secondary">Cada palestra do CEMA nasce do trabalho voluntário e amoroso de irmãos e irmãs que dedicam seu tempo, seu estudo e seu coração à difusão dos ensinamentos do Evangelho à luz da Doutrina Espírita.</p>
                    <p class="mt-2 text-sm text-text-secondary">Não são oradores profissionais, mas companheiros de caminhada que, com simplicidade e fraternidade, aproximam o conhecimento espírita do dia a dia de cada um de nós.</p>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-cream px-4 py-3">
                            <p class="font-display text-2xl font-bold text-primary">{{ $totalColaboradores }}</p>
                            <p class="text-xs text-text-muted">Colaboradores</p>
                        </div>
                        <div class="rounded-xl bg-secondary/[0.12] px-4 py-3">
                            <p class="font-display text-2xl font-bold text-secondary">{{ $totalAcervo }}</p>
                            <p class="text-xs text-text-muted">Palestras no acervo</p>
                        </div>
                    </div>
                </div>

                {{-- Em destaque: próxima palestra (sem fallback) --}}
                @if ($proxima)
                    @php($dpp = $proxima->palestrantesAtivos->first())
                    <div class="relative mt-6 overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#3a3266] p-6 text-white shadow-card">
                        <p class="mb-3 inline-flex items-center gap-2 font-display text-sm font-semibold">
                            <span class="inline-block size-2 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Em destaque
                        </p>
                        <div class="flex items-center gap-3">
                            <span class="cema-spk-avatar cema-grad-{{ $proxima->id % 8 }} grid size-12 shrink-0 place-items-center overflow-hidden rounded-full ring-2 ring-white/25">
                                @if ($dpp?->foto_thumb_url)
                                    <img src="{{ $dpp->foto_thumb_url }}" alt="" width="48" height="48" class="size-full object-cover">
                                @else
                                    <span class="font-display text-sm font-semibold text-white/90" aria-hidden="true">{{ $dpp?->iniciais ?? 'CEMA' }}</span>
                                @endif
                            </span>
                            <div class="min-w-0">
                                @if ($dpp)<p class="truncate text-sm text-white/80">{{ $dpp->nome }}</p>@endif
                                @if ($proxima->data_da_palestra)
                                    <p class="font-mono text-xs text-gold">{{ $proxima->data_da_palestra->translatedFormat('d \d\e M \d\e Y') }} · {{ $proxima->data_da_palestra->format('H\hi') }}</p>
                                @endif
                            </div>
                        </div>
                        <h3 class="mt-3 font-display font-semibold">{{ $proxima->titulo }}</h3>
                        <a href="{{ route('palestras.show', $proxima->slug) }}"
                           class="mt-4 inline-flex rounded-pill bg-gold px-5 py-2 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ver palestra</a>
                    </div>
                @endif
            </aside>
        </div>
    </section>
</x-layout.app>
```

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantesListagemTest`
Expected: PASS (1 existente + 2 novos = 3). O existente (`test_lista_mostra_ativos_e_oculta_inativos`) segue verde (a grade lista ativos e oculta inativos).

- [ ] **Step 6: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/PalestranteController.php tests/Feature/Front/PalestrantesListagemTest.php
git add app/Http/Controllers/PalestranteController.php resources/views/palestrantes/index.blade.php tests/Feature/Front/PalestrantesListagemTest.php
git commit -m "$(cat <<'EOF'
feat(palestrantes/index): casca (hero, breadcrumb, sidebar stats + destaque, JSON-LD)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: CSS `palestrantes.css` + import + build + suíte completa + Pint + verificação manual

Cria o CSS (hover do card, gradiente do avatar, animação de entrada, badge), importa no `app.css`, gera o build e faz a verificação final.

**Files:**
- Create: `resources/css/palestrantes.css`
- Modify: `resources/css/app.css` (adicionar `@import` após `palestras-calendario.css`)

**Interfaces:**
- Consumes: classes usadas pelas views (Tasks 3/5): `.cema-spk-card`, `.cema-spk-card:hover`, `.cema-spk-cta`, `.cema-spk-avatar` (consome `--grad` de `cema-grad-{n}`), animação de entrada.
- Produces: estilos aplicados; sem teste automatizado (verificação por build + suíte + manual).

- [ ] **Step 1: Criar o CSS** — `resources/css/palestrantes.css`

```css
/* Palestrantes — listagem. Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01 */

.cema-spk-card {
    cursor: pointer;
    animation: cemaSpkFade .4s both;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.cema-spk-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(46, 41, 82, .18);
    border-color: #E2D9C2;
}
.cema-spk-card:hover .cema-spk-cta {
    background-color: var(--color-primary);
    color: #fff;
}
.cema-spk-card:hover .cema-spk-avatar img {
    transform: scale(1.04);
}

/* Avatar (topo do card / destaque): consome o gradiente de cema-grad-{n}. */
.cema-spk-avatar {
    background: var(--grad, linear-gradient(140deg, var(--color-primary), var(--color-footer-bg)));
}
.cema-spk-avatar img {
    transition: transform .3s ease;
}

@keyframes cemaSpkFade {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
    .cema-spk-card { animation: none; transition: none; }
    .cema-spk-card:hover { transform: none; }
    .cema-spk-card:hover .cema-spk-avatar img,
    .cema-spk-avatar img { transform: none; transition: none; }
}
```

- [ ] **Step 2: Importar no `app.css`** — `resources/css/app.css`

Adicionar a linha após `@import './palestras-calendario.css';`:

```css
@import './palestras-calendario.css';
@import './palestrantes.css';
```

- [ ] **Step 3: Build (host)**

Run: `npm run build`
Expected: build sem erros; classes incorporadas ao bundle.

- [ ] **Step 4: Refletir no dev + suíte COMPLETA + Pint**

```bash
docker compose restart app worker
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
```
Expected: **suíte completa verde**; Pint `--test` PASS. *(Nota: `ImportadorBlogTest::test_cap_imagem_*` pode falhar de forma flaky por contenção de GD no container — fora do diff desta fatia; reconfirmar isolado se ocorrer.)*

- [ ] **Step 5: Verificação manual no localhost**

Abrir `http://localhost:8000/palestrantes` e conferir:
1. Hero (kicker + H1 + régua + subtítulo + atalho Calendário), breadcrumb.
2. Grade de cards com avatar de **iniciais** em gradiente (fotos ausentes no lançamento) + badge de contagem; hover eleva o card e o botão vira roxo.
3. Busca reativa (debounce), "×" limpa; `<select>` de ordenação (A–Z / Z–A / mais / menos) reordena; contagem "N palestrantes"; "Limpar filtros" aparece com busca e reseta.
4. Estado vazio (busca sem resultado) com botão "Limpar filtros".
5. Sidebar: stats reais (Colaboradores / Palestras no acervo); "Em destaque" com a próxima palestra (some se não houver futura).
6. Paginação (12/pág) funciona; card navegável por teclado (Tab/Enter); `prefers-reduced-motion` desativa animações; responsivo (sidebar empilha < desktop-sm).

- [ ] **Step 6: Commit**

```bash
git add resources/css/palestrantes.css resources/css/app.css
git commit -m "$(cat <<'EOF'
feat(palestrantes): CSS da listagem (card hover, avatar gradiente, animacao de entrada)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review (checklist do autor)

**1. Cobertura do spec:**
- §6 accessor `iniciais` → Task 1. ✅
- §7 componente (ordenar/updated/limparFiltros/filtrosAtivos/render match/paginate 12) → Task 2. ✅
- §8 controller `index()` (stats + proxima sem fallback) → Task 5. ✅
- §9.1 casca (hero/breadcrumb/sidebar/JSON-LD) → Task 5; §9.2 view (toolbar/resultados/grade/vazio/paginação) → Task 4; §9.3 card → Task 3. ✅
- §10 CSS (cema-spk-*, reuso cema-grad-*, reduced-motion) → Task 6. ✅
- §11 SEO (BreadcrumbList) → Task 5; A11y (labels/aria/teclado/foco) → Tasks 3-5; perf (lazy/webp) → Task 3. ✅
- §12 testes: PalestranteIniciaisTest, PalestrantesListaTest (estendido), PalestranteCardTest, PalestrantesListagemTest (estendido). ✅
- Escopo SEM área: nenhum arquivo cria coluna/enum/campo/chips/dots/"Explorar por área"/linha de área. ✅

**2. Placeholders:** nenhum "TBD"/"TODO"/"handle edge cases"; todo passo tem código real. ✅

**3. Consistência de tipos/nomes:**
- `palestras_ministradas_count` — alias no `withCount` (Task 2), lido no card (Task 3) e nos testes. ✅
- `iniciais` — accessor (Task 1), usado no card (Task 3) e no destaque (Task 5). ✅
- `$palestrantes`/`$filtrosAtivos` — passados no render (Task 2), consumidos na view (Task 4). ✅
- Classes `.cema-spk-card`/`.cema-spk-cta`/`.cema-spk-avatar` + `cema-grad-{id%8}` — usadas nas views (Tasks 3/5), definidas na Task 6 (`cema-grad-*` já existe na archive). ✅
- Ordenação `az|za|mais|menos` — `match` no render (Task 2), opções do `<select>` (Task 4), testes (Task 2). ✅
- Livewire v4.3.2: testes com `viewData`/`assertSet`/`assertSee` (a nota da Task 2 substitui os 2 usos de `assertViewHas` por `viewData`). ✅

## Execution Handoff

Executar via **superpowers:subagent-driven-development** (recomendado): implementador (sonnet) + revisor por-task (sonnet) + revisão final da branch (opus). Modelos: implementação/revisão de task = sonnet; revisão final = opus.
