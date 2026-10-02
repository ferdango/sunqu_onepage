# Formularios y avisos

Qué pasa con cada formulario de la web, cómo hacer que llegue a **sunqujourneys@gmail.com** y cómo recibir, además, una notificación en el celular.

---

## 1. El recorrido de un envío

```
 visitante llena el formulario
      │  POST https://cms.sunqujourneys.com/wp-json/sunqu/v1/formularios/{solicitud|resena|reclamacion}
      ▼
 plugin «Sunqu CMS» (WordPress)
      │ 1. valida el origen (solo la web), el tamaño, los campos obligatorios y el anti-spam
      │    (límite por IP, campo trampa, tiempo mínimo y, si se activa, Cloudflare Turnstile)
      │ 2. lo guarda en el panel
      │ 3. envía un correo HTML a sunqujourneys@gmail.com  ──► «Responder» le escribe directo al visitante
      │ 4. envía a la persona su correo, con la marca y en su idioma (copia de la solicitud, reseña «en revisión»)
      │ 5. (opcional) aviso al celular: Telegram o webhook
      ▼
 la web muestra la confirmación («Añay. Gracias.»)
```

| Formulario | Página | Queda en el panel | Correo que llega |
|---|---|---|---|
| Diseña tu viaje | Inicio, ficha de cada journey, Descubre | **Sunqu › Solicitudes de viaje** | «Nueva solicitud de viaje · Nombre», con sus datos (nombres completos, correo, celular con prefijo, país, cuándo, Mi grupo por habitación, qué quiere sentir, cómo nos encontró), los guardados adjuntos y la página de origen. **La persona recibe una copia completa** («Recibimos tu solicitud de viaje») |
| Comparte tu viaje | Recuerda | **Reseñas › Pendientes**, con sus fotos | «Nueva reseña para revisar · Nombre». La persona recibe «Gracias por enviar tu reseña» (en revisión) y, cuando la **publicas**, «Tu reseña ya es pública» |
| Libro de reclamaciones | /libro-de-reclamaciones | **Sunqu › Libro de reclamaciones** | «Libro de reclamaciones · SJ-2026-00012 · Nombre». Además, el consumidor recibe su **constancia** por correo |

**Dónde queda cada envío:**
- **Siempre en el panel.** Aunque el correo falle, el envío queda guardado.
- **En la ficha de cada envío.** La caja **Aviso a Sunqu** dice si el correo salió, a quién y cuándo, o el error, con un botón **Reenviar el aviso**.
- **En las listas** de solicitudes y reclamaciones, la columna **Aviso por correo** muestra ✔ o ✖.

El destinatario por defecto es **sunqujourneys@gmail.com**. Para cambiarlo o agregar más correos, separados por comas: **Sunqu › Conexión y herramientas › Avisos de los formularios › Correos que reciben los avisos**.

### Correos a la persona que llenó el formulario

| Cuándo | Asunto | Qué incluye |
|---|---|---|
| Envía «Diseña tu viaje» | Recibimos tu solicitud de viaje | Todo lo que envió, el plazo de respuesta (48 horas) y un botón a WhatsApp |
| Envía «Comparte tu viaje» | Gracias por enviar tu reseña | Estado «En revisión», su nombre, país, calificación, comentario y enlace |
| Publicas la reseña en el panel (Pendiente → Publicada) | Tu reseña ya es pública | Estado «Publicada», su comentario y un botón a Recuerda |

- **Con la marca** (logo, franja de colores, pie de Sunqu) y **en el idioma de la página** desde la que se envió (`?lang=`, traducido en Loco › contexto `interfaz`). El aviso de publicación usa el idioma guardado con la reseña.
- **«Responder»** le escribe a Sunqu (el primer correo de los avisos).
- **Límite:** 5 correos por dirección cada 24 horas, para que nadie use la web para llenar el buzón de otra persona. Si se supera, el envío se guarda igual y la ficha lo anota.
- **«Tu reseña ya es pública»** se envía una sola vez; si el correo falló, se reintenta la próxima vez que se publique.
- **Dónde se ve:** la caja «Aviso a Sunqu» de cada solicitud dice si salió la copia; la caja «Permiso de publicación» de cada reseña muestra el correo del autor (no se publica) y si salieron los dos avisos.
- El correo de la reseña se guarda solo en el panel: no es un campo de la reseña y nunca sale por la API.

