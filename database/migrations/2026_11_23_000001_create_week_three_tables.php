<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_permiso_extra', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('codigo', 100);
            $t->primary(['user_id', 'codigo']);
        });
        Schema::create('configuracion', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->boolean('visibilidad_por_embotellador')->default(false);
        });
        Schema::create('eventos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patrocinio_id')->constrained('patrocinios')->restrictOnDelete();
            $t->foreignId('ambito_id')->constrained('embotelladores')->restrictOnDelete();
            $t->string('nombre');
            foreach (['sede', 'ciudad', 'estado_republica', 'equipo_local', 'equipo_visitante', 'responsable'] as $c) {
                $t->string($c)->nullable();
            }
            $t->dateTime('fecha_hora')->index(); // UTC; no depende de la zona de la conexión.
            $t->unsignedInteger('asistencia_estimada')->nullable();
            $t->string('estado', 20)->default('PROGRAMADO');
            $t->timestamps();
        });
        Schema::create('evento_marca', function (Blueprint $t) {
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $t->primary(['evento_id', 'marca_id']);
        });
        Schema::create('evento_imagenes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $t->string('archivo_path');
            $t->timestamps();
        });
        Schema::create('hitos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patrocinio_id')->constrained('patrocinios')->restrictOnDelete();
            $t->foreignId('evento_id')->nullable()->constrained('eventos')->restrictOnDelete();
            $t->string('nombre');
            $t->date('fecha')->index();
            $t->string('tipo', 20);
            $t->text('detalles')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
        Schema::create('beneficios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patrocinio_id')->constrained('patrocinios')->restrictOnDelete();
            $t->foreignId('tipo_beneficio_id')->constrained('tipos_beneficio')->restrictOnDelete();
            $t->string('nombre');
            $t->unsignedInteger('disponibles');
            $t->date('vigencia_inicio');
            $t->date('vigencia_fin');
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
        Schema::create('beneficios_aplicados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('beneficio_id')->constrained('beneficios')->restrictOnDelete();
            $t->foreignId('evento_id')->nullable()->constrained('eventos')->restrictOnDelete();
            $t->string('nombre');
            $t->date('fecha');
            $t->unsignedInteger('cantidad');
            $t->string('imagen_path')->nullable();
            $t->string('documento_path')->nullable();
            $t->string('documento_nombre')->nullable();
            $t->timestamps();
        });
        Schema::create('documentos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('patrocinio_id')->constrained('patrocinios')->restrictOnDelete();
            $t->foreignId('evento_id')->nullable()->constrained('eventos')->restrictOnDelete();
            $t->foreignId('autor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nombre');
            $t->text('descripcion')->nullable();
            $t->string('categoria', 30);
            $t->string('estado', 20)->default('ACTIVO');
            $t->string('archivo_path');
            $t->string('nombre_archivo');
            $t->string('tipo_mime', 100);
            $t->unsignedBigInteger('tamano');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['documentos', 'beneficios_aplicados', 'beneficios', 'hitos', 'evento_imagenes', 'evento_marca', 'eventos', 'configuracion', 'usuario_permiso_extra'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
