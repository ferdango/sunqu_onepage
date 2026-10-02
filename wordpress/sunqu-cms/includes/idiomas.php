<?php
/**
 * Idiomas de la web (Sunqu › Idiomas) traducidos con Loco Translate.
 *
 * La web está escrita en español. Para mostrarla en inglés o portugués:
 *   1. Sunqu › Idiomas: activar el idioma (y elegir su variante: en_US, pt_BR…).
 *   2. Loco Translate › Plugins › Sunqu CMS › «Nuevo idioma»: crear el idioma en la ubicación «Personalizado»
 *      y traducir. La plantilla languages/sunqu-cms.pot trae TODOS los textos de la web: los fijos de las páginas
 *      (botones, títulos, formularios, aviso de cookies…) y los del contenido (journeys, Diario, legales, ajustes).
 *      El plugin la actualiza solo cuando cambia el contenido; en Loco, «Sincronizar» suma los textos nuevos.
 *   3. Cuando la traducción esté completa, marcar «Publicar en buscadores»: hasta entonces /en/… se ve pero no se indexa.
 *
 * La API entrega el contenido traducido con ?lang=en (lo que no está traducido sale en español) y, en sitio,
 * «_idiomas» (configuración pública) e «_i18n» (textos fijos traducidos). La web arma /en/… y /pt/… con eso.
 * Las traducciones viven en wp-content/languages/loco/plugins/sunqu-cms-<locale>.po/.mo (Loco «Personalizado»),
 * no dentro del plugin: sobreviven a las actualizaciones.
 */

defined('ABSPATH') || exit;

const SUNQU_IDIOMA_BASE = 'es';
/** Idiomas que la web puede mostrar: código => [nombre, variantes (la primera es la predeterminada)]. */
const SUNQU_IDIOMAS = [
	'en' => ['English', ['en_US', 'en_GB']],
	'pt' => ['Português', ['pt_BR', 'pt_PT']],
];
/** Campos del contenido que no son texto para personas (slugs, imágenes, colores, enlaces, contacto). */
const SUNQU_I18N_SIN_TRADUCIR = ['slug', 'id', 'img', 'imagenes', 'momentImg', 'photo', 'photos', 'poster', 'video', 'icon', 'card', 'href', 'link',
	'url', 'embed', 'email', 'telefono', 'telefonoHref', 'whatsapp', 'instagramUsuario', 'facebook', 'instagram', 'tiktok', 'youtube', 'ruc', 'bg',
	'fg', 'color', 'c', 'cat', 'dest', 'mp', 'plat', 'kind', 'topic', 'anfitriones', 'hosts', 'stage', 'ratio', 'pos', 'sizes', 'date'];

/** Configuración normalizada: ['selector' => bool, 'activos' => ['en' => ['locale' => 'en_US', 'indexar' => bool], …]]. */
function sunqu_idiomas(): array {
	$o = get_option('sunqu_idiomas', []);
	$o = is_array($o) ? $o : [];
	$activos = [];
	foreach (SUNQU_IDIOMAS as $code => [, $variantes]) {
		$a = $o['activos'][$code] ?? null;
		if (!is_array($a) || empty($a['activo'])) {
			continue;
		}
		$activos[$code] = [
			'locale' => in_array($a['locale'] ?? '', $variantes, true) ? $a['locale'] : $variantes[0],
			'indexar' => !empty($a['indexar']),
		];
	}
	return ['selector' => !array_key_exists('selector', $o) || !empty($o['selector']), 'activos' => $activos];
}

/** Idioma pedido a la API (?lang=en) si está activo; '' = español. */
function sunqu_idioma_peticion(WP_REST_Request $req): string {
	$l = sanitize_key((string) $req->get_param('lang'));
	return isset(sunqu_idiomas()['activos'][$l]) ? $l : '';
}

// ── Traducciones (archivos de Loco) ─────────────────────────────────────────────────────────

/** Archivos de traducción candidatos para un locale, en orden de preferencia (Loco «Personalizado» primero). */
function sunqu_i18n_archivos(string $locale): array {
	return [
		WP_LANG_DIR . "/loco/plugins/sunqu-cms-$locale.mo",
		WP_LANG_DIR . "/plugins/sunqu-cms-$locale.mo",
		SUNQU_CMS_DIR . "languages/sunqu-cms-$locale.mo",
	];
}

