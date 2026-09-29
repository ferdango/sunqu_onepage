<?php
/**
 * Contenido de la web: WordPress (con caché en disco) → caché vencida si WordPress falla → content/*.json.
 *
 * El formato es el mismo en los tres orígenes (el de content/*.json), así que las páginas no notan
 * la diferencia. La caché se vacía sola al vencer (CONTENT_CACHE_TTL) o cuando el plugin avisa al
 * publicar (POST /_sunqu/purge con CACHE_PURGE_TOKEN).
 */

declare(strict_types=1);

const SUNQU_SECCIONES = ['sitio', 'inicio', 'journeys', 'anfitriones', 'experiencias', 'resenas', 'productos', 'diario', 'legales'];

/** Secciones que usa cada página (igual que NEED en js/sunqu-content.js). */
const SUNQU_NECESITA = [
	'home' => ['sitio', 'inicio', 'journeys', 'anfitriones', 'diario'],
	'journey' => ['sitio', 'journeys', 'anfitriones'],
	'descubre' => ['sitio', 'journeys', 'experiencias'],
	'recuerda' => ['sitio', 'resenas', 'productos'],
	'blog' => ['sitio', 'diario'],
	'articulo' => ['sitio', 'diario'],
	'terminos' => ['sitio', 'legales'],
	'reclamaciones' => ['sitio'],
];

function sunqu_cache_dir(): string {
	$d = __DIR__ . '/../storage/cache';
	if (!is_dir($d)) {
		@mkdir($d, 0775, true);
	}
	return $d;
}

/** Escritura atómica (evita leer un archivo a medio escribir). */
function sunqu_write_atomic(string $file, string $data): void {
	$tmp = $file . '.' . getmypid() . '.tmp';
	if (@file_put_contents($tmp, $data) !== false) {
		@rename($tmp, $file);
	}
}

function sunqu_http_get(string $url, int $timeout = 5): ?string {
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
			CURLOPT_USERAGENT => 'SunquWeb/1.0',
		]);
		$body = curl_exec($ch);
		$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
	}
	$ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "Accept: application/json\r\nUser-Agent: SunquWeb/1.0\r\n", 'ignore_errors' => true]]);
	$body = @file_get_contents($url, false, $ctx);
	$status = isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m) ? (int) $m[1] : 0;
	return ($body !== false && $status >= 200 && $status < 300) ? $body : null;
}

/** content/*.json del repositorio. */
function sunqu_contenido_local(): array {
	$c = [];
	foreach (SUNQU_SECCIONES as $s) {
		$f = SUNQU_ROOT . "/content/$s.json";
		$c[$s] = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
	}
	return $c;
}

/**
 * Todo el contenido + de dónde salió: ['datos' => [...], 'origen' => …, 'edad' => s].
 *   wordpress      recién leído de WordPress        cache       caché vigente
 *   cache-vencida  WordPress no respondió o se está refrescando (se sirve la última copia)
 *   respaldo       WordPress falló y no hay caché: content/*.json
 *   local          sin WordPress configurado: content/*.json es la fuente
 */
