# Revisión de bitácora — 12 de noviembre de 2026

## Resultado

Las 23 rutas API pasan por AuditarSolicitudes y las rutas protegidas también por SesionCentrix. Los controladores describen sus operaciones; el servicio Bitacora evita duplicados. Se añadieron seis pruebas de integración que verifican las rutas reales, las páginas, salud, CORS y errores. La suite completa terminó con 41 pruebas aprobadas y 767 verificaciones, usando SQLite en memoria y notificaciones simuladas. No se enviaron peticiones a producción ni se modificaron datos de MySQL.

| Módulo | Endpoints comprobados |
| --- | --- |
| Autenticación | POST login, GET me, POST logout, POST forgot-password, POST reset-password |
| Usuarios | GET listado, GET detalle, POST crear, PUT editar, PATCH estado (activar e inactivar), PATCH desbloquear, POST reenviar acceso, DELETE eliminar |
| Roles | GET listado, GET detalle, POST crear, PUT editar, PATCH estado (activar e inactivar), PUT permisos, DELETE eliminar |
| Permisos | GET permisos y GET modulos (alias) |
| Bitácora | GET bitacora |

Además se verificaron GET /, GET /establecer-password, GET /up y OPTIONS de verificación CORS. Las solicitudes OPTIONS se registran como VERIFICAR_ORIGEN, sin describirlas como un inicio de sesión. La lectura de bitácora genera una sola entrada adicional, sin ciclos de registros.

Para cada ruta API se ejecuta una petición exitosa y se exige exactamente un registro, con módulo, acción, resultado y descripción. Una comparación con las rutas registradas obliga a ampliar la prueba si se añade un endpoint API nuevo. También se recorren las rutas protegidas sin token y, cuando exigen permiso, con un usuario que no tiene ninguno: cada rechazo debe generar una sola entrada.

## Correcciones

1. Los rechazos anteriores al controlador ahora obtienen módulo y acción del nombre de la ruta. Por ejemplo, logout rechazado se identifica como AUTENTICACION / LOGOUT, y el límite de reenvío de acceso como USUARIOS / REENVIAR_ACCESO.
2. Se reemplazó la marca auditada por el identificador del registro. Antes de omitir una inserción se verifica que exista: si una transacción revirtió un registro previo, el error final vuelve a registrarse fuera de esa transacción. Hay una prueba que reproduce este caso y exige un único registro ERROR.
3. Los errores de servidor no conservan resultados EXITOSO ni descripciones que afirmen que se creó un registro cuando la operación falló. Se verificó el caso de crear un usuario con el correo sin configurar: el alta se revierte, pero su error se registra una vez.

## Datos conservados

La tabla sigue teniendo nueve columnas: id, usuario_id, usuario_nombre, usuario_login, modulo, accion, resultado, descripcion y created_at. No se agregaron columnas ni se almacenan IP, cuerpos HTTP, contraseñas, tokens Bearer ni tokens de recuperación. Las pruebas buscan explícitamente esos secretos en los registros y comprueban que el token de un enlace en query no se copie a bitácora.

La garantía se limita a peticiones que llegan a Laravel y a una base donde la tabla bitacora está disponible. No se puede insertar en esa tabla durante una caída de MySQL o antes de crearla. Tampoco se registran desde Laravel errores que Apache/PHP rechace antes de ejecutar la aplicación ni accesos a archivos estáticos servidos directamente por el servidor web.

## Archivos

- app/Services/Bitacora.php: única modificación de código necesaria en producción para esta revisión.
- tests/Feature/EndpointAuditTest.php: seis pruebas nuevas de cobertura e integridad.
- docs/revision-bitacora.md: este informe.

## Subida por FTP

El ZIP entregas/bitacora-cpanel.zip incluye únicamente app/Services/Bitacora.php. Descomprimirlo y subir el archivo conservando esa ruta, reemplazando la versión del servidor después de respaldarla. No hace falta subir pruebas ni este documento.

No hay migraciones, dependencias, variables .env ni seeders nuevos para esta corrección. El ZIP acumulado roles-permisos-cpanel.zip también se actualizó para incluir este servicio; sus instrucciones del seeder solo corresponden a quienes aún no aplicaron la actualización de roles/permisos.
