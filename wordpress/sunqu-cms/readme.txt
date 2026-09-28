=== Sunqu CMS ===
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0

Administra el contenido de la web de Sunqu Journeys y lo entrega en /wp-json/sunqu/v1 con el mismo formato que content/*.json.

== Instalación ==

1. Copia esta carpeta en wp-content/plugins/ y activa «Sunqu CMS».
2. Instala y activa «Secure Custom Fields» (o ACF Pro) para editar los campos desde el panel.
3. Ajustes › Enlaces permanentes › «Nombre de la entrada».
4. Importa el contenido: wp sunqu importar --dir=/ruta/web/content --front=/ruta/web --user=<admin>
5. Verifica: wp sunqu verificar --dir=/ruta/web/content

Manual completo: docs/WORDPRESS.md en el repositorio de la web.

== Qué agrega ==

* Tipos de contenido: Journeys, Anfitriones, Experiencias, Reseñas, Productos (tienda).
* Menú Sunqu: Sitio, Inicio (portada), Journeys · común, Reseñas · temas y plataformas, Tienda · categorías, Legales, Solicitudes de viaje, Libro de reclamaciones y Conexión y herramientas.
* Diario Sunqu con las Entradas nativas (caja «Diario Sunqu»: minutos de lectura, autor, rol, entradilla).
* API: GET /sunqu/v1/contenido[?secciones=…], GET /sunqu/v1/contenido/{seccion}, GET /sunqu/v1/esquema.
* Formularios: POST /sunqu/v1/formularios/{solicitud|resena|reclamacion}.
* WP-CLI: wp sunqu importar | exportar | verificar.

== Modelo de contenido ==

schema.json se genera con wordpress/tools/generar-schema.py a partir de content/*.json. No lo edites a mano: agrega o cambia claves en los JSON y vuelve a generar.
