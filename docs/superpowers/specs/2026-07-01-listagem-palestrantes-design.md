# Redesign da Listagem de Palestrantes — Design/Spec

**Data:** 2026-07-01
**Autor:** Thiago Mourão — https://github.com/MouraoBSB
**Handoff visual:** `design_handoff_palestrantes/` (README hi-fi + `prototipo/Palestrantes.dc.html` + 3 screenshots)
**Molde:** `App\Livewire\Palestras\Lista` (archive, PR #1) — mesmo padrão de estado/busca/ordenação/grade/paginação.
**Fatia:** única — redesign do front de `/palestrantes` (listagem), reusando o back-end existente.

---

## 1. Objetivo

Redesenhar a **listagem pública de Palestrantes** (`/palestrantes`) na nova identidade
(hero roxo + partículas, Work Sans/Poppins, tokens do design-system), com **grade de cards**
(avatar da foto ou **iniciais** em gradiente + badge de contagem de palestras), **busca reativa**,
**ordenação**, **sidebar** (intro + estatísticas reais + card "Em destaque" da próxima palestra),
**estado vazio** e **paginação**. Substitui a listagem atual (busca simples + card antigo).

### Em escopo
- Casca `palestrantes/index.blade.php`: hero + breadcrumb + `<livewire:palestrantes.lista />` + sidebar.
- Componente Livewire `Palestrantes\Lista`: **estende o atual** (busca `q` + `withCount` já existem) com **ordenação** (`ordenar`), `updated()`, `limparFiltros()`, `filtrosAtivos()`.
- Redesign do card `x-palestrante.card` (avatar+badge+nome+botão; único consumidor é a lista).
- Sidebar (Blade estático, dados do controller): intro + 2 stats reais + "Em destaque" (próxima palestra, **sem fallback**).
- CSS próprio `resources/css/palestrantes.css` (hover do card, animação de entrada, badge) reusando `cema-grad-*`.
- Accessor `iniciais` no model `Palestrante`.
- SEO (título/descrição + JSON-LD `BreadcrumbList`), A11y, responsivo mobile-first.
- Testes + verificação no localhost.

### Fora de escopo (decisões do cliente / realidade do modelo)
- **Filtro por área (REMOVIDO nesta fase):** o handoff presume uma taxonomia de área **fictícia**; `Palestrante` **não tem** campo `area`. Removidos: **chips de área**, **dots de cor**, **card lateral "Explorar por área"** e a **linha de área** no card. **Não** criar coluna `area`, enum, nem campo no Filament. Feature adiada para quando houver taxonomia real.
- **Perfil** `/palestrantes/{slug}` (`show`) — já existe e funciona; os cards apenas linkam, **sem stub**.
- Importação de fotos do legado (fatia futura, read-only "quando liberado") → as fotos reais podem estar ausentes no lançamento; **o fallback de iniciais precisa carregar a página sozinho**.
- Admin/Filament, migração/schema.

---

## 2. Contexto (verificado no código — realidade que guia o design)

O handoff (§3.1/§3.3/§8) descreve um modelo com `area`, `foto_path`, `status`/`publicado()`,
`withCount('palestras')` — **nada disso corresponde ao código**. O real, conferido:

- **`App\Models\Palestrante`** ([app/Models/Palestrante.php](../../../app/Models/Palestrante.php)):
  - Scope **`ativo()`** (boolean `ativo`), **não** `publicado()`/enum status.
  - `palestras()` = `belongsToMany(Palestra, 'palestra_pessoa', 'pessoa_id', 'palestra_id')->withPivot('papel')`.
  - Foto por **Spatie Media Library**: accessors **`foto_url`** (WebP `web`) e **`foto_thumb_url`** (WebP `thumb`); **não** existe `foto_path`. Coleção `foto`.
  - **Sem** campo `area`. **Sem** accessor `iniciais` (a **criar**).
- **Contador** — reusar o alias já implementado em `Palestrantes\Lista`:
  `withCount(['palestras as palestras_ministradas_count' => fn($q) => $q->where('palestra_pessoa.papel', Palestra::PAPEL_PALESTRANTE)->where('palestras.status', Palestra::STATUS_PUBLICADO)])`.
  **Não** usar `withCount('palestras')`/`palestras_count` (contaria diretor e rascunho).
- **`Palestrantes\Lista` atual** ([app/Livewire/Palestrantes/Lista.php](../../../app/Livewire/Palestrantes/Lista.php)): já tem `#[Url(as:'q', except:'')] $q`, `updatedQ()→resetPage`, o `withCount` acima, `orderBy('nome')`, `paginate(12)`.
- **Rota** ([routes/web.php](../../../routes/web.php)): `palestrantes.index` (casca) e `palestrantes.show` com **`{slug}`** (string) → `PalestranteController@show` faz `Palestrante::query()->ativo()->where('slug',$slug)->firstOrFail()`. **Manter `{slug}` + `firstOrFail()->ativo()`** (binding implícito `{palestrante:slug}` puparia o scope `ativo` → 404 para inativo em vez de "não encontrado", e não aplica o scope).
- **Molde `Palestras\Lista`** (archive): `#[Url(as:'ordenar', except:'…')] $ordenar`; `updated(string $name)` agrupando `resetPage()`; `limparFiltros()` via `$this->reset([...])`; `filtrosAtivos(): array`; paginação `->onEachSide(1)->links()` (Tailwind default, **sem** view custom).
- **Reusos DRY confirmados:**
  - Os **8 gradientes `cema-grad-0..7`** ([resources/css/palestras-archive.css](../../../resources/css/palestras-archive.css)) são idênticos aos do handoff §8 (só o #7 diverge, irrelevante) → **reuso** para o avatar do palestrante; sem CSS de gradiente novo.
  - `<x-ui.particulas>`, `<x-layout.app title description>` + `<x-slot:head>`, padrão de hero/breadcrumb/"Veja também" da archive/calendário.

**Dados (dev):** 57 palestrantes importados (nem todos ativos; nem todos com foto — fotos são fatia futura). O contador por palestrante e os totais da sidebar saem de query real.

---

## 3. Decisões

1. **Sem área** (cliente): remover chips/dots/"Explorar por área"/linha de área do card; nenhuma coluna/enum/campo Filament de área.
2. **Card = avatar + badge + nome + botão** (sem bio, sem área). Avatar 188px: **foto (`foto_url`, `object-cover`)** quando houver; senão **gradiente `cema-grad-{id%8}` + iniciais** grandes. Badge (microfone + `palestras_ministradas_count`) no canto superior direito. Card inteiro é `<a>` → `palestrantes.show`.
3. **`iniciais`** = accessor no model: 2 primeiras palavras do nome → 1ª letra de cada, maiúsculas (ex.: "Kátia Malaquias" → "KM"; nome de 1 palavra → 1 letra; vazio → "?"). Fallback quando não há foto.
4. **Ordenação** `#[Url(as:'ordenar', except:'az')] $ordenar='az'`, valores **`az | za | mais | menos`**: `az`→`orderBy('nome')`; `za`→`orderBy('nome','desc')`; `mais`→`orderByDesc('palestras_ministradas_count')`; `menos`→`orderBy('palestras_ministradas_count')`. Desempate secundário sempre `orderBy('nome')`.
5. **Estado** espelha a archive: busca `#[Url(as:'q', except:'')] $q` (`wire:model.live.debounce.300ms`); `updated(string $name)` reseta página para `['q','ordenar']`; `limparFiltros()` = `reset(['q','ordenar'])` + `resetPage()`; `filtrosAtivos(): array` (só `q` nesta fase). `WithPagination`, `paginate(12)`. **Grade só** (sem toggle grid/list).
6. **Busca** = `where('nome','like','%q%')` — acento-insensibilidade vem da collation do MySQL em produção; **nos testes (SQLite) usar substring sem acento**. Sem `unaccent()`/`selectRaw` (não portável).
7. **Sidebar** (Blade estático, dados do controller): card **"Os Palestrantes"** (2 parágrafos + 2 stats reais: **N Colaboradores** = `Palestrante::ativo()->count()`; **N Palestras no acervo** = `Palestra::publicado()->count()`) + card **"Em destaque"** (próxima palestra pública). **Sem** "Explorar por área".
8. **"Em destaque" sem fallback:** reusa o padrão `$proxima` do Calendário (`publicado()` + `data_da_palestra >= now()` + `orderBy` + `first()`); **some quando não há futura** (nada de "em breve"/placeholder — guardrail big-bang).
9. **Paginação** = `->onEachSide(1)->links()` (Tailwind default), igual à archive; sem view custom.
10. **Rota inalterada**: `{slug}` + `firstOrFail()->ativo()`; cards só linkam.
11. **SEO**: `<title>`/description via `x-layout.app`; JSON-LD `BreadcrumbList` (Início › Palestras › Palestrantes) no `<x-slot:head>`.

---

## 4. Arquitetura e arquivos

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `app/Models/Palestrante.php` | Modificar | Accessor `iniciais`. |
| `app/Livewire/Palestrantes/Lista.php` | Modificar | + `ordenar`, `updated()`, `limparFiltros()`, `filtrosAtivos()`, ordenação no `render()`. |
| `app/Http/Controllers/PalestranteController.php` | Modificar | `index()` passa `totalColaboradores`, `totalAcervo`, `proxima` à casca. |
| `resources/views/components/palestrante/card.blade.php` | Reescrever | Card novo (avatar/iniciais + badge + nome + botão). |
| `resources/views/livewire/palestrantes/lista.blade.php` | Reescrever | Toolbar (busca + ordenar) + linha de resultados + grade + estado vazio + paginação. |
| `resources/views/palestrantes/index.blade.php` | Reescrever | Casca: hero + breadcrumb + `<livewire:palestrantes.lista />` + sidebar + JSON-LD. |
| `resources/css/palestrantes.css` | Criar | `.cema-spk-card` (hover), avatar (`background: var(--grad)`), animação de entrada, badge. |
| `resources/css/app.css` | Modificar | `@import './palestrantes.css';`. |
| `tests/Feature/Front/…` (novos/ajustes) | Criar | Model (iniciais), componente (ordenação/estado), card, casca/sidebar/SEO. |

---

## 5. Rotas (inalteradas)

```php
Route::get('/palestrantes', [PalestranteController::class, 'index'])->name('palestrantes.index');
Route::get('/palestrantes/{slug}', [PalestranteController::class, 'show'])->name('palestrantes.show');
```
`show` já existe (perfil); nada muda aqui.

---

## 6. Model — accessor `iniciais`

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

---

## 7. Componente Livewire `App\Livewire\Palestrantes\Lista`

Estende o atual. Estado:
```php
#[Url(as: 'q', except: '')]        public string $q = '';          // já existe
#[Url(as: 'ordenar', except: 'az')] public string $ordenar = 'az'; // az | za | mais | menos
```

Ciclo/ações:
- `updated(string $name): void` — se `$name ∈ ['q','ordenar']` → `resetPage()`. (Substitui o `updatedQ()` atual.)
- `limparFiltros(): void` — `$this->reset(['q','ordenar']); $this->resetPage();`.
- `filtrosAtivos(): array` — retorna `[['chave'=>'q','rotulo'=>'Nome: "…"']]` quando `q !== ''`, senão `[]` (gate do "Limpar filtros"; extensível quando a área voltar).

`render()`:
```php
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
```
> `orderBy('palestras_ministradas_count')` ordena pela coluna do `withCount` (subquery COUNT — portável, sem `selectRaw`). O `match` com `default` cobre `az` e valores inválidos vindos da URL. Sem fetch externo.

---

## 8. Controller — `PalestranteController@index`

```php
public function index(): View
{
    $proxima = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
        ->where('data_da_palestra', '>=', now())
        ->with(['palestrantesAtivos', 'assuntos'])
        ->orderBy('data_da_palestra')->first(); // sem fallback (pode ser null)

    return view('palestrantes.index', [
        'totalColaboradores' => Palestrante::ativo()->count(),
        'totalAcervo' => Palestra::publicado()->count(),
        'proxima' => $proxima,
    ]);
}
```
(`use App\Models\Palestra;` adicionado.)

---

## 9. Views

### 9.1 Casca `resources/views/palestrantes/index.blade.php`
`<x-layout.app title="Palestrantes" description="Conheça os palestrantes do CEMA — colaboradores que partilham as reflexões do Evangelho à luz da Doutrina Espírita.">`
- `<x-slot:head>`: JSON-LD `BreadcrumbList` (via `@php $var` + `@json($var, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)` — lição da archive; nunca `@json` inline multi-vírgula).
- **Hero** roxo (`from-primary to-footer-bg`) + `<x-ui.particulas>` + brilho radial: kicker mono `text-[#9db8e0]` "PALESTRAS PÚBLICAS · CEMA"; H1 "Palestrantes"; régua `h-1 w-16 bg-gold`; subtítulo `font-light text-white/85`; à direita **card-atalho "Calendário de Palestras"** (`bg-white/10 border-white/22 rounded-2xl`, ícone dourado) → `route('palestras.calendario')`.
- **Breadcrumb** `Início › Palestras › Palestrantes` (`bg-surface`, `aria-current="page"`).
- `<section class="bg-surface"><div class="… flex flex-wrap gap-8 …"><div class="min-w-[300px] flex-1"><livewire:palestrantes.lista /></div><aside class="w-full desktop-sm:w-[340px]">…sidebar…</aside></div></section>`.
- **Sidebar** (`<aside>`, empilha < `desktop-sm`):
  - Card **"Os Palestrantes"**: 2 parágrafos (copy §6 do handoff) + 2 stats: `{{ $totalColaboradores }} Colaboradores` (caixa creme) e `{{ $totalAcervo }} Palestras no acervo` (caixa azul-clara).
  - Card **"Em destaque"** (`@if ($proxima)`): gradiente roxo; avatar (iniciais/foto do 1º palestrante ativo) + nome + `data_da_palestra->translatedFormat('d \d\e M \d\e Y')` · `format('H\hi')` + título + botão dourado "Ver palestra" → `route('palestras.show', $proxima->slug)`. **Some** se `$proxima` for null.

### 9.2 Livewire `resources/views/livewire/palestrantes/lista.blade.php`
- **Toolbar** (card branco `rounded-2xl border shadow-card p-[18px]`): **busca** pílula (`bg-surface border rounded-pill`) com ícone lupa + input `wire:model.live.debounce.300ms="q"` + "×" (só com texto, `wire:click="$set('q','')"`); **Ordenar** label + `<select wire:model.live="ordenar">` (Nome A–Z / Nome Z–A / Mais palestras / Menos palestras).
- **Linha de resultados**: esquerda `{{ $palestrantes->total() }} palestrante(s)`; direita **"Limpar filtros"** (`wire:click="limparFiltros"`) só quando `! empty($filtrosAtivos)`.
- **Grade** (`@if (! $palestrantes->isEmpty())`): `grid grid-cols-[repeat(auto-fill,minmax(212px,1fr))] gap-[18px]`; `@foreach` com **`wire:key="palestrante-{{ $palestrante->id }}"`** → `<x-palestrante.card :palestrante="$palestrante" />`.
- **Estado vazio** (`@else`): card tracejado, ícone lupa em círculo creme, "Nenhum palestrante encontrado", texto de ajuda, botão "Limpar filtros".
- **Paginação**: `<div class="mt-10">{{ $palestrantes->onEachSide(1)->links() }}</div>`.

### 9.3 Card `resources/views/components/palestrante/card.blade.php`
`@props(['palestrante'])`; card inteiro é `<a href="{{ route('palestrantes.show', $palestrante->slug) }}" class="cema-spk-card group …">`:
- **Topo (188px)** `cema-spk-avatar cema-grad-{{ $palestrante->id % 8 }}` (o gradiente vira `--grad`): `@if ($palestrante->foto_url)` `<img src="{{ $palestrante->foto_url }}" alt="" object-cover>` `@else` iniciais `<span class="font-display text-[54px] text-white/90">{{ $palestrante->iniciais }}</span>` `@endif`. **Badge** canto sup. dir.: `<span class="… bg-black/28 backdrop-blur text-white rounded-pill">` ícone microfone + `{{ $palestrante->palestras_ministradas_count ?? 0 }}`.
- **Corpo**: nome `font-display font-semibold text-text-ink`; botão pílula "Ver palestras ›" (`bg-cream text-primary`, vira `bg-primary text-white` no hover do card — via `.cema-spk-card:hover .cema-spk-cta`).
- Sem linha de área, sem bio. `alt=""` (nome já está no corpo; avatar decorativo).

---

## 10. CSS `resources/css/palestrantes.css`

`@import` no `app.css` (após `palestras-calendario.css`). Via `var(--color-*)`/`var(--grad)`, nunca `theme()`:
- `.cema-spk-card { transition: transform .2s, box-shadow .2s, border-color .2s; }` + `:hover { transform: translateY(-5px); box-shadow: <elevated forte>; border-color: #E2D9C2; }` + `:hover .cema-spk-cta { background: var(--color-primary); color:#fff; }` + `:hover .cema-spk-avatar img { transform: scale(1.04); }`.
- `.cema-spk-avatar { background: var(--grad, linear-gradient(140deg, var(--color-primary), var(--color-footer-bg))); }` (consome `cema-grad-{n}`).
- **Animação de entrada** `@keyframes cemaSpkFade` (fade-up `.4s both`) na `.cema-spk-card` + `@media (prefers-reduced-motion: reduce)` desativando `transition`/`animation`/`transform`.
- Badge: literais `rgba(0,0,0,.28)` + blur (fora do sistema, ok).

---

## 11. SEO / A11y / Performance
- **JSON-LD** `BreadcrumbList` no `<x-slot:head>` (via var `@php` + `@json(...OPTIONS)`).
- **A11y:** busca com `<label sr-only>`/`aria-label`; `<select>` com label "Ordenar"; card é `<a>` navegável por teclado, foco visível; badge/ícones `aria-hidden`; contraste ≥ 4.5; `prefers-reduced-motion` desativa a animação de entrada e o hover-transform.
- **Performance:** sem fetch externo; grade leve (SSR Livewire); foto `loading="lazy"` `width/height`; `foto_url` (WebP) quando houver; iniciais quando não; sem libs novas; `npm run build`.

---

## 12. Plano de testes

**Suíte COMPLETA no fecho** (`docker compose exec -T app php artisan test`). **Pint** antes de cada commit. `docker compose restart app worker` após editar Blade/PHP.

- **`PalestranteIniciaisTest`** (model): "Kátia Malaquias"→"KM"; nome de 1 palavra→1 letra; espaços múltiplos→2 letras; nome vazio→"?".
- **`PalestrantesListaTest`** (Livewire, via `viewData('palestrantes')`):
  - default lista só **ativos**, ordem A–Z, `paginate(12)` (13 ativos → 12 na 1ª página).
  - busca `q` (substring **sem acento**) filtra por nome e reseta página.
  - `ordenar=za` inverte; `ordenar=mais`/`menos` ordenam por `palestras_ministradas_count` (criar palestrantes com contagens distintas via pivot `papel=palestrante` + palestra `publicado`); desempate por nome.
  - `palestras_ministradas_count` **ignora** papel diretor e palestra rascunho.
  - `limparFiltros()` zera `q`+`ordenar`+página; `filtrosAtivos()` traz `q` quando setado.
- **`PalestranteCardTest`** (Blade `$this->blade`): sem foto → renderiza `iniciais` + `cema-grad-{id%8}` (sem `<img>`); com contagem → badge com o número; link para `palestrantes.show`.
- **`PalestrantesIndexTest`** (feature): `GET /palestrantes` → 200 + "Palestrantes" (hero) + nome de um palestrante ativo listado; sidebar mostra `totalColaboradores`/`totalAcervo` reais; **"Em destaque" aparece com próxima futura e some sem futura** (sem fallback); JSON-LD `"@type":"BreadcrumbList"` presente.
- Não regredir os testes existentes de palestrantes (perfil/`show`, Plano 6).

---

## 13. Riscos & mitigações

| Risco | Mitigação |
|---|---|
| Recriar a área fictícia do handoff | Escopo explicitamente SEM área; nenhuma coluna/enum/campo; card sem linha de área. |
| `withCount('palestras')` contar diretor/rascunho | Reusar o alias `palestras_ministradas_count` (papel=palestrante + publicado). |
| `foto_path` inexistente | Usar `foto_url`/`foto_thumb_url` (Spatie); fallback de iniciais carrega a página sozinho. |
| Busca acento-sensível no SQLite | `like` simples; testes usam substring sem acento; acento-insensibilidade da collation MySQL em prod. |
| `orderBy` em coluna de `withCount` | `palestras_ministradas_count` é subquery COUNT (portável); sem `selectRaw`/`YEAR()`. |
| Binding implícito puxar o scope | Manter `{slug}` + `firstOrFail()->ativo()` (não `{palestrante:slug}`). |
| Morphdom reaproveitar card errado | `wire:key="palestrante-{id}"` em todo card do `@foreach`. |
| "Em destaque"/stats como placeholder | Query real; destaque some sem próxima (big-bang). |
| OPcache servindo Blade antigo no dev | `docker compose restart app worker`. |
| `theme()` no Tailwind v4 | `var(--color-*)`/`var(--grad)` + `npm run build`. |

---

## 14. Constraints globais (herdadas pelo plano)

- **Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Filament 5 · Tailwind v4 · MySQL 8 (dev) / SQLite `:memory:` (testes). **Sem dependências novas. Sem migração/schema.**
- **Escopo SEM área:** nenhuma coluna/enum/campo Filament de área; sem chips/dots/"Explorar por área"/linha de área no card.
- Reusar: `ativo()`, alias `palestras_ministradas_count`, `foto_url`/`foto_thumb_url`, `cema-grad-{n}`, `<x-ui.particulas>`, `<x-layout.app>`/`<x-slot:head>`, paginação `->onEachSide(1)->links()`.
- Estado espelha a archive: `#[Url(as:'q'/'ordenar')]`, `updated(string $name)`, `limparFiltros()`/`filtrosAtivos()`, `WithPagination` `paginate(12)`, **grade só**.
- **Big-bang:** stats/destaque de query real; destaque **sem fallback**; nada de "em breve"/placeholder.
- **Portabilidade SQLite:** `where … like`, `whereYear/whereMonth`, distinct/agrupamento em PHP; **nada** de `selectRaw`/`YEAR()`/`DATE_FORMAT()`.
- **`wire:key`** estável em todo `@foreach`/`@forelse`.
- Tokens via utilitários/`var(--color-*)` (nunca `theme()`); build `npm run build`; `restart app worker` no dev.
- A11y (label/aria, teclado, foco, contraste) + `prefers-reduced-motion`; mobile-first.
- Testes por `docker compose exec -T app php artisan test` (**suíte completa no fecho**, CI já roda tudo); **Pint** antes do commit. PROIBIDO `migrate:fresh/refresh/wipe/reset`/seed destrutivo.
- pt-BR com acentos; cabeçalho de autoria (`Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01`) nos arquivos **PHP** novos relevantes (componentes Blade seguem a convenção do projeto: sem header). Commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
