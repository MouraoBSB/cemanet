<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Console;

use App\Models\AgendaDia;
use App\Models\Departamento;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SomarDedAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class);
    }

    public function test_soma_ded_preservando_decom(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $comDecom = AgendaDia::factory()->create();
        $comDecom->departamentos()->sync([$decom]);

        $this->artisan('cema:somar-ded-agenda')->assertSuccessful();

        $ids = $comDecom->departamentos()->pluck('departamentos.id')->all();
        $this->assertContains($decom, $ids);
        $this->assertContains($ded, $ids);
        $this->assertCount(2, $ids);
    }

    public function test_idempotente_e_ignora_quem_nao_tem_decom(): void
    {
        $decom = Departamento::where('sigla', 'DECOM')->value('id');
        $ded = Departamento::where('sigla', 'DED')->value('id');
        $das = Departamento::where('sigla', 'DAS')->value('id');
        $comDecom = AgendaDia::factory()->create();
        $comDecom->departamentos()->sync([$decom]);
        $semDecom = AgendaDia::factory()->create();
        $semDecom->departamentos()->sync([$das]);

        $this->artisan('cema:somar-ded-agenda')->assertSuccessful();
        $this->artisan('cema:somar-ded-agenda')->assertSuccessful(); // 2ª vez: sem duplicar

        $this->assertSame([$ded, $decom], $comDecom->departamentos()->orderByRaw('departamentos.id')->pluck('departamentos.id')->sort()->values()->all());
        $this->assertSame([$das], $semDecom->departamentos()->pluck('departamentos.id')->all()); // intocado
    }
}
