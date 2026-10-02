<?php
/**
 * Endurecimiento de WordPress como CMS headless: la web pública solo usa /wp-json/sunqu/v1 (contenido y
 * formularios). Todo lo demás que WordPress expone por defecto y la web no necesita se cierra o pide sesión.
 *
 *   · XML-RPC, pingbacks y comentarios: apagados.
 *   · REST API sin sesión: solo las rutas de la web (sunqu/v1/contenido y sunqu/v1/formularios). El índice
 *     /wp-json/, /wp/v2 (usuarios, entradas, medios…), oEmbed y las rutas internas del editor piden sesión.
 *     El panel y el editor de bloques siguen igual (usan la sesión).
 *   · Usuarios: no se pueden enumerar (?author=N, /wp/v2/users, mensajes de acceso y de «olvidé mi contraseña»).
 *   · Acceso: 5 intentos fallidos por IP en 15 minutos → 15 minutos de espera. Detrás de la CDN de Hostinger,
 *     REMOTE_ADDR ya es la IP del visitante (filtro «sunqu_ip_cliente» para otros proxys).
 *   · Contraseñas de aplicación: apagadas (ninguna integración las usa); define('SUNQU_APP_PASSWORDS', true) las activa.
 *   · Editor de archivos de temas y plugins en el panel: apagado (DISALLOW_FILE_EDIT).
 *   · Biblioteca: solo imágenes, video y PDF; en /wp-content/uploads no se ejecutan scripts (.htaccess).
 *   · Cabeceras: nosniff, Referrer-Policy, Permissions-Policy, HSTS (con HTTPS) y sin X-Powered-By.
 * Estado y recomendaciones: Sunqu › Seguridad.
 */

defined('ABSPATH') || exit;

if (!defined('DISALLOW_FILE_EDIT')) {
	define('DISALLOW_FILE_EDIT', true);
}

const SUNQU_ACCESO_INTENTOS = 5;
const SUNQU_ACCESO_ESPERA = 15 * MINUTE_IN_SECONDS;
/** Versión de las protecciones de archivos (.htaccess de uploads): al subirla se vuelven a escribir. */
const SUNQU_SEGURIDAD_V = 1;

// ── XML-RPC, pingbacks y comentarios ─────────────────────────────────────────────────────────

add_filter('xmlrpc_enabled', '__return_false');
add_filter('xmlrpc_methods', fn() => []);
add_action('init', function () {
	if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
		status_header(403);
		header('Content-Type: text/plain; charset=utf-8');
		exit('XML-RPC está desactivado.');
	}
}, 0);
add_filter('wp_headers', function ($h) {
	unset($h['X-Pingback']);
	return $h;
});
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// ?ver=7.1.2 en los CSS y JS de la pantalla de acceso delata la versión de WordPress: se reemplaza por una
// huella que cambia igual con cada actualización (la caché del navegador se sigue renovando)
function sunqu_ocultar_version($src) {
	global $wp_version;
	if (is_string($src) && str_contains($src, 'ver=' . $wp_version)) {
		$src = str_replace('ver=' . $wp_version, 'ver=' . substr(md5($wp_version . wp_salt('nonce')), 0, 8), $src);
	}
	return $src;
}
add_filter('style_loader_src', 'sunqu_ocultar_version', 99);
add_filter('script_loader_src', 'sunqu_ocultar_version', 99);

add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);
add_action('pre_comment_on_post', function () {
	wp_die('Los comentarios están desactivados.', '', ['response' => 403]);
});
add_action('admin_init', function () {
	foreach (get_post_types() as $tipo) {
		if (post_type_supports($tipo, 'comments')) {
			remove_post_type_support($tipo, 'comments');
			remove_post_type_support($tipo, 'trackbacks');
		}
	}
});
add_action('admin_menu', fn() => remove_menu_page('edit-comments.php'), 99);
add_action('admin_bar_menu', fn($barra) => $barra->remove_node('comments'), 99);

