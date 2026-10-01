<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // « original » entrait en conflit avec une propriété interne d'Eloquent.
    public function up(): void
    {
        Schema::table('photos', fn (Blueprint $table) => $table->renameColumn('original', 'chemin_original'));
    }

    public function down(): void
    {
        Schema::table('photos', fn (Blueprint $table) => $table->renameColumn('chemin_original', 'original'));
    }
};
