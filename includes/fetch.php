<?php
// includes/fetch.php
// Responsible for fetching NASA DONKI data with simple file caching.
// Usage: fetch_nasa($type, $params = [], $cacheTtlSeconds = 3600)

function cache_get_path(string $key): string {
    $dir = __DIR__ . '/../.cache';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir . '/' . preg_replace('/[^a-z0-9_\-\.]/i', '_', $key) . '.json';
}

function cache_get(string $key, int $ttl = 3600) {
    $path = cache_get_path($key);
    if (!file_exists($path)) return null;
    $age = time() - filemtime($path);
    if ($age > $ttl) return null;
    $raw = file_get_contents($path);
    return json_decode($raw, true);
}

function cache_set(string $key, $data) {
    $path = cache_get_path($key);
    file_put_contents($path, json_encode($data), LOCK_EX);
}

function fetch_nasa(string $feed, array $params = [], int $cacheTtlSeconds = 900) {
    // feed: FLR, CME, RBE, etc.
    // params: startDate => YYYY-MM-DD, endDate => YYYY-MM-DD
    $apiBase = 'https://api.nasa.gov/DONKI/';
    $apiKey = getenv('NASA_API_KEY') ?: 'DEMO_KEY';

    // Build query string
    $qs = http_build_query(array_merge($params, ['api_key' => $apiKey]));
    $url = rtrim($apiBase, '/') . '/' . rawurlencode($feed) . '?' . $qs;

    // Cache key
    $cacheKey = md5($url);

    // Try cache
    $cached = cache_get($cacheKey, $cacheTtlSeconds);
    if ($cached !== null) return $cached;

    // Fetch with timeout and error handling
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ];
    $ch = curl_init();
    curl_setopt_array($ch, $opts);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        // Return null on error
        return null;
    }

    $data = json_decode($resp, true);
    if ($data === null) return null;

    cache_set($cacheKey, $data);
    return $data;
}