// ── REST API ─────────────────────────────────────────────────────────────────────────────────

/** ¿Esta ruta de la API responde sin sesión? Solo lo que usa la web pública. */
function sunqu_ruta_publica(string $ruta): bool {
	return (bool) preg_match('#^/sunqu/v1/(contenido|formularios)(/|$)#', $ruta);
}

// Prioridad 999 (la última): Secure Custom Fields engancha a este filtro una función que no devuelve nada
// (ACF_Rest_Api::initialize, prioridad 10) y borraría un bloqueo aplicado antes.
add_filter('rest_pre_dispatch', function ($resultado, $servidor, $request) {
	if ($resultado !== null || is_user_logged_in()) {
		return $resultado; // p. ej. la respuesta de una petición OPTIONS (CORS)
	}
	$ruta = untrailingslashit((string) $request->get_route()) ?: '/';
	if (sunqu_ruta_publica($ruta)) {
		return $resultado;
	}
	// Núcleo de WordPress (índice, /wp/v2, oEmbed, editor, salud del sitio, lotes) y el resto de sunqu/v1
	if ($ruta === '/' || preg_match('#^/(wp/v2|oembed/1\.0|wp-site-health/v1|wp-block-editor/v1|wp-abilities/v1|batch/v1|sunqu/v1)(/|$)#', $ruta)) {
		return new WP_Error('rest_no_disponible', 'Esta ruta de la API requiere iniciar sesión.', ['status' => rest_authorization_required_code()]);
	}
	return $resultado; // rutas de otros plugins: cada una valida sus permisos
}, 999, 3);

// Segunda capa contra la enumeración de usuarios: sin sesión, las rutas de usuarios ni siquiera existen
add_filter('rest_endpoints', function ($rutas) {
	if (!is_user_logged_in()) {
		foreach (array_keys((array) $rutas) as $r) {
			if (str_starts_with((string) $r, '/wp/v2/users')) {
				unset($rutas[$r]);
			}
		}
	}
	return $rutas;
});

// Sin enlaces a la API ni a oEmbed en las cabeceras (el front de WordPress no se usa)
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('template_redirect', 'rest_output_link_header', 11);
remove_action('wp_head', 'wp_oembed_add_discovery_links');

// ── Usuarios: sin enumeración ────────────────────────────────────────────────────────────────

// ?author=1 → /author/usuario/ revelaría el nombre de acceso: el front no los atiende (aunque cambie el tema)
add_action('template_redirect', function () {
	if (isset($_GET['author']) || is_author()) {
		wp_safe_redirect(admin_url(), 302);
		exit;
	}
}, 0);

// El mismo mensaje si falla el usuario o la contraseña
add_filter('authenticate', function ($usuario) {
	if (is_wp_error($usuario) && array_intersect($usuario->get_error_codes(), ['invalid_username', 'invalid_email', 'incorrect_password'])) {
		return new WP_Error('sunqu_acceso', '<strong>Error:</strong> el usuario o la contraseña no son correctos.');
	}
	return $usuario;
}, 100);

// «¿Olvidaste tu contraseña?» responde igual exista o no la cuenta (sin enviar correo si no existe)
add_action('lostpassword_post', function ($errores, $datos_usuario) {
	if ($datos_usuario) {
		return;
	}
	$codigos = $errores->get_error_codes();
	if ($codigos && array_diff($codigos, ['invalid_email', 'invalidcombo'])) {
		return; // p. ej. el campo vacío: ese error sí se muestra
	}
	wp_safe_redirect(add_query_arg('checkemail', 'confirm', wp_login_url()));
	exit;
}, 10, 2);

// ── Acceso: límite de intentos por IP ────────────────────────────────────────────────────────

function sunqu_acceso_clave(): string {
	return 'sunqu_acc_' . md5(sunqu_ip_cliente());
}

