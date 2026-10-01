<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->boolean('relances_auto')->default(true);
            $table->unsignedTinyInteger('relances')->default(0);
            $table->timestamp('derniere_relance_at')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->timestamp('avis_demande_at')->nullable();
            $table->timestamp('entretien_propose_at')->nullable();
        });

        // Demandes de devis (formulaire public, emails du site WordPress).
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20); // formulaire, email
            $table->string('statut', 20)->default('nouvelle')->index(); // nouvelle, traitee, ecartee
            $table->string('nom', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('ville', 120)->nullable();
            $table->text('message')->nullable();
            $table->string('message_id', 191)->nullable()->unique(); // email d'origine (pas de doublon)
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recue_at');
            $table->timestamps();
        });

        // Derniers emails reçus (aperçu, sans pièces jointes).
        Schema::create('emails_recus', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 191)->unique();
            $table->string('expediteur', 150)->nullable();
            $table->string('expediteur_email', 150)->nullable();
            $table->string('sujet', 255)->nullable();
            $table->text('extrait')->nullable();
            $table->boolean('est_demande')->default(false);
            $table->timestamp('recu_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails_recus');
        Schema::dropIfExists('demandes');
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['avis_demande_at', 'entretien_propose_at']));
        Schema::table('devis', fn (Blueprint $table) => $table->dropColumn(['relances_auto', 'relances', 'derniere_relance_at']));
    }
};
