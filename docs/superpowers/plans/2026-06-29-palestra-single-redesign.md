# Redesign do single de palestra + slide/referências/curtidas — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o single de palestra redesenhado (handoff `design_handoff_palestra_single`) com campo de slide (download direto do Drive), referências bibliográficas em destaque, "vídeo em breve", curtir com contador, calendário `.ics` e relacionadas por assunto.

**Architecture:** Backend em colunas/relação novas (`slide`, `duracao`, `referencias_evangelicas`, `curtidas`, tabela `palestra_referencias`); helper `LinkDrive` deriva o link de download (accessor, não-destrutivo); importação idempotente lê `_slides`; admin Filament ganha os campos; front é Blade + Alpine reusando `x-layout.app`/`x-ui.particulas`, com um componente Livewire isolado só para o curtir.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · Livewire 4 · Tailwind v4 · Blade/Alpine · MySQL 8 (Docker). Spec: [docs/superpowers/specs/2026-06-29-palestra-single-redesign-design.md](../specs/2026-06-29-palestra-single-redesign-design.md).

## Global Constraints

- 🚫 **Banco**: só `php artisan migrate` incremental. **NUNCA** `migrate:fresh/refresh/wipe/reset` nem seed destrutivo (apaga os dados importados).
- **Testes**: `docker compose exec -T app php artisan test` (alvo: `--filter=Nome`). Build de assets no host: `npm run build`.
- **Idioma**: tudo em pt-BR (código de domínio, labels, mensagens, commits).
- **Importação idempotente** por `slug`; origem legado é somente leitura.
- **`$fillable`** deve conter os campos novos **antes** de importar/salvar (senão `updateOrCreate` descarta em silêncio).
- **LinkDrive**: decide "é Drive" pelo **host** primeiro; não-Drive e `/drive/folders/` ficam intactos.
- **Calendário**: converter `->utc()` antes de `format('Ymd\THis\Z')` (a data é hora de parede `America/Sao_Paulo`).
- **Thumb**: usar `mqdefault`/`hqdefault` (nunca `maxresdefault` cru).
- **Repeater `referencias`** exige a relação existir: ordem migration→model→relação→Repeater.
- **Preservar testes verdes**: hero mantém classes `from-primary to-footer-bg` + `cema-hero-deco` (via `x-ui.particulas`) e **não** aplica `cor_fundo`; barra de compartilhar mantém `wa.me`, `facebook.com/sharer`, `Copiar link`, `x-data`; JSON-LD `Event` continua.
- **Curtir**: gate de dedup por navegador usa o `$persist` com chave `curtida_palestra_{id}`.
- Cabeçalho de autoria nos arquivos novos: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29`.

---

### Task 1: Dados base — migrations + models

**Files:**
- Create: `database/migrations/2026_06_29_100001_add_slide_duracao_refs_curtidas_to_palestras.php`
- Create: `database/migrations/2026_06_29_100002_create_palestra_referencias_table.php`
- Create: `app/Models/PalestraReferencia.php`
- Modify: `app/Models/Palestra.php`
- Test: `tests/Feature/Models/PalestraReferenciasTest.php`

**Interfaces:**
- Produces: colunas `palestras.slide|duracao|referencias_evangelicas` (nullable) e `palestras.curtidas` (unsignedInteger default 0); tabela `palestra_referencias(obra, autor?, nota?, ordem)`; `Palestra::referencias(): HasMany` (ordenada por `ordem`); model `PalestraReferencia` (`$fillable = ['obra','autor','nota','ordem']`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Models;

use App\Models\Palestra;
use App\Models\PalestraReferencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraReferenciasTest extends TestCase
{
    use RefreshDatabase;

    public function test_palestra_tem_referencias_ordenadas(): void
    {
        $palestra = Palestra::factory()->create();
        $palestra->referencias()->create(['obra' => 'O Evangelho', 'autor' => 'Kardec', 'nota' => 'b', 'ordem' => 1]);
        $palestra->referencias()->create(['obra' => 'O Livro dos Espíritos', 'autor' => 'Kardec', 'nota' => 'a', 'ordem' => 0]);

        $obras = $palestra->refresh()->referencias->pluck('obra')->all();

        $this->assertSame(['O Livro dos Espíritos', 'O Evangelho'], $obras);
    }

    public function test_campos_novos_sao_mass_assignable(): void
    {
        $palestra = Palestra::factory()->create([
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
            'duracao' => '≈1h10',
            'referencias_evangelicas' => 'João 14.',
            'curtidas' => 5,
        ]);

        $this->assertDatabaseHas('palestras', ['id' => $palestra->id, 'duracao' => '≈1h10', 'curtidas' => 5]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=PalestraReferenciasTest`
Expected: FAIL (coluna/relção inexistente; `PalestraReferencia` não existe).

- [ ] **Step 3: Create the alter migration**

`database/migrations/2026_06_29_100001_add_slide_duracao_refs_curtidas_to_palestras.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->string('slide')->nullable()->after('link_youtube');
            $table->string('duracao', 40)->nullable()->after('slide');
            $table->text('referencias_evangelicas')->nullable()->after('descricao');
            $table->unsignedInteger('curtidas')->default(0)->after('publico_total');
        });
    }

    public function down(): void
    {
        Schema::table('palestras', function (Blueprint $table) {
            $table->dropColumn(['slide', 'duracao', 'referencias_evangelicas', 'curtidas']);
        });
    }
};
```

- [ ] **Step 4: Create the referencias migration**

`database/migrations/2026_06_29_100002_create_palestra_referencias_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('palestra_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('palestra_id')->constrained('palestras')->cascadeOnDelete();
            $table->string('obra');
            $table->string('autor')->nullable();
            $table->text('nota')->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('palestra_referencias');
    }
};
```

- [ ] **Step 5: Create the `PalestraReferencia` model**

`app/Models/PalestraReferencia.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalestraReferencia extends Model
{
    use HasFactory;

    protected $fillable = ['obra', 'autor', 'nota', 'ordem'];

    public function palestra(): BelongsTo
    {
        return $this->belongsTo(Palestra::class);
    }
}
```

- [ ] **Step 6: Wire `Palestra` ($fillable + relação)**

Em `app/Models/Palestra.php`: **inserir** no `$fillable` (sem remover nenhuma chave existente) os itens `'slide', 'duracao', 'referencias_evangelicas', 'curtidas'`; `use Illuminate\Database\Eloquent\Relations\HasMany;` já está importado; e adicionar o método:

```php
    public function referencias(): HasMany
    {
        return $this->hasMany(PalestraReferencia::class)->orderBy('ordem');
    }
```

`$fillable` final:

```php
    protected $fillable = [
        'titulo', 'slug', 'subtitulo', 'resumo', 'descricao', 'data_da_palestra',
        'online', 'link_youtube', 'slide', 'duracao', 'referencias_evangelicas',
        'cor_fundo', 'publico_online', 'publico_presencial', 'publico_total', 'curtidas', 'status',
    ];
```

