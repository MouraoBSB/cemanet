# Tema CEMA para o painel Filament (/admin) — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar ao painel `/admin` a identidade visual do CEMA (fontes, cores, logos, superfícies e acentos) como camada puramente visual, sem tocar em funcionalidade.

**Architecture:** Tema Filament dedicado (`resources/css/filament/admin/theme.css`, gerado à mão a partir do stub oficial), registrado por `->viteTheme(...)` e por uma entrada no `vite.config.js`. As fontes entram no bundle do tema via `@import` do fontsource (self-hosted, sem CDN). Cores semânticas e marca são configuradas no `AdminPanelProvider`. Dark mode desligado.

**Tech Stack:** Laravel 13 · Filament 5.6 · Tailwind CSS v4 · Vite · fontsource (`@fontsource/poppins`, `@fontsource/work-sans`) · Docker (container `cema-app`).

## Global Constraints

- **Camada visual apenas.** Não alterar resources, forms, tables, actions, policies, widgets ou rotas. Nenhum teste funcional existente do painel muda de comportamento.
- **Não quebrar** `resources/css/filament/editor.css` (escopo `.editor-conteudo-blog`, registrado via `FilamentAsset` no `boot()` do provider) — não editar esse arquivo nem seu registro.
- **Fontes self-hosted, sem CDN em runtime.** Poppins/Work Sans entram no bundle do tema via fontsource. O helper `->font()` do Filament (CDN) **não** é usado.
- **Reusar tokens CEMA.** Hexes de marca: primary `#4E4483`, info/secondary `#6E9FCB`, warning/gold `#F2A81E`, danger `#C33A36`, success `#008000`. Único não-token: `Color::Neutral` (andaime da escala neutra).
- **Pílula só em botões e badges** (`.fi-btn`, `.fi-badge`). Inputs e cards mantêm o raio padrão.
- **Dark mode desligado** (`->darkMode(false)`).
- **npm/Vite rodam no HOST** (o container `cema-app` não tem Node). **artisan/pint/testes rodam no container** `cema-app`.
- **Ordem local build→testes:** `->viteTheme()` faz o painel emitir `@vite([theme])`; a partir de então, testes que renderizam página cheia do `/admin` exigem o tema no manifest. **Rodar `npm run build` (host) ANTES da suíte Filament** localmente. (A CI já builda antes dos testes.)
- **Pint limpo antes do push** (o CI roda `pint --test` antes dos testes).
- 🚫 **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset` e qualquer seed/factory destrutivo no banco de dev.

---

## Estrutura de arquivos

| Arquivo | Responsabilidade | Ação |
|---|---|---|
| `public/images/logos/logo-horizontal.png` · `logo-icone.png` | Marca do painel (brand logo + favicon) | Sincronizar (idempotente) |
| `resources/css/filament/admin/theme.css` | Bundle do tema: fontes + acentos CEMA | Criar |
| `vite.config.js` | Registrar a entrada do tema no `input` | Modificar |
| `app/Providers/Filament/AdminPanelProvider.php` | Cores semânticas, dark mode off, brand/favicon, `viteTheme` | Modificar |
| `package.json` / `package-lock.json` | `devDependencies` do fontsource | Modificar (via `npm i -D`) |
| `tests/Feature/Filament/TemaAdminTest.php` | Assertivas de config do painel (sem manifest) | Criar |

**Decisão registrada (a vetar na revisão):** `->collapsedBrandLogo(...)` do brief **não existe** no Filament v5.6 (o concern `HasBrandLogo` só tem `brandLogo`/`brandLogoHeight`/`getDarkModeBrandLogo`) e a sidebar deste painel **não é colapsável** (não há `sidebarCollapsibleOnDesktop`). Portanto o logo-ícone recolhido é **omitido**. Se no futuro habilitar sidebar colapsável, o logo-ícone recolhido vira incremento (via render hook). O `logo-icone.png` ainda é usado como **favicon**.

**Nota de acessibilidade (a vetar na revisão):** o brief cita o dourado no "anel de foco". Dourado `#F2A81E` sobre fundo claro tem contraste ~1.9:1 — abaixo do mínimo WCAG 3:1 para indicador de foco. Por isso o **anel de foco permanece roxo (`primary`)** por contraste; o dourado entra como acento no item de menu ativo e como cor semântica `warning`. (CLAUDE.md item 21 — A11y.)

