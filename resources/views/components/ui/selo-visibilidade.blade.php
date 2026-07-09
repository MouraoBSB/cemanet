{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-09 --}}
{{-- Selo discreto de visibilidade: ponto na cor do enum + rótulo em texto neutro ESCURO. --}}
{{-- text-text-ink (#26242e) sobre bg-surface (#f6f6f6) ≈ 14:1 (WCAG AA ok em 11px). --}}
{{-- NÃO usar text-text-muted (#7a8a8a): dá ~3,3:1 e reprova. O ponto colorido é decorativo. --}}
@props(['rotulo', 'cor'])
<span class="inline-flex items-center gap-1.5 rounded-pill bg-surface px-2.5 py-0.5 text-[11px] font-semibold text-text-ink">
    <span class="inline-block size-2 rounded-full" style="background: {{ $cor }}" aria-hidden="true"></span>
    {{ $rotulo }}
</span>
