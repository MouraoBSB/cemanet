# Calendário de Palestras — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir a página **Calendário de Palestras** (`/palestra_publica/calendario`) fiel ao handoff — agenda densa com mini-calendário indexador, destaque da próxima palestra, tabs Próximas/Realizadas com navegação de mês/ano — mais o feed `.ics` agregado e o modal "Assinar calendário", substituindo o stub atual.

**Architecture:** Página = casca Blade (hero + breadcrumb + JSON-LD + "Veja também" + modal) que embute um componente Livewire (`Palestras\Calendario`) responsável por todo o estado e a derivação (destaque, meses/anos do modo, matriz do mini-calendário, linhas do mês). O feed `.ics` (single **e** agregado) sai de um builder compartilhado `App\Support\Palestras\FeedIcs` (DRY). Tudo SSR (Livewire), Carbon no servidor, Alpine só para scroll/hover/countdown/modal. Sem dependências novas, sem migração.

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 4 · Filament 5 · Tailwind v4 · Vite · MySQL 8 (dev) / SQLite `:memory:` (testes) · Docker.

## Global Constraints

Toda tarefa herda implicitamente estas regras (valores copiados do spec §14):

- **Sem dependências novas. Sem migração/alteração de schema.** Dados já vêm do legado.
- **Fronteira de tempo única = `now()`** (o MESMO instante para `$proxima`, split de meses e as marcas Realizada/gravada). Nada de `startOfDay` na fronteira passado/futuro → nenhuma palestra realizada mais cedo no mesmo dia fica órfã.
- **`DTSTART`/`startDate` sempre da hora real** de `data_da_palestra` (19h domingo → `T220000Z`; 20h segunda → `T230000Z`); nunca hora fixa.
- **Mini-calendário destaca QUALQUER dia com palestra** (há 2 segundas 20h); rótulo do mini-calendário = **"Dias com palestra"**.
- **Feed `palestras.calendario-ics`** registrado **ANTES** de `palestras.show`; **não** reusar o nome `palestras.calendario` (esse é a página).
- **`FeedIcs` compartilhado**; single refatorado preservando o `CalendarioPalestraTest` (só assere DTSTART/DTEND/SUMMARY/escape → o enriquecimento DESCRIPTION/LOCATION é intencional e não regride).
- **`CalendarioStubTest` preservado** (200 + "Calendário de Palestras" + palestra futura listada).
- Reusar componentes existentes: `<x-ui.particulas>` (sem props), `<x-ui.countdown :data="$dt">` (não-compacto; só renderiza se `$dt->isFuture()`), `<x-palestra.badge-formato :palestra="$p" variante="solido|claro">`, `<x-layout.app title description>` com `<x-slot:head>`.
- Mini-calendário/agenda em **PHP (Carbon)**; **Alpine** só para scroll/hover/countdown/modal; nada de calendário client-side.
- Tokens via utilitários / `var(--color-*)` — **nunca `theme()`** no Tailwind v4. Build: `npm run build` (host). Refletir Blade/PHP no dev: `docker compose restart app worker`.
- Testes: `docker compose exec -T app php artisan test` (SQLite `:memory:`, seguro); por-task pode usar `--filter`; **suíte COMPLETA no fecho** (Task 6). **Pint** (`docker compose exec -T app ./vendor/bin/pint`) antes de cada commit.
- 🚫 **PROIBIDO** `migrate:fresh` / `migrate:refresh` / `db:wipe` / `migrate:reset` / seed destrutivo na conexão default (dev tem 127 palestras + 44 posts). Esta fatia **não** tem migração.
- pt-BR com acentos; cabeçalho de autoria `Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01` nos arquivos novos relevantes; commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

**Base da branch:** `fase-3-calendario` a partir de `main` (merge-base `d446e18`).

---

### Task 1: `FeedIcs` (builder compartilhado) + refactor do `.ics` do single

Extrai o escaper + montagem de `VEVENT`/`VCALENDAR` (hoje inline em `PalestraController@calendario`) para `App\Support\Palestras\FeedIcs`, e refatora o single para usá-lo. O `.ics` do single passa a incluir DESCRIPTION enriquecido + LOCATION condicional + cabeçalhos `X-WR-*` — melhoria intencional; o `CalendarioPalestraTest` só assere DTSTART/DTEND/SUMMARY/escape e permanece verde.

**Files:**
- Create: `app/Support/Palestras/FeedIcs.php`
- Create (test): `tests/Feature/Front/FeedIcsTest.php`
- Modify: `app/Http/Controllers/PalestraController.php:90-125` (método `calendario`)
- Preserve (test): `tests/Feature/Front/CalendarioPalestraTest.php` (sem alterar asserções)

**Interfaces:**
- Consumes: `App\Models\Palestra` (accessors `data_da_palestra` cast datetime, `online`, `link_youtube`, `titulo`, `slug`, `id`; relações `palestrantesAtivos`/`assuntos`), `App\Support\Palestras\DuracaoPalestra::minutos(?string): int`.
- Produces:
  - `FeedIcs::escapar(string $v): string`
  - `FeedIcs::vevento(App\Models\Palestra $p): array` (lista de linhas de UM VEVENT; requer `$p->data_da_palestra !== null`)
  - `FeedIcs::documento(iterable $palestras): string` (VCALENDAR completo; pula palestras sem data)
  - `FeedIcs::PRODID` (`'-//CEMA//Palestras//PT-BR'`)

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Front/FeedIcsTest.php`

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use App\Support\Palestras\FeedIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FeedIcsTest extends TestCase
{
    use RefreshDatabase;

    public function test_escapar_trata_caracteres_especiais_e_quebras(): void
    {
        $this->assertSame('a\\;b\\,c\\\\d', FeedIcs::escapar('a;b,c\\d'));
        $this->assertSame('L1\\nL2', FeedIcs::escapar("L1\r\nL2"));
    }

    public function test_vevento_usa_hora_real_domingo_19h(): void
    {
        $p = Palestra::factory()->create([
            'titulo' => 'Palestra Dominical',
            'online' => false,
            'duracao' => null,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'), // domingo
        ])->load(['palestrantesAtivos', 'assuntos']);

        $linhas = FeedIcs::vevento($p);

        $this->assertContains('BEGIN:VEVENT', $linhas);
        $this->assertContains('DTSTART:20260621T220000Z', $linhas);
        $this->assertContains('DTEND:20260621T233000Z', $linhas);
        $this->assertContains('UID:palestra-'.$p->id.'@cemanet.org.br', $linhas);
        $this->assertContains('SUMMARY:Palestra Dominical', $linhas);
        $this->assertTrue((bool) collect($linhas)->first(fn ($l) => str_starts_with($l, 'LOCATION:Centro Espírita')));
    }

    public function test_vevento_usa_hora_real_segunda_20h(): void
    {
        $p = Palestra::factory()->create([
            'online' => true,
            'duracao' => null,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 22, 20, 0, 0, 'America/Sao_Paulo'), // segunda 20h
        ])->load(['palestrantesAtivos', 'assuntos']);

        $linhas = FeedIcs::vevento($p);

        $this->assertContains('DTSTART:20260622T230000Z', $linhas); // 20h SP => 23h UTC
        $this->assertTrue((bool) collect($linhas)->first(fn ($l) => str_starts_with($l, 'LOCATION:Online')));
    }

    public function test_documento_embrulha_em_vcalendar_com_crlf_e_pula_sem_data(): void
    {
        $comData = Palestra::factory()->create([
            'titulo' => 'Com Data',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ])->load(['palestrantesAtivos', 'assuntos']);
        $semData = Palestra::factory()->create([
            'titulo' => 'Sem Data',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => null,
        ])->load(['palestrantesAtivos', 'assuntos']);

        $doc = FeedIcs::documento([$comData, $semData]);

        $this->assertStringStartsWith('BEGIN:VCALENDAR', $doc);
        $this->assertStringContainsString('PRODID:'.FeedIcs::PRODID, $doc);
        $this->assertStringContainsString("\r\n", $doc);
        $this->assertStringContainsString('SUMMARY:Com Data', $doc);
        $this->assertStringNotContainsString('Sem Data', $doc);
        $this->assertSame(1, substr_count($doc, 'BEGIN:VEVENT'));
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $doc);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=FeedIcsTest`
Expected: FAIL (`Class "App\Support\Palestras\FeedIcs" not found`).

