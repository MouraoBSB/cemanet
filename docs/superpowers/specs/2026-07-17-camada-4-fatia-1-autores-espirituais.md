# Spec — Camada 4 · Fatia 1 · Autores Espirituais (satélite, sem mensagens ainda)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17
> Enquadramento travado com o dono no kickoff da Camada 4 (Mensagens Mediúnicas), atualizado 2026-07-17
> (depto responsável = `['DEPAE','DECOM']`). Este spec **não** improvisa além das decisões travadas; **cada
> afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha` no §3) e o
> **legado foi medido ao vivo** pela conexão `legado` (somente `SELECT`, §4). Pontos em aberto ou em que o
> enquadramento **diverge da medição** estão no §13 para o **passe adversarial**.
> Destino: **SPEC** — vai ao passe adversarial **antes** de virar plano de implementação. **NÃO implementar
> ainda.**
> Base: `origin/main` (HEAD `c988f89`, PR #35 — **Fatia 0 mesclada**; a Fatia 1 ramifica daqui). Suíte baseline: **879 testes**
> (Fatia 0 mesclada, PR #35 `c988f89`; ver [[camada-4-fatia-0-integridade-papeis-spec]]).
> Fundação: o **modelo de capacidades** (VISIBILIDADE × CAPACIDADE, CLAUDE.md) + a **Camada 1** (acesso por
> tipo: `RegimeAcesso::DoTipo`/`PorRegistro`). Esta fatia clona um molde **já existente e testado** — o
> **Palestrante** — para uma entidade nova; não inventa arquitetura.

---

## 1. Contexto e objetivo

A Camada 4 abre o módulo **Mensagens Mediúnicas**. Uma mensagem é **psicografada/atribuída a um espírito** — o
**autor espiritual** (ex.: *Bezerra de Menezes*, *Irmã Cecília*). Antes da mensagem existir, precisamos do
**satélite** de que ela dependerá: o cadastro dos autores. Esta **Fatia 1** entrega **só** esse cadastro.

**Objetivo:** criar a entidade `AutorEspiritual` **clonando fielmente o molde do Palestrante** (mesma família:
`HasMedia` + `TemDepartamento`, regime **DoTipo** da Camada 1, Policy de capacidade, config na tela), com
**CRUD no `/admin`**, e **migrar os 19 autores** do CPT `autores-espirituais` do WordPress legado — clonando a
**cadeia de importação de Eventos** (Leitor + Importador + Command, com DI e idempotência).

**Por que "clone do Palestrante" é a decisão certa.** O Palestrante já é a entidade da mesma forma exata:
cadastro editorial com **foto (MediaLibrary)**, **bio HTML sanitizada**, **`chamada` (frase do hero)**,
**`ativo`**, **regime DoTipo** (departamento vem do tipo, não do form) e Policy `AutorizaPorDepartamento`. O
autor espiritual é o Palestrante **menos** os campos de contato (email/telefone/mostrar_*) — que autor espiritual
não tem. Clonar reduz o risco a zero: o caminho já está pavimentado e coberto por testes.

**A entidade nasce com a autorização INERTE — e isso é o esperado (§5.4).** Regime **DoTipo** + `/admin`
admin-only (`Gate::before`) + **sem superfície de site do autor nesta fatia** ⇒ a Policy existe mas **não morde**
ninguém hoje (só o admin edita, e ele passa antes no `Gate::before`). A maquinaria (permissions, tipo, Policy)
é montada **agora** para que a **Fatia 2** (página pública + curtidas + edição pelo site) a **ligue** sem dev de
fundação — exatamente como Agenda/Palestra/Post já vivem.

---

## 2. Decisões travadas (não reabrir)

1. **Clone fiel do Palestrante**, **departamentalizado** (entra na Camada 1): `TemDepartamento` + pivô +
   Policy + entrada no glossário + config de acesso por tipo. **Não** é admin-only por natureza — é DoTipo,
   hoje inerte.
2. **Manter `chamada`** (frase do hero), **nullable**. O legado **não** tem esse campo (§4.2) ⇒ importa `null`;
   é preenchido no `/admin`.
3. **Cortar** `email`, `telefone`, `mostrar_email`, `mostrar_telefone` — autor espiritual não tem contato.
4. **Adiado para a Fatia 2 (NÃO fazer agora):** página pública (controller/rotas/views), **curtidas** (coluna
   `curtidas` + Livewire de curtir), relação `mensagens()` e a grade de conteúdo do autor.
5. **Depto responsável na semente = `['DEPAE','DECOM']`** (brief atualizado 17/jul). É **valor inicial**,
   reconfigurável pela tela `/admin/matriz-capacidades` sem dev (insert-only nunca sobrescreve — §6.5). **Não**
   semear vazio: tipo DoTipo sem responsável grava **fail-closed** e o insert-only **congela** o vazio.
6. **Regime DoTipo** — o departamento vem do **tipo** (config da tela), **não** do formulário. O form **não** tem
   Select de "Departamentos" (o Palestrante não tem — §3.6).
7. **Importação idempotente** por **slug** (não há `wp_id` na tabela): `updateOrCreate(['slug'=>…])` +
   `clearMediaCollection` antes de reanexar a foto. Rodar 2x não duplica registro nem mídia.
8. **Legado é READ-ONLY** — só `SELECT` (§4 foi medição, nunca escrita).
9. **Sem `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo** no dev (152 usuários + 123
   palestras/agenda + 44 posts + 19 autores a importar + mídia). Só `migrate` incremental
   ([[nunca-migrate-fresh-no-dev]]).

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado no código em 2026-07-17 (base `c988f89`; a Fatia 0 só tocou `UserResource`/`CreateUser`/`EditUser`, então as citações abaixo são idênticas em `cfe3873`..`c988f89`). **Docblock não é evidência** — o que segue foi lido no
fonte.

### 3.1 O model-molde: `Palestrante`

[App\Models\Palestrante](../../../app/Models/Palestrante.php):

