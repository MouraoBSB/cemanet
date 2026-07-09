<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-08

namespace Tests\Feature\Front;

use App\Enums\VisibilidadeEvento;
use App\Livewire\Eventos\Lista;
use App\Models\CategoriaEvento;
use App\Models\Evento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventoListaTest extends TestCase
{
    use RefreshDatabase;

    private function ev(string $slug, string $dataInicio, ?int $catId = null, VisibilidadeEvento $v = VisibilidadeEvento::Publico): Evento
    {
        return Evento::create([
            'titulo' => ucfirst($slug), 'slug' => $slug, 'data_inicio' => $dataInicio,
            'categoria_evento_id' => $catId, 'visibilidade' => $v, 'status' => Evento::STATUS_PUBLICADO,
        ]);
    }

    public function test_abas_particionam_futuros_e_passados(): void
    {
        $this->ev('futuro', now()->addDays(10)->toDateString());
        $this->ev('passado', now()->subDays(10)->toDateString());

        Livewire::test(Lista::class)
            ->assertSee('Futuro')->assertDontSee('Passado')   // aba padrão = próximos
            ->set('aba', 'anteriores')
            ->assertSee('Passado')->assertDontSee('Futuro');
    }

    public function test_filtro_categoria_e_busca(): void
    {
        $cat = CategoriaEvento::create(['nome' => 'Brechó', 'slug' => 'brecho', 'cor' => '#89AB98']);
        $this->ev('brecho-junho', now()->addDays(5)->toDateString(), $cat->id);
        $this->ev('feirao-maio', now()->addDays(6)->toDateString());

        Livewire::test(Lista::class)
            ->set('categoria', 'brecho')->assertSee('Brecho-junho')->assertDontSee('Feirao-maio')
            ->set('categoria', '')->set('q', 'feirao')->assertSee('Feirao-maio')->assertDontSee('Brecho-junho');
    }

    public function test_exclui_destaque_da_grade(): void
    {
        $d = $this->ev('destaque', now()->addDays(1)->toDateString());
        $this->ev('outro', now()->addDays(2)->toDateString());

        Livewire::test(Lista::class, ['destaqueId' => $d->id])
            ->assertDontSee('Destaque')->assertSee('Outro');
    }

    public function test_restrito_nao_aparece_para_anonimo(): void
    {
        $this->ev('reservado', now()->addDays(3)->toDateString(), null, VisibilidadeEvento::Diretoria);
        $this->ev('aberto', now()->addDays(3)->toDateString());

        Livewire::test(Lista::class)->assertSee('Aberto')->assertDontSee('Reservado');
    }

    public function test_estado_vazio(): void
    {
        Livewire::test(Lista::class)->assertSee('Nenhum evento encontrado');
    }

    public function test_card_mostra_selo_de_visibilidade_so_para_logado(): void
    {
        Role::updateOrCreate(['name' => 'diretor', 'guard_name' => 'web'], ['nivel' => 30]);
        $u = User::factory()->create();
        $u->assignRole('diretor');
        // evento público mais próximo assume o destaque (hero), deixando o restrito na grade — onde o card vive.
        $this->ev('brecho-selo', Carbon::now()->addDays(2)->toDateString());
        Evento::create(['titulo' => 'Reunião', 'slug' => 'reuniao', 'data_inicio' => Carbon::now()->addDays(5)->toDateString(),
            'visibilidade' => VisibilidadeEvento::Diretoria, 'status' => Evento::STATUS_PUBLICADO]);

        // anônimo nem vê o card (filtrado) — sem selo
        $this->get('/eventos')->assertDontSee('Somente diretoria');
        // diretor vê o card com o selo
        $this->actingAs($u)->get('/eventos')->assertSee('Somente diretoria');
    }
}
