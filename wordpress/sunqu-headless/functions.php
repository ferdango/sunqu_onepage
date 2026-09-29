<?php
defined('ABSPATH') || exit;

// El front de WordPress no se usa: quien abre cms.sunqujourneys.com va al panel (sin sesión, WordPress pide el
// acceso y después entra al escritorio). No se redirigen la API, el cron, la vista previa ni robots.txt;
// las imágenes de la biblioteca se sirven directo y no pasan por aquí.
add_action('template_redirect', function () {
	if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_robots() || is_favicon() || (defined('REST_REQUEST') && REST_REQUEST)) {
		return;
	}
	wp_safe_redirect(admin_url(), 302);
	exit;
});

add_theme_support('post-thumbnails');
add_theme_support('title-tag');

// El CMS no se indexa: la web pública (sunqujourneys.com) tiene su propio sitemap y SEO.
// Las imágenes sí se pueden rastrear, porque la web las muestra desde aquí.
add_filter('wp_sitemaps_enabled', '__return_false');
add_filter('wp_robots', fn($robots) => ['noindex' => true, 'nofollow' => true] + $robots);
add_filter('robots_txt', fn() => "User-agent: *\nDisallow: /\nAllow: /wp-content/uploads/\n", 99);
