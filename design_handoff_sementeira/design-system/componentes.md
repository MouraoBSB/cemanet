# Inventário de componentes

> Levantado a partir dos esqueletos estruturais (HTML limpo) das páginas representativas do cemanet.org.br (CEMA – Centro Espírita Maria Madalena), hoje em WordPress + Elementor + Jet. Cada item traz a estrutura observada e notas para reconstruir **sem** WordPress.

## 1. Cabeçalho (Header)

**Widget Elementor:** `elementor-location-header` (ID `elementor-21493`)
**Páginas:** todas (Home, Contato, Quem Somos, Single de Palestra)

O cabeçalho é dividido em duas faixas sobrepostas verticalmente:

### 1.1 Faixa superior (barra utilitária)

`div.elementor-element-703790e.e-flex.e-con-boxed`

| Coluna | Conteúdo | Widget Jet/Elementor |
|---|---|---|
| Esquerda | Logo com link | `elementor-widget-image` |
| Centro | Campo de busca | `elementor-widget-jet-search` |
| Direita (desktop only) | Links de login e cadastro | `elementor-widget-jet-auth-links` |
| Direita (mobile/tablet) | Ícone de hambúrguer (`i.huge.huge-menu-07`) | `elementor-widget-icon` |

**Classes de responsividade observadas:** `elementor-hidden-tablet`, `elementor-hidden-mobile`, `elementor-hidden-desktop` — a coluna de auth-links e a coluna do ícone hambúrguer são mutuamente exclusivas por breakpoint.

**Reconstrução sem WP/Elementor:**
- Logo: `<a href="/"><img src="logo.svg" alt="CEMA"></a>`
- Busca: `<form role="search"><input type="search" placeholder="Pesquisar…"><button type="submit"></button></form>` — integrar a um endpoint de busca (JSON API, Algolia, etc.)
- Auth-links: par de links condicionais (`<a>Entrar</a>` / `<a>Cadastrar</a>`) mostrados/ocultados via estado de sessão no cliente
- Hambúrguer: `<button aria-controls="menu-mobile" aria-expanded="false">` — controla o off-canvas via JS

### 1.2 Faixa inferior — barra de menu desktop

`header.elementor-element-596622a.elementor-hidden-tablet.elementor-hidden-mobile`
Widget: `elementor-widget-jet-nav-menu`

Menu horizontal com mega-dropdown de até 3 níveis de profundidade. Estrutura observada:

```
nav.jet-nav-wrap
  div.jet-nav__mobile-trigger   ← par open/close de ícones
  div.menu-menu-principal-container
    div.jet-nav.jet-nav--horizontal
      div.menu-item.menu-item-has-children.jet-nav__item   ← item raiz com submenu
        a.menu-item-link.menu-item-link-top
          span.jet-nav-link-text
          div.jet-nav-arrow
        div.jet-nav__sub.jet-nav-depth-0    ← dropdown nível 1
          div.menu-item.jet-nav-item-sub
            a.menu-item-link.menu-item-link-sub
              span.jet-nav-link-text
              div.jet-nav-arrow              ← quando há nível 2
            div.jet-nav__sub.jet-nav-depth-1  ← dropdown nível 2
```

**Itens de menu raiz observados:** Institucional, Palestras, Mensagens Mediúnicas, Eventos, Vibração, Agenda, Evangelho, Sementeira.

**Reconstrução sem WP/Elementor:**
- `<nav>` com `<ul>/<li>` semânticos padrão
- Submenus como `<ul class="submenu">` posicionados absolutamente (CSS)
- Abertura por hover no desktop (`:hover + .submenu`) e por clique/touch no mobile
- `aria-haspopup="true"`, `aria-expanded` nos itens pai para acessibilidade
- O item ativo usa `current-menu-item` / `current-menu-ancestor` no original — mapear para classe `active` gerada pelo roteador da aplicação

### 1.3 Off-canvas (menu mobile)

`div#off-canvas-c60c42a.e-off-canvas`
Widget: `elementor-widget-off-canvas`