---

## 2. Activar el correo en producción (unos 15 minutos)

WordPress necesita un buzón que **envíe** los avisos. Sin SMTP, el servidor manda el correo «a su manera» y Gmail suele descartarlo o mandarlo a spam. Hay dos caminos.

### Opción A (recomendada): buzón del dominio en Hostinger

1. **Crea el buzón.** hPanel › **Correos** › dominio `sunqujourneys.com` › **Crear cuenta de correo** › `avisos@sunqujourneys.com`, con una contraseña larga. Solo envía los avisos; nadie tiene que leerlo.
2. **Autenticación del dominio** (para que Gmail confíe). En hPanel › Correos › **Configuración de DNS** o **Autenticación**, confirma que existan:
   - **SPF:** `TXT @ "v=spf1 include:_spf.mail.hostinger.com ~all"`;
   - **DKIM:** el registro que Hostinger genera (botón «Activar DKIM»);
   - **DMARC:** `TXT _dmarc "v=DMARC1; p=none; rua=mailto:sunqujourneys@gmail.com"`.

   Si el DNS del dominio está en Hostinger, se crean con un clic. Si está en otro proveedor (Cloudflare, etc.), copia ahí esos mismos registros.
3. **Conecta WordPress con el buzón.** En el Administrador de archivos del CMS abre `cms.sunqujourneys.com/public_html/wp-config.php` y pega esto **antes** de la línea `/* That's all, stop editing! */`:

   ```php
   // Avisos de los formularios (plugin Sunqu CMS)
   define('SUNQU_SMTP_HOST', 'smtp.hostinger.com');
   define('SUNQU_SMTP_PORT', 465);
   define('SUNQU_SMTP_SECURE', 'ssl');
   define('SUNQU_SMTP_USER', 'avisos@sunqujourneys.com');
   define('SUNQU_SMTP_PASS', 'LA-CONTRASEÑA-DEL-BUZÓN');
   define('SUNQU_MAIL_FROM_NAME', 'Sunqu Journeys');
   ```

   La contraseña vive solo en `wp-config.php`: no en la base de datos ni en el repositorio.
4. **Revisa el destinatario.** **Sunqu › Conexión y herramientas › Avisos de los formularios**:
   - debe decir «✔ Envío por SMTP: smtp.hostinger.com:465»;
   - «Correos que reciben los avisos» vacío equivale a `sunqujourneys@gmail.com`.
5. **Prueba.** Pulsa **Enviar correo de prueba**, abre Gmail y busca «Prueba · avisos de los formularios de Sunqu». Si la primera vez cae en *Spam*, márcalo como **No es spam**.
6. **Filtro en Gmail** (una vez, así nunca se pierde un aviso). Gmail › buscar `from:avisos@sunqujourneys.com` › *Crear filtro*:
   - «No enviar nunca a spam»;
   - «Marcar siempre como importante»;
   - «Aplicar la etiqueta: Web Sunqu».
7. **Prueba real.** Envía «Diseña tu viaje» desde la web. Debe llegar el correo, y la solicitud debe mostrar ✔ en *Sunqu › Solicitudes de viaje*.

### Opción B: enviar con la misma cuenta de Gmail

Sirve si todavía no hay buzón del dominio.
1. En la cuenta **sunqujourneys@gmail.com**, activa la **verificación en 2 pasos** (myaccount.google.com › Seguridad).
2. Crea una **contraseña de aplicación** en myaccount.google.com/apppasswords (nombre: «Sunqu CMS»). Son 16 letras.
3. En `wp-config.php`:

   ```php
   define('SUNQU_SMTP_HOST', 'smtp.gmail.com');
   define('SUNQU_SMTP_PORT', 587);
   define('SUNQU_SMTP_SECURE', 'tls');
   define('SUNQU_SMTP_USER', 'sunqujourneys@gmail.com');
   define('SUNQU_SMTP_PASS', 'xxxx xxxx xxxx xxxx');  // la contraseña de aplicación
   define('SUNQU_MAIL_FROM_NAME', 'Sunqu Journeys');
   ```

4. Pasos 4 a 7 de la opción A.

Límite de Gmail: unos 500 correos por día, sobrado para estos formularios. El remitente será la propia cuenta de Gmail (menos profesional para la **constancia** que recibe el consumidor del libro de reclamaciones). Por eso se recomienda la opción A.

