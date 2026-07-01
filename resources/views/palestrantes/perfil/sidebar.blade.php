@php $urlPerfil = route('palestrantes.show', $palestrante->slug); @endphp
<div class="flex flex-col gap-5">
    {{-- Próxima palestra (sem fallback: some se não houver futura). --}}
    @if ($proxima)
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#3a3266] p-6 text-white shadow-card">
            <span class="absolute -right-8 -top-8 size-28 rounded-full bg-gold/[0.18]" aria-hidden="true"></span>
            <p class="mb-1 font-mono text-[10.5px] uppercase tracking-[0.16em] text-gold">Em destaque</p>
            <h2 class="mb-4 font-display text-lg font-semibold">Próxima palestra</h2>
            <div class="mb-4 flex items-center gap-3">
                <span class="cema-grad-{{ $palestrante->id % 8 }} grid size-12 shrink-0 place-items-center overflow-hidden rounded-full ring-2 ring-white/25">
                    @if ($palestrante->foto_thumb_url)
                        <img src="{{ $palestrante->foto_thumb_url }}" alt="" width="48" height="48" class="size-full object-cover">
                    @else
                        <span class="font-display text-sm font-semibold text-white/90" aria-hidden="true">{{ $palestrante->iniciais }}</span>
                    @endif
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ $palestrante->nome }}</p>
                    @if ($proxima->data_da_palestra)
                        <p class="font-mono text-xs text-[#c7c0e6]">{{ $proxima->data_da_palestra->translatedFormat('D, d M Y') }} · {{ $proxima->data_da_palestra->format('H\hi') }}</p>
                    @endif
                </div>
            </div>
            <h3 class="mb-4 font-display font-semibold leading-snug">{{ $proxima->titulo }}</h3>
            <a href="{{ route('palestras.show', $proxima->slug) }}"
               class="inline-flex rounded-pill bg-gold px-5 py-2 text-sm font-semibold text-[#3a2f00] transition hover:brightness-105">Ver palestra</a>
        </div>
    @endif

    {{-- Áreas de atuação (clicáveis = filtram o grid, mesmo estado dos chips). --}}
    @if ($areas->isNotEmpty())
        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
            <h2 class="mb-3.5 font-display text-base font-semibold text-primary">Áreas de atuação</h2>
            <div class="flex flex-col gap-0.5">
                @foreach ($areas as $areaItem)
                    <button type="button"
                            @click="selecionar('{{ $areaItem['slug'] }}')"
                            :aria-pressed="area === '{{ $areaItem['slug'] }}'"
                            :class="area === '{{ $areaItem['slug'] }}' ? 'bg-surface' : ''"
                            class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm text-text-secondary transition hover:bg-surface">
                        <span class="flex items-center gap-2.5">
                            <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[9px] rounded-full"></span>{{ $areaItem['nome'] }}
                        </span>
                        <span class="text-xs text-[#9a93b4]">{{ $areaItem['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Contato — preserva comportamento existente (flags mostrar_email/mostrar_telefone). --}}
    @if (($palestrante->mostrar_email && $palestrante->email) || ($palestrante->mostrar_telefone && $palestrante->telefone))
        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card">
            <h2 class="mb-3 font-display text-base font-semibold text-primary">Contato</h2>
            @if ($palestrante->mostrar_email && $palestrante->email)
                <p class="text-sm text-text-secondary"><a href="mailto:{{ $palestrante->email }}" class="underline hover:text-secondary">{{ $palestrante->email }}</a></p>
            @endif
            @if ($palestrante->mostrar_telefone && $palestrante->telefone)
                <p class="mt-1 text-sm text-text-secondary">{{ $palestrante->telefone }}</p>
            @endif
        </div>
    @endif

    {{-- Compartilhar (client-side). --}}
    <div x-data="{ copiado: false }" class="rounded-2xl border border-border-muted bg-white p-5 shadow-card">
        <p class="mb-3.5 font-display text-sm font-semibold text-[#3a3266]">Compartilhar palestrante</p>
        <div class="flex flex-wrap gap-2.5">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($urlPerfil) }}" target="_blank" rel="noopener"
               aria-label="Compartilhar no Facebook" class="cema-share-btn bg-[#1877F2] text-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
            </a>
            <a href="https://wa.me/?text={{ urlencode($palestrante->nome.' — '.$urlPerfil) }}" target="_blank" rel="noopener"
               aria-label="Compartilhar no WhatsApp" class="cema-share-btn bg-[#1FA855] text-white">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M.06 24l1.68-6.13A11.86 11.86 0 0 1 .14 11.9C.14 5.34 5.48 0 12.05 0a11.82 11.82 0 0 1 8.41 3.49 11.82 11.82 0 0 1 3.48 8.41c0 6.56-5.34 11.9-11.9 11.9a11.9 11.9 0 0 1-5.69-1.45L.06 24zM6.6 20.13c1.68 1 3.28 1.6 5.43 1.6 5.46 0 9.9-4.43 9.9-9.88 0-5.46-4.44-9.9-9.9-9.9-5.46 0-9.9 4.44-9.9 9.9 0 2.26.66 3.95 1.77 5.72l-.99 3.62 3.69-1.06z"/></svg>
            </a>
            <button type="button" aria-label="Copiar link" class="cema-share-btn border border-border bg-surface text-primary"
                    @click="navigator.clipboard.writeText('{{ $urlPerfil }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000); })">
                <span x-show="!copiado"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span>
                <span x-show="copiado" x-cloak class="text-xs font-semibold" aria-hidden="true">✓</span>
            </button>
        </div>
    </div>
</div>
