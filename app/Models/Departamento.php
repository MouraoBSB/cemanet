<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-03

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    protected $fillable = ['sigla', 'nome', 'slug', 'descricao', 'cor', 'icone', 'ativo', 'ordem'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function setores(): HasMany
    {
        return $this->hasMany(Setor::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(Evento::class, 'departamento_evento', 'departamento_id', 'evento_id');
    }

    /** Tipos de conteúdo pelos quais este departamento responde (inversa da config de acesso). */
    public function tiposConteudo(): BelongsToMany
    {
        return $this->belongsToMany(
            TipoConteudo::class,
            'departamento_tipo_conteudo',
            'departamento_id',
            'tipo_conteudo_id',
        );
    }
}
