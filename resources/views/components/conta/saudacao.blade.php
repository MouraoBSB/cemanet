{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $user = auth()->user();
    $perfil = $user->perfil;
    $papel = $user->roles->first()?->name;
@endphp
<section class="relative overflow-hidden bg-gradient-to-br from-primary via-[#3c3468] to-footer-bg text-white">
    <x-ui.particulas />
    <div class="relative mx-auto flex max-w-[1240px] items-center gap-4 px-6 py-8">
        @if ($perfil?->foto_thumb_url)
            <img src="{{ $perfil->foto_thumb_url }}" alt="" class="size-16 rounded-full object-cover ring-2 ring-gold">
        @else
            <span class="flex size-16 items-center justify-center rounded-full bg-gold/20 text-xl font-semibold text-gold ring-2 ring-gold">{{ $user->iniciais }}</span>
        @endif
        <div>
            <p class="font-mono text-xs uppercase tracking-[0.12em] text-white/70">Olá,</p>
            <h1 class="font-display text-2xl font-bold leading-tight">{{ $user->name }}</h1>
            <div class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                @if ($papel)
                    <span class="rounded-pill bg-white/15 px-3 py-0.5 capitalize">{{ $papel }}</span>
                @endif
                @if ($user->socio)
                    <span class="rounded-pill bg-gold/90 px-3 py-0.5 font-medium text-primary">Sócio</span>
                @endif
            </div>
        </div>
    </div>
</section>
