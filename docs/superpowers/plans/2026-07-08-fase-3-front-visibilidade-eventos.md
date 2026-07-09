# Eventos — Fase 3 (Front público + Visibilidade) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publicar o front do módulo Eventos — arquivo `/eventos` e single `/eventos/{slug}` (SSR + Livewire), com visibilidade por papel aplicada desde a query, "Adicionar ao Google Calendar"/`.ics`, SEO (`schema.org/Event` + `BreadcrumbList`), 301 do legado, sitemap e ativação do menu.

**Architecture:** Clona a fatia pública de **Palestras** (controller + Livewire `Lista` + Blade + `FeedIcs` + modal assinar + slot `$head`). O front **já nasce filtrado por visibilidade**: toda query usa `Evento::scopeVisiveisPara($user)`; `show`/`.ics` autorizam com `abort_unless(...->podeSerVistoPor($user), 404)` (404, não 403). Selos de categoria (cor do banco `categorias_evento.cor`) e de status/contagem regressiva (`App\Support\Eventos\StatusEvento`). Endereço da sede consolidado em `config('cema.endereco')` (fonte única).

**Tech Stack:** PHP 8.3 · Laravel 13 · Livewire 3 · Blade SSR · Tailwind v4 (`@theme` em `resources/css/app.css`) · spatie/laravel-permission (`roles.nivel`).

> **✅ Fatiado em 2 PRs (decisão do dono):**
> - **3a (PR 1) = Tasks 1–8.** Recortes: `EventoController` tem **só** `index()` e `show()` (SEM `feed()`/`calendario()`, e **não** criar as rotas `.ics`); o CTA **"Adicionar à agenda"** entra na 3a (link Google Calendar via `Evento::inicioUtc()/fimUtc()`, não depende do `FeedIcs`); **NÃO** incluir `<x-eventos.assinar-modal>` na single/archive (ela chama `route('eventos.feed-ics')` e quebraria com `RouteNotFoundException`).
> - **3b (PR 2) = Task 9.** `FeedIcs` + adicionar `feed()`/`calendario()` ao controller + as rotas `.ics` + o `<x-eventos.assinar-modal>` na single + `Cache-Control: private, no-store` no `calendario()` restrito.

## Global Constraints

