# Centrix — entrega de semana 2

## Qué se entrega

Se mantienen los formatos statusCode, message, dataResponse y errorMessageDTO. Los listados contienen su nombre en plural y paginacion. Se agregan tres migraciones, catálogos, ámbitos de usuarios, patrocinios, imágenes y auditoría estructurada. El paquete no contiene .env, claves, contraseñas, vendor, archivos subidos ni una copia de la base local.

La colección Postman tiene 69 peticiones, agrupadas en once carpetas. Se verificaron las 65 rutas API, incluyendo la bitácora de cada endpoint nuevo. El alias GET /modulos sigue existiendo, aunque Postman utiliza /permisos.

## Usuarios

- Se devuelve estado con prioridad INACTIVO, BLOQUEADO, PENDIENTE, ACTIVO. PENDIENTE corresponde a requiere_establecer_password=true. Activo y bloqueado permanecen como booleanos independientes.
- Se devuelven created_at y ultimo_acceso_at. Se admite GET /usuarios?estado=PENDIENTE, así como los otros estados.
- POST/PUT admiten ambito_ids: [id,...]. Solo se pueden asignar embotelladores activos. En PUT, omitir el campo conserva las asignaciones; enviar [] las retira. Nacional siempre se incorpora en la respuesta y visibilidad, incluso para cuentas anteriores sin asignaciones.
- ambitos contiene objetos {id,nombre}. Los ámbitos inactivos ya asignados se conservan como referencias históricas; no se admiten como asignaciones nuevas.
- Una cuenta no puede inactivarse ni eliminarse a sí misma, aunque exista otro administrador. Devuelve 409.
- Si falla el correo, se conserva el usuario PENDIENTE, con HTTP 201, acceso_enviado=false y mensaje: “Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después.” El token de enlace fallido se revierte con un savepoint; se puede reenviar posteriormente.

## Roles y permisos

Se crean ADMIN, OPERADOR y CLIENTE sin duplicarlos ni modificar roles existentes. OPERADOR y CLIENTE no reciben permisos automáticamente: se configuran desde la asignación granular. ADMIN conserva acceso completo y no incluye el arreglo permisos en login/me; otros roles reciben los módulos y acciones true/false.

CATALOGO_EMPRESAS se sustituye por CATALOGO_EMBOTELLADORES. El seeder copia las asignaciones de cada código anterior a su equivalente y retira los vínculos del código viejo; no concede otras acciones. Las filas de códigos retirados se conservan inactivas para no romper referencias.

Se agrega PATROCINIOS: CONSULTAR, CREAR, EDITAR, PUBLICAR y ARCHIVAR. El catálogo activo tiene 45 permisos en diez módulos. Los códigos de los demás catálogos conservan sus acciones CONSULTAR/CREAR/EDITAR/ACTIVAR/ELIMINAR.

## Bitácora

La tabla pasa de nueve a trece columnas, añadiendo registro_tipo, registro_id, cambios e ip. cambios es un arreglo JSON de {campo,anterior,nuevo}; las lecturas y las acciones sin cambios devuelven []. Los tipos se expresan como USUARIO, ROL, PILAR, MARCA, CATEGORIA, TIPO_BENEFICIO, EMBOTELLADOR, PATROCINIO, PERMISO, BITACORA o SISTEMA.

Cada petición continúa generando una sola entrada. Se registran altas, ediciones, estados, asignaciones, orden, imágenes, consultas, rechazos y errores. Los cambios automáticos persistidos de intentos/bloqueo se conservan incluso si login devuelve 401 o 423. Las operaciones revertidas no se registran como cambios exitosos. Se excluyen contraseñas, tokens y rutas privadas de archivos de los datos auditables. La IP viene de Request::ip(); únicamente configurar proxies de confianza reales, nunca aceptar cabeceras de cualquier cliente.

GET /bitacora añade usuario_id, fecha_desde, fecha_hasta (YYYY-MM-DD, inclusivas), registro_tipo y registro_id, además de los filtros anteriores. Devuelve cambios como arreglo, no como string JSON.

