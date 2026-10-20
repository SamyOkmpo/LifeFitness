// Vista previa ESTÁTICA del sitio para Vercel (ramas de trabajo).
//
// - No ejecuta PHP ni se conecta a la base de datos: toma la plantilla de index.php
//   y la rellena con datos de ejemplo armados con las fotos de v1/imagenes_para_subir.
// - Solo publica la página y los archivos estáticos que usa (imágenes, videos, js/support.js).
//   Nunca copia .php, .sql, .csv ni nada de admin/ o api/.
// - Los formularios no envían nada: cualquier .php, api/ o petición que no sea GET se responde localmente.
//
// Uso: node preview/build.mjs   (genera preview/dist)
// Se puede borrar esta carpeta y vercel.json sin afectar al sitio real.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import crypto from 'node:crypto';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(ROOT, 'preview', 'dist');
const SAMPLE_DIR = 'v1/imagenes_para_subir';
// Ruta pública de las fotos de ejemplo. Empieza por "v1/cotizaciones/" para que el
// código del catálogo no le anteponga "cotizaciones/".
const SAMPLE_PUBLIC = 'v1/cotizaciones/muestra';
const ALLOWED_EXT = new Set(['.webp', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.mp4', '.webm', '.html', '.txt', '.js']);

function fail(msg) {
  console.error('\n[preview] ERROR: ' + msg + '\n');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// 1. Datos de ejemplo
// ---------------------------------------------------------------------------

const CATEGORIES = [
  ['Elípticas', 'elipticas', 'Cardio de bajo impacto', 'assets/cat_eliptica_main.webp'],
  ['Trotadoras', 'trotadoras', 'Caminadoras profesionales', 'assets/cat_trotadora_main.webp'],
  ['Bicicletas Estáticas', 'bicicletas', 'Equipos para rehabilitación', 'assets/cat_bici_main.webp'],
  ['Escaleras', 'escaleras', 'Cardio de alta intensidad', 'assets/cat_escaleras_v3.webp'],
  ['Spinning', 'spinning', 'Clases grupales', 'uploads/spinning.jpeg'],
  ['Máquinas de Pesas', 'pesas', 'Fuerza selectorizada', 'assets/cat_pesas_main.webp'],
  ['Peso Libre', 'pesolibre', 'Placa cargada y banco ajustable', 'assets/cat_peso_libre.webp'],
  ['Circuitos', 'circuitos', 'Fuerza en circuito', 'uploads/circuito.jpeg'],
  ['Entrenamiento Funcional', 'entrenamientofuncional', 'Synrgy360 — Life Fitness', 'uploads/funcional.jpeg'],
  ['Accesorios', 'accesorios', 'Mancuernas, barras y discos', 'assets/cat_accesorios_v3.webp'],
].map(([name, slug, tag, img], i) => ({ id: i + 1, name, slug, tag, img, badge: '0' + (i + 1), pos: i + 1, mostrar_home: 1 }));

const catId = (slug) => CATEGORIES.find((c) => c.slug === slug).id;

// Prefijo del archivo -> marca. Las fotos sin marca reconocible no se usan.
const BRANDS = [
  ['LIFEFITNESS', 'Life Fitness'], ['MATRIX', 'Matrix'], ['G3', 'Matrix'], ['PRECOR', 'Precor'],
  ['FREEMOTION', 'Freemotion'], ['CYBEX', 'Cybex'], ['FULLROM', 'Full ROM'], ['INSIGHT', 'Insight'],
  ['POWERLIFT', 'Powerlift'], ['SCHWINN', 'Schwinn'], ['TOMAHAWK', 'Tomahawk'],
];

function categoryFor(key) {
  const k = key.toUpperCase();
  if (/TREADMILL|TRM|INTEGRITY-T\b/.test(k)) return 'trotadoras';
  if (/IC7|ACPP|SCHWINN|TOMAHAWK/.test(k)) return 'spinning';
  if (/BIKE/.test(k)) return 'bicicletas';
  if (/E5X|ARCTRAINER|INTEGRITY-E\b/.test(k)) return 'elipticas';
  if (/CHRONO/.test(k)) return 'escaleras';
  if (/ROW|STRETCH/.test(k)) return 'entrenamientofuncional';
  if (/JUNGLE/.test(k)) return 'circuitos';
  if (/HR-PRO|OLY|SMITH|SQUAT|DAP|LINEAR/.test(k)) return 'pesolibre';
  return 'pesas';
}

function titleCase(s) {
  return s.split(/\s+/).filter(Boolean).map((w) => (/\d/.test(w) || w.length <= 3 ? w.toUpperCase() : w[0].toUpperCase() + w.slice(1).toLowerCase())).join(' ');
}

function slugFile(s) {
  return s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9.]+/g, '-').replace(/-+/g, '-').toLowerCase();
}

function buildSampleData() {
  const dir = path.join(ROOT, SAMPLE_DIR);
  if (!fs.existsSync(dir)) fail('No existe ' + SAMPLE_DIR);
  const files = fs.readdirSync(dir).filter((f) => /\.(jpe?g|webp|png)$/i.test(f)).sort();

  // Agrupa fotos por modelo: "MATRIX-VERSA-CHEST-01-blanco.jpg" -> "MATRIX-VERSA-CHEST"
  const groups = new Map();
  for (const f of files) {
    const key = f.replace(/\.(jpe?g|webp|png)$/i, '').replace(/-(0\d|\d|visor|main)(-blanco)?$/i, '').replace(/-blanco$/i, '');
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(f);
  }

  // Archivos idénticos usados por 3 modelos o más son marcadores (p. ej. las "-01" de Matrix Versa
  // son el mismo póster): no se usan como foto de producto.
  const hashOf = (f) => crypto.createHash('md5').update(fs.readFileSync(path.join(dir, f))).digest('hex');
  const hashGroups = new Map();
  for (const [key, imgs] of groups) for (const f of imgs) {
    const h = hashOf(f);
    if (!hashGroups.has(h)) hashGroups.set(h, new Set());
    hashGroups.get(h).add(key);
  }
  const isShared = (f) => hashGroups.get(hashOf(f)).size >= 3;

  const products = [];
  const copies = [];
  for (const [key, allImgs] of groups) {
    const upper = key.toUpperCase();
    const brand = BRANDS.find(([p]) => upper.startsWith(p));
    if (!brand) continue;
    const imgs = allImgs.filter((f) => !isShared(f));
    if (!imgs.length) continue;
    const pick = imgs.find((f) => /-01\.(jpe?g|webp|png)$/i.test(f)) || imgs.find((f) => /-01-blanco/i.test(f)) || imgs[0];
    const publicName = slugFile(pick);
    copies.push([path.join(SAMPLE_DIR, pick), path.join(SAMPLE_PUBLIC, publicName)]);

    let model = key.replace(/-/g, ' ');
    for (const [p] of BRANDS) model = model.replace(new RegExp('^' + p + '\\s*', 'i'), '');
    model = model.replace(new RegExp('^' + brand[1] + '\\s*', 'i'), '');
    const family = (model.match(/^(VERSA|INTEGRITY|EPIC|VR3)\b/i) || [])[1];
    const id = products.length + 1;
    products.push({
      id,
      series: brand[1],
      categoria_id: catId(categoryFor(key)),
      pos: id,
      item_no: 'MUESTRA-' + String(id).padStart(3, '0'),
      name: titleCase(model) || titleCase(key),
      price: '0.00',
      set_up_dimension: '',
      weight_stack: '',
      nw: '', gw: '', volume: '',
      img: SAMPLE_PUBLIC + '/' + publicName,
      serie: family ? titleCase(family) : '',
      media_json: [],
    });
  }
  if (products.length < 12) fail('Muy pocos productos de ejemplo (' + products.length + ')');

  const brandNames = [...new Set(products.map((p) => p.series))];
  const marcas = brandNames.map((name, i) => ({ id: i + 1, name, slug: slugFile(name), img: '', pos: i + 1 }));
  const seriesSeen = new Map();
  for (const p of products) if (p.serie) seriesSeen.set(p.serie + '|' + p.categoria_id, { name: p.serie, categoria_id: p.categoria_id });
  const series = [...seriesSeen.values()].map((s, i) => ({ id: i + 1, ...s, pos: i + 1 }));

  return { products, categorias: CATEGORIES, marcas, series, copies };
}

// ---------------------------------------------------------------------------
// 2. Página: plantilla de index.php sin el bloque PHP
// ---------------------------------------------------------------------------

const src = fs.readFileSync(path.join(ROOT, 'index.php'), 'utf8');
const headerEnd = src.indexOf('?>\n');
if (!src.startsWith('<?php') || headerEnd < 0) fail('index.php no tiene la forma esperada (bloque PHP inicial).');
let html = src.slice(headerEnd + 3);

const data = buildSampleData();
const inserts = [
  ['<?php echo $productosJson; ?>', JSON.stringify(data.products)],
  ['<?php echo $categoriasJson; ?>', JSON.stringify(data.categorias)],
  ['<?php echo $marcasJson; ?>', JSON.stringify(data.marcas)],
  ['<?php echo $seriesJson; ?>', JSON.stringify(data.series)],
  ['<?php echo json_encode($dbError ?? null); ?>', 'null'],
];
for (const [tag, value] of inserts) {
  if (!html.includes(tag)) fail('No encontré ' + tag + ' en index.php');
  html = html.split(tag).join(value);
}

// Seguridad: la salida no puede llevar PHP ni nada que parezca una credencial.
if (html.includes('<?')) fail('Quedó código PHP en la página generada.');
if (/\$password|\$username|new PDO|mysql:host|mysql\./i.test(html)) fail('La página generada contiene datos de conexión.');

// Marcas de vista previa: no indexar, aviso visible y formularios sin envío real.
const previewHead = `<meta name="robots" content="noindex, nofollow">
<script>
// Vista previa: los envíos (cotización, contacto) no salen del navegador.
// Se bloquea cualquier .php, cualquier api/ y cualquier petición que no sea GET.
(function () {
  var realFetch = window.fetch ? window.fetch.bind(window) : null;
  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '';
    var method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
    if (method !== 'GET' || /\\.php(\\?|#|$)/i.test(url) || /(^|\\/)api\\//.test(url)) {
      console.info('[vista previa] No se envía:', url);
      return Promise.resolve(new Response(JSON.stringify({ success: true, ok: true, preview: true }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    }
    return realFetch ? realFetch(input, init) : Promise.reject(new Error('fetch no disponible'));
  };
})();
</script>`;
const banner = `<div id="vista-previa" style="background:#111;color:#fff;font:600 12px/1.4 system-ui,sans-serif;text-align:center;padding:6px 12px;letter-spacing:.02em">VISTA PREVIA · datos de ejemplo · sin envíos</div>`;
if (!html.includes('<meta charset="utf-8">')) fail('No encontré <meta charset> en index.php');
html = html.replace('<meta charset="utf-8">', '<meta charset="utf-8">\n' + previewHead);
if (!html.includes('<body>')) fail('No encontré <body> en index.php');
html = html.replace('<body>', '<body>\n' + banner);

// ---------------------------------------------------------------------------
// 3. Archivos estáticos que usa la página (solo los referenciados)
// ---------------------------------------------------------------------------

const refs = new Set(['js/support.js', 'assets/logo.webp']);
for (const m of html.matchAll(/(?:assets|uploads)\/[A-Za-z0-9_\-./]+\.(?:webp|png|jpe?g|gif|svg|mp4|webm)/g)) refs.add(m[0]);
for (const c of data.categorias) if (c.img) refs.add(c.img);

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });

const copy = (from, to) => {
  const dst = path.join(OUT, to);
  fs.mkdirSync(path.dirname(dst), { recursive: true });
  fs.copyFileSync(path.join(ROOT, from), dst);
};
const missing = [];
for (const r of refs) {
  if (r.includes('..')) continue;
  if (fs.existsSync(path.join(ROOT, r))) copy(r, r);
  else missing.push(r);
}
for (const [from, to] of data.copies) copy(from, to);

fs.writeFileSync(path.join(OUT, 'index.html'), html);
fs.writeFileSync(path.join(OUT, 'robots.txt'), 'User-agent: *\nDisallow: /\n');

// Seguridad: solo tipos de archivo permitidos en la salida.
let total = 0, count = 0;
(function walk(d) {
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const p = path.join(d, e.name);
    if (e.isDirectory()) { walk(p); continue; }
    const ext = path.extname(e.name).toLowerCase();
    if (!ALLOWED_EXT.has(ext)) fail('Archivo no permitido en la salida: ' + path.relative(OUT, p));
    if (ext === '.js' && path.relative(OUT, p) !== path.join('js', 'support.js')) fail('JS inesperado en la salida: ' + p);
    total += fs.statSync(p).size; count++;
  }
})(OUT);

console.log(`[preview] ${data.products.length} productos de ejemplo, ${count} archivos, ${(total / 1e6).toFixed(1)} MB -> preview/dist`);
if (missing.length) console.log('[preview] Referenciados pero no están en el repo (se omiten):\n  ' + missing.join('\n  '));
