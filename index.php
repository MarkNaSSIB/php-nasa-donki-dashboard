<?php
require_once 'functions.php';

// Fetch data (7‑day window)
$start = date('Y-m-d', strtotime('-7 days'));
$flares = fetch_nasa('FLR', ['startDate' => $start]);
$cmes   = fetch_nasa('CME', ['startDate' => $start]);
$rads   = fetch_nasa('RBE', ['startDate' => $start]);

$updated = date('c');

// Helper to safely extract fields
function safe($arr, $key, $default = '') {
    return isset($arr[$key]) ? htmlspecialchars($arr[$key]) : $default;
}

// Extract flare classes for chart
$flareClasses = [];
if (is_array($flares)) {
    foreach ($flares as $f) {
        $cls = $f['classType'] ?? null;
        if ($cls) {
            $flareClasses[] = $cls;
        }
    }
}

// Extract CME speeds for chart
$cmeSpeeds = [];
if (is_array($cmes)) {
    foreach ($cmes as $c) {
        if (isset($c['cmeAnalyses'][0]['speed'])) {
            $cmeSpeeds[] = $c['cmeAnalyses'][0]['speed'];
        }
    }
}

// Extract radiation storm severity for chart
$radLevels = [];
if (is_array($rads)) {
    foreach ($rads as $r) {
        if (isset($r['radiationLevel'])) {
            $radLevels[] = $r['radiationLevel'];
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Artemis Space Weather Dashboard</title>
  <link rel="stylesheet" href="/public/styles.css">
  <link rel="icon" type="image/png" href="/public/favicon.png">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

<!-- FIXED HEADER WITH NAVIGATION -->
<header class="fixed-header">
  <div class="header-inner">
    <h1>Artemis Space Weather Dashboard</h1>
    <nav>
      <a href="#summary">Summary</a>
      <a href="#flares">Solar Flares</a>
      <a href="#cmes">CMEs</a>
      <a href="#radiation">Radiation Storms</a>
    </nav>
  </div>
</header>

<div class="page-offset"></div>

<!-- SUMMARY SECTION -->
<section id="summary">
  <div class="info">
    <strong>Last Updated:</strong> <?= $updated ?>
  </div>

  <h2>Space Weather Summary (Past 7 Days)</h2>
  <table>
    <thead>
      <tr>
        <th>Event Type</th>
        <th>Count</th>
        <th>Most Recent</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Solar Flares</td>
        <td><?= is_array($flares) ? count($flares) : 0 ?></td>
        <td><?= is_array($flares) && count($flares) ? safe($flares[0], 'beginTime') : '—' ?></td>
      </tr>
      <tr>
        <td>Coronal Mass Ejections (CMEs)</td>
        <td><?= is_array($cmes) ? count($cmes) : 0 ?></td>
        <td><?= is_array($cmes) && count($cmes) ? safe($cmes[0], 'startTime') : '—' ?></td>
      </tr>
      <tr>
        <td>Radiation Storms</td>
        <td><?= is_array($rads) ? count($rads) : 0 ?></td>
        <td><?= is_array($rads) && count($rads) ? safe($rads[0], 'eventTime') : '—' ?></td>
      </tr>
    </tbody>
  </table>
</section>

<!-- SOLAR FLARES -->
<section id="flares">
  <h2>Solar Flares</h2>

  <!-- Chart -->
  <canvas id="flareChart" class="chart-box"></canvas>

  <?php if (!is_array($flares)): ?>
    <div class="error">Error fetching solar flare data.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Begin</th>
          <th>Peak</th>
          <th>End</th>
          <th>Class</th>
          <th>Active Region</th>
          <th>Instruments</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($flares as $f): ?>
          <?php
            $cls = $f['classType'] ?? '';
            $clsLower = strtolower(substr($cls, 0, 1)); // x, m, c, b, a
          ?>
          <tr class="flare-<?= $clsLower ?>">
            <td><?= safe($f, 'beginTime') ?></td>
            <td><?= safe($f, 'peakTime') ?></td>
            <td><?= safe($f, 'endTime') ?></td>
            <td><?= safe($f, 'classType') ?></td>
            <td><?= safe($f, 'activeRegionNum', '—') ?></td>
            <td>
              <?php
                if (isset($f['instruments']) && is_array($f['instruments'])) {
                    $names = array_map(fn($i) => safe($i, 'displayName'), $f['instruments']);
                    echo implode(', ', $names);
                } else echo '—';
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- CMEs -->
<section id="cmes">
  <h2>Coronal Mass Ejections (CMEs)</h2>

  <!-- Chart -->
  <canvas id="cmeChart" class="chart-box"></canvas>

  <?php if (!is_array($cmes)): ?>
    <div class="error">Error fetching CME data.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Start Time</th>
          <th>Speed (km/s)</th>
          <th>Width (°)</th>
          <th>Type</th>
          <th>Source Location</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cmes as $c): ?>
          <tr>
            <td><?= safe($c, 'startTime') ?></td>
            <td><?= isset($c['cmeAnalyses'][0]['speed']) ? safe($c['cmeAnalyses'][0], 'speed') : '—' ?></td>
            <td><?= isset($c['cmeAnalyses'][0]['width']) ? safe($c['cmeAnalyses'][0], 'width') : '—' ?></td>
            <td><?= isset($c['cmeAnalyses'][0]['type']) ? safe($c['cmeAnalyses'][0], 'type') : '—' ?></td>
            <td><?= isset($c['sourceLocation']) ? safe($c, 'sourceLocation') : '—' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<!-- RADIATION STORMS -->
<section id="radiation">
  <h2>Radiation Storms</h2>

  <!-- Chart -->
  <canvas id="radChart" class="chart-box"></canvas>

  <?php if (!is_array($rads)): ?>
    <div class="error">Error fetching radiation storm data.</div>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Event Time</th>
          <th>Radiation Level</th>
          <th>NOAA Scale</th>
          <th>Linked Events</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rads as $r): ?>
          <tr>
            <td><?= safe($r, 'eventTime') ?></td>
            <td><?= safe($r, 'radiationLevel', '—') ?></td>
            <td><?= safe($r, 'noaaScale', '—') ?></td>
            <td>
              <?php
                if (isset($r['linkedEvents']) && is_array($r['linkedEvents'])) {
                    $ids = array_map(fn($e) => safe($e, 'activityID'), $r['linkedEvents']);
                    echo implode(', ', $ids);
                } else echo '—';
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<footer>
  Demo uses NASA DONKI API — Data courtesy of NASA/NOAA
</footer>

<!-- CHART.JS SCRIPTS -->
<script>
const flareClasses = <?= json_encode($flareClasses) ?>;
const cmeSpeeds = <?= json_encode($cmeSpeeds) ?>;
const radLevels = <?= json_encode($radLevels) ?>;

// Flare Chart
new Chart(document.getElementById('flareChart'), {
    type: 'bar',
    data: {
        labels: flareClasses,
        datasets: [{
            label: 'Flare Class',
            data: flareClasses.map(c => parseInt(c.substring(1)) || 1),
            backgroundColor: flareClasses.map(c => {
                if (c.startsWith('X')) return '#ff4d4d';
                if (c.startsWith('M')) return '#ff9933';
                if (c.startsWith('C')) return '#ffff66';
                return '#66ccff';
            })
        }]
    }
});

// CME Speed Chart
new Chart(document.getElementById('cmeChart'), {
    type: 'line',
    data: {
        labels: cmeSpeeds.map((_, i) => i + 1),
        datasets: [{
            label: 'CME Speed (km/s)',
            data: cmeSpeeds,
            borderColor: '#7abaff',
            backgroundColor: 'rgba(122,186,255,0.2)'
        }]
    }
});

// Radiation Chart
new Chart(document.getElementById('radChart'), {
    type: 'bar',
    data: {
        labels: radLevels.map((_, i) => i + 1),
        datasets: [{
            label: 'Radiation Level',
            data: radLevels,
            backgroundColor: '#ffcc00'
        }]
    }
});
</script>

</body>
</html>