- [ ] **Step 7: Migrate (incremental) and run the test**

Run: `docker compose exec -T app php artisan migrate --force && docker compose exec -T app php artisan test --filter=PalestraReferenciasTest`
Expected: migrations rodam (sem fresh) e o teste PASSA.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_29_1000*_*.php app/Models/PalestraReferencia.php app/Models/Palestra.php tests/Feature/Models/PalestraReferenciasTest.php
git commit -m "feat(palestra): colunas slide/duracao/refs/curtidas + tabela palestra_referencias"
```

---

### Task 2: `LinkDrive` + accessor `slide_download_url`

**Files:**
- Create: `app/Support/Palestras/LinkDrive.php`
- Modify: `app/Models/Palestra.php`
- Test: `tests/Unit/Palestras/LinkDriveTest.php`

**Interfaces:**
- Consumes: nada (helper puro).
- Produces: `LinkDrive::paraDownload(?string $link): ?string` (host-first, idempotente); accessor `Palestra->slide_download_url` (string|null).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Palestras;

use App\Support\Palestras\LinkDrive;
use PHPUnit\Framework\TestCase;

class LinkDriveTest extends TestCase
{
    public function test_file_d_vira_download(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing')
        );
    }

    public function test_open_id_vira_download(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/open?id=1ABCdefg_hij')
        );
    }

    public function test_idempotente_com_amp_encodado(): void
    {
        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            LinkDrive::paraDownload('https://drive.google.com/uc?export=download&amp;id=1ABCdefg_hij')
        );
    }

    public function test_pasta_do_drive_fica_intacta(): void
    {
        $url = 'https://drive.google.com/drive/folders/1ABCdefg_hijKLMNOpqrs';
        $this->assertSame($url, LinkDrive::paraDownload($url));
    }

    public function test_nao_drive_com_token_longo_fica_intacto(): void
    {
        $url = 'https://www.dropbox.com/s/AAAAAAAAAAAAAAAAAAAAAAAAAA/x.pptx';
        $this->assertSame($url, LinkDrive::paraDownload($url));
    }

    public function test_nao_drive_simples_fica_intacto(): void
    {
        $this->assertSame('https://exemplo.com/arquivo.pptx', LinkDrive::paraDownload('https://exemplo.com/arquivo.pptx'));
    }

    public function test_vazio_e_nulo_viram_nulo(): void
    {
        $this->assertNull(LinkDrive::paraDownload(null));
        $this->assertNull(LinkDrive::paraDownload('   '));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=LinkDriveTest`
Expected: FAIL (`LinkDrive` não existe).

- [ ] **Step 3: Implement `LinkDrive`**

`app/Support/Palestras/LinkDrive.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Support\Palestras;

class LinkDrive
{
    /** Converte um link do Google Drive em link de download direto. Não-Drive fica intacto. */
    public static function paraDownload(?string $link): ?string
    {
        if ($link === null || trim($link) === '') {
            return null;
        }

        $link = trim(html_entity_decode($link, ENT_QUOTES | ENT_HTML5));
        $host = parse_url($link, PHP_URL_HOST) ?: '';

        // "É Drive?" decide pelo host — nunca tentamos extrair ID de outro host.
        if (! str_contains($host, 'drive.google.com')) {
            return $link;
        }

        // Pasta não baixa via uc?export=download.
        if (str_contains($link, '/drive/folders/')) {
            return $link;
        }

        $id = self::extrairId($link);

        return $id !== null
            ? "https://drive.google.com/uc?export=download&id={$id}"
            : $link;
    }

    private static function extrairId(string $link): ?string
    {
        if (preg_match('/[?&]id=([A-Za-z0-9_-]{10,})/', $link, $m)) {
            return $m[1];
        }
        if (preg_match('#/file/d/([A-Za-z0-9_-]{10,})#', $link, $m)) {
            return $m[1];
        }
        if (preg_match('/([A-Za-z0-9_-]{25,})/', $link, $m)) {
            return $m[1];
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=LinkDriveTest`
Expected: PASS (7 testes).

- [ ] **Step 5: Add the accessor + its test**

Em `app/Models/Palestra.php`, adicionar `use App\Support\Palestras\LinkDrive;` e o accessor:

```php
    /** Link de download direto do slide (derivado do link cru), ou null. */
    protected function slideDownloadUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => LinkDrive::paraDownload($this->slide));
    }
```

Acrescentar ao `tests/Feature/Models/PalestraReferenciasTest.php`:

```php
    public function test_slide_download_url_deriva_do_link_cru(): void
    {
        $palestra = \App\Models\Palestra::factory()->create([
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing',
        ]);

        $this->assertSame(
            'https://drive.google.com/uc?export=download&id=1ABCdefg_hij',
            $palestra->slide_download_url
        );
    }
```

- [ ] **Step 6: Run both suites**

Run: `docker compose exec -T app php artisan test --filter=LinkDriveTest && docker compose exec -T app php artisan test --filter=PalestraReferenciasTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Support/Palestras/LinkDrive.php app/Models/Palestra.php tests/Unit/Palestras/LinkDriveTest.php tests/Feature/Models/PalestraReferenciasTest.php
git commit -m "feat(palestra): LinkDrive (Drive->download direto) + accessor slide_download_url"
```

---

### Task 3: Importação do `_slides`

**Files:**
- Modify: `app/Importacao/LeitorLegadoMysql.php:74-92`
- Modify: `app/Importacao/ImportadorPalestras.php:104-120`
- Test: `tests/Feature/Importacao/ImportarPalestrasCommandTest.php:32-42`

**Interfaces:**
- Consumes: `LinkDrive` (Task 2) via accessor; `metasDe()` (já existe).
- Produces: cada item de `palestras()` ganha a chave `'slide'`; `updateOrCreate` grava `slide`.

- [ ] **Step 1: Update the command test (failing)**

Em `tests/Feature/Importacao/ImportarPalestrasCommandTest.php`, no `palestras()` do leitor fake, acrescentar a chave `slide` ao array e, após o `assertSame(1, Palestra::count())`, asserir o tratamento:

```php
            public function palestras(): array
            {
                return [['titulo' => 'T', 'slug' => 't', 'subtitulo' => null, 'resumo' => null, 'descricao' => null, 'data_da_palestra' => Carbon::parse('2026-06-28 16:00:00'), 'online' => false, 'link_youtube' => null, 'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing', 'cor_fundo' => null, 'publico_online' => null, 'publico_presencial' => null, 'publico_total' => null, 'status' => 'publicado', 'palestrantes_slugs' => ['ana'], 'diretor_slug' => null, 'assuntos_slugs' => ['fe'], 'destaques' => []]];
            }
```

E ao final do método de teste:

```php
        $this->assertSame(1, Palestra::count());

        $palestra = Palestra::first();
        $this->assertSame('https://drive.google.com/file/d/1ABCdefg_hij/view?usp=sharing', $palestra->slide);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1ABCdefg_hij', $palestra->slide_download_url);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=ImportarPalestrasCommandTest`
