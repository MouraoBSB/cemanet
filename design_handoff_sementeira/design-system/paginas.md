# Mapa de templates e seções

> Levantado a partir dos esqueletos estruturais das páginas representativas do cemanet.org.br. Guia a ordem das seções (topo → rodapé) e os padrões de layout de cada template, para reconstrução **sem** WordPress.

## Elementos globais

### Cabeçalho (elementor-21493 — presente em todas as páginas)

Estrutura em dois níveis:

**Nível 1 — Barra superior** (`e-con-boxed`, `e-flex`, 3 colunas)
- Coluna 1: logotipo vinculado (`img`)
- Coluna 2: campo de busca (`jet-search`)
- Coluna 3 (desktop): links de autenticação — "Possui uma conta? Entrar / ou Cadastrar" (`jet-auth-links`); oculto em tablet e mobile (`elementor-hidden-tablet elementor-hidden-mobile`)
- Coluna 3 (mobile): ícone de hambúrguer (`huge-menu-07`); oculto em desktop (`elementor-hidden-desktop`)

**Nível 2 — Barra de navegação** (`e-con-boxed`, `e-flex`; oculta em tablet e mobile)
- Navegação horizontal via `jet-nav-menu` com 8 itens de primeiro nível, todos com dropdown:
  - Institucional > Nossa Estrutura, Nossa História, Contato
  - Palestras > Palestras Públicas, Palestrantes
  - Mensagens Mediúnicas > Mensagens, Autores Espirituais
  - Eventos > Nossos Eventos
  - Vibração > Caixinha de Vibração
  - Agenda > Agenda Reforma Íntima
  - Evangelho > Evangelho da Semana, Evangelho no Lar
  - Sementeira > Blog Espírita (com 5 subcategorias de terceiro nível)

**Off-canvas mobile** (acionado pelo hambúrguer; `e-off-canvas`)
- Linha superior: ícone de fechar + logotipo + botões "Fazer Login?" e "Criar Conta?"
- H2 "Menu" + navegação vertical (`elementor-nav-menu--layout-vertical`) espelhando o menu principal com submenus em acordeão

---

### Rodapé (elementor-21504 — presente em todas as páginas)

**Bloco 1 — Rodapé principal** (`e-con-full`, `section-m`, `e-flex`, 3 colunas)
- Coluna 1: logotipo (imagem, `wp-image-200`)
- Coluna 2: 3 listas de ícones verticais (`icon-list`) em colunas internas:
  - Lista A: Instituição, História do CEMA, Nosso Blog, Notícias do CEMA
  - Lista B: Vibração Virtual, Palestras Públicas, Palestrantes, Mensagens Mediúnicas, Evangelho da Semana, Agenda Reforma Íntima
  - Lista C: Minha Conta, Política de Privacidade, Política de Cookies, Termos e Condições, Contato
- Coluna 3: H2 "Inscreva-se no Newsletter" + formulário (`jet-form-builder`, campos Nome e Email + botão "Inscrever") + texto legal com links para Termos e Políticas de Privacidade

**Bloco 2 — Barra intermediária** (`e-con-boxed`, `section-xxs`, 2 subgrupos em grid)
- Subgrupo 1: 4 ícones de redes sociais (YouTube, Instagram, Facebook, WhatsApp) + logotipo WhatsApp (`wp-image-16`)
- Subgrupo 2: logotipos de parceiros FEB (`wp-image-187`) e UFE (`wp-image-185`)

**Bloco 3 — Barra de copyright** (`e-con-boxed`, `section-xxs`, `e-flex`, 2 colunas)
- Coluna 1: endereço dinâmico (`jet-listing-dynamic-field`) + CNPJ + "© 2026 Todos os direitos reservados." + créditos DECOM
- Coluna 2: texto com links para Cemanet, Cursos CEMA, CEMA Livraria

---

### Elementos transversais de infraestrutura
- **Banner de cookies** (`#cmplz-cookiebanner-container`): presente em todas as páginas; `cmplz-bottom-right`; categorias Funcional, Preferências, Estatísticas, Marketing; botões Aceitar, Negar, Ver preferências, Salvar preferências
- **Popups Jet** (`jet-popup--front-mode`): múltiplos por página, ocultos por padrão (`jet-popup--hide-state`), ativados por triggers; animações `fade` ou `zoom-in`

