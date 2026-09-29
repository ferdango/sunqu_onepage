<?php
/**
 * Formularios de la web.
 *
 *   POST /wp-json/sunqu/v1/formularios/solicitud     «Diseña tu viaje» (inicio, journey, descubre)
 *        nombre*, email*, mes, sentir, mensaje, guardados, pagina
 *   POST /wp-json/sunqu/v1/formularios/resena        «Comparte tu viaje» (recuerda) → Reseñas › Pendientes
 *        nombre*, pais*, tema, texto*, enlace, estrellas, permiso*, fotos: [{ nombre, data: "data:image/jpeg;base64,…" }]
 *   POST /wp-json/sunqu/v1/formularios/reclamacion   Libro de reclamaciones → Sunqu › Reclamaciones + correos
 *        nombre*, email*, tipoDoc*, numDoc*, celular*, domicilio*, apoderado (* si menor), servicio*, monto, fecha,
 *        descripcion*, tipo*, detalle*, pedido*, acepta*, codigo, identificacion, menor
 *   (* = obligatorio, igual que en el formulario de la web)
 *
 * Respuesta: { ok: true, id } · Error: { code, message } con estado 400/429.
 * Anti-spam: límite de 8 envíos cada 10 minutos por IP y tipo, campo trampa «website» (invisible en la web) y
 * tiempo mínimo de llenado (_t, milisegundos desde que se abrió la página: menos de 2,5 s es un bot).
 * Los envíos que parecen de bots responden «ok» (para no darles pistas) y no se procesan: las solicitudes y
 * reseñas se descartan; las hojas de reclamación quedan como borrador «[posible spam]», sin correos, por si
 * fuera una persona.
 * Libro de reclamaciones: el código correlativo (SJ-2026-00001) lo asigna el servidor. La constancia al
 * consumidor se envía como máximo 3 veces por correo cada 24 h (el formulario no sirve para enviar correos a terceros).
 * Correos: se envían con wp_mail (en producción configura SMTP, p. ej. con WP Mail SMTP).
 */

defined('ABSPATH') || exit;

/** Milisegundos mínimos entre abrir la página y enviar un formulario (menos = bot). */
const SUNQU_TIEMPO_MINIMO = 2500;

/** Tipos privados donde quedan los envíos (visibles en el panel, no en la API pública). */
function sunqu_registrar_tipos_envios(): void {
	$comun = ['public' => false, 'show_ui' => true, 'show_in_menu' => 'sunqu', 'supports' => ['title', 'editor'], 'capability_type' => 'post', 'map_meta_cap' => true, 'rewrite' => false];
	register_post_type('sunqu_solicitud', $comun + ['labels' => ['name' => 'Solicitudes de viaje', 'singular_name' => 'Solicitud', 'all_items' => 'Solicitudes de viaje', 'edit_item' => 'Solicitud']]);
	register_post_type('sunqu_reclamacion', $comun + ['labels' => ['name' => 'Libro de reclamaciones', 'singular_name' => 'Hoja de reclamación', 'all_items' => 'Libro de reclamaciones', 'edit_item' => 'Hoja de reclamación']]);
}
add_action('init', 'sunqu_registrar_tipos_envios');

add_action('rest_api_init', function () {
	register_rest_route('sunqu/v1', '/formularios/(?P<tipo>solicitud|resena|reclamacion)', [
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => 'sunqu_recibir_formulario',
	]);
});

