<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReiniciarBitacora extends Command
{
    protected $signature = 'centrix:reiniciar-bitacora {--force : Confirma eliminar todo el historial del entorno configurado}';

    protected $description = 'Elimina exclusivamente el historial de bitácora del entorno actual y registra su reinicio';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('No se borró nada. Usa --force para confirmar el reinicio de bitácora en '.DB::connection()->getDatabaseName().'.');

            return self::FAILURE;
        }
        DB::transaction(function () {
            $total = DB::table('bitacora')->count();
            DB::table('bitacora')->delete();
            DB::table('bitacora')->insert([
                'modulo' => 'SISTEMA', 'accion' => 'REINICIAR', 'resultado' => 'EXITOSO',
                'descripcion' => 'Se reinició la bitácora por solicitud del administrador.', 'created_at' => now(),
                'registro_tipo' => 'BITACORA', 'registro_id' => null,
                'cambios' => json_encode([['campo' => 'registros_anteriores', 'anterior' => $total, 'nuevo' => 0]], JSON_THROW_ON_ERROR),
                'ip' => null,
            ]);
        });
        $this->info('Historial anterior eliminado. La bitácora conserva únicamente su registro de reinicio.');

        return self::SUCCESS;
    }
}
