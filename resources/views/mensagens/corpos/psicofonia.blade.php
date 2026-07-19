{{-- Corpo psicofônico (O1): renderização IDÊNTICA à psicografia (mesmo campo único
     $mensagem->corpo, prosa Roboto Slab) + uma NOTA de transcrição. Sem infra de balões
     pergunta/resposta: 0/40 psicofonias do legado têm estrutura tipada (SPEC §4.2/§13-O1).
     Eventuais h3/blockquote no corpo já são estilizados por .cema-msg-prose. --}}
<div class="mb-6 flex items-center gap-2 text-[13px] text-text-muted">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 2a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3z"/>
        <path d="M19 10v1a7 7 0 0 1-14 0v-1"/>
        <line x1="12" y1="18" x2="12" y2="22"/>
        <line x1="8" y1="22" x2="16" y2="22"/>
    </svg>
    <span>Transcrição por psicofonia</span>
</div>

@include('mensagens.corpos.psicografia')
