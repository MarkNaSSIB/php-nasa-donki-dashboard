<?php
// includes/charts.php
// Aggregation helpers for charts

function aggregate_flare_classes(array $flares): array {
    // Count by leading letter (X, M, C, B, A, other)
    $counts = ['X' => 0, 'M' => 0, 'C' => 0, 'B' => 0, 'A' => 0, 'Other' => 0];
    foreach ($flares as $f) {
        $cls = $f['classType'] ?? '';
        if (!$cls) { $counts['Other']++; continue; }
        $lead = strtoupper(substr($cls, 0, 1));
        if (array_key_exists($lead, $counts)) $counts[$lead]++; else $counts['Other']++;
    }
    return $counts;
}

function cme_speed_buckets(array $speeds): array {
    // Buckets: <500, 500-999, 1000-1499, 1500+
    $buckets = ['<500' => 0, '500-999' => 0, '1000-1499' => 0, '1500+' => 0];
    foreach ($speeds as $s) {
        $s = floatval($s);
        if ($s < 500) $buckets['<500']++;
        elseif ($s < 1000) $buckets['500-999']++;
        elseif ($s < 1500) $buckets['1000-1499']++;
        else $buckets['1500+']++;
    }
    return $buckets;
}

function aggregate_radiation_levels(array $rads): array {
    // Count by noaaScale or radiationLevel if available
    $counts = [];
    foreach ($rads as $r) {
        $scale = $r['noaaScale'] ?? ($r['radiationLevel'] ?? 'Unknown');
        $scale = (string)$scale;
        if (!isset($counts[$scale])) $counts[$scale] = 0;
        $counts[$scale]++;
    }
    // Ensure consistent ordering: S5..S1, Unknown
    ksort($counts);
    return $counts;
}
