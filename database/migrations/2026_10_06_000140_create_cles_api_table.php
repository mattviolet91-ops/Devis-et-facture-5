<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clés d'accès pour Claude : seule l'empreinte SHA-256 est gardée.
        Schema::create('cles_api', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->char('sha256', 64)->unique();
            $table->string('debut', 12); // début de la clé, pour la reconnaître
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('derniere_utilisation_at')->nullable();
            $table->timestamp('revoquee_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cles_api');
    }
};
