{{-- Hero roxo: partículas + onda SVG + breadcrumb + foto 3:4 (ou iniciais) + chamada + chips + CTA calendário. --}}
<section class="relative overflow-hidden bg-gradient-to-br from-[#0b1030] via-[#1a1f4a] to-[#2c2f64] text-white">
    <x-ui.particulas />
    <svg viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"
         class="absolute inset-x-0 -bottom-px block h-20 w-full">
        <path d="M0,80 C240,20 480,110 720,70 C960,30 1200,100 1440,50 L1440,120 L0,120 Z" fill="var(--color-surface)"></path>
    </svg>

    <div class="relative z-[2] mx-auto max-w-[1160px] px-6 pb-24 pt-6">
        <nav aria-label="Trilha de navegação" class="mb-7 flex flex-wrap items-center gap-2 text-[12.5px] text-[#9aa6cf]">
            <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
            <a href="{{ route('palestrantes.index') }}" class="hover:text-white">Palestrantes</a><span aria-hidden="true">›</span>
            <span class="text-[#e7e9f4]" aria-current="page">{{ $palestrante->nome }}</span>
        </nav>

        <div class="flex flex-wrap items-end gap-9">
            {{-- Foto 3:4 em moldura translúcida; sem foto → iniciais em gradiente. --}}
            <div class="w-[186px] shrink-0 rounded-[22px] border border-white/16 bg-white/8 p-2 backdrop-blur-sm">
                @if ($palestrante->foto_url)
                    <img src="{{ $palestrante->foto_url }}" alt="{{ $palestrante->nome }}" width="186" height="248"
                         class="block aspect-[3/4] w-full rounded-[15px] object-cover">
                @else
                    <span class="cema-grad-{{ $palestrante->id % 8 }} grid aspect-[3/4] w-full place-items-center rounded-[15px]" aria-hidden="true">
                        <span class="font-display text-5xl font-semibold text-white/90">{{ $palestrante->iniciais }}</span>
                    </span>
                @endif
            </div>

            <div class="min-w-[280px] flex-1 basis-[420px]">
                <p class="mb-3 font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Palestrante · CEMA</p>
                <h1 class="mb-4 font-display font-semibold leading-[1.06] text-white [font-size:clamp(2.2rem,1.5rem+2.4vw,3.4rem)]">{{ $palestrante->nome }}</h1>
                <div class="mb-[18px] h-1 w-16 rounded-full bg-gold"></div>
                @if ($palestrante->chamada)
                    <p class="mb-5 max-w-[560px] font-serif italic text-white/85 [font-size:clamp(1.05rem,1rem+0.35vw,1.25rem)]">{{ $palestrante->chamada }}</p>
                @endif
                @if ($areasHero->isNotEmpty())
                    {{-- Máx. ~6 temas (top por frequência); cada um leva à archive filtrada por aquele assunto. --}}
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($areasHero as $areaItem)
                            <a href="{{ route('palestras.index', ['assunto' => $areaItem['slug']]) }}"
                               class="inline-flex items-center gap-2 rounded-pill border border-white/20 bg-white/10 px-3.5 py-1.5 text-[12.5px] text-[#e7e9f4] transition hover:bg-white/15">
                                <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-2 rounded-full"></span>{{ $areaItem['nome'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <a href="{{ route('calendario.index', ['tipo' => 'palestras']) }}"
               class="inline-flex shrink-0 items-center gap-3 rounded-2xl border border-white/22 bg-white/10 px-5 py-4 backdrop-blur-sm transition hover:bg-white/15">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-gold text-[#3a3266]" aria-hidden="true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4" stroke-linecap="round"/></svg>
                </span>
                <span>
                    <span class="block font-display font-semibold">Calendário de Palestras</span>
                    <span class="block text-sm text-white/75">Veja a programação completa →</span>
                </span>
            </a>
        </div>
    </div>
</section>
