# Imagens alinhadas/redimensionadas no Blog — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir, no RichEditor do blog, **alinhar/flutuar** (esq/dir/centro, texto contornando) e **redimensionar** imagens com **saída HTML responsiva** (classes + CSS, largura em %/max-width, nunca px fixo), e **preservar/estilizar** o layout de imagem do Gutenberg migrado (tamanhos + colunas imagem-ao-lado-de-texto).

**Architecture:** Uma **extensão TipTap** (JS estático que importa de `window.FilamentRichEditor.tiptap`, sem rebundlar; + espelho PHP que estende o `ImageExtension` do Filament para o round-trip no `getHtml` do servidor) adiciona ao nó `image` os atributos `align` e `size` renderizados como **classes** (`alignleft/alignright/aligncenter/alignnone`, `size-*`/`is-resized`). Registrada via um `RichContentPlugin` (`getTipTapJsExtensions` + `getEditorTools` + `getTipTapPhpExtensions`). O **purifier** (`conteudo_blog`) libera `class` (allow-list fechada via `Attr.AllowedClasses`), sem `style` inline. O **importador** preserva `size-*`/`aligncenter` e converte colunas Gutenberg num grid limpo por classes; **reimporta** os 45 posts. Um **CSS público** (`.conteudo-artigo`) estiliza a saída nova e a migrada com uma folha só. O single mantém `{!! $post->conteudo !!}` + nosso purifier (sem `RichContentRenderer`).

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5.6.7 (RichEditor TipTap v3 + ueberdosis/tiptap-php 2.1) · mews/purifier (HTMLPurifier) · Tailwind v4 · Vite · Docker. Testes: `docker compose exec -T app php artisan test`. Build de assets (quando houver): `npm run build` (host).

## Global Constraints

- **Saída responsiva por CLASSES**, nunca `px` fixo, sem `style` inline de largura. Largura em %/max-width via CSS atrelado às classes. No mobile a imagem flutuante **empilha** full-width.
- **Classes do WordPress** como vocabulário único: `alignleft`/`alignright`/`aligncenter`/`alignnone`, `size-thumbnail/medium/large/full`, `is-resized`, `wp-block-image` — assim **um só CSS** cobre editor novo e Gutenberg migrado.
- **Pipeline de render inalterado:** single continua `{!! $post->conteudo !!}` sanitizado pelo mutator do `Post` (`clean($v,'conteudo_blog')`). **NÃO** trocar para `RichContentRenderer`.
- **Sanitização por allow-list:** liberar `class` em `img/figure/div` + `Attr.AllowedClasses` (lista fechada). **Nunca** liberar `style` inline.
- **Legado SOMENTE LEITURA**; importação idempotente (upsert por slug); reimport seguro.
- **JS da extensão** importa TipTap de `window.FilamentRichEditor.tiptap` (NÃO rebundlar TipTap). Round-trip do save é via **tiptap-php no servidor** (`RichEditorStateCast::get → getHtml`), logo o **espelho PHP** é obrigatório.
- Cabeçalho de autoria `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26` em classes PHP novas. pt-BR (labels/comentários/commits). Testes só com `php artisan test` (SQLite). NUNCA `migrate:fresh`/`db:wipe`/factory destrutiva na conexão default.

---

## File Structure

