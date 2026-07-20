<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-19

namespace Tests\Feature\Mensagens;

use App\Models\Cargo;
use App\Models\Mensagem;
use App\Models\Setor;
use App\Models\User;
use Database\Seeders\EstruturaCemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MensagemVisibilidadeAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstruturaCemaSeeder::class); // papéis 10/20/30/100 + setores + cargos
    }

    /** Usuário com papel + setores/cargos opcionais (slugs). null (tudo vazio) = anônimo. */
    private function usuario(?string $papel, array $setores = [], array $cargos = []): ?User
    {
        if ($papel === null && $setores === [] && $cargos === []) {
            return null;
        }
        $u = User::factory()->create();
        if ($papel !== null) {
            $u->assignRole($papel);
        }
        foreach ($setores as $slug) {
            $u->setores()->attach(Setor::where('slug', $slug)->value('id'), ['funcao' => 'membro']);
        }
        foreach ($cargos as $slug) {
            $u->cargos()->attach(Cargo::where('slug', $slug)->value('id'));
        }

        return $u->fresh();
    }

    private function mensagem(?string $nivel, string $slug): Mensagem
    {
        return Mensagem::factory()->create(['nivel' => $nivel, 'slug' => $slug]);
    }

    public function test_matriz_pode_ser_visto_por(): void
    {
        // uma mensagem por nível de ESCADA/RECORTE fixo
        $msgs = [
            'publico' => $this->mensagem('publico', 'm-pub'),
            'trabalhadores' => $this->mensagem('trabalhadores', 'm-trab'),
            'mediuns' => $this->mensagem('mediuns-trabalhadores', 'm-med'),
            'diretores' => $this->mensagem('diretores', 'm-dir'),
            'diretor-depae' => $this->mensagem('diretor-depae', 'm-dd'),
        ];

        $medium = $this->usuario('trabalhador', [Setor::SLUG_MEDIUM]);
        $diretorDepae = $this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]);
        $presidente = $this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]); // cargo baixo → bypass mesmo assim

        // [persona, [publico, trabalhadores, mediuns, diretores, diretor-depae]]
        $matriz = [
            [$this->usuario(null), [true, false, false, false, false]],          // anônimo
            [$this->usuario('frequentador'), [true, false, false, false, false]],
            [$this->usuario('trabalhador'), [true, true, false, false, false]],
            [$medium, [true, true, true, false, false]],                          // recorte médium
            [$this->usuario('diretor'), [true, true, false, true, false]],        // NÃO vê médiuns
            [$diretorDepae, [true, true, false, true, true]],                     // recorte diretor-depae
            [$presidente, [true, true, true, true, true]],                        // bypass
            [$this->usuario('administrador'), [true, true, true, true, true]],    // bypass
        ];

        foreach ($matriz as [$u, $esperado]) {
            $i = 0;
            foreach ($msgs as $chave => $m) {
                $this->assertSame($esperado[$i], $m->podeSerVistoPor($u), "persona x {$chave}");
                $i++;
            }
        }
    }

    public function test_direcionada_so_destinatario_e_bypass(): void
    {
        $dirA = $this->mensagem('direcionada', 'm-dirA');
        $dirB = $this->mensagem('direcionada', 'm-dirB');
        $destinatario = $this->usuario('frequentador');
        $dirA->destinatarios()->attach($destinatario->id);

        $this->assertTrue($dirA->podeSerVistoPor($destinatario));
        $this->assertFalse($dirB->podeSerVistoPor($destinatario)); // não é destinatário de B
        $this->assertFalse($dirA->podeSerVistoPor($this->usuario('diretor'))); // diretor não é destinatário
        $this->assertTrue($dirA->podeSerVistoPor($this->usuario('administrador')));
        $this->assertTrue($dirA->podeSerVistoPor($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE])));
    }

    public function test_nivel_null_fail_closed_menos_bypass(): void
    {
        $nula = $this->mensagem(null, 'm-null');
        $this->assertFalse($nula->podeSerVistoPor(null));
        $this->assertFalse($nula->podeSerVistoPor($this->usuario('diretor')));
        $this->assertTrue($nula->podeSerVistoPor($this->usuario('administrador')));
        $this->assertTrue($nula->podeSerVistoPor($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE])));
    }

    public function test_scope_visiveis_para_nao_vaza(): void
    {
        // 8 mensagens: 5 níveis fixos + 2 direcionadas + 1 sem nível
        $this->mensagem('publico', 's-pub');
        $this->mensagem('trabalhadores', 's-trab');
        $this->mensagem('mediuns-trabalhadores', 's-med');
        $this->mensagem('diretores', 's-dir');
        $this->mensagem('diretor-depae', 's-dd');
        $dirA = $this->mensagem('direcionada', 's-dirA');
        $this->mensagem('direcionada', 's-dirB');
        $this->mensagem(null, 's-null');

        $destinatario = $this->usuario('frequentador');
        $dirA->destinatarios()->attach($destinatario->id);

        $this->assertSame(1, Mensagem::visiveisPara(null)->count());
        $this->assertSame(['publico'], Mensagem::visiveisPara(null)->pluck('nivel')->all());
        $this->assertSame(1, Mensagem::visiveisPara($this->usuario('frequentador'))->count());
        $this->assertSame(2, Mensagem::visiveisPara($this->usuario('trabalhador'))->count());
        $this->assertSame(3, Mensagem::visiveisPara($this->usuario('trabalhador', [Setor::SLUG_MEDIUM]))->count());
        $this->assertSame(3, Mensagem::visiveisPara($this->usuario('diretor'))->count());
        $this->assertSame(4, Mensagem::visiveisPara($this->usuario('diretor', [], [Cargo::SLUG_DIRETOR_DEPAE]))->count());
        $this->assertSame(2, Mensagem::visiveisPara($destinatario)->count()); // publico + a direcionada dele
        $this->assertSame(8, Mensagem::visiveisPara($this->usuario('administrador'))->count()); // bypass = tudo
        $this->assertSame(8, Mensagem::visiveisPara($this->usuario('frequentador', [], [Cargo::SLUG_PRESIDENTE]))->count());
    }
}
