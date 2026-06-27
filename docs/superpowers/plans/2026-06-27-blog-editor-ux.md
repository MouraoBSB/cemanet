# Ajustes de UX do editor do blog (Sementeira de Luz) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolver os 6 itens de UX do editor RichEditor do blog (preview de tamanho/alinhamento de imagem no canvas, toolbar fixa, salvar acessível, botão Parágrafo, cursor visível, alinhamento de texto + justificado por padrão) — tudo por CLASSES, sem `style` inline, sem tocar no conteúdo migrado nem no render do front.

**Architecture:** O RichEditor do Filament 5 é TipTap v3 (JS) + ueberdosis/tiptap-php (servidor). O blog já tem uma extensão JS (`imagem-alinhada.js`) + espelho PHP (`ImagemAtributosExtension`) que adiciona `align`/`size` ao nó `image` por classes WP, orquestrados pelo `ImagemPlugin` (RichContentPlugin) e listados no `toolbarButtons` do `PostResource`. Este plano: (a) injeta CSS no **canvas do editor** via `FilamentAsset::register(Css::make(...))` (publicado por `php artisan filament:assets`) para dar preview WYSIWYG; (b) reaproveita ferramentas **nativas** do Filament 5 (`paragraph`, `alignStart/alignCenter/alignEnd/alignJustify`) que já existem mas não estão no toolbar; (c) **substitui** a extensão `textAlign` padrão do Filament (que emite `style` inline) por uma extensão própria de mesmo `name` que emite classes `has-text-align-*`; (d) usa o sticky **nativo** de form actions para o "salvar acessível". O front renderiza `{!! $post->conteudo !!}` (sanitizado pelo mutator do model via `clean($v,'conteudo_blog')`) — inalterado, exceto ampliar a allow-list e o CSS de `.conteudo-artigo`.

**Tech Stack:** PHP 8.3 · Laravel 13 · Filament 5.6.7 · Livewire 4 · Tailwind v4 · Vite · TipTap v3 · mews/purifier (HTMLPurifier) · Docker.

## Global Constraints

- **Saída SEMPRE por CLASSES, nunca `style` inline.** O HTMLPurifier não permite `style` no perfil `conteudo_blog` e não consegue limitar `width`/`text-align` a valores seguros.
- **Não alterar o conteúdo já migrado** nem o pipeline de render do front (`{!! $post->conteudo !!}` + mutator do `App\Models\Post`). Só ampliar a allow-list do purifier e o CSS de `.conteudo-artigo`.
- **Extensões JS do TipTap** leem o TipTap do global `window.FilamentRichEditor.tiptap.core` (que expõe **só** `core`, `pmState`, `pmView`, `pmModel`). **Proibido** `import` de pacotes `@tiptap/*` (não são processados pelo Vite — o arquivo é servido cru via `FilamentAsset`). Padrão: `const { Extension } = window.FilamentRichEditor.tiptap.core` + `export default Extension.create({...})`. Modelo: `resources/js/filament/imagem-alinhada.js`.
- **CSS do canvas do admin** é registrado por `FilamentAsset::register([Css::make('id', resource_path('css/filament/editor.css'))])` e publicado com `php artisan filament:assets` (CSS **puro**, sem Tailwind/@apply, sem `var(--token)` do front — o painel não tem esses tokens). Escopar ao editor do blog via classe `editor-conteudo-blog` (aplicada no campo por `->extraAttributes(['class' => 'editor-conteudo-blog'])`) para **não** afetar outros RichEditors do admin.
- **Topbar do Filament**: `min-h-16` = **4rem (64px)**, `z-index: 30`. Toolbar sticky deve usar `top: 4rem` e `z-index: 20` (abaixo do topbar, acima do conteúdo).
- **Idioma pt-BR** em tudo (identificadores de domínio, comentários, rótulos). **Cabeçalho de autoria** em arquivos novos: `// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27` (ou `{{-- ... --}}`/`<?php // ...`).
- **Testes**: `docker compose exec -T app php artisan test`. **Assets Filament**: `docker compose exec -T app php artisan filament:assets`. **Build do front**: `npm run build` (no host).
- **Commits atômicos** por task; trabalhar na branch `fase-2-blog-editor-ux` (já criada a partir de `main`).
- **Rótulos distintos**: as ferramentas de alinhamento de **imagem** recebem rótulo `Imagem: …`; as de **texto** são as nativas do Filament (`Alinhar …`) — para não repetir a confusão atual.

---

### Task 1: Justificado por padrão + hifenização + esquerda no mobile (Item 6, parte CSS — front)

**Files:**
- Modify: `resources/css/conteudo.css` (acrescentar regras; o front já tem `<html lang="pt-BR">` em `resources/views/components/layout/app.blade.php:8`, então `hyphens:auto` já funciona)

