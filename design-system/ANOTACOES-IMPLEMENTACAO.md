# Anotações e Compreensão do Design — Novo Site CEMA

> Documento de trabalho do arquiteto de front-end (planejamento). Consolida o handoff de design
> (`design_handoff_cemanet/`) e o design-system (`design-system/`) para guiar a implementação no
> nosso ambiente **Laravel 13 · Filament 5 · Blade · Livewire 3 · Tailwind CSS v4 · Vite 8 ·
> MySQL 8 · Docker**. Idioma: pt-BR. Produzido em 2026-06-24 a partir da análise do handoff.

---

## 1. Resumo executivo

O handoff é um **protótipo navegável** (`design_handoff_cemanet/prototype/CEMA Site.dc.html`)
construído em um micro-framework proprietário da Anthropic ("Design Component" / `.dc.html`),
acompanhado de um runtime descartável (`support.js`, ~1581 linhas, React 18 + Babel carregados de
CDN em tempo de execução) e de 6 screenshots (`design_handoff_cemanet/screenshots/*.png`). Ele
cobre 5 telas: **Início (T01), Single Palestra (T06), Contato (T05), Nossa História (T03) e Guia
de Estilo**.

**Nível de fidelidade:** o protótipo é de **alta fidelidade visual e estrutural** (marcação
semântica, hierarquia, paleta e comportamentos descritos), porém **NÃO é entregável** — todo o
andaime (`<x-dc>`, `sc-for`, `sc-if`, `dc-import`, `support.js`, roteamento por estado, troca
mobile/desktop por `innerWidth`) é infraestrutura do editor e deve ser descartado. As cores estão
**hardcoded inline** (`style="..."`), sem `:root`/`var(--...)`, sem Tailwind no protótipo.

**Fonte da verdade (ordem de prioridade):**
1. **`design-system/` (specs + `tokens.json`)** — fonte canônica de tokens, escalas tipográficas,
   breakpoints, A11y/SEO/performance. Tem precedência.
2. **Protótipo HTML** — fonte da **estrutura semântica, ordem das seções e comportamentos**.
   Referência de marcação, não de valores.
3. **Screenshots** — confirmação visual (paleta, uso do dourado, layout). Todos são **recortes
   parciais do topo**; não mostram footer nem seções inferiores.

**O que muda por estarmos em Blade/Livewire (e não Astro/Next/React):** o handoff (e a seção 8 das
specs) sugere Astro/Next/SSG/headless — **ignorar**. No nosso stack: roteamento por estado →
**rotas Laravel reais com URLs** (essencial para SEO, que o protótipo não tem); `sc-for` →
`@foreach` sobre Eloquent/MySQL; componentes `.dc.html` → **componentes Blade `<x-...>`**;
interatividade → **Alpine.js (cliente) + Livewire 3 (servidor/estado)**; "SSG" → **SSR
Blade/Livewire + cache**. Tokens vivem no `@theme` de `resources/css/app.css` (não há
`tailwind.config.js`).

---

## 2. Identidade & marca

**CEMA — Centro Espírita Maria Madalena.** Casa espírita em Planaltina, Brasília-DF (Quadra 02,
Lote 16, Vila Vicentina; CNPJ 01.600.089/0001-90). Tom: **institucional, acolhedor, espiritual,
sereno** — muito espaço em branco, hierarquia clara, contraste adequado, nada agressivo.

**Paleta de marca (hex literais confirmados):**
- `#4E4483` **Primary** (roxo/índigo institucional) — header nav, títulos, botões primários, ícone do logo.
- `#6E9FCB` **Secondary** (azul) — links, interativos.
- `#89AB98` **Accent** (verde sálvia) — acentos, badges de ano na timeline.
- **`#F2A81E` DOURADO DA MARCA** — confirmado em CTAs ("Próxima palestra"), badge eyebrow,
  `::selection`, borda do dropdown do mega-menu (`border-top:3px solid #F2A81E`), partículas,
  estado ativo de navegação. Usado **com parcimônia, como acento pontual** — nunca em grandes
  áreas. Confirmado nos 6 screenshots.
