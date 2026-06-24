<?php
// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-24

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PalestraDestaque extends Model
{
    protected $fillable = ['palestra_id', 'destaque', 'texto', 'ordem'];

    public function palestra(): BelongsTo
    {
        return $this->belongsTo(Palestra::class);
    }
}
