<?php
// api.php
// Minimal, robust DONKI proxy + aggregator for the frontend charts.
// - Defensive parsing and logging (raw API data before/after parsing).
// - Safe defaults and fallbacks so client never receives null for arrays.
// - Small caps to avoid accidental huge ranges; respects DONKI defaults (30 days).
//
// Assumptions:
// - includes/fetch.php exposes fetch_nasa($type, $params, $ttlSeconds = 600) and returns decoded JSON (array) or null on failure.
// - includes/charts.php exposes aggregation helpers used below (aggregate_flare_classes, cme_speed_buckets, aggregate_radiation_levels).
// - This file runs under PHP 7.4+ (uses null coalescing and strict checks).
//
// Notes about DONKI (space weather DONKI API):
// - Endpoints accept startDate and endDate in YYYY-MM-DD format; default behavior is last 30 days if omitted.
// - There is no strict documented hard cap on startDate in the public docs, but default examples use 30 days.
// - This proxy accepts 30/60/90 as UI options but allows up to 365 days if explicitly requested (safe upper bound).
// - We log raw and parsed data to the PHP error log for debugging; remove or reduce logging in production.

require_once __DIR__ . '/includes/fetch.php';
require_once __DIR__ . '/includes/charts.php';

// ---- Configuration ----
$DEFAULT_RANGE = 14;
$ALLOWED_UI_RANGES = [7, 14, 30]; // values the UI exposes
$MAX_RANGE_DAYS = 365;             // safety upper bound
$CACHE_TTL = 600;                  // seconds for fetch_nasa caching

// ---- Helpers ----
function iso_date_utc_days_ago(int $days): string {
    // Return YYYY-MM-DD for UTC now minus $days
    $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    return $dt->sub(new DateInterval("P{$days}D"))->format('Y-m-d');
}

function safe_array($v) {
    return (is_array($v) ? $v : []);
}

function safe_assoc($v) {
    // ensure we return an associative array (object-like) for aggregations
    if (is_array($v)) return $v;
    return [];
}

// ---- Input parsing ----
// Accept `range` from query string; default to 30. Allow UI values 30/60/90 but permit up to MAX_RANGE_DAYS.
$rawRange = isset($_GET['range']) ? intval($_GET['range']) : $DEFAULT_RANGE;
if ($rawRange <= 0) $rawRange = $DEFAULT_RANGE;
$range = min($rawRange, $MAX_RANGE_DAYS);

// For UI convenience, keep a canonicalRange that maps to UI options (used for logging/analytics)
$canonicalRange = in_array($rawRange, $ALLOWED_UI_RANGES, true) ? $rawRange : $DEFAULT_RANGE;

// Compute start/end dates (DONKI expects startDate and endDate)
$startDate = iso_date_utc_days_ago($range);
$endDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

// Optional debug flag (allow quick client-driven verbose logs)
$debug = isset($_GET['debug']) && ($_GET['debug'] === '1' || strtolower($_GET['debug']) === 'true');

// ---- Prepare params for DONKI ----
$params = [
    'startDate' => $startDate,
    'endDate'   => $endDate,
];

// ---- Fetch raw data from DONKI via fetch_nasa wrapper ----
// We log the raw responses (before parsing) so we can inspect what DONKI returned.
error_log("API RAW REQUEST range={$range} canonicalRange={$canonicalRange} params=" . json_encode($params));

$rawFlares = null;
$rawCmes   = null;
$rawRads   = null;

try {
    $rawFlares = fetch_nasa('FLR', $params, $CACHE_TTL);
} catch (Throwable $e) {
    error_log("API FETCH ERROR FLR: " . $e->getMessage());
    $rawFlares = null;
}

try {
    $rawCmes = fetch_nasa('CME', $params, $CACHE_TTL);
} catch (Throwable $e) {
    error_log("API FETCH ERROR CME: " . $e->getMessage());
    $rawCmes = null;
}

try {
    $rawRads = fetch_nasa('RBE', $params, $CACHE_TTL);
} catch (Throwable $e) {
    error_log("API FETCH ERROR RBE: " . $e->getMessage());
    $rawRads = null;
}

// Log raw payload summaries (compact)
error_log('API RAW RESPONSE SUMMARY range=' . $range
    . ' flares=' . (is_array($rawFlares) ? count($rawFlares) : 'null')
    . ' cmes='   . (is_array($rawCmes)   ? count($rawCmes)   : (is_null($rawCmes) ? 'null' : 'non-array'))
    . ' rads='   . (is_array($rawRads)   ? count($rawRads)   : 'null')
);

