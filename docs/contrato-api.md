# Centrix — Referencia de la API

Formato de consulta por endpoint, al estilo Swagger. Actualizado: 17/11/2026. No es una especificación OpenAPI ni instala Swagger en el servidor.

**Servidor:** `https://backend-centrix.podium-sm.com/api`

**Contenido:** 65 operaciones HTTP únicas en once módulos. La colección tiene 69 peticiones porque incluye variantes de filtros y omite el alias `/modulos`.

## Autenticación y formato

Enviar `Accept: application/json`. Para JSON usar `Content-Type: application/json`. Las rutas protegidas requieren `Authorization: Bearer <token>`. Inactividad: 600 segundos; los éxitos renuevan actividad. El inicio de sesión revoca la sesión anterior. En multipart dejar que el cliente genere Content-Type con su boundary.

Las respuestas JSON tienen `statusCode`, `message`, `dataResponse` y `errorMessageDTO`. En éxito errorMessageDTO es null. En error dataResponse es null y errorMessageDTO contiene statusCode y message; validación puede agregar errors por campo. Ejemplo de error:

```json
{
  "statusCode": 409,
  "message": "No puedes inactivar tu propia cuenta.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "No puedes inactivar tu propia cuenta."
  }
}
```

Los ejemplos son ilustrativos, revisados contra el código local, no capturas de producción. Un GET de imagen exitoso devuelve bytes, sin envoltura JSON. Errores del hosting o la red pueden ocurrir fuera de este contrato. Mensajes dinámicos incluyen nombres o campos concretos.

## Reglas compartidas

Los listados paginados devuelven su arreglo y `paginacion: {pagina, por_pagina, total, ultima_pagina}`. Predeterminado: 20 por página, máximo 100. Los permisos no se paginan.

ADMIN tiene acceso completo. Los demás roles requieren permisos granulares; el token también debe permitirlos. Login y /me omiten permisos para ADMIN; otros roles los reciben agrupados por módulo. El catálogo /permisos describe permisos disponibles, no concesiones del usuario.

Los filtros nunca amplían visibilidad. Catálogos activos son consultables por cualquier sesión; inactivos requieren CONSULTAR. Sin PATROCINIOS.CONSULTAR, solo se ven publicados de Nacional y ámbitos asignados, también en detalle e imágenes. Un registro inaccesible devuelve 404.

La bitácora conserva cambios `[{campo, anterior, nuevo}]`, o []; anterior/nuevo pueden ser null, escalares, arreglos u objetos. IP capturada por el servidor, nullable. usuario_id identifica al autor autenticado; registro_id al objeto afectado. No enviar cambios/ip desde el front. Passwords y tokens no se auditan.

## Índice

