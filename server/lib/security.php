<?php
/**
 * Seguridad de la capa PHP.
 *
 *   · Errores: nunca se muestran al visitante. Van al log del servidor y la página responde un 500 genérico.
 *   · Content-Security-Policy de las páginas HTML. Las cabeceras comunes a todos los archivos (nosniff,
 *     Referrer-Policy, X-Frame-Options, Permissions-Policy, HSTS) están en .htaccess.
 *
 * CSP: el runtime de plantillas compila la lógica de cada página con new Function(), por eso script-src necesita
 * 'unsafe-eval'. Los scripts en línea (configuración de la página y arte del pie) se autorizan por su hash SHA-256:
 * un <script> o un onclick="…" inyectado no se ejecuta. CSP_INLINE_SCRIPTS=allow vuelve a 'unsafe-inline'
 * (solo si GTM usa etiquetas «HTML personalizado»). Si agregas un servicio nuevo en GTM, suma su dominio aquí.
 */

declare(strict_types=1);

/** Producción: errores al log, nunca en pantalla; sin X-Powered-By. Se llama al empezar server/index.php. */
function sunqu_endurecer_php(): void {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	ini_set('log_errors', '1');
	error_reporting(E_ALL);
	header_remove('X-Powered-By');
	set_exception_handler(function (Throwable $e): void {
		error_log('[sunqu] ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
		sunqu_error_500();
	});
	register_shutdown_function(function (): void {
		$e = error_get_last();
		if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
			sunqu_error_500(); // el detalle ya quedó en el log (log_errors)
		}
	});
}

/** Página 500 sin detalles internos. Si ya salió parte de la respuesta, no se agrega nada. */
function sunqu_error_500(): void {
	if (headers_sent()) {
		return;
	}
	http_response_code(500);
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: no-store');
	header('X-LiteSpeed-Cache-Control: no-cache');
	header('X-Robots-Tag: noindex');
	$f = __DIR__ . '/../500.html';
	echo is_file($f) ? (string) file_get_contents($f) : 'Error del servidor';
}

/** Responde 405 con los métodos permitidos. */
function sunqu_metodo_no_permitido(string $permitidos): void {
	http_response_code(405);
	header('Allow: ' . $permitidos);
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: no-store');
	echo 'Método no permitido';
	exit;
}

/** Hash CSP (base64 de SHA-256) de un script en línea, tal como llega al navegador. */
function sunqu_csp_hash(string $script): string {
	return base64_encode(hash('sha256', $script, true));
}

/**
 * @param string[] $hashes hashes de los scripts en línea de la página (sunqu_render)
 * @param bool     $meta   versión para <meta http-equiv>: sin frame-ancestors ni reportes (el navegador los ignora ahí)
 * @param string[] $extra  dominios de los scripts de WordPress › Sunqu › Scripts y seguimiento (server/lib/scripts.php)
 */
