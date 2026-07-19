# Spec — Camada 4 · Fatia 2A · Mensagem + migração (o GATE, camada de dados)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-18
> Enquadramento travado com o dono no kickoff da Camada 4 (Mensagens Mediúnicas). Este spec **não** improvisa
> além das decisões travadas; **cada afirmação sobre o terreno foi verificada contra o código real** (evidência
> `arquivo:linha` no §3, levantada por 7 leitores paralelos) e o **legado foi medido AO VIVO** pela conexão
> `legado` (túnel SSH ativo, somente `SELECT`, §4). Pontos em aberto ou em que o enquadramento **diverge da
> medição** estão no §13 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD `ef8841b`, PR #36 — **Fatia 1 mesclada**; a Fatia 2A ramifica daqui). Suíte baseline:
> **915 testes** (medido com `artisan test --list-tests`; a `MEMORY.md` diz 879 — desatualizado, era pós-Fatia 0).
> Fundação: o **modelo de capacidades** (VISIBILIDADE × CAPACIDADE) + a **Camada 1** (`RegimeAcesso::DoTipo`) +
> a **Fatia 1** (o satélite `AutorEspiritual`, do qual a Mensagem depende).

---

## 0. Recorte: por que esta é a fatia "2A" (e o que fica na 2B)

O kickoff recomenda partir a Fatia 2 (grande) em duas:

- **2A (ESTE spec):** `Mensagem` como **entidade + dados** — model, enum, migrations, ligação com a Camada 1,
  a cadeia de importação das 179 e o **CRUD no `/admin`**. Ao fim da 2A, as mensagens **existem, estão migradas
  e são editáveis no painel**. Sem superfície de site.
- **2B (spec seguinte):** o **front público** das 4 páginas (listagem/detalhe de mensagens + listagem/perfil de
  autores) recriando os `design_handoff_*` na stack real — **só a camada Públicas** (filtro fixo), sem níveis
  ricos nem engajamento.

O GATE (fatias 3/4/5 dependem desta) é a **2A**: é ela que cria a tabela, o modelo e os dados. A 2B é
apresentação.

---

## 1. Contexto e objetivo

A Camada 4 é o módulo **Mensagens Mediúnicas**: o corpo psicografado/psicofônico, atribuído a um **autor
espiritual** (a entidade da Fatia 1). Esta **Fatia 2A** entrega a **entidade `Mensagem` e a migração** das 179
mensagens do CPT `mensagem-mediunicas` do WordPress legado.

**Objetivo:** criar `App\Models\Mensagem` (família `HasMedia` + `TemDepartamento`, regime **DoTipo** da Camada 1,
Policy de capacidade **inerte**), com **CRUD no `/admin`**, o enum `FormatoMensagem`, o pivô N:N com
`AutorEspiritual` (casado **por slug**), o pivô **auto-referente simétrico** `mensagem_relacionada` (nasce vazio),
a **coleção de mídia multi-arquivo** `pictografia`, e a **cadeia de importação idempotente** (`cema:importar-mensagens`)
que traz as 179 (132 `publish` → `publicado`, 47 `pending` → `pendente`, excluindo 1 `auto-draft`).

**A Mensagem clona dois moldes já testados**, sem inventar arquitetura:
- **estrutura editorial** (model enxuto, Resource, Policy DoTipo, Camada 1): o **Autor Espiritual** (Fatia 1) e o
  **Post**;
- **cadeia de importação** (Leitor+Importador+Command+bind, idempotência, mídia, jet_rel, taxonomia, unix→data,
  Drive): os importadores de **Autores/Eventos/Palestras**, reusando `TransformadorLegado` e `LinkDrive`.

