# Fase 1 · Plano 2 — Importação das Palestras (`cema:importar-palestras`) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Comando idempotente que migra as 123 palestras (e palestrantes, assuntos, relações e destaques) do banco WordPress **legado** (read-only) para o MySQL novo, baixando as fotos.

**Architecture:** Componentes desacoplados — `TransformadorLegado` (puro), `LeitorLegado` (interface) + `LeitorLegadoMysql` (lê `DB::connection('legado')`), `BaixadorImagem` (URL→storage), `ImportadorPalestras` (upsert idempotente, depende da interface do leitor — testável com um fake) e o comando `cema:importar-palestras` que os orquestra. A idempotência é testada com um leitor fake; só a verificação final roda contra o legado real (exige túnel SSH ativo).

**Tech Stack:** Laravel 13, PHP 8.3, MySQL 8, Carbon, `Illuminate\Support\Facades\Http`/`Storage`. Testes em SQLite in-memory.

## Global Constraints

- Comandos no container: `docker compose exec -T app php artisan ...`. Testes: `docker compose exec -T app php artisan test`.
- **pt-BR** em identificadores de domínio, comentários, mensagens (inclui saída do comando) e commits.
- Cabeçalho de autoria (`// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24`) em cada classe nova de produção (não em testes nem migrations).
- Traduzir docblocks gerados pelo artisan para pt-BR.
- **Fonte = banco `legado`** (read-only, `DB::connection('legado')`). **Nunca** escrever no legado (só SELECT).
- **Idempotente:** upsert por `slug` (palestras, palestrantes, assuntos); re-rodar = mesmo estado, sem duplicar.
- **Mapeamentos confirmados pelo scout (verbatim):**
  - `status_palestrante`: `ativo = strtolower(trim($status)) ∈ {'true','on','1','sim'}` (valores reais: `true`/`on`=ativo, `false`=inativo).
  - `data_da_palestra` (Unix): `Carbon::createFromTimestamp((int)$unix)->setTimezone('America/Sao_Paulo')`.
  - **Direção das relações Jet (oposta!):** 107 → `parent_object_id`=palestrante, `child_object_id`=palestra (papel `palestrante`); 108 → `parent_object_id`=palestra, `child_object_id`=diretor (papel `diretor`).
  - Foto: `_thumbnail_id` → `wp_posts.guid` (URL pública) → baixar para `storage/app/public/palestrantes/`.
  - Repeater `assuntos_principais`: `unserialize` → itens `{destaque, texto}`, `ordem`=índice. (Nenhum item com texto vazio.)
  - Descrição: `descricao` ← `post_content`; `resumo` ← meta `descricao`; `subtitulo` ← `post_excerpt`.
  - `online` ← meta `palestra_online` (`'on'` = true).
- Cardinalidade (já existe `App\Support\Palestras\CardinalidadePalestra`): logar aviso quando uma palestra importada violar 1–2 palestrantes / 0–1 diretor; **não abortar** a importação por isso.
- **Fuso da aplicação:** o app deve usar `America/Sao_Paulo` (`config/app.php` → `'timezone'`) para gravar/exibir `data_da_palestra` no horário de Brasília (ver Task 1, Step 0).

---

### Task 1: `TransformadorLegado` (transformações puras)

**Files:**
- Create: `app/Importacao/TransformadorLegado.php`
- Test: `tests/Unit/Importacao/TransformadorLegadoTest.php`

**Interfaces:**
- Produces: `App\Importacao\TransformadorLegado` com:
  - `public const FUSO = 'America/Sao_Paulo';`
  - `public static function statusParaAtivo(?string $status): bool`
  - `public static function unixParaData(int|string|null $unix): ?\Illuminate\Support\Carbon`
  - `public static function destaquesDoRepeater(?string $serializado): array` — retorna `[['destaque'=>..., 'texto'=>..., 'ordem'=>int], ...]`

- [ ] **Step 0: Configurar o fuso da aplicação**

Em `config/app.php`, troque `'timezone' => 'UTC'` por `'timezone' => 'America/Sao_Paulo'` — garante que `data_da_palestra` seja gravada/exibida no horário de Brasília. Depois: `docker compose exec -T app php artisan config:clear`. (Commit junto com o desta task.)

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Unit/Importacao/TransformadorLegadoTest.php`:

```php
<?php

namespace Tests\Unit\Importacao;

use App\Importacao\TransformadorLegado;
use PHPUnit\Framework\TestCase;

