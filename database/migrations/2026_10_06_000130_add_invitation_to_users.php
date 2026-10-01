<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Invitation : seule l'empreinte SHA-256 du lien est gardée.
            $table->char('invitation_sha256', 64)->nullable()->unique();
            $table->timestamp('invitation_expire_at')->nullable();
            $table->timestamp('desactive_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['invitation_sha256', 'invitation_expire_at', 'desactive_at']));
    }
};
