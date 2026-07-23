@php
    use App\Enums\FormatoMensagem;
    use Illuminate\Support\Str;

    $urlPerfil = route('autores.show', $autor->slug);
    $selos = $resumo->selos();
    $ultima = $resumo->ultimaMensagem();
    $predominante = $resumo->predominante();

    // Chips só dos formatos presentes (em ordem de enum), evitando chips que filtram para vazio.
    $valoresPresentes = $resumo->porFormato()->pluck('valor');
    $formatosPresentes = collect(FormatoMensagem::cases())
        ->filter(fn (FormatoMensagem $f) => $valoresPresentes->contains($f->value))
        ->values();

    $rotuloContagem = fn (int $n) => $logado
        ? ($n === 1 ? 'disponível a você' : 'disponíveis a você')
        : ($n === 1 ? 'pública' : 'públicas');

    $tiles = [
        ['valor' => $resumo->total(), 'rotulo' => $logado ? 'Mensagens disponíveis a você' : 'Mensagens públicas', 'bg' => 'bg-cream'],
        ['valor' => $predominante ? $predominante->rotulo() : '—', 'rotulo' => 'Formato predominante', 'bg' => 'bg-[#EAF0F6]'],
        ['valor' => $ultima ? ucfirst(str_replace('.', '', $ultima->translatedFormat('M/Y'))) : '—', 'rotulo' => 'Última mensagem', 'bg' => 'bg-[#EAF2EC]'],
    ];