class TransformadorLegadoTest extends TestCase
{
    public function test_status_para_ativo(): void
    {
        $this->assertTrue(TransformadorLegado::statusParaAtivo('true'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('on'));
        $this->assertTrue(TransformadorLegado::statusParaAtivo('TRUE'));
        $this->assertFalse(TransformadorLegado::statusParaAtivo('false'));
        $this->assertFalse(TransformadorLegado::statusParaAtivo(''));
        $this->assertFalse(TransformadorLegado::statusParaAtivo(null));
    }

    public function test_unix_para_data_no_fuso_de_brasilia(): void
    {
        // 1782673200 = domingo 2026-06-28 16:00 em America/Sao_Paulo (-03)
        $data = TransformadorLegado::unixParaData('1782673200');
        $this->assertSame('2026-06-28 16:00:00', $data->format('Y-m-d H:i:s'));
        $this->assertSame('Sunday', $data->format('l'));
        $this->assertNull(TransformadorLegado::unixParaData(null));
        $this->assertNull(TransformadorLegado::unixParaData('0'));
    }

    public function test_destaques_do_repeater(): void
    {
        $serializado = serialize([
            'item-0' => ['destaque' => 'Fé', 'texto' => 'Sobre a fé'],
            'item-1' => ['destaque' => 'Caridade', 'texto' => 'Sobre caridade'],
        ]);
        $destaques = TransformadorLegado::destaquesDoRepeater($serializado);
        $this->assertCount(2, $destaques);
        $this->assertSame(['destaque' => 'Fé', 'texto' => 'Sobre a fé', 'ordem' => 0], $destaques[0]);
        $this->assertSame(1, $destaques[1]['ordem']);

        $this->assertSame([], TransformadorLegado::destaquesDoRepeater(''));
        $this->assertSame([], TransformadorLegado::destaquesDoRepeater(null));
        $this->assertSame([], TransformadorLegado::destaquesDoRepeater('lixo-não-serializado'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=TransformadorLegadoTest`
Expected: FAIL (classe não existe).

- [ ] **Step 3: Implementar a classe**

`app/Importacao/TransformadorLegado.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Carbon;

class TransformadorLegado
{
    public const FUSO = 'America/Sao_Paulo';

    public static function statusParaAtivo(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['true', 'on', '1', 'sim'], true);
    }

    public static function unixParaData(int|string|null $unix): ?Carbon
    {
        $ts = (int) $unix;

        return $ts > 0 ? Carbon::createFromTimestamp($ts)->setTimezone(self::FUSO) : null;
    }

    public static function destaquesDoRepeater(?string $serializado): array
    {
        if (empty($serializado)) {
            return [];
        }

        $dados = @unserialize($serializado);
        if (! is_array($dados)) {
            return [];
        }

        $destaques = [];
        $ordem = 0;
        foreach ($dados as $item) {
            if (! is_array($item)) {
                continue;
            }
            $destaque = trim((string) ($item['destaque'] ?? ''));
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($destaque === '' && $texto === '') {
                continue;
            }
            $destaques[] = ['destaque' => $destaque, 'texto' => $texto, 'ordem' => $ordem++];
        }

        return $destaques;
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=TransformadorLegadoTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Commit**

```bash
git add app/Importacao/TransformadorLegado.php tests/Unit/Importacao/TransformadorLegadoTest.php
git commit -m "feat(importacao): TransformadorLegado (status, data, repeater)"
```

---

### Task 2: `LeitorLegado` (interface) + `LeitorLegadoMysql` (lê o legado)

**Files:**
- Create: `app/Importacao/LeitorLegado.php` (interface)
- Create: `app/Importacao/LeitorLegadoMysql.php`
- Test: `tests/Feature/Importacao/LeitorLegadoMysqlTest.php` (integração — só roda com túnel; ver Step 5)

**Interfaces:**
- Produces: `interface App\Importacao\LeitorLegado` com:
  - `public function assuntos(): array` — `[['nome','slug','parent_slug'=>?string], ...]`
  - `public function palestrantes(): array` — `[['nome','slug','bio'=>?,'email'=>?,'telefone'=>?,'mostrar_email'=>bool,'mostrar_telefone'=>bool,'ativo'=>bool,'foto_url'=>?string], ...]`
  - `public function palestras(): array` — cada item: `['titulo','slug','subtitulo'=>?,'resumo'=>?,'descricao'=>?,'data_da_palestra'=>?Carbon,'online'=>bool,'link_youtube'=>?,'cor_fundo'=>?,'publico_online'=>?int,'publico_presencial'=>?int,'publico_total'=>?int,'status'=>'publicado','palestrantes_slugs'=>[...],'diretor_slug'=>?string,'assuntos_slugs'=>[...],'destaques'=>[['destaque','texto','ordem'],...]]`
- `LeitorLegadoMysql` implementa lendo `DB::connection('legado')` e usando `TransformadorLegado`.

- [ ] **Step 1: Definir a interface**

`app/Importacao/LeitorLegado.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

interface LeitorLegado
{
    /** @return array<int, array<string, mixed>> */
    public function assuntos(): array;

    /** @return array<int, array<string, mixed>> */
    public function palestrantes(): array;

    /** @return array<int, array<string, mixed>> */
    public function palestras(): array;
}
```

- [ ] **Step 2: Implementar `LeitorLegadoMysql`**

`app/Importacao/LeitorLegadoMysql.php` (use `DB::connection('legado')`; o prefixo da conexão é `wp_`, então com SQL cru use os nomes completos `wp_*`). Pontos-chave:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Facades\DB;

class LeitorLegadoMysql implements LeitorLegado
{
    private \Illuminate\Database\ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function assuntos(): array
    {
        $rows = $this->db->select(
            "SELECT t.term_id, t.name, t.slug, tt.parent
             FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = 'assuntos-principais'"
        );
        // mapa term_id -> slug para resolver o parent
        $slugPorId = [];
        foreach ($rows as $r) { $slugPorId[(int) $r->term_id] = $r->slug; }

        return array_map(fn ($r) => [
            'nome' => $r->name,
            'slug' => $r->slug,
            'parent_slug' => ((int) $r->parent) > 0 ? ($slugPorId[(int) $r->parent] ?? null) : null,
        ], $rows);
    }

    public function palestrantes(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content
             FROM wp_posts WHERE post_type='palestrantes' AND post_status='publish'"
        );
        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);
            $out[] = [
                'nome' => $p->post_title,
                'slug' => $p->post_name,
                'bio' => $p->post_content ?: null,
                'email' => $meta['email_palestrante'] ?? null,
                'telefone' => $meta['telefone_palestrante'] ?? null,
                'mostrar_email' => TransformadorLegado::statusParaAtivo($meta['mostrar_email_palestrante'] ?? null),
                'mostrar_telefone' => TransformadorLegado::statusParaAtivo($meta['mostrar_telefone_palestrante'] ?? null),
                'ativo' => TransformadorLegado::statusParaAtivo($meta['status_palestrante'] ?? null),
                'foto_url' => $this->urlDaImagem((int) $p->ID),
            ];
        }

        return $out;
    }

    public function palestras(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_excerpt, post_content, post_status
             FROM wp_posts WHERE post_type='palestra_publica' AND post_status='publish'"
        );
        $out = [];
        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $meta = $this->metasDe($id);
            $out[] = [
                'titulo' => $p->post_title,
                'slug' => $p->post_name,
                'subtitulo' => $p->post_excerpt ?: null,
                'resumo' => $meta['descricao'] ?? null,
                'descricao' => $p->post_content ?: null,
                'data_da_palestra' => TransformadorLegado::unixParaData($meta['data_da_palestra'] ?? null),
                'online' => TransformadorLegado::statusParaAtivo($meta['palestra_online'] ?? null),
                'link_youtube' => $meta['link_do_youtube'] ?? null,
                'cor_fundo' => $meta['escolher_cor_do_fundo'] ?? null,
                'publico_online' => isset($meta['publico_online']) && $meta['publico_online'] !== '' ? (int) $meta['publico_online'] : null,
                'publico_presencial' => isset($meta['publico_presencial']) && $meta['publico_presencial'] !== '' ? (int) $meta['publico_presencial'] : null,
                'publico_total' => isset($meta['publico_total']) && $meta['publico_total'] !== '' ? (int) $meta['publico_total'] : null,
                'status' => 'publicado',
                'palestrantes_slugs' => $this->slugsRelacionados(107, 'child', $id),  // 107: child=palestra, parent=palestrante
                'diretor_slug' => $this->slugsRelacionados(108, 'parent', $id)[0] ?? null, // 108: parent=palestra, child=diretor
                'assuntos_slugs' => $this->assuntosDaPalestra($id),
                'destaques' => TransformadorLegado::destaquesDoRepeater($meta['assuntos_principais'] ?? null),
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (primeiro valor) */
    private function metasDe(int $postId): array
    {
        $rows = $this->db->select('SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ?', [$postId]);
        $m = [];
        foreach ($rows as $r) { if (! array_key_exists($r->meta_key, $m)) { $m[$r->meta_key] = $r->meta_value; } }

        return $m;
    }

    private function urlDaImagem(int $postId): ?string
    {
        $row = $this->db->selectOne(
            "SELECT a.guid FROM wp_postmeta tm
             JOIN wp_posts a ON a.ID = tm.meta_value
             WHERE tm.post_id = ? AND tm.meta_key = '_thumbnail_id' LIMIT 1",
            [$postId]
        );

        return $row->guid ?? null;
    }

    /**
     * Slugs de wp_posts ligados a $palestraId pela relação $relId.
     * $ladoDaPalestra = 'child' (rel 107, palestra é child) ou 'parent' (rel 108, palestra é parent).
     * Retorna os slugs do OUTRO lado (a pessoa).
     * @return array<int,string>
     */
    private function slugsRelacionados(int $relId, string $ladoDaPalestra, int $palestraId): array
    {
        $tabela = "wp_jet_rel_{$relId}";
        [$colPalestra, $colPessoa] = $ladoDaPalestra === 'child'
            ? ['child_object_id', 'parent_object_id']
            : ['parent_object_id', 'child_object_id'];

        $rows = $this->db->select(
            "SELECT pessoa.post_name AS slug
             FROM {$tabela} r JOIN wp_posts pessoa ON pessoa.ID = r.{$colPessoa}
             WHERE r.{$colPalestra} = ? AND pessoa.post_type = 'palestrantes'",
            [$palestraId]
        );

        return array_values(array_filter(array_map(fn ($r) => $r->slug, $rows)));
    }

    /** @return array<int,string> */
    private function assuntosDaPalestra(int $palestraId): array
    {
        $rows = $this->db->select(
            "SELECT t.slug FROM wp_term_relationships tr
             JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             JOIN wp_terms t ON t.term_id = tt.term_id
             WHERE tr.object_id = ? AND tt.taxonomy = 'assuntos-principais'",
            [$palestraId]
        );

        return array_values(array_map(fn ($r) => $r->slug, $rows));
    }
}
```

- [ ] **Step 3: Sanidade da implementação**

Não há teste unit (depende do legado). Confirme visualmente que as queries usam a direção correta das relações (107 child=palestra; 108 parent=palestra) e o prefixo `wp_`.

- [ ] **Step 4: Commit**

```bash
git add app/Importacao/LeitorLegado.php app/Importacao/LeitorLegadoMysql.php
git commit -m "feat(importacao): LeitorLegado + LeitorLegadoMysql (lê o WordPress legado)"
```

- [ ] **Step 5: Verificação de integração (com túnel ativo)**

Run (precisa do túnel SSH aberto):
```
docker compose exec -T app php artisan tinker --execute="\$l=new App\Importacao\LeitorLegadoMysql(); echo 'assuntos='.count(\$l->assuntos()).' palestrantes='.count(\$l->palestrantes()).' palestras='.count(\$l->palestras());"
```
Expected: `assuntos=141 palestrantes=57 palestras=123` (aproximadamente). Se o túnel estiver fechado, falha com erro de conexão — reabrir e repetir. (Esta verificação não bloqueia o commit; é a prova de que o leitor lê o legado real.)

---

### Task 3: `BaixadorImagem` (URL → storage local)

**Files:**
- Create: `app/Importacao/BaixadorImagem.php`
- Test: `tests/Feature/Importacao/BaixadorImagemTest.php`

**Interfaces:**
- Produces: `App\Importacao\BaixadorImagem` com `public function baixar(?string $url, string $slug): ?string` — baixa a imagem para `palestrantes/{slug}.{ext}` no disco `public`; idempotente (não rebaixa se já existe); retorna o caminho relativo salvo ou `null` (url vazia/erro).

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Feature/Importacao/BaixadorImagemTest.php`:

```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BaixadorImagemTest extends TestCase
{
    public function test_baixa_e_salva_a_imagem(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('bytes-da-imagem', 200, ['Content-Type' => 'image/jpeg'])]);

        $caminho = (new BaixadorImagem)->baixar('https://cemanet.org.br/wp-content/uploads/2025/09/Fulano.jpg', 'fulano');

        $this->assertSame('palestrantes/fulano.jpg', $caminho);
        Storage::disk('public')->assertExists('palestrantes/fulano.jpg');
    }

    public function test_idempotente_nao_rebaixa(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('palestrantes/fulano.jpg', 'ja-existe');
        Http::fake();

        $caminho = (new BaixadorImagem)->baixar('https://x/Fulano.jpg', 'fulano');

        $this->assertSame('palestrantes/fulano.jpg', $caminho);
        Http::assertNothingSent();
    }

    public function test_url_vazia_retorna_null(): void
    {
        Storage::fake('public');
        $this->assertNull((new BaixadorImagem)->baixar(null, 'fulano'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=BaixadorImagemTest`
Expected: FAIL.

- [ ] **Step 3: Implementar**

`app/Importacao/BaixadorImagem.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BaixadorImagem
{
    public function baixar(?string $url, string $slug): ?string
    {
        if (empty($url)) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)) ?: 'jpg';
        $caminho = "palestrantes/{$slug}.{$ext}";
        $disco = Storage::disk('public');

        if ($disco->exists($caminho)) {
            return $caminho;
        }

        try {
            $resposta = Http::timeout(30)->get($url);
            if (! $resposta->successful()) {
                return null;
            }
            $disco->put($caminho, $resposta->body());

            return $caminho;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=BaixadorImagemTest`
Expected: PASS (3 testes).

- [ ] **Step 5: Commit**

```bash
git add app/Importacao/BaixadorImagem.php tests/Feature/Importacao/BaixadorImagemTest.php
git commit -m "feat(importacao): BaixadorImagem (URL -> storage, idempotente)"
```

---

### Task 4: `ImportadorPalestras` (upsert idempotente) — núcleo

**Files:**
- Create: `app/Importacao/ImportadorPalestras.php`
- Test: `tests/Feature/Importacao/ImportadorPalestrasTest.php` (usa um `LeitorLegado` fake + idempotência)

**Interfaces:**
- Consumes: `LeitorLegado` (Task 2), `BaixadorImagem` (Task 3), models do Plano 1, `CardinalidadePalestra`.
- Produces: `App\Importacao\ImportadorPalestras` com `public function __construct(LeitorLegado $leitor, BaixadorImagem $baixador)` e `public function importar(?callable $log = null): array` — retorna um resumo `['assuntos'=>int,'palestrantes'=>int,'palestras'=>int,'avisos'=>array]`.

- [ ] **Step 1: Escrever o teste (falha primeiro)** — fake leitor + idempotência

Create `tests/Feature/Importacao/ImportadorPalestrasTest.php`:

```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorPalestras;
use App\Importacao\LeitorLegado;
use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorPalestrasTest extends TestCase
{
    use RefreshDatabase;

    private function leitorFake(): LeitorLegado
    {
        return new class implements LeitorLegado
        {
            public function assuntos(): array
            {
                return [
                    ['nome' => 'Espiritismo', 'slug' => 'espiritismo', 'parent_slug' => null],
                    ['nome' => 'Fé', 'slug' => 'fe', 'parent_slug' => 'espiritismo'],
                ];
            }

            public function palestrantes(): array
            {
                return [
                    ['nome' => 'Ana', 'slug' => 'ana', 'bio' => '<p>bio</p>', 'email' => 'ana@x.org', 'telefone' => null, 'mostrar_email' => true, 'mostrar_telefone' => false, 'ativo' => true, 'foto_url' => 'https://x/ana.jpg'],
                    ['nome' => 'Diretor Bruno', 'slug' => 'bruno', 'bio' => null, 'email' => null, 'telefone' => null, 'mostrar_email' => false, 'mostrar_telefone' => false, 'ativo' => false, 'foto_url' => null],
                ];
            }

            public function palestras(): array
            {
                return [[
                    'titulo' => 'Auxílios do Invisível', 'slug' => 'auxilios', 'subtitulo' => 'sub', 'resumo' => 'res',
                    'descricao' => '<p>corpo</p>', 'data_da_palestra' => Carbon::parse('2026-06-28 16:00:00'),
                    'online' => true, 'link_youtube' => 'https://youtube.com/live/abc', 'cor_fundo' => '#89ab98',
                    'publico_online' => 10, 'publico_presencial' => 20, 'publico_total' => 30, 'status' => 'publicado',
                    'palestrantes_slugs' => ['ana'], 'diretor_slug' => 'bruno', 'assuntos_slugs' => ['fe'],
                    'destaques' => [['destaque' => 'Fé', 'texto' => 'sobre fé', 'ordem' => 0]],
                ]];
            }
        };
    }

    public function test_importa_e_e_idempotente(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('img', 200)]);
        $importador = new ImportadorPalestras($this->leitorFake(), new BaixadorImagem);

        // roda 2x
        $importador->importar();
        $resumo = $importador->importar();

        // contagens não duplicam
        $this->assertSame(2, Assunto::count());
        $this->assertSame(2, Palestrante::count());
        $this->assertSame(1, Palestra::count());

        $palestra = Palestra::first();
        $this->assertCount(1, $palestra->palestrantesAtivos);          // Ana (ativa)
        $this->assertSame('bruno', $palestra->diretor->slug);          // Bruno (diretor)
        $this->assertSame(['fe'], $palestra->assuntos->pluck('slug')->all());
        $this->assertCount(1, $palestra->destaques);
        $this->assertSame('espiritismo', Assunto::where('slug', 'fe')->first()->parent->slug);
        $this->assertSame('publicado', $palestra->status);
        $this->assertSame('2026-06-28 16:00:00', $palestra->data_da_palestra->format('Y-m-d H:i:s'));
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorPalestrasTest`
Expected: FAIL.

- [ ] **Step 3: Implementar o importador**

`app/Importacao/ImportadorPalestras.php` — upsert por slug em transação; resolve relações por slug; cardinalidade gera aviso (não aborta). Estrutura:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Importacao;

use App\Models\Assunto;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Support\Palestras\CardinalidadePalestra;
use Illuminate\Support\Facades\DB;

class ImportadorPalestras
{
    private array $avisos = [];

    public function __construct(
        private LeitorLegado $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];

        $log('Importando assuntos...');
        $nAssuntos = $this->importarAssuntos();

        $log('Importando palestrantes...');
        $nPalestrantes = $this->importarPalestrantes($log);

        $log('Importando palestras...');
        $nPalestras = $this->importarPalestras($log);

        return [
            'assuntos' => $nAssuntos,
            'palestrantes' => $nPalestrantes,
            'palestras' => $nPalestras,
            'avisos' => $this->avisos,
        ];
    }

    private function importarAssuntos(): int
    {
        $dados = $this->leitor->assuntos();
        // 1ª passada: upsert sem parent
        foreach ($dados as $a) {
            Assunto::updateOrCreate(['slug' => $a['slug']], ['nome' => $a['nome']]);
        }
        // 2ª passada: resolve parent_id por slug
        foreach ($dados as $a) {
            if (! empty($a['parent_slug'])) {
                $pai = Assunto::where('slug', $a['parent_slug'])->first();
                if ($pai) {
                    Assunto::where('slug', $a['slug'])->update(['parent_id' => $pai->id]);
                }
            }
        }

        return count($dados);
    }

    private function importarPalestrantes(callable $log): int
    {
        $dados = $this->leitor->palestrantes();
        foreach ($dados as $p) {
            $foto = $this->baixador->baixar($p['foto_url'] ?? null, $p['slug']);
            Palestrante::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'nome' => $p['nome'],
                    'bio' => $p['bio'] ?? null,
                    'email' => $p['email'] ?? null,
                    'telefone' => $p['telefone'] ?? null,
                    'mostrar_email' => $p['mostrar_email'] ?? false,
                    'mostrar_telefone' => $p['mostrar_telefone'] ?? false,
                    'ativo' => $p['ativo'] ?? true,
                    'foto' => $foto,  // mantém a foto baixada; se null e já havia, preserve (ver nota abaixo)
                ]
            );
        }

        return count($dados);
    }

    private function importarPalestras(callable $log): int
    {
        $dados = $this->leitor->palestras();
        foreach ($dados as $d) {
            DB::transaction(function () use ($d) {
                $palestra = Palestra::updateOrCreate(
                    ['slug' => $d['slug']],
                    [
                        'titulo' => $d['titulo'], 'subtitulo' => $d['subtitulo'] ?? null,
                        'resumo' => $d['resumo'] ?? null, 'descricao' => $d['descricao'] ?? null,
                        'data_da_palestra' => $d['data_da_palestra'], 'online' => $d['online'] ?? false,
                        'link_youtube' => $d['link_youtube'] ?? null, 'cor_fundo' => $d['cor_fundo'] ?? null,
                        'publico_online' => $d['publico_online'] ?? null, 'publico_presencial' => $d['publico_presencial'] ?? null,
                        'publico_total' => $d['publico_total'] ?? null, 'status' => $d['status'] ?? 'publicado',
                    ]
                );

                // pivô palestra_pessoa (sync por papel) — resolve por slug
                $sync = [];
                foreach ($d['palestrantes_slugs'] as $slug) {
                    $pid = Palestrante::where('slug', $slug)->value('id');
                    if ($pid) { $sync[$pid] = ['papel' => Palestra::PAPEL_PALESTRANTE]; }
                }
                if (! empty($d['diretor_slug'])) {
                    $did = Palestrante::where('slug', $d['diretor_slug'])->value('id');
                    if ($did) { $sync[$did] = ['papel' => Palestra::PAPEL_DIRETOR]; }
                }
                $palestra->palestrantes()->sync($sync);

                // cardinalidade -> aviso (não aborta)
                $nPal = collect($sync)->where('papel', Palestra::PAPEL_PALESTRANTE)->count();
                $nDir = collect($sync)->where('papel', Palestra::PAPEL_DIRETOR)->count();
                foreach (CardinalidadePalestra::erros($nPal, $nDir) as $erro) {
                    $this->avisos[] = "[{$d['slug']}] {$erro}";
                }

                // assuntos (N:N) por slug
                $assuntoIds = Assunto::whereIn('slug', $d['assuntos_slugs'])->pluck('id')->all();
                $palestra->assuntos()->sync($assuntoIds);

                // destaques (replace)
                $palestra->destaques()->delete();
                foreach ($d['destaques'] as $dest) {
                    $palestra->destaques()->create($dest);
                }
            });
        }

        return count($dados);
    }
}
```

> **Nota de implementação (foto idempotente):** quando `foto_url` é null mas o palestrante já tem foto salva, não sobrescreva com null. Se o teste de idempotência exigir, ajuste o `updateOrCreate` para só setar `foto` quando `$foto !== null`. Garanta que o `test_importa_e_e_idempotente` passe — ele é a fonte da verdade.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorPalestrasTest`
Expected: PASS. Ajuste a lógica de `foto`/`sync` até passar (idempotência sem duplicar).

- [ ] **Step 5: Commit**

```bash
git add app/Importacao/ImportadorPalestras.php tests/Feature/Importacao/ImportadorPalestrasTest.php
git commit -m "feat(importacao): ImportadorPalestras (upsert idempotente)"
```

---

### Task 5: Comando `cema:importar-palestras`

**Files:**
- Create: `app/Console/Commands/ImportarPalestras.php`
- Test: `tests/Feature/Importacao/ImportarPalestrasCommandTest.php`

**Interfaces:**
- Consumes: `ImportadorPalestras`, `LeitorLegadoMysql`, `BaixadorImagem`.
- Produces: comando `cema:importar-palestras` que valida a conexão `legado`, roda o importador com log no console e imprime o resumo. Em teste, o `LeitorLegado` é trocado por um fake via container binding.

- [ ] **Step 1: Escrever o teste (falha primeiro)**

Create `tests/Feature/Importacao/ImportarPalestrasCommandTest.php`:

```php
<?php

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorLegado;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarPalestrasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_usando_o_leitor_injetado(): void
    {
        Storage::fake('public');
        // injeta um leitor fake no container (evita depender do legado)
        $this->app->bind(LeitorLegado::class, fn () => new class implements LeitorLegado
        {
            public function assuntos(): array { return [['nome' => 'Fé', 'slug' => 'fe', 'parent_slug' => null]]; }

            public function palestrantes(): array { return [['nome' => 'Ana', 'slug' => 'ana', 'bio' => null, 'email' => null, 'telefone' => null, 'mostrar_email' => false, 'mostrar_telefone' => false, 'ativo' => true, 'foto_url' => null]]; }

            public function palestras(): array { return [['titulo' => 'T', 'slug' => 't', 'subtitulo' => null, 'resumo' => null, 'descricao' => null, 'data_da_palestra' => Carbon::parse('2026-06-28 16:00:00'), 'online' => false, 'link_youtube' => null, 'cor_fundo' => null, 'publico_online' => null, 'publico_presencial' => null, 'publico_total' => null, 'status' => 'publicado', 'palestrantes_slugs' => ['ana'], 'diretor_slug' => null, 'assuntos_slugs' => ['fe'], 'destaques' => []]]; }
        });

        $this->artisan('cema:importar-palestras')
            ->expectsOutputToContain('Importação concluída')
            ->assertExitCode(0);

        $this->assertSame(1, Palestra::count());
    }
}
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportarPalestrasCommandTest`
Expected: FAIL (comando não existe).

- [ ] **Step 3: Bind padrão do leitor + comando**

Em `app/Providers/AppServiceProvider.php`, no método `register()`, ligue a interface à implementação MySQL (o teste sobrescreve com o fake):

```php
$this->app->bind(\App\Importacao\LeitorLegado::class, \App\Importacao\LeitorLegadoMysql::class);
```

`app/Console/Commands/ImportarPalestras.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Console\Commands;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorPalestras;
use App\Importacao\LeitorLegado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarPalestras extends Command
{
    protected $signature = 'cema:importar-palestras';

    protected $description = 'Importa as palestras do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorLegado $leitor, BaixadorImagem $baixador): int
    {
        // valida a conexão legado (túnel SSH ativo?)
        try {
            DB::connection('legado')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
            $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
            $this->line('Detalhe: '.$e->getMessage());

            return self::FAILURE;
        }

        $resumo = (new ImportadorPalestras($leitor, $baixador))->importar(fn (string $m) => $this->info($m));

        $this->newLine();
        $this->info("Importação concluída: {$resumo['assuntos']} assuntos, {$resumo['palestrantes']} palestrantes, {$resumo['palestras']} palestras.");
        if (! empty($resumo['avisos'])) {
            $this->warn('Avisos de cardinalidade ('.count($resumo['avisos']).'):');
            foreach ($resumo['avisos'] as $aviso) { $this->line('  - '.$aviso); }
        }

        return self::SUCCESS;
    }
}
```

> O teste sobrescreve `LeitorLegado` com um fake e usa SQLite, então o `getPdo()` da conexão `legado` não é alcançado no teste? **Atenção:** o `handle` chama `DB::connection('legado')->getPdo()` mesmo no teste. Para o teste não exigir o legado, faça a checagem de conexão condicional: pule-a quando o leitor injetado **não** for `LeitorLegadoMysql` (ex.: `if ($leitor instanceof \App\Importacao\LeitorLegadoMysql) { ...valida pdo... }`). Implemente assim para o teste passar sem túnel.

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportarPalestrasCommandTest`
Expected: PASS.

- [ ] **Step 5: Suíte completa + Commit**

Run: `docker compose exec -T app php artisan test` → tudo verde.
```bash
git add app/Console/Commands/ImportarPalestras.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportarPalestrasCommandTest.php
git commit -m "feat(importacao): comando cema:importar-palestras"
```

---

### Task 6: Execução real contra o legado + verificação

> Requer **túnel SSH ativo**. Não cria testes novos — é a verificação ponta a ponta.

- [ ] **Step 1: Garantir o ambiente**

Run: `docker compose exec -T app php artisan migrate` (tabelas no MySQL de dev, se ainda não aplicadas) e confirme o túnel: porta 3307 aberta.

- [ ] **Step 2: Rodar a importação**

Run: `docker compose exec -T app php artisan cema:importar-palestras`
Expected: "Importação concluída: ~141 assuntos, 57 palestrantes, 123 palestras."

- [ ] **Step 3: Conferir os números no banco novo**

Run:
```
docker compose exec -T app php artisan tinker --execute="echo 'palestras='.App\Models\Palestra::count().' palestrantes='.App\Models\Palestrante::count().' assuntos='.App\Models\Assunto::count().' ativos='.App\Models\Palestrante::ativo()->count();"
```
Expected: `palestras=123 palestrantes=57 assuntos=141 ativos=55`.

- [ ] **Step 4: Conferir idempotência (rodar 2× = mesmo estado)**

Run o comando de novo e re-confira as contagens — devem ser **idênticas** (sem duplicar).

- [ ] **Step 5: Conferir um caso real (relações + destaques)**

Run:
```
docker compose exec -T app php artisan tinker --execute="\$p=App\Models\Palestra::where('slug','auxilios-do-invisivel')->first(); echo \$p->titulo.' | palestrantes='.\$p->palestrantesAtivos->pluck('nome')->join(', ').' | diretor='.optional(\$p->diretor)->nome.' | assuntos='.\$p->assuntos->count().' | destaques='.\$p->destaques->count().' | data='.\$p->data_da_palestra->format('Y-m-d H:i');"
```
Expected: dados coerentes (palestrante(s), data num domingo, assuntos e destaques).

- [ ] **Step 6: Storage público + link**

Run: `docker compose exec -T app php artisan storage:link` (se ainda não existir) — para as fotos baixadas ficarem acessíveis em `/storage/palestrantes/...`.

## Verificação final do Plano 2

- [ ] `docker compose exec -T app php artisan test` verde.
- [ ] `docker compose exec -T app ./vendor/bin/pint --test` sem violações.
- [ ] As 123 palestras, 57 palestrantes (55 ativos) e ~141 assuntos no banco novo; idempotência confirmada (2 execuções, mesmo estado).

> **Próximo plano:** Plano 3 (Filament Resources) — admin para gerir o que foi importado.
