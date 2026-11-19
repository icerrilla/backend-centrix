<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrocinios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('pilar_id')->constrained('pilares')->restrictOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->restrictOnDelete();
            $table->foreignId('ambito_id')->constrained('embotelladores')->restrictOnDelete();
            foreach (['contraparte', 'alcance', 'ciudad_territorio', 'responsable'] as $campo) {
                $table->string($campo)->nullable();
            }
            $table->date('vigencia_inicio')->nullable();
            $table->date('vigencia_fin')->nullable();
            $table->decimal('inversion', 18, 2)->nullable();
            $table->decimal('audiencia_promedio', 18, 2)->nullable();
            $table->string('estado', 20)->default('BORRADOR')->index();
            $table->string('imagen_path')->nullable();
            $table->timestamps();
            $table->index(['ambito_id', 'estado']);
        });
        Schema::create('patrocinio_marca', function (Blueprint $table) {
            $table->foreignId('patrocinio_id')->constrained('patrocinios')->cascadeOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $table->primary(['patrocinio_id', 'marca_id']);
        });
        Schema::create('patrocinio_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patrocinio_id')->constrained('patrocinios')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('rol')->nullable();
            $table->unsignedInteger('orden')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrocinio_contactos');
        Schema::dropIfExists('patrocinio_marca');
        Schema::dropIfExists('patrocinios');
    }
};
