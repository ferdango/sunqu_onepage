# Sunqu Journeys — web

**Producción:** https://sunqujourneys.com. Corre en Hostinger con la capa PHP `server/` y WordPress headless en `cms.sunqujourneys.com`.
**Publicación actual (estática):** https://sunqujourneys.com sirve la rama `master` desde GitHub Pages (dominio en `CNAME`; DNS en Hostinger: registros A de GitHub Pages en `@` y CNAME `www` → `ferdango.github.io`). Las páginas ya no llevan `noindex`. En este modo no corren `server/` ni `.htaccess`: las URLs limpias (`/es/…`, `/journeys/alma`) y los formularios esperan a la migración a Hostinger (`docs/DEPLOYMENT.md`); `https://ferdango.github.io/sunqu_onepage/` redirige al dominio.

**Idiomas en la web estática:** el español está en la raíz y el inglés, francés, alemán, italiano y ruso en `/en/`, `/fr/`, `/de/`, `/it/` y `/ru/`, generados por `tools/idiomas-estaticos.py` a partir de las páginas en español y de `i18n/<idioma>.json` ({texto en español: traducción}). El mismo script traduce `content/*.json` → `content/<idioma>/*.json` (los términos legales y las reseñas quedan en español), pone la descripción y los `hreflang` en cada página y escribe `sitemap.xml` y `robots.txt`. **Después de cambiar un texto en español:** `python3 tools/idiomas-estaticos.py --fuente` (lista de textos en `i18n/_fuente.json`), agrega la traducción de lo nuevo en cada `i18n/<idioma>.json` y vuelve a correr `python3 tools/idiomas-estaticos.py`: avisa cuántos textos faltan por idioma, y lo que falta se ve en español. No edites `/en/…` ni `content/en/…` a mano.
**Catálogo:** 3 journeys (`costa-andes-amazonia`, `esencial-con-amazonia`, `lo-mejor-del-peru`) y 3 experiencias (Lima colonial y catacumbas, Museo Larco, Huacachina), en `content/journeys.json` y `content/experiencias.json`. Cada experiencia tiene su ficha en `experiencia.html?e=<slug>` (resumen, datos, itinerario, incluye, no incluye, qué llevar, notas y galería). Contenido y fotos tomados de las fichas de Lima Experience (el enlace de cada una está en `fuente`); las fotos se cargan desde limaexperience.com.

| Documento | Para qué |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Cómo funciona en producción, estructura, configuración (`.env`), URLs, caché, seguridad |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Publicar en Hostinger paso a paso y qué queda pendiente de configurar |
| [`docs/WORDPRESS.md`](docs/WORDPRESS.md) | Contenido y WordPress: dónde se edita cada apartado, API, formularios, idiomas (Loco Translate), scripts de medición, entorno local |
| [`docs/FORMULARIOS.md`](docs/FORMULARIOS.md) | Formularios: recorrido de cada envío, correo a sunqujourneys@gmail.com (SMTP), pruebas y avisos al celular (Telegram, webhook) |
| [`docs/SEO.md`](docs/SEO.md) | Auditoría SEO con puntuación, URLs, `<head>`, datos estructurados, sitemap, robots, rendimiento y pendientes |
| [`docs/SECURITY.md`](docs/SECURITY.md) | Seguridad: arquitectura, protecciones de la web y de WordPress, Hostinger, secretos, copias, monitoreo, riesgos pendientes e incidentes |
| [`docs/ANALYTICS.md`](docs/ANALYTICS.md) | GA4, GTM, Meta Pixel, consentimiento, catálogo de eventos y scripts desde WordPress (Sunqu › Scripts y seguimiento) |
| [`docs/ACCESSIBILITY.md`](docs/ACCESSIBILITY.md) | WCAG 2.2 AA: qué se hizo y cómo se midió |
| [`docs/AUDITORIA.md`](docs/AUDITORIA.md) | Auditoría inicial y mapeo de la migración |

**Probar en local:**
- Solo la web: `python3 -m http.server 8000` en la raíz y abrir http://localhost:8000/.
- Como en producción, con WordPress: `cd wordpress && ./setup.sh` y abrir http://localhost:8081/.

> **Contenido:** vive en `content/*.json` (mismo formato que la API de WordPress). En producción el servidor lo lee de WordPress con caché y lo entrega ya incluido en cada página; si WordPress no responde, usa la caché o estos JSON.
`index.html` es la copia publicada de `Sunqu Journeys v2.dc.html`: al editar el prototipo, copia el archivo a `index.html` y haz push.

