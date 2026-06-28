// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-27
// Teste de interação VIVA da extensão de alinhamento/tamanho de imagem.
// Exercita o CAMINHO REAL do botão da toolbar (editor.chain().focus().comando().run()),
// não só a invocação standalone — é o que pega o no-op de composição. Insere a imagem,
// roda o comando SEM pré-selecionar (cursor como seleção de texto ao lado da imagem) e
// verifica que a CLASSE foi aplicada ao <img> (e que não há style inline).

import { beforeAll, expect, test } from 'vitest'
import { Editor } from '@tiptap/core'
import * as tiptapCore from '@tiptap/core'
import * as pmState from '@tiptap/pm/state'
import Document from '@tiptap/extension-document'
import Paragraph from '@tiptap/extension-paragraph'
import Text from '@tiptap/extension-text'
import Image from '@tiptap/extension-image'

let imagemAlinhada

beforeAll(async () => {
    // A extensão real lê o TipTap do global exposto pelo Filament; espelhamos isso
    // (core + pmState, exatamente como o bundle do Filament expõe).
    globalThis.window = globalThis.window ?? globalThis
    window.FilamentRichEditor = { tiptap: { core: tiptapCore, pmState } }
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

test('aplica alignleft pelo caminho real do botão, sem pré-selecionar', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    // Idêntico ao jsHandler do botão da toolbar:
    editor.chain().focus().definirAlinhamentoImagem('left').run()

    expect(editor.getHTML()).toContain('alignleft')
    editor.destroy()
})

test('aplica size-medium pelo caminho real do botão, sem pré-selecionar', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.chain().focus().definirTamanhoImagem('medium').run()

    expect(editor.getHTML()).toContain('size-medium')
    editor.destroy()
})

test('troca o tamanho em cliques seguidos (nó continua selecionado)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.chain().focus().definirTamanhoImagem('medium').run()
    editor.chain().focus().definirTamanhoImagem('large').run()

    const html = editor.getHTML()
    expect(html).toContain('size-large')
    expect(html).not.toContain('size-medium')
    editor.destroy()
})

test('também funciona standalone (editor.commands)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.commands.definirAlinhamentoImagem('center')

    expect(editor.getHTML()).toContain('aligncenter')
    editor.destroy()
})

test('não emite style inline (saída apenas por classes)', () => {
    const editor = criarEditor()
    editor.commands.setTextSelection(posDepoisDaImagem(editor))

    editor.chain().focus().definirAlinhamentoImagem('right').run()

    expect(editor.getHTML()).not.toContain('style=')
    editor.destroy()
})
