# Mobile application

`alona_mobile/` contains the unified Flutter application combining Buyer storefront,
Logistics Sorting Center tools, and Rider delivery operations. All modules communicate
with the ALONA backend via the REST API at `https://shopalona.online/api`.

From the repository root:

```powershell
cd mobile/alona_mobile
flutter pub get
flutter run
```

Use a reachable API URL for the target device; release builds require HTTPS. The
mobile app never connects directly to MySQL. Keep build output, package caches,
credentials and signing keys out of Git. Do not create nested `.git` directories:
a normal ALONA pull must include the reviewed mobile source without submodule setup.

Run `node scripts/verify-system.cjs --mobile` from the repository root after installing
the locked PHP, Node and Flutter dependencies. Backend tests use SQLite `:memory:`;
Rider widget tests use synthetic HTTP fixtures. Device API URLs belong in build/run
arguments; do not edit the source to use someone else's laptop address.

Install the locked Flutter dependencies separately in `rider/` and `alona_mobile/`.
The combined app accepts a backend origin (with or without `/api`); Rider's build
argument uses the `/api` base. Both share the same ALONA records and authorization.
