{{-- Corpo psicografado (O1): prosa Roboto Slab do campo ÚNICO $mensagem->corpo — já saneado
     por clean('conteudo') no model, por isso o {!! !!} é seguro. Assinatura ao final:
     autor(es) + casa (constante "CEMA") + data. "Sem assinatura" quando não há autor. --}}
@php $nomesAutores = $mensagem->autores->pluck('nome'); @endphp

<div class="cema-msg-prose">{!! $mensagem->corpo !!}</div>

<div class="mt-9 flex flex-col items-end gap-1 border-t border-[#F0EEF4] pt-6 text-right">
    <p class="font-serif text-[19px] italic leading-snug text-primary">
        @if ($nomesAutores->isNotEmpty())
            {{ $nomesAutores->join(', ', ' e ') }}
        @else
            <span class="text-text-muted">Sem assinatura</span>
        @endif
    </p>
    <p class="font-mono text-[11.5px] uppercase tracking-[0.08em] text-text-muted">
        {{ $mensagem->casa }}@if ($mensagem->data_recebimento) · {{ $mensagem->data_recebimento->translatedFormat('j \d\e F \d\e Y') }}@endif
    </p>
</div>
