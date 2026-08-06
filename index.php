<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invernadero — Monitoreo</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/date-fns@3.6.0/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<style>
  :root { --bg:#0f172a; --card:#1e293b; --txt:#e2e8f0; --muted:#94a3b8; --border:#334155; }
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'Segoe UI', Arial, sans-serif; background:var(--bg); color:var(--txt); }

  header { padding:16px 20px; background:var(--card); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
  header h1 { font-size:18px; }
  header h1 span { color:#38bdf8; }
  .rango { display:flex; gap:6px; }
  .rango button { background:#334155; color:var(--txt); border:none; padding:7px 14px; border-radius:8px; cursor:pointer; font-size:13px; }
  .rango button.activo { background:#38bdf8; color:#0f172a; font-weight:600; }

  main { padding:16px; max-width:1400px; margin:0 auto; }

  .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:16px; }
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
  .chart { position:relative; height:220px; }

  footer { padding:14px 20px; color:var(--muted); font-size:11px; text-align:center; }

  @media (max-width:900px) {
    .charts-row { grid-template-columns:1fr; }
  }
  @media (max-width:600px) {
    header { flex-direction:column; align-items:flex-start; }
    .stats-grid { grid-template-columns:1fr 1fr; }
    .chart { height:180px; }
  }
</style>
</head>
<body>

<header>
  <h1>🌱 Invernadero <span>IoT</span> — Monitoreo</h1>
  <div class="rango">
    <button data-rango="12h">12 h</button>
    <button data-rango="24h" class="activo">24 h</button>
    <button data-rango="7d">7 días</button>
    <button data-rango="30d">30 días</button>
  </div>
</header>

<main>
  <div id="stats-container" class="stats-grid"></div>
  <div class="charts-row">
    <div class="card"><h2>🌡️ Temperaturas</h2><div class="sub">4 sensores</div><div class="chart"><canvas id="graf-temp"></canvas></div></div>
    <div class="card"><h2>💧 Humedades</h2><div class="sub">3 sensores</div><div class="chart"><canvas id="graf-hum"></canvas></div></div>
  </div>
  <div class="chart-full card"><h2>📉 Tasa de Cambio (°C/h)</h2><div class="sub">Exterior vs Interior — velocidad de variación</div><div class="chart"><canvas id="graf-tasa"></canvas></div></div>
  <div class="chart-full card"><h2>📈 Comparación: Últimas 24h vs 24h Anteriores</h2><div class="sub">Línea sólida = actual | Punteada = anterior</div><div class="chart"><canvas id="graf-comparacion"></canvas></div></div>
  <div class="charts-row">
    <div class="card"><h2>🔗 Temperatura: Exterior vs Interior</h2><div class="sub">¿Qué tan bien mantiene calor?</div><div class="chart"><canvas id="graf-rel-temp"></canvas></div></div>
    <div class="card"><h2>🔗 Humedad: Suelo vs Interior</h2><div class="sub">¿El suelo afecta la humedad del aire?</div><div class="chart"><canvas id="graf-rel-hum"></canvas></div></div>
  </div>
</main>

<footer>Invernadero IoT — Los datos provienen del ESP32 vía la API</footer>

<script>
const COLORES = {
  'Temp Ext':    '#ef4444',
  'Temp alto':   '#f97316',
  'Temp bajo':   '#eab308',
  'Temp suelo':  '#ec4899',
  'Hum alto':    '#3b82f6',
  'Hum bajo':    '#06b6d4',
  'Hum suelo':   '#22c55e',
  'Presion Ext': '#a855f7',
};

const SENSOR_TEMP = ['Temp Ext', 'Temp alto', 'Temp bajo', 'Temp suelo'];
const SENSOR_HUM  = ['Hum alto', 'Hum bajo', 'Hum suelo'];

let graficos = {};
let rangoActual = '24h';

document.querySelectorAll('.rango button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.rango button').forEach(b => b.classList.activo && b.classList.remove('activo'));
    btn.classList.add('activo');
    rangoActual = btn.dataset.rango;
    cargarDatos();
  });
});

function destruirGraficos() {
  Object.values(graficos).forEach(g => g.destroy());
  graficos = {};
}

function crearLinea(ctx, datasets, opts = {}) {
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
            label: ctx => ' ' + ctx.dataset.label + ': ' + (ctx.parsed.y !== null ? ctx.parsed.y.toFixed(1) : '–') + ' ' + (opts.unidad || '')
          }
        }
      },
      scales: {
        x: {
          type: 'time',
          time: { unit: ['7d','30d'].includes(rangoActual) ? 'day' : 'hour', tooltipFormat: 'dd/MM HH:mm' },
          ticks: { color: '#94a3b8', maxTicksLimit: 8 },
          grid: { color: '#334155' }
        },
        y: {
          min: opts.minY !== undefined ? opts.minY : undefined,
          ticks: { color: '#94a3b8' },
          grid: { color: '#334155' }
        }
      },
      ...opts.chartOpts
    }
  });
}

