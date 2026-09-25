# Handoff: Sunqu Journeys — One-page (v2)

**Web publicada:** https://ferdango.github.io/sunqu_onepage/ (GitHub Pages, rama `master`, raíz).
`index.html` es la copia publicada de `Sunqu Journeys v2.dc.html`: al editar el prototipo, copia el archivo a `index.html` y haz push.
`descubre.html` es el catálogo (buscador, filtros, journeys y experiencias); los enlaces "Descubre" del header, el menú lateral y el footer apuntan ahí. Sus datos viven en `JOURNEYS` y `EXPS` dentro del archivo.
`recuerda.html` combina el muro de reseñas (videos, fotos y comentarios; los posts reales de Instagram/TikTok se muestran con su embed oficial poniendo su enlace en `url`) y la tienda de merch del manual de marca, con bolsa y compra por WhatsApp. Las reseñas actuales son de ejemplo (`REVIEWS`) y los precios de `PRODUCTS` son de referencia.
`journey.html?j=<id>` es la ficha de un journey (`alma`, `sabor`, `profundo`, `origen`, `corto`); los botones "Ver journey" de la one-page enlazan ahí. Todo su contenido vive en el objeto `J` y en `PLAN` (consejos, notas y temporada) dentro del propio archivo.

## Overview
Landing one-page inmersiva para **Sunqu Journeys**, agencia de viajes premium al Perú ("Viaja al corazón del Perú"). Presenta la marca, el Círculo Dorado (Why/How/What), los 6 principios, los journeys, anfitriones, la ruta en mapa, el acompañamiento antes/durante/después, blog, Instagram y un formulario de contacto. Idioma base: español (selector ES/EN/PT preparado).

Repo destino: `ferdango/sunqu_onepage` (rama `master`, actualmente vacío).

## About the Design Files
`Sunqu Journeys v2.dc.html` es una **referencia de diseño hecha en HTML** (un prototipo que muestra look y comportamiento), no código de producción. La tarea es **recrearla en un stack real**. Recomendación, al no existir entorno: **Next.js (App Router) + TypeScript + Tailwind CSS**, animaciones con **GSAP + ScrollTrigger** (o Framer Motion) y **Lenis** para smooth scroll. El mapa usa **d3-geo + topojson-client + world-atlas**.

Para ver el prototipo: servir la carpeta (`python3 -m http.server`) y abrir `Sunqu Journeys v2.dc.html`. El archivo usa un runtime propio (`support.js`): el markup está entre `<x-dc>…</x-dc>` con holes `{{ }}`, `<sc-for>`/`<sc-if>`, y la lógica en la clase `Component` al final (estado, `renderVals()`, loop `requestAnimationFrame` de scroll). Léelo como spec.

## Fidelity
**High-fidelity.** Colores, tipografía, espaciado, copy e interacciones son finales. Recrear pixel-perfect. Las fotos son de Unsplash (placeholder de calidad) — reemplazar por fotografía propia cuando exista.

## Design Tokens
**Colores (manual de marca)**
- Ocre principal (acento): `#736d47` · hover `#5e5939` · pressed `#4a462d`
- Blanco base / fondo claro: `#e9eef1` · superficie alterna `#dde3e7`
- Negro base / fondo oscuro: `#1c1c1c` · tarjeta sobre oscuro `#26262a`
- Footer (black_base 2): `#2b271b`
- Secundarios: naranja `#df5f0c`, amarillo `#f4a700`, verde lima `#83b300`, verde bosque `#48725E`
- Texto secundario: `#424244`, `#5d5d60`, `#7a7a7d` · divisores `rgba(28,28,28,.16)` (claro) / `rgba(233,238,241,.14)` (oscuro)

**Tipografía — Aeonik** (archivos en `fonts/`, licencia comercial de CoType: confirmar licencia web)
- 400 Regular (cuerpo), 500 Medium, 700 Bold (títulos, usado como 600), Regular Italic. Fallback: Manrope, system-ui.
- H1 hero: `clamp(46px, 9vw, 150px)`, lh .98, tracking -.035em
- H2 sección: `clamp(33px, 4.4vw, 71px)`, lh 1.02–1.04, tracking -.02/-.03em
- H3 tarjeta: 21–28px; títulos de stack cards `clamp(34px,4.4vw,64px)`
- Cuerpo: 15–19px, lh 1.5–1.6 · Eyebrow: 12–13px, uppercase, tracking .1–.12em

**Layout**: contenedor `max-width:1440px`, padding lateral `clamp(20px,4vw,56px)`, padding vertical de sección `clamp(80px,10vw,140px)`. Grids `repeat(auto-fit, minmax(min(100%, Npx), 1fr))`.

**Radios**: pills `999px`; tarjetas stack `32px`; imágenes `18–24px`; tarjetas journey `20px`; modal `28px`. **Sombras**: `0 30px 80px rgba(0,0,0,.18)` (cards), `0 10px 30px rgba(28,28,28,.12)` (pills).

**Easing global**: `cubic-bezier(.2,.7,.1,1)`.

## Screens / Secciones (en orden)
1. **Loader** — overlay fijo `#736d47`, logo blanco centrado (min(56vw,360px)), barra de progreso 2px, texto "Viaja al corazón del Perú". Al llegar a 100% se desliza hacia arriba (`translateY(-100%)`, 1s `cubic-bezier(.7,0,.2,1)`).
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
18. **Footer** `#2b271b` — cierre "Viaja al corazón del Perú." + CTA lima; contacto (email, teléfono, oficina); fila logo / links (Inicio, Sobre Sunqu, Experiencias, Blog, FAQ, Legales) / redes circulares 48px (YouTube, Instagram, Facebook, TikTok; hover lima); copyright + "Libro de reclamaciones".

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
- `fonts/Aeonik-*.ttf` — convertir a woff2 en producción
- Fotos: Unsplash (URLs en el HTML, licencia Unsplash) — placeholders a reemplazar

## Files
- `Sunqu Journeys v2.dc.html` — prototipo completo (markup + lógica)
- `support.js` — runtime necesario solo para ver el prototipo
- `assets/`, `fonts/`

## Pendientes / datos placeholder
Handle de Instagram, email, teléfono, links de redes y blog, precios, traducciones EN/PT, licencia web de Aeonik.