---

## Task 1: Sincronizar os logos da marca

Copia idempotente dos logos da fonte canônica do handoff para `public/`. Os arquivos já existem em `public/images/logos/`; este passo garante que batem com o handoff.

**Files:**
- Sync: `design_handoff_cemanet/prototype/assets/logo-horizontal.png` → `public/images/logos/logo-horizontal.png`
- Sync: `design_handoff_cemanet/prototype/assets/logo-icone.png` → `public/images/logos/logo-icone.png`

- [ ] **Step 1: Copiar os dois logos (idempotente)**

No host (PowerShell ou bash), a partir da raiz do projeto:

```bash
cp design_handoff_cemanet/prototype/assets/logo-horizontal.png public/images/logos/logo-horizontal.png
cp design_handoff_cemanet/prototype/assets/logo-icone.png public/images/logos/logo-icone.png
```

- [ ] **Step 2: Conferir que os arquivos existem e não estão vazios**

```bash
ls -l public/images/logos/logo-horizontal.png public/images/logos/logo-icone.png
```
Esperado: dois arquivos `.png` com tamanho > 0.

- [ ] **Step 3: Commit**

```bash
git add public/images/logos/logo-horizontal.png public/images/logos/logo-icone.png
git commit -m "feat(admin/tema): sincroniza logos da marca com o handoff canonico"
```

> Se `git add` reportar "nothing to commit" (arquivos idênticos aos versionados), o passo está satisfeito — seguir sem commit vazio.

---

## Task 2: Scaffold do tema + entrada no Vite

Cria o `theme.css` com o conteúdo do stub oficial do Filament (equivalente ao que `make:filament-theme admin` geraria — o comando não é usado porque roda `npm -v`/`npm run build` e aborta no container sem Node). Registra a entrada no `vite.config.js` e confirma que o bundle compila.

**Files:**
- Create: `resources/css/filament/admin/theme.css`
- Modify: `vite.config.js` (array `input`)

**Interfaces:**
- Produces: o entry Vite `resources/css/filament/admin/theme.css` (consumido pelo `->viteTheme(...)` na Task 3).

- [ ] **Step 1: Criar `resources/css/filament/admin/theme.css`**

Conteúdo (idêntico ao stub `ThemeCss.stub` resolvido para o painel `admin`):

```css
@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/**/*';
@source '../../../../resources/views/filament/**/*';
```

- [ ] **Step 2: Registrar a entrada no `vite.config.js`**

Adicionar `'resources/css/filament/admin/theme.css'` ao array `input` do plugin `laravel(...)`. O `input` fica:

```js
input: [
    'resources/css/app.css',
    'resources/js/app.js',
    'resources/js/cropper-perfil.js',
    'resources/css/filament/admin/theme.css',
],
```

- [ ] **Step 3: Buildar no host e confirmar que o tema entra no manifest**

No **host** (o container não tem Node):

```bash
npm run build
```
Esperado: build conclui sem erro; o manifest passa a listar a entrada do tema.

```bash
node -e "const m=require('./public/build/manifest.json'); console.log(!!m['resources/css/filament/admin/theme.css'])"
```
Esperado: `true`.

- [ ] **Step 4: Commit**

```bash
git add resources/css/filament/admin/theme.css vite.config.js
git commit -m "feat(admin/tema): scaffold do tema Filament + entrada no Vite"
```

> `public/build/` é **gitignored** (não versionado). O build (`npm run build`, host) é um **gate local antes dos testes de página cheia** — o manifest precisa existir na máquina para o `@vite([theme])` resolver. A CI builda antes dos testes; localmente, buildar antes de rodar a suíte. Nenhum commit inclui `public/build/`.

---

## Task 3: Provider — cores, dark mode, marca e viteTheme

