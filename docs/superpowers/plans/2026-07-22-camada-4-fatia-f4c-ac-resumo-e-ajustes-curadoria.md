# Camada 4 · Fatia F4c-AC — Plano de implementação

> **Para quem executa:** SUB-SKILL OBRIGATÓRIA — use `superpowers:subagent-driven-development`
> (recomendado) ou `superpowers:executing-plans` para implementar task a task. Os passos usam
> checkbox (`- [ ]`) para rastreio.

**Objetivo:** recuperar os 151 resumos editoriais que o WordPress tinha e a migração perdeu; e
fechar dois defeitos do `/admin` — imagem que some do site em 2 dos 3 formatos, e publicação
sem regra e sem autoria.

**Arquitetura:** três frentes independentes sobre o mesmo terreno. **(A)** coluna `resumo` +
leitor/comando dedicados ao legado + 3 pontos de exibição. **(C1)** a coleção de mídia é
renomeada `pictografia` → `imagens` e a galeria vira componente único, consumido pela
pictografia e pela psicografia (a psicofonia herda pelo `@include` que já existe). **(C2)** a
regra de publicação da F4b passa a valer nos 3 caminhos do painel — Action, Select e criação —
com autoria carimbada **só na transição** e transação de banco de verdade.

**Stack:** PHP 8.3 · Laravel 13 · Filament **5.6.7** · MySQL 8 (dev/prod) · SQLite em memória
(testes) · Blade + Livewire · Tailwind v4 · spatie/laravel-medialibrary ·
spatie/laravel-activitylog.

**SPEC:** [2026-07-21-camada-4-fatia-f4c-ac-resumo-e-ajustes-curadoria.md](../specs/2026-07-21-camada-4-fatia-f4c-ac-resumo-e-ajustes-curadoria.md)
(commits `bba4074` + `3829daa`).

## Restrições globais

Valem para **todas** as tasks. Não repetidas em cada uma.

- **Idioma:** tudo em pt-BR — identificadores de domínio, comentários, mensagens de UI e de
  erro, commits. Sintaxe e APIs de terceiros no original.
- **Banco:** só `php artisan migrate` **incremental**. 🚫 **PROIBIDO** `migrate:fresh`,
  `migrate:refresh`, `db:wipe`, `migrate:reset` e qualquer seeder/factory destrutivo — o dev
  guarda os dados importados do legado (180 mensagens, 44 posts, mídia).
- **Legado:** conexão `legado` é **somente `SELECT`**. Nada de `INSERT/UPDATE/DELETE/DDL`.
- **Comandos rodam no container:** `docker compose exec -T app php artisan …`,
  `docker compose exec -T app ./vendor/bin/pint`. **`npm` roda no HOST** (o container não tem
  Node).
- **Pint antes de cada commit:** `docker compose exec -T app ./vendor/bin/pint`. O CI roda
  `pint --test` e **aborta antes dos testes**.
- **Cabeçalho de autoria** em todo arquivo novo relevante:
  `// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22`
- **Nunca** editar schema na mão; **nunca** versionar credencial.
- **Baseline da suíte:** 1221 testes. Ao final, deve estar verde e maior.
- **Editar Blade/PHP no dev exige** `docker compose restart app worker` (OPcache com
  `validate_timestamps=0`); `view:clear` não basta.

## Estrutura de arquivos

**Criados (12):**

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/2026_07_22_000001_add_resumo_to_mensagens_table.php` | coluna `resumo` |
| `database/migrations/2026_07_22_000002_renomeia_colecao_pictografia_para_imagens.php` | dados em `media` |
| `app/Importacao/LeitorResumosMensagens.php` | contrato do leitor (interface **própria**) |
| `app/Importacao/LeitorResumosMensagensMysql.php` | `SELECT` de `post_excerpt` no legado |
| `app/Console/Commands/ImportarResumosMensagens.php` | `cema:importar-resumos` |
| `app/Filament/Resources/Mensagens/Pages/PublicaMensagem.php` | trait: **helpers** de regra + autoria |
| `resources/views/components/mensagem/imagens.blade.php` | galeria única dos 3 formatos |
| `tests/Feature/Mensagens/GlossarioCamposParidadeTest.php` | paridade `logOnly` × glossário |
| `tests/Feature/Importacao/LeitorResumosMensagensBindTest.php` | bind do leitor |
| `tests/Feature/Importacao/ImportarResumosMensagensTest.php` | comando (I1–I5b) |
| `tests/Feature/Filament/MensagemPublicarActionTest.php` | Action + regra nos 3 caminhos |
| `tests/Feature/Filament/PublicaMensagemHelperTest.php` | a reasserção server-side isolada do form |

**Modificados (16):** `app/Models/Mensagem.php` ·
`app/Support/Mensagens/GlossarioCamposMensagem.php` · `app/Providers/AppServiceProvider.php` ·
`app/Importacao/ImportadorMensagens.php` · `app/Filament/Schemas/MensagemForm.php` ·
`app/Filament/Resources/Mensagens/MensagemResource.php` ·
`app/Filament/Resources/Mensagens/Pages/EditMensagem.php` ·
`app/Filament/Resources/Mensagens/Pages/CreateMensagem.php` ·
`app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php` ·
`resources/views/mensagens/show.blade.php` ·
`resources/views/mensagens/corpos/pictografia.blade.php` ·
`resources/views/mensagens/corpos/psicografia.blade.php` ·
`resources/views/components/mensagem/card.blade.php` · + **12 arquivos de teste existentes**:
`MensagemTest` · `MensagemShowTest` · `MensagemSeoTest` · `ImportadorMensagensTest` ·
`MensagensContaEditarTest` · `MensagemResourceTest` · `MensagensContaCriarTest` ·
`MensagemDestinatariosFormTest` · `MensagemDestinatariosPersistenciaTest` · `AutorShowTest` ·
`MensagemListaTest` · `CuradoriaContaTest`.

**Ordem das tasks e dependências:**

```
Bloco A   T1 (coluna+auditoria) → T2 (leitor) → T3 (comando)
                              ↘ T4 (front) ↘ T5 (form)
Bloco C1  T6 (rename coleção) → T7 (componente) → T8 (card)
Bloco C2  T9 (filtro ativo) ─┐
          T10 (trait efetivo)┴→ T11 (required) → T12 (regra+autoria+transação) → T13 (Action)
Fecho     T14 (documentação)
```

T1–T5 e T6–T8 e T9–T13 são **independentes entre blocos**; dentro de cada bloco a ordem é
obrigatória.

---

## Task 1: Coluna `resumo` e os cinco lugares da auditoria

**Arquivos:**
- Criar: `database/migrations/2026_07_22_000001_add_resumo_to_mensagens_table.php`
- Criar: `tests/Feature/Mensagens/GlossarioCamposParidadeTest.php`
- Modificar: `app/Models/Mensagem.php` (`$fillable` :45-58 · `logOnly` :265-266 · `tapActivity` :296)
- Modificar: `app/Support/Mensagens/GlossarioCamposMensagem.php` (:11-12 docblock, :16-28 lista)
- Modificar: `tests/Feature/Models/MensagemTest.php` (:30-46)

**Interfaces:**
- Produz: coluna `mensagens.resumo` (`text` nullable); `Mensagem::$fillable` com 13 itens;
  `GlossarioCamposMensagem::CAMPOS_ROTULOS` com 12 chaves.
- Consome: nada.

- [ ] **Passo 1: escrever o teste-contrato de paridade (vermelho)** *(I6, I7)*

Cria `tests/Feature/Mensagens/GlossarioCamposParidadeTest.php`:

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Mensagens;

use App\Models\Mensagem;
use App\Support\Mensagens\GlossarioCamposMensagem;
use Tests\TestCase;

/**
 * Trava a paridade entre as DUAS listas mantidas à mão: o `logOnly` do model e a lista branca
 * do glossário. Campo em `logOnly` e fora do glossário some do histórico do DEPAE em SILÊNCIO
 * (HistoricoMensagem descarta chave sem rótulo) — este teste é a única rede contra esse drift.
 */
class GlossarioCamposParidadeTest extends TestCase
{
    public function test_glossario_cobre_exatamente_os_campos_do_log_only(): void
    {
        $logOnly = (new Mensagem)->getActivitylogOptions()->logAttributes;
        $glossario = array_keys(GlossarioCamposMensagem::CAMPOS_ROTULOS);

        sort($logOnly);
        sort($glossario);

        $this->assertSame($logOnly, $glossario, 'logOnly e o glossário divergiram');
    }

    public function test_resumo_esta_nas_duas_listas(): void
    {
        $this->assertContains('resumo', (new Mensagem)->getActivitylogOptions()->logAttributes);
        $this->assertArrayHasKey('resumo', GlossarioCamposMensagem::CAMPOS_ROTULOS);
    }
}
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=GlossarioCamposParidadeTest
```

Esperado: `test_resumo_esta_nas_duas_listas` FALHA (`resumo` não está em nenhuma das listas).
`test_glossario_cobre_exatamente_os_campos_do_log_only` **passa** (hoje as listas batem em 11).

- [ ] **Passo 3: criar a migration**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            // Texto editorial do legado (post_excerpt, ≤1164 chars) — texto PURO, sem HTML.
            $table->text('resumo')->nullable()->after('contexto');
        });
    }

    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $table) {
            $table->dropColumn('resumo');
        });
    }
};
```

> ⚠️ **O `down()` é destrutivo depois do cutover.** Os resumos vivem só nesta coluna — o
> `tapActivity` os redige na trilha, então não há cópia recuperável. Rollback só é seguro
> **antes** de `cema:importar-resumos`; depois, o caminho é reimportar do legado, nunca
> `migrate:rollback` como "desfazer".

- [ ] **Passo 4: rodar a migration (incremental)**

```bash
docker compose exec -T app php artisan migrate
```

Esperado: `2026_07_22_000001_add_resumo_to_mensagens_table … DONE`. Nenhuma outra migration.

- [ ] **Passo 5: `$fillable` — inserir `'resumo'` após `'contexto'`**

Em `app/Models/Mensagem.php`, dentro de `$fillable`, logo depois da linha de `'contexto'`:

```php
        'contexto', // texto puro (manual, não-IA); exibido escapado no front
        'resumo',   // texto puro editorial (importado do post_excerpt do legado)
```

- [ ] **Passo 6: `logOnly` — 11 → 12 campos**

```php
            ->logOnly(['titulo', 'slug', 'corpo', 'contexto', 'resumo', 'formato', 'data_recebimento',
                'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status'])
