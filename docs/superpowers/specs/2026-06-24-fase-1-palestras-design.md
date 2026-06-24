# Design — Fase 1: Módulo Palestras (CEMA)

Data: 2026-06-24 · Stack: Laravel 13 · Filament 5 · MySQL 8 · Blade/Livewire 3 · Tailwind v4 · Docker.
Referências: `DATA-MODEL.md`, `DB-LEGADO.md`, `design-system/ANOTACOES-IMPLEMENTACAO.md`, `ROADMAP.md`.

## Objetivo

Entregar a **fatia vertical completa** do módulo Palestras: banco → importação (do WordPress
legado) → admin (Filament) → front público (layout base + listagem + página individual),
com as **123 palestras públicas** migradas e fiéis ao design-system. Prova a arquitetura
inteira num módulo antes de expandir para os demais (Fase 2).

## Decisões (alinhadas no brainstorming)

1. **Fonte da importação:** banco `legado` (read-only) direto — conteúdo mais rico que a REST;
   relações Jet triviais via SQL; repeater serializado e metas acessíveis.
2. **Escopo do front:** layout base (header/footer responsivos, navegação real para o que existe;
   módulos futuros como placeholder) + listagem `/palestras` + single `/palestras/{slug}` (T06).
3. **Campos extras migrados já:** `online` (palestra) e `email`/`telefone`/`status` (palestrante).
4. **Modelo de pessoas:** tabela única `palestrantes` para palestrante **e** diretor; o papel vive
   no pivô `palestra_pessoa`. Cardinalidade **1–2 palestrantes (obrigatório) / 0–1 diretor (opcional)**.
5. **Visibilidade pública (regra de negócio):** `palestrantes.ativo` controla a exibição —
   *ativo* = nome aparece no site; *inativo* = não aparece (tipicamente quem só atua como diretor).
6. **Descrição:** dois campos — `descricao` (corpo HTML ← `post_content`) e `resumo`
   (← meta `descricao`); `subtitulo` ← `post_excerpt`.
7. **Implementação:** 1 comando orquestrador de importação · imagens baixadas para o storage local ·
   front Blade SSR com Livewire/Alpine só onde há interação.

## Modelo de dados (migrations + seeders)

> Conferir o que já existe antes de criar (não há tabelas de domínio ainda — só as de base do Laravel).
> FKs sempre. Nomes de domínio em pt-BR.

### `palestrantes`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | resolução na importação |
| foto | string null | caminho no storage local (imagem baixada) |
| bio | longtext null | HTML (← `post_content` do CPT) |
| email | string null | ← `email_palestrante` |
| telefone | string null | ← `telefone_palestrante` |
| mostrar_email | bool default false | ← `mostrar_email_palestrante` |
| mostrar_telefone | bool default false | ← `mostrar_telefone_palestrante` |
| ativo | bool default true | ← `status_palestrante`; **controla visibilidade pública** |
| timestamps | | |

### `palestras`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | ← `post_title` |
| slug | string unique | ← `post_name` |
| subtitulo | string null | ← `post_excerpt` |
| resumo | text null | ← meta `descricao` |
| descricao | longtext null | ← `post_content` (HTML) |
| data_da_palestra | datetime | ← meta `data_da_palestra` (Unix → datetime) |
| online | bool default false | ← meta `palestra_online` (`"on"`) |
| link_youtube | string null | ← meta `link_do_youtube` (preservar exato) |
| cor_fundo | string null | ← meta `escolher_cor_do_fundo` (hex) |
| publico_online | int null | |
| publico_presencial | int null | |
| publico_total | int null | |
| status | string default `publicado` | `publicado`/`rascunho` (← `post_status`) |
| timestamps | | |

### `assuntos` (taxonomia `assuntos-principais`, hierárquica)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | |
| parent_id | bigint null FK→assuntos.id | 141 termos, 46 com pai |

### `palestra_pessoa` (pivô — relações Jet 107/108)
`palestra_id` FK · `pessoa_id` FK→palestrantes · `papel` enum(`palestrante`,`diretor`).
Regra de cardinalidade validada na aplicação (não só no schema):
**1–2 `palestrante` (obrigatório) e 0–1 `diretor` (opcional)** por palestra.

### `assunto_palestra` (N:N)
`palestra_id` FK · `assunto_id` FK.

### `palestra_destaques` (repeater "assuntos principais")
`id` · `palestra_id` FK · `destaque` string · `texto` text · `ordem` int.

## Models e relações (Eloquent)

- **Palestra**: `belongsToMany` palestrantes (via `palestra_pessoa`, `withPivot('papel')`);
  `belongsToMany` assuntos; `hasMany` destaques (ordenados). Acessores/escopos:
  `palestrantesAtivos()` (papel=palestrante + pessoa ativa), `diretor()`,
  `scopePublicado`. Cast de `data_da_palestra`/`online`/inteiros.
- **Palestrante**: `belongsToMany` palestras (com papel). `scopeAtivo`.
- **Assunto**: `parent`/`children` (self-relation).
- **Cardinalidade**: validar em FormRequest (admin) + uma regra de domínio reutilizável
  (ex.: método estático ou observer) — 1–2 palestrantes obrigatórios, 0–1 diretor.

## Importação — `php artisan cema:importar-palestras`

