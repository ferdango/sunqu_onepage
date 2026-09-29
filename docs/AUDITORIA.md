# Auditoría y mapeo previos a producción

Entregable de las **fases 1 (auditoría)** y **2 (mapeo)** de la migración a `https://sunqujourneys.com`.
- **Base de la auditoría:** el repositorio tal como estaba en el commit `0d56ee4`, antes de cualquier cambio de producción.
- **Cómo se hizo:** lectura del código y medición automática en Chrome headless (escritorio 1440 px y móvil 390 px) de las 8 páginas.

---

## Fase 1 · Auditoría

### 1. Estructura actual

| Carpeta / archivo | Contenido |
|---|---|
| `index.html` (= `Sunqu Journeys v2.dc.html`) | Portada one-page: 14 secciones (hero, Detrás de Sunqu, Círculo Dorado, Principios, Formas de viajar, foto expandible, 01 Descubre, 02 Conecta, 03 Vive, Ruta, 04 Recuerda, Diario, Instagram, Contacto) |
| `journey.html?j=<slug>` | Ficha de journey (5 journeys): hero con galería, por qué, día a día, altitud, Momento Sunqu, temporada, consejos y notas, anfitriones, recuerda, galería, otros journeys, reserva |
| `descubre.html` | Catálogo: buscador (palabra, fechas, viajeros), filtros, lista/cuadrícula, guardados, experiencias paginadas, onboarding, formulario |
| `recuerda.html` | Muro de reseñas (video, foto, comentario, embeds de Instagram/TikTok), tienda con bolsa y cierre por WhatsApp, formulario «Comparte tu viaje» |
| `blog.html`, `articulo.html?a=<slug>` | Diario Sunqu: listado por categorías y artículo con índice y botones para compartir |
| `terminos.html`, `reclamaciones.html` | Términos y condiciones · Libro de reclamaciones (hoja imprimible) |
| `support.js` | Runtime de plantillas (`<x-dc>`, `{{ }}`, `<sc-for>`, `<sc-if>`), basado en React 18 UMD servido desde unpkg |
| `js/sunqu-content.js`, `js/sunqu-config.js` | Capa de contenido: lee `content/*.json` o la API de WordPress y envía los formularios |
| `content/*.json` | 9 archivos: sitio, inicio, journeys, anfitriones, experiencias, reseñas, productos, diario, legales |
| `css/<página>.min.css` | Un CSS minificado por página |
| `assets/` | Logos, 5 isotipos y 6 patrones (PNG), 3 fotos de catálogo, 10 de merch, 3 reels (MP4 + póster) |
| `fonts/` | Aeonik en TTF (4 pesos) |
| `wordpress/` | Plugin «Sunqu CMS», tema headless, entorno Docker, generador del esquema |

**Dependencias externas en tiempo de ejecución:**
- unpkg (React y ReactDOM 18.3.1, con SRI);
- jsDelivr (d3 v7, topojson-client y world-atlas `countries-50m.json`, solo en la portada);
- Google Fonts (Manrope, que solo actúa como tipografía de respaldo);
- Unsplash (39 fotos en la portada);
- `wa.me`;
- embeds de Instagram y TikTok, que solo se cargan al abrir una reseña.

**Sin analítica ni píxeles.** Datos guardados en `localStorage`: `sunqu-fav` (guardados), `sunqu-bag` (bolsa) y `sunqu-onb-descubre` (onboarding visto).

### 2. Formularios encontrados

