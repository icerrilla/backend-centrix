<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use App\Services\EnlacesPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CrearAdmin extends Command
{
    protected $signature = 'centrix:crear-admin {--nombre=} {--usuario=} {--email=}';

    protected $description = 'Crea el primer administrador y envía su enlace para establecer contraseña';

    public function handle(EnlacesPassword $enlaces): int
    {
        if (User::whereHas('rol', fn ($query) => $query->where('codigo', 'ADMIN'))->exists()) {
            $this->error('Ya existe un administrador. Utiliza la administración de usuarios.');

            return self::FAILURE;
        }
        $datos = [
            'name' => $this->option('nombre') ?: $this->ask('Nombre'),
            'usuario' => mb_strtolower(trim($this->option('usuario') ?: $this->ask('Usuario'))),
            'email' => mb_strtolower(trim($this->option('email') ?: $this->ask('Correo'))),
        ];
        $validacion = Validator::make($datos, [
            'name' => ['required', 'string', 'max:255'],
            'usuario' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_.-]+$/', 'unique:users,usuario'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        if ($validacion->fails()) {
            $this->error('Revisa nombre, usuario y correo. Los identificadores deben ser únicos.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($datos, $enlaces) {
            $rol = Rol::where('codigo', 'ADMIN')->where('activo', true)->lockForUpdate()->firstOrFail();
            abort_if(User::where('rol_id', $rol->id)->exists(), 409, 'Ya existe un administrador. Utiliza la administración de usuarios.');
            $usuario = new User;
            $usuario->forceFill($datos + ['rol_id' => $rol->id, 'password' => Str::random(64), 'requiere_establecer_password' => true])->save();
            $enlaces->enviar($usuario);
            DB::table('bitacora')->insert([
                'usuario_id' => null, 'usuario_nombre' => null, 'usuario_login' => null,
                'modulo' => 'USUARIOS', 'accion' => 'CREAR', 'resultado' => 'EXITOSO',
                'descripcion' => 'Se creó el administrador inicial '.$usuario->name.' desde la configuración del sistema.', 'created_at' => now(),
            ]);
        });
        $this->info('Administrador creado. Se envió el enlace para establecer su contraseña.');

        return self::SUCCESS;
    }
}