---

## T01 — Home (`index.html`, elementor-19)

### S1 — Hero principal
Seção `section-hero` com efeito de partículas (`jet-tricks-particles-section`). Layout: full-width, coluna única, centralizado.
- Texto animado (`jet-animated-text`) acima do título
- H1 com título da instituição
- Subtítulo em parágrafo
- Botão de CTA primário

### S2 — Chamadas em destaque (cards)
Seção `section-l`, contained (`e-con-boxed`). Grid ou flex de múltiplas colunas com cards que resumem seções do site (ex.: Palestras Públicas, Evangelho, Agenda).

### S3 — Próxima palestra
Seção contained. Layout de 2 colunas (informações à esquerda, imagem ou visual à direita). Destaca data, título e palestrante da próxima palestra.

### S4 — Últimas notícias / Sementeira
Seção `section-m`, contained. Listagem dinâmica (`jet-listing-grid`) de posts recentes; layout em grid, múltiplas colunas no desktop.

### S5 — Evangelho da semana
Seção contained. Layout de 1 ou 2 colunas; exibe trecho do evangelho em curso com link "Ver mais".

### S6 — Agenda Reforma Íntima
Seção contained. Destaque da meta e reflexão do dia corrente; 1 coluna com campos dinâmicos (`jet-listing-dynamic-field`).

### S7 — Palestrantes em destaque
Seção `section-m`, contained. Carrossel swiper ou grid de cards de palestrantes vinculados via JetEngine.

### S8 — Newsletter (CTA)
Seção de fundo diferenciado, full-width. Coluna única centralizada com H2 e formulário compacto (nome + e-mail + botão).

### S9 — Parceiros / Credenciais
Seção `section-s`, contained. Logos de FEB e UFE + ícones de redes sociais.

---

## T02 — Quem Somos (`quem-somos.outline.txt`)

> **Nota:** A URL `/quem-somos/` foi capturada como página de erro 404 (template elementor-25642, "ERRO 404 / Página Indisponível"). O conteúdo real desta seção institucional não estava disponível no snapshot — o slug parece morto; a página institucional viva é `/nossa-historia/`. Não é possível mapear seções.

---

## T03 — Nossa História (`nossa-historia.outline.txt`, elementor-111)

### S1 — Hero / Título
Seção `section-xxl`, contained. Coluna única com H1 e subtítulo introdutório.

### S2 — Navegação por âncoras (lateral)
Componente fixo lateral direito (`jet-scroll-navigation`); pontos clicáveis que saltam para cada seção histórica da página. Padrão de rolagem suave.

### S3 — Seção histórica 1 (fundação)
Seção `section-l`, contained. Layout 2 colunas: texto à esquerda (H2 + parágrafos narrativos) e imagem à direita. Flex horizontal.

### S4 — Seção histórica 2
Mesma estrutura 2 colunas com imagem invertida (imagem à esquerda, texto à direita). Alterna lado a cada seção.

### S5 — Seção histórica 3
2 colunas; texto + imagem.

### S6 — Seção histórica 4
2 colunas; texto + imagem.

### S7 — Carrossel de fotos históricas
Seção `section-m`. Carrossel Swiper full-width ou contained; múltiplas imagens em destaque horizontal.

### S8 — Diretoria (lista de diretores)
Seção contained. Tabela dinâmica (`jet-dynamic-table`) listando diretores históricos com colunas (nome, período, cargo).

---

## T04 — História (`historia.outline.txt`)

> **Nota:** A URL `/historia/` foi capturada como página de erro 404 (mesmo template elementor-25642 da página Quem Somos). Conteúdo não disponível no snapshot.

---

## T05 — Contato (`contato.outline.txt`, elementor-113)

### S1 — Hero com partículas
Seção `section-hero`, `jet-tricks-particles-section`, contained. Coluna única centralizada com H1 "Contato" e subtítulo.

### S2 — Breadcrumb
Seção `section-xxs`, contained. `jet-breadcrumbs`: Início > Contato. Flex, alinhamento à esquerda.

### S3 — Introdução com imagem
Seção `section-m`, contained. Layout 2 colunas: imagem ilustrativa à esquerda, texto introdutório (parágrafos) à direita.

