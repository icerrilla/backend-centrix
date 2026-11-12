<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoAccesoController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
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