> ¿Ya tienes **WP Mail SMTP** u otro plugin de correo configurado? Entonces no definas `SUNQU_SMTP_HOST`: el plugin Sunqu usa `wp_mail`, así que sus correos salen por ese plugin. No configures los dos a la vez.

---

## 3. Probar en local (Docker)

`cd wordpress && ./setup.sh` levanta, además del CMS y la web, **Mailpit**, un buzón de pruebas que atrapa los correos sin enviarlos a nadie.

1. Abre la web en http://localhost:8081 y envía cualquier formulario.
2. Mira el correo en **http://localhost:8025**.
3. La ficha del envío queda en http://localhost:8080/wp-admin › Sunqu.

---

## 4. Si algo no llega

| Síntoma | Qué revisar |
|---|---|
| La web dice «No pudimos enviar tu solicitud» | La web no llega a WordPress:<br>• `WORDPRESS_API_URL` en el `.env` de la web;<br>• **URL de la web** en Sunqu › Conexión (CORS);<br>• enlaces permanentes en «Nombre de la entrada». |
| El envío está en el panel con ✖ | Lee el error en la caja **Aviso a Sunqu**:<br>• `SMTP connect() failed`: host o puerto (465 con `ssl`, 587 con `tls`);<br>• `Could not authenticate`: usuario o contraseña.<br>Corrige `wp-config.php` y pulsa **Reenviar el aviso**. |
| El correo llega a *Spam* | Registros SPF, DKIM y DMARC (paso A.2) y el filtro de Gmail (A.6). |
| No llega nada ni hay error | Busca en Gmail «Sunqu» en *Todos* y *Spam*. Revisa también «Correos que reciben los avisos». |
| La constancia no le llega al consumidor | La hoja dice «Constancia por correo: No enviada…» cuando ya se enviaron 3 al mismo correo en 24 h (protección anti-abuso). |

---

## 5. Notificaciones push al celular

El correo ya puede avisar en el celular. Para algo **instantáneo y separado del correo** (por ejemplo, que todo el equipo se entere al mismo tiempo), el plugin trae dos canales listos: **Telegram** y **webhook**.

| Opción | Costo | Puesta en marcha | Qué llega al celular | Cuándo conviene |
|---|---|---|---|---|
| **App de Gmail** | Gratis | 2 min, sin programar | La notificación del correo completo | Para empezar hoy |
| **Telegram** ✔ incluido | Gratis | 10 min, sin programar | «Solicitud de viaje · De Ana Pérez» + enlace al panel, a una persona o a un grupo | **Recomendado** para el equipo |
| **Webhook** ✔ incluido → Make, Zapier o n8n | Plan gratis de Make/Zapier; WhatsApp o SMS pagan por mensaje | 30–60 min | Mensaje de WhatsApp, Slack o SMS | Si el equipo vive en WhatsApp |
| WhatsApp Cloud API directo (Meta) | Pago por conversación | Días: verificar Meta Business y aprobar una plantilla | WhatsApp oficial | Más adelante, si se vuelve clave |
| App propia con notificaciones web | Desarrollo | Semanas | — | No hace falta ahora |

### 5.1 Con la app de Gmail (sin configurar nada en la web)

1. Instala **Gmail** en el celular e inicia sesión con sunqujourneys@gmail.com.
2. Gmail › Configuración › la cuenta › **Notificaciones**: «Todas» o «Solo alta prioridad». Con «Solo alta prioridad», los avisos llegan igual si hiciste el filtro «Marcar siempre como importante» (paso A.6).
3. En Android también puedes activar las notificaciones solo para la etiqueta «Web Sunqu»: Configuración › la cuenta › *Administrar etiquetas* › Web Sunqu › *Notificar cada mensaje*.

### 5.2 Con Telegram (incluido en el plugin)

1. **Crea el bot.** En Telegram abre **@BotFather**, escribe `/newbot`, nómbralo (p. ej. «Avisos Sunqu») y elige un usuario que termine en `bot`. BotFather te da un **token** (`123456789:AA…`).
2. **Guárdalo.** Sunqu › Conexión y herramientas › Avisos › **Telegram · token del bot** › pega el token › **Guardar avisos**.
   - Más seguro: ponlo en `wp-config.php` como `define('SUNQU_TELEGRAM_TOKEN', '123456789:AA…');`.