### S4 — Redes sociais + Formulário
Seção `section-m`, contained. Layout 2 colunas:
- Coluna esquerda: lista de ícones de redes sociais com links (YouTube, Instagram, Facebook, WhatsApp)
- Coluna direita: formulário de contato (`jet-form-builder`) com campos:
  - Nome (obrigatório, `text-field`)
  - E-mail (obrigatório, `text-field`)
  - WhatsApp (`text-field`)
  - Assunto (`select` com 10 opções)
  - Mensagem (área de texto wysiwyg)
  - Botão "Enviar"
  - Popup de confirmação `jet-popup-21087` após envio (mensagem "Recebemos a sua solicitação" + links de WhatsApp e E-mail)

### S5 — Informações da biblioteca / presencial
Seção `section-s`, contained. Coluna única ou 2 colunas com endereço, horários e dados da biblioteca/sede.

---

## T06 — Single Palestra Pública (`palestra_publica_16403.outline.txt`, elementor-23574)

### S1 — Hero da palestra
Seção `section-hero`, `section-xxl`, contained. Layout de coluna única com:
- Ticker de notícias (`jet-blog-text-ticker`): rolagem de manchetes com hora e thumbnail; oculto em mobile
- H1 com título da palestra
- Subtítulo dinâmico (`jet-listing-dynamic-field`)
- Breadcrumb multinível: Início > Palestras Públicas > [título] (`jet-breadcrumbs`)
- Botão/CTA "Ver Calendário" com ícone (`jet-popup-target`)

### S2 — Barra de compartilhamento e interação
Seção `section-narrow`, contained. Flex horizontal com 4 elementos:
- Botão compartilhar Facebook
- Botão compartilhar WhatsApp
- Botão copiar link (clipboard dinâmico)
- Botão "Curtir" / favoritar (`jet-data-store-button`)

### S3 — Conteúdo principal (2 colunas)
Seção `section-m`, contained. Flex 2 colunas:
- Coluna esquerda (~30%): card do palestrante com foto dinâmica (`jet-listing-dynamic-image`), nome e link
- Coluna direita (~70%):
  - Player YouTube embutido (`jet-listing-dynamic-field` com embed)
  - Metadata da palestra: data, público online, assuntos principais (taxonomia)
  - Acordeão de tópicos abordados (`jet-accordion`)

### S4 — Barra de termos/taxonomia
Seção `section-s`, contained. Lista de tags/termos da taxonomia `assuntos-principais` renderizados como pílulas ou lista simples.

### S5 — Navegação anterior/próxima
Seção `section-xxs`, contained. Flex 2 colunas: link "Palestra anterior" à esquerda e "Próxima palestra" à direita.

### S6 — Últimas notícias (smart listing)
Seção `section-m`, contained. Grid de cards de posts recentes via `jet-listing-grid`; 3 colunas no desktop.

---

## T07 — Single Evangelho da Semana (`evangelho_semana-1.outline.txt`, elementor-24667)

### S1 — Título do trecho
Seção `section-xxl`, contained. Coluna única com H1 dinâmico exibindo o título do item (ex.: "Introdução").

### S2 — Localização no livro
Seção `section-s`, `gap-s`, contained. Flex horizontal com 3 campos dinâmicos em linha (`display-inline`):
- Parte/seção do livro (ex.: "PREFÁCIO; INTRODUÇÃO")
- Capítulo (ex.: "Capítulo: INTRODUÇÃO")
- Item (ex.: "Ítens: I")

### S3 — Conteúdo em acordeão
Seção `section-l`, contained. Coluna única com `jet-accordion`; cada item do acordeão corresponde a um sub-tópico do trecho (ex.: "Prefácio", "Objetivo desta obra"), expandindo o texto completo do Evangelho no Lar.

### S4 — Link de retorno à escala
Fim da seção `section-l` ou seção própria `section-xxs`. Lista de ícone único (`icon-list`) com seta esquerda (`huge-arrow-left-04-round`) e texto "Voltar para escala".

---

## T08 — Single Palestrante (`palestrantes_abadio-rodrigues.outline.txt`, elementor-23671)

### S1 — Hero do palestrante
Seção `section-hero`, `gap-m`, contained. Layout de coluna única com:
- Ticker de notícias (`jet-blog-text-ticker`): rolagem de manchetes; oculto em mobile
- H1 com nome do palestrante (dinâmico)
- Breadcrumb: Início > Palestrantes e Diretores > [nome] (`jet-breadcrumbs`)
- Botão "Veja o Calendário de Palestras" com ícone-box (`elementor-icon-box`, `jet-popup-target`)

