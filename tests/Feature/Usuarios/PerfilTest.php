<?php

namespace Tests\Feature\Usuarios;

use App\Models\PerfilMembro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_tem_um_perfil_membro(): void
    {
        $user = User::factory()->create(['socio' => true]);
        PerfilMembro::create([
            'user_id' => $user->id,
            'whatsapp' => '61999998888',
            'endereco' => 'Qd 1 Casa 2 - Planaltina - DF',
        ]);

        $this->assertTrue($user->fresh()->socio);
        $this->assertSame('61999998888', $user->perfil->whatsapp);
    }
}
