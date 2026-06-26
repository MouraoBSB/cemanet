# Blog "Sementeira de Luz" — Backlog de ajustes (review)

Lista **viva** de ajustes pedidos na revisão da Fatia 1 (admin + front já implementados).
Origem: review do Thiago — 2026-06-26. Spec base: `2026-06-26-blog-sementeira-de-luz-design.md`.
Legenda: `[ ]` aberto · `[x]` feito · **(DECISÃO)** = aguarda OK do cliente.

## Front público — artigo

- [ ] **Galeria do fim do artigo: trocar a grade "tijolos" por carrossel/slide.**
  Hoje renderiza em grid/masonry; preferência do cliente é **slider** (deslizar).
  Manter clique → lightbox. Responsivo (swipe no mobile) e lazy-load (galerias até
  34 fotos). Sugestão: lib leve (Embla/Splide/Glide/Swiper) ou Alpine puro.

## Admin — editor de conteúdo

- [ ] **Bloco de carrossel inserível no corpo do post.** Hoje a galeria é fixa no
  fim; permitir inserir um carrossel/galeria **onde quiser** dentro do texto.
  → ⚠️ **Não** reaproveita mais "bloco custom": a decisão do editor virou **extensão
  TipTap**. Um carrossel inserível precisará de **abordagem própria** (nó/extensão
  TipTap específico, ou uma referência à galeria que materializa no render) — escopo
  a definir quando chegar a vez.

## Admin — galeria / upload de imagens

Hoje lista "uma embaixo da outra", sem miniatura, sem upload múltiplo e sem
reordenar — pouco intuitivo.

- [ ] **Preview (miniatura)** de cada imagem.
- [ ] **Upload múltiplo** (selecionar/arrastar várias de uma vez).
- [ ] **Reordenar** (arrastar; a ordem reflete no front).
- [ ] **Otimização automática no upload — DECISÃO APROVADA: Spatie Media Library**
  (cobre também os 3 itens acima: preview, upload múltiplo e reordenar). Detalhes abaixo.

### DECISÃO APROVADA (2026-06-26): Spatie Media Library

Objetivo do cliente: "jogar a foto e o sistema **mastiga**" (sem ferramenta externa).
Aprovado por priorizar eficiência e manutenção a longo prazo.

**Adotado: Spatie Media Library + image-optimizer.**
- Resolve **de uma vez** os 3 itens de UX acima: o `SpatieMediaLibraryFileUpload` do
  Filament já traz **preview, upload múltiplo e reordenação**.
- Gera **conversões** (web/thumb), **WebP**, **imagens responsivas (srcset)** e
  **remove EXIF**; compressão via `spatie/image-optimizer`.
- **Política de armazenamento (DECIDIDO 2026-06-26):** **não guardar o original cru.**
  Capar **na entrada** (máx. ~2000px, qualidade ~82, **WebP**) e **descartar** o arquivo
  enviado; ficam só a versão capada + as conversões. Acervo em alta resolução é
  responsabilidade do cliente, **fora do sistema** (ex.: Google Drive). OG ~1200×630.
- **Conversões em fila** (queue) — galerias grandes não travam o upload.
- **Docker:** instalar os binários do optimizer no container (`jpegoptim`,
  `pngquant`, `optipng`, `cwebp`, `gifsicle`, `svgo`).
- **Trade-off:** troca as colunas string atuais (`imagem_destacada`,
  `post_imagens.caminho`) por modelo de mídia → ajustar migrations + importador.
  Mais arquitetura agora, mas é o padrão profissional e resolve vários itens juntos.
- Alternativa leve (se **não** quiser Media Library): `intervention/image` num
  observer (resize máx + compress + WebP), mantendo as colunas — porém **sem**
  srcset/preview/reordenar prontos (a UX continuaria manual).

### Ordem de execução (decidido 2026-06-26)

**Media Library primeiro.** Ela muda storage + importador e reimporta os 45 posts
**uma única vez**; a feature de alinhamento de imagem (extensão TipTap + allow-list +
CSS + colunas→markup limpo) **encaixa nesse pipeline depois** — evita reimportar e
mexer no importador duas vezes.

## Admin — Taxonomia e Publicação

- [ ] **Verificar importação de Tags.** Categorias vieram certas; Tags aparece
  vazio. No legado **só 1 post** tinha tag, então pode estar **correto** neste post —
  confirmar que o importador traz tags quando existem (testar no post que tem).

## Admin — FAQ

- [ ] **Pergunta como título do item** do accordeon. Hoje, recolhido, o item fica
  em branco. Usar o texto da pergunta como rótulo (Filament repeater
  `itemLabel`/`->collapsed()`).

## Admin — SEO

- [ ] **Imagem OG padrão = imagem de destaque** do post quando OG não preenchida
  (no front já é fallback; refletir também no preenchimento/preview do admin).
- [ ] **Placar SEO com cores** (verde = ok / vermelho = falta) no lugar de ✓/X em
  texto preto. Acessível: manter ícone/texto além da cor (não depender só de cor).
