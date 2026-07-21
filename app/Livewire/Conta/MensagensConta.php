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
 * Superfície pública do LANÇAMENTO e da EDIÇÃO de Mensagem em /minha-conta (Fatia F4b, D2/D10): o
 * médium cria a mensagem, que nasce SEMPRE pendente, com `medium_id` dele e `nivel = null` — ele
 * não escolhe o nível; quem arbitra é o diretor do DEPAE, ao publicar na curadoria (task própria).
 * Enquanto PENDENTE, o próprio médium pode editar (policy `editarPendente`); depois de publicada,
 * a posse passa ao curador — a aba não mostra mais o corpo nem linka para a página pública (D10).
 *
 * A lista mostra só as PRÓPRIAS mensagens (medium_id = auth()->id()) — nunca as dos outros 45.
 */
class MensagensConta extends Component implements HasForms
{
    use AuthorizesRequests, InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $editandoId = null;

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
            ->model($this->editandoId ? Mensagem::find($this->editandoId) : Mensagem::class)
            ->statePath('data')
            ->operation($this->editandoId ? 'edit' : 'create');
    }

    public function novo(): void
    {
        $this->authorize('lancar', Mensagem::class);
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

    /**
     * P1 — a âncora explícita é obrigatória: o schema pode já ter sido acessado (e cacheado) ANTES
     * desta action rodar, com `model(Mensagem::class)` (class-string) preso no cache. Sem esta
     * linha, `getRecord()` devolve null e o Select `autores` (->relationship()) e a pictografia
     * (mídia) hidratam VAZIOS, sem erro — o form parece novo, não uma edição.
     */
    public function editar(int $id): void
    {
        $registro = Mensagem::findOrFail($id);
        $this->authorize('editarPendente', $registro);

        $this->editandoId = $registro->id;
        $this->form->model($registro); // ANTES do fill: não depende de quando o schema foi cacheado

        // M2 — `direcionar`/`destinatarios` são VIRTUAIS (não são coluna nem ->relationship()):
        // attributesToArray() não os traz. Sem isto, `direcionar` chegaria false e `nivel` viraria
        // null ao salvar — esvaziando o pivô de uma mensagem direcionada mesmo editando só o título.
        $this->form->fill([
            ...$registro->attributesToArray(),
            'direcionar' => $registro->nivel === VisibilidadeMensagem::Direcionada->value,
            'destinatarios' => $registro->destinatarios()->pluck('users.id')->all(),
        ]);

        $this->mostrandoForm = true;
    }

    public function salvar(): void
    {
        if ($this->editandoId) {
            $this->atualizarRegistro();

            session()->flash('status', 'Mensagem atualizada.');
            $this->redirect(route('conta.mensagens'), navigate: true);

            return;
        }

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

    /**
     * Mesma mecânica de criarRegistro() (campos virtuais capturados antes do unset, privilegiados
     * reasseridos), mas SEM tocar `status`/`medium_id`/`publicado_por_id`/`publicado_em`: a edição
     * é sempre de uma PENDENTE (o guard da policy já exige isso) e a posse/autoria não muda ao
     * editar. Também SEM repetir saveRelationships() — ver comentário abaixo, junto ao update().
     */
    private function atualizarRegistro(): Mensagem
    {
        return DB::transaction(function (): Mensagem {
            $registro = Mensagem::findOrFail($this->editandoId);
            $this->authorize('editarPendente', $registro);

            $dados = $this->form->getState(); // valida — DENTRO da transação

            $ehDirecionada = (bool) ($dados['direcionar'] ?? false);
            $idsDestinatarios = $dados['destinatarios'] ?? []; // Select SEM ->relationship(): desidratado normalmente

            // Campos privilegiados: nunca confiar no POST, mesmo que getState() já os pode.
            unset(
                $dados['direcionar'], $dados['destinatarios'], $dados['status'], $dados['nivel'],
                $dados['medium_id'], $dados['publicado_por_id'], $dados['publicado_em'],
            );

            $dados['nivel'] = $ehDirecionada ? VisibilidadeMensagem::Direcionada->value : null;

            // Ao contrário de criarRegistro(), NÃO repetir saveRelationships() aqui: o
            // getState() acima já chamou (Schema::getState() invocado sem argumentos usa
            // $shouldCallHooksBefore=true por padrão — vendor/filament/schemas/src/Concerns/
            // HasState.php:483) e, na edição, o schema já está ancorado num registro
            // persistido (a âncora P1 do editar()), então o pivô `autores` e a pictografia
            // já foram sincronizados ali. Só é necessário repetir em criarRegistro() porque,
            // no momento do getState() ali, o schema ainda aponta para a class-string
            // Mensagem::class — getRecord() é null e a sincronização interna não tem onde gravar.
            $registro->update($dados);

            SincronizadorDestinatarios::aplicar($registro, $registro->nivel, $idsDestinatarios);

            return $registro;
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
