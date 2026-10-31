# Mobile applications

`rider/` contains the active Flutter Rider client for the ALONA backend in `..`.
`alona_mobile/` contains the imported Buyer/Logistics Flutter application. Its
Logistics operations follow the same backend as web; see its README and
docs/LOGISTICS_PARITY.md for verified Buyer/Logistics scope and remaining native work.
Seller mobile is not included. Each client keeps its own Flutter dependencies, platforms,
tests and documentation; the parent repository tracks their regular source files.

From the repository root:

```powershell
cd mobile/rider
flutter pub get
flutter test --no-pub --dart-define=API_BASE_URL=http://localhost:8000/api
flutter analyze --no-pub
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
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
