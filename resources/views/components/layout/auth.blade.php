@props(['titulo' => 'Entrar'])
{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo ?? 'Entrar' }} · CEMA</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-full bg-cream font-sans text-text-ink antialiased">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <a href="{{ url('/') }}" class="mb-8 flex justify-center" aria-label="Voltar ao site do CEMA">
            <span class="font-display text-2xl font-bold text-primary">CEMA</span>
        </a>

        <section class="rounded-lg bg-white p-6 shadow-card sm:p-8">
            <h1 class="mb-6 font-display text-xl font-semibold text-primary">{{ $titulo ?? 'Entrar' }}</h1>
            {{ $slot }}
        </section>

        <a href="{{ url('/') }}" class="mt-6 text-center text-sm text-text-muted underline hover:text-primary">
            ← Voltar ao site
        </a>
    </main>
</body>
</html>
