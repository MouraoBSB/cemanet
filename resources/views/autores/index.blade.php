<x-layout.app title="Autores Espirituais"
              description="Os espíritos que, pela mediunidade do CEMA, partilham mensagens de consolo e instrução.">
    @php
        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Mensagens Mediúnicas', 'item' => route('mensagens.index')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => 'Autores Espirituais'],
            ],
        ];
    @endphp
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero roxo (mais baixo que o das mensagens): partículas + onda SVG branca na base. --}}
    <section class="relative overflow-hidden text-white"
             style="background:radial-gradient(circle at 86% 8%, rgba(242,168,30,0.22), transparent 42%), radial-gradient(circle at 20% 90%, rgba(110,159,203,0.28), transparent 55%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
        <x-ui.particulas />
        <div class="relative z-[2] mx-auto max-w-[1160px] px-6 pb-5 pt-11 sm:pt-13">
            <p class="font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Mensagens Mediúnicas · CEMA</p>
            <h1 class="mt-3.5 font-display font-semibold leading-[1.08] text-white [font-size:clamp(2.1rem,1.4rem+2.4vw,3.3rem)]">Autores Espirituais</h1>
            <div class="my-4 h-1 w-16 rounded-full bg-gold"></div>
            <p class="max-w-[620px] font-serif text-[1.05rem] italic leading-relaxed text-[#d7def0]">Os espíritos que, pela mediunidade dos trabalhadores da Casa, partilham conosco suas mensagens de consolo e instrução.</p>
        </div>
        <x-ui.onda-hero />
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1160px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <a href="{{ route('mensagens.index') }}" class="hover:text-primary">Mensagens</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Autores Espirituais</span>
        </div>
    </nav>

    {{-- Grade de autores + sidebar institucional --}}
    <section class="bg-surface">
        <div class="mx-auto flex max-w-[1160px] flex-col gap-7 px-6 py-12 desktop-sm:flex-row desktop-sm:items-start">
            <div class="min-w-0 flex-1">
                <div class="grid grid-cols-[repeat(auto-fill,minmax(245px,1fr))] gap-5">
                    @foreach ($autores as $autor)
                        <x-autor.card :autor="$autor" />
                    @endforeach
                </div>
            </div>

            <aside class="flex w-full shrink-0 flex-col gap-5 desktop-sm:w-[340px] desktop-sm:sticky desktop-sm:top-24">
                {{-- Os Autores Espirituais (institucional, estático) --}}
                <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
                    <h2 class="font-display text-lg font-semibold text-primary">Os Autores Espirituais</h2>
                    <div class="mb-3.5 mt-2.5 h-[3px] w-11 rounded-full bg-gold"></div>
                    <p class="text-sm leading-relaxed text-text-secondary">Cada mensagem do acervo mediúnico do CEMA é atribuída ao espírito que a assinou — o autor espiritual. São benfeitores que, pela psicografia, pela psicofonia ou pela pictografia, nos alcançam com palavras de consolo, estudo e esperança.</p>
                    <p class="mt-2.5 text-sm leading-relaxed text-text-secondary">O acervo é mantido pelo DEPAE com respeito e critério: registramos a data de recebimento e o formato de cada comunicação, para que sirvam ao estudo sereno da doutrina.</p>
                </div>

                {{-- Mini-stats --}}
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-cream px-4 py-3">
                        <p class="font-display text-2xl font-bold text-primary">{{ $totalAutores }}</p>
                        <p class="text-xs text-text-muted">Autores</p>
                    </div>
                    <div class="rounded-xl bg-secondary/[0.12] px-4 py-3">
                        <p class="font-display text-2xl font-bold text-secondary">{{ $totalMensagensPublicas }}</p>
                        <p class="text-xs text-text-muted">Mensagens públicas</p>
                    </div>
                </div>

                {{-- Autor em evidência (O3: mais públicas, desempate por nome) --}}
                @if ($destaque)
                    <div class="rounded-2xl p-6 text-white shadow-card" style="background:linear-gradient(150deg,#3a3266,#4E4483 65%,#5b4f97);">
                        <p class="mb-2.5 font-mono text-[10px] uppercase tracking-[0.16em] text-[#F2C55C]">Autor em evidência</p>
                        <p class="font-display text-[19px] font-semibold text-white">{{ $destaque->nome }}</p>
                        @if (filled($destaque->chamada))
                            <p class="mt-1 font-serif text-[13.5px] italic text-[#cfc9e4]">{{ $destaque->chamada }}</p>
                        @endif
                        <a href="{{ route('autores.show', $destaque->slug) }}"
                           class="mt-4 inline-flex rounded-pill bg-gold px-5 py-2.5 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ver perfil</a>
                    </div>
                @endif
            </aside>
        </div>
    </section>
</x-layout.app>
