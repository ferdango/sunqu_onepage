<?php
/**
 * Sunqu Journeys · punto de entrada de producción (Hostinger).
 *
 * .htaccess envía aquí todas las URLs que no son archivos (/, /descubre, /journeys/alma, /sitemap.xml…).
 * Este archivo decide qué página servir, arma su <head> SEO y le entrega el contenido ya cargado.
 * La web también funciona sin él (GitHub Pages, vista previa local): ver docs/ARCHITECTURE.md.
 */

declare(strict_types=1);

ini_set('display_errors', '0'); // antes de cargar nada: ni un error de sintaxis se muestra al visitante

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/content.php';
require __DIR__ . '/lib/idiomas.php';
require __DIR__ . '/lib/geo.php';
require __DIR__ . '/lib/routes.php';
require __DIR__ . '/lib/seo.php';
require __DIR__ . '/lib/sinjs.php';
require __DIR__ . '/lib/page.php';
require __DIR__ . '/lib/sitemap.php';
require __DIR__ . '/lib/scripts.php';
require __DIR__ . '/lib/security.php';

sunqu_endurecer_php(); // errores al log, nunca en pantalla

$cfg = sunqu_config();
$ruta = sunqu_resolver($_SERVER['REQUEST_URI'] ?? '/');
$GLOBALS['sunqu_lang'] = $ruta['lang'] ?? SUNQU_IDIOMA_BASE;
$metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$lectura = in_array($metodo, ['GET', 'HEAD'], true);

switch ($ruta['tipo']) {
	case 'redireccion':
		// 301 para las URLs que cambiaron para siempre; 302 para un idioma desactivado (puede volver)
		$temporal = !empty($ruta['temporal']);
		header('Location: ' . $cfg['SITE_URL'] . $ruta['destino'], true, $temporal ? 302 : 301);
		header($temporal ? 'Cache-Control: no-cache' : 'Cache-Control: public, max-age=3600');
		exit;

	case 'raiz':
		// La raíz no tiene idioma: lleva a /es/, /en/… según la preferencia guardada, el navegador o el país
		if (!$lectura) {
			sunqu_metodo_no_permitido('GET, HEAD');
		}
		[$lang, $fuente] = sunqu_idioma_inicial();
		header('Location: ' . $cfg['SITE_URL'] . sunqu_ruta_idioma('/', $lang) . $ruta['query'], true, 302);
		header('Cache-Control: private, no-store');
		header('Vary: Accept-Language, Cookie');
		header('X-LiteSpeed-Cache-Control: no-cache');
		// Cómo se eligió el idioma, para el evento language_detected de la medición (js/sunqu-content.js lo lee y lo borra)
		setcookie('sunqu_lang_src', $fuente, ['expires' => time() + 120, 'path' => '/', 'secure' => str_starts_with($cfg['SITE_URL'], 'https://'), 'samesite' => 'Lax']);
		exit;

	case 'sitemap':
	case 'robots':
		if (!$lectura) {
			sunqu_metodo_no_permitido('GET, HEAD');
		}
		$sitemap = $ruta['tipo'] === 'sitemap';
		header($sitemap ? 'Content-Type: application/xml; charset=utf-8' : 'Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: public, max-age=' . ($sitemap ? 300 : 3600));
		if ($metodo !== 'HEAD') {
			echo $sitemap ? sunqu_sitemap() : sunqu_robots();
		}
		exit;

	case 'purga':
		// El plugin «Sunqu CMS» llama aquí al guardar contenido (POST con el token de .env).
		if ($metodo !== 'POST') {
			sunqu_metodo_no_permitido('POST');
		}
		$token = $_SERVER['HTTP_X_SUNQU_TOKEN'] ?? ($_POST['token'] ?? '');
		if ($cfg['CACHE_PURGE_TOKEN'] === '' || !is_string($token) || !hash_equals($cfg['CACHE_PURGE_TOKEN'], $token)) {
			if (sunqu_log_permitido('purga', 10)) {
				error_log('[sunqu] purga rechazada: token ausente o incorrecto');
			}
			sunqu_cabeceras_internas(403);
			echo '{"ok":false}';
			exit;
		}
		$n = sunqu_purgar();
		sunqu_cabeceras_internas(200);
		header('X-LiteSpeed-Purge: *'); // vacía también la caché de LiteSpeed en Hostinger
		echo json_encode(['ok' => true, 'borrados' => $n]);
		exit;

	case 'csp':
		if ($metodo !== 'POST') {
			sunqu_metodo_no_permitido('POST');
		}
		sunqu_reporte_csp();
		exit;

	case 'salud':
		if (!$lectura) {
			sunqu_metodo_no_permitido('GET, HEAD');
		}
		$s = sunqu_salud();
		sunqu_cabeceras_internas($s['estado']);
		if ($metodo !== 'HEAD') {
			echo json_encode($s['datos']);
		}
		exit;

	case 'pagina':
		if (!$lectura) {
			sunqu_metodo_no_permitido('GET, HEAD');
		}
		$contenido = sunqu_contenido(sunqu_lang());
		$GLOBALS['sunqu_dic'] = (array) ($contenido['datos']['sitio']['_i18n'] ?? []); // textos fijos traducidos (sunqu_t)
		$seo = sunqu_seo($ruta['pagina'], $ruta['params'], $contenido['datos']);
		if ($seo === null) {
			break; // slug inexistente → 404
		}
		$pagina = sunqu_render($ruta['pagina'], $ruta['params'], $seo);
		sunqu_cabeceras_html(200, $contenido['origen'], $pagina['hashes'], $pagina['dominios']);
		if ($metodo !== 'HEAD') {
			echo $pagina['html'];
		}
		exit;
}

// 404: página propia con estado real 404 (sin indexar)
sunqu_cabeceras_html(404);
if ($metodo !== 'HEAD') {
	$html = (string) file_get_contents(__DIR__ . '/404.html');
	echo strtr($html, ['{{SITE_URL}}' => sunqu_e($cfg['SITE_URL'])]);
}
