<?php
/**
 * Importador content/*.json → WordPress. Lo usan `wp sunqu importar` (cli.php) y el botón «Importar el contenido»
 * de Sunqu › Conexión y herramientas (admin.php).
 *
 * Es repetible: cada journey, experiencia, anfitrión, reseña, producto y artículo se busca por su slug y se
 * actualiza; cada imagen se sube una sola vez a la biblioteca (meta _sunqu_fuente con su origen).
 */

defined('ABSPATH') || exit;

class Sunqu_Importador {
	/** Orden de importación: los anfitriones van antes que journeys e inicio (relaciones). */
	public const ORDEN = ['sitio', 'anfitriones', 'experiencias', 'journeys', 'resenas', 'productos', 'diario', 'legales', 'inicio'];

	/** Imágenes que no se pudieron subir y otros avisos de la importación. */
	public array $avisos = [];
	/** Lo que se pasó a borrador (con $podar). */
	public array $notas = [];
	private array $media_cache = [];

	/**
	 * @param string        $front        carpeta donde están las imágenes locales del contenido (assets/…)
	 * @param bool          $sin_imagenes guarda las referencias tal cual, sin subirlas a la biblioteca (más rápido)
	 * @param bool          $podar        pasa a borrador lo publicado que no viene en el JSON (no borra nada)
	 * @param callable|null $log          fn(string $tipo 'aviso'|'nota', string $mensaje): muestra el avance (WP-CLI)
	 */
	public function __construct(private string $front, private bool $sin_imagenes = false, private bool $podar = false, private $log = null) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$this->front = rtrim($front, '/');
	}

	/** Importa una sección con su JSON ya leído. Devuelve un resumen («5 elementos», «ajustes»…). */
	public function seccion(string $nombre, array $datos): string {
		$sec = sunqu_seccion($nombre);
		if (!$sec) {
			$this->aviso("Sección desconocida: $nombre");
			return '';
		}
		kses_remove_filters(); // el contenido viene de los JSON del proyecto: no filtrar títulos ni cuerpos
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
						$this->aviso("No se pudo guardar $nombre/$slug");
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
				return $this->diario($sec, $datos, $media, $relacion);
		}
		return '';
	}

	private function diario(array $sec, array $datos, callable $media, callable $relacion): string {
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
					$this->aviso('No se pudo crear la categoría ' . $c['slug'] . ': ' . $nuevo->get_error_message());
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
				'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug, 'comment_status' => 'closed', 'ping_status' => 'closed',
				'post_title' => wp_slash($p['title'] ?? ''), 'post_excerpt' => wp_slash($p['excerpt'] ?? ''),
				'post_date' => $fecha, 'post_date_gmt' => get_gmt_from_date($fecha),
				'post_content' => wp_slash(sunqu_secciones_a_bloques($p['sections'] ?? [], $bloque_media)),
				'post_category' => isset($term_ids[$p['cat'] ?? '']) ? [$term_ids[$p['cat']]] : [],
			];
			$id = $id ? wp_update_post(['ID' => $id] + $post) : wp_insert_post($post);
			if (is_wp_error($id) || !$id) {
				$this->aviso('No se pudo guardar el artículo ' . $slug);
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

	/** Con $podar: lo publicado de ese tipo que no vino en el JSON pasa a borrador (no se borra). */
	private function podar_resto(string $tipo, array $ids): void {
		if (!$this->podar) {
			return;
		}
		$viejos = get_posts(['post_type' => $tipo, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids', 'post__not_in' => $ids ?: [0]]);
		foreach ($viejos as $viejo) {
			wp_update_post(['ID' => $viejo, 'post_status' => 'draft']);
			$this->nota('a borrador: «' . get_the_title($viejo) . "» ($tipo #$viejo)");
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
				$this->aviso("No existe $src");
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
			$this->aviso("No se pudo descargar $v: " . $tmp->get_error_message());
			return $v;
		}
		$id = media_handle_sideload(['name' => $name, 'tmp_name' => $tmp], 0);
		if (is_wp_error($id)) {
			@unlink($tmp);
			$this->aviso("No se pudo subir $v: " . $id->get_error_message());
			return $v;
		}
		update_post_meta($id, '_sunqu_fuente', $v);
		return $this->media_cache[$v] = (int) $id;
	}

	private function aviso(string $m): void {
		$this->avisos[] = $m;
		if ($this->log) {
			($this->log)('aviso', $m);
		}
	}

	private function nota(string $m): void {
		$this->notas[] = $m;
		if ($this->log) {
			($this->log)('nota', $m);
		}
	}
}
