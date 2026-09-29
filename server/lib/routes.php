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
 *   ['tipo' => 'sitemap'] · ['tipo' => 'robots'] · ['tipo' => 'purga'] · ['tipo' => 'no-encontrada']
 */
function sunqu_resolver(string $uri): array {
	$crudo = (string) parse_url($uri, PHP_URL_PATH);
	$path = rawurldecode($crudo);
	if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
		return ['tipo' => 'no-encontrada']; // caracteres de control: nunca es una URL de la web
	}
	parse_str((string) parse_url($uri, PHP_URL_QUERY), $q);
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

	$fijas = ['/' => 'home', '/descubre' => 'descubre', '/recuerda' => 'recuerda', '/diario' => 'blog', '/terminos' => 'terminos', '/libro-de-reclamaciones' => 'reclamaciones'];
	if (isset($fijas[$path])) {
		$params = [];
		if ($path === '/descubre' && is_string($q['q'] ?? null)) {
			$params['q'] = $q['q'];
		}
		if ($path === '/diario' && is_string($q['cat'] ?? null)) {
			$params['cat'] = $q['cat'];
		}
		return ['tipo' => 'pagina', 'pagina' => $fijas[$path], 'params' => $params, 'ruta' => $path];
	}
	if ($path === '/journeys') {
		return ['tipo' => 'redireccion', 'destino' => '/descubre'];
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

/** Reescribe los enlaces a páginas .html de un texto (HTML, JS o JSON) por sus URLs públicas. */
function sunqu_url_publica(string $texto): string {
	return strtr($texto, SUNQU_ENLACES);
}
