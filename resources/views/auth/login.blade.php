{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<x-layout.auth titulo="Entrar">
    @if (session('status'))
        <p class="mb-4 rounded-md bg-accent/15 px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p id="email-erro" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Senha</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded border-border text-primary focus:ring-primary"> Lembrar de mim
        </label>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Entrar</button>
    </form>

    <a href="{{ route('google.redirect') }}" class="mt-3 flex w-full items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 font-medium hover:bg-surface">
        <x-icon.google />
        Entrar com Google
    </a>

    <div class="mt-4 flex justify-between text-sm">
        <a href="{{ route('password.request') }}" class="text-text-muted underline hover:text-primary">Esqueci a senha</a>
        <a href="{{ route('register') }}" class="text-text-muted underline hover:text-primary">Criar conta</a>
    </div>
</x-layout.auth>
