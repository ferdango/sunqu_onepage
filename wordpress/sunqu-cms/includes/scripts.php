<?php
/**
 * Sunqu › Scripts y seguimiento: códigos de Google Tag Manager, Google Analytics, Meta Pixel, Hotjar, Clarity,
 * un chat… que la web inserta en todas sus páginas, en el <head>, al empezar el <body> o al final.
 *
 * Cada código tiene una condición de carga:
 *   · Siempre (necesario): se inserta tal cual. Solo para lo imprescindible (p. ej. un chat de atención).
 *   · Con permiso de analítica / de marketing: queda inerte hasta que la persona acepta en el aviso de cookies
 *     (js/sunqu-analytics.js lo activa). Sus <noscript>, <img> e <iframe> se quitan: sin JavaScript no hay forma
 *     de pedir permiso.
 * La web autoriza en su Content-Security-Policy los scripts en línea de estos códigos (por su hash) y los dominios
 * que aparecen en ellos, más los «Dominios adicionales» de esta página.
 *
 * Seguridad: lo que se pega aquí se ejecuta en sunqujourneys.com para todos los visitantes. Solo pueden editarlo
 * administradores (manage_options + unfiltered_html; con DISALLOW_UNFILTERED_HTML en wp-config.php nadie puede),
 * cada cambio queda en el historial y se avisa por correo a los destinatarios de Sunqu › Conexión › Avisos.
 * La web puede ignorarlos con CMS_SCRIPTS=off en su .env.
 * Todo lo que se pega es público (se ve en el código de la página): nunca contraseñas ni claves secretas.
 */

defined('ABSPATH') || exit;

const SUNQU_SCRIPTS_UBICACIONES = ['head' => 'En el <head>', 'body_inicio' => 'Al empezar el <body>', 'body_fin' => 'Al final del <body>'];
const SUNQU_SCRIPTS_CATEGORIAS = ['analitica' => 'Con permiso de analítica', 'marketing' => 'Con permiso de marketing', 'necesario' => 'Siempre (necesario)'];
const SUNQU_SCRIPTS_MAX = 20;
const SUNQU_SCRIPT_MAX_BYTES = 30000;

/** Scripts guardados: ['lista' => [[nombre, ubicacion, categoria, codigo, activo], …], 'dominios' => [...]]. */
function sunqu_scripts(): array {
	$o = get_option('sunqu_scripts', []);
	$o = is_array($o) ? $o : [];
	$lista = [];
	foreach ((array) ($o['lista'] ?? []) as $s) {
		if (!is_array($s) || !is_string($s['codigo'] ?? null) || $s['codigo'] === '') {
			continue;
		}
		$lista[] = [
			'nombre' => (string) ($s['nombre'] ?? ''),
			'ubicacion' => isset(SUNQU_SCRIPTS_UBICACIONES[$s['ubicacion'] ?? '']) ? $s['ubicacion'] : 'head',
			'categoria' => isset(SUNQU_SCRIPTS_CATEGORIAS[$s['categoria'] ?? '']) ? $s['categoria'] : 'analitica',
			'codigo' => $s['codigo'],
			'activo' => !empty($s['activo']),
		];
	}
	return ['lista' => $lista, 'dominios' => array_values(array_filter((array) ($o['dominios'] ?? []), 'is_string'))];
}

/** Lo que recibe la web (API › sitio › _scripts): solo los activos. */
function sunqu_scripts_publicos(): array {
	$s = sunqu_scripts();
	$items = [];
	foreach ($s['lista'] as $x) {
		if ($x['activo']) {
			$items[] = ['nombre' => $x['nombre'], 'ubicacion' => $x['ubicacion'], 'categoria' => $x['categoria'], 'codigo' => $x['codigo']];
		}
	}
	return ['items' => $items, 'dominios' => $s['dominios']];
}

/** Dominio para la CSP: https://dominio, wss://dominio o con comodín (https://*.hotjar.com). '' si no es válido. */
function sunqu_scripts_dominio(string $d): string {
	$d = strtolower(trim($d));
	if ($d === '') {
		return '';
	}
	if (!preg_match('#^[a-z]+://#', $d)) {
		$d = 'https://' . $d;
	}
	$d = rtrim($d, '/');
	return preg_match('#^(https|wss)://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(:\d{1,5})?$#', $d) ? $d : '';
}

