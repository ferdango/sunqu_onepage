# Sunqu Journeys · Arquitectura de producción

Cómo funciona la web en **https://sunqujourneys.com** (Hostinger) y por qué está armada así. Complementos:
[WORDPRESS.md](WORDPRESS.md) (contenido), [SEO.md](SEO.md), [ANALYTICS.md](ANALYTICS.md),
[DEPLOYMENT.md](DEPLOYMENT.md) y [ACCESSIBILITY.md](ACCESSIBILITY.md).

---

## 1. Decisión

Se **mantiene el frontend actual**: las mismas páginas HTML, sus estilos, su lógica y el runtime de plantillas
(`support.js`). Delante se agrega una **capa PHP delgada** (`server/`), que es la que corre en Hostinger. No se
reescribió en Astro, Next.js ni React SPA.

| Criterio | Reescribir (Astro / Next) | Mantener + capa PHP (elegido) |
|---|---|---|
| Diseño e interacciones | Reescribir 8 páginas con miles de líneas de lógica: riesgo alto de regresiones | Se conservan: el DOM de producción se comparó elemento por elemento con el original |
| Hostinger | Next con SSR necesita Node (VPS); Astro estático necesita rebuild en cada publicación | PHP 8 y `.htaccess` vienen en todos los planes |
| SEO | Muy bueno | `<head>` completo por URL, URLs limpias, sitemap y 404 reales generados en el servidor |
| Sitemap que crece solo | Requiere rebuild o SSR | Se genera en cada petición desde el contenido (con caché) |
| Mantenimiento | Stack nuevo y pipeline de build | Sin build obligatorio: se despliega el repositorio tal cual |

WordPress queda **headless** en `https://cms.sunqujourneys.com`: solo administra el contenido y recibe los formularios.

---

## 2. Qué pasa en cada visita

```
 navegador ── GET /journeys/alma ──► Hostinger (LiteSpeed)
                                       │ .htaccess: HTTPS, sin www, archivos internos bloqueados,
                                       │ AVIF/WebP, caché y cabeceras de seguridad
                                       ▼
                                 server/index.php
                                       │ 1. resuelve la ruta y el idioma (/en/… → en; server/lib/routes.php)
                                       │ 2. lee el contenido en ese idioma (server/lib/content.php)
                                       │      caché en disco (5 min) ─► WordPress (?lang=en) ─► caché vencida ─► content/*.json
                                       │ 3. arma el <head> SEO (server/lib/seo.php)
                                       │ 4. prepara la plantilla journey.html (server/lib/page.php): textos fijos
                                       │    traducidos (server/lib/idiomas.php) y scripts del panel (server/lib/scripts.php)
                                       ▼
 navegador ◄── HTML con <head> completo + configuración + contenido de la página ya incluido
     │           (+ <noscript> con el mismo contenido en HTML simple, para quien no ejecuta JavaScript)
     │
     ├─ js/sunqu-content.js   (defer) usa ese contenido (sin otra petición), lo sanea y arranca support.js
     ├─ support.js            runtime: React 18 desde /vendor, renderiza la plantilla <x-dc>
     ├─ js/sunqu-analytics.js consentimiento, GTM / GA4 / Meta Pixel, eventos
     └─ formularios ── POST ──► cms.sunqujourneys.com/wp-json/sunqu/v1/formularios/… (CORS)
```

**Lo que hace el servidor con cada plantilla** (`sunqu_render()` en `server/lib/page.php`):

