{{-- Corpo pictográfico (I8): introdução textual (campo único $mensagem->corpo, saneado) +
     galeria das imagens LOCAIS da MediaLibrary (coleção imagens, WebP web). Download por
     imagem (R3): atributo download com nome amigável derivado do título, apontando ao original
     (getUrl() sem conversão). NÃO usa link_arquivo (esse é o anexo Drive da sidebar). --}}
@if (filled($mensagem->corpo))
    <div class="cema-msg-prose mb-8">{!! $mensagem->corpo !!}</div>
@endif

<x-mensagem.imagens :mensagem="$mensagem" legenda="Desenho" />

@if ($mensagem->getMedia(\App\Models\Mensagem::COLECAO_IMAGENS)->isEmpty() && blank($mensagem->corpo))
    <p class="font-serif text-[15px] italic text-text-muted">Esta comunicação pictográfica ainda não tem desenhos disponíveis.</p>
@endif