### S2 — Foto do palestrante
Seção `section-s`, contained. Coluna única centralizada com imagem dinâmica em destaque (`jet-listing-dynamic-image`).

### S3 — Lista de palestras do palestrante
Seção `gap-s`, contained, interna `section-narrow`. H2 "Palestras" + listagem dinâmica via `jet-listing-grid` (template elementor-23510) em carrossel swiper de 1 coluna. Cada card de palestra exibe:
- Badge de status ("Em Breve" + contagem regressiva quando aplicável)
- Tipo ("Palestra Pública")
- Título da palestra
- Nome do palestrante
- Data da palestra
- Descrição/subtítulo
- Imagem da palestra (`jet-listing-dynamic-image`)
- Botão "Ver Palestra"

---

## T09 — Single Evento (`_evento_10o-encontro-da-familia.outline.txt`, elementor-24352)

### S1 — Hero do evento
Seção `section-xxl`, contained (com container pai `e-con-full jedv-enabled--yes`). Layout de coluna única com:
- Ticker de notícias (`jet-blog-text-ticker`); oculto em mobile
- Termos da taxonomia do evento com ícone (`jet-listing-dynamic-terms`, ícone `hm-sticky`)
- H1 com título do evento (dinâmico)
- Breadcrumb: Início > Evento > [título] (`jet-breadcrumbs`)

### S2 — Metadados do evento
Seção `section-m`, contained. Flex com 2 campos dinâmicos em linha:
- Data do evento com ícone SVG (ex.: "02/06/24")
- Local do evento com ícone SVG (ex.: "CEP Saúde")

### S3 — Barra de interação
Seção `section-narrow`, contained. Flex horizontal com 4 elementos:
- Botão compartilhar Facebook
- Botão compartilhar WhatsApp
- Botão copiar link (clipboard dinâmico)
- Botão "Curtir" (`jet-data-store-button`)

### S4 — Imagem principal do evento
Container `e-con-boxed`, coluna única. Imagem dinâmica em destaque (`jet-listing-dynamic-image`, tamanho `attachment-medium`).

### S5 — Mensagens relacionadas ao evento
Seção `section-m`, `gap-s`, contained. Layout 1 coluna principal com:
- H2 "Mensagens"
- Grid/carrossel de cards de mensagens via `jet-listing-grid` (template elementor-21982); cada card exibe:
  - Avaliação em estrelas (`e-rating`, 5 estrelas)
  - ID interno da mensagem
  - Ícone decorativo
  - Título da mensagem
  - Data (com ícone SVG)
  - Tipo/contexto da mensagem (ex.: "Atendimento Desobsessivo")

---

## T10 — Single Agenda Reforma Íntima (`agenda-reforma_01-de-junho-de-2026.outline.txt`, elementor-24545)

### S1 — Hero com partículas
Seção `section-xxl`, `jet-tricks-particles-section`, contained. Coluna única centralizada:
- H1 "Agenda Reforma Íntima"
- Linha divisória decorativa (`elementor-divider`)
- H2 com subtítulo descritivo da proposta

### S2 — Conteúdo do dia + Calendário mensal
Seção `section-m`, `gap-s`, contained. Layout 2 colunas principais:

**Coluna esquerda** (conteúdo do dia, subdividida em sub-colunas empilhadas):
- Data do dia por extenso (campo dinâmico, ex.: "segunda-feira, 01 de junho de 2026")
- Reflexão evangélica do dia: rótulo "Reflexão e Vivência em torno do Evangelho:" + trecho dinâmico (`jet-listing-dynamic-field`)
- Meta do Mês: H2 + título da meta + citação de apoio (campo dinâmico)
- Meta do Dia: H2 + título da meta + descrição curta (campo dinâmico, com `jedv-enabled--yes`)
- Sugestão de prece diária: H2 + texto dinâmico
- Botões de compartilhamento: ícone-box "Compartilhar" + ícones circulares de WhatsApp e Facebook

