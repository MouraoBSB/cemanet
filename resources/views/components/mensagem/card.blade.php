@props(['mensagem', 'variante' => 'lista'])

{{-- Card de mensagem (grade). :variante controla a coerência entre as duas superfícies:
     'lista'  = SEM miniatura, trecho 2 linhas, data simples (SPEC §4.1 / C-A);
     'perfil' = COM miniatura de pictografia, trecho 3 linhas, data mono dourada (SPEC §4.4).
     Badge/faixa de nível só @auth (I9); null-safe (I14/B1). Sem F5: sem ícone lida/não-lida. --}}
@php
    $perfil = $variante === 'perfil';
    $autores = $mensagem->autores;
    $data = $mensagem->data_recebimento;
    $miniatura = $perfil
        && $mensagem->formato === \App\Enums\FormatoMensagem::Pictografia
        ? $mensagem->getFirstMediaUrl(\App\Models\Mensagem::COLECAO_IMAGENS, 'web') : '';
    $trecho = \Illuminate\Support\Str::limit(strip_tags((string) ($mensagem->resumo ?: $mensagem->corpo)), 160);
@endphp
<article {{ $attributes->class(['cema-msg-card group flex flex-col overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card']) }}>
    <a href="{{ route('mensagens.show', $mensagem->slug) }}" class="flex h-full flex-col rounded-2xl outline-none focus-visible:ring-2 focus-visible:ring-gold">
        {{-- Faixa superior: cor do NÍVEL a logado (null-safe); marca para anônimo (look 2B). --}}
        @auth
            <span class="block h-1" style="background:{{ $mensagem->visibilidade()?->cor() ?? '#cbb26a' }}" aria-hidden="true"></span>
        @else
            <span class="block h-1 bg-gradient-to-r from-gold to-primary" aria-hidden="true"></span>
        @endauth

        @if ($miniatura)
            <div class="aspect-[16/9] overflow-hidden bg-cream">
                <img src="{{ $miniatura }}" alt="" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-[1.03]">
            </div>
        @endif

        <div class="flex flex-1 flex-col gap-3 px-5 pb-3 pt-[18px]">
            <div class="flex items-center justify-between gap-2">
                <x-mensagem.selo-formato :formato="$mensagem->formato" />
                @auth <x-mensagem.selo-nivel :visibilidade="$mensagem->visibilidade()" /> @endauth
            </div>

            <h3 class="font-display text-[17.5px] font-semibold leading-snug text-text-ink text-balance">{{ $mensagem->titulo }}</h3>

            @if ($trecho !== '')
                <p class="cema-msg-trecho font-serif text-[13.5px] font-light leading-[1.7] text-[#6a6685]" style="--linhas:{{ $perfil ? 3 : 2 }}">{{ $trecho }}</p>
            @endif

            <div class="mt-auto flex items-center justify-between gap-2.5 border-t border-[#F0EEF4] pt-3">
                <div class="flex min-w-0 items-center gap-2">
                    @if ($autores->isNotEmpty())
                        <span class="grid size-[26px] shrink-0 place-items-center rounded-full bg-gradient-to-br from-gold to-[#d98a14] font-display text-[10px] font-semibold text-[#3a3266]" aria-hidden="true">{{ $autores->first()->iniciais }}</span>
                        <span class="min-w-0 truncate text-[12.5px] text-[#5b576b]">{{ $autores->pluck('nome')->join(', ', ' e ') }}</span>
                    @else
                        <span class="text-[12px] italic text-text-muted">Sem assinatura</span>
                    @endif
                </div>
                @if ($data)
                    <time datetime="{{ $data->toDateString() }}"
                          @class(['shrink-0 text-[12px]', 'font-mono text-gold' => $perfil, 'text-text-muted' => ! $perfil])>{{ $data->translatedFormat('d M Y') }}</time>
                @endif
            </div>
        </div>

        <div class="px-5 pb-4">
            <span class="cema-msg-cta inline-flex items-center gap-1.5 rounded-pill bg-cream px-3.5 py-2 text-[12.5px] font-medium text-primary transition">
                Ler mensagem
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </span>
        </div>
    </a>
</article>
