# Camada 4 · Fatia 1 — Autores Espirituais

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Criar a entidade `AutorEspiritual` (clone fiel do Palestrante — DoTipo, departamentalizado, Camada 1) com CRUD no `/admin`, e migrar os 19 autores do CPT `autores-espirituais` do WordPress legado clonando a cadeia de importação de Eventos. Página pública + curtidas ficam na Fatia 2.

**Architecture:** Model `AutorEspiritual` (`HasMedia` + `TemDepartamento`, foto na MediaLibrary, bio sanitizada, `chamada`/`ativo`) sobre a tabela `autores_espirituais` + pivô `departamento_autor_espiritual`. A Camada 1 é **data-driven**: somar `'autor_espiritual'` ao `GlossarioCapacidades` propaga as 4 permissions (via `CapacidadesSeeder`) e a 6ª section da matriz; o `TiposConteudoSeeder` recebe a semente `DoTipo`+`['DEPAE','DECOM']`. `AutorEspiritualPolicy` troca só `recurso()`. A importação clona Leitor(interface)+Mysql+Importador+Command de Eventos, **com o bind de container obrigatório** e **sem** sincronizar departamentos (DoTipo). A autorização nasce **inerte** (só admin edita via `/admin`; `Gate::before`); a Fatia 2 a liga.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5 · MySQL 8 (dev/prod) e SQLite (testes) · spatie/laravel-permission · spatie/laravel-medialibrary · spatie/laravel-activitylog · PHPUnit.

**Spec:** [`docs/superpowers/specs/2026-07-17-camada-4-fatia-1-autores-espirituais.md`](../specs/2026-07-17-camada-4-fatia-1-autores-espirituais.md) (aprovada pelo dono: SÓLIDA, com O1/O2 aplicados + R1–R4; dois passes registrados no §13).

## Global Constraints

