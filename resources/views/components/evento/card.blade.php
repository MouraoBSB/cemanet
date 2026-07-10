@props(['evento', 'compacto' => false])

@php
    $flyer = $evento->flyerUrl ?? asset('images/logos/logo-icone.png');
    $s = $evento->status_selo;
@endphp
<a href="{{ route('eventos.show', $evento->slug) }}" {{ $attributes->except(['evento', 'compacto']) }}>
    <article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-[#EBE8E8] bg-white shadow-card transition duration-200 hover:-translate-y-1 hover:shadow-elevated">
        {{-- Flyer --}}
        <div class="relative w-full overflow-hidden {{ $compacto ? 'h-[170px]' : 'h-[188px]' }}">
            <img src="{{ $flyer }}" alt="" loading="lazy"
                 @class(['size-full object-cover transition duration-300 group-hover:scale-[1.03]', 'grayscale-[.55] opacity-90' => $evento->ehPassado])>

            @if ($evento->categoria)
                <span class="absolute left-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[10.5px] font-medium uppercase tracking-wide"
                      style="background: {{ $evento->categoria->cor }}; color: {{ $evento->categoria->cor_texto ?? '#fff' }};">
                    {{ $evento->categoria->nome }}
                </span>
            @endif

            <span class="absolute right-3 top-3 rounded-pill px-2.5 py-1 text-[11px] font-semibold"
                  style="background: {{ $s['cor'] }}; color: {{ $s['cor_texto'] }};">
                {{ $s['rotulo'] }}
            </span>
        </div>

        {{-- Corpo --}}
        <div class="flex flex-1 flex-col gap-2.5 p-4">
            <h3 class="font-display text-[16.5px] font-semibold leading-tight text-text-ink">{{ $evento->titulo }}</h3>

            @auth
                @if ($evento->visibilidade !== \App\Enums\VisibilidadeEvento::Publico)
                    <x-ui.selo-visibilidade :rotulo="$evento->visibilidade->rotulo()" :cor="$evento->visibilidade->cor()" />
                @endif
            @endauth

            <div class="mt-auto flex flex-col gap-1.5 text-[12.5px] text-text-muted">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#89AB98" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ $evento->periodo }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="#89AB98" stroke-width="1.75" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                    </svg>
                    {{ $evento->local ?: 'Local a confirmar' }}
                </span>
            </div>
        </div>
    </article>
</a>
