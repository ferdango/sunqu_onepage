#!/usr/bin/env python3
"""
Genera wordpress/sunqu-cms/schema.json a partir de content/*.json.

El esquema es la única definición del modelo de contenido: con él el plugin registra los tipos de
contenido y los campos (ACF / Secure Custom Fields), importa los JSON y responde la API con el mismo
formato que usa la web. Si agregas o renombras un campo en content/*.json, vuelve a correr:

    python3 wordpress/tools/generar-schema.py

Las etiquetas del panel salen de LABELS; los tipos se infieren de los valores (con los ajustes de TIPOS).
"""
import json, os, re

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
CONTENT = os.path.join(ROOT, "content")
OUT = os.path.join(ROOT, "wordpress", "sunqu-cms", "schema.json")

# ── Cómo se guarda cada archivo de content/ en WordPress ───────────────────────────────────────
SECCIONES = {
    "sitio": {"tipo": "ajustes", "id": "sunqu_sitio", "titulo": "Sitio", "orden": 1},
    "inicio": {"tipo": "ajustes", "id": "sunqu_inicio", "titulo": "Inicio (portada)", "orden": 2},
    "journeys": {"tipo": "coleccion", "post_type": "sunqu_journey", "singular": "Journey", "plural": "Journeys", "icono": "dashicons-location-alt",
                 "slug": "slug", "titulo_item": "title", "ajustes": {"id": "sunqu_journeys", "titulo": "Journeys · común"}, "orden": 3},
    "anfitriones": {"tipo": "coleccion", "post_type": "sunqu_anfitrion", "singular": "Anfitrión", "plural": "Anfitriones", "icono": "dashicons-groups",
                    "slug": "slug", "titulo_item": "role", "orden": 4},
    "experiencias": {"tipo": "coleccion", "post_type": "sunqu_experiencia", "singular": "Experiencia", "plural": "Experiencias", "icono": "dashicons-palmtree",
                     "slug": "slug", "titulo_item": "t", "orden": 5},
    "resenas": {"tipo": "coleccion", "post_type": "sunqu_resena", "singular": "Reseña", "plural": "Reseñas", "icono": "dashicons-format-quote",
                "slug": "id", "titulo_item": "name", "ajustes": {"id": "sunqu_resenas", "titulo": "Reseñas · temas y plataformas"}, "orden": 6},
    "productos": {"tipo": "coleccion", "post_type": "sunqu_producto", "singular": "Producto", "plural": "Productos (tienda)", "icono": "dashicons-cart",
                  "slug": "id", "titulo_item": "name", "ajustes": {"id": "sunqu_productos", "titulo": "Tienda · categorías"}, "orden": 7},
    "diario": {"tipo": "entradas", "orden": 8},
    "legales": {"tipo": "ajustes", "id": "sunqu_legales", "titulo": "Legales", "orden": 9},
}

