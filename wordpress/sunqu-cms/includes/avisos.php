<?php
/**
 * Avisos de los formularios: correo a Sunqu y, si se configura, notificación al celular.
 *
 * ── Correo ────────────────────────────────────────────────────────────────────────────────────
 * Destino: Sunqu › Conexión › Avisos › «Correos que reciben los avisos» (uno o varios, separados por comas).
 *          Si está vacío: la constante SUNQU_CORREO_AVISOS de wp-config.php o, si tampoco está,
 *          sunqujourneys@gmail.com.
 * Envío:   por SMTP si wp-config.php define SUNQU_SMTP_HOST (recomendado; sin SMTP los correos del servidor
 *          suelen caer en spam o no llegar a Gmail). Si no, se usa wp_mail tal cual (o el plugin de SMTP instalado).
 *
 *     define('SUNQU_SMTP_HOST', 'smtp.hostinger.com');
 *     define('SUNQU_SMTP_PORT', 465);                         // 465 con 'ssl' · 587 con 'tls'
 *     define('SUNQU_SMTP_SECURE', 'ssl');                     // 'ssl', 'tls' o '' (sin cifrado, solo en local)
 *     define('SUNQU_SMTP_USER', 'avisos@sunqujourneys.com');
 *     define('SUNQU_SMTP_PASS', '…');
 *     define('SUNQU_MAIL_FROM', 'avisos@sunqujourneys.com');  // opcional: por defecto, SUNQU_SMTP_USER
 *     define('SUNQU_MAIL_FROM_NAME', 'Sunqu Journeys');       // opcional
 *
 * Cada correo sale en HTML (con versión de texto) y con «Responder a» = el correo del visitante. Cada envío
 * guarda en su ficha si el aviso salió o el error, y se puede reenviar desde ahí.
 *
 * ── Celular (opcional) ────────────────────────────────────────────────────────────────────────
 * Telegram: token del bot + chat ID (Sunqu › Conexión › Avisos). El token también puede ir en wp-config.php
 *           como SUNQU_TELEGRAM_TOKEN. Llega como notificación instantánea de la app de Telegram.
 * Webhook:  una URL que recibe POST JSON { tipo, titulo, texto, url, fecha } → Make, Zapier o n8n pueden
 *           reenviarlo a WhatsApp, Slack, SMS, etc.
 * Los avisos al celular no llevan datos personales: solo el tipo de formulario, el nombre y el enlace al panel.
 */

defined('ABSPATH') || exit;

const SUNQU_CORREO_AVISOS_DEFECTO = 'sunqujourneys@gmail.com';

/** Nombre legible de cada formulario (para asuntos, avisos y el panel). */
function sunqu_tipos_formulario(): array {
	return [
		'solicitud' => 'Solicitud de viaje',
		'resena' => 'Reseña para revisar',
		'reclamacion' => 'Hoja de reclamación',
		'prueba' => 'Aviso de prueba',
	];
}

/** Correos que reciben los avisos. */
function sunqu_correos_avisos(): array {
	$lista = sunqu_lista_correos((string) get_option('sunqu_notificar', ''));
	if (!$lista && defined('SUNQU_CORREO_AVISOS')) {
		$lista = sunqu_lista_correos((string) SUNQU_CORREO_AVISOS);
	}
	return $lista ?: [SUNQU_CORREO_AVISOS_DEFECTO];
}

/** «a@x.com, b@y.com» → ['a@x.com', 'b@y.com'] (solo correos válidos, sin repetir). */
function sunqu_lista_correos(string $raw): array {
	$out = [];
	foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $c) {
		$c = sanitize_email($c);
		if ($c && is_email($c) && !in_array($c, $out, true)) {
			$out[] = $c;
		}
	}
	return $out;
}

// ── SMTP ─────────────────────────────────────────────────────────────────────────────────────

