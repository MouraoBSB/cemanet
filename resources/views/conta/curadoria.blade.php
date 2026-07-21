{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21 --}}
<x-layout.conta titulo="Curadoria" ativo="curadoria">
    {{-- Os TRÊS slots: tema Filament escopado ANTES do app.css (o site vence a cascata) + noindex
         (área pessoal) + JS dos componentes DEPOIS do Livewire (molde: conta/mensagens.blade.php). --}}
    <x-slot:headTop>@vite('resources/css/filament/site/theme.css')</x-slot:headTop>
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>
    <x-slot:scripts>@filamentScripts</x-slot:scripts>

    <livewire:conta.curadoria-conta />
</x-layout.conta>
