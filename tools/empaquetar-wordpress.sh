#!/usr/bin/env bash
# Arma los dos zips para subir al WordPress de producción (cms.sunqujourneys.com):
#
#   dist/sunqu-cms.zip       PLUGIN → Plugins › Añadir nuevo › Subir plugin («Reemplazar el actual» si ya está).
#                            Trae el contenido actual de la web (content/*.json y las imágenes locales que usa)
#                            para Sunqu › Conexión y herramientas › Importar el contenido.
#   dist/sunqu-headless.zip  TEMA → Apariencia › Temas › Añadir tema › Subir tema (no se sube como plugin).
#
# Uso: bash tools/empaquetar-wordpress.sh   (dist/ no se sube al repositorio)
set -euo pipefail
cd "$(dirname "$0")/.."

# Solo reemplaza sus dos zips: dist/ puede tener otros archivos (p. ej. el zip de la web o notas de producción)
mkdir -p dist && rm -rf dist/tmp dist/sunqu-cms.zip dist/sunqu-headless.zip && mkdir -p dist/tmp
cp -R wordpress/sunqu-cms dist/tmp/sunqu-cms
cp -R wordpress/sunqu-headless dist/tmp/sunqu-headless

# Contenido para importar desde el panel
D=dist/tmp/sunqu-cms/datos
mkdir -p "$D"
cp -R content "$D/content"
# Solo las imágenes y videos locales que usa el contenido (rutas assets/… dentro de los JSON)
grep -rhoE '"assets/[^"]+"' content/*.json | tr -d '"' | sort -u | while read -r f; do
	if [ -f "$f" ]; then
		mkdir -p "$D/$(dirname "$f")" && cp "$f" "$D/$f"
	else
		echo "⚠ el contenido usa $f, pero no existe" >&2
	fi
done
commit=$(git rev-parse --short HEAD 2>/dev/null || echo '-')
git diff --quiet HEAD -- content 2>/dev/null || commit="$commit + cambios sin publicar"
printf '{ "creado": "%s", "commit": "%s" }\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$commit" > "$D/paquete.json"

# Sin archivos de macOS (.DS_Store, __MACOSX) y con la carpeta del plugin o del tema en la raíz del zip
find dist/tmp -name '.DS_Store' -delete
(cd dist/tmp && zip -qr -X ../sunqu-cms.zip sunqu-cms && zip -qr -X ../sunqu-headless.zip sunqu-headless)
rm -rf dist/tmp
ls -lh dist/*.zip
