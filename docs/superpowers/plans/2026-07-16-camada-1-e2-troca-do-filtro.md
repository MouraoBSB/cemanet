# Camada 1 · E2 — A troca do filtro

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trocar o eixo do filtro das policies — de "o objeto está num departamento em comum comigo" (por objeto) para "eu estou num departamento responsável pelo TIPO" (configurado na tela do E1) — mantendo o **Evento** byte a byte no regime antigo.

**Architecture:** O serviço `AcessoPorTipo` (entregue pelo E1, **hoje com zero consumidores**) passa a ser lido em **três** pontos, e só neles: o trait das policies (`ver`/`editar`/`excluir` + o `criar`), o scope `AgendaDia::scopeNoEscopoDe` e a `AbaAgenda`. O campo "Departamentos" sai dos forms dos 4 tipos "do tipo"; o pivô `departamento_<x>` fica **congelado** (nem lido, nem gravado, **nada apagado**). O `AgendaMantenedores` hardcoded é deletado.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-activitylog · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md`](../specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md) (aprovado) — §9.4 as peças, §7 os invariantes (I1..I9), §10.3 os testes, §10.4 os que mudam de cor, §12.8 a armadilha do fallback, §13 a ciência. **Não há SPEC novo.**

**Plano anterior:** [`2026-07-16-camada-1-e1-fundacao.md`](2026-07-16-camada-1-e1-fundacao.md) — E1 **mesclado** (PR #32, `5a6a9ba`).

---

## Estado verificado no código real (16/07, `main` = `5a6a9ba`)

Levantamento por leitura direta + passe adversarial **antes** de escrever este plano. Os números valem sobre `5a6a9ba` e **deslocam** conforme as edições são aplicadas — **ancorar por assinatura, conferir pelo número**.

- **E1 não vazou:** `grep -rn "AcessoPorTipo" app/` = **exatamente 3 linhas** (classe `AcessoPorTipo.php:23`, import `AppServiceProvider.php:19`, binding `scoped` `:46`). **Zero consumidores de decisão.**
- **O trait tem 1 método**, `protected objetoNoDepartamentoDoUsuario` (`AutorizaPorDepartamento.php:16`), com **15 call sites** (5 policies × ver/editar/excluir) e **zero** referências em `tests/` — não há teste unitário do trait; a reescrita só é coberta **indiretamente** pelos testes de policy.
- **`criar` não usa o trait**: as 5 policies duplicam `$user->departamentos()->exists()` inline (Evento`:41`, AgendaDia`:26`, Palestra`:27`, Palestrante`:28`, Post`:26`). É o furo do I3.
- **`noEscopoDe` tem 2 chamadores de produção**: `AbaAgenda:41` e `AgendaConta:223`. **`AgendaMantenedores` tem 1**: `AgendaConta:182` (+ `AbaAgendaTest:44`).
- **`AcessoPorTipo` é fail-closed**: `null => false` explícito (`:47`), sem `??` que abra.

### 🚩 Divergências entre o SPEC e o código (o plano segue o código, não o SPEC)

**Duas são erros do SPEC; uma é defasagem — e a distinção importa.**

| # | Tipo | O SPEC diz | O código diz | Onde isso morde |
|---|---|---|---|---|
| 1 | **erro** | §6.6: mantenha o helper `registrarDepartamentosConteudo` porque "**o Evento ainda usa esse eixo**" | **Falso desde sempre.** `grep -rn "registrarDepartamentosConteudo" app/` = **2 linhas**: a declaração (`AuditoriaAutorizacao:98`) e o **único** chamador (`AgendaConta:187`). O Evento **não** usa. Depois da Task 4 o helper fica com **zero chamadores**. | Task 4. Mantê-lo é decisão consciente (o eixo volta na Camada 4) — mas **sem alegar um chamador que não existe**. |
| 2 | **erro** | §10.4: `ConteudosTemDepartamentoTest` "**Muda**" | **Não muda** — e é **proibido mexer** (Armadilha 7). | Task 5. |
| 3 | **defasagem** | §3.3: o `Gate::before` está em `AppServiceProvider.php:57-59` | Está na **`:65`**. | Só citações. Não muda comportamento. |

> ⚠️ **O item 3 NÃO é erro do SPEC — ele estava certo quando escrito.** O **E1** somou 6 linhas ao `register()` (o binding `scoped` do `AcessoPorTipo`) e empurrou o `boot()` para baixo. Chamar isso de "erro" mandaria o próximo leitor desconfiar do SPEC inteiro, e a lição é outra: **citação `arquivo:linha` envelhece a cada PR**. É exatamente a razão da constraint "ancorar por assinatura, conferir pelo número" — que vale para este plano tanto quanto para o SPEC.

---

## Os 5 aceites — cada um é um COMANDO com veredicto objetivo

Rodar todos na Task 6. Qualquer um vermelho **reprova o PR**.

### (a) O Evento saiu intacto — só o `setUp`

```bash
git diff -U0 origin/main -- tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php \
  | grep -E '^[-+]' | grep -vE '^(\+\+\+|---)'
