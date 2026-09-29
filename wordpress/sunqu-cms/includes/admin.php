<?php
/**
 * Panel: menú «Sunqu» con la página «Conexión y herramientas».
 *   · URL de la web, orígenes permitidos (CORS) y token de purga.
 *   · Avisos de los formularios: correos que los reciben, estado del SMTP, Telegram, webhook y pruebas.
 *   · Importar el contenido de la web que trae el plugin (carpeta datos/, la arma tools/empaquetar-wordpress.sh).
 *   · Limpiar la caché de la API, vaciar la caché de la web y descargar todo el contenido en JSON.
 *   · Aviso si falta el plugin de campos (Secure Custom Fields o ACF Pro).
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
	add_menu_page('Sunqu', 'Sunqu', 'edit_posts', 'sunqu', 'sunqu_pagina_conexion', 'dashicons-heart', 3);
	add_submenu_page('sunqu', 'Conexión y herramientas', 'Conexión y herramientas', 'manage_options', 'sunqu', 'sunqu_pagina_conexion');
}, 9); // antes que los submenús de los envíos, para que «Sunqu» abra esta página

add_action('admin_init', function () {
	foreach (['sunqu_web' => 'esc_url_raw', 'sunqu_origenes' => 'sanitize_textarea_field', 'sunqu_purga_token' => 'sanitize_text_field'] as $op => $san) {
		register_setting('sunqu_conexion', $op, ['sanitize_callback' => $san]);
	}
	$correos = fn($v) => implode(', ', sunqu_lista_correos((string) $v));
	foreach (['sunqu_notificar' => $correos, 'sunqu_telegram_token' => 'sanitize_text_field', 'sunqu_telegram_chat' => 'sanitize_text_field', 'sunqu_webhook_url' => 'esc_url_raw'] as $op => $san) {
		register_setting('sunqu_avisos', $op, ['sanitize_callback' => $san]);
	}
	if (isset($_POST['sunqu_accion']) && current_user_can('manage_options') && check_admin_referer('sunqu_herramientas')) {
		if ($_POST['sunqu_accion'] === 'cache') {
			sunqu_limpiar_cache();
			add_settings_error('sunqu', 'cache', 'Caché de la API limpiada.', 'updated');
		}
		if ($_POST['sunqu_accion'] === 'correo_prueba') {
			$campos = ['Nombre' => 'Viajera de prueba', 'Email' => get_option('admin_email'), '¿Cuándo?' => 'Julio – septiembre', 'Mensaje' => 'Si lees esto, los formularios de la web llegan a este correo.'];
			$r = sunqu_avisar('prueba', 0, $campos, ['asunto' => 'Prueba · avisos de los formularios de Sunqu', 'intro' => 'Correo de prueba enviado desde Sunqu › Conexión y herramientas.']);
			add_settings_error('sunqu', 'correo', $r['ok'] ? 'Correo de prueba enviado a ' . $r['para'] . '. Revisa la bandeja de entrada (y la de spam la primera vez).' : 'El correo no salió: ' . $r['error'], $r['ok'] ? 'updated' : 'error');
		}
		if ($_POST['sunqu_accion'] === 'push_prueba') {
			$r = sunqu_push('prueba', 'Aviso de prueba', 'Si ves esto, los avisos de la web llegan a tu celular.', admin_url('admin.php?page=sunqu'), true);
			$msg = !$r['canales'] ? 'No hay canales configurados: completa Telegram o el webhook y guarda.' : ($r['errores'] ? implode(' · ', $r['errores']) : 'Aviso enviado por: ' . implode(', ', $r['canales']) . '.');
			add_settings_error('sunqu', 'push', $msg, $r['canales'] && !$r['errores'] ? 'updated' : 'error');
		}
		if ($_POST['sunqu_accion'] === 'telegram_chat') {
			$r = sunqu_telegram_chats();
			if (!empty($r['error'])) {
				add_settings_error('sunqu', 'tg', $r['error'], 'error');
			} elseif (!$r['chats']) {
				add_settings_error('sunqu', 'tg', 'El bot todavía no recibió mensajes: desde Telegram, abre tu bot y escríbele «/start» (o agrégalo al grupo del equipo y escribe algo). Luego vuelve a pulsar «Detectar chat».', 'error');
			} else {
				if (count($r['chats']) === 1) {
					update_option('sunqu_telegram_chat', (string) array_key_first($r['chats']));
				}
				$lista = implode(' · ', array_map(fn($id, $n) => ($n ?: 'chat') . ' → ' . $id, array_keys($r['chats']), $r['chats']));
				add_settings_error('sunqu', 'tg', count($r['chats']) === 1 ? 'Chat detectado y guardado: ' . $lista : 'Chats encontrados (copia el que quieras en «Chat ID» y guarda): ' . $lista, 'updated');
			}
		}
		if ($_POST['sunqu_accion'] === 'purgar') {
			$r = sunqu_purgar_web();
			add_settings_error('sunqu', 'purga', $r['mensaje'], $r['ok'] ? 'updated' : 'error');
		}
		if ($_POST['sunqu_accion'] === 'descargar') {
			$todo = [];
			foreach (sunqu_secciones() as $s) {
				$todo[$s] = sunqu_exportar($s, false);
			}
			nocache_headers();
			header('Content-Type: application/json; charset=utf-8');
			header('Content-Disposition: attachment; filename="sunqu-contenido-' . wp_date('Ymd-His') . '.json"');
			echo wp_json_encode($todo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			exit;
		}
	}
});

/**
 * Contenido que trae el plugin para importar (datos/content/*.json y datos/assets/…): lo agrega
 * tools/empaquetar-wordpress.sh al armar el zip. Sin esa carpeta no hay nada que importar desde el panel.
 */
