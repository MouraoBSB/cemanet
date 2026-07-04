<x-layout.auth titulo="Esqueci a senha">
    @if (session('status'))
        <p class="mb-4 rounded-md bg-accent/15 px-3 py-2 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif
    <p class="mb-4 text-sm text-text-secondary">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email" value="{{ old('email') }}"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Enviar link</button>
    </form>
    <p class="mt-4 text-center text-sm"><a href="{{ route('login') }}" class="underline hover:text-primary">Voltar ao login</a></p>
</x-layout.auth>
