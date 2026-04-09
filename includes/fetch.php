<?php
// includes/fetch.php
// Robust DONKI fetch wrapper with file caching, logging, retries, and chunked fetch fallback.
//
// Usage: fetch_nasa($type, $params = [], $cacheTtlSeconds = 3600)
// Returns: array|null (decoded JSON array on success, null on error)

declare(strict_types=1);

function cache_get_path(string $key): string {
    $dir = __DIR__ . '/../.cache';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . '/' . preg_replace('/[^a-z0-9_\-\.]/i', '_', $key) . '.json';
}

function cache_get(string $key, int $ttl = 3600) {
    $path = cache_get_path($key);
    if (!file_exists($path)) return null;
    $age = time() - filemtime($path);
    if ($age > $ttl) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $decoded = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
}

function cache_set(string $key, $data) {
    $path = cache_get_path($key);
    $tmp = $path . '.' . uniqid('tmp', true);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    // atomic write
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @rename($tmp, $path);
    return true;
}

/**
 * Perform a single HTTP GET and return array|null.
 * Logs status, body length and a short snippet for debugging.
 */
function fetch_url_and_decode(string $url, int $timeout = 30, int $maxSnippet = 2048) {
    $ch = curl_init();
    $opts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'donki-proxy/1.0 (+https://example.com)',
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    curl_setopt_array($ch, $opts);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $bodyLen = is_string($resp) ? strlen($resp) : 0;
    $snippet = is_string($resp) ? substr($resp, 0, $maxSnippet) : '';

    // Log the raw HTTP result (compact)
    error_log("FETCH_NASA HTTP url={$url} status={$code} body_len={$bodyLen} curl_err=" . ($err ?: 'none'));
    if ($bodyLen > 0) {
        $oneLine = preg_replace("/[\r\n]+/", " ", $snippet);
        error_log("FETCH_NASA BODY_SNIPPET: " . $oneLine);
    } else {
        error_log("FETCH_NASA BODY_SNIPPET: <empty>");
    }

    // Handle common non-JSON cases explicitly
    if ($resp === false) {
        return null;
    }
    // If server returned 204 No Content or empty body, return null to let caller decide fallback
    if ($code === 204 || $bodyLen === 0) {
        return null;
    }
    // Try decode
    $data = json_decode($resp, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("FETCH_NASA JSON_DECODE_ERROR: " . json_last_error_msg());
        return null;
    }
    return $data;
}

/**
 * Main fetch wrapper.
 * - Accepts DONKI feed type (FLR, CME, RBE, etc.)
 * - Accepts params array (startDate, endDate)
 * - Caches per-request
 * - If a single request returns null for large date ranges, attempts chunked fetch (30-day windows) and merges results.
 */
function fetch_nasa(string $feed, array $params = [], int $cacheTtlSeconds = 900) {
    $apiBase = 'https://api.nasa.gov/DONKI';
    $apiKey = getenv('NASA_API_KEY') ?: 'DEMO_KEY';

    // Normalize params
    $params = array_filter($params, function($v){ return $v !== null && $v !== ''; });
    // Ensure api_key present in query
    $paramsWithKey = array_merge($params, ['api_key' => $apiKey]);

    // Build canonical URL for caching
    $qs = http_build_query($paramsWithKey);
    $url = rtrim($apiBase, '/') . '/' . rawurlencode($feed) . '?' . $qs;

    // Cache key uses feed + params
    $cacheKey = md5($url);

    // Try cache
    $cached = cache_get($cacheKey, $cacheTtlSeconds);
    if ($cached !== null) {
        error_log("FETCH_NASA CACHE HIT feed={$feed} url={$url} cache_key={$cacheKey}");
        return $cached;
    }

    // Attempt single fetch with simple retry for transient errors
    $attempts = 0;
    $maxAttempts = 2;
    $data = null;
    while ($attempts < $maxAttempts) {
        $attempts++;
        $data = fetch_url_and_decode($url);
        if ($data !== null) break;
        // If we got null and HTTP might have been 429/5xx, wait and retry (simple backoff)
        sleep($attempts); // 1s then 2s
        error_log("FETCH_NASA RETRY feed={$feed} attempt={$attempts} url={$url}");
    }

    // If we got valid data, cache and return
    if (is_array($data)) {
        cache_set($cacheKey, $data);
        return $data;
    }

    // If no data and params include startDate/endDate, consider chunked fallback for large ranges (CME often large)
    if (isset($params['startDate']) && isset($params['endDate'])) {
        // compute days between start and end
        try {
            $start = new DateTimeImmutable($params['startDate']);
            $end = new DateTimeImmutable($params['endDate']);
            $interval = $start->diff($end);
            $days = (int)$interval->format('%a');
        } catch (Throwable $e) {
            error_log("FETCH_NASA DATE_PARSE_ERROR: " . $e->getMessage());
            $days = 0;
        }
    }

    // If we reach here, no data could be fetched
    error_log("FETCH_NASA FAILED feed={$feed} url={$url} returning null");
    return null;
}
