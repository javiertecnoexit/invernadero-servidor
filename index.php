<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invernadero — Monitoreo</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/date-fns@3.6.0/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>
<style>
  :root { --bg:#0f172a; --card:#1e293b; --txt:#e2e8f0; --muted:#94a3b8; --border:#334155; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI', Arial, sans-serif; background:var(--bg); color:var(--txt); }

  header { padding:14px 20px; background:var(--card); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
  header h1 { font-size:18px; }
  header h1 span { color:#38bdf8; }
  .header-actions { display:flex; gap:8px; align-items:center; }
  .rango { display:flex; gap:6px; }
  .rango button { background:#334155; color:var(--txt); border:none; padding:7px 14px; border-radius:8px; cursor:pointer; font-size:13px; }
  .rango button.activo { background:#38bdf8; color:#0f172a; font-weight:600; }
  .btn-icon { background:#334155; color:var(--txt); border:none; padding:7px 12px; border-radius:8px; cursor:pointer; font-size:15px; }
  .btn-icon:hover { background:#475569; }

  main { padding:16px; max-width:1400px; margin:0 auto; }

  .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:16px; }
  .stat-card { background:var(--card); border-radius:10px; padding:14px; border:1px solid var(--border); }
  .stat-card .label { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
  .stat-card .values { display:flex; justify-content:space-between; align-items:baseline; gap:8px; }
  .stat-card .val { font-size:22px; font-weight:700; }
  .stat-card .range { font-size:11px; color:var(--muted); }
  .stat-card .avg { font-size:12px; color:#38bdf8; margin-top:4px; }

  .charts-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
  .chart-full { margin-bottom:16px; }
  .card { background:var(--card); border-radius:10px; padding:14px; border:1px solid var(--border); }
  .card h2 { font-size:14px; margin-bottom:4px; }
  .card .sub { color:var(--muted); font-size:11px; margin-bottom:8px; }
  .chart { position:relative; height:230px; }
  .vacio { position:absolute; inset:0; z-index:5; color:var(--muted); font-size:13px; display:flex; align-items:center; justify-content:center; pointer-events:none; }

  .hora-leyenda { display:flex; align-items:center; gap:8px; margin-top:8px; font-size:11px; color:var(--muted); }
  .hora-gradiente { flex:1; height:10px; border-radius:5px;
    background:linear-gradient(to right, hsl(210,80%,55%) 0%, hsl(128,80%,55%) 25%, hsl(45,80%,55%) 50%, hsl(128,80%,55%) 75%, hsl(210,80%,55%) 100%); }

  /* Modal umbrales */
  .modal-fondo { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:100; align-items:center; justify-content:center; padding:16px; }
  .modal-fondo.abierto { display:flex; }
  .modal { background:var(--card); border:1px solid var(--border); border-radius:12px; width:100%; max-width:520px; max-height:90vh; overflow:auto; padding:18px; }
  .modal h3 { font-size:16px; margin-bottom:14px; }
  .umbral-fila { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); }
  .umbral-fila .info { flex:1; }
  .umbral-fila .info .t { font-size:13px; }
  .umbral-fila .info .d { color:var(--muted); font-size:11px; }
  .umbral-fila input[type=number] { width:90px; background:#0f172a; color:var(--txt); border:1px solid var(--border); border-radius:6px; padding:6px 8px; font-size:13px; }
  .umbral-fila input[type=checkbox] { width:18px; height:18px; accent-color:#38bdf8; }
  .modal .api-key { margin-top:12px; display:flex; gap:8px; }
  .modal .api-key input { flex:1; background:#0f172a; color:var(--txt); border:1px solid var(--border); border-radius:6px; padding:8px 10px; font-size:13px; }
  .modal .botones { display:flex; gap:8px; margin-top:12px; justify-content:flex-end; }
  .modal button { padding:8px 16px; border:none; border-radius:8px; cursor:pointer; font-size:13px; }
  .btn-primario { background:#38bdf8; color:#0f172a; font-weight:600; }
  .btn-secundario { background:#334155; color:var(--txt); }
  .modal .msg { margin-top:10px; font-size:12px; min-height:16px; }
  .msg.ok { color:#22c55e; } .msg.err { color:#ef4444; }

  footer { padding:14px 20px; color:var(--muted); font-size:11px; text-align:center; }

  @media (max-width:900px) {
    .charts-row { grid-template-columns:1fr; }
  }
  @media (max-width:600px) {
    header { flex-direction:column; align-items:flex-start; }
    .stats-grid { grid-template-columns:1fr 1fr; }
    .chart { height:190px; }
  }
</style>
</head>
<body>

<header>
  <h1>🌱 Invernadero <span>IoT</span> — Monitoreo</h1>
  <div class="header-actions">
    <div class="rango">
      <button data-rango="12h">12 h</button>
      <button data-rango="24h" class="activo">24 h</button>
      <button data-rango="7d">7 días</button>
      <button data-rango="30d">30 días</button>
    </div>
    <button class="btn-icon" id="btn-umbrales" title="Configurar umbrales">⚙️</button>
  </div>
</header>

<main>
  <div id="stats-container" class="stats-grid"></div>

  <div class="charts-row">
    <div class="card"><h2>🌡️ Temperaturas</h2><div class="sub" id="sub-temp">Referencias: estrés 10°C · riesgo 5°C · crítico 0°C</div><div class="chart"><canvas id="graf-temp"></canvas></div></div>
    <div class="card"><h2>💧 Humedades</h2><div class="sub">3 sensores</div><div class="chart"><canvas id="graf-hum"></canvas></div></div>
  </div>

  <div class="charts-row">
    <div class="card"><h2>🛡️ Amortiguación térmica</h2><div class="sub">Temp bajo − Temp ext (°C de protección real)</div><div class="chart"><canvas id="graf-amort"></canvas></div></div>
    <div class="card"><h2>⏱️ Horas por zona de riesgo térmico</h2><div class="sub">Temp bajo · ventana nocturna 18:00–08:00</div><div class="chart"><canvas id="graf-zonas"></canvas></div></div>
  </div>

  <div class="charts-row">
    <div class="card"><h2>💨 VPD — Déficit de presión de vapor</h2><div class="sub">Temp alto + Hum alto · banda objetivo 0,4–0,8 kPa</div><div class="chart"><canvas id="graf-vpd"></canvas></div></div>
    <div class="card"><h2>🌧️ Punto de rocío y margen</h2><div class="sub">Temp bajo · Punto de rocío · Margen (eje derecho)</div><div class="chart"><canvas id="graf-rocio"></canvas></div></div>
  </div>

  <div class="charts-row">
    <div class="card"><h2>↕️ Gradiente vertical</h2><div class="sub">Temp alto − Temp bajo (estratificación térmica)</div><div class="chart"><canvas id="graf-grad"></canvas></div></div>
    <div class="card"><h2>📉 Tasa de cambio suavizada (°C/h)</h2><div class="sub">Media móvil · exterior vs interior</div><div class="chart"><canvas id="graf-tasa"></canvas></div></div>
  </div>

  <div class="chart-full card"><h2>🎈 Presión atmosférica</h2><div class="sub">Sensor exterior</div><div class="chart"><canvas id="graf-presion"></canvas></div></div>

  <div class="chart-full card"><h2>📈 Comparación: Últimas 24h vs 24h Anteriores</h2><div class="sub">Línea sólida = actual | Punteada = anterior (temperaturas)</div><div class="chart"><canvas id="graf-comparacion"></canvas></div></div>

  <div class="charts-row">
    <div class="card"><h2>🔗 Temperatura: Exterior vs Interior</h2><div class="sub" id="sub-rel-temp">Color por hora del día</div><div class="chart"><canvas id="graf-rel-temp"></canvas></div>
      <div class="hora-leyenda"><span>0h</span><div class="hora-gradiente"></div><span>12h</span><span style="flex:0">·</span><span style="flex:0">24h</span></div>
    </div>
    <div class="card"><h2>🔗 Humedad: Suelo vs Interior</h2><div class="sub" id="sub-rel-hum">Color por hora del día</div><div class="chart"><canvas id="graf-rel-hum"></canvas></div>
      <div class="hora-leyenda"><span>0h</span><div class="hora-gradiente"></div><span>12h</span><span style="flex:0">·</span><span style="flex:0">24h</span></div>
    </div>
  </div>
</main>

<footer>Invernadero IoT — Café Arabica · Datos del ESP32 vía API · Umbrales configurables</footer>

<!-- Modal umbrales -->
<div class="modal-fondo" id="modal-umbrales">
  <div class="modal">
    <h3>⚙️ Umbrales configurables</h3>
    <div id="lista-umbrales"></div>
    <div class="api-key">
      <input type="password" id="api-key-input" placeholder="Clave de API (para guardar)">
    </div>
    <div class="msg" id="msg-umbrales"></div>
    <div class="botones">
      <button class="btn-secundario" id="btn-cerrar-umbrales">Cancelar</button>
      <button class="btn-primario" id="btn-guardar-umbrales">Guardar</button>
    </div>
  </div>
</div>

<script>
Chart.register(Chart.Annotation);

const COLORES = {
  'Temp Ext':    '#ef4444',
  'Temp alto':   '#f97316',
  'Temp bajo':   '#eab308',
  'Temp suelo':  '#ec4899',
  'Hum alto':    '#3b82f6',
  'Hum bajo':    '#06b6d4',
  'Hum suelo':   '#22c55e',
  'Presion Ext': '#a855f7',
  'VPD':          '#8b5cf6',
  'Punto rocio':  '#0ea5e9',
  'Margen cond.': '#14b8a6',
  'Amortig. termica': '#22d3ee',
  'Gradiente vert.':  '#f472b6',
  'T interior':       '#fb923c',
};

const SENSOR_TEMP = ['Temp Ext', 'Temp alto', 'Temp bajo', 'Temp suelo'];
const SENSOR_HUM  = ['Hum alto', 'Hum bajo', 'Hum suelo'];

const UMBRALES_DEF = {
  buffer_termico_min:  { valor: 3.0,  activo: 1, unidad: '°C',  desc: 'Amortiguación térmica mínima (Temp bajo − Temp ext)' },
  temp_bajo_estres:    { valor: 10.0, activo: 1, unidad: '°C',  desc: 'Estrés fisiológico por frío (Temp bajo)' },
  temp_bajo_riesgo:    { valor: 5.0,  activo: 1, unidad: '°C',  desc: 'Zona de riesgo severo (Temp bajo)' },
  temp_bajo_critico:   { valor: 0.0,  activo: 1, unidad: '°C',  desc: 'Zona crítica / helada (Temp bajo)' },
  vpd_min:             { valor: 0.4,  activo: 1, unidad: 'kPa', desc: 'VPD mínimo (riesgo fúngico)' },
  vpd_max:             { valor: 0.8,  activo: 1, unidad: 'kPa', desc: 'VPD máximo (estrés hídrico)' },
  margen_rocio_min:    { valor: 2.0,  activo: 1, unidad: '°C',  desc: 'Margen de condensación mínimo' },
};

let UMBRALES = JSON.parse(JSON.stringify(UMBRALES_DEF));
let graficos = {};
let rangoActual = '24h';

document.querySelectorAll('.rango button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.rango button').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    rangoActual = btn.dataset.rango;
    cargarDatos();
  });
});

function destruirGraficos() {
  Object.values(graficos).forEach(g => g.destroy());
  graficos = {};
}

function limpiarVacio(canvas) {
  const c = canvas.closest('.chart');
  if (c) c.querySelectorAll('.vacio').forEach(e => e.remove());
}

function vacio(canvas, msg) {
  const c = canvas.closest('.chart');
  if (!c) return;
  c.querySelectorAll('.vacio').forEach(e => e.remove());
  const d = document.createElement('div');
  d.className = 'vacio';
  d.textContent = msg || 'Sin datos';
  c.appendChild(d);
}

function serieData(series, key) {
  return (series[key] || []).map(p => ({ x: p[0], y: p[1] }));
}

function datasetsCrudos(keys, series, opts = {}) {
  const y2Keys = opts.y2 || [];
  return keys.filter(k => series[k] && series[k].length).map(k => {
    const d = serieData(series, k);
    const ds = {
      label: k,
      data: d,
      borderColor: COLORES[k] || '#888',
      backgroundColor: (COLORES[k] || '#888') + '20',
      borderWidth: 2,
      pointRadius: 1.5,
      tension: 0.25,
      spanGaps: true,
      yAxisID: y2Keys.includes(k) ? 'y2' : 'y',
    };
    if (opts.puntoColor) {
      ds.pointRadius = 3;
      ds.pointBackgroundColor = d.map(p => opts.puntoColor(p));
      ds.pointBorderColor = '#00000033';
    }
    return ds;
  });
}

function datasetsDiarios(keys, diario, opts = {}) {
  const out = [];
  const y2Keys = opts.y2 || [];
  keys.forEach(k => {
    const dias = diario[k];
    if (!dias || !dias.length) return;
    const color = COLORES[k] || '#888';
    const baseIdx = out.length;
    out.push({
      label: k + ' (min)', data: dias.map(d => ({ x: d.fecha, y: d.min })),
      borderColor: 'transparent', backgroundColor: 'transparent', pointRadius: 0, borderWidth: 0, tension: 0.25, spanGaps: true,
      yAxisID: y2Keys.includes(k) ? 'y2' : 'y'
    });
    out.push({
      label: k + ' (máx)', data: dias.map(d => ({ x: d.fecha, y: d.max })),
      borderColor: 'transparent', backgroundColor: 'transparent', pointRadius: 0, borderWidth: 0, tension: 0.25, spanGaps: true,
      fill: { target: { datasetIndex: baseIdx }, above: color + '35' },
      yAxisID: y2Keys.includes(k) ? 'y2' : 'y'
    });
    out.push({
      label: k, data: dias.map(d => ({ x: d.fecha, y: d.avg })),
      borderColor: color, backgroundColor: color + '20', borderWidth: 2, pointRadius: 2, tension: 0.25, spanGaps: true,
      yAxisID: y2Keys.includes(k) ? 'y2' : 'y'
    });
  });
  return out;
}

function lineChart(canvas, datasets, opts = {}) {
  const ctx = canvas.getContext('2d');
  const scales = {
    x: {
      type: 'time',
      time: { unit: ['7d', '30d'].includes(rangoActual) ? 'day' : 'hour', tooltipFormat: 'dd/MM HH:mm' },
      ticks: { color: '#94a3b8', maxTicksLimit: 8 },
      grid: { color: '#334155' }
    },
    y: { position: 'left', min: opts.minY, max: opts.maxY, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
  };
  if (opts.y2) scales.y2 = { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#94a3b8' }, min: opts.minY2 };
  return new Chart(ctx, {
    type: 'line',
    data: { datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        tooltip: {
          callbacks: {
            title: items => items.length ? items[0].parsed.x : '',
            label: c => ' ' + c.dataset.label + ': ' + (c.parsed.y !== null ? c.parsed.y.toFixed(1) : '–') + ' ' + (opts.unidad || '')
          }
        },
        annotation: { annotations: opts.annotations || {} }
      },
      scales
    }
  });
}

// --- Ayudas de anotaciones (umbrales) ---
function lineaAnot(y, color, label) {
  return { type: 'line', yMin: y, yMax: y, borderColor: color, borderWidth: 1, borderDash: [6, 4],
           label: { content: label, display: true, position: 'start', color: '#e2e8f0', backgroundColor: '#1e293b', font: { size: 9 } } };
}
function cajaAnot(yMin, yMax, color) {
  return { type: 'box', yMin: yMin, yMax: yMax, backgroundColor: color, borderWidth: 0 };
}

function renderStats(stats) {
  const cont = document.getElementById('stats-container');
  cont.innerHTML = '';
  const orden = ['Temp Ext', 'Temp alto', 'Temp bajo', 'Temp suelo', 'Hum alto', 'Hum bajo', 'Hum suelo', 'Presion Ext'];
  const unidades = { 'Temp Ext':'°C', 'Temp alto':'°C', 'Temp bajo':'°C', 'Temp suelo':'°C', 'Hum alto':'%', 'Hum bajo':'%', 'Hum suelo':'%', 'Presion Ext':'hPa' };
  orden.forEach(s => {
    if (!stats[s]) return;
    const st = stats[s];
    const div = document.createElement('div');
    div.className = 'stat-card';
    div.innerHTML = `
      <div class="label" style="color:${COLORES[s]}">${s}</div>
      <div class="values"><span class="range">${st.min}–${st.max}${unidades[s]}</span></div>
      <div class="avg">promedio: ${st.avg}${unidades[s]}</div>`;
    cont.appendChild(div);
  });
}

function colorHora(h) {
  const a = h <= 12 ? h / 12 : (24 - h) / 12;
  const hue = 210 - a * 165;
  return `hsla(${hue}, 80%, 55%, 0.85)`;
}

function renderScatter(canvas, puntos, xTitulo, yTitulo, xMin, refBase) {
  if (puntos.length < 3) { vacio(canvas, 'Datos insuficientes'); return; }
  const ctx = canvas.getContext('2d');
  const xs = puntos.map(p => p[0]).concat(puntos.map(p => p[1]));
  const minV = Math.min(...xs) - (xMin === 0 ? 0 : 1);
  const maxV = Math.max(...xs) + 1;
  const refLine = [{ x: minV, y: minV }, { x: maxV, y: maxV }];
  return new Chart(ctx, {
    type: 'scatter',
    data: { datasets: [
      { label: 'Datos', data: puntos.map(p => ({ x: p[0], y: p[1] })),
        pointBackgroundColor: puntos.map(p => colorHora(p[2])),
        pointBorderColor: '#ffffff40', pointRadius: 3.5 },
      { label: 'Referencia (y=x)', data: refLine, type: 'line', borderColor: '#ffffff40', borderDash: [5, 5], borderWidth: 1, pointRadius: 0, fill: false }
    ]},
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        tooltip: { callbacks: { label: c => `${xTitulo}: ${c.parsed.x.toFixed(1)} → ${yTitulo}: ${c.parsed.y.toFixed(1)}` } }
      },
      scales: {
        x: { title: { display: true, text: xTitulo, color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' }, min: xMin },
        y: { title: { display: true, text: yTitulo, color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' }, min: xMin }
      }
    }
  });
}

function parear(serieA, serieB) {
  const mapB = {};
  serieB.forEach(p => mapB[p[0]] = p[1]);
  const out = [];
  serieA.forEach(p => {
    if (mapB[p[0]] !== undefined) {
      out.push([p[1], mapB[p[0]], parseInt(p[0].slice(11, 13), 10)]);
    }
  });
  return out;
}

function renderZonas(zonas) {
  const canvas = document.getElementById('graf-zonas');
  if (!zonas || !zonas.horas) { vacio(canvas, 'Sin datos'); return; }
  const horas = zonas.horas;
  const orden = ['normal', 'atencion', 'estres', 'riesgo_severo', 'critico'];
  const nom = { normal: 'Normal', atencion: 'Atención (<15°C)', estres: 'Estrés (<10°C)', riesgo_severo: 'Riesgo severo (<5°C)', critico: 'Crítico (≤0°C)' };
  const col = { normal: '#22c55e', atencion: '#eab308', estres: '#f97316', riesgo_severo: '#ef4444', critico: '#7f1d1d' };
  const datasets = orden.filter(z => horas[z] > 0).map(z => ({
    label: nom[z], data: [horas[z]], backgroundColor: col[z]
  }));
  if (datasets.length === 0) { vacio(canvas, 'Sin datos nocturnos en el período'); return; }
  const ctx = canvas.getContext('2d');
  graficos.zonas = new Chart(ctx, {
    type: 'bar',
    data: { labels: ['Horas (18:00–08:00)'], datasets },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
        tooltip: { callbacks: { label: c => ' ' + c.dataset.label + ': ' + c.parsed.x.toFixed(1) + ' h' } }
      },
      scales: {
        x: { stacked: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
        y: { stacked: true, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
      }
    }
  });
}

async function cargarDatos() {
  destruirGraficos();
  document.querySelectorAll('.chart canvas').forEach(c => limpiarVacio(c));
  document.getElementById('stats-container').innerHTML = '<div style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:20px">Cargando datos...</div>';

  let data;
  try {
    const res = await fetch('datos.php?rango=' + rangoActual);
    data = await res.json();
  } catch (e) {
    document.getElementById('stats-container').innerHTML = '<div style="color:#ef4444;grid-column:1/-1;text-align:center;padding:20px">Error al cargar datos</div>';
    return;
  }

  if (data.error || !data.series) {
    document.getElementById('stats-container').innerHTML = '<div style="color:#ef4444;grid-column:1/-1;text-align:center;padding:20px">Error al cargar datos</div>';
    return;
  }

  const hayDatos = Object.keys(data.series).length > 0;
  if (!hayDatos) {
    document.getElementById('stats-container').innerHTML = '<div style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:20px">Sin datos para este período</div>';
    return;
  }

  const esDiario = ['7d', '30d'].includes(rangoActual) && data.diario && Object.keys(data.diario).length > 0;
  const lineas = (keys, series, opts) => esDiario ? datasetsDiarios(keys, data.diario, opts) : datasetsCrudos(keys, series, opts);

  // Stats
  if (data.stats) renderStats(data.stats);

  // 1. Temperaturas (+ líneas de referencia de umbrales)
  const anotTemp = {};
  if (UMBRALES.temp_bajo_estres.activo)  anotTemp.e = lineaAnot(UMBRALES.temp_bajo_estres.valor,  '#f97316', 'estr');
  if (UMBRALES.temp_bajo_riesgo.activo)  anotTemp.r = lineaAnot(UMBRALES.temp_bajo_riesgo.valor,  '#ef4444', 'riesgo');
  if (UMBRALES.temp_bajo_critico.activo) anotTemp.c = lineaAnot(UMBRALES.temp_bajo_critico.valor, '#7f1d1d', 'crít');
  const dsT = lineas(SENSOR_TEMP, data.series);
  if (dsT.length) graficos.temp = lineChart(document.getElementById('graf-temp'), dsT, { unidad: '°C', annotations: anotTemp });
  else vacio(document.getElementById('graf-temp'));

  // 2. Humedades
  const dsH = lineas(SENSOR_HUM, data.series);
  if (dsH.length) graficos.hum = lineChart(document.getElementById('graf-hum'), dsH, { unidad: '%', minY: 0 });
  else vacio(document.getElementById('graf-hum'));

  // 3. Amortiguación térmica
  const anotAmort = { cero: lineaAnot(0, '#ffffff60', '0') };
  if (UMBRALES.buffer_termico_min.activo) {
    anotAmort.zona = cajaAnot(-50, UMBRALES.buffer_termico_min.valor, '#ef444415');
    anotAmort.lim = lineaAnot(UMBRALES.buffer_termico_min.valor, '#ef4444', 'mín');
  }
  const dsAm = lineas(['Amortig. termica'], data.series);
  if (dsAm.length) graficos.amort = lineChart(document.getElementById('graf-amort'), dsAm, { unidad: '°C', annotations: anotAmort });
  else vacio(document.getElementById('graf-amort'));

  // 4. Horas por zona de riesgo
  renderZonas(data.zonas_riesgo);

  // 5. VPD (banda objetivo + puntos fuera resaltados)
  const anotVpd = {};
  if (UMBRALES.vpd_min.activo && UMBRALES.vpd_max.activo) {
    anotVpd.banda = cajaAnot(UMBRALES.vpd_min.valor, UMBRALES.vpd_max.valor, '#22c55e1f');
    anotVpd.min = lineaAnot(UMBRALES.vpd_min.valor, '#22c55e', 'mín');
    anotVpd.max = lineaAnot(UMBRALES.vpd_max.valor, '#22c55e', 'máx');
  }
  const vpdPuntoColor = UMBRALES.vpd_min.activo && UMBRALES.vpd_max.activo
    ? p => (p.y < UMBRALES.vpd_min.valor || p.y > UMBRALES.vpd_max.valor) ? '#ef4444' : '#8b5cf6'
    : null;
  const dsVpd = datasetsCrudos(['VPD'], data.series, { puntoColor: vpdPuntoColor });
  if (dsVpd.length) graficos.vpd = lineChart(document.getElementById('graf-vpd'), dsVpd, { unidad: 'kPa', annotations: anotVpd });
  else vacio(document.getElementById('graf-vpd'));

  // 6. Punto de rocío y margen (doble eje)
  const anotRocio = {};
  if (UMBRALES.margen_rocio_min.activo) {
    anotRocio.m = { type: 'line', yMin: UMBRALES.margen_rocio_min.valor, yMax: UMBRALES.margen_rocio_min.valor,
                    yScaleID: 'y2', borderColor: '#ef4444', borderWidth: 1, borderDash: [6, 4],
                    label: { content: 'margen mín', display: true, position: 'start', color: '#e2e8f0', backgroundColor: '#1e293b', font: { size: 9 } } };
  }
  const dsRoc = lineas(['Temp bajo', 'Punto rocio', 'Margen cond.'], data.series, { y2: ['Margen cond.'], minY2: 0 });
  if (dsRoc.length) graficos.rocio = lineChart(document.getElementById('graf-rocio'), dsRoc, { unidad: '°C', y2: true, annotations: anotRocio });
  else vacio(document.getElementById('graf-rocio'));

  // 7. Gradiente vertical
  const anotGrad = { cero: lineaAnot(0, '#ffffff60', '0') };
  const dsG = lineas(['Gradiente vert.'], data.series);
  if (dsG.length) graficos.grad = lineChart(document.getElementById('graf-grad'), dsG, { unidad: '°C', annotations: anotGrad });
  else vacio(document.getElementById('graf-grad'));

  // 8. Tasa de cambio suavizada
  const dsTasa = [];
  if (data.tasa_series && data.tasa_series['Temp Ext'] && data.tasa_series['Temp Ext'].length) {
    dsTasa.push({ label: 'T ext', data: serieData(data.tasa_series, 'Temp Ext'), borderColor: COLORES['Temp Ext'], borderWidth: 2, pointRadius: 1, tension: 0.25, spanGaps: true });
  }
  if (data.tasa_series && data.tasa_series['T interior'] && data.tasa_series['T interior'].length) {
    dsTasa.push({ label: 'T interior', data: serieData(data.tasa_series, 'T interior'), borderColor: COLORES['Temp alto'], borderWidth: 2, pointRadius: 1, tension: 0.25, spanGaps: true });
  }
  if (dsTasa.length) graficos.tasa = lineChart(document.getElementById('graf-tasa'), dsTasa, { unidad: '°C/h' });
  else vacio(document.getElementById('graf-tasa'));

  // 9. Presión
  const dsP = lineas(['Presion Ext'], data.series);
  if (dsP.length) graficos.presion = lineChart(document.getElementById('graf-presion'), dsP, { unidad: 'hPa' });
  else vacio(document.getElementById('graf-presion'));

  // 10. Comparación 24h
  if (data.comparacion && Object.keys(data.comparacion).length > 0) {
    const ctxC = document.getElementById('graf-comparacion').getContext('2d');
    const compDatasets = [];
    SENSOR_TEMP.forEach(s => {
      if (data.series[s] && data.series[s].length > 0) {
        compDatasets.push({ label: s + ' (actual)', data: serieData(data.series, s), borderColor: COLORES[s], borderWidth: 2, pointRadius: 1, tension: 0.25, spanGaps: true });
      }
      if (data.comparacion[s] && data.comparacion[s].length > 0) {
        compDatasets.push({ label: s + ' (anterior)', data: data.comparacion[s].map(p => ({ x: p[0], y: p[1] })), borderColor: COLORES[s], borderWidth: 1.5, borderDash: [6, 3], pointRadius: 0, tension: 0.25, spanGaps: true });
      }
    });
    graficos.comp = lineChart(document.getElementById('graf-comparacion'), compDatasets, { unidad: '°C' });
  } else {
    vacio(document.getElementById('graf-comparacion'), 'Sin comparación (solo se muestra en 24h+)');
  }

  // 11. Scatter T ext vs T int (por hora)
  if (data.series['Temp Ext'] && data.series['T interior']) {
    const pts = parear(data.series['Temp Ext'], data.series['T interior']);
    graficos.relTemp = renderScatter(document.getElementById('graf-rel-temp'), pts, 'T exterior (°C)', 'T interior (°C)', undefined);
    const corr = data.correlaciones && data.correlaciones['T ext vs T int'];
    document.getElementById('sub-rel-temp').textContent = corr
      ? `Color por hora · Correlación r = ${corr.r} (n=${corr.n})`
      : 'Color por hora del día';
  } else {
    vacio(document.getElementById('graf-rel-temp'));
  }

  // 12. Scatter H suelo vs H int (por hora)
  if (data.series['Hum suelo'] && data.series['H interior']) {
    const pts = parear(data.series['Hum suelo'], data.series['H interior']);
    graficos.relHum = renderScatter(document.getElementById('graf-rel-hum'), pts, 'H suelo (%)', 'H interior (%)', 0);
    const corr = data.correlaciones && data.correlaciones['H suelo vs H int'];
    document.getElementById('sub-rel-hum').textContent = corr
      ? `Color por hora · Correlación r = ${corr.r} (n=${corr.n})`
      : 'Color por hora del día';
  } else {
    vacio(document.getElementById('graf-rel-hum'));
  }
}

// ================= Umbrales =================
async function cargarUmbrales() {
  try {
    const res = await fetch('umbrales.php');
    const d = await res.json();
    if (d.umbrales) {
      d.umbrales.forEach(u => {
        if (UMBRALES_DEF[u.nombre]) {
          UMBRALES[u.nombre] = { ...UMBRALES_DEF[u.nombre], valor: parseFloat(u.valor), activo: +u.activo };
        }
      });
    }
  } catch (e) { /* usa defaults */ }
}

function abrirModalUmbrales() {
  const lista = document.getElementById('lista-umbrales');
  lista.innerHTML = '';
  Object.entries(UMBRALES_DEF).forEach(([nombre, def]) => {
    const fila = document.createElement('div');
    fila.className = 'umbral-fila';
    fila.innerHTML = `
      <div class="info"><div class="t">${nombre} (${def.unidad})</div><div class="d">${def.desc}</div></div>
      <input type="number" step="0.1" id="umb-${nombre}" value="${UMBRALES[nombre].valor}">
      <input type="checkbox" id="act-${nombre}" ${UMBRALES[nombre].activo ? 'checked' : ''}>`;
    lista.appendChild(fila);
  });
  document.getElementById('api-key-input').value = '';
  document.getElementById('msg-umbrales').textContent = '';
  document.getElementById('modal-umbrales').classList.add('abierto');
}

document.getElementById('btn-umbrales').addEventListener('click', abrirModalUmbrales);
document.getElementById('btn-cerrar-umbrales').addEventListener('click', () => document.getElementById('modal-umbrales').classList.remove('abierto'));
document.getElementById('btn-guardar-umbrales').addEventListener('click', async () => {
  const msg = document.getElementById('msg-umbrales');
  msg.className = 'msg';
  const key = document.getElementById('api-key-input').value.trim();
  if (!key) { msg.textContent = 'Ingresá la clave de API para guardar'; msg.className = 'msg err'; return; }

  const umbrales = Object.keys(UMBRALES_DEF).map(nombre => ({
    nombre,
    valor: parseFloat(document.getElementById('umb-' + nombre).value),
    activo: document.getElementById('act-' + nombre).checked ? 1 : 0
  }));

  try {
    const res = await fetch('umbrales.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-API-Key': key },
      body: JSON.stringify({ umbrales })
    });
    const d = await res.json();
    if (res.ok) {
      msg.textContent = 'Umbrales guardados correctamente';
      msg.className = 'msg ok';
      umbrales.forEach(u => { if (UMBRALES[u.nombre]) UMBRALES[u.nombre].valor = u.valor; UMBRALES[u.nombre].activo = u.activo; });
      setTimeout(() => document.getElementById('modal-umbrales').classList.remove('abierto'), 900);
      cargarDatos();
    } else {
      msg.textContent = d.error || 'Error al guardar';
      msg.className = 'msg err';
    }
  } catch (e) {
    msg.textContent = 'Error de red al guardar';
    msg.className = 'msg err';
  }
});

cargarUmbrales();
cargarDatos();
</script>
</body>
</html>