# Spec — Molde Filament-no-site · Paleta runtime ausente (o trilho do toggle some e trava o médium)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23
> Enquadramento travado com o dono no kickoff desta fatia (o "molde Filament-no-site que destrava o médium").
> **Esta SPEC corrige a premissa do kickoff.** O kickoff apontou o **preflight do `app.css`** como causa
> ("re-introduz o preflight depois do tema → reseta o `fi-toggle`"). Um passe de terreno adversarial (5 agentes:
> 4 investigadores + 1 refutador encarregado de *derrubar* a tese do preflight) **REFUTOU o preflight por
> medição contra o CSS compilado** e achou a causa real: **a paleta raw do Filament (`--gray-*`/`--primary-*`)
> nunca é registrada no runtime da página do site**, porque as 3 páginas Fase E emitem `@filamentScripts` mas
> **nunca `@filamentStyles`**. Os 3 elos de carga foram **reconferidos à mão** contra `public/build/assets/`
> (§2, §3) — não é teoria de cascata, é o bundle.
> Destino: **PLANO** (tasks, ordem §9.0) → passe do plano → execução. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD **`75081c5`**, PR #47 — F4c-B mesclada). Branch nova daí: `molde-filament-no-site-paleta`.
> Suíte baseline: **1304 testes** (memória da F4c-B; o plano confere com `--list-tests`).
> **Sem migration.** Provável `npm run build` (o tema CSS muda) — **no HOST** (o container `cema-app` não tem Node).
> Cutover no dev: `optimize:clear` + `restart app worker`.

---

## 0. Recorte: o que esta fatia fecha (e o que ela NÃO é)

O sintoma que importa: em [/minha-conta/mensagens](resources/views/conta/mensagens.blade.php) o **médium não consegue
enviar** para curadoria — o toggle **"Direcionar a pessoas específicas"** aparece **sem trilho** (só uma bolinha
branca flutuando), o usuário **não distingue on/off**, liga o toggle sem querer e o envio trava com
**"O campo destinatários é obrigatório"**.

**Esta fatia fecha:**

1. **Restaura a paleta raw do Filament** (`--gray-*`, `--primary-*` e semânticas) no runtime das páginas que
   embutem forms Filament — o `fi-toggle` (e todo `fi-*` que dependa de cor) volta a ter cor. **Destrava o envio
   do médium**: com o trilho visível, ele vê o estado e não liga "direcionar" por acidente.
2. Beneficia **toda a superfície Fase E** (as 3 páginas: agenda, mensagens, curadoria) — não só o toggle.
3. **Conserto ESCOPADO** ao molde da conta. Zero alcance global.

**O que esta fatia NÃO é (fail-safe do escopo):**

- **NÃO mexe no preflight** nem no `app.css` (servem o site inteiro — mexer seria global e, provado no §2, **inútil**).
- **NÃO toca a regra de negócio da curadoria** — o servidor está **correto** (§3.5): não mexer em
  `MensagensConta`, `SincronizadorDestinatarios`, `RegraPublicacao`, nem na máquina de estados.
- **NÃO toca o tema do `/admin`** ([admin/theme.css](resources/css/filament/admin/theme.css)).
- **NÃO é** a fatia dos avatares nos Selects (autor/destinatário) — essa é a **próxima**, já enquadrada; não misturar.

---

## 1. Contexto e objetivo

O molde "form Filament dentro de página do site" nasceu no spike de 10/jul
([spike](docs/superpowers/spikes/2026-07-10-filament-forms-no-site.md)) e hoje serve **3 páginas** do `/minha-conta`:
agenda, mensagens, curadoria. Cada uma injeta o **tema Filament enxuto do site**
([theme.css](resources/css/filament/site/theme.css)) via slot `headTop` **antes** do `app.css`, e os **scripts** do
Filament via `@filamentScripts` **depois** do Livewire.

O tema enxuto foi feito **sem preflight de propósito** ("o site já tem o seu") — e é aí que mora a lacuna: ele traz
o **CSS dos componentes** `fi-*`, mas esses componentes assumem que a **paleta de cor** (`--gray-*`, `--primary-*`, …)
existe no `:root` em runtime. No `/admin` essa paleta é emitida por `@filamentStyles`. **Nas páginas do site,
`@filamentStyles` nunca é chamado** — só `@filamentScripts`. Resultado: as variáveis de cor ficam **indefinidas**, e
tudo que pinta cor por elas degrada. O `fi-toggle` é o mais visível porque o **trilho** é pintado **exclusivamente**
por uma cor de paleta.