function sunqu_smtp(): ?array {
	if (!defined('SUNQU_SMTP_HOST') || !SUNQU_SMTP_HOST) {
		return null;
	}
	$user = defined('SUNQU_SMTP_USER') ? (string) SUNQU_SMTP_USER : '';
	return [
		'host' => (string) SUNQU_SMTP_HOST,
		'port' => defined('SUNQU_SMTP_PORT') ? (int) SUNQU_SMTP_PORT : 465,
		'secure' => defined('SUNQU_SMTP_SECURE') ? (string) SUNQU_SMTP_SECURE : 'ssl',
		'user' => $user,
		'pass' => defined('SUNQU_SMTP_PASS') ? (string) SUNQU_SMTP_PASS : '',
		'from' => defined('SUNQU_MAIL_FROM') && SUNQU_MAIL_FROM ? (string) SUNQU_MAIL_FROM : $user,
		'from_name' => defined('SUNQU_MAIL_FROM_NAME') ? (string) SUNQU_MAIL_FROM_NAME : 'Sunqu Journeys',
	];
}

// Con SMTP, todos los correos de WordPress (también «¿Olvidaste tu contraseña?») salen del buzón autenticado:
// wp_mail() fija el remitente antes de phpmailer_init, y «wordpress@<host>» puede no ser válido o no pasar SPF.
add_filter('wp_mail_from', fn($de) => ($s = sunqu_smtp()) && $s['from'] ? $s['from'] : $de);
add_filter('wp_mail_from_name', fn($nombre) => ($s = sunqu_smtp()) && $s['from'] ? $s['from_name'] : $nombre);

add_action('phpmailer_init', function ($mail) {
	if ($smtp = sunqu_smtp()) {
		$mail->isSMTP();
		$mail->Host = $smtp['host'];
		$mail->Port = $smtp['port'];
		$mail->SMTPAuth = $smtp['user'] !== '';
		$mail->Username = $smtp['user'];
		$mail->Password = $smtp['pass'];
		$mail->SMTPSecure = in_array($smtp['secure'], ['ssl', 'tls'], true) ? $smtp['secure'] : '';
		$mail->SMTPAutoTLS = $smtp['secure'] !== '';
		$mail->Timeout = 15;
		if ($smtp['from']) {
			$mail->setFrom($smtp['from'], $smtp['from_name'], false);
			$mail->Sender = $smtp['from']; // Return-Path = el buzón autenticado (SPF alineado)
		}
	}
	// Versión de texto del correo HTML que se está enviando (la deja sunqu_enviar_correo)
	if (!empty($GLOBALS['sunqu_texto_alterno'])) {
		$mail->AltBody = $GLOBALS['sunqu_texto_alterno'];
	}
});

// El último error de envío (para el panel y la ficha del envío)
add_action('wp_mail_failed', function ($error) {
	$GLOBALS['sunqu_error_correo'] = $error instanceof WP_Error ? $error->get_error_message() : 'Error desconocido';
	update_option('sunqu_correo_error', ['fecha' => wp_date('c'), 'error' => $GLOBALS['sunqu_error_correo']], false);
});

/**
 * Envía un correo HTML con su versión de texto. Devuelve ['ok' => bool, 'error' => string].
 */
function sunqu_enviar_correo($para, string $asunto, string $html, string $texto, array $headers = []): array {
	$GLOBALS['sunqu_error_correo'] = '';
	$GLOBALS['sunqu_texto_alterno'] = $texto;
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$ok = wp_mail($para, $asunto, $html, $headers);
	$GLOBALS['sunqu_texto_alterno'] = '';
	if ($ok) {
		delete_option('sunqu_correo_error');
	}
	return ['ok' => (bool) $ok, 'error' => $ok ? '' : ($GLOBALS['sunqu_error_correo'] ?: 'wp_mail no pudo enviar el correo.')];
}

/** URL pública de la web (Sunqu › Conexión › URL de la web), con idioma: sunqu_url_web('en', '/recuerda') → …/en/recuerda. */
function sunqu_url_web(string $lang = '', string $ruta = ''): string {
	$web = untrailingslashit((string) get_option('sunqu_web', '')) ?: SUNQU_WEB_DEFECTO;
	return $lang === '' ? $web . $ruta : $web . '/' . $lang . ($ruta === '' || $ruta === '/' ? '/' : $ruta);
}

/**
 * Correo con la marca: encabezado, introducción, tabla de datos y botones.
 * $botones: [['texto' => 'Ver en el panel', 'url' => '…'], …]
 * $o (correos a quien llenó un formulario): visitante => true (logo y pie para la persona), lang => 'en',
 *    tr => traductor de los textos fijos, enlace => la web en su idioma.
 */
