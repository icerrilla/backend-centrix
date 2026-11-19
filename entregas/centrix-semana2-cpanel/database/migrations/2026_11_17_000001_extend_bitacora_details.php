<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->string('registro_tipo', 60)->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->json('cambios')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->index(['registro_tipo', 'registro_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bitacora', function (Blueprint $table) {
            $table->dropIndex(['registro_tipo', 'registro_id']);
            $table->dropColumn(['registro_tipo', 'registro_id', 'cambios', 'ip']);
        });
    }
};