**Interfaces:**
- Consumes: `.conteudo-artigo` (wrapper do conteúdo no single, `resources/views/blog/show.blade.php:229`).
- Produces: classes `has-text-align-left|center|right|justify` estilizadas no front (consumidas pela Task 6).

- [ ] **Step 1: Adicionar o bloco de alinhamento de texto ao final do `conteudo.css` (antes do `@media`)**

Inserir logo após a regra `.conteudo-artigo .colunas .coluna { min-width: 0; }` (linha ~118), **antes** do bloco `@media (max-width: 640px)`:

```css
/* Alinhamento de texto (parágrafos e títulos) — por classes WP/Gutenberg.
   Justificado é o PADRÃO do corpo; classes explícitas sobrepõem. */
.conteudo-artigo p {
    text-align: justify;
    hyphens: auto;
}

.conteudo-artigo .has-text-align-left {
    text-align: left;
    hyphens: manual;
}

.conteudo-artigo .has-text-align-center {
    text-align: center;
    hyphens: manual;
}

.conteudo-artigo .has-text-align-right {
    text-align: right;
    hyphens: manual;
}

.conteudo-artigo .has-text-align-justify {
    text-align: justify;
    hyphens: auto;
}
```

- [ ] **Step 2: No `@media (max-width: 640px)` existente, reverter o justify-padrão para esquerda (sem mexer em alinhamentos explícitos)**

Dentro do bloco `@media (max-width: 640px)` já existente (linha ~121), acrescentar:

```css
    /* Mobile: justificado prejudica leitura em tela estreita — volta à esquerda,
       mas só nos parágrafos SEM alinhamento explícito. */
    .conteudo-artigo p:not([class*="has-text-align"]) {
        text-align: left;
        hyphens: manual;
    }
```

- [ ] **Step 3: Build do front e verificação manual**

Run: `npm run build`
Abrir um post migrado em `http://localhost:8000/...` (Ctrl+Shift+R). Esperado: corpo aparece **justificado** com hifenização; em viewport `<640px` os parágrafos voltam a alinhar à **esquerda**. Conferir que títulos e legendas não ficaram justificados de forma estranha.

- [ ] **Step 4: Commit**

```bash
git add resources/css/conteudo.css
git commit -m "feat(blog): corpo do artigo justificado por padrão (hifenização; esquerda no mobile)"
```

---

### Task 2: Fundação do CSS do canvas + cursor visível (Item 5)

**Files:**
- Create: `resources/css/filament/editor.css`
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (registrar o CSS via `FilamentAsset`)
- Modify: `app/Filament/Resources/Posts/PostResource.php:77-104` (adicionar `->extraAttributes(['class' => 'editor-conteudo-blog'])` no `RichEditor::make('conteudo')`)
- Test: `tests/Feature/Filament/EditorAdminAssetsTest.php`

**Interfaces:**
- Consumes: padrão de registro de asset de `AdminPanelProvider::boot()` (já registra `Js::make('imagem-alinhada', ...)`).
- Produces: classe CSS-scope `.editor-conteudo-blog` no campo do editor; arquivo `editor.css` publicado em `public/css/app/editor.css` (consumido/estendido pelas Tasks 3, 6 e 8); asset Filament id `cema-editor`.

- [ ] **Step 1: Escrever o teste de registro do asset**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace Tests\Feature\Filament;

use Filament\Support\Facades\FilamentAsset;
use Tests\TestCase;

class EditorAdminAssetsTest extends TestCase
{
    public function test_css_do_editor_esta_registrado_no_filament(): void
    {
        // O AdminPanelProvider::boot() roda no bootstrap da app de teste.
        $href = FilamentAsset::getStyleHref('cema-editor', 'app');

        $this->assertNotEmpty($href);
        $this->assertStringContainsString('editor', $href);
    }

    public function test_arquivo_fonte_do_css_do_editor_existe_com_caret_color(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        $this->assertStringContainsString('caret-color', $css);
        $this->assertStringContainsString('editor-conteudo-blog', $css);
    }
}
```

- [ ] **Step 2: Rodar o teste e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: FAIL (asset não registrado / arquivo inexistente).

- [ ] **Step 3: Criar `resources/css/filament/editor.css`**

```css
/* CSS injetado SOMENTE no canvas do RichEditor do blog (escopo .editor-conteudo-blog)
   para dar preview WYSIWYG e cursor visível. Espelha .conteudo-artigo do front.
   Publicado via `php artisan filament:assets`. CSS puro (sem Tailwind/tokens do front).
   Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27 */

/* Cursor (caret) sempre visível na cor da marca; gapcursor (entre blocos) visível. */
.editor-conteudo-blog .tiptap.ProseMirror {
    caret-color: #4e4483;
}

