@php
    $palestrantes = $palestra->palestrantesAtivos;
    $data = $palestra->data_da_palestra;
    $ytId = $palestra->youtube_id;
    $jsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $palestra->titulo,
        'startDate' => optional($data)->toIso8601String(),
        'eventAttendanceMode' => $palestra->online
            ? 'https://schema.org/OnlineEventAttendanceMode'
            : 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => [
            '@type' => 'Place',
            'name' => 'Centro Espírita Maria Madalena',
            'address' => 'Quadra 02, Lote 16, Vila Vicentina, Planaltina, DF',
        ],
        'performer' => $palestrantes->map(fn ($p) => ['@type' => 'Person', 'name' => $p->nome])->all(),
        'organizer' => ['@type' => 'Organization', 'name' => 'CEMA'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $googleAgenda = $data
        ? 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($palestra->titulo)
            .'&dates='.$data->copy()->utc()->format('Ymd\THis\Z').'/'
            .$data->copy()->utc()->addMinutes(\App\Support\Palestras\DuracaoPalestra::minutos($palestra->duracao))->format('Ymd\THis\Z')
            .'&details='.urlencode(route('palestras.show', $palestra->slug))
        : null;
@endphp

<x-layout.app :title="$palestra->titulo" :description="$palestra->subtitulo ?? $palestra->resumo">
    <x-slot:head>
        <script type="application/ld+json">{!! $jsonLd !!}</script>
        @if ($ytId)
            <meta property="og:image" content="{{ $palestra->youtube_thumb }}">
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $palestra->titulo,
                'thumbnailUrl' => $palestra->youtube_thumb,
                'embedUrl' => 'https://www.youtube.com/embed/'.$ytId,
                'uploadDate' => optional($data)->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
    </x-slot:head>

    {{-- Barra de progresso de leitura --}}
    <div class="fixed inset-x-0 top-0 z-50 h-[3px] bg-gold/90 origin-left motion-reduce:hidden"
         x-data="{ p: 0 }" x-init="const f = () => { const h = document.documentElement; p = (h.scrollTop) / (h.scrollHeight - h.clientHeight) || 0; }; window.addEventListener('scroll', f, { passive: true }); f();"
         :style="`transform: scaleX(${p})`" aria-hidden="true"></div>

    {{-- S1: Hero (sempre roxo; partículas) --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestras.index') }}" class="hover:text-white">Palestras Públicas</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ \Illuminate\Support\Str::limit($palestra->titulo, 40) }}</span>
            </nav>
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestra Pública</p>
            <h1 class="mt-2 max-w-3xl font-display text-3xl font-semibold leading-tight text-balance md:text-5xl">{{ $palestra->titulo }}</h1>
            @if ($palestra->subtitulo)
                <p class="mt-3 max-w-2xl font-serif text-lg italic text-white/85">{{ $palestra->subtitulo }}</p>
            @endif
            <div class="mt-5 flex flex-wrap gap-2.5 text-sm">
                @if ($data)
                    <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">📅 {{ $data->translatedFormat('d \d\e F · H\hi') }}</span>
                @endif
                <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">🌐 {{ $palestra->online ? 'Online' : 'Presencial' }}</span>
                @foreach ($palestrantes as $p)
                    <span class="rounded-pill border border-white/18 bg-white/10 px-3 py-1.5">👤 {{ $p->nome }}</span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- S2: Conteúdo + sidebar sticky --}}
    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <div class="grid items-start gap-9 desktop-sm:grid-cols-[minmax(0,1fr)_320px]">
            {{-- Conteúdo --}}
            <div>
                @include('palestras.partials.player')

                <div class="mb-7 flex flex-wrap gap-3.5">
                    <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Data</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $data ? $data->translatedFormat('l, d \d\e F \d\e Y · H\hi') : 'A confirmar' }}</p>
                    </div>
                    <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                        <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Modalidade</p>
                        <p class="mt-1 font-semibold text-text-ink">{{ $palestra->online ? 'Online' : 'Presencial' }}</p>
                    </div>
                    @if (filled($palestra->duracao))
                        <div class="min-w-[150px] flex-1 rounded-xl border border-border-muted bg-white p-4">
                            <p class="font-mono text-[10.5px] uppercase tracking-[0.1em] text-text-muted">Duração</p>
                            <p class="mt-1 font-semibold text-text-ink">{{ $palestra->duracao }}</p>
                        </div>
                    @endif
                </div>

                @if ($palestra->descricao)
                    <div class="max-w-none text-justify hyphens-auto font-serif text-[16px] leading-[1.82] text-[#3a3553] [&_p]:mb-[18px] [&_a]:text-secondary [&_a]:underline">
                        {!! $palestra->descricao !!}
                    </div>
                @endif

                @include('palestras.partials.referencias')

                @if ($palestra->destaques->isNotEmpty())
                    <h2 class="mb-4 mt-8 font-display text-2xl font-semibold text-primary">Principais tópicos abordados</h2>
                    <div class="flex flex-col gap-2.5">
                        @foreach ($palestra->destaques as $i => $d)
                            <details class="group overflow-hidden rounded-xl border border-border-muted bg-[#FAFAFB]">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 font-display font-medium text-text-ink">
                                    <span><span class="mr-2 font-mono text-sm text-text-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $d->destaque }}</span>
                                    <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cream text-primary transition group-open:rotate-45">+</span>
                                </summary>
                                @if ($d->texto)
                                    <div class="px-5 pb-5 text-sm text-text-secondary">{{ $d->texto }}</div>
                                @endif
                            </details>
                        @endforeach
                    </div>
                @endif

                @if ($palestra->assuntos->isNotEmpty())
                    <div class="mt-8">
                        <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-text-muted">Assuntos principais</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($palestra->assuntos as $a)
                                <a href="{{ route('palestras.index', ['assunto' => $a->slug]) }}"
                                   class="rounded-pill border border-border bg-surface px-3.5 py-1.5 text-[13px] text-text-secondary hover:border-primary hover:text-primary">{{ $a->nome }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar sticky --}}
            <aside class="space-y-5 desktop-sm:sticky desktop-sm:top-24">
                @if ($palestrantes->count() > 1)
                    {{-- 2+ palestrantes: bloco compacto (avatar + nome + perfil), sem inflar a sidebar --}}
                    <div class="rounded-xl border border-border-muted bg-cream p-5">
                        <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Palestrantes</p>
                        <ul class="mt-3 space-y-3.5">
                            @foreach ($palestrantes as $p)
                                @php($iniciais = \Illuminate\Support\Str::upper(collect(explode(' ', $p->nome))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')))
                                <li>
                                    <a href="{{ route('palestrantes.show', $p->slug) }}" class="group flex items-center gap-3">
                                        @if ($p->foto_thumb_url)
                                            <img src="{{ $p->foto_thumb_url }}" alt="{{ $p->nome }}" loading="lazy" width="44" height="44" class="size-11 shrink-0 rounded-full object-cover">
                                        @else
                                            <span aria-hidden="true" class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary font-display text-sm font-semibold text-white">{{ $iniciais }}</span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="block truncate font-display font-semibold text-primary group-hover:underline">{{ $p->nome }}</span>
                                            <span class="text-xs text-secondary">Ver perfil completo →</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    @forelse ($palestrantes as $p)
                        <div class="overflow-hidden rounded-xl border border-border-muted bg-cream">
                            @if ($p->foto_thumb_url)
                                <img src="{{ $p->foto_thumb_url }}" alt="{{ $p->nome }}" loading="lazy" width="320" height="200" class="h-[200px] w-full object-cover">
                            @endif
                            <div class="p-5">
                                <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Palestrante</p>
                                <h2 class="mt-1 font-display text-xl font-semibold text-primary">
                                    <a href="{{ route('palestrantes.show', $p->slug) }}" class="hover:underline">{{ $p->nome }}</a>
                                </h2>
                                @if ($p->bio)
                                    <div class="mt-2 line-clamp-4 text-sm text-text-secondary">{!! \Illuminate\Support\Str::limit(strip_tags($p->bio), 200) !!}</div>
                                @endif
                                <a href="{{ route('palestrantes.show', $p->slug) }}" class="mt-3 inline-block text-sm font-semibold text-secondary hover:underline">Ver perfil completo →</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-text-muted">Palestrante a confirmar.</p>
                    @endforelse
                @endif

                {{-- Ações --}}
                <div class="space-y-2.5 rounded-xl border border-border-muted bg-white p-5">
                    @if ($ytId)
                        <a href="{{ $palestra->link_youtube }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill bg-[#FF0000] px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">▶ Assistir no YouTube</a>
                    @endif
                    @if (filled($palestra->slide))
                        <a href="{{ $palestra->slide_download_url }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill border border-primary px-4 py-2.5 text-sm font-semibold text-primary hover:bg-cream">⬇ Baixar slides</a>
                    @endif
                    @if ($googleAgenda)
                        <a href="{{ $googleAgenda }}" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:border-primary hover:text-primary">📅 Adicionar ao calendário</a>
                    @endif
                </div>

                {{-- Compartilhar + curtir --}}
                <div class="rounded-xl border border-border-muted bg-white p-5" data-acoes-palestra>
                    <p class="mb-3 text-sm text-text-muted">Compartilhar:</p>
                    @php($urlAtual = route('palestras.show', $palestra->slug))
                    <div class="flex flex-wrap items-center gap-2.5"
                         x-data="{ url: @js($urlAtual), titulo: @js($palestra->titulo), copiado: false,
                            copiar() { navigator.clipboard.writeText(this.url).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000); }); } }">
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white">f</span> Facebook
                        </a>
                        <a href="https://wa.me/?text={{ urlencode($palestra->titulo.' — '.$urlAtual) }}" target="_blank" rel="noopener noreferrer"
                           class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white">W</span> WhatsApp
                        </a>
                        <button type="button" @click="copiar()"
                                class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                            <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
                        </button>
                        <livewire:palestras.curtir :palestra="$palestra" />
                    </div>
                </div>
            </aside>
        </div>
    </section>

    {{-- S3: Anterior / Próxima --}}
    <section class="border-y border-border-muted bg-surface">
        <div class="mx-auto flex max-w-[1100px] flex-wrap justify-between gap-4 px-6 py-6">
            @if ($anterior)
                <a href="{{ route('palestras.show', $anterior->slug) }}" rel="prev" class="flex items-center gap-3 text-primary hover:underline">
                    <span aria-hidden="true" class="text-xl">‹</span>
                    <span><span class="block font-mono text-[10px] uppercase text-text-muted">Anterior</span><span class="font-semibold">{{ \Illuminate\Support\Str::limit($anterior->titulo, 38) }}</span></span>
                </a>
            @else <span></span> @endif
            @if ($proxima)
                <a href="{{ route('palestras.show', $proxima->slug) }}" rel="next" class="flex items-center gap-3 text-right text-primary hover:underline">
                    <span><span class="block font-mono text-[10px] uppercase text-text-muted">Próxima</span><span class="font-semibold">{{ \Illuminate\Support\Str::limit($proxima->titulo, 38) }}</span></span>
                    <span aria-hidden="true" class="text-xl">›</span>
                </a>
            @endif
        </div>
    </section>

    {{-- S4: Relacionadas --}}
    @include('palestras.partials.relacionadas')
</x-layout.app>