**Objetivo (aditivo, escopado ao molde):** fazer a paleta raw do Filament existir no runtime das 3 páginas Fase E,
com **paridade visual com o `/admin`**, sem tocar em nada global. Prova de pronto = **o trilho reaparece** e **o
médium envia sem direcionar** (§4, §9).

**Encadeamento raiz → sintoma (o que conecta CSS a "não envia"):**

1. **Raiz (CSS):** trilho do `fi-toggle` invisível (cor de paleta indefinida — §2).
2. **Ponte (UX):** sem ver o estado, o médium clica no toggle achando que "não aconteceu nada". Como `direcionar`
   **não tem `->default(false)`**, o estado inicial é `null`; o clique em `null` vira `true` (§3.6).
3. **Sintoma (servidor, corretíssimo):** com `direcionar = true`, o bloco Destinatários fica `visible` + `required`
   → "destinatários obrigatório". Enviar **sem tocar** o toggle **passa** (§3.5). O servidor não tem bug.

---

## 2. O veredito do diagnóstico — a tese do preflight está REFUTADA (por medição)

> Este é o coração da SPEC. O kickoff pediu "diagnóstico no navegador; nada de conserto no escuro". O passe de
> terreno diagnosticou contra o **CSS compilado** (`public/build/assets/*.css`) — mais forte que ler o fonte — e o
> §4 mantém a confirmação **no navegador** como gate de aceite.

**Causa-raiz (confirmada no build):**

