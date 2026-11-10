<?php

namespace Database\Seeders;

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
            $rolId = DB::table('roles')->where('codigo', 'ADMIN')->value('id');
            $catalogo = [
                'USUARIOS' => ['Usuarios', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR', 'INACTIVAR', 'DESBLOQUEAR', 'ELIMINAR', 'REENVIAR_ACCESO']],
                'ROLES' => ['Roles', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR', 'INACTIVAR', 'ELIMINAR', 'ASIGNAR_PERMISOS']],
                'MODULOS' => ['Módulos', ['CONSULTAR']],
                'BITACORA' => ['Bitácora', ['CONSULTAR']],
            ];
            foreach ($catalogo as $codigo => [$nombre, $acciones]) {
                DB::table('modulos')->insertOrIgnore([
                    'codigo' => $codigo, 'nombre' => $nombre, 'activo' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $moduloId = DB::table('modulos')->where('codigo', $codigo)->value('id');
                foreach ($acciones as $accion) {
                    DB::table('permisos')->insertOrIgnore([
                        'modulo_id' => $moduloId, 'accion' => $accion, 'nombre' => $accion.' '.$nombre,
                        'codigo' => $codigo.'.'.$accion, 'descripcion' => 'Permite '.mb_strtolower($accion).' en '.$nombre.'.', 'activo' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $permisoId = DB::table('permisos')->where('modulo_id', $moduloId)->where('accion', $accion)->value('id');
                    DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
                }
            }
        });
    }
}
