/*
 * Sunqu · conexión con WordPress
 * ─────────────────────────────────────────────────────────────────────────────
 * api: URL base de la API REST de WordPress (termina en /wp-json).
 *   ''                                           → la web usa los archivos de content/ (modo estático, GitHub Pages).
 *   'http://localhost:8080/wp-json'              → WordPress local del entorno Docker (wordpress/docker-compose.yml).
 *   'https://cms.sunqujourneys.com/wp-json'      → WordPress en producción.
 *
 * Con api configurada, el contenido se pide a GET {api}/sunqu/v1/contenido y los formularios
 * se envían a POST {api}/sunqu/v1/formularios/{solicitud|resena|reclamacion}.
 * Si WordPress no responde, la web vuelve a content/*.json automáticamente.
 *
 * gtm: contenedor de Google Tag Manager de la web estática (GitHub Pages no tiene servidor que inserte los scripts
 *   de WordPress › Sunqu › Scripts y seguimiento). Analytics (G-NEPSERFBX8) se configura dentro del contenedor;
 *   js/sunqu-analytics.js lo carga con Consent Mode y muestra el aviso de cookies. Con el servidor PHP manda GTM_ID.
 */
window.SUNQU_CMS = {
  api: '',
  gtm: 'GTM-P64T6BPH',
};
