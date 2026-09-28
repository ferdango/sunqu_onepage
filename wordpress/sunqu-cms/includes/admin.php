<?php
/**
 * Panel: menú «Sunqu» con la página «Conexión y herramientas».
 *   · URL de la web, orígenes permitidos (CORS) y correo de avisos de formularios.
 *   · Limpiar la caché de la API y descargar todo el contenido en JSON.
 *   · Aviso si falta el plugin de campos (Secure Custom Fields o ACF Pro).
 */

defined('ABSPATH') || exit;

add_action('admin_menu', function () {
	add_menu_page('Sunqu', 'Sunqu', 'edit_posts', 'sunqu', 'sunqu_pagina_conexion', 'dashicons-heart', 3);
	add_submenu_page('sunqu', 'Conexión y herramientas', 'Conexión y herramientas', 'manage_options', 'sunqu', 'sunqu_pagina_conexion');
}, 9); // antes que los submenús de los envíos, para que «Sunqu» abra esta página

add_action('admin_init', function () {
	foreach (['sunqu_web' => 'esc_url_raw', 'sunqu_origenes' => 'sanitize_textarea_field', 'sunqu_notificar' => 'sanitize_email'] as $op => $san) {
		register_setting('sunqu_conexion', $op, ['sanitize_callback' => $san]);
	}
	if (isset($_POST['sunqu_accion']) && current_user_can('manage_options') && check_admin_referer('sunqu_herramientas')) {
		if ($_POST['sunqu_accion'] === 'cache') {
			sunqu_limpiar_cache();
			add_settings_error('sunqu', 'cache', 'Caché limpiada.', 'updated');
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

function sunqu_pagina_conexion(): void {
	$api = rest_url('sunqu/v1/contenido');
	$campos = function_exists('acf_add_local_field_group');
	?>
	<div class="wrap">
		<h1>Sunqu · Conexión y herramientas</h1>
		<?php settings_errors('sunqu'); ?>
		<?php if (!$campos) : ?>
			<div class="notice notice-warning"><p><strong>Falta el plugin de campos.</strong> Instala y activa <em>Secure Custom Fields</em> (gratis) o ACF Pro para editar el contenido desde el panel. La API funciona igual.</p></div>
		<?php endif; ?>

		<h2>La web lee de aquí</h2>
		<p>En la web, <code>js/sunqu-config.js</code> debe tener <code>api: '<?php echo esc_html(untrailingslashit(rest_url())); ?>'</code>.</p>
		<p>Contenido: <a href="<?php echo esc_url($api); ?>" target="_blank"><?php echo esc_html($api); ?></a> · Por sección: <code>…/contenido/journeys</code> · Esquema: <code>…/sunqu/v1/esquema</code></p>

		<form method="post" action="options.php">
			<?php settings_fields('sunqu_conexion'); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="sunqu_web">URL de la web</label></th>
					<td><input type="url" class="regular-text" id="sunqu_web" name="sunqu_web" value="<?php echo esc_attr(get_option('sunqu_web')); ?>" placeholder="https://sunqujourneys.com">
						<p class="description">El tema «Sunqu Headless» redirige aquí a quien visite WordPress.</p></td></tr>
				<tr><th><label for="sunqu_origenes">Orígenes permitidos</label></th>
					<td><textarea class="large-text" rows="3" id="sunqu_origenes" name="sunqu_origenes" placeholder="https://sunqujourneys.com&#10;https://ferdango.github.io"><?php echo esc_textarea(get_option('sunqu_origenes')); ?></textarea>
						<p class="description">Uno por línea. Vacío = cualquier origen puede leer la API.</p></td></tr>
				<tr><th><label for="sunqu_notificar">Correo de avisos</label></th>
					<td><input type="email" class="regular-text" id="sunqu_notificar" name="sunqu_notificar" value="<?php echo esc_attr(get_option('sunqu_notificar')); ?>">
						<p class="description">Recibe las solicitudes de viaje, reseñas y hojas de reclamación. Vacío = correo de contacto de Sunqu › Sitio.</p></td></tr>
			</table>
			<?php submit_button('Guardar'); ?>
		</form>

		<h2>Herramientas</h2>
		<form method="post">
			<?php wp_nonce_field('sunqu_herramientas'); ?>
			<button class="button" name="sunqu_accion" value="cache">Limpiar caché de la API</button>
			<button class="button" name="sunqu_accion" value="descargar">Descargar todo el contenido (JSON)</button>
		</form>
		<p class="description">Importar y exportar por consola: <code>wp sunqu importar --dir=content --front=/ruta/a/la/web</code> · <code>wp sunqu exportar --dir=content</code> · <code>wp sunqu verificar --dir=content</code></p>
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