.editor-conteudo-blog .ProseMirror-gapcursor:after {
    border-top: 2px solid #4e4483;
    width: 1.25rem;
}
```

- [ ] **Step 4: Registrar o CSS no `AdminPanelProvider::boot()`**

Em `app/Providers/Filament/AdminPanelProvider.php`, importar `Filament\Support\Assets\Css` e acrescentar o `Css::make` ao `FilamentAsset::register([...])` existente:

```php
use Filament\Support\Assets\Css;
// ...
        FilamentAsset::register([
            Js::make('imagem-alinhada', resource_path('js/filament/imagem-alinhada.js'))
                ->loadedOnRequest(),
            Css::make('cema-editor', resource_path('css/filament/editor.css')),
        ], package: 'app');
```

- [ ] **Step 5: Escopar o editor do blog com a classe `editor-conteudo-blog`**

Em `app/Filament/Resources/Posts/PostResource.php`, no `RichEditor::make('conteudo')`, acrescentar (após `->columnSpanFull()` da linha 104, ou junto às demais chamadas):

```php
                        ->extraAttributes(['class' => 'editor-conteudo-blog'])
```

- [ ] **Step 6: Publicar os assets e rodar os testes**

Run: `docker compose exec -T app php artisan filament:assets`
Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: PASS.

- [ ] **Step 7: Verificação manual**

Abrir `http://localhost:8000/admin/posts/create`. Esperado: o caret pisca visível (roxo) ao digitar; clicar **antes/depois de uma imagem que ocupa a linha** mostra um ponto de inserção (gapcursor). Confirmar que o editor de `palestras` (se houver RichEditor lá) **não** mudou (escopo `.editor-conteudo-blog`).

- [ ] **Step 8: Commit**

```bash
git add resources/css/filament/editor.css app/Providers/Filament/AdminPanelProvider.php app/Filament/Resources/Posts/PostResource.php tests/Feature/Filament/EditorAdminAssetsTest.php
git commit -m "feat(blog/editor): CSS no canvas do editor + caret/gapcursor visíveis (item 5)"
```

---

### Task 3: Preview de tamanho/alinhamento de imagem no editor (Item 1)

**Files:**
- Modify: `resources/css/filament/editor.css` (acrescentar regras de imagem escopadas ao canvas)
- Modify: `resources/js/filament/imagem-alinhada.js` (acrescentar `isActive` via atributo para estado ativo das tools — opcional, ver Step 4)
- Modify: `app/Filament/RichContent/Plugins/ImagemPlugin.php` (acrescentar `->activeJsExpression(...)` às 6 tools de imagem)
- Test: `tests/Feature/Filament/EditorAdminAssetsTest.php` (acrescentar asserção)

**Interfaces:**
- Consumes: `editor.css` e a classe `.editor-conteudo-blog` (Task 2); classes `alignleft/alignright/aligncenter/alignnone` e `size-medium/size-large/size-full`/`is-resized` (já geradas pela extensão `imagem-alinhada.js`).
- Produces: preview visual no canvas; estado ativo nas tools de imagem.

- [ ] **Step 1: Acrescentar asserção do CSS de preview ao teste existente**

Em `tests/Feature/Filament/EditorAdminAssetsTest.php`, novo método:

```php
    public function test_css_do_editor_espelha_alinhamento_e_tamanho_de_imagem(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        // Escopadas ao canvas do blog, espelhando .conteudo-artigo do front.
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap .alignleft', $css);
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap .size-medium', $css);
        $this->assertStringContainsString('.editor-conteudo-blog .tiptap img.is-resized', $css);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: FAIL no novo método.

- [ ] **Step 3: Acrescentar as regras de imagem ao `editor.css`** (espelham `conteudo.css`, escopadas ao canvas)

```css
/* Preview de alinhamento/tamanho de imagem dentro do editor (item 1). */
.editor-conteudo-blog .tiptap {
    overflow-wrap: anywhere;
}

.editor-conteudo-blog .tiptap::after {
    content: "";
    display: block;
    clear: both;
}

.editor-conteudo-blog .tiptap .alignleft {
    float: left;
    margin: 0.25rem 1.5rem 1rem 0;
}

.editor-conteudo-blog .tiptap .alignright {
    float: right;
    margin: 0.25rem 0 1rem 1.5rem;
}

.editor-conteudo-blog .tiptap .aligncenter {
    display: block;
    float: none;
    clear: both;
    margin-left: auto;
    margin-right: auto;
}

.editor-conteudo-blog .tiptap .alignnone {
    float: none;
}

