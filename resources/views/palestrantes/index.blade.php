<x-layout.app title="Palestrantes" description="Palestrantes do Centro Espírita Maria Madalena (CEMA).">
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-16">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Palestrantes</p>
            <h1 class="mt-2 font-display text-4xl font-semibold">Quem leva a palavra</h1>
            <p class="mt-3 max-w-xl text-white/85">Os trabalhadores que conduzem as palestras públicas da casa.</p>
        </div>
    </section>
    <section class="mx-auto max-w-[1240px] px-6 py-12">
        <livewire:palestrantes.lista />
    </section>
</x-layout.app>