**Estructura de cada página.** El HTML está indentado con tabs y cada bloque principal (header, menú lateral, secciones, footer, modales) lleva un comentario `<!-- Sección #id · nombre -->`. Los estilos viven aparte, minificados, en `css/<página>.min.css` (`home.min.css` lo comparten `index.html` y `Sunqu Journeys v2.dc.html`) y se enlazan desde el `<head>`; las rutas a `fonts/` y `assets/` dentro del CSS son relativas a `css/` (`../fonts/…`). La lógica sigue al final de cada archivo, en `<script type="text/x-dc">` (clase `Component`).
Importante al editar el markup: indenta solo con tabs y saltos de línea. El runtime descarta los espacios en blanco sin el carácter espacio, pero un espacio entre dos etiquetas se renderiza como texto.
`descubre.html` es el catálogo (buscador, filtros, journeys y experiencias); los enlaces "Descubre" del header, el menú lateral y el footer apuntan ahí. Sus datos vienen de `content/journeys.json` (bloque `catalogo`) y `content/experiencias.json`. Los guardados se almacenan en `localStorage['sunqu-fav']` como `{ clave: { t: título, k: 'Journey'|'Experiencia' } }`; el formulario «Diseña tu viaje» (inicio, journey y descubre) los lee y ofrece un interruptor «Adjuntar mis guardados» que los envía en el campo oculto `guardados`.
Descubre abre un onboarding (bienvenida + 5 pasos con foco: palabra clave, fechas y viajeros, filtros, guardar y Guardados) **una vez por sesión**: se marca como visto en `sessionStorage['sunqu-onb-descubre']` apenas se abre, y se relanza cuando se quiera con el botón «Cómo funciona» de la barra. Los pasos del tour viven en `ONB` dentro del archivo.
`recuerda.html` combina el muro de reseñas (videos, fotos y comentarios; los posts reales de Instagram/TikTok se muestran con su embed oficial poniendo su enlace en `url`) y la tienda de merch del manual de marca, con bolsa y compra por WhatsApp. Reseñas en `content/resenas.json` (las actuales son de ejemplo) y tienda en `content/productos.json` (precios de referencia).
`blog.html` (listado con categorías) y `articulo.html?a=<slug>` (artículo) leen los artículos de `content/diario.json` (en WordPress: Entradas y Categorías).
El menú del header (escritorio y móvil) es el mismo en todas las páginas: Descubre (`descubre.html`), MICE (`mice.html`), Recuerda (`recuerda.html`) y Tienda (`recuerda.html#tienda`); vive en `NAV` dentro de cada plantilla.
`mice.html` presenta los viajes para empresas (reuniones, incentivos, congresos y eventos) y pide la propuesta por WhatsApp o correo (sin formulario: en GitHub Pages los formularios no llegan a nadie). Sus textos están en la propia plantilla.
`terminos.html` (Términos y condiciones, enlazado desde «Legales» del footer) y `reclamaciones.html` (Libro de reclamaciones virtual, enlazado desde el footer; header solo con el logo). El libro necesita un backend o servicio de correo para registrar y enviar cada hoja; el RUC y la dirección se completan en `content/sitio.json › empresa` (WordPress: Sunqu › Sitio). Con WordPress conectado, cada hoja se registra en Sunqu › Libro de reclamaciones y se envía por correo.
`journey.html?j=<slug>` es la ficha de un journey (`costa-andes-amazonia`, `esencial-con-amazonia`, `lo-mejor-del-peru`); los botones "Ver journey" de la one-page enlazan ahí. Su contenido viene de `content/journeys.json` (bloques `ficha` y `plan`), `content/anfitriones.json` y los `principios`/`galeria` comunes del mismo archivo.

## Overview
Landing one-page inmersiva para **Sunqu Journeys**, agencia de viajes premium al Perú ("Viaja al corazón del Perú"). Presenta la marca, el Círculo Dorado (Why/How/What), los 6 principios, los journeys, anfitriones, la ruta en mapa, el acompañamiento antes/durante/después, blog, Instagram y un formulario de contacto. Idiomas: cada uno con sus URLs (`/es/…`, `/en/…`; también portugués, francés, alemán, italiano y ruso cuando se activan en WordPress › Sunqu › Idiomas y se traducen con Loco Translate). La raíz elige el idioma de quien entra: su elección anterior, el idioma de su navegador, su país (respaldo por IP) o el inglés (`docs/WORDPRESS.md › 8.7`).

