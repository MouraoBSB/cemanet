# Redesign da single do Palestrante — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesenhar `/palestrantes/{slug}` na nova identidade (hero + stats reais + "Sobre" + grade de palestras filtrável/ordenável client-side + sidebar sticky), adicionando a coluna aditiva `chamada`.

**Architecture:** Página SSR (Blade) com filtro/ordenação client-side (Alpine, estado único). Controller enxuto delega estatísticas/áreas a um `ResumoPerfil` (PHP puro, portável). Reuso máximo: `<x-layout.app>`, `<x-ui.particulas>`, `<x-palestra.card>`, `cema-grad-{id%8}`, accessor `iniciais`, `foto_url`/`foto_thumb_url`. View dividida em parciais em `resources/views/palestrantes/perfil/`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 (Alpine do bundle) · Tailwind v4 · Vite · Docker (MySQL dev; SQLite `:memory:` nos testes).

## Global Constraints

- **Migração aditiva** só via `php artisan migrate` (forward). 🚫 **PROIBIDO** `migrate:fresh`/`migrate:refresh`/`db:wipe`/`migrate:reset`/seed destrutivo (dev tem 127 palestras + 44 posts + palestrantes).
- **Portabilidade SQLite:** distintos, contagens, `min(ano)` e ordenação **em PHP** (coleção). Nada de `selectRaw`/`YEAR()`/`DATE_FORMAT()`.
- **Rota/controller:** manter `show(string $slug)` + `Palestrante::query()->ativo()->where('slug',$slug)->firstOrFail()` (não binding implícito, não `{palestrante:slug}`).
- **Relações reais:** palestras do palestrante = `palestrasMinistradas()` (papel=palestrante), coluna de data `data_da_palestra`, temas = `assuntos` (belongsToMany). **Sem** taxonomia de "área".
- **Big-bang / degrada por registro:** esconder quando vazio (`chamada`, `bio`, `proxima`, contato); stats null-safe (`—`). Sem "em breve"/placeholder.
- **Cores das áreas:** rotação `assunto->id % 8` via `.cema-dot-{0..7}` (consistente hero/sidebar/filtro). **Não** o mapa fixo nome→cor do handoff.
- **Contato — não regredir:** preservar exibição de e-mail/telefone condicionada às flags `mostrar_email`/`mostrar_telefone` (há testes que asseguram isso).
- **Tailwind v4:** tokens via `var(--color-*)` / classes de token (`text-primary`, `bg-cream`…); nunca `theme()`; `text-text-ink` (não `text-text` = #000).
- **Testes:** `docker compose exec -T app php artisan test` (SQLite `:memory:`); por-task com `--filter`. Reflexo de Blade/PHP no dev: `docker compose restart app worker`. Build front: `npm run build` (host).
- **A11y:** `aria-pressed`/`aria-label`, `<label>` no select, foco visível; `<x-ui.particulas>` já cobre `prefers-reduced-motion`.
- **Autoria** nos PHP novos: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01`. **pt-BR** com acentos. Pint antes de cada commit. Commits terminam com `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.

---

### Task 1: Migração aditiva `chamada` + `$fillable` + campo no Filament

**Files:**
- Create: `database/migrations/2026_07_01_000001_add_chamada_to_palestrantes_table.php`
- Modify: `app/Models/Palestrante.php` (`$fillable`)
- Modify: `app/Filament/Resources/Palestrantes/PalestranteResource.php` (novo `TextInput`)
- Create: `tests/Feature/Models/PalestranteChamadaTest.php`
- Modify: `tests/Feature/Filament/PalestranteResourceTest.php` (1 método)

**Interfaces:**
- Produces: coluna `palestrantes.chamada` (string nullable); `Palestrante::$fillable` inclui `'chamada'`; campo Filament `chamada`.

- [ ] **Step 1: Escrever o teste do model (falha)**

`tests/Feature/Models/PalestranteChamadaTest.php`:
```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PalestranteChamadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coluna_chamada_existe(): void
    {
        $this->assertTrue(Schema::hasColumn('palestrantes', 'chamada'));
    }

    public function test_chamada_e_atribuivel_e_opcional(): void
    {
        $p = Palestrante::factory()->create(['chamada' => 'Trabalhador do bem.']);
        $this->assertSame('Trabalhador do bem.', $p->fresh()->chamada);

        $semChamada = Palestrante::factory()->create(['chamada' => null]);
        $this->assertNull($semChamada->fresh()->chamada);
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteChamadaTest`
Expected: FAIL (coluna inexistente / mass-assign).

- [ ] **Step 3: Criar a migração**

`database/migrations/2026_07_01_000001_add_chamada_to_palestrantes_table.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->string('chamada')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('palestrantes', function (Blueprint $table) {
            $table->dropColumn('chamada');
        });
    }
};
```

- [ ] **Step 4: Adicionar `chamada` ao `$fillable`**

Em `app/Models/Palestrante.php`, alterar o array `$fillable`:
```php
    protected $fillable = [
        'nome', 'slug', 'chamada', 'bio', 'email', 'telefone',
        'mostrar_email', 'mostrar_telefone', 'ativo',
    ];
```

- [ ] **Step 5: Rodar migração no dev (forward, incremental) e o teste**

Run:
```
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test --filter=PalestranteChamadaTest
```
Expected: migração aplica só a nova; testes PASS. (🚫 nunca `migrate:fresh`/`refresh`.)

- [ ] **Step 6: Escrever o teste do Filament (falha)**

> O arquivo real **já importa** `use Filament\Forms\Components\TextInput;` e `use Livewire\Livewire;` — não duplicar. E `TextInput::make('email')` está na `Section::make('Dados pessoais')` do `PalestranteResource` (é lá que o Step 8 insere o `chamada`).

Adicionar em `tests/Feature/Filament/PalestranteResourceTest.php`:
```php
    public function test_formulario_tem_campo_chamada_opcional(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->assertFormFieldExists('chamada', fn (TextInput $field): bool => ! $field->isRequired());
    }

    public function test_cria_palestrante_com_chamada(): void
    {
        Livewire::test(CreatePalestrante::class)
            ->fillForm([
                'nome' => 'Com Chamada',
                'slug' => 'com-chamada',
                'chamada' => 'Servindo desde a infância.',
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('palestrantes', [
            'slug' => 'com-chamada', 'chamada' => 'Servindo desde a infância.',
        ]);
    }
```

- [ ] **Step 7: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestranteResourceTest`
Expected: FAIL nos 2 novos (campo inexistente).

- [ ] **Step 8: Adicionar o `TextInput('chamada')` ao Resource**

Em `PalestranteResource.php`, dentro da `Section::make('Dados pessoais')`, após o `TextInput::make('email')`, acrescentar:
```php
                        TextInput::make('chamada')
                            ->label('Chamada (frase do hero)')
                            ->helperText('Frase curta exibida no topo do perfil. Opcional.')
                            ->maxLength(180)
                            ->columnSpan(2),
```

- [ ] **Step 9: Rodar os testes do Task 1**

Run: `docker compose exec -T app php artisan test --filter='PalestranteChamadaTest|PalestranteResourceTest'`
Expected: PASS. Rodar Pint: `docker compose exec -T app ./vendor/bin/pint app database tests`

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Models/Palestrante.php app/Filament/Resources/Palestrantes/PalestranteResource.php tests/Feature/Models/PalestranteChamadaTest.php tests/Feature/Filament/PalestranteResourceTest.php
git commit -m "feat(palestrante): coluna aditiva chamada + campo no Filament"
```

---

### Task 2: `ResumoPerfil` (estatísticas + áreas de atuação, PHP portável)

**Files:**
- Create: `app/Support/Palestrantes/ResumoPerfil.php`
- Create: `tests/Feature/Support/ResumoPerfilTest.php`

**Interfaces:**
- Consumes: `Illuminate\Support\Collection<int, App\Models\Palestra>` com `assuntos` carregados.
- Produces: `ResumoPerfil` com `totalPalestras():int`, `totalTemas():int`, `anoAtivoDesde():?int`, `percentualOnline():?int`, `areas():Collection<array{slug,nome,count,cor}>` (ordenada por `count` desc), `areasHero():Collection` (top-8). Constante `CHIPS_HERO = 8`.

- [ ] **Step 1: Escrever o teste (falha)**

`tests/Feature/Support/ResumoPerfilTest.php`:
```php
<?php

namespace Tests\Feature\Support;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Support\Palestrantes\ResumoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ResumoPerfilTest extends TestCase
{
    use RefreshDatabase;

    private function palestra(array $attrs, array $assuntos = []): Palestra
    {
        $p = Palestra::factory()->create($attrs);
        foreach ($assuntos as $a) {
            $p->assuntos()->attach($a);
        }

        return $p->load('assuntos');
    }

    public function test_totais_temas_ano_e_percentual(): void
    {
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $perdao = Assunto::factory()->create(['nome' => 'Perdão', 'slug' => 'perdao']);

        $palestras = new Collection([
            $this->palestra(['data_da_palestra' => '2024-03-10 19:30', 'online' => true], [$evangelho]),
            $this->palestra(['data_da_palestra' => '2022-08-01 19:30', 'online' => false], [$evangelho, $perdao]),
            $this->palestra(['data_da_palestra' => null, 'online' => true], [$perdao]),
        ]);

        $r = new ResumoPerfil($palestras);

        $this->assertSame(3, $r->totalPalestras());
        $this->assertSame(2, $r->totalTemas());
        $this->assertSame(2022, $r->anoAtivoDesde());
        $this->assertSame(67, $r->percentualOnline()); // 2 de 3 → 66.67 → 67
    }

    public function test_areas_com_contagem_cor_e_ordem(): void
    {
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $perdao = Assunto::factory()->create(['nome' => 'Perdão', 'slug' => 'perdao']);

        $palestras = new Collection([
            $this->palestra(['data_da_palestra' => '2024-01-01'], [$evangelho, $perdao]),
            $this->palestra(['data_da_palestra' => '2024-02-01'], [$evangelho]),
        ]);

        $areas = (new ResumoPerfil($palestras))->areas();

        $this->assertSame('evangelho', $areas->first()['slug']); // maior contagem primeiro
        $this->assertSame(2, $areas->first()['count']);
        $this->assertSame($evangelho->id % 8, $areas->first()['cor']);
        $this->assertEqualsCanonicalizing(['evangelho', 'perdao'], $areas->pluck('slug')->all());
    }

    public function test_null_safe_sem_palestras(): void
    {
        $r = new ResumoPerfil(new Collection());

        $this->assertSame(0, $r->totalPalestras());
        $this->assertSame(0, $r->totalTemas());
        $this->assertNull($r->anoAtivoDesde());
        $this->assertNull($r->percentualOnline()); // guarda de divisão por zero
        $this->assertTrue($r->areas()->isEmpty());
    }

    public function test_areas_hero_limita_top_8(): void
    {
        $palestras = new Collection();
        for ($i = 0; $i < 10; $i++) {
            $a = Assunto::factory()->create(['slug' => "assunto-$i"]);
            $palestras->push($this->palestra(['data_da_palestra' => '2024-01-0'.(($i % 9) + 1)], [$a]));
        }

        $this->assertSame(8, (new ResumoPerfil($palestras))->areasHero()->count());
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ResumoPerfilTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Implementar `ResumoPerfil`**

`app/Support/Palestrantes/ResumoPerfil.php`:
```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-01

namespace App\Support\Palestrantes;

use App\Models\Palestra;
use Illuminate\Support\Collection;

/**
 * Resumo do perfil de um palestrante, calculado em PHP (portável SQLite/MySQL)
 * a partir da coleção de palestras publicadas ministradas: estatísticas e as
 * áreas de atuação (assuntos distintos, com contagem e índice de cor).
 */
class ResumoPerfil
{
    /** Nº máximo de chips de área exibidos no hero. */
    public const CHIPS_HERO = 8;

    /** @param Collection<int, Palestra> $palestras */
    public function __construct(private Collection $palestras) {}

    public function totalPalestras(): int
    {
        return $this->palestras->count();
    }

    public function totalTemas(): int
    {
        return $this->areas()->count();
    }

    /** Menor ano de `data_da_palestra`; null quando não há data. */
    public function anoAtivoDesde(): ?int
    {
        return $this->palestras
            ->pluck('data_da_palestra')
            ->filter()
            ->map(fn ($d) => (int) $d->year)
            ->min();
    }

    /** Percentual de palestras online (0–100); null quando não há palestras. */
    public function percentualOnline(): ?int
    {
        $total = $this->palestras->count();

        if ($total === 0) {
            return null;
        }

        $online = $this->palestras->where('online', true)->count();

        return (int) round($online / $total * 100);
    }

    /**
     * Áreas de atuação: assuntos distintos, com contagem e índice de cor (id % 8),
     * ordenadas por frequência (desc).
     *
     * @return Collection<int, array{slug: string, nome: string, count: int, cor: int}>
     */
    public function areas(): Collection
    {
        return $this->palestras
            ->flatMap(fn (Palestra $p) => $p->assuntos)
            ->groupBy('id')
            ->map(function (Collection $grupo) {
                $assunto = $grupo->first();

                return [
                    'slug' => $assunto->slug,
                    'nome' => $assunto->nome,
                    'count' => $grupo->count(),
                    'cor' => $assunto->id % 8,
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /** Subconjunto de `areas()` para os chips do hero (top-N por frequência). */
    public function areasHero(): Collection
    {
        return $this->areas()->take(self::CHIPS_HERO);
    }
}
```

- [ ] **Step 4: Rodar o teste**

Run: `docker compose exec -T app php artisan test --filter=ResumoPerfilTest`
Expected: PASS. Pint: `docker compose exec -T app ./vendor/bin/pint app tests`

- [ ] **Step 5: Commit**

```bash
git add app/Support/Palestrantes/ResumoPerfil.php tests/Feature/Support/ResumoPerfilTest.php
git commit -m "feat(palestrante/perfil): ResumoPerfil (stats + areas de atuacao) em PHP portavel"
```

---

### Task 3: Controller `show()` — eager-load, ResumoPerfil, próxima e payload do Alpine

**Files:**
- Modify: `app/Http/Controllers/PalestranteController.php` (`show()`)
- Create: `tests/Feature/Front/PalestrantePerfilDadosTest.php`

**Interfaces:**
- Consumes: `ResumoPerfil` (Task 2), `palestrasMinistradas()`, `publicado()` scope.
- Produces: a view `palestrantes.show` recebe `palestrante`, `palestras` (Collection ordenada "recentes"), `resumo` (ResumoPerfil), `areas` (Collection), `areasHero` (Collection), `proxima` (?Palestra), `itensFiltro` (Collection de `['id','titulo','ts','assuntos']`).

> A view antiga (`palestrantes/show.blade.php`) permanece nesta task; as variáveis novas ficam disponíveis mas ainda não são consumidas — os testes existentes de `PalestrantePerfilTest` continuam verdes. A view nova entra na Task 4.

- [ ] **Step 1: Escrever o teste (falha)**

`tests/Feature/Front/PalestrantePerfilDadosTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Support\Palestrantes\ResumoPerfil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilDadosTest extends TestCase
{
    use RefreshDatabase;

    private function palestranteCom(array $palestras): Palestrante
    {
        $pessoa = Palestrante::factory()->ativo()->create(['slug' => 'fulano']);
        foreach ($palestras as $attrs) {
            $p = Palestra::factory()->create($attrs);
            $p->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        }

        return $pessoa;
    }

    public function test_view_recebe_resumo_areas_e_itens(): void
    {
        $assunto = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $pessoa = $this->palestranteCom([
            ['titulo' => 'Antiga', 'data_da_palestra' => '2020-01-01 19:30', 'status' => Palestra::STATUS_PUBLICADO],
            ['titulo' => 'Recente', 'data_da_palestra' => '2024-01-01 19:30', 'status' => Palestra::STATUS_PUBLICADO],
        ]);
        $pessoa->palestras->first()->assuntos()->attach($assunto);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertOk();

        $this->assertInstanceOf(ResumoPerfil::class, $resp->viewData('resumo'));
        // Ordem "recentes": a mais recente primeiro.
        $this->assertSame('Recente', $resp->viewData('palestras')->first()->titulo);
        $this->assertCount(2, $resp->viewData('itensFiltro'));
        $this->assertArrayHasKey('ts', $resp->viewData('itensFiltro')->first());
    }

    public function test_proxima_e_apenas_futura_publicada(): void
    {
        $pessoa = $this->palestranteCom([
            ['titulo' => 'Passada', 'data_da_palestra' => now()->subMonth(), 'status' => Palestra::STATUS_PUBLICADO],
            ['titulo' => 'Futura', 'data_da_palestra' => now()->addMonth(), 'status' => Palestra::STATUS_PUBLICADO],
        ]);

        $proxima = $this->get(route('palestrantes.show', 'fulano'))->viewData('proxima');
        $this->assertNotNull($proxima);
        $this->assertSame('Futura', $proxima->titulo);
    }

    public function test_proxima_null_sem_futura(): void
    {
        $this->palestranteCom([
            ['titulo' => 'Só passada', 'data_da_palestra' => now()->subMonth(), 'status' => Palestra::STATUS_PUBLICADO],
        ]);

        $this->assertNull($this->get(route('palestrantes.show', 'fulano'))->viewData('proxima'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantePerfilDadosTest`
Expected: FAIL (`viewData('resumo')` null).

- [ ] **Step 3: Reescrever `show()`**

Substituir o método `show()` em `app/Http/Controllers/PalestranteController.php` (e adicionar `use App\Support\Palestrantes\ResumoPerfil;` no topo):
```php
    public function show(string $slug): View
    {
        $palestrante = Palestrante::query()
            ->ativo()
            ->where('slug', $slug)
            ->firstOrFail();

        // Publicadas ministradas; ordem "recentes" (data desc, nulos por último) em PHP (portável).
        $palestras = $palestrante->palestrasMinistradas()
            ->publicado()
            ->with(['assuntos', 'palestrantesAtivos'])
            ->get()
            ->sortByDesc(fn (Palestra $p) => $p->data_da_palestra?->getTimestamp() ?? PHP_INT_MIN)
            ->values();

        $resumo = new ResumoPerfil($palestras);

        $proxima = $palestrante->palestrasMinistradas()
            ->publicado()
            ->whereNotNull('data_da_palestra')
            ->where('data_da_palestra', '>=', now())
            ->orderBy('data_da_palestra')
            ->first(); // sem fallback (big-bang)

        // Payload do filtro/ordenação client-side (Alpine): ordenação feita no cliente.
        $itensFiltro = $palestras->map(fn (Palestra $p) => [
            'id' => $p->id,
            'titulo' => $p->titulo,
            'ts' => $p->data_da_palestra?->getTimestamp(),
            'assuntos' => $p->assuntos->pluck('slug')->values()->all(),
        ])->values();

        return view('palestrantes.show', [
            'palestrante' => $palestrante,
            'palestras' => $palestras,
            'resumo' => $resumo,
            'areas' => $resumo->areas(),
            'areasHero' => $resumo->areasHero(),
            'proxima' => $proxima,
            'itensFiltro' => $itensFiltro,
        ]);
    }
```

- [ ] **Step 4: Rodar os testes (novos + regressão)**

Run: `docker compose exec -T app php artisan test --filter='PalestrantePerfilDadosTest|PalestrantePerfilTest'`
Expected: PASS (novos verdes; os 5 existentes de `PalestrantePerfilTest` seguem verdes — view antiga intacta). Pint.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PalestranteController.php tests/Feature/Front/PalestrantePerfilDadosTest.php
git commit -m "feat(palestrante/perfil): controller show() com ResumoPerfil, proxima e payload do filtro"
```

---

### Task 4: View redesenhada (casca + 5 parciais) + Alpine + CSS

**Files:**
- Rewrite: `resources/views/palestrantes/show.blade.php`
- Create: `resources/views/palestrantes/perfil/hero.blade.php`
- Create: `resources/views/palestrantes/perfil/estatisticas.blade.php`
- Create: `resources/views/palestrantes/perfil/sobre.blade.php`
- Create: `resources/views/palestrantes/perfil/palestras.blade.php`
- Create: `resources/views/palestrantes/perfil/sidebar.blade.php`
- Modify: `resources/js/app.js` (registrar `Alpine.data('palestranteDetalhe', …)`)
- Modify: `resources/css/palestrantes.css` (acréscimos: `.cema-dot-*`, `.cema-prosa-perfil`, `.cema-share-btn`)
- Create: `tests/Feature/Front/PalestrantePerfilRedesignTest.php`

**Interfaces:**
- Consumes: `palestrante`, `palestras`, `resumo`, `areas`, `areasHero`, `proxima`, `itensFiltro` (Task 3); `<x-palestra.card>`; `<x-ui.particulas>`; `<x-layout.app>`; accessor `iniciais`, `foto_url`/`foto_thumb_url`; `cema-grad-{id%8}`; rotas `palestras.calendario`/`palestras.show`.
- Produces: a página completa. Estado Alpine `palestranteDetalhe({ itens, areas })` com `area`/`sort`, métodos `visivel(id)`/`ordem(id)`/`selecionar(slug)` e getters `filtradas`/`vazio`/`rotulo`.

> Esta task substitui a view inteira de uma vez (as seções compartilham o mesmo escopo Alpine e os testes de regressão de `PalestrantePerfilTest` cobrem bio/palestras/contato/JSON-LD). Todos os parciais são criados junto com a casca.

- [ ] **Step 1: Registrar o componente Alpine**

Substituir o conteúdo de `resources/js/app.js` por:
```js
// Componente Alpine do perfil do palestrante: filtro por tema + ordenação (client-side).
// Alpine vem do bundle do Livewire; registramos no evento alpine:init.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('palestranteDetalhe', (config) => ({
        area: 'todos',
        sort: 'recent',
        itens: config.itens ?? [],
        areas: config.areas ?? [],

        visivel(id) {
            if (this.area === 'todos') {
                return true;
            }
            const item = this.itens.find((i) => i.id === id);

            return !!item && item.assuntos.includes(this.area);
        },

        get ordemPorId() {
            const arr = [...this.itens];
            if (this.sort === 'recent') {
                arr.sort((a, b) => (b.ts ?? -Infinity) - (a.ts ?? -Infinity));
            } else if (this.sort === 'old') {
                arr.sort((a, b) => (a.ts ?? Infinity) - (b.ts ?? Infinity));
            } else {
                arr.sort((a, b) => a.titulo.localeCompare(b.titulo, 'pt'));
            }
            const mapa = {};
            arr.forEach((i, idx) => {
                mapa[i.id] = idx;
            });

            return mapa;
        },

        ordem(id) {
            return this.ordemPorId[id] ?? 0;
        },

        selecionar(slug) {
            this.area = this.area === slug ? 'todos' : slug;
        },

        get filtradas() {
            return this.itens.filter((i) => this.visivel(i.id));
        },

        get vazio() {
            return this.filtradas.length === 0;
        },

        get rotulo() {
            const n = this.filtradas.length;
            const base = n === 1 ? '1 palestra' : `${n} palestras`;
            if (this.area === 'todos') {
                return base;
            }
            const a = this.areas.find((x) => x.slug === this.area);

            return a ? `${base} em ${a.nome}` : base;
        },
    }));
});
```

- [ ] **Step 2: Acréscimos de CSS**

Anexar ao final de `resources/css/palestrantes.css`:
```css

/* ===== Perfil (single) do palestrante ===== */
@layer components {
    /* Bolinhas das áreas — rotação por assunto->id % 8 (consistente hero/sidebar/filtro). */
    .cema-dot-0 { background: #4e4483; }
    .cema-dot-1 { background: #6e9fcb; }
    .cema-dot-2 { background: #89ab98; }
    .cema-dot-3 { background: #f2a81e; }
    .cema-dot-4 { background: #c87fb0; }
    .cema-dot-5 { background: #e79048; }
    .cema-dot-6 { background: #5aa9a0; }
    .cema-dot-7 { background: #7a6fbe; }

    /* Prosa de leitura do "Sobre". */
    .cema-prosa-perfil p { margin-bottom: 16px; font-size: 15.5px; line-height: 1.85; color: #3a3553; }
    .cema-prosa-perfil p:last-child { margin-bottom: 0; }
    .cema-prosa-perfil a { color: var(--color-secondary); text-decoration: underline; }
    .cema-prosa-perfil h2,
    .cema-prosa-perfil h3 { margin: 20px 0 10px; font-family: var(--font-display); font-weight: 600; color: var(--color-primary); }
    .cema-prosa-perfil ul { margin-bottom: 16px; padding-left: 1.25rem; list-style: disc; }

    /* Botões redondos de compartilhar. */
    .cema-share-btn { display: flex; width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 9999px; transition: filter .2s ease, transform .2s ease; }
    .cema-share-btn:hover { filter: brightness(1.06); transform: translateY(-1px); }
}
```

- [ ] **Step 3: Escrever o teste do redesign (falha)**

`tests/Feature/Front/PalestrantePerfilRedesignTest.php`:
```php
<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestrantePerfilRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function palestrante(array $attrs = []): Palestrante
    {
        return Palestrante::factory()->ativo()->create(array_merge(['slug' => 'fulano', 'nome' => 'Fulano de Tal'], $attrs));
    }

    private function comPalestra(Palestrante $pessoa, array $attrs, ?Assunto $assunto = null): Palestra
    {
        $p = Palestra::factory()->create(array_merge(['status' => Palestra::STATUS_PUBLICADO], $attrs));
        $p->palestrantes()->attach($pessoa, ['papel' => Palestra::PAPEL_PALESTRANTE]);
        if ($assunto) {
            $p->assuntos()->attach($assunto);
        }

        return $p;
    }

    public function test_hero_eyebrow_h1_e_cta_calendario(): void
    {
        $this->palestrante();
        $resp = $this->get(route('palestrantes.show', 'fulano'));

        $resp->assertOk();
        $resp->assertSee('Palestrante'); // eyebrow "Palestrante · CEMA" (maiúsculo é só CSS); assertSee é case-sensitive
        $resp->assertSee('Fulano de Tal');
        $resp->assertSee(route('palestras.calendario'), false);
        $resp->assertSee('palestranteDetalhe(', false); // wiring do Alpine
    }

    public function test_chamada_exibida_quando_preenchida_e_oculta_quando_vazia(): void
    {
        $this->palestrante(['chamada' => 'Servindo desde a infância.']);
        $this->get(route('palestrantes.show', 'fulano'))->assertSee('Servindo desde a infância.');

        Palestrante::query()->where('slug', 'fulano')->update(['chamada' => null]);
        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Servindo desde a infância.');
    }

    public function test_chips_de_area_e_barra_de_filtro(): void
    {
        $pessoa = $this->palestrante();
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $this->comPalestra($pessoa, ['titulo' => 'Palestra A'], $evangelho);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Evangelho');
        $resp->assertSee("selecionar('evangelho')", false); // chip clicável
        $resp->assertSee('Título (A–Z)');                    // opção de ordenação
        $resp->assertSee('Palestra A');                      // card via <x-palestra.card>
    }

    public function test_stats_reais_e_null_safe(): void
    {
        $pessoa = $this->palestrante();
        $this->comPalestra($pessoa, ['data_da_palestra' => '2023-05-01 19:30', 'online' => true]);

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Ativo no CEMA desde');
        $resp->assertSee('2023');
        $resp->assertSee('100%'); // 1 de 1 online

        // Sem palestras → ano/percentual viram "—" (null-safe).
        $vazio = $this->palestrante(['slug' => 'sem-palestras', 'nome' => 'Sem Palestras']);
        $this->get(route('palestrantes.show', 'sem-palestras'))->assertSee('—');
    }

    public function test_sobre_aparece_com_bio_e_some_sem_bio(): void
    {
        $this->palestrante(['bio' => '<p>Biografia rica.</p>']);
        $this->get(route('palestrantes.show', 'fulano'))->assertSee('Biografia rica', false);

        Palestrante::query()->where('slug', 'fulano')->update(['bio' => null]);
        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Sobre Fulano de Tal');
    }

    public function test_sidebar_proxima_e_compartilhar(): void
    {
        $pessoa = $this->palestrante();
        // A palestra futura precisa de um assunto: o bloco "Áreas de atuação"
        // só renderiza quando $areas não está vazio (big-bang — @if isNotEmpty).
        $evangelho = Assunto::factory()->create(['nome' => 'Evangelho', 'slug' => 'evangelho']);
        $this->comPalestra(
            $pessoa,
            ['titulo' => 'Palestra Futura', 'slug' => 'palestra-futura', 'data_da_palestra' => now()->addMonth()],
            $evangelho,
        );

        $resp = $this->get(route('palestrantes.show', 'fulano'));
        $resp->assertSee('Em destaque');
        $resp->assertSee('Palestra Futura');
        $resp->assertSee('facebook.com/sharer', false);
        $resp->assertSee('wa.me', false);
        $resp->assertSee('Áreas de atuação');
    }

    public function test_proxima_oculta_sem_futura(): void
    {
        $pessoa = $this->palestrante();
        $this->comPalestra($pessoa, ['titulo' => 'Só passada', 'data_da_palestra' => now()->subMonth()]);

        $this->get(route('palestrantes.show', 'fulano'))->assertDontSee('Em destaque');
    }

    public function test_seo_canonical_e_jsonld(): void
    {
        $this->palestrante();
        $resp = $this->get(route('palestrantes.show', 'fulano'));

        $resp->assertSee('rel="canonical"', false);
        $resp->assertSee(route('palestrantes.show', 'fulano'), false);
        $resp->assertSee('"@type":"Person"', false);
        $resp->assertDontSee('og:image'); // sem foto → sem meta og:image
    }
}
```

- [ ] **Step 4: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PalestrantePerfilRedesignTest`
Expected: FAIL (view antiga).

- [ ] **Step 5: Criar o parcial `hero`**

`resources/views/palestrantes/perfil/hero.blade.php`:
```blade
{{-- Hero roxo: partículas + onda SVG + breadcrumb + foto 3:4 (ou iniciais) + chamada + chips + CTA calendário. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-[#0b1030] via-[#1a1f4a] to-[#2c2f64] text-white">
    <x-ui.particulas />
    <svg viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"
         class="absolute inset-x-0 -bottom-px block h-20 w-full">
        <path d="M0,80 C240,20 480,110 720,70 C960,30 1200,100 1440,50 L1440,120 L0,120 Z" fill="var(--color-surface)"></path>
    </svg>

    <div class="relative z-[2] mx-auto max-w-[1160px] px-6 pb-24 pt-6">
        <nav aria-label="Trilha de navegação" class="mb-7 flex flex-wrap items-center gap-2 text-[12.5px] text-[#9aa6cf]">
            <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
            <a href="{{ route('palestrantes.index') }}" class="hover:text-white">Palestrantes</a><span aria-hidden="true">›</span>
            <span class="text-[#e7e9f4]" aria-current="page">{{ $palestrante->nome }}</span>
        </nav>

        <div class="flex flex-wrap items-end gap-9">
            {{-- Foto 3:4 em moldura translúcida; sem foto → iniciais em gradiente. --}}
            <div class="w-[186px] shrink-0 rounded-[22px] border border-white/16 bg-white/8 p-2 backdrop-blur-sm">
                @if ($palestrante->foto_url)
                    <img src="{{ $palestrante->foto_url }}" alt="{{ $palestrante->nome }}" width="186" height="248"
                         class="block aspect-[3/4] w-full rounded-[15px] object-cover">
                @else
                    <span class="cema-grad-{{ $palestrante->id % 8 }} grid aspect-[3/4] w-full place-items-center rounded-[15px]" aria-hidden="true">
                        <span class="font-display text-5xl font-semibold text-white/90">{{ $palestrante->iniciais }}</span>
                    </span>
                @endif
            </div>

            <div class="min-w-[280px] flex-1 basis-[420px]">
                <p class="mb-3 font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Palestrante · CEMA</p>
                <h1 class="mb-4 font-display font-semibold leading-[1.06] text-white [font-size:clamp(2.2rem,1.5rem+2.4vw,3.4rem)]">{{ $palestrante->nome }}</h1>
                <div class="mb-[18px] h-1 w-16 rounded-full bg-gold"></div>
                @if ($palestrante->chamada)
                    <p class="mb-5 max-w-[560px] font-serif italic text-white/85 [font-size:clamp(1.05rem,1rem+0.35vw,1.25rem)]">{{ $palestrante->chamada }}</p>
                @endif
                @if ($areasHero->isNotEmpty())
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($areasHero as $areaItem)
                            <button type="button"
                                    @click="selecionar('{{ $areaItem['slug'] }}')"
                                    :aria-pressed="area === '{{ $areaItem['slug'] }}'"
                                    :class="area === '{{ $areaItem['slug'] }}' ? 'ring-2 ring-gold' : ''"
                                    class="inline-flex items-center gap-2 rounded-pill border border-white/20 bg-white/10 px-3.5 py-1.5 text-[12.5px] text-[#e7e9f4] transition hover:bg-white/15">
                                <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-2 rounded-full"></span>{{ $areaItem['nome'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <a href="{{ route('palestras.calendario') }}"
               class="inline-flex shrink-0 items-center gap-3 rounded-2xl border border-white/22 bg-white/10 px-5 py-4 backdrop-blur-sm transition hover:bg-white/15">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold text-[#3a3266]" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                </span>
                <span>
                    <span class="block font-display font-semibold">Calendário de Palestras</span>
                    <span class="block text-sm text-white/75">Veja a programação completa →</span>
                </span>
            </a>
        </div>
    </div>
</section>
```

- [ ] **Step 6: Criar o parcial `estatisticas`**

`resources/views/palestrantes/perfil/estatisticas.blade.php`:
```blade
@php
    $ano = $resumo->anoAtivoDesde();
    $pct = $resumo->percentualOnline();
    $tiles = [
        ['valor' => $resumo->totalPalestras(), 'rotulo' => 'Palestras', 'bg' => 'bg-cream'],
        ['valor' => $resumo->totalTemas(), 'rotulo' => 'Temas abordados', 'bg' => 'bg-[#EAF0F6]'],
        ['valor' => $ano ?? '—', 'rotulo' => 'Ativo no CEMA desde', 'bg' => 'bg-[#EAF2EC]'],
        ['valor' => $pct !== null ? $pct.'%' : '—', 'rotulo' => 'Palestras online', 'bg' => 'bg-surface'],
    ];
@endphp
<div class="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(130px,1fr))]">
    @foreach ($tiles as $tile)
        <div class="{{ $tile['bg'] }} rounded-[14px] border border-border-muted px-4 py-[18px] text-center">
            <p class="font-display text-[26px] font-bold leading-none text-primary">{{ $tile['valor'] }}</p>
            <p class="mt-[7px] text-[11.5px] text-[#6a6685]">{{ $tile['rotulo'] }}</p>
        </div>
    @endforeach
</div>
```

- [ ] **Step 7: Criar o parcial `sobre`**

`resources/views/palestrantes/perfil/sobre.blade.php`:
```blade
@if ($palestrante->bio)
    <div class="mt-6 rounded-2xl border border-border-muted bg-white p-8 shadow-card">
        <div class="mb-[18px] flex items-center gap-2.5">
            <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
            <h2 class="font-display text-[19px] font-semibold text-primary">Sobre {{ $palestrante->nome }}</h2>
        </div>
        <div class="cema-prosa-perfil">{!! $palestrante->bio !!}</div>
    </div>
@endif
```

- [ ] **Step 8: Criar o parcial `palestras`**

`resources/views/palestrantes/perfil/palestras.blade.php`:
```blade
{{-- Cabeçalho + barra de filtro/ordenação + grade (reusa <x-palestra.card>) + empty state.
     Filtro/ordenação client-side via o escopo Alpine `palestranteDetalhe` (definido na casca). --}}
<div class="mt-8">
    <div class="mb-[18px] flex flex-wrap items-baseline justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
            <h2 class="font-display text-[21px] font-semibold text-primary">Palestras de {{ $palestrante->nome }}</h2>
        </div>
        <p class="text-[13px] text-text-muted" x-text="rotulo">{{ $palestras->count() }} {{ \Illuminate\Support\Str::plural('palestra', $palestras->count()) }}</p>
    </div>

    @if ($palestras->isNotEmpty())
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-[14px] border border-border-muted bg-white px-4 py-3.5 shadow-card">
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="area = 'todos'" :aria-pressed="area === 'todos'"
                        :class="area === 'todos' ? 'bg-primary text-white' : 'bg-surface text-text-secondary'"
                        class="rounded-pill px-3.5 py-1.5 text-[12.5px] font-medium transition">Todas</button>
                @foreach ($areas as $areaItem)
                    <button type="button"
                            @click="selecionar('{{ $areaItem['slug'] }}')"
                            :aria-pressed="area === '{{ $areaItem['slug'] }}'"
                            :class="area === '{{ $areaItem['slug'] }}' ? 'bg-primary text-white' : 'bg-surface text-text-secondary'"
                            class="inline-flex items-center gap-2 rounded-pill px-3.5 py-1.5 text-[12.5px] font-medium transition">
                        <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[7px] rounded-full"></span>{{ $areaItem['nome'] }}
                    </button>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                <label for="ordenar-palestras" class="whitespace-nowrap text-[13px] text-text-muted">Ordenar:</label>
                <select id="ordenar-palestras" x-model="sort"
                        class="cursor-pointer rounded-[10px] border border-border bg-white px-3 py-2 text-[13.5px] text-text-secondary outline-none">
                    <option value="recent">Mais recentes</option>
                    <option value="old">Mais antigas</option>
                    <option value="az">Título (A–Z)</option>
                </select>
            </div>
        </div>

        <div class="grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(258px,1fr))]" x-show="!vazio">
            @foreach ($palestras as $palestra)
                <x-palestra.card
                    :palestra="$palestra"
                    x-show="visivel({{ $palestra->id }})"
                    x-bind:style="{ order: ordem({{ $palestra->id }}) }" />
            @endforeach
        </div>

        <div x-show="vazio" x-cloak class="rounded-2xl border border-dashed border-[#DAD5E6] bg-white px-6 py-14 text-center">
            <p class="mb-1.5 font-display text-[17px] font-semibold text-[#3a3266]">Nenhuma palestra neste tema</p>
            <p class="mb-4 text-sm text-text-muted">Remova o filtro para ver todas as palestras.</p>
            <button type="button" @click="area = 'todos'" class="rounded-pill bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:brightness-110">Ver todas</button>
        </div>
    @else
        <p class="rounded-lg border border-border-muted bg-white px-6 py-8 text-text-secondary">Nenhuma palestra publicada por ora.</p>
    @endif
</div>
```

- [ ] **Step 9: Criar o parcial `sidebar`**

`resources/views/palestrantes/perfil/sidebar.blade.php`:
```blade
@php $urlPerfil = route('palestrantes.show', $palestrante->slug); @endphp
<div class="flex flex-col gap-5">
    {{-- Próxima palestra (sem fallback: some se não houver futura). --}}
    @if ($proxima)
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#3a3266] p-6 text-white shadow-card">
            <span class="absolute -right-8 -top-8 size-28 rounded-full bg-gold/[0.18]" aria-hidden="true"></span>
            <p class="mb-1 font-mono text-[10.5px] uppercase tracking-[0.16em] text-gold">Em destaque</p>
            <h2 class="mb-4 font-display text-lg font-semibold">Próxima palestra</h2>
            <div class="mb-4 flex items-center gap-3">
                <span class="cema-grad-{{ $palestrante->id % 8 }} grid size-12 shrink-0 place-items-center overflow-hidden rounded-full ring-2 ring-white/25">
                    @if ($palestrante->foto_thumb_url)
                        <img src="{{ $palestrante->foto_thumb_url }}" alt="" width="48" height="48" class="size-full object-cover">
                    @else
                        <span class="font-display text-sm font-semibold text-white/90" aria-hidden="true">{{ $palestrante->iniciais }}</span>
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $palestrante->nome }}</p>
                    @if ($proxima->data_da_palestra)
                        <p class="font-mono text-xs text-[#c7c0e6]">{{ $proxima->data_da_palestra->translatedFormat('D, d M Y') }} · {{ $proxima->data_da_palestra->format('H\hi') }}</p>
                    @endif
                </div>
            </div>
            <h3 class="mb-4 font-display font-semibold leading-snug">{{ $proxima->titulo }}</h3>
            <a href="{{ route('palestras.show', $proxima->slug) }}"
               class="inline-flex rounded-pill bg-gold px-5 py-2 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ver palestra</a>
        </div>
    @endif

    {{-- Áreas de atuação (clicáveis = filtram o grid, mesmo estado dos chips). --}}
    @if ($areas->isNotEmpty())
        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
            <h2 class="mb-3.5 font-display text-base font-semibold text-primary">Áreas de atuação</h2>
            <div class="flex flex-col gap-0.5">
                @foreach ($areas as $areaItem)
                    <button type="button"
                            @click="selecionar('{{ $areaItem['slug'] }}')"
                            :aria-pressed="area === '{{ $areaItem['slug'] }}'"
                            :class="area === '{{ $areaItem['slug'] }}' ? 'bg-surface' : ''"
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm text-text-secondary transition hover:bg-surface">
                        <span class="flex items-center gap-2.5">
                            <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[9px] rounded-full"></span>{{ $areaItem['nome'] }}
                        </span>
                        <span class="text-xs text-[#9a93b4]">{{ $areaItem['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Contato — preserva comportamento existente (flags mostrar_email/mostrar_telefone). --}}
    @if (($palestrante->mostrar_email && $palestrante->email) || ($palestrante->mostrar_telefone && $palestrante->telefone))
        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
            <h2 class="mb-3 font-display text-base font-semibold text-primary">Contato</h2>
            @if ($palestrante->mostrar_email && $palestrante->email)
                <p class="text-sm text-text-secondary"><a href="mailto:{{ $palestrante->email }}" class="underline hover:text-secondary">{{ $palestrante->email }}</a></p>
            @endif
            @if ($palestrante->mostrar_telefone && $palestrante->telefone)
                <p class="mt-1 text-sm text-text-secondary">{{ $palestrante->telefone }}</p>
            @endif
        </div>
    @endif

    {{-- Compartilhar (client-side). --}}
    <div x-data="{ copiado: false }" class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
        <p class="mb-3.5 font-display text-sm font-semibold text-[#3a3266]">Compartilhar palestrante</p>
        <div class="flex flex-wrap gap-2.5">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlPerfil) }}" target="_blank" rel="noopener"
               aria-label="Compartilhar no Facebook" class="cema-share-btn bg-[#1877F2] text-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
            </a>
            <a href="https://wa.me/?text={{ urlencode($palestrante->nome.' — '.$urlPerfil) }}" target="_blank" rel="noopener"
               aria-label="Compartilhar no WhatsApp" class="cema-share-btn bg-[#1FA855] text-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.06 24l1.68-6.13A11.86 11.86 0 0 1 .14 11.9C.14 5.34 5.48 0 12.05 0a11.82 11.82 0 0 1 8.41 3.49 11.82 11.82 0 0 1 3.48 8.41c0 6.56-5.34 11.9-11.9 11.9a11.9 11.9 0 0 1-5.69-1.45L.06 24zM6.6 20.13c1.68 1 3.28 1.6 5.43 1.6 5.46 0 9.9-4.43 9.9-9.88 0-5.46-4.44-9.9-9.9-9.9-5.46 0-9.9 4.44-9.9 9.9 0 2.26.66 3.95 1.77 5.72l-.99 3.62 3.69-1.06z"/></svg>
            </a>
            <button type="button" aria-label="Copiar link" class="cema-share-btn border border-border bg-surface text-primary"
                    @click="navigator.clipboard.writeText('{{ $urlPerfil }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000); })">
                <span x-show="!copiado"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span>
                <span x-show="copiado" x-cloak class="text-xs font-semibold" aria-hidden="true">✓</span>
            </button>
        </div>
    </div>
</div>
```

- [ ] **Step 10: Reescrever a casca `show.blade.php`**

`resources/views/palestrantes/show.blade.php`:
```blade
@php $urlPerfil = route('palestrantes.show', $palestrante->slug); @endphp
<x-layout.app :title="$palestrante->nome"
              :description="\Illuminate\Support\Str::limit(strip_tags($palestrante->chamada ?? $palestrante->bio ?? ''), 150) ?: 'Palestrante do CEMA'">
    <x-slot:head>
        <script type="application/ld+json">
        @php
            echo json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $palestrante->nome,
                'image' => $palestrante->foto_url, // omitido quando null
                'description' => \Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 200),
                'url' => $urlPerfil,
                'worksFor' => ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'],
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        </script>
        <link rel="canonical" href="{{ $urlPerfil }}">
        @if ($palestrante->foto_url)
            <meta property="og:image" content="{{ $palestrante->foto_url }}">
        @endif
    </x-slot:head>

    <div x-data="palestranteDetalhe({ itens: @js($itensFiltro), areas: @js($areas) })">
        @include('palestrantes.perfil.hero')

        <section class="bg-surface">
            <div class="mx-auto flex max-w-[1160px] flex-col gap-8 px-6 py-10 desktop-sm:flex-row desktop-sm:items-start">
                <div class="min-w-0 flex-1">
                    @include('palestrantes.perfil.estatisticas')
                    @include('palestrantes.perfil.sobre')
                    @include('palestrantes.perfil.palestras')
                </div>
                <aside class="w-full shrink-0 desktop-sm:sticky desktop-sm:top-24 desktop-sm:w-[340px]">
                    @include('palestrantes.perfil.sidebar')
                </aside>
            </div>
        </section>
    </div>
</x-layout.app>
```

- [ ] **Step 11: Build + reflexo no dev + testes (novos + regressão)**

Run:
```
npm run build
docker compose restart app worker
docker compose exec -T app php artisan test --filter='PalestrantePerfilRedesignTest|PalestrantePerfilTest|PalestrantePerfilDadosTest'
```
Expected: PASS (novos verdes; os 5 de `PalestrantePerfilTest` — bio, palestras, contato por flags, JSON-LD, 404 — seguem verdes). Pint: `docker compose exec -T app ./vendor/bin/pint app resources tests`.

> Se `PalestrantePerfilTest::test_contato_respeita_flags` ou `test_email_oculto_quando_flag_desligada` falharem, revisar o card "Contato" do parcial `sidebar` (gating por flag). As diretivas `x-show`/`x-bind:style` passadas ao `<x-palestra.card>` chegam ao `<article>` porque o componente renderiza `$attributes->class([...])` (que emite os demais atributos além do class). Nenhum `wire:key` é usado nesta página — o filtro/ordenação é Alpine, não Livewire.

- [ ] **Step 12: Commit**

```bash
git add resources/views/palestrantes resources/js/app.js resources/css/palestrantes.css tests/Feature/Front/PalestrantePerfilRedesignTest.php
git commit -m "feat(palestrante/single): redesign do perfil (hero, stats, grade filtravel, sidebar)"
```

---

### Task 5: Fecho da fatia — suíte completa, Pint, build, verificação manual

**Files:** nenhum novo (validação + DoD).

- [ ] **Step 1: Suíte completa**

Run: `docker compose exec -T app php artisan test`
Expected: tudo verde (regressão zero; os testes das fatias anteriores intactos).

- [ ] **Step 2: Pint (o CI roda `pint --test` antes dos testes)**

Run: `docker compose exec -T app ./vendor/bin/pint --test`
Expected: PASS. Se acusar drift, `./vendor/bin/pint` e commitar.

- [ ] **Step 3: Build de produção + reflexo no dev**

Run: `npm run build && docker compose restart app worker`
Expected: build ok (Alpine + CSS novos no bundle).

- [ ] **Step 4: Verificação manual no `localhost`** (humano)

- `http://localhost:8000/palestrantes/{slug}` de um palestrante com palestras: hero (foto/iniciais, chamada quando houver, chips), stats reais, "Sobre", grade; **filtro por tema** (chips do hero + barra + lista lateral compartilham estado; `aria-pressed`), **ordenação** (recentes/antigas/A–Z reordena via CSS `order`), **empty state** + "Ver todas", **próxima palestra** (ou ausente), **compartilhar** (FB/WA/copiar), sidebar **sticky** no desktop e estática no mobile; hero empilha no mobile; `prefers-reduced-motion` desliga partículas.
- Um palestrante **sem** palestras: stats `—`, sem chips/áreas/próxima, empty da grade, contato conforme flags.

- [ ] **Step 5: Abrir PR**

```bash
git push -u origin HEAD
gh pr create --base main --title "Redesign da single do Palestrante (/palestrantes/{slug})" --body "..."
```
Aguardar o passe adversarial do dono antes do merge; mesclar só com **CI verde no commit final**.

---

## Self-Review

**1. Cobertura do spec:**
- Migração `chamada` + Filament → Task 1. `ResumoPerfil` (stats/áreas portáveis) → Task 2. Controller (padrão `{slug}`, eager-load, próxima, payload) → Task 3. Hero (foto 3:4/iniciais, chamada condicional, chips top-N, CTA calendário, onda, breadcrumb) → Task 4/hero. Stats + null-safe → Task 4/estatisticas. "Sobre" prosa + esconde sem bio → Task 4/sobre. Grade reusando `<x-palestra.card>` + filtro/ordenação Alpine + empty → Task 4/palestras + app.js. Sidebar (próxima sem fallback, áreas clicáveis, compartilhar, **contato preservado**) → Task 4/sidebar. SEO (JSON-LD Person, canonical, og:image condicional) → Task 4/casca. A11y/responsivo/reduced-motion → Task 4 + guardrails. DoD → Task 5.
- Estado único Alpine compartilhado entre chips do hero + barra + lista lateral + grid → um `x-data` na casca envolvendo hero + conteúdo + sidebar. ✓

**2. Placeholders:** nenhum — todo passo traz o código real. Sem "TBD"/"etc.".

**3. Consistência de tipos/nomes:** `ResumoPerfil` (`areas()`/`areasHero()`/`anoAtivoDesde()`/`percentualOnline()`) idêntico entre Task 2 (def), Task 3 (uso) e Task 4 (view). Payload `itensFiltro` (`id/titulo/ts/assuntos`) casa com o consumo no `app.js` (`i.id/i.titulo/i.ts/i.assuntos`) e nas diretivas `visivel({id})`/`ordem({id})`. `areas` (`slug/nome/count/cor`) casa entre controller, chips, lista lateral e `rotulo` do Alpine. Classes `.cema-dot-{0..7}` definidas (Task 4/CSS) e usadas (hero/filtro/sidebar). `selecionar`/`area`/`sort`/`vazio`/`rotulo` definidos no `app.js` e referenciados nas views.

**4. Riscos observados / decisões:**
- **`<x-palestra.card>` recebe diretivas Alpine** (`x-show`, `x-bind:style` objeto) via `$attributes` (o `<article>` usa `$attributes->class([...])`, que renderiza também os demais atributos). `x-bind:style="{ order: … }"` em **forma de objeto** para não conflitar com o `display` gerido pelo `x-show`.
- **Regressão de contato:** o card "Contato" na sidebar preserva o comportamento das flags (testes existentes de `PalestrantePerfilTest`). Confirmado no Step 11.
- **Ordenação client-side por `localeCompare('pt')`** (A–Z correto com acentos); ranks não são pré-computados no servidor (evita dependência de `Collator`/intl e SQL raw). SSR entrega ordem "recentes".
- **Portabilidade:** distintos/contagem/`min(ano)`/ordenação em PHP; nenhuma query com `YEAR()`/`selectRaw`.
