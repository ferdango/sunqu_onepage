# Sunqu Journeys · Accesibilidad (WCAG 2.2 AA)

Qué se implementó, cómo se midió y qué queda. Objetivo: WCAG 2.2 nivel AA, **sin cambiar el diseño**. Los ajustes
de color son mínimos y conservan cada tono de la marca.

---

## 1. Qué se implementó

| Criterio | Implementación |
|---|---|
| 3.1.1 Idioma de la página | `<html lang="es">` en todas las plantillas; el servidor pone `en` o `pt` en `/en/…` y `/pt/…`. El selector de idioma muestra cada idioma en su propia lengua y marca el actual (`aria-pressed` / `aria-checked`) |
| 2.4.1 Evitar bloques | Enlace **«Saltar al contenido»**, primer elemento enfocable de cada página (visible al recibir foco). Lleva a `<main id="contenido" tabindex="-1">` |
| 2.4.2 Titulado de páginas | Título único por página, igual al del SEO |
| 2.1.2 / 2.4.3 Foco en diálogos | Con un diálogo `aria-modal="true"` abierto (menú lateral, idioma, visor de fotos, reel, bolsa, galería, onboarding), el foco no sale de él con Tab ni con Shift+Tab (`js/sunqu-content.js`). Escape y el retorno del foco al cerrar ya existían |
| 1.4.3 Contraste (mínimo) | Ajustes de la tabla 2. Resultado medido: 0 fallos, salvo un componente deshabilitado (exento) |
| 4.1.2 Nombre, función, valor | Botón «Guardados» de Descubre con `aria-label="Guardados (n)"`: en móvil solo mostraba el número. La etiqueta del botón flotante de WhatsApp sale del número real del CMS, no de un texto fijo |
| 3.3.1 / 3.3.2 / 4.1.3 Formularios | Mantienen sus etiquetas, obligatorios y mensajes. El error ya tenía `role="alert"`. Durante el envío llevan `aria-busy` y el isotipo del botón gira (sin animación si se pide movimiento reducido). La tarjeta del formulario es una región `aria-live="polite"`, así se anuncia el mensaje de éxito. En el libro de reclamaciones, el foco pasa a la hoja registrada |
| 2.3.3 / 4.1.2 Guardar (Descubre) | Al tocar el isotipo, una microanimación confirma la acción (salta y se marca al guardar, se encoge al quitar); con «reducir movimiento» no se anima. El estado sigue en `aria-pressed` y en el nombre del botón («Guardar: …» / «Quitar de guardados: …») |
| 2.4.4 Enlace «Diseñado por Wuality» (pie) | Lleva a wuality.agency en una pestaña nueva y su nombre lo dice: «Diseñado por Wuality (se abre en una pestaña nueva)» |
| 4.1.2 / 2.1.1 Campos nuevos (`js/sunqu-campos.js`) | **País** y **prefijo del celular**: patrón *combobox* con lista (`role="combobox"`, `aria-expanded`, `aria-controls`, `aria-activedescendant`, opciones `role="option"` con `aria-selected`); flechas, Enter, Escape y Tab; el `<label>` de la plantilla nombra el campo; si el texto no es un país de la lista, el navegador avisa «Elige un país de la lista». La lista se abre al hacer clic, escribir o con la flecha abajo (no al llegar con Tab). **Mi grupo**: botón con `aria-expanded` que muestra el resumen («1 habitación · 2 huéspedes»); cada −/+ tiene su nombre («Más niños en la habitación 2») y el número cambia en una región `aria-live`; en el mínimo o el máximo el botón se desactiva y el foco pasa al otro; Escape cierra y vuelve al botón |
| Campo trampa anti-spam | Fuera de pantalla, con `aria-hidden="true"` y `tabindex="-1"`: no lo alcanza el teclado ni un lector de pantalla |
| 2.3.3 / 2.2.2 Movimiento | Ya se respetaba `prefers-reduced-motion`: animaciones, loader, onboarding y arte del pie. El aviso de cookies y el giro del botón también lo respetan |
| 1.4.10 Reflow | Sin desplazamiento horizontal en 320, 375, 390, 430, 768, 1024, 1280, 1440 y 1920 px, en las 8 páginas (72 combinaciones medidas) |
| Aviso de cookies | Diálogo no modal, en el orden del documento justo después de «Saltar al contenido». Botones de 44 px, foco visible y reapertura desde «Preferencias de cookies» con el foco en el botón |

