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
	// dispararían descargas de «{{ s.src }}» (404) y la geometría SVG, errores en la consola. Con el
	// prefijo sc-camel- el navegador los ignora y el runtime los devuelve con el mismo nombre.
	$t = preg_replace_callback('#<(?!/|sc-|x-import|dc-import)([a-zA-Z][\w-]*)(' . $attrs . ')>#', function ($m) {
		$at = preg_replace('#(\s)(src|poster|width|height|x|y|x1|x2|y1|y2|cx|cy|r|rx|ry|points|d|transform)(\s*=\s*"[^"]*\{\{)#', '$1sc-camel-$2$3', $m[2]) ?? $m[2];
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

/** Configuración pública que recibe el navegador (nada secreto: los IDs de medición son públicos). */
function sunqu_config_publica(array $c): array {
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

/**
 * Página completa. $seo sale de sunqu_seo(); $params son los parámetros de la ruta (j, a, q, cat).
 */
function sunqu_render(string $pagina, array $params, array $seo, bool $noEncontrada = false): string {
	$plantilla = SUNQU_ROOT . '/' . SUNQU_PAGINAS[$pagina]['plantilla'];
	$html = (string) file_get_contents($plantilla);
	$c = sunqu_contenido()['datos'];

	$html = sunqu_url_publica(sunqu_rutas_absolutas($html));
	$html = sunqu_versionar($html);

	// Bloque SEO del <head> (en las plantillas trae noindex para la vista previa estática)
	$head = sunqu_head($seo, $c, $noEncontrada) . "\n\t\t" . sunqu_precargas($pagina, $params, $c);
	$html = sunqu_reemplazar_entre($html, '<!-- sunqu:seo -->', '<!-- /sunqu:seo -->', "\n\t\t" . $head . "\n\t\t");
	// El <title> de la plantilla (dentro de <helmet>) queda igual al del SEO
	if (preg_match('#<helmet>\s*<title>[^<]*</title>#', $html, $m, PREG_OFFSET_CAPTURE)) {
		$nuevo = preg_replace('#<title>[^<]*</title>#', '', $m[0][0]) . '<title>' . sunqu_e($seo['titulo']) . '</title>';
		$html = substr_replace($html, $nuevo, $m[0][1], strlen($m[0][0]));
	}
	$html = sunqu_codificar_plantilla($html); // después del título: convierte <helmet> en <sc-helmet>

	// Configuración + contenido precargado, antes de js/sunqu-config.js
	$datos = sunqu_contenido_pagina($pagina, $params);
	$boot = '<script>'
		. 'window.SUNQU_CONFIG=' . sunqu_json_script(sunqu_config_publica($c)) . ';'
		. 'window.SUNQU_PARAMS=' . sunqu_json_script((object) $params) . ';'
		. 'window.SUNQU_SEO=' . sunqu_json_script(['title' => $seo['titulo'], 'journey' => $seo['journey'] ?? null]) . ';'
		. 'window.__resources=Object.assign(window.__resources||{},' . sunqu_json_script((object) sunqu_recursos()) . ');'
		. 'window.SUNQU_PRELOADED=' . sunqu_url_publica(sunqu_rutas_absolutas(sunqu_json_script($datos))) . ';'
		. '</script>';
	$pos = strpos($html, '<script src="/js/sunqu-config.js');
	if ($pos !== false) {
		$html = substr_replace($html, $boot . "\n\t\t", $pos, 0);
	}
	return $html;
}

/** Precarga de la imagen del hero (LCP) y de las fuentes que se ven primero. */
function sunqu_precargas(string $pagina, array $params, array $c): string {
	$h = [];
	foreach (['Aeonik-Regular', 'Aeonik-Bold'] as $f) {
		if (is_file(SUNQU_ROOT . "/fonts/$f.woff2")) {
			$h[] = '<link rel="preload" href="/fonts/' . $f . '.woff2" as="font" type="font/woff2" crossorigin>';
		}
	}
	// Mismos anchos y «sizes» que el <img> de cada plantilla, para que el navegador reutilice la precarga
	$hero = '';
	$sizes = '100vw';
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
	}
	if ($hero !== '') {
		if (preg_match('#^https?://#', $hero)) {
			$set = $c['_imagenes'][$hero] ?? '';
			foreach (SUNQU_SECCIONES as $s) {
				$set = $set ?: ($c[$s]['_imagenes'][$hero] ?? '');
			}
			$h[] = '<link rel="preload" as="image" href="' . sunqu_e($hero) . '"' . ($set ? ' imagesrcset="' . sunqu_e($set) . '" imagesizes="' . $sizes . '"' : '') . ' fetchpriority="high">';
		} elseif (preg_match('#^[A-Za-z0-9_-]+$#', $hero)) { // id de Unsplash (una ruta local o data: no se precarga)
			$u = fn($x) => 'https://images.unsplash.com/photo-' . rawurlencode($hero) . "?auto=format&fit=crop&w=$x&q=72";
			$set = implode(', ', array_map(fn($x) => $u($x) . " {$x}w", $w));
			$h[] = '<link rel="preconnect" href="https://images.unsplash.com">';
			$h[] = '<link rel="preload" as="image" href="' . sunqu_e($u(end($w))) . '" imagesrcset="' . sunqu_e($set) . '" imagesizes="' . $sizes . '" fetchpriority="high">';
		}
	}
	return implode("\n\t\t", $h);
}
