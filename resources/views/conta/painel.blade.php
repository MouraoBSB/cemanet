{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
@php
    $atalhos = [
        ['rotulo' => 'Calendário de palestras', 'rota' => 'calendario.index'],
        ['rotulo' => 'Palestras', 'rota' => 'palestras.index'],
        ['rotulo' => 'Sementeira de Luz', 'rota' => 'blog.index'],
        ['rotulo' => 'Agenda Reforma Íntima', 'rota' => 'agenda.index'],
    ];
@endphp
<x-layout.conta titulo="Painel" ativo="painel">
    <div class="space-y-6">
        <div class="rounded-lg bg-white p-6 shadow-card">
            <h2 class="font-display text-lg font-semibold text-primary">Bem-vindo(a) de volta!</h2>
            <p class="mt-1 text-sm text-text-secondary">Este é o seu espaço no CEMA.</p>
        </div>

        <section class="rounded-lg bg-white p-6 shadow-card">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-lg font-semibold text-primary">Próximas palestras</h2>
                <a href="{{ route('calendario.index') }}" class="text-sm font-medium text-secondary hover:text-primary">Ver todas →</a>
            </div>

            @forelse ($proximas as $palestra)
                <x-palestra.linha :palestra="$palestra" />
            @empty
                <p class="rounded-md bg-surface px-4 py-6 text-center text-sm text-text-muted">Nenhuma palestra agendada no momento.</p>
            @endforelse
        </section>

        <section>
            <h2 class="mb-3 font-display text-lg font-semibold text-primary">Atalhos rápidos</h2>
            <div class="grid grid-cols-2 gap-3 tablet:grid-cols-4">
                @foreach ($atalhos as $atalho)
                    <a href="{{ route($atalho['rota']) }}"
                       class="rounded-lg bg-white p-4 text-center text-sm font-medium text-text shadow-card transition hover:shadow-elevated hover:text-primary">
                        {{ $atalho['rotulo'] }}
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-layout.conta>
