<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rendez-vous et chantiers (sur un ou plusieurs jours).
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('rdv'); // rdv, chantier
            $table->string('titre', 200);
            $table->dateTime('debut')->index();
            $table->dateTime('fin');
            $table->boolean('journee_entiere')->default(false);
            $table->string('lieu', 255)->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('devis_id')->nullable()->constrained('devis')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('fait')->default(false);
            $table->unsignedTinyInteger('rappel_client_jours')->nullable(); // 1 ou 2 : email au client
            $table->json('rappels_envoyes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Abonnements aux notifications sur le téléphone (Web Push).
        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('cle_p256dh', 255);
            $table->string('cle_auth', 255);
            $table->string('appareil', 120)->nullable();
            $table->timestamps();
        });

        // Météo : villes situées (Géoplateforme IGN) et prévisions (MET Norway), en cache.
        Schema::create('lieux_geocodes', function (Blueprint $table) {
            $table->id();
            $table->string('recherche', 191)->unique();
            $table->decimal('latitude', 8, 4)->nullable();
            $table->decimal('longitude', 8, 4)->nullable();
            $table->string('libelle', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('meteo_previsions', function (Blueprint $table) {
            $table->id();
            $table->string('point', 30); // « lat,lon » arrondis
            $table->date('jour');
            $table->string('symbole', 60)->nullable();
            $table->smallInteger('temperature_min')->nullable(); // °C
            $table->smallInteger('temperature_max')->nullable();
            $table->unsignedSmallInteger('pluie_dixiemes_mm')->default(0);
            $table->unsignedSmallInteger('vent_max_kmh')->default(0);
            $table->timestamps();
            $table->unique(['point', 'jour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meteo_previsions');
        Schema::dropIfExists('lieux_geocodes');
        Schema::dropIfExists('abonnements_push');
        Schema::dropIfExists('rendez_vous');
    }
};