function sunqu_correo_html(string $titulo, string $intro, array $campos, array $botones = [], array $o = []): string {
	$e = fn($s) => esc_html((string) $s);
	$msg = $o['tr'] ?? fn($s) => $s; // $msg('…'): tools/extraer-textos.py lo junta para traducir
	$visitante = !empty($o['visitante']);
	$lang = preg_match('/^[a-z]{2}$/', (string) ($o['lang'] ?? '')) ? $o['lang'] : 'es';
	$filas = '';
	foreach ($campos as $k => $v) {
		if ($v === '' || $v === null) {
			continue;
		}
		$v = (string) $v;
		if (is_email($v)) {
			$valor = '<a href="mailto:' . esc_attr($v) . '" style="color:#736d47">' . $e($v) . '</a>';
		} elseif (str_contains($v, ' | ')) {
			$valor = '<ul style="margin:0;padding-left:18px">' . implode('', array_map(fn($x) => '<li>' . $e($x) . '</li>', explode(' | ', $v))) . '</ul>';
		} else {
			$valor = nl2br($e($v));
		}
		$filas .= '<tr><td style="padding:12px 0;border-top:1px solid #e3e6e8;vertical-align:top;width:34%;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#736d47">' . $e($k) . '</td>'
			. '<td style="padding:12px 0 12px 16px;border-top:1px solid #e3e6e8;font-size:15px;line-height:1.5;color:#1c1c1c">' . $valor . '</td></tr>';
	}
	$bts = '';
	foreach ($botones as $i => $b) {
		$fondo = $i === 0 ? '#736d47' : '#ffffff';
		$color = $i === 0 ? '#ffffff' : '#1c1c1c';
		$bts .= '<a href="' . esc_url($b['url']) . '" style="display:inline-block;margin:0 8px 8px 0;padding:12px 20px;border-radius:999px;border:1px solid #736d47;background:' . $fondo . ';color:' . $color . ';font-size:14px;font-weight:600;text-decoration:none">' . $e($b['texto']) . '</a>';
	}
	$banda = '';
	foreach (['#f4a700', '#83b300', '#df5f0c', '#48725E', '#e9eef1'] as $i => $c) {
		$banda .= '<span style="display:inline-block;width:8px;height:8px;margin-right:8px;background:' . $c . ';transform:rotate(45deg);border-radius:2px"></span>';
	}
	$web = sunqu_url_web();
	// Para la persona: el logo de la web (con texto alternativo si su correo no muestra imágenes)
	$marca = $visitante
		? '<img src="' . esc_url($web . '/assets/logo-light.png') . '" width="168" height="42" alt="Sunqu Journeys" style="display:block;border:0;outline:none;height:auto;max-width:168px;color:#e9eef1;font-size:20px;font-weight:700">'
		: '<div style="font-size:20px;font-weight:700;letter-spacing:-.01em">Sunqu Journeys</div>';
	$pie = $visitante
		? '<strong style="color:#1c1c1c">Sunqu Journeys</strong> · ' . $e($msg('Viaja al corazón del Perú'))
			. '<br><a href="' . esc_url($o['enlace'] ?? $web) . '" style="color:#736d47">' . $e(preg_replace('#^https?://#', '', $web)) . '</a>'
			. '<br><br>' . $e($msg('Recibes este correo porque enviaste un formulario en sunqujourneys.com. Si tienes dudas, responde a este correo.'))
		: 'Aviso automático del formulario de sunqujourneys.com · ' . $e(wp_date('j \d\e F \d\e Y, H:i'));
	return '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($titulo) . '</title></head>'
		. '<body style="margin:0;padding:24px 12px;background:#e9eef1;font-family:Helvetica,Arial,sans-serif;color:#1c1c1c">'
		. ($visitante && $intro !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $e($intro) . '</div>' : '') // vista previa en la bandeja
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;overflow:hidden">'
		. '<tr><td style="background:#2b271b;padding:22px 28px;color:#e9eef1">' . $marca
		. '<div style="margin-top:12px">' . $banda . '</div></td></tr>'
		. '<tr><td style="padding:28px 28px 8px"><h1 style="margin:0;font-size:22px;line-height:1.25;color:#1c1c1c">' . $e($titulo) . '</h1>'
		. ($intro !== '' ? '<p style="margin:10px 0 0;font-size:15px;line-height:1.55;color:#424244">' . $e($intro) . '</p>' : '') . '</td></tr>'
		. '<tr><td style="padding:12px 28px 8px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $filas . '</table></td></tr>'
		. ($bts !== '' ? '<tr><td style="padding:12px 28px 20px">' . $bts . '</td></tr>' : '')
		. '<tr><td style="padding:16px 28px 22px;border-top:1px solid #e3e6e8;font-size:12px;line-height:1.5;color:#7a7a7d">' . $pie . '</td></tr>'
		. '</table></body></html>';
}