Repo destino: `ferdango/sunqu_onepage` (rama `master`, actualmente vacío).

## Código de las páginas
Las páginas usan un runtime propio (`support.js`):
- el markup va entre `<x-dc>…</x-dc>`, con `{{ }}`, `<sc-for>` y `<sc-if>`;
- la lógica va en la clase `Component` al final de cada archivo (estado, `renderVals()`, bucle de scroll con `requestAnimationFrame`).

Para producción **se mantuvo este frontend** y se le agregó una capa PHP delante, en lugar de reescribirlo (Next.js/Astro): conserva el diseño y todas las interacciones, y corre en cualquier plan de Hostinger. El porqué está en [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). El mapa usa módulos de d3 (`vendor/`) y un contorno de Perú y sus vecinos (`assets/geo/`), que se cargan cuando el mapa se acerca a la pantalla.

## Fidelity
**High-fidelity.** Colores, tipografía, espaciado, copy e interacciones son finales. Recrear pixel-perfect. Las fotos son de Unsplash (placeholder de calidad) — reemplazar por fotografía propia cuando exista.

## Design Tokens
**Colores (manual de marca)**
- Ocre principal (acento): `#736d47` · hover `#5e5939` · pressed `#4a462d`
- Blanco base / fondo claro: `#e9eef1` · superficie alterna `#dde3e7`
- Negro base / fondo oscuro: `#1c1c1c` · tarjeta sobre oscuro `#26262a`
- Footer (black_base 2): `#2b271b`
- Secundarios: naranja `#df5f0c`, amarillo `#f4a700`, verde lima `#83b300`, verde bosque `#48725E`
- Texto secundario: `#424244`, `#5d5d60`, `#626265` · divisores `rgba(28,28,28,.16)` (claro) / `rgba(233,238,241,.14)` (oscuro)
- Para cumplir contraste AA: el texto oliva sobre fondos claros usa `#676140`, y el texto sobre fondos oliva, `#fff` (ver `docs/ACCESSIBILITY.md`)

**Tipografía — Aeonik** (archivos en `fonts/`, licencia comercial de CoType: confirmar licencia web)
- 400 Regular (cuerpo), 500 Medium, 700 Bold (títulos, usado como 600), Regular Italic. WOFF2 con TTF de respaldo. Fallback: system-ui.
- H1 hero: `clamp(46px, 9vw, 150px)`, lh .98, tracking -.035em
- H2 sección: `clamp(33px, 4.4vw, 71px)`, lh 1.02–1.04, tracking -.02/-.03em
- H3 tarjeta: 21–28px; títulos de stack cards `clamp(34px,4.4vw,64px)`
- Cuerpo: 15–19px, lh 1.5–1.6 · Eyebrow: 12–13px, uppercase, tracking .1–.12em

**Layout**: contenedor `max-width:1440px`, padding lateral `clamp(20px,4vw,56px)`, padding vertical de sección `clamp(80px,10vw,140px)`. Grids `repeat(auto-fit, minmax(min(100%, Npx), 1fr))`.

**Radios**: pills `999px`; tarjetas stack `32px`; imágenes `18–24px`; tarjetas journey `20px`; modal `28px`. **Sombras**: `0 30px 80px rgba(0,0,0,.18)` (cards), `0 10px 30px rgba(28,28,28,.12)` (pills).

**Easing global**: `cubic-bezier(.2,.7,.1,1)`.

