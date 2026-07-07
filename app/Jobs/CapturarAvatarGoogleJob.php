<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Jobs;

use App\Importacao\BaixadorImagem;
use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CapturarAvatarGoogleJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId, public string $avatarUrl) {}

    public function handle(BaixadorImagem $baixador): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $perfil = PerfilMembro::firstOrCreate(['user_id' => $user->id]);
        if (! $perfil->podeAutoPopularFoto()) {
            return;
        }

        $bytes = $baixador->baixarCapado($this->avatarUrl, 2000);
        if ($bytes === null) {
            Log::info('Avatar do Google não baixado', ['user' => $user->id]);

            return;
        }

        $perfil->addMediaFromString($bytes)
            ->usingFileName('google-avatar.jpg')
            ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
    }
}
