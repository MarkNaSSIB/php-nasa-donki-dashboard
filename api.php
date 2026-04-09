<?php
// api.php
// AJAX endpoint for dynamic updates
require_once 'includes/fetch.php';
require_once 'includes/charts.php';

// Accept range parameter: 30, 60, 90 (default 30)
$allowed = [30, 60, 90];
$range = isset($_GET['range']) ? intval($_GET['range']) : 30;
if (!in_array($range, $allowed)) $range = 30;

$start = date('Y-m-d', strtotime("-{$range} days"));
$params = ['startDate' => $start];

// Fetch data (short cache TTL for API responses)
$flares = fetch_nasa('FLR', $params, 600);
$cmes = fetch_nasa('CME', $params, 600);
$rads = fetch_nasa('RBE', $params, 600);

// Prepare chart aggregates
$flareAgg = is_array($flares) ? aggregate_flare_classes($flares) : null;
$cmeSpeeds = [];
if (is_array($cmes)) {
    foreach ($cmes as $c) {
        if (isset($c['cmeAnalyses'][0]['speed'])) $cmeSpeeds[] = $c['cmeAnalyses'][0]['speed'];
    }
}
$cmeAgg = cme_speed_buckets($cmeSpeeds);
$radAgg = is_array($rads) ? aggregate_radiation_levels($rads) : null;

header('Content-Type: application/json; charset=utf-8');

// debug: log what the server is about to return for CMEs
error_log('API DEBUG range=' . ($range ?? 'null') . ' cmes_is_null=' . (is_null($cmes) ? '1' : '0') . ' cmeAgg_keys=' . json_encode(array_keys((array)$cmeAgg)));


echo json_encode([
    'range' => $range,
    'start' => $start,
    'flares' => $flares,
    'cmes' => $cmes,
    'rads' => $rads,
    'flareAgg' => $flareAgg,
    'cmeAgg' => $cmeAgg,
    'radAgg' => $radAgg,
]);
