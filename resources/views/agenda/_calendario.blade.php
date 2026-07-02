{{-- Calendário mensal SSR: célula com conteúdo = <a> crawlável; sem conteúdo = <span> inerte. --}}
@php($tituloMes = \Illuminate\Support\Str::ucfirst($dataAtual->translatedFormat('F \d\e Y')))
<section class="agenda-cal rounded-2xl border border-border-muted bg-white p-4 shadow-card" aria-label="Calendário do mês">
    <div class="mb-3 flex items-center justify-between gap-2 rounded-md bg-cream p-2">
        @if ($mesAnterior)
            <a href="{{ route('agenda.show', $mesAnterior) }}" wire:navigate aria-label="Mês anterior"
               class="agenda-cal-seta grid size-[34px] place-items-center rounded-[8px] text-primary transition hover:bg-primary/10">‹</a>
        @else
            <span class="grid size-[34px] place-items-center rounded-[8px] text-primary/40" aria-hidden="true">‹</span>
        @endif

        <h2 class="font-display font-semibold capitalize text-primary">{{ $tituloMes }}</h2>

        @if ($mesProximo)
            <a href="{{ route('agenda.show', $mesProximo) }}" wire:navigate aria-label="Próximo mês"
               class="agenda-cal-seta grid size-[34px] place-items-center rounded-[8px] text-primary transition hover:bg-primary/10">›</a>
        @else
            <span class="grid size-[34px] place-items-center rounded-[8px] text-primary/40" aria-hidden="true">›</span>
        @endif
    </div>

    <div class="grid grid-cols-7 gap-1.5 text-center">
        @foreach (['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'] as $inicial)
            <span class="font-display text-[11px] font-semibold uppercase tracking-wide text-[#9a93b4]" aria-hidden="true">{{ $inicial }}</span>
        @endforeach
    </div>

    <div class="mt-1.5 grid grid-cols-7 gap-1.5">
        @for ($v = 0; $v < $matriz['diasVazios']; $v++)
            <span aria-hidden="true"></span>
        @endfor

        @foreach ($matriz['dias'] as $celula)
            @if ($celula['temConteudo'])
                <a href="{{ route('agenda.show', $celula['ymd']) }}" wire:navigate
                   @class([
                       'agenda-dia',
                       'agenda-dia--sel' => $celula['selecionado'],
                       'agenda-dia--hoje' => $celula['hoje'],
                   ])
                   @if ($celula['hoje']) aria-current="date" @endif
                   aria-label="{{ $celula['dia'] }} — ver reflexão">
                    <span>{{ $celula['dia'] }}</span>
                    <svg width="9" height="12" viewBox="0 0 24 24" class="agenda-bookmark" aria-hidden="true"><path d="M5 3h14v18l-7-5-7 5z" fill="currentColor"></path></svg>
                </a>
            @else
                <span @class([
                          'agenda-dia agenda-dia--vazio',
                          'agenda-dia--hoje' => $celula['hoje'],
                      ])
                      @if ($celula['hoje']) aria-current="date" @endif>{{ $celula['dia'] }}</span>
            @endif
        @endforeach
    </div>

    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-border-muted pt-3 text-[11.5px] text-text-muted">
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-[3px] bg-primary"></span> Dia selecionado</span>
        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-3 rounded-[3px]" style="border:2px solid var(--color-primary);"></span> Hoje</span>
        <span class="inline-flex items-center gap-1.5"><svg width="9" height="12" viewBox="0 0 24 24" style="color:#c6bee6;" aria-hidden="true"><path d="M5 3h14v18l-7-5-7 5z" fill="currentColor"></path></svg> Conteúdo disponível</span>
    </div>
</section>
