<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-07-23

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove `contexto` (F4c-D): o texto editorial da mensagem passou a ser o `resumo`, renderizado
 * como lead. A migration anterior já fundiu o dado. Par no molde de
 * 2026_06_29_000001 + 2026_06_29_000002 (migra o dado, depois dropa a coluna).
 *
 * São DUAS migrations porque migration NÃO roda em transação em MySQL nem em SQLite
 * (Migrator.php:448-451 + Schema/Grammars/Grammar.php:31, que só Postgres e SQL Server
 * sobrescrevem): separadas, o passo concluído fica registrado em `migrations` e um novo
 * `migrate` retoma do ponto certo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            $tabela->dropColumn('contexto');
        });
    }

    /**
     * DESTRUTIVO — recria a coluna VAZIA. O texto fundido fica só no `resumo`, e o `up()` da
     * migration anterior já descartou de propósito o `contexto` das linhas que tinham resumo:
     * não há de onde voltar. Rollback aqui é de SCHEMA, nunca de dado.
     */
    public function down(): void
    {
        Schema::table('mensagens', function (Blueprint $tabela) {
            // ->after('corpo') devolve a posição original no MySQL; no SQLite é ignorado em silêncio.
            $tabela->text('contexto')->nullable()->after('corpo');
        });
    }
};
