/*!
 * Sunqu · capa de contenido
 * ─────────────────────────────────────────────────────────────────────────────
 * Carga el contenido de la página y después arranca el runtime (support.js).
 *
 *   <script src="js/sunqu-config.js"></script>
 *   <script src="js/sunqu-content.js" data-page="journey"></script>
 *
 * Orden de origen del contenido:
 *   1. window.SUNQU_PRELOADED  → lo inyecta el servidor de producción (server/index.php).
 *   2. WordPress (REST)        → si la configuración tiene api: GET {api}/sunqu/v1/contenido?secciones=…
 *   3. content/*.json          → archivos locales (también es el respaldo si WordPress no responde).
 *
 * Configuración: window.SUNQU_CONFIG (servidor) tiene prioridad sobre window.SUNQU_CMS (js/sunqu-config.js).
 *
 * Expone:
 *   window.SUNQU            contenido normalizado (mismo formato que content/*.json y que la API).
 *   window.SunquView        datos ya adaptados a lo que usa cada página (J, JOURNEYS, POSTS…).
 *   window.SunquContent     utilidades: img, srcset, param, titulo, esc, site, enviar, datosDe, fotos, cargarScript,
 *                           medir (eventos de medición, ver js/sunqu-analytics.js), origen (página de un formulario)
 *                           e idiomas: t (texto traducido), idioma, idiomas e irIdioma (selector de idioma).
 *
 * Idiomas: cada página vive en su idioma (/es/…, /en/…) y el servidor entrega window.SUNQU_I18N (idioma, URL de la
 * misma página en cada idioma activo y los textos de este archivo traducidos en Loco Translate). La raíz (/) elige
 * el idioma en el servidor (server/lib/idiomas.php); aquí solo se guarda la elección del selector (cookie
 * sunqu_language) y se miden language_detected y language_changed.
 */
