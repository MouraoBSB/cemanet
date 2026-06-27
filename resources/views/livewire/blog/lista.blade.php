<div>
    {{-- ===== Herói roxo imersivo (variante B — Semente de Luz) ===== --}}
    <section class="relative flex min-h-[480px] items-end overflow-hidden"
             style="background:linear-gradient(165deg,#2f2952 0%,#3c3468 50%,#4E4483 100%);"
             aria-label="Destaque">

        {{-- Foto de capa do destaque (fundo, lado direito) com véu roxo p/ legibilidade --}}
        @if ($destaque && $destaque->getFirstMedia(\App\Models\Post::COLECAO_DESTACADA))
            <img src="{{ $destaque->getFirstMediaUrl(\App\Models\Post::COLECAO_DESTACADA, 'web') }}" alt="" aria-hidden="true"
                 class="pointer-events-none absolute inset-y-0 right-0 h-full w-full object-cover object-center opacity-70 tablet:w-[58%]">
            <div aria-hidden="true" class="pointer-events-none absolute inset-0"
                 style="background:linear-gradient(95deg,#2f2952 0%,#2f2952 26%,rgba(47,41,82,0.82) 46%,rgba(60,52,104,0.45) 72%,rgba(78,68,131,0.18) 100%);"></div>
        @endif

        {{-- Raios cônicos + halo dourado (CSS puro, reduced-motion seguro) --}}
        <div class="sl-hero-raios" aria-hidden="true"></div>
        <div class="sl-hero-halo" aria-hidden="true"></div>

        {{-- Partículas ascendentes (sementes de luz) --}}
        <span aria-hidden="true" class="sl-hero-semente"
              style="left:62%;bottom:40px;width:6px;height:6px;background:rgba(242,168,30,0.8);animation-delay:0s;animation-duration:6s;"></span>
        <span aria-hidden="true" class="sl-hero-semente"
              style="left:70%;bottom:60px;width:5px;height:5px;background:rgba(242,168,30,0.7);animation-delay:1.2s;animation-duration:7.5s;"></span>
        <span aria-hidden="true" class="sl-hero-semente"
              style="left:76%;bottom:30px;width:7px;height:7px;background:rgba(255,235,190,0.85);animation-delay:2.1s;animation-duration:6.8s;"></span>
        <span aria-hidden="true" class="sl-hero-semente"
              style="left:66%;bottom:80px;width:4px;height:4px;background:rgba(110,159,203,0.7);animation-delay:0.6s;animation-duration:8s;"></span>
        <span aria-hidden="true" class="sl-hero-semente"
              style="left:82%;bottom:50px;width:5px;height:5px;background:rgba(242,168,30,0.7);animation-delay:3s;animation-duration:7s;"></span>

        {{-- Conteúdo do destaque (container centralizado, alinhado à página) --}}
        <div class="relative z-10 mx-auto w-full max-w-[1180px] px-6 pb-14 pt-16">
          <div class="max-w-[600px]">
            <div class="mb-4 flex flex-wrap items-center gap-2.5">
                <span class="rounded-pill bg-gold px-3 py-1 font-mono text-[10.5px] tracking-[.2em] uppercase text-footer-bg">Sementeira de Luz</span>
                <span class="font-mono text-[11px] tracking-[.14em] uppercase text-gold">Em destaque</span>
            </div>

            @if ($destaque)
                <h1 class="font-display text-[clamp(2.1rem,1.5rem+2vw,2.85rem)] font-semibold leading-[1.1] tracking-[-0.01em] text-white">
                    <a href="{{ $destaque->urlPublica }}" class="hover:underline">{{ $destaque->titulo }}</a>
                </h1>
                @if ($destaque->resumo)
                    <p class="mt-4 max-w-[520px] text-[16px] leading-[1.65] text-[#d8d2ec]">{{ $destaque->resumo }}</p>
                @endif
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <a href="{{ $destaque->urlPublica }}"
                       class="inline-flex items-center gap-2 rounded-pill bg-gold px-6 py-3 font-display text-[14.5px] font-semibold text-footer-bg transition hover:bg-[#e09a17]">
                        Ler artigo <span aria-hidden="true">→</span>
                    </a>
                    @if ($destaque->data_publicacao)
                        <span class="font-mono text-[11.5px] text-[#bdb4dd]">
                            {{ $destaque->data_publicacao->translatedFormat('d M Y') }}
                            @if ($destaque->tempo_leitura_min)
                                · {{ $destaque->tempo_leitura_min }} min
                            @endif
                        </span>
                    @endif
                </div>
            @else
                <h1 class="font-display text-4xl font-semibold text-white">Sementeira de Luz</h1>
                <p class="mt-3 text-[16px] text-[#d8d2ec]">Consolo, conhecimento e reflexão à luz do Evangelho.</p>
            @endif
          </div>
        </div>
    </section>

    {{-- ===== Chips de categoria ===== --}}
    <div class="border-b border-border-muted bg-white">
        <div class="mx-auto flex max-w-[1180px] flex-wrap items-center gap-2.5 px-6 py-4">
            <span class="mr-1 font-mono text-[10.5px] tracking-[.12em] uppercase text-text-muted">Categorias</span>

            <button type="button"
                    wire:click="$set('categoria', '')"
                    aria-pressed="{{ $categoria === '' ? 'true' : 'false' }}"
                    class="{{ $categoria === '' ? 'bg-primary text-white' : 'border border-border bg-white text-primary hover:border-primary' }} rounded-pill px-4 py-2 font-sans text-[13px] transition">
                Todas
            </button>

            @foreach ($categorias as $cat)
                <button type="button"
                        wire:click="$set('categoria', '{{ $cat->slug }}')"
                        aria-pressed="{{ $categoria === $cat->slug ? 'true' : 'false' }}"
                        class="{{ $categoria === $cat->slug ? 'bg-primary text-white' : 'border border-border bg-white hover:border-primary' }} rounded-pill px-4 py-2 font-sans text-[13px] transition"
                        style="{{ $categoria === $cat->slug ? '' : 'color:'.($cat->cor ?? '#4E4483') }}">
                    {{ $cat->nome }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- ===== Corpo: grid principal + sidebar ===== --}}
    <section class="mx-auto max-w-[1180px] px-6 py-10">
        <div class="grid gap-10 desktop-sm:grid-cols-[1fr_312px] desktop-sm:items-start">

            {{-- Coluna principal --}}
            <div>
                <div class="mb-6 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-display text-[22px] font-semibold text-primary">Últimas publicações</h2>
                    <div class="flex items-center gap-3">
                        <span class="font-mono text-[11px] text-text-muted">{{ $posts->total() }} publicações</span>
                        <label for="blog-ordenar" class="sr-only">Ordenar publicações</label>
                        <select id="blog-ordenar" wire:model.live="ordenar"
                                class="rounded-md border border-border bg-white px-2 py-1 font-mono text-[11px] text-text-secondary">
                            <option value="recente">Mais recentes</option>
                            <option value="antiga">Mais antigas</option>
                        </select>
                    </div>
                </div>

                @if ($posts->isEmpty())
                    <p class="rounded-lg border border-border-muted bg-surface px-6 py-10 text-center text-text-muted">
                        Nenhuma publicação encontrada.
                    </p>
                @else
                    {{-- 1º card: layout horizontal com imagem --}}
                    @php($primeiro = $posts->first())
                    <article class="mb-6 grid cursor-pointer grid-cols-1 gap-5 border-b border-border-muted pb-6 tablet:grid-cols-[230px_1fr]"
                             wire:key="post-destaque-{{ $primeiro->id }}">
                        @if ($primeiro->getFirstMedia(\App\Models\Post::COLECAO_DESTACADA))
                            <a href="{{ $primeiro->urlPublica }}" class="block overflow-hidden rounded-xl"
                               style="height:160px;">
                                <img src="{{ $primeiro->getFirstMediaUrl(\App\Models\Post::COLECAO_DESTACADA, 'web') }}"
                                     alt="{{ $primeiro->imagem_destacada_alt ?? $primeiro->titulo }}"
                                     loading="lazy" width="230" height="160"
                                     class="size-full object-cover transition duration-300 hover:scale-[1.03]">
                            </a>
                        @else
                            <a href="{{ $primeiro->urlPublica }}"
                               class="flex items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-primary to-footer-bg"
                               style="height:160px;">
                                <img src="{{ asset('images/logos/logo-icone.png') }}" alt="" class="h-10 w-auto opacity-80">
                            </a>
                        @endif
                        <div>
                            @if ($primeiro->categoriaPrincipal)
                                <p class="mb-2 font-mono text-[10.5px] tracking-[.12em] uppercase"
                                   style="color:{{ $primeiro->corCategoria }}">
                                    {{ $primeiro->categoriaPrincipal->nome }}
                                </p>
                            @endif
                            <h3 class="font-display text-[21px] font-semibold leading-[1.18] text-footer-bg">
                                <a href="{{ $primeiro->urlPublica }}" class="hover:underline">{{ $primeiro->titulo }}</a>
                            </h3>
                            @if ($primeiro->resumo)
                                <p class="mt-2 text-[14px] leading-[1.6] text-[#5b5766]">{{ $primeiro->resumo }}</p>
                            @endif
                            <div class="mt-3 font-mono text-[10.5px] text-text-muted">
                                @if ($primeiro->data_publicacao)
                                    {{ $primeiro->data_publicacao->translatedFormat('d M Y') }}
                                @endif
                                @if ($primeiro->tempo_leitura_min)
                                    · {{ $primeiro->tempo_leitura_min }} min
                                @endif
                            </div>
                        </div>
                    </article>

                    {{-- Grid restante 2 colunas --}}
                    <div class="grid gap-6 mobile:grid-cols-2">
                        @foreach ($posts->slice(1) as $post)
                            <x-blog.card :post="$post" wire:key="post-{{ $post->id }}" />
                        @endforeach
                    </div>
                @endif

                {{-- Paginação --}}
                <div class="mt-8 flex justify-center">
                    {{ $posts->onEachSide(1)->links() }}
                </div>
            </div>

            {{-- Barra lateral --}}
            <aside class="flex flex-col gap-6">

                {{-- Mais lidas --}}
                <div class="rounded-xl border border-border-muted bg-surface p-6">
                    <p class="mb-4 font-mono text-[10.5px] tracking-[.12em] uppercase text-orange">Mais lidas</p>
                    @if ($maisLidas->isEmpty())
                        <p class="text-sm text-text-muted">Nenhuma publicação ainda.</p>
                    @else
                        <ol class="flex flex-col gap-4">
                            @foreach ($maisLidas as $i => $lida)
                                <li class="flex items-start gap-3">
                                    <span class="font-display text-[22px] font-bold leading-none text-[#e3dcef]">
                                        {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                    <a href="{{ $lida->urlPublica }}"
                                       class="font-display text-[14px] font-medium leading-[1.3] text-footer-bg hover:underline">
                                        {{ $lida->titulo }}
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                {{-- Reflexão do dia --}}
                @if ($reflexao)
                    <div class="relative overflow-hidden rounded-xl p-6 text-white"
                         style="background:linear-gradient(160deg,#4E4483,#3a3266);">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-40 w-40 rounded-full"
                             style="background:radial-gradient(circle,rgba(242,168,30,0.4),rgba(242,168,30,0));"></div>
                        <p class="relative mb-3 font-mono text-[10.5px] tracking-[.12em] uppercase text-gold">Reflexão do dia</p>
                        <p class="relative font-serif text-[17px] leading-[1.55]">"{{ $reflexao }}"</p>
                    </div>
                @endif

                {{-- Navegar por categoria --}}
                <div class="rounded-xl border border-border-muted p-6">
                    <p class="mb-4 font-mono text-[10.5px] tracking-[.12em] uppercase text-text-muted">Navegar por categoria</p>
                    <ul class="flex flex-col divide-y divide-[#F2F1F4]">
                        @foreach ($categorias as $cat)
                            @if ($cat->posts_publicados_count > 0)
                                <li>
                                    <button type="button"
                                            wire:click="$set('categoria', '{{ $cat->slug }}')"
                                            class="flex w-full justify-between py-2.5 text-left text-[13.5px] text-footer-bg hover:text-primary">
                                        <span>{{ $cat->nome }}</span>
                                        <span class="text-[#9a93a8]">{{ $cat->posts_publicados_count }}</span>
                                    </button>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>

            </aside>
        </div>
    </section>

    {{-- ===== Faixa de newsletter (somente visual) ===== --}}
    <section class="mt-8 border-t border-border-muted bg-cream">
        <div class="mx-auto flex max-w-[1180px] flex-wrap items-center gap-8 px-6 py-12">
            <div class="min-w-[280px] flex-1">
                <h3 class="font-display text-[26px] font-semibold text-primary">Receba a sementeira toda semana</h3>
                <p class="mt-1.5 text-[15px] text-[#5b5766]">Uma reflexão, a agenda e as novidades da casa — direto no seu e-mail.</p>
            </div>
            <form class="flex min-w-[340px] gap-2.5" onsubmit="return false;" aria-label="Inscrição na newsletter">
                <label for="newsletter-email" class="sr-only">Seu melhor e-mail</label>
                <input id="newsletter-email" type="email" placeholder="Seu melhor e-mail" autocomplete="email"
                       class="flex-1 rounded-pill border border-border bg-white px-5 py-3.5 text-[14px] outline-none focus:border-primary">
                <button type="submit"
                        class="rounded-pill bg-primary px-7 py-3.5 font-display text-[14px] font-semibold text-white transition hover:bg-footer-bg">
                    Inscrever
                </button>
            </form>
        </div>
    </section>
</div>
