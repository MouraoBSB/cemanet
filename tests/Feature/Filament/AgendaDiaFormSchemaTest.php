<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-15

namespace Tests\Feature\Filament;

use App\Filament\Schemas\AgendaDiaForm;
use Filament\Forms\Components\Select;
use Tests\TestCase;

class AgendaDiaFormSchemaTest extends TestCase
{
    /** O Select 'departamentos' é um componente de topo do array (não aninhado no Grid). */
    private function temSelectDepartamentos(array $schema): bool
    {
        return collect($schema)->contains(
            fn ($c) => $c instanceof Select && $c->getName() === 'departamentos'
        );
    }

    /**
     * Trava anti-regressão: 'departamentos' é campo privilegiado e NÃO pode voltar ao form.
     * A Agenda está no regime "do tipo" — o pivô do registro não é lido nem gravado (§6.4), e
     * o schema é o MESMO no painel e no site (o parâmetro do toggle deixou de existir).
     */
    public function test_schema_nao_expoe_departamentos(): void
    {
        $this->assertFalse($this->temSelectDepartamentos(AgendaDiaForm::schema()));
    }
}