**Nível BRUTO agora; visibilidade rica na Fatia 3.** A 2A importa o `nivel` como **string crua** (o slug da
taxonomia `nivel-de-acesso`, ex.: `publico`). A listagem/detalhe pública (2B) filtra **só as Públicas** por um
**filtro fixo** (`status='publicado' AND nivel='publico'`), **sem** resolvedor de visibilidade. Os 6 níveis, o
resolvedor por papel, `noindex`/login e a "Direcionada" são **Fatia 3** (DATA-MODEL.md#L387-388). Ligar
visibilidade por nível agora, sem o cuidado da F3, **vazaria mensagem restrita** — por isso o filtro é fixo e
hard-coded, nunca um scope de visibilidade.

**A Policy nasce INERTE — e é o esperado.** Regime **DoTipo** + `/admin` admin-only (`Gate::before`) + o público
é **só-Públicas sem edição** ⇒ a Policy existe mas **não morde** ninguém hoje. O **eixo de autoria do médium**
(`mensagens.publicar` via setor "Médium"; `mensagens.definir-nivel` só diretor — DATA-MODEL.md#L365-366) é **outro
eixo, da Fatia 4** — **não** é a capacidade editorial da Camada 1 e **não** entra aqui.

---

## 2. Decisões travadas (não reabrir)

1. **Importar as 179**: `publish` (132) → `status='publicado'`; `pending` (47) → `status='pendente'`; **excluir**
   o 1 `auto-draft`.
2. **Podar** `origem_da_mensagem` e `grupo_mediunico`. **Manter** `casa` (default `'CEMA'`), `formato`,
   `data_recebimento`, `corpo`, autor (N:N), download (`link_arquivo` + `liberar_download`), `nivel`.
3. **Autor N:N** (pivô `mensagem_autor_espiritual`), casado **por slug** (o `AutorEspiritual` não tem `wp_id`).
   N:N é **decisão de flexibilidade** — o legado é 1:1 hoje (§4.5). O card exibe 1+ autores, ou "sem assinatura"
   quando 0.
4. **Mensagens relacionadas — COMPLETO**: pivô **auto-referente N:N** + Select no `/admin` (2A) + exibição no
   detalhe (2B). **SIMÉTRICA** (A↔B). **Nasce vazia** (o legado não tem; curadoria manual daqui pra frente).
5. **Nível BRUTO** na 2A (string da taxonomia). Visibilidade rica (6 níveis, resolvedor, "Direcionada") = **Fatia 3**.
6. **Engajamento** (curtidas/favoritos/vistas) = **Fatia 5** — nada agora.
7. **Camada 1**: `mensagem` ao **final** de `RECURSOS`; semente `DoTipo` + siglas **`['DEPAE']`** (eixo mediúnico;
   reconfigurável na tela). Policy padrão `ver/criar/editar/excluir` — **inerte**.
8. **Regime DoTipo** — o departamento vem do **tipo** (config da tela), **não** do formulário. Sem Select de
   "Departamentos" no form (paridade com Autor/Palestrante).
9. **Importação idempotente por `wp_id`** (`updateOrCreate(['wp_id'=>…])`). Rodar 2x não duplica registro nem mídia.
10. **Legado READ-ONLY** — só `SELECT` (§4 foi medição, nunca escrita).
11. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev (152 usuários + 123
    palestras/agenda + 44 posts + 19 autores + mídia). Só `migrate` incremental ([[nunca-migrate-fresh-no-dev]]).

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-18 (base `ef8841b`) por 7 leitores paralelos. **Docblock não é evidência** — o
que segue foi lido no fonte.

### 3.1 Os models-molde (`AutorEspiritual`, `Post`, `Evento`)

- [AutorEspiritual](../../../app/Models/AutorEspiritual.php): `$table='autores_espirituais'`
  ([:23](../../../app/Models/AutorEspiritual.php#L23)) — **precedente direto** do porquê `Mensagem` precisa de
  `$table='mensagens'` (o pluralizador inglês geraria `mensagems`). `implements HasMedia, TemDepartamento` +
  `use HasFactory, InteractsWithMedia, RegistraImagensPadrao` ([:18-20](../../../app/Models/AutorEspiritual.php#L18-L20));
  mutator `bio` com `clean($value,'conteudo')` ([:68-73](../../../app/Models/AutorEspiritual.php#L68-L73));
  `departamentos()` com pivô nomeado explícito ([:41-44](../../../app/Models/AutorEspiritual.php#L41-L44)).
- [Post](../../../app/Models/Post.php): constantes de status `STATUS_*` ([:27-31](../../../app/Models/Post.php#L27-L31));
  `casts()` com `datetime`/`boolean`/`integer` ([:71-80](../../../app/Models/Post.php#L71-L80)); **coleção
  multi-arquivo** `COLECAO_GALERIA` sem `singleFile()` ([:108-119](../../../app/Models/Post.php#L108-L119));
  `wp_id` no `$fillable` ([:63](../../../app/Models/Post.php#L63)).
- [Evento](../../../app/Models/Evento.php): **cast para enum** `'visibilidade' => VisibilidadeEvento::class`
  ([:40-45](../../../app/Models/Evento.php#L40-L45)) + `use App\Enums\VisibilidadeEvento`
  ([:7](../../../app/Models/Evento.php#L7)); galeria multi via trait `registrarColecaoImagem(self::COLECAO_GALERIA, unica: false, larguraWeb: 1920)`
  ([:51](../../../app/Models/Evento.php#L51)); mutator `conteudo` com `clean($v,'conteudo')`
  ([:128-133](../../../app/Models/Evento.php#L128-L133)).
- [RegistraImagensPadrao::registrarColecaoImagem($colecao, bool $unica=true, int $larguraWeb=1600, int $ladoThumb=400)](../../../app/Models/Concerns/RegistraImagensPadrao.php#L32-L56):
  `unica:true → singleFile()`; `unica:false → multi`; sempre `useDisk('public')`; conversões `web` (WebP q82) e
  `thumb` (WebP crop). **`pictografia` usa `unica:false`** (o legado tem mensagem com 2 imagens — §4.6).
- [TemDepartamento](../../../app/Models/Contracts/TemDepartamento.php#L10-L13): exige **1 método**
  `departamentos(): BelongsToMany`.
- **Padrão portável de coluna de data** (relevante p/ `data_recebimento`):
  [AgendaDia::data()](../../../app/Models/AgendaDia.php#L95-L101) usa `Attribute::make(get: Carbon::parse, set:
  ->format('Y-m-d'))` numa coluna `date` — o docblock ([:89-94](../../../app/Models/AgendaDia.php#L89-L94))
  explica que o cast nativo `date` **diverge SQLite×MySQL**. Ver decisão §6.4 e [[padrao-data-mutator-portavel]].

### 3.2 A Camada 1 é data-driven de `GlossarioCapacidades` (mexer em 1 lugar propaga)

[GlossarioCapacidades](../../../app/Support/Autorizacao/GlossarioCapacidades.php): `RECURSOS` tem **6**
(`…,'autor_espiritual'` — [:20](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L20)); `permissions()`
gera **24** nomes (confirmado por `tinker`: `count()===24`). Quem consome `RECURSOS` e propaga sozinho ao somar
`'mensagem'`:
- [CapacidadesSeeder](../../../database/seeders/CapacidadesSeeder.php#L19-L21): `updateOrCreate` por nome ⇒ **4
  novas** (24→28), idempotente.
- [TiposConteudoSeeder](../../../database/seeders/TiposConteudoSeeder.php): itera `RECURSOS` ([:55](../../../database/seeders/TiposConteudoSeeder.php#L55))
  e **explode `RuntimeException`** se faltar a semente ([:36-38](../../../database/seeders/TiposConteudoSeeder.php#L36-L38))
  **ou** se uma sigla não existir em `departamentos` ([:68-87](../../../database/seeders/TiposConteudoSeeder.php#L68-L87));
  `firstOrCreate` + `sync` só no `wasRecentlyCreated` (insert-only — [:40-48](../../../database/seeders/TiposConteudoSeeder.php#L40-L48)).
- [MatrizCapacidades](../../../app/Filament/Pages/MatrizCapacidades.php): 100% data-driven sobre `RECURSOS`
  ([:58](../../../app/Filament/Pages/MatrizCapacidades.php#L58), [:93](../../../app/Filament/Pages/MatrizCapacidades.php#L93))
  ⇒ 7ª Section "Mensagem" automática.
- [DepartamentoResource](../../../app/Filament/Resources/Departamentos/DepartamentoResource.php#L177-L183):
  data-driven do banco — **nenhuma edição**.
- [AcessoPorTipo::usuarioHabilitadoNoTipo](../../../app/Support/Autorizacao/AcessoPorTipo.php#L40-L61): `match` por
  `regime()`; `DoTipo` ⇒ usuário em depto **responsável pelo tipo**; `null`/sem responsável/sem depto ⇒ `false`
  (fail-closed). **Genérico por `$recurso` — nenhuma edição.**

⇒ **A única mudança de fundação é adicionar `'mensagem'` nos 3 mapas do glossário + 1 linha na SEMENTE.** Seeders
e tela propagam.

### 3.3 A Policy-molde e o trait

[AutorEspiritualPolicy](../../../app/Policies/AutorEspiritualPolicy.php)/[PostPolicy](../../../app/Policies/PostPolicy.php):
`use AutorizaPorDepartamento`, `recurso(): string`, 4 abilities pt-BR = `hasPermissionTo("<recurso>.<acao>") &&`
escopo do trait. [AutorizaPorDepartamento::noEscopo](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L27-L36)
**bifurca por regime**; `podeCriarNoEscopo` idem ([:44-47](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L44-L47)).
⇒ **trocar só `recurso()` p/ `'mensagem'` basta.** Policies são **auto-descobertas** por convenção
`Models\X → Policies\XPolicy` (grep por `Gate::policy`/`$policies` = zero); o admin passa antes no **único**
`Gate::before` ([AppServiceProvider.php:68](../../../app/Providers/AppServiceProvider.php#L68)).

### 3.4 A cadeia de importação-molde (Autores/Eventos/Palestras) + helpers prontos

- **Interface + Mysql + fake**: [LeitorAutoresEspirituais](../../../app/Importacao/LeitorAutoresEspirituais.php)
  (1 método) / [LeitorAutoresEspirituaisMysql](../../../app/Importacao/LeitorAutoresEspirituaisMysql.php)
  (`DB::connection('legado')`, SQL cru com prefixo literal `wp_`, `metasDe()` 1-valor-por-chave
  [:47-58](../../../app/Importacao/LeitorAutoresEspirituaisMysql.php#L47-L58)).
- **jet_rel** (o único leitor que lê `wp_jet_rel_*`): [LeitorLegadoMysql::slugsRelacionados](../../../app/Importacao/LeitorLegadoMysql.php#L132-L147)
  — ⚠️ é para as **tabelas dedicadas** `wp_jet_rel_107/108`; a Mensagem usa `wp_jet_rel_default` **filtrado por
  `rel_id=37`** (§4.5) — SQL **adaptado**, não cópia literal. Resolução por **slug** (child `post_name`) espelha
  [ImportadorPalestras](../../../app/Importacao/ImportadorPalestras.php#L128-L141) (resolve palestrante por slug).
- **Taxonomia**: [LeitorLegadoMysql::assuntosDaPalestra](../../../app/Importacao/LeitorLegadoMysql.php#L150-L161)
  — `wp_term_relationships`⋈`wp_term_taxonomy`⋈`wp_terms`; trocar a taxonomia p/ `nivel-de-acesso`.
- **Importador** (DI + idempotência + mídia): [ImportadorAutoresEspirituais](../../../app/Importacao/ImportadorAutoresEspirituais.php)
  — DI `Leitor + BaixadorImagem` ([:16-19](../../../app/Importacao/ImportadorAutoresEspirituais.php#L16-L19)); cada
  item numa `DB::transaction` ([:30](../../../app/Importacao/ImportadorAutoresEspirituais.php#L30)); `updateOrCreate`
  ([:33-36](../../../app/Importacao/ImportadorAutoresEspirituais.php#L33-L36)); **mídia O1** — `clearMediaCollection`
  **só após download OK, dentro do `if foto`** ([:41-56](../../../app/Importacao/ImportadorAutoresEspirituais.php#L41-L56));
  contadores/avisos. [ImportadorEventos](../../../app/Importacao/ImportadorEventos.php) é o molde do **`wp_id`**
  (`updateOrCreate(['wp_id'=>…])`) e da **galeria multi** ([:83-109](../../../app/Importacao/ImportadorEventos.php#L83-L109)).
- **Command**: [ImportarAutoresEspirituais](../../../app/Console/Commands/ImportarAutoresEspirituais.php) —
  `signature 'cema:importar-…'`; valida `legado->getPdo()` **só com o leitor real** (`instanceof …Mysql`,
  [:22-32](../../../app/Console/Commands/ImportarAutoresEspirituais.php#L22-L32)); resumo com contadores.
- **BIND obrigatório**: [AppServiceProvider::register](../../../app/Providers/AppServiceProvider.php#L38-L43) tem
  **6 binds** de leitor (`Leitor…::class → …Mysql::class`); o command/importador type-hintam a **interface**.
  Sem `bind(LeitorMensagens::class, LeitorMensagensMysql::class)`, `cema:importar-mensagens` quebra **em produção**
  com a **suíte verde** (o teste injeta fake) — **defeito C7** (§13/I16).
- **Helpers prontos (reuso, não reinventar):**
  - [BaixadorImagem::baixarCapado(?string $url, int $teto=2000): ?string](../../../app/Importacao/BaixadorImagem.php#L53)
    — bytes em memória; `null` em URL vazia/falha; blindagem `^https?://` ([:63](../../../app/Importacao/BaixadorImagem.php#L63)).
  - [TransformadorLegado::unixParaData](../../../app/Importacao/TransformadorLegado.php#L18-L40) — unix ts →
    Carbon em São Paulo **preservando o relógio de parede** (p/ `data_recebimento`).
  - [TransformadorLegado::statusParaAtivo](../../../app/Importacao/TransformadorLegado.php#L13-L16) — truthy
    `['true','on','1','sim']` (p/ `liberar_download_mensagem`, cujos valores são `true`/`on`/`false`/`''` — §4.4).
  - [TransformadorLegado::destaquesDoRepeater](../../../app/Importacao/TransformadorLegado.php#L42-L74) — molde de
    `unserialize($s, ['allowed_classes'=>false])` **seguro** com `set_error_handler` (p/ `_fotos_mensagem` — §4.6).
  - [LinkDrive::paraDownload](../../../app/Support/Palestras/LinkDrive.php#L10-L34) — `html_entity_decode` (`&amp;`)
    + Drive → `uc?export=download&id=…` (p/ `link_do_arquivo_mensagem` — §4.4).

### 3.5 O Resource-molde e os componentes

- [AutorEspiritualResource](../../../app/Filament/Resources/AutoresEspirituais/AutorEspiritualResource.php) —
  clone enxuto (form/table/Pages triviais). **Auto-descoberto** por
  [AdminPanelProvider::discoverResources](../../../app/Providers/Filament/AdminPanelProvider.php#L85) — **nenhum
  registro manual**. `$slug` explícito pt-BR (senão o pluralizador inglês erra a URL).
- **Select N:N** (molde de 3 lugares idênticos): `Select::make(<rel>)->relationship(<rel>,<titulo>)->multiple()->preload()->searchable()`
  — [PostResource categorias:199-204](../../../app/Filament/Resources/Posts/PostResource.php#L199-L204),
  [EventoForm departamentos:107-112](../../../app/Filament/Schemas/EventoForm.php#L107-L112),
  [PalestraResource assuntos:152-157](../../../app/Filament/Resources/Palestras/PalestraResource.php#L152-L157).
- **Select auto-referente** com exclusão do próprio registro **nativa**:
  [Select::relationship($name,$titleAttribute,$modifyQueryUsing,bool $ignoreRecord=false)](../../../vendor/filament/forms/src/Components/Select.php#L781)
  — `ignoreRecord:true` aplica `where(id,'!=',record)` na query de opções ([:991-993](../../../vendor/filament/forms/src/Components/Select.php#L991-L993)).
- **Upload múltiplo** (pictografia): [ComponentesImagem::upload($nome,$colecao, bool $multiplas=true)](../../../app/Filament/Support/ComponentesImagem.php#L22)
  — `multiplas:true` aplica `->multiple()->reorderable()->appendFiles()->panelLayout('grid')` ([:34-39](../../../app/Filament/Support/ComponentesImagem.php#L34-L39));
  uso real em [EventoForm galeria:68](../../../app/Filament/Schemas/EventoForm.php#L68).
- **DatePicker** (coluna `date`): `DatePicker::make(...)->native(false)->displayFormat('d/m/Y')` —
  [AgendaDiaForm:31-35](../../../app/Filament/Schemas/AgendaDiaForm.php#L31-L35),
  [EventoForm:73-76](../../../app/Filament/Schemas/EventoForm.php#L73-L76). (`DateTimePicker` só onde há hora —
  `data_publicacao`/`data_da_palestra`; `data_recebimento` é dia-granular — §6.4.)

### 3.6 Os testes-molde (o que muda de cor — e o que NÃO muda)

| Teste | Muda? | O que acontece ao somar `mensagem` |
|---|---|---|
| [CapacidadesSeederTest](../../../tests/Feature/Autorizacao/CapacidadesSeederTest.php#L30) | **SIM** | `assertSame(24,…)` → **28** + acrescentar os 4 `mensagem.*` (§9.2) |
| [TiposConteudoSeederTest](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php) | **Aditivo** | usa `count(RECURSOS)` (vira 7 sozinho); acrescentar assert de siglas `['DEPAE']`/regime DoTipo; a ordem **final** preserva o `expectExceptionMessage('DED')` de [:117](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L117) |
| [GlossarioCapacidadesMapaTest](../../../tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php#L35-L40) | **Verde só se o model existir** | `test_todo_model_do_mapa_existe` faz `class_exists` ⇒ **o model `Mensagem` tem de nascer ANTES** do edit `'mensagem'=>Mensagem::class` (ordenação TDD — §9.0) |
| [MatrizCapacidadesTest](../../../tests/Feature/Filament/MatrizCapacidadesTest.php) | **Não** | render/acesso; `setUp` semeia os seeders (que passam a exigir a semente `mensagem` + DEPAE — existe) |

Moldes de teste a clonar: [AutorEspiritualPolicyCapacidadeTest](../../../tests/Feature/Autorizacao/AutorEspiritualPolicyCapacidadeTest.php)
(DoTipo), [AutorEspiritualTest](../../../tests/Feature/Models/AutorEspiritualTest.php) (model),
[AutorEspiritualResourceTest](../../../tests/Feature/Filament/AutorEspiritualResourceTest.php) (Resource),
[ImportadorAutoresEspirituaisTest](../../../tests/Feature/Importacao/ImportadorAutoresEspirituaisTest.php)
(fake por classe anônima, `BaixadorImagem` sobrescrito PNG 1×1, `Storage::fake('public')`, idempotência, O1),
[ImportarAutoresEspirituaisCommandTest](../../../tests/Feature/Importacao/ImportarAutoresEspirituaisCommandTest.php)
(bind do fake + **guarda do binding** que resolve sem fake — I16).
[AcessoPorTipoTest](../../../tests/Feature/Autorizacao/AcessoPorTipoTest.php) já é **recurso-agnóstico** —
**NÃO tocar** (cobre a semântica DoTipo que a Policy herda pelo trait).

### 3.7 As rotas dos singles (para a 2B — convenção confirmada)

Em [routes/web.php](../../../routes/web.php): **nenhum** single usa route-model binding; todos usam `{slug}` cru +
`->where('slug','[a-z0-9-]+')`, resolvido no Controller; **list e single compartilham o mesmo segmento**
(`/eventos` + `/eventos/{slug}`); nomes em dot-notation com recurso no **plural** (`eventos.`, `blog.`). ⇒ a rota
sugerida no kickoff (`/mensagem-mediunica/{mensagem:slug}`, singular + model-binding) **rompe** a convenção — §13/O5.

---

## 4. Medições no legado (banco vivo, somente `SELECT`, 2026-07-18)

Consultas na conexão `legado` (túnel SSH ativo, container `cema-app`, prefixo `wp_`). **Nenhuma escrita.** Notas
completas em `scratchpad/legado-mensagens-medicao.md`.

### 4.1 População e corpo

| Medida | Valor |
|---|---|
| `post_type='mensagem-mediunicas'` por status | **publish 132 · pending 47 · auto-draft 1** |
| Alvo do import (publish+pending) | **179** (o auto-draft é excluído) |
| `post_title` vazio no alvo | **0** (título sempre presente ⇒ base segura p/ gerar slug) |
| `post_content` (corpo) | **179/179** têm (publish **e** pending); `MAX(len)`≈**8703**, `AVG`≈2015 |

⚠️ **Diverge do kickoff** ("132/132 publish têm corpo; máx 6,7KB"): o corpo está em **todos os 179** (pending
inclusive) e o máximo real é **~8,7KB**.

### 4.2 Slug (`post_name`) — 39 pending sem slug

| Medida | Valor |
|---|---|
| Pending **sem** `post_name` | **39** (todos os vazios são pending; publish todos têm) |
| Slugs duplicados **entre os não-vazios** | **0** |

⚠️ **Achado novo (não no kickoff).** A coluna `slug` no site novo é `unique NOT NULL`; 39 pending vêm sem
`post_name` ⇒ o import **gera slug único** (§6.6). O `AutorEspiritual` **não** tinha esse problema (usava
`post_name` cru) — é um padrão **novo** da Mensagem.

### 4.3 Metas (cobertura sobre os 179, non-empty) e formato

| Meta | Cobertura | Destino |
|---|--:|---|
| `_formato` | 179 | `formato` (enum) — **psicografia 137 · psicofonia 40 · pictografia 2** |
| `data_recebimento` | 179 | `data_recebimento` — **unix ts**, dia-granular (ex.: `1722902400`=2024-08-05); **0** zero/vazio |
| `casa_espirita` | 179 | — **constante `"cema"`** ⇒ não migrar; `casa` fica `default 'CEMA'` |
| `origem_da_mensagem` | 177 | **PODAR** |
| `grupo_mediunico` | 46 | **PODAR** |
| `_thumbnail_id` | **0** | — **não há imagem destacada** (só pictografia via `_fotos_mensagem`) |

⚠️ `_thumbnail_id=0`: ao contrário do Autor (que baixava a foto via `_thumbnail_id→guid`), a Mensagem **não tem
capa** — a única imagem é a **pictografia** (§4.6).

### 4.4 Download — real ≈ 8 (não 69)

| Meta | Valor |
|---|---|
| `link_do_arquivo_mensagem` (non-empty) | **8** — todas Drive `uc?export=download&amp;id=…` (LinkDrive trata o `&amp;`) |
| `liberar_download_mensagem` | `false`=39 · `true`=8 · `''`=21 · `on`=1 (**truthy=9**) |
| `liberar` truthy **E** link non-empty | **8** |
| link presente **mas** `liberar` falsy | **0** |

⚠️ **Diverge do kickoff** ("69"). O download efetivo é **8** — todo link tem `liberar` truthy. Modelo: guardar
`link_arquivo` (via `LinkDrive::paraDownload`) + `liberar_download` (bool via `statusParaAtivo`).

### 4.5 Autor N:N — `wp_jet_rel_default`, `rel_id='37'`

| Medida | Valor |
|---|---|
| Tabela | `wp_jet_rel_default` (colunas `parent_object_id`/`child_object_id`, filtro `rel_id`); `rel_id` presentes: 37/38/200 |
| **rel 37** | `parent`=`mensagem-mediunicas` (96) · `child`=`autores-espirituais` (96) |
| Mensagens (alvo) com autor | **96** (publish 81 + pending 15) |
| Mensagens com **>1 autor** | **0** |
| Autores distintos referenciados | **17**, `child_sem_slug=0`, `child` não-autor=0, **17 ⊆ 19 publish** (`fora=0`) |
| `rel_id=38` | `elementor_library`/`page`/`jet-theme-core` → mensagem = **ruído de template, IGNORAR** |

⚠️ **Diverge do kickoff** ("96 vínculos p/ 81 msgs → há msgs com >1 autor"): os 96 vínculos são **96 mensagens
distintas**, cada uma com **exatamente 1 autor** (os outros 15 são pending). **Nenhuma mensagem tem >1 autor
hoje** — N:N segue como **decisão** (flexibilidade), não como dado. **51 publish têm 0 autor** (⇒ "sem
assinatura" é comum). O casamento **por slug** é 100% resolúvel (17 ⊆ 19).

**SQL a espelhar** (adaptado de `slugsRelacionados`):
```sql
SELECT autor.post_name AS slug
FROM wp_jet_rel_default r
JOIN wp_posts autor ON autor.ID = r.child_object_id
WHERE r.rel_id = 37 AND r.parent_object_id = ? AND autor.post_type = 'autores-espirituais';
```

### 4.6 Pictografia — `_fotos_mensagem` (PHP serializado, MULTI)

| Medida | Valor |
|---|---|
| `_fotos_mensagem` (non-empty) | **2**, ambos **publish** (21744 "Instruções para o atendimento" = **2 imgs**; 21810 "Receita para todos" = 1 img) |
| Formato | `a:N:{i:0;a:2:{s:2:"id";i:22393;s:3:"url";s:83:"https://…/img.jpg";}…}` |

⚠️ Pode ter **múltiplas** imagens ⇒ coleção `pictografia` é **multi-arquivo** (`unica:false`); parse por
`unserialize` seguro (molde `destaquesDoRepeater`); baixar por **URL** (como o Autor), não por `_thumbnail_id`.

### 4.7 Nível de acesso — taxonomia `nivel-de-acesso`

| slug | name | alvo | publish |
|---|---|--:|--:|
| `publico` | Público | 29 | 29 |
| `trabalhadores` | Trabalhadores | 44 | 44 |
| `mediuns-trabalhadores` | Médiuns | 33 | 33 |
| `direcionada` | Direcionada | 15 | 15 |
| `diretores` | Diretores | 9 | 9 |
| `diretor-depae` | Diretor-DEPAE | 0 | 0 (termo ocioso) |

- **Com termo = 130** (todos publish); **sem termo = 49** (47 pending + 2 publish: posts 26021, 26818).
- **0 mensagens com >1 termo** (nível é escalar).

⚠️ **Diverge do kickoff** ("publica"): o slug real do público é **`publico`**. O filtro fixo da 2B é
`nivel='publico' AND status='publicado'`. Sem termo → `nivel = null` (não aparece; fail-closed). São **5 níveis
reais** (o `diretor-depae` é ocioso).

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Model:** `Mensagem` tem `$fillable=[titulo,slug,corpo,contexto,formato,data_recebimento,casa,link_arquivo,liberar_download,nivel,status,wp_id]`; casts `formato=>FormatoMensagem`, `liberar_download=>boolean`; `corpo` sanitizado (`clean(...,'conteudo')`, `<script>` some); `implements HasMedia, TemDepartamento`; `scopePublica` = `status='publicado' AND nivel='publico'`. | §9.1 |
| **I2** | **Pluralização/pivôs:** grava em **`mensagens`** (`$table` explícito; o inflector geraria `mensagems`); pivôs referenciam `mensagens`/`autores_espirituais`/`departamentos` **explicitamente**. | §9.1 |
| **I3** | **Enum:** `App\Enums\FormatoMensagem: string` com 3 casos (`psicografia`/`psicofonia`/`pictografia`) + `rotulo()` + `opcoes()`; o cast do model reidrata o enum. | §9.1 |
| **I4** | **Glossário propaga:** somar `'mensagem'` aos 3 mapas ⇒ `permissions()` tem **28** (os 4 `mensagem.*`), `RECURSOS_MODELS['mensagem']===Mensagem::class`, `RECURSOS` tem **7**. | §9.2 |
| **I5** | **Seeders idempotentes:** `CapacidadesSeeder` cria as 28 (2ª vez não duplica); `TiposConteudoSeeder` cria o tipo `mensagem` **DoTipo** responsáveis **`['DEPAE']`**, insert-only. | §9.2 |
| **I6** | **Guarda do seeder + ordem:** explode (`RuntimeException`) se faltar a semente `mensagem` **ou** se `DEPAE` não existir; `'mensagem'` no **final** de `RECURSOS` preserva `expectExceptionMessage('DED')`. | §9.2 |
| **I7** | **Policy DoTipo inerte:** responsável (DEPAE) **com** `mensagem.editar` ⇒ `editar` **mesmo a mensagem sem departamento**; depto disjunto nega; sem permissão nega; sem linha em `tipos_conteudo` nega; **admin** passa; `criar` invocado com a **classe**. **`mensagem.publicar`/`definir-nivel` NÃO existem** (Fatia 4). | §9.3 |
| **I8** | **Resource `/admin`:** cria/edita; **`origem_da_mensagem`/`grupo_mediunico`/`casa_espirita` NÃO existem** no form; `pictografia` = upload **múltiplo** ML; `autores`/`relacionadas` Selects múltiplos (relacionadas exclui o próprio registro); `formato`/`status` Selects; `nivel` Select simples (opções incl. `publico`, aceita null); slug auto no create; corpo sanitizado. | §9.4 |
| **I9** | **Import — mapeamento/status:** os 179 viram `Mensagem` (publish→`publicado`, pending→`pendente`, auto-draft **excluído**); `titulo←post_title`, `corpo←post_content`, `formato←_formato`, `data_recebimento←unixParaData(data_recebimento)`, `nivel←termo` (`null` se ausente); `casa='CEMA'`; **poda** origem/grupo. | §9.5 |
| **I10** | **Import — slug determinístico:** `post_name` não-vazio → `slug=post_name`; vazio (39 pending) → `slug=Str::slug(titulo).'-'.wp_id` (único, idempotente); nunca `null`/colisão. | §9.5 |
| **I11** | **Import — autor por SLUG:** rel 37 (parent=mensagem) → child `post_name` → `AutorEspiritual::where('slug')`; `sync` N:N; slug inexistente vira **aviso** (não quebra o import). | §9.5 |
| **I12** | **Import — pictografia multi + O1:** `_fotos_mensagem` (unserialize seguro) → `baixarCapado` por URL → coleção multi; `clearMediaCollection` **só se houve ao menos 1 download OK** (mensagem sem foto no legado **preserva** upload do `/admin`). | §9.5 |
| **I13** | **Import — idempotência por `wp_id`:** rodar 2x ⇒ **179** registros; mídia **não** duplica; **não** popula `relacionadas`; **não** sincroniza `departamentos` (DoTipo); re-import **preserva** a curadoria do admin — `slug`/`status`/`nivel` são **create-only** (fonte inicial = legado; depois, do admin), `contexto`/`casa`/`relacionadas` **nunca** vêm do import. | §9.5 |
| **I14** | **Import — download:** `link_arquivo←LinkDrive::paraDownload(link_do_arquivo_mensagem)` (`&amp;`→`&`, `uc?export=download`); `liberar_download←statusParaAtivo(liberar_download_mensagem)`; link presente mas `liberar` falsy ⇒ não expõe (bool `false`). | §9.5 |
| **I15** | **Relacionadas simétrica:** anexar A→B ⇒ B lista A; **nasce vazia** (import não popula); **sem auto-relação** (self excluído no Select e no `sincronizar`). | §9.1/§9.4 |
| **I16** | **Command + bind:** `cema:importar-mensagens` valida `legado` só com leitor real; com fake injetado dá `assertSuccessful`; resolver `LeitorMensagens` pelo container devolve `LeitorMensagensMysql` (guarda C7). | §9.6 |
| **I17** | **Índice ≤64:** os pivôs `mensagem_autor_espiritual`/`mensagem_relacionada`/`departamento_mensagem` têm **nome de unique explícito** (o auto de `mensagem_autor_espiritual` dá **exatos 64** — margem zero). | §9.1 |
| **I-neutro** | Nenhuma asserção existente muda de cor **exceto** a edição deliberada do `CapacidadesSeederTest` (24→28). Suíte **915** + novos, verde. | §9.7 |

---

## 6. Decisões de desenho

### 6.1 O model `App\Models\Mensagem`

- `protected $table = 'mensagens';` — **obrigatório** (I2; o pluralizador geraria `mensagems`).
- `implements HasMedia, TemDepartamento`; `use HasFactory, InteractsWithMedia, RegistraImagensPadrao`.
- Constantes: `STATUS_PUBLICADO='publicado'`, `STATUS_PENDENTE='pendente'`, `STATUS_DESPUBLICADA='despublicada'`
  (molde `Post`); `COLECAO_PICTOGRAFIA='pictografia'`.
- `$fillable = ['titulo','slug','corpo','contexto','formato','data_recebimento','casa','link_arquivo','liberar_download','nivel','status','wp_id']`.
- `casts()`: `['formato'=>FormatoMensagem::class, 'liberar_download'=>'boolean']` (+ `data_recebimento` — ver §6.4).
- Mutator `corpo`: `Attribute::make(set: fn(?string $v) => $v!==null ? clean($v,'conteudo') : null)`.
- Mutator `link_arquivo` (**R-A**): `Attribute::make(set: fn(?string $v) => LinkDrive::paraDownload($v))` — normaliza
  o Drive para download direto no set, valendo p/ o import **e** um link colado no `/admin`. Com o mutator, o
  importador passa o link **cru** (§6.6).
- **`contexto` (OA — decisão travada do dono, "manual, não IA"):** faixa editorial curta exibida na 2B como
  "**Contexto** — {texto}" (handoff single §4.3). É **texto puro** (Textarea no `/admin`, exibido escapado via
  Blade `{{ }}`) — **sem** `clean()`/HTML e **sem** mutator; **não** existe no legado ⇒ nasce `null` (o import não
  popula). Nasce **agora** (barato) para não virar migration corretiva depois — como foi o `chamada` da Fatia 1.
- `scopePublica(Builder $q)`: `->where('status', self::STATUS_PUBLICADO)->where('nivel','publico')`. (Model-level; a
  2B consome. Encapsula o **filtro fixo** do §1 — nunca um scope de visibilidade por papel.)
- Relações:
  - `departamentos()` → `belongsToMany(Departamento::class, 'departamento_mensagem', 'mensagem_id', 'departamento_id')`
    (contrato `TemDepartamento`; **inerte** sob DoTipo, por paridade — §12).
  - `autores()` → `belongsToMany(AutorEspiritual::class, 'mensagem_autor_espiritual', 'mensagem_id', 'autor_espiritual_id')`.
  - `relacionadas()` → `belongsToMany(self::class, 'mensagem_relacionada', 'mensagem_id', 'relacionada_id')`.
- `registerMediaCollections()` → `registrarColecaoImagem(self::COLECAO_PICTOGRAFIA, unica: false)` (multi — §4.6).
- **Sem** `getUrlPublicaAttribute` nesta fatia (a rota não existe até a 2B).

### 6.2 O enum `App\Enums\FormatoMensagem` (novo — não existe)

Molde [RegimeAcesso](../../../app/Enums/RegimeAcesso.php): `enum FormatoMensagem: string` com
`case Psicografia='psicografia'; case Psicofonia='psicofonia'; case Pictografia='pictografia';` + `rotulo(): string`
(match) + `static opcoes(): array` (para o Select). O `formato` grava o **value** (o mesmo string do legado).

### 6.3 As migrations (4 novas, incrementais, nada destrutivo)

Timestamps `2026_07_18_000001..000004` (base primeiro; `autores_espirituais`/`departamentos` já existem):

1. `create_mensagens_table`:
   ```php
   $table->id();
   $table->unsignedBigInteger('wp_id')->nullable()->unique();     // idempotência do legado
   $table->string('titulo');
   $table->string('slug')->unique();                              // 39 pending → gerar único (§6.6)
   $table->longText('corpo')->nullable();
   $table->text('contexto')->nullable();                          // OA: faixa editorial manual (não IA); nasce null
   $table->string('formato')->nullable();                         // enum FormatoMensagem
   $table->date('data_recebimento')->nullable();                  // dia-granular (§6.4) — nullable de origem
   $table->string('casa')->default('CEMA');
   $table->string('link_arquivo', 500)->nullable();               // M-A: alinha com o maxLength(500) do form
   $table->boolean('liberar_download')->default(false);
   $table->string('nivel')->nullable();                           // BRUTO; 49/179 null
   $table->string('status')->default('publicado');
   $table->timestamps();
   $table->index('status');
   $table->index('data_recebimento');
   ```
   ⚠️ **`data_recebimento` já nasce `nullable`** (lição do `posts`, cuja `data_publicacao` nasceu NOT NULL e
   precisou de migration corretiva).
2. `create_departamento_mensagem_table`: 2 FKs `constrained('mensagens')`/`constrained('departamentos')` cascade +
   `unique(['mensagem_id','departamento_id'], 'departamento_mensagem_unique')`.
3. `create_mensagem_autor_espiritual_table`: 2 FKs `constrained('mensagens')`/`constrained('autores_espirituais')`
   cascade + `unique(['mensagem_id','autor_espiritual_id'], 'mensagem_autor_espiritual_unique')`. ⚠️ **O nome auto
   do unique dá EXATOS 64 chars** (margem zero p/ o limite do MySQL) — nome explícito por convenção/segurança (I17).
4. `create_mensagem_relacionada_table` (**1º pivô auto-referente do projeto**): `mensagem_id` **e** `relacionada_id`
   **ambas** `constrained('mensagens')` (nome de tabela **obrigatório** — o Laravel inferiria `relacionadas` a partir
   da coluna) cascade + `unique(['mensagem_id','relacionada_id'], 'mensagem_relacionada_unique')`. Sem auto-relação:
   **guarda na aplicação** (padrão do projeto — cardinalidade se valida na app, cf. `CardinalidadePalestra`).

### 6.4 `data_recebimento` — coluna `date` + mutator portável (diverge do kickoff)

O kickoff descreve `data_recebimento` como **datetime**. **Recomendo `date`** porque: (a) o legado é
**dia-granular** (unix ts à meia-noite — §4.3); (b) a seção "Do mesmo dia" (2B) agrupa **por data**; (c) o projeto
tem convenção/memória para **portabilidade SQLite×MySQL** de colunas `date` ([[padrao-data-mutator-portavel]],
[AgendaDia::data():89-101](../../../app/Models/AgendaDia.php#L89-L101)). Desenho:
- Coluna `date` (§6.3); no import, `TransformadorLegado::unixParaData($ts)` (Carbon SP, relógio de parede
  preservado) e gravar via o **mutator portável**:
  ```php
  protected function dataRecebimento(): Attribute {
      return Attribute::make(
          get: fn (?string $v) => $v !== null ? \Illuminate\Support\Carbon::parse($v) : null,
          set: fn ($v) => $v !== null ? \Illuminate\Support\Carbon::parse($v)->format('Y-m-d') : null,
      );
  }
  ```
  (com o mutator, **não** listar `data_recebimento` em `casts()`). No `/admin`,
  `DatePicker->native(false)->displayFormat('d/m/Y')` (§3.5). **Ponto aberto O1 (§13)** — se o dono quiser hora,
  volta a `datetime`+`DateTimePicker`, mas perde-se a portabilidade e a hora é sempre 00:00 no legado.

### 6.5 Camada 1 (o coração data-driven — 3 mapas + 1 semente + 1 número mágico)

- **`GlossarioCapacidades`**: `use App\Models\Mensagem;` (posição alfabética, entre `Evento` e `Palestra`, p/ o
  `ordered_imports` do Pint); `'mensagem'` ao **final** de `RECURSOS` (§13/R1 — antes de `palestra` quebraria o
  teste do `DED`); `'mensagem' => 'Mensagem'` em `RECURSOS_ROTULOS`; `'mensagem' => Mensagem::class` em
  `RECURSOS_MODELS`. (Cosmético: docblock "24 nomes" → "28".)
- **`TiposConteudoSeeder::SEMENTE`**: `'mensagem' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DEPAE']]`.
  **Não vazio** (tipo DoTipo sem responsável grava fail-closed e o insert-only congela o vazio). **DEPAE existe**
  ([GlossarioUsuarios:26](../../../app/Importacao/GlossarioUsuarios.php#L26)) ⇒ a guarda de sigla não explode.
  **Valor inicial**, reconfigurável na tela.
- **`MensagemPolicy`** = clone de `AutorEspiritualPolicy` com `recurso() => 'mensagem'` e tipos `Mensagem`.
  **Auto-descoberta.** **Inerte** (§1/§12). **Não** cria `mensagem.publicar`/`definir-nivel` (Fatia 4).

### 6.6 A cadeia de importação (clone Autor/Eventos, com os desvios do domínio)

- **`App\Importacao\LeitorMensagens`** (interface): `mensagens(): array`.
- **`App\Importacao\LeitorMensagensMysql`**: `SELECT ID, post_title, post_name, post_content, post_status FROM
  wp_posts WHERE post_type='mensagem-mediunicas' AND post_status IN ('publish','pending')`; `metasDe()` (copiar);
  `nivel` via taxonomia `nivel-de-acesso` (molde `assuntosDaPalestra`, 1 termo ou null); `autores_slugs[]` via rel
  37 (§4.5); `fotos_urls[]` via `unserialize` seguro de `_fotos_mensagem`; `link` e `liberar` via `metasDe`.
  Emite por mensagem: `['wp_id','titulo','slug'(=post_name, pode ''),'corpo','formato','data_recebimento'(ts),
  'nivel','autores_slugs','fotos_urls','link_arquivo','liberar_download','status'('publicado'|'pendente')]`.
  **Poda** `origem_da_mensagem`/`grupo_mediunico`/`casa_espirita`.
- **`App\Importacao\ImportadorMensagens`** (DI `LeitorMensagens + BaixadorImagem`): para cada item numa
  `DB::transaction`:
  - **`firstOrNew(['wp_id'=>…])`** (não `updateOrCreate`): distingue **create** de **re-import** para honrar o I13.
    **Conteúdo do legado — sempre atualizado** (`fill`): `titulo`, `corpo`, `formato`,
    `data_recebimento`=`TransformadorLegado::unixParaData(...)`, `link_arquivo` (cru — o mutator do model normaliza, R-A),
    `liberar_download`=`TransformadorLegado::statusParaAtivo(...)`. **Curadoria — só no create** (preservada no
    re-import): `slug`, `status`, `nivel`. **Nunca setados:** `casa` (default `'CEMA'`), `contexto` (manual — OA
    §6.1). **`relacionadas` NÃO** é populado; **`departamentos` NÃO** é sincronizado (DoTipo).
  - **slug** (I10): no create, `post_name` não-vazio → `slug=post_name`; vazio (39 pending) →
    `Str::slug($titulo).'-'.$wp_id` (único, determinístico), com guarda de colisão residual. No re-import o slug é
    **preservado** (o admin pode renomear sem que o import desfaça).
  - **autores** (I11): resolver cada slug por `AutorEspiritual::where('slug',$s)->first()`; `sync` dos ids
    encontrados; slug inexistente → aviso (`autor_slug_inexistente++`).
  - **pictografia** (I12): baixar **todas** as `fotos_urls` (`baixarCapado`); se **≥1** OK →
    `clearMediaCollection(COLECAO_PICTOGRAFIA)` **uma vez** + loop `addMediaFromString(...)->toMediaCollection(...)`;
    se **nenhuma** URL ou todas falharam → **não** limpar (preserva `/admin` — O1). Contadores
    `com_pictografia`/`falha_foto`.
  - contadores/avisos (total, `com_autor`/`sem_autor`, `com_pictografia`, `com_download`, **`publish_sem_nivel`**
    — os 2 publish sem termo, ciência §12 — e avisos).
- **`App\Console\Commands\ImportarMensagens`**: `signature 'cema:importar-mensagens'`; valida `legado->getPdo()`
  **só** com `LeitorMensagensMysql`; resumo. **Sem** fail-fast de catálogo além da conexão (não há pré-requisito
  como o do Evento) — mas os **autores** precisam existir (a Fatia 1 já os importou); slug de autor não-resolvido
  vira aviso, não erro.
- **`AppServiceProvider::register()`**: **bind obrigatório** `bind(LeitorMensagens::class, LeitorMensagensMysql::class)`
  após [:43](../../../app/Providers/AppServiceProvider.php#L43) (**C7** — §13/I16).

### 6.7 O Resource `/admin` (clone enxuto do `AutorEspiritualResource`)

`App\Filament\Resources\Mensagens\MensagemResource` (auto-descoberto), `$slug='mensagens'`,
`$recordTitleAttribute='titulo'`, Pages `{Create,Edit,List}Mensagem` triviais. Form (seções, molde Autor/Post):
- **Conteúdo**: `titulo` (slug auto no create), `slug` (`->unique(ignoreRecord:true)`), `corpo` (RichEditor,
  `columnSpanFull`), **`contexto`** (`Textarea::make('contexto')->rows(3)` — texto puro, opcional — OA §6.1),
  `formato` (`Select->options(FormatoMensagem::opcoes())->required()`), `data_recebimento`
  (`DatePicker->native(false)->displayFormat('d/m/Y')`).
- **Classificação/download**: `nivel` (`Select` simples com as 5 opções do §4.7 — **`publico`,trabalhadores,
  mediuns-trabalhadores,direcionada,diretores** — `nullable`, **não** relação — §13/O2), `status`
  (`Select->options([publicado,pendente,despublicada])->required()`), `liberar_download` (`Toggle`), `link_arquivo`
  (`TextInput->url()->maxLength(500)`). **`casa` NÃO exposta** (constante — default no DB).
- **Relações**: `autores` (`Select->relationship('autores','nome')->multiple()->preload()->searchable()`),
  `relacionadas` (`Select->relationship('relacionadas','titulo', ignoreRecord: true)->multiple()->preload()->searchable()`
  — **ver §6.8 sobre a simetria**).
- **Pictografia**: `ComponentesImagem::upload('pictografia', Mensagem::COLECAO_PICTOGRAFIA, multiplas: true)`.
- Table enxuta: `titulo` (search/limit), `formato` (badge), `data_recebimento` (`date('d/m/Y')`), `status` (badge),
  `liberar_download` (icon), miniatura `pictografia` (opcional); filtros `status`/`formato`/`autores`;
  `defaultSort('data_recebimento','desc')`.

### 6.8 Relacionadas — simetria (padrão novo, sem precedente)

Não há pivô auto-referente no projeto. A relação `mensagem_relacionada` deve ser **simétrica** (A↔B) e a curadoria
é pelo Select do `/admin`. O `Select->relationship('relacionadas', …)` do Filament, ao salvar, faz
`->sync()` **só na direção `mensagem_id=self`** — **não** espelha a volta. Desenho proposto (a decidir no passe —
§13/O3):

- **Armazenamento dual (espelhado):** um método no model `sincronizarRelacionadas(array $ids): void` que, numa
  transação, remove **as duas direções** que envolvem `self` e grava **ambos** os sentidos (`self→id` **e**
  `id→self`) para cada `id` (excluindo o próprio `self`). A leitura `relacionadas()` (belongsToMany por
  `mensagem_id=self`) então enxerga todos os vínculos.
- **No Resource:** o campo `relacionadas` **não** usa o `->relationship()` de auto-sync (que gravaria só uma
  direção); usa `Select` com opções de `Mensagem` (excluindo o próprio via `ignoreRecord`/filtro), carrega o estado
  atual no `mutateFormDataBeforeFill` e grava via `sincronizarRelacionadas` no `afterSave` das Pages Create/Edit.
- **Escrita SEMPRE atômica** (Consultor): `sincronizarRelacionadas` roda numa **transação** gravando os **2
  sentidos** de uma vez (nunca meio-vínculo). As **2 FKs `cascadeOnDelete`** do pivô cobrem o delete de uma
  mensagem (removem os dois lados) ⇒ **sem órfão** — confirmado no §6.3.
- **Testes** (I15): `sincronizarRelacionadas([B])` em A ⇒ `B->relacionadas` contém A; **remover** B de A **reflete
  nos 2 lados** (A perde B **e** B perde A); `self` nunca entra; import **não** popula (nasce vazia).

### 6.9 Factory

`Database\Factories\MensagemFactory`: `titulo`, `slug` único (`Str::slug($titulo).'-'.unique numberBetween`),
`corpo` `<p>…</p>`, `formato` (um dos 3), `data_recebimento` (data), `nivel` (nullable, default null), `status`
(`publicado`), `wp_id` (nullable), `casa` default. Estados `publicada()`/`pendente()`/`publica()` (nivel=`publico`).

---

## 7. As peças (inventário)

**Novos (com cabeçalho de autoria — CLAUDE.md §8):**
`app/Models/Mensagem.php` · `app/Enums/FormatoMensagem.php` ·
`database/migrations/xxxx_create_mensagens_table.php` (+ `_departamento_mensagem_`, `_mensagem_autor_espiritual_`,
`_mensagem_relacionada_`) · `app/Policies/MensagemPolicy.php` ·
`app/Filament/Resources/Mensagens/MensagemResource.php` + `Pages/{Create,Edit,List}Mensagem.php` ·
`app/Importacao/LeitorMensagens.php` + `LeitorMensagensMysql.php` + `ImportadorMensagens.php` ·
`app/Console/Commands/ImportarMensagens.php` · `database/factories/MensagemFactory.php` · testes (§9).

**Editados (mínimo):** `app/Support/Autorizacao/GlossarioCapacidades.php` (3 mapas + 1 `use`) ·
`database/seeders/TiposConteudoSeeder.php` (1 linha em `SEMENTE`) · `app/Providers/AppServiceProvider.php`
(**1 bind de leitor** — C7) · `tests/Feature/Autorizacao/CapacidadesSeederTest.php` (24→28 + 4 nomes) ·
`tests/Feature/Autorizacao/TiposConteudoSeederTest.php` (asserts aditivos do `mensagem`).

**NÃO toca:** `AutorEspiritual`/`Post`/`Palestrante` e suas cadeias · `AutorizaPorDepartamento`/`AcessoPorTipo`/
`MatrizCapacidades`/`AcessoPorTipoTest` (propagam/genéricos) · `CapacidadesSeeder`/`EstruturaCemaSeeder`
(data-driven) · `AdminPanelProvider` (auto-discovery) · `TransformadorLegado`/`LinkDrive`/`BaixadorImagem`
(reuso) · qualquer front/rota/curtida.

---

## 8. Cutover (o que roda no deploy — do dono, idempotente)

Ordem em produção (todos idempotentes/insert-only; **nunca** destrutivo):
1. `php artisan migrate` (as 4 migrations novas, incrementais).
2. `php artisan db:seed --class=CapacidadesSeeder` (cria as 4 `mensagem.*`).
3. `php artisan db:seed --class=TiposConteudoSeeder` (cria o tipo `mensagem` DoTipo + DEPAE).
4. `php artisan cema:importar-autores-espirituais` **antes** (garantir os 19 autores — a Fatia 1 já o fez; o
   import de mensagens casa autor por slug e **precisa** deles).
5. `php artisan cema:importar-mensagens` (túnel SSH ativo — as 179).

**A capacidade nasce INERTE** — ligar `mensagem.*` para DEPAE na tela `/admin/matriz-capacidades` é cutover
manual (quando houver edição pelo site, Fatias 4/5). Hoje só admin edita, e passa no `Gate::before`.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

### 9.0 Ordenação obrigatória (constraint TDD)
O `GlossarioCapacidadesMapaTest::test_todo_model_do_mapa_existe` faz `class_exists` sobre `RECURSOS_MODELS` ⇒ o
**model `Mensagem` (e o enum) devem existir ANTES** do edit `'mensagem'=>Mensagem::class`. Sequência de tasks:
model+enum+migrations → glossário/seeders/Camada 1 → Policy → Resource → cadeia de importação → command+bind.

### 9.1 `MensagemTest` (model) — molde `AutorEspiritualTest`
`$fillable` exato; casts (`formato` reidrata `FormatoMensagem`; `liberar_download` bool); `corpo` remove `<script>`;
`$table==='mensagens'`; `departamentos()`/`autores()`/`relacionadas()` anexam/leem pelos pivôs; `scopePublica`
filtra (`publicado`+`publico`); coleção `pictografia` **multi** registra conversões `web`/`thumb`
(`Storage::fake('public')` + PNG 1×1); `data_recebimento` round-trip Y-m-d↔Carbon; **relacionadas simétrica**
(§6.8) e **sem auto-relação**. Enum `FormatoMensagemTest` (casos/`opcoes()`).

### 9.2 Glossário + seeders (data-driven)
- `GlossarioCapacidadesMapaTest`: **sem edição** — verde ao atualizar os 3 mapas + criar o model.
- `CapacidadesSeederTest`: **editar** — 24→28 + `mensagem.{ver,criar,editar,excluir}`.
- `TiposConteudoSeederTest`: **aditivo** — `assertSame(['DEPAE'], siglasDe('mensagem'))` + regime DoTipo; a ordem
  final preserva os testes de guarda (`DED`/`DECOM`).

### 9.3 `MensagemPolicyCapacidadeTest` (DoTipo) — molde `AutorEspiritualPolicyCapacidadeTest`
`setUp` = `CapacidadesSeeder` + `EstruturaCemaSeeder` + `TiposConteudoSeeder`. Casos: responsável (DEPAE) **com**
`mensagem.editar` ⇒ `editar` **inclusive com mensagem sem departamento**; depto disjunto nega; sem permissão nega;
sem depto nega; **recurso sem linha** nega; **admin** passa em `ver/criar/editar/excluir`; `criar` com a **classe**.

### 9.4 `MensagemResourceTest` — molde `AutorEspiritualResourceTest`
`actingAsAdmin`; lista renderiza; `titulo` required; `corpo` RichEditor; `pictografia` ML multi; `formato`/`status`
Selects; `nivel` Select (opções incl. `publico`, aceita null); **`contexto` Textarea existe** (OA); `autores`
Select; `relacionadas` Select (não lista o próprio registro); criar/editar; corpo sanitiza. **Acrescentar:**
`assertFormFieldDoesNotExist('origem_da_mensagem')`, `…('grupo_mediunico')`, `…('casa_espirita')`.

### 9.5 `ImportadorMensagensTest` — molde `ImportadorAutoresEspirituaisTest`
Fake leitor (classe anônima) + `BaixadorImagem` sobrescrito (PNG 1×1, `null` se URL vazia) + `Storage::fake('public')`.
Casos: mapeamento (titulo/corpo/formato/nivel/data/status; publish→publicado, pending→pendente); **nível ausente →
null**; **gera slug único p/ pending sem post_name** (2 pending sem slug → 2 slugs distintos, determinísticos por
wp_id); **autor por slug** sincroniza N:N; **slug de autor inexistente vira aviso** (não quebra); **pictografia
multi** anexa todas; **O1** (mensagem sem foto no legado com pictografia posta no `/admin` → preservada no
re-import); **download** (`&amp;`→`&`, `uc?export=download`); `liberar` falsy ⇒ bool `false`; **poda**
origem/grupo/casa; **idempotência por wp_id** (2x ⇒ 179, mídia não duplica); **não sincroniza departamentos**;
**re-import preserva** `nivel`/`status`/`contexto`/`relacionadas` editados no `/admin` (o legado não os tem);
**import não popula `contexto`** (nasce `null`); **conta `publish_sem_nivel`** (os 2 publish sem termo).

### 9.6 `ImportarMensagensCommandTest` — molde `ImportarAutoresEspirituaisCommandTest`
`bind` do fake + `artisan('cema:importar-mensagens')->assertSuccessful()`; conferir N criados. **+ Guarda do
binding (I16):** resolver `app(LeitorMensagens::class)` **sem** fake ⇒ instância `…Mysql` (só resolve o container,
não chama `mensagens()`, **não** toca o legado).

### 9.7 Regressão + suíte
Baseline **915** (`artisan test --list-tests`); alvo **915 + novos**. `docker compose exec -T app php artisan test`
+ **Pint** verdes no container ([[pint-antes-de-push]]); ciência [[flaky-importadorblog-gd-cap-imagem]].
- **Pré-merge (R3, [[verificar-leitor-legado-contra-banco-real]]):** rodar o **leitor real**
  (`cema:importar-mensagens`) contra o **legado vivo** antes do merge (os `*Mysql` só têm fake na suíte),
  confirmando os 179, a rel 37, a taxonomia `nivel-de-acesso` e `_fotos_mensagem`.
- **Pré-merge (psicofonia — ciência §12):** com o túnel de volta, rodar `clean($corpo,'conteudo')` sobre os corpos
  **reais** de psicofonia do legado e conferir que nenhuma usa `table`/`div` para o layout Pergunta/Resposta (o
  perfil os removeria). Se houver, extender o perfil ou criar um `mensagem` dedicado **antes** do import definitivo.

---

## 10. Fora de escopo (Fatias 2B/3/4/5 — não fazer agora)

- **Front público** (2B): controller/rotas/views de listagem+detalhe (mensagens **e** autores), corpo por formato,
  card do(s) autor(es)/"sem assinatura", download, pictografia, "Do mesmo dia", exibição das relacionadas, SEO/OG.
- **Visibilidade rica** (3): 6 níveis, resolvedor por papel, badge/cadeado/login/403, "Direcionada"/destinatários,
  `mensagem_destinatarios`. **Não** ligar visibilidade por nível agora (o filtro fixo `publico` é hard-coded).
- **Eixo de autoria do médium** (4): `mensagem.publicar` (setor "Médium"/DEPAE via `setor_usuario`) e
  `mensagem.definir-nivel` (só diretor) — DATA-MODEL.md#L365-366.
- **Engajamento** (5): curtidas/favoritos/vistas (`mensagem_lidas`/`favoritos`/`autor_curtidas`).

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** model + enum + 4 migrations + Policy + Resource(+Pages) + cadeia de importação (Leitor/interface +
Importador + Command) + Factory + testes.
**Toca (edição mínima):** `GlossarioCapacidades` (3 mapas + `use`) · `TiposConteudoSeeder` (1 linha) ·
`AppServiceProvider::register()` (1 bind) · `CapacidadesSeederTest` (24→28) · `TiposConteudoSeederTest` (aditivo).
**NÃO toca:** `AutorEspiritual`/`Post`/`Palestrante` · `AutorizaPorDepartamento`/`AcessoPorTipo`/`MatrizCapacidades`/
`AcessoPorTipoTest` · `CapacidadesSeeder`/`EstruturaCemaSeeder` · `AdminPanelProvider` · `TransformadorLegado`/
`LinkDrive`/`BaixadorImagem` · qualquer front/rota/curtida.

---

## 12. Ciências (não são tarefa desta fatia)

- **Policy INERTE por desenho** (DoTipo + `/admin` admin-only + público só-Públicas). A Fatia 4 liga a autoria do
  médium; a Fatia 5 o engajamento. Não é bug.
- **`departamento_mensagem` nasce inerte** (DoTipo não consulta o pivô do objeto) — existe por **paridade** e p/
  permitir trocar o regime pela tela no futuro. Igual ao `departamento_autor_espiritual`.
- **Form inline vs. "fonte única".** Como não há edição fora do `/admin` nesta fatia, o form é **inline** no
  Resource. Quando a Fatia 4/5 abrir edição pelo site, extrair para `App\Filament\Schemas\MensagemForm` e
  reasserir campos privilegiados no servidor. Vigiar.
- **`nivel` BRUTO agora.** É string crua da taxonomia; a resolução por papel/visibilidade é da Fatia 3. O filtro
  público é **fixo** (`publico`), nunca um scope de visibilidade — ligar sem o cuidado da F3 **vazaria** mensagem
  restrita.
- **Chave de import = `wp_id`** (a Mensagem **tem** `wp_id`, diferente do Autor). Re-import após renomear o slug
  no `/admin` é **seguro** (reacha por `wp_id`).
- **2 publish sem nível somem do público** (posts 26021 "Caminhar com Cristo" e 26818 "Cérebro material versos…"):
  `nivel=null` ⇒ o filtro fixo `publico` os **exclui** da listagem pública (fail-closed correto), mas estavam
  `publish` no WP. O import registra o contador **`publish_sem_nivel`** (§6.6) para o dono classificá-los pela
  tela após o import (ou decidir). Não bloqueia a 2A.
- **`status` default `'publicado'` na migration** é seguro na 2A (o form é `required` e o import mapeia
  explicitamente). **Quando a Fatia 4 abrir a criação pelo médium, o default deve virar `'pendente'`** (a mensagem
  nasce para revisão). Anotado para a F4.
- **`clean('conteudo')` roda no IMPORT** (mutator no SET do `corpo`). **Verificado empiricamente:** o perfil
  **preserva** a estrutura da psicofonia (`h2/h3/h4/p/blockquote/ul/li/strong/em`) e **remove** `div/span/table`
  (mantendo o texto, mas sem os wrappers). Se alguma psicofonia do legado montar o layout Pergunta/Resposta em
  `<table>`/`<div>`, o import a **achataria**. ⇒ **checagem pré-merge** (§9.7) contra os corpos reais (o túnel caiu
  neste passe); se houver, extender o perfil `conteudo` ou criar um `mensagem` dedicado — decisão do passe.
- **Re-import sobrescreve o CONTEÚDO editado no `/admin`** (por desenho): título/corpo/formato/data/link/autores/
  pictografia são do legado (sempre atualizados); só `slug`/`status`/`nivel`/`contexto`/`relacionadas` sobrevivem
  (create-only/nunca). É raro (o import é cutover one-shot), mas registrado para o dono não estranhar se editar
  conteúdo no painel e um re-import futuro reverter (achado do passe do plano).

---

## 13. Passe adversarial próprio (18/jul) — achados e pendências para o dono

> **Passe interno rodado antes da entrega:** legado medido **ao vivo** (túnel ativo, só `SELECT`); 7 leitores
> paralelos varreram os moldes com **evidência `arquivo:linha`**; consumidores de `RECURSOS` varridos;
> pluralização e nomes de índice conferidos. As divergências abaixo **já estão incorporadas** ao spec.

**Correções que ESTE spec já incorpora (divergências do brief/kickoff, todas confirmadas ao vivo):**

- **C1 — download é 8, não 69 (§4.4).** `link_do_arquivo_mensagem` non-empty = 8; todo link tem `liberar` truthy.
- **C2 — nível é `publico`, não "publica" (§4.7).** Slug real da taxonomia; o filtro fixo usa `publico`. 5 níveis
  reais (o `diretor-depae` é ocioso); 49/179 sem termo → `null`.
- **C3 — nenhuma mensagem tem >1 autor (§4.5).** Os 96 vínculos da rel 37 são 96 mensagens distintas (1:1). N:N
  segue por **decisão** (flexibilidade), não por dado; o kickoff inferiu multiplicidade comparando 96 vínculos a
  81 publish (os outros 15 são pending). 51 publish têm **0** autor.
- **C4 — 39 pending sem `post_name` (§4.2).** Achado novo: a coluna `slug` é `unique NOT NULL` ⇒ o import **gera**
  slug determinístico por `wp_id` (I10) — padrão que a cadeia do Autor não tinha.
- **C5 — `_thumbnail_id=0`; pictografia via `_fotos_mensagem`, MULTI (§4.3/§4.6).** Sem capa; coleção
  multi-arquivo; parse por `unserialize` seguro; baixar por URL.
- **C6 — `mensagem.publicar`/`definir-nivel` são Fatia 4 (§1/§10).** DATA-MODEL.md#L365-366 define capacidades
  finas (setor Médium / diretor) que **não** são o padrão `ver/criar/editar/excluir`. A 2A entrega **só** a Policy
  DoTipo inerte.
- **C7 — bind do leitor no container (DEFEITO REAL evitado).** Sem `bind(LeitorMensagens::class, …Mysql::class)`,
  `cema:importar-mensagens` quebra em produção com a suíte verde. Incorporado (§6.6, I16, teste-guarda §9.6).
- **C8 — falta a migration `departamento_mensagem` (§6.3).** `Mensagem` implementa `TemDepartamento` ⇒ precisa do
  pivô (inerte sob DoTipo, por paridade); o kickoff citava só 2 pivôs.
- **C9 — corpo em 179/179 (§4.1), casa constante "cema" (§4.3).** Ajustes de fidelidade; `casa` não migra
  (default `'CEMA'`).

**Pontos ABERTOS para o passe adversarial do dono:**

1. **O1 — `data_recebimento`: `date` (recomendado) vs `datetime` (kickoff).** Recomendo **`date` + mutator
   portável** (§6.4): o legado é dia-granular (hora sempre 00:00), a seção "Do mesmo dia" agrupa por data, e há
   convenção/memória p/ portabilidade SQLite×MySQL ([[padrao-data-mutator-portavel]]). Se o dono quiser hora,
   volta a `datetime`+`DateTimePicker` (mas a hora do legado é sempre zero). **Decisão do dono.**
2. **O2 — `nivel` string vs relação.** O spec trata `nivel` como **string crua** (slug BRUTO), coerente com o
   kickoff ("importar o nível BRUTO") e com a Fatia 3 (que dará a semântica). Um dos leitores sugeriu modelar
   `nivel` como taxonomia/relação já na 2A — **rejeitado** aqui (seria fazer parte da F3 e arriscaria vazamento).
   Confirmar.
3. **O3 — simetria de `relacionadas` (§6.8).** Padrão novo (sem precedente). Proposto: armazenamento **dual
   espelhado** via `sincronizarRelacionadas()`, com o Select **fora** do auto-sync do Filament (que só grava uma
   direção). Alternativa considerada e preterida: uma única linha + leitura por união das duas direções (exige
   relação customizada, menos legível). **Confirmar a abordagem** antes do plano.
4. **O4 — siglas da semente = `['DEPAE']`** (kickoff), vs `['DEPAE','DECOM']` do Autor. É **valor inicial**
   reconfigurável na tela; DEPAE (eixo mediúnico) existe e não explode a guarda. Mantido conforme o kickoff.
   (DATA-MODEL.md usa `mensagens.*` no **plural**; o glossário é **singular** — divergência **textual** de doc a
   reconciliar depois, sem impacto de código.)
5. **O5 — rota do single (2B).** O kickoff sugere `/mensagem-mediunica/{mensagem:slug}` (singular +
   route-model-binding), mas **nenhum** single do projeto usa model-binding e list/single compartilham o **mesmo
   segmento** (§3.7). Recomendação p/ a 2B: `/mensagens-mediunicas` + `/mensagens-mediunicas/{slug}` (`{slug}` cru
   + `->where`, nomes `mensagens.index`/`mensagens.show`). **Decisão do dono** (marketing pode preferir a URL
   singular).
6. **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose exec
   -T app php artisan test`; **todo brief de subagente que rode `artisan` DEVE proibir
   `migrate:fresh/refresh/wipe/reset` e seed destrutivo** e reafirmar `legado` como read-only
   ([[nunca-migrate-fresh-no-dev]]).

---

### Passe adversarial do CONSULTOR (18/jul) — veredito: ✅ SÓLIDA, 1 obrigatório aplicado

O Consultor **re-mediu o legado ao vivo** e bateu com a medição deste spec: nível `publico` (2 publish sem termo);
download **8** (o "48" de uma medição grossa contava a STRING `'false'` como truthy — `statusParaAtivo` trata
certo, o spec está certo em 8); autor **1:1** (96 mensagens, todas com exatamente 1 autor); 39 pending sem slug;
data dia-granular. Um obrigatório **aplicado**, confirmações abaixo.

**OA — campo `contexto` (OBRIGATÓRIO) — APLICADO.** Decisão travada do dono ("contexto **manual, não IA**"; a
"Faixa de contexto" do handoff single §4.3). Estava **omitido** e o model nasce agora ⇒ barato adicionar (senão
viraria migration corretiva, como o `chamada` da Fatia 1). Incorporado: coluna `text` nullable (§6.3), `$fillable`
+ bullet (§6.1/I1), Textarea no form (§6.7), **texto puro** (sem `clean`/HTML, exibido escapado), import **não**
popula (§6.6, nasce `null`), 2B exibe a faixa (§10). Confirmado ao vivo: **não há meta de contexto no legado**.

**Confirmações dos pontos abertos — Consultor concorda:** O1 `date`+mutator portável (dia-granular confirmado —
**não** `datetime`); O2 `nivel` string bruta; **O3 cravado** — `sincronizarRelacionadas` escreve os 2 sentidos
numa transação, Select **fora** do auto-sync do Filament, teste "remover reflete nos 2 lados", FKs `cascade` cobrem
o delete sem órfão (§6.8); O4 semente `['DEPAE']`; O5 rota `/mensagens-mediunicas/{slug}` (o handoff usa a URL
singular, mas a convenção do projeto vence — decisão do dono, é 2B).

**Ciências novas incorporadas (§12):** (a) 2 publish **sem nível** somem do público (fail-closed) → contador
`publish_sem_nivel` no resumo do import; (b) `status` default `'publicado'` seguro na 2A, mas a **Fatia 4** (criação
pelo médium) deve virar `'pendente'`; (c) `clean('conteudo')` roda **no import** e **preserva** a estrutura da
psicofonia (`h2/h3/h4/p/blockquote/ul/li/strong/em` — **verificado empiricamente**) mas **remove** `div/span/table`
⇒ **checagem pré-merge** (§9.7) contra os corpos reais (o túnel caiu no meio deste passe).

**Refinamento no plano (create-only) — §6.6/I13 reconciliados:** ao escrever o PLANO, `status`/`nivel`/`slug`
viraram **create-only** (via `firstOrNew`, não `updateOrCreate`): a fonte inicial é o legado, mas depois são do
admin. Assim um re-import **não** zera a classificação de nível dos 49 sem-termo que o dono faz pela tela (ciência
§12) nem desfaz um despublicar/rename. Conteúdo (titulo/corpo/formato/data/link/liberar/autores/pictografia) segue
**sempre** atualizado pelo legado.

**Veredito do Consultor:** com o `contexto` adicionado, **segue para o PLANO**; ele oferece o passe do plano antes
da execução.

---

### Passe do PLANO — Consultor (18/jul) — ✅ APROVADO, zero bloqueador

Confirmado contra código/legado: `firstOrNew` create-only (curadoria preservada, conteúdo sempre do legado);
`slugUnico` com guarda de colisão; `sincronizarRelacionadas` dual fiel ao molde `SincronizaPessoas`;
`pluck('mensagens.id')` qualificado; O1/bind C7+guarda/índices explícitos/autor-por-slug. **Medido:** **0**
mensagens têm `<img>`/iframe/`wp-content` no corpo ⇒ RichEditor simples basta (clone do **Autor**, não do Post —
acertado; sem `ReescritorImagensConteudo`).

**Refinamentos APLICADOS na 2A (o model nasce agora):**
- **R-A — `link_arquivo` normalizado no mutator do model** (`set → LinkDrive::paraDownload`): cobre o import **e**
  um link colado no `/admin` (`/file/d/…/view` vira download direto); o importador passa o link **cru**. §6.1
  (mutator), §6.6 (importador), + teste `test_link_arquivo_normalizado_via_link_drive` (§9.1).
- **M-A — `string('link_arquivo', 500)`** na migration (§6.3), alinhado ao `maxLength(500)` do form (evita "Data
  too long" no strict mode).

**Ciências registradas (não mudam o plano):** re-import sobrescreve conteúdo do `/admin` (§12, acima);
`assertFormFieldDoesNotExist` não é usado em nenhum teste do projeto — o fallback do plano (remover se não existir)
é aceitável, a Task 1 já prova a ausência das colunas.

**Veredito:** com R-A/M-A aplicados, **segue para a execução** (subagente-por-task). O passe do **PR** cobrará o R3
(leitor real: **179 · autor 96 · pictografia 2 · download 8 · publish_sem_nivel 2**) e a checagem da psicofonia
(Task 6, passo 3).
