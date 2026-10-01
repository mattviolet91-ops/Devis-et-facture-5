<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photo d'origine gardée quand on dessine dessus (annotation).
        Schema::table('photos', function (Blueprint $table) {
            $table->string('original', 255)->nullable();
        });

        // Demandes : adresse et photos envoyées avec le formulaire.
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('adresse', 200)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->json('photos')->nullable();
        });

        // Préférences de chaque compte (blocs de l'accueil, boutons de la barre du bas).
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable();
        });

        // Frais d'un chantier, rattachés à une facture (gérant seulement).
        Schema::create('frais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->cascadeOnDelete();
            $table->date('date_frais');
            $table->string('libelle', 150);
            $table->string('categorie', 30)->default('materiaux');
            $table->unsignedInteger('montant_ttc'); // centimes
            $table->string('justificatif', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frais');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferences'));
        Schema::table('demandes', fn (Blueprint $table) => $table->dropColumn(['adresse', 'code_postal', 'photos']));
        Schema::table('photos', fn (Blueprint $table) => $table->dropColumn('original'));
    }
};
