# Centrix — Referencia de la API

Formato de consulta por endpoint, al estilo Swagger. Actualizado: 23/11/2026. No es una especificación OpenAPI ni instala Swagger en el servidor.

**Servidor:** `https://backend-centrix.podium-sm.com/api`

**Contenido:** 95 operaciones HTTP únicas. La colección tiene 99 peticiones en diecisiete carpetas porque incluye variantes de filtros y omite el alias `/modulos`.

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
      },
      "permisos_extra": []
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
      },
      "permisos_extra": []
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
        },
        "permisos_extra": []
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
    },
    "permisos_extra": []
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
    "acceso_enviado": true,
    "permisos_extra": []
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
    "acceso_enviado": false,
    "permisos_extra": []
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
    },
    "permisos_extra": []
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
    },
    "permisos_extra": []
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
    },
    "permisos_extra": []
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
    "acceso_enviado": true,
    "permisos_extra": []
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
    "acceso_enviado": false,
    "permisos_extra": []
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
        "updated_at": "2026-11-17T18:00:00.000000Z",
        "beneficios_total": 0,
        "beneficios_agotados": 0,
        "avance_beneficios": 0
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
    "updated_at": "2026-11-17T18:00:00.000000Z",
    "beneficios_total": 0,
    "beneficios_agotados": 0,
    "avance_beneficios": 0
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
    "updated_at": "2026-11-17T18:00:00.000000Z",
    "beneficios_total": 0,
    "beneficios_agotados": 0,
    "avance_beneficios": 0
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
    "updated_at": "2026-11-17T18:00:00.000000Z",
    "beneficios_total": 0,
    "beneficios_agotados": 0,
    "avance_beneficios": 0
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
    "updated_at": "2026-11-17T18:00:00.000000Z",
    "beneficios_total": 0,
    "beneficios_agotados": 0,
    "avance_beneficios": 0
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
    "updated_at": "2026-11-17T18:00:00.000000Z",
    "beneficios_total": 0,
    "beneficios_agotados": 0,
    "avance_beneficios": 0
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

## Semana 3 — contratos y reglas compartidas

Los cinco módulos nuevos aportan 19 permisos; el catálogo total tiene 64 permisos en 15 módulos. ADMIN conserva acceso completo, sin arreglo de permisos en login/me. Los demás roles reciben las concesiones agrupadas por módulo. Ningún rol distinto de ADMIN recibe permisos nuevos automáticamente.

### Usuarios: ampliación de POST/PUT y respuestas

`permisos_extra`: arreglo opcional de códigos distintos. Solo admite DOCUMENTOS.DESCARGAR. POST omitido usa []; PUT omitido conserva lo actual, [] quita extras. Se administra con USUARIOS.CREAR/EDITAR, como se acordó; no es exclusivo de ADMIN. Aparece en GET usuarios, detalle y usuario de login/me. Los permisos efectivos son la unión del rol y extras, con rol/módulo/permiso activo y habilidades del token. Los cambios tienen efecto en la siguiente solicitud. Quitar un extra no retira un permiso también concedido por el rol. Cada cambio conserva anterior/nuevo en bitácora.

### Nuevos filtros y resumen de patrocinios

GET /api/patrocinios agrega marca_id (entero positivo), vigencia_desde y vigencia_hasta (YYYY-MM-DD) y orden: nombre, vigencia_inicio, inversion, con prefijo - para descendente. Sin orden: ID descendente. Rango inclusivo por solapamiento: fin >= desde e inicio <= hasta; fechas nulas no cumplen esa comparación. Orden no válido o rango invertido: 422.

Lista y detalle agregan beneficios_total, beneficios_agotados y avance_beneficios. Solo beneficios activos; agotado significa usados=disponibles. Avance es promedio aritmético de porcentajes, redondeado a dos decimales, cero sin activos. No exige CONSULTAR del módulo beneficios para recibir estos indicadores.

### Tipos de respuesta de semana 3

