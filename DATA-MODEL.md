# DATA-MODEL — Novo site do CEMA

Modelado a partir do conteúdo real do site atual. Tudo via **migrations**;
nomes em pt-BR para o domínio. Cobre os módulos já implementados (Mídia,
Palestras, Blog "Sementeira de Luz", Usuários/organização, Agenda, Eventos,
Mensagens mediúnicas + Autores espirituais) e o **modelo de capacidades + auditoria**
(quem edita o conteúdo, Fases A→D), além dos planejados (Comentários); os demais
seguem o mesmo padrão.

## Mídia (Spatie Media Library)

Tabela `media` (`database/migrations/2026_06_26_204926_create_media_table.php`) é o
repositório único de arquivos de imagem do sistema. A Media Library guarda o
**original enviado** — capado ao teto de cada coleção (≤2000px; ≤1200px na coleção
`og`) pelo listener `App\Listeners\CaparOriginalDaMidia`, sem trocar de formato — e
gera as **conversões em WebP** `web` e `thumb` (síncronas) via trait reutilizável
`App\Models\Concerns\RegistraImagensPadrao`. Usada por: `Palestrante` (coleção
`foto`), `Post` (coleções `destacada`, `galeria`, `og`, `conteudo`, `corpo`),
`PerfilMembro` (coleção `foto`), `Biblioteca` (coleção `biblioteca`),
`ConfiguracaoAgenda` (coleção `capa`).

## Tabelas — módulo Palestras

### `palestrantes`
Pessoas que ministram (palestrante) ou dirigem (diretor) — é o mesmo cadastro;
o papel é definido por palestra, no pivô.

| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| nome | string | |
| slug | string unique | usado na resolução da importação |
| bio | text null | |
| email | string null | |
| telefone | string null | |
| mostrar_email | bool default false | |
| mostrar_telefone | bool default false | |
| ativo | bool default true | |
| chamada | string null | texto de chamada/destaque do palestrante |
| timestamps | | |

Foto via Media Library (coleção `foto`, tabela `media`), não coluna — accessors
`foto_url`/`foto_thumb_url` (conversões `web`/`thumb`; ver seção "Mídia" acima).

### `palestras`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | |
| slug | string unique | |
| subtitulo | string null | (excerpt no WP) |
| resumo | text null | resumo curto adicional |
| descricao | longtext null | (content no WP; HTML) |
| referencias_evangelicas | text null | referências evangélicas (texto livre) |
| data_da_palestra | datetime | sempre num domingo; vem de meta Unix |
| duracao | string(40) null | duração da palestra |
| link_youtube | string null | preservar exatamente |
| slide | string null | link do slide (Drive) |
| cor_fundo | string null | `escolher_cor_do_fundo` |
| online | bool default false | flag online/presencial |
| publico_online | int null | |
| publico_presencial | int null | |
| publico_total | int null | |
| curtidas | unsigned int default 0 | contador de curtidas públicas |
| status | string | `publicado`/`rascunho` |
| timestamps | | |

Colunas `slide`/`duracao`/`referencias_evangelicas`/`curtidas` vieram depois da
criação (migration `2026_06_29_100001_add_slide_duracao_refs_curtidas_to_palestras.php`).

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

### `palestra_referencias` (repeater de obras/referências citadas)
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| palestra_id | FK→palestras | |
| obra | string | |
| autor | string null | |
| nota | text null | |
| ordem | unsigned int default 0 | |
| timestamps | | |

Model `PalestraReferencia`; relação `Palestra::referencias()`.
(migration `2026_06_29_100002_create_palestra_referencias_table.php`)

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

## Blog "Sementeira de Luz" (Fase 2 — Fatia 1)

Posts editoriais. Editor **RichEditor (TipTap/HTML)** — `conteudo` guarda o HTML do Gutenberg
preservado (limpo + sanitizado). Modelo **confirmado pela introspecção do legado (2026-06-26)**;
design e decisões em `docs/superpowers/specs/2026-06-26-blog-sementeira-de-luz-design.md`.

