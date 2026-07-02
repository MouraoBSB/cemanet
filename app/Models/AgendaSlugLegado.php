<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AgendaSlugLegado extends Model
{
    use HasFactory;

    protected $table = 'agenda_slugs_legado';

    // A tabela não tem created_at/updated_at (mapa raso de 301).
    public $timestamps = false;

    protected $fillable = ['slug', 'data'];

    /**
     * Normaliza `data` para string Y-m-d na escrita (robusto a string ou
     * Carbon) e devolve Carbon na leitura. O cast nativo 'date:Y-m-d' só
     * corrige a escrita de strings: se o atributo for atribuído como Carbon,
     * o valor grava como Y-m-d H:i:s no SQLite (testes) mas trunca no MySQL
     * (prod), divergindo entre ambientes e quebrando comparações por string.
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? Carbon::parse($value) : null,
            set: fn ($value) => $value !== null ? Carbon::parse($value)->format('Y-m-d') : null,
        );
    }
}
