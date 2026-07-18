<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-17

namespace Tests\Unit\Usuarios;

use App\Support\Usuarios\IntegridadePapel;
use PHPUnit\Framework\TestCase;

class IntegridadePapelTest extends TestCase
{
    // Níveis: sem-papel 0, frequentador 10, trabalhador 20, diretor 30, admin 100.

    public function test_sem_setor_e_sem_cargo_e_integro_em_qualquer_nivel(): void
    {
        foreach ([0, 10, 20, 30, 100] as $nivel) {
            $this->assertSame([], IntegridadePapel::violacoes($nivel, false, false), "nível {$nivel}");
        }
    }

    public function test_r1_setor_exige_trabalhador_ou_acima(): void
    {
        $this->assertCount(1, IntegridadePapel::violacoes(0, true, false));   // sem-papel + setor
        $this->assertCount(1, IntegridadePapel::violacoes(10, true, false));  // frequentador + setor
        $this->assertSame([], IntegridadePapel::violacoes(20, true, false));  // trabalhador + setor: ok
        $this->assertSame([], IntegridadePapel::violacoes(30, true, false));  // diretor + setor: ok
    }

    public function test_r2_cargo_exige_diretor(): void
    {
        $this->assertCount(1, IntegridadePapel::violacoes(0, false, true));   // sem-papel + cargo
        $this->assertCount(1, IntegridadePapel::violacoes(10, false, true));  // frequentador + cargo
        $this->assertCount(1, IntegridadePapel::violacoes(20, false, true));  // trabalhador + cargo
        $this->assertSame([], IntegridadePapel::violacoes(30, false, true));  // diretor + cargo: ok
    }

    public function test_frequentador_com_setor_e_cargo_acumula_duas_violacoes(): void
    {
        $this->assertCount(2, IntegridadePapel::violacoes(10, true, true));
    }

    public function test_admin_passa_com_setor_e_cargo(): void
    {
        $this->assertSame([], IntegridadePapel::violacoes(100, true, true));
    }

    public function test_mensagens_sao_pt_br_e_orientam(): void
    {
        $r1 = IntegridadePapel::violacoes(10, true, false)[0];
        $r2 = IntegridadePapel::violacoes(20, false, true)[0];
        $this->assertStringContainsString('Trabalhador', $r1);
        $this->assertStringContainsString('Diretor', $r2);
    }
}