.editor-conteudo-blog .tiptap .size-thumbnail { max-width: 25%; }
.editor-conteudo-blog .tiptap .size-medium { max-width: 50%; }
.editor-conteudo-blog .tiptap .size-large { max-width: 75%; }
.editor-conteudo-blog .tiptap .size-full { max-width: 100%; }
.editor-conteudo-blog .tiptap img.is-resized { max-width: 60%; }
```

- [ ] **Step 4: Estado ativo nas tools de imagem (feedback visual ao selecionar)**

Em `app/Filament/RichContent/Plugins/ImagemPlugin.php`, acrescentar `->activeJsExpression(...)` a cada tool (o atributo já existe no nó `image`):

```php
            RichEditorTool::make('imagemAlinharEsquerda')
                ->label('Imagem: alinhar à esquerda')
                ->icon(Heroicon::Bars3BottomLeft)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("left").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { align: "left" })'),

            RichEditorTool::make('imagemAlinharCentro')
                ->label('Imagem: alinhar ao centro')
                ->icon(Heroicon::Bars3)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("center").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { align: "center" })'),

            RichEditorTool::make('imagemAlinharDireita')
                ->label('Imagem: alinhar à direita')
                ->icon(Heroicon::Bars3BottomRight)
                ->jsHandler('$getEditor()?.chain().focus().definirAlinhamentoImagem("right").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { align: "right" })'),

            RichEditorTool::make('imagemTamanhoMedio')
                ->label('Imagem: tamanho médio')
                ->icon(Heroicon::Photo)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("medium").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { size: "medium" })'),

            RichEditorTool::make('imagemTamanhoGrande')
                ->label('Imagem: tamanho grande')
                ->icon(Heroicon::ArrowsPointingOut)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("large").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { size: "large" })'),

            RichEditorTool::make('imagemTamanhoTotal')
                ->label('Imagem: tamanho real')
                ->icon(Heroicon::ArrowsPointingOutSolid)
                ->jsHandler('$getEditor()?.chain().focus().definirTamanhoImagem("full").run()')
                ->activeJsExpression('$getEditor()?.isActive("image", { size: "full" })'),
```

Nota: `ArrowsPointingOutSolid` desfaz o ícone duplicado entre "grande" e "real"; se o enum `Heroicon` não tiver a variante Solid, usar outro ícone distinto disponível (ex.: `Heroicon::ArrowsPointingIn` para "real"/100% — o implementador confirma o enum em `vendor/filament/support/src/Icons/Heroicon.php` e escolhe um ícone existente e distinto).

- [ ] **Step 5: Publicar assets, rodar testes**

Run: `docker compose exec -T app php artisan filament:assets`
Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: PASS.

- [ ] **Step 6: Verificação manual**

Em `http://localhost:8000/admin/posts/{id}/edit`, **selecionar** uma imagem do corpo e clicar Pequena/Média/Grande/Real → a imagem **redimensiona na hora** no editor; clicar alinhar esquerda/direita → **float com texto contornando** já no editor; a tool ativa fica destacada. Salvar e conferir no front que o resultado bate.

- [ ] **Step 7: Commit**

```bash
git add resources/css/filament/editor.css app/Filament/RichContent/Plugins/ImagemPlugin.php tests/Feature/Filament/EditorAdminAssetsTest.php
git commit -m "feat(blog/editor): preview de tamanho/alinhamento de imagem no canvas + estado ativo (item 1)"
```

---

### Task 4: Botão de Parágrafo (P) na toolbar (Item 4)

**Files:**
- Modify: `app/Filament/Resources/Posts/PostResource.php:82-103` (acrescentar `'paragraph'` ao `toolbarButtons`)
- Test: `tests/Feature/Filament/PostResourceTest.php` (acrescentar asserção do schema)

**Interfaces:**
- Consumes: tool nativa `paragraph` do Filament 5 (`RichEditor.php:193-197`, `setParagraph()`, ícone `fi-o-paragraph`, com estado ativo nativo).
- Produces: botão Parágrafo no toolbar.

- [ ] **Step 1: Teste — o toolbar inclui `paragraph` na ordem de formato**

Em `tests/Feature/Filament/PostResourceTest.php`, novo teste (extrair o `RichEditor` do schema do form e checar `getToolbarButtons()`):

```php
    public function test_toolbar_do_editor_inclui_botao_paragrafo(): void
    {
        $componente = $this->localizarRichEditorConteudo(); // helper já existente ou criar

        $this->assertContains('paragraph', $componente->getToolbarButtons());
    }
```

Se não houver helper, usar a montagem do form do Resource: `PostResource::form(...)` / `Schema` e localizar o componente `conteudo` (seguir o padrão dos testes existentes em `PostResourceTest`). O essencial: asserir que `'paragraph'` está na lista de `toolbarButtons`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: FAIL (paragraph ausente).

- [ ] **Step 3: Acrescentar `'paragraph'` ao `toolbarButtons`** (agrupado com os formatos de bloco, antes de `h2`)

```php
                        ->toolbarButtons([
                            'attachFiles',
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'paragraph',
                            'h2',
                            'h3',
                            'italic',
                            // ... (resto inalterado)
                        ])
```

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: PASS.

- [ ] **Step 5: Verificação manual**

