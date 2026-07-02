<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace Tests\Feature\Filament;

use App\Filament\Pages\ConfiguracoesAgenda;
use App\Models\Configuracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ConfiguracoesAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_configuracoes_agenda_renderiza(): void
    {
        $this->get('/admin/configuracoes-agenda')->assertOk();
    }

    public function test_configuracoes_agenda_grava_capa(): void
    {
        Storage::fake('public');

        $arquivo = UploadedFile::fake()->image('agenda-capa.jpg');

        Livewire::test(ConfiguracoesAgenda::class)
            ->fillForm(['agenda_capa' => $arquivo])
            ->call('salvar')
            ->assertHasNoFormErrors();

        $caminho = Configuracao::valor('agenda_capa');

        $this->assertNotNull($caminho);
        Storage::disk('public')->assertExists($caminho);
    }
}
