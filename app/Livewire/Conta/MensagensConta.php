<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Livewire\Conta;

use App\Enums\VisibilidadeMensagem;
use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Conta\AbaMensagens;
use App\Support\Mensagens\SincronizadorDestinatarios;
use App\Support\Mensagens\SlugMensagem;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Superfície pública do LANÇAMENTO de Mensagem em /minha-conta (Fatia F4b, D2): o médium cria a
 * mensagem, que nasce SEMPRE pendente, com `medium_id` dele e `nivel = null` — ele não escolhe o
 * nível; quem arbitra é o diretor do DEPAE, ao publicar na curadoria (task própria).
 *
 * A lista mostra só as PRÓPRIAS mensagens (medium_id = auth()->id()) — nunca as dos outros 45.
 */
class MensagensConta extends Component implements HasForms
{
    use AuthorizesRequests, InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $mostrandoForm = false;

    public function mount(): void
    {
        abort_unless(AbaMensagens::visivelPara(auth()->user()), 403);
    }

    public function boot(): void
    {
        // /minha-conta não é painel Filament → porta cairia em 'sistema'. Marcar 'perfil'
        // explicitamente, em toda requisição do componente (inclui o /livewire/update do save).
        AuditoriaAutorizacao::usarPorta('perfil');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(MensagemForm::schemaMedium())
            ->model(Mensagem::class)
            ->statePath('data')
            ->operation('create');
    }

    public function novo(): void
    {
        $this->authorize('lancar', Mensagem::class);
        $this->form->fill();
        $this->mostrandoForm = true;
    }

    public function cancelar(): void
    {
        $this->mostrandoForm = false;
        $this->form->fill();
    }

    public function salvar(): void
    {
        $this->authorize('lancar', Mensagem::class);

        try {
            $this->criarRegistro();
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            // Corrida de slug único (unique de mensagens.slug): regenera e tenta UMA vez.
            $this->criarRegistro();
        }

        session()->flash('status', 'Mensagem enviada para curadoria.');
        $this->redirect(route('conta.mensagens'), navigate: true);
    }

    /**
     * Um único write, dentro de uma transação. Ordem obrigatória (o G1 desta fatia): capturar os
     * campos virtuais ANTES do unset, reasserir os campos privilegiados no servidor e só então
     * chamar saveRelationships() — sem essa linha o pivô de autores e a pictografia somem sem erro
     * e sem log, porque o Select `autores` usa ->relationship() (dehydrated(false)) e só grava ali.
     */
    private function criarRegistro(): Mensagem
    {
        return DB::transaction(function (): Mensagem {
            $dados = $this->form->getState(); // valida — DENTRO da transação

            $ehDirecionada = (bool) ($dados['direcionar'] ?? false);
            $idsDestinatarios = $dados['destinatarios'] ?? []; // Select SEM ->relationship(): desidratado normalmente

            // Campos privilegiados: nunca confiar no POST, mesmo que getState() já os pode.
            unset(
                $dados['direcionar'], $dados['destinatarios'], $dados['status'], $dados['nivel'],
                $dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em'],
            );

            $dados['status'] = Mensagem::STATUS_PENDENTE; // sempre — o médium não escolhe (D2)
            $dados['nivel'] = $ehDirecionada ? VisibilidadeMensagem::Direcionada->value : null;
            $dados['slug'] = SlugMensagem::unico($dados['titulo']);

            // UM único write: `new` respeita o $fillable; a autoria é atribuição direta (NÃO é
            // fillable) — um create() seguido de save() geraria um `updated` espúrio na trilha.
            $mensagem = new Mensagem($dados);
            $mensagem->medium_id = auth()->id();
            $mensagem->save();

            // A linha do G1.
            $this->form->model($mensagem)->saveRelationships();

            SincronizadorDestinatarios::aplicar($mensagem, $mensagem->nivel, $idsDestinatarios);

            return $mensagem;
        });
    }

    public function render(): View
    {
        $itens = Mensagem::query()
            ->where('medium_id', auth()->id())
            ->orderByDesc('id')
            ->get();

        return view('livewire.conta.mensagens-conta', compact('itens'));
    }
}