No editor, marcar um trecho como H2/H3 e clicar **Parágrafo (P)** → volta a texto normal; o botão de formato ativo fica destacado.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Posts/PostResource.php tests/Feature/Filament/PostResourceTest.php
git commit -m "feat(blog/editor): botão Parágrafo (P) na toolbar (item 4)"
```

---

### Task 5: Purifier libera alinhamento de texto por classe (Item 6, parte servidor)

**Files:**
- Modify: `config/purifier.php` (perfil `conteudo_blog`: `HTML.Allowed`, `Attr.AllowedClasses`, `custom_definition.rev`)
- Test: `tests/Feature/Models/SanitizacaoBlogTest.php` (acrescentar asserções)

**Interfaces:**
- Consumes: o mutator de `conteudo` do `App\Models\Post` chama `clean($v, 'conteudo_blog')`.
- Produces: persistência das classes `has-text-align-*` em `p`/`h2`/`h3`/`h4` (consumida pela Task 6).

- [ ] **Step 1: Testes — `has-text-align-justify` sobrevive em parágrafo/título; classe fora da lista é removida**

Em `tests/Feature/Models/SanitizacaoBlogTest.php`:

```php
    public function test_conteudo_preserva_alinhamento_de_texto_por_classe(): void
    {
        $post = Post::factory()->make([
            'conteudo' => '<p class="has-text-align-justify">Justificado.</p>'
                . '<h2 class="has-text-align-center">Centro</h2>'
                . '<p class="has-text-align-right">Direita</p>',
        ]);

        $html = $post->conteudo;

        $this->assertStringContainsString('has-text-align-justify', $html);
        $this->assertStringContainsString('has-text-align-center', $html);
        $this->assertStringContainsString('has-text-align-right', $html);
    }

    public function test_conteudo_remove_classe_de_paragrafo_fora_da_allowlist(): void
    {
        $post = Post::factory()->make([
            'conteudo' => '<p class="classe-maliciosa has-text-align-left">x</p>',
        ]);

        $html = $post->conteudo;

        $this->assertStringNotContainsString('classe-maliciosa', $html);
        $this->assertStringContainsString('has-text-align-left', $html);
    }
```

(Se o factory não preencher `conteudo` por padrão, usar `->make([...])` e acessar o atributo já sanitizado pelo mutator, como nos testes existentes do arquivo.)

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=SanitizacaoBlogTest`
Esperado: FAIL (`p`/`h2` perdem `class`; `has-text-align-justify` não está na allow-list).

- [ ] **Step 3: Liberar `class` em `p`/`h2`/`h3`/`h4` no `HTML.Allowed`**

Em `config/purifier.php`, perfil `conteudo_blog`, trocar o início de `HTML.Allowed`:

De:
```
'p,br,b,strong,i,em,u,s,h2,h3,h4,h5,ul,ol,li,blockquote,...'
```
Para (acrescentar `[class]` em `p,h2,h3,h4`):
```
'p[class],br,b,strong,i,em,u,s,h2[class],h3[class],h4[class],h5,ul,ol,li,blockquote,a[href|title|target|rel],img[src|alt|width|height|class|data-id],figure[class],figcaption,div[class],table,thead,tbody,tr,th,td,iframe[src|width|height|frameborder|allowfullscreen]'
```

- [ ] **Step 4: Acrescentar `has-text-align-justify` à `Attr.AllowedClasses`**

```php
            'Attr.AllowedClasses' => [
                // Alinhamento clássico do WordPress
                'alignleft', 'alignright', 'aligncenter', 'alignnone',
                // Alinhamento Gutenberg (texto)
                'has-text-align-left', 'has-text-align-center', 'has-text-align-right', 'has-text-align-justify',
                // Tamanhos de imagem do WordPress
                'size-thumbnail', 'size-medium', 'size-large', 'size-full',
                // Blocos Gutenberg de imagem
                'is-resized', 'wp-block-image', 'wp-block-media-text',
                // Layout de colunas personalizado
                'colunas', 'coluna',
            ],
```

- [ ] **Step 5: Incrementar `custom_definition.rev` (2 → 3)** para invalidar o cache serializado do HTMLPurifier

Em `config/purifier.php`, `settings.default.custom_definition.rev`: `'rev' => 3,`.

- [ ] **Step 6: Limpar cache do purifier e rodar os testes**

Run: `docker compose exec -T app sh -lc 'rm -rf storage/app/purifier/* 2>/dev/null; php artisan test --filter=SanitizacaoBlogTest'`
Esperado: PASS. Rodar também `--filter=SanitizacaoHtmlTest` para garantir que o perfil `conteudo` (palestras) **continua** sem permitir classes em `p` (não-regressão).

- [ ] **Step 7: Commit**

```bash
git add config/purifier.php tests/Feature/Models/SanitizacaoBlogTest.php
git commit -m "feat(blog/editor): purifier libera alinhamento de texto por classe (has-text-align-*, rev 3) (item 6)"
```

---