```

- [ ] **Passo 7: `tapActivity` — `resumo` entra na redação**

Na linha 296 de `app/Models/Mensagem.php`, trocar a lista do `foreach`:

```php
            foreach (['corpo', 'contexto', 'resumo'] as $campo) {
```

Motivo (comentar acima do `foreach`, junto do texto já existente): `resumo` chega a 1164
chars e **≥94 dos 154 pertencem a mensagem restrita** — não pode ir cru para uma trilha de
retenção indefinida.

- [ ] **Passo 8: glossário — rótulo e docblock**

Em `app/Support/Mensagens/GlossarioCamposMensagem.php`, na lista, após `'contexto'`:

```php
        'contexto' => 'Contexto',
        'resumo' => 'Resumo',
```

E no docblock (:11-12), trocar `Mesmos 11 campos` por:

```php
 * App\Support\Autorizacao\GlossarioCapacidades. Mesmos 12 campos de
 * Mensagem::getActivitylogOptions()->logOnly([...]) — paridade travada por
 * Tests\Feature\Mensagens\GlossarioCamposParidadeTest.
```

- [ ] **Passo 9: rodar o teste de paridade**

```bash
docker compose exec -T app php artisan test --filter=GlossarioCamposParidadeTest
```

Esperado: **2 passed**.

- [ ] **Passo 10: atualizar os dois testes de contrato do model**

Em `tests/Feature/Models/MensagemTest.php`, incluir `resumo` nas **duas** listas — a de
colunas (`test_colunas_esperadas_e_podadas`) e a de fillable (`test_fillable_exato`), na mesma
posição (após `contexto`):

```php
        foreach (['titulo', 'slug', 'corpo', 'contexto', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'] as $coluna) {
```

```php
        $this->assertSame(
            ['titulo', 'slug', 'corpo', 'contexto', 'resumo', 'formato', 'data_recebimento', 'casa', 'link_arquivo', 'liberar_download', 'nivel', 'status', 'wp_id'],
            (new Mensagem)->getFillable(),
        );
```

- [ ] **Passo 11: escrever o teste de redação do `resumo` na trilha**

Acrescentar a `tests/Feature/Models/MensagemTest.php`:

```php
    public function test_resumo_e_redigido_na_trilha_de_auditoria(): void
    {
        $m = Mensagem::factory()->create(['resumo' => 'SENTINELA-RESUMO-ANTIGO']);

        $m->update(['resumo' => 'SENTINELA-RESUMO-NOVO']);

        $props = $m->activities()->latest('id')->first()->properties;

        $this->assertSame('[texto não registrado]', $props['attributes']['resumo']);
        $this->assertSame('[texto não registrado]', $props['old']['resumo']);

        // Sentinelas em ASCII de propósito: json_encode escapa acento (conteúdo → conteúdo),
        // e uma busca por texto acentuado passaria SEMPRE, provando nada.
        $json = $m->activities()->get()->toJson();
        $this->assertStringNotContainsString('SENTINELA-RESUMO-ANTIGO', $json);
        $this->assertStringNotContainsString('SENTINELA-RESUMO-NOVO', $json);
    }
```

- [ ] **Passo 12: rodar os testes do model**

```bash
docker compose exec -T app php artisan test --filter=MensagemTest
```

Esperado: todos verdes, incluindo o novo.

- [ ] **Passo 13: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Models/Mensagem.php app/Support/Mensagens/GlossarioCamposMensagem.php \
        database/migrations/2026_07_22_000001_add_resumo_to_mensagens_table.php \
        tests/Feature/Models/MensagemTest.php tests/Feature/Mensagens/GlossarioCamposParidadeTest.php
git commit -m "feat(f4c-ac): coluna resumo e os cinco lugares da auditoria

O campo entra em fillable, logOnly, glossário e na redação do tapActivity —
ou nos quatro, ou em nenhum. Sobe também o teste-contrato de paridade entre
logOnly e glossário, que não existia: campo numa lista e fora da outra some
do histórico do DEPAE sem erro nenhum."
```

---

## Task 2: Leitor de resumos do legado

**Arquivos:**
- Criar: `app/Importacao/LeitorResumosMensagens.php`
- Criar: `app/Importacao/LeitorResumosMensagensMysql.php`
- Modificar: `app/Providers/AppServiceProvider.php` (:42-49)
- Criar: `tests/Feature/Importacao/LeitorResumosMensagensBindTest.php`

**Interfaces:**
- Produz: `LeitorResumosMensagens::resumos(): array` — lista de
  `['wp_id' => int, 'resumo' => ?string]`. Consumida pela Task 3.
- Consome: nada.

> ⚠️ **Interface NOVA, não método a mais.** `LeitorMensagens` tem **2 fakes anônimos** em
> `ImportadorMensagensTest.php:25` e `ImportarMensagensCommandTest.php:24`. Acrescentar método
> lá é **erro fatal de PHP**, não falha de asserção.

- [ ] **Passo 1: escrever o teste de bind (vermelho)**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use Tests\TestCase;

class LeitorResumosMensagensBindTest extends TestCase
{
    /** Sem bind manual, a INTERFACE resolve para o ...Mysql (molde de ImportarMensagensCommandTest:44). */
    public function test_interface_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(LeitorResumosMensagensMysql::class, app(LeitorResumosMensagens::class));
    }
}
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=LeitorResumosMensagensBindTest
```

Esperado: FALHA — `Target class [App\Importacao\LeitorResumosMensagens] does not exist`.

- [ ] **Passo 3: criar a interface**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Importacao;

/**
 * Contrato PRÓPRIO (não um método a mais em LeitorMensagens): aquela interface tem 2 fakes
 * anônimos nos testes, e acrescentar método a ela é erro fatal de PHP, não falha de asserção.
 */
interface LeitorResumosMensagens
{
    /** @return array<int, array{wp_id: int, resumo: ?string}> */
    public function resumos(): array;
}
```

- [ ] **Passo 4: criar a implementação MySQL**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Importacao;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class LeitorResumosMensagensMysql implements LeitorResumosMensagens
{
    private ConnectionInterface $db;

    public function __construct()
    {
        $this->db = DB::connection('legado');
    }

    public function resumos(): array
    {
        // Medido em 21/07: 154 preenchidos (125 publish + 29 pending), zero HTML, zero
        // entidades, zero shortcodes. O prefixo wp_ é literal: select() cru não aplica o
        // 'prefix' da conexão (molde LeitorMensagensMysql:21-27).
        $posts = $this->db->select(
            "SELECT ID, post_excerpt
             FROM wp_posts
             WHERE post_type = 'mensagem-mediunicas'
               AND post_status IN ('publish', 'pending')
               AND TRIM(post_excerpt) <> ''
             ORDER BY ID"
        );

        $out = [];
        foreach ($posts as $p) {
            $out[] = [
                'wp_id' => (int) $p->ID,
                // normaliza '' => null (molde LeitorBlogMysql:68): sem isso o critério
                // "resumo vazio" do comando deixaria de ser detectável no re-run.
                'resumo' => ($p->post_excerpt !== '' && $p->post_excerpt !== null) ? $p->post_excerpt : null,
            ];
        }

        return $out;
    }
}
```

- [ ] **Passo 5: registrar o bind**

Em `app/Providers/AppServiceProvider.php`, após a linha do `LeitorDirecionadasMensagem` (:49):

```php
        $this->app->bind(LeitorResumosMensagens::class, LeitorResumosMensagensMysql::class);
```

E os dois `use` no topo, em ordem alfabética junto dos irmãos:

```php
use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
```

- [ ] **Passo 6: rodar o teste de bind**

```bash
docker compose exec -T app php artisan test --filter=LeitorResumosMensagensBindTest
```

Esperado: **1 passed**.

- [ ] **Passo 7: verificar o SQL real contra o legado**

O leitor MySQL **não tem teste unitário** (a suíte usa fake). O SQL precisa ser exercido
contra o banco real **antes do merge** — já houve um `SQL 1064` que a suíte não pegou.

```bash
docker compose exec -T app php artisan tinker --execute="\$r = app(App\Importacao\LeitorResumosMensagensMysql::class)->resumos(); echo count(\$r).' resumos; primeiro: '.json_encode(\$r[0] ?? null, JSON_UNESCAPED_UNICODE);"
```

Esperado: **154 resumos**, o primeiro com `wp_id` **21694** (medido 22/07 com o `ORDER BY ID` do
leitor; a anotação anterior dizia 21724, que é o primeiro `pending` — a medição de 21/07 rodou
**sem** `ORDER BY` e o MySQL devolveu os pendentes antes). **O gate é a contagem: 154.** Se o
túnel estiver fechado
(`Connection refused`), **abrir o túnel e repetir** — não pular este passo.

- [ ] **Passo 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Importacao/LeitorResumosMensagens.php app/Importacao/LeitorResumosMensagensMysql.php \
        app/Providers/AppServiceProvider.php tests/Feature/Importacao/LeitorResumosMensagensBindTest.php
git commit -m "feat(f4c-ac): leitor dedicado do post_excerpt no legado

Interface própria em vez de um método a mais em LeitorMensagens, que tem
dois fakes anônimos nos testes — acrescentar método lá seria erro fatal de
PHP, não falha de asserção. SQL conferido contra o legado ao vivo: 154."
```

---

## Task 3: Comando `cema:importar-resumos`

**Arquivos:**
- Criar: `app/Console/Commands/ImportarResumosMensagens.php`
- Criar: `tests/Feature/Importacao/ImportarResumosMensagensTest.php`

**Interfaces:**
- Consome: `LeitorResumosMensagens::resumos()` (Task 2); coluna `resumo` (Task 1).
- Produz: comando `cema:importar-resumos`. Nada depende dele em código.

- [ ] **Passo 1: escrever os testes (vermelho)**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportarResumosMensagensTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{wp_id: int, resumo: ?string}> $linhas */
    private function fakeLeitor(array $linhas): void
    {
        $this->app->bind(LeitorResumosMensagens::class, fn () => new class($linhas) implements LeitorResumosMensagens
        {
            public function __construct(private array $linhas) {}

            public function resumos(): array
            {
                return $this->linhas;
            }
        });
    }

    private const TEXTO = 'A mensagem responde a uma pergunta sobre a continuidade dos projetos.';

    public function test_preenche_resumo_vazio(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame(self::TEXTO, $m->fresh()->resumo);
    }

    public function test_nao_sobrescreve_resumo_ja_preenchido(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => 'Curadoria do diretor.']);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame('Curadoria do diretor.', $m->fresh()->resumo);
    }

    /** I3: uma coluna só. O comando não é um importador de mensagens. */
    public function test_nao_altera_titulo_corpo_slug_nivel_nem_status(): void
    {
        $m = Mensagem::factory()->create([
            'wp_id' => 21724, 'resumo' => null,
            'titulo' => 'Título Original', 'corpo' => '<p>Corpo original.</p>',
            'slug' => 'slug-original', 'nivel' => 'publico', 'status' => Mensagem::STATUS_PENDENTE,
        ]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $f = $m->fresh();
        $this->assertSame('Título Original', $f->titulo);
        $this->assertStringContainsString('Corpo original.', (string) $f->corpo);
        $this->assertSame('slug-original', $f->slug);
        $this->assertSame('publico', $f->nivel);
        $this->assertSame(Mensagem::STATUS_PENDENTE, $f->status);
    }

    /** I2: o histórico do DEPAE não pode encher de "mensagem atualizada" por causa do backfill. */
    public function test_nao_gera_linha_em_activity_log(): void
    {
        Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $antes = DB::table('activity_log')->count();
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame($antes, DB::table('activity_log')->count());
    }

    /** Discriminante do teste acima: sem withoutLogs, um update DESTES gera linha. */
    public function test_guarda_do_discriminante_update_normal_gera_linha(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21724, 'resumo' => null]);
        $antes = DB::table('activity_log')->count();

        $m->update(['resumo' => self::TEXTO]);

        $this->assertGreaterThan($antes, DB::table('activity_log')->count());
    }

    /** I4: excerpt órfão não cria mensagem — por isso firstWhere, nunca firstOrNew. */
    public function test_ignora_excerpt_sem_mensagem_no_banco(): void
    {
        $this->fakeLeitor([['wp_id' => 99999, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertSame(0, Mensagem::count(), 'excerpt órfão não pode criar mensagem');
    }

    /** I4: a mensagem nascida no site (1 das 180) fica de fora por construção. */
    public function test_nao_toca_mensagem_sem_wp_id(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => null, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21724, 'resumo' => self::TEXTO]]);

        $this->artisan('cema:importar-resumos')->assertSuccessful();

        $this->assertNull($m->fresh()->resumo);
    }

    /** I5: os 3 lixos do legado (".", ".", "......") não podem virar meta description. */
    public function test_descarta_excerpt_curto_e_o_lista(): void
    {
        $m = Mensagem::factory()->create(['wp_id' => 21762, 'resumo' => null]);
        $this->fakeLeitor([['wp_id' => 21762, 'resumo' => '.']]);

        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('21762')
            ->assertSuccessful();

        $this->assertNull($m->fresh()->resumo, 'o lixo foi gravado');
    }

    /** §8-7: com o leitor REAL e sem túnel, aborta limpo (FAILURE + instrução), sem stack trace. */
    public function test_sem_tunel_o_comando_falha_com_instrucao(): void
    {
        $this->app->bind(LeitorResumosMensagens::class, LeitorResumosMensagensMysql::class);
        config(['database.connections.legado.host' => '127.0.0.1', 'database.connections.legado.port' => 1]);
        DB::purge('legado');

        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('O túnel SSH está ativo?')
            ->assertFailed();
    }

    /** I5b: contadores mutuamente exclusivos — a soma fecha com o total lido. */
    public function test_contadores_sao_mutuamente_exclusivos(): void
    {
        Mensagem::factory()->create(['wp_id' => 1, 'resumo' => null]);              // atualizada
        Mensagem::factory()->create(['wp_id' => 2, 'resumo' => 'Já tenho resumo.']); // ja_tinha
        $this->fakeLeitor([
            ['wp_id' => 1, 'resumo' => self::TEXTO],
            ['wp_id' => 2, 'resumo' => self::TEXTO],
            ['wp_id' => 3, 'resumo' => self::TEXTO],   // sem_mensagem
            ['wp_id' => 4, 'resumo' => '...'],         // curta
        ]);

        // ⚠️ UMA asserção com a linha INTEIRA, não 4 substrings. Cada expectsOutputToContain
        // vira uma expectativa `doWrite`+`withArgs` (PendingCommand.php:614-621); quando várias
        // casam com a MESMA chamada — e os 4 contadores saem numa linha só — o Mockery consome
        // apenas a primeira, e as outras nunca esvaziam `expectedOutputSubstrings`. Com a linha
        // completa é 1 expectativa para 1 chamada, e ainda prova mais: o formato exato.
        $this->artisan('cema:importar-resumos')
            ->expectsOutputToContain('Atualizadas: 1 · Já tinham resumo: 1 · Sem mensagem no banco: 1 · Descartadas por serem curtas: 1')
            ->assertSuccessful();
    }
}
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=ImportarResumosMensagensTest
```

Esperado: FALHA — `The command "cema:importar-resumos" does not exist.` (exceto
`test_guarda_do_discriminante_update_normal_gera_linha`, que já passa).

- [ ] **Passo 3: criar o comando**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Console\Commands;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use App\Models\Mensagem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportarResumosMensagens extends Command
{
    /** Piso medido: o legado tem 3 excerpts que são só pontuação (".", ".", "......"). */
    private const MINIMO_CARACTERES = 20;

    protected $signature = 'cema:importar-resumos';

    protected $description = 'Importa o post_excerpt das mensagens do WordPress legado para a coluna resumo (só preenche o que está vazio, SELECT-only, idempotente).';

    public function handle(LeitorResumosMensagens $leitor): int
    {
        // Só exige a conexão legado com o leitor real (o teste injeta fake) — molde ImportarMensagens:21-32.
        if ($leitor instanceof LeitorResumosMensagensMysql) {
            try {
                DB::connection('legado')->getPdo();
            } catch (Throwable $e) {
                $this->error('Não foi possível conectar ao banco legado. O túnel SSH está ativo?');
                $this->line('Abra com: ssh -N -L 3307:127.0.0.1:3306 deploy@SEU_VPS');
                $this->line('Detalhe: '.$e->getMessage());

                return self::FAILURE;
            }
        }

        $linhas = $leitor->resumos();
        $contadores = ['atualizadas' => 0, 'ja_tinha' => 0, 'sem_mensagem' => 0, 'curtas' => 0];
        $descartadas = [];

        // UM envelope para o laço inteiro: withoutLogs recebe Closure, tem finally que
        // re-habilita e guarda de reentrância; o ActivityLogStatus é scoped. Sem isto, cada
        // linha vira "mensagem atualizada" no histórico que o diretor do DEPAE lê na curadoria.
        activity()->withoutLogs(function () use ($linhas, &$contadores, &$descartadas): void {
            foreach ($linhas as $linha) {
                $texto = trim((string) ($linha['resumo'] ?? ''));

                // Os quatro contadores são mutuamente exclusivos (os `continue` garantem isso):
                // curtas + sem_mensagem + ja_tinha + atualizadas == total lido.
                if (mb_strlen($texto) < self::MINIMO_CARACTERES) {
                    $contadores['curtas']++;
                    $descartadas[] = "wp_id {$linha['wp_id']}: \"{$texto}\"";

                    continue;
                }

                // firstWhere, NUNCA firstOrNew/updateOrCreate: excerpt órfão não cria mensagem.
                $mensagem = Mensagem::firstWhere('wp_id', $linha['wp_id']);

                if ($mensagem === null) {
                    $contadores['sem_mensagem']++;

                    continue;
                }

                // blank() cobre null E '' — o critério "vazio" é estável entre execuções.
                if (! blank($mensagem->resumo)) {
                    $contadores['ja_tinha']++;

                    continue;
                }

                $mensagem->resumo = $texto;
                $mensagem->save();
                $contadores['atualizadas']++;
            }
        });

        $this->newLine();
        $this->info('Importação de resumos concluída.');
        $this->line("  Atualizadas: {$contadores['atualizadas']} · Já tinham resumo: {$contadores['ja_tinha']} · Sem mensagem no banco: {$contadores['sem_mensagem']} · Descartadas por serem curtas: {$contadores['curtas']}");

        if ($descartadas !== []) {
            $this->warn('  Descartadas (confira se alguma é sinopse legítima):');
            foreach ($descartadas as $item) {
                $this->line('    - '.$item);
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Passo 4: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter=ImportarResumosMensagensTest
```