function sunqu_csp(array $hashes = [], bool $meta = false, array $extra = []): string {
	$cfg = sunqu_config();
	$wp = $cfg['WORDPRESS_API_URL'] ? sunqu_origen($cfg['WORDPRESS_API_URL']) : '';
	// Google y Meta pegados en Sunqu › Scripts y seguimiento: los mismos dominios que con sus IDs en el .env
	// (envían los datos a otros dominios que los que aparecen en su código)
	$extra = array_values(array_filter(array_map('sunqu_dominio_csp', $extra)));
	$panel = implode(' ', $extra);
	$gtm = (bool) $cfg['GTM_ID'] || str_contains($panel, 'googletagmanager.com');
	$tag = $gtm || $cfg['GA4_ID'] || str_contains($panel, 'google-analytics.com');
	$pixel = (bool) $cfg['META_PIXEL_ID'] || $gtm || str_contains($panel, 'connect.facebook.net');
	// React se sirve desde vendor/; unpkg solo queda como respaldo si faltan esos archivos
	$unpkg = !is_file(SUNQU_ROOT . '/vendor/react.production.min.js') || !is_file(SUNQU_ROOT . '/vendor/react-dom.production.min.js');
	$enLinea = $cfg['CSP_INLINE_SCRIPTS'] === 'allow' ? ["'unsafe-inline'"] : array_map(fn($h) => "'sha256-$h'", array_values(array_unique($hashes)));
	// Vista previa de GTM (Tag Assistant): dominios que pide Google para depurar el contenedor
	$gtmPrevia = $gtm ? ['https://tagmanager.google.com'] : [];
	$turnstile = $cfg['TURNSTILE_SITE_KEY'] !== '' ? 'https://challenges.cloudflare.com' : '';
	$src = [
		'default-src' => ["'self'"],
		'script-src' => array_filter(array_merge(["'self'"], $enLinea, ["'unsafe-eval'", $unpkg ? 'https://unpkg.com' : '',
			$tag ? 'https://www.googletagmanager.com' : '', $tag ? 'https://*.googletagmanager.com' : '', $pixel ? 'https://connect.facebook.net' : '', $turnstile], $gtmPrevia)),
		'style-src' => array_merge(["'self'", "'unsafe-inline'"], $gtm ? ['https://www.googletagmanager.com', 'https://tagmanager.google.com', 'https://fonts.googleapis.com'] : []),
		'img-src' => array_filter(["'self'", 'data:', 'blob:', 'https://images.unsplash.com', $wp,
			$tag ? 'https://*.google-analytics.com' : '', $tag ? 'https://*.googletagmanager.com' : '',
			$tag ? 'https://*.g.doubleclick.net' : '', $tag ? 'https://www.google.com' : '', $tag ? 'https://www.google.com.pe' : '',
			$pixel ? 'https://www.facebook.com' : '', $gtm ? 'https://ssl.gstatic.com' : '', $gtm ? 'https://www.gstatic.com' : '']),
		'font-src' => array_merge(["'self'", 'data:'], $gtm ? ['https://fonts.gstatic.com'] : []),
		'connect-src' => array_filter(["'self'", $wp,
			$tag ? 'https://*.google-analytics.com' : '', $tag ? 'https://*.analytics.google.com' : '', $tag ? 'https://*.googletagmanager.com' : '',
			$tag ? 'https://*.g.doubleclick.net' : '', $tag ? 'https://www.google.com' : '', $tag ? 'https://www.google.com.pe' : '',
			$pixel ? 'https://www.facebook.com' : '', $pixel ? 'https://connect.facebook.net' : '']),
		'media-src' => array_filter(["'self'", 'blob:', $wp]),
		'frame-src' => array_filter(['https://www.instagram.com', 'https://www.tiktok.com', $tag ? 'https://www.googletagmanager.com' : '', $turnstile]),
		'worker-src' => ["'self'", 'blob:'],
		'manifest-src' => ["'self'"],
		'frame-ancestors' => ["'self'"],
		'base-uri' => ["'self'"],
		'form-action' => ["'self'"],
		'object-src' => ["'none'"],
	];
	// Servicios agregados en el panel: pueden cargar scripts, estilos, imágenes y fuentes, conectarse y mostrar iframes
	// (wss:// solo sirve para conectarse)
	foreach (['script-src', 'style-src', 'img-src', 'font-src', 'connect-src', 'frame-src', 'media-src'] as $k) {
		$src[$k] = array_merge($src[$k], $k === 'connect-src' ? $extra : array_filter($extra, fn($d) => !str_starts_with($d, 'wss://')));
	}
	if ($meta) {
		unset($src['frame-ancestors']);
	}
	$p = [];
	foreach ($src as $k => $v) {
		$p[] = $k . ' ' . implode(' ', array_unique($v));
	}
	if (!sunqu_es_staging() && str_starts_with($cfg['SITE_URL'], 'https://')) {
		$p[] = 'upgrade-insecure-requests';
	}
	if (!$meta) {
		// Las violaciones llegan a /_sunqu/csp (van al log del servidor, con límite por minuto)
		$p[] = 'report-uri /_sunqu/csp';
		if (str_starts_with($cfg['SITE_URL'], 'https://')) {
			$p[] = 'report-to csp';
		}
	}
	return implode('; ', $p);
}

/**
 * La misma política en una etiqueta <meta>, primera del <head>. Hostinger agrega a todas las respuestas
 * «Content-Security-Policy: upgrade-insecure-requests» y ese encabezado reemplaza el que envía PHP: con la
 * etiqueta, el navegador aplica igual la política completa (las dos se suman). frame-ancestors no funciona en
 * <meta>: el enmarcado lo sigue impidiendo X-Frame-Options.
 */
function sunqu_csp_meta(array $hashes, array $extra = []): string {
	return sunqu_config()['CSP_MODE'] === 'enforce' ? '<meta http-equiv="Content-Security-Policy" content="' . sunqu_e(sunqu_csp($hashes, true, $extra)) . '">' : '';
}

/**
 * $origen: de dónde salió el contenido (sunqu_contenido()). LiteSpeed solo cachea páginas armadas con contenido
 * vigente: una copia vencida o el respaldo de content/*.json se sirven sin guardarse, para que el cambio
 * publicado aparezca apenas WordPress responda. $hashes: scripts en línea autorizados (CSP).
 */