/** Versión de texto del mismo correo. */
function sunqu_correo_texto(string $titulo, string $intro, array $campos, array $botones = [], array $o = []): string {
	$msg = $o['tr'] ?? fn($s) => $s; // $msg('…'): tools/extraer-textos.py lo junta para traducir
	$l = [$titulo, str_repeat('─', 40)];
	if ($intro !== '') {
		$l[] = $intro;
		$l[] = '';
	}
	foreach ($campos as $k => $v) {
		if ($v !== '' && $v !== null) {
			$l[] = $k . ': ' . str_replace(' | ', "\n  · ", (string) $v);
		}
	}
	foreach ($botones as $b) {
		$l[] = '';
		$l[] = $b['texto'] . ': ' . $b['url'];
	}
	if (!empty($o['visitante'])) {
		array_push($l, '', '—', 'Sunqu Journeys · ' . $msg('Viaja al corazón del Perú') . ' · ' . sunqu_url_web(), $msg('Recibes este correo porque enviaste un formulario en sunqujourneys.com. Si tienes dudas, responde a este correo.'));
	}
	return implode("\n", $l);
}

/** «journey:alma» → «Ficha del journey El Alma de los Andes» (la página desde la que se envió). */
function sunqu_pagina_legible(string $origen): string {
	[$pag, $slug] = array_pad(explode(':', $origen, 2), 2, '');
	$nombres = ['home' => 'Inicio', 'descubre' => 'Descubre', 'recuerda' => 'Recuerda', 'reclamaciones' => 'Libro de reclamaciones'];
	if ($pag === 'journey') {
		foreach ((sunqu_exportar('journeys')['items'] ?? []) as $j) {
			if (($j['slug'] ?? '') === $slug) {
				return 'Ficha del journey «' . $j['title'] . '»';
			}
		}
		return 'Ficha del journey ' . $slug;
	}
	return $nombres[$pag] ?? $origen;
}

// ── Aviso de un envío ─────────────────────────────────────────────────────────────────────────

/**
 * Avisa de un envío por correo y al celular, y guarda el resultado en la ficha del envío ($id).
 * $o: asunto, intro, responder (correo del visitante), nombre (para el aviso al celular), botones extra.
 */
function sunqu_avisar(string $tipo, int $id, array $campos, array $o = []): array {
	$nombre_tipo = sunqu_tipos_formulario()[$tipo] ?? 'Formulario';
	$asunto = $o['asunto'] ?? $nombre_tipo;
	$intro = $o['intro'] ?? '';
	$panel = $id ? admin_url('post.php?post=' . $id . '&action=edit') : admin_url('admin.php?page=sunqu');
	$botones = [['texto' => 'Ver en el panel', 'url' => $panel]];
	if (!empty($o['responder']) && is_email($o['responder'])) {
		array_unshift($botones, ['texto' => 'Responder por correo', 'url' => 'mailto:' . $o['responder'] . '?subject=' . rawurlencode('Sunqu Journeys · ' . $nombre_tipo)]);
	}
	$headers = !empty($o['responder']) && is_email($o['responder']) ? ['Reply-To: ' . $o['responder']] : [];
	$para = sunqu_correos_avisos();
	$r = sunqu_enviar_correo($para, $asunto, sunqu_correo_html($asunto, $intro, $campos, $botones), sunqu_correo_texto($asunto, $intro, $campos, $botones), $headers);
	$estado = ['fecha' => wp_date('c'), 'para' => implode(', ', $para), 'ok' => $r['ok'], 'error' => $r['error']];

	// Al celular: sin datos personales, solo qué llegó, de quién y dónde verlo
	$nombre = trim((string) ($o['nombre'] ?? ''));
	$push = $tipo === 'prueba' ? ['canales' => []] : sunqu_push($tipo, $nombre_tipo, $nombre !== '' ? 'De ' . $nombre : 'Nuevo envío desde la web', $panel, false);

	if ($id) {
		update_post_meta($id, '_sunqu_aviso', wp_slash(wp_json_encode($estado + ['celular' => $push['canales']], JSON_UNESCAPED_UNICODE)));
		update_post_meta($id, '_sunqu_aviso_datos', wp_slash(wp_json_encode(['tipo' => $tipo, 'campos' => $campos, 'o' => $o], JSON_UNESCAPED_UNICODE)));
	}
	return $estado;
}

