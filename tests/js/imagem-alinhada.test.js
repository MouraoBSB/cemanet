// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Teste de interação VIVA da extensão de alinhamento/tamanho de imagem:
// insere a imagem, roda o comando SEM pré-selecionar (estado pós-inserção em que
// o cursor é seleção de texto ao lado da imagem) e verifica que a CLASSE foi
// aplicada ao <img>. Cobre o auto-select que conserta o no-op do BUG 1.

import { beforeAll, expect, test } from 'vitest'
import { Editor } from '@tiptap/core'
import * as tiptapCore from '@tiptap/core'
import Document from '@tiptap/extension-document'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import Image from '@tiptap/extension-image'

let imagemAlinhada

beforeAll(async () => {
    // A extensão real lê o TipTap do global exposto pelo Filament; espelhamos isso.
    globalThis.window = globalThis.window ?? globalThis
    window.FilamentRichEditor = { tiptap: { core: tiptapCore } }
    imagemAlinhada = (await import('../../resources/js/filament/imagem-alinhada.js')).default
})

function criarEditor() {
    return new Editor({
        extensions: [
            Document,
            Paragraph,
            Text,
            Image.configure({ inline: true }),
            imagemAlinhada,
        ],
        content: '<p><img src="foto.jpg"></p>',
    })
}

/** Posição logo após a imagem inline (estado típico após inserir). */
function posDepoisDaImagem(editor) {
    let pos = null
    editor.state.doc.descendants((node, p) => {
        if (node.type.name === 'image') {
            pos = p + node.nodeSize
        }
    })
    return pos
}

test('aplica alignleft sem pré-selecionar a imagem (auto-select)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.commands.definirAlinhamentoImagem('left')

    expect(editor.getHTML()).toContain('alignleft')
    editor.destroy()
})

test('aplica size-medium sem pré-selecionar a imagem (auto-select)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.commands.definirTamanhoImagem('medium')

    expect(editor.getHTML()).toContain('size-medium')
    editor.destroy()
})

test('não emite style inline (saída apenas por classes)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.commands.definirAlinhamentoImagem('center')

    expect(editor.getHTML()).not.toContain('style=')
    editor.destroy()
})
