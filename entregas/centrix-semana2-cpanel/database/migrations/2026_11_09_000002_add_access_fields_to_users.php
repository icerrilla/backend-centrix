<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            // Se completa explícitamente para usuarios anteriores, sin inventar identificadores.
            $table->string('usuario', 100)->nullable()->unique();
            $table->boolean('requiere_establecer_password')->default(false);
            // Nullable para conservar usuarios existentes sin otorgarles acceso ADMIN.
            $table->foreignId('rol_id')->nullable()->constrained('roles')->restrictOnDelete();
            $table->boolean('activo')->default(true);
            $table->boolean('bloqueado')->default(false);
            $table->unsignedTinyInteger('intentos_fallidos')->default(0);
            $table->timestamp('bloqueado_at')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rol_id');
            $table->dropUnique(['usuario']);
            $table->dropColumn(['apellido_paterno', 'apellido_materno', 'usuario', 'requiere_establecer_password', 'activo', 'bloqueado', 'intentos_fallidos', 'bloqueado_at', 'ultimo_acceso_at']);
        });
    }
};
