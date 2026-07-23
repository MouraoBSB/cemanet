# Camada 4 · Fatia F4b — Curadoria das Mensagens (o médium lança, o DEPAE publica) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Abrir a **produção de Mensagens no próprio site**: o **médium** lança em `/minha-conta/mensagens` (nasce **pendente**, `medium_id` dele, `nivel = null`) e o **diretor do DEPAE** cura em `/minha-conta/curadoria` (fila das pendentes, form completo, **histórico**, e o martelo **Publicar** que arbitra o nível). Fecha o núcleo da Camada 4.

**Architecture:** Eixo de autorização = **PERTENCIMENTO** (setor/cargo), como a 3C — **nada** pela matriz (`mensagem.*` segue inerte). Duas abas no molde `AbaDirecionadas` (`WeakMap`, sem `checkPermissionTo`), duas rotas GET no grupo `conta.`, dois componentes Livewire com **Filament Forms embutido** (molde `AgendaConta`) e **5 métodos novos de policy** (`lancar`/`editarPendente`/`curar`/`editarNaCuradoria`/`publicar`). O form vira **fonte única** `App\Filament\Schemas\MensagemForm` com 3 composições (`schemaAdmin` **idêntico ao de hoje** · `schemaMedium` · `schemaCuradoria`) e um **bloco de destinatários parametrizado pelo predicado** (o médium não tem campo `nivel`; ele tem um **toggle `direcionar`**). A escrita fora do painel segue a **sequência do G1** (`getState` → `new`+atribuição+`save` → `->model($m)->saveRelationships()` → `sync` do pivô), **inteira dentro de `DB::transaction`**. `Mensagem` ganha **3 colunas de autoria** (FK explícita para `users`), `$hidden`, **`LogsActivity`** com `log_name='mensagem'` e a **redação D11**. O histórico é o **primeiro leitor de trilha** do projeto.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 (v5.6) · Livewire 4 · MySQL (SQLite `:memory:` nos testes). Docker (`docker compose exec -T app php artisan …` — o projeto **não** usa Sail). **npm/Vite no HOST** — esta fatia **tem** asset novo.

**Spec:** [docs/superpowers/specs/2026-07-21-camada-4-fatia-f4b-curadoria-medium-depae.md](../specs/2026-07-21-camada-4-fatia-f4b-curadoria-medium-depae.md) — ✅ aprovada no passe do consultor (M1/M2/M3 + D10/D11; zero ponto aberto). Referências `§N`/`I#`/`M#`/`D#`/`P#` são a ela.

**PR único (D1):** `feat(camada-4-fatia-f4b): médium lança e diretor do DEPAE publica no /minha-conta`. Base `origin/main` (`8142883`). Branch `camada-4-fatia-f4b-curadoria-mensagens`. Suíte baseline: **1097**.

**Passe do plano — CONSULTOR (21/jul):** ✅ aprovado com **P1** (`->model($registro)` **antes** do `fill`, com a proibição de "hidratar na mão") e **P2** (as duas metades do teste do I27) — ambos incorporados. Confirmou no vendor: a redação D11 funciona porque o tap roda **antes** do `save()` (`ActivityLogger.php:172-175`); `assertForbidden` em `Livewire::test()` é idioma da casa (`AbaAgendaTest.php:112-118`); a Task 6 é segura (os testes existentes só exigem `array_key_exists('publico', …)` e `isLive()`); e o `GuardTest` assere ids **inexistentes**, o que obriga `filtrarPorNivel` a devolver **cru**.

**Passe interno do plano (21/jul, 3 lentes: APIs/viabilidade · provas do vermelho · ordem/dependências):** veredito **PROBLEMA_SERIO nas 3** — **5 bloqueadores** corrigidos nesta versão, entre eles **dois testes que nasciam vacuosos, um deles o do próprio M1**:

| # | Achado | Correção aplicada |
|---|---|---|
| **V1** | **O teste do M1 é vacuous:** `assertDontSee()` do Livewire tem `$stripInitialData = true` por padrão e **apaga o `wire:snapshot`** antes de comparar (`MakesAssertions.php:29` → `ComponentState.php:59-78`) — e o snapshot é o **único** vetor de `medium_id`. Passa com ou sem `$hidden`. | Asserção sobre o **estado**: `assertArrayNotHasKey('medium_id', $c->get('data'))` (Tasks 8/9) |
| **V2** | **`RegraPublicacao` como Unit puro + `ValidationException` é impossível:** `withMessages()` chama a facade `Validator` (`ValidationException.php:66-75`) e os `tests/Unit` do projeto **não** bootam o app. | Vira **`RegraPublicacao::erros(array): array`** (molde `CardinalidadePalestra::erros`); o **lançamento** fica no componente, com a chave **`data.nivel`** (statePath) |
| **V3** | **Task 5 não tinha caminho de verificação:** o molde `AgendaDiaFormSchemaTest` só varre o **topo** do array e todo campo do `MensagemForm` vive dentro de `Section`; descer exige `getContainer()`, que **fatalha** fora de um Livewire. | Task 5 vira **implementação-só**; **I22 → Task 7**, **I23 → Task 9** (onde há componente real) |
| **V4** | **I11 vacuous:** forjar `data.status` na curadoria é inerte — o `getState()` **poda** chaves sem componente (`HasState.php:450/467`), e o B5 tirou o Select `status` do `schemaCuradoria`. | Rebaixado a **trava de contrato**; o **vermelho real** da Task 9 passa a ser o furo **B4** (editar/publicar **publicada** ⇒ 403) |
| **V5** | **I7 vacuous na UI:** o `Select` multiple injeta `Rule::in` automática (`Select.php:1733-1775`, `1805-1807`) ⇒ id inexistente **reprova na validação**, `aplicar()` nem roda. | Na UI, assertar `assertHasErrors(['data.destinatarios.0'])`; a prova do **filtro server-side** fica na **unidade** da Task 3 |

