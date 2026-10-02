# Sunqu Journeys · Medición (GA4, GTM y Meta Pixel)

Implementación: `js/sunqu-analytics.js`, cargado con `defer` en todas las páginas. Los IDs se configuran en el `.env`
de producción. **Hoy los tres están vacíos: PENDIENTE DE CONFIGURACIÓN.** Sin IDs no se carga ningún servicio
externo ni aparece el aviso de cookies.

**Otra vía, sin tocar el servidor:** pegar los códigos de GTM, GA4, Meta Pixel u otros servicios en WordPress ›
**Sunqu › Scripts y seguimiento** (sección 7). Usa una de las dos vías, no ambas: se mediría dos veces.

```
GTM_ID=GTM-XXXXXXX        # si existe, GA4 y el Pixel se configuran DENTRO de GTM
GA4_ID=G-XXXXXXXXXX       # sin GTM: la web carga gtag.js directo
META_PIXEL_ID=000000000   # sin GTM: la web carga fbevents.js directo
CONSENT_MODE=banner       # banner (recomendado) | off
```

---

## 1. Dos modos

| | Con `GTM_ID` (recomendado) | Sin GTM (`GA4_ID` y/o `META_PIXEL_ID`) |
|---|---|---|
| Qué carga la web | `gtm.js` al abrir la página (con Consent Mode denegado) | `gtag.js` y/o `fbevents.js`, **solo después de aceptar** |
| Dónde se configuran GA4 y el Pixel | Dentro del contenedor de GTM (sección 4) | En el código, sin tocar nada |
| Eventos | `dataLayer.push({ event, …parámetros })` | `gtag('event', …)` y `fbq('track', …)` |

**Validación de IDs:** GA4 debe ser `G-…`, GTM `GTM-…` y el Pixel solo dígitos. Un ID con otro formato se ignora y se avisa en la consola.

## 2. Consentimiento

- **Consent Mode v2:** todo empieza en `denied`. Solo se conceden `functionality_storage` y `security_storage`. Además se activa `ads_data_redaction`. En producción el estado por defecto lo fija el script de arranque del `<head>`, antes que cualquier script del panel (con la elección guardada, si existe).
- **Aviso** (`CONSENT_MODE=banner` y al menos un ID o un script del panel que pida permiso):
  - «Solo necesarias» / «Aceptar», con enlace a *Protección de datos* (`/terminos#datos`, o `/en/terminos#datos` en inglés);
  - el texto nombra solo los servicios configurados (los del `.env` y los del panel, por su nombre);
  - sale en el idioma de la página (textos traducidos en Loco Translate, ver WORDPRESS.md › 8.7);
  - es accesible: diálogo no modal, va justo después de «Saltar al contenido» y tiene foco visible.
- **Elección:** se guarda en `localStorage['sunqu-consent']` como `{ v: 1, a, m, t }` (analítica, marketing, fecha).
- **«Preferencias de cookies»:** aparece en el pie de todas las páginas cuando hay aviso y lo vuelve a abrir.
- **Antes de elegir:** los eventos de la página esperan y se envían solo si la persona acepta. Si rechaza, se descartan.
- **Al cambiar la elección:** `gtag('consent','update')`, `fbq('consent','grant'|'revoke')` y, con GTM, el evento `sunqu_consent_update` (`consent_analytics`, `consent_marketing`).
- **`CONSENT_MODE=off`:** mide sin aviso. Úsalo solo si el asesor legal lo confirma para el público de la web.

**Pendiente legal:** los Términos tienen una sección de datos personales, pero no mencionan cookies ni medición. El texto debe escribirlo el negocio o su asesor; no se inventó.

## 3. Eventos

La web los emite con `SunquContent.medir(evento, parámetros)` o los detecta sola por clic. Todos pasan por una
**lista blanca de parámetros** y un filtro de datos personales:
- se descarta todo texto que parezca un correo o un teléfono;
- una búsqueda con un correo o un teléfono no se mide;
- **nunca** se envían nombre, correo, teléfono, mensaje ni ningún campo de formulario.

Todos los eventos llevan `pagina`: home, journey, descubre…

