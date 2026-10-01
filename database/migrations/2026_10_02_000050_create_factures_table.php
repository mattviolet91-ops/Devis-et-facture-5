<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Factures et avoirs. Une facture émise ne se modifie jamais et ne se supprime pas
        // (conservation 10 ans) : on la corrige par un avoir.
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('facture')->index(); // facture, acompte, situation, solde, avoir
            $table->foreignId('client_id')->constrained();
            $table->foreignId('devis_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facture_origine_id')->nullable()->constrained('factures')->nullOnDelete();
            $table->string('numero', 40)->nullable()->unique();
            $table->string('statut', 20)->default('brouillon')->index(); // brouillon, emise, payee, annulee
            $table->string('objet', 200)->nullable();
            $table->unsignedSmallInteger('pourcentage')->nullable(); // centièmes de % (acompte, situation)
            $table->unsignedSmallInteger('numero_situation')->nullable();
            $table->date('date_facture')->nullable();
            $table->date('date_prestation')->nullable();
            $table->date('date_echeance')->nullable();
            $table->unsignedSmallInteger('delai_paiement_jours')->default(30);
            $table->string('remise_type', 20)->nullable();
            $table->integer('remise_valeur')->default(0);
            $table->bigInteger('total_ht')->default(0);
            $table->bigInteger('total_remise')->default(0);
            $table->bigInteger('total_tva')->default(0);
            $table->bigInteger('total_ttc')->default(0);
            $table->bigInteger('total_options_ht')->default(0);
            $table->text('conditions')->nullable();
            $table->text('motif')->nullable(); // motif d'un avoir
            $table->boolean('relances_auto')->default(false);
            $table->unsignedTinyInteger('relances')->default(0);
            $table->timestamp('derniere_relance_at')->nullable();
            $table->timestamp('emise_at')->nullable();
            $table->string('pdf_chemin', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lignes_factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 10)->default('ligne');
            $table->string('designation', 255)->nullable();
            $table->text('description')->nullable();
            $table->bigInteger('quantite')->default(1000);
            $table->string('unite', 20)->nullable();
            $table->bigInteger('prix_unitaire_ht')->default(0);
            $table->unsignedSmallInteger('taux_tva')->default(0);
            $table->boolean('option')->default(false);
            $table->foreignId('prestation_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('total_ht')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_factures');
        Schema::dropIfExists('factures');
    }
};
