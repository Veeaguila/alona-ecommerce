# ALONA mobile: Buyer and Logistics

Imported from Veeaguila/alona-mobile commit4277995; provenance: [UPSTREAM](docs/UPSTREAM.md).
This app lives beside `../rider`. Both use the ALONA backend in `../..` and its
existing database. No nested Git checkout or separate mobile schema is required.

Logistics follows the web process through native Flutter screens: acceptance,
pickup dispatch/acknowledgment, physical arrival, sorting, eligible delivery
assignment, outbound readiness, proof/OTP delivery, returns and separate COD cash
receipt. Hub Staff use the server-scoped hub workspace. Company setup remains in
web. Current evidence and limits: [Logistics parity](docs/LOGISTICS_PARITY.md).

Buyer cart checkout and Buy Now use the same shipping/coverage, promotion, voucher,
stock and booking rules as web. Seller receives the order in the existing web
portal; Logistics receives its pickup after Seller marks it Ready for Pickup.
The Buyer mobile order detail can reveal its private delivery OTP and confirm receipt.
Online payment selections remain unpaid; payment completion currently uses web.
Buyer chat uses the same Seller web threads. Sign in through email/password and
email verification/reset; mobile Google sign-in is unavailable until proper
provider integration replaces the imported insecure form.
This is an integration source handoff, not a certified full mobile release.

From this directory, after installing Flutter matching `pubspec.yaml`:

```powershell
flutter pub get --enforce-lockfile
flutter test --no-pub
flutter analyze --no-pub
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Use a reachable backend origin for the device. Release builds require HTTPS. An
explicit build-time URL takes precedence over saved settings on startup; otherwise
choose Server API Configuration in Buyer Account before connecting. There is no
production fallback. Changing the server signs out both modules. Tokens belong to
one configured server; native credentials use secure storage, web preferences.

The backend enforces Logistics expiration after30 minutes without authenticated
requests. The app handles401 and checks approval when it opens/resumes; it never
keeps a session alive by an automatic heartbeat. Failed/uncertain actions require
a refresh before another physical confirmation.

Parent verification with checkpoints:

```powershell
node mobile/rider/scripts/integration_checkpoint.cjs --run alona node mobile/alona_mobile/scripts/check.cjs test
node mobile/rider/scripts/integration_checkpoint.cjs --run alona node mobile/alona_mobile/scripts/check.cjs analyze
```

`node scripts/check.cjs build-web` builds only the isolated acceptance profile for
loopback18973 under `/alona-app/`, with local renderer assets. It is not a deployed
release. Keep build output, credentials, signing keys and package caches out of Git.
Native packaging, production configuration and actual-device acceptance are pending.
