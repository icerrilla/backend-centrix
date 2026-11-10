<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoAccesoController;
use App\Http\Controllers\PasswordController;
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
Route::patch('usuarios/{id}/activar', [UsuarioController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.ACTIVAR')->name('usuarios.activar');
Route::patch('usuarios/{id}/inactivar', [UsuarioController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.INACTIVAR')->name('usuarios.inactivar');
Route::patch('usuarios/{id}/desbloquear', [UsuarioController::class, 'desbloquear'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.DESBLOQUEAR')->name('usuarios.desbloquear');
Route::post('usuarios/{id}/reenviar-acceso', [UsuarioController::class, 'reenviar'])->whereNumber('id')->middleware(['throttle:centrix-password', 'centrix.sesion:USUARIOS.REENVIAR_ACCESO'])->name('usuarios.reenviar');
Route::delete('usuarios/{id}', [UsuarioController::class, 'destroy'])->whereNumber('id')->middleware('centrix.sesion:USUARIOS.ELIMINAR')->name('usuarios.destroy');
Route::get('roles', [CatalogoAccesoController::class, 'roles'])->middleware('centrix.sesion:ROLES.CONSULTAR')->name('roles.index');
Route::get('modulos', [CatalogoAccesoController::class, 'modulos'])->middleware('centrix.sesion:MODULOS.CONSULTAR')->name('modulos.index');
Route::get('bitacora', [CatalogoAccesoController::class, 'bitacora'])->middleware('centrix.sesion:BITACORA.CONSULTAR')->name('bitacora.index');