/** Código limpio para guardar: sin etiquetas de documento (<html>, <head>, <body>) ni PHP. */
function sunqu_scripts_limpiar_codigo(string $c): string {
	$c = preg_replace('#<\?(php|=)?.*?(\?>|$)#is', '', $c) ?? '';
	$c = preg_replace('#</?(html|head|body)\b[^>]*>#i', '', $c) ?? '';
	return trim(str_replace("\r\n", "\n", $c));
}

/** JavaScript pegado sin <script>…</script> (se vería como texto en la página): se envuelve. */
function sunqu_scripts_envolver(string $c): string {
	if ($c === '' || preg_match('#<\s*(script|noscript|link|meta|style|iframe|img|div)\b#i', $c)) {
		return $c;
	}
	return "<script>\n" . $c . "\n</script>";
}

/**
 * Problemas conocidos de un código que harían que no mida o que la web lo bloquee. Devuelve los avisos (texto).
 * Se muestran al guardar y cada vez que se abre esta página.
 */
function sunqu_scripts_revisar(array $s): array {
	$c = $s['codigo'];
	$n = '«' . ($s['nombre'] ?: 'Script') . '»: ';
	$a = [];
	// Google Tag Manager: el ID del contenedor es GTM-XXXXXXX
	if (preg_match_all('#googletagmanager\.com/(?:gtm\.js|ns\.html)\?id=([\w-]+)#i', $c, $m)
		|| preg_match_all("#gtm\.js\?id=['\"]\s*\+\s*i.*?\(\s*window\s*,\s*document\s*,\s*['\"]script['\"]\s*,\s*['\"]\w+['\"]\s*,\s*['\"]([\w-]+)['\"]#is", $c, $m)) {
		foreach (array_unique($m[1]) as $id) {
			if (!preg_match('/^GTM-[A-Z0-9]+$/', $id)) {
				$a[] = $n . 'es el código de Google Tag Manager pero usa el ID «' . $id . '»' . (str_starts_with($id, 'G-') ? ', que es de Google Analytics' : '')
					. '. En Tag Manager el ID es GTM-XXXXXXX (Tag Manager › tu contenedor, arriba a la derecha). Si solo usas Analytics, pega en su lugar el código «Etiqueta de Google» (gtag.js) de Analytics › Administrar › Flujos de datos › Ver instrucciones de etiquetas.';
			}
		}
	}
	// gtag.js (Google Analytics)
	if (preg_match('#googletagmanager\.com/gtag/js\?id=([\w-]+)#i', $c, $m)) {
		if (str_starts_with($m[1], 'GTM-')) {
			$a[] = $n . 'carga gtag.js con un ID de Tag Manager (' . $m[1] . '). Para Tag Manager pega el código del contenedor (gtm.js); para Analytics, el ID es G-XXXXXXXXXX.';
		} elseif (!preg_match('#gtag\s*\(\s*[\'"]config[\'"]#', $c)) {
			$a[] = $n . 'carga gtag.js pero le falta la línea gtag(\'config\', \'' . $m[1] . '\'): sin ella Google Analytics no recibe nada. Copia el código completo de Analytics.';
		}
	}
	// Medición o publicidad de terceros que se carga sin permiso
	if ($s['categoria'] === 'necesario' && preg_match('#hotjar|contentsquare|clarity\.ms|connect\.facebook\.net|fbq\(|tiktok|linkedin|licdn|doubleclick|googleadservices#i', $c)) {
		$a[] = $n . 'mide o hace publicidad y está como «Siempre (necesario)»: se carga sin pedir permiso. Ponlo en «Con permiso de analítica» (Hotjar, Clarity) o «de marketing» (Pixel, TikTok, LinkedIn, Google Ads).';
	}
	// Con permiso, los <noscript>, <img> e <iframe> se quitan: si no queda un <script>, no hace nada
	if ($s['categoria'] !== 'necesario' && !preg_match('#<script\b#i', preg_replace('#<noscript\b.*?</noscript>#is', '', $c) ?? '')) {
		$a[] = $n . 'no tiene ningún <script> que ejecutar (con permiso se quitan <noscript>, <img> e <iframe>). Si es la parte <noscript> de Tag Manager, no hace falta: puedes quitarlo.';
	}
	return $a;
}

add_action('admin_menu', function () {
	add_submenu_page('sunqu', 'Scripts y seguimiento', 'Scripts y seguimiento', 'manage_options', 'sunqu-scripts', 'sunqu_pagina_scripts');
}, 16);