Painel lateral que desliza da esquerda. Contém:
- Botão fechar (`a.elementor-icon`)
- Logo
- Botões "Fazer Login?" e "Criar Conta?" (`elementor-widget-button`) com ícones `huge.huge-login-circle-01` e `huge.huge-user-unlock-01`
- Título "Menu" (`elementor-widget-heading`)
- Menu vertical completo espelhando o menu desktop (`elementor-nav-menu--layout-vertical`, `ul.elementor-nav-menu.sm-vertical`)

O menu mobile repete a estrutura duas vezes: uma `nav.elementor-nav-menu--main` (versão estática) e uma `nav.elementor-nav-menu--dropdown` (versão expandida pelo toggle). O `div.elementor-menu-toggle` fica entre elas.

**Reconstrução sem WP/Elementor:**
- `<aside id="menu-mobile" role="dialog" aria-modal="true">` com `position: fixed`, `transform: translateX(-100%)` e transição CSS
- Fechar pelo overlay (`div.e-off-canvas__overlay`) ou pelo botão interno
- Dentro: logo, botões de auth, `<nav>` com `<ul>` vertical idêntico ao desktop
- JS: toggle `aria-expanded` + classe `is-open` no elemento; foco aprisionado enquanto aberto (a11y)

---

## 2. Navegação / Menu — itens e estrutura

**Profundidade máxima observada:** 3 níveis (raiz → submenu → sub-submenu), ex.: Sementeira → Blog Espírita → Reflexões e Espiritualidade.

| Nível | Classe original | Equivalente HTML/CSS |
|---|---|---|
| Raiz | `.menu-item-link-top` / `.elementor-item` | `<a>` no `<li>` direto do `<ul>` principal |
| Nível 1 | `.menu-item-link-sub` / `.elementor-sub-item` | `<a>` em `<ul class="submenu">` |
| Nível 2 | `.menu-item-link-depth-2` | `<a>` em `<ul class="submenu submenu--deep">` |
| Item ativo | `.current-menu-item` / `.elementor-item-active` | classe `active` gerada pelo roteador |

**Indicador de item atual:** `current-menu-ancestor` marca o pai quando um filho está ativo — importante replicar na lógica de roteamento para marcar o item pai com `aria-current="page"`.

---

## 3. Hero / Seção de destaque

**Página:** Home (`section.elementor-element-c7b07a0.section-hero`)

Seção de fundo (provavelmente imagem ou cor via CSS do Elementor) com dois painéis lado a lado:

- **Painel esquerdo:** `h1` com nome do CEMA + widget `jet-listing-grid` exibindo a "Meta do Mês" (campo dinâmico do CPT via `jet-listing-dynamic-field`)
- **Painel direito:** três botões CTA (`elementor-widget-button`, `elementor-size-sm`) com os textos "Deus", "Cristo" e "Caridade"

**Reconstrução sem WP/Elementor:**
- `<section class="hero">` com imagem de fundo CSS (`background-image`)
- `<h1>` estático + bloco de "meta do mês" servido pelo back-end (endpoint REST ou SSG)
- Três `<a class="btn">` ou `<button>` para CTAs
- Sem slider/carousel — é uma seção fixa simples

---

## 4. Card de "Próxima Palestra" (destaque dinâmico)

**Página:** Home (`div.elementor-element-bd41a4d`)
**Widget:** `elementor-widget-jet-listing-grid` (template `25114`)

Exibe a palestra mais próxima como card de destaque com:
- Badge com contagem regressiva (`«Faltam 4 horas»`) — campo dinâmico posicionado como `elementor-absolute`
- `elementor-shape-bottom` (divisor SVG decorativo)
- **H2** "Palestra Pública" + campos dinâmicos: tema, palestrante, data
- Imagem do palestrante (`jet-listing-dynamic-image`)
- Logo do CEMA sobreposto (`elementor-widget-image`)
- Excerpta (campo meta `A escola de nossas almas`)
- Botão CTA "Ver Palestra" (`elementor-animation-grow`)

**Reconstrução sem WP/Elementor:**
- Componente `<ProximaPalestra>` alimentado por endpoint REST `GET /palestras?proxima=1`
- Badge de contagem regressiva: JS (`setInterval` a cada segundo calculando diferença para `data_da_palestra`)
- Divisor SVG: elemento `<div class="shape-divider">` com SVG inline ou CSS clip-path
- CTA: `<a href="/palestras/{slug}" class="btn btn--grow">Ver Palestra</a>`