function makeDatasets(series, sensores, extraOpts = {}) {
  return sensores.filter(s => series[s] && series[s].length > 0).map(s => ({
    label: s,
    data: series[s].map(p => ({ x: p[0], y: p[1] })),
    borderColor: COLORES[s] || '#888',
    backgroundColor: (COLORES[s] || '#888') + '20',
    borderWidth: 2,
    pointRadius: 1.5,
    tension: 0.25,
    spanGaps: true,
    ...extraOpts
  }));
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
      <div class="values">
        <span class="range">${st.min}–${st.max}${unidades[s]}</span>
      </div>
      <div class="avg">promedio: ${st.avg}${unidades[s]}</div>
    `;
    cont.appendChild(div);
  });
}

async function cargarDatos() {
  destruirGraficos();
  document.getElementById('stats-container').innerHTML = '<div style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:20px">Cargando datos...</div>';

  const res = await fetch('datos.php?rango=' + rangoActual);
  const data = await res.json();

  if (data.error || !data.series) {
    document.getElementById('stats-container').innerHTML = '<div style="color:#ef4444;grid-column:1/-1;text-align:center;padding:20px">Error al cargar datos</div>';
    return;
  }

  const hayDatos = Object.keys(data.series).length > 0;
  if (!hayDatos) {
    document.getElementById('stats-container').innerHTML = '<div style="color:#94a3b8;grid-column:1/-1;text-align:center;padding:20px">Sin datos para este período</div>';
    return;
  }

  // Stats
  if (data.stats) renderStats(data.stats);

  // Temperaturas
  const ctxT = document.getElementById('graf-temp').getContext('2d');
  graficos.temp = crearLinea(ctxT, makeDatasets(data.series, SENSOR_TEMP), { unidad: '°C' });

  // Humedades
  const ctxH = document.getElementById('graf-hum').getContext('2d');
  graficos.hum = crearLinea(ctxH, makeDatasets(data.series, SENSOR_HUM), { unidad: '%', minY: 0 });

  // Tasa de cambio
  if (data.tasas) {
    const ctxTasa = document.getElementById('graf-tasa').getContext('2d');
    const tasaDatasets = [];
    if (data.tasas['Temp Ext'] !== undefined) {
      tasaDatasets.push({
        label: 'T ext',
        data: [{ x: 0, y: data.tasas['Temp Ext'] }],
        borderColor: COLORES['Temp Ext'],
        backgroundColor: COLORES['Temp Ext'] + '40',
        borderWidth: 3,
        pointRadius: 8,
        showLine: false,
      });
    }
    const intAvg = (data.tasas['Temp alto'] !== undefined && data.tasas['Temp bajo'] !== undefined)
      ? (data.tasas['Temp alto'] + data.tasas['Temp bajo']) / 2
      : (data.tasas['Temp alto'] ?? data.tasas['Temp bajo'] ?? null);
    if (intAvg !== null) {
      tasaDatasets.push({
        label: 'T interior (prom)',
        data: [{ x: 1, y: intAvg }],
        borderColor: COLORES['Temp alto'],
        backgroundColor: COLORES['Temp alto'] + '40',
        borderWidth: 3,
        pointRadius: 8,
        showLine: false,
      });
    }

    graficos.tasa = new Chart(ctxTasa, {
      type: 'bar',
      data: {
        labels: tasaDatasets.map(d => d.label),
        datasets: [{
          data: tasaDatasets.map(d => d.data[0].y),
          backgroundColor: tasaDatasets.map(d => d.borderColor + '80'),
          borderColor: tasaDatasets.map(d => d.borderColor),
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ctx.parsed.y.toFixed(2) + ' °C/h'
            }
          }
        },
        scales: {
          x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
          y: {
            ticks: { color: '#94a3b8', callback: v => v + ' °C/h' },
            grid: { color: '#334155' }
          }
        }
      }
    });
  }

  // Comparación 24h
  if (data.comparacion && Object.keys(data.comparacion).length > 0) {
    const ctxC = document.getElementById('graf-comparacion').getContext('2d');
    const compDatasets = [];

    SENSOR_TEMP.forEach(s => {
      if (data.series[s] && data.series[s].length > 0) {
        compDatasets.push({
          label: s + ' (actual)',
          data: data.series[s].map(p => ({ x: p[0], y: p[1] })),
          borderColor: COLORES[s],
          borderWidth: 2,
          pointRadius: 1,
          tension: 0.25,
          spanGaps: true,
        });
      }
      if (data.comparacion[s] && data.comparacion[s].length > 0) {
        compDatasets.push({
          label: s + ' (anterior)',
          data: data.comparacion[s].map(p => ({ x: p[0], y: p[1] })),
          borderColor: COLORES[s],
          borderWidth: 1.5,
          borderDash: [6, 3],
          pointRadius: 0,
          tension: 0.25,
          spanGaps: true,
        });
      }
    });

    graficos.comp = crearLinea(ctxC, compDatasets, { unidad: '°C' });
  }

  // Relación T ext vs T int
  if (data.series['Temp Ext'] && data.series['Temp alto'] && data.series['Temp bajo']) {
    const ctxRT = document.getElementById('graf-rel-temp').getContext('2d');
    const extPts = data.series['Temp Ext'];
    const intAlto = data.series['Temp alto'];
    const intBajo = data.series['Temp bajo'];

    // Promediar interior por timestamp más cercano
    const intMap = {};
    intAlto.forEach(p => intMap[p[0]] = p[1]);
    intBajo.forEach(p => {
      if (intMap[p[0]] !== undefined) intMap[p[0]] = (intMap[p[0]] + p[1]) / 2;
      else intMap[p[0]] = p[1];
    });

    const scatterData = extPts
      .filter(p => intMap[p[0]] !== undefined)
      .map(p => ({ x: p[1], y: intMap[p[0]] }));

    // Línea de referencia y=x
    const allVals = extPts.map(p => p[1]).concat(Object.values(intMap));
    const minV = Math.min(...allVals) - 1;
    const maxV = Math.max(...allVals) + 1;

    graficos.relTemp = new Chart(ctxRT, {
      type: 'scatter',
      data: {
        datasets: [
          {
            label: 'T ext vs T int',
            data: scatterData,
            backgroundColor: '#f9731680',
            borderColor: '#f97316',
            pointRadius: 3,
          },
          {
            label: 'Referencia (y=x)',
            data: [{ x: minV, y: minV }, { x: maxV, y: maxV }],
            type: 'line',
            borderColor: '#ffffff30',
            borderDash: [5, 5],
            borderWidth: 1,
            pointRadius: 0,
            fill: false,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: ctx => `Ext: ${ctx.parsed.x.toFixed(1)}°C → Int: ${ctx.parsed.y.toFixed(1)}°C`
            }
          }
        },
        scales: {
          x: { title: { display: true, text: 'T exterior (°C)', color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } },
          y: { title: { display: true, text: 'T interior (°C)', color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' } }
        }
      }
    });
  }

  // Relación H suelo vs H int
  if (data.series['Hum suelo'] && (data.series['Hum alto'] || data.series['Hum bajo'])) {
    const ctxRH = document.getElementById('graf-rel-hum').getContext('2d');
    const sueloPts = data.series['Hum suelo'];
    const intAlto = data.series['Hum alto'] || [];
    const intBajo = data.series['Hum bajo'] || [];

    const intMap = {};
    intAlto.forEach(p => intMap[p[0]] = p[1]);
    intBajo.forEach(p => {
      if (intMap[p[0]] !== undefined) intMap[p[0]] = (intMap[p[0]] + p[1]) / 2;
      else intMap[p[0]] = p[1];
    });

    const scatterData = sueloPts
      .filter(p => intMap[p[0]] !== undefined)
      .map(p => ({ x: p[1], y: intMap[p[0]] }));

    const allVals = sueloPts.map(p => p[1]).concat(Object.values(intMap));
    const minV = Math.min(...allVals) - 5;
    const maxV = Math.max(...allVals) + 5;

    graficos.relHum = new Chart(ctxRH, {
      type: 'scatter',
      data: {
        datasets: [
          {
            label: 'H suelo vs H int',
            data: scatterData,
            backgroundColor: '#22c55e80',
            borderColor: '#22c55e',
            pointRadius: 3,
          },
          {
            label: 'Referencia (y=x)',
            data: [{ x: minV, y: minV }, { x: maxV, y: maxV }],
            type: 'line',
            borderColor: '#ffffff30',
            borderDash: [5, 5],
            borderWidth: 1,
            pointRadius: 0,
            fill: false,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { labels: { color: '#e2e8f0', font: { size: 11 } } },
          tooltip: {
            callbacks: {
              label: ctx => `Suelo: ${ctx.parsed.x.toFixed(1)}% → Int: ${ctx.parsed.y.toFixed(1)}%`
            }
          }
        },
        scales: {
          x: { title: { display: true, text: 'H suelo (%)', color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' }, min: 0 },
          y: { title: { display: true, text: 'H interior (%)', color: '#94a3b8' }, ticks: { color: '#94a3b8' }, grid: { color: '#334155' }, min: 0 }
        }
      }
    });
  }
}

cargarDatos();
</script>
</body>
</html>