1. **Rutas absolutas.** `assets/…`, `css/…` y `js/…` pasan a `/assets/…`, para que funcionen en `/journeys/alma`.
2. **Enlaces limpios.** `journey.html?j=alma` pasa a `/journeys/alma`, `blog.html` a `/diario`, etc. La tabla está en `SUNQU_ENLACES`, en `routes.php`.
3. **Versión en CSS y JS.** Cada archivo lleva `?v=<hash>`, así se cachea un año y se actualiza solo cuando cambia.
4. **Bloque SEO.** Reemplaza lo que hay entre `<!-- sunqu:seo -->` y `<!-- /sunqu:seo -->`: título, descripción, canonical, robots, Open Graph, Twitter, JSON-LD y las precargas (runtime y React, fuentes e imagen principal).
5. **Plantilla codificada.** Aplica a la plantilla `<x-dc>` la misma codificación del runtime (`encodeCase`):
   - los atributos camelCase pasan a `sc-camel-*`;
   - `<helmet>` pasa a `<sc-helmet>`;
   - las tablas y los `select` pasan a `sc-raw-*`;
   - los `src` y la geometría SVG que llevan `{{ }}` también pasan a `sc-camel-*`.

   Así el navegador no pierde las mayúsculas ni descarga `{{ s.src }}`, y el runtime no vuelve a descargar la página para leer la plantilla original. La codificación es idempotente: el runtime la reaplica sin cambios.
6. **Script de arranque.** Antes de `js/sunqu-config.js` (que pasa a `defer`, igual que `js/sunqu-content.js`) inyecta:
   - `SUNQU_CONFIG`: configuración pública (sin secretos);
   - `SUNQU_PARAMS`: el slug de la URL;
   - `SUNQU_SEO`: el título;
   - `__resources`: React servido desde `/vendor`;
   - `SUNQU_PRELOADED`: solo las secciones de contenido que usa la página. Los artículos que no se muestran van sin su cuerpo.

   Es el único script en línea junto al del arte del pie: los dos se autorizan en la CSP por su hash SHA-256 (sección 7).
7. **Idioma** (`server/lib/idiomas.php`). En `/en/…` y `/pt/…` el contenido ya llega traducido de WordPress, y los textos fijos de la plantilla se reemplazan con el diccionario `sitio._i18n` (traducciones de Loco): texto entre etiquetas, atributos `alt`/`aria-label`/`placeholder`/`title` y frases entre comillas del código `<script type="text/x-dc">`, solo por coincidencia exacta. También `<html lang>`, el prefijo en los enlaces internos y `SUNQU_I18N` (idioma, la misma página en cada idioma para el selector y los textos de `js/` traducidos). Lo que no tiene traducción queda en español.
8. **Scripts del panel** (`server/lib/scripts.php`). Los códigos de WordPress › Sunqu › Scripts y seguimiento van al `<head>`, al empezar o al final del `<body>`. Los que piden permiso llegan inertes y los activa `js/sunqu-analytics.js` al aceptar. La CSP suma sus hashes y dominios. El estado de Consent Mode por defecto va en el script de arranque, antes que ellos.
9. **Contenido sin JavaScript** (`server/lib/sinjs.php`). Al empezar el `<body>`, un `<noscript>` con la información de la página en HTML simple: journeys, artículos completos, términos, datos de contacto. Los buscadores y asistentes que no ejecutan JavaScript leen el contenido real, no la plantilla. Con JavaScript activo el navegador lo ignora y el diseño no cambia. La plantilla cruda queda oculta con `x-dc{display:none}`.

**Sin servidor** (vista previa estática en GitHub Pages o `python3 -m http.server`), las mismas páginas
funcionan como antes:
- usan enlaces `.html`;
- leen `content/*.json`, o WordPress si `js/sunqu-config.js` tiene `api`;
- cargan React desde unpkg;
- llevan `noindex`, para no competir con el dominio real.

---

## 3. Estructura

