<?php
/**
 * Rutas de producción (URLs limpias, sin barra final) y redirecciones 301 de las URLs antiguas.
 *
 *   /                          index.html          (home)
 *   /descubre                  descubre.html       (?q= busca)
 *   /journeys/{slug}           journey.html        (5 journeys; los nuevos aparecen solos)
 *   /recuerda                  recuerda.html
 *   /diario                    blog.html           (?cat= filtra)
 *   /diario/{slug}             articulo.html
 *   /terminos                  terminos.html
 *   /libro-de-reclamaciones    reclamaciones.html
 *   /sitemap.xml · /robots.txt generados
 *   /_sunqu/purge (POST, token) · /_sunqu/csp (POST, reportes CSP) · /_sunqu/salud (estado para monitoreo)
 *   /en/…, /pt/…               las mismas páginas en otro idioma, si está activo en WordPress (server/lib/idiomas.php)
 *
 * Las plantillas siguen siendo los HTML de la raíz: en local (y en GitHub Pages) funcionan tal cual
 * con sus enlaces .html; en producción el servidor reescribe esos enlaces con sunqu_url_publica().
 */

declare(strict_types=1);

const SUNQU_PAGINAS = [
	'home' => ['plantilla' => 'index.html', 'ruta' => '/'],
	'descubre' => ['plantilla' => 'descubre.html', 'ruta' => '/descubre'],
	'journey' => ['plantilla' => 'journey.html', 'ruta' => '/journeys/{slug}'],
	'recuerda' => ['plantilla' => 'recuerda.html', 'ruta' => '/recuerda'],
	'blog' => ['plantilla' => 'blog.html', 'ruta' => '/diario'],
	'articulo' => ['plantilla' => 'articulo.html', 'ruta' => '/diario/{slug}'],
	'terminos' => ['plantilla' => 'terminos.html', 'ruta' => '/terminos'],
	'reclamaciones' => ['plantilla' => 'reclamaciones.html', 'ruta' => '/libro-de-reclamaciones'],
];

/** Enlaces de las plantillas → URL pública. El orden importa (lo más específico primero). */
const SUNQU_ENLACES = [
	'journey.html?j=' => '/journeys/',
	'articulo.html?a=' => '/diario/',
	'blog.html?cat=' => '/diario?cat=',
	'blog.html' => '/diario',
	'descubre.html' => '/descubre',
	'recuerda.html' => '/recuerda',
	'terminos.html' => '/terminos',
	'reclamaciones.html' => '/libro-de-reclamaciones',
	'index.html#' => '/#',
	'index.html' => '/',
];

/**
 * Resuelve la petición. Devuelve una de:
 *   ['tipo' => 'pagina', 'pagina' => 'journey', 'params' => ['j' => 'alma'], 'ruta' => '/journeys/alma']
 *   ['tipo' => 'redireccion', 'destino' => '/journeys/alma']
 *   ['tipo' => 'sitemap'] · ['tipo' => 'robots'] · ['tipo' => 'purga'] · ['tipo' => 'csp'] · ['tipo' => 'salud']
 *   ['tipo' => 'no-encontrada']
 */
