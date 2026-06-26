<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-26

namespace App\Filament\Pages;

use App\Models\Configuracao;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConfiguracoesBlog extends Page
{
    protected string $view = 'filament.pages.configuracoes-blog';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Configurações do Blog';

    protected static ?string $title = 'Configurações do Blog';

    protected static ?string $slug = 'configuracoes-blog';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'reflexao_do_dia' => Configuracao::valor('blog.reflexao_do_dia', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('reflexao_do_dia')
                    ->label('Reflexão do dia')
                    ->rows(4)
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
        $dados = $this->form->getState();

        Configuracao::definir('blog.reflexao_do_dia', $dados['reflexao_do_dia'] ?? '');

        Notification::make()
            ->title('Configurações salvas com sucesso.')
            ->success()
            ->send();
    }
}
