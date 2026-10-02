<?php
/**
 * Idiomas de la web.
 *
 * El español es la base: sus URLs no llevan prefijo. Los idiomas activos en WordPress (Sunqu › Idiomas) se sirven en
 * /en/… y /pt/…, con las traducciones hechas en Loco Translate:
 *   · el contenido llega traducido de la API (?lang=en);
 *   · los textos fijos de las plantillas, de js/ y del servidor llegan en sitio._i18n ([original => traducción]) y
 *     se reemplazan aquí, al armar la página: texto entre etiquetas, atributos alt/aria-label/placeholder/title y
 *     frases entre comillas del código de la plantilla (las mismas que extrae tools/extraer-textos.py).
 * Lo que no está traducido se muestra en español. Un idioma sin «Publicar en buscadores» lleva noindex y no entra
 * al sitemap ni a los hreflang.
 */

declare(strict_types=1);

const SUNQU_IDIOMA_BASE = 'es';
/** Idiomas que conoce la web: código => [locale por defecto, og:locale por defecto]. */
const SUNQU_IDIOMAS_WEB = ['es' => 'es_PE', 'en' => 'en_US', 'pt' => 'pt_BR'];

/** Idiomas activos: ['es' => ['locale' => 'es_PE', 'indexar' => true], 'en' => [...]] (configuración de WordPress). */
function sunqu_idiomas_web(): array {
	static $memo = null;
	if ($memo !== null) {
		return $memo;
	}
	$cfg = sunqu_contenido(SUNQU_IDIOMA_BASE)['datos']['sitio']['_idiomas'] ?? [];
	$out = [SUNQU_IDIOMA_BASE => ['locale' => SUNQU_IDIOMAS_WEB[SUNQU_IDIOMA_BASE], 'indexar' => true]];
	foreach ((array) ($cfg['activos'] ?? []) as $code => $a) {
		if (isset(SUNQU_IDIOMAS_WEB[$code]) && $code !== SUNQU_IDIOMA_BASE && is_array($a)) {
			$locale = (string) ($a['locale'] ?? '');
			$out[$code] = ['locale' => preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale) ? $locale : SUNQU_IDIOMAS_WEB[$code], 'indexar' => !empty($a['indexar'])];
		}
	}
	return $memo = $out;
}

/** ¿Se muestra el selector de idioma? (activado en WordPress y con más de un idioma). */
function sunqu_selector_idiomas(): bool {
	$cfg = sunqu_contenido(SUNQU_IDIOMA_BASE)['datos']['sitio']['_idiomas'] ?? [];
	return count(sunqu_idiomas_web()) > 1 && (!is_array($cfg) || !array_key_exists('selector', $cfg) || !empty($cfg['selector']));
}

/** Idiomas que se indexan (para hreflang y el sitemap). */
function sunqu_idiomas_indexables(): array {
	return array_keys(array_filter(sunqu_idiomas_web(), fn($a) => $a['indexar']));
}

function sunqu_lang(): string {
	return (string) ($GLOBALS['sunqu_lang'] ?? SUNQU_IDIOMA_BASE);
}

/** Prefijo de URL de un idioma: '' para el español, '/en' para el inglés. */
function sunqu_prefijo(?string $lang = null): string {
	$l = $lang ?? sunqu_lang();
	return $l === SUNQU_IDIOMA_BASE ? '' : '/' . $l;
}

/** Ruta pública en un idioma: '/' → '/en', '/descubre' → '/en/descubre'. */
function sunqu_ruta_idioma(string $ruta, ?string $lang = null): string {
	$p = sunqu_prefijo($lang);
	return $p === '' ? $ruta : ($ruta === '/' ? $p : $p . $ruta);
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
	// Del diccionario, solo lo que usan js/sunqu-content.js y js/sunqu-analytics.js (lo demás ya está en el HTML)
	$js = '';
	foreach (['js/sunqu-content.js', 'js/sunqu-analytics.js'] as $f) {
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
