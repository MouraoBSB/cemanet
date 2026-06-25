# Front Público — Módulo Palestras (Plano 4) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o front público do módulo Palestras — layout base responsivo (header + footer), listagem `/palestras` (busca reativa) e página individual `/palestras/{slug}` (template T06) — exibindo as 123 palestras já importadas, fiel ao design-system.

**Architecture:** Blade SSR como padrão; um único componente de layout (`<x-layout.app>`) com header e footer globais alimentados por `config/navegacao.php`. A listagem usa um componente **Livewire 3** para busca/filtro/paginação reativos embutido numa view Blade normal (SEO no servidor). A página individual é 100% SSR (controller + Blade), com microinterações progressivas em **Alpine** (já incluído no bundle do Livewire 3). Tokens de design vivem no `@theme` de `resources/css/app.css` (já configurado).

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 3 · Blade · Alpine (bundle do Livewire) · Tailwind CSS v4 · Vite 8 · MySQL 8 (dev) / SQLite in-memory (testes) · Docker.

## Global Constraints

- **Idioma pt-BR** em tudo que for produzido: identificadores de domínio, comentários, textos de interface, mensagens, commits. Sintaxe e APIs de terceiros no original.
- **Comandos rodam no container:** `docker compose exec -T app <cmd>` (não há PHP/Composer no host). Ex.: `docker compose exec -T app php artisan test`, `docker compose exec -T app composer require ...`. Build de assets no host: `npm run build`.
- **Mobile-first e responsivo** (mobile/tablet/desktop). Ponto de troca do header desktop↔mobile = `1024px` (variante Tailwind `desktop-sm:`).
- **Só pessoas ativas aparecem no público.** Usar SEMPRE `palestrantesAtivos` (papel=palestrante + `ativo=true`). O diretor (tipicamente inativo) **nunca** é renderizado no front. Esta é regra de negócio inviolável.
- **Tokens do `@theme`, nunca cores hardcoded** em novas views. Mapeamento: roxo institucional=`primary` (#4e4483), azul=`secondary`, verde=`accent`, dourado=`gold` (#f2a81e), creme=`cream`, fundo escuro=`footer-bg` (#2f2952), texto=`text`/`text-secondary`/`text-muted`/`text-ink`, superfícies=`surface`/`border`/`border-muted`. Fontes: títulos=`font-display` (Work Sans), corpo=`font-sans` (Poppins), rótulos técnicos/datas=`font-mono` (Roboto Mono).
- **Contraste (A11y):** `secondary`/`accent` só em elementos grandes/ícones/fundos — nunca texto pequeno sobre branco. Corpo em `text`/`text-ink`.
- **A11y:** HTML semântico, `aria-*` em menus/dialogs, foco visível, `<details>/<summary>` nativos para acordeões, `<label>` associado em formulários, `alt` em imagens.
- **SEO:** rotas limpas; `<title>`/meta description/OpenGraph por página; `schema.org/Event` (JSON-LD) na página individual; `width`/`height` + `loading="lazy"` em imagens.
- **Performance:** HTML enxuto (o WP atual gasta ~0,5 MB/página — ficar bem abaixo). Fontes self-hosted (Bunny) já no build.
- **Autoria** em classes PHP novas relevantes (controllers, componentes Livewire): cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25`. Não em Blade nem em config.
- **Estilo:** rodar `docker compose exec -T app ./vendor/bin/pint` ao final de cada task com PHP novo (imports ordenados, formatação).
- **Fonte da verdade de design:** `design-system/` (tokens/escala/A11y) tem precedência; o protótipo `design_handoff_cemanet/prototype/CEMA Site.dc.html` é referência de **estrutura/ordem das seções e marcação semântica** (converter `style=` inline → classes Tailwind dos tokens), não de valores. Descartar todo o andaime `.dc`/`sc-*`/`support.js`/React.

## Dados disponíveis (já implementados — não recriar)

- **Models:** `App\Models\Palestra` (`scopePublicado`, `palestrantes()`, `palestrantesAtivos()`, `assuntos()`, `getDiretorAttribute()`, `destaques()`, casts de `data_da_palestra`/`online`/inteiros, constantes `STATUS_*`/`PAPEL_*`); `App\Models\Palestrante` (`scopeAtivo`, `palestras()`, campos `nome/slug/foto/bio/email/telefone/mostrar_email/mostrar_telefone/ativo`); `App\Models\Assunto` (`parent()`/`children()`); `App\Models\PalestraDestaque` (`destaque`/`texto`/`ordem`).
- **Factories:** `PalestraFactory` (default `status=publicado`), `PalestranteFactory` (estados `ativo()`/`inativo()`), `AssuntoFactory`. Pivô anexado nos testes via `$palestra->palestrantes()->attach($p, ['papel' => Palestra::PAPEL_PALESTRANTE|PAPEL_DIRETOR])` e `$palestra->assuntos()->attach($a)` (ver `tests/Feature/Models/RelacoesPalestraTest.php`).
- **Fotos:** baixadas para `storage/app/public/palestrantes/…`; `storage:link` existe → servir via `Storage::url($palestrante->foto)` ou `/storage/{foto}`. O campo `foto` guarda o caminho relativo (ex.: `palestrantes/abadio-rodrigues.jpg`).
- **Build:** Tailwind v4 + `@theme` completo (cores/dourado/footer-bg/breakpoints/fontes) em `resources/css/app.css`; fontes Bunny compiladas (Work Sans 400/600, Poppins 400, Roboto 400/500/600, Roboto Slab 400, Roboto Mono 400/500). `resources/js/app.js` está vazio.
- **Assets de logo (origem):** `design_handoff_cemanet/prototype/assets/` → `logo-horizontal.png`, `logo-branco.png`, `logo-icone.png`, `logo-vert.png`, `logo-vert-comp.png`.

## File Structure

**Criados:**
- `config/navegacao.php` — árvore do menu (rótulo, rota/url, ativo, itens). Fonte única para header e footer.
- `public/images/logos/*.png` — 5 logos copiados do handoff.
- `resources/views/components/layout/app.blade.php` — layout base (`<x-layout.app>`): `<head>` (title/description/OG, `@vite`, favicon), `<body>` com header + `{{ $slot }}` + footer.
- `resources/views/components/layout/header.blade.php` — header sticky (logo, busca, auth-links, mega-menu desktop, off-canvas mobile).
- `resources/views/components/layout/footer.blade.php` — footer (marca, listas, newsletter visual, redes, barra legal).
- `resources/views/components/palestra/card.blade.php` — card de palestra (`<x-palestra.card :palestra="$p" />`), reusado.
- `resources/views/pages/inicio.blade.php` — home placeholder mínima.
- `resources/views/palestras/index.blade.php` — página da listagem (envolve o componente Livewire).
- `resources/views/livewire/palestras/lista.blade.php` — view do componente Livewire (grid + busca + filtro + paginação).
- `resources/views/palestras/show.blade.php` — página individual (T06).
- `app/Http/Controllers/PalestraController.php` — `index` (view) e `show` (single + ant/próx).
- `app/Livewire/Palestras/Lista.php` — componente Livewire da listagem reativa.
- Testes: `tests/Feature/Front/LayoutTest.php`, `tests/Feature/Front/PalestrasListagemTest.php`, `tests/Feature/Livewire/PalestrasListaTest.php`, `tests/Feature/Front/PalestraSingleTest.php`, `tests/Feature/Front/PalestraInteracoesTest.php`.

**Modificados:**
- `routes/web.php` — rotas `home`, `palestras.index`, `palestras.show`.
- `resources/js/app.js` — registrar Alpine plugins se necessário (Livewire já inicia o Alpine).
- `composer.json`/`composer.lock` — `livewire/livewire`.

---

## Task 1: Instalar Livewire 3 + base do layout (shell, navegação, logos, home)

**Files:**
- Modify: `composer.json` (via `composer require`)
- Create: `config/navegacao.php`
- Create: `public/images/logos/` (5 PNGs)
- Create: `resources/views/components/layout/app.blade.php`
- Create: `resources/views/components/layout/header.blade.php` (placeholder mínimo — substituído na Task 2)
- Create: `resources/views/components/layout/footer.blade.php` (placeholder mínimo — substituído na Task 3)
- Create: `resources/views/pages/inicio.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Front/LayoutTest.php`

**Interfaces:**
- Produces: rota nomeada `home` (`/`), `palestras.index` (`/palestras`) e `palestras.show` (`/palestras/{slug}`) — registradas já nesta task (controller stub na Task 4/5; aqui apontam para closures temporárias OU já para o controller criado nesta task como stub). Para evitar rota quebrada, registrar `home` real e `palestras.index`/`palestras.show` como closures temporárias retornando `abort(404)` até as Tasks 4/5. **Decisão:** registrar as três; `palestras.*` apontam para closures `fn () => abort(404)` nesta task, substituídas pelo controller nas Tasks 4/5.
- Produces: `<x-layout.app :title="..." :description="...">{{ slot }}</x-layout.app>`, lendo o menu de `config('navegacao.menu')`.
- Produces: `config/navegacao.php` retorna `['menu' => [ ['rotulo'=>..., 'rota'=>?string, 'url'=>?string, 'ativo'=>bool, 'itens'=>[...]] ]]`. `ativo=false` ⇒ item desabilitado (placeholder, não vira `<a href>`).

- [ ] **Step 1: Instalar Livewire 3**

Run: `docker compose exec -T app composer require livewire/livewire`
Expected: instala `livewire/livewire` (^3). Livewire 3 injeta scripts/estilos automaticamente e inclui o Alpine — não instalar Alpine via npm.

- [ ] **Step 2: Copiar os logos para `public/images/logos/`**

Run (no host, PowerShell ou bash):
```bash
mkdir -p "public/images/logos"
cp "design_handoff_cemanet/prototype/assets/logo-horizontal.png" "public/images/logos/"
cp "design_handoff_cemanet/prototype/assets/logo-branco.png" "public/images/logos/"
cp "design_handoff_cemanet/prototype/assets/logo-icone.png" "public/images/logos/"
cp "design_handoff_cemanet/prototype/assets/logo-vert.png" "public/images/logos/"
cp "design_handoff_cemanet/prototype/assets/logo-vert-comp.png" "public/images/logos/"
```
Expected: 5 arquivos em `public/images/logos/`.

- [ ] **Step 3: Criar `config/navegacao.php`**

Menu raiz do design (8 itens). Só `Palestras` tem alvo real nesta fase; os demais ficam desabilitados (placeholder) para não gerar link quebrado.

```php
<?php

// Navegação principal do site (header e footer). Itens com 'ativo' => false
// ficam desabilitados (placeholder) até o módulo correspondente existir.
return [
    'menu' => [
        [
            'rotulo' => 'Institucional',
            'ativo' => false,
            'itens' => [
                ['rotulo' => 'Nossa História', 'ativo' => false],
                ['rotulo' => 'Contato', 'ativo' => false],
            ],
        ],
        [
            'rotulo' => 'Palestras',
            'rota' => 'palestras.index',
            'ativo' => true,
            'itens' => [
                ['rotulo' => 'Palestras Públicas', 'rota' => 'palestras.index', 'ativo' => true],
                ['rotulo' => 'Palestrantes', 'ativo' => false],
            ],
        ],
        ['rotulo' => 'Mensagens Mediúnicas', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Eventos', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Vibração', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Agenda', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Evangelho', 'ativo' => false, 'itens' => []],
        ['rotulo' => 'Sementeira', 'ativo' => false, 'itens' => []],
    ],
];
```

- [ ] **Step 4: Escrever o teste de layout (falha primeiro)**

`tests/Feature/Front/LayoutTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_home_renderiza_com_layout_base(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('CEMA', false); // alt do logo / marca
        $resp->assertSee('lang="pt-BR"', false);
        // link para a listagem de palestras presente na navegação
        $resp->assertSee(route('palestras.index'), false);
    }
}
```

- [ ] **Step 5: Rodar o teste (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: FAIL (rotas/views inexistentes).

- [ ] **Step 6: Registrar as rotas**

`routes/web.php`:
```php
<?php

use App\Http\Controllers\PalestraController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('pages.inicio'))->name('home');

// Substituídas pelo controller nas Tasks 4 e 5.
Route::get('/palestras', fn () => abort(404))->name('palestras.index');
Route::get('/palestras/{slug}', fn () => abort(404))->name('palestras.show');
```
> Nota para a Task 4/5: trocar as closures por `[PalestraController::class, 'index']` e `[PalestraController::class, 'show']`.

- [ ] **Step 7: Criar o layout base `<x-layout.app>`**

`resources/views/components/layout/app.blade.php`:
```blade
@props(['title' => null, 'description' => null])

@php
    $tituloPagina = $title ? $title.' — CEMA' : 'CEMA — Centro Espírita Maria Madalena';
    $descricaoPagina = $description ?? 'Centro Espírita Maria Madalena — uma casa de fé, estudo e caridade em Planaltina, DF.';
@endphp
<!DOCTYPE html>
<html lang="pt-BR" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloPagina }}</title>
    <meta name="description" content="{{ $descricaoPagina }}">
    <meta property="og:title" content="{{ $tituloPagina }}">
    <meta property="og:description" content="{{ $descricaoPagina }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="icon" href="{{ asset('images/logos/logo-icone.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen flex flex-col bg-white font-sans text-text antialiased">
    <x-layout.header />

    <main id="conteudo" class="flex-1">
        {{ $slot }}
    </main>

    <x-layout.footer />
</body>
</html>
```

- [ ] **Step 8: Criar header placeholder mínimo (substituído na Task 2)**

`resources/views/components/layout/header.blade.php`:
```blade
{{-- Placeholder mínimo — versão completa (mega-menu + off-canvas + busca) na Task 2. --}}
<header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_var(--color-border)]">
    <div class="mx-auto flex max-w-[1240px] items-center gap-6 px-6 py-3">
        <a href="{{ route('home') }}" class="shrink-0">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}"
                 alt="CEMA — Centro Espírita Maria Madalena" class="h-11 w-auto" width="180" height="46">
        </a>
        <nav class="ml-auto" aria-label="Navegação principal">
            <ul class="flex gap-4">
                @foreach (config('navegacao.menu') as $item)
                    <li>
                        @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                            <a href="{{ route($item['rota']) }}" class="font-ui text-sm text-primary hover:underline">{{ $item['rotulo'] }}</a>
                        @else
                            <span class="font-ui text-sm text-text-muted" aria-disabled="true">{{ $item['rotulo'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>
```

- [ ] **Step 9: Criar footer placeholder mínimo (substituído na Task 3)**

`resources/views/components/layout/footer.blade.php`:
```blade
{{-- Placeholder mínimo — versão completa (marca, listas, newsletter, legal) na Task 3. --}}
<footer class="bg-footer-bg text-[#cfc9e4]">
    <div class="mx-auto max-w-[1240px] px-6 py-10">
        <img src="{{ asset('images/logos/logo-branco.png') }}" alt="CEMA" class="mb-4 h-16 w-auto" width="160" height="74">
        <address class="text-sm not-italic">
            Centro Espírita Maria Madalena · CNPJ 01.600.089/0001-90 · Planaltina, DF
        </address>
        <p class="mt-4 text-xs text-[#a89fce]">© 2026 CEMA · Todos os direitos reservados · Desenvolvido por DECOM</p>
    </div>
</footer>
```

- [ ] **Step 10: Criar a home placeholder**

`resources/views/pages/inicio.blade.php`:
```blade
<x-layout.app>
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-24 text-center">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Fé · Estudo · Caridade</p>
            <h1 class="mt-3 font-display text-4xl font-semibold md:text-5xl">Centro Espírita Maria Madalena</h1>
            <p class="mx-auto mt-4 max-w-xl text-lg text-white/85">
                Em construção. Enquanto isso, conheça as palestras públicas da casa.
            </p>
            <a href="{{ route('palestras.index') }}"
               class="mt-8 inline-flex items-center gap-2 rounded-pill bg-gold px-6 py-3 font-ui font-semibold text-footer-bg transition hover:brightness-105">
                Ver palestras →
            </a>
        </div>
    </section>
</x-layout.app>
```

- [ ] **Step 11: Compilar assets e rodar o teste (deve passar)**

Run: `npm run build`
Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: PASS.

- [ ] **Step 12: Pint + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
```bash
git add -A
git commit -m "feat(front): instala Livewire e cria base do layout (shell, navegação, logos, home)"
```

---

## Task 2: Header completo (mega-menu desktop + off-canvas mobile + busca)

**Files:**
- Modify: `resources/views/components/layout/header.blade.php` (substitui o placeholder)
- Modify: `resources/views/components/layout/app.blade.php` (incluir `@livewireStyles`/`@livewireScripts` — ver Step 4)
- Test: `tests/Feature/Front/LayoutTest.php` (acrescenta asserts)

**Interfaces:**
- Consumes: `config('navegacao.menu')` (Task 1), rota `palestras.index`.
- Produces: header com `<form role="search" method="GET" action="{{ route('palestras.index') }}">` (input `name="q"`), mega-menu desktop (hover, `aria-haspopup`/`aria-expanded`) e off-canvas mobile (Alpine: `x-data`, foco, `Esc`). A busca alimenta a listagem reativa da Task 4 via query string `?q=`.

> **Alpine em todas as páginas.** O off-canvas usa Alpine. No Livewire 4 os assets só são
> **auto-injetados** quando um componente Livewire renderiza na requisição (confirmado em
> `SupportAutoInjectedAssets::shouldInjectLivewireAssets`). Home e single são Blade puro (sem
> componente Livewire) → sem `@livewireScripts` explícito, o Alpine não carrega nelas. Por isso o
> Step 4 adiciona `@livewireStyles`/`@livewireScripts` ao layout (carrega Livewire+Alpine sempre;
> nas páginas com componente, o Livewire detecta a colocação manual e não duplica).

- [ ] **Step 1: Acrescentar asserts ao teste (falha primeiro)**

Em `tests/Feature/Front/LayoutTest.php`, adicionar método:
```php
    public function test_header_tem_busca_e_itens_de_menu(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        // formulário de busca aponta para a listagem (GET ?q=)
        $resp->assertSee('action="'.route('palestras.index').'"', false);
        $resp->assertSee('name="q"', false);
        // item ativo é link; item futuro é placeholder (sem href de rota)
        $resp->assertSee('>Palestras<', false);
        $resp->assertSee('Mensagens Mediúnicas', false);
    }

    public function test_alpine_carregado_em_pagina_sem_componente_livewire(): void
    {
        // A home é Blade puro (sem componente Livewire); o header usa Alpine.
        // @livewireScripts no layout garante Livewire+Alpine carregados mesmo aqui.
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('livewire', false); // tag de script do Livewire (traz o Alpine)
    }
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: FAIL (placeholder não tem busca).

- [ ] **Step 3: Implementar o header completo**

Estrutura: duas faixas (barra branca com logo+busca+auth+hambúrguer; faixa roxa com mega-menu no desktop) + off-canvas mobile. Converter o protótipo (`design_handoff_cemanet/prototype/CEMA Site.dc.html`, header linhas ~33-112) para Tailwind dos tokens. Desktop a partir de `desktop-sm` (1024px); abaixo disso, hambúrguer + off-canvas.

`resources/views/components/layout/header.blade.php`:
```blade
@php($menu = config('navegacao.menu'))

<header class="sticky top-0 z-50 bg-white shadow-[0_1px_0_var(--color-border)]"
        x-data="{ menuMobile: false }">
    {{-- Faixa 1: logo + busca + auth/hambúrguer --}}
    <div class="mx-auto flex max-w-[1240px] items-center gap-5 px-6 py-3">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="Página inicial do CEMA">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}"
                 alt="CEMA — Centro Espírita Maria Madalena" class="h-11 w-auto" width="180" height="46">
        </a>

        {{-- Busca (desktop) --}}
        <form role="search" method="GET" action="{{ route('palestras.index') }}"
              class="hidden flex-1 desktop-sm:flex max-w-[420px] items-center rounded-pill border border-border bg-surface">
            <label for="busca-topo" class="sr-only">Pesquisar palestras</label>
            <input id="busca-topo" type="search" name="q" placeholder="Pesquisar palestras…"
                   class="w-full bg-transparent px-4 py-2 font-sans text-sm text-text outline-none">
            <button type="submit" aria-label="Buscar"
                    class="m-1 flex size-8 items-center justify-center rounded-full bg-primary text-white">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </button>
        </form>

        {{-- Auth (desktop) --}}
        <div class="ml-auto hidden items-center gap-1.5 desktop-sm:flex font-ui text-sm">
            <span class="text-text-muted">Possui uma conta?</span>
            <span class="font-semibold text-primary" aria-disabled="true">Entrar</span>
            <span class="text-text-muted">·</span>
            <span class="font-semibold text-secondary" aria-disabled="true">Cadastrar</span>
        </div>

        {{-- Hambúrguer (mobile) --}}
        <button type="button" class="ml-auto desktop-sm:hidden rounded-md p-2 text-primary"
                @click="menuMobile = true" :aria-expanded="menuMobile" aria-controls="menu-mobile" aria-label="Abrir menu">
            <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M3 6h18M3 12h18M3 18h18"/>
            </svg>
        </button>
    </div>

    {{-- Faixa 2: mega-menu (desktop) --}}
    <nav class="hidden bg-primary desktop-sm:block" aria-label="Navegação principal">
        <ul class="mx-auto flex max-w-[1240px] items-stretch px-6">
            @foreach ($menu as $item)
                @php($temItens = ! empty($item['itens']))
                <li class="group relative">
                    @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                        <a href="{{ route($item['rota']) }}"
                           class="flex items-center gap-1 px-4 py-3 font-ui text-sm text-[#efeaf7] hover:bg-white/10"
                           @if($temItens) aria-haspopup="true" @endif>
                            {{ $item['rotulo'] }}
                            @if($temItens)<span aria-hidden="true" class="text-[9px]">▾</span>@endif
                        </a>
                    @else
                        <span class="flex cursor-default items-center gap-1 px-4 py-3 font-ui text-sm text-[#efeaf7]/60"
                              aria-disabled="true" @if($temItens) aria-haspopup="true" @endif>
                            {{ $item['rotulo'] }}
                            @if($temItens)<span aria-hidden="true" class="text-[9px]">▾</span>@endif
                        </span>
                    @endif

                    @if($temItens)
                        <div class="invisible absolute left-0 top-full z-50 min-w-[232px] translate-y-2 rounded-b-xl border-t-[3px] border-gold bg-white p-2 opacity-0 shadow-elevated transition group-hover:visible group-hover:translate-y-0 group-hover:opacity-100">
                            <ul>
                                @foreach ($item['itens'] as $sub)
                                    <li>
                                        @if (($sub['ativo'] ?? false) && ($sub['rota'] ?? null))
                                            <a href="{{ route($sub['rota']) }}" class="block rounded-md px-3.5 py-2 font-ui text-sm text-text hover:bg-surface hover:text-primary">{{ $sub['rotulo'] }}</a>
                                        @else
                                            <span class="block rounded-md px-3.5 py-2 font-ui text-sm text-text-muted" aria-disabled="true">{{ $sub['rotulo'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    {{-- Off-canvas (mobile) --}}
    <div x-show="menuMobile" x-cloak class="fixed inset-0 z-[90] bg-[rgba(38,36,46,0.55)] desktop-sm:hidden"
         @click="menuMobile = false" x-transition.opacity></div>
    <aside id="menu-mobile" x-show="menuMobile" x-cloak role="dialog" aria-modal="true" aria-label="Menu"
           class="fixed inset-y-0 left-0 z-[95] flex w-[300px] max-w-[88vw] flex-col bg-white desktop-sm:hidden"
           x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
           @keydown.escape.window="menuMobile = false" x-trap="menuMobile">
        <div class="flex items-center justify-between border-b border-border-muted px-4 py-4">
            <img src="{{ asset('images/logos/logo-horizontal.png') }}" alt="CEMA" class="h-9 w-auto" width="150" height="38">
            <button type="button" class="flex size-9 items-center justify-center rounded-md bg-surface text-xl text-text" @click="menuMobile = false" aria-label="Fechar menu">×</button>
        </div>
        <nav class="flex-1 overflow-y-auto px-2.5 py-2" aria-label="Navegação principal (mobile)">
            <p class="mx-2 mb-1 mt-2 font-mono text-xs uppercase tracking-[0.08em] text-text-muted">Menu</p>
            <ul>
                @foreach ($menu as $item)
                    <li class="border-b border-[#f2f1f4]">
                        @if (empty($item['itens']))
                            @if (($item['ativo'] ?? false) && ($item['rota'] ?? null))
                                <a href="{{ route($item['rota']) }}" class="block px-2 py-3 font-ui text-[15px] font-medium text-text">{{ $item['rotulo'] }}</a>
                            @else
                                <span class="block px-2 py-3 font-ui text-[15px] text-text-muted" aria-disabled="true">{{ $item['rotulo'] }}</span>
                            @endif
                        @else
                            <details>
                                <summary class="flex cursor-pointer items-center justify-between px-2 py-3 font-ui text-[15px] font-medium text-text">
                                    {{ $item['rotulo'] }}<span aria-hidden="true" class="text-[10px]">▾</span>
                                </summary>
                                <div class="pb-1.5">
                                    @foreach ($item['itens'] as $sub)
                                        @if (($sub['ativo'] ?? false) && ($sub['rota'] ?? null))
                                            <a href="{{ route($sub['rota']) }}" class="block py-2 pl-[18px] pr-2 font-ui text-sm text-text">{{ $sub['rotulo'] }}</a>
                                        @else
                                            <span class="block py-2 pl-[18px] pr-2 font-ui text-sm text-text-muted" aria-disabled="true">{{ $sub['rotulo'] }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    </aside>
</header>
```
> `x-cloak` precisa de `[x-cloak]{display:none!important}`. Adicionar essa regra em `resources/css/app.css` (fora do `@theme`): `@layer base { [x-cloak] { display: none !important; } }`. Incluir esse passo aqui.

- [ ] **Step 4: Garantir Alpine global (Livewire directives) + regra `x-cloak`**

(a) Em `resources/views/components/layout/app.blade.php`, adicionar as diretivas do Livewire para
carregar Livewire+Alpine em TODAS as páginas (sem isso, Home/single não teriam Alpine):
- `@livewireStyles` logo após o `@vite(...)` no `<head>`;
- `@livewireScripts` imediatamente antes de `</body>`.

```blade
    {{-- ...dentro do <head>, após @vite --}}
    @livewireStyles
    {{ $head ?? '' }}
</head>
```
```blade
    <x-layout.footer />

    @livewireScripts
</body>
```

(b) Em `resources/css/app.css`, ao final do arquivo:
```css
@layer base {
    [x-cloak] { display: none !important; }
}
```

- [ ] **Step 5: Build + rodar o teste (deve passar)**

Run: `npm run build`
Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: PASS.

- [ ] **Step 6: Verificação manual (registrar no relatório)**

Abrir `http://localhost:8000` no navegador: conferir mega-menu (hover abre dropdown com borda dourada), off-canvas mobile (<1024px: hambúrguer abre painel, `Esc`/overlay fecham, foco preso), busca submete para `/palestras?q=...`. Registrar resultado.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(front): header completo (mega-menu desktop, off-canvas mobile, busca)"
```

---

## Task 3: Footer completo

**Files:**
- Modify: `resources/views/components/layout/footer.blade.php` (substitui o placeholder)
- Test: `tests/Feature/Front/LayoutTest.php` (acrescenta assert)

**Interfaces:**
- Consumes: rota `palestras.index`, asset `logo-branco.png`.
- Produces: footer com 4 blocos (marca, institucional, atividades, newsletter visual) + barra legal (endereço/CNPJ). Newsletter é **apenas visual** (sem submissão funcional) — marcar com `aria-disabled`/texto "em breve"; **não** apontar `action` para rota inexistente.

- [ ] **Step 1: Acrescentar assert ao teste (falha primeiro)**

Em `LayoutTest`:
```php
    public function test_footer_tem_cnpj_e_link_palestras(): void
    {
        $resp = $this->get(route('home'));

        $resp->assertOk();
        $resp->assertSee('01.600.089/0001-90', false);          // CNPJ
        $resp->assertSee(route('palestras.index'), false);       // link nas atividades
        $resp->assertSee('Inscreva-se', false);                  // newsletter (visual)
    }
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: FAIL.

- [ ] **Step 3: Implementar o footer completo**

`resources/views/components/layout/footer.blade.php`:
```blade
<footer class="bg-footer-bg text-[#cfc9e4]">
    <div class="mx-auto grid max-w-[1240px] gap-10 px-6 py-14 sm:grid-cols-2 desktop-sm:grid-cols-[1.4fr_1fr_1fr_1.3fr]">
        {{-- Marca --}}
        <div>
            <img src="{{ asset('images/logos/logo-branco.png') }}" alt="CEMA" class="mb-4 h-[74px] w-auto" width="160" height="74">
            <p class="text-sm leading-relaxed text-[#bdb4dd]">
                Centro Espírita Maria Madalena — uma casa de fé, estudo e caridade em Planaltina, DF.
            </p>
        </div>

        {{-- Institucional --}}
        <nav aria-label="Institucional">
            <h2 class="mb-3 font-display text-base font-semibold text-white">Institucional</h2>
            <ul class="space-y-2 text-sm">
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Nossa História</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Nosso Blog</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Contato</span></li>
            </ul>
        </nav>

        {{-- Atividades --}}
        <nav aria-label="Atividades">
            <h2 class="mb-3 font-display text-base font-semibold text-white">Atividades</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('palestras.index') }}" class="text-[#cfc9e4] hover:text-white hover:underline">Palestras Públicas</a></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Palestrantes</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Evangelho da Semana</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Agenda Reforma Íntima</span></li>
            </ul>
        </nav>

        {{-- Newsletter (apenas visual — backend de e-mail em fase futura) --}}
        <div>
            <h2 class="mb-3 font-display text-base font-semibold text-white">Inscreva-se</h2>
            <p class="mb-3 text-sm text-[#bdb4dd]">Receba novidades da casa. (Em breve.)</p>
            <form class="space-y-2" aria-label="Inscrição na newsletter (em breve)" onsubmit="return false">
                <label for="nl-nome" class="sr-only">Nome</label>
                <input id="nl-nome" type="text" placeholder="Nome" disabled
                       class="w-full rounded-md border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/50">
                <label for="nl-email" class="sr-only">E-mail</label>
                <input id="nl-email" type="email" placeholder="E-mail" disabled
                       class="w-full rounded-md border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/50">
                <button type="submit" disabled aria-disabled="true"
                        class="w-full cursor-not-allowed rounded-md bg-gold px-4 py-2 font-ui font-semibold text-footer-bg opacity-80">Inscrever</button>
            </form>
            <ul class="mt-4 flex gap-3" aria-label="Redes sociais">
                @foreach (['YouTube' => '#', 'Instagram' => '#', 'Facebook' => '#', 'WhatsApp' => '#'] as $rede => $url)
                    <li><span class="text-xs text-[#bdb4dd]" aria-disabled="true">{{ $rede }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Barra legal --}}
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1240px] flex-col gap-2 px-6 py-5 text-xs text-[#a89fce] sm:flex-row sm:items-center sm:justify-between">
            <address class="not-italic">
                Quadra 02, Lote 16, Vila Vicentina — Planaltina, DF · CNPJ 01.600.089/0001-90
            </address>
            <p>© 2026 CEMA · Todos os direitos reservados · Desenvolvido por DECOM</p>
        </div>
    </div>
</footer>
```

- [ ] **Step 4: Build + rodar (deve passar)**

Run: `npm run build`
Run: `docker compose exec -T app php artisan test --filter=LayoutTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(front): footer completo (marca, listas, newsletter visual, legal)"
```

---

## Task 4: Listagem `/palestras` (Livewire reativo + card)

**Files:**
- Create: `app/Http/Controllers/PalestraController.php` (método `index`)
- Modify: `routes/web.php` (apontar `palestras.index` para o controller)
- Create: `app/Livewire/Palestras/Lista.php`
- Create: `resources/views/livewire/palestras/lista.blade.php`
- Create: `resources/views/palestras/index.blade.php`
- Create: `resources/views/components/palestra/card.blade.php`
- Test: `tests/Feature/Front/PalestrasListagemTest.php`, `tests/Feature/Livewire/PalestrasListaTest.php`

**Interfaces:**
- Consumes: `Palestra::publicado()`, `palestrantesAtivos`, `assuntos`, layout `<x-layout.app>`.
- Produces: `GET /palestras` (200) renderiza `palestras.index`, que embute `<livewire:palestras.lista :q="request('q')" :assunto="request('assunto')" />`. O componente Livewire sincroniza `q`/`assunto` na URL (`#[Url]`), pagina (9 por página) e ordena por `data_da_palestra` desc. Card: `<x-palestra.card :palestra="$p" />` linka para `palestras.show`.

- [ ] **Step 1: Escrever o card component**

`resources/views/components/palestra/card.blade.php`:
```blade
@props(['palestra'])

@php
    $primeiro = $palestra->palestrantesAtivos->first();
    $foto = $primeiro?->foto ? asset('storage/'.$primeiro->foto) : null;
    $data = $palestra->data_da_palestra;
@endphp
<article {{ $attributes->class(['group flex flex-col overflow-hidden rounded-lg border border-border-muted bg-white shadow-card transition hover:shadow-elevated']) }}>
    <a href="{{ route('palestras.show', $palestra->slug) }}" class="flex h-full flex-col">
        <div class="aspect-[16/10] overflow-hidden bg-cream">
            @if ($foto)
                <img src="{{ $foto }}" alt="{{ $primeiro->nome }}" loading="lazy" width="320" height="200"
                     class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            @else
                <div class="flex size-full items-center justify-center font-mono text-xs text-text-muted">CEMA</div>
            @endif
        </div>
        <div class="flex flex-1 flex-col p-5">
            <div class="mb-2 flex items-center gap-2 font-mono text-[11px] uppercase tracking-wide text-text-muted">
                @if ($data)<time datetime="{{ $data->toIso8601String() }}">{{ $data->translatedFormat('d \d\e M Y') }}</time>@endif
                <span class="rounded-pill bg-surface px-2 py-0.5 text-[10px] text-primary">{{ $palestra->online ? 'Online' : 'Presencial' }}</span>
            </div>
            <h3 class="font-display text-lg font-semibold leading-snug text-primary group-hover:underline">{{ $palestra->titulo }}</h3>
            @if ($palestra->subtitulo)
                <p class="mt-1 line-clamp-2 text-sm text-text-secondary">{{ $palestra->subtitulo }}</p>
            @endif
            @if ($palestra->palestrantesAtivos->isNotEmpty())
                <p class="mt-3 text-sm text-text-secondary">
                    <span class="text-text-muted">Palestrante:</span>
                    {{ $palestra->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}
                </p>
            @endif
        </div>
    </a>
</article>
```

- [ ] **Step 2: Escrever os testes (falham primeiro)**

`tests/Feature/Front/PalestrasListagemTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrasListagemTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_mostra_publicadas_e_oculta_rascunho(): void
    {
        $pub = Palestra::factory()->create(['titulo' => 'Auxílios do Invisível', 'status' => Palestra::STATUS_PUBLICADO]);
        $rasc = Palestra::factory()->create(['titulo' => 'Rascunho Secreto', 'status' => Palestra::STATUS_RASCUNHO]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertOk();
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertDontSee('Rascunho Secreto');
    }

    public function test_listagem_mostra_so_palestrante_ativo(): void
    {
        $palestra = Palestra::factory()->create();
        $ativo = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        $diretor = Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);
        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);

        $resp = $this->get(route('palestras.index'));

        $resp->assertSee('João Ativo');
        $resp->assertDontSee('Maria Inativa');
    }
}
```

`tests/Feature/Livewire/PalestrasListaTest.php`:
```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Lista;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalestrasListaTest extends TestCase
{
    use RefreshDatabase;

    public function test_busca_filtra_por_titulo(): void
    {
        Palestra::factory()->create(['titulo' => 'Paz e Amor']);
        Palestra::factory()->create(['titulo' => 'Caridade Silenciosa']);

        Livewire::test(Lista::class)
            ->set('q', 'Paz')
            ->assertSee('Paz e Amor')
            ->assertDontSee('Caridade Silenciosa');
    }

    public function test_ordena_por_data_desc(): void
    {
        $antiga = Palestra::factory()->create(['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 16:00:00']);
        $nova = Palestra::factory()->create(['titulo' => 'Nova', 'data_da_palestra' => '2026-01-01 16:00:00']);

        $html = Livewire::test(Lista::class)->html();

        $this->assertLessThan(strpos($html, 'Antiga'), strpos($html, 'Nova'));
    }
}
```

- [ ] **Step 3: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter="PalestrasListagemTest|PalestrasListaTest"`
Expected: FAIL.

> **API Livewire 4 (instalado: v4.3.1 via Filament).** Diferenças do v3 que valem para esta task:
> (1) `make:livewire ... --class` gera componente **multi-file** (classe em `app/Livewire`, view em
> `resources/views/livewire`); sem `--class`, o v4 gera **single-file** por padrão — use `--class`.
> (2) `wire:model` é **deferred** por padrão no v4; para busca reativa use `wire:model.live` (já está na view).
> (3) Assets são **auto-injetados** em respostas HTML (o `<x-layout.app>` tem `</head>`/`</body>`), então
> **não** precisa de `@livewireScripts`/`@livewireStyles`. (4) Paginação default já é **Tailwind** —
> `->links()` basta. (5) `#[Url]` lê a query string no load inicial, então **não** é preciso passar
> `:q`/`:assunto` ao embutir o componente. (6) Reset de página via hooks `updatedQ()`/`updatedAssunto()`.

- [ ] **Step 4: Criar o componente Livewire**

Run: `docker compose exec -T app php artisan make:livewire Palestras/Lista --class`
Substituir `app/Livewire/Palestras/Lista.php` por:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Livewire\Palestras;

use App\Models\Palestra;
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

    // Livewire 4: resetar a paginação quando o filtro muda (hooks updated*).
    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedAssunto(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $palestras = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->when($this->q !== '', function ($query) {
                $termo = '%'.$this->q.'%';
                $query->where(function ($q) use ($termo) {
                    $q->where('titulo', 'like', $termo)
                        ->orWhere('subtitulo', 'like', $termo)
                        ->orWhere('resumo', 'like', $termo);
                });
            })
            ->when($this->assunto !== '', function ($query) {
                $query->whereHas('assuntos', fn ($a) => $a->where('slug', $this->assunto));
            })
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->paginate(9);

        return view('livewire.palestras.lista', ['palestras' => $palestras]);
    }
}
```
> `orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')` coloca as sem data por último e ordena as demais desc. Funciona em MySQL e SQLite.

- [ ] **Step 5: Criar a view do componente Livewire**

`resources/views/livewire/palestras/lista.blade.php`:
```blade
<div>
    {{-- Busca + filtro --}}
    <form class="mb-8 flex flex-col gap-3 sm:flex-row" wire:submit.prevent>
        <label for="busca-lista" class="sr-only">Buscar palestras</label>
        <input id="busca-lista" type="search" wire:model.live.debounce.350ms="q"
               placeholder="Buscar por título ou assunto…"
               class="w-full rounded-pill border border-border bg-white px-5 py-2.5 font-sans text-sm text-text outline-none focus:border-primary">
    </form>

    @if ($this->assunto !== '')
        <p class="mb-4 text-sm text-text-secondary">
            Filtrando por assunto:
            <button type="button" wire:click="$set('assunto','')" class="font-semibold text-secondary hover:underline">limpar filtro ✕</button>
        </p>
    @endif

    @if ($palestras->isEmpty())
        <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-secondary">
            Nenhuma palestra encontrada.
        </p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3">
            @foreach ($palestras as $palestra)
                <x-palestra.card :palestra="$palestra" wire:key="palestra-{{ $palestra->id }}" />
            @endforeach
        </div>

        <div class="mt-10">
            {{ $palestras->onEachSide(1)->links() }}
        </div>
    @endif
</div>
```

- [ ] **Step 6: Criar o controller e a página da listagem**

`app/Http/Controllers/PalestraController.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-25

namespace App\Http\Controllers;

class PalestraController extends Controller
{
    public function index()
    {
        return view('palestras.index');
    }
}
```

`resources/views/palestras/index.blade.php`:
```blade
<x-layout.app title="Palestras Públicas" description="Palestras públicas do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Palestras Públicas</p>
            <h1 class="mt-2 font-display text-4xl font-semibold">Palestras do CEMA</h1>
            <p class="mt-3 max-w-xl text-white/85">Reflexões à luz do Espiritismo, abertas a todos.</p>
        </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        {{-- #[Url] lê q/assunto da query string no load inicial; a busca do header (GET ?q=) cai aqui. --}}
        <livewire:palestras.lista />
    </section>
</x-layout.app>
```

Atualizar `routes/web.php`: trocar a closure de `palestras.index` por `[PalestraController::class, 'index']`.

- [ ] **Step 7: Rodar os testes (devem passar)**

Run: `docker compose exec -T app php artisan test --filter="PalestrasListagemTest|PalestrasListaTest"`
Expected: PASS.

- [ ] **Step 8: Pint + build + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
Run: `npm run build`
```bash
git add -A
git commit -m "feat(front): listagem /palestras com busca reativa (Livewire) e card"
```

---

## Task 5: Página individual `/palestras/{slug}` (T06, SSR)

**Files:**
- Modify: `app/Http/Controllers/PalestraController.php` (método `show`)
- Modify: `routes/web.php` (apontar `palestras.show` para o controller)
- Create: `resources/views/palestras/show.blade.php`
- Test: `tests/Feature/Front/PalestraSingleTest.php`

**Interfaces:**
- Consumes: `Palestra::publicado()`, `palestrantesAtivos`, `assuntos`, `destaques`, `<x-layout.app>`. Filtro de assunto da listagem (`palestras.index?assunto=`).
- Produces: `GET /palestras/{slug}` → 200 para publicada, 404 para rascunho/inexistente. View T06: hero (cor de fundo), card de palestrante(s) ativo(s), data/modalidade, vídeo (iframe lazy), acordeão de destaques (`<details>`), tags de assunto (links p/ listagem filtrada), navegação anterior/próxima. JSON-LD `schema.org/Event`. **Sem** seção "últimas notícias".

- [ ] **Step 1: Escrever os testes (falham primeiro)**

`tests/Feature/Front/PalestraSingleTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleTest extends TestCase
{
    use RefreshDatabase;

    private function palestraComPessoas(): Palestra
    {
        $palestra = Palestra::factory()->create([
            'titulo' => 'Auxílios do Invisível',
            'slug' => 'auxilios-do-invisivel',
            'status' => Palestra::STATUS_PUBLICADO,
        ]);
        $ativo = Palestrante::factory()->ativo()->create(['nome' => 'João Ativo']);
        $diretor = Palestrante::factory()->inativo()->create(['nome' => 'Maria Inativa']);
        $palestra->palestrantes()->attach($ativo, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        $palestra->palestrantes()->attach($diretor, ['papel' => Palestra::PAPEL_DIRETOR]);
        $palestra->destaques()->create(['destaque' => 'A fé raciocinada', 'texto' => 'Estudo sério.', 'ordem' => 0]);

        return $palestra;
    }

    public function test_single_publica_retorna_200_com_conteudo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertOk();
        $resp->assertSee('Auxílios do Invisível');
        $resp->assertSee('João Ativo');           // palestrante ativo aparece
        $resp->assertSee('A fé raciocinada');      // destaque aparece
    }

    public function test_single_nao_mostra_diretor_inativo(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertDontSee('Maria Inativa');
    }

    public function test_single_rascunho_da_404(): void
    {
        Palestra::factory()->create(['slug' => 'oculta', 'status' => Palestra::STATUS_RASCUNHO]);

        $this->get(route('palestras.show', 'oculta'))->assertNotFound();
    }

    public function test_single_slug_inexistente_da_404(): void
    {
        $this->get(route('palestras.show', 'nao-existe'))->assertNotFound();
    }

    public function test_single_tem_jsonld_event(): void
    {
        $this->palestraComPessoas();

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertSee('application/ld+json', false);
        $resp->assertSee('"@type":"Event"', false);
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraSingleTest`
Expected: FAIL.

- [ ] **Step 3: Implementar `show` no controller**

Adicionar a `app/Http/Controllers/PalestraController.php`:
```php
    public function show(string $slug)
    {
        $palestra = \App\Models\Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos', 'destaques'])
            ->where('slug', $slug)
            ->firstOrFail();

        $base = \App\Models\Palestra::query()->publicado();

        $anterior = (clone $base)
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '<', $palestra->data_da_palestra)
            ->orderByDesc('data_da_palestra')
            ->first();

        $proxima = (clone $base)
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>', $palestra->data_da_palestra)
            ->orderBy('data_da_palestra')
            ->first();

        return view('palestras.show', compact('palestra', 'anterior', 'proxima'));
    }
```
Atualizar `routes/web.php`: trocar a closure de `palestras.show` por `[PalestraController::class, 'show']`.

- [ ] **Step 4: Implementar a view T06**

`resources/views/palestras/show.blade.php` — converter a estrutura do protótipo (`isPalestra`, linhas ~509-625) para Tailwind dos tokens, **sem** a seção S5 "Últimas notícias". Usar `cor_fundo` da palestra no hero quando houver (fallback ao gradiente roxo). Embed do YouTube via `<iframe loading="lazy">` (a fachada clicável fica para enhancement futuro).

```blade
@php
    $palestrantes = $palestra->palestrantesAtivos;
    $data = $palestra->data_da_palestra;
    $heroStyle = $palestra->cor_fundo ? 'background:'.$palestra->cor_fundo : null;
    // extrai o ID do YouTube de formatos comuns (watch?v=, youtu.be/, live/, embed/)
    $ytId = null;
    if ($palestra->link_youtube && preg_match('~(?:v=|youtu\.be/|live/|embed/)([A-Za-z0-9_-]{6,})~', $palestra->link_youtube, $m)) {
        $ytId = $m[1];
    }
@endphp

<x-layout.app :title="$palestra->titulo" :description="$palestra->subtitulo ?? $palestra->resumo">
    <x-slot:head>
        <script type="application/ld+json">
        @json([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $palestra->titulo,
            'startDate' => optional($data)->toIso8601String(),
            'eventAttendanceMode' => $palestra->online ? 'https://schema.org/OnlineEventAttendanceMode' : 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => [
                '@type' => 'Place',
                'name' => 'Centro Espírita Maria Madalena',
                'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF',
            ],
            'performer' => $palestrantes->map(fn ($p) => ['@type' => 'Person', 'name' => $p->nome])->all(),
            'organizer' => ['@type' => 'Organization', 'name' => 'CEMA'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- S1: Hero (cor_fundo da palestra quando houver; senão, gradiente roxo) --}}
    <section class="relative overflow-hidden text-white" @if($heroStyle) style="{{ $heroStyle }}" @endif>
        @unless ($heroStyle)
            <div class="absolute inset-0 bg-gradient-to-br from-primary to-footer-bg"></div>
        @endunless
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestras.index') }}" class="hover:text-white">Palestras Públicas</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ \Illuminate\Support\Str::limit($palestra->titulo, 40) }}</span>
            </nav>
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestra Pública</p>
            <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight md:text-5xl">{{ $palestra->titulo }}</h1>
            @if ($palestra->subtitulo)
                <p class="mt-3 max-w-2xl text-lg text-white/85">{{ $palestra->subtitulo }}</p>
            @endif
        </div>
    </section>

    {{-- S2: Barra de ações (markup; comportamento JS na Task 6) --}}
    <section class="border-b border-border-muted bg-white" data-acoes-palestra>
        <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-2.5 px-6 py-4">
            <span class="text-sm text-text-muted">Compartilhar:</span>
            {{-- preenchido na Task 6 --}}
        </div>
    </section>

    {{-- S3: Conteúdo (grid 2 colunas) --}}
    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <div class="grid items-start gap-9 desktop-sm:grid-cols-[300px_1fr]">
            {{-- Coluna esquerda: palestrante(s) --}}
            <aside class="space-y-5">
                @forelse ($palestrantes as $p)
                    <div class="overflow-hidden rounded-xl border border-border-muted bg-cream">
                        @if ($p->foto)
                            <img src="{{ asset('storage/'.$p->foto) }}" alt="{{ $p->nome }}"
                                 loading="lazy" width="300" height="230" class="h-[230px] w-full object-cover">
                        @endif
                        <div class="p-5">
                            <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Palestrante</p>
                            <h2 class="mt-1 font-display text-xl font-semibold text-primary">{{ $p->nome }}</h2>
                            @if ($p->bio)
                                <div class="mt-2 line-clamp-4 text-sm text-text-secondary">{!! \Illuminate\Support\Str::limit(strip_tags($p->bio), 220) !!}</div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Palestrante a confirmar.</p>
                @endforelse
            </aside>

            {{-- Coluna direita --}}
            <div>
                @if ($ytId)
                    <div class="mb-7 overflow-hidden rounded-2xl bg-black">
                        <iframe class="aspect-video w-full" src="https://www.youtube.com/embed/{{ $ytId }}"
                                title="Vídeo: {{ $palestra->titulo }}" loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                    </div>
                @endif

                {{-- Data + Modalidade --}}
                <div class="mb-7 flex flex-wrap gap-3.5">
                    <div class="min-w-[170px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Data</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $data ? $data->translatedFormat('l, d \d\e F \d\e Y · H\hi') : 'A confirmar' }}</p>
                    </div>
                    <div class="min-w-[170px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Modalidade</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $palestra->online ? 'Online' : 'Presencial' }}</p>
                    </div>
                </div>

                {{-- Descrição --}}
                @if ($palestra->descricao)
                    <div class="mb-8 max-w-none text-text-secondary [&_p]:mb-4 [&_p]:leading-relaxed [&_a]:text-secondary [&_a]:underline">
                        {!! $palestra->descricao !!}
                    </div>
                @endif

                {{-- Acordeão de destaques --}}
                @if ($palestra->destaques->isNotEmpty())
                    <h2 class="mb-4 font-display text-2xl font-semibold text-primary">Principais tópicos abordados</h2>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($palestra->destaques as $d)
                            <details class="group overflow-hidden rounded-xl border border-border-muted bg-white">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 font-display font-medium text-text-ink">
                                    {{ $d->destaque }}
                                    <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cream text-primary transition group-open:rotate-45">+</span>
                                </summary>
                                @if ($d->texto)
                                    <div class="px-5 pb-5 text-sm text-text-secondary">{{ $d->texto }}</div>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif

                {{-- Tags de assunto --}}
                @if ($palestra->assuntos->isNotEmpty())
                    <div class="mt-8">
                        <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-text-muted">Assuntos principais</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($palestra->assuntos as $a)
                                <a href="{{ route('palestras.index', ['assunto' => $a->slug]) }}"
                                   class="rounded-pill border border-border bg-surface px-3.5 py-1.5 text-[13px] text-text-secondary hover:border-primary hover:text-primary">{{ $a->nome }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- S4: Navegação anterior/próxima --}}
    <section class="border-y border-border-muted bg-surface">
        <div class="mx-auto flex max-w-[1100px] flex-wrap justify-between gap-4 px-6 py-6">
            @if ($anterior)
                <a href="{{ route('palestras.show', $anterior->slug) }}" rel="prev" class="flex items-center gap-3 text-primary hover:underline">
                    <span aria-hidden="true" class="text-xl">‹</span>
                    <span>
                        <span class="block font-mono text-[10px] uppercase text-text-muted">Anterior</span>
                        <span class="font-semibold">{{ \Illuminate\Support\Str::limit($anterior->titulo, 38) }}</span>
                    </span>
                </a>
            @else <span></span> @endif

            @if ($proxima)
                <a href="{{ route('palestras.show', $proxima->slug) }}" rel="next" class="flex items-center gap-3 text-right text-primary hover:underline">
                    <span>
                        <span class="block font-mono text-[10px] uppercase text-text-muted">Próxima</span>
                        <span class="font-semibold">{{ \Illuminate\Support\Str::limit($proxima->titulo, 38) }}</span>
                    </span>
                    <span aria-hidden="true" class="text-xl">›</span>
                </a>
            @endif
        </div>
    </section>
</x-layout.app>
```
> Notas: (1) `translatedFormat` exige locale pt-BR — garantir `config('app.locale')='pt_BR'` ou usar `Carbon::setLocale('pt_BR')` no `AppServiceProvider::boot()` (incluir esse ajuste neste passo se o locale estiver em `en`). (2) `descricao` vem do legado como HTML confiável (conteúdo próprio) → `{!! !!}` é aceitável aqui; sanitização fica fora do escopo desta fatia.

- [ ] **Step 5: Garantir locale pt-BR para datas**

O `config/app.php` está com `'locale' => env('APP_LOCALE', 'en')` (en). `translatedFormat` precisa do locale pt-BR. Adicionar em `app/Providers/AppServiceProvider.php`, método `boot()` (hoje vazio):
```php
public function boot(): void
{
    \Illuminate\Support\Carbon::setLocale('pt_BR');
}
```

- [ ] **Step 6: Rodar os testes (devem passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraSingleTest`
Expected: PASS.

- [ ] **Step 7: Pint + build + commit**

Run: `docker compose exec -T app ./vendor/bin/pint`
Run: `npm run build`
```bash
git add -A
git commit -m "feat(front): página individual /palestras/{slug} (T06, SSR + JSON-LD)"
```

---

## Task 6: Interações de cliente na single (Alpine) — compartilhar, copiar, curtir

**Files:**
- Modify: `resources/views/palestras/show.blade.php` (preencher a barra de ações S2)
- Test: `tests/Feature/Front/PalestraInteracoesTest.php`

**Interfaces:**
- Consumes: Alpine (bundle do Livewire 3 — já disponível em todas as páginas com o layout). Plugins inclusos: `persist` (`$persist`), `focus`.
- Produces: barra de ações com compartilhar Facebook/WhatsApp (links `<a>` SSR), copiar link (Alpine + Clipboard API), curtir (Alpine + `$persist` localStorage), e Web Share API como enhancement no mobile.

- [ ] **Step 1: Escrever o teste (falha primeiro)**

`tests/Feature/Front/PalestraInteracoesTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraInteracoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_barra_de_acoes_tem_compartilhar_e_curtir(): void
    {
        Palestra::factory()->create(['slug' => 'auxilios-do-invisivel', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.show', 'auxilios-do-invisivel'));

        $resp->assertOk();
        $resp->assertSee('wa.me', false);                              // WhatsApp
        $resp->assertSee('facebook.com/sharer', false);                // Facebook
        $resp->assertSee('Copiar link', false);                        // copiar
        $resp->assertSee('x-data', false);                             // Alpine presente
    }
}
```

- [ ] **Step 2: Rodar (deve falhar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraInteracoesTest`
Expected: FAIL.

- [ ] **Step 3: Preencher a barra de ações S2**

Substituir o bloco `{{-- preenchido na Task 6 --}}` em `show.blade.php` por:
```blade
            @php($urlAtual = route('palestras.show', $palestra->slug))
            <div class="flex flex-wrap items-center gap-2.5"
                 x-data="{
                     url: @js($urlAtual),
                     titulo: @js($palestra->titulo),
                     copiado: false,
                     curtido: $persist(false).as('curtida_palestra_{{ $palestra->id }}'),
                     copiar() {
                         navigator.clipboard.writeText(this.url).then(() => {
                             this.copiado = true;
                             setTimeout(() => this.copiado = false, 2000);
                         });
                     },
                     async compartilhar() {
                         if (navigator.share) { try { await navigator.share({ title: this.titulo, url: this.url }); } catch (e) {} }
                     }
                 }">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white">f</span> Facebook
                </a>
                <a href="https://wa.me/?text={{ urlencode($palestra->titulo.' — '.$urlAtual) }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white">W</span> WhatsApp
                </a>
                <button type="button" @click="compartilhar()" x-show="navigator.share" x-cloak
                        class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">Compartilhar…</button>
                <button type="button" @click="copiar()"
                        class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
                </button>
                <button type="button" @click="curtido = !curtido" :aria-pressed="curtido"
                        class="ml-auto flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold transition"
                        :class="curtido ? 'text-danger border-danger' : 'text-primary'">
                    <span x-text="curtido ? '♥' : '♡'" aria-hidden="true"></span>
                    <span x-text="curtido ? 'Curtido' : 'Curtir'">Curtir</span>
                </button>
            </div>
```

- [ ] **Step 4: Rodar o teste (deve passar)**

Run: `docker compose exec -T app php artisan test --filter=PalestraInteracoesTest`
Expected: PASS.

- [ ] **Step 5: Verificação manual (registrar no relatório)**

Abrir uma palestra real no `localhost`: copiar link (muda para "Link copiado!"), curtir (persiste após reload via localStorage), links FB/WhatsApp abrem com a URL certa, botão "Compartilhar…" aparece só onde há `navigator.share`. Conferir responsivo (mobile/tablet/desktop) e contraste.

- [ ] **Step 6: Build + commit**

Run: `npm run build`
```bash
git add -A
git commit -m "feat(front): interações da palestra (compartilhar, copiar link, curtir) via Alpine"
```

---

## Verificação final (whole-branch)

- [ ] Rodar a suíte completa: `docker compose exec -T app php artisan test` — tudo verde (incluindo os 13 testes pré-existentes).
- [ ] `npm run build` sem erros; conferir peso do HTML de `/palestras/{slug}` bem abaixo do WP atual (~0,5 MB).
- [ ] Verificação manual no `localhost` com as 123 palestras reais: listagem (busca/filtro/paginação), single (todas as seções), responsivo mobile/tablet/desktop, contraste (secondary/accent só em elementos grandes), só palestrantes ativos exibidos.
- [ ] Atualizar `ROADMAP.md`: marcar "Front público: listagem + página individual" como concluído.

## Critérios de pronto (Definition of Done)

- `/palestras` lista as palestras publicadas com busca reativa, filtro por assunto e paginação; só palestrantes ativos aparecem.
- `/palestras/{slug}` renderiza a T06 completa (hero, palestrante ativo, vídeo, data/modalidade, acordeão de destaques, tags, ant/próx) com JSON-LD `Event`; rascunho/inexistente dão 404; diretor inativo nunca aparece.
- Layout base responsivo: header (mega-menu desktop + off-canvas mobile + busca) e footer fiéis ao design, sem links quebrados (módulos futuros desabilitados).
- Interações (compartilhar/copiar/curtir) funcionam no cliente.
- `php artisan test` verde + verificação manual no localhost.
