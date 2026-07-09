# Calendário unificado (Palestras + Eventos) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Uma página `/calendario` que unifica Palestras e Eventos numa mesma grade mensal, com filtro por tipo (todos/palestras/eventos), reaproveitando a anatomia do calendário de palestras e respeitando a visibilidade por papel dos Eventos.

**Architecture:** Uma camada de suporte agnóstica de model — DTO `OcorrenciaCalendario` (readonly) + interface `FonteCalendario` com uma implementação por tipo (`PalestrasFonte`, `EventosFonte`) — mescla e ordena as ocorrências **em PHP** (sem UNION em SQL). Um componente Livewire genérico consome as fontes ativas (derivadas do filtro `tipo`) e monta hero/abas/navegação/mini-grid/lista. Toda query de Evento passa por `visiveisPara($user)`; um 3º tipo (Agenda) entra depois só criando uma nova fonte.

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 3 · Blade SSR · Tailwind v4 (`@theme`) · Alpine · SQLite (testes) / MySQL 8 (dev/prod).

## Global Constraints

- **Idioma:** pt-BR em tudo (código de domínio, comentários, UI, commits). Diacríticos corretos.
- **Banco:** NENHUMA migration nesta fatia. **PROIBIDO** `migrate:fresh`/`refresh`/`wipe`/`reset`/seed destrutivo (todo brief de subagente deve repetir esta proibição).
- **Visibilidade (regra travada):** anônimo vê só `Publico`; logado vê por `roles.nivel` (frequentador 10→+logados; trabalhador 20→+trabalhadores; diretor 30→+diretoria; admin 100→tudo). Palestras não têm visibilidade (sempre aparecem). Toda query de Evento usa `Evento::scopeVisiveisPara(?User)`.
- **Fuso:** `America/Sao_Paulo`. "Realizado" de evento = `COALESCE(data_fim, data_inicio) < hoje` (data, não instante). "Realizada" de palestra = `data_da_palestra < now()`.
- **Endereço/nome da casa:** `config('cema.endereco')` / `config('cema.nome')` (fonte única).
- **Rota antiga:** `permanentRedirect` de `/palestra_publica/calendario` → `/calendario`; **manter** `palestras.calendario-ics`.
- **Qualidade:** Pint antes do push; `docker compose exec -T app php artisan test` verde; `npm run build` **no host**; `docker compose restart app worker` (OPcache) antes da conferência visual; cabeçalho de autoria (`Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09`) nos arquivos novos.
- **Selo de visibilidade:** só para logado e só quando `visibilidade !== Publico`; nunca para anônimo.
- **⚠️ Pré-requisito (3b):** este plano usa `Evento::temHora()` e `Evento::intervaloSchema()`, que **só existem na branch da Fase 3b (PR #21), ainda não na `main`**. **NÃO iniciar antes de a 3b MESCLAR** e passar no passe do dono. Se o passe da 3b alterar esses métodos, este plano acompanha (a `EventosFonte` na Task 3 é o único consumidor).
- **⚠️ Blast-radius de testes (Tasks 5–6):** trocar a rota/página/componente/modal de palestras afeta **7 arquivos de teste** — `CalendarioComponentTest`, `CalendarioRotaTest`, `CalendarioSeoTest`, `CalendarioStubTest`, `AssinarModalTest`, `PalestrasArchiveSeoTest`, `PalestrantePerfilRedesignTest`. **2 ficam INTOCADOS:** `CalendarioFeedTest` (feed `.ics` das próximas, preservado) e **`CalendarioPalestraTest` (o nome engana — testa `palestras.evento-ics`, o `.ics` de UMA palestra, incluindo o escape RFC 5545; esta fatia não toca essa rota).** **Antes de apagar/remover qualquer coisa**, rodar o grep autoritativo do dono: `grep -rl "Palestras\\\\Calendario\|palestras\.calendario'\|assinar-modal" tests/` e tratar CADA arquivo. No **fechamento, rodar a SUÍTE INTEIRA (sem `--filter`)**.

---

### Task 1: DTO `OcorrenciaCalendario` + interface `FonteCalendario`

**Files:**
- Create: `app/Support/Calendario/OcorrenciaCalendario.php`
- Create: `app/Support/Calendario/FonteCalendario.php`
- Test: `tests/Unit/Support/Calendario/OcorrenciaCalendarioTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Carbon`, `Illuminate\Support\Collection`, `App\Models\User`.
- Produces:
  - `OcorrenciaCalendario` (final readonly) com props: `string $tipo`, `string $chave`, `string $titulo`, `string $url`, `CarbonInterface $inicio`, `?CarbonInterface $fim`, `bool $temHora`, `?string $subtitulo`, `string $corAcento`, `array $selo` (`['rotulo','cor','cor_texto']`), `?array $seloVisibilidade` (`['rotulo','cor']` | null), `?string $imagem = null`, `?string $iniciais = null`.
  - `OcorrenciaCalendario::diasNoMes(int $ano, int $mes): array` (list de inteiros 1..N).
  - `OcorrenciaCalendario::ordenar(Collection $ocorrencias): Collection` (por `inicio`, empate → palestra antes de evento).
  - Interface `FonteCalendario` com `tipo(): string`, `meses(string $modo, ?User $u): array`, `ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection`, `proxima(?User $u): ?OcorrenciaCalendario`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Unit\Support\Calendario;

use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OcorrenciaCalendarioTest extends TestCase
{
    private function oc(array $o = []): OcorrenciaCalendario
    {
        return new OcorrenciaCalendario(
            tipo: $o['tipo'] ?? 'evento',
            chave: $o['chave'] ?? 'evento-1',
            titulo: 'X',
            url: '/x',
            inicio: $o['inicio'],
            fim: $o['fim'] ?? null,
            temHora: $o['temHora'] ?? false,
            subtitulo: null,
            corAcento: '#89AB98',
            selo: ['rotulo' => 'S', 'cor' => '#000', 'cor_texto' => '#fff'],
            seloVisibilidade: $o['seloVisibilidade'] ?? null,
        );
    }

    public function test_dias_no_mes_instantaneo_acende_um_dia(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-27 19:00')]);
        $this->assertSame([27], $oc->diasNoMes(2026, 6));
    }

    public function test_dias_no_mes_multidia_acende_todos(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-27'), 'fim' => Carbon::parse('2026-06-29')]);
        $this->assertSame([27, 28, 29], $oc->diasNoMes(2026, 6));
    }

    public function test_dias_no_mes_recorta_na_virada_de_mes(): void
    {
        $oc = $this->oc(['inicio' => Carbon::parse('2026-06-30'), 'fim' => Carbon::parse('2026-07-02')]);
        $this->assertSame([30], $oc->diasNoMes(2026, 6));
        $this->assertSame([1, 2], $oc->diasNoMes(2026, 7));
    }

    public function test_ordenar_por_inicio_com_empate_palestra_antes(): void
    {
        $inst = Carbon::parse('2026-06-27 19:00');
        $evento = $this->oc(['tipo' => 'evento', 'chave' => 'evento-1', 'inicio' => $inst]);
        $palestra = $this->oc(['tipo' => 'palestra', 'chave' => 'palestra-1', 'inicio' => $inst]);
        $depois = $this->oc(['tipo' => 'evento', 'chave' => 'evento-2', 'inicio' => Carbon::parse('2026-06-28 10:00')]);

        $ordenado = OcorrenciaCalendario::ordenar(new Collection([$depois, $evento, $palestra]));

        $this->assertSame(['palestra-1', 'evento-1', 'evento-2'], $ordenado->pluck('chave')->all());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=OcorrenciaCalendarioTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Criar a interface**

`app/Support/Calendario/FonteCalendario.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario;

use App\Models\User;
use Illuminate\Support\Collection;

interface FonteCalendario
{
    /** Identificador do tipo: 'palestra' | 'evento' (| 'agenda' no futuro). */
    public function tipo(): string;

    /**
     * Meses 'Y-m' com ocorrência VISÍVEL no modo, em ordem ascendente.
     *
     * @return list<string>
     */
    public function meses(string $modo, ?User $u): array;

    /**
     * Ocorrências VISÍVEIS que TOCAM (ano,mês) no modo, já como DTO.
     *
     * @return Collection<int, OcorrenciaCalendario>
     */
    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection;

    /** Próxima ocorrência FUTURA visível (hero/countdown); null se não houver. */
    public function proxima(?User $u): ?OcorrenciaCalendario;
}
```

- [ ] **Step 4: Criar o DTO**

`app/Support/Calendario/OcorrenciaCalendario.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class OcorrenciaCalendario
{
    /** Ordem de desempate quando duas ocorrências começam no mesmo instante. */
    private const ORDEM_TIPO = ['palestra' => 0, 'evento' => 1, 'agenda' => 2];

    public function __construct(
        public string $tipo,
        public string $chave,
        public string $titulo,
        public string $url,
        public CarbonInterface $inicio,
        public ?CarbonInterface $fim, // fim para o SPAN de dias no grid — é uma DATA, não o instante de término
        public bool $temHora,
        public ?string $subtitulo,
        public string $corAcento,
        public array $selo,
        public ?array $seloVisibilidade,
        public ?string $imagem = null,
        public ?string $iniciais = null,
    ) {}

    /**
     * Dias (1..N) que a ocorrência cobre DENTRO de (ano,mês) — multi-dia acende vários,
     * recortando na virada do mês.
     *
     * @return list<int>
     */
    public function diasNoMes(int $ano, int $mes): array
    {
        // Fuso explícito: não depender de config('app.timezone') — se virar UTC, o último dia
        // de um evento multi-dia sumiria silenciosamente (o startOfDay ficaria 3h à frente).
        $primeiro = Carbon::create($ano, $mes, 1, 0, 0, 0, 'America/Sao_Paulo');
        $ultimo = $primeiro->copy()->endOfMonth();

        $ini = $this->inicio->copy()->setTimezone('America/Sao_Paulo')->startOfDay();
        $fim = ($this->fim ?? $this->inicio)->copy()->setTimezone('America/Sao_Paulo')->startOfDay();

        $de = $ini->lt($primeiro) ? $primeiro->copy() : $ini;
        $ate = $fim->gt($ultimo) ? $ultimo->copy() : $fim;

        if ($de->gt($ate)) {
            return [];
        }

        $dias = [];
        for ($d = $de->copy(); $d->lte($ate); $d->addDay()) {
            $dias[] = (int) $d->day;
        }

        return $dias;
    }

    /** Ordena por início; empate → palestra antes de evento (determinístico). */
    public static function ordenar(Collection $ocorrencias): Collection
    {
        return $ocorrencias
            ->sort(fn (self $a, self $b) => [$a->inicio->getTimestamp(), self::ORDEM_TIPO[$a->tipo] ?? 9]
                <=> [$b->inicio->getTimestamp(), self::ORDEM_TIPO[$b->tipo] ?? 9])
            ->values();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=OcorrenciaCalendarioTest`
Expected: PASS (4 testes).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Calendario/OcorrenciaCalendario.php app/Support/Calendario/FonteCalendario.php tests/Unit/Support/Calendario/OcorrenciaCalendarioTest.php
git commit -m "feat(calendario): DTO OcorrenciaCalendario + interface FonteCalendario"
```

---

### Task 2: `PalestrasFonte`

**Files:**
- Create: `app/Support/Calendario/Fontes/PalestrasFonte.php`
- Test: `tests/Feature/Calendario/PalestrasFonteTest.php`

**Interfaces:**
- Consumes: `FonteCalendario`, `OcorrenciaCalendario` (Task 1); `App\Models\Palestra` (`data_da_palestra` datetime, `online` bool, `palestrantesAtivos`, `assuntos`, `slug`, `titulo`, rota `palestras.show`).
- Produces: `PalestrasFonte` implements `FonteCalendario` com `tipo() === 'palestra'`. `seloVisibilidade` sempre `null` (palestra não tem visibilidade). `temHora` sempre `true`, `fim` sempre `null`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Models\Palestra;
use App\Support\Calendario\Fontes\PalestrasFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PalestrasFonteTest extends TestCase
{
    use RefreshDatabase;

    private function palestra(array $o = []): Palestra
    {
        return Palestra::factory()->create(array_merge([
            'status' => 'publicado',
            'data_da_palestra' => Carbon::now()->addDays(7)->setTime(19, 0),
        ], $o));
    }

    public function test_proximas_e_realizadas_separam_por_data(): void
    {
        $this->palestra(['slug' => 'futura', 'data_da_palestra' => Carbon::now()->addDays(10)->setTime(19, 0)]);
        $this->palestra(['slug' => 'passada', 'data_da_palestra' => Carbon::now()->subDays(10)->setTime(19, 0)]);

        $fonte = new PalestrasFonte;

        $this->assertCount(1, $fonte->ocorrencias((int) now()->addDays(10)->year, (int) now()->addDays(10)->month, 'proximas', null));
        $this->assertSame('palestra', $fonte->tipo());
    }

    public function test_ocorrencia_vira_dto_com_hora_e_sem_visibilidade(): void
    {
        $quando = Carbon::now()->addDays(5)->setTime(19, 0);
        $this->palestra(['slug' => 'p1', 'titulo' => 'Mediunidade', 'data_da_palestra' => $quando]);

        $oc = new PalestrasFonte->ocorrencias((int) $quando->year, (int) $quando->month, 'proximas', null)->first();

        $this->assertSame('palestra', $oc->tipo);
        $this->assertTrue($oc->temHora);
        $this->assertNull($oc->fim);
        $this->assertNull($oc->seloVisibilidade);
        $this->assertStringContainsString('Mediunidade', $oc->titulo);
    }

    public function test_proxima_retorna_a_mais_proxima_futura(): void
    {
        $this->palestra(['slug' => 'depois', 'data_da_palestra' => Carbon::now()->addDays(20)->setTime(19, 0)]);
        $this->palestra(['slug' => 'antes', 'titulo' => 'A Próxima', 'data_da_palestra' => Carbon::now()->addDays(3)->setTime(19, 0)]);

        $this->assertStringContainsString('A Próxima', (new PalestrasFonte)->proxima(null)->titulo);
    }
}
```

> Nota: `new PalestrasFonte->ocorrencias(...)` exige PHP 8.4; neste projeto (8.3) usar `(new PalestrasFonte)->ocorrencias(...)`. Ajustar a linha do teste conforme.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=PalestrasFonteTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Implementar `PalestrasFonte`**

`app/Support/Calendario/Fontes/PalestrasFonte.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario\Fontes;

use App\Models\Palestra;
use App\Models\User;
use App\Support\Calendario\FonteCalendario;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class PalestrasFonte implements FonteCalendario
{
    public const COR = '#F4C24B'; // dourado (acento de palestra)

    public function tipo(): string
    {
        return 'palestra';
    }

    public function meses(string $modo, ?User $u): array
    {
        return $this->query($modo)->orderBy('data_da_palestra')
            ->pluck('data_da_palestra')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()->values()->all();
    }

    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection
    {
        $agora = now();
        $proxima = $this->query('proximas')->orderBy('data_da_palestra')->first();

        return $this->query($modo)
            ->whereYear('data_da_palestra', $ano)
            ->whereMonth('data_da_palestra', $mes)
            ->with(['palestrantesAtivos', 'assuntos'])
            ->orderBy('data_da_palestra')
            ->get()
            ->map(fn (Palestra $p) => $this->paraOcorrencia($p, $agora, $proxima));
    }

    public function proxima(?User $u): ?OcorrenciaCalendario
    {
        $agora = now();
        $p = $this->query('proximas')->orderBy('data_da_palestra')
            ->with(['palestrantesAtivos', 'assuntos'])->first();

        return $p ? $this->paraOcorrencia($p, $agora, $p) : null;
    }

    /** Palestras publicadas com data, filtradas pelo modo. */
    private function query(string $modo): Builder
    {
        $agora = now();
        $q = Palestra::query()->publicado()->whereNotNull('data_da_palestra');

        return $modo === 'realizadas'
            ? $q->where('data_da_palestra', '<', $agora)
            : $q->where('data_da_palestra', '>=', $agora);
    }

    private function paraOcorrencia(Palestra $p, Carbon $agora, ?Palestra $proxima): OcorrenciaCalendario
    {
        $ehProxima = $proxima !== null && $p->id === $proxima->id;
        $ehRealizada = $p->data_da_palestra->lt($agora);

        $palestrantes = $p->palestrantesAtivos->pluck('nome')->join(', ', ' e ');
        $tema = optional($p->assuntos->first())->nome;
        $formato = $p->online ? 'Online' : 'Presencial';
        $subtitulo = trim(implode(' · ', array_filter([
            $palestrantes !== '' ? 'com '.$palestrantes : null,
            $tema,
            $formato,
        ])));

        $pa = $p->palestrantesAtivos->first();

        return new OcorrenciaCalendario(
            tipo: 'palestra',
            chave: 'palestra-'.$p->id,
            titulo: $p->titulo,
            url: route('palestras.show', $p->slug),
            inicio: $p->data_da_palestra->copy(),
            fim: null,
            temHora: true,
            subtitulo: $subtitulo !== '' ? $subtitulo : null,
            corAcento: self::COR,
            selo: $this->selo($ehProxima, $ehRealizada),
            seloVisibilidade: null,
            imagem: $pa?->foto_thumb_url,
            iniciais: $pa ? $this->iniciais($pa->nome) : 'CEMA',
        );
    }

    /** @return array{rotulo:string,cor:string,cor_texto:string} */
    private function selo(bool $ehProxima, bool $ehRealizada): array
    {
        if ($ehProxima) {
            return ['rotulo' => 'Próxima', 'cor' => '#F4C24B', 'cor_texto' => '#3a2f00'];
        }
        if ($ehRealizada) {
            return ['rotulo' => 'Realizada', 'cor' => '#EFEDF5', 'cor_texto' => '#6a6390'];
        }

        return ['rotulo' => 'Agendada', 'cor' => '#EAF1EC', 'cor_texto' => '#3a6b4e'];
    }

    private function iniciais(string $nome): string
    {
        return collect(explode(' ', $nome))->take(2)->map(fn ($n) => mb_substr($n, 0, 1))->implode('');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=PalestrasFonteTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Calendario/Fontes/PalestrasFonte.php tests/Feature/Calendario/PalestrasFonteTest.php
git commit -m "feat(calendario): PalestrasFonte (Palestra -> OcorrenciaCalendario)"
```

---

### Task 3: `EventosFonte` (com visibilidade — o ponto crítico)

**Files:**
- Create: `app/Support/Calendario/Fontes/EventosFonte.php`
- Test: `tests/Feature/Calendario/EventosFonteTest.php`

**Interfaces:**
- Consumes: `FonteCalendario`, `OcorrenciaCalendario` (Task 1); `App\Models\Evento` (`scopeVisiveisPara(?User)`, `scopePublicado`, `inicioUtc()`, `status_selo`, `periodo`, `categoria`, `visibilidade`, `data_inicio`/`data_fim` Y-m-d, `hora_inicio`/`hora_fim`, `temHora()`, `flyerUrl`); `App\Enums\VisibilidadeEvento` (`rotulo()`, `cor()`, `Publico`).
- Produces: `EventosFonte` implements `FonteCalendario` com `tipo() === 'evento'`. **Toda** query via `->visiveisPara($u)`. `seloVisibilidade` = `['rotulo','cor']` só quando `$u` pode ver e `visibilidade !== Publico`; senão `null`.

- [ ] **Step 1: Write the failing test** (foco: visibilidade + multi-dia + overlap)

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use App\Support\Calendario\Fontes\EventosFonte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventosFonteTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $o = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó', 'slug' => 'brecho',
            'data_inicio' => Carbon::now()->addDays(10)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_anonimo_nao_ve_evento_restrito_diretor_ve(): void
    {
        $mes = (int) Carbon::now()->addDays(10)->month;
        $ano = (int) Carbon::now()->addDays(10)->year;
        $this->evento(['slug' => 'reservado', 'titulo' => 'Reunião', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $fonte = new EventosFonte;

        $this->assertTrue($fonte->ocorrencias($ano, $mes, 'proximas', null)->isEmpty());
        $this->assertCount(1, $fonte->ocorrencias($ano, $mes, 'proximas', $this->diretor()));
        // e o mês nem aparece p/ anônimo
        $this->assertSame([], $fonte->meses('proximas', null));
    }

    public function test_selo_visibilidade_so_para_quem_ve_restrito(): void
    {
        $quando = Carbon::now()->addDays(10);
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria]);

        $oc = (new EventosFonte)->ocorrencias((int) $quando->year, (int) $quando->month, 'proximas', $this->diretor())->first();

        $this->assertNotNull($oc->seloVisibilidade);
        $this->assertSame('Somente diretoria', $oc->seloVisibilidade['rotulo']);
    }

    public function test_multidia_acende_todos_os_dias_do_mes(): void
    {
        $ini = Carbon::now()->addDays(10)->startOfMonth()->addDays(9); // dia 10 do mês
        $this->evento(['slug' => 'semana', 'data_inicio' => $ini->toDateString(), 'data_fim' => $ini->copy()->addDays(2)->toDateString()]);

        $oc = (new EventosFonte)->ocorrencias((int) $ini->year, (int) $ini->month, 'proximas', null)->first();

        $this->assertSame([(int) $ini->day, (int) $ini->day + 1, (int) $ini->day + 2], $oc->diasNoMes((int) $ini->year, (int) $ini->month));
    }

    public function test_realizado_usa_coalesce_data_fim(): void
    {
        // começou ontem, termina amanhã → é PRÓXIMO (em andamento), não realizado
        $this->evento(['slug' => 'andamento', 'data_inicio' => Carbon::now()->subDay()->toDateString(), 'data_fim' => Carbon::now()->addDay()->toDateString()]);

        $fonte = new EventosFonte;
        $mes = (int) now()->month;
        $ano = (int) now()->year;

        $this->assertCount(1, $fonte->ocorrencias($ano, $mes, 'proximas', null));
        $this->assertTrue($fonte->ocorrencias($ano, $mes, 'realizadas', null)->isEmpty());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventosFonteTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Implementar `EventosFonte`**

`app/Support/Calendario/Fontes/EventosFonte.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Support\Calendario\Fontes;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use App\Support\Calendario\FonteCalendario;
use App\Support\Calendario\OcorrenciaCalendario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EventosFonte implements FonteCalendario
{
    public function tipo(): string
    {
        return 'evento';
    }

    public function meses(string $modo, ?User $u): array
    {
        // Um evento pode cobrir vários meses; junta os meses cobertos por cada intervalo visível.
        return $this->query($modo, $u)->orderBy('data_inicio')
            ->get(['data_inicio', 'data_fim'])
            ->flatMap(fn (Evento $e) => $this->mesesDoIntervalo(
                $e->getRawOriginal('data_inicio'),
                $e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio')
            ))
            ->unique()->sort()->values()->all();
    }

    public function ocorrencias(int $ano, int $mes, string $modo, ?User $u): Collection
    {
        $primeiro = Carbon::create($ano, $mes, 1)->toDateString();
        $ultimo = Carbon::create($ano, $mes, 1)->endOfMonth()->toDateString();

        return $this->query($modo, $u)
            // overlap: começa até o fim do mês E termina (coalesce) no primeiro dia ou depois
            ->where('data_inicio', '<=', $ultimo)
            ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$primeiro])
            ->with(['categoria', 'media'])
            ->orderBy('data_inicio')
            ->get()
            ->map(fn (Evento $e) => $this->paraOcorrencia($e, $u));
    }

    public function proxima(?User $u): ?OcorrenciaCalendario
    {
        $e = $this->query('proximas', $u)
            ->with(['categoria', 'media'])
            ->orderBy('data_inicio')->first();

        return $e ? $this->paraOcorrencia($e, $u) : null;
    }

    /** Eventos publicados VISÍVEIS ao usuário, filtrados pelo modo (data, não instante). */
    private function query(string $modo, ?User $u): Builder
    {
        $hoje = now('America/Sao_Paulo')->toDateString();
        $q = Evento::query()->publicado()->visiveisPara($u);

        return $modo === 'realizadas'
            ? $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje])
            : $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje]);
    }

    /** @return list<string> meses 'Y-m' cobertos por [inicio,fim] (strings Y-m-d). */
    private function mesesDoIntervalo(string $inicio, string $fim): array
    {
        $cursor = Carbon::parse($inicio)->startOfMonth();
        $ate = Carbon::parse($fim)->startOfMonth();
        $meses = [];
        while ($cursor->lte($ate)) {
            $meses[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $meses;
    }

    private function paraOcorrencia(Evento $e, ?User $u): OcorrenciaCalendario
    {
        $restrito = $e->visibilidade !== VisibilidadeEvento::Publico;

        return new OcorrenciaCalendario(
            tipo: 'evento',
            chave: 'evento-'.$e->id,
            titulo: $e->titulo,
            url: route('eventos.show', $e->slug),
            inicio: $e->inicioUtc()->setTimezone('America/Sao_Paulo'),
            // fim = DATA crua no fuso da casa (span de dias no grid, não instante de término).
            fim: Carbon::parse($e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio'), 'America/Sao_Paulo'),
            temHora: $e->temHora(),
            subtitulo: $e->local ?: null,
            corAcento: $e->categoria?->cor ?? '#89AB98',
            selo: $e->status_selo, // ['rotulo','cor','cor_texto']
            seloVisibilidade: $restrito
                ? ['rotulo' => $e->visibilidade->rotulo(), 'cor' => $e->visibilidade->cor()]
                : null,
            imagem: $e->flyerUrl,
            iniciais: null,
        );
    }
}
```

> **Nota de fuso/exibição:** `inicio` usa `inicioUtc()->setTimezone(SP)` para o chip de data/hora e o countdown; `fim` usa a **data** crua (para `diasNoMes` multi-dia e a lógica de span). Como `diasNoMes` normaliza com `startOfDay()`, misturar instante (início) e data (fim) é seguro.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=EventosFonteTest`
Expected: PASS (visibilidade anon×diretor, selo, multi-dia, coalesce).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Calendario/Fontes/EventosFonte.php tests/Feature/Calendario/EventosFonteTest.php
git commit -m "feat(calendario): EventosFonte com visibilidade por papel + multi-dia"
```

---

### Task 4: Componente Livewire `Calendario\Calendario`

**Files:**
- Create: `app/Livewire/Calendario/Calendario.php`
- Create (esqueleto mínimo p/ o teste renderizar): `resources/views/livewire/calendario/calendario.blade.php` (só o essencial; UI completa na Task 6)
- Test: `tests/Feature/Calendario/CalendarioLivewireTest.php`

**Interfaces:**
- Consumes: `PalestrasFonte` (Task 2), `EventosFonte` (Task 3), `OcorrenciaCalendario::ordenar()` (Task 1).
- Produces: componente com `#[Url]` `modo` (`proximas`|`realizadas`), `mes` (`Y-m`), `tipo` (`todos`|`palestras`|`eventos`). Passa à view: `proxima`, `modo`, `tipo`, `mesFoco`, `anos`, `ocorrenciasDoMes` (Collection<OcorrenciaCalendario>), `matriz` (`['diasVazios'=>int,'dias'=>list<['dia'=>int,'ocorrencias'=>array,'ancora'=>?string,'hoje'=>bool]>]`), `agora`, `temAnterior`, `temProximo`, `contagem` (int).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Calendario;

use App\Enums\VisibilidadeEvento;
use App\Livewire\Calendario\Calendario;
use App\Models\Evento;
use App\Models\Palestra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CalendarioLivewireTest extends TestCase
{
    use RefreshDatabase;

    private function semear(): void
    {
        $quando = Carbon::now()->addDays(10);
        Palestra::factory()->create(['status' => 'publicado', 'titulo' => 'Palestra X', 'slug' => 'px', 'data_da_palestra' => $quando->copy()->setTime(19, 0)]);
        Evento::create(['titulo' => 'Evento Y', 'slug' => 'ey', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Reunião Secreta', 'slug' => 'rs', 'data_inicio' => $quando->toDateString(), 'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
    }

    private function diretor(): User
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');

        return $u;
    }

    public function test_todos_intercala_palestra_e_evento_publicos(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)
            ->assertSee('Palestra X')
            ->assertSee('Evento Y')
            ->assertDontSee('Reunião Secreta'); // anônimo não vê restrito
    }

    public function test_filtro_tipo_isola_a_fonte(): void
    {
        $this->semear();
        Livewire::test(Calendario::class)->set('tipo', 'palestras')
            ->assertSee('Palestra X')->assertDontSee('Evento Y');
        Livewire::test(Calendario::class)->set('tipo', 'eventos')
            ->assertSee('Evento Y')->assertDontSee('Palestra X');
    }

    public function test_diretor_ve_evento_restrito(): void
    {
        $this->semear();
        Livewire::actingAs($this->diretor())->test(Calendario::class)
            ->assertSee('Reunião Secreta');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CalendarioLivewireTest`
Expected: FAIL (componente/rota inexistente).

- [ ] **Step 3: Implementar o componente**

`app/Livewire/Calendario/Calendario.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace App\Livewire\Calendario;

use App\Support\Calendario\Fontes\EventosFonte;
use App\Support\Calendario\Fontes\PalestrasFonte;
use App\Support\Calendario\OcorrenciaCalendario;
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

    #[Url(as: 'tipo', except: 'todos')]
    public string $tipo = 'todos';

    public function mount(): void
    {
        $this->normaliza();
        $meses = $this->mesesModoAsc();
        if ($this->mes === null || ! in_array($this->mes, $meses, true)) {
            $this->mes = $this->mesPadrao($meses);
        }
    }

    public function updatedModo(): void
    {
        $this->normaliza();
        $this->mes = $this->mesPadrao($this->mesesModoAsc());
    }

    public function updatedTipo(): void
    {
        $this->normaliza();
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
        $usuario = auth()->user();

        $proxima = OcorrenciaCalendario::ordenar(
            collect($this->fontesAtivas())->map(fn ($f) => $f->proxima($usuario))->filter()->values()
        )->first();

        $mesesAsc = $this->mesesModoAsc();
        $mesesExib = $this->modo === 'realizadas' ? array_reverse($mesesAsc) : $mesesAsc;
        $anos = collect($mesesExib)->map(fn ($m) => substr($m, 0, 4))->unique()->values()->all();

        $mesFoco = in_array($this->mes, $mesesAsc, true) ? $this->mes : $this->mesPadrao($mesesAsc);

        $ocorrenciasDoMes = new Collection;
        $matriz = ['diasVazios' => 0, 'dias' => []];
        $temAnterior = $temProximo = false;

        if ($mesFoco !== null) {
            [$ano, $mesNum] = array_map('intval', explode('-', $mesFoco));

            $ocorrenciasDoMes = OcorrenciaCalendario::ordenar(
                collect($this->fontesAtivas())
                    ->flatMap(fn ($f) => $f->ocorrencias($ano, $mesNum, $this->modo, $usuario)->all())
                    ->pipe(fn ($c) => new Collection($c))
            );

            $i = array_search($mesFoco, $mesesAsc, true);
            $temAnterior = $i !== false && $i > 0;
            $temProximo = $i !== false && $i < count($mesesAsc) - 1;

            $matriz = $this->matriz($ano, $mesNum, $ocorrenciasDoMes, $agora);
        }

        return view('livewire.calendario.calendario', [
            'proxima' => $proxima,
            'modo' => $this->modo,
            'tipo' => $this->tipo,
            'mesFoco' => $mesFoco,
            'anos' => $anos,
            'ocorrenciasDoMes' => $ocorrenciasDoMes,
            'contagem' => $ocorrenciasDoMes->count(),
            'matriz' => $matriz,
            'agora' => $agora,
            'temAnterior' => $temAnterior,
            'temProximo' => $temProximo,
        ]);
    }

    /** @return list<\App\Support\Calendario\FonteCalendario> */
    private function fontesAtivas(): array
    {
        return match ($this->tipo) {
            'palestras' => [new PalestrasFonte],
            'eventos' => [new EventosFonte],
            default => [new PalestrasFonte, new EventosFonte],
        };
    }

    private function normaliza(): void
    {
        if (! in_array($this->modo, ['proximas', 'realizadas'], true)) {
            $this->modo = 'proximas';
        }
        if (! in_array($this->tipo, ['todos', 'palestras', 'eventos'], true)) {
            $this->tipo = 'todos';
        }
    }

    /** União (ordenada ASC) dos meses das fontes ativas, no modo atual. */
    private function mesesModoAsc(): array
    {
        $usuario = auth()->user();

        return collect($this->fontesAtivas())
            ->flatMap(fn ($f) => $f->meses($this->modo, $usuario))
            ->unique()->sort()->values()->all();
    }

    private function mesPadrao(array $mesesAsc): ?string
    {
        if ($mesesAsc === []) {
            return null;
        }

        return $this->modo === 'realizadas' ? end($mesesAsc) : $mesesAsc[0];
    }

    /**
     * @param  Collection<int,OcorrenciaCalendario>  $ocorrencias
     * @return array{diasVazios:int, dias:list<array{dia:int, ocorrencias:list<array{tipo:string,cor:string,titulo:string}>, ancora:?string, hoje:bool}>}
     */
    private function matriz(int $ano, int $mes, Collection $ocorrencias, Carbon $agora): array
    {
        $primeiro = Carbon::create($ano, $mes, 1);
        $diasNoMes = $primeiro->daysInMonth;
        $offset = $primeiro->dayOfWeek; // 0=domingo

        $porDia = [];
        $ancoraDia = [];
        foreach ($ocorrencias as $oc) {
            foreach ($oc->diasNoMes($ano, $mes) as $d) {
                $porDia[$d][] = ['tipo' => $oc->tipo, 'cor' => $oc->corAcento, 'titulo' => $oc->titulo];
                $ancoraDia[$d] ??= $oc->chave; // 1ª ocorrência do dia = alvo do scroll
            }
        }

        $ehMesCorrente = (int) $agora->year === $ano && (int) $agora->month === $mes;

        $dias = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $dias[] = [
                'dia' => $d,
                'ocorrencias' => $porDia[$d] ?? [],
                'ancora' => $ancoraDia[$d] ?? null,
                'hoje' => $ehMesCorrente && (int) $agora->day === $d,
            ];
        }

        return ['diasVazios' => $offset, 'dias' => $dias];
    }
}
```

- [ ] **Step 4: Esqueleto mínimo da view (só p/ o teste passar; UI completa na Task 6)**

`resources/views/livewire/calendario/calendario.blade.php`:

```blade
<div>
    @foreach ($ocorrenciasDoMes as $oc)
        <a href="{{ $oc->url }}" wire:key="{{ $oc->chave }}">{{ $oc->titulo }}</a>
    @endforeach
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=CalendarioLivewireTest`
Expected: PASS (intercala; filtro isola; anônimo não vê restrito; diretor vê).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Calendario/Calendario.php resources/views/livewire/calendario/calendario.blade.php tests/Feature/Calendario/CalendarioLivewireTest.php
git commit -m "feat(calendario): componente Livewire unificado (filtro tipo + merge por fonte)"
```

---

### Task 5: Rota `/calendario` + controller + 301 + SEO/cache + sitemap + links

**Files:**
- Modify: `routes/web.php` (substituir a rota-página `palestras.calendario` por `permanentRedirect`; adicionar `calendario.index`)
- Modify: `app/Http/Controllers/CalendarioController.php` (`index()` → página unificada + `$jsonLdItemList` público + `Cache-Control` p/ logado; `feed()` inalterado)
- Create: `resources/views/calendario.blade.php` (casca: hero + breadcrumb + `<livewire:calendario.calendario />` + JSON-LD + canonical)
- Modify (6 links): `resources/views/agenda/index.blade.php:131`, `resources/views/conta/painel.blade.php:4` e `:20`, `resources/views/palestras/index.blade.php:29`, `resources/views/palestrantes/index.blade.php:29`, `resources/views/palestrantes/perfil/hero.blade.php:49`
- Modify: `resources/views/sitemap.blade.php` (linha estática `/calendario`)
- Modify (testes que referenciam a rota/página removida — reapontar): `tests/Feature/Front/CalendarioRotaTest.php`, `tests/Feature/Front/CalendarioStubTest.php`, `tests/Feature/Front/CalendarioSeoTest.php`, `tests/Feature/Front/PalestrasArchiveSeoTest.php`, `tests/Feature/Front/PalestrantePerfilRedesignTest.php`
- Test: `tests/Feature/Front/CalendarioUnificadoTest.php`
- (O teste de **componente** — `CalendarioComponentTest` — e o `AssinarModalTest` são tratados na **Task 6**, junto da remoção do componente/modal. `CalendarioFeedTest` (feed `.ics` das próximas) e `CalendarioPalestraTest` (`.ics` de UMA palestra — `palestras.evento-ics`) ficam **inalterados**.)

**Interfaces:**
- Consumes: `Calendario\Calendario` (Task 4); `Palestra`, `Evento` (JSON-LD público).
- Produces: rota nomeada `calendario.index` (`/calendario`); redirect 301 de `/palestra_publica/calendario`; view `calendario` renderizando o Livewire.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioUnificadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendario_200_e_canonical(): void
    {
        $this->get('/calendario')->assertOk()->assertSee('rel="canonical"', false);
    }

    public function test_301_da_url_antiga(): void
    {
        $this->get('/palestra_publica/calendario')->assertRedirect('/calendario');
        $this->get('/palestra_publica/calendario')->assertStatus(301);
    }

    public function test_jsonld_nao_inclui_evento_restrito(): void
    {
        Evento::create(['titulo' => 'Reunião Secreta', 'slug' => 'rs', 'data_inicio' => Carbon::now()->addDays(5)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Feirão Aberto', 'slug' => 'fa', 'data_inicio' => Carbon::now()->addDays(6)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);

        $r = $this->get('/calendario')->assertOk();
        $r->assertSee('Feirão Aberto', false);
        $r->assertDontSee('Reunião Secreta', false); // nem no JSON-LD nem na grade p/ anônimo
    }

    public function test_logado_recebe_cache_control_sem_public(): void
    {
        $r = $this->actingAs(User::factory()->create())->get('/calendario')->assertOk();
        $this->assertStringNotContainsString('public', (string) $r->headers->get('Cache-Control'));
    }

    public function test_sitemap_inclui_calendario(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/calendario'), false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CalendarioUnificadoTest`
Expected: FAIL (rota/controller/view novos).

- [ ] **Step 3: Rotas**

Em `routes/web.php`, **trocar** a linha da página (hoje `palestras.calendario`) pelo redirect e **adicionar** `/calendario`. A `.ics` fica:

```php
// Página do calendário migrou para /calendario (unificado). 301 preserva SEO/links antigos.
// DEVE vir ANTES de palestras.show para não ser capturada por {slug}.
Route::permanentRedirect('/palestra_publica/calendario', '/calendario');

// Feed .ics agregado das próximas palestras. DEVE vir ANTES de palestras.show.
Route::get('/palestra_publica/calendario.ics', [CalendarioController::class, 'feed'])->name('palestras.calendario-ics');
```

E, junto às rotas de topo (ex.: perto de `eventos.index`), adicionar:

```php
// Calendário unificado (Palestras + Eventos). Rota de topo, sem colisão de {slug}.
Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
```

- [ ] **Step 4: Controller `index()` unificado**

Substituir `CalendarioController::index()` (o `feed()` continua igual). Monta o JSON-LD só com públicos (palestras + eventos `visiveisPara(null)`), ordenados, ≤16, e marca `Cache-Control` p/ logado:

```php
public function index(Request $request): Response
{
    $palestras = Palestra::query()->publicado()->whereNotNull('data_da_palestra')
        ->where('data_da_palestra', '>=', now())
        ->with(['palestrantesAtivos'])
        ->orderBy('data_da_palestra')->take(16)->get()
        ->map(fn (Palestra $p) => [
            '@type' => 'Event',
            'name' => $p->titulo,
            'startDate' => $p->data_da_palestra->toIso8601String(),
            'url' => route('palestras.show', $p->slug),
        ]);

    $eventos = Evento::query()->publicado()->visiveisPara(null)
        ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [now('America/Sao_Paulo')->toDateString()])
        ->orderBy('data_inicio')->take(16)->get()
        ->map(function (Evento $e) {
            $intervalo = $e->intervaloSchema();

            return [
                '@type' => 'Event',
                'name' => $e->titulo,
                'startDate' => $intervalo['inicio'],
                'endDate' => $intervalo['fim'],
                'url' => route('eventos.show', $e->slug),
            ];
        });

    $ocorrenciasSeo = $palestras->concat($eventos)->sortBy('startDate')->take(16)->values();

    $resposta = response()->view('calendario', ['ocorrenciasSeo' => $ocorrenciasSeo]);

    // Página varia por nível de acesso quando logado → nunca em cache compartilhado.
    if ($request->user() !== null) {
        $resposta->header('Cache-Control', 'private, no-store');
    }

    return $resposta;
}
```

> Ajustar os `use`: `App\Models\Evento`, `Illuminate\Http\Request`, `Illuminate\Http\Response` (o `View` deixa de ser o retorno de `index`).

- [ ] **Step 5: View casca `resources/views/calendario.blade.php`**

Hero (molde do calendário de palestras) + breadcrumb + `<livewire:calendario.calendario />` + canonical + JSON-LD `ItemList` (só se houver ocorrências). Ver molde em `resources/views/palestras/calendario.blade.php` (hero linhas 45–61; JSON-LD linhas 2–43). Título "Calendário"; subtítulo genérico; `<link rel="canonical" href="{{ route('calendario.index') }}">` no `<x-slot:head>`. O bloco JSON-LD usa `$ocorrenciasSeo` (já pronto do controller):

```blade
<x-slot:head>
    <link rel="canonical" href="{{ route('calendario.index') }}">
    @if ($ocorrenciasSeo->isNotEmpty())
        <script type="application/ld+json">
            @json(['@context' => 'https://schema.org', '@type' => 'ItemList',
                'name' => 'Próximas atividades do CEMA',
                'itemListElement' => $ocorrenciasSeo->values()->map(fn ($ev, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => $ev])->all()],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
        </script>
    @endif
</x-slot:head>
```

- [ ] **Step 6: Atualizar os 6 links (views) + sitemap**

- `route('palestras.calendario')` → `route('calendario.index')` em `agenda/index.blade.php:131`, `conta/painel.blade.php:4` e `:20`.
- `route('palestras.calendario')` → `route('calendario.index', ['tipo' => 'palestras'])` em `palestras/index.blade.php:29`, `palestrantes/index.blade.php:29`, `palestrantes/perfil/hero.blade.php:49`.
- `resources/views/sitemap.blade.php`: adicionar uma `<url><loc>{{ url('/calendario') }}</loc>...</url>` estática (junto de outras páginas fixas, se houver; senão criar a entrada).

- [ ] **Step 7: Reapontar os testes que dependiam da rota/página antiga** (senão `RouteNotFoundException` / 200 virou 301)

- `tests/Feature/Front/CalendarioRotaTest.php`: `test_pagina_calendario_segue_respondendo_200` → passar a esperar **301** em `/palestra_publica/calendario` (`assertRedirect('/calendario')`) e criar/mover a asserção de 200 para `GET /calendario`. **Manter** as asserções do `.ics` (linhas 15/21 — `palestras.calendario-ics` continua).
- `tests/Feature/Front/CalendarioStubTest.php`: `test_pagina_calendario_responde_200` e `test_calendario_lista_palestra_futura` → GET `/calendario` (não a antiga); **remover** a linha `assertSame(url('/palestra_publica/calendario'), route('palestras.calendario'))` (rota nomeada deixou de existir) — substituir por `assertRedirect` da antiga. *(Ou migrar tudo para `CalendarioUnificadoTest` e apagar o arquivo.)*
- `tests/Feature/Front/CalendarioSeoTest.php`: apontar o SEO para `GET /calendario` (canonical + JSON-LD) — a antiga agora é 301.
- `tests/Feature/Front/PalestrasArchiveSeoTest.php:26`: `assertSee(route('palestras.calendario'), false)` → `assertSee(route('calendario.index', ['tipo' => 'palestras']), false)`.
- `tests/Feature/Front/PalestrantePerfilRedesignTest.php:39`: idem → `route('calendario.index', ['tipo' => 'palestras'])`.

- [ ] **Step 8: Verificar ausência de referência órfã à rota nomeada**

Run: `docker compose exec -T app php artisan route:list --name=palestras.calendario`
Expected: aparece **só** `palestras.calendario-ics` (a página `palestras.calendario` não existe mais).
Run (grep): `grep -rn "palestras\.calendario'" resources/ tests/` — **zero** ocorrências (só `palestras.calendario-ics` com hífen, que não casa com `calendario'`).

- [ ] **Step 9: Run tests + Pint**

Run: `docker compose exec -T app php artisan test --filter="CalendarioUnificadoTest|CalendarioRotaTest|CalendarioStubTest|CalendarioSeoTest|CalendarioFeedTest|PalestrasArchiveSeoTest|PalestrantePerfilRedesignTest"`
Expected: PASS. Depois: `docker compose exec -T app ./vendor/bin/pint --dirty`.

- [ ] **Step 10: Commit**

```bash
git add routes/web.php app/Http/Controllers/CalendarioController.php resources/views/calendario.blade.php resources/views/sitemap.blade.php resources/views/agenda/index.blade.php resources/views/conta/painel.blade.php resources/views/palestras/index.blade.php resources/views/palestrantes/index.blade.php resources/views/palestrantes/perfil/hero.blade.php tests/Feature/Front/CalendarioUnificadoTest.php tests/Feature/Front/CalendarioRotaTest.php tests/Feature/Front/CalendarioStubTest.php tests/Feature/Front/CalendarioSeoTest.php tests/Feature/Front/PalestrasArchiveSeoTest.php tests/Feature/Front/PalestrantePerfilRedesignTest.php
git commit -m "feat(calendario): rota /calendario + 301 + SEO/cache + sitemap + relink"
```

---

### Task 6: View completa do calendário (hero adaptável, grid multi-dia, lista unificada) + CSS + selo de visibilidade

**Files:**
- Modify: `resources/views/livewire/calendario/calendario.blade.php` (substitui o esqueleto da Task 4 pela UI completa + botão/modal Assinar)
- Modify: `app/Livewire/Calendario/Calendario.php` (`render()` passa `feedsAssinar` derivado de `$tipo`)
- Create: `resources/views/components/ui/selo-visibilidade.blade.php` (`@props(['rotulo','cor'])` — ponto na cor do enum + rótulo em **texto neutro escuro**; §9.3, cor corrigida no passe)
- Create: `resources/views/components/ui/assinar-modal.blade.php` (`@props(['feeds'])` genérico — clone do comportamento do de palestras: Google/Apple/baixar **por feed**)
- Create: `resources/css/calendario.css` + `@import './calendario.css';` em `resources/css/app.css`
- Delete (código morto após a unificação): `app/Livewire/Palestras/Calendario.php`, `resources/views/livewire/palestras/calendario.blade.php`, `resources/views/palestras/calendario.blade.php`, e — **só depois** que o modal genérico + botão existirem — `resources/views/components/palestras/assinar-modal.blade.php`
- Migrar/reapontar testes de componente/modal: `tests/Feature/Front/CalendarioComponentTest.php` (usa `Livewire::test(Palestras\Calendario::class)` → migrar asserções úteis, ex.: `viewData('proxima')`, p/ `CalendarioLivewireTest` e apagar), `tests/Feature/Front/AssinarModalTest.php` (reapontar de `<x-palestras.assinar-modal :feed-url>` p/ `<x-ui.assinar-modal :feeds>`)
- **INTOCADOS** (não migrar/apagar): `tests/Feature/Front/CalendarioFeedTest.php` (feed `.ics`) e `tests/Feature/Front/CalendarioPalestraTest.php` — este, **apesar do nome**, testa `palestras.evento-ics` (o `.ics` de UMA palestra + escape RFC 5545), que esta fatia não toca. *(Opcional, fora desta fatia: renomear p/ `PalestraIcsTest` num commit próprio.)*
- **NÃO tocar** `resources/views/components/eventos/assinar-modal.blade.php` (3b, usado em `eventos/index` e `eventos/show`). **Follow-up fora desta fatia:** consolidar os 3 modais num só `<x-ui.assinar-modal>`.
- Test: estende `tests/Feature/Calendario/CalendarioLivewireTest.php`

**Interfaces:**
- Consumes: variáveis da `render()` (Task 4): `proxima`, `ocorrenciasDoMes` (DTOs), `matriz` (dias com `ocorrencias`/`ancora`), `contagem`, `tipo`, `feedsAssinar` (novo), etc. `<x-ui.countdown :data>`, `x-ui.particulas`, classes `cema-cal-*`/`cema-row` (já existem em `palestras-calendario.css`).
- Produces: UI final; `<x-ui.selo-visibilidade :rotulo :cor />`; `<x-ui.assinar-modal :feeds>`; `Calendario::render()` passa `feedsAssinar` (`list<array{rotulo:string,url:string}>`).

- [ ] **Step 1: Escrever/estender o teste (UI unificada + selo)**

Adicionar a `CalendarioLivewireTest`:

```php
public function test_selo_de_visibilidade_so_para_logado_em_evento_restrito(): void
{
    $this->semear();
    // anônimo não vê o restrito (logo, nem o selo)
    Livewire::test(Calendario::class)->assertDontSee('Somente diretoria');
    // diretor vê o card restrito COM o selo
    Livewire::actingAs($this->diretor())->test(Calendario::class)
        ->assertSee('Reunião Secreta')->assertSee('Somente diretoria');
}

public function test_contador_do_mes_respeita_visibilidade_e_pluraliza_em_pt(): void
{
    $this->semear(); // 1 evento público + 1 evento restrito no mesmo mês (+ 1 palestra)
    // anônimo (tipo=eventos): só o público conta
    Livewire::test(Calendario::class)->set('tipo', 'eventos')->assertSee('1 item');
    // diretor: os DOIS eventos contam → "2 itens". Asserção POSITIVA pega o bug do Str::plural (inglês → "2 items").
    Livewire::actingAs($this->diretor())->test(Calendario::class)->set('tipo', 'eventos')->assertSee('2 itens');
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CalendarioLivewireTest`
Expected: FAIL (selo/contador ainda não renderizados).

- [ ] **Step 3: Componente do selo**

`resources/views/components/ui/selo-visibilidade.blade.php`:

```blade
{{-- Selo discreto de visibilidade: ponto na cor do enum + rótulo em texto neutro ESCURO. --}}
{{-- text-text-ink (#26242e) sobre bg-surface (#f6f6f6) ≈ 14:1 (WCAG AA ok em 11px). --}}
{{-- NÃO usar text-text-muted (#7a8a8a): dá ~3,3:1 e reprova. O ponto colorido é decorativo. --}}
@props(['rotulo', 'cor'])
<span class="inline-flex items-center gap-1.5 rounded-pill bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-text-ink">
    <span class="inline-block size-2 rounded-full" style="background: {{ $cor }}" aria-hidden="true"></span>
    {{ $rotulo }}
</span>
```

- [ ] **Step 4: Modal genérico `<x-ui.assinar-modal>`** (decisão do dono: manter Assinar; 1 feed quando filtrado, 2 quando `todos`)

`resources/views/components/ui/assinar-modal.blade.php` (clona o comportamento do de palestras, mas por lista de feeds):

```blade
{{-- Modal "Assinar calendário": 1+ feeds. Abre no evento de janela `open-assinar`. --}}
@props(['feeds']) {{-- feeds: list<array{rotulo:string,url:string}> --}}
<div x-data="{ aberto:false, abre(){ this.aberto=true; $nextTick(()=>$refs.dlg?.showModal()); }, fecha(){ this.aberto=false; $refs.dlg?.close(); } }"
     x-on:open-assinar.window="abre()">
    <dialog x-ref="dlg" x-on:close="aberto=false" x-on:click.self="fecha()" role="dialog" aria-modal="true"
            aria-labelledby="assinar-cal-titulo"
            class="cema-modal m-auto w-[min(92vw,460px)] rounded-2xl border border-border-muted bg-white p-0 text-text-ink backdrop:bg-black/50">
        <div class="p-6 sm:p-7">
            <div class="flex items-start justify-between gap-4">
                <h2 id="assinar-cal-titulo" class="font-display text-xl font-semibold text-primary">Assinar calendário</h2>
                <button type="button" x-on:click="fecha()" aria-label="Fechar" class="shrink-0 rounded-full p-1.5 text-text-muted transition hover:bg-surface hover:text-text-ink">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke-linecap="round"/></svg>
                </button>
            </div>
            @foreach ($feeds as $feed)
                @php
                    $parts = parse_url($feed['url']);
                    $webcal = 'webcal://'.($parts['host'] ?? request()->getHost()).($parts['path'] ?? '');
                    $google = 'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcal);
                @endphp
                <div class="mt-5">
                    @if (count($feeds) > 1)
                        <p class="mb-2 font-mono text-[11px] uppercase tracking-[0.12em] text-text-muted">{{ $feed['rotulo'] }}</p>
                    @endif
                    <div class="flex flex-col gap-2.5">
                        <a href="{{ $google }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">📅</span> Google Calendar</a>
                        <a href="{{ $webcal }}" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">🍎</span> Apple Calendar</a>
                        <a href="{{ $feed['url'].'?download=1' }}" class="flex items-center gap-3 rounded-xl border border-border-muted bg-surface px-4 py-3 font-medium transition hover:border-primary"><span class="grid size-9 place-items-center rounded-lg bg-white text-lg" aria-hidden="true">⬇️</span> Baixar .ics</a>
                    </div>
                </div>
            @endforeach
            <p class="mt-4 text-xs text-text-muted">No Google, "assinar por URL" só sincroniza em produção (o Google não alcança o localhost).</p>
        </div>
    </dialog>
</div>
```

- [ ] **Step 5: `render()` passa `feedsAssinar` derivado de `$tipo`**

Em `app/Livewire/Calendario/Calendario.php`, adicionar à lista de dados de `view('livewire.calendario.calendario', [...])`:

```php
'feedsAssinar' => match ($this->tipo) {
    'palestras' => [['rotulo' => 'Palestras', 'url' => route('palestras.calendario-ics')]],
    'eventos' => [['rotulo' => 'Eventos', 'url' => route('eventos.feed-ics')]],
    default => [
        ['rotulo' => 'Palestras', 'url' => route('palestras.calendario-ics')],
        ['rotulo' => 'Eventos', 'url' => route('eventos.feed-ics')],
    ],
},
```

> O botão "Assinar calendário" fica no cabeçalho da barra de período (dentro do Livewire, p/ reagir ao `$tipo`) e dispara `$dispatch('open-assinar')`; o `<x-ui.assinar-modal :feeds="$feedsAssinar" />` é incluído no fim da view. Assim, ao trocar o filtro, os feeds do modal se ajustam sozinhos.

- [ ] **Step 6: View completa do Livewire**

Reescrever `resources/views/livewire/calendario/calendario.blade.php` a partir do molde `resources/views/livewire/palestras/calendario.blade.php`, generalizado:

- **Hero "Próxima ocorrência"** (`@if ($proxima)`): adapta por `$proxima->tipo` — palestra usa avatar (`$proxima->imagem`/`$proxima->iniciais`) + subtítulo; evento usa flyer/ícone + selo de status. Ambos: chip de data (ou "dia inteiro" se `! $proxima->temHora`), `<x-ui.countdown :data="$proxima->inicio" />`, botão "Ver".
- **Barra:** abas Próximas/Realizadas (como hoje) **+ filtro de tipo** (pills Todos/Palestras/Eventos via `wire:click="$set('tipo', '...')"`) + navegação de mês + seletor de ano + botão **"Assinar calendário"** (`x-data` + `x-on:click="$dispatch('open-assinar')"`).
- **Mini-grid** (multi-dia): para cada célula com `ocorrencias`, botão aceso; até 2 pontinhos coloridos (`corAcento` de cada tipo, no máximo 2); `x-on:click` rola até `#linha-{ancora}` (mesmo efeito `.is-destaque` de hoje). Legenda: Palestra (dourado) · Evento (cor de acento) · Hoje.
- **Lista do mês:** cabeçalho com o mês + `{{ $contagem }} {{ $contagem === 1 ? 'item' : 'itens' }}` (**não** `Str::plural`, que pluraliza em **inglês** → "items"). Cada `$oc`: `wire:key="{{ $oc->chave }}"`, `id="linha-{{ $oc->chave }}"`, `href="{{ $oc->url }}"`; chip de data (dia + hora, ou "dia inteiro"); badge de tipo (Palestra/Evento — cor por tipo); selo de status (`$oc->selo`); **selo de visibilidade** `@if ($oc->seloVisibilidade) <x-ui.selo-visibilidade :rotulo="$oc->seloVisibilidade['rotulo']" :cor="$oc->seloVisibilidade['cor']" /> @endif`; título; subtítulo; imagem/iniciais quando houver.
- **Estados vazios** por mês e total (sensíveis a `modo`/`tipo`).
- **Modal** (fim da view): `<x-ui.assinar-modal :feeds="$feedsAssinar" />`.

Snippet-chave do grid multi-dia (célula):

```blade
@foreach ($matriz['dias'] as $celula)
    @if (! empty($celula['ocorrencias']))
        <button type="button" wire:key="dia-{{ $celula['dia'] }}"
                class="cema-cal-day cema-cal-day--com-palestra @if ($celula['hoje']) cema-cal-day--hoje @endif"
                aria-label="{{ $celula['dia'] }}: {{ collect($celula['ocorrencias'])->pluck('titulo')->join('; ') }}"
                x-data
                x-on:click="
                    const alvo = document.getElementById('linha-{{ $celula['ancora'] }}');
                    if (alvo) { alvo.scrollIntoView({behavior:'smooth', block:'center'}); alvo.classList.add('is-destaque'); setTimeout(() => alvo.classList.remove('is-destaque'), 1900); }
                ">
            <span>{{ $celula['dia'] }}</span>
            <span class="cema-cal-dots" aria-hidden="true">
                @foreach (array_slice(collect($celula['ocorrencias'])->unique('tipo')->values()->all(), 0, 2) as $pt)
                    <span class="cema-cal-dot" style="background: {{ $pt['cor'] }}"></span>
                @endforeach
            </span>
        </button>
    @else
        <span wire:key="dia-{{ $celula['dia'] }}" class="cema-cal-day @if ($celula['hoje']) cema-cal-day--hoje @endif">{{ $celula['dia'] }}</span>
    @endif
@endforeach
```

- [ ] **Step 7: CSS novo** (`resources/css/calendario.css`) — só o que o molde de palestras não cobre: `.cema-cal-dots` (posicionamento dos pontinhos na célula) e `.cema-cal-dot`. Reaproveitar `cema-cal-day*`, `cema-row`, `.is-destaque`, `cema-chip-data--*` do `palestras-calendario.css` (não duplicar). Adicionar `@import './calendario.css';` em `resources/css/app.css`.

- [ ] **Step 8: Migrar os testes de componente/modal e só então apagar o código morto**

1. **Antes de apagar**, migrar as asserções ainda válidas de `CalendarioComponentTest` (ex.: `Livewire::test(...)->assertViewHas('proxima')`, marcação de dia no mini-grid) para `CalendarioLivewireTest` (agora sobre `App\Livewire\Calendario\Calendario`).
2. `AssinarModalTest`: reapontar de `<x-palestras.assinar-modal :feed-url="$feedUrl" />` para `<x-ui.assinar-modal :feeds="[['rotulo' => 'Palestras', 'url' => $feed]]" />`, mantendo as asserções (`webcal://…`, `…/calendario.ics?download=1`).
3. **Só então** `git rm`: `App\Livewire\Palestras\Calendario`, `resources/views/livewire/palestras/calendario.blade.php`, `resources/views/palestras/calendario.blade.php`, `resources/views/components/palestras/assinar-modal.blade.php`, `tests/Feature/Front/CalendarioComponentTest.php`. **NÃO** apagar `CalendarioPalestraTest` (testa `palestras.evento-ics`, intocado) nem `CalendarioFeedTest`.
4. Confirmar (grep) que `livewire:palestras.calendario`, `x-palestras.assinar-modal` e `Palestras\\Calendario` não aparecem mais em `resources/` nem `tests/`.

- [ ] **Step 9: Suíte INTEIRA + build**

Run: `docker compose exec -T app php artisan test` (**sem `--filter`** — é o que pega qualquer teste órfão restante). Depois `docker compose exec -T app ./vendor/bin/pint --test`. No host: `npm run build`; depois `docker compose restart app worker`.
Expected: verde (reexecutar os 2 flaky de GD do blog se aparecerem).

- [ ] **Step 10: Commit**

```bash
git add resources/views/livewire/calendario/calendario.blade.php app/Livewire/Calendario/Calendario.php resources/views/components/ui/selo-visibilidade.blade.php resources/views/components/ui/assinar-modal.blade.php resources/css/calendario.css resources/css/app.css tests/Feature/Calendario/CalendarioLivewireTest.php tests/Feature/Front/AssinarModalTest.php
git rm app/Livewire/Palestras/Calendario.php resources/views/livewire/palestras/calendario.blade.php resources/views/palestras/calendario.blade.php resources/views/components/palestras/assinar-modal.blade.php tests/Feature/Front/CalendarioComponentTest.php
git commit -m "feat(calendario): UI unificada + modal assinar generico + selo visibilidade; remove calendario de palestras"
```

---

### Task 7: Selo de visibilidade na lista de `/eventos`

**Files:**
- Modify: `resources/views/components/evento/card.blade.php` (selo p/ logado quando `visibilidade !== Publico`)
- Test: `tests/Feature/Front/EventoListaTest.php` (ou `EventoArchiveTest.php`) — estende com o caso do selo

**Interfaces:**
- Consumes: `<x-ui.selo-visibilidade>` (Task 6); `App\Enums\VisibilidadeEvento` (`rotulo()`, `cor()`, `Publico`).
- Produces: card de evento com selo de visibilidade só para logado + restrito.

- [ ] **Step 1: Write the failing test**

```php
public function test_card_mostra_selo_de_visibilidade_so_para_logado(): void
{
    Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
    $u = User::factory()->create();
    $u->assignRole('diretor');
    Evento::create(['titulo' => 'Reunião', 'slug' => 'reuniao', 'data_inicio' => Carbon::now()->addDays(5)->toDateString(),
        'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);

    // anônimo nem vê o card (filtrado) — sem selo
    $this->get('/eventos')->assertDontSee('Somente diretoria');
    // diretor vê o card com o selo
    $this->actingAs($u)->get('/eventos')->assertSee('Somente diretoria');
}
```

> Garantir os `use` no teste: `VisibilidadeEvento`, `User`, `Role`, `Carbon`.

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec -T app php artisan test --filter="EventoListaTest::test_card_mostra_selo_de_visibilidade_so_para_logado"`
Expected: FAIL (selo ausente).

- [ ] **Step 3: Adicionar o selo ao card**

Em `resources/views/components/evento/card.blade.php`, dentro do corpo do card (perto do título/rodapé), acrescentar:

```blade
@auth
    @if ($evento->visibilidade !== \App\Enums\VisibilidadeEvento::Publico)
        <x-ui.selo-visibilidade :rotulo="$evento->visibilidade->rotulo()" :cor="$evento->visibilidade->cor()" />
    @endif
@endauth
```

- [ ] **Step 4: Run to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=EventoListaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/evento/card.blade.php tests/Feature/Front/EventoListaTest.php
git commit -m "feat(eventos): selo de visibilidade no card da lista (logado + restrito)"
```

---

### Fechamento da fatia (verificação)

- [ ] **Step 1:** `docker compose exec -T app php artisan test` (suíte inteira verde; reexecutar os 2 flaky de GD do blog se aparecerem).
- [ ] **Step 2:** `docker compose exec -T app ./vendor/bin/pint --test`. No host: `npm run build`.
- [ ] **Step 3: Conferência real** — `docker compose restart app worker` (OPcache). Abrir `/calendario`: hero/countdown da próxima ocorrência; abas Próximas×Realizadas; **filtro Todos/Palestras/Eventos**; navegação de mês + ano; mini-grid acendendo (inclusive **multi-dia** — criar no admin um evento de 3 dias) e o clique rolando para a linha; lista intercalada; **logar como diretor** e ver um evento restrito com o **selo** (e some ao deslogar); `/palestra_publica/calendario` redireciona (301) para `/calendario`; `/calendario?tipo=palestras` chega pré-filtrado; `/sitemap.xml` tem `/calendario`; mobile (grid empilha, sticky ok).

---

## Notas de verificação do plano (self-review)

- **Cobertura do spec:** DTO+fontes (§4 → Tasks 1–3); componente/filtro/matriz multi-dia (§5 → Task 4); rotas/301/controller/SEO/cache/sitemap/links (§8 → Task 5); UI/anatomia/selo (§7, §2-selo → Tasks 6–7); visibilidade em toda query de Evento (§2, §6.2 → Tasks 3,4,5 com testes anon×diretor em fonte, componente, contador e JSON-LD); Agenda como fonte futura (§4.2 → interface pronta, **não** implementada). **Fora de escopo** respeitado: sem `.ics` unificado, sem Agenda, sem restrição de criação.
- **Armadilhas (§6):** multi-dia (Task 1 `diasNoMes` + Task 3 overlap + Task 4 grid) com testes; vazamento de visibilidade coberto em 4 níveis (fonte, componente/contador, JSON-LD do controller); UNION evitado (merge em PHP); filtro (Task 4); rota/links órfãos (Task 5 Step 7 grep + `route:list`); SEO/canonical/sitemap (Task 5); hero por filtro ativo (Task 4 `proxima` sobre fontes ativas); cache logado (Task 5 + teste "sem public").
- **Consistência de tipos:** `FonteCalendario::{meses,ocorrencias,proxima}` idênticos nas duas fontes e no componente; `OcorrenciaCalendario` com os mesmos campos usados por fontes (Tasks 2–3), componente (Task 4) e view (Task 6); `selo` = `['rotulo','cor','cor_texto']` (status) e `seloVisibilidade` = `['rotulo','cor']` (enum) — nomes estáveis entre Task 3 (produz) e Tasks 4/6 (consomem). `<x-ui.selo-visibilidade :rotulo :cor />` idêntico em Tasks 6 e 7.
- **Passe do dono incorporado (2026-07-09, 1º e 2º):** (1) blast-radius de testes **completo e corrigido no 2º passe** — **7 afetados** (`CalendarioComponentTest`, `CalendarioRotaTest`, `CalendarioSeoTest`, `CalendarioStubTest`, `AssinarModalTest`, `PalestrasArchiveSeoTest`, `PalestrantePerfilRedesignTest`) tratados nas Tasks 5–6 + **2 INTOCADOS** (`CalendarioFeedTest` e `CalendarioPalestraTest` — este testa `palestras.evento-ics`, não o calendário), com **suíte inteira sem `--filter`** no fechamento; (2) regressão do `.ics` de palestras resolvida — modal genérico `<x-ui.assinar-modal :feeds>` + botão no calendário **antes** de apagar `components/palestras/assinar-modal` (e `x-eventos.assinar-modal` intocado; follow-up: consolidar os 3); (3) contador pt-BR sem `Str::plural` (`'item'|'itens'`) + teste que **assere** "2 itens" p/ diretor; (4) selo com `text-text-ink` (WCAG AA). Menores: `JSON_HEX_TAG` no JSON-LD; fuso explícito em `diasNoMes`/`EventosFonte`; docblock do `fim`; pré-requisito 3b registrado.
- **§9 resolvido no passe:** assinar → **manter** com modal de 1/2 feeds; menu → **não mexer** nesta fatia (alcançada pelos 6 links); selo → ponto + texto **escuro**; dois-tipos-no-mesmo-dia → **aprovado**; realizadas por `COALESCE(data_fim,data_inicio) < hoje` → **confirmado**.
- **Placeholders:** nenhum passo com "TODO/etc."; código real nos passos de suporte (Tasks 1–5,7); a view grande (Task 6) segue o padrão do projeto (molde `palestras/calendario` citado com linhas + snippets concretos do que muda), como nos planos anteriores de Blade.
