// public/app.js
// Handles dynamic fetching, chart updates, collapsible tables, and UI interactions.

const apiEndpoint = '/api.php';
let currentRange = 30;
let charts = {};

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

function updateCharts(data) {
  // Flare chart: aggregated counts by class
  const flareAgg = data.flareAgg || {};
  const flareLabels = Object.keys(flareAgg);
  const flareValues = flareLabels.map(k => flareAgg[k] || 0);
  const flareColors = flareLabels.map(l => {
    if (l === 'X') return '#ff4d4d';
    if (l === 'M') return '#ff9933';
    if (l === 'C') return '#ffff66';
    if (l === 'B' || l === 'A') return '#66ccff';
    return '#888';
  });

  if (charts.flare) charts.flare.destroy();
  charts.flare = new Chart(el('flareChart'), {
    type: 'bar',
    data: { labels: flareLabels, datasets: [{ label: 'Count', data: flareValues, backgroundColor: flareColors }] },
    options: { responsive: true, maintainAspectRatio: false }
  });

  // CME chart: buckets
  const cmeAgg = data.cmeAgg || {};
  const cmeLabels = Object.keys(cmeAgg);
  const cmeValues = cmeLabels.map(k => cmeAgg[k] || 0);
  if (charts.cme) charts.cme.destroy();
  charts.cme = new Chart(el('cmeChart'), {
    type: 'bar',
    data: { labels: cmeLabels, datasets: [{ label: 'Count', data: cmeValues, backgroundColor: '#7abaff' }] },
    options: { responsive: true, maintainAspectRatio: false }
  });

  // Radiation chart
  const radAgg = data.radAgg || {};
  const radLabels = Object.keys(radAgg);
  const radValues = radLabels.map(k => radAgg[k] || 0);
  if (charts.rad) charts.rad.destroy();
  charts.rad = new Chart(el('radChart'), {
    type: 'bar',
    data: { labels: radLabels, datasets: [{ label: 'Count', data: radValues, backgroundColor: '#ffcc00' }] },
    options: { responsive: true, maintainAspectRatio: false }
  });
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
    refresh(currentRange);
  });

  // Collapsible toggles
  document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const target = el(targetId);
      if (!target) return;
      const collapsed = target.classList.toggle('collapsed');
      btn.textContent = collapsed ? 'Show details' : 'Hide details';
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  setupControls();
  refresh(currentRange);
});
