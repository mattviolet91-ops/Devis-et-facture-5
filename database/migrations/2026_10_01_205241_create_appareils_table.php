<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appareils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Empreinte SHA-256 du jeton d'appareil (le jeton lui-même n'est jamais stocké).
            $table->string('empreinte', 64);
            $table->string('libelle', 120);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'empreinte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appareils');
    }
};
