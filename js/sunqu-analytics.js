/*!
 * Sunqu · medición (Google Tag Manager, Google Analytics 4 y Meta Pixel) con consentimiento
 * ─────────────────────────────────────────────────────────────────────────────
 * Los IDs llegan en window.SUNQU_CONFIG (servidor, variables GTM_ID, GA4_ID y META_PIXEL_ID del .env).
 * Sin IDs no se carga ningún servicio externo ni se muestra el aviso de cookies.
 *
 *   · Con GTM_ID: se carga GTM y GA4 y el Pixel se configuran dentro de GTM (docs/ANALYTICS.md).
 *   · Sin GTM_ID: GA4 (gtag.js) y el Pixel (fbevents.js) se cargan directo, solo después de aceptar.
 *   · Consent Mode v2: todo empieza denegado; el aviso guarda la elección en localStorage «sunqu-consent».
 *
 * Eventos: la web llama SunquContent.medir(evento, parámetros); aquí se validan (lista blanca de
 * parámetros, sin correos ni teléfonos) y se envían a dataLayer, GA4 y Meta. Nunca se envían datos
 * de los formularios.
 *
 * Scripts de WordPress › Sunqu › Scripts y seguimiento: los de «analítica» o «marketing» llegan inertes
 * (<script type="text/plain" data-sunqu-consent="…">, server/lib/scripts.php) y se activan aquí cuando la persona
 * acepta. El aviso de cookies los nombra. Si después elige «Solo necesarias», la página se recarga sin ellos.
 */
