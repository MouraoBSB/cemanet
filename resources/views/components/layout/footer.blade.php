<footer class="bg-footer-bg text-[#cfc9e4]">
    <div class="mx-auto grid max-w-[1240px] gap-10 px-6 py-14 sm:grid-cols-2 desktop-sm:grid-cols-[1.4fr_1fr_1fr_1.3fr]">
        {{-- Marca --}}
        <div>
            <img src="{{ asset('images/logos/logo-branco.png') }}" alt="CEMA — Centro Espírita Maria Madalena" class="mb-4 h-[74px] w-auto" width="160" height="74">
            <p class="text-sm leading-relaxed text-[#bdb4dd]">
                Centro Espírita Maria Madalena — uma casa de fé, estudo e caridade em Planaltina, DF.
            </p>
        </div>

        {{-- Institucional --}}
        <nav aria-label="Institucional">
            <h2 class="mb-3 font-display text-base font-semibold text-white">Institucional</h2>
            <ul class="space-y-2 text-sm">
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Nossa História</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Nosso Blog</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Contato</span></li>
            </ul>
        </nav>

        {{-- Atividades --}}
        <nav aria-label="Atividades">
            <h2 class="mb-3 font-display text-base font-semibold text-white">Atividades</h2>
            <ul class="space-y-2 text-sm">
                <li><a href="{{ route('palestras.index') }}" class="text-[#cfc9e4] hover:text-white hover:underline">Palestras Públicas</a></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Palestrantes</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Evangelho da Semana</span></li>
                <li><span class="text-[#bdb4dd]" aria-disabled="true">Agenda Reforma Íntima</span></li>
            </ul>
        </nav>

        {{-- Newsletter (apenas visual — backend de e-mail em fase futura) --}}
        <div>
            <h2 class="mb-3 font-display text-base font-semibold text-white">Inscreva-se</h2>
            <p class="mb-3 text-sm text-[#bdb4dd]">Receba novidades da casa. (Em breve.)</p>
            <form class="space-y-2" aria-label="Inscrição na newsletter (em breve)" onsubmit="return false">
                <label for="nl-nome" class="sr-only">Nome</label>
                <input id="nl-nome" type="text" placeholder="Nome" disabled
                       class="w-full rounded-md border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/50">
                <label for="nl-email" class="sr-only">E-mail</label>
                <input id="nl-email" type="email" placeholder="E-mail" disabled
                       class="w-full rounded-md border border-white/15 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/50">
                <button type="submit" disabled aria-disabled="true"
                        class="w-full cursor-not-allowed rounded-md bg-gold px-4 py-2 font-ui font-semibold text-footer-bg opacity-80">Inscrever</button>
            </form>
            <ul class="mt-4 flex gap-3" aria-label="Redes sociais">
                @foreach (['YouTube', 'Instagram', 'Facebook', 'WhatsApp'] as $rede)
                    <li><span class="text-xs text-[#bdb4dd]" aria-disabled="true">{{ $rede }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Barra legal --}}
    <div class="border-t border-white/10">
        <div class="mx-auto flex max-w-[1240px] flex-col gap-2 px-6 py-5 text-xs text-[#a89fce] sm:flex-row sm:items-center sm:justify-between">
            <address class="not-italic">
                Quadra 02, Lote 16, Vila Vicentina — Planaltina, DF · CNPJ 01.600.089/0001-90
            </address>
            <p>© 2026 CEMA · Todos os direitos reservados · Desenvolvido por DECOM</p>
        </div>
    </div>
</footer>
