<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Liens envoyés aux clients : jeton aléatoire, retrouvé par son empreinte SHA-256.
        Schema::create('liens_clients', function (Blueprint $table) {
            $table->id();
            $table->morphs('document');
            $table->char('jeton_sha256', 64)->unique();
            $table->text('jeton_chiffre');
            $table->timestamp('dernier_acces_at')->nullable();
            $table->unsignedInteger('acces')->default(0);
            $table->timestamp('revoque_at')->nullable();
            $table->timestamps();
        });

        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->cascadeOnDelete();
            $table->string('nom', 150);
            $table->string('image_chemin', 255);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('sur_place')->default(false);
            $table->boolean('execution_immediate')->default(false);
            $table->string('pdf_chemin', 255)->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->timestamp('signe_at');
            $table->timestamps();
        });

        Schema::create('demandes_modification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained('devis')->cascadeOnDelete();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('traitee_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_modification');
        Schema::dropIfExists('signatures');
        Schema::dropIfExists('liens_clients');
    }
};
