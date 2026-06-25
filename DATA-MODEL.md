# DATA-MODEL — Novo site do CEMA

Modelado a partir do conteúdo real do site atual. Tudo via **migrations**;
nomes em pt-BR para o domínio. Esta versão cobre a **fatia Palestras** (Fase 1);
os demais módulos seguem o mesmo padrão.

## Tabelas — módulo Palestras

### `palestrantes`
Pessoas que ministram (palestrante) ou dirigem (diretor) — é o mesmo cadastro;
o papel é definido por palestra, no pivô.

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | usado na resolução da importação |
| foto | string null | caminho da imagem migrada |
| bio | text null | |
| timestamps | | |

### `palestras`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | |
| slug | string unique | |
| subtitulo | string null | (excerpt no WP) |
| descricao | longtext null | (content no WP; HTML) |
| data_da_palestra | datetime | sempre num domingo; vem de meta Unix |
| link_youtube | string null | preservar exatamente |
| cor_fundo | string null | `escolher_cor_do_fundo` |
| publico_online | int null | |
| publico_presencial | int null | |
| publico_total | int null | |
| status | string | `publicado`/`rascunho` |
| timestamps | | |

### `assuntos` (taxonomia hierárquica `assuntos-principais`)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | |
| parent_id | bigint null FK→assuntos.id | ~140 termos, hierárquicos |

### `palestra_pessoa` (pivô — relações 107 e 108)
| Coluna | Tipo | Notas |
|---|---|---|
| palestra_id | FK→palestras | |
| pessoa_id | FK→palestrantes | |
| papel | enum(`palestrante`,`diretor`) | **107**=palestrante, **108**=diretor |

Regra de negócio (validar na aplicação, não só no schema):
**1–2 palestrantes (obrigatório)** e **0–1 diretor (opcional)** por palestra.

### `assunto_palestra` (pivô N:N)
`palestra_id` FK · `assunto_id` FK.

### `palestra_destaques` (repeater "assuntos principais")
| Coluna | Tipo | Notas |
|---|---|---|
| palestra_id | FK→palestras | |
| destaque | string | título curto do tópico |
| texto | text | descrição |
| ordem | int | |

## Mapeamento REST (site atual) → MySQL

CPT `palestra_publica` (endpoint `/wp-json/wp/v2/palestra_publica`, `context=edit`):

| Origem (WP) | Destino |
|---|---|
| `title.rendered` | `palestras.titulo` |
| `slug` | `palestras.slug` |
| `excerpt` | `palestras.subtitulo` |
| `content` | `palestras.descricao` |
| `meta.data_da_palestra` (Unix) | `palestras.data_da_palestra` (datetime) |
| `meta.link_do_youtube` | `palestras.link_youtube` |
| `meta.escolher_cor_do_fundo` | `palestras.cor_fundo` |
| `meta.publico_online/presencial/total` | colunas correspondentes |
| `meta.assuntos_principais` (repeater) | linhas em `palestra_destaques` |
| taxonomia `assuntos-principais` | `assunto_palestra` (resolver por slug) |
| relação **107** (palestrante) | `palestra_pessoa` (papel=palestrante) |
| relação **108** (diretor) | `palestra_pessoa` (papel=diretor) |

Notas de importação:
- **Idempotente**: upsert por `slug` (palestras, palestrantes, assuntos).
- Resolver pessoas/assuntos **por slug**; logar o que não existir (não criar às cegas).
- Importação é **somente leitura** na origem (apenas GET).
- Campo protegido `_slides` (se necessário no futuro) exige snippet no WP — opcional.

## Confirmado/refinado pela introspecção do banco legado (2026-06-24)

Acesso read-only ao WP atual (detalhe e queries em `DB-LEGADO.md`). Pontos que afetam
diretamente a importação das palestras:

- **Direção das relações Jet é OPOSTA entre as duas** (atenção ao importar):
  - **107 (palestrante):** `parent_object_id` = palestrante, `child_object_id` = palestra
    → palestrantes de uma palestra: `parent WHERE child = {palestra_id}`.
  - **108 (diretor):** `parent_object_id` = palestra, `child_object_id` = diretor
    → diretor de uma palestra: `child WHERE parent = {palestra_id}`.
  - Cardinalidade real confere: 117 palestras com 1 palestrante, 7 com 2 (1–2 ✓); diretor 0–1.
- **`assuntos-principais` é mesmo hierárquica:** 141 termos, 46 com `parent ≠ 0` → manter `parent_id`.
- **Repeater `meta.assuntos_principais` é PHP serializado** (`item-N → {destaque, texto}`,
  ordem = índice) — a importação precisa `unserialize()` para popular `palestra_destaques`.
- **Descrição tem 3 fontes** (cobertura/123): `post_content` 59, meta `descricao` 54,
  `post_excerpt` 117. Precedência sugerida: `descricao` ← `post_content`; `subtitulo` ← `post_excerpt`.
- **Campo extra `meta.palestra_online`** (`"on"`/vazio) — flag online/presencial; avaliar incluir
  coluna `online` em `palestras`.
- **`palestrantes` (CPT):** nome ← `post_title`, slug ← `post_name`, bio ← `post_content`,
  foto ← `_thumbnail_id` (attachment). Meta disponível: `email_palestrante`, `telefone_palestrante`
  (+ flags `mostrar_*`), `status_palestrante` — incluir no cadastro novo se desejado.
- **Fonte da importação:** além da REST (GET), o **banco `legado` (read-only)** é fonte direta e
  mais completa — decidir na Fase 1 qual usar (as relações Jet, por exemplo, são triviais via banco).

## Comentários do blog (Fase 2)

Comentar **não exige conta**: visitante informa nome + e-mail; quem está logado
fica vinculado ao usuário. Sistema próprio (Livewire + moderação no Filament),
sem widget de terceiro. Decisão registrada em `PROJECT.md`.

### `comentarios`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| post_id | FK→posts | postagem comentada |
| parent_id | bigint null FK→comentarios.id | respostas encadeadas (thread) |
| usuario_id | bigint null FK→users.id | preenchido **só** se logado (conta opcional) |
| autor_nome | string | nome exibido (logado ou anônimo) |
| autor_email | string | **nunca público** — moderação/notificação/avatar |
| conteudo | text | corpo do comentário |
| status | enum(`pendente`,`aprovado`,`spam`,`lixeira`) | fluxo de moderação |
| ip | string null | rate limit / anti-abuso |
| user_agent | string null | anti-abuso (opcional) |
| consentimento_lgpd | bool | aceite da política (fluxo anônimo) |
| timestamps | | |

Regras de negócio (validar na aplicação):
- **Anônimo permitido**: `autor_nome` + `autor_email` obrigatórios quando não há
  `usuario_id`; com login, herdar nome/e-mail do usuário.
- **Moderação progressiva**: 1º comentário de um `autor_email` entra `pendente`;
  após **um** aprovado, próximos do mesmo e-mail entram `aprovado` automaticamente.
- **Anti-spam**: honeypot + hCaptcha condicional + rate limit por IP; opcional
  Akismet/lista de palavras → marca `spam`.
- **LGPD**: exigir `consentimento_lgpd` no fluxo anônimo; e-mail só interno.
- Exibir publicamente apenas `status = aprovado`.

## Próximos módulos (resumo)

Mesma abordagem por CPT: `evangelho`, `mensagem-mediunicas`, `_evento`,
`agenda-reforma`, `autores-espirituais`, posts/blog e páginas. Taxonomias
adicionais: `capitulos-do-evangelho`, `nivel-de-acesso` (vira controle de acesso
por roles na área de membros).
