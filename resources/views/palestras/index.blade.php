<x-layout.app title="Palestras Públicas" description="Palestras públicas do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Palestras Públicas</p>
            <h1 class="mt-2 font-display text-4xl font-semibold">Palestras do CEMA</h1>
            <p class="mt-3 max-w-xl text-white/85">Reflexões à luz do Espiritismo, abertas a todos.</p>
        </div>
    </section>

    @if ($proxima)
        @php($pp = $proxima->palestrantesAtivos->first())
        <section class="mx-auto max-w-[1240px] px-6 pt-12" aria-label="Próxima palestra">
            <p class="mb-3 font-display text-2xl font-semibold text-primary"><span class="text-gold">Próximas</span> Palestras</p>
            <div class="flex flex-col items-center gap-5 rounded-2xl bg-accent p-6 text-white sm:flex-row sm:gap-7">
                @if ($pp?->foto_thumb_url)
                    <img src="{{ $pp->foto_thumb_url }}" alt="{{ $pp->nome }}" width="112" height="112"
                         class="size-28 shrink-0 rounded-full object-cover ring-4 ring-white/40">
                @endif
                <div class="flex-1 text-center sm:text-left">
                    <h2 class="font-display text-2xl font-semibold">{{ $proxima->titulo }}</h2>
                    @if ($proxima->data_da_palestra)
                        <p class="mt-1 text-white/85">{{ $proxima->data_da_palestra->translatedFormat('d \d\e F \d\e Y · H\hi') }}</p>
                    @endif
                    @if ($pp)<p class="mt-1 text-sm text-white/80">{{ $pp->nome }}</p>@endif
                    <div class="mt-4 flex justify-center sm:justify-start">
                        <x-ui.countdown :data="$proxima->data_da_palestra" />
                    </div>
                </div>
                <a href="{{ route('palestras.show', $proxima->slug) }}"
                   class="shrink-0 rounded-pill bg-white px-6 py-3 font-ui font-semibold text-accent transition hover:bg-white/90">Ver Palestra</a>
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        {{-- #[Url] lê q/assunto da query string no load inicial; a busca do header (GET ?q=) cai aqui. --}}
        <livewire:palestras.lista />
    </section>
</x-layout.app>