add_action('admin_post_sunqu_scripts_guardar', function () {
	if (!current_user_can('manage_options') || !current_user_can('unfiltered_html')) {
		wp_die('Solo un administrador puede cambiar los scripts de la web.', '', ['response' => 403]);
	}
	check_admin_referer('sunqu_scripts_guardar');
	$antes = sunqu_scripts();
	$filas = is_array($_POST['sunqu_scripts'] ?? null) ? wp_unslash($_POST['sunqu_scripts']) : [];
	$lista = [];
	$avisos = [];
	foreach (array_slice(array_values($filas), 0, SUNQU_SCRIPTS_MAX) as $f) {
		if (!is_array($f)) {
			continue;
		}
		$codigo = sunqu_scripts_envolver(sunqu_scripts_limpiar_codigo((string) ($f['codigo'] ?? '')));
		if ($codigo === '') {
			continue;
		}
		$nombre = mb_substr(sanitize_text_field((string) ($f['nombre'] ?? '')), 0, 80) ?: 'Script sin nombre';
		if (strlen($codigo) > SUNQU_SCRIPT_MAX_BYTES) {
			$avisos[] = '«' . $nombre . '» supera los 30 KB: no se guardó.';
			continue;
		}
		$lista[] = [
			'nombre' => $nombre,
			'ubicacion' => isset(SUNQU_SCRIPTS_UBICACIONES[$f['ubicacion'] ?? '']) ? $f['ubicacion'] : 'head',
			'categoria' => isset(SUNQU_SCRIPTS_CATEGORIAS[$f['categoria'] ?? '']) ? $f['categoria'] : 'analitica',
			'codigo' => $codigo,
			'activo' => !empty($f['activo']) ? 1 : 0,
		];
	}
	$dominios = [];
	foreach (preg_split('/[\r\n,]+/', (string) wp_unslash($_POST['sunqu_scripts_dominios'] ?? '')) ?: [] as $d) {
		$ok = sunqu_scripts_dominio((string) $d);
		if ($ok !== '') {
			$dominios[] = $ok;
		} elseif (trim((string) $d) !== '') {
			$avisos[] = 'Dominio no válido, no se guardó: ' . sanitize_text_field((string) $d);
		}
	}
	$nuevo = ['lista' => $lista, 'dominios' => array_values(array_unique(array_slice($dominios, 0, 30)))];

	// Qué cambió (para el historial y el aviso por correo)
	$clave = fn($s) => $s['nombre'] . '|' . $s['ubicacion'] . '|' . $s['categoria'] . '|' . ($s['activo'] ? 1 : 0) . '|' . md5($s['codigo']);
	$viejos = array_map($clave, $antes['lista']);
	$nuevos = array_map($clave, $lista);
	$cambios = [];
	foreach ($lista as $i => $s) {
		if (!in_array($nuevos[$i], $viejos, true)) {
			$cambios[] = (in_array($s['nombre'], array_column($antes['lista'], 'nombre'), true) ? 'Cambió' : 'Nuevo') . ': ' . $s['nombre'] . ' (' . SUNQU_SCRIPTS_UBICACIONES[$s['ubicacion']] . ', ' . SUNQU_SCRIPTS_CATEGORIAS[$s['categoria']] . ($s['activo'] ? '' : ', desactivado') . ')';
		}
	}
	foreach ($antes['lista'] as $s) {
		if (!in_array($s['nombre'], array_column($lista, 'nombre'), true)) {
			$cambios[] = 'Quitado: ' . $s['nombre'];
		}
	}
	if ($nuevo['dominios'] !== $antes['dominios']) {
		$cambios[] = 'Dominios adicionales: ' . (implode(', ', $nuevo['dominios']) ?: '(ninguno)');
	}
	update_option('sunqu_scripts', $nuevo, false); // la web se entera sola (purga)

	if ($cambios) {
		$u = wp_get_current_user();
		$historial = get_option('sunqu_scripts_historial', []);
		$historial = is_array($historial) ? $historial : [];
		array_unshift($historial, ['fecha' => wp_date('c'), 'usuario' => $u->display_name, 'cambios' => $cambios]);
		update_option('sunqu_scripts_historial', array_slice($historial, 0, 20), false);
		$asunto = 'Aviso de seguridad · cambiaron los scripts de la web';
		$campos = ['Quién' => $u->display_name, 'Cuándo' => wp_date('j \d\e F \d\e Y, H:i'), 'Cambios' => implode(' | ', $cambios)]; // sin el usuario de acceso
		$intro = 'Alguien cambió los códigos que la web inserta en todas sus páginas (Sunqu › Scripts y seguimiento). Si no reconoces el cambio, revisa esa página y cambia las contraseñas de los administradores.';
		$botones = [['texto' => 'Revisar los scripts', 'url' => admin_url('admin.php?page=sunqu-scripts')]];
		sunqu_enviar_correo(sunqu_correos_avisos(), $asunto, sunqu_correo_html($asunto, $intro, $campos, $botones), sunqu_correo_texto($asunto, $intro, $campos, $botones));
	}
	set_transient('sunqu_scripts_msg_' . get_current_user_id(), ['ok' => count($lista) . ' scripts guardados.', 'avisos' => $avisos], 60);
	wp_safe_redirect(admin_url('admin.php?page=sunqu-scripts'));
	exit;
});

