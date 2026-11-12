<?php

namespace App\Support;

class CatalogoPermisos
{
    public static function modulos(): array
    {
        return [
            'USUARIOS' => ['Usuarios', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR', 'DESBLOQUEAR', 'ELIMINAR', 'REENVIAR_ACCESO']],
            'ROLES' => ['Roles', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR', 'ASIGNAR_PERMISOS', 'ELIMINAR']],
            'PERMISOS' => ['Permisos', ['CONSULTAR']],
            'BITACORA' => ['Bitácora', ['CONSULTAR']],
            'CATALOGO_EMPRESAS' => ['Empresas', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR']],
            'CATALOGO_MARCAS' => ['Marcas', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR']],
            'CATALOGO_PILARES' => ['Pilares', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR']],
            'CATALOGO_CATEGORIAS' => ['Categorías', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR']],
            'CATALOGO_TIPOS_BENEFICIO' => ['Tipos de beneficio', ['CONSULTAR', 'CREAR', 'EDITAR', 'ACTIVAR']],
        ];
    }

    public static function codigos(): array
    {
        $codigos = [];
        foreach (self::modulos() as $modulo => [$nombre, $acciones]) {
            foreach ($acciones as $accion) {
                $codigos[] = $modulo.'.'.$accion;
            }
        }

        return $codigos;
    }

    public static function agrupar(array $concedidos): array
    {
        $concedidos = array_fill_keys($concedidos, true);
        $grupos = [];
        foreach (self::modulos() as $modulo => [$nombre, $acciones]) {
            $permisos = [];
            foreach ($acciones as $accion) {
                $permisos[strtolower($accion)] = isset($concedidos[$modulo.'.'.$accion]);
            }
            $grupos[] = ['modulo' => $modulo, 'permisos' => $permisos];
        }

        return $grupos;
    }

    public static function descripcion(string $codigo): string
    {
        [$modulo, $accion] = explode('.', $codigo, 2);
        $nombre = self::modulos()[$modulo][0];

        return mb_strtolower(str_replace('_', ' ', $accion).' en '.$nombre);
    }
}
