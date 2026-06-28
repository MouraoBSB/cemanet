# Blog — Editor (RichEditor): retorno visual de tamanho + barra fixa + salvar acessível

Feedback do dono testando o editor após a entrega da Media Library + alinhamento.
São **3 itens de UX do editor** (não alteram o conteúdo migrado nem o front).
Spec base: `2026-06-26-blog-sementeira-de-luz-design.md`. Backlog: `2026-06-26-blog-sementeira-ajustes.md`.

---

## 1. Tamanho da imagem não dá retorno visual no editor (prioridade alta)

**Comportamento atual:** ao selecionar uma imagem e clicar em **Pequena / Média / Grande /
Tamanho real**, **nada muda visualmente** no editor — a imagem continua igual.

**Esperado:** a imagem **redimensiona na hora dentro do editor**, fiel ao que será publicado.
O mesmo vale para o **alinhamento** (esquerda/direita/centro deveriam mostrar o float + o texto
contornando já no editor).

**Diagnóstico provável (para confirmar):** a extensão aplica o tamanho/alinhamento como
**classes WP** (`size-*`, `is-resized`, `alignleft/right/center`) — decisão "candidato B,
por classes, sem `style` inline". Mas o **canvas do editor** (ProseMirror / área de conteúdo
do RichEditor) **provavelmente não carrega o CSS** que estiliza essas classes. Resultado: a
classe entra no markup, mas o editor não a **renderiza** → "clico e não acontece nada".

**Perguntas ao agente:**
1. Ao clicar nos tamanhos, a **classe está sendo aplicada e salva** no HTML (ou seja, ao
   **publicar** sai do tamanho certo) e o que falta é só o **preview** no editor? Ou o clique
   **não aplica nada**? (Isso define se é só UX ou também bug funcional.)
2. Confirmar que os presets do editor usam **as mesmas classes** que o CSS público
   (`.conteudo-artigo`) já estiliza — para editor e front baterem.

**Direção sugerida (mantendo a decisão):** **não** voltar a `style` inline no HTML salvo —
em vez disso, **carregar no editor o mesmo CSS** que o front usa (espelhar as regras de
`.conteudo-artigo` — tamanhos `size-*`/`is-resized` e float `alignleft/right/center` —
escopadas à área de conteúdo do RichEditor). Assim o **preview no editor = resultado no front**.

**Critério de aceite:** clicar em cada tamanho **muda a imagem visivelmente** no editor; alinhar
à esquerda/direita mostra o **texto contornando** já no editor; o HTML salvo continua **por
classes** (sem `style` inline); front inalterado.

---

## 2. Barra de ferramentas fixa (acompanha a digitação/rolagem)

**Comportamento atual:** em post longo, ao digitar/rolar, a **barra de ferramentas some** no topo.

**Esperado:** a barra fica **fixa (sticky)** e **acompanha** enquanto edito — sempre acessível.

**Direção sugerida:** `position: sticky` no elemento da toolbar do RichEditor, com **offset
abaixo do header do Filament** e `z-index` adequado (não sobrepor menus). Confirmar se o
Filament 5 tem opção nativa para isso antes de CSS custom.

**Critério de aceite:** em um post longo, a toolbar permanece **visível** durante toda a edição.

---

## 3. Salvar acessível sem rolar até o fim

**Comportamento atual:** o botão **Salvar** fica no **rodapé** da página; em post longo, obriga a
rolar até o fim.

**Necessidade do dono:** poder **salvar a qualquer momento** sem descer até o rodapé —
idealmente **junto da barra** que acompanha a edição.

**Direção sugerida (forma idiomática do Filament):** tornar a ação de salvar **sempre visível** —
por exemplo, **ações de formulário fixas (sticky footer)** ou um **botão Salvar no cabeçalho**
da página. Colocar o botão **literalmente dentro da toolbar do editor** pode não ser idiomático
(a toolbar é do editor, o salvar é do formulário); o agente escolhe a forma **nativa mais limpa**
que entregue o objetivo (salvar à mão sem rolar). Se fizer sentido na arquitetura, os itens **2 e 3
podem virar uma única barra fixa** (ferramentas do editor + ação de salvar).

**Critério de aceite:** dá para **salvar de qualquer ponto** do post sem rolar até o rodapé.