**Introspecção (resumo):** blog no site principal do multisite (`blog_id=1`, prefixo `wp_`).
44 posts publish. Conteúdo Gutenberg em `post_content` (wrappers JetStyleManager `jet-sm-gb-*` a
limpar; `_elementor_data` é resíduo). Imagem destacada 100% (`_thumbnail_id`). **FAQ** em meta
`_faq` (repeater serializado `{_pergunta_faq, _resposta_faq}`, 28/44). **Galeria** em meta
`_fotos_carrossel_` (serializado `{id, url}`, 14/44). Categorias: taxonomia `category` — 5 reais
+ "Sem categoria"; `post_tag` quase sem uso. SEO: Rank Math (`rank_math_description` 20,
`rank_math_focus_keyword` 30, `rank_math_primary_category` 30, `rank_math_title` 1) com resíduo
Yoast. **Sem `nivel-de-acesso`** nos posts → 100% público. **Sem autor público** (blog assinado
pela instituição; autoria só administrativa). Vínculo **post↔palestra** = Jet `wp_jet_rel_default`
rel_id=200 (12 vínculos) — deferido (guardar `wp_id`). Permalink atual `/%postname%/` (raiz) →
nova URL `/sementeira/{slug}` + 301.

### `posts`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | `post_title` |
| slug | string unique | `post_name` (idempotência) |
| resumo | text null | dek ← `post_excerpt` |
| conteudo | longtext | `post_content` (HTML limpo + sanitizado) |
| imagem_destacada_alt | string null | ← `_wp_attachment_image_alt` (alt da imagem da coleção `destacada`) |
| criado_por_id | bigint null FK→users | autoria **administrativa** (não pública); null na importação |
| categoria_principal_id | bigint null FK→categorias | ← `rank_math_primary_category` |
| destaque | bool default false | herói da listagem (fallback: mais recente) |
| tempo_leitura_min | smallint | calculado (~200 ppm) |
| visualizacoes | unsigned int default 0 | "Mais lidas" |
| data_publicacao | datetime | `post_date` |
| status | enum(`publicado`,`rascunho`,`agendado`) | `post_status` |
| wp_id | unsigned bigint unique null | id legado (idempotência + vínculo post↔palestra futuro) |
| seo_titulo / seo_descricao / seo_keyword | string null | Rank Math → fallback Yoast |
| robots_noindex | bool default false | controle de indexação |
| canonical | string null | URL canônica custom (raro) |
| timestamps | | |

Imagem destacada, galeria e OG customizado vivem na Media Library (tabela `media`),
coleções `destacada`/`galeria`/`og` do model `Post` (ver seção "Mídia" acima) — não
são mais colunas de `posts` nem tabela `post_imagens` própria.

### `categorias`
`id` · `nome` · `slug` unique · `cor` (hex do design) · `descricao` null · `ordem` · `wp_term_id` null.
5 reais: Reflexões e Espiritualidade (`#4E4483`), Estudando a Mediunidade (`#6E9FCB`), Prática do
Amor ao Próximo (`#89AB98`), Datas Comemorativas (`#F2A81E`), CEMA em Ação (`#E79048`) + "Sem categoria".

### `tags`
`id` · `nome` · `slug` unique · `wp_term_id` null. (Pouco usada no legado — secundária.)

### Pivôs e filhos
- `categoria_post` (N:N): `post_id` FK · `categoria_id` FK.
- `post_tag` (N:N): `post_id` FK · `tag_id` FK.
- `post_faqs`: `id` · `post_id` FK · `pergunta` · `resposta` text · `ordem`. ← meta `_faq`.

### `bibliotecas`
Singleton (`tipo` unique, default `principal`) dono da coleção de Media Library
`biblioteca`: pool central de imagens reutilizáveis inseridas no corpo dos posts,
referenciadas por URL estável `/midia/{id}/web`. (migration
`2026_06_28_000002_create_bibliotecas_table.php`; model `Biblioteca`.)

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

## Módulo Usuários e Área de membros (implementado — audiência pendente)

Modelado no estudo de 2026-06-25 (introspecção do legado + verificação adversarial
multiagente). Resolve a classificação de usuários separando **quatro dimensões
ortogonais** que o WordPress achatava num único "papel". Tudo via migrations; CRUD
no Filament (nada hardcoded); importação idempotente a partir do banco `legado`.

