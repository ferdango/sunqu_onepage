# Sunqu Journeys · SEO

Todo el SEO se genera en el servidor (`server/lib/seo.php`, `sinjs.php`, `sitemap.php`, `routes.php`) a partir del
contenido real (WordPress o `content/*.json`). En el código solo hay a mano la marca «Sunqu Journeys» y los sufijos
«— Diario Sunqu» / «— Sunqu»; los títulos y descripciones de las páginas fijas se editan en Sunqu › Sitio › SEO.

---

## 0. Auditoría y puntuación (29/09/2026)

**Alcance:**
- las 20 URLs del sitemap: inicio, Descubre, 5 journeys, Recuerda, Diario, 9 artículos, Términos y Libro de reclamaciones;
- redirecciones, 404, robots, sitemap y la versión sin JavaScript.

**Método:**
- se midió la versión de producción (capa PHP + `.htaccess`) en Docker con Apache + PHP 8.3, porque `sunqujourneys.com` aún no está publicado;
- revisión del HTML servido y del DOM renderizado: títulos, descripciones, canonical, encabezados, `alt`, enlaces y JSON-LD;
- Lighthouse 12 en móvil (4G lento simulado) en las 8 plantillas.

La puntuación por área es una **evaluación propia con criterios explícitos**, no una métrica oficial. Lighthouse
SEO mide solo lo básico y ya daba 100.

### Puntuación por área

| Área | Peso | Antes | Ahora | Qué la explica |
|---|---|---|---|---|
| Rastreo e indexación | 15 | 13 | **15** | Robots, sitemap, canonical, 301 y 404 reales ya estaban bien. Faltaba: `//ruta` servía la portada con 200 (duplicado); los JSON de respaldo eran indexables; la búsqueda interna (`?q=`) era indexable |
| Metadatos | 15 | 9 | **14** | Títulos de journeys sin tipo ni duración; descripciones de 59–94 caracteres; títulos de artículos de hasta 78 (cortados en Google); favicons **no cuadrados** (27×32 y 154×180: Google no los muestra). Queda sin `twitter:site` (no hay cuenta de X) |
| Contenido y semántica | 20 | 10 | **13** | Saltos de encabezado en 3 páginas y fotos del hero sin `alt` (corregidos). **Pendiente principal: los 9 artículos tienen entre 51 y 222 palabras** (contenido delgado) |
| Datos estructurados | 10 | 8 | **9** | Grafo completo y válido. Se agregó `areaServed`, `priceRange` y `dateModified` real. Faltan teléfono, dirección y `sameAs`: datos del negocio |
| Rendimiento (Core Web Vitals) | 15 | 8 | **11** | Lighthouse móvil 72–89 → 89–97. LCP 3,7–5,1 s → 2,6–3,6 s. Ver «Cómo leer el rendimiento» |
| Contenido sin JavaScript | 10 | 1 | **8** | Antes, quien no ejecuta JS (otros buscadores, vistas previas, asistentes de IA) veía ~10 palabras. Ahora recibe el contenido real (journeys, artículos completos, términos) en `<noscript>` |
| Móvil y accesibilidad | 5 | 4 | **5** | Lighthouse accesibilidad 95–100 → 96–100. Lo que aún marca son falsos positivos (sección 5) |
| SEO local e internacional | 10 | 2 | **2** | Sin teléfono ni dirección reales, sin Google Business Profile, redes en `#`, un solo idioma (EN/PT pendientes) |
| **Total** | **100** | **55** | **77** | Nivel intermedio. Para pasar de 85 hace falta sobre todo contenido y datos del negocio (sección 8), no código |

### Lighthouse móvil, antes → ahora

