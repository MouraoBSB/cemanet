<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-10
// SPIKE (descartável): prova que o MESMO schema do EventoResource renderiza e SALVA
// dentro de uma página do site, com a validação do Filament rodando.

namespace App\Livewire\Spike;

use App\Filament\Schemas\EventoForm;
use App\Models\Evento;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FormularioEvento extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $eventoSalvoId = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(EventoForm::schema())   // FONTE ÚNICA (mesma do EventoResource)
            ->model(Evento::class)
            ->statePath('data')
            ->operation('create');
    }

    public function salvar(): void
    {
        // getState() dispara a validação do Filament (required, unique, afterOrEqual,
        // e a regra de PeriodoEvento::horaFimAntesNoMesmoDia).
        $dados = $this->form->getState();

        $evento = Evento::create($dados);

        // Persiste mídia (Spatie) + relações (departamentos), como o CreateRecord do painel faz.
        $this->form->model($evento)->saveRelationships();

        $this->eventoSalvoId = $evento->id;

        $this->form->fill();
    }

    public function render(): View
    {
        return view('livewire.spike.formulario-evento');
    }
}