function sunqu_resolver(string $uri): array {
	// REQUEST_URI siempre es una ruta: no se usa parse_url(), que leería «//otro.sitio/» como un dominio
	[$crudo, $query] = array_pad(explode('?', $uri, 2), 2, '');
	$crudo = explode('#', $crudo, 2)[0];
	if ($crudo === '' || $crudo[0] !== '/' || strlen($uri) > 2048) {
		return ['tipo' => 'no-encontrada'];
	}
	$path = rawurldecode($crudo);
	if (preg_match('/[\x00-\x1F\x7F]/', $path) || !mb_check_encoding($path, 'UTF-8')) {
		return ['tipo' => 'no-encontrada']; // caracteres de control o bytes inválidos: nunca es una URL de la web
	}
	parse_str($query, $q);
	// Parámetros que se conservan en las redirecciones: los de la página y los de campañas (utm_*, gclid, fbclid…)
	$campana = array_filter($q, fn($v, $k) => is_string($v) && preg_match('/^(utm_[a-z_]+|gclid|gbraid|wbraid|fbclid|msclkid)$/', (string) $k), ARRAY_FILTER_USE_BOTH);
	$qs = function (array $keep) use ($q, $campana) {
		$x = http_build_query(array_filter(array_intersect_key($q, array_flip($keep)), 'is_string') + $campana);
		return $x !== '' ? '?' . $x : '';
	};

	// Archivos generados
	if ($path === '/sitemap.xml') {
		return ['tipo' => 'sitemap'];
	}
	if ($path === '/robots.txt') {
		return ['tipo' => 'robots'];
	}
	if ($path === '/_sunqu/purge') {
		return ['tipo' => 'purga'];
	}
	if ($path === '/_sunqu/csp') {
		return ['tipo' => 'csp'];
	}
	if ($path === '/_sunqu/salud') {
		return ['tipo' => 'salud'];
	}

	// Barras repetidas (//descubre, /journeys//alma/) → la misma URL con una sola barra y sin barra final
	if (str_contains($crudo, '//')) {
		$limpio = rtrim(preg_replace('#/{2,}#', '/', $crudo) ?? '', '/');
		return ['tipo' => 'redireccion', 'destino' => ($limpio === '' ? '/' : $limpio) . ($q ? '?' . http_build_query($q) : '')];
	}

	// URLs antiguas (.html con parámetros) → 301
	$base = basename($path);
	$antiguas = [
		'index.html' => '/' . $qs([]), 'Sunqu Journeys v2.dc.html' => '/' . $qs([]), 'descubre.html' => '/descubre' . $qs(['q']),
		'recuerda.html' => '/recuerda' . $qs([]), 'terminos.html' => '/terminos' . $qs([]), 'reclamaciones.html' => '/libro-de-reclamaciones' . $qs([]),
		'blog.html' => '/diario' . $qs(['cat']),
		'journey.html' => (is_string($q['j'] ?? null) && $q['j'] !== '' ? '/journeys/' . rawurlencode($q['j']) : '/descubre') . $qs([]),
		'articulo.html' => (is_string($q['a'] ?? null) && $q['a'] !== '' ? '/diario/' . rawurlencode($q['a']) : '/diario') . $qs([]),
	];
	if (isset($antiguas[$base]) && dirname($path) === '/') {
		return ['tipo' => 'redireccion', 'destino' => $antiguas[$base]];
	}

	// Barra final → sin barra (salvo la raíz). Se usa la ruta tal como llegó (codificada): %3F sigue siendo %3F
	if ($path !== '/' && str_ends_with($path, '/')) {
		$limpio = rtrim($crudo, '/');
		return ['tipo' => 'redireccion', 'destino' => ($limpio === '' ? '/' : $limpio) . ($q ? '?' . http_build_query($q) : '')];
	}

	// Idioma: /en/…, /pt/… si está activo en WordPress (Sunqu › Idiomas); /es/… → la misma URL sin prefijo
	$lang = SUNQU_IDIOMA_BASE;
	if (preg_match('#^/([a-z]{2})(/.*)?$#', $path, $mi) && isset(SUNQU_IDIOMAS_WEB[$mi[1]])) {
		if ($mi[1] === SUNQU_IDIOMA_BASE) {
			return ['tipo' => 'redireccion', 'destino' => (substr($crudo, 3) ?: '/') . ($q ? '?' . http_build_query($q) : '')];
		}
		if (!isset(sunqu_idiomas_web()[$mi[1]])) {
			return ['tipo' => 'no-encontrada'];
		}
		$lang = $mi[1];
		$path = ($mi[2] ?? '') ?: '/';
	}
	$r = sunqu_resolver_pagina($path, $q);
	return $r + ['lang' => $lang];
}

/** Página de una ruta sin prefijo de idioma. */
function sunqu_resolver_pagina(string $path, array $q): array {
	$fijas = ['/' => 'home', '/descubre' => 'descubre', '/recuerda' => 'recuerda', '/diario' => 'blog', '/terminos' => 'terminos', '/libro-de-reclamaciones' => 'reclamaciones'];
	if (isset($fijas[$path])) {
		$params = [];
		// Búsqueda: texto corto, sin caracteres de control (la página la muestra como texto)
		if ($path === '/descubre' && is_string($q['q'] ?? null) && trim($q['q']) !== '') {
			$params['q'] = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $q['q']) ?? ''), 0, 100);
		}
		// Categoría del Diario: solo un slug
		if ($path === '/diario' && is_string($q['cat'] ?? null) && preg_match('/^[a-z0-9-]{1,40}$/', $q['cat'])) {
			$params['cat'] = $q['cat'];
		}
		return ['tipo' => 'pagina', 'pagina' => $fijas[$path], 'params' => $params, 'ruta' => $path];
	}
	if ($path === '/journeys') {
		return ['tipo' => 'redireccion', 'destino' => '/descubre', 'conIdioma' => true];
	}
	if (preg_match('#^/journeys/([a-z0-9-]+)$#', $path, $m)) {
		return ['tipo' => 'pagina', 'pagina' => 'journey', 'params' => ['j' => $m[1]], 'ruta' => $path];
	}
	if (preg_match('#^/diario/([a-z0-9-]+)$#', $path, $m)) {
		return ['tipo' => 'pagina', 'pagina' => 'articulo', 'params' => ['a' => $m[1]], 'ruta' => $path];
	}
	return ['tipo' => 'no-encontrada'];
}

/** Ruta pública de una página (con su slug). */
function sunqu_ruta(string $pagina, string $slug = ''): string {
	return str_replace('{slug}', $slug, SUNQU_PAGINAS[$pagina]['ruta']);
}

/**
 * Reescribe los enlaces a páginas .html de un texto (HTML, JS o JSON) por sus URLs públicas, en el idioma de la
 * página: con $prefijo '/en', «descubre.html» pasa a «/en/descubre» e «index.html#contacto» a «/en#contacto».
 */
function sunqu_url_publica(string $texto, string $prefijo = ''): string {
	if ($prefijo === '') {
		return strtr($texto, SUNQU_ENLACES);
	}
	$mapa = [];
	foreach (SUNQU_ENLACES as $de => $a) {
		$mapa[$de] = $a === '/' ? $prefijo : (str_starts_with($a, '/#') ? $prefijo . substr($a, 1) : $prefijo . $a);
	}
	return strtr($texto, $mapa);
}
