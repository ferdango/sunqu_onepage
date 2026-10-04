# Sunqu Journeys · Seguridad

**Security hardening implementado según las medidas descritas, con los riesgos residuales documentados.**
Ningún sitio es invulnerable: este documento dice qué se protegió, cómo se probó, qué depende de la configuración de
Hostinger y de las cuentas, y qué queda pendiente.

Revisión: 29 de septiembre de 2026, sobre el repositorio completo (web, capa PHP, plugin y tema de WordPress,
Docker, herramientas e historial de Git) y sobre lo que responden hoy `sunqujourneys.com` y `cms.sunqujourneys.com`.

---

## 1. Security architecture

```
Visitante ──HTTPS──► CDN de Hostinger ──► LiteSpeed ──► web (public_html)
                                                        ├─ archivos estáticos (HTML de plantillas, CSS, JS, imágenes)
                                                        └─ server/index.php  (páginas, sitemap, robots, /_sunqu/*)
                                                              │  GET servidor → servidor, caché de 5 min
                                                              ▼
Navegador ──POST formularios (CORS: solo la web)──► cms.sunqujourneys.com /wp-json/sunqu/v1/…  (WordPress headless)
WordPress ──POST + token──► web /_sunqu/purge          WordPress ──► SMTP (avisos) · Telegram / webhook (opcionales)
```

| Pieza | Qué expone | Datos que guarda |
|---|---|---|
| Web (`sunqujourneys.com`) | Páginas (GET/HEAD), `sitemap.xml`, `robots.txt`, `/_sunqu/purge` (POST + token), `/_sunqu/csp` (POST, reportes), `/_sunqu/salud` (GET) | Ninguno personal. No tiene base de datos ni sesiones. Cookies propias, solo funcionales y sin datos personales: `sunqu_language` (el idioma que eligió la persona, 1 año) y `sunqu_lang_src` (cómo se eligió el idioma en la raíz, 2 minutos, para la medición). La preferencia de cookies va en `localStorage` |
| CMS (`cms.sunqujourneys.com`) | Panel (`/wp-admin`, `/wp-login.php`), `/wp-json/sunqu/v1/contenido` (lectura pública, solo lo publicado), `/wp-json/sunqu/v1/formularios/*` (POST público con anti-spam), `/wp-content/uploads` (medios) | Contenido, solicitudes de viaje, reseñas pendientes y el libro de reclamaciones (datos personales) |

**Límites de confianza:**
- Para la web, lo que llega de WordPress **no es confiable**: se sanea en el servidor y en el navegador, y todo texto se escapa al mostrarse. Las traducciones de Loco Translate también son texto: se escapan al insertarlas en la página.
- **Excepción deliberada:** los códigos de **Sunqu › Scripts y seguimiento** (GTM, Analytics, Pixel…) se ejecutan tal cual en la web. Es una decisión de confianza en los administradores del CMS, con sus controles (sección 7 › Scripts del panel).
- Para WordPress, lo que llega por los formularios **no es confiable**: se valida y se limpia en el servidor.
- El único secreto compartido entre la web y el CMS es el token de purga.

## 2. Protecciones implementadas

| Riesgo | Medida | Dónde |
|---|---|---|
| **XSS** | React escapa todo texto. El servidor escapa con `htmlspecialchars` el `<head>` y el bloque sin JavaScript. El JSON incrustado usa `JSON_HEX_TAG`, así que un «`</script>`» no corta el script. URLs `javascript:`, `vbscript:` y `data:` que no sean imagen → `#`, en el servidor y en el navegador. CSP **sin `'unsafe-inline'` en scripts**: solo corren los dos scripts en línea de la web, autorizados por su hash SHA-256 | `server/lib/content.php` › `sunqu_sanear`, `js/sunqu-content.js` › `sanear`, `server/lib/security.php`, `page.php` › `sunqu_hashes_en_linea` |
| **CSRF** | La web no tiene sesiones ni cookies de autenticación. La purga exige un token. Las acciones del panel llevan nonce y capacidad. La API de WordPress con cookie exige nonce (núcleo). Los formularios son públicos, sin autoridad de sesión, y además rechazan otros orígenes | `server/index.php`, plugin `admin.php`, `avisos.php`, `formularios.php` |
| **Inyección SQL** | La web no usa base de datos. El plugin usa las funciones de WordPress; la única consulta directa (código correlativo del libro) va con `$wpdb->prepare` | `formularios.php` › `sunqu_codigo_reclamacion` |
| **Inyección de comandos** | No hay `exec`, `shell_exec`, `system` ni similares en el código que corre en el servidor (solo en herramientas locales de `tools/`) | revisado con búsqueda en todo el repositorio |
| **Path traversal** | Plantillas y secciones de contenido de listas fijas. Slugs validados con `[a-z0-9-]`. Ningún nombre de archivo sale del visitante: las fotos se guardan como `resena-{id}-{n}` | `routes.php`, `content.php`, `formularios.php` |
| **Open redirect** | Toda redirección es `SITE_URL` + la ruta normalizada: `//otro.sitio/` se trata como una ruta y responde 404. WordPress usa `wp_safe_redirect` | `routes.php`, `index.php` |
| **SSRF** | La web solo pide a `WORDPRESS_API_URL` (configuración): http/https, hasta 3 redirecciones, 5 MB. El webhook del CMS usa `wp_safe_remote_post` (no sigue a IPs internas) y exige `https://` | `content.php` › `sunqu_http_get`, `avisos.php` |
| **Prototype pollution** | El saneador del navegador descarta las claves `__proto__`, `constructor` y `prototype` del contenido | `js/sunqu-content.js` |
| **Clickjacking** | `X-Frame-Options: SAMEORIGIN` y `frame-ancestors 'self'` en la web. El panel ya los trae (núcleo) | `.htaccess`, `security.php` |
| **MIME sniffing** | `X-Content-Type-Options: nosniff` en la web y en el CMS | `.htaccess`, `seguridad.php` |
| **IDOR** | Ningún endpoint devuelve registros por ID. Las respuestas de los formularios ya no incluyen el ID interno. Los envíos son privados y solo los ven Editores y Administradores. `/wp/v2` está cerrado sin sesión | `formularios.php`, `seguridad.php` |
| **Exposición de información** | En producción no se muestran errores: 500 genérica y el detalle va al log. Sin `X-Powered-By`. Archivos internos, copias y volcados → 404 (no se confirma que existan). Sin versión de WordPress. Usuarios no enumerables. Índice de la API cerrado. `readme.txt` del plugin bloqueado | `security.php`, `.htaccess`, `server/500.html`, `seguridad.php`, `wordpress/sunqu-cms/.htaccess` |
| **Secretos** | `.env` fuera de `public_html` y fuera de Git. Tokens que el panel no vuelve a mostrar. SMTP y Turnstile en `wp-config.php`. Historial revisado (sección 9) | `.gitignore`, `admin.php` |
| **Abuso / fuerza bruta** | Formularios: 8 envíos cada 10 min por IP y tipo. Acceso: 5 intentos fallidos por IP en 15 min. Constancias: 3 por correo cada 24 h. Logs de CSP y purga: tope por minuto | `formularios.php`, `seguridad.php`, `security.php` |