```
index.html, journey.html, descubre.html, recuerda.html,   Plantillas de las páginas (también funcionan solas)
blog.html, articulo.html, terminos.html, reclamaciones.html
Sunqu Journeys v2.dc.html     Fuente de index.html (se copia tal cual)
support.js                    Runtime de plantillas (no se modifica)
css/<página>.min.css          Estilos por página
js/sunqu-config.js            Solo para hosting estático: URL de WordPress (en producción manda el .env)
js/sunqu-content.js           Capa de contenido: origen, vistas por página, imágenes/srcset, formularios, foco en diálogos
js/sunqu-analytics.js         Consentimiento + GTM/GA4/Pixel + eventos (sin datos personales)
js/sunqu-campos.js            Campos de los formularios: País con buscador, Celular con prefijo y bandera, Mi grupo
content/*.json                Contenido completo (respaldo si WordPress no responde; mismo formato que la API)
assets/                       Imágenes locales + versiones .avif/.webp + favicons + assets/geo (mapa)
fonts/                        Aeonik en WOFF2 (y TTF de respaldo)
vendor/                       React 18.3.1 y módulos de d3 servidos desde el propio sitio (VERSIONS.json)

.htaccess                     Reglas de Hostinger: HTTPS, www, rutas, bloqueos, formatos, caché, compresión, cabeceras
.env.example                  Variables de producción (el .env real nunca se sube)
server/
  index.php                   Punto de entrada: rutas, 301, sitemap, robots, purga, reportes CSP, salud, 404
  404.html · 500.html         Páginas de error (estado HTTP real, noindex, sin detalles internos)
  lib/config.php              Lee el .env (una sola fuente de configuración)
  lib/routes.php              URLs públicas y redirecciones de las antiguas
  lib/content.php             WordPress con caché en disco (una por idioma) y respaldos
  lib/idiomas.php             Idiomas: /es/…, /en/…, idioma de la raíz, textos fijos traducidos, selector (SUNQU_I18N)
  lib/geo.php                 País aproximado por IP (cabecera de la CDN o GEOIP_URL), con caché y sin guardar IPs
  lib/scripts.php             Scripts de WordPress › Sunqu › Scripts y seguimiento (consentimiento y CSP)
  lib/seo.php                 <head> y JSON-LD por página
  lib/page.php                Transformaciones de la plantilla, precargas y hashes CSP de los scripts en línea
  lib/sinjs.php               Contenido sin JavaScript (<noscript>) para buscadores y asistentes
  lib/sitemap.php             sitemap.xml (con imágenes) y robots.txt
  lib/security.php            Errores sin detalles, Content-Security-Policy, cabeceras y reportes CSP
  storage/                    (se crea solo; fuera de git) caché de contenido

wordpress/                    Plugin «Sunqu CMS», tema headless, Docker local y generador del esquema
tools/                        Scripts de mantenimiento: vendor.mjs, optimizar-imagenes.mjs, fuentes-woff2.py,
                              extraer-textos.py (textos fijos para traducir con Loco Translate)
docs/                         Esta documentación
```

---

## 4. Configuración central

Todo sale de un solo `.env`. El ejemplo es `.env.example`: se copia fuera de `public_html`, y `server/lib/config.php`
lo lee. Las variables del entorno del servidor tienen prioridad sobre el archivo. Un valor vacío significa
**PENDIENTE DE CONFIGURACIÓN**: la web funciona igual, sin esa integración.

| Variable | Uso | Si está vacía |
|---|---|---|
| `SITE_URL` | Dominio canónico: canonical, OG, JSON-LD, sitemap, redirecciones | Se usa el host de la petición (solo sirve en local) |
| `APP_ENV` | `production` o `staging`. `staging` agrega `noindex` y `Disallow: /` | `production` |
| `WORDPRESS_API_URL` | API pública de WordPress (formularios, CORS, CSP) | La web usa `content/*.json` y los formularios muestran error |
| `WORDPRESS_API_URL_INTERNAL` | URL con la que el servidor lee WordPress, si es distinta | `WORDPRESS_API_URL` |
| `GA4_ID`, `GTM_ID`, `META_PIXEL_ID` | Medición (ver ANALYTICS.md) | No se carga nada de terceros |
| `WHATSAPP_NUMBER` | Respaldo del número. Manda **Sunqu › Sitio › WhatsApp** | Se usa el del CMS |
| `CONSENT_MODE` | `banner` (aviso antes de medir) u `off` | `banner` |
| `CONTENT_CACHE_TTL` | Segundos de caché del contenido | 300 |
| `CACHE_PURGE_TOKEN` | Secreto con el que WordPress vacía la caché al publicar | Los cambios aparecen al vencer la caché |
| `CSP_MODE` | `enforce`, `report` u `off` | `enforce` |
| `CSP_INLINE_SCRIPTS` | `hash` (solo los scripts en línea de la web) o `allow` (`'unsafe-inline'`, solo si GTM usa etiquetas «HTML personalizado») | `hash` |
| `TURNSTILE_SITE_KEY` | Clave pública de Cloudflare Turnstile para los formularios (la secreta va en el `wp-config.php` del CMS) | Formularios sin Turnstile |
| `CMS_SCRIPTS` | `on` u `off`: insertar los scripts de WordPress › Sunqu › Scripts y seguimiento (`off` = interruptor de emergencia) | `on` |
| `GEOIP_URL`, `GEOIP_CAMPO`, `GEOIP_TIMEOUT_MS` | Servicio de país por IP (respaldo para el idioma de la raíz), consultado desde el servidor: `{ip}` se reemplaza; campo del JSON con el país; espera máxima (800 ms) | Sin consulta por IP (se usa la cabecera de país de la CDN, si existe) |