| Página | Rendimiento | Accesibilidad | Buenas prácticas | SEO | LCP |
|---|---|---|---|---|---|
| Inicio | 72 → **91** | 96 → 96 | 100 | 100 | 5,0 → **2,7 s** |
| Journey (`/journeys/alma`) | 83 → **90** | 95 → 96 | 100 | 100 | 4,6 → 3,5 s |
| Descubre | 84 → **91** | 95 → 97 | 100 | 100 | 4,4 → 3,4 s |
| Recuerda | 81 → **89** | 100 | 96 | 100 | 5,1 → 3,6 s |
| Diario | 88 → **93** | 98 → **100** | 100 | 100 | 3,8 → 3,1 s |
| Artículo | 85 → **91** | 100 | 100 | 100 | 4,3 → 3,4 s |
| Términos | 89 → **97** | 100 | 100 | 100 | 3,7 → 2,6 s |
| Libro de reclamaciones | 88 → **96** | 100 | 100 | 100 | 3,8 → 2,7 s |

CLS 0 en todas antes y después. TBT entre 0 y 90 ms.

### Cómo leer el rendimiento

En el entorno de prueba, **la mayor parte de la mejora vino de comprimir el JavaScript**:
- `.htaccess` no comprimía `text/javascript`, el tipo que usa Apache para `.js`, y React viajaba con 131 KB en lugar de 43 KB;
- es muy probable que Hostinger (LiteSpeed y su CDN) ya lo comprima, así que allí esa parte de la ganancia no se va a ver.

Medido aparte, con la compresión ya corregida (mediana de 3 corridas):
- **lo que aportan las precargas y el `defer`** se ve sobre todo en el inicio: LCP de 3,5 a 2,7 s;
- en el journey y en Descubre queda igual (3,4–3,6 s).

**Qué sigue limitando el LCP** en móvil: las páginas se dibujan con JavaScript (runtime + React). Bajarlo de 2,5 s en
todas exige generar la primera pantalla en el servidor; está anotado en la sección 8.

**La medición que cuenta** es la de campo: PageSpeed Insights / Search Console, con visitas reales, cuando la web esté publicada.

### Qué se cambió

| Cambio | Dónde |
|---|---|
| `//ruta` y `/journeys//alma/` → 301 a la ruta normalizada (nunca a otro dominio). Rutas de más de 2048 bytes o con UTF-8 inválido → 404 | `routes.php` |
| Archivos internos, copias y volcados → 404. `X-Robots-Tag: noindex` en los JSON | `.htaccess` |
| `/descubre?q=` → `noindex, follow` | `seo.php` |
| `robots` con `max-snippet:-1` y `max-video-preview:-1` | `seo.php` |
| **Títulos de journeys:** «El Alma de los Andes: journey de 8 días — Sunqu Journeys» (tipo y duración del catálogo, ≤ 62 caracteres) | `seo.php` |
| **Descripciones de journeys** de ~155 caracteres: qué es + precio («Desde USD 4.200 por persona.») + por qué | `seo.php` |
| **Artículos:** sufijo de marca solo si cabe en ~62 caracteres; descripción con extracto + entradilla | `seo.php` |
| **Páginas fijas:** títulos con palabras de búsqueda, armados con frases que ya usa la web (tabla de abajo) | `content/sitio.json` › `seo.paginas` |
| **Favicons cuadrados:** 32, 48, 180 (fondo de marca para iPhone), 192 y 512 px, y `/favicon.ico` | `assets/favicon-*.png`, `seo.php`, `.htaccess` |
| `og:image:width/height` también para imágenes locales; `article:modified_time` y `article:section` | `seo.php` |
| JSON-LD: `areaServed` (Perú) y `priceRange` («USD 600 – 6.800», del catálogo) en `TravelAgency`; `dateModified` con la última edición en WordPress | `seo.php`, plugin (`_modificado`) |
| Sitemap con `<image:image>` (foto principal de cada página) y `lastmod` real de journeys y artículos | `sitemap.php` |
| **Contenido sin JavaScript** (`<noscript>`): la información de cada página en HTML simple, con enlaces a todos los journeys y artículos | `sinjs.php` |
| Encabezados en orden (H2→H4 en el journey, H1→H3 en el Diario y en Descubre) | `journey.html`, `blog.html`, `descubre.html` (+ `css/journey.min.css`) |
| Fotos del hero con su leyenda como `alt` | `index.html` |
| Rendimiento: precarga de `support.js` + React, `defer` en los scripts de contenido, compresión de JS, conexión anticipada a Unsplash, primera fila del Diario sin carga diferida y con precarga | `page.php`, `.htaccess`, `blog.html` |

