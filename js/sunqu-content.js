/*!
 * Sunqu · capa de contenido
 * ─────────────────────────────────────────────────────────────────────────────
 * Carga el contenido de la página y después arranca el runtime (support.js).
 *
 *   <script src="js/sunqu-config.js"></script>
 *   <script src="js/sunqu-content.js" data-page="journey"></script>
 *
 * Orden de origen del contenido:
 *   1. window.SUNQU_PRELOADED  → inyectado por el servidor (p. ej. un tema de WordPress).
 *   2. WordPress (REST)        → si window.SUNQU_CMS.api tiene valor: GET {api}/sunqu/v1/contenido?secciones=…
 *   3. content/*.json          → archivos locales (también es el respaldo si WordPress no responde).
 *
 * Expone:
 *   window.SUNQU            contenido normalizado (mismo formato que content/*.json y que la API).
 *   window.SunquView        datos ya adaptados a lo que usa cada página (J, JOURNEYS, POSTS…).
 *   window.SunquContent     { img, site, enviar, views } — utilidades.
 */
(function () {
  'use strict';

  var me = document.currentScript;
  var PAGE = (me && me.getAttribute('data-page')) || 'home';
  var BASE = me ? me.src.replace(/js\/sunqu-content\.js(\?.*)?$/, '') : '';
  var CFG = window.SUNQU_CMS || {};

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
  // Imagen: acepta una URL completa (WordPress), una ruta local (assets/…) o un id de Unsplash.
  function img(v, w, q, h) {
    if (!v) return '';
    if (/^(assets\/|blob:|data:|https?:|\/)/.test(v)) return v;
    return 'https://images.unsplash.com/photo-' + v + '?auto=format&fit=crop&w=' + w + (h ? '&h=' + h : '') + '&q=' + (q || 72);
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
  var MON = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

  // Datos de contacto y redes para el footer, el menú lateral y los botones de WhatsApp.
  function site(c) {
    var s = (c && c.sitio) || {}, k = s.contacto || {}, r = s.redes || {};
    return {
      email: k.email || '', phone: k.telefono || '', phoneHref: k.telefonoHref || '', office: k.oficina || '',
      wa: k.whatsapp || '', youtube: r.youtube || '#', instagram: r.instagram || '#', facebook: r.facebook || '#', tiktok: r.tiktok || '#',
      igUser: r.instagramUsuario || '',
      waLink: function (text) { return 'https://wa.me/' + (k.whatsapp || '') + '?text=' + encodeURIComponent(text); },
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
          return { id: j.slug, img: img(i.img, 1400, 70), c: j.c, title: j.title, region: i.region, days: j.days, alt: j.alt, price: i.price, icon: i.icon, ph: i.ph, why: i.why, moment: i.moment };
        }),
        FORM_LINKS: c.inicio.formas.map(function (f) { return [f.href, f.cta]; }),
        forms: c.inicio.formas.map(function (f) { return [f.title, f.sub, f.img]; }),
        hs: c.inicio.heroSlides.map(function (s) { return [s.img, s.caption]; }),
        principles: c.inicio.principios.map(function (p) { var o = omit(p, []); o.photo = img(p.photo, 800, 70); return o; }),
        hosts: c.inicio.anfitriones.map(function (slug) {
          var h = hosts[slug] || {};
          return { id: slug, img: img(h.img, 1400, 70), role: h.role, place: h.place, teach: h.teach, ph: h.altInicio || h.alt };
        }),
        conditions: c.inicio.condiciones,
        phases: c.inicio.fases.map(function (p) { var o = omit(p, []); o.photo = img(p.photo, 800, 70); return o; }),
        words: c.inicio.palabras,
        steps: c.inicio.pasos,
        instagram: (c.inicio.instagram || []).map(function (p) { return { img: img(p.img, 700, 70, 700), href: p.href || '#' }; }),
        blogCards: posts.map(function (p, i) {
          return { href: 'articulo.html?a=' + p.slug, img: img(p.img, 1000, 70), title: p.title, excerpt: p.excerpt, cat: cats[p.cat] || p.cat, date: fmt(p.date), delay: String(i * 120) };
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
          o.sections = p.sections.map(function (s) { var x = omit(s, ['blocks']); x.blocks = blocksOut(s.blocks); return x; });
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
  // Sin WordPress configurado resuelve de inmediato ({ local: true }) y la página muestra su confirmación.
  function enviar(tipo, datos) {
    if (!CFG.api) return Promise.resolve({ local: true });
    return fetch(CFG.api.replace(/\/$/, '') + '/sunqu/v1/formularios/' + tipo, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos || {}),
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error((j && j.message) || 'Error ' + r.status);
        return j;
      });
    });
  }
  // FormData de un <form> → objeto plano (los campos repetidos se juntan en una lista).
  function datosDe(form) {
    var o = {};
    new FormData(form).forEach(function (v, k) {
      if (v instanceof File) return;
      if (k in o) o[k] = [].concat(o[k], v); else o[k] = v;
    });
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

  window.SunquContent = { img: img, site: site, enviar: enviar, datosDe: datosDe, fotos: fotos, views: views, page: PAGE };

  // ── Arranque ──────────────────────────────────────────────────────────────
  function boot(content, origen) {
    window.SUNQU = content;
    window.SUNQU_ORIGEN = origen;
    try {
      window.SunquView = views[PAGE](content);
      window.SunquView.site = site(content);
    } catch (e) {
      console.error('[sunqu] el contenido no tiene el formato esperado para «' + PAGE + '»:', e);
      return;
    }
    var s = document.createElement('script');
    s.src = BASE + 'support.js';
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
    var url = CFG.api.replace(/\/$/, '') + '/sunqu/v1/contenido?secciones=' + NEED[PAGE].join(',');
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
