<?php

namespace Tests\Feature\Models;

use App\Models\Categoria;
use App\Models\Post;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_e_seeder(): void
    {
        $this->seed(CategoriaSeeder::class);
        $this->assertSame(6, Categoria::count());
        $this->assertDatabaseHas('categorias', ['slug' => 'cema-em-acao', 'cor' => '#E79048']);
        Post::factory()->create(['slug' => 'x']);
        $this->assertDatabaseHas('posts', ['slug' => 'x']);
    }
}