- Evento: id,patrocinio_id,ambito_id,nombre,sede,ciudad,estado_republica,fecha_hora,equipo_local,equipo_visitante,responsable,asistencia_estimada,estado,patrocinio {id,nombre,pilar_id},ambito {id,nombre},marcas [{id,nombre}],marca_ids,imagenes [{id,url}],created_at,updated_at.
- Hito: id,nombre,fecha,tipo,detalles,patrocinio_id,evento_id,activo,patrocinio {id,nombre,pilar_id},evento {id,nombre} o null,created_at,updated_at.
- Beneficio: id,patrocinio_id,tipo_beneficio_id,nombre,disponibles,vigencia_inicio,vigencia_fin,activo,tipo_beneficio {id,nombre},usados,avance,created_at,updated_at.
- Aplicado: id,beneficio_id,nombre,fecha,cantidad,evento {id,nombre} o null,imagen_url,documento_url,documento_nombre,created_at,updated_at.
- Documento: id,nombre,descripcion,categoria,estado,nombre_archivo,tipo_mime,tamano (bytes),patrocinio {id,nombre},evento {id,nombre} o null,autor {id,nombre} o null,created_at,updated_at.
- Configuración: visibilidad_por_embotellador booleano, false inicial.

Listas de eventos,hitos,beneficios y documentos: dataResponse con arreglo de ese nombre y paginacion. Aplicados: {aplicados:[...]} sin paginación, fecha descendente y luego ID descendente. Fechas simples YYYY-MM-DD; fecha_hora se recibe con zona ISO8601 y se devuelve UTC con Z. Creación/actualización de tablas de semana 3 usa marcas de tiempo del servidor America/Mexico_City.

CONSULTAR del propio módulo permite ver todos sus registros aunque no se tenga PATROCINIOS.CONSULTAR. Sin ese permiso: patrocinio publicado de Nacional o ámbitos asignados; eventos usan su propio ámbito, documentos y aplicados ligados a eventos deben cumplir ambos ámbitos. Hitos siguen únicamente al patrocinio. Hitos/beneficios inactivos y documentos retirados solo son visibles con CONSULTAR. Restaurar por permiso RETIRAR/ACTIVAR conserva alcance y no exige CONSULTAR adicional. Archivo o detalle fuera de alcance: 404.

Archivos privados con Bearer; Cache-Control private y no-store. Imágenes de evento: máximo 20; JPG/JPEG/PNG/WebP de hasta 20 MB. Evidencias aplicadas: imagen del mismo tipo, documento PDF o imagen, cada uno hasta 20 MB. Documentos generales también aceptan PPTX, validado como contenedor de presentación además de su extensión. Visor no impide guardar los bytes desde el navegador: DESCARGAR controla la operación explícita y su bitácora.

### Entradas de semana 3

- Evento POST/PUT: obligatorios patrocinio_id,nombre,fecha_hora. Opcionales sede,ciudad,estado_republica (ISO mexicano sin MX-),responsable,asistencia_estimada (entero 0..4294967295),marca_ids distintos,ambito_id (omitido hereda patrocinio),equipo_local/equipo_visitante (ambos o ninguno). Textos máximo 255. PUT reemplaza datos opcionales y marcas. No enviar estado. Cambiar patrocinio se bloquea con 409 si deja hitos,documentos o aplicaciones asociados incompatibles.
- Hito POST/PUT: nombre,fecha YYYY-MM-DD,tipo FECHA_LIMITE/ENTREGA/OTRO,patrocinio_id obligatorios; detalles opcional hasta 20000,evento_id opcional del mismo patrocinio. No enviar activo.
- Beneficio POST/PUT: patrocinio_id,nombre,tipo_beneficio_id activo,disponibles entero 1..4294967295,vigencia_inicio y vigencia_fin obligatorios (YYYY-MM-DD; fin >= inicio). No enviar activo. Se pueden conservar tipos previamente asignados inactivos.
- Aplicado POST: nombre,fecha dentro de vigencia,cantidad entero >=1; evento_id opcional del mismo patrocinio; imagen y documento opcionales. Edición multipart vía POST reemplaza campos de datos; archivos omitidos se conservan; quitar_imagen=1/quitar_documento=1 los retiran. No enviar archivo nuevo y quitarlo en la misma petición. Beneficio inactivo: 409 para registro/edición; aplicados se pueden borrar con REGISTRAR y visibilidad.
- Documento POST multipart: nombre,categoria CONTENIDO_VISUAL/REPORTE_POST_EVENTO/OTRO,patrocinio_id,archivo obligatorios; descripcion hasta 20000 y evento_id opcionales. PUT solo datos y reemplaza opcionales. Cambiar archivo por POST separado.


