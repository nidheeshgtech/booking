# booking

Maki & Ramen "Book Now" nearest-venue finder — standalone PHP prototype, to be ported to a WordPress plugin.

Flow: Book Now → use current location (or UK postcode via postcodes.io) → nearest venue with a booking link → redirect to that venue's booking page.

- `popup.php` – popup version (location first, postcode fallback)
- `index.php` – earlier one-page version
- `api/nearest.php` – `?lat=&lng=` or `?postcode=` (add `&debug=1` for the full ranking)
- `lib/locator.php` – distance + postcode lookup
- `data/venues.php` – venue list and per-venue booking URLs
- `dev/share-router.php` – router for sharing only the demo through a tunnel

Run locally with MAMP: http://localhost:8888/booking/popup.php
