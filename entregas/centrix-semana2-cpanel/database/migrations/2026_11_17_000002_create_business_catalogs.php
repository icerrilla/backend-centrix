<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pilares', 'marcas', 'categorias', 'tipos_beneficio', 'embotelladores'] as $nombre) {
            Schema::create($nombre, function (Blueprint $table) use ($nombre) {
                $table->id();
                $table->string('nombre', 180)->unique();
                $table->boolean('activo')->default(true)->index();
                if ($nombre === 'pilares') {
                    $table->text('descripcion')->nullable();
                    $table->string('color', 7)->default('#64748b');
                    $table->unsignedInteger('orden')->default(0)->index();
                }
                if (in_array($nombre, ['pilares', 'marcas'], true)) {
                    $table->string('imagen_path')->nullable();
                }
                if ($nombre === 'embotelladores') {
                    $table->string('color', 7)->default('#64748b');
                    $table->json('estados');
                    $table->boolean('es_nacional')->default(false);
                    $table->string('clave_sistema', 30)->nullable()->unique();
                }
                $table->timestamps();
            });
        }
        Schema::create('usuario_embotellador', function (Blueprint $table) {
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('embotellador_id')->constrained('embotelladores')->restrictOnDelete();
            $table->primary(['usuario_id', 'embotellador_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_embotellador');
        foreach (['embotelladores', 'tipos_beneficio', 'categorias', 'marcas', 'pilares'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
