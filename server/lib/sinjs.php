<?php
/**
 * Contenido sin JavaScript (<noscript>).
 *
 * Las páginas se dibujan en el navegador (runtime de plantillas + React). Google ejecuta JavaScript, pero otros
 * buscadores, las vistas previas y los asistentes de IA suelen leer solo el HTML. Este bloque les entrega la
 * misma información de la página en HTML simple: título, textos, journeys, artículos completos, términos…
 * Con JavaScript activo el navegador no lo muestra (es <noscript>): el diseño no cambia.
 *
 * Todo sale del contenido (content/*.json o WordPress) y todo texto pasa por sunqu_e(). Las reseñas de muestra
 * («sample») no se incluyen, y tampoco los precios de la tienda (pendientes de confirmar).
 * En otro idioma, el contenido ya llega traducido; los títulos fijos pasan por sunqu_t() y los enlaces llevan el
 * prefijo del idioma (/en/…).
 */

declare(strict_types=1);

/** Estilos mínimos del bloque (solo se aplican sin JavaScript). */
function sunqu_sin_js_estilos(): string {
	return '<noscript><style>'
		. '.sj-sinjs{max-width:760px;margin:0 auto;padding:32px 20px 56px;font-family:Aeonik,system-ui,sans-serif;font-size:17px;line-height:1.6;color:#1c1c1c;background:#e9eef1}'
		. '.sj-sinjs h1{font-size:clamp(34px,6vw,54px);line-height:1.06;letter-spacing:-.02em;margin:24px 0 16px}'
		. '.sj-sinjs h2{font-size:26px;line-height:1.15;margin:36px 0 10px}.sj-sinjs h3{font-size:19px;margin:20px 0 6px}'
		. '.sj-sinjs a{color:#676140}.sj-sinjs nav a{margin-right:14px}.sj-sinjs li{margin:6px 0}'
		. '.sj-sinjs blockquote{margin:16px 0;padding-left:16px;border-left:3px solid #676140}'
		. '.sj-sinjs img{max-width:100%;height:auto}.sj-sinjs footer{margin-top:40px;font-size:15px}'
		. '</style></noscript>';
}

/** Texto del <h1> de la plantilla si es fijo (sin {{ }}), p. ej. «Encuentra tu viaje perfecto.». */
function sunqu_h1_plantilla(string $html): string {
	if (!preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $m)) {
		return '';
	}
	$t = trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace('</span>', ' </span>', $m[1]))) ?? '');
	return str_contains($t, '{{') ? '' : $t;
}

/** Párrafo, si hay texto. */
function sunqu_p(string $t): string {
	return $t !== '' ? '<p>' . sunqu_e($t) . '</p>' : '';
}

/** Bloques de contenido de un artículo o de los términos: { p } · { ul } · { quote, cite } · { img, cap } · { h3 }. */
function sunqu_sin_js_bloques(array $bloques): string {
	$o = '';
	foreach ($bloques as $b) {
		if (!is_array($b)) {
			continue;
		}
		if (($t = sunqu_txt($b['p'] ?? '')) !== '') {
			$o .= sunqu_p($t);
		} elseif (isset($b['ul']) && is_array($b['ul'])) {
			$li = '';
			foreach ($b['ul'] as $it) {
				[$neg, $txt] = is_array($it) ? (array_is_list($it) ? [sunqu_txt($it[0] ?? ''), sunqu_txt($it[1] ?? '')] : [sunqu_txt($it['b'] ?? ''), sunqu_txt($it['t'] ?? '')]) : ['', sunqu_txt($it)];
				$li .= '<li>' . ($neg !== '' ? '<strong>' . sunqu_e($neg) . '</strong> ' : '') . sunqu_e($txt) . '</li>';
			}
			$o .= $li !== '' ? '<ul>' . $li . '</ul>' : '';
		} elseif (($q = sunqu_txt($b['quote'] ?? '')) !== '') {
			$cite = sunqu_txt($b['cite'] ?? '');
			$o .= '<blockquote><p>' . sunqu_e($q) . '</p>' . ($cite !== '' ? '<footer>' . sunqu_e($cite) . '</footer>' : '') . '</blockquote>';
		} elseif (($img = sunqu_txt($b['img'] ?? '')) !== '') {
			$cap = sunqu_txt($b['cap'] ?? '');
			$src = sunqu_img_abs($img, 960, 0);
			$o .= '<figure><img src="' . sunqu_e($src) . '" alt="' . sunqu_e($cap) . '" loading="lazy">' . ($cap !== '' ? '<figcaption>' . sunqu_e($cap) . '</figcaption>' : '') . '</figure>';
		} elseif (($h = sunqu_txt($b['h3'] ?? $b['h'] ?? '')) !== '') {
			$o .= '<h3>' . sunqu_e($h) . '</h3>';
		}
	}
	return $o;
}

