<?php
/**
 * Sunqu Journeys · punto de entrada de producción (Hostinger).
 *
 * .htaccess envía aquí todas las URLs que no son archivos (/, /descubre, /journeys/alma, /sitemap.xml…).
 * Este archivo decide qué página servir, arma su <head> SEO y le entrega el contenido ya cargado.
 * La web también funciona sin él (GitHub Pages, vista previa local): ver docs/ARCHITECTURE.md.
 */

declare(strict_types=1);

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/content.php';
require __DIR__ . '/lib/routes.php';
require __DIR__ . '/lib/seo.php';
require __DIR__ . '/lib/page.php';
require __DIR__ . '/lib/sitemap.php';
require __DIR__ . '/lib/security.php';

$cfg = sunqu_config();
$ruta = sunqu_resolver($_SERVER['REQUEST_URI'] ?? '/');

switch ($ruta['tipo']) {
	case 'redireccion':
		header('Location: ' . $cfg['SITE_URL'] . $ruta['destino'], true, 301);
		header('Cache-Control: public, max-age=3600');
		exit;

	case 'sitemap':
		header('Content-Type: application/xml; charset=utf-8');
		header('Cache-Control: public, max-age=300');
		echo sunqu_sitemap();
		exit;

	case 'robots':
		header('Content-Type: text/plain; charset=utf-8');
		header('Cache-Control: public, max-age=3600');
		echo sunqu_robots();
		exit;

	case 'purga':
		// El plugin «Sunqu CMS» llama aquí al guardar contenido (POST con el token de .env).
		$token = $_SERVER['HTTP_X_SUNQU_TOKEN'] ?? ($_POST['token'] ?? '');
		if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $cfg['CACHE_PURGE_TOKEN'] === '' || !hash_equals($cfg['CACHE_PURGE_TOKEN'], (string) $token)) {
			http_response_code(403);
			header('Content-Type: application/json');
			echo '{"ok":false}';
			exit;
		}
		$n = sunqu_purgar();
		header('X-LiteSpeed-Purge: *'); // vacía también la caché de LiteSpeed en Hostinger
		header('Content-Type: application/json');
		echo json_encode(['ok' => true, 'borrados' => $n]);
		exit;

	case 'pagina':
		$contenido = sunqu_contenido();
		$seo = sunqu_seo($ruta['pagina'], $ruta['params'], $contenido['datos']);
		if ($seo === null) {
			break; // slug inexistente → 404
		}
		sunqu_cabeceras_html(200, $contenido['origen']);
		if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
			echo sunqu_render($ruta['pagina'], $ruta['params'], $seo);
		}
		exit;
}

// 404: página propia con estado real 404 (sin indexar)
sunqu_cabeceras_html(404);
$html = (string) file_get_contents(__DIR__ . '/404.html');
echo strtr($html, ['{{SITE_URL}}' => sunqu_e($cfg['SITE_URL'])]);
