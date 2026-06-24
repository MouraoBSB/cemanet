# Design System — CEMA (cemanet.org.br)

> Documento-guia consolidado a partir dos artefatos extraídos do site **cemanet.org.br**
> (CEMA – Centro Espírita Maria Madalena), hoje em WordPress + Elementor + Jet.
> Serve de ponto de partida para **reconstruir o site SEM WordPress**.
>
> Fontes deste guia (mesma pasta): `tokens.json`, `componentes.md`, `paginas.md`, `_sinais.json`.

---

## 1. Visão geral / Marca

O **CEMA — Centro Espírita Maria Madalena** é uma instituição espírita sediada em
Planaltina, Brasília-DF (Quadra 02, Lote 16, Vila Vicentina; CNPJ 01.600.089/0001-90).
O site reúne palestras públicas, mensagens mediúnicas, estudo do Evangelho, agenda de
reforma íntima, eventos e um blog ("Sementeira de Luz").

**Identidade visual:** serena e acolhedora. O **roxo institucional** (`#4E4483`) é a cor
âncora — títulos, header e botões primários — apoiado por um **azul** (`#6E9FCB`) para
links e interações e por um **verde suave** (`#89AB98`) como acento. Tons de **creme**
(`#F3EDDD`) e cinzas claros dão respiro às seções.

**Tom da marca:** institucional, acolhedor, espiritual e sereno. A reconstrução deve
preservar essa leveza: muito espaço em branco, hierarquia tipográfica clara, contraste
adequado e nada de elementos agressivos.

---

## 2. Paleta de cores

Extraída de `tokens.json`. Os hexes coincidem com as variáveis globais do Elementor
(`--e-global-color-*` em `_sinais.json`), o que confirma que são as cores oficiais da marca
— e não ruído de blocos genéricos do WordPress.

| Token            | Hex       | Papel                                                              |
|------------------|-----------|-------------------------------------------------------------------|
| `primary`        | `#4E4483` | Cor principal · títulos · header · botões primários               |
| `secondary`      | `#6E9FCB` | Azul de apoio · destaques · links · elementos interativos         |
| `accent`         | `#89AB98` | Verde suave · acentos · ícones · destaque secundário              |
| `orange`         | `#E79048` | Alerta · chamada de atenção · badges                              |
| `danger`         | `#C33A36` | Erro · aviso crítico                                              |
| `success`        | `#008000` | Confirmação · sucesso                                            |
| `text`           | `#000000` | Texto padrão                                                     |
| `neutral-dark`   | `#414141` | Texto secundário · subtítulos                                    |
| `neutral-gray`   | `#7A8A8A` | Texto de apoio · metadados · legendas                            |
| `cream`          | `#F3EDDD` | Fundo suave · seções alternadas · cartões                        |
| `neutral-light`  | `#F6F6F6` | Fundo de seções · backgrounds alternativos                       |
| `neutral-border` | `#E4E4E4` | Bordas · divisores                                              |
| `neutral-muted`  | `#EBE8E8` | Bordas suaves · separadores                                      |
| `white`          | `#FFFFFF` | Fundo padrão · texto sobre fundos escuros                        |

