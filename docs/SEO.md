# Sunqu Journeys · SEO técnico

Todo el SEO se genera en el servidor (`server/lib/seo.php`, `sitemap.php`, `routes.php`) a partir del contenido real.
Los datos vienen de WordPress o de `content/*.json`. No hay textos SEO escritos a mano en el código, salvo la marca
«Sunqu Journeys» y el sufijo «— Diario Sunqu».

---

## 1. Dominio y URLs

- **Dominio canónico:** `SITE_URL=https://sunqujourneys.com` en el `.env`. Es la única fuente para canonical, Open Graph, JSON-LD, sitemap y redirecciones.
- **`.htaccess`:** fuerza HTTPS (301) y quita `www` (301).
- **URLs limpias sin barra final.** Con barra final redirigen 301 a la versión sin barra. Tabla completa en [ARCHITECTURE.md › 5](ARCHITECTURE.md#5-urls).
- **URLs antiguas** (`index.html`, `journey.html?j=…`, `articulo.html?a=…`, `blog.html?cat=…`, `descubre.html?q=…`, `Sunqu Journeys v2.dc.html`): redirigen 301 a su versión limpia.
  - Conservan el parámetro útil y los de campañas (`utm_*`, `gclid`, `fbclid`, `gbraid`, `wbraid`, `msclkid`), así los enlaces viejos compartidos en redes siguen midiéndose.
  - Una ruta con caracteres de control responde 404.
- **404 real:** un slug de journey o de artículo que no existe devuelve estado 404, con `noindex`, `server/404.html` y enlaces a Inicio y Descubre. Antes, `journey.html?j=cualquiercosa` mostraba «alma» con estado 200 (contenido duplicado).
- **Enlaces internos:** en el HTML que sirve el servidor, todos los enlaces `.html` se reescriben a su URL limpia. Queda 0 enlaces `.html`.

## 2. `<head>` por página

| Etiqueta | Valor |
|---|---|
| `<title>` | Páginas fijas: `sitio.seo.paginas.<página>.title`. Journey: `<título> — Sunqu Journeys`. Artículo: `<título> — Diario Sunqu` |
| `meta description` | Páginas fijas: `…description`. Journey: `ficha.what` recortado a ~200 caracteres. Artículo: el extracto |
| `robots` | `index, follow, max-image-preview:large`. En 404 y en `APP_ENV=staging`: `noindex, nofollow` |
| `canonical` | `SITE_URL` + ruta limpia, sin parámetros (`/diario?cat=x` apunta a `/diario`, `/descubre?q=x` a `/descubre`) |
| Open Graph | `og:site_name`, `og:locale` (es_PE), `og:type` (`article` en artículos), `og:title`, `og:description`, `og:url`, `og:image` y su `alt`, dimensiones, `article:published_time` |
| Twitter / X | `summary_large_image`, título, descripción e imagen |
| Iconos | `/assets/favicon-32.png`, `apple-touch-icon` 180 px |
| Precargas | Fuentes Aeonik Regular/Bold (WOFF2) e imagen del hero (con `imagesrcset` igual al del `<img>`) |

**Imagen para compartir:**
- journey: la primera foto de su galería;
- artículo: su imagen;
- páginas fijas: la primera foto del hero del inicio.

Las fotos de Unsplash se piden recortadas a 1200×630; las de WordPress se usan en su URL original.

**Dónde se editan los textos de las páginas fijas:** **Sunqu › Sitio › SEO (buscadores y redes sociales) ›
Páginas fijas** (título y descripción de Inicio, Descubre, Recuerda, Diario Sunqu, Términos y Libro de
reclamaciones). Hoy tienen los títulos que ya usaban las páginas y las bajadas que se ven en cada una.

## 3. Datos estructurados (JSON-LD)

Un solo `@graph` por página, con `@id` estables:

| Nodo | Dónde | Datos |
|---|---|---|
| `TravelAgency` `#organizacion` | todas | Nombre, razón social, URL, logo, correo y dirección (Lima, PE). **Solo si son datos reales:** teléfono (el actual `+51 000 000 000` se omite), `taxID` (el RUC «por confirmar» se omite) y `sameAs` (las redes en `#` se omiten) |
| `WebSite` `#sitio` + `SearchAction` | todas | Búsqueda: `/descubre?q={search_term_string}` (la página lee `q`) |
| `WebPage` / `CollectionPage` `#pagina` | todas | Nombre, descripción, idioma, imagen principal, migas |
| `BreadcrumbList` `#migas` | todas menos el inicio | Inicio › sección › página |
| `ItemList` `#journeys` | /descubre | Journeys en el orden del catálogo |
| `TouristTrip` `#journey` | /journeys/{slug} | Nombre, descripción, imagen, proveedor, itinerario (las paradas de `ficha.route`) y oferta |
| `Blog` `#diario` | /diario | Las entradas publicadas |
| `BlogPosting` `#articulo` | /diario/{slug} | Titular, descripción, imagen, fechas, sección, autor, editor |

**Oferta del journey:** solo aparece si el catálogo tiene un precio numérico. Se expresa como `PriceSpecification` con `minPrice` (y `maxPrice` si hay rango) en USD, porque la web muestra «Desde USD 4.200» o «USD 600 – 900». «Sunqu Origen» (precio a consultar) no lleva oferta.

**Lo que no se marca, a propósito:**
- `Review` y `AggregateRating`: las reseñas actuales son de ejemplo;
- `Product`: los precios de la tienda son de referencia;
- `FAQPage`: el sitio no tiene preguntas frecuentes.

Cuando haya reseñas reales y precios definitivos, se pueden agregar en `sunqu_seo()`.

## 4. sitemap.xml y robots.txt

- **`/sitemap.xml`:** se genera desde el contenido en cada petición (con la caché de contenido). Incluye inicio, descubre, cada journey (en el orden del catálogo), recuerda, diario (`lastmod` del artículo más reciente), cada artículo (`lastmod` con su fecha), términos y libro de reclamaciones. Un journey o un artículo nuevo aparece solo al publicarlo.
- **`/robots.txt`:** `Allow: /` y `Disallow` de `/server/`, `/wordpress/`, `/docs/` y `/_sunqu/`, más la línea `Sitemap:`. Con `APP_ENV=staging` responde `Disallow: /`.
- **CMS (`cms.sunqujourneys.com`):** robots `Disallow: /` salvo `/wp-content/uploads/` (las imágenes que usa la web), sin `wp-sitemap.xml`, meta `noindex` y `X-Robots-Tag: noindex` en la API.
- **Vista previa estática (GitHub Pages):** cada plantilla lleva `<meta name="robots" content="noindex">` en su bloque SEO, así la copia de ferdango.github.io no compite con el dominio real. En producción el servidor reemplaza ese bloque.

## 5. Contenido y semántica

- `<html lang="es">` en todas las páginas.
- Un `<h1>` por página, el mismo que ya tenía el diseño.
- Todas las `<img>` tienen `alt`: el texto del contenido o `alt=""` en las decorativas.
- Los títulos de las pestañas coinciden con el SEO. `SunquContent.titulo()` usa el título que envía el servidor.
- **Saltos de nivel de encabezado** (no bloquean la indexación):
  - en el itinerario del journey, un H4 sigue a un H2;
  - en el listado del Diario, un H3 sigue al H1.

  Vienen del diseño original y se dejaron así para no cambiar estilos.

## 6. Verificación después del despliegue

1. `curl -I http://sunqujourneys.com/descubre/` → 301 a `https://sunqujourneys.com/descubre`.
2. `https://sunqujourneys.com/journeys/no-existe` → 404.
3. Prueba de resultados enriquecidos de Google y validador de schema.org con `/`, `/journeys/alma` y un artículo.
4. **Google Search Console** (PENDIENTE DE CONFIGURACIÓN, requiere acceso a DNS o a la cuenta):
   - agregar la propiedad de dominio `sunqujourneys.com` (verificación por registro TXT en el DNS de Hostinger);
   - enviar `https://sunqujourneys.com/sitemap.xml`;
   - usar «Inspección de URLs» en las páginas principales.
5. Compartir una URL en WhatsApp o en el depurador de Facebook para ver la tarjeta.

## 7. Pendiente de contenido (no se inventó)

- **Teléfono real y enlaces de redes:** hoy son `+51 000 000 000` y `#`. Al cargarlos en Sunqu › Sitio pasan solos al JSON-LD (`telephone`, `sameAs`).
- **RUC** (Sunqu › Sitio › Empresa, «por confirmar»): con 11 dígitos se publica como `taxID`.
- **Revisión de los textos de Sunqu › Sitio › SEO por el negocio:** hoy repiten los títulos y bajadas existentes.
