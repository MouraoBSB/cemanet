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

## Módulo Usuários e Área de membros (fase futura)

Modelado no estudo de 2026-06-25 (introspecção do legado + verificação adversarial
multiagente). Resolve a classificação de usuários separando **quatro dimensões
ortogonais** que o WordPress achatava num único "papel". Tudo via migrations; CRUD
no Filament (nada hardcoded); importação idempotente a partir do banco `legado`.

### Princípio — 4 dimensões ortogonais

| # | Dimensão | Pergunta | Mecanismo |
|---|---|---|---|
| 1 | **Papel / autorização** | O que **pode fazer**? | Spatie roles + permissions (sem *teams*) |
| 2 | **Lotação** | **Onde** atua/dirige? | `departamentos`/`setores`/`cargos` + pivôs |
| 3 | **Atributos** | Que **marcas** carrega? | `atributos` + pivô (sócio) |
| 4 | **Audiência** | Quem **pode ver** o conteúdo? | `audiencias` polimórfica (interseção) |

Duas perguntas de autorização, mecanismos distintos:
- **Agir (escrita):** permissão + **Policy** com escopo no departamento **de direção**
  do registro (vindo só de `cargo_usuario`, **nunca** de setor — senão um diretor
  com setor em outro depto editaria fora do escopo).
- **Ver (leitura):** **interseção** entre as dimensões do usuário e a audiência do
  conteúdo. É filtro de busca, não permissão. Conteúdo **sem audiência = privado**
  (fail-closed).

### Catálogos (CRUD)

**`departamentos`** — 8 (espinha dorsal): `sigla` unique, `nome`, `slug` unique,
`descricao?`, `ativo`, `ordem`.

**`setores`** — atividades; `departamento_id` **nullable** (nulo = PAMANA / incertos),
`nome`, `slug` unique, `edita_conteudo` (bool), `provisorio` (bool — vínculo incerto),
`ativo`.

**`cargos`** — direção: `nome`, `slug` unique, `departamento_id` **nullable**,
`institucional` (bool), `alcance` enum(`departamental`,`institucional_restrito`,`total`),
`ativo`.

**`atributos`** — marcas ortogonais (hoje só sócio): `nome`, `slug` unique, `descricao?`.

### Usuário e perfil

**`users`** (acréscimos à auth): `login?` (referência/rastreio, **não** é credencial),
`socio` (bool, indexado — espelho de `atributo_usuario`), `origem_legado_id?` (unique —
`wp_users.ID`), `ativo`. **Identidade = e-mail** (login por e-mail).

**`perfis_membro`** (1:1): `user_id` unique, `whatsapp?`, `whatsapp_publico` (bool),
`data_nascimento?`, `endereco?` (**texto único**, sem parser), `foto_perfil?`.

**`cursos_realizados`** (1:N): `user_id`, `nome`, `ano?`, `local?`, `ordem`.

### Pivôs de lotação e atributo

**`setor_usuario`** (onde **atua**): `(setor_id, user_id)` PK, `funcao`
enum(`membro`,`coordenador`) default `membro`, `desde?`.
**`cargo_usuario`** (onde **dirige**): `(cargo_id, user_id)` PK.
**`atributo_usuario`**: `(atributo_id, user_id)` PK, `desde?`, `ate?`.

### Audiência de conteúdo (polimórfica)

**`audiencias`**: `conteudo_type`/`conteudo_id` (morph), `alvo_tipo`
enum(`publico`,`papel`,`setor`,`departamento`,`cargo`,`atributo`,`pessoa`), `alvo_id`
(sentinela `0` quando `publico`), `modo` enum(`incluir`,`excluir`) default `incluir`,
`grupo?` (linhas do mesmo grupo casam em **E**; grupos distintos em **OU**). Índices:
`(conteudo_type, conteudo_id)`, `(alvo_tipo, alvo_id, modo)`. Catálogos com soft-delete
(id nunca reaproveitado) + observer p/ não deixar audiência órfã.

**`log_mudancas_papel`** (auditoria imutável): `user_id`, `ator_id`, `acao`, `de?`,
`para?`, `contexto?` (json), `created_at`.

### RBAC

Tabelas padrão do Spatie (`roles` **+ coluna `nivel`**, `permissions`, pivôs); *teams*
**desligado**. Hierarquia = **permissões cumulativas** (cada nível contém o anterior);
`nivel` serve só para comparação de audiência por papel. Filament Shield para o CRUD
de papéis/permissões — **edição restrita ao admin**.

### Decisões do dono (2026-06-25)

- **PAMANA**: atributo `externo` que o **exclui** das audiências internas.
- **Quem cria diretor**: **só o administrador** (admin = governança de papéis;
  presidente = poder editorial total, mas não gere diretores).
- **Audiência por papel**: oferecer **os dois modos** ("este nível e acima" / "exato").
- **Hierarquia de papéis**: **estritamente linear** (frequentador < trabalhador <
  diretor < administrador).
- **Escopo de escrita do diretor**: só via `cargo_usuario` (correção de segurança).
- **Médium publica / diretor define nível**: permissões separadas (`mensagens.publicar`
  via vínculo ao setor "Médium"; `mensagens.definir-nivel` só p/ diretor do depto).

### Migração (a partir do banco `legado`)

- **Senhas — PROVADO migrar sem reset.** Formatos `$wp$` (WP 6.8 bcrypt, ~80) e `$P$`
  (phpass, ~75). Hasher custom reconhece os dois (`$wp` → `password_verify` sobre o
  pré-hash `base64(hmac-sha384(senha,'wp-sha384'))` em `substr($h,3)`; `$P$`/`$H$` →
  `crypt_private` phpass) + rehash transparente no 1º login. Validado contra hash real
  (`usuario-teste`) e por round-trip.
- **Identidade = e-mail** (0 vazios, 0 duplicados no legado); `user_login` descartado.
- **Nomes**: sanitizar para Title Case (UTF-8) com preposições minúsculas
  (de/da/do/das/dos/e); fonte = `display_name`.
- **Campos**: migrar whatsapp (73%), data_nascimento (70%), endereco (59%),
  whatsapp_publico (18%), cursos_realizados (1:N). Fotos = **passo opcional**
  (local → avatar → Google → Gravatar por e-mail; senão iniciais). **Descartar**
  `email_principal` (redundante), `sobre_mim`/`o_que_espera_da_doutrina`/redes sociais
  (<2%), banner, `vinculo_com_a_casa` (tab vazia).
- **Excluir** os 4 admins (contas técnicas: DECOM1, n8n-admin-cemanet, fullservice,
  "Agenda Reforma - Claude"); revisar o `subscriber`.
- **Idempotência**: chave `origem_legado_id` → e-mail; pivôs por `sync`; repeaters por
  delete+recriação ordenada; senha nunca sobrescrita após rehash.
- **Em aberto p/ fase de mensagens**: "Direcionada" (nível de acesso por pessoa/grupo)
  será resolvido quando as mensagens mediúnicas forem migradas.

Detalhes do legado e cobertura real em [DB-LEGADO.md](DB-LEGADO.md).

## Próximos módulos (resumo)

Mesma abordagem por CPT: `evangelho`, `mensagem-mediunicas`, `_evento`,
`agenda-reforma`, `autores-espirituais`, posts/blog e páginas. Taxonomias
adicionais: `capitulos-do-evangelho`, `nivel-de-acesso` (vira controle de acesso
por roles na área de membros).