Configura o `AdminPanelProvider`: mapa de cores semânticas CEMA, dark mode desligado, brand logo + favicon e o registro do tema. Adiciona um teste de configuração que **não** depende do manifest do Vite (inspeciona o painel, não renderiza página).

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `tests/Feature/Filament/TemaAdminTest.php`

**Interfaces:**
- Consumes: entry Vite `resources/css/filament/admin/theme.css` (Task 2).
- Consumes: `public/images/logos/logo-horizontal.png`, `logo-icone.png` (Task 1).

- [ ] **Step 1: Escrever o teste de configuração (falhando)**

Criar `tests/Feature/Filament/TemaAdminTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

class TemaAdminTest extends TestCase
{
    private function painel()
    {
        return Filament::getPanel('admin');
    }

    public function test_dark_mode_desligado(): void
    {
        $this->assertFalse($this->painel()->hasDarkMode());
    }

    public function test_marca_usa_os_logos_cema(): void
    {
        $painel = $this->painel();

        $this->assertStringContainsString('logo-horizontal', (string) $painel->getBrandLogo());
        $this->assertSame('2rem', $painel->getBrandLogoHeight());
        $this->assertStringContainsString('logo-icone', (string) $painel->getFavicon());
    }

    public function test_cores_semanticas_cema_registradas(): void
    {
        $chaves = array_keys($this->painel()->getColors());

        foreach (['primary', 'info', 'warning', 'danger', 'success', 'gray'] as $papel) {
            $this->assertContains($papel, $chaves);
        }
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar**

No container `cema-app`:
```bash
docker compose exec app php artisan test --filter=TemaAdminTest
```
Esperado: FALHA (`hasDarkMode()` ainda true; favicon/height ainda não configurados; falta `info`/`warning` nas cores).

- [ ] **Step 3: Editar o `AdminPanelProvider`**

No método `panel()`, substituir o bloco `->colors([...])` atual e inserir as chamadas de tema/marca/dark mode. O topo do encadeamento fica assim (o `Color` já está importado; `->login()` permanece):

```php
return $panel
    ->default()
    ->id('admin')
    ->path('admin')
    ->authenticatedRoutes(fn () => Route::post(
        '/midia/colar',
        [MidiaController::class, 'colar'],
    )->name('midia.colar'))
    ->login()
    ->viteTheme('resources/css/filament/admin/theme.css')
    ->darkMode(false)
    ->brandLogo(asset('images/logos/logo-horizontal.png'))
    ->brandLogoHeight('2rem')
    ->favicon(asset('images/logos/logo-icone.png'))
    ->colors([
        'primary' => Color::hex('#4E4483'),
        'info' => Color::hex('#6E9FCB'),
        'warning' => Color::hex('#F2A81E'),
        'danger' => Color::hex('#C33A36'),
        'success' => Color::hex('#008000'),
        'gray' => Color::Neutral,
    ])
    ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
    // ... resto do encadeamento inalterado ...
```

Não alterar mais nada no arquivo (o `boot()` com o `editor.css` fica intocado).

- [ ] **Step 4: Buildar no host (o viteTheme passa a exigir o tema no manifest)**

No **host**:
```bash
npm run build
```
Esperado: build OK (o manifest já tem a entrada do tema desde a Task 2; segue presente).

- [ ] **Step 5: Rodar o teste de config + um teste de página cheia existente**

No container `cema-app`:
```bash
docker compose exec app php artisan test --filter=TemaAdminTest
docker compose exec app php artisan test --filter=AssuntoResourceTest
```
Esperado: ambos PASSAM. (`TemaAdminTest` prova a config; `AssuntoResourceTest` prova que o painel renderiza com o `viteTheme` e o manifest resolve.)

- [ ] **Step 6: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php tests/Feature/Filament/TemaAdminTest.php
git commit -m "feat(admin/tema): cores CEMA, dark mode off, marca e viteTheme no provider"
```
(`public/build/` é gitignored — não incluir; o build é gate local antes dos testes.)

---

## Task 4: Customizações do theme.css (fontes + acentos)

Coloca as `@font-face` de Poppins/Work Sans no bundle via fontsource e aplica os acentos CEMA: fonte de corpo Poppins, títulos Work Sans, pílula em botões/badges, canvas creme leve e espinha dourada no item de menu ativo.

