<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historique des emails envoyés aux clients (devis, factures, relances…).
        Schema::create('emails_envoyes', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('document');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destinataire', 150);
            $table->string('sujet', 200);
            $table->text('corps');
            $table->string('piece_jointe', 200)->nullable();
            $table->string('modele', 30)->nullable();
            $table->boolean('automatique')->default(false);
            $table->string('statut', 20)->default('envoye');
            $table->string('erreur', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails_envoyes');
    }
};
