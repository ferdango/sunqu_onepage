# Sunqu Journeys · Despliegue en Hostinger

Guía para publicar **https://sunqujourneys.com** (la web) y **https://cms.sunqujourneys.com** (WordPress headless).
Los pasos marcados con ⚠ necesitan accesos que el repositorio no tiene: la cuenta de Hostinger, el DNS, las
cuentas externas y las credenciales. **Esos pasos están PENDIENTES DE CONFIGURACIÓN.**

---

## 0. Requisitos

- ⚠ **Plan de Hostinger** con PHP 8.1 o superior (recomendado 8.3), MySQL/MariaDB, subdominios y SSH. Por ejemplo Premium/Business o Cloud. LiteSpeed y `mod_rewrite` vienen activos.
- ⚠ **Dominio** `sunqujourneys.com` apuntando a Hostinger: nameservers de Hostinger o registros A/AAAA.
- **El repositorio** `ferdango/sunqu_onepage` (rama `master`).

## 1. DNS y SSL ⚠

1. **hPanel › Dominios:** comprueba que `sunqujourneys.com` y `www.sunqujourneys.com` resuelven al hosting.
2. **Subdominio `cms.sunqujourneys.com`:** créalo en hPanel › Dominios › Subdominios, o agrega un registro A si el DNS está afuera.
3. **hPanel › Seguridad › SSL:** instala el certificado gratuito para `sunqujourneys.com`, `www` y `cms`.
4. **No actives** «Forzar HTTPS» ni redirecciones `www` en hPanel. El `.htaccess` ya lo hace (HTTPS y sin `www`, con 301); duplicarlo puede crear bucles.

## 2. PHP

hPanel › Avanzado › Configuración de PHP:
- **versión:** 8.3;
- **extensiones:** `curl`, `json` y `mbstring` (vienen activas);
- **`allow_url_fopen`:** no hace falta, porque se usa cURL.

## 3. Subir la web

**Opción A — Git (recomendada):** hPanel › Avanzado › Git.
1. Repositorio `https://github.com/ferdango/sunqu_onepage.git`, rama `master`, directorio `public_html` (vacío).
2. Activa el despliegue automático con el webhook que da hPanel en GitHub (Settings › Webhooks).
3. Cada `push` a `master` actualiza la web. Los CSS y JS cambian de `?v=` solos, así que no queda caché vieja.

**Opción B — Administrador de archivos o FTP:** sube el contenido del repositorio a `public_html`.
- Puedes omitir `.git/`, `wordpress/`, `docs/` y `tools/`: el `.htaccess` los bloquea igual, pero no hacen falta en el servidor.
- Tienen que estar `.htaccess`, `server/`, las páginas `.html`, `support.js`, `css/`, `js/`, `content/`, `assets/`, `fonts/` y `vendor/`.

**Resultado:** `public_html/.htaccess` y `public_html/server/index.php`, junto a `index.html`.

## 4. Variables de producción (`.env`)

1. En el Administrador de archivos crea **`/home/<usuario>/domains/sunqujourneys.com/.env`**, **fuera** de `public_html`. `server/lib/config.php` lo busca ahí primero.
2. Copia el contenido de `.env.example` y completa:

```ini
APP_ENV=production
SITE_URL=https://sunqujourneys.com
WORDPRESS_API_URL=https://cms.sunqujourneys.com/wp-json      # cuando WordPress esté listo (paso 5)
CACHE_PURGE_TOKEN=<cadena larga y aleatoria>                 # p. ej. el resultado de: openssl rand -hex 32
CONTENT_CACHE_TTL=300
CONSENT_MODE=banner
CSP_MODE=enforce
GA4_ID=            # ⚠ PENDIENTE: ver ANALYTICS.md
GTM_ID=            # ⚠ PENDIENTE
META_PIXEL_ID=     # ⚠ PENDIENTE
WHATSAPP_NUMBER=   # opcional: respaldo del número de Sunqu › Sitio
```

3. Permisos del archivo: `600`.
4. **Nunca** lo subas a git. `.gitignore` ya excluye `.env`.

**Si Hostinger no permite archivos fuera de `public_html`:** ponlo en `public_html/.env`. El `.htaccess` bloquea `.env*` (403).

## 5. WordPress en `cms.sunqujourneys.com` ⚠

