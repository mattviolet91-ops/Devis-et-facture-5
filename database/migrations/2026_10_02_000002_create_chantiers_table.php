<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adresses de chantier d'un client (plusieurs possibles).
        Schema::create('chantiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('libelle', 120)->nullable();
            $table->string('adresse', 200)->nullable();
            $table->string('code_postal', 10)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('type_toiture', 40)->nullable();
            // Surface en m² et pente en degrés (mesures, pas des montants).
            $table->decimal('surface', 8, 2)->nullable();
            $table->unsignedTinyInteger('pente')->nullable();
            $table->string('acces', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chantiers');
    }
};
