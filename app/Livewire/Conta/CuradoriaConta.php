<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-21

namespace App\Livewire\Conta;

use App\Filament\Schemas\MensagemForm;
use App\Models\Mensagem;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Conta\AbaCuradoria;
use App\Support\Mensagens\HistoricoMensagem;
use App\Support\Mensagens\RegraPublicacao;
use App\Support\Mensagens\SincronizadorDestinatarios;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Superfície pública da CURADORIA em /minha-conta/curadoria (Fatia F4b): o diretor do DEPAE (ou
 * o presidente) vê a fila de TODAS as pendentes e corrige título/corpo/nível/autores/pictografia.
 * `salvar()` NUNCA muda o status — a curadoria pode salvar quantas vezes quiser sem que a
 * mensagem saia de pendente. `publicar()` (Task 10) é o martelo: arbitra o nível de acesso via
 * `RegraPublicacao` e põe a mensagem no ar.
 *
 * O furo B4 (ver task-9-report.md, Step 2a): autorizar com `curar` (sem objeto) deixaria um
 * curador abrir/editar/publicar uma mensagem JÁ PUBLICADA, porque o id vem do cliente.
 * `editar()`/`salvar()`/`publicar()` autorizam com `editarNaCuradoria`/`publicar` sobre o
 * REGISTRO (exige status pendente) — `findOrFail()` roda ANTES do `authorize()`, nunca o contrário.
 */
class CuradoriaConta extends Component implements HasForms
{
    use AuthorizesRequests, InteractsWithForms;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?int $editandoId = null;

    public bool $mostrandoForm = false;

    public function mount(): void
    {
        abort_unless(AbaCuradoria::visivelPara(auth()->user()), 403);
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
            ->components(MensagemForm::schemaCuradoria())
            ->model($this->editandoId ? Mensagem::find($this->editandoId) : Mensagem::class)
            ->statePath('data')
            ->operation('edit');
    }

    public function cancelar(): void
    {
        $this->mostrandoForm = false;
        $this->editandoId = null;
        $this->form->fill();
    }

    /**
     * Âncora P1 (molde MensagensConta::editar) — obrigatória: sem `->model()` ANTES do `fill()`,
     * o Select `autores` (->relationship()) e a pictografia hidratam VAZIOS, sem erro.
     */
    public function editar(int $id): void
    {
        $registro = Mensagem::findOrFail($id);
        $this->authorize('editarNaCuradoria', $registro); // NUNCA 'curar' sem objeto — furo B4

        $this->editandoId = $registro->id;
        $this->form->model($registro); // ANTES do fill: não depende de quando o schema foi cacheado

        // `destinatarios` é VIRTUAL (Select sem ->relationship()): attributesToArray() não o traz.
        $this->form->fill([
            ...$registro->attributesToArray(),
            'destinatarios' => $registro->destinatarios()->pluck('users.id')->all(),
        ]);

        $this->mostrandoForm = true;
    }

    public function salvar(): void
    {
        $this->atualizarRegistro();

        session()->flash('status', 'Mensagem atualizada.');
        $this->redirect(route('conta.curadoria'), navigate: true);
    }

    /**
     * O martelo: o diretor do DEPAE (ou presidente) arbitra o nível e publica. `findOrFail` +
     * `authorize` ANTES da transação (a policy já exige status pendente, via `editarNaCuradoria`).
     *
     * Fix pós-revisão (Important, Task 10): `publicar` é um método público Livewire — nada
     * impede um curador de chamá-lo com um `$id` diferente do `editandoId` corrente, fora da UI
     * (o botão só emite `publicar({{ $editandoId }})`, mas isso não amarra o servidor). `$registro`
     * (do `$id` do cliente) seria usado para fill/save, enquanto `$this->form->getState()` — mais
     * abaixo — opera sobre o modelo ANCORADO em `$this->editandoId` (P1, `editar()`): dois
     * registros diferentes na mesma operação. O guard trava o `$id` recebido no `$editandoId` do
     * próprio componente ANTES do `authorize`, fechando a divergência na origem.
     *
     * `RegraPublicacao::erros()` roda DEPOIS do `getState()` mas AINDA DENTRO da transação: o
     * `getState()` já executa `saveRelationships()` (autores/pictografia) internamente, porque o
     * schema está ancorado no registro (P1). Se a checagem ficasse fora da transação, uma
     * publicação recusada por nível inválido deixaria essas relações já gravadas — meio save
     * aplicado. Dentro da transação, o `throw` reverte tudo.
     */
    public function publicar(int $id): void
    {
        $registro = Mensagem::findOrFail($id);
        abort_unless($id === $this->editandoId, 403);
        $this->authorize('publicar', $registro);

        DB::transaction(function () use ($registro): void {
            $dados = $this->form->getState(); // valida + saveRelationships() — DENTRO da transação

            $erros = RegraPublicacao::erros($dados);

            if ($erros !== []) {
                throw ValidationException::withMessages(['data.nivel' => $erros[0]]);
            }

            $idsDestinatarios = $dados['destinatarios'] ?? [];
            unset($dados['destinatarios']);

            $registro->fill($dados);
            $registro->status = Mensagem::STATUS_PUBLICADO;
            $registro->publicado_por_id = auth()->id();
            $registro->publicado_em = now();
            $registro->save();

            SincronizadorDestinatarios::aplicar($registro, $registro->nivel, $idsDestinatarios);
        });

        session()->flash('status', 'Mensagem publicada.');
        $this->redirect(route('conta.curadoria'), navigate: true);
    }

    /**
     * Mesma mecânica de MensagensConta::atualizarRegistro() (campo virtual capturado antes do
     * unset, `getState()` já roda saveRelationships() com o schema ancorado no registro pela P1),
     * mas SEMPRE reasserindo `status = pendente`: salvar NUNCA publica, mesmo que o Select `status`
     * não exista em schemaCuradoria (getState() já poda uma chave forjada) — a reasserção explícita
     * documenta a regra e blinda contra uma futura mudança de schema.
     */
    private function atualizarRegistro(): Mensagem
    {
        return DB::transaction(function (): Mensagem {
            $registro = Mensagem::findOrFail($this->editandoId);
            $this->authorize('editarNaCuradoria', $registro); // NUNCA 'curar' sem objeto — furo B4

            $dados = $this->form->getState(); // valida — DENTRO da transação

            $idsDestinatarios = $dados['destinatarios'] ?? []; // Select SEM ->relationship(): desidratado normalmente
            unset($dados['destinatarios']);

            $dados['status'] = Mensagem::STATUS_PENDENTE; // sempre — salvar nunca publica

            $registro->update($dados);

            SincronizadorDestinatarios::aplicar($registro, $registro->nivel, $idsDestinatarios);

            return $registro;
        });
    }

    public function render(): View
    {
        $itens = Mensagem::query()
            ->where('status', Mensagem::STATUS_PENDENTE)
            ->with('medium:id,name', 'autores')
            ->orderByDesc('data_recebimento')
            ->get();

        // Aviso "editada pelo autor após o lançamento" — Task 11 (HistoricoMensagem), 1 query p/ a fila inteira.
        $editadasPeloAutor = HistoricoMensagem::editadasPeloAutor($itens);

        $editando = $this->editandoId ? Mensagem::find($this->editandoId) : null;

        return view('livewire.conta.curadoria-conta', [
            'itens' => $itens,
            'editadasPeloAutor' => $editadasPeloAutor,
            'editando' => $editando,
        ]);
    }
}