1. **Instalar WordPress:** hPanel › Sitios web › Agregar sitio web › WordPress, en el subdominio `cms`, en español. Guarda el usuario administrador en un gestor de contraseñas. El instalador crea también la base de datos.
2. **Armar los dos zips** en tu equipo, desde la raíz del repositorio:

   ```bash
   bash tools/empaquetar-wordpress.sh
   ```

   Deja en `dist/`:
   - `sunqu-cms.zip`, el **plugin**, con el contenido actual de la web para importar;
   - `sunqu-headless.zip`, el **tema**.

   Los zips salen sin los archivos ocultos de macOS. Comprimir la carpeta con el Finder agrega `__MACOSX` y puede fallar.
3. **Plugin:** Plugins › Añadir nuevo › **Subir plugin** › `sunqu-cms.zip` › Instalar ahora › Activar. Si ya estaba instalado, WordPress ofrece «Reemplazar el actual por el subido».
4. **Tema:** Apariencia › Temas › Añadir tema › **Subir tema** › `sunqu-headless.zip` › Instalar ahora › Activar.
   - Es un **tema**: subido en Plugins da «No se encontraron plugins».
   - Desde que está activo, abrir `cms.sunqujourneys.com` lleva directo al acceso del panel, y después al escritorio.
5. **Campos:** Plugins › Añadir nuevo › busca **Secure Custom Fields** › Instalar ahora › Activar. Sin este plugin el contenido se importa igual, pero no se puede editar desde el panel.
6. **Enlaces permanentes:** Ajustes › Enlaces permanentes › «Nombre de la entrada». Sin esto, `/wp-json/` no responde y la web no puede leer el contenido.
7. **Ajustes › Lectura:** marca «Disuade a los motores de búsqueda…». El CMS no se indexa; la web tiene su propio SEO.
8. **Plugins que sobran en un CMS headless:** desactiva **Yoast SEO**, que publica sus propios sitemaps del CMS, y **Google Site Kit**. Si no usas el asistente de IA de Hostinger, revisa también su conexión MCP.
9. **Sunqu › Conexión y herramientas:**
   - **URL de la web:** `https://sunqujourneys.com`. Es el único origen al que CORS deja leer la API y al que se avisa al publicar.
   - **Orígenes permitidos:** vacío, para usar solo el de la web. Agrega `https://ferdango.github.io` solo si la vista previa debe leer este WordPress.
   - **Correo de avisos:** ⚠ el buzón que recibe solicitudes, reseñas y reclamaciones.
   - **Token de purga:** el mismo valor que `CACHE_PURGE_TOKEN`.
10. **Importar el contenido:** en la misma pantalla, **Importar el contenido**.
    - Crea journeys, experiencias, anfitriones, reseñas, tienda, Diario, portada y ajustes, y sube las fotos a la biblioteca. Tarda alrededor de un minuto; no cierres la página hasta que diga «Listo».
    - Deja marcado «Pasar a borrador lo publicado que no está…»: así «¡Hola, mundo!» sale del Diario.
    - Si se interrumpe (por ejemplo, porque una foto no se descargó), vuelve a pulsar. Lo ya importado y las fotos ya subidas se reutilizan, sin duplicar nada.
    - Ya importado, volver a pulsar **reemplaza** lo editado en el panel con el contenido del zip. La pantalla lo advierte.
    - **Alternativa por SSH** (WP-CLI incluido en Hostinger):

      ```bash
      cd "$HOME/domains/cms.sunqujourneys.com/public_html"
      DATOS="$PWD/wp-content/plugins/sunqu-cms/datos"
      wp sunqu importar --dir="$DATOS/content" --front="$DATOS" --user=<admin> --podar
      wp sunqu verificar --dir="$DATOS/content" --user=<admin>
      ```

      `verificar` debe terminar con «WordPress devuelve exactamente el contenido de los JSON».
11. **Correo** ⚠: instala **WP Mail SMTP** con una cuenta de correo del dominio (hPanel › Correos). Sin SMTP, los avisos y la constancia del libro de reclamaciones pueden no llegar. Los envíos quedan guardados igual en el panel.
12. **Probar la conexión:** cuando la web esté publicada, Sunqu › Conexión › **Vaciar caché de la web** debe responder «La web vació su caché».
13. **Activar la lectura de WordPress:** en el `.env` de la web pon `WORDPRESS_API_URL=https://cms.sunqujourneys.com/wp-json`. No hace falta reiniciar nada.

**Por qué no un `.sql` para phpMyAdmin:**
- las tablas de cada instalación tienen su propio prefijo;
- los IDs chocarían con los de tu WordPress;
- las fotos no viajan dentro de un `.sql`;
- un error de importación rompe el sitio.