/** Huella de los archivos de traducción (cambia cuando Loco guarda): parte de las claves de caché. */
function sunqu_i18n_huella(string $locale): string {
	$h = '';
	foreach (sunqu_i18n_archivos($locale) as $mo) {
		foreach ([$mo, substr($mo, 0, -3) . '.l10n.php'] as $f) {
			$h .= is_file($f) ? $f . filemtime($f) . filesize($f) : '';
		}
	}
	return substr(md5($h), 0, 10);
}

/** Carga las traducciones de un locale para el dominio sunqu-cms (sin depender del idioma del panel). */
function sunqu_i18n_cargar(string $locale): void {
	if (($GLOBALS['sunqu_i18n']['locale'] ?? null) === $locale) {
		return;
	}
	unload_textdomain('sunqu-cms', true);
	$hay = false;
	foreach (sunqu_i18n_archivos($locale) as $mo) {
		if (is_file($mo) || is_file(substr($mo, 0, -3) . '.l10n.php')) {
			$hay = load_textdomain('sunqu-cms', $mo, $locale) || $hay; // .l10n.php (WordPress 6.5+) o .mo
		}
	}
	$GLOBALS['sunqu_i18n'] = ['locale' => $locale, 'hay' => $hay];
}

/**
 * Traducción de un texto con las traducciones cargadas (sunqu_i18n_cargar); el mismo texto si no la hay.
 * Sin archivos para ese idioma no se llama a translate(): WordPress cargaría las del idioma del panel.
 */
function sunqu_tr(string $s): string {
	return !empty($GLOBALS['sunqu_i18n']['hay']) ? translate($s, 'sunqu-cms') : $s;
}

// ── Qué se traduce del contenido ───────────────────────────────────────────────────────────

/** ¿Es texto para personas? (no slugs, rutas, colores, ids de Unsplash…). Las mismas reglas para la plantilla y la API. */
function sunqu_i18n_traducible(string $clave, string $v): bool {
	if ($clave === '' || $clave[0] === '_' || in_array($clave, SUNQU_I18N_SIN_TRADUCIR, true)) {
		return false;
	}
	$t = trim($v);
	if (mb_strlen($t) < 2 || !preg_match('/\p{L}/u', $t)) {
		return false;
	}
	if (preg_match('#^(https?:|mailto:|tel:|data:|/|\#|assets/)#i', $t) || preg_match('/^#?[0-9a-f]{3,8}$/i', $t) || preg_match('/^\d+-[0-9a-f]{6,}$/i', $t)) {
		return false;
	}
	return (bool) (preg_match('/\s/u', $t) || preg_match('/[^\x00-\x7F]/', $t) || preg_match('/^\p{Lu}\p{Ll}/u', $t));
}

/**
 * Recorre el contenido y aplica $fn(texto, ruta) a cada texto traducible. La ruta usa el slug o el id de cada
 * elemento («journeys/alma/ficha/why»): es el comentario que ve quien traduce en Loco.
 */
function sunqu_i18n_recorrer($v, callable $fn, string $clave = '', string $ruta = '') {
	if (is_array($v)) {
		foreach ($v as $k => $x) {
			$paso = is_int($k) && is_array($x) ? (string) ($x['slug'] ?? $x['id'] ?? $k) : (string) $k;
			$v[$k] = sunqu_i18n_recorrer($x, $fn, is_int($k) ? $clave : (string) $k, $ruta . '/' . $paso);
		}
		return $v;
	}
	return is_string($v) && sunqu_i18n_traducible($clave, $v) ? $fn($v, $ruta) : $v;
}

/** Sección exportada (sunqu_exportar) traducida a un idioma activo. */
function sunqu_i18n_traducir_seccion(array $datos, string $lang): array {
	$idioma = sunqu_idiomas()['activos'][$lang] ?? null;
	if (!$idioma) {
		return $datos;
	}
	sunqu_i18n_cargar($idioma['locale']);
	return sunqu_i18n_recorrer($datos, fn($s) => sunqu_tr($s));
}

/** Textos fijos de la web (plantillas, JS, PHP): [texto => dónde aparece]. Lo genera tools/extraer-textos.py. */
function sunqu_i18n_textos_web(): array {
	static $t = null;
	if ($t === null) {
		$f = SUNQU_CMS_DIR . 'i18n/textos-web.php';
		$t = is_file($f) ? (array) include $f : [];
	}
	return $t;
}