---

## 5. Grid de categorias / Cards de seções

**Página:** Home (`section.elementor-element-94843b7.e-grid.section-m.gap-s.e-con-boxed`)
**Widget:** widget HA (HappyAddons) — `ha-card--top`

Grid de 6 cards idênticos com estrutura:
```html
figure.ha-card-figure
  img
div.ha-card-body
  h2.ha-card-title
  div.ha-card-text > p
```

Cards presentes: Vibração, Palestras Pública, Mensagem Mediúnica, Agenda Reforma Íntima, Culto do Evangelho no Lar, Sementeira de Luz.

**Reconstrução sem WP/Elementor:**
- Grid CSS (`display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr))`)
- Cada card: `<article class="card card--top"><figure><img></figure><div class="card__body"><h2></h2><p></p></div></article>`
- Nenhuma lógica dinâmica — conteúdo estático

---

## 6. Card com badge (Evangelho Semanal)

**Página:** Home (`div.elementor-element-00b2ad5` via jet-listing-grid template `25177`)

Variante do card HA com badge posicionado no canto superior direito:
```html
figure.ha-card-figure
  img
  div.ha-badge.ha-badge--top-right  «Ítens 1, 5 a 8»
div.ha-card-body
  h2.ha-card-title
  div.ha-card-text
```

**Reconstrução:** card padrão + `<span class="badge badge--top-right">` com `position: absolute` relativo à `figure`.

---

## 7. Smart Listing de Últimas Notícias

**Páginas:** Home e Single de Palestra
**Widget:** `elementor-widget-jet-blog-smart-listing`

Layout híbrido "1 destaque à esquerda + lista de 3 itens à direita":

```
div.jet-smart-listing.has-featured-position-left
  div.jet-smart-listing__featured          ← item destaque grande
    div.post-thumbnail-featured > a > img
    div.jet-smart-listing__featured-content
      .post-title-featured > a
      .jet-smart-listing__meta             ← data + contagem de comentários
      .post-excerpt-featured
  div.jet-smart-listing__posts             ← lista lateral
    div.jet-smart-listing__post-wrapper    ← repetido 3x
      div.jet-smart-listing__post.has-post-thumb
        div.post-thumbnail-simple > a > img
        div.jet-smart-listing__post-content
          .post-title-simple > a
          .jet-smart-listing__meta
          .post-excerpt-simple
```

**Reconstrução sem WP/Elementor:**
- Endpoint REST `GET /posts?categoria=blog&limit=4`
- Componente `<SmartListing>` com CSS grid/flexbox: primeiro item em coluna larga, restantes em coluna estreita
- `<time datetime="YYYY-MM-DD">` para a data
- Lazy loading das imagens (`loading="lazy"`)

---

## 8. Seção "Quem Somos" com galeria

**Página:** Home (duas versões — desktop e mobile)

**Versão desktop** (`section.elementor-element-9ec3da2.elementor-hidden-tablet.elementor-hidden-mobile`):
- Galeria de 14 itens (`elementor-gallery`, `e-gallery-item.elementor-animated-content`) com overlay animado
- Texto institucional + botão "Saiba Mais"

**Versão mobile** (`section.elementor-element-9565413.elementor-hidden-desktop`):
- Imagem única em vez da galeria
- Mesmo texto + botão

**Reconstrução sem WP/Elementor:**
- Galeria: `<div class="gallery">` com itens em CSS grid; overlay com `opacity: 0` → `opacity: 1` via CSS `transition` no `:hover`/`:focus`
- Versão mobile: `@media (max-width: breakpoint)` oculta galeria e exibe imagem única, ou usar `<picture>` com `srcset`

---

## 9. Text Ticker (faixa de últimas notícias)

**Página:** Single de Palestra (oculto em mobile: `elementor-hidden-mobile`)
**Widget:** `elementor-widget-jet-blog-text-ticker`

Carrossel de texto animado ("typing effect") com itens de blog. Cada item:
```
div.jet-text-ticker__item
  div.jet-text-ticker__item-content.jet-use-typing.jet-multiline-typing
    img.jet-text-ticker__post-thumb
    div.jet-text-ticker__post-date   «08:00»
    div.jet-text-ticker__item-typed-wrap
      a.jet-text-ticker__item-typed
        span.jet-text-ticker__item-typed-inner
```

