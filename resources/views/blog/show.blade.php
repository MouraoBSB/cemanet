{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26 --}}
<x-layout.app :title="$post->titulo" :description="$post->resumo">

    {{-- Barra de progresso de leitura (fixed, topo) --}}
    <div
        aria-hidden="true"
        x-data="{
            progresso: 0,
            reduzido: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
            atualizar() {
                const alt = document.documentElement.scrollHeight - window.innerHeight;
                this.progresso = alt > 0 ? Math.min(100, (window.scrollY / alt) * 100) : 0;
            }
        }"
        x-init="if (!reduzido) { window.addEventListener('scroll', () => atualizar(), { passive: true }); }"
        x-show="!reduzido"
        class="fixed left-0 top-0 z-[200] h-[3px] w-full bg-black/10"
    >
        <div
            class="h-full bg-gradient-to-r from-primary to-secondary transition-none"
            :style="'width:' + progresso + '%'"
        ></div>
    </div>

    {{-- S1: Herói escuro --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        {{-- Decoração visual: raios dourados e sementes flutuantes (igual ao hero de listagem) --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0">
            <div class="sl-hero-raios"></div>
            <div class="sl-hero-halo"></div>
            <span class="sl-hero-semente" style="left:12%;width:8px;height:8px;background:rgba(242,168,30,.55);bottom:-10px;animation-delay:0s;animation-duration:6s"></span>
            <span class="sl-hero-semente" style="left:35%;width:5px;height:5px;background:rgba(255,255,255,.30);bottom:-10px;animation-delay:2s;animation-duration:8s"></span>
            <span class="sl-hero-semente" style="left:60%;width:10px;height:10px;background:rgba(242,168,30,.40);bottom:-10px;animation-delay:4s;animation-duration:7s"></span>
            <span class="sl-hero-semente" style="left:80%;width:6px;height:6px;background:rgba(255,255,255,.20);bottom:-10px;animation-delay:1s;animation-duration:9s"></span>
        </div>

        <div class="relative mx-auto max-w-[780px] px-6 py-14 md:py-20">
            {{-- Breadcrumb --}}
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a>
                <span aria-hidden="true">›</span>
                <a href="{{ route('blog.index') }}" class="hover:text-white">Sementeira de Luz</a>
                <span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ \Illuminate\Support\Str::limit($post->titulo, 45) }}</span>
            </nav>

            {{-- Chip de categoria --}}
            @if ($post->categoriaPrincipal)
                <span class="mb-4 inline-block rounded-pill px-3.5 py-1 font-mono text-[11px] font-semibold uppercase tracking-[.12em]"
                      style="background:{{ $post->corCategoria }}22; color:{{ $post->corCategoria }}; border:1px solid {{ $post->corCategoria }}55">
                    {{ $post->categoriaPrincipal->nome }}
                </span>
            @endif

            {{-- Título --}}
            <h1 class="mt-2 max-w-2xl font-display text-3xl font-semibold leading-tight text-white md:text-5xl">
                {{ $post->titulo }}
            </h1>

            @if ($post->resumo)
                <p class="mt-4 max-w-xl text-lg leading-relaxed text-white/85">{{ $post->resumo }}</p>
            @endif

            {{-- Meta --}}
            <div class="mt-6 flex flex-wrap items-center gap-4 font-mono text-xs text-white/60">
                @if ($post->data_publicacao)
                    <time datetime="{{ $post->data_publicacao->toIso8601String() }}">
                        {{ $post->data_publicacao->translatedFormat('d \d\e F \d\e Y') }}
                    </time>
                @endif
                @if ($post->tempo_leitura_min)
                    <span>· {{ $post->tempo_leitura_min }} min de leitura</span>
                @endif
            </div>
        </div>
    </section>

    {{-- S2: Trilho de compartilhar --}}
    <section class="border-b border-border-muted bg-white" aria-label="Compartilhar este artigo">
        <div class="mx-auto flex max-w-[780px] flex-wrap items-center gap-2.5 px-6 py-4">
            <span class="text-sm text-text-muted">Compartilhar:</span>
            @php($urlAtual = route('blog.show', $post->slug))
            <div class="flex flex-wrap items-center gap-2.5"
                 x-data="{
                     url: @js($urlAtual),
                     titulo: @js($post->titulo),
                     copiado: false,
                     curtido: $persist(false).as('curtida_post_{{ $post->id }}'),
                     copiar() {
                         navigator.clipboard.writeText(this.url).then(() => {
                             this.copiado = true;
                             setTimeout(() => this.copiado = false, 2000);
                         });
                     },
                     async compartilhar() {
                         if (navigator.share) {
                             try { await navigator.share({ title: this.titulo, url: this.url }); } catch (e) {}
                         }
                     }
                 }">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlAtual) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#3b5998] text-[12px] font-bold text-white">f</span>
                    Facebook
                </a>
                <a href="https://wa.me/?text={{ urlencode($post->titulo.' — '.$urlAtual) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#25d366] text-[11px] font-bold text-white">W</span>
                    WhatsApp
                </a>
                <button type="button" @click="compartilhar()" x-show="navigator.share" x-cloak
                        class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    Compartilhar…
                </button>
                <button type="button" @click="copiar()"
                        class="rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold text-primary hover:bg-surface">
                    <span x-text="copiado ? 'Link copiado!' : 'Copiar link'">Copiar link</span>
                </button>
                <button type="button" @click="curtido = !curtido" :aria-pressed="curtido"
                        class="ml-auto flex items-center gap-2 rounded-pill border border-border bg-white px-4 py-2 text-[13px] font-semibold transition"
                        :class="curtido ? 'text-danger border-danger' : 'text-primary'">
                    <span x-text="curtido ? '♥' : '♡'" aria-hidden="true"></span>
                    <span x-text="curtido ? 'Curtido' : 'Curtir'">Curtir</span>
                </button>
            </div>
        </div>
    </section>

    {{-- S3: Corpo do artigo --}}
    <section class="mx-auto max-w-[780px] px-6 py-12">
        {{-- Conteúdo principal (já sanitizado pelo model) --}}
        <div class="prose-blog
            text-text-secondary leading-[1.8] text-[17px]
            [&_p]:mb-5
            [&_h2]:mt-10 [&_h2]:mb-4 [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-primary
            [&_h3]:mt-8 [&_h3]:mb-3 [&_h3]:font-display [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-text-ink
            [&_blockquote]:my-8 [&_blockquote]:border-l-4 [&_blockquote]:border-gold [&_blockquote]:bg-cream/60 [&_blockquote]:px-6 [&_blockquote]:py-4 [&_blockquote]:font-display [&_blockquote]:text-xl [&_blockquote]:italic [&_blockquote]:text-primary
            [&_ul]:mb-5 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:space-y-1
            [&_ol]:mb-5 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:space-y-1
            [&_li]:text-text-secondary
            [&_a]:text-secondary [&_a]:underline [&_a]:hover:text-primary
            [&_strong]:font-semibold [&_strong]:text-text-ink
            [&_img]:mx-auto [&_img]:rounded-xl [&_img]:shadow-card">
            {!! $post->conteudo !!}
        </div>

        {{-- Galeria de imagens com lightbox --}}
        @if ($post->imagens->isNotEmpty())
            <div class="mt-10"
                 x-data="{
                     aberta: false,
                     imgAtual: 0,
                     imagens: @js($post->imagens->map(fn($i) => ['src' => asset('storage/'.$i->caminho), 'alt' => $i->alt ?? ''])->values()),
                     abrir(i) { this.imgAtual = i; this.aberta = true; },
                     fechar() { this.aberta = false; },
                     anterior() { this.imgAtual = (this.imgAtual - 1 + this.imagens.length) % this.imagens.length; },
                     proxima() { this.imgAtual = (this.imgAtual + 1) % this.imagens.length; }
                 }"
                 @keydown.escape.window="fechar()"
            >
                <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Galeria</h2>
                <div class="grid grid-cols-2 gap-3 tablet:grid-cols-3">
                    @foreach ($post->imagens as $i => $img)
                        <button type="button"
                                @click="abrir({{ $i }})"
                                class="group overflow-hidden rounded-xl focus-visible:outline-2 focus-visible:outline-primary"
                                aria-label="Ampliar imagem {{ $loop->iteration }}">
                            <img src="{{ asset('storage/'.$img->caminho) }}"
                                 alt="{{ $img->alt ?? $post->titulo }}"
                                 loading="lazy"
                                 width="300" height="200"
                                 class="size-full cursor-zoom-in object-cover transition duration-300 group-hover:scale-[1.04]"
                                 style="height:180px">
                        </button>
                    @endforeach
                </div>

                {{-- Lightbox overlay --}}
                <div x-show="aberta" x-cloak
                     @click.self="fechar()"
                     class="lightbox fixed inset-0 z-[300] flex items-center justify-center bg-black/90 p-4"
                     role="dialog" aria-modal="true" aria-label="Galeria ampliada">
                    {{-- Fechar --}}
                    <button type="button" @click="fechar()"
                            class="absolute right-4 top-4 flex size-10 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/40"
                            aria-label="Fechar galeria">
                        <span aria-hidden="true" class="text-xl leading-none">×</span>
                    </button>

                    {{-- Navegar: anterior --}}
                    <button type="button" @click="anterior()"
                            class="absolute left-4 top-1/2 -translate-y-1/2 flex size-10 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/40"
                            aria-label="Imagem anterior">
                        <span aria-hidden="true">‹</span>
                    </button>

                    {{-- Imagem ampliada --}}
                    <img :src="imagens[imgAtual].src" :alt="imagens[imgAtual].alt"
                         class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-elevated">

                    {{-- Navegar: próxima --}}
                    <button type="button" @click="proxima()"
                            class="absolute right-4 top-1/2 -translate-y-1/2 flex size-10 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/40"
                            aria-label="Próxima imagem">
                        <span aria-hidden="true">›</span>
                    </button>

                    {{-- Contador --}}
                    <p class="absolute bottom-4 left-1/2 -translate-x-1/2 font-mono text-xs text-white/70">
                        <span x-text="imgAtual + 1"></span> / {{ $post->imagens->count() }}
                    </p>
                </div>
            </div>
        @endif

        {{-- FAQ acordeão --}}
        @if ($post->faqs->isNotEmpty())
            <div class="mt-10">
                <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Perguntas frequentes</h2>
                <div class="flex flex-col gap-2.5">
                    @foreach ($post->faqs as $faq)
                        <details class="group overflow-hidden rounded-xl border border-border-muted bg-white">
                            <summary class="flex cursor-pointer items-center justify-between gap-4 px-5 py-4 font-display font-medium text-text-ink">
                                {{ $faq->pergunta }}
                                <span aria-hidden="true"
                                      class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cream text-primary transition group-open:rotate-45">+</span>
                            </summary>
                            @if ($faq->resposta)
                                <div class="px-5 pb-5 text-sm leading-relaxed text-text-secondary">{{ $faq->resposta }}</div>
                            @endif
                        </details>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tags --}}
        @if ($post->tags->isNotEmpty())
            <div class="mt-10">
                <p class="font-mono text-[11px] uppercase tracking-[0.1em] text-text-muted">Tags</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($post->tags as $tag)
                        <span class="rounded-pill border border-border bg-surface px-3.5 py-1.5 text-[13px] text-text-secondary">
                            {{ $tag->nome }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    {{-- S4: Continue semeando (relacionados) --}}
    @if ($relacionados->isNotEmpty())
        <section class="border-t border-border-muted bg-surface py-14">
            <div class="mx-auto max-w-[1100px] px-6">
                <h2 class="mb-8 font-display text-2xl font-semibold text-primary">Continue semeando</h2>
                <div class="grid gap-7 tablet:grid-cols-2 desktop-sm:grid-cols-3">
                    @foreach ($relacionados as $rel)
                        <x-blog.card :post="$rel" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- S5: Navegação anterior/próxima --}}
    @if ($anterior || $proxima)
        <section class="border-y border-border-muted bg-white">
            <div class="mx-auto flex max-w-[780px] flex-wrap justify-between gap-4 px-6 py-6">
                @if ($anterior)
                    <a href="{{ route('blog.show', $anterior->slug) }}" rel="prev"
                       class="flex items-center gap-3 text-primary hover:underline">
                        <span aria-hidden="true" class="text-xl">‹</span>
                        <span>
                            <span class="block font-mono text-[10px] uppercase text-text-muted">Anterior</span>
                            <span class="font-semibold">{{ \Illuminate\Support\Str::limit($anterior->titulo, 38) }}</span>
                        </span>
                    </a>
                @else
                    <span></span>
                @endif

                @if ($proxima)
                    <a href="{{ route('blog.show', $proxima->slug) }}" rel="next"
                       class="flex items-center gap-3 text-right text-primary hover:underline">
                        <span>
                            <span class="block font-mono text-[10px] uppercase text-text-muted">Próxima</span>
                            <span class="font-semibold">{{ \Illuminate\Support\Str::limit($proxima->titulo, 38) }}</span>
                        </span>
                        <span aria-hidden="true" class="text-xl">›</span>
                    </a>
                @endif
            </div>
        </section>
    @endif

</x-layout.app>