| Evento | Cuándo | Parámetros | Meta (sin GTM) |
|---|---|---|---|
| `page_view` | Cada página (GA4 lo envía con `config`) | `page_location`, sin parámetros que parezcan datos personales | `PageView` |
| `click_whatsapp` | Botón flotante, «pregúntalo aquí», bolsa | `ubicacion` (flotante, cabecera, menu, pie, bolsa, id de la sección) | `Contact` |
| `begin_checkout` | «Pedir por WhatsApp» desde la bolsa de la tienda | `ubicacion: bolsa` | — |
| `form_start` | Primer foco en un formulario | `form_name` (solicitud, resena, reclamacion) | — |
| `form_submit` | Envío guardado en WordPress | `form_name`, `experience_id` (en la ficha de un journey) | — |
| `generate_lead` | Envío correcto de «Diseña tu viaje» | ídem | `Lead` |
| `form_error` | Envío rechazado o sin conexión | `form_name` | — |
| `view_experience` | Abrir la ficha de un journey | `experience_id`, `experience_name` | `ViewContent` |
| `click_experience` | Clic en la tarjeta de un journey | `experience_id`, `list_name` (sección) | — |
| `add_to_wishlist` | Guardar un journey o una experiencia en Descubre | `items: [{ item_id, item_name, item_category }]` | — |
| `search` | Buscar en Descubre (enviar o llegar con `?q=`) | `search_term` | — |
| `tutorial_begin` / `tutorial_complete` | Onboarding de Descubre | — | — |
| `add_to_cart` | «Comprar» en la tienda | `items: [{ item_id, item_name, item_variant }]` | `AddToCart` |
| `select_content` | Abrir una reseña del muro | `content_type: resena`, `content_id` | — |
| `share` | Compartir un artículo | `method` (whatsapp, facebook, x, linkedin), `content_type: articulo`, `item_id` | — |
| `cta_click` | «Diseña tu viaje» (cabecera, menú, secciones) | `ubicacion` | — |
| `outbound_click` | Enlace a otro dominio | `link_domain` | — |

**Contexto inicial en `dataLayer`:**
- `pagina`;
- `entorno`;
- `sunqu_page_location`: la URL sin parámetros que parezcan datos personales. Úsala en GTM como Page Location.

GA4 va con:
- `allow_google_signals: false` y `allow_ad_personalization_signals: false`;
- `page_referrer` sin parámetros ni ancla.

El Pixel va con `autoConfig: false`, sin detección automática de botones ni formularios.

**Límite conocido:** en modo directo, el Pixel envía la URL completa de la página (`dl`). Las URLs propias no llevan datos personales: los formularios usan POST y la búsqueda no escribe en la URL. Un enlace externo armado con datos en la URL sí los pasaría. Con GTM, la variable `sunqu_page_location` permite usar la URL limpia.

## 4. Configurar GTM (cuando exista la cuenta)

PENDIENTE DE CONFIGURACIÓN. Necesita acceso a Google Tag Manager, Google Analytics y Meta Business.

1. **Crear el contenedor web** para `sunqujourneys.com` y poner su ID en `GTM_ID`.
2. **Activar Consent Mode:** Admin › Configuración del contenedor › *Habilitar la vista general del consentimiento*.
3. **Variables** (Capa de datos): `pagina`, `form_name`, `experience_id`, `experience_name`, `ubicacion`, `search_term`, `method`, `link_domain`, `items`, `content_type`, `content_id`, `sunqu_page_location`.
4. **Etiqueta Google (GA4):**
   - ID `G-…`, en *Todas las páginas*;
   - `page_location` = `{{sunqu_page_location}}`;
   - consentimiento: requiere `analytics_storage`.
5. **Eventos de GA4:** una etiqueta «Evento de GA4» por cada evento de la tabla, cada una con su disparador «Evento personalizado» del mismo nombre y sus parámetros. Márcalos como conversión en GA4 (eventos clave): `generate_lead`, `click_whatsapp` y, si interesa, `begin_checkout`.
6. **Meta Pixel** (plantilla de la galería de GTM o HTML personalizado):
   - `PageView` en todas las páginas;
   - `Lead` con `generate_lead`, `Contact` con `click_whatsapp`, `ViewContent` con `view_experience` y `AddToCart` con `add_to_cart`;
   - consentimiento: requiere `ad_storage`;
   - en el Administrador de eventos, deja **desactivada** la *coincidencia avanzada automática*.
7. **Nuevos dominios:** si agregas otros servicios en GTM (Google Ads, Hotjar, un chat…), suma sus dominios en WordPress › Sunqu › Scripts y seguimiento › **Dominios adicionales** (o en la CSP de `server/lib/security.php`), porque si no el navegador los bloquea.
   - **Usa plantillas de la galería de GTM**, no etiquetas «HTML personalizado». La CSP no permite scripts en línea (solo los de la web, por su hash): un HTML personalizado no se ejecutaría.
   - Si una etiqueta no tiene plantilla, `CSP_INLINE_SCRIPTS=allow` en el `.env` lo permite, con menos protección contra XSS (SECURITY.md › 7).
   - La vista previa de GTM (Tag Assistant) ya está autorizada.