- `config/purifier.php` — perfil `conteudo_blog`: `class` em img/figure/div + `Attr.AllowedClasses`.
- `app/Importacao/TransformadorBlog.php` — `limparGutenberg()` (novo/estendido): preservar `wp-block-image`/`size-*`/`aligncenter`; converter `wp-block-columns/wp-block-column`(+flex-basis) → `.colunas/.coluna`.
- `app/Importacao/ReescritorImagensConteudo.php` / `ImportadorBlog.php` — chamar `limparGutenberg()` antes da sanitização (se ainda não chamam).
- `app/Filament/RichContent/Plugins/ImagemPlugin.php` — `implements RichContentPlugin` (JS + PHP + tools).
- `app/Filament/RichContent/TipTap/ImagemExtension.php` — espelho PHP (estende `Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension`) com atributos `align`/`size` (classe).
- `resources/js/filament/imagem-alinhada.js` — extensão TipTap JS estática (atributos `align`/`size` → classe + comandos).
- `app/Providers/Filament/AdminPanelProvider.php` — registrar o asset JS via `FilamentAsset::register([...])` no `boot()`.
- `app/Filament/Resources/Posts/PostResource.php` — `RichEditor::make('conteudo')->plugins([ImagemPlugin::make()])->toolbarButtons([...])`.
- `resources/css/conteudo.css` (novo) + import em `resources/css/app.css` — CSS do conteúdo (`.conteudo-artigo`).
- `resources/views/blog/show.blade.php` — wrapper do conteúdo recebe `.conteudo-artigo`.
- Testes: `tests/Feature/Models/SanitizacaoBlogTest.php` (estender), `tests/Unit/Importacao/TransformadorBlogTest.php` (estender), `tests/Feature/Front/BlogConteudoResponsivoTest.php` (novo).

---

## Task 1 (SPIKE): extensão TipTap JS carrega + round-trip do atributo `align`

**Objetivo do spike:** provar o pé do build/integração antes da feature inteira — (1) um módulo TipTap JS estático carrega no RichEditor via plugin + FilamentAsset; (2) um atributo `align` no nó `image` **sobrevive ao save** (round-trip pelo tiptap-php no servidor), gravando `class="alignleft"`. Resolver aqui o **mecanismo do espelho PHP** (subclasse de `ImageExtension` vs atributos globais).

**Files:**
- Create: `app/Filament/RichContent/Plugins/ImagemPlugin.php`, `app/Filament/RichContent/TipTap/ImagemExtension.php`, `resources/js/filament/imagem-alinhada.js`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (registrar asset), `app/Filament/Resources/Posts/PostResource.php` (plugin + 1 botão)
- Test: `tests/Feature/Filament/RichEditorImagemRoundtripTest.php`

**Interfaces — Produces:** `ImagemPlugin::make(): static` implementando `Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin` (`getTipTapPhpExtensions(): array`, `getTipTapJsExtensions(): array`, `getEditorTools(): array`, `getEditorActions(): array`).

- [ ] **Step 1: JS estático mínimo** — `resources/js/filament/imagem-alinhada.js`. Importa do global do Filament (sem bundler) e adiciona o atributo `align` (→ classe) ao nó `image` + comando:

```js
// Extensão TipTap: alinhamento de imagem por classe. Importa o TipTap do Filament
// (não rebundlar). Carregada via RichContentPlugin::getTipTapJsExtensions().
const { Extension } = window.FilamentRichEditor.tiptap.core

const CLASSES_ALIGN = { left: 'alignleft', right: 'alignright', center: 'aligncenter', none: 'alignnone' }

export default Extension.create({
    name: 'imagemAlinhada',
    addGlobalAttributes() {
        return [{
            types: ['image'],
            attributes: {
                align: {
                    default: null,
                    parseHTML: (el) => {
                        for (const [k, c] of Object.entries(CLASSES_ALIGN)) {
                            if (el.classList?.contains(c)) return k
                        }
                        return null
                    },
                    renderHTML: (attrs) => (attrs.align && CLASSES_ALIGN[attrs.align])
                        ? { class: CLASSES_ALIGN[attrs.align] } : {},
                },
            },
        }]
    },
    addCommands() {
        return {
            definirAlinhamentoImagem: (align) => ({ commands }) =>
                commands.updateAttributes('image', { align }),
        }
    },
})
```

- [ ] **Step 2: Espelho PHP (candidato A — subclasse)** — `app/Filament/RichContent/TipTap/ImagemExtension.php` estende o `ImageExtension` do Filament e adiciona o atributo `align` que faz parse/render via **classe** (espelha o padrão de `addAttributes` do Filament, que vimos em `vendor/.../TipTapExtensions/ImageExtension.php`):

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26
namespace App\Filament\RichContent\TipTap;

use Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension;

