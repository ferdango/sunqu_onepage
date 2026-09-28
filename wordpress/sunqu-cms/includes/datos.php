<?php
/**
 * Motor de datos: lee y escribe los campos definidos en schema.json.
 *
 * Los valores se guardan con el mismo formato que usa ACF / Secure Custom Fields, así el panel de
 * WordPress los muestra y edita sin conversiones:
 *   campo simple      {nombre}                      + referencia _{nombre} = clave del campo
 *   grupo             {grupo}_{subcampo}
 *   repetidor / lista {nombre} = nº de filas, filas {nombre}_{i}_{subcampo}   (las listas usan el subcampo «valor»)
 *   meses             {nombre}_{ene…dic} = 0 | 1 | 2
 *   relación          {nombre} = [ID de post, …]
 * En entradas y tipos de contenido van como post meta; en páginas de ajustes, como opciones
 * con el prefijo del id de la página (p. ej. sunqu_sitio_contacto_email).
 *
 * La lectura no depende de ACF: la API funciona aunque el plugin de campos esté desactivado.
 */

defined('ABSPATH') || exit;

const SUNQU_MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** Esquema completo (schema.json). */
function sunqu_schema(): array {
	static $schema = null;
	if ($schema === null) {
		$raw = file_get_contents(SUNQU_CMS_DIR . 'schema.json');
		$schema = json_decode($raw ?: '{}', true) ?: ['secciones' => []];
	}
	return $schema;
}

/** Definición de una sección (sitio, journeys, diario…). */
function sunqu_seccion(string $nombre): ?array {
	return sunqu_schema()['secciones'][$nombre] ?? null;
}

/** Nombres de todas las secciones, en orden. */
function sunqu_secciones(): array {
	return array_keys(sunqu_schema()['secciones']);
}

/** Clave estable de un campo para ACF (field_sq + hash de su ruta en el esquema). */
function sunqu_fkey(string $ruta): string {
	return 'field_sq' . substr(md5($ruta), 0, 12);
}

// ── Almacenamiento: ['post', ID] o ['opcion', 'sunqu_sitio'] ──────────────────────────────────

function sunqu_meta_get(array $ctx, string $key) {
	if ($ctx[0] === 'post') {
		return get_post_meta($ctx[1], $key, true);
	}
	return get_option($ctx[1] . '_' . $key, '');
}

function sunqu_meta_set(array $ctx, string $key, $valor, string $fkey): void {
	if ($ctx[0] === 'post') {
		update_post_meta($ctx[1], $key, wp_slash($valor));
		update_post_meta($ctx[1], '_' . $key, $fkey);
	} else {
		update_option($ctx[1] . '_' . $key, $valor, false);
		update_option('_' . $ctx[1] . '_' . $key, $fkey, false);
	}
}

// ── Lectura (WordPress → formato de content/*.json) ──────────────────────────────────────────

/** Lee una lista de campos como objeto asociativo. */
function sunqu_leer_campos(array $ctx, array $campos, string $prefijo = ''): array {
	$out = [];
	foreach ($campos as $f) {
		$meta = $prefijo === '' ? $f['name'] : $prefijo . '_' . $f['name'];
		$v = sunqu_leer_campo($ctx, $f, $meta);
		if (!empty($f['opcional']) && ($v === '' || $v === null || $v === [])) {
			continue; // clave ausente, como en el JSON original
		}
		$out[$f['name']] = $v;
	}
	return $out;
}

