@php
    $ano = $resumo->anoAtivoDesde();
    $pct = $resumo->percentualOnline();
    $tiles = [
        ['valor' => $resumo->totalPalestras(), 'rotulo' => 'Palestras', 'bg' => 'bg-cream'],
        ['valor' => $resumo->totalTemas(), 'rotulo' => 'Temas abordados', 'bg' => 'bg-[#EAF0F6]'],
        ['valor' => $ano ?? '—', 'rotulo' => 'Ativo no CEMA desde', 'bg' => 'bg-[#EAF2EC]'],
        ['valor' => $pct !== null ? $pct.'%' : '—', 'rotulo' => 'Palestras online', 'bg' => 'bg-surface'],
    ];
@endphp
<div class="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(130px,1fr))]">
    @foreach ($tiles as $tile)
        <div class="{{ $tile['bg'] }} rounded-[14px] border border-border-muted px-4 py-[18px] text-center">
            <p class="font-display text-[26px] font-bold leading-none text-primary">{{ $tile['valor'] }}</p>
            <p class="mt-[7px] text-[11.5px] text-[#6a6685]">{{ $tile['rotulo'] }}</p>
        </div>
    @endforeach
</div>