Catálogos, pivôs de lotação/atributo e RBAC (Spatie) já implementados (migrations
`2026_07_03_*` e `2026_07_04_*`).

**Estado das 4 dimensões (2026-07-16):**
- **Papel/autorização (1)** e **lotação (2)** — implementados. O eixo **CAPACIDADE**
  ("quem edita") foi resolvido pelas **Fases A→D** (seção "Modelo de capacidades" abaixo):
  o escopo de escrita vem do pivô **`departamento_usuario`** (vínculo editorial), não de
  `cargo_usuario` — os comandos `cema:vincular-diretores-departamento` e
  `cema:vincular-presidentes-departamentos` populam esse vínculo a partir dos cargos.
- **Atributos (3)** — implementado (`atributos` + pivô; sócio).
- **Audiência (4)** — a tabela polimórfica **`audiencias` NÃO foi construída**. A
  **VISIBILIDADE** hoje é **por módulo** (ex.: `eventos.visibilidade` via enum
  `VisibilidadeEvento`; scopes de publicação). A audiência polimórfica segue como design
  a revisitar quando o gate por `nivel-de-acesso` for atacado.
- **`log_mudancas_papel`** — **não construída**: a auditoria foi resolvida de forma
  transversal por `spatie/laravel-activitylog` (tabela `activity_log`, seção abaixo).

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
`nome`, `slug` unique, `provisorio` (bool — vínculo incerto), `ativo`.

**`cargos`** — direção: `nome`, `slug` unique, `departamento_id` **nullable**,
`institucional` (bool), `ativo`.

**`atributos`** — marcas ortogonais (hoje só sócio): `nome`, `slug` unique, `descricao?`.

### Usuário e perfil

**`users`** (acréscimos à auth): `socio` (bool, indexado — espelho de
`atributo_usuario`), `origem_legado_id?` (unique — `wp_users.ID`), `ativo`,
`google_id?` (unique — id do provedor Google/Socialite). **Identidade = e-mail**
(login por e-mail; login social casa por `google_id`/e-mail).

**`perfis_membro`** (1:1): `user_id` unique, `whatsapp?`, `whatsapp_publico` (bool),
`data_nascimento?`, `endereco?` (**texto único**, sem parser). Foto via Media
Library (coleção `foto`, conversões `web` ≤640px + `thumb` 400×400 quadrado —
accessors `foto_url`/`foto_thumb_url`), não coluna.

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
**desligado**; `register_permission_check_method => false` (o nome cru `agenda.editar`
**não** é ability de Gate — a leitura de capacidade é `hasPermissionTo` nas policies).
Hierarquia = **permissões cumulativas** (cada nível contém o anterior); `nivel` serve
para comparação por papel. 4 papéis fixos (`frequentador` 10 < `trabalhador` 20 <
`diretor` 30 < `administrador` 100), semeados por `EstruturaCemaSeeder`.

O CRUD de papel→permissão é a página **`/admin/matriz-capacidades`** (Fase C) — **não**
usamos Filament Shield. Ver "Modelo de capacidades" abaixo.

### Decisões do dono (2026-06-25)

- **PAMANA**: atributo `externo` que o **exclui** das audiências internas.
- **Quem cria diretor**: **só o administrador** (admin = governança de papéis;
  presidente = poder editorial total, mas não gere diretores).
- **Audiência por papel**: oferecer **os dois modos** ("este nível e acima" / "exato").
- **Hierarquia de papéis**: **estritamente linear** (frequentador < trabalhador <
  diretor < administrador).
