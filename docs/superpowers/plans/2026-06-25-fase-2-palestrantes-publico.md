# Palestrante como página pública (Plano 6 — Fase 2, fatia 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para implementar tarefa a tarefa. Steps usam checkbox (`- [ ]`).

**Goal:** Entregar as páginas públicas de Palestrante — listagem reativa `/palestrantes` (busca por nome) e perfil `/palestrantes/{slug}` (bio, contato condicional e palestras ministradas) — exibindo só palestrantes ativos, e integrar com a single da palestra.

**Architecture:** Espelha o front de Palestras (Plano 4). Listagem = componente **Livewire 4** (busca reativa) embutido numa view Blade SSR. Perfil = controller + Blade SSR puro com `schema.org/Person`. Reusa `<x-layout.app>`, `<x-palestra.card>`, tokens do `@theme`, i18n pt-BR. Regra de negócio: só `ativo` aparece (perfil 404 para inativo).

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Blade · Tailwind v4 · MySQL 8 (dev) / SQLite (testes) · Docker.

## Global Constraints

- **Idioma pt-BR** em tudo (textos, comentários, commits). Sintaxe/APIs no original.
- **Comandos no container:** `docker compose exec -T app <cmd>`. Build no host: `npm run build`.
- **Mobile-first e responsivo**; ponto de troca desktop = 1024px (`desktop-sm:`).
- **Regra de negócio INVIOLÁVEL:** só palestrantes `ativo=true` aparecem no público. Listagem usa `scopeAtivo`; perfil dá **404** para inativo/inexistente (`Palestrante::ativo()->where('slug',$slug)->firstOrFail()`).
- **Contato condicional:** e-mail só renderiza se `mostrar_email`; telefone só se `mostrar_telefone`. Nunca expor o que está oculto.
- **Tokens do `@theme`**, nunca cores hardcoded em views novas (exceto valores já usados no front, ex.: gradiente do hero).
- **Livewire 4** (instalado v4.3.1 via Filament): `make:livewire ... --class`; `#[Url(as:…, except:'')]`; `wire:model.live` (deferred é o default); assets auto-injetados pelo layout (`@livewireScripts` já está no `<x-layout.app>` — não adicionar de novo); paginação `->links()` (Tailwind por padrão). Embuta com `<livewire:palestrantes.lista />` (o `#[Url]` lê a query string).
- **A11y:** semântica, `alt` nas fotos, `aria-*`, foco visível, `<label sr-only>` na busca.
- **SEO:** `<title>`/meta/OG por página; `schema.org/Person` (JSON-LD via `<x-slot:head>`) no perfil; imagens com `width/height` + `loading="lazy"`.
- **Segurança:** `bio` já é sanitizada no model (mutator) → renderizar com `{!! $palestrante->bio !!}` é aceitável. JSON-LD com `JSON_HEX_TAG` (como na single de palestra).
- **Autoria** em classes PHP novas relevantes: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25`.
- **Pint** ao final de cada task com PHP novo.

## Dados disponíveis (não recriar)

- `App\Models\Palestrante`: `scopeAtivo`, `palestras()` (belongsToMany via `palestra_pessoa`, `withPivot('papel')`), `bio` sanitizada, campos `nome/slug/foto/bio/email/telefone/mostrar_email/mostrar_telefone/ativo`. Factory `PalestranteFactory` (estados `ativo()/inativo()`).
- `App\Models\Palestra`: `scopePublicado`, constantes `PAPEL_PALESTRANTE`/`STATUS_PUBLICADO`, `palestrantesAtivos`. Factory `PalestraFactory`.
- Front pronto: `<x-layout.app :title :description>` (com slot `head`), `<x-palestra.card :palestra>`, layout/header/footer, `@livewireScripts` no layout, i18n pt-BR (datas Carbon), `asset('storage/'.$foto)` (symlink existe). Padrão de listagem reativa em `app/Livewire/Palestras/Lista.php` + `resources/views/livewire/palestras/lista.blade.php` (referência).

## File Structure

**Criados:**
- `app/Http/Controllers/PalestranteController.php` — `index` (view) e `show` (perfil + palestras).
- `app/Livewire/Palestrantes/Lista.php` — listagem reativa.
- `resources/views/livewire/palestrantes/lista.blade.php` — grid + busca + paginação.
- `resources/views/palestrantes/index.blade.php` — página da listagem.
- `resources/views/palestrantes/show.blade.php` — perfil.
- `resources/views/components/palestrante/card.blade.php` — card de palestrante.
- Testes: `tests/Feature/Front/PalestrantesListagemTest.php`, `tests/Feature/Livewire/PalestrantesListaTest.php`, `tests/Feature/Front/PalestrantePerfilTest.php`, e um teste de relação em `tests/Feature/Models/PalestranteTest.php`.

**Modificados:**
- `app/Models/Palestrante.php` — relação `palestrasMinistradas()`.
- `config/navegacao.php` — habilitar "Palestrantes".
- `routes/web.php` — `palestrantes.index` + `palestrantes.show`.
- `resources/views/palestras/show.blade.php` — link "Ver perfil completo →" no card de palestrante.

---

## Task 1: Relação no model + rotas base

**Files:**
- Modify: `app/Models/Palestrante.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Models/PalestranteTest.php` (acrescenta)

**Interfaces:**
- Produces: `Palestrante::palestrasMinistradas(): BelongsToMany` — palestras onde a pessoa tem papel `palestrante` (sem filtro de status; o controller aplica `publicado()`). Rotas nomeadas `palestrantes.index` e `palestrantes.show` (closures temporárias → 404 até as Tasks 2/3).

- [ ] **Step 1: Escrever o teste da relação (falha primeiro)**

Acrescentar a `tests/Feature/Models/PalestranteTest.php`:
```php
    public function test_palestras_ministradas_so_traz_papel_palestrante(): void
    {
        $pessoa = \App\Models\Palestrante::factory()->ativo()->create();
        $comoPalestrante = \App\Models\Palestra::factory()->create();
        $comoDiretor = \App\Models\Palestra::factory()->create();
        $comoPalestrante->palestrantes()->attach($pessoa, ['papel' => \App\Models\Palestra::PAPEL_PALESTRANTE]);
        $comoDiretor->palestrantes()->attach($pessoa, ['papel' => \App\Models\Palestra::PAPEL_DIRETOR]);

        $ids = $pessoa->palestrasMinistradas()->pluck('palestras.id')->all();

        $this->assertContains($comoPalestrante->id, $ids);
        $this->assertNotContains($comoDiretor->id, $ids);
    }
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestranteTest`
Expected: FAIL (método inexistente).

- [ ] **Step 3: Adicionar a relação**

Em `app/Models/Palestrante.php`, após `palestras()`:
```php
    public function palestrasMinistradas(): BelongsToMany
    {
        return $this->palestras()->wherePivot('papel', Palestra::PAPEL_PALESTRANTE);
    }
