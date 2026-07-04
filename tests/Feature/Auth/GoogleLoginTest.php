<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('frequentador', 'web');
    }

    private function mockGoogle(string $id, ?string $email, string $nome = 'Membro Google'): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($id);
        $abstract->shouldReceive('getEmail')->andReturn($email);
        $abstract->shouldReceive('getName')->andReturn($nome);
        $abstract->shouldReceive('getNickname')->andReturn(null);
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($abstract);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_cria_frequentador_novo(): void
    {
        $this->mockGoogle('g-123', 'novo@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/minha-conta');

        $user = User::where('email', 'novo@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('g-123', $user->google_id);
        $this->assertTrue($user->hasRole('frequentador'));
        $this->assertNotNull($user->perfil);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_casa_usuario_existente_por_email_e_grava_google_id(): void
    {
        $user = User::factory()->create(['email' => 'migrado@gmail.com', 'google_id' => null, 'ativo' => true]);
        $this->mockGoogle('g-999', 'migrado@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect('/minha-conta');
        $this->assertSame('g-999', $user->fresh()->google_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_conta_inativa_bloqueada(): void
    {
        User::factory()->create(['email' => 'inativo@gmail.com', 'ativo' => false]);
        $this->mockGoogle('g-000', 'inativo@gmail.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_google_sem_email_redireciona_com_erro(): void
    {
        $this->mockGoogle('g-sememail', null);

        $this->get('/auth/google/callback')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_nao_sobrescreve_google_id_existente(): void
    {
        $user = User::factory()->create(['email' => 'link@x.com', 'google_id' => 'g-original', 'ativo' => true]);
        $this->mockGoogle('g-diferente', 'link@x.com');

        $this->get('/auth/google/callback')->assertRedirect('/minha-conta');
        $this->assertSame('g-original', $user->fresh()->google_id);
    }
}
