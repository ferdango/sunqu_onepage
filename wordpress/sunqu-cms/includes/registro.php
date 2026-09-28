<?php
/**
 * Registro: tipos de contenido, páginas de ajustes (menú «Sunqu») y grupos de campos.
 *
 * Todo sale de schema.json. Los campos se registran con acf_add_local_field_group(), que existe en
 * ACF Pro y en Secure Custom Fields (gratis en wordpress.org). Sin ese plugin los datos y la API
 * funcionan igual, pero no se pueden editar desde el panel.
 */

defined('ABSPATH') || exit;

/** Tipos de contenido de las colecciones (journeys, anfitriones, experiencias, reseñas, productos). */
function sunqu_registrar_tipos(): void {
	foreach (sunqu_schema()['secciones'] as $nombre => $sec) {
		if ($sec['tipo'] !== 'coleccion') {
			continue;
		}
		register_post_type($sec['post_type'], [
			'labels' => [
				'name' => $sec['plural'],
				'singular_name' => $sec['singular'],
				'add_new' => 'Agregar',
				'add_new_item' => 'Agregar ' . mb_strtolower($sec['singular']),
				'edit_item' => 'Editar ' . mb_strtolower($sec['singular']),
				'all_items' => 'Todos',
				'search_items' => 'Buscar',
				'not_found' => 'Sin resultados',
			],
			'description' => $sec['info'] ?? '',
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'show_in_rest' => true, // editor y /wp/v2 (la web usa /sunqu/v1/contenido)
			'menu_icon' => $sec['icono'] ?? 'dashicons-admin-post',
			'menu_position' => 20 + (int) ($sec['orden'] ?? 0),
			'supports' => ['title', 'page-attributes', 'revisions'],
			'hierarchical' => false,
			'has_archive' => false,
			'rewrite' => false,
		]);
	}
	// El Diario usa las entradas nativas: cuerpo en el editor de bloques, extracto e imagen destacada.
	add_post_type_support('post', ['excerpt', 'thumbnail']);
}
add_action('init', 'sunqu_registrar_tipos');

/** Páginas de ajustes: Sunqu › Sitio, Inicio, Legales y los ajustes comunes de cada colección. */
function sunqu_paginas_ajustes(): array {
	$paginas = [];
	foreach (sunqu_schema()['secciones'] as $nombre => $sec) {
		if ($sec['tipo'] === 'ajustes') {
			$paginas[] = ['id' => $sec['id'], 'titulo' => $sec['titulo'], 'seccion' => $nombre, 'campos' => $sec['campos'], 'info' => $sec['info'] ?? ''];
		} elseif (!empty($sec['ajustes']['campos'])) {
			$paginas[] = ['id' => $sec['ajustes']['id'], 'titulo' => $sec['ajustes']['titulo'], 'seccion' => $nombre, 'campos' => $sec['ajustes']['campos'], 'info' => ''];
		}
	}
	return $paginas;
}

add_action('acf/init', function () {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}
	// Páginas de ajustes
	if (function_exists('acf_add_options_page')) {
		foreach (sunqu_paginas_ajustes() as $pg) {
			acf_add_options_page([
				'page_title' => 'Sunqu · ' . $pg['titulo'],
				'menu_title' => $pg['titulo'],
				'menu_slug' => $pg['id'],
				'post_id' => $pg['id'],
				'parent_slug' => 'sunqu',
				'capability' => 'edit_posts',
				'autoload' => false,
				'update_button' => 'Guardar',
				'updated_message' => 'Guardado. La web mostrará los cambios en la próxima carga.',
			]);
			acf_add_local_field_group([
				'key' => 'group_sq_' . $pg['id'],
				'title' => $pg['titulo'],
				'fields' => sunqu_acf_campos($pg['campos'], $pg['seccion'] . '/' . ($pg['id'])),
				'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => $pg['id']]]],
				'style' => 'seamless',
				'label_placement' => 'top',
				'description' => $pg['info'],
			]);
		}
	}
	// Colecciones: un grupo por tipo de contenido
	foreach (sunqu_schema()['secciones'] as $nombre => $sec) {
		if ($sec['tipo'] === 'coleccion') {
			acf_add_local_field_group([
				'key' => 'group_sq_' . $sec['post_type'],
				'title' => $sec['singular'],
				'fields' => sunqu_acf_campos($sec['campos'], $nombre . '/' . $sec['post_type']),
				'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => $sec['post_type']]]],
				'position' => 'normal',
				'style' => 'default',
				'label_placement' => 'top',
				'description' => $sec['info'] ?? '',
			]);
		} elseif ($sec['tipo'] === 'entradas') {
			acf_add_local_field_group([
				'key' => 'group_sq_diario',
				'title' => 'Diario Sunqu',
				'fields' => sunqu_acf_campos($sec['campos'], $nombre . '/post'),
				'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
				'position' => 'side',
				'label_placement' => 'top',
			]);
		}
	}
});