8. **Probar:** vista previa de GTM (Tag Assistant) y DebugView de GA4.

## 5. Sin GTM

Basta con poner `GA4_ID` y/o `META_PIXEL_ID`: los eventos de la tabla ya se envían. Para las conversiones, marca `generate_lead` y `click_whatsapp` como eventos clave en GA4.

## 6. Cómo se probó (IDs del `.env`)

Con IDs ficticios (`G-TEST…`, `GTM-TEST…`) en Docker, bloqueando en el navegador las peticiones a Google y Meta para
no enviar nada:

1. **Sin elegir:**
   - aparece el aviso;
   - no se pide ningún dominio de terceros;
   - `dataLayer` solo tiene el consentimiento denegado y el contexto.
2. **«Aceptar»:**
   - se piden `gtag/js` y `fbevents.js`;
   - `consent update` pasa a `granted`;
   - el clic en WhatsApp hecho antes de elegir se envía (`click_whatsapp` y `Contact`).
3. **Recarga:** no hay aviso y los servicios cargan directamente.
4. **Búsquedas:** con un correo o un teléfono no generan evento; «cusco» genera `search`.
5. **Otros eventos:** `add_to_wishlist`, `form_start`, `view_experience` (y `ViewContent`) llegan con sus parámetros.
6. **«Preferencias de cookies» → «Solo necesarias»:** el consentimiento pasa a `denied` y se guarda.
7. **Modo GTM:**
   - `gtm.js` carga al abrir la página;
   - los eventos llegan a `dataLayer` como objetos;
   - no se cargan `gtag.js` ni `fbevents.js` aparte.
8. **CSP:** 0 violaciones.

## 7. Scripts desde WordPress (Sunqu › Scripts y seguimiento)

Para que el equipo agregue o cambie servicios de medición sin tocar el servidor. Implementación: plugin
`includes/scripts.php` (panel), `server/lib/scripts.php` (inserción y CSP) y `js/sunqu-analytics.js` (activación).

| Opción del panel | Qué hace la web |
|---|---|
| **Dónde**: `<head>` · al empezar el `<body>` · al final del `<body>` | Inserta el código ahí, en todas las páginas e idiomas, con un comentario `<!-- Nombre (Sunqu › Scripts y seguimiento) -->` |
| **Con permiso de analítica** / **de marketing** | Cada `<script>` llega inerte (`type="text/plain" data-sunqu-consent="analitica"`, `src` → `data-src`) y se activa al aceptar, en el mismo orden. Se quitan sus `<noscript>`, `<img>` e `<iframe>` (sin JavaScript no se puede pedir permiso). El aviso nombra el servicio |
| **Siempre (necesario)** | Se inserta tal cual, sin aviso. Si es de Google (GTM o gtag.js), funciona en Consent Mode: el aviso aparece igual y Google no usa cookies hasta que la persona acepta |
| **Dominios adicionales** | Se suman a la CSP de la web, además de los que aparecen en cada código. Con un código de Google o Meta se autorizan solos sus dominios de envío (google-analytics.com, facebook.com…) |

**Eventos de la web** (tabla de la sección 3) con scripts del panel:
- llegan a `dataLayer` como `{ event, …parámetros }`: un **GTM** pegado en el panel los recibe igual que con `GTM_ID` (configúralo como en la sección 4);
- un **GA4 (gtag.js)** pegado en el panel recibe además `gtag('event', …)` de cada evento;
- antes de aceptar quedan en el navegador; si la persona rechaza, nunca salen.
- Recomendado para GTM: pegar solo el bloque del `<head>` («Con permiso de analítica», o «Siempre» para Consent Mode avanzado).

**Quitar el permiso:** un script del panel ya activo no se puede detener; si la persona elige «Solo necesarias» después de aceptar, la página se recarga sin ellos.

**Interruptor:** `CMS_SCRIPTS=off` en el `.env` de la web ignora todos los scripts del panel (por ejemplo, si un código rompe la web).

**Probado** en Docker (plugin 1.4.0), con un script de cada tipo y dominios ficticios: antes de elegir solo corre el «necesario»; «Aceptar» activa los demás una vez y sin violaciones de la CSP; «Solo necesarias» recarga sin ellos; con `CONSENT_MODE=off` corren todos sin aviso; con `CMS_SCRIPTS=off` no se inserta ninguno.