# ── Etiquetas del panel (clave → etiqueta · ayuda) ─────────────────────────────────────────────
LABELS = {
    "contacto": "Contacto", "email": "Correo", "telefono": "Teléfono (como se muestra)", "telefonoHref": "Teléfono para enlace (solo dígitos y +)",
    "oficina": "Oficina", "whatsapp": "WhatsApp · número internacional sin + (ej. 51966461384)", "redes": "Redes sociales",
    "youtube": "YouTube (URL)", "instagram": "Instagram", "facebook": "Facebook (URL)", "tiktok": "TikTok (URL)", "instagramUsuario": "Usuario de Instagram (@…)",
    "empresa": "Empresa (Libro de reclamaciones)", "name": "Nombre", "ruc": "RUC", "address": "Dirección",
    "conceptos": "Conceptos (qué quieres sentir)", "destinos": "Destinos", "duraciones": "Tipos de duración", "slug": "Identificador (slug)", "label": "Etiqueta", "icon": "Isotipo",
    "heroSlides": "Hero · fotos en rotación", "caption": "Pie de foto", "img": "Imagen", "principios": "Principios", "n": "Número", "layer": "Capa", "title": "Título", "q": "Pregunta o frase",
    "bg": "Color de fondo", "fg": "Color del texto", "photo": "Foto", "formas": "Formas de viajar", "sub": "Subtítulo", "href": "Enlace", "cta": "Texto del botón",
    "condiciones": "Momento Sunqu · condiciones", "body": "Texto", "altitud": "Curva de altitud (journey insignia)", "dias": "Días", "d": "Día (D1…)", "place": "Lugar",
    "short": "Nombre corto", "m": "Altitud (m)", "status": "Estado", "habitual": "Altitudes de referencia (m)", "ruta": "Mapa de la ruta · paradas", "alt": "Altitud",
    "lon": "Longitud", "lat": "Latitud", "fases": "Antes, durante y después", "kicker": "Antetítulo", "palabras": "Palabras en quechua", "word": "Palabra",
    "meaning": "Significado", "color": "Color", "pasos": "Cómo diseñamos tu viaje · pasos", "anfitriones": "Anfitriones destacados", "diario": "Diario en la portada",
    "cantidad": "Artículos a mostrar", "items": "Elementos", "c": "Conceptos", "days": "Duración (texto)", "ficha": "Ficha del journey (journey.html)",
    "route": "Ruta (paradas)", "momentAlt": "Momento Sunqu · texto alternativo", "momentImg": "Momento Sunqu · foto", "price": "Precio", "priceShort": "Precio corto",
    "priceKicker": "Antetítulo del precio", "priceNote": "Nota del precio", "altLabel": "Etiqueta de la altitud", "region": "Región", "card": "Imagen de tarjeta (otros journeys)",
    "why": "Por qué", "what": "Qué es", "whatLong": "Qué es (versión larga)", "storyTitle": "Historia · título", "story": "Historia · párrafos", "moment": "Momento Sunqu",
    "how": "Principio que lo guía", "howText": "Cómo se vive · texto", "hosts": "Anfitriones", "mp": "Variante del Momento Sunqu", "ayniNote": "Nota de reciprocidad",
    "slides": "Galería del hero", "vivTitle": "Día a día · título", "vivText": "Día a día · texto", "chartTitle": "Altitud · título", "chartText": "Altitud · texto",
    "itin": "Itinerario día a día", "t": "Título", "stage": "Etapa (DESCUBRE, VIVE…)", "plan": "Planifica (consejos, notas y temporada)", "tips": "Consejos Sunqu",
    "access": "Nota de accesibilidad", "season": "Temporada", "text": "Texto", "clima": "Clima", "temp": "Temperatura", "cal": "Calendario", "months": "Meses (ene → dic)",
    "catalogo": "Tarjeta del catálogo (descubre.html)", "orden": "Orden", "dest": "Destinos", "dur": "Duración", "priceMax": "Precio hasta (USD)",
    "kw": "Palabras clave de búsqueda", "imagenes": "Imágenes", "inicio": "Tarjeta de la portada", "ph": "Texto alternativo de la foto", "role": "Rol",
    "teach": "Te enseña…", "altInicio": "Texto alternativo en la portada", "principios_j": "Principios (fichas)", "id": "Identificador", "galeria": "Galería «Recuerda»",
    "ratio": "Proporción (3/4, 4/3)", "temas": "Temas", "one": "Etiqueta en singular", "plataformas": "Plataformas", "sample": "Es un ejemplo (muestra la etiqueta)",
    "kind": "Tipo", "plat": "Plataforma", "country": "País", "when": "Cuándo", "topic": "Tema", "video": "Video (mp4 vertical)", "poster": "Portada del video",
    "dur_video": "Duración", "likes": "Me gusta", "url": "Enlace al post (Instagram o TikTok)", "stars": "Estrellas (1–5)", "photos": "Fotos", "categorias": "Categorías",
    "cat": "Categoría", "desc": "Descripción", "sizes": "Tallas", "tag": "Etiqueta", "pos": "Encuadre de la foto (CSS object-position)", "read": "Minutos de lectura",
    "author": "Autor", "lead": "Entradilla", "terminos": "Términos y condiciones", "secciones": "Secciones", "h": "Título de la sección", "blocks": "Bloques",
    "p": "Párrafo", "ul": "Lista", "b": "Negrita", "link": "Enlace", "cite": "Cita · fuente", "quote": "Cita", "cap": "Pie de imagen",
    "seo": "SEO (buscadores y redes sociales)", "paginas": "Páginas fijas", "description": "Descripción (meta description)",
}
# Páginas fijas dentro de Sitio › SEO (las claves coinciden con otras del contenido, por eso van aparte)
SEO_PAGINAS = {"inicio": "Inicio", "descubre": "Descubre", "recuerda": "Recuerda", "diario": "Diario Sunqu",
               "terminos": "Términos y condiciones", "reclamaciones": "Libro de reclamaciones"}
