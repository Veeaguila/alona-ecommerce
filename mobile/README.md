# Mobile applications

`rider/` contains the active Flutter Rider client for the ALONA backend in `..`.
Future Logistics, Buyer and Seller mobile clients can have their own directories here.
They are not included yet. Each client keeps its own Flutter dependencies, platforms,
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