```

**Esperado — exatamente estas 2 linhas, nada mais, nenhuma começando com `-`:**

```
+use Database\Seeders\TiposConteudoSeeder;
+        $this->seed(TiposConteudoSeeder::class);
```

Este comando prova **conteúdo**, não contagem. **Zero linha `-` = nenhuma asserção foi editada nem apagada. Exatamente estas 2 adições = nada além do `setUp` entrou.** É o aceite mais forte do PR, e quando falha ele **mostra a linha ofensora**.

> ⚠️ **Por que não `--numstat`.** Testado empiricamente contra o arquivo real: `--numstat` dá `2	0` (verde) **também** quando o executor troca o import por FQN e gasta a linha livre numa asserção adulterada. Contagem não é conteúdo. O `--numstat` fica como **conferência barata** ao lado, não como o gate:
> ```bash
> git diff --numstat origin/main -- tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php
> ```
> Esperado: `2	0	tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php`.

### (b) Os 4 tipos negam quem não é responsável — os 3 estados do pivô

```bash
docker compose exec -T app php artisan test --filter=CamadaUmFiltroPorTipoTest
```

**Esperado:** PASS (13 testes). Cobre I1/I2/I3/I4/I6/I9 e **os 3 estados do pivô** (§7/I9): **vazio** (`test_i9_...`), **disjunto do usuário** (`test_disjunto_nega` e `test_pivo_ignorado_...`) e **coincidente com o usuário** (`test_pivo_nao_abre_...`). **Todo caso do "do tipo" fixa o pivô explicitamente** — sem isso o teste não reprova nada (Armadilha 2).

### (c) `AgendaMantenedores` deletado — inclusive na doc

```bash
test ! -f app/Support/Agenda/AgendaMantenedores.php && echo "OK: arquivo não existe"
grep -rn "AgendaMantenedores" app/ tests/ DATA-MODEL.md ROADMAP.md || echo "OK: nenhuma referência"
```

**Esperado:** os dois OK.

> ⚠️ **`app/ tests/` sozinho não basta:** `DATA-MODEL.md:472` cita a classe. Foi assim que o E1 deixou doc podre (hoje `grep -n "tipos_conteudo\|RegimeAcesso" DATA-MODEL.md ROADMAP.md` = **zero hits**, e o E1 mesclou mesmo assim). A Task 6, Passo 3 corrige a doc **antes** deste aceite rodar.

### (d) Nenhum fallback de `null` (§12.8)

```bash
grep -rnE '\?\?\s*RegimeAcesso|\?->regime\s*\?\?|regime\([^)]*\)\s*\?\?' app/ || echo "OK: nenhum ?? de regime"
grep -rn "null =>" app/Policies/Concerns/AutorizaPorDepartamento.php app/Support/Autorizacao/AcessoPorTipo.php app/Models/AgendaDia.php
```

**Esperado:** o 1º comando **vazio** (OK). O 2º: **todo** `null =>` resolve para `false` ou `$query->whereRaw('1 = 0')` — **nenhum** para `objetoNoDepartamentoDoUsuario`, regime default ou `departamentos()->exists()`.

### (e) O pivô não autoriza mais nos 4 tipos

```bash
grep -rn "objetoNoDepartamentoDoUsuario" app/
```

**Esperado: exatamente 2 linhas** — a **declaração** no trait e **um único uso**, dentro do braço `RegimeAcesso::PorRegistro` do `match` de `noEscopo()`. (Hoje são **16**: 1 declaração + 15 call sites.) Se qualquer policy o chamar direto, **o congelamento é ficção**.

**Reforço:** o método passa de `protected` a **`private`** no trait — documenta e estreita a intenção; o `grep` é o guarda real.

---

## Armadilhas — leia ANTES da Task 1

### 🚨 1. BLOQUEADOR — `CapacidadeConteudosTest` não aceita a receita "semeie o teste"

**Descobrir isso na execução custaria horas e induziria exatamente ao erro que o §12.8 proíbe.**

`tests/Feature/Autorizacao/CapacidadeConteudosTest.php` **não roda `EstruturaCemaSeeder`** — o `setUp` (`:29-34`) só faz `Role::findOrCreate('administrador')` + `CapacidadesSeeder`. Os departamentos nascem **ad hoc dentro de cada método**, pelo helper `depto()` (`:66-69`, `Departamento::create`), em 9 call sites (`:90, 102, 103, 115, 128, 141, 154, 165, 176`).

Somar `$this->seed(TiposConteudoSeeder::class)` ao `setUp` roda o seeder **com a tabela `departamentos` vazia**:

1. `TiposConteudoSeeder::idsPorSigla()` (`:57-63`) faz `Departamento::whereIn('sigla', ...)->pluck(...)` ⇒ devolve `[]` — **sem lançar**;
2. `sync([])` (`:46`) ⇒ os 5 tipos nascem com **zero responsáveis**;
3. o **insert-only** (`:45`, `wasRecentlyCreated`) **congela** — resemear **não repara**;
4. I1 nega tudo ⇒ `:95`, `:158`, `:170` vermelhas **com cara de bug de autorização**.

**É a isca perfeita do §12.8.** **Vermelho aqui NÃO é bug da policy — é `setUp` mal montado.** As 3 correções (Task 1, Passo 3) são todas obrigatórias:

- **(i) Ordem:** `EstruturaCemaSeeder` **antes** de `TiposConteudoSeeder`.
- **(ii) Helper:** `depto()` **precisa** deixar de fazer `Departamento::create` e resolver por sigla — `departamentos` tem `UNIQUE` em `sigla` **e** `slug` (`create_departamentos_table.php:13,15`), e os dois seeders geram `DED`/`ded`. **Sem isto, (i) troca "vermelho silencioso" por `QueryException` em 8 métodos.**
- **(iii) Siglas:** o teste usa **DED nos 4 recursos**, mas a semente é `post ⇒ DECOM`. 🚫 **Proibido** semear `post ⇒ DED` para simplificar (divergiria de §4.2/§9.1 e mataria a cobertura).

> ✅ **O bloqueador está isolado neste arquivo.** Os 5 testes de `/minha-conta` e o `CapacidadeViaPapelTest:25` **já semeiam `EstruturaCemaSeeder`** ⇒ somar `TiposConteudoSeeder` depois é seguro.

> 🔭 **Follow-up conhecido (A1) — NÃO fazer no E2, é dívida do E1 e decisão do dono.**
> A falha silenciosa que arma esta armadilha **não é só de teste: é risco de PRODUÇÃO**, e está na `main`
> desde o E1. `TiposConteudoSeeder:57-63` devolve `[]` quando a sigla **não existe**, sem lançar; com o
> insert-only (`:45`), o tipo nasce sem responsáveis e **resemear nunca repara** — só a tela. O §13.1 manda
> rodar esse seeder como **passo obrigatório do cutover de PROD**: rodado antes de os departamentos
> existirem — ou depois de alguém **renomear uma sigla** no `/admin` — o cutover grava fail-closed em
> silêncio. Sintoma em prod: *"ninguém consegue editar nada"*, sem um erro no log.
> O seeder já tem o padrão certo para o caso irmão (recurso sem semente ⇒ `RuntimeException`, `:38`): sigla
> ausente merece o mesmo — **o seeder é o lugar de explodir, não a autorização**. São ~4 linhas + 1 teste.
> 🚫 **Não implementar aqui e não dobrar no E2.** É PR próprio, antes ou depois. **Até lá, esta Armadilha 1
> é a proteção — e ela basta.** (Com a guarda, ela viraria erro explícito em vez de exigir disciplina.)

### ⚠️ 2. Todo caso do "do tipo" DEVE fixar o pivô do objeto

Sem isso o teste **não reprova nada**: um híbrido (`habilitado && objetoNoDepartamento...`) ou a variante permissiva (`||`) passariam **verdes**. A `AgendaDiaFactory` **não** anexa departamento — o `->departamentos()->sync()` é **explícito no arrange**.

### ⚠️ 3. Dois memos, dois remédios — NÃO espalhar `fresh()` por reflexo

**(1) `AbaAgenda::$cache`** (`:26`, `private static ?WeakMap`) é chaveado pelo **objeto** `User` (`:32`) e sobrevive dentro do mesmo teste. Só engana um caso que consulte a aba, **mute config/vínculo e reconsulte com o mesmo `$user`** — aí, e **só aí**, use `$user->fresh()`.

🚫 **Nenhum caso novo deste plano tem esse formato ⇒ escrevê-los SEM `fresh()`.** Os casos de aba chamam `visivelPara` **uma única vez**, sem mutação anterior; `test_scope_do_tipo_e_tudo_ou_nada` nem passa por `AbaAgenda`; e `test_regime_do_tipo_ignora_o_pivo_do_objeto` vai por `Gate` → policy → trait, sem WeakMap, com a única mutação (`givePermissionTo` no papel) **antes de o usuário existir**.

**Prova empírica:** `CapacidadeViaPapelTest:73,85,89` têm exatamente essa forma e estão **verdes hoje, sem `fresh()`**.

**O `CapacidadeViaPapelTest:54,58,62` NÃO é precedente do WeakMap** — aquele arquivo nunca toca `AbaAgenda`. Ali o `fresh()` é necessário porque `:57`/`:61` mutam o **papel** ENTRE as checagens: é cache de permission do spatie na instância, **outro mecanismo**. Por isso a Task 1 **mantém** o `fresh()` lá.

**(2) `AcessoPorTipo::$memo`** (`:26`) é chaveado por **recurso** (`:67`), **não por User** — **`$user->fresh()` NÃO o invalida**. **É este o vetor real de staleness do E2.** Regra: semear/`configurar()` **ANTES** da primeira checagem. Se um caso precisar TROCAR a config no meio, use **`app()->forgetScopedInstances()`** (binding `scoped`, `AppServiceProvider:46`) — **nunca** `fresh()`.

### 🚫 4. NÃO adicionar `match` a `podeCriarNoEscopo`

`AcessoPorTipo::usuarioHabilitadoNoTipo` (`:39-49`) **já ramifica por regime**:

```php
return match ($this->regime($recurso)) {
    RegimeAcesso::DoTipo => $this->usuarioResponsavel($user, $recurso),
    RegimeAcesso::PorRegistro => $user->departamentos()->exists(),   // I4: inalterado
    null => false,                                                   // I1/I2
};
```

Logo `podeCriarNoEscopo` = **uma linha** já entrega: Evento ⇒ o comportamento de hoje (**I4**); os 4 "do tipo" ⇒ responsável (**I3**); sem linha ⇒ `false` (**I2**). Duplicar o `match` criaria uma **segunda implementação da pergunta única** — o que §6.2 existe para impedir.

### ⚠️ 5. `AgendaDiaForm`: o bloco é `:64-73`, **não** `:64-72`

`:64` é o `if ($comDepartamentos) {` e **`:73` é o `}`**. Cortar em `:72` deixa **chave órfã ⇒ Parse error**. E **o import `use Filament\Forms\Components\Select;` FICA** — segue em uso na `:36` (`Select::make('status')`).

### ⚠️ 6. Os números do §10.2/§10.4 do SPEC são **pré-E1** — e um está errado

O E1 somou 2 linhas ao `MatrizCapacidadesTest` (`use` `:13` + `seed` `:29`), deslocando o arquivo:

- **`MatrizCapacidadesTest` NÃO cai em I2** — **já semeia** `TiposConteudoSeeder` (`:29`). **Semear de novo é no-op.** A causa é **disjunta**: diretor em **DECOM** (`:104`, via `$decom` de `:95`) vs `palestra ⇒ DED`. **Conserto: a sigla da `:95`.**
- **O SPEC erra o número:** diz `:107`, mas `:107` é o `sync` do pivô da palestra (que o "do tipo" **ignora**). **Quem fica vermelho é o `assertTrue` da `:109`.**
- ⇒ `CapacidadeViaPapelTest` e `MatrizCapacidadesTest` **não** pertencem ao mesmo grupo causal. A frase do SPEC "Nos dois, o conserto é semear e ajustar os vínculos" está **sobre-prescrita**.

### ⚠️ 7. `ConteudosTemDepartamentoTest` **NÃO muda** — e é proibido mexer

O SPEC o lista em "**Muda:**" (§10.4:1067). **Está errado.** O arquivo (54 linhas, sem `setUp`, sem `seed`, sem `Gate::`) mede o que §6.4 manda **preservar**: o contrato (`:37`), a existência da pivô (`:38`) e a integridade da relação (`:48-52`). O deny de I2 **não o alcança**.

🚫 **Proibido "consertar".** **Se ficar vermelho em E2, o vermelho é o bug** — E2 removeu tabela/relação/contrato e violou §6.4. Investigar o **código**, nunca o teste.

> Ele e o `test_regime_do_tipo_ignora_o_pivo_do_objeto` são guardiões de **eixos diferentes**: um prova que o pivô **continua existindo e íntegro**; o outro, que **ninguém o lê para autorizar**.

### ⚠️ 8. O import `Departamento` do `AgendaConta` — o **Pint não pega**

Removendo o bloco do sync, dois imports ficam órfãos:
- `:10` `use App\Support\Agenda\AgendaMantenedores;` — **o Pint pega** e aborta o CI;
- `:9` `use App\Models\Departamento;` — **o Pint NÃO pega.** O `no_unused_imports` varre comentários com regex de nome curto, e os comentários pt-BR de `:24`, `:100` e `:110` contêm "departamento" ⇒ casam ⇒ contam como uso. **Verificado empiricamente.** **Remover à mão.**

`AuditoriaAutorizacao` (`:11`) **fica** (usado em `:52`). `QueryException` (`:16`) **fica** (`:140`, `:188`).

### ⚠️ 9. `AgendaDiaFormSchemaTest` quebra por **Error fatal**, não por asserção

O grep do §10.4 (`check('ver'|...`) **não encontra este arquivo** — ele testa forma de schema. Ponto cego. Os 2 testes caem: `:21-24` (`assertTrue` do Select) por definição; `:26-34` usa o argumento **nomeado** `comDepartamentos:` ⇒ **`Error: Unknown named parameter`**. **Decisão: reescrever** como guarda anti-regressão (Task 5).

### 🚨 10. `Evento` **NÃO TEM FACTORY** — criar à mão

**Não existe `database/factories/EventoFactory.php`** (verificado: a pasta tem 10 arquivos, nenhum do Evento; `grep -rn "EventoFactory"` sem vendor = **zero**; sem `newFactory()`, sem resolver custom). `Evento` usa o trait `HasFactory` (`Evento.php:24`), então `Evento::factory()` cai em `Factory::factoryForModel()` e estoura **`Error: Class "Database\Factories\EventoFactory" not found`** — **fatal, não asserção**.

**O projeto inteiro já contorna:** os 16 arquivos que criam Evento usam `Evento::create([...])`, e o `CapacidadeConteudosTest` **omite deliberadamente** o `evento` do DataProvider (`:39-44`) porque seu helper `objeto()` (`:73`) usa `$model::factory()`.

⇒ **O teste novo usa o helper `eventoComPivo()`** (Task 2). 🚫 **NÃO criar `EventoFactory`** — artefato novo fora do escopo do E2.

### ⚠️ 11. Herdadas do E1 (§12 do SPEC)

`pluck` qualificado · Livewire v3 (propriedade+método homônimos) · `docker compose restart app worker` · **Pint por task** · `grep -rn` (nunca `-rl`) · docblock não é evidência · **`scoped`, nunca `singleton`**.

---

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**.
- **Branch:** criar `camada-1-e2-filtro` a partir de `origin/main` (= `5a6a9ba`). **Nunca** na `main`.
- **Banco:** 🚫 **PROIBIDO** `migrate:fresh`, `migrate:refresh`, `db:wipe`, `migrate:reset` e seed/factory destrutivo — apagam os 123 AgendaDia, 127 Palestras, 45 Posts, 59 Palestrantes e 56 Eventos do legado. **E2 não tem migration nenhuma.** Se algo pedir migration, **pare e reporte**.
- **Legado:** conexão `legado` é **somente SELECT**.
- **Comandos:** `docker compose exec -T app php artisan ...` (o projeto **não** usa Sail). npm/Vite no **host**.
- **Shell: Git Bash (POSIX sh), não PowerShell.** Os blocos ```bash são para **Git Bash**. Os `tinker --execute` escapam variáveis PHP com `\$`, o que funciona no Git Bash e **quebra no PowerShell** (escape é backtick ⇒ `"\$criam"` vira `\` + expansão ⇒ **parse error** no PHP). Verificado. Se o ambiente abrir PowerShell, **rodar pelo Bash tool**.
- **Pint por task:** `docker compose exec -T app ./vendor/bin/pint` antes de cada commit — o CI roda `pint --test` **antes** dos testes.
- **Dev:** depois de editar PHP/Blade, `docker compose restart app worker` (OPcache).
- **Autoria** em arquivo novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16`.
- **Ancorar por assinatura, não por número.** Os números foram verificados em `5a6a9ba` mas **deslocam** conforme as edições do próprio E2. Usar a assinatura como âncora, os números como conferência. Se não bater, **conferir — não improvisar**.
- 🚫 **Regime `null` NUNCA tem fallback.** Se um teste ficar vermelho porque `tipos_conteudo` está vazia, **semeie o teste** (com a ordem correta — Armadilha 1). **Jamais** afrouxar `AcessoPorTipo`.

### Commits intermediários VERMELHOS são esperados entre a Task 2 e a Task 5 — e só entre elas

A Task 2 troca o eixo do filtro **antes** de as Tasks 3-5 reescreverem os testes que dependiam do eixo velho. **É ordem deliberada, não erro.** O CI só precisa fechar verde no **último** commit do PR; a suíte completa só é exigida verde na **Task 6, Passo 2**.

**Vermelhos previstos (ancorar pela assinatura):**

| Ao fim da | Arquivo · caso | Causa | Consertado na |
|---|---|---|---|
| **Task 2** | `AgendaContaEditarExcluirTest` · `test_editar_registro_de_outro_departamento_e_negado` e `test_excluir_...` | o `authorize()` da **ação** (`AgendaConta:100`/`:110`) já usa a policy nova; DECOM **é** responsável ⇒ o `assertForbidden` cai | **Task 2, Passo 8-bis** (é a própria task) |
| **Task 3** | `AgendaContaCriarTest` · `test_lista_mostra_so_o_escopo_do_usuario` | o scope vira **tudo-ou-nada** ⇒ o responsável vê o registro do DED | **Task 3, Passo 1** (é a própria task) |
| **Task 4** | `AuditoriaAgendaPortaTest` · `test_criar_pelo_site_grava_porta_perfil_e_log_de_depto` | a chamada `registrarDepartamentosConteudo` some ⇒ não há entrada manual | **Task 4, Passo 4-bis** |

> **O 🚨 "PARE E REPORTE" do Passo 7 da Task 1 vale SÓ para a Task 1** (neutra por construção). **Nas Tasks 2-5, vermelho FORA desta tabela é que é PARE E REPORTE.**

### O dev muda de comportamento durante a execução — é esperado

O dev **já tem a config semeada** (`tipos: 5 | pivo: 5`) ⇒ **não há cutover no dev**: no instante em que a Task 2 rodar, o acesso passa a valer pela config. **Entre a Task 2 e o fim do E2 o `/minha-conta` fica inconsistente no dev.** Esperado; a Task 6 fecha. Em **PROD**, rodar o `TiposConteudoSeeder` é **passo obrigatório** (§13.1).

---

### Task 0: Branch

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-1-e2-filtro origin/main
git log --oneline -1
```

Esperado: `5a6a9ba Merge pull request #32 from MouraoBSB/camada-1-e1-fundacao`.

---

### Task 1: Preparar o terreno dos testes — a semente (COMPORTAMENTO-NEUTRA)

**Por que esta task existe (leia antes de executar).** Nada **autoriza** pela config ainda: o `AcessoPorTipo` tem **zero consumidores** (o E2 só o liga na Task 2). A config já é lida e escrita pela **tela do E1** (`MatrizCapacidades:68` e `:193-227`), mas **nenhuma policy, nenhum scope e nenhuma aba a consultam** — e o único teste que exercita a tela (`MatrizCapacidadesTest`) **já semeia desde o E1** (`:29`). Logo **semear é inócuo para o ACESSO**, e a suíte fica **848 verde antes e depois**.

O ganho é isolar as **duas causas de vermelho**: depois desta task, qualquer vermelho nas Tasks 2-5 só pode ser **regra**, nunca **semente ausente**. É o que torna a isca do §12.8 **impossível** em vez de apenas proibida.

**Files:** só `tests/`. **Esta task não toca `app/`.**

- [ ] **Passo 1: Confirmar o ponto de partida**

```bash
docker compose exec -T app php artisan test 2>&1 | tail -3
```

Esperado: **848 passed**. (Se `ImportadorBlogTest` acusar 2 falhas de cap de imagem (GD), é a flakiness conhecida sob carga — rodar isolado; **não é regressão desta fase**.)

- [ ] **Passo 2: `EventoPolicyCapacidadeTest` — o aceite (a), 2 linhas e nada mais**

Somar o import (ordem alfabética: logo após `CapacidadesSeeder`, na `:12` — `Database` < `Illuminate`, então **nenhum outro import é reordenado**):

```php
use Database\Seeders\TiposConteudoSeeder;
```

E, no `setUp`, **imediatamente após** `$this->seed(CapacidadesSeeder::class);` (hoje a `:25`; **com o import somado ela vira `:26`, a linha nova cai na `:27` e o `}` vai para a `:28`**):

```php
        $this->seed(TiposConteudoSeeder::class);
```

🚫 **Nenhuma asserção, nenhum cenário, nenhum valor esperado muda.** Se algo mais precisar mudar, **PARE E REPORTE** — o E2 vazou.

> **Por que é seguro** (§10.3, nota do I4): `evento` é `PorRegistro` com `siglas: []` (`TiposConteudoSeeder:29`), e `idsPorSigla()` retorna `[]` de saída antecipada (`:59-61`) **sem consultar `Departamento`** ⇒ o seeder **não** depende dos deptos ad hoc que este arquivo cria por caso (`depto()` em `:47-50`). Os 4 tipos "do tipo" ficam com `sync([])` — **irrelevante**: o arquivo só testa Evento.

Conferir na hora:

```bash
git diff -U0 -- tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php | grep -E '^[-+]' | grep -vE '^(\+\+\+|---)'
```

Esperado: exatamente as 2 linhas `+`. **Se aparecer qualquer linha `-`, pare.**

- [ ] **Passo 3: `CapacidadeConteudosTest` — as 3 correções do bloqueador**

**(i)** No `setUp` (`:29-34`), `EstruturaCemaSeeder` **ANTES** do `TiposConteudoSeeder`:

```php
    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class); // as 20 permissions (inclui palestrante.*)
        // Os 8 departamentos PRECISAM existir antes da semente da config: o TiposConteudoSeeder
        // resolve responsável por sigla e, com a tabela vazia, gravaria zero responsáveis SEM
        // erro — e o insert-only congelaria esse estado (resemear não repara).
        (new EstruturaCemaSeeder)->run();
        $this->seed(TiposConteudoSeeder::class);
    }
```

Imports: `use Database\Seeders\EstruturaCemaSeeder;` e `use Database\Seeders\TiposConteudoSeeder;`.

**(ii)** O helper `depto()` (`:66-69`) resolve, não cria:

```php
    /** Resolve o departamento semeado pelo EstruturaCemaSeeder (não cria: violaria o unique de sigla/slug). */
    private function depto(string $sigla): Departamento
    {
        return Departamento::where('sigla', $sigla)->firstOrFail();
    }
```

**(iii)** O DataProvider carrega a **sigla responsável** (`TiposConteudoSeeder:25-29`):

```php
    /** @return array<string, array{class-string<Model>, string, string}> */
    public static function recursos(): array
    {
        // A 3ª coluna é a sigla RESPONSÁVEL pelo tipo na semente. 'post' é DECOM — não DED.
        // Alinhar o vínculo à semente é o que mantém o teste medindo a regra, e não a
        // coincidência de todos os recursos serem DED.
        return [
            'palestra' => [Palestra::class, 'palestra', 'DED'],
            'post' => [Post::class, 'post', 'DECOM'],
            'agenda' => [AgendaDia::class, 'agenda', 'DED'],
            'palestrante' => [Palestrante::class, 'palestrante', 'DED'],
        ];
    }
```

Cada método do DataProvider ganha o 3º parâmetro `string $sigla`, trocando `$this->depto('DED')` por `$this->depto($sigla)` — **exceto** o `depto('DEPRO')` da `:103`, que é o **disjunto** e continua `DEPRO`.

> **Isto continua neutro:** sob o filtro **velho**, usuário e objeto ficam na **mesma** sigla ⇒ interseção ⇒ os `assertTrue` seguem verdes; o disjunto segue `assertFalse`. **O `EstruturaCemaSeeder` cria os deptos com o nome real** (`'Estudos Doutrinários'`) em vez de `'DED'` — conferido: nenhum caso deste arquivo depende do **nome**, só da sigla.

- [ ] **Passo 4: `CapacidadeViaPapelTest` — semente + o vínculo dos DOIS lados**

No `setUp`, após `$this->seed(CapacidadesSeeder::class);` (`:26`), somar `$this->seed(TiposConteudoSeeder::class);` + o import (ordem alfabética: após `EstruturaCemaSeeder`, na `:13`).

E, em `test_usuario_do_papel_ganha_e_perde_capacidade`, trocar **`DECOM` por `DED` nos dois lados** (`:50` e `:51`):

```php
        $diretor = $this->diretorNos(['DED']);
        $palestra = $this->palestraNos(['DED']);
```

> ⚠️ **Os dois, não só o usuário.** Trocar só o `diretorNos` deixaria usuário DED × palestra DECOM ⇒ **disjunto** ⇒ o `assertTrue` da `:58` ficaria **vermelho já nesta task**, quebrando a neutralidade. Com os dois em DED: interseção sob o filtro velho **e** responsável sob o novo. **Neutro nos dois regimes.**

✅ **Manter os `->fresh()` das `:54,:58,:62`** — ali são reconsultas **pós-mutação de papel** (cache do spatie), legítimas (Armadilha 3).
**`:73` — NENHUM ajuste de vínculo** (o presidente tem os 8, DED incluso). **`:77-90` — não tocar** (reescrito na Task 2).

- [ ] **Passo 5: `MatrizCapacidadesTest` — uma linha, e NÃO é semente**

**Já semeia** (`:29`). Trocar a sigla na **`:95`** e renomear a variável `$decom` → `$ded` nos usos (`:104`, `:107`):

```php
        $ded = Departamento::where('sigla', 'DED')->first();
```

> **Quem fica vermelho é o `assertTrue` da `:109`** — não a `:107` (Armadilha 6). Conferir com `grep -n "decom" tests/Feature/Filament/MatrizCapacidadesTest.php` que não sobrou nenhum uso.

- [ ] **Passo 6: Os 5 testes de `/minha-conta` — só a semente**

Nos 5, somar `$this->seed(TiposConteudoSeeder::class);` **depois** do `EstruturaCemaSeeder` existente, mais o import:

| Arquivo | `EstruturaCemaSeeder` | Vínculo | Ajustar vínculo? |
|---|---|---|---|
| `tests/Feature/Conta/AbaAgendaTest.php` | `:26` | por caso | **não** — casos decididos na Task 3 |
| `tests/Feature/Conta/AgendaContaCriarTest.php` | `:26` | `DECOM` (`:38`) | **não** — `agenda ⇒ DED+DECOM` |
| `tests/Feature/Conta/AgendaContaEditarExcluirTest.php` | `:25` | por caso (`:36`) | **não** — os 4 casos estão decididos na **Task 2, Passo 8-bis** |
| `tests/Feature/Conta/AcessoAgendaContaTest.php` | `:23` | `DECOM` (`:33`) | **não** — casos decididos na Task 3 |
| `tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php` | `:28` | por caso | **não** — caso decidido na **Task 4, Passo 4-bis** |

- [ ] **Passo 7: Provar a neutralidade**

```bash
docker compose exec -T app php artisan test 2>&1 | tail -3
git diff --stat -- app/ ; echo "^ esperado: VAZIO — esta task não toca app/"
```

Esperado: **848 passed**, exatamente como no Passo 1, e **nenhuma** mudança em `app/`.

> 🚨 **Se qualquer teste mudou de cor aqui, PARE E REPORTE.** Esta task é neutra por construção: **nada autoriza pela config** e **ela não toca `app/`** — logo o único código que hoje lê a config (a tela) é byte a byte o mesmo. Vermelho aqui = uma das edições **de teste** mudou um cenário.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add tests/
git commit -m "test(camada-1): semeia a config de acesso nos testes que E2 vai tocar (neutro)"
```

---

### Task 2: O trait, as 5 policies e os invariantes — **é aqui que o acesso muda**

**Files:**
- Modify: `app/Policies/Concerns/AutorizaPorDepartamento.php`
- Modify: `app/Policies/{Evento,AgendaDia,Palestra,Palestrante,Post}Policy.php`
- Test: `tests/Feature/Autorizacao/CamadaUmFiltroPorTipoTest.php` (novo)
- Modify: `tests/Feature/Autorizacao/CapacidadeViaPapelTest.php`, `CapacidadeConteudosTest.php`
- Modify: `tests/Feature/Conta/AgendaContaEditarExcluirTest.php` (Passo 8-bis)

- [ ] **Passo 1: Escrever o teste que falha — os invariantes**

Criar `tests/Feature/Autorizacao/CamadaUmFiltroPorTipoTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Autorizacao;

use App\Enums\RegimeAcesso;
use App\Enums\VisibilidadeEvento;
use App\Models\AgendaDia;
use App\Models\Departamento;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Os invariantes da Camada 1 (§7 do spec). Cada caso REPROVA uma implementação errada — não basta
 * ficar verde: o arranjo tem de distinguir "responsável pelo tipo" de "objeto no meu departamento".
 * Por isso TODO caso do regime "do tipo" fixa o pivô do objeto explicitamente (a AgendaDiaFactory
 * não anexa departamento; e o Evento NÃO tem factory — é criado à mão, no molde do
 * EventoPolicyCapacidadeTest:52-61, porque `slug` é UNIQUE).
 */
class CamadaUmFiltroPorTipoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();      // 8 departamentos + papéis
        $this->seed(CapacidadesSeeder::class); // as 20 permissions
    }

    private function idDe(string $sigla): int
    {
        return Departamento::where('sigla', $sigla)->value('id');
    }

    /**
     * Configura o tipo direto na tabela (a tela é do E1; aqui interessa o estado).
     * SEMPRE antes da 1ª checagem: o memo do AcessoPorTipo é por RECURSO e fresh() não o invalida.
     */
    private function configurar(string $recurso, RegimeAcesso $regime, array $siglas = []): void
    {
        $tipo = TipoConteudo::updateOrCreate(['recurso' => $recurso], ['regime' => $regime]);
        $tipo->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));
    }

    private function diretorEm(array $siglas, array $capacidades = ['agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir']): User
    {
        $u = User::factory()->create();
        foreach ($capacidades as $c) {
            $u->givePermissionTo($c);
        }
        $u->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $u;
    }

    /** O pivô do objeto é SEMPRE explícito: [] = vazio, ou as siglas dadas. */
    private function agendaComPivo(array $siglas): AgendaDia
    {
        $ag = AgendaDia::factory()->create();
        $ag->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $ag;
    }

    /** Evento NÃO tem factory (não existe database/factories/EventoFactory.php) — criar à mão; slug é UNIQUE. */
    private function eventoComPivo(string $slug, array $siglas = []): Evento
    {
        $e = Evento::create([
            'titulo' => 'E',
            'slug' => $slug,
            'data_inicio' => '2026-08-15',
            'visibilidade' => VisibilidadeEvento::Publico,
            'status' => Evento::STATUS_RASCUNHO,
        ]);
        $e->departamentos()->sync(array_map(fn (string $s): int => $this->idDe($s), $siglas));

        return $e;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole(Role::findOrCreate('administrador', 'web'));

        return $u;
    }

    private function assertNegaTudo(User $u, AgendaDia $ag, string $porque): void
    {
        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertFalse(Gate::forUser($u)->check($acao, $ag), "{$porque}: {$acao}");
        }
        $this->assertFalse(Gate::forUser($u)->check('criar', AgendaDia::class), "{$porque}: criar");
    }

    private function assertPermiteTudo(User $u, AgendaDia $ag, string $porque): void
    {
        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $ag), "{$porque}: {$acao}");
        }
    }

    // ---------- I1: config vazia NUNCA permite ----------

    public function test_i1_tipo_sem_responsaveis_nega_tudo_mesmo_com_capacidade_e_vinculo(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, []);   // "do tipo" SEM responsáveis
        $diretor = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo(['DED']);                     // pivô coincidente — não pode salvar

        $this->assertNegaTudo($diretor, $ag, 'I1: tipo sem responsáveis');
    }

    public function test_i1_admin_passa_mesmo_com_config_vazia(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, []);
        $ag = $this->agendaComPivo([]);

        $this->assertPermiteTudo($this->admin(), $ag, 'I6: admin passa no Gate::before');
        $this->assertTrue(Gate::forUser($this->admin())->check('criar', AgendaDia::class));
    }

    // ---------- I2: tabela não semeada ⇒ nega e NÃO explode, nos 5 recursos ----------

    /**
     * Sem TiposConteudoSeeder: tipos_conteudo VAZIA. Cobre os 5 recursos — INCLUSIVE o evento.
     * Estender ao Evento trava por teste o fallback proibido do §12.8: se alguém trocar
     * `null => false` por fallback ao pivô, este caso fica vermelho.
     */
    public function test_i2_tabela_vazia_nega_os_quatro_verbos_nos_cinco_recursos_e_nao_lanca(): void
    {
        $this->assertSame(0, TipoConteudo::count(), 'a tabela precisa estar vazia para este caso valer');

        $ded = $this->idDe('DED');

        $mapa = [
            'agenda' => [AgendaDia::class, AgendaDia::factory()->create()],
            'evento' => [Evento::class, $this->eventoComPivo('i2-evento')],
            'palestra' => [Palestra::class, Palestra::factory()->create()],
            'post' => [Post::class, Post::factory()->create()],
            'palestrante' => [Palestrante::class, Palestrante::factory()->create()],
        ];

        foreach ($mapa as $recurso => [$classe, $objeto]) {
            $objeto->departamentos()->sync([$ded]);   // pivô COINCIDENTE: o velho filtro permitiria

            $u = User::factory()->create();
            foreach (['ver', 'criar', 'editar', 'excluir'] as $acao) {
                $u->givePermissionTo("{$recurso}.{$acao}");
            }
            $u->departamentos()->sync([$ded]);

            foreach (['ver', 'editar', 'excluir'] as $acao) {
                $this->assertFalse(Gate::forUser($u)->check($acao, $objeto), "I2 {$recurso}.{$acao}");
            }
            $this->assertFalse(Gate::forUser($u)->check('criar', $classe), "I2 {$recurso}.criar");
        }
    }

    // ---------- I3: o furo do criar, com o cenário real do §4.3 ----------

    public function test_i3_diretor_do_depro_com_capacidade_nao_cria_agenda(): void
    {
        // O cenário medido no dev (§4.3): 10 diretores criam hoje e não conseguem editar.
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);
        $depro = $this->diretorEm(['DEPRO']);

        $this->assertFalse(Gate::forUser($depro)->check('criar', AgendaDia::class), 'I3: DEPRO não é responsável');
    }

    public function test_i3_diretor_do_ded_cria_agenda(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED', 'DECOM']);

        $this->assertTrue(Gate::forUser($this->diretorEm(['DED']))->check('criar', AgendaDia::class));
    }

    // ---------- No regime "do tipo", o pivô do objeto NÃO decide ----------
    // Os 3 estados do pivô (§7/I9) estão distribuídos: DISJUNTO do usuário (test_disjunto_nega,
    // não-responsável; e test_pivo_ignorado_*, responsável), COINCIDENTE com o usuário
    // (test_pivo_nao_abre_*) e VAZIO (test_i9_*, na seção seguinte).

    /** Pivô DISJUNTO do usuário E usuário NÃO responsável ⇒ nega. O caso-base do não-responsável. */
    public function test_disjunto_nega(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DEPRO']);
        $ag = $this->agendaComPivo(['DAS']);   // pivô disjunto do usuário E dos responsáveis

        $this->assertNegaTudo($u, $ag, 'usuário fora dos responsáveis, pivô disjunto dele');
    }

    /**
     * PIVÔ IGNORADO. Reprova o AND puro e o híbrido:
     * usuário responsável, mas o pivô do objeto é disjunto DELE ⇒ PERMITE mesmo assim.
     */
    public function test_pivo_ignorado_permite_e_reprova_o_and(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo(['DEPRO']);   // pivô DISJUNTO do usuário

        $this->assertPermiteTudo($u, $ag, 'no "do tipo" o objeto NÃO é consultado');
    }

    /**
     * PIVÔ NÃO ABRE. Pivô COINCIDENTE com o usuário, usuário DISJUNTO dos responsáveis ⇒ NEGA.
     * É o caso que SEPARA as duas causas possíveis de negação: aqui o pivô PERMITIRIA (o filtro
     * velho abre pela interseção usuário∩pivô) e só a não-responsabilidade nega — logo reprova a
     * variante permissiva (||) E o filtro velho.
     */
    public function test_pivo_nao_abre_e_reprova_o_or(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DEPRO']);
        $ag = $this->agendaComPivo(['DEPRO']);   // pivô COINCIDE com o usuário — e ainda assim nega

        $this->assertNegaTudo($u, $ag, 'o pivô não pode abrir o que a config fechou');
    }

    // ---------- I9: alargamento consciente (o 3º estado: pivô VAZIO) ----------

    public function test_i9_objeto_sem_departamento_e_editavel_pelo_responsavel(): void
    {
        $this->configurar('agenda', RegimeAcesso::DoTipo, ['DED']);
        $u = $this->diretorEm(['DED']);
        $ag = $this->agendaComPivo([]);   // pivô VAZIO — hoje seria só-admin

        $this->assertPermiteTudo($u, $ag, 'I9: no "do tipo" o objeto não tem escopo próprio');
    }

    // ---------- I4: "por registro" (Evento) inalterado ----------

    public function test_i4_por_registro_permite_objeto_no_departamento_do_usuario(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        foreach (['ver', 'criar', 'editar', 'excluir'] as $a) {
            $u->givePermissionTo("evento.{$a}");
        }
        $u->departamentos()->sync([$this->idDe('DEPRO')]);

        $evento = $this->eventoComPivo('i4-dentro', ['DEPRO']);

        $this->assertTrue(Gate::forUser($u)->check('editar', $evento));
        $this->assertTrue(Gate::forUser($u)->check('criar', Evento::class), 'I4: criar = tem algum depto');
    }

    public function test_i4_por_registro_nega_objeto_fora_e_objeto_sem_departamento(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        $u->givePermissionTo('evento.editar');
        $u->departamentos()->sync([$this->idDe('DEPRO')]);

        $fora = $this->eventoComPivo('i4-fora', ['DED']);
        $orfao = $this->eventoComPivo('i4-orfao');   // sem departamento — os 7 do §13.2

        $this->assertFalse(Gate::forUser($u)->check('editar', $fora), 'objeto fora do meu depto');
        $this->assertFalse(Gate::forUser($u)->check('editar', $orfao), 'objeto sem departamento');
    }

    public function test_i4_por_registro_nega_quem_nao_tem_departamento(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $u = User::factory()->create();
        $u->givePermissionTo('evento.criar');

        $this->assertFalse(Gate::forUser($u)->check('criar', Evento::class));
    }

    // ---------- I6: admin passa em qualquer config ----------

    public function test_i6_admin_passa_no_por_registro_com_objeto_orfao(): void
    {
        $this->configurar('evento', RegimeAcesso::PorRegistro, []);
        $orfao = $this->eventoComPivo('i6-orfao');

        $this->assertTrue(Gate::forUser($this->admin())->check('editar', $orfao));
    }
}
```

- [ ] **Passo 2: Rodar o teste e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=CamadaUmFiltroPorTipoTest
```