- **Idioma:** todo código, comentário, mensagem de UI/erro e commit em **português brasileiro**. Sintaxe e APIs de terceiros no original.
- **Branch:** criar `camada-4-fatia-1-autores-espirituais` a partir de `origin/main` (= **`c988f89`**, PR #35, Fatia 0 mesclada). **Nunca** na `main`. O PR leva código **e** os commits de docs (SPEC + este plano) juntos.
- **Cabeçalho de autoria** em todo arquivo PHP novo: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17` (migrations anônimas do molde: manter o cabeçalho como as demais migrations com autoria).
- **🚫 Banco (dev):** só `php artisan migrate` **incremental**. **PROIBIDO** `migrate:fresh`/`refresh`/`db:wipe`/`migrate:reset` e qualquer seed/factory destrutivo — o dev tem 152 usuários + 123 AgendaDia + 127 Palestras + 45 Posts + 59 Palestrantes + 19 autores (após esta fatia) + mídia. A conexão **`legado` é READ-ONLY** (só `SELECT`).
- **C3/I2 — pluralização:** o model **DEVE** ter `protected $table = 'autores_espirituais'` (o pluralizador do Laravel geraria `autor_espirituals`) e o pivô **DEVE** usar `constrained('autores_espirituais')` explícito. O Resource **DEVE** ter `protected static ?string $slug = 'autores-espirituais'` (senão a rota vira `/admin/autor-espirituals`).
- **R1 — ordem de `RECURSOS`:** anexar `'autor_espiritual'` ao **FINAL** de `GlossarioCapacidades::RECURSOS` (depois de `'palestrante'`). Antes de `'palestra'` deixaria [TiposConteudoSeederTest:111](../../../tests/Feature/Autorizacao/TiposConteudoSeederTest.php#L111) vermelho (a mensagem de erro deixaria de citar `DED`).
- **O1 — regra de mídia no import:** o legado sobrescreve a foto **só quando tem `_thumbnail_id`**; o `clearMediaCollection` roda **só após um download bem-sucedido** (dentro do `if foto_url`). Autor sem thumbnail (ou cujo download falhou) **preserva** a foto posta no `/admin`. **Nunca** o `clearMediaCollection` incondicional do molde `ImportadorEventos:83-84`.
- **C7/I12 — bind de container obrigatório:** o command e o Importador type-hintam a **interface** `LeitorAutoresEspirituais`; sem `bind(LeitorAutoresEspirituais::class, LeitorAutoresEspirituaisMysql::class)` no `AppServiceProvider`, `cema:importar-…` quebra em **prod** com a **suíte verde**. Task 5 tem o teste-guarda.
- **Import não toca depto nem clobber (I10):** o Importador **não** chama `departamentos()->sync()` (DoTipo) e **não** seta `chamada`/`ativo` no `updateOrCreate` (são do admin).
- **Aceite:** suíte verde (**879 + novos**) e **nenhuma asserção de teste existente muda de cor**, exceto a edição deliberada de `CapacidadesSeederTest` (20→24) e os asserts aditivos de `TiposConteudoSeederTest`.
- **Comandos:** testes focados por task `docker compose exec -T app php artisan test --filter=X` (o projeto **não** usa Sail). **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint <arquivos>` (o CI roda `pint --test` antes dos testes — [[pint-antes-de-push]]). Migrations no dev: `docker compose exec -T app php artisan migrate`. Se um teste rodar código aparentemente **stale** após editar um arquivo existente, `docker compose restart app worker` (OPcache `validate_timestamps=0`) e rode de novo.
- **Ciência de flaky:** [[flaky-importadorblog-gd-cap-imagem]] — 2 testes de cap de imagem do blog podem falhar sob carga; se passam isolados/no CI, não é regressão desta fatia.

---

### Task 0: Branch

**Files:** nenhum (só git).

- [ ] **Passo 1: Criar a branch a partir de `origin/main`**

```bash
cd "d:/Claude Code - Projetos/Cemanet - Novo Site"
git fetch origin
git switch -c camada-4-fatia-1-autores-espirituais origin/main
git log --oneline -1
```

Esperado: HEAD em `c988f89` (merge do PR #35 — Fatia 0). Os commits de docs (SPEC + este plano) entram junto; o PR leva código **e** docs.

---

### Task 1: Fundação de dados — tabela, pivô, model, factory

**Files:**
- Create: `database/migrations/2026_07_17_000001_create_autores_espirituais_table.php`
- Create: `database/migrations/2026_07_17_000002_create_departamento_autor_espiritual_table.php`
- Create: `app/Models/AutorEspiritual.php`
- Create: `database/factories/AutorEspiritualFactory.php`
- Test: `tests/Feature/Models/AutorEspiritualTest.php`

**Interfaces:**
- Consumes: `App\Models\Departamento`, `App\Models\Concerns\{RegistraImagensPadrao,TemIniciais}`, `App\Models\Contracts\TemDepartamento`.
- Produces:
  - `App\Models\AutorEspiritual` — `$table='autores_espirituais'`, `COLECAO_FOTO='foto'`, `$fillable=['nome','slug','chamada','bio','ativo']`, `scopeAtivo(Builder): Builder`, `departamentos(): BelongsToMany` (pivô `departamento_autor_espiritual`, chaves `autor_espiritual_id`/`departamento_id`), `fotoUrl`/`fotoThumbUrl` accessors, `bio` sanitizada.
  - `Database\Factories\AutorEspiritualFactory` com estados `ativo()`/`inativo()`.

**Contexto:** clone do [Palestrante](../../../app/Models/Palestrante.php) **menos** email/telefone/mostrar_* (não existem no autor), **mais** `$table` explícito (C3). A coluna `foto` string do molde de create foi dropada depois — **não** recriar (a foto vive na MediaLibrary). O pivô referencia `autores_espirituais` explicitamente. Ordem das migrations: base (`000001`) antes da pivô (`000002`) — o `constrained` não resolve o contrário (C8).

- [ ] **Passo 1: Escrever o teste de model que falha**

Criar `tests/Feature/Models/AutorEspiritualTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Models;

use App\Models\AutorEspiritual;
use App\Models\Contracts\TemDepartamento;
use App\Models\Departamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class AutorEspiritualTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    public function test_grava_na_tabela_autores_espirituais(): void
    {
        $this->assertSame('autores_espirituais', (new AutorEspiritual)->getTable());
    }

    public function test_tabela_nao_tem_colunas_de_contato_nem_foto_string(): void
    {
        foreach (['email', 'telefone', 'mostrar_email', 'mostrar_telefone', 'foto', 'curtidas', 'wp_id'] as $coluna) {
            $this->assertFalse(Schema::hasColumn('autores_espirituais', $coluna), "coluna indevida: {$coluna}");
        }
        foreach (['nome', 'slug', 'chamada', 'bio', 'ativo'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('autores_espirituais', $coluna), "coluna esperada ausente: {$coluna}");
        }
    }

    public function test_fillable_e_exatamente_os_cinco_campos(): void
    {
        $this->assertSame(['nome', 'slug', 'chamada', 'bio', 'ativo'], (new AutorEspiritual)->getFillable());
    }

    public function test_ativo_e_boolean_e_scope_filtra(): void
    {
        AutorEspiritual::factory()->create(['ativo' => true]);
        AutorEspiritual::factory()->create(['ativo' => false]);

        $ativos = AutorEspiritual::ativo()->get();
        $this->assertCount(1, $ativos);
        $this->assertIsBool($ativos->first()->ativo);
        $this->assertTrue($ativos->first()->ativo);
    }

    public function test_bio_e_sanitizada(): void
    {
        $autor = AutorEspiritual::factory()->create(['bio' => '<p>Legítimo</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script', (string) $autor->bio);
        $this->assertStringContainsString('Legítimo', (string) $autor->bio);
    }

    public function test_implementa_contratos_de_midia_e_departamento(): void
    {
        $autor = new AutorEspiritual;
        $this->assertInstanceOf(HasMedia::class, $autor);
        $this->assertInstanceOf(TemDepartamento::class, $autor);
    }

    public function test_departamentos_anexa_e_le_pelo_pivo(): void
    {
        $autor = AutorEspiritual::factory()->create();
        $depto = Departamento::create(['sigla' => 'DEPAE', 'nome' => 'Assistência Espiritual', 'slug' => 'depae']);

        $autor->departamentos()->sync([$depto->id]);

        $this->assertTrue($autor->fresh()->departamentos->contains('id', $depto->id));
        $this->assertDatabaseHas('departamento_autor_espiritual', [
            'autor_espiritual_id' => $autor->id, 'departamento_id' => $depto->id,
        ]);
    }

    public function test_foto_registra_conversoes_web_e_thumb(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create();

        $autor->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('foto.png')
            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);

        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualTest`
Esperado: FAIL — `Class "App\Models\AutorEspiritual" not found`.

- [ ] **Passo 3: Criar a migration da tabela base**

`database/migrations/2026_07_17_000001_create_autores_espirituais_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autores_espirituais', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('chamada')->nullable();
            $table->longText('bio')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autores_espirituais');
    }
};
```

- [ ] **Passo 4: Criar a migration do pivô**

`database/migrations/2026_07_17_000002_create_departamento_autor_espiritual_table.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_autor_espiritual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autor_espiritual_id')->constrained('autores_espirituais')->cascadeOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();

            $table->unique(['autor_espiritual_id', 'departamento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_autor_espiritual');
    }
};
```

- [ ] **Passo 5: Criar o model**

`app/Models/AutorEspiritual.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Models;

use App\Models\Concerns\RegistraImagensPadrao;
use App\Models\Concerns\TemIniciais;
use App\Models\Contracts\TemDepartamento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AutorEspiritual extends Model implements HasMedia, TemDepartamento
{
    use HasFactory, InteractsWithMedia, RegistraImagensPadrao, TemIniciais;

    // Pluralização pt-BR: o pluralizador do Laravel geraria 'autor_espirituals'.
    protected $table = 'autores_espirituais';

    public const COLECAO_FOTO = 'foto';

    protected $fillable = ['nome', 'slug', 'chamada', 'bio', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(Departamento::class, 'departamento_autor_espiritual', 'autor_espiritual_id', 'departamento_id');
    }

    public function registerMediaCollections(): void
    {
        // Tratamento padrão de imagem (trait RegistraImagensPadrao): disco public, WebP web + miniatura.
        $this->registrarColecaoImagem(self::COLECAO_FOTO);
    }

    /** URL da foto (WebP web) via Media Library, ou null. */
    protected function fotoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'web') ?: null,
        );
    }

    /** URL da miniatura (WebP thumb) via Media Library, ou null. */
    protected function fotoThumbUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->getFirstMediaUrl(self::COLECAO_FOTO, 'thumb') ?: null,
        );
    }

    protected function bio(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? clean($value, 'conteudo') : null,
        );
    }
}
```

- [ ] **Passo 6: Criar a factory**

`database/factories/AutorEspiritualFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AutorEspiritualFactory extends Factory
{
    public function definition(): array
    {
        $nome = fake()->name();

        return [
            'nome' => $nome,
            'slug' => Str::slug($nome).'-'.fake()->unique()->numberBetween(1, 99999),
            'bio' => '<p>'.fake()->paragraph().'</p>',
            'ativo' => true,
        ];
    }

    public function ativo(): static
    {
        return $this->state(fn () => ['ativo' => true]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
```

- [ ] **Passo 7: Aplicar as migrations no dev (incremental)**

Run: `docker compose exec -T app php artisan migrate`
Esperado: `2026_07_17_000001_create_autores_espirituais_table ... DONE` e `..._000002_...departamento_autor_espiritual ... DONE`. **Nenhuma** outra migration roda (incremental).

- [ ] **Passo 8: Rodar o teste e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualTest`
Esperado: PASS (todos os métodos).

- [ ] **Passo 9: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Models/AutorEspiritual.php database/factories/AutorEspiritualFactory.php database/migrations/2026_07_17_000001_create_autores_espirituais_table.php database/migrations/2026_07_17_000002_create_departamento_autor_espiritual_table.php tests/Feature/Models/AutorEspiritualTest.php
git add app/Models/AutorEspiritual.php database/factories/AutorEspiritualFactory.php database/migrations/2026_07_17_0000*.php tests/Feature/Models/AutorEspiritualTest.php
git commit -m "feat(camada-4-fatia-1): model AutorEspiritual + tabela + pivô + factory"
```

---

### Task 2: Camada 1 data-driven — glossário + semente + testes de contagem

**Files:**
- Modify: `app/Support/Autorizacao/GlossarioCapacidades.php` (3 mapas + docblock)
- Modify: `database/seeders/TiposConteudoSeeder.php:24-30` (1 linha em `SEMENTE`)
- Modify: `tests/Feature/Autorizacao/CapacidadesSeederTest.php` (20→24 + 4 nomes)
- Modify: `tests/Feature/Autorizacao/TiposConteudoSeederTest.php` (asserts aditivos do autor)

**Interfaces:**
- Consumes: `App\Models\AutorEspiritual` (Task 1), `App\Enums\RegimeAcesso`.
- Produces: recurso `'autor_espiritual'` no catálogo ⇒ permissions `autor_espiritual.{ver,criar,editar,excluir}`; tipo `autor_espiritual` regime `DoTipo` responsáveis `['DEPAE','DECOM']`.

**Contexto:** somar `'autor_espiritual'` ao **FINAL** de `RECURSOS` (R1) propaga a `CapacidadesSeeder`, `MatrizCapacidades` e `GlossarioCapacidadesMapaTest` sozinho; o `TiposConteudoSeeder` **exige** a semente (senão `RuntimeException`). `GlossarioCapacidadesMapaTest` fica verde por construção (o model existe da Task 1). `MatrizCapacidadesTest` não conta Sections — não muda de cor.

- [ ] **Passo 1: Atualizar `CapacidadesSeederTest` (o teste guia — vai falhar)**

Em `tests/Feature/Autorizacao/CapacidadesSeederTest.php`, renomear o método e ajustar para 24 + os 4 nomes novos:

```php
    public function test_semeia_os_24_nomes_exatos_e_e_idempotente(): void
    {
        $this->seed(CapacidadesSeeder::class);
        $this->seed(CapacidadesSeeder::class); // 2ª vez não duplica

        $esperados = [
            'evento.ver', 'evento.criar', 'evento.editar', 'evento.excluir',
            'palestra.ver', 'palestra.criar', 'palestra.editar', 'palestra.excluir',
            'post.ver', 'post.criar', 'post.editar', 'post.excluir',
            'agenda.ver', 'agenda.criar', 'agenda.editar', 'agenda.excluir',
            'palestrante.ver', 'palestrante.criar', 'palestrante.editar', 'palestrante.excluir',
            'autor_espiritual.ver', 'autor_espiritual.criar', 'autor_espiritual.editar', 'autor_espiritual.excluir',
        ];

        $this->assertSame(24, Permission::where('guard_name', 'web')->count());
        foreach ($esperados as $nome) {
            $this->assertDatabaseHas('permissions', ['name' => $nome, 'guard_name' => 'web']);
        }
    }
```

- [ ] **Passo 2: Atualizar `TiposConteudoSeederTest` (asserts aditivos do autor — vão falhar)**

Em `tests/Feature/Autorizacao/TiposConteudoSeederTest.php`, no `test_a_semente_bate_com_o_que_cada_tipo_ja_tem_hoje` acrescentar a linha do autor:

```php
        $this->assertSame(['DECOM', 'DEPAE'], $this->siglasDe('autor_espiritual'));
```

E no `test_regimes_da_semente` incluir `'autor_espiritual'` no laço DoTipo:

```php
        foreach (['agenda', 'palestra', 'palestrante', 'post', 'autor_espiritual'] as $recurso) {
            $this->assertSame(RegimeAcesso::DoTipo, TipoConteudo::where('recurso', $recurso)->first()->regime);
        }
```

- [ ] **Passo 3: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter="CapacidadesSeederTest|TiposConteudoSeederTest"`
Esperado: FAIL — `24 !== 20`; e `Call to a member function ... on null` / `null !== ['DECOM','DEPAE']` (o recurso `autor_espiritual` ainda não está no glossário nem na semente).

- [ ] **Passo 4: Adicionar `'autor_espiritual'` ao glossário**

Em `app/Support/Autorizacao/GlossarioCapacidades.php`:

1. `use App\Models\AutorEspiritual;` (no topo, junto dos outros models).
2. `RECURSOS` — anexar ao **final** (R1):

```php
    public const RECURSOS = ['evento', 'palestra', 'post', 'agenda', 'palestrante', 'autor_espiritual'];
```

3. `RECURSOS_ROTULOS` — acrescentar:

```php
        'autor_espiritual' => 'Autor Espiritual',
```

4. `RECURSOS_MODELS` — acrescentar:

```php
        'autor_espiritual' => AutorEspiritual::class,
```

5. Atualizar o docblock de `permissions()` de "os 20 nomes" para "os 24 nomes".

- [ ] **Passo 5: Adicionar a semente do tipo**

Em `database/seeders/TiposConteudoSeeder.php`, dentro de `SEMENTE` (`:24-30`), acrescentar a linha:

```php
        'autor_espiritual' => ['regime' => RegimeAcesso::DoTipo, 'siglas' => ['DEPAE', 'DECOM']],
```

- [ ] **Passo 6: Rodar os testes tocados + os vizinhos data-driven**

Run: `docker compose exec -T app php artisan test --filter="CapacidadesSeederTest|TiposConteudoSeederTest|GlossarioCapacidadesMapaTest|MatrizCapacidadesTest"`
Esperado: PASS. (Se algum rodar código stale, `docker compose restart app worker` e repita.)

- [ ] **Passo 7: Semear o dev (idempotente, insert-only)**

Run: `docker compose exec -T app php artisan db:seed --class=CapacidadesSeeder && docker compose exec -T app php artisan db:seed --class=TiposConteudoSeeder`
Esperado: sem erro. Cria `autor_espiritual.*` (4 permissions) e o tipo `autor_espiritual` (DoTipo, DEPAE+DECOM) se ainda não existirem; **não** toca config já feita na tela.

- [ ] **Passo 8: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/TiposConteudoSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php tests/Feature/Autorizacao/TiposConteudoSeederTest.php
git add app/Support/Autorizacao/GlossarioCapacidades.php database/seeders/TiposConteudoSeeder.php tests/Feature/Autorizacao/CapacidadesSeederTest.php tests/Feature/Autorizacao/TiposConteudoSeederTest.php
git commit -m "feat(camada-4-fatia-1): recurso autor_espiritual no glossário + semente DoTipo DEPAE/DECOM"
```

---

### Task 3: Policy de capacidade (DoTipo)

**Files:**
- Create: `app/Policies/AutorEspiritualPolicy.php`
- Test: `tests/Feature/Autorizacao/AutorEspiritualPolicyCapacidadeTest.php`

**Interfaces:**
- Consumes: `App\Policies\Concerns\AutorizaPorDepartamento` (trait), `App\Models\{AutorEspiritual,User}`.
- Produces: `AutorEspiritualPolicy` com `recurso() => 'autor_espiritual'` e abilities pt-BR `ver/criar/editar/excluir`. Auto-descoberta (convenção `Models\X → Policies\XPolicy` — sem registro manual).

**Contexto:** clone da [PalestrantePolicy](../../../app/Policies/PalestrantePolicy.php) trocando só `recurso()` e os tipos. Regime **DoTipo**: `noEscopo` cai em `usuarioHabilitadoNoTipo` — responsável pelo **tipo**; o **objeto NÃO é consultado** (autor sem departamento é editável pelo responsável). O admin passa antes no [Gate::before](../../../app/Providers/AppServiceProvider.php#L65). Molde das asserções: [AcessoPorTipoTest](../../../tests/Feature/Autorizacao/AcessoPorTipoTest.php); esqueleto (seeders/helpers): [EventoPolicyCapacidadeTest](../../../tests/Feature/Autorizacao/EventoPolicyCapacidadeTest.php).

- [ ] **Passo 1: Escrever o teste de Policy que falha**

`tests/Feature/Autorizacao/AutorEspiritualPolicyCapacidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Autorizacao;

use App\Models\AutorEspiritual;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Models\User;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutorEspiritualPolicyCapacidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('administrador', 'web');
        $this->seed(CapacidadesSeeder::class);
        (new EstruturaCemaSeeder)->run();   // DEPAE/DECOM antes da semente da config
        $this->seed(TiposConteudoSeeder::class);
    }

    private function depto(string $sigla): Departamento
    {
        return Departamento::where('sigla', $sigla)->firstOrFail();
    }

    private function usuario(array $permissoes = [], array $departamentoIds = []): User
    {
        $u = User::factory()->create();
        foreach ($permissoes as $p) {
            $u->givePermissionTo($p);
        }
        $u->departamentos()->sync($departamentoIds);

        return $u;
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->assignRole('administrador');

        return $u;
    }

    public function test_responsavel_edita_mesmo_autor_sem_departamento(): void
    {
        // DoTipo: o objeto NÃO é consultado — autor sem depto é editável pelo responsável do TIPO.
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertTrue(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_responsavel_decom_tambem_edita(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DECOM')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertTrue(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_depto_disjunto_nega(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DED')->id]);   // DED não responde por autor
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_sem_permissao_nega(): void
    {
        $u = $this->usuario([], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_sem_departamento_nega(): void
    {
        $u = $this->usuario(['autor_espiritual.editar'], []);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_recurso_sem_linha_nega_ate_responsavel(): void
    {
        TipoConteudo::where('recurso', 'autor_espiritual')->delete();   // fail-closed
        $u = $this->usuario(['autor_espiritual.editar'], [$this->depto('DEPAE')->id]);
        $autor = AutorEspiritual::factory()->create();

        $this->assertFalse(Gate::forUser($u)->check('editar', $autor));
    }

    public function test_criar_invocado_com_a_classe(): void
    {
        $comDepto = $this->usuario(['autor_espiritual.criar'], [$this->depto('DEPAE')->id]);
        $semDepto = $this->usuario(['autor_espiritual.criar'], []);

        $this->assertTrue(Gate::forUser($comDepto)->check('criar', AutorEspiritual::class));
        $this->assertFalse(Gate::forUser($semDepto)->check('criar', AutorEspiritual::class));
    }

    public function test_admin_passa_em_todas_as_acoes(): void
    {
        $admin = $this->admin();
        $autor = AutorEspiritual::factory()->create();

        foreach (['ver', 'editar', 'excluir'] as $acao) {
            $this->assertTrue(Gate::forUser($admin)->check($acao, $autor), $acao);
        }
        $this->assertTrue(Gate::forUser($admin)->check('criar', AutorEspiritual::class));
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualPolicyCapacidadeTest`
Esperado: FAIL — sem a Policy, o Gate nega tudo (até o admin? não: o admin passa no `Gate::before`; os casos `assertTrue` de responsável falham porque não há Policy que conceda).

- [ ] **Passo 3: Criar a Policy**

`app/Policies/AutorEspiritualPolicy.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Policies;

use App\Models\AutorEspiritual;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorDepartamento;

/**
 * Capacidade (quem edita) de AutorEspiritual: permissão autor_espiritual.* (hasPermissionTo, NUNCA can())
 * + escopo por regime (trait). Regime DoTipo (semente DEPAE+DECOM): o responsável é quem está num depto
 * responsável pelo TIPO; o objeto NÃO é consultado. O admin passa antes no Gate::before.
 */
class AutorEspiritualPolicy
{
    use AutorizaPorDepartamento;

    protected function recurso(): string
    {
        return 'autor_espiritual';
    }

    public function ver(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.ver') && $this->noEscopo($user, $autor);
    }

    public function criar(User $user): bool
    {
        return $user->hasPermissionTo('autor_espiritual.criar') && $this->podeCriarNoEscopo($user);
    }

    public function editar(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.editar') && $this->noEscopo($user, $autor);
    }

    public function excluir(User $user, AutorEspiritual $autor): bool
    {
        return $user->hasPermissionTo('autor_espiritual.excluir') && $this->noEscopo($user, $autor);
    }
}
```

- [ ] **Passo 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualPolicyCapacidadeTest`
Esperado: PASS.

- [ ] **Passo 5: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Policies/AutorEspiritualPolicy.php tests/Feature/Autorizacao/AutorEspiritualPolicyCapacidadeTest.php
git add app/Policies/AutorEspiritualPolicy.php tests/Feature/Autorizacao/AutorEspiritualPolicyCapacidadeTest.php
git commit -m "feat(camada-4-fatia-1): AutorEspiritualPolicy (DoTipo, clone da PalestrantePolicy)"
```

---

### Task 4: Resource `/admin` + Pages

**Files:**
- Create: `app/Filament/Resources/AutoresEspirituais/AutorEspiritualResource.php`
- Create: `app/Filament/Resources/AutoresEspirituais/Pages/CreateAutorEspiritual.php`
- Create: `app/Filament/Resources/AutoresEspirituais/Pages/EditAutorEspiritual.php`
- Create: `app/Filament/Resources/AutoresEspirituais/Pages/ListAutoresEspirituais.php`
- Test: `tests/Feature/Filament/AutorEspiritualResourceTest.php`

**Interfaces:**
- Consumes: `App\Filament\Support\ComponentesImagem`, `App\Models\AutorEspiritual`.
- Produces: Resource auto-descoberto (rota `/admin/autores-espirituais` via `$slug`), form em 3 seções (Dados incl. `ativo`; Foto; Biografia), sem campos de contato, sem Select de Departamentos.

**Contexto:** clone da [PalestranteResource](../../../app/Filament/Resources/Palestrantes/PalestranteResource.php) cortando email/telefone/mostrar_* (a seção "Contato e exibição" some) — o toggle `ativo` **muda de lar** para a seção "Dados". `$slug` explícito (C3). Auto-discovery via [AdminPanelProvider:85](../../../app/Providers/Filament/AdminPanelProvider.php#L85) — sem registro manual.

- [ ] **Passo 1: Escrever o teste do Resource que falha**

`tests/Feature/Filament/AutorEspiritualResourceTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Filament;

use App\Filament\Resources\AutoresEspirituais\Pages\CreateAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\EditAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\ListAutoresEspirituais;
use App\Models\AutorEspiritual;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutorEspiritualResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    public function test_lista_renderiza(): void
    {
        Livewire::test(ListAutoresEspirituais::class)->assertSuccessful();
    }

    public function test_autor_aparece_na_tabela(): void
    {
        AutorEspiritual::factory()->create(['nome' => 'Bezerra de Menezes']);

        Livewire::test(ListAutoresEspirituais::class)->assertSee('Bezerra de Menezes');
    }

    public function test_form_nome_obrigatorio(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('nome', fn (TextInput $f) => $f->isRequired());
    }

    public function test_form_tem_rich_editor_bio(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('bio', fn (RichEditor $f) => true);
    }

    public function test_form_usa_media_library_para_foto(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('foto', fn (SpatieMediaLibraryFileUpload $c): bool => $c->getCollection() === AutorEspiritual::COLECAO_FOTO);
    }

    public function test_chamada_opcional(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldExists('chamada', fn (TextInput $f): bool => ! $f->isRequired());
    }

    public function test_form_nao_tem_campos_de_contato(): void
    {
        // A ausência é garantida no schema da tabela (ver AutorEspiritualTest). Aqui provamos no form.
        // Se `assertFormFieldDoesNotExist` não existir na sua versão do Filament, remova estas 4 linhas —
        // a garantia dura é o teste de tabela (Task 1) + o corte da seção no form.
        Livewire::test(CreateAutorEspiritual::class)
            ->assertFormFieldDoesNotExist('email')
            ->assertFormFieldDoesNotExist('telefone')
            ->assertFormFieldDoesNotExist('mostrar_email')
            ->assertFormFieldDoesNotExist('mostrar_telefone');
    }

    public function test_cria_autor_com_chamada_e_bio_sanitizada(): void
    {
        Livewire::test(CreateAutorEspiritual::class)
            ->fillForm([
                'nome' => 'Irmã Cecília',
                'slug' => 'irma-cecilia',
                'chamada' => 'Servindo na seara.',
                'ativo' => true,
                'bio' => '<p>Espírito de luz.</p><script>alert(1)</script>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $autor = AutorEspiritual::where('slug', 'irma-cecilia')->first();
        $this->assertNotNull($autor);
        $this->assertSame('Servindo na seara.', $autor->chamada);
        $this->assertStringNotContainsString('<script', (string) $autor->bio);
    }

    public function test_edita_autor(): void
    {
        $autor = AutorEspiritual::factory()->create(['nome' => 'Nome Antigo', 'slug' => 'nome-antigo']);

        Livewire::test(EditAutorEspiritual::class, ['record' => $autor->getRouteKey()])
            ->fillForm(['nome' => 'Nome Atualizado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('autores_espirituais', ['slug' => 'nome-antigo', 'nome' => 'Nome Atualizado']);
    }

    public function test_rota_de_listagem_responde_ok(): void
    {
        AutorEspiritual::factory()->count(3)->create();

        $this->get('/admin/autores-espirituais')->assertOk();
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualResourceTest`
Esperado: FAIL — classes de Page/Resource inexistentes.

- [ ] **Passo 3: Criar o Resource**

`app/Filament/Resources/AutoresEspirituais/AutorEspiritualResource.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais;

use App\Filament\Resources\AutoresEspirituais\Pages\CreateAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\EditAutorEspiritual;
use App\Filament\Resources\AutoresEspirituais\Pages\ListAutoresEspirituais;
use App\Filament\Support\ComponentesImagem;
use App\Models\AutorEspiritual;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AutorEspiritualResource extends Resource
{
    protected static ?string $model = AutorEspiritual::class;

    // Sem $slug o Laravel geraria 'autor-espirituals' (pluralizador inglês) — travamos a rota pt-BR.
    protected static ?string $slug = 'autores-espirituais';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Autores Espirituais';

    protected static ?string $modelLabel = 'Autor Espiritual';

    protected static ?string $pluralModelLabel = 'Autores Espirituais';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            })
                            ->columnSpan(2),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Toggle::make('ativo')
                            ->label('Autor ativo')
                            ->default(true),

                        TextInput::make('chamada')
                            ->label('Chamada (frase do hero)')
                            ->helperText('Frase curta exibida no topo do perfil. Opcional.')
                            ->maxLength(180)
                            ->columnSpan(2),
                    ]),

                Section::make('Foto')
                    ->schema([
                        ComponentesImagem::upload('foto', AutorEspiritual::COLECAO_FOTO)
                            ->label('Foto do autor espiritual'),
                    ]),

                Section::make('Biografia')
                    ->schema([
                        RichEditor::make('bio')
                            ->label('Biografia')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'h2',
                                'h3',
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('foto')
                    ->label('Foto')
                    ->collection(AutorEspiritual::COLECAO_FOTO)
                    ->conversion('thumb')
                    ->circular(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('nome')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionados'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutoresEspirituais::route('/'),
            'create' => CreateAutorEspiritual::route('/create'),
            'edit' => EditAutorEspiritual::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Passo 4: Criar as 3 Pages**

`app/Filament/Resources/AutoresEspirituais/Pages/CreateAutorEspiritual.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais\Pages;

use App\Filament\Resources\AutoresEspirituais\AutorEspiritualResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAutorEspiritual extends CreateRecord
{
    protected static string $resource = AutorEspiritualResource::class;
}
```

`app/Filament/Resources/AutoresEspirituais/Pages/EditAutorEspiritual.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais\Pages;

use App\Filament\Resources\AutoresEspirituais\AutorEspiritualResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAutorEspiritual extends EditRecord
{
    protected static string $resource = AutorEspiritualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
```

`app/Filament/Resources/AutoresEspirituais/Pages/ListAutoresEspirituais.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Filament\Resources\AutoresEspirituais\Pages;

use App\Filament\Resources\AutoresEspirituais\AutorEspiritualResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAutoresEspirituais extends ListRecords
{
    protected static string $resource = AutorEspiritualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
```

- [ ] **Passo 5: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=AutorEspiritualResourceTest`
Esperado: PASS. (Se `assertFormFieldDoesNotExist` não existir na versão do Filament, remover o `test_form_nao_tem_campos_de_contato` — a ausência das colunas já é provada no `AutorEspiritualTest` da Task 1.)

- [ ] **Passo 6: Conferir no navegador (real)**

`docker compose restart app worker` e abrir `http://localhost/admin/autores-espirituais` (logado como admin): a lista renderiza, "Novo" abre o form com Dados/Foto/Biografia, **sem** campos de contato.

- [ ] **Passo 7: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Filament/Resources/AutoresEspirituais tests/Feature/Filament/AutorEspiritualResourceTest.php
git add app/Filament/Resources/AutoresEspirituais tests/Feature/Filament/AutorEspiritualResourceTest.php
git commit -m "feat(camada-4-fatia-1): AutorEspiritualResource + Pages (/admin, clone do Palestrante sem contato)"
```

---

### Task 5: Cadeia de importação — Leitor + Importador + Command + bind

**Files:**
- Create: `app/Importacao/LeitorAutoresEspirituais.php` (interface)
- Create: `app/Importacao/LeitorAutoresEspirituaisMysql.php`
- Create: `app/Importacao/ImportadorAutoresEspirituais.php`
- Create: `app/Console/Commands/ImportarAutoresEspirituais.php`
- Modify: `app/Providers/AppServiceProvider.php` (1 bind + 2 `use`)
- Test: `tests/Feature/Importacao/ImportadorAutoresEspirituaisTest.php`
- Test: `tests/Feature/Importacao/ImportarAutoresEspirituaisCommandTest.php`

**Interfaces:**
- Consumes: `App\Importacao\BaixadorImagem`, `App\Models\AutorEspiritual`, conexão `legado`.
- Produces:
  - `LeitorAutoresEspirituais::autores(): array` — cada item `['slug','nome','bio','foto_url']`.
  - `ImportadorAutoresEspirituais::__construct(LeitorAutoresEspirituais $leitor, BaixadorImagem $baixador)` + `importar(?callable $log=null): array` → `['autores'=>int, 'avisos'=>list<string>, 'contadores'=>['com_foto','sem_thumbnail','falha_foto']]`.
  - Command `cema:importar-autores-espirituais`.
  - Bind `LeitorAutoresEspirituais → LeitorAutoresEspirituaisMysql` no `AppServiceProvider` (C7/I12).

**Contexto:** clone da cadeia de Eventos ([ImportadorEventos](../../../app/Importacao/ImportadorEventos.php), [LeitorEventosMysql](../../../app/Importacao/LeitorEventosMysql.php), [ImportarEventos](../../../app/Console/Commands/ImportarEventos.php)). Diferenças travadas: **chave = slug** (sem `wp_id`); **updateOrCreate só com `nome`/`bio`** (chamada/ativo do admin — I10); **`clearMediaCollection` só após download bem-sucedido** dentro do `if foto_url` (O1); **sem** `departamentos()->sync()` (DoTipo — I10); **sem** fail-fast de catálogo (não há pré-requisito — a guarda é só a conexão `legado`); **bind obrigatório** no `AppServiceProvider` (C7). Medição do legado (§4 do spec): 19 autores publish, 5 sem thumbnail, 6 bios vazias, slug ≠ título às vezes.

- [ ] **Passo 1: Escrever o teste do Importador que falha**

`tests/Feature/Importacao/ImportadorAutoresEspirituaisTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Importacao;

use App\Importacao\BaixadorImagem;
use App\Importacao\ImportadorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituais;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportadorAutoresEspirituaisTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';

    private function leitor(array $autores): LeitorAutoresEspirituais
    {
        return new class($autores) implements LeitorAutoresEspirituais
        {
            public function __construct(private array $autores) {}

            public function autores(): array
            {
                return $this->autores;
            }
        };
    }

    private function baixador(): BaixadorImagem
    {
        return new class extends BaixadorImagem
        {
            public function baixarCapado(?string $url, int $teto = 2000): ?string
            {
                return $url ? base64_decode(ImportadorAutoresEspirituaisTest::pngBytes()) : null;
            }
        };
    }

    public static function pngBytes(): string
    {
        return self::PNG_1X1;
    }

    private function autorLegado(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'bezerra-de-menezes',
            'nome' => 'Bezerra de Menezes',
            'bio' => '<p>Médico dos pobres.</p>',
            'foto_url' => 'https://legado.example/wp-content/uploads/bezerra.jpg',
        ], $overrides);
    }

    private function importar(array $autores): array
    {
        return (new ImportadorAutoresEspirituais($this->leitor($autores), $this->baixador()))->importar();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_mapeia_nome_slug_bio_e_foto(): void
    {
        $this->importar([$this->autorLegado()]);

        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $this->assertNotNull($autor);
        $this->assertSame('Bezerra de Menezes', $autor->nome);
        $this->assertStringContainsString('Médico dos pobres', (string) $autor->bio);
        $this->assertTrue($autor->hasMedia(AutorEspiritual::COLECAO_FOTO));
        $this->assertNull($autor->chamada);   // legado não tem chamada
        $this->assertTrue($autor->ativo);      // default true
    }

    public function test_bio_vazia_vira_null(): void
    {
        $this->importar([$this->autorLegado(['slug' => 'irma-marta', 'bio' => null, 'foto_url' => null])]);

        $this->assertNull(AutorEspiritual::firstWhere('slug', 'irma-marta')->bio);
    }

    public function test_autor_sem_thumbnail_fica_sem_midia_sem_erro(): void
    {
        $resumo = $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);

        $autor = AutorEspiritual::firstWhere('slug', 'abilio');
        $this->assertFalse($autor->hasMedia(AutorEspiritual::COLECAO_FOTO));
        $this->assertSame(1, $resumo['contadores']['sem_thumbnail']);
    }

    public function test_nao_sincroniza_departamentos(): void
    {
        $this->importar([$this->autorLegado(['foto_url' => null])]);

        $this->assertSame(0, AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes')->departamentos()->count());
    }

    public function test_e_idempotente(): void
    {
        $this->importar([$this->autorLegado()]);
        $this->importar([$this->autorLegado()]);

        $this->assertSame(1, AutorEspiritual::count());
        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $this->assertSame(1, $autor->getMedia(AutorEspiritual::COLECAO_FOTO)->count()); // não duplicou
    }

    public function test_reimport_preserva_chamada_e_ativo_do_admin(): void
    {
        $this->importar([$this->autorLegado(['foto_url' => null])]);

        $autor = AutorEspiritual::firstWhere('slug', 'bezerra-de-menezes');
        $autor->update(['chamada' => 'O médico dos pobres.', 'ativo' => false]);   // curadoria do admin

        $this->importar([$this->autorLegado(['foto_url' => null])]);   // re-import (legado sem chamada/ativo)

        $autor->refresh();
        $this->assertSame('O médico dos pobres.', $autor->chamada);
        $this->assertFalse($autor->ativo);
    }

    public function test_reimport_de_autor_sem_thumbnail_preserva_foto_do_admin(): void
    {
        // O1: o clobber que o molde de Eventos faria.
        $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);
        $autor = AutorEspiritual::firstWhere('slug', 'abilio');

        $autor->addMediaFromString(base64_decode(self::PNG_1X1))
            ->usingFileName('manual.png')
            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO));

        $this->importar([$this->autorLegado(['slug' => 'abilio', 'foto_url' => null])]);   // re-import sem thumbnail

        $this->assertTrue($autor->fresh()->hasMedia(AutorEspiritual::COLECAO_FOTO), 'a foto do /admin foi apagada — clobber de mídia (O1)');
    }
}
```

- [ ] **Passo 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorAutoresEspirituaisTest`
Esperado: FAIL — `interface App\Importacao\LeitorAutoresEspirituais not found`.

- [ ] **Passo 3: Criar a interface do Leitor**

`app/Importacao/LeitorAutoresEspirituais.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

interface LeitorAutoresEspirituais
{
    /**
     * Autores espirituais lidos do legado, normalizados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function autores(): array;
}
```

- [ ] **Passo 4: Criar o Importador**

`app/Importacao/ImportadorAutoresEspirituais.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

use App\Models\AutorEspiritual;
use Illuminate\Support\Facades\DB;

class ImportadorAutoresEspirituais
{
    private array $avisos = [];

    private array $contadores = [];

    public function __construct(
        private LeitorAutoresEspirituais $leitor,
        private BaixadorImagem $baixador,
    ) {}

    public function importar(?callable $log = null): array
    {
        $log ??= fn (string $m) => null;
        $this->avisos = [];
        $this->contadores = ['com_foto' => 0, 'sem_thumbnail' => 0, 'falha_foto' => 0];

        $processados = 0;

        foreach ($this->leitor->autores() as $d) {
            DB::transaction(function () use ($d, $log) {
                // Chave = slug (não há wp_id). NÃO setar chamada/ativo: são do admin (I10) —
                // chamada nasce null, ativo default true; ambos preservados num re-import.
                $autor = AutorEspiritual::updateOrCreate(
                    ['slug' => $d['slug']],
                    ['nome' => $d['nome'], 'bio' => $d['bio'] ?? null],
                );

                // O1: o legado sobrescreve a foto SÓ quando tem thumbnail; e o clearMediaCollection roda
                // SÓ após um download bem-sucedido. Assim autor sem thumbnail (ou download falho) preserva
                // a foto posta no /admin. Idempotente: mesmo thumbnail => mesma foto reanexada (1 mídia).
                if (! empty($d['foto_url'])) {
                    $bytes = $this->baixador->baixarCapado($d['foto_url'], 2000);
                    if ($bytes !== null) {
                        $autor->clearMediaCollection(AutorEspiritual::COLECAO_FOTO);
                        $autor->addMediaFromString($bytes)
                            ->usingFileName(basename(parse_url($d['foto_url'], PHP_URL_PATH) ?? 'foto.jpg'))
                            ->withCustomProperties(['url_legado' => $d['foto_url']])
                            ->toMediaCollection(AutorEspiritual::COLECAO_FOTO);
                        $this->contadores['com_foto']++;
                    } else {
                        $this->avisos[] = "[{$d['slug']}] falha ao baixar foto (mídia existente preservada)";
                        $this->contadores['falha_foto']++;
                    }
                } else {
                    $this->contadores['sem_thumbnail']++;
                }

                $log("Autor importado: {$d['slug']}");
            });

            $processados++;
        }

        return ['autores' => $processados, 'avisos' => $this->avisos, 'contadores' => $this->contadores];
    }
}
```

- [ ] **Passo 5: Rodar o teste do Importador e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportadorAutoresEspirituaisTest`
Esperado: PASS (inclui `test_reimport_de_autor_sem_thumbnail_preserva_foto_do_admin` — a prova do O1).

- [ ] **Passo 6: Escrever o teste do Command (+ guarda do bind) que falha**

`tests/Feature/Importacao/ImportarAutoresEspirituaisCommandTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Feature\Importacao;

use App\Importacao\ImportadorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituaisMysql;
use App\Models\AutorEspiritual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportarAutoresEspirituaisCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_importa_via_leitor_injetado(): void
    {
        Storage::fake('public');

        // fake do leitor no container (sem tocar o legado; sem imagens p/ determinismo)
        $this->app->bind(LeitorAutoresEspirituais::class, fn () => new class implements LeitorAutoresEspirituais
        {
            public function autores(): array
            {
                return [[
                    'slug' => 'catarina', 'nome' => 'Catarina', 'bio' => '<p>Guia.</p>', 'foto_url' => null,
                ]];
            }
        });

        $this->artisan('cema:importar-autores-espirituais')->assertSuccessful();

        $this->assertSame(1, AutorEspiritual::count());
        $this->assertSame('Catarina', AutorEspiritual::firstWhere('slug', 'catarina')->nome);
    }

    /** Guarda C7/I12: sem bind manual, resolver a INTERFACE devolve o ...Mysql (bind do AppServiceProvider). */
    public function test_interface_do_leitor_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(
            LeitorAutoresEspirituaisMysql::class,
            app(LeitorAutoresEspirituais::class),
        );
    }

    /** Guarda C7/I12: o Importador resolve pelo container (constrói a cadeia real) sem bind manual. */
    public function test_importador_resolve_pelo_container(): void
    {
        $this->assertInstanceOf(
            ImportadorAutoresEspirituais::class,
            app(ImportadorAutoresEspirituais::class),
        );
    }
}
```

- [ ] **Passo 7: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=ImportarAutoresEspirituaisCommandTest`
Esperado: FAIL — `command "cema:importar-autores-espirituais" is not defined` e/ou `Target [App\Importacao\LeitorAutoresEspirituais] is not instantiable` (interface sem bind).

