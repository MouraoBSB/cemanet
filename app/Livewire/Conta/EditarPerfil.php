<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-04

namespace App\Livewire\Conta;

use App\Models\PerfilMembro;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditarPerfil extends Component
{
    use WithFileUploads;

    public string $name = '';

    public ?string $data_nascimento = null;

    public ?string $endereco = null;

    public ?string $whatsapp = null;

    public bool $whatsapp_publico = false;

    public $foto = null;

    public bool $removerFoto = false;

    public function mount(): void
    {
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);

        $this->name = (string) $user->name;
        $this->data_nascimento = $perfil->data_nascimento?->format('Y-m-d');
        $this->endereco = $perfil->endereco;
        $this->whatsapp = $perfil->whatsapp;
        $this->whatsapp_publico = (bool) $perfil->whatsapp_publico;
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date', 'before:today'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'whatsapp_publico' => ['boolean'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    public function removerFoto(): void
    {
        $this->removerFoto = true;
        $this->foto = null; // remover e enviar são mutuamente exclusivos
    }

    public function updatedFoto(): void
    {
        if ($this->foto) {
            $this->removerFoto = false; // novo upload cancela a remoção pendente
        }
    }

    public function temFoto(): bool
    {
        return (bool) (auth()->user()->perfil?->hasMedia(PerfilMembro::COLECAO_FOTO));
    }

    public function salvar()
    {
        $dados = $this->validate();
        $user = auth()->user();
        $perfil = $user->perfil()->firstOrCreate([]);

        DB::transaction(function () use ($user, $perfil, $dados) {
            $user->update(['name' => $dados['name']]);
            $perfil->update([
                'data_nascimento' => $dados['data_nascimento'],
                'endereco' => $dados['endereco'],
                'whatsapp' => $dados['whatsapp'],
                'whatsapp_publico' => $dados['whatsapp_publico'],
            ]);

            if ($this->foto) {
                $perfil->addMedia($this->foto->getRealPath())
                    ->usingFileName('foto.'.$this->foto->getClientOriginalExtension())
                    ->toMediaCollection(PerfilMembro::COLECAO_FOTO);
                $perfil->foto_definida_pelo_membro = true;
                $perfil->save();
            } elseif ($this->removerFoto) {
                $perfil->clearMediaCollection(PerfilMembro::COLECAO_FOTO);
                $perfil->foto_definida_pelo_membro = true;
                $perfil->save();
            }
        });

        session()->flash('status', 'Perfil atualizado.');

        return $this->redirect(route('conta.perfil'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.conta.editar-perfil');
    }
}
