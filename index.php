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
  :root { --bg:#0f172a; --card:#1e293b; --txt:#e2e8f0; --muted:#94a3b8; --accent:#38bdf8; }
  * { box-sizing:border-box; }
  body { margin:0; font-family:'Segoe UI', Arial, sans-serif; background:var(--bg); color:var(--txt); }
  header { padding:18px 24px; background:var(--card); border-bottom:1px solid #334155; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; }
  header h1 { margin:0; font-size:20px; }
  header h1 span { color:var(--accent); }
  .rango { display:flex; gap:8px; }
  .rango button { background:#334155; color:var(--txt); border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:14px; }
  .rango button.activo { background:var(--accent); color:#0f172a; font-weight:600; }
  .rango button:hover { filter:brightness(1.15); }
  main { padding:24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(420px, 1fr)); gap:20px; }
  .card { background:var(--card); border-radius:12px; padding:16px; border:1px solid #334155; }
  .card h2 { margin:0 0 4px; font-size:16px; }
  .card .sub { color:var(--muted); font-size:12px; margin-bottom:10px; }
  .chart { position:relative; height:260px; }
  #vacio { text-align:center; color:var(--muted); padding:40px; grid-column:1/-1; font-size:15px; }
  footer { padding:16px 24px; color:var(--muted); font-size:12px; }
</style>
</head>
<body>
<header>
  <h1>🌱 Invernadero <span>IoT</span> — Monitoreo</h1>
  <div class="rango">
    <button data-rango="24h" class="activo">24 h</button>
    <button data-rango="7d">7 días</button>
    <button data-rango="30d">30 días</button>
  </div>
</header>

<main id="contenedor">
  <div id="vacio">Cargando datos...</div>
</main>

<footer>Los datos provienen del ESP32 vía la API. Hora local: <span id="zona"></span></footer>

<script>
const CONFIGURACION_GRAFICOS = [
  {
    titulo: 'Temperaturas (°C)',
    sensores: ['Temp bajo', 'Temp alto', 'Temp suelo', 'Temp Ext'],
    color: 'rgba(56,189,248,'
  },
  {
    titulo: 'Humedad del aire (%)',
    sensores: ['Hum bajo', 'Hum alto'],
    color: 'rgba(74,222,128,',
    minY: 0
  },
  {
    titulo: 'Humedad del suelo (%)',
    sensores: ['Hum suelo'],
    color: 'rgba(251,191,36,',
    minY: 0
  },
  {
    titulo: 'Presión atmosférica (hPa)',
    sensores: ['Presion Ext'],
    color: 'rgba(232,121,249,'
  }
];

let grafos = [];
let rangoActual = '24h';

document.querySelectorAll('.rango button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.rango button').forEach(b => b.classList.remove('activo'));
    btn.classList.add('activo');
    rangoActual = btn.dataset.rango;
    cargarDatos();
  });
});

async function cargarDatos() {
  const contenedor = document.getElementById('contenedor');
  contenedor.innerHTML = '<div id="vacio">Cargando datos...</div>';
  grafos.forEach(g => g.destroy());
  grafos = [];

  const res = await fetch('datos.php?rango=' + rangoActual);
  const data = await res.json();
  document.getElementById('zona').textContent = Intl.DateTimeFormat().resolvedOptions().timeZone;

  if (data.error || !data.series) {
    contenedor.innerHTML = '<div id="vacio">Error al leer la base de datos.</div>';
    return;
  }

  const hayDatos = Object.keys(data.series).length > 0;
  if (!hayDatos) {
    contenedor.innerHTML = '<div id="vacio">Todavía no hay datos para este período.<br>El ESP32 debería empezar a enviar lecturas en cuanto tenga el endpoint configurado.</div>';
    return;
  }

  contenedor.innerHTML = '';

  CONFIGURACION_GRAFICOS.forEach((cfg, idx) => {
    const card = document.createElement('div');
    card.className = 'card';
    card.innerHTML = '<h2>' + cfg.titulo + '</h2><div class="sub">' + rangoActual + '</div><div class="chart"><canvas id="graf-' + idx + '"></canvas></div>';
    contenedor.appendChild(card);

    const datasets = [];
    cfg.sensores.forEach((s, i) => {
      const puntos = data.series[s] || [];
      if (puntos.length === 0) return;
      datasets.push({
        label: s,
        data: puntos.map(p => ({ x: p[0], y: p[1] })),
        borderColor: cfg.color + (0.85 - i * 0.2).toString() + ')',
        backgroundColor: cfg.color + '0.1)',
        borderWidth: 2,
        pointRadius: 1.5,
        tension: 0.25,
        spanGaps: true
      });
    });

    const ctx = document.getElementById('graf-' + idx).getContext('2d');
    grafos.push(new Chart(ctx, {
      type: 'line',
      data: { datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { labels: { color: '#e2e8f0' } },
          tooltip: {
            callbacks: {
              title: (items) => items.length ? items[0].parsed.x : '',
              label: (ctx) => ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) + ' ' + (data.unidades[ctx.dataset.label] || '')
            }
          }
        },
        scales: {
          x: {
            type: 'time',
            time: { unit: rangoActual === '24h' ? 'hour' : 'day', tooltipFormat: 'dd/MM HH:mm' },
            ticks: { color: '#94a3b8', maxTicksLimit: 8 },
            grid: { color: '#334155' }
          },
          y: {
            min: cfg.minY !== undefined ? cfg.minY : undefined,
            ticks: { color: '#94a3b8' },
            grid: { color: '#334155' }
          }
        }
      }
    }));
  });
}

cargarDatos();
</script>
</body>
</html>
