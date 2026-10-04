# Sunqu Journeys · Integración con WordPress

Manual para el desarrollador. Explica cómo está armada la web, cómo levantar WordPress en local en unos minutos y dónde está cada apartado para integrarlo rápido.

---

## 1. En 30 segundos

La web usa las mismas páginas de siempre (`index.html`, `journey.html`, `descubre.html`…), con el mismo diseño. Lo que cambió es **de dónde sale el contenido**: ya no está escrito dentro de cada página. Vive en `content/*.json` y lo entrega **WordPress** por su API REST.

En producción (Hostinger), la capa PHP `server/` lee WordPress **desde el servidor**, con caché, y entrega cada página con su contenido ya incluido y su SEO. Los detalles están en [ARCHITECTURE.md](ARCHITECTURE.md). Sin esa capa (vista previa estática), la web lee WordPress o `content/*.json` desde el navegador.

```
                        ┌─────────────────────────────────────────────┐
  editor ──► wp-admin ──►  WordPress + plugin «Sunqu CMS»              │
                        │  (journeys, experiencias, anfitriones,       │
                        │   reseñas, tienda, Diario, portada, ajustes) │
                        └───────────────┬─────────────────────────────┘
                                        │ GET /wp-json/sunqu/v1/contenido      (servidor de la web, con caché)
                                        │ POST /wp-json/sunqu/v1/formularios/… (navegador, CORS)
                                        │ POST https://sunqujourneys.com/_sunqu/purge al guardar (token)
                                        ▼
  visitante ──► /journeys/alma ──► server/index.php ──► HTML + contenido ──► support.js (runtime) ──► página
                                        ▲
                                        │ si WordPress no responde: caché vencida o content/*.json
                                   content/*.json
```

- **Headless.** WordPress solo administra el contenido y recibe los formularios. Quien abre el CMS va directo al acceso del panel (tema «Sunqu Headless»), y el CMS no se indexa.
- **Mismo formato en todos lados.** `content/journeys.json`, la respuesta de `…/sunqu/v1/contenido/journeys` y lo que exporta `wp sunqu exportar` son idénticos. Si WordPress falla, la web usa los JSON sin que se note.
- **Un solo modelo.** `wordpress/sunqu-cms/schema.json` define los tipos de contenido, los campos del panel, la importación y la API. Se genera desde los JSON con un script.

---

## 2. Qué hay en el repositorio

```
index.html, journey.html, descubre.html, recuerda.html,      Páginas (HTML indentado con tabs, un comentario por sección)
blog.html, articulo.html, terminos.html, reclamaciones.html
Sunqu Journeys v2.dc.html                                    Fuente de index.html (se copia tal cual)
css/<página>.min.css                                         Estilos de cada página (minificados)
support.js                                                   Runtime de las plantillas (no tocar)

content/                                                     ◆ CONTENIDO — única fuente de datos de la web
  sitio.json          contacto, redes, empresa, conceptos, destinos, duraciones
  inicio.json         colecciones de la portada (hero, principios, formas, ruta, altitud…)
  journeys.json       journeys (ficha, plan, tarjeta de catálogo y de portada) + principios y galería comunes
  anfitriones.json    anfitriones
  experiencias.json   experiencias del catálogo
  resenas.json        reseñas + temas y plataformas
  productos.json      tienda + categorías
  diario.json         Diario Sunqu: categorías y artículos
  legales.json        términos y condiciones

js/
  sunqu-config.js     URL de la API solo para hosting estático (en producción manda el .env de la web)
  sunqu-content.js    carga el contenido, lo adapta a cada página y envía los formularios
  sunqu-analytics.js  consentimiento y medición (docs/ANALYTICS.md)

server/               capa PHP de producción (docs/ARCHITECTURE.md)

wordpress/                                                   ◆ CMS
  setup.sh            levanta todo con Docker, instala, importa y verifica
  docker-compose.yml  WordPress + MariaDB + WP-CLI + la web «sitio» (Apache + PHP + .htaccess, como Hostinger)
  .env.example        puertos, credenciales locales y token de purga (se copia como .env)
  sunqu-cms/          plugin «Sunqu CMS»
    schema.json       modelo de contenido (generado)
    includes/…        motor de datos, registro, API, formularios, Diario, panel, idiomas, scripts, seguridad y WP-CLI
    i18n/textos-web.php  textos fijos de la web para traducir (generado por tools/extraer-textos.py)
  sunqu-headless/     tema mínimo: el front de WordPress lleva al acceso del panel
  tools/generar-schema.py  genera schema.json desde content/*.json

tools/extraer-textos.py  junta los textos fijos de las páginas, js/ y server/ para Loco Translate (8.7)

docs/WORDPRESS.md     este manual · ARCHITECTURE, SEO, ANALYTICS, DEPLOYMENT, SECURITY y ACCESSIBILITY: el resto
```

---

## 3. Arranque rápido (local, con Docker)

**Requisitos:** Docker Desktop abierto y los puertos 8080 y 8081 libres. Se cambian en `wordpress/.env`.

```bash
cd wordpress
./setup.sh
```

El script hace todo esto y termina en unos minutos. La primera vez descarga las imágenes de Docker y sube las fotos a la biblioteca.
1. Copia `.env.example` → `.env` (puertos y credenciales **solo locales**) y genera el token de purga.
2. Levanta `db`, `wordpress` y `sitio`.
3. Instala WordPress en español, **Secure Custom Fields** (gratis), el plugin **Sunqu CMS** y el tema **Sunqu Headless**.
4. Configura los enlaces permanentes, la URL de la web, el token de purga y el «no indexar» del CMS.
5. Importa `content/*.json` con `wp sunqu importar` (con imágenes y videos a la biblioteca).
6. Corre `wp sunqu verificar`, que confirma que la API devuelve exactamente el contenido de los JSON.

