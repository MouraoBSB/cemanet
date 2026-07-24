{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21 --}}
<x-layout.conta titulo="Minhas Mensagens" ativo="mensagens">
    {{-- Os TRÊS slots: tema Filament escopado ANTES do app.css (o site vence a cascata) + noindex
         (área pessoal) + JS dos componentes DEPOIS do Livewire. Sem os três, o form fica sem CSS,
         sem JS ou indexável — nenhuma outra página do site combina os três (molde inédito). --}}
    <x-slot:headTop><x-conta.filament-head /></x-slot:headTop>
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>
    <x-slot:scripts>@filamentScripts</x-slot:scripts>

    <livewire:conta.mensagens-conta />
</x-layout.conta>
