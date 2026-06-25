<x-layout.app>
    <section class="bg-gradient-to-br from-primary to-footer-bg text-white">
        <div class="mx-auto max-w-[1240px] px-6 py-24 text-center">
            <p class="font-mono text-xs uppercase tracking-[0.14em] text-gold">Fé · Estudo · Caridade</p>
            <h1 class="mt-3 font-display text-4xl font-semibold md:text-5xl">Centro Espírita Maria Madalena</h1>
            <p class="mx-auto mt-4 max-w-xl text-lg text-white/85">
                Em construção. Enquanto isso, conheça as palestras públicas da casa.
            </p>
            <a href="{{ route('palestras.index') }}"
               class="mt-8 inline-flex items-center gap-2 rounded-pill bg-gold px-6 py-3 font-ui font-semibold text-footer-bg transition hover:brightness-105">
                Ver palestras →
            </a>
        </div>
    </section>
</x-layout.app>
