<?php
/**
 * País aproximado del visitante, solo como respaldo para elegir el idioma en la raíz (/): se usa cuando no hay
 * preferencia guardada ni un idioma del navegador soportado (server/lib/idiomas.php › sunqu_idioma_inicial).
 *
 * Fuentes, en orden:
 *   1. una cabecera de país que agregue la CDN (Cloudflare CF-IPCountry, CloudFront, X-Country-Code…);
 *   2. el servicio configurado en el .env (GEOIP_URL, p. ej. IPinfo Lite), consultado desde el servidor: la clave
 *      nunca llega al navegador. Timeout corto (GEOIP_TIMEOUT_MS), caché de 30 días por red (/24 en IPv4, /48 en
 *      IPv6, guardada con hash y sin la IP) y pausa de 5 minutos si el servicio falla.
 * No hay base de datos de IPs propia. Sin cabecera ni GEOIP_URL devuelve '' y la web usa el idioma por defecto.
 */

declare(strict_types=1);

const SUNQU_GEO_TTL = 30 * 86400;       // país conocido
const SUNQU_GEO_TTL_NADA = 86400;       // el servicio no supo el país
const SUNQU_GEO_PAUSA = 300;            // tras un fallo del servicio, no se vuelve a consultar en 5 minutos

/** Código de país válido (ISO 3166-1 alfa-2) o ''. Descarta los códigos genéricos (XX, T1 de Tor, EU, AP…). */
function sunqu_pais_valido(string $p): string {
	$p = strtoupper(trim($p));
	return preg_match('/^[A-Z]{2}$/', $p) && !in_array($p, ['XX', 'ZZ', 'EU', 'AP', 'A1', 'A2', 'O1'], true) ? $p : '';
}

/** País que informa la CDN delante del sitio, si lo hace. */
function sunqu_pais_cabecera(): string {
	foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_VERCEL_IP_COUNTRY', 'GEOIP_COUNTRY_CODE', 'GEOIP2_COUNTRY_CODE'] as $k) {
		$v = sunqu_pais_valido((string) ($_SERVER[$k] ?? getenv($k) ?: ''));
		if ($v !== '') {
			return $v;
		}
	}
	return '';
}

/** IP pública del visitante ('' si es local o reservada). Detrás de la CDN de Hostinger, REMOTE_ADDR ya es la real. */
function sunqu_ip_visitante(): string {
	$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
	return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : '';
}

/** Clave de caché de la red de una IP (/24 o /48): un hash, nunca la IP. */
function sunqu_geo_clave(string $ip): string {
	$bin = (string) @inet_pton($ip);
	$red = strlen($bin) === 4 ? substr($bin, 0, 3) : substr($bin, 0, 6);
	return hash('sha256', 'sunqu-geo|' . $red . '|' . (sunqu_config()['CACHE_PURGE_TOKEN'] ?: SUNQU_ROOT));
}

function sunqu_geo_dir(): string {
	return __DIR__ . '/../storage/cache/geo';
}

/** ¿Se puede usar esta URL de servicio? https; http solo para un servicio interno (localhost o un nombre sin punto). */
function sunqu_geo_url_valida(string $url): bool {
	if (!str_contains($url, '{ip}') || !preg_match('#^(https?)://([^/:?]+)#i', $url, $m)) {
		return false;
	}
	return strtolower($m[1]) === 'https' || $m[2] === 'localhost' || !str_contains($m[2], '.');
}

/** Respuesta del servicio de geolocalización (cuerpo) o null si falla o tarda más de $ms milisegundos. */
function sunqu_geo_pedir(string $url, int $ms): ?string {
	if (!function_exists('curl_init')) {
		$ctx = stream_context_create(['http' => ['timeout' => max(1, $ms / 1000), 'follow_location' => 0, 'header' => "Accept: application/json, text/plain\r\nUser-Agent: SunquWeb/1.0\r\n"]]);
		$b = @file_get_contents($url, false, $ctx, 0, 65536);
		return is_string($b) ? $b : null;
	}
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
		CURLOPT_TIMEOUT_MS => $ms,
		CURLOPT_CONNECTTIMEOUT_MS => min($ms, 500),
		CURLOPT_NOSIGNAL => true,
		CURLOPT_HTTPHEADER => ['Accept: application/json, text/plain'],
		CURLOPT_USERAGENT => 'SunquWeb/1.0',
	]);
	$b = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	return is_string($b) && $code >= 200 && $code < 300 && strlen($b) <= 65536 ? $b : null;
}

/** País en la respuesta: el campo GEOIP_CAMPO del JSON (admite «country.iso_code») o, sin campo, el texto tal cual. */
function sunqu_geo_leer(string $cuerpo, string $campo): string {
	if ($campo === '') {
		return sunqu_pais_valido($cuerpo);
	}
	$v = json_decode($cuerpo, true);
	foreach (explode('.', $campo) as $k) {
		if (!is_array($v) || !array_key_exists($k, $v)) {
			return '';
		}
		$v = $v[$k];
	}
	return is_string($v) ? sunqu_pais_valido($v) : '';
}

/** País del visitante (ISO 3166-1 alfa-2) o '' si no se sabe. */
function sunqu_pais_visitante(): string {
	static $memo = null;
	if ($memo !== null) {
		return $memo;
	}
	$memo = sunqu_pais_cabecera();
	$cfg = sunqu_config();
	$url = $cfg['GEOIP_URL'];
	if ($memo !== '' || $url === '' || !sunqu_geo_url_valida($url) || ($ip = sunqu_ip_visitante()) === '') {
		return $memo;
	}
	$dir = sunqu_geo_dir();
	$clave = sunqu_geo_clave($ip);
	$archivo = $dir . '/' . substr($clave, 0, 2) . '/' . $clave;
	$guardado = is_file($archivo) ? trim((string) @file_get_contents($archivo)) : '';
	$edad = $guardado !== '' ? time() - (int) @filemtime($archivo) : PHP_INT_MAX;
	if ($guardado !== '' && $edad < ($guardado === '--' ? SUNQU_GEO_TTL_NADA : SUNQU_GEO_TTL)) {
		return $memo = $guardado === '--' ? '' : sunqu_pais_valido($guardado);
	}
	$pausa = $dir . '/.pausa';
	if (is_file($pausa) && time() - (int) @filemtime($pausa) < SUNQU_GEO_PAUSA) {
		return $memo;
	}
	$cuerpo = sunqu_geo_pedir(str_replace('{ip}', rawurlencode($ip), $url), max(200, min(3000, (int) $cfg['GEOIP_TIMEOUT_MS'])));
	if ($cuerpo === null) {
		@mkdir($dir, 0775, true);
		@touch($pausa);
		error_log('[sunqu] el servicio de geolocalización (GEOIP_URL) no respondió: pausa de 5 minutos');
		return $memo;
	}
	$memo = sunqu_geo_leer($cuerpo, $cfg['GEOIP_CAMPO']);
	@mkdir(dirname($archivo), 0775, true);
	@file_put_contents($archivo, $memo !== '' ? $memo : '--', LOCK_EX);
	// De vez en cuando, borra de una carpeta las entradas vencidas (la caché no crece sin límite)
	if (random_int(1, 200) === 1) {
		foreach (glob($dir . '/' . substr($clave, 2, 2) . '/*') ?: [] as $f) {
			if (time() - (int) @filemtime($f) > SUNQU_GEO_TTL) {
				@unlink($f);
			}
		}
	}
	return $memo;
}
