@if ($palestrante->bio)
    <div class="mt-6 rounded-2xl border border-border-muted bg-white p-8 shadow-card">
        <div class="mb-[18px] flex items-center gap-2.5">
            <span class="h-[3px] w-[22px] rounded-sm bg-gold" aria-hidden="true"></span>
            <h2 class="font-display text-[19px] font-semibold text-primary">Sobre {{ $palestrante->nome }}</h2>
        </div>
        <div class="cema-prosa-perfil">{!! $palestrante->bio !!}</div>
    </div>
@endif
