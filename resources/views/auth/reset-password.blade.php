{{-- Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04 --}}
<x-layout.auth titulo="Redefinir senha">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-medium">E-mail</label>
            <input id="email" name="email" type="email" required autocomplete="email"
                   value="{{ old('email', $request->email) }}"
                   @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('email')<p id="email-erro" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Nova senha</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   @error('password') aria-invalid="true" aria-describedby="password-erro" @enderror
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
            @error('password')<p id="password-erro" class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmar nova senha</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="mt-1 w-full rounded-md border border-border px-3 py-2 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="w-full rounded-pill bg-primary px-4 py-2.5 font-medium text-white hover:bg-primary/90">Redefinir senha</button>
    </form>
</x-layout.auth>
