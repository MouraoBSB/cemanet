# Eventos — Fase 2 (Importador do legado) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Importar de forma idempotente os 54 eventos do WordPress legado (CPT `_evento`) para as tabelas criadas na Fase 1 (`eventos`, `departamento_evento`, mídia), resolvendo data/hora, visibilidade, categoria (heurística), departamentos (por sigla) e imagens (flyer + galeria).

**Architecture:** Espelha o pipeline de importação do **Blog** (o mais próximo: post_content + thumbnail + galeria + taxonomia N:N). Interface `LeitorEventos` → `LeitorEventosMysql` (SELECT no legado, somente leitura) → `ImportadorEventos` (upsert por slug, mídia via `BaixadorImagem`, taxonomia por sigla) → comando `cema:importar-eventos` com guarda de túnel. Lógica reaproveitada: `TransformadorLegado::unixParaData`/`statusParaAtivo`, `BaixadorImagem::baixarCapado`, o padrão `metasDe()` (1º valor por chave — resolve os 13 `data_do_evento` duplicados de graça).

**Tech Stack:** PHP 8.3 · Laravel 13 · MySQL 8 (dev) / SQLite (testes) · conexão `legado` (somente leitura, via túnel SSH) · spatie/laravel-medialibrary.

## Global Constraints

- **Idioma:** tudo em pt-BR (identificadores de domínio, mensagens, comentários, commits).
- **Legado é SOMENTE LEITURA:** apenas `SELECT` na conexão `legado`. 🚫 Nunca INSERT/UPDATE/DELETE/DDL no legado; a conexão `legado` jamais roda migrations/seeders nem usa root.
- **Banco de dev:** só `php artisan migrate` incremental. 🚫 NUNCA `migrate:fresh`/`refresh`/`wipe`/`reset` nem seed destrutivo (apagariam os 123 palestras/44 posts/eventos importados).
- **Idempotência obrigatória:** upsert por `slug` (e `wp_id` unique); rodar 2× não duplica eventos nem mídia (limpar coleções antes de reanexar, como no blog).
- **Datas:** `data_do_evento` é **timestamp Unix** (JetEngine, relógio local gravado como UTC) → converter com `TransformadorLegado::unixParaData` (mesma classe que Palestras usa) e separar em `data_inicio` (`Y-m-d`) + `hora_inicio` (`H:i`). Legado não tem fim → `data_fim`/`hora_fim` ficam `null`.
- **Autoria:** cabeçalho `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08` em todo arquivo PHP novo.
- **Ferramentas no container:** `docker compose exec -T app php artisan ...` / `./vendor/bin/pint`. `npm`/Vite no host (irrelevante aqui).
- **Convenção do projeto (leitor `*Mysql`):** leitores do legado **não têm teste unitário** — o comportamento é coberto por um *fake* nos testes do importador, e o **SQL real é verificado contra o legado vivo no GATE de merge** (memória `verificar-leitor-legado-contra-banco-real`: um SQL 1064 já passou pela suíte por causa disso).
- **Pint** antes do push; suíte no container; commits atômicos em pt-BR na branch `eventos-fase-2-importador`.

## Decisões a confirmar no passe adversarial (o dono decide)

1. **`mostrar_horario` ausente** (26/54 eventos não têm a meta): o CPT tinha default **ON**, então este plano trata **ausente = mostra a hora** (só `mostrar_horario` presente-e-falsy zera `hora_inicio`). Alternativa: ausente = esconde (idiomático `statusParaAtivo`). *Escolhido: ausente → mostra.*
2. **Não-público → `diretoria`** (fail-closed) + aviso, conforme spec §5. (Já decidido na review do design.)
3. **Categoria por heurística do título** (Brechó/Feirão·Livros/Encontro·Família/Campanha/Curso·Estudo·CEMART); sem match → `null` + aviso (revisar no admin). Palavras-chave abaixo — confirmar o conjunto.
4. **Evento sem `data_do_evento` válido** → **pulado** com aviso (não cria registro com data placeholder). Esperado: 0 casos (54/54 têm TS), mas defensivo.