/** Textos fijos traducidos a un idioma: [original => traducción] (solo los que tienen traducción). */
function sunqu_i18n_diccionario(string $lang): array {
	$idioma = sunqu_idiomas()['activos'][$lang] ?? null;
	if (!$idioma) {
		return [];
	}
	sunqu_i18n_cargar($idioma['locale']);
	$dic = [];
	foreach (array_keys(sunqu_i18n_textos_web()) as $s) {
		$s = (string) $s;
		$tr = sunqu_tr($s);
		if ($tr !== $s && $tr !== '') {
			$dic[$s] = $tr;
		}
	}
	return $dic;
}

/** Datos de idioma que agrega la API a la sección sitio. */
function sunqu_i18n_sitio(array $sitio, string $lang): array {
	$cfg = sunqu_idiomas();
	$sitio['_idiomas'] = ['base' => SUNQU_IDIOMA_BASE, 'selector' => $cfg['selector'], 'activos' => array_map(fn($a) => ['locale' => $a['locale'], 'indexar' => $a['indexar']], $cfg['activos'])];
	if ($lang !== '') {
		$sitio['_i18n'] = (object) sunqu_i18n_diccionario($lang);
	}
	return $sitio;
}

// ── Plantilla para Loco Translate (languages/sunqu-cms.pot) ─────────────────────────────────

function sunqu_po_cadena(string $s): string {
	return '"' . str_replace(['\\', '"', "\r\n", "\r", "\n", "\t"], ['\\\\', '\\"', '\n', '\n', '\n', '\t'], $s) . '"';
}

/**
 * Escribe languages/sunqu-cms.pot con los textos fijos de la web y todos los textos del contenido publicado
 * (en español). Devuelve ['ok' => bool, 'textos' => int, 'error' => string].
 */
function sunqu_i18n_generar_pot(): array {
	$entradas = [];
	foreach (sunqu_i18n_textos_web() as $s => $donde) {
		$entradas[(string) $s][] = 'Web: ' . $donde;
	}
	foreach (sunqu_secciones() as $sec) {
		$datos = sunqu_exportar($sec, false);
		sunqu_i18n_recorrer($datos ?? [], function ($s, $ruta) use (&$entradas, $sec) {
			if (count($entradas[$s] ?? []) < 3) {
				$entradas[$s][] = 'Contenido: ' . $sec . $ruta;
			}
			return $s;
		});
	}
	$l = [
		'# Textos de la web de Sunqu Journeys (páginas y contenido) para traducir con Loco Translate.',
		'# GENERADO por el plugin Sunqu CMS al cambiar el contenido: no lo edites a mano.',
		'msgid ""',
		'msgstr ""',
		'"Project-Id-Version: Sunqu CMS ' . SUNQU_CMS_VERSION . '\n"',
		'"POT-Creation-Date: ' . gmdate('Y-m-d H:i') . '+0000\n"',
		'"MIME-Version: 1.0\n"',
		'"Content-Type: text/plain; charset=UTF-8\n"',
		'"Content-Transfer-Encoding: 8bit\n"',
		'"Language: es\n"',
		'"X-Domain: sunqu-cms\n"',
		'',
	];
	foreach ($entradas as $s => $comentarios) {
		foreach (array_slice(array_unique($comentarios), 0, 3) as $c) {
			$l[] = '#. ' . str_replace(["\r", "\n"], ' ', $c);
		}
		$l[] = 'msgid ' . sunqu_po_cadena((string) $s);
		$l[] = 'msgstr ""';
		$l[] = '';
	}
	$dir = SUNQU_CMS_DIR . 'languages';
	if (!is_dir($dir) && !wp_mkdir_p($dir)) {
		return ['ok' => false, 'textos' => 0, 'error' => 'No se pudo crear la carpeta languages del plugin.'];
	}
	$ok = file_put_contents($dir . '/sunqu-cms.pot', implode("\n", $l) . "\n", LOCK_EX) !== false;
	if ($ok) {
		update_option('sunqu_i18n_pot', ['v' => sunqu_i18n_version_contenido(), 'fecha' => wp_date('c'), 'textos' => count($entradas)], false);
	}
	return ['ok' => $ok, 'textos' => count($entradas), 'error' => $ok ? '' : 'No se pudo escribir languages/sunqu-cms.pot (permisos de la carpeta del plugin).'];
}

/** Versión del contenido + del plugin: si cambia, la plantilla se vuelve a generar. */
function sunqu_i18n_version_contenido(): string {
	return SUNQU_CMS_VERSION . '-' . (int) get_option('sunqu_cache_v', 1);
}