Expected: FAIL (`slide` salvo como null — importador não grava).

- [ ] **Step 3: Read `_slides` in the leitor**

Em `app/Importacao/LeitorLegadoMysql.php`, dentro do `foreach` de `palestras()`, acrescentar a chave ao array `$out[]` (logo após `'link_youtube'`):

```php
                'slide' => html_entity_decode((string) ($meta['_slides'] ?? ''), ENT_QUOTES | ENT_HTML5) ?: null,
```

- [ ] **Step 4: Persist `slide` in the importador**

Em `app/Importacao/ImportadorPalestras.php`, no array do `updateOrCreate` (após `'link_youtube' => $d['link_youtube'] ?? null,`):

```php
                        'slide' => $d['slide'] ?? null,
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=ImportarPalestrasCommandTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Importacao/LeitorLegadoMysql.php app/Importacao/ImportadorPalestras.php tests/Feature/Importacao/ImportarPalestrasCommandTest.php
git commit -m "feat(import): importa _slides do legado para palestras.slide (idempotente)"
```

> Após mergear a fatia, rodar `docker compose exec -T app php artisan cema:importar-palestras` (túnel aberto) para trazer os 19 slides — **nunca** `migrate:fresh`.

---

### Task 4: Admin Filament — campos slide/duração/referências

**Files:**
- Modify: `app/Filament/Resources/Palestras/PalestraResource.php:117-164`
- Test: `tests/Feature/Filament/PalestraResourceTest.php`

**Interfaces:**
- Consumes: relação `Palestra::referencias()` (Task 1).
- Produces: campos `slide`, `duracao`, `referencias_evangelicas` e Repeater `referencias` no form.

- [ ] **Step 1: Write the failing test**

Acrescentar a `tests/Feature/Filament/PalestraResourceTest.php`:

```php
    public function test_cria_palestra_com_slide_duracao_e_referencias(): void
    {
        $p1 = \App\Models\Palestrante::factory()->ativo()->create();

        \Livewire\Livewire::test(\App\Filament\Resources\Palestras\Pages\CreatePalestra::class)
            ->fillForm([
                'titulo' => 'Com Slide',
                'slug' => 'com-slide',
                'status' => \App\Models\Palestra::STATUS_PUBLICADO,
                'ids_palestrantes' => [$p1->id],
                'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
                'duracao' => '≈1h10',
                'referencias_evangelicas' => 'João 14.',
                'referencias' => [
                    ['obra' => 'O Livro dos Espíritos', 'autor' => 'Allan Kardec', 'nota' => 'Conclusão.'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $palestra = \App\Models\Palestra::where('slug', 'com-slide')->first();
        $this->assertSame('≈1h10', $palestra->duracao);
        $this->assertSame('https://drive.google.com/uc?export=download&id=1ABCdefg_hij', $palestra->slide_download_url);
        $this->assertCount(1, $palestra->referencias);
        $this->assertSame('O Livro dos Espíritos', $palestra->referencias->first()->obra);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter="PalestraResourceTest::test_cria_palestra_com_slide"`
Expected: FAIL (campos não existem no form).

- [ ] **Step 3: Add slide/duração to the "Dados" tab**

Em `PalestraResource::form`, dentro da `Tabs\Tab::make('Dados')`, após o `Grid` com `online`/`link_youtube` (linha ~124) e antes do `Grid::make(3)` dos públicos, inserir:

```php
                    Grid::make(2)->schema([
                        TextInput::make('slide')
                            ->label('Link dos slides (Google Drive)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Cole o link normal do Google Drive; o sistema gera o download direto automaticamente.'),
                        TextInput::make('duracao')
                            ->label('Duração')
                            ->placeholder('≈1h10')
                            ->maxLength(40),
                    ]),
```

- [ ] **Step 4: Add the "Referências" tab**

Após a `Tabs\Tab::make('Assuntos e destaques')` (fechamento na linha ~164), acrescentar nova aba dentro do `->tabs([...])`:

```php
                Tabs\Tab::make('Referências')->schema([
                    Textarea::make('referencias_evangelicas')
                        ->label('Referências evangélicas')
                        ->rows(3)
                        ->columnSpanFull(),
                    Repeater::make('referencias')
                        ->label('Referências doutrinárias')
                        ->relationship('referencias')
                        ->schema([
                            TextInput::make('obra')->label('Obra')->required()->maxLength(255),
                            TextInput::make('autor')->label('Autor')->maxLength(255),
                            Textarea::make('nota')->label('Nota')->rows(2),
                        ])
                        ->orderColumn('ordem')
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Adicionar referência')
                        ->columnSpanFull(),
                ]),
```

- [ ] **Step 5: Run the resource suite (new + regression)**

Run: `docker compose exec -T app php artisan test --filter=PalestraResourceTest`
Expected: PASS (novo + os existentes continuam verdes).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Palestras/PalestraResource.php tests/Feature/Filament/PalestraResourceTest.php
git commit -m "feat(admin/palestra): campos slide/duracao/refs evangelicas + repeater de referencias"
```

---

### Task 5: Componente Livewire `Curtir` (contador)

**Files:**
- Create: `app/Livewire/Palestras/Curtir.php`
- Create: `resources/views/livewire/palestras/curtir.blade.php`
- Test: `tests/Feature/Livewire/CurtirPalestraTest.php`

**Interfaces:**
- Consumes: `palestras.curtidas` (Task 1).
- Produces: componente `<livewire:palestras.curtir :palestra="$palestra" />`; métodos `curtir()`/`descurtir()`; prop pública `int $curtidas`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Palestras\Curtir;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurtirPalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_curtir_incrementa_e_descurtir_decrementa_atomicamente(): void
    {
        $palestra = Palestra::factory()->create(['curtidas' => 0]);

        Livewire::test(Curtir::class, ['palestra' => $palestra])
            ->assertSet('curtidas', 0)
            ->call('curtir')
            ->assertSet('curtidas', 1)
            ->call('descurtir')
            ->assertSet('curtidas', 0);

        $this->assertSame(0, $palestra->refresh()->curtidas);
    }

    public function test_descurtir_nao_passa_de_zero(): void
    {
        $palestra = Palestra::factory()->create(['curtidas' => 0]);

        Livewire::test(Curtir::class, ['palestra' => $palestra])
            ->call('descurtir')
            ->assertSet('curtidas', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=CurtirPalestraTest`
Expected: FAIL (componente inexistente).

- [ ] **Step 3: Implement the component**

