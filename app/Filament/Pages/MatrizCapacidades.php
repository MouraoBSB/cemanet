<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-12

namespace App\Filament\Pages;

use App\Enums\RegimeAcesso;
use App\Importacao\GlossarioUsuarios;
use App\Models\Departamento;
use App\Models\TipoConteudo;
use App\Support\Autorizacao\AuditoriaAutorizacao;
use App\Support\Autorizacao\GlossarioCapacidades;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

/**
 * Configuração de acesso por tipo (Camada 1) — evolução da matriz papel×capacidade (Fase C).
 * Por tipo: as 20 capacidades × papel (toggles) + o REGIME + os DEPARTAMENTOS RESPONSÁVEIS.
 * Única escritora de role_has_permissions E da config de acesso (tipos_conteudo) — I8.
 * Admin-only pelo portão do painel. syncPermissions já limpa o cache do spatie (não chamar forget).
 *
 * O slug segue 'matriz-capacidades': trocar a rota quebraria links e não traz benefício.
 */
class MatrizCapacidades extends Page
{
    protected string $view = 'filament.pages.matriz-capacidades';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Configuração de acesso por tipo';

    protected static ?string $title = 'Configuração de acesso por tipo';

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

        // Config de acesso por tipo — namespace separado dos toggles ($estado[papel][recurso][acao]):
        // aqui é $estado[recurso][regime|departamentos]. Não colidem (PAPEIS_EDITAVEIS ∌ RECURSOS).
        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $tipo = TipoConteudo::with('departamentos')->where('recurso', $recurso)->first();

            // Recurso sem linha: regime null (o required reprova o submit) e nenhum responsável.
            // A tela escreve a config EXISTENTE — quem semeia o catálogo é o TiposConteudoSeeder (I8).
            $estado[$recurso]['regime'] = $tipo?->regime?->value;
            $estado[$recurso]['departamentos'] = $tipo?->departamentos->pluck('id')->all() ?? [];
        }

        $this->form->fill($estado);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->secoesPorRecurso());
    }

    /** @return array<Component> uma Section por recurso: regime + responsáveis + toggles (ação × papel). */
    private function secoesPorRecurso(): array
    {
        $secoes = [];
        // Hoistado: dentro do laço seriam 5 queries idênticas (uma por Section).
        $departamentos = Departamento::orderBy('sigla')->pluck('nome', 'id');

        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $campos = [
                Select::make("{$recurso}.regime")
                    ->label('Regime de acesso')
                    ->options(RegimeAcesso::opcoes())
                    ->required()
                    ->live()
                    ->columnSpanFull(),
                Select::make("{$recurso}.departamentos")
                    ->label('Departamentos responsáveis')
                    ->options($departamentos)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    // disabled + dehydrated(true), NUNCA visible/hidden: componente oculto não
                    // desidrata (HasState.php:774-783) e o salvar() receberia [] — apagaria os
                    // responsáveis. disabled() também não desidrata sozinho (CanBeDisabled.php:25).
                    // A integridade real é o cinto do salvar(), não isto aqui: com dehydrated(true)
                    // o valor vem do CLIENTE.
                    ->disabled(fn (Get $get): bool => $get("{$recurso}.regime") !== RegimeAcesso::DoTipo->value)
                    ->dehydrated(true)
                    ->helperText(fn (Get $get): string => $get("{$recurso}.regime") === RegimeAcesso::DoTipo->value
                        ? 'Quem responde por este tipo. A responsabilidade só chega ao usuário pelo vínculo com o departamento, em Usuários.'
                        : 'Regime "em cada registro": estes responsáveis ficam guardados, mas não são lidos. Voltar ao "do tipo" os restaura.')
                    ->columnSpanFull(),
            ];

            foreach (GlossarioCapacidades::ACOES as $acao) {
                foreach (GlossarioUsuarios::PAPEIS_EDITAVEIS as $papel) {
                    $campos[] = Toggle::make("{$papel}.{$recurso}.{$acao}")
                        ->label(GlossarioCapacidades::rotuloAcao($acao).' — '.ucfirst($papel))
                        ->inline(false);
                }
            }

            $secoes[] = Section::make(GlossarioCapacidades::rotuloRecurso($recurso))
                ->columns(count(GlossarioUsuarios::PAPEIS_EDITAVEIS))
                ->schema($campos);
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

        $this->salvarConfigPorTipo($estado);

        Notification::make()
            ->title('Matriz de capacidades salva com sucesso.')
            ->success()
            ->send();
    }

    /** Escreve a config de acesso por tipo. Só toca linha existente (o catálogo é do seeder — I8). */
    private function salvarConfigPorTipo(array $estado): void
    {
        foreach (GlossarioCapacidades::RECURSOS as $recurso) {
            $tipo = TipoConteudo::where('recurso', $recurso)->first();

            if ($tipo === null) {
                continue;   // recurso sem linha: a tela não cria catálogo
            }

            $regime = RegimeAcesso::tryFrom($estado[$recurso]['regime'] ?? '');

            if ($regime === null) {
                continue;   // o required() já reprovou; belt contra valor fora do enum
            }

            $regimeAntes = $tipo->regime->value;
            $tipo->update(['regime' => $regime]);
            AuditoriaAutorizacao::registrarRegimeTipo($tipo, $regimeAntes, $regime->value);

            // CINTO SERVER-SIDE: no "em cada registro" os responsáveis NÃO são sincronizados — são
            // preservados POR CONSTRUÇÃO, não pela hidratação do form. Com dehydrated(true) o valor
            // vem do cliente, e o vendor avisa (CanBeDisabled.php:20-24) que disabled() não é
            // barreira. Sem este if, um POST forjado com departamentos=[] apagaria a config.
            if ($regime !== RegimeAcesso::DoTipo) {
                continue;
            }

            $ids = array_map('intval', $estado[$recurso]['departamentos'] ?? []);
            $antes = $tipo->departamentos()->pluck('departamentos.nome', 'departamentos.id')->all();   // ANTES do sync
            $tipo->departamentos()->sync($ids);
            $depois = Departamento::whereIn('id', $ids)->pluck('nome', 'id')->all();

            AuditoriaAutorizacao::registrarDepartamentosTipo($tipo, $antes, $depois);
        }
    }
}
