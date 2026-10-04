<?php
/**
 * Idiomas de la web: cada uno con su URL (/es/…, /en/…) y la raíz (/) que elige el idioma de quien entra.
 *
 * El contenido se escribe en español en WordPress. Los idiomas activos (WordPress › Sunqu › Idiomas) se traducen con
 * Loco Translate:
 *   · el contenido llega traducido de la API (?lang=en);
 *   · los textos fijos de las plantillas, de js/ y del servidor llegan en sitio._i18n ([original => traducción]) y
 *     se reemplazan aquí, al armar la página: texto entre etiquetas, atributos alt/aria-label/placeholder/title y
 *     frases entre comillas del código de la plantilla (las mismas que extrae tools/extraer-textos.py).
 * Lo que no está traducido se muestra en español. Un idioma sin «Publicar en buscadores» lleva noindex y no entra
 * al sitemap ni a los hreflang.
 *
 * Idioma de cada visita (sunqu_idioma_inicial), solo en la raíz:
 *   1. el idioma de la URL siempre manda (/es/… se ve en español aunque la preferencia guardada sea otra);
 *   2. la preferencia guardada (cookie sunqu_language, la escribe el selector de idioma);
 *   3. el idioma del navegador (Accept-Language), si está activo;
 *   4. el país aproximado por IP (server/lib/geo.php), con el mapa SUNQU_PAISES_IDIOMA;
 *   5. el inglés (SUNQU_IDIOMA_DEFECTO), o el español si el inglés no está activo.
 * Un buscador (sunqu_es_bot) va siempre a un idioma con «Publicar en buscadores»: el de su Accept-Language si se
 * indexa; si no, sunqu_idioma_defecto_indexable() (el español mientras el inglés no se publique).
 */

declare(strict_types=1);

/** Idioma en que se escribe el contenido: siempre activo y respaldo de toda traducción que falte. */
const SUNQU_IDIOMA_BASE = 'es';
/** Idioma para quien no tiene preferencia ni un idioma del navegador soportado (Sunqu recibe viajeros de todo el mundo). */
const SUNQU_IDIOMA_DEFECTO = 'en';
/** Idiomas que conoce la web: código => locale por defecto (og:locale). Se activan en WordPress › Sunqu › Idiomas. */
const SUNQU_IDIOMAS_WEB = ['es' => 'es_PE', 'en' => 'en_US', 'pt' => 'pt_BR', 'fr' => 'fr_FR', 'de' => 'de_DE', 'it' => 'it_IT', 'ru' => 'ru_RU'];
/** Cookie con el idioma que eligió la persona en el selector (solo el código: «en»). */
const SUNQU_COOKIE_IDIOMA = 'sunqu_language';
/**
 * País → idioma, solo como respaldo por IP. Español: Sudamérica, Centroamérica, España, México y el Caribe
 * hispanohablante. Portugués: Brasil. Ruso: Rusia. El resto (Norteamérica, Europa, Asia, Oceanía, África) → inglés.
 * Sunqu › Idiomas › «Idioma por país» agrega excepciones (FR=fr).
 */
const SUNQU_PAISES_IDIOMA = [
	'es' => ['AR', 'BO', 'CL', 'CO', 'EC', 'GY', 'PY', 'PE', 'SR', 'UY', 'VE', 'BZ', 'CR', 'SV', 'GT', 'HN', 'NI', 'PA', 'ES', 'MX', 'CU', 'DO', 'PR'],
	'pt' => ['BR'],
	'ru' => ['RU'],
];

/** Configuración de idiomas que publica WordPress (sitio._idiomas). */
function sunqu_idiomas_config(): array {
	$cfg = sunqu_contenido(SUNQU_IDIOMA_BASE)['datos']['sitio']['_idiomas'] ?? [];
	return is_array($cfg) ? $cfg : [];
}

/** Idiomas activos: ['es' => ['locale' => 'es_PE', 'indexar' => true], 'en' => [...]] (configuración de WordPress). */
function sunqu_idiomas_web(): array {
	static $memo = null;
	if ($memo !== null) {
		return $memo;
	}
	$out = [SUNQU_IDIOMA_BASE => ['locale' => SUNQU_IDIOMAS_WEB[SUNQU_IDIOMA_BASE], 'indexar' => true]];
	foreach ((array) (sunqu_idiomas_config()['activos'] ?? []) as $code => $a) {
		if (isset(SUNQU_IDIOMAS_WEB[$code]) && $code !== SUNQU_IDIOMA_BASE && is_array($a)) {
			$locale = (string) ($a['locale'] ?? '');
			$out[$code] = ['locale' => preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) ? $locale : SUNQU_IDIOMAS_WEB[$code], 'indexar' => !empty($a['indexar'])];
		}
	}
	return $memo = $out;
}

/** ¿Se muestra el selector de idioma? (activado en WordPress y con más de un idioma). */
function sunqu_selector_idiomas(): bool {
	$cfg = sunqu_idiomas_config();
	return count(sunqu_idiomas_web()) > 1 && (!array_key_exists('selector', $cfg) || !empty($cfg['selector']));
}

