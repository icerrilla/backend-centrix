# Centrix: autenticación y administración de usuarios

> Actualización de roles y permisos: los contratos vigentes, el catálogo de 35 permisos, ADMIN protegido, estado unificado y pasos de subida están en [roles-permisos.md](roles-permisos.md). Este documento conserva el contexto de la primera entrega; la actualización sustituye sus referencias a los 17 permisos iniciales y a las rutas separadas activar/inactivar.

## Estado de la entrega

La implementación se ajustó al texto de acuerdos suministrado por el usuario. El 10 de noviembre de 2026 se completaron las siete migraciones y el seeder AccessCatalogSeeder en centrix_db, después de recuperar un fallo de índice causado por el motor MyISAM del servidor. Se verificaron 1 rol ADMIN, 4 módulos, 17 permisos y 17 asignaciones. No se crearon cuentas reales ni se enviaron correos reales. Las pruebas crean y revierten estructuras únicamente en SQLite en memoria y simulan notificaciones.

Validación final: 21 pruebas aprobadas, 237 verificaciones; formato PHP aprobado y composer.json válido. Se revisó el listado de 17 rutas API. Las pruebas cubren conservación de datos durante migración, catálogos repetibles, contrato JSON, sesión única, límite exacto de inactividad, bloqueo, permisos, CRUD, enlaces de un solo uso, protección del último administrador, relaciones restrictivas y bitácora automática.

La revisión inicial confirmó mediante metadatos que centrix_db estaba vacía. Se conservaron intactas las tres migraciones originales. Las cuatro migraciones nuevas se corrigieron antes de aplicarlas; si alguien hubiera aplicado una versión intermedia en otro entorno, debe preparar una migración adicional, nunca reemplazar tablas con datos.

### Recuperación del error 1071

El servidor MySQL 8.4.7 tiene MyISAM como motor predeterminado. El índice único de email con utf8mb4 superó su límite de 1,000 bytes. La revisión del fallo encontró únicamente migrations y users, ambas vacías; users tenía solo su índice primario y la primera migración no estaba registrada.

Se fijó InnoDB en config/database.php para las conexiones mysql y mariadb de la aplicación, sin cambiar el motor global del servidor. Esto también garantiza soporte para transacciones, bloqueos de fila y claves foráneas. No se redujo la longitud de email ni el juego de caracteres.

Se conservó la tabla incompleta mediante RENAME TABLE como users_respaldo_migracion_20261110, se convirtió migrations a InnoDB y se ejecutaron las siete migraciones originales/pendientes y el seeder. Las 15 tablas operativas quedaron en InnoDB; la tabla de respaldo vacía conserva MyISAM. No se borraron tablas ni registros ni se utilizó migrate:fresh. La recuperación comprobó el estado exacto antes de actuar; no debe repetirse como procedimiento general en otras bases.

## Estructura acordada

- Un rol por usuario; rol_id nullable conserva cuentas anteriores sin elevarlas automáticamente a ADMIN.
- Se conserva users.name internamente; la API lo expone como nombre. Se añaden apellido_paterno, apellido_materno, usuario único, activo, bloqueado, intentos_fallidos, bloqueado_at y ultimo_acceso_at.
- requiere_establecer_password bloquea el login de cuentas nuevas hasta usar su enlace de contraseña; las cuentas anteriores conservan el valor false.
- Permisos con modulo_id, accion, codigo único MODULO.ACCION, nombre, descripcion y activo. Cada combinación módulo/acción es única.
- rol_permiso tiene clave compuesta; al eliminar un rol se eliminan sus asignaciones, pero un rol usado por usuarios no puede borrarse. Un módulo usado por permisos tampoco puede borrarse.
- Solo se crea ADMIN en el seeder. No crea usuarios ni contraseñas predeterminadas.
- Bitácora tiene exactamente: id, usuario_id, usuario_nombre, usuario_login, modulo, accion, resultado, descripcion y created_at. No hay IP, user_agent, updated_at, metadata ni columnas de valores anteriores/nuevos.
- usuario_id es el autor autenticado; su nombre e identificador se conservan como texto histórico. Al borrar al autor, usuario_id queda null. En intentos no autenticados se conserva el login enviado sin acreditar identidad. Los nombres de usuarios afectados y cambios relevantes se guardan en descripcion.
- Ejemplo de bloqueo: «Se bloqueó la cuenta por 3 intentos fallidos.» Nunca se registran contraseñas, tokens, cuerpos completos, direcciones IP ni datos internos en bitácora.