- `implements HasMedia, TemDepartamento` + `use HasFactory, InteractsWithMedia, RegistraImagensPadrao, TemIniciais`
  ([:18-20](../../../app/Models/Palestrante.php#L18-L20)).
- `const COLECAO_FOTO = 'foto'` ([:22](../../../app/Models/Palestrante.php#L22)).
- `$fillable` inclui `email, telefone, mostrar_email, mostrar_telefone` ([:24-27](../../../app/Models/Palestrante.php#L24-L27))
  — **estes 4 saem** no clone.
- `casts()` só booleanos `mostrar_email/mostrar_telefone/ativo` ([:29-36](../../../app/Models/Palestrante.php#L29-L36))
  — no clone sobra **só `ativo => 'boolean'`**.
- `scopeAtivo` ([:38-41](../../../app/Models/Palestrante.php#L38-L41)); `departamentos(): BelongsToMany` para
  `departamento_palestrante` ([:55-58](../../../app/Models/Palestrante.php#L55-L58)); `registerMediaCollections`
  chamando `registrarColecaoImagem(self::COLECAO_FOTO)` ([:60-65](../../../app/Models/Palestrante.php#L60-L65));
  `fotoUrl`/`fotoThumbUrl` ([:68-81](../../../app/Models/Palestrante.php#L68-L81)); `bio` com
  `clean($value, 'conteudo')` ([:83-88](../../../app/Models/Palestrante.php#L83-L88)).
- ⚠️ `palestras()`/`palestrasMinistradas()` ([:43-53](../../../app/Models/Palestrante.php#L43-L53)) são
  **específicos do Palestrante** — **não clonar** (o autor não tem `palestra_pessoa`).

### 3.2 As migrations-molde (e uma armadilha)

- [create_palestrantes_table](../../../database/migrations/2026_06_24_213725_create_palestrantes_table.php#L14-L26):
  `id, nome, slug unique, foto, bio longText null, email, telefone, mostrar_* bool, ativo default true, timestamps`.
  ⚠️ **A coluna `foto` string ([:18](../../../database/migrations/2026_06_24_213725_create_palestrantes_table.php#L18))
  foi DROPADA depois** por
  [2026_06_29_000002_drop_foto_from_palestrantes](../../../database/migrations/2026_06_29_000002_drop_foto_from_palestrantes.php)
  (o Palestrante migrou p/ MediaLibrary). O clone usa MediaLibrary **desde o dia 1** ⇒ **não** criar a coluna `foto`.
- `chamada` **não** está no create; foi adicionada por
  [2026_07_01_000001_add_chamada_to_palestrantes_table](../../../database/migrations/2026_07_01_000001_add_chamada_to_palestrantes_table.php#L13-L15)
  (`string nullable`). O clone já **nasce** com `chamada` no create (tabela nova).
- [create_departamento_palestrante_table](../../../database/migrations/2026_07_11_000004_create_departamento_palestrante_table.php#L11-L17):
  `id + foreignId('palestrante_id')->constrained('palestrantes')->cascadeOnDelete() + foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete() + unique([...])`.

### 3.3 A Policy-molde e o trait que a alimenta

[PalestrantePolicy](../../../app/Policies/PalestrantePolicy.php): `use AutorizaPorDepartamento`,
`recurso(): string => 'palestrante'` ([:22-25](../../../app/Policies/PalestrantePolicy.php#L22-L25)), 4 abilities
pt-BR `ver/criar/editar/excluir` = `hasPermissionTo("palestrante.<acao>") && $this->noEscopo/podeCriarNoEscopo`
([:27-45](../../../app/Policies/PalestrantePolicy.php#L27-L45)).

[AutorizaPorDepartamento](../../../app/Policies/Concerns/AutorizaPorDepartamento.php): `abstract protected recurso()`
([:24](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L24)); `noEscopo()` **bifurca por regime**
([:27-36](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L27-L36)) — `DoTipo` ⇒
`usuarioHabilitadoNoTipo`, `PorRegistro` ⇒ interseção do objeto, `null` ⇒ `false`. `podeCriarNoEscopo` idem
([:44-47](../../../app/Policies/Concerns/AutorizaPorDepartamento.php#L44-L47)). **⇒ trocar só `recurso()` no
clone basta** — o trait faz o resto por regime, sem código novo.

[AcessoPorTipo::usuarioHabilitadoNoTipo](../../../app/Support/Autorizacao/AcessoPorTipo.php#L40-L61): `DoTipo` ⇒
usuário está num departamento **responsável pelo tipo** (config da tela); `null`/sem responsável/sem depto ⇒
`false` (fail-closed).

### 3.4 A Camada 1 é data-driven de `GlossarioCapacidades` (mexer em 1 lugar propaga)

[GlossarioCapacidades](../../../app/Support/Autorizacao/GlossarioCapacidades.php):
- `RECURSOS` = `['evento','palestra','post','agenda','palestrante']` — **5**
  ([:19](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L19)).
- `RECURSOS_ROTULOS` ([:24-30](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L24-L30)) e
  `RECURSOS_MODELS` ([:33-39](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L33-L39)).
- `permissions()` gera `recurso.acao` para cada par ⇒ **5×4 = 20**
  ([:50-60](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L50-L60)).

Quem **consome** `RECURSOS` e propaga sozinho ao adicionar `'autor_espiritual'`:
- [CapacidadesSeeder](../../../database/seeders/CapacidadesSeeder.php#L17-L23) — `Permission::updateOrCreate` por
  nome ⇒ semeia **4 novas** permissions (20→24), idempotente.
- [TiposConteudoSeeder](../../../database/seeders/TiposConteudoSeeder.php) — itera `recursos()` (= `RECURSOS`) e
  **explode** se faltar semente ([:34-37](../../../database/seeders/TiposConteudoSeeder.php#L34-L37)) **ou** se
  uma sigla não existir em `departamentos` ([:75-83](../../../database/seeders/TiposConteudoSeeder.php#L75-L83)).
- [MatrizCapacidades::secoesPorRecurso](../../../app/Filament/Pages/MatrizCapacidades.php#L87-L143) — **uma
  `Section` por recurso** ([:137](../../../app/Filament/Pages/MatrizCapacidades.php#L137)) ⇒ 6ª section "Autor
  Espiritual" automática; `mount()` lê `TipoConteudo` por recurso ([:67-74](../../../app/Filament/Pages/MatrizCapacidades.php#L67-L74)).

⇒ **A única mudança de fundação é adicionar `'autor_espiritual'` nos 3 mapas do glossário**; seeders e tela
propagam. **Mas o `TiposConteudoSeeder` EXIGE a semente** (senão `RuntimeException`) — §6.5.

### 3.5 A cadeia de importação-molde: Eventos (DI + idempotência)

- [LeitorEventos](../../../app/Importacao/LeitorEventos.php#L7-L15): interface de 1 método `eventos(): array`.
- [LeitorEventosMysql](../../../app/Importacao/LeitorEventosMysql.php): `DB::connection('legado')`
  ([:14-17](../../../app/Importacao/LeitorEventosMysql.php#L14-L17)); `SELECT … FROM wp_posts WHERE post_type=…
  AND post_status='publish'` ([:21-25](../../../app/Importacao/LeitorEventosMysql.php#L21-L25)); `metasDe()`
  1-valor-por-chave ([:57-68](../../../app/Importacao/LeitorEventosMysql.php#L57-L68)); `urlDaImagem()` =
  `SELECT guid FROM wp_posts WHERE ID=? AND post_type='attachment'`
  ([:71-79](../../../app/Importacao/LeitorEventosMysql.php#L71-L79)) — **é exatamente o `_thumbnail_id → guid` que
  o autor precisa**.
- [ImportadorEventos](../../../app/Importacao/ImportadorEventos.php): DI `LeitorEventos + BaixadorImagem`
  ([:19-22](../../../app/Importacao/ImportadorEventos.php#L19-L22)); `updateOrCreate`
  ([:67](../../../app/Importacao/ImportadorEventos.php#L67)); `clearMediaCollection` **antes** de reanexar
  ([:83-84](../../../app/Importacao/ImportadorEventos.php#L83-L84)); `baixarCapado → addMediaFromString`
  ([:86-96](../../../app/Importacao/ImportadorEventos.php#L86-L96)); **⚠️ `departamentos()->sync()`
  ([:111-118](../../../app/Importacao/ImportadorEventos.php#L111-L118)) é do regime PorRegistro (Evento) — NÃO
  clonar** (§6.6).
- [ImportarEventos](../../../app/Console/Commands/ImportarEventos.php): `signature 'cema:importar-eventos'`
  ([:16](../../../app/Console/Commands/ImportarEventos.php#L16)); valida `legado->getPdo()` **só quando o leitor
  real está em uso** ([:23-33](../../../app/Console/Commands/ImportarEventos.php#L23-L33)); fail-fast de
  pré-requisito (categorias) ([:36-40](../../../app/Console/Commands/ImportarEventos.php#L36-L40)); resumo com
  contadores/avisos ([:44-53](../../../app/Console/Commands/ImportarEventos.php#L44-L53)).
- [BaixadorImagem::baixarCapado](../../../app/Importacao/BaixadorImagem.php#L53-L116): baixa URL http(s), capa o
  lado maior a `$teto` px, devolve bytes ou `null`.

### 3.6 O Resource-molde: `PalestranteResource` (form inline, sem Departamentos)

[PalestranteResource](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php): `$model`
([:32](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L32)), `navigationIcon` =
`Heroicon::OutlinedUser` ([:34](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L34)),
labels ([:36-40](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L36-L40)) +
`recordTitleAttribute='nome'` ([:42](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L42)).

Form ([:44-126](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L44-L126)) em 4 seções:
| Seção | Campos | No clone |
|---|---|---|
| Dados pessoais ([:48-79](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L48-L79)) | nome (slug auto `Str::slug` no create, `:55-60`), slug unique, **email** (`:69-72`), chamada (`:74-78`) | **cortar email** |
| Foto ([:81-85](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L81-L85)) | `ComponentesImagem::upload('foto', COLECAO_FOTO)` | manter (trocar model) |
| Biografia ([:87-104](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L87-L104)) | `RichEditor::make('bio')` + toolbar | manter |
| Contato e exibição ([:106-123](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L106-L123)) | **telefone**, **mostrar_email**, **mostrar_telefone**, **ativo** | **cortar seção**; ⚠️ **`ativo` precisa de novo lar** (§6.7) |

**Não há Select de "Departamentos"** no form (regime DoTipo — confirmado, alinha com a decisão travada §2.6).
Table ([:128-186](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L128-L186)): colunas foto,
nome, slug, **email (`:148-151`)**, **mostrar_email (`:153-155`)**, ativo, created/updated — **cortar email e
mostrar_email**. `getPages()` = index/create/edit
([:193-200](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php#L193-L200)).

Pages triviais: [CreatePalestrante](../../../app/Filament/Resources/Palestrantes/Pages/CreatePalestrante.php#L10-L13)
(vazio), [EditPalestrante](../../../app/Filament/Resources/Palestrantes/Pages/EditPalestrante.php#L11-L21)
(`DeleteAction` no header), [ListPalestrantes](../../../app/Filament/Resources/Palestrantes/Pages/ListPalestrantes.php#L11-L21)
(`CreateAction` no header).

[ComponentesImagem::upload(string $nome, string $colecao, bool $multiplas=false)](../../../app/Filament/Support/ComponentesImagem.php#L22-L42):
fábrica de `SpatieMediaLibraryFileUpload` no disco `public`, resize ≤2000px, conversão `thumb`.

**Resources são auto-descobertos** —
[AdminPanelProvider::panel](../../../app/Providers/Filament/AdminPanelProvider.php#L85) faz
`->discoverResources(in: app_path('Filament/Resources'), …)`. **Nenhum registro manual** do novo Resource.

### 3.7 As traits e o factory-molde

- [TemIniciais](../../../app/Models/Concerns/TemIniciais.php#L13-L29): usa `nomeParaIniciais()` → `$this->nome`
  ([:25-28](../../../app/Models/Concerns/TemIniciais.php#L25-L28)) — funciona no autor (tem `nome`).
- [RegistraImagensPadrao::registrarColecaoImagem($colecao, unica=true, larguraWeb=1600, ladoThumb=400)](../../../app/Models/Concerns/RegistraImagensPadrao.php#L32-L56)
  — coleção single-file WebP `web`/`thumb`. O Palestrante chama com só o nome (defaults).
- [PalestranteFactory](../../../database/factories/PalestranteFactory.php): `nome, slug único, bio, email,
  telefone, mostrar_*, ativo` + estados `ativo()/inativo()` — **cortar email/telefone/mostrar_***
  ([:18-21](../../../database/factories/PalestranteFactory.php#L18-L21)).

### 3.8 Os testes data-driven (o que muda de cor — e o que **não** muda)

⚠️ **Correção ao brief:** nem todos os 4 testes citados "mudam de contagem". Medido linha a linha:

| Teste | Afirma contagem 20/5? | O que acontece ao somar `autor_espiritual` |
|---|---|---|
| [CapacidadesSeederTest](../../../tests/Feature/Autorizacao/CapacidadesSeederTest.php#L16-L33) | **SIM**: `assertSame(20, …)` (`:29`) **+ lista os 20 nomes** (`:21-27`) | **EDITAR**: 20→24 e **acrescentar os 4** `autor_espiritual.*` |
| [TiposConteudoSeederTest](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php) | **Não** (usa `count(RECURSOS)`, `:36/:69`) | **ADITIVO**: acrescentar assert de siglas `['DECOM','DEPAE']` (`:42-51`) e do regime DoTipo (`:53-62`) para o autor |
| [GlossarioCapacidadesMapaTest](../../../tests/Unit/Autorizacao/GlossarioCapacidadesMapaTest.php) | **Não** — é `assertEqualsCanonicalizing(RECURSOS, keys(RECURSOS_MODELS))` (`:18-21`) + `class_exists` (`:35-40`) | **Fica verde sozinho SE** os 3 mapas forem atualizados juntos **e o model `AutorEspiritual` existir** — `test_todo_model_do_mapa_existe` (`:35-40`) **fica vermelho** se somar o recurso sem o model |
| [MatrizCapacidadesTest](../../../tests/Feature/Filament/MatrizCapacidadesTest.php) | **Não** conta Sections — testa render/acesso/save de recursos específicos | **Nenhuma edição**; depende do `setUp` semear `TiposConteudoSeeder` (`:29`) — que agora exige a semente do autor + DEPAE/DECOM (existem no `EstruturaCemaSeeder` do `setUp`, `:27`) |

⇒ **Só o `CapacidadesSeederTest` tem número mágico a trocar.** Os outros são estruturais/comportamentais; o
`GlossarioCapacidadesMapaTest` **força o model a existir** (bom — é a rede que pega "somei o recurso e esqueci o
model"). Ver §13.

### 3.9 Os molde-de-teste

- [PalestranteResourceTest](../../../tests/Feature/Filament/PalestranteResourceTest.php): `actingAsAdmin` no
  `setUp` (`:29`); render de lista, campo `nome` required (`:46-50`), RichEditor `bio` (`:52-56`), upload
  MediaLibrary `foto` c/ `getCollection()===COLECAO_FOTO` (`:58-62`), criar via form (`:64-80` — **inclui
  email/mostrar_*, sair no clone**), editar (`:82-92`), bio sanitizada (`<script>` some, `:94-109` — ⚠️ o slug é **fixado** no
  `fillForm` `:99`, logo esse teste **não** exercita o slug-auto; a geração automática vem do `afterStateUpdated`
  do form, testável à parte se o clone quiser), `chamada` opcional (`:118-122`) e criar com `chamada` (`:124-139`).
- [AcessoPorTipoTest](../../../tests/Feature/Autorizacao/AcessoPorTipoTest.php): **o molde da semântica DoTipo** —
  responsável habilita (`:84-89`), disjunto nega (`:91-96`), sem depto nega (`:98-103`), sem linha/sem
  responsável nega (`:55-80`).
- [EventoPolicyCapacidadeTest](../../../tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php): molde
  **estrutural** de teste de Policy por `Gate::forUser()->check()` — **mas Evento é `PorRegistro`**; suas
  asserções de **interseção por objeto** (`:70-108`) **não** valem para DoTipo (§6.3). Reusar o *esqueleto*
  (seeders `:26-31`, helpers `usuario/admin/depto` `:34-57`, `test_nome_cru_… :129-137`, `test_admin_passa_… :147-156`),
  **trocar as asserções** para as de DoTipo do `AcessoPorTipoTest`.
- [ImportadorEventosTest](../../../tests/Feature/Importacao/ImportadorEventosTest.php): fake leitor por classe
  anônima (`:27-38`), `BaixadorImagem` sobrescrito p/ devolver PNG 1×1 sem HTTP (`:41-55`), `Storage::fake('public')`
  (`:84`), idempotência (`:169-178`).
- [ImportarEventosCommandTest](../../../tests/Feature/Importacao/ImportarEventosCommandTest.php): `bind` do fake
  no container (`:24-35`) + `artisan('cema:…')->assertSuccessful()` (`:37-38`); teste de fail-fast (`:44-58`).

---

## 4. Medições no legado (banco vivo, somente `SELECT`, 17/07/2026)

Consultas na conexão `legado` (túnel SSH ativo, container `cema-app`). **Nenhuma escrita.** Confirmam e refinam
o mapeamento do brief.

### 4.1 População

| Medida | Valor |
|---|---|
| `post_type='autores-espirituais'`, `post_status='publish'` | **19** |
| Outros status (draft/pending/private/…) | **0** — todos publish |
| Slugs (`post_name`) duplicados entre os publish | **0** ⇒ `updateOrCreate` por slug é seguro |

### 4.2 Cobertura de campos (sobre os 19 publish)

| Campo/meta | Cobertura | Destino | Nota |
|---|--:|---|---|
| `post_title` | 19 | `nome` | — |
| `post_name` | 19 | `slug` | ⚠️ **difere do título** às vezes (ex.: título **"Rajian"** → slug **`radian`**) — usar `post_name`, nunca derivar do título |
| `post_content` | **13/19** (6 vazios) | `bio` (sanitizada) | 6 autores com content vazio ⇒ **bio `null`** (`post_content ?: null`) |
| `_thumbnail_id` | **14/19** | `foto` (MediaLibrary) | 5 sem thumbnail ⇒ **foto ausente** (`baixarCapado(null)=null`) |
| *(chamada)* | **0** | `chamada` | **não existe no legado** ⇒ importa `null` (decisão §2.2) |
| *(status/ativo)* | **0** | `ativo` | **não existe** ⇒ **default `true`** no DB |
| email/telefone | 0 | — | não existem (autor não tem contato) |

**Exemplo `_thumbnail_id → guid`:** `thumb_id=21676 → https://cemanet.org.br/wp-content/uploads/2025/09/klauss-1.jpg`
— host `cemanet.org.br`, URL real; `baixarCapado` baixa por HTTP (idêntico a palestrantes/eventos).

### 4.3 Achados que o brief não previu (relevantes p/ a Fatia 2 — **não** são tarefa desta fatia)

- 🔎 **As curtidas do legado JÁ EXISTEM.** Meta `jet_engine_store_count_curtir-autor-espiritual` presente em
  **18/19** — é o contador de "curtir" do Jet Engine para este CPT. A **Fatia 2** (curtidas) tem, portanto,
  uma **fonte de dados** para semear os contadores no primeiro import. Registrado como vigilância; **nada** se
  importa disso agora (§10).
- 🔎 **Há `post_excerpt`** (~250–450 chars em ~13/19). **Não** é mapeado nesta fatia (a `chamada` importa `null`
  por decisão §2.2). Se a Fatia 2 quiser uma origem para `chamada`/resumo, o excerpt é candidato — decisão do
  dono, fora daqui.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Model:** `AutorEspiritual` tem `$fillable = [nome, slug, chamada, bio, ativo]` (**sem** email/telefone/mostrar_*), cast `ativo=>boolean`, `scopeAtivo`, `departamentos()` (pivô `departamento_autor_espiritual`), `COLECAO_FOTO='foto'` com conversões `web`/`thumb`, `bio` sanitizada (`<script>` some), `implements HasMedia, TemDepartamento`. | §9.1 |
| **I2** | **Tabela/pluralização:** o model grava em **`autores_espirituais`** (Laravel pluralizaria `autor_espirituals` — exige `$table`). O pivô referencia `autores_espirituais` (não `autor_espirituals`). | §9.1 |
| **I3** | **Glossário propaga:** somar `'autor_espiritual'` aos 3 mapas ⇒ `permissions()` tem **24** nomes (os 4 `autor_espiritual.*`), `RECURSOS_MODELS['autor_espiritual']===AutorEspiritual::class`, `RECURSOS` tem **6**. | §9.2 |
| **I4** | **Seeders idempotentes:** `CapacidadesSeeder` cria as 24 permissions (2ª vez não duplica); `TiposConteudoSeeder` cria o tipo `autor_espiritual` **regime DoTipo** com responsáveis **`['DECOM','DEPAE']`**, insert-only (não sobrescreve config da tela). | §9.2 |
| **I5** | **Guarda do seeder:** `TiposConteudoSeeder` **explode** (`RuntimeException`) se faltar a semente do autor **ou** se `DEPAE`/`DECOM` não existir em `departamentos` — o lugar de explodir é o seeder (não a autorização). | §9.2 |
| **I6** | **Policy DoTipo:** usuário num depto **responsável** (DEPAE ou DECOM) **com** `autor_espiritual.editar` ⇒ `editar` permitido, **mesmo o autor não tendo departamento** (DoTipo ignora o pivô do objeto); usuário de depto **disjunto** nega; **sem a permissão** nega; **recurso sem linha** em `tipos_conteudo` nega; **admin** passa em tudo. | §9.3 |
| **I7** | **Resource `/admin`:** cria/edita autor via form; **`email`/`telefone`/`mostrar_*` NÃO existem** no form; `foto` é `SpatieMediaLibraryFileUpload` com `COLECAO_FOTO`; `chamada` opcional; slug auto no create; bio sanitizada. | §9.4 |
| **I8** | **Import — mapeamento:** os 19 viram `AutorEspiritual` por slug; `nome←post_title`, `bio←post_content` (`null` se vazio), `foto←_thumbnail_id→guid`; `chamada`/`ativo` **não** vêm do legado (chamada `null`, ativo `true`). | §9.5 |
| **I9** | **Import — idempotência:** rodar 2x ⇒ **19 registros** (não 38); autor **com** thumbnail: mídia **não** duplica (clear+reanexa **dentro** do `if foto_url`); autor **sem** thumbnail: **nenhuma** mídia tocada, sem erro. | §9.5 |
| **I10** | **Import — não toca depto nem clobber:** o importador **não** chama `departamentos()->sync()` (DoTipo); e **não** sobrescreve `chamada`/`ativo` **nem a foto de autor sem thumbnail** já editados no `/admin` num re-import. Regra: o legado sobrescreve **só o que ELE tem** (`nome`/`bio`/foto-se-thumbnail); preserva o que não tem (`chamada`/`ativo`/foto-sem-thumb). | §9.5 |
| **I11** | **Command:** `cema:importar-autores-espirituais` valida `legado` só com o leitor real; com leitor fake injetado, importa e dá `assertSuccessful`. | §9.6 |
| **I12** | **Binding do leitor:** resolver `LeitorAutoresEspirituais` pelo container devolve `LeitorAutoresEspirituaisMysql`; `cema:importar-autores-espirituais` resolve **sem** bind manual de fake — pega o green-em-teste/broken-em-prod (C7). | §9.6 |
| **I-neutro** | **Nenhuma asserção de teste existente muda de cor** exceto a edição deliberada do `CapacidadesSeederTest` (§3.8). Suíte fica verde (**879** + novos). | §9.7 |

---

## 6. Decisões de desenho

### 6.1 O model (clone enxuto)

`App\Models\AutorEspiritual` — **igual ao Palestrante menos os campos de contato**, **mais** `$table` explícito:

- `protected $table = 'autores_espirituais';` — **obrigatório**: o pluralizador inglês do Laravel geraria
  `autor_espirituals` (I2).
- `implements HasMedia, TemDepartamento`; `use HasFactory, InteractsWithMedia, RegistraImagensPadrao, TemIniciais`.
- `const COLECAO_FOTO = 'foto';`
- `$fillable = ['nome','slug','chamada','bio','ativo'];`
- `casts(): ['ativo' => 'boolean']`.
- `scopeAtivo`; `departamentos()` → `belongsToMany(Departamento::class, 'departamento_autor_espiritual', 'autor_espiritual_id', 'departamento_id')`;
  `registerMediaCollections()` → `registrarColecaoImagem(self::COLECAO_FOTO)`; `fotoUrl`/`fotoThumbUrl`; `bio`
  com `clean($value,'conteudo')`.
- **Não** clonar `palestras()`/`palestrasMinistradas()`.

### 6.2 As migrations (2 novas, incrementais, nada destrutivo)

1. `create_autores_espirituais_table`: `id; string nome; string slug unique; string chamada nullable; longText
   bio nullable; boolean ativo default true; timestamps`. **Sem** `foto` (MediaLibrary — §3.2), **sem**
   email/telefone/mostrar_*, **sem** `curtidas`, **sem** `wp_id`.
2. `create_departamento_autor_espiritual_table`: `id; foreignId('autor_espiritual_id')->constrained('autores_espirituais')->cascadeOnDelete();
   foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete(); unique(['autor_espiritual_id','departamento_id'])`.
   ⚠️ O `constrained('autores_espirituais')` é **explícito** — sem ele o Laravel apontaria p/ `autor_espirituals`.
   **Ordem das migrations (C8):** o timestamp da pivô deve ser **posterior** ao da `create_autores_espirituais_table`
   (base primeiro, pivô depois — como `palestrantes` `2026_06_24` < `departamento_palestrante` `2026_07_11`),
   senão o FK não resolve no `migrate` incremental.

### 6.3 A Policy (troca de 1 linha)

`App\Policies\AutorEspiritualPolicy` = clone de `PalestrantePolicy` com `recurso() => 'autor_espiritual'` e os
tipos trocados p/ `AutorEspiritual`. **Nada mais** — o trait `AutorizaPorDepartamento` bifurca por regime
(§3.3). Como é **DoTipo**, a semântica é a do `AcessoPorTipoTest` (responsável pelo **tipo**, o objeto não é
consultado) — por isso o teste da Policy **não** clona as asserções de interseção do Evento (§3.9).

**Registro da Policy — auto-discovery (verificado, §13/O2):** o projeto **não** tem mapa explícito de policies
(`grep` por `Gate::policy`/`$policies`/`guessPolicyNamesUsing` = zero); as 5 policies existentes
(Palestrante/Evento/Post/Palestra/AgendaDia) são descobertas por convenção `Models\X → Policies\XPolicy`.
`AutorEspiritualPolicy` é achada **automaticamente** — **nenhum registro manual**. O admin passa antes no único
`Gate::before` do sistema ([AppServiceProvider.php:65](../../../app/Providers/AppServiceProvider.php#L65)).

### 6.4 O glossário (3 linhas — o coração data-driven)

Em `GlossarioCapacidades`: adicionar `'autor_espiritual'` a `RECURSOS`, `RECURSOS_ROTULOS`
(`'autor_espiritual' => 'Autor Espiritual'`) e `RECURSOS_MODELS` (`'autor_espiritual' => AutorEspiritual::class`,
com o `use`). ⚠️ **Anexar ao FINAL de `RECURSOS`** (depois de `'palestrante'`) — **não** antes de `'palestra'`:
[TiposConteudoSeederTest:111](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L111)
(`expectExceptionMessage('DED')`) conta que, com **todos** os departamentos apagados, o **1º** recurso DoTipo a
falhar seja `'palestra'` (`['DED']`); inserir `autor_espiritual` (`['DEPAE','DECOM']`) antes faria a mensagem
citar "DEPAE, DECOM" **sem** DED e o teste ficaria vermelho (**R1** do passe). Isso, **sozinho**, gera as 4
permissions (via `CapacidadesSeeder`), a 6ª section da matriz e o recurso no catálogo. **É a única mudança de
fundação.**

### 6.5 A semente do tipo (obrigatória — senão o seeder explode)

Em `TiposConteudoSeeder::SEMENTE`, acrescentar:
```php
'autor_espiritual' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DEPAE', 'DECOM']],
```
- **Por que não vazio:** um tipo DoTipo sem responsável grava **fail-closed** e o insert-only
  ([TiposConteudoSeeder:44-48](../../../database/seeders/TiposConteudoSeeder.php#L44-L48)) **congela** o vazio —
  resemear não repara, só a tela. O sintoma seria "ninguém edita autor", irreparável por reseed (§2.5).
- **DEPAE + DECOM existem** em [GlossarioUsuarios::DEPARTAMENTOS](../../../app/Importacao/GlossarioUsuarios.php#L21-L30)
  (`DEPAE`=Assistência Espiritual `:26`, `DECOM`=Comunicação e Multimídia `:29`), semeados pelo
  [EstruturaCemaSeeder](../../../database/seeders/EstruturaCemaSeeder.php#L28-L33) ⇒ a guarda de sigla **não**
  explode.
- **É valor inicial**, não sentença: reconfigurável na tela `/admin/matriz-capacidades` sem dev (insert-only
  nunca reescreve config da tela — [TiposConteudoSeederTest:73-87](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L73-L87)).
- **Rationale do par:** DEPAE = eixo mediúnico (abriga o setor **Médium**,
  [GlossarioUsuarios:35](../../../app/Importacao/GlossarioUsuarios.php#L35)); DECOM = eixo publicação (já dono de
  Agenda `[DED,DECOM]` e Post `[DECOM]`). Espelha o desenho da Agenda.

### 6.6 A cadeia de importação (clone de Eventos, **sem** o bloco de departamentos)

- **`App\Importacao\LeitorAutoresEspirituais`** (interface): `autores(): array`.
- **`App\Importacao\LeitorAutoresEspirituaisMysql`** (clone do `LeitorEventosMysql`):
  `SELECT ID, post_title, post_name, post_content FROM wp_posts WHERE post_type='autores-espirituais' AND
  post_status='publish'`; `_thumbnail_id` via `metasDe()`; `foto_url` via `urlDaImagem(thumbId)` (guid do
  attachment). Emite por autor: `['slug','nome','bio','foto_url']`. **Não** lê galeria, departamentos nem
  `chamada` (não existem — §4.2).
- **`App\Importacao\ImportadorAutoresEspirituais`** (clone do `ImportadorEventos`): DI
  `LeitorAutoresEspirituais + BaixadorImagem`; para cada autor
  `updateOrCreate(['slug'=>$d['slug']], ['nome'=>$d['nome'], 'bio'=>$d['bio']])`;
  ⚠️ **o `clearMediaCollection(COLECAO_FOTO)` vai DENTRO do `if foto_url`** — **não** incondicional como o molde
  [ImportadorEventos:83-84](../../../app/Importacao/ImportadorEventos.php#L83-L84):
  `if ($d['foto_url']) { clearMediaCollection(COLECAO_FOTO); baixarCapado($url,2000) → addMediaFromString(...)->toMediaCollection(COLECAO_FOTO); }`.
  Autor **com** thumbnail = refresh idempotente (limpa e reanexa a mesma foto); autor **sem** thumbnail =
  **mídia intocada** (preserva foto posta no `/admin`) — achado **O1** do passe do dono (§13);
  contadores/avisos (ex.: "sem thumbnail", "falha ao baixar foto"). **NÃO** clonar `departamentos()->sync()`
  (I10) — DoTipo. **NÃO** setar `chamada`/`ativo` no `updateOrCreate` — são **do admin** (chamada fica `null`
  no create; `ativo` default `true`; ambos **preservados** num re-import — I10).
- **`App\Console\Commands\ImportarAutoresEspirituais`** (clone do `ImportarEventos`):
  `signature 'cema:importar-autores-espirituais'`; valida `legado->getPdo()` **só** com `LeitorAutoresEspirituaisMysql`;
  resumo com contadores/avisos. ⚠️ **Sem o fail-fast de categorias** (o Evento tem; autor **não** tem
  pré-requisito de catálogo) — a única guarda é a conexão `legado` (§13, ponto O4).
- **`App\Providers\AppServiceProvider::register()`** — ⚠️ **binding OBRIGATÓRIO** (achado do passe adversarial,
  §13/C7): `$this->app->bind(LeitorAutoresEspirituais::class, LeitorAutoresEspirituaisMysql::class);` junto dos 5
  binds de leitor já existentes ([AppServiceProvider.php:36-40](../../../app/Providers/AppServiceProvider.php#L36-L40)).
  O command **e** o Importador type-hintam a **interface** `LeitorAutoresEspirituais` (como o `ImportadorEventos`);
  **sem o bind**, resolvê-la em produção lança `BindingResolutionException` e o `cema:importar-…` (cutover §8,
  passo 4) quebra — mas a **suíte fica verde** (o teste do command injeta o fake, mascarando o furo). Por isso
  §9.6 traz o teste-guarda que resolve **sem** fake (I12).

### 6.7 O Resource (clone com corte + o lar do `ativo`)

`App\Filament\Resources\AutoresEspirituais\AutorEspiritualResource` (auto-descoberto — §3.6), Pages análogas.
Form em **3 seções** (a "Contato e exibição" some inteira):
- **Dados** — `nome` (slug auto no create), `slug`, `chamada`, **e o toggle `ativo`** (o `ativo` do Palestrante
  vivia na seção cortada; realojar aqui — I7).
- **Foto** — `ComponentesImagem::upload('foto', AutorEspiritual::COLECAO_FOTO)`.
- **Biografia** — `RichEditor::make('bio')`.

Table: foto, nome, slug, ativo, created/updated — **sem** email/mostrar_email. Labels/`navigationLabel` =
"Autores Espirituais"; `modelLabel` = "Autor Espiritual"; `navigationIcon` próprio (ex.:
`Heroicon::OutlinedSparkles` — cosmético). **Sem** Select de "Departamentos".

### 6.8 Factory

`Database\Factories\AutorEspiritualFactory`: `nome`, `slug` único, `bio`, `ativo` + estados `ativo()/inativo()`.
**Sem** email/telefone/mostrar_*. `chamada` não é obrigatória (default `null`).

---

## 7. As peças (inventário)

**Novos (com cabeçalho de autoria — CLAUDE.md §8):**
`app/Models/AutorEspiritual.php` · `database/migrations/xxxx_create_autores_espirituais_table.php` ·
`database/migrations/xxxx_create_departamento_autor_espiritual_table.php` · `app/Policies/AutorEspiritualPolicy.php` ·
`app/Filament/Resources/AutoresEspirituais/AutorEspiritualResource.php` + `Pages/{Create,Edit,List}AutorEspiritual.php` ·
`app/Importacao/LeitorAutoresEspirituais.php` + `LeitorAutoresEspirituaisMysql.php` +
`app/Importacao/ImportadorAutoresEspirituais.php` · `app/Console/Commands/ImportarAutoresEspirituais.php` ·
`database/factories/AutorEspiritualFactory.php` · testes (§9).

**Editados (mínimo):** `app/Support/Autorizacao/GlossarioCapacidades.php` (3 mapas) ·
`database/seeders/TiposConteudoSeeder.php` (1 linha em `SEMENTE`) ·
`tests/Feature/Autorizacao/CapacidadesSeederTest.php` (20→24 + 4 nomes) ·
`tests/Feature/Autorizacao/TiposConteudoSeederTest.php` (asserts aditivos do autor) ·
`app/Providers/AppServiceProvider.php` (**1 bind de leitor** — C7).

**NÃO toca:** `Palestrante` e sua cadeia · `AutorizaPorDepartamento`/`AcessoPorTipo` · `MatrizCapacidades`
(propaga sozinho) · `CapacidadesSeeder`/`EstruturaCemaSeeder` (data-driven/idempotentes) · `AdminPanelProvider`
(auto-discovery) · qualquer coisa de Mensagens/curtidas/site.

---

## 8. Cutover (o que roda no deploy — do dono, idempotente)

Ordem em produção (todos idempotentes/insert-only; **nunca** destrutivo):
1. `php artisan migrate` (as 2 migrations novas, incrementais).
2. `php artisan db:seed --class=CapacidadesSeeder` (cria as 4 `autor_espiritual.*`).
3. `php artisan db:seed --class=TiposConteudoSeeder` (cria o tipo `autor_espiritual` DoTipo + DEPAE/DECOM —
   `EstruturaCemaSeeder` já rodou em ambientes existentes, as siglas existem).
4. `php artisan cema:importar-autores-espirituais` (túnel SSH ativo — os 19).

**A capacidade nasce INERTE** (sem superfície de site do autor): ligar `autor_espiritual.*` para DEPAE/DECOM na
tela `/admin/matriz-capacidades` é **cutover manual da Fatia 2**, quando houver edição pelo site — hoje não é
necessário (só admin edita, e passa no `Gate::before`). Alinha com [[fase-c-matriz-capacidade-spec]] (a matriz é
a única escritora de `role_has_permissions`).

---

## 9. Plano de teste (TDD real, vermelho primeiro)

### 9.1 `AutorEspiritualTest` (model) — molde do teste de model do Palestrante
`$fillable` exato (sem contato); cast `ativo`; `scopeAtivo` filtra; `bio` remove `<script>`; `departamentos()`
anexa/lê pelo pivô; `$table === 'autores_espirituais'`; `COLECAO_FOTO` registra conversões `web`/`thumb`
(`Storage::fake('public')` + `addMediaFromString` de PNG 1×1).

### 9.2 Glossário + seeders (data-driven)
- `GlossarioCapacidadesMapaTest`: **sem edição** — fica verde ao atualizar os 3 mapas + criar o model (o
  `test_todo_model_do_mapa_existe` prova o model). Opcional: acrescentar assert `modelDe('autor_espiritual')===AutorEspiritual::class`.
- `CapacidadesSeederTest`: **editar** — 20→24 e acrescentar `autor_espiritual.{ver,criar,editar,excluir}`.
- `TiposConteudoSeederTest`: **aditivo** — `assertSame(['DECOM','DEPAE'], siglasDe('autor_espiritual'))`;
  autor no laço de regime DoTipo. (A guarda de sigla/semente já é coberta pelos testes existentes `:89-156`.)

### 9.3 `AutorEspiritualPolicyCapacidadeTest` (DoTipo) — esqueleto do Evento, asserções do `AcessoPorTipoTest`
`setUp` = `CapacidadesSeeder` + `EstruturaCemaSeeder` + `TiposConteudoSeeder`. Casos:
- responsável (user em **DEPAE** ou **DECOM**) **com** `autor_espiritual.editar` ⇒ `Gate::check('editar', $autor)` **true**,
  **inclusive com autor sem departamento** (prova que DoTipo ignora o pivô do objeto — o oposto do
  `test_objeto_sem_departamento_so_admin` do Evento);
- user de depto **disjunto** (ex.: DED) nega; **sem** a permissão nega; **sem** depto nega;
- **recurso sem linha** (deletar a linha `autor_espiritual` de `tipos_conteudo`) ⇒ nega até p/ responsável+permissão;
- **admin** passa em `ver/criar/editar/excluir`; **anônimo** nega; `criar` invocado com a **classe**.

### 9.4 `AutorEspiritualResourceTest` — molde do `PalestranteResourceTest`
`actingAsAdmin`; lista renderiza; `nome` required; `bio` RichEditor; `foto` MediaLibrary c/ `COLECAO_FOTO`;
criar via form (**sem** email/mostrar_*); editar; slug auto; bio sanitiza; `chamada` opcional + criar com
`chamada`. **Acrescentar:** `assertFormFieldDoesNotExist('email')`, `…('telefone')`, `…('mostrar_email')`,
`…('mostrar_telefone')` (prova o corte).

### 9.5 `ImportadorAutoresEspirituaisTest` — molde do `ImportadorEventosTest`
Fake leitor (classe anônima) + `BaixadorImagem` sobrescrito (PNG 1×1, `null` se url vazia) + `Storage::fake('public')`.
Casos: mapeamento (nome/slug/bio/foto); **bio `null`** quando content vazio; **foto ausente** quando `foto_url=null`
(sem erro); **idempotência** (importar 2x ⇒ 1 registro, mídia não duplica); **não sincroniza departamentos**
(`$autor->departamentos()->count()===0`); **re-import preserva `chamada`/`ativo`** editados (setar `chamada`
manual + `ativo=false`, reimportar, conferir que persistem — I10); **re-import de autor SEM thumbnail preserva a
foto do `/admin`** (autor com `foto_url=null`: anexar mídia manual, reimportar, conferir que **permanece** — O1);
e **re-import de autor COM thumbnail** re-anexa a foto do legado sem duplicar (idempotência de mídia).

### 9.6 `ImportarAutoresEspirituaisCommandTest` — molde do `ImportarEventosCommandTest`
`bind` do fake no container + `artisan('cema:importar-autores-espirituais')->assertSuccessful()`; conferir os N
autores criados. (Sem análogo do "aborta sem categorias" — não há pré-requisito de catálogo; §6.6/O4.)
**+ Guarda do binding (I12):** um teste que **não** injeta o fake e resolve `app(LeitorAutoresEspirituais::class)`
(ou `app(ImportadorAutoresEspirituais::class)`), assertando instância `…Mysql` — trava a regressão
green-em-teste/broken-em-prod. Só resolve o container (não chama `autores()`), então **não** toca o legado.

### 9.7 Regressão + suíte
Suíte existente **verde** exceto a edição deliberada do `CapacidadesSeederTest`. Baseline **879** (medir com
`artisan test --list-tests`); alvo **879 + novos**. `docker compose exec -T app php artisan test` + **Pint**
verdes no container ([[pint-antes-de-push]]); ciência [[flaky-importadorblog-gd-cap-imagem]] (2 testes de cap de
imagem do blog podem falhar sob carga — se passam isolados/no CI, não é regressão desta fatia).
- **Pré-merge (R3, [[verificar-leitor-legado-contra-banco-real]]):** os leitores `*Mysql` só têm **fake** na
  suíte — rodar o **leitor real** (`cema:importar-autores-espirituais`) contra o **legado vivo** antes do merge,
  confirmando `post_type='autores-espirituais'`, `_thumbnail_id` e `guid→host`. O SQL escrito precisa ser
  exercido ao vivo (a introspecção do §4 foi medida no meu passe com o túnel ativo; no passe do dono o túnel
  estava caído).

---

## 10. Fora de escopo (Fatia 2+ — não fazer agora)

- **Página pública** do autor (controller/rotas/views index+show) e SEO/OG.
- **Curtidas:** coluna `curtidas` + componente Livewire — **e** o import dos contadores do legado
  (`jet_engine_store_count_curtir-autor-espiritual`, §4.3), que ficam **disponíveis** para quando a Fatia 2
  chegar.
- **`mensagens()`** e a grade de conteúdo do autor (é a Camada 4 propriamente dita).
- **Mapear `post_excerpt`** para `chamada`/resumo (§4.3) — decisão do dono, se e quando.
- **Ligar a capacidade** na matriz (cutover da Fatia 2, §8).

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** model + 2 migrations + Policy + Resource(+Pages) + cadeia de importação (Leitor/interface +
Importador + Command) + Factory + testes.
**Toca (edição mínima):** `GlossarioCapacidades` (3 mapas) · `TiposConteudoSeeder` (1 linha) ·
`AppServiceProvider::register()` (**1 bind de leitor**) · `CapacidadesSeederTest` (20→24) ·
`TiposConteudoSeederTest` (asserts do autor).
**NÃO toca:** `Palestrante`/sua cadeia · `AutorizaPorDepartamento`/`AcessoPorTipo`/`MatrizCapacidades` ·
`CapacidadesSeeder`/`EstruturaCemaSeeder` · `AdminPanelProvider` · `User`/policies existentes · qualquer coisa
de Mensagens/curtidas/site público.

---

## 12. Ciências (não são tarefa desta fatia)

- **Autorização INERTE por desenho.** DoTipo + `/admin` admin-only + sem site ⇒ a Policy não morde hoje. É o
  mesmo estado do Palestrante. A **Fatia 2** liga tudo (site + matriz) sem dev de fundação. Não é bug — é o
  ponto de a fatia ser "satélite".
- **O pivô `departamento_autor_espiritual` nasce inerte.** Sob DoTipo o objeto não é consultado; o pivô existe
  por **paridade com o Palestrante** e para permitir trocar o regime para PorRegistro pela tela no futuro (aí
  o pivô passa a valer). Igual ao `departamento_palestrante`.
- **Form inline vs. "fonte única".** O CLAUDE.md pede form de **fonte única** (`Schemas\*Form`) quando há
  edição **fora** do `/admin`. Como o Palestrante, o autor **não** tem superfície não-admin nesta fatia ⇒ form
  **inline** no Resource (como o Palestrante). Quando a **Fatia 2** abrir edição pelo site, o form deve ser
  **extraído** para `App\Filament\Schemas\AutorEspiritualForm` (molde `EventoForm`/`AgendaDiaForm`) e os campos
  privilegiados reasseridos no servidor. Vigiar.
- **Importação passa ao largo da autorização** (como todo importador) — roda como comando, não pelo
  `/minha-conta`; não há R1/R2 aqui (aquilo é usuário, não autor). Nada a fazer.
- **`chamada`/`ativo` são do admin, não do legado** — o import **não** os toca (I10), então re-import é seguro
  mesmo depois de curadoria manual. Diferente do Evento (que reescreve mais campos), de propósito.
- **Chave de import = `slug`, sem `wp_id`** (não há a coluna, por decisão). Re-import **após renomear o slug no
  `/admin`** cria **duplicata** (o `updateOrCreate` não reacha o registro antigo). **Aceitável:** o import é
  **one-shot no cutover**, não recorrente; o Evento tem `wp_id` de reserva, o autor não — de propósito (**R2** do
  passe do dono). Registrado como vigilância.

---

## 13. Passe adversarial próprio (17/jul) — achados e pendências para o dono

> **Passe interno rodado antes da entrega:** verificação adversarial em paralelo (6 verificadores + crítico de
> completude, **116 citações `arquivo:linha` reconferidas no fonte**), legado medido ao vivo, consumidores de
> `RECURSOS` varridos, pluralização cravada no `tinker`. **Veredito:** **111/116 citações exatas**; 3 ajustes de
> faixa de linha + 2 achados de completude (**1 defeito real** — C7, binding do leitor; 1 nota — C8, ordem de
> migrations), **todos aplicados**. Pontos abertos abaixo.

**Correções que ESTE spec já incorpora (divergências do brief):**

- **C1 — os 4 testes "data-driven" não mudam todos de contagem (§3.8).** Só o `CapacidadesSeederTest` fixa `20`.
  `GlossarioCapacidadesMapaTest` é canonicalizing + `class_exists` (muda de cor só por **exigir o model**);
  `MatrizCapacidadesTest` **não** conta Sections; `TiposConteudoSeederTest` usa `count(RECURSOS)` (aditivo, não
  substitutivo). O brief dizia "20→24, 5→6" para o Mapa — **impreciso**; corrigido.
- **C2 — a coluna `foto` do molde de migration foi dropada depois (§3.2).** Clonar o create "1:1" reintroduziria
  uma coluna morta. O clone **não** cria `foto` (MediaLibrary).
- **C3 — pluralização (§6.1/§6.2), verificado no `tinker`.** `Str::snake(Str::pluralStudly('AutorEspiritual'))`
  = **`autor_espirituals`** (tabela default **errada**); FK default = `autor_espiritual_id` (essa serve como
  coluna do pivô). Exige `$table='autores_espirituais'` no model **e** `constrained('autores_espirituais')`
  explícito no pivô (senão o FK apontaria p/ `autor_espirituals`). Sem isso a app quebra em runtime — **não**
  nos testes de unidade do glossário.
- **C4 — o teste da Policy DoTipo não clona as asserções do Evento (§3.9/§9.3).** Evento é PorRegistro (interseção
  por objeto); autor é DoTipo (responsável pelo tipo, objeto ignorado). Reusar só o **esqueleto**; asserções vêm
  do `AcessoPorTipoTest`. Acrescentei o caso-chave "responsável edita autor **sem** departamento".
- **C5 — o `ativo` precisa de novo lar no form (§6.7).** No Palestrante ele vive na seção "Contato e exibição",
  que é cortada. Realojado em "Dados".
- **C6 — o importador não deve tocar `chamada`/`ativo` (I10).** Como são do admin e o legado não os tem, incluí-los
  no `updateOrCreate` os clobber­aria num re-import. O import só traz `nome`/`bio`/foto.
- **C7 — binding do leitor no container (DEFEITO REAL evitado, achado do passe).** O molde só funciona porque
  [AppServiceProvider::register():36-40](../../../app/Providers/AppServiceProvider.php#L36-L40) faz `bind` de
  **toda** interface de leitor ao `...Mysql`, e o command/importador type-hintam a **interface**. Clonar **sem**
  `bind(LeitorAutoresEspirituais::class, LeitorAutoresEspirituaisMysql::class)` faria `cema:importar-autores-espirituais`
  lançar `BindingResolutionException` **em produção** — com a **suíte verde** (o teste do command injeta o fake).
  Incorporado: §6.6 (peça), §7/§11 (inventário), I12/§9.6 (teste-guarda).
- **C8 — ordem das migrations (menor).** A pivô precisa de timestamp **posterior** à base (senão `constrained`
  não resolve no migrate incremental). Explicitado em §6.2.

**Achados do legado que refinam o plano (§4):** 19 todos publish; slug ≠ título (usar `post_name`); 6 bios vazias
(→ null); 5 sem foto; **curtidas do legado já existem** (fonte p/ Fatia 2); `post_excerpt` presente (não mapeado).

**Pontos ABERTOS para o passe adversarial do dono:**

1. **O1 — depto responsável (RESOLVIDO no brief atualizado):** `['DEPAE','DECOM']`. Ambos existem; guarda não
   explode; reconfigurável na tela. ✅ (§2.5/§6.5)
2. **O2 — registro da Policy (RESOLVIDO no passe):** `grep` por `Gate::policy`/`$policies`/`guessPolicyNamesUsing`
   = **zero**; as 5 policies existentes são **auto-descobertas** por convenção. `AutorEspiritualPolicy` é achada
   automaticamente — **nenhum registro manual** (§6.3). O admin passa no único `Gate::before`
   ([AppServiceProvider.php:65](../../../app/Providers/AppServiceProvider.php#L65)). ✅
3. **O3 — consumidores de `RECURSOS` (RESOLVIDO no passe — era o maior risco de efeito colateral):** varridos
   **todos** os usos de `GlossarioCapacidades::RECURSOS` no código (fora de docs): só `CapacidadesSeeder` (via
   `permissions()`), `TiposConteudoSeeder`, `MatrizCapacidades` e `GlossarioCapacidadesMapaTest` — **todos**
   tratados por esta fatia. `app/Http` **não** consome `RECURSOS`; o `/minha-conta` (Fase D) usa superfície
   **explícita por recurso** ([AbaAgenda](../../../app/Support/Conta/AbaAgenda.php) /
   [AgendaConta](../../../app/Livewire/Conta/AgendaConta.php), **só Agenda**), **não** um laço sobre `RECURSOS`
   ⇒ somar `autor_espiritual` **não** expõe aba/edição quebrada em `/minha-conta`. ✅
4. **O4 — command sem fail-fast de catálogo (§6.6):** diferente do molde (Evento tem a guarda de categorias), o
   autor não tem pré-requisito além da conexão `legado`. Mantido de propósito; registrado para o dono não
   estranhar a ausência.
5. **Regra sempre:** pt-BR em tudo; cabeçalho de autoria no PHP novo; `Pint` antes do push; `docker compose
   exec -T app php artisan test`; **todo brief de subagente que rode `artisan` DEVE proibir
   `migrate:fresh/refresh/wipe/reset` e seed destrutivo** (dev tem dados importados) e reafirmar `legado` como
   read-only. Ver [[nunca-migrate-fresh-no-dev]].

---

### Passe adversarial do DONO (17/jul) — veredito: ✅ SÓLIDA, aprovada com O1/O2 aplicados

Confirmado pelo dono contra o código: C7 real (bom pegador); pluralização; I-neutro (único número mágico =
[CapacidadesSeederTest:29](../../../tests/Feature/Autorizacao/CapacidadesSeederTest.php#L29)); `EstruturaCemaSeeder`
roda antes do `TiposConteudoSeeder` em todo `setUp` ⇒ DEPAE existe ⇒ a semente nova não explode a suíte; Policy
auto-descoberta.

**Obrigatórios — APLICADOS:**
- **O1 (clobber de mídia) — CORRIGIDO.** O `clearMediaCollection` do molde é **incondicional**
  ([ImportadorEventos:83-84](../../../app/Importacao/ImportadorEventos.php#L83-L84)) ⇒ nos **5 autores sem
  thumbnail**, um re-import **apagaria** a foto posta no `/admin` (mesmo clobber evitado em `chamada`/`ativo`, mas
  a mídia ficou fora da proteção). Movido para **dentro do `if foto_url`** (§6.6). Regra final coerente: o legado
  sobrescreve só o que ELE tem (`nome`/`bio`/foto-se-thumbnail), preserva o resto. Invariantes I9/I10 atualizados;
  caso novo em §9.5.
- **O2 (base) — CORRIGIDO.** Base = **`c988f89`** (PR #35, Fatia 0 mesclada), não `cfe3873`. Header/§3
  atualizados; a Fatia 1 **ramifica de `c988f89`**. A Fatia 0 só tocou `UserResource`/`CreateUser`/`EditUser` —
  **sem colisão** com os arquivos desta fatia (as citações do §3 permanecem válidas).

**Refinamentos — INCORPORADOS:**
- **R1 (ordem de `RECURSOS`) — aplicado (§6.4):** `'autor_espiritual'` vai ao **final** de `RECURSOS`; antes de
  `'palestra'` quebraria [TiposConteudoSeederTest:111](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L111)
  (a mensagem deixaria de citar `DED`).
- **R2 (chave = slug, sem `wp_id`) — registrado (§12):** re-import após renomear o slug no `/admin` cria
  duplicata; aceitável (import one-shot no cutover).
- **R3 (revalidar o leitor no PR) — na checklist (§9.7):** rodar o leitor real contra o legado vivo antes do
  merge ([[verificar-leitor-legado-contra-banco-real]]); túnel esteve caído no passe do dono.
- **R4 (cosmético):** [CamadaUmFiltroPorTipoTest:142](../../../tests/Feature/Autorizacao/CamadaUmFiltroPorTipoTest.php#L142)
  tem o comentário "cinco recursos" — agora **6**; o autor não entra nesse guardião (I2), mas seu fail-closed
  está no §9.3; ajustar o comentário **se tocar o arquivo** (opcional).

**Veredito:** com O1/O2 aplicados, a SPEC segue para o **PLANO**; o dono oferece o passe do plano antes da
execução.