## Eventos

### GET /api/eventos

Sesión; alcance del módulo.

**Query opcional:** `patrocinio_id`, `pilar_id`, `ambito_id`, `estado`, `desde`, `hasta`, `buscar`, `marca_id`, `orden`, `page`, `per_page`. desde/hasta ISO8601 con zona; estado PROGRAMADO/CANCELADO/REALIZADO; orden fecha_hora o -fecha_hora.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### GET /api/eventos/{id}

Sesión; alcance del módulo.

Ruta: id entero positivo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/eventos

Sesión y permiso EVENTOS.CREAR.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Evento de prueba",
  "sede": "Estadio",
  "ciudad": "Ciudad de México",
  "estado_republica": "CMX",
  "fecha_hora": "{{evento_fecha_hora}}",
  "equipo_local": "Equipo local",
  "equipo_visitante": "Equipo visitante",
  "responsable": "Responsable",
  "asistencia_estimada": 1000,
  "marca_ids": [],
  "ambito_id": "{{embotellador_id}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Evento creado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### PUT /api/eventos/{id}

Sesión y permiso EVENTOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Evento de prueba",
  "sede": "Estadio",
  "ciudad": "Ciudad de México",
  "estado_republica": "CMX",
  "fecha_hora": "{{evento_fecha_hora}}",
  "equipo_local": "Equipo local",
  "equipo_visitante": "Equipo visitante",
  "responsable": "Responsable",
  "asistencia_estimada": 1000,
  "marca_ids": [],
  "ambito_id": "{{embotellador_id}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Evento actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 409 | El evento tiene registros de otro patrocinio y no se puede trasladar. / El evento tiene beneficios de otro patrocinio y no se puede trasladar. |


### PATCH /api/eventos/{id}/estado

Sesión y permiso EVENTOS.CANCELAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "estado": "CANCELADO"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Estado del evento actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |

CANCELADO se conserva aunque pase la fecha; PROGRAMADO en pasado se devuelve REALIZADO. Para volver a PROGRAMADO, restaurar y reprogramar a futuro. REALIZADO se calcula al consultar, no hay actualización automática que genere entradas ficticias de bitácora.


### POST /api/eventos/{id}/imagenes

Sesión y permiso EVENTOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo multipart/form-data** — campos y validaciones en “Entradas de semana 3”.