| Qué | URL |
|---|---|
| Panel de WordPress | http://localhost:8080/wp-admin (usuario y clave en `wordpress/.env`) |
| API de contenido | http://localhost:8080/wp-json/sunqu/v1/contenido |
| La web como en producción (Apache + PHP + `.htaccess`, leyendo WordPress) | http://localhost:8081 (`/`, `/descubre`, `/journeys/alma`…) |

Si quieres importar más rápido sin subir imágenes, pon `IMPORT_FLAGS=--sin-imagenes` en `.env`. La web las muestra igual.

Ese modo sirve **solo para probar la API**. Si después editas y guardas un elemento en el panel, el plugin de campos borra las imágenes que no están en la biblioteca. Para trabajar en el panel, importa con imágenes.

Comandos útiles:

```bash
docker compose run --rm cli sunqu importar --dir=/var/www/front/content --front=/var/www/front --user=admin --podar   # --podar: lo que ya no está en los JSON pasa a borrador
docker compose run --rm cli sunqu verificar --dir=/var/www/front/content
docker compose run --rm cli sunqu exportar --dir=/var/www/front/content      # WordPress → content/*.json
docker compose run --rm cli plugin list
docker compose down            # apaga (los datos quedan en volúmenes)
docker compose down -v         # apaga y borra la base de datos para empezar de cero
```

### Sin Docker (WordPress existente: LocalWP, MAMP o un hosting)

1. Arma los zips: `bash tools/empaquetar-wordpress.sh`. Deja en `dist/`:
   - `sunqu-cms.zip`, el **plugin**, con el contenido actual de la web en `datos/`;
   - `sunqu-headless.zip`, el **tema**.
2. Plugins › Añadir nuevo › **Subir plugin** › `sunqu-cms.zip` › Activar.
3. Apariencia › Temas › Añadir tema › **Subir tema** › `sunqu-headless.zip` › Activar. Es un tema: en Plugins da «No se encontraron plugins».
4. Instala y activa **Secure Custom Fields**. ACF Pro también sirve.
5. En Ajustes › Enlaces permanentes elige «Nombre de la entrada». Así funciona `/wp-json/`.
6. Importa desde **Sunqu › Conexión y herramientas › Importar el contenido**. También por consola: `wp sunqu importar --dir=/ruta/a/la/web/content --front=/ruta/a/la/web --user=<admin>`.
7. Verifica por consola: `wp sunqu verificar --dir=/ruta/a/la/web/content`.

El botón importa una sección por petición, para no chocar con el límite de tiempo de PHP mientras descarga las fotos. Es la misma lógica que la consola (`includes/importador.php`): se puede repetir, porque busca cada elemento por su slug y reutiliza las fotos ya subidas.

---

## 4. Conectar la web con WordPress

**En producción (Hostinger):** en el `.env` de la web, `WORDPRESS_API_URL=https://cms.sunqujourneys.com/wp-json` (ver [DEPLOYMENT.md](DEPLOYMENT.md)). El servidor lee el contenido, lo guarda 5 minutos (`CONTENT_CACHE_TTL`) y lo incluye en cada página. Con el **token de purga** configurado en los dos lados, cada «Actualizar» en WordPress se ve al instante.

**En un hosting estático** (vista previa en GitHub Pages), edita **`js/sunqu-config.js`**:

```js
window.SUNQU_CMS = {
  api: 'https://cms.sunqujourneys.com/wp-json',   // '' = usar content/*.json
};
```

Cada página carga, en el `<head>`, `js/sunqu-config.js` y después `js/sunqu-content.js` con `data-page="…"`. Si el servidor ya incluyó el contenido (`SUNQU_PRELOADED`), lo usa. Si no:

1. Pide a WordPress solo las secciones que necesita esa página:
   `GET {api}/sunqu/v1/contenido?secciones=sitio,journeys,anfitriones`
2. Si WordPress no responde, usa `content/*.json` y lo avisa en la consola.
3. Adapta el contenido al formato de la página (`window.SunquView`) y recién ahí arranca `support.js`.

En la consola del navegador, `SUNQU_ORIGEN` dice de dónde salió el contenido: `servidor` (producción), `wordpress` o `local`.

Secciones que pide cada página (constante `NEED` en `js/sunqu-content.js`):

| Página | `data-page` | Secciones |
|---|---|---|
| index.html | home | sitio, inicio, journeys, anfitriones, diario |
| journey.html?j=slug | journey | sitio, journeys, anfitriones |
| descubre.html | descubre | sitio, journeys, experiencias |
| recuerda.html | recuerda | sitio, resenas, productos |
| blog.html / articulo.html?a=slug | blog / articulo | sitio, diario |
| terminos.html | terminos | sitio, legales |
| reclamaciones.html | reclamaciones | sitio |

---

## 5. Mapa: cada apartado de la web → contenido → WordPress

Notación: `archivo › clave` es la ruta dentro de `content/`. **Menú › campo** es dónde se edita en WordPress. Todas las colecciones se ordenan con el campo **Orden** del atributo de página (de menor a mayor).

### Todas las páginas

| Apartado | Contenido | WordPress |
|---|---|---|
| Footer: correo, teléfono, oficina | `sitio › contacto` | **Sunqu › Sitio** › Contacto |
| Footer y menú lateral: redes | `sitio › redes` | Sunqu › Sitio › Redes sociales |
| Botón flotante y enlaces de WhatsApp | `sitio › contacto.whatsapp` | Sunqu › Sitio › Contacto › WhatsApp |
| Menú lateral: correo y teléfono | `sitio › contacto` | Sunqu › Sitio › Contacto |
| Título de la pestaña y descripción para Google y redes (páginas fijas) | `sitio › seo.paginas.{inicio, descubre, recuerda, diario, terminos, reclamaciones}` | Sunqu › Sitio › **SEO (buscadores y redes sociales)** › Páginas fijas. Journeys y artículos usan su propio título y texto (ver SEO.md) |

### Inicio (`index.html`)