**Reconstrução sem WP/Elementor:**
- Biblioteca JS: Typed.js para o efeito de digitação, ou CSS animation pura para fading
- Array de posts vindos da API; rotacionar com `setInterval`
- Ocultar via `display: none` em mobile com media query

---

## 10. Seção de detalhe da palestra (Single CPT)

**Página:** `palestra_publica_16403` — template `elementor-23574.elementor-location-single`

Layout principal do single de palestra em duas colunas:

### 10.1 Coluna esquerda — Palestrante

Widget `jet-listing-grid` (template `23604`) com card de palestrante:
- Foto (`jet-listing-dynamic-image`)
- Nome (`jet-listing-dynamic-field`)
- Link overlay para o perfil do palestrante (`jet-engine-listing-overlay-link`)

### 10.2 Coluna direita — Conteúdo da palestra

- **Embed YouTube** via `div.yt-embed > iframe` (dentro de `elementor-widget-shortcode`)
- Campo "Tema" destacado (bg-overlay, `ha-has-bg-overlay`)
- Data com ícone SVG
- Local (endereço)
- **Acordeão** de "Principais Tópicos" (`elementor-widget-jet-accordion`):
  ```
  div.jet-accordion
    div.jet-accordion__inner
      div.jet-accordion__item.jet-toggle
        div.jet-toggle__control
          div.jet-toggle__label-icon (ícones open/close)
          div.jet-toggle__label-text
        div.jet-toggle__content
          div.jet-toggle__content-inner
  ```

### 10.3 Taxonomia — Principais Temas

Widget `elementor-widget-jet-listing-dynamic-terms`:
```
div.jet-listing-dynamic-terms
  a.jet-listing-dynamic-terms__link   ← tag clicável
  span.jet-listing-dynamic-terms__delimiter  ← separador
```

**Reconstrução sem WP/Elementor:**
- Card palestrante: componente `<CardPalestrante>` com dados de `/palestrantes/{id}`
- Embed YouTube: `<iframe>` com `loading="lazy"` e `title` acessível; ou API do YouTube via JS com thumbnail clicável (melhor performance)
- Acordeão: `<details>/<summary>` nativos do HTML, ou componente JS simples com `aria-expanded`
- Tags: lista de `<a class="tag" href="/palestras?tema={slug}">` separadas por ponto/vírgula

---

## 11. Barra de ações da palestra (curtir, favoritar, compartilhar)

**Página:** Single de Palestra (`section.elementor-element-13b6132.e-grid.section-narrow`)

Componentes presentes:
- `elementor-share-btn_whatsapp` — botão circular de compartilhar no WhatsApp
- `elementor-share-btn_facebook` — botão circular do Facebook
- `jet-engine-data-store-button` × 2 — botões "Curtir" e "Favoritar" (`jet-data-store-link.jet-add-to-store`)

**Reconstrução sem WP/Elementor:**
- Compartilhar WhatsApp: `<a href="https://wa.me/?text={url+titulo}" target="_blank" rel="noopener">`
- Compartilhar Facebook: `<a href="https://www.facebook.com/sharer/sharer.php?u={url}" target="_blank" rel="noopener">`
- Curtir/Favoritar: botões com estado persistido em localStorage (anônimo) ou em conta de usuário via API; toggle de ícone/classe via JS

---

## 12. Navegação entre posts (anterior/próximo)

**Página:** Single de Palestra
**Widget:** `elementor-widget-jet-blog-posts-navigation`

```
nav.navigation.post-navigation
  div.nav-links
    div.nav-previous > a «Sua Vida é Importante» + i.jet-arrow-prev
    div.nav-next > a «Bezerra de Menezes» + i.jet-arrow-next
```

**Reconstrução sem WP/Elementor:**
- Endpoint REST `GET /palestras/{id}/adjacentes` retornando `{anterior: {slug, titulo}, proximo: {slug, titulo}}`
- HTML: `<nav aria-label="Navegação entre palestras"><a rel="prev"><a rel="next">`

---

## 13. Breadcrumbs

**Páginas:** Contato, Single de Palestra
**Widget:** `elementor-widget-jet-breadcrumbs`

