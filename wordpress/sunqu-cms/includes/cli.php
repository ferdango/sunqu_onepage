<?php
/**
 * WP-CLI:
 *   wp sunqu importar  --dir=<content> [--front=<raíz de la web>] [--sin-imagenes] [--solo=sitio,journeys]
 *   wp sunqu exportar  --dir=<carpeta>                                     (escribe content/*.json desde WordPress)
 *   wp sunqu verificar --dir=<content>                                     (compara WordPress con los JSON)
 */

defined('ABSPATH') || exit;

class Sunqu_CLI {
	private string $front = '';
	private bool $sin_imagenes = false;
	private bool $podar = false;
	private array $media_cache = [];

	/** Orden de importación: los anfitriones van antes que journeys e inicio (relaciones). */
	private const ORDEN = ['sitio', 'anfitriones', 'experiencias', 'journeys', 'resenas', 'productos', 'diario', 'legales', 'inicio'];

	/**
	 * Importa el contenido de content/*.json.
	 *
	 * ## OPTIONS
	 *
	 * --dir=<ruta>
	 * : Carpeta con los archivos JSON (content/).
	 *
	 * [--front=<ruta>]
	 * : Raíz de la web, para subir las imágenes locales (assets/…). Por defecto, la carpeta superior a --dir.
	 *
	 * [--sin-imagenes]
	 * : No sube imágenes a la biblioteca: guarda las referencias tal cual (más rápido; la web las entiende igual).
	 *
	 * [--solo=<secciones>]
	 * : Importa solo estas secciones, separadas por comas.
	 *
	 * [--podar]
	 * : Pasa a borrador lo publicado que ya no está en los JSON (journeys, anfitriones… y entradas del Diario).
	 *
	 * ## EXAMPLES
	 *
	 *     wp sunqu importar --dir=/var/www/front/content --front=/var/www/front
	 */
	public function importar($args, $assoc) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$dir = rtrim($assoc['dir'], '/');
		$this->front = rtrim($assoc['front'] ?? dirname($dir), '/');
		$this->sin_imagenes = !empty($assoc['sin-imagenes']);
		$this->podar = !empty($assoc['podar']);
		kses_remove_filters(); // el contenido viene de los JSON del proyecto: no filtrar títulos ni cuerpos
		$solo = !empty($assoc['solo']) ? array_map('trim', explode(',', $assoc['solo'])) : self::ORDEN;
		$GLOBALS['sunqu_sin_cache'] = true;

