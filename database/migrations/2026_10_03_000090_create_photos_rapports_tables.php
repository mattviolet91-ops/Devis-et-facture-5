<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Photos de chantier (réduites, sans données de position).
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->string('moment', 20)->default('avant'); // avant, pendant, apres
            $table->string('legende', 200)->nullable();
            $table->string('chemin', 255);
            $table->string('miniature', 255);
            $table->unsignedSmallInteger('largeur');
            $table->unsignedSmallInteger('hauteur');
            $table->unsignedInteger('taille');
            $table->boolean('dans_documents')->default(false); // annexe des devis et factures
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['client_id', 'chantier_id']);
        });

        // Rapports d'intervention envoyés au client.
        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chantier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->date('date_intervention');
            $table->string('titre', 200);
            $table->text('travaux')->nullable();
            $table->text('constats')->nullable();
            $table->text('conseils')->nullable();
            $table->json('photos')->nullable(); // identifiants des photos choisies
            $table->timestamp('envoye_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
        Schema::dropIfExists('photos');
    }
};
