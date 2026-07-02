{{-- Calendário mensal SSR: célula com conteúdo = <a> crawlável; sem conteúdo = <span> inerte. --}}
@php($tituloMes = \Illuminate\Support\Str::ucfirst($dataAtual->translatedFormat('F \d\e Y')))
<section class="agenda-cal rounded-2xl border border-border-muted bg-white p-4 shadow-card" aria-label="Calendário do mês">
    <div class="mb-3 flex items-center justify-between gap-2">
        @if ($mesAnterior)
            <a href="{{ route('agenda.show', $mesAnterior) }}" wire:navigate aria-label="Mês anterior"
               class="agenda-cal-seta grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary">‹</a>
        @else
            <span class="grid size-9 place-items-center rounded-full border border-border-muted text-text-muted opacity-40" aria-hidden="true">‹</span>
        @endif

        <h2 class="font-display font-semibold text-text-ink">{{ $tituloMes }}</h2>

        @if ($mesProximo)
            <a href="{{ route('agenda.show', $mesProximo) }}" wire:navigate aria-label="Próximo mês"
               class="agenda-cal-seta grid size-9 place-items-center rounded-full border border-border-muted text-text-secondary transition hover:border-primary hover:text-primary">›</a>
        @else
            <span class="grid size-9 place-items-center rounded-full border border-border-muted text-text-muted opacity-40" aria-hidden="true">›</span>
        @endif
    </div>

    <div class="grid grid-cols-7 gap-1 text-center">
        @foreach (['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $i => $inicial)
            <span class="py-1 font-mono text-[11px] font-semibold text-text-muted" aria-hidden="true">{{ $inicial }}</span>
        @endforeach

        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
            <span aria-hidden="true"></span>
        @endfor

        @foreach ($matriz['dias'] as $celula)
            @if ($celula['temConteudo'])
                <a href="{{ route('agenda.show', $celula['ymd']) }}" wire:navigate
                   @class([
                       'agenda-dia agenda-dia--conteudo',
                       'agenda-dia--sel' => $celula['selecionado'],
                       'agenda-dia--hoje' => $celula['hoje'],
                   ])
                   @if ($celula['hoje']) aria-current="date" @endif
                   aria-label="{{ $celula['dia'] }} — ver reflexão">{{ $celula['dia'] }}</a>
            @else
                <span @class([
                          'agenda-dia agenda-dia--vazio',
                          'agenda-dia--hoje' => $celula['hoje'],
                      ])
                      @if ($celula['hoje']) aria-current="date" @endif>{{ $celula['dia'] }}</span>
            @endif
        @endforeach
    </div>

    <div class="mt-3 flex items-center gap-4 text-[11px] text-text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full bg-gold"></span> Com reflexão</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-full ring-2 ring-primary"></span> Hoje</span>
    </div>
</section>