/** Journeys en el orden del catálogo. */
function sunqu_sin_js_journeys(array $c): array {
	$items = array_values(array_filter($c['journeys']['items'] ?? [], fn($j) => is_array($j) && sunqu_txt($j['slug'] ?? '') !== ''));
	usort($items, fn($a, $b) => ($a['catalogo']['orden'] ?? 99) <=> ($b['catalogo']['orden'] ?? 99));
	return $items;
}

/** Lista de journeys con su enlace, duración, región, precio y el porqué. */
function sunqu_sin_js_lista_journeys(array $items, string $excluir = ''): string {
	$li = '';
	foreach ($items as $j) {
		$slug = sunqu_txt($j['slug']);
		if ($slug === $excluir) {
			continue;
		}
		$f = is_array($j['ficha'] ?? null) ? $j['ficha'] : [];
		$datos = array_filter([sunqu_txt($j['days'] ?? ''), sunqu_txt($f['region'] ?? $j['catalogo']['region'] ?? ''), sunqu_txt($f['price'] ?? '')]);
		$por = sunqu_txt($j['catalogo']['why'] ?? $f['why'] ?? '');
		$li .= '<li><a href="' . sunqu_e(sunqu_ruta_idioma(sunqu_ruta('journey', $slug))) . '">' . sunqu_e(sunqu_txt($j['title'] ?? $slug)) . '</a>'
			. ($datos ? ' — ' . sunqu_e(implode(' · ', $datos)) : '') . ($por !== '' ? '. ' . sunqu_e($por) : '') . '</li>';
	}
	return $li !== '' ? '<ul>' . $li . '</ul>' : '';
}

/** Artículos del Diario (más recientes primero). */
function sunqu_sin_js_posts(array $c): array {
	$posts = array_values(array_filter($c['diario']['posts'] ?? [], fn($p) => is_array($p) && sunqu_txt($p['slug'] ?? '') !== ''));
	usort($posts, fn($a, $b) => strcmp(sunqu_txt($b['date'] ?? ''), sunqu_txt($a['date'] ?? '')));
	return $posts;
}

function sunqu_sin_js_lista_posts(array $posts, array $cats, int $max = 50): string {
	$li = '';
	foreach (array_slice($posts, 0, $max) as $p) {
		$meta = array_filter([sunqu_txt($p['date'] ?? ''), $cats[sunqu_txt($p['cat'] ?? '')] ?? '']);
		$li .= '<li><a href="' . sunqu_e(sunqu_ruta_idioma(sunqu_ruta('articulo', sunqu_txt($p['slug'])))) . '">' . sunqu_e(sunqu_txt($p['title'] ?? '')) . '</a>'
			. ($meta ? ' (' . sunqu_e(implode(' · ', $meta)) . ')' : '') . (($x = sunqu_txt($p['excerpt'] ?? '')) !== '' ? '. ' . sunqu_e($x) : '') . '</li>';
	}
	return $li !== '' ? '<ul>' . $li . '</ul>' : '';
}