function sunqu_contenido(): array {
	static $memo = null;
	if ($memo !== null) {
		return $memo;
	}
	$cfg = sunqu_config();
	if ($cfg['WORDPRESS_API_URL_INTERNAL'] === '') {
		return $memo = ['datos' => sunqu_contenido_local(), 'origen' => 'local', 'edad' => 0];
	}
	$file = sunqu_cache_dir() . '/contenido.json';
	$ttl = max(10, (int) $cfg['CONTENT_CACHE_TTL']);
	$edad = is_file($file) ? time() - (int) filemtime($file) : PHP_INT_MAX;
	if ($edad < $ttl) {
		$datos = json_decode((string) file_get_contents($file), true);
		if (is_array($datos)) {
			return $memo = ['datos' => $datos, 'origen' => 'cache', 'edad' => $edad];
		}
	}
	// Candado: si varias peticiones llegan con la caché vencida, solo una consulta a WordPress.
	// Si WordPress falló hace menos de un minuto no se vuelve a intentar (así una caída o un WordPress
	// lento no demora cada página: se sirve la caché vencida o content/*.json).
	$fallo = $file . '.fallo';
	$espera = is_file($fallo) && time() - (int) filemtime($fallo) < 60;
	$lock = $espera ? false : @fopen($file . '.lock', 'c');
	$tengo = $lock && flock($lock, LOCK_EX | LOCK_NB);
	if (!$espera && ($tengo || !is_file($file))) {
		$inicio = time();
		// ?_= evita que una caché intermedia (LiteSpeed Cache en el CMS, una CDN) devuelva una copia vieja
		$body = sunqu_http_get($cfg['WORDPRESS_API_URL_INTERNAL'] . '/sunqu/v1/contenido?_=' . $inicio);
		$datos = $body !== null ? json_decode($body, true) : null;
		if (is_array($datos) && isset($datos['sitio'], $datos['journeys'])) {
			sunqu_write_atomic($file, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			// Si llegó una purga mientras se descargaba, esta copia puede ser anterior al cambio: queda vencida
			clearstatcache();
			$purga = sunqu_cache_dir() . '/contenido.purga';
			if (is_file($purga) && (int) filemtime($purga) >= $inicio) {
				@touch($file, 1);
			}
			if ($lock) {
				flock($lock, LOCK_UN);
			}
			@unlink($fallo);
			return $memo = ['datos' => $datos, 'origen' => 'wordpress', 'edad' => 0];
		}
		@touch($fallo);
		error_log('[sunqu] WordPress no respondió con contenido válido; se usa la caché o content/*.json');
	}
	if ($lock) {
		if ($tengo) {
			flock($lock, LOCK_UN);
		}
		fclose($lock);
	}
	if (is_file($file)) {
		$datos = json_decode((string) file_get_contents($file), true);
		if (is_array($datos)) {
			return $memo = ['datos' => $datos, 'origen' => 'cache-vencida', 'edad' => $edad];
		}
	}
	return $memo = ['datos' => sunqu_contenido_local(), 'origen' => 'respaldo', 'edad' => 0];
}

/**
 * Vacía la caché de contenido (las páginas las cachea LiteSpeed: la purga envía X-LiteSpeed-Purge).
 * No borra la copia: la marca como vencida. Así la siguiente petición la renueva (una sola, con el candado)
 * y, si WordPress no responde, la web sigue con la última copia en lugar de volver a content/*.json.
 */
function sunqu_purgar(): int {
	$d = sunqu_cache_dir();
	@touch($d . '/contenido.purga');
	@unlink($d . '/contenido.json.fallo'); // al publicar se vuelve a intentar WordPress enseguida
	if (is_file($d . '/contenido.json')) {
		@touch($d . '/contenido.json', 1);
		return 1;
	}
	return 0;
}

/**
 * Contenido que se precarga en una página (solo sus secciones, sin cuerpos de artículos que no usa).
 */
function sunqu_contenido_pagina(string $pagina, array $params): array {
	$todo = sunqu_contenido()['datos'];
	$out = [];
	foreach (SUNQU_NECESITA[$pagina] ?? [] as $s) {
		$out[$s] = $todo[$s] ?? [];
	}
	if (isset($out['diario']['posts'])) {
		$actual = $pagina === 'articulo' ? ($params['a'] ?? '') : '';
		foreach ($out['diario']['posts'] as $i => $p) {
			if (($p['slug'] ?? '') !== $actual) {
				unset($out['diario']['posts'][$i]['sections']);
			}
		}
		$out['diario']['posts'] = array_values($out['diario']['posts']);
	}
	return $out;
}

/** Busca un elemento de una colección por clave (slug o id). */
function sunqu_buscar(array $items, string $clave, string $valor): ?array {
	foreach ($items as $it) {
		if ((string) ($it[$clave] ?? '') === $valor) {
			return $it;
		}
	}
	return null;
}
