<?php
/**
 * Cabeceras de seguridad de las páginas HTML. Las comunes a todos los archivos están en .htaccess;
 * aquí va la Content-Security-Policy, que depende de la configuración (WordPress, GTM, GA4, Pixel).
 *
 * El runtime de plantillas compila la lógica de cada página con new Function() y hay estilos y scripts
 * en línea, por eso script-src necesita 'unsafe-inline' y 'unsafe-eval'. Aun así la política limita de
 * dónde se cargan scripts, imágenes y conexiones, bloquea <object>, <base> y el enmarcado del sitio.
 * Si agregas un servicio nuevo en GTM (otro píxel, un chat), suma su dominio aquí.
 */

declare(strict_types=1);

function sunqu_csp(): string {
	$cfg = sunqu_config();
	$wp = $cfg['WORDPRESS_API_URL'] ? sunqu_origen($cfg['WORDPRESS_API_URL']) : '';
	$tag = $cfg['GTM_ID'] || $cfg['GA4_ID'];
	$pixel = (bool) $cfg['META_PIXEL_ID'] || (bool) $cfg['GTM_ID'];
	// React se sirve desde vendor/; unpkg solo queda como respaldo si faltan esos archivos
	$unpkg = !is_file(SUNQU_ROOT . '/vendor/react.production.min.js') || !is_file(SUNQU_ROOT . '/vendor/react-dom.production.min.js');
	$src = [
		'default-src' => ["'self'"],
		'script-src' => array_filter(["'self'", "'unsafe-inline'", "'unsafe-eval'", $unpkg ? 'https://unpkg.com' : '',
			$tag ? 'https://www.googletagmanager.com' : '', $tag ? 'https://*.googletagmanager.com' : '', $pixel ? 'https://connect.facebook.net' : '']),
		'style-src' => ["'self'", "'unsafe-inline'"],
		'img-src' => array_filter(["'self'", 'data:', 'blob:', 'https://images.unsplash.com', $wp,
			$tag ? 'https://*.google-analytics.com' : '', $tag ? 'https://*.googletagmanager.com' : '',
			$tag ? 'https://*.g.doubleclick.net' : '', $tag ? 'https://www.google.com' : '', $tag ? 'https://www.google.com.pe' : '',
			$pixel ? 'https://www.facebook.com' : '']),
		'font-src' => ["'self'", 'data:'],
		'connect-src' => array_filter(["'self'", $wp,
			$tag ? 'https://*.google-analytics.com' : '', $tag ? 'https://*.analytics.google.com' : '', $tag ? 'https://*.googletagmanager.com' : '',
			$tag ? 'https://*.g.doubleclick.net' : '', $tag ? 'https://www.google.com' : '', $tag ? 'https://www.google.com.pe' : '',
			$pixel ? 'https://www.facebook.com' : '', $pixel ? 'https://connect.facebook.net' : '']),
		'media-src' => array_filter(["'self'", 'blob:', $wp]),
		'frame-src' => array_filter(['https://www.instagram.com', 'https://www.tiktok.com', $tag ? 'https://www.googletagmanager.com' : '']),
		'frame-ancestors' => ["'self'"],
		'base-uri' => ["'self'"],
		'form-action' => ["'self'"],
		'object-src' => ["'none'"],
	];
	$p = [];
	foreach ($src as $k => $v) {
		$p[] = $k . ' ' . implode(' ', array_unique($v));
	}
	if (!sunqu_es_staging() && str_starts_with($cfg['SITE_URL'], 'https://')) {
		$p[] = 'upgrade-insecure-requests';
	}
	return implode('; ', $p);
}

/**
 * $origen: de dónde salió el contenido (sunqu_contenido()). LiteSpeed solo cachea páginas armadas con contenido
 * vigente: una copia vencida o el respaldo de content/*.json se sirven sin guardarse, para que el cambio
 * publicado aparezca apenas WordPress responda.
 */
function sunqu_cabeceras_html(int $estado = 200, string $origen = 'local'): void {
	$cfg = sunqu_config();
	http_response_code($estado);
	header('Content-Type: text/html; charset=utf-8');
	// El HTML se revalida siempre; LiteSpeed (Hostinger) puede cachearlo 5 min y se purga al publicar.
	header('Cache-Control: public, max-age=0, must-revalidate');
	// HSTS también desde aquí: el «env=HTTPS» del .htaccess puede no existir en LiteSpeed
	$https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
	if ($https && !sunqu_es_staging()) {
		header('Strict-Transport-Security: max-age=31536000');
	}
	if ($estado === 200 && !sunqu_es_staging() && in_array($origen, ['wordpress', 'cache', 'local'], true)) {
		header('X-LiteSpeed-Cache-Control: public, max-age=' . max(60, (int) $cfg['CONTENT_CACHE_TTL']));
		header('X-LiteSpeed-Tag: sunqu');
	} else {
		header('X-LiteSpeed-Cache-Control: no-cache');
	}
	if ($cfg['CSP_MODE'] === 'enforce') {
		header('Content-Security-Policy: ' . sunqu_csp());
	} elseif ($cfg['CSP_MODE'] === 'report') {
		header('Content-Security-Policy-Report-Only: ' . sunqu_csp());
	}
	if (sunqu_es_staging()) {
		header('X-Robots-Tag: noindex, nofollow');
	}
}