// Al entrar al panel: plantilla al día (si cambió el contenido) y, si Loco guardó traducciones nuevas, la web se entera
add_action('admin_init', function () {
	if (!current_user_can('edit_others_posts') || wp_doing_ajax()) {
		return;
	}
	$pot = get_option('sunqu_i18n_pot', []);
	if (!is_array($pot) || ($pot['v'] ?? '') !== sunqu_i18n_version_contenido() || !is_file(SUNQU_CMS_DIR . 'languages/sunqu-cms.pot')) {
		sunqu_i18n_generar_pot();
	}
	$huellas = implode(',', array_map(fn($a) => sunqu_i18n_huella($a['locale']), sunqu_idiomas()['activos']));
	if ($huellas !== (string) get_option('sunqu_i18n_huellas', '')) {
		update_option('sunqu_i18n_huellas', $huellas, false);
		sunqu_limpiar_cache(); // traducciones nuevas: la API y la web vuelven a armar las páginas traducidas
	}
});

// ── Sunqu › Idiomas ────────────────────────────────────────────────────────────────────────

add_action('admin_menu', function () {
	add_submenu_page('sunqu', 'Idiomas', 'Idiomas', 'manage_options', 'sunqu-idiomas', 'sunqu_pagina_idiomas');
}, 15);

add_action('admin_init', function () {
	register_setting('sunqu_idiomas', 'sunqu_idiomas', ['sanitize_callback' => function ($v) {
		$v = is_array($v) ? $v : [];
		$out = ['selector' => !empty($v['selector']) ? 1 : 0, 'activos' => []];
		foreach (SUNQU_IDIOMAS as $code => [, $variantes]) {
			$a = is_array($v['activos'][$code] ?? null) ? $v['activos'][$code] : [];
			$out['activos'][$code] = [
				'activo' => !empty($a['activo']) ? 1 : 0,
				'locale' => in_array($a['locale'] ?? '', $variantes, true) ? $a['locale'] : $variantes[0],
				'indexar' => !empty($a['indexar']) ? 1 : 0,
			];
		}
		return $out;
	}]);
	if (isset($_POST['sunqu_idiomas_accion']) && current_user_can('manage_options') && check_admin_referer('sunqu_idiomas_pot')) {
		$r = sunqu_i18n_generar_pot();
		add_settings_error('sunqu_idiomas', 'pot', $r['ok'] ? 'Plantilla actualizada: ' . $r['textos'] . ' textos. En Loco Translate, abre cada idioma y pulsa «Sincronizar».' : $r['error'], $r['ok'] ? 'updated' : 'error');
	}
});

/** Cuántos textos tiene traducidos un locale: ['web' => [traducidos, total], 'contenido' => [traducidos, total]]. */
function sunqu_i18n_progreso(string $locale): array {
	sunqu_i18n_cargar($locale);
	$web = array_keys(sunqu_i18n_textos_web());
	$hechos = count(array_filter($web, fn($s) => sunqu_tr((string) $s) !== (string) $s));
	$cont = [];
	foreach (sunqu_secciones() as $sec) {
		sunqu_i18n_recorrer(sunqu_exportar($sec) ?? [], function ($s) use (&$cont) {
			$cont[$s] = true;
			return $s;
		});
	}
	$hc = count(array_filter(array_keys($cont), fn($s) => sunqu_tr((string) $s) !== (string) $s));
	return ['web' => [$hechos, count($web)], 'contenido' => [$hc, count($cont)]];
}