**Coluna direita** (calendário):
- Calendário mensal interativo (`jet-listing-calendar`, template elementor-24522)
- Grade tabular (`table.jet-calendar-grid`) com cabeçalho de meses e navegação prev/next
- Dias da semana no cabeçalho (`dom` a `sáb`)
- Cada célula de dia com eventos vinculados; no desktop: ícone de link; no mobile: texto "Ver Agenda"

---

## Padrões recorrentes

### Padrão Hero
Seções de entrada de páginas e single posts usam a classe `section-hero` ou `section-xxl` com `e-con-boxed`. Quase sempre trazem H1 dinâmico, breadcrumb e um elemento adicional de contexto (ticker de notícias ou CTA). Efeito de partículas (`jet-tricks-particles-section`) aparece nos heroes de páginas institucionais (Contato, Agenda).

### Ticker de notícias
Componente `jet-blog-text-ticker` presente nos heroes de Palestra Pública, Palestrante e Evento. Exibe manchetes recentes com hora e thumbnail. Oculto em mobile (`elementor-hidden-mobile`).

### Breadcrumb multinível
`jet-breadcrumbs` presente em todos os singles e páginas internas. Estrutura padrão: Início > [CPT ou seção] > [título atual]. Separador por ícone de seta (`jet-blocks-icon`).

### Barra de compartilhamento + interação
Seção `section-narrow` com flex horizontal de 4 ações: Facebook, WhatsApp, copiar link (clipboard dinâmico), curtir/favoritar (`jet-data-store-button`). Presente em Palestra Pública e Evento.

### Cards dinâmicos via JetEngine listing
Listagens de palestras, mensagens e outros CPTs usam `jet-listing-grid` com template dedicado. Estrutura padrão de card: imagem dinâmica (`jet-listing-dynamic-image`) + campos dinâmicos (`jet-listing-dynamic-field`) para título, data, palestrante/autor, subtítulo/descrição + botão CTA. Carrossel swiper quando lista rolável.

### Formulários JetFormBuilder
Dois formulários recorrentes: contato (5 campos + select de assunto) e newsletter (2 campos). Ambos com submit AJAX e popup de confirmação (`jet-popup`) pós-envio.

### Popups Jet
Múltiplos `jet-popup` ocultos por padrão em cada página. Tipos: confirmação de formulário (com conteúdo visível), loaders (container vazio aguardando conteúdo dinâmico), lightbox de imagem (animação `zoom-in`). Todos com botão de fechar e overlay.

### Layouts de 2 colunas assimétricos
Padrão recorrente em singles: coluna menor à esquerda (foto/card de palestrante ou imagem) e coluna maior à direita (conteúdo principal — player, texto, acordeão). Também usado em páginas institucionais com alternância de lados (texto-imagem, imagem-texto) a cada bloco.

### Acordeão de tópicos
`jet-accordion` usado tanto no single de Palestra Pública (tópicos abordados) quanto no single de Evangelho (sub-tópicos do texto). Ícones de abrir/fechar customizados; efeito `jet-toggle-move-up-effect`.

### Navegação por âncoras lateral
`jet-scroll-navigation` (pontos laterais) presente na página Nossa História para navegar entre seções longas. Padrão adequado a páginas de conteúdo narrativo extenso.

### Calendário interativo
`jet-listing-calendar` presente na Agenda Reforma Íntima. Grade tabular com navegação de mês e células com eventos vinculados. Comportamento diferente por breakpoint: desktop mostra ícone de link; mobile mostra texto "Ver Agenda".

### Escalas de tamanho de seção
Classes utilitárias de espaçamento verticais por ordem decrescente de padding: `section-hero` > `section-xxl` > `section-l` > `section-m` > `section-s` > `section-xxs`. Complementadas por `section-narrow` e `section-narrow-xs` para largura máxima restrita de conteúdo.

### Ícones customizados
Dois conjuntos: `huge.*` (ícones de UI gerais: menu, login, setas, casa, cookie) e `hm.*` (ícones temáticos: palestrante `hm-speaker`, marcador `hm-sticky`, caneta `hm-pen-paper`). Nunca misturados no mesmo contexto funcional.

### Responsividade por visibilidade condicional
Estratégia de exibição condicional por breakpoint via classes `elementor-hidden-desktop`, `elementor-hidden-tablet`, `elementor-hidden-mobile`. Elementos críticos têm versões paralelas no markup para cada viewport (ex.: menu desktop × off-canvas mobile; ticker visível no desktop × oculto no mobile).