**Também corrigidos (importantes):** I14 e I27 **nasciam verdes** (implementação vinha em task anterior) ⇒ **mutação obrigatória e registrada**; faltavam dois testes de **vazamento real** (lista do médium só as próprias; fila da curadoria só pendentes); as negativas do renderizador de histórico eram vacuosas (a D11 já limpa o dado) ⇒ **injetar linha suja à mão**; o I5a dependia de `autores` continuar `->relationship()` sem nada travar isso ⇒ **`! $f->isDehydrated()`**; I10 ambíguo (batia no 403); Task 5 podia mudar o `/admin` em silêncio (filtro `ativo` + perda do `helperText`); Tasks 6/10/11 não re-rodavam os filtros do que editavam; faltava **checkpoint de suíte após a Task 1** (raio global do model) e a **task de documentação**.

---

## Global Constraints

- **pt-BR em tudo**: identificadores de domínio, comentários, UI, mensagens de erro, commits.
- **Cabeçalho de autoria** em todo PHP/Blade novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21`.
- **1 migration, aditiva e incremental.** 🚫 **NUNCA** `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed/factory destrutivo — o dev tem 179 mensagens, 148 usuários, 73 vínculos e mídia ([[nunca-migrate-fresh-no-dev]]). `legado` é **read-only**. **Todo brief de subagente que rode `artisan` reafirma isto.**
- **🚫 `constrained('users')` SEMPRE explícito** (`Str::plural('medium') === 'media'` ⇒ FK silenciosamente errada; `publicado_por_id` daria erro explícito — a regra vale para as duas).
- **O eixo é PERTENCIMENTO.** Não tocar `MatrizCapacidades`/`TiposConteudoSeeder`/`CapacidadesSeeder`/`GlossarioCapacidades`; não ativar `mensagem.*`; não usar `ver/criar/editar/excluir` (403 para todo não-admin) nem `view`/`viewAny` (passam para qualquer leitor).
- **`Gate::before` do admin mascara tudo** ⇒ **nenhum** teste do eixo de autoria usa `actingAsAdmin()`; a persona "diretor-DEPAE" **nunca** é presidente.
- **Não tocar:** resolvedor 3A · `scopePublica`/`scopePublicado` · `Mensagens\Lista` · `MensagemController` · barreira/single (3B) · 3C inteira · `SitemapController` · Autores · `config/navegacao.php` · `AgendaConta`/`AgendaDiaForm` · `SincronizaRelacionadas` · importadores (salvo o teste da Task 12).
- **Sequência do G1 obrigatória** em todo create fora do painel: sem `$this->form->model($registro)->saveRelationships()` a mídia e os pivôs de `->relationship()` somem **sem erro e sem log**.
- **🚫 P1 — `->model($registro)` ANTES do `fill()` em TODA edição.** O padrão `->model($this->editandoId ? find(…) : Class::class)` funciona **por sorte de ordem** (o schema é cacheado no 1º acesso do request; com class-string o `getRecord()` é **null** ⇒ hidratação vazia de `autores`/`pictografia`, sem erro). Bloco literal na Task 8.
- **🚫 Proibido "consertar" a hidratação de `autores`/`pictografia` escrevendo em `$this->data`** — a tela **exibe** certo e continua **não gravando** (I5b verde por acidente).
- **`Notification::make()` NÃO funciona no site** — o idioma é `session()->flash('status', …)` + `$this->redirect(route(…), navigate: true)`.
- **Porta de auditoria** em **`boot()`** (nunca `mount()`), cópia literal de `AgendaConta.php:46-51`. **Não** criar trait.
- **Qualificar todo `pluck` de pivô** (`pluck('users.id')`) — pivôs com `id` próprio dão *ambiguous column*.
- **Chave de erro = statePath.** `assertHasFormErrors(['nivel'])` procura **`data.nivel`** (`TestsForms.php:101-113`) ⇒ toda mensagem manual usa a chave **`data.<campo>`**.
- **Testes:** PHPUnit clássico, `public function test_<pt_BR>(): void` (**sem `#[Test]`**), `Tests\TestCase` + `RefreshDatabase`, `$this->seed(EstruturaCemaSeeder::class)` explícito, personas por **helper privado**, `Storage::fake('public')` em todo teste que anexe mídia. Por task: `--filter=<Nome>`; **suíte inteira** nos 2 checkpoints (após a Task 1 e na Task 12).
- **Pint antes de cada commit:** `docker compose exec -T app vendor/bin/pint <arquivos>` ([[pint-antes-de-push]]).
- **Cada commit** termina com `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **Regra dos `assertDontSee` (M1, agora com a ressalva V1):** pergunte *"essa string apareceria NESTA superfície, NESTE estado, se o guard saísse?"* — e lembre que no Livewire o `assertDontSee` **apaga o snapshot**: para provar guarda de estado, asserte `$c->get('data')`.
- **Toda prova do vermelho exige MUTAÇÃO registrada:** quando a implementação já existir de uma task anterior, **desfazer temporariamente** a parte sob teste, rodar o filtro, **colar a saída da falha** no relato da task e reverter.

---

## Task 1: Migration de autoria + model (colunas, `$hidden`, relações, trilha, redação D11)

**Files:**
- Create: `database/migrations/2026_07_21_000001_add_autoria_to_mensagens_table.php`
- Modify: `app/Models/Mensagem.php`
- Test: `tests/Feature/Mensagens/MensagemAutoriaColunasTest.php`, `tests/Feature/Autorizacao/AuditoriaMensagemTest.php`

- [ ] **Step 1: Testes que falham** — `MensagemAutoriaColunasTest`: (a) **I21** `PRAGMA foreign_key_list('mensagens')` ⇒ `medium_id` e `publicado_por_id` apontam para **`users`**; (b) `medium()` resolve o `User`; (c) `$u->delete()` ⇒ `medium_id` null e a mensagem **existe**; (d) `$hidden`: `array_keys($m->toArray())` sem os 3 campos.
  `AuditoriaMensagemTest`: (e) `update(['titulo' => 'Novo'])` ⇒ 1 entrada `log_name='mensagem'`, `event='updated'`, com `properties['porta']`; (f) `save()` limpo ⇒ **0** entradas; (g) **I27/P2** (sentinelas distintos p/ antigo e novo):
  ```php
  $m = Mensagem::factory()->create(['corpo' => '<p>SENTINELA-ANTIGA-XYZ</p>']);
  Activity::query()->delete();
  $m->update(['corpo' => '<p>SENTINELA-NOVA-XYZ</p>']);

  $props = Activity::where('log_name', 'mensagem')->latest('id')->first()->properties;
  $this->assertArrayHasKey('corpo', $props['attributes']);   // a CHAVE sobrevive
  $this->assertArrayHasKey('corpo', $props['old']);

  $json = Activity::where('log_name', 'mensagem')->get()->toJson();
  $this->assertStringNotContainsString('SENTINELA-ANTIGA-XYZ', $json);   // o TEXTO não
  $this->assertStringNotContainsString('SENTINELA-NOVA-XYZ', $json);
  ```
  (h) idem `contexto`; (i) editar `titulo` ⇒ o **valor** do título **está** no `properties` (a redação é cirúrgica).
- [ ] **Step 2a: Trilha SEM a redação** — migration (§6.1) + `$hidden` + relações + `LogsActivity` + `getActivitylogOptions()` + `tapActivity()` **só com `merge(AuditoriaAutorizacao::contexto())`**. Rodar `--filter=AuditoriaMensagem`: **(g)/(h) REPROVAM** (o texto está na trilha). **Colar a saída** — é a prova do vermelho do I27.
- [ ] **Step 2b: A redação D11** — acrescentar o laço de redação ao `tapActivity` (`array_key_exists`, **nunca** `isset`).
  > **Por que funciona ali (prova do consultor — pôr no comentário do método):** `ActivityLogger::log()` monta a Activity com `withProperties($event->changes)` e **só depois** chama o tap (`vendor/spatie/laravel-activitylog/src/ActivityLogger.php:172-175`), **antes** do `save()`. Mover a redação para outro hook = **no-op silencioso**.
- [ ] **Step 3: Verificar — CHECKPOINT 1 (raio global)** — `--filter=MensagemAutoriaColunas`, `--filter=AuditoriaMensagem` e **a suíte inteira** (`php artisan test`): a Task 1 altera o `Mensagem` globalmente e **40 arquivos de teste** o referenciam; a partir daqui toda `Mensagem::factory()` escreve em `activity_log`. **Rodar `php artisan migrate` no MySQL do dev** e conferir no Adminer que `medium_id` referencia **`users`**.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): colunas de autoria + trilha da Mensagem (redacao do corpo)`

