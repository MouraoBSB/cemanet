<?php

namespace Tests\Feature\Usuarios;

use App\Models\Cargo;
use App\Models\Departamento;
use App\Models\Setor;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EstruturaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_idempotente_cria_estrutura(): void
    {
        (new EstruturaCemaSeeder)->run();
        (new EstruturaCemaSeeder)->run(); // 2x → sem duplicar

        $this->assertSame(4, Role::count());
        $this->assertSame(8, Departamento::count());
        $this->assertSame(16, Setor::count()); // 17 slugs → 16 setores-base (campanha colapsa)
        $this->assertSame(13, Cargo::count()); // 12 + Diretor do DAS
        $this->assertNull(Setor::where('slug', 'pamana')->first()->departamento_id);
        $this->assertSame(30, (int) Role::findByName('diretor')->nivel);
    }
}
