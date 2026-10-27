/**
 * Vercel serverless version of api/nearest.php (Vercel has no PHP runtime).
 * Same query parameters, same JSON response. vercel.json rewrites
 * /api/nearest.php here, so the pages call one URL on MAMP and on Vercel.
 *
 * GET ?lat=..&lng=..   or   ?postcode=..   (+ &debug=1 for the full ranking)
 */
const venues = require('../data/venues.json');

const EARTH_RADIUS_MILES = 3958.8;

const MESSAGES = {
  invalid_postcode: "That doesn't look like a UK postcode. Try e.g. M4 1ES or SW1A 1AA.",
  postcode_not_found: "We couldn't find that postcode. Please check it and try again.",
  lookup_failed: 'Postcode lookup is unavailable right now. Please try again shortly.',
  invalid_location: 'Your location could not be read. Please enter your postcode instead.',
  no_bookable_venue: 'Online booking is unavailable right now. Please contact your nearest restaurant.',
};

/** Great-circle distance in miles (haversine). */
function distanceMiles(lat1, lng1, lat2, lng2) {
  const rad = (d) => (d * Math.PI) / 180;
  const dLat = rad(lat2 - lat1);
  const dLng = rad(lng2 - lng1);
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(dLng / 2) ** 2;
  return 2 * EARTH_RADIUS_MILES * Math.asin(Math.min(1, Math.sqrt(a)));
}

function isValidBookingUrl(url) {
  if (typeof url !== 'string' || url.trim() === '') return false;
  try {
    return /^https?:$/.test(new URL(url).protocol);
  } catch (e) {
    return false;
  }
}

/** Full UK postcode ("SW1A1AA") or outward code only ("SW1A", "M4"). */
function postcodeKind(normalised) {
  if (/^[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2}$/.test(normalised)) return 'full';
  if (/^[A-Z]{1,2}[0-9][A-Z0-9]?$/.test(normalised)) return 'outcode';
  return null;
}

/** Postcode -> { lat, lng, label } or { error } via postcodes.io (free, no key). */
async function lookupPostcode(postcode) {
  const pc = String(postcode).toUpperCase().replace(/[^A-Z0-9]/g, '');
  const kind = postcodeKind(pc);
  if (!kind) return { error: 'invalid_postcode' };

  const endpoint = kind === 'full' ? 'postcodes' : 'outcodes';
  let body;
  try {
    const res = await fetch(`https://api.postcodes.io/${endpoint}/${encodeURIComponent(pc)}`, {
      headers: { Accept: 'application/json' },
      signal: AbortSignal.timeout(8000),
    });
    body = await res.json();
  } catch (e) {
    return { error: 'lookup_failed' };
  }

  const r = body && body.status === 200 ? body.result : null;
  if (!r || r.latitude == null || r.longitude == null) return { error: 'postcode_not_found' };
  return { lat: r.latitude, lng: r.longitude, label: r.postcode || r.outcode || pc };
}

function rankVenues(lat, lng) {
  return venues
    .map((v) => ({
      ...v,
      distance_miles: Math.round(distanceMiles(lat, lng, v.lat, v.lng) * 100) / 100,
      bookable: isValidBookingUrl(v.booking_url),
    }))
    .sort((a, b) => a.distance_miles - b.distance_miles);
}

function send(res, status, body) {
  res.statusCode = status;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');
  res.end(JSON.stringify(body));
}

module.exports = async (req, res) => {
  const q = new URL(req.url, 'http://localhost').searchParams;
  let origin;

  if (q.has('postcode')) {
    const loc = await lookupPostcode(q.get('postcode'));
    if (loc.error) {
      return send(res, loc.error === 'lookup_failed' ? 502 : 422, { ok: false, error: loc.error, message: MESSAGES[loc.error] });
    }
    origin = { lat: loc.lat, lng: loc.lng, source: 'postcode', label: loc.label };
  } else {
    const lat = Number(q.get('lat'));
    const lng = Number(q.get('lng'));
    if (!q.get('lat') || !q.get('lng') || !Number.isFinite(lat) || !Number.isFinite(lng) || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
      return send(res, 422, { ok: false, error: 'invalid_location', message: MESSAGES.invalid_location });
    }
    origin = { lat, lng, source: 'geolocation', label: 'your location' };
  }

  const ranked = rankVenues(origin.lat, origin.lng);
  const skipped = [];
  const venue = ranked.find((v) => v.bookable || (skipped.push(v.name), false));

  if (!venue) {
    return send(res, 503, { ok: false, error: 'no_bookable_venue', message: MESSAGES.no_bookable_venue });
  }

  const body = {
    ok: true,
    origin,
    venue: {
      name: venue.name,
      address: venue.address,
      distance_miles: venue.distance_miles,
      booking_url: venue.booking_url,
    },
    skipped,
  };
  const debug = q.get('debug');
  if (debug && debug !== '0') {
    body.ranked = ranked.map((r) => ({ name: r.name, distance_miles: r.distance_miles, bookable: r.bookable }));
  }
  send(res, 200, body);
};
