# Sunqu Journeys · Integración con WordPress

Manual para el desarrollador. Explica cómo está armada la web, cómo levantar WordPress en local en unos minutos y dónde está cada apartado para integrarlo rápido.

---

## 1. En 30 segundos

La web sigue siendo **estática**: `index.html`, `journey.html`, `descubre.html` y demás páginas, con el mismo diseño de siempre. Lo que cambió es **de dónde sale el contenido**. Ya no está escrito dentro de cada página: vive en `content/*.json` y, si se configura, lo entrega **WordPress** por su API REST.

```
                        ┌─────────────────────────────────────────────┐
  editor ──► wp-admin ──►  WordPress + plugin «Sunqu CMS»              │
                        │  (journeys, experiencias, anfitriones,       │
                        │   reseñas, tienda, Diario, portada, ajustes) │
                        └───────────────┬─────────────────────────────┘
                                        │ GET /wp-json/sunqu/v1/contenido
                                        │ POST /wp-json/sunqu/v1/formularios/…
                                        ▼
  visitante ──► index.html … ──► js/sunqu-content.js ──► support.js (runtime) ──► página
                                        ▲
                                        │ si no hay WordPress (o no responde)
                                   content/*.json
```

- **Headless.** WordPress solo administra el contenido; la web puede seguir en GitHub Pages o en cualquier hosting estático.
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
  sunqu-config.js     ◆ URL de la API de WordPress (vacío = usar content/)
  sunqu-content.js    carga el contenido, lo adapta a cada página y envía los formularios

