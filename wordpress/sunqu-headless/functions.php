<?php
defined('ABSPATH') || exit;

// El front de WordPress no se usa: redirige a la web (salvo panel, API, cron y vista previa).
add_action('template_redirect', function () {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}
	$web = get_option('sunqu_web');
	if ($web) {
		wp_redirect($web, 302);
		exit;
	}
});

add_theme_support('post-thumbnails');
add_theme_support('title-tag');
