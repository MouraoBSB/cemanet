<?php

namespace Tests\Unit;

use App\Importacao\LeitorUsuariosFake;
use PHPUnit\Framework\TestCase;

class LeitorUsuariosFakeTest extends TestCase
{
    public function test_fake_devolve_os_itens(): void
    {
        $fake = new LeitorUsuariosFake([['email' => 'a@b.com']]);
        $this->assertSame('a@b.com', iterator_to_array((function () use ($fake) {
            yield from $fake->usuarios();
        })())[0]['email']);
    }
}