- `#E79048` Orange · `#C33A36` Danger (estado "curtido") · `#008000` Success.
- `#F3EDDD` Cream (fundos suaves, cards) · `#F6F6F6` Surface · `#FFFFFF` White.
- **`#2f2952`** roxo escuro — fundo do footer e fim do gradiente dos heros roxos.
- Texto: `#000000` corpo · `#26242e` tinta alternativa · `#414141` secundário/subtítulo ·
  `#7A8A8A` muted/metadados.
- Bordas: `#E4E4E4` · `#EBE8E8` (suave).

**Tipografia (5 famílias):**
| Família | Uso | Pesos |
|---|---|---|
| **Work Sans** | Títulos h1–h3 | 400, 600 |
| **Poppins** | Corpo, h4/h5, subtítulos, small | 300/400 |
| **Roboto** | UI, botões, labels (apoio/defaults) | 400/500/600 |
| **Roboto Slab** | Citações, destaques editoriais | 400 |
| **Roboto Mono** | Eyebrows, labels técnicos, datas, rótulos de placeholder | 400/500 |

Par crítico: **Work Sans 600 + Poppins 400** (preload). Fontes via **Bunny**
(`laravel-vite-plugin/fonts`), não Google Fonts. Carregar só os pesos usados.

**As 5 logos** (`design_handoff_cemanet/prototype/assets/`):
- `logo-horizontal.png` — header desktop (altura ~46px).
- `logo-branco.png` — footer sobre roxo (altura ~74px).
- `logo-icone.png` — favicon, ícone compacto, off-canvas.
- `logo-vert.png` / `logo-vert-comp.png` — variações verticais (uso pontual: hero, telas estreitas).

O ícone da marca: contorno de "casa/telhado" em traço duplo dourado, com livro aberto azul e
coração rosa/magenta — usar como referência ao recriar em SVG.

---

## 3. Tokens → Tailwind v4

### 3.1 Tabela de reconciliação (resumo)

