#!/usr/bin/env python3
"""
Genera la web en otros idiomas para la versión estática (GitHub Pages): /en/, /fr/, /de/, /it/ y /ru/.

Las páginas en español de la raíz son la fuente. Para cada idioma, este script:
  · traduce las 9 plantillas con i18n/<idioma>.json ({texto en español: traducción}), con las mismas reglas que el
    servidor (server/lib/idiomas.php): texto entre etiquetas, atributos alt/aria-label/placeholder/title y frases
    entre comillas del código de la plantilla. También el <title> y la descripción;
  · escribe <idioma>/<página>.html con las rutas de estilos, scripts e imágenes desde la raíz (/css/…, /assets/…);
  · traduce el contenido (content/*.json → content/<idioma>/*.json). Los términos legales y las reseñas quedan en
    español (la versión legal es la española; las reseñas son palabras de cada viajero);
  · define window.SUNQU_I18N_DIC con los textos de js/ que usa t() (js/sunqu-idiomas.js arma el resto).
Además actualiza en todas las páginas (también las de la raíz) el bloque <!-- sunqu:seo --> con la descripción y los
hreflang, y escribe sitemap.xml y robots.txt.

Uso:
  python3 tools/idiomas-estaticos.py --fuente      escribe i18n/_fuente.json: todos los textos a traducir
  python3 tools/idiomas-estaticos.py               genera los idiomas (avisa de lo que falta traducir)
Lo que no tiene traducción se queda en español.
"""
import html
import importlib.util
import json
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
DOMINIO = 'https://sunqujourneys.com'
IDIOMAS = ['en', 'fr', 'de', 'it', 'ru']
LOCALES = {'es': 'es_PE', 'en': 'en_US', 'fr': 'fr_FR', 'de': 'de_DE', 'it': 'it_IT', 'ru': 'ru_RU'}
PLANTILLAS = ['index.html', 'journey.html', 'experiencia.html', 'descubre.html', 'recuerda.html', 'blog.html', 'articulo.html', 'terminos.html', 'reclamaciones.html']
# Páginas fijas: descripción (Sunqu › Sitio › SEO) y hreflang. Las de journey, experiencia y artículo dependen del parámetro.
SEO = {'index.html': 'inicio', 'descubre.html': 'descubre', 'recuerda.html': 'recuerda', 'blog.html': 'diario', 'terminos.html': 'terminos', 'reclamaciones.html': 'reclamaciones'}
JS = ['js/sunqu-content.js', 'js/sunqu-analytics.js', 'js/sunqu-campos.js']
CONTENIDO = ['sitio', 'inicio', 'journeys', 'anfitriones', 'experiencias', 'diario', 'productos', 'resenas', 'legales']
# Claves que nunca se traducen: identificadores, rutas, colores, etapas (data-s del CSS) y datos de contacto
NO_TRADUCIR = {'_info', 'slug', 'id', 'img', 'href', 'icon', 'photo', 'bg', 'fg', 'color', 'video', 'poster', 'url', 'fuente', 'card',
               'momentImg', 'dest', 'c', 'cat', 'kind', 'plat', 'topic', 'pos', 'ratio', 'how', 'stage', 'date', 'sizes', 'n', 'd',
               'email', 'telefono', 'telefonoHref', 'whatsapp', 'ruc', 'instagramUsuario', 'youtube', 'instagram', 'facebook', 'tiktok',
               'imagenes', 'galeria', 'gallery', 'months', 'mp', 'hosts', 'word'}
# Por sección: el tipo del catálogo (journey/exp) y los anfitriones de la portada (slugs)
NO_TRADUCIR_EN = {'journeys': {'dur'}, 'inicio': {'anfitriones'}}
# Secciones que solo traducen una parte: reseñas (solo sus categorías) y legales (nada)
SOLO = {'resenas': {'temas', 'plataformas'}, 'legales': set()}

