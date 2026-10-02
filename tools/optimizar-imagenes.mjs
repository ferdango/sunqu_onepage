// Genera versiones optimizadas de las imágenes locales, junto al original:
//   assets/merch/polo.jpg  →  assets/merch/polo.jpg.avif  y  assets/merch/polo.jpg.webp
// .htaccess sirve la mejor que acepte el navegador; el HTML no cambia y los originales quedan
// como respaldo. Cada versión se redimensiona al tamaño máximo en que realmente se muestra y solo se
// guarda si pesa menos que el original. También crea los favicons.
//
// Requisitos: macOS (sips, para AVIF), Google Chrome (para WebP) y la web servida en
// http://localhost:8000 (python3 -m http.server 8000 desde la raíz).
// Uso: node tools/optimizar-imagenes.mjs
import { execFileSync, spawn } from 'node:child_process';
import { readdirSync, statSync, writeFileSync, mkdtempSync, copyFileSync, existsSync, unlinkSync } from 'node:fs';
import { join, relative } from 'node:path';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const BASE = process.env.BASE || 'http://localhost:8000/';
const TMP = mkdtempSync(join(tmpdir(), 'sunqu-img-'));

// Tamaño máximo (lado mayor, px) según cómo se usa cada imagen en la web (a 2x)
function maxLado(rel) {
	if (rel === 'assets/diamond-lime.png') return 800;            // también es la máscara del loader
	if (/assets\/diamond-/.test(rel)) return 320;                  // isotipos: iconos de 13–84 px
	if (rel === 'assets/logo-mask.png') return 1000;               // máscara del logo del loader (≤300 px)
	if (/assets\/logo-(light|dark)\.png$/.test(rel)) return 720;   // header y footer (≤180 px)
	if (/assets\/pattern-/.test(rel)) return 1000;
	if (/assets\/catalogo\//.test(rel)) return 1280;
	return 1200;
}

const archivos = [];
(function walk(dir) {
	for (const n of readdirSync(dir)) {
		const p = join(dir, n);
		if (statSync(p).isDirectory()) {
			if (n !== 'og') walk(p); // assets/og: las imágenes para compartir se quedan solo en JPEG (.htaccess)
		} else if (/\.(jpe?g|png)$/i.test(n) && !/^favicon-/.test(n)) archivos.push(p);
	}
})(join(ROOT, 'assets'));

// ── Chrome headless para WebP ───────────────────────────────────────────────
const PORT = 9800 + Math.floor(Math.random() * 100);
const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${TMP}/prof`, '--no-first-run', 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let ws, id = 0; const pending = new Map();
const send = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method, params })); });

try {
	let t; for (let k = 0; k < 50; k++) { try { t = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json(); break; } catch { await sleep(200); } }
	ws = new WebSocket(t.find((x) => x.type === 'page').webSocketDebuggerUrl);
	await new Promise((r) => ws.addEventListener('open', r, { once: true }));
	ws.addEventListener('message', (e) => { const m = JSON.parse(e.data); if (m.id && pending.has(m.id)) { const p = pending.get(m.id); pending.delete(m.id); m.error ? p.rej(new Error(m.error.message)) : p.res(m.result); } });
	await send('Page.enable'); await send('Runtime.enable');
	await send('Page.navigate', { url: BASE + 'robots.txt' }); await sleep(600); // mismo origen que las imágenes

	let antes = 0, despues = 0;
	for (const abs of archivos) {
		const rel = relative(ROOT, abs);
		const orig = statSync(abs).size;
		const lado = maxLado(rel);
		const png = /\.png$/i.test(rel);
		// 1) copia redimensionada (sips conserva la transparencia)
		const tmp = join(TMP, rel.replace(/[\/]/g, '_'));
		copyFileSync(abs, tmp);
		execFileSync('sips', ['-Z', String(lado), tmp], { stdio: 'ignore' });
		// 2) AVIF
		const avif = abs + '.avif';
		execFileSync('sips', ['-s', 'format', 'avif', '-s', 'formatOptions', png ? '70' : '55', tmp, '--out', avif], { stdio: 'ignore' });
		// 3) WebP con el canvas de Chrome (misma dimensión que la copia redimensionada)
		const r = await send('Runtime.evaluate', {
			awaitPromise: true, returnByValue: true,
			expression: `new Promise((ok, ko) => { const im = new Image(); im.onload = () => { const k = Math.min(1, ${lado} / Math.max(im.naturalWidth, im.naturalHeight)); const c = document.createElement('canvas'); c.width = Math.round(im.naturalWidth * k); c.height = Math.round(im.naturalHeight * k); const x = c.getContext('2d'); x.imageSmoothingQuality = 'high'; x.drawImage(im, 0, 0, c.width, c.height); ok(c.toDataURL('image/webp', ${png ? 0.86 : 0.8}).split(',')[1]); }; im.onerror = () => ko('error'); im.src = ${JSON.stringify(BASE + rel)}; })`,
		});
		const webp = abs + '.webp';
		writeFileSync(webp, Buffer.from(r.result.value, 'base64'));
		// 4) solo se queda lo que pesa menos que el original
		for (const f of [avif, webp]) {
			if (statSync(f).size >= orig) { unlinkSync(f); }
		}
		const a = existsSync(avif) ? statSync(avif).size : null, w = existsSync(webp) ? statSync(webp).size : null;
		antes += orig; despues += Math.min(orig, a ?? orig, w ?? orig);
		console.log(`${rel.padEnd(36)} ${(orig / 1024).toFixed(0).padStart(4)} KB → avif ${a ? (a / 1024).toFixed(0) + ' KB' : '—'} · webp ${w ? (w / 1024).toFixed(0) + ' KB' : '—'}`);
	}
	console.log(`\nTotal (mejor formato): ${(antes / 1024).toFixed(0)} KB → ${(despues / 1024).toFixed(0)} KB`);

	// Favicons desde el isotipo lima
	for (const [n, px] of [['favicon-32.png', 32], ['favicon-180.png', 180], ['favicon-512.png', 512]]) {
		const out = join(ROOT, 'assets', n);
		copyFileSync(join(ROOT, 'assets/diamond-lime.png'), out);
		execFileSync('sips', ['-Z', String(px), out], { stdio: 'ignore' });
		console.log('✓', 'assets/' + n);
	}
} finally {
	try { ws.close(); } catch {}
	chrome.kill('SIGTERM');
}