function sunqu_pagina_idiomas(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$o = get_option('sunqu_idiomas', []);
	$o = is_array($o) ? $o : [];
	$cfg = sunqu_idiomas();
	$loco = is_plugin_active('loco-translate/loco.php') || class_exists('Loco_package_Bundle');
	$pot = get_option('sunqu_i18n_pot', []);
	$web = untrailingslashit((string) get_option('sunqu_web', '')) ?: SUNQU_WEB_DEFECTO;
	?>
	<div class="wrap">
		<h1>Sunqu · Idiomas</h1>
		<?php settings_errors('sunqu_idiomas'); ?>
		<p>La web está escrita en <strong>español</strong>. Activa otros idiomas aquí y tradúcelos con <strong>Loco Translate</strong>: cada idioma activo se ve en <code><?php echo esc_html($web); ?>/en/…</code> o <code>/pt/…</code>, y el selector de idioma de la web lo ofrece. Lo que todavía no está traducido se muestra en español.</p>
		<?php if (!$loco) : ?>
			<div class="notice notice-warning inline"><p><strong>Falta Loco Translate.</strong> Instálalo y actívalo (Plugins › Añadir nuevo › «Loco Translate») para traducir los textos.</p></div>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields('sunqu_idiomas'); ?>
			<table class="widefat striped" style="max-width:980px">
				<thead><tr><th>Idioma</th><th>Activo en la web</th><th>Variante</th><th>Publicar en buscadores</th><th>Traducido</th></tr></thead>
				<tbody>
					<tr><td><strong>Español</strong> (base)</td><td>Siempre</td><td>es</td><td>Sí</td><td>—</td></tr>
					<?php foreach (SUNQU_IDIOMAS as $code => [$nombre, $variantes]) :
						$a = is_array($o['activos'][$code] ?? null) ? $o['activos'][$code] : [];
						$locale = in_array($a['locale'] ?? '', $variantes, true) ? $a['locale'] : $variantes[0];
						$p = isset($cfg['activos'][$code]) ? sunqu_i18n_progreso($locale) : null; ?>
						<tr>
							<td><strong><?php echo esc_html($nombre); ?></strong> <code>/<?php echo esc_html($code); ?></code></td>
							<td><label><input type="checkbox" name="sunqu_idiomas[activos][<?php echo esc_attr($code); ?>][activo]" value="1" <?php checked(!empty($a['activo'])); ?>> Activo</label></td>
							<td><select name="sunqu_idiomas[activos][<?php echo esc_attr($code); ?>][locale]">
								<?php foreach ($variantes as $v) : ?><option value="<?php echo esc_attr($v); ?>" <?php selected($locale, $v); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
							</select></td>
							<td><label><input type="checkbox" name="sunqu_idiomas[activos][<?php echo esc_attr($code); ?>][indexar]" value="1" <?php checked(!empty($a['indexar'])); ?>> Sí</label></td>
							<td><?php if ($p) :
								$pct = fn($x) => $x[1] ? (int) round(100 * $x[0] / $x[1]) : 0; ?>
								Páginas: <strong><?php echo esc_html($pct($p['web'])); ?>%</strong> (<?php echo esc_html($p['web'][0] . '/' . $p['web'][1]); ?>) · Contenido: <strong><?php echo esc_html($pct($p['contenido'])); ?>%</strong> (<?php echo esc_html($p['contenido'][0] . '/' . $p['contenido'][1]); ?>)
								<?php if ($loco) : ?><br><a href="<?php echo esc_url(admin_url('admin.php?page=loco-plugin&action=view&bundle=' . rawurlencode('sunqu-cms/sunqu-cms.php'))); ?>">Traducir en Loco Translate</a><?php endif; ?>
							<?php else : ?>—<?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><label><input type="checkbox" name="sunqu_idiomas[selector]" value="1" <?php checked($cfg['selector']); ?>> Mostrar el selector de idioma en la web (cabecera y menú). Sin idiomas activos no se muestra.</label></p>
			<p class="description">«Publicar en buscadores»: márcalo cuando la traducción esté completa. Hasta entonces las páginas del idioma se pueden visitar, pero llevan <code>noindex</code> y no entran al sitemap ni a los <code>hreflang</code>.</p>
			<?php submit_button('Guardar idiomas'); ?>
		</form>

		<h2>Cómo traducir con Loco Translate</h2>
		<ol>
			<li>Activa el idioma arriba y guarda.</li>
			<li>Ve a <strong>Loco Translate › Plugins › Sunqu CMS</strong> › <strong>Nuevo idioma</strong>, elige el mismo idioma y variante (p. ej. <em>English (United States)</em>) y la ubicación <strong>Personalizado</strong> (así no se pierde al actualizar el plugin).</li>
			<li>Traduce y pulsa <strong>Guardar</strong>. La web muestra las traducciones en unos minutos (o al instante con «Vaciar caché de la web» en Sunqu › Conexión).</li>
			<li>Cuando cambies textos en español (un journey, un artículo…), la plantilla se actualiza sola: en Loco abre el idioma y pulsa <strong>Sincronizar</strong> para ver lo nuevo.</li>
		</ol>
		<p>Plantilla: <code>wp-content/plugins/sunqu-cms/languages/sunqu-cms.pot</code><?php echo is_array($pot) && !empty($pot['fecha']) ? ' · ' . esc_html((string) $pot['textos']) . ' textos · actualizada el ' . esc_html(wp_date('j M Y, H:i', strtotime($pot['fecha']))) : ''; ?>.</p>
		<form method="post">
			<?php wp_nonce_field('sunqu_idiomas_pot'); ?>
			<button class="button" name="sunqu_idiomas_accion" value="pot">Actualizar la plantilla ahora</button>
		</form>
	</div>
	<?php
}
