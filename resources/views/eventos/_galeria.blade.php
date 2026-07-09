{{-- Grade de miniaturas da galeria do evento (WebP conversão "web"). --}}
@php($galeriaMedia = $evento->getMedia('galeria'))
<section class="bg-surface">
    <div class="mx-auto max-w-[1100px] px-6 py-10">
        <h2 class="mb-5 font-display text-2xl font-semibold text-primary">Galeria</h2>
        <div class="grid grid-cols-2 gap-3 tablet:grid-cols-3">
            @foreach ($galeriaMedia as $img)
                <div class="overflow-hidden rounded-xl">
                    <img src="{{ $img->getUrl('web') }}"
                         alt="{{ $img->getCustomProperty('alt') ?? $evento->titulo }}"
                         loading="lazy" width="300" height="200"
                         class="aspect-[3/2] w-full object-cover">
                </div>
            @endforeach
        </div>
    </div>
</section>