/** Campos del esquema → definición de ACF. $ruta debe coincidir con la que usa el motor de datos. */
function sunqu_acf_campos(array $campos, string $ruta): array {
	$out = [];
	foreach ($campos as $f) {
		$out[] = sunqu_acf_campo($f, $ruta . '/' . $f['name']);
	}
	return $out;
}

function sunqu_acf_campo(array $f, string $ruta): array {
	$base = [
		'key' => sunqu_fkey($ruta),
		'name' => $f['name'],
		'label' => $f['label'] ?? $f['name'],
		'instructions' => $f['ayuda'] ?? '',
	];
	switch ($f['type']) {
		case 'textarea':
			return $base + ['type' => 'textarea', 'rows' => 3, 'new_lines' => ''];
		case 'number':
			return $base + ['type' => 'number', 'step' => !empty($f['decimal']) ? 'any' : 1];
		case 'true_false':
			return $base + ['type' => 'true_false', 'ui' => 1];
		case 'imagen':
			return $base + ['type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all'];
		case 'archivo':
			return $base + ['type' => 'file', 'return_format' => 'id', 'mime_types' => 'mp4,webm,mov'];
		case 'color':
			return $base + ['type' => 'color_picker'];
		case 'select':
			return $base + ['type' => 'select', 'choices' => sunqu_opciones($f), 'allow_null' => 1, 'ui' => 0, 'return_format' => 'value'];
		case 'checkbox':
			return $base + ['type' => 'checkbox', 'choices' => sunqu_opciones($f), 'layout' => 'horizontal', 'return_format' => 'value'];
		case 'relacion':
			$sec = sunqu_seccion($f['coleccion']);
			return $base + ['type' => 'post_object', 'post_type' => [$sec['post_type']], 'multiple' => 1, 'return_format' => 'id', 'ui' => 1, 'allow_null' => 1];
		case 'group':
			return $base + ['type' => 'group', 'layout' => 'block', 'sub_fields' => sunqu_acf_campos($f['sub_fields'], $ruta)];
		case 'repeater':
			return $base + ['type' => 'repeater', 'layout' => 'block', 'button_label' => 'Agregar', 'collapsed' => sunqu_fkey($ruta . '/' . $f['sub_fields'][0]['name']), 'sub_fields' => sunqu_acf_campos($f['sub_fields'], $ruta)];
		case 'lista':
			$tipos = ['imagen' => 'imagen', 'number' => 'number', 'textarea' => 'textarea', 'text' => 'text'];
			$item = sunqu_acf_campo(['name' => 'valor', 'label' => 'Valor', 'type' => $tipos[$f['item']] ?? 'text'], $ruta . '/valor');
			return $base + ['type' => 'repeater', 'layout' => 'table', 'button_label' => 'Agregar', 'sub_fields' => [$item]];
		case 'meses':
			$sub = [];
			foreach (SUNQU_MESES as $m) {
				$sub[] = ['key' => sunqu_fkey($ruta . '/' . $m), 'name' => $m, 'label' => ucfirst($m), 'type' => 'select',
					'choices' => ['0' => 'Lluvias', '1' => 'Buena', '2' => 'Ideal'], 'default_value' => '0'];
			}
			return $base + ['type' => 'group', 'layout' => 'table', 'sub_fields' => $sub];
		default:
			return $base + ['type' => 'text'];
	}
}

/** Opciones de un select/checkbox: fijas en el esquema o tomadas del contenido («sitio:conceptos»). */
function sunqu_opciones(array $f): array {
	if (!empty($f['choices'])) {
		return $f['choices'];
	}
	if (empty($f['opciones_de'])) {
		return [];
	}
	[$seccion, $clave] = explode(':', $f['opciones_de']);
	$datos = sunqu_exportar($seccion);
	$out = [];
	foreach ((array) ($datos[$clave] ?? []) as $it) {
		$k = $it['slug'] ?? $it['id'] ?? '';
		if ($k !== '') {
			$out[$k] = isset($it['title']) ? $k . ' · ' . $it['title'] : ($it['label'] ?? $k);
		}
	}
	return $out;
}
