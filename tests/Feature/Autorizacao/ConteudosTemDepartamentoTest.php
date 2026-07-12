<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-11

namespace Tests\Feature\Autorizacao;

use App\Models\AgendaDia;
use App\Models\Contracts\TemDepartamento;
use App\Models\Departamento;
use App\Models\Palestra;
use App\Models\Palestrante;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConteudosTemDepartamentoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{class-string<Model>, string}> */
    public static function conteudos(): array
    {
        return [
            'palestra' => [Palestra::class, 'departamento_palestra'],
            'post' => [Post::class, 'departamento_post'],
            'palestrante' => [Palestrante::class, 'departamento_palestrante'],
            'agenda_dia' => [AgendaDia::class, 'departamento_agenda_dia'],
        ];
    }

    #[DataProvider('conteudos')]
    public function test_model_implementa_contrato_e_pivot_nasce_vazia(string $model, string $pivot): void
    {
        $this->assertInstanceOf(TemDepartamento::class, new $model);
        $this->assertSame(0, DB::table($pivot)->count());
    }

    #[DataProvider('conteudos')]
    public function test_relaciona_e_desrelaciona_departamentos(string $model, string $pivot): void
    {
        $obj = $model::factory()->create();
        $ded = Departamento::create(['sigla' => 'DED', 'nome' => 'DED', 'slug' => 'ded']);
        $decom = Departamento::create(['sigla' => 'DECOM', 'nome' => 'DECOM', 'slug' => 'decom']);

        $obj->departamentos()->attach([$ded->id, $decom->id]);
        $this->assertSame(2, $obj->departamentos()->count());

        $obj->departamentos()->detach($ded->id);
        $this->assertSame(1, $obj->departamentos()->count());
    }
}