class ImagemExtension extends ImageExtension
{
    private const CLASSES = ['left' => 'alignleft', 'right' => 'alignright', 'center' => 'aligncenter', 'none' => 'alignnone'];

    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            'align' => [
                'parseHTML' => function ($DOMNode) {
                    $classes = explode(' ', (string) $DOMNode->getAttribute('class'));
                    foreach (self::CLASSES as $k => $c) {
                        if (in_array($c, $classes, true)) {
                            return $k;
                        }
                    }
                    return null;
                },
                'renderHTML' => fn ($attributes) => isset($attributes->align) && isset(self::CLASSES[$attributes->align])
                    ? ['class' => self::CLASSES[$attributes->align]]
                    : [],
            ],
        ];
    }
}
```

- [ ] **Step 3: Plugin** — `app/Filament/RichContent/Plugins/ImagemPlugin.php`:

```php
<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26
namespace App\Filament\RichContent\Plugins;

use App\Filament\RichContent\TipTap\ImagemExtension;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;

class ImagemPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc('imagem-alinhada', 'app')];
    }

    public function getTipTapPhpExtensions(): array
    {
        return [app(ImagemExtension::class)];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('imagemAlinharEsquerda')
                ->icon(Heroicon::Bars3BottomLeft)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("left").run()'),
        ];
    }

    public function getEditorActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Registrar o asset** — em `app/Providers/Filament/AdminPanelProvider.php::boot()` (criar `boot()` se não existir):

```php
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Js;

public function boot(): void
{
    FilamentAsset::register([
        Js::make('imagem-alinhada', resource_path('js/filament/imagem-alinhada.js'))->loadedOnRequest(),
    ], package: 'app');
}
```

- [ ] **Step 5: Ligar no PostResource** — no `RichEditor::make('conteudo')` adicionar `->plugins([\App\Filament\RichContent\Plugins\ImagemPlugin::make()])` e, em `toolbarButtons`, incluir `'imagemAlinharEsquerda'` num grupo.

- [ ] **Step 6: Teste de round-trip do save (servidor)** — `tests/Feature/Filament/RichEditorImagemRoundtripTest.php`: monta o `RichEditor` com o plugin e exercita o `RichEditorStateCast` (server) para confirmar que um documento com `image` + `align:left` gera HTML com `class="alignleft"`. Esqueleto:

```php
public function test_atributo_align_sobrevive_ao_getHtml(): void
{
    $campo = \Filament\Forms\Components\RichEditor::make('conteudo')
        ->plugins([\App\Filament\RichContent\Plugins\ImagemPlugin::make()]);

    $doc = ['type' => 'doc', 'content' => [[
        'type' => 'paragraph', 'content' => [[
            'type' => 'image',
            'attrs' => ['src' => 'https://x/a.jpg', 'align' => 'left'],
        ]],
    ]]];

    $html = $campo->getTipTapEditor()->setContent($doc)->getHtml();

    $this->assertStringContainsString('alignleft', $html);
}
```

- [ ] **Step 7: Rodar o teste.** Se FALHAR (o `align` não sai como classe), o candidato A não funcionou no tiptap-php → **candidato B**: em vez de subclasse, registrar uma `\Tiptap\Core\Extension` com `addGlobalAttributes()` para `['image']` (espelho exato do JS). Trocar `getTipTapPhpExtensions()` para devolvê-la. Repetir o teste até passar. Documentar no relatório qual candidato venceu.

- [ ] **Step 8: Verificação manual (carga do JS)** — `npm run build` (se aplicável) + abrir `/admin`, editar um post, inserir imagem, clicar no botão "Alinhar à esquerda"; confirmar no DOM do editor a classe `alignleft` na `<img>`. Salvar e conferir no banco (`Post::find(id)->conteudo`) que o HTML salvo contém `alignleft`.

- [ ] **Step 9: Commit** — `spike(blog): extensão TipTap de alinhamento de imagem (JS+PHP) com round-trip provado`.

**⚠️ Gate do spike:** só prosseguir para as próximas tasks após o Step 8 confirmar o round-trip real (classe no HTML salvo). Se o round-trip não for viável por nenhum candidato, ESCALAR (reabrir a decisão do editor).

---

## Task 2: Sanitização — allow-list de classes no purifier

**Files:** Modify `config/purifier.php` (perfil `conteudo_blog`); Test `tests/Feature/Models/SanitizacaoBlogTest.php`.