Esperado: **FAIL por ASSERÇÃO** — `test_i3_diretor_do_depro_...` (o furo aberto), `test_pivo_ignorado_...`, `test_i9_...` (o filtro velho nega) e `test_i2_...` (o filtro velho permite pelo pivô).

> ⚠️ **Se aparecer qualquer `Error:` / `Class not found`, o arranjo está quebrado — conserte o ARRANJO, nunca a policy.** (Foi o que a Armadilha 10 evita: `Evento::factory()` não existe.)

- [ ] **Passo 3: Reescrever o trait**

`app/Policies/Concerns/AutorizaPorDepartamento.php` **inteiro**:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Policies\Concerns;

use App\Enums\RegimeAcesso;
use App\Models\Contracts\TemDepartamento;
use App\Models\User;
use App\Support\Autorizacao\AcessoPorTipo;

/**
 * Escopo por departamento das policies de capacidade (fonte única), por REGIME (Camada 1):
 * - "do tipo": vale quem está num departamento responsável pelo TIPO (a config da tela). O
 *   objeto NÃO é consultado — o pivô departamento_<x> está congelado (§6.4 do spec).
 * - "por registro": o filtro de sempre (objeto ∈ deptos do usuário), byte a byte. Só o Evento.
 *
 * Fail-closed em todos os caminhos: recurso sem linha em tipos_conteudo ⇒ nega (I1/I2).
 * 🚫 `null` NUNCA tem fallback — nem para PorRegistro, nem para regime default (§12.8).
 */