function sunqu_paquete_contenido(): ?array {
	$dir = SUNQU_CMS_DIR . 'datos';
	if (!is_file("$dir/content/sitio.json")) {
		return null;
	}
	$info = json_decode((string) @file_get_contents("$dir/paquete.json"), true);
	$fecha = is_array($info) && !empty($info['creado']) ? wp_date('j \d\e F \d\e Y, H:i', strtotime($info['creado'])) : '';
	return ['dir' => $dir, 'fecha' => $fecha];
}

// Importación desde el panel: una sección por petición (evita el límite de tiempo de PHP con las fotos)
add_action('wp_ajax_sunqu_importar', function () {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['mensaje' => 'No tienes permiso para importar.'], 403);
	}
	check_ajax_referer('sunqu_importar');
	$paquete = sunqu_paquete_contenido();
	$nombre = sanitize_key(wp_unslash($_POST['seccion'] ?? ''));
	if (!$paquete || !in_array($nombre, Sunqu_Importador::ORDEN, true)) {
		wp_send_json_error(['mensaje' => 'El plugin no trae ese contenido.'], 400);
	}
	$datos = json_decode((string) @file_get_contents($paquete['dir'] . "/content/$nombre.json"), true);
	if (!is_array($datos)) {
		wp_send_json_error(['mensaje' => "Falta o está dañado datos/content/$nombre.json."], 400);
	}
	if (function_exists('set_time_limit')) {
		@set_time_limit(300);
	}
	wp_raise_memory_limit('admin');
	$GLOBALS['sunqu_sin_cache'] = true;
	$imp = new Sunqu_Importador($paquete['dir'], false, !empty($_POST['podar']));
	$resumen = $imp->seccion($nombre, $datos);
	$GLOBALS['sunqu_sin_cache'] = false;
	if (!empty($_POST['ultima'])) {
		sunqu_limpiar_cache(); // una sola vez al final: la web se entera (purga)
		update_option('sunqu_importado', wp_date('c'), false);
	}
	wp_send_json_success(['resumen' => $resumen, 'avisos' => $imp->avisos, 'notas' => $imp->notas]);
});

