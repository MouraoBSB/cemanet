{{-- Card do dia: cabeçalho com setas prev/próximo (crawláveis), 4 blocos SSR e compartilhar. --}}
@php
    $ehHoje = $dataAtual->toDateString() === $hojeBrasilia->toDateString();
    $urlAtual = $ehUrlNua ? route('agenda.index') : route('agenda.show', $dataAtual->format('Y-m-d'));
    $textoCompartilhar = trim(implode("\n\n", array_filter([
        'Agenda Reforma Íntima — '.$dia->tituloExtenso(),
        trim(strip_tags((string) $dia->reflexao)),
        filled($dia->meta_dia_texto) ? trim(strip_tags((string) $dia->meta_dia_texto)) : null,
        filled($dia->prece) ? trim(strip_tags((string) $dia->prece)) : null,
        'CEMA — Centro Espírita Maria Madalena',
    ])));
@endphp
<article class="agenda-card overflow-hidden rounded-2xl border border-border-muted bg-white shadow-card">
    <header class="agenda-card-topo flex items-center justify-between gap-4 bg-gradient-to-br from-primary to-footer-bg px-6 py-5 text-white">
        @if ($diaAnterior)
            <a href="{{ route('agenda.show', $diaAnterior) }}" wire:navigate aria-label="Dia anterior com conteúdo"
               class="grid size-10 shrink-0 place-items-center rounded-full border border-white/20 text-xl transition hover:bg-white/10">‹</a>
        @else
            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-white/10 text-xl opacity-40" aria-hidden="true">‹</span>
        @endif

        <div class="min-w-0 text-center">
            <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-gold">Devocional do dia</p>
            <h2 class="mt-1 font-display text-xl font-semibold leading-tight sm:text-2xl">{{ $dia->tituloExtenso() }}</h2>
            @unless ($ehHoje)
                <a href="{{ route('agenda.index') }}" wire:navigate
                   class="mt-2 inline-flex items-center gap-1.5 rounded-pill bg-gold px-3 py-1 font-mono text-[11px] font-semibold text-[#3a2f00] transition hover:opacity-90">↺ Voltar para hoje</a>
            @endunless
        </div>

        @if ($diaProximo)
            <a href="{{ route('agenda.show', $diaProximo) }}" wire:navigate aria-label="Próximo dia com conteúdo"
               class="grid size-10 shrink-0 place-items-center rounded-full border border-white/20 text-xl transition hover:bg-white/10">›</a>
        @else
            <span class="grid size-10 shrink-0 place-items-center rounded-full border border-white/10 text-xl opacity-40" aria-hidden="true">›</span>
        @endif
    </header>

    <div class="agenda-card-corpo px-6 py-6 sm:px-8">
        @if (filled($dia->reflexao))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Reflexão e Vivência do Evangelho</h3>
                <div class="agenda-prosa">{!! $dia->reflexao !!}</div>
            </section>
        @endif

        @if ($metaMes || filled($dia->meta_mes_texto))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Meta do Mês</h3>
                @if ($metaMes)
                    <p class="agenda-subtitulo">{{ $metaMes->titulo }}</p>
                @endif
                @if (filled($dia->meta_mes_texto))
                    <div class="agenda-prosa">{!! $dia->meta_mes_texto !!}</div>
                @endif
            </section>
        @endif

        @if (filled($dia->meta_dia_titulo) || filled($dia->meta_dia_texto))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Meta do Dia</h3>
                @if (filled($dia->meta_dia_titulo))
                    <p class="agenda-subtitulo">{{ $dia->meta_dia_titulo }}</p>
                @endif
                @if (filled($dia->meta_dia_texto))
                    <div class="agenda-prosa">{!! $dia->meta_dia_texto !!}</div>
                @endif
            </section>
        @endif

        @if (filled($dia->prece))
            <section class="agenda-bloco">
                <h3 class="agenda-bloco-titulo"><span class="agenda-tick" aria-hidden="true"></span>Sugestão de Prece</h3>
                <div class="agenda-prosa">{!! $dia->prece !!}</div>
            </section>
        @endif
    </div>

    <footer class="agenda-compartilhar border-t border-border-muted bg-cream px-6 py-5 sm:px-8"
            x-data="{ url: @js($urlAtual), texto: @js($textoCompartilhar), copiado: false,
                copiar() { navigator.clipboard.writeText(this.url).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000); }); },
                nativo() { if (navigator.share) { navigator.share({ title: 'Agenda Reforma Íntima', text: this.texto, url: this.url }); } } }">
        <p class="mb-3 text-sm text-text-muted">Compartilhar:</p>
        <div class="flex flex-wrap items-center gap-2.5">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white" aria-hidden="true">f</span> Facebook
            </a>
            <a href="https://wa.me/?text={{ urlencode($textoCompartilhar."\n".$urlAtual) }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white" aria-hidden="true">W</span> WhatsApp
            </a>
            <button type="button" @click="copiar()"
                    class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
            </button>
            <button type="button" x-cloak x-show="navigator.share" @click="nativo()"
                    class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">Compartilhar…</button>
        </div>
    </footer>
</article>