AYUDA = {
    "img": "Sube la imagen o elige una de la biblioteca.",
    "slug": "Minúsculas y guiones. Se usa en la URL (journey.html?j=slug) y en «Guardados».",
    "mp": "alma, profundo u origen: qué foto de Momento Sunqu usa la ficha. Vacío = ninguna.",
    "how": "Uno de los principios de «Journeys · común».",
    "months": "0 = temporada de lluvias · 1 = buena · 2 = ideal",
    "sample": "Actívalo en reseñas de muestra; desactívalo en reseñas reales.",
    "url": "Si pegas el enlace de un post público, la web muestra el embed oficial.",
    "kw": "Separadas por espacios. Ayudan al buscador de Descubre.",
    "orden": "Orden en el catálogo de Descubre (1 = primero).",
    "description": "Unos 150 caracteres. Es el texto que muestran Google y las redes al compartir el enlace.",
}

IMAGEN = {"img", "photo", "card", "momentImg", "poster"}
LISTA_IMAGEN = {"imagenes", "photos"}
COLOR = {"bg", "fg", "color"}
TEXTAREA = {"body", "why", "what", "whatLong", "caption", "desc", "text", "teach", "q", "howText", "moment", "lead", "p", "access", "status", "storyTitle", "t_body"}
ISOTIPOS = {f"assets/diamond-{c}.png": c.capitalize() for c in ["orange", "yellow", "lime", "olive", "teal"]}
PATRONES = {f"assets/pattern-{c}.png": c.capitalize() for c in ["pair", "flower", "steps", "cluster", "ring", "weave"]}
# Campos cuyo valor sale de otra lista del contenido (el plugin arma las opciones en vivo)
OPCIONES_DE = {"c": "sitio:conceptos", "dest": "sitio:destinos", "topic": "resenas:temas", "plat": "resenas:plataformas"}
RELACION = {("journeys", "hosts"): "anfitriones", ("inicio", "anfitriones"): "anfitriones"}


def label(k):
    return LABELS.get(k, k)


def infer(key, values, section, path):
    """values: todos los valores que toma la clave (en todos los elementos)."""
    vals = [v for v in values if v is not None]
    f = {"name": key, "label": label(key)}
    if section == "anfitriones" and key == "alt":
        f["label"] = "Texto alternativo de la foto"
    if section == "resenas" and key == "what":
        f["label"] = "Qué vivió o compró"
    if section == "resenas" and key == "dur":
        f["label"] = "Duración del video"
    if section == "sitio" and path[-1:] == ["paginas"]:
        f["label"] = SEO_PAGINAS.get(key, f["label"])
    if section == "sitio" and "seo" in path and key == "title":
        f["label"] = "Título (pestaña del navegador y buscadores)"
    if key in AYUDA:
        f["ayuda"] = AYUDA[key]
    if any(v is None for v in values):
        f["nulo"] = True
    if not vals:
        f["type"] = "text"; return f
    v0 = vals[0]
    if (section, key) in RELACION:
        f.update(type="relacion", coleccion=RELACION[(section, key)]); return f
    if key == "months":
        f["type"] = "meses"; return f
    if key in OPCIONES_DE:
        f.update(type="checkbox" if isinstance(v0, list) else "select", opciones_de=OPCIONES_DE[key]); return f
    if key == "dur" and section == "journeys":
        f.update(type="select", opciones_de="sitio:duraciones"); return f
    if key == "cat" and section == "productos":
        f.update(type="select", opciones_de="productos:categorias"); return f
    if key == "how":
        f.update(type="select", opciones_de="journeys:principios"); return f
    if key == "kind":
        f.update(type="select", choices={"video": "Video", "photo": "Foto", "comment": "Comentario"}); return f
    if key == "icon":
        f.update(type="select", choices=ISOTIPOS); return f
    if section == "inicio" and key == "img" and "fases" in path:
        f.update(type="select", choices=PATRONES, label="Patrón"); return f
    if key == "video":
        f["type"] = "archivo"; return f
    if key in IMAGEN:
        f["type"] = "imagen"; return f
    if key in COLOR:
        f["type"] = "color"; return f
    if isinstance(v0, bool):
        f["type"] = "true_false"; return f
    if isinstance(v0, (int, float)):
        f["type"] = "number"
        if any(isinstance(v, float) and not float(v).is_integer() for v in vals):
            f["decimal"] = True
        return f
    if isinstance(v0, str):
        long = any(len(v) > 90 or "\n" in v for v in vals)
        f["type"] = "textarea" if (key in TEXTAREA or long) else "text"
        return f
    if isinstance(v0, list):
        inner = [x for v in vals for x in v]
        if key in LISTA_IMAGEN:
            f.update(type="lista", item="imagen"); return f
        if not inner or all(isinstance(x, str) for x in inner):
            f.update(type="lista", item="textarea" if any(len(x) > 90 for x in inner) else "text"); return f
        if all(isinstance(x, (int, float)) for x in inner):
            f.update(type="lista", item="number"); return f
        if all(isinstance(x, dict) for x in inner):
            f.update(type="repeater", sub_fields=fields_of(inner, section, path + [key])); return f
        raise ValueError(f"lista mixta en {section}.{'.'.join(path + [key])}")
    if isinstance(v0, dict):
        f.update(type="group", sub_fields=fields_of(vals, section, path + [key])); return f
    raise ValueError(key)


