// public/app.js - Refactored: stable chart sizing, debounced resize, chunk-safe toggles
// Assumes Chart.js is loaded on the page.

const API = '/api.php';
let currentRange = 30;
const charts = { flare: null, cme: null, rad: null };
const initialized = { flare: false, cme: false, rad: false };

// map canvas id -> charts key
const idToKey = {
  flareChart: 'flare',
  cmeChart: 'cme',
  radChart: 'rad'
};

function $id(id) { return document.getElementById(id); }

async function fetchData(range = 30) {
  const url = `${API}?range=${encodeURIComponent(range)}`;
  const res = await fetch(url, { cache: 'no-store' });
  if (!res.ok) throw new Error(`Fetch failed ${res.status}`);
  return res.json();
}

function safeNumArray(arr, maxLen = 1000) {
  if (!Array.isArray(arr)) return [];
  const out = [];
  for (let i = 0; i < arr.length && out.length < maxLen; i++) {
    const n = Number(arr[i]);
    if (!Number.isFinite(n)) continue;
    out.push(n);
  }
  return out;
}

/**
 * Ensure canvas has a reasonable baseline height so Chart.js measurements are stable.
 * Prefer CSS (.chart-wrapper) to control final height; this only sets an initial inline
 * height to avoid transient measurement issues on first render.
 */
function ensureCanvas(id, px = 320) {
  const c = $id(id);
  if (!c) return;
  // Only set inline height if not already constrained by CSS
  if (!c.style.height) {
    c.style.height = px + 'px';
    c.style.minHeight = px + 'px';
  }
  // ensure the canvas has a 2D context before Chart.js uses it
  if (!c.getContext) return;
}

/**
 * Create a bar chart and store it in the charts map.
 * Charts are created with maintainAspectRatio:false so they fill the wrapper.
 */
function createBarChart(canvasId, labels, values, color) {
  ensureCanvas(canvasId, 320);
  const el = $id(canvasId);
  if (!el) return null;
  const ctx = el.getContext('2d');
  const key = idToKey[canvasId] || canvasId;

  // destroy existing chart for this key
  if (charts[key]) {
    try { charts[key].destroy(); } catch (e) { /* ignore */ }
    charts[key] = null;
  }

  const chart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{ label: 'Count', data: values, backgroundColor: color }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 200 },
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { color: '#cfeeff' } },
        y: { ticks: { color: '#cfeeff' }, beginAtZero: true }
      }
    }
  });

  // Stabilize once after initial render
  try { chart.resize(); chart.update(); } catch (e) { /* ignore */ }

  charts[key] = chart;
  return chart;
}

function buildSummary(data) {
  const fl = Array.isArray(data.flares) ? data.flares.length : 0;
  const cm = Array.isArray(data.cmes) ? data.cmes.length : 0;
  const rd = Array.isArray(data.rads) ? data.rads.length : 0;
  const container = $id('summaryCards');
  if (container) {
    container.innerHTML = `
      <div class="card"><strong>Flares</strong><div class="card-num">${fl}</div></div>
      <div class="card"><strong>CMEs</strong><div class="card-num">${cm}</div></div>
      <div class="card"><strong>Radiation</strong><div class="card-num">${rd}</div></div>
    `;
  }
  const lu = $id('lastUpdated');
  if (lu) lu.textContent = `Fetched: ${new Date().toISOString().replace('T',' ').replace(/\..+/, ' UTC')}`;
}

function buildTable(id, rowsHtml) {
  const wrapper = $id(id);
  if (!wrapper) return;
  wrapper.innerHTML = rowsHtml || '<div class="info">No data</div>';
}