function sunqu_pagina_conexion(): void {
	$api = rest_url('sunqu/v1/contenido');
	$campos = function_exists('acf_add_local_field_group');
	$paquete = sunqu_paquete_contenido();
	$importado = (string) get_option('sunqu_importado', '');
	?>
	<div class="wrap">
		<h1>Sunqu · Conexión y herramientas</h1>
		<?php settings_errors('sunqu'); ?>
		<?php if (!$campos) : ?>
			<div class="notice notice-warning"><p><strong>Falta el plugin de campos.</strong> Instala y activa <em>Secure Custom Fields</em> (gratis) o ACF Pro para editar el contenido desde el panel. La API funciona igual.</p></div>
		<?php endif; ?>

		<h2>La web lee de aquí</h2>
		<p>En el <code>.env</code> de la web: <code>WORDPRESS_API_URL=<?php echo esc_html(untrailingslashit(rest_url())); ?></code> (en una vista previa estática, <code>js/sunqu-config.js › api</code>).</p>
		<p>Contenido: <a href="<?php echo esc_url($api); ?>" target="_blank"><?php echo esc_html($api); ?></a> · Por sección: <code>…/contenido/journeys</code> · Esquema: <code>…/sunqu/v1/esquema</code></p>

		<form method="post" action="options.php">
			<?php settings_fields('sunqu_conexion'); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="sunqu_web">URL de la web</label></th>
					<td><input type="url" class="regular-text" id="sunqu_web" name="sunqu_web" value="<?php echo esc_attr(get_option('sunqu_web')); ?>" placeholder="https://sunqujourneys.com">
						<p class="description">La web pública. Solo ella puede leer la API desde el navegador (CORS) y es a la que se avisa al publicar para que vacíe su caché.</p></td></tr>
				<tr><th><label for="sunqu_origenes">Orígenes permitidos</label></th>
					<td><textarea class="large-text" rows="3" id="sunqu_origenes" name="sunqu_origenes" placeholder="https://sunqujourneys.com&#10;https://ferdango.github.io"><?php echo esc_textarea(get_option('sunqu_origenes')); ?></textarea>
						<p class="description">Uno por línea. Vacío = solo el de «URL de la web».</p></td></tr>
				<tr><th><label for="sunqu_purga_token">Token de purga</label></th>
					<td><input type="password" class="regular-text" id="sunqu_purga_token" name="sunqu_purga_token" value="<?php echo esc_attr(get_option('sunqu_purga_token')); ?>" autocomplete="off" spellcheck="false">
						<p class="description">El mismo valor que <code>CACHE_PURGE_TOKEN</code> en el <code>.env</code> de la web. Con él, cada vez que guardas contenido la web lo muestra al instante; sin él, lo muestra al vencer su caché (5 minutos por defecto).</p></td></tr>
			</table>
			<?php submit_button('Guardar'); ?>
		</form>

		<?php sunqu_seccion_avisos(); ?>

		<h2>Importar el contenido de la web</h2>
		<?php if (!$paquete) : ?>
			<p class="description">Este plugin no trae contenido para importar. Súbelo armado con <code>tools/empaquetar-wordpress.sh</code> (incluye <code>content/*.json</code> y sus imágenes) o importa por consola con <code>wp sunqu importar</code>.</p>
		<?php else : ?>
			<p>El plugin trae el contenido de la web<?php echo $paquete['fecha'] ? ' del <strong>' . esc_html($paquete['fecha']) . '</strong>' : ''; ?>: crea o actualiza journeys, experiencias, anfitriones, reseñas, tienda, Diario, portada y ajustes, y sube las fotos a la biblioteca. Tarda unos minutos porque descarga las fotos; no cierres esta página hasta que diga «Listo».</p>
			<?php if ($importado) : ?>
				<div class="notice notice-warning inline"><p>Ya se importó el <?php echo esc_html(wp_date('j \d\e F \d\e Y, H:i', strtotime($importado))); ?>. Volver a importar <strong>reemplaza</strong> lo que tenga el mismo slug con el contenido del paquete: se pierden los cambios hechos después en el panel.</p></div>
			<?php endif; ?>
			<p><label><input type="checkbox" id="sunqu-podar" <?php checked($importado === ''); ?>> Pasar a borrador lo publicado que no está en el contenido de la web (por ejemplo, la entrada «¡Hola, mundo!» de WordPress). No se borra nada.</label></p>
			<p><button type="button" class="button button-primary" id="sunqu-importar">Importar el contenido</button></p>
			<ol id="sunqu-importar-log" aria-live="polite"></ol>
			<script>
			(function () {
				var b = document.getElementById('sunqu-importar'), log = document.getElementById('sunqu-importar-log'), podar = document.getElementById('sunqu-podar');
				var secciones = <?php echo wp_json_encode(Sunqu_Importador::ORDEN); ?>, ya = <?php echo $importado ? 'true' : 'false'; ?>;
				var nombres = { sitio: 'Sitio', anfitriones: 'Anfitriones', experiencias: 'Experiencias', journeys: 'Journeys', resenas: 'Reseñas', productos: 'Tienda', diario: 'Diario Sunqu', legales: 'Legales', inicio: 'Portada' };
				var linea = function (li, texto) { var d = document.createElement('div'); d.className = 'description'; d.textContent = texto; li.appendChild(d); };
				b.addEventListener('click', async function () {
					if (ya && !window.confirm('Se reemplazará con el contenido del paquete lo que tenga el mismo slug. ¿Continuar?')) return;
					b.disabled = true; log.textContent = '';
					for (var i = 0; i < secciones.length; i++) {
						var s = secciones[i], li = document.createElement('li');
						li.textContent = nombres[s] + '…'; log.appendChild(li);
						try {
							var r = await fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({ action: 'sunqu_importar', _ajax_nonce: <?php echo wp_json_encode(wp_create_nonce('sunqu_importar')); ?>, seccion: s, podar: podar.checked ? '1' : '', ultima: i === secciones.length - 1 ? '1' : '' }) });
							var j = await r.json().catch(function () { return null; });
							if (!j || !j.success) throw new Error((j && j.data && j.data.mensaje) || ('el servidor respondió ' + r.status));
							li.textContent = nombres[s] + ': ' + j.data.resumen + ' ✓';
							(j.data.notas || []).forEach(function (n) { linea(li, n); });
							(j.data.avisos || []).forEach(function (a) { linea(li, '⚠ ' + a); });
						} catch (e) {
							li.textContent = nombres[s] + ': se interrumpió (' + e.message + '). Vuelve a pulsar «Importar el contenido»: lo que ya se importó (y las fotos) se reutiliza.';
							b.disabled = false; return;
						}
					}
					var fin = document.createElement('li'); fin.innerHTML = '<strong>Listo.</strong> El contenido ya está en el panel y en la API.'; log.appendChild(fin);
					b.disabled = false; ya = true;
				});
			})();
			</script>
		<?php endif; ?>

		<h2>Herramientas</h2>
		<form method="post">
			<?php wp_nonce_field('sunqu_herramientas'); ?>
			<button class="button" name="sunqu_accion" value="cache">Limpiar caché de la API</button>
			<button class="button" name="sunqu_accion" value="purgar">Vaciar caché de la web</button>
			<button class="button" name="sunqu_accion" value="descargar">Descargar todo el contenido (JSON)</button>
		</form>
		<p class="description">Por consola: <code>wp sunqu importar --dir=content --front=/ruta/a/la/web</code> · <code>wp sunqu exportar --dir=content</code> · <code>wp sunqu verificar --dir=content</code></p>
	</div>
	<?php
}

add_action('admin_notices', function () {
	if (function_exists('acf_add_local_field_group') || !current_user_can('activate_plugins')) {
		return;
	}
	$screen = get_current_screen();
	if ($screen && (str_starts_with((string) $screen->post_type, 'sunqu_') || str_contains((string) $screen->id, 'sunqu'))) {
		echo '<div class="notice notice-warning"><p><strong>Sunqu CMS:</strong> activa <em>Secure Custom Fields</em> o ACF Pro para ver y editar los campos.</p></div>';
	}
});

/** Sección «Avisos de los formularios» de Sunqu › Conexión y herramientas. */
function sunqu_seccion_avisos(): void {
	$smtp = sunqu_smtp();
	$error = get_option('sunqu_correo_error');
	$token_cfg = defined('SUNQU_TELEGRAM_TOKEN') && SUNQU_TELEGRAM_TOKEN;
	?>
	<h2 id="avisos">Avisos de los formularios</h2>
	<p>Cada «Diseña tu viaje», reseña y hoja de reclamación se guarda en el panel y llega por correo a estos destinatarios. Opcionalmente, también como notificación al celular.</p>
	<?php if ($smtp) : ?>
		<div class="notice notice-success inline"><p>✔ Envío por <strong>SMTP</strong>: <?php echo esc_html($smtp['host'] . ':' . $smtp['port']); ?> · remitente <strong><?php echo esc_html($smtp['from'] ?: '(el de WordPress)'); ?></strong> (configurado en <code>wp-config.php</code>).</p></div>
	<?php else : ?>
		<div class="notice notice-warning inline"><p><strong>Sin SMTP.</strong> Los correos salen con la función de correo del servidor y pueden caer en spam o no llegar a Gmail. Configura un buzón del dominio en <code>wp-config.php</code> (<code>SUNQU_SMTP_HOST</code>, <code>SUNQU_SMTP_USER</code>, <code>SUNQU_SMTP_PASS</code>…; ver <code>docs/FORMULARIOS.md</code>).</p></div>
	<?php endif; ?>
	<?php if (is_array($error) && !empty($error['error'])) : ?>
		<div class="notice notice-error inline"><p>Último error de envío (<?php echo esc_html(wp_date('j M Y, H:i', strtotime($error['fecha']))); ?>): <?php echo esc_html($error['error']); ?></p></div>
	<?php endif; ?>
	<form method="post" action="options.php">
		<?php settings_fields('sunqu_avisos'); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="sunqu_notificar">Correos que reciben los avisos</label></th>
				<td><input type="text" class="large-text" id="sunqu_notificar" name="sunqu_notificar" value="<?php echo esc_attr(get_option('sunqu_notificar')); ?>" placeholder="<?php echo esc_attr(SUNQU_CORREO_AVISOS_DEFECTO); ?>">
					<p class="description">Uno o varios, separados por comas. Vacío = <strong><?php echo esc_html(implode(', ', sunqu_correos_avisos())); ?></strong>.</p></td></tr>
			<tr><th><label for="sunqu_telegram_token">Telegram · token del bot</label></th>
				<td><?php if ($token_cfg) : ?>
						<p>Definido en <code>wp-config.php</code> (<code>SUNQU_TELEGRAM_TOKEN</code>).</p>
					<?php else : ?>
						<input type="password" class="regular-text" id="sunqu_telegram_token" name="sunqu_telegram_token" value="<?php echo esc_attr(get_option('sunqu_telegram_token')); ?>" autocomplete="off" spellcheck="false" placeholder="123456789:ABC…">
					<?php endif; ?>
					<p class="description">Opcional: avisos instantáneos en la app de Telegram. Crea el bot con <strong>@BotFather</strong> (comando <code>/newbot</code>) y pega aquí el token.</p></td></tr>
			<tr><th><label for="sunqu_telegram_chat">Telegram · chat ID</label></th>
				<td><input type="text" class="regular-text" id="sunqu_telegram_chat" name="sunqu_telegram_chat" value="<?php echo esc_attr(get_option('sunqu_telegram_chat')); ?>" placeholder="Se completa con «Detectar chat»">
					<p class="description">Escríbele «/start» a tu bot (o agrégalo al grupo del equipo) y pulsa <strong>Detectar chat</strong>.</p></td></tr>
			<tr><th><label for="sunqu_webhook_url">Webhook</label></th>
				<td><input type="url" class="large-text" id="sunqu_webhook_url" name="sunqu_webhook_url" value="<?php echo esc_attr(get_option('sunqu_webhook_url')); ?>" placeholder="https://hook.make.com/…">
					<p class="description">Opcional: recibe <code>POST</code> JSON <code>{ tipo, titulo, texto, url, fecha }</code> en cada envío. Sirve para reenviar a WhatsApp, Slack o SMS con Make, Zapier o n8n. Sin datos personales.</p></td></tr>
		</table>
		<?php submit_button('Guardar avisos'); ?>
	</form>
	<form method="post">
		<?php wp_nonce_field('sunqu_herramientas'); ?>
		<button class="button button-primary" name="sunqu_accion" value="correo_prueba">Enviar correo de prueba</button>
		<button class="button" name="sunqu_accion" value="telegram_chat">Detectar chat de Telegram</button>
		<button class="button" name="sunqu_accion" value="push_prueba">Enviar aviso de prueba al celular</button>
	</form>
	<?php
}
