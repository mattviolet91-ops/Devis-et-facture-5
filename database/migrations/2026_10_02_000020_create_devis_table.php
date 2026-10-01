<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            // Numéro attribué à l'envoi seulement (DEV-AAAA-0001, puis -V2 pour une nouvelle version).
            $table->string('numero', 40)->nullable()->unique();
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('devis_origine_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->foreignId('remplace_par_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->string('statut', 20)->default('brouillon')->index();
            $table->string('objet', 200)->nullable();
            $table->date('date_devis')->nullable();
            $table->unsignedSmallInteger('validite_jours')->default(30);
            $table->unsignedTinyInteger('acompte_pourcentage')->default(0);
            $table->date('date_debut_travaux')->nullable();
            $table->string('duree_travaux', 100)->nullable();
            $table->text('dechets_estimation')->nullable();
            // Remise globale : en centièmes de % (pourcentage) ou en centimes (montant).
            $table->string('remise_type', 20)->nullable();
            $table->integer('remise_valeur')->default(0);
            // Totaux en centimes (recalculés à chaque enregistrement).
            $table->bigInteger('total_ht')->default(0);
            $table->bigInteger('total_remise')->default(0);
            $table->bigInteger('total_tva')->default(0);
            $table->bigInteger('total_ttc')->default(0);
            $table->bigInteger('total_options_ht')->default(0);
            $table->text('conditions')->nullable();
            $table->boolean('hors_etablissement')->default(false);
            $table->boolean('urgence')->default(false);
            $table->timestamp('envoye_at')->nullable();
            $table->timestamp('accepte_at')->nullable();
            $table->timestamp('refuse_at')->nullable();
            $table->text('motif_refus')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lignes_devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 10)->default('ligne');
            $table->string('designation', 255)->nullable();
            $table->text('description')->nullable();
            // Quantité en millièmes (1,5 m² = 1500) : jamais de nombre à virgule.
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
        Schema::dropIfExists('lignes_devis');
        Schema::dropIfExists('devis');
    }
};
