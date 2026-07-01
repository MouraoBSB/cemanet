# Calendário de Palestras — Design/Spec

**Data:** 2026-07-01
**Autor:** Thiago Mourão — https://github.com/MouraoBSB
**Handoff visual:** `design_handoff_calendario/` (README hi-fi + `prototipo/Calendário de Palestras.dc.html` + 5 screenshots)
**Fatia:** única — **página + feed .ics + modal "Assinar"** (aprovado pelo dono).

---

## 1. Objetivo

Construir a página **Calendário de Palestras** (`/palestra_publica/calendario`) fiel ao handoff:
formato **agenda** (denso, legível) com **mini-calendário** indexador, **destaque da próxima
palestra** com contagem regressiva, **tabs Próximas/Realizadas** + navegação de mês/ano, e o
**diferencial "Assinar calendário"** (feed `.ics`/webcal para Google/Apple + baixar). Substitui
o **stub** entregue na fatia da archive. Sem dependências novas; reusa componentes existentes.

### Em escopo
- Página Livewire completa (destaque, barra de período, bloco do mês com mini-calendário + agenda, estado vazio, "Veja também").
- Feed iCalendar agregado (`.ics`/webcal) das próximas palestras + modal de assinatura.
- Refactor: extrair o builder de VEVENT/escaper do `.ics` por-palestra para `App\Support\Palestras\FeedIcs`, usado pelo single **e** pelo feed (DRY), **preservando** o `CalendarioPalestraTest`.
- SEO (JSON-LD `ItemList`/`Event`), A11y, responsivo mobile-first.
- CSS próprio da página (`resources/css/palestras-calendario.css`).
- Testes + preservação do `CalendarioStubTest` e do `CalendarioPalestraTest`.

### Fora de escopo
- Importação/mudança de schema (dados já vêm do legado; sem migração).
- Lembretes/notificações push (o feed `.ics` é o mecanismo de assinatura).
- Qualquer alteração na archive/single além do refactor do `.ics` para `FeedIcs`.

---

## 2. Contexto (mapeado) — realidade que guia o design

**Dados (dev, pós-import):** 127 palestras publicadas; **126 com `data_da_palestra`** (1 sem);
**4 futuras** (domingos 06/13/20/26-jul-2026, 19h). **124/126 são domingos 19h, mas 2 são
segundas 20h** → o mini-calendário destaca **qualquer dia com palestra** (não assume domingo) e
o feed/JSON-LD usam a **hora real** (nunca 19h fixo). Acervo 2024-01 → 2026-07 (31 meses).
Cobertura: `link_youtube` 100%, `assuntos` 89% (algumas sem tema), foto de palestrante 99% (1 sem
→ iniciais), `duracao` **vazia** em todas → `DuracaoPalestra::minutos(null)` = **90 min**.
1 palestra sem palestrante ativo (`Cine Debate`) — a UI deve degradar sem quebrar.

**Já existe (reusar — assinaturas confirmadas):**
- `<x-ui.particulas />` (sem props) · `<x-ui.countdown :data="$dt" />` (só renderiza se `$dt->isFuture()`; variante não-compacta → `role="timer" aria-label="Contagem regressiva para a palestra"`).
- `<x-palestra.badge-formato :palestra="$p" variante="claro|solido" />` (usa `$p->formato = ['slug','rotulo','cor']`).
- `<x-layout.app title="" description="">` com `<x-slot:head>` (injeta no `<head>`).
- Padrão de hero/partículas, **círculos decorativos gold/secondary** e **"Veja também"** da archive (`resources/views/palestras/index.blade.php`), CSS `resources/css/palestras-archive.css` (`.cema-archive-particles`, gradientes, `cemaFadeUp`), tokens `@theme` em `app.css` (var(--color-*)).
- `App\Models\Palestra`: scope `publicado()`, `palestrantesAtivos` (1–2), `assuntos`, accessors `formato`, `youtube_id`, `data_da_palestra` (cast datetime). `App\Support\Palestras\DuracaoPalestra::minutos()`.
- `PalestraController@calendario($slug)` (`palestras.evento-ics`): monta o `.ics` por-palestra (UID, DTSTART/DTEND em UTC `Ymd\THis\Z`, SUMMARY, escaper `\\;,\r\n`, LOCATION). **Fonte do refactor.**
- **Stub atual** (a substituir): `CalendarioController@index` → `view('pages.calendario', ['proximas'=>...])`; view `resources/views/pages/calendario.blade.php` (hero simples + lista de próximas). Rota `palestras.calendario` já registrada em `/palestra_publica/calendario` (antes de `show`, com `->where('slug','[a-z0-9-]+')` no show).

