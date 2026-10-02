#!/usr/bin/env python3
"""
Extrae los textos fijos de la web para traducirlos con Loco Translate (WordPress › Loco › Plugins › Sunqu CMS).

Lee las 8 plantillas, js/sunqu-content.js, js/sunqu-analytics.js, js/sunqu-campos.js, server/lib/*.php y el plugin, y escribe
wordpress/sunqu-cms/i18n/textos-web.php: la lista de textos de la interfaz con dónde aparece cada uno. El
plugin la junta con los textos del contenido (journeys, Diario, legales…) en languages/sunqu-cms.pot, la
plantilla que usa Loco.

Qué se extrae (las mismas reglas que aplica la web al traducir, server/lib/idiomas.php):
  · texto entre etiquetas de las plantillas (sin {{ }} solos), y los atributos alt, aria-label, placeholder y title;
  · textos entre comillas del código de cada plantilla (<script type="text/x-dc">) que parecen frases para
    personas: tienen letras y un espacio, una tilde o una mayúscula inicial; nada de rutas, CSS ni código;
  · en JS y PHP, lo marcado con t('…'), sunqu_t('…') o sunqu_tr('…').

Uso: python3 tools/extraer-textos.py   (después de cambiar textos de las plantillas; luego empaquetar el plugin)
"""
import html
import json
import re
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
PLANTILLAS = ['index.html', 'journey.html', 'descubre.html', 'recuerda.html', 'blog.html', 'articulo.html', 'terminos.html', 'reclamaciones.html']
JS = ['js/sunqu-content.js', 'js/sunqu-analytics.js', 'js/sunqu-campos.js']
PHP = sorted(str(p.relative_to(RAIZ)) for p in [*RAIZ.glob('server/lib/*.php'), *RAIZ.glob('wordpress/sunqu-cms/includes/*.php')])
SALIDA = RAIZ / 'wordpress/sunqu-cms/i18n/textos-web.php'
ATRIBUTOS = ('alt', 'aria-label', 'placeholder', 'title')

# Textos del código de las plantillas que parecen frases pero son datos o código (no se traducen)
NO_TRADUCIR = {
    'Aeonik', 'Manrope', 'Sunqu', 'Sunqu Journeys', 'Hola Sunqu', 'WhatsApp', 'Instagram', 'TikTok', 'YouTube', 'Facebook', 'LinkedIn', 'X',
    # Teclas y APIs del navegador que el código compara (traducirlas rompería el teclado o la lógica)
    'Escape', 'Enter', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'IntersectionObserver', 'ResizeObserver', 'MutationObserver',
    # El selector de idioma muestra cada idioma en su propia lengua
    'Español', 'English', 'Português', 'Hola, bienvenido', 'Hello, welcome', 'Olá, bem-vindo', 'Idioma · Language · Idioma',
}
CODIGO = re.compile(r'(://|\.html|\.png|\.jpe?g|\.svg|\.mp4|\.webp|\.js\b|\.css|^#|^\.|^/|^\(|px\b|rgba?\(|var\(|cubic-bezier|translate|scale\(|calc\(|inset\(|serif\b|\bopacity\b|\blinear\b|\bease(-in|-out)?\b|transition|xMid|=>|[{}\[\]<>=;`\\]|^\S*,\S*$|^\w+-\w+(-\w+)*$|^[a-z]+([A-Z][a-z]*)+$|^[a-z_]+$|\bsc-|\bsj-|\bjr-|\bdc-|\bbc-|\bar-|\bbl-|\bis-)')


def normalizar(t: str) -> str:
    """Igual que sunqu_i18n_clave() (server/lib/idiomas.php): sin entidades y con los espacios colapsados."""
    return re.sub(r'\s+', ' ', html.unescape(t)).strip()


# Literales entre comillas simples o dobles, leídos de izquierda a derecha como sunqu_traducir_js()
LITERAL = re.compile(r"'((?:[^'\\\n]|\\.)*)'|\"((?:[^\"\\\n]|\\.)*)\"")


