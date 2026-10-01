<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Compteur du site internet : aucune adresse IP, aucun cookie.
        Schema::create('visites_site', function (Blueprint $table) {
            $table->id();
            $table->date('jour')->index();
            $table->char('empreinte', 16); // anonyme, change chaque jour
            $table->string('evenement', 20); // vue, appeler, email, whatsapp, devis, formulaire
            $table->string('page', 255)->nullable();
            $table->string('source', 60)->nullable();
            $table->string('appareil', 20)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visites_site');
    }
};
