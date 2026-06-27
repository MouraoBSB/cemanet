// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Extensão TipTap: alinhamento de imagem por classe WP.
// Importa o TipTap do Filament (não rebundlar).
// Carregada via RichContentPlugin::getTipTapJsExtensions().

const { Extension } = window.FilamentRichEditor.tiptap.core

const CLASSES_ALIGN = {
    left:   'alignleft',
    right:  'alignright',
    center: 'aligncenter',
    none:   'alignnone',
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
                    renderHTML: (attrs) => (attrs.align && CLASSES_ALIGN[attrs.align])
                        ? { class: CLASSES_ALIGN[attrs.align] }
                        : {},
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