El reinicio solicitado de historial es un comando separado: centrix:reiniciar-bitacora --force. Elimina únicamente registros de bitacora y deja una entrada de REINICIAR con el conteo eliminado. No reinicia IDs ni elimina usuarios, roles o datos de negocio. Sin --force no borra nada. Se pidió ejecutarlo tanto en local como en producción; consultar el estado real de aplicación al final de esta guía antes de afirmar que se ha hecho.

La migración NO borra historial por sí misma. La separación permite probar y controlar cuándo reiniciar cada entorno. Durante una caída de base o antes de aplicar la ampliación, la API devuelve 503 y no es posible guardar auditoría en MySQL. Peticiones que el servidor rechaza antes de llegar a Laravel tampoco pueden auditarse desde la aplicación.

## Catálogos

| Ruta | Lista | Campos |
| --- | --- | --- |
| /pilares | pilares | nombre, descripcion, color, orden, imagen_url, activo |
| /marcas | marcas | nombre, logotipo_url, activo |
| /categorias | categorias | nombre, activo |
| /tipos-beneficio | tipos_beneficio | nombre, activo |
| /embotelladores | embotelladores | nombre, color, estados, es_nacional, activo |

Todos tienen GET listado/detalle, POST, PUT y PATCH /{id}/estado con {activo:true|false}. DELETE /{id} elimina físicamente solo registros sin uso y requiere el permiso ELIMINAR del catálogo. Las referencias de patrocinios (incluso archivados) y las asignaciones de embotelladores a usuarios bloquean el borrado con 409. Nacional está protegido. La imagen se elimina después de confirmar el borrado y la bitácora conserva los datos anteriores. Crear/editar requiere nombre; los nombres son únicos en cada catálogo. El estado existente se cambia por su ruta separada y su permiso ACTIVAR. Todo usuario con sesión puede leer activos; consultar inactivos requiere el permiso CONSULTAR correspondiente. Pedir un detalle inactivo sin ese permiso devuelve 404; pedir explícitamente activo=0 sin permiso devuelve 403.

Los listados admiten buscar, activo, page y per_page de 1 a 100. El orden de pilares es orden/id; los otros catálogos se ordenan por nombre/id. PATCH /pilares/orden acepta {ids:[...]} e incluye todos los pilares exactamente una vez, también los inactivos.

Se siembran Físico, Media, Digital y Otro, y un embotellador Nacional activo con los 32 estados. Nacional no se puede inactivar ni convertir en otro ámbito. Un registro normal no puede convertirse en Nacional. La lista de claves ISO sin MX- usa QUE para Querétaro, CMX para Ciudad de México y NLE para Nuevo León. Fuente primaria de contraste: https://assets.publishing.service.gov.uk/media/68664eb7dd1a7e01559e6d06/Mexico_Toponymic_Factfile.pdf (tabla de subdivisiones ISO 3166-2).

### Imágenes

POST /pilares/{id}/imagen y POST /marcas/{id}/imagen reciben multipart con archivo. Solo JPG, PNG o WebP, hasta 20 MB. No enviar manualmente Content-Type en Postman/Angular al usar FormData: el cliente construye el boundary. Las URLs de estos catálogos son públicas y llevan una versión para evitar caché antigua al reemplazar una imagen.

Los archivos se guardan en storage/app/private con nombres aleatorios. Los controladores los entregan por sus rutas; no se necesita php artisan storage:link. Se deshabilitó la ruta genérica del disco local para no tener una vía alternativa a archivos privados. La imagen vieja se elimina después de confirmar la transacción; se limpian imágenes nuevas sin referencia si la petición falla.

En MultiPHP INI Editor del hosting configurar upload_max_filesize=20M y post_max_size al menos 24M para admitir un archivo de 20 MB más el multipart. El límite de aplicación también se valida; un rechazo de Apache anterior a PHP depende del hosting.

## Patrocinios