- **Idioma:** tudo em pt-BR (labels, mensagens, comentários, commits).
- **Visibilidade é fundação, não acabamento:** **toda** consulta pública nasce de `Evento::scopeVisiveisPara($user)`; `show`/`calendario` fazem `abort_unless($evento->podeSerVistoPor($user), 404)` (**404, nunca 403** — não vazar existência); restritos ficam **fora** do sitemap e do feed `.ics` público; single restrita responde `Cache-Control: private`. `administrador` (nível 100) vê tudo.
- **Datas/horas:** `data_inicio`/`data_fim` são `Y-m-d` (mutator Attribute → Carbon na leitura, string crua em `getRawOriginal`); `hora_inicio`/`hora_fim` são `HH:MM` string. Fuso `America/Sao_Paulo`. Sem `hora_inicio` = "dia inteiro".
- **Endereço da sede:** **fonte única** `config('cema.endereco')` (novo `config/cema.php`); grafia canônica `"Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF"`. **Não** criar 5ª cópia.
- **`resumo` no front:** para `meta description` e cards, usar **texto puro** (`strip_tags` + limite) **na view**, não no model (o model mantém HTML sanitizado).
- **Tokens Tailwind já existem** (`gold`/`footer-bg`/`text-ink`/Roboto Mono no `@theme`). Cor de categoria vem de `categorias_evento.cor` (inline). Só **adicionar `@import './eventos.css';`** em `resources/css/app.css` (após os imports existentes).
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08` em todo PHP novo.
- **Ferramentas no container:** `docker compose exec -T app php artisan ...` / `./vendor/bin/pint`. **`npm`/Vite rodam no HOST** (o container não tem Node): após criar `eventos.css`/editar views que exigem build, `npm run build` no host + `docker compose restart app worker` (OPcache) para conferência no navegador.
- **Pint** antes do push; suíte no container; commits atômicos pt-BR na branch `eventos-fase-3-front`.

## Design de referência (do handoff `design_handoff_eventos/`)

- **Archive `/eventos`** (screenshots `01`–`04-eventos.png`): (1) hero roxo `linear-gradient(150deg,#4E4483,#2f2952)` (breadcrumb, kicker font-mono "PROGRAMAÇÃO DO CEMA", H1 "Eventos"); (2) **"Próximo destaque"** sobre creme `#F3EDDD` — card 2 colunas raio 22px (flyer c/ 2 selos + título/excerpt/data-hora-local + CTAs "Ver evento" e "Adicionar à agenda"), **fora da grade**; (3) barra de filtros sobre `#F6F6F6` (abas Próximos×Já aconteceram c/ sublinhado `gold`; busca pílula; `<select>` mês; chips de categoria; contador font-mono); (4) grade `repeat(auto-fill,minmax(290px,1fr))` gap 24px de **cards**.
- **Card** (raio 16px, borda `#EBE8E8`): faixa flyer 188px (170px em relacionados) c/ **selo de categoria** (topo-esq, cor do banco) + **selo de status** (topo-dir); título Work Sans 600; metadados (data/horário/local, ícones stroke verde `#89AB98`); card inteiro é `<a>`; hover `-translate-y-1` + sombra. Passado: flyer `grayscale(.55)` + selo "Encerrado".
- **Single `/eventos/{slug}`** (screenshots `05`–`07`): hero c/ breadcrumb `Início › Eventos › {título}` (**último nó = título**, não categoria) + par de selos + H1 + resumo; barra de ações (Facebook/WhatsApp/Copiar/"Adicionar à agenda"); corpo `repeat(auto-fit,minmax(290px,1fr))` — esquerda: `lead` + parágrafos + bloco **"Serviço"** (pares: Data, Horário, Local, Endereço [config], Categoria, Departamento); direita: `<aside sticky top-90>` com flyer + data/local + CTAs; **"Outros eventos"** (≤3 relacionados). Galeria só se houver.
- **Selo de categoria:** pílula font-mono, `background: {categoria.cor}`, texto branco (exceto `campanha #F2A81E` → texto `#3a3266`, via `categorias_evento.cor_texto`).
- **Selo de status** (regra exata — `App\Support\Eventos\StatusEvento`): `dias = (início-do-dia do evento) − (início-do-dia de hoje)`:
  - `hoje > data_fim` (coalescida a `data_inicio`) → **"Encerrado"** `#2f2952` (flyer grayscale)
  - `data_fim > data_inicio` E `data_inicio ≤ hoje ≤ data_fim` → **"Acontecendo agora"** `#C33A36` (só multi-dia)
  - `dias ≤ 0` → **"É hoje"** `#C33A36` · `dias == 1` → **"É amanhã"** `#E79048` · `2–7` → **"Faltam N dias"** `#E79048` · `>7` → **"Em N dias"** `#89AB98`
- **"Adicionar à agenda":** link Google Calendar `render?action=TEMPLATE&text=…&dates={ini}/{fim}&details={url}&location={local — endereço}`; sem `hora_fim` → duração **+2h**.
- **Estado vazio:** caixa tracejada "Nenhum evento encontrado" + "Ajuste a busca ou os filtros para ver outros eventos.".

---

### Task 1: `config/cema.php` + consolidar o endereço da sede (fonte única)

**Files:**
- Create: `config/cema.php`
- Modify: `app/Support/Palestras/FeedIcs.php` (const → config)
- Modify: `resources/views/palestras/show.blade.php` (JSON-LD address → config)
- Modify: `resources/views/palestras/calendario.blade.php` (JSON-LD address → config)
- Modify: `resources/views/components/layout/footer.blade.php` (grafia divergente → config)
- Test: `tests/Unit/ConfigCemaTest.php`

**Interfaces:**
- Produces: `config('cema.endereco')` = `'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF'`; `config('cema.nome')` = `'Centro Espírita Maria Madalena'`. Consumido por `FeedIcs` (Task 9), as views de Eventos (Tasks 7–8) e as views/const de Palestras (consolidadas aqui).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit;

use Tests\TestCase;

class ConfigCemaTest extends TestCase
{
    public function test_endereco_e_nome_da_sede(): void
    {
        $this->assertSame('Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF', config('cema.endereco'));
        $this->assertSame('Centro Espírita Maria Madalena', config('cema.nome'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=ConfigCemaTest`
Expected: FAIL (`config('cema.endereco')` é null).

- [ ] **Step 3: Create the config**

`config/cema.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

return [
    // Identidade institucional — fonte ÚNICA (não duplicar em views/const).
    'nome' => 'Centro Espírita Maria Madalena',
    'endereco' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF',
];
```

- [ ] **Step 4: Consolidar as 4 cópias hardcoded**

1. `app/Support/Palestras/FeedIcs.php` — remover a const `LOCAL_PRESENCIAL` e trocar seu uso (`$p->online ? 'Online — YouTube' : self::LOCAL_PRESENCIAL`) por:
   ```php
   $local = $p->online ? 'Online — YouTube' : config('cema.nome').' — '.config('cema.endereco');
   ```
2. `resources/views/palestras/show.blade.php` (linha ~17, JSON-LD): `'address' => 'Quadra…DF'` → `'address' => config('cema.endereco'),`.
3. `resources/views/palestras/calendario.blade.php` (linha ~16): `'address' => 'Quadra…DF'` → `'address' => config('cema.endereco'),` e `'name' => 'Centro…'` → `'name' => config('cema.nome'),`.
4. `resources/views/components/layout/footer.blade.php` (linha ~58): trocar o texto hardcoded "Quadra 02, Lote 16, Vila Vicentina — Planaltina, DF · CNPJ…" por `{{ config('cema.endereco') }} · CNPJ 01.600.089/0001-90` (corrige a grafia divergente do travessão).

- [ ] **Step 5: Verify + Pint + commit**

Run: `docker compose exec -T app php artisan test --filter="ConfigCemaTest|FeedIcs|Palestra"` (o `ConfigCemaTest` passa; os testes de Palestras/`FeedIcs` continuam verdes com o endereço vindo do config).
`docker compose exec -T app ./vendor/bin/pint config/cema.php app/Support/Palestras/FeedIcs.php`.

```bash
git add config/cema.php app/Support/Palestras/FeedIcs.php resources/views/palestras/show.blade.php resources/views/palestras/calendario.blade.php resources/views/components/layout/footer.blade.php tests/Unit/ConfigCemaTest.php
git commit -m "feat(eventos): config('cema.endereco') como fonte unica do endereco da sede"
```

---

### Task 2: Autorização por visibilidade (`User::nivelMaximo`, `Evento::podeSerVistoPor`/`scopeVisiveisPara`, `EventoPolicy`, `VisibilidadeEvento::cor`)

**Files:**
- Modify: `app/Models/User.php` (adicionar `nivelMaximo()`)
- Modify: `app/Enums/VisibilidadeEvento.php` (adicionar `cor()`; opcional p/ badge de admin — mantém consistência)
- Modify: `app/Models/Evento.php` (adicionar `podeSerVistoPor()` + `scopeVisiveisPara()`)
- Create: `app/Policies/EventoPolicy.php`
- Test: `tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php`

**Interfaces:**
- Produces: `User::nivelMaximo(): int` (`(int) $this->roles->max('nivel')`, 0 se sem roles); `Evento::podeSerVistoPor(?User): bool`; `Evento::scopeVisiveisPara(Builder, ?User): Builder`; `EventoPolicy@view/viewAny` (auto-descoberta por convenção — sem registro manual). Consumido por `EventoController` (Task 4), `Livewire\Eventos\Lista` (Task 5), `SitemapController`/feed (Tasks 9).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisibilidadeEventoAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['frequentador' => 10, 'trabalhador' => 20, 'diretor' => 30, 'administrador' => 100] as $slug => $nivel) {
            Role::updateOrCreate(['name' => $slug, 'guard_name' => 'web'], ['nivel' => $nivel]);
        }
    }

    private function usuario(?string $papel): ?User
    {
        if ($papel === null) {
            return null;
        }
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u;
    }

    private function evento(VisibilidadeEvento $vis, string $slug = 'e'): Evento
    {
        return Evento::create([
            'titulo' => 'E', 'slug' => $slug, 'data_inicio' => '2026-06-27',
            'visibilidade' => $vis, 'status' => Evento::STATUS_PUBLICADO,
        ]);
    }

    public function test_matriz_pode_ser_visto_por(): void
    {
        $niveis = [
            'publico' => VisibilidadeEvento::Publico,
            'logados' => VisibilidadeEvento::Logados,
            'trabalhadores' => VisibilidadeEvento::Trabalhadores,
            'diretoria' => VisibilidadeEvento::Diretoria,
        ];
        // [papel => [publico, logados, trabalhadores, diretoria]]
        $esperado = [
            null => [true, false, false, false],           // anônimo
            'frequentador' => [true, true, false, false],
            'trabalhador' => [true, true, true, false],
            'diretor' => [true, true, true, true],
            'administrador' => [true, true, true, true],    // vê tudo
        ];

        foreach ($esperado as $papel => $linha) {
            $u = $this->usuario($papel);
            $i = 0;
            foreach ($niveis as $vis) {
                $evento = $this->evento($vis, "e-{$papel}-{$i}");
                $this->assertSame($linha[$i], $evento->podeSerVistoPor($u), "papel={$papel} vis={$vis->value}");
                $i++;
            }
        }
    }

    public function test_scope_visiveis_para_filtra_no_banco(): void
    {
        $this->evento(VisibilidadeEvento::Publico, 'pub');
        $this->evento(VisibilidadeEvento::Diretoria, 'dir');

        $this->assertSame(1, Evento::visiveisPara(null)->count());               // anônimo só o público
        $this->assertSame(2, Evento::visiveisPara($this->usuario('diretor'))->count());
        $this->assertSame(2, Evento::visiveisPara($this->usuario('administrador'))->count());
        $this->assertSame(1, Evento::visiveisPara($this->usuario('frequentador'))->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeEventoAcessoTest`
Expected: FAIL (`nivelMaximo`/`podeSerVistoPor`/`scopeVisiveisPara` inexistentes).

- [ ] **Step 3: `User::nivelMaximo()`**

Em `app/Models/User.php`, adicionar o método:

```php
/** Maior nível entre os papéis do usuário (roles.nivel); 0 se não tiver papel. */
public function nivelMaximo(): int
{
    return (int) $this->roles->max('nivel');
}
```

- [ ] **Step 4: `Evento::podeSerVistoPor()` + `scopeVisiveisPara()`**

Em `app/Models/Evento.php`, adicionar (importar `use App\Models\User;` e `use Illuminate\Database\Eloquent\Builder;` se necessário):

```php
/** Regra de visibilidade por papel (fonte única). Admin (nível 100) satisfaz qualquer >=. */
public function podeSerVistoPor(?User $usuario): bool
{
    $nivel = $usuario?->nivelMaximo() ?? 0;

    return match ($this->visibilidade) {
        VisibilidadeEvento::Publico => true,
        VisibilidadeEvento::Logados => $usuario !== null,
        VisibilidadeEvento::Trabalhadores => $usuario !== null && $nivel >= VisibilidadeEvento::Trabalhadores->nivelMinimo(),
        VisibilidadeEvento::Diretoria => $usuario !== null && $nivel >= VisibilidadeEvento::Diretoria->nivelMinimo(),
    };
}

/** Filtra no banco os eventos que o usuário (ou anônimo) pode ver — não vaza títulos restritos. */
public function scopeVisiveisPara(Builder $query, ?User $usuario): Builder
{
    $nivel = $usuario?->nivelMaximo() ?? 0;

    return $query->where(function (Builder $q) use ($usuario, $nivel) {
        $q->where('visibilidade', VisibilidadeEvento::Publico->value);
        if ($usuario !== null) {
            $q->orWhere('visibilidade', VisibilidadeEvento::Logados->value);
            if ($nivel >= VisibilidadeEvento::Trabalhadores->nivelMinimo()) {
                $q->orWhere('visibilidade', VisibilidadeEvento::Trabalhadores->value);
            }
            if ($nivel >= VisibilidadeEvento::Diretoria->nivelMinimo()) {
                $q->orWhere('visibilidade', VisibilidadeEvento::Diretoria->value);
            }
        }
    });
}
```

- [ ] **Step 5: `EventoPolicy` (auto-descoberta) + `VisibilidadeEvento::cor()`**

`app/Policies/EventoPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Policies;

use App\Models\Evento;
use App\Models\User;

/**
 * Policy parcial (só view/viewAny) — segura porque o Filament NÃO usa strict authorization
 * (isAuthorizationStrict = false por padrão; o projeto não altera), então create/update/delete
 * no /admin seguem permitidos. ⚠️ Se um dia ligarem strictAuthorization, esta policy parcial
 * passará a lançar LogicException nos métodos ausentes — adicione-os então.
 */
class EventoPolicy
{
    /** Delegada à regra única do model; $user é null-safe (visitante anônimo passa por Gate::forUser(null)). */
    public function view(?User $user, Evento $evento): bool
    {
        return $evento->podeSerVistoPor($user);
    }

    public function viewAny(?User $user): bool
    {
        return true; // a listagem filtra por scopeVisiveisPara; não há bloqueio geral
    }
}
```

Em `app/Enums/VisibilidadeEvento.php`, adicionar (para badges/consistência do admin/front):

```php
public function cor(): string
{
    return match ($this) {
        self::Publico => '#89AB98',
        self::Logados => '#6E9FCB',
        self::Trabalhadores => '#E79048',
        self::Diretoria => '#C33A36',
    };
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=VisibilidadeEventoAcessoTest` (+ `VisibilidadeEventoTest` do enum segue verde). Pint nos arquivos tocados.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php app/Models/Evento.php app/Enums/VisibilidadeEvento.php app/Policies/EventoPolicy.php tests/Feature/Eventos/VisibilidadeEventoAcessoTest.php
git commit -m "feat(eventos): autorizacao por visibilidade (nivelMaximo, podeSerVistoPor, scopeVisiveisPara, EventoPolicy)"
```

---

### Task 3: `App\Support\Eventos\StatusEvento` (selo de status/contagem regressiva)

**Files:**
- Create: `app/Support/Eventos/StatusEvento.php`
- Modify: `app/Models/Evento.php` (accessor `status_selo` + `ehPassado`)
- Test: `tests/Unit/Support/Eventos/StatusEventoTest.php`

**Interfaces:**
- Produces: `StatusEvento::para(?string $dataInicio, ?string $dataFim, ?CarbonInterface $hoje = null): array` → `['estado' => 'futuro'|'acontecendo'|'passado', 'rotulo' => string, 'cor' => string(hex)]`. `Evento::getStatusSeloAttribute(): array` e `Evento::getEhPassadoAttribute(): bool`. Consumido pelos cards/hero (Tasks 7–8) e pelas queries de "próximos/anteriores" (Task 4/5).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Support\Eventos;

use App\Support\Eventos\StatusEvento;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class StatusEventoTest extends TestCase
{
    private string $hoje = '2026-06-27';

    private function status(string $ini, ?string $fim = null): array
    {
        // Mesmo fuso do StatusEvento (senão o offset de 3h mascara erros de limite de dia).
        return StatusEvento::para($ini, $fim, Carbon::parse($this->hoje, StatusEvento::FUSO));
    }

    public function test_encerrado_quando_fim_passou(): void
    {
        $s = $this->status('2026-06-20', '2026-06-25');
        $this->assertSame('passado', $s['estado']);
        $this->assertSame('Encerrado', $s['rotulo']);
    }

    public function test_acontecendo_agora_so_multi_dia_em_curso(): void
    {
        $s = $this->status('2026-06-26', '2026-06-28'); // hoje 27, dentro do intervalo
        $this->assertSame('acontecendo', $s['estado']);
        $this->assertSame('Acontecendo agora', $s['rotulo']);
    }

    public function test_evento_de_um_dia_hoje_e_e_hoje_nao_acontecendo(): void
    {
        $s = $this->status('2026-06-27', '2026-06-27'); // 1 dia, hoje
        $this->assertSame('É hoje', $s['rotulo']); // NÃO "Acontecendo agora"
    }

    public function test_amanha_faltam_e_em_n_dias(): void
    {
        $this->assertSame('É amanhã', $this->status('2026-06-28')['rotulo']);
        $this->assertSame('Faltam 5 dias', $this->status('2026-07-02')['rotulo']);
        $this->assertSame('Em 30 dias', $this->status('2026-07-27')['rotulo']);
    }

    public function test_cor_texto_para_contraste(): void
    {
        $this->assertSame('#FFFFFF', $this->status('2026-06-20', '2026-06-25')['cor_texto']); // Encerrado (fundo escuro)
        $this->assertSame('#26242E', $this->status('2026-06-28')['cor_texto']);               // É amanhã (fundo claro)
        $this->assertSame('#26242E', $this->status('2026-07-27')['cor_texto']);               // Em N dias (fundo claro)
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=StatusEventoTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Create the class**

`app/Support/Eventos/StatusEvento.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Support\Eventos;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Selo de status / contagem regressiva de um evento, a partir de data_inicio/data_fim (Y-m-d).
 * Regras do design (§07): Encerrado / Acontecendo agora (só multi-dia) / É hoje / É amanhã /
 * Faltam N dias / Em N dias. Datas comparadas por dia (start-of-day), fuso America/Sao_Paulo.
 */
class StatusEvento
{
    public const FUSO = 'America/Sao_Paulo';

    /** @return array{estado:string,rotulo:string,cor:string,cor_texto:string} */
    public static function para(?string $dataInicio, ?string $dataFim, ?CarbonInterface $hoje = null): array
    {
        $hoje = ($hoje ? $hoje->copy() : Carbon::today(self::FUSO))->startOfDay();
        $inicio = Carbon::parse((string) $dataInicio, self::FUSO)->startOfDay();
        $fim = Carbon::parse((string) ($dataFim ?: $dataInicio), self::FUSO)->startOfDay();

        if ($hoje->greaterThan($fim)) {
            return ['estado' => 'passado', 'rotulo' => 'Encerrado', 'cor' => '#2f2952', 'cor_texto' => '#FFFFFF'];
        }

        // Só multi-dia em curso vira "Acontecendo agora" (evento de 1 dia hoje cai em "É hoje").
        if ($fim->greaterThan($inicio) && $hoje->betweenIncluded($inicio, $fim)) {
            return ['estado' => 'acontecendo', 'rotulo' => 'Acontecendo agora', 'cor' => '#C33A36', 'cor_texto' => '#FFFFFF'];
        }

        $dias = (int) $hoje->diffInDays($inicio, false); // início − hoje

        // cor_texto garante contraste WCAG AA: branco nos fundos escuros; tinta #26242E nos claros (#E79048/#89AB98).
        return match (true) {
            $dias <= 0 => ['estado' => 'futuro', 'rotulo' => 'É hoje', 'cor' => '#C33A36', 'cor_texto' => '#FFFFFF'],
            $dias === 1 => ['estado' => 'futuro', 'rotulo' => 'É amanhã', 'cor' => '#E79048', 'cor_texto' => '#26242E'],
            $dias <= 7 => ['estado' => 'futuro', 'rotulo' => "Faltam {$dias} dias", 'cor' => '#E79048', 'cor_texto' => '#26242E'],
            default => ['estado' => 'futuro', 'rotulo' => "Em {$dias} dias", 'cor' => '#89AB98', 'cor_texto' => '#26242E'],
        };
    }
}
```

- [ ] **Step 4: Accessors no `Evento`**

Em `app/Models/Evento.php`, adicionar (usam os valores crus Y-m-d):

```php
/** @return array{estado:string,rotulo:string,cor:string,cor_texto:string} */
public function getStatusSeloAttribute(): array
{
    return \App\Support\Eventos\StatusEvento::para($this->attributes['data_inicio'] ?? null, $this->attributes['data_fim'] ?? null);
}

public function getEhPassadoAttribute(): bool
{
    return $this->status_selo['estado'] === 'passado';
}

/** Instante de início em UTC (para Google Calendar/ICS com hora); dia inteiro → 00:00. */
public function inicioUtc(): Carbon
{
    $data = $this->attributes['data_inicio'];
    $hora = ($this->hora_inicio ?? '') !== '' ? $this->hora_inicio : '00:00';

    return Carbon::parse("{$data} {$hora}", 'America/Sao_Paulo')->utc();
}

/** Instante de fim em UTC: hora_fim quando há hora; senão início + 2h. */
public function fimUtc(): Carbon
{
    if (($this->hora_inicio ?? '') !== '' && ($this->hora_fim ?? '') !== '') {
        $dataFim = ($this->attributes['data_fim'] ?? null) ?: $this->attributes['data_inicio'];

        return Carbon::parse("{$dataFim} {$this->hora_fim}", 'America/Sao_Paulo')->utc();
    }

    return $this->inicioUtc()->addHours(2);
}
```

> `inicioUtc()`/`fimUtc()` (no model, Task 3) são a **fonte única** dos instantes usada pelo botão Google Calendar da single (Task 6) e por `FeedIcs` no ramo com hora (Task 9) — evitando dependência de ordem entre elas.

- [ ] **Step 5: Run test + Pint + commit**

Run: `docker compose exec -T app php artisan test --filter=StatusEventoTest`. Pint.

```bash
git add app/Support/Eventos/StatusEvento.php app/Models/Evento.php tests/Unit/Support/Eventos/StatusEventoTest.php
git commit -m "feat(eventos): StatusEvento (selo de status/contagem regressiva)"
```

---

### Task 4: Rotas + 301 + `EventoController` (index, show, feed, calendario)

**Files:**
- Modify: `routes/web.php` (rotas de eventos + 301 do `/_evento`)
- Create: `app/Http/Controllers/EventoController.php`
- Test: `tests/Feature/Front/EventoRotasTest.php`

**Interfaces:**
- Consumes: `Evento` (scope `visiveisPara`, `podeSerVistoPor`), `StatusEvento::FUSO`.
- Produces (3a): rotas `eventos.index`/`eventos.show` + 301; `EventoController` com `index()`/`show()`. Consumido pela Livewire `Lista` (Task 5) e pelas views (Tasks 7–8). As rotas/métodos `.ics` vêm na Task 9 (3b).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoRotasTest extends TestCase
{
    use RefreshDatabase;

    private function evento(array $o = []): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'Brechó', 'slug' => 'brecho', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    public function test_archive_200(): void
    {
        $this->evento();
        $this->get('/eventos')->assertOk()->assertSee('Eventos');
    }

    public function test_single_publico_200(): void
    {
        $this->evento();
        $this->get('/eventos/brecho')->assertOk()->assertSee('Brechó');
    }

    public function test_single_restrito_404_para_anonimo(): void
    {
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria]);
        $this->get('/eventos/reservado')->assertNotFound(); // 404, não 403
    }

    public function test_single_restrito_visivel_para_diretor(): void
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');
        $this->evento(['slug' => 'reservado', 'visibilidade' => VisibilidadeEvento::Diretoria, 'titulo' => 'Reunião']);

        $this->actingAs($u)->get('/eventos/reservado')->assertOk()->assertSee('Reunião');
    }

    public function test_301_do_legado(): void
    {
        $this->evento();
        $this->get('/_evento')->assertRedirect('/eventos');
        $this->get('/_evento/brecho')->assertStatus(301);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventoRotasTest`
Expected: FAIL (rotas/controller inexistentes).

- [ ] **Step 3: Criar o controller**

`app/Http/Controllers/EventoController.php` (métodos `index`/`show` completos; `feed`/`calendario` chamam `FeedIcs` — se executar 3b depois, começar com `abort(404)` e completar na Task 9):

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Support\Eventos\StatusEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EventoController extends Controller
{
    /** Arquivo /eventos: hero + "Próximo destaque" (fora da grade) + Livewire da grade. */
    public function index(Request $request)
    {
        $usuario = $request->user();

        // Destaque = próximo evento FUTURO visível mais próximo (independe dos filtros).
        $destaque = Evento::query()
            ->publicado()
            ->visiveisPara($usuario)
            ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [now(StatusEvento::FUSO)->toDateString()])
            ->with(['categoria', 'departamentos'])
            ->orderBy('data_inicio')
            ->first();

        return view('eventos.index', ['destaque' => $destaque]);
    }

    public function show(Request $request, string $slug)
    {
        $usuario = $request->user();

        $evento = Evento::query()->publicado()
            ->with(['categoria', 'departamentos'])
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($evento->podeSerVistoPor($usuario), 404); // 404, não 403 (não vaza existência)

        // Relacionados: mesma categoria, visíveis, futuros primeiro; fallback p/ quaisquer visíveis.
        $rel = Evento::query()->publicado()->visiveisPara($usuario)
            ->where('id', '!=', $evento->id)
            ->when($evento->categoria_evento_id, fn (Builder $q) => $q->where('categoria_evento_id', $evento->categoria_evento_id))
            ->with('categoria')
            ->orderByRaw('COALESCE(data_fim, data_inicio) >= ? DESC', [now(StatusEvento::FUSO)->toDateString()])
            ->orderBy('data_inicio')
            ->take(3)->get();

        if ($rel->count() < 3) {
            $exclui = $rel->pluck('id')->push($evento->id)->all();
            $rel = $rel->concat(
                Evento::query()->publicado()->visiveisPara($usuario)
                    ->whereNotIn('id', $exclui)->with('categoria')
                    ->orderBy('data_inicio')->take(3 - $rel->count())->get()
            );
        }

        $resposta = response()->view('eventos.show', ['evento' => $evento, 'relacionados' => $rel]);

        // Single restrita não pode ficar em cache compartilhado.
        if ($evento->visibilidade !== \App\Enums\VisibilidadeEvento::Publico) {
            $resposta->header('Cache-Control', 'private, no-store');
        }

        return $resposta;
    }
}
```

> **3a:** o controller tem SÓ `index()` e `show()`. `feed()`/`calendario()` (+ imports `FeedIcs`/`Response`) são adicionados na Task 9 (3b).

- [ ] **Step 4: Rotas + 301** — em `routes/web.php`, no bloco de rotas públicas (estáticas antes de `{slug}`), adicionar (importar `use App\Http\Controllers\EventoController;`):

```php
Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');
Route::get('/eventos/{slug}', [EventoController::class, 'show'])
    ->name('eventos.show')->where('slug', '[a-z0-9-]+');

// Compat 301 das URLs antigas do WP (/_evento e /_evento/{slug}).
Route::permanentRedirect('/_evento', '/eventos');
Route::get('/_evento/{slug}', fn (string $slug) => redirect()->route('eventos.show', ['slug' => $slug], 301))
    ->where('slug', '[a-z0-9-]+');
```

> **3a:** só `/eventos` e `/eventos/{slug}` + 301. As rotas `.ics` (`eventos.feed-ics`, `eventos.evento-ics`) entram na Task 9 (3b) — a `calendario.ics` deve ser inserida **antes** de `/eventos/{slug}`.

> Nesta task já são necessárias as **views** `eventos.index` e `eventos.show` para os testes de rota passarem — elas são criadas nas Tasks 7–8. **Se executar sequencialmente**, criar aqui um esqueleto mínimo das duas (só o `<x-layout.app>` com H1 "Eventos" / o título do evento) e enriquecê-las nas Tasks 7–8; ou reordenar para criar as views antes. O plano assume o esqueleto mínimo aqui e o conteúdo completo nas Tasks 7–8 (os testes de rota deste passo checam status/`assertSee` do título, que o esqueleto já satisfaz).

- [ ] **Step 5: Esqueleto mínimo das views (para os testes de rota)**

`resources/views/eventos/index.blade.php` (mínimo — completado na Task 8):
```blade
<x-layout.app title="Eventos" description="Programação de eventos do CEMA.">
    <h1 class="sr-only">Eventos</h1>
    <div class="mx-auto max-w-[1240px] px-4 py-10"><h2>Eventos</h2></div>
</x-layout.app>
```
`resources/views/eventos/show.blade.php` (mínimo — completado na Task 7):
```blade
<x-layout.app :title="$evento->titulo" :description="\Illuminate\Support\Str::limit(strip_tags((string) $evento->resumo), 155)">
    <div class="mx-auto max-w-[1240px] px-4 py-10"><h1>{{ $evento->titulo }}</h1></div>
</x-layout.app>
```

- [ ] **Step 6: Run test + Pint + commit**

Run: `docker compose exec -T app php artisan test --filter=EventoRotasTest`. Pint.

```bash
git add routes/web.php app/Http/Controllers/EventoController.php resources/views/eventos/index.blade.php resources/views/eventos/show.blade.php tests/Feature/Front/EventoRotasTest.php
git commit -m "feat(eventos): rotas /eventos + 301 do legado + EventoController (visibilidade no show/feed)"
```

---

### Task 5: `App\Livewire\Eventos\Lista` (grade filtrável) + view

**Files:**
- Create: `app/Livewire/Eventos/Lista.php`
- Create: `resources/views/livewire/eventos/lista.blade.php`
- Create: `resources/views/components/evento/card.blade.php` (componente `<x-evento.card>` — SINGULAR, convenção do projeto: `components/palestra/card`, `components/blog/card`; reaproveitado pela grade e por "Outros eventos")
- Create: `resources/css/eventos.css` (+ `@import` em `app.css`)
- Test: `tests/Feature/Front/EventoListaTest.php`

**Interfaces:**
- Consumes: `Evento::scopeVisiveisPara`, `StatusEvento` (selo via accessor `status_selo`), `CategoriaEvento` (chips).
- Produces: componente Livewire `eventos.lista` (props `#[Url]`: `q`, `mes`, `categoria`, `aba`; prop `destaqueId` para excluir o destaque da grade "Próximos"). Consumido pela casca `eventos/index.blade` (Task 8).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Livewire\Eventos\Lista;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventoListaTest extends TestCase
{
    use RefreshDatabase;

    private function ev(string $slug, string $dataInicio, ?int $catId = null, VisibilidadeEvento $v = VisibilidadeEvento::Publico): Evento
    {
        return Evento::create([
            'titulo' => ucfirst($slug), 'slug' => $slug, 'data_inicio' => $dataInicio,
            'categoria_evento_id' => $catId, 'visibilidade' => $v, 'status' => Evento::STATUS_PUBLICADO,
        ]);
    }

    public function test_abas_particionam_futuros_e_passados(): void
    {
        $this->ev('futuro', now()->addDays(10)->toDateString());
        $this->ev('passado', now()->subDays(10)->toDateString());

        Livewire::test(Lista::class)
            ->assertSee('Futuro')->assertDontSee('Passado')   // aba padrão = próximos
            ->set('aba', 'anteriores')
            ->assertSee('Passado')->assertDontSee('Futuro');
    }

    public function test_filtro_categoria_e_busca(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $this->ev('brecho-junho', now()->addDays(5)->toDateString(), $cat->id);
        $this->ev('feirao-maio', now()->addDays(6)->toDateString());

        Livewire::test(Lista::class)
            ->set('categoria', 'brecho')->assertSee('Brecho-junho')->assertDontSee('Feirao-maio')
            ->set('categoria', '')->set('q', 'feirao')->assertSee('Feirao-maio')->assertDontSee('Brecho-junho');
    }

    public function test_exclui_destaque_da_grade(): void
    {
        $d = $this->ev('destaque', now()->addDays(1)->toDateString());
        $this->ev('outro', now()->addDays(2)->toDateString());

        Livewire::test(Lista::class, ['destaqueId' => $d->id])
            ->assertDontSee('Destaque')->assertSee('Outro');
    }

    public function test_restrito_nao_aparece_para_anonimo(): void
    {
        $this->ev('reservado', now()->addDays(3)->toDateString(), null, VisibilidadeEvento::Diretoria);
        $this->ev('aberto', now()->addDays(3)->toDateString());

        Livewire::test(Lista::class)->assertSee('Aberto')->assertDontSee('Reservado');
    }

    public function test_estado_vazio(): void
    {
        Livewire::test(Lista::class)->assertSee('Nenhum evento encontrado');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventoListaTest`
Expected: FAIL (componente inexistente).

- [ ] **Step 3: Criar o componente Livewire**

`app/Livewire/Eventos/Lista.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Livewire\Eventos;

use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Lista extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'mes', except: '')]
    public string $mes = ''; // 'AAAA-MM'

    #[Url(as: 'categoria', except: '')]
    public string $categoria = '';

    #[Url(as: 'aba', except: 'proximos')]
    public string $aba = 'proximos'; // proximos | anteriores

    /** id do evento em destaque (excluído da grade de "próximos" para não duplicar). */
    public ?int $destaqueId = null;

    public function updated(string $name): void
    {
        if (in_array($name, ['q', 'mes', 'categoria', 'aba'], true)) {
            $this->resetPage();
        }
    }

    private function baseVisivel(): Builder
    {
        return Evento::query()->publicado()->visiveisPara(auth()->user());
    }

    public function render()
    {
        $hoje = now('America/Sao_Paulo')->toDateString();

        $eventos = $this->baseVisivel()
            ->with(['categoria'])
            ->when($this->aba === 'anteriores',
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje])->orderByDesc('data_inicio'),
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje])
                    ->when($this->destaqueId, fn (Builder $b) => $b->where('id', '!=', $this->destaqueId))
                    ->orderBy('data_inicio'))
            ->when($this->q !== '', fn (Builder $q) => $q->where('titulo', 'like', '%'.$this->q.'%'))
            ->when($this->categoria !== '', fn (Builder $q) => $q->whereHas('categoria', fn (Builder $c) => $c->where('slug', $this->categoria)))
            ->when($this->mes !== '' && preg_match('/^\d{4}-\d{2}$/', $this->mes),
                fn (Builder $q) => $q->where('data_inicio', 'like', $this->mes.'-%'))
            ->paginate(9);

        return view('livewire.eventos.lista', [
            'eventos' => $eventos,
            'categorias' => CategoriaEvento::ativo()->orderBy('ordem')->get(['nome', 'slug', 'cor', 'cor_texto']),
            'meses' => $this->mesesDisponiveis(),
        ]);
    }

    /** Meses 'AAAA-MM' distintos existentes NA ABA corrente (o <select> não oferece mês que dá 0 resultado). */
    private function mesesDisponiveis(): array
    {
        $hoje = now('America/Sao_Paulo')->toDateString();

        return $this->baseVisivel()
            ->when($this->aba === 'anteriores',
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) < ?', [$hoje]),
                fn (Builder $q) => $q->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [$hoje]))
            ->pluck('data_inicio')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()->sortDesc()->values()->all();
    }
}
```

- [ ] **Step 4: View da grade + card + CSS**

`resources/views/livewire/eventos/lista.blade.php` — barra de filtros (abas `role=tab` com sublinhado `border-gold`; busca `wire:model.live.debounce.350ms="q"`; `<select wire:model.live="mes">` populado de `$meses`; chips `wire:click="$set('categoria', '<slug>')"` com "Todas"; contador font-mono `{{ $eventos->total() }} eventos`) + grade `grid grid-cols-[repeat(auto-fill,minmax(290px,1fr))] gap-6` com `@foreach ($eventos as $e) <x-evento.card :evento="$e" wire:key="ev-{{ $e->id }}" /> @endforeach` + paginação; **estado vazio** (`@if ($eventos->isEmpty())`) com a caixa tracejada "Nenhum evento encontrado" + "Ajuste a busca ou os filtros para ver outros eventos.". Use classes Tailwind dos tokens (`bg-surface`, `border-border-muted`, `rounded-pill`, `font-mono`, `text-primary`, `border-gold`). (Estrutura e medidas: molde `resources/views/livewire/palestras/lista.blade.php` + design §arquivo.)

`resources/views/components/evento/card.blade.php` — componente **anônimo** `<x-evento.card>` (`@props(['evento','compacto' => false])`): `<a href="{{ route('eventos.show', $evento->slug) }}">` envolvendo `<article>` (borda `#EBE8E8`, raio 16px, hover `-translate-y-1` + sombra); faixa do flyer (`{{ $evento->flyerUrl ?? asset('images/logos/logo-icone.png') }}` — **fallback padrão do projeto** (o `components/palestra/card` usa o mesmo; NÃO existe `placeholder-evento.png`); altura 188px ou 170px se `$compacto`, `loading=lazy`, `@class(['grayscale-[.55] opacity-90' => $evento->ehPassado])`); **selo de categoria** (só se `$evento->categoria`): `<span style="background: {{ $evento->categoria->cor }}; color: {{ $evento->categoria->cor_texto ?? '#fff' }}">{{ $evento->categoria->nome }}</span>` (pílula font-mono, topo-esq); **selo de status**: `@php($s = $evento->status_selo)` `<span style="background: {{ $s['cor'] }}; color: {{ $s['cor_texto'] }}">{{ $s['rotulo'] }}</span>` (topo-dir, **contraste WCAG via `cor_texto`**); corpo: `<h3>` título + metadados (período via `$evento->periodo`, local com fallback `{{ $evento->local ?: 'Local a confirmar' }}`, ícones SVG stroke `#89AB98`).

