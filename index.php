<?php
// index.php - orchestrator
require_once 'includes/format.php';
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

<header class="fixed-header">
  <div class="header-inner">
    <h1>Artemis Space Weather Dashboard</h1>
    <nav>
      <a href="#summary">Summary</a>
      <a href="#flares">Solar Flares</a>
      <a href="#cmes">CMEs</a>
      <a href="#radiation">Radiation</a>
    </nav>
  </div>
</header>

<div class="page-offset"></div>

<main id="app">
  <section id="summary">
    <div class="controls">
      <label for="rangeSelect">Range:</label>
      <select id="rangeSelect" aria-label="Select data range">
        <option value="30">30 days</option>
        <option value="60">60 days</option>
        <option value="90">90 days</option>
      </select>
      <span id="lastUpdated" class="info"></span>
    </div>

    <h2>Space Weather Summary</h2>
    <div id="summaryCards" class="cards">
      <!-- Filled by JS -->
    </div>
  </section>

  <section id="flares">
    <h2>Solar Flares <span class="info-icon" title="Solar flares are bursts of radiation from the Sun.">ℹ</span></h2>
    <p class="section-desc"><?= htmlspecialchars(description_for('flares')) ?></p>
    <canvas id="flareChart" class="chart-box"></canvas>
    <div class="section-controls">
      <button class="toggle-btn" data-target="flaresTable">Show details</button>
    </div>
    <div id="flaresTable" class="table-wrapper collapsed"></div>
  </section>

  <section id="cmes">
    <h2>Coronal Mass Ejections (CMEs) <span class="info-icon" title="CMEs are large expulsions of plasma and magnetic field from the Sun.">ℹ</span></h2>
    <p class="section-desc"><?= htmlspecialchars(description_for('cmes')) ?></p>
    <canvas id="cmeChart" class="chart-box"></canvas>
    <div class="section-controls">
      <button class="toggle-btn" data-target="cmesTable">Show details</button>
    </div>
    <div id="cmesTable" class="table-wrapper collapsed"></div>
  </section>

  <section id="radiation">
    <h2>Radiation Storms <span class="info-icon" title="Radiation storms are increases in energetic particles from the Sun.">ℹ</span></h2>
    <p class="section-desc"><?= htmlspecialchars(description_for('radiation')) ?></p>
    <canvas id="radChart" class="chart-box"></canvas>
    <div class="section-controls">
      <button class="toggle-btn" data-target="radTable">Show details</button>
    </div>
    <div id="radTable" class="table-wrapper collapsed"></div>
  </section>

  <footer>
    Demo uses NASA DONKI API — Data courtesy of NASA/NOAA
  </footer>
</main>

<script src="/public/app.js"></script>
</body>
</html>
