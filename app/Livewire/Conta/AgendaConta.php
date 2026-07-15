<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace App\Livewire\Conta;

use App\Filament\Schemas\AgendaDiaForm;
use App\Models\AgendaDia;
use App\Support\Agenda\AgendaMantenedores;
use App\Support\Conta\AbaAgenda;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Superfície pública da Agenda da Reforma Íntima em /minha-conta: lista escopada ao
 * departamento do usuário + criação. Edição/exclusão chegam na Task 7.
 *
 * Campos privilegiados NUNCA confiam no POST: `departamentos` é ausente do schema do site
 * (AgendaDiaForm::schema(comDepartamentos: false)) e é forçado no servidor para os
 * mantenedores (DED+DECOM); `status` é reasserido contra o enum e travado em rascunho para
 * quem tem agenda.criar mas não agenda.editar (D-F9).
 */
class AgendaConta extends Component implements HasForms
{
    use AuthorizesRequests, InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $editandoId = null;

    public bool $mostrandoForm = false;

    public function mount(): void
    {
        abort_unless(AbaAgenda::visivelPara(auth()->user()), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(AgendaDiaForm::schema(comDepartamentos: false))
            ->model($this->editandoId ? AgendaDia::find($this->editandoId) : AgendaDia::class)
            ->statePath('data')
            ->operation($this->editandoId ? 'edit' : 'create');
    }

    /** Só para a UI decidir mostrar o botão "Novo dia" (fail-closed; a autorização real é no novo()/salvar()). */
    public function podeCriar(): bool
    {
        return auth()->user()->checkPermissionTo('agenda.criar');
    }

    public function novo(): void
    {
        $this->authorize('criar', AgendaDia::class);
        $this->editandoId = null;
        $this->form->fill();
        $this->mostrandoForm = true;
    }

    public function cancelar(): void
    {
        $this->mostrandoForm = false;
        $this->editandoId = null;
        $this->form->fill();
    }

    public function salvar(): void
    {
        $user = auth()->user();
        $this->authorize('criar', AgendaDia::class); // agenda.criar + departamentos()->exists()

        $dados = $this->form->getState(); // valida required + unique('data') do schema

        // Belt server-side do unique('data') por string Y-m-d (portátil — [[padrao-data-mutator-portavel]]).
        $dataYmd = Carbon::parse($dados['data'])->format('Y-m-d');
        if ($this->dataJaUsada($dataYmd)) {
            $this->addError('data', 'Já existe um dia de agenda nessa data.');

            return;
        }

        // Campo privilegiado STATUS: enum reasserido no servidor + quem não tem agenda.editar não
        // publica na criação (D-F9).
        $dados['status'] = $this->statusValido($dados['status']);
        if (! $user->checkPermissionTo('agenda.editar')) {
            $dados['status'] = AgendaDia::STATUS_RASCUNHO;
        }

        $registro = AgendaDia::create($dados);

        // Campo privilegiado DEPARTAMENTOS forçado: todo novo AgendaDia nasce DED+DECOM (O1).
        $registro->departamentos()->sync(AgendaMantenedores::ids());

        session()->flash('status', 'Dia da agenda criado.');
        $this->redirect(route('conta.agenda'), navigate: true);
    }

    /** Belt server-side do unique('data'): consulta por string Y-m-d (portátil), podendo ignorar um id. */
    private function dataJaUsada(string $dataYmd, ?int $ignorarId = null): bool
    {
        return AgendaDia::query()
            ->where('data', $dataYmd)
            ->when($ignorarId, fn ($q) => $q->where('id', '!=', $ignorarId))
            ->exists();
    }

    /** Belt do enum de status: nunca confia no POST — valor fora do enum vira rascunho. */
    private function statusValido(?string $status): string
    {
        return in_array($status, [AgendaDia::STATUS_PUBLICADO, AgendaDia::STATUS_RASCUNHO], true)
            ? $status
            : AgendaDia::STATUS_RASCUNHO;
    }

    public function render(): View
    {
        $itens = AgendaDia::noEscopoDe(auth()->user())
            ->orderBy('data', 'desc')
            ->get();

        return view('livewire.conta.agenda-conta', compact('itens'));
    }
}
