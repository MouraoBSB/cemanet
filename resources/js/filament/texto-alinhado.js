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