(function () {
  'use strict';

  var C = (window.SunquContent && window.SunquContent.config) || window.SUNQU_CONFIG || {};
  var ID = function (v, re) { v = String(v || '').trim(); if (v && !re.test(v)) { console.warn('[sunqu] ID de medición con formato inválido:', v); return ''; } return v; };
  var GTM = ID(C.gtm, /^GTM-[A-Z0-9]+$/), GA4 = ID(C.ga4, /^G-[A-Z0-9]+$/), PIXEL = ID(C.pixel, /^\d{6,20}$/);
  // Nombres de los scripts del panel que esperan permiso: { analitica: ['Hotjar'], marketing: ['TikTok Pixel'] }
  var PANEL = C.scripts || {};
  // google: GTM o gtag.js marcados «necesario» (Consent Mode: se cargan siempre y miden con cookies solo con permiso)
  var nombres = function (cat) { return Array.isArray(PANEL[cat]) ? PANEL[cat].map(String) : []; };
  var CON_PANEL = nombres('analitica').length + nombres('marketing').length + nombres('google').length > 0;
  var HAY = !!(GTM || GA4 || PIXEL || CON_PANEL);
  var MODO = C.consent === 'off' ? 'off' : 'banner';
  var CLAVE = 'sunqu-consent';
  var PAGE = (window.SunquContent && window.SunquContent.page) || '';

  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  window.gtag = window.gtag || gtag;

  // Textos del aviso traducidos (Loco Translate, ver server/lib/idiomas.php); sin traducción, en español
  var I18N = window.SUNQU_I18N || {};
  function t(s) { var d = I18N.dic || {}; return Object.prototype.hasOwnProperty.call(d, s) && d[s] ? d[s] : s; }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // ── Consentimiento ────────────────────────────────────────────────────────
  function leer() {
    if (MODO === 'off') return { a: true, m: true };
    try { var c = JSON.parse(window.localStorage.getItem(CLAVE) || 'null'); if (c && c.v === 1) return c; } catch (e) {}
    return null;
  }
  var elegido = leer();
  var permiso = { a: !!(elegido && elegido.a), m: !!(elegido && elegido.m) };
  function estadoGoogle(p) {
    return {
      analytics_storage: p.a ? 'granted' : 'denied',
      ad_storage: p.m ? 'granted' : 'denied', ad_user_data: p.m ? 'granted' : 'denied', ad_personalization: p.m ? 'granted' : 'denied',
    };
  }
  // En producción el estado por defecto ya lo fijó el script de arranque, antes que los scripts del panel
  if (!window.__sunquConsent) {
    var def = estadoGoogle(permiso);
    def.functionality_storage = 'granted'; def.security_storage = 'granted'; def.wait_for_update = 500;
    gtag('consent', 'default', def);
    gtag('set', 'ads_data_redaction', true);
  }

  // ── Privacidad de las URLs y de los parámetros ────────────────────────────
  var PARECE_PII = /@|\+?\d[\d\s().-]{6,}\d/;
  function urlLimpia() {
    var q = [];
    try {
      new URLSearchParams(window.location.search).forEach(function (v, k) { if (!PARECE_PII.test(v)) q.push(encodeURIComponent(k) + '=' + encodeURIComponent(v)); });
    } catch (e) {}
    return window.location.origin + window.location.pathname + (q.length ? '?' + q.join('&') : '');
  }
  var PERMITIDOS = ['form_name', 'ubicacion', 'experience_id', 'experience_name', 'list_name', 'search_term', 'content_type', 'content_id',
    'item_id', 'item_name', 'item_category', 'item_variant', 'method', 'link_domain', 'pagina', 'items',
    'language', 'source', 'from', 'to']; // language_detected / language_changed (códigos de idioma y de origen)
  function valor(v) {
    if (typeof v === 'number' || typeof v === 'boolean') return v;
    if (typeof v !== 'string') return undefined;
    v = v.trim().slice(0, 100);
    return v && !PARECE_PII.test(v) ? v : undefined;
  }
  function limpiar(p) {
    var o = {};
    Object.keys(p || {}).forEach(function (k) {
      if (PERMITIDOS.indexOf(k) < 0) return;
      if (k === 'items' && Array.isArray(p.items)) {
        o.items = p.items.slice(0, 10).map(function (it) { var x = {}; Object.keys(it || {}).forEach(function (kk) { var v = valor(it[kk]); if (v !== undefined && /^item_/.test(kk)) x[kk] = v; }); return x; });
        return;
      }
      var v = valor(p[k]);
      if (v !== undefined) o[k] = v;
    });
    return o;
  }

  // ── Carga de los servicios ────────────────────────────────────────────────
  function script(src) { var s = document.createElement('script'); s.async = true; s.src = src; document.head.appendChild(s); }
  var gaListo = false, fbListo = false;
  function cargarGTM() {
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    script('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(GTM));
  }
  function cargarGA4() {
    if (gaListo || !GA4 || GTM) return;
    gaListo = true;
    script('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(GA4));
    gtag('js', new Date());
    gtag('config', GA4, { page_location: urlLimpia(), page_referrer: document.referrer ? document.referrer.split(/[?#]/)[0] : undefined, allow_google_signals: false, allow_ad_personalization_signals: false });
  }
  function cargarPixel() {
    if (fbListo || !PIXEL || GTM) return;
    fbListo = true;
    /* eslint-disable */
    !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    /* eslint-enable */
    window.fbq('set', 'autoConfig', false, PIXEL); // sin detección automática de botones ni formularios
    window.fbq('init', PIXEL);
    window.fbq('track', 'PageView');
  }

  // Scripts del panel de una categoría: cada <script type="text/plain"> se reemplaza por uno ejecutable, en orden
  var activos = { analitica: false, marketing: false };
  function activar(cat) {
    activos[cat] = true;
    Array.prototype.forEach.call(document.querySelectorAll('script[type="text/plain"][data-sunqu-consent="' + cat + '"]'), function (viejo) {
      var s = document.createElement('script');
      Array.prototype.forEach.call(viejo.attributes, function (a) {
        if (a.name !== 'type' && a.name !== 'data-sunqu-consent') s.setAttribute(a.name === 'data-src' ? 'src' : a.name, a.value);
      });
      if (s.src && !viejo.hasAttribute('async')) s.async = false; // los externos se ejecutan en el orden del panel
      s.text = viejo.text; // mismo contenido: la CSP lo autoriza por su hash
      viejo.parentNode.replaceChild(s, viejo);
    });
  }

  // Equivalencias con los eventos estándar de Meta (con GTM se configuran allí, ver docs/ANALYTICS.md)
  function meta(evento, p) {
    if (!fbListo || !window.fbq) return;
    var id = p.experience_id || p.item_id || (p.items && p.items[0] && p.items[0].item_id);
    var nombre = p.experience_name || p.item_name || (p.items && p.items[0] && p.items[0].item_name);
    if (evento === 'click_whatsapp') window.fbq('track', 'Contact');
    else if (evento === 'generate_lead') window.fbq('track', 'Lead');
    else if (evento === 'view_experience') window.fbq('track', 'ViewContent', { content_ids: id ? [id] : [], content_name: nombre || '', content_type: 'product' });
    else if (evento === 'add_to_cart') window.fbq('track', 'AddToCart', { content_ids: id ? [id] : [], content_name: nombre || '', content_type: 'product' });
  }

  // Antes de elegir, los eventos de esta página esperan (y se descartan si se rechaza).
  var espera = [];
  function evento(nombre, params) {
    if (!/^[a-z_]{2,40}$/.test(String(nombre))) return;
    var p = limpiar(params);
    if (nombre === 'search' && !p.search_term) return; // la búsqueda parecía un correo o un teléfono: no se mide
    if (!p.pagina && PAGE) p.pagina = PAGE;
    // A dataLayer, para GTM (del .env o pegado en el panel); queda en el navegador hasta que GTM se carga
    if (GTM || CON_PANEL) { var o = { event: nombre }; Object.keys(p).forEach(function (k) { o[k] = p[k]; }); window.dataLayer.push(o); }
    if (PANEL.gtag && !GTM && !GA4) gtag('event', nombre, p); // GA4 pegado en el panel (gtag.js lo envía cuando carga)
    if (GTM || !(GA4 || PIXEL)) return;
    if (!gaListo && !fbListo) { if (!elegido && espera.length < 20) espera.push([nombre, p]); return; }
    if (gaListo) gtag('event', nombre, p);
    meta(nombre, p);
  }

  function aplicar(p, guardar) {
    permiso = { a: !!p.a, m: !!p.m };
    if (guardar) {
      elegido = { v: 1, a: permiso.a, m: permiso.m, t: new Date().toISOString() };
      try { window.localStorage.setItem(CLAVE, JSON.stringify(elegido)); } catch (e) {}
    }
    gtag('consent', 'update', estadoGoogle(permiso));
    if (GTM || CON_PANEL) window.dataLayer.push({ event: 'sunqu_consent_update', consent_analytics: permiso.a, consent_marketing: permiso.m });
    if (permiso.a) { cargarGA4(); activar('analitica'); }
    if (permiso.m) { cargarPixel(); activar('marketing'); }
    if (window.fbq && fbListo) window.fbq('consent', permiso.m ? 'grant' : 'revoke');
    var cola = espera; espera = [];
    if (permiso.a || permiso.m) cola.forEach(function (e) { evento(e[0], e[1]); });
  }

  // ── Aviso de cookies ─────────────────────────────────────────────────────
  var aviso = null;
  function enlaceDatos() {
    var base = (window.SunquContent && window.SunquContent.base) || '/';
    return C.rutas === 'limpias' ? (I18N.prefijo || '') + '/terminos#datos' : base + (I18N.contenido ? I18N.lang + '/' : '') + 'terminos.html#datos';
  }
  function abrirAviso(desdeBoton) {
    if (!HAY || MODO !== 'banner') return;
    if (!aviso) {
      // Para qué y con qué servicios (los del .env y los del panel de WordPress)
      var analitica = (GA4 || GTM ? ['Google Analytics'] : []).concat(nombres('google'), nombres('analitica'));
      var marketing = (PIXEL || GTM ? ['Meta'] : []).concat(nombres('marketing'));
      var usos = [];
      if (analitica.length) usos.push(t('medir cómo se usa la web') + ' (' + analitica.join(', ') + ')');
      if (marketing.length) usos.push(t('medir nuestros anuncios') + ' (' + marketing.join(', ') + ')');
      var st = document.createElement('style');
      st.textContent = '.sq-cc{position:fixed;left:16px;bottom:16px;z-index:2147483000;width:min(440px,calc(100% - 32px));box-sizing:border-box;padding:20px 20px 18px;border-radius:18px;background:#1c1c1c;color:#e9eef1;border:1px solid rgba(233,238,241,.16);box-shadow:0 18px 50px rgba(0,0,0,.35);font:15px/1.5 Aeonik,system-ui,sans-serif}'
        + '.sq-cc[hidden]{display:none}.sq-cc-t{margin:0 0 6px;font-weight:600;font-size:16px}.sq-cc-d{margin:0;color:rgba(233,238,241,.85)}.sq-cc-d a{color:#e9eef1;text-decoration:underline;text-underline-offset:3px}'
        + '.sq-cc-b{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}.sq-cc-b button{flex:1 1 150px;min-height:44px;padding:0 18px;border-radius:999px;font:inherit;font-weight:500;cursor:pointer;border:1px solid rgba(233,238,241,.4);background:transparent;color:#e9eef1}'
        + '.sq-cc-b .sq-si{background:#83b300;border-color:#83b300;color:#1c1c1c}.sq-cc-b button:hover{border-color:#e9eef1}.sq-cc-b .sq-si:hover{background:#94c40d}'
        + '.sq-cc :focus-visible{outline:2px solid #f4a700;outline-offset:3px}'
        + '@media (prefers-reduced-motion:no-preference){.sq-cc{animation:sqcc .45s cubic-bezier(.2,.7,.1,1)}@keyframes sqcc{from{opacity:0;transform:translateY(12px)}}}';
      document.head.appendChild(st);
      aviso = document.createElement('div');
      aviso.className = 'sq-cc';
      aviso.setAttribute('role', 'dialog');
      aviso.setAttribute('aria-modal', 'false');
      aviso.setAttribute('aria-labelledby', 'sq-cc-t');
      aviso.setAttribute('aria-describedby', 'sq-cc-d');
      aviso.innerHTML = '<p class="sq-cc-t" id="sq-cc-t">' + esc(t('Cookies de medición')) + '</p>'
        + '<p class="sq-cc-d" id="sq-cc-d">' + esc(t('Con tu permiso usamos cookies para %s.').replace('%s', function () { return usos.join(' ' + t('y') + ' '); })) + ' '
        + esc(t('Nunca enviamos lo que escribes en los formularios.')) + ' <a href="' + esc(enlaceDatos()) + '">' + esc(t('Protección de datos')) + '</a></p>'
        + '<div class="sq-cc-b"><button type="button" data-cc="no">' + esc(t('Solo necesarias')) + '</button><button type="button" class="sq-si" data-cc="si">' + esc(t('Aceptar')) + '</button></div>';
      aviso.addEventListener('click', function (e) {
        var b = e.target.closest('[data-cc]');
        if (!b) return;
        var si = b.getAttribute('data-cc') === 'si';
        // Un script del panel ya activo no se puede detener: al quitar el permiso, la página se recarga sin él
        var recargar = !si && (activos.analitica || activos.marketing);
        aplicar({ a: si, m: si }, true);
        if (recargar) { window.location.reload(); return; }
        aviso.hidden = true;
        if (aviso._volver && aviso._volver.focus) aviso._volver.focus({ preventScroll: true });
      });
      aviso.addEventListener('keydown', function (e) { if (e.key === 'Escape' && elegido) aviso.hidden = true; });
      // Al inicio del documento, justo después del enlace «Saltar al contenido», para llegar con el teclado
      var skip = document.querySelector('.sj-skip');
      if (skip && skip.parentNode === document.body) document.body.insertBefore(aviso, skip.nextSibling);
      else document.body.insertBefore(aviso, document.body.firstChild);
    }
    aviso.hidden = false;
    if (desdeBoton) {
      aviso._volver = document.activeElement;
      var b = aviso.querySelector(elegido && elegido.a ? '[data-cc="no"]' : '[data-cc="si"]');
      if (b) b.focus({ preventScroll: true });
    }
  }

  // ── Interacciones que se miden sin tocar el código de cada página ─────────
  function ubicacion(el) {
    if (el.getAttribute('data-track')) return el.getAttribute('data-track');
    if (el.closest('.sj-wa')) return 'flotante';
    if (el.closest('.sj-drawer')) return 'menu';
    if (el.closest('header')) return 'cabecera';
    if (el.closest('footer')) return 'pie';
    var s = el.closest('section[id], [id].sj-form-card');
    return s ? s.id : 'pagina';
  }
  var RED = { 'wa.me': 'whatsapp', 'api.whatsapp.com': 'whatsapp', 'www.facebook.com': 'facebook', 'twitter.com': 'x', 'x.com': 'x', 'www.linkedin.com': 'linkedin' };
  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-cookie-prefs]')) { e.preventDefault(); abrirAviso(true); return; }
    var a = t.closest('a[href]');
    if (!a) return;
    var url;
    try { url = new URL(a.getAttribute('href'), window.location.href); } catch (err) { return; }
    var host = url.hostname, lugar = ubicacion(a);
    if (a.closest('.ar-share')) {
      evento('share', { method: RED[host] || host, content_type: 'articulo', item_id: (window.SunquContent && window.SunquContent.param('a')) || '' });
      return;
    }
    if (host === 'wa.me' || host === 'api.whatsapp.com') {
      if (lugar === 'bolsa') evento('begin_checkout', { ubicacion: lugar });
      evento('click_whatsapp', { ubicacion: lugar });
      return;
    }
    var j = url.pathname.match(/\/journeys\/([\w-]+)$/) || (/journey\.html$/.test(url.pathname) && url.searchParams.get('j') ? [0, url.searchParams.get('j')] : null);
    if (j && url.host === window.location.host) { evento('click_experience', { experience_id: j[1], list_name: lugar }); return; }
    if (a.matches('.sj-cta, .sj-drawer-cta') || /#(contacto|reservar)$/.test(a.getAttribute('href'))) { evento('cta_click', { ubicacion: lugar }); return; }
    if (/^https?:$/.test(url.protocol) && url.host !== window.location.host) evento('outbound_click', { link_domain: host });
  });
  var empezados = {};
  document.addEventListener('focusin', function (e) {
    var f = e.target && e.target.closest && e.target.closest('form[data-form]');
    if (!f) return;
    var n = f.getAttribute('data-form');
    if (empezados[n]) return;
    empezados[n] = true;
    evento('form_start', { form_name: n });
  });

  // ── Arranque ──────────────────────────────────────────────────────────────
  window.dataLayer.push({ pagina: PAGE, entorno: C.env || '', sunqu_page_location: urlLimpia() });
  if (GTM) cargarGTM();
  if (HAY) {
    if (MODO === 'banner') document.documentElement.classList.add('sj-consent'); // muestra «Preferencias de cookies» en el pie
    if (elegido) aplicar(elegido, false);
    else if (MODO === 'banner') {
      if (document.body) abrirAviso(false); else document.addEventListener('DOMContentLoaded', function () { abrirAviso(false); });
    }
  }

  window.SunquTrack = {
    evento: evento,
    preferencias: function () { abrirAviso(true); },
    consentimiento: function () { return { analitica: permiso.a, marketing: permiso.m, elegido: !!elegido }; },
  };
  // Eventos que la página registró antes de que este archivo cargara
  (window.__sunquCola || []).forEach(function (e) { evento(e[0], e[1]); });
  window.__sunquCola = [];
})();