| Apartado de la web | Contenido | WordPress |
|---|---|---|
| Hero: fotos en rotación y pie de foto | `inicio › heroSlides` | **Sunqu › Inicio** › Hero |
| Principios «Curamos, no acumulamos» (6 tarjetas) | `inicio › principios` | Sunqu › Inicio › Principios |
| Formas de viajar (lista + imagen + enlace) | `inicio › formas` | Sunqu › Inicio › Formas de viajar |
| 01 Descubre: tarjetas de journeys | `journeys › items[].inicio` + datos base | **Journeys** › cada journey › Tarjeta de la portada |
| 02 Conecta: anfitriones | `inicio › anfitriones` (orden) + `anfitriones` | Sunqu › Inicio › Anfitriones destacados + **Anfitriones** |
| 03 Vive: condiciones del Momento Sunqu | `inicio › condiciones` | Sunqu › Inicio › Momento Sunqu · condiciones |
| Curva de altitud | `inicio › altitud` | Sunqu › Inicio › Curva de altitud |
| Mapa de la ruta (paradas con coordenadas) | `inicio › ruta` | Sunqu › Inicio › Mapa de la ruta |
| 04 Recuerda: antes, durante y después | `inicio › fases` | Sunqu › Inicio › Antes, durante y después |
| Palabras en quechua | `inicio › palabras` | Sunqu › Inicio › Palabras en quechua |
| Diario Sunqu (3 tarjetas) | los artículos más recientes de `diario › posts` | **Entradas** (cantidad en Sunqu › Inicio › Diario en la portada) |
| Instagram (grilla) y usuario | `inicio › instagram`, `sitio › redes.instagramUsuario` | Sunqu › Inicio › Instagram · Sunqu › Sitio › Redes |
| Contacto: pasos | `inicio › pasos` | Sunqu › Inicio › Cómo diseñamos tu viaje |
| Contacto: formulario | — | llega a **Sunqu › Solicitudes de viaje** |

Los títulos y textos fijos de sección («El Perú no es un destino…», «Detrás de Sunqu», «Círculo Dorado») siguen en la plantilla. Para hacerlos editables, sigue la receta 8.3.

### Ficha de journey (`journey.html?j=<slug>`)

Todo sale del tipo de contenido **Journeys**. El slug del journey es el `?j=` de la URL.

| Apartado | Campo del journey |
|---|---|
| Hero: título, antetítulo, región, días, altitud, fotos | Título · Ficha › Antetítulo, Región, Galería del hero · Duración · Altitud |
| Precio (tarjeta lateral y barra móvil) | Ficha › Precio, Precio corto, Antetítulo del precio, Nota del precio |
| Por qué / Qué es / Historia | Ficha › Por qué, Qué es, Historia · título, Historia · párrafos |
| Momento Sunqu | Ficha › Momento Sunqu, Momento Sunqu · foto, texto alternativo, Variante |
| Cómo se vive (principio) | Ficha › Principio que lo guía (de **Sunqu › Journeys · común › Principios**) y Cómo se vive · texto |
| Día a día e itinerario | Ficha › Día a día · título/texto, Itinerario día a día (vacío = sin itinerario) |
| Curva de altitud | Ficha › Altitud · título/texto + la altitud de cada día del itinerario |
| El mejor momento para visitar | Planifica › Temporada (texto, clima, temperatura, calendario, **Meses**) |
| Consejos Sunqu y notas | Planifica › Consejos Sunqu, Nota de accesibilidad |
| Conecta: anfitriones y ficha de reciprocidad | Ficha › Anfitriones (relación con **Anfitriones**), Nota de reciprocidad |
| Galería «Recuerda» | **Sunqu › Journeys · común** › Galería |
| Otros journeys | los demás journeys (Ficha › Imagen de tarjeta) |
| Formulario de reserva | llega a Sunqu › Solicitudes de viaje |

### Descubre (`descubre.html`)

| Apartado | Contenido | WordPress |
|---|---|---|
| Filtros: conceptos, destinos, duración | `sitio › conceptos, destinos, duraciones` | Sunqu › Sitio |
| Journeys del catálogo (orden, textos, precio, imágenes, palabras clave) | `journeys › items[].catalogo` | Journeys › Tarjeta del catálogo |
| Temporada por mes (etiqueta «Ideal en …») | `journeys › items[].plan.season.months` | Journeys › Planifica › Temporada › Meses |
| Experiencias | `experiencias › items` | **Experiencias** |
| Guardados | se guardan en el navegador (`localStorage['sunqu-fav']`, clave `j-<slug>` / `e-<slug>`) | — |
| Formulario | — | Sunqu › Solicitudes de viaje (incluye los guardados adjuntos) |

### Recuerda (`recuerda.html`)

| Apartado | Contenido | WordPress |
|---|---|---|
| Muro de reseñas (videos, fotos, comentarios) | `resenas › items` | **Reseñas** (las «Pendientes» no se publican hasta aprobarlas) |
| Pestañas por tema y nombres de plataforma | `resenas › temas, plataformas` | Sunqu › Reseñas · temas y plataformas |
| Tienda | `productos › items` | **Productos (tienda)** |
| Categorías de la tienda | `productos › categorias` | Sunqu › Tienda · categorías |
| «Comparte tu viaje» (formulario) | — | crea una **Reseña pendiente** con sus fotos |

Una reseña con **Enlace al post** de Instagram o TikTok muestra el embed oficial. **Es un ejemplo** activado muestra la etiqueta «Ejemplo».

### Diario Sunqu (`blog.html`, `articulo.html?a=<slug>`)

Usa las **Entradas** y **Categorías** nativas de WordPress.

| Dato de la web | En WordPress |
|---|---|
| slug, título, fecha, extracto | slug, título, fecha de publicación, extracto |
| imagen | Imagen destacada |
| categoría (pestañas) | Categoría. El orden de las pestañas es el de la importación (meta `sunqu_orden`) |
| minutos de lectura, autor, rol, entradilla | caja lateral **Diario Sunqu** |
| cuerpo | editor de bloques: cada **Encabezado** inicia una sección del índice. Se admiten Párrafo, Lista (negrita inicial + texto), Cita (con fuente) e Imagen (con leyenda) |