Estrutura:
```
div.jet-breadcrumbs
  div.jet-breadcrumbs__content
    div.jet-breadcrumbs__wrap
      div.jet-breadcrumbs__item
        a.jet-breadcrumbs__item-link.is-home  «Início»
      div.jet-breadcrumbs__item
        div.jet-breadcrumbs__item-sep > span.jet-blocks-icon  ← separador (ícone)
      div.jet-breadcrumbs__item
        a.jet-breadcrumbs__item-link   ← intermediário clicável
      ...
      div.jet-breadcrumbs__item
        span.jet-breadcrumbs__item-target   ← item atual (não é link)
```

Na palestra o caminho é: Início › Palestras Públicas › Palestra Pública › Título da palestra.

**Reconstrução sem WP/Elementor:**
- `<nav aria-label="Breadcrumb"><ol>` com `<li>` e `<a>` para itens clicáveis; último item como `<span aria-current="page">`
- Separador como pseudo-elemento CSS (`li + li::before { content: "›" }`) ou ícone SVG inline
- Dados gerados pelo roteador com base na hierarquia de páginas

---

## 14. Formulários (Jet Form Builder)

### 14.1 Formulário de Contato

**Página:** Contato (`elementor-widget-jet-form-builder-form`, `submit-type-reload`)

Campos:
| Campo | Tipo | ID | Observação |
|---|---|---|---|
| Nome | `text-field` | `nome_assunto` | obrigatório (`*`) |
| Email | `text-field` | `email_assunto` | obrigatório |
| WhatsApp | `text-field masked` | `zap_assunto` | opcional, com máscara |
| Assunto | `select-field` | `assunto` | 10 opções predefinidas |
| Mensagem | `wysiwyg-field` | `wp_editor_message` | rich text via `<textarea>` |
| Submit | `submit-field` | — | texto "Enviar", reload |
| Captcha | `input.captcha-token` | — | campo oculto |

Inclui `div.jet-form-builder-messages-wrap` para feedback inline.

### 14.2 Formulário de Newsletter

**Páginas:** Contato, Quem Somos, Single de Palestra (rodapé) — `submit-type-ajax`

Campos:
| Campo | Tipo | ID |
|---|---|---|
| Nome | `text-field` | `nome_lead` |
| Email | `text-field` | `email_newsletter` |
| Submit | `submit-field` | — (texto "Inscrever") |

**Reconstrução sem WP/Elementor:**
- Formulários nativos HTML5 com `<form>`, `<label>`, `<input>`, `<select>`, `<textarea>`, `<button type="submit">`
- Máscara do WhatsApp: biblioteca leve como IMask.js ou atributo `pattern`
- WYSIWYG simplificado: `<textarea>` puro (suficiente para contato); se necessário rico, usar Quill ou TipTap
- Validação: `required`, `type="email"` nativos + validação JS extra
- Submit AJAX: `fetch(endpoint, {method: 'POST', body: FormData})`; exibir resposta em `div.mensagens` sem recarregar
- Captcha: substituir `captcha-token` oculto por reCAPTCHA v3 ou hCaptcha invisível
- Formulário de contato (reload): pode permanecer como POST tradicional

---

## 15. Popups (Jet Popup)

**Páginas:** Contato, Quem Somos, Single de Palestra
**Widget:** `div#jet-popup-{ID}.jet-popup`

Estrutura genérica:
```
div.jet-popup.jet-popup--hide-state.jet-popup--animation-fade  ← oculto por padrão
  div.jet-popup__inner
    div.jet-popup__overlay    ← fundo escurecido
    div.jet-popup__container
      div.jet-popup__container-content
        ...conteúdo Elementor...
  button.jet-popup__close-button
```

Popups identificados com conteúdo visível:

| ID | Animação | Conteúdo |
|---|---|---|
| `21087` | fade | Confirmação de envio de mensagem com texto animado (jet-animated-text "Mensagem Enviada / Animete") |
| `22281` | fade | Confirmação genérica "Recebemos a sua solicitação" + links WhatsApp e Email |
| `27116` | zoom-in | Apenas uma imagem (`wp-image-27117`) |
| `23866`, `24792`, `23563`, `25517`, `25737` | fade | Conteúdo vazio/loader — provavelmente gatilhados dinamicamente |