3. **Háblale al bot.**
   - Para una persona: desde el celular abre el bot y escribe `/start`.
   - Para todo el equipo: crea un grupo en Telegram, agrega el bot y escribe cualquier mensaje.
4. **Detecta el chat.** Pulsa **Detectar chat de Telegram**. Si hay un solo chat, se guarda solo; si hay varios, copia el ID que quieras en «Chat ID» y guarda.
5. **Prueba.** Pulsa **Enviar aviso de prueba al celular**.

Desde ahí, cada formulario enviado llega como notificación de Telegram, por ejemplo «**Solicitud de viaje** · De Ana Pérez · Ver en el panel».

### 5.3 Con webhook → WhatsApp, Slack o SMS

En **Sunqu › Conexión › Avisos › Webhook** pega una URL. En cada envío, WordPress le manda:

```json
{ "tipo": "solicitud", "titulo": "Solicitud de viaje", "texto": "De Ana Pérez",
  "url": "https://cms.sunqujourneys.com/wp-admin/post.php?post=123&action=edit", "fecha": "2026-09-28T18:04:11-05:00" }
```

Ejemplo con **Make** (make.com):
1. Escenario nuevo › **Webhooks › Custom webhook**. Copia su URL en Sunqu › Webhook y guarda.
2. Pulsa **Enviar aviso de prueba al celular**: Make aprende la estructura.
3. Agrega el módulo de destino:
   - **WhatsApp Business Cloud › Send a template message** (necesita una cuenta de Meta Business y una plantilla aprobada, p. ej. «Nuevo {{1}} en la web: {{2}}»);
   - **Twilio › Send an SMS / WhatsApp**;
   - **Slack › Create a message**.
4. Activa el escenario.

Zapier («Webhooks by Zapier › Catch Hook») y n8n («Webhook») funcionan igual.

### 5.4 Privacidad

Los avisos al celular llevan solo qué tipo de formulario llegó, el **nombre** de la persona y el enlace al panel. El correo, el teléfono, el mensaje y los datos del libro de reclamaciones solo están en el panel (con usuario y contraseña) y en el correo de Sunqu. Así, un celular perdido o un servicio externo exponen lo mínimo.

Seguridad de los formularios (origen, límites, fotos sin EXIF, Turnstile): [SECURITY.md › 6](SECURITY.md#6-form-security).

---

## 6. Para el desarrollador

- **Código.**
  - `wordpress/sunqu-cms/includes/formularios.php`: endpoints, validación y anti-spam.
  - `wordpress/sunqu-cms/includes/avisos.php`: correo HTML, SMTP, estado del aviso, reenvío, Telegram y webhook.
- **Qué hace falta para que funcione en producción.**
  - **Configuración (fuera del repositorio):** `wp-config.php` del CMS define el SMTP (`SUNQU_SMTP_*`) y, opcionalmente, el token de Telegram (`SUNQU_TELEGRAM_TOKEN`) y el destinatario (`SUNQU_CORREO_AVISOS`).
  - **Destinatarios y canales (desde el panel):** Sunqu › Conexión y herramientas › Avisos de los formularios.
  - **Web:** envía con `SunquContent.enviar(tipo, datos)` (`js/sunqu-content.js`). En producción necesita `WORDPRESS_API_URL`.
- **Idiomas.** Fuera del español, la web agrega `?lang=en` (o el idioma de la página) al envío: los mensajes de error («Falta el campo…», «El celular no es válido»…) y los correos a la persona llegan traducidos con Loco Translate. Lo que se guarda en el panel, los avisos a Sunqu y la constancia del libro de reclamaciones siguen en español.
- **Campos de la web** (`js/sunqu-campos.js`): País (lista con buscador; nombres del navegador en el idioma de la página), Celular (prefijo con bandera, con buscador; banderas de `vendor/TwemojiCountryFlags.woff2` para Windows) y Mi grupo (hasta 6 habitaciones: adultos de 11 años o más, 1–8; niños de 3 a 10, 0–6; bebés de 0 a 2, 0–4). El servidor vuelve a validar y recortar todo.
- **Otro canal.** Para agregar uno (por ejemplo, Slack directo), súmalo en `sunqu_push()`. Recibe tipo, título, texto y URL, sin datos personales.