El importador usa las funciones de WordPress, así que no tiene esos problemas y se puede repetir.

## 6. Caché de Hostinger

- **Páginas:** el servidor las marca con `X-LiteSpeed-Cache-Control: public, max-age=300`. Al publicar en WordPress, la purga devuelve `X-LiteSpeed-Purge: *`.
- **Vaciar a mano:** hPanel › Avanzado › Administrador de caché, o el botón del paso 5.12.
- **CDN de Hostinger:** si se activa, conserva los encabezados del servidor. Tras un despliegue grande, purga también la CDN.
  - Las imágenes locales se sirven en AVIF o WebP bajo la **misma URL** `.jpg`/`.png`, según la cabecera `Accept` del navegador, con `Vary: Accept`.
  - Comprueba que la CDN respete `Vary: Accept`: abre una foto de `assets/merch/` en Safari y en Chrome y confirma que se ve en los dos.
  - Si la CDN no lo respeta, desactívala para `/assets/` o borra las reglas «AVIF o WebP» del `.htaccess`. Los originales siguen en su lugar.
- **En el CMS**, si Hostinger instaló *LiteSpeed Cache*, puede quedar activo:
  - la API de contenido le pide que no la guarde y la web la consulta con un parámetro anti-caché;
  - los formularios son POST y no se cachean;
  - verifícalo con `curl -sI "https://cms.sunqujourneys.com/wp-json/sunqu/v1/contenido?secciones=sitio" | grep -i litespeed`, que debe responder `X-LiteSpeed-Cache-Control: no-cache`.
- **Si pones una CDN delante del CMS**, el límite de envíos por IP vería la IP de la CDN. Ajusta el filtro `sunqu_ip_cliente` a la cabecera que envíe esa CDN (WORDPRESS.md › 6).

## 7. Verificación después de publicar

| Prueba | Esperado |
|---|---|
| `http://sunqujourneys.com`, `https://www.sunqujourneys.com/descubre/` | 301 → `https://sunqujourneys.com/descubre` |
| `/journeys/alma`, `/diario/aclimatarte-en-los-andes` | 200, título y canonical correctos (ver código fuente) |
| `/journeys/no-existe` | 404 |
| `/journey.html?j=sabor` | 301 → `/journeys/sabor` |
| `/sitemap.xml`, `/robots.txt` | Con el dominio real |
| `/.env`, `/server/lib/config.php`, `/docs/`, `/wordpress/` | 403 |
| Consola del navegador (F12) en cada página | Sin errores ni avisos de CSP; `SUNQU_ORIGEN` = `servidor` |
| «Diseña tu viaje», «Comparte tu viaje», libro de reclamaciones | Llegan a Sunqu › Solicitudes / Reseñas › Pendientes / Libro de reclamaciones y al correo de avisos |
| Cambiar un texto de un journey en WordPress | Se ve en la web al recargar |
| securityheaders.com, PageSpeed Insights | Cabeceras presentes. Rendimiento: comparar con los valores de la auditoría |
| Search Console ⚠ | Propiedad de dominio verificada y sitemap enviado (SEO.md › 6) |

## 8. Volver atrás

- **Git:** en hPanel › Git despliega el commit anterior, o `git revert` y `push`. El `.env` y la caché no se tocan.
- **Si WordPress falla:** la web sigue sola con la última caché o con `content/*.json`. Para cortar la dependencia, vacía `WORDPRESS_API_URL`. Los formularios mostrarán su mensaje de error con la vía de WhatsApp en lugar de un envío falso.

## 9. Entorno de pruebas (opcional)

Un subdominio `staging.sunqujourneys.com` con el mismo código y `APP_ENV=staging` en su `.env` agrega `noindex`
y `robots.txt` con `Disallow: /`.

En local, `cd wordpress && ./setup.sh` levanta WordPress y la web (servicio `sitio`) con Apache, PHP y el
mismo `.htaccess` que Hostinger. Está en http://localhost:8081.

## 10. Lo que el repositorio no puede hacer por ti

- ⚠ Acceso a Hostinger, DNS del dominio y certificados.
- ⚠ Usuario administrador de WordPress y buzón SMTP.
- ⚠ Cuentas de GA4, GTM y Meta Business, y sus IDs (ANALYTICS.md).
- ⚠ Google Search Console (verificación por DNS).
- ⚠ Datos del negocio: teléfono, redes, RUC, reseñas reales, revisión legal de Términos y cookies, y licencia web de la fuente Aeonik.
