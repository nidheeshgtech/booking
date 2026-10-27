<?php
/**
 * GET api/nearest.php?lat=..&lng=..        (from browser geolocation)
 * GET api/nearest.php?postcode=..          (manual fallback)
 * Add &debug=1 to include the full ranked list.
 */
require __DIR__ . '/../lib/locator.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$messages = [
    'invalid_postcode'   => "That doesn't look like a UK postcode. Try e.g. M4 1ES or SW1A 1AA.",
    'postcode_not_found' => "We couldn't find that postcode. Please check it and try again.",
    'lookup_failed'      => 'Postcode lookup is unavailable right now. Please try again shortly.',
    'invalid_location'   => 'Your location could not be read. Please enter your postcode instead.',
    'no_bookable_venue'  => 'Online booking is unavailable right now. Please contact your nearest restaurant.',
];

if (isset($_GET['postcode'])) {
    $loc = mr_lookup_postcode((string) $_GET['postcode']);
    if (isset($loc['error'])) {
        respond($loc['error'] === 'lookup_failed' ? 502 : 422, ['ok' => false, 'error' => $loc['error'], 'message' => $messages[$loc['error']]]);
    }
    $origin = ['lat' => $loc['lat'], 'lng' => $loc['lng'], 'source' => 'postcode', 'label' => $loc['label']];
} else {
    $lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
    $lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);
    if (!is_float($lat) || !is_float($lng) || abs($lat) > 90 || abs($lng) > 180) {
        respond(422, ['ok' => false, 'error' => 'invalid_location', 'message' => $messages['invalid_location']]);
    }
    $origin = ['lat' => $lat, 'lng' => $lng, 'source' => 'geolocation', 'label' => 'your location'];
}

$ranked = mr_rank_venues(require __DIR__ . '/../data/venues.php', $origin['lat'], $origin['lng']);
$result = mr_find_nearest_bookable($ranked);

if ($result['venue'] === null) {
    respond(503, ['ok' => false, 'error' => 'no_bookable_venue', 'message' => $messages['no_bookable_venue']]);
}

$v = $result['venue'];
$body = [
    'ok'      => true,
    'origin'  => $origin,
    'venue'   => [
        'name'           => $v['name'],
        'address'        => $v['address'],
        'distance_miles' => $v['distance_miles'],
        'booking_url'    => $v['booking_url'],
    ],
    'skipped' => $result['skipped'],
];
if (!empty($_GET['debug'])) {
    $body['ranked'] = array_map(fn($r) => [
        'name' => $r['name'], 'distance_miles' => $r['distance_miles'], 'bookable' => $r['bookable'],
    ], $ranked);
}
respond(200, $body);