---

## 4. Falta o botão de Parágrafo (P) na barra

**Comportamento atual:** a barra tem só **H2** e **H3** — não há **Parágrafo (P)** / texto normal.

**Problema:** depois de marcar um trecho como título, não há um botão claro para **voltar ao
parágrafo** normal; falta o controle explícito de "texto normal".

**Esperado:** um botão **Parágrafo (P)** (ou um **seletor de formato**: Parágrafo / Título 2 /
Título 3) que aplique o formato e **mostre o estado ativo**.

**Direção sugerida:** adicionar a tool de parágrafo (`setParagraph`) ao `toolbarButtons` do
RichEditor; idealmente um dropdown de formato. Confirmar suporte nativo no Filament 5 antes de custom.

**Critério de aceite:** dá para alternar entre **Parágrafo / H2 / H3** pela barra, com indicação
do formato ativo.

---

## 5. Cursor (caret) visível — principalmente ao redor de imagens

**Comportamento atual:** não dá para ver **onde o cursor está**; com imagem no meio do texto,
fica **confuso** saber onde o texto vai entrar.

**Esperado:** um **caret piscando, sempre visível**; e um **ponto de inserção visível ao redor de
imagens/blocos** (antes/depois de uma imagem que ocupa a linha inteira).

**Diagnóstico/direção:**
- Garantir que o **caret está visível** — checar `caret-color` no CSS do editor (pode estar
  transparente ou igual ao fundo).
- **Habilitar a extensão `Gapcursor` do TipTap** → mostra um cursor visível nos "vãos" onde
  normalmente não dá para clicar (ex.: **antes/depois de uma imagem de bloco**). É exatamente o
  que resolve o "fica confuso com imagem".
- Opcional: **`Dropcursor`** (mostra onde um item arrastado vai cair).

**Critério de aceite:** o cursor é **sempre visível** ao digitar; clicar **antes/depois de uma
imagem** mostra um ponto de inserção claro e o texto entra onde esperado.

---

## 6. Alinhamento de TEXTO (parágrafo) não existe — e justificar por padrão

**Comportamento atual:** os botões de alinhar na barra são de **IMAGEM** (o tooltip diz
**"Imagem alinhar centro"**) — agem sobre uma **imagem selecionada**, não sobre o parágrafo.
Por isso, selecionar um parágrafo e clicar **não faz nada**. **Não há alinhamento de texto**
(esquerda / centro / direita / justificado), nem no editor nem no front.

**Esperado:**
- Botões de **alinhamento de texto** próprios (esquerda / centro / direita / **justificado**),
  agindo no parágrafo/título, com preview no editor **e** no front.
- **Padrão:** o corpo já nascer **justificado** (sem marcar parágrafo a parágrafo).

**Direção sugerida:**
- Adicionar a extensão **`TextAlign` do TipTap** (alvos `paragraph` + `heading`) com **4 tools**
  na toolbar. É **distinta** da extensão de **imagem** que já existe — deixar **rótulos claros**
  ("Texto: alinhar…" vs "Imagem: alinhar…") para não confundir como hoje.
- **Justificado por padrão** via CSS: `.conteudo-artigo p { text-align: justify }` (vale p/
  migrados e novos, sem markup). **Legibilidade (obrigatório):** ligar **`hyphens: auto`** com
  `lang="pt-BR"` para evitar "rios" de espaço, e **voltar a esquerda no mobile** (`<640px`) —
  justificado em tela estreita prejudica leitura/acessibilidade.
- Garantir que o `text-align` **renderiza no editor** também (mesmo princípio do item 1: CSS no
  canvas do RichEditor).

**Critério de aceite:** selecionar um parágrafo e **justificar/centralizar** funciona no editor
e no front; por **padrão** o corpo aparece **justificado** (com hifenização; **esquerda no mobile**);
os rótulos distinguem alinhamento de **texto** vs de **imagem**.

---

## Observações

- Os três itens são **ajustes de UX do editor**; não tocam no conteúdo já migrado nem no
  pipeline de render do front.
- O item 1 é o mais importante (hoje a edição de tamanho é "às cegas").
- Registrar os três no backlog (`2026-06-26-blog-sementeira-ajustes.md`).