### Task 6: Alinhamento de texto por classes + tools nativas (Item 6, parte editor)

**Files:**
- Create: `resources/js/filament/texto-alinhado.js` (extensão TipTap que **substitui** a `textAlign` padrão)
- Create: `app/Filament/RichContent/Plugins/TextoAlinhamentoPlugin.php` (RichContentPlugin que registra a extensão JS)
- Modify: `app/Providers/Filament/AdminPanelProvider.php` (registrar `Js::make('texto-alinhado', ...)`)
- Modify: `app/Filament/Resources/Posts/PostResource.php` (`->plugins([...])` + `'alignStart','alignCenter','alignEnd','alignJustify'` no toolbar)
- Modify: `resources/css/filament/editor.css` (espelhar `has-text-align-*` e o justify-padrão no canvas)
- Test: `tests/Feature/Filament/PostResourceTest.php` (toolbar inclui as 4 tools de alinhamento de texto)

**Interfaces:**
- Consumes: tools nativas `alignStart/alignCenter/alignEnd/alignJustify` do Filament (`RichEditor.php:352-375`, chamam `setTextAlign('start'|'center'|'end'|'justify')` e leem `isActive({ textAlign })`); allow-list do purifier (Task 5); classes `has-text-align-*` do front (Task 1); mecanismo de substituição de extensão por `name` do Filament (`vendor/.../rich-editor/extensions.js:206-221`).
- Produces: HTML salvo com `has-text-align-*` (sem `style`), com preview WYSIWYG e estado ativo nas tools.

- [ ] **Step 1: Teste — o toolbar inclui as 4 ferramentas de alinhamento de texto**

```php
    public function test_toolbar_do_editor_inclui_alinhamento_de_texto(): void
    {
        $componente = $this->localizarRichEditorConteudo();
        $botoes = $componente->getToolbarButtons();

        foreach (['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'] as $tool) {
            $this->assertContains($tool, $botoes);
        }
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: FAIL.

- [ ] **Step 3: Criar a extensão JS `texto-alinhado.js`** (substitui a `textAlign` padrão; saída por classes)

```js
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Extensão TipTap: alinhamento de TEXTO (parágrafo/título) por classes WP.
// SUBSTITUI a extensão `textAlign` padrão do Filament (mesmo `name`), trocando a
// saída de `style` inline por CLASSES has-text-align-*. As ferramentas nativas do
// Filament (alignStart/alignCenter/alignEnd/alignJustify) chamam setTextAlign(...)
// e leem isActive({ textAlign }) — continuam funcionando contra este atributo.
// Carregada via RichContentPlugin::getTipTapJsExtensions(). Não rebundlar o TipTap.

const { Extension } = window.FilamentRichEditor.tiptap.core

// Alinhamento lógico do TipTap -> classe WP (start = padrão "justify", sem classe).
const CLASSES = {
    start:   'has-text-align-left',
    center:  'has-text-align-center',
    end:     'has-text-align-right',
    justify: 'has-text-align-justify',
}

const ALINHAMENTOS = Object.keys(CLASSES)

export default Extension.create({
    name: 'textAlign',

    addOptions() {
        return {
            types: ['heading', 'paragraph'],
            // Padrão = justify: o corpo já nasce justificado (CSS .conteudo-artigo p).
            // Assim clicar "justificar" não polui o HTML com classe redundante e
            // clicar "esquerda/centro/direita" emite a classe que sobrepõe o padrão.
            defaultAlignment: 'justify',
        }
    },

    addGlobalAttributes() {
        return [{
            types: this.options.types,
            attributes: {
                textAlign: {
                    default: this.options.defaultAlignment,
                    parseHTML: (el) => {
                        for (const [k, c] of Object.entries(CLASSES)) {
                            if (el.classList?.contains(c)) return k
                        }
                        return this.options.defaultAlignment
                    },
                    renderHTML: (attrs) => {
                        const a = attrs.textAlign
                        // Padrão (justify) não emite classe — o CSS do front cuida.
                        if (!a || a === this.options.defaultAlignment) return {}
                        return CLASSES[a] ? { class: CLASSES[a] } : {}
                    },
                },
            },
        }]
    },

    addCommands() {
        return {
            setTextAlign: (alignment) => ({ commands }) => {
                if (!ALINHAMENTOS.includes(alignment)) return false
                return this.options.types
                    .map((type) => commands.updateAttributes(type, { textAlign: alignment }))
                    .every(Boolean)
            },
        }
    },
})
```

- [ ] **Step 4: Criar o plugin `TextoAlinhamentoPlugin`**

```php
<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27

namespace App\Filament\RichContent\Plugins;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Support\Facades\FilamentAsset;

/**
 * Plugin RichContent: substitui a extensão `textAlign` padrão do Filament por uma
 * versão que emite CLASSES has-text-align-* (em vez de `style` inline). As tools de
 * alinhamento de texto são as NATIVAS do Filament (alignStart/Center/End/Justify),
 * apenas listadas no toolbarButtons do PostResource.
 */