function rowsForFlares(flares) {
  if (!Array.isArray(flares) || flares.length === 0) return '<div class="info">No flares in this range.</div>';
  const rows = flares.slice(0,200).map(f => {
    const cls = f.classType || '—';
    return `<tr><td>${f.beginTime||'—'}</td><td>${f.peakTime||'—'}</td><td>${cls}</td><td>${f.activeRegionNum||'—'}</td></tr>`;
  }).join('');
  return `<table><thead><tr><th>Begin</th><th>Peak</th><th>Class</th><th>Region</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function rowsForCmes(cmes) {
  if (!Array.isArray(cmes) || cmes.length === 0) return '<div class="info">No CMEs in this range.</div>';
  const rows = cmes.slice(0,200).map(c => {
    const a = (c.cmeAnalyses && c.cmeAnalyses[0]) || {};
    return `<tr><td>${c.startTime||'—'}</td><td>${a.speed||'—'}</td><td>${a.halfAngle||a.width||'—'}</td></tr>`;
  }).join('');
  return `<table><thead><tr><th>Start</th><th>Speed</th><th>Width</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function rowsForRads(rads) {
  if (!Array.isArray(rads) || rads.length === 0) return '<div class="info">No radiation storms in this range.</div>';
  const rows = rads.slice(0,200).map(r => `<tr><td>${r.eventTime||'—'}</td><td>${r.noaaScale||'—'}</td></tr>`).join('');
  return `<table><thead><tr><th>Event Time</th><th>NOAA Scale</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function updateAllCharts(data) {
  // flares
  const flareAgg = data.flareAgg || {};
  const flareLabels = Object.keys(flareAgg);
  const flareValues = safeNumArray(flareLabels.map(k => flareAgg[k] || 0), 100);
  charts.flare = createBarChart('flareChart', flareLabels, flareValues, '#7abaff');

  // cmes
  const cmeAgg = data.cmeAgg || {};
  const cmeLabels = Object.keys(cmeAgg);
  const cmeValues = safeNumArray(cmeLabels.map(k => cmeAgg[k] || 0), 100);
  charts.cme = createBarChart('cmeChart', cmeLabels, cmeValues, '#7abaff');

  // radiation
  const radAgg = data.radAgg || {};
  const radLabels = Object.keys(radAgg);
  const radValues = safeNumArray(radLabels.map(k => radAgg[k] || 0), 100);
  charts.rad = createBarChart('radChart', radLabels, radValues, '#ffcc00');
}

function updateTablesAndSummary(data) {
  buildSummary(data);
  buildTable('flaresTable', rowsForFlares(data.flares));
  buildTable('cmesTable', rowsForCmes(data.cmes));
  buildTable('radTable', rowsForRads(data.rads));
}

// Debounced safe resize for charts
function debounce(fn, wait) {
  let t;
  return function(...args) {
    clearTimeout(t);
    t = setTimeout(() => fn.apply(this, args), wait);
  };
}

const safeResize = debounce(() => {
  try {
    Object.values(charts).forEach(ch => {
      if (ch && typeof ch.resize === 'function') {
        ch.resize();
        ch.update();
      }
    });
  } catch (e) {
    console.error('safeResize error', e);
  }
}, 120);

async function refresh(range = 30) {
  try {
    const data = await fetchData(range);
    updateAllCharts(data);
    updateTablesAndSummary(data);
  } catch (err) {
    console.error(err);
    const sc = $id('summaryCards');
    if (sc) sc.innerHTML = `<div class="error">Error: ${err.message}</div>`;
  }
}

// Setup controls and toggles
function setupControls() {
  const sel = $id('rangeSelect');
  if (sel) {
    sel.value = String(currentRange);
    sel.addEventListener('change', () => {
      currentRange = parseInt(sel.value, 10) || 30;
      refresh(currentRange);
    });
  }

  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const target = $id(targetId);
      if (!target) return;
      const isOpen = target.classList.toggle('open');
      // keep collapsed class for legacy compatibility
      target.classList.toggle('collapsed', !isOpen);
      btn.textContent = isOpen ? 'Hide details' : 'Show details';
      // After expand, give layout a moment then resize charts once
      safeResize();
    });
  });

  // Observe chart-wrapper size changes and debounce resize
  if (window.ResizeObserver) {
    document.querySelectorAll('.chart-wrapper').forEach(wrapper => {
      const ro = new ResizeObserver(debounce(() => safeResize(), 80));
      ro.observe(wrapper);
    });
  }

  // Window resize fallback
  window.addEventListener('resize', debounce(() => safeResize(), 200));
}

document.addEventListener('DOMContentLoaded', () => {
  setupControls();
  // set baseline canvas heights to avoid transient measurement issues
  ['flareChart','cmeChart','radChart'].forEach(id => ensureCanvas(id, 320));
  refresh(currentRange);
});
