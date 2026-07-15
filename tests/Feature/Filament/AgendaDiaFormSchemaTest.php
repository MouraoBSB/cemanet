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

    public function test_schema_padrao_inclui_departamentos(): void
    {
        $this->assertTrue($this->temSelectDepartamentos(AgendaDiaForm::schema()));
    }

    public function test_schema_do_site_omite_departamentos(): void
    {
        $comDeptos = AgendaDiaForm::schema(comDepartamentos: true);
        $semDeptos = AgendaDiaForm::schema(comDepartamentos: false);

        // O site tem exatamente 1 componente a menos (o Select departamentos) e não o contém.
        $this->assertCount(count($comDeptos) - 1, $semDeptos);
        $this->assertFalse($this->temSelectDepartamentos($semDeptos), 'O schema do site NÃO deve incluir departamentos.');
    }
}
