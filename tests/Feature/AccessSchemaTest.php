<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccessSchemaTest extends TestCase
{
    public function test_upgrade_preserves_users_and_seeding_is_repeatable(): void
    {
        // Nunca permitir que esta prueba toque la base configurada para la aplicación.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        Artisan::call('migrate', ['--path' => 'database/migrations/0001_01_01_000000_create_users_table.php', '--force' => true]);
        DB::table('users')->insert([
            'name' => 'Usuario anterior', 'email' => 'anterior@example.com',
            'password' => Hash::make('password'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::firstOrFail();
        $original = $user->getAttributes();
        Artisan::call('migrate', ['--force' => true]);
        $user->refresh();
        foreach ($original as $column => $value) {
            $this->assertEquals($value, $user->getRawOriginal($column));
        }
        $this->assertNull($user->rol_id);
        $this->assertTrue($user->activo);
        $this->assertFalse($user->bloqueado);
        $this->assertSame(0, $user->intentos_fallidos);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, DB::table('roles')->count());
        $this->assertSame('ADMIN', DB::table('roles')->value('codigo'));
        $this->assertSame(9, DB::table('modulos')->count());
        $this->assertSame(35, DB::table('permisos')->count());
        $this->assertSame(35, DB::table('rol_permiso')->count());
        $this->assertSame(1, User::count());

        // Repetir seed no reactiva roles inactivados ni cambia nombres personalizados.
        DB::table('roles')->update(['activo' => false, 'nombre' => 'Nombre personalizado']);
        $this->seed(DatabaseSeeder::class);
        $this->assertEquals(0, DB::table('roles')->value('activo'));
        $this->assertSame('Nombre personalizado', DB::table('roles')->value('nombre'));

        $this->assertCount(9, Schema::getColumnListing('bitacora'));
        DB::table('bitacora')->insert([
            'usuario_id' => $user->id, 'usuario_nombre' => $user->name,
            'modulo' => 'USUARIOS', 'accion' => 'ELIMINAR',
            'resultado' => 'EXITOSO',
            'descripcion' => 'Se eliminó el usuario '.$user->name.'.',
        ]);
        $token = $user->createToken('prueba');
        $this->assertNotEmpty($token->plainTextToken);
        // Sanctum usa relación polimórfica: el servicio debe revocar antes de borrar.
        $user->tokens()->delete();
        $user->delete();
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertNull(DB::table('bitacora')->value('usuario_id'));
        $this->assertSame($original['name'], DB::table('bitacora')->value('usuario_nombre'));
        $this->assertStringContainsString($original['name'], DB::table('bitacora')->value('descripcion'));

        // Revierte únicamente el lote nuevo; la migración inicial y users siguen presentes.
        Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'rol_id'));
        $this->assertFalse(Schema::hasTable('roles'));
    }
}
