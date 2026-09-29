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
 * Las respuestas se guardan en caché (transient) y se invalidan al guardar cualquier contenido.
 * Cada sección con imágenes de la biblioteca trae «_imagenes»: { url: srcset } para la web.
 * CORS: solo responden a los orígenes de Sunqu › Conexión; si esa lista está vacía, al de «URL de la
 * web»; si tampoco hay, a cualquiera (WordPress por defecto).
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
	register_rest_route('sunqu/v1', '/contenido', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'args' => ['secciones' => ['type' => 'string', 'required' => false]],
		'callback' => function (WP_REST_Request $req) {
			$p = $req->get_param('secciones');
			$pedidas = array_filter(array_map('trim', explode(',', is_string($p) ? $p : '')));
			$pedidas = $pedidas ?: sunqu_secciones();
			$out = [];
			foreach ($pedidas as $s) {
				$datos = sunqu_exportar($s);
				if ($datos === null) {
					return new WP_Error('sunqu_seccion', 'Sección desconocida: ' . $s, ['status' => 404]);
				}
				$out[$s] = $datos;
			}
			return sunqu_respuesta($out);
		},
	]);

	register_rest_route('sunqu/v1', '/contenido/(?P<seccion>[a-z]+)', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => function (WP_REST_Request $req) {
			// De la ruta: con $req['seccion'], un ?seccion= en la URL tendría prioridad
			$datos = sunqu_exportar((string) ($req->get_url_params()['seccion'] ?? ''));
			return $datos === null
				? new WP_Error('sunqu_seccion', 'Sección desconocida', ['status' => 404])
				: sunqu_respuesta($datos);
		},
	]);

	register_rest_route('sunqu/v1', '/esquema', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'callback' => fn() => rest_ensure_response(sunqu_schema()),
	]);
});

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

/** Orígenes permitidos: la lista de Sunqu › Conexión o, si está vacía, el de «URL de la web». Vacío = cualquiera. */
function sunqu_origenes(): array {
	$raw = (string) get_option('sunqu_origenes', '');
	$lista = array_values(array_filter(array_map(fn($o) => rtrim(trim($o), '/'), preg_split('/[\s,]+/', $raw))));
	if (!$lista) {
		$p = wp_parse_url((string) get_option('sunqu_web', ''));
		if (!empty($p['host'])) {
			$lista[] = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
		}
	}
	return $lista;
}

// Si hay lista de orígenes, las rutas /sunqu/v1 solo responden con CORS a esos orígenes.
add_filter('rest_pre_serve_request', function ($served, $result, $request) {
	if (!str_starts_with($request->get_route(), '/sunqu/v1')) {
		return $served;
	}
	header('X-Robots-Tag: noindex, nofollow'); // la API no debe aparecer en buscadores
	$permitidos = sunqu_origenes();
	$origen = rtrim((string) get_http_origin(), '/');
	if ($permitidos && $origen && !in_array($origen, $permitidos, true)) {
		header_remove('Access-Control-Allow-Origin');
		header_remove('Access-Control-Allow-Credentials');
	}
	return $served;
}, 20, 3);