| Token | `tokens.json` / `app.css` atual | Handoff/protótipo | Ação |
|---|---|---|---|
| primary, secondary, accent, orange, danger, success, cream | OK | OK | manter |
| **gold (dourado)** | **ausente** | `#F2A81E` | **ADICIONAR `--color-gold`** |
| **footer-bg** | **ausente** | `#2f2952` | **ADICIONAR `--color-footer-bg`** |
| **text-ink (alt)** | **ausente** | `#26242e` | **ADICIONAR `--color-text-ink`** |
| **font-mono (Roboto Mono)** | **ausente** | usada | **ADICIONAR `--font-mono`** |
| text-secondary | existe como `neutral-dark` (#414141) | `text-secondary` | **RENOMEAR** |
| text-muted | existe como `neutral-gray` (#7a8a8a) | `text-muted` | **RENOMEAR** |
| surface | existe como `neutral-light` (#f6f6f6) | `surface` | **RENOMEAR** |
| border | existe como `neutral-border` (#e4e4e4) | `border` | **RENOMEAR** |
| border-muted | existe como `neutral-muted` (#ebe8e8) | separador suave | **RENOMEAR** |
| breakpoint tablet-sm (650px) | ausente no `app.css` | — | adicionar (uso raro) |
| breakpoint desktop-sm (1024px) | **ausente no `app.css`** | ponto de troca `<1024` | **ADICIONAR** (crítico) |

O `app.css` atual confirma: faltam `gold`, `footer-bg`, `text-ink`, `font-mono`, `desktop-sm`; e
os neutros estão sob nomes `neutral-*`.

### 3.2 Bloco `@theme` final recomendado para `resources/css/app.css`

```css
@theme {
    /* ===== Tipografia — design-system ===== */
    --font-sans: 'Poppins', ui-sans-serif, system-ui, sans-serif;
    --font-display: 'Work Sans', ui-sans-serif, system-ui, sans-serif;
    --font-ui: 'Roboto', ui-sans-serif, system-ui, sans-serif;
    --font-serif: 'Roboto Slab', ui-serif, Georgia, serif;
    --font-mono: 'Roboto Mono', ui-monospace, 'SFMono-Regular', monospace; /* ADIÇÃO: eyebrows/labels técnicos/datas */

    /* ===== Cores institucionais — design-system ===== */
    --color-primary: #4e4483;
    --color-secondary: #6e9fcb;
    --color-accent: #89ab98;
    --color-gold: #f2a81e;          /* ADIÇÃO: dourado da marca — CTAs/badges/estado ativo */
    --color-orange: #e79048;
    --color-danger: #c33a36;
    --color-success: #008000;
    --color-cream: #f3eddd;

    /* Texto */
    --color-text: #000000;
    --color-text-ink: #26242e;       /* ADIÇÃO: tinta neutra alternativa */
    --color-text-secondary: #414141; /* CORREÇÃO: era neutral-dark */
    --color-text-muted: #7a8a8a;     /* CORREÇÃO: era neutral-gray */

    /* Superfícies e bordas */
    --color-surface: #f6f6f6;        /* CORREÇÃO: era neutral-light */
    --color-border: #e4e4e4;         /* CORREÇÃO: era neutral-border */
    --color-border-muted: #ebe8e8;   /* CORREÇÃO: era neutral-muted */
    --color-footer-bg: #2f2952;      /* ADIÇÃO: roxo escuro do rodapé/heros */

    /* ===== Breakpoints — design-system ===== */
    --breakpoint-mobile-sm: 480px;
    --breakpoint-mobile: 600px;
    --breakpoint-tablet-sm: 650px;   /* ADIÇÃO opcional (uso raro) */
    --breakpoint-tablet: 768px;
    --breakpoint-desktop-sm: 1024px; /* ADIÇÃO: ponto de troca desktop/mobile do header */
    --breakpoint-desktop: 1200px;
    --breakpoint-desktop-lg: 1440px;

    /* ===== Raios — design-system ===== */
    --radius-sm: 4px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --radius-xl: 20px;
    --radius-pill: 50px;

    /* ===== Sombras — design-system ===== */
    --shadow-card: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-elevated: 0 4px 16px rgba(0, 0, 0, 0.12);
    --shadow-border: inset 0 0 0 1px #e4e4e4;

    /* ===== Espaçamentos de seção — design-system ===== */
    --spacing-section-sm: 3.75rem;
    --spacing-section: 5rem;
    --spacing-section-lg: 9.13rem;
}
```

**Atenção:** ao renomear `neutral-*`, é obrigatório atualizar quaisquer usos existentes em views
Blade (`bg-neutral-light`, `text-neutral-dark`, `text-neutral-gray`, `border-neutral-border`,
`bg-neutral-muted`) para os novos nomes (`bg-surface`, `text-text-secondary`, `text-text-muted`,
`border-border`, `border-border-muted`). Recomenda-se também espelhar `gold`, `footer-bg`,
`text-ink` e `Roboto Mono` de volta no `design-system/tokens.json` para restabelecer a fonte da
verdade.

**Escala tipográfica fluida** (das specs) — definir como utilitários/classes para h1–h5/body/small/xs
usando os `clamp()` exatos (ex.: h1 `clamp(2.027rem, 1.3974rem + 2.0146vw, 3.815rem)`, 32→61px;
body fixo 16px; `small`/`xs` têm coeficiente `vw` negativo — proposital, manter). Line-heights:
h1 1.15 · h2 1.2 · h3 1.25 · h4/h5 1.3 · body 1.6.

---

## 4. Componentes globais

### Header (`<x-layout.header>`)

Sticky branco (`top:0; z-index:80`), container `max-width:1240px`, duas faixas.

- **Logo** `logo-horizontal.png` → link `/` (`goHome` vira rota nomeada).
- **Busca** (só desktop): `<form role="search">` pílula (`radius-pill`) + input
  `placeholder="Pesquisar no site…"` + botão circular roxo `#4E4483` com SVG lupa. → **Livewire**
  (rota + query Eloquent/Scout).
- **Entrar / Cadastrar** (só desktop): "Possui uma conta?" + `Entrar` (roxo) + `Cadastrar` (azul
  `#6E9FCB`). Condicional por sessão (Auth nativo Laravel). Mutuamente exclusivo com o hambúrguer
  por breakpoint.
- **Mega-menu desktop** (`<x-nav.mega-menu>`): `<nav style="background:#4E4483">`,
  `<ul>/<li class="cema-navitem">`, dropdown branco com `border-top:3px solid #F2A81E` e box-shadow.
  Abertura por **hover (CSS puro)**; até 3 níveis; `aria-haspopup`, `aria-expanded`,
  `aria-current="page"` + `current-menu-ancestor`.
- **Off-canvas mobile** (`<x-nav.mobile-menu>`): hambúrguer `<button aria-controls aria-expanded>`;
  painel `<aside role="dialog" aria-modal="true">` (300px, `translateX(-100%)` + transição),
  overlay `rgba(38,36,46,0.55)`, botões "Fazer Login / Criar Conta", e cada item do menu como
  **`<details>`/`<summary>` nativo** (acordeão). Fecha por overlay/×/`Esc`; focus-trap. Toggle via
  **Alpine**.

**Os 8 itens de menu (raiz):** `Institucional`, `Palestras`, `Mensagens Mediúnicas`, `Eventos`,
`Vibração`, `Agenda`, `Evangelho`, `Sementeira`.

### Footer (`<x-layout.footer>`)

`background:#2f2952` (token `footer-bg`), texto `#cfc9e4`. Grid
`repeat(auto-fit,minmax(200px,1fr))`. Blocos:
1. **Marca**: `logo-branco.png` + tagline "Centro Espírita Maria Madalena — uma casa de fé,
   estudo e caridade em Planaltina, DF."
2. **Institucional**: Instituição, Nossa História (→ rota), Nosso Blog, Notícias do CEMA.
3. **Atividades**: Vibração Virtual, Palestras Públicas (→ rota), Palestrantes, Evangelho da
   Semana, Agenda Reforma Íntima.
4. **Newsletter**: form ("Inscreva-se") Nome + E-mail + botão dourado **Inscrever** → **Livewire**
   + redes sociais (YouTube/Instagram/Facebook/WhatsApp, `rel="noopener noreferrer"`).
5. **Barra legal**: `<address>` (endereço + CNPJ 01.600.089/0001-90) + "© 2026 CEMA · Todos os
   direitos reservados · Desenvolvido por DECOM".

> As specs detalham uma terceira lista no footer (Minha Conta, Política de Privacidade/Cookies,
> Termos, Contato) e selos FEB/UFE — confirmar com o dono qual versão (protótipo 4-blocos vs specs
> 3-blocos) vai a produção (ver seção 7).

---

## 5. Templates

> **FRONT PÚBLICO** (Blade SSR + Livewire): todas as páginas abaixo. **ADMIN Filament 5**: CRUD de
> Palestras, Palestrantes/Diretores (pivot `palestra_pessoa` com `papel`), Posts, Eventos,
> Mensagens, Agenda — alimenta o front via Eloquent. O Guia de Estilo é página interna de
> referência, **não** vai para produção pública (vira documentação viva / Storybook-like opcional).

### Início — T01 (rota `/`, `pages/inicio.blade.php` + Livewire para countdown/forms)
Hero → S2 Atividades da casa (grid 6 cards) → S3 Próxima palestra + **contagem regressiva** + foto
palestrante → S4 Últimas do blog (1 destaque + 3 lista) → S5 Evangelho da semana + Meta do dia →
S6 Newsletter (faixa roxa) → S7 Parceiros/federações (FEB, UFE, CFN, Livraria).

**Hero — 3 variantes (escolher UMA para produção, ver seção 7):**
- **V0 (default/principal):** roxo `linear-gradient(160deg,#4E4483→#3c3468→#2f2952)`, badge
  "Fé · Estudo · Caridade", H1 "Centro Espírita Maria Madalena", 2 CTAs ("Próxima palestra →"
  dourado, "Conheça o CEMA" outline), partículas. **É o confirmado nos screenshots.**
- **V1:** fundo creme `#F3EDDD`, "Meta do mês" + 3 cards (Deus/Cristo/Caridade).
- **V2:** foto fullbleed com gradiente e CTA.

### Single Palestra — T06 (rota `/palestras/{slug}`)
Hero roxo (ticker desktop, breadcrumb multinível, eyebrow "Palestra Pública", H1, subtítulo, CTA
"Ver calendário") → S2 Barra de ações (Compartilhar: Facebook/WhatsApp/Copiar link + ♥ Curtir) →
S3 Grid 2 colunas: `<aside>` card palestrante (~30%) + coluna principal (~70%) com player vídeo,
cards Data/Local, **acordeão "Principais tópicos abordados"** (`<details>` nativos) e tags → S4
Navegação anterior/próxima → S5 Últimas notícias (3 cards). Adicionar `schema.org/Event`.

### Contato — T05 (rota `/contato`, componente Livewire para o form)
Hero roxo "Contato" (partículas) → S2 Breadcrumb → S3 Intro "Como podemos ajudar?" (imagem +
texto) → S4 Faixa creme `#F3EDDD`: "Nossas redes" (cards com cores reais das marcas — YouTube
vermelho, Instagram gradiente) + `<form>` Nome*, E-mail*, WhatsApp, Assunto (`<select>` 6 opções),
Mensagem (`<textarea>`), botão "Enviar mensagem", popup de confirmação → S5 Cards de info
(Endereço, Horários, CNPJ).

### Nossa História — T03 (rota `/nossa-historia`)
> Atenção: `/quem-somos/` e `/historia/` davam 404 no snapshot; a página viva é `/nossa-historia/`.

Hero roxo "Nossa História · Desde 1991" → S2 Nav lateral fixa de **scrollspy** (só desktop,
`right:26px`, 4 pontos) → S3 Timeline `histBlocks` (4 blocos zigzag texto/imagem alternados, badge
de ano em **verde sálvia**, `data-hist` alvo do scrollspy) → S4 Memórias da casa (carrossel
`scroll-snap`) → S5 Diretoria (tabela: nome, período, cargo).

### Guia de Estilo (rota interna, ex.: `/guia-de-estilo`, não pública)
Hero → Paleta (8 swatches) → Tipografia (amostras Work Sans/Poppins/Roboto Slab) → Botões/Badges/
Tags → Campos de formulário. Serve para validar o `@theme` e os componentes.

---

## 6. Comportamentos/interações → estratégia no stack

| Interação | Técnica recomendada | Justificativa |
|---|---|---|
| **Mega-menu desktop** | CSS puro (hover `:hover`) + `aria-*` | já funciona só com CSS no protótipo; zero JS; A11y via atributos |
| **Off-canvas mobile** | **Alpine.js** (toggle `is-open`, focus-trap, `Esc`) + `<details>` para submenus | precisa de estado/foco; `<details>` mantém acordeão acessível sem JS |
| **Acordeões** (tópicos palestra, evangelho, submenus) | **`<details>`/`<summary>` nativos** | acessível, SSR, zero JS; ícone gira via `details[open]` em CSS |
| **Contagem regressiva** | **Alpine** (timer no cliente; servidor entrega a data da próxima palestra) | só visual; não deve bloquear SSR |
| **Curtir/favoritar** | **Alpine** + `localStorage` (anônimo); **Livewire** + endpoint se logado | persistência opcional; toggle de ícone/cor `#C33A36` |
| **Compartilhar (FB/WhatsApp)** | links `wa.me` / `facebook.com/sharer` + **Web Share API** no mobile (Alpine) | nativo, sem dependências; `rel="noopener"` |
| **Copiar link** | **Alpine** (Clipboard API) | trivial no cliente |
| **Formulário Contato + popup** | **Livewire** (validação, CSRF, rate-limit, FormRequest) + `<dialog>` nativo ou flash | segurança e feedback reais; toast do protótipo vira flash message |
| **Newsletter** | **Livewire** (`submit-type-ajax` → ação Livewire) | idem |
| **Busca do header** | **Livewire** (rota + query) | SSR + estado |
| **Scroll-spy da História** | **Alpine** (`IntersectionObserver`) + `scroll-behavior:smooth` (CSS) | leve, sem lib; atualiza item ativo |
| **Ticker (palestra)** | **Alpine** ou CSS `@keyframes`; **oculto no mobile** | decorativo |
| **Partículas** | CSS `@keyframes` | decoração pura |

Prioridade transversal: **SSR primeiro** (conteúdo renderizado no servidor), Alpine só para
microinterações de cliente, Livewire para qualquer coisa com estado/persistência/segurança. Nada
de React/Babel de CDN.

---

## 7. Divergências handoff × nosso ambiente + DECISÕES PENDENTES do dono do produto

**Divergências de stack (já resolvidas — ignorar a sugestão do handoff):**
- Handoff/specs sugerem **Astro/Next/React/Vue + SSG/headless** → usamos **Blade/Livewire/Filament SSR**.
- Roteamento por estado (`state.route`/`go()`) → **rotas Laravel com URLs** (SEO).
- Andaime `.dc`/`support.js`/React-CDN/Babel → **descartado** integralmente.

**DECISÕES PENDENTES (precisam do dono do produto):**
1. **Qual Hero vai para produção** (V0 roxo / V1 creme / V2 foto). Recomendação técnica: **V0**
   (confirmado nos screenshots e como default). Descartar as outras duas como código.
2. **Confirmar `#F2A81E` como cor oficial da marca** (dourado). Está só no protótipo/README,
   ausente do `tokens.json`. É a omissão mais crítica — bloqueia o `@theme` definitivo.
3. **Footer: 3 blocos (specs) vs 4 blocos (protótipo)** — definir listas finais, incluir ou não a
   coluna "Minha Conta/Políticas/Termos" e selos FEB/UFE.
4. **Placeholders → imagens reais**: cada `<Placeholder label="…"/>` marca posição/proporção de
   uma imagem real (foto de palestrante, capa, fotos históricas). Precisamos dos **assets reais**
   ou da estratégia de migração via REST API.
5. **Ícones**: o original tem dois conjuntos (`huge.*` UI e `hm.*` temáticos). Decisão:
   **substituir tudo por SVG inline** (leve, acessível) — confirmar se há um kit de ícones preferido.
6. **Captcha** do formulário de contato: reCAPTCHA v3 / hCaptcha / **Turnstile** — escolher provedor.
7. **Recriar as 5 logos em SVG** (hoje são PNG) para nitidez/peso — confirmar disponibilidade dos
   vetores originais.
8. **Curtidas**: anônimas (localStorage) ou exigem conta? Define se há tabela/endpoint.

---

## 8. Aproveitar vs descartar

**Aproveitar:**
- **Logos** (`design_handoff_cemanet/prototype/assets/logo-*.png`) → copiar para
  **`public/images/logos/`** (servir estático com cache longo). Se vetorizadas, `resources/` para
  processar via Vite. Há 5: horizontal, branco, ícone, vert, vert-comp.
- **Marcação semântica do protótipo** (Header, mega-menu, off-canvas, Footer, e a ordem de seções
  de T01/T03/T05/T06) → referência para os componentes Blade, **convertendo `style=` inline em
  classes Tailwind v4 dos tokens do `@theme`**.
- **`<details>`/`<summary>`** dos acordeões → manter como HTML nativo.
- **Paleta e tipografia** do Guia de Estilo → validar/completar o `@theme`.
- **Conceito/proporção dos placeholders** (`$preview` 280×180, `min-height:120px`) → definir
  `aspect-ratio` dos slots de `<img>`/`<picture>` para evitar CLS (preservar a intenção, não os
  números mágicos).

**Descartar (andaime, NÃO migrar):**
- Todo o `support.js` (runtime do framework), `Placeholder.dc.html`, tags
  `<x-dc>`/`sc-if`/`sc-for`/`dc-import`/`x-import`/`sc-helmet`, atributos
  `sc-camel-*`/`data-dc-tpl`/`hint-*`.
- React/ReactDOM/Babel carregados de CDN; ponte `postMessage`/`window.__dc*`; bloco
  `data-props`/`$preview`/`tsType`.
- CSS de runtime: `sc-placeholder`, `@keyframes sc-shine`, `sc-interp`, `sc-missing`,
  `ATOMIC_CSS`, `CANVAS_BG`.
- **Roteamento por estado** → rotas Laravel.
- **Troca mobile/desktop por `window.innerWidth<1024`** → markup único responsivo (mobile-first),
  nada de duplicar por viewport (evitar o anti-padrão `elementor-hidden-*`).
- **Barra flutuante "Protótipo"** e **seletor "Hero: 1/2/3"** (ferramentas de demo).
- `soon`/`toastSoon` e textos placeholder ("Seção de exemplo — protótipo CEMA").

---

## 9. Performance, SEO e A11y

**Performance** (o WP atual é pesado: ~0,5 MB HTML/página, 211 CSS ~3,5 MB, huge-icons 166 KB,
roboto.css 99 KB — ficar **bem abaixo**):
- HTML semântico enxuto; CSS único e pequeno (tokens `@theme` + utilitários Tailwind).
- **SVG inline** no lugar de icon fonts.
- Fontes self-hosted via Bunny, só pesos usados; **preload do par crítico** (Work Sans 600 +
  Poppins 400).
- Imagens **webp/avif** com `srcset` + `loading="lazy"` + `width`/`height` (anti-CLS). Embed
  YouTube via fachada (thumbnail clicável) ou `<iframe loading="lazy" title="...">`.
- Cache de aplicação/HTTP + purge ao atualizar conteúdo (conteúdo muda ~1×/semana).

**SEO:**
- Rotas limpas por CPT (`/palestras/{slug}`, `/palestrantes/{slug}`); `<title>`/meta/OpenGraph por
  página; `schema.org/Event` em palestras/eventos; sitemap; **redirects dos slugs antigos** do WP.

**A11y:**
- Menus com `aria-haspopup`/`aria-expanded`/`aria-current`; item pai marcado quando filho ativo.
- Off-canvas e popups `role="dialog" aria-modal` com focus-trap e fechamento por `Esc`.
- Breadcrumbs `<nav><ol>` com `aria-current="page"`; formulários com `<label>` associado e erros
  acessíveis; **foco visível**.
- **Contraste — alerta crítico:** `secondary #6E9FCB` e `accent #89AB98` ficam **abaixo de 4.5:1**
  sobre branco → usar **só em elementos grandes, ícones, fundos ou bordas**, nunca como texto
  pequeno (escurecer se necessário). Corpo `#000`/`#26242e` sobre `#FFF` está ótimo.

---

## 10. Próximos passos sugeridos (Fase 1 — Palestras)

1. **Fechar o `@theme`** em `resources/css/app.css` aplicando o bloco da seção 3.2 (adicionar
   `gold`, `footer-bg`, `text-ink`, `font-mono`, `desktop-sm`; renomear `neutral-*` →
   `surface/border/border-muted/text-secondary/text-muted`) e atualizar usos existentes. Espelhar
   em `tokens.json`. **Bloqueado pela decisão #2** (confirmar dourado).
2. **Configurar fontes via Bunny** (`laravel-vite-plugin/fonts`): Work Sans 400/600, Poppins 400,
   Roboto Mono 400/500 (+ Roboto/Roboto Slab se mantidos). Preload do par crítico.
3. **Mover logos** para `public/images/logos/` e definir favicon (`logo-icone`).
4. **Layout base** `<x-layout.app>` + **Header** (`<x-layout.header>`, `<x-nav.mega-menu>`,
   `<x-nav.mobile-menu>` com Alpine) + **Footer** (`<x-layout.footer>`) — componentes globais
   primeiro, pois servem todas as páginas.
5. **Página Guia de Estilo** interna como sandbox dos tokens/componentes base (botões, badges,
   tags, cards, campos) — valida o `@theme` antes de escalar.
6. **Vertical slice Palestras** (alinhado ao CLAUDE.md): migrations/seeders (já há pivot
   `palestra_pessoa`), recurso Filament (admin), depois as **páginas públicas**:
   - Listagem `/palestras` (cards de palestra, badge "Em Breve"+countdown).
   - **Single Palestra `/palestras/{slug}` (T06)** como referência — implementa hero, barra de
     ações (Alpine: compartilhar/copiar/curtir), acordeão `<details>`, anterior/próxima,
     `schema.org/Event`.
   - Card "Próxima palestra" + contagem regressiva (Alpine) reusado na Home.
7. **Importação idempotente** (upsert por slug) via comando que consome a REST API do WP (somente
   GET) para popular o MySQL — substitui os placeholders por imagens/dados reais.
8. **Home (T01)** com Hero V0 (após decisão #1), reusando os componentes de palestra já prontos.
9. **Verificar de verdade**: `php artisan test` + abrir no `localhost` (mobile/tablet/desktop),
   checar contraste, peso de HTML e CLS.

**Arquivos-chave de referência:**
- `design_handoff_cemanet/prototype/CEMA Site.dc.html` (marcação + lógica embutida)
- `design_handoff_cemanet/prototype/assets/` (5 logos)
- `design_handoff_cemanet/screenshots/` (6 PNGs, recortes do topo)
- `design-system/` (`design-system.md`, `tokens.json`, `componentes.md`, `paginas.md`)
- `resources/css/app.css` (`@theme` atual — alvo da atualização)
