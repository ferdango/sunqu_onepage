<?php
/**
 * Scripts de WordPress › Sunqu › Scripts y seguimiento (GTM, Analytics, Pixel, Hotjar, un chat…), en todas las páginas.
 *
 *   · «Siempre (necesario)»: se insertan tal cual.
 *   · «Con permiso de analítica / marketing»: cada <script> queda inerte (type="text/plain" data-sunqu-consent="…") y
 *     js/sunqu-analytics.js lo activa cuando la persona acepta en el aviso de cookies. Se quitan sus <noscript>,
 *     <img> e <iframe>: sin JavaScript no hay forma de pedir permiso.
 * La CSP autoriza los scripts en línea de estos códigos por su hash y los dominios que aparecen en ellos (más los
 * «Dominios adicionales» del panel). Es una decisión de confianza: solo un administrador de WordPress puede
 * editarlos (docs/SECURITY.md). CMS_SCRIPTS=off en el .env hace que la web los ignore.
 *
 * Google (GTM, gtag.js) marcado «necesario» = Consent Mode avanzado: se carga siempre, pero el estado de
 * consentimiento por defecto (denegado) se fija antes que cualquier script del panel (sunqu_consentimiento_js) y
 * el aviso de cookies aparece igual para pedir permiso.
 */

declare(strict_types=1);

const SUNQU_SCRIPTS_LUGARES = ['head', 'body_inicio', 'body_fin'];

/** Dominio válido para la CSP (https://x.y, https://*.x.y, wss://…); '' si no lo es. */
function sunqu_dominio_csp(string $d): string {
	$d = strtolower(trim($d));
	return preg_match('#^(https|wss)://(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(:\d{1,5})?$#', $d) ? $d : '';
}

/** ¿El <script> con estos atributos es ejecutable? (sin type, o un tipo de JavaScript). */
function sunqu_script_ejecutable(string $attrs): bool {
	if (!preg_match('/\stype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $t)) {
		return true;
	}
	return in_array(strtolower($t[1]), ['text/javascript', 'application/javascript', 'module', 'text/ecmascript', 'application/ecmascript'], true);
}

/**
 * Un código del panel listo para la página: [html, hashes de sus scripts en línea, dominios que menciona].
 * $categoria: necesario | analitica | marketing.
 */
function sunqu_script_preparar(string $codigo, string $categoria): array {
	$codigo = preg_replace('#</?(html|head|body)\b[^>]*>#i', '', $codigo) ?? '';
	$conPermiso = $categoria !== 'necesario';
	if ($conPermiso) {
		$codigo = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', '', $codigo) ?? '';
		$codigo = preg_replace('#<(img|iframe)\b[^>]*>(?:\s*</\1>)?#is', '', $codigo) ?? '';
	}
	// Dominios de lo que queda (lo quitado no se carga: no hace falta autorizarlo)
	$dominios = [];
	if (preg_match_all('#(?:https?:)?//((?:[a-z0-9-]+\.)+[a-z]{2,})(?=[/:"\'\s?]|$)#i', $codigo, $m)) {
		foreach ($m[1] as $h) {
			$dominios[] = 'https://' . strtolower($h);
		}
	}
	$hashes = [];
	$codigo = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', function ($m) use (&$hashes, $conPermiso, $categoria) {
		[$todo, $attrs, $js] = $m;
		if (!sunqu_script_ejecutable($attrs)) {
			return $todo; // datos (JSON-LD, plantillas): el navegador no los ejecuta
		}
		$conSrc = (bool) preg_match('/\ssrc\s*=/i', $attrs);
		if (!$conSrc && trim($js) !== '') {
			$hashes[] = sunqu_csp_hash($js); // el mismo contenido que tendrá al activarse
		}
		if (!$conPermiso) {
			return $todo;
		}
		$attrs = preg_replace('/\stype\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs) ?? '';
		$attrs = preg_replace('/\ssrc\s*=/i', ' data-src=', $attrs) ?? '';
		return '<script type="text/plain" data-sunqu-consent="' . $categoria . '"' . $attrs . '>' . $js . '</script>';
	}, $codigo) ?? '';
	return [$codigo, $hashes, $dominios];
}