---

## Task 2: Policy — os 5 métodos do eixo de autoria

**Files:** Modify `app/Policies/MensagemPolicy.php` (**+5**; os 6 existentes intactos) · Test `tests/Feature/Autorizacao/MensagemPolicyAutoriaTest.php`

- [ ] **Step 1: Testes que falham** — helper `usuario(?string $papel, array $setores = [], array $cargos = [])` (molde `MensagemVisibilidadeAcessoTest.php:26-43`), `setUp` com `EstruturaCemaSeeder`, sempre `Gate::forUser($naoAdmin)`:
  `lancar` (médium ✅ · frequentador ❌ · diretor-DEPAE não-médium ❌) · `curar` (diretor-DEPAE ✅ · presidente ✅ · médium comum ❌) · `editarPendente` (dono+pendente ✅ · dono+publicada ❌ · outro médium ❌ · não-médium ❌) · `editarNaCuradoria`/`publicar` (curador+pendente ✅ · **curador+publicada ❌** · médium comum ❌).
- [ ] **Step 2: Implementar** (§6.2; `publicar` delega a `editarNaCuradoria`).
- [ ] **Step 3: Verificar** `--filter=MensagemPolicyAutoria`.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): policy do eixo de autoria (lancar/curar/publicar)`

---

## Task 3: Serviços de domínio + o trait vira adaptador

**Files:** Create `app/Support/Mensagens/{SincronizadorDestinatarios,RegraPublicacao,SlugMensagem}.php` · Modify `app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php` · Test `tests/Unit/Mensagens/RegraPublicacaoTest.php`, `tests/Feature/Mensagens/SincronizadorDestinatariosTest.php`

**Interfaces:**
- **`RegraPublicacao::erros(array $dados): array`** (**V2** — devolve mensagens, **não lança**; molde `App\Support\Palestras\CardinalidadePalestra::erros`, que é testado em Unit **puro**). Quem lança é o componente: `throw ValidationException::withMessages(['data.nivel' => $erro])` — chave **com o statePath**.
- `SincronizadorDestinatarios::filtrarPorNivel(?string, array): array` (**CRU**) · `::sincronizar(Mensagem, array): void` · `::aplicar(Mensagem, ?string, array): void` (filtra contra `users` **existentes** e delega).
- **Contrato PRESERVADO no trait:** `protected array $idsDestinatarios`, `capturarDestinatarios(array): array` (**com** o `unset`), `aplicarDestinatarios(Mensagem): void`.

- [ ] **Step 1: Testes que falham** — `RegraPublicacaoTest` (Unit **puro**, `extends PHPUnit\Framework\TestCase`): `erros(['nivel' => null])` ⇒ 1 erro; `''` e `'lixo'` idem; `'publico'` ⇒ `[]`; `'direcionada'` **sem** destinatário ⇒ 1 erro; com ≥1 ⇒ `[]`.
  `SincronizadorDestinatariosTest` (Feature): `filtrarPorNivel('publico', [7,9]) === []`; `filtrarPorNivel(null, [7]) === []`; `filtrarPorNivel('direcionada', [7,9]) === [7,9]`; **`aplicar($m, 'direcionada', [$u->id, 99999])` ⇒ `assertSame([$u->id], …pluck('users.id')->all())`**; `aplicar` com nível ≠ direcionada ⇒ pivô **esvaziado**.
  > **⚠️ DUAS ARMADILHAS NOMEADAS.** (1) **O filtro contra `users` vive em `aplicar()`, NUNCA em `filtrarPorNivel()`**: o `MensagemDestinatariosGuardTest` da F4a assere `assertSame([7, 9], $r['ids'])` com ids **inexistentes** — filtrar ali o deixa **vermelho**, e "corrigir o teste da F4a" **violaria o I20**. (2) O vermelho de `aplicar` com id inexistente aparece como **`QueryException` de FK** (`mensagem_destinatario.user_id` é `constrained('users')`) — a correção é **filtrar**, **nunca** envolver em `try/catch`.
- [ ] **Step 2: Implementar** os 3 serviços; trait vira adaptador fino (delega, mantendo assinaturas **e a propriedade**).
- [ ] **Step 3: Verificar** `--filter=RegraPublicacao`, `--filter=SincronizadorDestinatarios` **e** `--filter=MensagemDestinatarios` (**16 verdes, zero arquivo de teste alterado**).
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): servicos de dominio das mensagens (sync/publicacao/slug)`