trait AutorizaPorDepartamento
{
    /** Slug do recurso no GlossarioCapacidades — o MESMO literal que a policy usa em 'x.ver'. */
    abstract protected function recurso(): string;

    /** ver/editar/excluir: o escopo depende do regime. */
    protected function noEscopo(User $user, TemDepartamento $objeto): bool
    {
        $acesso = app(AcessoPorTipo::class);

        return match ($acesso->regime($this->recurso())) {
            RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, $this->recurso()),
            RegimeAcesso::PorRegistro => $this->objetoNoDepartamentoDoUsuario($user, $objeto),
            null => false,
        };
    }

    /**
     * criar: não há objeto — a pergunta é sempre sobre o TIPO. Uma linha, de propósito:
     * usuarioHabilitadoNoTipo JÁ ramifica por regime (DoTipo ⇒ responsável = I3;
     * PorRegistro ⇒ tem algum depto = I4 intacto; null ⇒ false = I1/I2). Repetir o match aqui
     * criaria uma SEGUNDA implementação da pergunta única — o que §6.2 proíbe.
     */
    protected function podeCriarNoEscopo(User $user): bool
    {
        return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, $this->recurso());
    }

    /**
     * Regime "por registro": intacto (I4). PRIVATE de propósito — só o braço PorRegistro do
     * match acima pode alcançá-lo. Se aparecer chamada fora dali, o congelamento é ficção.
     */
    private function objetoNoDepartamentoDoUsuario(User $user, TemDepartamento $objeto): bool
    {
        $idsUsuario = $user->departamentos()->pluck('departamentos.id')->all();

        if ($idsUsuario === []) {
            return false;
        }

        return $objeto->departamentos()
            ->whereIn('departamentos.id', $idsUsuario)
            ->exists();
    }
}
```

> O corpo de `objetoNoDepartamentoDoUsuario` é **byte a byte o de hoje** (`:18-26`). Só a visibilidade muda. **É o aceite (e).**

- [ ] **Passo 4: As 5 policies — diff mínimo e uniforme**

| Policy | `recurso()` | `ver`/`editar`/`excluir` | `criar` |
|---|---|---|---|
| `AgendaDiaPolicy` | `'agenda'` | `:21, :31, :36` | `:26` |
| `PalestraPolicy` | `'palestra'` | `:22, :32, :37` | `:27` |
| `PalestrantePolicy` | `'palestrante'` | `:23, :33, :38` | `:28` |
| `PostPolicy` | `'post'` | `:21, :31, :36` | `:26` |
| `EventoPolicy` | `'evento'` | `:36, :46, :51` | `:41` |

Em cada uma, somar (logo após o `use AutorizaPorDepartamento;`):

```php
    /** O mesmo literal já hardcodado em 'agenda.ver' — zero divergência nova (§9.2). */
    protected function recurso(): string
    {
        return 'agenda';
    }
