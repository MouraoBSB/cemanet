<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
    ];

    /** Retorna o valor de uma chave ou o $default se não existir. */
    public static function valor(string $chave, mixed $default = null): mixed
    {
        return static::firstWhere('chave', $chave)?->valor ?? $default;
    }

    /** Cria ou atualiza uma configuração pelo par chave/valor. */
    public static function definir(string $chave, mixed $valor): static
    {
        return static::updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }
}