### Términos y condiciones (`terminos.html`)

`legales › terminos.secciones` → **Sunqu › Legales**. Cada sección tiene un identificador (el ancla del índice), un título y bloques: Párrafo, Lista (Negrita + Texto) o Enlace.

### Libro de reclamaciones (`reclamaciones.html`)

- Razón social, RUC y dirección: `sitio › empresa` → **Sunqu › Sitio › Empresa**.
- Cada hoja enviada llega a **Sunqu › Libro de reclamaciones**. Se envía por correo una constancia al consumidor y un aviso a la empresa.

---

## 6. API

Base: `{sitio}/wp-json/sunqu/v1`. Todas las respuestas son JSON.

### Lectura

| Método y ruta | Devuelve |
|---|---|
| `GET /contenido?secciones=a,b,c` | `{ "a": {…}, "b": {…} }`. Sin `secciones`, devuelve todas |
| `GET /contenido/{seccion}` | una sección: `sitio`, `inicio`, `journeys`, `anfitriones`, `experiencias`, `resenas`, `productos`, `diario` o `legales` |
| `…?lang=en` (o `pt`) | el mismo contenido traducido con Loco Translate, si ese idioma está activo en Sunqu › Idiomas (lo que falta, en español; un idioma inactivo se ignora) |
| `GET /esquema` | `schema.json` (pide sesión con permiso de edición) |

**Sin sesión solo responden `/contenido` y `/formularios`.** El índice `/wp-json/`, `/wp/v2/*` (usuarios, entradas, medios…), oEmbed y las rutas internas del editor responden 401 (plugin, `includes/seguridad.php`). El panel y el editor de bloques no cambian. Ver [SECURITY.md](SECURITY.md).

- **Imágenes:** la API devuelve URLs de la biblioteca. La web también acepta rutas `assets/…` e ids de Unsplash, por eso los JSON y la API son intercambiables.
  - Cada sección con fotos de la biblioteca trae además **`_imagenes`**, en formato `{ url: srcset }`, con los tamaños que generó WordPress. La web lo usa para servir el tamaño justo a cada pantalla.
  - Cada journey, experiencia, anfitrión, reseña, producto y artículo trae **`_modificado`** (fecha de su última edición, ISO 8601). La web la usa en el sitemap (`lastmod`) y en `dateModified`.
  - `sitio` trae también **`_idiomas`** (idiomas activos y si se indexan), **`_i18n`** (con `?lang=`: los textos fijos de la web traducidos, `{ original: traducción }`) y **`_scripts`** (los códigos activos de Sunqu › Scripts y seguimiento). Los usa el servidor de la web al armar cada página; no viajan al navegador.
  - `wp sunqu exportar` y `verificar` ignoran `_imagenes`, `_modificado`, `_idiomas`, `_i18n` y `_scripts`, porque solo existen en WordPress.
- **Caché:**
  - las respuestas se guardan en caché hasta que se guarda cualquier contenido (y con `Cache-Control: max-age=60`);
  - al guardar algo **publicado** (publicar, editar, despublicar o borrar), el plugin avisa a la web (`POST {URL de la web}/_sunqu/purge` con el **Token de purga**) para que renueve la suya. Los borradores, los autoguardados y los envíos de formularios no avisan;
  - *LiteSpeed Cache*, si está en el CMS, no guarda estas respuestas (`litespeed_control_set_nocache`): la web ya tiene su propia caché;
  - herramientas en Sunqu › Conexión: **Limpiar caché de la API** y **Vaciar caché de la web** (muestra si la web respondió).
- **CORS** (toda la API): solo responde a los **Orígenes permitidos** de Sunqu › Conexión y al propio CMS.
  - Si la lista está vacía, al origen de la **URL de la web**; si tampoco hay URL, a `https://sunqujourneys.com`.
  - Nunca a «cualquiera» ni con credenciales.
- **No indexar:** la API responde con `X-Robots-Tag: noindex`. El tema desactiva `wp-sitemap.xml` y, en `robots.txt`, solo permite `/wp-content/uploads/`.

### Formularios

`POST /formularios/{tipo}` con `Content-Type: application/json`. La web ya los envía mediante `SunquContent.enviar(tipo, datos)`.
- **Sin `api` en la vista previa estática:** solo muestra la confirmación, como antes.
- **Sin `api` en producción:** muestra el mensaje de error con la vía de WhatsApp, para no dar por enviado algo que no llegó.

| Tipo | Campos (* obligatorios) | Queda en |
|---|---|---|
| `solicitud` | nombre*, email*, celular + celular_prefijo + celular_pais, pais (ISO) + pais_nombre, mes (`no-se`, `ene-mar`…), fuente (`instagram`, `youtube`, `tiktok`, `conocidos`), grupo (`[[adultos, niños, bebés], …]` por habitación), sentir, mensaje, guardados, pagina (`home`, `descubre`, `journey:<slug>`) | Sunqu › Solicitudes de viaje + aviso a Sunqu + **copia completa a la persona** |
| `resena` | nombre*, pais* (ISO) + pais_nombre, email, texto*, permiso*, tema, enlace, estrellas, fotos `[{nombre, data}]` (data URL, máx. 4) | Reseñas › Pendiente + aviso a Sunqu + correo «Gracias por enviar tu reseña» (en revisión). Al publicarla en el panel, «Tu reseña ya es pública». La caja «Permiso de publicación» muestra cuándo lo aceptó el autor, su correo (no se publica) y si salieron los dos avisos |
| `reclamacion` | nombre*, email*, tipoDoc*, numDoc*, celular*, domicilio*, servicio*, descripcion*, tipo*, detalle*, pedido*, acepta*, apoderado (* si `menor`), monto, fecha, codigo, identificacion, menor | Sunqu › Libro de reclamaciones (con la constancia de la aceptación del tratamiento de datos) + correo al consumidor y a la empresa |