## 2. Ajustes de contraste

Medidos sobre los fondos reales de la marca: claro `#e9eef1`, alterno `#dde3e7` y superficies de tarjeta.

| Elemento | Antes | Ahora | Contraste |
|---|---|---|---|
| Texto oliva (antetítulos, enlaces, números) | `#736d47` (4,48 / 4,05:1) | `#676140` | 5,35:1 claro · 4,83:1 alterno |
| Texto sobre botón oliva (`.btn-primary`, paginación, idioma activo, día abierto) | `#e9eef1` (4,48:1) | `#fff` | 5,24:1 |
| Principio 05 «Personalizamos» (fondo oliva) | texto `#e9eef1` | `#ffffff` (contenido: `inicio.json › principios`) | 5,24:1 |
| Gris secundario (opcional, notas, pestañas, índice) | `#7a7a7d` (3,3–3,99:1) | `#626265` | 5,20:1 claro |
| «Destino» tachado, placeholders, número de pestaña inactiva | `#8a8a8d` / `#b0b0b3` (1,85–2,94:1) | `#6a6a6d` | 4,61:1 |
| Antetítulos de fases del journey («Durante», «Después») | `#5c8f00` / `#b87d00` (3,0–3,3:1) | `#4a7300` / `#8f6100` | 4,80 / 4,63:1 |
| Números del índice de Términos | `#f4a700` (1,7:1) | `#8f6100` | 4,63:1 |
| Etiquetas sobre fotos (Descubre, «Medio día») | fondo `rgba(28,28,28,.55)` (hasta 4,29:1) | `rgba(28,28,28,.72)` | ≥ 5,7:1 incluso sobre una foto blanca |

Sin cambios, porque ya cumplían o están exentos: los colores sobre fondos oscuros, el texto grande (≥ 24 px) y los
componentes deshabilitados. El único caso que marca la auditoría es «Adjuntar mis guardados» cuando **no hay
guardados**: el interruptor está deshabilitado, lo que está exento según 1.4.3. La explicación que lo acompaña sí cumple.

## 3. Cómo se midió

Auditoría automática en Chrome, en escritorio (1440) y móvil (390 @2x). Revisa por página:
- contraste real de cada texto visible;
- imágenes sin `alt`;
- botones, enlaces y campos sin nombre;
- títulos, idioma y desbordes.

| | Antes (auditoría inicial) | Después |
|---|---|---|
| Fallos de contraste por página | 2 a 11 | 0 (salvo el componente deshabilitado exento) |
| Imágenes sin `alt` | 0 | 0 |
| Campos sin etiqueta | 0 | 0 |
| Desbordes horizontales | 0 | 0 (en 9 anchos) |

**Pruebas manuales con teclado:**
- Tab lleva primero a «Saltar al contenido» y Enter enfoca `main`.
- Con el menú abierto, 14 Tab y un Shift+Tab siguen dentro del menú.

**Formularios de punta a punta:** los estados de éxito y error se anuncian, y el foco va a la hoja de reclamación.

## 4. Pendiente o fuera de alcance

- **Saltos de nivel de encabezado:** corregidos el 29/09/2026 sin cambiar el diseño (los estilos van por clase). El día del gráfico de altitud del journey pasó de H4 a H3; las tarjetas del Diario y de Descubre, de H3 a H2. Lighthouse ya no marca `heading-order`.
- **Fotos del hero del inicio:** ahora llevan su leyenda como texto alternativo («Valle Sagrado · 2.800 m», …). Antes tenían `alt=""`.
- **Falsos positivos de la herramienta:**
  - los enlaces del menú lateral se reportan «sin nombre» mientras el menú está cerrado (oculto); al abrirlo tienen su texto visible;
  - Lighthouse marca contraste bajo en textos del inicio, el journey y Descubre (p. ej. `#424244` o `#676140` «sobre `#1c1c1c`»). La página cambia el color de fondo al desplazarse entre secciones oscuras y claras, y la herramienta evalúa todo el texto contra el fondo de la primera pantalla. Cuando ese texto está a la vista, su fondo es el claro. Revisado el 02/10/2026.
- **Loader del inicio (~2,3 s):** respeta el movimiento reducido y se muestra una sola vez por sesión. En las visitas siguientes el hero aparece directo.
- **Recomendado antes del lanzamiento:** una prueba con lector de pantalla (VoiceOver o NVDA) en los tres formularios y en el onboarding de Descubre.
