<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $g = Socialite::driver('google')->user();

        $user = User::where('google_id', $g->getId())->first()
            ?? User::where('email', $g->getEmail())->first();

        if ($user) {
            if (! $user->ativo) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Sua conta está inativa, entre em contato com o administrador do sistema.']);
            }
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $g->getId()])->save();
            }
        } else {
            $user = DB::transaction(function () use ($g) {
                $novo = User::create([
                    'name' => $g->getName() ?: $g->getNickname() ?: 'Membro',
                    'email' => mb_strtolower(trim($g->getEmail())),
                    'password' => Hash::make(Str::random(64)), // inutilizável; "esqueci a senha" cria uma local
                    'google_id' => $g->getId(),
                    'ativo' => true,
                    'email_verified_at' => now(),
                ]);
                $novo->assignRole('frequentador');
                $novo->perfil()->create([]);

                return $novo;
            });
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate(); // evita session fixation (paridade com o PrepareAuthenticatedSession do Fortify)

        return redirect()->intended('/');
    }
}