Al navegador solo llega `SUNQU_CONFIG`, con valores públicos: entorno, dominio, API, IDs de medición, WhatsApp, modo de consentimiento, clave pública de Turnstile y versiones.

---

## 5. URLs

Cada página vive en su idioma (`{idioma}` = `es`, `en`… los activos en WordPress › Sunqu › Idiomas):

| URL | Plantilla | Parámetro |
|---|---|---|
| `/` | — | Elige el idioma y redirige (302) a `/es/` o `/en/` (ver abajo) |
| `/{idioma}/` | index.html | — |
| `/{idioma}/descubre` | descubre.html | `?q=` (búsqueda) |
| `/{idioma}/journeys/{slug}` | journey.html | slug del journey. Un journey nuevo en WordPress tiene URL sola |
| `/{idioma}/recuerda` | recuerda.html | — |
| `/{idioma}/diario` | blog.html | `?cat=` (canonical sin parámetro) |
| `/{idioma}/diario/{slug}` | articulo.html | slug de la entrada |
| `/{idioma}/terminos` | terminos.html | — |
| `/{idioma}/libro-de-reclamaciones` | reclamaciones.html | — |

El inicio de cada idioma lleva barra final (`/en` → `/en/`); el resto, no (`/en/descubre/` → `/en/descubre`). Redirecciones:
- **URLs de antes, sin prefijo** (eran en español): `/descubre`, `/journeys/alma`… → **301** a `/es/descubre`, `/es/journeys/alma`…
- **URLs antiguas `.html`:** `/journey.html?j=alma` → `/es/journeys/alma`; `/blog.html?cat=x` → `/es/diario?cat=x`; `/index.html` → `/`.
- `/{idioma}/journeys` → `/{idioma}/descubre`.
- **Idioma conocido pero no activo** (`/fr/…`): **302** a la misma página en español.
- Los slugs no se traducen. SEO de cada idioma en [SEO.md › 9](SEO.md).

**Idioma en la raíz** (`server/lib/idiomas.php › sunqu_idioma_inicial`): preferencia guardada (cookie `sunqu_language`, la escribe el selector) → idioma del navegador (`Accept-Language`) si está activo → país aproximado por IP (`server/lib/geo.php`: cabecera de la CDN o el servicio de `GEOIP_URL`, con caché y espera máxima corta; nunca para buscadores) con el mapa `SUNQU_PAISES_IDIOMA` → inglés (o español si el inglés no está activo). La respuesta no se cachea (`private, no-store`, `Vary: Accept-Language, Cookie`) y deja la cookie `sunqu_lang_src` (2 minutos) para el evento `language_detected`.

Los slugs inexistentes devuelven **404 real**. `/sitemap.xml` y `/robots.txt` se generan en el servidor.
Rutas internas: `/_sunqu/purge` (POST con token), `/_sunqu/csp` (POST, reportes de CSP) y `/_sunqu/salud` (GET, 200/503 para monitoreo), todas con `noindex` y sin caché. `/favicon.ico` sirve `assets/favicon-48.png`.