function sunqu_leer_campo(array $ctx, array $f, string $meta) {
	switch ($f['type']) {
		case 'group':
			return sunqu_leer_campos($ctx, $f['sub_fields'], $meta);

		case 'repeater':
			$n = (int) sunqu_meta_get($ctx, $meta);
			if ($n === 0 && !empty($f['nulo'])) {
				return null;
			}
			$filas = [];
			for ($i = 0; $i < $n; $i++) {
				$filas[] = sunqu_leer_campos($ctx, $f['sub_fields'], $meta . '_' . $i);
			}
			return $filas;

		case 'lista':
			$n = (int) sunqu_meta_get($ctx, $meta);
			$filas = [];
			for ($i = 0; $i < $n; $i++) {
				$filas[] = sunqu_convertir($f['item'], sunqu_meta_get($ctx, $meta . '_' . $i . '_valor'));
			}
			return $filas;

		case 'meses':
			$out = [];
			foreach (SUNQU_MESES as $m) {
				$out[] = (int) sunqu_meta_get($ctx, $meta . '_' . $m);
			}
			return $out;

		case 'relacion':
			$ids = maybe_unserialize(sunqu_meta_get($ctx, $meta));
			$out = [];
			foreach ((array) $ids as $id) {
				$slug = $id ? get_post_field('post_name', (int) $id) : '';
				if ($slug) {
					$out[] = $slug;
				}
			}
			return $out;

		case 'checkbox':
			$v = maybe_unserialize(sunqu_meta_get($ctx, $meta));
			return array_values(array_filter(array_map('strval', (array) $v), 'strlen'));

		default:
			$v = sunqu_convertir($f['type'], sunqu_meta_get($ctx, $meta));
			if (!empty($f['nulo']) && $v === '') {
				return null;
			}
			return $v;
	}
}

/** Valor guardado → valor del JSON según el tipo. */
function sunqu_convertir(string $tipo, $raw) {
	switch ($tipo) {
		case 'number':
			if ($raw === '' || $raw === null) {
				return '';
			}
			return str_contains((string) $raw, '.') ? (float) $raw : (int) $raw;
		case 'true_false':
			return (bool) (int) $raw;
		case 'imagen':
		case 'archivo':
			return sunqu_url_media($raw);
		default:
			return is_scalar($raw) ? (string) $raw : '';
	}
}

/**
 * Imagen o archivo → URL. Si el valor es un ID de la biblioteca, devuelve su URL; si no (importación
 * sin imágenes), devuelve el texto tal cual: la web entiende rutas assets/…, URLs e ids de Unsplash.
 */
function sunqu_url_media($raw): string {
	if (is_numeric($raw)) {
		return (int) $raw > 0 ? (wp_get_attachment_url((int) $raw) ?: '') : '';
	}
	return is_scalar($raw) ? (string) $raw : '';
}

// ── Escritura (formato de content/*.json → WordPress) ────────────────────────────────────────

/**
 * Escribe una lista de campos. $ruta es la ruta del esquema (para las claves de ACF) y $media una
 * función que convierte una imagen del JSON en un ID de la biblioteca (o la deja como texto).
 */
function sunqu_escribir_campos(array $ctx, array $campos, array $datos, string $ruta, callable $media, callable $relacion, string $prefijo = ''): void {
	foreach ($campos as $f) {
		$meta = $prefijo === '' ? $f['name'] : $prefijo . '_' . $f['name'];
		sunqu_escribir_campo($ctx, $f, $meta, $ruta . '/' . $f['name'], $datos[$f['name']] ?? null, $media, $relacion);
	}
}

function sunqu_escribir_campo(array $ctx, array $f, string $meta, string $ruta, $v, callable $media, callable $relacion): void {
	$key = sunqu_fkey($ruta);
	switch ($f['type']) {
		case 'group':
			sunqu_meta_set($ctx, $meta, '', $key);
			sunqu_escribir_campos($ctx, $f['sub_fields'], is_array($v) ? $v : [], $ruta, $media, $relacion, $meta);
			return;

		case 'repeater':
			$filas = is_array($v) ? array_values($v) : [];
			sunqu_meta_set($ctx, $meta, count($filas), $key);
			foreach ($filas as $i => $fila) {
				sunqu_escribir_campos($ctx, $f['sub_fields'], is_array($fila) ? $fila : [], $ruta, $media, $relacion, $meta . '_' . $i);
			}
			return;

		case 'lista':
			$filas = is_array($v) ? array_values($v) : [];
			sunqu_meta_set($ctx, $meta, count($filas), $key);
			$sub = sunqu_fkey($ruta . '/valor');
			foreach ($filas as $i => $valor) {
				$guardar = $f['item'] === 'imagen' ? $media($valor) : $valor;
				sunqu_meta_set($ctx, $meta . '_' . $i . '_valor', $guardar, $sub);
			}
			return;

		case 'meses':
			sunqu_meta_set($ctx, $meta, '', $key);
			foreach (SUNQU_MESES as $i => $m) {
				sunqu_meta_set($ctx, $meta . '_' . $m, (string) (int) ($v[$i] ?? 0), sunqu_fkey($ruta . '/' . $m));
			}
			return;

		case 'relacion':
			$ids = [];
			foreach ((array) $v as $slug) {
				$id = $relacion($f['coleccion'], (string) $slug);
				if ($id) {
					$ids[] = (string) $id;
				}
			}
			sunqu_meta_set($ctx, $meta, $ids, $key);
			return;

		case 'checkbox':
			sunqu_meta_set($ctx, $meta, array_values(array_map('strval', (array) $v)), $key);
			return;

		case 'true_false':
			sunqu_meta_set($ctx, $meta, $v ? '1' : '0', $key);
			return;

		case 'imagen':
		case 'archivo':
			sunqu_meta_set($ctx, $meta, $v ? $media($v) : '', $key);
			return;

		default:
			sunqu_meta_set($ctx, $meta, $v === null ? '' : (is_scalar($v) ? (string) $v : ''), $key);
	}
}