@endphp
<x-layout.app :title="$autor->nome"
              :description="Str::limit(strip_tags((string) ($autor->chamada ?: $autor->bio)), 155) ?: 'Autor espiritual do CEMA'">
    <x-slot:head>
        <script type="application/ld+json">
        @php
            echo json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $autor->nome,
                'image' => $autor->foto_url, // omitido quando null
                'description' => Str::limit(strip_tags((string) ($autor->bio ?: $autor->chamada)), 200),
                'url' => $urlPerfil,
                'worksFor' => ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'],
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        </script>
        <link rel="canonical" href="{{ $urlPerfil }}">
        @if ($autor->foto_url)
            <meta property="og:image" content="{{ $autor->foto_url }}">
        @endif
    </x-slot:head>

    <div x-data="autorMensagens({ itens: @js($itensFiltro) })">
        {{-- ===== HERO roxo: partículas + onda + breadcrumb + foto ou fallback + chamada + selos + CTA ===== --}}
        <section class="relative overflow-hidden text-white"
                 style="background:radial-gradient(circle at 86% 8%, rgba(242,168,30,0.22), transparent 42%), radial-gradient(circle at 20% 90%, rgba(110,159,203,0.28), transparent 55%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
            <x-ui.particulas />

            <div class="relative z-[2] mx-auto max-w-[1160px] px-6 pb-8 pt-6">
                <p class="mb-4 font-mono text-xs uppercase tracking-[0.18em] text-[#9db8e0]">Autor Espiritual · CEMA</p>
                <nav aria-label="Trilha de navegação" class="mb-7 flex flex-wrap items-center gap-2 text-[12.5px] text-[#9aa6cf]">
                    <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                    <a href="{{ route('autores.index') }}" class="hover:text-white">Autores Espirituais</a><span aria-hidden="true">›</span>
                    <span class="text-[#e7e9f4]" aria-current="page">{{ $autor->nome }}</span>
                </nav>

                <div class="flex flex-wrap items-end gap-9">
                    {{-- Foto 3:4 em moldura translúcida; sem foto → imagem de fallback (autor-fallback.svg). --}}
                    <div class="w-[186px] shrink-0 rounded-[22px] border border-white/16 bg-white/8 p-2 backdrop-blur-sm">
                        @if ($autor->foto_url)
                            <img src="{{ $autor->foto_url }}" alt="{{ $autor->nome }}" width="186" height="248"
                                 class="block aspect-[3/4] w-full rounded-[15px] object-cover">
                        @else
                            <img src="{{ asset('images/autor-fallback.svg') }}" alt="{{ $autor->nome }}" width="186" height="248"
                                 class="block aspect-[3/4] w-full rounded-[15px] object-cover">
                        @endif
                    </div>

                    <div class="min-w-[280px] flex-1 basis-[420px]">
                        <h1 class="mb-4 font-display font-semibold leading-[1.06] text-white [font-size:clamp(2.2rem,1.5rem+2.4vw,3.4rem)]">{{ $autor->nome }}</h1>
                        <div class="mb-[18px] h-1 w-16 rounded-full bg-gold"></div>
                        @if (filled($autor->chamada))
                            <p class="mb-5 max-w-[560px] font-serif italic text-white/85 [font-size:clamp(1.05rem,1rem+0.35vw,1.25rem)]">{{ $autor->chamada }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            @if ($selos->isNotEmpty())
                                <div class="flex flex-wrap gap-2.5">
                                    @foreach ($selos as $selo)
                                        <span class="inline-flex items-center gap-2 rounded-pill border border-white/20 bg-white/10 px-3.5 py-1.5 text-[12.5px] text-[#e7e9f4]">
                                            <span class="inline-block size-2 rounded-full" style="background:{{ $selo['cor'] }}"></span>{{ $selo['rotulo'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            @if ($mensagens->isNotEmpty())
                                <a href="#mensagens"
                                   class="inline-flex items-center gap-2 rounded-pill border border-white/22 bg-white/10 px-4 py-2 text-[12.5px] font-medium text-white transition hover:bg-white/15">
                                    Ver mensagens
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <x-ui.onda-hero />
        </section>

        {{-- ===== Corpo: coluna principal (tiles + grade + rodapé) + sidebar ===== --}}
        <section class="bg-surface">
            <div class="mx-auto flex max-w-[1160px] flex-col gap-8 px-6 py-10 desktop-sm:flex-row desktop-sm:items-start">
                <div class="min-w-0 flex-1">
                    {{-- 3 tiles (SEM "Curtidas" — F5) --}}
                    <div class="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(150px,1fr))]">
                        @foreach ($tiles as $tile)
                            <div class="{{ $tile['bg'] }} rounded-[14px] border border-border-muted px-4 py-[18px] text-center">
                                <p class="font-display text-[26px] font-bold leading-none text-primary">{{ $tile['valor'] }}</p>
                                <p class="mt-[7px] text-[11.5px] text-[#6a6685]">{{ $tile['rotulo'] }}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- Sobre {nome}: bio em prosa. Só renderiza quando há bio (D3). --}}
                    @if (filled($autor->bio))
                        <div class="mt-8 rounded-[18px] border border-border-muted bg-white p-7 shadow-card">
                            <h2 class="font-display text-xl font-semibold text-primary">Sobre {{ $autor->nome }}</h2>
                            <div class="mb-4 mt-2.5 h-[3.5px] w-[52px] rounded-sm bg-gold"></div>
                            {{-- bio é HTML saneado por clean('conteudo') no model — {!! !!} é seguro (mesmo caso do corpo da mensagem). --}}
                            <div class="cema-msg-prose">{!! $autor->bio !!}</div>
                        </div>
                    @endif

                    {{-- Grade das mensagens visíveis do autor --}}
                    <div id="mensagens" class="mt-10 scroll-mt-24">
                        <div class="mb-[18px] flex flex-wrap items-baseline justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
                                <h2 class="font-display text-[21px] font-semibold text-primary">Mensagens de {{ $autor->nome }}</h2>
                            </div>
                            {{-- Só o total de VISÍVEIS (nada de "de N" — não vaza a contagem de ocultas). --}}
                            <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-[#b08a2e]">{{ $resumo->total() }} {{ $rotuloContagem($resumo->total()) }}</p>
                        </div>

                        @if ($mensagens->isNotEmpty())
                            {{-- Controles: chips de formato (client-side) + ordenar --}}
                            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por formato">
                                    <button type="button" @click="formato = 'todos'"
                                            :aria-pressed="formato === 'todos'"
                                            :class="formato === 'todos' ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'"
                                            class="rounded-pill border px-4 py-1.5 text-[13px] font-medium transition">Todos</button>
                                    @foreach ($formatosPresentes as $f)
                                        <button type="button" @click="formato = '{{ $f->value }}'"
                                                :aria-pressed="formato === '{{ $f->value }}'"
                                                :class="formato === '{{ $f->value }}' ? 'border-primary bg-primary text-white' : 'border-border bg-white text-text-secondary hover:bg-surface'"
                                                class="rounded-pill border px-4 py-1.5 text-[13px] font-medium transition">{{ $f->rotulo() }}</button>
                                    @endforeach
                                </div>

                                <div class="flex items-center gap-2">
                                    <label for="ordenar-mensagens" class="whitespace-nowrap text-[13px] text-text-muted">Ordenar:</label>
                                    <select id="ordenar-mensagens" x-model="ordenar"
                                            class="cursor-pointer rounded-[10px] border border-border bg-white px-3 py-2 text-[13.5px] text-text-secondary outline-none">
                                        <option value="recente">Mais recentes</option>
                                        <option value="antiga">Mais antigas</option>
                                        <option value="az">Título (A–Z)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid gap-5 [grid-template-columns:repeat(auto-fill,minmax(262px,1fr))]">
                                @foreach ($mensagens as $m)
                                    <x-mensagem.card :mensagem="$m" variante="perfil"
                                                     x-show="visivel({{ $m->id }})"
                                                     x-bind:style="{ order: ordem({{ $m->id }}) }" />
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-xl border border-dashed border-border-muted bg-white px-6 py-10 text-center text-text-secondary">
                                {{ $logado ? 'Ainda não há mensagens deste autor que você possa ver.' : 'Ainda não há mensagens públicas deste autor.' }}
                            </p>
                        @endif

                        {{-- Rodapé condicional (O1): só quando há oculta hierárquica para ESTE usuário. Sem número (anti-PII). --}}
                        @if ($temRestritasOcultas)
                            <p class="mt-8 rounded-xl border border-dashed border-border bg-white/60 px-5 py-4 text-center text-[13.5px] leading-relaxed text-text-secondary">
                                @guest
                                    Há mensagens restritas a trabalhadores e médiuns.
                                    <a href="{{ route('login') }}" class="font-medium text-primary underline hover:text-secondary">Entre</a> para ver o que é seu.
                                @else
                                    Este autor tem mensagens restritas que você ainda não pode ver.
                                @endguest
                            </p>
                        @endif
                    </div>
                </div>

                {{-- ===== Sidebar: destaque + formatos + compartilhar ===== --}}
                <aside class="flex w-full shrink-0 flex-col gap-5 desktop-sm:sticky desktop-sm:top-24 desktop-sm:w-[340px]">
                    {{-- Em destaque: mensagem mais recente visível (some se não houver). --}}
                    @if ($destaque)
                        <div class="relative overflow-hidden rounded-2xl p-6 text-white shadow-card"
                             style="background:linear-gradient(150deg,#3a3266,#4E4483 65%,#5b4f97);">
                            <span class="absolute -right-8 -top-8 size-28 rounded-full bg-gold/[0.18]" aria-hidden="true"></span>
                            <p class="mb-2.5 font-mono text-[10px] uppercase tracking-[0.16em] text-[#F2C55C]">Em destaque</p>
                            <p class="mb-1 font-mono text-[11px] text-[#c7c0e6]">
                                {{ $destaque->formato?->rotulo() }}@if ($destaque->data_recebimento) · {{ $destaque->data_recebimento->translatedFormat('d M Y') }}@endif
                            </p>
                            <h3 class="mb-4 font-display text-lg font-semibold leading-snug">{{ $destaque->titulo }}</h3>
                            <a href="{{ route('mensagens.show', $destaque->slug) }}"
                               class="inline-flex rounded-pill bg-gold px-5 py-2.5 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ler mensagem</a>
                        </div>
                    @endif

                    {{-- Formatos: distribuição das visíveis (groupBy). --}}
                    @if ($resumo->porFormato()->isNotEmpty())
                        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
                            <h2 class="mb-3.5 font-display text-base font-semibold text-primary">Formatos</h2>
                            <div class="flex flex-col gap-0.5">
                                @foreach ($resumo->porFormato() as $item)
                                    <div class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm text-text-secondary">
                                        <span class="flex items-center gap-2.5">
                                            <span class="inline-block size-[9px] rounded-full" style="background:{{ $item['cor'] }}"></span>{{ $item['rotulo'] }}
                                        </span>
                                        <span class="font-mono text-xs text-[#9a93b4]">{{ $item['count'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Compartilhar autor (client-side; sem curtir — F5). --}}
                    <div x-data="{ copiado: false }" class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
                        <p class="mb-3.5 font-display text-sm font-semibold text-[#3a3266]">Compartilhar autor</p>
                        <div class="flex flex-wrap gap-2.5">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlPerfil) }}" target="_blank" rel="noopener"
                               aria-label="Compartilhar no Facebook" class="cema-share-btn bg-[#1877F2] text-white">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
                            </a>
                            <a href="https://wa.me/?text={{ urlencode($autor->nome.' — '.$urlPerfil) }}" target="_blank" rel="noopener"
                               aria-label="Compartilhar no WhatsApp" class="cema-share-btn bg-[#1FA855] text-white">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>
                            <button type="button" aria-label="Copiar link" class="cema-share-btn border border-border bg-surface text-primary"
                                    @click="navigator.clipboard.writeText('{{ $urlPerfil }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000); })">
                                <span x-show="!copiado"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span>
                                <span x-show="copiado" x-cloak class="text-xs font-semibold" aria-hidden="true">✓</span>
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</x-layout.app>