`resources/css/eventos.css` — estilos auxiliares (pulse do "Próximo destaque", `position:sticky` do aside da single, ajustes de selo). Adicionar `@import './eventos.css';` em `resources/css/app.css` (após os `@import` existentes, linhas ~8–12).

- [ ] **Step 5: Run test + build + Pint + commit**

Run: `docker compose exec -T app php artisan test --filter=EventoListaTest`. **No host:** `npm run build` (Vite; container não tem Node). Pint nos PHP.

```bash
git add app/Livewire/Eventos/Lista.php resources/views/livewire/eventos/lista.blade.php resources/views/eventos/_card.blade.php resources/css/eventos.css resources/css/app.css tests/Feature/Front/EventoListaTest.php
git commit -m "feat(eventos): Livewire Lista (grade filtravel por aba/busca/mes/categoria) + card"
```

---

### Task 6: Single `/eventos/{slug}` completa (hero, ações, 2 colunas, Serviço, galeria, Outros eventos, SEO, Google Calendar, assinar-modal)

**Files:**
- Modify: `resources/views/eventos/show.blade.php` (conteúdo completo, substitui o esqueleto)
- Create: `resources/views/eventos/_servico.blade.php`, `resources/views/eventos/_galeria.blade.php`, `resources/views/eventos/_relacionados.blade.php`
- Test: `tests/Feature/Front/EventoSingleSeoTest.php`
- **(3a) NÃO** criar/incluir `assinar-modal` aqui — ele chama `route('eventos.feed-ics')` (Task 9). Fica na 3b.