- [Login](#login)
- [Usuarios](#usuarios)
- [Roles](#roles)
- [Permisos](#permisos)
- [Bitácora](#bitacora)
- [Pilares](#pilares)
- [Marcas](#marcas)
- [Categorías](#categorias)
- [Tipos de beneficio](#tipos-de-beneficio)
- [Embotelladores](#embotelladores)
- [Patrocinios](#patrocinios)

## Login

### POST /api/login

Público.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| login | string | Sí | Usuario o correo; máximo 255. |
| password | string | Sí | Máximo 1024 caracteres y 72 bytes. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "login": "{{login}}",
  "password": "{{password}}"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Inicio de sesión correcto. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | Credenciales incorrectas. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 423 | La cuenta está bloqueada. Contacta al administrador. |
| 423 | errorMessageDTO.message: La cuenta fue bloqueada por seguridad. |
| 429 | Demasiados intentos. Intenta más tarde. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Inicio de sesión correcto.</summary>

```json
{
  "statusCode": 200,
  "message": "Inicio de sesión correcto.",
  "dataResponse": {
    "token": "TOKEN_DE_EJEMPLO_NO_UTILIZABLE",
    "token_type": "Bearer",
    "expires_in": 600,
    "usuario": {
      "id": 8,
      "nombre": "Usuario",
      "apellido_paterno": "Ejemplo",
      "apellido_materno": null,
      "usuario": "usuario.ejemplo",
      "email": "usuario@example.com",
      "activo": true,
      "bloqueado": false,
      "estado": "ACTIVO",
      "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
      "created_at": "2026-11-17T18:00:00.000000Z",
      "ambitos": [
        {
          "id": 1,
          "nombre": "Nacional"
        }
      ],
      "rol": {
        "id": 1,
        "codigo": "ADMIN",
        "nombre": "Administrador"
      }
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "login": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>

<details>
<summary>423 — La cuenta está bloqueada. Contacta al administrador.</summary>

```json
{
  "statusCode": 423,
  "message": "La cuenta está bloqueada. Contacta al administrador.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 423,
    "message": "La cuenta fue bloqueada por seguridad."
  }
}
```

</details>


### GET /api/me

Sesión Bearer.

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "usuario": {
      "id": 8,
      "nombre": "Usuario",
      "apellido_paterno": "Ejemplo",
      "apellido_materno": null,
      "usuario": "usuario.ejemplo",
      "email": "usuario@example.com",
      "activo": true,
      "bloqueado": false,
      "estado": "ACTIVO",
      "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
      "created_at": "2026-11-17T18:00:00.000000Z",
      "ambitos": [
        {
          "id": 1,
          "nombre": "Nacional"
        }
      ],
      "rol": {
        "id": 1,
        "codigo": "ADMIN",
        "nombre": "Administrador"
      }
    }
  },
  "errorMessageDTO": null
}
```

</details>


### POST /api/logout

Sesión Bearer.

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Sesión cerrada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Sesión cerrada correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Sesión cerrada correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>


### POST /api/forgot-password

Público.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| email | string (email) | Sí | Máximo 255. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "email": "{{email_recuperacion}}"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Si la cuenta está disponible, recibirás un enlace para establecer tu contraseña. |
| 400 | Utiliza una conexión HTTPS segura. |
| 422 | Los datos proporcionados no son válidos. |
| 429 | Demasiados intentos. Intenta más tarde. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Si la cuenta está disponible, recibirás un enlace para establecer tu contraseña.</summary>

```json
{
  "statusCode": 200,
  "message": "Si la cuenta está disponible, recibirás un enlace para establecer tu contraseña.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "email": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/reset-password

Público.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| email | string (email) | Sí | Máximo 255. |
| token | string | Sí | Máximo 255; token vigente del enlace. |
| password | string | Sí | Mínimo 12 caracteres; mayúsculas, minúsculas, números y símbolos; máximo 72 bytes. |
| password_confirmation | string | Sí | Debe coincidir con password. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "email": "{{email_recuperacion}}",
  "token": "{{reset_token}}",
  "password": "{{nueva_password}}",
  "password_confirmation": "{{nueva_password}}"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Contraseña establecida correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 422 | Los datos proporcionados no son válidos. |
| 422 | El enlace no es válido o ya expiró. |
| 429 | Demasiados intentos. Intenta más tarde. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Contraseña establecida correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Contraseña establecida correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "password": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>

<details>
<summary>422 — El enlace no es válido o ya expiró.</summary>

```json
{
  "statusCode": 422,
  "message": "El enlace no es válido o ya expiró.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "El enlace no es válido o ya expiró."
  }
}
```

</details>


## Usuarios

### GET /api/usuarios

Sesión Bearer. Permiso: `USUARIOS.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |
| bloqueado | query | boolean | No | 1 bloqueado; 0 desbloqueado. |
| rol_id | query | integer | No | ID positivo de rol. |
| estado | query | string | No | INACTIVO, BLOQUEADO, PENDIENTE o ACTIVO. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "usuarios": [
      {
        "id": 8,
        "nombre": "Usuario",
        "apellido_paterno": "Ejemplo",
        "apellido_materno": null,
        "usuario": "usuario.ejemplo",
        "email": "usuario@example.com",
        "activo": true,
        "bloqueado": false,
        "estado": "ACTIVO",
        "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
        "created_at": "2026-11-17T18:00:00.000000Z",
        "ambitos": [
          {
            "id": 1,
            "nombre": "Nacional"
          }
        ],
        "rol": {
          "id": 2,
          "codigo": "OPERADOR",
          "nombre": "Operador"
        }
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/usuarios/{id}

Sesión Bearer. Permiso: `USUARIOS.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "ACTIVO",
    "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/usuarios

Sesión Bearer. Permiso: `USUARIOS.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Máximo 255. |
| apellido_paterno | string o null | No | Máximo 255. |
| apellido_materno | string o null | No | Máximo 255. |
| usuario | string | Sí | Máximo 100; letras minúsculas, números, _, . y -; único. |
| email | string (email) | Sí | Máximo 255; único sin distinguir mayúsculas. |
| rol_id | integer | Sí | Rol activo existente. |
| ambito_ids | array<integer> | No | IDs distintos de embotelladores activos; PUT omitido conserva asignaciones. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "{{usuario_nombre}}",
  "apellido_paterno": "{{usuario_apellido_paterno}}",
  "apellido_materno": "{{usuario_apellido_materno}}",
  "usuario": "{{usuario_login}}",
  "email": "{{usuario_email}}",
  "rol_id": {{rol_id}},
  "ambito_ids": {{usuario_ambito_ids}}
}
```

No admite password, activo ni bloqueado en esta operación. La invitación fallida conserva el usuario y responde 201 con acceso_enviado=false.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Usuario creado correctamente. |
| 201 | Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 409 | El rol no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Usuario creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Usuario creado correctamente.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "PENDIENTE",
    "ultimo_acceso_at": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    },
    "acceso_enviado": true
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>201 — Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después.</summary>

```json
{
  "statusCode": 201,
  "message": "Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "PENDIENTE",
    "ultimo_acceso_at": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    },
    "acceso_enviado": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol no está disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol no está disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol no está disponible."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/usuarios/{id}

Sesión Bearer. Permiso: `USUARIOS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Máximo 255. |
| apellido_paterno | string o null | No | Máximo 255. |
| apellido_materno | string o null | No | Máximo 255. |
| usuario | string | Sí | Máximo 100; letras minúsculas, números, _, . y -; único. |
| email | string (email) | Sí | Máximo 255; único sin distinguir mayúsculas. |
| rol_id | integer | Sí | Rol activo existente. |
| ambito_ids | array<integer> | No | IDs distintos de embotelladores activos; PUT omitido conserva asignaciones. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "{{usuario_nombre}}",
  "apellido_paterno": "{{usuario_apellido_paterno}}",
  "apellido_materno": "{{usuario_apellido_materno}}",
  "usuario": "{{usuario_login}}",
  "email": "{{usuario_email_nuevo}}",
  "rol_id": {{rol_id}},
  "ambito_ids": {{usuario_ambito_ids}}
}
```

No admite password, activo ni bloqueado en esta operación. La invitación fallida conserva el usuario y responde 201 con acceso_enviado=false.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Usuario actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | Debe permanecer al menos un administrador disponible. |
| 409 | El rol no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Usuario actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Usuario actualizado correctamente.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "ACTIVO",
    "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — Debe permanecer al menos un administrador disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "Debe permanecer al menos un administrador disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Debe permanecer al menos un administrador disponible."
  }
}
```

</details>

<details>
<summary>409 — El rol no está disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol no está disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol no está disponible."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/usuarios/{id}/estado

Sesión Bearer. Permiso: `USUARIOS.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{usuario_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | No puedes inactivar tu propia cuenta. |
| 409 | Debe permanecer al menos un administrador disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": false,
    "bloqueado": false,
    "estado": "INACTIVO",
    "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — No puedes inactivar tu propia cuenta.</summary>

```json
{
  "statusCode": 409,
  "message": "No puedes inactivar tu propia cuenta.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "No puedes inactivar tu propia cuenta."
  }
}
```

</details>

<details>
<summary>409 — Debe permanecer al menos un administrador disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "Debe permanecer al menos un administrador disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Debe permanecer al menos un administrador disponible."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/usuarios/{id}/desbloquear

Sesión Bearer. Permiso: `USUARIOS.DESBLOQUEAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Cuenta desbloqueada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | Debe permanecer al menos un administrador disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Cuenta desbloqueada correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Cuenta desbloqueada correctamente.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "ACTIVO",
    "ultimo_acceso_at": "2026-11-17T18:00:00.000000Z",
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — Debe permanecer al menos un administrador disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "Debe permanecer al menos un administrador disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Debe permanecer al menos un administrador disponible."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/usuarios/{id}/reenviar-acceso

Sesión Bearer. Permiso: `USUARIOS.REENVIAR_ACCESO`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

No admite password, activo ni bloqueado en esta operación. La invitación fallida conserva el usuario y responde 201 con acceso_enviado=false.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Usuario creado correctamente. |
| 201 | Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El rol no está disponible. |
| 409 | La cuenta no está disponible para recibir un enlace. |
| 422 | Los datos proporcionados no son válidos. |
| 429 | Demasiados intentos. Intenta más tarde. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Usuario creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Usuario creado correctamente.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "PENDIENTE",
    "ultimo_acceso_at": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    },
    "acceso_enviado": true
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>201 — Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después.</summary>

```json
{
  "statusCode": 201,
  "message": "Usuario creado. No se pudo enviar el enlace; puedes reenviarlo después.",
  "dataResponse": {
    "id": 8,
    "nombre": "Usuario",
    "apellido_paterno": "Ejemplo",
    "apellido_materno": null,
    "usuario": "usuario.ejemplo",
    "email": "usuario@example.com",
    "activo": true,
    "bloqueado": false,
    "estado": "PENDIENTE",
    "ultimo_acceso_at": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "ambitos": [
      {
        "id": 1,
        "nombre": "Nacional"
      }
    ],
    "rol": {
      "id": 2,
      "codigo": "OPERADOR",
      "nombre": "Operador"
    },
    "acceso_enviado": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol no está disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol no está disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol no está disponible."
  }
}
```

</details>

<details>
<summary>409 — La cuenta no está disponible para recibir un enlace.</summary>

```json
{
  "statusCode": 409,
  "message": "La cuenta no está disponible para recibir un enlace.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "La cuenta no está disponible para recibir un enlace."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/usuarios/{id}

Sesión Bearer. Permiso: `USUARIOS.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| confirmar | boolean | Sí | true confirma el borrado físico. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "confirmar": true
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Usuario eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | No puedes eliminar tu propia cuenta. |
| 409 | Debe permanecer al menos un administrador disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Usuario eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Usuario eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — No puedes eliminar tu propia cuenta.</summary>

```json
{
  "statusCode": 409,
  "message": "No puedes eliminar tu propia cuenta.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "No puedes eliminar tu propia cuenta."
  }
}
```

</details>

<details>
<summary>409 — Debe permanecer al menos un administrador disponible.</summary>

```json
{
  "statusCode": 409,
  "message": "Debe permanecer al menos un administrador disponible.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Debe permanecer al menos un administrador disponible."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "confirmar": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


## Roles

### GET /api/roles

Sesión Bearer. Permiso: `ROLES.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "roles": [
      {
        "id": 2,
        "codigo": "OPERADOR",
        "nombre": "Operador",
        "activo": true,
        "usuarios_asignados": 1,
        "permisos": [
          {
            "modulo": "USUARIOS",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "desbloquear": false,
              "eliminar": false,
              "reenviar_acceso": false
            }
          },
          {
            "modulo": "ROLES",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "asignar_permisos": false,
              "eliminar": false
            }
          },
          {
            "modulo": "PERMISOS",
            "permisos": {
              "consultar": true
            }
          },
          {
            "modulo": "BITACORA",
            "permisos": {
              "consultar": true
            }
          },
          {
            "modulo": "CATALOGO_EMBOTELLADORES",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "eliminar": false
            }
          },
          {
            "modulo": "CATALOGO_MARCAS",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "eliminar": false
            }
          },
          {
            "modulo": "CATALOGO_PILARES",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "eliminar": false
            }
          },
          {
            "modulo": "CATALOGO_CATEGORIAS",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "eliminar": false
            }
          },
          {
            "modulo": "CATALOGO_TIPOS_BENEFICIO",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "activar": false,
              "eliminar": false
            }
          },
          {
            "modulo": "PATROCINIOS",
            "permisos": {
              "consultar": true,
              "crear": false,
              "editar": false,
              "publicar": false,
              "archivar": false
            }
          }
        ]
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/roles/{id}

Sesión Bearer. Permiso: `ROLES.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 2,
    "codigo": "OPERADOR",
    "nombre": "Operador",
    "activo": true,
    "usuarios_asignados": 1,
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "desbloquear": false,
          "eliminar": false,
          "reenviar_acceso": false
        }
      },
      {
        "modulo": "ROLES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "asignar_permisos": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PERMISOS",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "BITACORA",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "CATALOGO_EMBOTELLADORES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_MARCAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_PILARES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_CATEGORIAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_TIPOS_BENEFICIO",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PATROCINIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "publicar": false,
          "archivar": false
        }
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/roles

Sesión Bearer. Permiso: `ROLES.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| codigo | string | Sí | Máximo 50; se normaliza a mayúsculas; patrón ^[A-Z][A-Z0-9_]*$; único y distinto de ADMIN. |
| nombre | string | Sí | Máximo 255. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "codigo": "{{rol_codigo}}",
  "nombre": "{{rol_nombre}}"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Rol creado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Rol creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Rol creado correctamente.",
  "dataResponse": {
    "id": 2,
    "codigo": "OPERADOR",
    "nombre": "Operador",
    "activo": true,
    "usuarios_asignados": 0,
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "desbloquear": false,
          "eliminar": false,
          "reenviar_acceso": false
        }
      },
      {
        "modulo": "ROLES",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "asignar_permisos": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PERMISOS",
        "permisos": {
          "consultar": false
        }
      },
      {
        "modulo": "BITACORA",
        "permisos": {
          "consultar": false
        }
      },
      {
        "modulo": "CATALOGO_EMBOTELLADORES",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_MARCAS",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_PILARES",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_CATEGORIAS",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_TIPOS_BENEFICIO",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PATROCINIOS",
        "permisos": {
          "consultar": false,
          "crear": false,
          "editar": false,
          "publicar": false,
          "archivar": false
        }
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/roles/{id}

Sesión Bearer. Permiso: `ROLES.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| codigo | string | Sí | Máximo 50; se normaliza a mayúsculas; patrón ^[A-Z][A-Z0-9_]*$; único y distinto de ADMIN. |
| nombre | string | Sí | Máximo 255. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "codigo": "{{rol_codigo}}",
  "nombre": "{{rol_nombre}}"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Rol actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El rol ADMIN está protegido y no se puede modificar ni eliminar. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Rol actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Rol actualizado correctamente.",
  "dataResponse": {
    "id": 2,
    "codigo": "OPERADOR",
    "nombre": "Operador",
    "activo": true,
    "usuarios_asignados": 1,
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "desbloquear": false,
          "eliminar": false,
          "reenviar_acceso": false
        }
      },
      {
        "modulo": "ROLES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "asignar_permisos": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PERMISOS",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "BITACORA",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "CATALOGO_EMBOTELLADORES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_MARCAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_PILARES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_CATEGORIAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_TIPOS_BENEFICIO",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PATROCINIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "publicar": false,
          "archivar": false
        }
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol ADMIN está protegido y no se puede modificar ni eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/roles/{id}/estado

Sesión Bearer. Permiso: `ROLES.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{rol_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado del rol actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El rol ADMIN está protegido y no se puede modificar ni eliminar. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado del rol actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado del rol actualizado correctamente.",
  "dataResponse": {
    "id": 2,
    "codigo": "OPERADOR",
    "nombre": "Operador",
    "activo": true,
    "usuarios_asignados": 1,
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "desbloquear": false,
          "eliminar": false,
          "reenviar_acceso": false
        }
      },
      {
        "modulo": "ROLES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "asignar_permisos": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PERMISOS",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "BITACORA",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "CATALOGO_EMBOTELLADORES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_MARCAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_PILARES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_CATEGORIAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_TIPOS_BENEFICIO",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PATROCINIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "publicar": false,
          "archivar": false
        }
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol ADMIN está protegido y no se puede modificar ni eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/roles/{id}/permisos

Sesión Bearer. Permiso: `ROLES.ASIGNAR_PERMISOS`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| permisos | array<object> | Sí | Reemplazo total; [] quita las concesiones. |
| permisos[].modulo | string | Sí | Código de módulo definido; sin duplicados. |
| permisos[].permisos | object<boolean> | Sí | Acciones en minúsculas con true/false estrictos; ausentes deniegan. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "permisos": [
    {
      "modulo": "USUARIOS",
      "permisos": {
        "consultar": true,
        "crear": false,
        "editar": false,
        "activar": false,
        "desbloquear": false,
        "eliminar": false,
        "reenviar_acceso": false
      }
    },
    {
      "modulo": "ROLES",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "activar": false,
        "asignar_permisos": false,
        "eliminar": false
      }
    },
    {
      "modulo": "PERMISOS",
      "permisos": {
        "consultar": true
      }
    },
    {
      "modulo": "BITACORA",
      "permisos": {
        "consultar": false
      }
    },
    {
      "modulo": "CATALOGO_EMBOTELLADORES",
      "permisos": {
        "consultar": true,
        "crear": false,
        "editar": false,
        "activar": false,
        "eliminar": false
      }
    },
    {
      "modulo": "CATALOGO_MARCAS",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "activar": false,
        "eliminar": false
      }
    },
    {
      "modulo": "CATALOGO_PILARES",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "activar": false,
        "eliminar": false
      }
    },
    {
      "modulo": "CATALOGO_CATEGORIAS",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "activar": false,
        "eliminar": false
      }
    },
    {
      "modulo": "CATALOGO_TIPOS_BENEFICIO",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "activar": false,
        "eliminar": false
      }
    },
    {
      "modulo": "PATROCINIOS",
      "permisos": {
        "consultar": false,
        "crear": false,
        "editar": false,
        "publicar": false,
        "archivar": false
      }
    }
  ]
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Permisos asignados correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El rol ADMIN está protegido y no se puede modificar ni eliminar. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Permisos asignados correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Permisos asignados correctamente.",
  "dataResponse": {
    "id": 2,
    "codigo": "OPERADOR",
    "nombre": "Operador",
    "activo": true,
    "usuarios_asignados": 1,
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "desbloquear": false,
          "eliminar": false,
          "reenviar_acceso": false
        }
      },
      {
        "modulo": "ROLES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "asignar_permisos": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PERMISOS",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "BITACORA",
        "permisos": {
          "consultar": true
        }
      },
      {
        "modulo": "CATALOGO_EMBOTELLADORES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_MARCAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_PILARES",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_CATEGORIAS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "CATALOGO_TIPOS_BENEFICIO",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "activar": false,
          "eliminar": false
        }
      },
      {
        "modulo": "PATROCINIOS",
        "permisos": {
          "consultar": true,
          "crear": false,
          "editar": false,
          "publicar": false,
          "archivar": false
        }
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol ADMIN está protegido y no se puede modificar ni eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "permisos": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/roles/{id}

Sesión Bearer. Permiso: `ROLES.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| confirmar | boolean | Sí | true confirma el borrado físico. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "confirmar": true
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Rol eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El rol ADMIN está protegido y no se puede modificar ni eliminar. |
| 409 | El rol tiene usuarios asignados y no se puede eliminar. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Rol eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Rol eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El rol ADMIN está protegido y no se puede modificar ni eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol ADMIN está protegido y no se puede modificar ni eliminar."
  }
}
```

</details>

<details>
<summary>409 — El rol tiene usuarios asignados y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El rol tiene usuarios asignados y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El rol tiene usuarios asignados y no se puede eliminar."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "confirmar": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


## Permisos

### GET /api/permisos

Sesión Bearer. Permiso: `PERMISOS.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| modulo | query | string | No | Código del módulo; máximo 50 en bitácora. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "permisos": [
      {
        "modulo": "USUARIOS",
        "permisos": {
          "consultar": true,
          "crear": true,
          "editar": true,
          "activar": true,
          "desbloquear": true,
          "eliminar": true,
          "reenviar_acceso": true
        },
        "nombre": "Usuarios"
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


## Bitácora

### GET /api/bitacora

Sesión Bearer. Permiso: `BITACORA.CONSULTAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| usuario_id | query | integer | No | ID positivo del autor autenticado. |
| modulo | query | string | No | Código del módulo; máximo 50 en bitácora. |
| accion | query | string | No | Acción exacta; máximo 50 caracteres. |
| resultado | query | string | No | Resultado exacto, p. ej. EXITOSO, RECHAZADO, BLOQUEADO o ERROR. |
| registro_tipo | query | string | No | Máximo 60 caracteres. |
| registro_id | query | integer | No | ID positivo del registro afectado. |
| fecha_desde | query | string (date) | No | YYYY-MM-DD; desde las 00:00:00. |
| fecha_hasta | query | string (date) | No | YYYY-MM-DD; hasta las 23:59:59; no anterior a fecha_desde. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "registros": [
      {
        "id": 1,
        "usuario_id": 8,
        "usuario_nombre": "Usuario Ejemplo",
        "usuario_login": "usuario.ejemplo",
        "modulo": "PATROCINIOS",
        "accion": "EDITAR",
        "resultado": "EXITOSO",
        "descripcion": "Se actualizó el patrocinio «Patrocinio ejemplo».",
        "created_at": "2026-11-17 18:00:00",
        "registro_tipo": "PATROCINIO",
        "registro_id": 1,
        "cambios": [
          {
            "campo": "inversion",
            "anterior": 500,
            "nuevo": 1000
          }
        ],
        "ip": "192.0.2.10"
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


## Pilares

### GET /api/pilares

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_PILARES.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "pilares": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00",
        "descripcion": null,
        "color": "#64748b",
        "orden": 1,
        "imagen_url": null
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/pilares/{id}

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_PILARES.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "descripcion": null,
    "color": "#64748b",
    "orden": 1,
    "imagen_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/pilares

Sesión Bearer. Permiso: `CATALOGO_PILARES.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| activo | boolean | No | Predeterminado true; editar estado por PATCH separado. |
| descripcion | string o null | No | Máximo 10000. |
| orden | integer | No | Mínimo 0; alta omitida se agrega al final. |
| color | string | No | Hexadecimal #RRGGBB. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Pilar de prueba",
  "descripcion": "Descripción",
  "color": "#165a72",
  "orden": 1
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Pilar creado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Pilar creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Pilar creado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "descripcion": null,
    "color": "#64748b",
    "orden": 1,
    "imagen_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/pilares/{id}

Sesión Bearer. Permiso: `CATALOGO_PILARES.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| descripcion | string o null | No | Máximo 10000. |
| orden | integer | No | Mínimo 0; alta omitida se agrega al final. |
| color | string | No | Hexadecimal #RRGGBB. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Pilar de prueba",
  "descripcion": "Descripción",
  "color": "#165a72",
  "orden": 1
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "descripcion": null,
    "color": "#64748b",
    "orden": 1,
    "imagen_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/pilares/{id}/estado

Sesión Bearer. Permiso: `CATALOGO_PILARES.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{catalogo_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": false,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "descripcion": null,
    "color": "#64748b",
    "orden": 1,
    "imagen_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/pilares/{id}/imagen

Sesión Bearer. Permiso: `CATALOGO_PILARES.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — multipart/form-data**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| archivo | file | Sí | JPG, JPEG, PNG o WebP; hasta 20 MB. |

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen actualizada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Imagen actualizada correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Imagen actualizada correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "descripcion": null,
    "color": "#64748b",
    "orden": 1,
    "imagen_url": "https://backend-centrix.podium-sm.com/api/pilares/1/imagen?v=ejemplo"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "archivo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/pilares/{id}/imagen

Público.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen binaria, no JSON. |
| 400 | Utiliza una conexión HTTPS segura. |
| 404 | No se encontró el recurso solicitado. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

200 — bytes de imagen (image/png, image/jpeg o image/webp).


### PATCH /api/pilares/orden

Sesión Bearer. Permiso: `CATALOGO_PILARES.EDITAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| ids | array<integer> | Sí | Todos los pilares exactamente una vez; el orden del arreglo define el orden final. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "ids": {{pilar_orden_ids}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Orden de pilares actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Orden de pilares actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Orden de pilares actualizado correctamente.",
  "dataResponse": {
    "pilares": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00",
        "descripcion": null,
        "color": "#64748b",
        "orden": 1,
        "imagen_url": null
      }
    ]
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/pilares/{id}

Sesión Bearer. Permiso: `CATALOGO_PILARES.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El registro está en uso y no se puede eliminar. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El registro está en uso y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El registro está en uso y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El registro está en uso y no se puede eliminar."
  }
}
```

</details>


## Marcas

### GET /api/marcas

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_MARCAS.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "marcas": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00",
        "logotipo_url": null
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/marcas/{id}

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_MARCAS.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "logotipo_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/marcas

Sesión Bearer. Permiso: `CATALOGO_MARCAS.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| activo | boolean | No | Predeterminado true; editar estado por PATCH separado. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Marca de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Marca creada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Marca creada correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Marca creada correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "logotipo_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/marcas/{id}

Sesión Bearer. Permiso: `CATALOGO_MARCAS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Marca de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "logotipo_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/marcas/{id}/estado

Sesión Bearer. Permiso: `CATALOGO_MARCAS.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{catalogo_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": false,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "logotipo_url": null
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/marcas/{id}/imagen

Sesión Bearer. Permiso: `CATALOGO_MARCAS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — multipart/form-data**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| archivo | file | Sí | JPG, JPEG, PNG o WebP; hasta 20 MB. |

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen actualizada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Imagen actualizada correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Imagen actualizada correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "logotipo_url": "https://backend-centrix.podium-sm.com/api/marcas/1/imagen?v=ejemplo"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "archivo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/marcas/{id}/imagen

Público.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen binaria, no JSON. |
| 400 | Utiliza una conexión HTTPS segura. |
| 404 | No se encontró el recurso solicitado. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

200 — bytes de imagen (image/png, image/jpeg o image/webp).


### DELETE /api/marcas/{id}

Sesión Bearer. Permiso: `CATALOGO_MARCAS.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El registro está en uso y no se puede eliminar. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El registro está en uso y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El registro está en uso y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El registro está en uso y no se puede eliminar."
  }
}
```

</details>


## Categorías

### GET /api/categorias

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_CATEGORIAS.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "categorias": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00"
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/categorias/{id}

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_CATEGORIAS.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/categorias

Sesión Bearer. Permiso: `CATALOGO_CATEGORIAS.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| activo | boolean | No | Predeterminado true; editar estado por PATCH separado. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Categoría de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Categoría creada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Categoría creada correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Categoría creada correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/categorias/{id}

Sesión Bearer. Permiso: `CATALOGO_CATEGORIAS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Categoría de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/categorias/{id}/estado

Sesión Bearer. Permiso: `CATALOGO_CATEGORIAS.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{catalogo_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": false,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/categorias/{id}

Sesión Bearer. Permiso: `CATALOGO_CATEGORIAS.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El registro está en uso y no se puede eliminar. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El registro está en uso y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El registro está en uso y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El registro está en uso y no se puede eliminar."
  }
}
```

</details>


## Tipos de beneficio

### GET /api/tipos-beneficio

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_TIPOS_BENEFICIO.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "tipos_beneficio": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00"
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/tipos-beneficio/{id}

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_TIPOS_BENEFICIO.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/tipos-beneficio

Sesión Bearer. Permiso: `CATALOGO_TIPOS_BENEFICIO.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| activo | boolean | No | Predeterminado true; editar estado por PATCH separado. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Tipo de beneficio de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Tipo de beneficio creado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Tipo de beneficio creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Tipo de beneficio creado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/tipos-beneficio/{id}

Sesión Bearer. Permiso: `CATALOGO_TIPOS_BENEFICIO.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Tipo de beneficio de prueba"
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/tipos-beneficio/{id}/estado

Sesión Bearer. Permiso: `CATALOGO_TIPOS_BENEFICIO.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{catalogo_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": false,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/tipos-beneficio/{id}

Sesión Bearer. Permiso: `CATALOGO_TIPOS_BENEFICIO.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El registro está en uso y no se puede eliminar. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El registro está en uso y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El registro está en uso y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El registro está en uso y no se puede eliminar."
  }
}
```

</details>


## Embotelladores

### GET /api/embotelladores

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_EMBOTELLADORES.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| activo | query | boolean | No | 1 activo; 0 inactivo. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "embotelladores": [
      {
        "id": 1,
        "nombre": "Registro ejemplo",
        "activo": true,
        "created_at": "2026-11-17 18:00:00",
        "updated_at": "2026-11-17 18:00:00",
        "color": "#64748b",
        "estados": [
          "JAL"
        ],
        "es_nacional": false
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/embotelladores/{id}

Sesión Bearer. Activos para cualquier sesión; inactivos requieren CATALOGO_EMBOTELLADORES.CONSULTAR.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "color": "#64748b",
    "estados": [
      "JAL"
    ],
    "es_nacional": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/embotelladores

Sesión Bearer. Permiso: `CATALOGO_EMBOTELLADORES.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| activo | boolean | No | Predeterminado true; editar estado por PATCH separado. |
| color | string | No | Hexadecimal #RRGGBB. |
| estados | array<string> | No | Códigos ISO de estados mexicanos, sin prefijo MX- y sin duplicados. |
| es_nacional | boolean | No | Solo el registro del sistema puede ser Nacional. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Embotellador de prueba",
  "color": "#4477aa",
  "estados": [
    "JAL",
    "NLE"
  ],
  "es_nacional": false
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Embotellador creado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Embotellador creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Embotellador creado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "color": "#64748b",
    "estados": [
      "JAL"
    ],
    "es_nacional": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/embotelladores/{id}

Sesión Bearer. Permiso: `CATALOGO_EMBOTELLADORES.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Único en el catálogo; máximo 180. |
| color | string | No | Hexadecimal #RRGGBB. |
| estados | array<string> | No | Códigos ISO de estados mexicanos, sin prefijo MX- y sin duplicados. |
| es_nacional | boolean | No | Solo el registro del sistema puede ser Nacional. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Embotellador de prueba",
  "color": "#4477aa",
  "estados": [
    "JAL",
    "NLE"
  ],
  "es_nacional": false
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | La identidad de Nacional está protegida. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": true,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "color": "#64748b",
    "estados": [
      "JAL"
    ],
    "es_nacional": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — La identidad de Nacional está protegida.</summary>

```json
{
  "statusCode": 409,
  "message": "La identidad de Nacional está protegida.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "La identidad de Nacional está protegida."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/embotelladores/{id}/estado

Sesión Bearer. Permiso: `CATALOGO_EMBOTELLADORES.ACTIVAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| activo | boolean o string | Sí | true/false; también activo/inactivo. La validación Laravel acepta 1, 0, "1", "0". |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "activo": {{catalogo_activo}}
}
```

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Estado actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | Nacional no se puede desactivar. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Estado actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Estado actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Registro ejemplo",
    "activo": false,
    "created_at": "2026-11-17 18:00:00",
    "updated_at": "2026-11-17 18:00:00",
    "color": "#64748b",
    "estados": [
      "JAL"
    ],
    "es_nacional": false
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — Nacional no se puede desactivar.</summary>

```json
{
  "statusCode": 409,
  "message": "Nacional no se puede desactivar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Nacional no se puede desactivar."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "activo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### DELETE /api/embotelladores/{id}

Sesión Bearer. Permiso: `CATALOGO_EMBOTELLADORES.ELIMINAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Registro eliminado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | El registro está en uso y no se puede eliminar. |
| 409 | Nacional no se puede eliminar. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Registro eliminado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Registro eliminado correctamente.",
  "dataResponse": null,
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — El registro está en uso y no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "El registro está en uso y no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "El registro está en uso y no se puede eliminar."
  }
}
```

</details>

<details>
<summary>409 — Nacional no se puede eliminar.</summary>

```json
{
  "statusCode": 409,
  "message": "Nacional no se puede eliminar.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "Nacional no se puede eliminar."
  }
}
```

</details>


## Patrocinios

### GET /api/patrocinios

Sesión Bearer. Visibilidad según rol y ámbitos.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| page | query | integer | No | Desde 1; predeterminado 1. |
| per_page | query | integer | No | Entre 1 y 100; predeterminado 20. |
| buscar | query | string | No | Máximo 100 caracteres; búsqueda parcial sin distinguir mayúsculas. |
| pilar_id | query | integer | No | ID positivo de pilar. |
| ambito_id | query | integer | No | ID positivo de embotellador. |
| estado | query | string | No | BORRADOR, PUBLICADO o ARCHIVADO. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "patrocinios": [
      {
        "id": 1,
        "nombre": "Patrocinio ejemplo",
        "descripcion": "Descripción de ejemplo",
        "pilar_id": 1,
        "categoria_id": 1,
        "ambito_id": 1,
        "contraparte": "Empresa ejemplo",
        "alcance": null,
        "ciudad_territorio": "Ciudad de México",
        "responsable": "Responsable ejemplo",
        "estado": "BORRADOR",
        "vigencia_inicio": "2026-11-17",
        "vigencia_fin": "2026-12-31",
        "inversion": 1000,
        "audiencia_promedio": 500,
        "pilar": {
          "id": 1,
          "nombre": "Pilar ejemplo"
        },
        "categoria": {
          "id": 1,
          "nombre": "Categoría ejemplo"
        },
        "ambito": {
          "id": 1,
          "nombre": "Nacional"
        },
        "marcas": [
          {
            "id": 1,
            "nombre": "Marca ejemplo"
          }
        ],
        "marca_ids": [
          1
        ],
        "contactos": [
          {
            "nombre": "Contacto ejemplo",
            "email": "contacto@example.com",
            "rol": "Coordinador"
          }
        ],
        "imagen_url": null,
        "created_at": "2026-11-17T18:00:00.000000Z",
        "updated_at": "2026-11-17T18:00:00.000000Z"
      }
    ],
    "paginacion": {
      "pagina": 1,
      "por_pagina": 20,
      "total": 1,
      "ultima_pagina": 1
    }
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/patrocinios/{id}

Sesión Bearer. Visibilidad según rol y ámbitos.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Consulta correcta. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Consulta correcta.</summary>

```json
{
  "statusCode": 200,
  "message": "Consulta correcta.",
  "dataResponse": {
    "id": 1,
    "nombre": "Patrocinio ejemplo",
    "descripcion": "Descripción de ejemplo",
    "pilar_id": 1,
    "categoria_id": 1,
    "ambito_id": 1,
    "contraparte": "Empresa ejemplo",
    "alcance": null,
    "ciudad_territorio": "Ciudad de México",
    "responsable": "Responsable ejemplo",
    "estado": "BORRADOR",
    "vigencia_inicio": "2026-11-17",
    "vigencia_fin": "2026-12-31",
    "inversion": 1000,
    "audiencia_promedio": 500,
    "pilar": {
      "id": 1,
      "nombre": "Pilar ejemplo"
    },
    "categoria": {
      "id": 1,
      "nombre": "Categoría ejemplo"
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [
      {
        "id": 1,
        "nombre": "Marca ejemplo"
      }
    ],
    "marca_ids": [
      1
    ],
    "contactos": [
      {
        "nombre": "Contacto ejemplo",
        "email": "contacto@example.com",
        "rol": "Coordinador"
      }
    ],
    "imagen_url": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "page": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### POST /api/patrocinios

Sesión Bearer. Permiso: `PATROCINIOS.CREAR`.

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Máximo 255. |
| pilar_id | integer | Sí | Pilar activo; en edición se puede conservar el anterior inactivo. |
| descripcion | string o null | No | Máximo 20000. |
| categoria_id | integer o null | No | Categoría activa; se puede conservar la anterior. |
| ambito_id | integer | No | Embotellador activo; omitido usa Nacional incluso en PUT. |
| marca_ids | array<integer> | No | Marcas activas distintas; PUT reemplaza la lista. |
| contraparte | string o null | No | Máximo 255. |
| alcance | string o null | No | Máximo 255. |
| ciudad_territorio | string o null | No | Máximo 255. |
| responsable | string o null | No | Máximo 255. |
| vigencia_inicio | string (date) o null | No | YYYY-MM-DD. |
| vigencia_fin | string (date) o null | No | YYYY-MM-DD; no anterior al inicio si se proporciona. |
| inversion | number o null | No | 0 a 999999999999.99; máximo dos decimales. |
| audiencia_promedio | number o null | No | 0 a 999999999999.99; máximo dos decimales. |
| contactos | array<object> | No | Hasta 100; PUT reemplaza la lista. |
| contactos[].nombre | string | Sí, por contacto | Máximo 255. |
| contactos[].email | string (email) o null | No | Máximo 255. |
| contactos[].rol | string o null | No | Texto libre; máximo 255. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Patrocinio de prueba",
  "pilar_id": {{pilar_id}}
}
```

No enviar estado ni rutas de imagen. POST crea BORRADOR; PUT reemplaza campos opcionales, marcas y contactos.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 201 | Patrocinio creado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>201 — Patrocinio creado correctamente.</summary>

```json
{
  "statusCode": 201,
  "message": "Patrocinio creado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Patrocinio ejemplo",
    "descripcion": "Descripción de ejemplo",
    "pilar_id": 1,
    "categoria_id": 1,
    "ambito_id": 1,
    "contraparte": "Empresa ejemplo",
    "alcance": null,
    "ciudad_territorio": "Ciudad de México",
    "responsable": "Responsable ejemplo",
    "estado": "BORRADOR",
    "vigencia_inicio": "2026-11-17",
    "vigencia_fin": "2026-12-31",
    "inversion": 1000,
    "audiencia_promedio": 500,
    "pilar": {
      "id": 1,
      "nombre": "Pilar ejemplo"
    },
    "categoria": {
      "id": 1,
      "nombre": "Categoría ejemplo"
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [
      {
        "id": 1,
        "nombre": "Marca ejemplo"
      }
    ],
    "marca_ids": [
      1
    ],
    "contactos": [
      {
        "nombre": "Contacto ejemplo",
        "email": "contacto@example.com",
        "rol": "Coordinador"
      }
    ],
    "imagen_url": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PUT /api/patrocinios/{id}

Sesión Bearer. Permiso: `PATROCINIOS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| nombre | string | Sí | Máximo 255. |
| pilar_id | integer | Sí | Pilar activo; en edición se puede conservar el anterior inactivo. |
| descripcion | string o null | No | Máximo 20000. |
| categoria_id | integer o null | No | Categoría activa; se puede conservar la anterior. |
| ambito_id | integer | No | Embotellador activo; omitido usa Nacional incluso en PUT. |
| marca_ids | array<integer> | No | Marcas activas distintas; PUT reemplaza la lista. |
| contraparte | string o null | No | Máximo 255. |
| alcance | string o null | No | Máximo 255. |
| ciudad_territorio | string o null | No | Máximo 255. |
| responsable | string o null | No | Máximo 255. |
| vigencia_inicio | string (date) o null | No | YYYY-MM-DD. |
| vigencia_fin | string (date) o null | No | YYYY-MM-DD; no anterior al inicio si se proporciona. |
| inversion | number o null | No | 0 a 999999999999.99; máximo dos decimales. |
| audiencia_promedio | number o null | No | 0 a 999999999999.99; máximo dos decimales. |
| contactos | array<object> | No | Hasta 100; PUT reemplaza la lista. |
| contactos[].nombre | string | Sí, por contacto | Máximo 255. |
| contactos[].email | string (email) o null | No | Máximo 255. |
| contactos[].rol | string o null | No | Texto libre; máximo 255. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "nombre": "Patrocinio de prueba",
  "descripcion": "Descripción del patrocinio",
  "pilar_id": {{pilar_id}},
  "categoria_id": {{categoria_id}},
  "marca_ids": [{{marca_id}}],
  "contraparte": "Partner de prueba",
  "alcance": "Nacional",
  "ciudad_territorio": "Ciudad de México",
  "vigencia_inicio": "2026-11-01",
  "vigencia_fin": "2026-12-31",
  "inversion": 1000.25,
  "audiencia_promedio": 500,
  "responsable": "Responsable de prueba",
  "contactos": [{"nombre":"Contacto","email":"contacto@example.com","rol":"Director"}]
}
```

No enviar estado ni rutas de imagen. POST crea BORRADOR; PUT reemplaza campos opcionales, marcas y contactos.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Patrocinio actualizado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Patrocinio actualizado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Patrocinio actualizado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Patrocinio ejemplo",
    "descripcion": "Descripción de ejemplo",
    "pilar_id": 1,
    "categoria_id": 1,
    "ambito_id": 1,
    "contraparte": "Empresa ejemplo",
    "alcance": null,
    "ciudad_territorio": "Ciudad de México",
    "responsable": "Responsable ejemplo",
    "estado": "BORRADOR",
    "vigencia_inicio": "2026-11-17",
    "vigencia_fin": "2026-12-31",
    "inversion": 1000,
    "audiencia_promedio": 500,
    "pilar": {
      "id": 1,
      "nombre": "Pilar ejemplo"
    },
    "categoria": {
      "id": 1,
      "nombre": "Categoría ejemplo"
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [
      {
        "id": 1,
        "nombre": "Marca ejemplo"
      }
    ],
    "marca_ids": [
      1
    ],
    "contactos": [
      {
        "nombre": "Contacto ejemplo",
        "email": "contacto@example.com",
        "rol": "Coordinador"
      }
    ],
    "imagen_url": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "nombre": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### PATCH /api/patrocinios/{id}/estado

Sesión Bearer. Requiere PUBLICAR, ARCHIVAR o EDITAR según el estado destino.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — application/json**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| estado | string | Sí | BORRADOR, PUBLICADO o ARCHIVADO. |

Ejemplo de entrada (las variables se resuelven en Postman):

```json
{
  "estado": "{{patrocinio_estado}}"
}
```

Publicar requiere nombre,pilar,categoría,partner,ciudad/territorio,inicio,fin,inversión,responsable y ámbito. Transiciones: BORRADOR → PUBLICADO; PUBLICADO → BORRADOR/ARCHIVADO; ARCHIVADO → BORRADOR. Repetir estado actual está permitido.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Patrocinio publicado correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 409 | La transición de estado solicitada no está permitida. |
| 422 | Los datos proporcionados no son válidos. |
| 422 | Para publicar falta: partner. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Patrocinio publicado correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Patrocinio publicado correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Patrocinio ejemplo",
    "descripcion": "Descripción de ejemplo",
    "pilar_id": 1,
    "categoria_id": 1,
    "ambito_id": 1,
    "contraparte": "Empresa ejemplo",
    "alcance": null,
    "ciudad_territorio": "Ciudad de México",
    "responsable": "Responsable ejemplo",
    "estado": "PUBLICADO",
    "vigencia_inicio": "2026-11-17",
    "vigencia_fin": "2026-12-31",
    "inversion": 1000,
    "audiencia_promedio": 500,
    "pilar": {
      "id": 1,
      "nombre": "Pilar ejemplo"
    },
    "categoria": {
      "id": 1,
      "nombre": "Categoría ejemplo"
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [
      {
        "id": 1,
        "nombre": "Marca ejemplo"
      }
    ],
    "marca_ids": [
      1
    ],
    "contactos": [
      {
        "nombre": "Contacto ejemplo",
        "email": "contacto@example.com",
        "rol": "Coordinador"
      }
    ],
    "imagen_url": null,
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>409 — La transición de estado solicitada no está permitida.</summary>

```json
{
  "statusCode": 409,
  "message": "La transición de estado solicitada no está permitida.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 409,
    "message": "La transición de estado solicitada no está permitida."
  }
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "estado": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>

<details>
<summary>422 — Para publicar falta: partner.</summary>

```json
{
  "statusCode": 422,
  "message": "Para publicar falta: partner.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Para publicar falta: partner.",
    "errors": {
      "contraparte": [
        "El campo partner es obligatorio para publicar."
      ]
    }
  }
}
```

</details>


### POST /api/patrocinios/{id}/imagen

Sesión Bearer. Permiso: `PATROCINIOS.EDITAR`.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo — multipart/form-data**

| Campo | Tipo | Obligatorio | Regla |
|---|---|---|---|
| archivo | file | Sí | JPG, JPEG, PNG o WebP; hasta 20 MB. |

No enviar estado ni rutas de imagen. POST crea BORRADOR; PUT reemplaza campos opcionales, marcas y contactos.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen actualizada correctamente. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. |
| 422 | Los datos proporcionados no son válidos. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

<details>
<summary>200 — Imagen actualizada correctamente.</summary>

```json
{
  "statusCode": 200,
  "message": "Imagen actualizada correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Patrocinio ejemplo",
    "descripcion": "Descripción de ejemplo",
    "pilar_id": 1,
    "categoria_id": 1,
    "ambito_id": 1,
    "contraparte": "Empresa ejemplo",
    "alcance": null,
    "ciudad_territorio": "Ciudad de México",
    "responsable": "Responsable ejemplo",
    "estado": "BORRADOR",
    "vigencia_inicio": "2026-11-17",
    "vigencia_fin": "2026-12-31",
    "inversion": 1000,
    "audiencia_promedio": 500,
    "pilar": {
      "id": 1,
      "nombre": "Pilar ejemplo"
    },
    "categoria": {
      "id": 1,
      "nombre": "Categoría ejemplo"
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [
      {
        "id": 1,
        "nombre": "Marca ejemplo"
      }
    ],
    "marca_ids": [
      1
    ],
    "contactos": [
      {
        "nombre": "Contacto ejemplo",
        "email": "contacto@example.com",
        "rol": "Coordinador"
      }
    ],
    "imagen_url": "https://backend-centrix.podium-sm.com/api/patrocinios/1/imagen",
    "created_at": "2026-11-17T18:00:00.000000Z",
    "updated_at": "2026-11-17T18:00:00.000000Z"
  },
  "errorMessageDTO": null
}
```

</details>

<details>
<summary>422 — Los datos proporcionados no son válidos.</summary>

```json
{
  "statusCode": 422,
  "message": "Los datos proporcionados no son válidos.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "Los datos proporcionados no son válidos.",
    "errors": {
      "archivo": [
        "Revisa el valor proporcionado."
      ]
    }
  }
}
```

</details>


### GET /api/patrocinios/{id}/imagen

Sesión Bearer. Visibilidad según rol y ámbitos.

**Parámetros**

| Nombre | Ubicación | Tipo | Obligatorio | Regla |
|---|---|---|---|---|
| id | path | integer | Sí | ID positivo del registro. |

**Cuerpo:** no requiere.

**Respuestas: códigos HTTP y mensajes**

| HTTP | Mensaje |
|---|---|
| 200 | Imagen binaria, no JSON. |
| 400 | Utiliza una conexión HTTPS segura. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. |
| 401 | La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. |
| 403 | La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

**Ejemplos de respuesta**

200 — bytes de imagen (image/png, image/jpeg o image/webp).

## Alias de compatibilidad

### GET /api/modulos

Alias de `GET /api/permisos`: sesión Bearer, permiso PERMISOS.CONSULTAR, filtro opcional modulo, misma estructura {permisos:[{modulo,nombre,permisos}]} y mismos códigos y mensajes.
