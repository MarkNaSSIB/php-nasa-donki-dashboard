// public/app.js - Minimal, defensive charts and UI
// Assumes Chart.js is loaded on the page.

const API = '/api.php';
let currentRange = 30;
const charts = { flare: null, cme: null, rad: null };
const initialized = { flare: false, cme: false, rad: false };
let resizeTimer = null;

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

function ensureCanvas(id, px = 320) {
  const c = $id(id);
  if (!c) return;
  c.style.height = px + 'px';
  c.style.minHeight = px + 'px';
  c.setAttribute('height', px);
  // ensure the canvas has a 2D context before Chart.js uses it
  if (!c.getContext) return;
}

function createBarChart(canvasId, labels, values, color) {
  ensureCanvas(canvasId, 320);
  const ctx = $id(canvasId).getContext('2d');
  // destroy existing
  if (charts[canvasId.replace('Chart','').toLowerCase()]) {
    try { charts[canvasId.replace('Chart','').toLowerCase()].destroy(); } catch(e){}
  }
  // create with responsive initially true to render, then disable auto-resize
  const chart = new Chart(ctx, {
    type: 'bar',
    data: { labels: labels, datasets: [{ label: 'Count', data: values, backgroundColor: color }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 200 },
      plugins: { legend: { display: false } }
    }
  });
  // Prevent Chart.js from continuously auto-resizing after initial render
  try { chart.options.responsive = false; } catch(e){}
  // stabilize once
  try { chart.resize(); chart.update(); } catch(e){}
  return chart;
}

function buildSummary(data) {
  const fl = Array.isArray(data.flares) ? data.flares.length : 0;
  const cm = Array.isArray(data.cmes) ? data.cmes.length : 0;
  const rd = Array.isArray(data.rads) ? data.rads.length : 0;
  $id('summaryCards').innerHTML = `
    <div class="card"><strong>Flares</strong><div class="card-num">${fl}</div></div>
    <div class="card"><strong>CMEs</strong><div class="card-num">${cm}</div></div>
    <div class="card"><strong>Radiation</strong><div class="card-num">${rd}</div></div>
  `;
  $id('lastUpdated').textContent = `Fetched: ${new Date().toISOString().replace('T',' ').replace(/\..+/, ' UTC')}`;
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
    return `<tr><td>${c.startTime||'—'}</td><td>${a.speed||'—'}</td><td>${a.width||'—'}</td></tr>`;
  }).join('');
  return `<table><thead><tr><th>Start</th><th>Speed</th><th>Width</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function rowsForRads(rads) {
  if (!Array.isArray(rads) || rads.length === 0) return '<div class="info">No radiation storms in this range.</div>';
  const rows = rads.slice(0,200).map(r => `<tr><td>${r.eventTime||'—'}</td><td>${r.noaaScale||'—'}</td></tr>`).join('');
  return `<table><thead><tr><th>Event Time</th><th>NOAA Scale</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function updateAllCharts(data) {
  // prepare aggregates defensively
  const flareAgg = data.flareAgg || {};
  const flareLabels = Object.keys(flareAgg);
  const flareValues = safeNumArray(flareLabels.map(k => flareAgg[k] || 0), 100);
  if (!initialized.flare) initialized.flare = true;
  charts.flare = createBarChart('flareChart', flareLabels, flareValues, '#7abaff');

  const cmeAgg = data.cmeAgg || {};
  const cmeLabels = Object.keys(cmeAgg);
  const cmeValues = safeNumArray(cmeLabels.map(k => cmeAgg[k] || 0), 100);
  if (!initialized.cme) initialized.cme = true;
  charts.cme = createBarChart('cmeChart', cmeLabels, cmeValues, '#7abaff');

  const radAgg = data.radAgg || {};
  const radLabels = Object.keys(radAgg);
  const radValues = safeNumArray(radLabels.map(k => radAgg[k] || 0), 100);
  if (!initialized.rad) initialized.rad = true;
  charts.rad = createBarChart('radChart', radLabels, radValues, '#ffcc00');
}

function updateTablesAndSummary(data) {
  buildSummary(data);
  buildTable('flaresTable', rowsForFlares(data.flares));
  buildTable('cmesTable', rowsForCmes(data.cmes));
  buildTable('radTable', rowsForRads(data.rads));
}

async function refresh(range = 30) {
  try {
    const data = await fetchData(range);
    updateAllCharts(data);
    updateTablesAndSummary(data);
  } catch (err) {
    console.error(err);
    $id('summaryCards').innerHTML = `<div class="error">Error: ${err.message}</div>`;
  }
}

// Debounced window resize: manually resize charts (if needed)
function onWindowResize() {
  clearTimeout(resizeTimer);
  resizeTimer = setTimeout(() => {
    Object.values(charts).forEach(ch => { try { if (ch) ch.resize(); } catch(e){} });
  }, 250);
}

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
      const collapsed = target.classList.toggle('collapsed');
      btn.textContent = collapsed ? 'Show details' : 'Hide details';
      // After expand, give layout a moment then resize charts once
      if (!collapsed) setTimeout(() => { Object.values(charts).forEach(ch => { try { if (ch) ch.resize(); } catch(e){} }); }, 200);
    });
  });

  window.addEventListener('resize', onWindowResize);
}

document.addEventListener('DOMContentLoaded', () => {
  setupControls();
  // set baseline canvas heights to avoid transient measurement issues
  ['flareChart','cmeChart','radChart'].forEach(id => ensureCanvas(id, 320));
  refresh(currentRange);
});
