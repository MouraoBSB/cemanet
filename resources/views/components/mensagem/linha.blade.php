@props(['mensagem'])

{{-- Linha de mensagem (visão lista): faixa lateral decorativa + meta (formato + nível + data),
     título, "por {Autor}"/"Sem assinatura" e CTA "Ler". Badge/faixa de nível só @auth (I9);
     null-safe (I14/B1). Sem F5: sem ícone lida/não-lida. --}}
@php
    $autores = $mensagem->autores;
    $data = $mensagem->data_recebimento;
@endphp
<article {{ $attributes->class(['cema-msg-card group flex overflow-hidden rounded-[14px] border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('mensagens.show', $mensagem->slug) }}" class="flex w-full items-stretch rounded-[14px] outline-none focus-visible:ring-2 focus-visible:ring-gold">
        @auth
            <span class="w-[5px] shrink-0" style="background:{{ $mensagem->visibilidade()?->cor() ?? '#cbb26a' }}" aria-hidden="true"></span>
        @else
            <span class="w-[5px] shrink-0 bg-gradient-to-b from-gold to-primary" aria-hidden="true"></span>
        @endauth
        <div class="flex flex-1 flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <div class="mb-1.5 flex flex-wrap items-center gap-2">
                    <x-mensagem.selo-formato :formato="$mensagem->formato" />
                    @auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" /> @endauth
                    @if ($data)
                        <time datetime="{{ $data->toDateString() }}" class="text-[12px] text-text-muted">{{ $data->translatedFormat('d M Y') }}</time>
                    @endif
                </div>
                <h3 class="font-display text-[16.5px] font-semibold leading-snug text-text-ink group-hover:text-primary">{{ $mensagem->titulo }}</h3>
                <p class="mt-0.5 text-[13px] text-text-muted">
                    @if ($autores->isNotEmpty())
                        por {{ $autores->pluck('nome')->join(', ', ' e ') }}
                    @else
                        <span class="italic">Sem assinatura</span>
                    @endif
                </p>
            </div>
            <span class="cema-msg-cta inline-flex shrink-0 items-center gap-1.5 rounded-pill bg-cream px-4 py-2 text-[13px] font-medium text-primary transition">
                Ler
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        </div>
    </a>
</article>