- [ ] **Step 3: Implementar `app/Support/Palestras/FeedIcs.php`**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Support\Palestras;

use App\Models\Palestra;

final class FeedIcs
{
    public const PRODID = '-//CEMA//Palestras//PT-BR';

    private const LOCAL_PRESENCIAL = 'Centro Espírita Maria Madalena — Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF';

    /** Escapa valor para iCal: \, ; , e quebras de linha (CRLF/CR/LF → \n). */
    public static function escapar(string $v): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $v
        );
    }

    /**
     * Linhas de UM VEVENT a partir da palestra (usa a hora REAL de data_da_palestra).
     *
     * @return list<string>
     */
    public static function vevento(Palestra $p): array
    {
        $inicio = $p->data_da_palestra->copy()->utc();
        $fim = $inicio->copy()->addMinutes(DuracaoPalestra::minutos($p->duracao));
        $fmt = fn ($d) => $d->format('Ymd\THis\Z');

        $palestrantes = $p->relationLoaded('palestrantesAtivos')
            ? $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ')
            : '';
        $tema = $p->relationLoaded('assuntos') ? optional($p->assuntos->first())->nome : null;

        $partes = array_filter([
            $palestrantes !== '' ? 'com '.$palestrantes : null,
            $tema,
            $p->online ? 'Online' : 'Presencial',
        ]);
        $descricao = implode(' · ', $partes)."\n".route('palestras.show', $p->slug);
        $local = $p->online ? 'Online — YouTube' : self::LOCAL_PRESENCIAL;

        return [
            'BEGIN:VEVENT',
            'UID:palestra-'.$p->id.'@cemanet.org.br',
            'DTSTART:'.$fmt($inicio),
            'DTEND:'.$fmt($fim),
            'SUMMARY:'.self::escapar($p->titulo),
            'DESCRIPTION:'.self::escapar($descricao),
            'LOCATION:'.self::escapar($local),
            'END:VEVENT',
        ];
    }

    /** Documento VCALENDAR completo com N VEVENTs; pula palestras sem data. */
    public static function documento(iterable $palestras): string
    {
        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'X-WR-CALNAME:Palestras CEMA',
            'X-WR-TIMEZONE:America/Sao_Paulo',
        ];

        foreach ($palestras as $p) {
            if ($p->data_da_palestra === null) {
                continue;
            }
            $linhas = array_merge($linhas, self::vevento($p));
        }

        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", $linhas)."\r\n";
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=FeedIcsTest`
Expected: PASS (4 testes).

- [ ] **Step 5: Refatorar o single para usar `FeedIcs`** — `app/Http/Controllers/PalestraController.php`

Substituir o método `calendario` inteiro (linhas 90-125) por:

```php
    public function calendario(string $slug)
    {
        $palestra = Palestra::query()
            ->publicado()
            ->with(['palestrantesAtivos', 'assuntos'])
            ->where('slug', $slug)
            ->firstOrFail();
        abort_if($palestra->data_da_palestra === null, 404);

        return response(FeedIcs::documento([$palestra]), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="palestra-'.$palestra->slug.'.ics"',
        ]);
    }
```

Ajustar os `use` no topo: remover `use App\Support\Palestras\DuracaoPalestra;` (não mais usado aqui) e adicionar `use App\Support\Palestras\FeedIcs;`. Manter `use App\Models\Palestra;` e `use Illuminate\Database\Eloquent\Builder;`.

- [ ] **Step 6: Rodar o teste do single (não pode regredir) + o novo**

Run: `docker compose exec -T app php artisan test --filter="FeedIcsTest|CalendarioPalestraTest"`
Expected: PASS (todos). O `CalendarioPalestraTest` (3 testes) continua verde: DTSTART/DTEND/SUMMARY idênticos; DESCRIPTION agora enriquecido (não asserido).

- [ ] **Step 7: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Palestras/FeedIcs.php app/Http/Controllers/PalestraController.php tests/Feature/Front/FeedIcsTest.php
git add app/Support/Palestras/FeedIcs.php app/Http/Controllers/PalestraController.php tests/Feature/Front/FeedIcsTest.php
git commit -m "$(cat <<'EOF'
feat(palestras/ics): extrai FeedIcs compartilhado e refatora o .ics do single

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Rota do feed + `CalendarioController@feed`

Registra `GET /palestra_publica/calendario.ics` (nome `palestras.calendario-ics`, **antes** de `palestras.show`) e adiciona o método `feed()` que serve o `.ics` agregado das próximas ≤16 palestras via `FeedIcs`.

**Files:**
- Modify: `routes/web.php:18-20` (inserir rota do feed após a rota da página, antes do `show`)
- Modify: `app/Http/Controllers/CalendarioController.php` (adicionar `feed()`)
- Create (test): `tests/Feature/Front/CalendarioFeedTest.php`
- Create (test): `tests/Feature/Front/CalendarioRotaTest.php`

**Interfaces:**
- Consumes: `App\Support\Palestras\FeedIcs::documento()` (Task 1), `App\Models\Palestra`.
- Produces: `CalendarioController::feed(Illuminate\Http\Request $request): Illuminate\Http\Response`; rota nomeada `palestras.calendario-ics` → `/palestra_publica/calendario.ics`.

- [ ] **Step 1: Escrever os testes que falham** — `tests/Feature/Front/CalendarioFeedTest.php`

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_responde_text_calendar_com_futuras(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura A',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
        $resp->assertSee('BEGIN:VCALENDAR', false);
        $resp->assertSee('SUMMARY:Futura A', false);
        $this->assertSame(1, substr_count($resp->getContent(), 'BEGIN:VEVENT'));
    }

    public function test_feed_exclui_passadas(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura A',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);
        Palestra::factory()->create([
            'titulo' => 'Passada B',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->subDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $resp->assertSee('SUMMARY:Futura A', false);
        $resp->assertDontSee('Passada B', false);
    }

    public function test_feed_inline_por_padrao(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics'));

        $this->assertStringNotContainsString('attachment', (string) $resp->headers->get('content-disposition'));
    }

    public function test_feed_download_adiciona_attachment(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(5),
        ]);

        $resp = $this->get(route('palestras.calendario-ics', ['download' => 1]));

        $this->assertStringContainsString('attachment', $resp->headers->get('content-disposition'));
        $this->assertStringContainsString('cema-palestras.ics', $resp->headers->get('content-disposition'));
    }
}
```

`tests/Feature/Front/CalendarioRotaTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarioRotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rota_feed_resolve_para_a_url_esperada(): void
    {
        $this->assertSame(url('/palestra_publica/calendario.ics'), route('palestras.calendario-ics'));
    }

    public function test_feed_nao_e_capturado_pelo_show(): void
    {
        // Não existe palestra com slug 'calendario.ics'; o ponto do ".ics" deve cair no feed, não no {slug}.
        $resp = $this->get('/palestra_publica/calendario.ics');

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
    }

    public function test_pagina_calendario_segue_respondendo_200(): void
    {
        Palestra::factory()->create([
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => now()->addDays(3),
        ]);

        $this->get('/palestra_publica/calendario')->assertOk();
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="CalendarioFeedTest|CalendarioRotaTest"`
Expected: FAIL (rota `palestras.calendario-ics` não definida).

