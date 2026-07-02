{{-- Stub da Agenda Reforma Íntima — a casca rica vem na Task 11. --}}
<x-layout.app
    title="Agenda Reforma Íntima — {{ $dataAtual->format('d/m/Y') }}"
    :description="$dia?->descricaoSeo()">
    <x-slot:head>
        @unless ($temConteudo)
            <meta name="robots" content="noindex">
        @endunless
    </x-slot:head>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        @if ($temConteudo)
            <article>{!! $dia->reflexao !!}</article>
        @else
            <p>Não há reflexão publicada para esta data.</p>
            <a href="{{ route('agenda.index') }}" wire:navigate>Voltar para hoje</a>
        @endif
    </section>
</x-layout.app>
