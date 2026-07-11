<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Model que pertence a departamentos — habilita o escopo editorial das policies. */
interface TemDepartamento
{
    public function departamentos(): BelongsToMany;
}
