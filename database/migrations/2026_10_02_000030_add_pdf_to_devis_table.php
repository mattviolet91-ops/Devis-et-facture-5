<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PDF figé à l'envoi, avec son empreinte SHA-256 (preuve qu'il n'a pas été modifié).
        Schema::table('devis', function (Blueprint $table) {
            $table->string('pdf_chemin', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('pdf_fige_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn(['pdf_chemin', 'pdf_sha256', 'pdf_fige_at']);
        });
    }
};
