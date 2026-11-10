<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use App\Notifications\EstablecerPassword;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentrixApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['centrix.https_obligatorio' => false, 'centrix.password_url' => 'https://centrix.test/establecer-password']);
        Artisan::call('migrate', ['--force' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    private function usuario(array $datos = []): User
    {
        return User::factory()->create($datos + ['usuario' => fake()->unique()->userName(), 'rol_id' => Rol::where('codigo', 'ADMIN')->value('id')]);
    }

    private function cabeceras(User $usuario): array
    {
        return ['Authorization' => 'Bearer '.$usuario->createToken('prueba')->plainTextToken];
    }

    public function test_login_contract_case_insensitivity_single_session_and_logout(): void
    {
        $usuario = $this->usuario(['usuario' => 'jperez', 'email' => 'juan@example.com', 'intentos_fallidos' => 2]);
        $anterior = $usuario->createToken('anterior')->plainTextToken;
        $respuesta = $this->postJson('/api/login', ['login' => 'JPEREZ', 'password' => 'password'])->assertOk()
            ->assertJsonPath('statusCode', 200)->assertJsonPath('errorMessageDTO', null)
            ->assertJsonPath('dataResponse.expires_in', 600)->assertJsonPath('dataResponse.usuario.usuario', 'jperez')
            ->assertJsonPath('dataResponse.usuario.rol.codigo', 'ADMIN');
        $token = $respuesta->json('dataResponse.token');
        $this->assertSame(0, $usuario->fresh()->intentos_fallidos);
        $this->assertSame(1, $usuario->tokens()->count());
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$anterior])->assertUnauthorized();
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertOk();
        $nuevo = $this->postJson('/api/login', ['login' => 'JUAN@EXAMPLE.COM', 'password' => 'password'])->assertOk()->json('dataResponse.token');
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson('/api/logout', [], ['Authorization' => 'Bearer '.$nuevo])->assertOk()->assertJsonPath('dataResponse', null);
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$nuevo])->assertUnauthorized();
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'LOGOUT')->count());
        $this->assertStringNotContainsString($token, json_encode(DB::table('bitacora')->get()));
    }

    public function test_third_failure_commits_lock_and_revokes_access_without_leaking_secrets(): void
    {
        $usuario = $this->usuario(['usuario' => 'bloqueable']);
        $token = $usuario->createToken('previo')->plainTextToken;
        for ($intento = 1; $intento <= 3; $intento++) {
            $this->postJson('/api/login', ['login' => 'BLOQUEABLE', 'password' => 'secreto-equivocado'])
                ->assertStatus($intento === 3 ? 423 : 401);
            $this->assertSame($intento, $usuario->fresh()->intentos_fallidos);
        }
        $this->assertTrue($usuario->fresh()->bloqueado);
        $this->assertNotNull($usuario->fresh()->bloqueado_at);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson('/api/login', ['login' => 'bloqueable', 'password' => 'password'])->assertStatus(423);
        $registro = DB::table('bitacora')->where('resultado', 'BLOQUEADO')->where('descripcion', 'Se bloqueó la cuenta por 3 intentos fallidos.')->first();
        $this->assertNotNull($registro);
        $this->assertSame('BLOQUEABLE', $registro->usuario_login);
        $this->assertStringNotContainsString('secreto-equivocado', json_encode(DB::table('bitacora')->get()));
        $desconocida = $this->postJson('/api/login', ['login' => 'no-existe', 'password' => 'password'])->assertUnauthorized()->json();
        $incorrecta = $this->postJson('/api/login', ['login' => 'bloqueable', 'password' => 'incorrecta'])->assertUnauthorized()->json();
        $this->assertSame($desconocida, $incorrecta);
    }

    public function test_inactivity_is_sliding_and_rejects_at_exactly_ten_minutes(): void
    {
        $this->freezeTime();
        $usuario = $this->usuario();
        $headers = $this->cabeceras($usuario);
        $this->travel(9)->minutes();
        $this->getJson('/api/me', $headers)->assertOk();
        $this->travel(9)->minutes();
        $this->getJson('/api/me', $headers)->assertOk();
        $this->travel(10)->minutes();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->assertSame(0, $usuario->tokens()->count());
        $this->travelBack();
    }

    public function test_permissions_and_active_role_module_and_permission_are_enforced(): void
    {
        $usuario = $this->usuario();
        $headers = $this->cabeceras($usuario);
        $this->getJson('/api/usuarios', $headers)->assertOk();
        DB::table('permisos')->where('codigo', 'USUARIOS.CONSULTAR')->update(['activo' => false]);
        $this->getJson('/api/usuarios', $headers)->assertForbidden();
        DB::table('permisos')->where('codigo', 'USUARIOS.CONSULTAR')->update(['activo' => true]);
        DB::table('modulos')->where('codigo', 'USUARIOS')->update(['activo' => false]);
        $this->getJson('/api/usuarios', $headers)->assertForbidden();
        $usuario->rol->forceFill(['activo' => false])->save();
        $this->getJson('/api/me', $headers)->assertForbidden();
        $this->assertSame(0, $usuario->tokens()->count());
        $sinRol = $this->usuario(['rol_id' => null]);
        $this->postJson('/api/login', ['login' => $sinRol->usuario, 'password' => 'password'])->assertForbidden();
    }

    public function test_failed_requests_do_not_extend_inactivity(): void
    {
        $this->freezeTime();
        $usuario = $this->usuario();
        $headers = $this->cabeceras($usuario);
        DB::table('permisos')->where('codigo', 'USUARIOS.CONSULTAR')->update(['activo' => false]);
        $this->travel(9)->minutes();
        $this->getJson('/api/usuarios', $headers)->assertForbidden();
        $this->travel(1)->minutes();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->travelBack();
    }

    public function test_create_user_sends_one_time_link_and_does_not_accept_password(): void
    {
        Notification::fake();
        $admin = $this->usuario();
        $headers = $this->cabeceras($admin);
        $datos = ['nombre' => 'Juan', 'apellido_paterno' => 'Pérez', 'usuario' => 'JPEREZ', 'email' => 'JUAN@EXAMPLE.COM', 'rol_id' => $admin->rol_id];
        $this->postJson('/api/usuarios', $datos + ['password' => 'prohibida'], $headers)->assertUnprocessable();
        $creado = $this->postJson('/api/usuarios', $datos, $headers)->assertCreated()->assertJsonPath('dataResponse.usuario', 'jperez');
        $usuario = User::findOrFail($creado->json('dataResponse.id'));
        $this->assertTrue($usuario->requiere_establecer_password);
        $token = null;
        Notification::assertSentTo($usuario, EstablecerPassword::class, function ($notificacion) use (&$token) {
            $token = $notificacion->token;

            return true;
        });
        $this->assertNotEmpty($token);
        $this->assertNotSame($token, DB::table('password_reset_tokens')->where('email', $usuario->email)->value('token'));
        $password = 'Nueva-Password123!';
        $reset = ['email' => 'JUAN@EXAMPLE.COM', 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
        $this->postJson('/api/reset-password', $reset)->assertOk();
        $this->assertFalse($usuario->fresh()->requiere_establecer_password);
        $this->postJson('/api/reset-password', $reset)->assertUnprocessable();
        $this->postJson('/api/login', ['login' => 'JPEREZ', 'password' => $password])->assertOk();
        $this->assertStringNotContainsString($password, json_encode(DB::table('bitacora')->get()));
        $this->assertStringNotContainsString($token, json_encode(DB::table('bitacora')->get()));
        $this->assertStringNotContainsString('password', json_encode($creado->json()));
    }

    public function test_update_filters_and_unique_identifiers_and_role_validation(): void
    {
        $admin = $this->usuario();
        $usuario = $this->usuario(['email' => 'antes@example.com', 'usuario' => 'antes']);
        $headers = $this->cabeceras($admin);
        $usuario->createToken('viejo');
        Password::createToken($usuario);
        $datos = ['nombre' => 'Nuevo', 'usuario' => 'NuevoUsuario', 'email' => 'nuevo@example.com', 'rol_id' => $admin->rol_id];
        $this->putJson('/api/usuarios/'.$usuario->id, $datos, $headers)->assertOk();
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'antes@example.com')->count());
        $this->assertDatabaseHas('users', ['id' => $usuario->id, 'usuario' => 'nuevousuario']);
        $this->assertStringContainsString('antes@example.com', DB::table('bitacora')->where('accion', 'EDITAR')->value('descripcion'));
        $this->getJson('/api/usuarios?buscar=nuevousuario&activo=1&per_page=1', $headers)->assertOk()->assertJsonPath('dataResponse.paginacion.total', 1);
        $this->getJson('/api/usuarios/'.$usuario->id, $headers)->assertOk()->assertJsonPath('dataResponse.nombre', 'Nuevo');
        $this->putJson('/api/usuarios/'.$usuario->id, array_replace($datos, ['email' => mb_strtoupper($admin->email)]), $headers)->assertUnprocessable();
        $inactivo = Rol::forceCreate(['codigo' => 'INACTIVO', 'nombre' => 'Inactivo', 'activo' => false]);
        $this->putJson('/api/usuarios/'.$usuario->id, array_replace($datos, ['rol_id' => $inactivo->id]), $headers)->assertUnprocessable();
    }

    public function test_deactivate_revoke_unlock_keeps_inactive_and_activate_keeps_blocked(): void
    {
        $admin = $this->usuario();
        $usuario = $this->usuario();
        $headers = $this->cabeceras($admin);
        $token = $usuario->createToken('viejo')->plainTextToken;
        $this->patchJson('/api/usuarios/'.$usuario->id.'/inactivar', [], $headers)->assertOk();
        $this->getJson('/api/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson('/api/login', ['login' => $usuario->usuario, 'password' => 'password'])->assertForbidden();
        $usuario->forceFill(['bloqueado' => true, 'intentos_fallidos' => 3, 'bloqueado_at' => now()])->save();
        $this->patchJson('/api/usuarios/'.$usuario->id.'/desbloquear', [], $headers)->assertOk();
        $this->assertFalse($usuario->fresh()->activo);
        $this->assertFalse($usuario->fresh()->bloqueado);
        $this->assertSame(0, $usuario->fresh()->intentos_fallidos);
        $usuario->refresh()->forceFill(['bloqueado' => true])->save();
        $this->patchJson('/api/usuarios/'.$usuario->id.'/activar', [], $headers)->assertOk();
        $this->assertTrue($usuario->fresh()->bloqueado);
    }

    public function test_last_admin_cannot_be_removed_deactivated_or_demoted(): void
    {
        $admin = $this->usuario();
        $headers = $this->cabeceras($admin);
        $this->patchJson('/api/usuarios/'.$admin->id.'/inactivar', [], $headers)->assertStatus(409);
        $this->deleteJson('/api/usuarios/'.$admin->id, ['confirmar' => true], $headers)->assertStatus(409);
        $rol = Rol::forceCreate(['codigo' => 'CONSULTA', 'nombre' => 'Consulta', 'activo' => true]);
        $this->putJson('/api/usuarios/'.$admin->id, [
            'nombre' => 'Admin', 'usuario' => $admin->usuario, 'email' => $admin->email, 'rol_id' => $rol->id,
        ], $headers)->assertStatus(409);
        $this->assertTrue($admin->fresh()->activo);
        $this->assertSame('ADMIN', $admin->fresh()->rol->codigo);
    }

    public function test_physical_deletion_requires_confirmation_and_preserves_history(): void
    {
        $admin = $this->usuario();
        $usuario = $this->usuario(['name' => 'Juan Histórico']);
        $headers = $this->cabeceras($admin);
        $this->getJson('/api/me', $this->cabeceras($usuario))->assertOk();
        Password::createToken($usuario);
        DB::table('sessions')->insert(['id' => 'sesion-juan', 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);
        $this->deleteJson('/api/usuarios/'.$usuario->id, [], $headers)->assertUnprocessable();
        $this->deleteJson('/api/usuarios/'.$usuario->id, ['confirmar' => true], $headers)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $usuario->id]);
        $this->assertSame(0, $usuario->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $usuario->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $usuario->email]);
        $historico = DB::table('bitacora')->where('usuario_nombre', 'Juan Histórico')->first();
        $this->assertNotNull($historico);
        $this->assertNull($historico->usuario_id);
        $this->assertStringContainsString('Juan Histórico', DB::table('bitacora')->where('accion', 'ELIMINAR')->where('resultado', 'EXITOSO')->value('descripcion'));
        $this->getJson('/api/usuarios/'.$usuario->id, $headers)->assertNotFound();
    }

    public function test_recovery_is_generic_resend_is_authorized_and_log_mail_is_rejected(): void
    {
        Notification::fake();
        $admin = $this->usuario();
        $usuario = $this->usuario();
        $headers = $this->cabeceras($admin);
        $desconocido = $this->postJson('/api/forgot-password', ['email' => 'noexiste@example.com'])->assertOk()->json();
        $conocido = $this->postJson('/api/forgot-password', ['email' => $usuario->email])->assertOk()->json();
        $this->assertSame($conocido, $desconocido);
        Notification::assertSentTo($usuario, EstablecerPassword::class);
        $this->postJson('/api/usuarios/'.$usuario->id.'/reenviar-acceso', [], $headers)->assertStatus(429);
        $this->travel(61)->seconds();
        $this->postJson('/api/usuarios/'.$usuario->id.'/reenviar-acceso', [], $headers)->assertOk();
        config(['mail.default' => 'log']);
        $this->postJson('/api/usuarios', [
            'nombre' => 'No guardar', 'usuario' => 'noguardar', 'email' => 'noguardar@example.com', 'rol_id' => $admin->rol_id,
        ], $headers)->assertStatus(503);
        $this->assertDatabaseMissing('users', ['usuario' => 'noguardar']);
        $this->travelBack();
    }

    public function test_all_http_results_are_audited_once_without_ip_or_user_agent(): void
    {
        $admin = $this->usuario();
        $headers = $this->cabeceras($admin);
        $this->getJson('/api/usuarios', $headers)->assertOk();
        $this->getJson('/api/roles', $headers)->assertOk();
        $this->getJson('/api/modulos', $headers)->assertOk();
        $this->getJson('/api/bitacora', $headers)->assertOk();
        $this->getJson('/api/usuarios')->assertUnauthorized();
        $this->postJson('/api/login', ['login' => 'intentovalidacion'])->assertUnprocessable();
        $this->getJson('/api/no-existe')->assertNotFound();
        $this->get('/')->assertOk();
        Route::get('/api/error-prueba', fn () => throw new \RuntimeException('detalle interno de prueba'));
        $this->getJson('/api/error-prueba')->assertStatus(500)->assertJsonPath('message', 'Ocurrió un error interno del servidor.');
        $this->assertSame(9, DB::table('bitacora')->count());
        $this->assertSame('intentovalidacion', DB::table('bitacora')->where('usuario_login', 'intentovalidacion')->value('usuario_login'));
        $this->assertSame(1, DB::table('bitacora')->where('resultado', 'ERROR')->count());
        $this->assertSame([
            'id', 'usuario_id', 'usuario_nombre', 'usuario_login', 'modulo', 'accion', 'resultado', 'descripcion', 'created_at',
        ], Schema::getColumnListing('bitacora'));
    }

    public function test_throttle_and_https_use_json_contract_and_are_audited(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/api/login', ['login' => 'desconocido', 'password' => 'password'])->assertUnauthorized();
        }
        $this->postJson('/api/login', ['login' => 'desconocido', 'password' => 'password'])->assertStatus(429)->assertJsonPath('statusCode', 429);
        $this->assertSame(16, DB::table('bitacora')->count());
        config(['centrix.https_obligatorio' => true]);
        $this->getJson('/api/me')->assertStatus(400)->assertJsonPath('message', 'Utiliza una conexión HTTPS segura.');
        $this->getJson('https://localhost/api/me')->assertUnauthorized();
    }

    public function test_related_business_data_prevents_deletion_and_rolls_back_revocation(): void
    {
        $admin = $this->usuario();
        $usuario = $this->usuario();
        $headers = $this->cabeceras($admin);
        $usuario->createToken('debe-conservarse');
        Schema::create('relacion_negocio_prueba', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
        });
        DB::table('relacion_negocio_prueba')->insert(['usuario_id' => $usuario->id]);
        $this->deleteJson('/api/usuarios/'.$usuario->id, ['confirmar' => true], $headers)->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $usuario->id]);
        $this->assertSame(1, $usuario->tokens()->count());
        $this->assertSame(0, DB::table('bitacora')->where('accion', 'ELIMINAR')->where('resultado', 'EXITOSO')->count());
    }

    public function test_token_abilities_assignments_and_admin_only_unlock_are_enforced(): void
    {
        $admin = $this->usuario();
        $limitado = $admin->createToken('limitado', ['USUARIOS.CONSULTAR'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$limitado];
        $this->getJson('/api/usuarios', $headers)->assertOk();
        $this->patchJson('/api/usuarios/'.$admin->id.'/desbloquear', [], $headers)->assertForbidden();
        $rol = Rol::forceCreate(['codigo' => 'CONSULTOR', 'nombre' => 'Consultor', 'activo' => true]);
        $noAdmin = $this->usuario(['rol_id' => $rol->id]);
        $headers = $this->cabeceras($noAdmin);
        $this->getJson('/api/usuarios', $headers)->assertForbidden();
        $rol->permisos()->attach(DB::table('permisos')->where('codigo', 'USUARIOS.CONSULTAR')->value('id'));
        $rol->permisos()->attach(DB::table('permisos')->where('codigo', 'USUARIOS.DESBLOQUEAR')->value('id'));
        $this->getJson('/api/usuarios', $headers)->assertOk();
        $this->patchJson('/api/usuarios/'.$admin->id.'/desbloquear', [], $headers)->assertForbidden();
    }

    public function test_bootstrap_admin_requires_invitation_and_cannot_be_repeated(): void
    {
        Notification::fake();
        $this->artisan('centrix:crear-admin', [
            '--nombre' => 'Administrador inicial', '--usuario' => 'ADMIN', '--email' => 'ADMIN@EXAMPLE.COM',
        ])->assertExitCode(0);
        $admin = User::where('usuario', 'admin')->firstOrFail();
        $this->assertTrue($admin->requiere_establecer_password);
        $this->assertSame('ADMIN', $admin->rol->codigo);
        Notification::assertSentTo($admin, EstablecerPassword::class);
        $this->assertSame(1, DB::table('bitacora')->where('accion', 'CREAR')->count());
        $this->artisan('centrix:crear-admin', [
            '--nombre' => 'Otro', '--usuario' => 'otro', '--email' => 'otro@example.com',
        ])->assertExitCode(1);
        $this->assertSame(1, User::count());
    }

    public function test_password_reset_revokes_old_access_and_never_activates_or_unlocks(): void
    {
        $usuario = $this->usuario();
        $headers = $this->cabeceras($usuario);
        $token = Password::createToken($usuario);
        $datos = [
            'email' => $usuario->email, 'token' => $token,
            'password' => 'Nueva-Password123!', 'password_confirmation' => 'Nueva-Password123!',
        ];
        $usuario->forceFill(['activo' => false, 'bloqueado' => true, 'intentos_fallidos' => 3])->save();
        $this->postJson('/api/reset-password', $datos)->assertUnprocessable();
        $this->assertFalse($usuario->fresh()->activo);
        $this->assertTrue($usuario->fresh()->bloqueado);
        $usuario->forceFill(['activo' => true, 'bloqueado' => false, 'intentos_fallidos' => 0])->save();
        $this->postJson('/api/reset-password', $datos)->assertOk();
        $this->getJson('/api/me', $headers)->assertUnauthorized();
        $this->postJson('/api/login', ['login' => $usuario->usuario, 'password' => 'password'])->assertUnauthorized();
        $this->postJson('/api/login', ['login' => $usuario->usuario, 'password' => $datos['password']])->assertOk();
        $this->travel(61)->seconds();
        $datos['token'] = Password::createToken($usuario);
        $this->travel(61)->minutes();
        $this->postJson('/api/reset-password', $datos)->assertUnprocessable();
        $this->travelBack();
    }

    public function test_passwords_are_not_silently_truncated_by_bcrypt(): void
    {
        $password = str_repeat('a', 68).'Aa1!';
        $usuario = $this->usuario(['password' => $password]);
        $this->postJson('/api/login', ['login' => $usuario->usuario, 'password' => $password.'extra'])->assertUnprocessable();
        $this->postJson('/api/login', ['login' => $usuario->usuario, 'password' => $password])->assertOk();
        $token = Password::createToken($usuario);
        $this->postJson('/api/reset-password', [
            'email' => $usuario->email, 'token' => $token, 'password' => $password.'extra', 'password_confirmation' => $password.'extra',
        ])->assertUnprocessable();
        $this->assertTrue(Password::tokenExists($usuario, $token));
    }
}