## Catálogos iniciales

| Módulo | Permisos |
| --- | --- |
| USUARIOS | CONSULTAR, CREAR, EDITAR, ACTIVAR, INACTIVAR, DESBLOQUEAR, ELIMINAR, REENVIAR_ACCESO |
| ROLES | CONSULTAR, CREAR, EDITAR, ACTIVAR, INACTIVAR, ELIMINAR, ASIGNAR_PERMISOS |
| MODULOS | CONSULTAR |
| BITACORA | CONSULTAR |

Son cuatro módulos y 17 permisos asignados a ADMIN. El seeder puede repetirse sin duplicar registros ni cambiar nombres o estados existentes; vuelve a añadir las asignaciones iniciales de ADMIN que hayan sido retiradas. Usarlo deliberadamente como inicialización, no en cada despliegue.

La matriz del texto también propone CATÁLOGOS, PATROCINIOS, EVENTOS, DOCUMENTOS, RECORDATORIOS, TABLERO, CALENDARIO, MAPA y ASIGNACIÓN TERRITORIAL. Permanecen documentados para etapas de negocio, junto con pilares, marcas, territorios, embotelladores, categorías y asignaciones de visibilidad. No se inventaron sus datos ni se construyeron esos módulos en esta etapa. OPERADOR y CLIENTE tampoco se crean.

El catálogo administrativo de roles y la asignación de permisos ya cuentan con endpoints separados; ver roles-permisos.md. Los códigos técnicos son un catálogo fijo. Por el nuevo acuerdo, ADMIN está protegido y tiene todos sus permisos; el resto de los roles usa asignaciones y exige rol, permiso y módulo activos.

## API disponible después de aplicar el esquema

Todas las respuestas API usan statusCode, message, dataResponse y errorMessageDTO; los errores llevan dataResponse null y los éxitos errorMessageDTO null. Las respuestas incluyen Cache-Control: no-store. Los errores internos no exponen detalles al cliente, incluso con APP_DEBUG habilitado.

Usar Content-Type: application/json y Accept: application/json. En rutas protegidas: Authorization: Bearer TOKEN.

| Método | Ruta | Función |
| --- | --- | --- |
| POST | /api/login | Usuario o correo y contraseña |
| GET | /api/me | Datos de la sesión |
| POST | /api/logout | Revocar sesión |
| POST | /api/forgot-password | Solicitar enlace, respuesta genérica |
| POST | /api/reset-password | Establecer/restablecer contraseña con enlace |
| GET | /api/usuarios | Listar, filtrar y paginar |
| GET | /api/usuarios/{id} | Consultar usuario |
| POST | /api/usuarios | Crear y enviar enlace de acceso |
| PUT | /api/usuarios/{id} | Actualizar datos y rol |
| PATCH | /api/usuarios/{id}/activar | Activar sin desbloquear |
| PATCH | /api/usuarios/{id}/inactivar | Inactivar y revocar acceso |
| PATCH | /api/usuarios/{id}/desbloquear | Reiniciar bloqueo, solo ADMIN con permiso |
| POST | /api/usuarios/{id}/reenviar-acceso | Enviar enlace para establecer contraseña |
| DELETE | /api/usuarios/{id} | Borrado físico; JSON con confirmar=true |
| GET | /api/roles | Catálogo de roles con permisos |
| GET | /api/modulos | Catálogos de módulos y permisos |
| GET | /api/bitacora | Consultar bitácora paginada |

Login: {"login":"jperez","password":"contraseña"}. Devuelve token, token_type=Bearer, expires_in=600 y usuario con nombre, apellidos, usuario, email, activo, bloqueado y rol.

Crear/editar: nombre, apellido_paterno opcional, apellido_materno opcional, usuario, email y rol_id. PUT requiere el conjunto completo; omitir apellidos los deja null. No acepta contraseña ni cambios directos de activo/bloqueado. El rol debe existir y estar activo. Usuario admite letras ASCII, números, punto, guion y guion bajo, sin @ para evitar ambigüedad con el correo. Usuario y correo se normalizan a minúsculas y se valida unicidad sin distinguir mayúsculas.

GET usuarios acepta buscar, activo (0/1), bloqueado (0/1), rol_id, page y per_page (1–100). Devuelve usuarios y paginacion. GET bitacora admite modulo, accion, resultado, page y per_page, y devuelve registros y paginacion.

