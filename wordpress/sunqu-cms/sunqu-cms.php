<?php
/**
 * Plugin Name:       Sunqu CMS
 * Description:       Contenido de sunqujourneys (journeys, experiencias, anfitriones, reseñas, tienda, Diario, portada y ajustes) para la web estática. Expone la API /wp-json/sunqu/v1 con el mismo formato que content/*.json y recibe los formularios.
 * Version:           1.5.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Sunqu Journeys
 * Text Domain:       sunqu-cms
 * Domain Path:       /languages
 *
 * Estructura
 *   schema.json              Modelo de contenido (se genera con wordpress/tools/generar-schema.py).
 *   includes/datos.php       Motor: lee y escribe campos según el esquema (formato compatible con ACF/SCF).
 *   includes/registro.php    Tipos de contenido, páginas de ajustes y grupos de campos.
 *   includes/diario.php      Entradas del Diario ↔ secciones y bloques de la web.
 *   includes/api.php         GET /sunqu/v1/contenido (con caché) y CORS.
 *   includes/formularios.php POST /sunqu/v1/formularios/{solicitud|resena|reclamacion}.
 *   includes/avisos.php      Aviso de cada envío: correo HTML (SMTP propio) y, opcional, al celular (Telegram / webhook).
 *   includes/idiomas.php     Sunqu › Idiomas: inglés y portugués traducidos con Loco Translate (plantilla languages/sunqu-cms.pot).
 *   includes/scripts.php     Sunqu › Scripts y seguimiento: GTM, Analytics, Pixel… en el <head> o el <body>, con consentimiento.
 *   includes/seguridad.php   Endurecimiento headless: API pública mínima, XML-RPC y comentarios apagados, límite de
 *                            intentos de acceso, sin enumeración de usuarios, biblioteca sin scripts. Sunqu › Seguridad.
 *   includes/importador.php  content/*.json → WordPress (lo usan el panel y WP-CLI).
 *   includes/admin.php       Menú «Sunqu»: conexión, importar el contenido y herramientas.
 *   includes/cli.php         wp sunqu importar | exportar | verificar.
 *   datos/                   (solo en el zip de tools/empaquetar-wordpress.sh) contenido de la web para importar.
 */

defined('ABSPATH') || exit;

define('SUNQU_CMS_VERSION', '1.5.0');
define('SUNQU_CMS_DIR', plugin_dir_path(__FILE__));
define('SUNQU_CMS_URL', plugin_dir_url(__FILE__));

require_once SUNQU_CMS_DIR . 'includes/datos.php';
require_once SUNQU_CMS_DIR . 'includes/registro.php';
require_once SUNQU_CMS_DIR . 'includes/diario.php';
require_once SUNQU_CMS_DIR . 'includes/idiomas.php';
require_once SUNQU_CMS_DIR . 'includes/api.php';
require_once SUNQU_CMS_DIR . 'includes/formularios.php';
require_once SUNQU_CMS_DIR . 'includes/avisos.php';
require_once SUNQU_CMS_DIR . 'includes/scripts.php';
require_once SUNQU_CMS_DIR . 'includes/seguridad.php';
require_once SUNQU_CMS_DIR . 'includes/importador.php';
require_once SUNQU_CMS_DIR . 'includes/admin.php';

if (defined('WP_CLI') && WP_CLI) {
	require_once SUNQU_CMS_DIR . 'includes/cli.php';
}

register_activation_hook(__FILE__, function () {
	sunqu_registrar_tipos();
	sunqu_registrar_tipos_envios();
	flush_rewrite_rules();
	sunqu_proteger_uploads(); // /wp-content/uploads/.htaccess: los archivos subidos no se ejecutan
});
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
