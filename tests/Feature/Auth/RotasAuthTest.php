<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Auth;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RotasAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_de_auth_renderizam(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Entrar');
        $this->get('/cadastro')->assertOk()->assertSee('Criar conta');
        $this->get('/esqueci-a-senha')->assertOk();
        $this->get('/redefinir-senha/token-qualquer?email=a@b.com')->assertOk();
    }

    public function test_fallback_preserva_redirect_301_de_slug_de_post(): void
    {
        Post::factory()->create(['slug' => 'reflexao-do-dia', 'status' => 'publicado']);

        $this->get('/reflexao-do-dia')->assertRedirect('/sementeira/reflexao-do-dia');
        $this->get('/slug-que-nao-existe')->assertNotFound();
    }
}