```text
archivo: <archivo obligatorio>
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Imagen agregada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 409 | El evento admite hasta 20 imágenes. |
| 422 | El contenido y la extensión del archivo no corresponden a un formato permitido. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. (rechazo por tamaño del cuerpo) |


### GET /api/eventos/{id}/imagenes/{imagen_id}

Sesión; alcance del módulo.

Ruta: id entero positivo; imagen_id debe pertenecer al evento.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Bytes del archivo; Content-Type real, sin JSON. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### DELETE /api/eventos/{id}/imagenes/{imagen_id}

Sesión y permiso EVENTOS.EDITAR.

Ruta: id entero positivo; imagen_id debe pertenecer al evento.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Imagen eliminada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Hitos

### GET /api/hitos

Sesión; alcance del módulo.

**Query opcional:** `patrocinio_id`, `evento_id`, `pilar_id`, `activo`, `desde`, `hasta`, `page`, `per_page`. desde/hasta YYYY-MM-DD; activo 1/0.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/hitos

Sesión y permiso HITOS.CREAR.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Entrega de prueba",
  "fecha": "{{fecha}}",
  "tipo": "ENTREGA",
  "detalles": "Detalle",
  "evento_id": "{{evento_id}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Hito creado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### PUT /api/hitos/{id}

Sesión y permiso HITOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Entrega de prueba",
  "fecha": "{{fecha}}",
  "tipo": "ENTREGA",
  "detalles": "Detalle",
  "evento_id": "{{evento_id}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Hito actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### PATCH /api/hitos/{id}/estado

Sesión y permiso HITOS.RETIRAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "activo": false
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Estado del hito actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Beneficios

### GET /api/beneficios

Sesión; alcance del módulo.

**Query opcional:** `patrocinio_id`, `activo`, `page`, `per_page`. 

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/beneficios

Sesión y permiso BENEFICIOS.CREAR.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Boletos",
  "tipo_beneficio_id": "{{tipo_beneficio_id}}",
  "disponibles": 10,
  "vigencia_inicio": "{{vigencia_inicio}}",
  "vigencia_fin": "{{vigencia_fin}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Beneficio creado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### PUT /api/beneficios/{id}

Sesión y permiso BENEFICIOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "patrocinio_id": "{{patrocinio_id}}",
  "nombre": "Boletos",
  "tipo_beneficio_id": "{{tipo_beneficio_id}}",
  "disponibles": 10,
  "vigencia_inicio": "{{vigencia_inicio}}",
  "vigencia_fin": "{{vigencia_fin}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Beneficio actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 422 | No puede ser menor que la cantidad ya utilizada. (errors.disponibles) |
| 422 | La vigencia debe incluir todas las aplicaciones existentes. |
| 409 | El beneficio tiene aplicaciones de eventos de otro patrocinio. |


### PATCH /api/beneficios/{id}/estado

Sesión y permiso BENEFICIOS.ACTIVAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "activo": false
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Estado del beneficio actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Beneficios aplicados

### GET /api/beneficios/{id}/aplicados

Sesión; alcance del módulo.

Ruta: id entero positivo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/beneficios/{id}/aplicados

Sesión y permiso BENEFICIOS.REGISTRAR.

Ruta: id entero positivo.

**Cuerpo multipart/form-data** — campos y validaciones en “Entradas de semana 3”.

```text
nombre: Uso de boletos
fecha: {{fecha}}
cantidad: 1
evento_id: {{evento_id}}
imagen: <archivo opcional>
documento: <archivo opcional>
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Aplicación registrada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 409 | El beneficio está inactivo y no admite aplicaciones. |
| 422 | La cantidad supera los beneficios disponibles. (errors.cantidad) |
| 422 | No envíes un archivo y su eliminación al mismo tiempo. |
| 422 | El contenido y la extensión del archivo no corresponden a un formato permitido. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. (rechazo por tamaño del cuerpo) |


### POST /api/beneficios-aplicados/{id}

Sesión y permiso BENEFICIOS.REGISTRAR.

Ruta: id entero positivo.

**Cuerpo multipart/form-data** — campos y validaciones en “Entradas de semana 3”.

