<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Livewire\Conta;

use Illuminate\View\View;
use Livewire\Component;

/**
 * STUB (Task 4) — implementação da lista/form da agenda é da Task 5.
 * Sem mount/guarda aqui: o acesso já é barrado pelo ContaController@agenda
 * (abort_unless via AbaAgenda) antes desta view renderizar.
 */
class AgendaConta extends Component
{
    public function render(): View
    {
        return view('livewire.conta.agenda-conta');
    }
}
