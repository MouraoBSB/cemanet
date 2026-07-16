<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace App\Models;

use App\Enums\RegimeAcesso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Configuração de acesso de um TIPO de conteúdo (1 linha por recurso do GlossarioCapacidades):
 * o regime + os departamentos responsáveis. Escrita SÓ pela página MatrizCapacidades (I8); o
 * TiposConteudoSeeder é insert-only e apenas semeia a linha ausente.
 *
 * NÃO implementa TemDepartamento: não é conteúdo — o contrato existe para o trait de policy.
 */
class TipoConteudo extends Model
{
    protected $table = 'tipos_conteudo';

    protected $fillable = ['recurso', 'regime'];

    protected function casts(): array
    {
        return ['regime' => RegimeAcesso::class];
    }

    /** Departamentos responsáveis pelo tipo (lidos só no regime "do tipo"). */
    public function departamentos(): BelongsToMany
    {
        return $this->belongsToMany(
            Departamento::class,
            'departamento_tipo_conteudo',
            'tipo_conteudo_id',
            'departamento_id',
        );
    }
}
