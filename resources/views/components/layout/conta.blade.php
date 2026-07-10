{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@props(['titulo' => null, 'ativo' => 'painel'])
<x-layout.app :title="$titulo">
    {{-- Repassa os slots do layout do site: sem isto, uma página da conta que precise
         embutir um Filament Form perderia o tema e os scripts em silêncio. --}}
    <x-slot:headTop>{{ $headTop ?? '' }}</x-slot:headTop>
    <x-slot:scripts>{{ $scripts ?? '' }}</x-slot:scripts>

    <x-conta.saudacao />
    @if (session('status'))
        <div class="mx-auto max-w-[1240px] px-6 pt-6">
            <p class="rounded-md bg-accent/15 px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>
        </div>
    @endif
    <div class="mx-auto grid max-w-[1240px] gap-6 px-6 py-8 desktop-sm:grid-cols-[220px_1fr]">
        <aside class="desktop-sm:sticky desktop-sm:top-24 desktop-sm:self-start">
            <x-conta.nav :ativo="$ativo" />
        </aside>
        <div>{{ $slot }}</div>
    </div>
</x-layout.app>
