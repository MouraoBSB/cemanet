<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-20

namespace App\Filament\Pages;

use App\Models\Configuracao;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConfiguracoesContato extends Page
{
    protected string $view = 'filament.pages.configuracoes-contato';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Configurações de Contato';

    protected static ?string $title = 'Configurações de Contato';

    protected static ?string $slug = 'configuracoes-contato';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'contato_email' => Configuracao::valor('contato.email', ''),
            'contato_whatsapp' => Configuracao::valor('contato.whatsapp', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('contato_email')
                    ->label('E-mail de contato')
                    ->email()
                    ->maxLength(255)
                    ->helperText('Exibido na tela "sem permissão" das mensagens restritas.'),
                TextInput::make('contato_whatsapp')
                    ->label('WhatsApp (com DDI/DDD)')
                    ->maxLength(30)
                    ->helperText('Ex.: +55 61 99999-0000'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('salvar')
                ->footer([
                    Actions::make([
                        Action::make('salvar')
                            ->label('Salvar')
                            ->submit('salvar'),
                    ]),
                ]),
        ]);
    }

    public function salvar(): void
    {
        $dados = $this->form->getState();

        Configuracao::definir('contato.email', $dados['contato_email'] ?? '');
        Configuracao::definir('contato.whatsapp', $dados['contato_whatsapp'] ?? '');

        Notification::make()
            ->title('Configurações salvas com sucesso.')
            ->success()
            ->send();
    }
}