# Frases del código armadas con variables (`…${x}…`) o palabras sueltas que el extractor no toma: se reemplazan tal
# cual en el código de la plantilla. La traducción está en i18n/<idioma>.json con el fragmento como clave.
FRAGMENTOS = [
    "`tus ${n} guardados`",
    "`Tu Travel Advisor recibirá ${what} junto a esta solicitud.`",
    "'viajero' : 'viajeros'",
    "`Para ${pax} ${paxWord}: ${j.priceMax ? '' : 'desde '}USD",
    "`Ideal en ${mName}`",
    "`Buena época en ${mName}`",
    "`Lluvias en ${mName}`",
    "`Necesita ${need} días y tu ventana es de ${win}. Lo ajustamos contigo.`",
    "`Paso ${i} de ${S.length - 1} · ${stp.k}`",
    "`Adjuntamos tus ${st.sentFav} guardados a la solicitud.`",
    "`Adjuntamos tus ${this.state.sentFav} guardados a la solicitud.`",
    "`Foto ${st.lb + 1} de ${this.GAL.length}`",
    "`${r.stars || 0} de 5 estrellas`",
    "' (menor de edad)'",
    "['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']",
    "`${this.MONTHS[now.getMonth()]} de ${now.getFullYear()}`",
]

_spec = importlib.util.spec_from_file_location('extraer', RAIZ / 'tools/extraer-textos.py')
X = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(X)


# ── Textos a traducir ─────────────────────────────────────────────────────
def textos_fijos():
    """Textos de las plantillas (como el servidor) y de js/ (t('…'))."""
    out = {}
    for p in PLANTILLAS:
        fuente = (RAIZ / p).read_text(encoding='utf-8')
        for t, _ in X.textos_plantilla(p, fuente):
            out[t] = 1
        cuerpo = fuente[fuente.find('<body'):]
        m = re.search(r'<script type="text/x-dc"[^>]*>(.*?)</script>', cuerpo, re.S)
        for lit in X.literales(m.group(1) if m else ''):  # 'Día ' + n: también con espacios alrededor
            if lit != lit.strip() and X.es_frase(lit.strip()):
                out[lit.strip()] = 1
        for t in re.findall(r'<title>([^<]+)</title>', fuente):
            out[X.normalizar(t)] = 1
    for ruta in JS:
        for t, _ in X.marcados(ruta, (RAIZ / ruta).read_text(encoding='utf-8')):
            out[t] = 1
    out['Día'] = 1
    for frag in FRAGMENTOS:
        out[frag] = 1
    return list(out)


def es_ruta(v: str) -> bool:
    return bool(re.match(r'^(https?:|assets/|/|#|[\w-]+\.html)', v) or re.fullmatch(r'\d{9,}-[0-9a-f]+', v) or re.fullmatch(r'[\d\W]*', v))


def hojas(v, clave='', seccion=''):
    """Strings traducibles de una sección de contenido."""
    if clave in NO_TRADUCIR or clave in NO_TRADUCIR_EN.get(seccion, ()):
        return
    if isinstance(v, dict):
        for k, x in v.items():
            if seccion in SOLO and clave == '' and k not in SOLO[seccion]:
                continue
            yield from hojas(x, k, seccion)
    elif isinstance(v, list):
        for x in v:
            yield from hojas(x, clave, seccion)
    elif isinstance(v, str) and v.strip() and not es_ruta(v):
        yield v


def contenido(n):
    return json.loads((RAIZ / f'content/{n}.json').read_text(encoding='utf-8'))


def textos_contenido():
    out = {}
    for n in CONTENIDO:
        for t in hojas(contenido(n), '', n):
            out[t] = 1
    return list(out)


# ── Traducción ────────────────────────────────────────────────────────────
def traducir_js(js, dic):
    """Frases entre comillas del código (como sunqu_traducir_js), también con espacios alrededor ('Día ')."""
    def f(m):
        q = m.group(0)[0]
        crudo = m.group(1) if q == "'" else m.group(2)
        texto = crudo.replace('\\\\', '\x00').replace('\\' + q, q).replace('\x00', '\\')
        clave, antes, despues = texto, '', ''
        if clave not in dic and texto.strip() in dic and texto.strip():
            clave = texto.strip()
            antes, despues = texto[:len(texto) - len(texto.lstrip())], texto[len(texto.rstrip()):]
        if clave not in dic:
            return m.group(0)
        nuevo = antes + dic[clave] + despues
        return q + nuevo.replace('\\', '\\\\').replace(q, '\\' + q).replace('\r', '').replace('\n', '\\n').replace('</', '<\\/') + q
    js = X.LITERAL.sub(f, js)
    for frag in FRAGMENTOS:  # después: un apóstrofo de la traducción («We've») no desordena las demás frases
        if frag in dic:
            js = js.replace(frag, dic[frag])
    if 'Día' in dic:  # aria-label de cada día: `Día ${i + 1}, …`
        js = js.replace('`Día ${', '`' + dic['Día'] + ' ${')
    return js


