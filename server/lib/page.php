<?php
/**
 * Arma una página de producción a partir de su plantilla (los HTML de la raíz):
 *   1. rutas de assets absolutas (/assets, /css, /js) para que funcionen en /journeys/alma;
 *   2. enlaces .html → URLs limpias;
 *   3. <head>: bloque SEO, precargas y versión de CSS/JS (caché del navegador sin archivos viejos);
 *   4. configuración pública + contenido precargado (la página no espera ninguna petición extra).
 */

declare(strict_types=1);

/** Versión de un archivo estático (cambia cuando cambia el archivo). */
function sunqu_version(string $rel): string {
	$f = SUNQU_ROOT . '/' . ltrim($rel, '/');
	return is_file($f) ? substr(md5((string) filemtime($f) . filesize($f)), 0, 8) : '0';
}

/** Rutas relativas de las plantillas → absolutas desde la raíz del sitio. */
function sunqu_rutas_absolutas(string $html): string {
	// src="assets/…", href="css/…", 'assets/…' (JS), url(assets/…) (estilos en línea)
	$html = preg_replace('#(["\'(])(assets|css|js|fonts|content|vendor)/#', '$1/$2/', $html) ?? $html;
	return str_replace(['src="./support.js"', "'./support.js'"], ['src="/support.js"', "'/support.js'"], $html);
}

/** CSS y JS locales con ?v= para poder cachearlos un año. */
function sunqu_versionar(string $html): string {
	return preg_replace_callback('#(href|src)="(/(?:css|js)/[^"?]+\.(?:css|js))"#', fn($m) => $m[1] . '="' . $m[2] . '?v=' . sunqu_version($m[2]) . '"', $html) ?? $html;
}

/** Reemplaza lo que hay entre dos marcas (sin interpretar el texto nuevo). */
function sunqu_reemplazar_entre(string $html, string $ini, string $fin, string $nuevo): string {
	$a = strpos($html, $ini);
	$b = $a === false ? false : strpos($html, $fin, $a);
	if ($a === false || $b === false) {
		return $html;
	}
	$a += strlen($ini);
	return substr($html, 0, $a) . $nuevo . substr($html, $b);
}

/**
 * Plantilla <x-dc> con la misma codificación que aplica el runtime (support.js › encodeCase):
 * atributos camelCase → sc-camel-*, <helmet> → <sc-helmet> y tablas/select → sc-raw-*.
 * Así el navegador no pierde las mayúsculas al leer el HTML y el runtime no necesita volver a
 * descargar la página para leer la plantilla original (lo hace solo si falta window.__resources).
 * La codificación es idempotente: el runtime la vuelve a aplicar sin cambios.
 */
