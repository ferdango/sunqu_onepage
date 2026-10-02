<?php
/**
 * Imagen para compartir (Open Graph) de Sunqu Journeys, una por idioma: assets/og/sunqu-og-{idioma}.jpg (1200×630).
 *
 * Sigue el sistema gráfico del manual de marca (póster de assets/merch/poster.jpg): fondo oliva, las cuatro formas
 * redondeadas (verde, naranja, amarillo y gris claro) encajadas alrededor de un hueco, el logo arriba, el titular
 * abajo en Aeonik y la dirección de la web. Sin fotos: se reconoce igual en una vista previa pequeña de WhatsApp.
 *
 * Uso (necesita PHP con Imagick: sirve la imagen de Docker de WordPress, que ya lo trae):
 *   docker run --rm -v "$PWD":/repo -w /repo --entrypoint php wordpress:php8.3-apache tools/imagen-og.php
 * El ruso necesita una fuente con cirílico (Aeonik no la tiene): CIRILICO_BOLD y CIRILICO_MEDIUM con la ruta de
 * dos .ttf montadas en el contenedor (-v carpeta:/fuentes:ro). Las de ahora son Avenir Next Demi Bold y Medium del
 * Mac (/System/Library/Fonts/Avenir Next.ttc, extraídas a .ttf con fontTools). Sin ellas, se salta el ruso.
 * Los textos están en server/lib/og.php. Después de cambiar uno, vuelve a correrlo: la web agrega ?v= a la URL y las
 * redes ven la imagen nueva.
 */

declare(strict_types=1);

const ANCHO = 1200;
const ALTO = 630;
const RAIZ = __DIR__ . '/..';
const OLIVA = '#736d47';
const CLARO = '#f1efe6';

// Textos de cada idioma: los mismos que usa la web como texto alternativo de la imagen
require RAIZ . '/server/lib/og.php';

function fuentes(string $lang): ?array {
	if ($lang !== 'ru') {
		return ['bold' => RAIZ . '/fonts/Aeonik-Bold.ttf', 'medium' => RAIZ . '/fonts/Aeonik-Medium.ttf'];
	}
	$b = (string) getenv('CIRILICO_BOLD');
	$m = (string) getenv('CIRILICO_MEDIUM');
	return is_file($b) && is_file($m) ? ['bold' => $b, 'medium' => $m] : null;
}

/** Texto en varias líneas que no pasan de $max píxeles. Solo corta en los espacios normales (no en los de no separación). */
function partir(Imagick $lienzo, ImagickDraw $d, string $texto, int $max): array {
	$lineas = [];
	$actual = '';
	foreach (preg_split('/ +/', trim($texto)) as $palabra) {
		$prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
		if ($actual !== '' && $lienzo->queryFontMetrics($d, $prueba)['textWidth'] > $max) {
			$lineas[] = $actual;
			$actual = $palabra;
		} else {
			$actual = $prueba;
		}
	}
	if ($actual !== '') {
		$lineas[] = $actual;
	}
	return $lineas;
}

/** Fondo oliva con luz suave a la izquierda y bordes algo más oscuros (profundidad, como el póster). */
function fondo(): Imagick {
	$img = new Imagick();
	$img->newImage(ANCHO, ALTO, new ImagickPixel(OLIVA));
	$img->setImageColorspace(Imagick::COLORSPACE_SRGB);
	$luz = new Imagick();
	$luz->newPseudoImage(1500, 1500, 'radial-gradient:#857e53-#5d5838');
	$luz->cropImage(ANCHO, ALTO, 180, 420);
	$luz->setImagePage(0, 0, 0, 0);
	$img->compositeImage($luz, Imagick::COMPOSITE_OVER, 0, 0);
	return $img;
}

/**
 * Polígono ortogonal con esquinas redondeadas, en sentido horario: [[x, y, radio], …]. Las esquinas entrantes (las
 * que giran a la izquierda) se redondean hacia dentro, así que la misma función dibuja cuadrados y formas en L.
 */
function poligono(ImagickDraw $d, array $vertices): void {
	$n = count($vertices);
	$tramos = [];
	foreach ($vertices as $i => [$x, $y, $r]) {
		[$px, $py] = $vertices[($i + $n - 1) % $n];
		[$sx, $sy] = $vertices[($i + 1) % $n];
		$ex = $x <=> $px; $ey = $y <=> $py;   // dirección con la que se llega a la esquina
		$fx = $sx <=> $x; $fy = $sy <=> $y;   // dirección con la que se sale
		$tramos[] = [$x - $ex * $r, $y - $ey * $r, $x + $fx * $r, $y + $fy * $r, $r, $ex * $fy - $ey * $fx > 0];
	}
	$d->pathStart();
	$d->pathMoveToAbsolute($tramos[$n - 1][2], $tramos[$n - 1][3]);
	foreach ($tramos as [$x1, $y1, $x2, $y2, $r, $saliente]) {
		$d->pathLineToAbsolute($x1, $y1);
		$d->pathEllipticArcAbsolute($r, $r, 0, false, $saliente, $x2, $y2);
	}
	$d->pathClose();
	$d->pathFinish();
}