```

E trocar as 4 chamadas:

```php
-        return $user->hasPermissionTo('agenda.ver') && $this->objetoNoDepartamentoDoUsuario($user, $agendaDia);
+        return $user->hasPermissionTo('agenda.ver') && $this->noEscopo($user, $agendaDia);
```
```php
-        return $user->hasPermissionTo('agenda.criar') && $user->departamentos()->exists();
+        return $user->hasPermissionTo('agenda.criar') && $this->podeCriarNoEscopo($user);
```

🚫 **`EventoPolicy::view` (`:24-27`) e `::viewAny` (`:29-32`) NÃO são tocados** — eixo de **visibilidade** (`?User` nullable). Camada 4.

- [ ] **Passo 5: Rodar o teste novo e ver passar**

```bash
docker compose exec -T app php artisan test --filter=CamadaUmFiltroPorTipoTest
```

Esperado: **PASS** (13 testes).

- [ ] **Passo 6: `CapacidadeViaPapelTest` — reescrever o teste da exceção morta**

`test_decom_edita_palestra_com_dois_departamentos_por_intersecao` (`:77-90`) testa **nominalmente** a exceção que a decisão 4 mata (§5). **Reescrever, não deletar** — vira o guardião do item 5 do §11.

```php
    /**
     * O caso canônico do congelamento (§6.4 + I9). Substitui o antigo
     * test_decom_edita_palestra_com_dois_departamentos_por_intersecao, cuja premissa (a exceção
     * por objeto da Fase C) a decisão 4 do §5 matou. Guardião do item 5 do §11: se algum caminho
     * voltar a ler o pivô para autorizar, alguma destas 3 fases fica vermelha.
     */
    public function test_regime_do_tipo_ignora_o_pivo_do_objeto(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');
        // A semente dá palestra ⇒ "do tipo", responsável = DED (TiposConteudoSeeder:26).

        $ded = $this->diretorNos(['DED']);

        // 1) pivô do objeto DED+DECOM ⇒ permite (o objeto não é consultado)
        $this->assertTrue(Gate::forUser($ded)->check('editar', $this->palestraNos(['DED', 'DECOM'])));

        // 2) pivô do objeto SÓ DECOM (disjunto do usuário) ⇒ permite mesmo assim — o congelamento
        $this->assertTrue(Gate::forUser($ded)->check('editar', $this->palestraNos(['DECOM'])));

        // 3) usuário no DECOM (NÃO responsável), pivô coincidente ⇒ nega — o pivô não abre nada
        $decom = $this->diretorNos(['DECOM']);
        $this->assertFalse(Gate::forUser($decom)->check('editar', $this->palestraNos(['DECOM'])));
    }
```

> **Sem `fresh()`** (Armadilha 3): a única mutação (`givePermissionTo` no papel) acontece **antes** de os usuários existirem — é a forma do `:73/:85/:89`, verdes hoje sem `fresh()`.

🚫 **Proibido "consertar" semeando `palestra ⇒ DED+DECOM`** para o `assertTrue` voltar.

- [ ] **Passo 7: `test_presidente_diretor_com_8_deptos_...` — decisão escrita**

`:65-75` varre `['DED','DECOM','DEPRO']` afirmando que o presidente edita "em qualquer departamento". Sob "do tipo" **o pivô não é consultado** ⇒ as 3 iterações passam **trivialmente**: **verde medindo nada**. Reduzir ao que ele realmente prova:

```php
    /**
     * O presidente (8 vínculos) inclui o DED, responsável por palestra ⇒ edita.
     * Antes varria 3 siglas do pivô do objeto; sob o regime "do tipo" isso seria tautologia
     * (o objeto não é consultado — ver test_regime_do_tipo_ignora_o_pivo_do_objeto).
     */
    public function test_presidente_diretor_edita_palestra_por_estar_no_departamento_responsavel(): void
    {
        Role::findByName('diretor', 'web')->givePermissionTo('palestra.editar');

        $presidente = $this->diretorNos(array_keys(GlossarioUsuarios::DEPARTAMENTOS)); // 8 vínculos

        $this->assertTrue(Gate::forUser($presidente)->check('editar', $this->palestraNos(['DED'])));
    }
```

- [ ] **Passo 8: `CapacidadeConteudosTest` — os 2 casos que mudam de natureza**

Sob "do tipo" o pivô deixa de autorizar ⇒ **2 casos invertem de propósito**. **Não é bug — é a regra nova.**

**(a)** `test_nega_caso_disjunto` (`:100-110`) → é o caso "Pivô ignorado" (§10.3):

```php
    /**
     * Era test_nega_caso_disjunto: o pivô disjunto negava. Sob o regime "do tipo" o objeto não é
     * consultado ⇒ o responsável edita mesmo objeto de outro departamento. É o §6.4/I9, escrito.
     */
    #[DataProvider('recursos')]
    public function test_pivo_disjunto_do_objeto_nao_impede_o_responsavel(string $model, string $recurso, string $sigla): void
    {
        $responsavel = $this->depto($sigla);
        $depro = $this->depto('DEPRO');
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$responsavel->id]);
        $obj = $this->objeto($model, [$depro->id]);   // objeto em OUTRO departamento

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
        }
    }
```

**(b)** `test_objeto_sem_departamento_so_admin` (`:126-136`) → é o **I9**:

```php
    /**
     * Era test_objeto_sem_departamento_so_admin. I9 (§7): no "do tipo" o objeto não tem escopo
     * próprio ⇒ o responsável edita. Alargamento CONSCIENTE — alarga 0 registros hoje (§4.1).
     */
    #[DataProvider('recursos')]
    public function test_i9_objeto_sem_departamento_e_do_responsavel(string $model, string $recurso, string $sigla): void
    {
        $u = $this->usuario(["{$recurso}.ver", "{$recurso}.editar", "{$recurso}.excluir"], [$this->depto($sigla)->id]);
        $obj = $this->objeto($model, []);

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($u)->check($acao, $obj), "{$recurso}.{$acao}");
            $this->assertTrue(Gate::forUser($this->admin())->check($acao, $obj), "admin {$recurso}.{$acao}");
        }
    }
```

**Decisão escrita para os demais** (nenhum fica sem destino):

| Caso | Destino |
|---|---|
| `test_policies_resolvidas_por_auto_discovery` (`:79-85`) | **não muda** |
| `test_permite_ver_editar_excluir_com_intersecao` (`:87-97`) | **verde**; renomear para `..._com_responsavel` (o nome "interseção" passa a mentir) |
| `test_nega_sem_vinculo` (`:112-123`) | **verde** — sem departamento nunca é responsável |
| `test_nega_sem_a_permissao` (`:138-149`) | **verde** — a capacidade é o 1º fator |
| `test_criar_com_e_sem_departamento` (`:151-160`) | **verde** com o vínculo na sigla responsável (é o I3 pelo lado positivo) |
| `test_nome_cru_nega_mas_ability_permite` (`:162-171`) | **verde** (I5) |
| `test_visitante_anonimo_negado` (`:173-180`) | **não muda** |
| `test_admin_passa_em_tudo` (`:182-192`) | **não muda** (I6) |

- [ ] **Passo 8-bis: `AgendaContaEditarExcluirTest` — os 4 casos, decididos AQUI**

Os 4 são **determinísticos**, não "a decidir". A semente dá `agenda ⇒ DoTipo, ['DED','DECOM']`, logo o `editorDe('DECOM')` (`:32-39`) **é responsável**. E o 403 vem do `authorize()` da **AÇÃO** (`AgendaConta:100` e `:110`, sobre um `findOrFail` **não escopado**) ⇒ **quem os move é a policy desta task**, não o scope da Task 3.

- `test_editar_altera_conteudo_e_preserva_departamentos` (`:49-65`) — **VERDE, não tocar.** É o guardião do §6.4 pelo lado da escrita: o `update()` (`AgendaConta:139`) segue **preservando** o pivô. **Se ficar vermelho, o vermelho é o bug.**
- `test_excluir_remove_registro_do_escopo` (`:78-88`) — **VERDE, não tocar.**
- `test_editar_registro_de_outro_departamento_e_negado` (`:67-76`) e `test_excluir_..._e_negado` (`:90-101`) — **ficam VERMELHOS nesta task** (`:75`, `:98`): DECOM é responsável ⇒ o `authorize()` permite. **Reescrever invertendo a asserção**, mantendo o usuário em `DECOM` e o `$alheio` em `['DED']`:

```php
    /**
     * Era test_editar_registro_de_outro_departamento_e_negado. Sob o regime "do tipo" o pivô do
     * objeto não é consultado ⇒ o responsável (DECOM) edita o dia de pivô DED. §10.3 "Pivô ignorado".
     */
    public function test_editar_registro_de_pivo_disjunto_e_permitido_ao_responsavel(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']);          // mantém o mount() passando ATÉ a Task 3
        $alheio = $this->agendaEm(['DED']);  // pivô disjunto do usuário — e irrelevante

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('editar', $alheio->id)
            ->assertSet('editandoId', $alheio->id)
            ->assertSet('mostrandoForm', true);
    }

    /** Era test_excluir_registro_de_outro_departamento_e_negado. Idem: o pivô não fecha nada. */
    public function test_excluir_registro_de_pivo_disjunto_e_permitido_ao_responsavel(): void
    {
        $user = $this->editorDe('DECOM');
        $this->agendaEm(['DECOM']);
        $alheio = $this->agendaEm(['DED']);

        Livewire::actingAs($user)->test(AgendaConta::class)
            ->call('excluir', $alheio->id);

        $this->assertDatabaseMissing('agenda_dias', ['id' => $alheio->id]);
    }
```

> ⚠️ **Conferir a assinatura real dos helpers `editorDe`/`agendaEm` no arquivo antes de usar** — se divergirem, ajustar o caso, **não** o helper. 🚫 **Não** reintroduzir o sync do pivô nem afrouxar `AcessoPorTipo`.

- [ ] **Passo 9: Rodar os testes de autorização**

```bash
docker compose exec -T app php artisan test --filter="CamadaUmFiltroPorTipo|CapacidadeViaPapel|CapacidadeConteudos|EventoPolicyCapacidade|MatrizCapacidades|ConteudosTemDepartamento|AgendaContaEditarExcluir"
```

Esperado: **PASS neste filtro**. `EventoPolicyCapacidadeTest` e `ConteudosTemDepartamentoTest` verdes **sem nenhuma asserção alterada** — se algum exigir mudança de asserção, **PARE E REPORTE**.

⚠️ **O filtro é deliberadamente estreito.** Na **suíte completa**, `AgendaContaCriarTest::test_lista_mostra_so_o_escopo_do_usuario` ainda estará vermelho — **esperado** (tabela das Global Constraints); a Task 3 o reescreve. **Commitar assim é correto.** **Qualquer OUTRO vermelho = PARE E REPORTE.**

- [ ] **Passo 10: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Policies tests/Feature/Autorizacao tests/Feature/Conta/AgendaContaEditarExcluirTest.php
git commit -m "feat(camada-1): filtro das policies passa a ser por responsável do tipo (I1..I4, I9); criar deixa de aceitar qualquer departamento"
```

---

### Task 3: O scope e a aba — o `/minha-conta` para de consultar registro

**Files:**
- Modify: `app/Models/AgendaDia.php` (`scopeNoEscopoDe`)
- Modify: `app/Support/Conta/AbaAgenda.php` (o `calcular()` **e o docblock inteiro**)
- Modify: `tests/Feature/Conta/AbaAgendaTest.php`, `AcessoAgendaContaTest.php`, `AgendaContaCriarTest.php` (**um único caso** — a lista)

**Contexto:** `noEscopoDe` tem **2 chamadores de produção** (`AbaAgenda:41`, `AgendaConta:223`). A listagem de `AgendaConta:223` **não chama `authorize()` por linha** — a confidencialidade depende **100%** do scope. No "do tipo" é **tudo-ou-nada**.

- [ ] **Passo 1: Os casos novos + a decisão escrita de TODOS os existentes**

Somar a `tests/Feature/Conta/AbaAgendaTest.php` (**sem `fresh()`** — não há reconsulta pós-mutação; Armadilha 3):

```php
    /** §6.3: a aba não consulta registro — responsável vê a aba mesmo com a Agenda VAZIA. */
    public function test_responsavel_ve_a_aba_com_a_agenda_vazia(): void
    {
        $this->assertSame(0, AgendaDia::count(), 'este caso exige a Agenda vazia');

        $this->assertTrue(AbaAgenda::visivelPara($this->editorDe('DED')));
    }

    /** Não-responsável não vê a aba, mesmo com registros existindo. */
    public function test_nao_responsavel_nao_ve_a_aba_mesmo_com_registros(): void
    {
        AgendaDia::factory()->count(3)->create();

        $this->assertFalse(AbaAgenda::visivelPara($this->editorDe('DEPRO')));
    }

    /** Tudo-ou-nada: o responsável enxerga TODOS os registros, inclusive os de pivô disjunto. */
    public function test_scope_do_tipo_e_tudo_ou_nada(): void
    {
        $depro = Departamento::where('sigla', 'DEPRO')->value('id');
        AgendaDia::factory()->count(2)->create()->each(fn ($a) => $a->departamentos()->sync([$depro]));
        AgendaDia::factory()->create();   // pivô vazio

        $this->assertSame(3, AgendaDia::noEscopoDe($this->editorDe('DED'))->count(), 'responsável vê tudo');
        $this->assertSame(0, AgendaDia::noEscopoDe($this->editorDe('DEPRO'))->count(), 'não-responsável vê nada');
    }

    /** §10.3 ("Aba"), 2º portão: o mount do componente aborta 403 para o não-responsável. */
    public function test_nao_responsavel_nao_monta_o_componente(): void
    {
        AgendaDia::factory()->count(3)->create();

        Livewire::actingAs($this->editorDe('DEPRO'))
            ->test(AgendaConta::class)
            ->assertForbidden();
    }
```

