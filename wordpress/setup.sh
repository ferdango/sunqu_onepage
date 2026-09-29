#!/usr/bin/env bash
# Levanta el entorno local, instala WordPress + Secure Custom Fields + Sunqu CMS, importa content/*.json y verifica.
set -euo pipefail
cd "$(dirname "$0")"
[ -f .env ] || cp .env.example .env
# Token de purga (web ↔ WordPress): se genera una vez y queda en .env
if ! grep -q '^PURGE_TOKEN=.\+' .env; then
  sed -i.bak '/^PURGE_TOKEN=/d' .env && rm -f .env.bak
  echo "PURGE_TOKEN=$(openssl rand -hex 24)" >> .env
fi
set -a; . ./.env; set +a
wp() { docker compose run --rm -T cli "$@"; }

echo "▸ Levantando contenedores…"
docker compose up -d db wordpress sitio

echo "▸ Esperando a WordPress…"
for i in $(seq 1 60); do
  if wp core version >/dev/null 2>&1 && wp db check >/dev/null 2>&1; then break; fi
  sleep 3
done

if ! wp core is-installed >/dev/null 2>&1; then
  echo "▸ Instalando WordPress…"
  wp core install --url="$WP_URL" --title="Sunqu CMS" --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" --admin_email="$WP_ADMIN_EMAIL" --skip-email
  # Contenido de ejemplo de WordPress («¡Hola mundo!», «Página de ejemplo»): no es parte del Diario
  wp post delete $(wp post list --post_type=post,page --format=ids) --force >/dev/null 2>&1 || true
fi

echo "▸ Idioma, plugins y tema…"
wp language core install es_ES --activate >/dev/null 2>&1 || true
wp plugin install secure-custom-fields --activate
wp plugin activate sunqu-cms
wp theme activate sunqu-headless
wp rewrite structure '/%postname%/' --hard
wp option update sunqu_web "$WEB_URL" >/dev/null
wp option update sunqu_purga_token "$PURGE_TOKEN" >/dev/null
wp option update blogdescription "Contenido de sunqujourneys" >/dev/null
wp option update blog_public 0 >/dev/null   # el CMS no se indexa (la web tiene su propio SEO)

echo "▸ Importando content/*.json…"
wp sunqu importar --dir=/var/www/front/content --front=/var/www/front --user="$WP_ADMIN_USER" --podar $IMPORT_FLAGS

echo "▸ Verificando que la API devuelva lo mismo que los JSON…"
wp sunqu verificar --dir=/var/www/front/content --user="$WP_ADMIN_USER"

cat <<MSG

✔ Listo.
  Panel de WordPress : $WP_URL/wp-admin   (usuario y clave en wordpress/.env)
  API de contenido   : $WP_URL/wp-json/sunqu/v1/contenido
  Web (como en Hostinger, leyendo WordPress): $WEB_URL
MSG