## 3. WordPress hardening

**Lo hace el plugin «Sunqu CMS» 1.3.0 (`includes/seguridad.php`), sin plugins extra:**
- **XML-RPC** apagado: responde 403. Tampoco hay pingbacks ni cabecera `X-Pingback`.
- **Comentarios** apagados en todo el sitio: tampoco se aceptan por `wp-comments-post.php`, y se quitan del menú.
- **API REST sin sesión:** solo `/sunqu/v1/contenido` y `/sunqu/v1/formularios/*` (filtro `rest_pre_dispatch` con prioridad 999, después de Secure Custom Fields; sección 15).
  - Piden sesión: `/wp-json/` (índice), `/wp/v2/*` (usuarios, entradas, medios, búsqueda…), oEmbed, `wp-site-health`, `wp-block-editor`, `batch` y `/sunqu/v1/esquema`.
  - El panel y el editor de bloques funcionan igual, porque usan la sesión.
  - Las rutas de otros plugins (p. ej. LiteSpeed) validan sus propios permisos.
- **Enumeración de usuarios cerrada:**
  - `?author=1` ya no redirige a `/author/{usuario}/` (lo atiende el plugin con prioridad 0 y el tema con prioridad 1);
  - `/wp/v2/users` pide sesión;
  - el acceso responde siempre «el usuario o la contraseña no son correctos»;
  - «¿Olvidaste tu contraseña?» responde igual exista o no la cuenta.