**Interfaces:**
- Consumes: `$evento`, `$relacionados` (do `EventoController::show`), `config('cema.endereco')`, `StatusEvento`, `PeriodoEvento`, `x-evento.card`.
- Produces: a single renderizada + JSON-LD `Event` + botão Google Calendar.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoSingleSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_tem_jsonld_event_e_google_calendar(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        Evento::create([
            'titulo' => 'Brechó Solidário', 'slug' => 'brecho', 'resumo' => '<p>Venha</p>',
            'data_inicio' => '2026-06-27', 'hora_inicio' => '08:30',
            'categoria_evento_id' => $cat->id, 'visibilidade' => VisibilidadeEvento::Publico,
            'status' => Evento::STATUS_PUBLICADO, 'local' => 'CEMA',
        ]);

        $r = $this->get('/eventos/brecho')->assertOk();
        $r->assertSee('schema.org', false);
        $r->assertSee('"@type":"Event"', false);
        $r->assertSee('calendar.google.com/calendar/render', false);
        $r->assertSee('Serviço');                       // bloco de serviço
        $r->assertSee(config('cema.endereco'));          // endereço da fonte única
        // meta description = resumo em texto puro (sem HTML)
        $r->assertSee('name="description"', false);
        $r->assertDontSee('<p>Venha</p>', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=EventoSingleSeoTest`
Expected: FAIL (esqueleto não tem JSON-LD/Serviço/Google Calendar).

- [ ] **Step 3: `eventos/show.blade.php` completa**

Estrutura (clonar de `resources/views/palestras/show.blade.php`), com um `@php` que monta o JSON-LD e o link do Google Calendar, e `<x-slot:head>` com o `Event`:

```blade
@php
    use Illuminate\Support\Str;
    $s = $evento->status_selo;
    $descricaoSeo = Str::limit(trim(strip_tags((string) $evento->resumo)), 155);
    $inicioIso = \Illuminate\Support\Carbon::parse($evento->getRawOriginal('data_inicio').' '.($evento->hora_inicio ?? '00:00'), \App\Support\Eventos\StatusEvento::FUSO)->toIso8601String();
    $jsonLd = json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $evento->titulo,
        'startDate' => $inicioIso,
        'eventStatus' => $evento->ehPassado ? 'https://schema.org/EventScheduled' : 'https://schema.org/EventScheduled',
        'location' => ['@type' => 'Place', 'name' => config('cema.nome'), 'address' => config('cema.endereco')],
        'organizer' => ['@type' => 'Organization', 'name' => 'CEMA'],
        'description' => $descricaoSeo ?: null,
        'image' => $evento->flyerUrl ?: null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

    $gcInicio = $evento->inicioUtc()->format('Ymd\THis\Z');
    $gcFim = $evento->fimUtc()->format('Ymd\THis\Z');
    $googleAgenda = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($evento->titulo)
        .'&dates='.$gcInicio.'/'.$gcFim
        .'&details='.urlencode(route('eventos.show', $evento->slug))
        .'&location='.urlencode(($evento->local ?: '').' — '.config('cema.endereco'));
@endphp

<x-layout.app :title="$evento->titulo" :description="$descricaoSeo">
    <x-slot:head>
        <script type="application/ld+json">{!! $jsonLd !!}</script>
        @if ($evento->flyerUrl)<meta property="og:image" content="{{ $evento->flyerUrl }}">@endif
    </x-slot:head>

    {{-- Hero: breadcrumb "Início › Eventos › {título}" + par de selos + H1 + resumo --}}
    {{-- Barra de ações: Facebook/WhatsApp/Copiar + "Adicionar à agenda" ($googleAgenda) --}}
    {{-- Corpo 2 colunas: esquerda (lead + parágrafos + @include('eventos._servico')) ;
         direita <aside class="sticky top-[90px]"> flyer + data/local + CTAs (Google Calendar + WhatsApp) --}}
    {{-- @if ($evento->getMedia('galeria')->isNotEmpty()) @include('eventos._galeria') @endif --}}
    {{-- @include('eventos._relacionados') (usa $relacionados, <x-evento.card :compacto="true">) --}}
    {{-- (3b) Modal assinar (Task 9): <x-eventos.assinar-modal :feedUrl="route('eventos.feed-ics')" /> — NÃO na 3a --}}
</x-layout.app>
```

`resources/views/eventos/_servico.blade.php` — bloco branco "Serviço" (eyebrow font-mono verde), grid `repeat(auto-fit,minmax(200px,1fr))` de pares rótulo/valor: **Data/Período** (`{{ $evento->periodo }}`), **Horário** (só se `$evento->hora_inicio`), **Local** (`{{ $evento->local ?: 'Local a confirmar' }}`), **Endereço** (`{{ config('cema.endereco') }}`), **Categoria** (`{{ $evento->categoria?->nome ?? '—' }}`), **Departamento(s)** (`{{ $evento->departamentos->pluck('sigla')->join(', ') ?: '—' }}`).

`resources/views/eventos/_galeria.blade.php` — grade de miniaturas `getMedia('galeria')` (WebP conversão `web`, `loading=lazy`).

`resources/views/eventos/_relacionados.blade.php` — H2 "Outros eventos" + link "Ver todos →" (`route('eventos.index')`); grade de até 3 `<x-evento.card :evento="$r" :compacto="true">`.

> **3a:** o CTA "Adicionar à agenda" (`$googleAgenda`) entra normalmente (não depende do `FeedIcs`). O `assinar-modal` (que aponta para `route('eventos.feed-ics')`) fica para a Task 9 (3b).

- [ ] **Step 4: Run test + build + Pint + commit**

Run: `docker compose exec -T app php artisan test --filter=EventoSingleSeoTest`. `npm run build` no host. Pint.

```bash
git add resources/views/eventos/show.blade.php resources/views/eventos/_servico.blade.php resources/views/eventos/_galeria.blade.php resources/views/eventos/_relacionados.blade.php tests/Feature/Front/EventoSingleSeoTest.php
git commit -m "feat(eventos): single completa (hero+selos+servico+galeria+relacionados+JSON-LD Event+Google Calendar)"
```

---

### Task 7: Archive `/eventos` completo (hero + "Próximo destaque" + BreadcrumbList + grade)

**Files:**
- Modify: `resources/views/eventos/index.blade.php` (conteúdo completo, substitui o esqueleto)
- Test: `tests/Feature/Front/EventoArchiveTest.php`

**Interfaces:**
- Consumes: `$destaque` (do `EventoController::index`), `x-evento.card`, `@livewire('eventos.lista', ['destaqueId' => $destaque?->id])`.
- Produces: o archive renderizado + JSON-LD `BreadcrumbList`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_mostra_destaque_e_breadcrumb(): void
    {
        Evento::create([
            'titulo' => 'Próximo Brechó', 'slug' => 'proximo-brecho',
            'data_inicio' => now()->addDays(3)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ]);

        $r = $this->get('/eventos')->assertOk();
        $r->assertSee('Próximo destaque');           // bloco de destaque presente
        $r->assertSee('Próximo Brechó');             // o evento futuro é o destaque
        $r->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_destaque_some_sem_evento_futuro(): void
    {
        Evento::create([
            'titulo' => 'Antigo', 'slug' => 'antigo', 'data_inicio' => now()->subDays(10)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO,
        ]);

        $this->get('/eventos')->assertOk()->assertDontSee('Próximo destaque');
    }
}
```

- [ ] **Step 2–4: Implementar, testar, commitar**

`resources/views/eventos/index.blade.php` completo: `<x-slot:head>` com JSON-LD `BreadcrumbList` (Início › Eventos); **hero roxo** (`bg-[linear-gradient(150deg,#4E4483,#2f2952)]`, kicker font-mono "PROGRAMAÇÃO DO CEMA", H1 "Eventos", breadcrumb); **bloco "Próximo destaque"** `@if ($destaque)` sobre `bg-cream` — card 2 colunas raio 22px (flyer + 2 selos via `x-evento.card`-like inline, título/`periodo`/local, CTAs "Ver evento" [`route('eventos.show', $destaque->slug)`] + "Adicionar à agenda" [link Google Calendar como na single]); `@livewire('eventos.lista', ['destaqueId' => $destaque?->id])`. Medidas/estilos: design §arquivo + molde `palestras/index.blade.php`.

Run: `docker compose exec -T app php artisan test --filter=EventoArchiveTest`. `npm run build` no host. Pint.

```bash
git add resources/views/eventos/index.blade.php tests/Feature/Front/EventoArchiveTest.php
git commit -m "feat(eventos): archive completo (hero + Proximo destaque + BreadcrumbList)"
```

---

### Task 8: Sitemap (só públicos) + ativar menu

**Files:**
- Modify: `app/Http/Controllers/SitemapController.php` (adicionar eventos públicos)
- Modify: `resources/views/sitemap.blade.php` (loop de eventos)
- Modify: `config/navegacao.php` (ativar item "Eventos")
- Test: `tests/Feature/Front/EventoSitemapTest.php`

**Interfaces:**
- Consumes: `Evento::scopeVisiveisPara(null)` (só o público visível ao anônimo entra no sitemap), `config/navegacao.php`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_inclui_publico_e_exclui_restrito(): void
    {
        Evento::create(['titulo' => 'Pub', 'slug' => 'pub-ev', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Publico, 'status' => Evento::STATUS_PUBLICADO]);
        Evento::create(['titulo' => 'Rest', 'slug' => 'rest-ev', 'data_inicio' => '2026-06-27',
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);

        $r = $this->get('/sitemap.xml')->assertOk();
        $r->assertSee(route('eventos.index'), false);
        $r->assertSee('/eventos/pub-ev', false);
        $r->assertDontSee('/eventos/rest-ev', false);   // restrito fora do sitemap
    }
}
```

- [ ] **Step 2–4: Implementar, testar, commitar**

- `SitemapController::index()`: adicionar `$eventos = Evento::publicado()->visiveisPara(null)->orderByDesc('data_inicio')->get(['slug', 'updated_at']);` e passar no `compact(...)`.
- `sitemap.blade.php`: adicionar a URL nua `route('eventos.index')` (changefreq weekly, priority 0.8) + `@foreach ($eventos as $ev)` com `route('eventos.show', $ev->slug)` (`lastmod` = `updated_at->toAtomString()`, monthly, 0.7).
- `config/navegacao.php`: trocar `['rotulo' => 'Eventos', 'ativo' => false, 'itens' => []]` por `['rotulo' => 'Eventos', 'rota' => 'eventos.index', 'ativo' => true, 'itens' => []]`.

Run: `docker compose exec -T app php artisan test --filter=EventoSitemapTest`. Pint.

```bash
git add app/Http/Controllers/SitemapController.php resources/views/sitemap.blade.php config/navegacao.php tests/Feature/Front/EventoSitemapTest.php
git commit -m "feat(eventos): eventos publicos no sitemap + item Eventos no menu"
```

---

### Task 9: `App\Support\Eventos\FeedIcs` (ICS) — completar feed/calendario

**Files (3b — PR 2):**
- Create: `app/Support/Eventos/FeedIcs.php`
- Create: `resources/views/components/eventos/assinar-modal.blade.php` (clone de `components/palestras/assinar-modal`, `@props(['feedUrl'])`, apontando p/ `eventos.feed-ics`)
- Modify: `app/Http/Controllers/EventoController.php` (adicionar `feed()`/`calendario()` + imports `FeedIcs`/`Response`; `calendario()` restrito → `Cache-Control: private, no-store`)
- Modify: `routes/web.php` (`eventos.feed-ics` **antes** de `/eventos/{slug}` + `eventos.evento-ics`)
- Modify: `resources/views/eventos/show.blade.php` (incluir `<x-eventos.assinar-modal :feedUrl="route('eventos.feed-ics')" />`)
- Test: `tests/Unit/Support/Eventos/FeedIcsTest.php` + `tests/Feature/Front/EventoIcsTest.php`

**Interfaces:**
- Consumes: `Evento` (data/hora crus, `titulo`, `slug`, `resumo`, `local`).
- Produces: `FeedIcs::documento(iterable): string`; `FeedIcs::vevento(Evento): array`; `FeedIcs::FUSO`. Usa `Evento::inicioUtc()/fimUtc()` (Task 3) no ramo com hora e `getRawOriginal` no dia inteiro (VALUE=DATE). Clona `escapar()`/`dobrar()` de `App\Support\Palestras\FeedIcs`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Support\Eventos;

use App\Models\Evento;
use App\Support\Eventos\FeedIcs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedIcsTest extends TestCase
{
    use RefreshDatabase;

    private function ev(array $o): Evento
    {
        return Evento::create(array_merge([
            'titulo' => 'E', 'slug' => 'e', 'data_inicio' => '2026-06-27', 'status' => Evento::STATUS_PUBLICADO,
        ], $o));
    }

    public function test_com_hora_usa_datetime_e_hora_fim(): void
    {
        $ics = FeedIcs::documento([$this->ev(['hora_inicio' => '08:00', 'hora_fim' => '12:00'])]);
        $this->assertStringContainsString('DTSTART:20260627T110000Z', $ics); // 08:00 SP = 11:00 UTC
        $this->assertStringContainsString('DTEND:20260627T150000Z', $ics);   // 12:00 SP = 15:00 UTC
    }

    public function test_sem_hora_fim_soma_2h(): void
    {
        $ics = FeedIcs::documento([$this->ev(['hora_inicio' => '08:00'])]);
        $this->assertStringContainsString('DTEND:20260627T130000Z', $ics);   // 08:00+2h = 10:00 SP = 13:00 UTC
    }

    public function test_dia_inteiro_value_date_com_dtend_exclusivo(): void
    {
        $ics = FeedIcs::documento([$this->ev(['data_fim' => '2026-06-29'])]); // sem hora → dia inteiro, 27→29
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260627', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260630', $ics); // data_fim + 1 dia (exclusivo)
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=FeedIcsTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Criar `FeedIcs`** (clone adaptado do de Palestras)

`app/Support/Eventos/FeedIcs.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Support\Eventos;

use App\Models\Evento;
use Illuminate\Support\Carbon;

final class FeedIcs
{
    public const PRODID = '-//CEMA//Eventos//PT-BR';

    public const FUSO = 'America/Sao_Paulo';

    /** Escapa valor para iCal (\, ; , e quebras de linha). */
    public static function escapar(string $v): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\r", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $v);
    }

    /** Dobra a linha lógica em ≤75 octetos (RFC 5545), sem partir UTF-8. */
    public static function dobrar(string $linha): string
    {
        if (strlen($linha) <= 75) {
            return $linha;
        }
        $saida = '';
        $atual = 0;
        foreach (mb_str_split($linha) as $ch) {
            $oct = strlen($ch);
            if ($atual + $oct > 75) {
                $saida .= "\r\n ";
                $atual = 1;
            }
            $saida .= $ch;
            $atual += $oct;
        }

        return $saida;
    }

    public static function temHora(Evento $e): bool
    {
        return $e->hora_inicio !== null && $e->hora_inicio !== '';
    }

    /** @return list<string> */
    public static function vevento(Evento $e): array
    {
        if (self::temHora($e)) {
            // Instantes vêm do model (fonte única compartilhada com o botão Google Calendar).
            $dt = [
                'DTSTART:'.$e->inicioUtc()->format('Ymd\THis\Z'),
                'DTEND:'.$e->fimUtc()->format('Ymd\THis\Z'),
            ];
        } else {
            // Dia inteiro: VALUE=DATE, DTEND exclusivo (data_fim + 1 dia).
            $ini = Carbon::parse($e->getRawOriginal('data_inicio'))->format('Ymd');
            $fimExcl = Carbon::parse($e->getRawOriginal('data_fim') ?: $e->getRawOriginal('data_inicio'))->addDay()->format('Ymd');
            $dt = ["DTSTART;VALUE=DATE:{$ini}", "DTEND;VALUE=DATE:{$fimExcl}"];
        }

        $descricao = trim(strip_tags((string) $e->resumo))."\n".route('eventos.show', $e->slug);
        $local = $e->local ?: config('cema.endereco');

        return array_merge(
            ['BEGIN:VEVENT', 'UID:evento-'.$e->id.'@cemanet.org.br'],
            $dt,
            [
                'SUMMARY:'.self::escapar($e->titulo),
                'DESCRIPTION:'.self::escapar($descricao),
                'LOCATION:'.self::escapar($local),
                'END:VEVENT',
            ]
        );
    }

    public static function documento(iterable $eventos): string
    {
        $linhas = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:'.self::PRODID,
            'X-WR-CALNAME:Eventos CEMA', 'X-WR-TIMEZONE:'.self::FUSO,
        ];
        foreach ($eventos as $e) {
            $linhas = array_merge($linhas, self::vevento($e));
        }
        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'dobrar'], $linhas))."\r\n";
    }
}
```

- [ ] **Step 4: `feed()`/`calendario()` no controller + rotas `.ics` + modal** — adicionar ao `EventoController` (imports `use App\Support\Eventos\FeedIcs;` e `use Illuminate\Http\Response;`):

```php
/** Feed .ics agregado: só eventos PÚBLICOS e não encerrados. */
public function feed(Request $request): Response
{
    $eventos = Evento::query()->publicado()
        ->where('visibilidade', \App\Enums\VisibilidadeEvento::Publico->value)
        ->whereRaw('COALESCE(data_fim, data_inicio) >= ?', [now(StatusEvento::FUSO)->toDateString()])
        ->orderBy('data_inicio')->get();

    $headers = ['Content-Type' => 'text/calendar; charset=utf-8'];
    if ($request->boolean('download')) {
        $headers['Content-Disposition'] = 'attachment; filename="cema-eventos.ics"';
    }

    return response(FeedIcs::documento($eventos), 200, $headers);
}