O popup `21087` usa `elementor-widget-jet-animated-text`:
```
div.jet-animated-text.jet-animated-text--effect-fx1
  div.jet-animated-text__before-text «Mensagem»
  div.jet-animated-text__animated-text
    div.jet-animated-text__animated-text-item  «Enviada» (ativo)
    div.jet-animated-text__animated-text-item  «Animate»
  div.jet-animated-text__after-text «Com» «Sucesso»
```

**Reconstrução sem WP/Elementor:**
- `<dialog>` nativo HTML (`showModal()` / `close()`) com polyfill para Safari antigo
- Overlay: `::backdrop` do `<dialog>` ou `<div>` manual
- Fechar: botão interno + clique no overlay + `Escape`
- Foco aprisionado (`focus-trap`)
- Animação: `@keyframes fadeIn` / `scaleIn` controlados por classe JS
- Texto animado: Typed.js ou CSS `@keyframes` com `clip-path`

---

## 16. Rodapé (Footer)

**Widget:** `elementor-location-footer` (ID `elementor-21504`) — idêntico em todas as páginas.

O rodapé tem três seções verticais:

### 16.1 Seção principal (`section.elementor-element-031605f.section-m`)

Três colunas:

**Coluna 1 — Logo alternativo:**
`elementor-widget-image` com logo diferente do header (ID `wp-image-200`)

**Coluna 2 — Listas de navegação:**
Dois grupos `elementor-widget-icon-list` com ícones `huge.*` / `hm.*`:
- Grupo A: Instituição, História do CEMA, Nosso Blog, Notícias do CEMA
- Grupo B: Vibração Virtual, Palestras Públicas, Palestrantes, Mensagens Mediúnicas, Evangelho da Semana, Agenda Reforma Íntima

**Coluna 3 — Área legal:**
Terceiro grupo `elementor-widget-icon-list`:
- Minha Conta, Política de Privacidade, Política de Cookies, Termos e Condições, Contato

### 16.2 Seção de Newsletter (`div.elementor-element-fd643fa`)

- Heading "Inscreva-se no Newsletter"
- Formulário de newsletter (ver item 14.2)
- Texto legal com links para Termos e Políticas

### 16.3 Barra de redes sociais (`section.elementor-element-31d7e50.section-xxs`)

Dois grupos:
- 4 ícones de redes sociais (`elementor-widget-icon`) + imagem do WhatsApp
- 2 imagens de selos/badges (`wp-image-187`, `wp-image-185`)

### 16.4 Rodapé inferior (`section.elementor-element-e25abd8.section-xxs`)

- Endereço (`jet-listing-dynamic-field` com ícone SVG): "Quadra 02, Lote 16, Vila Vicentina, Planaltina, Brasília-DF"
- CNPJ: 01.600.089/0001-90
- Copyright "© 2026 Todos os direitos reservados."
- Créditos: "Desenvolvido por DECOM"
- Texto com links para sites relacionados (Cemanet, Cursos CEMA, CEMA Livraria)

**Reconstrução sem WP/Elementor:**
- `<footer>` com `<nav aria-label="Rodapé">` para as listas
- `<ul>` com ícones SVG inline ou icon font (substituir `huge.*`/`hm.*` por SVGs)
- Formulário de newsletter idêntico ao descrito no item 14.2
- Endereço em `<address>`
- Seção de redes sociais com `rel="noopener noreferrer"` nos links externos

---

## 17. Banner de Consentimento de Cookies (CMPLZ)

**Páginas:** Quem Somos (e provavelmente todas)
**Plugin:** Complianz (`cmplz-cookiebanner-container`)

Estrutura completa observada:
```
div.cmplz-cookiebanner.banner-1.banner-a.optin.cmplz-bottom-right
  div.cmplz-header
    div.cmplz-logo > a > img
    div.cmplz-title «Gerenciar o consentimento»
    div.cmplz-close
  div.cmplz-body
    div.cmplz-message
    div.cmplz-categories
      details.cmplz-category   ← x4: Funcional, Preferências, Estatísticas, Marketing
        summary > span.cmplz-category-header
          span.cmplz-category-title
          span.cmplz-banner-checkbox > input[type=checkbox]
        div.cmplz-description
  div.cmplz-buttons
    button.cmplz-accept «Aceitar»
    button.cmplz-deny «Negar»
    button.cmplz-view-preferences «Ver preferências»
    button.cmplz-save-preferences «Salvar preferências»
```

