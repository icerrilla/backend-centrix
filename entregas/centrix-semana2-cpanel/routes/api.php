<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoAccesoController;
use App\Http\Controllers\CatalogoNegocioController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PatrocinioController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use App\Support\CatalogosNegocio;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:centrix-login')->name('auth.login');
Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:centrix-password')->name('auth.forgot-password');
Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:centrix-password')->name('auth.reset-password');
Route::get('me', [AuthController::class, 'me'])->middleware('centrix.sesion')->name('auth.me');
Route::post('logout', [AuthController::class, 'logout'])->middleware('centrix.sesion')->name('auth.logout');

Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('centrix.sesion:USUARIOS.CONSULTAR')->name('usuarios.index');
Route::get('usuarios/{id}', [UsuarioController::class, 'show'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.CONSULTAR')->name('usuarios.show');
Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('centrix.sesion:USUARIOS.CREAR')->name('usuarios.store');
Route::put('usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.EDITAR')->name('usuarios.update');
Route::patch('usuarios/{id}/estado', [UsuarioController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.ACTIVAR')->name('usuarios.estado');
Route::patch('usuarios/{id}/desbloquear', [UsuarioController::class, 'desbloquear'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.DESBLOQUEAR')->name('usuarios.desbloquear');
Route::post('usuarios/{id}/reenviar-acceso', [UsuarioController::class, 'reenviar'])->whereNumber('id')->middleware(['throttle:centrix-password', 'centrix.sesion:USUARIOS.REENVIAR_ACCESO'])->name('usuarios.reenviar');
Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.ELIMINAR')->name('usuarios.destroy');
Route::get('roles', [RolController::class, 'index'])->middleware('centrix.sesion:ROLES.CONSULTAR')->name('roles.index');
Route::get('roles/{id}', [RolController::class, 'show'])->whereNumber('id')->middleware('centrix.sesion:ROLES.CONSULTAR')->name('roles.show');
Route::post('roles', [RolController::class, 'store'])->middleware('centrix.sesion:ROLES.CREAR')->name('roles.store');
Route::put('roles/{id}', [RolController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:ROLES.EDITAR')->name('roles.update');
Route::patch('roles/{id}/estado', [RolController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:ROLES.ACTIVAR')->name('roles.estado');
Route::put('roles/{id}/permisos', [RolController::class, 'asignarPermisos'])->whereNumber('id')->middleware('centrix.sesion:ROLES.ASIGNAR_PERMISOS')->name('roles.permisos');
Route::delete('roles/{id}', [RolController::class, 'destroy'])->whereNumber('id')->middleware('centrix.sesion:ROLES.ELIMINAR')->name('roles.destroy');
Route::get('permisos', [PermisoController::class, 'index'])->middleware('centrix.sesion:PERMISOS.CONSULTAR')->name('permisos.index');
Route::get('modulos', [PermisoController::class, 'index'])->middleware('centrix.sesion:PERMISOS.CONSULTAR')->name('modulos.index');
Route::get('bitacora', [CatalogoAccesoController::class, 'bitacora'])->middleware('centrix.sesion:BITACORA.CONSULTAR')->name('bitacora.index');

foreach (CatalogosNegocio::todos() as $catalogo => $config) {
    Route::get($catalogo, [CatalogoNegocioController::class, 'index'])->defaults('catalogo', $catalogo)->middleware('centrix.sesion')->name('catalogos.'.$catalogo.'.index');
    Route::get($catalogo.'/{id}', [CatalogoNegocioController::class, 'show'])->whereNumber('id')->defaults('catalogo', $catalogo)->middleware('centrix.sesion')->name('catalogos.'.$catalogo.'.show');
    Route::post($catalogo, [CatalogoNegocioController::class, 'store'])->defaults('catalogo', $catalogo)->middleware('centrix.sesion:'.$config['modulo'].'.CREAR')->name('catalogos.'.$catalogo.'.store');
    Route::put($catalogo.'/{id}', [CatalogoNegocioController::class, 'update'])->whereNumber('id')->defaults('catalogo', $catalogo)->middleware('centrix.sesion:'.$config['modulo'].'.EDITAR')->name('catalogos.'.$catalogo.'.update');
    Route::patch($catalogo.'/{id}/estado', [CatalogoNegocioController::class, 'estado'])->whereNumber('id')->defaults('catalogo', $catalogo)->middleware('centrix.sesion:'.$config['modulo'].'.ACTIVAR')->name('catalogos.'.$catalogo.'.estado');
    Route::delete($catalogo.'/{id}', [CatalogoNegocioController::class, 'destroy'])->whereNumber('id')->defaults('catalogo', $catalogo)->middleware('centrix.sesion:'.$config['modulo'].'.ELIMINAR')->name('catalogos.'.$catalogo.'.destroy');
    if (in_array($catalogo, ['pilares', 'marcas'], true)) {
        Route::post($catalogo.'/{id}/imagen', [CatalogoNegocioController::class, 'imagen'])->whereNumber('id')->defaults('catalogo', $catalogo)->middleware('centrix.sesion:'.$config['modulo'].'.EDITAR')->name('catalogos.'.$catalogo.'.imagen');
        Route::get($catalogo.'/{id}/imagen', [CatalogoNegocioController::class, 'verImagen'])->whereNumber('id')->defaults('catalogo', $catalogo)->name('catalogos.'.$catalogo.'.imagen.ver');
    }
}
Route::patch('pilares/orden', [CatalogoNegocioController::class, 'orden'])->defaults('catalogo', 'pilares')->middleware('centrix.sesion:CATALOGO_PILARES.EDITAR')->name('catalogos.pilares.orden');

Route::get('patrocinios', [PatrocinioController::class, 'index'])->middleware('centrix.sesion')->name('patrocinios.index');
Route::get('patrocinios/{id}', [PatrocinioController::class, 'show'])->whereNumber('id')->middleware('centrix.sesion')->name('patrocinios.show');
Route::post('patrocinios', [PatrocinioController::class, 'store'])->middleware('centrix.sesion:PATROCINIOS.CREAR')->name('patrocinios.store');
Route::put('patrocinios/{id}', [PatrocinioController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:PATROCINIOS.EDITAR')->name('patrocinios.update');
Route::patch('patrocinios/{id}/estado', [PatrocinioController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion')->name('patrocinios.estado');
Route::post('patrocinios/{id}/imagen', [PatrocinioController::class, 'imagen'])->whereNumber('id')->middleware('centrix.sesion:PATROCINIOS.EDITAR')->name('patrocinios.imagen');
Route::get('patrocinios/{id}/imagen', [PatrocinioController::class, 'verImagen'])->whereNumber('id')->middleware('centrix.sesion')->name('patrocinios.imagen.ver');
