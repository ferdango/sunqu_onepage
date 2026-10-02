<?php
/**
 * WP-CLI:
 *   wp sunqu importar  --dir=<content> [--front=<raíz de la web>] [--sin-imagenes] [--solo=sitio,journeys]
 *   wp sunqu exportar  --dir=<carpeta>                                     (escribe content/*.json desde WordPress)
 *   wp sunqu verificar --dir=<content>                                     (compara WordPress con los JSON)
 */

defined('ABSPATH') || exit;

class Sunqu_CLI {
	/**
	 * Importa el contenido de content/*.json (la lógica está en includes/importador.php).
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
		$dir = rtrim($assoc['dir'], '/');
		$imp = new Sunqu_Importador($assoc['front'] ?? dirname($dir), !empty($assoc['sin-imagenes']), !empty($assoc['podar']),
			fn($tipo, $m) => $tipo === 'aviso' ? WP_CLI::warning($m) : WP_CLI::log('    ' . $m));
		$solo = !empty($assoc['solo']) ? array_map('trim', explode(',', $assoc['solo'])) : Sunqu_Importador::ORDEN;
		$GLOBALS['sunqu_sin_cache'] = true;
		foreach (Sunqu_Importador::ORDEN as $nombre) {
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
			WP_CLI::log(sprintf('  %-13s %s', $nombre, $imp->seccion($nombre, $datos)));
		}
		$GLOBALS['sunqu_sin_cache'] = false;
		sunqu_limpiar_cache();
		WP_CLI::success('Contenido importado.');
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
			unset($v['_imagenes'], $v['_modificado'], $v['_idiomas'], $v['_i18n'], $v['_scripts']); // solo existen en WordPress
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