```text
nombre: Uso de boletos
fecha: {{fecha}}
cantidad: 1
evento_id: {{evento_id}}
quitar_imagen: 0
quitar_documento: 0
imagen: <archivo opcional>
documento: <archivo opcional>
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Aplicación actualizada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 409 | El beneficio está inactivo y no admite aplicaciones. |
| 422 | La cantidad supera los beneficios disponibles. (errors.cantidad) |
| 422 | No envíes un archivo y su eliminación al mismo tiempo. |
| 422 | El contenido y la extensión del archivo no corresponden a un formato permitido. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. (rechazo por tamaño del cuerpo) |


### DELETE /api/beneficios-aplicados/{id}

Sesión y permiso BENEFICIOS.REGISTRAR.

Ruta: id entero positivo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Aplicación eliminada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### GET /api/beneficios-aplicados/{id}/imagen

Sesión; alcance del módulo.

Ruta: id entero positivo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Bytes del archivo; Content-Type real, sin JSON. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### GET /api/beneficios-aplicados/{id}/documento

Sesión; alcance del módulo.

Ruta: id entero positivo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Bytes del archivo; Content-Type real, sin JSON. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Documentos

### GET /api/documentos

Sesión; alcance del módulo.

**Query opcional:** `patrocinio_id`, `evento_id`, `categoria`, `estado`, `page`, `per_page`. 

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/documentos

Sesión y permiso DOCUMENTOS.SUBIR.

**Cuerpo multipart/form-data** — campos y validaciones en “Entradas de semana 3”.

```text
nombre: Reporte de prueba
descripcion: Descripción
categoria: REPORTE_POST_EVENTO
patrocinio_id: {{patrocinio_id}}
evento_id: {{evento_id}}
archivo: <archivo obligatorio>
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 201 | Documento subido correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 422 | El contenido y la extensión del archivo no corresponden a un formato permitido. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. (rechazo por tamaño del cuerpo) |


### PUT /api/documentos/{id}

Sesión y permiso DOCUMENTOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "nombre": "Reporte de prueba",
  "descripcion": "Descripción",
  "categoria": "REPORTE_POST_EVENTO",
  "patrocinio_id": "{{patrocinio_id}}",
  "evento_id": "{{evento_id}}"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Documento actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### POST /api/documentos/{id}/archivo

Sesión y permiso DOCUMENTOS.EDITAR.

Ruta: id entero positivo.

**Cuerpo multipart/form-data** — campos y validaciones en “Entradas de semana 3”.

