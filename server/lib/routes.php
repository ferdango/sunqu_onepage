<?php
/**
 * Rutas de producción y redirecciones de las URLs antiguas. Cada página vive en su idioma: /es/…, /en/…
 * (los activos en WordPress › Sunqu › Idiomas). Sin barra final, salvo el inicio de cada idioma (/es/).
 *
 *   /                               elige el idioma (302 a /es/ o /en/, ver server/lib/idiomas.php)
 *   /{idioma}/                      index.html          (home)
 *   /{idioma}/descubre              descubre.html       (?q= busca)
 *   /{idioma}/journeys/{slug}       journey.html        (5 journeys; los nuevos aparecen solos)
 *   /{idioma}/recuerda              recuerda.html
 *   /{idioma}/diario                blog.html           (?cat= filtra)
 *   /{idioma}/diario/{slug}         articulo.html
 *   /{idioma}/mice                  mice.html
 *   /{idioma}/terminos              terminos.html
 *   /{idioma}/libro-de-reclamaciones reclamaciones.html
 *   /sitemap.xml · /robots.txt      generados
 *   /_sunqu/purge (POST, token) · /_sunqu/csp (POST, reportes CSP) · /_sunqu/salud (estado para monitoreo)
 *   /descubre, /journeys/alma…      URLs de antes (eran en español) → 301 a /es/descubre, /es/journeys/alma…
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
	'mice' => ['plantilla' => 'mice.html', 'ruta' => '/mice'],
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
	'mice.html' => '/mice',
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

	// Barras repetidas (//descubre, /es/journeys//alma/) → una sola barra (la barra final se resuelve después)
	if (str_contains($crudo, '//')) {
		$limpio = preg_replace('#/{2,}#', '/', $crudo) ?? '/';
		return ['tipo' => 'redireccion', 'destino' => $limpio . ($q ? '?' . http_build_query($q) : '')];
	}

	// URLs antiguas (.html con parámetros) → 301. El inicio va a la raíz (elige el idioma); el resto era en español
	$base = basename($path);
	$antiguas = [
		'index.html' => '/' . $qs([]), 'Sunqu Journeys v2.dc.html' => '/' . $qs([]), 'descubre.html' => '/es/descubre' . $qs(['q']),
		'recuerda.html' => '/es/recuerda' . $qs([]), 'mice.html' => '/es/mice' . $qs([]), 'terminos.html' => '/es/terminos' . $qs([]), 'reclamaciones.html' => '/es/libro-de-reclamaciones' . $qs([]),
		'blog.html' => '/es/diario' . $qs(['cat']),
		'journey.html' => (is_string($q['j'] ?? null) && $q['j'] !== '' ? '/es/journeys/' . rawurlencode($q['j']) : '/es/descubre') . $qs([]),
		'articulo.html' => (is_string($q['a'] ?? null) && $q['a'] !== '' ? '/es/diario/' . rawurlencode($q['a']) : '/es/diario') . $qs([]),
	];
	if (isset($antiguas[$base]) && dirname($path) === '/') {
		return ['tipo' => 'redireccion', 'destino' => $antiguas[$base]];
	}
	$conQuery = fn(string $ruta) => $ruta . ($q ? '?' . http_build_query($q) : '');

	// Con idioma: /es/…, /en/…
	if (preg_match('#^/([a-z]{2})(/.*)?$#', $path, $mi) && isset(SUNQU_IDIOMAS_WEB[$mi[1]])) {
		$lang = $mi[1];
		$resto = $mi[2] ?? '';
		$crudoResto = substr($crudo, 3);
		if ($resto === '') {
			return ['tipo' => 'redireccion', 'destino' => $conQuery('/' . $lang . '/')]; // /en → /en/
		}
		if ($resto !== '/' && str_ends_with($resto, '/')) {
			return ['tipo' => 'redireccion', 'destino' => $conQuery('/' . $lang . rtrim($crudoResto, '/'))]; // sin barra final
		}
		if (!isset(sunqu_idiomas_web()[$lang])) {
			// Idioma desactivado (o todavía no activado): la misma página en español, sin dar el idioma por perdido
			return ['tipo' => 'redireccion', 'destino' => $conQuery('/' . SUNQU_IDIOMA_BASE . $crudoResto), 'temporal' => true];
		}
		$r = sunqu_resolver_pagina($resto, $q);
		if ($r['tipo'] === 'redireccion') {
			$r['destino'] = sunqu_ruta_idioma($r['destino'], $lang);
		}
		return $r + ['lang' => $lang];
	}

	// Sin idioma: la raíz elige; las demás eran las URLs en español de antes → 301 a /es/…
	if ($path === '/') {
		return ['tipo' => 'raiz', 'query' => $q ? '?' . http_build_query($q) : ''];
	}
	$sinBarra = rtrim($path, '/');
	$r = sunqu_resolver_pagina($sinBarra, $q);
	if ($r['tipo'] === 'pagina') {
		return ['tipo' => 'redireccion', 'destino' => $conQuery('/' . SUNQU_IDIOMA_BASE . rtrim($crudo, '/'))];
	}
	if ($r['tipo'] === 'redireccion') {
		return ['tipo' => 'redireccion', 'destino' => sunqu_ruta_idioma($r['destino'], SUNQU_IDIOMA_BASE)];
	}
	return $r;
}

/** Página de una ruta (ya sin el prefijo de idioma). */
function sunqu_resolver_pagina(string $path, array $q): array {
	$fijas = ['/' => 'home', '/descubre' => 'descubre', '/recuerda' => 'recuerda', '/diario' => 'blog', '/mice' => 'mice', '/terminos' => 'terminos', '/libro-de-reclamaciones' => 'reclamaciones'];
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
		return ['tipo' => 'redireccion', 'destino' => '/descubre']; // en el idioma de la petición (sunqu_resolver)
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
 * página: con $prefijo '/en', «descubre.html» pasa a «/en/descubre» e «index.html#contacto» a «/en/#contacto».
 */
function sunqu_url_publica(string $texto, string $prefijo = ''): string {
	if ($prefijo === '') {
		return strtr($texto, SUNQU_ENLACES);
	}
	return strtr($texto, array_map(fn($a) => $prefijo . $a, SUNQU_ENLACES));
}