Reset: email, token, password y password_confirmation. Contraseña mínima de 12 caracteres con mayúsculas, minúsculas, números y símbolos. El enlace expira según auth.passwords.users.expire (60 minutos de origen); solo se guarda su hash y es de un solo uso.

Con el algoritmo bcrypt actual, login y reset rechazan contraseñas de más de 72 bytes para impedir truncamientos silenciosos. Los caracteres multibyte pueden ocupar más de un byte.

Códigos: 200 éxito, 201 creación, 401 credenciales/sesión inválidas, 403 acceso no permitido o cuenta no disponible, 423 bloqueo, 422 validación, 429 límite de solicitudes, 404 inexistente, 409 conflicto o protección de administrador/relaciones, 500 error interno y 503 servicio aún no configurado. HTTP sin TLS cuando se requiere HTTPS recibe 400.

## Reglas implementadas

1. Login transaccional con bloqueo de fila. Al tercer fallo consecutivo de una cuenta activa se persiste el bloqueo y se revocan tokens y sesiones. Un login correcto reinicia intentos. Cuentas inexistentes y contraseñas incorrectas tienen respuesta genérica; el tercer fallo devuelve 423. Una cuenta bloqueada/inactiva solo revela su estado cuando las credenciales son correctas, salvo el propio tercer fallo que acaba de provocar el bloqueo.
2. Nuevo login exitoso revoca los tokens y sesiones anteriores antes de emitir un token Sanctum. Se autentica mediante el modelo de tokens de Sanctum en SesionCentrix; no se acepta una cookie web como sustituto del Bearer. No habilitar otras rutas protegidas solo con auth:sanctum: deben usar centrix.sesion para mantener estas reglas.
3. SesionCentrix verifica last_used_at o created_at ANTES de renovar actividad. A los 600 segundos exactos rechaza y elimina el token; solo respuestas exitosas renuevan la ventana. No hay refresh ni vencimiento absoluto de 10 minutos. La verificación y actualización se serializan con usuario y token bloqueados hasta terminar la petición.
4. Cada solicitud protegida comprueba cuenta activa/no bloqueada, contraseña establecida, rol activo y, cuando procede, permiso, asignación, módulo activo y habilidad del token. Los cambios de permisos se consultan en cada solicitud.
5. Inactivar revoca tokens, sesiones y enlaces de recuperación; conserva al usuario. Activar no desbloquea. Desbloquear no activa ni restablece contraseñas. Cambiar rol, usuario o correo revoca sesiones; cambiar correo invalida su enlace anterior y verificación de correo.
6. Borrado físico con permiso y confirmar=true, sin SoftDeletes. Borra tokens, sesiones y enlaces; conserva bitácora y nombres. Las relaciones restrictivas impiden borrar y revierten toda la operación. Los módulos futuros deben añadir FK restrictivas cuando su información de negocio deba conservarse.
7. Se impide eliminar/inactivar/quitar ADMIN si no queda otro administrador activo, no bloqueado y con contraseña establecida. Las operaciones administrativas se serializan bloqueando primero el rol ADMIN y después actor, token y destino. La concurrencia real aún debe probarse en una copia de MySQL; SQLite no comprueba sus bloqueos de fila.
8. Auditoría automática de peticiones HTTP, incluidas consultas, validaciones, rechazos, rutas inexistentes, límites y errores. Las operaciones autenticadas y login registran dentro de su transacción; el middleware global completa los restantes casos sin duplicar. Los cambios editados llevan valores anteriores y nuevos en descripcion, sin información de contraseña. La bitácora carece de endpoints de edición/borrado.
9. Límite de login: 15 solicitudes/minuto por dirección de origen; enlaces: 5/minuto. También se limita la generación repetida de enlaces por cuenta mediante el broker de contraseñas. La dirección de origen se usa para limitar solicitudes, nunca se guarda en bitácora.
10. Si falta el esquema, API devuelve 503; el sitio inicial sigue disponible. No es posible persistir bitácora antes de crear su tabla o durante una caída de base de datos.

## Configuración pendiente antes de usar invitaciones

