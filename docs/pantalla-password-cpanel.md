# Pantalla de contraseña en cPanel

La pantalla se sirve desde el mismo backend. No requiere Angular, npm, dependencias nuevas ni migraciones adicionales.

1. Respaldar routes/web.php del servidor.
2. Subir por FTP routes/web.php y resources/views/auth/establecer-password.blade.php, conservando sus rutas. Crear resources/views/auth si no existe. Los archivos están en el proyecto backend de desarrollo; copiar las versiones actualizadas si se trabaja desde backend-prod.
3. Configurar en el .env del servidor:

```env
CENTRIX_PASSWORD_URL=https://backend-centrix.podium-sm.com/establecer-password
```

4. Desde la terminal de cPanel:

```bash
cd ~/backend-centrix.podium-sm.com
/opt/cpanel/ea-php82/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan route:clear
/opt/cpanel/ea-php82/root/usr/bin/php artisan view:clear
```

5. Abrir https://backend-centrix.podium-sm.com/establecer-password. Sin token ni correo muestra un aviso para abrir el enlace completo del correo. Es el comportamiento esperado.
6. Crear un usuario nuevo desde Postman con un correo real y rol activo. La invitación llega por SMTP según el .env ya configurado. Si una creación anterior devolvió 503, se revirtió y puede repetirse. Si indica que el usuario ya existe, usar POST /api/usuarios/{id}/reenviar-acceso con un token ADMIN autorizado. El broker limita la repetición de enlaces durante 60 segundos.
7. Abrir el enlace recibido: permite ingresar y confirmar contraseña; llama a POST /api/reset-password del mismo servidor. Al guardar, habilita el login de la cuenta y consume el enlace. No se inicia sesión automáticamente ni se desbloquean/reactivan cuentas.

La pantalla incluye mensajes de enlace incompleto, error, validación y éxito; adapta su ancho a móviles y no requiere recursos externos. El token solo se mantiene en memoria y se quita de la barra de dirección con replaceState; no se guarda en almacenamiento del navegador ni se incluye en HTML generado. El GET no consume ni valida el token para evitar que los escáneres de correo lo inutilicen. Los enlaces conservan el contrato actual con token y email en query; el servidor web puede registrar la URL inicial según su configuración de accesos. Las respuestas usan no-store y no-referrer, y una política de contenido con nonce.

Archivos de pruebas: tests/Feature/PasswordPageTest.php. No se suben tests ni esta guía a la carpeta pública del servidor.