```
(Importar `App\Models\Palestra` se necessário — provavelmente já referenciado via `palestras()`.)

- [ ] **Step 4: Registrar as rotas (closures temporárias)**

Em `routes/web.php`, adicionar:
```php
// Substituídas pelo controller nas Tasks 2 e 3.
Route::get('/palestrantes', fn () => abort(404))->name('palestrantes.index');
Route::get('/palestrantes/{slug}', fn () => abort(404))->name('palestrantes.show');
```

- [ ] **Step 5: Rodar (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestranteTest`
Expected: PASS. Depois a suíte completa (sem regressão).

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A
git commit -m "feat(palestrantes): relação palestrasMinistradas + rotas base"
```

---

## Task 2: Listagem `/palestrantes` (Livewire reativo + card)

**Files:**
- Create: `app/Http/Controllers/PalestranteController.php` (método `index`)
- Create: `app/Livewire/Palestrantes/Lista.php`
- Create: `resources/views/livewire/palestrantes/lista.blade.php`
- Create: `resources/views/palestrantes/index.blade.php`
- Create: `resources/views/components/palestrante/card.blade.php`
- Modify: `routes/web.php` (apontar `palestrantes.index` ao controller), `config/navegacao.php` (habilitar item)
- Test: `tests/Feature/Front/PalestrantesListagemTest.php`, `tests/Feature/Livewire/PalestrantesListaTest.php`

**Interfaces:**
- Consumes: `Palestrante::ativo()`, `<x-layout.app>`, `<x-palestra.card>` (padrão).
- Produces: `GET /palestrantes` (200) renderiza `palestrantes.index`, que embute `<livewire:palestrantes.lista />`. O componente: `#[Url] q`, busca por nome (`wire:model.live.debounce.350ms`), `scopeAtivo`, `withCount` de palestras ministradas, `orderBy('nome')`, paginação. Card linka para `palestrantes.show`.

