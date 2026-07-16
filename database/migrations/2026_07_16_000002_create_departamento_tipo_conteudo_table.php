<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamento_tipo_conteudo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_conteudo_id')->constrained('tipos_conteudo')->cascadeOnDelete();
            // restrictOnDelete diverge do molde dos 6 pivôs DE PROPÓSITO: esta é tabela de
            // autorização. Cascade faria do DELETE de um departamento um segundo escritor da
            // config — sem passar pela tela e sem trilha de auditoria (fura I7/I8 do spec).
            $table->foreignId('departamento_id')->constrained('departamentos')->restrictOnDelete();

            // Nome do índice EXPLÍCITO (1º do projeto): o automático do Laravel seria
            // 'departamento_tipo_conteudo_tipo_conteudo_id_departamento_id_unique' = 66 caracteres,
            // e o MySQL limita identificadores a 64 (erro 1059). Estoura porque 'tipo_conteudo'
            // entra duas vezes (tabela + coluna) — os 6 pivôs existentes escapam por pouco
            // (departamento_agenda_dia: 60, departamento_palestrante: 62). O SQLite dos testes não
            // tem esse limite, então a suíte NÃO pega: só o migrate no MySQL pega.
            $table->unique(['tipo_conteudo_id', 'departamento_id'], 'departamento_tipo_conteudo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamento_tipo_conteudo');
    }
};