- O trilho do `.fi-toggle` é pintado **só** por `bg-gray-200` (OFF) / cor primária (ON), com **borda transparente**
  e **sem sombra** — fonte: [toggle.css:2](vendor/filament/support/resources/css/components/toggle.css#L2).
- O `@theme inline` do tema-base do Filament **remapeia** `--color-gray-200 → var(--gray-200)`
  ([support/index.css:63](vendor/filament/support/resources/css/index.css#L63)). Logo `bg-gray-200` compila para
  **`background-color: var(--gray-200)`** — a variável **raw**, sem fallback.
- **`--gray-200` (raw) não é definida em NENHUMA folha da página.** Reconferido à mão: `grep -c '--gray-200:'` no
  tema do site compilado **e** no `app.css` compilado = **0/0**. O `.fi-toggle{…background-color:var(--gray-200)…}`
  está lá; a definição raw, não.
- Essas variáveis raw só existem quando **`@filamentStyles`** emite o `<style> :root{ --gray-…; --primary-… }`
  ([assets.blade.php:13-16](vendor/filament/support/resources/views/assets.blade.php#L13-L16)). As 3 páginas Fase E
  emitem só `@filamentScripts` ([mensagens.blade.php:8](resources/views/conta/mensagens.blade.php#L8)); `@filamentStyles`
  = **0 ocorrências** em `resources/views`.
- `var(--gray-200)` indefinida e **sem fallback** é **inválida em computed-value time (IACVT)** → `background-color`
  reverte ao **inicial** (`transparent`) → **trilho invisível**. A bolinha usa `var(--color-white)` (`#fff`, token
  estático **não** remapeado), que resolve → **bolinha branca flutuando**. É exatamente o sintoma relatado.

**Por que o PREFLIGHT está inocente (o ponto que corrige o kickoff):**

- `.fi-toggle` cai em **`@layer components`** ([support/index.css:33](vendor/filament/support/resources/css/index.css#L33));
  o reset `button{background-color:transparent}` do preflight cai em **`@layer base`**
  ([preflight.css:238-252](node_modules/tailwindcss/preflight.css#L238-L252)).
- A ordem **global** de camadas é fixada pela **1ª folha** carregada — o tema do site, via `headTop` — em
  `theme, base, components, utilities` ([theme.css:11](resources/css/filament/site/theme.css#L11)). O `app.css`
  (`@import 'tailwindcss'`) só reafirma essa ordem. **`components > base`.**
- Portanto **`.fi-toggle` VENCE** o `button{}` do preflight. O preflight é a declaração **perdedora (riscada)**. Ele
  não some o trilho: quem "vence" já resolve para `transparent` sozinho (a var indefinida).
- **Remover o preflight não mudaria nada** — IACVT reverte ao *initial*, não ao valor do preflight. Ele não é nem
  necessário nem suficiente.
- **Duplamente falso:** o `app.css` até define `--color-gray-200: oklch(…)`, mas é a **variável errada** (o token
  `--color-gray-*`, não o raw `--gray-*` que o toggle usa por causa do `@theme inline`). A contribuição de paleta
  do `app.css` é **inerte** para o toggle. Ou seja: "o preflight **do app.css** reseta o toggle" erra na peça (preflight)
  **e** na folha (o `app.css` não pinta o trilho de jeito nenhum).

**Reforço independente (ratificado no passe): a especificidade também exonera o preflight.** A precedência da cascata é
consultada nesta ordem: origem/importância → **camada** → especificidade → ordem de origem. No caso real as regras estão
em **camadas distintas** (`.fi-toggle` em `components`, o reset em `base`), então a **camada decide sozinha** e a
especificidade nem chega a ser consultada. Mas há uma **rede independente**, que **dispensa conferir `@layer` no CSS
minificado**: o reset do preflight é seletor de **elemento** (`button, [type=…]`, especificidade **0,0,1**); `.fi-toggle`
é **classe** (**0,1,0**). No cenário hipotético em que ambos caíssem na **mesma** camada (ou sem camadas), a classe
venceria o elemento por especificidade — mesmo o `app.css` vindo depois. **O preflight não vence o toggle sob nenhuma das
duas análises**; e, decisivo, removê-lo não muda nada (a var indefinida já resolve para `transparent` — IACVT).

**Por que só o toggle chama atenção (a assimetria):** os outros `fi-*` (Select, DatePicker, RichEditor) têm
superfície `bg-white` — e `--color-white` é token **estático** (`#fff`), **não** remapeado pelo `@theme inline`
([support/index.css:36-131](vendor/filament/support/resources/css/index.css#L36)). Então renderizam como caixa branca
visível mesmo sem a paleta; só perdem detalhes (anel fino `ring-gray-950/10`). O `fi-toggle` não tem superfície
estática no trilho → some inteiro. **Mas o escopo do bug é maior que o toggle:** anéis de foco
(`focus-visible:ring-primary-600`), `.fi-icon` (`text-gray-400`), badges e o estado **ON** do próprio toggle
(`--primary-500` também indefinido — por isso "não distingue on/off") estão todos degradados. Consertar a paleta
conserta a Fase E inteira.

---

## 3. Terreno confirmado por medição (evidência `arquivo:linha`)

### 3.1 A cascata das duas folhas (por que `components > base`)

- [theme.css:11](resources/css/filament/site/theme.css#L11) declara `@layer theme, base, components, utilities;` — e,
  por ser a **1ª folha** (slot `headTop`, [app.blade.php:23](resources/views/components/layout/app.blade.php#L23)),
  **fixa a ordem global**. O `@vite(app.css)` vem depois ([app.blade.php:24](resources/views/components/layout/app.blade.php#L24));
  seu `@import 'tailwindcss'` ([app.css:7](resources/css/app.css#L7)) reusa os mesmos nomes → não reordena.
- O tema do site importa Tailwind **sem** preflight (só `theme.css` + `utilities.css`) —
  [theme.css:13-21](resources/css/filament/site/theme.css#L13-L21). O **único** preflight da página é o do `app.css`.

### 3.2 A variável raw + o `@theme inline` (o mecanismo exato)

- [toggle.css:2](vendor/filament/support/resources/css/components/toggle.css#L2): `.fi-toggle { @apply … border-2
  border-transparent bg-gray-200 … dark:bg-gray-700 }`; [toggle.css:22](vendor/filament/support/resources/css/components/toggle.css#L22):
  a bolinha usa `bg-white`.
- [support/index.css:63](vendor/filament/support/resources/css/index.css#L63): `@theme inline { --color-gray-200:
  var(--gray-200) }` (e `:90` idem para `--color-primary-500: var(--primary-500)`). O `inline` **inlina o valor** na
  utilidade → `bg-gray-200` vira `var(--gray-200)`.
- **Reconferido no compilado** (`public/build/assets/theme-<hash>.css` — o hash rotaciona a cada build; o fonte acima
  garante o resultado): a regra é `.fi-toggle{…background-color:var(--gray-200);border-color:#0000;border-width:2px…}`,
  e `--color-gray-200:var(--gray-200)`. `grep -c '--gray-200:'` no tema do site **e** no `app.css` = **0/0**. O
  `app.css` define `--color-gray-200:oklch(92.8% .006 264.531)` (a variável **errada**, inerte para o toggle).

### 3.3 A origem runtime da paleta (o que falta injetar)

- A marcação do toggle é um `<button class="fi-toggle" role="switch" type="button">` com a bolinha como 1º filho
  `<div>` — [toggle.blade.php:14](vendor/filament/support/resources/views/components/toggle.blade.php#L14),
  [:39](vendor/filament/support/resources/views/components/toggle.blade.php#L39).
- O bloco `:root{ --{cor}: {valor} }` que define a paleta raw é emitido por
  [assets.blade.php:13-16](vendor/filament/support/resources/views/assets.blade.php#L13-L16), acionado por
  **`@filamentStyles`** (o painel `/admin` o injeta pelo layout base do Filament). **Reconferido:** `@filamentStyles`
  = **0** em `resources/views`.

### 3.4 O molde das 3 páginas Fase E (o alvo do conserto)

- Ordem idêntica nas 3: `headTop = @vite(theme.css)` **antes** de `@vite(app.css)`; `scripts = @filamentScripts`
  **depois** do Livewire — [mensagens.blade.php:6-8](resources/views/conta/mensagens.blade.php#L6-L8),
  [curadoria.blade.php:5-7](resources/views/conta/curadoria.blade.php#L5-L7),
  [agenda.blade.php:5,7](resources/views/conta/agenda.blade.php#L5).
- O componente [conta.blade.php:6-8](resources/views/components/layout/conta.blade.php#L6-L8) repassa os 3 slots para
  o `x-layout.app`. A superfície Fase E é **exatamente essas 3** views (grep de `theme.css`/`@filamentScripts`/
  `@filamentStyles` em `resources/views` = 6 linhas, todas nelas). **Nenhuma outra** view baixa o tema do site.
- **Divergência anotada (não é escopo):** `agenda.blade.php` **não** tem o slot `head`/`noindex` que mensagens e
  curadoria têm ([agenda.blade.php:5,7](resources/views/conta/agenda.blade.php#L5)). Fica como observação para uma
  fatia futura de higiene — não se resolve aqui.

### 3.5 O servidor está correto (não tocar) — tese do kickoff confirmada

- O bloco Destinatários é gateado pelo predicado `(bool) $get('direcionar')`
  ([MensagemForm.php:288](app/Filament/Schemas/MensagemForm.php#L288)); a Section fica `->visible($ehDirecionada)`
  ([:200](app/Filament/Schemas/MensagemForm.php#L200)) e o Select `->required($ehDirecionada)->minItems(1)`
  ([:211-212](app/Filament/Schemas/MensagemForm.php#L211-L212)).
- Com `direcionar = false`: `nivel = null` → `SincronizadorDestinatarios::filtrarPorNivel(null) = []` → `sync([])`,
  **zero linhas** no pivô `mensagem_destinatario` ([MensagensConta.php:144,154,166](app/Livewire/Conta/MensagensConta.php#L144);
  [SincronizadorDestinatarios.php:25-27](app/Support/Mensagens/SincronizadorDestinatarios.php#L25-L27)). A Section
  invisível **não** valida os filhos → **enviar sem tocar o toggle passa**. A `RegraPublicacao` (que exigiria ≥1
  destinatário) só roda **na publicação** ([RegraPublicacao.php:31-33](app/Support/Mensagens/RegraPublicacao.php#L31-L33)),
  não no lançamento do médium ([MensagensConta.php:153](app/Livewire/Conta/MensagensConta.php#L153), nasce PENDENTE).

### 3.6 O `->default(false)` é INERTE (não é o conserto)

- `Toggle::make('direcionar')->live()` **sem** `->default(false)`
  ([MensagemForm.php:284-286](app/Filament/Schemas/MensagemForm.php#L284-L286)). Em `novo()` o `fill()` sem args deixa
  `data.direcionar = null`; em `editar()` vem `bool` real ([MensagensConta.php:68,98](app/Livewire/Conta/MensagensConta.php#L68)).
- **Todos** os consumidores castam `(bool)`; como `(bool) null === false`, `->default(false)` **não** muda
  `visible`/`required` nem a gravação, e **não** conserta o CSS. Único efeito seria cosmético (fixa `aria-checked`
  como `false` explícito). É **ortogonal** ao bug. Fica como decisão opcional de higiene (§5, §11) — não como fix.

### 3.7 A paleta do `/admin` = o alvo de paridade

- [AdminPanelProvider.php:60-84](app/Providers/Filament/AdminPanelProvider.php#L60-L84) registra: `primary` (rampa
  **hex explícita** 50–950, 600=`#4e4483`, 900=`#2f2952`), `info = Color::hex('#6E9FCB')`,
  `warning = Color::hex('#F2A81E')`, `danger = Color::hex('#C33A36')`, `success = Color::hex('#008000')`,
  `gray = Color::Neutral`. **Esse conjunto é o valor de verdade** que o conserto deve reproduzir no site.

---

## 4. Protocolo de diagnóstico no navegador — o GATE (guardrail #1 do kickoff)

> Obrigatório **antes** de escolher o mecanismo e **depois** de aplicar, nas **duas** telas (`/minha-conta/mensagens`
> como médium e `/minha-conta/curadoria` como diretor DEPAE). É a prova que só o browser dá — a suíte (§6) não vê CSS.

**Antes (confirmar a causa-raiz, refutar o preflight):**

1. Inspecionar o `<button class="fi-toggle">` → **Computed** → `background-color` = `rgba(0, 0, 0, 0)`.
2. Em **Styles**, a declaração **vencedora (não riscada)** é a do próprio `.fi-toggle` (`@layer components`) com
   `background-color: var(--gray-200)` **sem swatch/valor resolvido**; a regra `button{ … transparent }` do preflight
   aparece **riscada** abaixo → **preflight exonerado ao vivo**.
3. Console: `getComputedStyle(document.documentElement).getPropertyValue('--gray-200')` retorna **`''`** (vazio).
4. **Teste decisivo de 1 linha:** `document.documentElement.style.setProperty('--gray-200', '#e5e7eb')` → **o trilho
   reaparece cinza na hora**. Se reaparece, é **paleta ausente** (confirmado); se continuasse transparente, seria
   outra regra (não é o caso).

**Depois (aceite do conserto):**

5. Trilho **OFF** cinza e **ON** roxo institucional (`#4e4483`) — on/off distinguível; anéis de foco e ícones com cor.
6. Se a via escolhida for `@filamentStyles` (§5, Opção B): confirmar no `<head>` que o `:root` emitido traz
   **`--primary-600: #4e4483`** (cor CEMA), **não** a paleta default do Filament. (Se emitir default, cai para a Opção A.)
7. **Sem regressão visual** no resto do site (o tema é bundle isolado — §3.4; spot-check em 2–3 páginas públicas).

---

## 5. Decisões de desenho — candidatas de conserto (ranqueadas)

Todas partem do mesmo alvo: **fazer a paleta raw (`--gray-*`, `--primary-*` e semânticas) existir no `:root` das 3
páginas Fase E**, com paridade CEMA (§3.7), **sem tocar em nada global**.

### 5.0 — ⚠️ ATUALIZAÇÃO DO VEÍCULO (medido na EXECUÇÃO, 23/jul — LEIA PRIMEIRO)

A Opção A abaixo (paleta **no `theme.css`**) foi ratificada no 1º passe, mas a **execução provou que NÃO funciona**: o
transform do **Tailwind v4** **poda** as variáveis raw (`--gray-*`/`--primary-*`) declaradas à mão em CSS que ele
processa. Medido no bundle compilado, `grep -c '--gray-200:'` = **0** em **todas** as variantes testadas — `:root`,
`html:root`, `@theme static`, `@layer base{:root}` — **minificado E não-minificado** (logo **não** é o
Lightning/minificador, é o **Tailwind**). O `--font-family` sobrevive por ser input de tema reconhecido; `--gray-200`
(não-namespace) é descartado.

**P1 (cores CEMA) e P2 (paleta completa) seguem intactos; muda só o VEÍCULO** (decisão do dono no 2º passe): a paleta
vai num **`<style>` INLINE** no `<head>`, via **componente Blade** [conta/filament-head](resources/views/components/conta/filament-head.blade.php)
(que também centraliza o `@vite(theme.css)`), usado no `headTop` das 3 páginas Fase E. Inline no HTML é **imune ao
build** — é exatamente como o `@filamentStyles` do `/admin` injeta a paleta. **Sem CSP** no projeto (0 ocorrências,
0 nonce), o inline não é bloqueado. Guarda = **teste HTTP** [MoldeSitePaletaTest](tests/Feature/Conta/MoldeSitePaletaTest.php)
que assere o `<style>` com `--gray-200:` e `--primary-600:` de matiz CEMA (~288, roxo) nas **duas** telas (prova que
as blades usam o componente). As "Opção A/B" abaixo ficam como **registro do raciocínio do 1º passe** — o alvo (cores,
completude, escopo, captura correta dos literais) continua valendo; só a Opção A leu "dentro do theme.css", o que o
build refutou.

### Opção A — **paleta raw estática no tema escopado** (~~RECOMENDADA~~ — veículo refutado pelo build, ver §5.0)

Declarar um bloco `:root { --gray-50..950; --primary-50..950; --danger/--info/--warning/--success-50..950 }` **dentro
de** [theme.css](resources/css/filament/site/theme.css) — que só é carregado pelo `headTop` das 3 páginas.

- **Fonte determinística dos valores (sem reproduzir algoritmo de cor):** capturar o `:root{ --gray-…; --primary-… }`
  que o **`/admin` já emite** (view-source de qualquer página do painel, o bloco do `@filamentStyles`) e **colar os
  literais** no `theme.css`. Garante **paridade exata** com o painel, é **autossuficiente** em runtime (não depende de
  o painel estar "corrente") e **imune a tree-shaking**.
- **Formato dos literais (ponto menor #2 do passe, resolvido):** serão `oklch(…)` — **cor completa**, usável direto em
  `background-color`, **não** canais RGB soltos. Garantido por
  [AssetManager.php:312-324](vendor/filament/support/src/Assets/AssetManager.php#L312-L324), cujo
  `resolveColorShadeFromPalette` só retorna strings que começam com `oklch(`. O teste decisivo do §4 (`setProperty
  --gray-200 = #e5e7eb` → trilho volta) já prova, ao vivo, que o formato de cor completa funciona.
- **Captura correta — com uma ARMADILHA medida (23/jul):** a captura **tem** que vir de uma **requisição HTTP a uma
  página do painel servido** (ex.: `curl -s http://localhost:8000/admin/login`), onde o painel é o **corrente** e o
  `FilamentColor` tem as cores CEMA. ⚠️ **NÃO** capturar via `artisan tinker`/`renderStyles()` nem via `@filamentStyles`
  fora do painel: **sem painel corrente, `FilamentColor::getColors()` cai na paleta DEFAULT** (primária **âmbar**, não o
  roxo CEMA). Medido: `tinker` deu `--primary-600: oklch(0.666 0.179 58.318)` (âmbar, matiz 58); `/admin/login` servido
  deu `oklch(0.428 0.102 288.629)` (roxo, matiz 288). **É a mesma razão que reprova a Opção B** — reforça a escolha de A.
  Os literais reais já foram capturados e estão embutidos no PLANO (Task 1), sem placeholder.
- **Risco de regressão:** ~nulo. O `theme.css` é bundle separado, baixado **só** pelas 3 páginas de conta (§3.4);
  um `:root` de paleta ali **não vaza** para o resto do site e só afeta quem consome `var(--gray-*)`/`var(--primary-*)`
  — os `fi-*`.
- **Custo:** o bloco é verboso (≈ 8 cores × 11 tons), mas os valores são **estáveis** (a paleta do admin quase não muda).

### Opção B — **emitir `@filamentStyles` no molde** (alternativa de menor manutenção, condicional)

Adicionar `@filamentStyles` ao `headTop` (ou ao `x-layout.conta`) das 3 páginas. O parcial
[assets.blade.php:13-16](vendor/filament/support/resources/views/assets.blade.php#L13-L16) emite **exatamente** o
`:root` de paleta — zero literais copiados, paridade automática.

- **Condição (o gate do §4.6):** só vale **se** o navegador confirmar que ela emite as cores **CEMA**
  (`--primary-600: #4e4483`) na página do site. Há dúvida real: fora do painel corrente, `FilamentColor::getColors()`
  **pode** cair na paleta **default** do Filament (primária âmbar), quebrando a paridade. **O plano decide isso no
  browser**, não no escuro.
- **Custo/risco extra:** `@filamentStyles` também emite os **assets registrados** (ex.: `cema-editor.css`, fonte Inter
  — o tema já sobrepõe a fonte para Poppins via `html:root`). Peso modesto, aceitável em página autenticada, mas é
  mais que a Opção A. Memória do projeto ([[filament-forms-fora-do-painel]]) alerta que `fi-*` fora do painel tem
  pegadinhas — por isso B é **condicional**, não default.

### O que NÃO fazer (fail-safe)

- **Não** mexer no preflight nem no `app.css` — global e **inútil** (§2).
- **Não** definir `--color-gray-*` (token) achando que pinta o trilho — é a variável **errada**; o toggle usa o raw
  `--gray-*` (§2, §3.2).
- **Não** confiar em `--gray-*: var(--color-neutral-*)` sem checar o compilado — o `--color-neutral-*` pode ser
  **tree-shaken** se nada mais o usa (risco que a Opção A, com literais, elimina).

### Recomendação

**Opção A** (estática, valores capturados do `/admin`) como caminho primário — determinística, autossuficiente,
paridade exata, risco global nulo. **Opção B** só se o passe no navegador (§4.6) provar que emite as cores CEMA
barato; nesse caso é preferível pela manutenção. **Decisão final = do dono no passe** (§11).

**Higiene opcional (ortogonal):** somar `->default(false)` ao Toggle `direcionar`
([MensagemForm.php:284](app/Filament/Schemas/MensagemForm.php#L284)) — não conserta o bug (§3.6), mas deixa
`data.direcionar` determinístico. Incluir **só** se o dono quiser; não é necessário.

---

## 6. Invariantes (cada um vira teste OU verificação explícita)

> A suíte **não enxerga CSS renderizado** (Livewire::test avalia o servidor, não o clique/estilo). Por isso os
> invariantes se dividem em **automatizáveis** (build/servidor) e **UAT manual** (o visual — §4, §9).

- **I1 (guarda HTTP — atualizada p/ o veículo inline, §5.0):** a paleta chega às páginas Fase E. Teste **HTTP**
  [MoldeSitePaletaTest](tests/Feature/Conta/MoldeSitePaletaTest.php): GET autenticado a `conta.mensagens` (médium) **e**
  `conta.curadoria` (diretor DEPAE), assere que o HTML traz o `<style>` com `--gray-200:` **e** `--primary-600:` de matiz
  CEMA (~288, roxo — não âmbar ~58). **CI-safe** (renderiza o Blade, sem build) e **mais forte** que a guarda de fonte
  original: prova que a paleta **alcança a página** e que as **duas** blades usam o componente. *(A guarda de fonte no
  `theme.css` e o `grep` no bundle foram descartados: com o veículo inline, o `theme.css`/bundle não têm mais a paleta.)*
- **I2 (servidor intacto):** os testes existentes das Mensagens continuam **verdes** — enviar sem direcionar passa;
  `direcionar=false` não grava destinatário; `RegraPublicacao` inalterada. **Zero regressão** na suíte (1304).
- **I3 (escopo):** nenhuma página **fora** das 3 Fase E baixa o `theme.css` (grep de `theme.css` em `resources/views`
  segue = 6 linhas nas 3 blades). O conserto não pode vazar.
- **I4 (UAT — a prova real, §9):** o trilho reaparece (OFF cinza / ON roxo) e **o médium envia sem direcionar**; ao
  marcar "direcionar", o trilho move e o bloco Destinatários aparece.

---

## 7. Riscos e armadilhas

- **A variável errada.** `--color-gray-*` (token Tailwind) ≠ `--gray-*` (raw Filament). Definir o token **não** pinta
  o trilho. O conserto tem de definir o **raw** (§2, §3.2).
- **`@filamentStyles` pode emitir a paleta DEFAULT** (não-CEMA) fora do painel corrente — daí a Opção B ser condicional
  ao gate do §4.6.
- **Tree-shaking do `--color-neutral-*`** — não usar essa ponte sem checar o compilado (Opção A com literais evita).
- **Fonte Inter / assets extras** se a Opção B for adotada — modesto, mas medir.
- **Hash do bundle rotaciona** (`theme-<hash>.css`) — não fixar o nome do arquivo em teste; testar pelo conteúdo/manifest.
- **Não confundir com a próxima fatia** (avatares nos Selects). Se o form ainda parecer "cru" em algo que não seja cor,
  é outro assunto — não expandir o escopo aqui.

---

## 8. Cutover (dev; PROD do dono, quando houver)

1. `npm run build` — **no HOST** (o container `cema-app` não tem Node; o tema CSS muda).
2. `docker compose exec -T app php artisan optimize:clear`.
3. `restart app worker` (OPcache `validate_timestamps=0` no dev — `view:clear` não basta).
4. **Conferência do dono (visual):** abrir `/minha-conta/mensagens` (médium) e `/minha-conta/curadoria` (diretor);
   validar o §4 "depois" (trilho OFF/ON, envio sem direcionar, sem regressão no site).

**Sem migration. Sem importador.** O agente executa build+cutover e **reporta o medido**; ao dono fica a conferência
visual (o toggle e o envio).

---

## 9. O que prova que está pronto

- **Automatizado:** suíte **1304 verde, 0 regressão** (§6 I2); Pint limpo (o CI roda `pint --test` antes dos testes).
- **Build (I1):** `theme-*.css` pós-build contém `--gray-*`/`--primary-*` no `:root` (hoje 0).
- **UAT manual (a prova que só o browser dá) — nas DUAS telas:**
  1. Médium: **envia SEM tocar** o toggle → **passa** (nasce pendente).
  2. Médium: **marca "Direcionar"** → o **trilho move** (cinza→roxo) e o bloco **Destinatários aparece**; escolhe ≥1 →
     envia; direcionada gravada.
  3. Curadoria: os `fi-toggle`/campos com cor (ex.: `liberar_download`) têm trilho visível; publicar arbitra nível.
  4. **Sem regressão visual** no site público (spot-check).
- **Verificação VISUAL nos dois forms compilados**, não só a suíte (guardrail do kickoff).

---

## 10. Fora de escopo

- **Avatares nos Selects** (autor/destinatário) — **próxima** fatia, já enquadrada.
- **Regra de negócio da curadoria** — servidor correto (§3.5): não tocar `MensagensConta`,
  `SincronizadorDestinatarios`, `RegraPublicacao`, máquina de estados.
- **Tema do `/admin`** ([admin/theme.css](resources/css/filament/admin/theme.css)).
- **Preflight / `app.css`** (global) — refutado como causa (§2); mexer seria inútil e arriscado.
- **Slot `noindex` ausente na `agenda.blade.php`** (§3.4) — anotado como observação; higiene de outra fatia.

---

## 11. Passe do dono (2026-07-23) — SPEC APROVADA, decisões RATIFICADAS

Veredito: **APROVADA, segue para o PLANO**. O dono reproduziu os 3 elos do §2 por medição no CSS compilado (build de
22/jul). **Diagnóstico fechado — não reabrir.** As 4 pendências foram **ratificadas**:

1. **P1 = Opção A** — paleta raw **estática** no [theme.css do site](resources/css/filament/site/theme.css), com
   literais capturados do `:root` que o `/admin` emite. (Opção B descartada.)
2. **P2 = paleta COMPLETA** — `gray + primary + danger/info/warning/success`. Conserta a Fase E inteira.
3. **P3 = `->default(false)` FORA.** É inerte (§3.6) e tocaria o `MensagemForm`; esta fatia fica **100% CSS/paleta**.
   Se a higiene for desejada, cabe na **fatia dos avatares** (a próxima).
4. **P4 = o `:root` dentro do próprio `theme.css` do site** (mantém o molde num arquivo). **⚠️ REVISADO no 2º passe
   (23/jul) — ver §5.0 e §11-bis:** a execução mediu que o `theme.css` **não** carrega a paleta (o Tailwind v4 poda as
   variáveis raw no build). Veículo revisado = **`<style>` INLINE** via componente Blade
   [conta/filament-head](resources/views/components/conta/filament-head.blade.php). P1/P2/P3 seguem; muda só o veículo.

**Reforço incorporado ao §2** (a especificidade exonera o preflight independentemente de `@layer`) e **pontos menores**
tratados: I1 vira **guarda HTTP** (§6, atualizada); os literais da paleta são **cores completas `oklch(…)`**, não canais
RGB (§5). O **gate do §4 permanece firme** (teste decisivo ANTES, aceite visual nas DUAS telas, UAT do envio, spot-check
de não-regressão) — é a prova que a suíte não dá.

## 11-bis. 2º passe do dono (2026-07-23) — pivot de VEÍCULO (P4) aprovado

A execução bateu num muro medido: **a paleta não sobrevive ao build dentro do `theme.css`** (Tailwind v4 poda `--gray-*`/
`--primary-*`; medido 0 no bundle em `:root`/`html:root`/`@theme static`/`@layer base`, minificado e não). O dono
**aprovou o pivot de veículo**, confirmando que **não há CSP** no projeto (inline seguro). Decisões:

- **Veículo = `<style>` inline** no `<head>` das 3 páginas Fase E, via componente Blade
  [conta/filament-head](resources/views/components/conta/filament-head.blade.php) (centraliza `@vite(theme.css)` + a
  paleta) — como o `@filamentStyles` do `/admin` faz. **P1 (cores CEMA) e P2 (paleta completa) intactos.**
- **`theme.css` LIMPO** — removido o `:root` morto (revert do bloco); **mantém** o `@import` dos componentes `fi-*` (de
  onde vem o `.fi-toggle`); só a paleta sai.
- **Guarda vira teste HTTP** [MoldeSitePaletaTest](tests/Feature/Conta/MoldeSitePaletaTest.php) (substitui o de fonte):
  GET autenticado a `mensagens` **e** `curadoria`, assere o `<style>` com `--gray-200:` e `--primary-600:` de matiz CEMA
  (~288) — cobre as **duas** telas (prova que as blades usam o componente).
- **Gate do §4 mantido** (o teste decisivo já provou que definir `--gray-200` conserta; o `<style>` define — confirmar o
  trilho no navegador).