// ---- Defensive parsing and aggregation ----
// Ensure we never return null to the client for arrays; convert null -> [] and log that conversion.
$flares = safe_array($rawFlares);
if ($rawFlares === null) {
    error_log("API PARSE: rawFlares was null; falling back to empty array for range={$range}");
}

$cmes = safe_array($rawCmes);
if ($rawCmes === null) {
    error_log("API PARSE: rawCmes was null; falling back to empty array for range={$range}");
}

$rads = safe_array($rawRads);
if ($rawRads === null) {
    error_log("API PARSE: rawRads was null; falling back to empty array for range={$range}");
}

// Aggregations
$flareAgg = null;
try {
    $flareAgg = is_array($flares) ? aggregate_flare_classes($flares) : null;
} catch (Throwable $e) {
    error_log("API AGGREGATE ERROR flareAgg: " . $e->getMessage());
    $flareAgg = null;
}

$cmeSpeeds = [];
if (is_array($cmes)) {
    foreach ($cmes as $c) {
        // Defensive access: cmeAnalyses may be missing or empty
        if (isset($c['cmeAnalyses']) && is_array($c['cmeAnalyses']) && isset($c['cmeAnalyses'][0]['speed'])) {
            $speed = $c['cmeAnalyses'][0]['speed'];
            // ensure numeric
            if (is_numeric($speed)) $cmeSpeeds[] = (float)$speed;
        }
    }
}
$cmeAgg = null;
try {
    $cmeAgg = cme_speed_buckets($cmeSpeeds);
} catch (Throwable $e) {
    error_log("API AGGREGATE ERROR cmeAgg: " . $e->getMessage());
    $cmeAgg = null;
}

$radAgg = null;
try {
    $radAgg = is_array($rads) ? aggregate_radiation_levels($rads) : null;
} catch (Throwable $e) {
    error_log("API AGGREGATE ERROR radAgg: " . $e->getMessage());
    $radAgg = null;
}

// Ensure aggregation shapes are safe for JSON and client consumption
$flareAgg = safe_assoc($flareAgg);
$cmeAgg   = safe_assoc($cmeAgg);
$radAgg   = safe_assoc($radAgg);

// ---- Debug logging: after parsing and aggregation ----
$logAfter = [
    'range' => $range,
    'startDate' => $startDate,
    'endDate' => $endDate,
    'counts' => [
        'flares' => count($flares),
        'cmes'   => count($cmes),
        'rads'   => count($rads),
    ],
    'flareAgg_keys' => array_values(array_keys($flareAgg)),
    'cmeAgg_keys'   => array_values(array_keys($cmeAgg)),
    'radAgg_keys'   => array_values(array_keys($radAgg)),
];
error_log('API PARSED SUMMARY: ' . json_encode($logAfter));

// If debug flag is set, log the raw payloads (careful: can be large)
if ($debug) {
    error_log('API DEBUG FULL RAW FLARES: ' . json_encode($rawFlares));
    error_log('API DEBUG FULL RAW CMES: '   . json_encode($rawCmes));
    error_log('API DEBUG FULL RAW RADS: '   . json_encode($rawRads));
    error_log('API DEBUG PARSED flareAgg: ' . json_encode($flareAgg));
    error_log('API DEBUG PARSED cmeAgg: '   . json_encode($cmeAgg));
    error_log('API DEBUG PARSED radAgg: '   . json_encode($radAgg));
}

// ---- Response ----
header('Content-Type: application/json; charset=utf-8');
// Avoid aggressive caching for this dynamic endpoint
header('Cache-Control: no-store, must-revalidate');

$response = [
    'range'    => $range,
    'start'    => $startDate,
    'end'      => $endDate,
    'flares'   => $flares,
    'cmes'     => $cmes,
    'rads'     => $rads,
    'flareAgg' => $flareAgg,
    'cmeAgg'   => $cmeAgg,
    'radAgg'   => $radAgg,
];

// Final safety: ensure arrays are arrays (never null)
$response['flares'] = safe_array($response['flares']);
$response['cmes']   = safe_array($response['cmes']);
$response['rads']   = safe_array($response['rads']);
$response['flareAgg'] = safe_assoc($response['flareAgg']);
$response['cmeAgg']   = safe_assoc($response['cmeAgg']);
$response['radAgg']   = safe_assoc($response['radAgg']);

// Output JSON
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit(0);
