<x-layout.app title="Palestras Públicas" description="Palestras públicas do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Palestras Públicas</p>
            <h1 class="mt-2 font-display text-4xl font-semibold">Palestras do CEMA</h1>
            <p class="mt-3 max-w-xl text-white/85">Reflexões à luz do Espiritismo, abertas a todos.</p>
        </div>
    </section>

    <section class="mx-auto max-w-[1240px] px-6 py-12">
        {{-- #[Url] lê q/assunto da query string no load inicial; a busca do header (GET ?q=) cai aqui. --}}
        <livewire:palestras.lista />
    </section>
</x-layout.app>
