<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailTransacionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_remetente_default_e_do_cema(): void
    {
        $this->assertSame('admin@cemanet.org.br', config('mail.from.address'));
        $this->assertSame('CEMA', config('mail.from.name'));
    }

    public function test_email_de_reset_em_pt_br_com_url_correta(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'membro@x.com']);

        $this->post('/esqueci-a-senha', ['email' => 'membro@x.com'])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Redefinição de senha — CEMA'
                && $mail->actionText === 'Redefinir senha'
                && str_contains($mail->actionUrl, '/redefinir-senha/')
                && collect($mail->introLines)->contains(fn ($linha) => str_contains($linha, 'redefinir a senha'));
        });
    }
}
