/*!
 * Sunqu · campos de los formularios
 * ─────────────────────────────────────────────────────────────────────────────
 * Tres campos que las plantillas marcan con un contenedor vacío y este archivo completa (en cualquier idioma):
 *
 *   <div data-sunqu-campo="pais" data-id="f-country" data-required="1"></div>
 *       País: lista con buscador. Envía pais (código ISO, «PE») y pais_nombre (como lo vio la persona).
 *   <div data-sunqu-campo="celular" data-id="f-tel" data-required="1"></div>
 *       Celular con prefijo (bandera + código, con buscador). Envía celular, celular_prefijo («+51») y celular_pais.
 *   <div data-sunqu-campo="grupo" data-id="f-group"></div>
 *       Mi grupo: habitaciones con adultos (11+), niños (3–10) y bebés (0–2). Envía grupo: [[2,0,0],[1,1,0]].
 *
 * El <label for="…"> de la plantilla apunta al data-id. Los nombres de los países salen del navegador en el idioma
 * de la página (Intl.DisplayNames); las banderas son emoji con la fuente TwemojiCountryFlags (vendor/), para que
 * también se vean en Windows. Los textos pasan por SunquContent.t (Loco Translate).
 */
(function () {
  'use strict';

  var SC = window.SunquContent || {};
  var t = SC.t || function (s) { return s; };
  var LANG = (SC.idioma && SC.idioma()) || 'es';
  var BASE = SC.base || '';

  // ── Países: código ISO 3166-1 + prefijo telefónico (E.164). Los del Plan de Numeración de Norteamérica van con +1.
  var DATOS = ('AD376 AE971 AF93 AG1 AI1 AL355 AM374 AO244 AR54 AS1 AT43 AU61 AW297 AX358 AZ994 BA387 BB1 BD880 BE32 '
    + 'BF226 BG359 BH973 BI257 BJ229 BL590 BM1 BN673 BO591 BQ599 BR55 BS1 BT975 BW267 BY375 BZ501 CA1 CC61 CD243 CF236 '
    + 'CG242 CH41 CI225 CK682 CL56 CM237 CN86 CO57 CR506 CU53 CV238 CW599 CX61 CY357 CZ420 DE49 DJ253 DK45 DM1 DO1 DZ213 '
    + 'EC593 EE372 EG20 EH212 ER291 ES34 ET251 FI358 FJ679 FK500 FM691 FO298 FR33 GA241 GB44 GD1 GE995 GF594 GG44 GH233 '
    + 'GI350 GL299 GM220 GN224 GP590 GQ240 GR30 GT502 GU1 GW245 GY592 HK852 HN504 HR385 HT509 HU36 ID62 IE353 IL972 IM44 '
    + 'IN91 IO246 IQ964 IR98 IS354 IT39 JE44 JM1 JO962 JP81 KE254 KG996 KH855 KI686 KM269 KN1 KP850 KR82 KW965 KY1 KZ7 '
    + 'LA856 LB961 LC1 LI423 LK94 LR231 LS266 LT370 LU352 LV371 LY218 MA212 MC377 MD373 ME382 MF590 MG261 MH692 MK389 '
    + 'ML223 MM95 MN976 MO853 MP1 MQ596 MR222 MS1 MT356 MU230 MV960 MW265 MX52 MY60 MZ258 NA264 NC687 NE227 NF672 NG234 '
    + 'NI505 NL31 NO47 NP977 NR674 NU683 NZ64 OM968 PA507 PE51 PF689 PG675 PH63 PK92 PL48 PM508 PN64 PR1 PS970 PT351 '
    + 'PW680 PY595 QA974 RE262 RO40 RS381 RU7 RW250 SA966 SB677 SC248 SD249 SE46 SG65 SH290 SI386 SJ47 SK421 SL232 SM378 '
    + 'SN221 SO252 SR597 SS211 ST239 SV503 SX1 SY963 SZ268 TC1 TD235 TG228 TH66 TJ992 TK690 TL670 TM993 TN216 TO676 TR90 '
    + 'TT1 TV688 TW886 TZ255 UA380 UG256 US1 UY598 UZ998 VA39 VC1 VE58 VG1 VI1 VN84 VU678 WF681 WS685 XK383 YE967 YT262 '
    + 'ZA27 ZM260 ZW263').split(' ').map(function (x) { return { code: x.slice(0, 2), dial: '+' + x.slice(2) }; });
  // Si varios países comparten prefijo, el que se elige al escribir «+1…» en el número
  var PRINCIPAL = { '+1': 'US', '+7': 'RU', '+44': 'GB', '+39': 'IT', '+47': 'NO', '+61': 'AU', '+262': 'RE', '+590': 'GP', '+599': 'CW', '+212': 'MA', '+358': 'FI', '+64': 'NZ', '+672': 'NF' };
  var POR_IDIOMA = { es: 'PE', en: 'US', pt: 'BR', fr: 'FR', de: 'DE', it: 'IT', ru: 'RU' };

  function nombresEn(lang) {
    try { if (typeof Intl.DisplayNames === 'function') { var dn = new Intl.DisplayNames([lang], { type: 'region' }); return function (c) { try { return dn.of(c) || c; } catch (e) { return c; } }; } } catch (e) {}
    return function (c) { return c; };
  }
  var nombre = nombresEn(LANG), nombreEn = nombresEn('en');
  function bandera(c) { return String.fromCodePoint(0x1F1E6 + c.charCodeAt(0) - 65, 0x1F1E6 + c.charCodeAt(1) - 65); }
  function normal(s) { return String(s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }
  var PAISES = DATOS.map(function (p) {
    var n = nombre(p.code);
    return { code: p.code, dial: p.dial, name: n, flag: bandera(p.code), buscar: normal(n + ' ' + nombreEn(p.code) + ' ' + p.code) };
  }).sort(function (a, b) { return a.name.localeCompare(b.name, LANG); });
  var POR_CODIGO = {};
  PAISES.forEach(function (p) { POR_CODIGO[p.code] = p; });

  // País probable de la persona, solo para el prefijo por defecto: la región de su navegador (es-PE → PE) o la del idioma
  function paisProbable() {
    var langs = (navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || '']);
    for (var i = 0; i < langs.length; i++) {
      var m = String(langs[i]).match(/-([A-Za-z]{2})\b/);
      if (m && POR_CODIGO[m[1].toUpperCase()]) return m[1].toUpperCase();
    }
    return POR_IDIOMA[LANG] || 'PE';
  }
  function fmt(s, v) { return String(s).replace(/\{(\w+)\}/g, function (_, k) { return v[k] != null ? v[k] : ''; }); }
  function el(tag, attrs, html) {
    var e = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) { if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) e.setAttribute(k, attrs[k] === true ? '' : attrs[k]); });
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  var CHEVRON = '<svg class="sq-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

  // ── Lista con buscador (role=listbox) que se maneja desde un campo de texto (role=combobox) ──────────────
  // Cada lista tiene sus propias filas: los países (items) se comparten entre los campos de la página.
  function Lista(input, items, opciones) {
    var lista = el('ul', { id: input.id + '-lista', class: 'sq-lista', role: 'listbox', hidden: true, 'aria-label': opciones.titulo || null });
    var vacio = el('li', { class: 'sq-vacio', role: 'presentation', hidden: true }, esc(t('No encontramos ese país.')));
    var activo = -1, visibles = [];
    var filas = items.map(function (it, i) {
      var li = el('li', { id: input.id + '-' + it.code, class: 'sq-op', role: 'option', 'aria-selected': 'false', 'data-i': i },
        '<span class="sq-flag" aria-hidden="true">' + it.flag + '</span><span class="sq-op-n">' + esc(it.name) + '</span>' + (opciones.dial ? '<small>' + it.dial + '</small>' : ''));
      lista.appendChild(li);
      return { it: it, li: li };
    });
    lista.appendChild(vacio);
    input.setAttribute('aria-controls', lista.id);
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    function marcar(i) {
      if (activo >= 0 && visibles[activo]) visibles[activo].li.classList.remove('is-act');
      activo = i;
      if (activo >= 0 && visibles[activo]) {
        var li = visibles[activo].li;
        li.classList.add('is-act');
        input.setAttribute('aria-activedescendant', li.id);
        var top = li.offsetTop, bottom = top + li.offsetHeight;
        if (top < lista.scrollTop) lista.scrollTop = top; else if (bottom > lista.scrollTop + lista.clientHeight) lista.scrollTop = bottom - lista.clientHeight;
      } else {
        input.removeAttribute('aria-activedescendant');
      }
    }
    function filtrar(q) {
      var n = normal(q).replace(/^\+/, ''), digitos = /^\d+$/.test(n);
      visibles = [];
      filas.forEach(function (f) {
        var ok = !n || (digitos && opciones.dial ? f.it.dial.slice(1).indexOf(n) === 0 : f.it.buscar.indexOf(n) >= 0);
        f.li.hidden = !ok;
        if (ok) visibles.push(f);
      });
      vacio.hidden = visibles.length > 0;
      marcar(visibles.length ? 0 : -1);
    }
    var api = {
      lista: lista,
      abierta: function () { return !lista.hidden; },
      abrir: function () {
        if (!lista.hidden) return;
        lista.hidden = false; input.setAttribute('aria-expanded', 'true');
        filtrar(opciones.filtroInicial ? opciones.filtroInicial() : '');
        var sel = visibles.filter(function (f) { return f.li.getAttribute('aria-selected') === 'true'; })[0];
        if (sel) marcar(visibles.indexOf(sel));
      },
      cerrar: function () { lista.hidden = true; input.setAttribute('aria-expanded', 'false'); marcar(-1); },
      filtrar: filtrar,
      seleccionar: function (code) { filas.forEach(function (f) { f.li.setAttribute('aria-selected', f.it.code === code ? 'true' : 'false'); }); },
      tecla: function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          if (!api.abierta()) { api.abrir(); return; }
          if (visibles.length) marcar((activo + (e.key === 'ArrowDown' ? 1 : -1) + visibles.length) % visibles.length);
        } else if (e.key === 'Enter') {
          if (api.abierta() && visibles[activo]) { e.preventDefault(); opciones.elegir(visibles[activo].it); }
        } else if (e.key === 'Escape') {
          if (api.abierta()) { e.preventDefault(); e.stopPropagation(); api.cerrar(); if (opciones.alCerrar) opciones.alCerrar(true); }
        } else if (e.key === 'Tab') {
          if (api.abierta() && visibles[activo] && opciones.tabElige) opciones.elegir(visibles[activo].it);
          api.cerrar();
        }
      },
    };
    lista.addEventListener('mousedown', function (e) { e.preventDefault(); }); // el foco se queda en el campo
    lista.addEventListener('click', function (e) {
      var li = e.target.closest('.sq-op');
      if (li) opciones.elegir(filas[+li.getAttribute('data-i')].it);
    });
    return api;
  }

  // ── País ────────────────────────────────────────────────────────────────────────────────────────
  function montarPais(caja) {
    var id = caja.getAttribute('data-id') || 'sq-pais';
    var flag = el('span', { class: 'sq-flag sq-combo-flag', 'aria-hidden': 'true' });
    var input = el('input', { id: id, type: 'text', class: 'input sq-combo-in', role: 'combobox', autocomplete: 'off', spellcheck: 'false', placeholder: caja.getAttribute('data-placeholder') || t('Busca tu país'), required: caja.getAttribute('data-required') === '1' });
    var codigo = el('input', { type: 'hidden', name: caja.getAttribute('data-name') || 'pais' });
    var nombreH = el('input', { type: 'hidden', name: (caja.getAttribute('data-name') || 'pais') + '_nombre' });
    var elegido = null;
    function validar() {
      input.setCustomValidity(input.value.trim() && !elegido ? t('Elige un país de la lista.') : '');
    }
    function elegir(p, silencio) {
      elegido = p;
      input.value = p ? p.name : '';
      codigo.value = p ? p.code : '';
      nombreH.value = p ? p.name : '';
      flag.textContent = p ? p.flag : '';
      caja.classList.toggle('con-bandera', !!p);
      lista.seleccionar(p ? p.code : '');
      lista.cerrar();
      validar();
      if (!silencio && p) caja.dispatchEvent(new CustomEvent('sunqu:pais', { bubbles: true, detail: { code: p.code } }));
    }
    var lista = Lista(input, PAISES, { elegir: function (p) { elegir(p); }, tabElige: true, titulo: t('Países'), filtroInicial: function () { return elegido ? '' : input.value; } });
    // La lista se abre al hacer clic, al escribir o con la flecha abajo (no al llegar con Tab: taparía los campos siguientes)
    input.addEventListener('focus', function () { if (elegido) input.select(); });
    input.addEventListener('click', function () { lista.abrir(); });
    input.addEventListener('input', function () {
      elegido = null; codigo.value = ''; nombreH.value = ''; flag.textContent = ''; caja.classList.remove('con-bandera');
      lista.abrir(); lista.filtrar(input.value); validar();
    });
    input.addEventListener('keydown', lista.tecla);
    input.addEventListener('blur', function () {
      // Escrito completo sin elegir de la lista: si coincide con un país, cuenta como elegido
      if (!elegido && input.value.trim()) {
        var n = normal(input.value), igual = PAISES.filter(function (p) { return normal(p.name) === n || normal(nombreEn(p.code)) === n; })[0];
        if (igual) elegir(igual);
      }
      lista.cerrar(); validar();
    });
    caja.classList.add('sq-combo');
    caja.appendChild(flag); caja.appendChild(input); caja.appendChild(lista.lista); caja.appendChild(codigo); caja.appendChild(nombreH);
    var inicial = POR_CODIGO[String(caja.getAttribute('data-valor') || '').toUpperCase()];
    if (inicial) elegir(inicial, true);
  }

  // ── Celular con prefijo ──────────────────────────────────────────────────────────────────────────
  function montarCelular(caja) {
    var id = caja.getAttribute('data-id') || 'sq-tel';
    var actual = POR_CODIGO[paisProbable()] || POR_CODIGO.PE, manual = false;
    var boton = el('button', { type: 'button', class: 'input sq-tel-pref', 'aria-haspopup': 'listbox', 'aria-expanded': 'false', 'aria-controls': id + '-pop' });
    var numero = el('input', { id: id, type: 'tel', name: caja.getAttribute('data-name') || 'celular', class: 'input sq-tel-num', inputmode: 'tel', autocomplete: 'tel-national', placeholder: caja.getAttribute('data-placeholder') || '987 654 321', required: caja.getAttribute('data-required') === '1', maxlength: '24' });
    var pop = el('div', { id: id + '-pop', class: 'sq-pop', hidden: true });
    var buscar = el('input', { id: id + '-buscar', type: 'text', class: 'input sq-pop-q', role: 'combobox', autocomplete: 'off', spellcheck: 'false', placeholder: t('Busca un país o un código'), 'aria-label': t('Prefijo del país') });
    var prefijo = el('input', { type: 'hidden', name: (caja.getAttribute('data-name') || 'celular') + '_prefijo' });
    var paisH = el('input', { type: 'hidden', name: (caja.getAttribute('data-name') || 'celular') + '_pais' });
    function pintar() {
      boton.innerHTML = '<span class="sq-flag" aria-hidden="true">' + actual.flag + '</span><span class="sq-tel-code">' + actual.dial + '</span>' + CHEVRON;
      boton.setAttribute('aria-label', t('Prefijo del país') + ': ' + actual.name + ' ' + actual.dial);
      prefijo.value = actual.dial; paisH.value = actual.code;
      lista.seleccionar(actual.code);
    }
    function abrir() { pop.hidden = false; boton.setAttribute('aria-expanded', 'true'); buscar.value = ''; lista.abrir(); buscar.focus(); }
    function cerrar(volver) { pop.hidden = true; boton.setAttribute('aria-expanded', 'false'); lista.cerrar(); if (volver) boton.focus(); }
    var lista = Lista(buscar, PAISES, { dial: true, titulo: t('Prefijo del país'), elegir: function (p) { actual = p; manual = true; pintar(); cerrar(true); validar(); }, alCerrar: function () { cerrar(true); } });
    function validar() {
      var d = numero.value.replace(/\D/g, '');
      numero.setCustomValidity(numero.value.trim() && (d.length < 6 || d.length > 15) ? t('Escribe un número de celular válido.') : '');
    }
    // «+51 987…» o «0051 987…» escrito en el número: el prefijo pasa al selector
    function separarPrefijo() {
      var v = numero.value.trim(), m = v.match(/^(?:\+|00)\s*(\d[\d\s]*)/);
      if (!m) return;
      var d = m[1].replace(/\s/g, '');
      for (var n = 4; n >= 1; n--) {
        var dial = '+' + d.slice(0, n), candidatos = PAISES.filter(function (p) { return p.dial === dial; });
        if (candidatos.length) {
          actual = candidatos.filter(function (p) { return p.code === actual.code; })[0] || POR_CODIGO[PRINCIPAL[dial]] || candidatos[0];
          manual = true; pintar();
          numero.value = v.replace(/^(?:\+|00)\s*/, '').replace(new RegExp('^' + d.slice(0, n).split('').join('\\s*')), '').trim();
          return;
        }
      }
    }
    boton.addEventListener('click', function () { if (pop.hidden) abrir(); else cerrar(true); });
    buscar.addEventListener('input', function () { lista.filtrar(buscar.value); });
    buscar.addEventListener('keydown', function (e) { if (e.key === 'Tab') { cerrar(false); return; } lista.tecla(e); });
    numero.addEventListener('input', validar);
    numero.addEventListener('blur', function () { separarPrefijo(); validar(); });
    document.addEventListener('mousedown', function (e) { if (!pop.hidden && !caja.contains(e.target)) cerrar(false); });
    // Si la persona elige su país en el mismo formulario y no tocó el prefijo, el prefijo lo sigue
    var form = caja.closest('form');
    if (form) form.addEventListener('sunqu:pais', function (e) { var p = POR_CODIGO[e.detail && e.detail.code]; if (p && !manual) { actual = p; pintar(); } });
    pop.appendChild(buscar); pop.appendChild(lista.lista);
    caja.classList.add('sq-tel');
    caja.appendChild(boton); caja.appendChild(numero); caja.appendChild(pop); caja.appendChild(prefijo); caja.appendChild(paisH);
    pintar();
  }

  // ── Mi grupo: habitaciones y huéspedes ──────────────────────────────────────────────────────────────
  var TIPOS = [
    { titulo: t('Adultos'), edad: t('11 años o más'), min: 1, max: 8 },
    { titulo: t('Niños'), edad: t('De 3 a 10 años'), min: 0, max: 6 },
    { titulo: t('Bebés'), edad: t('De 0 a 2 años'), min: 0, max: 4 },
  ];
  var MAX_HAB = 6;
  function montarGrupo(caja) {
    var id = caja.getAttribute('data-id') || 'sq-grupo';
    var hab = [[2, 0, 0]];
    var boton = el('button', { id: id, type: 'button', class: 'input sq-grupo-btn', 'aria-expanded': 'false', 'aria-controls': id + '-panel' });
    var panel = el('div', { id: id + '-panel', class: 'sq-grupo-panel', role: 'group', 'aria-label': t('Mi grupo'), hidden: true });
    var oculto = el('input', { type: 'hidden', name: caja.getAttribute('data-name') || 'grupo' });
    function resumen() {
      var h = hab.length, g = hab.reduce(function (s, r) { return s + r[0] + r[1] + r[2]; }, 0);
      return {
        habitaciones: fmt(h === 1 ? t('1 habitación') : t('{n} habitaciones'), { n: h }),
        huespedes: fmt(g === 1 ? t('1 huésped') : t('{n} huéspedes'), { n: g }),
      };
    }
    // Totales, botón y campo oculto (sin redibujar: los lectores de pantalla anuncian el número que cambió)
    function actualizar() {
      var r = resumen();
      boton.innerHTML = '<span class="sq-grupo-res">' + esc(r.habitaciones + ' · ' + r.huespedes) + '</span>' + CHEVRON;
      oculto.value = JSON.stringify(hab);
      Array.prototype.forEach.call(panel.querySelectorAll('.sq-cont'), function (c) {
        var i = +c.getAttribute('data-h'), j = +c.getAttribute('data-j'), tp = TIPOS[j];
        c.querySelector('.sq-num').textContent = hab[i][j];
        c.querySelector('[data-d="-1"]').disabled = hab[i][j] <= tp.min;
        c.querySelector('[data-d="1"]').disabled = hab[i][j] >= tp.max;
      });
      var listo = panel.querySelector('[data-listo]');
      if (listo) listo.textContent = fmt(t('Necesito {habitaciones}, {huespedes}'), r);
    }
    // Estructura: una caja por habitación (al agregar o quitar una)
    function pintar(foco) {
      var html = '';
      hab.forEach(function (fila, i) {
        var n = i + 1;
        html += '<div class="sq-hab"><p class="sq-hab-t">' + esc(fmt(t('Habitación {n}'), { n: n })) + '</p>';
        TIPOS.forEach(function (tp, j) {
          var cat = tp.titulo.toLowerCase();
          html += '<div class="sq-fila"><div><span class="sq-fila-t">' + esc(tp.titulo) + '</span><span class="sq-fila-s">' + esc(tp.edad) + '</span></div>'
            + '<div class="sq-cont" data-h="' + i + '" data-j="' + j + '">'
            + '<button type="button" data-d="-1" aria-label="' + esc(fmt(t('Menos {categoria} en la habitación {n}'), { categoria: cat, n: n })) + '">−</button>'
            + '<span class="sq-num" aria-live="polite"></span>'
            + '<button type="button" data-d="1" aria-label="' + esc(fmt(t('Más {categoria} en la habitación {n}'), { categoria: cat, n: n })) + '">+</button></div></div>';
        });
        if (hab.length > 1) html += '<button type="button" class="sq-link sq-hab-quitar" data-quitar="' + i + '">' + esc(t('Quitar habitación')) + '</button>';
        html += '</div>';
      });
      if (hab.length < MAX_HAB) html += '<button type="button" class="sq-link sq-hab-agregar" data-agregar="1">+ ' + esc(t('Agregar habitación')) + '</button>';
      html += '<button type="button" class="btn sq-grupo-listo" data-listo="1"></button>';
      panel.innerHTML = html;
      actualizar();
      if (foco) { var f = panel.querySelector(foco); (f && !f.disabled ? f : panel.querySelector('[data-listo]')).focus(); }
    }
    function abrir(v) { panel.hidden = !v; boton.setAttribute('aria-expanded', v ? 'true' : 'false'); }
    boton.addEventListener('click', function () { abrir(panel.hidden); });
    panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); abrir(false); boton.focus(); } });
    panel.addEventListener('click', function (e) {
      var b = e.target.closest('button');
      if (!b) return;
      if (b.hasAttribute('data-listo')) { abrir(false); boton.focus(); return; }
      if (b.hasAttribute('data-agregar')) { hab.push([1, 0, 0]); pintar('.sq-hab:nth-last-of-type(1) [data-d="1"]'); return; }
      if (b.hasAttribute('data-quitar')) { hab.splice(+b.getAttribute('data-quitar'), 1); pintar('[data-agregar]'); return; }
      var c = b.closest('.sq-cont');
      if (!c) return;
      var i = +c.getAttribute('data-h'), j = +c.getAttribute('data-j'), tp = TIPOS[j];
      hab[i][j] = Math.max(tp.min, Math.min(tp.max, hab[i][j] + (+b.getAttribute('data-d'))));
      actualizar();
      if (b.disabled) (b.getAttribute('data-d') === '1' ? c.querySelector('[data-d="-1"]') : c.querySelector('[data-d="1"]')).focus();
    });
    caja.classList.add('sq-grupo');
    caja.appendChild(boton); caja.appendChild(panel); caja.appendChild(oculto);
    pintar();
  }

  // ── Estilos (los mismos tonos que los formularios de la web) ─────────────────────────────────────
  var css = "@font-face{font-family:'Sunqu Banderas';src:url('" + BASE + "vendor/TwemojiCountryFlags.woff2') format('woff2');unicode-range:U+1F1E6-1F1FF,U+1F3F4,U+E0062-E0063,U+E0065,U+E0067,U+E006C,U+E006E,U+E0073-E0074,U+E0077,U+E007F;font-display:swap}"
    + ".sq-flag{font-family:'Sunqu Banderas','Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif;font-size:19px;line-height:1}"
    + ".sq-combo,.sq-tel,.sq-grupo{position:relative;min-width:0}"
    + ".sq-combo-flag{position:absolute;left:16px;top:16px;pointer-events:none}.sq-combo.con-bandera .sq-combo-in{padding-left:48px}"
    + ".sq-lista{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 6px);max-height:280px;overflow:auto;margin:0;padding:6px;list-style:none;background:#fff;border:1px solid rgba(28,28,28,.14);border-radius:14px;box-shadow:0 18px 40px rgba(28,28,28,.16)}"
    + ".sq-op{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:10px;font-size:15px;line-height:1.3;color:#1c1c1c;cursor:pointer}.sq-op small{margin-left:auto;color:#626265;font-size:14px}"
    + ".sq-op[aria-selected=true]{font-weight:600}.sq-op.is-act,.sq-op:hover{background:rgba(115,109,71,.12)}.sq-vacio{padding:10px 12px;font-size:14px;color:#626265}"
    + ".sq-tel{display:flex;gap:8px}.sq-tel-pref{flex:0 0 auto;width:auto;display:inline-flex;align-items:center;gap:8px;padding:0 12px 0 14px;cursor:pointer;text-align:left}"
    + ".sq-tel-code{font-size:16px;color:#1c1c1c}.sq-tel-num{flex:1 1 auto;min-width:0}.sq-chev{flex:0 0 auto;color:#736d47}"
    + ".sq-pop{position:absolute;z-index:40;left:0;top:calc(100% + 6px);width:min(340px,100%);padding:8px;background:#fff;border:1px solid rgba(28,28,28,.14);border-radius:14px;box-shadow:0 18px 40px rgba(28,28,28,.16)}"
    + ".sq-pop .sq-pop-q{min-height:44px;padding:10px 12px;font-size:15px}.sq-pop .sq-lista{position:static;max-height:240px;padding:6px 0 0;border:0;box-shadow:none}"
    + ".sq-grupo-btn{display:flex;align-items:center;justify-content:space-between;gap:12px;text-align:left;cursor:pointer;color:#1c1c1c}"
    + ".sq-grupo-panel{margin-top:10px;padding:18px clamp(14px,3vw,22px);background:#fff;border:1px solid rgba(28,28,28,.14);border-radius:16px}"
    + ".sq-hab{padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid rgba(28,28,28,.1)}.sq-hab-t{margin:0 0 4px;font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#736d47}"
    + ".sq-fila{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0}.sq-fila-t{display:block;font-size:16px;color:#1c1c1c}.sq-fila-s{display:block;font-size:13px;color:#626265}"
    + ".sq-cont{display:flex;align-items:center;gap:12px;flex:0 0 auto}.sq-cont button{width:40px;height:40px;padding:0;border-radius:50%;border:1px solid rgba(28,28,28,.35);background:transparent;font:inherit;font-size:20px;line-height:1;color:#1c1c1c;cursor:pointer}"
    + ".sq-cont button:hover:not(:disabled){border-color:#736d47;color:#736d47}.sq-cont button:disabled{opacity:.35;cursor:not-allowed}.sq-num{min-width:22px;text-align:center;font-weight:600;font-size:16px}"
    + ".sq-link{display:inline-block;padding:6px 0;background:none;border:0;font:inherit;font-size:14px;color:#736d47;text-decoration:underline;text-underline-offset:3px;cursor:pointer}.sq-hab-quitar{color:#a2412b}"
    + ".sq-grupo-listo{display:flex;width:100%;justify-content:center;margin-top:14px}"
    + ".sq-cont button:focus-visible,.sq-link:focus-visible,.sq-op:focus-visible{outline:2px solid #736d47;outline-offset:2px}"
    + ".sq-lista[hidden],.sq-pop[hidden],.sq-grupo-panel[hidden],.sq-op[hidden],.sq-vacio[hidden]{display:none!important}";
  var st = document.createElement('style');
  st.textContent = css;
  document.head.appendChild(st);

  // ── Montaje: las plantillas se dibujan después de cargar el contenido (y vuelven a dibujar el formulario) ──
  // Nunca dentro de <x-dc>: es la plantilla cruda que el runtime (support.js) lee para compilar la página; si el
  // campo se armara ahí, React copiaría ese HTML sin sus eventos. Se arma en la página ya dibujada.
  var MONTAR = { pais: montarPais, celular: montarCelular, grupo: montarGrupo };
  function montar(raiz) {
    Array.prototype.forEach.call((raiz || document).querySelectorAll('[data-sunqu-campo]:not([data-montado])'), function (caja) {
      var f = MONTAR[caja.getAttribute('data-sunqu-campo')];
      if (!f || caja.closest('x-dc')) return;
      caja.setAttribute('data-montado', '1');
      try { f(caja); } catch (e) { console.error('[sunqu] no se pudo preparar el campo', caja.getAttribute('data-sunqu-campo'), e); }
    });
  }
  montar();
  new MutationObserver(function () { montar(); }).observe(document.documentElement, { childList: true, subtree: true });
  window.SunquCampos = { paises: PAISES, montar: montar };
})();
