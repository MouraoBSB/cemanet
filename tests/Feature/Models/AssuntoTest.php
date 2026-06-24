<?php

namespace Tests\Feature\Models;

use App\Models\Assunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssuntoTest extends TestCase
{
    use RefreshDatabase;

    public function test_assunto_tem_pai_e_filhos(): void
    {
        $pai = Assunto::create(['nome' => 'Espiritismo', 'slug' => 'espiritismo']);
        $filho = Assunto::create(['nome' => 'Fé', 'slug' => 'fe', 'parent_id' => $pai->id]);

        $this->assertTrue($filho->parent->is($pai));
        $this->assertTrue($pai->children->contains($filho));
        $this->assertNull($pai->parent);
    }
}
