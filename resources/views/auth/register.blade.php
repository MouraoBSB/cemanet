<x-layout.auth titulo="Criar conta">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium">Nome</label>
            <input id="name" name="name" type="text" required autofocus autocomplete="name" value="{{ old('name') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('password')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmar senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Cadastrar</button>
    </form>

    <a href="{{ route('google.redirect') }}" class="mt-3 flex w-full items-center justify-center gap-2 rounded-pill border border-border px-4 py-2.5 font-medium hover:bg-surface">
        Cadastrar com Google
    </a>

    <p class="mt-4 text-center text-sm text-text-muted">
        Já tem conta? <a href="{{ route('login') }}" class="underline hover:text-primary">Entrar</a>
    </p>
</x-layout.auth>