---

### Task 1: `LeitorEventos` (interface) + `ClassificadorCategoria` (heurística pura)

**Files:**
- Create: `app/Importacao/LeitorEventos.php`
- Create: `app/Importacao/ClassificadorCategoria.php`
- Test: `tests/Unit/Importacao/ClassificadorCategoriaTest.php`

**Interfaces:**
- Produces: `App\Importacao\LeitorEventos` (`eventos(): array`) — implementado por `LeitorEventosMysql` (Task 2) e por fakes nos testes (Task 3/4). `App\Importacao\ClassificadorCategoria::paraSlug(string $titulo): ?string` — consumido pelo `ImportadorEventos` (Task 3).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Unit\Importacao;

use App\Importacao\ClassificadorCategoria;
use PHPUnit\Framework\TestCase;

class ClassificadorCategoriaTest extends TestCase
{
    public function test_infere_categoria_pelo_titulo(): void
    {
        $this->assertSame('brecho', ClassificadorCategoria::paraSlug('Brechó Solidário do CEMA – Festa Junina'));
        $this->assertSame('feirao', ClassificadorCategoria::paraSlug('Feirão de Livros Espíritas — Chico Xavier'));
        $this->assertSame('feirao', ClassificadorCategoria::paraSlug('Grande venda de Livros usados'));
        $this->assertSame('familia', ClassificadorCategoria::paraSlug('Encontro da Família CEMA'));
        $this->assertSame('familia', ClassificadorCategoria::paraSlug('Semana da Família'));
        $this->assertSame('campanha', ClassificadorCategoria::paraSlug('Campanha do Agasalho 2026'));
        $this->assertSame('estudo', ClassificadorCategoria::paraSlug('Curso de Passe'));
        $this->assertSame('estudo', ClassificadorCategoria::paraSlug('20º CEMART — Estudo do Evangelho'));
    }

