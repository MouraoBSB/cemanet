{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15 --}}
<x-layout.conta titulo="Agenda da Reforma Íntima" ativo="agenda">
    {{-- Tema Filament escopado ANTES do app.css (o site vence a cascata); JS dos componentes
         DEPOIS do Livewire. Só nesta página — não vaza para o resto do /minha-conta. --}}
    <x-slot:headTop>@vite('resources/css/filament/site/theme.css')</x-slot:headTop>
    <x-slot:scripts>@filamentScripts</x-slot:scripts>

    <livewire:conta.agenda-conta />
</x-layout.conta>