**Reconstrução sem WP/Elementor:**
- Substituir por solução dedicada: Cookie Consent (orestbida), Tarteaucitron ou implementação própria com `localStorage` / cookies
- Deve respeitar LGPD; categorias mínimas: Funcional (sempre ativo), Preferências, Estatísticas, Marketing

---

## 18. Popup de Notas de Rodapé / Texto Longo

**Página:** Quem Somos (provavelmente ligado a links âncora no conteúdo)
**ID:** `div.elementor-25462.elementor-location-popup`

Popup de conteúdo extenso com:
- Heading "Nota de rodapé 5"
- Dividers decorativos (`elementor-widget-divider`)
- Texto longo com `<em>` e referências a obras doutrinárias

**Reconstrução:** `<dialog>` ou painel lateral (`<aside role="complementary">`) ativado por `<a href="#nota-5">` com `scroll-into-view` ou modal.

---

## 19. Seção "Sugestão para Prece" (Widget dinâmico)

**Página:** Home (`div.elementor-element-1904d88`)
**Widget:** `jet-listing-grid` com overlay link (template `25027`)

Card com dois painéis lado a lado:
- Esquerdo: H2 "Sugestão para sua prece de hoje" + texto dinâmico do CPT
- Direito: imagem decorativa (`wp-image-24782`)
- `a.jet-engine-listing-overlay-link` — card inteiro é clicável

**Reconstrução:**
- Endpoint `GET /prece-do-dia`
- `<article class="card card--clickable">` com `<a>` wrapper ou `onclick` no JS
- Atualização diária via cron ou conteúdo gerado estaticamente (SSG)

---

## 20. Seção "Meta do Mês" (Widget dinâmico)

**Página:** Home (dentro do hero)
**Widget:** `jet-listing-grid` (template `24945`) + `jet-listing-dynamic-field`

Card com H2 "Meta do Mês:" + texto dinâmico. Link overlay para a página da meta.

**Reconstrução:** `GET /meta-do-mes` → texto inserido em `<p>` dentro do hero.

---

## Resumo dos Widgets Jet/Elementor identificados

| Widget | Ocorrências | Função |
|---|---|---|
| `elementor-widget-jet-nav-menu` | Header (todas as páginas) | Menu horizontal com dropdown |
| `elementor-widget-jet-search` | Header (todas as páginas) | Campo de busca |
| `elementor-widget-jet-auth-links` | Header (todas as páginas) | Links Entrar/Cadastrar |
| `elementor-widget-jet-listing-grid` | Home (×5), Single de Palestra (×1) | Grid dinâmico de CPTs |
| `elementor-widget-jet-listing-dynamic-field` | Home, Contato, Single, Rodapé | Campo meta dinâmico |
| `elementor-widget-jet-listing-dynamic-image` | Single de Palestra, Palestrante card | Imagem dinâmica de CPT |
| `elementor-widget-jet-listing-dynamic-terms` | Single de Palestra | Tags/taxonomia do post |
| `elementor-widget-jet-blog-smart-listing` | Home, Single de Palestra | Listing de blog com destaque |
| `elementor-widget-jet-blog-text-ticker` | Single de Palestra | Faixa de notícias animada |
| `elementor-widget-jet-blog-posts-navigation` | Single de Palestra | Anterior/próximo post |
| `elementor-widget-jet-accordion` | Single de Palestra | Acordeão de tópicos |
| `elementor-widget-jet-breadcrumbs` | Contato, Single de Palestra | Trilha de navegação |
| `elementor-widget-jet-form-builder-form` | Contato, Rodapé | Formulários de contato e newsletter |
| `elementor-widget-jet-animated-text` | Popup #21087 | Texto com efeito de digitação |
| `elementor-widget-jet-engine-data-store-button` | Single de Palestra | Curtir / Favoritar |
| `elementor-widget-off-canvas` | Header (todas as páginas) | Painel lateral mobile |
| `elementor-widget-share-buttons` | Single de Palestra | Botões de compartilhamento |
| `ha-card` (HappyAddons) | Home (×6), Evangelho | Cards com figura e texto |