/** Idiomas que se indexan (para hreflang y el sitemap). */
function sunqu_idiomas_indexables(): array {
	return array_keys(array_filter(sunqu_idiomas_web(), fn($a) => $a['indexar']));
}

/** Idioma por defecto (inglés si está activo; si no, el español). */
function sunqu_idioma_defecto(): string {
	return isset(sunqu_idiomas_web()[SUNQU_IDIOMA_DEFECTO]) ? SUNQU_IDIOMA_DEFECTO : SUNQU_IDIOMA_BASE;
}

/** Idioma de x-default en los hreflang: el por defecto si se indexa; si no, el español. */
function sunqu_idioma_defecto_indexable(): string {
	return in_array(SUNQU_IDIOMA_DEFECTO, sunqu_idiomas_indexables(), true) ? SUNQU_IDIOMA_DEFECTO : SUNQU_IDIOMA_BASE;
}

function sunqu_lang(): string {
	return (string) ($GLOBALS['sunqu_lang'] ?? SUNQU_IDIOMA_BASE);
}

/** Prefijo de URL de un idioma: '/es', '/en'. */
function sunqu_prefijo(?string $lang = null): string {
	return '/' . ($lang ?? sunqu_lang());
}

/** Ruta pública en un idioma: '/' → '/en/', '/descubre' → '/en/descubre'. */
function sunqu_ruta_idioma(string $ruta, ?string $lang = null): string {
	$p = sunqu_prefijo($lang);
	return ($ruta === '' || $ruta === '/') ? $p . '/' : $p . $ruta;
}

/** Idioma del navegador (Accept-Language) que está activo, en el orden de preferencia de la persona; '' si ninguno. */
function sunqu_idioma_navegador(string $cabecera): string {
	$items = [];
	foreach (array_slice(explode(',', $cabecera), 0, 20) as $i => $parte) {
		$p = array_map('trim', explode(';', $parte));
		$tag = strtolower($p[0]);
		if (!preg_match('/^[a-z]{2,3}(-[a-z0-9]{1,8})*$/', $tag)) {
			continue;
		}
		$q = 1.0;
		foreach (array_slice($p, 1) as $x) {
			if (preg_match('/^q=([01](\.\d{1,3})?)$/', $x, $m)) {
				$q = (float) $m[1];
			}
		}
		if ($q > 0) {
			$items[] = [$q, -$i, explode('-', $tag)[0]]; // en-US → en
		}
	}
	rsort($items); // mayor q primero; a igual q, el orden en que llegaron
	foreach ($items as [, , $base]) {
		if (isset(sunqu_idiomas_web()[$base])) {
			return $base;
		}
	}
	return '';
}

/** Idioma que corresponde a un país (mapa + excepciones de Sunqu › Idiomas); 'en' si no está en el mapa. */
function sunqu_idioma_pais(string $pais): string {
	$excepciones = (array) (sunqu_idiomas_config()['paises'] ?? []);
	if (isset($excepciones[$pais]) && isset(SUNQU_IDIOMAS_WEB[$excepciones[$pais]])) {
		return (string) $excepciones[$pais];
	}
	foreach (SUNQU_PAISES_IDIOMA as $lang => $paises) {
		if (in_array($pais, $paises, true)) {
			return $lang;
		}
	}
	return SUNQU_IDIOMA_DEFECTO;
}

