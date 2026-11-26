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

/** Valor de texto de un campo del contenido (cadena o número; si no, vacío). El contenido no siempre trae lo esperado. */
function sunqu_txt($v): string {
	return is_scalar($v) ? trim((string) $v) : '';
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
	return 'https://images.unsplash.com/photo-' . rawurlencode($v) . "?auto=format&fit=crop&w=$w" . ($h > 0 ? "&h=$h" : '') . '&q=80';
}

/** Ancho, alto y tipo de una imagen local (assets/…, con o sin ?v=) para og:image; [] si no es local o no se puede leer. */
function sunqu_img_medidas(string $url): array {
	$site = sunqu_config()['SITE_URL'];
	$url = explode('?', $url, 2)[0];
	if (!str_starts_with($url, $site . '/assets/')) {
		return [];
	}
	$rel = ltrim(substr($url, strlen($site)), '/');
	$f = SUNQU_ROOT . '/' . $rel; // SUNQU_ROOT ya trae «..»: solo se revisa la parte que viene de la URL
	$m = (!str_contains($rel, '..') && is_file($f)) ? @getimagesize($f) : false;
	return $m ? [(int) $m[0], (int) $m[1], (string) ($m['mime'] ?? '')] : [];
}

/** Título con sufijo de marca solo si cabe (~62 caracteres): los buscadores cortan los títulos largos. */
function sunqu_titulo_con_marca(string $titulo, array $sufijos): string {
	foreach ($sufijos as $s) {
		if (mb_strlen($titulo . $s) <= 62) {
			return $titulo . $s;
		}
	}
	return $titulo;
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

/** URL absoluta de una ruta en el idioma de la página: https://sunqujourneys.com/en/journeys/alma */
function sunqu_url_idioma(string $ruta, ?string $lang = null): string {
	return sunqu_config()['SITE_URL'] . sunqu_ruta_idioma($ruta, $lang);
}

/** Datos SEO de la página actual (en su idioma). Devuelve null si el slug no existe (→ 404). */
function sunqu_seo(string $pagina, array $params, array $c): ?array {
	$cfg = sunqu_config();
	$site = $cfg['SITE_URL'];
	$sitio = $c['sitio'] ?? [];
	$paginas = $sitio['seo']['paginas'] ?? [];
	$marca = 'Sunqu Journeys';
	$base = $pagina === 'home' ? '/' : sunqu_ruta($pagina);
	$seo = [
		'pagina' => $pagina,
		'titulo' => $paginas[$pagina === 'blog' ? 'diario' : ($pagina === 'home' ? 'inicio' : $pagina)]['title'] ?? $marca,
		'descripcion' => $paginas[$pagina === 'blog' ? 'diario' : ($pagina === 'home' ? 'inicio' : $pagina)]['description'] ?? '',
		'ruta_base' => $base, // sin prefijo de idioma: con ella se arman el canonical, los hreflang y el selector
		'canonical' => sunqu_url_idioma($base),
		'og_type' => 'website',
		'imagen' => '', // foto principal de la página (datos estructurados); al compartir: sunqu_imagen_compartir()
		'imagen_alt' => '',
		'migas' => [],
		'grafo' => [],
	];
	// Un idioma todavía sin «Publicar en buscadores» (Sunqu › Idiomas) se ve, pero no se indexa
	if (!(sunqu_idiomas_web()[sunqu_lang()]['indexar'] ?? true)) {
		$seo['robots'] = 'noindex, follow';
	}
	$inicio = [sunqu_t('Inicio'), sunqu_url_idioma('/')];
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
			// «El Alma de los Andes: journey de 8 días — Sunqu Journeys» (tipo y duración del catálogo)
			$tipo = ($j['catalogo']['dur'] ?? '') === 'escapada' ? sunqu_t('escapada') : sunqu_t('journey');
			$dias = sunqu_txt($j['days'] ?? '');
			$seo['titulo'] = $dias !== '' ? sunqu_titulo_con_marca(sunqu_t('%1$s: %2$s de %3$s', (string) $j['title'], $tipo, $dias), [' — ' . $marca, ' — Sunqu']) : $j['title'] . ' — ' . $marca;
			// Descripción: qué es + precio (si lo hay) + por qué, en ~160 caracteres
			$precio = sunqu_txt($f['price'] ?? '');
			$porPersona = sunqu_t('por persona');
			$precio = str_contains($precio, 'USD') ? $precio . (str_contains(sunqu_txt($f['priceNote'] ?? ''), $porPersona) ? ' ' . $porPersona . '.' : '.') : '';
			$seo['descripcion'] = sunqu_recortar(implode(' ', array_filter([sunqu_txt($f['what'] ?? ''), $precio, sunqu_txt($f['why'] ?? '')])), 158);
			$seo['ruta_base'] = sunqu_ruta('journey', $j['slug']);
			$seo['canonical'] = sunqu_url_idioma($seo['ruta_base']);
			$primera = $f['slides'][0] ?? [];
			$seo['imagen'] = sunqu_img_abs((string) ($primera['img'] ?? $f['card'] ?? ''));
			$seo['imagen_alt'] = (string) ($primera['caption'] ?? $j['title']);
			$seo['migas'] = [$inicio, [sunqu_t('Journeys'), sunqu_url_idioma('/descubre')], [$j['title'], $seo['canonical']]];
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
			$diario = sunqu_t('Diario Sunqu');
			$seo['titulo'] = sunqu_titulo_con_marca((string) $p['title'], [' — ' . $diario, ' — Sunqu']);
			$seo['descripcion'] = sunqu_recortar(implode(' ', array_filter([sunqu_txt($p['excerpt'] ?? ''), sunqu_txt($p['lead'] ?? '')])), 158);
			$seo['ruta_base'] = sunqu_ruta('articulo', $p['slug']);
			$seo['canonical'] = sunqu_url_idioma($seo['ruta_base']);
			$seo['og_type'] = 'article';
			$seo['imagen'] = sunqu_img_abs((string) ($p['img'] ?? ''));
			$seo['imagen_alt'] = (string) $p['title'];
			$seo['publicado'] = (string) ($p['date'] ?? '');
			// Última edición en WordPress (si el CMS la envía); si no, la fecha de publicación
			$seo['modificado'] = preg_match('/^\d{4}-\d{2}-\d{2}/', sunqu_txt($p['_modificado'] ?? '')) ? sunqu_txt($p['_modificado']) : $seo['publicado'];
			$seo['seccion'] = $cats[$p['cat'] ?? ''] ?? '';
			$seo['migas'] = [$inicio, [$diario, sunqu_url_idioma('/diario')], [$p['title'], $seo['canonical']]];
			$seo['grafo'][] = [
				'@type' => 'BlogPosting',
				'@id' => $seo['canonical'] . '#articulo',
				'headline' => $p['title'],
				'description' => $seo['descripcion'],
				'image' => $seo['imagen'] ?: null,
				'datePublished' => $seo['publicado'] ?: null,
				'dateModified' => $seo['modificado'] ?: null,
				'articleSection' => $seo['seccion'] ?: null,
				'inLanguage' => sunqu_lang(),
				// «Equipo Sunqu»: el autor es el equipo (una organización), no una persona
				'author' => ['@type' => 'Organization', 'name' => $p['author'] ?? $marca, 'url' => sunqu_url_idioma('/')],
				'publisher' => ['@id' => $site . '/#organizacion'],
				'mainEntityOfPage' => ['@id' => $seo['canonical'] . '#pagina'],
			];
			break;

		case 'descubre':
			$lista = [];
			$items = $c['journeys']['items'] ?? [];
			usort($items, fn($a, $b) => ($a['catalogo']['orden'] ?? 99) <=> ($b['catalogo']['orden'] ?? 99));
			foreach ($items as $i => $j) {
				$lista[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => sunqu_url_idioma(sunqu_ruta('journey', $j['slug'])), 'name' => $j['title']];
			}
			$seo['migas'] = [$inicio, [sunqu_t('Descubre'), $seo['canonical']]];
			$seo['grafo'][] = ['@type' => 'ItemList', '@id' => $seo['canonical'] . '#journeys', 'name' => sunqu_t('Journeys'), 'itemListElement' => $lista];
			$seo['tipo_pagina'] = 'CollectionPage';
			// Resultados de una búsqueda interna (?q=): se siguen los enlaces, pero la página no se indexa
			if (isset($params['q'])) {
				$seo['robots'] = 'noindex, follow';
			}
			break;

		case 'blog':
			$posts = [];
			foreach (array_slice($c['diario']['posts'] ?? [], 0, 20) as $p) {
				$posts[] = ['@type' => 'BlogPosting', 'headline' => $p['title'], 'url' => sunqu_url_idioma(sunqu_ruta('articulo', $p['slug'])), 'datePublished' => $p['date'] ?? null];
			}
			$seo['migas'] = [$inicio, [sunqu_t('Diario Sunqu'), $seo['canonical']]];
			$seo['grafo'][] = ['@type' => 'Blog', '@id' => $seo['canonical'] . '#diario', 'name' => sunqu_t('Diario Sunqu'), 'url' => $seo['canonical'], 'inLanguage' => sunqu_lang(), 'publisher' => ['@id' => $site . '/#organizacion'], 'blogPost' => $posts];
			$seo['tipo_pagina'] = 'CollectionPage';
			// ?cat= muestra el mismo contenido filtrado: el canonical es /diario
			break;

		case 'recuerda':
			$seo['migas'] = [$inicio, [sunqu_t('Recuerda'), $seo['canonical']]];
			break;
		case 'mice':
			$seo['migas'] = [$inicio, [sunqu_t('MICE'), $seo['canonical']]];
			break;
		case 'terminos':
			$seo['migas'] = [$inicio, [sunqu_t('Términos y condiciones'), $seo['canonical']]];
			break;
		case 'reclamaciones':
			$seo['migas'] = [$inicio, [sunqu_t('Libro de reclamaciones'), $seo['canonical']]];
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
		'inLanguage' => sunqu_lang(),
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
		'slogan' => sunqu_t('Viaja al corazón del Perú'),
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
	// Dónde opera y rango de precios del catálogo (los mismos que muestra la web)
	$org['areaServed'] = ['@type' => 'Country', 'name' => sunqu_t('Perú')];
	$precios = [];
	foreach ($c['journeys']['items'] ?? [] as $j) {
		foreach (['price', 'priceMax'] as $campo) {
			if (is_numeric($j['catalogo'][$campo] ?? null) && (float) $j['catalogo'][$campo] > 0) {
				$precios[] = (float) $j['catalogo'][$campo];
			}
		}
	}
	if ($precios) {
		$org['priceRange'] = 'USD ' . number_format(min($precios), 0, ',', '.') . ' – ' . number_format(max($precios), 0, ',', '.');
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
		'inLanguage' => count(sunqu_idiomas_indexables()) > 1 ? sunqu_idiomas_indexables() : SUNQU_IDIOMA_BASE, // los que se indexan
		'publisher' => ['@id' => $site . '/#organizacion'],
		'potentialAction' => ['@type' => 'SearchAction', 'target' => ['@type' => 'EntryPoint', 'urlTemplate' => sunqu_url_idioma('/descubre') . '?q={search_term_string}'], 'query-input' => 'required name=search_term_string'],
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

/**
 * Imagen para compartir (Open Graph y X): [URL, texto alternativo]. Un journey o un artículo comparten su foto; las
 * demás páginas, y los que no tienen foto, la imagen de marca del idioma (server/lib/og.php). Sin ella, la foto
 * principal de la página.
 */
function sunqu_imagen_compartir(array $seo): array {
	if (in_array($seo['pagina'], ['journey', 'articulo'], true) && $seo['imagen'] !== '') {
		return [$seo['imagen'], $seo['imagen_alt']];
	}
	return sunqu_og_marca() ?? [$seo['imagen'], $seo['imagen_alt']];
}

/** Etiquetas del <head> para la página. */
function sunqu_head(array $seo, array $c, bool $noEncontrada = false): string {
	$cfg = sunqu_config();
	$site = $cfg['SITE_URL'];
	$robots = ($noEncontrada || sunqu_es_staging()) ? 'noindex, nofollow' : ($seo['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
	$h = [];
	$h[] = '<title>' . sunqu_e($seo['titulo']) . '</title>';
	if ($seo['descripcion'] !== '') {
		$h[] = '<meta name="description" content="' . sunqu_e($seo['descripcion']) . '">';
	}
	$h[] = '<meta name="robots" content="' . $robots . '">';
	if (!$noEncontrada) {
		$h[] = '<link rel="canonical" href="' . sunqu_e($seo['canonical']) . '">';
	}
	// La misma página en los otros idiomas que se indexan (Sunqu › Idiomas › «Publicar en buscadores»)
	$indexables = sunqu_idiomas_indexables();
	if (!$noEncontrada && !str_contains($robots, 'noindex') && count($indexables) > 1) {
		foreach ($indexables as $l) {
			$h[] = '<link rel="alternate" hreflang="' . $l . '" href="' . sunqu_e(sunqu_url_idioma($seo['ruta_base'], $l)) . '">';
		}
		// x-default: el idioma por defecto (inglés, si se indexa); quien no coincide con ningún idioma ve ese
		$h[] = '<link rel="alternate" hreflang="x-default" href="' . sunqu_e(sunqu_url_idioma($seo['ruta_base'], sunqu_idioma_defecto_indexable())) . '">';
	}
	$h[] = '<meta name="theme-color" content="#1c1c1c">';
	// Íconos cuadrados: Google muestra en los resultados un favicon cuadrado de 48 px o más
	$h[] = '<link rel="icon" href="/assets/favicon-32.png" type="image/png" sizes="32x32">';
	$h[] = '<link rel="icon" href="/assets/favicon-48.png" type="image/png" sizes="48x48">';
	$h[] = '<link rel="icon" href="/assets/favicon-192.png" type="image/png" sizes="192x192">';
	$h[] = '<link rel="apple-touch-icon" href="/assets/favicon-180.png" sizes="180x180">';
	// Open Graph y Twitter/X
	$og = [
		'og:site_name' => 'Sunqu Journeys', 'og:locale' => sunqu_idiomas_web()[sunqu_lang()]['locale'] ?? 'es_PE', 'og:type' => $seo['og_type'],
		'og:title' => $seo['titulo'], 'og:description' => $seo['descripcion'], 'og:url' => $seo['canonical'],
	];
	[$imagen, $imagenAlt] = sunqu_imagen_compartir($seo);
	$imagenAlt = $imagenAlt ?: $seo['titulo'];
	if ($imagen !== '') {
		$og['og:image'] = $imagen;
		$og['og:image:alt'] = $imagenAlt;
		$medidas = str_contains($imagen, 'images.unsplash.com') ? [1200, 630] : sunqu_img_medidas($imagen);
		if ($medidas) {
			$og['og:image:width'] = (string) $medidas[0];
			$og['og:image:height'] = (string) $medidas[1];
			$og['og:image:type'] = $medidas[2] ?? ''; // solo de las locales: Unsplash elige el formato según el navegador
		}
	}
	if (!empty($seo['publicado'])) {
		$og['article:published_time'] = $seo['publicado'];
		$og['article:modified_time'] = $seo['modificado'] ?? $seo['publicado'];
		$og['article:section'] = $seo['seccion'] ?? '';
	}
	foreach ($og as $k => $v) {
		if ($v !== '') {
			$h[] = '<meta property="' . $k . '" content="' . sunqu_e((string) $v) . '">';
		}
	}
	foreach (sunqu_idiomas_web() as $l => $a) {
		if ($l !== sunqu_lang() && $a['indexar']) {
			$h[] = '<meta property="og:locale:alternate" content="' . sunqu_e($a['locale']) . '">';
		}
	}
	$h[] = '<meta name="twitter:card" content="summary_large_image">';
	$h[] = '<meta name="twitter:title" content="' . sunqu_e($seo['titulo']) . '">';
	if ($seo['descripcion'] !== '') {
		$h[] = '<meta name="twitter:description" content="' . sunqu_e($seo['descripcion']) . '">';
	}
	if ($imagen !== '') {
		$h[] = '<meta name="twitter:image" content="' . sunqu_e($imagen) . '">';
		$h[] = '<meta name="twitter:image:alt" content="' . sunqu_e($imagenAlt) . '">';
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
			'inLanguage' => sunqu_lang(),
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