- Configurar MAIL_MAILER con un transporte de entrega real y sus credenciales. Se rechaza log y cualquier failover que incluya log, porque escribiría enlaces secretos. array se admite únicamente en pruebas. Si falla el envío al crear una cuenta, su creación se revierte; un error tardío de entrega de correo no puede retirarse del buzón externo, por lo que no deben reintentarse transacciones con envío automáticamente.
- CENTRIX_PASSWORD_URL puede apuntar a la pantalla incluida en el backend: https://backend-centrix.podium-sm.com/establecer-password. Recoge email/token del enlace y envía el reset a la API. HTTPS es obligatorio para el enlace cuando CENTRIX_REQUIRE_HTTPS=true. Ver docs/pantalla-password-cpanel.md para los archivos y pasos de subida por FTP.
- En producción usar APP_ENV=production, APP_DEBUG=false, APP_URL con HTTPS y CENTRIX_REQUIRE_HTTPS=true. Configurar TLS en el servidor. Para desarrollo local por HTTP se puede fijar explícitamente false; no se cambió el .env real.
- Si TLS termina en un proxy, configurar CENTRIX_TRUSTED_PROXIES como lista de sus direcciones separadas por comas. No confiar indiscriminadamente en cabeceras de cualquier cliente. Usar almacenamiento de caché compartido entre instancias para los límites de solicitudes.
- El almacenamiento del token en el cliente sigue pendiente de decidir al construir el frontend. Esta entrega no escribe tokens en localStorage ni implementa una interfaz.

## Revisión antes de aplicar en MySQL

1. Verificar de nuevo la base y el respaldo disponible; la inspección anterior no autoriza sobrescribir tablas que hayan aparecido después.
2. Revisar las cuatro migraciones nuevas, las nueve columnas de bitácora y el catálogo. Comparar esquema real, tipos de claves y nombres de tablas. No se ocultan conflictos con comprobaciones que los salten.
3. Validar en una base MySQL desechable separada: crear esquema y seeders, probar restricciones y solicitudes concurrentes. Las pruebas en memoria no reemplazan esta revisión.
4. Solo después de la revisión aplicar php artisan migrate y, separadamente, php artisan db:seed --class=AccessCatalogSeeder. En centrix_db ambos se completaron el 10 de noviembre de 2026; este paso queda como referencia para otros entornos.
5. Con correo y frontend configurados, crear el primer administrador con php artisan centrix:crear-admin --nombre="Nombre" --usuario="admin" --email="correo autorizado". Envía un enlace y no muestra contraseña ni token en consola. Se niega a crear otro si ya existe ADMIN; las altas siguientes usan el CRUD autorizado. No fue ejecutado contra MySQL.

No usar migrate:fresh, migrate:refresh, db:wipe ni rollback en bases con datos reales. Los down eliminan las nuevas estructuras y sus datos; son para pruebas o reversión evaluada. composer setup también incluye migraciones: no se ejecutó. migrate --pretend puede crear la tabla de control si no existe: no utilizarlo sobre la base real como garantía de cero escrituras.

## Archivos agregados o modificados

- .env.example, config/centrix.php y config/trustedproxy.php: configuración de entorno, HTTPS, proxy y enlaces.
- config/database.php: motor InnoDB explícito para mysql/mariadb, independientemente del predeterminado del servidor.
- composer.json y composer.lock: Sanctum instalado en la etapa anterior.
- app/Models/User.php, Rol.php y Permiso.php: relaciones, permisos y representación pública.
- app/Support/ApiResponse.php: contrato JSON.
- app/Rules/PasswordBytes.php: límite seguro para bcrypt.
- app/Services/Bitacora.php, Sesiones.php y EnlacesPassword.php.
- app/Notifications/EstablecerPassword.php.
- app/Http/Middleware/AuditarSolicitudes.php, RequerirHttps.php y SesionCentrix.php.
- app/Http/Controllers/AuthController.php, PasswordController.php, UsuarioController.php y CatalogoAccesoController.php.
- app/Console/Commands/CrearAdmin.php.
- app/Providers/AppServiceProvider.php, bootstrap/app.php y routes/api.php.
- database/migrations/2026_11_09_000001_create_access_catalogs.php.
- database/migrations/2026_11_09_000002_add_access_fields_to_users.php.
- database/migrations/2026_11_09_000003_create_bitacora_table.php.
- database/migrations/2026_11_09_000004_create_personal_access_tokens_table.php (preparada en la etapa anterior).
- database/seeders/AccessCatalogSeeder.php y DatabaseSeeder.php.
- tests/Feature/AccessSchemaTest.php y CentrixApiTest.php.
- docs/preparacion-accesos.md (este documento).
