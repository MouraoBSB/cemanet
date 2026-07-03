<?php

namespace Tests\Feature\Usuarios;

use App\Importacao\ImportadorUsuarios;
use App\Importacao\LeitorUsuariosFake;
use App\Importacao\TransformadorUsuarios;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ImportadorUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): LeitorUsuariosFake
    {
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));

        return new LeitorUsuariosFake([
            [
                'origem_id' => 26, 'login' => 'ana', 'nome' => 'ANA KARLA DA SILVA',
                'email' => 'ana@exemplo.com', 'senha' => '$wp'.password_hash($pre, PASSWORD_BCRYPT),
                'registrado' => '2024-01-10 12:00:00',
                'roles' => ['trabalhador'], 'setores' => ['medium', 'coordenador_da_campanha_auta_de_souza'],
                'cargos' => [], 'socio' => 'true',
                'meta' => ['whatsapp' => '61999998888', 'whatsapp_publico' => 'on', 'nascimento' => '1980-05-02', 'endereco' => 'Qd 1', 'cursos' => null],
            ],
            [
                'origem_id' => 1, 'login' => 'DECOM1', 'nome' => 'DECOM1', 'email' => 'decom1@cemanet.org.br',
                'senha' => '$P$Bxxxxxxxxxxxxxxxxxxxxx', 'registrado' => null,
                'roles' => ['administrator'], 'setores' => [], 'cargos' => [], 'socio' => null, 'meta' => [],
            ],
        ]);
    }

    public function test_importa_classifica_e_ignora_admin_idempotente(): void
    {
        (new EstruturaCemaSeeder)->run();
        $importador = new ImportadorUsuarios($this->fake(), app(TransformadorUsuarios::class));

        $r1 = $importador->importar(fn ($m) => null);
        $r2 = $importador->importar(fn ($m) => null); // 2x → estável

        $this->assertSame(1, $r1['usuarios']);
        $this->assertSame(1, User::count()); // admin não migrou; idempotente
        $this->assertSame(1, $r1['ignorados']); // o admin

        $ana = User::where('email', 'ana@exemplo.com')->first();
        $this->assertSame('Ana Karla da Silva', $ana->name);
        $this->assertTrue($ana->socio);
        $this->assertNotNull($ana->email_verified_at);
        $this->assertTrue($ana->hasRole('trabalhador'));
        $this->assertSame('coordenador', $ana->setores()->where('slug', 'campanha-auta-de-souza')->first()->pivot->funcao);
        $this->assertSame('61999998888', $ana->perfil->whatsapp);
        $this->assertTrue($ana->perfil->whatsapp_publico);
    }

    public function test_senha_legada_valida_no_login(): void
    {
        (new EstruturaCemaSeeder)->run();
        (new ImportadorUsuarios($this->fake(), app(TransformadorUsuarios::class)))->importar(fn ($m) => null);

        $this->assertTrue(Auth::attempt([
            'email' => 'ana@exemplo.com', 'password' => 'segredo123',
        ]));
    }

    public function test_nascimento_unix_timestamp_e_convertido(): void
    {
        (new EstruturaCemaSeeder)->run();

        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $fake = new LeitorUsuariosFake([
            [
                'origem_id' => 26, 'login' => 'ana', 'nome' => 'ANA KARLA DA SILVA',
                'email' => 'ana@exemplo.com', 'senha' => '$wp'.password_hash($pre, PASSWORD_BCRYPT),
                'registrado' => '2024-01-10 12:00:00',
                'roles' => ['trabalhador'], 'setores' => [],
                'cargos' => [], 'socio' => null,
                'meta' => ['whatsapp' => null, 'whatsapp_publico' => null, 'nascimento' => '98064000', 'endereco' => null, 'cursos' => null],
            ],
        ]);

        (new ImportadorUsuarios($fake, app(TransformadorUsuarios::class)))->importar(fn ($m) => null);

        $ana = User::where('email', 'ana@exemplo.com')->first();
        $this->assertSame('1973-02-09', $ana->perfil->data_nascimento->format('Y-m-d'));
    }
}
