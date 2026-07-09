@php
    use Illuminate\Support\Str;
    $s = $evento->status_selo;
    $resumoTexto = trim(strip_tags((string) $evento->resumo));
    $descricaoSeo = Str::limit($resumoTexto, 155) ?: null;
    $intervalo = $evento->intervaloSchema();
    $jsonLd = json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => $evento->titulo,
        'startDate' => $intervalo['inicio'],
        'endDate' => $intervalo['fim'],
        'eventStatus' => 'https://schema.org/EventScheduled',
        'location' => ['@type' => 'Place', 'name' => config('cema.nome'), 'address' => config('cema.endereco')],
        'organizer' => ['@type' => 'Organization', 'name' => config('cema.nome')],
        'description' => $descricaoSeo ?: null,
        'image' => $evento->flyerUrl ?: null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

    $gcLocal = $evento->local ? $evento->local.' — '.config('cema.endereco') : config('cema.endereco');
    $googleAgenda = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.urlencode($evento->titulo)
        .'&dates='.$evento->googleCalendarDates()
        .'&details='.urlencode(route('eventos.show', $evento->slug))
        .'&location='.urlencode($gcLocal);

    $urlAtual = route('eventos.show', $evento->slug);
@endphp

<x-layout.app :title="$evento->titulo" :description="$descricaoSeo">
    <x-slot:head>
        <script type="application/ld+json">{!! $jsonLd !!}</script>
        @if ($evento->flyerUrl)<meta property="og:image" content="{{ $evento->flyerUrl }}">@endif
    </x-slot:head>

    {{-- S1: Hero (sempre roxo; partículas) --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('eventos.index') }}" class="hover:text-white">Eventos</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ Str::limit($evento->titulo, 40) }}</span>
            </nav>

            <div class="flex flex-wrap gap-2.5 text-sm">
                @if ($evento->categoria)
                    <span class="cema-evt-selo rounded-pill px-3 py-1.5 font-mono text-[11px] font-medium uppercase tracking-wide"
                          style="background: {{ $evento->categoria->cor }}; color: {{ $evento->categoria->cor_texto ?? '#fff' }};">
                        {{ $evento->categoria->nome }}
                    </span>
                @endif
                <span class="cema-evt-selo rounded-pill px-3 py-1.5 text-[11px] font-semibold"
                      style="background: {{ $s['cor'] }}; color: {{ $s['cor_texto'] }};">
                    {{ $s['rotulo'] }}
                </span>
            </div>

            <h1 class="mt-4 max-w-3xl font-display text-3xl font-semibold leading-tight text-balance md:text-5xl">{{ $evento->titulo }}</h1>

            @if ($resumoTexto)
                <p class="mt-3 max-w-2xl font-serif text-lg text-white/85">{{ $resumoTexto }}</p>
            @endif
        </div>
    </section>

    {{-- Barra de ações: compartilhar + adicionar à agenda --}}
    <section class="border-b border-border-muted bg-white">
        <div class="mx-auto max-w-[1100px] px-6 py-4"
             x-data="{ url: @js($urlAtual), copiado: false,
                copiar() { navigator.clipboard.writeText(this.url).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 2000); }); } }">
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-white"><x-icon.facebook class="size-3" /></span> Facebook
                </a>
                <a href="https://wa.me/?text={{ urlencode($evento->titulo.' — '.$urlAtual) }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-white"><x-icon.whatsapp class="size-3.5" /></span> WhatsApp
                </a>
                <button type="button" @click="copiar()"
                        class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
                </button>
                <a href="{{ $googleAgenda }}" target="_blank" rel="noopener"
                   class="flex items-center gap-2 rounded-pill border border-primary px-4 py-2 text-[13px] font-semibold text-primary hover:bg-cream">
                    📅 Adicionar à agenda
                </a>
                <button type="button" @click="$dispatch('open-assinar')"
                        class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    🔔 Assinar calendário
                </button>
            </div>
        </div>
    </section>

    {{-- S2: Conteúdo + sidebar sticky --}}
    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <div class="grid items-start gap-9 desktop-sm:grid-cols-[minmax(0,1fr)_320px]">
            {{-- Conteúdo --}}
            <div>
                @if ($evento->conteudo)
                    <div class="max-w-none text-justify hyphens-auto [overflow-wrap:anywhere] font-serif text-[16px] leading-[1.82] text-[#3a3553]
                        [&_p]:mb-[18px] [&>p:first-of-type]:text-lg [&>p:first-of-type]:font-medium
                        [&_a]:text-secondary [&_a]:underline [&_a]:[overflow-wrap:anywhere]">
                        {!! $evento->conteudo !!}
                    </div>
                @endif

                @include('eventos._servico')
            </div>

            {{-- Sidebar sticky --}}
            <aside class="space-y-5 desktop-sm:sticky desktop-sm:top-24">
                @if ($evento->flyerUrl)
                    <div class="overflow-hidden rounded-xl border border-border-muted bg-cream">
                        <img src="{{ $evento->flyerUrl }}" alt="{{ $evento->titulo }}" loading="lazy" width="320" height="400" class="w-full object-cover">
                    </div>
                @endif

                <div class="rounded-xl border border-border-muted bg-white p-5">
                    <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-accent">Quando e onde</p>
                    <p class="mt-2 font-semibold text-text-ink">{{ $evento->periodo }}</p>
                    <p class="mt-1 text-sm text-text-secondary">{{ $evento->local ?: 'Local a confirmar' }}</p>
                </div>

                {{-- CTAs --}}
                <div class="space-y-2.5 rounded-xl border border-border-muted bg-white p-5">
                    <a href="{{ $googleAgenda }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 rounded-pill bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">📅 Adicionar à agenda</a>
                    <a href="https://wa.me/?text={{ urlencode($evento->titulo.' — '.$urlAtual) }}" target="_blank" rel="noopener noreferrer"
                       class="flex items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 text-sm font-semibold text-text-secondary hover:border-primary hover:text-primary">
                        <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-white"><x-icon.whatsapp class="size-3.5" /></span> Compartilhar no WhatsApp
                    </a>
                </div>
            </aside>
        </div>
    </section>

    {{-- Galeria (só se houver mídia) --}}
    @if ($evento->getMedia('galeria')->isNotEmpty())
        @include('eventos._galeria')
    @endif

    {{-- Outros eventos --}}
    @include('eventos._relacionados')

    <x-eventos.assinar-modal :feed-url="route('eventos.feed-ics')" />
</x-layout.app>
