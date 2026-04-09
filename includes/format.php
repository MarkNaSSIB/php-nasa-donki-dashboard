<?php
// includes/format.php
// Small formatting helpers

function safe($arr, $key, $default = '') {
    if (is_array($arr) && array_key_exists($key, $arr)) {
        return htmlspecialchars((string)$arr[$key]);
    }
    return $default;
}

function format_iso_utc(string $iso = null): string {
    if (empty($iso)) return '—';
    // Some DONKI timestamps include timezone Z; strtotime handles it.
    $ts = strtotime($iso);
    if ($ts === false) return htmlspecialchars($iso);
    return gmdate('Y-m-d H:i', $ts) . ' UTC';
}

// Short human description helper (keeps index.php tidy)
function description_for(string $section): string {
    $map = [
        'flares' => 'Solar flares are sudden bursts of electromagnetic radiation from the Sun. They are classified by intensity (A, B, C, M, X). Strong flares can disrupt radio communications and affect satellites.',
        'cmes' => 'Coronal Mass Ejections (CMEs) are large expulsions of plasma and magnetic field from the Sun\'s corona. Fast or wide CMEs can trigger geomagnetic storms that affect power grids, navigation, and spacecraft.',
        'radiation' => 'Radiation storms are increases in energetic particles (protons, electrons) from the Sun. NOAA uses scales (S1–S5) to indicate severity; higher levels can be hazardous to astronauts and satellites.',
    ];
    return $map[$section] ?? '';
}