class TextoAlinhamentoPlugin implements RichContentPlugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    /** @return array<\Tiptap\Core\Extension> */
    public function getTipTapPhpExtensions(): array
    {
        // Não é necessário espelho PHP: o conteúdo do blog é salvo como o HTML do
        // editor (classes) e sanitizado pelo mutator; o front renderiza o HTML cru.
        return [];
    }

    /** @return array<string> */
    public function getTipTapJsExtensions(): array
    {
        return [FilamentAsset::getScriptSrc('texto-alinhado', 'app')];
    }

    /** @return array<\Filament\Forms\Components\RichEditor\RichEditorTool> */
    public function getEditorTools(): array
    {
        return [];
    }

    /** @return array<\Filament\Actions\Action> */
    public function getEditorActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 5: Registrar o asset JS no `AdminPanelProvider::boot()`**

```php
        FilamentAsset::register([
            Js::make('imagem-alinhada', resource_path('js/filament/imagem-alinhada.js'))
                ->loadedOnRequest(),
            Js::make('texto-alinhado', resource_path('js/filament/texto-alinhado.js'))
                ->loadedOnRequest(),
            Css::make('cema-editor', resource_path('css/filament/editor.css')),
        ], package: 'app');
```

- [ ] **Step 6: Adicionar o plugin e as tools ao `PostResource`**

No `RichEditor::make('conteudo')`:
```php
                        ->plugins([
                            ImagemPlugin::make(),
                            TextoAlinhamentoPlugin::make(),
                        ])
```
E no `toolbarButtons`, acrescentar as 4 tools nativas (agrupadas, ex.: após `'underline'` e antes das de imagem):
```php
                            'alignStart',
                            'alignCenter',
                            'alignEnd',
                            'alignJustify',
```
(Importar `App\Filament\RichContent\Plugins\TextoAlinhamentoPlugin` no topo do arquivo.)

- [ ] **Step 7: Espelhar `has-text-align-*` + justify-padrão no canvas (`editor.css`)**

```css
/* Preview de alinhamento de texto no editor (item 6). Espelha o front. */
.editor-conteudo-blog .tiptap p {
    text-align: justify;
    hyphens: auto;
}

.editor-conteudo-blog .tiptap .has-text-align-left { text-align: left; hyphens: manual; }
.editor-conteudo-blog .tiptap .has-text-align-center { text-align: center; hyphens: manual; }
.editor-conteudo-blog .tiptap .has-text-align-right { text-align: right; hyphens: manual; }
.editor-conteudo-blog .tiptap .has-text-align-justify { text-align: justify; hyphens: auto; }
```

- [ ] **Step 8: Publicar assets, build do front, rodar testes**

Run: `docker compose exec -T app php artisan filament:assets`
Run: `npm run build`
Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: PASS.

- [ ] **Step 9: Verificação manual (round-trip)**

Em `http://localhost:8000/admin/posts/create`: digitar parágrafos; selecionar um e clicar **Centralizar**/**Justificar**/**Esquerda** → o texto realinha **no editor** (preview) e a tool ativa destaca. **Salvar**, reabrir o post → o alinhamento **persiste** (lê a classe de volta). Conferir no front que bate. Inspecionar o HTML salvo: deve conter `class="has-text-align-center"` e **nenhum** `style="text-align..."`.

- [ ] **Step 10: Commit**

```bash
git add resources/js/filament/texto-alinhado.js app/Filament/RichContent/Plugins/TextoAlinhamentoPlugin.php app/Providers/Filament/AdminPanelProvider.php app/Filament/Resources/Posts/PostResource.php resources/css/filament/editor.css tests/Feature/Filament/PostResourceTest.php
git commit -m "feat(blog/editor): alinhamento de texto por classes has-text-align-* + tools nativas (item 6)"
```

---

### Task 7: Salvar acessível sem rolar (Item 3)

**Files:**
- Modify: `app/Filament/Resources/Posts/Pages/EditPost.php`
- Modify: `app/Filament/Resources/Posts/Pages/CreatePost.php`
- Test: `tests/Feature/Filament/PostResourceTest.php` (asserção das páginas)

**Interfaces:**
- Consumes: mecanismo nativo `BasePage::$formActionsAreSticky` / `stickyFormActions()` do Filament (CSS `fi-sticky` → `position: fixed; bottom: 0`).
- Produces: rodapé de ações fixo nas páginas de criar/editar post.

- [ ] **Step 1: Teste — as páginas marcam as form actions como sticky**