## Screens / Secciones (en orden)
1. **Loader** — minimalista: fondo `#1c1c1c`, solo el logo (primero el isotipo lima, luego las letras) y el lema "Viaja al corazón del Perú" en tono suave. Se muestra **una vez por sesión** (`sessionStorage['sunqu-loader']`): en las visitas siguientes al inicio, el hero entra directo, con sus mismas animaciones adelantadas 2,8 s. Dura ~2,3 s y sale "al corazón": una máscara con forma de isotipo nace sobre el corazón del logo y crece hasta revelar el hero (si el navegador no soporta `mask-composite`, se desliza hacia arriba; con movimiento reducido, se desvanece).
2. **Header** fijo — logo (swap `logo-light.png`/`logo-dark.png` según sección clara/oscura), links Descubre/Conecta/Vive/Recuerda, botón idioma (globo + "ES", pill con borde), CTA "Diseña tu viaje". Con scroll>30px: fondo blur `rgba(28,28,28,.7)` (oscuro) o `rgba(233,238,241,.88)` (claro).
3. **Modal idioma** — backdrop `rgba(28,28,28,.55)` + blur 6px; tarjeta 440px, radio 28px; opciones Español/English/Português (activa en ocre). Cierra con X, click fuera o al elegir.
4. **Hero** (oscuro, 100vh) — 4 fotos a pantalla completa en crossfade cada 5.2s con Ken Burns (scale 1→1.12, 8s linear); prop opcional `heroVideo` (mp4) reemplaza las fotos. Gradiente inferior. H1 "El Perú no es / un destino. / Es una relación." (última línea `#f4a700`). Fila inferior: bajada, caption de la foto, 4 barras de progreso, botón pill blanco "Descubre los journeys".
5. **Detrás de Sunqu** (claro) — texto grande con efecto "se ilumina al hacer scroll" (gradiente de color recortado al texto). Bloque de pills: "Destino" (tachado, borde punteado, ícono pin) → rombo amarillo → "Relación" (pill ocre); "Fotografía" (ícono cámara) → "Vínculo" (pill lima).
6. **Círculo Dorado** (300vh, sticky) — 3 círculos concéntricos con foto de fondo cada uno (WHAT Machu Picchu, HOW tejedora, WHY mujeres sonriendo); el activo con overlay .2, inactivos .62; labels blancos. Texto de la derecha cambia por tercios de scroll (01 Por qué / 02 Cómo / 03 Qué) + 3 barras de progreso.
7. **Principios — "Curamos, no acumulamos"** (520vh, sticky) — 6 tarjetas apiladas estilo riseatseven: entran desde abajo (115vh) rotando y se posan con rotaciones [-5, 4, -2, 3]°; colores negro, lima, amarillo, naranja, ocre, verde bosque. Detrás, 6 stickers grandes (patrones del manual) con borde blanco y sombra.
8. **Formas de viajar** — lista grande (Journeys/Private/Collective/Marketplace); hover cambia la imagen cuadrada de la izquierda (fade + scale 1.08→1), desplaza el título 16px y rellena el botón circular.
9. **Foto expandible** (oscuro, 260vh) — imagen que pasa de `clip-path: inset(22% 30% round 28px)` a `inset(0)`; al final aparece "Primero la piedra. Después, la gente."
10. **01 Descubre** (oscuro, scroll horizontal) — sección pinneada; la pista de tarjetas se traslada en X según scroll; altura de sección = ancho de pista − viewport + 100vh. Filtros pill (Todo / La historia / El sabor / La gente) y barra de progreso amarilla.
11. **02 Conecta** (claro) — 3 anfitriones con retrato, y bloque "Ficha de reciprocidad (Ayni)".
12. **03 Vive** (oscuro) — 4 condiciones del Momento Sunqu + gráfico SVG de curva de altitud (umbral soroche 3.400 m).
13. **Ruta** (oscuro, 280vh sticky) — mapa real del Perú (world-atlas 50m, id 604) + vecinos (218,170,076,068,152); ruta Lima → Valle Sagrado → Moray → Machu Picchu → Cusco → Arequipa → Colca dibujada con `stroke-dashoffset` según scroll; lista lateral resalta paradas alcanzadas.
14. **04 Recuerda** (claro) — stack cards Antes/Durante/Después (mismo patrón que principios), palabras en quechua (Sunqu, Añay, Ayni), Marketplace y Playlist.
15. **Blog "Diario Sunqu"** — 3 artículos: imagen 4:3 radio 20px (zoom en hover), tag categoría pill, fecha, título, extracto, "Leer artículo →".
16. **Instagram** — grilla de 6 posts cuadrados radio 18px, overlay oscuro + ícono IG en hover, CTA "Síguenos".
17. **Contacto** — 3 pasos + formulario (nombre, email, mes, radio "qué quieres sentir", mensaje). Submit → estado de éxito "Añay. Gracias."
18. **Footer** `#2b271b` — cierre "Viaja al corazón del Perú." + CTA lima; contacto (email, teléfono, oficina); fila logo / links (Inicio, Sobre Sunqu, Descubre, Recuerda, Diario Sunqu, FAQ, Legales) / redes circulares 48px (YouTube, Instagram, Facebook, TikTok; hover lima); copyright + "Libro de reclamaciones". Desde 1280px, bajo los contactos, aparece el arte `.sj-foot-art` (SVG inline "del mar a la montaña": sol sobre el Pacífico, cordillera con nevados, andenes, cóndores y una ruta punteada que sube hasta el isotipo); se anima al entrar en pantalla (script `data-foot-art`) y respeta `prefers-reduced-motion`. Es igual en todas las páginas.

