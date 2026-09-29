<?php
/**
 * SEO técnico por URL: <title>, meta description, canonical, robots, Open Graph, Twitter Cards y
 * datos estructurados (JSON-LD). Todo sale del contenido real (content/*.json o WordPress):
 *   páginas fijas  → sitio.seo.paginas.{pagina} (Sunqu › Sitio › SEO)
 *   journeys       → título + ficha.what, foto del hero, ruta y precio del catálogo
 *   artículos      → título + extracto, imagen, fecha, autor y categoría
 */

declare(strict_types=1);

function sunqu_e(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** URL absoluta de una imagen del contenido (URL completa, ruta local o id de Unsplash). */
function sunqu_img_abs(string $v, int $w = 1200, int $h = 630): string {
	if ($v === '') {
		return '';
	}
	if (preg_match('#^https?://#', $v)) {
		return $v;
	}
	if (str_starts_with($v, '/') || str_starts_with($v, 'assets/')) {
		return sunqu_config()['SITE_URL'] . '/' . ltrim($v, '/');
	}
	return 'https://images.unsplash.com/photo-' . rawurlencode($v) . "?auto=format&fit=crop&w=$w&h=$h&q=80";
}

/** Recorta un texto a ~$max caracteres sin partir palabras. */
function sunqu_recortar(string $t, int $max = 200): string {
	$t = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
	if (mb_strlen($t) <= $max) {
		return $t;
	}
	$corte = mb_substr($t, 0, $max);
	return rtrim(preg_replace('/\s+\S*$/u', '', $corte) ?? $corte, ' ,;:') . '…';
}

/** Datos SEO de la página actual. Devuelve null si el slug no existe (→ 404). */
function sunqu_seo(string $pagina, array $params, array $c): ?array {
	$cfg = sunqu_config();
	$site = $cfg['SITE_URL'];
	$sitio = $c['sitio'] ?? [];
	$paginas = $sitio['seo']['paginas'] ?? [];
	$marca = 'Sunqu Journeys';
	$seo = [
		'pagina' => $pagina,
		'titulo' => $paginas[$pagina === 'blog' ? 'diario' : ($pagina === 'home' ? 'inicio' : $pagina)]['title'] ?? $marca,
		'descripcion' => $paginas[$pagina === 'blog' ? 'diario' : ($pagina === 'home' ? 'inicio' : $pagina)]['description'] ?? '',
		'canonical' => $site . ($pagina === 'home' ? '/' : sunqu_ruta($pagina)),
		'og_type' => 'website',
		'imagen' => '',
		'imagen_alt' => '',
		'migas' => [],
		'grafo' => [],
	];
	$inicioSlides = $c['inicio']['heroSlides'] ?? [];
	$seo['imagen'] = sunqu_img_abs((string) ($inicioSlides[0]['img'] ?? ''));
	$seo['imagen_alt'] = (string) ($inicioSlides[0]['caption'] ?? '');

	switch ($pagina) {
		case 'journey':
			$j = sunqu_buscar($c['journeys']['items'] ?? [], 'slug', $params['j'] ?? '');
			if (!$j) {
				return null;
			}
			$f = $j['ficha'] ?? [];
			$seo['titulo'] = $j['title'] . ' — ' . $marca;
			$seo['descripcion'] = sunqu_recortar((string) ($f['what'] ?? $f['why'] ?? ''));
			$seo['canonical'] = $site . sunqu_ruta('journey', $j['slug']);
			$primera = $f['slides'][0] ?? [];
			$seo['imagen'] = sunqu_img_abs((string) ($primera['img'] ?? $f['card'] ?? ''));
			$seo['imagen_alt'] = (string) ($primera['caption'] ?? $j['title']);
			$seo['migas'] = [['Inicio', $site . '/'], ['Journeys', $site . '/descubre'], [$j['title'], $seo['canonical']]];
			$seo['grafo'][] = sunqu_ld_journey($j, $seo);
			$seo['journey'] = ['id' => $j['slug'], 'nombre' => $j['title']];
			break;

		case 'articulo':
			$p = sunqu_buscar($c['diario']['posts'] ?? [], 'slug', $params['a'] ?? '');
			if (!$p) {
				return null;
			}
			$cats = [];
			foreach ($c['diario']['categorias'] ?? [] as $cat) {
				$cats[$cat['slug']] = $cat['label'];
			}
			$seo['titulo'] = $p['title'] . ' — Diario Sunqu';
			$seo['descripcion'] = sunqu_recortar((string) ($p['excerpt'] ?? ''));
			$seo['canonical'] = $site . sunqu_ruta('articulo', $p['slug']);
			$seo['og_type'] = 'article';
			$seo['imagen'] = sunqu_img_abs((string) ($p['img'] ?? ''));
			$seo['imagen_alt'] = (string) $p['title'];
			$seo['publicado'] = (string) ($p['date'] ?? '');
			$seo['migas'] = [['Inicio', $site . '/'], ['Diario Sunqu', $site . '/diario'], [$p['title'], $seo['canonical']]];
			$seo['grafo'][] = [
				'@type' => 'BlogPosting',
				'@id' => $seo['canonical'] . '#articulo',
				'headline' => $p['title'],
				'description' => $seo['descripcion'],
				'image' => $seo['imagen'] ?: null,
				'datePublished' => $seo['publicado'] ?: null,
				'dateModified' => $seo['publicado'] ?: null,
				'articleSection' => $cats[$p['cat'] ?? ''] ?? null,
				'inLanguage' => 'es',
				'author' => ['@type' => 'Organization', 'name' => $p['author'] ?? $marca],
				'publisher' => ['@id' => $site . '/#organizacion'],
				'mainEntityOfPage' => ['@id' => $seo['canonical'] . '#pagina'],
			];
			break;

		case 'descubre':
			$lista = [];
			$items = $c['journeys']['items'] ?? [];
			usort($items, fn($a, $b) => ($a['catalogo']['orden'] ?? 99) <=> ($b['catalogo']['orden'] ?? 99));
			foreach ($items as $i => $j) {
				$lista[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $site . sunqu_ruta('journey', $j['slug']), 'name' => $j['title']];
			}
			$seo['migas'] = [['Inicio', $site . '/'], ['Descubre', $seo['canonical']]];
			$seo['grafo'][] = ['@type' => 'ItemList', '@id' => $seo['canonical'] . '#journeys', 'name' => 'Journeys', 'itemListElement' => $lista];
			$seo['tipo_pagina'] = 'CollectionPage';
			break;

		case 'blog':
			$posts = [];
			foreach (array_slice($c['diario']['posts'] ?? [], 0, 20) as $p) {
				$posts[] = ['@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => $site . sunqu_ruta('articulo', $p['slug']), 'datePublished' => $p['date'] ?? null];
			}
			$seo['migas'] = [['Inicio', $site . '/'], ['Diario Sunqu', $seo['canonical']]];
			$seo['grafo'][] = ['@type' => 'Blog', '@id' => $seo['canonical'] . '#diario', 'name' => 'Diario Sunqu', 'url' => $seo['canonical'], 'inLanguage' => 'es', 'publisher' => ['@id' => $site . '/#organizacion'], 'blogPost' => $posts];
			$seo['tipo_pagina'] = 'CollectionPage';
			// ?cat= muestra el mismo contenido filtrado: el canonical es /diario
			break;

		case 'recuerda':
			$seo['migas'] = [['Inicio', $site . '/'], ['Recuerda', $seo['canonical']]];
			break;
		case 'terminos':
			$seo['migas'] = [['Inicio', $site . '/'], ['Términos y condiciones', $seo['canonical']]];
			break;
		case 'reclamaciones':
			$seo['migas'] = [['Inicio', $site . '/'], ['Libro de reclamaciones', $seo['canonical']]];
			break;
	}
	return $seo;
}

function sunqu_ld_journey(array $j, array $seo): array {
	$site = sunqu_config()['SITE_URL'];
	$f = $j['ficha'] ?? [];
	$k = $j['catalogo'] ?? [];
	$trip = [
		'@type' => 'TouristTrip',
		'@id' => $seo['canonical'] . '#journey',
		'name' => $j['title'],
		'description' => $seo['descripcion'],
		'image' => $seo['imagen'] ?: null,
		'url' => $seo['canonical'],
		'inLanguage' => 'es',
		'provider' => ['@id' => $site . '/#organizacion'],
	];
	$ruta = array_values(array_filter(array_map('strval', (array) ($f['route'] ?? []))));
	if ($ruta) {
		$trip['itinerary'] = ['@type' => 'ItemList', 'numberOfItems' => count($ruta), 'itemListElement' => array_map(
			fn($lugar, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'item' => ['@type' => 'Place', 'name' => $lugar]],
			$ruta, array_keys($ruta)
		)];
	}
	// Oferta solo si el catálogo tiene un precio numérico. La web muestra «Desde USD …» o un rango
	// «USD … – …»: por eso es un precio mínimo (y máximo si hay rango), no un precio exacto.
	$precio = (float) ($k['price'] ?? 0);
	if ($precio > 0) {
		$spec = ['@type' => 'PriceSpecification', 'priceCurrency' => 'USD', 'minPrice' => $precio];
		if (!empty($k['priceMax'])) {
			$spec['maxPrice'] = (float) $k['priceMax'];
		}
		$trip['offers'] = ['@type' => 'Offer', 'url' => $seo['canonical'], 'priceCurrency' => 'USD', 'priceSpecification' => $spec, 'description' => (string) ($f['priceNote'] ?? '')];
	}
	return $trip;
}

/** Organización (TravelAgency) y sitio: se repiten en todas las páginas con el mismo @id. */
function sunqu_ld_base(array $c): array {
	$cfg = sunqu_config();
	$site = $cfg['SITE_URL'];
	$s = $c['sitio'] ?? [];
	$k = $s['contacto'] ?? [];
	$e = $s['empresa'] ?? [];
	$org = [
		'@type' => 'TravelAgency',
		'@id' => $site . '/#organizacion',
		'name' => 'Sunqu Journeys',
		'url' => $site . '/',
		'logo' => $site . '/assets/logo-dark.png',
		'image' => $site . '/assets/logo-dark.png',
		'slogan' => 'Viaja al corazón del Perú',
	];
	if (!empty($e['name'])) {
		$org['legalName'] = $e['name'];
	}
	if (!empty($k['email'])) {
		$org['email'] = $k['email'];
	}
	// Teléfono y RUC solo cuando dejan de ser marcadores de posición
	if (!empty($k['telefono']) && !preg_match('/000\s*000\s*000/', $k['telefono'])) {
		$org['telephone'] = $k['telefono'];
	}
	if (!empty($e['ruc']) && preg_match('/^\d{11}$/', (string) $e['ruc'])) {
		$org['taxID'] = $e['ruc'];
	}
	$org['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $k['oficina'] ?? 'Lima', 'addressCountry' => 'PE'];
	if (preg_match('/^(.+?),\s*(Perú|Peru)$/u', (string) ($k['oficina'] ?? ''), $m)) {
		$org['address']['addressLocality'] = $m[1];
	}
	$same = [];
	foreach (['instagram', 'facebook', 'youtube', 'tiktok'] as $red) {
		$u = (string) ($s['redes'][$red] ?? '');
		if (preg_match('#^https?://#', $u)) {
			$same[] = $u;
		}
	}
	if ($same) {
		$org['sameAs'] = $same;
	}
	$web = [
		'@type' => 'WebSite',
		'@id' => $site . '/#sitio',
		'name' => 'Sunqu Journeys',
		'url' => $site . '/',
		'inLanguage' => 'es',
		'publisher' => ['@id' => $site . '/#organizacion'],
		'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $site . '/descubre?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
	];
	return [$org, $web];
}

/** Quita claves nulas del JSON-LD. */
function sunqu_limpiar_ld($v) {
	if (!is_array($v)) {
		return $v;
	}
	$out = [];
	foreach ($v as $k => $x) {
		if ($x === null || $x === '' || $x === []) {
			continue;
		}
		$out[$k] = sunqu_limpiar_ld($x);
	}
	return array_keys($v) === range(0, count($v) - 1) ? array_values($out) : $out;
}

/** Etiquetas del <head> para la página. */
function sunqu_head(array $seo, array $c, bool $noEncontrada = false): string {
	$cfg = sunqu_config();
	$site = $cfg['SITE_URL'];
	$robots = ($noEncontrada || sunqu_es_staging()) ? 'noindex, nofollow' : 'index, follow, max-image-preview:large';
	$h = [];
	$h[] = '<title>' . sunqu_e($seo['titulo']) . '</title>';
	if ($seo['descripcion'] !== '') {
		$h[] = '<meta name="description" content="' . sunqu_e($seo['descripcion']) . '">';
	}
	$h[] = '<meta name="robots" content="' . $robots . '">';
	if (!$noEncontrada) {
		$h[] = '<link rel="canonical" href="' . sunqu_e($seo['canonical']) . '">';
	}
	$h[] = '<meta name="theme-color" content="#1c1c1c">';
	$h[] = '<link rel="icon" href="/assets/favicon-32.png" type="image/png" sizes="32x32">';
	$h[] = '<link rel="apple-touch-icon" href="/assets/favicon-180.png">';
	// Open Graph y Twitter/X
	$og = [
		'og:site_name' => 'Sunqu Journeys', 'og:locale' => 'es_PE', 'og:type' => $seo['og_type'],
		'og:title' => $seo['titulo'], 'og:description' => $seo['descripcion'], 'og:url' => $seo['canonical'],
	];
	if ($seo['imagen'] !== '') {
		$og['og:image'] = $seo['imagen'];
		$og['og:image:alt'] = $seo['imagen_alt'] ?: $seo['titulo'];
		if (str_contains($seo['imagen'], 'images.unsplash.com')) {
			$og['og:image:width'] = '1200';
			$og['og:image:height'] = '630';
		}
	}
	if (!empty($seo['publicado'])) {
		$og['article:published_time'] = $seo['publicado'];
	}
	foreach ($og as $k => $v) {
		if ($v !== '') {
			$h[] = '<meta property="' . $k . '" content="' . sunqu_e((string) $v) . '">';
		}
	}
	$h[] = '<meta name="twitter:card" content="summary_large_image">';
	$h[] = '<meta name="twitter:title" content="' . sunqu_e($seo['titulo']) . '">';
	if ($seo['descripcion'] !== '') {
		$h[] = '<meta name="twitter:description" content="' . sunqu_e($seo['descripcion']) . '">';
	}
	if ($seo['imagen'] !== '') {
		$h[] = '<meta name="twitter:image" content="' . sunqu_e($seo['imagen']) . '">';
	}
	// Datos estructurados
	if (!$noEncontrada) {
		[$org, $web] = sunqu_ld_base($c);
		$pagina = [
			'@type' => $seo['tipo_pagina'] ?? 'WebPage',
			'@id' => $seo['canonical'] . '#pagina',
			'url' => $seo['canonical'],
			'name' => $seo['titulo'],
			'description' => $seo['descripcion'],
			'inLanguage' => 'es',
			'isPartOf' => ['@id' => $site . '/#sitio'],
			'about' => $seo['pagina'] === 'home' ? ['@id' => $site . '/#organizacion'] : null,
			'primaryImageOfPage' => $seo['imagen'] ? ['@type' => 'ImageObject', 'url' => $seo['imagen']] : null,
		];
		$grafo = [$org, $web, $pagina];
		if ($seo['migas']) {
			$pagina['breadcrumb'] = ['@id' => $seo['canonical'] . '#migas'];
			$grafo[2] = $pagina;
			$grafo[] = ['@type' => 'BreadcrumbList', '@id' => $seo['canonical'] . '#migas', 'itemListElement' => array_map(
				fn($m, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $m[0], 'item' => $m[1]],
				$seo['migas'], array_keys($seo['migas'])
			)];
		}
		foreach ($seo['grafo'] as $n) {
			$grafo[] = $n;
		}
		$ld = sunqu_limpiar_ld(['@context' => 'https://schema.org', '@graph' => $grafo]);
		$h[] = '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
	}
	return implode("\n\t\t", $h);
}
