<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un compteur par type de document et par année : numérotation continue et sans trou.
        Schema::create('compteurs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('dernier');
            $table->timestamps();
            $table->unique(['type', 'annee']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteurs');
    }
};