## Interactions & Behavior
- **Fondo de página**: cada sección tiene `data-bg="light|dark"`; la que cruza el centro del viewport define el fondo (`#e9eef1`/`#1c1c1c`, transición .9s) y los colores del header.
- **Reveal**: elementos `data-reveal` entran con opacity 0→1 y translateY 36→0 (1–1.1s, delays escalonados). `data-clip`: clip-path inset 10%→0 (1.4s).
- **Parallax**: `data-parallax` = factor sobre la distancia al centro del viewport.
- Prop `motion=false` desactiva animaciones (respetar también `prefers-reduced-motion`).
- Iconos: Lucide, stroke 1.5.

## State Management
`scrolled`, `dark` (sección actual), `hero` (índice slide), `pct`/`loaded` (loader), `gc` (paso del Círculo Dorado), `form` (hover de Formas), `concept` (filtro journeys), `mapIdx` (parada), `lang`/`langOpen`, `sent` (formulario). Datos estáticos (journeys, principios, anfitriones, fases, blog) → mover a JSON/CMS.

## Assets
- `assets/logo-light.png`, `logo-dark.png` (logo del manual, corazón `#83b300`), `logo-mask.png` (monocromo)
- `assets/diamond-*.png` (rombos de color), `assets/pattern-*.png` (patrones andinos del manual)
- `fonts/Aeonik-*.woff2` (y `.ttf` de respaldo), generadas con `tools/fuentes-woff2.py`
- `assets/**/*.jpg|png` con versiones `.avif`/`.webp` al lado (`tools/optimizar-imagenes.mjs`): el `.htaccess` sirve la mejor según el navegador · favicons `assets/favicon-*.png`
- `assets/og/sunqu-og-{idioma}.jpg` — imagen de marca para compartir (Open Graph y X), una por idioma, 1200×630 (`tools/imagen-og.php`, textos en `server/lib/og.php`; `docs/SEO.md` › 2)
- Fotos: Unsplash (licencia Unsplash) o la biblioteca de WordPress. Son placeholders a reemplazar por fotografía propia

## Files
- `index.html` … `reclamaciones.html` — plantillas de las páginas · `Sunqu Journeys v2.dc.html` — fuente de `index.html`
- `support.js` — runtime de las plantillas (lo arranca `js/sunqu-content.js` cuando el contenido está listo)
- `content/` — contenido de toda la web (JSON)
- `js/sunqu-config.js`, `js/sunqu-content.js`, `js/sunqu-analytics.js` y `js/sunqu-campos.js` — conexión con WordPress (hosting estático), capa de contenido, medición y campos de los formularios (País, Celular con prefijo, Mi grupo)
- `server/`, `.htaccess`, `.env.example` — producción en Hostinger (`docs/ARCHITECTURE.md`)
- `wordpress/` — plugin «Sunqu CMS», tema headless, entorno Docker y generador del esquema
- `vendor/` — React y d3 servidos desde el propio sitio · `tools/` — scripts de mantenimiento (vendor, imágenes, fuentes, `extraer-textos.py` para las traducciones, `imagen-og.php` para la imagen de compartir y `empaquetar-wordpress.sh`, que arma los zips del plugin y del tema)
- `css/` — hojas de estilo minificadas, una por página
- `assets/`, `fonts/`, `docs/`

## Pendientes / datos placeholder
- **Configuración** (ver `docs/DEPLOYMENT.md › 10`): Hostinger, DNS, SSL, WordPress en `cms.`, SMTP, IDs de GA4, GTM y Meta Pixel, y Search Console.
- **Seguridad** (ver `docs/SECURITY.md › 13`): 2FA en WordPress y Hostinger, prueba de restauración de copias, monitor externo de `/_sunqu/salud`, Turnstile (opcional).
- **Datos del negocio:** teléfono, enlaces de redes, RUC, reseñas reales, precios definitivos de la tienda, revisión legal de Términos y cookies, traducciones EN/PT en Loco Translate (la integración está lista) y licencia web de Aeonik.