def fields_of(objs, section, path, skip=()):
    keys = []
    for o in objs:
        for k in o:
            if k not in keys and k not in skip and not k.startswith("_"):
                keys.append(k)
    out = []
    for k in keys:
        values = [o.get(k) for o in objs if k in o]
        f = infer(k, values, section, path)
        if any(k not in o for o in objs):
            f["opcional"] = True
            if any(o[k] in ("", [], None) for o in objs if k in o):
                raise ValueError(f"{section}.{'.'.join(path + [k])}: clave opcional con valor vacío; quítala de ese elemento del JSON")
        out.append(f)
    return out


def main():
    schema = {"_info": "Modelo de contenido de Sunqu Journeys. Generado por wordpress/tools/generar-schema.py desde content/*.json: no lo edites a mano.", "secciones": {}}
    for name, cfg in sorted(SECCIONES.items(), key=lambda x: x[1]["orden"]):
        data = json.load(open(os.path.join(CONTENT, name + ".json"), encoding="utf-8"))
        sec = dict(cfg)
        sec["info"] = data.get("_info", "")
        if cfg["tipo"] == "ajustes":
            sec["campos"] = fields_of([data], name, [])
        elif cfg["tipo"] == "coleccion":
            sec["campos"] = fields_of(data["items"], name, [], skip=(cfg["slug"], cfg["titulo_item"]))
            rest = {k: v for k, v in data.items() if k != "items" and not k.startswith("_")}
            if rest:
                sec["ajustes"]["campos"] = fields_of([rest], name, [])
        elif cfg["tipo"] == "entradas":
            # Entradas nativas: título, slug, fecha, extracto, imagen destacada, categoría y cuerpo (bloques) son de WordPress.
            nativos = ("slug", "title", "date", "excerpt", "img", "cat", "sections")
            sec["campos"] = fields_of(data["posts"], name, [], skip=nativos)
            sec["nativos"] = {"slug": "post_name", "title": "post_title", "date": "post_date", "excerpt": "post_excerpt", "img": "imagen destacada", "cat": "categoría", "sections": "cuerpo (editor de bloques)"}
        schema["secciones"][name] = sec
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "w", encoding="utf-8") as fh:
        json.dump(schema, fh, ensure_ascii=False, indent=1)
        fh.write("\n")
    n = sum(1 for _ in re.finditer(r'"name"', open(OUT, encoding="utf-8").read()))
    print(f"schema.json: {len(schema['secciones'])} secciones, {n} campos → {os.path.relpath(OUT, ROOT)}")


if __name__ == "__main__":
    main()
