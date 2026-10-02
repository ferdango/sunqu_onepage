<?php
/**
 * Imagen de marca para compartir (Open Graph y X), una por idioma: assets/og/sunqu-og-{idioma}.jpg, 1200×630.
 * La dibuja tools/imagen-og.php con estos textos, y la web los usa como texto alternativo (dice lo mismo que la imagen).
 * Se comparte en la portada y en las páginas sin foto propia; un journey o un artículo comparten su foto.
 */

declare(strict_types=1);

// El español sale de la web (inicio y título SEO). Los demás son propuestas: revisarlas con una persona nativa antes
// de publicar ese idioma y, si cambian, volver a correr tools/imagen-og.php.
const SUNQU_OG_TEXTOS = [
	'es' => ['antetitulo' => 'VIAJES A MEDIDA · PERÚ', 'titulo' => 'Viaja al corazón del Perú.'],
	'en' => ['antetitulo' => 'TAILOR-MADE JOURNEYS · PERU', 'titulo' => 'Travel to the heart of Peru.'],
	'pt' => ['antetitulo' => 'VIAGENS SOB MEDIDA · PERU', 'titulo' => 'Viaje ao coração do Peru.'],
	'fr' => ['antetitulo' => 'VOYAGES SUR MESURE · PÉROU', 'titulo' => 'Voyagez au cœur du Pérou.'],
	'de' => ['antetitulo' => 'MASSGESCHNEIDERTE REISEN · PERU', 'titulo' => 'Reisen Sie ins Herz Perus.'],
	'it' => ['antetitulo' => 'VIAGGI SU MISURA · PERÙ', 'titulo' => 'Viaggia nel cuore del Perù.'],
	'ru' => ['antetitulo' => 'ИНДИВИДУАЛЬНЫЕ ПУТЕШЕСТВИЯ · ПЕРУ', 'titulo' => "Путешествие в\u{00A0}сердце Перу."], // «в» no queda sola al final de la línea
];

/**
 * Imagen de marca del idioma de la página: [URL absoluta con ?v=, texto alternativo]. Si falta la de ese idioma, la del
 * idioma por defecto o la del español; null si no hay ninguna (la página comparte entonces la foto del inicio).
 */
function sunqu_og_marca(): ?array {
	foreach ([sunqu_lang(), SUNQU_IDIOMA_DEFECTO, SUNQU_IDIOMA_BASE] as $lang) {
		$rel = 'assets/og/sunqu-og-' . $lang . '.jpg';
		if (isset(SUNQU_OG_TEXTOS[$lang]) && is_file(SUNQU_ROOT . '/' . $rel)) {
			$alt = 'Sunqu Journeys · ' . rtrim(str_replace("\u{00A0}", ' ', SUNQU_OG_TEXTOS[$lang]['titulo']), '.');
			return [sunqu_config()['SITE_URL'] . '/' . $rel . '?v=' . sunqu_version($rel), $alt];
		}
	}
	return null;
}
