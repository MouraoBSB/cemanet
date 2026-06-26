<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_e_seeder(): void
    {
        $this->seed(\Database\Seeders\CategoriaSeeder::class);
        $this->assertSame(6, \App\Models\Categoria::count());
        $this->assertDatabaseHas('categorias', ['slug' => 'cema-em-acao', 'cor' => '#E79048']);
        \App\Models\Post::factory()->create(['slug' => 'x']);
        $this->assertDatabaseHas('posts', ['slug' => 'x']);
    }
}