function sunqu_recibir_formulario(WP_REST_Request $req) {
	// De la ruta, no de $req['tipo']: el libro de reclamaciones tiene un campo «tipo» (Reclamo/Queja) en el cuerpo
	$tipo = (string) ($req->get_url_params()['tipo'] ?? '');
	$d = $req->get_json_params() ?: $req->get_body_params();
	$d = is_array($d) ? $d : [];

	// Límite por IP (cuenta también los envíos de bots)
	$lk = 'sunqu_rl_' . $tipo . '_' . md5(sunqu_ip_cliente());
	$n = (int) get_transient($lk);
	if ($n >= 8) {
		return new WP_Error('sunqu_limite', 'Demasiados envíos. Inténtalo en unos minutos.', ['status' => 429]);
	}
	set_transient($lk, $n + 1, 10 * MINUTE_IN_SECONDS);

	$v = fn($k) => is_scalar($d[$k] ?? null) ? (string) $d[$k] : '';
	$t = fn($k, $max = 500) => mb_substr(sanitize_text_field($v($k)), 0, $max);
	$ta = fn($k, $max = 5000) => mb_substr(sanitize_textarea_field($v($k)), 0, $max);

	// Campo trampa lleno o envío instantáneo: responde «ok» y no se procesa
	if ($v('website') !== '' || (int) $v('_t') < SUNQU_TIEMPO_MINIMO) {
		if ($tipo === 'reclamacion') {
			$campos = sunqu_campos_reclamacion($d, $t, $ta, 'Sin asignar: posible spam (la web mostró ' . ($t('codigo', 40) ?: '—') . ')');
			sunqu_guardar_envio('sunqu_reclamacion', '[posible spam] ' . $campos['Consumidor'], $campos, 'draft');
		}
		return ['ok' => true];
	}

	$falta = function (array $req) use ($v) {
		foreach ($req as $k) {
			if (trim($v($k)) === '') {
				return new WP_Error('sunqu_campo', 'Falta el campo «' . $k . '».', ['status' => 400]);
			}
		}
		return null;
	};

	switch ($tipo) {
		case 'solicitud':
			if ($e = $falta(['nombre', 'email'])) {
				return $e;
			}
			if (!is_email($v('email'))) {
				return new WP_Error('sunqu_email', 'El correo no es válido.', ['status' => 400]);
			}
			$campos = [
				'Nombre' => $t('nombre'), 'Email' => sanitize_email($v('email')), '¿Cuándo?' => $t('mes'), '¿Qué quiere sentir?' => $t('sentir'),
				'Mensaje' => $ta('mensaje'), 'Guardados adjuntos' => $ta('guardados', 3000), 'Página' => $t('pagina'),
			];
			$id = sunqu_guardar_envio('sunqu_solicitud', $campos['Nombre'] . ' · ' . wp_date('j M Y, H:i'), $campos);
			sunqu_notificar('Nueva solicitud de viaje: ' . $campos['Nombre'], $campos, $campos['Email']);
			return ['ok' => true, 'id' => $id];

		case 'resena':
			if ($e = $falta(['nombre', 'pais', 'texto'])) {
				return $e;
			}
			if (empty($d['permiso'])) {
				return new WP_Error('sunqu_permiso', 'Falta el permiso para publicar la reseña.', ['status' => 400]);
			}
			return sunqu_guardar_resena($d, $t, $ta);

		case 'reclamacion':
			if ($e = $falta(['nombre', 'email', 'tipoDoc', 'numDoc', 'celular', 'domicilio', 'servicio', 'descripcion', 'tipo', 'detalle', 'pedido'])) {
				return $e;
			}
			if (!empty($d['menor']) && ($e = $falta(['apoderado']))) {
				return $e;
			}
			if (empty($d['acepta'])) {
				return new WP_Error('sunqu_acepta', 'Falta aceptar el tratamiento de datos personales.', ['status' => 400]);
			}
			if (!is_email($v('email'))) {
				return new WP_Error('sunqu_email', 'El correo no es válido.', ['status' => 400]);
			}
			$codigo = sunqu_codigo_reclamacion();
			$campos = sunqu_campos_reclamacion($d, $t, $ta, $codigo);
			// Constancia para el consumidor: máximo 3 por correo cada 24 h
			$rk = 'sunqu_rc_' . md5(strtolower($campos['Correo']));
			$enviadas = (int) get_transient($rk);
			$campos['Constancia por correo'] = $enviadas < 3 ? 'Enviada al consumidor' : 'No enviada: ya se enviaron 3 constancias a este correo en 24 horas';
			$id = sunqu_guardar_envio('sunqu_reclamacion', $codigo . ' · ' . $campos['Consumidor'], $campos, 'private');
			$sitio = sunqu_exportar('sitio');
			$empresa = $sitio['empresa']['name'] ?? get_bloginfo('name');
			if ($enviadas < 3) {
				set_transient($rk, $enviadas + 1, DAY_IN_SECONDS);
				wp_mail($campos['Correo'], 'Constancia de tu hoja de reclamación ' . $codigo . ' · ' . $empresa, sunqu_texto_envio($campos) . "\n\nResponderemos en un plazo máximo de 15 días hábiles.");
			}
			sunqu_notificar('Libro de reclamaciones: ' . $codigo, $campos, $campos['Correo']);
			return ['ok' => true, 'id' => $id, 'codigo' => $codigo];
	}
	return new WP_Error('sunqu_tipo', 'Formulario desconocido', ['status' => 404]);
}