Comando único orquestrador, lendo `DB::connection('legado')` (read-only). Verifica a conexão no
início e **aborta com mensagem clara** se o túnel SSH não estiver ativo. Idempotente: **upsert por
slug**; nunca duplica; resolve pessoas/assuntos por slug e **loga** o que não resolver (não cria às cegas).

Ordem:
1. **Assuntos** — `wp_terms`+`wp_term_taxonomy` (taxonomy=`assuntos-principais`); preserva hierarquia
   (`parent` → `parent_id`, resolvido em 2 passadas ou por mapa term_id→id).
2. **Palestrantes** — CPT `palestrantes` (publish): nome/slug/bio + metas (email/telefone/flags/status→ativo);
   baixa a foto (`_thumbnail_id` → URL pública do attachment) para o storage local.
3. **Palestras** — CPT `palestra_publica` (publish): campos do post + metas (mapeamento da seção acima).
4. **Relações** (atenção à **direção oposta**, ver `DB-LEGADO.md`):
   - **107 (palestrante):** `parent_object_id`=palestrante, `child_object_id`=palestra →
     papel `palestrante`.
   - **108 (diretor):** `parent_object_id`=palestra, `child_object_id`=diretor → papel `diretor`.
5. **assunto_palestra** — `wp_term_relationships` (taxonomy=`assuntos-principais`), resolvendo por slug.
6. **Destaques** — meta `assuntos_principais` (PHP serializado `item-N → {destaque, texto}`);
   `unserialize()`, `ordem` = índice.

**Mídia:** imagens baixadas via GET na URL pública do attachment (leitura pública, sem tocar no WP)
e salvas em `storage/app/public/palestrantes/…`; idempotente (não rebaixa se já existe).

**A confirmar na implementação:** valores reais de `status_palestrante` (mapear → `ativo`); fuso do
timestamp `data_da_palestra`; 3 palestras sem `link_do_youtube` e ~64 sem `post_content` (ficam null).

## Admin (Filament 5)

- **PalestraResource** — CRUD completo; *relation managers*: palestrantes (com seletor de `papel`),
  assuntos (multiselect/árvore), destaques (repeater `destaque`/`texto`/`ordem`); **validação da
  cardinalidade** (1–2 palestrantes, 0–1 diretor) no form; upload/preview de mídia; filtros por
  assunto/status/data.
- **PalestranteResource** — CRUD: nome, slug, foto, bio (rich text), email/telefone + flags, `ativo`.
- Assuntos geríveis (recurso simples ou via relação), com `parent_id`.

## Front público (Blade SSR + Livewire/Alpine pontual)

- **Layout base** `resources/views/components/layout/app.blade.php` (`<x-layout.app>`): header
  (logo, busca, navegação) + footer, responsivo, usando os tokens do `@theme`. Mega-menu com itens
  reais (Palestras, Institucional) e os de módulos futuros como placeholder/desabilitado.
- **`/palestras`** (`PalestraController@index` ou page component) — listagem em cards (título,
  data, modalidade, palestrante(s) ativo(s), assuntos), paginada; ordenação por data desc.
- **`/palestras/{slug}`** — single (template T06): hero (cor de fundo da palestra), **palestrante(s)
  ativos** (card com foto/bio), vídeo (fachada YouTube, `loading=lazy`), data/modalidade, **acordeão
  de destaques** (`<details>`/`<summary>`), tags de assunto (links), navegação anterior/próxima.
- **Interações:** compartilhar (wa.me / facebook sharer / Web Share API), copiar link, curtir
  (Alpine + localStorage) — escopo mínimo; busca do header via Livewire.
- **SEO/A11y:** rotas limpas; `<title>`/meta/OpenGraph por página; `schema.org/Event` na single;
  semântica + `aria-*`; contraste — `secondary`/`accent` só em elementos grandes/ícones, nunca texto pequeno.
- **Performance:** HTML enxuto; imagens com `width/height` + lazy; fontes self-hosted (Bunny) já no build.

## Testes e verificação

- **Unit**: regra de cardinalidade (1–2 palestrantes, 0–1 diretor); `unserialize` do repeater →
  destaques na ordem; resolução por slug.
- **Feature**: importação **idempotente** (rodar 2× = mesmo estado, sem duplicatas); `/palestras` e
  `/palestras/{slug}` retornam 200 com o conteúdo certo; **só palestrantes ativos aparecem** no público;
  palestra com diretor não mostra o diretor inativo.
- **Manual** (no localhost): as 123 palestras corretas no público e no admin; responsivo
  (mobile/tablet/desktop); peso de HTML bem abaixo do WP atual; checagem de contraste.

## Critérios de pronto (Definition of Done)

- As 123 palestras migradas (com palestrante(s), diretor quando houver, assuntos e destaques).
- Admin permite criar/editar respeitando as cardinalidades.
- Listagem e single públicas, responsivas e fiéis ao design-system, exibindo só pessoas ativas.
- `php artisan test` verde + verificação manual no localhost.
- Página de palestra significativamente mais leve que a atual.

## Riscos / pontos de atenção

- **Túnel SSH**: a importação depende dele ativo; o comando deve detectar e orientar.
- **Direção oposta das relações Jet** (107 vs 108) — fonte de bug se ignorada (documentada).
- **Mapeamento de `status_palestrante`** e do **fuso** de `data_da_palestra` a confirmar com dados reais.
- **Mega-menu**: itens de módulos futuros não podem virar links quebrados (placeholder/desabilitado).