function sunqu_pagina_scripts(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$s = sunqu_scripts();
	$msg = get_transient('sunqu_scripts_msg_' . get_current_user_id());
	delete_transient('sunqu_scripts_msg_' . get_current_user_id());
	$historial = get_option('sunqu_scripts_historial', []);
	$puede = current_user_can('unfiltered_html');
	$fila = function (int $i, array $x) {
		$n = 'sunqu_scripts[' . $i . ']';
		ob_start(); ?>
		<div class="sunqu-script" style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 16px;margin:0 0 14px;max-width:980px">
			<p style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:0 0 10px">
				<input type="text" class="regular-text" name="<?php echo esc_attr($n); ?>[nombre]" value="<?php echo esc_attr($x['nombre']); ?>" placeholder="Nombre (p. ej. Google Tag Manager)" aria-label="Nombre">
				<select name="<?php echo esc_attr($n); ?>[ubicacion]" aria-label="Dónde se inserta">
					<?php foreach (SUNQU_SCRIPTS_UBICACIONES as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($x['ubicacion'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
				</select>
				<select name="<?php echo esc_attr($n); ?>[categoria]" aria-label="Cuándo se carga">
					<?php foreach (SUNQU_SCRIPTS_CATEGORIAS as $k => $v) : ?><option value="<?php echo esc_attr($k); ?>" <?php selected($x['categoria'], $k); ?>><?php echo esc_html($v); ?></option><?php endforeach; ?>
				</select>
				<label><input type="checkbox" name="<?php echo esc_attr($n); ?>[activo]" value="1" <?php checked($x['activo']); ?>> Activo</label>
				<button type="button" class="button-link button-link-delete sunqu-quitar">Quitar</button>
			</p>
			<textarea name="<?php echo esc_attr($n); ?>[codigo]" rows="7" class="large-text code" spellcheck="false" aria-label="Código" placeholder="&lt;script&gt; … &lt;/script&gt;"><?php echo esc_textarea($x['codigo']); ?></textarea>
		</div>
		<?php return (string) ob_get_clean();
	};
	?>
	<div class="wrap">
		<h1>Sunqu · Scripts y seguimiento</h1>
		<?php if (is_array($msg)) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html($msg['ok']); ?> La web los aplica en unos segundos.</p></div>
			<?php foreach ((array) $msg['avisos'] as $a) : ?><div class="notice notice-warning"><p><?php echo esc_html($a); ?></p></div><?php endforeach; ?>
		<?php endif; ?>
		<p>Pega aquí los códigos de <strong>Google Tag Manager</strong>, <strong>Google Analytics</strong>, <strong>Meta Pixel</strong>, Hotjar, Clarity, un chat… La web los inserta en <strong>todas sus páginas</strong> e idiomas, donde indiques.</p>
		<ul style="list-style:disc;padding-left:20px;max-width:980px">
			<li><strong>Con permiso de analítica / marketing</strong> (recomendado para medición y publicidad): el código espera a que la persona acepte en el aviso de cookies. El aviso aparece solo y nombra cada servicio. Sus partes <code>&lt;noscript&gt;</code>, <code>&lt;img&gt;</code> e <code>&lt;iframe&gt;</code> se quitan, porque sin JavaScript no se puede pedir permiso.</li>
			<li><strong>Siempre (necesario)</strong>: se inserta tal cual, sin pedir permiso. Úsalo solo para lo imprescindible (p. ej. un chat de atención). Excepción: un código de Google (Tag Manager o gtag.js) marcado así funciona en <em>Consent Mode</em>: se carga desde el principio, pero no guarda cookies hasta que la persona acepta, y el aviso de cookies aparece igual.</li>
			<li><strong>Google Tag Manager</strong>: pega el bloque del <code>&lt;head&gt;</code> (el del <code>&lt;body&gt;</code> es un <code>&lt;noscript&gt;</code> y no hace falta). Los eventos de la web (formularios, WhatsApp, journeys…) llegan a su <code>dataLayer</code>. No configures además <code>GTM_ID</code>, <code>GA4_ID</code> o <code>META_PIXEL_ID</code> en el <code>.env</code> de la web: se medirían dos veces.</li>
			<li><strong>Google Analytics (gtag.js)</strong> pegado aquí también recibe los eventos de la web.</li>
			<li>La web autoriza en su política de seguridad (CSP) los dominios que aparecen en cada código. Si un servicio carga otros dominios, agrégalos en «Dominios adicionales» (lo verás como error en la consola del navegador).</li>
			<li><strong>Todo lo que pegas aquí es público</strong> (se ve en el código de la página): nunca contraseñas ni claves secretas. Cada cambio queda en el historial y se avisa por correo.</li>
		</ul>
		<?php foreach ($s['lista'] as $x) :
			if (!$x['activo']) {
				continue;
			}
			foreach (sunqu_scripts_revisar($x) as $aviso) : ?>
				<div class="notice notice-warning inline"><p><strong>Revisa:</strong> <?php echo esc_html($aviso); ?></p></div>
			<?php endforeach;
		endforeach; ?>
		<?php if (!$puede) : ?>
			<div class="notice notice-error inline"><p>Tu usuario no puede guardar HTML sin filtrar (<code>unfiltered_html</code>): solo un administrador del sitio puede cambiar los scripts.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="sunqu_scripts_guardar">
			<?php wp_nonce_field('sunqu_scripts_guardar'); ?>
			<div id="sunqu-scripts-lista">
				<?php foreach ($s['lista'] as $i => $x) {
					echo $fila($i, $x); // phpcs:ignore WordPress.Security.EscapeOutput -- ya escapado en $fila
				} ?>
			</div>
			<p><button type="button" class="button" id="sunqu-scripts-agregar">Agregar un script</button></p>
			<h2>Dominios adicionales (CSP)</h2>
			<p><textarea name="sunqu_scripts_dominios" rows="3" class="large-text code" style="max-width:980px" placeholder="https://*.hotjar.com&#10;wss://*.hotjar.com"><?php echo esc_textarea(implode("\n", $s['dominios'])); ?></textarea></p>
			<p class="description">Uno por línea: <code>https://dominio.com</code>, <code>https://*.dominio.com</code> o <code>wss://…</code>. Solo hace falta si la consola del navegador muestra que la política de seguridad bloqueó algo de un servicio que agregaste.</p>
			<?php submit_button('Guardar scripts', 'primary', 'submit', true, $puede ? [] : ['disabled' => 'disabled']); ?>
		</form>
		<template id="sunqu-script-plantilla"><?php echo $fila(999, ['nombre' => '', 'ubicacion' => 'head', 'categoria' => 'analitica', 'codigo' => '', 'activo' => true]); // phpcs:ignore ?></template>
		<script>
		(function () {
			var lista = document.getElementById('sunqu-scripts-lista'), n = lista.children.length + 1;
			document.getElementById('sunqu-scripts-agregar').addEventListener('click', function () {
				var html = document.getElementById('sunqu-script-plantilla').innerHTML.replace(/sunqu_scripts\[999\]/g, 'sunqu_scripts[' + (n++) + ']');
				lista.insertAdjacentHTML('beforeend', html);
				lista.lastElementChild.querySelector('input[type=text]').focus();
			});
			lista.addEventListener('click', function (e) {
				if (e.target.classList.contains('sunqu-quitar') && window.confirm('¿Quitar este script? Se aplica al guardar.')) e.target.closest('.sunqu-script').remove();
			});
		})();
		</script>
		<?php if (is_array($historial) && $historial) : ?>
			<h2>Historial de cambios</h2>
			<table class="widefat striped" style="max-width:980px"><tbody>
				<?php foreach ($historial as $h) : ?>
					<tr><td style="width:170px"><?php echo esc_html(wp_date('j M Y, H:i', strtotime((string) $h['fecha']))); ?></td><td style="width:140px"><?php echo esc_html((string) $h['usuario']); ?></td><td><?php echo esc_html(implode(' · ', (array) $h['cambios'])); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>
	</div>
	<?php
}
