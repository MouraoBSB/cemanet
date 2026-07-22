<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-22

namespace Tests\Feature\Importacao;

use App\Importacao\LeitorResumosMensagens;
use App\Importacao\LeitorResumosMensagensMysql;
use Tests\TestCase;

class LeitorResumosMensagensBindTest extends TestCase
{
    /** Sem bind manual, a INTERFACE resolve para o ...Mysql (molde de ImportarMensagensCommandTest:44). */
    public function test_interface_resolve_para_o_mysql(): void
    {
        $this->assertInstanceOf(LeitorResumosMensagensMysql::class, app(LeitorResumosMensagens::class));
    }
}