---

## Task 4: Extração LITERAL do `MensagemForm` (passo (i) — neutro, I20)

**Files:** Create `app/Filament/Schemas/MensagemForm.php` · Modify `app/Filament/Resources/Mensagens/MensagemResource.php`

- [ ] **Step 1: Sem teste novo.** O critério **são os 29 existentes** (16 da F4a em 4 arquivos + 13 do `MensagemResourceTest`).
- [ ] **Step 2: Implementar** — `schemaAdmin(): array` como **cópia campo a campo, sem uma vírgula de diferença** (as 5 Sections, na ordem), incluindo a Section "Destinatários" **inline** e o `->unique(ignoreRecord: true)` **como está hoje**. A const `NIVEIS` **fica no Resource**.
  > **⚠️ As "Regras da extração" 1-4 da SPEC §6.4 NÃO se aplicam a esta task.** A regra 1 (nomear a tabela no `unique`) entra na **Task 6**; as 2-4 valem só para as composições novas. Aqui, qualquer diferença **é** violação do critério.
- [ ] **Step 3: Verificar** `--filter=MensagemResourceTest` **e** `--filter=MensagemDestinatarios`: **29 verdes, zero arquivo de teste tocado**. Se algum exigir alteração, a extração não foi literal — desfazer e refazer.
- [ ] **Step 4: Commit** — `refactor(camada-4-fatia-f4b): extrai MensagemForm::schemaAdmin (neutro)`

---

## Task 5: As composições novas (**implementação-só** — V3)

**Files:** Modify `app/Filament/Schemas/MensagemForm.php`

**Sem Step de teste próprio:** as asserções vivem onde há componente real — **I22 na Task 7** e **I23 na Task 9**. *(O molde `AgendaDiaFormSchemaTest` só varre o topo do array e todo campo do `MensagemForm` está dentro de `Section`; descer exige `getContainer()`, que fatalha fora de um Livewire.)*

- [ ] **Step 1: Implementar** — `blocoDestinatarios(Closure $ehDirecionada): Section` + `schemaMedium()` + `schemaCuradoria()` (§6.4).
  - `schemaMedium`: título, contexto, corpo, formato, data, **`autores` — obrigatoriamente `Select::make('autores')->relationship('autores','nome')->multiple()`** (é o que dá sentido ao I5a), pictografia, **`Toggle::make('direcionar')->live()`**, bloco com predicado `fn (Get $g) => (bool) $g('direcionar')`.
  - `schemaCuradoria`: `schemaAdmin` **sem** slug, **sem** o Select `status`, **sem** `relacionadas`; nível com **`VisibilidadeMensagem::opcoes()`**.
  - **🚫 O `schemaAdmin` NÃO é refatorado para usar o bloco parametrizado** (mantém a Section inline da Task 4). Compartilhá-lo mudaria o `/admin` em silêncio: o bloco novo filtra `ativo` e **não tem** o `helperText` de hoje — e **nenhum** dos 29 testes pegaria (a `UserFactory` não seta `ativo`, cujo default é `true`).
- [ ] **Step 2: Verificar** `--filter=MensagemResourceTest` + `--filter=MensagemDestinatarios` (**os 29 seguem verdes** — nada do painel mudou).
- [ ] **Step 3: Commit** — `feat(camada-4-fatia-f4b): schemas do medium e da curadoria (bloco parametrizado)`

---

## Task 6: O `/admin` (passo (ii-b) — O2 + M3)

**Files:** Modify `app/Filament/Resources/Mensagens/MensagemResource.php` e `app/Filament/Schemas/MensagemForm.php` · Test `tests/Feature/Filament/MensagemAdminAutoriaNivelTest.php`

