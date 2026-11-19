<?php

namespace Database\Seeders;

use App\Support\CatalogoPermisos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();
            DB::table('roles')->insertOrIgnore([
                'codigo' => 'ADMIN', 'nombre' => 'Administrador', 'activo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $admin = DB::table('roles')->where('codigo', 'ADMIN')->lockForUpdate()->first();
            foreach (['OPERADOR' => 'Operador', 'CLIENTE' => 'Cliente'] as $codigoRol => $nombreRol) {
                DB::table('roles')->insertOrIgnore(['codigo' => $codigoRol, 'nombre' => $nombreRol, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
            foreach (CatalogoPermisos::modulos() as $codigo => [$nombre, $acciones]) {
                DB::table('modulos')->upsert([[
                    'codigo' => $codigo, 'nombre' => $nombre, 'activo' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]], ['codigo'], ['nombre', 'activo', 'updated_at']);
                $moduloId = DB::table('modulos')->where('codigo', $codigo)->value('id');
                foreach ($acciones as $accion) {
                    DB::table('permisos')->upsert([[
                        'modulo_id' => $moduloId, 'accion' => $accion, 'nombre' => $accion.' '.$nombre,
                        'codigo' => $codigo.'.'.$accion,
                        'descripcion' => 'Permite '.mb_strtolower(str_replace('_', ' ', $accion)).' en '.$nombre.'.',
                        'activo' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]], ['codigo'], ['modulo_id', 'accion', 'nombre', 'descripcion', 'activo', 'updated_at']);
                }
            }
            // Traslada concesiones antiguas de EMPRESAS a EMBOTELLADORES sin elevar otros accesos.
            foreach (['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR'] as $accion) {
                $viejo = DB::table('permisos')->where('codigo', 'CATALOGO_EMPRESAS.'.$accion)->value('id');
                $nuevo = DB::table('permisos')->where('codigo', 'CATALOGO_EMBOTELLADORES.'.$accion)->value('id');
                if ($viejo && $nuevo) {
                    foreach (DB::table('rol_permiso')->where('permiso_id', $viejo)->pluck('rol_id') as $rolId) {
                        DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $nuevo]);
                    }
                    DB::table('rol_permiso')->where('permiso_id', $viejo)->delete();
                }
            }
            // Se conservan filas de códigos retirados, desactivadas para no autorizar.
            DB::table('permisos')->whereNotIn('codigo', CatalogoPermisos::codigos())->update(['activo' => false, 'updated_at' => $now]);
            DB::table('modulos')->whereNotIn('codigo', array_keys(CatalogoPermisos::modulos()))->update(['activo' => false, 'updated_at' => $now]);
            foreach (DB::table('permisos')->whereIn('codigo', CatalogoPermisos::codigos())->pluck('id') as $permisoId) {
                DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $admin->id, 'permiso_id' => $permisoId]);
            }
            // No altera otros roles ni asigna permisos nuevos automáticamente a sus usuarios.
        });
    }
}
