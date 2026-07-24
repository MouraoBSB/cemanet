# Molde Filament-no-site — Paleta raw ausente Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> ## ⚠️ ATUALIZAÇÃO DE EXECUÇÃO (23/jul) — o VEÍCULO mudou; leia antes das tasks
> Durante a execução, o build **refutou** o veículo da Task 1 (paleta **no `theme.css`**): o transform do **Tailwind
> v4 poda** as variáveis raw (`--gray-*`/`--primary-*`) declaradas à mão em CSS que ele processa — medido 0 no bundle
> em `:root`, `html:root`, `@theme static` e `@layer base`, **minificado E não** (não é o minificador). O dono aprovou
> o **pivot de veículo** (2º passe; sem CSP no projeto → inline seguro). **O que foi REALMENTE construído** (P1 cores
> CEMA + P2 paleta completa **intactos**; só o veículo muda — ver **SPEC §5.0 e §11-bis**):
> 1. **Componente Blade** [conta/filament-head](resources/views/components/conta/filament-head.blade.php) que emite
>    `@vite(theme.css)` **+** um `<style>` com os 66 literais oklch CEMA (paleta inline no `<head>`, imune ao build —
>    como o `@filamentStyles` do `/admin`).
> 2. As **3 blades** Fase E (agenda/mensagens/curadoria) passam a usar `<x-conta.filament-head />` no `headTop`.
> 3. **`theme.css` LIMPO** (removido o `:root` morto; mantém o `@import` dos `fi-*`).
> 4. **Guarda vira teste HTTP** [MoldeSitePaletaTest](tests/Feature/Conta/MoldeSitePaletaTest.php) (substitui o de
>    fonte): GET autenticado a `mensagens` **e** `curadoria`, assere o `<style>` com `--gray-200:` e `--primary-600:`
>    de matiz CEMA (~288). Cutover: `optimize:clear` + `restart app worker` (Blade mudou; build do CSS opcional).
> As tasks abaixo descrevem o **plano original (theme.css)** e ficam como registro; siga a SPEC §5.0/§11-bis para o
> veículo real.

**Goal:** Fazer a paleta raw do Filament (`--gray-*`/`--primary-*`/semânticas) existir no runtime das 3 páginas Fase E (agenda, mensagens, curadoria), para o trilho do `fi-toggle` voltar a ter cor e o médium conseguir enviar — sem tocar em nada global.

**Architecture:** Fatia **100% CSS/paleta**. Uma única mudança de fonte: adicionar um `:root{}` com a paleta raw ao tema **escopado** [resources/css/filament/site/theme.css](resources/css/filament/site/theme.css) (bundle carregado só pelo slot `headTop` das 3 páginas). Os literais são `oklch(…)` **capturados do `:root` que o `/admin` servido emite** — as cores **CEMA**, não a paleta default do Filament. Uma guarda de fonte (teste PHPUnit CI-safe) protege contra remoção acidental; a prova de renderização é o gate de navegador do §4 da SPEC.

**Tech Stack:** Tailwind v4 · Filament 5.6 · Vite (build no HOST) · PHPUnit · Docker (artisan/pint no container `app`).

**SPEC:** [docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md](docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md) — diagnóstico (§2), gate de aceite (§4), decisões ratificadas (§11). O diagnóstico está fechado; **não reabrir**.

## Global Constraints