- [ ] **Step 1: Teste (falha primeiro)** — classes da allow-list sobrevivem; classe fora dela é removida; `style` inline some:

```php
public function test_sanitiza_preserva_classes_de_imagem_da_allowlist(): void
{
    $p = Post::factory()->create([
        'conteudo' => '<figure class="wp-block-image size-large alignleft hackzor">'
            .'<img src="/x.jpg" alt="" class="alignright size-medium evil" style="width:50px"></figure>',
    ]);
    foreach (['wp-block-image','size-large','alignleft','alignright','size-medium'] as $c) {
        $this->assertStringContainsString($c, $p->conteudo);
    }
    $this->assertStringNotContainsString('hackzor', $p->conteudo);
    $this->assertStringNotContainsString('evil', $p->conteudo);
    $this->assertStringNotContainsString('style=', $p->conteudo);   // sem style inline
}
```

- [ ] **Step 2: Implementar** — no perfil `conteudo_blog`, no `HTML.Allowed` adicionar `class` em `img`, `figure` e incluir `div[class]`; adicionar a diretiva `Attr.AllowedClasses`:

```php
'HTML.Allowed' => 'p,br,b,strong,i,em,u,s,h2,h3,h4,h5,ul,ol,li,blockquote,'
    .'a[href|title|target|rel],'
    .'img[src|alt|width|height|class],'
    .'figure[class],figcaption,'
    .'div[class],'
    .'table,thead,tbody,tr,th,td,'
    .'iframe[src|width|height|frameborder|allowfullscreen]',
'Attr.AllowedClasses' => [
    'alignleft','alignright','aligncenter','alignnone',
    'has-text-align-left','has-text-align-center','has-text-align-right',
    'size-thumbnail','size-medium','size-large','size-full',
    'is-resized','wp-block-image','wp-block-media-text',
    'colunas','coluna',   // grid de colunas convertido (Task 3)
],
```

- [ ] **Step 3: Rodar** `--filter=SanitizacaoBlogTest` → PASS. Commit `feat(blog): purifier preserva classes de imagem (allow-list)`.

---

## Task 3: TransformadorBlog — preservar tamanhos + converter colunas Gutenberg

**Files:** Modify `app/Importacao/TransformadorBlog.php`; confirmar a chamada em `app/Importacao/ImportadorBlog.php`/`ReescritorImagensConteudo.php`; Test `tests/Unit/Importacao/TransformadorBlogTest.php`.

**Interfaces — Produces:** `TransformadorBlog::limparGutenberg(?string $html): string` — remove comentários `<!-- wp:… -->`, remove wrappers `jet-sm-gb-*`, **preserva** `wp-block-image`/`size-*`/`aligncenter` e **converte** `wp-block-columns`→`<div class="colunas">` e `wp-block-column`(+`style="flex-basis:…"`)→`<div class="coluna">` (descartando o `flex-basis` inline). Se já houver limpeza equivalente embutida no importador, refatorar para este método.

- [ ] **Step 1: Teste (falha primeiro)**:

```php
public function test_limpar_gutenberg_converte_colunas_e_preserva_tamanho(): void
{
    $html = '<!-- wp:columns --><div class="wp-block-columns are-vertically-aligned-center">'
        .'<!-- wp:column {"width":"33.33%"} --><div class="wp-block-column" style="flex-basis:33.33%">'
        .'<figure class="wp-block-image size-large"><img src="/x.jpg" alt="" class="wp-image-1"/></figure>'
        .'</div><!-- /wp:column --></div><!-- /wp:columns -->';

    $out = TransformadorBlog::limparGutenberg($html);

    $this->assertStringNotContainsString('wp:columns', $out);     // comentários removidos
    $this->assertStringNotContainsString('flex-basis', $out);     // sem style inline
    $this->assertStringContainsString('class="colunas"', $out);   // grid limpo
    $this->assertStringContainsString('coluna', $out);
    $this->assertStringContainsString('size-large', $out);        // tamanho preservado
    $this->assertStringContainsString('wp-block-image', $out);
}
```