def traducir_html(doc, dic):
    """Cuerpo de una plantilla (como sunqu_traducir_html)."""
    i = doc.find('<body')
    def f(m):
        tok = m.group(0)
        if m.group(1):
            if m.group(1).lower() == 'script' and 'type="text/x-dc"' in (m.group(2) or ''):
                a, b = tok.index('>') + 1, tok.rindex('</')
                return tok[:a] + traducir_js(tok[a:b], dic) + tok[b:]
            return tok
        if tok.startswith('<'):
            def g(a):
                k = X.normalizar(a.group(2))
                return a.group(1) + html.escape(dic[k], quote=True) + a.group(3) if k in dic and '{{' not in a.group(2) else a.group(0)
            return re.sub(r'(\s(?:alt|aria-label|placeholder|title)=")([^"]*)(")', g, tok)
        k = X.normalizar(tok)
        if not k or k not in dic:
            return tok
        e = re.match(r'^(\s*).*?(\s*)$', tok, re.S)
        return e.group(1) + html.escape(dic[k], quote=False) + e.group(2)
    cuerpo = re.sub(r'<(script|style|noscript)\b([^>]*)>.*?</\1>|<[^>]+>|[^<]+', f, doc[i:], flags=re.S | re.I)
    return doc[:i] + cuerpo


def traducir_contenido(v, dic, clave='', seccion='', nivel=0):
    if isinstance(v, dict):
        return {k: (x if (seccion in SOLO and nivel == 0 and k not in SOLO[seccion]) else traducir_contenido(x, dic, k, seccion, nivel + 1)) for k, x in v.items()}
    if isinstance(v, list):
        return [traducir_contenido(x, dic, clave, seccion, nivel + 1) for x in v]
    if isinstance(v, str):
        if v.startswith('assets/'):
            return '/' + v  # las páginas traducidas están en /en/…: las imágenes, desde la raíz
        if clave not in NO_TRADUCIR and clave not in NO_TRADUCIR_EN.get(seccion, ()) and v in dic:
            return dic[v]
    return v


# ── Páginas ───────────────────────────────────────────────────────────────
def url(lang, pagina):
    return DOMINIO + '/' + ('' if lang == 'es' else lang + '/') + ('' if pagina == 'index.html' else pagina)


def bloque_seo(pagina, lang, dic):
    sitio = contenido('sitio')['seo']['paginas']
    h = []
    if pagina in SEO:
        desc = sitio[SEO[pagina]]['description']
        h.append(f'<meta name="description" content="{html.escape(dic.get(desc, desc), quote=True)}">')
        h.append(f'<link rel="canonical" href="{url(lang, pagina)}">')
        for l in ['es'] + IDIOMAS:
            h.append(f'<link rel="alternate" hreflang="{l}" href="{url(l, pagina)}">')
        h.append(f'<link rel="alternate" hreflang="x-default" href="{url("en", pagina)}">')
    h.append('<link rel="icon" href="' + ('' if lang == 'es' else '/') + 'assets/favicon-32.png" type="image/png">')
    return '<!-- sunqu:seo -->' + ''.join(h) + '<!-- /sunqu:seo -->'


def con_seo(doc, pagina, lang, dic):
    return re.sub(r'<!-- sunqu:seo -->.*?<!-- /sunqu:seo -->', lambda m: bloque_seo(pagina, lang, dic), doc, count=1, flags=re.S)


def rutas_desde_raiz(doc):
    """En /en/… los estilos, scripts, imágenes y datos se piden a la raíz; los enlaces entre páginas quedan en el idioma."""
    doc = re.sub(r'''(["'])(assets|css|js|vendor|fonts)/''', r'\1/\2/', doc)
    return doc


