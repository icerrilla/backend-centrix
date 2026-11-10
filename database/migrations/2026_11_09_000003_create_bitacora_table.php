<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usuario_nombre')->nullable();
            $table->string('usuario_login')->nullable();
            $table->string('modulo', 50);
            $table->string('accion', 50);
            $table->string('resultado', 50);
            $table->text('descripcion');
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['modulo', 'accion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
