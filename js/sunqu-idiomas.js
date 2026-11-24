/*!
 * Sunqu · idiomas de la web estática (GitHub Pages)
 * ─────────────────────────────────────────────────────────────────────────────
 * Las páginas en español están en la raíz (/, /descubre.html…) y cada idioma en su carpeta (/en/, /fr/…), generadas
 * por tools/idiomas-estaticos.py. Este archivo arma window.SUNQU_I18N a partir de la URL: el idioma de la página,
 * la misma página en cada idioma (selector) y los textos de js/ traducidos (window.SUNQU_I18N_DIC, que la página
 * generada define antes de cargar este archivo). Con el servidor PHP, window.SUNQU_I18N ya viene definido y esto
 * no hace nada.
 */
(function () {
  'use strict';
  if (window.SUNQU_I18N) return;
  var IDIOMAS = ['es', 'en', 'fr', 'de', 'it', 'ru'];
  // /en/journey.html → raíz «/», idioma «en», archivo «journey.html» (también bajo una subcarpeta, p. ej. /sunqu_onepage/)
  var m = window.location.pathname.match(/^(.*?\/)(?:(en|fr|de|it|ru)\/)?([^\/]*)$/);
  var raiz = m ? m[1] : '/';
  var lang = (m && m[2]) || 'es';
  var archivo = m && m[3] && m[3] !== 'index.html' ? m[3] : '';
  var alternos = {};
  IDIOMAS.forEach(function (l) { alternos[l] = raiz + (l === 'es' ? '' : l + '/') + archivo; });
  window.SUNQU_I18N = {
    lang: lang,
    base: 'es',
    prefijo: '',
    alternos: alternos,
    selector: true,
    contenido: lang !== 'es', // content/<idioma>/*.json
    dic: window.SUNQU_I18N_DIC || {},
  };
  document.documentElement.lang = lang;
})();