/** .ics de UM evento (autoriza como o show; restrito → 404 + Cache-Control private). */
public function calendario(Request $request, string $slug): Response
{
    $evento = Evento::query()->publicado()->where('slug', $slug)->firstOrFail();
    abort_unless($evento->podeSerVistoPor($request->user()), 404);

    $resposta = response(FeedIcs::documento([$evento]), 200, [
        'Content-Type' => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="evento-'.$evento->slug.'.ics"',
    ]);
    if ($evento->visibilidade !== \App\Enums\VisibilidadeEvento::Publico) {
        $resposta->header('Cache-Control', 'private, no-store');
    }

    return $resposta;
}
```
Rotas (**antes** de `/eventos/{slug}`): `Route::get('/eventos/calendario.ics', [EventoController::class, 'feed'])->name('eventos.feed-ics');` e `Route::get('/eventos/{slug}/calendario.ics', [EventoController::class, 'calendario'])->name('eventos.evento-ics')->where('slug', '[a-z0-9-]+');`. Criar `components/eventos/assinar-modal.blade.php` (clone) e incluí-lo na single.
Teste `EventoIcsTest`: `/eventos/calendario.ics` → 200 `text/calendar` só públicos futuros; `.ics` de restrito → **404** anônimo; restrito visível a diretor → `Cache-Control: private`. Rodar `--filter="FeedIcsTest|EventoIcsTest"`. Pint.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Eventos/FeedIcs.php app/Http/Controllers/EventoController.php routes/web.php resources/views/components/eventos/assinar-modal.blade.php resources/views/eventos/show.blade.php tests/Unit/Support/Eventos/FeedIcsTest.php tests/Feature/Front/EventoIcsTest.php
git commit -m "feat(eventos): FeedIcs (ICS: dia inteiro VALUE=DATE, DTEND+1, hora_fim/+2h) + feed/calendario + modal"
```

