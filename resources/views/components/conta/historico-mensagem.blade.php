{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21 --}}
{{-- Histórico de auditoria do item (Fatia F4b, Task 11): só CAMPOS alterados, NUNCA valores.
     `properties` carrega valor (ex.: o do título) — proibido @json/print_r/dd/{!! !!} ou qualquer
     foreach que imprima o VALOR bruto de attributes/old/diff. `quem` já chega resolvido a um nome
     (R4: nunca o objeto User). Entrada manual (diff) mostra só a descrição — nunca os nomes de
     dentro do diff, que podem ser PII (I24). --}}
@props(['mensagem', 'limite' => 20])
@php
    $linhas = \App\Support\Mensagens\HistoricoMensagem::linhas($mensagem, $limite);
    $haMais = \App\Support\Mensagens\HistoricoMensagem::haMaisQue($mensagem, $limite);
@endphp
<div class="space-y-3">
    <h3 class="font-display text-sm font-semibold text-primary">Histórico</h3>
    @forelse ($linhas as $linha)
        <div class="rounded-md border border-border-muted bg-surface px-3 py-2 text-sm">
            <p class="text-text">
                <span class="font-medium">{{ $linha['quem'] }}</span>
                — {{ $linha['descricao'] }}
                <span class="text-text-muted">· {{ $linha['quando']?->format('d/m/Y H:i') }}</span>
            </p>
            @if ($linha['campos'] !== [])
                <p class="mt-1 text-xs text-text-muted">Campos alterados: {{ implode(', ', $linha['campos']) }}</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-text-muted">Sem histórico registrado.</p>
    @endforelse
    @if ($haMais)
        <p class="text-xs text-text-muted">Mostrando as {{ $limite }} mais recentes.</p>
    @endif
</div>