- [ ] **Passo 8: Criar o Leitor Mysql**

`app/Importacao/LeitorAutoresEspirituaisMysql.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorAutoresEspirituaisMysql implements LeitorAutoresEspirituais
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function autores(): array
    {
        $posts = $this->db->select(
            "SELECT ID, post_title, post_name, post_content
             FROM wp_posts
             WHERE post_type = 'autores-espirituais' AND post_status = 'publish'"
        );

        $out = [];
        foreach ($posts as $p) {
            $meta = $this->metasDe((int) $p->ID);

            $thumbId = isset($meta['_thumbnail_id']) && $meta['_thumbnail_id'] !== ''
                ? (int) $meta['_thumbnail_id']
                : null;

            $out[] = [
                'slug' => $p->post_name,
                'nome' => $p->post_title,
                'bio' => $p->post_content ?: null,
                'foto_url' => $thumbId ? $this->urlDaImagem($thumbId) : null,
            ];
        }

        return $out;
    }

    /** @return array<string,string> meta_key => meta_value (1º valor por chave) */
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
}
```

- [ ] **Passo 9: Criar o Command**

`app/Console/Commands/ImportarAutoresEspirituais.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace App\Console\Commands;

use App\Importacao\ImportadorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituaisMysql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarAutoresEspirituais extends Command
{
    protected $signature = 'cema:importar-autores-espirituais';

    protected $description = 'Importa os autores espirituais (CPT autores-espirituais) do WordPress legado (somente leitura) para o MySQL local.';

    public function handle(LeitorAutoresEspirituais $leitor, ImportadorAutoresEspirituais $importador): int
    {
        // valida a conexão legado apenas quando o leitor real está em uso (túnel SSH ativo?)
        if ($leitor instanceof LeitorAutoresEspirituaisMysql) {
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
        $this->info("Importação concluída: {$resumo['autores']} autores espirituais.");
        $c = $resumo['contadores'];
        $this->line("  Com foto: {$c['com_foto']} · Sem thumbnail: {$c['sem_thumbnail']} · Falha de foto: {$c['falha_foto']}");
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

- [ ] **Passo 10: Adicionar o bind no `AppServiceProvider` (C7)**

Em `app/Providers/AppServiceProvider.php`, acrescentar os 2 `use` (ordem alfabética junto dos leitores) e a linha de bind ao lado dos outros (`register()`, após o bind de `LeitorUsuarios`):

```php
use App\Importacao\LeitorAutoresEspirituais;
use App\Importacao\LeitorAutoresEspirituaisMysql;
```

```php
        $this->app->bind(LeitorAutoresEspirituais::class, LeitorAutoresEspirituaisMysql::class);