- [ ] **Step 3: Registrar a rota** — `routes/web.php`

Inserir a linha da rota do feed logo após a rota da página `palestras.calendario` (linha 18) e antes de `palestras.show`:

```php
Route::get('/palestra_publica/calendario', [CalendarioController::class, 'index'])->name('palestras.calendario');

// Feed .ics agregado das próximas palestras. DEVE vir ANTES de palestras.show.
Route::get('/palestra_publica/calendario.ics', [CalendarioController::class, 'feed'])->name('palestras.calendario-ics');

Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])
    ->name('palestras.show')
    ->where('slug', '[a-z0-9-]+');
```

- [ ] **Step 4: Implementar `feed()`** — `app/Http/Controllers/CalendarioController.php`

Adicionar os `use` no topo: `use App\Support\Palestras\FeedIcs;`, `use Illuminate\Http\Request;`, `use Illuminate\Http\Response;`. Adicionar o método após `index()`:

```php
    public function feed(Request $request): Response
    {
        $palestras = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(16)
            ->get();

        $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="cema-palestras.ics"';
        }

        return response(FeedIcs::documento($palestras), 200, $headers);
    }
```

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter="CalendarioFeedTest|CalendarioRotaTest"`
Expected: PASS (7 testes).

- [ ] **Step 6: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint routes/web.php app/Http/Controllers/CalendarioController.php tests/Feature/Front/CalendarioFeedTest.php tests/Feature/Front/CalendarioRotaTest.php
git add routes/web.php app/Http/Controllers/CalendarioController.php tests/Feature/Front/CalendarioFeedTest.php tests/Feature/Front/CalendarioRotaTest.php
git commit -m "$(cat <<'EOF'
feat(palestras/calendario): rota e endpoint do feed .ics agregado

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Modal "Assinar calendário" (`<x-palestras.assinar-modal>`)

Componente Blade `<dialog>` nativo + Alpine, independente. Abre ao receber o evento `open-assinar` (disparado pelo botão do hero). Oferece Google Calendar, Apple Calendar (webcal) e "Baixar .ics".

**Files:**
- Create: `resources/views/components/palestras/assinar-modal.blade.php`
- Create (test): `tests/Feature/Front/AssinarModalTest.php`

**Interfaces:**
- Consumes: prop `feedUrl` (URL absoluta do feed, ex.: `route('palestras.calendario-ics')`).
- Produces: componente Blade `x-palestras.assinar-modal :feed-url="…"`; escuta `window.open-assinar` (Alpine).

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Front/AssinarModalTest.php`

```php
<?php

namespace Tests\Feature\Front;

use Tests\TestCase;

class AssinarModalTest extends TestCase
{
    public function test_modal_monta_links_google_apple_e_download(): void
    {
        $feed = 'http://localhost/palestra_publica/calendario.ics';

        $view = $this->blade('<x-palestras.assinar-modal :feed-url="$feedUrl" />', ['feedUrl' => $feed]);

        // webcal (Apple) — mesmo host/path do feed
        $view->assertSee('webcal://localhost/palestra_publica/calendario.ics', false);
        // Google Calendar por URL
        $view->assertSee('calendar.google.com/calendar/r', false);
        // Baixar .ics (attachment)
        $view->assertSee('palestra_publica/calendario.ics?download=1', false);
        // acessibilidade do dialog
        $view->assertSee('role="dialog"', false);
        $view->assertSee('aria-modal="true"', false);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AssinarModalTest`
Expected: FAIL (componente `palestras.assinar-modal` inexistente).

- [ ] **Step 3: Implementar o modal** — `resources/views/components/palestras/assinar-modal.blade.php`

```blade
{{-- Modal "Assinar calendário": <dialog> nativo + Alpine. Abre em `open-assinar`. --}}
@props(['feedUrl'])

@php
    $parts = parse_url($feedUrl);
    $host = $parts['host'] ?? request()->getHost();
    $path = $parts['path'] ?? '';
    $webcal = 'webcal://'.$host.$path;
    $google = 'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcal);
    $download = $feedUrl.'?download=1';
@endphp

<div
    x-data="{ aberto: false, abre() { this.aberto = true; $nextTick(() => $refs.dlg?.showModal()); }, fecha() { this.aberto = false; $refs.dlg?.close(); } }"
    x-on:open-assinar.window="abre()"
>
    <dialog
        x-ref="dlg"
        x-on:close="aberto = false"
        x-on:click.self="fecha()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assinar-titulo"
        class="cema-modal m-auto w-[min(92vw,460px)] rounded-2xl border border-border-muted bg-white p-0 text-text-ink backdrop:bg-black/50"
    >
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="assinar-titulo" class="font-display text-xl font-semibold text-primary">Assinar calendário</h2>
                    <p class="mt-1 text-sm text-text-secondary">Assine uma vez e cada domingo, às 19h, entra automaticamente no seu calendário.</p>
                </div>
                <button type="button" x-on:click="fecha()" aria-label="Fechar" class="shrink-0 rounded-full p-1.5 text-text-muted transition hover:bg-surface hover:text-text-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="mt-5 flex flex-col gap-2.5">
                <a href="{{ $google }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">📅</span>
                    Google Calendar
                </a>
                <a href="{{ $webcal }}"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">🍎</span>
                    Apple Calendar
                </a>
                <a href="{{ $download }}"
                   class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary">
                    <span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">⬇️</span>
                    Baixar .ics
                </a>
            </div>

            <p class="mt-4 text-xs text-text-muted">No Google, "assinar por URL" só sincroniza em produção (o Google não alcança o localhost).</p>
        </div>
    </dialog>
</div>
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AssinarModalTest`
Expected: PASS (1 teste).

- [ ] **Step 5: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint tests/Feature/Front/AssinarModalTest.php
git add resources/views/components/palestras/assinar-modal.blade.php tests/Feature/Front/AssinarModalTest.php
git commit -m "$(cat <<'EOF'
feat(palestras/calendario): modal Assinar (Google/Apple/baixar .ics)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: Componente Livewire `Palestras\Calendario` (lógica + view)

Núcleo funcional da página: estado `#[Url] modo/mes`, navegação de mês/ano dentro do conjunto do modo, derivação do destaque, das linhas do mês (com marcas Próxima/Realizada/gravada) e da matriz do mini-calendário. Usa **`now()` como fronteira única** (sem estado órfão). A view consome exatamente o que `render()` passa.

**Files:**
- Create: `app/Livewire/Palestras/Calendario.php`
- Create: `resources/views/livewire/palestras/calendario.blade.php`
- Create (test): `tests/Feature/Front/CalendarioComponentTest.php`

**Interfaces:**
- Consumes: `App\Models\Palestra` (scope `publicado()`, `data_da_palestra` datetime, `link_youtube`, `online`, relações `palestrantesAtivos`/`assuntos`, accessor `formato`), componentes `x-ui.countdown`, `x-palestra.badge-formato`.
- Produces: componente Livewire `App\Livewire\Palestras\Calendario` (tag `<livewire:palestras.calendario />`). `render()` passa à view: `proxima` (`?Palestra`), `modo` (string), `mesFoco` (`?string 'Y-m'`), `anos` (`list<string>`), `palestrasDoMes` (`Collection<Palestra>` com atributos dinâmicos `eh_proxima`/`eh_realizada`/`tem_gravacao`), `matriz` (`array{diasVazios:int, dias:list<array{dia:int,palestra:?array{slug,titulo},hoje:bool}>}`), `agora` (`Carbon`), `temAnterior`/`temProximo` (bool). Métodos públicos: `mount()`, `updatedModo()`, `mesAnterior()`, `mesProximo()`, `irParaAno($ano)`.

- [ ] **Step 1: Escrever os testes que falham** — `tests/Feature/Front/CalendarioComponentTest.php`