/** IP del visitante para el límite de envíos. Detrás de una CDN o proxy, ajústala con el filtro «sunqu_ip_cliente». */
function sunqu_ip_cliente(): string {
	return (string) apply_filters('sunqu_ip_cliente', sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''));
}

/** Código correlativo de la hoja de reclamación (SJ-2026-00001): uno por hoja, sin repetirse aunque lleguen juntas. */
function sunqu_codigo_reclamacion(): string {
	global $wpdb;
	add_option('sunqu_reclamacion_n', '0', '', false); // no hace nada si ya existe
	$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", 'sunqu_reclamacion_n'));
	$n = (int) $wpdb->get_var('SELECT LAST_INSERT_ID()');
	wp_cache_delete('sunqu_reclamacion_n', 'options');
	return sprintf('SJ-%s-%05d', wp_date('Y'), $n);
}

/** Datos de una hoja de reclamación, con las etiquetas del libro. */
function sunqu_campos_reclamacion(array $d, callable $t, callable $ta, string $codigo): array {
	$fecha = wp_date('j \d\e F \d\e Y, H:i');
	return [
		'Código' => $codigo, 'Código de identificación' => $t('identificacion', 80), 'Fecha' => $fecha,
		'Consumidor' => $t('nombre') . (!empty($d['menor']) ? ' (menor de edad)' : ''), 'Padre, madre o apoderado' => $t('apoderado'),
		'Documento' => trim($t('tipoDoc', 20) . ' ' . $t('numDoc', 30)), 'Correo' => sanitize_email($t('email')), 'Celular' => $t('celular', 30),
		'Domicilio' => $t('domicilio'), 'Servicio' => $t('servicio'), 'Monto reclamado' => $t('monto', 40), 'Fecha del servicio' => $t('fecha', 30),
		'Descripción del servicio' => $ta('descripcion'), 'Tipo' => $t('tipo', 20), 'Detalle' => $ta('detalle'), 'Pedido' => $ta('pedido'),
		'Tratamiento de datos personales' => !empty($d['acepta']) ? 'Aceptado en el formulario (' . $fecha . ')' : 'No aceptado',
	];
}

function sunqu_guardar_envio(string $tipo, string $titulo, array $campos, string $estado = 'private'): int {
	$id = wp_insert_post([
		'post_type' => $tipo,
		'post_status' => $estado,
		'post_title' => wp_slash($titulo),
		'post_content' => wp_slash(esc_html(sunqu_texto_envio($campos))),
	]);
	if ($id && !is_wp_error($id)) {
		update_post_meta($id, '_sunqu_datos', wp_slash(wp_json_encode($campos, JSON_UNESCAPED_UNICODE)));
		return (int) $id;
	}
	return 0;
}

function sunqu_texto_envio(array $campos): string {
	$l = [];
	foreach ($campos as $k => $v) {
		if ($v !== '' && $v !== null) {
			$l[] = $k . ': ' . $v;
		}
	}
	return implode("\n", $l);
}

/** Correo de aviso a Sunqu (Sunqu › Conexión › Correo de avisos, o el correo de contacto del sitio). */
function sunqu_notificar(string $asunto, array $campos, string $responder = ''): void {
	$sitio = sunqu_exportar('sitio');
	$para = get_option('sunqu_notificar') ?: ($sitio['contacto']['email'] ?? '') ?: get_option('admin_email');
	$headers = $responder && is_email($responder) ? ['Reply-To: ' . $responder] : [];
	wp_mail($para, $asunto, sunqu_texto_envio($campos), $headers);
}