- [ ] **Step 1: Testes que falham** — (a) `assertFormFieldExists('nivel', fn (Select $f) => count($f->getOptions()) === 6 && array_key_exists('diretor-depae', $f->getOptions()))`; (b) na `ListMensagens`, `nivel='diretor-depae'` ⇒ "Diretor do DEPAE" e `nivel=null` ⇒ "— (sem nível)"; (c) **M3** `assertTableColumnExists('medium.name')`, linha com `medium_id` ⇒ **nome**, linha sem ⇒ **"Importada do legado"**.
- [ ] **Step 2: Implementar** (§6.4/ii-b) — enum no Select (**preservando `->live()`**), `rotulo()` na coluna, coluna `medium.name` **por relação** + `placeholder`, `helperText` corrigido, `->with('medium:id,name')` no `modifyQueryUsing`, e a regra 1 (`unique(table: 'mensagens', …)`).
- [ ] **Step 3: Verificar** `--filter=MensagemAdminAutoriaNivel`, `--filter=MensagemResourceTest` **e `--filter=MensagemDestinatarios`** (a task edita o Select `nivel` e a `table()` — exatamente o que os 16 da F4a travam).
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): /admin com os 6 niveis e a coluna 'Lancada por'`

---

## Task 7: A aba do médium — portão, rota, view e **criação** (o vermelho do G1)

**Files:** Create `app/Support/Conta/AbaMensagens.php`, `app/Livewire/Conta/MensagensConta.php`, `resources/views/conta/mensagens.blade.php`, `resources/views/livewire/conta/mensagens-conta.blade.php` · Modify `routes/web.php`, `app/Http/Controllers/ContaController.php`, `resources/views/components/conta/nav.blade.php`, `resources/css/filament/site/theme.css` · Test `tests/Feature/Conta/AbaMensagensTest.php`, `tests/Feature/Conta/MensagensContaCriarTest.php`

- [ ] **Step 1: Testes que falham** —
  - `AbaMensagensTest`: `visivelPara` true p/ médium, false p/ não-médium, **sem semear capacidade**.
  - **I1**: não-médium ⇒ `get(route('conta.mensagens'))->assertForbidden()` **e** `Livewire::actingAs($naoMedium)->test(MensagensConta::class)->assertForbidden()` (molde `AbaAgendaTest.php:112-118`); anônimo ⇒ `assertRedirect(route('login'))`; médium ⇒ 200 + nav com "Minhas Mensagens".
  - **I25**: `->assertSee('noindex, nofollow', false)`.
  - **I3**: criar ⇒ `pendente`, `medium_id`, `nivel` null, `publicado_*` null, slug gerado; **dois títulos iguais ⇒ slugs distintos**.
  - **I4**: `direcionar=true` + 2 destinatários ⇒ `nivel='direcionada'` + pivô; `false` ⇒ null + 0; `true` sem destinatário ⇒ `assertHasFormErrors(['destinatarios'])`.
  - **I5a (VERMELHO do G1)**: criar com 1 autor ⇒ `assertSame(1, DB::table('mensagem_autor_espiritual')->where('mensagem_id', $m->id)->count())`.
  - **Trava do I5a (não deixar o vermelho perder o sentido):** `assertFormFieldExists('autores', fn (Select $f) => $f->isMultiple() && ! $f->isDehydrated())` — se alguém trocar `->relationship()` por `->options()`, o pivô passa a gravar por outro caminho, o I5a fica verde **sem** a linha do G1 e a **pictografia quebra em produção sem cobertura**.
  - **ESCOPO DA LISTA (vazamento real, faltava):** `Mensagem::factory()->pendente()->create(['titulo' => 'PENDENTE-DE-OUTRO-MEDIUM', 'medium_id' => $outroMedium->id])` + a própria ⇒ `->assertSee('MINHA-PENDENTE')->assertDontSee('PENDENTE-DE-OUTRO-MEDIUM')`.
  - **I7 (V5 — o comportamento REAL):** `->set('data.direcionar', false)->set('data.destinatarios', [$u1->id])->call('salvar')` ⇒ `nivel` null e **0** pivô; e `direcionar=true` com id **inexistente** ⇒ `assertHasErrors(['data.destinatarios.0'])` + `assertSame(0, Mensagem::count())` *(o `Select` multiple injeta `Rule::in` automática — a prova do filtro server-side é a da Task 3)*.
  - **I22 (vindo da Task 5):** `assertFormFieldDoesNotExist` de `nivel`, `status`, `slug`, `link_arquivo`, `liberar_download`, `relacionadas`; `assertFormFieldExists` dos 9 do D6.
- [ ] **Step 2a: Implementar SEM a linha do G1** — tudo (aba, rota, controller, nav, view com os 3 slots, `@source`, componente com `salvar()`), **menos** `$this->form->model($mensagem)->saveRelationships()`. Rodar `--filter=MensagensContaCriar`: **I5a REPROVA** (0 linhas no pivô). **Colar a saída.**
- [ ] **Step 2b: Acrescentar a linha do G1** e ver verde.
- [ ] **Step 3: Verificar** `--filter=AbaMensagens`, `--filter=MensagensContaCriar`.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): aba Minhas Mensagens + lancamento pelo medium`

---

## Task 8: Edição pelo médium — **P1 + M2**, preservação e D10

**Files:** Modify `app/Livewire/Conta/MensagensConta.php`, `resources/views/livewire/conta/mensagens-conta.blade.php` · Test `tests/Feature/Conta/MensagensContaEditarTest.php` (`Storage::fake('public')` no `setUp`)

**A forma determinística (P1) — idêntica nos dois componentes:**

```php
public function editar(int $id): void
{
    $registro = Mensagem::findOrFail($id);
    $this->authorize('editarPendente', $registro);   // CuradoriaConta: 'editarNaCuradoria'

    $this->editandoId = $registro->id;
    $this->form->model($registro);   // ANTES do fill: não depende de quando o schema foi cacheado

    $this->form->fill([
        ...$registro->attributesToArray(),
        'direcionar' => $registro->nivel === VisibilidadeMensagem::Direcionada->value,   // M2 (virtual)
        'destinatarios' => $registro->destinatarios()->pluck('users.id')->all(),         // sem relationship
    ]);

    $this->mostrandoForm = true;
}
```