---

## 6. Caché y rendimiento

| Qué | Dónde | Duración |
|---|---|---|
| Contenido de WordPress | `server/storage/cache/contenido-{idioma}.json` (`contenido-es.json`, `contenido-en.json`…) | `CONTENT_CACHE_TTL` (5 min). Al publicar (o al guardar traducciones en Loco), la purga marca vencidos todos los idiomas |
| País por IP (solo con `GEOIP_URL`) | `server/storage/cache/geo/` (un hash por red /24 o /48, sin la IP) | 30 días (1 día si el servicio no supo el país; 5 min de pausa si falló) |
| Raíz `/` (elección de idioma) | — | Nunca: cada persona puede ir a un idioma distinto |
| HTML | LiteSpeed (`X-LiteSpeed-Cache-Control`); el navegador revalida | 5 min, solo si la página se armó con contenido vigente. La purga envía `X-LiteSpeed-Purge: *` |
| CSS y JS (con `?v=`), fuentes | navegador | 1 año, `immutable` |
| Imágenes y video | navegador | 30 días |

**Purga:** no borra la copia, la marca como vencida.
- La siguiente petición la renueva: una sola, gracias al candado.
- Mientras tanto, las demás reciben la copia anterior.
- Si llega una purga durante una descarga, esa descarga queda vencida.

**Si WordPress falla:** se sirve la última copia y, si no existe, `content/*.json`. No se vuelve a intentar durante 60 s, así una caída no hace lenta cada página. Esas páginas se sirven sin cachear en LiteSpeed, así el cambio aparece apenas WordPress responde.

WordPress solo avisa cuando cambia algo **publicado**: publicar, editar, despublicar o borrar. Los borradores, los autoguardados y lo que llega por los formularios no purgan.

Otras medidas de rendimiento:
- **Imágenes locales:** versiones AVIF y WebP (`foto.jpg.avif` / `.webp`), que `.htaccess` elige según el navegador. El HTML no cambia.
- **Imágenes del contenido:** `srcset`/`sizes` medidos en 4 anchos de pantalla. Unsplash por ancho; WordPress con los tamaños de su biblioteca (`_imagenes` en la API).
- **Hero:** la primera foto con `fetchpriority="high"` y precarga en el `<head>`; las demás, después de `load`. En el Diario, la primera fila de tarjetas va sin carga diferida y con precarga (era la imagen LCP y estaba en `lazy`).
- **Cadena de arranque en paralelo:** `support.js`, React y ReactDOM se precargan desde el `<head>`, y `sunqu-config.js` / `sunqu-content.js` van con `defer` (no bloquean el primer pintado). Antes se descargaban uno tras otro.
- **Compresión:** `.htaccess` comprime también `text/javascript` (el tipo que usan hoy Apache y LiteSpeed para `.js`). Sin esa línea, React viajaba sin comprimir en Apache (131 KB → 43 KB).
- **Loader:** una vez por sesión (`sessionStorage`). El LCP del inicio baja de ~4,6 s en la primera visita a ~1,5 s en las siguientes.
- **Imágenes debajo del pliegue:** `loading="lazy"` y `decoding="async"`.
- **Mapa:** d3 (4 módulos) y el contorno de Perú y sus vecinos (99 KB, 32 KB con gzip) se cargan desde `/vendor` y `/assets/geo` solo cuando el mapa se acerca a la pantalla. Antes eran d3 completo + world-atlas (240 KB) desde CDN en todas las visitas.
- **Fuentes:** WOFF2 (~35 KB cada una) con precarga. Se quitó Manrope de Google Fonts, que no se usaba.
- **Bibliotecas:** React se sirve desde `/vendor`, sin CDN en el camino crítico.

---

## 7. Seguridad

Detalle completo, pruebas y riesgos pendientes: [SECURITY.md](SECURITY.md).