- **Idioma:** tudo em **pt-BR** (comentários, mensagens, commits). Cabeçalho de autoria em arquivo novo: `Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23`.
- **Escopo travado (P3):** **não** tocar `MensagemForm` (nada de `->default(false)`), `MensagensConta`, `SincronizadorDestinatarios`, `RegraPublicacao`, máquina de estados. **Não** tocar `app.css`, o preflight, nem o tema do `/admin`. Só `theme.css` do site + 1 teste.
- **Paleta (P1+P2):** paleta **completa** — `gray + primary + danger + info + warning + success` (6 famílias × 11 tons). Literais **CEMA** capturados do painel servido (matiz do primary ~288 = roxo). **Nunca** a paleta default (primary âmbar, matiz ~58).
- **Banco:** nenhuma migration/seeder. Esta fatia não toca o banco. 🚫 Proibido `migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo.
- **Build no HOST:** `npm run build` roda no host (o container `cema-app` não tem Node — [[npm-vite-no-host]]). `artisan`/`pint` rodam no container.
- **Cutover no dev:** `optimize:clear` + `docker compose restart app worker`.
- **Verde antes de push:** `./vendor/bin/pint` limpo (o CI roda `pint --test` e aborta antes dos testes — [[pint-antes-de-push]]) + suíte sem regressão (baseline **1304**; os novos testes somam **4**).

---

### Task 1: Guarda de fonte + paleta raw no `theme.css` do site

**Files:**
- Create: `tests/Feature/Filament/MoldeSitePaletaTest.php`
- Modify: `resources/css/filament/site/theme.css` (append no fim; hoje 32 linhas)

**Interfaces:**
- Consumes: nada (não há dependência de tasks anteriores).
- Produces: o bloco `:root{ --gray-*; --primary-*; --danger-*; --info-*; --warning-*; --success-* }` no fonte do tema do site — insumo do build da Task 2.

- [ ] **Step 1: Escrever o teste que reprova (guarda de fonte)**

Espelha o molde de [EditorAdminAssetsTest.php](tests/Feature/Filament/EditorAdminAssetsTest.php) (`file_get_contents(resource_path(...))` + asserts). Guarda **presença** e **formato de cor completa** (não canais RGB), não os valores exatos (não-frágil).

Criar `tests/Feature/Filament/MoldeSitePaletaTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Filament;

use Tests\TestCase;

/**
 * Guarda do molde Filament-no-site: o tema escopado do site (carregado só nas 3 páginas Fase E via
 * headTop) precisa DEFINIR a paleta raw do Filament, porque essas páginas emitem @filamentScripts mas
 * NÃO @filamentStyles (que no /admin injeta o :root da paleta em runtime). Sem esta paleta, o trilho do
 * fi-toggle (bg-gray-200 -> var(--gray-200)) computa transparent e o médium não consegue enviar.
 * Ver docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md.
 */
class MoldeSitePaletaTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/filament/site/theme.css'));
    }

    public function test_toggle_off_e_on_tem_cor_completa_oklch(): void
    {
        $css = $this->css();

        // --gray-200 pinta o trilho OFF; --primary-600 pinta o ON e os CTAs. Precisam ser COR COMPLETA
        // (oklch), não canais RGB soltos — senão background-color: var(--gray-200) seria inválido.
        $this->assertMatchesRegularExpression('/--gray-200:\s*oklch\(/', $css);
        $this->assertMatchesRegularExpression('/--primary-600:\s*oklch\(/', $css);
    }

    public function test_primary_e_o_roxo_cema_nao_a_paleta_default(): void
    {
        $css = $this->css();

        // Guarda de IDENTIDADE (R2 do passe): o --primary-600 tem que ser o ROXO CEMA (matiz oklch ~288),
        // não a paleta DEFAULT do Filament (âmbar, matiz ~58) — que sairia se a paleta fosse recapturada da
        // fonte errada (tinker/renderStyles ou @filamentStyles fora do painel corrente). Âmbar é oklch válido,
        // então os asserts de formato passariam; só este de matiz pega a fonte errada. Aceita 200–299 (roxo/azul),
        // rejeita ~58 (âmbar). Ver a nota de captura na Task 1 / §5 da SPEC.
        $this->assertMatchesRegularExpression('/--primary-600:\s*oklch\([\d.]+\s+[\d.]+\s+2\d\d/', $css);
    }

    public function test_paleta_completa_das_seis_familias(): void
    {
        $css = $this->css();

        foreach (['gray', 'primary', 'danger', 'info', 'warning', 'success'] as $familia) {
            $this->assertStringContainsString("--{$familia}-500:", $css, "Falta a família --{$familia}-*.");
        }
    }

    public function test_todas_as_onze_tonalidades_do_cinza(): void
    {
        $css = $this->css();

        foreach ([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as $tom) {
            $this->assertStringContainsString("--gray-{$tom}:", $css, "Falta o tom --gray-{$tom}.");
        }
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que REPROVA**

Run: `docker compose exec -T app php artisan test --filter=MoldeSitePaletaTest`
Expected: **FAIL** — `test_toggle_off_e_on_tem_cor_completa_oklch` e os demais falham (o `theme.css` ainda não tem `:root`/`--gray-200`).

- [ ] **Step 3: Adicionar a paleta raw ao `theme.css` do site**

Acrescentar ao **fim** de [resources/css/filament/site/theme.css](resources/css/filament/site/theme.css) (depois do bloco `html:root { --font-family: 'Poppins'; }` existente) o bloco abaixo. Os valores são os literais `oklch(…)` **capturados do `/admin/login` servido** em 23/jul (cores CEMA: primary roxo matiz ~288, gray neutro puro, danger/info/warning/success derivados de `#C33A36`/`#6E9FCB`/`#F2A81E`/`#008000`).

```css

/* ===== Paleta raw do Filament (o que falta no molde do site) =====
   No /admin, o :root com --gray-*/--primary-*/semânticas é emitido em RUNTIME pelo @filamentStyles
   (renderStyles -> assets.blade.php). As 3 páginas Fase E (agenda, mensagens, curadoria) embutem os
   forms com @filamentScripts mas NÃO @filamentStyles — então essas variáveis ficariam indefinidas e o
   trilho do fi-toggle (bg-gray-200 -> var(--gray-200), via @theme inline do Filament) computaria
   `transparent`, travando o envio do médium. Literais CAPTURADOS do :root que o /admin SERVIDO emite
   (cores CEMA; a paleta default do Filament seria âmbar — por isso a captura é do painel servido, nunca
   de tinker/renderStyles fora do painel corrente). Escopado: este arquivo só carrega no headTop das 3
   páginas Fase E, não vaza para o resto do site.
   Ver docs/superpowers/specs/2026-07-23-molde-filament-no-site-paleta.md.
   Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23 */
:root {
    /* Primária — roxo institucional (#4E4483 no 600). */
    --primary-50: oklch(0.97 0.013 286.149);
    --primary-100: oklch(0.935 0.028 288.028);
    --primary-200: oklch(0.875 0.051 289.02);
    --primary-300: oklch(0.791 0.072 288.228);
    --primary-400: oklch(0.66 0.091 288.808);
    --primary-500: oklch(0.54 0.1 288.936);
    --primary-600: oklch(0.428 0.102 288.629);
    --primary-700: oklch(0.386 0.091 288.9);
    --primary-800: oklch(0.347 0.082 289.062);
    --primary-900: oklch(0.309 0.072 288.166);
    --primary-950: oklch(0.224 0.057 288.615);

    /* Cinza — Color::Neutral (neutro puro). Pinta o trilho OFF do toggle. */
    --gray-50: oklch(0.985 0 0);
    --gray-100: oklch(0.97 0 0);
    --gray-200: oklch(0.922 0 0);
    --gray-300: oklch(0.87 0 0);
    --gray-400: oklch(0.708 0 0);
    --gray-500: oklch(0.556 0 0);
    --gray-600: oklch(0.439 0 0);
    --gray-700: oklch(0.371 0 0);
    --gray-800: oklch(0.269 0 0);
    --gray-900: oklch(0.205 0 0);
    --gray-950: oklch(0.145 0 0);

    /* Danger — #C33A36. */
    --danger-50: oklch(0.97717647058824 0.01395454545455 26.365);
    --danger-100: oklch(0.95035294117647 0.03272727272727 26.365);
    --danger-200: oklch(0.90547058823529 0.06318181818182 26.365);
    --danger-300: oklch(0.84047058823529 0.10604545454546 26.365);
    --danger-400: oklch(0.75352941176471 0.15027272727273 26.365);
    --danger-500: oklch(0.68270588235294 0.17009090909091 26.365);
    --danger-600: oklch(0.59782352941176 0.16913636363636 26.365);
    --danger-700: oklch(0.51494117647059 0.14940909090909 26.365);
    --danger-800: oklch(0.44611764705882 0.12331818181818 26.365);
    --danger-900: oklch(0.39458823529412 0.09963636363636 26.365);
    --danger-950: oklch(0.27788235294118 0.07136363636364 26.365);

    /* Info — #6E9FCB. */
    --info-50: oklch(0.97717647058824 0.01395454545455 246.479);
    --info-100: oklch(0.95035294117647 0.03272727272727 246.479);
    --info-200: oklch(0.90547058823529 0.06318181818182 246.479);
    --info-300: oklch(0.84047058823529 0.10604545454546 246.479);
    --info-400: oklch(0.75352941176471 0.15027272727273 246.479);
    --info-500: oklch(0.68270588235294 0.17009090909091 246.479);
    --info-600: oklch(0.59782352941176 0.16913636363636 246.479);
    --info-700: oklch(0.51494117647059 0.14940909090909 246.479);
    --info-800: oklch(0.44611764705882 0.12331818181818 246.479);
    --info-900: oklch(0.39458823529412 0.09963636363636 246.479);
    --info-950: oklch(0.27788235294118 0.07136363636364 246.479);

    /* Warning — #F2A81E (dourado da marca). */
    --warning-50: oklch(0.97717647058824 0.01395454545455 75.703);
    --warning-100: oklch(0.95035294117647 0.03272727272727 75.703);
    --warning-200: oklch(0.90547058823529 0.06318181818182 75.703);
    --warning-300: oklch(0.84047058823529 0.10604545454546 75.703);
    --warning-400: oklch(0.75352941176471 0.15027272727273 75.703);
    --warning-500: oklch(0.68270588235294 0.17009090909091 75.703);
    --warning-600: oklch(0.59782352941176 0.16913636363636 75.703);
    --warning-700: oklch(0.51494117647059 0.14940909090909 75.703);
    --warning-800: oklch(0.44611764705882 0.12331818181818 75.703);
    --warning-900: oklch(0.39458823529412 0.09963636363636 75.703);
    --warning-950: oklch(0.27788235294118 0.07136363636364 75.703);

    /* Success — #008000. */
    --success-50: oklch(0.97717647058824 0.01395454545455 142.495);
    --success-100: oklch(0.95035294117647 0.03272727272727 142.495);
    --success-200: oklch(0.90547058823529 0.06318181818182 142.495);
    --success-300: oklch(0.84047058823529 0.10604545454546 142.495);
    --success-400: oklch(0.75352941176471 0.15027272727273 142.495);
    --success-500: oklch(0.68270588235294 0.17009090909091 142.495);
    --success-600: oklch(0.59782352941176 0.16913636363636 142.495);
    --success-700: oklch(0.51494117647059 0.14940909090909 142.495);
    --success-800: oklch(0.44611764705882 0.12331818181818 142.495);
    --success-900: oklch(0.39458823529412 0.09963636363636 142.495);
    --success-950: oklch(0.27788235294118 0.07136363636364 142.495);
}
```

> **Nota ao executor (opcional — só se quiser reconferir a captura):** os literais foram capturados com
> `curl -s http://localhost:8000/admin/login | tr '}' '\n' | grep -oiE -- '--(gray|primary|danger|info|warning|success)-[0-9]+:oklch\([^)]*\)'`.
> ⚠️ **Não** recapturar via `tinker`/`renderStyles()` nem via `@filamentStyles` no site: fora do painel corrente, dá a
> paleta **default** (primary âmbar `oklch(0.666 … 58.318)`), não a CEMA. Se recapturar e o `--primary-600` não tiver
> matiz ~288 (roxo), a captura veio da fonte errada.

- [ ] **Step 4: Rodar o teste e confirmar que PASSA**

Run: `docker compose exec -T app php artisan test --filter=MoldeSitePaletaTest`
Expected: **PASS** (4 testes verdes).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Filament/MoldeSitePaletaTest.php
git add tests/Feature/Filament/MoldeSitePaletaTest.php resources/css/filament/site/theme.css
git commit -m "fix(molde-forms): paleta raw do Filament no tema do site (destrava o fi-toggle)"
```

---

### Task 2: Build, cutover e gate de aceite no navegador

**Files:**
- Nenhum fonte novo. Gera os assets do Vite; se `public/build/` for versionado (conferir `.gitignore`), os assets rebuildados entram no commit; se ignorado, o build é só do cutover.

**Interfaces:**
- Consumes: o `:root` da paleta adicionado na Task 1.
- Produces: o bundle `public/build/assets/theme-*.css` com a paleta definida; o aceite visual do dono.

- [ ] **Step 1: Suíte inteira verde (I2 — zero regressão)**

Run: `docker compose exec -T app php artisan test`
Expected: **PASS**, 0 falhas. Total = baseline **1304 + 4** (os testes da Task 1; baseline da memória — confirmar com `--list-tests`). Nenhum teste existente muda (a fatia não toca servidor/PHP de produção).

- [ ] **Step 2: Build no HOST**

Run (no host, não no container — [[npm-vite-no-host]]): `npm run build`
Expected: build conclui sem erro; `public/build/manifest.json` mapeia `resources/css/filament/site/theme.css` para um novo `assets/theme-<hash>.css`.

- [ ] **Step 3: Verificação de cutover — a paleta entrou no bundle (I1)**

Run: `grep -c -- '--gray-200:' public/build/assets/theme-*.css`
Expected: **≥ 1** (hoje = 0). Prova determinística de que o `--gray-200` (raw) agora está **definido** no tema compilado do site. Checa a **definição da variável** (`--gray-200:`), não o formato do valor — o minificador (Lightning CSS) pode reformatar o `oklch(…)`, mas a definição continua. (É verificação de **cutover**, não asserção de suíte — o CI não builda o CSS.)

- [ ] **Step 4: Cutover no dev**

```bash
docker compose exec -T app php artisan optimize:clear
docker compose restart app worker
```
Expected: comandos concluem sem erro.

- [ ] **Step 5: Gate de aceite no navegador (§4 da SPEC) — conferência do DONO**

> É a prova que a suíte não dá. Rodar nas **DUAS** telas: `/minha-conta/mensagens` (como médium) e `/minha-conta/curadoria` (como diretor DEPAE).

Depois do fix, confirmar:
1. **Trilho visível:** inspecionar o `<button class="fi-toggle">` → Computed `background-color` **≠** `rgba(0,0,0,0)`. Console: `getComputedStyle(document.querySelector('.fi-toggle')).backgroundColor` retorna uma cor (cinza no OFF).
2. **Cor CEMA:** OFF cinza, **ON roxo** (`--primary-600`, `oklch(0.428 0.102 288.629)`); anéis de foco e ícones com cor.
3. **Console:** `getComputedStyle(document.documentElement).getPropertyValue('--gray-200')` agora retorna um `oklch(...)` (antes retornava `''`).

- [ ] **Step 6: UAT — o encadeamento que destrava o médium — conferência do DONO**

1. **Médium** em `/minha-conta/mensagens`: preencher e **enviar SEM tocar** o toggle → **passa** (mensagem nasce pendente). *(Continua correto — o servidor sempre esteve; agora sem o clique acidental.)*
2. **Médium:** marcar **"Direcionar a pessoas específicas"** → o **trilho move** (cinza → roxo) e o bloco **Destinatários aparece**; escolher ≥1 usuário → enviar → direcionada gravada.
3. **Curadoria** em `/minha-conta/curadoria`: os `fi-toggle`/campos com cor (ex.: `liberar_download`) têm trilho visível; publicar arbitra o nível normalmente.
4. **Não-regressão visual:** spot-check em 2–3 páginas públicas do site — nada mudou (o tema é bundle isolado das 3 páginas de conta).

- [ ] **Step 7: (sem commit de assets)**

`public/build/` **está no `.gitignore`** (confirmado no passe, `.gitignore:6`) → **não há commit de assets**. O build pertence **só ao cutover**; cada ambiente rebuilda no seu deploy. O único commit de código desta fatia é o da Task 1 (fonte + teste).

---

## Notas de fechamento

- **Cutover de PROD = do dono** (quando houver ambiente): `npm run build` (host) + `optimize:clear` + `restart app worker`. Sem migration, sem importador.
- **Fora de escopo desta fatia:** avatares nos Selects (autor/destinatário) = **próxima** fatia; a higiene `->default(false)` (se desejada) cabe lá; o slot `noindex` ausente na `agenda.blade.php` (SPEC §3.4) fica anotado para uma fatia de higiene.
- **Se o toggle ainda parecer "cru"** em algo que **não** seja cor após o fix, é outro assunto (provável a fatia dos avatares) — **não** expandir o escopo aqui.