- [ ] **Step 1: Testes que falham** —
  - **I6/I5b (VERMELHO do M2 e do P1):** pendente do médium com **`nivel='direcionada'` + 2 destinatários + 1 autor + 1 mídia** (`addMediaFromString(PNG_1X1)`); `->call('editar', $m->id)->fillForm(['titulo' => 'Novo'])->call('salvar')`; assertar **quatro**: pivô intacto, 1 linha de autor, `getMedia()->count() === 1`, **`nivel` ainda `'direcionada'`**.
  - **I6 (negativos):** `call('editar', $publicadaPropria->id)` ⇒ 403; `call('editar', $pendenteDeOutro->id)` ⇒ 403.
  - **I26 (D10):** médium **trabalhador** autor de **publicada** `nivel='diretores'` ⇒ aba **200**, `assertSee` do título, **`assertDontSee` do trecho-sentinela do corpo** e **`assertDontSee(route('mensagens.show', $slug), false)`**; a mesma **pendente** ⇒ o autor **vê** o corpo no form.
  - **I18 (V1 — sobre o ESTADO, não sobre o HTML):**
    ```php
    $c = Livewire::actingAs($medium)->test(MensagensConta::class)->call('editar', $m->id);
    $this->assertArrayNotHasKey('medium_id', $c->get('data'));
    $this->assertArrayNotHasKey('publicado_por_id', $c->get('data'));
    $this->assertArrayNotHasKey('publicado_em', $c->get('data'));
    ```
    *(`assertDontSee` do Livewire **apaga o `wire:snapshot`** antes de comparar ⇒ passaria com ou sem `$hidden`.)*
- [ ] **Step 2a: Implementar `editar()` no padrão `AgendaConta`** (só `fill($registro->attributesToArray())`, `->model()` no `form()`). Rodar: **I6/I5b REPROVA** (nível vira null, pivô esvazia, mídia/autor somem). **Colar a saída.**
- [ ] **Step 2b: Aplicar o bloco P1 + M2** acima. 🚫 **Rejeitar as duas correções erradas:** hidratar `autores`/`pictografia` em `$this->data` (tela exibe, não grava) e afrouxar o guard para preservar o pivô vazio (reabre o furo da F4a).
- [ ] **Step 3: Verificar** `--filter=MensagensConta`.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): edicao da pendente pelo medium (ancora do schema + hidratacao)`

---

## Task 9: A aba da curadoria — portão, fila e **Salvar**

**Files:** Create `app/Support/Conta/AbaCuradoria.php`, `app/Livewire/Conta/CuradoriaConta.php`, `resources/views/conta/curadoria.blade.php`, `resources/views/livewire/conta/curadoria-conta.blade.php` · Modify `routes/web.php`, `ContaController`, `nav.blade.php` · Test `tests/Feature/Conta/AbaCuradoriaTest.php`, `tests/Feature/Conta/CuradoriaContaTest.php`

- [ ] **Step 1: Testes que falham** —
  - **I2**: médium comum ⇒ 403 (rota **e** `mount`); diretor-DEPAE ⇒ 200; presidente ⇒ 200; **admin puro ⇒ 403** (decisão §6.3); anônimo ⇒ redirect. **I25**: `noindex`.
  - **FILA — só PENDENTES (faltava):** `Mensagem::factory()->publicada()->comNivel('publico')->create(['titulo' => 'JA-PUBLICADA-NAO-ENTRA'])` + uma pendente ⇒ `->assertSee('NA-FILA')->assertDontSee('JA-PUBLICADA-NAO-ENTRA')`.
  - **I25 (fila)**: pendente com `medium_id=null, nivel=null` ⇒ **200** + `assertSee('Importada do legado')`; pendente de um médium ⇒ `assertSee($medium->name)`.
  - **VERMELHO da task (V4 — o furo B4):** `->call('editar', $publicada->id)` ⇒ `assertForbidden()`; `->call('publicar', $publicada->id)` ⇒ `assertForbidden()`. *(Discrimina contra a implementação natural `authorize('curar', Mensagem::class)`.)*
  - **I11 (trava de contrato, NÃO prova do vermelho):** `->set('data.status', 'publicado')->call('salvar')` ⇒ continua `pendente` — comentar no teste que ele prova a **poda do `getState()`**, já que o Select `status` não existe no `schemaCuradoria`.
  - **I12**: altera título+corpo+nível e salva ⇒ persistido e **`pendente`**; **+ teste-contrato de ausência**: `foreach (['excluir','despublicar','devolver'] as $m) assertFalse(method_exists(CuradoriaConta::class, $m))` (idem `MensagensConta`) e todas as rotas `conta.*` são **só GET**.
  - **I23 (vindo da Task 5):** `assertFormFieldExists('nivel', fn (Select $f) => count($f->getOptions()) === 6 && array_key_exists('diretor-depae', $f->getOptions()))`.
  - **I18 (V1)**: `assertArrayNotHasKey` nos 3 campos, após `call('editar', …)`.
- [ ] **Step 2: Implementar** — `AbaCuradoria`, rota, controller, nav, view com os 3 slots, componente (fila `->where('status', PENDENTE)->with('medium:id,name','autores')`, `editar`/`salvar` autorizando **`editarNaCuradoria`**, `status` reasserido em `pendente`).
- [ ] **Step 3: Verificar** `--filter=AbaCuradoria`, `--filter=CuradoriaConta`.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): aba de curadoria com a fila de pendentes`

---

## Task 10: O martelo — `publicar()` + `RegraPublicacao`

**Files:** Modify `app/Livewire/Conta/CuradoriaConta.php`, `resources/views/livewire/conta/curadoria-conta.blade.php` · Test `tests/Feature/Conta/CuradoriaPublicarTest.php`

- [ ] **Step 1: Testes que falham** —
  - **I8**: publicar ⇒ `status`, `publicado_por_id`, `publicado_em`, **`medium_id` inalterado**.
  - **I9 (VERMELHO)**: publicar com `nivel` null / `''` / `'lixo-invalido'` ⇒ `assertHasFormErrors(['nivel'])` (a chave gravada é **`data.nivel`**) e **continua pendente**; um caso usa pendente **legada** (`medium_id` null).
  - **I9 — a asserção que morde o "getState dentro da transação":** pendente com autor **A**; `->call('editar', $m->id)->fillForm(['autores' => [$autorB->id], 'nivel' => null])->call('publicar', $m->id)` ⇒ após a recusa, `assertSame([$autorA->id], $m->fresh()->autores->pluck('id')->all())`. *(Com o `getState()` **fora** da transação o pivô já estaria em B — vermelho.)*
  - **I9-direcionada**: publicar `direcionada` **sem** destinatário ⇒ recusa.
  - **I10 (sem ambiguidade)**: **pendente** `direcionada` com 2 destinatários ⇒ `->call('editar', $m->id)->fillForm(['nivel' => 'trabalhadores'])->call('publicar', $m->id)` ⇒ `status='publicado'` e **0** linhas de pivô.
  - **I13 (D3)**: persona médium + diretor-DEPAE, não-admin, **não-presidente**, publica a **própria** ⇒ permitido; `Activity` `updated` com `causer_id` = ele.
