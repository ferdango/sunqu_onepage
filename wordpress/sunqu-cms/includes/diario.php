<?php
/**
 * Diario Sunqu: entradas nativas de WordPress ↔ formato de content/diario.json.
 *
 *   slug → post_name · title → post_title · date → post_date · excerpt → post_excerpt
 *   img → imagen destacada · cat → categoría · read, author, role, lead → campos «Diario Sunqu»
 *   sections → cuerpo en el editor de bloques:
 *       Encabezado (H2)            = nueva sección { h }
 *       Párrafo                    = { p }
 *       Lista con negrita inicial  = { ul: [{ b, t }] }
 *       Cita (con fuente)          = { quote, cite }
 *       Imagen (con leyenda)       = { img, cap }
 */

defined('ABSPATH') || exit;

function sunqu_exportar_diario(array $sec): array {
	// Categorías en el orden de la web (término meta sunqu_orden)
	$defecto = (int) get_option('default_category');
	$terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
	$cats = [];
	foreach ($terms as $t) {
		if ((int) $t->term_id === $defecto) {
			continue; // «Sin categoría» de WordPress
		}
		$cats[] = ['orden' => (int) get_term_meta($t->term_id, 'sunqu_orden', true), 'slug' => $t->slug, 'label' => html_entity_decode($t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
	}
	usort($cats, fn($a, $b) => [$a['orden'], $a['label']] <=> [$b['orden'], $b['label']]);
	$cats = array_map(fn($c) => ['slug' => $c['slug'], 'label' => $c['label']], $cats);

	$posts = [];
	foreach (get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']) as $p) {
		$terms = get_the_terms($p, 'category') ?: [];
		$cat = '';
		foreach ($terms as $t) {
			if ((int) $t->term_id !== $defecto) {
				$cat = $t->slug;
				break;
			}
		}
		$thumb = get_post_thumbnail_id($p);
		$img = $thumb ? sunqu_url_media($thumb) : (string) get_post_meta($p->ID, '_sunqu_img', true);
		$item = [
			'slug' => $p->post_name,
			'cat' => $cat,
			'date' => get_the_date('Y-m-d', $p),
			'img' => $img,
			'title' => $p->post_title, // sin filtros: wptexturize cambiaría comillas y guiones
			'excerpt' => $p->post_excerpt,
		];
		$item += sunqu_leer_campos(['post', $p->ID], $sec['campos']);
		$item['sections'] = sunqu_bloques_a_secciones($p->post_content);
		$item['_modificado'] = get_post_modified_time('c', false, $p); // última edición (sitemap y dateModified)
		$posts[] = $item;
	}
	return ['categorias' => $cats, 'posts' => $posts];
}

/** Texto plano de un fragmento HTML. */
function sunqu_texto(string $html): string {
	return trim(html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Cuerpo del editor de bloques → [{ h, blocks: [...] }]. */
function sunqu_bloques_a_secciones(string $contenido): array {
	$secciones = [];
	$actual = null;
	foreach (parse_blocks($contenido) as $b) {
		$name = $b['blockName'] ?? '';
		$html = $b['innerHTML'] ?? '';
		if ($name === 'core/heading') {
			if ($actual) {
				$secciones[] = $actual;
			}
			$actual = ['h' => sunqu_texto($html), 'blocks' => []];
			continue;
		}
		if ($name === '' && trim(wp_strip_all_tags($html)) === '') {
			continue; // espacio entre bloques
		}
		if (!$actual) {
			$actual = ['h' => '', 'blocks' => []];
		}
		switch ($name) {
			case 'core/paragraph':
				$actual['blocks'][] = ['p' => sunqu_texto($html)];
				break;
			case 'core/list':
				$items = [];
				$lis = $b['innerBlocks'] ?: [];
				$fuentes = $lis ? array_map(fn($li) => $li['innerHTML'], $lis) : (preg_match_all('#<li[^>]*>(.*?)</li>#s', $html, $m) ? $m[1] : []);
				foreach ($fuentes as $li) {
					if (preg_match('#<strong>(.*?)</strong>(.*)#s', $li, $mm)) {
						$items[] = ['b' => sunqu_texto($mm[1]), 't' => sunqu_texto($mm[2])];
					} else {
						$items[] = ['b' => '', 't' => sunqu_texto($li)];
					}
				}
				$actual['blocks'][] = ['ul' => $items];
				break;
			case 'core/quote':
			case 'core/pullquote':
				$full = $html . implode('', array_map(fn($x) => $x['innerHTML'], $b['innerBlocks'] ?? []));
				$cite = preg_match('#<cite[^>]*>(.*?)</cite>#s', $html, $m) ? sunqu_texto($m[1]) : '';
				$sin_cite = preg_replace('#<cite[^>]*>.*?</cite>#s', '', $full);
				$actual['blocks'][] = ['quote' => sunqu_texto($sin_cite), 'cite' => $cite];
				break;
			case 'core/image':
				$src = preg_match('#<img[^>]+src="([^"]+)"#', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
				$cap = preg_match('#<figcaption[^>]*>(.*?)</figcaption>#s', $html, $m) ? sunqu_texto($m[1]) : '';
				$id = (int) ($b['attrs']['id'] ?? 0);
				$fuente = $id ? (string) get_post_meta($id, '_sunqu_fuente', true) : '';
				if ($src && $id) {
					sunqu_registrar_srcset($src, $id);
				}
				$actual['blocks'][] = ['img' => $src ?: $fuente, 'cap' => $cap];
				break;
			default:
				$t = sunqu_texto($html);
				if ($t !== '') {
					$actual['blocks'][] = ['p' => $t];
				}
		}
	}
	if ($actual) {
		$secciones[] = $actual;
	}
	return $secciones;
}

/** [{ h, blocks }] → cuerpo del editor de bloques. $media convierte una imagen del JSON en [id, url]. */
function sunqu_secciones_a_bloques(array $secciones, callable $media): string {
	$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8', true); // sin esc_html: no debe «ahorrarse» entidades ya escritas
	$out = [];
	foreach ($secciones as $s) {
		if (($s['h'] ?? '') !== '') {
			$out[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . $e($s['h']) . "</h2>\n<!-- /wp:heading -->";
		}
		foreach ($s['blocks'] ?? [] as $b) {
			if (isset($b['p'])) {
				$out[] = "<!-- wp:paragraph -->\n<p>" . $e($b['p']) . "</p>\n<!-- /wp:paragraph -->";
			} elseif (isset($b['ul'])) {
				$lis = '';
				foreach ($b['ul'] as $it) {
					[$bold, $txt] = is_array($it) && isset($it['t']) ? [$it['b'] ?? '', $it['t']] : [$it[0] ?? '', $it[1] ?? ''];
					$li = ($bold !== '' ? '<strong>' . $e($bold) . '</strong> ' : '') . $e($txt);
					$lis .= "<!-- wp:list-item -->\n<li>" . $li . "</li>\n<!-- /wp:list-item -->\n";
				}
				$out[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $lis . "</ul>\n<!-- /wp:list -->";
			} elseif (isset($b['quote'])) {
				$cite = ($b['cite'] ?? '') !== '' ? '<cite>' . $e($b['cite']) . '</cite>' : '';
				$out[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>" . $e($b['quote']) . "</p>\n<!-- /wp:paragraph -->" . $cite . "</blockquote>\n<!-- /wp:quote -->";
			} elseif (isset($b['img'])) {
				[$id, $url] = $media($b['img']);
				$cap = ($b['cap'] ?? '') !== '' ? '<figcaption class="wp-element-caption">' . $e($b['cap']) . '</figcaption>' : '';
				$attrs = $id ? ' {"id":' . (int) $id . ',"sizeSlug":"large"}' : '';
				$cls = $id ? ' class="wp-image-' . (int) $id . '"' : '';
				$out[] = "<!-- wp:image" . $attrs . " -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . esc_attr($url) . "\" alt=\"\"" . $cls . "/>" . $cap . "</figure>\n<!-- /wp:image -->";
			}
		}
	}
	return implode("\n\n", $out);
}
