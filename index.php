<?php
require_once 'functions.php';
$flares = fetch_nasa('FLR', ['startDate' => date('Y-m-d', strtotime('-7 days'))]);
$cmes = fetch_nasa('CME', ['startDate' => date('Y-m-d', strtotime('-7 days'))]);
$updated = date('c');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Artemis Space Weather Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header><h1>Artemis Space Weather Dashboard</h1></header>
  <section>
    <p><strong>Last updated:</strong> <?= htmlspecialchars($updated) ?></p>
    <h2>Recent Solar Flares</h2>
    <?php if (isset($flares['error'])): ?>
      <p>Error fetching flares: <?= htmlspecialchars($flares['status'] ?? 'unknown') ?></p>
    <?php else: ?>
      <table>
        <thead><tr><th>Time</th><th>Class</th><th>Active Region</th></tr></thead>
        <tbody>
        <?php foreach ($flares as $f): ?>
          <tr>
            <td><?= htmlspecialchars($f['beginTime'] ?? '') ?></td>
            <td><?= htmlspecialchars($f['classType'] ?? '') ?></td>
            <td><?= htmlspecialchars($f['activeRegionNum'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <footer>Demo uses NASA DONKI API</footer>
</body>
</html>