`app/Livewire/Palestras/Curtir.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Livewire\Palestras;

use App\Models\Palestra;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Curtir extends Component
{
    #[Locked]
    public int $palestraId;

    public int $curtidas = 0;

    public function mount(Palestra $palestra): void
    {
        $this->palestraId = $palestra->id;
        $this->curtidas = $palestra->curtidas;
    }

    public function curtir(): void
    {
        $this->ajustar(1);
    }

    public function descurtir(): void
    {
        $this->ajustar(-1);
    }

    private function ajustar(int $delta): void
    {
        $chave = 'curtir:'.request()->ip().':'.$this->palestraId;
        if (RateLimiter::tooManyAttempts($chave, 20)) {
            return;
        }
        RateLimiter::hit($chave, 60);

        $palestra = Palestra::findOrFail($this->palestraId);
        if ($delta > 0) {
            $palestra->increment('curtidas');
        } elseif ($palestra->curtidas > 0) {
            $palestra->decrement('curtidas');
        }

        $this->curtidas = $palestra->refresh()->curtidas;
    }

    public function render()
    {
        return view('livewire.palestras.curtir');
    }
}
```

- [ ] **Step 4: Create the component view**

`resources/views/livewire/palestras/curtir.blade.php`:

```blade
<div x-data="{ curtido: $persist(false).as('curtida_palestra_{{ $palestraId }}') }">
    <button type="button"
            @click="curtido ? ($wire.descurtir(), curtido = false) : ($wire.curtir(), curtido = true)"
            :aria-pressed="curtido"
            class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold transition"
            :class="curtido ? 'text-danger border-danger' : 'text-primary'">
        <span x-text="curtido ? '♥' : '♡'" aria-hidden="true"></span>
        <span x-text="curtido ? 'Curtido' : 'Curtir'">Curtir</span>
        <span class="rounded-full bg-cream px-2 py-0.5 text-[12px] text-primary">{{ number_format($curtidas, 0, ',', '.') }}</span>
    </button>
</div>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec -T app php artisan test --filter=CurtirPalestraTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Palestras/Curtir.php resources/views/livewire/palestras/curtir.blade.php tests/Feature/Livewire/CurtirPalestraTest.php
git commit -m "feat(palestra): componente Livewire de curtir com contador atomico + dedup localStorage"
```

---

### Task 6: Controller — relacionadas + calendário `.ics`

**Files:**
- Create: `app/Support/Palestras/DuracaoPalestra.php`
- Modify: `app/Http/Controllers/PalestraController.php`
- Modify: `routes/web.php:12-13`
- Test: `tests/Feature/Front/PalestraRelacionadasTest.php`
- Test: `tests/Feature/Front/CalendarioPalestraTest.php`

**Interfaces:**
- Consumes: `Palestra::publicado()`, relação `assuntos`, `data_da_palestra` (Carbon, fuso SP), `duracao`.
- Produces: `$relacionadas` (Collection, ≤3) na view `palestras.show`; `PalestraController@calendario` (rota `palestras.calendario`); `DuracaoPalestra::minutos(?string): int` (fallback 90).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Front/PalestraRelacionadasTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Assunto;
use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraRelacionadasTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_monta_relacionadas_por_assunto(): void
    {
        $assunto = Assunto::factory()->create();
        $atual = Palestra::factory()->create(['slug' => 'atual', 'status' => Palestra::STATUS_PUBLICADO]);
        $atual->assuntos()->attach($assunto);

        $irma = Palestra::factory()->create(['titulo' => 'Palestra Irmã', 'status' => Palestra::STATUS_PUBLICADO]);
        $irma->assuntos()->attach($assunto);

        $resp = $this->get(route('palestras.show', 'atual'));

        $resp->assertOk();
        // Testa o DADO passado à view (não o HTML — a partial nasce na Task 7).
        $this->assertTrue($resp->viewData('relacionadas')->contains('id', $irma->id));
    }

    public function test_relacionadas_usam_fallback_quando_sem_assunto(): void
    {
        Palestra::factory()->create(['slug' => 'atual', 'status' => Palestra::STATUS_PUBLICADO]);
        $outra = Palestra::factory()->create(['titulo' => 'Outra Recente', 'status' => Palestra::STATUS_PUBLICADO]);

        $resp = $this->get(route('palestras.show', 'atual'));

        $resp->assertOk();
        $this->assertTrue($resp->viewData('relacionadas')->contains('id', $outra->id));
    }
}
```

`tests/Feature/Front/CalendarioPalestraTest.php`:

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarioPalestraTest extends TestCase
{
    use RefreshDatabase;

    public function test_ics_responde_em_utc_com_duracao_padrao(): void
    {
        // 19:00 America/Sao_Paulo => 22:00Z; +1h30 padrão => 23:30Z
        Palestra::factory()->create([
            'slug' => 'cema-65',
            'titulo' => 'CEMA 65 Anos',
            'status' => Palestra::STATUS_PUBLICADO,
            'duracao' => null,
            'data_da_palestra' => Carbon::create(2026, 6, 21, 19, 0, 0, 'America/Sao_Paulo'),
        ]);

        $resp = $this->get(route('palestras.calendario', 'cema-65'));

        $resp->assertOk();
        $this->assertStringContainsString('text/calendar', $resp->headers->get('content-type'));
        $resp->assertSee('DTSTART:20260621T220000Z', false);
        $resp->assertSee('DTEND:20260621T233000Z', false);
        $resp->assertSee('SUMMARY:CEMA 65 Anos', false);
    }

    public function test_ics_404_sem_data(): void
    {
        Palestra::factory()->create(['slug' => 'sem-data', 'status' => Palestra::STATUS_PUBLICADO, 'data_da_palestra' => null]);

        $this->get(route('palestras.calendario', 'sem-data'))->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec -T app php artisan test --filter=PalestraRelacionadasTest && docker compose exec -T app php artisan test --filter=CalendarioPalestraTest`
Expected: FAIL (rota `palestras.calendario` inexistente; `viewData('relacionadas')` indefinido).

- [ ] **Step 3: Implement `DuracaoPalestra`**

`app/Support/Palestras/DuracaoPalestra.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Support\Palestras;

class DuracaoPalestra
{
    public const PADRAO_MIN = 90;

    /** Converte uma duração livre ("≈1h10", "45 min", "2h") em minutos; fallback 90. */
    public static function minutos(?string $duracao): int
    {
        if ($duracao === null || trim($duracao) === '') {
            return self::PADRAO_MIN;
        }

        $s = mb_strtolower($duracao);
        $min = 0;

        if (preg_match('/(\d+)\s*h(?:\s*(\d+))?/', $s, $m)) {
            $min += (int) $m[1] * 60;
            if (isset($m[2]) && $m[2] !== '') {
                $min += (int) $m[2];
            }
        } elseif (preg_match('/(\d+)\s*min/', $s, $m)) {
            $min += (int) $m[1];
        }

        return $min > 0 ? $min : self::PADRAO_MIN;
    }
}
```

- [ ] **Step 4: Add `$relacionadas` + `calendario()` to the controller**

Em `app/Http/Controllers/PalestraController.php`: importar `use App\Support\Palestras\DuracaoPalestra;` e `use Illuminate\Database\Eloquent\Builder;`. No fim de `show()`, antes do `return view(...)`, montar relacionadas e passar à view:

```php
        $assuntoIds = $palestra->assuntos->pluck('id');

        $relacionadas = Palestra::query()
            ->publicado()
            ->where('id', '!=', $palestra->id)
            ->when(
                $assuntoIds->isNotEmpty(),
                fn (Builder $q) => $q->whereHas('assuntos', fn (Builder $a) => $a->whereIn('assuntos.id', $assuntoIds))
            )
            ->with('palestrantesAtivos')
            ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
            ->take(3)
            ->get();

        if ($relacionadas->count() < 3) {
            $exclui = $relacionadas->pluck('id')->push($palestra->id)->all();
            $relacionadas = $relacionadas->concat(
                Palestra::query()->publicado()
                    ->whereNotIn('id', $exclui)
                    ->with('palestrantesAtivos')
                    ->orderByRaw('data_da_palestra IS NULL, data_da_palestra DESC')
                    ->take(3 - $relacionadas->count())
                    ->get()
            );
        }

        return view('palestras.show', compact('palestra', 'anterior', 'proxima', 'relacionadas'));
```

(Remover o `return view('palestras.show', compact('palestra', 'anterior', 'proxima'));` antigo.)

Adicionar o método `calendario`:

```php
    public function calendario(string $slug)
    {
        $palestra = Palestra::query()->publicado()->where('slug', $slug)->firstOrFail();
        abort_if($palestra->data_da_palestra === null, 404);

        $inicio = $palestra->data_da_palestra->copy()->utc();
        $fim = $inicio->copy()->addMinutes(DuracaoPalestra::minutos($palestra->duracao));
        $fmt = fn ($d) => $d->format('Ymd\THis\Z');

        $escapar = fn (string $v) => str_replace(["\\", ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $v);
        $local = 'Centro Espírita Maria Madalena — Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF';

        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CEMA//Palestras//PT-BR',
            'BEGIN:VEVENT',
            'UID:palestra-'.$palestra->id.'@cemanet.org.br',
            'DTSTART:'.$fmt($inicio),
            'DTEND:'.$fmt($fim),
            'SUMMARY:'.$escapar($palestra->titulo),
            'DESCRIPTION:'.$escapar(route('palestras.show', $palestra->slug)),
            'LOCATION:'.$escapar($local),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $linhas)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="palestra-'.$palestra->slug.'.ics"',
        ]);
    }
```

- [ ] **Step 5: Register the route**

Em `routes/web.php`, após a linha `Route::get('/palestra_publica/{slug}', [PalestraController::class, 'show'])->name('palestras.show');`:

```php
Route::get('/palestra_publica/{slug}/calendario.ics', [PalestraController::class, 'calendario'])
    ->name('palestras.calendario')
    ->where('slug', '[a-z0-9-]+');
```

> Ambos fecham **verdes nesta task**: `PalestraRelacionadasTest` valida `viewData('relacionadas')` (independe do render da partial, que vem na Task 7); `CalendarioPalestraTest` valida o `.ics`. O teste de **render** das relacionadas no HTML fica na Task 7.

- [ ] **Step 6: Run both tests (green)**

Run: `docker compose exec -T app php artisan test --filter=PalestraRelacionadasTest && docker compose exec -T app php artisan test --filter=CalendarioPalestraTest`
Expected: PASS (ambos).

- [ ] **Step 7: Commit**

```bash
git add app/Support/Palestras/DuracaoPalestra.php app/Http/Controllers/PalestraController.php routes/web.php tests/Feature/Front/CalendarioPalestraTest.php tests/Feature/Front/PalestraRelacionadasTest.php
git commit -m "feat(palestra): relacionadas por assunto + calendario .ics (UTC) no controller"
```

---

### Task 7: View redesign — `show.blade.php` + partials

**Files:**
- Create: `resources/views/palestras/partials/player.blade.php`
- Create: `resources/views/palestras/partials/referencias.blade.php`
- Create: `resources/views/palestras/partials/relacionadas.blade.php`
- Modify: `resources/views/palestras/show.blade.php` (reescrita)
- Test: `tests/Feature/Front/PalestraSingleSlideTest.php`

**Interfaces:**
- Consumes: `$palestra` (com `slide_download_url`, `referencias`, `referencias_evangelicas`, `duracao`, `destaques`, `assuntos`, `palestrantesAtivos`, `youtube_id`, `youtube_thumb`), `$anterior`, `$proxima`, `$relacionadas`; `<livewire:palestras.curtir>` (Task 5); rota `palestras.calendario` (Task 6).

