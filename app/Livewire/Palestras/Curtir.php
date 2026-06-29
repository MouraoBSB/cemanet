<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

namespace App\Livewire\Palestras;

use App\Models\Palestra;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Curtir extends Component
{
    #[Locked]
    public int $palestraId;

    public int $curtidas = 0;

    public function mount(Palestra $palestra): void
    {
        $this->palestraId = $palestra->id;
        $this->curtidas = $palestra->curtidas;
    }

    public function curtir(): void
    {
        $this->ajustar(1);
    }

    public function descurtir(): void
    {
        $this->ajustar(-1);
    }

    private function ajustar(int $delta): void
    {
        $chave = 'curtir:'.request()->ip().':'.$this->palestraId;
        if (RateLimiter::tooManyAttempts($chave, 20)) {
            return;
        }
        RateLimiter::hit($chave, 60);

        $palestra = Palestra::findOrFail($this->palestraId);
        if ($delta > 0) {
            $palestra->increment('curtidas');
        } elseif ($palestra->curtidas > 0) {
            $palestra->decrement('curtidas');
        }

        $this->curtidas = $palestra->refresh()->curtidas;
    }

    public function render()
    {
        return view('livewire.palestras.curtir');
    }
}