    public function test_titulo_desconhecido_retorna_null(): void
    {
        $this->assertNull(ClassificadorCategoria::paraSlug('Reunião de Diretoria'));
        $this->assertNull(ClassificadorCategoria::paraSlug(''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=ClassificadorCategoriaTest`
Expected: FAIL (classe inexistente).

- [ ] **Step 3: Create the interface**

`app/Importacao/LeitorEventos.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

interface LeitorEventos
{
    /**
     * Retorna todos os eventos lidos do legado, normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function eventos(): array;
}
```

- [ ] **Step 4: Create the classifier**

`app/Importacao/ClassificadorCategoria.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use Illuminate\Support\Str;

/**
 * Infere a categoria pública de um evento a partir do título (o legado não tem
 * campo de categoria). Sem correspondência → null (revisar manualmente no admin).
 */
class ClassificadorCategoria
{
    public static function paraSlug(string $titulo): ?string
    {
        $t = Str::ascii(Str::lower($titulo));

        return match (true) {
            str_contains($t, 'brecho') => 'brecho',
            str_contains($t, 'feirao') || str_contains($t, 'livros') => 'feirao',
            str_contains($t, 'encontro') || str_contains($t, 'familia') => 'familia',
            str_contains($t, 'campanha') => 'campanha',
            str_contains($t, 'curso') || str_contains($t, 'estudo') || str_contains($t, 'cemart') => 'estudo',
            default => null,
        };
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=ClassificadorCategoriaTest`
Expected: PASS (2 testes). Rodar `docker compose exec -T app ./vendor/bin/pint app/Importacao/LeitorEventos.php app/Importacao/ClassificadorCategoria.php tests/Unit/Importacao/ClassificadorCategoriaTest.php`.

- [ ] **Step 6: Commit**

```bash
git add app/Importacao/LeitorEventos.php app/Importacao/ClassificadorCategoria.php tests/Unit/Importacao/ClassificadorCategoriaTest.php
git commit -m "feat(eventos): LeitorEventos (interface) + ClassificadorCategoria por titulo"
```

---

### Task 2: `LeitorEventosMysql` (leitor real do legado, somente leitura)

**Files:**
- Create: `app/Importacao/LeitorEventosMysql.php`

**Interfaces:**
- Consumes: conexão `legado`, `LeitorEventos`.
- Produces: `LeitorEventosMysql implements LeitorEventos` — cada item de `eventos()` tem as chaves: `wp_id`, `titulo`, `slug`, `resumo`, `conteudo`, `data_do_evento` (TS Unix cru), `evento_publico`, `mostrar_horario`, `mostrar_horario_definido` (bool), `local`, `flyer_url`, `galeria_urls` (array ordenado), `departamentos_siglas` (array de nomes de termo = siglas). Estas são exatamente as chaves que o `ImportadorEventos` (Task 3) e o fake de teste consomem.

> **Sem teste automatizado** (convenção do projeto para leitores `*Mysql`): o comportamento é coberto pelo fake na Task 3; o **SQL real é verificado no GATE de merge** (túnel ativo). Não escrever teste que exija a conexão `legado`.

- [ ] **Step 1: Create the reader**

`app/Importacao/LeitorEventosMysql.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorEventosMysql implements LeitorEventos
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function eventos(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_excerpt, post_content, post_status, post_date
             FROM wp_posts
             WHERE post_type = '_evento' AND post_status = 'publish'"
        );

        $out = [];
        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $meta = $this->metasDe($id);

            $thumbId = isset($meta['_thumbnail_id']) && $meta['_thumbnail_id'] !== ''
                ? (int) $meta['_thumbnail_id']
                : null;

            $out[] = [
                'wp_id' => $id,
                'titulo' => $p->post_title,
                'slug' => $p->post_name,
                'resumo' => ($p->post_excerpt !== '' && $p->post_excerpt !== null) ? $p->post_excerpt : null,
                'conteudo' => $p->post_content ?: null,
                'data_do_evento' => $meta['data_do_evento'] ?? null,
                'evento_publico' => $meta['evento_publico'] ?? null,
                'mostrar_horario' => $meta['mostrar_horario'] ?? null,
                'mostrar_horario_definido' => array_key_exists('mostrar_horario', $meta),
                'local' => (($meta['local'] ?? '') !== '') ? $meta['local'] : null,
                'flyer_url' => $thumbId ? $this->urlDaImagem($thumbId) : null,
                'galeria_urls' => $this->galeriaUrls($meta['_galeria-de-imagens'] ?? null),
                'departamentos_siglas' => $this->siglasDepartamento($id),
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (1º valor por chave; resolve duplicatas) */
    private function metasDe(int $postId): array
    {
        $rows = $this->db->select('SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?', [$postId]);
        $m = [];
        foreach ($rows as $r) {
            if (! array_key_exists($r->meta_key, $m)) {
                $m[$r->meta_key] = $r->meta_value;
            }
        }

        return $m;
    }

    /** URL (guid) de um attachment pelo ID. */
    private function urlDaImagem(int $attId): ?string
    {
        $row = $this->db->selectOne(
            'SELECT guid FROM wp_posts WHERE ID = ? AND post_type = ? LIMIT 1',
            [$attId, 'attachment']
        );

        return $row->guid ?? null;
    }

    /** CSV de IDs de attachment → URLs (guid) na ordem, ignorando os não resolvidos. */
    private function galeriaUrls(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        $urls = [];
        foreach (explode(',', $csv) as $raw) {
            $attId = (int) trim($raw);
            if ($attId <= 0) {
                continue;
            }
            $url = $this->urlDaImagem($attId);
            if ($url !== null) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** Nomes dos termos (= siglas de departamento) da taxonomia _departamentos_tax do evento. */
    private function siglasDepartamento(int $postId): array
    {
        $rows = $this->db->select(
            "SELECT t.name FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = '_departamentos_tax'",
            [$postId]
        );

        return array_values(array_map(fn ($r) => trim((string) $r->name), $rows));
    }
}
```

- [ ] **Step 2: Sanity check (sem conexão) — só compila e Pint**

Run: `docker compose exec -T app php -l app/Importacao/LeitorEventosMysql.php` (lint PHP) e `docker compose exec -T app ./vendor/bin/pint app/Importacao/LeitorEventosMysql.php`.
Expected: sem erros de sintaxe/estilo. (A execução real contra o legado é o GATE de merge.)

- [ ] **Step 3: Commit**

```bash
git add app/Importacao/LeitorEventosMysql.php
git commit -m "feat(eventos): LeitorEventosMysql (leitor read-only do CPT _evento)"
```

---

### Task 3: `ImportadorEventos` + testes com fake

**Files:**
- Create: `app/Importacao/ImportadorEventos.php`
- Test: `tests/Feature/Importacao/ImportadorEventosTest.php`

**Interfaces:**
- Consumes: `LeitorEventos`, `BaixadorImagem`, `TransformadorLegado`, `ClassificadorCategoria`, models `Evento`/`CategoriaEvento`/`Departamento`, enum `VisibilidadeEvento`.
- Produces: `ImportadorEventos` — `__construct(LeitorEventos $leitor, BaixadorImagem $baixador)`; `importar(?callable $log = null): array` → `['eventos' => int, 'avisos' => string[]]`. Idempotente (upsert por slug; limpa mídia antes de reanexar).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Importacao;

use App\Enums\VisibilidadeEvento;
use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorEventos;
use App\Importacao\LeitorEventos;
use App\Models\Departamento;
use App\Models\Evento;
use Database\Seeders\CategoriaEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorEventosTest extends TestCase
{
    use RefreshDatabase;

    /** 1x1 PNG válido (evita HTTP/GD real; addMediaFromString aceita). */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function leitor(array $eventos): LeitorEventos
    {
        return new class($eventos) implements LeitorEventos
        {
            public function __construct(private array $eventos) {}

            public function eventos(): array
            {
                return $this->eventos;
            }
        };
    }

    /** Baixador que devolve bytes fixos (sem HTTP), ou null quando a URL é vazia. */
    private function baixador(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixarCapado(?string $url, int $teto = 2000): ?string
            {
                return $url ? base64_decode(ImportadorEventosTest::pngBytes()) : null;
            }
        };
    }

    public static function pngBytes(): string
    {
        return self::PNG_1X1;
    }

    private function eventoLegado(array $overrides = []): array
    {
        return array_merge([
            'wp_id' => 27457,
            'titulo' => 'Brechó Solidário do CEMA – 27 de junho',
            'slug' => 'brecho-solidario-27-de-junho',
            'resumo' => 'Garimpar, ajudar e reencontrar.',
            'conteudo' => '<p>Venha!</p>',
            'data_do_evento' => (string) Carbon::create(2026, 6, 27, 8, 30, 0, 'UTC')->timestamp, // JetEngine grava o relógio local como se fosse UTC
            'evento_publico' => 'true',
            'mostrar_horario' => 'true',
            'mostrar_horario_definido' => true,
            'local' => 'CEMA',
            'flyer_url' => 'https://legado.example/wp-content/uploads/flyer.jpg',
            'galeria_urls' => ['https://legado.example/g1.jpg', 'https://legado.example/g2.jpg'],
            'departamentos_siglas' => ['DEPRO'],
        ], $overrides);
    }

    private function importar(array $eventos): array
    {
        return (new ImportadorEventos($this->leitor($eventos), $this->baixador()))->importar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); // isola a mídia anexada (flyer/galeria) do disco real
        $this->seed(CategoriaEventoSeeder::class);
        Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções e Eventos', 'slug' => 'depro']);
        Departamento::create(['sigla' => 'DED', 'nome' => 'Estudos Doutrinários', 'slug' => 'ded']);
    }

    public function test_importa_evento_publico_com_mapeamento_completo(): void
    {
        $this->importar([$this->eventoLegado()]);

        $evento = Evento::firstWhere('slug', 'brecho-solidario-27-de-junho');
        $this->assertNotNull($evento);
        $this->assertSame(27457, $evento->wp_id);
        $this->assertSame('Garimpar, ajudar e reencontrar.', $evento->resumo);
        $this->assertSame('2026-06-27', $evento->data_inicio->format('Y-m-d'));
        $this->assertSame('08:30', $evento->hora_inicio); // unixParaData: relógio UTC 08:30 reinterpretado como São Paulo
        $this->assertNull($evento->data_fim);
        $this->assertSame(VisibilidadeEvento::Publico, $evento->visibilidade);
        $this->assertSame('brecho', $evento->categoria->slug);
        $this->assertTrue($evento->departamentos->contains('sigla', 'DEPRO'));
        $this->assertTrue($evento->hasMedia(Evento::COLECAO_FLYER));
        $this->assertSame(2, $evento->getMedia(Evento::COLECAO_GALERIA)->count());
    }

    public function test_nao_publico_vira_diretoria_com_aviso(): void
    {
        $resumo = $this->importar([$this->eventoLegado([
            'slug' => 'reuniao-diretoria', 'titulo' => 'Reunião de Diretoria',
            'evento_publico' => 'false', 'flyer_url' => null, 'galeria_urls' => [], 'departamentos_siglas' => [],
        ])]);

        $evento = Evento::firstWhere('slug', 'reuniao-diretoria');
        $this->assertSame(VisibilidadeEvento::Diretoria, $evento->visibilidade);
        $this->assertNull($evento->categoria_evento_id); // "Reunião de Diretoria" não casa nenhuma categoria
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'diretoria')));
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'categoria não inferida')));
    }

    public function test_mostrar_horario_off_zera_a_hora(): void
    {
        $this->importar([$this->eventoLegado([
            'slug' => 'sem-hora', 'mostrar_horario' => 'false', 'mostrar_horario_definido' => true,
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $this->assertNull(Evento::firstWhere('slug', 'sem-hora')->hora_inicio);
    }

    public function test_mostrar_horario_ausente_mantem_a_hora(): void
    {
        $this->importar([$this->eventoLegado([
            'slug' => 'com-hora-default', 'mostrar_horario' => null, 'mostrar_horario_definido' => false,
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $this->assertSame('08:30', Evento::firstWhere('slug', 'com-hora-default')->hora_inicio);
    }

    public function test_departamento_desconhecido_gera_aviso(): void
    {
        $resumo = $this->importar([$this->eventoLegado([
            'slug' => 'depto-x', 'departamentos_siglas' => ['DEPRO', 'DECOM'],
            'flyer_url' => null, 'galeria_urls' => [],
        ])]);

        $evento = Evento::firstWhere('slug', 'depto-x');
        $this->assertTrue($evento->departamentos->contains('sigla', 'DEPRO'));
        $this->assertFalse($evento->departamentos->contains('sigla', 'DECOM'));
        $this->assertNotEmpty(array_filter($resumo['avisos'], fn ($a) => str_contains($a, 'DECOM')));
    }

    public function test_e_idempotente(): void
    {
        $this->importar([$this->eventoLegado()]);
        $this->importar([$this->eventoLegado()]); // 2ª vez

        $this->assertSame(1, Evento::count());
        $evento = Evento::firstWhere('slug', 'brecho-solidario-27-de-junho');
        $this->assertTrue($evento->hasMedia(Evento::COLECAO_FLYER)); // não duplicou
        $this->assertSame(2, $evento->getMedia(Evento::COLECAO_GALERIA)->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=ImportadorEventosTest`
Expected: FAIL (`ImportadorEventos` inexistente).

- [ ] **Step 3: Create the importer**

`app/Importacao/ImportadorEventos.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Importacao;

use App\Enums\VisibilidadeEvento;
use App\Models\CategoriaEvento;
use App\Models\Departamento;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;

class ImportadorEventos
{
    private array $avisos = [];

    public function __construct(
        private LeitorEventos $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $processados = 0;

        foreach ($this->leitor->eventos() as $d) {
            $dataHora = TransformadorLegado::unixParaData($d['data_do_evento'] ?? null);

            if ($dataHora === null) {
                $this->avisos[] = "[{$d['slug']}] sem data_do_evento válida — evento pulado";

                continue;
            }

            DB::transaction(function () use ($d, $dataHora, $log) {
                // Hora: ausente = mostra (default-ON do legado); presente-e-falsy = esconde.
                $mostraHora = ($d['mostrar_horario_definido'] ?? false)
                    ? TransformadorLegado::statusParaAtivo($d['mostrar_horario'] ?? null)
                    : true;

                // Visibilidade: público (true) senão fail-closed em diretoria.
                $publico = TransformadorLegado::statusParaAtivo($d['evento_publico'] ?? null);
                $visibilidade = $publico ? VisibilidadeEvento::Publico : VisibilidadeEvento::Diretoria;
                if (! $publico) {
                    $this->avisos[] = "[{$d['slug']}] evento não-público → visibilidade=diretoria (revisar)";
                }

                // Categoria (heurística pelo título).
                $catSlug = ClassificadorCategoria::paraSlug((string) ($d['titulo'] ?? ''));
                $categoriaId = $catSlug ? CategoriaEvento::where('slug', $catSlug)->value('id') : null;
                if ($catSlug === null) {
                    $this->avisos[] = "[{$d['slug']}] categoria não inferida (revisar no admin)";
                }

                $evento = Evento::updateOrCreate(['slug' => $d['slug']], [
                    'titulo' => $d['titulo'],
                    'resumo' => $d['resumo'] ?? null,
                    'conteudo' => $d['conteudo'] ?? null,
                    'data_inicio' => $dataHora->format('Y-m-d'),
                    'hora_inicio' => $mostraHora ? $dataHora->format('H:i') : null,
                    'data_fim' => null,
                    'hora_fim' => null,
                    'local' => $d['local'] ?? null,
                    'categoria_evento_id' => $categoriaId,
                    'visibilidade' => $visibilidade,
                    'status' => Evento::STATUS_PUBLICADO,
                    'wp_id' => $d['wp_id'],
                ]);

                // Idempotência de mídia: limpa antes de reanexar.
                $evento->clearMediaCollection(Evento::COLECAO_FLYER);
                $evento->clearMediaCollection(Evento::COLECAO_GALERIA);

                if ($d['flyer_url'] ?? null) {
                    $bytes = $this->baixador->baixarCapado($d['flyer_url'], 2000);
                    if ($bytes !== null) {
                        $evento->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($d['flyer_url'], PHP_URL_PATH) ?? 'flyer.jpg'))
                            ->withCustomProperties(['url_legado' => $d['flyer_url']])
                            ->toMediaCollection(Evento::COLECAO_FLYER);
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar flyer";
                    }
                }

                foreach ($d['galeria_urls'] ?? [] as $url) {
                    $bytes = $this->baixador->baixarCapado($url, 2000);
                    if ($bytes === null) {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar imagem da galeria";

                        continue;
                    }
                    $evento->addMediaFromString($bytes)
                        ->usingFileName(basename(parse_url($url, PHP_URL_PATH) ?? 'galeria.jpg'))
                        ->withCustomProperties(['url_legado' => $url])
                        ->toMediaCollection(Evento::COLECAO_GALERIA);
                }

                // Departamentos por sigla.
                $siglas = $d['departamentos_siglas'] ?? [];
                $departamentos = Departamento::whereIn('sigla', $siglas)->get();
                foreach (array_diff($siglas, $departamentos->pluck('sigla')->all()) as $sigla) {
                    $this->avisos[] = "[{$d['slug']}] departamento não resolvido: {$sigla}";
                }
                $evento->departamentos()->sync($departamentos->pluck('id')->all());

                $log("Evento importado: {$d['slug']}");
            });

            $processados++;
        }

        return ['eventos' => $processados, 'avisos' => $this->avisos];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=ImportadorEventosTest`
Expected: PASS (6 testes). Se a asserção de hora `08:30` divergir, **não alterar o valor esperado**: conferir o cálculo real de `TransformadorLegado::unixParaData('1782549000')` e reportar (o TS foi escolhido para bater 27/06/2026 08:30 em America/Sao_Paulo). Rodar Pint nos arquivos tocados.

- [ ] **Step 5: Commit**

```bash
git add app/Importacao/ImportadorEventos.php tests/Feature/Importacao/ImportadorEventosTest.php
git commit -m "feat(eventos): ImportadorEventos idempotente (mapeamento + midia + taxonomia)"
```

---

### Task 4: Comando `cema:importar-eventos` + bind

**Files:**
- Create: `app/Console/Commands/ImportarEventos.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind `LeitorEventos` → `LeitorEventosMysql`)
- Test: `tests/Feature/Importacao/ImportarEventosCommandTest.php`

**Interfaces:**
- Consumes: `LeitorEventos`, `ImportadorEventos`, `LeitorEventosMysql`.
- Produces: comando `cema:importar-eventos` com guarda de túnel (só valida a conexão `legado` quando o leitor real está em uso) + resumo/avisos.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorEventos;
use App\Models\Departamento;
use App\Models\Evento;
use Database\Seeders\CategoriaEventoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportarEventosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        $this->seed(CategoriaEventoSeeder::class);
        Departamento::create(['sigla' => 'DEPRO', 'nome' => 'Promoções', 'slug' => 'depro']);

        // fake do leitor no container (sem tocar o legado; sem imagens p/ manter determinístico)
        $this->app->bind(LeitorEventos::class, fn () => new class implements LeitorEventos
        {
            public function eventos(): array
            {
                return [[
                    'wp_id' => 1, 'titulo' => 'Feirão de Livros', 'slug' => 'feirao-de-livros',
                    'resumo' => null, 'conteudo' => null, 'data_do_evento' => '1782549000',
                    'evento_publico' => 'true', 'mostrar_horario' => 'true', 'mostrar_horario_definido' => true,
                    'local' => 'CEMA', 'flyer_url' => null, 'galeria_urls' => [], 'departamentos_siglas' => ['DEPRO'],
                ]];
            }
        });

        $this->artisan('cema:importar-eventos')
            ->assertSuccessful();

        $this->assertSame(1, Evento::count());
        $this->assertSame('feirao', Evento::firstWhere('slug', 'feirao-de-livros')->categoria->slug);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=ImportarEventosCommandTest`
Expected: FAIL (comando/bind inexistentes).

- [ ] **Step 3: Create the command**

`app/Console/Commands/ImportarEventos.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace App\Console\Commands;

use App\Importacao\ImportadorEventos;
use App\Importacao\LeitorEventos;
use App\Importacao\LeitorEventosMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarEventos extends Command
{
    protected $signature = 'cema:importar-eventos';

    protected $description = 'Importa os eventos (_evento) do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorEventos $leitor, ImportadorEventos $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorEventosMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (\Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $resumo = $importador->importar(fn (string $m) => $this->info($m));

        $this->newLine();
        $this->info("Importação concluída: {$resumo['eventos']} eventos.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) {
                $this->line('  - '.$aviso);
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register the bind**

Em `app/Providers/AppServiceProvider.php`, `register()`, junto dos binds existentes (ver linhas ~31-34), adicionar o import `use App\Importacao\LeitorEventos;` + `use App\Importacao\LeitorEventosMysql;` e a linha:

```php
$this->app->bind(LeitorEventos::class, LeitorEventosMysql::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=ImportarEventosCommandTest`
Expected: PASS. Rodar Pint nos arquivos tocados.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ImportarEventos.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportarEventosCommandTest.php
git commit -m "feat(eventos): comando cema:importar-eventos + bind do leitor"
```

---

### Fechamento da Fase 2 (verificação)

- [ ] **Step 1: Suíte completa + Pint**

Run: `docker compose exec -T app php artisan test` (verde; reexecutar se os 2 flaky de GD do blog aparecerem) e `docker compose exec -T app ./vendor/bin/pint --test`.

- [ ] **Step 2: GATE DE MERGE (bloqueante, do dono — exige TÚNEL SSH ativo)**

O `LeitorEventosMysql` é **Fake-only** na suíte; o SQL real precisa ser conferido contra o legado vivo **antes de mesclar** (memória `verificar-leitor-legado-contra-banco-real`). Com o túnel aberto:

```
docker compose exec app php artisan cema:importar-eventos
```

Conferir: **~54 eventos** importados; rodar **2×** (2ª rodada não duplica — idempotência); revisar os **avisos** (não-públicos→diretoria; categorias não inferidas; departamentos não resolvidos como DECOM). Amostrar 3-5 eventos no `/admin`:
- **data/hora** batem com o site atual (fuso America/Sao_Paulo; sem deslocamento);
- **flyer** e **galeria** anexados (URLs `guid` do host de mídia atual resolveram → 200);
- **departamentos** por sigla e **categoria** inferida corretas;
- os **13 `data_do_evento` duplicados** não causaram problema (o `metasDe` pega o 1º valor);
- o **CSV da galeria** (`_galeria-de-imagens`) explodiu na ordem certa.

**Vigiar (achados a confirmar no legado real):** (a) o `guid` do `_thumbnail_id`/galeria aponta para o host de mídia atual de cemanet.org.br e responde 200; (b) o nome do termo de `_departamentos_tax` é exatamente a sigla (`DED`, `DEPRO`…) — se vier com sufixo/acento, ajustar `siglasDepartamento`; (c) `data_do_evento` é inteiro puro (não serializado).

- [ ] **Step 3: Não mesclar sem** CI verde no último commit + GATE do legado passado + go do dono.

---

## Notas de verificação do plano (self-review)

- **Cobertura do spec §5:** `LeitorEventos`/`LeitorEventosMysql` (Tasks 1-2), `ClassificadorCategoria` (Task 1), `ImportadorEventos` (Task 3), `cema:importar-eventos` + bind (Task 4). Mapeamento: `data_do_evento` unix→`data_inicio`+`hora_inicio` (dedup automático via `metasDe`); `mostrar_horario` off→hora null; `evento_publico`→visibilidade fail-closed; `local`; `_thumbnail_id`→flyer; `_galeria-de-imagens`→galeria (CSV); `_departamentos_tax`→sync por sigla; categoria por heurística. **Fora desta fase:** front público, autorização em runtime, SEO, ICS (Fases 3-4).
- **Idempotência:** upsert por `slug`; `clearMediaCollection` antes de reanexar; teste roda 2× e assere contagem estável. `wp_id` unique dá a 2ª âncora.
- **Leitor `*Mysql` sem teste automatizado** — convenção do projeto (Fake cobre o comportamento; SQL real = GATE). O `php -l` + Pint cobrem sintaxe/estilo.
- **Determinismo dos testes:** fake do leitor (classe anônima) + `BaixadorImagem` stub (bytes fixos, sem HTTP); o caso de mídia usa 1×1 PNG e verifica `hasMedia`/contagem (GD só num teste, minimizando o risco flaky).
- **Riscos deixados para o GATE:** resolvibilidade das URLs de mídia (guid) no host atual; formato exato do nome do termo de departamento; TS inteiro puro. Todos verificáveis só contra o legado vivo.