- [ ] **Step 2: Implementar** — `publicar()` com `authorize('publicar', $registro)` e **uma** `DB::transaction` envolvendo `getState` → `RegraPublicacao::erros()` → (se houver) `throw ValidationException::withMessages(['data.nivel' => …])` → `update` → `sync`.
- [ ] **Step 3: Verificar** `--filter=CuradoriaPublicar` **e `--filter=CuradoriaConta`** (a task edita o componente da Task 9).
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): o martelo do DEPAE (publicar com nivel obrigatorio)`

---

## Task 11: O histórico (primeiro leitor de trilha do projeto)

**Files:** Create `app/Support/Mensagens/{HistoricoMensagem,GlossarioCamposMensagem}.php`, `resources/views/components/conta/historico-mensagem.blade.php` · Modify `app/Livewire/Conta/CuradoriaConta.php`, `resources/views/livewire/conta/curadoria-conta.blade.php` · Test `tests/Feature/Conta/HistoricoMensagemTest.php`

**Interfaces:** `HistoricoMensagem::linhas(Mensagem, int $limite = 20): array` · `::editadasPeloAutor(Collection): array`. **R4:** o `->with('causer')` traz o `User` inteiro — a view mostra **só `->name`**; não passar o objeto a nada que serialize.

- [ ] **Step 1: Testes que falham** —
  - **I15 — renderizador ISOLADO, com a linha suja INJETADA à mão** (senão as negativas são vacuosas: depois da D11 a trilha nunca contém o corpo):
    ```php
    activity()->useLog('mensagem')->performedOn($m)
        ->withProperties(['attributes' => ['corpo' => 'SENTINELA-VAZAMENTO-XYZ', 'titulo' => 'T'],
                          'old' => ['corpo' => 'SENTINELA-ANTIGO-XYZ'],
                          'porta' => 'perfil', 'ip' => '127.0.0.1', 'user_agent' => 'Symfony'])
        ->event('updated')->log('mensagem atualizada');

    $this->blade('<x-conta.historico-mensagem :mensagem="$m" />', ['m' => $m])
        ->assertSee('Corpo da mensagem')
        ->assertDontSee('SENTINELA-VAZAMENTO-XYZ')->assertDontSee('SENTINELA-ANTIGO-XYZ')
        ->assertDontSee('user_agent', false)->assertDontSee('attributes', false);
    ```
    (`$this->blade()` existe e o projeto já usa — `tests/Feature/Front/PalestranteCardTest.php:19`.) Idem injetando `['diff' => ['adicionados' => ['Fulano Sigiloso']]]` ⇒ o nome **não** aparece.
  - Unidade: `created` (sem `old`), `updated` (união de chaves), campo fora da lista branca não aparece, entrada manual não quebra, `causer` null ⇒ **"Sistema"**.
  - **Morph (B3) — com id FORÇADO, não por sorte:** `DB::table('activity_log')->insert([... 'subject_type' => (new User)->getMorphClass(), 'subject_id' => $m->id, 'event' => 'updated', 'causer_id' => $m->medium_id, 'properties' => '{}' ...])` ⇒ `linhas($m)` **não** inclui essa entrada e `editadasPeloAutor` **não** marca o id.
  - **I16**: recém-criada não marcada; `updated` do **curador** não marca; `updated` do **médium autor** marca; **legada** (`medium_id` null) nunca marca.
  - **I24**: direcionada com 2 destinatários (um com **nome-sentinela**) ⇒ nenhuma entrada de `log_name='mensagem'` com `destinatario`/`medium_id`/`publicado_por_id` **nem com o nome-sentinela**.
  - **R3**: com 21+ entradas, a tela diz "mostrando as 20 mais recentes".
- [ ] **Step 2: Implementar** (§6.8) — query com `subject_type` **e** `log_name`, `->with('causer')`, `latest('id')`, `limit(20)`; união de chaves com `array_key_exists`; rótulos pela lista branca; **proibido** imprimir valor.
- [ ] **Step 3: Verificar** `--filter=HistoricoMensagem`, `--filter=CuradoriaConta`, `--filter=CuradoriaPublicar`.
- [ ] **Step 4: Commit** — `feat(camada-4-fatia-f4b): historico do item na curadoria (campos, nunca valores)`

---

## Task 12: Porta `'perfil'`, importador, não-vazamento e fechamento

**Files:** Test `tests/Feature/Autorizacao/AuditoriaMensagemPortaTest.php`, `tests/Feature/Front/MensagemAutoriaNaoVazaTest.php`, `tests/Feature/Importacao/ImportadorMensagensTest.php` (**+1 teste**, sem tocar os existentes)

- [ ] **Step 1: Testes que falham** —
  - **I14 (arranjo discriminante)**, nos **dois** componentes:
    ```php
    $c = Livewire::actingAs($medium)->test(MensagensConta::class);   // mount/boot marcou
    AuditoriaAutorizacao::usarPorta(null);                            // simula o processo novo do wire:click
    $c->call('novo')->fillForm([...])->call('salvar')->assertHasNoFormErrors();
    $this->assertSame('perfil', Activity::where('log_name','mensagem')->latest('id')->first()->properties['porta']);
    ```
    **MUTAÇÃO OBRIGATÓRIA** (o `boot()` já existe desde as Tasks 7/9 ⇒ o teste nasceria verde): mover `usarPorta` para `mount()` nos dois, rodar `--filter=AuditoriaMensagemPorta`, **colar a falha** (`sistema ≠ perfil`) e reverter. Nenhum teste novo sobrescreve `tearDown()` sem `parent::tearDown()`.
  - **I17**: importar o lote, `Activity::query()->delete()`, importar **o mesmo lote** ⇒ `Activity::where('log_name','mensagem')->count() === 0`. **Fixture que morde**: ≥1 item com `link_arquivo` bruto do legado (com `&amp;`, molde `ImportadorMensagensTest.php:175`), um `corpo` que o `clean()` **reescreva** e `data_recebimento` em unix.
  - **I18 (trava de regressão)**: nomes-sentinela; `assertDontSee` do nome e das 3 chaves em single (anônimo e logado), lista (`get`), sitemap, perfil do autor e `/minha-conta/direcionadas`. *(A prova que morde é a das Tasks 8/9, sobre o estado.)*
  - **I19**: pendente criada pelo fluxo novo ⇒ fora da lista, **404** no single, fora do sitemap e das Direcionadas; **publicada** com nível `publico` ⇒ **aparece**.
- [ ] **Step 2: Implementar** — só o que os testes exigirem (o esperado é **nada** de produção; se faltar algo, é bug de task anterior).
- [ ] **Step 3: Verificar — CHECKPOINT 2 (final):**
  - `docker compose exec -T app php artisan test`: **1097 + novos**, verde, **zero teste existente alterado**.
  - `docker compose exec -T app vendor/bin/pint` limpo.
  - `npm run build` **no host** + `optimize:clear` + `docker compose restart app worker`.
  - **Prova no navegador (obrigatória):** como médium real, criar mensagem **com imagem e autor** e conferir no `/admin` que **gravaram**; **editar e conferir que a imagem e o autor SOBREVIVERAM** (prova visual do P1); conferir a **grade** do form (prova do `@source`); como Charles, curar → publicar; **abrir o histórico: rótulos em pt-BR e NENHUM texto** (validação visual do D11 — a suíte prova o dado, não a renderização); uma das **47 legadas recusa** publicação sem nível; usuário sem setor médium ⇒ sem aba e 403.
- [ ] **Step 4: Commit** — `test(camada-4-fatia-f4b): porta perfil, importador e nao-vazamento da autoria`

---

## Task 13: Documentação (commit separado, molde 3B/3C)

**Files:** Modify `DATA-MODEL.md`, `ROADMAP.md`

- [ ] **Step 1:** `DATA-MODEL.md` — as 3 colunas de autoria em `mensagens`; `log_name='mensagem'` na seção de `activity_log`; e **revogar a decisão de 25/jun** ([:365-366](../../../DATA-MODEL.md#L365-L366): `mensagens.publicar` **pela matriz**), superada pelo **D4** (eixo por cargo/setor, via policy).
- [ ] **Step 2:** `ROADMAP.md` — marcar "Mensagens mediúnicas + Autores espirituais".
- [ ] **Step 3: Commit + PR** — `docs(camada-4-fatia-f4b): autoria e trilha da Mensagem no DATA-MODEL`; abrir o PR único, aguardar **CI verde no ÚLTIMO commit** ([[merge-so-com-ci-verde-no-commit-final]]) e o **passe do PR** antes do merge.

---

## Cobertura dos invariantes (rastreabilidade)

| Invariante | Task | Invariante | Task |
|---|---|---|---|
| I1 (portão médium) | 7 | I15 (histórico isolado) | 11 |
| I2 (portão curador + admin 403) | 9 | I16 (editada pelo autor) | 11 |
| I3 (nascimento) | 7 | I17 (re-import) | 12 |
| I4 (switch direcionar) | 7 | I18 (autoria fora do front) | 8, 9 (estado) · 12 (regressão) |
| **I5a (G1 — autores)** | **7** | I19 (3B/3C intactas) | 12 |
| I5b (mídia sobrevive) | 8 | I20 (extração neutra) | 4 |
| **I6 (posse + M2 + P1)** | **8** | I21 (FK → `users`) | 1 |
| I7 (POST não confiável) | 7 (UI) · 3 (filtro) | I22 (D6 — campos do médium) | 7 |
| I8 (martelo) | 10 | I23 (6 níveis) | 6 (admin) · **9 (curadoria)** |
| I9 (publicar exige nível) | 10 | I24 (PII na trilha) | 11 |
| I10 (troca esvazia pivô) | 3, 10 | I25 (noindex + legado + null) | 7, 9 |
| I11 (salvar não publica) | 9 (trava) | **I26 (D10)** | **8** |
| I12 (curador edita pendente + sem excluir) | 9 | **I27 (D11)** | **1** |
| I13 (D3) | 10 | I-reg (suíte + Pint + migrate) | 1 (chk 1) · 12 (chk 2) |
| I14 (porta `'perfil'`) | 12 | Escopo da lista / fila | 7 / 9 |

**Provas do vermelho (cada uma vista REPROVAR, com a saída colada no relato):**
1. **I27** — Task 1, Step 2a (trilha sem redação ⇒ o texto vaza).
2. **I5a** — Task 7, Step 2a (sem `saveRelationships` ⇒ 0 linhas de pivô).
3. **I6/M2/P1** — Task 8, Step 2a (sem âncora + hidratação ⇒ nível null, pivô e mídia perdidos).
4. **B4** — Task 9 (`editar`/`publicar` de publicada ⇒ 403 exige `editarNaCuradoria`).
5. **I9** — Task 10 (sem `RegraPublicacao` publica sem nível; e o pivô de autores denuncia o `getState` fora da transação).
6. **I14** — Task 12 (mutação: `usarPorta` em `mount()`).

**Não são provas do vermelho (travas de contrato, documentar como tal):** I11 (poda do `getState`), I18 no GET, o teste-contrato de métodos ausentes, I19.
