@php $urlPerfil = route('palestrantes.show', $palestrante->slug); @endphp
<x-layout.app :title="$palestrante->nome"
              :description="\Illuminate\Support\Str::limit(strip_tags($palestrante->chamada ?? $palestrante->bio ?? ''), 150) ?: 'Palestrante do CEMA'">
    <x-slot:head>
        <script type="application/ld+json">
        @php
            echo json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $palestrante->nome,
                'image' => $palestrante->foto_url, // omitido quando null
                'description' => \Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 200),
                'url' => $urlPerfil,
                'worksFor' => ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'],
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        </script>
        <link rel="canonical" href="{{ $urlPerfil }}">
        @if ($palestrante->foto_url)
            <meta property="og:image" content="{{ $palestrante->foto_url }}">
        @endif
    </x-slot:head>

    <div x-data="palestranteDetalhe({ itens: @js($itensFiltro), areas: @js($areas) })">
        @include('palestrantes.perfil.hero')

        <section class="bg-surface">
            <div class="mx-auto flex max-w-[1160px] flex-col gap-8 px-6 py-10 desktop-sm:flex-row desktop-sm:items-start">
                <div class="min-w-0 flex-1">
                    @include('palestrantes.perfil.estatisticas')
                    @include('palestrantes.perfil.sobre')
                    @include('palestrantes.perfil.palestras')
                </div>
                <aside class="w-full shrink-0 desktop-sm:sticky desktop-sm:top-24 desktop-sm:w-[340px]">
                    @include('palestrantes.perfil.sidebar')
                </aside>
            </div>
        </section>
    </div>
</x-layout.app>
