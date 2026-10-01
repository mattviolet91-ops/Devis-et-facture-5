<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encaissements : jamais modifiés ni supprimés. Une erreur se corrige par une
        // écriture d'annulation (montant négatif). Chaque ligne est chaînée à la précédente
        // par une empreinte SHA-256 (inaltérabilité).
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures');
            $table->bigInteger('montant');
            $table->string('mode', 20);
            $table->date('date_paiement');
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('annule_paiement_id')->nullable()->constrained('paiements');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('empreinte', 64);
            $table->char('empreinte_precedente', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Paiements en ligne par carte (myPOS Checkout) : une tentative par passage sur la page de paiement.
        Schema::create('paiements_en_ligne', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures');
            $table->string('order_id', 40)->unique();
            $table->bigInteger('montant');
            $table->boolean('essai')->default(false);
            $table->string('statut', 20)->default('en_attente'); // en_attente, payee, annulee
            $table->string('transaction', 100)->nullable();
            $table->foreignId('paiement_id')->nullable()->constrained('paiements');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_en_ligne');
        Schema::dropIfExists('paiements');
    }
};