Los obligatorios del servidor son los de siempre; los campos nuevos (celular, país, grupo, correo de la reseña) se validan si llegan, así un formulario anterior que quede en caché sigue funcionando. En la web, celular, país y el correo de la reseña son obligatorios. Los correos a la persona salen en el idioma de la página, con la marca, «Responder» le escribe a Sunqu y como máximo 5 por dirección cada 24 horas ([FORMULARIOS.md](FORMULARIOS.md)).

- **Respuestas:** `{ "ok": true }` (la reclamación agrega `"codigo"`); el ID interno ya no se devuelve. Los errores devuelven `{ "code", "message" }` con estado:
  - 400: falta un campo o el correo no es válido;
  - 403: otro origen, o Turnstile no validó;
  - 413: el envío pasa de 16 MB;
  - 429: más de 8 envíos en 10 minutos desde la misma IP.
- **Origen:** desde un navegador solo se aceptan los orígenes de la web (Sunqu › Conexión). Otro sitio recibe 403.
- **Fotos de reseñas:** solo JPG/PNG/WebP reales, de hasta 4 MB y 3000 px. Se vuelven a codificar sin EXIF ni GPS, con nombre `resena-{id}-{n}`.
- **Turnstile (opcional):** con `define('SUNQU_TURNSTILE_SECRET', '…')` en `wp-config.php`, cada envío debe traer un token válido (`_ts`); la web lo agrega si tiene `TURNSTILE_SITE_KEY`. Configura las dos claves o ninguna.
- **Anti-spam:**
  - primero, el límite por IP (8 envíos cada 10 minutos por tipo);
  - después, se considera bot si llega el campo trampa `website` o si `_t` (milisegundos desde que se abrió la página, lo agrega `SunquContent.datosDe`) es menor que 2500;
  - con un bot, la respuesta es `ok`, para no dar pistas: solicitudes y reseñas se descartan, y las hojas de reclamación quedan como **borrador «[posible spam]»**, sin correos, por si fuera una persona;
  - la IP sale de `REMOTE_ADDR`: con la CDN de Hostinger ya es la del visitante. Detrás de otra CDN, ajústala con el filtro `sunqu_ip_cliente`.
- **Libro de reclamaciones:**
  - el **código correlativo** (`SJ-2026-00001`) lo asigna el servidor (opción `sunqu_reclamacion_n`), y la web muestra ese código;
  - la constancia al consumidor se envía como máximo 3 veces por correo cada 24 h;
  - si se supera, la hoja se guarda igual y queda anotado «Constancia por correo: No enviada».
- **Destino de los avisos:** **Sunqu › Conexión › Avisos de los formularios** (uno o varios correos). Si está vacío, **sunqujourneys@gmail.com**. Correo HTML con «Responder a» = el visitante, SMTP propio (`SUNQU_SMTP_*` en `wp-config.php`) y avisos opcionales al celular (Telegram, webhook): ver [FORMULARIOS.md](FORMULARIOS.md).

---

## 7. Cómo viaja un cambio (ejemplo)

Cambiar el precio de «El Alma de los Andes»:

1. wp-admin › **Journeys** › El Alma de los Andes › *Ficha › Precio* = «Desde USD 4.500» › **Actualizar**.
2. El plugin vacía su caché y avisa a la web (purga).
3. La siguiente visita a `/journeys/alma` lee el contenido nuevo de WordPress y muestra el precio nuevo, con su SEO y su JSON-LD al día. Sin token de purga, se ve al vencer la caché de la web (5 minutos).
4. (Opcional) Para que el respaldo estático también lo tenga: `wp sunqu exportar --dir=content` y commit.

---

## 8. Recetas

### 8.1 Agregar un journey, una experiencia o un producto

wp-admin › **Journeys** (o Experiencias, Productos) › **Agregar**. Completa el título, el **slug** y los campos, fija el **Orden** y publica.
- Un journey nuevo aparece en la portada, en el catálogo y en `journey.html?j=<slug>`.
- En **Descubre › Guardados** la clave del journey es `j-<slug>`, así que no cambies el slug después de publicarlo.

### 8.2 Agregar un campo nuevo

Ejemplo: «Idiomas» en cada experiencia.

1. Agrega la clave en los datos: en `content/experiencias.json`, `"idiomas": "Español, inglés"` en cada ítem.
2. Regenera el esquema: `python3 wordpress/tools/generar-schema.py`. El campo aparece en el panel. Si quieres una etiqueta bonita, agrégala en `LABELS` dentro del script.
3. Úsalo en la página:
   - si la vista ya pasa el objeto completo (como `EXPS` en `js/sunqu-content.js › views.descubre`), el dato llega solo;
   - si no, agrégalo en la vista correspondiente.
   Después muéstralo en la plantilla con `{{ e.idiomas }}` dentro de su `<sc-for>`.
4. Importa y verifica: `wp sunqu importar --dir=… --solo=experiencias` y `wp sunqu verificar --dir=…`.

Tipos que infiere el generador:
- **Por el nombre de la clave:** `img`, `photo`, `card`, `poster` → imagen; `video` → archivo; `bg`, `fg`, `color` → color; `months` → meses.
- **Por el valor:** número → número; `true`/`false` → sí/no; texto largo → área de texto; lista de textos → lista; lista de objetos → repetidor; objeto → grupo.
- **Claves especiales:** `c`, `dest`, `topic`, `plat` → opciones tomadas del contenido.
- **Claves que faltan en algunos ítems** → opcionales. La API las omite si están vacías.

### 8.3 Hacer editable un texto fijo de la plantilla