Esperado: **10 passed**.

- [ ] **Passo 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Console/Commands/ImportarResumosMensagens.php tests/Feature/Importacao/ImportarResumosMensagensTest.php
git commit -m "feat(f4c-ac): comando cema:importar-resumos

Uma coluna só, casada por wp_id, dentro de um único activity()->withoutLogs
— sem isso o backfill de 151 linhas despeja 'mensagem atualizada' no
histórico que o DEPAE lê. Os quatro contadores são mutuamente exclusivos
pelos continue: sem eles um excerpt de 1 caractere seria contado como curto
E gravado, e uma mensagem inexistente estouraria no acesso à propriedade."
```

---

## Task 4: O resumo no front — card, meta description e lead

**Arquivos:**
- Modificar: `resources/views/components/mensagem/card.blade.php:14`
- Modificar: `resources/views/mensagens/show.blade.php:7` e :137-144 (o lead)
- Modificar: `tests/Feature/Front/MensagemShowTest.php` (testes novos)

**Interfaces:**
- Consome: coluna `resumo` (Task 1).
- Produz: nada consumido por outra task.

- [ ] **Passo 1: escrever os testes (vermelho)**

> ⚠️ **As rotas públicas não são `/mensagens/…`.** São `mensagens.index` =
> `/mensagens-mediunicas`, `mensagens.show` = `/mensagens-mediunicas/{slug}` e `autores.show` =
> `/autores-espirituais/{slug}` ([routes/web.php:104-106,114-116](routes/web.php#L104-L116)).
> Qualquer outra cai no `Route::fallback` (`:146-152`) e dá **404** — o que deixaria o teste da
> barreira **verde por vacuidade**. Usar sempre o helper `route()`, como o resto do arquivo.
>
> **Import obrigatório antes de colar:** acrescentar `use App\Enums\VisibilidadeMensagem;` aos
> imports de `MensagemShowTest.php` (:5-11).

Acrescentar a `tests/Feature/Front/MensagemShowTest.php`:

```php
    /**
     * I9: a asserção é sobre a TAG. O `contexto` também é renderizado no corpo da página
     * (show.blade.php:85-92) e o resumo vira lead — `assertSee`/`assertDontSee` soltos não
     * distinguiriam a meta description de nada.
     */
    public function test_meta_description_usa_o_resumo_quando_existe(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'com-resumo',
            'resumo' => 'Radian convida os trabalhadores a refletirem sobre a palavra.',
            'contexto' => 'Contexto de reserva do single.',
            'corpo' => '<p>Corpo que nao deve aparecer.</p>',
        ]);

        $this->get(route('mensagens.show', 'com-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Radian convida os trabalhadores a refletirem sobre a palavra."', false)
            ->assertDontSee('name="description" content="Contexto de reserva do single."', false);
    }

    /** GUARDA (não-regressão, já verde): a reserva `contexto` não pode sumir ao entrar o resumo. */
    public function test_meta_description_cai_no_contexto_sem_resumo(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'sem-resumo', 'resumo' => null,
            'contexto' => 'Contexto de reserva.', 'corpo' => '<p>Corpo.</p>',
        ]);

        $this->get(route('mensagens.show', 'sem-resumo'))
            ->assertOk()
            ->assertSee('name="description" content="Contexto de reserva."', false);
    }

    /** D7: o lead é editorial e visualmente distinto da prosa mediúnica. */
    public function test_lead_do_resumo_aparece_no_single(): void
    {
        Mensagem::factory()->publica()->create([
            'slug' => 'lead-visivel', 'resumo' => "Primeiro parágrafo.\nSegundo parágrafo.",
        ]);

        $this->get(route('mensagens.show', 'lead-visivel'))
            ->assertOk()
            ->assertSee('cema-msg-resumo', false)
            ->assertSee('Primeiro parágrafo.<br />', false);   // nl2br honra os 12 com parágrafos
    }

    public function test_lead_nao_aparece_sem_resumo(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'sem-lead', 'resumo' => null]);

        $this->get(route('mensagens.show', 'sem-lead'))->assertOk()->assertDontSee('cema-msg-resumo', false);
    }

    /** I10 / REGRESSÃO (T10): a barreira intercepta ANTES do render — o resumo restrito não vaza. */
    public function test_barreira_continua_interceptando_o_restrito_depois_do_resumo(): void
    {
        Mensagem::factory()->comNivel(VisibilidadeMensagem::Trabalhadores)->create([
            'slug' => 'restrita-com-resumo',
            'status' => Mensagem::STATUS_PUBLICADO,
            'resumo' => 'Resumo reservado que o anônimo não pode ler.',
        ]);

        $this->get(route('mensagens.show', 'restrita-com-resumo'))
            ->assertOk()   // barreira-200 CEGA da 3B — sem o assertOk, um 404 deixaria isto verde
            ->assertDontSee('Resumo reservado', false);
    }