---

## 3. Decisões (aprovadas + 4 polimentos)

1. **Fatia única:** página + feed `.ics` + modal.
2. **Feed = próximas ≤16 futuras** (`data_da_palestra >= now()`), 1 `VEVENT` cada. Google "assinar por URL" só funciona em **produção** (Google não alcança localhost) — esperado.
3. **Mini-calendário destaca qualquer dia com palestra** (por causa das 2 segundas). **Rótulo do mini-calendário = "Dias com palestra"** (não "Domingos com palestra"). *(polimento #1)*
4. **`startDate` (JSON-LD) e `DTSTART` (feed/single) usam o `data_da_palestra` real, com a hora real (19h ou 20h) — nunca hora fixa.** O dado manda. *(polimento #2)*
5. **"Tema" = taxonomia `assuntos`** real; palestra sem assunto → sem tag de tema.
6. **Destaque "Próxima palestra" sem fallback** (some quando não há futura) — coerente com a archive.
7. **Modal** = componente Blade `<dialog>` nativo + Alpine (não inline).
8. **`FeedIcs` compartilhado:** extraio o escaper + montagem de `VEVENT`/`VCALENDAR` para `App\Support\Palestras\FeedIcs`; **refatoro o `.ics` do single** para usá-lo, mantendo a saída idêntica (DTSTART/DTEND/SUMMARY) → `CalendarioPalestraTest` continua verde. *(polimento #4: rodar suíte completa no fecho)*
9. **Rota do feed:** `GET /palestra_publica/calendario.ics`, nome **`palestras.calendario-ics`**, registrada **antes** de `palestras.show` (cinto-e-suspensório; o `.` já não casa `[a-z0-9-]`, mas registrar antes por clareza). **Não** reusar o nome `palestras.calendario` (é a página). *(polimento #3)*
10. **CalendarioStubTest preservado:** a página real mantém `assertOk`, o texto "Calendário de Palestras" e a listagem de uma palestra futura; ajusto seletores só se o markup exigir. *(polimento #4)*

---

## 4. Arquitetura e arquivos

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `routes/web.php` | Modificar | Rota do feed `palestras.calendario-ics` (antes do `show`). |
| `app/Http/Controllers/CalendarioController.php` | Modificar | `index()` → casca `palestras.calendario` + JSON-LD das próximas; `feed()` → `.ics` agregado via `FeedIcs`. |
| `app/Support/Palestras/FeedIcs.php` | Criar | Escaper + `VEVENT` por palestra + documento `VCALENDAR`. Compartilhado. |
| `app/Http/Controllers/PalestraController.php` | Modificar | `calendario()` passa a usar `FeedIcs` (saída idêntica). |
| `app/Livewire/Palestras/Calendario.php` | Criar | Estado `#[Url] modo/mes`; destaque, meses/anos, matriz do mini-calendário, linhas do mês. |
| `resources/views/livewire/palestras/calendario.blade.php` | Criar | Destaque + barra de período + bloco do mês (mini-calendário + agenda) + estado vazio. |
| `resources/views/palestras/calendario.blade.php` | Criar | Casca: hero + breadcrumb + `<livewire:palestras.calendario />` + "Veja também" + modal + JSON-LD. |
| `resources/views/pages/calendario.blade.php` | Remover | Substituída pela casca acima (stub aposentado). |
| `resources/views/components/palestras/assinar-modal.blade.php` | Criar | Modal `<dialog>` + Alpine (Google/Apple/baixar). |
| `resources/css/palestras-calendario.css` | Criar | Mini-calendário, linha da agenda, avatar dourado, chips, countdown boxes, animações. |
| `resources/css/app.css` | Modificar | `@import './palestras-calendario.css';`. |
| `tests/Feature/Front/CalendarioStubTest.php` | Manter/ajustar | Preservar (200 + "Calendário de Palestras" + futura). |
| `tests/Feature/Front/CalendarioPalestraTest.php` | Manter | `.ics` do single via `FeedIcs` (saída idêntica). |
| `tests/…` (novos) | Criar | Componente, feed, SEO, `FeedIcs` unit, ordem de rota. |

---

## 5. Rotas (`routes/web.php`)

Ordem no bloco de palestras (mantendo o que já existe):

```php
Route::get('/palestra_publica', [PalestraController::class, 'index'])->name('palestras.index');
Route::get('/palestra_publica/calendario', [CalendarioController::class, 'index'])->name('palestras.calendario');
Route::get('/palestra_publica/calendario.ics', [CalendarioController::class, 'feed'])->name('palestras.calendario-ics'); // NOVO — antes do show
Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])->name('palestras.show')->where('slug', '[a-z0-9-]+');
Route::get('/palestra_publica/{slug}/calendario.ics', [PalestraController::class, 'calendario'])->name('palestras.evento-ics')->where('slug', '[a-z0-9-]+');
```

---

## 6. `App\Support\Palestras\FeedIcs` (builder compartilhado)

Encapsula o que hoje está inline no `PalestraController@calendario`. Saída **idêntica** para o
single (DTSTART/DTEND em UTC `Ymd\THis\Z`, SUMMARY escapado) → não regride o `CalendarioPalestraTest`.

Assinatura:
```php
final class FeedIcs
{
    public const PRODID = '-//CEMA//Palestras//PT-BR';

    /** Escapa valor para iCal: \\ ; , e quebras de linha (CRLF/CR/LF → \n). */
    public static function escapar(string $v): string;

    /** Linhas de UM VEVENT a partir da palestra (usa a hora REAL de data_da_palestra). */
    public static function vevento(Palestra $p): array; // requer $p->data_da_palestra != null

    /** Documento VCALENDAR completo com N VEVENTs (para o feed). */
    public static function documento(iterable $palestras): string; // pula palestras sem data
}
```

`vevento(Palestra $p)`:
- `$inicio = $p->data_da_palestra->copy()->utc()` · `$fim = $inicio->copy()->addMinutes(DuracaoPalestra::minutos($p->duracao))` · `$fmt = fn($d)=>$d->format('Ymd\THis\Z')`.
- `UID:palestra-{id}@cemanet.org.br`, `DTSTART:{fmt(inicio)}`, `DTEND:{fmt(fim)}`, `SUMMARY:{escapar(titulo)}`.
- `DESCRIPTION`: "com {palestrantes} · {tema} · {Online|Presencial}\n{url do single}" (escapado; campos ausentes omitidos com elegância).
- `LOCATION`: **Presencial** → endereço da sede CEMA; **Online** → "Online — YouTube". (Não asserido pelo teste do single → melhoria segura e alinhada ao handoff.)
- `DTSTART` usa a **hora real** (19h dom → `T220000Z`; 20h seg → `T230000Z`).

`documento($palestras)`: `BEGIN:VCALENDAR / VERSION:2.0 / PRODID / X-WR-CALNAME:Palestras CEMA / X-WR-TIMEZONE:America/Sao_Paulo` + VEVENTs + `END:VCALENDAR`, unidos por `\r\n` (CRLF, RFC 5545).

`PalestraController@calendario` passa a: `abort_if(sem data,404)` + `response(FeedIcs::documento([$palestra]), 200, [... attachment por-slug ...])`. Saída idêntica à atual → teste verde.

---

## 7. `CalendarioController`

```php
public function index(): View
{
    $proximas = Palestra::publicado()->whereNotNull('data_da_palestra')
        ->where('data_da_palestra', '>=', now())
        ->with(['palestrantesAtivos', 'assuntos'])
        ->orderBy('data_da_palestra')->take(16)->get();

    return view('palestras.calendario', ['proximasParaSeo' => $proximas]); // JSON-LD ItemList
}

public function feed(Request $request): Response
{
    $palestras = Palestra::publicado()->whereNotNull('data_da_palestra')
        ->where('data_da_palestra', '>=', now())
        ->with(['palestrantesAtivos', 'assuntos'])
        ->orderBy('data_da_palestra')->take(16)->get();

    $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];
    if ($request->boolean('download')) {
        $headers['Content-Disposition'] = 'attachment; filename="cema-palestras.ics"';
    }
    return response(FeedIcs::documento($palestras), 200, $headers);
}
```
> Feed **inline** por padrão (para `webcal`/assinatura funcionar); `?download=1` adiciona `attachment` (botão "Baixar .ics" do modal). Cache curto opcional (`Cache-Control: public, max-age=1800`).

---

## 8. Componente Livewire `App\Livewire\Palestras\Calendario`

Estado (`#[Url]`, compartilhável/SEO):
```php
#[Url(as: 'modo', except: 'proximas')] public string $modo = 'proximas';   // proximas | realizadas
#[Url(as: 'mes')]                      public ?string $mes = null;          // 'YYYY-MM'
```

Ciclo de vida:
- `mount()`: normaliza `$modo`; se `$mes` inválido/nulo, define para o **1º mês do conjunto do modo** (proximas → mês da próxima; realizadas → mês mais recente do passado).
- `updatedModo()`: reseta `$mes` para o 1º mês do novo conjunto.
- `mesAnterior()`/`mesProximo()`: movem `$mes` para o vizinho **dentro do conjunto** (desabilitados nos limites).
- `irParaAno(int $ano)`: `$mes` = 1º mês daquele ano no conjunto atual.

`render()` deriva (tudo Eloquent + Carbon, sem SQL não-portável):
- **`$agora = now()`** — **fronteira única passado/futuro** (mesmo instante para `$proxima`, `$mesesDoModo` e as marcas Realizada/gravada; **nada de `startOfDay`**). Assim, passada a hora da palestra, ela migra de Próximas para Realizadas **sem estado órfão** (sem ficar "nem próxima, nem realizada" no restante do dia).
- **`$proxima`** = `publicado()->whereNotNull(data)->where('data','>=',$agora)->with(palestrantesAtivos,assuntos)->orderBy(data)->first()` (**sem fallback**; pode ser null).
- **`$mesesDoModo`** = distinct `Y-m` das palestras do modo (proximas: `data >= $agora`, asc; realizadas: `data < $agora`, desc) — **distinct em PHP** (`pluck(data)->map(->format('Y-m'))->unique()->…`).
- **`$anos`** = anos distintos de `$mesesDoModo`.
- **`$mesFoco`** = `$mes` se ∈ `$mesesDoModo`, senão 1º do conjunto (ou null se vazio).
- **`$palestrasDoMes`** = `publicado()->whereNotNull(data)->whereYear(data, ano($mesFoco))->whereMonth(data, mes($mesFoco))->with(palestrantesAtivos,assuntos)->orderBy(data)->get()` (**`whereYear/whereMonth` portáveis**). Cada palestra marca: **Próxima** (`id === $proxima?->id`), **Realizada** (`data < $agora`), **gravada** (`link_youtube` presente **e** `data < $agora`). *(Marcas derivadas do mesmo `$agora` → uma palestra realizada mais cedo hoje já conta como Realizada/gravada, nunca fica órfã.)*
- **`$matriz`** = matriz do mini-calendário de `$mesFoco` (Carbon: `firstOfMonth`, `dayOfWeek` com semana começando no **domingo**, `daysInMonth`), + mapa `dia => ['slug'=>…, 'titulo'=>…]` para **qualquer dia** com palestra no mês (primeira, se houver mais de uma). Marca **hoje** (célula do dia atual = quando `$mesFoco` é o mês corrente de `$agora`, destaca `$agora->day` — realce visual, **independente** da fronteira passado/futuro).
- Passa: `proxima, modo, mesFoco, mesesDoModo, anos, palestrasDoMes, matriz, agora, temAnterior, temProximo`.

Sem fetch externo; contagem regressiva é **Alpine** (`x-ui.countdown`), nunca recalculada no servidor.

---

## 9. Views

### 9.1 Casca `resources/views/palestras/calendario.blade.php`
`<x-layout.app title="Calendário de Palestras" description="Todo domingo, às 19h. Assine e receba cada palestra pública do CEMA no seu calendário.">`
- `<x-slot:head>`: **JSON-LD** `ItemList` de `Event` a partir de `$proximasParaSeo` (§10).
- **Hero** roxo (`from-primary to-footer-bg`) + `<x-ui.particulas>` + brilho radial: kicker mono, H1 "Calendário de Palestras", régua dourada `h-1 w-16 bg-gold`, subtítulo `font-light`; à direita **botão "Assinar calendário"** (`bg-white/10 border-white/20 rounded-2xl`, ícone dourado) que faz `x-data @click="$dispatch('open-assinar')"`.
- **Breadcrumb** `Início › Palestras › Calendário` (`bg-surface`, `aria-current="page"`).
- `<section class="bg-surface"> … <livewire:palestras.calendario /> … </section>`.
- **"Veja também"** (padrão da archive): pílulas com bolinha `accent` → `palestras.index`, `palestrantes.index`, `blog.index` (+ demais rotas reais existentes).
- `<x-palestras.assinar-modal :feed-url="route('palestras.calendario-ics')" />`.

### 9.2 Livewire `resources/views/livewire/palestras/calendario.blade.php`
- **Destaque "Próxima palestra"** (`@if ($proxima)`): rótulo com bolinha dourada pulsante + "Próxima palestra"; banner `rounded-[18px]` gradiente roxo + **círculos gold/secondary** (reuso do padrão da archive); avatar 88px de **iniciais** (gradiente dourado, rotação por índice) ou foto; chip de data (`data_da_palestra->translatedFormat('d \d\e M')` · `format('H\hi')` — **hora real**); `<x-palestra.badge-formato variante="solido">`; H3 título; "com **{palestrante(s)}** · {1º assunto}"; **`<x-ui.countdown :data="$proxima->data_da_palestra" />`** (não-compacto); botão branco "Ver palestra".
- **Barra de período** (card branco): tabs `role="tablist"` **Próximas | Realizadas** (`wire:click="$set('modo', …)"`, `aria-selected`); navegação **‹** (`wire:click="mesAnterior"`, `disabled` se `!$temAnterior`) **[Mês Ano]** **›** (`mesProximo`); `<select wire:model.live` que chama `irParaAno` — na prática `wire:change="irParaAno($event.target.value)"`) **Ano**.
- **Bloco do mês** (`@if ($mesFoco)`): cabeçalho (Mês capitalizado + ano + pílula "N palestra(s)"); `flex gap-6 flex-wrap`:
  - **Mini-calendário** (sticky `top-[88px]`, `flex-basis 300px`): rótulo mono **"Dias com palestra"**; grid 7col `D S T Q Q S S`; dias fora do mês vazios; **dia com palestra = círculo dourado** clicável com `title="{titulo}"` e `@click` Alpine → **scroll suave** até `#linha-{slug}` + **pulse** temporário (~1.9s, `clearTimeout`); **hoje** = anel `secondary`; legenda (Palestra / Hoje).
  - **Agenda** (`flex-1`): `@forelse ($palestrasDoMes as $p)` linha `.cema-row` com `id="linha-{{ $p->slug }}"`: **chip de data 72px** (`translatedFormat('D')` abrev + dia grande + `format('H\hi')` — **hora real**; dourado-claro p/ próxima, cinza p/ realizada), badge modalidade, tag tema (se houver), **marca Próxima/Realizada**, H3 título, avatar-iniciais + palestrante(s), **▶ gravada** (se `link_youtube` e realizada), CTA "Ver palestra" → `route('palestras.show', $p->slug)`. Linha inteira é `<a>` (teclado).
- **Estado vazio** (`@empty` / `@if (!$mesFoco)`): painel tracejado, ícone calendário, "Nenhuma palestra neste período", botão "Ver próximas palestras" (`wire:click="$set('modo','proximas')"`).

### 9.3 Modal `resources/views/components/palestras/assinar-modal.blade.php`
`@props(['feedUrl'])` — `<dialog>` nativo + Alpine (`x-data`, abre em `open-assinar`, fecha em `Esc`/clique-fora/×, focus-trap, `role="dialog" aria-modal`). 3 opções:
- **Google Calendar** → `https://calendar.google.com/calendar/r?cid={urlencode(webcal://HOST + path)}`.
- **Apple Calendar** → `webcal://{HOST}{path}` (abre o Calendar).
- **Baixar .ics** → `{feedUrl}?download=1` (attachment).
Onde `path = parse do route('palestras.calendario-ics')`; `HOST` = host atual. Texto de apoio: "Assine uma vez e cada domingo, às 19h, entra automaticamente no seu calendário."

---

## 10. SEO / A11y / Performance

- **JSON-LD** (`<x-slot:head>`, dados do controller = próximas ≤16): `@type ItemList`, `itemListElement` de `@type Event` — `name` (título), **`startDate`** = `data_da_palestra->toIso8601String()` (**hora real** + offset SP), `endDate` (+`DuracaoPalestra::minutos`), `eventAttendanceMode` (Online→`OnlineEventAttendanceMode` / Presencial→`OfflineEventAttendanceMode`), `location` (Presencial→`Place` sede / Online→`VirtualLocation` com `url` do YouTube), `performer` (`Person` por palestrante), `url` (`palestras.show`). Emitido via `@json($jsonLd, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)` a partir de var `@php` (a diretiva `@json` inline com array multi-vírgula não compila — lição da archive).
- **A11y:** tabs `role="tablist"`/`aria-selected`; `aria-label` na navegação de mês e nos dias; linhas `<a>` navegáveis por teclado, foco visível; modal `Esc`/focus-trap; contraste ≥ 4.5; `prefers-reduced-motion` desativa animações (pulse, partículas, fade, scroll suave).
- **Performance:** sem embed de vídeo; mini-calendário e agenda são HTML leve (SSR do Livewire); sem libs novas; `build` via `npm run build`.

---

## 11. CSS `resources/css/palestras-calendario.css`

`@import` no `app.css` (após `palestras-archive.css`). Contém (via `var(--color-*)`, nunca `theme()`):
- `.cema-cal-day` (dia do mini-calendário): base, `--com-palestra` (círculo dourado + sombra + hover `scale(1.12)`), `--hoje` (anel `secondary`), `--vazio`.
- `.cema-row` (linha da agenda): base + hover (`translateX(3px)` + sombra + borda gold + CTA roxo) + `.is-destaque` (pulse temporário ao rolar do mini-calendário).
- **Avatar dourado**: 4 gradientes rotacionados por índice (`#F7C24E→#E79048`, etc.).
- **Chips de data** (próxima `#FBF1DA/#8a6a1e`, realizada `#F2F1F6/#9a93b4`) e tag de tema (`#EFEBF7/#6a6390`) — literais fora do sistema (ok, conforme handoff §8).
- Círculos do banner (reaproveita o padrão da archive), `cemaFadeUp`, e `@media (prefers-reduced-motion: reduce)`.

---

## 12. Plano de testes

**Rodar a suíte COMPLETA no fecho** (`docker compose exec -T app php artisan test`, não `--filter`). **Pint** antes de cada commit. `docker compose restart app worker` após edições de Blade/PHP.

### Preservar
- **`CalendarioStubTest`**: `GET /palestra_publica/calendario` → 200 + "Calendário de Palestras" + palestra futura listada. Com a página real (modo `proximas` → mês da futura em foco), a futura aparece na agenda/destaque; ajustar apenas seletores se o markup exigir.
- **`CalendarioPalestraTest`**: `.ics` do single via `FeedIcs` — DTSTART/DTEND/SUMMARY/escape idênticos → verde sem alteração de asserção.

### Novos
- **`FeedIcsTest`** (unit): `escapar()` (`;`,`,`,`\`,CRLF→`\n`); `vevento()` gera DTSTART na **hora real** (segunda 20h SP → `T230000Z`; domingo 19h → `T220000Z`), UID estável, LOCATION por formato; `documento()` embrulha em VCALENDAR com CRLF.
- **`CalendarioFeedTest`**: `GET /palestra_publica/calendario.ics` → 200 `text/calendar`; **1 VEVENT por futura** (≤16), nenhuma passada; `?download=1` adiciona `Content-Disposition: attachment`; palestra sem data não entra.
- **`CalendarioComponentTest`** (Livewire): destaque = próxima futura (null sem futura, **sem fallback**); `modo=realizadas` muda o conjunto de meses (passado desc) e reseta `$mes`; `mesAnterior/mesProximo` respeitam limites; `$palestrasDoMes` traz só o mês em foco, com marca Próxima/Realizada/gravada; **palestra realizada mais cedo hoje** (`data_da_palestra` = hoje, algumas horas atrás) **não** é a próxima e cai em **Realizadas**, marcada `Realizada` (e `gravada` se tem `link_youtube`) — cobre a fronteira `now()` consistente (sem estado órfão); **matriz marca um dia não-domingo** (uma segunda 20h) como "com palestra".
- **`CalendarioSeoTest`**: casca tem `"@type":"ItemList"` + `"@type":"Event"` com `startDate` na hora real e `eventAttendanceMode`.
- **`CalendarioRotaTest`**: `route('palestras.calendario-ics')` resolve para `/palestra_publica/calendario.ics` e **não** é capturada por `palestras.show`; `palestras.calendario` (página) segue 200.

---

## 13. Riscos & mitigações

| Risco | Mitigação |
|---|---|
| Refactor do `.ics` do single quebrar o `CalendarioPalestraTest` | `FeedIcs::vevento` reproduz exatamente DTSTART/DTEND/SUMMARY/escape atuais; rodar o teste + suíte completa. |
| `whereYear/whereMonth`/distinct quebrar no SQLite | `whereYear/whereMonth` são portáveis; meses/anos distintos em PHP. |
| Feed servido como `attachment` impedir assinatura webcal | Feed **inline** por padrão; `attachment` só com `?download=1`. |
| Palestra sem palestrante/assunto/foto (ex.: Cine Debate) | Degradar: avatar de iniciais; sem tag de tema; nome ausente tratado. |
| Hora fixa 19h vazar no feed/SEO | `DTSTART`/`startDate` sempre de `data_da_palestra` real (2 segundas 20h cobertas por teste). |
| OPcache servindo Blade antigo no dev | `docker compose restart app worker` após edições. |
| `theme()` no Tailwind v4 | `var(--color-*)` + `npm run build`. |
| Substituir stub regredir o `CalendarioStubTest` | Preservar asserções (200 + "Calendário de Palestras" + futura) ou atualizar ao novo markup. |

---

## 14. Constraints globais (herdadas pelo plano)

- **Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Filament 5 · Tailwind v4 · MySQL 8 (dev) / SQLite `:memory:` (testes). **Sem dependências novas. Sem migração/schema.**
- Reusar componentes existentes (`x-ui.particulas`, `x-ui.countdown` `:data`, `x-palestra.badge-formato`, `x-layout.app`/`<x-slot:head>`, padrões da archive).
- Mini-calendário/agenda em **PHP (Carbon)**; **Alpine** só para scroll/hover/countdown; nada de calendário client-side.
- **`FeedIcs` compartilhado**; single refatorado preservando `CalendarioPalestraTest`.
- **`DTSTART`/`startDate` sempre da hora real**; mini-calendário destaca **qualquer** dia; rótulo "Dias com palestra".
- **Fronteira de tempo única = `now()`** (mesmo instante para `$proxima`, split de meses e marcas Realizada/gravada) → sem estado órfão para palestra realizada mais cedo no mesmo dia.
- **Enriquecimento do `.ics` do single é intencional:** o refactor para `FeedIcs` adiciona `DESCRIPTION` + `LOCATION` condicional ao evento do single; `CalendarioPalestraTest` só assere DTSTART/DTEND/SUMMARY → permanece verde (confirmar rodando o teste).
- Feed `palestras.calendario-ics` **antes** do `show`; **não** reusar o nome `palestras.calendario`.
- Tokens via utilitários/`var(--color-*)` (nunca `theme()`); build `npm run build` no host; `restart app worker` no dev.
- Testes por `docker compose exec -T app php artisan test` (**suíte completa no fecho**); **Pint** antes do commit. PROIBIDO `migrate:fresh/refresh/wipe/reset/db:wipe`/seed destrutivo.
- pt-BR com acentos; cabeçalho de autoria nos arquivos novos relevantes; commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
