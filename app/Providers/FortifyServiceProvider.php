<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Fortify::ignoreRoutes(); // rotas declaradas no web.php (pt-BR, acima do fallback)
    }

    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null; // mensagem genérica do Fortify (sem revelar se o e-mail existe)
            }

            if (! $user->ativo) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Sua conta está inativa, entre em contato com o administrador do sistema.',
                ]);
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($request->password)])->save();
            }

            return $user;
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));

        // E-mail de redefinição de senha em pt-BR, na identidade CEMA (o padrão do Laravel vem em inglês).
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
            $minutos = config('auth.passwords.users.expire', 60);

            return (new MailMessage)
                ->subject('Redefinição de senha — CEMA')
                ->greeting('Olá!')
                ->line('Recebemos um pedido para redefinir a senha da sua conta no CEMA.')
                ->action('Redefinir senha', $url)
                ->line("Este link expira em {$minutos} minutos.")
                ->line('Se você não fez esse pedido, ignore este e-mail — nada muda.')
                ->salutation('Abraço fraterno, Equipe CEMA');
        });
    }
}