- **Escopo de escrita do diretor**: só via `cargo_usuario` (correção de segurança).
- **Médium publica / diretor define nível** — ⚠️ **SUPERADA pela Camada 4 / Fatia F4b**
  (2026-07-21). Valia até aqui: permissões separadas (`mensagens.publicar` via vínculo ao
  setor "Médium"; `mensagens.definir-nivel` só p/ diretor do depto), i.e. capacidade pela
  matriz papel×permissão. Passou a valer: a F4b **não** usa a matriz de capacidades para
  Mensagem — as permissions `mensagem.*` seguem semeadas mas **inertes** (nunca ligadas via
  `/admin/matriz-capacidades`). O eixo real é **pertencimento por setor/cargo**, resolvido em
  métodos próprios da `MensagemPolicy` (`lancar`/`editarPendente`/`curar`/
  `editarNaCuradoria`/`publicar`), que consultam `User::ehMedium()` (setor "Médium"),
  `User::ehDiretorDepae()` (cargo Diretor do DEPAE) e `User::ehPresidente()` — nunca
  `hasPermissionTo`. Ver "Edição pelo site — Mensagens mediúnicas" acima.

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

## Modelo de capacidades — quem EDITA o conteúdo (Fases A→D)

Eixo **CAPACIDADE** ("quem edita"), distinto de **VISIBILIDADE** ("quem vê"). O `/admin`
é **exclusivo de administrador** (`User::canAccessPanel` → `hasRole('administrador')` é o
**único** portão do painel); o não-admin edita pelo **site** (`/minha-conta`).

**Três condições, todas exigidas** para um não-admin editar um objeto (**fail-closed** —
faltando qualquer uma, nega). O admin passa antes, no `Gate::before`:

| Condição | Origem | Onde vive |
|---|---|---|
| **capacidade** (`hasPermissionTo('recurso.acao')`) | papel → permissão | `role_has_permissions` (escrito **só** pela matriz) |
| **vínculo** do usuário a um departamento | atribuição editorial | `departamento_usuario` |
| **objeto** num departamento em comum | departamento do conteúdo | `departamento_<conteudo>` |

### Capacidades (permissions)

**20 nomes `recurso.acao`** (guard `web`), produto de `GlossarioCapacidades`
(`app/Support/Autorizacao/`): **recursos** `evento`, `palestra`, `post`, `agenda`,
`palestrante` × **ações** `ver`, `criar`, `editar`, `excluir`. Semeadas por
`CapacidadesSeeder` (a Biblioteca fica fora — singleton admin-only). O glossário também
guarda os **rótulos legíveis** (`RECURSOS_ROTULOS`/`ACOES_ROTULOS`; `agenda` → "Agenda do Dia").

`role_has_permissions` é escrito **exclusivamente** pela página `/admin/matriz-capacidades`
(`Role::syncPermissions`), que só toca os papéis **editáveis** (`GlossarioUsuarios::PAPEIS_EDITAVEIS`
= `trabalhador`, `diretor`) — **nunca** admin/frequentador. Não há comando/seeder que
escreva essa tabela: **ligar a matriz é passo de cutover manual, por ambiente**.

### `departamento_usuario` (vínculo editorial)

`(user_id, departamento_id)` FK cascade + `unique(user_id, departamento_id)`; **sem
timestamps**. Relação `User::departamentos()`. É o **escopo de escrita** — populado pela
UI (`Select` no `UserResource`) e pelos comandos `cema:vincular-diretores-departamento`
(cargos com departamento) e `cema:vincular-presidentes-departamentos` (presidente → os 8).

### Departamento do conteúdo (5 pivôs, mesmo molde)

`departamento_palestra` · `departamento_post` · `departamento_palestrante` ·
`departamento_agenda_dia` · `departamento_evento`. Molde: `id` próprio + `<conteudo>_id`
FK cascade + `departamento_id` FK cascade + `unique(<conteudo>_id, departamento_id)`.

⚠️ **Os pivôs têm `id` próprio** → `pluck('id')` numa relação `belongsToMany` dá
*ambiguous column*: **sempre qualificar** (`departamentos.id` / `departamentos.nome`).

Os 5 models implementam o contrato **`App\Models\Contracts\TemDepartamento`**
(`departamentos(): BelongsToMany`). O escopo é aplicado pelo trait
**`App\Policies\Concerns\AutorizaPorDepartamento`** (interseção `whereIn(...)->exists()`;
fail-closed dos dois lados: usuário **ou** objeto sem departamento ⇒ `false`), usado pelas
**5 policies** (`Evento`/`Palestra`/`Post`/`AgendaDia`/`Palestrante`), com abilities em
pt-BR (`ver`/`criar`/`editar`/`excluir`).