```

E em `tests/Feature/Front/AutorShowTest.php` (ou o arquivo que exercita o card variante
`perfil` — usar o mesmo que já testa o trecho do corpo):

```php
    /** I8: o card prefere o resumo e cai no corpo quando não há. */
    public function test_card_usa_o_resumo_e_cai_no_corpo_sem_ele(): void
    {
        $autor = AutorEspiritual::factory()->create(['slug' => 'radian', 'ativo' => true]);

        $comResumo = Mensagem::factory()->publica()->create([
            'titulo' => 'Com resumo', 'resumo' => 'Trecho editorial do card.',
            'corpo' => '<p>Corpo que nao deve aparecer no card.</p>',
        ]);
        $semResumo = Mensagem::factory()->publica()->create([
            'titulo' => 'Sem resumo', 'resumo' => null, 'corpo' => '<p>Corpo de reserva.</p>',
        ]);
        $comResumo->autores()->attach($autor);
        $semResumo->autores()->attach($autor);

        $this->get(route('autores.show', 'radian'))
            ->assertOk()
            ->assertSee('Trecho editorial do card.')
            ->assertDontSee('Corpo que nao deve aparecer no card.')
            ->assertSee('Corpo de reserva.');
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter="MensagemShowTest|AutorShowTest"
```

Esperado: **3 FALHAM** — `test_meta_description_usa_o_resumo_quando_existe`,
`test_lead_do_resumo_aparece_no_single` e `test_card_usa_o_resumo_e_cai_no_corpo_sem_ele`. Os
outros 3 (`test_meta_description_cai_no_contexto_sem_resumo`, `test_lead_nao_aparece_sem_resumo`
e o da barreira) **já passam**: são guardas de não-regressão, não alvos.

- [ ] **Passo 3: card — o trecho passa a preferir o resumo**

`resources/views/components/mensagem/card.blade.php`, linha 14:

```blade
    $trecho = \Illuminate\Support\Str::limit(strip_tags((string) ($mensagem->resumo ?: $mensagem->corpo)), 160);
```

- [ ] **Passo 4: single — meta description**

`resources/views/mensagens/show.blade.php`, linha 7:

```blade
              :description="\Illuminate\Support\Str::limit(strip_tags($mensagem->resumo ?: $mensagem->contexto ?: $mensagem->corpo), 155)">
```

- [ ] **Passo 5: single — o lead**

Em `resources/views/mensagens/show.blade.php`, **dentro** do `<div class="mx-auto max-w-[640px]">`
(linha 138) e **antes** do `@switch` (linha 139):

```blade
                                @if (filled($mensagem->resumo))
                                    {{-- Lead editorial (D7): é texto da CURADORIA, não palavra do
                                         espírito — por isso a barra dourada e a tipografia menor o
                                         separam da prosa. e() antes de nl2br: escapa e só então
                                         converte as quebras dos 12 resumos com parágrafo. --}}
                                    <div class="cema-msg-resumo mb-8 border-l-[3px] border-gold bg-cream/60 px-5 py-4">
                                        <p class="font-serif text-[14.5px] font-light leading-[1.75] text-[#5b576b]">{!! nl2br(e($mensagem->resumo)) !!}</p>
                                    </div>
                                @endif

                                @switch($mensagem->formato)
```

- [ ] **Passo 6: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter="MensagemShowTest|AutorShowTest"
```

Esperado: todos verdes.

- [ ] **Passo 7: build e conferência no navegador**

```bash
npm run build          # NO HOST — obrigatório: as utilitárias `border-l-[3px]` e
                       # `leading-[1.75]` do lead não existem em Blade nenhum hoje,
                       # e o Tailwind v4 só as emite no build
docker compose restart app worker
```

Abrir uma mensagem pública em `localhost:8000/mensagens-mediunicas/<slug>` — sem resumo ainda no
dev, o lead não deve aparecer e nada deve ter mudado visualmente.

- [ ] **Passo 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/components/mensagem/card.blade.php resources/views/mensagens/show.blade.php \
        tests/Feature/Front/MensagemShowTest.php tests/Feature/Front/AutorShowTest.php
git commit -m "feat(f4c-ac): o resumo no card, na meta description e como lead do single

O lead fica visualmente separado da prosa porque é texto da curadoria, não
palavra do espírito. Sobe junto o teste de regressão da barreira: a meta
description passou a ler um campo novo e não pode vazar mensagem restrita
ao anônimo."
```

---

## Task 5: Textarea de resumo no admin e na curadoria

**Arquivos:**
- Modificar: `app/Filament/Schemas/MensagemForm.php` (`schemaAdmin` :59-63 e `schemaCuradoria`)
- Modificar: `tests/Feature/Filament/MensagemResourceTest.php`

**Interfaces:**
- Consome: `$fillable` com `resumo` (Task 1).
- Produz: campo `resumo` nos schemas `admin` e `curadoria`.

- [ ] **Passo 1: escrever os testes (vermelho)**

Em `tests/Feature/Filament/MensagemResourceTest.php`:

```php
    public function test_form_do_admin_tem_o_campo_resumo(): void
    {
        Livewire::test(CreateMensagem::class)
            ->assertFormFieldExists('resumo', fn (Textarea $f) => true);
    }
```

E, em `tests/Feature/Conta/MensagensContaCriarTest.php`, acrescentar **uma linha** à cadeia de
asserções que já existe no teste do formulário do médium (junto de
`->assertFormFieldDoesNotExist('nivel')`, linha 250):

```php
            ->assertFormFieldDoesNotExist('resumo')   // I11: texto editorial da curadoria; o médium tem o `contexto`
```

E, em `tests/Feature/Conta/CuradoriaContaTest.php` — é lá, e não em `CuradoriaPublicarTest`,
que vivem as asserções de campo do `schemaCuradoria` (`:201-213`); o helper `diretorDepae()`
está em `:39`:

```php
    /** I11: o resumo é texto de quem CURA — existe no schemaCuradoria (e não no form do médium). */
    public function test_i11_form_da_curadoria_tem_o_campo_resumo(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists('resumo');
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter="MensagemResourceTest|MensagensContaCriarTest"
```

Esperado: o teste do admin FALHA (campo inexistente); o do médium **passa** (ainda não existe
em lugar nenhum) — é a guarda que impede o campo de vazar para lá no passo seguinte.

- [ ] **Passo 3: `schemaAdmin` — Textarea após o `contexto`**

Em `app/Filament/Schemas/MensagemForm.php`, dentro de `schemaAdmin()`, logo após o
`Textarea::make('contexto')` (:59-63):

```php
                    Textarea::make('resumo')
                        ->label('Resumo (texto editorial)')
                        ->helperText('Aparece no card, na busca do Google e como abertura da página. Importado do site antigo quando havia. Opcional.')
                        ->rows(4)
                        ->maxLength(1500)
                        ->columnSpan(2),
```

- [ ] **Passo 4: `schemaCuradoria` — o mesmo campo**

Em `schemaCuradoria()` (a partir de `:276`), logo após o `Textarea::make('contexto')` da linha
**288** — mesmo bloco de código do passo 3, literal.

🚫 **Não** acrescentar ao `schemaMedium()` (`:200`), cujo `Textarea::make('contexto')` está na
linha **212**: o resumo é texto de quem cura.

- [ ] **Passo 5: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter="MensagemResourceTest|MensagensContaCriarTest|MensagensContaEditarTest|CuradoriaContaTest"
```

Esperado: todos verdes — o campo existe no admin e na curadoria, e continua ausente no médium.

- [ ] **Passo 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Schemas/MensagemForm.php tests/Feature/Filament/MensagemResourceTest.php \
        tests/Feature/Conta/MensagensContaCriarTest.php tests/Feature/Conta/CuradoriaContaTest.php
git commit -m "feat(f4c-ac): campo resumo no admin e na curadoria, fora do formulário do médium

O resumo é texto editorial de quem cura; o médium já tem o contexto para
dizer de onde veio a mensagem."
```

---

## Task 6: Renomear a coleção `pictografia` → `imagens`

**Arquivos:**
- Criar: `database/migrations/2026_07_22_000002_renomeia_colecao_pictografia_para_imagens.php`
- Modificar: `app/Models/Mensagem.php:43,228` · `app/Importacao/ImportadorMensagens.php:88,93` ·
  `app/Filament/Resources/Mensagens/MensagemResource.php:100,102` ·
  `app/Filament/Schemas/MensagemForm.php:149,151,152 / 253,255,256 / 348,350,351` ·
  `resources/views/mensagens/corpos/pictografia.blade.php:5` ·
  `resources/views/mensagens/show.blade.php:3` ·
  `resources/views/components/mensagem/card.blade.php:13`
- Modificar (testes, **15 pontos + 2**): `ImportadorMensagensTest:156,165,166,170,199` ·
  `MensagemTest:135,136,139` · `MensagemShowTest:125,127` · `MensagemSeoTest:56,59` ·
  `MensagensContaEditarTest:68,83` · `MensagemResourceTest:63` · `MensagensContaCriarTest:262`

**Interfaces:**
- Produz: `Mensagem::COLECAO_IMAGENS = 'imagens'`. Consumida pelas Tasks 7 e 8.
- Consome: nada.

> ⚠️ **Três sentidos de "pictografia".** Só a **coleção** e o **nome do campo** mudam. O
> **formato** (`FormatoMensagem::Pictografia`, valor `'pictografia'`) **NÃO muda** — nem em
> `ResumoAutor.php:24`, onde é chave da paleta de cor: renomear ali faz `ResumoAutor.php:63`
> cair no fallback roxo, **sem teste que pegue**.

- [ ] **Passo 1: escrever o teste da migration de dados (vermelho)**

Em `tests/Feature/Models/MensagemTest.php`:

```php
    public function test_colecao_de_imagens_se_chama_imagens(): void
    {
        Storage::fake('public');
        $this->assertSame('imagens', Mensagem::COLECAO_IMAGENS);

        $m = Mensagem::factory()->create();
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('a.png')
          ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        $this->assertSame('imagens', $m->fresh()->getMedia(Mensagem::COLECAO_IMAGENS)->first()->collection_name);
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=test_colecao_de_imagens_se_chama_imagens
```

Esperado: FALHA — `Undefined constant Mensagem::COLECAO_IMAGENS`.

- [ ] **Passo 3: criar a migration de dados**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

use App\Models\Mensagem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O rótulo dizia "Imagens (pictografia)" mas o front só renderizava no formato Pictografia —
 * imagem em psicografia sumia sem aviso. Ao corrigir isso, o nome técnico da coleção também
 * deixa de mentir. Os arquivos NÃO se movem: o path da medialibrary usa o id da media, e as
 * conversões (web/thumb) já geradas continuam válidas. 4 linhas no dev.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('media')
            ->where('model_type', Mensagem::class)   // FQCN literal: não há morph map no projeto
            ->where('collection_name', 'pictografia')
            ->update(['collection_name' => 'imagens']);
    }

    public function down(): void
    {
        DB::table('media')
            ->where('model_type', Mensagem::class)
            ->where('collection_name', 'imagens')
            ->update(['collection_name' => 'pictografia']);
    }
};
```

- [ ] **Passo 4: renomear a constante no model**

`app/Models/Mensagem.php:43`:

```php
    /** Imagens da mensagem — vale para os 3 formatos (na pictografia os desenhos SÃO a mensagem). */
    public const COLECAO_IMAGENS = 'imagens';
```

E `:228`:

```php
        // MÚLTIPLAS imagens (o legado tem mensagem com 2). WebP web + miniatura pelo trait.
        $this->registrarColecaoImagem(self::COLECAO_IMAGENS, unica: false);
```

- [ ] **Passo 5: trocar os 8 call sites de produção da constante**

Substituir `COLECAO_PICTOGRAFIA` por `COLECAO_IMAGENS` em: `ImportadorMensagens.php:88,93` ·
`MensagemResource.php:102` · `MensagemForm.php:151,255,350` · `pictografia.blade.php:5` ·
`show.blade.php:3`.

- [ ] **Passo 6: card — string crua vira constante**

`resources/views/components/mensagem/card.blade.php:13` — era o **único** ponto do front que
usava a string:

```blade
        ? $mensagem->getFirstMediaUrl(\App\Models\Mensagem::COLECAO_IMAGENS, 'web') : '';
```

- [ ] **Passo 7: rótulos e nome do campo**

Nos **três** schemas de `MensagemForm.php` (:149-152, :253-256, :348-351):

```php
            Section::make('Imagens')
                ->schema([
                    ComponentesImagem::upload('imagens', Mensagem::COLECAO_IMAGENS, multiplas: true)
                        ->label('Imagens da mensagem'),
                ]),
```

E em `MensagemResource.php:100`:

```php
                SpatieMediaLibraryImageColumn::make('imagens')
                    ->label('Imagens')
```

- [ ] **Passo 8: corrigir os 17 pontos nos testes**

Trocar `COLECAO_PICTOGRAFIA` → `COLECAO_IMAGENS` nos 15 pontos listados em **Arquivos**, e o
nome do campo nos 2: `MensagemResourceTest:63` e `MensagensContaCriarTest:262` passam a
`assertFormFieldExists('imagens', …)`.

- [ ] **Passo 9: rodar a migration e a suíte dos afetados**

```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan test --filter="MensagemTest|MensagemShowTest|MensagemSeoTest|ImportadorMensagensTest|MensagensContaEditarTest|MensagemResourceTest|MensagensContaCriarTest"
```

Esperado: todos verdes.

- [ ] **Passo 10: rodar os dois greps do I16 (allowlist fechada)**

```bash
grep -rn "COLECAO_PICTOGRAFIA" app/ resources/ tests/ ; echo "--- esperado: 0 linhas ---"
grep -rnE "['\"]pictografia['\"]" app/ resources/ ; echo "--- esperado: EXATAMENTE 2 ---"
```

Esperado no segundo: **só** `app/Enums/FormatoMensagem.php:11` e
`app/Support/AutoresEspirituais/ResumoAutor.php:24`. Se aparecer um terceiro, **parar** e
verificar se não é o formato sendo renomeado por engano.

- [ ] **Passo 11: conferir os dados no dev**

```bash
docker compose exec -T app php artisan tinker --execute="echo App\Models\Mensagem::find(93)?->getMedia('imagens')->count().' imagens na msg 93';"
```

Esperado: a contagem que existia antes (não zero).

- [ ] **Passo 12: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add database/migrations/2026_07_22_000002_renomeia_colecao_pictografia_para_imagens.php \
        app/Models/Mensagem.php app/Importacao/ImportadorMensagens.php \
        app/Filament/Resources/Mensagens/MensagemResource.php app/Filament/Schemas/MensagemForm.php \
        resources/views/mensagens/corpos/pictografia.blade.php resources/views/mensagens/show.blade.php \
        resources/views/components/mensagem/card.blade.php \
        tests/Feature/Importacao/ImportadorMensagensTest.php tests/Feature/Models/MensagemTest.php \
        tests/Feature/Front/MensagemShowTest.php tests/Feature/Front/MensagemSeoTest.php \
        tests/Feature/Conta/MensagensContaEditarTest.php tests/Feature/Conta/MensagensContaCriarTest.php \
        tests/Feature/Filament/MensagemResourceTest.php
git commit -m "refactor(f4c-ac): coleção de mídia da mensagem passa a se chamar imagens

O nome técnico dizia pictografia e o rótulo dizia 'Imagens (pictografia)' —
os dois mentiam, porque a coleção sempre valeu para qualquer formato. A
migration move 4 linhas em media.collection_name; os arquivos não se movem,
porque o path usa o id da media. O FORMATO Pictografia continua intacto,
inclusive como chave da paleta em ResumoAutor."
```

---

## Task 7: Componente de imagens único para os 3 formatos

**Arquivos:**
- Criar: `resources/views/components/mensagem/imagens.blade.php`
- Modificar: `resources/views/mensagens/corpos/pictografia.blade.php` (reescrita da cadeia)
- Modificar: `resources/views/mensagens/corpos/psicografia.blade.php` (insere a galeria)
- Modificar: `tests/Feature/Front/MensagemShowTest.php`

**Interfaces:**
- Consome: `Mensagem::COLECAO_IMAGENS` (Task 6).
- Produz: `<x-mensagem.imagens :mensagem="$m" legenda="Desenho|Imagem" />`.

> A **psicofonia sai de graça**: `psicofonia.blade.php:15` já faz
> `@include('mensagens.corpos.psicografia')`. **Zero linha nova** nesse arquivo — e pôr um
> bloco lá também duplicaria a galeria.

- [ ] **Passo 1: escrever os testes (vermelho)**

Em `tests/Feature/Front/MensagemShowTest.php`:

```php
    /** @return Mensagem mensagem pública do formato dado, com 1 imagem na coleção */
    private function mensagemComImagem(string $formato, string $slug): Mensagem
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create(['slug' => $slug, 'formato' => $formato, 'titulo' => 'Mensagem Ilustrada']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('img.png')
          ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        return $m->fresh();
    }

    /** I12: hoje a imagem some do site quando o formato não é Pictografia. */
    public function test_imagem_em_psicografia_aparece_no_corpo(): void
    {
        $m = $this->mensagemComImagem('psicografia', 'psico-com-imagem');

        $this->get(route('mensagens.show', 'psico-com-imagem'))
            ->assertOk()
            ->assertSee($m->getMedia(Mensagem::COLECAO_IMAGENS)->first()->getUrl('web'), false)
            ->assertSee('Imagem 1')
            ->assertSee('Baixar')
            ->assertSee('— imagem 1', false)        // I28: o alt segue a legenda (A11y)
            ->assertDontSee('— desenho 1', false);  // hoje o alt é hardcoded "desenho"
    }

    /** I13: a psicofonia inclui a psicografia — a galeria não pode sair em dobro. */
    public function test_psicofonia_mostra_a_galeria_uma_unica_vez(): void
    {
        $this->mensagemComImagem('psicofonia', 'psicofonia-com-imagem');

        $html = $this->get(route('mensagens.show', 'psicofonia-com-imagem'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Imagem 1'), 'a galeria duplicou pelo @include');
    }

    /** I28: na pictografia os desenhos SÃO a mensagem — a legenda diz isso. */
    public function test_pictografia_mantem_a_legenda_desenho(): void
    {
        $this->mensagemComImagem('pictografia', 'pict-legenda');

        $this->get(route('mensagens.show', 'pict-legenda'))
            ->assertOk()
            ->assertSee('Desenho 1')
            ->assertDontSee('Imagem 1')
            ->assertSee('— desenho 1', false);   // I28: o alt acompanha
    }

    /** I14: o texto de vazio é da pictografia e não pode vazar. Exige corpo NULL (senão passa por vacuidade). */
    public function test_estado_vazio_da_pictografia_nao_vaza_para_psicografia(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'psico-sem-nada', 'formato' => 'psicografia', 'corpo' => null]);

        $this->get(route('mensagens.show', 'psico-sem-nada'))
            ->assertOk()
            ->assertDontSee('ainda não tem desenhos disponíveis');
    }

    public function test_estado_vazio_continua_na_pictografia_sem_corpo_e_sem_imagem(): void
    {
        Mensagem::factory()->publica()->create(['slug' => 'pict-vazia', 'formato' => 'pictografia', 'corpo' => null]);

        $this->get(route('mensagens.show', 'pict-vazia'))->assertOk()->assertSee('ainda não tem desenhos disponíveis');
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=MensagemShowTest
```

Esperado: **2 FALHAM** — `test_imagem_em_psicografia_aparece_no_corpo` e
`test_psicofonia_mostra_a_galeria_uma_unica_vez`. Os outros 3 já passam e são guardas de
não-regressão (a legenda da pictografia, o estado vazio que não vaza, o estado vazio que fica).

- [ ] **Passo 3: criar o componente**

`resources/views/components/mensagem/imagens.blade.php` — o miolo das linhas 12-31 da
pictografia, **com os artefatos literais preservados**:

```blade
@props(['mensagem', 'legenda' => 'Imagem'])

{{-- Galeria das imagens LOCAIS da MediaLibrary (coleção imagens, WebP web), compartilhada
     pelos 3 formatos. Na pictografia os desenhos SÃO a mensagem (legenda "Desenho"); nos
     demais são ilustração ("Imagem"). Download por item apontando ao ORIGINAL (getUrl() sem
     conversão), nome amigável derivado do título. NÃO usa link_arquivo (anexo Drive da sidebar). --}}
@php $imagens = $mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS); @endphp

@if ($imagens->isNotEmpty())
    {{-- $attributes->class(): sem isto, o `class="mt-8"` que a psicografia passa é descartado
         em silêncio (molde de components/mensagem/card.blade.php:16). --}}
    <div {{ $attributes->class(['cema-pictografia-grid']) }}>
        @foreach ($imagens as $i => $img)
            <figure class="flex flex-col overflow-hidden rounded-[14px] border border-border-muted bg-[#FAFAFB]">
                <div class="aspect-[4/3] overflow-hidden bg-cream">
                    <img src="{{ $img->getUrl('web') }}" loading="lazy" decoding="async"
                         alt="{{ $mensagem->titulo }} — {{ mb_strtolower($legenda) }} {{ $i + 1 }}"
                         class="size-full object-cover">
                </div>
                <figcaption class="flex items-center justify-between gap-3 px-4 py-3">
                    <span class="font-mono text-[11px] uppercase tracking-[0.06em] text-text-muted">{{ $legenda }} {{ $i + 1 }}</span>
                    <a href="{{ $img->getUrl() }}"
                       download="{{ \Illuminate\Support\Str::slug($mensagem->titulo) }}-{{ $i + 1 }}.{{ $img->extension ?: 'jpg' }}"
                       class="inline-flex items-center gap-1.5 rounded-pill bg-cream px-3 py-1.5 text-[12px] font-medium text-primary transition hover:bg-primary hover:text-white">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Baixar
                    </a>
                </figcaption>
            </figure>
        @endforeach
    </div>
@endif
```

> A classe `.cema-pictografia-grid` é **autoral** (`resources/css/mensagens.css:63`) e **não
> muda**: o alinhamento de vocabulário desta fatia é de domínio, não de CSS. Renomear só no
> Blade faria a galeria perder o grid nos 3 formatos, **sem erro**.

- [ ] **Passo 4: reescrever a pictografia em dois blocos independentes**

A cadeia atual é `@if`(11) … `</div>`(31) … `@elseif`(32-33) … `@endif`(34) — **não é um
recorte balanceado**. Substituir das linhas 11 a 34 por:

```blade
<x-mensagem.imagens :mensagem="$mensagem" legenda="Desenho" />

@if ($mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS)->isEmpty() && blank($mensagem->corpo))
    <p class="font-serif text-[15px] italic text-text-muted">Esta comunicação pictográfica ainda não tem desenhos disponíveis.</p>
@endif
```

E remover o `@php $desenhos = … @endphp` da linha 5 (agora vive dentro do componente).

- [ ] **Passo 5: psicografia — galeria entre o corpo e a assinatura**

Em `resources/views/mensagens/corpos/psicografia.blade.php`, entre o `<div class="cema-msg-prose">`
(linha 6) e o bloco da assinatura (linha 8):

```blade
<div class="cema-msg-prose">{!! $mensagem->corpo !!}</div>

{{-- Ilustrações da mensagem (I12). A psicofonia herda este bloco pelo @include — não
     acrescentar galeria lá, sob pena de sair em dobro. --}}
<x-mensagem.imagens :mensagem="$mensagem" legenda="Imagem" class="mt-8" />

<div class="mt-9 flex flex-col items-end gap-1 border-t border-[#F0EEF4] pt-6 text-right">
```

- [ ] **Passo 6: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter=MensagemShowTest
```

Esperado: todos verdes, incluindo os artefatos literais (`getUrl('web')`, `download`, "Baixar").

- [ ] **Passo 7: conferência visual**

```bash
# npm run build NÃO é preciso aqui: o componente é cópia literal das classes de
# pictografia.blade.php:12-31, e .cema-pictografia-grid é autoral em mensagens.css:63
docker compose restart app worker
```

Subir uma imagem à mão numa psicografia pública pelo `/admin` e abrir a página. **Abrir uma
psicografia existente não prova nada** — hoje só 3 mensagens têm mídia, e as 3 são
pictografia.

- [ ] **Passo 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/components/mensagem/imagens.blade.php resources/views/mensagens/corpos/ \
        tests/Feature/Front/MensagemShowTest.php
git commit -m "feat(f4c-ac): imagem da mensagem passa a aparecer nos 3 formatos

Um componente só, consumido pela pictografia e pela psicografia; a
psicofonia herda pelo @include que já existia. O estado vazio fica fora do
componente, na pictografia, senão 'ainda não tem desenhos' vazaria para
psicografia sem imagem. A legenda governa o rodapé e o alt."
```

---

## Task 8: Card mostra imagem em qualquer formato

**Arquivos:**
- Modificar: `resources/views/components/mensagem/card.blade.php:11-13`
- Modificar: `tests/Feature/Front/AutorShowTest.php` (ou o teste da variante `perfil`)

**Interfaces:**
- Consome: `Mensagem::COLECAO_IMAGENS` (Task 6).

- [ ] **Passo 1: escrever os testes (vermelho)**

> **Antes de colar**, em `tests/Feature/Front/AutorShowTest.php`: acrescentar
> `use Illuminate\Support\Facades\Storage;` aos imports e, dentro da classe logo após
> `use RefreshDatabase;`, a constante (cópia literal de `MensagemShowTest.php:18`):
>
> ```php
>     /** PNG 1x1 mínimo (evita GD real sob carga — flaky conhecido do blog). */
>     private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAC0lEQVQImWNgAAIAAAUAAWJVMogAAAAASUVORK5CYII=';
> ```

```php
    /** I12: o gate de FORMATO cai; o de VARIANTE fica. */
    public function test_card_do_perfil_mostra_imagem_de_psicografia(): void
    {
        Storage::fake('public');
        $autor = AutorEspiritual::factory()->create(['slug' => 'radian', 'ativo' => true]);
        $m = Mensagem::factory()->publica()->create(['formato' => 'psicografia', 'titulo' => 'Ilustrada']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('c.png')
          ->toMediaCollection(Mensagem::COLECAO_IMAGENS);
        $m->autores()->attach($autor);

        $this->get(route('autores.show', 'radian'))
            ->assertOk()
            ->assertSee($m->fresh()->getFirstMediaUrl(Mensagem::COLECAO_IMAGENS, 'web'), false);
    }

    /** I15/D11: a lista pública segue sem miniatura — decisão de design da 2B. */
    public function test_lista_publica_continua_sem_miniatura(): void
    {
        Storage::fake('public');
        $m = Mensagem::factory()->publica()->create(['formato' => 'pictografia', 'slug' => 'na-lista']);
        $m->addMediaFromString(base64_decode(self::PNG_1X1))->usingFileName('d.png')
          ->toMediaCollection(Mensagem::COLECAO_IMAGENS);

        $this->get(route('mensagens.index'))
            ->assertOk()
            ->assertDontSee($m->fresh()->getFirstMediaUrl(Mensagem::COLECAO_IMAGENS, 'web'), false);
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter="AutorShowTest|MensagemListaTest"
```

Esperado: o primeiro FALHA; o segundo passa (é a guarda do D11).

- [ ] **Passo 3: derrubar só o gate de formato**

`resources/views/components/mensagem/card.blade.php:11-13`:

```blade
    {{-- Miniatura: vale para QUALQUER formato (I12) — a de variante continua, porque a lista
         pública é sem imagem por desenho da 2B (D11) e Lista.php não faz eager-load de mídia. --}}
    $miniatura = $perfil ? $mensagem->getFirstMediaUrl(\App\Models\Mensagem::COLECAO_IMAGENS, 'web') : '';
```

E ajustar o comentário do topo do arquivo (linha 5), que diz *"'perfil' = COM miniatura de
pictografia"* → *"COM miniatura da mensagem (qualquer formato)"*.

- [ ] **Passo 4: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter="AutorShowTest|MensagemListaTest|MinhasDirecionadasTest"
```

Esperado: todos verdes.

- [ ] **Passo 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add resources/views/components/mensagem/card.blade.php tests/Feature/Front/AutorShowTest.php
git commit -m "feat(f4c-ac): miniatura do card vale para qualquer formato

Cai o gate de formato; o de variante fica. A lista pública segue sem
miniatura por desenho da 2B — e mudá-la exigiria eager-load de mídia em
Lista.php, que hoje só carrega autores."
```

---

## Task 9: Select de destinatários do admin passa a filtrar `ativo`

**Arquivos:**
- Modificar: `app/Filament/Schemas/MensagemForm.php:138-146` (Select do `schemaAdmin`) e
  o docblock de `blocoDestinatarios` (:160-161)
- Modificar: `tests/Feature/Filament/MensagemDestinatariosFormTest.php`

**Interfaces:**
- Produz: options do Select filtradas. Consumida logicamente pela Task 12 (a regra valida o
  conjunto efetivo).

> Sem esta task, a reasserção da Task 12 recusaria um destinatário que a **própria tela
> ofereceu** — mensagem que contradiz o que o admin vê.

- [ ] **Passo 1: escrever os testes (vermelho)**

> **Imports obrigatórios antes de colar**, em `MensagemDestinatariosFormTest.php` (:5-12):
> `use App\Enums\VisibilidadeMensagem;`, `use App\Filament\Resources\Mensagens\Pages\EditMensagem;`
> e `use App\Models\User;`.

```php
    /** I31: o Select não pode oferecer quem a regra vai descartar. */
    public function test_select_de_destinatarios_nao_oferece_usuario_inativo(): void
    {
        $ativo = User::factory()->create(['name' => 'Ana Ativa']);
        $inativo = User::factory()->create(['name' => 'Ivo Inativo', 'ativo' => false]);

        Livewire::test(CreateMensagem::class)
            ->fillForm(['nivel' => 'direcionada'])
            ->assertFormFieldExists('destinatarios', function (Select $f) use ($ativo, $inativo): bool {
                $opcoes = $f->getOptions();

                return array_key_exists($ativo->id, $opcoes) && ! array_key_exists($inativo->id, $opcoes);
            });
    }

    /**
     * I31 (a outra metade): quem JÁ está selecionado continua na lista mesmo tendo sido
     * desativado depois — senão o Select injeta Rule::in(options) sem o id hidratado pelo
     * fill() e trava até um Salvar de título, sem a opção aparecer para ser removida.
     */
    public function test_select_mantem_o_destinatario_ja_selecionado_que_ficou_inativo(): void
    {
        $u = User::factory()->create(['name' => 'Ivo Desativado Depois']);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);
        $u->update(['ativo' => false]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertFormFieldExists('destinatarios', fn (Select $f): bool => array_key_exists($u->id, $f->getOptions()));
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=MensagemDestinatariosFormTest
```

Esperado: o primeiro FALHA (hoje `User::orderBy('name')` lista todos); o segundo passa.

- [ ] **Passo 3: aplicar o filtro, com o `orWhereIn` obrigatório**

> ⚠️ **Trocar só a linha `->options(…)`** (`MensagemForm.php:141`). Os encadeamentos
> `->multiple()`, `->searchable()`, `->required(…)`, `->minItems(1)` e `->columnSpanFull(),`
> (`:142-146`) continuam idênticos — são eles que fazem `test_direcionada_sem_destinatario…` e
> `test_edit_remover_todos_reprova…` passarem. O bloco abaixo mostra o campo inteiro para
> contexto, mas **só a `->options` muda**.

`app/Filament/Schemas/MensagemForm.php`, no Select do `schemaAdmin` (:141) — molde literal do
`blocoDestinatarios` (:180-184), preservando o `helperText` que só o painel tem:

```php
                    Select::make('destinatarios')
                        ->label('Destinatários')
                        ->helperText('Obrigatório para mensagens de nível "Direcionada".')
                        // As options SEMPRE incluem os já selecionados (orWhereIn), mesmo que
                        // tenham deixado de estar `ativo` depois — senão o Select injeta
                        // Rule::in(options) sem o id hidratado pelo fill() e trava até um
                        // simples Salvar de título, sem a opção aparecer para ser removida.
                        ->options(fn (Get $get) => User::query()
                            ->where('ativo', true)
                            ->orWhereIn('id', (array) $get('destinatarios'))
                            ->orderBy('name')
                            ->pluck('name', 'id'))
```

- [ ] **Passo 4: corrigir o docblock que mentia**

Em `blocoDestinatarios` (:160-161), a frase *"NÃO é usado pelo schemaAdmin, que mantém a
Section inline (filtra `ativo` e não tem o helperText do painel …)"* descrevia uma intenção
nunca implementada. Agora o admin **de fato** filtra — trocar a justificativa:

```php
 * é o diretor, na curadoria). NÃO é usado pelo schemaAdmin, que mantém a Section inline por
 * causa do helperText próprio do painel — o filtro de `ativo` + orWhereIn é o MESMO nos dois
 * desde a F4c (antes desta fatia o docblock afirmava que o admin filtrava, e ele não filtrava).
```

- [ ] **Passo 5: rodar os testes**

```bash
docker compose exec -T app php artisan test --filter="MensagemDestinatariosFormTest|MensagemDestinatariosPersistenciaTest"
```

Esperado: todos verdes.

- [ ] **Passo 6: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Schemas/MensagemForm.php tests/Feature/Filament/MensagemDestinatariosFormTest.php
git commit -m "fix(f4c-ac): Select de destinatários do admin passa a filtrar ativo

O docblock de blocoDestinatarios afirmava que o schemaAdmin filtrava — e
ele não filtrava. Sem isto, a regra de publicação recusaria um destinatário
que a própria tela ofereceu. O orWhereIn não é opcional: sem ele um
destinatário desativado depois trava até um Salvar de título."
```

---

## Task 10: O admin grava o mesmo conjunto de destinatários que valida

**Arquivos:**
- Modificar: `app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php`
- Modificar: `tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php`

**Interfaces:**
- Produz: `aplicarDestinatarios()` passando a filtrar integridade.
- Consome: `SincronizadorDestinatarios::aplicar()` (já existe, **não** mudar).

> ⚠️ **Não tocar `capturarDestinatarios()`.** `MensagemDestinatariosGuardTest` o consome por
> classe anônima **sem banco**, assertando `[7, 9]` — ids que não existem em `users`. Levar
> `efetivos()` para dentro dele quebra o teste e o torna dependente de banco.

- [ ] **Passo 1: escrever os testes (vermelho)**

> **Import obrigatório antes de colar**, em `MensagemDestinatariosPersistenciaTest.php` (:7-15):
> `use App\Support\Mensagens\SincronizadorDestinatarios;` — o arquivo hoje **não** o importa, e
> sem ele o segundo teste dá erro **fatal**, não falha de asserção.

```php
    /** I32: valida com efetivos() e gravava o conjunto CRU — o inativo entrava no pivô. */
    public function test_nao_grava_destinatario_inativo_no_pivo(): void
    {
        $ativo = User::factory()->create();
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$ativo->id, $inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$ativo->id, $inativo->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$ativo->id], $this->pivo($m));
    }

    /**
     * I32 (a metade que a UI alcança): id forjado é barrado pela `Rule::in` que o Select
     * multiple injeta em `data.destinatarios.*` (CanBeValidated:912-917 + Select:1741-1766) —
     * nunca chega a `sincronizar()`, logo NÃO há `QueryException` de FK a provar por esta
     * porta. O filtro de integridade de `aplicar()` é provado no nível de domínio, abaixo.
     */
    public function test_id_inexistente_reprova_na_validacao_e_nao_entra_no_pivo(): void
    {
        $u = User::factory()->create();
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)->create();
        $m->destinatarios()->sync([$u->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['destinatarios' => [$u->id, 999999]])
            ->call('save')
            // `.0` e NÃO `.1`, embora o id inválido seja o segundo: quando o estado tem id fora
            // das options, Select::getInValidationRuleValues() devolve [] e a regra vira `in:`
            // com lista VAZIA aplicada a `destinatarios.*` ⇒ TODOS os índices reprovam. Não
            // "corrigir" para .1 — isso quebra o teste.
            ->assertHasErrors(['data.destinatarios.0']);

        $this->assertSame([$u->id], $this->pivo($m));
        $this->assertSame(
            [$u->id],
            SincronizadorDestinatarios::efetivos(VisibilidadeMensagem::Direcionada->value, [$u->id, 999999]),
        );
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=MensagemDestinatariosPersistenciaTest
```

Esperado: **só o primeiro** FALHA (pivô com os 2 ids). O segundo já passa — é a guarda de que o
id fantasma morre na validação nativa, antes de chegar ao pivô.

- [ ] **Passo 3: guardar o nível e trocar o método de gravação**

`app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php`:

```php
    /** @var array<int, int|string> */
    protected array $idsDestinatarios = [];

    /** Nível capturado junto dos ids — `aplicar()` precisa dele para o guard. */
    protected ?string $nivelDestinatarios = null;

    protected function capturarDestinatarios(array $data): array
    {
        // CORPO INALTERADO: o MensagemDestinatariosGuardTest exercita este método por classe
        // anônima SEM banco, com ids inexistentes. Trazer efetivos() para cá quebraria o teste
        // e o tornaria dependente de banco — o filtro de integridade mora em aplicar().
        $this->nivelDestinatarios = $data['nivel'] ?? null;
        $this->idsDestinatarios = SincronizadorDestinatarios::filtrarPorNivel(
            $data['nivel'] ?? null,
            $data['destinatarios'] ?? []
        );
        unset($data['destinatarios']); // nunca chega ao model (destinatarios não é coluna)

        return $data;
    }

    protected function aplicarDestinatarios(Mensagem $mensagem): void
    {
        // Era sincronizar() — CRU. Agora filtra integridade (ativo + existência), o mesmo
        // conjunto que a reasserção da regra valida: senão a regra passa por causa de um ativo
        // e o pivô grava o inativo junto, ou um id forjado estoura QueryException de FK.
        SincronizadorDestinatarios::aplicar($mensagem, $this->nivelDestinatarios, $this->idsDestinatarios);
    }
```

- [ ] **Passo 4: rodar os testes do trait e do guard**

```bash
docker compose exec -T app php artisan test --filter="MensagemDestinatariosPersistenciaTest|MensagemDestinatariosGuardTest|MensagemDestinatariosFormTest"
```

Esperado: todos verdes — **inclusive o Guard**, que não foi tocado.

- [ ] **Passo 5: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Resources/Mensagens/Pages/SincronizaDestinatarios.php \
        tests/Feature/Filament/MensagemDestinatariosPersistenciaTest.php
git commit -m "fix(f4c-ac): o admin grava o conjunto efetivo de destinatários

Validava com efetivos() e gravava com filtrarPorNivel() cru: com [ativo,
inativo] a regra passava por causa do ativo e o pivô guardava os dois; com
um id forjado, sincronizar() estourava FK. A correção fica em
aplicarDestinatarios — capturarDestinatarios continua intocado porque o
GuardTest o exercita sem banco, com ids inexistentes."
```

---

## Task 11: `nivel` obrigatório quando o status é publicado

**Arquivos:**
- Modificar: `app/Filament/Schemas/MensagemForm.php` (:87-91 `nivel`, :93-101 `status`, e a
  constante da mensagem)
- Modificar: `tests/Feature/Filament/MensagemResourceTest.php` (:66-70 e os 3 saves)

**Interfaces:**
- Produz: `MensagemForm::MSG_NIVEL_OBRIGATORIO` (string), consumida pela Task 13.

- [ ] **Passo 1: escrever o teste do `required` condicional (vermelho)**

Substituir `test_form_tem_select_nivel_com_publico_e_aceita_null` (:66-70) por:

```php
    /**
     * I25: o required é CONDICIONAL. Com status pendente o nível continua opcional (o /admin
     * cadastra rascunho); com status publicado ele é exigido, e é isso que fecha o buraco de
     * publicar sem nível — que já produziu 2 mensagens publicadas invisíveis no acervo.
     */
    public function test_form_tem_select_nivel_com_publico_e_required_so_quando_publicado(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm(['status' => Mensagem::STATUS_PENDENTE])
            ->assertFormFieldExists('nivel', fn (Select $f): bool => array_key_exists('publico', $f->getOptions()) && ! $f->isRequired());

        Livewire::test(CreateMensagem::class)
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO])
            ->assertFormFieldExists('nivel', fn (Select $f): bool => $f->isRequired());
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=test_form_tem_select_nivel_com_publico_e_required_so_quando_publicado
```

Esperado: FALHA na segunda asserção (`isRequired()` é `false` sempre).

- [ ] **Passo 3: a constante da mensagem**

No topo de `app/Filament/Schemas/MensagemForm.php`, dentro da classe:

```php
    /**
     * Frase única do /admin para "publicada precisa de nível" — consumida pelo
     * validationMessages() do Select E pela Action Publicar. A RegraPublicacao NÃO muda: ela é
     * compartilhada com a curadoria do site, onde o texto genérico é adequado, e tem teste
     * unitário próprio.
     */
    public const MSG_NIVEL_OBRIGATORIO = 'Selecione o nível de acesso para manter esta mensagem publicada.';
```

- [ ] **Passo 4: `required` condicional no `nivel` do `schemaAdmin`**

`MensagemForm.php:87-91`:

```php
                    Select::make('nivel')
                        ->label('Nível de acesso')
                        ->options(VisibilidadeMensagem::opcoes())
                        ->live() // pré-requisito do visible da Section Destinatários / required condicional
                        ->required(fn (Get $get): bool => $get('status') === Mensagem::STATUS_PUBLICADO)
                        ->validationMessages(['required' => self::MSG_NIVEL_OBRIGATORIO])
                        ->helperText('Define quem pode acessar esta mensagem no site.'),
```

- [ ] **Passo 5: `live()` no `status`**

`MensagemForm.php:93-101` — sem isto, o asterisco e a mensagem só reagem no próximo
round-trip:

```php
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            Mensagem::STATUS_PUBLICADO => 'Publicada',
                            Mensagem::STATUS_PENDENTE => 'Pendente',
                            Mensagem::STATUS_DESPUBLICADA => 'Despublicada',
                        ])
                        ->default(Mensagem::STATUS_PUBLICADO)
                        ->live() // o required condicional do `nivel` depende deste estado
                        ->required(),
```

> **Só no `schemaAdmin`.** O `schemaCuradoria` e o `schemaMedium` **não têm** campo `status`
> — `$get('status')` seria sempre `null` e a regra, morta. Lá quem arbitra é o botão Publicar
> da F4b.

- [ ] **Passo 6: ajustar os 3 saves que passam a quebrar**

Em `tests/Feature/Filament/MensagemResourceTest.php`, acrescentar `'nivel' => 'publico'` ao
`fillForm` de **`test_cria_mensagem_com_corpo_sanitizado`** (:90-96) e
**`test_criar_com_relacionadas_espelha_nos_dois_lados`** (:122-128). Em
**`test_edita_mensagem`** (:107-111), o registro vem da factory (`status=publicado`,
`nivel=null`), então o `fillForm` precisa do nível:

```php
            ->fillForm(['titulo' => 'Título Novo', 'nivel' => 'publico'])
```

- [ ] **Passo 7: travar a não-regressão do `schemaCuradoria` (§8-32)**

Acrescentar a `tests/Feature/Conta/CuradoriaContaTest.php` — o `required` condicional é
**exclusivo** do `schemaAdmin`, o único schema com campo `status`:

```php
    /** I25/§8-32: o required condicional do /admin não pode vazar para a curadoria. */
    public function test_i25_nivel_da_curadoria_continua_nao_required(): void
    {
        $pendente = Mensagem::factory()->pendente()->create();

        Livewire::actingAs($this->diretorDepae())->test(CuradoriaConta::class)
            ->call('editar', $pendente->id)
            ->assertFormFieldExists('nivel', fn (Select $f): bool => ! $f->isRequired());
    }
```

```bash
docker compose exec -T app php artisan test --filter="MensagemResourceTest|CuradoriaContaTest|CuradoriaPublicarTest|MensagensContaEditarTest"
```

Esperado: todos verdes. **A sentinela do vazamento é
`CuradoriaContaTest::test_i11_forjar_status_no_estado_nao_publica_prova_a_poda_do_getstate`**
(`:107-118`), que salva uma `factory()->pendente()` — logo `nivel = null` — esperando
`assertHasNoFormErrors()`. Se ela quebrar, o `required` vazou para o `schemaCuradoria`; voltar
ao Passo 4. ⚠️ `CuradoriaPublicarTest` **não** serve de sentinela: ele já espera erro em
`nivel` (`:89,107,122`) e passaria mesmo com o vazamento.

- [ ] **Passo 8: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Schemas/MensagemForm.php tests/Feature/Filament/MensagemResourceTest.php
git commit -m "feat(f4c-ac): nível vira obrigatório quando o status é publicado

Só no schemaAdmin: os outros dois não têm campo status, onde a regra seria
morta. A frase que ensina o caminho vira constante, porque a Action também
vai precisar dela. Três testes existentes passam a informar o nível — eles
usavam o default da factory, que nasce publicado e sem nível."
```

---

## Task 12: Regra e autoria nos caminhos do Select e da criação

**Arquivos:**
- Criar: `app/Filament/Resources/Mensagens/Pages/PublicaMensagem.php`
- Modificar: `app/Filament/Resources/Mensagens/Pages/EditMensagem.php`
- Modificar: `app/Filament/Resources/Mensagens/Pages/CreateMensagem.php`
- Criar: `tests/Feature/Filament/MensagemPublicarActionTest.php` (parte 1)
- Criar: `tests/Feature/Filament/PublicaMensagemHelperTest.php` (a rede server-side, ver Passo 1b)

**Interfaces:**
- Produz: `reasserirRegraDePublicacao(array $data): array` e
  `carimbarAutoriaSePublicando(Mensagem $registro): void`; propriedade `$publicandoAgora`.
- Consome: `MensagemForm::MSG_NIVEL_OBRIGATORIO` (Task 11);
  `SincronizadorDestinatarios::efetivos()`; `RegraPublicacao::erros()`.

> ⚠️ **O trait NUNCA declara hook.** `EditMensagem` e `CreateMensagem` já declaram
> `mutateFormDataBefore*` e `after*` **na classe**, e **método de classe vence método de trait
> sem erro nem aviso** — um hook no trait seria no-op silencioso. O trait expõe **helpers**,
> como os dois traits irmãos.

- [ ] **Passo 1: escrever os testes (vermelho)**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\CreateMensagem;
use App\Filament\Resources\Mensagens\Pages\EditMensagem;
use App\Filament\Schemas\MensagemForm;   // MSG_NIVEL_OBRIGATORIO — sem isto, erro FATAL
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MensagemPublicarActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /** I22: o Select publicava sem passar por regra nenhuma. */
    public function test_salvar_publicado_sem_nivel_e_recusado(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null])
            ->call('save')
            ->assertHasFormErrors(['nivel' => MensagemForm::MSG_NIVEL_OBRIGATORIO]);

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /** I23: mesmo buraco na criação — o status nasce publicado por default. */
    public function test_criar_publicado_sem_nivel_e_recusado(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Sem nível', 'slug' => 'sem-nivel', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['nivel']);

        $this->assertSame(0, Mensagem::where('slug', 'sem-nivel')->count());
    }

    /** I24: publicar pelo Select grava autoria, igual à Action. */
    public function test_publicar_pelo_select_grava_autoria(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico'])
            ->call('save')
            ->assertHasNoFormErrors();

        $f = $m->fresh();
        $this->assertNotNull($f->publicado_em);
        $this->assertSame(auth()->id(), $f->publicado_por_id);
    }

    /**
     * I26 — o BLOQUEADOR: as 133 publicadas do acervo têm publicado_em NULL. Um gatilho por
     * ESTADO ("publicado e publicado_em null") gravaria "publicada hoje, por mim" em qualquer
     * edição de qualquer uma delas. O gatilho é a TRANSIÇÃO.
     */
    public function test_editar_titulo_de_publicada_nao_carimba_autoria(): void
    {
        $m = Mensagem::factory()->create([
            'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico',
            'publicado_em' => null, 'publicado_por_id' => null, 'titulo' => 'Antigo',
        ]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['titulo' => 'Novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $f = $m->fresh();
        $this->assertSame('Novo', $f->titulo);
        $this->assertNull($f->publicado_em, 'autoria falsa carimbada numa publicada antiga');
        $this->assertNull($f->publicado_por_id);
    }

    /**
     * I30: sem `$hasDatabaseTransactions` o rollback do Filament é no-op e o save fica pela
     * metade. ⚠️ O cenário TEM de recusar pelo SERVIDOR, não pela validação nativa: com
     * `nivel = null` o `required` da Task 11 barra ANTES de `getState()` rodar
     * `saveRelationships()`, e o teste passaria por vacuidade sem provar transação nenhuma.
     * Por isso a recusa aqui vem do destinatário INATIVO — nível válido, validação nativa
     * satisfeita, e a reasserção lançando depois de autores e mídia já terem sido gravados.
     */
    public function test_save_recusado_nao_deixa_autores_gravados(): void
    {
        $autor = \App\Models\AutorEspiritual::factory()->create();
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)
            ->create(['status' => Mensagem::STATUS_PENDENTE]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm([
                'status' => Mensagem::STATUS_PUBLICADO,
                'destinatarios' => [$inativo->id],
                'autores' => [$autor->id],
            ])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);

        $this->assertCount(0, $m->fresh()->autores, 'meio-save: autores gravados apesar da recusa');
    }

    /** I24, a outra metade: nascer publicada também carimba — prova que o hook do Create é o afterCreate. */
    public function test_criar_ja_publicada_grava_autoria(): void
    {
        Livewire::test(CreateMensagem::class)
            ->fillForm([
                'titulo' => 'Nasce publicada', 'slug' => 'nasce-publicada', 'formato' => 'psicografia',
                'status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $m = Mensagem::where('slug', 'nasce-publicada')->firstOrFail();
        $this->assertNotNull($m->publicado_em);
        $this->assertSame(auth()->id(), $m->publicado_por_id);
    }

    /** I19/I22: direcionada cujo único destinatário está inativo não vai ao ar invisível. */
    public function test_publicar_direcionada_com_destinatario_inativo_e_recusado(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);
        $m = Mensagem::factory()->comNivel(VisibilidadeMensagem::Direcionada)
            ->create(['status' => Mensagem::STATUS_PENDENTE]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['status' => Mensagem::STATUS_PUBLICADO, 'destinatarios' => [$inativo->id]])
            ->call('save')
            ->assertHasFormErrors(['destinatarios']);
    }
}
```

- [ ] **Passo 1b: escrever o teste do HELPER (a rede que não depende do form)**

⚠️ **Sem este passo, dois dos três ramos de `reasserirRegraDePublicacao()` nascem sem prova.**
Os testes `test_salvar_publicado_sem_nivel_e_recusado` e `test_criar_publicado_sem_nivel_e_recusado`
**nunca alcançam o helper**: o `required` declarativo da Task 11 barra antes. Apagar o bloco do
nível dentro do helper deixaria a suíte inteira verde — e era exatamente esse o argumento da
SPEC §5.3 (*"`->required()` é hidratação, não integridade; a reasserção é a rede que não depende
dele"*). A rede precisa de prova própria.

Criar `tests/Feature/Filament/PublicaMensagemHelperTest.php` — molde **exato** de
`MensagemDestinatariosGuardTest`: os helpers são `protected`, então o harness anônimo os expõe.
É Feature (não Unit puro) porque `efetivos()` consulta `users`.

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Filament;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Resources\Mensagens\Pages\PublicaMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Testa a REASSERÇÃO server-side isoladamente. Ela existe porque o `->required()` do form é
 * hidratação, não integridade — e os testes de UI passam pelo required, nunca por aqui.
 */
class PublicaMensagemHelperTest extends TestCase
{
    use RefreshDatabase;

    private function harness(): object
    {
        return new class
        {
            use PublicaMensagem;

            public function exec(array $data): array
            {
                return $this->reasserirRegraDePublicacao($data);
            }
        };
    }

    /**
     * Captura FORA do catch: `$this->fail()` dentro de `catch (RuntimeException)` é engolido,
     * porque AssertionFailedError estende RuntimeException.
     *
     * @return array<string, array<int, string>>
     */
    private function errosAoExecutar(array $data): array
    {
        $erros = null;

        try {
            $this->harness()->exec($data);
        } catch (ValidationException $e) {
            $erros = $e->errors();
        }

        $this->assertNotNull($erros, 'esperava ValidationException e não veio nenhuma');

        return $erros;
    }

    /** Ramo 1: status != publicado ⇒ early-return, sem validar e sem lançar. */
    public function test_status_nao_publicado_passa_direto(): void
    {
        $data = ['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null, 'titulo' => 'Rascunho'];

        $this->assertSame($data, $this->harness()->exec($data));
    }

    /** Ramo 2: publicado sem nível ⇒ data.nivel, com a frase que ENSINA o caminho (C4). */
    public function test_publicado_sem_nivel_lanca_na_chave_do_nivel(): void
    {
        $erros = $this->errosAoExecutar(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => null]);

        $this->assertArrayHasKey('data.nivel', $erros);
        $this->assertSame(MensagemForm::MSG_NIVEL_OBRIGATORIO, $erros['data.nivel'][0]);
    }

    /** Ramo 2b: nível fora do enum é tão inválido quanto nulo (tryFrom fail-closed). */
    public function test_publicado_com_nivel_inexistente_lanca_na_chave_do_nivel(): void
    {
        $erros = $this->errosAoExecutar(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'lixo-invalido']);

        $this->assertArrayHasKey('data.nivel', $erros);
    }

    /** Ramo 3: direcionada cujo conjunto EFETIVO é vazio ⇒ data.destinatarios. */
    public function test_direcionada_so_com_inativo_lanca_na_chave_dos_destinatarios(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);

        $erros = $this->errosAoExecutar([
            'status' => Mensagem::STATUS_PUBLICADO,
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [$inativo->id],
        ]);

        $this->assertArrayHasKey('data.destinatarios', $erros);
    }

    /** Guarda: direcionada com destinatário ATIVO passa — a regra não é um "não" universal. */
    public function test_direcionada_com_ativo_passa(): void
    {
        $ativo = User::factory()->create();

        $data = [
            'status' => Mensagem::STATUS_PUBLICADO,
            'nivel' => VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => [$ativo->id],
        ];

        $this->assertSame($data, $this->harness()->exec($data));
    }
}
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter="MensagemPublicarActionTest|PublicaMensagemHelperTest"
```

Esperado: **os 5 do helper falham** (`PublicaMensagem` ainda não existe) e, em
`MensagemPublicarActionTest`, **4 FALHAS** — `test_publicar_pelo_select_grava_autoria`,
`test_criar_ja_publicada_grava_autoria`,
`test_publicar_direcionada_com_destinatario_inativo_e_recusado` e
`test_save_recusado_nao_deixa_autores_gravados`. Os outros 3 **já passam**: dois pelo
`required` declarativo da Task 11 (viraram guardas de que a UI recusa antes do servidor) e o do
I26 porque ninguém escreve autoria ainda.

- [ ] **Passo 3: criar o trait de helpers**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace App\Filament\Resources\Mensagens\Pages;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Mensagens\RegraPublicacao;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Illuminate\Validation\ValidationException;

/**
 * Reasserção server-side da regra de publicação + carimbo de autoria, para os caminhos do
 * /admin (Select `status` no Edit e criação). Molde EXATO dos traits irmãos
 * SincronizaDestinatarios/SincronizaRelacionadas: expõe HELPERS, nunca declara hook — as pages
 * já declaram os hooks `mutateFormDataBefore…` e `after…` na classe, e método de classe vence
 * método de trait sem erro nem aviso (o hook do trait seria no-op silencioso).
 */
trait PublicaMensagem
{
    /** Só a TRANSIÇÃO para publicado carimba autoria (nunca o estado). */
    protected bool $publicandoAgora = false;

    /**
     * Chamar ANTES de capturarDestinatarios(), que faz unset($data['destinatarios']): depois
     * dele toda direcionada seria lida como "sem destinatário".
     */
    protected function reasserirRegraDePublicacao(array $data): array
    {
        if (($data['status'] ?? null) !== Mensagem::STATUS_PUBLICADO) {
            return $data;
        }

        // efetivos(), NUNCA filtrarPorNivel(): é o filtro de `ativo` que impede publicar uma
        // direcionada visível para ninguém.
        $idsEfetivos = SincronizadorDestinatarios::efetivos(
            $data['nivel'] ?? null,
            $data['destinatarios'] ?? []
        );

        $erros = RegraPublicacao::erros([
            'nivel' => $data['nivel'] ?? null,
            'destinatarios' => $idsEfetivos,
        ]);

        if ($erros === []) {
            return $data;
        }

        // Com o conjunto EFETIVO já filtrado, só sobra erro de destinatário quando o nível em
        // si é 'direcionada' (válido); senão o erro é do nível (molde CuradoriaConta:157-163).
        $ehDirecionada = ($data['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
        $chave = $ehDirecionada ? 'data.destinatarios' : 'data.nivel';
        $mensagem = $ehDirecionada ? $erros[0] : MensagemForm::MSG_NIVEL_OBRIGATORIO;

        throw ValidationException::withMessages([$chave => $mensagem]);
    }

    protected function carimbarAutoriaSePublicando(Mensagem $registro): void
    {
        if (! $this->publicandoAgora) {
            return;
        }

        $registro->publicado_em = now();
        $registro->publicado_por_id = auth()->id();
        $registro->save();
    }
}
```

- [ ] **Passo 4: compor no `EditMensagem`**

> **Import obrigatório em AMBAS as pages** (`EditMensagem.php` importa hoje só
> `MensagemResource`, `DeleteAction` e `EditRecord`; `CreateMensagem.php`, só `MensagemResource`
> e `CreateRecord`) — sem ele, `Mensagem::STATUS_PUBLICADO` é erro fatal:
>
> ```php
> use App\Models\Mensagem;
> ```

```php
class EditMensagem extends EditRecord
{
    use PublicaMensagem;
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    /**
     * A reasserção lança DEPOIS de getState() já ter gravado autores e mídia em
     * saveRelationships(); sem esta flag o begin/rollback do Filament é no-op (opt-in, default
     * off) e a recusa deixaria meio-save. Precedente: CreateUser/EditUser.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['relacionadas'] = $this->record->relacionadas()->pluck('mensagens.id')->all();
        $data['destinatarios'] = $this->record->destinatarios()->pluck('users.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->publicandoAgora = $this->record->status !== Mensagem::STATUS_PUBLICADO
            && ($data['status'] ?? null) === Mensagem::STATUS_PUBLICADO;

        $data = $this->reasserirRegraDePublicacao($data);   // ANTES de capturarDestinatarios

        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterSave(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
        $this->carimbarAutoriaSePublicando($this->record);
    }
}
```

- [ ] **Passo 5: compor no `CreateMensagem` — hooks de nome DIFERENTE**

```php
class CreateMensagem extends CreateRecord
{
    use PublicaMensagem;
    use SincronizaDestinatarios;
    use SincronizaRelacionadas;

    protected static string $resource = MensagemResource::class;

    /**
     * Aqui o saveRelationships() roda DEPOIS do mutate (CreateRecord::create:115), então a
     * recusa não deixa meio-save de relações. A flag entra assim mesmo: torna atômico o par
     * create + pivôs do afterCreate.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->publicandoAgora = ($data['status'] ?? null) === Mensagem::STATUS_PUBLICADO; // sem estado anterior

        $data = $this->reasserirRegraDePublicacao($data);

        return $this->capturarDestinatarios($this->capturarRelacionadas($data));
    }

    protected function afterCreate(): void
    {
        $this->aplicarRelacionadas($this->record);
        $this->aplicarDestinatarios($this->record);
        $this->carimbarAutoriaSePublicando($this->record);
    }
}
```

- [ ] **Passo 6: rodar os testes novos e os 11 saves**

```bash
docker compose exec -T app php artisan test --filter="MensagemPublicarActionTest|MensagemDestinatariosPersistenciaTest|MensagemDestinatariosFormTest|MensagemResourceTest"
```

Esperado: todos verdes. Se algum dos 6 saves do `PersistenciaTest` quebrar com "sem
destinatário", a reasserção rodou **depois** de `capturarDestinatarios` — conferir a ordem no
passo 4.

- [ ] **Passo 7: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Resources/Mensagens/Pages/ tests/Feature/Filament/MensagemPublicarActionTest.php
git commit -m "feat(f4c-ac): regra e autoria também nos caminhos do Select e da criação

O Select publicava sem validar nada e sem gravar autoria — daí as 133
publicadas terem publicado_em NULL e existirem 2 publicadas sem nível. O
carimbo é por TRANSIÇÃO, nunca por estado: um gatilho de estado gravaria
'publicada hoje, por mim' em qualquer edição das 133. O trait expõe helpers
e não declara hook, porque método de classe vence método de trait sem erro.
As pages ligam hasDatabaseTransactions: sem isso a recusa deixa autores e
mídia gravados, porque o rollback do Filament é opt-in."
```

---

## Task 13: Action "Publicar" no header do EditMensagem

**Arquivos:**
- Modificar: `app/Filament/Resources/Mensagens/Pages/EditMensagem.php` (`getHeaderActions`)
- Modificar: `tests/Feature/Filament/MensagemPublicarActionTest.php` (parte 2)

**Interfaces:**
- Consome: `MensagemForm::MSG_NIVEL_OBRIGATORIO` (Task 11); `RegraPublicacao::erros()`;
  `SincronizadorDestinatarios::efetivos()`/`sincronizar()`; `Mensagem::sincronizarRelacionadas()`.

- [ ] **Passo 1: escrever os testes (vermelho)**

> ⚠️ **`assertHasFormErrors` depois de `callAction` precisa do 2º argumento `'form'`.** Com
> `requiresConfirmation()` a Action **não é desmontada** ao lançar
> (`InteractsWithActions.php:296-306`), e `assertHasFormErrors()` sem nome resolve para
> `mountedActionSchema0` (`TestsForms.php:86`), que não existe numa Action sem `->schema()`
> (`EditRecord.php:297-305` só resolve schema default para Create/Edit/ViewAction) ⇒
> **`PropertyNotFoundException`**, erro fatal, não falha de asserção. Nos testes via
> `->call('save')`/`->call('create')` (Task 12) a Action não está montada e o default resolve
> certo — o nome `'form'` é obrigatório **só aqui**.

Acrescentar a `MensagemPublicarActionTest`:

```php
    private function pendente(array $attrs = []): Mensagem
    {
        return Mensagem::factory()->create([...['status' => Mensagem::STATUS_PENDENTE, 'nivel' => null], ...$attrs]);
    }

    /** I18: a Action é a primeira escritora de publicado_em no painel. */
    public function test_action_publica_e_grava_autoria(): void
    {
        $m = $this->pendente(['slug' => 'a-publicar']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $f = $m->fresh();
        $this->assertSame(Mensagem::STATUS_PUBLICADO, $f->status);
        $this->assertNotNull($f->publicado_em);
        $this->assertSame(auth()->id(), $f->publicado_por_id);
    }

    /**
     * I17: contrato INVERSO ao do /minha-conta (CuradoriaConta:169 regenera). Aqui o slug é
     * campo de tela: regenerar sobrescreveria o que o admin digitou.
     */
    public function test_action_nao_altera_o_slug(): void
    {
        $m = $this->pendente(['slug' => 'slug-escolhido-a-mao', 'titulo' => 'Título Completamente Outro']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $this->assertSame('slug-escolhido-a-mao', $m->fresh()->slug);
    }

    /** I27: relacionadas não são fillable — fill() as descartaria em silêncio. */
    public function test_action_persiste_as_relacionadas_nos_dois_sentidos(): void
    {
        $b = Mensagem::factory()->create(['titulo' => 'Mensagem B']);
        $m = $this->pendente(['slug' => 'com-relacionada']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico', 'relacionadas' => [$b->id]])
            ->callAction('publicar');

        $this->assertTrue($m->fresh()->relacionadas->contains('id', $b->id));
        $this->assertTrue($b->fresh()->relacionadas->contains('id', $m->id), 'a relação não espelhou');
    }

    /**
     * I19: nível NULO pela UI. O slug inválido não é injetável por esta porta (o Select aplica
     * `Rule::in` sobre as 6 opções do enum) e já está coberto em
     * tests/Unit/Mensagens/RegraPublicacaoTest.php:27.
     */
    public function test_action_recusa_nivel_invalido(): void
    {
        $m = $this->pendente();

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => null])
            ->callAction('publicar')
            ->assertHasFormErrors(['nivel'], 'form');

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /** I19: o caminho que a validação NATIVA do Select não cobre. */
    public function test_action_recusa_direcionada_com_destinatario_inativo(): void
    {
        $inativo = User::factory()->create(['ativo' => false]);
        $m = $this->pendente(['nivel' => VisibilidadeMensagem::Direcionada->value]);
        $m->destinatarios()->sync([$inativo->id]);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->callAction('publicar')
            ->assertHasFormErrors(['destinatarios'], 'form');

        $this->assertSame(Mensagem::STATUS_PENDENTE, $m->fresh()->status);
    }

    /**
     * I20. SÓ assertActionHidden: visible(false) já protege no v5.6.7 — hidden ⇒ isDisabled()
     * ⇒ mountAction() desmonta e retorna null, e callAction() faz assertActionVisible() antes.
     * "Chamar numa publicada e afirmar que nada mudou" seria falso-verde.
     */
    public function test_action_nao_aparece_em_mensagem_ja_publicada(): void
    {
        $m = Mensagem::factory()->create(['status' => Mensagem::STATUS_PUBLICADO, 'nivel' => 'publico']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->assertActionHidden('publicar');
    }

    /** I21: sem o refreshFormData, o próximo "Salvar alterações" despublica em silêncio. */
    public function test_depois_da_action_salvar_nao_despublica(): void
    {
        $m = $this->pendente(['slug' => 'nao-despublicar']);

        $tela = Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $tela->call('save')->assertHasNoFormErrors();

        $this->assertSame(Mensagem::STATUS_PUBLICADO, $m->fresh()->status);
    }
```

E o teste de porta, **com os dois passos de `setUp`**:

```php
    /** I29. $portaForcada é ESTÁTICA e sobrevive entre testes do mesmo processo: resetar é obrigatório. */
    public function test_auditoria_da_action_registra_porta_admin(): void
    {
        \App\Support\Autorizacao\AuditoriaAutorizacao::usarPorta(null);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $m = $this->pendente(['slug' => 'porta-admin']);

        Livewire::test(EditMensagem::class, ['record' => $m->getRouteKey()])
            ->fillForm(['nivel' => 'publico'])
            ->callAction('publicar');

        $atividade = $m->fresh()->activities()->latest('id')->first();

        $this->assertSame('admin', $atividade->properties['porta']);
    }
```

- [ ] **Passo 2: rodar e confirmar que falha**

```bash
docker compose exec -T app php artisan test --filter=MensagemPublicarActionTest
```

Esperado: as 8 novas FALHAM (a Action não existe).

- [ ] **Passo 3: implementar a Action**

Em `EditMensagem::getHeaderActions()`:

```php
    protected function getHeaderActions(): array
    {
        return [
            Action::make('publicar')
                ->label('Publicar')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->modalHeading('Publicar esta mensagem?')
                ->modalDescription('A mensagem passa a valer no site, com o nível de acesso escolhido no formulário.')
                ->visible(fn (): bool => $this->record->status !== Mensagem::STATUS_PUBLICADO)
                ->action(function (): void {
                    DB::transaction(function (): void {
                        $registro = $this->record;

                        // Defesa em profundidade — NÃO exercitável pela UI (visible false já
                        // impede o mount). Impede sobrescrever a autoria de outra pessoa.
                        abort_if($registro->status === Mensagem::STATUS_PUBLICADO, 403);

                        $dados = $this->form->getState();  // valida + saveRelationships(), DENTRO da transação

                        // Reasserção dos 3 campos privilegiados: hoje redundante (não são
                        // fillable e getState() já poda), mas explícita — DATA-MODEL.md.
                        unset($dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em']);

                        $ids = SincronizadorDestinatarios::efetivos($dados['nivel'] ?? null, $dados['destinatarios'] ?? []);
                        $erros = RegraPublicacao::erros(['nivel' => $dados['nivel'] ?? null, 'destinatarios' => $ids]);

                        if ($erros !== []) {
                            $ehDirecionada = ($dados['nivel'] ?? null) === VisibilidadeMensagem::Direcionada->value;
                            $chave = $ehDirecionada ? 'data.destinatarios' : 'data.nivel';
                            $mensagem = $ehDirecionada ? $erros[0] : MensagemForm::MSG_NIVEL_OBRIGATORIO;

                            throw ValidationException::withMessages([$chave => $mensagem]);
                        }

                        $idsRelacionadas = $dados['relacionadas'] ?? [];
                        // Nenhum dos dois é coluna: fill() os descartaria em SILÊNCIO.
                        unset($dados['destinatarios'], $dados['relacionadas']);

                        $registro->fill($dados);
                        // NÃO regenerar o slug: aqui ele é campo de tela (contrato inverso ao
                        // de CuradoriaConta:169, onde o slug nasce do rascunho do médium).
                        $registro->status = Mensagem::STATUS_PUBLICADO;
                        $registro->publicado_por_id = auth()->id();
                        $registro->publicado_em = now();
                        $registro->save();

                        SincronizadorDestinatarios::sincronizar($registro, $ids);
                        $registro->sincronizarRelacionadas($idsRelacionadas);
                    });

                    // Sem isto, $this->data['status'] segue "pendente" e o próximo
                    // "Salvar alterações" despublica em silêncio.
                    $this->refreshFormData(['status']);

                    Notification::make()->success()->title('Mensagem publicada.')->send();
                }),

            DeleteAction::make(),
        ];
    }
```

Imports a acrescentar em `EditMensagem.php`:

```php
use App\Enums\VisibilidadeMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Mensagens\RegraPublicacao;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
```

- [ ] **Passo 4: rodar os testes da Action**

```bash
docker compose exec -T app php artisan test --filter=MensagemPublicarActionTest
```

Esperado: **15 passed** — 7 da Task 12 (4 que nasceram vermelhos + 3 guardas) + 8 desta.

- [ ] **Passo 5: rodar a suíte completa**

```bash
docker compose exec -T app php artisan test
```

Esperado: verde, com total **acima de 1221**. Nenhuma falha nova.

- [ ] **Passo 6: conferir na tela**

```bash
docker compose restart app worker
```

Abrir `/admin/mensagens` → uma pendente → o botão **Publicar** no topo. **Antes de clicar,
revisar o campo Slug**: 39 das 47 pendentes têm slug de máquina (`comunicabilidade-25751`), e
a URL nasce definitiva. Publicar e conferir que o Select `status` virou "Publicada".

- [ ] **Passo 7: Pint e commit**

```bash
docker compose exec -T app ./vendor/bin/pint
git add app/Filament/Resources/Mensagens/Pages/EditMensagem.php tests/Feature/Filament/MensagemPublicarActionTest.php
git commit -m "feat(f4c-ac): botão Publicar no header do EditMensagem

Lê o FORM, não o registro: 47 das 47 pendentes têm nível nulo, então uma
Action que validasse o registro persistido recusaria todos os casos reais.
Transação explícita porque getState() grava autores e mídia antes da regra.
Não regenera o slug — contrato inverso ao do /minha-conta, onde o slug
nasce do servidor. E persiste as relacionadas, que fill() descartaria em
silêncio por não serem coluna."
```

---

## Task 14: Documentação que passou a mentir

**Arquivos:**
- Modificar: `DATA-MODEL.md` (:462-464, :524-525, seção de mídia/mensagens)
- Modificar: `docs/superpowers/specs/2026-07-21-camada-4-fatia-f4b-curadoria-medium-depae.md`
  (nota de superação do O6)

- [ ] **Passo 1: `DATA-MODEL.md` — a redação da trilha**

Em `:462-464`, onde diz que o `tapActivity()` redige `corpo` e `contexto`, incluir `resumo`.

- [ ] **Passo 2: `DATA-MODEL.md` — a autoria da publicação**

Em `:524-525`, onde diz que `publicado_por_id`/`publicado_em` são "gravados só em
`publicar()`": passam a ser gravados também pela Action do `/admin` e pelos hooks de
`EditMensagem`/`CreateMensagem`, **sempre na transição** para publicado.

- [ ] **Passo 3: `DATA-MODEL.md` — coluna e coleção**

Não existe tabela de colunas de `mensagens` no `DATA-MODEL.md` — acrescentar dois bullets ao
final da seção **"### Edição pelo site — Mensagens mediúnicas"** (`:496-516`):

```markdown
- **`resumo`** (`text` nullable, após `contexto`) — texto editorial **da curadoria**, importado
  do `post_excerpt` do legado por `cema:importar-resumos` (só preenche o que está vazio). Texto
  puro, sem HTML; aparece no card, na meta description e como lead do single. Está no
  `$fillable`, no `logOnly` e no glossário, e é **redigido** no `tapActivity()`.
- **Mídia** — a coleção de `Mensagem` chama-se **`imagens`** (`Mensagem::COLECAO_IMAGENS`,
  ex-`pictografia`) e vale para os **3 formatos**; na Pictografia os desenhos SÃO a mensagem.
```

- [ ] **Passo 3b: `DATA-MODEL.md:512` — o nome do campo do form**

`'corrige título/corpo/autores/pictografia'` → `'corrige título/corpo/autores/**imagens**'` (o
campo foi renomeado na Task 6).

- [ ] **Passo 4: SPEC da F4b — superação do O6**

Acrescentar nota em `§6.6` e `§13` daquela SPEC: o **O6** ("a `RegraPublicacao` vale só no
site") foi **revogado pela F4c-AC** — a regra passa a valer nos 3 caminhos do `/admin`.

- [ ] **Passo 5: rodar os greps do I16 uma última vez e a suíte**

```bash
grep -rn "COLECAO_PICTOGRAFIA" app/ resources/ tests/ ; echo "--- esperado: 0 ---"
grep -rnE "['\"]pictografia['\"]" app/ resources/ ; echo "--- esperado: 2 (FormatoMensagem:11 e ResumoAutor:24) ---"
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app php artisan test
npm run build     # NO HOST
```

- [ ] **Passo 6: commit da documentação**

```bash
git add DATA-MODEL.md ROADMAP.md docs/superpowers/specs/ docs/superpowers/plans/
git commit -m "docs(f4c-ac): atualiza o que a fatia tornou desatualizado

A redação da trilha agora inclui resumo; a autoria da publicação deixou de
ser exclusiva do publicar() do site; a coleção de mídia mudou de nome; o O6
da F4b foi revogado; e o ROADMAP deixa de dizer que a F4b tem PR a abrir."
```

- [ ] **Passo 7: abrir o PR**

```bash
git push -u origin camada-4-fatia-f4c-ac-resumo-ajustes
gh pr create --base main --title "Camada 4 · F4c-AC — resumo do legado + ajustes da curadoria"
```

No corpo do PR, **declarar explicitamente** (SPEC §5.3):

- a **divergência de slug** entre a Action do `/admin` (não regenera) e o `/minha-conta`
  (regenera) — e que 39 das 47 pendentes carregam slug de máquina;
- que as mensagens **168** e **179** passam a **travar qualquer edição no `/admin`** até
  receberem nível — efeito desejado do C4, **não** regressão;
- que o cutover exige `npm run build` no host e o túnel SSH aberto no passo 3;
- que o **O6 da F4b foi revogado** (D12).

- [ ] **Passo 8: esperar o CI**

Só pedir o "go" do dono **depois** que o CI fechar verde no **último** commit — não com check
pendente.

---

## Cutover (do dono, depois do merge)

```
1) git pull
2) docker compose exec -T app php artisan migrate        # 2 migrations
3) docker compose exec -T app php artisan cema:importar-resumos   # COM o túnel SSH aberto
4) npm run build                                          # NO HOST — obrigatório (o lead traz classes novas)
5) docker compose exec -T app php artisan optimize:clear
6) docker compose restart app worker
```

**Esperado no passo 3:** `Já tinham: 0` · `Sem mensagem: 0` · **`Atualizadas + Curtas = 154`**.
A partilha 151/3 foi medida em 21/07 e deve ser reconferida no ato — o legado é um site vivo.

**Conferir na tela:** card com o novo trecho · single com o lead e a nova description · subir
imagem numa **psicografia pública com autor** (p.ex. id **68**, `/ser-consciente`; as 3 que já
têm imagem não servem — 93 e 158 não têm autor e não são públicas) · botão Publicar numa
pendente, **revisando o slug antes** · abrir a mensagem **168** e ver a mensagem que pede o
nível (o campo Resumo pode já vir preenchido pelo passo 3 — é esperado).
