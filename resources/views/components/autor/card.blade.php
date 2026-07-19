@props(['autor'])

{{-- Requer: $autor com mensagens_publicas_count e a relação mensagens já pré-filtrada
     por publica() (senão vaza formatos de restritas).
     Card de autor (grade inteira clicável). Foto 3:4 ou iniciais + gradiente cema-grad-{id%8}
     (O4). Contagem SÓ das públicas (B1 — nunca Str::plural) + pontinhos de formatos distintos
     das públicas (mensagens já vem eager-load filtrado por publica() no controller — sem N+1).
     Sem curtir/coluna curtidas (F5). --}}
@php
    $contagem = $autor->mensagens_publicas_count ?? 0;
    $formatos = $autor->mensagens->pluck('formato')->filter()->unique();
    $corPonto = fn (\App\Enums\FormatoMensagem $formato): string => match ($formato) {
        \App\Enums\FormatoMensagem::Psicografia => '#8f83d6',
        \App\Enums\FormatoMensagem::Psicofonia => '#6E9FCB',
        \App\Enums\FormatoMensagem::Pictografia => '#89AB98',
    };
@endphp
<a {{ $attributes->except(['autor']) }} href="{{ route('autores.show', $autor->slug) }}"
   class="cema-msg-card group flex flex-col overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
    <span class="cema-autor-avatar cema-grad-{{ $autor->id % 8 }} relative block aspect-[3/4] w-full overflow-hidden" aria-hidden="true">
        @if ($autor->foto_url)
            <img src="{{ $autor->foto_url }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
        @else
            <span class="flex size-full items-center justify-center font-display text-[56px] font-bold tracking-wide text-white/92">{{ $autor->iniciais }}</span>
            <span class="absolute inset-x-0 bottom-0 h-14" style="background:linear-gradient(transparent, rgba(11,16,48,0.45))"></span>
        @endif
    </span>
    <span class="flex flex-1 flex-col gap-2 px-[18px] pb-[18px] pt-4">
        <span class="font-display text-[17.5px] font-semibold leading-snug text-text-ink">{{ $autor->nome }}</span>
        @if (filled($autor->chamada))
            <span class="font-serif text-[13px] italic leading-[1.6] text-[#6a6685]">{{ $autor->chamada }}</span>
        @endif
        <span class="mt-auto flex items-center justify-between gap-2 border-t border-[#F0EEF4] pt-2.5">
            <span class="font-mono text-[10.5px] uppercase tracking-[0.12em] text-[#b08a2e]">{{ $contagem }} {{ $contagem === 1 ? 'mensagem' : 'mensagens' }}</span>
            @if ($formatos->isNotEmpty())
                <span class="inline-flex gap-1.5">
                    @foreach ($formatos as $formato)
                        <span title="{{ $formato->rotulo() }}" aria-label="{{ $formato->rotulo() }}"
                              class="inline-block size-[9px] rounded-full" style="background:{{ $corPonto($formato) }}"></span>
                    @endforeach
                </span>
            @endif
        </span>
    </span>
</a>