- [ ] **Step 1: Escrever o card**

`resources/views/components/palestrante/card.blade.php`:
```blade
@props(['palestrante'])

@php
    $foto = $palestrante->foto ? asset('storage/'.$palestrante->foto) : null;
    $resumoBio = $palestrante->bio ? \Illuminate\Support\Str::limit(strip_tags($palestrante->bio), 120) : null;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestrantes.show', $palestrante->slug) }}" class="flex h-full flex-col">
        <div class="aspect-square overflow-hidden bg-cream">
            @if ($foto)
                <img src="{{ $foto }}" alt="{{ $palestrante->nome }}" loading="lazy" width="320" height="320"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div class="flex size-full items-center justify-center font-mono text-xs text-text-muted" aria-hidden="true">CEMA</div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-5">
            <h3 class="font-display text-lg font-semibold text-primary group-hover:underline">{{ $palestrante->nome }}</h3>
            @isset($palestrante->palestras_ministradas_count)
                <p class="mt-1 font-mono text-[11px] uppercase tracking-wide text-text-muted">
                    {{ $palestrante->palestras_ministradas_count }} {{ \Illuminate\Support\Str::plural('palestra', $palestrante->palestras_ministradas_count) }}
                </p>
            @endisset
            @if ($resumoBio)
                <p class="mt-2 line-clamp-3 text-sm text-text-secondary">{{ $resumoBio }}</p>
            @endif
        </div>
    </a>
</article>
```

- [ ] **Step 2: Escrever os testes (falham primeiro)**

`tests/Feature/Front/PalestrantesListagemTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantesListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_mostra_ativos_e_oculta_inativos(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);

        $resp = $this->get(route('palestrantes.index'));

        $resp->assertOk();
        $resp->assertSee('João Ativo');
        $resp->assertDontSee('Maria Inativa');
    }
}
```

`tests/Feature/Livewire/PalestrantesListaTest.php`:
```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestrantes\Lista;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrantesListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_filtra_por_nome(): void
    {
        Palestrante::factory()->ativo()->create(['nome' => 'Abadio Rodrigues']);
        Palestrante::factory()->ativo()->create(['nome' => 'Bezerra de Menezes']);

        Livewire::test(Lista::class)
            ->set('q', 'Abadio')
            ->assertSee('Abadio Rodrigues')
            ->assertDontSee('Bezerra de Menezes');
    }
}
```

- [ ] **Step 3: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter="PalestrantesListagemTest|PalestrantesListaTest"`
Expected: FAIL.

- [ ] **Step 4: Criar o componente Livewire**

Run: `docker compose exec -T app php artisan make:livewire Palestrantes/Lista --class`
Substituir `app/Livewire/Palestrantes/Lista.php`:
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

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $palestrantes = Palestrante::query()
            ->ativo()
            ->when($this->q !== '', fn (Builder $query) => $query->where('nome', 'like', '%'.$this->q.'%'))
            ->withCount(['palestras as palestras_ministradas_count' => function (Builder $query) {
                $query->where('palestra_pessoa.papel', Palestra::PAPEL_PALESTRANTE)
                    ->where('palestras.status', Palestra::STATUS_PUBLICADO);
            }])
            ->orderBy('nome')
            ->paginate(12);

        return view('livewire.palestrantes.lista', ['palestrantes' => $palestrantes]);
    }
}
```
> Se o `withCount` com a condição de pivô (`palestra_pessoa.papel`) gerar SQL ambíguo, ajustar a qualificação da coluna; validar com o teste. O count é cosmético (card) — não bloqueia a listagem se precisar simplificar.

