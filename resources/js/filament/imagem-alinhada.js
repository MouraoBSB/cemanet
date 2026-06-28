// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Extensão TipTap: alinhamento e tamanho de imagem por classes WP.
// Importa o TipTap do Filament (não rebundlar).
// Carregada via RichContentPlugin::getTipTapJsExtensions().

const { Extension } = window.FilamentRichEditor.tiptap.core
const { NodeSelection } = window.FilamentRichEditor.tiptap.pmState

const CLASSES_ALIGN = {
    left:   'alignleft',
    right:  'alignright',
    center: 'aligncenter',
    none:   'alignnone',
}

const CLASSES_SIZE = {
    medium: 'size-medium',
    large:  'size-large',
    full:   'size-full',
}

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
                    // TipTap mescla classes de múltiplos atributos no mesmo elemento.
                    // Retornar { class } aqui não sobrescreve o `size` — o merge é feito
                    // pelo TipTap iterando todos os atributos do nó.
                    renderHTML: (attrs) => (attrs.align && CLASSES_ALIGN[attrs.align])
                        ? { class: CLASSES_ALIGN[attrs.align] }
                        : {},
                },
                size: {
                    default: null,
                    parseHTML: (el) => {
                        for (const [k, c] of Object.entries(CLASSES_SIZE)) {
                            if (el.classList?.contains(c)) return k
                        }
                        return null
                    },
                    renderHTML: (attrs) => (attrs.size && CLASSES_SIZE[attrs.size])
                        ? { class: CLASSES_SIZE[attrs.size] }
                        : {},
                },
            },
        }]
    },

    addCommands() {
        // Localiza a POSIÇÃO do nó 'image' alvo: a já selecionada (NodeSelection, via
        // clique/toolbar flutuante) ou a imagem inline adjacente ao cursor (recém-inserida
        // — o cursor vira seleção de TEXTO ao lado dela). Sem isso, atualizar atributos
        // era no-op (o cursor não está "sobre" a imagem).
        const localizarImagem = (state) => {
            const sel = state.selection

            if (sel.node && sel.node.type.name === 'image') {
                return sel.from
            }

            const { $from } = sel

            if ($from.nodeBefore && $from.nodeBefore.type.name === 'image') {
                return $from.pos - $from.nodeBefore.nodeSize
            }

            if ($from.nodeAfter && $from.nodeAfter.type.name === 'image') {
                return $from.pos
            }

            return null
        }

        // Aplica os atributos direto na transação via setNodeMarkup — robusto tanto na
        // invocação standalone (editor.commands.x) quanto encadeada pelo botão
        // (editor.chain().focus().x().run()). Mantém o nó selecionado para cliques seguidos.
        const aplicar = (attrs) => ({ state, tr, dispatch }) => {
            const pos = localizarImagem(state)

            if (pos === null) {
                return false // nenhuma imagem por perto — nada a fazer
            }

            const node = state.doc.nodeAt(pos)

            if (! node || node.type.name !== 'image') {
                return false
            }

            if (dispatch) {
                tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...attrs })
                tr.setSelection(NodeSelection.create(tr.doc, pos))
            }

            return true
        }

        return {
            definirAlinhamentoImagem: (align) => aplicar({ align }),
            definirTamanhoImagem: (size) => aplicar({ size }),
        }
    },
})