### `activity_log` (auditoria — spatie/laravel-activitylog ^4.12)

Tabela padrão do pacote (migrations `2026_07_13_191455/56/57`): `log_name`, `description`,
`subject_type`/`subject_id`, `causer_type`/`causer_id`, `properties` (json), **`event`**,
`batch_uuid`, timestamps. Trilha **append-only** (só escrita; não há viewer).

- **`log_name`**: `usuario` (trait no `User`), `autorizacao` (log manual dos 3 pivôs de
  autorização), `agenda` (trait no `AgendaDia` + o log manual do depto↔conteúdo), `mensagem`
  (trait no `Mensagem` — lançamento pelo médium e curadoria pelo diretor do DEPAE/presidente,
  Fatia F4b).
- **`mensagem` redige o conteúdo restrito na escrita**: `tapActivity()` substitui os valores
  de `corpo` e `resumo` (nos blocos `attributes` e `old` da entrada) pelo literal
  `'[texto não registrado]'`, preservando a **chave** (`array_key_exists`, nunca `isset` — o
  valor pode ser `null` e é a chave que importa manter). Decisão do dono: a retenção da
  trilha é indefinida, e sem a redação ela acumularia cópia integral de mensagens de níveis
  restritos (Trabalhadores/Diretores/Médiuns/Diretor-DEPAE/Direcionada).
- **Helper `App\Support\Autorizacao\AuditoriaAutorizacao`** — fonte única do contexto:
  `porta()` + `contexto()` (porta + IP + user-agent) + `diff()` + os `registrar*`.
- **`porta`** (em `properties` de toda entrada): **`admin`** (painel Filament) ·
  **`sistema`** (CLI/importadores) · **`perfil`** (`/minha-conta`). Como `/minha-conta`
  **não** é painel Filament (`Filament::getCurrentPanel()` = null), a porta `perfil` é
  **marcada explicitamente** por um override estático setado no `boot()` do componente
  Livewire (roda em mount **e** hydration → cobre o `POST /livewire/update` do save).
- `AgendaDia` loga os **7 campos** de conteúdo (`data`, `status`, `reflexao`,
  `meta_mes_texto`, `meta_dia_titulo`, `meta_dia_texto`, `prece`) com `logOnlyDirty` +
  `dontSubmitEmptyLogs`. O vínculo **N:N depto↔conteúdo não é capturado pelo `logOnly`**
  → log **manual** (`registrarDepartamentosConteudo`, `log_name='agenda'` — mesma trilha).

### Edição pelo site (`/minha-conta`) — piloto da Agenda (Fase D)

Rota `conta.agenda` (`/minha-conta/agenda`); aba condicional (capacidade `agenda.ver`
**+** o usuário ser **responsável pelo tipo** — a Camada 1 revogou, no §6.3, a "decisão 1"
da Fase D, que exigia existir registro no escopo). O form vem da **fonte única**
`App\Filament\Schemas\AgendaDiaForm::schema()` — **sem parâmetro**: o campo
`departamentos` não existe mais no schema, nem no painel nem no site.

**1 campo privilegiado, forçado no servidor** (nunca do POST):
- **`status`** — reasserido contra o enum; quem tem `agenda.criar` mas **não**
  `agenda.editar` só cria **rascunho**.

O `departamentos` **deixou de ser gravado**: o pivô `departamento_agenda_dia` está
**congelado** (§6.4 da Camada 1) — nem lido, nem gravado, nada apagado. Quem responde pela
Agenda vem da **Configuração de acesso por tipo**, não do registro.

### Edição pelo site — Mensagens mediúnicas (Camada 4 / Fatia F4b)

Duas superfícies novas em `/minha-conta`, fora do padrão capacidade+departamento acima: o
eixo aqui é **AUTORIA por pertencimento a setor/cargo**, não a matriz de capacidades (as
permissions `mensagem.*` continuam **inertes** — ver revogação abaixo). `MensagemPolicy`
concentra as regras em métodos próprios (`lancar`/`editarPendente`/`curar`/
`editarNaCuradoria`/`publicar`), que consultam `User::ehMedium()` (setor "Médium"),
`User::ehDiretorDepae()` (cargo Diretor do DEPAE) e `User::ehPresidente()` — nunca
`hasPermissionTo`. O admin passa antes, no `Gate::before`, em ambas.

