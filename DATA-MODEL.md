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

## Próximos módulos (resumo)

Mesma abordagem por CPT: `evangelho`, `mensagem-mediunicas`, `_evento`,
`agenda-reforma`, `autores-espirituais`, posts/blog e páginas. Taxonomias
adicionais: `capitulos-do-evangelho`, `nivel-de-acesso` (vira controle de acesso
por roles na área de membros).
