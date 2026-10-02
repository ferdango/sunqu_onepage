<?php
/**
 * Configuración central de producción.
 *
 * Se lee de un archivo .env (nunca se sube al repositorio). Orden de búsqueda:
 *   1. la ruta de la variable de entorno SUNQU_ENV_FILE;
 *   2. un nivel por encima de la raíz web (en Hostinger: ~/domains/sunqujourneys.com/.env);
 *   3. la raíz web (.env, bloqueado por .htaccess).
 * Las variables del entorno del servidor tienen prioridad sobre el archivo.
 */

declare(strict_types=1);

const SUNQU_ROOT = __DIR__ . '/../..';

/** Variables reconocidas y su valor por defecto. Vacío = «PENDIENTE DE CONFIGURACIÓN». */
const SUNQU_DEFAULTS = [
	'APP_ENV' => 'production',             // production | staging (staging = noindex)
	'SITE_URL' => '',                      // https://sunqujourneys.com
	'WORDPRESS_API_URL' => '',             // https://cms.sunqujourneys.com/wp-json
	'WORDPRESS_API_URL_INTERNAL' => '',    // opcional: URL que usa el servidor para leer WordPress (p. ej. en Docker)
	'GA4_ID' => '',
	'GTM_ID' => '',
	'META_PIXEL_ID' => '',
	'WHATSAPP_NUMBER' => '',               // respaldo si Sunqu › Sitio › WhatsApp está vacío
	'CONSENT_MODE' => 'banner',            // banner | off
	'CONTENT_CACHE_TTL' => '300',          // segundos
	'CACHE_PURGE_TOKEN' => '',             // lo usa el plugin para vaciar la caché al publicar
	'CSP_MODE' => 'enforce',               // enforce | report | off
	'CSP_INLINE_SCRIPTS' => 'hash',        // hash (seguro) | allow (solo si GTM usa etiquetas «HTML personalizado»)
	'CMS_SCRIPTS' => 'on',                 // on | off: insertar los scripts de WordPress › Sunqu › Scripts y seguimiento
	'TURNSTILE_SITE_KEY' => '',            // opcional: Cloudflare Turnstile en los formularios (con SUNQU_TURNSTILE_SECRET en WordPress)
	'GEOIP_URL' => '',                     // opcional: servicio país-por-IP para elegir el idioma en la raíz ({ip} se reemplaza)
	'GEOIP_CAMPO' => 'country',            // campo del JSON con el código de país (vacío = la respuesta es el código en texto)
	'GEOIP_TIMEOUT_MS' => '800',           // espera máxima del servicio, en milisegundos
];

function sunqu_env_file(): ?string {
	$candidatos = array_filter([
		getenv('SUNQU_ENV_FILE') ?: null,
		dirname(realpath(SUNQU_ROOT) ?: SUNQU_ROOT) . '/.env',
		SUNQU_ROOT . '/.env',
	]);
	foreach ($candidatos as $f) {
		if (is_file($f) && is_readable($f)) {
			return $f;
		}
	}
	return null;
}

/** Lee KEY=VALUE (admite comentarios # y comillas). */
function sunqu_parse_env(string $file): array {
	$out = [];
	foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
		$linea = trim($linea);
		if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
			continue;
		}
		[$k, $v] = array_map('trim', explode('=', $linea, 2));
		if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
			$v = substr($v, 1, -1);
		} else {
			$v = preg_replace('/\s+#.*$/', '', $v); // comentario al final de la línea
		}
		$out[$k] = $v;
	}
	return $out;
}

function sunqu_config(): array {
	static $cfg = null;
	if ($cfg !== null) {
		return $cfg;
	}
	$archivo = sunqu_env_file();
	$env = $archivo ? sunqu_parse_env($archivo) : [];
	$cfg = [];
	foreach (SUNQU_DEFAULTS as $k => $def) {
		$v = getenv($k);
		$cfg[$k] = ($v !== false && $v !== '') ? $v : ($env[$k] ?? $def);
	}
	// SITE_URL sin barra final. Si falta, se usa el host de la petición (solo para entornos locales): en producción
	// el host lo manda el visitante, así que sin SITE_URL los canonical y las redirecciones no son confiables.
	if ($cfg['SITE_URL'] === '') {
		$https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
		$host = preg_match('/^[a-z0-9.-]+(:\d+)?$/i', (string) ($_SERVER['HTTP_HOST'] ?? '')) ? $_SERVER['HTTP_HOST'] : 'localhost';
		$cfg['SITE_URL'] = ($https ? 'https' : 'http') . '://' . $host;
		$cfg['_site_url_pendiente'] = true;
		if ($cfg['APP_ENV'] === 'production') {
			error_log('[sunqu] falta SITE_URL en el .env de producción');
		}
	}
	if (!preg_match('/^[0-9A-Za-z_-]{10,100}$/', $cfg['TURNSTILE_SITE_KEY'])) {
		$cfg['TURNSTILE_SITE_KEY'] = '';
	}
	if (!preg_match('/^[A-Za-z0-9_.]{0,60}$/', $cfg['GEOIP_CAMPO'])) {
		$cfg['GEOIP_CAMPO'] = 'country';
	}
	if (!in_array($cfg['CSP_INLINE_SCRIPTS'], ['hash', 'allow'], true)) {
		$cfg['CSP_INLINE_SCRIPTS'] = 'hash';
	}
	$cfg['SITE_URL'] = rtrim($cfg['SITE_URL'], '/');
	$cfg['WORDPRESS_API_URL'] = rtrim($cfg['WORDPRESS_API_URL'], '/');
	$cfg['WORDPRESS_API_URL_INTERNAL'] = rtrim($cfg['WORDPRESS_API_URL_INTERNAL'] ?: $cfg['WORDPRESS_API_URL'], '/');
	$cfg['_env_file'] = $archivo;
	return $cfg;
}

function sunqu_es_staging(): bool {
	return sunqu_config()['APP_ENV'] !== 'production';
}

/** Origen (esquema + host) de una URL, p. ej. https://cms.sunqujourneys.com */
function sunqu_origen(string $url): string {
	$p = parse_url($url);
	if (!$p || empty($p['host'])) {
		return '';
	}
	return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
}