```php
<?php

namespace Tests\Feature\Front;

use App\Livewire\Palestras\Calendario;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarioComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 12, 0, 0)); // fronteira fixa (tz do app)
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // NOTA (Livewire v4.3.2): o Testable NÃO tem `assertViewHas`, mas TEM `viewData($key)`
    // (retorna getView()->getData()[$key]) — mesma API já usada na archive mergeada. Aferimos os
    // dados de render DIRETAMENTE pelo dado (proxima, mesFoco, palestrasDoMes com eh_*, matriz,
    // temAnterior/temProximo) e usamos `assertSet` para estado público (modo/mes) onde couber.

    public function test_destaque_usa_proxima_futura_sem_fallback(): void
    {
        Palestra::factory()->create(['titulo' => 'Passada', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 15, 19, 0)]);
        Palestra::factory()->create(['titulo' => 'Futura', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0)]);

        $proxima = Livewire::test(Calendario::class)->viewData('proxima');

        $this->assertNotNull($proxima);
        $this->assertSame('Futura', $proxima->titulo);
    }

    public function test_sem_futura_destaque_e_nulo(): void
    {
        Palestra::factory()->create(['titulo' => 'Só Passada', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 15, 19, 0)]);

        $this->assertNull(Livewire::test(Calendario::class)->viewData('proxima')); // sem fallback
    }

    public function test_modo_realizadas_alterna_conjunto_e_reseta_mes(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 8, 2, 19, 0)]);  // futura
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 5, 10, 19, 0)]); // passada

        $c = Livewire::test(Calendario::class);
        $this->assertSame('2026-08', $c->viewData('mesFoco'));   // proximas: mês da futura

        $c->set('modo', 'realizadas');                            // dispara updatedModo → reseta $mes
        $c->assertSet('modo', 'realizadas');
        $this->assertSame('2026-05', $c->viewData('mesFoco'));   // realizadas: mês mais recente do passado
    }

    public function test_navegacao_de_mes_respeita_limites(): void
    {
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0)]);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 8, 9, 19, 0)]);
        Palestra::factory()->create(['status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 9, 6, 19, 0)]);

        $c = Livewire::test(Calendario::class);
        $this->assertSame('2026-07', $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temAnterior'));
        $this->assertTrue($c->viewData('temProximo'));

        $c->call('mesProximo');
        $this->assertSame('2026-08', $c->viewData('mesFoco'));

        $c->call('mesProximo');
        $this->assertSame('2026-09', $c->viewData('mesFoco'));
        $this->assertFalse($c->viewData('temProximo'));

        $c->call('mesProximo'); // topo: não avança além do limite
        $this->assertSame('2026-09', $c->viewData('mesFoco'));

        $c->call('mesAnterior');
        $this->assertSame('2026-08', $c->viewData('mesFoco'));
    }

    public function test_palestra_realizada_mais_cedo_hoje_e_marcada_sem_orfa(): void
    {
        // Fronteira now() consistente: palestra de hoje 09h (já passou às 12h) → Realizada+gravada, não órfã.
        // Sob a fronteira antiga (startOfDay) eh_realizada seria FALSE → sem marca (órfã). Aferimos PELO DADO.
        Palestra::factory()->create([
            'titulo' => 'Hoje Cedo',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtube.com/live/abc1234',
            'data_da_palestra' => Carbon::create(2026, 7, 1, 9, 0),
        ]);
        Palestra::factory()->create([
            'titulo' => 'Ainda Vem',
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::create(2026, 7, 5, 19, 0),
        ]);

        $c = Livewire::test(Calendario::class);

        // não é a próxima
        $this->assertSame('Ainda Vem', $c->viewData('proxima')->titulo);

        // em Próximas, o mês em foco (julho) lista AMBAS; marcas aferidas pelo dado
        $col = $c->viewData('palestrasDoMes');
        $hoje = $col->firstWhere('titulo', 'Hoje Cedo');
        $futura = $col->firstWhere('titulo', 'Ainda Vem');

        $this->assertNotNull($hoje);
        $this->assertTrue((bool) $hoje->eh_realizada);   // realizada mais cedo hoje (fronteira now())
        $this->assertTrue((bool) $hoje->tem_gravacao);   // realizada + youtube
        $this->assertFalse((bool) $hoje->eh_proxima);    // não é a próxima (não órfã)
        $this->assertTrue((bool) $futura->eh_proxima);
        $this->assertFalse((bool) $futura->eh_realizada);
    }

    public function test_mini_calendario_marca_dia_nao_domingo(): void
    {
        // 2026-06-22 é uma SEGUNDA-feira (20h) → dia com palestra no mini-calendário (não assume domingo).
        Palestra::factory()->create(['titulo' => 'Segunda 20h', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => Carbon::create(2026, 6, 22, 20, 0)]);

        $c = Livewire::test(Calendario::class)->set('modo', 'realizadas'); // 22/jun é passado (now = 01/jul)
        $this->assertSame('2026-06', $c->viewData('mesFoco'));

        $matriz = $c->viewData('matriz');
        $dia22 = collect($matriz['dias'])->firstWhere('dia', 22); // 22/jun/2026 = segunda-feira
        $this->assertNotNull($dia22);
        $this->assertNotNull($dia22['palestra']);
        $this->assertSame('Segunda 20h', $dia22['palestra']['titulo']);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CalendarioComponentTest`
Expected: FAIL (`Class "App\Livewire\Palestras\Calendario" not found`).

- [ ] **Step 3: Implementar o componente** — `app/Livewire/Palestras/Calendario.php`

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Livewire\Palestras;

use App\Models\Palestra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

class Calendario extends Component
{
    #[Url(as: 'modo', except: 'proximas')]
    public string $modo = 'proximas';

    #[Url(as: 'mes')]
    public ?string $mes = null;

    public function mount(): void
    {
        $this->normalizaModo();
        $meses = $this->mesesModoAsc();
        if ($this->mes === null || ! in_array($this->mes, $meses, true)) {
            $this->mes = $this->mesPadrao($meses);
        }
    }

    public function updatedModo(): void
    {
        $this->normalizaModo();
        $this->mes = $this->mesPadrao($this->mesesModoAsc());
    }

    public function mesAnterior(): void
    {
        $meses = $this->mesesModoAsc();
        $i = array_search($this->mes, $meses, true);
        if ($i !== false && $i > 0) {
            $this->mes = $meses[$i - 1];
        }
    }

    public function mesProximo(): void
    {
        $meses = $this->mesesModoAsc();
        $i = array_search($this->mes, $meses, true);
        if ($i !== false && $i < count($meses) - 1) {
            $this->mes = $meses[$i + 1];
        }
    }

    public function irParaAno($ano): void
    {
        foreach ($this->mesesModoAsc() as $m) {
            if (str_starts_with($m, (string) $ano.'-')) {
                $this->mes = $m;

                return;
            }
        }
    }

