# Listagem de Palestras — redesign + compat de URL (Plano 7) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps usam checkbox (`- [ ]`).

**Goal:** Deixar a listagem pública de palestras mais atrativa e leve — URL compatível com o WP (`/palestra_publica`), destaque da próxima palestra, cards menores com **capa do YouTube**, e filtros reativos (título, data, palestrante, assunto, ordenar) com total dinâmico.

**Architecture:** Reaproveita o componente Livewire `Palestras\Lista` (já reativo) estendendo os filtros; o destaque "próxima palestra" é estático (controller → view, acima dos filtros). O card de palestra passa a usar a miniatura do YouTube (`i.ytimg.com/vi/{id}/mqdefault.jpg`, 16:9 ~10 KB) via accessor no model, com placeholder da marca para palestras sem vídeo. URLs migram para `/palestra_publica` mantendo os **nomes de rota** (links internos intactos) + redirect 301 de `/palestras`.

**Tech Stack:** Laravel 13 · Livewire 4 · Blade · Tailwind v4 · MySQL 8 (dev) / SQLite (testes) · Docker.

## Global Constraints

- **Idioma pt-BR** em tudo (textos, comentários, commits).
- **Comandos no container:** `docker compose exec -T app <cmd>`. Build no host: `npm run build`.
- **Regra INVIOLÁVEL:** só palestras `publicado` e só `palestrantesAtivos` aparecem.
- **Compat de URL (crítico):** os links já divulgados são `cemanet.org.br/palestra_publica/{slug}`. A single DEVE responder em `/palestra_publica/{slug}`. Manter os **nomes de rota** `palestras.index`/`palestras.show` (todos os `route()` internos continuam válidos). Redirect **301** de `/palestras` → `/palestra_publica` e `/palestras/{slug}` → `/palestra_publica/{slug}`.
- **Capa do YouTube:** usar `mqdefault.jpg` (16:9). `<link rel="preconnect" href="https://i.ytimg.com">` no layout. `loading="lazy"` + `aspect-video` (anti-CLS). Fallback (sem vídeo) = placeholder da marca (cor_fundo da palestra ou gradiente roxo + ícone CEMA). A foto do palestrante permanece no perfil e na single — só o **card** muda.
- **Livewire 4:** `#[Url(as:…, except:…)]`, `wire:model.live` (selects) / `wire:model.live.debounce.350ms` (texto/data), `resetPage()` ao filtrar, `->links()`.
- **Tokens do `@theme`**; mobile-first; A11y (labels, `alt`, `aria-*`); SEO mantido.
- **Pint** ao final de cada task com PHP novo. **Autoria** em classes PHP novas relevantes.

## Dados/estado atual (reuso — não recriar)

- `routes/web.php`: `palestras.index` (`/palestras`), `palestras.show` (`/palestras/{slug}`), `palestrantes.*`.
- `App\Livewire\Palestras\Lista`: já tem `#[Url] q` e `#[Url] assunto`, query `publicado()->with(['palestrantesAtivos','assuntos'])`, `orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')`, `paginate(9)`.
- `resources/views/components/palestra/card.blade.php`: card atual (foto do palestrante). Reusado também na perfil do palestrante.
- `resources/views/palestras/index.blade.php`: hero + `<livewire:palestras.lista />`.
- `resources/views/livewire/palestras/lista.blade.php`: busca (q) + grid + paginação.
- `App\Models\Palestra`: campos `link_youtube`, `cor_fundo`, `data_da_palestra`, `online`, `titulo`, `slug`; `scopePublicado`, `palestrantesAtivos`, `assuntos`. **Sem** accessor de YouTube (a single extrai o id por regex inline).
- `App\Models\Assunto`: `parent()/children()` (sem relação `palestras()`). `App\Models\Palestrante`: `scopeAtivo`.
- Layout `app.blade.php`: `<head>` com favicon/@vite/@livewireStyles + slot `head`.

## File Structure

**Criados:** (nenhum arquivo novo essencial — extensões dos existentes)
- Testes: `tests/Feature/Front/PalestraUrlCompatTest.php`, `tests/Feature/Models/PalestraYoutubeTest.php`, `tests/Feature/Livewire/PalestrasFiltrosTest.php`, `tests/Feature/Front/PalestrasDestaqueTest.php`.

