{{-- Grade de miniaturas (thumb) + lightbox acessível (web) da galeria do evento. --}}
@php($galeriaMedia = $evento->getMedia('galeria'))
<section class="bg-surface"
         x-data="{
            aberto: false,
            i: 0,
            origem: null,
            imagens: @js($galeriaMedia->map(fn ($m) => [
                'web' => $m->getUrl('web'),
                'alt' => $m->getCustomProperty('alt') ?? $evento->titulo,
            ])->values()),
            abrir(n, el) { this.i = n; this.origem = el; this.aberto = true; this.$nextTick(() => this.$refs.dialogo?.focus()); },
            fechar() { this.aberto = false; this.$nextTick(() => this.origem?.focus()); },
            prox() { this.i = (this.i + 1) % this.imagens.length; },
            ant() { this.i = (this.i - 1 + this.imagens.length) % this.imagens.length; },
         }">
    <div class="mx-auto max-w-[1100px] px-6 py-10">
        <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Galeria</h2>
        <div class="grid grid-cols-2 gap-3 tablet:grid-cols-3">
            @foreach ($galeriaMedia as $img)
                <button type="button"
                        @click="abrir({{ $loop->index }}, $event.currentTarget)"
                        aria-label="Ampliar imagem {{ $loop->iteration }} de {{ $galeriaMedia->count() }}"
                        class="group block overflow-hidden rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                    <img src="{{ $img->getUrl('thumb') }}"
                         alt="{{ $img->getCustomProperty('alt') ?? $evento->titulo }}"
                         loading="lazy" width="300" height="200"
                         class="aspect-[3/2] w-full object-cover transition duration-300 group-hover:scale-105">
                </button>
            @endforeach
        </div>
    </div>

    {{-- Lightbox: overlay fecha ao clicar fora; conteúdo interrompe a propagação --}}
    <div x-cloak x-show="aberto" x-transition.opacity
         @keydown.escape.window="aberto && fechar()"
         @click="fechar()"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 sm:p-8">
        <button type="button" @click="fechar()" aria-label="Fechar galeria"
                class="absolute right-4 top-4 z-10 flex size-11 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
        </button>

        <div x-ref="dialogo" tabindex="-1" role="dialog" aria-modal="true" aria-label="Galeria de imagens"
             @click.stop
             @keydown.arrow-right.prevent="prox()"
             @keydown.arrow-left.prevent="ant()"
             class="flex max-h-full w-full max-w-4xl flex-col items-center focus:outline-none">
            <img :src="imagens[i]?.web" :alt="imagens[i]?.alt"
                 class="max-h-[78vh] w-auto max-w-full rounded-lg object-contain shadow-2xl">

            <template x-if="imagens.length > 1">
                <div class="mt-4 flex items-center gap-6 text-white">
                    <button type="button" @click="ant()" aria-label="Imagem anterior"
                            class="flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                        </svg>
                    </button>
                    <span class="font-mono text-sm tabular-nums" aria-live="polite">
                        <span x-text="i + 1"></span> / <span x-text="imagens.length"></span>
                    </span>
                    <button type="button" @click="prox()" aria-label="Próxima imagem"
                            class="flex size-11 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</section>