**Títulos nuevos de las páginas fijas** (antes → ahora):

| Página | Antes | Ahora |
|---|---|---|
| Inicio | Sunqu Journeys — El corazón del Perú | Sunqu Journeys — Viajes a medida al corazón del Perú |
| Descubre | Descubre · Catálogo — Sunqu Journeys | Descubre journeys y experiencias en el Perú — Sunqu Journeys |
| Diario | Blog · Diario Sunqu — Sunqu Journeys | Diario Sunqu: guías e historias para viajar al Perú |

> ⚠ Estos títulos viven en el contenido. En el WordPress de producción hay que cambiarlos en **Sunqu › Sitio › SEO**,
> o volver a importar la sección «Sitio» con el plugin nuevo. Si no, la web sigue mostrando los anteriores.

---

## 1. Dominio y URLs

- **Dominio canónico:** `SITE_URL=https://sunqujourneys.com` en el `.env`. Es la única fuente para canonical, Open Graph, JSON-LD, sitemap y redirecciones.
- **`.htaccess`:** fuerza HTTPS (301) y quita `www` (301).
- **Cada idioma con su prefijo** (`/es/…`, `/en/…`, sección 9). La raíz `/` elige el idioma y redirige (302).
- **URLs limpias sin barra final**, salvo el inicio de cada idioma (`/es/`). Con barra final, o con barras repetidas (`//descubre`), redirigen 301 a la versión limpia. Tabla completa en [ARCHITECTURE.md › 5](ARCHITECTURE.md#5-urls).
- **URLs antiguas** (`index.html`, `journey.html?j=…`, `articulo.html?a=…`, `blog.html?cat=…`, `descubre.html?q=…`, `Sunqu Journeys v2.dc.html`) y las limpias **sin prefijo** de antes (`/descubre`, `/journeys/…`): redirigen 301 a su versión `/es/…` (el inicio, a la raíz).
  - Conservan el parámetro útil y los de campañas (`utm_*`, `gclid`, `fbclid`, `gbraid`, `wbraid`, `msclkid`), así los enlaces viejos compartidos en redes siguen midiéndose.
- **404 real:** un slug de journey o de artículo que no existe devuelve 404, con `noindex`, `server/404.html` y enlaces a Inicio y Descubre. También dan 404 los archivos internos y de respaldo.
- **Enlaces internos:** en el HTML que sirve el servidor, todos los enlaces `.html` se reescriben a su URL limpia. Queda 0 enlaces `.html`.

## 2. `<head>` por página

| Etiqueta | Valor |
|---|---|
| `<title>` | Páginas fijas: `sitio.seo.paginas.<página>.title`. Journey: `<título>: journey de <días> — Sunqu Journeys` (o `escapada`). Artículo: `<título> — Diario Sunqu`, `— Sunqu` o solo el título, según lo que quepa en ~62 caracteres |
| `meta description` | Páginas fijas: `…description`. Journey: `ficha.what` + precio + `ficha.why`, ~158 caracteres. Artículo: extracto + entradilla, ~158 caracteres |
| `robots` | `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`. Búsqueda interna (`/descubre?q=`): `noindex, follow`. En 404 y en `APP_ENV=staging`: `noindex, nofollow` |
| `canonical` | `SITE_URL` + ruta limpia del idioma, sin parámetros (`/es/diario?cat=x` apunta a `/es/diario`, `/es/descubre?q=x` a `/es/descubre`; en inglés, `/en/…`) |
| `hreflang` | Solo con más de un idioma publicado en buscadores (sección 9): la misma página en cada uno y `x-default` (inglés, o español mientras el inglés no se publique) |
| Open Graph | `og:site_name`, `og:locale` (es_PE, en_US, pt_BR… según la página) y `og:locale:alternate` de los otros idiomas publicados, `og:type` (`article` en artículos), `og:title`, `og:description`, `og:url`, `og:image` con `alt` y dimensiones (y `og:image:type` en las locales), `article:published_time`, `article:modified_time`, `article:section` |
| Twitter / X | `summary_large_image`, título, descripción, imagen y su texto alternativo (`twitter:image:alt`) |
| Iconos | 32, 48 y 192 px (`rel="icon"`), 180 px (`apple-touch-icon`), todos cuadrados, y `/favicon.ico` |
| Precargas | `support.js`, React y ReactDOM; fuentes Aeonik Regular, Medium y Bold; imagen principal (hero, journey, artículo o primera tarjeta del Diario) con `imagesrcset` igual al del `<img>`; conexión anticipada a `images.unsplash.com` |

**Imagen para compartir:**
- inicio y páginas fijas: la imagen de marca del idioma de la página (abajo);
- journey: la primera foto de su galería;
- artículo: su imagen;
- journey o artículo sin foto: la imagen de marca.

Las fotos de Unsplash se piden recortadas a 1200×630. Las de WordPress se usan en su URL original.

**Imagen de marca** (`assets/og/sunqu-og-es.jpg`, `-en`, `-pt`, `-fr`, `-de`, `-it`, `-ru`):
- 1200×630, JPEG de ~50 KB, sin fotos: se reconoce también en la miniatura de WhatsApp;
- sigue el póster del manual de marca: fondo oliva, las cuatro formas de la marca, el logo, «Viaja al corazón del Perú.» en el idioma de la página y sunqujourneys.com;
- la URL lleva `?v=`, que cambia cuando cambia el archivo: así WhatsApp, Facebook y LinkedIn piden la imagen nueva en lugar de usar la que guardaron;
- se sirve siempre en JPEG (el `.htaccess` no la cambia por AVIF/WebP), con medidas, `og:image:type` y texto alternativo («Sunqu Journeys · Viaja al corazón del Perú»).

Para cambiar sus textos: `server/lib/og.php` (los mismos sirven de texto alternativo), y después correr `tools/imagen-og.php` (el comando está al principio del archivo). El español sale de la web. **Los textos en inglés, portugués, francés, alemán, italiano y ruso son propuestas: revisarlos con una persona nativa antes de publicar ese idioma.** El ruso usa Avenir Next, porque Aeonik no tiene letras cirílicas.

Los datos estructurados (`primaryImageOfPage`) y el sitemap de imágenes siguen usando las fotos de la web, no la imagen de marca.

**Dónde se editan los textos de las páginas fijas:** **Sunqu › Sitio › SEO (buscadores y redes sociales) › Páginas fijas**.

## 3. Datos estructurados (JSON-LD)

Un solo `@graph` por página, con `@id` estables:

| Nodo | Dónde | Datos |
|---|---|---|
| `TravelAgency` `#organizacion` | todas | Nombre, razón social, URL, logo, correo, dirección (Lima, PE), `areaServed` (Perú) y `priceRange` (del catálogo). **Solo si son datos reales:** teléfono (el actual `+51 000 000 000` se omite), `taxID` (el RUC «por confirmar» se omite) y `sameAs` (las redes en `#` se omiten) |
| `WebSite` `#sitio` + `SearchAction` | todas | Búsqueda: `/descubre?q={search_term_string}`. Google ya no muestra el cuadro de búsqueda en resultados (2024), pero el marcado no molesta y lo usan otros buscadores |
| `WebPage` / `CollectionPage` `#pagina` | todas | Nombre, descripción, idioma, imagen principal, migas |
| `BreadcrumbList` `#migas` | todas menos el inicio | Inicio › sección › página |
| `ItemList` `#journeys` | /descubre | Journeys en el orden del catálogo |
| `TouristTrip` `#journey` | /journeys/{slug} | Nombre, descripción, imagen, proveedor, itinerario (las paradas de `ficha.route`) y oferta |
| `Blog` `#diario` | /diario | Las entradas publicadas |
| `BlogPosting` `#articulo` | /diario/{slug} | Titular, descripción, imagen, `datePublished`, `dateModified` (última edición en WordPress), sección, autor («Equipo Sunqu», organización) y editor |

**Oferta del journey:**
- solo aparece si el catálogo tiene un precio numérico;
- se expresa como `PriceSpecification` con `minPrice` (y `maxPrice` si hay rango) en USD, porque la web muestra «Desde USD 4.200» o «USD 600 – 900»;
- «Sunqu Origen» (precio a consultar) no lleva oferta.

**Lo que no se marca, a propósito:**
- `Review` y `AggregateRating`: las reseñas actuales son de ejemplo. Aun con reseñas reales, Google no muestra estrellas de reseñas que una empresa publica sobre sí misma;
- `Product`: los precios de la tienda son de referencia;
- `FAQPage`: el sitio no tiene preguntas frecuentes, y Google limita ese resultado enriquecido a sitios de gobierno y salud.

## 4. sitemap.xml y robots.txt

- **`/sitemap.xml`:** se genera desde el contenido, con la caché de contenido.
  - Incluye inicio, Descubre, cada journey (en el orden del catálogo), Recuerda, Diario, cada artículo, Términos y Libro de reclamaciones.
  - Cada URL lleva su foto principal (`<image:image>`).
  - `lastmod` con la última edición en WordPress (`_modificado`) o la fecha del artículo; el Diario, la del artículo más reciente.
  - Un journey o un artículo nuevo aparece solo al publicarlo.
  - Con más de un idioma publicado en buscadores, cada página aparece en cada idioma con sus alternativas (`<xhtml:link rel="alternate" hreflang>`). Un idioma sin «Publicar en buscadores» no entra.
- **`/robots.txt`:** `Allow: /` y `Disallow` de `/server/`, `/wordpress/`, `/docs/` y `/_sunqu/`, más la línea `Sitemap:`. Con `APP_ENV=staging` responde `Disallow: /`.
  - No bloquea a los rastreadores de IA (GPTBot, ClaudeBot, PerplexityBot…). Si el negocio prefiere excluirlos, es una línea por cada uno.
- **CMS (`cms.sunqujourneys.com`):**
  - robots `Disallow: /` salvo `/wp-content/uploads/` (las imágenes que usa la web);
  - sin `wp-sitemap.xml`;
  - meta `noindex` y `X-Robots-Tag: noindex` en la API.
- **Vista previa estática (GitHub Pages):** cada plantilla lleva `<meta name="robots" content="noindex">`, así la copia de ferdango.github.io no compite con el dominio real. Cuando la web esté publicada, conviene desactivar GitHub Pages.

## 5. Contenido y semántica

- `<html lang="es">` en todas las páginas (`en` o `pt` en sus versiones traducidas, sección 9).
- Un `<h1>` por página, el mismo que ya tenía el diseño. Encabezados sin saltos de nivel (corregido el 29/09/2026; los estilos van por clase, el diseño no cambió).
- Todas las `<img>` tienen `alt`: el texto del contenido o `alt=""` en las decorativas. Las fotos del hero, su leyenda.
- Los títulos de las pestañas coinciden con el SEO (`SunquContent.titulo()` usa el que envía el servidor).
- **Sin JavaScript** (`server/lib/sinjs.php`): un `<noscript>` al comienzo del `<body>` con:
  - el `<h1>` de la página;
  - el texto principal;
  - la lista de journeys (con duración, región y precio);
  - el artículo completo, los términos completos y el contacto.

  Todo sale del contenido, se escapa, y omite lo que no está confirmado: reseñas de ejemplo y precios de la tienda. Con JavaScript activo el navegador no lo muestra.
- **Falsos positivos de Lighthouse en contraste** (inicio, journey, Descubre): la página cambia el color de fondo al desplazarse (secciones oscuras y claras), y la herramienta evalúa todo el texto contra el fondo oscuro de la primera pantalla. En pantalla, ese texto está sobre fondo claro. Ver [ACCESSIBILITY.md](ACCESSIBILITY.md).

## 6. Rendimiento

| Medida | Efecto |
|---|---|
| Precarga de `support.js`, React y ReactDOM | Se descargan en paralelo con el CSS (antes, uno tras otro) |
| `defer` en `sunqu-config.js` y `sunqu-content.js` | No bloquean el primer pintado. La plantilla cruda queda oculta con CSS |
| Compresión de `text/javascript` | React 131 KB → 43 KB en Apache (Hostinger probablemente ya comprime) |
| Fuentes WOFF2 precargadas (Regular, Medium, Bold) | El texto aparece con su fuente sin saltos |
| Imagen principal precargada con `fetchpriority="high"` | Hero, journey, artículo y primera tarjeta del Diario (antes en `lazy`) |
| AVIF / WebP automáticos, `srcset`/`sizes`, `loading="lazy"` debajo del pliegue | Menos bytes por pantalla |
| Loader una vez por sesión | En el inicio suma ~2,3 s **solo** en la primera visita (decisión de diseño) |

## 7. Verificación después del despliegue

1. `curl -I http://sunqujourneys.com/descubre/` → 301 a `https://sunqujourneys.com/descubre`.
2. `https://sunqujourneys.com/journeys/no-existe` y `https://sunqujourneys.com/docs/SEO.md` → 404.
3. **Prueba de resultados enriquecidos** de Google y validador de schema.org con `/`, `/journeys/alma` y un artículo.
4. **PageSpeed Insights** (móvil) en `/`, `/journeys/alma` y un artículo. Comparar con la tabla de la sección 0.
5. **Google Search Console** (PENDIENTE DE CONFIGURACIÓN, requiere acceso al DNS):
   - propiedad de dominio `sunqujourneys.com`, con un registro TXT en el DNS de Hostinger;
   - enviar `https://sunqujourneys.com/sitemap.xml`;
   - «Inspección de URLs» en las páginas principales y «Probar URL publicada», que confirma que la CDN no bloquea a Google.
6. **Bing Webmaster Tools:** importar la propiedad desde Search Console. Bing también alimenta a varios asistentes de IA.
7. **Vista previa al compartir:** `/es/` (y `/en/`, si está activo) en el [depurador de Facebook](https://developers.facebook.com/tools/debug/) › «Volver a extraer»: la imagen de marca de ese idioma, 1200×630. Un journey: su foto. Después, una URL en WhatsApp.
8. **CDN de Hostinger:** no dejar activo el «modo bajo ataque» ni bloquear EE. UU., porque bloquea a los rastreadores ([Hostinger: crawlers y SEO](https://www.hostinger.com/support/hostinger-cdn-search-engine-crawlers-and-seo/)).

## 8. Pendiente para pasar de intermedio a avanzado

En orden de impacto. Lo de contenido y negocio no se inventó: lo tiene que aportar Sunqu.

1. **Contenido (el mayor impacto):**
   - los 9 artículos del Diario tienen entre 51 y 222 palabras; llevarlos a 800–1.500, con experiencia propia: rutas, altitudes, anfitriones, temporadas, precios orientativos;
   - sumar guías por destino y por búsqueda frecuente, por ejemplo: cómo llegar al Valle Sagrado, mal de altura en Cusco, qué comer en Arequipa, viaje a medida por Perú;
   - enlazar cada journey con los artículos que le corresponden.
2. **SEO local:**
   - crear el **Google Business Profile** (dirección u oficina real, horario, teléfono, fotos);
   - cargar en Sunqu › Sitio el teléfono, las redes (pasan solas a `sameAs`) y el RUC;
   - pedir reseñas reales en Google.
3. **Idiomas:** la estructura está lista (sección 9: `/en/…`, `/pt/…`, `hreflang`, sitemap, SEO traducido). Falta traducir en Loco Translate, revisar las traducciones y marcar «Publicar en buscadores».
4. **Autoridad:**
   - enlaces desde medios de viaje, PromPerú, directorios de turismo, alianzas con anfitriones y comunidades;
   - TripAdvisor con el mismo nombre, dirección y teléfono.
5. **Fotos propias** en lugar de Unsplash, con nombres de archivo y `alt` descriptivos (Google Imágenes).
6. **LCP < 2,5 s en todas las páginas:**
   - generar en el servidor la primera pantalla de cada plantilla, o reducir el JavaScript inicial;
   - evaluar acortar el loader en móviles (decisión de diseño).
7. **Títulos nuevos en el WordPress de producción** (sección 0) y revisión de los textos de Sunqu › Sitio › SEO por el negocio.

## 9. Idiomas (`/es/…`, `/en/…`)

Cada idioma tiene URLs propias e indexables, con las mismas rutas y slugs: `/es/`, `/es/descubre`, `/es/journeys/alma` ·
`/en/`, `/en/descubre`, `/en/journeys/alma`. Idiomas activables en WordPress › Sunqu › Idiomas: inglés, portugués,
francés, alemán, italiano y ruso (el español siempre está). Traducción: Loco Translate ([WORDPRESS.md › 8.7](WORDPRESS.md)).

| | Idioma con «Publicar en buscadores» | Idioma activo sin publicar |
|---|---|---|
| Se puede visitar | Sí | Sí (útil para revisar) |
| `robots` | `index, follow…` | `noindex, follow` |
| `canonical` | Su propia URL (`/en/…` → `/en/…`) | Ídem |
| `hreflang` (en todas sus versiones) | Sí, con `x-default` = inglés (o español mientras el inglés no se publique) | No |
| sitemap.xml | Sí, con `xhtml:link` alternos | No |
| `<html lang>`, `og:locale`, `inLanguage` (JSON-LD) | El del idioma | El del idioma |

- **La raíz** (`/`) no es una página: redirige (302, `Cache-Control: private, no-store`, `Vary: Accept-Language, Cookie, User-Agent`) al idioma de la persona (preferencia guardada, navegador, país por IP o inglés) y no entra al sitemap. Los buscadores van siempre a un idioma con «Publicar en buscadores» (el inglés si se publica; si no, el español): antes Googlebot terminaba en `/en/` con `noindex` y Google no indexaba la portada. Cada versión se descubre por los `hreflang` y el sitemap.
- **Una URL con idioma nunca se redirige** según el navegador o el país: así cada versión es rastreable.
- **Migración desde las URLs sin prefijo:** `/descubre`, `/journeys/{slug}`, `/diario/{slug}`, `/recuerda`, `/terminos`, `/libro-de-reclamaciones` y las `.html` antiguas redirigen **301** a su versión `/es/…` (eran las páginas en español), conservando los parámetros útiles y de campañas. En Search Console: enviar de nuevo `sitemap.xml` y revisar en «Páginas» que las URLs nuevas se indexen.
- **Idioma no activo** (`/fr/…` antes de activarlo): 302 a la misma página en español.
- **Imagen para compartir:** cada idioma tiene la suya (sección 2), con el texto en ese idioma; revisarlo antes de publicar el idioma.
- **Qué se traduce en la página:** `<title>`, description, Open Graph, migas, nombres del JSON-LD (journeys, artículos), el contenido, los textos fijos de la plantilla (texto, `alt`, `aria-label`, `placeholder`, `title`), el aviso de cookies y el bloque sin JavaScript.
- **SEO por idioma:** los títulos y descripciones de Sunqu › Sitio › SEO van en Loco en el contexto `seo`, aparte del resto: conviene escribirlos para cada idioma (palabras que se buscan en ese idioma), no traducirlos palabra por palabra, y revisarlos antes de publicar.
- **Lo que no tiene traducción** se muestra en español. Conviene publicar en buscadores solo un idioma completo y revisado: una página «en inglés» con la mitad en español compite mal y confunde.
- **Selector de idioma:** lleva a la misma página en el otro idioma (con sus filtros y su ancla) y guarda la elección.

**Probado** en Docker con una traducción de prueba al inglés: canonical propio en `/es/` y `/en/`, `hreflang` es/en con `x-default` → `/en/…`, `og:locale` es_PE / en_US con su alternativo, JSON-LD con `inLanguage` del idioma; las URLs sin prefijo redirigen 301 a `/es/…`; la raíz redirige 302 sin caché; sitemap válido con 40 URLs (20 por idioma), sin la raíz.
