<?php
/**
 * /sitemap.xml y /robots.txt, generados desde el contenido: un journey o artículo nuevo publicado en
 * WordPress aparece solo (al vencer la caché o al publicar, si el plugin tiene el aviso configurado).
 *
 * Cada URL lleva su imagen principal (<image:image>, para Google Imágenes) y, cuando se conoce, la fecha de su
 * última edición (<lastmod>): la de WordPress si el CMS la envía («_modificado»), o la del artículo.
 * Con más de un idioma con «Publicar en buscadores» (Sunqu › Idiomas), cada página aparece en cada idioma con sus
 * alternativas (<xhtml:link hreflang>), igual que en el <head>.
 */

declare(strict_types=1);

/** Fecha W3C válida para <lastmod> (AAAA-MM-DD o fecha y hora ISO 8601); vacío si no lo es. */
function sunqu_lastmod(string $f): string {
	return preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z))?$/', $f) ? $f : '';
}

function sunqu_sitemap(): string {
	$c = sunqu_contenido()['datos'];
	$urls = [];
	$add = function (string $ruta, string $lastmod = '', string $img = '') use (&$urls) {
		$urls[] = ['ruta' => $ruta, 'lastmod' => sunqu_lastmod($lastmod), 'img' => $img !== '' ? sunqu_img_abs($img) : ''];
	};
	$add('/', '', (string) ($c['inicio']['heroSlides'][0]['img'] ?? ''));
	$add('/descubre');
	$items = $c['journeys']['items'] ?? [];
	usort($items, fn($a, $b) => ($a['catalogo']['orden'] ?? 99) <=> ($b['catalogo']['orden'] ?? 99));
	foreach ($items as $j) {
		if (!empty($j['slug']) && is_string($j['slug'])) {
			$add(sunqu_ruta('journey', $j['slug']), (string) ($j['_modificado'] ?? ''), (string) ($j['ficha']['slides'][0]['img'] ?? $j['ficha']['card'] ?? ''));
		}
	}
	$add('/recuerda');
	$posts = $c['diario']['posts'] ?? [];
	$ultimo = '';
	foreach ($posts as $p) {
		$ultimo = max($ultimo, sunqu_lastmod((string) ($p['_modificado'] ?? '')) ?: (string) ($p['date'] ?? ''));
	}
	$add('/diario', $ultimo);
	foreach ($posts as $p) {
		if (!empty($p['slug']) && is_string($p['slug'])) {
			$add(sunqu_ruta('articulo', $p['slug']), sunqu_lastmod((string) ($p['_modificado'] ?? '')) ?: (string) ($p['date'] ?? ''), (string) ($p['img'] ?? ''));
		}
	}
	$add('/terminos');
	$add('/libro-de-reclamaciones');

	$idiomas = sunqu_idiomas_indexables();
	$varios = count($idiomas) > 1;
	$x = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' . ($varios ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '') . '>'];
	foreach ($urls as $u) {
		$alternos = '';
		if ($varios) {
			foreach ($idiomas as $l) {
				$alternos .= '<xhtml:link rel="alternate" hreflang="' . $l . '" href="' . htmlspecialchars(sunqu_url_idioma($u['ruta'], $l), ENT_XML1) . '"/>';
			}
			$alternos .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars(sunqu_url_idioma($u['ruta'], SUNQU_IDIOMA_BASE), ENT_XML1) . '"/>';
		}
		foreach ($idiomas as $l) {
			$x[] = '  <url><loc>' . htmlspecialchars(sunqu_url_idioma($u['ruta'], $l), ENT_XML1) . '</loc>'
				. ($u['lastmod'] !== '' ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '')
				. $alternos
				. ($u['img'] !== '' ? '<image:image><image:loc>' . htmlspecialchars($u['img'], ENT_XML1) . '</image:loc></image:image>' : '')
				. '</url>';
		}
	}
	$x[] = '</urlset>';
	return implode("\n", $x) . "\n";
}

function sunqu_robots(): string {
	$site = sunqu_config()['SITE_URL'];
	if (sunqu_es_staging()) {
		return "# Entorno de pruebas: no indexar\nUser-agent: *\nDisallow: /\n";
	}
	return implode("\n", [
		'User-agent: *',
		'Allow: /',
		'Disallow: /server/',
		'Disallow: /wordpress/',
		'Disallow: /docs/',
		'Disallow: /_sunqu/',
		'',
		'Sitemap: ' . $site . '/sitemap.xml',
		'',
	]);
}