/** Reseña enviada desde la web: queda «Pendiente» en Reseñas, con sus fotos en la biblioteca. */
function sunqu_guardar_resena(array $d, callable $t, callable $ta) {
	$sec = sunqu_seccion('resenas');
	$enlace = esc_url_raw((string) ($d['enlace'] ?? ''));
	$plat = str_contains($enlace, 'tiktok.com') ? 'tiktok' : (str_contains($enlace, 'instagram.com') ? 'instagram' : 'web');
	$kind = $plat === 'tiktok' || preg_match('#instagram\.com/(reel|reels|tv)/#', $enlace) ? 'video' : ($plat === 'instagram' ? 'photo' : 'comment');
	$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
	$nombre = $t('nombre', 80) ?: 'Viajero';
	$id = wp_insert_post([
		'post_type' => $sec['post_type'],
		'post_status' => 'pending',
		'post_title' => wp_slash($nombre),
		'post_name' => 'u' . time() . wp_rand(10, 99),
	]);
	if (!$id || is_wp_error($id)) {
		return new WP_Error('sunqu_guardar', 'No se pudo guardar la reseña.', ['status' => 500]);
	}
	update_post_meta($id, '_sunqu_permiso', wp_date('c')); // constancia del permiso de publicación
	$fotos = [];
	foreach (array_slice((array) ($d['fotos'] ?? []), 0, 4) as $i => $f) {
		$att = sunqu_foto_desde_data((string) ($f['data'] ?? ''), sanitize_file_name((string) ($f['nombre'] ?? 'foto.jpg')), $id);
		if ($att) {
			$fotos[] = $att;
		}
	}
	$item = [
		'sample' => false, 'kind' => $kind, 'plat' => $plat, 'country' => $t('pais', 80) ?: 'Perú',
		'when' => $meses[(int) wp_date('n') - 1] . ' de ' . wp_date('Y'), 'topic' => $t('tema', 20) ?: 'viaje', 'what' => '',
		'caption' => $ta('texto', 2000), 'stars' => max(1, min(5, (int) ($d['estrellas'] ?? 5))), 'url' => $enlace, 'photos' => $fotos,
	];
	$ruta = 'resenas/' . $sec['post_type'];
	$media = fn($v) => $v; // las fotos ya son IDs de la biblioteca
	$relacion = fn($c, $s) => 0;
	foreach ($sec['campos'] as $f) {
		if (array_key_exists($f['name'], $item)) {
			sunqu_escribir_campo(['post', $id], $f, $f['name'], $ruta . '/' . $f['name'], $item[$f['name']], $media, $relacion);
		}
	}
	sunqu_notificar('Nueva reseña pendiente de ' . $nombre, ['Nombre' => $nombre, 'Texto' => $item['caption'], 'Enlace' => $enlace, 'Permiso de publicación' => 'Sí', 'Revisar' => admin_url('post.php?post=' . $id . '&action=edit')]);
	return ['ok' => true, 'id' => $id];
}

// Constancia visible al revisar una reseña: si llegó desde la web, cuándo aceptó el autor que se publique.
add_action('add_meta_boxes', function () {
	$sec = sunqu_seccion('resenas');
	if (!$sec) {
		return;
	}
	add_meta_box('sunqu_permiso', 'Permiso de publicación', function ($post) {
		$f = (string) get_post_meta($post->ID, '_sunqu_permiso', true);
		echo $f
			? '<p>El autor aceptó en la web que Sunqu publique su reseña, sus fotos y el enlace que compartió (' . esc_html(wp_date('j \d\e F \d\e Y, H:i', strtotime($f))) . ').</p>'
			: '<p>Esta reseña no llegó por el formulario de la web.</p>';
	}, $sec['post_type'], 'side');
});

/** data:image/…;base64 → adjunto de la biblioteca (máx. 6 MB, solo JPG/PNG/WebP). */
function sunqu_foto_desde_data(string $data, string $nombre, int $padre): int {
	if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $data, $m)) {
		return 0;
	}
	$bin = base64_decode(substr($data, strlen($m[0])), true);
	if ($bin === false || strlen($bin) > 6 * MB_IN_BYTES || !@getimagesizefromstring($bin)) {
		return 0; // vacío, demasiado grande o no es una imagen real
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
	$up = wp_upload_bits(preg_replace('/\.[^.]+$/', '', $nombre) . '.' . $ext, null, $bin);
	if (!empty($up['error'])) {
		return 0;
	}
	$att = wp_insert_attachment(['post_mime_type' => 'image/' . $m[1], 'post_title' => $nombre, 'post_status' => 'inherit'], $up['file'], $padre);
	if (!$att || is_wp_error($att)) {
		return 0;
	}
	wp_update_attachment_metadata($att, wp_generate_attachment_metadata($att, $up['file']));
	return (int) $att;
}