- [ ] **Step 2: Implementar** `limparGutenberg()`: (1) `preg_replace` dos comentários `~<!--\s*/?wp:.*?-->~s` → ''; (2) `preg_replace` removendo a classe-token `jet-sm-gb-\S+` dos atributos `class`; (3) converter colunas: `wp-block-columns` → `colunas` e `wp-block-column` → `coluna` na classe, e remover `style="flex-basis:…"` dos divs de coluna (`~\sstyle="[^"]*flex-basis[^"]*"~`); manter as demais classes da allow-list. Ordem: cleanup → conversão. Aplicar este método ao `conteudo` **antes** da reescrita de imagens e da atribuição no model (a sanitização final pelo mutator mantém só as classes da allow-list da Task 2).

- [ ] **Step 3: Rodar** `--filter=TransformadorBlogTest` → PASS. Commit `feat(blog): preserva tamanhos e converte colunas Gutenberg na importação`.

---

## Task 4: Extensão completa — atributo `size` (largura %) + tools de alinhamento e tamanho

**Files:** Modify `resources/js/filament/imagem-alinhada.js`, `app/Filament/RichContent/TipTap/ImagemExtension.php`, `app/Filament/RichContent/Plugins/ImagemPlugin.php`; Test `tests/Feature/Filament/RichEditorImagemRoundtripTest.php`.

**Interfaces — Produces:** atributo `size` no nó `image` → classe `size-medium|size-large|size-full` (mapa %, ver CSS Task 6); comandos `definirAlinhamentoImagem(left|right|center|none)` e `definirTamanhoImagem(medium|large|full)`; tools `imagemAlinhar{Esquerda,Centro,Direita}` e `imagemTamanho{Medio,Grande,Total}`.

- [ ] **Step 1: Teste (falha primeiro)** — round-trip de `size` (espelha o teste do `align` da Task 1): doc com `image` + `size:'large'` → HTML com `class="...size-large..."`. (E que align+size coexistem: `alignleft size-large`.)

- [ ] **Step 2: JS** — adicionar o atributo `size` (mesmo padrão de `addGlobalAttributes` do `align`, mapa `{ medium:'size-medium', large:'size-large', full:'size-full' }`) + comando `definirTamanhoImagem`. Preservar múltiplas classes (align + size) — no `renderHTML`, retornar `{ class: [classeAlign, classeSize].filter(Boolean).join(' ') }` combinando ambos (cuidar para não sobrescrever: usar uma única `renderHTML` por atributo; o TipTap mescla `class`).

- [ ] **Step 3: PHP** — adicionar o atributo `size` ao `ImagemExtension` (mesmo padrão do `align`). Garantir que `align` + `size` saem juntos na `class` (o tiptap-php mescla os `renderHTML` de atributos; se não mesclar, combinar num único atributo que computa a classe final).

- [ ] **Step 4: Plugin** — `getEditorTools()` retorna os 6 tools (3 align + 3 size) com `->icon()` e `->jsHandler('$getEditor()?.chain().focus().definir...Imagem("...").run()')`. `getTipTapJsExtensions()`/`getTipTapPhpExtensions()` inalterados.

- [ ] **Step 5: PostResource** — `toolbarButtons` inclui um grupo com os 6 nomes de tool.

- [ ] **Step 6: Rodar** o teste de round-trip → PASS. `npm run build`. Commit `feat(blog): extensão de imagem (alinhamento + tamanho %) completa`.

---

## Task 5: CSS público do conteúdo (`.conteudo-artigo`)

**Files:** Create `resources/css/conteudo.css`; Modify `resources/css/app.css` (import) e `resources/views/blog/show.blade.php` (classe no wrapper); Test `tests/Feature/Front/BlogConteudoResponsivoTest.php`.

- [ ] **Step 1: `resources/css/conteudo.css`** — copiar verbatim o bloco do spec §"CSS do conteúdo (referência)" (clearfix; `img{max-width:100%;height:auto}`; `.alignleft/.alignright/.aligncenter/.alignnone` com float/margens; `h2,h3{clear:both}`; `figure`/`figcaption`; `size-thumbnail/medium/large/full` com `max-width` em **%** — ajustar os valores do spec que estão em px para %: `size-medium → max-width:50%`, `size-large → max-width:75%`, `size-full → max-width:100%`; `img.is-resized → max-width:60%`; responsivo `@media (max-width:640px)` empilha com `width:100%!important`). Acrescentar o grid de colunas convertido:

