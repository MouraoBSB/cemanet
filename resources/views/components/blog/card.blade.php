@props(['post'])

<article {{ $attributes->class(['group flex flex-col']) }}>
    {{-- Imagem destacada --}}
    <a href="{{ $post->urlPublica }}" class="block overflow-hidden rounded-xl" style="height:170px;">
        @if ($post->getFirstMedia(\App\Models\Post::COLECAO_DESTACADA))
            <img src="{{ $post->getFirstMediaUrl(\App\Models\Post::COLECAO_DESTACADA, 'web') }}"
                 alt="{{ $post->imagem_destacada_alt ?? $post->titulo }}"
                 loading="lazy" width="320" height="170"
                 class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <div class="flex size-full items-center justify-center bg-gradient-to-br from-primary to-footer-bg">
                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" class="h-10 w-auto opacity-80">
            </div>
        @endif
    </a>

    {{-- Kicker (categoria) --}}
    @if ($post->categoriaPrincipal)
        <p class="mb-1.5 mt-3 font-mono text-[10px] tracking-[.12em] uppercase"
           style="color:{{ $post->corCategoria }}">
            {{ $post->categoriaPrincipal->nome }}
        </p>
    @endif

    {{-- Título --}}
    <h3 class="font-display text-[17px] font-semibold leading-[1.22] text-footer-bg group-hover:underline">
        <a href="{{ $post->urlPublica }}">{{ $post->titulo }}</a>
    </h3>

    {{-- Dek (resumo) --}}
    @if ($post->resumo)
        <p class="mt-1.5 line-clamp-3 text-[14px] leading-[1.6] text-[#5b5766]">{{ $post->resumo }}</p>
    @endif

    {{-- Meta --}}
    <div class="mt-2 font-mono text-[10px] text-text-muted">
        @if ($post->data_publicacao)
            <time datetime="{{ $post->data_publicacao->toIso8601String() }}">
                {{ $post->data_publicacao->translatedFormat('d M Y') }}
            </time>
        @endif
        @if ($post->tempo_leitura_min)
            · {{ $post->tempo_leitura_min }} min de leitura
        @endif
    </div>
</article>
