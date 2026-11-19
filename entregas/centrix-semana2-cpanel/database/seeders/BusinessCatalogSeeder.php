<?php

namespace Database\Seeders;

use App\Models\Embotellador;
use App\Support\EstadosMexico;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();
            foreach (['Físico', 'Media', 'Digital', 'Otro'] as $nombre) {
                DB::table('tipos_beneficio')->insertOrIgnore(['nombre' => $nombre, 'activo' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
            $nacional = Embotellador::firstOrNew(['clave_sistema' => 'NACIONAL']);
            $nacional->forceFill(['nombre' => 'Nacional', 'activo' => true, 'es_nacional' => true, 'estados' => EstadosMexico::CLAVES]);
            if (! $nacional->exists) {
                $nacional->color = '#64748b';
            }
            $nacional->save();
        });
    }
}