// Después de validar (prioridad 99): con la IP en espera, ni una contraseña correcta entra
add_filter('authenticate', function ($usuario, $nombre = '') {
	if ((string) $nombre === '' && !($usuario instanceof WP_User)) {
		return $usuario; // formulario vacío o solo la cookie de prueba
	}
	if ((int) get_transient(sunqu_acceso_clave()) >= SUNQU_ACCESO_INTENTOS) {
		return new WP_Error('sunqu_espera', '<strong>Demasiados intentos.</strong> Por seguridad, espera 15 minutos antes de volver a intentarlo.');
	}
	return $usuario;
}, 99, 2);

add_action('wp_login_failed', function ($nombre, $error = null) {
	if ($error instanceof WP_Error && in_array('sunqu_espera', $error->get_error_codes(), true)) {
		return; // ya está en espera: no se alarga
	}
	$k = sunqu_acceso_clave();
	set_transient($k, (int) get_transient($k) + 1, SUNQU_ACCESO_ESPERA);
}, 10, 2);

add_action('wp_login', fn() => delete_transient(sunqu_acceso_clave()));

// ── Contraseñas de aplicación ────────────────────────────────────────────────────────────────

add_filter('wp_is_application_passwords_available', fn($disponible) => defined('SUNQU_APP_PASSWORDS') && SUNQU_APP_PASSWORDS ? $disponible : false);

// ── Biblioteca de medios ─────────────────────────────────────────────────────────────────────

/** Tipos que se pueden subir: imágenes, video y PDF (sin HTML, SVG, scripts ni documentos ejecutables). */
add_filter('upload_mimes', function ($tipos) {
	$permitidos = ['jpg|jpeg|jpe', 'gif', 'png', 'webp', 'avif', 'heic', 'heif', 'mp4|m4v', 'mov|qt', 'webm', 'pdf'];
	return array_intersect_key((array) $tipos, array_flip($permitidos));
}, 99);

/** /wp-content/uploads/.htaccess: los archivos subidos nunca se ejecutan como código en el servidor. */
function sunqu_proteger_uploads(): bool {
	$dir = (string) (wp_upload_dir(null, false)['basedir'] ?? '');
	if ($dir === '' || !is_dir($dir) || !wp_is_writable($dir)) {
		return false;
	}
	$f = $dir . '/.htaccess';
	$marca = '# Sunqu CMS: en la biblioteca no se ejecutan scripts';
	$actual = is_file($f) ? (string) file_get_contents($f) : '';
	if (str_contains($actual, $marca)) {
		return true;
	}
	$reglas = $marca . "\n"
		. "<FilesMatch \"\\.(?i:php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|htaccess)$\">\n\tRequire all denied\n</FilesMatch>\n";
	return (bool) file_put_contents($f, $reglas . ($actual !== '' ? "\n" . $actual : ''));
}

add_action('admin_init', function () {
	if ((int) get_option('sunqu_seguridad_v', 0) < SUNQU_SEGURIDAD_V && current_user_can('manage_options')) {
		if (sunqu_proteger_uploads()) {
			update_option('sunqu_seguridad_v', SUNQU_SEGURIDAD_V, false);
		}
	}
});

// ── Cabeceras de seguridad (panel, acceso, API y front) ───────────────────────────────────────

function sunqu_cabeceras_seguridad(): void {
	if (headers_sent()) {
		return;
	}
	header_remove('X-Powered-By');
	header('X-Content-Type-Options: nosniff');
	header('Referrer-Policy: strict-origin-when-cross-origin');
	header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()');
	if (is_ssl()) {
		header('Strict-Transport-Security: max-age=31536000');
	}
}
add_action('send_headers', 'sunqu_cabeceras_seguridad');
add_action('admin_init', 'sunqu_cabeceras_seguridad');
add_action('login_init', 'sunqu_cabeceras_seguridad');
add_filter('rest_pre_serve_request', function ($servido) {
	sunqu_cabeceras_seguridad();
	return $servido;
}, 5);