/**
 * La composición del póster del manual de marca (assets/merch/poster.jpg). Delante, dos cuadrados grandes (verde
 * arriba a la izquierda, gris claro abajo a la derecha) a los que les falta la esquina del centro; detrás, dos más
 * chicos (naranja arriba a la derecha, amarillo abajo a la izquierda) con una muesca más pequeña, así que en el centro
 * asoman como un borde fino alrededor de un hueco por el que se ve el fondo. Es simétrica: los de abajo son los de
 * arriba girados 180°. Medidas en píxeles del póster, con el centro del hueco en (0, 0); $k las escala.
 */
function formas(Imagick $img, float $cx, float $cy, float $k): void {
	$grande = [[-230, -230, 44], [50, -230, 44], [50, -50, 20], [-50, -50, 26], [-50, 50, 20], [-230, 50, 44]];
	$chica = [[-24, -152, 30], [152, -152, 30], [152, 24, 30], [35, 24, 13], [35, -35, 22], [-24, -35, 13]];
	$girar = fn(array $v) => array_map(fn($p) => [-$p[0], -$p[1], $p[2]], $v);
	$ubicar = fn(array $v) => array_map(fn($p) => [$cx + $p[0] * $k, $cy + $p[1] * $k, $p[2] * $k], $v);
	$d = new ImagickDraw();
	$d->setStrokeOpacity(0);
	foreach ([['#df5f0c', $chica], ['#f4a700', $girar($chica)], ['#83b300', $grande], ['#e9eef1', $girar($grande)]] as [$color, $v]) {
		$d->setFillColor(new ImagickPixel($color));
		poligono($d, $ubicar($v));
	}
	$img->drawImage($d);
}

/** Franja de rombos de la marca (como el borde de los formularios y del pie de la web), sin el verde azulado: sobre el oliva casi no se ve. */
function rombos(Imagick $img, int $x, int $y): void {
	$d = new ImagickDraw();
	foreach (['#f4a700', '#83b300', '#df5f0c', '#e9eef1'] as $i => $c) {
		$d->setFillColor(new ImagickPixel($c));
		$px = $x + $i * 22;
		$d->polygon([['x' => $px, 'y' => $y - 6], ['x' => $px + 6, 'y' => $y], ['x' => $px, 'y' => $y + 6], ['x' => $px - 6, 'y' => $y]]);
	}
	$img->drawImage($d);
}

function imagen(array $t, array $f): Imagick {
	$img = fondo();
	formas($img, 878, 315, 1.05);

	// Logo
	$logo = new Imagick(RAIZ . '/assets/logo-light.png');
	$logo->resizeImage(0, 48, Imagick::FILTER_LANCZOS, 1);
	$img->compositeImage($logo, Imagick::COMPOSITE_OVER, 80, 72);

	// Antetítulo
	$d = new ImagickDraw();
	$d->setFont($f['medium']);
	$d->setFontSize(17);
	$d->setTextKerning(2.6);
	$d->setFillColor(new ImagickPixel(CLARO));
	$d->setFillOpacity(0.82);
	$img->annotateImage($d, 82, 372, 0, $t['antetitulo']);

	// Titular
	$h = new ImagickDraw();
	$h->setFont($f['bold']);
	$h->setFontSize(66);
	$h->setTextKerning(-1.2);
	$h->setFillColor(new ImagickPixel(CLARO));
	$lineas = partir($img, $h, $t['titulo'], 520);
	$y = 438;
	foreach (array_slice($lineas, 0, 3) as $linea) {
		$img->annotateImage($h, 78, $y, 0, $linea);
		$y += 70;
	}

	// Rombos y dirección de la web
	rombos($img, 88, 566);
	$w = new ImagickDraw();
	$w->setFont($f['medium']);
	$w->setFontSize(20);
	$w->setTextKerning(0.4);
	$w->setFillColor(new ImagickPixel(CLARO));
	$w->setFillOpacity(0.9);
	$img->annotateImage($w, 174, 573, 0, 'sunqujourneys.com');

	$img->stripImage();
	$img->setImageFormat('jpeg');
	$img->setImageCompressionQuality(88);
	$img->setInterlaceScheme(Imagick::INTERLACE_PLANE); // JPEG progresivo
	$img->setOption('jpeg:sampling-factor', '1x1');      // bordes de color nítidos
	return $img;
}

$dir = RAIZ . '/assets/og';
if (!is_dir($dir)) {
	mkdir($dir, 0775, true);
}
foreach (SUNQU_OG_TEXTOS as $lang => $t) {
	$f = fuentes($lang);
	if (!$f) {
		fwrite(STDERR, "· $lang: sin fuente con cirílico (CIRILICO_BOLD / CIRILICO_MEDIUM), se salta\n");
		continue;
	}
	$archivo = "$dir/sunqu-og-$lang.jpg";
	imagen($t, $f)->writeImage($archivo);
	printf("✓ assets/og/sunqu-og-%s.jpg  %d KB\n", $lang, (int) round(filesize($archivo) / 1024));
}
