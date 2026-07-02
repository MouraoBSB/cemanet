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

    {{-- Áreas de atuação: cada tema navega para a archive filtrada. Sanfona: mostra 5, expande o resto. --}}
    @if ($areas->isNotEmpty())
        <div class="rounded-2xl border border-border-muted bg-white p-6 shadow-card" x-data="{ abertas: false }">
            <h2 class="mb-3.5 font-display text-base font-semibold text-primary">Áreas de atuação</h2>
            <div class="flex flex-col gap-0.5">
                @foreach ($areas as $areaItem)
                    <a href="{{ route('palestras.index', ['assunto' => $areaItem['slug']]) }}"
                       @if ($loop->index >= 5) x-show="abertas" x-cloak @endif
                       class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm text-text-secondary transition hover:bg-surface">
                        <span class="flex items-center gap-2.5">
                            <span class="cema-dot-{{ $areaItem['cor'] }} inline-block size-[9px] rounded-full"></span>{{ $areaItem['nome'] }}
                        </span>
                        <span class="text-xs text-[#9a93b4]">{{ $areaItem['count'] }}</span>
                    </a>
                @endforeach
            </div>
            @if ($areas->count() > 5)
                <button type="button" @click="abertas = !abertas"
                        class="mt-3 inline-flex items-center gap-1 text-[13px] font-medium text-primary hover:underline">
                    <span x-show="!abertas">Ver mais {{ $areas->count() - 5 }} ↓</span>
                    <span x-show="abertas" x-cloak>Ver menos ↑</span>
                </button>
            @endif
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
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </a>
            <button type="button" aria-label="Copiar link" class="cema-share-btn border border-border bg-surface text-primary"
                    @click="navigator.clipboard.writeText('{{ $urlPerfil }}').then(() => { copiado = true; setTimeout(() => copiado = false, 2000); })">
                <span x-show="!copiado"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1.5-1.5"/></svg></span>
                <span x-show="copiado" x-cloak class="text-xs font-semibold" aria-hidden="true">✓</span>
            </button>
        </div>
    </div>
</div>