- [ ] **Step 5: Criar a view do componente**

`resources/views/livewire/palestrantes/lista.blade.php`:
```blade
<div>
    <form class="mb-8" wire:submit.prevent>
        <label for="busca-palestrantes" class="sr-only">Buscar palestrante por nome</label>
        <input id="busca-palestrantes" type="search" wire:model.live.debounce.350ms="q"
               placeholder="Buscar palestrante…"
               class="w-full rounded-pill border border-border bg-white px-5 py-2.5 font-sans text-sm text-text outline-none focus:border-primary sm:max-w-md">
    </form>

    @if ($palestrantes->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">Nenhum palestrante encontrado.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3 desktop:grid-cols-4">
            @foreach ($palestrantes as $palestrante)
                <x-palestrante.card :palestrante="$palestrante" wire:key="palestrante-{{ $palestrante->id }}" />
            @endforeach
        </div>
        <div class="mt-10">{{ $palestrantes->onEachSide(1)->links() }}</div>
    @endif
</div>
```

- [ ] **Step 6: Controller + página + rota + nav**

`app/Http/Controllers/PalestranteController.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

class PalestranteController extends Controller
{
    public function index()
    {
        return view('palestrantes.index');
    }
}
```

`resources/views/palestrantes/index.blade.php`:
```blade
<x-layout.app title="Palestrantes" description="Palestrantes do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Palestrantes</p>
            <h1 class="mt-2 font-display text-4xl font-semibold">Quem leva a palavra</h1>
            <p class="mt-3 max-w-xl text-white/85">Os trabalhadores que conduzem as palestras públicas da casa.</p>
        </div>
    </section>
    <section class="mx-auto max-w-[1240px] px-6 py-12">
        <livewire:palestrantes.lista />
    </section>
</x-layout.app>
```

Em `routes/web.php`: trocar a closure de `palestrantes.index` por `[PalestranteController::class, 'index']` (importar a classe).
Em `config/navegacao.php`: habilitar o item "Palestrantes" sob Palestras:
```php
['rotulo' => 'Palestrantes', 'rota' => 'palestrantes.index', 'ativo' => true],
```

- [ ] **Step 7: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter="PalestrantesListagemTest|PalestrantesListaTest"`
Run: `npm run build`
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(palestrantes): listagem /palestrantes reativa (Livewire) + card + navegação"
```

---

## Task 3: Perfil `/palestrantes/{slug}` (SSR)

**Files:**
- Modify: `app/Http/Controllers/PalestranteController.php` (método `show`)
- Modify: `routes/web.php` (apontar `palestrantes.show`)
- Create: `resources/views/palestrantes/show.blade.php`
- Test: `tests/Feature/Front/PalestrantePerfilTest.php`

**Interfaces:**
- Consumes: `Palestrante::ativo()`, `palestrasMinistradas()` + `publicado()`, `<x-palestra.card>`.
- Produces: `GET /palestrantes/{slug}` → 200 (ativo), 404 (inativo/inexistente). Perfil: foto/nome/bio, contato condicional, grid de palestras ministradas (publicadas, recentes primeiro), `schema.org/Person`.

- [ ] **Step 1: Escrever os testes (falham primeiro)**

