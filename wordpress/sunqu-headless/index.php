<?php defined('ABSPATH') || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body style="margin:0;font-family:system-ui,sans-serif;background:#1c1c1c;color:#e9eef1;display:grid;place-items:center;min-height:100vh;text-align:center">
	<main style="max-width:36rem;padding:2rem">
		<h1 style="font-weight:600">Sunqu · CMS</h1>
		<p>Este WordPress administra el contenido de la web. La web pública lee <code><?php echo esc_html(rest_url('sunqu/v1/contenido')); ?></code>.</p>
		<p><a style="color:#83b300" href="<?php echo esc_url(admin_url()); ?>">Ir al panel</a></p>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
