# Spec — Camada 4 · Fatia F4c-AC · Resumo do legado + ajustes da curadoria

- **Base:** `origin/main` = `45a9eb7` (merge do PR #43, F4b mesclada).
- **Branch:** `camada-4-fatia-f4c-ac-resumo-ajustes`.
- **Formato:** 1 PR. O bloco B (visibilidade dos Autores Espirituais) é PR próprio, depois.
- **Data:** 2026-07-21.
- **Autor:** Thiago Mourão — https://github.com/MouraoBSB

---

## 0. Recorte: o que esta fatia fecha

Dois blocos independentes que tocam o mesmo terreno (`/admin` + `MensagemForm` + views de
mensagem), entregues juntos por economia de contexto — não por acoplamento.

**(A) Resumo do legado.** O WordPress guarda um texto editorial próprio por mensagem
(`post_excerpt`) que a Fatia 2A nunca importou, porque o leitor não o seleciona. São **154
excerpts** no legado, dos quais **151 viram resumo** (3 são lixo de pontuação, descartados
pelo D6), de ~391 caracteres cada. Esta fatia cria a coluna, o comando de importação e os
lugares onde o texto aparece.

**(C) Ajustes da curadoria.** Dois defeitos que o dono levantou ao rodar o circuito da F4b
no dev:

- **C1** — o rótulo diz "Imagens (pictografia)" e o front só renderiza imagem quando o
  formato é Pictografia. Imagem anexada a uma psicografia é aceita no formulário e
  **desaparece do site, sem aviso**.
- **C2** — não há botão de publicar no `/admin`. E, ao investigar, apurou-se um defeito
  maior: o Select `status` **publica sem validar nada e sem gravar autoria**.

**Fica de fora:** o bloco B (autores), a fatia do molde (`@filamentStyles` + preflight),
preencher `contexto`, e tudo de visibilidade/engajamento. Ver §10.

---

## 1. Contexto e objetivo

A Camada 4 fechou seu núcleo na F4b: o médium lança pelo `/minha-conta`, o diretor do DEPAE
cura e publica, e há uma trilha de auditoria legível na tela. Esta fatia não acrescenta
capacidade nova a ninguém — ela **recupera conteúdo perdido na migração** e **fecha dois
buracos** que só apareceram quando o circuito foi exercido de ponta a ponta por um humano.

O objetivo declarado, em uma frase por bloco:

- **A** — que os **151** textos editoriais aproveitáveis (de 154 excerpts) voltem a existir,
  sejam editáveis pela curadoria e apareçam no card, na meta description e na página da
  mensagem.
- **C** — que uma imagem anexada a qualquer formato apareça no site, e que publicar pelo
  `/admin` passe pela mesma regra de negócio que publicar pelo `/minha-conta`.

---

## 2. Decisões travadas (não reabrir)

| # | Decisão | Origem |
|---|---|---|
| **D1** | Coluna **própria** `resumo`. O campo `contexto` (criado na 2A, 1/180 preenchido) fica livre para o uso desenhado — não reaproveitar para o excerpt. | Dono, kickoff |
| **D2** | Import por **comando dedicado** `cema:importar-resumos`, que só preenche `resumo` vazio e roda sem trilha. **Não** estender `cema:importar-mensagens`. | Dono, kickoff |
| **D3** | Imagens: renomear o rótulo para "Imagens da mensagem" **e** passar a exibi-las nos 3 formatos. | Dono, kickoff |
| **D4** | Publicar no `/admin`: Action própria no header do `EditMensagem`; o Select `status` **continua** no form. | Dono, kickoff |
| **D5** | Corte: este PR = A + C. O bloco B tem SPEC/plano/PR separados. | Dono, kickoff |
| **D6** | Excerpt com **menos de 20 caracteres** (após `trim`) é **descartado**, não importado. | Dono, §3.1 |
| **D7** | O resumo é **visível na página da mensagem**, como lead destacado antes do corpo — não só no card e na meta description. | Dono, §5.1 |
| **D8** | A **coleção de mídia é renomeada** `pictografia` → `imagens`, com migration de dados. | Dono, §5.2 |
| **D9** | A regra de publicação vale nos **3 caminhos** do `/admin`: Action, Select no Edit e criação. A autoria é carimbada nos 3. | Dono, §5.3 |
| **D10** | A galeria mantém o **botão de download nos 3 formatos**; só a legenda muda ("Desenho" na pictografia, "Imagem" nos demais). | Dono, §5.2 |
| **D11** | O **gate de variante** (`$perfil`) do card **permanece**. A lista pública `/mensagens` segue sem miniatura — decisão de design da 2B. Cai **apenas** o gate de formato. | Dono, P1 |
| **D12** | Esta fatia **revoga o O6 da F4b** (`RegraPublicacao` "vale só no site", §6.6/§13 daquela SPEC) e a frase "no `/admin` nada muda / o admin pode salvar publicado sem nível". Motivo: o Select publicava sem validar e sem gravar autoria. Consequência prevista e aceita: **4 testes** quebram (§8.2). | Dono, F4c |

---

## 3. Terreno confirmado por medição

Nada nesta seção é presumido. O legado foi medido em **2026-07-21 com o túnel SSH aberto**;
o dev, por consulta somente-leitura ao MySQL local; o código, por leitura de
`origin/main` = `45a9eb7`.

### 3.1 Legado — `wp_posts`, CPT `mensagem-mediunicas`

SQL de referência (somente `SELECT`, conexão `legado`):

```sql
-- universo e cobertura
SELECT post_status, COUNT(*) total,
       SUM(CASE WHEN post_excerpt IS NOT NULL AND TRIM(post_excerpt) <> '' THEN 1 ELSE 0 END) com_excerpt
FROM wp_posts WHERE post_type='mensagem-mediunicas' GROUP BY post_status;

-- saneamento (conclusão NEGATIVA precisa do SQL que a sustenta)
SELECT SUM(post_excerpt LIKE '%<%')                AS tem_menor_que,
       SUM(post_excerpt REGEXP '<[a-zA-Z/!]')      AS tem_tag_real,
       SUM(post_excerpt LIKE '%&%')                AS tem_e_comercial,   -- pega &nbsp; &#8217; &amp;
       SUM(post_excerpt LIKE '%[%')                AS tem_shortcode,
       SUM(post_excerpt <> TRIM(post_excerpt))     AS precisa_trim
FROM wp_posts WHERE post_type='mensagem-mediunicas'
  AND post_status IN ('publish','pending') AND TRIM(post_excerpt) <> '';
```

| Fato | Valor |
|---|---|
| `publish` | 132 total · **125 com excerpt** |
| `pending` | 47 total · **29 com excerpt** |
| `auto-draft` | 1 · 0 com excerpt (fora do recorte) |
| **Total lido pelo comando** | **154** |
| Comprimento | média **391**, mínimo 1, **máximo 1164** |
| Contém `<` | **0** |
| Contém tag HTML real (regex) | **0** |
| Contém `&` (entidade `&nbsp;`/`&#8217;`/`&amp;`) | **0** |
| Contém `[` (shortcode) | **0** |
| Precisa de `trim` | **0** |
| Com quebra de linha | 32 |
| Com parágrafo duplo (`\n\n`) | 12 |

**É texto editorial, não corte do corpo.** Varredura dos **154** (não amostra),
normalizando acento, caixa e espaço em branco:

| Relação excerpt × corpo | Quantidade |
|---|---|
| excerpt é prefixo do corpo (60 primeiros chars) | **0** |
| excerpt aparece contido no corpo em algum ponto | 2 |
| excerpt idêntico ao corpo | **0** |

**Três excerpts são lixo** — só pontuação. Sem o corte do D6, viram meta description `"."`,
que é pior que o fallback do corpo:

```
[21762] len=1  "."        Reencontro
[21763] len=1  "."        AMOR
[21793] len=6  "......"   mensagem em audio, nao teve titulo
```

Distribuição que sustenta o corte em 20 caracteres — ele mata **exatamente** esses três e
preserva o menor legítimo (`[21719]`, 31 chars, *"Sugestão para um novo trabalho."*):

| Faixa | `<20` | 20–99 | 100–299 | 300–599 | `≥600` |
|---|--:|--:|--:|--:|--:|
| Excerpts | **3** | 5 | 35 | 73 | 38 |

**As 25 que ficam sem resumo** (18 `pending` + 7 `publish`) são conhecidas e aceitas: elas
caem no fallback atual (`contexto ?: corpo`). Não é falha do comando.

⚠️ **Esta seção é um snapshot de 2026-07-21.** O site legado é vivo. A partilha 151/3 deve
ser reconferida no ato do cutover (§7, M8).

### 3.2 Dev — banco local

| Fato | Valor |
|---|---|
| `mensagens` | **180** linhas — 179 com `wp_id`, 1 sem (criada no site na F4b) |
| Casamento por `wp_id` | **154 de 154** excerpts têm mensagem no dev. **Zero órfãos** |
| Status dos alvos | 125 publicadas · 29 pendentes |
| Formato × status | `psicografia` 137 · `psicofonia` 40 · `pictografia` 3. `formato` nulo: **0** |
| `nivel` nulo | **49** — dos quais **47/47 pendentes** e **2 publicadas** |
| **Publicadas com `nivel = null`** | **id 168** (`wp_id` 26021, "Caminhar com Cristo") e **id 179** (`wp_id` 26818, "Cérebro material versos cérebro espiritual") |
| `publicado_em` não nulo | **0** — **as 133 publicadas têm `publicado_em` E `publicado_por_id` NULL** |
| `contexto` preenchido | **1** (a mensagem criada no site) |
| Mídia | **4 arquivos em 3 mensagens** (ids 93, 158, 191), coleção `pictografia`, **as 3 de formato Pictografia** |
| `activity_log` `log_name='mensagem'` | **3 linhas**, todas do `subject_id = 191` — **nenhuma importada foi editada ainda** |
| Restritas × públicas | 101 restritas (44 trabalhadores · 33 mediuns-trabalhadores · 15 direcionada · 9 diretores) contra ≤29 públicas |

Coleções existentes em `media` — **zero colisão** para o nome `imagens` (D8):

```
Post          destacada 45 · corpo 81 · galeria 160
Palestrante   foto 58        PerfilMembro  foto 23
Evento        flyer 48 · galeria 42        AutorEspiritual foto 14
Biblioteca    biblioteca 2   ConfiguracaoAgenda capa 1
Mensagem      pictografia 4   ← única a mudar
```

Não há morph map no projeto: `model_type` é a FQCN literal `App\Models\Mensagem`.

### 3.3 Código

**T1 — `Mensagem` tem 18 colunas** e `resumo` **não** existe (nome livre):
`id, wp_id, titulo, slug, corpo, contexto, formato, data_recebimento, casa, medium_id,
publicado_por_id, publicado_em, link_arquivo, liberar_download, nivel, status, created_at,
updated_at`. `resumo` é o **nome padrão do projeto** — `posts`, `palestras` e `eventos` já
têm a coluna.

**T2 — a causa raiz do bloco A:** `LeitorMensagensMysql::mensagens()`
([LeitorMensagensMysql.php:19-27](app/Importacao/LeitorMensagensMysql.php#L19-L27)) faz
`SELECT ID, post_title, post_name, post_content, post_status` — **sem `post_excerpt`**.

**T3 — a interface do leitor tem 2 fakes anônimos**, em
[ImportadorMensagensTest.php:25](tests/Feature/Importacao/ImportadorMensagensTest.php#L25) e
[ImportarMensagensCommandTest.php:24](tests/Feature/Importacao/ImportarMensagensCommandTest.php#L24).
Acrescentar método a `LeitorMensagens` é **erro fatal de PHP**, não falha de asserção — ver
R4.

**T4 — auditoria:** [Mensagem.php:261-276](app/Models/Mensagem.php#L261-L276) declara
`logOnly` com **11 campos**; [:285-307](app/Models/Mensagem.php#L285-L307) o `tapActivity`
substitui **`corpo` e `contexto`** por `'[texto não registrado]'` nos blocos `attributes` e
`old`. `withoutLogs` tem **zero ocorrências** em `app/`. **`publicado_em` e
`publicado_por_id` NÃO estão no `logOnly`** — mudança neles é invisível na trilha.

**T5 — o glossário é lista branca sem rede.**
[GlossarioCamposMensagem.php:11-12](app/Support/Mensagens/GlossarioCamposMensagem.php#L11-L12)
afirma no docblock *"Mesmos 11 campos de `logOnly`"*, e `rotulo()` devolve `null` para chave
fora da lista — o consumidor **descarta** o desconhecido. **Nenhum teste compara as duas
listas.** Um campo em `logOnly` e fora do glossário some do histórico do DEPAE em silêncio.

**T6 — os três sentidos de "pictografia".** Distinção crítica; confundi-los quebra 180
registros:

| Sentido | Onde | Nesta fatia |
|---|---|---|
| **Formato** — `FormatoMensagem::Pictografia`, valor `'pictografia'` | enum, selo, factory, `ResumoAutor`, coluna do Resource | **NÃO muda** |
| **Coleção de mídia** — `Mensagem::COLECAO_PICTOGRAFIA = 'pictografia'` | 10 pontos em `app/` + `resources/`, **15** em `tests/` | **Muda para `imagens`** |
| **Nome do campo do form** — `ComponentesImagem::upload('pictografia', …)` | 3 schemas, 2 testes | **Muda para `imagens`** (§5.2) |

**T7 — `MensagemForm` tem 3 schemas** (`schemaAdmin` L34-155, `schemaMedium`,
`schemaCuradoria` L~316) e o upload rotulado `'Imagens (pictografia)'` aparece nos **três**
([:152](app/Filament/Schemas/MensagemForm.php#L152),
[:256](app/Filament/Schemas/MensagemForm.php#L256),
[:351](app/Filament/Schemas/MensagemForm.php#L351)). O `nivel` é `live()` e **não é
`required`** ([:87-91](app/Filament/Schemas/MensagemForm.php#L87-L91)); o `status` é Select
de 3 opções com **default `publicado`** e **sem `live()`**
([:93-101](app/Filament/Schemas/MensagemForm.php#L93-L101)). O Select de destinatários do
`schemaAdmin` ([:141](app/Filament/Schemas/MensagemForm.php#L141)) usa `User::orderBy('name')`
puro — **sem** o filtro/`orWhereIn` que o `blocoDestinatarios` do site ganhou
([:180-184](app/Filament/Schemas/MensagemForm.php#L180-L184)).

**T8 — o front hoje:** só [corpos/pictografia.blade.php](resources/views/mensagens/corpos/pictografia.blade.php)
renderiza imagens, numa cadeia `@if` (11) … `</div>` (31) … `@elseif blank(corpo)` (32-33) …
`@endif` (**34**). [card.blade.php:11-13](resources/views/components/mensagem/card.blade.php#L11-L13)
tem **gate duplo** (variante `perfil` **e** formato Pictografia) e usa a **string crua**
`'pictografia'` — único ponto do front que não usa a constante.

**T9 — a psicofonia sai de graça.** [psicofonia.blade.php:15](resources/views/mensagens/corpos/psicofonia.blade.php#L15)
faz `@include('mensagens.corpos.psicografia')`. Há **um único ponto de inserção** para os
dois formatos textuais.

**T10 — a barreira intercepta antes do render.** [MensagemController.php](app/Http/Controllers/MensagemController.php)
resolve a barreira antes de devolver a view ⇒ meta description de mensagem restrita não vaza
ao anônimo. Mídia **já** vem eager-loaded no single; **não** vem na lista
([Lista.php:89](app/Livewire/Mensagens/Lista.php#L89) faz só `->with('autores')`).

**T11 — `EditMensagem` e `CreateMensagem`** não declaram `$hasDatabaseTransactions` ⇒ a
transação do Filament é **no-op** (opt-in, default off). Ambas **declaram na classe** os
hooks `mutateFormDataBefore*` e `after*`
([EditMensagem.php:33-42](app/Filament/Resources/Mensagens/Pages/EditMensagem.php#L33-L42),
[CreateMensagem.php:17-26](app/Filament/Resources/Mensagens/Pages/CreateMensagem.php#L17-L26)),
chamando os helpers dos traits `SincronizaDestinatarios`/`SincronizaRelacionadas`. O header
do `EditMensagem` tem **só** `DeleteAction`. **Não existe nenhuma `Action::make()` custom em
`getHeaderActions()`** no projeto — há moldes de Action custom fora do header
(`BibliotecaResource.php:93,137`, `ConfiguracoesContato.php:66`), úteis ao plano.

**T12 — a máquina de publicação da F4b** ([CuradoriaConta.php:138-180](app/Livewire/Conta/CuradoriaConta.php#L138-L180)):
`DB::transaction` → `getState()` → `SincronizadorDestinatarios::efetivos()` (filtra `ativo`)
→ `RegraPublicacao::erros()` → `throw ValidationException` (chave `data.nivel` ou
`data.destinatarios`) → `fill` + **regenera slug** + status + autoria + `save()` →
`sincronizar()`. [RegraPublicacao::erros()](app/Support/Mensagens/RegraPublicacao.php#L18-L36)
é estática, pura, nunca lança, devolve no máximo 1 erro.
`SincronizadorDestinatarios` tem **dois** métodos de semântica distinta: `filtrarPorNivel()`
(cru) e **`efetivos()`** (filtra `ativo`, `:53-62`).

**T13 — os 11 saves das pages** (`->call('create')` / `->call('save')`):

| Arquivo | Linhas | Nível no **estado do form** (fillForm **ou** hidratação do registro)? |
|---|---|---|
| `MensagemResourceTest` | 97, 111, 129 | **não** — factory default (`status=publicado`, `nivel=null`) |
| `MensagemDestinatariosPersistenciaTest` | 53, 79, 95, 109, 123, 141 | **sim** — nas linhas 79/95/109 vem do registro criado por `direcionadaCom()` (`:29`), não do `fillForm` |
| `MensagemDestinatariosFormTest` | 54, 76 | **sim** (`:54` = `direcionada`; `:76` = `publico`/`trabalhadores`) |

`MensagemFactory::definition()` nasce `status = STATUS_PUBLICADO` **e** `nivel = null`.

**T14 — suíte e build:** baseline **1221 testes / 265 arquivos** em `45a9eb7`. SQLite em
memória. CI: `npm ci && npm run build` → `migrate` → `pint --test` → `php artisan test`,
com o Pint **abortando antes** dos testes. Tailwind v4 **sem** `tailwind.config.js`: o
`@source` de `resources/css/app.css` varre `resources/views/**` — classe nova em Blade é
compilada, mas **só no `npm run build`**. `.cema-pictografia-grid` é classe **autoral** em
[mensagens.css:63](resources/css/mensagens.css#L63), sempre emitida.

---

## 4. Invariantes (cada um vira teste que reprova)

### Bloco A

- **I1** — o comando **nunca** sobrescreve `resumo` já preenchido. Segunda execução é no-op
  sobre quem já tem texto.
- **I2** — o comando **não gera nenhuma linha** em `activity_log`.
- **I3** — o comando **não altera** `titulo`, `corpo`, `slug`, `nivel` nem `status`.
- **I4** — mensagem **sem `wp_id`** não é tocada, e excerpt **sem mensagem correspondente**
  não cria registro.
- **I5** — excerpt com menos de 20 caracteres é **descartado e listado** no relatório.
- **I5b** — os quatro contadores do relatório são **mutuamente exclusivos** e sua soma é
  igual ao total de excerpts lidos.
- **I6** — `resumo` está nos **quatro** lugares da auditoria — `$fillable`, `logOnly`,
  glossário e redação do `tapActivity` — ou em nenhum.
- **I7** — **paridade**: `array_keys(GlossarioCamposMensagem::CAMPOS_ROTULOS)` ≡
  `getActivitylogOptions()->logAttributes`. Teste-contrato novo, que hoje não existe.
- **I8** — o card usa `resumo` quando há, e cai no corpo quando não há.
- **I9** — a meta description usa `resumo ?: contexto ?: corpo`.
- **I10** — a barreira continua interceptando a mensagem restrita **depois** de a description
  passar a ler `resumo` (regressão de T10).
- **I11** — `resumo` **não** aparece no `schemaMedium`, e **aparece** no `schemaAdmin` e no
  `schemaCuradoria`.

### Bloco C1

- **I12** — imagem anexada a uma **psicografia** aparece no corpo do single **e** no card
  (variante `perfil`).
- **I13** — a psicofonia mostra a galeria **uma única vez** (não duplica pelo `@include`).
- **I14** — o texto de estado vazio da pictografia (*"ainda não tem desenhos disponíveis"*)
  **não** aparece em psicografia/psicofonia sem imagem.
- **I15** — a lista pública `/mensagens` continua **sem miniatura** (D11) e sem query extra.
- **I16** — nenhuma referência à **coleção** antiga sobrevive, com **allowlist fechada**:
  `grep -r "COLECAO_PICTOGRAFIA" app/ resources/ tests/` ⇒ **0 ocorrências**;
  `grep -rE "['\"]pictografia['\"]" app/ resources/` ⇒ **exatamente 2**, ambas do eixo
  **FORMATO** e ambas intocadas — [FormatoMensagem.php:11](app/Enums/FormatoMensagem.php#L11)
  (o enum) e [ResumoAutor.php:24](app/Support/AutoresEspirituais/ResumoAutor.php#L24) (chave
  da paleta `COR`, indexada pelo valor do enum: renomeá-la faz `ResumoAutor.php:63` cair no
  fallback roxo, **sem teste que pegue**). **Sobrevivem também, e são esperados:**
  `ImportadorMensagens.php:91` (`'pictografia.jpg'`, nome de arquivo de fallback) e a classe
  CSS `.cema-pictografia-grid` — nenhum dos dois é casado pelo regex, nenhum dos dois muda.
- **I28** — a `legenda` governa o rodapé **e** o `alt` da imagem; o botão "Baixar" existe nos
  3 formatos (fecha o D10).

### Bloco C2

- **I17** — publicar pela Action **não regenera** o slug a partir do título: o slug
  persistido é o **do formulário** (contrato inverso ao do `/minha-conta`,
  `CuradoriaConta.php:169`). Se o campo não for tocado, o slug fica inalterado.
- **I18** — a Action grava `publicado_em` **e** `publicado_por_id`.
- **I19** — a Action recusa direcionada sem destinatário **efetivo** e recusa `nivel`
  inválido (tanto `null` quanto slug inexistente).
- **I20** — a Action **não aparece** em mensagem já publicada.
- **I21** — depois da Action, o form reflete o novo `status` — o "Salvar alterações" seguinte
  **não despublica**.
- **I22** — salvar com `status = publicado` e `nivel = null` é **recusado**, com mensagem que
  ensina o caminho.
- **I23** — criar com `status = publicado` e `nivel = null` é **recusado**.
- **I24** — publicar pelo Select (Edit) e criar já publicada gravam a **autoria**, igual à
  Action.
- **I25** — no `/admin`, `nivel` continua **NÃO obrigatório** quando `status != publicado`:
  o `required` é **condicional**, nunca incondicional.
- **I26** — salvar uma mensagem **já publicada** (as 133 importadas, todas com
  `publicado_em = null`) **sem mudar o status** NÃO grava `publicado_em` nem
  `publicado_por_id`.
- **I27** — publicar pela Action **persiste as `relacionadas` editadas no formulário**,
  simetricamente nos dois sentidos (paridade com o "Salvar alterações" da mesma tela).
- **I29** — a entrada de trilha gerada pela Action tem `porta = 'admin'`.

---

## 5. Decisões de desenho

### 5.1 Bloco A — o resumo

**Migration** `add_resumo_to_mensagens_table`:

```php
$table->text('resumo')->nullable()->after('contexto');
```

Aditiva, incremental, `down()` com `dropColumn`. **Nunca** `migrate:fresh`/`refresh`/`wipe`.

**Tipo: texto puro, sem mutator.** Medido: zero HTML, zero entidades, zero shortcodes. Molde
`Post::resumo`, que tem a coluna **só** no `$fillable` e é renderizado escapado — ao
contrário de `Evento`, que sanea com `clean($v, 'conteudo')`. Aqui não há HTML a sanear, e o
campo será um `Textarea`: HTML digitado ali seria acidente, não conteúdo.

**Os quatro lugares da auditoria (I6), mais o quinto (I7):**

| Lugar | Mudança |
|---|---|
| `$fillable` | `+ 'resumo'`, logo após `'contexto'` (espelha a ordem do schema). 12 → **13** |
| `logOnly` | `+ 'resumo'`. 11 → **12** |
| `tapActivity` | `foreach (['corpo', 'contexto', 'resumo'] as $campo)` — **≥94 dos 154 resumos pertencem a mensagem restrita** (§3.2: 101 restritas contra ≤29 públicas). Texto que descreve conteúdo reservado não pode ir cru para uma trilha de retenção indefinida |
| `GlossarioCamposMensagem` | `'resumo' => 'Resumo'` |
| **docblocks** | `GlossarioCamposMensagem` diz *"Mesmos 11 campos"* → **12**. Corrigir junto, senão a próxima leitura mente |
| **novo teste-contrato** | I7: paridade glossário ≡ `logOnly`, para que a próxima coluna não possa divergir em silêncio |

**Leitor: interface NOVA, não método a mais** (T3).

```
App\Importacao\LeitorResumosMensagens          (interface)
    public function resumos(): array;          // [ ['wp_id' => int, 'resumo' => ?string], … ]

App\Importacao\LeitorResumosMensagensMysql     (implementação)
```

```sql
SELECT ID, post_excerpt
FROM wp_posts
WHERE post_type = 'mensagem-mediunicas'
  AND post_status IN ('publish', 'pending')
  AND TRIM(post_excerpt) <> ''
ORDER BY ID
```

Normaliza `'' → null` na leitura, molde literal de
[LeitorBlogMysql.php:68](app/Importacao/LeitorBlogMysql.php#L68). Bind em
[AppServiceProvider.php:42-49](app/Providers/AppServiceProvider.php#L42-L49), junto dos
demais.

**Comando `cema:importar-resumos`** — ordem travada, **com os `continue`**:

```
1. guarda do túnel  (molde literal de ImportarMensagens.php:21-32:
                     if ($leitor instanceof LeitorResumosMensagensMysql) { try getPdo() }
                     → error + instrução do túnel + return FAILURE)
2. lê tudo
3. activity()->withoutLogs(function () use (…) {        ← UM envelope, o laço INTEIRO
       para cada {wp_id, resumo}:
           $texto = trim((string) $resumo)
           if (mb_strlen($texto) < 20)      → conta "curtas" + registra wp_id e valor; continue
           $m = Mensagem::firstWhere('wp_id', $wp_id)
           if ($m === null)                 → conta "sem_mensagem"; continue
           if (! blank($m->resumo))         → conta "ja_tinha";     continue
           $m->resumo = $texto; $m->save()  → conta "atualizadas"
   });
4. relatório
```

**Os quatro contadores são mutuamente exclusivos** (I5b) — invariante de fechamento:
`curtas + sem_mensagem + ja_tinha + atualizadas == total lido`. **Sem os `continue`**, um
excerpt de 1 caractere seria contado como "curta" **e gravado** (`wp_id` 21762/21763 são as
mensagens **111 e 112 do dev, publicadas** — exatamente o que o D6 existe para impedir), e
`$m === null` estouraria em `$m->resumo`. A ordem "curtas **antes** da busca" é deliberada:
excerpt curto órfão conta como *curta*, não como *sem_mensagem*.

**Por que `withoutLogs` envolve o laço, e não cada item:** a assinatura é
`withoutLogs(Closure $callback)`, com `finally` que re-habilita e guarda de reentrância; o
`ActivityLogStatus` é `scoped`. Envolver item a item é 154 vezes mais caro e não é mais
seguro.

**Por que `blank()` e não `is_null()`:** cobre `null` **e** `''`. Combinado com a
normalização do leitor, o critério "vazio" é estável entre execuções.

**Proibido:** `updateOrCreate`, `firstOrNew`, `fill()` de qualquer outro campo. Um excerpt
órfão **não pode criar mensagem** (I4).

**Relatório** — descarte silencioso é inaceitável. Nota de vocabulário: o array de retorno
chama-se **`$relatorio`**, não `$resumo` — em `ImportarMensagens.php:34` `$resumo` já
significa "o retorno do importador", e agora `resumo` é um campo do domínio.

```
Importação de resumos concluída.
  Atualizadas: 151 · Já tinham resumo: 0 · Sem mensagem no banco: 0 · Descartadas por serem curtas: 3
  Descartadas (confira se alguma é sinopse legítima):
    - wp_id 21762: "."
    - wp_id 21763: "."
    - wp_id 21793: "......"
```

**Efeito colateral declarado:** o backfill bumpa `updated_at` de até 151 linhas. Aceito —
sem trilha de auditoria, mas é mutação observável. Não vale a gambiarra de desligar
`timestamps`. **Consumidor conhecido:** o `<lastmod>` do sitemap
([SitemapController.php:36-38](app/Http/Controllers/SitemapController.php#L36-L38) →
`sitemap.blade.php:80`), que lê `Mensagem::publica()` — **no máximo 29 URLs** passam a
declarar a data do import. A **ordenação não muda** (lista e tabela do `/admin` ordenam por
`data_recebimento`) e `HistoricoMensagem` lê `activity_log`, não `updated_at`.

**Onde o resumo aparece:**

| Superfície | Antes | Depois |
|---|---|---|
| `card.blade.php:14` | `Str::limit(strip_tags($corpo), 160)` | `Str::limit(strip_tags($resumo ?: $corpo), 160)` |
| `show.blade.php:7` | `Str::limit(strip_tags($contexto ?: $corpo), 155)` | `Str::limit(strip_tags($resumo ?: $contexto ?: $corpo), 155)` |
| `show.blade.php` (novo) | — | **lead destacado** antes do `@switch` |
| `MensagemForm` | — | `Textarea` no `schemaAdmin` **e** `schemaCuradoria`; **fora** do `schemaMedium` |

**O lead (D7).** Dentro do card da mensagem, no `max-w-[640px]`, imediatamente antes do
`@switch` dos corpos — visualmente distinto da prosa mediúnica, porque **o resumo é
editorial, não é palavra do espírito**. Barra dourada à esquerda, fundo suave, tipografia
menor que a do corpo. Renderizado `{!! nl2br(e($mensagem->resumo)) !!}` — `e()` primeiro,
`nl2br` depois, para honrar os 12 com parágrafos sem abrir injeção. Só existe quando há
resumo: as 25 sem resumo ficam idênticas a hoje.

**Fora do `schemaMedium` (I11):** o resumo é texto da curadoria. O médium já tem o
`contexto` para dizer de onde veio a mensagem.

### 5.2 Bloco C1 — imagens nos 3 formatos

**Assinatura do componente** (`resources/views/components/mensagem/imagens.blade.php`) — o
miolo **12-31** de `pictografia.blade.php` (o `<div class="cema-pictografia-grid">…</div>`)
migra **preservando os artefatos literais**; o `getMedia()` e o `@if isNotEmpty` passam a
viver **dentro** do componente:

```blade
@props(['mensagem', 'legenda' => 'Imagem'])
@php $imagens = $mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS); @endphp
@if ($imagens->isNotEmpty())
    …grid literal das linhas 12-31, com "{{ $legenda }} {{ $i + 1 }}" no figcaption
      e alt="{{ $mensagem->titulo }} — {{ mb_strtolower($legenda) }} {{ $i + 1 }}"…
@endif
```

Consumo — **a psicofonia sai de graça**, graças ao `@include` (T9):

```
├─ pictografia.blade.php   corpo + <x-mensagem.imagens :mensagem="$mensagem" legenda="Desenho" /> + estado-vazio próprio
└─ psicografia.blade.php   corpo + <x-mensagem.imagens :mensagem="$mensagem" legenda="Imagem" />  + assinatura
       └─ psicofonia       herda pelo @include — ZERO linha nova no arquivo
```

**Passar `:mensagem` é obrigatório** — componente anônimo tem escopo isolado (molde
`card.blade.php:1`); sem o `@props`+bind, dá *Undefined variable `$mensagem`*.

**A extração NÃO é um recorte de 11-31.** Hoje o estado vazio é o `@elseif` da mesma cadeia
(`@if` na 11, `</div>` na 31, `@elseif` na 32-33, `@endif` na **34**) — copiar 11-31 deixaria
o `@if` sem fechamento. A cadeia é **reescrita em dois blocos independentes** (I14):

```blade
<x-mensagem.imagens :mensagem="$mensagem" legenda="Desenho" />
@if ($mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS)->isEmpty() && blank($mensagem->corpo))
    <p …>Esta comunicação pictográfica ainda não tem desenhos disponíveis.</p>
@endif
```

**Os artefatos literais preservados** — [MensagemShowTest.php:115-139](tests/Feature/Front/MensagemShowTest.php#L115-L139)
os fixa: `getUrl('web')`, `getUrl()` sem conversão, o `download` derivado do **título**
(`Str::slug($mensagem->titulo)-N.ext`, `:23`) e o texto "Baixar". O parâmetro `legenda`
governa **dois** textos: o rodapé (`:21`) **e** o `alt` da `<img>` (`:17`), hoje hardcoded
como "— desenho N" — em psicografia/psicofonia passa a ser "— imagem N" (A11y, I28).

**O nome é `imagens`, não `galeria`:** `galeria` já é coleção de `Post` (160 arquivos) e de
`Evento` (42). Reusar o termo criaria ambiguidade permanente. Vocabulário alinhado:
constante `COLECAO_IMAGENS`, coleção `imagens`, campo do form `imagens`, componente
`<x-mensagem.imagens>`, rótulo "Imagens da mensagem".

**Rename da coleção (D8)** — migration de dados reversível:

```php
DB::table('media')
    ->where('model_type', \App\Models\Mensagem::class)   // FQCN literal: não há morph map
    ->where('collection_name', 'pictografia')
    ->update(['collection_name' => 'imagens']);          // 4 linhas no dev; down() inverte
```

Os arquivos físicos **não se movem** — o path da medialibrary usa o `id` da media. As
conversões já geradas (`web`, `thumb`) continuam válidas.

**Lista COMPLETA dos call sites** — nenhum pode ficar para trás:

*Produção — a constante:*

| Arquivo | Linha |
|---|---|
| `app/Models/Mensagem.php` | 43 (declaração), 228 (`registrarColecaoImagem`) |
| `app/Importacao/ImportadorMensagens.php` | 88 (`clearMediaCollection`), 93 (`toMediaCollection`) |
| `app/Filament/Resources/Mensagens/MensagemResource.php` | 102 (`->collection(...)`) |
| `app/Filament/Schemas/MensagemForm.php` | 151, 255, 350 |
| `resources/views/mensagens/corpos/pictografia.blade.php` | 5 (`getMedia`) |
| `resources/views/mensagens/show.blade.php` | 3 (`$ogImg`) |

*Produção — a string crua e os rótulos:*

| Arquivo | Linha | O quê |
|---|---|---|
| `resources/views/components/mensagem/card.blade.php` | 13 | string crua `'pictografia'` → **constante** |
| `app/Filament/Schemas/MensagemForm.php` | 149, 253, 348 | `Section::make('Pictografia')` → `'Imagens'` |
| `app/Filament/Schemas/MensagemForm.php` | 152, 256, 351 | `->label('Imagens (pictografia)')` → `'Imagens da mensagem'` |
| `app/Filament/Schemas/MensagemForm.php` | 151, 255, 350 | nome do campo `upload('pictografia', …)` → `upload('imagens', …)` |
| `app/Filament/Resources/Mensagens/MensagemResource.php` | 100 | `SpatieMediaLibraryImageColumn::make('pictografia')` → `'imagens'` + label |

*Testes (**15** pontos da constante — `MensagemResourceTest.php:63` conta nos dois eixos —
**+ 2** do nome do campo):*

| Arquivo | Linhas |
|---|---|
| `tests/Feature/Importacao/ImportadorMensagensTest.php` | 156, 165, 166, 170, 199 |
| `tests/Feature/Models/MensagemTest.php` | 135, 136, 139 |
| `tests/Feature/Front/MensagemShowTest.php` | 125, 127 |
| `tests/Feature/Front/MensagemSeoTest.php` | 56, 59 |
| `tests/Feature/Conta/MensagensContaEditarTest.php` | 68, 83 |
| `tests/Feature/Filament/MensagemResourceTest.php` | 63 (`assertFormFieldExists('pictografia', …)` → `'imagens'`) |
| `tests/Feature/Conta/MensagensContaCriarTest.php` | 262 (`assertFormFieldExists('pictografia')` → `'imagens'`) |

**O que NÃO muda:** o enum `FormatoMensagem::Pictografia` e seu valor `'pictografia'` — o
formato continua se chamando Pictografia em `FormatoMensagem.php`, `selo-formato.blade.php`,
`autor/card.blade.php`, **`ResumoAutor.php:24`** (chave da paleta de cor), `MensagemFactory.php:23`
e `FormatoMensagemTest.php`. **Confundir os dois quebra os 180 registros.**

Também **não muda** a classe CSS `.cema-pictografia-grid`
([mensagens.css:62-63](resources/css/mensagens.css#L62-L63)): a extração da galeria é literal
e o alinhamento de vocabulário desta fatia é de **domínio** (constante/coleção/campo/
componente/rótulo), **não de CSS**. Renomeá-la obrigaria a tocar Blade + CSS por ganho
funcional zero — e, se alguém renomear só no Blade, a galeria perde o grid nos 3 formatos,
**sem erro**.

**O card:** cai **só** o gate de formato (D11).

```blade
{{-- antes --}}  $perfil && $mensagem->formato === FormatoMensagem::Pictografia
{{-- depois --}} $perfil
```

A lista pública segue sem miniatura, e [Lista.php:89](app/Livewire/Mensagens/Lista.php#L89)
**não** ganha `->with('media')` — não há N+1 novo (I15). As superfícies que ganham imagem
(`/autores/{slug}` e `/minha-conta/direcionadas`) **já** fazem eager-load de mídia.

**Este bloco é no-op no acervo atual.** As 3 mensagens com imagem são as 3 pictografias.
Consequência para a verificação: o teste **tem** de fabricar mídia numa psicografia
(`Storage::fake('public')` + `PNG_1X1`, molde `MensagemShowTest.php:115-139`), e a conferência
no localhost **exige subir uma imagem à mão**. Abrir uma psicografia existente e "ver que
está tudo certo" não prova nada.

### 5.3 Bloco C2 — publicar no /admin

**A Action lê o FORM, não o registro.** Fato que decide: **47/47 pendentes têm
`nivel = null`**, e o `nivel` não é `required` no `schemaAdmin`. Uma Action que validasse o
registro persistido recusaria **100% dos casos reais** — o botão nasceria inútil.

```php
Action::make('publicar')
    ->label('Publicar')
    ->requiresConfirmation()
    ->visible(fn (): bool => $this->record->status !== Mensagem::STATUS_PUBLICADO)
    ->action(function (): void {
        DB::transaction(function (): void {           // ← T11: a do Filament é no-op aqui
            $registro = $this->record;

            // defesa em profundidade — NÃO exercitável pela UI: hidden ⇒ isDisabled()
            // (CanBeDisabled.php:24) ⇒ mountAction() desmonta e retorna null
            // (InteractsWithActions.php:131-135); callAction() faz assertActionVisible() antes.
            abort_if($registro->status === Mensagem::STATUS_PUBLICADO, 403);

            $dados = $this->form->getState();          // valida + saveRelationships(), DENTRO da transação

            // reasserção deliberada dos 3 campos privilegiados (alinhamento com DATA-MODEL.md);
            // hoje redundante — não são $fillable e getState() já poda.
            unset($dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em']);

            $ids = SincronizadorDestinatarios::efetivos($dados['nivel'] ?? null, $dados['destinatarios'] ?? []);
            $erros = RegraPublicacao::erros(['nivel' => $dados['nivel'] ?? null, 'destinatarios' => $ids]);

            if ($erros !== []) {
                // molde literal de CuradoriaConta.php:159-163 — prefixo 'data.' porque o
                // statePath do form da page é 'data'.
                $chave = ($dados['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value
                    ? 'data.destinatarios'
                    : 'data.nivel';

                throw ValidationException::withMessages([$chave => $erros[0]]);
            }

            $idsRelacionadas = $dados['relacionadas'] ?? [];
            unset($dados['destinatarios'], $dados['relacionadas']); // não são colunas: fill() descartaria em silêncio

            $registro->fill($dados);
            //  ⚠️ NÃO regenerar o slug — ver abaixo
            $registro->status = Mensagem::STATUS_PUBLICADO;
            $registro->publicado_por_id = auth()->id();
            $registro->publicado_em = now();
            $registro->save();

            SincronizadorDestinatarios::sincronizar($registro, $ids);
            $registro->sincronizarRelacionadas($idsRelacionadas);   // simétrica, na MESMA transação
        });

        $this->refreshFormData(['status']);            // ← I21
        Notification::make()->success()->title('Mensagem publicada.')->send();
    });
```

**Por que `DB::transaction` explícita:** `getState()` executa `saveRelationships()` **antes**
da regra de negócio — autores e mídia já ficam gravados. Sem transação própria, uma
publicação recusada deixaria meio-save. `EditMensagem` não declara
`$hasDatabaseTransactions`, então o begin/rollback do Filament é no-op (T11). Existe a
alternativa `getState(afterValidate: fn () => …)`, usada pelo próprio `EditRecord::save()`;
ficamos com a transação explícita porque ela cobre também `save()` + `sincronizar()` no mesmo
átomo. Ressalva honesta: o rollback desfaz a linha em `media`, **não** o arquivo no disco.

**Por que `relacionadas` sai do `$dados` (I27):** é `Select` com `->options()`
(`MensagemForm.php:123-131`), logo desidrata e volta em `getState()`; **não** é `$fillable`,
então `fill()` a descartaria **sem erro**. Quem persiste no fluxo normal é
`capturarRelacionadas`/`aplicarRelacionadas` — que a Action não chama. Sem isso, quem editar
as relacionadas e clicar **Publicar** (em vez de Salvar) perde a edição, e o
`refreshFormData` deixa a tela mostrando o que não persistiu.

**Por que NÃO regenerar o slug (I17):** no `/admin` o slug é **campo de tela editável antes
de publicar**, com `unique(ignoreRecord: true)` — regenerar sobrescreveria o que o admin
digitou no próprio formulário que está sendo submetido. **Atenção declarada:** **39 das 47
pendentes** carregam slug gerado pelo importador com sufixo `wp_id`
(`comunicabilidade-25751`) porque o WP não tinha `post_name`
([ImportadorMensagens.php:116](app/Importacao/ImportadorMensagens.php#L116)) — publicar sem
revisar cimenta essa URL, e a mesma mensagem publicada pelo `/minha-conta` sairia como
`comunicabilidade`. A divergência entre as duas portas é deliberada e **vai declarada no PR**.

**Por que `refreshFormData(['status'])` (I21):** a Action grava `publicado` no registro, mas
`$this->data['status']` continua `pendente`. Sem o refresh, o próximo "Salvar alterações"
**despublica em silêncio**.

**Fechando o buraco do Select (D9)** — trait `PublicaMensagem`, molde **exato** dos traits
`SincronizaDestinatarios`/`SincronizaRelacionadas`: o trait expõe **helpers**, **nunca
declara hook**. `EditMensagem`/`CreateMensagem` já declaram `mutateFormDataBefore*` e
`after*` **na classe** (T11), e **método de classe vence método de trait sem erro nem
aviso** — um hook no trait seria **no-op silencioso**.

```php
trait PublicaMensagem
{
    /** Chamar ANTES de capturarDestinatarios() — depois dele $data['destinatarios'] não existe mais. */
    protected function reasserirRegraDePublicacao(array $data): array { … }

    protected function carimbarAutoriaSePublicando(Mensagem $registro): void { … }
}
```

As pages compõem nos hooks que **já têm**:

```php
// EditMensagem
protected function mutateFormDataBeforeSave(array $data): array
{
    $this->publicandoAgora = $this->record->status !== Mensagem::STATUS_PUBLICADO
        && ($data['status'] ?? null) === Mensagem::STATUS_PUBLICADO;

    $data = $this->reasserirRegraDePublicacao($data);            // ← PRIMEIRO
    return $this->capturarDestinatarios($this->capturarRelacionadas($data));
}

protected function afterSave(): void
{
    $this->aplicarRelacionadas($this->record);
    $this->aplicarDestinatarios($this->record);
    $this->carimbarAutoriaSePublicando($this->record);           // ← POR ÚLTIMO
}
```

| Camada | O quê | Cobre |
|---|---|---|
| **Declarativa** (UX) | `Select::make('nivel')->required(fn (Get $get) => $get('status') === Mensagem::STATUS_PUBLICADO)` com `->validationMessages(['required' => 'Selecione o nível de acesso para manter esta mensagem publicada.'])`, **exclusivamente em `MensagemForm::schemaAdmin()` (`:87-91`)**. O `schemaCuradoria` (`:316-320`) e o `schemaMedium` ficam **intactos** — lá não existe campo `status` (`$get('status')` seria sempre `null`, regra morta) e quem arbitra é o botão Publicar da F4b. Exige também `->live()` no `Select::make('status')` (`:93-101`, hoje **sem**), senão o asterisco/mensagem só reage no próximo round-trip. | I22, I23, I25, **C4** |
| **Server-side** (integridade) | `reasserirRegraDePublicacao($data)`: se `$data['status'] === STATUS_PUBLICADO`, calcula `SincronizadorDestinatarios::efetivos($data['nivel'] ?? null, $data['destinatarios'] ?? [])` e roda `RegraPublicacao::erros()` → `ValidationException`. **`efetivos()`, nunca `filtrarPorNivel()`** — é o filtro de `ativo` que faz o teste 25 existir. **Ordem obrigatória:** rodar **antes** de `capturarDestinatarios()`, que faz `unset($data['destinatarios'])` (`SincronizaDestinatarios.php:33`); depois dele, toda direcionada seria lida como "sem destinatário" e os 6 saves de `MensagemDestinatariosPersistenciaTest` quebrariam. | I22, I23 |
| **Autoria** | Carimba **só na TRANSIÇÃO**, nunca por estado. `mutateFormDataBeforeSave` guarda `$this->publicandoAgora`; `afterSave` carimba `publicado_em = now()` + `publicado_por_id = auth()->id()` **apenas se** essa flag. No `afterCreate` não há estado anterior: carimba se o registro **nasceu** publicado. **Nunca** usar "está publicado e `publicado_em` é null" como gatilho: as **133** publicadas do acervo têm `publicado_em` NULL (§3.2), e o gatilho de estado gravaria **autoria falsa em qualquer edição** de qualquer uma delas — inclusive na mensagem 168 do roteiro do §7. Agrava-se por `publicado_em`/`publicado_por_id` **não** estarem no `logOnly` (T4): a mutação seria invisível até na trilha. | I24, **I26** |

`->required()` é **hidratação, não integridade** — protege só o caminho do form. A
reasserção server-side é a rede que não depende dele.

**Ressalva declarada:** a reasserção usa o conjunto **efetivo** (filtra `ativo`), mas o
Select de destinatários do `schemaAdmin` (`MensagemForm.php:141`) **não** filtra `ativo` nem
tem o `orWhereIn` do `blocoDestinatarios` (`:180-184`). Como o carimbo e a regra só valem na
**transição** para publicado, um save de direcionada **já publicada** não é bloqueado por
destinatário que ficou inativo. Zero linhas no dev hoje (0 usuários inativos de 148). **Não**
relaxar a reasserção para o conjunto cru — seria publicar direcionada invisível para todos.

**A mensagem do C4 não toca `RegraPublicacao`.** A regra é compartilhada com a curadoria,
onde o texto atual é adequado, e tem teste unitário próprio
(`tests/Unit/Mensagens/RegraPublicacaoTest.php`). A mensagem que ensina o caminho vem do
`->validationMessages()` do `/admin`.

**Efeito no dado real (C4):** as mensagens **168** ("Caminhar com Cristo") e **179**
("Cérebro material versos cérebro espiritual") passam a **travar qualquer edição no `/admin`**
até receberem nível. É o efeito desejado — elas estão publicadas e invisíveis ao resolvedor
de visibilidade desde a 3A — e a mensagem ensina o que fazer. **Declarar isso no PR**, para
que o dono não interprete como regressão ao abrir a primeira delas.

**A publicação pelo `/admin` não notifica ninguém.** Publicar aqui é ato de administrador;
notificação/e-mail está fora de escopo (§10), como já estava na F4b.

---

## 6. As peças (inventário)

**Novos — 7 arquivos:**

| Arquivo | Papel |
|---|---|
| `database/migrations/…_add_resumo_to_mensagens_table.php` | coluna `resumo` |
| `database/migrations/…_renomeia_colecao_pictografia_para_imagens.php` | dados em `media` |
| `app/Importacao/LeitorResumosMensagens.php` | contrato |
| `app/Importacao/LeitorResumosMensagensMysql.php` | leitura do legado |
| `app/Console/Commands/ImportarResumosMensagens.php` | `cema:importar-resumos` |
| `app/Filament/Resources/Mensagens/Pages/PublicaMensagem.php` | trait: **helpers** de regra + autoria, compostos pelos hooks das pages (Select no Edit e criação). **A Action tem código próprio** — `CreateMensagem` não tem `$this->record` |
| `resources/views/components/mensagem/imagens.blade.php` | galeria reutilizável |

**Alterados — 13 de produção:**

`app/Models/Mensagem.php` · `app/Support/Mensagens/GlossarioCamposMensagem.php` ·
`app/Providers/AppServiceProvider.php` · `app/Importacao/ImportadorMensagens.php` ·
`app/Filament/Schemas/MensagemForm.php` · `app/Filament/Resources/Mensagens/MensagemResource.php` ·
`app/Filament/Resources/Mensagens/Pages/EditMensagem.php` ·
`app/Filament/Resources/Mensagens/Pages/CreateMensagem.php` ·
`resources/views/mensagens/show.blade.php` ·
`resources/views/mensagens/corpos/pictografia.blade.php` ·
`resources/views/mensagens/corpos/psicografia.blade.php` ·
`resources/views/components/mensagem/card.blade.php` · `database/factories/MensagemFactory.php`
(só se necessário — **não** mudar o default sem contar os saves da §8.2)

**Não tocar:** `RegraPublicacao`, `SincronizadorDestinatarios`, `SlugMensagem`,
`CuradoriaConta`, `MensagensConta`, `LeitorMensagens`/`LeitorMensagensMysql`, o resolvedor de
visibilidade da 3A, `Lista.php`, o enum `FormatoMensagem`, `ResumoAutor`,
`resources/css/mensagens.css`.

---

## 7. Cutover (dev e, depois, prod — do dono)

```
1) git pull
2) php artisan migrate                    # 2 migrations, incrementais
3) php artisan cema:importar-resumos      # COM o túnel SSH aberto; sem ele aborta limpo
4) npm run build                          # NO HOST — obrigatório, ver abaixo
5) php artisan optimize:clear
6) docker compose restart app worker
```

**`npm run build` é obrigatório** (confirma o R6 do kickoff): Tailwind v4 varre os Blades em
tempo de build, e **o lead do resumo** traz classes utilitárias que ainda não estão no CSS
compilado. O componente de imagens **reaproveita** classes já compiladas
(`.cema-pictografia-grid` é autoral em `mensagens.css`, sempre emitida) — o passo continua
obrigatório, mas pelo lead. A F4a não precisou; esta precisa.

**Ordem importa:** o passo 3 depende do passo 2. Se o túnel não estiver aberto, o comando
falha com mensagem e `FAILURE` — não com stack trace — e pode ser repetido depois sem efeito
colateral (I1).

**Esperado no relatório do passo 3:** `Já tinham: 0` · `Sem mensagem: 0` ·
**`Atualizadas + Curtas = 154`**. A partilha **151/3** foi medida em 2026-07-21 e deve ser
reconferida no ato, com o túnel aberto (a §3.1 é um snapshot daquela data).

**Conferir na tela:**

- card com o novo trecho · single com o lead e a nova description;
- **subir uma imagem numa psicografia pública e com autor espiritual** — p.ex. **id 68**
  (`/ser-consciente`); há **16** candidatas. As 3 que já têm imagem (93, 158, 191) **não
  servem**: 93 é `direcionada` sem autor e 158 é `trabalhadores` sem autor, e o perfil do
  autor filtra por `publica()`;
- botão Publicar no `/admin` de uma pendente — **antes de clicar, revisar o campo Slug**: a
  URL nasce definitiva, e 39/47 pendentes têm slug de máquina;
- abrir a mensagem **168** e ver a mensagem que pede o nível. O campo **Resumo** pode já vir
  preenchido pelo passo 3 — isso é esperado, não erro. E, com `nivel = null` (fail-closed no
  resolvedor 3A), o lead dessas duas não renderiza para ninguém além de admin/presidente até
  receberem nível.

---

## 8. Plano de teste (TDD real, vermelho primeiro)

Cada um nasce vermelho.

**Bloco A — comando e modelo**

1. preenche `resumo` vazio a partir do excerpt *(I1)*
2. **não** sobrescreve `resumo` já preenchido *(I1)*
3. **não** altera `titulo`, `corpo`, `slug`, `nivel` nem `status` — assert nos **cinco** *(I3)*
4. **não** gera linha em `activity_log` *(I2)*
5. ignora mensagem sem `wp_id`; excerpt órfão não cria registro *(I4)*
6. descarta excerpt com menos de 20 chars **e o lista** no relatório *(I5)*
6b. os contadores fecham: `curtas + sem_mensagem + ja_tinha + atualizadas == total lido` *(I5b)*
7. guarda do túnel: leitor real sem conexão → `FAILURE` + instrução, sem stack trace
8. **paridade glossário ≡ `logOnly`** *(I7)* — teste-contrato novo
9. `resumo` é redigido no `tapActivity` (não vaza cru na trilha) *(I6)*
10. `test_colunas_esperadas_e_podadas` **e** `test_fillable_exato` incluem `resumo`

**Bloco A — front**

11. card usa `resumo`, e cai no corpo quando `null` *(I8)*
12. meta description do single usa `resumo` *(I9)*
13. lead aparece quando há resumo e **some** quando não há *(D7)*
14. **regressão**: a barreira continua interceptando o restrito depois da mudança na
    description *(I10)*
15. `resumo` **não** está no `schemaMedium` *(I11)*
15b. `resumo` **existe** em `schemaAdmin` **e** em `schemaCuradoria` *(I11)*

**Bloco C1**

16. imagem em **psicografia** aparece no corpo do single *(I12)*
17. imagem em **psicografia** aparece no card variante `perfil` *(I12)*
18. **psicofonia** mostra a galeria **uma única vez** *(I13)*
19. estado vazio da pictografia **não** vaza para psicografia — **com `corpo = null`** *(I14)*
20. lista pública continua sem miniatura *(I15)*
21. artefatos literais sobrevivem à extração: `getUrl('web')`, `getUrl()`, `download="…"`,
    "Baixar", e o `alt` muda com a legenda *(I28)*

**Bloco C2**

22. Action publica: grava `publicado_em` **e** `publicado_por_id` *(I18)*
23. Action **não** altera o slug *(I17)*
23b. Action aplica as `relacionadas` escolhidas no form, **nos dois sentidos** *(I27)*
24. Action recusa `nivel = null` **e** `nivel` inexistente (`'lixo-invalido'`) — o caso que o
    `tryFrom` de `RegraPublicacao.php:23` distingue *(I19)*
25. Action recusa direcionada com destinatário **inativo** *(I19)* — o caminho que a
    validação nativa **não** cobre
26. Action **ausente** em mensagem já publicada — `assertActionHidden('publicar')`. **Só
    isso**: `visible(false)` já protege no v5.6.7, e "chamar a Action numa publicada e
    afirmar que a autoria não mudou" é falso-verde (o Filament nem monta a Action). O
    `abort_if` fica como defesa em profundidade, sem teste de UI *(I20)*
27. depois da Action, salvar **não** despublica *(I21)*
28. salvar com `status=publicado` e `nivel=null` é recusado *(I22)*
29. criar com `status=publicado` e `nivel=null` é recusado *(I23)*
30. publicar pelo Select e criar já publicada gravam autoria *(I24)*
30b. editar o **título** de uma mensagem já publicada (factory default) **não** carimba
    `publicado_em` nem `publicado_por_id` *(I26)*
31. auditoria da Action tem `porta = 'admin'` *(I29)* — exige
    `Filament::setCurrentPanel(Filament::getPanel('admin'))` no `setUp`, molde de
    [AuditoriaUserResourceTest.php:31](tests/Feature/Autorizacao/AuditoriaUserResourceTest.php#L31)
32. **não-regressão**: `schemaCuradoria` continua com `nivel` **não-required** *(I25)*

### 8.1 Armadilhas de teste (falso-verde)

- **A regra de direcionada pela UI dá falso-verde.** O Select de destinatários tem
  `->required()` + `->minItems(1)`: a validação nativa dispara **antes** de
  `RegraPublicacao`. Os caminhos que **só** a regra cobre são `nivel = null`/inválido e o
  destinatário **inativo**.
- **Action escondida não é chamável.** `hidden ⇒ isDisabled()` (`CanBeDisabled.php:24`) ⇒
  `mountAction()` desmonta e retorna `null` (`InteractsWithActions.php:131-135`); e
  `callAction()` faz `assertActionVisible()` antes (`TestsActions.php:84`). Testar "chamei e
  não aconteceu nada" prova apenas que o Filament não montou a Action.
- **O teste 19 exige `corpo = null`.** O estado vazio da pictografia é
  `@elseif (blank($mensagem->corpo))`, e nenhuma das 180 mensagens reais — nem a
  `MensagemFactory` (`:21`) — tem corpo vazio. Com corpo preenchido, o teste passa por
  vacuidade.
- **`$this->fail()` dentro de `catch (RuntimeException)` é engolido** —
  `AssertionFailedError` estende `RuntimeException`.
- **`publicado_em`/`publicado_por_id` são `$hidden` e não `$fillable`** — nunca chegam ao
  form via `fillForm`/`refreshFormData`. Assertar no registro, não no form.
- **O teste de "não gerou `activity_log`"** precisa contar antes e depois, com o log do
  **model** ativo — senão passa por não haver nada a logar.

### 8.2 Os 11 saves **e as asserções de forma** — quais quebram (C3 do dono)

`MensagemFactory` nasce `status = publicado` **e** `nivel = null`. Com a regra reassertada:

| Teste | Linha | Veredito | Ajuste |
|---|---|---|---|
| `MensagemResourceTest::test_cria_mensagem_com_corpo_sanitizado` | 97 | **QUEBRA** | passar `nivel` no `fillForm` |
| `MensagemResourceTest::test_edita_mensagem` | 111 | **QUEBRA** | idem |
| `MensagemResourceTest::test_criar_com_relacionadas_espelha_nos_dois_lados` | 129 | **QUEBRA** | idem |
| `MensagemResourceTest::test_form_tem_select_nivel_com_publico_e_aceita_null` | **66-70** | **QUEBRA** — **não é save, é asserção de forma** | o `CreateMensagem` monta com `status = publicado` (default de `MensagemForm.php:100`) e `isRequired()` **avalia a Closure** ⇒ `! $f->isRequired()` reprova. Renomear para `…_e_required_so_quando_publicado` e desdobrar em duas asserções: com `fillForm(['status' => STATUS_PENDENTE])` ⇒ **não** required; com `status = publicado` ⇒ required. Manter o `array_key_exists('publico', …)` |
| `MensagemDestinatariosPersistenciaTest` (6 saves) | 53, 79, 95, 109, 123, 141 | sobrevive | já têm nível no estado do form |
| `MensagemDestinatariosFormTest` (2 saves) | 54, 76 | sobrevive | os dois já têm nível no estado; o de `:54` espera erro em `destinatarios` pela validação **nativa**, que continua disparando antes |

**Os quatro ajustes são nominais e explícitos no plano.** É o mesmo padrão do O1 da Fase C,
onde o `required` de departamento quebrou 35 creates e quase passou batido. **Não** mudar o
default da factory para contornar: isso mascararia o defeito em toda a suíte.

Corrigir junto: [MensagemDestinatariosFormTest.php:62-63](tests/Feature/Filament/MensagemDestinatariosFormTest.php#L62-L63)
afirma *"o caso `nivel=null` já é coberto pela regressão do `MensagemResourceTest` (os creates
existentes nascem com `nivel` default null)"* — deixa de ser verdade com o C2. Mesma régua
aplicada ao docblock do `GlossarioCamposMensagem`.

### 8.3 Verificação antes do PR

- Rodar os **dois greps do I16** e conferir contra a allowlist (**0** / **exatamente 2**).
- `docker compose exec -T app ./vendor/bin/pint` — **antes** do push; o CI roda
  `pint --test` e aborta o job antes dos testes.
- `docker compose exec -T app php artisan test` — suíte cheia verde.
- `npm run build` no host, sem erro.
- Conferência manual do §7 no `localhost`.
- **Nunca** `migrate:fresh` / `refresh` / `wipe` / `reset` / seed destrutivo no dev.

---

## 9. Riscos

| # | Risco | Mitigação |
|---|---|---|
| R1 | Renomear a **coleção** por engano junto do **formato** ⇒ 180 registros com formato inválido, ou a paleta de `ResumoAutor` caindo no fallback roxo | T6 separa os três sentidos; I16 com allowlist fechada é o grep que prova (§8.3) |
| R2 | `resumo` em `logOnly` sem entrar na redação ⇒ texto de mensagem restrita cru na trilha. **≥94 dos 154 resumos** pertencem a mensagem restrita | I6 + teste 9 |
| R3 | `resumo` em `logOnly` sem entrar no glossário ⇒ some do histórico do DEPAE em silêncio | I7, teste-contrato de paridade |
| R4 | Método novo em `LeitorMensagens` ⇒ **erro fatal** nos 2 fakes | Interface separada (§5.1) |
| R5 | Publicação recusada deixando meio-save (autores/mídia já gravados) | `DB::transaction` explícita |
| R6 | Próximo "Salvar" despublicando em silêncio | `refreshFormData(['status'])` + teste 27 |
| R7 | C1 "verificado" sem nunca ter sido exercido (acervo é 100% pictografia) | Teste fabrica mídia em psicografia; §7 nomeia a mensagem 68 |
| R8 | Re-execução de `cema:importar-resumos` sobrescrevendo resumo curado | I1 (`blank()`) + teste 2 |
| R9 | Esquecer `npm run build` no cutover ⇒ lead sem estilo | §7 passo 4, declarado no PR |
| R10 | Autoria falsa carimbada nas 133 publicadas por gatilho de estado | Carimbo **só na transição** (§5.3) + I26 + teste 30b |
| R11 | Trait declarando hook ⇒ camada inteira vira no-op silencioso | Trait expõe **helpers**; pages compõem (§5.3) + os testes 28/29/30 |

**Ciência (não é desta fatia):** o conflito pré-existente entre `cema:importar-mensagens` e a
curadoria (O5 da F4b) segue como está.

---

## 10. Fora de escopo

- **Bloco B** — `AutorEspiritualController`, views de autores, `ResumoAutor`, sitemap.
  Próximo PR.
- **Fatia do molde** — `@filamentStyles` e preflight do botão. Fatia própria.
- **Preencher `contexto`** (1/180) — adiado de propósito até os resumos estarem no ar.
- Visibilidade/nível, resolvedor 3A, 3B/3C, front da lista, engajamento (F5).
- `config/navegacao.php` e o rótulo do submenu "Mensagens Públicas" — backlog.
- **Miniatura na lista pública** — fica para a F6, junto do avatar do autor (D11).
- Notificação/e-mail ao publicar pelo `/admin`.
- Filtrar `ativo` no Select de destinatários do `schemaAdmin` (ressalva declarada em §5.3).

---

## 11. Fronteiras

| Toca | Não toca |
|---|---|
| `mensagens.resumo` (coluna **nova**) + **escrita** de `status`, `publicado_em` e `publicado_por_id` (C2) | o **schema** de qualquer outra coluna de `mensagens` |
| `media.collection_name` de `Mensagem` (4 linhas) | mídia de qualquer outro model |
| `MensagemForm` (3 schemas) | `PalestraForm`, `EventoForm`, `PostForm` |
| `EditMensagem`, `CreateMensagem` | qualquer outra page do `/admin` |
| corpos de mensagem + card + show | views de palestra, blog, evento, agenda |
| `ImportadorMensagens` (só a constante da coleção) | a lógica de importação |

---

## 12. Entregável

SPEC (este documento) → **passe adversarial do consultor** → PLANO em tasks TDD → passe do
consultor → execução SDD → PR → passe final antes do merge.

**Merge = CI verde no último commit + "go" do dono.** Não pré-configurar merge automático.

---

## 13. Documentação a atualizar no PR

- `DATA-MODEL.md:462-464` — o `tapActivity()` passa a redigir **`corpo`, `contexto` e
  `resumo`**.
- `DATA-MODEL.md:524-525` — `publicado_por_id`/`publicado_em` deixam de ser "gravados só em
  `publicar()`": passam a ser gravados também pela Action do `/admin` e pelos hooks de
  `EditMensagem`/`CreateMensagem`, **sempre na transição** para publicado.
- `DATA-MODEL.md` (seção de Mídia / `mensagens`) — coluna nova `resumo` e coleção de mídia de
  `Mensagem` renomeada `pictografia` → **`imagens`**.
- SPEC da F4b — nota de superação do **O6** (ver D12).

*(O trecho de `ROADMAP.md:158` — "F4b nesta branch, PR a abrir" — já está desatualizado em
`45a9eb7`; é dívida pré-existente, corrigir por oportunidade.)*
