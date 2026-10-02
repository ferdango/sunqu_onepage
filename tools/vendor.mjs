// Descarga las librerías de terceros que la web sirve desde su propio dominio (vendor/) y el recorte
// del mapa (assets/geo/peru-vecinos.json). Verifica la integridad (SHA-384) y deja un registro en
// vendor/VERSIONS.json. Se corre una vez, o al actualizar una versión:  node tools/vendor.mjs
import { createHash } from 'node:crypto';
import { writeFileSync, readFileSync } from 'node:fs';
import vm from 'node:vm';

import { fileURLToPath } from 'node:url';
const ROOT = fileURLToPath(new URL('..', import.meta.url));
const support = readFileSync(ROOT + 'support.js', 'utf8');
const sri = (name) => (support.match(new RegExp(`var ${name}_SRI = "([^"]+)"`)) || [])[1];

// React: el mismo archivo y hash que support.js usa desde unpkg
const LIBS = [
	{ file: 'react.production.min.js', url: 'https://unpkg.com/react@18.3.1/umd/react.production.min.js', sri: sri('REACT') },
	{ file: 'react-dom.production.min.js', url: 'https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js', sri: sri('REACT_DOM') },
	// d3 por módulos: solo lo que usa el mapa de la ruta (proyección y curva)
	{ file: 'd3-array.min.js', url: 'https://cdn.jsdelivr.net/npm/d3-array@3.2.4/dist/d3-array.min.js' },
	{ file: 'd3-geo.min.js', url: 'https://cdn.jsdelivr.net/npm/d3-geo@3.1.1/dist/d3-geo.min.js' },
	{ file: 'd3-path.min.js', url: 'https://cdn.jsdelivr.net/npm/d3-path@3.1.0/dist/d3-path.min.js' },
	{ file: 'd3-shape.min.js', url: 'https://cdn.jsdelivr.net/npm/d3-shape@3.2.0/dist/d3-shape.min.js' },
	// Banderas de los campos País y Celular (js/sunqu-campos.js): Windows no trae banderas emoji.
	// country-flag-emoji-polyfill (MIT); gráficos Twemoji © Twitter, Inc. y colaboradores, CC-BY 4.0
	{ file: 'TwemojiCountryFlags.woff2', url: 'https://cdn.jsdelivr.net/npm/country-flag-emoji-polyfill@0.1.10/dist/TwemojiCountryFlags.woff2', sri: 'sha384-khwRTemzZWkDYZ9+Z+cL4KGye7omQSt25J1/RiIDQl66s74qnxoR5tJ2//QPJTDH' },
];
const hash = (buf) => 'sha384-' + createHash('sha384').update(buf).digest('base64');
const get = async (url) => { const r = await fetch(url); if (!r.ok) throw new Error(url + ' → ' + r.status); return Buffer.from(await r.arrayBuffer()); };

const registro = {};
for (const lib of LIBS) {
	const buf = await get(lib.url);
	const h = hash(buf);
	if (lib.sri && lib.sri !== h) throw new Error(`Integridad distinta en ${lib.file}: esperado ${lib.sri}, recibido ${h}`);
	writeFileSync(ROOT + 'vendor/' + lib.file, buf);
	registro[lib.file] = { url: lib.url, integrity: h, bytes: buf.length };
	console.log('✓', lib.file, (buf.length / 1024).toFixed(1) + ' KB', lib.sri ? '(SRI verificado)' : '');
}
writeFileSync(ROOT + 'vendor/VERSIONS.json', JSON.stringify(registro, null, 2) + '\n');

// Mapa: Perú (604) y vecinos (Ecuador 218, Colombia 170, Brasil 076, Bolivia 068, Chile 152) desde world-atlas 50m
const world = JSON.parse((await get('https://cdn.jsdelivr.net/npm/world-atlas@2.0.2/countries-50m.json')).toString());
const topo = (await get('https://cdn.jsdelivr.net/npm/topojson-client@3.1.0/dist/topojson-client.min.js')).toString();
const sandbox = {}; vm.runInNewContext(topo, sandbox);
const all = sandbox.topojson.feature(world, world.objects.countries).features;
const ids = ['604', '218', '170', '076', '068', '152'];
// Coordenadas a 3 decimales (~100 m): imperceptible a la escala del mapa y ~40 % más liviano
const r3 = (c) => (typeof c[0] === 'number' ? [Math.round(c[0] * 1e3) / 1e3, Math.round(c[1] * 1e3) / 1e3] : c.map(r3));
const fc = { type: 'FeatureCollection', fuente: 'world-atlas@2.0.2 countries-50m (Natural Earth, dominio público)', features: all.filter((f) => ids.includes(f.id)).map((f) => ({ type: 'Feature', id: f.id, properties: { name: f.properties.name }, geometry: { type: f.geometry.type, coordinates: r3(f.geometry.coordinates) } })) };
writeFileSync(ROOT + 'assets/geo/peru-vecinos.json', JSON.stringify(fc));
console.log('✓ assets/geo/peru-vecinos.json', fc.features.length, 'países', (JSON.stringify(fc).length / 1024).toFixed(1) + ' KB');
