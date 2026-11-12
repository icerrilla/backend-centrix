# Roles y permisos de Centrix

## Alcance

CRUD independiente de roles, catálogo fijo de permisos y permisos agrupados por módulo. Se conservaron usuarios, roles, tablas y asignaciones existentes. No se ejecutaron cambios en MySQL local ni en producción: la validación usa SQLite en memoria. Los cinco catálogos de negocio solo tienen sus códigos preparados; sus endpoints CRUD quedan para otra etapa.

Validación final: 35 pruebas aprobadas con 400 verificaciones, formato PHP aprobado y 23 rutas API revisadas. La colección se valida sin enviar peticiones al servidor.

## Contrato de sesión

POST /api/login y GET /api/me mantienen el contrato JSON existente. En dataResponse.usuario, primero aparece rol y después permisos para usuarios que no son ADMIN:

```json
{
  "rol": {"id": 2, "codigo": "OPERADOR", "nombre": "Operador"},
  "permisos": [
    {"modulo": "USUARIOS", "permisos": {"consultar": true, "crear": false, "editar": false, "activar": false, "desbloquear": false, "eliminar": false, "reenviar_acceso": false}},
    {"modulo": "ROLES", "permisos": {"consultar": false, "crear": false, "editar": false, "activar": false, "asignar_permisos": false, "eliminar": false}}
  ]
}
```

El ejemplo abreviado muestra dos módulos; la respuesta real contiene los nueve módulos y sus 35 acciones. Los valores son booleanos JSON, no strings. Una acción falsa o no concedida no autoriza. Los códigos de alta siguen llamándose crear, correspondientes a MODULO.CREAR.

Para ADMIN, se devuelve rol y se omite completamente la clave permisos. ADMIN tiene acceso a todos los códigos definidos en el catálogo fijo, sin depender de las asignaciones editables del rol; las restricciones de cuenta activa/no bloqueada y las habilidades del token siguen aplicando. Los clientes deben reconocer usuario.rol.codigo === "ADMIN" como acceso completo. No se introdujo ningún rol nuevo de forma automática.

Las asignaciones de otros roles son efectivas solo si rol, permiso y módulo están activos. GET /me consulta los cambios actuales, sin necesitar otro login. Las sesiones ya emitidas también comprueban el permiso en cada operación. El frontend debe refrescar /me para reflejar cambios de menús/botones.

## Catálogo fijo

| Módulo | Acciones |
| --- | --- |
| USUARIOS | CONSULTAR, CREAR, EDITAR, ACTIVAR, DESBLOQUEAR, ELIMINAR, REENVIAR_ACCESO |
| ROLES | CONSULTAR, CREAR, EDITAR, ACTIVAR, ASIGNAR_PERMISOS, ELIMINAR |
| PERMISOS | CONSULTAR |
| BITACORA | CONSULTAR |
| CATALOGO_EMPRESAS | CONSULTAR, CREAR, EDITAR, ACTIVAR |
| CATALOGO_MARCAS | CONSULTAR, CREAR, EDITAR, ACTIVAR |
| CATALOGO_PILARES | CONSULTAR, CREAR, EDITAR, ACTIVAR |
| CATALOGO_CATEGORIAS | CONSULTAR, CREAR, EDITAR, ACTIVAR |
| CATALOGO_TIPOS_BENEFICIO | CONSULTAR, CREAR, EDITAR, ACTIVAR |

ROLES.ELIMINAR se agregó tras confirmar que también puede eliminar un usuario de otro rol cuando tenga ese permiso. PERMISOS.CONSULTAR protege el módulo independiente de permisos. No existen rutas para crear, editar ni eliminar códigos de permisos desde el sitio.

El seeder añade los códigos faltantes y los concede a ADMIN. Desactiva códigos y módulos retirados del catálogo, incluyendo USUARIOS.INACTIVAR, ROLES.INACTIVAR y el antiguo MODULOS.CONSULTAR, sin eliminar sus filas ni sus relaciones históricas. No altera nombres/estados de roles existentes, ni concede permisos nuevos a roles distintos de ADMIN. Puede ejecutarse de nuevo sin duplicar registros; reconcilia el catálogo fijo y vuelve a añadir sus asignaciones a ADMIN.

## Rutas

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | /api/roles | ROLES.CONSULTAR |
| GET | /api/roles/{id} | ROLES.CONSULTAR |
| POST | /api/roles | ROLES.CREAR |
| PUT | /api/roles/{id} | ROLES.EDITAR |
| PATCH | /api/roles/{id}/estado | ROLES.ACTIVAR |
| PUT | /api/roles/{id}/permisos | ROLES.ASIGNAR_PERMISOS |
| DELETE | /api/roles/{id} | ROLES.ELIMINAR |
| GET | /api/permisos | PERMISOS.CONSULTAR |
| PATCH | /api/usuarios/{id}/estado | USUARIOS.ACTIVAR |

GET /api/modulos se conserva como alias de /api/permisos y usa PERMISOS.CONSULTAR, con el nuevo formato. El desbloqueo de usuarios ahora se autoriza mediante USUARIOS.DESBLOQUEAR, también para roles distintos de ADMIN.

### Crear y editar roles

```json
{"codigo": "OPERADOR", "nombre": "Operador"}
```

codigo se normaliza a mayúsculas, es único, empieza con letra y admite letras ASCII, números y guion bajo (máximo 50 caracteres). nombre admite hasta 255 caracteres. El rol se crea activo, sin permisos. El estado y los permisos no se aceptan en crear/editar; se usan sus rutas separadas.

GET /roles acepta buscar, activo=0/1, page y per_page de 1 a 100. dataResponse contiene roles y paginacion. Cada rol incluye id, codigo, nombre, activo, usuarios_asignados y permisos agrupados. GET /roles/{id} devuelve ese mismo objeto directamente en dataResponse.