```

- [ ] **Passo 11: Rodar o teste do Command e ver passar**

Run: `docker compose exec -T app php artisan test --filter=ImportarAutoresEspirituaisCommandTest`
Esperado: PASS (incl. as duas guardas do bind C7/I12).

- [ ] **Passo 12: Pint + commit**

```bash
docker compose exec -T app ./vendor/bin/pint app/Importacao/LeitorAutoresEspirituais.php app/Importacao/LeitorAutoresEspirituaisMysql.php app/Importacao/ImportadorAutoresEspirituais.php app/Console/Commands/ImportarAutoresEspirituais.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorAutoresEspirituaisTest.php tests/Feature/Importacao/ImportarAutoresEspirituaisCommandTest.php
git add app/Importacao/LeitorAutoresEspirituais.php app/Importacao/LeitorAutoresEspirituaisMysql.php app/Importacao/ImportadorAutoresEspirituais.php app/Console/Commands/ImportarAutoresEspirituais.php app/Providers/AppServiceProvider.php tests/Feature/Importacao/ImportadorAutoresEspirituaisTest.php tests/Feature/Importacao/ImportarAutoresEspirituaisCommandTest.php
git commit -m "feat(camada-4-fatia-1): importação dos autores espirituais (leitor+importador+command+bind)"
```

---

### Task 6: Verificação end-to-end e fechamento

**Files:** nenhum de código.

- [ ] **Passo 1: Rodar a suíte completa + Pint**

```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app php artisan test
```
Esperado: **Pint** limpo; suíte **verde** (879 + os novos testes das Tasks 1–5). Se 2 testes de cap de imagem do blog falharem sob carga, rodar isolados p/ confirmar que não é regressão desta fatia ([[flaky-importadorblog-gd-cap-imagem]]).

- [ ] **Passo 2: Revalidar o leitor real contra o legado vivo (R3 — antes de fechar)**

Com o túnel SSH ativo, importar os 19 de verdade no dev (idempotente):

```bash
docker compose exec -T app php artisan cema:importar-autores-espirituais
```
Esperado: `Importação concluída: 19 autores espirituais.` com `Sem thumbnail: 5`. Conferir no `/admin/autores-espirituais` que os 19 aparecem, 14 com foto. Rodar **de novo** e confirmar que continua **19** (idempotência, sem duplicar mídia). ⚠️ Se o túnel estiver caído, o command falha com a mensagem de diagnóstico — subir o túnel e repetir. **É a checagem que valida o SQL do leitor contra o banco real** ([[verificar-leitor-legado-contra-banco-real]]).

- [ ] **Passo 3: Commitar os docs e abrir o PR**

```bash
git add docs/superpowers/specs/2026-07-17-camada-4-fatia-1-autores-espirituais.md docs/superpowers/plans/2026-07-17-camada-4-fatia-1-autores-espirituais.md
git commit -m "docs(camada-4-fatia-1): SPEC + plano + passe adversarial dos autores espirituais"
git push -u origin camada-4-fatia-1-autores-espirituais
gh pr create --base main --title "Camada 4 · Fatia 1 — Autores Espirituais" --body "Entidade AutorEspiritual (clone do Palestrante, DoTipo), CRUD /admin e importação dos 19 do CPT autores-espirituais. SPEC/plano em docs/superpowers. Cutover de prod no §8 da SPEC."
```

- [ ] **Passo 4: Mesclar só com o CI verde no ÚLTIMO commit** ([[merge-so-com-ci-verde-no-commit-final]])

Aguardar o CI fechar verde no último commit antes do merge — não mesclar com check pending.

---

## Cutover de produção (do dono, no deploy — não é tarefa de código)

Sequência idempotente (§8 da SPEC): `php artisan migrate` → `db:seed --class=CapacidadesSeeder` → `db:seed --class=TiposConteudoSeeder` → `cema:importar-autores-espirituais` (túnel ativo). A capacidade nasce **inerte** (sem superfície de site do autor) — ligar `autor_espiritual.*` para DEPAE/DECOM na tela `/admin/matriz-capacidades` é cutover da **Fatia 2**, quando houver edição pelo site.

## Self-Review (feita ao escrever o plano)

- **Cobertura da SPEC:** model/migrations/factory (Task 1) · glossário+semente+bind (Tasks 2/5) · Policy DoTipo (Task 3) · Resource+Pages (Task 4) · cadeia de importação (Task 5) · invariantes I1–I12 mapeados a testes concretos · cutover (§8) documentado. Sem lacuna.
- **Sem placeholders:** todo passo de código traz o código completo; todo teste traz asserções reais.
- **Consistência de tipos/nomes:** `autores_espirituais` (tabela), `departamento_autor_espiritual`/`autor_espiritual_id` (pivô), `COLECAO_FOTO='foto'`, `LeitorAutoresEspirituais::autores()` → `['slug','nome','bio','foto_url']`, `ImportadorAutoresEspirituais(LeitorAutoresEspirituais, BaixadorImagem)`, `cema:importar-autores-espirituais` — usados de forma idêntica entre tasks.