**Files:**
- Modify: `resources/css/filament/admin/theme.css`
- Modify: `package.json` / `package-lock.json` (via `npm i -D`)

- [ ] **Step 1: Instalar as fontes self-hosted (host)**

No **host**:
```bash
npm i -D @fontsource/poppins @fontsource/work-sans
```

- [ ] **Step 1.5: Confirmar os nomes reais dos arquivos de subset (host)**

O fontsource v5 expõe um CSS por subset+peso (`{subset}-{peso}.css`). Confirmar que os arquivos referenciados no Step 2 existem no pacote instalado **antes** de escrevê-los:

```bash
ls node_modules/@fontsource/poppins/latin-400.css node_modules/@fontsource/poppins/latin-ext-400.css node_modules/@fontsource/poppins/latin-500.css node_modules/@fontsource/poppins/latin-ext-500.css node_modules/@fontsource/poppins/latin-600.css node_modules/@fontsource/poppins/latin-ext-600.css
ls node_modules/@fontsource/work-sans/latin-400.css node_modules/@fontsource/work-sans/latin-ext-400.css node_modules/@fontsource/work-sans/latin-600.css node_modules/@fontsource/work-sans/latin-ext-600.css
```
Esperado: todos existem. Se algum nome divergir (ex.: peso indisponível para um subset), ajustar o `@import` correspondente no Step 2 para o arquivo real. Se um peso não existir em subset, usar o `{peso}.css` cheio só para aquele peso (não regredir todos para o cheio).

- [ ] **Step 2: Reescrever `resources/css/filament/admin/theme.css`**

Conteúdo final (fontes por subset `latin` + `latin-ext` — cobre pt-BR e nomes europeus, enxuga o build ao dropar vietnamese/devanagari do `{peso}.css` cheio):

```css
/* Tema CEMA para o painel /admin — camada visual (Filament 5.6, Tailwind v4).
   Fontes self-hosted via fontsource (sem CDN); acentos de marca sobre o tema base.
   Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 */

/* Fontes CEMA self-hosted — Poppins (corpo) e Work Sans (títulos), subsets latin + latin-ext. */
@import '@fontsource/poppins/latin-400.css';
@import '@fontsource/poppins/latin-ext-400.css';
@import '@fontsource/poppins/latin-500.css';
@import '@fontsource/poppins/latin-ext-500.css';
@import '@fontsource/poppins/latin-600.css';
@import '@fontsource/poppins/latin-ext-600.css';
@import '@fontsource/work-sans/latin-400.css';
@import '@fontsource/work-sans/latin-ext-400.css';
@import '@fontsource/work-sans/latin-600.css';
@import '@fontsource/work-sans/latin-ext-600.css';

@import '../../../../vendor/filament/filament/resources/css/theme.css';

@source '../../../../app/Filament/**/*';
@source '../../../../resources/views/filament/**/*';

:root {
    /* Filament deriva --font-sans de var(--font-family): define o corpo como Poppins. */
    --font-family: 'Poppins';
    /* Acentos de marca. */
    --cema-gold: #f2a81e;
    --cema-cream-bg: #faf7f0;
}

/* Superfície quente: canvas do painel em creme bem leve (sidebar/cards seguem neutros). */
.fi-body {
    background-color: var(--cema-cream-bg);
}

/* Títulos em Work Sans (cabeçalho de página e páginas simples/login). */
.fi-header-heading,
.fi-simple-header-heading {
    font-family: 'Work Sans', ui-sans-serif, system-ui, sans-serif;
}

/* Pílula só em botões e badges (inputs e cards mantêm o raio padrão). */
.fi-btn,
.fi-badge {
    border-radius: 9999px;
}

/* Acento dourado: espinha à esquerda no item de menu ativo da sidebar. */
.fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
    box-shadow: inset 3px 0 0 0 var(--cema-gold);
}
```

- [ ] **Step 3: Buildar no host e conferir que as fontes entraram no bundle**

No **host**:
```bash
npm run build
```
Esperado: build OK. As `.woff2` de Poppins/Work Sans e as `@font-face` ficam no CSS do tema (bundle do `theme.css`), não numa CDN.

