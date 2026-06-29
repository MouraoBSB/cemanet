<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-28

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/** Campo de formulário: grade visual de miniaturas da biblioteca; o estado é o id da mídia escolhida. */
class SeletorMidiaBiblioteca extends Field
{
    protected string $view = 'filament.forms.seletor-midia-biblioteca';
}
