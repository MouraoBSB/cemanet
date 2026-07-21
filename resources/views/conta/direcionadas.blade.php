{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
<x-layout.conta titulo="Minhas Mensagens Direcionadas" ativo="direcionadas">
    <x-slot:head><meta name="robots" content="noindex, nofollow"></x-slot:head>

    <div class="space-y-6">
        {{-- Cabeçalho "Área pessoal" — card creme, SEM lista de destinatários (F2). --}}
        <section class="flex items-start gap-3.5 rounded-lg border border-[#ECE6D6] bg-[#FAF8F2] p-6 shadow-card">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c19532" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <div>
                <h2 class="font-display text-lg font-semibold text-primary">Minhas mensagens direcionadas</h2>
                <p class="mt-1 text-sm text-text-secondary">Mensagens endereçadas pessoalmente a você nas reuniões mediúnicas da Casa. Somente você as vê por aqui.</p>
            </div>
        </section>

        {{-- Grade das direcionadas (só as minhas, publicadas — controller). Card 'perfil' (badge Direcionada @auth). --}}
        @if ($direcionadas->isNotEmpty())
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @foreach ($direcionadas as $mensagem)
                    <x-mensagem.card :mensagem="$mensagem" variante="perfil" />
                @endforeach
            </div>
        @else
            {{-- Estado vazio: improvável (a aba só aparece com ≥1), mas defende a corrida despublicar↔clicar. --}}
            <p class="rounded-lg border border-dashed border-border bg-surface px-4 py-10 text-center text-sm text-text-muted">
                Nenhuma mensagem direcionada a você no momento.
            </p>
        @endif
    </div>
</x-layout.conta>