Conferência rápida (o CSS compilado do tema referencia as fontes):
```bash
node -e "const m=require('./public/build/manifest.json'); const f=m['resources/css/filament/admin/theme.css'].file; const css=require('fs').readFileSync('public/build/'+f,'utf8'); console.log('poppins:', css.includes('Poppins')||css.toLowerCase().includes('poppins')); console.log('work sans:', css.toLowerCase().includes('work sans')||css.toLowerCase().includes('work-sans'));"
```
Esperado: `poppins: true` e `work sans: true`.

- [ ] **Step 4: Commit**

```bash
git add resources/css/filament/admin/theme.css package.json package-lock.json
git commit -m "feat(admin/tema): fontes fontsource + acentos CEMA no theme.css"
```
(`public/build/` é gitignored — não incluir.)

---

## Task 5: Verificação final

Garante a definição de pronto: build limpo, suíte Filament inteira verde e Pint limpo.

**Files:** nenhum (verificação).

- [ ] **Step 1: Build no host**

```bash
npm run build
```
Esperado: sucesso.

- [ ] **Step 2: Suíte Filament inteira no container**

```bash
docker compose exec app php artisan test --testsuite=Feature
```
Esperado: toda a suíte verde (inclui `TemaAdminTest`, os `*ResourceTest`, `UsuarioResourceTest`). Se algum teste de importação de blog (GD) falhar de forma intermitente sob carga, reexecutar isolado — é flaky conhecido, não regressão.

- [ ] **Step 3: Pint limpo no container**

```bash
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/pint --test
```
Esperado: `--test` sem drift (sem arquivos a corrigir).

- [ ] **Step 4: Commit de eventuais ajustes do Pint (se houver)**

```bash
git add -A
git commit -m "style(admin/tema): pint"
```

- [ ] **Step 5: Verificação visual manual (dona/dono do projeto — fora do escopo automatizado)**

No dev (após `restart app worker` para o provider PHP recarregar — OPcache `validate_timestamps=0`): abrir `/admin/login` e `/admin`, conferir logo horizontal, favicon (aba), fontes Poppins/Work Sans, ausência do alternador claro/escuro, botões/badges em pílula, espinha dourada no item de menu ativo, canvas creme leve, e responsividade em mobile. Conferir também um form de resource (inputs/cards **não** em pílula) e que o editor do blog (`.editor-conteudo-blog`) segue igual.

---

## Self-Review (feito na escrita do plano)

**Cobertura da spec:**
- §1 Logos/favicon/marca → Task 1 (sync) + Task 3 (brandLogo/height/favicon). ⚠️ `collapsedBrandLogo` omitido (API inexistente no v5.6 + sidebar não-colapsável) — documentado acima.
- §2 Fontes self-hosted → Task 4 (fontsource + `--font-family`).
- §3 Cores semânticas (warning=gold, gray=Neutral) → Task 3 (`->colors`).
- §4 Tema CSS (superfície creme, pílula botões/badges, acento dourado) → Task 4.
- §5 Dark mode off + login herda marca → Task 3 (`darkMode(false)`, brand); login coberto na verificação visual.
- §Verificação → Task 5 (build host, suíte, Pint) + verificação visual manual.

**Placeholder scan:** sem TBD/TODO; todo passo de código traz o código; comandos com saída esperada.

**Consistência de tipos/nomes:** `resources/css/filament/admin/theme.css` idêntico em Task 2/3/4; `Color::hex`/`Color::Neutral` confirmados no v5.6; getters (`hasDarkMode`, `getBrandLogo`, `getBrandLogoHeight`, `getFavicon`, `getColors`) confirmados; classes CSS (`.fi-body`, `.fi-btn`, `.fi-badge`, `.fi-header-heading`, `.fi-simple-header-heading`, `.fi-sidebar-item.fi-active > .fi-sidebar-item-btn`) e a variável `--font-family` confirmadas no vendor.

**Desvios do brief documentados (a vetar na revisão):** logo recolhido omitido; anel de foco permanece roxo por A11y.
