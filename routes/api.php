<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BeneficioController;
use App\Http\Controllers\CatalogoAccesoController;
use App\Http\Controllers\CatalogoNegocioController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\HitoController;
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

// Semana 3: todas las rutas pasan por sesión y auditoría.
Route::get('eventos', [EventoController::class, 'index'])->middleware('centrix.sesion')->name('eventos.index');
Route::get('eventos/{id}', [EventoController::class, 'show'])->whereNumber('id')->middleware('centrix.sesion')->name('eventos.show');
Route::post('eventos', [EventoController::class, 'store'])->middleware('centrix.sesion:EVENTOS.CREAR')->name('eventos.store');
Route::put('eventos/{id}', [EventoController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:EVENTOS.EDITAR')->name('eventos.update');
Route::patch('eventos/{id}/estado', [EventoController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:EVENTOS.CANCELAR')->name('eventos.estado');
Route::post('eventos/{id}/imagenes', [EventoController::class, 'imagen'])->whereNumber('id')->middleware('centrix.sesion:EVENTOS.EDITAR')->name('eventos.imagen');
Route::get('eventos/{id}/imagenes/{imagen_id}', [EventoController::class, 'verImagen'])->whereNumber('id')->whereNumber('imagen_id')->middleware('centrix.sesion')->name('eventos.imagen.ver');
Route::delete('eventos/{id}/imagenes/{imagen_id}', [EventoController::class, 'borrarImagen'])->whereNumber('id')->whereNumber('imagen_id')->middleware('centrix.sesion:EVENTOS.EDITAR')->name('eventos.borrarImagen');
Route::get('hitos', [HitoController::class, 'index'])->middleware('centrix.sesion')->name('hitos.index');
Route::post('hitos', [HitoController::class, 'store'])->middleware('centrix.sesion:HITOS.CREAR')->name('hitos.store');
Route::put('hitos/{id}', [HitoController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:HITOS.EDITAR')->name('hitos.update');
Route::patch('hitos/{id}/estado', [HitoController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:HITOS.RETIRAR')->name('hitos.estado');
Route::get('beneficios', [BeneficioController::class, 'index'])->middleware('centrix.sesion')->name('beneficios.index');
Route::post('beneficios', [BeneficioController::class, 'store'])->middleware('centrix.sesion:BENEFICIOS.CREAR')->name('beneficios.store');
Route::put('beneficios/{id}', [BeneficioController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:BENEFICIOS.EDITAR')->name('beneficios.update');
Route::patch('beneficios/{id}/estado', [BeneficioController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:BENEFICIOS.ACTIVAR')->name('beneficios.estado');
Route::get('beneficios/{id}/aplicados', [BeneficioController::class, 'aplicados'])->whereNumber('id')->middleware('centrix.sesion')->name('beneficios.aplicados');
Route::post('beneficios/{id}/aplicados', [BeneficioController::class, 'registrar'])->whereNumber('id')->middleware('centrix.sesion:BENEFICIOS.REGISTRAR')->name('beneficios.registrar');
Route::post('beneficios-aplicados/{id}', [BeneficioController::class, 'editarAplicado'])->whereNumber('id')->middleware('centrix.sesion:BENEFICIOS.REGISTRAR')->name('aplicados.editar');
Route::delete('beneficios-aplicados/{id}', [BeneficioController::class, 'borrarAplicado'])->whereNumber('id')->middleware('centrix.sesion:BENEFICIOS.REGISTRAR')->name('aplicados.destroy');
Route::get('beneficios-aplicados/{id}/imagen', [BeneficioController::class, 'archivo'])->whereNumber('id')->defaults('campo', 'imagen')->middleware('centrix.sesion')->name('aplicados.imagen');
Route::get('beneficios-aplicados/{id}/documento', [BeneficioController::class, 'archivo'])->whereNumber('id')->defaults('campo', 'documento')->middleware('centrix.sesion')->name('aplicados.documento');
Route::get('documentos', [DocumentoController::class, 'index'])->middleware('centrix.sesion')->name('documentos.index');
Route::post('documentos', [DocumentoController::class, 'store'])->middleware('centrix.sesion:DOCUMENTOS.SUBIR')->name('documentos.store');
Route::put('documentos/{id}', [DocumentoController::class, 'update'])->whereNumber('id')->middleware('centrix.sesion:DOCUMENTOS.EDITAR')->name('documentos.update');
Route::post('documentos/{id}/archivo', [DocumentoController::class, 'reemplazar'])->whereNumber('id')->middleware('centrix.sesion:DOCUMENTOS.EDITAR')->name('documentos.reemplazar');
Route::patch('documentos/{id}/estado', [DocumentoController::class, 'estado'])->whereNumber('id')->middleware('centrix.sesion:DOCUMENTOS.RETIRAR')->name('documentos.estado');
Route::get('documentos/{id}/archivo', [DocumentoController::class, 'archivo'])->whereNumber('id')->middleware('centrix.sesion')->name('documentos.archivo');
Route::get('configuracion', [ConfiguracionController::class, 'index'])->middleware('centrix.sesion')->name('configuracion.index');
Route::put('configuracion', [ConfiguracionController::class, 'update'])->middleware('centrix.sesion:CONFIGURACION.EDITAR')->name('configuracion.update');
