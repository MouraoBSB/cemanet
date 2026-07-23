<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

namespace Tests\Feature\Idioma;

use Tests\TestCase;

/**
 * Trava a completude de lang/pt_BR/validation.php contra o canônico do framework. O Translator faz
 * fallback por CHAVE, não por arquivo: uma regra faltando sai em inglês NA MESMA TELA, ao lado das
 * traduzidas. Quando um composer update trouxer regra nova, este teste fica vermelho — que é
 * exatamente o aviso que se quer.
 *
 * A comparação é RECURSIVA e EXCLUI `custom` e `attributes`: `attributes` é [] no canônico e tem
 * conteúdo no pt-BR (é o que faz as telas fora do Filament dizerem "data de nascimento" em vez de
 * "data nascimento"), e `custom` é placeholder. Comparar as duas daria vermelho falso — e o
 * "conserto" seria esvaziar justamente a seção que motiva o arquivo.
 */
class ValidationPtBrTest extends TestCase
{
    private const CAMINHO_CANONICO = 'vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php';

    private const SECOES_QUE_DIVERGEM = ['custom', 'attributes'];

    /** @return list<string> chaves em notação de ponto, ordenadas */
    private function chaves(array $itens, string $prefixo = ''): array
    {
        $chaves = [];

        foreach ($itens as $chave => $valor) {
            $caminho = $prefixo === '' ? (string) $chave : "{$prefixo}.{$chave}";
            $chaves[] = $caminho;

            if (is_array($valor)) {
                $chaves = array_merge($chaves, $this->chaves($valor, $caminho));
            }
        }

        sort($chaves);

        return $chaves;
    }

    private function semSecoesQueDivergem(array $itens): array
    {
        return array_diff_key($itens, array_flip(self::SECOES_QUE_DIVERGEM));
    }

    public function test_cobre_todas_as_chaves_do_canonico(): void
    {
        $canonico = base_path(self::CAMINHO_CANONICO);

        $this->assertFileExists($canonico, "O canônico do Laravel mudou de lugar: {$canonico} não existe. Reveja este teste (I13) antes de concluir que falta tradução.");

        $esperadas = $this->chaves($this->semSecoesQueDivergem(require $canonico));
        $traduzidas = $this->chaves($this->semSecoesQueDivergem(require lang_path('pt_BR/validation.php')));

        $this->assertSame($esperadas, $traduzidas, 'lang/pt_BR/validation.php divergiu do canônico — chave faltando sai em inglês na mesma tela');
    }

    /** A seção `attributes` só importa fora do Filament (lá o :attribute vem do ->label()). */
    public function test_attributes_cobre_as_telas_fora_do_filament(): void
    {
        $traduzido = require lang_path('pt_BR/validation.php');

        foreach (['name', 'email', 'password', 'password_confirmation', 'token',
            'data_nascimento', 'endereco', 'whatsapp', 'whatsapp_publico', 'foto'] as $campo) {
            $this->assertArrayHasKey($campo, $traduzido['attributes'], "atributo sem rótulo pt-BR: {$campo}");
        }
    }

    /** Prova que o arquivo está em uso de verdade — não basta existir no disco. */
    public function test_mensagem_nativa_sai_em_portugues(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertSame('O campo nome é obrigatório.', __('validation.required', ['attribute' => 'nome']));
    }

    /** D9: meio-traduzido é pior que consistentemente em inglês — o /entrar mistura os dois arquivos. */
    public function test_auth_e_passwords_estao_traduzidos(): void
    {
        $this->assertSame('Estas credenciais não conferem com nossos registros.', __('auth.failed'));
        $this->assertSame('A senha informada está incorreta.', __('auth.password'));
        $this->assertSame('Enviamos o link de redefinição de senha para o seu e-mail.', __('passwords.sent'));
        $this->assertSame('Não encontramos nenhum usuário com esse endereço de e-mail.', __('passwords.user'));
    }
}
