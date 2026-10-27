# booking

Maki & Ramen "Book Now" nearest-venue finder — prototype, to be ported to a WordPress plugin.

Flow: Book Now → use current location (or UK postcode via postcodes.io) → nearest venue with a booking link → redirect to that venue's booking page.

| File | What |
|---|---|
| `index.html` | Popup version (location first, postcode fallback) |
| `onepage.html` | Earlier one-page version |
| `data/venues.json` | Venue list + per-venue booking URLs — **edit links here** |
| `api/nearest.php` | API for MAMP / WordPress (PHP) |
| `api/nearest.js` | Same API for Vercel (Node) — `vercel.json` rewrites `/api/nearest.php` to it |
| `lib/locator.php` | PHP distance + postcode lookup |
| `dev/share-router.php` | Router for sharing only the demo through a tunnel |

API: `api/nearest.php?postcode=M4 1ES` or `?lat=..&lng=..`; add `&debug=1` for the full ranking.
Pages: add `?debug=1` to see all distances without redirecting.

- MAMP: http://localhost:8888/booking/
- Vercel: import this repo — no build settings needed.