`tests/Feature/Front/PalestrantePerfilTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_ativo_mostra_bio_e_palestras(): void
    {
        $pessoa = Palestrante::factory()->ativo()->create([
            'nome' => 'Abadio Rodrigues', 'slug' => 'abadio-rodrigues',
            'bio' => '<p>Trabalhador da casa.</p>',
        ]);
        $palestra = Palestra::factory()->create(['titulo' => 'Auxílios do Invisível', 'status' => Palestra::STATUS_PUBLICADO]);
        $palestra->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestrantes.show', 'abadio-rodrigues'));

        $resp->assertOk();
        $resp->assertSee('Abadio Rodrigues');
        $resp->assertSee('Trabalhador da casa', false);
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertSee('"@type":"Person"', false);
    }

    public function test_contato_respeita_flags(): void
    {
        Palestrante::factory()->ativo()->create([
            'slug' => 'com-email', 'email' => 'pessoa@cema.org', 'telefone' => '61999990000',
            'mostrar_email' => true, 'mostrar_telefone' => false,
        ]);

        $resp = $this->get(route('palestrantes.show', 'com-email'));

        $resp->assertSee('pessoa@cema.org');
        $resp->assertDontSee('61999990000');
    }

    public function test_inativo_da_404(): void
    {
        Palestrante::factory()->inativo()->create(['slug' => 'oculto']);
        $this->get(route('palestrantes.show', 'oculto'))->assertNotFound();
    }

    public function test_slug_inexistente_da_404(): void
    {
        $this->get(route('palestrantes.show', 'nao-existe'))->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestrantePerfilTest`
Expected: FAIL.

- [ ] **Step 3: Implementar `show`**

Adicionar a `app/Http/Controllers/PalestranteController.php`:
```php
    public function show(string $slug)
    {
        $palestrante = \App\Models\Palestrante::query()
            ->ativo()
            ->where('slug', $slug)
            ->firstOrFail();

        $palestras = $palestrante->palestrasMinistradas()
            ->publicado()
            ->with('palestrantesAtivos')
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->get();

        return view('palestrantes.show', compact('palestrante', 'palestras'));
    }
```
Em `routes/web.php`: trocar a closure de `palestrantes.show` por `[PalestranteController::class, 'show']`.

- [ ] **Step 4: Implementar a view do perfil**

`resources/views/palestrantes/show.blade.php`:
```blade
@php
    $foto = $palestrante->foto ? asset('storage/'.$palestrante->foto) : null;
@endphp

<x-layout.app :title="$palestrante->nome" :description="\Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 150) ?: 'Palestrante do CEMA'">
    <x-slot:head>
        <script type="application/ld+json">
        @php
            echo json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $palestrante->nome,
                'image' => $foto,
                'description' => \Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 200),
                'url' => route('palestrantes.show', $palestrante->slug),
                'worksFor' => ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        </script>
    </x-slot:head>

    {{-- Hero / perfil --}}
    <section class="relative overflow-hidden text-white">
        <div class="absolute inset-0 bg-gradient-to-br from-primary to-footer-bg"></div>
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestrantes.index') }}" class="hover:text-white">Palestrantes</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ $palestrante->nome }}</span>
            </nav>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                @if ($foto)
                    <img src="{{ $foto }}" alt="{{ $palestrante->nome }}" width="160" height="160"
                         class="size-40 shrink-0 rounded-2xl object-cover">
                @endif
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestrante</p>
                    <h1 class="mt-2 font-display text-3xl font-semibold md:text-4xl">{{ $palestrante->nome }}</h1>
                    @if ($palestrante->mostrar_email && $palestrante->email)
                        <p class="mt-3 text-sm text-white/85"><a href="mailto:{{ $palestrante->email }}" class="underline hover:text-gold">{{ $palestrante->email }}</a></p>
                    @endif
                    @if ($palestrante->mostrar_telefone && $palestrante->telefone)
                        <p class="mt-1 text-sm text-white/85">{{ $palestrante->telefone }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Bio --}}
    @if ($palestrante->bio)
        <section class="mx-auto max-w-[760px] px-6 py-12">
            <div class="max-w-none text-text-secondary [&_p]:mb-4 [&_p]:leading-relaxed [&_a]:text-secondary [&_a]:underline">
                {!! $palestrante->bio !!}
            </div>
        </section>
    @endif

    {{-- Palestras ministradas --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <h2 class="mb-6 font-display text-2xl font-semibold text-primary">Palestras de {{ $palestrante->nome }}</h2>
        @if ($palestras->isEmpty())
            <p class="rounded-lg border border-border-muted bg-surface px-6 py-8 text-text-secondary">Nenhuma palestra publicada por ora.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @foreach ($palestras as $palestra)
                    <x-palestra.card :palestra="$palestra" />
                @endforeach
            </div>
        @endif
    </section>
</x-layout.app>
```

