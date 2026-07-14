<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace App\Filament\Pages;

use App\Importacao\GlossarioUsuarios;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Autorizacao\GlossarioCapacidades;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

/**
 * Matriz papel×capacidade (Fase C): liga/desliga as 20 capacidades para os papéis
 * trabalhador e diretor. Único escritor de role_has_permissions. Admin-only pelo portão
 * do painel. syncPermissions já limpa o cache do spatie (não chamar forget).
 */
class MatrizCapacidades extends Page
{
    protected string $view = 'filament.pages.matriz-capacidades';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Matriz de capacidades';

    protected static ?string $title = 'Matriz de capacidades';

    protected static ?string $slug = 'matriz-capacidades';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $estado = [];

        foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
            $nomes = Role::findByName($papel, 'web')->permissions()->pluck('name')->all();

            foreach (GlossarioCapacidades::RECURSOS as $recurso) {
                foreach (GlossarioCapacidades::ACOES as $acao) {
                    $estado[$papel][$recurso][$acao] = in_array("{$recurso}.{$acao}", $nomes, true);
                }
            }
        }

        $this->form->fill($estado);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->secoesPorRecurso());
    }

    /** @return array<Component> uma Section por recurso; toggles = ação × papel. */
    private function secoesPorRecurso(): array
    {
        $secoes = [];

        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $toggles = [];

            foreach (GlossarioCapacidades::ACOES as $acao) {
                foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
                    $toggles[] = Toggle::make("{$papel}.{$recurso}.{$acao}")
                        ->label(GlossarioCapacidades::rotuloAcao($acao).' — '.ucfirst($papel))
                        ->inline(false);
                }
            }

            $secoes[] = Section::make(GlossarioCapacidades::rotuloRecurso($recurso))
                ->columns(count(GlossarioUsuarios::PAPEIS_EDITAVEIS))
                ->schema($toggles);
        }

        return $secoes;
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
        $estado = $this->form->getState();

        foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
            $marcados = [];

            foreach (GlossarioCapacidades::RECURSOS as $recurso) {
                foreach (GlossarioCapacidades::ACOES as $acao) {
                    if (($estado[$papel][$recurso][$acao] ?? false) === true) {
                        $marcados[] = "{$recurso}.{$acao}";
                    }
                }
            }

            // findByName + syncPermissions (nunca recriar o papel: zeraria 'nivel').
            // syncPermissions já limpa o cache do spatie — não chamar forget (F10).
            $role = Role::findByName($papel, 'web');
            $antes = $role->permissions()->pluck('name')->all();   // ANTES (relê do banco)
            $role->syncPermissions($marcados);
            AuditoriaAutorizacao::registrarPapelCapacidades($role, $antes, $marcados);
        }

        Notification::make()
            ->title('Matriz de capacidades salva com sucesso.')
            ->success()
            ->send();
    }
}
