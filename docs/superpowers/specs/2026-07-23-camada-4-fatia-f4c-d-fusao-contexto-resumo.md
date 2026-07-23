# Spec — Camada 4 · Fatia F4c-D · Fusão `contexto`→`resumo` + validação em pt-BR

- **Base:** `origin/main` = `4e466c9` (F4c-AC mesclada pelo PR #44).
- **Branch:** `camada-4-fatia-f4c-d-fusao-resumo`.
- **Formato:** 1 PR, 2 blocos independentes que tocam o mesmo terreno.
- **Data:** 2026-07-23.
- **Autor:** Thiago Mourão — https://github.com/MouraoBSB

---

## 0. Recorte: o que esta fatia fecha

**Bloco 1 — a fusão.** `contexto` (criado na 2A como "faixa editorial") e `resumo` (criado na
F4c-AC, importado do `post_excerpt` do legado) são a **mesma coisa**: um texto editorial curto
que abre a mensagem. Hoje eles renderizam em **dois lugares diferentes** da mesma página — a
faixa "Contexto —" abaixo do hero e o lead dourado dentro do card. O dono viu os dois com o
mesmo texto e mandou fundir: sobrevive `resumo`, a coluna `contexto` é dropada, e o único
lugar de renderização passa a ser o lead.

**Bloco 2 — a validação em pt-BR.** A mensagem *"The slug has already been taken."* aparece em
inglês no `/admin`. A causa raiz medida: **não existe `lang/pt_BR/validation.php`**, então toda
validação nativa cai no `fallback_locale = en`. A fatia cria os arquivos de idioma que faltam
(`validation.php`, mais `auth.php` e `passwords.php` pelo D9 — ver §11) e acrescenta a frase
específica do slug. **Não é uma mudança do módulo Mensagens: ela vale para o site inteiro.**

**Fica de fora:** o bloco B (autores espirituais), slug auto-incremental no `/admin`, e os
follow-ups herdados da F4c-AC. Ver §10.

---

## 1. Contexto e objetivo

Esta fatia não dá capacidade nova a ninguém. Ela **desfaz uma duplicação** que a própria
Camada 4 criou em duas fatias diferentes, e **mata uma causa raiz** de interface em inglês.

Objetivo declarado, em uma frase por bloco:

- **1** — que exista **um só** campo de texto editorial na mensagem, **um só** lugar onde ele
  aparece, e que o texto já escrito como `contexto` não se perca no caminho.
- **2** — que o usuário do `/admin` e do `/minha-conta` leia mensagens de validação em
  português, e que a frase do slug ensine o que fazer.

---

## 2. Decisões travadas (não reabrir)

| # | Decisão | Origem |
|---|---|---|
| **D1** | Sobrevive `resumo`; `contexto` é **eliminado** (coluna dropada). Medido: `resumo` 152 · `contexto` 2 · só-`contexto` 1. | Dono, kickoff |
| **D2** | O médium **mantém** o campo: `resumo` entra no `schemaMedium`. **Revoga o I11 da F4c-AC**, cujo argumento ("o médium já tem `contexto`") caiu junto com o `contexto`. | Dono, kickoff |
| **D3** | Renderização final: **um lugar só**, o lead dourado acima do corpo. A faixa "Contexto —" é **removida**. | Dono, kickoff |
| **D4** | Slug em pt-BR: `->validationMessages` no campo **e** `lang/pt_BR/validation.php` completo. O dono reviu a decisão original (que era só cirúrgica) depois que a premissa `APP_LOCALE='en'` caiu — ver §3.4. | Dono, P1 |
| **D5** | Slug "inteligente" no `/admin` (auto-sufixo até não colidir) — **backlog**, não implementar. A peça já existe: `SlugMensagem::unico()`. | Dono, kickoff + §3.5 |
| **D6** | Rótulo **"Resumo"** limpo nos **três** schemas (sai o parêntese "(texto editorial)"), com **helper por público**: admin/curadoria mantêm a frase que cita a importação; o médium recebe uma própria. Um nome só para a coluna — o glossário de auditoria diz "Resumo". | Dono, P2 |
| **D7** | `contexto` sai do glossário de auditoria junto com o `logOnly`. A paridade `≡` do `GlossarioCamposParidadeTest` é **preservada, não afrouxada**. Consequência aceita: 3 entradas antigas de `activity_log` perdem essa linha na tela do DEPAE. | Dono, P3 |
| **D8** | **DUAS** migrations (funde → dropa), e não uma. **Emenda ao O1 do kickoff**, com o dado que o motiva em §3.3 e §5.1. | Proposta — §11 |
| **D9** | O bloco 2 cobre **três** arquivos de idioma: `validation.php`, `auth.php` e `passwords.php`. Criar só o primeiro deixa o `/entrar` meio-traduzido. | Proposta — §11 |
| **D10** | A copy de [autores/index.blade.php:61](resources/views/autores/index.blade.php#L61) é ajustada, e a divergência em relação ao handoff de design é declarada no PR. | Proposta — §11 |

---

## 3. Terreno confirmado por medição

Nada nesta seção é presumido. O dev foi medido por consulta somente-leitura em 2026-07-22/23;
o código, por leitura de `origin/main` = `4e466c9`; o comportamento dos drivers, por execução
real em SQLite `:memory:` descartável e por leitura do `vendor/`.

### 3.1 Dev — o dado que a fusão move

| Fato | Valor |
|---|---|
| `mensagens` | **181** linhas |
| `resumo` preenchido | **152** — máximo **1164** caracteres, **0** acima de 1500 |
| `contexto` preenchido | **2** |
| `contexto` preenchido **e** `resumo` vazio | **1** — é tudo o que a migration copia |
| `activity_log` `log_name='mensagem'` | **6** entradas; **3** citam a chave `contexto` (ids 21, 24, 26) |

As duas linhas com `contexto` são de teste do dono (texto *Mussum Ipsum*):

| id | Título | `contexto` | `resumo` | Destino |
|---|---|---|---|---|
| **191** | Nova Mensagem Teste | 329 chars | **NULL** | **copiado** para `resumo` |
| **192** | Nova Mensagem teste | 279 chars | preenchido e **idêntico** ao `contexto` (comparação `===` deu verdadeiro) | descartado — nada se perde |

Ambas têm **`wp_id = NULL`**, logo estão fora do alcance de `cema:importar-resumos` (que casa
por `wp_id`) — a fusão não disputa dado com o importador **neste** acervo. A regra geral, que
vale para qualquer ambiente novo, está em §6.

O maior `contexto` do dev tem 329 caracteres, bem abaixo do `->maxLength(1500)` do `resumo`:
**nenhuma linha fundida fica impossível de salvar** no formulário.

### 3.2 Os três sentidos de "contexto" — a armadilha nº 1

Um `grep` cego por `contexto` **mata a auditoria do projeto**. São três coisas diferentes:

| Sentido | Onde | Nesta fatia |
|---|---|---|
| **Campo** — a coluna `contexto` de `mensagens` | model, glossário, 3 schemas, factory, view, testes | **morre** |
| **Método** — `AuditoriaAutorizacao::contexto()` (porta + IP + user-agent) | [AuditoriaAutorizacao.php:34](app/Support/Autorizacao/AuditoriaAutorizacao.php#L34), [:157](app/Support/Autorizacao/AuditoriaAutorizacao.php#L157), [Mensagem.php:289](app/Models/Mensagem.php#L289), [User.php:140](app/Models/User.php#L140), [AgendaDia.php:177](app/Models/AgendaDia.php#L177), `AuditoriaHelperTest:48`, `HistoricoMensagemTest:132` | **NÃO TOCAR** |
| **Prosa** — a palavra em texto de interface | [autores/index.blade.php:61](resources/views/autores/index.blade.php#L61) ("…a data de recebimento, o formato e o contexto de cada comunicação…") | ver D10 |

Mesmo padrão do I16 da F4c-AC (os três sentidos de "pictografia").

### 3.3 Migrations — o que os drivers realmente fazem

Verificado no `vendor/` **e executado** em SQLite `:memory:` descartável (o banco de dev não
foi tocado):

- **`UPDATE` coluna-para-coluna funciona nos dois drivers.** `compileUpdateColumns`
  (`Query/Grammars/Grammar.php:1368-1373`) usa `parameter()`, que devolve o valor **cru** quando
  o argumento é uma `Expression` (`Grammar.php:218-221`), e `cleanBindings`
  (`Query/Builder.php:4727-4735`) tira o `Expression` dos bindings. Isso é da **grammar base** —
  não é código de MySQL nem de SQLite. Execução real em SQLite com 5 linhas de fixture:
  `NULL` → copiou, `''` → copiou, `resumo` já preenchido → preservado, `contexto` vazio →
  ignorado.
- ⚠️ **O identificador vai NU** em `DB::raw('contexto')`. Aspas duplas quebram no MySQL (vira
  literal de string sem `ANSI_QUOTES`); crase quebra no SQLite. Sem aspas, os dois leem a coluna.
  Será a **primeira** ocorrência de `DB::raw` do projeto — não há nenhuma hoje.
- **`dropColumn` em SQLite é nativo no Laravel 13**, sem `doctrine/dbal`.
  `SQLiteGrammar::compileDropColumn` (`:525-538`) emite `alter table … drop column` para SQLite
  ≥ 3.35; o container tem **3.46.1**. `doctrine/dbal` **não** está no `composer.json` nem no
  `composer.lock` (só `doctrine/inflector` e `doctrine/lexer` em `vendor/`).
- **Pré-condição do drop nativo satisfeita:** `contexto` **não participa de nenhum índice**. Os
  índices de `mensagens` são `wp_id` unique ([:15](database/migrations/2026_07_18_000001_create_mensagens_table.php#L15)),
  `slug` unique ([:17](database/migrations/2026_07_18_000001_create_mensagens_table.php#L17)),
  `status` ([:29](database/migrations/2026_07_18_000001_create_mensagens_table.php#L29)) e
  `data_recebimento` ([:30](database/migrations/2026_07_18_000001_create_mensagens_table.php#L30)).
- **Migration NÃO roda em transação** em nenhum dos dois drivers: `Migrator.php:448-451` só
  envolve em transação quando `supportsSchemaTransactions()`, e `Schema/Grammars/Grammar.php:31`
  define `$transactions = false` — só Postgres e SQL Server sobrescrevem. **É o argumento do D8.**
- **Ordem confirmada:** `2026_07_23_*` > `2026_07_22_*`, então o drop roda **depois** do
  `->after('contexto')` da migration do `resumo` — um `migrate` do zero (como a suíte faz)
  funciona. No SQLite o `after()` é **ignorado em silêncio**: `SQLiteGrammar.php:20` não lista
  o modificador `After` e não existe `modifyAfter` nessa grammar.
- **Precedente literal do par no projeto:**
  [2026_06_29_000001_mover_fotos_palestrante_para_media_library.php](database/migrations/2026_06_29_000001_mover_fotos_palestrante_para_media_library.php)
  (migra o dado) + [2026_06_29_000002_drop_foto_from_palestrantes.php](database/migrations/2026_06_29_000002_drop_foto_from_palestrantes.php)
  (dropa a coluna, `down()` recria vazia). O molde de idioma dominante é
  [2026_07_22_000002_renomeia_colecao_pictografia_para_imagens.php:19-22](database/migrations/2026_07_22_000002_renomeia_colecao_pictografia_para_imagens.php#L19-L22):
  `DB::table(...)->where(...)->update([...])`, Query Builder puro, com a facade importada.

### 3.4 i18n — a causa raiz, corrigida

O kickoff afirmava `APP_LOCALE='en'`, lendo o **default** de `config/app.php:81`
(`env('APP_LOCALE', 'en')`) como se fosse o valor efetivo. **Falso:**

| Fato | Valor |
|---|---|
| `.env:9` e `.env.example:9` | `APP_LOCALE=pt_BR` |
| `.env:10` | `APP_FALLBACK_LOCALE=en` |
| `lang/pt_BR/` | contém **um** arquivo: `pagination.php` |
| `lang/pt_BR/validation.php` · `auth.php` · `passwords.php` | **não existem** |
| `phpunit.xml` | **não** sobrescreve `APP_LOCALE` ⇒ o arquivo novo vale **também na suíte** |
| CI | `cp .env.example .env` ([ci.yml:57-58](.github/workflows/ci.yml#L57-L58)) ⇒ vale no CI |
| Pacote de tradução instalado | **nenhum** (sem `laravel-lang` no `composer.json`/`composer.lock`) |
| `laravel/framework` | **v13.17.0** (`composer.lock`) |
| Canônico `en/validation.php` | **109 chaves** de primeiro nível = 107 regras + `custom` + `attributes` |
| Testes que asseram texto de validação | **zero** — o único texto comparado na suíte vem da constante `MensagemForm::MSG_NIVEL_OBRIGATORIO` |

**O `->unique()` do Filament não é a regra `unique` do Laravel.** É um closure que faz
`$fail(__($component->getValidationMessages()['unique'] ?? 'validation.unique', ['attribute' => $component->getValidationAttribute()]))`
(`filament/forms/src/Components/Concerns/CanBeValidated.php:621`). Duas consequências que a
fatia precisa honrar:

1. **O `:attribute` vem do `->label()`**, com `Str::lcfirst` (`CanBeValidated.php:767-780`).
   Logo, **dentro de qualquer schema Filament** — inclusive os do site (`AgendaConta`,
   `CuradoriaConta`, `MensagensConta`) — a seção `attributes` do arquivo de idioma é **inerte**:
   o array inline do Filament entra em `$livewire->validate($rules, $messages, $attributes)`
   (`filament/schemas/src/Concerns/CanBeValidated.php:130`) e tem precedência
   (`FormatsMessages.php:287` é consultado antes de `:294`).
2. **`->validationMessages()` precede o arquivo de idioma.** Fazer os dois no mesmo campo é
   deliberado (frase melhor), não redundância — mas quem esperar ver a frase genérica naquele
   campo não vai vê-la.

**Onde a seção `attributes` importa de verdade** — as telas fora do Filament, onde o nome cru
da coluna vira a frase:

| Tela | Campos exibidos | Sem `attributes` |
|---|---|---|
| `/entrar` | `email` | "O campo email…" (o rótulo da tela é "E-mail") |
| `/cadastro` | `name`, `email`, `password` | "O campo name…" (rótulo "Nome") |
| `/esqueci-a-senha` | `email` | idem login |
| `/redefinir-senha/{token}` | `email`, `password` | idem |
| **`/minha-conta/perfil`** | `name`, `data_nascimento`, `endereco`, `whatsapp`, `whatsapp_publico`, `foto` | **"data nascimento"**, **"endereco"** sem cedilha, "whatsapp publico" — é esta tela que justifica a seção sozinha |

**Ganho não previsto pelo kickoff:** [AgendaDiaForm.php:36](app/Filament/Schemas/AgendaDiaForm.php#L36)
tem `->unique(` e é consumido **fora** do painel por [AgendaConta.php:56](app/Livewire/Conta/AgendaConta.php#L56).
Hoje o membro lê a frase em inglês em `/minha-conta/agenda`; o arquivo conserta isso de brinde.
Ou seja, o bloco 2 **não** é "a frase do slug no `/admin`" — é uma troca de idioma em telas que
ninguém listou para revisão. Vai declarado no PR.

**Molde de `validationMessages` já existente no projeto** (só há dois call sites):
[AgendaMetaMesResource.php:72-74](app/Filament/Resources/Agenda/AgendaMetaMesResource.php#L72-L74)
(*"Já existe um tema cadastrado para este mês e ano."*) e
[MensagemForm.php:107](app/Filament/Schemas/MensagemForm.php#L107) (com a frase numa constante
pública, `:39`).

### 3.5 O slug — por que o D5 é backlog e não trabalho

[SlugMensagem::unico()](app/Support/Mensagens/SlugMensagem.php#L20-L37) **já é** o slug
inteligente: `Str::slug($titulo)` + sufixo numérico incremental até não colidir, com
`$ignorarId` para a reedição. Ele governa os caminhos de servidor — o médium
([MensagensConta.php:155](app/Livewire/Conta/MensagensConta.php#L155)) e a curadoria
([CuradoriaConta.php:169](app/Livewire/Conta/CuradoriaConta.php#L169)). O `/admin` é o único
lugar onde o slug é **campo de tela** com `->unique(ignoreRecord: true)`
([MensagemForm.php:60-65](app/Filament/Schemas/MensagemForm.php#L60-L65)) — por isso o bloco 2
tem **um** ponto de aplicação, e por isso o D5 é *reusar a peça*, não escrevê-la.

Prova em campo, do próprio dev: as duas mensagens com `contexto` são "Nova Mensagem Teste"
(`nova-mensagem-teste`) e "Nova Mensagem teste" (`nova-mensagem-teste-2`) — o sufixo funcionou
com títulos que colidem por `Str::slug`.

Assimetria conhecida, **não** desta fatia: `MensagensConta::salvar()` trata a corrida de slug
(`catch` do SQLSTATE 23000, [:118-127](app/Livewire/Conta/MensagensConta.php#L118-L127)) e
`CuradoriaConta::publicar()` **não**. Fica registrado; não entra no escopo.

### 3.6 Persistência do `resumo` — o que já está pronto

`resumo` **já** é `$fillable` ([Mensagem.php:51](app/Models/Mensagem.php#L51)), já está no
`logOnly`, no glossário e na redação do `tapActivity`. Consequência: **acrescentar o
`Textarea::make('resumo')` ao `schemaMedium` basta** — nenhuma linha de `MensagensConta` muda.
Os únicos `unset()` do caminho do médium
([:148-151](app/Livewire/Conta/MensagensConta.php#L148-L151) e
[:190-193](app/Livewire/Conta/MensagensConta.php#L190-L193)) são denylist de campos
privilegiados (`direcionar`, `destinatarios`, `status`, `nivel`, `medium_id`,
`publicado_por_id`, `publicado_em`) e **não** citam `resumo`; a gravação é
`new Mensagem($dados)` / `$registro->update($dados)`, que respeitam o `$fillable`. Na curadoria
o campo **já existe e já persiste** nos dois caminhos (salvar e publicar).

**Buraco medido:** não existe **nenhum** teste que prove a persistência do `resumo` — nem no
médium, nem na curadoria, nem no `/admin`. Só existe `assertFormFieldExists`. Ver I6 e §8.

### 3.7 Render — inventário fechado

| Onde | O quê | Ação |
|---|---|---|
| [show.blade.php:7](resources/views/mensagens/show.blade.php#L7) | meta description **e** `og:description` (o layout imprime nos dois) — `resumo ?: contexto ?: corpo` | **muda** |
| [show.blade.php:85-93](resources/views/mensagens/show.blade.php#L85-L93) | a faixa "Contexto —" | **sai** |
| [show.blade.php:139-147](resources/views/mensagens/show.blade.php#L139-L147) | o lead do `resumo` | fica |
| [card.blade.php:14](resources/views/components/mensagem/card.blade.php#L14) | trecho `resumo ?: corpo`, consumido pela lista pública, pelo perfil do autor e pela aba Minhas Direcionadas | não muda |
| `MensagemResource` (tabela, filtros, actions) | **não** expõe `contexto` nem `resumo` | não muda |
| Sitemap, `.ics`, JSON-LD, barreira, `linha.blade.php` | **não** leem os campos | não mudam |

⚠️ O bloco "Direcionada a você" ([:95-103](resources/views/mensagens/show.blade.php#L95-L103))
fica **imediatamente abaixo** da faixa e é outra coisa: não pode sair junto.

**Build:** a faixa **não tem nenhuma classe autoral** — só utilitários Tailwind, todos
reaparecendo em outras views (o próprio bloco "Direcionada a você" logo abaixo repete
`bg-[#FAF8F2]`, `border-b`, `mx-auto flex max-w-[1100px] items-start gap-3.5 px-6 py-5`,
`text-[14.5px] leading-relaxed text-text-secondary`, `font-semibold text-primary`). A saída do
Tailwind é byte-idêntica ⇒ **`npm run build` não é necessário**. E `.cema-msg-resumo` **não
existe em CSS nenhum** (confirmado no fonte e no bundle de `public/build/assets/*.css`): é
âncora de teste, não estilo. **Não remover.**

### 3.8 O que o histórico do DEPAE perde (D7)

`HistoricoMensagem::camposAlterados()`
([:146-149](app/Support/Mensagens/HistoricoMensagem.php#L146-L149)) mapeia cada chave do
`activity_log` por `GlossarioCamposMensagem::rotulo()` e **descarta os `null`** com
`array_filter`. Tirando `'contexto'` da lista branca, as 3 entradas antigas continuam
íntegras no banco e continuam listadas na tela (quem/descrição/quando), apenas sem aquele
rótulo em "Campos alterados":

| Entrada | Hoje | Depois |
|---|---|---|
| id 21 (`created`, msg 191) | 11 rótulos | 10 |
| id 24 (`created`, msg 192) | 12 rótulos | 11 |
| id 26 (`updated`, msg 192) | Resumo + Contexto | **só** Resumo |

Nenhuma fica com a lista vazia (o `@if` de
[historico-mensagem.blade.php:21](resources/views/components/conta/historico-mensagem.blade.php#L21)
nem chega a ser exercitado). O valor gravado nessas entradas **já é** `[texto não registrado]`
— nada informativo se perde. **Nada será feito no `activity_log`**: a trilha é append-only.

### 3.9 CI e suíte

- `phpunit.xml` força **SQLite `:memory:`** — inclusive no CI. O comentário do topo de
  `ci.yml` ("testes (PHPUnit) sobre MySQL 8") é **falso**; quem ler só o `ci.yml` planeja errado.
- Mas o CI **sobe `mysql:8.0`** e roda `php artisan migrate --force`
  ([ci.yml:68-70](.github/workflows/ci.yml#L68-L70)) **antes** do Pint e dos testes ⇒ **a
  migration desta fatia é exercitada em MySQL de verdade**, e um `DB::raw` mal escrito derruba
  o job no passo "Migrar banco". Boa notícia — e insuficiente: o CI migra banco **vazio**, então
  a **semântica** da fusão continua sem prova. É o que o I1/I2 existem para cobrir.
- **Não há strict mode** no projeto (`shouldBeStrict`/`preventSilentlyDiscardingAttributes`:
  zero ocorrências em `app/`). É isso que faz chave não-`fillable` ser descartada **em silêncio**
  e atributo inexistente devolver `null` — a origem dos falsos-verdes do R2.

---

## 4. Invariantes (cada um vira teste que reprova)

### Bloco 1

- **I1** — texto **não se perde**: onde `resumo` estava vazio (`NULL` **ou** `''`) e `contexto`
  tinha conteúdo, o texto passa a ser o `resumo`.
- **I2** — onde os **dois** estavam preenchidos, o `resumo` **vence** e o `contexto` é
  descartado. Precedência explícita, não acidente de ordem.
- **I3** — a coluna **não existe mais**: `Schema::hasColumn('mensagens', 'contexto') === false`.
  Asserção **positiva sobre a ausência** — é a rede contra um rollback silencioso da fusão.
- **I4** — `logOnly` **≡** glossário, ambos com **11** campos. A paridade é preservada, nunca
  afrouxada (D7).
- **I5** — `contexto` **não existe em nenhum dos três schemas**: guarda negativa no `/admin`,
  no médium **e na curadoria** — os três, não dois.
- **I6** — `resumo` **existe no `schemaMedium` e persiste**: o valor digitado pelo médium chega
  ao banco (criar **e** editar). Prova de round-trip, não de existência.
- **I7** — a página da mensagem **não tem mais** a faixa "Contexto —", e o lead do `resumo`
  continua exatamente onde estava.
- **I8** — a meta description é `resumo ?: corpo`. Sem elo do meio.
- **I9** — round-trip de texto puro do `resumo` (`create` → `fresh()` → mesmo valor). Herda o
  papel do teste do `contexto`, que é hoje a **única** prova desse tipo fora do `corpo`.
- **I10** — a redação da trilha continua cobrindo **valor novo `null`**: a chave sobrevive e o
  valor vira `[texto não registrado]`. É a rede contra alguém trocar `array_key_exists` por
  `isset` em [Mensagem.php:301](app/Models/Mensagem.php#L301).
- **I11** — as guardas negativas **não passam por vacuidade**: o form precisa estar montado
  (`->call('novo')` / `->call('editar', $id)`) antes do `assertFormFieldDoesNotExist`.

### Bloco 2

- **I12** — a mensagem do `unique` do slug no `/admin` sai em **pt-BR**, com a frase própria.
- **I13** — `lang/pt_BR/validation.php` tem **todas** as chaves do canônico da v13.17.0. Chave
  faltando = fallback silencioso para o inglês **na mesma tela** — o Translator faz fallback por
  **chave**, não por arquivo.
- **I14** — a seção `attributes` cobre as telas fora do Filament, e **não** contém campo que
  viva só em schema Filament (seria código morto competindo com o `->label()`).

---

## 5. Decisões de desenho

### 5.1 As duas migrations (D8)

**Arquivo 1** — `2026_07_23_000001_funde_contexto_em_resumo_nas_mensagens.php`:

```php
DB::table('mensagens')
    ->whereNotNull('contexto')
    ->where('contexto', '<>', '')
    ->where(function ($q) {
        // blank() em SQL: NULL e '' — mesmo critério de ImportarResumosMensagens.php:68.
        $q->whereNull('resumo')->orWhere('resumo', '');
    })
    // Identificador NU de propósito: "contexto" vira literal no MySQL e `contexto` quebra no SQLite.
    ->update(['resumo' => DB::raw('contexto')]);
```

`down()` **vazio, com o motivo escrito**: nada distingue o `resumo` que veio do `contexto` do
que sempre foi `resumo`. Molde de
[2026_06_29_000001:33](database/migrations/2026_06_29_000001_mover_fotos_palestrante_para_media_library.php#L33).

**Query Builder, não Eloquent.** O model tem `LogsActivity`: um laço com `->save()` viraria uma
enxurrada de *"mensagem atualizada"* no histórico que o diretor do DEPAE lê — o mesmo motivo do
`activity()->withoutLogs()` em [ImportarResumosMensagens.php:45](app/Console/Commands/ImportarResumosMensagens.php#L45).
O `DB::table` simplesmente não tem esse problema.

**Arquivo 2** — `2026_07_23_000002_drop_contexto_from_mensagens_table.php`: `dropColumn`, com
`down()` recriando a coluna **vazia** (`text nullable`, `->after('corpo')`), molde de
[2026_06_29_000002](database/migrations/2026_06_29_000002_drop_foto_from_palestrantes.php).

**O `down()` é DESTRUTIVO, por dois motivos independentes** — e isso vai escrito no docblock:
(a) recria a coluna vazia, e o texto fundido fica só no `resumo`; (b) o `up()` **já descartou**
de propósito o `contexto` das linhas que tinham `resumo` — esse texto nunca chega a ser
copiado, então nem em teoria haveria de onde voltar. **Rollback aqui é de schema, nunca de
dado.** Precedente aceito no projeto:
[2026_07_22_000001:20-24](database/migrations/2026_07_22_000001_add_resumo_to_mensagens_table.php#L20-L24).

**Por que duas e não uma (emenda ao O1).** Migration não roda em transação em MySQL nem em
SQLite (§3.3): o `UPDATE` e o `DROP` **nunca** serão atômicos entre si. Separados, o passo
concluído fica registrado em `migrations` e um novo `migrate` retoma do ponto certo; o
`UPDATE` é idempotente pelo próprio `WHERE`, então o pior caso é benigno. Há **precedente
literal** do par no projeto (§3.3). **Variante de arquivo único**, se o dono preferir uma
unidade lógica só: o `UPDATE` vem **antes** do `Schema::table` e **fora** do closure — o
callback do `Schema::table` roda na construção do `Blueprint`, antes do `build()`, então lá
dentro *também* executaria antes do DDL, mas escrever assim só confunde quem lê.

### 5.2 Model, auditoria e glossário

| Lugar | Mudança |
|---|---|
| [Mensagem.php:50](app/Models/Mensagem.php#L50) `$fillable` | `- 'contexto'`. 13 → **12** |
| [Mensagem.php:267](app/Models/Mensagem.php#L267) `logOnly` | `- 'contexto'`. 12 → **11** |
| [Mensagem.php:299](app/Models/Mensagem.php#L299) `tapActivity` | `foreach (['corpo', 'resumo'] …)` |
| [Mensagem.php:289](app/Models/Mensagem.php#L289) | **NÃO TOCAR** — é o método (§3.2) |
| [GlossarioCamposMensagem.php:21](app/Support/Mensagens/GlossarioCamposMensagem.php#L21) | `- 'contexto' => 'Contexto'`. Docblock "Mesmos 12 campos" → **11** |
| [MensagemFactory.php:22](database/factories/MensagemFactory.php#L22) | `- 'contexto' => null` |
| [ImportadorMensagens.php:51](app/Importacao/ImportadorMensagens.php#L51) | comentário-contrato citando `contexto` |

**O detonador de massa é o `$fillable`, não a factory.** Sem strict mode, `'contexto' => null`
na factory vira código morto inofensivo (a chave é descartada em silêncio). Quem **esquecer a
linha 50** derruba a suíte inteira com *column not found*, mesmo tendo limpado a factory. A
prioridade é essa, nesta ordem.

**`logOnly` e glossário saem no MESMO commit.** Tirar de um lado só deixa o
`GlossarioCamposParidadeTest` vermelho; esquecer nos dois o deixa **verde** com drift
silencioso (`logOnly` de atributo inexistente nunca fica *dirty*). A rede pega o desalinhamento,
não o esquecimento — daí o I3.

### 5.3 Formulário (D2, D6)

Nos três schemas, o `Textarea::make('contexto')` sai. No `schemaMedium`, entra o `resumo` —
**cópia do campo do `schemaCuradoria`**, com `rows(4)` e `maxLength(1500)`, para os três terem
contrato idêntico (o `contexto` do médium não tinha `maxLength` nenhum).

| Schema | Rótulo | Helper |
|---|---|---|
| admin, curadoria | **Resumo** | "Aparece no card, na busca do Google e como abertura da página. Importado do site antigo quando havia. Opcional." |
| médium | **Resumo** | "Texto curto que abre a página da mensagem e aparece no card. Opcional." |

Efeito colateral do D6 que é **desejável**: como o `:attribute` do Filament vem do `->label()`
(§3.4), a mensagem de validação do campo passa a dizer "resumo" e não "resumo (texto
editorial)".

`maxLength(1500)` × coluna `text` (65.535 **bytes**): no pior caso UTF-8 são ~6.000 bytes —
sobra ~10×. Nenhuma linha do dev chega perto (máximo 1164 caracteres).

### 5.4 Views

- **Sai** o bloco [85-93](resources/views/mensagens/show.blade.php#L85-L93) inteiro — e **só**
  ele: o bloco "Direcionada a você" começa na 95.
- **Vira** `Str::limit(strip_tags($mensagem->resumo ?: $mensagem->corpo), 155)` na
  [:7](resources/views/mensagens/show.blade.php#L7). Lembrar que esse valor alimenta **duas**
  tags (`description` e `og:description`).
- O lead e o card **não mudam**.

**Consequência semântica declarada:** depois da fusão, um texto escrito como "contexto"
(*"Recebida na reunião pública de quarta"*) passa a alimentar a meta description, o
`og:description`, o trecho do card em três superfícies e o lead. É mudança de **vitrine e SEO**,
não só de banco. No dev são 2 registros de teste; a regra passa a valer para tudo que o
`/admin` escrever daqui em diante — e é exatamente o que o D1 quer, já que os dois campos eram
a mesma coisa.

### 5.5 Bloco 2 — os arquivos de idioma

**`lang/pt_BR/validation.php`** — gerado a partir de
`vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php` da **v13.17.0**
instalada, chave a chave, **nunca de um gist antigo**: a v13 tem regras recentes (`any_of`,
`contains`, `doesnt_contain`, `encoding`, `extensions`, `in_array_keys`, `list`, `hex_color`,
`present_if/unless/with/with_all`, `prohibited_if_accepted/declined`,
`required_if_accepted/declined`) que faltam nas traduções que circulam por aí — e chave ausente
é fallback silencioso para o inglês **dentro da mesma tela** (I13).

Estrutura: 107 regras (98 strings + 9 arrays — as 8 de tamanho com as sub-chaves
`array`/`file`/`numeric`/`string`, mais `password` com `letters`/`mixed`/`numbers`/`symbols`/
`uncompromised`) + `custom` + `attributes`.

**Seção `attributes`** — fechada, só o que as telas fora do Filament exibem (I14):

```
name, email, password, password_confirmation, token,
data_nascimento, endereco, whatsapp, whatsapp_publico, foto
```

**Não** entra nada de Mensagens (`titulo`, `slug`, `resumo`, `corpo`, …): esses campos vivem
só em schemas Filament, que já têm `->label()` — seria código morto criando uma segunda fonte
de verdade para o mesmo rótulo.

**`auth.php` e `passwords.php` (D9)** — 3 e 5 chaves. Sem eles, o `/entrar` fica com "O campo
e-mail é obrigatório." ao lado de "These credentials do not match our records.", e o
`/esqueci-a-senha` responde em inglês. **Meio-traduzido é pior que consistentemente em inglês.**

**A frase do slug** — em [MensagemForm.php:60-65](app/Filament/Schemas/MensagemForm.php#L60-L65),
molde de [AgendaMetaMesResource.php:72-74](app/Filament/Resources/Agenda/AgendaMetaMesResource.php#L72-L74):

```php
->validationMessages(['unique' => 'Este slug já está em uso. Ajuste-o antes de salvar.'])
```

Frase acionável, e não a genérica, porque **39 das 47 pendentes** carregam slug de máquina
(`comunicabilidade-25751`) e precisam de revisão antes de publicar — nota herdada da F4c-AC.

---

## 6. Riscos e armadilhas

- **R1 — os três "contexto" (§3.2).** Separar campo × método × prosa em **cada** ponto. Um
  `grep -r contexto | xargs sed` mata a auditoria de `User`, `AgendaDia` e `Mensagem`.
- **R2 — falso-verde, não vermelho.** Sem strict mode (§3.9), estes testes **continuam verdes**
  e viram asserção vazia se não forem tocados à mão:
  `ImportadorMensagensTest:94` (`assertNull($m->contexto)` passa por vacuidade — atributo
  inexistente devolve `null`) e `MensagemShowTest:221-234` (a chave `contexto` da fixture é
  descartada, o `assertSee` do resumo continua verdadeiro e o `assertDontSee` procura string que
  nunca existiu). Nenhum reprova. **É o item que exige revisão manual, porque o framework não
  vai avisar.**
- **R3 — guarda negativa vacuosa.** Em componente Livewire+Filament o schema só existe depois de
  montado: `assertFormFieldDoesNotExist('contexto')` **sem** o `->call('novo')` /
  `->call('editar', $id)` antes passa porque o form nunca foi montado (I11). É a armadilha nº 1
  das guardas novas.
- **R4 — `validationMessages` precede o lang.** Fazer os dois no mesmo campo é decisão (§5.5);
  esperar ver a frase genérica ali é erro de leitura do vendor.
- **R5 — ordem do cutover (§7).** `migrate` antes de reiniciar o app abre janela de erro 500.
- **R6 — `cema:importar-resumos` × fusão.** O comando só preenche `resumo` **vazio**
  ([:68](app/Console/Commands/ImportarResumosMensagens.php#L68)) e casa por `wp_id`
  ([:59](app/Console/Commands/ImportarResumosMensagens.php#L59)). Quem rodar primeiro **vence
  para sempre**. Regra: em ambiente novo, **`cema:importar-resumos` antes da fusão**. No dev é
  inócuo — as 2 linhas com `contexto` têm `wp_id = NULL` (§3.1) — e o comando **não** roda neste
  cutover.
- **R7 — `migrate:rollback` depois do merge.** Se as duas migrations caírem no mesmo *batch*, o
  rollback recria `contexto` vazia e o `down()` da fusão é no-op. Schema volta, dado não.
- **R8 — Filament v5 / Livewire v3.** A transação é opt-in (`$hasDatabaseTransactions` já está
  em `EditMensagem`: **não perder a flag** se a fatia encostar nas Pages). O estado do Livewire
  é `data.*` (os *ids* dos campos é que são `form.*`) — teste que force estado usa
  `->set('data.resumo', …)`.
- **R9 — snapshot antigo é inofensivo.** Todos os três caminhos de escrita passam por
  `getState()`, que devolve só os componentes do schema; nenhum passa `$this->data` cru. Uma aba
  aberta com `data.contexto` no snapshot **não** grava a coluna morta. O risco de 500 no cutover
  vem do **bytecode velho no OPcache**, não do Livewire.

---

## 7. Cutover (dev; PROD do dono, quando houver)

**A ordem está invertida em relação ao kickoff — de propósito.**

```
1) git pull / checkout do código novo
2) docker compose exec app php artisan optimize:clear
3) docker compose restart app worker          ← o app já roda o código SEM `contexto`
4) docker compose exec app php artisan migrate ← só então a coluna some
```

**Por quê.** O dev roda com OPcache `validate_timestamps=0`: o bytecode velho continua servindo
até o `restart`. Na ordem do kickoff (`migrate` → `optimize:clear` → `restart`) existe uma
janela em que o **banco já perdeu a coluna** e o **código em memória** ainda tem `contexto` no
`$fillable` e o `Textarea` nos três schemas — qualquer salvamento nessa janela manda a coluna
morta para o `INSERT`/`UPDATE` (SQLSTATE 42S22 ⇒ 500 no `/admin` e no `/minha-conta`). O sentido
inverso é **benigno**: código novo com banco velho apenas deixa de ler e escrever a coluna.

**Sem `npm run build`** (§3.7). **Sem `cema:importar-resumos`** (R6).

**Conferir depois:**
1. a página de uma mensagem **não** tem mais a faixa "Contexto —" e o lead dourado segue no lugar;
2. o médium vê o campo **"Resumo"** em `/minha-conta/mensagens`, digita, salva e **o texto volta** ao reabrir;
3. no `/admin`, salvar uma mensagem com slug repetido mostra a frase **em português**;
4. `/entrar` com o formulário vazio responde em português (prova o `validation.php` + `attributes`).

---

## 8. Testes — lista nominal

**Do campo `contexto` (TOCAR):**

| Arquivo:linha | Método | Ação |
|---|---|---|
| `MensagemTest:32` | `test_colunas_esperadas_e_podadas` | **mover** `contexto` da lista de esperadas para a de **podadas** (I3) |
| `MensagemTest:43` | `test_fillable_exato` | tirar da lista — passa a travar 12 campos |
| `MensagemTest:69` | `test_contexto_e_texto_puro_persistido` | **REESCREVER** para `resumo` (I9) — **não apagar**: é a única prova de round-trip de texto puro |
| `MensagemShowTest:37` | `test_contexto_e_escapado` | **REMOVER** — testa a faixa; o escape do lead já tem teste próprio (`:263`), mais forte (`{!! nl2br(e()) !!}`) |
| `MensagemShowTest:221` | `test_meta_description_usa_o_resumo_quando_existe` | **REESCREVER** — tirar a chave `contexto` da fixture e trocar o `assertDontSee` para o corpo. ⚠️ **falso-verde** se esquecido (R2) |
| `MensagemShowTest:237` | `test_meta_description_cai_no_contexto_sem_resumo` | **REESCREVER** → `..._cai_no_corpo_...`; sem ele a cadeia fica sem guarda de fallback |
| `MensagemResourceTest:54` | `test_form_tem_textarea_contexto` | **REESCREVER como negativa** — mover para `test_form_nao_tem_campos_podados` (`:116`) |
| `MensagensContaCriarTest:256,260` | `test_i22_campos_do_medium` | **INVERTER as duas**: `assertFormFieldExists('resumo')` + `assertFormFieldDoesNotExist('contexto')`. É o teste que vira de sentido pelo D2 |
| `AuditoriaMensagemTest:58` | `test_editar_contexto_registra_a_chave_mas_nunca_o_texto` | **REMOVER** — o par do `resumo` já existe em `MensagemTest:187` |
| `AuditoriaMensagemTest:82` | `test_editar_contexto_para_null_redige_o_campo…` | **REESCREVER** para `resumo` (I10) — **não apagar**: é o único teste com valor novo `null` |
| `AuditoriaMensagemTest:97` | comentário | "corpo/contexto" → "corpo/resumo" |
| `ImportadorMensagensTest:94` | `test_mapeia_campos_do_legado` | trocar por `assertNull($m->resumo)`. ⚠️ **falso-verde** (R2) |
| `ImportadorMensagensTest:228,233,241` | `test_reimport_preserva_curadoria_do_admin` | trocar `contexto` por `resumo` — a asserção passa a afirmar que o texto editorial sobrevive ao re-import |
| `HistoricoMensagemTest:23,54` | comentários | "corpo/contexto" → "corpo/resumo" |
| `CuradoriaContaTest:226` | docblock de `test_i11_form_da_curadoria_tem_o_campo_resumo` | a frase "(e não no form do médium)" fica **falsa** com o D2 |

**NÃO TOCAR (é o método, §3.2):** `AuditoriaHelperTest:48` · `HistoricoMensagemTest:132` ·
`HistoricoMensagemTest:48` (é "contexto **técnico**": `user_agent`/`attributes`).

**Testes novos:**

| # | O que prova |
|---|---|
| **I1/I2** | a fusão: `resumo` vazio + `contexto` cheio → copia; ambos cheios → `resumo` vence; `contexto` vazio → não toca |
| **I3** | `Schema::hasColumn('mensagens','contexto') === false` |
| **I5** | guarda negativa de `contexto` nos **três** schemas — incluindo a **curadoria**, que hoje não tem nenhuma (com o form montado, I11) |
| **I6** | round-trip do `resumo` pelo save do médium (criar **e** editar) e pela curadoria |
| **I12** | a frase pt-BR do `unique` do slug no `/admin` |
| **I13** | paridade de chaves `lang/pt_BR/validation.php` **≡** canônico do vendor (rede contra tradução parcial) |

⚠️ O teste do **I13** lê o arquivo do `vendor/`. Isso é deliberado: quando um `composer update`
trouxer regra nova, ele fica **vermelho** e avisa que falta traduzir — que é exatamente o que se
quer. Consequência a declarar no PR: é o primeiro teste do projeto que depende de um arquivo do
`vendor/`, e ele exige `composer install` (o CI já faz).

**Baseline medida em `4e466c9`, nesta branch:** **1278 passed** (3958 asserções), 624s.

---

## 9. O que prova que está pronto

1. Suíte **completa** verde (não `--filter`: o bloco 2 é global — §3.4).
2. `pint --test` limpo **antes** de qualquer push (o CI aborta o job no Pint, antes dos testes).
3. `grep -rn "contexto" app/ resources/ database/ tests/` devolve **apenas** as ocorrências do
   **método** de auditoria e a prosa da §3.2 — allowlist fechada, no molde do I16 da F4c-AC.
4. CI verde no **último** commit, com o passo "Migrar banco" (MySQL 8 real) passando.
5. Conferência no localhost dos 4 itens da §7.

---

## 10. Fora de escopo

- **Bloco B** (visibilidade dos autores espirituais) — PR próprio, é a próxima fatia.
- **Slug auto-incremental no `/admin`** (D5): reusar `SlugMensagem::unico()` — backlog.
- **`catch` do 23000 em `CuradoriaConta::publicar()`** (§3.5) — assimetria conhecida, não desta
  fatia.
- **Follow-ups herdados da F4c-AC:** `@switch` sem `@default` em `show.blade.php`;
  `<aside aria-label>` no lead; ~6 linhas de `throw` duplicadas entre a Action e o trait.
- **Traduzir o restante da interface** (mensagens de e-mail, textos do Filament já cobertos pelo
  vendor).
- Visibilidade, engajamento, curadoria além do que a F4c-AC fechou.

---

## 11. Pendências de ratificação

Três pontos que **divergem do kickoff ou ampliam o escopo**. O desenho acima já os adota; se o
dono recusar qualquer um, a mudança é local e está isolada.

| # | Pendência | O que a SPEC adotou | Se recusado |
|---|---|---|---|
| **P-A** | **D8 — duas migrations.** O O1 do kickoff diz "numa migration". Dado novo: migration não roda em transação em nenhum dos dois drivers (§3.3), e existe precedente literal do par no projeto. | duas migrations | variante de arquivo único, documentada em §5.1 — `UPDATE` antes do `Schema::table`, fora do closure |
| **P-B** | **D9 — `auth.php` + `passwords.php`.** O kickoff previa só a frase do slug. Sem estes dois, o `/entrar` fica meio-traduzido (§5.5). | os três arquivos | só `validation.php`; o login continua em inglês e vira dívida declarada |
| **P-C** | **D10 — a copy e o handoff.** [autores/index.blade.php:61](resources/views/autores/index.blade.php#L61) promete "registramos … o contexto de cada comunicação" — promessa que o site deixa de cumprir. E o handoff de design documenta a faixa como **aprovada**: `design_handoff_mensagem_single/README.md:52-53` ("**Faixa de contexto** (se houver) — fundo `#FAF8F2`, traço dourado 22×3") e o protótipo `Mensagem - Detalhe.dc.html:164`, que ainda expõe o *toggle* `comContexto`. | ajustar a frase (retirando a promessa) e **declarar no PR** a divergência com o handoff | a frase fica como está e a divergência segue sem registro |