def pagina_traducida(pagina, lang, dic, dic_js):
    doc = (RAIZ / pagina).read_text(encoding='utf-8')
    doc = traducir_html(doc, dic)
    doc = doc.replace('<html lang="es">', f'<html lang="{lang}">', 1)
    doc = re.sub(r'<title>([^<]+)</title>', lambda m: '<title>' + html.escape(dic.get(X.normalizar(m.group(1)), m.group(1)), quote=False) + '</title>', doc, count=1)
    doc = con_seo(doc, pagina, lang, dic)
    doc = rutas_desde_raiz(doc)
    script = '<script>window.SUNQU_I18N_DIC=' + json.dumps(dic_js, ensure_ascii=False).replace('</', '<\\/') + ';</script>\n\t\t'
    doc = doc.replace('<script src="/js/sunqu-idiomas.js"></script>', script + '<script src="/js/sunqu-idiomas.js"></script>', 1)
    return doc


def sitemap():
    j = contenido('journeys')['items']
    e = contenido('experiencias')['items']
    d = contenido('diario')['posts']
    rutas = [p for p in SEO] + [f'journey.html?j={x["slug"]}' for x in j] + [f'experiencia.html?e={x["slug"]}' for x in e] + [f'articulo.html?a={x["slug"]}' for x in d]
    out = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">']
    for r in rutas:
        for l in ['es'] + IDIOMAS:
            out.append(f'  <url><loc>{html.escape(url(l, r))}</loc>' + ''.join(
                f'<xhtml:link rel="alternate" hreflang="{x}" href="{html.escape(url(x, r))}"/>' for x in ['es'] + IDIOMAS) + '</url>')
    out.append('</urlset>')
    (RAIZ / 'sitemap.xml').write_text('\n'.join(out) + '\n', encoding='utf-8')
    (RAIZ / 'robots.txt').write_text(f'User-agent: *\nAllow: /\nDisallow: /server/\nDisallow: /wordpress/\nDisallow: /docs/\nDisallow: /tools/\n\nSitemap: {DOMINIO}/sitemap.xml\n', encoding='utf-8')
    return len(rutas) * (1 + len(IDIOMAS))


def main():
    fuente = textos_fijos() + [t for t in textos_contenido() if t not in set(textos_fijos())]
    if '--fuente' in sys.argv:
        (RAIZ / 'i18n').mkdir(exist_ok=True)
        (RAIZ / 'i18n/_fuente.json').write_text(json.dumps(fuente, ensure_ascii=False, indent=1) + '\n', encoding='utf-8')
        print(f'{len(fuente)} textos → i18n/_fuente.json')
        return
    js = {t for r in JS for t, _ in X.marcados(r, (RAIZ / r).read_text(encoding='utf-8'))} | {'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'}
    for p in PLANTILLAS:  # la raíz: descripción y hreflang
        f = RAIZ / p
        f.write_text(con_seo(f.read_text(encoding='utf-8'), p, 'es', {}), encoding='utf-8')
    for lang in IDIOMAS:
        archivo = RAIZ / f'i18n/{lang}.json'
        dic = json.loads(archivo.read_text(encoding='utf-8')) if archivo.exists() else {}
        dic = {k: v for k, v in dic.items() if isinstance(v, str) and v.strip()}
        faltan = [t for t in fuente if t not in dic]
        (RAIZ / lang).mkdir(exist_ok=True)
        dic_js = {k: v for k, v in dic.items() if k in js}
        for p in PLANTILLAS:
            (RAIZ / lang / p).write_text(pagina_traducida(p, lang, dic, dic_js), encoding='utf-8')
        (RAIZ / 'content' / lang).mkdir(exist_ok=True)
        for n in CONTENIDO:
            datos = traducir_contenido(contenido(n), dic, '', n)
            (RAIZ / f'content/{lang}/{n}.json').write_text(json.dumps(datos, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
        print(f'{lang}: {len(fuente) - len(faltan)}/{len(fuente)} textos traducidos' + (f' · faltan {len(faltan)} (p. ej. {faltan[0]!r})' if faltan else ''))
    print(f'sitemap.xml: {sitemap()} URLs')


if __name__ == '__main__':
    main()