**Modificados:**
- `routes/web.php` — paths `/palestra_publica` + redirects.
- `app/Models/Palestra.php` — accessors `youtube_id`/`youtube_thumb`.
- `app/Models/Assunto.php` — relação `palestras()`.
- `resources/views/components/layout/app.blade.php` — `preconnect`.
- `resources/views/components/palestra/card.blade.php` — capa do YouTube + card menor.
- `app/Livewire/Palestras/Lista.php` — filtros (data, palestrante, ordenar) + opções + total.
- `resources/views/livewire/palestras/lista.blade.php` — painel de filtros + total + grid.
- `app/Http/Controllers/PalestraController.php` — `index` busca a próxima palestra.
- `resources/views/palestras/index.blade.php` — destaque "Próxima palestra".
- `resources/views/palestras/show.blade.php` — (opcional) reusar `$palestra->youtube_id`.

---

## Task 1: Compat de URL — rotas `/palestra_publica` + redirects 301

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Front/PalestraUrlCompatTest.php`

**Interfaces:**
- Produces: `palestras.index` → `/palestra_publica`; `palestras.show` → `/palestra_publica/{slug}` (nomes inalterados). `/palestras` e `/palestras/{slug}` → 301 para os novos.

- [ ] **Step 1: Escrever o teste (falha primeiro)**

`tests/Feature/Front/PalestraUrlCompatTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraUrlCompatTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_responde_em_palestra_publica(): void
    {
        $this->get('/palestra_publica')->assertOk();
        $this->assertSame(url('/palestra_publica'), route('palestras.index'));
    }

    public function test_single_responde_em_palestra_publica_slug(): void
    {
        Palestra::factory()->create(['slug' => 'auxilios-do-invisivel', 'status' => Palestra::STATUS_PUBLICADO]);

        $this->get('/palestra_publica/auxilios-do-invisivel')->assertOk();
        $this->assertSame(url('/palestra_publica/auxilios-do-invisivel'), route('palestras.show', 'auxilios-do-invisivel'));
    }

    public function test_url_antiga_da_listagem_redireciona_301(): void
    {
        $this->get('/palestras')->assertRedirect('/palestra_publica')->assertStatus(301);
    }

    public function test_url_antiga_da_single_redireciona_301(): void
    {
        Palestra::factory()->create(['slug' => 'paz-e-luz', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get('/palestras/paz-e-luz');
        $resp->assertStatus(301);
        $resp->assertRedirect('/palestra_publica/paz-e-luz');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraUrlCompatTest`
Expected: FAIL.

- [ ] **Step 3: Atualizar as rotas**

Em `routes/web.php`, substituir as duas linhas de palestras por:
```php
Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])->name('palestras.show');

// Compat: URLs antigas (WP/divulgação) → 301 para as novas, preservando o slug.
Route::permanentRedirect('/palestras', '/palestra_publica');
Route::get('/palestras/{slug}', fn (string $slug) => redirect()->route('palestras.show', ['slug' => $slug], 301));
```
(Manter as rotas de palestrantes como estão.)

- [ ] **Step 4: Rodar (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraUrlCompatTest`
Expected: PASS. Depois a suíte completa (`docker compose exec -T app php artisan test`) — os testes que usam `route('palestras.show', …)` seguem válidos (nome de rota inalterado).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A
git commit -m "feat(palestras): URLs em /palestra_publica (compat WP) + redirect 301 de /palestras"
```

---

## Task 2: Capa do YouTube no card + card menor + preconnect

**Files:**
- Modify: `app/Models/Palestra.php` (accessors)
- Modify: `resources/views/components/layout/app.blade.php` (preconnect)
- Modify: `resources/views/components/palestra/card.blade.php`
- Test: `tests/Feature/Models/PalestraYoutubeTest.php`

**Interfaces:**
- Produces: `Palestra::youtube_id` (?string) e `Palestra::youtube_thumb` (?string, URL `i.ytimg.com/.../mqdefault.jpg`). Card usa `youtube_thumb`; sem vídeo → placeholder da marca.

- [ ] **Step 1: Escrever o teste do accessor (falha primeiro)**

`tests/Feature/Models/PalestraYoutubeTest.php`:
```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraYoutubeTest extends TestCase
{
    use RefreshDatabase;

    public function test_extrai_id_de_formatos_comuns(): void
    {
        $casos = [
            'https://www.youtube.com/watch?v=ABC123defGH' => 'ABC123defGH',
            'https://youtu.be/ABC123defGH' => 'ABC123defGH',
            'https://www.youtube.com/live/ABC123defGH' => 'ABC123defGH',
            'https://www.youtube.com/embed/ABC123defGH' => 'ABC123defGH',
        ];
        foreach ($casos as $url => $id) {
            $p = Palestra::factory()->make(['link_youtube' => $url]);
            $this->assertSame($id, $p->youtube_id, "Falhou para: $url");
            $this->assertSame("https://i.ytimg.com/vi/{$id}/mqdefault.jpg", $p->youtube_thumb);
        }
    }

    public function test_sem_link_retorna_null(): void
    {
        $p = Palestra::factory()->make(['link_youtube' => null]);
        $this->assertNull($p->youtube_id);
        $this->assertNull($p->youtube_thumb);
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraYoutubeTest`
Expected: FAIL.

- [ ] **Step 3: Adicionar os accessors no model**

Em `app/Models/Palestra.php`:
```php
    public function getYoutubeIdAttribute(): ?string
    {
        if ($this->link_youtube && preg_match('~(?:v=|youtu\.be/|live/|embed/|shorts/)([A-Za-z0-9_-]{6,})~', $this->link_youtube, $m)) {
            return $m[1];
        }

        return null;
    }

    public function getYoutubeThumbAttribute(): ?string
    {
        return $this->youtube_id ? "https://i.ytimg.com/vi/{$this->youtube_id}/mqdefault.jpg" : null;
    }
```

- [ ] **Step 4: Adicionar o preconnect no layout**

Em `resources/views/components/layout/app.blade.php`, após a linha do favicon:
```blade
    <link rel="preconnect" href="https://i.ytimg.com">
```

- [ ] **Step 5: Reescrever o card (menor + capa do YouTube)**

`resources/views/components/palestra/card.blade.php`:
```blade
@props(['palestra'])

@php
    $thumb = $palestra->youtube_thumb;
    $data = $palestra->data_da_palestra;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        <div class="relative aspect-video overflow-hidden bg-cream" @if($palestra->cor_fundo && ! $thumb) style="background:{{ $palestra->cor_fundo }}" @endif>
            @if ($thumb)
                <img src="{{ $thumb }}" alt="Capa: {{ $palestra->titulo }}" loading="lazy" width="320" height="180"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div aria-hidden="true" class="flex size-full items-center justify-center @unless($palestra->cor_fundo) bg-gradient-to-br from-primary to-footer-bg @endunless">
                    <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" class="h-10 w-auto opacity-90">
                </div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-4">
            <div class="mb-1.5 flex items-center gap-2 font-mono text-[10px] uppercase tracking-wide text-text-muted">
                @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                <span class="rounded-pill bg-surface px-2 py-0.5 text-[10px] text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
            </div>
            <h3 class="line-clamp-2 font-display text-base font-semibold leading-snug text-primary group-hover:underline">{{ $palestra->titulo }}</h3>
            @if ($palestra->palestrantesAtivos->isNotEmpty())
                <p class="mt-2 line-clamp-1 text-xs text-text-muted">{{ $palestra->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</p>
            @endif
        </div>
    </a>
</article>
```

- [ ] **Step 6: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter=PalestraYoutubeTest`
Run: `npm run build`
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(palestras): capa do YouTube no card (menor) + preconnect; foto fica no perfil"
```

---

## Task 3: Filtros expandidos (data, palestrante, assunto, ordenar) + total

**Files:**
- Modify: `app/Models/Assunto.php` (relação `palestras()`)
- Modify: `app/Livewire/Palestras/Lista.php`
- Modify: `resources/views/livewire/palestras/lista.blade.php`
- Test: `tests/Feature/Livewire/PalestrasFiltrosTest.php`

**Interfaces:**
- Produces: `Lista` com `#[Url]` para `q`, `assunto`, `palestrante` (slug), `dataDe`, `dataAte`, `ordenar` (`recente`/`antiga`). Render passa `palestrantes` (ativos) e `assuntos` (com palestras publicadas) para os selects, e o paginador expõe `->total()`.

- [ ] **Step 1: Adicionar a relação `palestras()` em Assunto**

Em `app/Models/Assunto.php`:
```php
    public function palestras(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Palestra::class, 'assunto_palestra', 'assunto_id', 'palestra_id');
    }
```
(Importar `App\Models\Palestra` se necessário.)

- [ ] **Step 2: Escrever os testes (falham primeiro)**

`tests/Feature/Livewire/PalestrasFiltrosTest.php`:
```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasFiltrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_palestrante(): void
    {
        $a = Palestrante::factory()->ativo()->create(['nome' => 'Ana', 'slug' => 'ana']);
        $b = Palestrante::factory()->ativo()->create(['nome' => 'Bruno', 'slug' => 'bruno']);
        $pa = Palestra::factory()->create(['titulo' => 'Da Ana']);
        $pb = Palestra::factory()->create(['titulo' => 'Do Bruno']);
        $pa->palestrantes()->attach($a, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $pb->palestrantes()->attach($b, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        Livewire::test(Lista::class)
            ->set('palestrante', 'ana')
            ->assertSee('Da Ana')
            ->assertDontSee('Do Bruno');
    }

    public function test_filtra_por_assunto(): void
    {
        $assunto = Assunto::factory()->create(['slug' => 'mediunidade']);
        $com = Palestra::factory()->create(['titulo' => 'Com Assunto']);
        $sem = Palestra::factory()->create(['titulo' => 'Sem Assunto']);
        $com->assuntos()->attach($assunto);

        Livewire::test(Lista::class)
            ->set('assunto', 'mediunidade')
            ->assertSee('Com Assunto')
            ->assertDontSee('Sem Assunto');
    }

    public function test_filtra_por_intervalo_de_data(): void
    {
        Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00']);
        Palestra::factory()->create(['titulo' => 'Recente', 'data_da_palestra' => '2026-01-01 16:00:00']);

        Livewire::test(Lista::class)
            ->set('dataDe', '2025-01-01')
            ->assertSee('Recente')
            ->assertDontSee('Antiga');
    }

    public function test_ordena_antiga_primeiro(): void
    {
        Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00']);
        Palestra::factory()->create(['titulo' => 'Recente', 'data_da_palestra' => '2026-01-01 16:00:00']);

        $html = Livewire::test(Lista::class)->set('ordenar', 'antiga')->html();
        $this->assertLessThan(strpos($html, 'Recente'), strpos($html, 'Antiga'));
    }
}
```

- [ ] **Step 3: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestrasFiltrosTest`
Expected: FAIL.

- [ ] **Step 4: Estender o componente Lista**

`app/Livewire/Palestras/Lista.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestras;

use App\Models\Assunto;
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

    #[Url(as: 'assunto', except: '')]
    public string $assunto = '';

    #[Url(as: 'palestrante', except: '')]
    public string $palestrante = '';

    #[Url(as: 'de', except: '')]
    public string $dataDe = '';

    #[Url(as: 'ate', except: '')]
    public string $dataAte = '';

    #[Url(as: 'ordenar', except: 'recente')]
    public string $ordenar = 'recente';

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar'], true)) {
            $this->resetPage();
        }
    }

    public function limparFiltros(): void
    {
        $this->reset(['q', 'assunto', 'palestrante', 'dataDe', 'dataAte', 'ordenar']);
        $this->resetPage();
    }

    public function render()
    {
        $palestras = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->when($this->q !== '', function (Builder $query) {
                $termo = '%'.$this->q.'%';
                $query->where(function (Builder $q) use ($termo) {
                    $q->where('titulo', 'like', $termo)
                        ->orWhere('subtitulo', 'like', $termo)
                        ->orWhere('resumo', 'like', $termo);
                });
            })
            ->when($this->assunto !== '', fn (Builder $query) => $query->whereHas('assuntos', fn (Builder $a) => $a->where('slug', $this->assunto)))
            ->when($this->palestrante !== '', fn (Builder $query) => $query->whereHas('palestrantesAtivos', fn (Builder $p) => $p->where('palestrantes.slug', $this->palestrante)))
            ->when($this->dataDe !== '', fn (Builder $query) => $query->whereDate('data_da_palestra', '>=', $this->dataDe))
            ->when($this->dataAte !== '', fn (Builder $query) => $query->whereDate('data_da_palestra', '<=', $this->dataAte))
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra '.($this->ordenar === 'antiga' ? 'asc' : 'desc'))
            ->paginate(12);

        return view('livewire.palestras.lista', [
            'palestras' => $palestras,
            'palestrantes' => Palestrante::ativo()->orderBy('nome')->get(['nome', 'slug']),
            'assuntos' => Assunto::whereHas('palestras', fn (Builder $q) => $q->where('status', Palestra::STATUS_PUBLICADO))->orderBy('nome')->get(['nome', 'slug']),
        ]);
    }
}
```

- [ ] **Step 5: Reescrever a view do componente (painel de filtros + total + grid)**

`resources/views/livewire/palestras/lista.blade.php`:
```blade
<div>
    {{-- Painel de filtros --}}
    <div class="mb-8 rounded-2xl border border-border-muted bg-white p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-display text-xl font-semibold text-primary">Filtrar Palestras</h2>
            <span class="font-display text-sm font-semibold text-primary">Total {{ $palestras->total() }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 desktop-sm:grid-cols-4">
            <div>
                <label for="f-de" class="mb-1 block text-sm text-text-secondary">De</label>
                <input id="f-de" type="date" wire:model.live="dataDe" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-ate" class="mb-1 block text-sm text-text-secondary">Até</label>
                <input id="f-ate" type="date" wire:model.live="dataAte" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label for="f-palestrante" class="mb-1 block text-sm text-text-secondary">Palestrante</label>
                <select id="f-palestrante" wire:model.live="palestrante" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="">Selecione…</option>
                    @foreach ($palestrantes as $p)
                        <option value="{{ $p->slug }}">{{ $p->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="f-assunto" class="mb-1 block text-sm text-text-secondary">Assunto</label>
                <select id="f-assunto" wire:model.live="assunto" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    @foreach ($assuntos as $a)
                        <option value="{{ $a->slug }}">{{ $a->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="f-titulo" class="mb-1 block text-sm text-text-secondary">Título</label>
                <input id="f-titulo" type="search" wire:model.live.debounce.350ms="q" placeholder="Pesquisar…"
                       class="w-full rounded-md border border-border bg-white px-4 py-2 text-sm">
            </div>
            <div>
                <label for="f-ordenar" class="mb-1 block text-sm text-text-secondary">Organizar</label>
                <select id="f-ordenar" wire:model.live="ordenar" class="w-full rounded-md border border-border bg-white px-3 py-2 text-sm">
                    <option value="recente">Mais recentes</option>
                    <option value="antiga">Mais antigas</option>
                </select>
            </div>
            <button type="button" wire:click="limparFiltros" class="rounded-pill border border-border px-4 py-2 text-sm text-text-secondary hover:border-primary hover:text-primary">Limpar</button>
        </div>
    </div>

    {{-- Grid --}}
    @if ($palestras->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">Nenhuma palestra encontrada.</p>
    @else
        <div class="grid gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="palestra-{{ $palestra->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestras->onEachSide(1)->links() }}</div>
    @endif
</div>
```

- [ ] **Step 6: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter=PalestrasFiltrosTest`
Run: `npm run build`
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(palestras): filtros reativos (data, palestrante, assunto, ordenar) + total"
```

---

## Task 4: Destaque "Próxima palestra"

**Files:**
- Modify: `app/Http/Controllers/PalestraController.php` (`index`)
- Modify: `resources/views/palestras/index.blade.php`
- Test: `tests/Feature/Front/PalestrasDestaqueTest.php`

**Interfaces:**
- Consumes: `Palestra::publicado()`, `palestrantesAtivos`, `youtube_thumb` (opcional).
- Produces: `index` passa `$proxima` (próxima palestra com data ≥ hoje; senão, a mais recente) à view, renderizada num card de destaque acima dos filtros.

- [ ] **Step 1: Escrever o teste (falha primeiro)**

`tests/Feature/Front/PalestrasDestaqueTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestrasDestaqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_destaca_proxima_palestra_futura(): void
    {
        Palestra::factory()->create(['titulo' => 'Já Passou', 'data_da_palestra' => Carbon::now()->subDays(10), 'status' => Palestra::STATUS_PUBLICADO]);
        Palestra::factory()->create(['titulo' => 'Vem Aí', 'data_da_palestra' => Carbon::now()->addDays(5), 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSeeText('Próximas Palestras');
        $resp->assertSeeText('Vem Aí');
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestrasDestaqueTest`
Expected: FAIL.

- [ ] **Step 3: Buscar a próxima no controller**

Em `app/Http/Controllers/PalestraController.php`, método `index`:
```php
    public function index()
    {
        $proxima = \App\Models\Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with('palestrantesAtivos')
            ->orderBy('data_da_palestra')
            ->first()
            ?? \App\Models\Palestra::query()
                ->publicado()
                ->whereNotNull('data_da_palestra')
                ->with('palestrantesAtivos')
                ->orderByDesc('data_da_palestra')
                ->first();

        return view('palestras.index', compact('proxima'));
    }
```

- [ ] **Step 4: Adicionar o destaque na view**

Em `resources/views/palestras/index.blade.php`, entre o hero e a `<section>` da listagem, adicionar (bloco único responsivo — sem duplicar o título):
```blade
    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        <section class="mx-auto max-w-[1240px] px-6 pt-12" aria-label="Próxima palestra">
            <p class="mb-3 font-display text-2xl font-semibold text-primary"><span class="text-gold">Próximas</span> Palestras</p>
            <div class="flex flex-col items-center gap-5 rounded-2xl bg-accent p-6 text-white sm:flex-row sm:gap-7">
                @if ($pp?->foto)
                    <img src="{{ asset('storage/'.$pp->foto) }}" alt="{{ $pp->nome }}" width="112" height="112"
                         class="size-28 shrink-0 rounded-full object-cover ring-4 ring-white/40">
                @endif
                <div class="flex-1 text-center sm:text-left">
                    <h2 class="font-display text-2xl font-semibold">{{ $proxima->titulo }}</h2>
                    @if ($proxima->data_da_palestra)
                        <p class="mt-1 text-white/85">{{ $proxima->data_da_palestra->translatedFormat('d \d\e F \d\e Y') }}</p>
                    @endif
                    @if ($pp)<p class="mt-1 text-sm text-white/80">{{ $pp->nome }}</p>@endif
                </div>
                <a href="{{ route('palestras.show', $proxima->slug) }}"
                   class="shrink-0 rounded-pill bg-white px-6 py-3 font-ui font-semibold text-accent transition hover:bg-white/90">Ver Palestra</a>
            </div>
        </section>
    @endif
```

- [ ] **Step 5: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter=PalestrasDestaqueTest`
Run: `npm run build`
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(palestras): destaque da próxima palestra no topo da listagem"
```

---

## Verificação final (whole-branch)

- [ ] `docker compose exec -T app php artisan test` — tudo verde.
- [ ] Smoke real (MySQL): `/palestra_publica` (200, destaque + filtros + cards com capa do YouTube + Total dinâmico), `/palestra_publica/{slug}` (200), `/palestras` → 301, `/palestras/{slug}` → 301. Conferir peso da página (capas leves) e responsividade.
- [ ] Conferir que os filtros combinam (data + palestrante + assunto + título) e o "Limpar" zera.

## Critérios de pronto (DoD)

- Single e listagem em `/palestra_publica` (compat WP) com redirect 301 das URLs antigas.
- Destaque da próxima palestra; cards menores com capa do YouTube (fallback de marca) e mais leves.
- Filtros reativos por título/data/palestrante/assunto + organizar + Total dinâmico.
- `php artisan test` verde + verificação manual no localhost.
