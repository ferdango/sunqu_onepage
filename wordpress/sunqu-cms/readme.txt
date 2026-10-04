=== Sunqu CMS ===
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 1.5.1

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
* API: GET /sunqu/v1/contenido[?secciones=…], GET /sunqu/v1/contenido/{seccion}, GET /sunqu/v1/esquema (con sesión).
* Formularios: POST /sunqu/v1/formularios/{solicitud|resena|reclamacion} → panel + correo (y celular, opcional).
* Importar el contenido de la web desde el panel (carpeta datos/ del zip) o con WP-CLI: wp sunqu importar | exportar | verificar.
* Seguridad (Sunqu › Seguridad): API pública mínima, XML-RPC y comentarios apagados, límite de intentos de acceso, sin enumeración de usuarios, biblioteca sin scripts y revisión del sitio.
* Idiomas (Sunqu › Idiomas): inglés y portugués en /en/… y /pt/…, traducidos con Loco Translate (plantilla languages/sunqu-cms.pot con los textos de las páginas y del contenido).
* Scripts y seguimiento (Sunqu › Scripts y seguimiento): Tag Manager, Analytics, Pixel, Hotjar… en el <head> o el <body> de todas las páginas, con el permiso del aviso de cookies.

== Modelo de contenido ==

schema.json se genera con wordpress/tools/generar-schema.py a partir de content/*.json. No lo edites a mano: agrega o cambia claves en los JSON y vuelve a generar.

== Cambios ==

= 1.5.1 =
* Scripts y seguimiento: revisa cada código activo y avisa de lo que impediría medir (Tag Manager con un ID de Analytics G-… en vez de GTM-…, gtag.js sin gtag('config'), Hotjar o un Pixel cargados sin permiso, un código sin <script>).
* Un JavaScript pegado sin <script>…</script> se envuelve al guardar (antes se veía como texto en la página).

= 1.5.0 =
* Idiomas: francés, alemán, italiano y ruso, además de inglés y portugués. La web usa /es/… y /en/… y su raíz elige el idioma (preferencia guardada, navegador, país por IP o inglés); «Idioma por país» con excepciones (FR=fr).
* Traducciones separadas por contexto en Loco Translate (interfaz, seo, contenido) y progreso por contexto en Sunqu › Idiomas.
* «Diseña tu viaje»: celular con prefijo por país, país, «¿Cómo nos encontraste?» y Mi grupo (habitaciones con adultos, niños y bebés). La persona recibe una copia completa de su solicitud, en su idioma.
* «Comparte tu viaje»: país de una lista, correo del autor (solo en el panel) y dos avisos con la marca: «Gracias por enviar tu reseña» (en revisión) y, al publicarla, «Tu reseña ya es pública».
* Correos a la persona con logo, en el idioma de la página, «Responder» a Sunqu y máximo 5 por dirección cada 24 horas.

= 1.4.0 =
* Sunqu › Idiomas: activar inglés y portugués (con su variante), «Publicar en buscadores» por idioma, selector de idioma en la web y porcentaje traducido. Textos Domain «sunqu-cms» (Domain Path /languages).
* La API entrega el contenido traducido con ?lang=en|pt (lo que falta, en español), la configuración pública de idiomas (sitio._idiomas) y los textos fijos de la web traducidos (sitio._i18n). Caché por idioma; se renueva sola cuando Loco guarda.
* Plantilla languages/sunqu-cms.pot: textos de las 8 páginas, de js/ y del servidor (i18n/textos-web.php, de tools/extraer-textos.py) más todos los textos del contenido, con dónde aparece cada uno. Se regenera al cambiar el contenido.
* Los mensajes de error de los formularios llegan en el idioma de la página.
* Sunqu › Scripts y seguimiento: hasta 20 códigos (head, inicio o final del body), cada uno «Siempre», «Con permiso de analítica» o «Con permiso de marketing»; dominios adicionales para la CSP; historial y aviso por correo de cada cambio. Solo administradores con unfiltered_html.

= 1.3.0 =
* Endurecimiento headless: sin sesión, la API solo responde /sunqu/v1/contenido y /sunqu/v1/formularios (el índice, /wp/v2 —usuarios incluidos—, oEmbed y el esquema piden sesión).
* XML-RPC, pingbacks y comentarios apagados; ?author=N ya no revela el nombre de usuario; mensajes de acceso y de «olvidé mi contraseña» sin pistas.
* Máximo 5 intentos de acceso fallidos por IP cada 15 minutos. Contraseñas de aplicación y editor de archivos del panel desactivados.
* Biblioteca: solo imágenes, video y PDF; wp-content/uploads/.htaccess impide ejecutar scripts. El plugin no sirve ningún archivo por la web.
* CORS de toda la API limitado a la web (nunca «cualquier origen» ni credenciales); los formularios rechazan otros orígenes y envíos de más de 16 MB.
* Fotos de reseñas: se valida el tipo real y las medidas y se vuelven a codificar (sin EXIF ni GPS). Las respuestas ya no incluyen el ID interno.
* Solo Editores y Administradores ven las solicitudes, el libro de reclamaciones y los ajustes del sitio.
* Tokens (purga, Telegram) que no se vuelven a mostrar en el panel; webhook solo https y sin direcciones internas.
* Nueva página Sunqu › Seguridad (revisión de versiones, usuarios, permisos y configuración). Fecha de edición («_modificado») en la API.

= 1.2.0 =
* Avisos de los formularios a sunqujourneys@gmail.com (o a los correos de Sunqu › Conexión › Avisos) en HTML, con «Responder a» al visitante.
* SMTP propio desde wp-config.php (SUNQU_SMTP_*), sin plugins extra; estado del envío y «Reenviar» en cada ficha.
* Avisos al celular opcionales: Telegram (bot + chat ID) y webhook (Make, Zapier, n8n → WhatsApp, Slack, SMS).
* Botones «Enviar correo de prueba», «Detectar chat de Telegram» y «Enviar aviso de prueba al celular».

= 1.1.0 =
* Botón «Importar el contenido» en Sunqu › Conexión y herramientas (sin SSH).
* Anti-spam, validaciones del servidor y constancias en los formularios; código correlativo del libro de reclamaciones.
* Aviso a la web al publicar (token de purga), CORS limitado a la web, tamaños de imagen en la API (_imagenes).