		foreach (self::ORDEN as $nombre) {
			if (!in_array($nombre, $solo, true)) {
				continue;
			}
			$file = "$dir/$nombre.json";
			if (!file_exists($file)) {
				WP_CLI::warning("No existe $file");
				continue;
			}
			$datos = json_decode(file_get_contents($file), true);
			if (!is_array($datos)) {
				WP_CLI::error("JSON inválido: $file");
			}
			$sec = sunqu_seccion($nombre);
			$n = $this->importar_seccion($nombre, $sec, $datos);
			WP_CLI::log(sprintf('  %-13s %s', $nombre, $n));
		}
		$GLOBALS['sunqu_sin_cache'] = false;
		sunqu_limpiar_cache();
		WP_CLI::success('Contenido importado.');
	}

	private function importar_seccion(string $nombre, array $sec, array $datos): string {
		$media = fn($v) => $this->media($v);
		$relacion = fn($coleccion, $slug) => $this->post_id(sunqu_seccion($coleccion)['post_type'], $slug);
		switch ($sec['tipo']) {
			case 'ajustes':
				sunqu_escribir_campos(['opcion', $sec['id']], $sec['campos'], $datos, $nombre . '/' . $sec['id'], $media, $relacion);
				return 'ajustes';

			case 'coleccion':
				$ids = [];
				foreach (array_values($datos['items'] ?? []) as $i => $item) {
					$slug = sanitize_title($item[$sec['slug']] ?? '');
					$id = $this->post_id($sec['post_type'], $slug);
					$post = ['post_type' => $sec['post_type'], 'post_status' => 'publish', 'post_title' => wp_slash((string) ($item[$sec['titulo_item']] ?? $slug)), 'post_name' => $slug, 'menu_order' => $i];
					$id = $id ? wp_update_post(['ID' => $id] + $post) : wp_insert_post($post);
					if (is_wp_error($id) || !$id) {
						WP_CLI::warning("No se pudo guardar $nombre/$slug");
						continue;
					}
					sunqu_escribir_campos(['post', $id], $sec['campos'], $item, $nombre . '/' . $sec['post_type'], $media, $relacion);
					$ids[] = $id;
				}
				$this->podar_resto($sec['post_type'], $ids);
				if (!empty($sec['ajustes']['campos'])) {
					sunqu_escribir_campos(['opcion', $sec['ajustes']['id']], $sec['ajustes']['campos'], $datos, $nombre . '/' . $sec['ajustes']['id'], $media, $relacion);
				}
				return count($ids) . ' elementos';

			case 'entradas':
				return $this->importar_diario($sec, $datos, $media, $relacion);
		}
		return '';
	}

	private function importar_diario(array $sec, array $datos, callable $media, callable $relacion): string {
		$term_ids = [];
		foreach (array_values($datos['categorias'] ?? []) as $i => $c) {
			$t = get_term_by('slug', $c['slug'], 'category');
			if ($t) {
				$tid = (int) $t->term_id;
				if (html_entity_decode($t->name, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $c['label']) {
					wp_update_term($tid, 'category', ['name' => $c['label']]);
				}
			} else {
				$nuevo = wp_insert_term($c['label'], 'category', ['slug' => $c['slug']]);
				if (is_wp_error($nuevo)) {
					WP_CLI::warning('No se pudo crear la categoría ' . $c['slug'] . ': ' . $nuevo->get_error_message());
					continue;
				}
				$tid = (int) $nuevo['term_id'];
			}
			update_term_meta($tid, 'sunqu_orden', $i);
			$term_ids[$c['slug']] = $tid;
		}
		$bloque_media = function ($v) {
			$id = $this->media($v);
			return is_numeric($id) && $id ? [(int) $id, wp_get_attachment_url((int) $id)] : [0, (string) $v];
		};
		$n = 0;
		$ids = [];
		foreach ($datos['posts'] ?? [] as $p) {
			$slug = sanitize_title($p['slug']);
			$id = $this->post_id('post', $slug);
			$fecha = ($p['date'] ?? wp_date('Y-m-d')) . ' 09:00:00';
			$post = [
				'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug,
				'post_title' => wp_slash($p['title'] ?? ''), 'post_excerpt' => wp_slash($p['excerpt'] ?? ''),
				'post_date' => $fecha, 'post_date_gmt' => get_gmt_from_date($fecha),
				'post_content' => wp_slash(sunqu_secciones_a_bloques($p['sections'] ?? [], $bloque_media)),
				'post_category' => isset($term_ids[$p['cat'] ?? '']) ? [$term_ids[$p['cat']]] : [],
			];
			$id = $id ? wp_update_post(['ID' => $id] + $post) : wp_insert_post($post);
			if (is_wp_error($id) || !$id) {
				WP_CLI::warning('No se pudo guardar el artículo ' . $slug);
				continue;
			}
			$img = $this->media($p['img'] ?? '');
			if (is_numeric($img) && $img) {
				set_post_thumbnail($id, (int) $img);
				delete_post_meta($id, '_sunqu_img');
			} else {
				delete_post_thumbnail($id);
				update_post_meta($id, '_sunqu_img', (string) ($p['img'] ?? ''));
			}
			sunqu_escribir_campos(['post', $id], $sec['campos'], $p, 'diario/post', $media, $relacion);
			$ids[] = (int) $id;
			$n++;
		}
		$this->podar_resto('post', $ids);
		return "$n artículos, " . count($term_ids) . ' categorías';
	}

	/** Con --podar: lo publicado de ese tipo que no vino en el JSON pasa a borrador (no se borra). */
	private function podar_resto(string $tipo, array $ids): void {
		if (!$this->podar) {
			return;
		}
		$viejos = get_posts(['post_type' => $tipo, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'post__not_in' => $ids ?: [0]]);
		foreach ($viejos as $viejo) {
			wp_update_post(['ID' => $viejo, 'post_status' => 'draft']);
			WP_CLI::log("    a borrador: $tipo #$viejo");
		}
	}

	/** ID de un post por tipo y slug (0 si no existe). */
	private function post_id(string $tipo, string $slug): int {
		if ($slug === '') {
			return 0;
		}
		$q = get_posts(['post_type' => $tipo, 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids']);
		return $q ? (int) $q[0] : 0;
	}

	/**
	 * Imagen o video del JSON → ID en la biblioteca. Acepta rutas locales (assets/…), URLs e ids de
	 * Unsplash. Cada archivo se sube una sola vez (meta _sunqu_fuente). Si falla, guarda el texto tal cual.
	 */
	private function media($v) {
		$v = is_scalar($v) ? (string) $v : '';
		if ($v === '' || $this->sin_imagenes) {
			return $v;
		}
		if (isset($this->media_cache[$v])) {
			return $this->media_cache[$v];
		}
		$prev = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_sunqu_fuente', 'meta_value' => $v, 'numberposts' => 1, 'fields' => 'ids']);
		if ($prev) {
			return $this->media_cache[$v] = (int) $prev[0];
		}
		if (str_starts_with($v, 'assets/')) {
			$src = $this->front . '/' . $v;
			if (!file_exists($src)) {
				WP_CLI::warning("No existe $src");
				return $v;
			}
			$tmp = wp_tempnam(basename($src));
			copy($src, $tmp);
			$name = basename($src);
		} elseif (preg_match('#^https?://#', $v)) {
			$tmp = download_url($v, 60);
			$name = basename((string) parse_url($v, PHP_URL_PATH)) ?: 'imagen.jpg';
		} else {
			$tmp = download_url('https://images.unsplash.com/photo-' . rawurlencode($v) . '?auto=format&fit=crop&w=2000&q=80&fm=jpg', 60);
			$name = 'unsplash-' . sanitize_file_name($v) . '.jpg';
		}
		if (is_wp_error($tmp)) {
			WP_CLI::warning("No se pudo descargar $v: " . $tmp->get_error_message());
			return $v;
		}
		$id = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], 0);
		if (is_wp_error($id)) {
			@unlink($tmp);
			WP_CLI::warning("No se pudo subir $v: " . $id->get_error_message());
			return $v;
		}
		update_post_meta($id, '_sunqu_fuente', $v);
		return $this->media_cache[$v] = (int) $id;
	}

	/**
	 * Escribe content/*.json con el contenido de WordPress.
	 *
	 * ## OPTIONS
	 *
	 * --dir=<ruta>
	 * : Carpeta de destino.
	 */
	public function exportar($args, $assoc) {
		$dir = rtrim($assoc['dir'], '/');
		wp_mkdir_p($dir);
		foreach (sunqu_secciones() as $s) {
			// Las imágenes de la biblioteca se escriben con su origen (assets/…, id de Unsplash): el JSON sirve igual sin WordPress.
			$json = wp_json_encode($this->normalizar(sunqu_exportar($s, false)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$json = preg_replace_callback('/^( +)/m', fn($m) => str_repeat(' ', intdiv(strlen($m[1]), 2)), $json);
			file_put_contents("$dir/$s.json", $json . "\n");
			WP_CLI::log("  $dir/$s.json");
		}
		WP_CLI::success('Contenido exportado.');
	}

	/**
	 * Compara lo que devuelve la API con content/*.json (las imágenes subidas se comparan por su origen).
	 *
	 * ## OPTIONS
	 *
	 * --dir=<ruta>
	 * : Carpeta con los JSON de referencia.
	 */
	public function verificar($args, $assoc) {
		$dir = rtrim($assoc['dir'], '/');
		$errores = 0;
		foreach (sunqu_secciones() as $s) {
			$esperado = json_decode((string) @file_get_contents("$dir/$s.json"), true);
			$real = $this->normalizar(sunqu_exportar($s, false));
			$dif = [];
			$this->comparar($this->ordenar($esperado), $this->ordenar($real), $s, $dif);
			if ($dif) {
				$errores += count($dif);
				WP_CLI::warning("$s: " . count($dif) . ' diferencias');
				foreach (array_slice($dif, 0, 15) as $d) {
					WP_CLI::log('    ' . $d);
				}
			} else {
				WP_CLI::log("  $s: igual");
			}
		}
		$errores ? WP_CLI::error("$errores diferencias") : WP_CLI::success('WordPress devuelve exactamente el contenido de los JSON.');
	}

	/** URLs de la biblioteca → su origen (_sunqu_fuente), para comparar con el JSON. */
	private function normalizar($v) {
		if (is_array($v)) {
			return array_map(fn($x) => $this->normalizar($x), $v);
		}
		if (is_string($v) && preg_match('#/wp-content/uploads/#', $v)) {
			$id = attachment_url_to_postid($v);
			$src = $id ? get_post_meta($id, '_sunqu_fuente', true) : '';
			return $src ?: $v;
		}
		return $v;
	}

	private function ordenar($v) {
		if (!is_array($v)) {
			return $v;
		}
		$v = array_map(fn($x) => $this->ordenar($x), $v);
		if (array_keys($v) !== range(0, count($v) - 1)) {
			ksort($v);
		}
		return $v;
	}

	private function comparar($a, $b, string $ruta, array &$dif): void {
		if (is_array($a) && is_array($b)) {
			foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
				if (!array_key_exists($k, $a)) {
					$dif[] = "$ruta.$k: sobra en WordPress";
				} elseif (!array_key_exists($k, $b)) {
					$dif[] = "$ruta.$k: falta en WordPress";
				} else {
					$this->comparar($a[$k], $b[$k], "$ruta.$k", $dif);
				}
			}
			return;
		}
		if ($a !== $b) {
			$dif[] = "$ruta: esperado " . wp_json_encode($a, JSON_UNESCAPED_UNICODE) . ' · WordPress ' . wp_json_encode($b, JSON_UNESCAPED_UNICODE);
		}
	}
}

WP_CLI::add_command('sunqu', 'Sunqu_CLI');
