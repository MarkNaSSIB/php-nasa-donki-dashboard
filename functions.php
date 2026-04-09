<?php
// functions.php
function get_env($key, $default = null) {
    return getenv($key) ?: $default;
}

function cache_get($key, $ttl = 600) {
    $file = sys_get_temp_dir() . "/donki_cache_" . md5($key) . ".json";
    if (file_exists($file) && (time() - filemtime($file) < $ttl)) {
        return json_decode(file_get_contents($file), true);
    }
    return null;
}

function cache_set($key, $data) {
    $file = sys_get_temp_dir() . "/donki_cache_" . md5($key) . ".json";
    file_put_contents($file, json_encode($data));
}

function fetch_nasa($path, $params = []) {
    $base = "https://api.nasa.gov/DONKI/";
    $apiKey = get_env('NASA_API_KEY');
    $params['api_key'] = $apiKey;
    $url = $base . $path . '?' . http_build_query($params);

    $cacheKey = $url;
    $cached = cache_get($cacheKey, 600);
    if ($cached !== null) return $cached;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($resp, true);
        cache_set($cacheKey, $data);
        return $data;
    }
    return ['error' => 'fetch_failed', 'status' => $code];
}
