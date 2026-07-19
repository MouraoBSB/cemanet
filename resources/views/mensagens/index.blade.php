<x-layout.app title="Mensagens Mediúnicas"
              description="Mensagens psicografadas, psicofônicas e pictográficas recebidas na mediunidade do CEMA.">
    @php
        $breadcrumbJsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Mensagens Mediúnicas'],
            ],
        ];
    @endphp
    <x-slot:head>
        <script type="application/ld+json">
            @json($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden text-white"
             style="background:radial-gradient(circle at 78% 22%, rgba(110,159,203,0.40), transparent 54%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
        <x-ui.particulas />
        <x-mensagem.envelope-hero />
        <div class="relative z-[2] mx-auto flex max-w-[1240px] flex-col items-end justify-between gap-10 px-6 py-16 desktop-sm:flex-row">
            <div class="w-full max-w-xl">
                <p class="font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Centro Espírita Maria Madalena</p>
                <h1 class="mt-3.5 font-display text-4xl font-semibold leading-[1.08] sm:text-5xl">Mensagens Mediúnicas</h1>
                <div class="mt-4 h-1 w-16 rounded-full bg-gold"></div>
                <p class="mt-4 max-w-xl font-light text-[#d7def0]">Comunicações recebidas pelos médiuns da Casa — palavras de consolo, orientação e esperança, registradas com respeito e publicadas para o bem de todos.</p>
            </div>
            <div class="flex shrink-0 items-center gap-3.5 rounded-2xl border border-white/22 bg-white/10 px-6 py-4 backdrop-blur-sm">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold text-[#3a3266]" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                <span>
                    <span class="block font-display text-[22px] font-bold leading-tight">{{ $totalPublicas }}</span>
                    <span class="block max-w-[160px] text-[12.5px] text-[#c7d0ea]">{{ $totalPublicas === 1 ? 'mensagem pública' : 'mensagens públicas' }}</span>
                </span>
            </div>
        </div>
    </section>

    {{-- Breadcrumb --}}
    <nav aria-label="Trilha de navegação" class="border-b border-border-muted bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-2.5 text-[13px] text-text-muted">
            <a href="{{ url('/') }}" class="hover:text-primary">Início</a>
            <span aria-hidden="true"> › </span>
            <span class="text-text-secondary" aria-current="page">Mensagens Mediúnicas</span>
        </div>
    </nav>

    {{-- Lista (Livewire) + Veja também --}}
    <section class="bg-surface">
        <div class="mx-auto max-w-[1240px] px-6 py-12">
            <livewire:mensagens.lista />

            <div class="mt-12 border-t border-border-muted pt-9">
                <h2 class="mb-4 font-display text-lg font-semibold text-primary">Veja também</h2>
                <div class="flex flex-wrap gap-3">
                    @php
                        $vejaTambem = [
                            ['rota' => route('autores.index'), 'rotulo' => 'Autores Espirituais'],
                            ['rota' => route('palestras.index'), 'rotulo' => 'Palestras Públicas'],
                            ['rota' => route('blog.index'), 'rotulo' => 'Sementeira de Luz'],
                            ['rota' => route('agenda.index'), 'rotulo' => 'Agenda de Reforma Íntima'],
                        ];
                    @endphp
                    @foreach ($vejaTambem as $item)
                        <a href="{{ $item['rota'] }}" class="inline-flex items-center gap-2 rounded-pill border border-border bg-white px-5 py-2.5 text-sm text-text-secondary transition hover:border-primary hover:text-primary">
                            <span class="inline-block size-2 rounded-full bg-accent" aria-hidden="true"></span>{{ $item['rotulo'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-layout.app>
