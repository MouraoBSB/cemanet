<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-02

namespace App\Filament\Pages;

use App\Filament\Support\ComponentesImagem;
use App\Models\ConfiguracaoAgenda as ConfiguracaoAgendaModel;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConfiguracoesAgenda extends Page
{
    protected string $view = 'filament.pages.configuracoes-agenda';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configurações da Agenda';

    protected static ?string $title = 'Configurações da Agenda';

    protected static ?string $slug = 'configuracoes-agenda';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?ConfiguracaoAgendaModel $record = null;

    public function mount(): void
    {
        $this->record = ConfiguracaoAgendaModel::instance();

        // Array não-nulo (mesmo vazio) força o hidratador a carregar o estado
        // do componente de mídia a partir das relações (mídia já existente).
        $this->form->fill([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->model($this->record)
            ->statePath('data')
            ->components([
                ComponentesImagem::upload('agenda_capa', ConfiguracaoAgendaModel::COLECAO_CAPA)
                    ->label('Capa da Agenda (livro)')
                    ->columnSpanFull(),
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
        // getState() já dispara saveRelationships() do componente de mídia
        // (grava/otimiza a capa na Media Library do registro vinculado).
        $this->form->getState();

        Notification::make()
            ->title('Configurações salvas com sucesso.')
            ->success()
            ->send();
    }
}