### Una sola ruta de estado

Para usuarios y roles:

```json
{"activo": true}
```

Activa. false inactiva. También se aceptan {"activo":"activo"} y {"activo":"inactivo"}; se rechazan otros textos y la omisión del campo. No hay toggle implícito: repetir una petición mantiene el estado solicitado.

Se retiraron /api/usuarios/{id}/activar y /api/usuarios/{id}/inactivar; el frontend debe usar /estado. USUARIOS.ACTIVAR autoriza ambas operaciones. Activar no desbloquea. Inactivar un rol revoca tokens, sesiones y enlaces de contraseña de todos sus usuarios sin cambiar sus indicadores personales activo/bloqueado. Reactivar el rol permite un nuevo login, sin restaurar tokens viejos.

### Asignar permisos

PUT /roles/{id}/permisos acepta el mismo formato agrupado:

```json
{
  "permisos": [
    {"modulo": "USUARIOS", "permisos": {"consultar": true, "crear": false}},
    {"modulo": "CATALOGO_EMPRESAS", "permisos": {"consultar": true}}
  ]
}
```

Es una sustitución completa. Solo los true se conceden; módulos/acciones omitidos o false se retiran. {"permisos":[]} quita todas las asignaciones del rol. Rechaza módulos/acciones desconocidos, grupos duplicados, permisos inactivos y valores que no sean booleanos JSON; no interpreta el string "false" como true. La validación y el reemplazo se realizan de forma transaccional.

GET /permisos devuelve dataResponse.permisos, con cada módulo, su nombre y el mapa de acciones booleanas. En este catálogo los booleanos representan disponibilidad del código, NO los permisos del usuario autenticado. Para los permisos de un rol usar GET /roles/{id}; para los propios, GET /me. Puede filtrarse con ?modulo=USUARIOS.

### Protecciones y auditoría

- ADMIN no puede renombrarse, inactivarse, eliminarse ni cambiar sus permisos por API.
- DELETE requiere {"confirmar":true}. Si el rol tiene cualquier usuario, incluso inactivo o bloqueado, responde 409: "El rol tiene usuarios asignados y no se puede eliminar." Si no tiene usuarios, elimina el rol y sus asignaciones, conservando bitácora.
- Se conserva la protección del último administrador disponible al editar, inactivar o eliminar usuarios.
- Las operaciones de usuarios y roles se serializan primero con el rol ADMIN para evitar que dos operaciones administrativas retiren a la vez el último acceso. La concurrencia en MySQL debe validarse en un entorno de prueba del hosting; SQLite no prueba sus bloqueos de fila.
- Todas las operaciones tienen la bitácora existente de nueve columnas, con descripciones humanas y cambios de permisos agregados/retirados. No se registran contraseñas ni tokens.
- Los modelos ahora aplican los valores iniciales activo=true y bloqueado=false al crear usuarios, evitando que la respuesta inmediata de creación muestre null mientras la base ya tiene defaults.

## Subida por FTP

El ZIP entregas/roles-permisos-cpanel.zip incluye únicamente los archivos del backend de este ajuste, con rutas relativas para extraer/subir sobre la raíz de Centrix. No incluye vendor, .env, archivos de usuarios, tokens, contraseñas, cachés ni datos de MySQL. La colección Postman se entrega aparte y no necesita subirse al servidor.

1. Respaldar archivos y base de producción. Coordinar la actualización del frontend, porque cambian la estructura de permisos, la respuesta de roles y la ruta de estado.
2. Subir los archivos del ZIP conservando sus rutas. No mezclar con archivos de una copia backend-prod anterior; los cambios se hicieron en backend.
3. En la terminal de cPanel, desde la carpeta donde está artisan:

```bash
cd ~/backend-centrix.podium-sm.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan db:seed --class=AccessCatalogSeeder --force
/opt/cpanel/ea-php82/root/usr/bin/php artisan optimize
```

No se necesitan migraciones nuevas ni Composer: se reutilizan las tablas existentes. No ejecutar migrate:fresh ni importar la base local sobre producción.

4. Reimportar colleccion postman.json en Postman. Contiene 25 peticiones, estado unificado y carpetas Roles/Permisos independientes. Crear rol guarda rol_objetivo_id para probar las siguientes operaciones, sin sustituir el rol_id del usuario de ejemplo Jared ni el token administrativo.
5. Comprobar login ADMIN sin permisos, CRUD de un rol de prueba, asignaciones agrupadas, login/me del usuario de ese rol y denegación de acciones false. Eliminar el rol de prueba solo si ya no tiene usuarios. No enviar las peticiones de eliminación sobre roles reales como parte de una ejecución masiva.

## Archivos del backend incluidos en el ZIP

- app/Support/CatalogoPermisos.php
- app/Support/EstadoCuenta.php
- app/Models/User.php
- app/Models/Rol.php
- app/Models/Permiso.php
- app/Http/Controllers/AuthController.php
- app/Http/Controllers/UsuarioController.php
- app/Http/Controllers/CatalogoAccesoController.php
- app/Http/Controllers/RolController.php
- app/Http/Controllers/PermisoController.php
- app/Http/Middleware/SesionCentrix.php
- app/Services/Bitacora.php (revisión posterior: registros de errores y reversión de transacciones)
- database/seeders/AccessCatalogSeeder.php
- routes/api.php

Pruebas agregadas/ampliadas: RolePermissionTest, AccessSchemaTest y CentrixApiTest. Los cambios previos de pantalla de contraseña (routes/web.php y la vista Blade) se conservan y no forman parte de este ZIP incremental.
