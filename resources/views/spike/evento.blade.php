{{-- SPIKE (descartável): página do site hospedando o Filament Form. --}}
<x-layout.app title="Spike — Formulário de Evento no site">
    {{-- Assets do Filament ESCOPADOS a esta página (nada vaza p/ /eventos, /calendario, ...). --}}
    <x-slot:headTop>
        @filamentStyles
        @vite('resources/css/filament/admin/theme.css')
    </x-slot:headTop>

    <x-slot:scripts>
        @filamentScripts
    </x-slot:scripts>

    <section class="mx-auto max-w-[1100px] px-6 py-12">
        <h1 class="mb-2 font-display text-3xl font-semibold text-primary">Spike: Filament Form dentro do site</h1>
        <p class="mb-8 text-text-secondary">Mesmo schema do <code>EventoResource</code>, renderizado no layout público.</p>

        @livewire('spike.formulario-evento')
    </section>
</x-layout.app>