Rutas: GET /patrocinios, GET /patrocinios/{id}, POST /patrocinios, PUT /patrocinios/{id}, PATCH /patrocinios/{id}/estado, POST /patrocinios/{id}/imagen y GET /patrocinios/{id}/imagen.

POST/PUT: nombre, descripcion, pilar_id, categoria_id, marca_ids, contraparte, alcance, ciudad_territorio, ambito_id, vigencia_inicio, vigencia_fin, inversion, audiencia_promedio, responsable y contactos[{nombre,email,rol}]. Solo nombre/pilar son obligatorios al guardar borrador. Omitir ambito_id asigna Nacional, según se confirmó. PUT sustituye el conjunto de datos editable y las listas de marcas/contactos; los campos opcionales omitidos quedan vacíos. No se puede modificar estado ni imagen_url dentro del POST/PUT.

Fecha: YYYY-MM-DD; cuando ambas existen, fin no puede ser anterior al inicio. Inversión y audiencia: números de 0 a 999999999999.99, hasta dos decimales, guardados como DECIMAL y devueltos como números. Responsable y contactos son texto libre, sin asociación con users. Si hay contactos, cada uno requiere nombre; email es opcional pero debe ser válido cuando se envía. Los errores conservan las claves contactos.0.email, etc.

Publicar requiere nombre, pilar, categoría, contraparte (partner), ciudad/territorio, inicio, fin, inversión, responsable y embotellador. Devuelve 422 por campo y un message que enumera los faltantes. Inversión 0 es válida. Editar un PUBLICADO también debe conservar esos requisitos; no se permite dejarlo incompleto sin regresar a BORRADOR.

Transiciones: BORRADOR -> PUBLICADO -> ARCHIVADO. PUBLICADO y ARCHIVADO pueden volver a BORRADOR. Repetir el mismo estado es idempotente. No se permite BORRADOR -> ARCHIVADO ni ARCHIVADO -> PUBLICADO directo. Publicar requiere PUBLICAR, archivar ARCHIVAR y volver a borrador EDITAR.

La respuesta incluye pilar/categoria/ambito {id,nombre}, marcas, marca_ids, contactos, imagen_url, created_at y updated_at. Las relaciones inactivas ya usadas se pueden conservar; no se asignan de nuevo a otros registros.

### Visibilidad e imágenes privadas

Quien tenga PATROCINIOS.CONSULTAR y una habilidad de token que lo permita ve todos los estados y ámbitos. Los demás usuarios con sesión solo ven PUBLICADOS de Nacional más sus embotelladores asignados. El filtro de lista no amplía esa visibilidad. El mismo alcance se aplica al detalle y a las imágenes; un ID ajeno devuelve 404. Las operaciones de edición también respetan esa visibilidad.

La imagen del patrocinio se sube con archivo, igual que las de catálogos, pero imagen_url apunta a GET /api/patrocinios/{id}/imagen y requiere Authorization: Bearer. No enviar el token en la URL. En Angular, solicitar como Blob con HttpClient y asignar URL.createObjectURL al img; revocar el object URL al dejar de usarlo. La respuesta es privada y no-store. No hay copia en public/storage ni endpoint genérico del disco local que la exponga.

## Subida por FTP / cPanel

El ZIP entregas/centrix-semana2-cpanel.zip es una actualización acumulada del código de Centrix; se extrae localmente y se suben sus carpetas sobre la raíz donde están artisan, app, routes y vendor. No incluye vendor porque no hay dependencias nuevas. No sustituye .env ni archivos de usuarios, ni publica por sí solo el sitio.

1. Mantener una copia de archivos y base actual para poder recuperar una migración fallida. No importar la base local sobre producción.
2. En la terminal de cPanel, antes de subir, activar mantenimiento con la versión actual:

```bash
cd ~/backend-centrix.podium-sm.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan down --retry=60
```

3. Subir los archivos del ZIP conservando las rutas. El archivo de Postman y esta guía se entregan por separado, no van a la carpeta pública.
4. Ejecutar uno por uno; si alguno falla, detenerse y revisar, sin continuar con el reinicio:

