<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('particulier');
            $table->string('civilite', 20)->nullable();
            $table->string('nom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->string('raison_sociale', 150)->nullable();
            $table->string('siret', 14)->nullable();
            $table->string('tva_intracom', 20)->nullable();
            $table->string('telephone', 20)->nullable()->index();
            $table->string('telephone2', 20)->nullable();
            $table->string('email', 150)->nullable()->index();
            $table->string('adresse', 200)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('provenance', 60)->nullable()->index();
            $table->string('provenance_detail', 150)->nullable();
            // Texte sans accents ni majuscules, pour la recherche.
            $table->text('recherche')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
