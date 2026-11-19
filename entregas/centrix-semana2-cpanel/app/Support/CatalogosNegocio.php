<?php

namespace App\Support;

class CatalogosNegocio
{
    public static function todos(): array
    {
        return [
            'pilares' => ['tabla' => 'pilares', 'modulo' => 'CATALOGO_PILARES', 'singular' => 'pilar'],
            'marcas' => ['tabla' => 'marcas', 'modulo' => 'CATALOGO_MARCAS', 'singular' => 'marca'],
            'categorias' => ['tabla' => 'categorias', 'modulo' => 'CATALOGO_CATEGORIAS', 'singular' => 'categoría'],
            'tipos-beneficio' => ['tabla' => 'tipos_beneficio', 'modulo' => 'CATALOGO_TIPOS_BENEFICIO', 'singular' => 'tipo de beneficio'],
            'embotelladores' => ['tabla' => 'embotelladores', 'modulo' => 'CATALOGO_EMBOTELLADORES', 'singular' => 'embotellador'],
        ];
    }
}