```php
    public function test_paginas_de_post_tem_form_actions_sticky(): void
    {
        $refEdit = new \ReflectionProperty(\App\Filament\Resources\Posts\Pages\EditPost::class, 'formActionsAreSticky');
        $refCreate = new \ReflectionProperty(\App\Filament\Resources\Posts\Pages\CreatePost::class, 'formActionsAreSticky');

        $this->assertTrue($refEdit->getDefaultValue());
        $this->assertTrue($refCreate->getDefaultValue());
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: FAIL.

- [ ] **Step 3: Marcar sticky em `EditPost` e `CreatePost`**

Em ambos os arquivos, dentro da classe:
```php
    protected static bool $formActionsAreSticky = true;
```
(`CreatePost extends CreateRecord`, `EditPost extends EditRecord` — ambos herdam de `BasePage`, que declara a propriedade.)

- [ ] **Step 4: Rodar e ver passar**

Run: `docker compose exec -T app php artisan test --filter=PostResourceTest`
Esperado: PASS.

- [ ] **Step 5: Verificação manual**

Abrir um post longo no admin. Esperado: o botão **Salvar** fica fixo no rodapé (visível sem rolar até o fim). Salvar de qualquer ponto funciona. Conferir no mobile que o rodapé fixo não cobre conteúdo essencial.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/Posts/Pages/EditPost.php app/Filament/Resources/Posts/Pages/CreatePost.php tests/Feature/Filament/PostResourceTest.php
git commit -m "feat(blog/editor): salvar acessível com rodapé de ações fixo (item 3)"
```

---

### Task 8: Toolbar fixa (sticky) (Item 2)

**Files:**
- Modify: `resources/css/filament/editor.css` (sticky na toolbar do RichEditor)
- Test: `tests/Feature/Filament/EditorAdminAssetsTest.php` (asserção da regra sticky)

**Interfaces:**
- Consumes: `editor.css` + `.editor-conteudo-blog` (Task 2); seletor `.fi-fo-rich-editor-toolbar` (Filament); offset do topbar (4rem / z-30).
- Produces: toolbar do editor do blog acompanha a rolagem.

- [ ] **Step 1: Teste — `editor.css` tem a regra sticky da toolbar**

```php
    public function test_css_do_editor_fixa_a_toolbar(): void
    {
        $css = file_get_contents(resource_path('css/filament/editor.css'));

        $this->assertStringContainsString('.fi-fo-rich-editor-toolbar', $css);
        $this->assertStringContainsString('position: sticky', $css);
    }
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: FAIL.

- [ ] **Step 3: Acrescentar a regra sticky ao `editor.css`**

```css
/* Toolbar fixa acompanhando a rolagem (item 2). top = altura do topbar (4rem),
   z-index abaixo do topbar (z-30) e acima do conteúdo. */
.editor-conteudo-blog .fi-fo-rich-editor-toolbar {
    position: sticky;
    top: 4rem;
    z-index: 20;
    background: var(--fi-color-white, #fff);
}

.dark .editor-conteudo-blog .fi-fo-rich-editor-toolbar {
    background: #18181b;
}
```

(Se o canvas usar outra cor de fundo no tema, o implementador ajusta o `background` para casar; o essencial é não ficar transparente sobre o texto que rola por baixo.)

- [ ] **Step 4: Publicar assets, rodar testes**

Run: `docker compose exec -T app php artisan filament:assets`
Run: `docker compose exec -T app php artisan test --filter=EditorAdminAssetsTest`
Esperado: PASS.

- [ ] **Step 5: Verificação manual**

Em um post longo, rolar o editor: a barra de ferramentas **permanece visível** logo abaixo do topbar do Filament, sem sobrepor menus. Confirmar que não conflita com o rodapé sticky da Task 7.

- [ ] **Step 6: Commit**

```bash
git add resources/css/filament/editor.css tests/Feature/Filament/EditorAdminAssetsTest.php
git commit -m "feat(blog/editor): toolbar do editor fixa acompanhando a rolagem (item 2)"
```

---

## Verificação final (após as 8 tasks)

- [ ] Rodar a suíte completa: `docker compose exec -T app php artisan test` (tudo verde).
- [ ] `php artisan filament:assets` + `npm run build` aplicados.
- [ ] **Roteiro manual no localhost** (criar um post novo): justificar/centralizar um parágrafo (preview no editor + persiste no front); redimensionar/alinhar uma imagem (preview no editor); alternar Parágrafo/H2/H3; cursor visível ao redor de imagem (gapcursor); toolbar acompanha a rolagem; salvar pelo rodapé fixo sem rolar. Conferir 2–3 posts migrados (alinhamento e colunas intactos; nada do conteúdo migrado mudou).
- [ ] Conferir que o HTML salvo usa **classes** (`has-text-align-*`, `alignleft`, `size-*`) e **nunca** `style="..."`.

## Notas de deploy

- O `editor.css` e os JS de extensão são publicados por `php artisan filament:assets` — garantir que esse comando roda no build/deploy (Dockerfile/pipeline) junto com `npm run build` e `php artisan filament:assets` já existentes para a extensão de imagem.