// ── Estado de seguridad (Sunqu › Seguridad) ──────────────────────────────────────────────────

add_action('admin_menu', function () {
	add_submenu_page('sunqu', 'Seguridad', 'Seguridad', 'manage_options', 'sunqu-seguridad', 'sunqu_pagina_seguridad');
}, 20);

/**
 * Revisión del sitio: cada punto es [estado ('ok'|'aviso'|'mal'), título, detalle]. Solo lee; no cambia nada.
 */
function sunqu_revision_seguridad(): array {
	require_once ABSPATH . 'wp-admin/includes/update.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$r = [];
	$add = function (string $estado, string $titulo, string $detalle) use (&$r) {
		$r[] = [$estado, $titulo, $detalle];
	};

	// Versiones
	global $wp_version;
	$core = get_core_updates(['dismissed' => false]);
	$hay_core = is_array($core) && isset($core[0]->response) && $core[0]->response === 'upgrade';
	$add($hay_core ? 'mal' : 'ok', 'WordPress ' . $wp_version, $hay_core ? 'Hay una versión nueva: actualiza desde Escritorio › Actualizaciones.' : 'Al día.');
	$php = PHP_VERSION;
	$add(version_compare($php, '8.2', '>=') ? 'ok' : 'aviso', 'PHP ' . $php, version_compare($php, '8.2', '>=') ? 'Versión con soporte de seguridad.' : 'Sube a PHP 8.3 en hPanel › Avanzado › Configuración de PHP.');
	$plugins = get_plugins();
	$pend = array_intersect_key($plugins, (array) get_plugin_updates());
	$add($pend ? 'mal' : 'ok', 'Plugins actualizados', $pend ? 'Actualizar: ' . implode(', ', array_map(fn($p) => $p['Name'], $pend)) . '.' : 'Todos al día.');
	$inactivos = array_diff_key($plugins, array_flip((array) get_option('active_plugins', [])));
	$add($inactivos ? 'aviso' : 'ok', 'Plugins sin usar', $inactivos ? 'Desactivados pero instalados (bórralos si no los usas): ' . implode(', ', array_map(fn($p) => $p['Name'], $inactivos)) . '.' : 'Solo están instalados los que se usan.');
	$temas = wp_get_themes();
	$activo = get_stylesheet();
	$otros = array_filter(array_keys($temas), fn($t) => $t !== $activo && $t !== get_template());
	$add(count($otros) > 1 ? 'aviso' : 'ok', 'Temas', count($otros) > 1 ? 'Instalados sin usar: ' . implode(', ', $otros) . '. Deja como mucho uno de respaldo (por ejemplo, el más reciente de WordPress).' : 'Activo: ' . $activo . '.');

	// Usuarios
	$admins = get_users(['role' => 'administrator', 'fields' => ['user_login']]);
	$add(count($admins) > 2 ? 'aviso' : 'ok', 'Administradores: ' . count($admins), count($admins) > 2 ? 'Revisa Usuarios: cada persona con su propia cuenta y el rol mínimo (Editor para editar contenido).' : 'Cada persona debe tener su propia cuenta; no compartan contraseñas.');
	$nombres = array_map(fn($u) => strtolower($u->user_login), $admins);
	$obvio = array_intersect($nombres, ['admin', 'administrator', 'administrador', 'root', 'sunqu', 'test']);
	$add($obvio ? 'mal' : 'ok', 'Nombres de usuario', $obvio ? 'Hay un administrador con un nombre fácil de adivinar. Crea otro administrador con un nombre propio y borra ese (asignando su contenido).' : 'Ningún administrador usa un nombre obvio.');
	$registro = (bool) get_option('users_can_register');
	$add($registro ? 'mal' : 'ok', 'Registro de usuarios', $registro ? 'Cualquiera puede crear una cuenta: desmarca «Cualquiera puede registrarse» en Ajustes › Generales.' : 'Cerrado.');
	$add('aviso', 'Verificación en dos pasos (2FA)', 'WordPress no la trae. Recomendado: el plugin «Two Factor» (de los colaboradores de WordPress) para cada administrador, y 2FA también en la cuenta de Hostinger.');

	// Configuración
	$add(is_ssl() && str_starts_with(home_url(), 'https://') ? 'ok' : 'mal', 'HTTPS', is_ssl() && str_starts_with(home_url(), 'https://') ? 'El panel y la API van por HTTPS.' : 'Ajustes › Generales: las dos direcciones deben empezar con https://.');
	$debug = defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY);
	$add($debug ? 'mal' : 'ok', 'Errores en pantalla', $debug ? 'WP_DEBUG muestra errores a los visitantes: en wp-config.php deja WP_DEBUG en false (o WP_DEBUG_DISPLAY en false).' : 'No se muestran errores a los visitantes.');
	$log = WP_CONTENT_DIR . '/debug.log';
	$add(is_file($log) ? 'aviso' : 'ok', 'debug.log', is_file($log) ? 'Existe wp-content/debug.log: bórralo si no lo estás usando (puede contener rutas y datos).' : 'No hay archivo de depuración.');
	$cfg = file_exists(ABSPATH . 'wp-config.php') ? ABSPATH . 'wp-config.php' : dirname(ABSPATH) . '/wp-config.php';
	$perm = is_file($cfg) ? substr(sprintf('%o', fileperms($cfg)), -3) : '';
	$add($perm !== '' && (int) $perm[2] > 0 ? 'aviso' : 'ok', 'Permisos de wp-config.php' . ($perm ? " ($perm)" : ''), $perm !== '' && (int) $perm[2] > 0 ? 'Otros usuarios del servidor pueden leerlo: cámbialo a 600 o 640 en el Administrador de archivos.' : 'Correctos.');
	$add(defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT ? 'ok' : 'mal', 'Editor de archivos del panel', 'Desactivado: nadie puede cambiar el código de temas o plugins desde el panel.');
	$up = (string) (wp_upload_dir(null, false)['basedir'] ?? '');
	$protegido = $up !== '' && is_file($up . '/.htaccess') && str_contains((string) file_get_contents($up . '/.htaccess'), 'Sunqu CMS');
	$add($protegido ? 'ok' : 'mal', 'Biblioteca de medios', $protegido ? 'En /wp-content/uploads no se ejecutan scripts.' : 'No se pudo escribir wp-content/uploads/.htaccess (permisos de la carpeta).');
	foreach (['readme.html', 'license.txt'] as $f) {
		if (is_file(ABSPATH . $f)) {
			$add('aviso', $f, 'Muestra la versión de WordPress a cualquiera: puedes borrarlo (vuelve con cada actualización).');
		}
	}
	$add((int) get_option('blog_public') === 0 ? 'ok' : 'aviso', 'Visibilidad en buscadores', (int) get_option('blog_public') === 0 ? 'El CMS pide no ser indexado.' : 'Marca «Disuadir a los motores de búsqueda» en Ajustes › Lectura (la web tiene su propio SEO).');

	// Conexión con la web
	$web = (string) get_option('sunqu_web', '');
	$add($web !== '' ? 'ok' : 'mal', 'URL de la web', $web !== '' ? 'Solo ' . sunqu_origen_url($web) . ' (y los orígenes permitidos) puede usar la API desde el navegador.' : 'Complétala en Sunqu › Conexión: sin ella la API solo acepta ' . SUNQU_WEB_DEFECTO . '.');
	$add(get_option('sunqu_purga_token') ? 'ok' : 'aviso', 'Token de purga', get_option('sunqu_purga_token') ? 'Configurado.' : 'Sin token, la web tarda hasta 5 minutos en mostrar lo que publicas.');
	$add(sunqu_smtp() ? 'ok' : 'aviso', 'Correo (SMTP)', sunqu_smtp() ? 'Los avisos salen por SMTP autenticado.' : 'Sin SMTP los avisos pueden caer en spam: ver docs/FORMULARIOS.md.');
	$add(defined('SUNQU_TURNSTILE_SECRET') && SUNQU_TURNSTILE_SECRET ? 'ok' : 'aviso', 'Verificación anti-bots (Turnstile)', defined('SUNQU_TURNSTILE_SECRET') && SUNQU_TURNSTILE_SECRET ? 'Los formularios exigen la verificación de Cloudflare Turnstile.' : 'Opcional: los formularios ya tienen campo trampa, tiempo mínimo y límite por IP. Ver docs/SECURITY.md para activarla.');

	// Scripts que la web inserta en todas sus páginas (Sunqu › Scripts y seguimiento)
	if (function_exists('sunqu_scripts')) {
		$sc = array_filter(sunqu_scripts()['lista'], fn($x) => $x['activo']);
		$siempre = array_filter($sc, fn($x) => $x['categoria'] === 'necesario');
		$add($siempre ? 'aviso' : 'ok', 'Scripts de la web: ' . count($sc) . ' activos',
			!$sc ? 'Ninguno. Los que agregues en Sunqu › Scripts y seguimiento se ejecutan en todas las páginas: revísalos cuando cambie el historial.'
				: ($siempre ? count($siempre) . ' se cargan sin pedir permiso («Siempre»): confirma que no miden ni hacen publicidad, o que son de Google en Consent Mode.' : 'Todos esperan el permiso del aviso de cookies.'));
	}

	// Protecciones del plugin
	$add('ok', 'XML-RPC y comentarios', 'Desactivados.');
	$add('ok', 'API pública', 'Sin sesión solo responden /sunqu/v1/contenido y /sunqu/v1/formularios. Usuarios, entradas y el índice de la API piden sesión.');
	$add('ok', 'Intentos de acceso', SUNQU_ACCESO_INTENTOS . ' intentos fallidos por IP cada 15 minutos; mensajes sin revelar si el usuario existe.');
	$add(defined('SUNQU_APP_PASSWORDS') && SUNQU_APP_PASSWORDS ? 'aviso' : 'ok', 'Contraseñas de aplicación', defined('SUNQU_APP_PASSWORDS') && SUNQU_APP_PASSWORDS ? 'Activadas por SUNQU_APP_PASSWORDS: úsalas solo para integraciones y revócalas cuando no se usen.' : 'Desactivadas (ninguna integración las necesita).');

	// Lo que se revisa en Hostinger
	$add('aviso', 'Copias de seguridad', 'Revisa en hPanel › Archivos › Copias de seguridad que existan copias recientes de archivos y base de datos, y prueba restaurar una en un subdominio de prueba. Descarga también «Descargar todo el contenido (JSON)» y el libro de reclamaciones cada mes.');
	return $r;
}

function sunqu_pagina_seguridad(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$iconos = ['ok' => ['✔', '#008a20'], 'aviso' => ['▲', '#996800'], 'mal' => ['✖', '#b32d2e']];
	echo '<div class="wrap"><h1>Sunqu · Seguridad</h1>';
	echo '<p>Revisión del CMS (solo lectura). Las medidas y los riesgos pendientes están en <code>docs/SECURITY.md</code> del repositorio de la web.</p>';
	echo '<table class="widefat striped" style="max-width:980px"><tbody>';
	foreach (sunqu_revision_seguridad() as [$estado, $titulo, $detalle]) {
		[$icono, $color] = $iconos[$estado];
		echo '<tr><td style="width:28px;color:' . esc_attr($color) . ';font-weight:700">' . esc_html($icono) . '</td><td style="width:32%"><strong>' . esc_html($titulo) . '</strong></td><td>' . esc_html($detalle) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
