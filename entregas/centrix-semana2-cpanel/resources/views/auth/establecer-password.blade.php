<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Establece tu contraseña · Centrix</title>
    <style nonce="{{ $nonce }}">
        :root { color-scheme: light; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172b40; background: #f1f5f8; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        main { width: 100%; max-width: 460px; padding: 36px; background: white; border: 1px solid #dde5ec; border-radius: 20px; box-shadow: 0 16px 60px #16334a0b; }
        .brand { margin: 0 0 28px; font-size: 15px; font-weight: 800; letter-spacing: .18em; color: #22675f; }
        h1 { font-size: 28px; line-height: 1.2; margin: 0 0 12px; letter-spacing: -.035em; }
        p { font-size: 14px; line-height: 1.65; color: #53667a; }
        form { margin-top: 26px; }
        label { display: block; margin: 18px 0 8px; font-size: 14px; font-weight: 650; }
        input { width: 100%; border: 1px solid #bccad6; border-radius: 9px; padding: 12px; font: inherit; min-width: 0; }
        input:focus { outline: 3px solid #c1e5df; border-color: #22675f; }
        input[readonly] { background: #f4f7f9; color: #53667a; }
        button { width: 100%; padding: 14px; margin-top: 24px; border: 0; border-radius: 9px; background: #195e55; color: white; font: inherit; font-weight: 650; cursor: pointer; }
        button:disabled { opacity: .65; cursor: wait; }
        .hint { margin: 8px 0 0; font-size: 12px; }
        .notice { padding: 14px; border-radius: 9px; background: #edf5f3; color: #195e55; overflow-wrap: anywhere; }
        .notice.error { background: #fff1ed; color: #923c28; }
        [hidden] { display: none !important; }
        @media (max-width: 480px) { main { padding: 26px 22px; } }
    </style>
</head>
<body>
<main>
    <p class="brand">CENTRIX</p>
    <h1 id="title">Establece tu contraseña</h1>
    <p id="intro">Elige una contraseña para acceder a tu cuenta.</p>
    <p id="message" class="notice" role="status" aria-live="polite" hidden></p>
    <noscript><p class="notice error">Activa JavaScript en tu navegador para establecer tu contraseña.</p></noscript>
    <form id="password-form" hidden>
        <label for="email">Correo electrónico</label>
        <input id="email" type="email" readonly autocomplete="username" required>
        <label for="password">Nueva contraseña</label>
        <input id="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="rules" required>
        <p class="hint" id="rules">Usa al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos. Máximo 72 bytes.</p>
        <label for="confirmation">Confirma tu contraseña</label>
        <input id="confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <button id="submit" type="submit">Guardar contraseña</button>
    </form>
</main>
<script nonce="{{ $nonce }}">
    (() => {
        const params = new URLSearchParams(window.location.search);
        let token = params.get('token') || '';
        const email = params.get('email') || '';
        // El enlace se conserva solo en memoria, nunca en almacenamiento del navegador.
        if (window.location.search) window.history.replaceState(null, '', window.location.pathname);
        const form = document.getElementById('password-form');
        const message = document.getElementById('message');
        const password = document.getElementById('password');
        const confirmation = document.getElementById('confirmation');
        const submit = document.getElementById('submit');
        const notify = (text, error = true) => {
            message.textContent = text;
            message.className = error ? 'notice error' : 'notice';
            message.hidden = false;
        };
        if (!token || token.length > 255 || !email || email.length > 255) {
            notify('Para establecer tu contraseña, abre el enlace completo que recibiste por correo. Si ya venció, solicita uno nuevo.');
            return;
        }
        document.getElementById('email').value = email;
        form.hidden = false;
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (submit.disabled || !form.reportValidity()) return;
            if (password.value !== confirmation.value) {
                notify('Las contraseñas no coinciden.');
                confirmation.focus();
                return;
            }
            if (new TextEncoder().encode(password.value).length > 72) {
                notify('La contraseña supera la longitud permitida de 72 bytes. Usa una más corta.');
                return;
            }
            submit.disabled = true;
            submit.textContent = 'Guardando…';
            message.hidden = true;
            try {
                const response = await fetch({{ Illuminate\Support\Js::from(route('auth.reset-password', [], false)) }}, {
                    method: 'POST',
                    credentials: 'omit',
                    cache: 'no-store',
                    referrerPolicy: 'no-referrer',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, token, password: password.value, password_confirmation: confirmation.value })
                });
                const data = await response.json();
                if (!response.ok) {
                    if (data.errorMessageDTO?.errors?.password) {
                        notify('Revisa la contraseña: necesita al menos 12 caracteres, mayúsculas, minúsculas, números y símbolos.');
                    } else {
                        notify(data.message || 'No se pudo guardar la contraseña. Inténtalo nuevamente.');
                    }
                    return;
                }
                token = '';
                password.value = '';
                confirmation.value = '';
                form.hidden = true;
                document.getElementById('title').textContent = 'Tu contraseña está lista';
                document.getElementById('intro').textContent = 'Ya puedes regresar a Centrix e iniciar sesión con tu nueva contraseña.';
                notify('Contraseña guardada correctamente.', false);
            } catch {
                notify('No se pudo conectar con el servicio. Comprueba tu conexión e inténtalo nuevamente.');
            } finally {
                submit.disabled = false;
                submit.textContent = 'Guardar contraseña';
            }
        });
    })();
</script>
</body>
</html>