```css
.conteudo-artigo .colunas { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: center; margin: 1.5rem 0; }
.conteudo-artigo .colunas .coluna { min-width: 0; }
@media (max-width: 640px) { .conteudo-artigo .colunas { grid-template-columns: 1fr; } }
```

Trocar o cinza da legenda (`#6b7280`) pelo token do design (`color: #7A8A8A;` ou `var(--color-text-muted)`).

- [ ] **Step 2: Importar a folha** — em `resources/css/app.css`, após o Tailwind, `@import './conteudo.css';` (ou colar o conteúdo num `@layer components`). Confirmar que o Vite a inclui.

- [ ] **Step 3: Wrapper no single** — em `resources/views/blog/show.blade.php`, o `div` que renderiza `{!! $post->conteudo !!}` recebe a classe **`conteudo-artigo`** (somar à `prose-blog` existente).

- [ ] **Step 4: Teste de saída responsiva** — `BlogConteudoResponsivoTest`: post publicado cujo `conteudo` tem `<figure class="wp-block-image size-large alignleft"><img ...></figure>`; `get('/sementeira/{slug}')` → 200, **vê** `conteudo-artigo`, **vê** `alignleft` e `size-large`, e o HTML **não contém** `style="width` nem `px"` no corpo do artigo (garante "nunca px fixo / sem style inline").

- [ ] **Step 5:** `npm run build`; rodar `--filter=BlogConteudoResponsivoTest` → PASS. Commit `feat(blog): CSS do conteúdo (alinhamento/tamanho/colunas, responsivo)`.

---

## Task 6: Reimportar os 45 posts + verificação

**Files:** (nenhum novo) — execução + verificação manual.

- [ ] **Step 1: Suíte completa** — `docker compose exec -T app php artisan test` → tudo verde.
- [ ] **Step 2: Reimport (túnel SSH ativo)** — `docker compose exec -T app php artisan cema:importar-blog` (idempotente). Conferir contagem (45) e ausência de erros.
- [ ] **Step 3: Verificar no banco** — amostrar 2–3 posts que tinham colunas/`size-*` no legado: `Post::where('slug',...)->value('conteudo')` deve conter `class="colunas"`/`coluna`, `size-large`/`size-full`, `wp-block-image`, e **não** conter `flex-basis`, `wp:` ou `style="`.
- [ ] **Step 4: Verificação visual (localhost)** — (a) criar um post novo no `/admin` com imagem **à esquerda do texto** + **redimensionada** (tamanho médio); abrir no `/sementeira/{slug}` e confirmar o texto contornando + responsivo (encolher a janela < 640px → empilha full-width). (b) Abrir 2–3 posts migrados com colunas e confirmar imagem-ao-lado-de-texto.

---

## Verificação final
- `php artisan test` verde (incluindo os novos testes de sanitização, transformador e conteúdo responsivo); nenhum teste pré-existente quebrado.
- Editor: alinhar/redimensionar imagem grava **classes** (sem px/sem style inline); round-trip provado.
- Migrado: tamanhos + colunas preservados após reimport; público fiel.

## Riscos / pontos de atenção
- **Round-trip PHP (Task 1):** o ponto mais incerto — o tiptap-php precisa renderizar `align`/`size` como classe. O spike decide subclasse vs `addGlobalAttributes`; **gate** antes de seguir.
- **Merge de `class`:** align + size na mesma `<img>` — garantir que os dois `renderHTML` somam classes (não sobrescrevem), tanto no JS quanto no PHP.
- **Asset JS:** estático (importa do global do Filament) → sem build Vite; servido por `FilamentAsset::register(Js::make(...)->loadedOnRequest())`. Conferir a URL via `getScriptSrc('imagem-alinhada','app')`.
- **CSS de tamanho em %:** os valores do spec estão em px (fallback); para a saída nova usar % (decisão "nunca px fixo").
- **Reimport** depende do túnel SSH ativo; idempotente (upsert por slug) — seguro.
