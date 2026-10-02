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
8. **Plugins que sobran en un CMS headless:** desactiva y **borra** Yoast SEO (publica sus propios sitemaps del CMS), Google Site Kit, Hostinger AI Assistant y Hostinger Easy Onboarding. Cada plugin instalado es código más que mantener al día. Quedan Sunqu CMS, Secure Custom Fields y, si lo usas, LiteSpeed Cache.
9. **Sunqu › Conexión y herramientas:**
   - **URL de la web:** `https://sunqujourneys.com`. Es el único origen al que CORS deja leer la API y al que se avisa al publicar.
   - **Orígenes permitidos:** vacío, para usar solo el de la web. Agrega `https://ferdango.github.io` solo si la vista previa debe leer este WordPress.
   - **Avisos de los formularios:** los correos que reciben solicitudes, reseñas y reclamaciones. Vacío = **sunqujourneys@gmail.com**. Aquí también se configuran Telegram y el webhook (opcionales). Ver [FORMULARIOS.md](FORMULARIOS.md).
   - **Token de purga:** el mismo valor que `CACHE_PURGE_TOKEN`. No se vuelve a mostrar: para cambiarlo, pega el nuevo.
   - Sin **URL de la web**, la API solo acepta formularios de `https://sunqujourneys.com`. Complétala igual.
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
11. **Correo** ⚠: crea el buzón `avisos@sunqujourneys.com` (hPanel › Correos), activa SPF/DKIM/DMARC y pega las constantes `SUNQU_SMTP_*` en el `wp-config.php` del CMS ([FORMULARIOS.md › 2](FORMULARIOS.md#2-activar-el-correo-en-producción-unos-15-minutos)). No hace falta WP Mail SMTP. Prueba con **Sunqu › Conexión › Enviar correo de prueba**. Sin SMTP, los avisos y la constancia del libro de reclamaciones pueden no llegar; los envíos quedan guardados igual en el panel.
12. **Probar la conexión:** cuando la web esté publicada, Sunqu › Conexión › **Vaciar caché de la web** debe responder «La web vació su caché».
13. **Seguridad** (el plugin ya cierra XML-RPC, comentarios, la API sin sesión y la enumeración de usuarios, y limita los intentos de acceso):
    - abre **Sunqu › Seguridad** y resuelve lo que esté en rojo o ámbar;
    - 2FA para cada administrador (plugin «Two Factor») y en la cuenta de Hostinger;
    - `wp-config.php` con permisos 600 o 640 y `define('FORCE_SSL_ADMIN', true);`;
    - lista completa en [SECURITY.md › 3 y 4](SECURITY.md#3-wordpress-hardening).
14. **Activar la lectura de WordPress:** en el `.env` de la web pon `WORDPRESS_API_URL=https://cms.sunqujourneys.com/wp-json`. No hace falta reiniciar nada.
15. **Idiomas (opcional):** Plugins › Añadir nuevo › **Loco Translate** › Activar. Luego **Sunqu › Idiomas**: activa inglés y/o portugués, y traduce en **Loco Translate › Plugins › Sunqu CMS › Nuevo idioma** (ubicación **Personalizado**). Marca «Publicar en buscadores» solo cuando la traducción esté completa y revisada. Paso a paso en [WORDPRESS.md › 8.7](WORDPRESS.md).
16. **Medición (opcional):** pega los códigos de GTM, GA4, Meta Pixel u otros en **Sunqu › Scripts y seguimiento** (con «Con permiso de analítica / marketing») o usa los IDs del `.env` de la web, no las dos cosas. Ver [WORDPRESS.md › 8.8](WORDPRESS.md) y [ANALYTICS.md › 7](ANALYTICS.md).

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
- **CDN delante del CMS:** con la CDN de Hostinger, PHP ya recibe la IP real del visitante, así que los límites de envíos y de intentos de acceso funcionan sin cambios. Con otra CDN (p. ej. Cloudflare), ajusta el filtro `sunqu_ip_cliente` a la cabecera que envíe esa CDN (WORDPRESS.md › 6).

## 7. Verificación después de publicar

| Prueba | Esperado |
|---|---|
| `http://sunqujourneys.com`, `https://www.sunqujourneys.com/descubre/` | 301 → `https://sunqujourneys.com/descubre` |
| `/journeys/alma`, `/diario/aclimatarte-en-los-andes` | 200, título y canonical correctos (ver código fuente) |
| `/journeys/no-existe` | 404 |
| `/journey.html?j=sabor` | 301 → `/journeys/sabor` |
| `/sitemap.xml`, `/robots.txt` | Con el dominio real |
| `/server/lib/config.php`, `/docs/SEO.md`, `/wordpress/setup.sh`, `/.git/config`, `/copia.sql` | 404 (no se confirma que existan) |
| `/_sunqu/salud` | `{"ok":true,"contenido":"wordpress"…}` (o `cache`) |
| `cms.sunqujourneys.com/wp-json/wp/v2/users`, `/?author=1`, `/xmlrpc.php` (sin sesión) | 401, redirección al panel y 403: sin nombres de usuario |
| Consola del navegador (F12) en cada página | Sin errores ni avisos de CSP; `SUNQU_ORIGEN` = `servidor` |
| «Diseña tu viaje», «Comparte tu viaje», libro de reclamaciones | Llegan a Sunqu › Solicitudes / Reseñas › Pendientes / Libro de reclamaciones y al correo de avisos |
| Cambiar un texto de un journey en WordPress | Se ve en la web al recargar |
| securityheaders.com, PageSpeed Insights | Cabeceras presentes. Rendimiento: comparar con SEO.md › 0 |
| Código fuente de `/` | Primera etiqueta del `<head>` tras `viewport`: `<meta http-equiv="Content-Security-Policy" …>` con `script-src 'self' 'sha256-…'` (Hostinger reemplaza el encabezado CSP por `upgrade-insecure-requests`; ver SECURITY.md › 7) |
| Search Console ⚠ | Propiedad de dominio verificada y sitemap enviado (SEO.md › 6) |
| Con un idioma activo en Sunqu › Idiomas: `/en`, `/en/journeys/alma` | 200, `<html lang="en">`, selector de idioma visible. Sin «Publicar en buscadores»: `noindex` y sin `hreflang` (SEO.md › 9) |
| Con un script en Sunqu › Scripts y seguimiento | Aparece en el código fuente con su comentario; si pide permiso, solo corre después de «Aceptar» en el aviso de cookies; consola sin bloqueos de CSP |

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
- ⚠ Usuario administrador de WordPress y buzón SMTP (`avisos@sunqujourneys.com`, constantes en `wp-config.php`).
- ⚠ Cuentas de GA4, GTM y Meta Business, y sus IDs o códigos (ANALYTICS.md).
- ⚠ Traducciones al inglés y al portugués en Loco Translate, y su revisión antes de publicarlas en buscadores.
- ⚠ Google Search Console (verificación por DNS).
- ⚠ Datos del negocio: teléfono, redes, RUC, reseñas reales, revisión legal de Términos y cookies, y licencia web de la fuente Aeonik.
- ⚠ Seguridad de las cuentas: 2FA en WordPress y Hostinger, borrar plugins sin uso, activar el escáner de malware, probar una restauración de copia y crear el monitor externo de `/_sunqu/salud` (SECURITY.md).
