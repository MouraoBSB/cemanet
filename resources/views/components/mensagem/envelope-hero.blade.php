{{-- Envelope dourado animado do hero das Mensagens (decoração pura). O CSS das animações
     vive em resources/css/mensagens.css: some abaixo de 1280px e respeita prefers-reduced-motion. --}}
<div class="cema-env-wrap" aria-hidden="true">
    <svg viewBox="0 0 200 200" width="100%" height="100%" fill="none">
        <defs>
            <filter id="cemaEnvBlur" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="13"/></filter>
        </defs>
        <circle class="cema-env-glow" cx="100" cy="82" r="52" fill="rgba(242,168,30,0.30)" filter="url(#cemaEnvBlur)"/>
        <g stroke="#d7def0" stroke-width="2" stroke-linecap="round">
            <line class="cema-env-ray" x1="100" y1="62" x2="100" y2="18" style="animation-delay:3.4s"/>
            <line class="cema-env-ray" x1="72" y1="68" x2="48" y2="34" style="animation-delay:3.8s"/>
            <line class="cema-env-ray" x1="128" y1="68" x2="152" y2="34" style="animation-delay:4.2s"/>
            <line class="cema-env-ray" x1="56" y1="82" x2="26" y2="64" style="animation-delay:4.6s"/>
            <line class="cema-env-ray" x1="144" y1="82" x2="174" y2="64" style="animation-delay:5s"/>
        </g>
        <g class="cema-env-float">
            <g stroke="#F2A81E" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect class="cema-env-draw" x="40" y="88" width="120" height="74" rx="9"/>
                <path class="cema-env-draw" d="M42 156 L84 122 M158 156 L116 122" style="animation-delay:.4s"/>
                <path class="cema-env-flap" d="M42 92 L100 134 L158 92" fill="rgba(242,168,30,0.08)"/>
            </g>
        </g>
    </svg>
</div>