/**
 * Scripts de la página: ['head' => html, 'body_inicio' => html, 'body_fin' => html, 'hashes' => [], 'dominios' => [],
 * 'consentimiento' => ['analitica' => [nombres], 'marketing' => [nombres]]].
 */
function sunqu_scripts_pagina(array $c): array {
	$out = ['head' => '', 'body_inicio' => '', 'body_fin' => '', 'hashes' => [], 'dominios' => [], 'consentimiento' => ['analitica' => [], 'marketing' => [], 'google' => [], 'gtag' => false]];
	$s = $c['sitio']['_scripts'] ?? null;
	if (sunqu_config()['CMS_SCRIPTS'] === 'off' || !is_array($s)) {
		return $out;
	}
	foreach (array_slice((array) ($s['items'] ?? []), 0, 20) as $it) {
		if (!is_array($it) || !is_string($it['codigo'] ?? null) || trim($it['codigo']) === '') {
			continue;
		}
		$lugar = in_array($it['ubicacion'] ?? '', SUNQU_SCRIPTS_LUGARES, true) ? $it['ubicacion'] : 'head';
		$cat = in_array($it['categoria'] ?? '', ['necesario', 'analitica', 'marketing'], true) ? $it['categoria'] : 'analitica';
		$nombre = trim(preg_replace('/-{2,}/', '-', preg_replace('/[^\p{L}\p{N} ._·()-]/u', '', (string) ($it['nombre'] ?? '')) ?? '') ?? '') ?: 'Script';
		[$html, $hashes, $dominios] = sunqu_script_preparar($it['codigo'], $cat);
		$out[$lugar] .= "\n<!-- " . $nombre . " (Sunqu › Scripts y seguimiento) -->\n" . $html . "\n";
		array_push($out['hashes'], ...$hashes);
		array_push($out['dominios'], ...$dominios);
		if ($cat !== 'necesario') {
			$out['consentimiento'][$cat][] = $nombre;
		} elseif (preg_match('#googletagmanager\.com|google-analytics\.com#i', $it['codigo'])) {
			$out['consentimiento']['google'][] = $nombre; // Consent Mode: mide sin cookies hasta que acepten
		}
		// gtag.js sin GTM (GA4 pegado en el panel): la web le manda sus eventos con gtag('event')
		if (preg_match('#googletagmanager\.com/gtag/js#i', $it['codigo']) && !preg_match('#gtm\.js#i', $it['codigo'])) {
			$out['consentimiento']['gtag'] = true;
		}
	}
	foreach ((array) ($s['dominios'] ?? []) as $d) {
		if (is_string($d) && ($ok = sunqu_dominio_csp($d)) !== '') {
			$out['dominios'][] = $ok;
		}
	}
	$out['hashes'] = array_values(array_unique($out['hashes']));
	$out['dominios'] = array_values(array_unique(array_filter(array_map('sunqu_dominio_csp', $out['dominios']))));
	$out['consentimiento'] = array_map(fn($n) => is_array($n) ? array_values(array_unique($n)) : $n, $out['consentimiento']);
	return $out;
}

/**
 * Consent Mode v2 por defecto, en el script de arranque (antes que los scripts del panel): la elección guardada
 * en el aviso («sunqu-consent») o todo denegado. js/sunqu-analytics.js después solo lo actualiza.
 */
function sunqu_consentimiento_js(): string {
	$off = sunqu_config()['CONSENT_MODE'] === 'off' ? 'true' : 'false';
	return '(function(){var c=null,o=' . $off . ';try{c=JSON.parse(localStorage.getItem("sunqu-consent")||"null")}catch(e){}'
		. 'if(!c||c.v!==1)c={};var a=o||!!c.a,m=o||!!c.m,g=function(x){return x?"granted":"denied"};'
		. 'window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments)};'
		. 'gtag("consent","default",{analytics_storage:g(a),ad_storage:g(m),ad_user_data:g(m),ad_personalization:g(m),functionality_storage:"granted",security_storage:"granted",wait_for_update:500});'
		. 'gtag("set","ads_data_redaction",true);window.__sunquConsent=1})();';
}