| Formulario | Dónde | Campos (* obligatorio) | Lógica actual |
|---|---|---|---|
| **Diseña tu viaje** (solicitud) | `index.html#contacto`, `journey.html#reservar`, `descubre.html#reservar` (mismo componente) | nombre*, email* (type=email), mes (Aún no lo sé · Enero – marzo · Abril – junio · Julio – septiembre · Octubre – diciembre), sentir (La historia · El sabor · La gente), mensaje (opcional), interruptor «Adjuntar mis guardados» → `guardados` (oculto) | `SunquContent.enviar('solicitud')`, estados enviando / error / «Añay. Gracias.». **Sin API configurada muestra éxito sin enviar nada** |
| **Comparte tu viaje** (reseña) | `recuerda.html#comparte` | nombre*, pais*, tema (Viaje · Souvenir · Merch), estrellas (1–5), texto*, enlace (se valida contra Instagram/TikTok), fotos (hasta 4), permiso* (checkbox) | La reseña se agrega en la página al instante; `enviar('resena')` con las fotos como JPEG |
| **Hoja de reclamación** | `reclamaciones.html#hoja` | nombre*, menor (checkbox) → apoderado*, tipoDoc* (DNI · Carné de extranjería · Pasaporte · RUC · Otro), numDoc*, email*, celular*, domicilio*, servicio* (6 opciones), monto, fecha, descripcion*, tipo (Reclamo/Queja), detalle*, pedido*, acepta* | Genera el código `SJ-AAAAMMDD-HHMMSS`, muestra un resumen imprimible y llama a `enviar('reclamacion')` |
| Buscador (no es de contacto) | `descubre.html` | palabra clave, desde, hasta, pasajeros | Filtra en la página; lee `?q=` |

Los endpoints de WordPress (`/sunqu/v1/formularios/{solicitud|resena|reclamacion}`) existen y ya aceptan estos campos.

### 3. Integraciones existentes

- **WhatsApp.** Número único en `content/sitio.json › contacto.whatsapp` (51966461384, editable en WordPress). Se usa en:
  - el botón flotante en las 8 páginas;
  - «pregúntalo aquí» en la ficha de journey;
  - el cierre de la bolsa en la tienda;
  - el enlace «Compartir por WhatsApp» del artículo (este no usa el número).
- **WordPress (headless).** Plugin con tipos de contenido, campos generados desde `schema.json`, API `/sunqu/v1/contenido`, formularios, WP-CLI importar/exportar/verificar. Nunca se ejecutó en un WordPress real.
- **Guardados → formulario** (interruptor «Adjuntar mis guardados») y **onboarding** de Descubre.

### 4. Problemas encontrados

**SEO**
1. Ninguna página tiene `lang`, meta description, canonical, Open Graph, Twitter Card ni datos estructurados.
2. URLs con parámetros y `.html` (`journey.html?j=alma`, `articulo.html?a=…`).
3. No hay `sitemap.xml`, `robots.txt` ni página 404: un slug inexistente muestra «El Alma de los Andes» o el primer artículo con estado 200 (contenido duplicado).
4. El título real sale del runtime: sin JavaScript no hay texto, porque el contenido se pinta en el cliente.

**Rendimiento** (medido en local)
- **Peso de la portada:** 9,8 MB en la primera visita, de los que 8 MB son 39 fotos de Unsplash a 1400–2000 px (en móvil se muestran a 390 px).
- **Mapa:** carga d3 (~90 KB) y world-atlas (240 KB) aunque la sección Ruta esté lejos.
- **LCP de la portada:** unos 4,5–4,8 s. Lo determinan el loader (2,35 s + salida) y la revelación del hero (retrasos de 2,9–3,3 s), no la red.
- **Imágenes sobredimensionadas:**
  - isotipos PNG de 744–996 px que se muestran a 13–22 px;
  - logos de 1688 px que se muestran a 116 px.
- **Fuentes:** en TTF (unos 100 KB cada una), sin WOFF2. Manrope se descarga sin usarse.
- **Terceros:** React depende de unpkg y d3 de jsDelivr (dos proveedores externos en el camino crítico).

**Accesibilidad** (WCAG 2.2 AA)
1. Falta `<html lang="es">`.
2. No hay enlace para saltar al contenido.
3. Los modales (menú, idioma, visor, bolsa, galería) no retienen el foco con Tab.
4. En móvil, el botón «Guardados» de Descubre no tiene nombre accesible (su texto se oculta).
5. **Contraste insuficiente** (medido por sección):