```text
archivo: <archivo obligatorio>
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Archivo reemplazado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |
| 422 | El contenido y la extensión del archivo no corresponden a un formato permitido. |
| 413 | El archivo supera el tamaño permitido. El máximo es 20 MB. (rechazo por tamaño del cuerpo) |


### PATCH /api/documentos/{id}/estado

Sesión y permiso DOCUMENTOS.RETIRAR.

Ruta: id entero positivo.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "estado": "RETIRADO"
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Estado del documento actualizado correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### GET /api/documentos/{id}/archivo

Sesión; alcance del módulo. Descargar explícitamente requiere DOCUMENTOS.DESCARGAR (rol o extra).

Ruta: id entero positivo.

**Query opcional:** `descargar`. descargar=0 (inline, predeterminado) o 1 (attachment).

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Bytes del archivo; Content-Type real, sin JSON. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Configuración

### GET /api/configuracion

Sesión; alcance del módulo.

**Cuerpo:** ninguno.

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Consulta correcta. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


### PUT /api/configuracion

Sesión y permiso CONFIGURACION.EDITAR.

**Cuerpo JSON** — campos y validaciones en “Entradas de semana 3”.

```json
{
  "visibilidad_por_embotellador": false
}
```

**Respuestas**

| HTTP | Mensaje / contenido |
|---|---|
| 200 | Configuración actualizada correctamente. |
| 401 | La sesión no está disponible. Inicia sesión nuevamente. / La sesión expiró por inactividad. Inicia sesión nuevamente. |
| 403 | No tienes permiso para realizar esta acción. / La cuenta no está disponible. |
| 404 | No se encontró el recurso solicitado. (inexistente o fuera de alcance) |
| 422 | Los datos proporcionados no son válidos. Con errors por campo. |
| 400 | Utiliza una conexión HTTPS segura. |
| 500 | Ocurrió un error interno del servidor. |
| 503 | El servicio no está disponible. |


## Ejemplos de respuesta de semana 3

Los ejemplos siguientes corresponden a los tipos de dataResponse indicados en las operaciones anteriores. En listados paginados se colocan en el arreglo del módulo; en aplicados, en {aplicados: [...]}.

### Evento — 201

```json
{
  "statusCode": 201,
  "message": "Evento creado correctamente.",
  "dataResponse": {
    "id": 1,
    "patrocinio_id": 1,
    "ambito_id": 1,
    "nombre": "Evento de prueba",
    "sede": null,
    "ciudad": null,
    "estado_republica": null,
    "equipo_local": null,
    "equipo_visitante": null,
    "responsable": null,
    "asistencia_estimada": null,
    "fecha_hora": "2026-11-25T00:00:00.000000Z",
    "estado": "PROGRAMADO",
    "patrocinio": {
      "id": 1,
      "nombre": "Patrocinio",
      "pilar_id": 1
    },
    "ambito": {
      "id": 1,
      "nombre": "Nacional"
    },
    "marcas": [],
    "marca_ids": [],
    "imagenes": [],
    "created_at": "2026-11-23 18:00:00",
    "updated_at": "2026-11-23 18:00:00"
  },
  "errorMessageDTO": null
}
```

### Hito — 201

```json
{
  "statusCode": 201,
  "message": "Hito creado correctamente.",
  "dataResponse": {
    "id": 1,
    "patrocinio_id": 1,
    "evento_id": null,
    "nombre": "Entrega",
    "fecha": "2026-11-24",
    "tipo": "ENTREGA",
    "detalles": null,
    "activo": true,
    "patrocinio": {
      "id": 1,
      "nombre": "Patrocinio",
      "pilar_id": 1
    },
    "evento": null,
    "created_at": "2026-11-23 18:00:00",
    "updated_at": "2026-11-23 18:00:00"
  },
  "errorMessageDTO": null
}
```

### Beneficio — 201

```json
{
  "statusCode": 201,
  "message": "Beneficio creado correctamente.",
  "dataResponse": {
    "id": 1,
    "patrocinio_id": 1,
    "tipo_beneficio_id": 1,
    "nombre": "Boletos",
    "disponibles": 10,
    "vigencia_inicio": "2026-11-01",
    "vigencia_fin": "2027-12-31",
    "activo": true,
    "tipo_beneficio": {
      "id": 1,
      "nombre": "Físico"
    },
    "usados": 0,
    "avance": 0,
    "created_at": "2026-11-23 18:00:00",
    "updated_at": "2026-11-23 18:00:00"
  },
  "errorMessageDTO": null
}
```

### Aplicado — 201

```json
{
  "statusCode": 201,
  "message": "Aplicación registrada correctamente.",
  "dataResponse": {
    "id": 1,
    "beneficio_id": 1,
    "nombre": "Uso de boletos",
    "fecha": "2026-11-24",
    "cantidad": 1,
    "evento": null,
    "imagen_url": null,
    "documento_url": null,
    "documento_nombre": null,
    "created_at": "2026-11-23 18:00:00",
    "updated_at": "2026-11-23 18:00:00"
  },
  "errorMessageDTO": null
}
```

### Documento — 201

```json
{
  "statusCode": 201,
  "message": "Documento subido correctamente.",
  "dataResponse": {
    "id": 1,
    "nombre": "Reporte",
    "descripcion": null,
    "categoria": "OTRO",
    "estado": "ACTIVO",
    "nombre_archivo": "reporte.pdf",
    "tipo_mime": "application/pdf",
    "tamano": 12000,
    "patrocinio": {
      "id": 1,
      "nombre": "Patrocinio"
    },
    "evento": null,
    "autor": {
      "id": 8,
      "nombre": "Usuario"
    },
    "created_at": "2026-11-23 18:00:00",
    "updated_at": "2026-11-23 18:00:00"
  },
  "errorMessageDTO": null
}
```

### Configuración — 200

```json
{
  "statusCode": 200,
  "message": "Configuración actualizada correctamente.",
  "dataResponse": {
    "visibilidad_por_embotellador": false
  },
  "errorMessageDTO": null
}
```

### Cupo insuficiente — 422

```json
{
  "statusCode": 422,
  "message": "La cantidad supera los beneficios disponibles.",
  "dataResponse": null,
  "errorMessageDTO": {
    "statusCode": 422,
    "message": "La cantidad supera los beneficios disponibles.",
    "errors": {
      "cantidad": [
        "La cantidad supera los beneficios disponibles."
      ]
    }
  }
}
```