1. En el JSON de la página (por ejemplo `content/inicio.json`) agrega `"textos": { "heroTitulo": "El Perú no es un destino." }`.
2. Regenera el esquema (paso 8.2).
3. En `js/sunqu-content.js › views.home` devuelve `textos: c.inicio.textos` y en la página agrega `textos: SunquView.textos` al `return` de `renderVals()`.
4. En el HTML reemplaza el texto por `{{ textos.heroTitulo }}`.

### 8.4 Publicar WordPress en producción

Paso a paso en [DEPLOYMENT.md › 5](DEPLOYMENT.md#5-wordpress-en-cmssunqujourneyscom-). En resumen:
1. WordPress en `cms.sunqujourneys.com` con HTTPS.
2. Plugin y tema desde los zips de `tools/empaquetar-wordpress.sh`, Secure Custom Fields, «Nombre de la entrada» y «Disuade a los motores de búsqueda».
3. Importar con el botón del panel.
4. En **Sunqu › Conexión**: URL de la web, token de purga y avisos de los formularios.
5. SMTP en `wp-config.php` ([FORMULARIOS.md › 2](FORMULARIOS.md)) y **Enviar correo de prueba**.
6. `WORDPRESS_API_URL` en el `.env` de la web.

Recomendado: copias de seguridad diarias.

### 8.5 Mantener el respaldo estático al día

`wp sunqu exportar --dir=content`, revisa el diff y haz commit. Si WordPress no responde, la web mostrará ese contenido.

### 8.6 Moderar reseñas y ver envíos

- **Reseñas › Pendientes**: revisa, completa (Tema, «Qué vivió o compró») y **Publica**.
- **Sunqu › Solicitudes de viaje** y **Sunqu › Libro de reclamaciones**: cada envío con todos sus datos. Son privados y no salen por la API.

### 8.7 Idiomas: URLs, detección y traducción (Sunqu › Idiomas + Loco Translate)

**URLs.** Cada idioma tiene las suyas, con el mismo camino y los mismos slugs: `/es/`, `/es/descubre`, `/es/journeys/alma` · `/en/`, `/en/descubre`, `/en/journeys/alma`. Idiomas listos para activar: inglés, portugués, francés, alemán, italiano y ruso (el español siempre está activo, porque es el idioma en que se escribe el contenido).
- La **raíz** (`https://sunqujourneys.com/`) no tiene idioma: lleva (302, sin caché) a `/es/` o `/en/`.
- Las URLs de antes sin prefijo (`/descubre`, `/journeys/alma`, `/diario/…`) eran en español: redirigen 301 a `/es/…`.
- Un idioma conocido pero no activo (`/fr/…`) redirige (302) a la misma página en español.

**Cómo se elige el idioma en la raíz** (`server/lib/idiomas.php › sunqu_idioma_inicial`), en este orden:
1. **Preferencia guardada**: la cookie `sunqu_language` que escribe el selector de idioma. Si después la persona entra a una URL de otro idioma, la URL manda y la preferencia pasa a ser ese idioma.
2. **Idioma del navegador** (`Accept-Language`, en su orden de preferencia: `en-US` → `en`), si está activo.
3. **País aproximado por IP**, solo como respaldo: español para Sudamérica, Centroamérica, España, México y el Caribe hispanohablante; portugués para Brasil; ruso para Rusia; inglés para el resto. Necesita un servicio configurado en el `.env` de la web (`GEOIP_URL`, ver `.env.example`) o una CDN que mande el país; si no, se salta. Excepciones por país en Sunqu › Idiomas (`FR=fr`).
4. **Inglés** (o español mientras el inglés no esté activo).

Una URL con idioma (`/es/…`) nunca se redirige: la autodetección solo corre en la raíz. Los buscadores no pasan por la consulta de país.

**Activar y traducir un idioma:**
1. **Plugins › Añadir nuevo › «Loco Translate»** › Activar.
2. **Sunqu › Idiomas**: marca **Activo**, elige la variante (`en_US`/`en_GB`, `pt_BR`/`pt_PT`…) y guarda. El selector de idioma de la web (cabecera y menú) aparece cuando hay más de un idioma; se puede ocultar mientras se traduce.
3. **Loco Translate › Plugins › Sunqu CMS › Nuevo idioma**: el mismo idioma y variante, ubicación **Personalizado** (`wp-content/languages/loco/plugins/`: no se pierde al actualizar el plugin). Traduce y **Guarda**.
4. La web muestra las traducciones en unos minutos (al instante con Sunqu › Conexión › **Vaciar caché de la web**). Sunqu › Idiomas muestra el porcentaje traducido de **Páginas**, **SEO** y **Contenido**.
5. Cuando la traducción esté completa y revisada, marca **Publicar en buscadores**. Hasta entonces el idioma se puede visitar pero lleva `noindex` y no entra al sitemap ni a los `hreflang` ([SEO.md › 9](SEO.md)).

El **inglés es el idioma por defecto** para quien no coincide con ninguno: actívalo cuando tenga traducciones, porque desde ese momento lo verán los visitantes de otros países (lo que falte se muestra en español).

**Qué se traduce.** La plantilla `languages/sunqu-cms.pot` trae todos los textos, separados por **contexto** (columna «Contexto» en Loco):
- `interfaz`: textos fijos de las páginas, de `js/` y del servidor (botones, formularios, aviso de cookies, mensajes de error, correos a la persona). Los junta `tools/extraer-textos.py` en `wordpress/sunqu-cms/i18n/textos-web.php`: si cambias un texto de una plantilla, de `js/` o de `server/`, vuelve a correrlo y empaqueta el plugin;
- `seo`: títulos y descripciones de Sunqu › Sitio › SEO (cada idioma tiene los suyos; conviene escribirlos, no traducirlos palabra por palabra);
- `contenido`: journeys, experiencias, Diario, legales y ajustes, tal como están publicados en español. No se traducen slugs, imágenes, colores, enlaces ni datos de contacto.

El plugin regenera la plantilla cuando cambia el contenido; en Loco, abre el idioma y pulsa **Sincronizar**. Un texto que cambia en español vuelve a quedar sin traducir, así nunca se muestra una traducción vieja. Lo que no tiene traducción se muestra en español, y fuera de producción la consola del navegador avisa de los textos de `js/` sin traducir. El ruso se ve con la fuente del sistema: Aeonik no trae letras cirílicas.

### 8.8 Tag Manager, Analytics u otros scripts (Sunqu › Scripts y seguimiento)

Para agregar Google Tag Manager, Google Analytics, Meta Pixel, Hotjar, Clarity, un chat… sin tocar el servidor:

1. **Sunqu › Scripts y seguimiento › Agregar un script**: nombre, código (tal como lo entrega el servicio), **dónde** (`<head>`, al empezar el `<body>` o al final) y **cuándo**:
   - **Con permiso de analítica / de marketing** (lo normal para medición y publicidad): el código queda inerte hasta que la persona acepta en el aviso de cookies, que aparece solo y nombra cada servicio. Sus `<noscript>`, `<img>` e `<iframe>` se quitan.
   - **Siempre (necesario)**: se inserta tal cual, sin pedir permiso. Solo para lo imprescindible. Un código de Google (GTM o gtag.js) marcado así funciona en *Consent Mode*: carga desde el principio, sin cookies hasta que la persona acepta.
2. **Guardar scripts**. La web los aplica en segundos en todas las páginas e idiomas.
3. Si aparece un aviso amarillo **«Revisa: …»**, corrígelo: el panel revisa cada código activo (p. ej. un Tag Manager con el ID `G-…` de Analytics en vez de `GTM-…`, o Hotjar marcado «Siempre»). Un JavaScript pegado sin `<script>…</script>` se envuelve solo.
4. La web autoriza sola los dominios que aparecen en el código **y todos sus subdominios** (`t.contentsquare.net` → `*.contentsquare.net`), los de Google y Meta, y los conocidos de Hotjar/Contentsquare, Clarity, TikTok, LinkedIn y Tawk.to. Si aun así la consola del navegador muestra que la política de seguridad (CSP) bloqueó algo, agrega ese dominio en **Dominios adicionales** (`https://*.servicio.com`, `wss://…`).

Más detalles (eventos, GTM, consentimiento) en [ANALYTICS.md › 7](ANALYTICS.md) y la decisión de seguridad en [SECURITY.md](SECURITY.md). Usa esta vía **o** los IDs del `.env` (`GTM_ID`, `GA4_ID`, `META_PIXEL_ID`), no las dos.

---

## 9. Convenciones del código

- **Plantillas (`<x-dc>`).** `{{ valor }}` inserta datos, `<sc-for list as>` repite y `<sc-if value>` condiciona.
- **La lógica.** Está en `<script type="text/x-dc">` (clase `Component`, método `renderVals()`). Los datos de contenido llegan por `SunquView` (por ejemplo `J = SunquView.J`).
- **Indentación.** Solo con **tabs y saltos de línea**. El runtime descarta el espacio en blanco sin el carácter espacio, pero un espacio entre dos etiquetas se renderiza como texto.
- **Textos insertados.** Cada `{{ texto }}` se envuelve en `<span class="sc-interp">`, así que en CSS no uses selectores `span` sin clase.
- **Estilos.** En `css/<página>.min.css`. `home.min.css` es de `index.html` y de `Sunqu Journeys v2.dc.html`. `index.html` es una copia exacta de `Sunqu Journeys v2.dc.html`: edita la v2 y cópiala.
- **Imágenes en el contenido.** Pueden ser una URL completa, una ruta `assets/…` o un id de Unsplash. `SunquContent.img(valor, ancho, calidad)` resuelve las tres.
- **Si cambias la estructura de `content/*.json`:**
  1. actualiza la vista en `js/sunqu-content.js`;
  2. regenera `schema.json`;
  3. corre `wp sunqu verificar`.

---

## 10. Solución de problemas

| Síntoma | Causa y solución |
|---|---|
| La página queda en blanco | La consola muestra `[sunqu] …`. Revisa que `content/*.json` exista (en local, sirve la carpeta con un servidor: `python3 -m http.server`) o que la API responda |
| La consola dice «no se pudo leer WordPress, se usa content/*.json» | La API no responde o hay un error de CORS. Abre la URL de la API en el navegador y revisa Sunqu › Conexión › Orígenes permitidos |
| `/wp-json/` responde 404 | Enlaces permanentes en «Simple». Cámbialos a «Nombre de la entrada» (en Apache hace falta `.htaccess`) |
| No veo los campos en el panel | Falta activar **Secure Custom Fields** o ACF Pro |
| Las fotos no se importaron | Sin internet no se descargan las de Unsplash: se guarda la referencia y la web las muestra igual. Reintenta `wp sunqu importar` con conexión |
| No llegan los correos | Configura el SMTP y revisa la caja «Aviso a Sunqu» de cada envío ([FORMULARIOS.md › 4](FORMULARIOS.md)). Los envíos igual quedan guardados en el panel |
| Un cambio no aparece en la web | Sunqu › Conexión › **Vaciar caché de la web**. Si responde 403, el token no coincide con `CACHE_PURGE_TOKEN`; si no responde, revisa la URL de la web. Si hay CDN, púrgala |
| Los formularios responden «Formulario desconocido» | Plugin anterior a esta versión: el campo `tipo` del libro de reclamaciones pisaba el tipo de la ruta. Actualiza `sunqu-cms` |
| Docker se queda en «Created» y no arranca contenedores | El uso compartido de archivos de Docker Desktop quedó trabado. Reinicia Docker Desktop (`docker desktop restart`) y revisa Settings › Resources › File sharing |
| `wp sunqu verificar` muestra diferencias | Alguien editó en el panel después de importar (es normal) o cambió la estructura sin regenerar el esquema |
| `/en/…` lleva a `/es/…` | El idioma no está activo en Sunqu › Idiomas (o la web todavía tiene la caché anterior: Vaciar caché de la web) |
| La raíz siempre lleva al mismo idioma | Es la preferencia guardada (cookie `sunqu_language`) o el idioma del navegador. Prueba en una ventana privada o cambia de idioma con el selector |
| Una traducción de Loco no aparece | Revisa que esté en la ubicación **Personalizado** y con la misma variante elegida en Sunqu › Idiomas; luego Vaciar caché de la web. Un texto que cambió en español necesita su traducción nueva |
| Un servicio de Scripts y seguimiento no mide | Consola del navegador: si la CSP lo bloquea, agrega el dominio en «Dominios adicionales». Si el script es «Con permiso», solo carga después de «Aceptar» en el aviso de cookies |

---

## 11. Estado y pendientes

**Probado en un WordPress real** (Docker: WordPress 6.9, PHP 8.3, MariaDB 11, Secure Custom Fields):
- `wp sunqu importar` con imágenes: 36 archivos en la biblioteca. `wp sunqu verificar` da «WordPress devuelve exactamente el contenido de los JSON» en las 9 secciones.
- **La web de producción** (servicio `sitio`) lee WordPress desde el servidor. Las 9 páginas probadas renderizan el mismo DOM que la versión estática; solo cambian las URLs de las imágenes (biblioteca de WordPress, con su `srcset`).
- **Formularios desde el navegador:** 3 solicitudes, 1 reseña (con su constancia de permiso) y 1 hoja de reclamación, guardadas en el panel.
- **Anti-spam:** los envíos con la trampa llena, sin `_t` o con `_t` de 900 ms no se guardan.
- **Validaciones del servidor:** correo inválido, permiso faltante, celular faltante y apoderado faltante devuelven 400.
- **CORS:** el origen de la web recibe `Access-Control-Allow-Origin`; otro origen no.
- **Purga:** editar el título de un journey con WP-CLI se ve en la web en la petición siguiente. El endpoint rechaza GET, POST sin token y token incorrecto (403).

**Formularios e idiomas (plugin 1.5.0)**, probados en Docker con WordPress 7.1.2, PHP 8.3, Loco Translate y una traducción de prueba al inglés:
- Raíz: `en-US` → `/en/` (navegador); `es-PE` → `/es/`; navegador en un idioma no activo + país PE o MX → `/es/` (IP); cookie `sunqu_language=es` con navegador en inglés → `/es/` (preferencia); sin datos → `/en/`. Los buscadores no consultan el país. La respuesta de la raíz no se guarda en caché.
- `/descubre`, `/journeys/alma`, `/journey.html?j=alma` → 301 a `/es/…`; `/fr/…` (no activo) → 302 a `/es/…`; `/es`, `/en` → `/es/`, `/en/`.
- Servicio de país simulado: caché por red sin guardar la IP, corte a los 800 ms, pausa tras un fallo e IPs privadas sin consulta.
- En el navegador: el selector guarda `sunqu_language` y mide `language_changed`; la raíz mide `language_detected` con su fuente; una URL `/es/…` manda sobre la preferencia y la actualiza.
- «Diseña tu viaje» en las tres páginas y «Comparte tu viaje», en español y en inglés: país con buscador (sin tildes, por teclado), prefijo con bandera que sigue al país, «+51 …» escrito en el número pasa al selector, Mi grupo con límites. La solicitud llega a Sunqu y la copia completa a la persona (en inglés desde `/en/`); la reseña envía «en revisión» y, al publicarla con WP-CLI, «ya es pública» una sola vez. El correo del autor no sale por la API.
- Formularios anteriores (sin los campos nuevos) siguen funcionando; un grupo manipulado se recorta a los límites.

**Idiomas y scripts (plugin 1.4.0)**, probados en Docker con WordPress 7.1.2, PHP 8.3, Loco Translate y una traducción de prueba al inglés (con las URLs de entonces: español sin prefijo):
- `?lang=en` entrega el contenido y los textos fijos traducidos; `?lang=pt` sin traducciones y un idioma inactivo devuelven el español. Con el panel en inglés, `/pt` sigue en español (no toma las traducciones de otro idioma).
- `/en`, `/en/descubre`, `/en/journeys/alma`, `/en/diario/…`, `/en/terminos`, `/en/libro-de-reclamaciones` y `/en/recuerda` responden 200 con `<html lang="en">`, canonical y `hreflang`; `/es/…` redirige a la URL sin prefijo y un idioma inactivo da 404. Las 8 páginas cargan sin errores en la consola.
- El selector de idioma (cabecera y menú) abre la misma página en el otro idioma, con sus filtros y su ancla.
- Los mensajes de error de los formularios llegan en inglés con `?lang=en`.
- Scripts: guardados desde el formulario del panel (con historial y aviso por correo); los «Con permiso» quedan inertes hasta «Aceptar», se activan una sola vez y sin bloqueos de la CSP; «Solo necesarias» recarga la página sin ellos. `CMS_SCRIPTS=off` y `CONSENT_MODE=off` se comportan como se espera.
- Guardar Idiomas o Scripts por primera vez vacía la caché de la web; visitar el panel no genera purgas en cadena. `wp sunqu verificar` sigue igual.

**Corregido en esta versión:** el libro de reclamaciones respondía 404 («Formulario desconocido»). WordPress da prioridad al campo `tipo` del cuerpo sobre el `{tipo}` de la ruta; ahora el tipo se lee de la ruta.

**Pendientes de negocio:**
- RUC y dirección reales (Sunqu › Sitio › Empresa);
- teléfono y enlaces de redes (hoy `#`);
- reseñas reales (las actuales son de ejemplo);
- revisión legal de los Términos (incluidas las cookies);
- SMTP para los correos;
- traducciones EN/PT en Loco Translate (la integración está lista; faltan los textos traducidos y revisarlos antes de «Publicar en buscadores»).