(function () {
  'use strict';

  var me = document.currentScript;
  var PAGE = (me && me.getAttribute('data-page')) || 'home';
  var BASE = me ? me.src.replace(/js\/sunqu-content\.js(\?.*)?$/, '') : '';
  var CFG = {};
  [window.SUNQU_CMS || {}, window.SUNQU_CONFIG || {}].forEach(function (o) { Object.keys(o).forEach(function (k) { if (o[k] !== '' && o[k] != null) CFG[k] = o[k]; }); });
  var I18N = window.SUNQU_I18N || null; // sin servidor (vista previa estática): solo español
  var LANG = (I18N && I18N.lang) || 'es';
  var PREFIJO = (I18N && I18N.prefijo) || '';
  // Texto fijo traducido (Loco Translate); si no hay traducción, el original en español. Fuera de producción,
  // la consola avisa una vez por texto sin traducir.
  var avisados = {};
  function t(s) {
    var d = (I18N && I18N.dic) || {};
    if (Object.prototype.hasOwnProperty.call(d, s) && d[s]) return d[s];
    if (I18N && LANG !== I18N.base && CFG.env !== 'production' && !avisados[s]) { avisados[s] = 1; console.warn('[sunqu] sin traducción (' + LANG + '):', s); }
    return s;
  }

  // Secciones de contenido que necesita cada página (= archivos de content/ = secciones de la API).
  var NEED = {
    home: ['sitio', 'inicio', 'journeys', 'anfitriones', 'diario'],
    journey: ['sitio', 'journeys', 'anfitriones'],
    descubre: ['sitio', 'journeys', 'experiencias'],
    recuerda: ['sitio', 'resenas', 'productos'],
    blog: ['sitio', 'diario'],
    articulo: ['sitio', 'diario'],
    terminos: ['sitio', 'legales'],
    reclamaciones: ['sitio'],
  };

  // Mientras llega el contenido, la plantilla cruda no se muestra.
  var hide = document.createElement('style');
  hide.textContent = 'x-dc{display:none!important}';
  document.head.appendChild(hide);

  // ── Utilidades ────────────────────────────────────────────────────────────
  // Imagen: acepta una URL completa (WordPress), una ruta local (assets/… o /assets/…) o un id de Unsplash.
  // Con URLs limpias (producción) las rutas locales van desde la raíz: /journeys/alma no es una carpeta.
  function img(v, w, q, h) {
    if (!v) return '';
    if (/^assets\//.test(v)) return CFG.rutas === 'limpias' ? '/' + v : v;
    if (/^(blob:|data:|https?:|\/)/.test(v)) return v;
    return 'https://images.unsplash.com/photo-' + v + '?auto=format&fit=crop&w=' + w + (h ? '&h=' + h : '') + '&q=' + (q || 72);
  }
  // srcset para servir el tamaño justo: Unsplash por ancho; WordPress con los tamaños que entrega la API.
  var IMGSET = {};
  function srcset(v, widths, q, h) {
    if (!v) return '';
    if (/^https?:/.test(v)) return IMGSET[v] || '';
    if (/^(assets\/|blob:|data:|\/)/.test(v)) return '';
    return widths.map(function (w) { return img(v, w, q, h ? Math.round(h * w / widths[widths.length - 1]) : 0) + ' ' + w + 'w'; }).join(', ');
  }
  // Parámetro de la página: en producción viene de la URL limpia (/journeys/alma → j=alma).
  function param(name) {
    var p = window.SUNQU_PARAMS || {};
    if (p[name] != null && p[name] !== '') return String(p[name]);
    try { return new URLSearchParams(window.location.search).get(name); } catch (e) { return null; }
  }
  // Página de origen de un formulario: «journey:alma», «home», «descubre»…
  function origen() {
    var k = PAGE === 'journey' ? param('j') : PAGE === 'articulo' ? param('a') : '';
    return PAGE + (k ? ':' + k : '');
  }
  // Evento de medición (lo procesa js/sunqu-analytics.js; si todavía no cargó, queda en cola).
  function medir(evento, params) {
    try {
      if (window.SunquTrack) window.SunquTrack.evento(evento, params || {});
      else (window.__sunquCola = window.__sunquCola || []).push([evento, params || {}]);
    } catch (e) {}
  }
  // Título de la pestaña: el del SEO (Sunqu › Sitio › SEO) si el servidor lo envió; si no, el de la página.
  function titulo(def) {
    var s = window.SUNQU_SEO;
    return (s && s.title) || def;
  }
  // Texto seguro para insertar en HTML armado a mano (p. ej. las etiquetas del mapa).
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  var scripts = {};
  function cargarScript(src) {
    if (!scripts[src]) {
      scripts[src] = new Promise(function (ok, ko) {
        var s = document.createElement('script');
        s.src = src; s.async = false;
        s.onload = function () { ok(); };
        s.onerror = function () { delete scripts[src]; ko(new Error('No se pudo cargar ' + src)); };
        document.head.appendChild(s);
      });
    }
    return scripts[src];
  }
  function dict(list, key, val) {
    var o = {};
    (list || []).forEach(function (it) { o[it[key]] = typeof val === 'function' ? val(it) : it[val]; });
    return o;
  }
  function omit(o, keys) {
    var r = {};
    Object.keys(o).forEach(function (k) { if (keys.indexOf(k) < 0) r[k] = o[k]; });
    return r;
  }
  // Listas del cuerpo: el contenido guarda { b, t }; las páginas usan [negrita, texto].
  function blocksOut(blocks) {
    return (blocks || []).map(function (b) {
      if (!b.ul) return b;
      var c = omit(b, []);
      c.ul = b.ul.map(function (it) { return Array.isArray(it) ? it : [it.b, it.t]; });
      return c;
    });
  }
  var MON = [t('ene'), t('feb'), t('mar'), t('abr'), t('may'), t('jun'), t('jul'), t('ago'), t('sep'), t('oct'), t('nov'), t('dic')];
  // Enlace a un artículo: URL limpia en producción (con el prefijo del idioma), archivo .html en la vista previa estática.
  function articulo(slug) { return CFG.rutas === 'limpias' ? PREFIJO + '/diario/' + slug : 'articulo.html?a=' + slug; }

  // ── Idiomas ───────────────────────────────────────────────────────────────
  // Cada idioma se muestra en su propia lengua. Solo aparecen los activos en WordPress › Sunqu › Idiomas (y, sin
  // servidor, los tres primeros como vista previa del selector). Agregar uno: aquí y en SUNQU_IDIOMAS_WEB.
  var IDIOMAS = [
    { code: 'es', name: 'Español', hello: 'Hola, bienvenido', icon: 'assets/diamond-orange.png' },
    { code: 'en', name: 'English', hello: 'Hello, welcome', icon: 'assets/diamond-yellow.png' },
    { code: 'pt', name: 'Português', hello: 'Olá, bem-vindo', icon: 'assets/diamond-lime.png' },
    { code: 'fr', name: 'Français', hello: 'Bonjour, bienvenue', icon: 'assets/diamond-teal.png' },
    { code: 'de', name: 'Deutsch', hello: 'Hallo, willkommen', icon: 'assets/diamond-olive.png' },
    { code: 'it', name: 'Italiano', hello: 'Ciao, benvenuto', icon: 'assets/diamond-orange.png' },
    { code: 'ru', name: 'Русский', hello: 'Здравствуйте, добро пожаловать', icon: 'assets/diamond-yellow.png' },
  ];
  var COOKIE_IDIOMA = 'sunqu_language';
  function idioma() { return LANG; }
  function idiomas() {
    var alt = (I18N && I18N.alternos) || null;
    return IDIOMAS.filter(function (l, i) { return alt ? alt[l.code] : i < 3; }).map(function (l) {
      return { code: l.code, name: l.name, hello: l.hello, icon: img(l.icon), on: l.code === LANG };
    });
  }
  function leerCookie(n) {
    var m = document.cookie.match(new RegExp('(?:^|; )' + n + '=([^;]*)'));
    return m ? decodeURIComponent(m[1]) : '';
  }
  function escribirCookie(n, v, segundos) {
    document.cookie = n + '=' + encodeURIComponent(v) + '; Max-Age=' + segundos + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function sesion(k, v) {
    try { if (v === undefined) return window.sessionStorage.getItem(k); if (v === null) window.sessionStorage.removeItem(k); else window.sessionStorage.setItem(k, v); } catch (e) {}
    return null;
  }
  // Abre la misma página en otro idioma (con sus filtros y su ancla) y guarda la elección: la raíz no vuelve a
  // decidir por la persona. false si no hay a dónde ir (vista previa estática).
  function irIdioma(code) {
    var alt = (I18N && I18N.alternos) || {};
    if (!alt[code]) return false;
    escribirCookie(COOKIE_IDIOMA, code, 31536000);
    if (code !== LANG) {
      sesion('sunqu-idioma-cambio', JSON.stringify({ from: LANG, to: code })); // se mide al cargar la otra página
      window.location.href = alt[code] + window.location.search + window.location.hash;
    }
    return true;
  }
  // Al cargar: la URL manda. Si la persona ya había elegido otro idioma y llegó a esta página, su preferencia pasa a
  // ser este. language_detected se mide una vez por visita (con cómo se eligió) y language_changed tras el selector.
  if (I18N) {
    var guardado = leerCookie(COOKIE_IDIOMA);
    if (guardado && guardado !== LANG) escribirCookie(COOKIE_IDIOMA, LANG, 31536000);
    var cambio = sesion('sunqu-idioma-cambio');
    if (cambio) {
      sesion('sunqu-idioma-cambio', null);
      try { var c = JSON.parse(cambio); if (c && c.to === LANG) medir('language_changed', { from: String(c.from), to: LANG, source: 'manual' }); } catch (e) {}
    } else if (!sesion('sunqu-idioma-medido')) {
      var fuente = leerCookie('sunqu_lang_src');
      medir('language_detected', { language: LANG, source: /^(saved_preference|browser|ip|default)$/.test(fuente) ? fuente : 'url' });
    }
    if (leerCookie('sunqu_lang_src')) escribirCookie('sunqu_lang_src', '', 0);
    sesion('sunqu-idioma-medido', '1');
  }

  // Datos de contacto y redes para el footer, el menú lateral y los botones de WhatsApp.
  // WhatsApp: el número de Sunqu › Sitio; si falta, el de la configuración (WHATSAPP_NUMBER).
  function site(c) {
    var s = (c && c.sitio) || {}, k = s.contacto || {}, r = s.redes || {};
    var wa = String(k.whatsapp || CFG.whatsapp || '').replace(/\D+/g, '');
    if (!wa) console.warn('[sunqu] falta el número de WhatsApp (Sunqu › Sitio › WhatsApp o WHATSAPP_NUMBER)');
    // +51 966 461 384 (Perú: 9 dígitos en grupos de 3); otros países: +<número>
    var waLabel = /^51\d{9}$/.test(wa) ? '+51 ' + wa.slice(2).replace(/(\d{3})(?=\d)/g, '$1 ') : wa ? '+' + wa : '';
    return {
      email: k.email || '', phone: k.telefono || '', phoneHref: k.telefonoHref || '', office: k.oficina || '',
      wa: wa, waLabel: waLabel, youtube: r.youtube || '#', instagram: r.instagram || '#', facebook: r.facebook || '#', tiktok: r.tiktok || '#',
      igUser: r.instagramUsuario || '',
      waLink: function (text) { return 'https://wa.me/' + wa + '?text=' + encodeURIComponent(text); },
    };
  }

  // ── Vistas: contenido normalizado → estructuras que usa cada página ─────────
  var views = {
    home: function (c) {
      var hosts = dict(c.anfitriones.items, 'slug', function (h) { return h; });
      var cats = dict(c.diario.categorias, 'slug', 'label');
      var posts = c.diario.posts.slice().sort(function (a, b) { return a.date < b.date ? 1 : -1; }).slice(0, (c.inicio.diario || {}).cantidad || 3);
      var fmt = function (d) { var p = d.split('-'); return p[2] + ' ' + MON[+p[1] - 1] + ' ' + p[0]; };
      return {
        ALT: c.inicio.altitud.dias,
        HAB: c.inicio.altitud.habitual,
        STOPS: c.inicio.ruta.map(function (s) { return [s.name, s.alt, s.lon, s.lat]; }),
        journeys: c.journeys.items.map(function (j) {
          var i = j.inicio;
          return { id: j.slug, img: img(i.img, 1400, 70), srcset: srcset(i.img, [480, 720, 960, 1400], 70), c: j.c, title: j.title, region: i.region, days: j.days, alt: j.alt, price: i.price, icon: i.icon, ph: i.ph, why: i.why, moment: i.moment };
        }),
        FORM_LINKS: c.inicio.formas.map(function (f) { return [f.href, f.cta]; }),
        forms: c.inicio.formas.map(function (f) { return [f.title, f.sub, f.img]; }),
        hs: c.inicio.heroSlides.map(function (s) { return [s.img, s.caption]; }),
        principles: c.inicio.principios.map(function (p) { var o = omit(p, []); o.photo = img(p.photo, 800, 70); o.srcset = srcset(p.photo, [400, 800], 70); return o; }),
        hosts: c.inicio.anfitriones.map(function (slug) {
          var h = hosts[slug] || {};
          return { id: slug, img: img(h.img, 1400, 70), srcset: srcset(h.img, [480, 720, 1080, 1400], 70), role: h.role, place: h.place, teach: h.teach, ph: h.altInicio || h.alt };
        }),
        conditions: c.inicio.condiciones,
        phases: c.inicio.fases.map(function (p) { var o = omit(p, []); o.photo = img(p.photo, 800, 70); o.srcset = srcset(p.photo, [400, 800], 70); return o; }),
        words: c.inicio.palabras,
        steps: c.inicio.pasos,
        instagram: (c.inicio.instagram || []).map(function (p) { return { img: img(p.img, 700, 70, 700), srcset: srcset(p.img, [240, 480, 700], 70, 700), href: p.href || '#' }; }),
        blogCards: posts.map(function (p, i) {
          return { href: articulo(p.slug), img: img(p.img, 1000, 70), srcset: srcset(p.img, [480, 720, 1000], 70), title: p.title, excerpt: p.excerpt, cat: cats[p.cat] || p.cat, date: fmt(p.date), delay: String(i * 120) };
        }),
      };
    },

    journey: function (c) {
      var labels = dict(c.sitio.conceptos, 'slug', 'label');
      var J = {}, PLAN = {};
      c.journeys.items.forEach(function (j) {
        var f = j.ficha, o = omit(f, ['slides', 'momentImg']);
        o.title = j.title; o.c = j.c; o.days = j.days; o.alt = j.alt;
        o.concepts = j.c.map(function (k) { return labels[k]; });
        o.momentImg = img(f.momentImg, 2200, 72);
        o.momentSrcset = srcset(f.momentImg, [800, 1280, 1600, 2200], 72);
        o.slides = (f.slides || []).map(function (s) { return [s.img, s.caption]; });
        J[j.slug] = o;
        PLAN[j.slug] = j.plan;
      });
      return {
        PRINCIPLES: dict(c.journeys.principios, 'id', function (p) { return omit(p, ['id']); }),
        HOSTS: dict(c.anfitriones.items, 'slug', function (h) { return { role: h.role, place: h.place, teach: h.teach, img: h.img, alt: h.alt }; }),
        J: J,
        ORDER: c.journeys.items.map(function (j) { return j.slug; }),
        PLAN: PLAN,
        GAL: c.journeys.galeria.map(function (g) { return [g.img, g.ratio]; }),
      };
    },

    descubre: function (c) {
      var s = c.sitio;
      return {
        CL: dict(s.conceptos, 'slug', 'label'),
        CI: dict(s.conceptos, 'slug', 'icon'),
        DL: dict(s.destinos, 'slug', 'label'),
        RL: dict(s.duraciones, 'slug', 'label'),
        JOURNEYS: c.journeys.items.slice().sort(function (a, b) { return a.catalogo.orden - b.catalogo.orden; }).map(function (j) {
          var k = j.catalogo, o = { id: j.slug, title: j.title, days: j.days, alt: j.alt, c: j.c, img: k.imagenes, months: j.plan.season.months };
          Object.keys(k).forEach(function (key) { if (key !== 'orden' && key !== 'imagenes') o[key] = k[key]; });
          return o;
        }),
        EXPS: c.experiencias.items,
      };
    },

    recuerda: function (c) {
      var t = c.resenas.temas;
      return {
        TOPICS: dict(t, 'slug', 'label'),
        TOPIC_ONE: dict(t, 'slug', 'one'),
        TOPIC_ICON: dict(t, 'slug', 'icon'),
        PLAT: dict(c.resenas.plataformas, 'slug', 'label'),
        REVIEWS: c.resenas.items,
        PRODUCTS: c.productos.items,
        CATS: c.productos.categorias.map(function (x) { return [x.slug, x.label]; }),
      };
    },

    blog: function (c) {
      return {
        CATS: dict(c.diario.categorias, 'slug', 'label'),
        POSTS: c.diario.posts.map(function (p) {
          var o = omit(p, ['sections']);
          // En producción solo el artículo abierto trae su cuerpo (el resto se usa como tarjeta)
          o.sections = (p.sections || []).map(function (s) { var x = omit(s, ['blocks']); x.blocks = blocksOut(s.blocks); return x; });
          return o;
        }),
      };
    },

    terminos: function (c) {
      return { TERMS: c.legales.terminos.secciones.map(function (s) { var x = omit(s, ['blocks']); x.blocks = blocksOut(s.blocks); return x; }) };
    },

    reclamaciones: function (c) {
      return { CO: c.sitio.empresa };
    },
  };
  views.articulo = views.blog;

  // ── Formularios ───────────────────────────────────────────────────────────
  // tipo: 'solicitud' (Diseña tu viaje), 'resena' (Comparte tu viaje), 'reclamacion' (Libro de reclamaciones).
  //   · Con WordPress: POST {api}/sunqu/v1/formularios/{tipo}.
  //   · Vista previa sin WordPress: resuelve de inmediato ({ local: true }) y la página muestra su confirmación.
  //   · Producción sin WordPress: falla, para no mostrar un envío que no llegó a nadie.
  // Cada resultado se mide (form_submit + generate_lead / form_error) sin datos personales: solo el tipo de formulario.
  function enviar(tipo, datos) {
    var ctx = { form_name: tipo, experience_id: PAGE === 'journey' ? param('j') || undefined : undefined };
    var ok = function (r) { medir('form_submit', ctx); if (tipo === 'solicitud') medir('generate_lead', ctx); return r; };
    var ko = function (e) { medir('form_error', ctx); throw e; };
    if (!CFG.api) {
      if (CFG.env === 'production') return Promise.reject(new Error('Formularios sin configurar: falta WORDPRESS_API_URL')).catch(ko);
      return Promise.resolve({ local: true }).then(ok);
    }
    return verificacion().then(function (token) {
      var cuerpo = token ? Object.assign({}, datos || {}, { _ts: token }) : (datos || {});
      // ?lang=: los mensajes de error de WordPress llegan en el idioma de la página
      return fetch(String(CFG.api).replace(/\/$/, '') + '/sunqu/v1/formularios/' + tipo + (LANG !== 'es' ? '?lang=' + LANG : ''), {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cuerpo),
      });
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error((j && j.message) || 'Error ' + r.status);
        return j;
      });
    }).then(ok, ko);
  }

  // Verificación anti-bots opcional (Cloudflare Turnstile). Solo con TURNSTILE_SITE_KEY en el .env de la web
  // (y SUNQU_TURNSTILE_SECRET en WordPress): se carga al enviar, no antes, y es invisible salvo que Cloudflare
  // necesite que la persona confirme con un clic. Sin clave, los formularios funcionan como siempre.
  var ts = null;
  function verificacion() {
    if (!CFG.turnstile) return Promise.resolve('');
    return cargarScript('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit').then(function () {
      return new Promise(function (ok, ko) {
        var ts0 = window.turnstile;
        if (!ts0) { ko(new Error(t('No se pudo cargar la verificación. Vuelve a intentarlo.'))); return; }
        var fin = function (f, v) { if (ts.espera) { var e = ts.espera; ts.espera = null; e[f](v); } };
        if (!ts) {
          var caja = document.createElement('div');
          caja.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483000';
          document.body.appendChild(caja);
          ts = { caja: caja, espera: null };
          ts.id = ts0.render(caja, {
            sitekey: CFG.turnstile, appearance: 'interaction-only', execution: 'execute', language: { en: 'en', pt: 'pt-br', fr: 'fr', de: 'de', it: 'it', ru: 'ru' }[LANG] || 'es',
            callback: function (token) { fin(0, token); },
            'error-callback': function () { fin(1, new Error(t('No pudimos verificar el envío. Vuelve a intentarlo.'))); },
            'timeout-callback': function () { fin(1, new Error(t('La verificación tardó demasiado. Vuelve a intentarlo.'))); },
          });
        } else {
          ts0.reset(ts.id); // cada envío necesita un token nuevo
        }
        ts.espera = [ok, ko];
        ts0.execute(ts.caja);
      });
    });
  }
  // FormData de un <form> → objeto plano (los campos repetidos se juntan en una lista).
  // _t: milisegundos desde que se abrió la página (el plugin descarta envíos imposibles de tan rápidos).
  function datosDe(form) {
    var o = {};
    new FormData(form).forEach(function (v, k) {
      if (v instanceof File) return;
      if (k in o) o[k] = [].concat(o[k], v); else o[k] = v;
    });
    try { o._t = Math.round(performance.now()); } catch (e) {}
    return o;
  }

  // Fotos de un formulario → data URLs JPEG (máx. 1600 px) para enviarlas a WordPress. Sin WordPress no se procesan.
  function fotos(files) {
    if (!CFG.api || !files || !files.length) return Promise.resolve([]);
    return Promise.all(Array.prototype.slice.call(files, 0, 4).map(function (f) {
      return new Promise(function (ok) {
        var url = URL.createObjectURL(f), im = new Image();
        im.onload = function () {
          var k = Math.min(1, 1600 / Math.max(im.naturalWidth, im.naturalHeight)), cv = document.createElement('canvas');
          cv.width = Math.round(im.naturalWidth * k); cv.height = Math.round(im.naturalHeight * k);
          cv.getContext('2d').drawImage(im, 0, 0, cv.width, cv.height);
          URL.revokeObjectURL(url);
          ok({ nombre: f.name, data: cv.toDataURL('image/jpeg', 0.85) });
        };
        im.onerror = function () { URL.revokeObjectURL(url); ok(null); };
        im.src = url;
      });
    })).then(function (list) { return list.filter(Boolean); });
  }

  // ── Accesibilidad: el foco no sale de un diálogo abierto (menú, idioma, visor, bolsa, galería…) ──
  function dialogoAbierto() {
    var list = document.querySelectorAll('[aria-modal="true"]');
    for (var i = list.length - 1; i >= 0; i--) {
      var d = list[i], cs = window.getComputedStyle(d);
      if (cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.05 && d.getClientRects().length) return d;
    }
    return null;
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var d = dialogoAbierto();
    if (!d) return;
    var f = Array.prototype.filter.call(d.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'), function (el) {
      return el.getClientRects().length && window.getComputedStyle(el).visibility !== 'hidden';
    });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1], a = document.activeElement;
    if (!d.contains(a)) { e.preventDefault(); first.focus(); }
    else if (e.shiftKey && a === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && a === last) { e.preventDefault(); first.focus(); }
  }, true);

  window.SunquContent = {
    img: img, srcset: srcset, param: param, titulo: titulo, esc: esc, cargarScript: cargarScript, medir: medir, origen: origen,
    site: site, enviar: enviar, datosDe: datosDe, fotos: fotos, views: views, page: PAGE, base: BASE, config: CFG,
    t: t, idioma: idioma, idiomas: idiomas, irIdioma: irIdioma, prefijo: PREFIJO,
  };

  // El contenido de WordPress no se considera confiable: un valor que el navegador ejecutaría en un href o
  // un src («javascript:…», data: que no sea imagen) se reemplaza por «#». Lo mismo hace el servidor
  // (server/lib/content.php › sunqu_sanear) antes de precargarlo.
  var PELIGRO = /^(javascript|vbscript|livescript):/;
  function sanear(v) {
    if (Array.isArray(v)) return v.map(sanear);
    if (v && typeof v === 'object') {
      var o = {};
      Object.keys(v).forEach(function (k) { if (k !== '__proto__' && k !== 'constructor' && k !== 'prototype') o[k] = sanear(v[k]); });
      return o;
    }
    if (typeof v !== 'string') return v;
    var c = v.replace(/[\u0000- ]+/g, '').toLowerCase();
    if (PELIGRO.test(c)) return '#';
    if (v.trim().indexOf(' ') < 0 && c.indexOf('data:') === 0 && !/^data:image\/(png|jpe?g|gif|webp|avif)[;,]/.test(c)) return '#';
    return v;
  }

  // ── Arranque ──────────────────────────────────────────────────────────────
  function boot(content, origen) {
    content = sanear(content || {});
    window.SUNQU = content;
    window.SUNQU_ORIGEN = origen;
    Object.keys(content || {}).forEach(function (s) { if (content[s] && content[s]._imagenes) Object.assign(IMGSET, content[s]._imagenes); });
    try {
      window.SunquView = views[PAGE](content);
      window.SunquView.site = site(content);
    } catch (e) {
      console.error('[sunqu] el contenido no tiene el formato esperado para «' + PAGE + '»:', e);
      return;
    }
    var s = document.createElement('script');
    s.src = BASE + 'support.js' + (CFG.v ? '?v=' + CFG.v : '');
    document.head.appendChild(s);
  }
  function local() {
    return Promise.all(NEED[PAGE].map(function (n) {
      return fetch(BASE + 'content/' + n + '.json').then(function (r) {
        if (!r.ok) throw new Error('content/' + n + '.json: ' + r.status);
        return r.json();
      });
    })).then(function (list) {
      var c = {};
      NEED[PAGE].forEach(function (n, i) { c[n] = list[i]; });
      return c;
    });
  }
  function remote() {
    var url = String(CFG.api).replace(/\/$/, '') + '/sunqu/v1/contenido?secciones=' + NEED[PAGE].join(',');
    return fetch(url, { credentials: 'omit' }).then(function (r) {
      if (!r.ok) throw new Error('WordPress respondió ' + r.status);
      return r.json();
    });
  }

  if (window.SUNQU_PRELOADED) boot(window.SUNQU_PRELOADED, 'servidor');
  else if (CFG.api) {
    remote().then(function (c) { boot(c, 'wordpress'); }).catch(function (e) {
      console.warn('[sunqu] no se pudo leer WordPress, se usa content/*.json:', e);
      local().then(function (c) { boot(c, 'local'); });
    });
  } else {
    local().then(function (c) { boot(c, 'local'); }).catch(function (e) { console.error('[sunqu] no se pudo cargar el contenido:', e); });
  }
})();