    public function render(): View
    {
        $agora = now();

        $proxima = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', $agora)
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->first();

        $mesesAsc = $this->mesesModoAsc();
        $mesesExib = $this->modo === 'realizadas' ? array_reverse($mesesAsc) : $mesesAsc;
        $anos = collect($mesesExib)->map(fn ($m) => substr($m, 0, 4))->unique()->values()->all();

        $mesFoco = in_array($this->mes, $mesesAsc, true) ? $this->mes : $this->mesPadrao($mesesAsc);

        $palestrasDoMes = new Collection;
        $matriz = ['diasVazios' => 0, 'dias' => []];
        $temAnterior = false;
        $temProximo = false;

        if ($mesFoco !== null) {
            [$ano, $mesNum] = array_map('intval', explode('-', $mesFoco));

            $palestrasDoMes = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
                ->whereYear('data_da_palestra', $ano)
                ->whereMonth('data_da_palestra', $mesNum)
                ->with(['palestrantesAtivos', 'assuntos'])
                ->orderBy('data_da_palestra')
                ->get()
                ->each(function (Palestra $p) use ($agora, $proxima) {
                    $p->eh_proxima = $proxima !== null && $p->id === $proxima->id;
                    $p->eh_realizada = $p->data_da_palestra->lt($agora);
                    $p->tem_gravacao = $p->eh_realizada && ! empty($p->link_youtube);
                });

            $i = array_search($mesFoco, $mesesAsc, true);
            $temAnterior = $i !== false && $i > 0;
            $temProximo = $i !== false && $i < count($mesesAsc) - 1;

            $matriz = $this->matriz($ano, $mesNum, $palestrasDoMes, $agora);
        }

        return view('livewire.palestras.calendario', [
            'proxima' => $proxima,
            'modo' => $this->modo,
            'mesFoco' => $mesFoco,
            'anos' => $anos,
            'palestrasDoMes' => $palestrasDoMes,
            'matriz' => $matriz,
            'agora' => $agora,
            'temAnterior' => $temAnterior,
            'temProximo' => $temProximo,
        ]);
    }

    private function normalizaModo(): void
    {
        if (! in_array($this->modo, ['proximas', 'realizadas'], true)) {
            $this->modo = 'proximas';
        }
    }

