<?php

namespace Tests\Unit;

use App\Auth\HasherLegadoCema;
use PHPUnit\Framework\TestCase;

class HasherLegadoCemaTest extends TestCase
{
    private HasherLegadoCema $hasher;

    protected function setUp(): void
    {
        $this->hasher = new HasherLegadoCema(['rounds' => 10]);
    }

    public function test_valida_hash_wp_bcrypt(): void
    {
        // hash $wp$ gerado com a mesma receita do WP 6.8 para a senha 'segredo123'
        $pre = base64_encode(hash_hmac('sha384', 'segredo123', 'wp-sha384', true));
        $hash = '$wp'.password_hash($pre, PASSWORD_BCRYPT);

        $this->assertTrue($this->hasher->check('segredo123', $hash));
        $this->assertFalse($this->hasher->check('errada', $hash));
        $this->assertTrue($this->hasher->needsRehash($hash));
    }

    public function test_valida_hash_phpass(): void
    {
        // round-trip: gera um $P$ e confere
        $setting = '$P$B'.'k9d2Xa7Q';
        $hash = $this->hasher->phpass('MinhaSenha#2026', $setting);

        $this->assertTrue($this->hasher->check('MinhaSenha#2026', $hash));
        $this->assertFalse($this->hasher->check('outra', $hash));
        $this->assertTrue($this->hasher->needsRehash($hash));
    }

    public function test_bcrypt_nativo_passa_direto_e_nao_precisa_rehash(): void
    {
        $hash = $this->hasher->make('nativa');

        $this->assertTrue($this->hasher->check('nativa', $hash));
        $this->assertFalse($this->hasher->needsRehash($hash));
    }
}