def literales(codigo: str):
    for m in LITERAL.finditer(codigo):
        q = m.group(0)[0]
        crudo = m.group(1) if q == "'" else m.group(2)
        yield crudo.replace('\\\\', '\x00').replace('\\' + q, q).replace('\x00', '\\')


def es_frase(t: str) -> bool:
    """Texto para personas: letras y (un espacio, una letra no ASCII o mayúscula seguida de minúscula)."""
    if len(t) < 2 or not re.search(r'[^\W\d_]', t) or t in NO_TRADUCIR or CODIGO.search(t):
        return False
    return bool(re.search(r'\s', t) or re.search(r'[^\x00-\x7f]', t) or re.match(r'^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]', t))


def es_texto_html(t: str) -> bool:
    return len(t) >= 1 and bool(re.search(r'[^\W\d_]', t)) and not re.fullmatch(r'(\{\{[^}]*\}\}\s*)+', t) and t not in NO_TRADUCIR


def textos_plantilla(nombre: str, fuente: str):
    """(texto, referencia) de una plantilla: nodos de texto, atributos y frases de su código."""
    cuerpo = fuente[fuente.find('<body'):]
    logica = ''
    m = re.search(r'<script type="text/x-dc"[^>]*>(.*?)</script>', cuerpo, re.S)
    if m:
        logica = m.group(1)
    sin_bloques = re.sub(r'<(script|style)\b[^>]*>.*?</\1>', ' ', cuerpo, flags=re.S)
    for nodo in re.findall(r'>([^<>]+)<', sin_bloques):
        t = normalizar(nodo)
        if es_texto_html(t):
            yield t, f'{nombre} (texto)'
    for atr in ATRIBUTOS:
        for valor in re.findall(r'\s' + atr + r'="([^"]*)"', sin_bloques):
            t = normalizar(valor)
            if '{{' not in t and es_texto_html(t):
                yield t, f'{nombre} ({atr})'
    for t in literales(logica):
        if es_frase(t.strip()) and t == t.strip():
            yield t, f'{nombre} (código)'
    # Meses abreviados de las fechas («12 sep 2026»)
    for lista in re.findall(r"MON = \[([^\]]+)\]", logica):
        for mes in re.findall(r"'([a-z]{3})'", lista):
            yield mes, f'{nombre} (mes abreviado)'


def marcados(ruta: str, fuente: str):
    """Textos marcados con t('…') en JS o sunqu_t('…') / sunqu_tr('…') / $msg('…') en PHP."""
    patron = r"\b(?:t|sunqu_t|sunqu_tr|msg)\(\s*'((?:[^'\\\n]|\\.)*)'"
    for lit in re.findall(patron, fuente):
        t = lit.replace("\\'", "'").replace('\\\\', '\\')
        if t:
            yield t, ruta


def php_cadena(s: str) -> str:
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def main():
    textos = {}
    for nombre in PLANTILLAS:
        for t, ref in textos_plantilla(nombre, (RAIZ / nombre).read_text(encoding='utf-8')):
            textos.setdefault(t, set()).add(ref)
    for ruta in JS + PHP:
        for t, ref in marcados(ruta, (RAIZ / ruta).read_text(encoding='utf-8')):
            textos.setdefault(t, set()).add(ref)
    if '--lista' in sys.argv:
        for t in sorted(textos):
            print(json.dumps(t, ensure_ascii=False), '·', ', '.join(sorted(textos[t])))
        return
    lineas = [
        '<?php',
        '/**',
        ' * Textos fijos de la web (plantillas, JS y PHP), para traducir con Loco Translate.',
        ' * GENERADO por tools/extraer-textos.py: no lo edites a mano.',
        ' */',
        '',
        "defined('ABSPATH') || exit;",
        '',
        'return [',
    ]
    for t in sorted(textos):
        lineas.append(f"\t{php_cadena(t)} => {php_cadena(', '.join(sorted(textos[t])))},")
    lineas.append('];')
    SALIDA.parent.mkdir(parents=True, exist_ok=True)
    SALIDA.write_text('\n'.join(lineas) + '\n', encoding='utf-8')
    print(f'{len(textos)} textos → {SALIDA.relative_to(RAIZ)}')


if __name__ == '__main__':
    main()