    /** Meses ('Y-m') com palestra no modo atual, em ordem CRONOLÓGICA ASCENDENTE. */
    private function mesesModoAsc(): array
    {
        $agora = now();
        $q = Palestra::query()->publicado()->whereNotNull('data_da_palestra');
        $q = $this->modo === 'realizadas'
            ? $q->where('data_da_palestra', '<', $agora)
            : $q->where('data_da_palestra', '>=', $agora);

        return $q->orderBy('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    /** Mês default do modo: proximas → 1º (mais próximo); realizadas → último (mais recente). */
    private function mesPadrao(array $mesesAsc): ?string
    {
        if ($mesesAsc === []) {
            return null;
        }

        return $this->modo === 'realizadas' ? end($mesesAsc) : $mesesAsc[0];
    }

    /**
     * @return array{diasVazios:int, dias:list<array{dia:int, palestra:?array{slug:string,titulo:string}, hoje:bool}>}
     */
    private function matriz(int $ano, int $mes, Collection $palestrasDoMes, Carbon $agora): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $offset = $primeiro->dayOfWeek; // 0=domingo … 6=sábado (semana começa no domingo)

        $porDia = [];
        foreach ($palestrasDoMes as $p) {
            $d = (int) $p->data_da_palestra->day;
            if (! isset($porDia[$d])) {
                $porDia[$d] = ['slug' => $p->slug, 'titulo' => $p->titulo];
            }
        }

        $ehMesCorrente = (int) $agora->year === $ano && (int) $agora->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dias[] = [
                'dia' => $d,
                'palestra' => $porDia[$d] ?? null,
                'hoje' => $ehMesCorrente && (int) $agora->day === $d,
            ];
        }

        return ['diasVazios' => $offset, 'dias' => $dias];
    }
}
```

- [ ] **Step 4: Implementar a view** — `resources/views/livewire/palestras/calendario.blade.php`

```blade
<div>
    {{-- Destaque: próxima palestra (sem fallback) --}}
    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        @php($ptema = $proxima->assuntos->first())
        <section class="mb-10" aria-label="Próxima palestra">
            <p class="mb-3 inline-flex items-center gap-2 font-display text-base font-semibold text-primary">
                <span class="inline-block size-2.5 animate-pulse rounded-full bg-gold" aria-hidden="true"></span> Próxima palestra
            </p>
            <div class="relative overflow-hidden rounded-[18px] bg-gradient-to-r from-[#3a3266] via-primary to-[#5b4f92] p-6 text-white sm:p-8">
                <span aria-hidden="true" class="pointer-events-none absolute -top-[40px] -right-[30px] size-[180px] rounded-full bg-gold/[0.14]"></span>
                <span aria-hidden="true" class="pointer-events-none absolute -bottom-[60px] right-[120px] size-[150px] rounded-full bg-secondary/[0.16]"></span>
                <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:gap-7">
                    <span class="cema-cal-avatar cema-cal-avatar-{{ $proxima->id % 4 }} flex size-[88px] shrink-0 items-center justify-center overflow-hidden rounded-full ring-4 ring-white/20">
                        @if ($pp?->foto_thumb_url)
                            <img src="{{ $pp->foto_thumb_url }}" alt="{{ $pp->nome }}" width="88" height="88" class="size-full object-cover">
                        @else
                            <span class="font-display text-2xl font-semibold text-[#3a2f00]">{{ $pp ? collect(explode(' ', $pp->nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('') : 'CEMA' }}</span>
                        @endif
                    </span>
                    <div class="flex-1 text-center sm:text-left">
                        <div class="flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                            <span class="inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-xs font-semibold text-[#3a2f00]">
                                {{ $proxima->data_da_palestra->translatedFormat('d \d\e M') }} · {{ $proxima->data_da_palestra->format('H\hi') }}
                            </span>
                            <x-palestra.badge-formato :palestra="$proxima" variante="solido" />
                        </div>
                        <h3 class="mt-3 font-display text-2xl font-semibold">{{ $proxima->titulo }}</h3>
                        @if ($pp || $ptema)
                            <p class="mt-1 text-white/80">@if ($pp)com <strong class="font-semibold">{{ $proxima->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</strong>@endif@if ($pp && $ptema) · @endif@if ($ptema){{ $ptema->nome }}@endif</p>
                        @endif
                        <div class="mt-4 flex justify-center sm:justify-start">
                            <x-ui.countdown :data="$proxima->data_da_palestra" />
                        </div>
                    </div>
                    <a href="{{ route('palestras.show', $proxima->slug) }}"
                       class="shrink-0 rounded-pill bg-white px-6 py-3 font-semibold text-primary transition hover:bg-cream">Ver palestra</a>
                </div>
            </div>
        </section>
    @endif

    {{-- Barra de período: tabs + navegação de mês + seletor de ano --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-border-muted bg-white p-4 shadow-card sm:flex-row sm:items-center sm:justify-between">
        <div role="tablist" aria-label="Filtrar por período" class="inline-flex rounded-pill bg-surface p-1">
            <button type="button" role="tab" aria-selected="{{ $modo === 'proximas' ? 'true' : 'false' }}"
                    wire:click="$set('modo', 'proximas')"
                    @class(['rounded-pill px-4 py-1.5 text-sm font-semibold transition', 'bg-primary text-white' => $modo === 'proximas', 'text-text-secondary hover:text-primary' => $modo !== 'proximas'])>
                Próximas
            </button>
            <button type="button" role="tab" aria-selected="{{ $modo === 'realizadas' ? 'true' : 'false' }}"
                    wire:click="$set('modo', 'realizadas')"
                    @class(['rounded-pill px-4 py-1.5 text-sm font-semibold transition', 'bg-primary text-white' => $modo === 'realizadas', 'text-text-secondary hover:text-primary' => $modo !== 'realizadas'])>
                Realizadas
            </button>
        </div>

        @if ($mesFoco)
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <button type="button" wire:click="mesAnterior" @disabled(! $temAnterior) aria-label="Mês anterior"
                            class="grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40">‹</button>
                    <span class="min-w-[9.5rem] text-center font-display font-semibold text-text-ink">
                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::createFromFormat('!Y-m', $mesFoco)->translatedFormat('F \d\e Y')) }}
                    </span>
                    <button type="button" wire:click="mesProximo" @disabled(! $temProximo) aria-label="Próximo mês"
                            class="grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-40">›</button>
                </div>
                @if (count($anos) > 1)
                    <select wire:change="irParaAno($event.target.value)" aria-label="Ir para o ano"
                            class="rounded-pill border border-border-muted bg-surface px-3 py-1.5 text-sm text-text-secondary">
                        @foreach ($anos as $ano)
                            <option value="{{ $ano }}" @selected(str_starts_with($mesFoco, $ano.'-'))>{{ $ano }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endif
    </div>

    {{-- Bloco do mês: mini-calendário + agenda --}}
    @if ($mesFoco)
        <div class="mt-6 flex flex-wrap gap-6">
            {{-- Mini-calendário --}}
            <aside class="w-full shrink-0 sm:sticky sm:top-[88px] sm:w-[300px] sm:self-start">
                <div class="rounded-2xl border border-border-muted bg-white p-4 shadow-card">
                    <p class="mb-3 font-mono text-[11px] uppercase tracking-[0.14em] text-text-muted">Dias com palestra</p>
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $inicial)
                            <span wire:key="dow-{{ $loop->index }}" class="py-1 font-mono text-[11px] font-semibold text-text-muted" aria-hidden="true">{{ $inicial }}</span>
                        @endforeach
                        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
                            <span wire:key="vazio-{{ $v }}" aria-hidden="true"></span>
                        @endfor
                        @foreach ($matriz['dias'] as $celula)
                            @if ($celula['palestra'])
                                <button type="button"
                                        wire:key="dia-{{ $celula['dia'] }}"
                                        class="cema-cal-day cema-cal-day--com-palestra @if ($celula['hoje']) cema-cal-day--hoje @endif"
                                        title="{{ $celula['palestra']['titulo'] }}"
                                        aria-label="{{ $celula['dia'] }}: {{ $celula['palestra']['titulo'] }}"
                                        x-data
                                        x-on:click="
                                            const alvo = document.getElementById('linha-{{ $celula['palestra']['slug'] }}');
                                            if (alvo) {
                                                alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                                alvo.classList.add('is-destaque');
                                                setTimeout(() => alvo.classList.remove('is-destaque'), 1900);
                                            }
                                        ">{{ $celula['dia'] }}</button>
                            @else
                                <span wire:key="dia-{{ $celula['dia'] }}" class="cema-cal-day @if ($celula['hoje']) cema-cal-day--hoje @endif">{{ $celula['dia'] }}</span>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center gap-4 text-[11px] text-text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full bg-gold"></span> Palestra</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full ring-2 ring-secondary"></span> Hoje</span>
                    </div>
                </div>
            </aside>

            {{-- Agenda --}}
            <div class="min-w-0 flex-1">
                <div class="mb-4 flex items-center gap-3">
                    <h2 class="font-display text-lg font-semibold text-primary">
                        {{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Carbon::createFromFormat('!Y-m', $mesFoco)->translatedFormat('F \d\e Y')) }}
                    </h2>
                    <span class="rounded-pill bg-surface px-2.5 py-0.5 text-xs font-semibold text-primary">{{ $palestrasDoMes->count() }} {{ \Illuminate\Support\Str::plural('palestra', $palestrasDoMes->count()) }}</span>
                </div>

                <div class="flex flex-col gap-3">
                    @forelse ($palestrasDoMes as $p)
                        @php($pa = $p->palestrantesAtivos->first())
                        @php($ptag = $p->assuntos->first())
                        <a wire:key="linha-{{ $p->id }}" id="linha-{{ $p->slug }}" href="{{ route('palestras.show', $p->slug) }}"
                           class="cema-row group flex items-stretch gap-4 rounded-2xl border border-border-muted bg-white p-3 shadow-card sm:p-4">
                            <span @class(['flex w-[72px] shrink-0 flex-col items-center justify-center rounded-xl py-2 text-center', 'cema-chip-data--proxima' => $p->eh_proxima, 'cema-chip-data--realizada' => ! $p->eh_proxima])>
                                <span class="font-mono text-[10px] uppercase">{{ $p->data_da_palestra->translatedFormat('D') }}</span>
                                <span class="font-display text-2xl font-bold leading-none">{{ $p->data_da_palestra->format('d') }}</span>
                                <span class="font-mono text-[10px]">{{ $p->data_da_palestra->format('H\hi') }}</span>
                            </span>
                            <div class="flex min-w-0 flex-1 flex-col justify-center">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-palestra.badge-formato :palestra="$p" variante="claro" />
                                    @if ($ptag)
                                        <span class="rounded-pill bg-[#EFEBF7] px-2.5 py-0.5 text-[11px] font-semibold text-[#6a6390]">{{ $ptag->nome }}</span>
                                    @endif
                                    @if ($p->eh_proxima)
                                        <span class="rounded-pill bg-gold/[0.16] px-2.5 py-0.5 text-[11px] font-semibold text-[#8a6a1e]">Próxima</span>
                                    @elseif ($p->eh_realizada)
                                        <span class="rounded-pill bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-text-muted">Realizada</span>
                                    @endif
                                    @if ($p->tem_gravacao)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-danger" aria-hidden="true">▶ gravada</span>
                                    @endif
                                </div>
                                <h3 class="mt-1 truncate font-display font-semibold text-text-ink group-hover:text-primary">{{ $p->titulo }}</h3>
                                @if ($pa)
                                    <p class="mt-0.5 truncate text-sm text-text-secondary">com {{ $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ') }}</p>
                                @endif
                            </div>
                            <span class="cema-row-cta hidden shrink-0 items-center self-center rounded-pill border border-border-muted px-4 py-2 text-sm font-semibold text-primary transition sm:inline-flex">Ver palestra</span>
                        </a>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-12 text-center">
                            <p class="text-lg font-semibold text-text-secondary">Nenhuma palestra neste período</p>
                            <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas palestras</button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        {{-- Estado vazio total (nenhum mês no modo) --}}
        <div class="mt-6 rounded-2xl border border-dashed border-border-muted bg-surface px-6 py-16 text-center">
            <p class="text-4xl" aria-hidden="true">🗓️</p>
            <p class="mt-2 text-lg font-semibold text-text-secondary">Nenhuma palestra {{ $modo === 'realizadas' ? 'realizada' : 'agendada' }} no momento</p>
            @if ($modo === 'realizadas')
                <button type="button" wire:click="$set('modo', 'proximas')" class="mt-3 rounded-pill bg-primary px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90">Ver próximas palestras</button>
            @endif
        </div>
    @endif
</div>
```

- [ ] **Step 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=CalendarioComponentTest`
Expected: PASS (6 testes).

- [ ] **Step 6: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Livewire/Palestras/Calendario.php tests/Feature/Front/CalendarioComponentTest.php
git add app/Livewire/Palestras/Calendario.php resources/views/livewire/palestras/calendario.blade.php tests/Feature/Front/CalendarioComponentTest.php
git commit -m "$(cat <<'EOF'
feat(palestras/calendario): componente Livewire (agenda, mini-calendario, fronteira now())

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Casca da página + `CalendarioController@index` + JSON-LD + aposentar o stub

Substitui o stub pela casca real: hero + breadcrumb + `<livewire:palestras.calendario />` + "Veja também" + modal, com JSON-LD `ItemList`/`Event` a partir das próximas ≤16. Preserva o `CalendarioStubTest` e adiciona o `CalendarioSeoTest`.

**Files:**
- Create: `resources/views/palestras/calendario.blade.php`
- Modify: `app/Http/Controllers/CalendarioController.php` (`index()` → nova casca + `$proximasParaSeo`)
- Delete: `resources/views/pages/calendario.blade.php` (stub aposentado)
- Preserve (test): `tests/Feature/Front/CalendarioStubTest.php` (sem alterar asserções)
- Create (test): `tests/Feature/Front/CalendarioSeoTest.php`

**Interfaces:**
- Consumes: `<livewire:palestras.calendario />` (Task 4), `<x-palestras.assinar-modal :feed-url="…" />` (Task 3), `route('palestras.calendario-ics')` (Task 2), `App\Support\Palestras\DuracaoPalestra`.
- Produces: view `palestras.calendario` (recebe `$proximasParaSeo`: `Collection<Palestra>`); `CalendarioController::index(): View` passa `compact('proximasParaSeo')`.

- [ ] **Step 1: Escrever o teste que falha** — `tests/Feature/Front/CalendarioSeoTest.php`

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_emite_jsonld_itemlist_de_event(): void
    {
        Palestra::factory()->create([
            'titulo' => 'Futura SEO',
            'online' => false,
            'status' => Palestra::STATUS_PUBLICADO,
            'data_da_palestra' => Carbon::now()->addDays(4)->setTime(19, 0),
        ]);

        $resp = $this->get('/palestra_publica/calendario');

        $resp->assertOk();
        $resp->assertSee('"@type":"ItemList"', false);
        $resp->assertSee('"@type":"Event"', false);
        $resp->assertSee('"eventAttendanceMode"', false);
        $resp->assertSee('"startDate"', false);
        $resp->assertSee('Futura SEO', false);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=CalendarioSeoTest`
Expected: FAIL (view `palestras.calendario` inexistente / JSON-LD ausente — o stub atual não emite ItemList).

- [ ] **Step 3: Implementar `index()`** — `app/Http/Controllers/CalendarioController.php`

Substituir o corpo de `index()` (e o docblock do stub) por:

```php
    public function index(): View
    {
        $proximasParaSeo = Palestra::query()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->take(16)
            ->get();

        return view('palestras.calendario', compact('proximasParaSeo'));
    }
```

Manter `use Illuminate\Contracts\View\View;` (já presente). O `feed()` da Task 2 permanece.

- [ ] **Step 4: Implementar a casca** — `resources/views/palestras/calendario.blade.php`

```blade
<x-layout.app title="Calendário de Palestras" description="Todo domingo, às 19h. Assine e receba cada palestra pública do CEMA no seu calendário.">
    @php
        $eventos = $proximasParaSeo->map(function ($p) {
            $inicio = $p->data_da_palestra;
            $fim = $inicio->copy()->addMinutes(\App\Support\Palestras\DuracaoPalestra::minutos($p->duracao));
            $ev = [
                '@type' => 'Event',
                'name' => $p->titulo,
                'startDate' => $inicio->toIso8601String(),
                'endDate' => $fim->toIso8601String(),
                'eventAttendanceMode' => $p->online
                    ? 'https://schema.org/OnlineEventAttendanceMode'
                    : 'https://schema.org/OfflineEventAttendanceMode',
                'location' => $p->online
                    ? ['@type' => 'VirtualLocation', 'url' => $p->link_youtube]
                    : ['@type' => 'Place', 'name' => 'Centro Espírita Maria Madalena', 'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF'],
                'url' => route('palestras.show', $p->slug),
            ];
            if ($p->palestrantesAtivos->isNotEmpty()) {
                $ev['performer'] = $p->palestrantesAtivos->map(fn ($x) => ['@type' => 'Person', 'name' => $x->nome])->all();
            }

            return $ev;
        })->all();

        $calendarioJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Próximas palestras públicas do CEMA',
            'itemListElement' => collect($eventos)->map(fn ($ev, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => $ev,
            ])->all(),
        ];
    @endphp
    <x-slot:head>
        @if (! empty($eventos))
            <script type="application/ld+json">
                @json($calendarioJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            </script>
        @endif
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto flex max-w-[1240px] flex-col gap-8 px-6 py-16 desktop-sm:flex-row desktop-sm:items-center desktop-sm:justify-between">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Agenda</p>
                <h1 class="mt-3 font-display text-4xl font-semibold sm:text-5xl">Calendário de Palestras</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Todo domingo, às 19h, presencialmente e ao vivo pelo nosso canal. Assine e receba cada palestra no seu calendário.</p>
            </div>
            <button type="button" x-data x-on:click="$dispatch('open-assinar')"
                    class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 px-5 py-4 transition hover:bg-white/15">
                <span class="text-2xl text-gold" aria-hidden="true">🔔</span>
                <span class="font-display font-semibold">Assinar calendário</span>
            </button>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('palestras.index') }}" class="hover:text-primary">Palestras</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Calendário</span>
        </div>
    </nav>

    {{-- Calendário (Livewire) --}}
    <section class="bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <livewire:palestras.calendario />
        </div>
    </section>

    {{-- Veja também --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <div class="border-t border-border-muted pt-8">
            <h2 class="font-display text-lg font-semibold text-primary">Veja também</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                @foreach ([['Palestras Públicas', route('palestras.index')], ['Palestrantes', route('palestrantes.index')], ['Blog Sementeira de Luz', route('blog.index')]] as [$rotulo, $url])
                    <a href="{{ $url }}" class="inline-flex items-center gap-2 rounded-pill border border-border-muted bg-white px-5 py-2.5 text-sm text-[#3a3553] transition hover:border-primary">
                        <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $rotulo }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <x-palestras.assinar-modal :feed-url="route('palestras.calendario-ics')" />
</x-layout.app>
```

- [ ] **Step 5: Aposentar o stub**

```bash
git rm resources/views/pages/calendario.blade.php
```

- [ ] **Step 6: Rodar os testes que validam a casca (novo + preservados)**

Run: `docker compose exec -T app php artisan test --filter="CalendarioSeoTest|CalendarioStubTest"`
Expected: PASS. `CalendarioSeoTest` (1) verde; `CalendarioStubTest` (4) verde — a página real mostra "Calendário de Palestras" (hero) e a palestra futura aparece no destaque/agenda do Livewire.

- [ ] **Step 7: Pint + Commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Http/Controllers/CalendarioController.php tests/Feature/Front/CalendarioSeoTest.php
git add resources/views/palestras/calendario.blade.php app/Http/Controllers/CalendarioController.php tests/Feature/Front/CalendarioSeoTest.php
git rm --cached resources/views/pages/calendario.blade.php 2>/dev/null || true
git commit -m "$(cat <<'EOF'
feat(palestras/calendario): casca da pagina (hero, breadcrumb, JSON-LD, modal) e aposenta o stub

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: CSS da página + import + build + suíte completa + Pint + verificação manual

Cria o CSS próprio (mini-calendário, linha da agenda, avatar dourado, chips), importa no `app.css`, gera o build e faz a verificação final (suíte completa + Pint + checagem visual no localhost).

**Files:**
- Create: `resources/css/palestras-calendario.css`
- Modify: `resources/css/app.css:9` (adicionar `@import` após `palestras-archive.css`)

**Interfaces:**
- Consumes: classes referenciadas pelas views das Tasks 4-5 (`.cema-cal-day`, `.cema-cal-day--com-palestra`, `.cema-cal-day--hoje`, `.cema-row`, `.cema-row-cta`, `.cema-cal-avatar-{0..3}`, `.cema-chip-data--proxima`, `.cema-chip-data--realizada`, `.cema-modal`).
- Produces: estilos aplicados; sem teste automatizado (verificação por build + suíte + manual).

- [ ] **Step 1: Criar o CSS** — `resources/css/palestras-calendario.css`

```css
/* Palestras — Calendário. Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01 */

/* Dia do mini-calendário */
.cema-cal-day {
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    margin: 0 auto;
    border-radius: 9999px;
    font-size: 13px;
    color: var(--color-text-secondary);
}
.cema-cal-day--com-palestra {
    color: #3a2f00;
    font-weight: 700;
    background: radial-gradient(circle at 30% 30%, #f7c24e, var(--color-gold));
    box-shadow: 0 2px 8px rgba(242, 168, 30, .4);
    cursor: pointer;
    transition: transform .18s ease, box-shadow .18s ease;
}
.cema-cal-day--com-palestra:hover {
    transform: scale(1.12);
    box-shadow: 0 4px 12px rgba(242, 168, 30, .55);
}
.cema-cal-day--hoje {
    box-shadow: inset 0 0 0 2px var(--color-secondary);
}

/* Linha da agenda */
.cema-row {
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.cema-row:hover {
    transform: translateX(3px);
    box-shadow: 0 12px 28px rgba(46, 41, 82, .14);
    border-color: var(--color-gold);
}
.cema-row:hover .cema-row-cta {
    background-color: var(--color-primary);
    color: #fff;
    border-color: var(--color-primary);
}
.cema-row.is-destaque {
    animation: cema-row-pulse 1.9s ease;
}
@keyframes cema-row-pulse {
    0%, 100% { box-shadow: 0 2px 8px rgba(0, 0, 0, .08); }
    30% { box-shadow: 0 0 0 3px var(--color-gold), 0 12px 28px rgba(242, 168, 30, .35); }
}

/* Chip de data (72px) */
.cema-chip-data--proxima { background: #fbf1da; color: #8a6a1e; }
.cema-chip-data--realizada { background: #f2f1f6; color: #9a93b4; }

/* Avatar do destaque — gradientes dourados rotacionados por índice */
.cema-cal-avatar { background: linear-gradient(140deg, #f7c24e, #e79048); }
.cema-cal-avatar-0 { background: linear-gradient(140deg, #f7c24e, #e79048); }
.cema-cal-avatar-1 { background: linear-gradient(140deg, #f2a81e, #d9772e); }
.cema-cal-avatar-2 { background: linear-gradient(140deg, #f9d976, #e79048); }
.cema-cal-avatar-3 { background: linear-gradient(140deg, #e6a53a, #c86a2f); }

/* Modal */
.cema-modal { box-shadow: 0 24px 64px rgba(0, 0, 0, .28); }

@media (prefers-reduced-motion: reduce) {
    .cema-cal-day--com-palestra,
    .cema-row { transition: none; }
    .cema-cal-day--com-palestra:hover,
    .cema-row:hover { transform: none; }
    .cema-row.is-destaque { animation: none; }
}
```

- [ ] **Step 2: Importar no `app.css`** — `resources/css/app.css`

Adicionar a linha após `@import './palestras-archive.css';` (linha 9):

```css
@import './palestras-archive.css';
@import './palestras-calendario.css';
```

- [ ] **Step 3: Build (host)**

Run: `npm run build`
Expected: build sem erros; `palestras-calendario.css` incorporado ao bundle.

- [ ] **Step 4: Refletir no dev + suíte COMPLETA + Pint**

```bash
docker compose restart app worker
docker compose exec -T app php artisan test
docker compose exec -T app ./vendor/bin/pint --test
```
Expected: **suíte completa verde** (todos os testes novos + preservação de `CalendarioStubTest`/`CalendarioPalestraTest`); Pint `--test` PASS. *(Nota: `ImportadorBlogTest::test_cap_imagem_*` pode falhar de forma flaky por contenção de memória do GD no container — fora do diff desta fatia; reconfirmar isolado se ocorrer.)*

- [ ] **Step 5: Verificação manual no localhost**

Abrir `http://localhost/palestra_publica/calendario` e conferir:
1. Destaque da próxima palestra com countdown ao vivo (some se não houver futura).
2. Tabs Próximas/Realizadas alternam o conjunto; navegação de mês ‹/› e seletor de ano funcionam e respeitam limites (URL reflete `?modo=`/`?mes=`).
3. Mini-calendário destaca os dias com palestra (rótulo "Dias com palestra"); clicar num dia rola até a linha e pulsa; "hoje" com anel.
4. Modal "Assinar" abre (botão do hero), fecha por ×/Esc/clique-fora; links Google/Apple/Baixar corretos.
5. `curl -sI http://localhost/palestra_publica/calendario.ics` → `text/calendar` inline; `...calendario.ics?download=1` → `attachment`.
6. Responsivo (mobile empilha mini-calendário sobre a agenda); `prefers-reduced-motion` desativa animações.

- [ ] **Step 6: Commit**

```bash
git add resources/css/palestras-calendario.css resources/css/app.css
git commit -m "$(cat <<'EOF'
feat(palestras/calendario): CSS da pagina (mini-calendario, agenda, avatar, modal)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review (checklist do autor)

**1. Cobertura do spec:**
- §5 rotas → Task 2 (feed antes do show, nome `palestras.calendario-ics`). ✅
- §6 `FeedIcs` (escapar/vevento/documento, hora real, LOCATION por formato) → Task 1. ✅
- §7 `CalendarioController` (index + feed, ≤16, `?download`) → Tasks 2 e 5. ✅
- §8 componente Livewire (fronteira `now()` única, sem fallback, meses do modo, matriz) → Task 4. ✅
- §9.1 casca (hero/breadcrumb/JSON-LD/veja também/modal) → Task 5; §9.2 livewire view → Task 4; §9.3 modal → Task 3. ✅
- §10 SEO (ItemList/Event, startDate hora real, eventAttendanceMode) → Task 5 + `CalendarioSeoTest`. ✅
- §11 CSS → Task 6. ✅
- §12 testes: FeedIcsTest/CalendarioFeedTest/CalendarioComponentTest/CalendarioSeoTest/CalendarioRotaTest criados; CalendarioStubTest/CalendarioPalestraTest preservados. ✅
- Polimentos: #1 "Dias com palestra" (Task 4 view); #2 hora real DTSTART/startDate (Tasks 1/5 + testes segunda 20h); #3 rota antes do show (Task 2 + `CalendarioRotaTest`); #4 suíte completa + stub preservado (Task 6 + `CalendarioStubTest`). ✅
- Ajuste do dono: fronteira `now()` única → Task 4 + `test_palestra_realizada_mais_cedo_hoje_cai_em_realizadas_sem_orfa`. ✅

**2. Placeholders:** nenhum "TBD"/"TODO"/"handle edge cases"; todo passo tem código real. ✅

**3. Consistência de tipos/nomes:**
- `FeedIcs::escapar/vevento/documento/PRODID` — mesma assinatura da Task 1 usada nas Tasks 2 e 5. ✅
- Atributos dinâmicos `eh_proxima`/`eh_realizada`/`tem_gravacao` — setados no `render()` (Task 4), lidos na view (Task 4) e aferidos **pelo dado** no `CalendarioComponentTest` via `viewData('palestrasDoMes')`. ✅
- **API de teste do Livewire v4.3.2:** o `Testable` TEM `viewData($key)` (usado na archive mergeada) mas NÃO tem `assertViewHas`; os testes usam `viewData('proxima'|'mesFoco'|'palestrasDoMes'|'matriz'|'temAnterior'|'temProximo')` + `assertSet` (props públicas `modo`/`mes`). ✅
- `createFromFormat('!Y-m', $mesFoco)` (com `!`) evita overflow de dia (29-31) ao formatar o rótulo do mês. ✅
- **`wire:key` nos loops do render** (convenção da archive): `linha-{id}` no `<a>` da agenda; `dia-{n}`/`vazio-{n}`/`dow-{n}` nas células do mini-calendário → morphdom não reaproveita nó errado ao trocar de mês (id de scroll correto, sem vazamento de estado Alpine). ✅
- `matriz` (`diasVazios`/`dias[dia,palestra,hoje]`) — produzida no componente, consumida na view e no teste. ✅
- Classes CSS referenciadas nas views (Tasks 4-5) definidas na Task 6. ✅
- `palestras.calendario-ics` — nome usado na rota (Task 2), no modal (Task 3 via prop) e na casca (Task 5). ✅

## Execution Handoff

Executar via **superpowers:subagent-driven-development** (recomendado): implementador (sonnet) + revisor por-task (sonnet) + revisão final da branch (opus). Modelos: implementação/revisão de task = sonnet; revisão final = opus.
