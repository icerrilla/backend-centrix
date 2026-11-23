# Centrix — entrega de semana 3

Fecha: 23/11/2026. Código acumulado sobre semana 2. Sin dependencias Composer nuevas.

## Qué incluye

- 30 operaciones nuevas: eventos (8), hitos (4), beneficios y aplicados (10), documentos (6), configuración (2).
- 19 permisos nuevos, asignables y retirables por rol. Total: 64 permisos en 15 módulos. Solo ADMIN recibe los nuevos por el seeder; OPERADOR y CLIENTE conservan sus asignaciones.
- `permisos_extra` individual, solo DOCUMENTOS.DESCARGAR, administrado con USUARIOS.CREAR/EDITAR por decisión del usuario. Omitirlo en PUT conserva; [] lo limpia. La unión con el rol se valida en cada petición, también frente a las habilidades del token.
- Eventos en UTC (entrada ISO con zona, salida con Z); PROGRAMADO/REALIZADO calculados por fecha, CANCELADO persistente. Reprogramar uno realizado permite devolverlo a PROGRAMADO; si estaba cancelado, restaurarlo explícitamente. No requiere cron ni escribe bitácoras automáticas por el paso del tiempo.
- Máximo 20 imágenes de 20 MB por evento. Archivos privados; validación de extensión y contenido real. PPTX requiere estructura de presentación. Nombres originales de documentos sin rutas ni caracteres de control, con filename UTF-8.
- Usados por suma de cantidades, cupo protegido bloqueando el beneficio en transacción; se rechazan nuevas aplicaciones o ediciones en un beneficio inactivo. Fecha dentro de vigencia. Reducir disponibles por debajo de usados o excluir aplicaciones de su vigencia devuelve 422. Borrar una aplicación libera su cantidad.
- Resumen de beneficios activos en patrocinio: total, agotados y promedio de avance a dos decimales, cero sin activos.
- Nuevas referencias impiden borrar tipos de beneficio, marcas y embotelladores usados. Hitos y documentos se retiran/restauran; no tienen DELETE.
- Toda ruta conserva bitácora de consultas, archivos, cambios, rechazos y descargas explícitas, con cambios anterior/nuevo e IP. Nuevos archivos fallidos se limpian; reemplazos/borrados eliminan los anteriores después de confirmar la transacción.
- Sin CONSULTAR del propio módulo: publicados de Nacional y ámbitos del usuario. Eventos usan ámbito propio; documentos/aplicados ligados a evento verifican ambos. CONSULTAR del propio módulo permite ver todo sin exigir PATROCINIOS.CONSULTAR.
- El interruptor de configuración es visual: nunca amplía acceso a datos. Ver un documento entrega sus bytes; el permiso DESCARGAR restringe la descarga explícita y su registro, no impide guardar desde herramientas del navegador.

## Archivos principales

Nuevos:

- database/migrations/2026_11_23_000001_create_week_three_tables.php
- app/Http/Controllers/EventoController.php
- app/Http/Controllers/HitoController.php
- app/Http/Controllers/BeneficioController.php
- app/Http/Controllers/DocumentoController.php
- app/Http/Controllers/ConfiguracionController.php
- app/Services/AccesoNegocio.php
- app/Services/ArchivosPrivados.php
- app/Exceptions/ValidacionNegocio.php

Modificados: routes/api.php, bootstrap/app.php, CatalogoPermisos, User, UsuarioController, Patrocinio, PatrocinioController, CatalogoNegocioController, Bitacora y AuditarSolicitudes. El AccessCatalogSeeder existente utiliza el catálogo ampliado; no requiere otro seeder. Se actualizaron pruebas, la colección única y el contrato.

## Contrato para el front

Ver [Referencia de la API](contrato-api.md): 95 operaciones. Importar `colleccion postman.json`: 99 peticiones, sin ejemplos de respuesta. Filtros opcionales están deshabilitados en Params. Seleccionar los archivos en multipart; no fijar Content-Type manual. La asignación de permisos a roles incluye los módulos nuevos con false para no concederlos accidentalmente.

## Aplicación local

No usar migrate:fresh, migrate:refresh ni db:wipe. No volver a reiniciar la bitácora por esta entrega.

Con MySQL activo, desde la raíz del backend:

```powershell
& 'C:\wamp64\bin\php\php8.2.29\php.exe' artisan migrate:status
& 'C:\wamp64\bin\php\php8.2.29\php.exe' artisan migrate
& 'C:\wamp64\bin\php\php8.2.29\php.exe' artisan db:seed --class=AccessCatalogSeeder
& 'C:\wamp64\bin\php\php8.2.29\php.exe' artisan optimize:clear
```

Si alguna migración de semana 2 sigue pendiente, aplicarla y ejecutar DatabaseSeeder (`artisan db:seed`) para crear Nacional y tipos de beneficio iniciales. Conservar usuarios, datos de negocio e historial.

## cPanel: actualización por FTP

1. Respaldar la base y los archivos actuales desde cPanel.
2. En terminal, ir a la raíz donde está artisan y activar mantenimiento **antes** de subir el código:

```bash
cd ~/backend-centrix.podium-sm.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan down --retry=60
```

3. Extraer `entregas/centrix-semana3-cpanel.zip` en la PC. Subir su contenido sobre esa raíz. No reemplazar `.env`, vendor ni storage. El paquete no incluye cachés, secretos ni archivos de usuarios. Mantener el dominio apuntando a public.
4. PHP web y CLI deben ser 8.2 o superior. Comprobar extensiones `fileinfo` y `zip` en ambos. En MultiPHP INI: `upload_max_filesize=20M`, `post_max_size=50M` para admitir dos evidencias de hasta 20 MB más campos en una petición. Los límites del proxy/hosting también deben admitirlo. No usar storage:link para estos archivos privados.
5. Ejecutar uno por uno; detenerse si alguno falla:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate:status
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --class=AccessCatalogSeeder --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize
/opt/cpanel/ea-php82/root/usr/bin/php artisan up
```

Si hay migraciones pendientes de semana 2, usar `artisan db:seed --force` en lugar del seeder específico. La migración nueva agrega nueve tablas, no elimina ni vacía tablas existentes. No ejecutar el comando de reinicio de bitácora.

6. Comprobar login, /me, catálogo de permisos, configuración y una operación autorizada de cada módulo. Verificar 404 en un archivo de otro ámbito y 403 en descarga sin permiso. Las pruebas del proyecto no envían correos ni consultan producción.

## Estado de aplicación

Implementación y pruebas en base SQLite de memoria. Al comprobar MySQL local, 127.0.0.1:3306 rechazó la conexión; por tanto no se aplicaron migraciones ni seeders en la base local real. Tampoco se modificó producción.

## Resultados de verificación

72 pruebas aprobadas, 2,404 comprobaciones, con SQLite de memoria; formato Pint aprobado. Se verificaron las 95 rutas, incluyendo auditoría de cada operación y rechazo por falta de autenticación/permisos. La colección tiene 99 peticiones sin respuestas de ejemplo y sus cuerpos, variables y scripts se validaron sin enviar peticiones. No se verificó la ejecución de la migración contra MySQL porque el servicio local no respondió; producción no fue consultada.