> **Reescrita de comportamento (player):** a view atual embute o `<iframe>` no load; passa a ser **lazy** (placeholder thumb + play → injeta o iframe no clique). Sem `link_youtube` → estado "Vídeo em breve".

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSingleSlideTest extends TestCase
{
    use RefreshDatabase;

    public function test_botao_baixar_slides_aparece_quando_preenchido(): void
    {
        Palestra::factory()->create([
            'slug' => 'com-slide',
            'status' => Palestra::STATUS_PUBLICADO,
            'slide' => 'https://drive.google.com/file/d/1ABCdefg_hij/view',
        ]);

        $resp = $this->get(route('palestras.show', 'com-slide'));

        $resp->assertOk();
        $resp->assertSee('Baixar slides');
        $resp->assertSee('https://drive.google.com/uc?export=download&id=1ABCdefg_hij', false);
    }

    public function test_botao_baixar_slides_oculto_sem_slide(): void
    {
        Palestra::factory()->create(['slug' => 'sem-slide', 'status' => Palestra::STATUS_PUBLICADO, 'slide' => null]);

        $this->get(route('palestras.show', 'sem-slide'))->assertOk()->assertDontSee('Baixar slides');
    }

    public function test_video_em_breve_quando_sem_link(): void
    {
        Palestra::factory()->create(['slug' => 'sem-video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $resp = $this->get(route('palestras.show', 'sem-video'));

        $resp->assertOk();
        $resp->assertSee('Vídeo em breve');
        $resp->assertDontSee('youtube.com/embed', false); // não carrega iframe no load
    }

    public function test_referencias_doutrinarias_e_evangelicas_renderizam(): void
    {
        $palestra = Palestra::factory()->create([
            'slug' => 'com-refs',
            'status' => Palestra::STATUS_PUBLICADO,
            'referencias_evangelicas' => 'A promessa do Consolador (João 14).',
        ]);
        $palestra->referencias()->create(['obra' => 'O Livro dos Espíritos', 'autor' => 'Allan Kardec', 'nota' => 'Progresso moral.', 'ordem' => 0]);

        $resp = $this->get(route('palestras.show', 'com-refs'));

        $resp->assertOk();
        $resp->assertSee('Referências doutrinárias');
        $resp->assertSee('O Livro dos Espíritos');
        $resp->assertSee('Allan Kardec');
        $resp->assertSee('Referências evangélicas');
        $resp->assertSee('A promessa do Consolador (João 14).');
    }

    public function test_relacionadas_renderizam_no_html(): void
    {
        $assunto = \App\Models\Assunto::factory()->create();
        $atual = Palestra::factory()->create(['slug' => 'atual-r', 'status' => Palestra::STATUS_PUBLICADO]);
        $atual->assuntos()->attach($assunto);
        $irma = Palestra::factory()->create(['titulo' => 'Palestra Irmã', 'status' => Palestra::STATUS_PUBLICADO]);
        $irma->assuntos()->attach($assunto);

        $this->get(route('palestras.show', 'atual-r'))->assertOk()->assertSee('Palestra Irmã');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec -T app php artisan test --filter=PalestraSingleSlideTest`
Expected: FAIL (sem botão de slides, sem "Vídeo em breve", sem referências).

- [ ] **Step 3: Create the player partial**

`resources/views/palestras/partials/player.blade.php`:

```blade
@php($ytId = $palestra->youtube_id)
<div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-footer-bg to-primary">
    @if ($ytId)
        <div x-data="{ aberto: false }">
            <button type="button" x-show="!aberto" @click="aberto = true"
                    class="group relative flex aspect-video w-full items-center justify-center"
                    aria-label="Reproduzir vídeo: {{ $palestra->titulo }}">
                <img src="{{ $palestra->youtube_thumb }}" alt=""
                     loading="lazy" class="absolute inset-0 size-full object-cover opacity-70"
                     onerror="this.style.display='none'">
                <span class="absolute left-4 top-4 flex items-center gap-2 rounded-pill bg-black/40 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#FF0000] text-[10px]">▶</span> CEMA TV
                </span>
                <span class="relative flex size-16 items-center justify-center rounded-full bg-[#FF0000] text-2xl text-white shadow-lg transition group-hover:scale-105">▶</span>
                <span class="absolute bottom-4 text-sm font-semibold text-white">Assista no YouTube</span>
            </button>
            <template x-if="aberto">
                <iframe class="aspect-video w-full" src="https://www.youtube.com/embed/{{ $ytId }}?autoplay=1"
                        title="Vídeo: {{ $palestra->titulo }}" loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </template>
        </div>
    @else
        <div class="flex aspect-video w-full flex-col items-center justify-center gap-3 text-center text-white">
            <span class="flex size-14 items-center justify-center rounded-full bg-white/15 text-2xl">▶</span>
            <p class="font-display text-lg font-semibold">Vídeo em breve</p>
            <p class="max-w-xs text-sm text-white/80">O vídeo desta palestra estará disponível em breve.</p>
        </div>
    @endif
</div>
```

- [ ] **Step 4: Create the referências partial**

`resources/views/palestras/partials/referencias.blade.php`:

```blade
@if ($palestra->referencias->isNotEmpty() || filled($palestra->referencias_evangelicas))
    <div class="mt-8 border-t border-border-muted pt-6">
        @if ($palestra->referencias->isNotEmpty())
            <h2 class="mb-4 font-display text-lg font-semibold text-primary">Referências doutrinárias</h2>
            <div class="flex flex-col gap-3">
                @foreach ($palestra->referencias as $ref)
                    <div class="flex gap-3 rounded-xl border border-[#ECE6D6] bg-[#FAF8F2] p-4">
                        <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-white shadow-[inset_3px_0_0_0_var(--color-gold)]" aria-hidden="true">📖</span>
                        <div>
                            <p class="font-display text-[15px] font-semibold text-primary">{{ $ref->obra }}@if ($ref->autor)<span class="font-normal text-text-muted"> · {{ $ref->autor }}</span>@endif</p>
                            @if ($ref->nota)
                                <p class="mt-1 text-sm text-text-secondary">{{ $ref->nota }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if (filled($palestra->referencias_evangelicas))
            <p class="mt-4 text-sm text-text-secondary">
                <span class="font-semibold text-primary">Referências evangélicas</span> — {{ $palestra->referencias_evangelicas }}
            </p>
        @endif
    </div>
@endif
```

- [ ] **Step 5: Create the relacionadas partial**

`resources/views/palestras/partials/relacionadas.blade.php`:

```blade
@if ($relacionadas->isNotEmpty())
    <section class="bg-surface">
        <div class="mx-auto max-w-[1100px] px-6 py-10">
            <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Você também pode gostar</h2>
            <div class="grid gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @forelse ($relacionadas as $rel)
                    <a href="{{ route('palestras.show', $rel->slug) }}"
                       class="block overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card transition hover:-translate-y-1">
                        <div class="aspect-video bg-gradient-to-br from-primary to-footer-bg"></div>
                        <div class="p-4">
                            <p class="font-display font-semibold text-text-ink">{{ \Illuminate\Support\Str::limit($rel->titulo, 50) }}</p>
                            <p class="mt-1 text-xs text-text-muted">
                                {{ $rel->data_da_palestra?->translatedFormat('d \d\e F \d\e Y') ?? 'A confirmar' }}
                                @if ($rel->palestrantesAtivos->isNotEmpty()) · {{ $rel->palestrantesAtivos->first()->nome }} @endif
                            </p>
                        </div>
                    </a>
                @empty
                @endforelse
            </div>
        </div>
    </section>
@endif
```

- [ ] **Step 6: Rewrite `show.blade.php`**

Substituir `resources/views/palestras/show.blade.php` pelo conteúdo abaixo (mantém JSON-LD `Event`, hero roxo com `from-primary to-footer-bg` + `x-ui.particulas`, e a barra de compartilhar com `wa.me`/`facebook.com/sharer`/`Copiar link`/`x-data` — exigidos pelos testes existentes):

```blade
@php
    $palestrantes = $palestra->palestrantesAtivos;
    $data = $palestra->data_da_palestra;
    $ytId = $palestra->youtube_id;
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $palestra->titulo,
        'startDate' => optional($data)->toIso8601String(),
        'eventAttendanceMode' => $palestra->online
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [
            '@type' => 'Place',
            'name' => 'Centro Espírita Maria Madalena',
            'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF',
        ],
        'performer' => $palestrantes->map(fn ($p) => ['@type' => 'Person', 'name' => $p->nome])->all(),
        'organizer' => ['@type' => 'Organization', 'name' => 'CEMA'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $googleAgenda = $data
        ? 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($palestra->titulo)
            .'&dates='.$data->copy()->utc()->format('Ymd\THis\Z').'/'
            .$data->copy()->utc()->addMinutes(\App\Support\Palestras\DuracaoPalestra::minutos($palestra->duracao))->format('Ymd\THis\Z')
            .'&details='.urlencode(route('palestras.show', $palestra->slug))
        : null;
@endphp

<x-layout.app :title="$palestra->titulo" :description="$palestra->subtitulo ?? $palestra->resumo">
    <x-slot:head>
        <script type="application/ld+json">{!! $jsonLd !!}</script>
        @if ($ytId)
            <meta property="og:image" content="{{ $palestra->youtube_thumb }}">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $palestra->titulo,
                'thumbnailUrl' => $palestra->youtube_thumb,
                'embedUrl' => 'https://www.youtube.com/embed/'.$ytId,
                'uploadDate' => optional($data)->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
    </x-slot:head>

    {{-- Barra de progresso de leitura --}}
    <div class="fixed inset-x-0 top-0 z-50 h-[3px] bg-gold/90 origin-left motion-reduce:hidden"
         x-data="{ p: 0 }" x-init="const f = () => { const h = document.documentElement; p = (h.scrollTop) / (h.scrollHeight - h.clientHeight) || 0; requestAnimationFrame(() => {}); }; window.addEventListener('scroll', f, { passive: true }); f();"
         :style="`transform: scaleX(${p})`" aria-hidden="true"></div>

    {{-- S1: Hero (sempre roxo; partículas) --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestras.index') }}" class="hover:text-white">Palestras Públicas</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ \Illuminate\Support\Str::limit($palestra->titulo, 40) }}</span>
            </nav>
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestra Pública</p>
            <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight text-balance md:text-5xl">{{ $palestra->titulo }}</h1>
            @if ($palestra->subtitulo)
                <p class="mt-3 max-w-2xl font-serif text-lg italic text-white/85">{{ $palestra->subtitulo }}</p>
            @endif
            <div class="mt-5 flex flex-wrap gap-2.5 text-sm">
                @if ($data)
                    <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">📅 {{ $data->translatedFormat('d \d\e F · H\hi') }}</span>
                @endif
                <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">🌐 {{ $palestra->online ? 'Online' : 'Presencial' }}</span>
                @foreach ($palestrantes as $p)
                    <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">👤 {{ $p->nome }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- S2: Conteúdo + sidebar sticky --}}
    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <div class="grid items-start gap-9 desktop-sm:grid-cols-[minmax(0,1fr)_320px]">
            {{-- Conteúdo --}}
            <div>
                @include('palestras.partials.player')

                <div class="mb-7 flex flex-wrap gap-3.5">
                    <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Data</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $data ? $data->translatedFormat('l, d \d\e F \d\e Y · H\hi') : 'A confirmar' }}</p>
                    </div>
                    <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Modalidade</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $palestra->online ? 'Online' : 'Presencial' }}</p>
                    </div>
                    @if (filled($palestra->duracao))
                        <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Duração</p>
                            <p class="mt-1 font-semibold text-text-ink">{{ $palestra->duracao }}</p>
                        </div>
                    @endif
                </div>

                @if ($palestra->descricao)
                    <div class="max-w-none font-serif text-[16px] leading-[1.82] text-[#3a3553] [&_p]:mb-[18px] [&_a]:text-secondary [&_a]:underline">
                        {!! $palestra->descricao !!}
                    </div>
                @endif

                @include('palestras.partials.referencias')

                @if ($palestra->destaques->isNotEmpty())
                    <h2 class="mb-4 mt-8 font-display text-2xl font-semibold text-primary">Principais tópicos abordados</h2>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($palestra->destaques as $i => $d)
                            <details class="group overflow-hidden rounded-xl border border-border-muted bg-[#FAFAFB]">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 font-display font-medium text-text-ink">
                                    <span><span class="mr-2 font-mono text-sm text-text-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $d->destaque }}</span>
                                    <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cream text-primary transition group-open:rotate-45">+</span>
                                </summary>
                                @if ($d->texto)
                                    <div class="px-5 pb-5 text-sm text-text-secondary">{{ $d->texto }}</div>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif

                @if ($palestra->assuntos->isNotEmpty())
                    <div class="mt-8">
                        <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-text-muted">Assuntos principais</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($palestra->assuntos as $a)
                                <a href="{{ route('palestras.index', ['assunto' => $a->slug]) }}"
                                   class="rounded-pill border border-border bg-surface px-3.5 py-1.5 text-[13px] text-text-secondary hover:border-primary hover:text-primary">{{ $a->nome }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar sticky --}}
            <aside class="space-y-5 desktop-sm:sticky desktop-sm:top-24">
                @forelse ($palestrantes as $p)
                    <div class="overflow-hidden rounded-xl border border-border-muted bg-cream">
                        @if ($p->foto_thumb_url)
                            <img src="{{ $p->foto_thumb_url }}" alt="{{ $p->nome }}" loading="lazy" width="320" height="200" class="h-[200px] w-full object-cover">
                        @endif
                        <div class="p-5">
                            <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Palestrante</p>
                            <h2 class="mt-1 font-display text-xl font-semibold text-primary">
                                <a href="{{ route('palestrantes.show', $p->slug) }}" class="hover:underline">{{ $p->nome }}</a>
                            </h2>
                            @if ($p->bio)
                                <div class="mt-2 line-clamp-4 text-sm text-text-secondary">{!! \Illuminate\Support\Str::limit(strip_tags($p->bio), 200) !!}</div>
                            @endif
                            <a href="{{ route('palestrantes.show', $p->slug) }}" class="mt-3 inline-block text-sm font-semibold text-secondary hover:underline">Ver perfil completo →</a>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Palestrante a confirmar.</p>
                @endforelse

                {{-- Ações --}}
                <div class="space-y-2.5 rounded-xl border border-border-muted bg-white p-5">
                    @if ($ytId)
                        <a href="{{ $palestra->link_youtube }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill bg-[#FF0000] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">▶ Assistir no YouTube</a>
                    @endif
                    @if (filled($palestra->slide))
                        <a href="{{ $palestra->slide_download_url }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill border border-primary px-4 py-2.5 text-sm font-semibold text-primary hover:bg-cream">⬇ Baixar slides</a>
                    @endif
                    @if ($googleAgenda)
                        <a href="{{ $googleAgenda }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:border-primary hover:text-primary">📅 Adicionar ao calendário</a>
                        <a href="{{ route('palestras.calendario', $palestra->slug) }}"
                           class="block text-center text-xs text-text-muted hover:text-primary">Baixar .ics</a>
                    @endif
                </div>

                {{-- Compartilhar + curtir --}}
                <div class="rounded-xl border border-border-muted bg-white p-5" data-acoes-palestra>
                    <p class="mb-3 text-sm text-text-muted">Compartilhar:</p>
                    @php($urlAtual = route('palestras.show', $palestra->slug))
                    <div class="flex flex-wrap items-center gap-2.5"
                         x-data="{ url: @js($urlAtual), titulo: @js($palestra->titulo), copiado: false,
                            copiar() { navigator.clipboard.writeText(this.url).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000); }); } }">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white">f</span> Facebook
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($palestra->titulo.' — '.$urlAtual) }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white">W</span> WhatsApp
                        </a>
                        <button type="button" @click="copiar()"
                                class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
                        </button>
                        <livewire:palestras.curtir :palestra="$palestra" />
                    </div>
                </div>
            </aside>
        </div>
    </section>

    {{-- S3: Anterior / Próxima --}}
    <section class="border-y border-border-muted bg-surface">
        <div class="mx-auto flex max-w-[1100px] flex-wrap justify-between gap-4 px-6 py-6">
            @if ($anterior)
                <a href="{{ route('palestras.show', $anterior->slug) }}" rel="prev" class="flex items-center gap-3 text-primary hover:underline">
                    <span aria-hidden="true" class="text-xl">‹</span>
                    <span><span class="block font-mono text-[10px] uppercase text-text-muted">Anterior</span><span class="font-semibold">{{ \Illuminate\Support\Str::limit($anterior->titulo, 38) }}</span></span>
                </a>
            @else <span></span> @endif
            @if ($proxima)
                <a href="{{ route('palestras.show', $proxima->slug) }}" rel="next" class="flex items-center gap-3 text-right text-primary hover:underline">
                    <span><span class="block font-mono text-[10px] uppercase text-text-muted">Próxima</span><span class="font-semibold">{{ \Illuminate\Support\Str::limit($proxima->titulo, 38) }}</span></span>
                    <span aria-hidden="true" class="text-xl">›</span>
                </a>
            @endif
        </div>
    </section>

    {{-- S4: Relacionadas --}}
    @include('palestras.partials.relacionadas')
