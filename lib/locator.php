<?php
/**
 * Nearest-venue locator. Plain functions (mr_ prefix) so they can move
 * into a WordPress plugin unchanged.
 */

const MR_EARTH_RADIUS_MILES = 3958.8;

/** Great-circle distance in miles (haversine). */
function mr_distance_miles(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return 2 * MR_EARTH_RADIUS_MILES * asin(min(1, sqrt($a)));
}

function mr_is_valid_booking_url($url): bool
{
    return is_string($url)
        && trim($url) !== ''
        && filter_var($url, FILTER_VALIDATE_URL) !== false
        && preg_match('#^https?://#i', $url) === 1;
}

/** Uppercase, strip spaces/junk: " sw1a 1aa " -> "SW1A1AA". */
function mr_normalise_postcode(string $postcode): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($postcode));
}

/** Full UK postcode ("SW1A1AA") or outward code only ("SW1A", "M4"). */
function mr_postcode_kind(string $normalised): ?string
{
    if (preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/', $normalised)) {
        return 'full';
    }
    if (preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?$/', $normalised)) {
        return 'outcode';
    }
    return null;
}

/**
 * Postcode -> coordinates via postcodes.io (free, no key).
 * Returns ['lat','lng','label'] or ['error' => code].
 */
function mr_lookup_postcode(string $postcode): array
{
    $pc = mr_normalise_postcode($postcode);
    $kind = mr_postcode_kind($pc);
    if ($kind === null) {
        return ['error' => 'invalid_postcode'];
    }

    $endpoint = $kind === 'full' ? 'postcodes' : 'outcodes';
    $res = mr_http_get_json('https://api.postcodes.io/' . $endpoint . '/' . rawurlencode($pc));
    if ($res === null) {
        return ['error' => 'lookup_failed'];
    }
    if (($res['status'] ?? 0) !== 200 || empty($res['result'])) {
        return ['error' => 'postcode_not_found'];
    }

    $r = $res['result'];
    if (!isset($r['latitude'], $r['longitude'])) {
        return ['error' => 'postcode_not_found'];
    }
    return [
        'lat'   => (float) $r['latitude'],
        'lng'   => (float) $r['longitude'],
        'label' => $r['postcode'] ?? $r['outcode'] ?? $pc,
    ];
}

/** GET a JSON URL. Returns decoded array (also for 4xx bodies) or null on network failure. */
function mr_http_get_json(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $failed = $body === false;
    if (PHP_VERSION_ID < 80000) {
        curl_close($ch);
    }
    if ($failed) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

/** All venues with distance_miles, closest first. */
function mr_rank_venues(array $venues, float $lat, float $lng): array
{
    foreach ($venues as &$v) {
        $v['distance_miles'] = round(mr_distance_miles($lat, $lng, $v['lat'], $v['lng']), 2);
        $v['bookable'] = mr_is_valid_booking_url($v['booking_url'] ?? null);
    }
    unset($v);
    usort($venues, fn($a, $b) => $a['distance_miles'] <=> $b['distance_miles']);
    return $venues;
}

/**
 * Closest venue that has a valid booking URL.
 * Returns ['venue' => array|null, 'skipped' => names of closer venues without a URL].
 */
function mr_find_nearest_bookable(array $ranked): array
{
    $skipped = [];
    foreach ($ranked as $v) {
        if ($v['bookable']) {
            return ['venue' => $v, 'skipped' => $skipped];
        }
        $skipped[] = $v['name'];
    }
    return ['venue' => null, 'skipped' => $skipped];
}
