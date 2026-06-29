@php($ytId = $palestra->youtube_id)
<div class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-br from-footer-bg to-primary">
    @if ($ytId)
        <div x-data="{ aberto: false }">
            <button type="button" x-show="!aberto" @click="aberto = true"
                    class="group relative flex aspect-video w-full items-center justify-center"
                    aria-label="Reproduzir vídeo: {{ $palestra->titulo }}">
                <img src="{{ $palestra->youtube_thumb }}" alt=""
                     loading="lazy" class="absolute inset-0 size-full object-cover opacity-70"
                     onerror="this.style.display='none'">
                <span class="absolute left-4 top-4 flex items-center gap-2 rounded-pill bg-black/40 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#FF0000] text-[10px]">▶</span> CEMA TV
                </span>
                <span class="relative flex size-16 items-center justify-center rounded-full bg-[#FF0000] text-2xl text-white shadow-lg transition group-hover:scale-105">▶</span>
                <span class="absolute bottom-4 text-sm font-semibold text-white">Assista no YouTube</span>
            </button>
            <template x-if="aberto">
                <iframe class="aspect-video w-full" src="https://www.youtube.com/embed/{{ $ytId }}?autoplay=1"
                        title="Vídeo: {{ $palestra->titulo }}" loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </template>
        </div>
    @else
        <div class="flex aspect-video w-full flex-col items-center justify-center gap-3 text-center text-white">
            <span class="flex size-14 items-center justify-center rounded-full bg-white/15 text-2xl">▶</span>
            <p class="font-display text-lg font-semibold">Vídeo em breve</p>
            <p class="max-w-xs text-sm text-white/80">O vídeo desta palestra estará disponível em breve.</p>
        </div>
    @endif
</div>