/** Bloque <noscript> completo de la página. $plantilla: HTML de la plantilla (para el <h1> fijo). */
function sunqu_sin_js(string $pagina, array $params, array $c, array $seo, string $plantilla): string {
	$h1 = sunqu_h1_plantilla($plantilla);
	$cats = [];
	foreach ($c['diario']['categorias'] ?? [] as $cat) {
		if (is_array($cat) && sunqu_txt($cat['slug'] ?? '') !== '') {
			$cats[sunqu_txt($cat['slug'])] = sunqu_txt($cat['label'] ?? '');
		}
	}
	$o = '';
	switch ($pagina) {
		case 'home':
			$o .= sunqu_p($seo['descripcion']);
			$o .= '<h2>' . sunqu_e(sunqu_t('Journeys')) . '</h2>' . sunqu_sin_js_lista_journeys(sunqu_sin_js_journeys($c));
			$posts = sunqu_sin_js_posts($c);
			if ($posts) {
				$o .= '<h2>' . sunqu_e(sunqu_t('Diario Sunqu')) . '</h2>' . sunqu_sin_js_lista_posts($posts, $cats, 3)
					. '<p><a href="' . sunqu_e(sunqu_ruta_idioma('/diario')) . '">' . sunqu_e(sunqu_t('Todos los artículos')) . '</a></p>';
			}
			break;

		case 'journey':
			$j = sunqu_buscar($c['journeys']['items'] ?? [], 'slug', $params['j'] ?? '') ?? [];
			$f = is_array($j['ficha'] ?? null) ? $j['ficha'] : [];
			$h1 = sunqu_txt($j['title'] ?? '');
			$o .= sunqu_p(sunqu_txt($f['whatLong'] ?? $f['what'] ?? ''));
			$o .= sunqu_p(sunqu_txt($f['why'] ?? ''));
			$datos = array_filter([
				sunqu_t('Duración') => sunqu_txt($j['days'] ?? ''), sunqu_t('Región') => sunqu_txt($f['region'] ?? ''),
				sunqu_txt($f['altLabel'] ?? '') ?: sunqu_t('Altitud de entrada') => sunqu_txt($j['alt'] ?? ''),
				sunqu_t('Precio') => trim(sunqu_txt($f['price'] ?? '') . ' ' . sunqu_txt($f['priceNote'] ?? '')),
			]);
			if ($datos) {
				$o .= '<ul>' . implode('', array_map(fn($k, $v) => '<li><strong>' . sunqu_e($k) . ':</strong> ' . sunqu_e($v) . '</li>', array_keys($datos), $datos)) . '</ul>';
			}
			if (($t = sunqu_txt($f['storyTitle'] ?? '')) !== '') {
				$o .= '<h2>' . sunqu_e($t) . '</h2>';
			}
			foreach ((array) ($f['story'] ?? []) as $par) {
				$o .= sunqu_p(sunqu_txt($par));
			}
			$ruta = array_filter(array_map('sunqu_txt', (array) ($f['route'] ?? [])));
			if ($ruta) {
				$o .= '<h2>' . sunqu_e(sunqu_t('Ruta')) . '</h2><ol>' . implode('', array_map(fn($r) => '<li>' . sunqu_e($r) . '</li>', $ruta)) . '</ol>';
			}
			$itin = array_filter((array) ($f['itin'] ?? []), 'is_array');
			if ($itin) {
				$o .= '<h2>' . sunqu_e(sunqu_txt($f['vivTitle'] ?? '') ?: sunqu_t('Día a día')) . '</h2><ol>';
				foreach ($itin as $d) {
					$o .= '<li><strong>' . sunqu_e(sunqu_txt($d['t'] ?? '')) . '.</strong> ' . sunqu_e(sunqu_txt($d['body'] ?? '')) . '</li>';
				}
				$o .= '</ol>';
			}
			$plan = is_array($j['plan'] ?? null) ? $j['plan'] : [];
			$temporada = is_array($plan['season'] ?? null) ? $plan['season'] : [];
			if (($t = sunqu_txt($temporada['text'] ?? '')) !== '') {
				$o .= '<h2>' . sunqu_e(sunqu_t('El mejor momento para visitar')) . '</h2>' . sunqu_p($t);
			}
			$notas = array_filter(array_merge(array_map('sunqu_txt', (array) ($plan['tips'] ?? [])), [sunqu_txt($plan['access'] ?? '')]));
			if ($notas) {
				$o .= '<h2>' . sunqu_e(sunqu_t('Notas importantes')) . '</h2><ul>' . implode('', array_map(fn($n) => '<li>' . sunqu_e($n) . '</li>', $notas)) . '</ul>';
			}
			$o .= '<p><a href="' . sunqu_e(sunqu_ruta_idioma('/')) . '#contacto">' . sunqu_e(sunqu_t('Diseña tu viaje')) . '</a></p>';
			$otros = sunqu_sin_js_lista_journeys(sunqu_sin_js_journeys($c), sunqu_txt($j['slug'] ?? ''));
			$o .= $otros !== '' ? '<h2>' . sunqu_e(sunqu_t('Otros journeys')) . '</h2>' . $otros : '';
			break;

		case 'descubre':
			$o .= sunqu_p($seo['descripcion']);
			$o .= '<h2>' . sunqu_e(sunqu_t('Journeys')) . '</h2>' . sunqu_sin_js_lista_journeys(sunqu_sin_js_journeys($c));
			$li = '';
			foreach ($c['experiencias']['items'] ?? [] as $x) {
				if (is_array($x) && ($t = sunqu_txt($x['t'] ?? '')) !== '') {
					$datos = array_filter([sunqu_txt($x['place'] ?? ''), sunqu_txt($x['dur'] ?? '')]);
					$li .= '<li>' . sunqu_e($t) . ($datos ? ' — ' . sunqu_e(implode(' · ', $datos)) : '') . '</li>';
				}
			}
			$o .= $li !== '' ? '<h2>' . sunqu_e(sunqu_t('Experiencias')) . '</h2><ul>' . $li . '</ul>' : '';
			break;

		case 'recuerda':
			$o .= sunqu_p($seo['descripcion']);
			$li = '';
			foreach ($c['resenas']['items'] ?? [] as $r) {
				if (is_array($r) && empty($r['sample']) && ($t = sunqu_txt($r['caption'] ?? '')) !== '') {
					$quien = array_filter([sunqu_txt($r['name'] ?? ''), sunqu_txt($r['country'] ?? '')]);
					$li .= '<li><blockquote><p>' . sunqu_e($t) . '</p>' . ($quien ? '<footer>' . sunqu_e(implode(', ', $quien)) . '</footer>' : '') . '</blockquote></li>';
				}
			}
			$o .= $li !== '' ? '<h2>' . sunqu_e(sunqu_t('Reseñas')) . '</h2><ul>' . $li . '</ul>' : '';
			$li = '';
			foreach ($c['productos']['items'] ?? [] as $p) {
				if (is_array($p) && ($t = sunqu_txt($p['name'] ?? '')) !== '') {
					$li .= '<li><strong>' . sunqu_e($t) . '</strong>' . (($d = sunqu_txt($p['desc'] ?? '')) !== '' ? '. ' . sunqu_e($d) : '') . '</li>';
				}
			}
			$o .= $li !== '' ? '<h2>' . sunqu_e(sunqu_t('Tienda Sunqu')) . '</h2><ul>' . $li . '</ul>' : '';
			break;

		case 'blog':
			$o .= sunqu_p($seo['descripcion']) . sunqu_sin_js_lista_posts(sunqu_sin_js_posts($c), $cats);
			break;

		case 'articulo':
			$p = sunqu_buscar($c['diario']['posts'] ?? [], 'slug', $params['a'] ?? '') ?? [];
			$h1 = sunqu_txt($p['title'] ?? '');
			$meta = array_filter([sunqu_txt($p['date'] ?? ''), sunqu_txt($p['author'] ?? ''), $cats[sunqu_txt($p['cat'] ?? '')] ?? '', sunqu_txt($p['read'] ?? '')]);
			$o .= $meta ? '<p>' . sunqu_e(implode(' · ', $meta)) . '</p>' : '';
			$o .= sunqu_p(sunqu_txt($p['lead'] ?? '') ?: sunqu_txt($p['excerpt'] ?? ''));
			foreach ((array) ($p['sections'] ?? []) as $s) {
				if (!is_array($s)) {
					continue;
				}
				$o .= (($h = sunqu_txt($s['h'] ?? '')) !== '' ? '<h2>' . sunqu_e($h) . '</h2>' : '') . sunqu_sin_js_bloques((array) ($s['blocks'] ?? []));
			}
			$otros = array_filter(sunqu_sin_js_posts($c), fn($x) => sunqu_txt($x['slug']) !== sunqu_txt($p['slug'] ?? ''));
			$o .= $otros ? '<h2>' . sunqu_e(sunqu_t('Más del Diario Sunqu')) . '</h2>' . sunqu_sin_js_lista_posts(array_values($otros), $cats, 3) : '';
			break;

		case 'terminos':
			foreach ($c['legales']['terminos']['secciones'] ?? [] as $s) {
				if (!is_array($s)) {
					continue;
				}
				$id = preg_match('/^[a-z0-9-]+$/', sunqu_txt($s['id'] ?? '')) ? ' id="' . sunqu_e(sunqu_txt($s['id'])) . '"' : '';
				$o .= (($h = sunqu_txt($s['h'] ?? '')) !== '' ? '<h2' . $id . '>' . sunqu_e($h) . '</h2>' : '') . sunqu_sin_js_bloques((array) ($s['blocks'] ?? []));
			}
			break;

		case 'reclamaciones':
			$o .= sunqu_p($seo['descripcion']);
			$emp = is_array($c['sitio']['empresa'] ?? null) ? $c['sitio']['empresa'] : [];
			$datos = array_filter([
				sunqu_t('Razón social') => sunqu_txt($emp['name'] ?? ''),
				'RUC' => preg_match('/^\d{11}$/', sunqu_txt($emp['ruc'] ?? '')) ? sunqu_txt($emp['ruc']) : '',
				sunqu_t('Dirección') => sunqu_txt($emp['address'] ?? ''),
			]);
			if ($datos) {
				$o .= '<ul>' . implode('', array_map(fn($k, $v) => '<li><strong>' . sunqu_e($k) . ':</strong> ' . sunqu_e($v) . '</li>', array_keys($datos), $datos)) . '</ul>';
			}
			$correo = sunqu_txt($c['sitio']['contacto']['email'] ?? '');
			$enlace = '<a href="mailto:' . sunqu_e($correo) . '">' . sunqu_e($correo) . '</a>';
			$o .= '<p>' . sunqu_e(sunqu_t('La hoja de reclamación virtual necesita JavaScript.'))
				. ($correo !== '' ? ' ' . str_replace('%s', $enlace, sunqu_e(sunqu_t('También puedes escribirnos a %s.'))) : '') . '</p>';
			break;
	}

	// Contacto (todas las páginas): correo y WhatsApp del contenido
	$k = is_array($c['sitio']['contacto'] ?? null) ? $c['sitio']['contacto'] : [];
	$wa = sunqu_whatsapp($c);
	$contacto = array_filter([
		($correo = sunqu_txt($k['email'] ?? '')) !== '' ? '<a href="mailto:' . sunqu_e($correo) . '">' . sunqu_e($correo) . '</a>' : '',
		$wa !== '' ? '<a href="https://wa.me/' . sunqu_e($wa) . '">WhatsApp</a>' : '',
	]);
	$a = fn(string $ruta, string $texto) => '<a href="' . sunqu_e(sunqu_ruta_idioma($ruta)) . '">' . sunqu_e(sunqu_t($texto)) . '</a>';
	$nav = '<nav aria-label="' . sunqu_e(sunqu_t('Principal')) . '">' . $a('/', 'Inicio') . $a('/descubre', 'Descubre') . $a('/recuerda', 'Recuerda') . $a('/diario', 'Diario Sunqu') . '</nav>';
	$pie = '<footer>' . ($contacto ? '<p>' . sunqu_e(sunqu_t('Contacto')) . ': ' . implode(' · ', $contacto) . '</p>' : '')
		. '<p>' . $a('/terminos', 'Términos y condiciones') . ' · ' . $a('/libro-de-reclamaciones', 'Libro de reclamaciones') . '</p></footer>';
	return '<noscript><div class="sj-sinjs">' . $nav . '<main><h1>' . sunqu_e($h1 !== '' ? $h1 : $seo['titulo']) . '</h1>' . $o . '</main>' . $pie . '</div></noscript>';
}
