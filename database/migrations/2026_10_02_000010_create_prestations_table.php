<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogue de prestations. Prix en centimes HT, TVA en centièmes de % (null = taux par défaut).
        Schema::create('prestations', function (Blueprint $table) {
            $table->id();
            $table->string('categorie', 80)->nullable()->index();
            $table->string('nom', 200);
            $table->text('description')->nullable();
            $table->string('unite', 20)->default('u');
            $table->integer('prix_ht')->nullable();
            $table->unsignedSmallInteger('taux_tva')->nullable();
            $table->text('recherche')->nullable();
            $table->unsignedInteger('utilisations')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestations');
    }
};
