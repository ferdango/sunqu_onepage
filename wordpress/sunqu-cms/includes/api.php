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
 * CORS: WordPress ya responde a cualquier origen; en Sunqu › Conexión se puede limitar a una lista.
 */

defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
	register_rest_route('sunqu/v1', '/contenido', [
		'methods' => 'GET',
		'permission_callback' => '__return_true',
		'args' => ['secciones' => ['type' => 'string', 'required' => false]],
		'callback' => function (WP_REST_Request $req) {
			$pedidas = array_filter(array_map('trim', explode(',', (string) $req->get_param('secciones'))));
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
			$datos = sunqu_exportar($req['seccion']);
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

/** Respuesta con caché HTTP corta (los CDN pueden guardarla un minuto). */
function sunqu_respuesta(array $datos): WP_REST_Response {
	$r = rest_ensure_response($datos);
	$r->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
	return $r;
}

/** Orígenes permitidos (Sunqu › Conexión). Vacío = cualquiera. */
function sunqu_origenes(): array {
	$raw = (string) get_option('sunqu_origenes', '');
	return array_values(array_filter(array_map(fn($o) => rtrim(trim($o), '/'), preg_split('/[\s,]+/', $raw))));
}

// Si hay lista de orígenes, las rutas /sunqu/v1 solo responden con CORS a esos orígenes.
add_filter('rest_pre_serve_request', function ($served, $result, $request) {
	if (!str_starts_with($request->get_route(), '/sunqu/v1')) {
		return $served;
	}
	$permitidos = sunqu_origenes();
	$origen = rtrim((string) get_http_origin(), '/');
	if ($permitidos && $origen && !in_array($origen, $permitidos, true)) {
		header_remove('Access-Control-Allow-Origin');
		header_remove('Access-Control-Allow-Credentials');
	}
	return $served;
}, 20, 3);