Imports novos: `use App\Livewire\Conta\AgendaConta;` e `use Livewire\Livewire;`.

> ⚠️ **O helper do arquivo é `editorDe(string $sigla): User` (`:31-38`)** — cria o `User`, faz `assignRole('diretor')` (`:34`) e o `sync` (`:35`). Como o `setUp` (`:28`) já concede `agenda.ver` ao papel `diretor`, **não chamar `givePermissionTo`** nos casos novos (é o idioma do arquivo).
> 🚫 **Não confundir com `usuarioEm(string ...$siglas)` de `AcessoPorTipoTest:32`** — homônimo de OUTRO arquivo, variádico e **sem** `assignRole`; copiá-lo para cá faria `visivelPara` devolver `false` por falta do papel.
>
> **Por que o `test_nao_responsavel_nao_monta_o_componente` é obrigatório:** o portão de `AgendaConta:45` tem **zero teste** hoje — verificado. Os 2 `assertForbidden` de `AgendaContaEditarExcluirTest` vêm do `authorize()` da **ação** e arranjam o mount para **passar**. **Apagar o `abort_unless` da `:45` inteiro não deixaria um único teste vermelho.**

**🚨 Decisão escrita — os 7 casos existentes do `AbaAgendaTest` (nenhum fica sem destino).** Com a semente (`agenda ⇒ DED+DECOM`), o `editorDe('DECOM')` passa a ser **responsável** ⇒ 3 casos medem o eixo que o "do tipo" abandona. **Vermelho neles NÃO é bug — é a regra nova.**

| Caso | Linhas | Destino |
|---|---|---|
| `test_mantenedores_sao_ded_e_decom` | `:40-45` | **APAGAR** — usa `AgendaMantenedores::ids()` (`:44`), classe deletada na Task 4. O que provava vira **dado de configuração**, coberto por `TiposConteudoSeederTest` (E1). Remover também o import `:10`. |
| `test_scope_no_escopo_filtra_por_departamento` | `:47-62` | **APAGAR** — a `:61` mede a interseção **por objeto**: DECOM é responsável ⇒ tudo-ou-nada ⇒ o `$foraEscopo` (pivô DED) **entra** na lista. Substituído por `test_scope_do_tipo_e_tudo_ou_nada`. |
| `test_aba_oculta_sem_registro_no_escopo` | `:81-86` | **APAGAR** — a premissa é a "decisão 1" da Fase D, **revogada por §6.3**. 🚨 **É o oposto literal de `test_responsavel_ve_a_aba_com_a_agenda_vazia`**, acima: mesmo arranjo, asserção contrária. **Deixar os dois torna o arquivo impossível de ficar verde.** |
| `test_aba_visivel_com_capacidade_e_registro_no_escopo` | `:73-79` | **verde**; **renomear** para `test_aba_visivel_para_o_responsavel_com_capacidade` (o nome passa a mentir). Rename puro. |
| `test_scope_fail_closed_para_usuario_sem_departamento` | `:64-71` | **verde, não muda** — sem vínculo nunca é responsável. Fail-closed nos dois regimes. |
| `test_aba_oculta_sem_capacidade` | `:88-96` | **não muda** — a capacidade é o 1º fator, curto-circuito. |
| `test_nao_quebra_quando_a_capacidade_nao_esta_no_catalogo` | `:98-109` | **não muda** — `checkPermissionTo` devolve false antes de tocar a config (§3.7). |

> 🚫 **Não "consertar" devolvendo a query de registro ao `AbaAgenda::calcular()`.** O vermelho da `:85` é a **isca literal do §12.8**: aparece num teste de aba com mensagem que lê "aba oculta". Reintroduzir o `exists()` reverte §6.3, reabre o furo do 1º registro **e** deixa o caso novo vermelho. **O teste velho é que está errado — apagar, não reanimar.**

**🚨 `AcessoAgendaContaTest` — 1 reescrita + 1 caso novo (o portão da ROTA):**

`test_editor_sem_registro_no_escopo_recebe_403` (`:57-65`) é o **único** teste que exercita o deny-por-escopo da rota. O usuário é **DECOM** (responsável) sem registros ⇒ depois do Passo 4 recebe **200** ⇒ a `:64` fica vermelha. **A premissa foi revogada** (§6.3). **Reescrever, invertendo por regra:**

```php
    /** §6.3: a rota abre para o responsável mesmo sem nenhum registro (a aba não consulta registro). */
    public function test_responsavel_sem_registro_acessa_a_rota(): void
    {
        // mesmo arranjo de antes; a asserção inverte porque a decisão 1 da Fase D foi revogada
        // ... (manter o corpo, trocar assertForbidden() por assertOk())
    }

    /** O outro eixo do 403: não-responsável, COM registros existindo (§10.3, 1º portão). */
    public function test_nao_responsavel_recebe_403_mesmo_com_registros(): void
    {
        $depro = Departamento::where('sigla', 'DEPRO')->value('id');
        $user = User::factory()->create();
        $user->assignRole('diretor');
        $user->departamentos()->sync([$depro]);
        // Pivô EXPLÍCITO e coincidente com o usuário (Armadilha 2): os registros existem e o pivô
        // intersecta o DEPRO — e ainda assim nega, porque DEPRO não é responsável (DED+DECOM).
        AgendaDia::factory()->count(3)->create()->each(fn ($a) => $a->departamentos()->sync([$depro]));

        $this->actingAs($user)->get(route('conta.agenda'))->assertForbidden();
    }
```

> **Sem o caso novo, o arquivo perde a cobertura do 403 no eixo "responsável"** — o `test_usuario_sem_capacidade_recebe_403` (`:46-55`) nega pela **capacidade**, não pelo eixo novo. **Só com os dois** (+ o do componente, acima) a linha "Aba" do §10.3 ("abort 403 nos **2 portões**") está entregue. Os demais casos do arquivo ficam **verdes e inalterados** (o helper `editorDecomComAgenda` usa DECOM, responsável).

**🚨 `AgendaContaCriarTest::test_lista_mostra_so_o_escopo_do_usuario` (`:140-155`) — quebra NESTA task.**

Monta o usuário em **DECOM** (`:38`) e assere `->assertDontSee('AlheioDoDed')` (`:153`) sobre um AgendaDia em **DED**. A lista vem de `AgendaConta:223` (`noEscopoDe`, **sem `authorize()` por linha**) ⇒ DECOM é responsável ⇒ **vê tudo** ⇒ a `:153` fica vermelha. **É aqui que se decide** — a Task 4 **não toca o scope**. Reescrever:

```php
    /**
     * Sob o regime "do tipo" a lista é TUDO-OU-NADA: o responsável (DECOM, da semente
     * agenda ⇒ DED+DECOM) enxerga todos os dias, inclusive os de pivô disjunto. Era
     * test_lista_mostra_so_o_escopo_do_usuario, cuja premissa (interseção por objeto) a
     * decisão 4 do §5 matou.
     */
    public function test_lista_mostra_tudo_ao_responsavel(): void
    {
        // ... mesmo arranjo; trocar ->assertDontSee('AlheioDoDed') por ->assertSee('AlheioDoDed')
    }
```

> 🚫 **Não "consertar" tirando o DECOM da semente** para o `assertDontSee` voltar: divergiria de §4.2/§9.1. O deny do não-responsável é provado por `test_scope_do_tipo_e_tudo_ou_nada` (`assertSame(0, ...)` para o DEPRO) — **a confidencialidade continua coberta.**

- [ ] **Passo 2: Rodar e ver falhar**

```bash
docker compose exec -T app php artisan test --filter=AbaAgendaTest
```

Esperado: **FAIL** — `test_responsavel_ve_a_aba_com_a_agenda_vazia` (o `exists()` de hoje nega com a tabela vazia), `test_nao_responsavel_nao_ve_a_aba_mesmo_com_registros` e `test_scope_do_tipo_e_tudo_ou_nada`. **Os 3 casos apagados já saíram** — se algum aparecer no output, a deleção não foi aplicada.

- [ ] **Passo 3: O scope por regime**

Em `app/Models/AgendaDia.php`, substituir `scopeNoEscopoDe` (`:51-64`, docblock incluso):

```php
    /**
     * AgendaDia no escopo do usuário, por REGIME (Camada 1):
     * - "do tipo": TUDO-OU-NADA — responsável vê todos os registros (o pivô não é consultado);
     * - "por registro": o filtro de objeto de sempre (interseção de departamentos).
     * Fail-closed: recurso sem linha em tipos_conteudo ⇒ nenhum registro (I1/I2).
     */
    public function scopeNoEscopoDe(Builder $query, User $user): Builder
    {
        $acesso = app(AcessoPorTipo::class);

        return match ($acesso->regime('agenda')) {
            RegimeAcesso::DoTipo => $acesso->usuarioHabilitadoNoTipo($user, 'agenda')
                ? $query
                : $query->whereRaw('1 = 0'),
            RegimeAcesso::PorRegistro => $this->escopoPorRegistro($query, $user),
            null => $query->whereRaw('1 = 0'),
        };
    }

    /** Regime "por registro": o corpo de sempre, intacto. */
    private function escopoPorRegistro(Builder $query, User $user): Builder
    {
        $ids = $user->departamentos()->pluck('departamentos.id')->all();

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('departamentos', fn (Builder $q) => $q->whereIn('departamentos.id', $ids));
    }
```

Imports: `use App\Enums\RegimeAcesso;` e `use App\Support\Autorizacao\AcessoPorTipo;`.

- [ ] **Passo 4: A aba — o `calcular()` e o docblock INTEIRO**

Em `app/Support/Conta/AbaAgenda.php`, **substituir o docblock da classe (`:11-23`) inteiro** — ele afirma duas coisas hoje falsas: a "decisão 1" (registro no escopo), **revogada** por §6.3; e o "catch", que **não existe** no arquivo (é do spatie, `HasPermissions:260-267` — §3.7):

```php
/**
 * Fonte única do acesso à aba/rota "Agenda" no /minha-conta.
 * Usada por: a nav (mostrar/ocultar), o ContaController@agenda (abort_unless) e o mount do
 * componente. Aba visível ⇔ capacidade de ver + "sou responsável pelo tipo" (a pergunta única
 * do AcessoPorTipo). NÃO consulta registro: a config já restringe a quem mantém a agenda, e
 * consultar perpetuaria o furo do 1º registro (tabela vazia ⇒ aba some ⇒ ninguém cria o
 * primeiro dia).
 *
 * Memoizada por request via WeakMap pelo objeto User (a nav renderiza em TODA página
 * /minha-conta; auth()->user() devolve a mesma instância no request).
 *
 * Sinal de nav ⇒ checkPermissionTo, NUNCA hasPermissionTo: o fail-closed é do próprio spatie
 * (HasPermissions::checkPermissionTo captura PermissionDoesNotExist e devolve false). Com
 * hasPermissionTo, um ambiente sem CapacidadesSeeder derrubaria a nav de todas as páginas.
 */
```

E o `calcular()` (`:35-42`):

```php
    private static function calcular(User $user): bool
    {
        if (! $user->checkPermissionTo('agenda.ver')) {
            return false;
        }

        return app(AcessoPorTipo::class)->usuarioHabilitadoNoTipo($user, 'agenda');
    }
```

Imports: somar `use App\Support\Autorizacao\AcessoPorTipo;`; **remover `use App\Models\AgendaDia;`** (fica órfão).

- [ ] **Passo 5: Rodar e ver passar**

```bash
docker compose exec -T app php artisan test --filter="AbaAgenda|AcessoAgendaConta|AgendaContaEditarExcluir|AgendaContaCriar"
```

Esperado: **PASS**.