- **Límite de intentos de acceso:** 5 fallos por IP en 15 minutos → 15 minutos de espera, incluso con la contraseña correcta.
  - Detrás de la CDN de Hostinger, `REMOTE_ADDR` ya es la IP real del visitante ([Hostinger: Visitor IP addresses behind Hostinger CDN](https://www.hostinger.com/support/hostinger-cdn-visitor-ip-addresses-in-logs-and-analytics/)).
  - Con otro proxy delante, se ajusta con el filtro `sunqu_ip_cliente`.
- **Contraseñas de aplicación** apagadas, porque ninguna integración las usa. `define('SUNQU_APP_PASSWORDS', true);` las activa si algún día hace falta una integración autenticada. Así, la contraseña principal nunca tiene que ir en un frontend.
- **Editor de archivos del panel** apagado (`DISALLOW_FILE_EDIT`).
- **Biblioteca:**
  - solo imágenes, video y PDF (sin HTML, SVG, JS ni otros tipos);
  - `wp-content/uploads/.htaccess` impide ejecutar PHP y otros scripts (se crea al activar el plugin y al entrar al panel).
- **La carpeta del plugin no se sirve por la web** (`.htaccess` con `Require all denied`): ni `readme.txt`, ni `schema.json`, ni el contenido de `datos/`.
- **Sin versión visible:** sin `generator` y con `?ver=` de núcleo reemplazado por una huella.
- **Mínimo privilegio:**
  - las solicitudes, el libro de reclamaciones y los ajustes del sitio (Sitio, Inicio, Legales…) solo los ven y editan Editores y Administradores;
  - Conexión y Seguridad, solo Administradores.
- **Scripts y seguimiento (1.4.0):** solo administradores con `unfiltered_html` pueden guardarlos (nonce + capacidad); cada cambio queda en un historial (quién, cuándo, qué) y se avisa por correo a los destinatarios de los avisos. `define('DISALLOW_UNFILTERED_HTML', true);` en `wp-config.php` bloquea el panel para todos.
- **Página Sunqu › Seguridad:** revisión de solo lectura. Muestra:
  - versiones de WordPress, PHP y plugins, y actualizaciones pendientes;
  - plugins y temas sin usar;
  - administradores y nombres obvios;
  - registro abierto, HTTPS y errores en pantalla;
  - `debug.log`, permisos de `wp-config.php` y protección de la biblioteca;
  - URL de la web, token de purga, SMTP y Turnstile;
  - cuántos scripts de Scripts y seguimiento están activos y cuántos cargan sin pedir permiso.

**Lo hace el administrador (una vez, en el panel o en hPanel):**
1. **Actualizar** WordPress, plugins y temas. Encender las actualizaciones automáticas de seguridad: Plugins › «Activar actualizaciones automáticas» en los que se usan.
2. **Borrar lo que no se usa.** Hoy están instalados Hostinger AI Assistant y Hostinger Easy Onboarding: bórralos si no se usan. Deja un solo tema de respaldo.
3. **Usuarios:**
   - una cuenta por persona;
   - rol **Editor** para quien solo edita contenido;
   - contraseñas largas y únicas;
   - **2FA** con el plugin «Two Factor» (de los colaboradores de WordPress);
   - borrar las cuentas que no se usan.
4. **`wp-config.php`:**
   - permisos 600 o 640;
   - `WP_DEBUG` en `false`;
   - `define('FORCE_SSL_ADMIN', true);`;
   - claves y *salts* únicas (si se filtraron, generar nuevas en api.wordpress.org/secret-key/1.1/salt).
5. **Ajustes:**
   - direcciones con `https://`;
   - «Cualquiera puede registrarse» desmarcado;
   - Lectura › «Disuadir a los motores de búsqueda» marcado.
6. **Opcional:** borrar `readme.html` y `license.txt` de la raíz del CMS. Muestran que es WordPress y vuelven con cada actualización.

## 4. Hostinger protections

No se asume qué trae el plan. Lo verificado desde fuera el 29/09/2026:

| Qué | Estado | Qué hacer |
|---|---|---|
| **SSL** | Let's Encrypt en `sunqujourneys.com` + `www` (vence 02/11/2026) y `cms.sunqujourneys.com` (vence 27/12/2026). Hostinger las renueva sola | Confirmar la renovación automática en hPanel › Seguridad › SSL |
| **HTTPS** | La web fuerza HTTPS y quita `www` (`.htaccess`). HSTS de 1 año (sin `includeSubDomains` hasta confirmar que todo subdominio tiene certificado) | Opcional: «Forzar HTTPS» en hPanel |
| **CDN de Hostinger** | Activa en los dos dominios (`server: hcdn`). Pasa la IP real (`REMOTE_ADDR`) y `X-Forwarded-Proto` | «Modo bajo ataque» solo durante un ataque: bloquea también a Google ([Hostinger: crawlers y SEO](https://www.hostinger.com/support/hostinger-cdn-search-engine-crawlers-and-seo/)). No bloquear países donde están los buscadores (EE. UU.) |
| **PHP** | El CMS corría PHP 8.3 (ya no se anuncia en las cabeceras) | Web y CMS en PHP 8.3 (hPanel › Avanzado › Configuración de PHP) |
| **Firewall / ModSecurity / escáner de malware** | No verificable desde fuera | En hPanel › Seguridad: confirmar qué ofrece el plan, activar el escáner de malware y sus avisos por correo. No desactivar ModSecurity sin una razón técnica |
| **Copias de seguridad** | No verificable desde fuera | Ver sección 10 |
| **Acceso al panel** | — | 2FA en la cuenta de Hostinger. No compartir la contraseña: usar «Acceso de colaboradores» si otra persona necesita entrar |
| **SSH / FTP** | — | Desactivar SSH si no se usa. Borrar cuentas FTP que no se usen. Preferir SFTP |
| **Dominio** (Namecheap) | DNS en Hostinger (`ns1/ns2.dns-parking.com`) | En Namecheap: bloqueo de transferencia, 2FA, renovación automática y privacidad de WHOIS |
| **DNS de correo** | SPF `include:_spf.mail.hostinger.com ~all` ✔, DKIM `hostingermail-a` ✔, DMARC `p=none` | Tras unas semanas de monitoreo, pasar DMARC a `p=quarantine` y agregar `rua=` con un buzón de reportes |
| **CAA** | Sin registro | Opcional: `0 issue "letsencrypt.org"`, solo si Hostinger sigue usando Let's Encrypt (hoy sí) |
| **DNSSEC** | No activo | Activar si Hostinger lo ofrece para dominios con sus nameservers y registrar el DS en Namecheap |

## 5. API security

| Endpoint | Método | Acceso | Validación y límites | Respuesta |
|---|---|---|---|---|
| `web /_sunqu/purge` | POST (otro → 405) | Token `CACHE_PURGE_TOKEN` en `X-Sunqu-Token`, comparación en tiempo constante (`hash_equals`) | Fallos registrados en el log, con tope por minuto | `{ok}` con `no-store` y `noindex` |
| `web /_sunqu/csp` | POST | Público (reportes del navegador) | Máx. 16 KB y 5 reportes por petición; 20 líneas de log por minuto | 204 |
| `web /_sunqu/salud` | GET/HEAD | Público | Sin datos internos: solo el origen del contenido y su antigüedad | 200 o 503 |
| Páginas de la web | GET/HEAD (otro → 405) | Público | `q` hasta 100 caracteres; `cat` solo slug; ruta hasta 2048 bytes, sin caracteres de control ni UTF-8 inválido | HTML |
| `cms /sunqu/v1/contenido[?secciones=]` | GET | Público | Secciones con `sanitize_key`, máx. 20. **Solo lo publicado**: nada de borradores, reseñas pendientes, envíos ni metadatos privados | JSON con caché; `noindex` |
| `cms /sunqu/v1/formularios/{tipo}` | POST | Público, con anti-spam | Sección 6 | `{ok}` (reclamación: `{ok, codigo}`) |
| `cms /sunqu/v1/esquema` | GET | Sesión con `edit_posts` | — | Esquema del contenido |
| `cms /wp-json/`, `/wp/v2/*`, oEmbed… | — | Sesión | — | 401 sin sesión |

**CORS:**
- La API solo acepta, desde el navegador, a la web: Sunqu › Conexión › URL de la web u Orígenes permitidos; si están vacíos, `https://sunqujourneys.com`.
- Nunca responde a «cualquier origen» ni con credenciales.
- El propio CMS (panel y editor) siempre puede.

**Errores:** mensajes genéricos, sin rutas, SQL ni trazas.

## 6. Form security

Formularios: **Diseña tu viaje** (inicio, journey, Descubre), **Comparte tu viaje** (Recuerda, con hasta 4 fotos) y **Libro de reclamaciones**. No se agregaron ni quitaron campos.

| Capa | Medida |
|---|---|
| Origen | Desde un navegador, solo la web (403 para otro sitio): nadie puede usar los formularios desde otra página, p. ej. para mandar constancias a terceros |
| Tamaño | Envío máx. 16 MB (413). Cada texto con longitud máxima (500 o 5000 caracteres; guardados 3000) |
| Validación en el servidor | Obligatorios iguales a los del formulario, `is_email`, `sanitize_text_field` / `sanitize_textarea_field`, enlace con `esc_url_raw`. La validación del navegador es solo una ayuda |
| Bots | Campo trampa invisible (`website`), tiempo mínimo de llenado (2,5 s), 8 envíos cada 10 min por IP y tipo. Un bot recibe «ok» y no se procesa; una hoja de reclamación dudosa queda en borrador «[posible spam]» sin correos, por si fuera una persona |
| **Turnstile** (opcional) | Cloudflare Turnstile, invisible salvo que Cloudflare pida un clic. Se activa con `TURNSTILE_SITE_KEY` en el `.env` de la web **y** `define('SUNQU_TURNSTILE_SECRET', '…')` en `wp-config.php`: las dos o ninguna. Si Cloudflare no responde, el envío pasa y queda en el log, para no perder una reclamación. PENDIENTE DE CONFIGURACIÓN |
| Fotos | Solo JPG, PNG o WebP de hasta 4 MB y 3000 px por lado, validadas por su contenido real, no por la extensión. Se vuelven a codificar sin EXIF ni GPS. El nombre lo pone el servidor. Nunca se sube un ejecutable |
| Correo | Constancia del libro: máx. 3 por dirección cada 24 h. Cabeceras sin saltos de línea. HTML del aviso escapado |
| Datos personales | Se guardan como envíos privados (Editores y Administradores). **Nunca van a GA4, GTM ni Meta**: la medición solo recibe el tipo de formulario (`form_name`) y, en un journey, su slug. Los avisos al celular llevan el nombre, no el correo ni el mensaje |

**Conservación:** el libro de reclamaciones tiene un plazo legal de conservación (reglamento de INDECOPI; confirmar el plazo vigente con el asesor legal). Las solicitudes de viaje pueden borrarse cuando ya no se necesiten.

## 7. Headers

| Cabecera | Web | CMS |
|---|---|---|
| `Content-Security-Policy` | Sí (ver abajo) | No: el panel de WordPress necesita scripts en línea |
| `Strict-Transport-Security` | `max-age=31536000` (con HTTPS) | Ídem (plugin) |
| `X-Content-Type-Options` | `nosniff` | `nosniff` |
| `X-Frame-Options` / `frame-ancestors` | `SAMEORIGIN` / `'self'` | `SAMEORIGIN` (núcleo, panel y acceso) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Ídem |
| `Permissions-Policy` | Sin cámara, micrófono, ubicación, pagos, USB ni Topics | Ídem |
| `Cross-Origin-Opener-Policy` | `same-origin-allow-popups` (compartir en redes sigue abriendo ventanas) | — |
| `X-Powered-By` / `Server` | Quitado / `ServerSignature Off` | Quitado |
| `X-Robots-Tag` | `noindex` en JSON, `/_sunqu/*` y staging | `noindex` en la API |

**Hostinger pisa la CSP del servidor** (verificado el 02/10/2026):
- agrega `Content-Security-Policy: upgrade-insecure-requests` a todas las respuestas (HTML, CSS, 404 y también el CMS), y ese encabezado reemplaza el que envía PHP. Con la versión publicada hasta hoy, la web **no tenía CSP efectiva**;
- por eso la web repite su política en un `<meta http-equiv="Content-Security-Policy">`, la primera etiqueta del `<head>`. El navegador aplica las dos y suma sus restricciones. Probado reproduciendo el encabezado de Hostinger: un script inyectado y un enlace `javascript:` quedan bloqueados, y la página funciona;
- en `<meta>` no rigen `frame-ancestors` ni los reportes: el enmarcado lo sigue impidiendo `X-Frame-Options`, y los reportes llegan solo si Hostinger deja pasar el encabezado;
- probablemente lo agrega la opción de HTTPS / contenido mixto de hPanel o de la CDN. Si se encuentra y se desactiva, la CSP completa volverá a llegar también como encabezado (ya incluye `upgrade-insecure-requests`). No hace falta para que la protección funcione.

**CSP de la web** (`server/lib/security.php`):
- **Base:**
  - `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`, `upgrade-insecure-requests`;
  - `script-src 'self' 'sha256-…' 'unsafe-eval'`. El runtime de plantillas compila con `new Function`, por eso `'unsafe-eval'`. Los scripts en línea van por hash, no con `'unsafe-inline'`;
  - reportes a `/_sunqu/csp`.
- **Dominios que se agregan solos según la configuración:**

| Servicio | Directivas |
|---|---|
| Fotos de Unsplash | `img-src https://images.unsplash.com` |
| WordPress (`WORDPRESS_API_URL`) | `img-src`, `media-src` y `connect-src` del CMS |
| Instagram / TikTok (reels de Recuerda) | `frame-src https://www.instagram.com https://www.tiktok.com` |
| GA4 o GTM | `script-src https://www.googletagmanager.com https://*.googletagmanager.com`; `img-src`/`connect-src` `https://*.google-analytics.com https://analytics.google.com https://*.analytics.google.com https://*.googletagmanager.com https://*.g.doubleclick.net https://www.google.com https://www.google.com.pe` |
| Vista previa de GTM | `https://tagmanager.google.com`, `https://fonts.googleapis.com`, `https://fonts.gstatic.com`, `https://ssl.gstatic.com`, `https://www.gstatic.com` |
| Meta Pixel | `script-src https://connect.facebook.net`; `img-src`/`connect-src https://www.facebook.com https://connect.facebook.net` |
| Turnstile | `script-src` y `frame-src https://challenges.cloudflare.com` |

- **GTM con etiquetas «HTML personalizado»:** necesita scripts en línea. Configura el Pixel y GA4 con las plantillas de la galería de GTM; si igual hace falta HTML personalizado, `CSP_INLINE_SCRIPTS=allow` vuelve a `'unsafe-inline'`, con menos protección contra XSS.
- **Un servicio nuevo:** su dominio en WordPress › Sunqu › Scripts y seguimiento › **Dominios adicionales** (o en `sunqu_csp()`), y prueba con `CSP_MODE=report` antes de `enforce`.

**Scripts del panel (Sunqu › Scripts y seguimiento):**
- **Qué se autoriza:** los scripts en línea de cada código, por su hash SHA-256 (no `'unsafe-inline'`); los dominios que aparecen en el código; los «Dominios adicionales» (validados: `https://` o `wss://`, con comodín opcional; `wss://` solo en `connect-src`); y, si el código es de Google o de Meta, sus dominios conocidos de la tabla de arriba.
- **Qué no cambia:** un script inyectado por un atacante en el contenido sigue bloqueado (no tiene un hash autorizado), y los enlaces `javascript:` siguen saneados.
- **El riesgo que se acepta:** un administrador (o alguien que robe su sesión) puede publicar JavaScript en la web. Controles: capacidad `unfiltered_html`, historial, aviso por correo de cada cambio, fila en Sunqu › Seguridad y el interruptor **`CMS_SCRIPTS=off`** en el `.env` de la web, que ignora todos los scripts del panel sin tocar WordPress.
- **Privacidad:** los de «analítica» y «marketing» no se ejecutan sin permiso (llegan como `type="text/plain"`); sus `<noscript>`, `<img>` e `<iframe>` se quitan. Lo que se pega es público: nunca contraseñas ni claves secretas.

## 8. Authentication

- **Web:**
  - no tiene cuentas;
  - el único acceso privilegiado es la purga, con un token de 48 caracteres aleatorios que no sale del servidor ni del panel;
  - el frontend nunca recibe credenciales: `SUNQU_CONFIG` solo lleva IDs públicos.
- **WordPress:** una cuenta por persona y mínimo privilegio:

| Rol | Puede |
|---|---|
| Administrador | Todo, incluidas Conexión y Seguridad. Idealmente una o dos personas |
| Editor | Journeys, Diario, reseñas, ajustes del sitio, solicitudes y libro de reclamaciones |
| Autor / Colaborador | Solo sus entradas del Diario (no ve ajustes ni envíos) |

- **Protección del acceso:** 2FA recomendada (plugin «Two Factor»), intentos limitados, mensajes sin pistas y contraseñas de aplicación apagadas.
- **Si una integración necesita WordPress con autenticación:** contraseña de aplicación de un usuario con el rol mínimo, guardada en el servidor de la integración, nunca en JavaScript ni en Git.
- **Nunca** se comparten credenciales ni se guardan contraseñas en el código, GitHub, JavaScript, el `.env` público o la documentación.

## 9. Secrets

| Secreto | Dónde vive | Rotación |
|---|---|---|
| `CACHE_PURGE_TOKEN` | `.env` de la web (fuera de `public_html`) y Sunqu › Conexión (no se vuelve a mostrar) | `openssl rand -hex 24`; cambiarlo en los dos sitios a la vez |
| Base de datos del CMS | `wp-config.php` | hPanel › Bases de datos › cambiar contraseña, y actualizar `wp-config.php` |
| SMTP (`SUNQU_SMTP_PASS`) | `wp-config.php` | Cambiar la contraseña del buzón en hPanel › Correo y en `wp-config.php` |
| Bot de Telegram | `wp-config.php` (`SUNQU_TELEGRAM_TOKEN`, recomendado) o Sunqu › Conexión (no se vuelve a mostrar) | @BotFather › `/revoke` |
| `SUNQU_TURNSTILE_SECRET` | `wp-config.php` | Panel de Cloudflare › Turnstile › rotar la clave |
| Claves del Docker local | `wordpress/.env` (aleatorias, las crea `setup.sh`; ignorado por Git) | Borrar la línea y volver a correr `setup.sh` |

**Revisión del repositorio** (29/09/2026, 16 commits, todo el historial):
- no hay claves de API, tokens, contraseñas reales, claves privadas ni archivos `.env`, `.sql`, `.pem` o de respaldo;
- las únicas contraseñas versionadas eran las de ejemplo del Docker local (`wordpress/.env.example`, solo `127.0.0.1`). Ya no están: `setup.sh` genera valores aleatorios;
- `.gitignore` cubre `.env*`, volcados SQL, respaldos, logs, claves y `node_modules`;
- `dist/` (paquetes y `env-produccion.txt` con el token real) está ignorado y nunca se subió.

**Si un secreto se filtra** (en Git, un chat o una captura): se da por comprometido y **se rota**. Borrarlo del historial no alcanza.

## 10. Backups

Un respaldo vale solo si se probó restaurarlo.

| Qué | Dónde está el original | Cómo se respalda |
|---|---|---|
| Código de la web | GitHub (`ferdango/sunqu_onepage`) | Cada «publicar». Hostinger lo despliega desde Git |
| `.env` de la web | `~/domains/sunqujourneys.com/.env` | Copia cifrada fuera del servidor (gestor de contraseñas) |
| Base de datos del CMS (contenido **y envíos**) | MySQL de Hostinger | Copias automáticas de Hostinger (frecuencia según el plan: verificar) + exportación manual mensual desde phpMyAdmin |
| Medios del CMS | `wp-content/uploads` | Copias de Hostinger + descarga manual |
| Contenido en JSON | CMS | Sunqu › Conexión › «Descargar todo el contenido (JSON)», o `wp sunqu exportar` |
| `wp-config.php` | Raíz del CMS | Copia cifrada fuera del servidor |

**Prueba de restauración (cada 3 meses):**
1. Crear un subdominio de prueba (p. ej. `restauracion.sunqujourneys.com`).
2. Restaurar ahí la copia de archivos y la base de datos desde hPanel › Copias de seguridad.
3. Comprobar el acceso al panel, el libro de reclamaciones y `/wp-json/sunqu/v1/contenido`.
4. Borrar el subdominio.

**Antes de cualquier cambio grande** (borrar una base de datos, actualizar muchos plugins, importar): generar una copia manual.

## 11. Monitoring

| Qué | Cómo se ve | Estado |
|---|---|---|
| Web caída o con errores 500 | Monitor externo de `https://sunqujourneys.com/` | PENDIENTE: UptimeRobot o Better Stack (gratis), cada 5 min, alerta por correo o WhatsApp |
| WordPress caído o la API fallando | `GET https://sunqujourneys.com/_sunqu/salud` → 503 si la web vive de la copia vencida o del respaldo | Implementado. Falta crear el monitor externo |
| Formularios fallando | Cada ficha guarda si el aviso por correo salió; Sunqu › Conexión muestra el último error de correo | Implementado. Probar con «Enviar correo de prueba» |
| Errores de la web | `error_log` de PHP: `[sunqu] …` (WordPress sin respuesta, excepciones, purgas rechazadas, violaciones de CSP) | Implementado. hPanel › Avanzado › Registros de errores |
| Cambios inesperados en la web | La web se despliega desde Git: cualquier archivo distinto al del repositorio es sospechoso | Revisar el despliegue en hPanel › GIT después de cada «publicar» |
| Malware | Escáner de Hostinger | PENDIENTE: activar y confirmar los avisos |
| Tráfico anómalo | Analítica y registros de acceso de hPanel; niveles de seguridad de la CDN | Revisar ante picos |
| Indexación y seguridad para Google | Search Console (problemas de seguridad, cobertura) | PENDIENTE: dar de alta la propiedad (ver SEO.md) |

## 12. Dependency security

| Dependencia | Versión | Estado |
|---|---|---|
| React / ReactDOM (UMD, `vendor/`) | 18.3.1 | Última de la rama 18. Sin vulnerabilidades conocidas para uso en el navegador. Las de React Server Components (2025) no aplican: no se usa `react-server-dom` |
| d3-array · d3-geo · d3-path · d3-shape (`vendor/`) | 3.2.4 · 3.1.1 · 3.1.0 · 3.2.0 | Últimas versiones. No incluyen `d3-color` (el del ReDoS antiguo) |
| Runtime de plantillas (`support.js`) | Generado, en el repositorio | Solo carga React desde `vendor/`. unpkg queda como respaldo (con SRI) si faltaran esos archivos |
| Babel (runtime, solo para `x-import` JSX) | — | No se usa en estas páginas |
| WordPress | 7.1.2 (29/09/2026) | Mantener al día (Sunqu › Seguridad avisa) |
| Secure Custom Fields · LiteSpeed Cache | 6.9.4 · 7.9.1 | Actualizar desde el panel cuando haya versión nueva |
| Hostinger AI Assistant · Easy Onboarding | 3.1.1 · 3.0.1 | Innecesarios en un CMS headless: desactivar y borrar |
| Herramientas (`tools/*.mjs`, `*.py`, `*.sh`) | — | Solo en el equipo de desarrollo. No se despliegan (`.htaccess` las bloquea) |

No hay `package.json` ni dependencias de npm en producción. `tools/vendor.mjs` fija las versiones y los hashes (SRI) de lo que descarga.

## 13. Riesgos pendientes

| Riesgo | Nivel | Por qué queda | Mitigación actual / próximo paso |
|---|---|---|---|
| `'unsafe-eval'` en la CSP | Medio | El runtime de plantillas compila con `new Function` | Scripts en línea solo por hash, contenido saneado y escapado. Eliminarlo exige compilar las plantillas antes del despliegue |
| `'unsafe-inline'` en estilos | Bajo | Las plantillas usan estilos en línea | La CSP de scripts es estricta. El CSS no ejecuta código |
| Cuentas de WordPress sin 2FA | Medio | Requiere un plugin y la configuración de cada persona | Límite de intentos y mensajes genéricos. Instalar «Two Factor» |
| Robo de la sesión de un administrador | Medio | Todo panel web lo tiene | HTTPS, HSTS, SAMEORIGIN, cookies `secure` y `HttpOnly` (núcleo). 2FA y equipos al día |
| Formularios sin CAPTCHA mientras Turnstile no esté configurado | Bajo | Requiere cuenta de Cloudflare | Trampa, tiempo mínimo, límite por IP, origen y tope de constancias |
| Límite de intentos por IP con muchas IP (botnet) | Bajo | Es un límite por IP | Nombres de usuario no enumerables, 2FA y contraseñas fuertes |
| Backups sin prueba de restauración | Medio | Depende del plan y de hacerlo | Procedimiento de la sección 10 |
| Firewall, malware y copias de Hostinger no verificados | Medio | Requiere entrar a hPanel | Lista de la sección 4 |
| DMARC en `p=none`, sin CAA ni DNSSEC | Bajo | Cambios de DNS | Sección 4 |
| Plugins de Hostinger instalados sin uso | Bajo | Los instaló el asistente de Hostinger | Borrarlos |
| `readme.html` / `license.txt` del CMS | Muy bajo | Vuelven con cada actualización | Opcional borrarlos |
| Datos personales en el CMS (reclamaciones) | Medio | Obligación legal | Acceso solo de Editores y Administradores, copias protegidas, sección 6 |
| JavaScript de terceros desde Sunqu › Scripts y seguimiento | Medio | Es la función del módulo: lo que se pega corre en la web | Solo administradores con `unfiltered_html`, historial y aviso por correo, permiso de cookies para medición, CSP por hash y dominios. `CMS_SCRIPTS=off` lo apaga. Revisar el historial cuando llegue un aviso |
| Un servicio del panel carga dominios que no aparecen en su código | Bajo | La CSP los bloquea (falla «seguro») | Se ve en la consola; agregarlos en «Dominios adicionales» |
| IP del visitante enviada a un servicio de geolocalización (si se configura `GEOIP_URL`) | Bajo | Es el respaldo pedido para el idioma; solo se consulta en la raíz, cuando el navegador no está en un idioma activo y nunca para buscadores | Desde el servidor (la clave no llega al navegador), solo https, espera máxima corta, caché de 30 días por red con hash (no se guarda la IP) y pausa si falla. Mencionarlo en la política de privacidad (pendiente legal) |
| Correos de los formularios a direcciones de terceros (alguien escribe el correo de otra persona) | Bajo | La web confirma lo que se envió a la dirección escrita | Máximo 5 correos por dirección cada 24 horas, sin enlaces de acción, «Responder» a Sunqu |

## 14. Procedimiento ante incidente

1. **Contener:**
   - cambiar la contraseña del administrador afectado y de Hostinger;
   - cerrar las sesiones (Usuarios › tu perfil › «Cerrar la sesión en todas partes»);
   - si hay desfiguración o malware, poner la web en mantenimiento desde hPanel.
2. **Rotar secretos** (sección 9): token de purga, base de datos, SMTP, Telegram, Turnstile y *salts* de `wp-config.php` (esto último cierra todas las sesiones).
3. **Evaluar:**
   - Sunqu › Seguridad;
   - usuarios nuevos o con rol cambiado;
   - plugins o archivos desconocidos (el escáner de Hostinger y, en la web, compararla con el repositorio de Git);
   - registros de error y de acceso.
4. **Limpiar:**
   - volver a desplegar la web desde Git;
   - reinstalar el núcleo de WordPress y los plugins desde fuentes oficiales;
   - si hace falta, restaurar la última copia limpia (sección 10).
5. **Datos personales:** si hubo acceso al libro de reclamaciones o a las solicitudes, consultar al asesor legal las obligaciones de aviso (Ley N° 29733 de Protección de Datos Personales).
6. **Aprender:** anotar qué pasó, cómo se detectó y qué se cambia. Actualizar este documento.

## 15. Cómo se probó

Entorno Docker con la web (Apache + PHP 8.3 + el mismo `.htaccess`), WordPress **7.1.2** (la versión del CMS de producción) con Secure Custom Fields 6.9.5, el plugin 1.3.0 (1.4.0 en las filas marcadas) y el tema, MariaDB 11 y Mailpit (Loco Translate para 1.4.0). Fechas: 29/09 y 02/10/2026.

| Prueba | Resultado |
|---|---|
| Las 8 páginas en el navegador con la CSP nueva (sin `'unsafe-inline'`) | Sin violaciones de CSP ni errores. Dos scripts en línea autorizados por hash |
| Las 8 páginas con un encabezado `upgrade-insecure-requests` que pisa la CSP (como Hostinger) | Funcionan. La CSP de la etiqueta `<meta>` bloquea un `<script>` inyectado y un enlace `javascript:` |
| `/.git/config`, `/server/lib/config.php`, `/docs/…`, `/wordpress/setup.sh`, `/tools/vendor.mjs`, `/vendor/VERSIONS.json`, `/backup.sql`, `/dist/…` | 404, sin contenido. `/.env` y la caché, 403 |
| `//evil.example/` | 301 a `/evil.example` (ruta propia) → 404. Nunca a otro dominio |
| POST a una página / PUT al sitemap / GET a la purga | 405 |
| Purga sin token o con token incorrecto / con token | 403 (queda en el log) / 200 |
| Reporte CSP | 204 y una línea en el log |
| Excepción en PHP | Página 500 genérica, 0 detalles internos en la respuesta; el detalle (archivo, línea) queda en el log |
| En WordPress: `javascript:` (también `java⇥script:`) y `data:text/html,…` en enlaces del contenido | La API los entrega tal cual; la web los publica como `#` |
| Editar un journey en WordPress | La web muestra el cambio en la petición siguiente (purga automática) |
| API del CMS sin sesión: `/wp-json/`, `/wp/v2/users`, `/wp/v2/users/1`, `/wp/v2/posts`, `/pages`, `/media`, `/search`, oEmbed, `/sunqu/v1/esquema`, `?rest_route=/wp/v2/users` | 401 en todas |
| `/sunqu/v1/contenido` y el preflight `OPTIONS` de los formularios | 200 |
| API con sesión (cookie + nonce): editor de bloques, `/wp/v2/types`, `/wp/v2/users/me`, `/wp/v2/posts`, `/sunqu/v1/esquema` | 200: el panel funciona igual |
| `xmlrpc.php` (POST) | 403 |
| `?author=1` | Redirige al panel, sin el nombre de usuario |
| `readme.txt`, `schema.json` y un `.php` suelto del plugin | 403 |
| Un `.php` puesto a mano en `wp-content/uploads` | No se ejecuta (403) |
| Subir `.html` o `.svg` a la biblioteca / un `.jpg` | Rechazados / aceptado |
| Acceso con un usuario inexistente y con contraseña mala | El mismo mensaje: «el usuario o la contraseña no son correctos» |
| 6.º intento con la contraseña **correcta** tras 5 fallos | Bloqueado («espera 15 minutos»); después entra normalmente |
| «¿Olvidaste tu contraseña?» con una cuenta que no existe y con una que sí | La misma respuesta (redirección a «revisa tu correo»); solo la real recibe el correo |
| CORS: origen ajeno / la web | Sin cabeceras CORS / `Access-Control-Allow-Origin` de la web, sin credenciales |
| Formulario desde un origen ajeno | 403 |
| Formulario desde la web (también desde el navegador, de punta a punta) | Guardado con todos sus campos y aviso por correo |
| `<script>` en el mensaje | Se guarda sin la etiqueta |
| Campo trampa lleno | Responde «ok», no se guarda |
| 9.º envío en 10 minutos desde la misma IP | 429 |
| Foto falsa (texto con cabecera JPEG y PHP adentro) | Descartada; la reseña se guarda sin foto |
| Foto real con EXIF (texto «GPS» de prueba) y nombre `../../evil.php` | Guardada como `resena-{id}-1.jpg`, sin EXIF, sin archivos con el nombre enviado |
| Turnstile con las claves de prueba públicas de Cloudflare | Sin token → 403. Secreto «siempre falla» → 403. En el navegador, con la clave «siempre pasa», el script se carga solo al enviar y el envío llega; con la clave de sitio «siempre bloquea», el formulario muestra su mensaje de error y no envía nada |
| `wp sunqu importar` + `verificar` | «WordPress devuelve exactamente el contenido de los JSON» |
| Scripts y seguimiento (1.4.0): guardar desde el formulario del panel con sesión de administrador | Guardado; código PHP y etiquetas `<html>`/`<body>` quitados, nombre sin HTML, ubicación y categoría inválidas → valores seguros, dominio inválido rechazado con aviso; historial y correo «Aviso de seguridad» |
| Scripts «Con permiso» antes y después de «Aceptar» | Inertes (`type="text/plain"`) / se ejecutan una vez, sin violaciones de CSP. «Solo necesarias» recarga la página sin ellos |
| `CMS_SCRIPTS=off` | Ningún script del panel en las páginas ni en la CSP |
| Idiomas (1.4.0): `/pt` sin traducciones con el panel de WordPress en inglés | Español: no toma traducciones de otro idioma |
| País por IP (1.5.0) con un servicio simulado | Caché sin la IP (solo un hash de la red y el país), IPs privadas sin consulta, corte a los 800 ms, pausa de 5 minutos tras un fallo, buscadores sin consulta |
| Reseña con correo (1.5.0) | El correo queda solo en el panel; la API de reseñas no lo incluye. Un correo inválido da 400 |
| Mi grupo manipulado (1.5.0): 99 adultos, números negativos, habitaciones vacías o de más | Se recorta a los límites (1–8 adultos, 0–6 niños, 0–4 bebés, hasta 6 habitaciones) |

**Hallazgos durante las pruebas (ya corregidos):**
- **Secure Custom Fields anulaba el bloqueo de la API.** Engancha al filtro `rest_pre_dispatch` una función que no devuelve nada (`ACF_Rest_Api::initialize`), y eso borraba cualquier bloqueo aplicado antes.
  - Ahora el bloqueo corre con prioridad 999.
  - Como segunda capa, las rutas de usuarios ni siquiera existen sin sesión.
  - Si en el futuro se agrega otro plugin, verificar `/wp-json/wp/v2/users` sin sesión: debe responder 401.
- **El correo de «¿Olvidaste tu contraseña?» no salía** en un sitio sin dominio con punto: WordPress usaba el remitente `wordpress@localhost`. Ahora, con SMTP configurado, todos los correos de WordPress salen del buzón autenticado (mejor entrega en producción también).
