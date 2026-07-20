{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20 --}}
{{-- Barreira CEGA do single restrito: NUNCA renderiza título/corpo/OG/destinatários da mensagem-alvo.
     $modo = 'login' (anônimo → modal) | 'sem-permissao' (logado sem acesso → contato). --}}
@php
    $emailContato = \App\Models\Configuracao::valor('contato.email');
    $whatsappContato = \App\Models\Configuracao::valor('contato.whatsapp');
    $whatsappDigitos = $whatsappContato ? preg_replace('/\D/', '', $whatsappContato) : null;
@endphp
<x-layout.app title="Conteúdo restrito"
              description="Esta mensagem é reservada. Entre para vê-la, se estiver disponível para você.">
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow">
    </x-slot:head>

    <section class="relative overflow-hidden text-white"
             style="background:radial-gradient(circle at 78% 22%, rgba(110,159,203,0.40), transparent 54%), linear-gradient(135deg,#0b1030 0%,#1a1f4a 48%,#2c2f64 100%);">
        <x-ui.particulas />
        <div class="relative z-[2] mx-auto max-w-[720px] px-6 py-20 text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-white/12" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#f2a81e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <h1 class="mt-6 font-display text-3xl font-semibold sm:text-4xl">Conteúdo restrito</h1>

            @if ($modo === 'login')
                <p class="mx-auto mt-4 max-w-md font-light text-[#d7def0]">Esta mensagem é reservada aos membros da Casa. Entre para vê-la — se estiver disponível para você.</p>
                <button type="button" x-data @click="$dispatch('abrir-login')"
                        class="mt-7 inline-flex items-center gap-2 rounded-pill bg-gold px-6 py-3 font-medium text-[#3a3266] transition hover:bg-[#e59e12]">
                    Entrar para ver
                </button>
            @else
                <p class="mx-auto mt-4 max-w-md font-light text-[#d7def0]">Você não tem permissão para ver esta mensagem.</p>
                @if ($emailContato || $whatsappDigitos)
                    <p class="mt-4 text-[13.5px] text-[#c7d0ea]">Em caso de dúvida, entre em contato:</p>
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                        @if ($emailContato)
                            <a href="mailto:{{ $emailContato }}" class="rounded-pill border border-white/22 bg-white/10 px-5 py-2.5 text-sm transition hover:bg-white/18">{{ $emailContato }}</a>
                        @endif
                        @if ($whatsappDigitos)
                            <a href="https://wa.me/{{ $whatsappDigitos }}" target="_blank" rel="noopener noreferrer" class="rounded-pill border border-white/22 bg-white/10 px-5 py-2.5 text-sm transition hover:bg-white/18">WhatsApp: {{ $whatsappContato }}</a>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </section>

    @if ($modo === 'login')
        {{-- Modal de login inline: abre no load (x-init) e reabre em qualquer recarga (inclui pós-erro do Fortify — R1). --}}
        <dialog x-data x-init="$el.showModal()" @abrir-login.window="$el.showModal()"
                class="m-auto w-[min(92vw,420px)] rounded-2xl bg-white p-0 text-text-ink shadow-elevated backdrop:bg-black/50">
            <div class="p-6 sm:p-7">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-xl font-semibold text-primary">Entrar</h2>
                    <button type="button" @click="$el.closest('dialog').close()" aria-label="Fechar"
                            class="grid size-8 place-items-center rounded-full text-text-muted transition hover:bg-surface">×</button>
                </div>
                <x-auth.form-login />
            </div>
        </dialog>
    @endif
</x-layout.app>
