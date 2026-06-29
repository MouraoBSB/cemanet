<?php

// Thiago Mourão — https://github.com/MouraoBSB — 2026-06-29

use App\Models\Palestrante;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Copia (preservingOriginal) as fotos da coluna 'foto' para a Media Library, para
     * palestrantes que ainda não têm mídia 'foto'. NÃO dropa a coluna nem remove o arquivo
     * original — o front ainda lê da coluna até o próximo passo. Idempotente.
     */
    public function up(): void
    {
        Palestrante::query()->whereNotNull('foto')->get()->each(function (Palestrante $p): void {
            if ($p->getFirstMedia(Palestrante::COLECAO_FOTO)) {
                return; // já migrado
            }
            if (! Storage::disk('public')->exists($p->foto)) {
                return; // arquivo não está em disco
            }
            $p->addMedia(Storage::disk('public')->path($p->foto))
                ->preservingOriginal()
                ->toMediaCollection(Palestrante::COLECAO_FOTO);
        });
    }

    public function down(): void
    {
        // Sem reversão: a coluna 'foto' permanece intacta nesta etapa.
    }
};