---

### Fechamento da Fase 3 (verificação)

- [ ] **Step 1:** `docker compose exec -T app php artisan test` (verde; reexecutar os 2 flaky de GD do blog se aparecerem).
- [ ] **Step 2:** `docker compose exec -T app ./vendor/bin/pint --test`. **No host:** `npm run build`.
- [ ] **Step 3: Conferência real** — `docker compose restart app worker` (OPcache) + rebuild Vite. Abrir `/eventos` (destaque, abas, busca, mês, chips, grade, estado vazio); uma single pública (selos, Serviço, galeria, Outros eventos, "Adicionar à agenda" abre o Google Calendar, `.ics` baixa); um evento **restrito** → **404 anônimo** e visível logado com papel; conferir `/sitemap.xml` (público sim, restrito não) e o item **Eventos** no menu.

---

## Notas de verificação do plano (self-review)

- **Cobertura (carry-forwards do dono + spec §6/§7/§10):** archive+single+ICS+JSON-LD (Tasks 5–7,9), 301 (Task 4), visibilidade por papel restrito=404/fora do sitemap+feed (Tasks 2,4,8,9), `resumo` strip_tags no front (Tasks 4,6), sitemap+nav (Task 8), endereço fonte única (Task 1). **Fora desta fase (Fase 4):** restrição de **quem CRIA** por visibilidade no admin + polish/testes de blindagem adicionais.
- **Visibilidade nasce na query:** `scopeVisiveisPara` em toda listagem/feed/destaque/relacionados; `podeSerVistoPor`→404 no `show`/`calendario`; sitemap e feed só `Publico`; single restrita `Cache-Control: private`. `EventoPolicy` por auto-descoberta (sem registro — confirmado que o app não tem `AuthServiceProvider`).
- **Correção do spec:** tokens Tailwind (`gold`/`footer-bg`/`text-ink`/Roboto Mono) **já existem** no `@theme` — nenhuma task de token; só `@import './eventos.css'`.
- **Ordem/dependências:** Task 4 (rotas) exige as views mínimas → esqueleto criado na própria Task 4; conteúdo completo nas Tasks 6–7. `FeedIcs` (Task 9) é chamado por `EventoController` (Task 4) — se fatiar em 3a/3b, os métodos `feed`/`calendario` começam como `abort(404)` na 3a e são completados na 3b.
- **Datas/ICS:** `getRawOriginal('data_inicio'/'data_fim')` dá as strings Y-m-d cruas (o accessor devolve Carbon); horas SP→UTC; dia inteiro `VALUE=DATE` com **DTEND = data_fim + 1 dia** (exclusivo). Testes cobrem hora×dia-inteiro×+2h.
- **Blade grande = spec estrutural + molde:** as views-casca (index/show) trazem o código dos blocos de lógica (JSON-LD, Google Calendar, `@php`) e a estrutura/medidas + o molde exato a clonar (`palestras/*`, design handoff); os partials pequenos (`_card`/`_servico`) e o CSS são criados no passo. Vite roda **no host**.