function sunqu_cabeceras_html(int $estado = 200, string $origen = 'local', array $hashes = [], array $extra = []): void {
	$cfg = sunqu_config();
	http_response_code($estado);
	header('Content-Type: text/html; charset=utf-8');
	// El HTML se revalida siempre; LiteSpeed (Hostinger) puede cachearlo 5 min y se purga al publicar.
	header('Cache-Control: public, max-age=0, must-revalidate');
	// HSTS también desde aquí: el «env=HTTPS» del .htaccess puede no existir en LiteSpeed
	if (sunqu_es_https() && !sunqu_es_staging()) {
		header('Strict-Transport-Security: max-age=31536000');
	}
	if ($estado === 200 && !sunqu_es_staging() && in_array($origen, ['wordpress', 'cache', 'local'], true)) {
		header('X-LiteSpeed-Cache-Control: public, max-age=' . max(60, (int) $cfg['CONTENT_CACHE_TTL']));
		header('X-LiteSpeed-Tag: sunqu');
	} else {
		header('X-LiteSpeed-Cache-Control: no-cache');
	}
	if ($cfg['CSP_MODE'] === 'enforce' || $cfg['CSP_MODE'] === 'report') {
		header(($cfg['CSP_MODE'] === 'enforce' ? 'Content-Security-Policy: ' : 'Content-Security-Policy-Report-Only: ') . sunqu_csp($hashes, false, $extra));
		if (str_starts_with($cfg['SITE_URL'], 'https://')) {
			header('Reporting-Endpoints: csp="' . $cfg['SITE_URL'] . '/_sunqu/csp"');
		}
	}
	if (sunqu_es_staging()) {
		header('X-Robots-Tag: noindex, nofollow');
	}
}

function sunqu_es_https(): bool {
	return (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

/** Cabeceras de las respuestas internas (/_sunqu/…): JSON, sin caché y sin indexar. */
function sunqu_cabeceras_internas(int $estado): void {
	http_response_code($estado);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	header('X-LiteSpeed-Cache-Control: no-cache');
	header('X-Robots-Tag: noindex, nofollow');
}

/**
 * ¿Toca registrar este evento? Límite por minuto con un contador en server/storage (evita llenar el log
 * si alguien envía miles de reportes). $tipo: 'csp', 'purga'…
 */
function sunqu_log_permitido(string $tipo, int $maximo = 20): bool {
	$f = sunqu_cache_dir() . '/log-' . preg_replace('/[^a-z]/', '', $tipo) . '.' . gmdate('YmdHi');
	$n = is_file($f) ? (int) file_get_contents($f) : 0;
	if ($n >= $maximo) {
		return false;
	}
	@file_put_contents($f, (string) ($n + 1), LOCK_EX);
	foreach (glob(sunqu_cache_dir() . '/log-' . $tipo . '.*') ?: [] as $viejo) {
		if ($viejo !== $f && filemtime($viejo) < time() - 300) {
			@unlink($viejo);
		}
	}
	return true;
}

/** POST /_sunqu/csp: reportes de violaciones de la CSP (formato report-uri y Reporting API) → log del servidor. */
function sunqu_reporte_csp(): void {
	$cuerpo = (string) file_get_contents('php://input', false, null, 0, 16384);
	$j = json_decode($cuerpo, true);
	$reportes = [];
	if (isset($j['csp-report']) && is_array($j['csp-report'])) {
		$reportes[] = $j['csp-report'];
	} elseif (is_array($j)) {
		foreach ($j as $r) {
			if (is_array($r) && ($r['type'] ?? '') === 'csp-violation' && is_array($r['body'] ?? null)) {
				$reportes[] = $r['body'];
			}
		}
	}
	foreach (array_slice($reportes, 0, 5) as $r) {
		if (!sunqu_log_permitido('csp')) {
			break;
		}
		$campo = fn(string ...$k) => (string) (array_values(array_filter(array_map(fn($x) => $r[$x] ?? null, $k), 'is_scalar'))[0] ?? '');
		$linea = sprintf('[sunqu][csp] %s bloqueado: %s en %s', $campo('effective-directive', 'effectiveDirective', 'violated-directive'),
			$campo('blocked-uri', 'blockedURL'), $campo('document-uri', 'documentURL'));
		error_log(substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', $linea) ?? '', 0, 500));
	}
	http_response_code(204);
	header('Cache-Control: no-store');
}