</x-layout.app>
```

- [ ] **Step 7: Build assets + run the FULL suite (regressões)**

Run (host): `npm run build`
Run: `docker compose exec -T app php artisan test`
Expected: PASS na **suíte inteira** (não só `--filter`). A reescrita do `show.blade.php` está coberta por testes de regressão fora destes filtros; rodar tudo é obrigatório. Devem continuar verdes, em especial: `PalestraSingleTest` (`from-primary to-footer-bg`, `cema-hero-deco`, `"@type":"Event"`, `application/ld+json`, `assertDontSee('background:#abcdef')`, `assertDontSee('</script> XSS')`, link `route('palestrantes.show','joao-ativo')`) e `PalestraInteracoesTest` (`wa.me`, `facebook.com/sharer`, `Copiar link`, `x-data`). Conferir no markup que a sidebar mantém **"Ver perfil completo →"** (`route('palestrantes.show', $p->slug)`) e a barra mantém `wa.me`/`facebook.com/sharer`/`Copiar link`/`x-data`.

- [ ] **Step 8: Commit**

```bash
git add resources/views/palestras/show.blade.php resources/views/palestras/partials/ tests/Feature/Front/PalestraSingleSlideTest.php
git commit -m "feat(palestra/single): redesign da view (player lazy, video em breve, refs, sidebar, slides, relacionadas)"
```

---

### Task 8: SEO/`VideoObject` + verificação visual final

**Files:**
- Test: `tests/Feature/Front/PalestraSeoVideoTest.php`
- Modify (se necessário): `resources/css/app.css` (somente se a prosa serifada/cards exigirem regra extra)

**Interfaces:**
- Consumes: blocos `<x-slot:head>` da Task 7 (JSON-LD `VideoObject` + `og:image`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Front;

use App\Models\Palestra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalestraSeoVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_videoobject_presente_quando_ha_video(): void
    {
        Palestra::factory()->create([
            'slug' => 'com-video',
            'status' => Palestra::STATUS_PUBLICADO,
            'link_youtube' => 'https://youtube.com/live/ABCdefGhijk',
        ]);

        $resp = $this->get(route('palestras.show', 'com-video'));

        $resp->assertOk();
        $resp->assertSee('"@type":"VideoObject"', false);
        $resp->assertSee('og:image', false);
    }

    public function test_videoobject_ausente_sem_video(): void
    {
        Palestra::factory()->create(['slug' => 'sem-video', 'status' => Palestra::STATUS_PUBLICADO, 'link_youtube' => null]);

        $this->get(route('palestras.show', 'sem-video'))->assertOk()->assertDontSee('"@type":"VideoObject"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it passes (or fails)**

Run: `docker compose exec -T app php artisan test --filter=PalestraSeoVideoTest`
Expected: PASS já com a Task 7 (os blocos `<x-slot:head>` foram adicionados lá). Se FALHAR, conferir que o `youtube_id` é extraído do `link_youtube` (accessor existente) e que o bloco `@if ($ytId)` envolve o `VideoObject`/`og:image`.

- [ ] **Step 3: Run the full palestra suite (no regressions)**

Run: `docker compose exec -T app php artisan test --filter=Palestra`
Expected: PASS em toda a família `Palestra*` (single, interações, resource, relacionadas, importação, seo, curtir).

- [ ] **Step 4: Manual verification on localhost**

Abrir e conferir (conforme CLAUDE.md):
- `http://localhost:8000/palestra_publica/cema-65-anos` → player lazy (clica e o vídeo carrega), chips de meta, sidebar sticky, compartilhar+curtir.
- Uma palestra **com** slide (ex.: `cema-65-anos`) → botão "Baixar slides" baixa direto (sem abrir o preview do Drive).
- Uma palestra **sem** `link_youtube` → "Vídeo em breve".
- Uma palestra **com** referências (cadastrar no admin) → cards doutrinários + linha evangélica.
- "Adicionar ao calendário" → evento com hora correta; "Baixar .ics" abre no app de calendário.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Front/PalestraSeoVideoTest.php resources/css/app.css
git commit -m "test(palestra/single): JSON-LD VideoObject + og:image; verificacao visual"
```

---

## Self-Review (cobertura do spec)

- **§4 (dados):** Task 1 (colunas + `palestra_referencias` + models + `$fillable`). ✓
- **§5 (LinkDrive host-first):** Task 2 (helper + accessor + 7 casos-âncora). ✓
- **§6 (importação `_slides`):** Task 3 (leitor + importador + teste com `&amp;`/Drive). ✓
- **§7 (admin):** Task 4 (slide/duração/refs evangélicas + Repeater na ordem segura). ✓
- **§9.1 (curtir contador):** Task 5 (componente Livewire atômico + dedup `$persist` + throttle). ✓
- **§9.2 (calendário UTC):** Task 6 (`->utc()` + `DuracaoPalestra` + teste `T220000Z`/`T233000Z`). ✓
- **§9.3 (relacionadas):** Task 6 (controller) + Task 7 (render) + fallback. ✓
- **§8 (front redesign):** Task 7 (hero chips, grid sidebar sticky, player lazy + "vídeo em breve", cartões meta, prosa serifada, tópicos, assuntos, sidebar ações, barra de progresso, anterior/próxima). ✓
- **§11 (SEO/A11y):** Task 7 (`og:image`, `VideoObject` só com vídeo, `text-balance`, `motion-reduce`) + Task 8 (testes). ✓
- **Regressões preservadas:** hero (`from-primary to-footer-bg`/`cema-hero-deco`), compartilhar (`wa.me`/`facebook.com/sharer`/`Copiar link`/`x-data`), JSON-LD `Event` — mantidos no markup da Task 7. ✓

**Pontos de atenção registrados:**
- A fonte Roboto Slab (`--font-serif`) já existe no `@theme` ([resources/css/app.css:19]); confirmar no navegador (Step 4 da Task 8) se o webfont está sendo carregado — se não, adicionar o `@font-face`/import na Task 8 Step 5.
- `PalestraRelacionadasTest` (criado na Task 6) só fecha verde na Task 7 (precisa do render); está explicitado no fluxo.
- Importação real dos 19 slides é pós-merge (`cema:importar-palestras`), nunca `migrate:fresh`.