/** ¿Es un buscador o una herramienta automática? (no se les consulta el país por IP). */
function sunqu_es_bot(): bool {
	return (bool) preg_match('/bot|crawl|spider|slurp|inspectiontool|google-site-verification|google-extended|yandex|duckduck|bingpreview|facebookexternalhit|embedly|lighthouse|pagespeed|headless|curl|wget|python|java\//i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * Idioma para quien entra a la raíz (/): [código, fuente]. La fuente (saved_preference, browser, ip, default) va al
 * evento language_detected de la medición.
 */
function sunqu_idioma_inicial(): array {
	$web = sunqu_idiomas_web();
	// Un buscador (sin cookie ni país) va siempre a un idioma que se indexa: si no, la raíz del dominio terminaría en
	// una página con noindex (p. ej. /en/ sin «Publicar en buscadores») y Google no indexaría la portada
	if (sunqu_es_bot()) {
		$navegador = sunqu_idioma_navegador((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
		return [in_array($navegador, sunqu_idiomas_indexables(), true) ? $navegador : sunqu_idioma_defecto_indexable(), 'default'];
	}
	$guardado = (string) ($_COOKIE[SUNQU_COOKIE_IDIOMA] ?? '');
	if (isset($web[$guardado])) {
		return [$guardado, 'saved_preference'];
	}
	$navegador = sunqu_idioma_navegador((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
	if ($navegador !== '') {
		return [$navegador, 'browser'];
	}
	$pais = sunqu_pais_visitante();
	$porPais = $pais !== '' ? sunqu_idioma_pais($pais) : '';
	if (isset($web[$porPais])) {
		return [$porPais, 'ip'];
	}
	return [sunqu_idioma_defecto(), 'default'];
}

/** Textos fijos traducidos del idioma de la página ([original => traducción]); vacío en español. */
function sunqu_dic(): array {
	return (array) ($GLOBALS['sunqu_dic'] ?? []);
}

/** Texto fijo del servidor traducido; con %s / %1$s se reemplazan $args (si la traducción está mal formada, va el original). */
function sunqu_t(string $s, string ...$args): string {
	$t = sunqu_dic()[$s] ?? $s;
	if (!$args) {
		return $t;
	}
	try {
		return vsprintf($t, $args);
	} catch (Throwable $e) {
		return vsprintf($s, $args);
	}
}

/** Texto de un nodo como clave del diccionario: sin entidades y con los espacios colapsados (igual que tools/extraer-textos.py). */
function sunqu_i18n_clave(string $s): string {
	$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
}

/** Frases entre comillas del código de una plantilla traducidas (solo las que están en el diccionario). */
function sunqu_traducir_js(string $js, array $dic): string {
	return preg_replace_callback('/\'((?:[^\'\\\\\n]|\\\\.)*)\'|"((?:[^"\\\\\n]|\\\\.)*)"/', function ($m) use ($dic) {
		$q = $m[0][0];
		$crudo = $q === "'" ? $m[1] : ($m[2] ?? '');
		$texto = str_replace("\x00", '\\', str_replace('\\' . $q, $q, str_replace('\\\\', "\x00", $crudo)));
		if (!isset($dic[$texto])) {
			return $m[0];
		}
		return $q . str_replace(['\\', $q, "\r", "\n", '</'], ['\\\\', '\\' . $q, '', '\n', '<\/'], (string) $dic[$texto]) . $q;
	}, $js) ?? $js;
}

/**
 * Traduce el <body> de una plantilla: texto entre etiquetas, atributos alt/aria-label/placeholder/title y las frases
 * del código <script type="text/x-dc">. No toca otros <script>, <style> ni <noscript> (el contenido sin JavaScript
 * ya sale traducido).
 */
function sunqu_traducir_html(string $html, array $dic): string {
	if (!$dic) {
		return $html;
	}
	$i = stripos($html, '<body');
	if ($i === false) {
		return $html;
	}
	$cuerpo = preg_replace_callback('#<(script|style|noscript)\b([^>]*)>.*?</\1>|<[^>]+>|[^<]+#is', function ($m) use ($dic) {
		$tok = $m[0];
		if (isset($m[1]) && $m[1] !== '') {
			if (strtolower($m[1]) === 'script' && preg_match('/\stype="text\/x-dc"/', $m[2] ?? '')) {
				$a = strpos($tok, '>') + 1;
				$b = strrpos($tok, '</');
				return substr($tok, 0, $a) . sunqu_traducir_js(substr($tok, $a, $b - $a), $dic) . substr($tok, $b);
			}
			return $tok;
		}
		if ($tok[0] === '<') {
			return preg_replace_callback('#(\s(?:alt|aria-label|placeholder|title)=")([^"]*)(")#', function ($a) use ($dic) {
				$k = sunqu_i18n_clave($a[2]);
				return isset($dic[$k]) && !str_contains($a[2], '{{') ? $a[1] . htmlspecialchars((string) $dic[$k], ENT_QUOTES | ENT_HTML5, 'UTF-8') . $a[3] : $a[0];
			}, $tok) ?? $tok;
		}
		$k = sunqu_i18n_clave($tok);
		if ($k === '' || !isset($dic[$k])) {
			return $tok;
		}
		preg_match('/^(\s*).*?(\s*)$/su', $tok, $e);
		return ($e[1] ?? '') . htmlspecialchars((string) $dic[$k], ENT_NOQUOTES | ENT_HTML5, 'UTF-8') . ($e[2] ?? '');
	}, substr($html, $i)) ?? substr($html, $i);
	return substr($html, 0, $i) . $cuerpo;
}

/**
 * Lo que recibe el navegador para los idiomas (window.SUNQU_I18N): idioma, prefijo, URL de la misma página en cada
 * idioma activo, si se muestra el selector y los textos de js/ traducidos.
 */
function sunqu_i18n_publico(string $rutaBase): array {
	$alternos = [];
	foreach (array_keys(sunqu_idiomas_web()) as $l) {
		$alternos[$l] = sunqu_ruta_idioma($rutaBase, $l);
	}
	// Del diccionario, solo lo que usan los archivos de js/ (lo demás ya está traducido en el HTML)
	$js = '';
	foreach (['js/sunqu-content.js', 'js/sunqu-analytics.js', 'js/sunqu-campos.js'] as $f) {
		$js .= (string) @file_get_contents(SUNQU_ROOT . '/' . $f);
	}
	$dic = [];
	foreach (sunqu_dic() as $k => $v) {
		if (str_contains($js, "'" . str_replace("'", "\\'", (string) $k) . "'")) {
			$dic[$k] = $v;
		}
	}
	return ['lang' => sunqu_lang(), 'base' => SUNQU_IDIOMA_BASE, 'prefijo' => sunqu_prefijo(), 'alternos' => $alternos, 'selector' => sunqu_selector_idiomas(), 'dic' => (object) $dic];
}
