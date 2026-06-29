// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29
// Extensão TipTap: colar/arrastar imagem no corpo → envia para a biblioteca e insere
// a URL portável /midia/{id}/web. Importa o TipTap do Filament (não rebundlar).
// Carregada via RichContentPlugin::getTipTapJsExtensions().

const { Extension } = window.FilamentRichEditor.tiptap.core
const { Plugin } = window.FilamentRichEditor.tiptap.pmState

const ENDPOINT = '/admin/midia/colar'
const MAX_BYTES = 20 * 1024 * 1024 // 20 MB — alinhado ao endpoint e ao PHP (Fatia A)

// Token CSRF a partir do cookie XSRF-TOKEN (Laravel) → header X-XSRF-TOKEN.
function tokenCsrf() {
    const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
    return m ? decodeURIComponent(m[1]) : ''
}

// Aviso por OVERLAY DOM (fixo na viewport): NÃO é nó do documento, então nunca é
// salvo/serializado — invariante "nada intermediário é persistível".
function criarAviso(texto) {
    const el = document.createElement('div')
    el.textContent = texto
    el.style.cssText =
        'position:fixed; left:50%; bottom:24px; transform:translateX(-50%); z-index:9999;' +
        'background:#4e4483; color:#fff; padding:8px 16px; border-radius:9999px;' +
        'font-size:0.875rem; box-shadow:0 6px 20px rgba(0,0,0,.25); pointer-events:none;'
    document.body.appendChild(el)
    return el
}

async function enviarEInserir(editor, file) {
    if (!file.type || !file.type.startsWith('image/')) {
        return
    }
    if (file.size > MAX_BYTES) {
        window.alert('Imagem muito grande (máximo 20 MB). Reduza e tente novamente.')
        return
    }

    const aviso = criarAviso('Enviando imagem…')

    try {
        const dados = new FormData()
        dados.append('imagem', file)

        const resposta = await fetch(ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-XSRF-TOKEN': tokenCsrf(),
                Accept: 'application/json',
            },
            body: dados,
        })

        if (!resposta.ok) {
            throw new Error('Resposta ' + resposta.status)
        }

        const json = await resposta.json()

        // Insere SÓ a <img> final (URL portável), sem data-id (id:null) — mesma forma do
        // nó da tool "Inserir da biblioteca", para ficar fora do cleanup do RichEditor.
        editor
            .chain()
            .focus()
            .insertContent({
                type: 'image',
                attrs: { src: json.url, alt: '', id: null },
            })
            .run()
    } catch (erro) {
        window.alert('Falha ao enviar a imagem. Verifique a conexão e tente novamente.')
    } finally {
        aviso.remove()
    }
}

export default Extension.create({
    name: 'colarNaBiblioteca',

    addProseMirrorPlugins() {
        const editor = this.editor

        return [
            new Plugin({
                props: {
                    handlePaste(view, event) {
                        const imagens = Array.from(event.clipboardData?.files || [])
                            .filter((f) => f.type && f.type.startsWith('image/'))

                        if (!imagens.length) {
                            return false // não é imagem → deixa o colar padrão (texto/HTML)
                        }

                        event.preventDefault()
                        imagens.forEach((f) => enviarEInserir(editor, f))

                        return true
                    },

                    handleDrop(view, event) {
                        const imagens = Array.from(event.dataTransfer?.files || [])
                            .filter((f) => f.type && f.type.startsWith('image/'))

                        if (!imagens.length) {
                            return false
                        }

                        event.preventDefault()
                        imagens.forEach((f) => enviarEInserir(editor, f))

                        return true
                    },
                },
            }),
        ]
    },
})
