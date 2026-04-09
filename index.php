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
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Artemis Space Weather Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>

<body>
<header>
  <h1>Artemis Space Weather Dashboard</h1>
</header>

<section>
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

<section>
  <h2>Recent Solar Flares</h2>
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
          <tr>
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

<section>
  <h2>Recent Coronal Mass Ejections (CMEs)</h2>
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

<section>
  <h2>Recent Radiation Storms</h2>
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

</body>
</html>
