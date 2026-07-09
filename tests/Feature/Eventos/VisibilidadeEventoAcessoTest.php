<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Eventos;

use App\Enums\VisibilidadeEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisibilidadeEventoAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['frequentador' => 10, 'trabalhador' => 20, 'diretor' => 30, 'administrador' => 100] as $slug => $nivel) {
            Role::updateOrCreate(['name' => $slug, 'guard_name' => 'web'], ['nivel' => $nivel]);
        }
    }

    private function usuario(?string $papel): ?User
    {
        if ($papel === null) {
            return null;
        }
        $u = User::factory()->create();
        $u->assignRole($papel);

        return $u;
    }

    private function evento(VisibilidadeEvento $vis, string $slug = 'e'): Evento
    {
        return Evento::create([
            'titulo' => 'E', 'slug' => $slug, 'data_inicio' => '2026-06-27',
            'visibilidade' => $vis, 'status' => Evento::STATUS_PUBLICADO,
        ]);
    }

    public function test_matriz_pode_ser_visto_por(): void
    {
        $niveis = [
            'publico' => VisibilidadeEvento::Publico,
            'logados' => VisibilidadeEvento::Logados,
            'trabalhadores' => VisibilidadeEvento::Trabalhadores,
            'diretoria' => VisibilidadeEvento::Diretoria,
        ];
        // [papel, [publico, logados, trabalhadores, diretoria]]
        // Nota: lista indexada (não associativa) — uma chave `null => ...` seria
        // coagida pelo PHP para a chave string '', o que faria o papel "anônimo"
        // criar um User real (sem papel) em vez de testar $usuario === null.
        $esperado = [
            [null, [true, false, false, false]],            // anônimo
            ['frequentador', [true, true, false, false]],
            ['trabalhador', [true, true, true, false]],
            ['diretor', [true, true, true, true]],
            ['administrador', [true, true, true, true]],    // vê tudo
        ];

        foreach ($esperado as [$papel, $linha]) {
            $u = $this->usuario($papel);
            $i = 0;
            foreach ($niveis as $vis) {
                $evento = $this->evento($vis, "e-{$papel}-{$i}");
                $this->assertSame($linha[$i], $evento->podeSerVistoPor($u), "papel={$papel} vis={$vis->value}");
                $i++;
            }
        }
    }

    public function test_scope_visiveis_para_filtra_no_banco(): void
    {
        $this->evento(VisibilidadeEvento::Publico, 'pub');
        $this->evento(VisibilidadeEvento::Diretoria, 'dir');

        $this->assertSame(1, Evento::visiveisPara(null)->count());               // anônimo só o público
        $this->assertSame(2, Evento::visiveisPara($this->usuario('diretor'))->count());
        $this->assertSame(2, Evento::visiveisPara($this->usuario('administrador'))->count());
        $this->assertSame(1, Evento::visiveisPara($this->usuario('frequentador'))->count());
    }

    public function test_policy_view_via_gate(): void
    {
        $diretoria = $this->evento(VisibilidadeEvento::Diretoria, 'gate-dir');
        $publico = $this->evento(VisibilidadeEvento::Publico, 'gate-pub');

        $this->assertTrue(Gate::forUser($this->usuario('diretor'))->allows('view', $diretoria));
        $this->assertFalse(Gate::forUser(null)->allows('view', $diretoria)); // anônimo negado no restrito
        $this->assertTrue(Gate::forUser(null)->allows('view', $publico));     // anônimo vê o público
    }
}
