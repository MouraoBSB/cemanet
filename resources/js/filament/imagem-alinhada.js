// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Extensão TipTap: alinhamento e tamanho de imagem por classes WP.
// Importa o TipTap do Filament (não rebundlar).
// Carregada via RichContentPlugin::getTipTapJsExtensions().

const { Extension } = window.FilamentRichEditor.tiptap.core

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
        // updateAttributes('image') só age sobre uma imagem DENTRO da seleção. Após
        // inserir, o cursor vira seleção de TEXTO ao lado da imagem (inline) → seria
        // no-op. Então, antes de aplicar, garantimos o nó 'image' selecionado:
        //  - se já é uma NodeSelection de imagem (clique/toolbar flutuante), aplica direto;
        //  - senão, localiza a imagem inline imediatamente antes/depois do cursor
        //    (caso recém-inserida) e faz setNodeSelection antes do updateAttributes.
        const aplicar = (attrs) => ({ state, chain }) => {
            const { selection } = state

            if (selection.node && selection.node.type.name === 'image') {
                return chain().updateAttributes('image', attrs).run()
            }

            const { $from } = selection
            let pos = null

            if ($from.nodeBefore && $from.nodeBefore.type.name === 'image') {
                pos = $from.pos - $from.nodeBefore.nodeSize
            } else if ($from.nodeAfter && $from.nodeAfter.type.name === 'image') {
                pos = $from.pos
            }

            if (pos === null) {
                return false // nenhuma imagem por perto — nada a fazer
            }

            return chain().setNodeSelection(pos).updateAttributes('image', attrs).run()
        }

        return {
            definirAlinhamentoImagem: (align) => aplicar({ align }),
            definirTamanhoImagem: (size) => aplicar({ size }),
        }
    },
})