- **`conta.mensagens`** (componente `MensagensConta`) — o **médium** lança e edita as
  próprias mensagens. Toda mensagem nasce **`status = pendente`**, com `nivel = null` (o
  médium não escolhe o nível de acesso) e, enquanto pendente, só ele pode editá-la
  (`editarPendente`: exige `medium_id === auth()->id()` e `status = pendente`). A lista é
  escopada às próprias (`where('medium_id', auth()->id())`).
- **`conta.curadoria`** (componente `CuradoriaConta`) — o **diretor do DEPAE** (ou o
  presidente) vê a fila de todas as pendentes, corrige título/corpo/autores/**imagens**
  (`salvar()` nunca muda o status) e aplica o **martelo** (`publicar()`): arbitra o `nivel`
  (via `App\Support\Mensagens\RegraPublicacao`), grava `status = publicado`,
  `publicado_por_id = auth()->id()` e `publicado_em = now()`. Depois de publicada, a posse
  passa ao curador — o médium deixa de poder editá-la pelo site.

**3 campos privilegiados, sempre forçados no servidor** (nunca do POST — `unset` explícito
antes de montar o registro em ambos os componentes):
- **`medium_id`** (FK→`users`, nullable, `nullOnDelete`) — quem lançou; atribuído só em
  `criarRegistro()`, nunca reasserido depois. **`null` significa "importada do legado"**: as
  mensagens vindas do WordPress não têm autoria real (lá foram cadastradas por contas
  técnicas do DECOM) — atribuí-las a um médium seria dado falso.
- **`publicado_por_id`** (FK→`users`, nullable, `nullOnDelete`) — quem publicou, gravado
  **sempre na transição** para `publicado` — pelo `publicar()` do site, mas também pela
  Action "Publicar" do `/admin` e pelos hooks de `EditMensagem`/`CreateMensagem` (Fatia
  F4c-AC): os três caminhos gravam, nunca por estado (uma mensagem já publicada não
  reescreve o campo ao ser apenas salva de novo).
- **`publicado_em`** (timestamp nullable) — quando foi publicada, gravado junto, na mesma
  transição.

(migration `2026_07_21_000001_add_autoria_to_mensagens_table.php`.) Os três **não estão em
`$fillable`** (atribuição é sempre direta, `$model->campo = valor`) e estão em `$hidden` no
model `Mensagem` — nunca saem em `toArray()`/`wire:snapshot`.

- **`resumo`** (`text` nullable) — texto editorial escrito pelo médium ou pela curadoria (a D2
  da F4c-D revoga o I11 da F4c-AC), importado do `post_excerpt` do legado por
  `cema:importar-resumos` (só preenche o que está vazio). Texto
  puro, sem HTML; aparece no card, na meta description e como lead do single. Está no
  `$fillable`, no `logOnly` e no glossário, e é **redigido** no `tapActivity()`.
- **Mídia** — a coleção de `Mensagem` chama-se **`imagens`** (`Mensagem::COLECAO_IMAGENS`,
  ex-`pictografia`) e vale para os **3 formatos**; na Pictografia os desenhos SÃO a mensagem.

## Agenda

Devocional diário "Agenda de Reforma Íntima", migrado do legado.

### `agenda_metas_mes`
`id` · `ano` · `mes` · `titulo` · unique(`ano`,`mes`).

### `agenda_dias`
`id` · `data` unique · `reflexao` (HTML) · `meta_mes_texto` (HTML) · `meta_dia_titulo`
· `meta_dia_texto` (HTML) · `prece` (HTML) · `status` (`publicado`/`rascunho`) ·
`wp_id` unique null (rastreio do legado).

`data` usa **mutator `Attribute`** (get → Carbon; set → string `Y-m-d`), **não** cast:
o cast nativo diverge entre SQLite (testes) e MySQL (prod). Consultar/upsert por strings
`Y-m-d`. Os 4 campos HTML são sanitizados por mutator (`clean($v,'conteudo')`) — cobre
admin, site e importador.

Departamentalizado (Fase B) via pivô **`departamento_agenda_dia`**; implementa
`TemDepartamento`. Auditado (Fase D) por `LogsActivity` (`log_name='agenda'`, os 7 campos).
Ver "Modelo de capacidades" acima.

### `agenda_slugs_legado`
`id` · `slug` unique (post_name legado, numérico ou de data) · `data` (destino do
301 — N slugs podem apontar para a mesma data). Sem timestamps.

### `agenda_configuracoes`
Singleton dono da coleção de Media Library `capa` — capa da Agenda, conversão
`web` ≤1200px.

(migrations `2026_07_02_000001` a `2026_07_02_000004`; models `AgendaMetaMes`,
`AgendaDia`, `AgendaSlugLegado`, `ConfiguracaoAgenda`.)

## Eventos

Agenda institucional (CPT `_evento` no legado), importada por `cema:importar-eventos`.

### `eventos`
| Coluna | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| titulo | string | |
| slug | string unique | idempotência |
| resumo | text null | chamada / SEO |
| conteudo | longtext null | HTML |
| data_inicio | date (index) | |
| hora_inicio | string(5) null | vazio = "dia inteiro" |
| data_fim | date null (index) | |
| hora_fim | string(5) null | |
| local | string null | |
| categoria_evento_id | FK→categorias_evento null | `nullOnDelete` |
| visibilidade | string default `publico` (index) | enum `VisibilidadeEvento` |
| status | string default `publicado` (index) | `publicado`/`rascunho` |
| wp_id | unsigned bigint unique null | rastreio do legado |
| timestamps | | |

**`VisibilidadeEvento`** (`app/Enums/`): `publico` · `logados` · `trabalhadores` ·
`diretoria`. É o eixo **VISIBILIDADE** ("quem vê") — hoje o único módulo com gate próprio.

Mídia via Media Library: coleções `flyer` (capa) e `galeria` (múltiplas, `web` ≤1920px).
A rede server-side de período (`App\Support\Eventos\PeriodoEvento`) valida o que as regras
de campo não pegam (hora de término sem início; hora fora de `HH:MM`) — **todo consumidor
novo do schema precisa aplicá-la**; `getState()` sozinho não basta.

### `categorias_evento`
`id` · `nome` · `slug` unique · `cor` (7) · `cor_texto` (7) null · `icone` null ·
`ordem` · `ativo` · timestamps.

### `departamento_evento`
Pivô N:N (departamentos organizadores) — molde dos 5 pivôs de departamento (ver "Modelo
de capacidades"). `Evento` implementa `TemDepartamento`.

**`departamentos`** ganhou `cor` (7) null e `icone` null (migration `2026_07_08_000002`)
para o calendário unificado.

O form vive em **fonte única** `App\Filament\Schemas\EventoForm::schema(): array`
(consumida por `EventoResource`) — molde que a Fase D replicou para `AgendaDiaForm`.

## Configurações

### `configuracoes`
`id` · `chave` unique · `valor` text null · timestamps. Store genérico
chave/valor (model `Configuracao`, helpers estáticos `valor()`/`definir()`).

## Próximos módulos (resumo)

Mesma abordagem por CPT, para o que **ainda falta**: `evangelho` e `page` (páginas
institucionais). Taxonomia adicional: `capitulos-do-evangelho`. A taxonomia
`nivel-de-acesso` do WP foi resolvida, **para Mensagem**, sem importar o termo bruto: o
enum `VisibilidadeMensagem` (6 níveis — ver "Edição pelo site — Mensagens mediúnicas"
acima) é o gate de **VISIBILIDADE** por papel/pertencimento do módulo; `Evento` mantém o
seu próprio, via `VisibilidadeEvento`.

(Já entregues: `palestra_publica`, `palestrantes`, `agenda-reforma`, `_evento`, `post`/blog,
`mensagem-mediunicas` e `autores-espirituais` — ver [ROADMAP.md](ROADMAP.md).)
