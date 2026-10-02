<?php
/**
 * API de lectura para la web.
 *
 *   GET /wp-json/sunqu/v1/contenido?secciones=sitio,journeys,anfitriones
 *       → { "sitio": {…}, "journeys": {…}, "anfitriones": {…} }   (mismo formato que content/*.json)
 *   GET /wp-json/sunqu/v1/contenido/{seccion}
 *       → contenido de una sección
 *   GET /wp-json/sunqu/v1/esquema
 *       → schema.json (para herramientas y documentación)
 *
 * ?lang=en (o pt): el contenido traducido con Loco Translate (includes/idiomas.php); lo no traducido sale en español.
 * Las respuestas se guardan en caché (transient) y se invalidan al guardar cualquier contenido.
 * Cada sección con imágenes de la biblioteca trae «_imagenes»: { url: srcset } para la web y cada elemento,
 * «_modificado» (fecha de su última edición, para el sitemap y los datos estructurados).
 * Solo sale lo publicado: borradores, reseñas pendientes y envíos de formularios no aparecen.
 * CORS (todas las rutas de la API): solo los orígenes de Sunqu › Conexión; si esa lista está vacía, el de
 * «URL de la web»; si tampoco hay, https://sunqujourneys.com. Nunca «cualquiera» ni con credenciales.
 * El esquema (/esquema) pide sesión: la web no lo necesita.
 */

defined('ABSPATH') || exit;

/** Web pública por defecto (si en Sunqu › Conexión no hay URL de la web ni orígenes). */
const SUNQU_WEB_DEFECTO = 'https://sunqujourneys.com';

add_action('rest_api_init', function () {
	register_rest_route('sunqu/v1', '/contenido', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'args' => ['secciones' => ['type' => 'string', 'required' => false], 'lang' => ['type' => 'string', 'required' => false]],
		'callback' => function (WP_REST_Request $req) {
			$p = $req->get_param('secciones');
			$pedidas = array_slice(array_unique(array_filter(array_map('sanitize_key', explode(',', is_string($p) ? $p : '')))), 0, 20);
			$pedidas = $pedidas ?: sunqu_secciones();
			$lang = sunqu_idioma_peticion($req);
			$out = [];
			foreach ($pedidas as $s) {
				$datos = sunqu_exportar($s, true, $lang);
				if ($datos === null) {
					return new WP_Error('sunqu_seccion', 'Sección desconocida: ' . $s, ['status' => 404]);
				}
				$out[$s] = $s === 'sitio' ? sunqu_sitio_extras($datos, $lang) : $datos;
			}
			return sunqu_respuesta($out);
		},
	]);

	register_rest_route('sunqu/v1', '/contenido/(?P<seccion>[a-z]+)', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function (WP_REST_Request $req) {
			// De la ruta: con $req['seccion'], un ?seccion= en la URL tendría prioridad
			$s = (string) ($req->get_url_params()['seccion'] ?? '');
			$lang = sunqu_idioma_peticion($req);
			$datos = sunqu_exportar($s, true, $lang);
			return $datos === null
				? new WP_Error('sunqu_seccion', 'Sección desconocida', ['status' => 404])
				: sunqu_respuesta($s === 'sitio' ? sunqu_sitio_extras($datos, $lang) : $datos);
		},
	]);

	register_rest_route('sunqu/v1', '/esquema', [
		'methods' => 'GET',
		'permission_callback' => fn() => current_user_can('edit_posts'),
		'callback' => fn() => rest_ensure_response(sunqu_schema()),
	]);
});

/**
 * Lo que la sección sitio agrega para la web, además de su contenido: idiomas (_idiomas, _i18n) y los scripts de
 * Sunqu › Scripts y seguimiento (_scripts). No se guarda en la caché del contenido: cambia con otros ajustes.
 */
function sunqu_sitio_extras(array $sitio, string $lang): array {
	$sitio = sunqu_i18n_sitio($sitio, $lang);
	if (function_exists('sunqu_scripts_publicos')) {
		$sitio['_scripts'] = sunqu_scripts_publicos();
	}
	return $sitio;
}

/**
 * Respuesta con caché corta para navegadores (vista previa estática). LiteSpeed Cache del CMS no la guarda:
 * no se purgaría al editar las páginas de ajustes y la web de producción ya tiene su propia caché.
 */
function sunqu_respuesta(array $datos): WP_REST_Response {
	do_action('litespeed_control_set_nocache', 'Sunqu: la web guarda su propia copia del contenido');
	$r = rest_ensure_response($datos);
	$r->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
	$r->header('X-LiteSpeed-Cache-Control', 'no-cache');
	return $r;
}

/** Origen (esquema + host + puerto) de una URL: https://sunqujourneys.com */
function sunqu_origen_url(string $url): string {
	$p = wp_parse_url($url);
	return !empty($p['host']) ? strtolower(($p['scheme'] ?? 'https') . '://' . $p['host']) . (isset($p['port']) ? ':' . $p['port'] : '') : '';
}

/**
 * Orígenes que pueden usar la API desde el navegador: la lista de Sunqu › Conexión o, si está vacía, el de
 * «URL de la web»; si tampoco hay, la web de producción (SUNQU_WEB_DEFECTO).
 */
function sunqu_origenes(): array {
	$raw = (string) get_option('sunqu_origenes', '');
	$lista = array_values(array_filter(array_map(fn($o) => sunqu_origen_url(trim($o)), preg_split('/[\s,]+/', $raw) ?: [])));
	if (!$lista) {
		$lista[] = sunqu_origen_url((string) get_option('sunqu_web', '')) ?: SUNQU_WEB_DEFECTO;
	}
	return array_values(array_unique($lista));
}

/** ¿Puede este origen usar la API desde el navegador? El propio CMS (panel, editor) siempre. */
function sunqu_origen_permitido(string $origen): bool {
	$origen = strtolower(rtrim($origen, '/'));
	return in_array($origen, sunqu_origenes(), true) || in_array($origen, [sunqu_origen_url(home_url()), sunqu_origen_url(site_url())], true);
}

// CORS de toda la API: WordPress responde a cualquier origen y con credenciales; aquí solo quedan los permitidos,
// y las rutas de la web (sunqu/v1) nunca aceptan credenciales (la web no manda cookies).
add_filter('rest_pre_serve_request', function ($served, $result, $request) {
	$sunqu = str_starts_with((string) $request->get_route(), '/sunqu/v1');
	if ($sunqu) {
		header('X-Robots-Tag: noindex, nofollow'); // la API no debe aparecer en buscadores
	}
	$origen = (string) get_http_origin();
	if ($origen === '') {
		return $served;
	}
	if (!sunqu_origen_permitido($origen)) {
		foreach (['Allow-Origin', 'Allow-Credentials', 'Allow-Methods', 'Allow-Headers', 'Expose-Headers'] as $h) {
			header_remove('Access-Control-' . $h);
		}
	} elseif ($sunqu) {
		header_remove('Access-Control-Allow-Credentials');
	}
	return $served;
}, 20, 3);