> ⚠️ **`AgendaContaCriar` entra no filtro de propósito** — o caso da lista quebra **nesta** task. Sem ele, o Passo 5 reportaria PASS com um vermelho na árvore e o Passo 6 **commitaria o vermelho**, que só apareceria na Task 4 — numa task que não o causou. Os outros 2 casos desse arquivo seguem verdes aqui (o sync do `AgendaConta` só sai na Task 4).

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Models/AgendaDia.php app/Support/Conta/AbaAgenda.php tests/Feature/Conta
git commit -m "feat(camada-1): scope e aba da Agenda passam a valer pela config do tipo (tudo-ou-nada, sem consultar registro)"
```

---

### Task 4: `AgendaConta` para de forçar DED+DECOM · `AgendaMantenedores` deletado

**Files:**
- Modify: `app/Livewire/Conta/AgendaConta.php`
- Delete: `app/Support/Agenda/AgendaMantenedores.php`
- Modify: `tests/Feature/Conta/AgendaContaCriarTest.php`, `tests/Feature/Autorizacao/AuditoriaAgendaPortaTest.php`

- [ ] **Passo 1: Remover o bloco do sync forçado**

No `try` (`:178-188`), **apagar `:181-187`** e **manter o `create` da `:179`**:

```php
        try {
            $registro = AgendaDia::create($dados);
        } catch (QueryException $e) {
```

Sai: o comentário do campo privilegiado (`:181`), `AgendaMantenedores::ids()` (`:182`), o `sync` (`:183`), o comentário do log manual (`:185`), o preparo de `$depois` (`:186`) e a chamada `registrarDepartamentosConteudo` (`:187`).

> **O helper `AuditoriaAutorizacao::registrarDepartamentosConteudo` FICA** — mas **pela razão certa**: 🚩 a frase do SPEC §6.6 ("o Evento ainda usa esse eixo") **é falsa** — `grep -rn "registrarDepartamentosConteudo" app/` dá **2 linhas** (declaração `AuditoriaAutorizacao:98` + o único chamador `AgendaConta:187`). Depois desta task o helper fica com **zero chamadores**. Mantê-lo é **decisão consciente** (o eixo depto↔conteúdo volta na Camada 4). **Apagar a chamada, não o helper — sem alegar um chamador que não existe.**

- [ ] **Passo 2: Os 2 imports órfãos — um o Pint NÃO pega**

```php
-use App\Models\Departamento;                     // :9  — o Pint NÃO pega. Remover à mão.
-use App\Support\Agenda\AgendaMantenedores;       // :10 — o Pint pega (aborta o CI).
```

**Ficam:** `AuditoriaAutorizacao` (`:11`, usado em `:52`) e `QueryException` (`:16`, usado em `:140` e `:188`).

- [ ] **Passo 3: Deletar a classe**

```bash
git rm app/Support/Agenda/AgendaMantenedores.php
```

- [ ] **Passo 4: Conferir os belts que NÃO podem sair**

🚫 **Preservar**: `:139` (o `update()` **preserva** os departamentos na edição), `:173` (`statusValido()` reasserido), `:174-176` (sem `agenda.editar` ⇒ status forçado a rascunho), `:204-211` (`dataJaUsada()`), `:213-219` (`statusValido()`).

- [ ] **Passo 4-bis: `AuditoriaAgendaPortaTest` — a decisão escrita (o Passo 1 mata este caso)**

`test_criar_pelo_site_grava_porta_perfil_e_log_de_depto` (`:53-86`) **morre no Passo 1**: sem a chamada da `:187`, não há mais entrada com `event` NULL (o `registrar()` privado loga **sem** `->event()`) ⇒ `:79` (`assertNotNull`) vermelha e `:80` estoura fatal em `null->properties`. **Vermelho ESPERADO.** 🚫 **Proibido devolver `registrarDepartamentosConteudo` para "consertar"** (decisão 7 / §6.4).

**Manter `:55-72` intactas** — a entrada **automática** do trait sobrevive e continua com `porta='perfil'` (é o que §6.6 preserva: `AgendaDia:144` + `tapActivity`). **Se `:70-72` ficarem vermelhas, aí sim é bug: E2 quebrou a trilha automática.**

**Renomear** (o nome nomeia a chamada removida) e **trocar `:74-85`** pela trava inversa:

```php
    public function test_criar_pelo_site_grava_porta_perfil_e_nao_loga_depto(): void
```
```php
        // O sync forçado de depto saiu (decisão 7 / §6.4): não há mais entrada manual.
        // Trava inversa — se alguém devolver o sync + registrarDepartamentosConteudo, isto fica vermelho.
        $this->assertSame(0, Activity::where('log_name', 'agenda')
            ->where('subject_id', $novo->id)
            ->whereNull('event')
            ->count(), 'sem sync de depto, não há entrada manual');
```

> `test_editar_nao_gera_log_manual_de_depto` (`:88-107`) fica **VERDE e inalterado** (já contava 0). O import `Departamento` (`:9`) **continua usado** em `:55`, `:90-91` — **não remover sem grep**.

- [ ] **Passo 5: `AgendaContaCriarTest` — o caso do sync**

`test_criar_forca_departamentos_ded_e_decom` (`:45-70`) — **RENOMEAR e reescrever**, não deletar: é o único teste que prova o ciclo pela **UI** (registro nascido do componente Livewire, não da factory) — o `test_i9_...` (Task 2) usa `AgendaDia::factory()` e **não** cobre este eixo. A `:62` morre. Vira "nasce sem pivô e o responsável edita" (I9). 🚫 **Não reintroduzir o sync.**

```bash
docker compose exec -T app php artisan test --filter="AgendaConta|AuditoriaAgendaPorta|AbaAgenda"
```

Esperado: **PASS** — depois de aplicados os Passos 4-bis e 5.

> A trilha `log_name='agenda'` com `porta='perfil'` **continua** (é do trait do model) — some apenas a entrada **manual** do vínculo, que não tem mais o que logar.

- [ ] **Passo 6: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add -A app/Livewire/Conta/AgendaConta.php app/Support/Agenda tests/
git commit -m "feat(camada-1): AgendaConta para de forçar DED+DECOM e AgendaMantenedores é deletado"
```

---

### Task 5: O campo "Departamentos" sai dos 4 forms

**Files:**
- Modify: `app/Filament/Schemas/AgendaDiaForm.php` · `Resources/Palestras/PalestraResource.php` (`:158-164`) · `Resources/Posts/PostResource.php` (`:229-235`) · `Resources/Palestrantes/PalestranteResource.php` (`:126-135`) · `app/Livewire/Conta/AgendaConta.php:58`
- Modify: `tests/Feature/Filament/AgendaDiaFormSchemaTest.php` + os 4 ResourceTests

🚫 **NÃO tocar:** `EventoForm.php:107-112` (é o Evento, **permanece**) · `UserResource.php:107-111` (vínculo do usuário).

- [ ] **Passo 1: `AgendaDiaForm` — o bloco é `:64-73`**

Remover o **`if` inteiro** (`:64-73`, incluindo o `}` da `:73` — cortar em `:72` ⇒ **Parse error**) e o parâmetro da `:26`:

```php
-    public static function schema(bool $comDepartamentos = true): array
+    public static function schema(): array
```

✅ **Manter `use Filament\Forms\Components\Select;`** — em uso na `:36`. Atualizar os docblocks obsoletos: `AgendaDiaForm.php:19-20` e `AgendaConta.php:26-30`.

- [ ] **Passo 2: Os 3 chamadores do parâmetro**

| Chamador | Ação |
|---|---|
| `app/Livewire/Conta/AgendaConta.php:58` | `schema(comDepartamentos: false)` → **`schema()`** (senão `Error: Unknown named parameter`) |
| `app/Filament/Resources/Agenda/AgendaDiaResource.php:38` | `schema()` sem argumento — **não muda** |
| `tests/Feature/Filament/AgendaDiaFormSchemaTest.php:23,28,29` | **reescrito** (Passo 4) |

- [ ] **Passo 3: Os 3 Resources**

- `PalestraResource.php:158-164` e `PostResource.php:229-235` — remover só o `Select` (os containers têm irmãos).
- `PalestranteResource.php:126-135` — **remover a `Section::make('Departamentos')` inteira**: o `Select` é o **único filho** (verificado: schema abre na `:127`, o array fecha na `:135`) — sobraria Section vazia.

Remover os imports que ficarem órfãos — **conferir um a um** (`Select`/`Section` podem seguir em uso).

- [ ] **Passo 4: `AgendaDiaFormSchemaTest` — reescrever como guarda anti-regressão**

**Decisão (Armadilha 9): reescrever, não deletar.** Apagar `test_schema_padrao_inclui_departamentos` (`:21-24`) e `test_schema_do_site_omite_departamentos` (`:26-34`); o helper `temSelectDepartamentos()` (`:14-19`) **fica**:

```php
    /** Trava anti-regressão: 'departamentos' é campo privilegiado e NÃO pode voltar ao form. */
    public function test_schema_nao_expoe_departamentos(): void
    {
        $this->assertFalse($this->temSelectDepartamentos(AgendaDiaForm::schema()));
    }
```

- [ ] **Passo 5: Os 8 métodos dos ResourceTests morrem inteiros**

**São 8, não 4.** Os `test_exige_departamento` também morrem **inteiros**: sem a asserção ficam com **zero asserções** (PHPUnit marca *risky*) e o nome **mente** (o campo não está mais no schema, logo não existe o erro que ele nomeia).

| Arquivo | Método | Linhas (conferência) | Assertiva |
|---|---|---|---|
| `PalestraResourceTest` | `test_salva_departamento` | 249-266 | `:265` |
| `PalestraResourceTest` | `test_exige_departamento` | 268-281 | `:280` |
| `PostResourceTest` | `test_salva_departamento` | 354-369 | `:368` |
| `PostResourceTest` | `test_exige_departamento` | 371-382 | `:381` |
| `PalestranteResourceTest` | `test_salva_departamento` | 148-162 | `:161` |
| `PalestranteResourceTest` | `test_exige_departamento` | 164-174 | `:173` |
| `AgendaDiaResourceTest` | `test_salva_departamento` | 62-75 | `:74` |
| `AgendaDiaResourceTest` | `test_exige_departamento` | 77-86 | `:85` |

> ⚠️ **Ancorar pela assinatura, não pelo número** — os números deslocam a cada deleção no mesmo arquivo, e a tabela do SPEC mistura **duas convenções de fronteira** (com/sem `~`). **Apagar o método + uma linha em branco adjacente**, para não deixar brancos consecutivos. O Pint ao final resolve o resíduo.

🚫 **NÃO reintroduzir o sync do pivô** (decisão 7 / §6.4).

- [ ] **Passo 6: Limpar os 23 `fillForm`**

Remover a linha `'departamentos' => [...]` dos `fillForm` dos métodos **que sobrevivem**. Verificadas contra `5a6a9ba` — batem 100%:

| Arquivo | Linhas |
|---|---|
| `PalestraResourceTest` | 40, 73, 97, 115, 147, 172, 191, 210, 231 |
| `PostResourceTest` | 56, 73, 90, 110, 130, 156, 173, 278 |
| `PalestranteResourceTest` | 79, 92, 107, 138 |
| `AgendaDiaResourceTest` | 35, 56 |

🚫 **Não tocar:** `EventoResourceTest:43`, `UsuarioResourceTest:62`, `AuditoriaUserResourceTest:45,94`.

- [ ] **Passo 7: Rodar os testes de Filament**

```bash
docker compose exec -T app php artisan test --filter="PalestraResource|PostResource|PalestranteResource|AgendaDiaResource|AgendaDiaFormSchema|EventoResource|UsuarioResource"
docker compose exec -T app php artisan test --filter=ConteudosTemDepartamento
grep -rn "comDepartamentos" app/ tests/ || echo "OK: zero ocorrências"
```

Esperado: **PASS** nos dois + grep vazio. `ConteudosTemDepartamentoTest` prova que o pivô e a relação continuam **existindo e íntegros** (§6.4) — **se ficar vermelho, o vermelho é o bug** (Armadilha 7).

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament app/Livewire/Conta/AgendaConta.php tests/Feature/Filament
git commit -m "feat(camada-1): campo Departamentos sai dos 4 tipos do tipo (o Evento mantém)"
```

---

### Task 6: Fechamento — doc, os 5 aceites e a verificação REAL no dev

> **A lição do E1 tem irmã aqui.** Lá foi "a suíte não prova que a migration roda" (o índice de 64 chars passou verde no SQLite e quebrou no MySQL). Aqui é: **a suíte não prova a TELA nem o fluxo real.** E2 não tem migration, mas mexe no `/minha-conta` — onde a autorização morde. Os Passos 5-7 não são opcionais.

- [ ] **Passo 1: Pint em tudo**

```bash
docker compose exec -T app ./vendor/bin/pint --test
```

- [ ] **Passo 2: Suíte completa**

```bash
docker compose exec -T app php artisan test 2>&1 | tail -5
```

Esperado: **verde**. **Reportar o número final e a conta** (848 − métodos apagados + novos).

- [ ] **Passo 3: A doc que o E2 torna falsa**

O E2 deixaria `DATA-MODEL.md:472` citando uma classe **deletada na Task 4**. Não é doc nova (regra global 11): é **correção de doc existente que o próprio E2 falsifica**, e o `CLAUDE.md` manda manter esses documentos cientes.

`DATA-MODEL.md:463-476` — 4 pontos:
- `:465-466` "aba condicional (capacidade `agenda.ver` **+** existir registro no escopo)" → **"capacidade `agenda.ver` + o usuário ser responsável pelo tipo (§6.3 revogou a 'decisão 1' da Fase D)"**;
- `:467-468` "`AgendaDiaForm::schema(bool $comDepartamentos = true)` — o site chama com `false`" → **"`AgendaDiaForm::schema()` — sem parâmetro; o campo Departamentos não existe mais no schema"**;
- `:470` "**2 campos privilegiados**" → **"1 campo privilegiado, forçado no servidor (nunca do POST)"**;
- `:471-474` (bloco `departamentos`/`AgendaMantenedores`) → **apagar**, trocando por: **"`departamentos` deixou de ser gravado — o pivô `departamento_agenda_dia` está congelado (§6.4): nem lido, nem gravado, nada apagado. O acesso vem da config por tipo."**

`ROADMAP.md:216` — "(`departamentos` = DED+DECOM; `status` — ...)" → **"(`status` — quem não tem `agenda.editar` só cria rascunho; `departamentos` deixou de ser forçado no E2)"**.

🚫 **Fora do escopo:** `ROADMAP.md:21`/`:226` ("Fase E") e `:149` ("252 testes") são **dívida do E1**, não falsidade criada pelo E2. Corrigir aqui seria escopo vazando.

```bash
git add DATA-MODEL.md ROADMAP.md
git commit -m "docs(camada-1): DATA-MODEL e ROADMAP param de descrever o regime por registro na Agenda"
```

⚠️ **Este passo roda ANTES do Passo 4** — senão o aceite (c) ampliado reprova o PR.

- [ ] **Passo 4: Os 5 aceites, como comando**

```bash
echo "=== (a) O Evento saiu intacto — esperado: exatamente 2 linhas '+', nenhuma '-'"
git diff -U0 origin/main -- tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php \
  | grep -E '^[-+]' | grep -vE '^(\+\+\+|---)'

echo "=== (b) Os invariantes"
docker compose exec -T app php artisan test --filter=CamadaUmFiltroPorTipoTest 2>&1 | tail -2

echo "=== (c) AgendaMantenedores deletado (inclusive na doc)"
test ! -f app/Support/Agenda/AgendaMantenedores.php && echo "OK: arquivo não existe"
grep -rn "AgendaMantenedores" app/ tests/ DATA-MODEL.md ROADMAP.md || echo "OK: nenhuma referência"

echo "=== (d) Nenhum fallback de null"
grep -rnE '\?\?\s*RegimeAcesso|\?->regime\s*\?\?|regime\([^)]*\)\s*\?\?' app/ || echo "OK: nenhum ?? de regime"
grep -rn "null =>" app/Policies/Concerns/AutorizaPorDepartamento.php app/Support/Autorizacao/AcessoPorTipo.php app/Models/AgendaDia.php

echo "=== (e) O pivô só autoriza no ramo PorRegistro — esperado: 2 linhas"
grep -rn "objetoNoDepartamentoDoUsuario" app/
```

**Critério:** (a) = exatamente as 2 linhas `+`, nenhuma `-`; (c) = os dois OK; (d) = o 1º vazio e todo `null =>` negando; (e) = **exatamente 2 linhas**, o uso **dentro** do braço `PorRegistro`.

- [ ] **Passo 5: Verificação real no dev — o E2E do `/minha-conta`**

```bash
docker compose restart app worker
```

Como **diretor de DED ou DECOM**, em `http://localhost:8000/minha-conta`:
- a aba **Agenda** aparece;
- **criar** um dia → salva; **editar** e **excluir** → funcionam;
- a listagem mostra **todos** os dias (tudo-ou-nada), inclusive os de pivô disjunto.

- [ ] **Passo 6: A trilha continua gravando com a porta certa**

```bash
docker compose exec -T app php artisan tinker --execute="
\$a = Spatie\Activitylog\Models\Activity::where('log_name','agenda')->latest('id')->first();
if (! \$a) { echo 'SEM TRILHA — rode o E2E do Passo 5 antes'.PHP_EOL; }
else { echo \$a->log_name.' | porta='.(\$a->properties['porta'] ?? 'SEM PORTA').' | '.\$a->description.PHP_EOL; }
"
```

Esperado: `agenda | porta=perfil | ...`.

- [ ] **Passo 7: O furo do `criar` fechou na prática (I3) — os 10 diretores medidos**

O §4.3 mediu **10 diretores** que hoje **criam e não conseguem editar** (Charles/DEPAE, Cris/DIJ, Iara/DIJ, Daniel/DDA, **Marcio/DDA**, Marli/DDA, Maria Aparecida/DEPRO, Emanuela/DEPRO, Gaspar/DEMAPA, Salvador/DEMAPA):

```bash
docker compose exec -T app php artisan tinker --execute="
\$criam = 0; \$fora = 0;
foreach (App\Models\User::all() as \$u) {
    if (! \$u->hasPermissionTo('agenda.criar')) { continue; }
    if (! \$u->departamentos()->exists()) { continue; }
    if (Illuminate\Support\Facades\Gate::forUser(\$u)->check('criar', App\Models\AgendaDia::class)) { \$criam++; }
    else { \$fora++; }
}
echo 'criam agora: '.\$criam.' | perderam o criar: '.\$fora.PHP_EOL;
"
```

Esperado: **`criam agora: 6 | perderam o criar: 10`** — os 6 de DED/DECOM seguem criando (e **editam o que criam**); os **10** perderam o `criar` que nunca deveriam ter tido. **É a correção desejada, medida.**

> **Universo verificado no dev:** 16 usuários com `agenda.criar` + vínculo, **zero admins** entre eles (o `hasPermissionTo` filtra antes do `Gate::before`). Se o número divergir de **6/10**, **PARE E REPORTE** — a config do dev ou a medição do §4.3 mudou. **Não "ajustar" o código para bater o número.**

- [ ] **Passo 8: Os dados do legado estão intactos**

```bash
docker compose exec -T app php artisan migrate --pretend | tail -3
docker compose exec -T app php artisan tinker --execute="echo 'AgendaDia: '.App\Models\AgendaDia::count().' | Palestra: '.App\Models\Palestra::count().' | Post: '.App\Models\Post::count().' | Palestrante: '.App\Models\Palestrante::count().' | Evento: '.App\Models\Evento::count().PHP_EOL; echo 'pivôs congelados (intactos): '.DB::table('departamento_palestra')->count().' palestra, '.DB::table('departamento_post')->count().' post, '.DB::table('departamento_agenda_dia')->count().' agenda, '.DB::table('departamento_palestrante')->count().' palestrante'.PHP_EOL;"
```

Esperado: **sem migrations pendentes** e `AgendaDia: 123 | Palestra: 127 | Post: 45 | Palestrante: 59 | Evento: 56`. Os **354 registros de pivô** dos 4 tipos **continuam lá** — congelados, não apagados.

- [ ] **Passo 9: Push e PR**

```bash
git push -u origin camada-1-e2-filtro
gh pr create --title "feat(camada-1): E2 — a troca do filtro (acesso passa a valer por responsável do tipo)" --body "$(cat <<'CORPO'
**É onde o acesso muda.** O filtro sai de "o objeto está num departamento em comum comigo" (por objeto) para "eu estou num departamento responsável pelo TIPO" (a config do E1). O **Evento** fica no regime antigo (`por_registro`), intacto.

Spec: `docs/superpowers/specs/2026-07-16-camada-1-configuracao-acesso-por-tipo.md` (§8, E2).
Plano: `docs/superpowers/plans/2026-07-16-camada-1-e2-troca-do-filtro.md`.

## Entrega
- trait com os dois ramos lendo a config + `criar` por regime (**I3**: fecha o furo dos 10 diretores medidos no §4.3)
- `AgendaDia::scopeNoEscopoDe` por regime (tudo-ou-nada no "do tipo")
- `AbaAgenda` sem a query de registro + docblock reescrito (a "decisão 1" da Fase D foi revogada; o "catch" nunca existiu)
- campo "Departamentos" sai dos 4 forms (no Palestrante, a `Section` inteira); **o Evento mantém**
- `AgendaConta` para de forçar DED+DECOM · **`AgendaMantenedores` deletado**
- pivô `departamento_<x>` **congelado**: nem lido, nem gravado — **354 registros intactos** (§6.4)
- `DATA-MODEL.md`/`ROADMAP.md` param de descrever o regime "por registro" na Agenda

## Aceite (todos por comando — ver §"Os 5 aceites")
- **(a)** `git diff -U0 origin/main -- tests/.../EventoPolicyCapacidadeTest.php | grep -E '^[-+]' | grep -vE '^(\+\+\+|---)'` = exatamente as 2 linhas do `setUp`, **nenhuma linha `-`** — nenhuma asserção tocada
- **(b)** `CamadaUmFiltroPorTipoTest` (13) cobre I1/I2/I3/I4/I6/I9 + os **3 estados do pivô** (vazio, disjunto, coincidente)
- **(c)** `AgendaMantenedores` não existe e não é referenciado (inclusive na doc)
- **(d)** nenhum fallback de `null` — todo `null =>` nega (§12.8)
- **(e)** `objetoNoDepartamentoDoUsuario` só é alcançável pelo ramo `PorRegistro` (2 linhas no grep, contra 16 hoje)

## Verificado no dev, não só na suíte
- E2E do `/minha-conta` como diretor de DED (criar/editar/excluir) + trilha `log_name='agenda'`, `porta='perfil'`
- **I3 medido:** 6 diretores criam (e editam o que criam), **10 perderam o `criar`** que nunca deveriam ter tido
- dados do legado intactos: 123/127/45/59/56

## Divergências SPEC × código encontradas ao planejar
- **Erro:** §6.6 dizia manter o helper `registrarDepartamentosConteudo` porque "o Evento ainda usa esse eixo" — **falso**: o único chamador era o `AgendaConta`. O helper fica por decisão consciente (o eixo volta na Camada 4), não por uso.
- **Erro:** §10.4 listava `ConteudosTemDepartamentoTest` em "Muda" — **não muda**, e é o guardião do §6.4.
- **Defasagem (não erro):** §3.3 aponta o `Gate::before` em `AppServiceProvider:57-59`; está na `:65` — o **E1** somou 6 linhas ao `register()` e empurrou o `boot()`. O SPEC estava certo quando escrito; citação `arquivo:linha` envelhece a cada PR.

## Follow-up registrado (fora do escopo — decisão do dono)
- **A1:** `TiposConteudoSeeder:57-63` devolve `[]` para sigla inexistente **sem lançar**; com o insert-only, o tipo nasce fail-closed e resemear não repara. É **risco de produção** no caminho do cutover do §13.1 (dívida do E1, não do E2) — ver a nota 🔭 na Armadilha 1 do plano.

## ⚠️ Cutover de PROD
Rodar o `TiposConteudoSeeder` é **passo obrigatório** (§13.1) — vale para os **5** recursos, inclusive o Evento. Sem ele, I2 nega tudo para não-admin (fail-closed). O seeder é insert-only: rodá-lo é seguro e idempotente.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
CORPO
)"
```

- [ ] **Passo 10: Só mesclar com o CI verde no ÚLTIMO commit**

```bash
gh pr checks --watch
```

**Não** mesclar com check `pending`. E só com o **"go" do dono**.

---

## Notas para o executor

**Dependências.** Caminho crítico: **0 → 1 → 2**. A Task 1 é **pré-requisito de todas** — é o que garante que vermelho nas Tasks 2-5 significa **regra**, nunca **semente**. Depois da Task 2, as Tasks **3, 4 e 5** são independentes entre si. A Task 6 fecha.

**O que E2 NÃO faz** (vazar reprova o PR):
- **migration nenhuma** — se algo pedir, pare e reporte;
- **não** cria `EventoFactory` nem qualquer artefato novo em `app/`/`database/`;
- **não** toca `EventoPolicy::view`/`viewAny` (visibilidade — Camada 4);
- **não** toca `Gate::before`, `config/permission.php`, `departamento_usuario` nem `UserResource:107-111`;
- **não** apaga pivô, tabela nem a relação `departamentos()` dos models (§6.4);
- **não** cria o 1º `getEloquentQuery()` escopado nem o 1º `canAccess()` (§3.5/§11.6);
- **não** escreve config por comando `cema:*` (I8 — a tela é a única escritora);
- **não** cria doc nova nem mexe em `PROJECT.md`/`CLAUDE.md`: o único toque em doc é a **correção** de `DATA-MODEL.md:463-476` e `ROADMAP.md:216`, que o E2 torna falsos.

**Se um teste ficar vermelho porque `tipos_conteudo` está vazia, semeie o teste** — com a ordem correta (`EstruturaCemaSeeder` **antes**; Armadilha 1). 🚫 **Jamais** afrouxar `AcessoPorTipo` nem devolver o fallback ao pivô: faria recurso sem linha **ABRIR** e reabriria os 354 registros de eco do backfill como fonte de autorização — e o teste de I2, se escrito só sobre `agenda`, continuaria **verde**, escondendo o furo. Daí o I2 cobrir os 5 recursos.

**Se algo divergir do código real, PARE E REPORTE** em vez de improvisar.