- **`.htaccess`:** `.env*`, `.git*`, `server/` (salvo el punto de entrada), `wordpress/`, `docs/`, `tools/`, `dist/`, `*.md`, `*.py`, `*.sh`, `*.mjs`, copias y volcados (`*.sql`, `*.bak`, `*.zip`, `*.log`…) y `vendor/VERSIONS.json` van al enrutador, que responde **404** (no se confirma que existan). Los archivos ocultos, 403. Agrega HSTS, `nosniff`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`, COOP y `X-Permitted-Cross-Domain-Policies`. El HSTS también lo envía PHP, porque LiteSpeed puede ignorar el `env=HTTPS` del `.htaccess`.
- **Rutas:** una ruta con caracteres de control, UTF-8 inválido o de más de 2048 bytes responde 404. `//algo` se normaliza (301 a una ruta propia): nunca redirige a otro dominio. Métodos distintos de GET/HEAD en páginas → 405.
- **Errores:** en producción nunca se muestran. Van al log de PHP y el visitante ve `server/500.html`. Sin `X-Powered-By`.
- **CSP** (`security.php`): se arma según la configuración. Solo permite los dominios de WordPress, Unsplash y los servicios de medición configurados, y bloquea `object`, `base` y el enmarcado externo. `script-src` lleva `'unsafe-eval'` (el runtime compila con `new Function()`), pero **no** `'unsafe-inline'`: los dos scripts en línea de la página se autorizan por su hash. Las violaciones se reportan a `/_sunqu/csp` y quedan en el log. La misma política va también en un `<meta http-equiv>`, primero en el `<head>`, porque Hostinger reemplaza el encabezado CSP por el suyo (`upgrade-insecure-requests`).
- **Contenido de WordPress saneado** (`sunqu_sanear` en el servidor y `sanear` en el navegador): valores `javascript:` o `data:` que no son imagen pasan a `#`, se quitan los caracteres de control y, en el navegador, las claves `__proto__`.
- **Escape en el JSON incrustado** (`JSON_HEX_TAG|AMP|APOS|QUOT`).
- **Mapa:** los nombres de las paradas, que vienen del CMS, se escapan (`SunquContent.esc`). Antes se insertaban sin escapar (XSS almacenado).
- **Formularios** (plugin): cada envío se valida en el servidor con los mismos obligatorios que en la web y se sanea. Para frenar el spam se combinan tres medidas:
  - un límite de 8 envíos cada 10 minutos por IP;
  - un campo trampa (`website`);
  - un tiempo mínimo de llenado (`_t`, 2,5 s).
- **Libro de reclamaciones:**
  - el código correlativo lo asigna el servidor;
  - la constancia por correo tiene un tope de 3 por destinatario cada 24 h, para que el formulario no sirva para enviar correos a terceros;
  - una hoja que parece de bot se guarda como borrador «[posible spam]» en lugar de perderse.
- **CORS:** toda la API del CMS solo responde al origen de la web, nunca con credenciales. Los formularios rechazan otros orígenes (403).
- **Purga:** exige `POST` con el token (`hash_equals`). Los intentos fallidos quedan en el log.
- **Monitoreo:** `/_sunqu/salud` responde 200 con el contenido vigente y 503 si WordPress no responde.

---

## 8. Escalabilidad (preparado, no implementado)

- **Nuevo journey, experiencia, producto o artículo:** se crea en WordPress y aparece con su URL, su SEO, su JSON-LD y su entrada en el sitemap, sin tocar código.
- **Nuevo campo:** se agrega a `content/*.json` y se regenera `schema.json` (ver WORDPRESS.md › 8.2).
- **Nueva página fija:** plantilla HTML + fila en `SUNQU_PAGINAS`, `SUNQU_ENLACES` y `sunqu_resolver()` + su `seo.paginas.<clave>`.
- **Idiomas:** el selector ES/EN/PT existe, pero no hay contenido traducido. Lo natural sería un prefijo `/en/…` resuelto en `routes.php` y un campo por idioma en el CMS. No se implementó porque no existe el contenido.
