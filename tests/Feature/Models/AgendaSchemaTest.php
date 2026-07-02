<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabelas_da_agenda_existem_com_as_colunas_esperadas(): void
    {
        $this->assertTrue(Schema::hasTable('agenda_metas_mes'));
        $this->assertTrue(Schema::hasTable('agenda_dias'));
        $this->assertTrue(Schema::hasTable('agenda_slugs_legado'));

        $this->assertTrue(Schema::hasColumns('agenda_metas_mes', [
            'id', 'ano', 'mes', 'titulo', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('agenda_dias', [
            'id', 'data', 'reflexao', 'meta_mes_texto', 'meta_dia_titulo',
            'meta_dia_texto', 'prece', 'status', 'wp_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('agenda_slugs_legado', [
            'id', 'slug', 'data',
        ]));
    }
}