```bash
/opt/cpanel/ea-php82/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan centrix:reiniciar-bitacora --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize
/opt/cpanel/ea-php82/root/usr/bin/php artisan up
```

El comando db:seed ejecuta AccessCatalogSeeder y BusinessCatalogSeeder; ejecutar solo el seeder de acceso no crea Nacional ni los tipos de beneficio. Las tres migraciones son adicionales: no reconstruyen users ni eliminan registros de negocio. No usar migrate:fresh, migrate:refresh, db:wipe ni borrar tablas a mano.

5. Revisar /up y probar en Postman: crear catálogo, crear borrador, completar, publicar, y probar un CLIENTE con y sin ámbito. Cambiar los permisos de OPERADOR desde la asignación granular; no se conceden solos. Reimportar colleccion postman.json y mantener los tokens/contraseñas como valores privados.

## Aplicación local

Con MySQL local encendido, desde E:\proyectosADR\centrix\backend:

```powershell
php artisan migrate
php artisan db:seed
php artisan centrix:reiniciar-bitacora --force
```

El reinicio de local y el de producción son independientes y solo deben ejecutarse en esta puesta a cero solicitada. No automatizarlos en futuras actualizaciones: borrarían el historial nuevo.

## Validación y estado de aplicación

Las pruebas automatizadas usan SQLite en memoria, correo simulado e imágenes de prueba. Cubren migración conservando usuarios, catálogos repetibles, permisos granulares, ámbito Nacional, estados, referencias inactivas, publicación, fechas, contactos, archivos privados, remapeo EMPRESAS, auditoría por endpoint y reinicio limitado a bitácora. La suite completa pasó 59 pruebas y 1,821 verificaciones, incluido el cierre de la ruta genérica de archivos privados y la transición del esquema de auditoría. No se han ejecutado pruebas que envíen mensajes de correo reales.

Estado al preparar la entrega: MySQL local está detenido y Windows no permitió iniciarlo desde esta sesión. No se aplicaron todavía las migraciones ni el reinicio local. Tampoco se aplicaron cambios ni se borró historial de producción desde aquí. Los pasos anteriores permiten completarlos en el entorno correcto.

## Archivos principales

- Migraciones 2026_11_17_000001_extend_bitacora_details.php, 000002_create_business_catalogs.php y 000003_create_patronage_tables.php.
- BusinessCatalogSeeder, AccessCatalogSeeder y DatabaseSeeder.
- CatalogoNegocioController, PatrocinioController y ajustes a usuarios, autenticación, roles, contraseñas y consulta de bitácora.
- Modelos Embotellador, Patrocinio y sus relaciones; Support CatalogosNegocio/EstadosMexico/CatalogoPermisos.
- Services Bitacora/Imagenes, middleware AuditarSolicitudes, config/filesystems.php, bootstrap/app.php y routes/api.php.
- Comando ReiniciarBitacora y actualización de CrearAdmin para la nueva auditoría.
- Pruebas BusinessApiTest y ampliaciones de las pruebas de auditoría, acceso, usuarios y roles.
- colleccion postman.json, validada con los cuerpos de JSON, scripts de captura y cobertura de rutas sin enviar HTTP.

Se retiraron los ZIP incrementales anteriores porque sus archivos ya no corresponden al nuevo esquema. Se conservaron los documentos anteriores como referencia histórica, marcados por esta entrega. No se borraron archivos subidos por usuarios ni tablas ajenas a bitácora.

## Actualización: eliminación de catálogos

En una instalación con semana 2 aplicada, subir el paquete actualizado y ejecutar `php artisan db:seed --class=AccessCatalogSeeder --force` usando PHP 8.2. No requiere nuevas migraciones ni reiniciar la bitácora. ADMIN recibe los cinco permisos nuevos; otros roles requieren asignación explícita. En esta PC ya se aplicaron las migraciones, los catálogos iniciales y el reinicio autorizado de bitácora. Producción permanece pendiente.
