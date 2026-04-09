// public/app.js
// Handles dynamic fetching, chart updates, collapsible tables, and UI interactions.

const apiEndpoint = '/api.php';
let currentRange = 30;
let charts = {};
let _resizeTimer = null;

function el(id) { return document.getElementById(id); }

async function fetchData(range = 30) {
  const url = `${apiEndpoint}?range=${encodeURIComponent(range)}`;
  const res = await fetch(url, { cache: 'no-store' });
  if (!res.ok) throw new Error('Network response was not ok');
  return res.json();
}

function formatTime(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (isNaN(d)) return iso;
  // Format YYYY-MM-DD HH:MM UTC
  const yyyy = d.getUTCFullYear();
  const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(d.getUTCDate()).padStart(2, '0');
  const hh = String(d.getUTCHours()).padStart(2, '0');
  const min = String(d.getUTCMinutes()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd} ${hh}:${min} UTC`;
}

/* ---------- Small helpers added ---------- */

// Ensure canvas has a sane height before Chart.js measures it
function ensureCanvasHeight(id, px = 320) {
  const c = el(id);
  if (!c) return;
  c.setAttribute('height', px);
  c.style.height = px + 'px';
  c.style.minHeight = px + 'px';
}

// Debounced global resize/update to avoid tight loops
function safeResizeAll(delay = 200) {
  clearTimeout(_resizeTimer);
  _resizeTimer = setTimeout(() => {
    Object.values(charts).forEach(ch => {
      try { if (ch) { ch.resize(); ch.update(); } } catch (e) { /* ignore */ }
    });
  }, delay);
}

// Sanitize numeric arrays (coerce, drop NaN, clamp, limit length)
function safeNumbers(arr, opts = {}) {
  const maxLen = opts.maxLen || 1000;
  const minVal = (typeof opts.min === 'number') ? opts.min : -1e9;
  const maxVal = (typeof opts.max === 'number') ? opts.max : 1e9;
  if (!Array.isArray(arr)) return [];
  const out = [];
  for (let i = 0; i < arr.length && out.length < maxLen; i++) {
    const n = Number(arr[i]);
    if (!Number.isFinite(n)) continue;
    out.push(Math.max(minVal, Math.min(maxVal, n)));
  }
  return out;
}

/* ---------- Existing UI builders (unchanged except small sanitization) ---------- */

function buildSummaryCards(data) {
  const cards = [];
  const flaresCount = Array.isArray(data.flares) ? data.flares.length : 0;
  const cmesCount = Array.isArray(data.cmes) ? data.cmes.length : 0;
  const radsCount = Array.isArray(data.rads) ? data.rads.length : 0;
  const last = new Date().toISOString();
  cards.push(`<div class="card"><strong>Flares</strong><div class="card-num">${flaresCount}</div></div>`);
  cards.push(`<div class="card"><strong>CMEs</strong><div class="card-num">${cmesCount}</div></div>`);
  cards.push(`<div class="card"><strong>Radiation</strong><div class="card-num">${radsCount}</div></div>`);
  el('summaryCards').innerHTML = cards.join('');
  el('lastUpdated').textContent = `Fetched: ${new Date().toISOString().replace('T',' ').replace(/\..+/, ' UTC')}`;
}

function buildTableHtmlForFlares(flares) {
  if (!Array.isArray(flares) || flares.length === 0) return '<div class="info">No flares in this range.</div>';
  const rows = flares.map(f => {
    const cls = f.classType || '—';
    const clsLead = (cls && cls.length) ? cls[0].toUpperCase() : 'other';
    const instruments = Array.isArray(f.instruments) ? f.instruments.map(i => i.displayName || '').filter(Boolean).join(', ') : '—';
    return `<tr class="flare-${clsLead.toLowerCase()}">
      <td>${formatTime(f.beginTime)}</td>
      <td>${formatTime(f.peakTime)}</td>
      <td>${formatTime(f.endTime)}</td>
      <td>${cls}</td>
      <td>${f.activeRegionNum || '—'}</td>
      <td>${instruments}</td>
    </tr>`;
  });
  return `<table>
    <thead><tr><th>Begin</th><th>Peak</th><th>End</th><th>Class</th><th>Active Region</th><th>Instruments</th></tr></thead>
    <tbody>${rows.join('')}</tbody></table>`;
}

function buildTableHtmlForCMEs(cmes) {
  if (!Array.isArray(cmes) || cmes.length === 0) return '<div class="info">No CMEs in this range.</div>';
  const rows = cmes.map(c => {
    const analysis = (c.cmeAnalyses && c.cmeAnalyses[0]) ? c.cmeAnalyses[0] : {};
    return `<tr>
      <td>${formatTime(c.startTime)}</td>
      <td>${analysis.speed || '—'}</td>
      <td>${analysis.width || '—'}</td>
      <td>${analysis.type || '—'}</td>
      <td>${c.sourceLocation || '—'}</td>
    </tr>`;
  });
  return `<table>
    <thead><tr><th>Start Time</th><th>Speed (km/s)</th><th>Width (°)</th><th>Type</th><th>Source Location</th></tr></thead>
    <tbody>${rows.join('')}</tbody></table>`;
}

function buildTableHtmlForRads(rads) {
  if (!Array.isArray(rads) || rads.length === 0) return '<div class="info">No radiation storms in this range.</div>';
  const rows = rads.map(r => {
    const linked = Array.isArray(r.linkedEvents) ? r.linkedEvents.map(e => e.activityID || '').filter(Boolean).join(', ') : '—';
    return `<tr>
      <td>${formatTime(r.eventTime)}</td>
      <td>${r.radiationLevel || '—'}</td>
      <td>${r.noaaScale || '—'}</td>
      <td>${linked}</td>
    </tr>`;
  });
  return `<table>
    <thead><tr><th>Event Time</th><th>Radiation Level</th><th>NOAA Scale</th><th>Linked Events</th></tr></thead>
    <tbody>${rows.join('')}</tbody></table>`;
}

/* ---------- Chart updates with minimal, safe changes ---------- */

function updateCharts(data) {
  // Flare chart: aggregated counts by class
  const flareAgg = data.flareAgg || {};
  const flareLabels = Object.keys(flareAgg);
  const flareValues = safeNumbers(flareLabels.map(k => flareAgg[k] || 0), { maxLen: 100 });
  const flareColors = flareLabels.map(l => {
    if (l === 'X') return '#ff4d4d';
    if (l === 'M') return '#ff9933';
    if (l === 'C') return '#ffff66';
    if (l === 'B' || l === 'A') return '#66ccff';
    return '#888';
  });

  // Ensure canvas baseline before Chart.js measures
  ensureCanvasHeight('flareChart', 320);
  if (charts.flare) charts.flare.destroy();
  charts.flare = new Chart(el('flareChart'), {
    type: 'bar',
    data: { labels: flareLabels, datasets: [{ label: 'Count', data: flareValues, backgroundColor: flareColors }] },
    options: { responsive: true, maintainAspectRatio: false }
  });
  // add this right after creating the chart instance
charts.flare.options.responsive = false;


  // CME chart: buckets
  const cmeAgg = data.cmeAgg || {};
  const cmeLabels = Object.keys(cmeAgg);
  const cmeValues = safeNumbers(cmeLabels.map(k => cmeAgg[k] || 0), { maxLen: 100 });
  ensureCanvasHeight('cmeChart', 320);
  if (charts.cme) charts.cme.destroy();
  charts.cme = new Chart(el('cmeChart'), {
    type: 'bar',
    data: { labels: cmeLabels, datasets: [{ label: 'Count', data: cmeValues, backgroundColor: '#7abaff' }] },
    options: { responsive: true, maintainAspectRatio: false }
  });
  charts.cme.options.responsive = false;



  // Radiation chart
  const radAgg = data.radAgg || {};
  const radLabels = Object.keys(radAgg);
  const radValues = safeNumbers(radLabels.map(k => radAgg[k] || 0), { maxLen: 100 });
  ensureCanvasHeight('radChart', 320);
  if (charts.rad) charts.rad.destroy();
  charts.rad = new Chart(el('radChart'), {
    type: 'bar',
    data: { labels: radLabels, datasets: [{ label: 'Count', data: radValues, backgroundColor: '#ffcc00' }] },
    options: { responsive: true, maintainAspectRatio: false }
  });
  charts.rad.options.responsive = false;
  // After creating charts, schedule a safe resize to stabilize layout
  safeResizeAll(150);
}

function updateTables(data) {
  el('flaresTable').innerHTML = buildTableHtmlForFlares(data.flares);
  el('cmesTable').innerHTML = buildTableHtmlForCMEs(data.cmes);
  el('radTable').innerHTML = buildTableHtmlForRads(data.rads);
}

async function refresh(range = 30) {
  try {
    const data = await fetchData(range);
    buildSummaryCards(data);
    updateCharts(data);
    updateTables(data);
  } catch (err) {
    console.error(err);
    el('summaryCards').innerHTML = `<div class="error">Error fetching data: ${err.message}</div>`;
  }
}

function setupControls() {
  // Range selector
  const sel = el('rangeSelect');
  sel.value = String(currentRange);
  sel.addEventListener('change', () => {
    currentRange = parseInt(sel.value, 10);
    // debounce quick changes from user
    clearTimeout(_resizeTimer);
    _resizeTimer = setTimeout(() => refresh(currentRange), 200);
  });

  // Collapsible toggles
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const target = el(targetId);
      if (!target) return;
      const collapsed = target.classList.toggle('collapsed');
      btn.textContent = collapsed ? 'Show details' : 'Hide details';

      // After expanding, give layout a moment then resize charts
      if (!collapsed) {
        setTimeout(() => safeResizeAll(100), 260);
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  setupControls();

  // Give canvases a stable baseline immediately to avoid Chart.js measuring transient sizes
  ['flareChart','cmeChart','radChart'].forEach(id => {
    const c = el(id);
    if (c) {
      c.style.minHeight = '320px';
      c.style.height = '320px';
      c.setAttribute('height', 320);
    }
  });

  refresh(currentRange);
});
