@php
    $foto = $palestrante->foto ? asset('storage/'.$palestrante->foto) : null;
@endphp

<x-layout.app :title="$palestrante->nome" :description="\Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 150) ?: 'Palestrante do CEMA'">
    <x-slot:head>
        <script type="application/ld+json">
        @php
            echo json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Person',
                'name' => $palestrante->nome,
                // omite 'image' quando não há foto (null vira chave inválida no schema)
                'image' => $foto,
                'description' => \Illuminate\Support\Str::limit(strip_tags($palestrante->bio ?? ''), 200),
                'url' => route('palestrantes.show', $palestrante->slug),
                'worksFor' => ['@type' => 'Organization', 'name' => 'Centro Espírita Maria Madalena'],
            ], fn ($v) => $v !== null && $v !== ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        @endphp
        </script>
    </x-slot:head>

    {{-- Hero / perfil --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-primary to-footer-bg text-white">
        <x-ui.particulas />
        <div class="relative mx-auto max-w-[1100px] px-6 py-16">
            <nav aria-label="Você está em" class="mb-5 flex flex-wrap items-center gap-2 text-xs text-white/70">
                <a href="{{ route('home') }}" class="hover:text-white">Início</a><span aria-hidden="true">›</span>
                <a href="{{ route('palestrantes.index') }}" class="hover:text-white">Palestrantes</a><span aria-hidden="true">›</span>
                <span class="text-gold" aria-current="page">{{ $palestrante->nome }}</span>
            </nav>
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                @if ($foto)
                    <img src="{{ $foto }}" alt="{{ $palestrante->nome }}" width="160" height="160"
                         class="size-40 shrink-0 rounded-2xl object-cover">
                @endif
                <div>
                    <p class="font-mono text-xs uppercase tracking-[0.14em] text-white/60">Palestrante</p>
                    <h1 class="mt-2 font-display text-3xl font-semibold md:text-4xl">{{ $palestrante->nome }}</h1>
                    @if ($palestrante->mostrar_email && $palestrante->email)
                        <p class="mt-3 text-sm text-white/85"><a href="mailto:{{ $palestrante->email }}" class="underline hover:text-gold">{{ $palestrante->email }}</a></p>
                    @endif
                    @if ($palestrante->mostrar_telefone && $palestrante->telefone)
                        <p class="mt-1 text-sm text-white/85">{{ $palestrante->telefone }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- Bio --}}
    @if ($palestrante->bio)
        <section class="mx-auto max-w-[760px] px-6 py-12">
            <div class="max-w-none text-text-secondary [&_p]:mb-4 [&_p]:leading-relaxed [&_a]:text-secondary [&_a]:underline">
                {!! $palestrante->bio !!}
            </div>
        </section>
    @endif

    {{-- Palestras ministradas --}}
    <section class="mx-auto max-w-[1240px] px-6 pb-16">
        <h2 class="mb-6 font-display text-2xl font-semibold text-primary">Palestras de {{ $palestrante->nome }}</h2>
        @if ($palestras->isEmpty())
            <p class="rounded-lg border border-border-muted bg-surface px-6 py-8 text-text-secondary">Nenhuma palestra publicada por ora.</p>
        @else
            <div class="grid gap-6 sm:grid-cols-2 desktop-sm:grid-cols-3">
                @foreach ($palestras as $palestra)
                    <x-palestra.card :palestra="$palestra" />
                @endforeach
            </div>
        @endif
    </section>
</x-layout.app>
