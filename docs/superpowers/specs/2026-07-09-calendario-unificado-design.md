# Spec — Calendário unificado (Palestras + Eventos)

> Autoria: Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09
> Molde visual: o **Calendário de Palestras** atual (`resources/views/palestras/calendario.blade.php`
> + `App\Livewire\Palestras\Calendario` + `resources/css/palestras-calendario.css`).
> ⚠️ **Pré-requisito duro:** a **Fase 3b de Eventos (PR #21) MESCLADA**. Este design usa
> `Evento::scopeVisiveisPara`, `inicioUtc()`, `status_selo`, `periodo`, `categoria`,
> `VisibilidadeEvento::rotulo()/cor()` e, **em especial, `Evento::temHora()` e
> `Evento::intervaloSchema()` — que existem SÓ na branch da 3b (working tree, ainda não na
> `main`)**. **Não iniciar antes de a 3b mesclar e passar no passe;** se o passe da 3b alterar
> esses métodos, o plano acompanha (único consumidor: a `EventosFonte`).

## 1. Contexto e objetivo

Hoje há **dois calendários mentais** e apenas um construído: `/palestra_publica/calendario`
(Palestras) e nenhuma visão de calendário para **Eventos** (a 3a/3b entregou arquivo + single +
`.ics`, mas não uma grade mensal). Este slice unifica os dois numa **única página `/calendario`**
com **filtro por tipo** — *Palestras*, *Eventos* ou *os dois* — reaproveitando a **mesma anatomia
visual** do calendário de palestras: hero com a próxima ocorrência + countdown, abas *Próximas ×
Realizadas*, navegação de mês, mini-grid de 7 colunas acendendo os "dias com" e a lista de cards do
mês.

**Objetivo de arquitetura:** a estrutura já deve aceitar um **3º tipo — Agenda da Reforma Íntima
(`AgendaDia`, coluna `data`)** sem refatorar. **Este slice NÃO implementa a Agenda** — só deixa a
abstração pronta.

**Entrega:** (a) rota `/calendario` (`calendario.index`) com **301 permanente** de
`/palestra_publica/calendario`; (b) componente Livewire unificado com filtro `tipo` e toda a
mecânica de mês/abas/grid; (c) **camada de abstração** (DTO `OcorrenciaCalendario` + `FonteCalendario`
por tipo) que mescla e ordena em PHP; (d) **visibilidade por papel** aplicada a **toda** consulta de
Evento; (e) **selo de visibilidade** para logados (reaproveita `VisibilidadeEvento::rotulo()/cor()`,
hoje código morto) na grade do calendário **e** na lista de `/eventos`; (f) SEO (canonical, JSON-LD
só de ocorrências públicas, sitemap).

## 2. Decisões travadas (com o dono)

**Produto**
- **URL:** rota nova `/calendario` (`name: calendario.index`) + **`permanentRedirect`** de
  `/palestra_publica/calendario` → `/calendario`. `palestras.calendario-ics` (o feed `.ics`)
  **permanece** como está — é o feed, não a página.
- **Tipos agora:** **Palestras + Eventos**. A arquitetura aceita um **3º tipo (Agenda)** sem
  refatorar; **não implementar a Agenda** nesta fatia (só deixar o DTO/fonte prontos).
- **Filtro:** `#[Url] tipo = 'todos' | 'palestras' | 'eventos'` (default `todos`). Os **"meses
  disponíveis"** da navegação são a **UNIÃO** dos meses dos tipos ativos, **já filtrados por
  visibilidade**.
- **Hero/countdown:** a **próxima ocorrência do filtro ativo** (se o filtro é *eventos*, o countdown
  é do próximo evento; se *todos*, é a ocorrência futura mais próxima entre os dois).

**Visibilidade (confirmado pelo dono)** — regra já implementada em `Evento::podeSerVistoPor` /
`scopeVisiveisPara`:
- **Anônimo** vê só `publico`. **Logado** vê conforme `roles.nivel`: frequentador `10` → +`logados`;
  trabalhador `20` → +`trabalhadores`; diretor `30` → +`diretoria`; administrador `100` → tudo.
- **Palestras não têm visibilidade** — aparecem **sempre**.
- O calendário aplica a regra em **TODA** query de Evento: dias acesos, lista do mês, contador,
  meses da navegação, hero/countdown, JSON-LD.

**Cache** (verificado: **sem bug hoje** — o Symfony já devolve `Cache-Control: no-cache, private` por
padrão e o projeto não tem cache público de *página*; só assets no `MidiaController`/media-library):
- Ainda assim, **quando houver usuário logado**, `/calendario` responde **explicitamente** com
  `Cache-Control: private, no-store` (como o `show()` de evento restrito já faz). É a defesa de rede
  contra alguém, no futuro, cachear publicamente uma página que varia por nível de acesso.

**Selo de visibilidade** (reaproveita `VisibilidadeEvento::rotulo()/::cor()` — existem desde a 3a e
**não são usados em nenhuma view**):
- Nos cards do calendário **e** na lista de `/eventos`, **para usuário logado**, exibir um **selo
  discreto** quando `visibilidade !== Publico` (ex.: "Somente diretoria", cor do enum). **Não exibir
  para anônimo** (ele nunca deveria ver item restrito). Benefício duplo: o logado entende por que
  aquele item não é público, e um eventual vazamento fica visível de imediato.

**Regras de sempre** (CLAUDE.md): pt-BR em tudo; tokens Tailwind v4 (`@theme`); **nunca**
`migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo; **Pint** antes do push; `docker compose
exec -T app php artisan test` + conferência real no `localhost`; mobile-first, A11y e SEO desde a
estrutura; cabeçalho de autoria nos arquivos novos.

## 3. Fora de escopo (não fazer)

- **Agenda da Reforma Íntima** como 3º tipo — só deixar o DTO/fonte prontos para recebê-la depois.
- **`.ics` unificado** (`/calendario.ics`). Os feeds separados continuam: `palestras.calendario-ics`
  e `eventos.feed-ics` (3b). O botão **"Assinar calendário" É mantido** (decisão do dono), mas abre um
  **modal genérico** com os feeds existentes — 1 feed quando filtrado (`palestras.calendario-ics` ou
  `eventos.feed-ics`), os **dois** quando `tipo=todos`. Não há endpoint `.ics` novo.
- **Restrição de quem CRIA** eventos por visibilidade (é a Fase 4 do módulo Eventos).

## 4. Arquitetura — o coração: DTO + Fontes (por que não UNION em SQL)

Palestra é um **instante** (`data_da_palestra` datetime); Evento é um **intervalo** (`data_inicio`
date + `data_fim` date + horas opcionais como strings). As colunas e as regras de "passado/futuro"
divergem — **UNION em SQL seria frágil e não escala** para a Agenda. A união é feita **em PHP** sobre
um DTO comum; são **poucos itens por mês** (~4 palestras + ~5 eventos), então o custo é irrelevante.

### 4.1. DTO `App\Support\Calendario\OcorrenciaCalendario` (readonly)

Representa **uma ocorrência** já pronta para a UI, agnóstica de model:

```
final readonly class OcorrenciaCalendario
{
    public function __construct(
        public string $tipo,             // 'palestra' | 'evento' (| 'agenda' no futuro)
        public string $chave,            // p/ wire:key e âncora — ex.: "evento-12"
        public string $titulo,
        public string $url,
        public CarbonInterface $inicio,  // instante (com hora) OU 00:00 local (dia inteiro)
        public ?CarbonInterface $fim,    // fim p/ o SPAN de dias no grid — é uma DATA, NÃO o instante de término; null = instantâneo
        public bool $temHora,            // false = "dia inteiro" (chip sem hora)
        public ?string $subtitulo,       // "com Fulano · Tema" (palestra) | local (evento)
        public string $corAcento,        // categoria do evento | cor fixa da palestra
        public array  $selo,             // status pronto: ['rotulo','cor','cor_texto']
        public ?array $seloVisibilidade, // ['rotulo','cor'] p/ evento restrito; null caso contrário
        public ?string $imagem = null,   // thumb do palestrante | flyer do evento (hero/lista)
        public ?string $iniciais = null, // fallback do avatar quando não há imagem
    ) {}

    /** Dias (1..N) que a ocorrência cobre DENTRO de (ano,mês) — multi-dia acende vários. */
    public function diasNoMes(int $ano, int $mes): array;

    /** Ordena por início; empate → palestra antes de evento (estável). */
}
```

- **Multi-dia:** `diasNoMes()` de um evento 27→29 devolve `[27,28,29]`; de uma palestra (instante),
  `[dia]`. É o que faz o grid acender 3 dias.
- **`seloVisibilidade`** já vem **null para anônimo** (a fonte recebe o `?User` e decide), então a
  view não precisa reguardar — mas a view **também** só o renderiza para logado (defesa em profundidade).

### 4.2. Interface `App\Support\Calendario\FonteCalendario`

Uma implementação **por tipo**; cada uma encapsula a regra de passado/futuro e a visibilidade do seu
model:

```
interface FonteCalendario
{
    public function tipo(): string; // 'palestra' | 'evento'

    /** Meses 'Y-m' com ocorrência VISÍVEL no modo. @return list<string> */
    public function meses(string $modo, ?User $u): array;

    /** Ocorrências VISÍVEIS que TOCAM (ano,mês) no modo, já como DTO. @return Collection<OcorrenciaCalendario> */
    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection;

    /** Próxima ocorrência FUTURA visível (hero/countdown); null se não houver. */
    public function proxima(?User $u): ?OcorrenciaCalendario;
}
```

Implementações desta fatia:
- **`App\Support\Calendario\Fontes\PalestrasFonte`** — envolve `Palestra`. `modo` = `data_da_palestra
  >= agora` (próximas) / `< agora` (realizadas). Sem visibilidade (ignora `$u`). Mapeia palestrante
  (`palestrantesAtivos`), tema (`assuntos`), badge de formato (online/presencial) para o DTO.
- **`App\Support\Calendario\Fontes\EventosFonte`** — envolve `Evento` **sempre** via
  `->visiveisPara($u)`. `modo` = `COALESCE(data_fim, data_inicio) >= hoje` (próximos) / `< hoje`
  (realizados). "Toca o mês" = **overlap**: `data_inicio <= último-dia-do-mês` **e**
  `COALESCE(data_fim, data_inicio) >= primeiro-dia-do-mês`. Selo de status = `status_selo`; selo de
  visibilidade só quando `$u` pode ver **e** `visibilidade !== Publico`.
- **(futuro) `AgendaFonte`** — `AgendaDia` (coluna `data`); **não** nesta fatia.

O registro das fontes ativas é derivado do filtro `tipo` (todos → ambas; senão a única escolhida).

> **Ganho:** a Agenda entra criando **só** `AgendaFonte` + registrando-a. Nenhuma mudança no
> componente, no DTO ou nas views.

## 5. Componente Livewire `App\Livewire\Calendario\Calendario`

Espelha a mecânica de `Palestras\Calendario` (comprovadamente reaproveitável), generalizada sobre as
fontes:

- **Estado (`#[Url]`):** `modo` (`proximas` default | `realizadas`), `mes` (`Y-m`), **`tipo`**
  (`todos` default | `palestras` | `eventos`).
- **`fontesAtivas(): array`** — resolve as `FonteCalendario` conforme `$tipo`.
- **`mesesModoAsc()`** — **união** de `fonte->meses($modo, $user)` de todas as fontes ativas, únicos,
  ordenados ASC. É a base de navegação, default de mês e limites `mesAnterior/mesProximo`.
- **`ocorrenciasDoMes()`** — `merge` de `fonte->ocorrencias(...)` das fontes ativas, ordenado por
  `inicio` (o DTO define o desempate). Alimenta a lista **e** a matriz.
- **`matriz()`** — grade 7 colunas (semana começa domingo, `daysInMonth` + `dayOfWeek` como hoje).
  Para **cada** ocorrência, acende **todos** os dias de `diasNoMes()` (multi-dia). Cada dia aceso
  guarda a(s) ocorrência(s) e a "âncora" da 1ª (para o scroll-to no clique). Marca `hoje`.
- **`proxima()`** — mínimo por `inicio` entre `fonte->proxima($user)` das fontes ativas (hero/countdown).
- Reaproveita `updatedModo`, `mesAnterior/mesProximo`, `irParaAno`, `mesPadrao`. **Novo:**
  `updatedTipo` reseta o mês para o default do novo conjunto de meses.
- **Reset de mês:** ao trocar `modo` **ou** `tipo`, se o `mes` atual não existe no novo
  `mesesModoAsc()`, cai no `mesPadrao`.

O componente **não** define headers HTTP (Livewire); o `Cache-Control` sai do **controller** (§8).

## 6. Armadilhas que o plano resolve (checklist de teste)

1. **Multi-dia.** Evento 27→29 acende **3 dias** no grid e aparece no mês. "Próximos × Realizados" de
   evento é `COALESCE(data_fim, data_inicio) >= hoje` — **não** `data >= now()`. Evento que cruza a
   virada do mês aparece nos **dois** meses (overlap). *(Teste: grid acende 27,28,29; evento
   30/jun→02/jul aparece em jun e jul.)*
2. **⚠️ Vazamento de visibilidade (o ponto mais crítico).** Se o grid acender um dia por um evento de
   `diretoria`, revela sua existência a um anônimo — justo o que a 3a evitou com 404. **TODA** consulta
   de Evento passa por `visiveisPara($user)`: dias acesos, lista, contador, meses da navegação,
   hero/countdown, JSON-LD. *(Testes obrigatórios: anônimo **não** vê o dia aceso nem o card de um
   evento `diretoria`; **nem** ele conta no contador do mês; um diretor vê ambos. Idem meses da
   navegação: um mês que só tem evento restrito não aparece para anônimo.)*
3. **União cronológica sem UNION SQL.** DTO + fontes; merge/sort em PHP (§4).
4. **Filtro.** `tipo` filtra fontes ativas; meses da navegação = **união** dos tipos ativos, já por
   visibilidade. *(Teste: `tipo=palestras` não traz eventos e vice-versa; `todos` intercala por data.)*
5. **Rota e links.** Criar `calendario.index`; `permanentRedirect` da URL antiga; **atualizar os 6
   usos** de `route('palestras.calendario')` (senão `RouteNotFoundException`). Contextos de palestras
   (`palestras/index`, `palestrantes/*`) linkam `/calendario?tipo=palestras` (pré-filtrado). Manter
   `palestras.calendario-ics`. *(Teste: `route:list`/grep sem `palestras.calendario`; `/calendario`
   200; 301 da antiga.)*
6. **SEO.** `canonical` em `/calendario`; JSON-LD `ItemList/Event` **só** com ocorrências **públicas**
   (evento restrito fora — `visiveisPara(null)`); `/calendario` no sitemap.
7. **Hero/countdown** = próxima ocorrência do **filtro ativo** (§7).
8. **Cache** = `private, no-store` para logado; teste garante que a resposta logada **não** contém
   `public` no `Cache-Control` (§8).

## 7. Anatomia visual (reaproveita o molde de palestras)

Mesmos blocos e classes CSS (`cema-cal-day`, `cema-cal-avatar`, `cema-chip-data--*`, `cema-row`,
`.is-destaque`, `x-ui.countdown`, `x-ui.particulas`) do calendário de palestras:

- **Hero** (roxo, partículas) com título "Calendário", subtítulo e (proposta §9) botão "Assinar
  calendário". Abaixo, o **card "Próxima ocorrência"** com countdown — **adapta por tipo**:
  - *palestra* → avatar do palestrante (ou iniciais) + "com Fulano · Tema" + badge formato;
  - *evento* → flyer/ícone + categoria + local; selo de status (`status_selo`).
  Ambos: data/hora (ou "dia inteiro"), countdown (`x-ui.countdown :data="$proxima->inicio"`), "Ver".
- **Barra de período:** abas *Próximas × Realizadas* + **filtro de tipo** (novo: pills
  *Todos / Palestras / Eventos*) + navegação de mês (‹ ›) + seletor de ano.
- **Mini-grid** (aside sticky): 7 colunas, dias acesos clicáveis (scroll-to na linha). **Legenda**
  ampliada: Palestra (dourado) · Evento (cor de acento) · Hoje. Dia com os dois tipos: até 2 pontos.
- **Lista do mês:** linhas no padrão `cema-row`, **unificadas** — chip de data (ou "dia inteiro"),
  badge de tipo (Palestra/Evento), título, subtítulo, selo de status e, para logado, **selo de
  visibilidade** (evento restrito). Contador "N itens" no cabeçalho do mês (plural correto).
- **Estados vazios:** por mês e total, no padrão atual (mensagem sensível ao `modo`/`tipo`).

Novos partials/classes específicas de tipo (badge Palestra/Evento, selo de visibilidade) entram em
`resources/css/calendario.css` (import em `app.css`), sem tocar o CSS de palestras.

## 8. Rotas, controller e cache

- **`routes/web.php`:**
  - Substituir a rota-página `/palestra_publica/calendario` (hoje `palestras.calendario`, web.php:55)
    por `Route::permanentRedirect('/palestra_publica/calendario', '/calendario');` — **na mesma
    posição** (antes de `palestras.show`, senão `{slug}` a captura).
  - Adicionar `Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');`.
  - **Manter** `/palestra_publica/calendario.ics` (`palestras.calendario-ics`).
- **`CalendarioController::index()`** passa a renderizar a **página unificada**. `$proximasParaSeo` =
  **merge** de palestras públicas futuras + eventos **`visiveisPara(null)`** futuros, ordenado, `<=16`,
  para o JSON-LD `ItemList/Event` (só públicos). `feed()` (palestras) **fica como está**.
- **Cache:** se `auth()->check()`, adicionar `Cache-Control: private, no-store` à resposta da view
  (via `response()->view(...)->header(...)`, como o `EventoController::show`).
- **Links (6):** `agenda/index.blade.php:131`, `conta/painel.blade.php:4` e `:20` →
  `route('calendario.index')`; `palestras/index.blade.php:29`, `palestrantes/index.blade.php:29`,
  `palestrantes/perfil/hero.blade.php:49` → `route('calendario.index', ['tipo' => 'palestras'])`.
  Atualizar também qualquer teste que aponte para `palestras.calendario` (ex.: `CalendarioRotaTest` →
  passa a assertar o **301**).

## 9. Decisões do passe adversarial (2026-07-09) — resolvidas

1. **Botão "Assinar calendário": MANTER**, abrindo um **modal genérico** (`<x-ui.assinar-modal :feeds>`)
   com os feeds existentes — `tipo=palestras` → só `palestras.calendario-ics`; `tipo=eventos` → só
   `eventos.feed-ics`; `tipo=todos` → **os dois** links. O botão fica dentro do Livewire (reage ao
   `$tipo`) e dispara `open-assinar`. **Não** criar `.ics` unificado.
2. **Menu: NÃO mexer** nesta fatia. A página é alcançada pelos 6 links internos já existentes
   (relinkados para `/calendario`, com `?tipo=palestras` nos contextos de palestras).
3. **Selo de visibilidade:** **ponto na cor do enum + rótulo em texto neutro ESCURO**
   (`text-text-ink` ≈ 14:1 sobre `bg-surface`). **Não** `text-text-muted` (≈3,3:1, reprova WCAG AA em
   11px). O ponto é decorativo (`aria-hidden`).
4. **Grid com dois tipos no mesmo dia: APROVADO** — dia aceso com **até 2 pontinhos** (dourado =
   palestra, cor de acento = evento), `aria-hidden`; o botão do dia tem `aria-label` listando os
   títulos; clique rola para a 1ª ocorrência daquele dia.
5. **"Realizadas" de evento: CONFIRMADO** — `COALESCE(data_fim, data_inicio) < hoje` (data, não
   instante). Evento *em andamento* (começou ontem, termina amanhã) conta como **Próximo**.

## 10. Impacto e não-objetivos de dados

- **Nenhuma migration** — o slice é só front + camada de suporte + rotas. Não cria/altera tabela.
- **Sem novas dependências.** Reusa Livewire, Alpine, `x-ui.countdown`, `x-ui.particulas`, o CSS de
  calendário e os models existentes.
- **Performance:** ~poucos itens/mês; eager-load de relações (palestrantes/assuntos; `categoria`/
  `media` do evento) nas fontes para evitar N+1; sem cache de página (varia por papel).

## 11. Riscos e mitigações

| Risco | Mitigação |
|---|---|
| Vazar existência de evento restrito no grid/contador/meses | `visiveisPara($user)` em **toda** query de Evento; testes anon×diretor cobrindo dia, card, contador e meses |
| `route('palestras.calendario')` órfão + testes órfãos → suíte quebra | **7 arquivos** tratados (`CalendarioComponentTest`, `CalendarioRotaTest`, `CalendarioSeoTest`, `CalendarioStubTest`, `AssinarModalTest`, `PalestrasArchiveSeoTest`, `PalestrantePerfilRedesignTest`) + **2 INTOCADOS** (`CalendarioFeedTest`; `CalendarioPalestraTest` — testa `palestras.evento-ics`, o nome engana); grep autoritativo antes de apagar; **suíte inteira sem `--filter`** no fechamento |
| Multi-dia sumir no grid ou na virada de mês | DTO `diasNoMes()` com **fuso explícito** (`America/Sao_Paulo`) + overlap por mês; testes dedicados |
| Página logada cacheada por proxy no futuro | `Cache-Control: private, no-store` quando logado + teste "sem `public`" |
| Acoplar a Agenda e ter de refatorar | Abstração `FonteCalendario`/DTO; Agenda entra só como nova fonte |
| **Regressão: `.ics` de palestras perde a única porta de UI** (o modal só era usado na página apagada) | Criar `<x-ui.assinar-modal :feeds>` genérico + botão no `/calendario` **antes** de apagar `components/palestras/assinar-modal`; `x-eventos.assinar-modal` intocado |
| Depender de métodos da 3b não mesclados (`temHora()`, `intervaloSchema()`) | Pré-requisito duro: **não iniciar antes de a 3b mesclar**; único consumidor é a `EventosFonte` |
| `Str::plural` pluralizar em inglês ("items") | Usar `$n === 1 ? 'item' : 'itens'`; teste **assere** "2 itens" p/ diretor (asserção positiva) |

## 12. Critérios de aceite

- `/calendario` responde 200; `/palestra_publica/calendario` → **301** para `/calendario`;
  `route('palestras.calendario')` não existe mais em lugar nenhum.
- Filtro `tipo` (todos/palestras/eventos) troca as fontes; meses da navegação são a união dos ativos.
- Multi-dia acende todos os dias e aparece nos meses que cobre.
- **Anônimo** não vê dia/​card/​contador/​mês de evento restrito; **diretor** vê. JSON-LD e sitemap só
  com públicos.
- Logado recebe `Cache-Control` **sem** `public`; selo de visibilidade aparece só para logado em
  evento restrito (calendário **e** `/eventos`).
- Hero/countdown refletem a próxima ocorrência do filtro ativo.
- Suíte verde (`docker compose exec -T app php artisan test`), Pint limpo, `npm run build`, conferência
  real no `localhost` (grid, abas, filtro, navegação, mobile).
```