| Texto | Fondo | Contraste | Mínimo |
|---|---|---|---|
| Botón primario (#e9eef1 sobre oliva #736d47) | — | 4,48 | 4,5 |
| Antetítulos en oliva | fondo claro | 4,48 | 4,5 |
| Antetítulos en oliva | fondo alterno | 4,05 | 4,5 |
| Gris secundario #7a7a7d («Opcional», rol del autor, «Día 2») | claro | 3,3–3,99 | 4,5 |
| Números de sección en amarillo (Términos) | claro | 1,7–1,85 | 4,5 |
| Antetítulos «Durante» y «Después» (lima y amarillo) | claro | 3,0–3,35 | 4,5 |
| Pastilla «Medio día» sobre foto | foto | 4,29 | 4,5 |
| Pastilla tachada «Destino» | claro | 2,94 | 3 |

6. Las reglas de movimiento reducido existen en todas las páginas.

**Seguridad**
1. El mapa inserta los nombres de las paradas, que son contenido del CMS, con `innerHTML` sin escapar (XSS almacenado).
2. GitHub Pages no permite cabeceras de seguridad (CSP, HSTS…).
3. Sin API, los formularios simulan el envío: en producción se perderían datos.
4. El plugin guarda los formularios, pero no la constancia del consentimiento (`permiso`, `acepta`).

**Contenido pendiente del negocio:**
- teléfono `+51 000 000 000` y enlaces de redes en `#`;
- RUC «por confirmar»;
- reseñas de ejemplo y precios de merch de referencia;
- el enlace «FAQ» del footer lleva al contacto: no existe contenido de preguntas frecuentes;
- el selector ES/EN/PT no traduce: solo existe el contenido en español.

### 5. Arquitectura recomendada (decisión)

**Se mantiene el frontend actual (HTML + CSS + JS + runtime), con una capa PHP delgada en Hostinger delante.** No se migra a Astro, Next.js ni React SPA:

| Criterio | Reescribir en Astro/Next | Mantener + capa PHP |
|---|---|---|
| Conservar diseño e interacciones | Reescribir 8 páginas con miles de líneas de lógica: riesgo alto de regresiones | Se conservan exactamente (verificado elemento por elemento) |
| Hostinger | Next con SSR necesita Node (VPS); Astro estático necesita rebuild en cada publicación | PHP 8 y `.htaccess` vienen en todos los planes |
| SEO | Muy bueno | `<head>` completo por URL, URLs limpias, sitemap y 404 generados en el servidor; Googlebot renderiza el contenido JS |
| Sitemap que crece solo | Requiere rebuild o SSR | Se genera en cada petición desde el contenido (con caché) |
| WordPress | Headless igual | Headless con el plugin existente |
| Mantenimiento y costo | Nuevo stack y pipeline de build | Sin build obligatorio: se despliega el repositorio tal cual |

**WordPress** va en `https://cms.sunqujourneys.com` (subdominio en la misma cuenta de Hostinger), como CMS headless. La web lo lee **desde el servidor**, con caché y respaldo en `content/*.json`, y envía los formularios a su API.

---

## Fase 2 · Mapeo

### A. Contenido: repositorio → WordPress

Ya estaba mapeado 1:1 (`content/*.json` ↔ plugin, ver `docs/WORDPRESS.md`). Para producción se agrega **solo** lo necesario:

| Contenido | Antes | Producción |
|---|---|---|
| Título y descripción de las 6 páginas fijas | `<title>` en cada HTML; descripción inexistente | `content/sitio.json › seo.paginas` (Sunqu › Sitio › SEO), con los títulos y bajadas visibles actuales. Las fichas de journey y los artículos toman su título y descripción de su propio contenido, sin campos nuevos |
| Número de WhatsApp | `sitio.contacto.whatsapp` | Igual (CMS). `WHATSAPP_NUMBER` en `.env` actúa como respaldo si el CMS está vacío |
| Tamaños de imagen de WordPress | Solo la URL original | La API agrega `_imagenes` (srcset por imagen) para servir el tamaño adecuado |

### B. Formularios: actual → producción

Se conservan sin cambios los campos, las etiquetas, las validaciones, los mensajes y el diseño.

| Formulario | Producción |
|---|---|
| Solicitud | POST `…/sunqu/v1/formularios/solicitud` → **Sunqu › Solicitudes de viaje** + correo de aviso. Se agrega contexto automático (`pagina`, con el journey de origen) |
| Reseña | POST `…/resena` → **Reseñas › Pendiente**, con las fotos en la biblioteca y la constancia de `permiso` |
| Reclamación | POST `…/reclamacion` → **Sunqu › Libro de reclamaciones**, con constancia por correo al consumidor, aviso a la empresa y registro de `acepta` |

Mejoras técnicas en los tres:
- campo trampa invisible y tiempo mínimo de llenado (anti-spam);
- límite por IP (ya existía);
- anuncio del éxito a lectores de pantalla;
- eventos `form_start`, `form_submit` y `form_error` sin datos personales;
- sin API en producción, los formularios muestran un error con la vía de WhatsApp en lugar de un éxito falso.

### C. Tracking: interacción → evento

| Interacción existente | dataLayer / GA4 | Meta Pixel |
|---|---|---|
| Carga de página | `page_view` | `PageView` |
| Clic en WhatsApp (flotante, «pregúntalo aquí», bolsa) | `click_whatsapp` {ubicacion} | `Contact` |
| Primer campo tocado de un formulario | `form_start` {form_name} | — |
| Envío correcto | `form_submit` {form_name}; `generate_lead` (solicitud) | `Lead` (solo solicitud) |
| Envío con error | `form_error` {form_name} | — |
| Ver ficha de journey | `view_experience` {experience_id, experience_name} | `ViewContent` |
| Clic en tarjeta de journey | `click_experience` {experience_id, list_name} | — |
| Guardar journey o experiencia | `add_to_wishlist` | — |
| Buscar en Descubre | `search` {search_term} (se descarta si parece un correo o un teléfono) | — |
| Onboarding de Descubre | `tutorial_begin` / `tutorial_complete` | — |
| Agregar a la bolsa (tienda) | `add_to_cart` | `AddToCart` |
| Pedir la bolsa por WhatsApp | `begin_checkout` + `click_whatsapp` | `Contact` |
| Abrir una reseña del muro | `select_content` {content_type: resena} | — |
| Compartir un artículo | `share` {method} | — |
| Botón «Diseña tu viaje» | `cta_click` {ubicacion} | — |
| Enlaces externos | `outbound_click` {link_domain} | — |

Nunca se envían nombre, correo, teléfono, mensaje ni ningún otro dato de formulario. GA4 y el Pixel se cargan solo con consentimiento.

### D. SEO: contenido → SEO técnico

| URL de producción | Antes | Título / descripción | Datos estructurados |
|---|---|---|---|
| `/` | `index.html` | `seo.paginas.inicio` | Organization + TravelAgency, WebSite (+ SearchAction → `/descubre?q=`), WebPage |
| `/descubre` | `descubre.html` | `seo.paginas.descubre` | CollectionPage + ItemList de journeys, BreadcrumbList |
| `/journeys/<slug>` | `journey.html?j=<slug>` | título del journey · `ficha.what` | TouristTrip (itinerario de la ruta; oferta solo si hay precio numérico), BreadcrumbList |
| `/recuerda` | `recuerda.html` | `seo.paginas.recuerda` | WebPage, BreadcrumbList. **Sin Review ni Product**: las reseñas son de ejemplo y los precios de referencia |
| `/diario` | `blog.html` | `seo.paginas.diario` | Blog, BreadcrumbList |
| `/diario/<slug>` | `articulo.html?a=<slug>` | título del artículo · extracto | BlogPosting, BreadcrumbList |
| `/terminos` | `terminos.html` | `seo.paginas.terminos` | WebPage, BreadcrumbList |
| `/libro-de-reclamaciones` | `reclamaciones.html` | `seo.paginas.reclamaciones` | WebPage, BreadcrumbList |

URLs sin barra final: con barra final redirigen 301 (decisión de la implementación; ver `docs/SEO.md`).

Reglas comunes:
- **No se usa FAQPage**, porque no hay preguntas frecuentes en el sitio.
- **Open Graph:** la primera foto del hero de cada página.
- **Canonical y URLs absolutas:** salen de `SITE_URL`.
- **Redirecciones:** las URLs antiguas devuelven 301 a las nuevas.
- **404:** los slugs inexistentes devuelven 404 real.