// ── Celular: Telegram y webhook ───────────────────────────────────────────────────────────────

/** Token del bot (formato 123456789:ABC…); vacío si no hay o no tiene ese formato. */
function sunqu_telegram_token(): string {
	$t = defined('SUNQU_TELEGRAM_TOKEN') && SUNQU_TELEGRAM_TOKEN ? (string) SUNQU_TELEGRAM_TOKEN : trim((string) get_option('sunqu_telegram_token', ''));
	return preg_match('/^\d+:[\w-]+$/', $t) ? $t : '';
}

/**
 * Aviso al celular por los canales configurados. $esperar = true espera la respuesta (botón de prueba);
 * con false no demora la respuesta del formulario. Devuelve ['canales' => [...], 'errores' => [...]].
 */
function sunqu_push(string $tipo, string $titulo, string $texto, string $url, bool $esperar): array {
	$canales = [];
	$errores = [];
	$token = sunqu_telegram_token();
	$chat = trim((string) get_option('sunqu_telegram_chat', ''));
	if ($token !== '' && $chat !== '') {
		$msg = '<b>' . esc_html($titulo) . '</b>' . "\n" . esc_html($texto) . "\n" . '<a href="' . esc_url($url) . '">Ver en el panel</a>';
		$r = wp_remote_post('https://api.telegram.org/bot' . $token . '/sendMessage', [
			'timeout' => $esperar ? 10 : 3,
			'blocking' => $esperar,
			'body' => ['chat_id' => $chat, 'text' => $msg, 'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'],
		]);
		$canales[] = 'telegram';
		if ($esperar) {
			$j = is_wp_error($r) ? null : json_decode((string) wp_remote_retrieve_body($r), true);
			if (is_wp_error($r) || empty($j['ok'])) {
				$errores[] = 'Telegram: ' . (is_wp_error($r) ? $r->get_error_message() : ($j['description'] ?? 'respuesta inesperada'));
			}
		}
	}
	$hook = trim((string) get_option('sunqu_webhook_url', ''));
	// wp_safe_remote_post: no sigue a direcciones internas del servidor (localhost, redes privadas)
	if ($hook !== '' && wp_http_validate_url($hook)) {
		$r = wp_safe_remote_post($hook, [
			'timeout' => $esperar ? 10 : 3,
			'blocking' => $esperar,
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode(['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'url' => $url, 'fecha' => wp_date('c')], JSON_UNESCAPED_UNICODE),
		]);
		$canales[] = 'webhook';
		if ($esperar && (is_wp_error($r) || wp_remote_retrieve_response_code($r) >= 400)) {
			$errores[] = 'Webhook: ' . (is_wp_error($r) ? $r->get_error_message() : 'respondió ' . wp_remote_retrieve_response_code($r));
		}
	}
	return ['canales' => $canales, 'errores' => $errores];
}

/** Chats que le escribieron al bot (para encontrar el chat ID desde el panel). */
function sunqu_telegram_chats(): array {
	$token = sunqu_telegram_token();
	if ($token === '') {
		return ['error' => 'Primero guarda el token del bot (formato 123456789:ABC…).'];
	}
	$r = wp_remote_get('https://api.telegram.org/bot' . $token . '/getUpdates', ['timeout' => 10]);
	$j = is_wp_error($r) ? null : json_decode((string) wp_remote_retrieve_body($r), true);
	if (is_wp_error($r) || empty($j['ok'])) {
		return ['error' => 'Telegram no respondió: ' . (is_wp_error($r) ? $r->get_error_message() : ($j['description'] ?? 'revisa el token'))];
	}
	$chats = [];
	foreach ($j['result'] ?? [] as $u) {
		$c = $u['message']['chat'] ?? $u['my_chat_member']['chat'] ?? $u['channel_post']['chat'] ?? null;
		if ($c && isset($c['id'])) {
			$chats[(string) $c['id']] = trim(($c['title'] ?? '') ?: (($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
		}
	}
	return ['chats' => $chats];
}

// ── Ficha de cada envío: estado del aviso y «Reenviar» ────────────────────────────────────────

add_action('add_meta_boxes', function () {
	$tipos = ['sunqu_solicitud', 'sunqu_reclamacion'];
	$resenas = sunqu_seccion('resenas');
	if ($resenas) {
		$tipos[] = $resenas['post_type'];
	}
	add_meta_box('sunqu_aviso', 'Aviso a Sunqu', 'sunqu_caja_aviso', $tipos, 'side', 'high');
});

function sunqu_caja_aviso(WP_Post $post): void {
	$a = json_decode((string) get_post_meta($post->ID, '_sunqu_aviso', true), true);
	if (!is_array($a)) {
		echo '<p>Este elemento no llegó por un formulario de la web.</p>';
		return;
	}
	$fecha = esc_html(wp_date('j M Y, H:i', strtotime($a['fecha'])));
	echo $a['ok']
		? '<p>✔ Correo enviado a <strong>' . esc_html($a['para']) . '</strong><br><span class="description">' . $fecha . '</span></p>'
		: '<p style="color:#b32d2e">✖ El correo no salió (' . $fecha . '): ' . esc_html($a['error']) . '</p>';
	if (!empty($a['celular'])) {
		echo '<p class="description">Aviso al celular: ' . esc_html(implode(', ', $a['celular'])) . '</p>';
	}
	$copia = json_decode((string) get_post_meta($post->ID, '_sunqu_copia', true), true);
	if (is_array($copia)) {
		echo $copia['ok']
			? '<p class="description">✔ Copia de la solicitud enviada a ' . esc_html((string) $copia['para']) . '</p>'
			: '<p class="description" style="color:#b32d2e">✖ La copia a la persona no salió: ' . esc_html((string) $copia['error']) . '</p>';
	}
	if (get_post_meta($post->ID, '_sunqu_aviso_datos', true)) {
		$url = wp_nonce_url(admin_url('admin-post.php?action=sunqu_reenviar&post=' . $post->ID), 'sunqu_reenviar_' . $post->ID);
		echo '<p><a class="button" href="' . esc_url($url) . '">Reenviar el aviso</a></p>';
	}
}

add_action('admin_post_sunqu_reenviar', function () {
	$id = (int) ($_GET['post'] ?? 0);
	if (!$id || !current_user_can('edit_post', $id)) {
		wp_die('Sin permiso.');
	}
	check_admin_referer('sunqu_reenviar_' . $id);
	$d = json_decode((string) get_post_meta($id, '_sunqu_aviso_datos', true), true);
	if (is_array($d)) {
		sunqu_avisar((string) $d['tipo'], $id, (array) $d['campos'], (array) ($d['o'] ?? []));
	}
	wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
	exit;
});

// Columna «Aviso» en las listas de solicitudes y reclamaciones
foreach (['sunqu_solicitud', 'sunqu_reclamacion'] as $pt) {
	add_filter("manage_{$pt}_posts_columns", fn($c) => array_slice($c, 0, 2, true) + ['sunqu_aviso' => 'Aviso por correo'] + $c);
	add_action("manage_{$pt}_posts_custom_column", function ($col, $id) {
		if ($col !== 'sunqu_aviso') {
			return;
		}
		$a = json_decode((string) get_post_meta($id, '_sunqu_aviso', true), true);
		echo is_array($a) ? ($a['ok'] ? '✔ Enviado' : '<span style="color:#b32d2e">✖ Falló</span>') : '—';
	}, 10, 2);
}