// ── Exportación de secciones completas ───────────────────────────────────────────────────────

/** Sección completa con el formato de content/{seccion}.json (usa caché; se limpia al guardar). */
function sunqu_exportar(string $nombre, bool $cache = true): ?array {
	$sec = sunqu_seccion($nombre);
	if (!$sec) {
		return null;
	}
	$ck = 'sunqu_c_' . $nombre . '_' . (int) get_option('sunqu_cache_v', 1);
	if ($cache && ($hit = get_transient($ck)) !== false) {
		return $hit;
	}
	$out = ['_info' => $sec['info'] ?? ''];
	switch ($sec['tipo']) {
		case 'ajustes':
			$out += sunqu_leer_campos(['opcion', $sec['id']], $sec['campos']);
			break;

		case 'coleccion':
			$items = [];
			$q = get_posts([
				'post_type' => $sec['post_type'],
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
			]);
			foreach ($q as $p) {
				$item = [$sec['slug'] => $p->post_name, $sec['titulo_item'] => $p->post_title];
				$items[] = $item + sunqu_leer_campos(['post', $p->ID], $sec['campos']);
			}
			$out['items'] = $items;
			if (!empty($sec['ajustes']['campos'])) {
				$out += sunqu_leer_campos(['opcion', $sec['ajustes']['id']], $sec['ajustes']['campos']);
			}
			break;

		case 'entradas':
			$out += sunqu_exportar_diario($sec);
			break;
	}
	$out = apply_filters('sunqu_exportar_seccion', $out, $nombre);
	if ($cache) {
		set_transient($ck, $out, DAY_IN_SECONDS);
	}
	return $out;
}

/** Invalida la caché de la API (se llama al guardar cualquier contenido). */
function sunqu_limpiar_cache(): void {
	if (!empty($GLOBALS['sunqu_sin_cache'])) {
		return; // durante la importación se limpia una sola vez al final
	}
	update_option('sunqu_cache_v', (int) get_option('sunqu_cache_v', 1) + 1, false);
}
/** Tipos cuyo guardado cambia la API (entradas + colecciones; no los envíos de formularios). */
function sunqu_tipos_contenido(): array {
	$tipos = ['post', 'attachment'];
	foreach (sunqu_schema()['secciones'] as $sec) {
		if ($sec['tipo'] === 'coleccion') {
			$tipos[] = $sec['post_type'];
		}
	}
	return $tipos;
}
add_action('save_post', function ($id, $post) {
	if ($post && in_array($post->post_type, sunqu_tipos_contenido(), true)) {
		sunqu_limpiar_cache();
	}
}, 10, 2);
add_action('deleted_post', function ($id, $post = null) {
	if (!$post || in_array($post->post_type, sunqu_tipos_contenido(), true)) {
		sunqu_limpiar_cache();
	}
}, 10, 2);
add_action('acf/save_post', 'sunqu_limpiar_cache');
foreach (['created_category', 'edited_category', 'delete_category'] as $hook) {
	add_action($hook, 'sunqu_limpiar_cache');
}
add_action('updated_option', function ($opcion) {
	if (str_starts_with($opcion, 'sunqu_') && $opcion !== 'sunqu_cache_v') {
		sunqu_limpiar_cache();
	}
});
