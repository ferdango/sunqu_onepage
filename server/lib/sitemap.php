<?php
/**
 * /sitemap.xml y /robots.txt, generados desde el contenido: un journey o artículo nuevo publicado en
 * WordPress aparece solo (al vencer la caché o al publicar, si el plugin tiene el aviso configurado).
 */

declare(strict_types=1);

function sunqu_sitemap(): string {
	$c = sunqu_contenido()['datos'];
	$site = sunqu_config()['SITE_URL'];
	$urls = [];
	$add = function (string $ruta, string $lastmod = '') use (&$urls, $site) {
		$urls[] = ['loc' => $site . $ruta, 'lastmod' => $lastmod];
	};
	$add('/');
	$add('/descubre');
	$items = $c['journeys']['items'] ?? [];
	usort($items, fn($a, $b) => ($a['catalogo']['orden'] ?? 99) <=> ($b['catalogo']['orden'] ?? 99));
	foreach ($items as $j) {
		if (!empty($j['slug'])) {
			$add(sunqu_ruta('journey', $j['slug']));
		}
	}
	$add('/recuerda');
	$posts = $c['diario']['posts'] ?? [];
	$ultimo = '';
	foreach ($posts as $p) {
		$ultimo = max($ultimo, (string) ($p['date'] ?? ''));
	}
	$add('/diario', $ultimo);
	foreach ($posts as $p) {
		if (!empty($p['slug'])) {
			$add(sunqu_ruta('articulo', $p['slug']), (string) ($p['date'] ?? ''));
		}
	}
	$add('/terminos');
	$add('/libro-de-reclamaciones');

	$x = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
	foreach ($urls as $u) {
		$x[] = '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1) . '</loc>' . ($u['lastmod'] && preg_match('/^\d{4}-\d{2}-\d{2}$/', $u['lastmod']) ? '<lastmod>' . $u['lastmod'] . '</lastmod>' : '') . '</url>';
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
