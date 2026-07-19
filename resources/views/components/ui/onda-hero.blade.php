@props(['altura' => 52])

{{-- Onda SVG branca (cor da superfície) na base de um hero roxo, fazendo a transição para o
     corpo da página. Fica em fluxo normal (não absoluta) — o hero reserva o espaço acima dela. --}}
<svg viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true"
     class="relative z-[2] block w-full" style="height:{{ $altura }}px">
    <path d="M0,42 C240,72 480,4 720,26 C960,48 1200,14 1440,40 L1440,70 L0,70 Z" fill="var(--color-surface)"></path>
</svg>
