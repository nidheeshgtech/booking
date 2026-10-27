<?php
/**
 * Maki & Ramen venues — loaded from venues.json, which is the single source
 * shared with the Vercel function (api/nearest.js).
 *
 * booking_url: TEMPORARY placeholder = venue page. Replace with the real
 * booking links from the client. Leave '' (empty) if a venue has none —
 * the locator will skip it and use the next closest venue.
 */
return json_decode(file_get_contents(__DIR__ . '/venues.json'), true);