- [ ] **Step 5: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter=PalestrantePerfilTest`
Run: `npm run build`
Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(palestrantes): perfil /palestrantes/{slug} (SSR + schema.org/Person)"
```

---

## Task 4: Integração — link na single da palestra

**Files:**
- Modify: `resources/views/palestras/show.blade.php` (card de palestrante)
- Test: `tests/Feature/Front/PalestraSingleTest.php` (acrescenta)

**Interfaces:**
- Consumes: rota `palestrantes.show`.
- Produces: no card de palestrante da single da palestra, o nome vira link e há um "Ver perfil completo →" para `palestrantes.show`.

- [ ] **Step 1: Acrescentar assert ao teste (falha primeiro)**

Em `tests/Feature/Front/PalestraSingleTest.php`, no `test_single_publica_retorna_200_com_conteudo` (ou um novo método), criar a palestra com palestrante de slug conhecido e:
```php
    public function test_single_linka_perfil_do_palestrante(): void
    {
        $palestra = Palestra::factory()->create(['slug' => 'aux-link', 'status' => Palestra::STATUS_PUBLICADO]);
        $p = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo', 'slug' => 'joao-ativo']);
        $palestra->palestrantes()->attach($p, ['papel' => Palestra::PAPEL_PALESTRANTE]);

        $resp = $this->get(route('palestras.show', 'aux-link'));

        $resp->assertOk();
        $resp->assertSee(route('palestrantes.show', 'joao-ativo'), false);
    }
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraSingleTest`
Expected: FAIL.

- [ ] **Step 3: Adicionar o link no card de palestrante da single**

Em `resources/views/palestras/show.blade.php`, dentro do `@forelse ($palestrantes as $p)` (coluna esquerda), tornar o nome um link e adicionar o "Ver perfil completo". Ex.: trocar o `<h2>...{{ $p->nome }}...</h2>` por um link e adicionar abaixo da bio:
```blade
                            <h2 class="mt-1 font-display text-xl font-semibold text-primary">
                                <a href="{{ route('palestrantes.show', $p->slug) }}" class="hover:underline">{{ $p->nome }}</a>
                            </h2>
                            ...
                            <a href="{{ route('palestrantes.show', $p->slug) }}" class="mt-3 inline-block text-sm font-semibold text-secondary hover:underline">Ver perfil completo →</a>
```
(Preservar o restante do card: rótulo "Palestrante", foto, trecho da bio.)

- [ ] **Step 4: Rodar (deve passar) + build + commit**

Run: `docker compose exec -T app php artisan test --filter=PalestraSingleTest`
Run: `npm run build`
```bash
git add -A
git commit -m "feat(palestrantes): linka o perfil do palestrante na single da palestra"
```

---

## Verificação final (whole-branch)

- [ ] `docker compose exec -T app php artisan test` — tudo verde (incl. os 63 testes pré-existentes).
- [ ] Smoke real (MySQL): `/palestrantes` (200, mostra ativos, busca funciona), `/palestrantes/{slug-real}` (200, bio + palestras), inativo → 404. HTML enxuto.
- [ ] Atualizar `ROADMAP.md`: marcar "Palestrantes (página individual e listagem)" como concluído na Fase 2.

## Critérios de pronto (Definition of Done)

- `/palestrantes` lista só ativos, com busca reativa por nome.
- `/palestrantes/{slug}` mostra perfil (bio, contato conforme flags) + palestras ministradas; inativo/inexistente dá 404; `schema.org/Person` presente.
- Menu "Palestrantes" habilitado; single da palestra linka para o perfil.
- `php artisan test` verde + verificação manual no localhost.