> **Atenção (a11y):** o texto-corpo usa `#000` sobre `#FFF` (contraste ótimo). Verificar
> contraste do `secondary` (#6E9FCB) e do `accent` (#89AB98) quando usados como **texto**
> sobre branco — ambos ficam abaixo de 4.5:1; reserve-os para elementos grandes, ícones,
> fundos ou bordas, e escureça quando precisarem servir de texto pequeno.

### CSS custom properties — cole no `:root`

```css
:root {
  /* Marca */
  --cor-primary:        #4E4483;
  --cor-secondary:      #6E9FCB;
  --cor-accent:         #89AB98;

  /* Estados / feedback */
  --cor-orange:         #E79048;
  --cor-danger:         #C33A36;
  --cor-success:        #008000;

  /* Texto */
  --cor-text:           #000000;
  --cor-text-secondary: #414141;
  --cor-text-muted:     #7A8A8A;

  /* Superfícies e neutros */
  --cor-cream:          #F3EDDD;
  --cor-surface:        #F6F6F6;
  --cor-border:         #E4E4E4;
  --cor-border-soft:    #EBE8E8;
  --cor-white:          #FFFFFF;
}
```

---

## 3. Tipografia

### Famílias

| Família         | Uso                                                  | Pesos          |
|-----------------|------------------------------------------------------|----------------|
| **Work Sans**   | Títulos (h1–h3)                                       | 400, 600       |
| **Poppins**     | Corpo · subtítulos · texto corrido · small · h4/h5   | 400            |
| **Roboto**      | Interface · botões · labels (defaults do tema)       | 400, 500, 600  |
| **Roboto Slab** | Citações · destaques editoriais                      | 400            |

> **Pegadinha herdada do Elementor:** em `_sinais.json`, as variáveis "globais" de
> tipografia (`--e-global-typography-primary/secondary/text/accent`) apontam para
> **Roboto / Roboto Slab** — esses são os *defaults do tema*, aplicados a botões e widgets
> Elementor. A **escala real de títulos e corpo**, porém, usa **Work Sans** (h1–h3) e
> **Poppins** (h4–h5, corpo), conforme `tokens.json`. Na reconstrução, adote **Work Sans +
> Poppins** como par tipográfico principal e mantenha **Roboto** apenas como família de
> apoio para UI/botões (ou unifique tudo em Poppins/Work Sans para reduzir downloads).

### Carregamento (Google Fonts)

Todas as famílias são do Google Fonts (no WP atual vêm de `uploads/elementor/google-fonts/`,
ex.: `roboto.css` com ~99 KB). Para reduzir peso, carregue **só os pesos usados** e
prefira `font-display: swap` + `preconnect`:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
  href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;600&family=Poppins:wght@400&family=Roboto:wght@400;500;600&display=swap"
  rel="stylesheet">
```

> **Performance:** o ideal é **self-host** das fontes (`.woff2`) com `preload` do peso
> crítico (Work Sans 600 + Poppins 400). Considere dispensar Roboto/Roboto Slab se o
> projeto novo padronizar UI em Poppins.

### Escala tipográfica fluida

Todos os tamanhos usam `clamp(mín, fluido, máx)` — escalam suavemente com a viewport,
sem media queries. Direto de `tokens.json`:

| Nível      | Fonte / Peso         | `clamp()`                                             | ≈ mín → máx     |
|------------|----------------------|-------------------------------------------------------|-----------------|
| `h1`       | Work Sans / 600      | `clamp(2.027rem, 1.3974rem + 2.0146vw, 3.815rem)`     | 32 → 61 px      |
| `h2`       | Work Sans / 400      | `clamp(1.802rem, 1.3619rem + 1.4085vw, 3.052rem)`     | 29 → 49 px      |
| `h3`       | Work Sans / 400      | `clamp(1.602rem, 1.3066rem + 0.9454vw, 2.441rem)`     | 26 → 39 px      |
| `h4`       | Poppins / 400        | `clamp(1.424rem, 1.3751rem + 0.1566vw, 1.563rem)`     | 23 → 25 px      |
| `h5`       | Poppins / 400        | `clamp(1.266rem, 1.1614rem + 0.3346vw, 1.563rem)`     | 20 → 25 px      |
| `body-lg`  | Poppins / 400        | `clamp(1.125rem, 1.081rem + 0.1408vw, 1.25rem)`       | 18 → 20 px      |
| `body`     | Poppins / 400        | `clamp(1rem, 1rem + 0vw, 1rem)`                       | 16 px (fixo)    |
| `small`    | Poppins / 400        | `clamp(0.889rem, 0.9203rem + -0.1003vw, 0.8rem)`      | 14 → 13 px      |
| `xs`       | Poppins / 400        | `clamp(0.79rem, 0.8428rem + -0.169vw, 0.64rem)`       | 13 → 10 px      |

> `small` e `xs` têm coeficiente `vw` negativo: **encolhem** conforme a tela cresce
> (legendas mais discretas no desktop). Mantenha — é proposital.

### CSS custom properties — escala

```css
:root {
  /* Famílias */
  --fonte-titulo: "Work Sans", system-ui, sans-serif;
  --fonte-corpo:  "Poppins", system-ui, sans-serif;
  --fonte-ui:     "Roboto", system-ui, sans-serif;     /* botões/labels (apoio) */
  --fonte-quote:  "Roboto Slab", Georgia, serif;        /* citações */

  /* Escala fluida */
  --fs-h1:      clamp(2.027rem, 1.3974rem + 2.0146vw, 3.815rem);
  --fs-h2:      clamp(1.802rem, 1.3619rem + 1.4085vw, 3.052rem);
  --fs-h3:      clamp(1.602rem, 1.3066rem + 0.9454vw, 2.441rem);
  --fs-h4:      clamp(1.424rem, 1.3751rem + 0.1566vw, 1.563rem);
  --fs-h5:      clamp(1.266rem, 1.1614rem + 0.3346vw, 1.563rem);
  --fs-body-lg: clamp(1.125rem, 1.081rem + 0.1408vw, 1.25rem);
  --fs-body:    1rem;
  --fs-small:   clamp(0.889rem, 0.9203rem + -0.1003vw, 0.8rem);
  --fs-xs:      clamp(0.79rem, 0.8428rem + -0.169vw, 0.64rem);

  /* Pesos */
  --peso-regular: 400;
  --peso-medio:   500;
  --peso-semi:    600;
}

h1 { font: var(--peso-semi) var(--fs-h1)/1.15 var(--fonte-titulo); }
h2 { font: var(--peso-regular) var(--fs-h2)/1.2 var(--fonte-titulo); }
h3 { font: var(--peso-regular) var(--fs-h3)/1.25 var(--fonte-titulo); }
h4 { font: var(--peso-regular) var(--fs-h4)/1.3 var(--fonte-corpo); }
h5 { font: var(--peso-regular) var(--fs-h5)/1.3 var(--fonte-corpo); }
body { font: var(--peso-regular) var(--fs-body)/1.6 var(--fonte-corpo); color: var(--cor-text); }
small { font-size: var(--fs-small); }
```

---

## 4. Espaçamento, breakpoints, raios e sombras

### Espaçamento

A escala bruta de `tokens.json` mistura px e rem. Os **valores rem grandes** (9.13 / 6.63 /
5.38 / 5 / 3.75 rem) governam o **respiro vertical entre seções**; os **px pequenos** (2–30)
governam gaps e paddings internos de componentes. Proposta de escala enxuta para a
reconstrução (mantendo os pontos mais recorrentes em `_sinais.json` — 10px, 20px, 24px,
1rem, 1.5rem, 2.5rem):

```css
:root {
  /* Espaçamento interno de componentes (px → rem) */
  --space-2xs: 0.25rem;  /*  4px */
  --space-xs:  0.5rem;   /*  8px */
  --space-sm:  0.625rem; /* 10px */
  --space-md:  1rem;     /* 16px */
  --space-lg:  1.5rem;   /* 24px */
  --space-xl:  2.5rem;   /* 40px */

  /* Respiro vertical entre seções (escala "section-*" do tema) */
  --section-xxs: 1.25rem;
  --section-s:   2.5rem;
  --section-m:   3.75rem;
  --section-l:   5.38rem;
  --section-xxl: 6.63rem;
  --section-hero: 9.13rem;
}
```

> O tema usa classes utilitárias de seção em ordem decrescente de padding vertical:
> `section-hero` > `section-xxl` > `section-l` > `section-m` > `section-s` > `section-xxs`,
> mais `section-narrow` / `section-narrow-xs` para **largura máxima** restrita do conteúdo.
> Mapeie essas classes para os tokens `--section-*` acima.

### Breakpoints

Consolidados de `tokens.json` + `_sinais.json` (os mais frequentes no CSS são **767px** e
**1024px**, herança do Elementor). Para a reconstrução, **mobile-first**, sugere-se este
conjunto principal:

| Nome         | Largura  | Uso típico                                         |
|--------------|----------|----------------------------------------------------|
| `mobile-sm`  | 480 px   | Ajustes em telas muito estreitas                   |
| `mobile`     | 600 px   | Limite superior de mobile                          |
| `tablet`     | 768 px   | Tablet retrato (Elementor usa 767/768)             |
| `desktop-sm` | 1024 px  | Tablet paisagem / desktop pequeno                  |
| `desktop`    | 1200 px  | Desktop padrão (largura de container)              |
| `desktop-lg` | 1440 px  | Telas largas                                       |

```css
/* mobile-first: estilos base = mobile; min-width sobe a partir daí */
@media (min-width: 600px)  { /* mobile largo */ }
@media (min-width: 768px)  { /* tablet */ }
@media (min-width: 1024px) { /* desktop pequeno */ }
@media (min-width: 1200px) { /* desktop */ }
@media (min-width: 1440px) { /* desktop largo */ }
```

> Há ainda 650px (tablet-sm) no CSS original; trate como ajuste pontual, não como
> breakpoint estrutural.

### Raios de borda

Recorrentes (px): `4`, `6`, `10`, `15`, `16`, `20`, `36`, `50` + `50%`/`100%` para
círculos (ícones, avatares). Proposta:

```css
:root {
  --raio-sm:    4px;
  --raio-md:    10px;
  --raio-lg:    20px;
  --raio-pill:  50px;   /* botões/badges em pílula */
  --raio-full:  9999px; /* círculo (avatar, ícone) */
}
```

### Sombras

Inventário curto (`tokens.json`); as `inset 0 0 0 1px ...` de `_sinais.json` são
**falsas-bordas** de botões sociais — trate como borda, não sombra.

```css
:root {
  --sombra-none: none;
  --sombra-sm:   0 2px 8px rgba(0,0,0,0.08);  /* cards em repouso */
  --sombra-md:   0 4px 16px rgba(0,0,0,0.12); /* hover / elevação */
  --borda-1px:   inset 0 0 0 1px var(--cor-border); /* contorno sutil */
}
```

---

## 5. Componentes

Resumo dos principais. **Detalhe completo (estrutura HTML observada + notas de
reconstrução) em `componentes.md`.**

| Componente | Resumo | Ref. |
|---|---|---|
| **Header / cabeçalho** | Duas faixas: barra utilitária (logo · busca · login/cadastro no desktop · hambúrguer no mobile) + barra de menu desktop. | §1, §2 |
| **Busca** | Campo no header (`jet-search`) → reconstruir como `<form role="search">` ligado a índice de busca. | §1.1 |
| **Auth-links** | Par "Entrar / Cadastrar" condicional por estado de sessão. | §1.1 |
| **Mega-menu** | Nav horizontal com dropdowns de até **3 níveis** (8 itens raiz). Hover no desktop, clique/touch no mobile. | §1.2, §2 |
| **Off-canvas mobile** | Painel lateral deslizante (`<aside role="dialog" aria-modal>`) com logo, auth e menu vertical espelhando o desktop. | §1.3 |
| **Hero** | Seção de destaque com H1 dinâmico, subtítulo, CTA; efeito de partículas em páginas institucionais. | §3 |
| **Card "Próxima Palestra"** | Card dinâmico com contagem regressiva, divisor SVG, foto do palestrante e CTA "Ver Palestra". | §4 |
| **Cards de seção (HappyAddons)** | Grid de 6 cards `ha-card` (figura + título + texto); variante com badge no canto. | §5, §6 |
| **Smart Listing** | Layout "1 destaque + lista de 3" para últimas notícias do blog. | §7 |
| **Galeria "Quem Somos"** | Galeria com overlay animado (desktop) / imagem única (mobile). | §8 |
| **Text Ticker** | Faixa de manchetes com efeito de digitação; oculto no mobile. | §9 |
| **Single de palestra** | Duas colunas: card do palestrante + (embed YouTube, tema, data, local, acordeão, taxonomia). | §10 |
| **Barra de ações** | Compartilhar (WhatsApp/Facebook), copiar link, curtir/favoritar. | §11 |
| **Navegação ant./próx.** | Links para palestra anterior e seguinte (`rel="prev"`/`rel="next"`). | §12 |
| **Breadcrumbs** | Trilha `Início › … › atual`; reconstruir com `<nav aria-label="Breadcrumb"><ol>`. | §13 |
| **Acordeão** | Tópicos abordados / sub-trechos; reconstruir com `<details>/<summary>` nativos. | §10, §7 |
| **Formulários** | Contato (5 campos + select de assunto + WYSIWYG) e Newsletter (nome + e-mail). | §14 |
| **Popups** | Confirmações, lightbox de imagem, loaders; reconstruir com `<dialog>` nativo. | §15 |
| **Calendário** | Calendário mensal interativo na Agenda Reforma Íntima (grade tabular, prev/next). | §10/T10 |
| **Banner de cookies** | Complianz (LGPD); 4 categorias; substituir por solução dedicada. | §17 |
| **Rodapé** | 3 seções: navegação em listas + newsletter + redes sociais/selos + barra legal (endereço, CNPJ, copyright, créditos DECOM). | §16 |

**Ícones:** dois conjuntos — `huge.*` (UI geral) e `hm.*` (temáticos). Na reconstrução,
substituir por **SVGs inline** (acessíveis, leves) ou um sprite único.

---

## 6. Templates de página

Lista dos templates mapeados em `paginas.md` (ver lá o detalhe seção a seção):

| Template | Descrição (1 frase) |
|---|---|
| **T01 — Home** | Hero institucional + grade de cards de seções + próxima palestra + últimas notícias + evangelho da semana + agenda + palestrantes + newsletter + parceiros. |
| **T03 — Nossa História** | Página narrativa com hero, navegação por âncoras lateral, blocos texto/imagem alternados, carrossel histórico e tabela da diretoria. |
| **T05 — Contato** | Hero com partículas, breadcrumb, introdução com imagem, redes sociais + formulário de contato (com popup de confirmação) e dados da sede. |
| **T06 — Single Palestra Pública** | Hero com ticker/breadcrumb, barra de compartilhamento, card do palestrante + player YouTube + acordeão de tópicos, taxonomia, navegação ant./próx. e smart listing. |
| **T07 — Single Evangelho da Semana** | Título do trecho, localização no livro (parte/capítulo/item), conteúdo em acordeão por sub-tópico e link "Voltar para escala". |
| **T08 — Single Palestrante** | Hero com nome dinâmico, foto em destaque e lista/carrossel das palestras do palestrante. |
| **T09 — Single Evento** | Hero com taxonomia, metadados (data/local), barra de interação, imagem principal e grid de mensagens relacionadas. |
| **T10 — Agenda Reforma Íntima** | Hero com partículas + duas colunas: conteúdo do dia (reflexão, metas, prece, compartilhar) e calendário mensal interativo. |

> **Globais a todos os templates:** cabeçalho, rodapé, banner de cookies e popups Jet.
> Ver `paginas.md` → "Elementos globais".
>
> **404:** T02 (Quem Somos, `/quem-somos/`) e T04 (História, `/historia/`) responderam
> erro 404 no snapshot — a página institucional viva é **`/nossa-historia/`** (T03).

---

## 7. Modelo de conteúdo

Hoje o conteúdo vive em **Custom Post Types (CPTs)** + **taxonomia** geridos pelo
WordPress/JetEngine. Os tipos identificados:

| CPT / taxonomia | Conteúdo |
|---|---|
| `palestra_publica` | Palestras públicas (tema, palestrante, data, YouTube, público, tópicos). |
| `palestrantes` | Palestrantes e diretores (foto, nome, biografia, relação com palestras). |
| `evangelho` | Trechos do Evangelho da semana (parte/capítulo/item, sub-tópicos). |
| `mensagem-mediunicas` | Mensagens mediúnicas (título, data, tipo/contexto, avaliação). |
| `_evento` | Eventos (data, local, taxonomia, imagem, mensagens relacionadas). |
| `agenda-reforma` | Itens diários da Agenda Reforma Íntima (reflexão, metas, prece). |
| `autores-espirituais` | Autores espirituais vinculados às mensagens. |
| **tax. `assuntos-principais`** | Taxonomia hierárquica (~140 termos) usada nas palestras. |

**Numa reconstrução sem WordPress**, esses CPTs viram **coleções de dados** servidas por
um de três caminhos:

1. **Headless CMS** (Strapi, Directus, Payload, Sanity, Keystone) — bom se a equipe
   precisa editar conteúdo por painel. Cada CPT vira um *content type*.
2. **Dados estáticos / SSG** (arquivos Markdown/JSON + frontmatter, no padrão deste repo
   em `conteudo-palestras/`) — ótimo para conteúdo versionado em git e build estático.
3. **Back-end próprio + API REST/GraphQL** — se já houver banco/serviço, expor endpoints
   `GET /palestras`, `GET /palestrantes/{slug}`, etc.

> As relações do JetEngine (palestrante↔palestra, diretor↔palestra) viram **referências
> por slug/id** entre coleções. A contagem regressiva, "meta do mês", "prece do dia" etc.
> são campos dinâmicos resolvidos no build (SSG) ou no cliente (fetch).

---

## 8. Recomendações para reconstrução sem WordPress

### Stack sugerida

- **Framework:** **Astro** (ideal — SSG/ilhas, HTML mínimo, zero-JS por padrão) ou
  **Next.js** (se precisar de mais interatividade/SSR e área logada).
- **Estilo:** **CSS Modules** ou **Tailwind**, em ambos os casos **alimentados pelos tokens
  acima como CSS custom properties** no `:root` (fonte única da verdade). Evitar
  reintroduzir um page-builder.
- **Conteúdo:** **SSG** para palestras, palestrantes, evangelho, eventos e agenda
  (conteúdo majoritariamente estático, muda 1×/semana). Build incremental quando o CMS
  atualizar.

### Substitutos diretos (WP/Jet → web nativa)

| Recurso atual (Jet/Elementor) | Substituto sem WordPress |
|---|---|
| **JetFormBuilder** (contato, newsletter) | `<form>` HTML5 → `fetch` para endpoint serverless (function/edge) ou serviço (Formspree, Resend). Máscara via IMask.js; captcha via reCAPTCHA v3 / hCaptcha / Turnstile. |
| **jet-popup** | Elemento **`<dialog>` nativo** (`showModal()`/`close()`, `::backdrop`, fecha no `Esc`, com focus-trap). |
| **jet-listing-calendar** | Componente de calendário próprio (grade `<table>`/CSS grid) ou lib leve; eventos vindos da coleção `agenda-reforma`/`_evento`. |
| **jet-search** | Índice de busca estático (Pagefind, Lunr, Fuse.js) ou **Algolia/Typesense** se o volume crescer. |
| **jet-listing-grid / dynamic-field** | Componentes de listagem/card alimentados no build (SSG) ou por fetch à API. |
| **jet-blog-text-ticker** | Typed.js (efeito de digitação) ou CSS `@keyframes`; ocultar no mobile. |
| **jet-data-store-button** (curtir/favoritar) | Estado em `localStorage` (anônimo) ou via API quando houver conta. |
| **Área de membros / `nivel-de-acesso`** | **Autenticação** (Auth.js/NextAuth, Clerk, Supabase Auth) + rotas/conteúdo protegidos. |
| **Complianz (cookies)** | Cookie Consent (orestbida), Tarteaucitron ou implementação própria com `localStorage` (LGPD). |
| **Compartilhamento social** | Links `wa.me` / `facebook.com/sharer` + Web Share API (`navigator.share`) no mobile. |
| **Embed YouTube** | `<iframe loading="lazy">` com `title` acessível, ou fachada (thumbnail clicável) para performance. |

### Pontos de atenção

- **Performance (crítico):** o WP atual é **muito pesado** — `_sinais.json` aponta **~0,5 MB
  de HTML por página** e **211 arquivos CSS (~3,5 MB)**, incluindo `huge-icons` (166 KB) e
  `roboto.css` (99 KB). A reconstrução deve cortar isso drasticamente: HTML semântico enxuto,
  CSS único e pequeno (tokens + utilitários), **SVGs no lugar de icon fonts**, fontes
  self-hosted só nos pesos usados, imagens em `webp/avif` com `srcset` e `loading="lazy"`.
- **Mobile-first:** o site original duplica markup por viewport (`elementor-hidden-*`).
  Substituir por **um só markup responsivo** com CSS (grid/flex + `clamp()` já cobre boa
  parte da fluidez), evitando elementos duplicados.
- **Acessibilidade (A11y):** menus com `aria-haspopup`/`aria-expanded` e `aria-current`;
  off-canvas e popups como `role="dialog" aria-modal` com **focus-trap** e fechamento por
  `Esc`; breadcrumbs em `<nav><ol>` com `aria-current="page"`; formulários com `<label>`
  associados e mensagens de erro acessíveis; foco visível; contraste conforme §2.
- **SEO:** rotas limpas por CPT (`/palestras/{slug}`, `/palestrantes/{slug}`), HTML
  renderizado no servidor/build, `<title>`/meta/OpenGraph por página, dados estruturados
  (`schema.org/Event` para palestras/eventos), sitemap e redirecionamento dos slugs antigos.
- **Cache:** com SSG, servir via CDN com cache longo + invalidação no rebuild; revalidação
  incremental quando o conteúdo muda.

---

## 9. Ressalvas

- **Snapshot representativo, não exaustivo:** o material em `snapshot/` cobre **1–2 páginas
  por template** (ver `html_representativos` em `_sinais.json`). Outras variações de layout
  podem existir e não foram capturadas.
- **404 em `/quem-somos/` e `/historia/`:** ambas responderam **erro 404** no snapshot
  (template de erro `elementor-25642`). A página institucional viva e mapeável é
  **`/nossa-historia/`** — use-a como referência da seção "história".
- **Conteúdo restrito a membros não capturado:** áreas protegidas por login
  (`nivel-de-acesso`) ficaram fora do snapshot; seu layout/comportamento precisará ser
  levantado à parte na reconstrução.
- **Defaults do Elementor podem mascarar a marca:** algumas cores/fontes "frequentes" em
  `_sinais.json` (ex.: paleta padrão de blocos do Gutenberg: `#ff6900`, `#9b51e0` etc.) são
  **ruído de plugins**, não a identidade do CEMA. As cores e fontes oficiais são as de
  `tokens.json` (consolidadas nas §2 e §3).
- **Mapa de cardinalidade do domínio:** regras de negócio das palestras (1–2 palestrantes,
  0–1 diretor, datas em domingo etc.) estão no `CLAUDE.md`/`MEMORY.md` do repositório de
  conteúdo, não neste guia de UI.
