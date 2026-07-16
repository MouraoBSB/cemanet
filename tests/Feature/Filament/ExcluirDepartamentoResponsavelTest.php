<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-16

namespace Tests\Feature\Filament;

use App\Filament\Resources\Departamentos\Pages\ListDepartamentos;
use App\Models\Departamento;
use Database\Seeders\CapacidadesSeeder;
use Database\Seeders\EstruturaCemaSeeder;
use Database\Seeders\TiposConteudoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExcluirDepartamentoResponsavelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new EstruturaCemaSeeder)->run();
        $this->seed(CapacidadesSeeder::class);
        $this->seed(TiposConteudoSeeder::class);   // DED responde por agenda/palestra/palestrante
        $this->actingAsAdmin();
    }

    public function test_nao_exclui_departamento_que_responde_por_um_tipo(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();

        Livewire::test(ListDepartamentos::class)
            ->callTableAction('delete', $ded);

        $this->assertDatabaseHas('departamentos', ['id' => $ded->id]);
    }

    public function test_exclui_departamento_que_nao_responde_por_nenhum_tipo(): void
    {
        // Departamento novo e SEM vínculos: os do EstruturaCemaSeeder arrastam setores/cargos,
        // cujas FKs fariam o teste falhar por motivo alheio ao guarda.
        $avulso = Departamento::create(['sigla' => 'DTESTE', 'nome' => 'Departamento de Teste', 'slug' => 'dteste']);

        Livewire::test(ListDepartamentos::class)
            ->callTableAction('delete', $avulso);

        $this->assertDatabaseMissing('departamentos', ['id' => $avulso->id]);
    }

    public function test_bulk_nao_exclui_se_algum_responde_por_tipo(): void
    {
        $ded = Departamento::where('sigla', 'DED')->first();   // responde por agenda/palestra/palestrante
        $avulso = Departamento::create(['sigla' => 'DTESTE', 'nome' => 'Departamento de Teste', 'slug' => 'dteste']);

        Livewire::test(ListDepartamentos::class)
            ->callTableBulkAction('delete', [$ded->id, $avulso->id]);

        $this->assertDatabaseHas('departamentos', ['id' => $ded->id]);
        // O 3º parâmetro de assertDatabaseHas é a CONEXÃO, não uma mensagem: para não perder o
        // porquê da asserção, o lote inteiro é conferido por assertNotNull (que aceita mensagem).
        $this->assertNotNull($avulso->fresh(), 'o guarda deixou passar o resto do lote');
    }
}