function sunqu_codificar_plantilla(string $html): string {
	$a = strpos($html, '<x-dc>');
	$b = strrpos($html, '</x-dc>');
	if ($a === false || $b === false || $b < $a) {
		return $html;
	}
	$a += strlen('<x-dc>');
	$t = substr($html, $a, $b - $a);
	$attrs = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';
	$t = preg_replace('#<(x-import|dc-import)(' . $attrs . ')/>#i', '<$1$2></$1>', $t) ?? $t;
	$t = preg_replace('#<helmet(\s|>)#i', '<sc-helmet$1', $t) ?? $t;
	$t = preg_replace('#</helmet\s*>#i', '</sc-helmet>', $t) ?? $t;
	$t = preg_replace_callback('#(\s)([a-z]+[A-Z][A-Za-z0-9]*)(\s*=)#', fn($m) => $m[1] . 'sc-camel-' . strtolower(preg_replace('/([A-Z])/', '-$1', $m[2])) . $m[3], $t) ?? $t;
	foreach (['select', 'table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'caption'] as $tag) {
		$t = preg_replace('#(</?)' . $tag . '(?=[\s>])#i', '$1sc-raw-' . $tag, $t) ?? $t;
	}
	// Atributos con {{ … }} que el navegador intentaría usar al leer la plantilla (oculta): src y poster
	// dispararían descargas de «{{ s.src }}» (404), la geometría SVG y los value/min/max de un <input type="date">,
	// avisos en la consola. Con el prefijo sc-camel- el navegador los ignora y el runtime los devuelve con el mismo nombre.
	$t = preg_replace_callback('#<(?!/|sc-|x-import|dc-import)([a-zA-Z][\w-]*)(' . $attrs . ')>#', function ($m) {
		$at = preg_replace('#(\s)(src|poster|width|height|x|y|x1|x2|y1|y2|cx|cy|r|rx|ry|points|d|transform|value|min|max)(\s*=\s*"[^"]*\{\{)#', '$1sc-camel-$2$3', $m[2]) ?? $m[2];
		return '<' . $m[1] . $at . '>';
	}, $t) ?? $t;
	return substr($html, 0, $a) . $t . substr($html, $b);
}

/**
 * JSON seguro para incrustar dentro de <script> (JSON_HEX_TAG impide «</script>»). Las barras van sin escapar
 * para que sunqu_rutas_absolutas() y sunqu_url_publica() también corrijan las rutas y enlaces del contenido.
 */
function sunqu_json_script($v): string {
	return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

/** Número de WhatsApp: el del CMS (Sunqu › Sitio) y, si falta, WHATSAPP_NUMBER. */
function sunqu_whatsapp(array $c): string {
	$n = preg_replace('/\D+/', '', (string) ($c['sitio']['contacto']['whatsapp'] ?? ''));
	return $n ?: preg_replace('/\D+/', '', sunqu_config()['WHATSAPP_NUMBER']);
}

/**
 * Configuración pública que recibe el navegador (nada secreto: los IDs de medición son públicos).
 * $consentimiento: nombres de los scripts del panel que esperan permiso ['analitica' => [...], 'marketing' => [...]].
 */
function sunqu_config_publica(array $c, array $consentimiento = []): array {
	$cfg = sunqu_config();
	return [
		'env' => $cfg['APP_ENV'],
		'siteUrl' => $cfg['SITE_URL'],
		'api' => $cfg['WORDPRESS_API_URL'],
		'ga4' => $cfg['GA4_ID'],
		'gtm' => $cfg['GTM_ID'],
		'pixel' => $cfg['META_PIXEL_ID'],
		'whatsapp' => sunqu_whatsapp($c),
		'consent' => $cfg['CONSENT_MODE'],
		'turnstile' => $cfg['TURNSTILE_SITE_KEY'], // clave pública (la secreta está solo en WordPress)
		'scripts' => (object) array_filter($consentimiento), // js/sunqu-analytics.js: aviso de cookies y activación
		'lang' => sunqu_lang(),
		'rutas' => 'limpias',
		'v' => sunqu_version('support.js'),
		'vendor' => sunqu_version('vendor/VERSIONS.json'), // d3 y el mapa (?v= para la caché de un año)
	];
}

/** React servido desde el propio sitio (support.js lo busca en window.__resources). */
function sunqu_recursos(): array {
	$r = [];
	foreach (['react' => 'react.production.min.js', 'react-dom' => 'react-dom.production.min.js'] as $pkg => $file) {
		if (is_file(SUNQU_ROOT . "/vendor/$file")) {
			$r["https://unpkg.com/$pkg@18.3.1/umd/$file"] = "/vendor/$file?v=" . sunqu_version("vendor/$file");
		}
	}
	return $r;
}

/** Scripts en línea ejecutables de un HTML: [contenido, …] (sin los que tienen src ni los de datos). */
function sunqu_scripts_en_linea(string $html): array {
	$out = [];
	if (preg_match_all('#<script(\s[^>]*)?>(.*?)</script>#is', $html, $m, PREG_SET_ORDER)) {
		foreach ($m as $s) {
			$attrs = $s[1] ?? '';
			if (preg_match('/\ssrc\s*=/i', $attrs)) {
				continue;
			}
			if (preg_match('/\stype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $t) && !in_array(strtolower($t[1]), ['text/javascript', 'application/javascript', 'module'], true)) {
				continue; // datos (JSON-LD, plantilla x-dc): el navegador no los ejecuta
			}
			if ($s[2] !== '') {
				$out[] = $s[2];
			}
		}
	}
	return $out;
}

/**
 * Hashes CSP de los scripts en línea de la página. Solo se autorizan los que vienen de la plantilla (archivo del
 * repositorio, con las mismas transformaciones que el resto del HTML) o los que arma el servidor ($propios): un
 * <script> que llegara por el contenido no coincidiría con ninguno y el navegador no lo ejecutaría.
 */
function sunqu_hashes_en_linea(string $html, string $plantilla, array $propios, string $prefijo = ''): array {
	foreach (sunqu_scripts_en_linea($plantilla) as $s) {
		$propios[] = sunqu_url_publica(sunqu_rutas_absolutas($s), $prefijo);
	}
	$h = [];
	foreach (sunqu_scripts_en_linea($html) as $s) {
		if (in_array($s, $propios, true)) {
			$h[] = sunqu_csp_hash($s);
		}
	}
	return array_values(array_unique($h));
}

/** Inserta $nuevo justo antes de la primera $marca (o de la última, con $ultima). */
function sunqu_insertar_antes(string $html, string $marca, string $nuevo, bool $ultima = false): string {
	$p = $ultima ? strripos($html, $marca) : stripos($html, $marca);
	return $p === false || $nuevo === '' ? $html : substr_replace($html, $nuevo, $p, 0);
}

/**
 * Página completa en el idioma actual (sunqu_lang). $seo sale de sunqu_seo(); $params son los parámetros de la
 * ruta (j, a, q, cat). Devuelve ['html' => …, 'hashes' => […], 'dominios' => […]] (para la Content-Security-Policy).
 */
function sunqu_render(string $pagina, array $params, array $seo, bool $noEncontrada = false): array {
	$archivo = SUNQU_ROOT . '/' . SUNQU_PAGINAS[$pagina]['plantilla'];
	$original = (string) file_get_contents($archivo);
	$lang = sunqu_lang();
	$prefijo = sunqu_prefijo();
	$c = sunqu_contenido($lang)['datos'];

	$html = sunqu_url_publica(sunqu_rutas_absolutas($original), $prefijo);
	$html = sunqu_versionar($html);
	// Otro idioma: textos fijos de la plantilla y de su código traducidos (Loco Translate) y <html lang>
	$html = sunqu_traducir_html($html, sunqu_dic());
	if ($lang !== SUNQU_IDIOMA_BASE) {
		$html = preg_replace('#<html lang="es"#', '<html lang="' . $lang . '"', $html, 1) ?? $html;
	}

	// Bloque SEO del <head> (en las plantillas trae noindex para la vista previa estática)
	$estilos = '<style>x-dc{display:none!important}</style>' // la plantilla cruda nunca se muestra (scripts con defer)
		. (sunqu_selector_idiomas() ? '' : '<style>.sj-lang,.sj-seg{display:none!important}</style>'); // sin otros idiomas, sin selector
	$head = sunqu_head($seo, $c, $noEncontrada) . "\n\t\t" . sunqu_precargas($pagina, $params, $c) . "\n\t\t" . $estilos . sunqu_sin_js_estilos();
	$html = sunqu_reemplazar_entre($html, '<!-- sunqu:seo -->', '<!-- /sunqu:seo -->', "\n\t\t" . $head . "\n\t\t");
	// El <title> de la plantilla (dentro de <helmet>) queda igual al del SEO
	if (preg_match('#<helmet>\s*<title>[^<]*</title>#', $html, $m, PREG_OFFSET_CAPTURE)) {
		$nuevo = preg_replace('#<title>[^<]*</title>#', '', $m[0][0]) . '<title>' . sunqu_e($seo['titulo']) . '</title>';
		$html = substr_replace($html, $nuevo, $m[0][1], strlen($m[0][0]));
	}
	$html = sunqu_codificar_plantilla($html); // después del título: convierte <helmet> en <sc-helmet>

	// Scripts de WordPress › Sunqu › Scripts y seguimiento (GTM, Analytics…)
	$scripts = sunqu_scripts_pagina($c);

	// Configuración + contenido precargado, antes de js/sunqu-config.js
	$datos = sunqu_contenido_pagina($pagina, $params);
	$codigo = sunqu_consentimiento_js()
		. 'window.SUNQU_CONFIG=' . sunqu_json_script(sunqu_config_publica($c, $scripts['consentimiento'])) . ';'
		. 'window.SUNQU_I18N=' . sunqu_json_script(sunqu_i18n_publico((string) ($seo['ruta_base'] ?? '/'))) . ';'
		. 'window.SUNQU_PARAMS=' . sunqu_json_script((object) $params) . ';'
		. 'window.SUNQU_SEO=' . sunqu_json_script(['title' => $seo['titulo'], 'journey' => $seo['journey'] ?? null]) . ';'
		. 'window.__resources=Object.assign(window.__resources||{},' . sunqu_json_script((object) sunqu_recursos()) . ');'
		. 'window.SUNQU_PRELOADED=' . sunqu_url_publica(sunqu_rutas_absolutas(sunqu_json_script($datos)), $prefijo) . ';';
	$pos = strpos($html, '<script src="/js/sunqu-config.js');
	if ($pos !== false) {
		$html = substr_replace($html, '<script>' . $codigo . "</script>\n\t\t", $pos, 0);
	}
	// Configuración y capa de contenido con defer: no bloquean el primer pintado (el orden se mantiene)
	$html = preg_replace('#<script src="(/js/sunqu-(?:config|content)\.js[^"]*)"([^>]*)></script>#', '<script src="$1"$2 defer></script>', $html) ?? $html;

	// Al empezar el <body>: los scripts del panel para ese lugar y el contenido sin JavaScript
	$inicio = $scripts['body_inicio'] . "\n\t\t" . sunqu_sin_js($pagina, $params, $c, $seo, $html); // $html: con el <h1> ya traducido
	$html = preg_replace('#<body([^>]*)>#', '<body$1>' . "\n\t\t" . str_replace(['\\', '$'], ['\\\\', '\\$'], $inicio), $html, 1) ?? $html;
	$html = sunqu_insertar_antes($html, '</head>', $scripts['head']);
	$html = sunqu_insertar_antes($html, '</body>', $scripts['body_fin'], true);

	$hashes = array_values(array_unique(array_merge(sunqu_hashes_en_linea($html, $original, [$codigo], $prefijo), $scripts['hashes'])));
	// CSP también como <meta>, antes de cualquier script (ver sunqu_csp_meta)
	$html = preg_replace('#<!-- sunqu:seo -->#', '<!-- sunqu:seo -->' . str_replace(['\\', '$'], ['\\\\', '\\$'], sunqu_csp_meta($hashes, $scripts['dominios'])), $html, 1) ?? $html;
	return ['html' => $html, 'hashes' => $hashes, 'dominios' => $scripts['dominios']];
}

/**
 * Precargas del <head>: la cadena de scripts que dibuja la página (support.js → React), las fuentes que se
 * ven primero y la imagen principal (LCP). Así se descargan en paralelo con el CSS en lugar de una tras otra.
 */
function sunqu_precargas(string $pagina, array $params, array $c): string {
	$h = [];
	$h[] = '<link rel="preload" href="/support.js?v=' . sunqu_version('support.js') . '" as="script">';
	foreach (sunqu_recursos() as $local) {
		$h[] = '<link rel="preload" href="' . sunqu_e($local) . '" as="script">';
	}
	foreach (['Aeonik-Regular', 'Aeonik-Medium', 'Aeonik-Bold'] as $f) {
		if (is_file(SUNQU_ROOT . "/fonts/$f.woff2")) {
			$h[] = '<link rel="preload" href="/fonts/' . $f . '.woff2" as="font" type="font/woff2" crossorigin>';
		}
	}
	// Las fotos de Unsplash (journeys, anfitriones, Diario): conexión anticipada en las páginas que las muestran
	if (!in_array($pagina, ['terminos', 'reclamaciones'], true)) {
		$h[] = '<link rel="preconnect" href="https://images.unsplash.com">';
	}
	// Mismos anchos y «sizes» que el <img> de cada plantilla, para que el navegador reutilice la precarga
	$hero = '';
	$sizes = '100vw';
	$q = 72;
	if ($pagina === 'home') {
		$hero = (string) ($c['inicio']['heroSlides'][0]['img'] ?? '');
		$w = [640, 960, 1280, 1600, 2000];
	} elseif ($pagina === 'journey') {
		$j = sunqu_buscar($c['journeys']['items'] ?? [], 'slug', $params['j'] ?? '');
		$hero = (string) ($j['ficha']['slides'][0]['img'] ?? '');
		$w = [640, 960, 1280, 1600, 2200];
	} elseif ($pagina === 'articulo') {
		$p = sunqu_buscar($c['diario']['posts'] ?? [], 'slug', $params['a'] ?? '');
		$hero = (string) ($p['img'] ?? '');
		$w = [640, 960, 1280, 1600, 2200];
		$sizes = '(max-width: 1440px) 92vw, 1340px';
	} elseif ($pagina === 'blog') {
		// Primera tarjeta del Diario (la más reciente, o la primera de la categoría pedida)
		$posts = array_values(array_filter($c['diario']['posts'] ?? [], fn($p) => !isset($params['cat']) || ($p['cat'] ?? '') === $params['cat']));
		usort($posts, fn($a, $b) => strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? '')));
		$hero = (string) ($posts[0]['img'] ?? '');
		$w = [480, 720, 1000];
		$sizes = '(max-width: 767px) 90vw, 420px';
	}
	if ($hero !== '') {
		if (preg_match('#^https?://#', $hero)) {
			$set = $c['_imagenes'][$hero] ?? '';
			foreach (SUNQU_SECCIONES as $s) {
				$set = $set ?: ($c[$s]['_imagenes'][$hero] ?? '');
			}
			$h[] = '<link rel="preload" as="image" href="' . sunqu_e($hero) . '"' . ($set ? ' imagesrcset="' . sunqu_e($set) . '" imagesizes="' . $sizes . '"' : '') . ' fetchpriority="high">';
		} elseif (preg_match('#^[A-Za-z0-9_-]+$#', $hero)) { // id de Unsplash (una ruta local o data: no se precarga)
			$u = fn($x) => 'https://images.unsplash.com/photo-' . rawurlencode($hero) . "?auto=format&fit=crop&w=$x&q=$q";
			$set = implode(', ', array_map(fn($x) => $u($x) . " {$x}w", $w));
			$h[] = '<link rel="preload" as="image" href="' . sunqu_e($u(end($w))) . '" imagesrcset="' . sunqu_e($set) . '" imagesizes="' . $sizes . '" fetchpriority="high">';
		}
	}
	return implode("\n\t\t", $h);
}
