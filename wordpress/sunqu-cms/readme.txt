=== Sunqu CMS ===
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.1.0

Administra el contenido de la web de Sunqu Journeys y lo entrega en /wp-json/sunqu/v1 con el mismo formato que content/*.json.

== Instalación ==

1. Plugins › Añadir nuevo › Subir plugin › sunqu-cms.zip (lo arma tools/empaquetar-wordpress.sh) › Activar.
2. El tema va aparte: Apariencia › Temas › Añadir tema › Subir tema › sunqu-headless.zip › Activar.
3. Instala y activa «Secure Custom Fields» (o ACF Pro) para editar los campos desde el panel.
4. Ajustes › Enlaces permanentes › «Nombre de la entrada».
5. Importa el contenido: Sunqu › Conexión y herramientas › Importar el contenido.
   Por consola: wp sunqu importar --dir=/ruta/web/content --front=/ruta/web --user=<admin>
6. Verifica por consola: wp sunqu verificar --dir=/ruta/web/content

Manual completo: docs/WORDPRESS.md en el repositorio de la web.

== Qué agrega ==

* Tipos de contenido: Journeys, Anfitriones, Experiencias, Reseñas, Productos (tienda).
* Menú Sunqu: Sitio, Inicio (portada), Journeys · común, Reseñas · temas y plataformas, Tienda · categorías, Legales, Solicitudes de viaje, Libro de reclamaciones y Conexión y herramientas.
* Diario Sunqu con las Entradas nativas (caja «Diario Sunqu»: minutos de lectura, autor, rol, entradilla).
* API: GET /sunqu/v1/contenido[?secciones=…], GET /sunqu/v1/contenido/{seccion}, GET /sunqu/v1/esquema.
* Formularios: POST /sunqu/v1/formularios/{solicitud|resena|reclamacion}.
* Importar el contenido de la web desde el panel (carpeta datos/ del zip) o con WP-CLI: wp sunqu importar | exportar | verificar.

== Modelo de contenido ==

schema.json se genera con wordpress/tools/generar-schema.py a partir de content/*.json. No lo edites a mano: agrega o cambia claves en los JSON y vuelve a generar.

== Cambios ==

= 1.1.0 =
* Botón «Importar el contenido» en Sunqu › Conexión y herramientas (sin SSH).
* Anti-spam, validaciones del servidor y constancias en los formularios; código correlativo del libro de reclamaciones.
* Aviso a la web al publicar (token de purga), CORS limitado a la web, tamaños de imagen en la API (_imagenes).
