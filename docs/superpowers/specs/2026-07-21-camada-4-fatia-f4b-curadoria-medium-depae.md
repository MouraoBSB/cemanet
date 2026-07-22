# Spec — Camada 4 · Fatia F4b · Curadoria das Mensagens (o médium lança, o DEPAE publica)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21
> Enquadramento travado com o dono no kickoff da F4b. Este spec **não** improvisa além das decisões travadas
> (D1–D9, §2); **cada afirmação sobre o terreno foi verificada contra o código real** (evidência `arquivo:linha`
> no §3).
> **Dois passes já rodaram** (§14): (1) **terreno**, com 18 agentes — 10 investigadores + 8 verificadores
> encarregados de *refutar* G1, G2, G3, G6, G12, G13, T6 e D8 —, que **derrubou 3 premissas do kickoff** e achou
> **1 bug latente crítico de migration**; (2) **revisão adversarial deste spec**, com 5 lentes (completude ·
> cobertura de teste · fronteiras/creep · técnica do código prescrito · verificação de citações), que achou
> **7 bloqueadores neste documento** — todos corrigidos abaixo (§14.2).
> **Passe do consultor: ✅ APROVADA para virar PLANO** (§14.3), com **3 obrigatórios** (M1/M2/M3) e **2 decisões
> novas do dono** (**D10** e **D11**) — todos já incorporados abaixo. **Zero ponto aberto**: O1/O6 fechados pelo
> autor, O5 fechado pelo dono, O2/O3/O4/O7 ratificados (§13).
> Destino: **PLANO** (tasks TDD, ordem §9.0) → passe do plano → execução. **NÃO implementar ainda.**
> Base: `origin/main` (HEAD **`8142883`**, PR #42 — **F4a mesclada**). Branch: `camada-4-fatia-f4b-curadoria-mensagens`.
> Suíte baseline: **1097 testes** em 250 arquivos (medido com `--list-tests`, não estimado).
> Fundação: **2A** · **2B** · **3A** (enum de 6 níveis + resolvedor + pivô `mensagem_destinatario`) · **3B**
> (barreira + `scopePublicado`) · **3C** (aba "Minhas Direcionadas") · **F4a** (destinatários + guard de nível no
> `/admin`). A F4b é a **primeira escrita de Mensagem fora do `/admin`** e a **primeira leitura de trilha** do projeto.

---

## 0. Recorte: o que a F4b fecha (e o que fica para F5)

Hoje uma Mensagem só nasce por **duas** portas: o importador do legado e o `/admin` (admin-only,
`User::canAccessPanel → hasRole('administrador')`). Os **46 médiuns** e os **2 diretores do DEPAE** do dev **não
são admin** e não têm nenhuma porta. A F4b abre o ciclo mínimo ponta a ponta **no próprio site**:

- **(a) o MÉDIUM lança** em `/minha-conta/mensagens` → nasce **pendente**, com `medium_id` dele e `nivel = null`
  (ele **não** arbitra nível — D2);
- **(b) o DIRETOR DO DEPAE cura** em `/minha-conta/curadoria` → revisa, corrige, **arbitra o nível** e **publica**,
  vendo **quem lançou** e o **histórico** do item.

**O que a F4b NÃO é:** não devolve publicado→pendente; não despublica nem exclui pelo site; **não edita mensagem
já publicada pelo site** (O7); não manda e-mail; não toca a matriz de capacidades (`mensagem.*` segue **inerte**);
não abre o `/admin` a não-admin; não cria viewer geral de trilha no painel; não toca o front público nem a 3C.

---

## 1. Contexto e objetivo

O eixo desta fatia é **PERTENCIMENTO (setor/cargo)**, não capacidade — como a 3C e **diferente** da Fatia D. Não é
preferência de estilo: é a única leitura que funciona (§3.4 — a matriz é `role`-based por construção do spatie, e o
regime `DoTipo` do recurso `mensagem` habilitaria **3 pessoas**, não os 46 médiuns).

**Objetivo (aditivo, todo no `/minha-conta`):**

1. **Aba "Minhas Mensagens"** (`conta.mensagens`), para quem é do setor **Médium**: lista as **próprias**; **cria**
   e **edita enquanto pendente**; publicada vira **read-only**.
2. **Aba "Curadoria"** (`conta.curadoria`), para o **Diretor do DEPAE** (+ presidente): **fila de todas as
   pendentes** (inclusive as **47 legadas**, rotuladas "Importada do legado"), com **quem lançou**, **quando** e
   aviso de **"editada pelo autor após o lançamento"**; ao abrir, **form + histórico** e dois botões: **Salvar**
   (segue pendente) e **Publicar** (o martelo).
3. **Autoria interna** (`medium_id`, `publicado_por_id`, `publicado_em`) — para curadoria e `/admin`, **nunca** no
   front (D8).
4. **Trilha de auditoria da Mensagem** (hoje inexistente) + o **primeiro leitor de trilha** do projeto, mostrando
   **nomes de campos**, nunca valores (G13).

**Sem migration não há fatia:** `mensagens` não tem coluna de autoria (§3.1). É a **primeira migration aditiva com
FK** do projeto inteiro — e o lugar do bug latente C-F.

---

## 2. Decisões travadas (não reabrir)

1. **D1 — fatia ÚNICA:** as duas superfícies no mesmo PR.
2. **D2 — o médium NÃO escolhe o nível.** Ele marca um switch **"direcionar a pessoas específicas"** e, se marcar,
   escolhe os destinatários. Sem marcar, nasce `nivel = null`; **quem arbitra o nível é o diretor, ao publicar** —
   com os **6** níveis do enum disponíveis em `schemaCuradoria()` (§6.4).
3. **D3 — conflito de interesse é desejado:** os 2 diretores do DEPAE **também são médiuns** (Aury id=15, Charles
   id=24). Cada um **pode publicar a própria**, sem trava — a trilha registra quem publicou (I13).
4. **D4 — poder preso ao CARGO:** publica quem ocupa `Cargo::SLUG_DIRETOR_DEPAE`, mais admin e presidente.
   **Nada** pela matriz; `mensagem.*` continua inerte; **não** tocar `MatrizCapacidades`/`TiposConteudoSeeder`.
5. **D5 — destinatários = qualquer usuário ATIVO** (`users.ativo`), `searchable()` **sem** `preload`.
   *Esclarecimento (não relitígio):* `preload` é opção de `->relationship()`; o campo usa `->options()` — logo
   **não há preload** por construção, e o `searchable()` é client-side sobre o conjunto (molde `ids_palestrantes`).
   ⚠️ **Calibragem do consultor:** o filtro `ativo` é **higiene de UI**, não porta de segurança — um usuário
   inativo **não consegue logar** (`FortifyServiceProvider.php:34` nega no `authenticate`), então endereçar a ele
   é dado inútil, não brecha. A **reasserção server-side** do §6.5 continua obrigatória, mas pelas razões certas:
   **id inexistente, id de usuário apagado** e qualquer conjunto forjado ([[filament-hidratacao-nao-e-integridade]]).
6. **D6 — campos do médium:** título, formato, data de recebimento, contexto, corpo, autor(es) espiritual(is),
   pictografia, switch "direcionar" + destinatários. **FORA:** link do arquivo, liberar download, **nível**,
   **status**, slug (gerado), relacionadas. ⇒ vira **teste de ausência** (I22).
7. **D7 — o diretor publica e edita tudo na curadoria** (enquanto **pendente** — O7). Publicada, o médium **não
   edita mais**, sem volta. Ninguém exclui nem despublica pelo site.
8. **D8 — autoria é INTERNA:** curadoria e `/admin` sim; front **nunca** — nem página, nem meta/OG, nem JSON-LD,
   nem sitemap, em nível nenhum.
9. **D9 — histórico visível na curadoria:** data/hora, quem, o que aconteceu e **quais campos** mudaram — **sem**
   despejar o texto antigo. A fila mostra quem lançou, quando, e o aviso de edição pós-lançamento.
10. **D10 (novo — dono, no passe do consultor) — a aba do médium NÃO mostra o corpo de uma publicada.** O curador
    pode classificar como `diretores` uma mensagem lançada por um médium **trabalhador**; se a aba exibisse o
    corpo, ela seria a **primeira exceção à escada de visibilidade da 3A** (hoje só admin e presidente furam). ⇒ a
    lista do médium mostra **título, data, status e nível** — nunca o corpo de uma publicada — e **não linka** para
    `mensagens.show`. Enquanto **pendente**, o form completo continua disponível ao autor. Vira **I26**.
11. **D11 (novo — dono, no passe do consultor) — a trilha registra QUE o corpo mudou, sem guardar o texto.** Com
    retenção indefinida, logar `attributes`/`old` de `corpo` acumularia **cópia integral** de mensagens restritas e
    direcionadas para sempre. ⇒ **omitir o VALOR, preservar a CHAVE** (`'[texto não registrado]'`) para `corpo` e
    `contexto`; `titulo` **fica com valor** (é curto, o curador já o vê na fila, e sem ele o histórico perde
    utilidade). Implementação no `tapActivity` (§6.7). Vira **I27**.
12. **Heranças duras:** pt-BR em tudo; cabeçalho de autoria no PHP/Blade novo; `Pint` antes do push; **sem**
    `migrate:fresh/refresh/wipe/reset` nem seed destrutivo ([[nunca-migrate-fresh-no-dev]]); `legado` read-only;
    **todo brief de subagente que rode `artisan` repete essas proibições**.

---

## 3. Terreno confirmado por leitura (não presumir diferente)

Verificado em 2026-07-21 (base `8142883`), por leitura de fonte + vendor + **medição somente-leitura do dev**, e
**re-conferido** por uma lente dedicada de verificação de citações (§14.1). Referências relativas a partir de
`docs/superpowers/specs/` (`../../../`).

### 3.0 As TRÊS premissas do kickoff que CAÍRAM (leia antes de tudo)

| # | O kickoff diz | O código diz | Consequência |
|---|---|---|---|
| **C-A** | **T6/G4:** "`nivel = null` é fail-closed; a pendente não vaza" | **Fail-closed para todos MENOS admin e presidente.** O bypass roda **antes** do teste de null: [Mensagem.php:96-100](../../../app/Models/Mensagem.php#L96-L100) e [:123-125](../../../app/Models/Mensagem.php#L123-L125) (`return $query; // vê tudo, inclusive nível null`); `veTudo` = admin **ou** `ehPresidente()` ([:86-90](../../../app/Models/Mensagem.php#L86-L90)). Há **2 presidentes** no dev (Aury id=15, Elizabete id=40). | A pendente continua **não vazando** (o bypass é de **nível**, nunca de **status**, e lista/single filtram `publicado()` antes). Mas **as 2 publicadas com `nivel` null já estão no ar** para admin/presidente. Nos testes, a persona "diretor-DEPAE" **não pode ser presidente**. |
| **C-B** | **G12:** "re-import despeja ~179 entradas `atualizada`; envolva em `withoutLogs`" | **Não despeja.** O Eloquent curto-circuita antes do evento: `$saved = $this->isDirty() ? $this->performUpdate($query) : true;` (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1374`) + `if (count($dirty) > 0) { … fireModelEvent('updated') }` (`:1490-1498`). O **molde de asserção** é [AuditoriaAgendaDiaTest.php:30-38](../../../tests/Feature/Autorizacao/AuditoriaAgendaDiaTest.php#L30-L38) (`test_save_sem_mudanca_nao_gera_entrada`), que prova o curto-circuito num `save()` limpo — **ele não exercita o importador**; hoje **nenhum** teste trava o importador, e é isso que o **I17** passa a cobrir. | `withoutLogs` **sai** do escopo. Entra o teste-contrato I17, **com fixture que exercite os mutators** (§9.8). |
| **C-B2** | (memória do projeto) "o importador é **create-only** (`firstOrNew`)" | **Falso.** Create-only vale só para **slug/status/nivel** ([ImportadorMensagens.php:52-56](../../../app/Importacao/ImportadorMensagens.php#L52-L56)). Os **6 campos de conteúdo** são **SEMPRE reescritos**, com comentário literal no fonte ([:39-58](../../../app/Importacao/ImportadorMensagens.php#L39-L58)). | **Conflito de produto real:** o D7 manda o diretor **corrigir o texto** — e o próximo `cema:importar-mensagens` **desfaz a correção** nas 179 importadas → ponto aberto **O5**. |
| **C-C** | **G13/T10:** "não existe UI de trilha" | **Confirmado, e mais forte:** zero leitura de `activity_log` em `app/`, `resources/`, `routes/`; [DATA-MODEL.md:447](../../../DATA-MODEL.md#L447) declara "append-only (só escrita; não há viewer)"; e há **teste-contrato** proibindo Resource de Activity no painel ([AuditoriaInfraTest.php:38-43](../../../tests/Feature/Autorizacao/AuditoriaInfraTest.php#L38-L43)). **Mas** "nunca valores" é falso sobre o **dado**: o `properties` grava valores integrais (a linha id=16 do dev tem o HTML da reflexão, com data serializada em UTC). E entradas **manuais** usam `diff`, não `attributes/old`. | O histórico da F4b vive no **`/minha-conta`** ⇒ **não colide** com o teste-contrato. "Só nomes de campos" é regra de **renderização** (§6.8), e o leitor precisa dos **dois formatos**. |

### 3.1 Schema: a coluna não existe, e a FK curta aponta para o lugar errado

- **`mensagens` tem 15 colunas e NENHUMA de autoria** — na migration
  ([create_mensagens_table.php:13-31](../../../database/migrations/2026_07_18_000001_create_mensagens_table.php#L13-L31))
  **e no banco de dev**. Índices: PRIMARY, UNIQUE(`wp_id`), UNIQUE(`slug`), INDEX(`status`),
  INDEX(`data_recebimento`) — **sem** índice em `nivel`.
- **Não existe nenhum `Schema::table('mensagens', …)`**; as 4 migrations posteriores são pivôs.
- **⚠️ C-F — BUG LATENTE CRÍTICO:** `foreignId('medium_id')->constrained()` **sem** o nome da tabela aponta para
  **`media`**, não `users` — e o `migrate` **passa em silêncio**. O vendor infere por
  `beforeLast('_'.$column)->plural()`
  (`vendor/laravel/framework/src/Illuminate/Database/Schema/ForeignIdColumnDefinition.php:42`), e
  **`Str::plural('medium') === 'media'`** (executado no container; regra em
  `vendor/doctrine/inflector/src/Rules/English/Inflectible.php:143`). A tabela `media` **existe**
  ([create_media_table.php:11](../../../database/migrations/2026_06_26_204926_create_media_table.php#L11)) ⇒ a FK
  seria aceita apontando para a **biblioteca de mídia**. **Nenhum teste pegaria.** ⇒ regra dura:
  **`constrained('users')` sempre explícito** (a convenção real do projeto em 100% dos casos).
  ⚠️ **Por que a regra NÃO é decorativa na segunda FK** (nota do consultor, para o leitor futuro que vai querer
  "simplificar"): `publicado_por_id` inferiria **`publicado_pors`** — tabela inexistente ⇒ **erro explícito** no
  `migrate`. O **silêncio é privilégio do `medium_id`**, justamente porque `media` existe. As duas seguem a mesma
  regra: uma para não quebrar, a outra para **não quebrar em silêncio**.
- **Precedente único de FK-para-`users` fora de pivô:** [posts.criado_por_id](../../../database/migrations/2026_06_26_000003_create_posts_table.php#L20)
  — mas nasceu num `Schema::create`. **Não há molde de migration ADITIVA com FK**: seria a primeira do projeto
  (levantamento dos 13 `Schema::table` existentes: só `->change()`, coluna escalar ou `dropColumn`).
- **Convenção de nomes (corrigida):** o projeto **nunca** nomeia FK à mão; e nomeia o *unique composto* **quando o
  automático estouraria 64 chars** — 2 casos reais e documentados no fonte (`departamento_tipo_conteudo` = 66,
  `departamento_autor_espiritual` = 72). Há pivô com unique composto **sem** nome
  ([departamento_usuario:16](../../../database/migrations/2026_07_11_000001_create_departamento_usuario_table.php#L16)).
  Os nomes desta fatia (`mensagens_medium_id_foreign` = **27**; `mensagens_publicado_por_id_foreign` = **34**)
  cabem ⇒ **não** nomear.
- **`users.ativo` existe**: boolean NOT NULL default true ([migration:14](../../../database/migrations/2026_07_03_000006_add_campos_cema_to_users_table.php#L14), cast em [User.php:89](../../../app/Models/User.php#L89)).
- **Testes em SQLite `:memory:`** ([phpunit.xml:26-27](../../../phpunit.xml#L26-L27)) com FK **ligadas**. ⚠️ (a) o
  SQLite **reconstrói a tabela** ao adicionar FK e **ignora `->after()`** ⇒ nunca assertar ordem de coluna; (b) o
  `down()` **não pode** usar `dropForeign('nome_string')` (RuntimeException em
  `vendor/laravel/framework/src/Illuminate/Database/Schema/Grammars/SQLiteGrammar.php:607`) — use
  `dropConstrainedForeignId('col')`.

### 3.2 O model e o resolvedor (3A) — o que a F4b consome sem tocar

- [Mensagem.php:22-27](../../../app/Models/Mensagem.php#L22-L27): `HasMedia, TemDepartamento`; traits
  `HasFactory, InteractsWithMedia, RegistraImagensPadrao` — **sem `LogsActivity`**.
- Constantes `STATUS_PUBLICADO`/`STATUS_PENDENTE`/`STATUS_DESPUBLICADA`/`COLECAO_PICTOGRAFIA`
  ([:29-38](../../../app/Models/Mensagem.php#L29-L38)); `$fillable` com 12 chaves, **sem**
  `destinatarios`/`relacionadas` ([:40-53](../../../app/Models/Mensagem.php#L40-L53)).
- **`nivel` não é castado** — a tipagem é o **método** [`visibilidade(): ?VisibilidadeMensagem`](../../../app/Models/Mensagem.php#L77-L84)
  (`tryFrom` ⇒ null para null **e** slug desconhecido). ⚠️ `$m->visibilidade` **sem parênteses** lança
  `LogicException` em runtime.
- **Enum de 6 casos** ([VisibilidadeMensagem.php:7-14](../../../app/Enums/VisibilidadeMensagem.php#L7-L14)) com
  `rotulo()/cor()/…/nivelMinimo()` e **`opcoes()`** ([:91-99](../../../app/Enums/VisibilidadeMensagem.php#L91-L99)).
  ⚠️ **slug do CARGO = `diretor-do-depae`**; **valor do NÍVEL = `diretor-depae`** — sempre pelas constantes.
- **Scopes:** `publica()` (fixo, 2B) · **`publicado()` status-only** (3B) · `visiveisPara()` (escada + 3 recortes +
  bypass). ⚠️ `visiveisPara` **não** filtra status: o par obrigatório é `publicado()->visiveisPara($u)`.
- **Mídia:** coleção **múltipla** `pictografia`, disco `public`, conversões `web`/`thumb` pelo trait
  [RegistraImagensPadrao](../../../app/Models/Concerns/RegistraImagensPadrao.php#L32-L56); no form, a fábrica
  [ComponentesImagem::upload(..., multiplas: true)](../../../app/Filament/Support/ComponentesImagem.php#L22-L42).
- **Factory:** default `status=publicado`, **`nivel=null`**; states `publicada()/pendente()/publica()/comNivel()`.
  ⚠️ `formato` é **sorteado** — todo teste que assere corpo deve fixá-lo.

### 3.3 A superfície `/minha-conta` — o molde exato (a F4b replica 2×)

- **Duas abas, dois critérios:** [AbaAgenda](../../../app/Support/Conta/AbaAgenda.php#L38-L45) = **capacidade**;
  [AbaDirecionadas](../../../app/Support/Conta/AbaDirecionadas.php#L21-L33) = **pertencimento puro**. Ambas
  memoizadas por **`WeakMap` estático**. ⇒ a F4b copia o molde **AbaDirecionadas**. Prova viva: o
  [AbaDirecionadasTest](../../../tests/Feature/Conta/AbaDirecionadasTest.php#L14-L19) **não semeia nada** e passa.
- **Não existe registro de abas**: array literal com **dois `if` hard-coded** por FQCN
  ([nav.blade.php:2-14](../../../resources/views/components/conta/nav.blade.php#L2-L14)) ⇒ **4 edições** por aba
  nova (classe + `if` + rota + método no controller).
- **Rotas:** grupo `middleware('auth')->prefix('minha-conta')->name('conta.')`, **só GET**
  ([routes/web.php:41-47](../../../routes/web.php#L41-L47)) — anônimo é **redirecionado ao login**.
- **Componente ([AgendaConta](../../../app/Livewire/Conta/AgendaConta.php)):** `implements HasForms` +
  `AuthorizesRequests, InteractsWithForms`; `public ?array $data`; **`mount()` = 2º portão**
  ([:41-44](../../../app/Livewire/Conta/AgendaConta.php#L41-L44)); **`boot()` marca a porta `'perfil'`**
  ([:46-51](../../../app/Livewire/Conta/AgendaConta.php#L46-L51)); `form()` liga
  `components(...)->model(...)->statePath('data')->operation(...)`; `salvar()` faz `authorize → getState() → belts
  → persistir → session()->flash + redirect(navigate: true)`.
  ⚠️ **Nada de `Notification::make()`**: o componente de notificações do Filament **não está montado** no site.
- **Views:** a da agenda tem `headTop`+`scripts` e **não** tem `noindex`; a das direcionadas tem `head`(noindex) e
  **não** carrega tema/scripts. ⚠️ **Nenhuma página combina os TRÊS slots** — a F4b é a primeira. Os três **são**
  repassados por [x-layout.conta:6-8](../../../resources/views/components/layout/conta.blade.php#L6-L8).
- **Reasserção server-side** é o idioma da casa ([statusValido()](../../../app/Livewire/Conta/AgendaConta.php#L205-L211)),
  com **teste de forja** por `->set('data.<campo>', …)` ([AgendaContaCriarTest.php:83-99](../../../tests/Feature/Conta/AgendaContaCriarTest.php#L83-L99)).

### 3.4 Autorização: por que os métodos são NOVOS (e por que a matriz fica fora)

- **`MensagemPolicy` tem 6 métodos em 2 eixos** ([:19-57](../../../app/Policies/MensagemPolicy.php#L19-L57)):
  `view/viewAny` (**visibilidade**) e `ver/criar/editar/excluir` (**capacidade**). **Nenhum método de ação
  específica** em policy nenhuma do projeto.
- **Reaproveitar `editar` daria 403** a todo não-admin: `mensagem.*` está **inerte** (medido:
  `role_has_permissions` = **8 linhas, todas `agenda.*`**) e o escopo exige o **departamento DEPAE** (regime
  `DoTipo`), que tem **3 vinculados**, não os 46 médiuns.
- ⚠️ **Armadilha `view` × `ver`**: `authorize('view', $m)` passa para **qualquer** leitor autorizado pelo nível.
  `viewAny` retorna `true` incondicionalmente.
- ⚠️ **`Gate::before` do admin** ([AppServiceProvider.php:72-74](../../../app/Providers/AppServiceProvider.php#L72-L74))
  passa em **qualquer** ability ⇒ **todo teste do eixo de autoria usa não-admin**.
- **O trait fica em `app/Policies/Concerns/AutorizaPorDepartamento.php`** (não em `app/Models/Concerns`).
- **Helpers** ([User.php:99-120](../../../app/Models/User.php#L99-L120)): `ehMedium()`, `ehDiretorDepae()`,
  `ehPresidente()` — query por relação contra **constante**; **não memoizados**; **não** filtram `ativo`/`desde`
  (comportamento existente, **não** mexer).
- **Policies por auto-discovery** (não há `AuthServiceProvider`).

### 3.5 O form do `/admin` (a extrair) e o que a extração NÃO pode carregar em silêncio

- **Form inline no Resource**, 5 Sections ([MensagemResource.php:66-188](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L66-L188)).
  Só existem **2** Schemas extraídos no projeto, e **só 1** com consumidor fora do painel — que **não** exercita
  relationship nem mídia.
- **Os 3 pivôs têm 3 mecânicas diferentes:** `autores` = **auto-sync** (`->relationship()`, e **por isso** é
  `dehydrated(false)`); `relacionadas` = sync manual **simétrico**; `destinatarios` = sync manual **com guard de
  nível** (F4a). **Uniformizar quebra.**
- **⚠️ C-H — `MensagemResource::NIVEIS` tem 5 entradas; o enum tem 6** (falta `diretor-depae`), usada no Select
  `:122` **e** na coluna `:210-213` ⇒ hoje é impossível classificar como "Diretor do DEPAE" **pela UI do painel**.
  Nenhum teste detecta (o existente só exige a chave `publico`). ⚠️ **Isto NÃO bloqueia o D2**: o curador da F4b
  consome `schemaCuradoria()`, método **novo**, que já nasce com `VisibilidadeMensagem::opcoes()` (§6.4). Corrigir
  o **painel** é melhoria opcional → **O2** (rebaixado).
- **Texto de UI mentiroso** (candidato ao mesmo O2): o `helperText` do nível ainda diz *"Só as Públicas aparecem no
  site (por ora)…"* ([:124](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L124)) — a 3B já está no ar.
- **Pegadinhas da extração**: `->unique(ignoreRecord: true)` sem tabela só é aplicada se o schema tiver `model`;
  o **auto-slug** depende de `$operation === 'create'`, que fora de página Filament nunca casa; o Select
  `relacionadas` injeta `?Mensagem $record` (null fora do painel ⇒ oferece auto-relação).
- **Testes que travam o form: 16 da F4a (em 4 arquivos: Form 4 · Guard 3 · Persistência 7 · Tabela 2) + 13 da 2A**
  (`MensagemResourceTest`) = **29**. **Nenhum** referencia `MensagemResource::form()` nem `NIVEIS` ⇒ **uma
  extração literal não quebra nada**. ⚠️ O `MensagemDestinatariosGuardTest` consome a **propriedade**
  `protected array $idsDestinatarios` por classe anônima ([:19-31](../../../tests/Feature/Filament/MensagemDestinatariosGuardTest.php#L19-L31))
  — o adaptador **tem** de preservá-la (§6.5).

### 3.6 Front público e 3C: o que a F4b não pode quebrar (e o que não pode reusar)

- Lista e single filtram **`publicado()`** antes de tudo ⇒ **pendente nunca vaza**, nem para admin/presidente.
- **⚠️ `x-mensagem.card` e `x-mensagem.linha` linkam incondicionalmente para `mensagens.show`**
  ([card.blade.php:17](../../../resources/views/components/mensagem/card.blade.php#L17)) — **404 em pendente** ⇒ as
  telas da F4b **não podem reusá-los**.
- **`x-mensagem.selo-nivel`** é reutilizável e tem o **NULL-GUARD** ([:9](../../../resources/views/components/mensagem/selo-nivel.blade.php#L9)).
- **Nenhuma superfície pública serializa o model** (11 allowlists; o sitemap nem hidrata as colunas). **Mas** o
  model não tem `$hidden` e o molde que a F4b clona (`fill($registro->attributesToArray())` com `public ?array
  $data`) **põe a linha inteira no `wire:snapshot`** ⇒ `$hidden` é obrigatório (§6.10).

### 3.7 Auditoria: o helper, o molde e o que ainda não existe

- **Porta** = `static ?string $portaForcada ?? Filament::getCurrentPanel()?->getId() ?? 'sistema'`
  ([AuditoriaAutorizacao.php:16-31](../../../app/Support/Autorizacao/AuditoriaAutorizacao.php#L16-L31)) ⇒ no
  `/minha-conta` cai em `'sistema'` sem o override.
- **Por que `boot()` e não `mount()`** — o hook `boot` roda no **mount E na hidratação**
  (`vendor/livewire/livewire/src/Features/SupportLifecycleHooks/SupportLifecycleHooks.php:30-31` e `:46-47`); num
  `wire:click` (o save!) o `mount` **não roda**. ⚠️ **O teste de porta existente NÃO prova isso**
  ([AuditoriaAgendaPortaTest.php:63-74](../../../tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php#L63-L74)) —
  a F4b precisa do arranjo discriminante (§9.6).
- **Molde de trilha:** [AgendaDia.php:159-178](../../../app/Models/AgendaDia.php#L159-L178).
- **Contrato real de `properties`**: `created` → `{attributes, porta, ip, user_agent}` (**sem `old`**); `updated` +
  `logOnlyDirty` → `{attributes, old, …}` com as **mesmas chaves**; `deleted` → só `old`; **manual** →
  `{diff:{adicionados,removidos}, …}` com `event = NULL`. **`porta`/`ip`/`user_agent` são irmãos de `attributes`**.
  A API que poda é `Activity::changes()`.
- ⚠️ Datas viram **Carbon em UTC** no properties ⇒ `useAttributeRawValues(['data_recebimento'])` (existe:
  `vendor/spatie/laravel-activitylog/src/LogOptions.php:161`, lido em `Traits/LogsActivity.php:359-361`).
  Entradas de console têm `ip='127.0.0.1'`/`user_agent='Symfony'` — o discriminante é `porta='sistema'` + `causer` NULL.
- **Débito pré-existente (§12):** [EditarPerfil](../../../app/Livewire/Conta/EditarPerfil.php) não marca a porta e
  escreve em `User` (auditado) ⇒ grava `porta='sistema'`.

### 3.8 Medição do banco de DEV (somente-leitura, 21/jul) — reconferida por 2 agentes

| Medição | Valor | Nota |
|---|---|---|
| Mensagens | **179** = 132 publicadas + 47 pendentes | zero "despublicada" |
| `nivel` NULL **puro** | **49** (47 pendentes + **2 publicadas**: id=168 e id=179) | zero string vazia |
| Publicadas por nível | trabalhadores 44 · mediuns-trabalhadores 33 · publico 29 · direcionada 15 · diretores 9 · NULL 2 | **`diretor-depae` = 0** |
| Médiuns (setor `medium`) | **46**, todos ativos (29 trabalhador + 17 diretor) | ✅ bate |
| Cargo `diretor-do-depae` | **2**: Aury (15) e Charles (24) — **ambos médiuns** | ✅ bate (D3) |
| Cargo `presidente` | **2**: Aury (15) **e Elizabete (40)** | ⚠️ correção (o kickoff fala em 1) |
| Vínculo ao **departamento** DEPAE | **3**: Aury, Charles **e Elizabete** | cargo ≠ departamento |
| Usuários | **148**, todos `ativo=1` | ✅ bate |
| `activity_log` | **13** linhas (autorizacao 9 · agenda 2 · usuario 2) | **zero** de Mensagem |
| `mensagem_destinatario` | 73 linhas · 15 mensagens · 17 usuários | (3A) |
| `departamento_mensagem` | **0 linhas** | pivô congelado por desenho |
| **Pendentes sem autor espiritual** | **32 de 47 (68%)** | ⚠️ se publicar exigir autor, 32 travam → **O3** |
| `role_has_permissions` | 8 linhas, **todas `agenda.*`** | `mensagem.*` inerte |

---

## 4. Sem handoff de design (é UI funcional, molde da Fatia D)

`grep` por "curadoria|pendente|publicar|médium" em `design_handoff_mensagens_lista/`,
`design_handoff_mensagem_single/` e `handoff_minha_conta/` retorna **zero**. O handoff da conta cobre só a casca +
Painel + Meu Perfil ("novos módulos entram quando forem construídos").
⇒ O visual sai do que existe: `x-layout.conta` + **form Filament embutido** (molde `conta/agenda`) + cards Blade
simples (molde `livewire/conta/agenda-conta.blade.php`) + `x-mensagem.selo-nivel`. **Mobile-first** e **A11y**
herdados.

---

## 5. Invariantes (cada um vira teste que reprova)

| # | Invariante | Teste (§9) |
|---|---|---|
| **I1** | **Portão do médium:** não-médium → **403** em `conta.mensagens` (rota **e** `mount`); médium → 200 e a aba aparece na nav. | §9.2 |
| **I2** | **Portão do curador:** médium comum → **403**; diretor-DEPAE **e** presidente → 200; **admin puro → 403** (ele usa o `/admin` — decisão §6.3); anônimo → **redirect** ao login nas duas. | §9.2 |
| **I3** | **Nascimento:** médium cria → `status='pendente'`, `medium_id` = ele, `nivel=null`, `publicado_por_id`/`publicado_em` nulos, `slug` gerado no servidor e **único** (dois títulos iguais ⇒ dois slugs). | §9.3 |
| **I4** | **Switch direcionar:** marcado ⇒ `nivel='direcionada'` **e** pivô com os ids (≥1 obrigatório — `assertHasFormErrors(['destinatarios'])` sem nenhum); não marcado ⇒ `nivel=null` **e** pivô vazio. | §9.3 |
| **I5a** | **G1 — o pivô de autores grava no create do site:** criar com 1 autor ⇒ 1 linha em `mensagem_autor_espiritual`. **Sem** `->model($m)->saveRelationships()` fica **vermelho** (o Select `autores` é `->relationship()` ⇒ `dehydrated(false)` ⇒ só grava ali). | §9.3 |
| **I5b** | **A mídia sobrevive à edição:** pendente com 1 imagem anexada; editar **só o título** ⇒ `getMedia('pictografia')->count()` continua 1 (pega o wipe silencioso do `saveRelationships` na edição). *(A anexação via form na **criação** não é testável — §9.3/limitação.)* | §9.4 |
| **I6** | **Posse do médium (e o M2):** edita a própria pendente **`direcionada`** preenchendo **só o título** ⇒ **preserva** destinatários, autores, mídia **e o nível** — é o teste que prova a **hidratação do toggle `direcionar`** (§6.5); a própria **publicada** ⇒ 403; a **pendente de outro** ⇒ 403. | §9.4 |
| **I7** | **POST não é confiável (alvos que mordem):** `direcionar=false` + `destinatarios=[ids]` forjados ⇒ `nivel` null e **0** linhas de pivô; **id inexistente / de usuário apagado** injetado ⇒ **não** entra no pivô (reasserção server-side; as options são UI); forja de `status`/`nivel` permanece inerte (trava de regressão). | §9.4 |
| **I8** | **O martelo:** curador publica ⇒ `status='publicado'`, `publicado_por_id` = ele, `publicado_em` preenchido; `medium_id` **inalterado**. | §9.5 |
| **I9** | **G4 — publicar exige nível válido:** `nivel` null/`''`/slug inválido ⇒ **recusado no servidor**, registro **continua pendente e íntegro** (rollback). Cobre uma pendente **legada**. | §9.5 |
| **I10** | **G5 — trocar de direcionada esvazia o pivô** (UI **e** unidade do guard: `filtrarPorNivel('publico', [7,9]) === []`). | §9.5 |
| **I11** | **Salvar não publica:** na curadoria, salvar ⇒ segue `pendente`, `publicado_por_id` null — **inclusive** se `status` for forjado no estado. Publicar uma **já publicada** ⇒ 403. | §9.5 |
| **I12** | **Curador edita o que é dele editar:** título/corpo/nível/destinatários alterados persistem **sem** publicar; e **não existe** caminho de despublicar/excluir pelo site. | §9.5 |
| **I13** | **D3:** usuário **médium E diretor-DEPAE** (não-admin, **não-presidente**) publica a **própria**: permitido, e a trilha registra `causer` = ele. | §9.5 |
| **I14** | **G6 — porta `'perfil'`** em toda entrada dos 2 componentes, **inclusive no save** (arranjo que reprova `mount()`). | §9.6 |
| **I15** | **G13 — histórico (renderizador ISOLADO):** produz "criada/atualizada/publicada", quem (ou "Sistema") e a **lista de rótulos pt-BR**; **não** produz corpo antigo, nome de destinatário, nem chave crua (`user_agent`, `"porta"`, `attributes`). | §9.7 |
| **I16** | **"Editada pelo autor após o lançamento":** marca só quando existe `updated` cujo `causer` é o **próprio médium** da mensagem; **não** marca item recém-criado, **nem** quando quem salvou foi o **curador**, **nem** as legadas (`medium_id` null). | §9.7 |
| **I17** | **G12 — importador não infla a trilha:** importar o **mesmo lote** duas vezes ⇒ 0 entradas novas de `log_name='mensagem'`, **com fixture que exercite `clean()`, `LinkDrive` e a data** (§9.8). | §9.8 |
| **I18** | **D8 — autoria nunca no front:** nome do médium/publicador e as chaves `medium_id`/`publicado_por_id`/`publicado_em` ausentes em single (anônimo e logado), lista, sitemap, perfil de autor e `/minha-conta/direcionadas` **(trava de regressão)** — **e**, o que morde (**M1**): ausentes no `wire:snapshot` **no estado HIDRATADO** das telas novas (`->call('editar', $id)`), que é o **único** ponto por onde a autoria entraria, via `fill($registro->attributesToArray())`. | §9.9 |
| **I19** | **3B/3C intactas:** pendente fora da lista pública, 404 no single, fora do sitemap e de "Minhas Direcionadas"; publicada pela curadoria com nível `publico` **aparece** (ponte F4b→3A→3B). | §9.9 |
| **I20** | **Extração literal é neutra:** após o **passo (i)** do §6.4 (extração campo a campo, **sem uma vírgula de diferença**), os **29** testes (16 da F4a em 4 arquivos + 13 da 2A) seguem verdes **sem uma linha alterada** — inclusive o `GuardTest`, que consome a propriedade `protected $idsDestinatarios`. | §9.1 |
| **I21** | **FK correta (C-F):** `mensagens.medium_id` referencia **`users`** — não `media`. | §9.1 |
| **I22** | **D6 — o médium não vê o que não é dele:** `schemaMedium` **não tem** `nivel`, `status`, `slug`, `link_arquivo`, `liberar_download`, `relacionadas`; **tem** `titulo`, `formato`, `data_recebimento`, `contexto`, `corpo`, `autores`, `pictografia`, `direcionar`, `destinatarios`. | §9.3 |
| **I23** | **D2 — o curador arbitra entre os 6 níveis:** o Select de nível de `schemaCuradoria()` oferece as **6** chaves do enum, incluindo `diretor-depae`. | §9.5 |
| **I24** | **PII na trilha:** nenhuma entrada de `log_name='mensagem'` contém `destinatario`, `medium_id` ou `publicado_por_id` **nem o NOME de um destinatário-sentinela** no `properties` (**R4**: a chave é o vetor previsto; o nome é o dano real). | §9.7 |
| **I25** | **Telas privadas e legíveis:** as duas emitem `noindex, nofollow`; a fila renderiza **200** com pendentes de `nivel=null` e `medium_id=null`, mostrando **"Importada do legado"**; com médium, mostra o nome de quem lançou. | §9.2/§9.5 |
| **I26** | **D10 — a aba do médium não fura a escada da 3A:** autor de uma **publicada** com `nivel='diretores'` (sendo ele **trabalhador**) **não vê o corpo** na aba nem por GET direto, e a aba **não linka** para `mensagens.show`. Enquanto **pendente**, o autor vê e edita normalmente. | §9.4 |
| **I27** | **D11 — a trilha diz QUE mudou, não O QUE dizia:** editar o corpo pelo site ⇒ existe entrada com a **chave** `corpo` em `attributes`, **e** nenhuma entrada de `log_name='mensagem'` contém um trecho-sentinela do texto (idem `contexto`); `titulo` **continua** com valor. | §9.7 |
| **I-reg** | **Sem regressão:** suíte **1097 + novos** verde; `Pint` verde; `migrate` **incremental** roda no MySQL do dev; nenhum teste existente alterado. | §9.10 |

---

## 6. Decisões de desenho

### 6.1 Migration (aditiva, incremental, a primeira com FK do projeto)

```php
// database/migrations/2026_07_21_000001_add_autoria_to_mensagens_table.php
public function up(): void
{
    Schema::table('mensagens', function (Blueprint $table) {
        // constrained('users') EXPLÍCITO: sem o nome da tabela, Str::plural('medium') === 'media'
        // e a FK apontaria em SILÊNCIO para a biblioteca de mídia (§3.1/C-F).
        $table->foreignId('medium_id')->nullable()->after('casa')
            ->constrained('users')->nullOnDelete();
        $table->foreignId('publicado_por_id')->nullable()->after('medium_id')
            ->constrained('users')->nullOnDelete();
        $table->timestamp('publicado_em')->nullable()->after('publicado_por_id');
    });
}

public function down(): void
{
    Schema::table('mensagens', function (Blueprint $table) {
        // dropConstrainedForeignId: dropForeign('nome_string') lança RuntimeException no SQLite dos testes.
        $table->dropConstrainedForeignId('medium_id');
        $table->dropConstrainedForeignId('publicado_por_id');
        $table->dropColumn('publicado_em');
    });
}
```

- **Sem nome explícito de constraint** (27 e 34 chars; a convenção é nomear só quando o automático estouraria 64).
- **Sem índice extra:** no MySQL/InnoDB o `ADD FOREIGN KEY` cria o índice; `nivel` segue sem índice (179 linhas).
- **Sem backfill, por decisão registrada:** as 179 do legado foram cadastradas no WP por **4 contas do DECOM**;
  **não há fonte** de onde derivar autoria — atribuí-la seria **dado falso**. ⇒ **`medium_id = null` significa
  "importada do legado"**, e é assim que a fila rotula (§6.9/I25).
- ⚠️ **SQLite reconstrói a tabela** e **ignora `->after()`** ⇒ nenhum teste assere ordem de coluna; e **testes
  verdes não provam o `migrate` no MySQL** (§9.10 item 1).

### 6.2 Policy — 5 métodos NOVOS (eixo de AUTORIA, pt-BR)

Os 4 existentes são o eixo de **capacidade**, inerte: **não** reaproveitar, **não** ativar. Os novos vivem na mesma
`MensagemPolicy`, com `User` **não-nulável** e **sem** consultar permission:

```php
/** Eixo AUTORIA (F4b) — pertencimento por setor/cargo, NUNCA capacidade/matriz. Admin passa antes no Gate::before. */
public function lancar(User $user): bool
{
    return $user->ehMedium();
}

public function editarPendente(User $user, Mensagem $mensagem): bool
{
    return $user->ehMedium()
        && $mensagem->medium_id === $user->id
        && $mensagem->status === Mensagem::STATUS_PENDENTE;
}

/** Portão da ABA/rota da curadoria (sem objeto). */
public function curar(User $user): bool
{
    return $user->ehDiretorDepae() || $user->ehPresidente();
}

/** Portão de CADA registro na curadoria: só pendente (O7 — publicada é /admin). */
public function editarNaCuradoria(User $user, Mensagem $mensagem): bool
{
    return $this->curar($user) && $mensagem->status === Mensagem::STATUS_PENDENTE;
}

public function publicar(User $user, Mensagem $mensagem): bool
{
    return $this->editarNaCuradoria($user, $mensagem);
}
```

- **`editarNaCuradoria` é o método que fechou o furo do passe (§14.2/B4):** sem ele, `curar()` (que não recebe
  objeto) autorizaria o botão **Salvar** sobre uma das 132 **publicadas**, já que o componente recebe o id do
  cliente (`editar(int $id)`, molde [AgendaConta.php:95-103](../../../app/Livewire/Conta/AgendaConta.php#L95-L103)).
- **`lancar`/`curar` sem objeto** ⇒ `$this->authorize('lancar', Mensagem::class)`.
- **D3 não tem trava**: `publicar` **não** consulta `medium_id` (I13).
- ⚠️ **Todo teste destes métodos usa não-admin**; e a persona "diretor-DEPAE" **não pode ser presidente**.

### 6.3 As duas abas (molde `AbaDirecionadas`: pertencimento + `WeakMap`)

```php
// app/Support/Conta/AbaMensagens.php
return self::$cache[$user] ??= $user->ehMedium();

// app/Support/Conta/AbaCuradoria.php
return self::$cache[$user] ??= $user->ehDiretorDepae() || $user->ehPresidente();
```

- **Não** consultam permission (D4) — como a 3C; por isso os testes passam **sem semear capacidade**.
- **Decisão explícita sobre o ADMIN puro (achado do passe):** os portões de **rota** usam `Aba*::visivelPara`, que
  **não** passa pelo `Gate::before` ⇒ um admin que não seja médium/diretor-DEPAE/presidente recebe **403** nas duas
  abas. **É intencional:** o admin edita pelo `/admin`, e a área do membro não é atalho de painel. Vira **teste**
  (I2), para não parecer bug.
- **Para Aury e Charles as DUAS abas aparecem** — correto (D3).
- **Registro (4 edições por aba):** classe + `if` no nav (`['chave'=>'mensagens','rotulo'=>'Minhas Mensagens',
  'rota'=>'conta.mensagens']` e `['chave'=>'curadoria','rotulo'=>'Curadoria','rota'=>'conta.curadoria']`) + rota GET
  + método no `ContaController` (portão puro + `view`).

### 6.4 Fonte única do form — `App\Filament\Schemas\MensagemForm`, extraído em DOIS passos

**Passo (i) — extração LITERAL (comportamento-neutro, I20):** mover
[MensagemResource::form()](../../../app/Filament/Resources/Mensagens/MensagemResource.php#L66-L188) para
`MensagemForm::schemaAdmin()` **campo a campo, sem uma vírgula de diferença**, e o Resource passa a consumi-lo.
Commit próprio; critério de aceitação = os **29** testes verdes sem alteração.

**Passo (ii) — as composições novas** (cada uma com teste próprio):

| Método | Conteúdo | Consumidor |
|---|---|---|
| `schemaAdmin()` | idêntico ao de hoje | `MensagemResource::form()` |
| `schemaMedium()` | miolo **sem slug** (título, contexto, corpo) · formato · data · autores · pictografia · **`Toggle::make('direcionar')->live()`** + bloco de destinatários | `MensagensConta` |
| `schemaCuradoria()` | `schemaAdmin()` **sem slug**, **sem o Select `status`** e **sem `relacionadas`** (ver abaixo) · Select de nível com **`VisibilidadeMensagem::opcoes()`** (I23) | `CuradoriaConta` |

**Passo (ii-b) — o `/admin` (item próprio, O2 + M3):** o D8 promete autoria "na curadoria **e** no `/admin`", e
quem resolve problema de verdade é o admin. Na `table()` do `MensagemResource`, **por RELAÇÃO** (a coluna está em
`$hidden`, §6.10 — um campo de form ligado à coluna nasceria vazio):

```php
TextColumn::make('medium.name')->label('Lançada por')
    ->placeholder('Importada do legado')   // medium_id null = legado (as 179)
    ->toggleable()->searchable(),
```

…mais o que o O2 já previa: `->options(VisibilidadeMensagem::opcoes())` no Select de nível,
`VisibilidadeMensagem::tryFrom((string) $state)?->rotulo() ?? '— (sem nível)'` na coluna, e o `helperText`
mentiroso corrigido. **N+1:** `->with('medium:id,name')` no `modifyQueryUsing` (R2 — selecionar colunas evita
arrastar `email`/`google_id` do `User` para qualquer serialização futura).

**O bloco de destinatários é PARAMETRIZADO, não copiado** (correção do bloqueador B1 — o form do médium **não tem
`nivel`**, então o predicado `$get('nivel')` do `/admin` deixaria a Section eternamente oculta e o `required` nunca
dispararia):

```php
/** @param Closure(Get): bool $ehDirecionada — o predicado muda por consumidor; o resto é idêntico. */
private static function blocoDestinatarios(Closure $ehDirecionada): Section
{
    return Section::make('Destinatários')
        ->description('Usuários a quem esta mensagem direcionada foi endereçada.')
        ->visible($ehDirecionada)
        ->schema([
            Select::make('destinatarios')
                ->label('Destinatários')
                ->options(fn () => User::where('ativo', true)->orderBy('name')->pluck('name', 'id'))
                ->multiple()->searchable()
                ->required($ehDirecionada)->minItems(1)->columnSpanFull(),
        ]);
}
// schemaAdmin/schemaCuradoria: fn (Get $g) => $g('nivel') === VisibilidadeMensagem::Direcionada->value
// schemaMedium:                fn (Get $g) => (bool) $g('direcionar')
```

**Por que `status` e `relacionadas` saem do `schemaCuradoria` (bloqueadores B5/B7 do passe):**

- **`status`**: o Select do painel tem 3 opções (**incluindo "Despublicada"**) e `default('publicado')`. Mantê-lo
  daria ao curador um caminho de publicar/despublicar **por fora** da `RegraPublicacao` — furando I8, I9 e a
  máquina de estados. Na curadoria o estado é decidido pelos **botões** (Salvar/Publicar), reasseridos no servidor.
- **`relacionadas`**: a persistência **não vive no schema**, vive no trait das Pages do painel. Herdá-lo entregaria
  um campo que o curador preenche e que **não grava nada, em silêncio** — exatamente a classe de falha que o G1
  eleva a invariante. ⇒ **fora do escopo da F4b** (§10); relacionar segue sendo do `/admin`.

**Regras da extração** (cada uma fecha uma pegadinha do vendor):

1. `->unique(table: 'mensagens', column: 'slug', ignoreRecord: true)` — higiene (o único consumidor com campo
   `slug` é o painel, que tem `model`; fora dele o slug é server-side).
2. **O auto-slug fica só no `schemaAdmin`**; no site o slug é **gerado no servidor** (§6.5).
3. **Preservar** o par `->live()` no nível + `->visible()/->required()` **no `schemaAdmin`** (é o que os testes
   `MensagemDestinatariosFormTest` travam).
4. O filtro `->where('ativo', true)` nas options é **UI**; a integridade é a reasserção do §6.5 (I7).

### 6.5 O caminho de escrita fora do painel (G1 + G3 + campos privilegiados)

**Sequência OBRIGATÓRIA** — provada em 3 lugares do vendor
(`vendor/filament/filament/src/Resources/Pages/CreateRecord.php:105/113/115`,
`vendor/filament/actions/src/CreateAction.php:108-109` e o **gerador oficial**
`vendor/filament/forms/src/Commands/FileGenerators/LivewireFormComponentClassGenerator.php:205-211`). Causa raiz:
com `->model(Mensagem::class)` (class-string) o `getRecord()` devolve **null**
(`vendor/filament/schemas/src/Components/Concerns/BelongsToModel.php:222-224`) e todo `saveRelationships()` vira
**no-op silencioso** (`:59-61`); a mídia nem aparece em `$data`
(`vendor/filament/spatie-laravel-media-library-plugin/src/Forms/Components/SpatieMediaLibraryFileUpload.php:79-81`).

```php
// MensagensConta::salvar() — CRIAÇÃO pelo médium
public function salvar(): void
{
    $this->authorize('lancar', Mensagem::class);

    try {
        DB::transaction(function (): void {
            // getState() DENTRO da transação: na EDIÇÃO ele já persiste mídia/autores internamente
            // (vendor/filament/schemas/src/Concerns/HasState.php:483/497) — só assim uma recusa
            // posterior (RegraPublicacao) faz rollback de TUDO.
            $dados = $this->form->getState();

            $ehDirecionada = (bool) ($dados['direcionar'] ?? false);
            $idsDestinatarios = $dados['destinatarios'] ?? [];   // Select SEM ->relationship() É desidratado

            // Belt: getState() já poda chaves fora do schema; o unset é defesa em profundidade.
            unset($dados['direcionar'], $dados['destinatarios'], $dados['status'], $dados['nivel'],
                  $dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em']);

            $dados['status'] = Mensagem::STATUS_PENDENTE;                                   // sempre (D2)
            $dados['nivel']  = $ehDirecionada ? VisibilidadeMensagem::Direcionada->value : null;
            $dados['slug']   = SlugMensagem::unico($dados['titulo']);

            // UM único write: `new` respeita o $fillable; a autoria é atribuição direta (não é fillable)
            // ⇒ 1 INSERT, 1 evento `created`, sem o `updated` espúrio de um segundo save().
            $mensagem = new Mensagem($dados);
            $mensagem->medium_id = auth()->id();
            $mensagem->save();

            // A LINHA (G1): sem ela, pictografia e autores somem SEM erro, SEM log.
            $this->form->model($mensagem)->saveRelationships();

            // Pivô: sync manual com guard de nível (G3) + filtro server-side de destinatário (I7).
            SincronizadorDestinatarios::aplicar($mensagem, $mensagem->nivel, $idsDestinatarios);
        });
    } catch (QueryException $e) {
        // Corrida de slug (unique): regera com novo sufixo e tenta uma vez. O try/catch envolve a
        // transação (um `return` dentro dela COMMITARIA o parcial).
        if ($e->getCode() === '23000') { /* 1 retry; se falhar, addError('titulo', …) */ }
        throw $e;
    }

    session()->flash('status', 'Mensagem enviada para curadoria.');
    $this->redirect(route('conta.mensagens'), navigate: true);
}
```

**`CuradoriaConta`** repete a forma, com três diferenças:

- **`editar(int $id)` e `salvar()` autorizam `editarNaCuradoria`** sobre o registro (`findOrFail` **antes** do
  `authorize`) — é o que barra a edição de publicada (I11/I12).
- **`salvar()` reassere `status = STATUS_PENDENTE`** (o Select saiu do schema, mas o servidor não confia nem no que
  não existe).
- **`publicar(int $id)`** autoriza `publicar`, roda `RegraPublicacao::validar($dados)` **dentro da transação** e só
  então grava `status`, `publicado_por_id = auth()->id()` e `publicado_em = now()`. Recusa ⇒ `ValidationException`
  ⇒ **rollback completo** (I9).

**Detalhes que o passe corrigiu:**

- **Passar a MESMA instância** para `->model()` — nunca `find($id)` novo (`RichEditor` descarta anexos se
  `wasRecentlyCreated` for false).
- **`$dados['destinatarios']` (validado), não `$this->data[...]` (cru).** O `dehydrated(false)` do `Select` está
  **dentro de `->relationship()`** (`vendor/filament/forms/src/Components/Select.php:977`): vale para `autores`
  (que por isso **não** aparece em `$dados`), **não** para `destinatarios`/`relacionadas`, que **são** desidratados
  — é assim que a F4a já funciona ([SincronizaDestinatarios.php:25-27](../../../app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php#L25-L27)).
- **⚠️ M2 — Hidratação da edição, INCLUINDO o campo VIRTUAL `direcionar` (o caminho de destruição silenciosa):**
  `attributesToArray()` só traz **colunas** — logo `direcionar` (que não é coluna) chegaria **`false`** e
  `destinatarios`/`autores` chegariam **vazios**. No caminho **mais comum** (o médium edita **só o título** de uma
  direcionada) isso faria `$ehDirecionada = false` ⇒ `nivel = null` **e o pivô esvaziado**. Perda de dado, sem erro.
  A hidratação é, obrigatoriamente:

  ```php
  $this->form->fill($registro->attributesToArray());                      // colunas
  $this->data['direcionar']    = $registro->nivel === VisibilidadeMensagem::Direcionada->value;  // VIRTUAL
  $this->data['destinatarios'] = $registro->destinatarios()->pluck('users.id')->all();           // sem relationship
  ```

  **O que NÃO precisa de hidratação manual:** `autores` (Select **com** `->relationship()`) e `pictografia`
  (mídia) são carregados pelo `loadStateFromRelationships()`, que o `Component::hydrateState()` dispara porque o
  `fill()` **com argumento** mantém `$hydratedDefaultState = null`
  (`vendor/filament/schemas/src/Components/Concerns/HasState.php:450-451`) — desde que o schema esteja com
  `->model($registro)` (**instância**). ⚠️ Confirmar no 1º RED da task de edição: se o `assertFormSet` de `autores`
  vier vazio, hidratar à mão também (`pluck('autores_espirituais.id')`).

  **🚫 A "correção" errada e tentadora:** quando o I6 ficar vermelho por essa causa, é tentador fazer o guard
  **preservar** o pivô se a lista vier vazia. Isso **reabriria exatamente o furo que a F4a fechou** (nível ≠
  direcionada **tem** de esvaziar — I10). A correção certa é **hidratar**, nunca afrouxar o guard. O plano crava
  isso na task.

**Serviços novos em `App\Support\Mensagens\`:**

- **`SincronizadorDestinatarios`** — move a mecânica da F4a para o domínio **sem mudar o `/admin`**: o trait
  [SincronizaDestinatarios](../../../app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php) vira
  **adaptador fino** e **preserva o contrato inteiro** — a propriedade `protected array $idsDestinatarios`
  (consumida pelo `MensagemDestinatariosGuardTest` por classe anônima), `capturarDestinatarios(array): array`
  (**com** o `unset`) e `aplicarDestinatarios(Mensagem): void`. Métodos do serviço:
  `filtrarPorNivel(?string $nivel, array $ids): array` (guard, fail-closed) · `sincronizar(Mensagem, array): void`
  · `aplicar(Mensagem, ?string $nivel, array $ids): void`, que **filtra os ids contra `users` ativos** antes do
  `sync` (I7 — a options-list é UI, não integridade).
- **`RegraPublicacao::validar(array $dados): void`** — publicar exige
  `VisibilidadeMensagem::tryFrom((string) $nivel) !== null` (pega null, `''` e slug inválido) **e**, se
  `direcionada`, ≥1 destinatário. Lança `ValidationException` em pt-BR no campo `nivel`. **Vale só no site** (O6).
- **`SlugMensagem::unico(string $titulo, ?int $ignorarId = null): string`** — `Str::slug` + sufixo incremental.

### 6.6 A máquina de estados (o invariante em uma frase)

> **Pelo site, uma mensagem só se move num sentido — `pendente → publicado` — e só pelo martelo de quem tem
> `publicar`; toda outra transição é recusada no servidor.**

| Transição | Pelo site | O que acontece |
|---|---|---|
| (nada) → `pendente` | **médium** (`lancar`) | criada; `status` **forçado** no servidor |
| `pendente` → `pendente` | médium **dono** (`editarPendente`) ou curador (`editarNaCuradoria`) | salva; `status` reasserido em `pendente` |
| `pendente` → `publicado` | **curador** (`publicar`) | exige nível válido (**I9**, com rollback); grava `publicado_por_id` + `publicado_em` |
| `publicado` → qualquer coisa | **ninguém** | médium **e** curador: **403** (ambos os portões exigem `pendente`); **não existe** rota/método de despublicar ou excluir |
| `pendente` → `despublicada` | **ninguém** | fora de escopo (só `/admin`) |

**No `/admin` nada muda:** o admin segue soberano (troca `status` livremente e pode salvar publicado sem nível).
Aplicar a `RegraPublicacao` ao painel **quebraria** testes da 2A e violaria I20 ⇒ **decidido: não** (O6 fechado).

> **Superado pela Fatia F4c-AC (D12):** o O6 foi **revogado** — a `RegraPublicacao` passou a
> valer também no `/admin`, nos 3 caminhos (Action "Publicar", Select no `EditMensagem` e
> criação em `CreateMensagem`), sempre na transição para `publicado`. Ver
> `docs/superpowers/specs/2026-07-21-camada-4-fatia-f4c-ac-resumo-e-ajustes-curadoria.md`.

### 6.7 Auditoria da Mensagem (a escrita)

```php
// app/Models/Mensagem.php — molde AgendaDia.php:159-178
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->useLogName('mensagem')                       // log_name PRÓPRIO
        ->logOnly(['titulo', 'slug', 'corpo', 'contexto', 'formato', 'data_recebimento',
                   'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status'])
        ->useAttributeRawValues(['data_recebimento'])  // o accessor devolve Carbon; grava a string Y-m-d
        ->logOnlyDirty()
        ->dontSubmitEmptyLogs()
        ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
            'created' => 'mensagem criada',
            'updated' => 'mensagem atualizada',
            'deleted' => 'mensagem excluída',
            default => "mensagem {$evento}",
        });
}

/** Contexto (porta/IP/UA) + D11: registra QUE o texto mudou, sem guardar o texto. */
public function tapActivity(Activity $activity, string $eventName): void
{
    $props = $activity->properties->merge(AuditoriaAutorizacao::contexto());

    foreach (['attributes', 'old'] as $bloco) {
        $dados = $props->get($bloco);

        if (! is_array($dados)) {
            continue;
        }

        foreach (['corpo', 'contexto'] as $campo) {
            // array_key_exists, NUNCA isset: o valor pode ser null e é a CHAVE que se preserva.
            if (array_key_exists($campo, $dados)) {
                $dados[$campo] = '[texto não registrado]';
            }
        }

        $props = $props->put($bloco, $dados);
    }

    $activity->properties = $props;
}
```

- **`logOnly` NÃO inclui a autoria** — quem publicou é o **`causer`**; ids de usuário ficam fora do `properties`
  (I24).
- **D11 (a redação acima) roda DEPOIS do `logOnlyDirty`** ⇒ **não** altera a detecção de mudança: o campo continua
  entrando na trilha quando muda, só o **valor** é substituído. `titulo` **fica com valor** de propósito (curto, já
  visível na fila, e sem ele o histórico perde utilidade). Trava: **I27**.
- **PROIBIDO** (vetores reais): `logAll()`/`logUnguarded()`/`logFillable()`; chave com `.` no `logOnly`
  (dot-notation **lê a relação** e materializa PII); copiar `registrarDepartamentosConteudo` para o pivô de
  destinatários (grava `{id, nome}` — seria **nome de quem recebeu direcionada**). Se um dia for preciso, grava-se
  **só cardinalidade**. **Teste-contrato: I24.**
- **"Publicada" não é evento custom:** deriva do diff (`attributes.status === 'publicado'` e
  `old.status !== 'publicado'`) — evita a linha dupla que um `->event('publicada')` geraria junto do `updated` do
  mesmo save.
- **Porta `'perfil'`:** `boot()` **próprio em cada um dos dois componentes**, cópia literal do
  [AgendaConta.php:46-51](../../../app/Livewire/Conta/AgendaConta.php#L46-L51) — **nunca** em `mount()`.
  *Decisão (achado do passe):* **não** criar `App\Livewire\Concerns\MarcaPortaPerfil`; uma abstração com 2
  consumidores e 1 dissidente (o `AgendaConta`, que não será tocado) é pior que a repetição de 3 linhas. O
  esquecimento é coberto por I14 nos dois componentes.
- **Importador: sem `withoutLogs`** (C-B); entra o teste-contrato I17. Em base virgem o primeiro import gera 179
  `created` com `causer` null e `porta='sistema'` — **proveniência legítima**, renderizada como "criada · Sistema".

### 6.8 O histórico (a leitura — primeira do projeto)

**`App\Support\Mensagens\HistoricoMensagem`** + **`GlossarioCamposMensagem`** (lista branca + rótulos, molde
[GlossarioCapacidades](../../../app/Support/Autorizacao/GlossarioCapacidades.php#L26-L34)):

```php
Activity::query()
    ->where('subject_type', (new Mensagem)->getMorphClass())   // OBRIGATÓRIO: subject_id é compartilhado (morphs)
    ->where('subject_id', $mensagem->id)
    ->with('causer')            // sem isto é N+1; causer_id pode ser NULL
    ->latest('id')              // ordena pela PRIMARY (created_at NÃO tem índice)
    ->limit(20)
    ->get();
```

| Coluna | Origem | Regra |
|---|---|---|
| **Quando** | `created_at` | formato pt-BR |
| **Quem** | `causer?->name` | `null` ⇒ **"Sistema"** (morphs são `nullableMorphs` sem FK ⇒ sempre null-safe) |
| **O quê** | `description` | rotulada **"publicada"** quando o diff mostra `status` indo para `publicado` |
| **Campos alterados** | **união** das chaves de `attributes` e `old` ∩ **lista branca** → rótulos | ver abaixo |

```php
$mudou = array_values(array_unique(array_merge(
    array_keys($a->properties->get('attributes', [])),
    array_keys($a->properties->get('old', [])),
)));
```

A **união** é obrigatória: `created` não tem `old`, `deleted` não tem `attributes`, e `null → valor` só aparece
porque o vendor preenche a chave com null (`vendor/spatie/laravel-activitylog/src/Traits/LogsActivity.php:291`).
**Nunca** `array_filter`/`!empty` sobre valores nem `isset` (false para null) — usar `array_key_exists`.

**Lista branca e rótulos (`GlossarioCamposMensagem::ROTULOS`)** — campo fora da lista **não é exibido**:

| campo | rótulo | | campo | rótulo |
|---|---|---|---|---|
| `titulo` | Título | | `casa` | Casa |
| `slug` | Slug | | `link_arquivo` | Link do arquivo |
| `corpo` | Corpo da mensagem | | `liberar_download` | Liberar download |
| `contexto` | Contexto | | `nivel` | Nível de acesso |
| `formato` | Formato | | `status` | Status |
| `data_recebimento` | Data de recebimento | | | |

**Guardas obrigatórias:** ignorar `porta`/`ip`/`user_agent` (são **irmãos** de `attributes`); entrada **manual**
(`event` null, chave `diff`) ⇒ só a descrição; **proibido** `@json($properties)`, `foreach` que imprima valor,
`print_r`, `dd` e `{!! !!}` (o `corpo` é HTML cru ⇒ XSS armazenado **além** de vazamento).
**R3 — o corte tem de ser visível:** havendo mais de 20 entradas, a tela diz *"mostrando as 20 mais recentes"* —
senão o curador lê ausência como "não houve mais nada".

**"Editada pelo autor após o lançamento" (D9/I16)** — o predicado foi **qualificado** pelo passe: marcar quando
existe ≥1 `Activity` de `event='updated'` **cujo `causer_id` é o `medium_id` da mensagem**. Assim o **save do
próprio curador não marca** (ele sabe o que fez) e as **legadas** (`medium_id` null) **nunca** marcam. Resolvido em
**uma query** para a fila inteira, **filtrando `subject_type` e `log_name`**:

```php
Activity::query()
    ->where('subject_type', (new Mensagem)->getMorphClass())->where('log_name', 'mensagem')
    ->where('event', 'updated')->whereIn('subject_id', $ids)
    ->whereColumn(...)  // ou: join/whereIn com os pares (mensagem_id, medium_id) já carregados
    ->distinct()->pluck('subject_id');
```

### 6.9 As telas

**Views de página** — a F4b é a **primeira** a precisar dos **três** slots:

```blade
<x-layout.conta titulo="Minhas Mensagens" ativo="mensagens">
    <x-slot:headTop>@vite('resources/css/filament/site/theme.css')</x-slot:headTop>
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>
    <x-slot:scripts>@filamentScripts</x-slot:scripts>

    <livewire:conta.mensagens-conta />
</x-layout.conta>
```

- Copiar "a página da agenda" entrega um formulário **indexável**; copiar "a das direcionadas" entrega um
  formulário **sem CSS e sem JS**. Os três slots são obrigatórios nas duas telas (**I25** trava o `noindex`).
- **`@source` (item obrigatório, não "blindagem opcional"):** a linha atual do tema do site aponta para **um**
  arquivo de schema justamente porque schemas emitem as classes de **grade** (`columns(2)`, `columnSpan`,
  `columnSpanFull`) que o `MensagemForm` também usará. Generalizar para
  `@source '../../../../app/Filament/Schemas/**/*';` (alinha com o tema do `/admin`) **e atualizar o comentário do
  cabeçalho do `theme.css`**, que hoje diz "Carregado só na página da agenda". A conferência **visual da grade**
  entra na verificação manual (§9.10).

**Conteúdo (Blade puro, molde `livewire/conta/agenda-conta.blade.php`):**

- **Aba do médium:** cabeçalho + "Nova mensagem" → `{{ $this->form }}` em `<form wire:submit="salvar">`; lista das
  próprias (`medium_id = auth()->id()`) com **selo de status**.
  **⚠️ D10 — a publicada é um ITEM DE ÍNDICE, não uma leitura:** a linha mostra **título, data, status e nível** e
  **nada mais** — **sem corpo, sem trecho, sem link** para `mensagens.show`. Motivo: o curador pode ter classificado
  como `diretores` a mensagem de um médium **trabalhador**; exibir o corpo ali faria da aba a **primeira exceção à
  escada da 3A**. A leitura segue as regras normais (se ele tiver nível, chega pela lista pública; se não, a
  barreira da 3B é o comportamento correto — só não é caminho de UI). **Enquanto pendente**, o form completo
  continua disponível ao autor. Trava: **I26**.
- **Aba da curadoria:** fila de pendentes com **título · quem lançou** (**"Importada do legado"** quando
  `medium_id` é null) **· quando · aviso "editada pelo autor após o lançamento"**; ao abrir, form + **histórico** +
  **Salvar** e **Publicar** (`wire:confirm`).
- **⚠️ NÃO reusar `x-mensagem.card`/`linha`** (linkam para `mensagens.show` ⇒ **404 em pendente**). Cards próprios.
- **Selo de nível** sempre via `<x-mensagem.selo-nivel :visibilidade="$m->visibilidade()" />`; cor por
  `?->cor() ?? '#cbb26a'`. **Proibido** `VisibilidadeMensagem::from(...)` (49 registros nulos ⇒ 500 na fila).
  **I25** trava a fila renderizando com `nivel=null`.
- **Sem paginação** (nenhuma tela do `/minha-conta` pagina; fila = 47 hoje).
- **Feedback:** `session()->flash('status', …)` + `redirect(..., navigate: true)` — **nunca** `Notification::make()`.

### 6.10 `$hidden`, PII e fronteira do front (D8)

```php
/** Autoria INTERNA (curadoria/admin): nunca sai em toArray()/wire:snapshot. Não é fillable — o servidor atribui. */
protected $hidden = ['medium_id', 'publicado_por_id', 'publicado_em'];
```

- Os **três** campos entram (o D8 os lista juntos; custo zero).
- **Regra dura que o `$hidden` impõe:** como ele poda `attributesToArray()` — a fonte de hidratação dos forms —,
  **a autoria só pode ser EXIBIDA por RELAÇÃO** (`$registro->medium?->name`, `TextColumn::make('medium.name')`),
  **nunca** por campo de form ligado à coluna (nasceria vazio, sem erro). Colunas de tabela não são afetadas.
- **Não** acrescentar a autoria ao `$fillable`; **não** criar `$appends`; **não** eager-loadar em controller público.
- **Fronteira de PII (F2):** destinatários só nas telas de lançamento e curadoria; nunca na leitura. As duas telas
  novas são `@auth` + `noindex`.

### 6.11 A11y, responsivo, performance, segurança

- **Mobile-first** herdado do `x-layout.conta`; componentes Filament acessíveis; `wire:confirm` no martelo.
- **Performance:** `->with('causer')` no histórico; a marca de "editada" em **1 query** para a fila;
  `->with('autores','media')` nas listas; **nunca** `podeSerVistoPor` em laço.
- **Segurança:** 3 camadas (middleware `auth` → `abort_unless` no controller **e** no `mount` → `authorize` em
  **cada** ação, **sempre sobre o objeto** quando houver id do cliente); campos privilegiados reasseridos;
  `DB::transaction` envolvendo **getState + persistência + sync**; `noindex`.

---

## 7. As peças (inventário)

**Novos:** `database/migrations/2026_07_21_000001_add_autoria_to_mensagens_table.php` ·
`app/Filament/Schemas/MensagemForm.php` · `app/Support/Mensagens/{SincronizadorDestinatarios,RegraPublicacao,
SlugMensagem,HistoricoMensagem,GlossarioCamposMensagem}.php` · `app/Support/Conta/{AbaMensagens,AbaCuradoria}.php` ·
`app/Livewire/Conta/{MensagensConta,CuradoriaConta}.php` · `resources/views/conta/{mensagens,curadoria}.blade.php` ·
`resources/views/livewire/conta/{mensagens-conta,curadoria-conta}.blade.php` ·
`resources/views/components/conta/historico-mensagem.blade.php` · testes (§9).

**Editados (cirúrgico):** `app/Models/Mensagem.php` (+`LogsActivity`, options, `tapActivity`, `$hidden`, relações
`medium()`/`publicadoPor()`) · `app/Policies/MensagemPolicy.php` (**+5 métodos**) ·
`app/Filament/Resources/Mensagens/MensagemResource.php` (form → `MensagemForm::schemaAdmin()`; **+coluna "Lançada
por"** e o item O2 — níveis do enum, rótulo da coluna, `helperText` — §6.4/ii-b) ·
`Pages/SincronizaDestinatarios.php` (adaptador, contrato preservado) · `app/Http/Controllers/ContaController.php`
(+2) · `routes/web.php` (+2) · `resources/views/components/conta/nav.blade.php` (+2) ·
`resources/css/filament/site/theme.css` (`@source` + comentário).

**NÃO toca:** resolvedor 3A · `scopePublica`/`scopePublicado` · `Mensagens\Lista` · `MensagemController` ·
barreira/single (3B) · 3C inteira · sitemap · Autores · `config/navegacao.php` · matriz/seeders de capacidade ·
`AgendaConta`/`AgendaDiaForm` · `SincronizaRelacionadas` · importadores (salvo o teste I17) · pivôs (só `sync`).

---

## 8. Cutover (o que roda no deploy — do dono)

1. `git pull` — sem novas dependências Composer.
2. **`docker compose exec -T app php artisan migrate`** (incremental; **nunca** `fresh/refresh/reset/wipe`) — as 179
   existentes ficam com `medium_id = null` ("importada do legado").
3. **`npm run build`** no **HOST** ([[npm-vite-no-host]]) — há Blade/CSS novo (diferente da F4a).
4. `php artisan optimize:clear` + `docker compose restart app worker` ([[dev-opcache-restart-app-worker]]).
5. **Nada na matriz de capacidades** (D4).

**Ciência:** os 46 médiuns passam a ver "Minhas Mensagens"; os 2 diretores do DEPAE (+ 2 presidentes) veem
"Curadoria" com as **47 pendentes** esperando nível. Nada muda para os demais, e **nada muda no front público**.

---

## 9. Plano de teste (TDD real, vermelho primeiro)

Convenções **medidas**: PHPUnit clássico (**não** Pest); `Tests\TestCase` + `RefreshDatabase`; métodos
`public function test_<frase_pt_BR>(): void` — **sem `#[Test]`**; SQLite `:memory:`;
`$this->seed(EstruturaCemaSeeder::class)` explícito; personas por **helper privado** (não há factory state de
setor/cargo); `Storage::fake('public')` + `PNG_1X1`/`addMediaFromString` para mídia; cabeçalho de autoria.
⚠️ **Nos testes do `/minha-conta` é PROIBIDO `actingAsAdmin()`**; a persona "diretor-DEPAE" **não pode** ser
presidente. **Todo brief de subagente que rode `artisan` repete as proibições de banco.**

### 9.0 Ordenação (constraint)

Migration + model (**+ a trilha com o D11/I27 já aqui** — encaixe do consultor: se a auditoria entrar depois, o
dado nasce gravado **com** o texto) → **policy (5 métodos)** → serviços → **extração LITERAL do `MensagemForm`
(passo (i), I20)** → composições novas (passo (ii): `schemaMedium`/`schemaCuradoria`, I22/I23) → **item do `/admin`
(passo (ii-b): O2 + M3)** → aba+rota+controller do médium → **componente do médium (I3/I4/I5a/I7/I22 + M2, com o
I6 como teste-do-vermelho e a nota da "correção errada" cravada na task)** → aba+rota+controller da curadoria →
componente da curadoria (I8–I13/I23/I25/I26) → histórico (I15/I16/I24) → não-vazamento e regressão (I17–I21, I-reg).

**Testes-do-vermelho não-vacuous:**
(a) **I5a** — sem `->model($m)->saveRelationships()` o pivô de **autores** fica vazio (o Select é
`->relationship()` ⇒ a única gravação é ali);
(b) **I9** — publicar sem nível **passa** enquanto a `RegraPublicacao` não existir;
(c) **I14** — com `usarPorta` em `mount()` o teste **falha** (arranjo do §9.6);
(d) **I7** — sem o filtro server-side, o destinatário **inativo** forjado entra no pivô;
(e) **I11** — sem a reasserção, o `status` forjado publica pela curadoria.
*(Correção do passe: forjar `status` no **schemaMedium** é inerte — o `getState()` **poda** chaves fora do schema
(`vendor/filament/schemas/src/Concerns/HasState.php:447-467`); ali o vermelho viria do default da coluna, não da
reasserção. Por isso o alvo de forja mudou para destinatários/inativo (médium) e `status` (curadoria).)*

### 9.1 Fundação: migration, model, policy, extração — I20/I21

- **I21**: FK de `medium_id` aponta para `users` (`PRAGMA foreign_key_list('mensagens')` no SQLite; conferência no
  MySQL em §9.10).
- Model: `$hidden` com os 3 campos; `medium()`/`publicadoPor()` resolvem `User`; `nullOnDelete` funciona.
- **Policy (5 métodos × personas), com `Gate::forUser($naoAdmin)`**: médium ✅`lancar`/❌`curar`; diretor-DEPAE
  ✅`curar`; presidente ✅`curar`; frequentador ❌ tudo; `editarPendente` true só para dono+pendente (4 combinações);
  **`editarNaCuradoria`/`publicar` false em publicada**.
- **I20**: rodar os **29** testes (4 arquivos da F4a + `MensagemResourceTest`) **sem alterar uma linha**, após o
  **passo (i)**. O `GuardTest` é o mais sensível (consome `$idsDestinatarios`) — citar nominalmente no plano.

### 9.2 Portões e telas — I1/I2/I25(noindex)

Para **cada** aba: os **dois** portões (rota e `mount`) e as personas autorizada / não autorizada / **admin puro
(403)** / anônima (redirect). Mais: `Aba*::visivelPara` direto **sem semear capacidade**; a nav (`assertSee`/
`assertDontSee`); e `->assertSee('noindex, nofollow', false)` nas duas telas (molde
[MinhasDirecionadasTest.php:32-41](../../../tests/Feature/Conta/MinhasDirecionadasTest.php#L32-L41)).

### 9.3 O médium lança — I3/I4/I5a/I22

- **I3**: `Livewire::actingAs($medium)->test(MensagensConta::class)->call('novo')->fillForm([...])->call('salvar')`;
  no banco: `pendente`, `medium_id`, `nivel` null, slug único (duas com o mesmo título ⇒ slugs distintos).
- **I4**: `direcionar=true` + 2 destinatários ⇒ `nivel='direcionada'` + pivô com os 2; `false` ⇒ `nivel` null + 0
  pivô; `true` **sem** destinatário ⇒ `assertHasFormErrors(['destinatarios'])`.
- **I5a**: criar com 1 autor espiritual ⇒ 1 linha em `mensagem_autor_espiritual` (**o teste-do-vermelho do G1**).
- **I22** (molde [MensagemResourceTest.php:79-85](../../../tests/Feature/Filament/MensagemResourceTest.php#L79-L85)):
  `assertFormFieldDoesNotExist` para `nivel`, `status`, `slug`, `link_arquivo`, `liberar_download`, `relacionadas`;
  `assertFormFieldExists` para os 9 campos do D6.
- **⚠️ Limitação registrada (achado do passe):** a **anexação de mídia via form na CRIAÇÃO não é testável** — o
  pipeline do `SpatieMediaLibraryFileUpload` depende do ciclo de upload temporário do Livewire, que não roda em
  teste (documentado no próprio projeto em
  [PostResourceTest.php:252-260](../../../tests/Feature/Filament/PostResourceTest.php#L252-L260)). A prova de mídia
  vai para **I5b** (preservação na edição) + **verificação manual obrigatória** (§9.10 item 2).

### 9.4 Posse, preservação, D10 e POST não confiável — I5b/I6/I7/I26

- **I6/I5b — o teste-do-vermelho do M2 (arranjo campo a campo, senão é vacuoso):** pendente do médium com
  **`nivel='direcionada'` + 2 destinatários + 1 autor + 1 mídia** (`addMediaFromString`);
  `->call('editar', $m->id)->fillForm(['titulo' => 'Novo'])` — **só o título** — `->call('salvar')`; assertar as
  **quatro**: pivô de destinatários intacto, 1 linha de autor, `getMedia()->count() === 1` **e
  `$m->fresh()->nivel === 'direcionada'`**. *(Sem o nível `direcionada` no arranjo, o guard devolveria `[]` de
  qualquer jeito e a asserção viraria 0 == 0; preencher o form inteiro mascararia a falta de hidratação — achado da
  F4a; e sem hidratar o toggle `direcionar` o nível vira null e o pivô esvazia — **M2**.)*
  **Nota obrigatória na task:** quando este teste ficar vermelho, a correção é **hidratar o toggle**, jamais fazer
  o guard preservar o pivô com lista vazia (reabriria o furo da F4a — I10).
- **I6 (negativos):** a própria **publicada** ⇒ 403; a **pendente de outro** ⇒ 403.
- **I26 (D10):** médium **trabalhador** autor de uma **publicada** com `nivel='diretores'` ⇒ a aba responde **200**,
  `assertSee` do título e **`assertDontSee` de um trecho-sentinela do corpo** e do `route('mensagens.show', $slug)`
  (sem link); a **mesma** mensagem **pendente** ⇒ o autor abre o form e **vê** o corpo.
- **I7**: `->set('data.direcionar', false)->set('data.destinatarios', [$u1,$u2])` ⇒ `nivel` null e 0 pivô;
  `direcionar=true` com um `User::factory()->create(['ativo'=>false])` e com um id **inexistente** ⇒ nenhum dos
  dois entra no pivô; forja de `status`/`nivel` mantida como trava de regressão.

### 9.5 A curadoria e o martelo — I8–I13/I23/I25

- **I8**: publica ⇒ `status`, `publicado_por_id`, `publicado_em`, `medium_id` preservado.
- **I9**: publicar com `nivel` null / `''` / `'lixo-invalido'` ⇒ recusa + **registro íntegro** (título anterior
  preservado — prova o rollback); com nível válido ⇒ publica. Um caso usa pendente **legada** (`medium_id` null).
- **I10**: direcionada com 2 → trocar para `trabalhadores` ⇒ 0 pivô; **+ unidade** do guard.
- **I11**: `fillForm(['status' => 'publicado'])`/`->set('data.status','publicado')` + **Salvar** ⇒ segue
  `pendente`, `publicado_por_id` null; idem `'despublicada'`; **publicar já publicada** ⇒ 403; **`editar` de uma
  publicada** ⇒ 403 (o furo que o passe encontrou).
- **I12**: altera título+corpo+nível **sem** publicar ⇒ persistido e `pendente`.
- **I13 (D3)**: persona médium + diretor-DEPAE, não-admin, **não-presidente** publica a própria ⇒ permitido, e a
  `Activity` de `updated` tem `causer_id` = ele.
- **I23**: `assertFormFieldExists('nivel', fn (Select $f) => count($f->getOptions()) === 6 && array_key_exists('diretor-depae', $f->getOptions()))`.
- **I25 (fila)**: pendente com `medium_id=null, nivel=null` ⇒ **200** + `assertSee('Importada do legado')` (pega o
  rótulo **e** um eventual 500 de `from()`); pendente lançada por médium ⇒ `assertSee($medium->name)`.

### 9.6 A porta da auditoria — I14 (arranjo discriminante)

```php
$c = Livewire::actingAs($medium)->test(MensagensConta::class);  // mount/boot marcou
AuditoriaAutorizacao::usarPorta(null);                          // simula o processo novo do wire:click
$c->call('novo')->fillForm([...])->call('salvar')->assertHasNoFormErrors();

$this->assertSame('perfil', Activity::where('log_name', 'mensagem')->latest('id')->first()->properties['porta']);
```

Repetir para a curadoria. **Prova do vermelho:** mover `usarPorta` para `mount()` e ver falhar. Nenhum teste novo
sobrescreve `tearDown()` sem `parent::tearDown()`.

### 9.7 O histórico — I15/I16/I24

- **I15 — testar o RENDERIZADOR ISOLADO**, nunca a página: `$this->blade('<x-conta.historico-mensagem :mensagem="$m" />', …)`
  ou unidade sobre `HistoricoMensagem::linhas($m)`. *(Na página inteira, `assertSee('Título')` fica verde só pelos
  **labels do form**, e `assertDontSee('ip')` é falso-vermelho garantido — a página carrega `@filamentScripts`.)*
  Asserções negativas por token não-ambíguo: `assertDontSee('user_agent', false)`, `assertDontSee('"porta"', false)`,
  `assertDontSee('attributes', false)`, e o corpo antigo + nome de destinatário.
- Unidade do leitor: `created` (sem `old`), `updated` (união), campo fora da lista branca **não** aparece, entrada
  **manual** (`diff`) não quebra, `causer` null ⇒ "Sistema", **e** uma `Activity` de **outro `subject_type` com o
  mesmo `subject_id`** não entra (prova o filtro de morph).
- **I16**: recém-criado **não** marcado; `updated` do **curador** **não** marca; `updated` do **médium autor**
  marca; legada (`medium_id` null) **nunca** marca — com uma `Activity` de outro subject_type no mesmo id.
- **I24**: criar direcionada com 2 destinatários pelo site (um deles com **nome-sentinela**) ⇒ nenhuma entrada de
  `log_name='mensagem'` com `destinatario`/`medium_id`/`publicado_por_id` **nem com o nome-sentinela** no
  `properties` (**R4**).
- **I27 (D11)**: editar o `corpo` de uma pendente pelo site, com um **trecho-sentinela** no texto novo **e** no
  antigo ⇒ a entrada `updated` **tem a chave `corpo`** em `attributes` (o histórico continua listando "Corpo da
  mensagem"), **e** `Activity::where('log_name','mensagem')->get()` **não contém** nenhum dos dois trechos; idem
  `contexto`; e o **`titulo`** de uma edição de título **continua** com o valor no `properties` (prova que a
  redação é cirúrgica, não geral).

### 9.8 Importador — I17

Importar o lote, `Activity::query()->delete()`, importar **o mesmo lote**, assertar
`Activity::where('log_name','mensagem')->count() === 0`. **O fixture precisa morder** (achado do passe): ≥1 item com
`link_arquivo` no formato bruto do legado (com `&amp;`, molde
[ImportadorMensagensTest.php:175](../../../tests/Feature/Importacao/ImportadorMensagensTest.php#L175)), um `corpo`
que o `clean()` **reescreva** e `data_recebimento` em unix — só então prova a idempotência dos mutators.

### 9.9 Não-vazamento e ponte com 3A/3B/3C — I18/I19

- **I18**: nomes-sentinela; `assertDontSee` do **nome** e das **3 chaves** em single (anônimo e logado), lista
  (`get`, não `Livewire::test`), sitemap, perfil do autor e `/minha-conta/direcionadas` — **trava de regressão**
  (passa hoje, e é isso que se quer preservar).
  **⚠️ M1 — o que morde é o estado HIDRATADO, não o GET.** No molde `AgendaConta` o `$data` só é preenchido em
  `novo()`/`editar()`; no GET inicial ele é `[]` e os itens da lista vão para a **view** pelo `render()` (não são
  propriedade pública) ⇒ **sem `$hidden` o GET continua não contendo `medium_id`** e um teste sobre ele **passa
  sempre**. O teste correto exercita o `fill($registro->attributesToArray())`:

  ```php
  Livewire::actingAs($curador)->test(CuradoriaConta::class)
      ->call('editar', $pendenteDeUmMedium->id)
      ->assertDontSee('medium_id', false);
  ```

  (e o par na tela do médium). **Regra para TODO `assertDontSee` desta fatia:** *"essa string apareceria NESTA
  superfície, NESTE estado, se o guard saísse?"* — se a resposta não for um **sim demonstrável**, o teste é
  decorativo e não conta como cobertura.
- **I19**: pendente criada pelo fluxo novo **não** aparece na lista, dá **404** no single, fora do sitemap e das
  Direcionadas; **depois de publicada** com nível `publico`, **aparece**.

### 9.10 Regressão, verificação real e suíte — I-reg

Baseline **1097**; alvo **1097 + novos**, verde; **nenhum teste existente alterado**. Verificações **fora da suíte**:

1. `docker compose exec -T app php artisan migrate` **no MySQL do dev** + conferir no Adminer que
   `mensagens.medium_id` referencia **`users`** (o C-F **só** aparece aqui).
2. `npm run build` no host + `restart app worker`; como médium real: criar mensagem **com imagem e autor** e
   **conferir no `/admin` que a pictografia e o autor estão lá** (a única prova da mídia na criação — §9.3);
   conferir a **grade** do form (não só "estilizado"). Como Charles: curar → publicar; ver o histórico; confirmar
   que uma das 47 legadas **recusa** publicação sem nível.
3. Usuário sem setor médium: sem aba, 403 na rota.
4. `./vendor/bin/pint` ([[pint-antes-de-push]]); atenção ao flaky de GD ([[flaky-importadorblog-gd-cap-imagem]]).

---

## 10. Fora de escopo

Devolver `publicado → pendente`; despublicar/excluir pelo site; **editar mensagem já publicada pelo site** (O7);
**`relacionadas` na curadoria** (a persistência vive no trait das Pages; relacionar segue no `/admin` — §6.4);
notificação/e-mail; **F5**; mexer na matriz ou abrir o `/admin` a não-admin; viewer geral de trilha no `/admin`;
mexer no front público; paginação; índice em `nivel`; backfill de autoria; corrigir o débito de porta do
`EditarPerfil`; migrar o `AgendaConta`.

---

## 11. Fronteiras: o que toca × o que NÃO toca

**Toca (novo):** migration · `MensagemForm` · 5 serviços em `App\Support\Mensagens` · 2 abas · 2 componentes · 4
views + 1 componente de histórico · testes.
**Toca (cirúrgico):** `Mensagem` · `MensagemPolicy` (+5) · `MensagemResource` (consome o schema) · trait
`SincronizaDestinatarios` (adaptador, **contrato preservado**) · `ContaController` · `routes/web.php` · `nav` ·
`theme.css`.
**NÃO toca:** resolvedor 3A · scopes · `Mensagens\Lista` · `MensagemController` · barreira/single · 3C · sitemap ·
Autores · `config/navegacao.php` · matriz/seeders · `AgendaConta`/`AgendaDiaForm` · `SincronizaRelacionadas` ·
importadores (salvo I17) · pivôs (só `sync`).

---

## 12. Ciências (não são tarefa desta fatia)

- A F4b concentra risco em dois pontos de **falha silenciosa**: a sequência do §6.5 e o renderizador do §6.8.
- **`departamento_mensagem` está vazio (0/179)** e é irrelevante no regime `DoTipo`.
- **Bypass do presidente é comportamento aceito** (3A): Aury e Elizabete veem as 15 direcionadas de terceiros e as
  2 publicadas sem nível.
- **Dívida de dados:** as 2 publicadas com `nivel` null ficam fora de qualquer trava nova (candidatas a um
  `cema:sanear-mensagens-sem-nivel` — não nesta fatia).
- **Débito de porta:** `EditarPerfil` grava `porta='sistema'` (correção de 1 linha, fora do escopo).
- **Documentação a atualizar no PR:** [DATA-MODEL.md:365-366](../../../DATA-MODEL.md#L365-L366) ainda registra a
  decisão de 25/jun (`mensagens.publicar` **pela matriz**), **superada pelo D4**; `DATA-MODEL` ganha as 3 colunas +
  `log_name='mensagem'`; `ROADMAP` marca "Mensagens mediúnicas".
- **Se a trilha precisar rastrear destinatários**, o pivô **não tem PK nem timestamps** — migration própria.
- **O5 (fechado: aceitar e documentar) — o alcance real do conflito importador × curadoria é PEQUENO,** e a razão
  é o `wp_id`: o importador casa por `firstOrNew(['wp_id' => …])` ([ImportadorMensagens.php:36](../../../app/Importacao/ImportadorMensagens.php#L36))
  e **toda mensagem criada pelo site nasce SEM `wp_id`** ⇒ o re-import **nunca a alcança**. O dano possível se
  limita às **179 legadas** cujo texto tenha sido corrigido na curadoria. **Recomendação operacional:** se um dia
  for preciso re-importar, **avisar os curadores antes**.
- **R1 — mídia órfã no rollback:** o `saveRelationships()` escreve **arquivo no disco** dentro da transação; uma
  recusa da `RegraPublicacao` reverte as linhas de `media`, mas **não apaga os arquivos**. É **lixo, não
  corrupção** — registrado aqui de propósito, sem tarefa nesta fatia.

---

## 13. Decisões fechadas (zero ponto aberto)

**Fechados pelo autor** (decisão técnica, não de produto): **O1** — nome `medium_id` **mantido**, com
`constrained('users')` explícito + I21. **O6** — a `RegraPublicacao` vale **só no site**; aplicá-la ao painel
quebraria testes da 2A e violaria I20.
*(Superado pela Fatia F4c-AC — D12: o O6 foi revogado, a `RegraPublicacao` passou a valer também nos 3 caminhos do `/admin`.)*

**Fechados no passe do consultor:**

| # | Pergunta | **Decisão** |
|---|---|---|
| **O2** | Corrigir o **painel** (`NIVEIS` 5→6 + `formatStateUsing` + `helperText` mentiroso)? *(rebaixado: **não** bloqueia o D2 — a curadoria usa `schemaCuradoria()`.)* | **ENTRA**, como item próprio do passo (ii-b), **junto com o M3** (coluna "Lançada por"), com teste. |
| **O3** | Autor espiritual **obrigatório** para publicar? **32 das 47 pendentes (68%) não têm autor.** | **NÃO obrigar** — a fila travaria; "Sem assinatura" já é estado legítimo no front. |
| **O4** | Destinatários: `->options()` client-side **ou** `getSearchResultsUsing` server-side? | **`->options()` client-side** — honra o D5, mantém **um** idioma com o `/admin`, e a integridade vem da reasserção server-side (I7). |
| **O5** | **Conflito importador × curadoria (C-B2)** | **ACEITAR E DOCUMENTAR** (§12), com a ciência do `wp_id` (o re-import **nunca** alcança o que nasceu no site) e o aviso operacional aos curadores. |
| **O7** | Depois de publicada, o diretor continua editando **pelo site**? | **NÃO nesta fatia** — `editarNaCuradoria` exige `pendente`; editar as 132 publicadas é superfície nova, com fatia própria. |

**Regras sempre:** pt-BR; cabeçalho de autoria; `Pint` antes do push; `docker compose exec -T app php artisan test`;
`npm run build` **no host**; **migrations só incrementais**; **todo brief de subagente que rode `artisan` DEVE
proibir `migrate:fresh/refresh/wipe/reset` e seed destrutivo** ([[nunca-migrate-fresh-no-dev]]).

---

## 14. Os dois passes adversariais

### 14.1 Passe de TERRENO (18 agentes) — antes de escrever

10 investigadores (schema · model/3A · autorização · superfície `/minha-conta` · form Filament · auditoria ·
tema/assets · front público · convenções de teste · medição somente-leitura do dev) + **8 verificadores
encarregados de refutar** G1, G2, G3, G6, G12, G13, T6 e D8.
Resultado: **G1 CONFIRMADO** (causa raiz + 7 armadilhas), **G3 CONFIRMADO**, **G6 CONFIRMADO** (+ a descoberta de
que o teste de porta atual **não** discrimina `boot` de `mount`), **D8 CONFIRMADO**, **G2 PARCIAL** (o `@source`
não é bloqueador; os **slots** são), **G13 PARCIAL**, **T6 PARCIAL** (bypass de presidente), **G12 REFUTADO** — e o
achado do **bug latente C-F**, que nenhum teste pegaria.

### 14.2 Passe sobre ESTE spec (5 lentes) — o que ele corrigiu

Lentes: completude vs. kickoff · cobertura de teste · fronteiras/creep · técnica do código prescrito · verificação
de citações. Veredito: **4 lentes com PROBLEMA_SERIO**, 1 com ajustes menores. **7 bloqueadores**, todos
incorporados:

| # | O que estava errado | Onde ficou |
|---|---|---|
| **B1** | O bloco de destinatários reusava o predicado `$get('nivel')` — **o form do médium não tem `nivel`** ⇒ Section eternamente oculta, `required` nunca dispara, **I4 impossível de ficar verde**. | §6.4 (bloco **parametrizado** por closure) |
| **B2** | "Select multiple é `dehydrated(false)`" — **falso**: isso só vale com `->relationship()`. Mandava ler o estado **cru** (`$this->data`), contra o CLAUDE.md e contra o idioma da F4a. | §6.5 (`$dados['destinatarios']`, validado) |
| **B3** | A query de "editada após o lançamento" **não filtrava `subject_type`** — `subject_id` é compartilhado entre morphs ⇒ um `User` id=7 marcaria a Mensagem id=7. | §6.8 (+ teste de morph em §9.7) |
| **B4** | **Nenhum método barrava o curador de EDITAR uma publicada**: `curar()` não recebe objeto, e o componente aceita id do cliente ⇒ furo em O7 e na máquina de estados. | §6.2 (`editarNaCuradoria`) + I11 |
| **B5** | `schemaCuradoria` arrastava o Select `status` (com "Despublicada" e default "Publicada") ⇒ publicar por fora da `RegraPublicacao`; e `relacionadas`, que **não gravaria nada** em silêncio. | §6.4 (ambos removidos) + §10 |
| **B6** | O teste-carro-chefe (mídia via form na criação) **não é implementável** — o pipeline do upload não roda em teste (documentado no próprio projeto). | I5a/I5b + limitação registrada (§9.3) e verificação manual (§9.10) |
| **B7** | `create()` + `save()` gerava INSERT+UPDATE (um `updated` espúrio no histórico); e passar `medium_id` no `create()` seria **descartado em silêncio** (não é fillable, e o projeto não liga `preventSilentlyDiscardingAttributes`). | §6.5 (`new` + atribuição + **1** save) |

**Também incorporados (importantes):** `getState()` **dentro** da transação (uma recusa de publicação deixava
mídia/autores já persistidos); alvo de forja do I7 trocado para **destinatários/inativo** (o `getState()` **poda**
chaves fora do schema, então forjar `status` no form do médium é inerte) + **filtro server-side de destinatários**;
teste do histórico no **renderizador isolado** (na página, `assertSee('Título')` passa pelos labels do form e
`assertDontSee('ip')` é falso-vermelho); predicado de "editada" **qualificado pelo autor** (senão o próprio save do
curador marcava); fixture do I17 que **exercite** `clean()`/`LinkDrive`; **I22** (ausência de campos no médium) e
**I23** (6 níveis) — o D6 e o D2 não tinham teste; **I24** (PII na trilha) e **I25** (noindex + rótulo do legado +
fila com `nivel` null); extração em **dois passos** (I20 era autocontraditório: prometia neutralidade e carregava 3
mudanças de comportamento); **O2 rebaixado** (o D2 se cumpre em `schemaCuradoria()`); **O1/O6 fechados**; trait
`MarcaPortaPerfil` **descartado** (abstração com 2 consumidores e 1 dissidente); `$hidden` com os **3** campos +
regra "autoria só por relação"; contrato do adaptador **preservando `$idsDestinatarios`**; `@source` promovido a
item obrigatório; admin puro → 403 **por decisão**, com teste.
**Citações corrigidas:** `ForeignIdColumnDefinition.php:42` (era :41); **16 da F4a em 4 arquivos + 13 da 2A = 29**
(era "16 + 12" e "3 arquivos"); caminhos completos do vendor; convenção de unique nomeado; C-B reformulado (o teste
citado é **molde**, não trava o importador).

### 14.3 Passe do CONSULTOR (21/jul) — veredito: ✅ **APROVADA para virar PLANO**

Verificado contra o vendor e o dev (não contra o resumo): **C-F confirmado** (com o complemento de que
`publicado_pors` daria erro **explícito** — o silêncio é privilégio do `medium_id`, §3.1); **B2 confirmado** no
fonte (`Select.php:977`, dentro de `relationship()`); **B6 confirmado** (a limitação de mídia em teste está
documentada em [PostResourceTest.php:251-260](../../../tests/Feature/Filament/PostResourceTest.php#L251-L260)) —
**e a escolha de provar a linha do G1 pelo pivô de AUTORES é suficiente**, porque autores e mídia passam pelo
**mesmo** `saveRelationships()` do schema; conferem ainda os 3 slots, `useAttributeRawValues`, o acoplamento do
`GuardTest` a `$idsDestinatarios` e as contagens **16 (4 arquivos) + 13 = 29**.
**Uma superestimação corrigida:** usuário inativo **não loga** (`FortifyServiceProvider.php:34`) ⇒ o filtro `ativo`
é **higiene de UI**, não porta de segurança (§2-D5).

**3 OBRIGATÓRIOS — incorporados:**

| # | O que estava errado | Onde ficou |
|---|---|---|
| **M1** | **I18 era vacuous:** no GET o `$data` é `[]` e os itens vão para a view pelo `render()` ⇒ **sem `$hidden` o teste passaria igual**. | §9.9 (teste no estado **hidratado**, `->call('editar')`) + a **regra geral** para todo `assertDontSee` |
| **M2** | **A hidratação do toggle `direcionar` não estava prescrita** — campo **virtual**, que `attributesToArray()` nunca traz ⇒ no caminho **mais comum** (editar só o título de uma direcionada) o nível virava null e **o pivô era esvaziado**, sem erro. | §6.5 (hidratação obrigatória) + I6 como teste-do-vermelho + **a nota da "correção errada"** (afrouxar o guard reabriria o furo da F4a) |
| **M3** | **O D8 promete `/admin` e a SPEC só entregava a curadoria** — o admin ficava sem ver quem lançou. | §6.4/ii-b (coluna `medium.name` **por relação**, com `placeholder` do legado e `->with('medium:id,name')`), junto do O2 |

**2 DECISÕES NOVAS DO DONO** (§2): **D10** — a aba do médium **não** mostra o corpo de uma publicada (senão seria a
primeira exceção à escada da 3A) → **I26**; **D11** — a trilha registra **que** o corpo mudou, **sem** guardar o
texto (retenção indefinida ⇒ o `activity_log` acumularia cópia integral de mensagens restritas) → **I27**, com a
redação cirúrgica no `tapActivity` (§6.7).

**Refinamentos aceitos:** R1 (mídia órfã no rollback → §12) · R2 (`->with('medium:id,name')`) · R3 (avisar o corte
de 20 no histórico) · R4 (I24 procura também o **nome** do destinatário) · R5 (I15 segue no renderizador isolado).
**Encaixes de ordem (§9.0):** o M2 entra na **mesma task** do componente do médium; o **D11 entra com a auditoria,
antes do histórico** — senão o I27 nasceria depois do dado já gravado com texto.

**Destino:** **PLANO** (tasks TDD, ordem §9.0) → **passe do plano** (o consultor) → execução → PR → passe do PR.
**Não implementar antes do go.**
