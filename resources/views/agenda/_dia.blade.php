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
                <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-white" aria-hidden="true"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></span> WhatsApp
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