wordpress/                                                   ◆ CMS
  setup.sh            levanta todo con Docker, instala, importa y verifica
  docker-compose.yml  WordPress + MariaDB + WP-CLI + la web (nginx)
  .env.example        puertos y credenciales locales (se copia como .env)
  local/sunqu-config.js  configuración de la web dentro de Docker (apunta al WordPress local)
  sunqu-cms/          plugin «Sunqu CMS»
    schema.json       modelo de contenido (generado)
    includes/…        motor de datos, registro, API, formularios, Diario, panel y WP-CLI
  sunqu-headless/     tema mínimo: redirige el front de WordPress a la web
  tools/generar-schema.py  genera schema.json desde content/*.json

docs/WORDPRESS.md     este manual
```

---

## 3. Arranque rápido (local, con Docker)

**Requisitos:** Docker Desktop abierto y los puertos 8080 y 8081 libres. Se cambian en `wordpress/.env`.

```bash
cd wordpress
./setup.sh
```

El script hace todo esto y termina en unos minutos. La primera vez descarga las imágenes de Docker y sube las fotos a la biblioteca.
1. Copia `.env.example` → `.env` (puertos y credenciales **solo locales**).
2. Levanta `db`, `wordpress` y `web`.
3. Instala WordPress en español, **Secure Custom Fields** (gratis), el plugin **Sunqu CMS** y el tema **Sunqu Headless**.
4. Configura enlaces permanentes y la URL de la web.
5. Importa `content/*.json` con `wp sunqu importar` (con imágenes y videos a la biblioteca).
6. Corre `wp sunqu verificar`, que confirma que la API devuelve exactamente el contenido de los JSON.

| Qué | URL |
|---|---|
| Panel de WordPress | http://localhost:8080/wp-admin (usuario y clave en `wordpress/.env`) |
| API de contenido | http://localhost:8080/wp-json/sunqu/v1/contenido |
| La web leyendo de WordPress | http://localhost:8081 |

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

1. Copia `wordpress/sunqu-cms` en `wp-content/plugins/` y `wordpress/sunqu-headless` en `wp-content/themes/`.
2. Instala y activa **Secure Custom Fields**. ACF Pro también sirve.
3. Activa **Sunqu CMS** y el tema **Sunqu Headless**.
4. En Ajustes › Enlaces permanentes elige «Nombre de la entrada». Así funciona `/wp-json/`.
5. Importa: `wp sunqu importar --dir=/ruta/a/la/web/content --front=/ruta/a/la/web --user=<admin>`.
6. Verifica: `wp sunqu verificar --dir=/ruta/a/la/web/content`.

---

## 4. Conectar la web con WordPress

Edita **`js/sunqu-config.js`**:

```js
window.SUNQU_CMS = {
  api: 'https://cms.sunqujourneys.com/wp-json',   // '' = usar content/*.json
};
```

Cada página carga, en el `<head>`, `js/sunqu-config.js` y después `js/sunqu-content.js` con `data-page="…"`. Este último:

1. Pide a WordPress solo las secciones que necesita esa página:
   `GET {api}/sunqu/v1/contenido?secciones=sitio,journeys,anfitriones`
2. Si WordPress no responde, usa `content/*.json` y lo avisa en la consola.
3. Adapta el contenido al formato de la página (`window.SunquView`) y recién ahí arranca `support.js`.

En la consola del navegador, `SUNQU_ORIGEN` dice de dónde salió el contenido: `wordpress`, `local` o `servidor`.

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
| `GET /esquema` | `schema.json` |

- **Imágenes:** la API devuelve URLs de la biblioteca. La web también acepta rutas `assets/…` e ids de Unsplash, por eso los JSON y la API son intercambiables.
- **Caché:** las respuestas se guardan en caché hasta que se guarda cualquier contenido (y con `Cache-Control: max-age=60`). Para vaciarla: Sunqu › Conexión › **Limpiar caché**.
- **CORS:** por defecto cualquier origen puede leer la API. En **Sunqu › Conexión › Orígenes permitidos** se limita a los dominios de la web.

### Formularios

`POST /formularios/{tipo}` con `Content-Type: application/json`. La web ya los envía mediante `SunquContent.enviar(tipo, datos)`. Sin `api` configurada, solo muestra la confirmación, como antes.

| Tipo | Campos (* obligatorios) | Queda en |
|---|---|---|
| `solicitud` | nombre*, email*, mes, sentir, mensaje, guardados, pagina | Sunqu › Solicitudes de viaje + correo de aviso |
| `resena` | texto*, nombre, pais, tema, enlace, estrellas, fotos `[{nombre, data}]` (data URL, máx. 4) | Reseñas › Pendiente + aviso |
| `reclamacion` | nombre*, email*, numDoc*, servicio*, descripcion*, tipo*, detalle*, pedido*, tipoDoc, celular, domicilio, apoderado, monto, fecha, codigo, identificacion, menor | Sunqu › Libro de reclamaciones + correo al consumidor y a la empresa |

- **Respuestas:** `{ "ok": true, "id": 123 }`. Los errores devuelven `{ "code", "message" }` con estado 400 (falta un campo o el correo no es válido) o 429 (más de 8 envíos en 10 minutos desde la misma IP).
- **Anti-spam:** si llega el campo `website` (campo trampa), el envío se descarta en silencio.
- **Destino de los avisos:** **Sunqu › Conexión › Correo de avisos**. Si está vacío, se usa el correo de Sunqu › Sitio.

---

## 7. Cómo viaja un cambio (ejemplo)

Cambiar el precio de «El Alma de los Andes»:

1. wp-admin › **Journeys** › El Alma de los Andes › *Ficha › Precio* = «Desde USD 4.500» › **Actualizar**.
2. El plugin vacía su caché.
3. La siguiente visita a `journey.html?j=alma` pide `/sunqu/v1/contenido?secciones=sitio,journeys,anfitriones` y muestra el precio nuevo.
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

1. Hosting con PHP 8.1+ y MySQL/MariaDB (por ejemplo `cms.sunqujourneys.com`), con HTTPS.
2. Sube el plugin `sunqu-cms` y el tema `sunqu-headless`. Instala Secure Custom Fields y activa los tres.
3. Enlaces permanentes: «Nombre de la entrada».
4. Importa el contenido (WP-CLI del hosting o migra la base de datos local).
5. En **Sunqu › Conexión**:
   - **URL de la web**: la dirección de la web pública;
   - **Orígenes permitidos**: los dominios de la web, por ejemplo `https://sunqujourneys.com` y `https://ferdango.github.io`;
   - **Correo de avisos**.
6. Configura el envío de correo (SMTP, por ejemplo con WP Mail SMTP). Sin eso, `wp_mail` puede no entregar.
7. En la web, `js/sunqu-config.js` › `api: 'https://cms.sunqujourneys.com/wp-json'`. Después publica.
8. Recomendado: copias de seguridad diarias y una caché o CDN delante de `/wp-json/sunqu/v1/contenido`, que ya envía `Cache-Control`.

### 8.5 Mantener el respaldo estático al día

`wp sunqu exportar --dir=content`, revisa el diff y haz commit. Si WordPress no responde, la web mostrará ese contenido.

### 8.6 Moderar reseñas y ver envíos

- **Reseñas › Pendientes**: revisa, completa (Tema, «Qué vivió o compró») y **Publica**.
- **Sunqu › Solicitudes de viaje** y **Sunqu › Libro de reclamaciones**: cada envío con todos sus datos. Son privados y no salen por la API.

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
| No llegan los correos | Configura SMTP (8.4.6). Los envíos igual quedan guardados en el panel |
| Un cambio no aparece en la web | Sunqu › Conexión › Limpiar caché. Si hay CDN, espera 60 s o púrgala |
| `wp sunqu verificar` muestra diferencias | Alguien editó en el panel después de importar (es normal) o cambió la estructura sin regenerar el esquema |

---

## 11. Estado y pendientes

- **Web + `content/`: probado.** Las 8 páginas cargan desde `content/*.json` y el resultado (DOM y estilos) es idéntico al de antes de la integración, en escritorio y móvil. Las vistas de `js/sunqu-content.js` reconstruyen exactamente los datos originales de cada página.
- **Web ↔ API: probado contra una API simulada** con el mismo contrato que el plugin:
  - la web lee de `/sunqu/v1/contenido`;
  - los tres formularios envían sus datos a `/sunqu/v1/formularios/…` (la reseña, con la foto como JPEG redimensionado);
  - si la API no responde, la web vuelve sola a `content/*.json` y el formulario muestra su mensaje de error.
- **Plugin de WordPress: escrito, revisado y simulado, pero todavía no ejecutado en un WordPress real.** Tuvo una revisión de código independiente y una simulación de su motor de datos (escribir → base de datos → leer), que da 0 diferencias en las 9 secciones. Su primera corrida real es `./setup.sh`, que termina con `wp sunqu verificar`; debe decir «WordPress devuelve exactamente el contenido de los JSON».
- **Pendientes de negocio:**
  - RUC y dirección reales (Sunqu › Sitio › Empresa);
  - teléfono y enlaces de redes (hoy `#`);
  - reseñas reales (las actuales son de ejemplo);
  - revisión legal de los Términos;
  - SMTP para los correos;
  - traducciones EN/PT (el selector de idioma todavía no cambia el contenido).
