# Rider App

This Flutter app is the courier-facing mobile client for the ALONA Laravel API in
[`../..`](../..). It lives at `mobile/rider` and shares the parent Git repository.
ALONA remains the single backend and database. Source provenance: [docs/UPSTREAM.md](docs/UPSTREAM.md).

## Requirements

- Flutter and Dart versions supported by `pubspec.yaml`
- Android Studio with an Android emulator, or a connected Android device
- A running ALONA API and its existing database

## Install dependencies

Run these commands from the Rider project:

```powershell
flutter pub get
```

Widget tests use synthetic HTTP fixtures and require the explicit test API define:

```powershell
flutter test --no-pub --dart-define=API_BASE_URL=http://localhost:8000/api
flutter analyze --no-pub
```

Start the existing Laravel API by following [`../../README.md`](../../README.md). Then launch the app with the API URL for your target device:

### Android emulator

The Android emulator reaches services on the development computer through `10.0.2.2`:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

### Web, desktop, or iOS simulator on the same computer

```powershell
flutter run --dart-define=API_BASE_URL=http://localhost:8000/api
```

### Physical device

Connect the device and development computer to the same trusted network. Start Laravel so it listens on the network interface:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Then replace `<computer-lan-ip>` with the computer's LAN address:

```powershell
flutter run --dart-define=API_BASE_URL=http://<computer-lan-ip>:8000/api
```

Allow port 8000 through the local firewall only on the trusted development network. Android permits cleartext HTTP for debug builds only; production deployments should use HTTPS.

## Structure

| Path | Purpose |
| --- | --- |
| `lib/pages` | Registration, login, pickup, delivery, and proof screens |
| `lib/services` | API configuration and shared service code |
| `assets/images` | App images |
| `android`, `ios`, `web`, `windows`, `macos`, `linux` | Flutter platform projects |
| `test` | Flutter tests |

Web debug builds default to http://localhost:8000/api. Native builds require an explicit API_BASE_URL reachable from the target; localhost refers to the device itself. Trailing slashes are normalized. Release builds require an explicit HTTPS URL ending in /api:

```powershell
flutter build apk --dart-define=API_BASE_URL=https://<alona-host>/api
```

The public GET /api/sorting-centers endpoint can verify connectivity without logging in or changing database records. Its data list may be empty. Flutter web also requires the server to permit the application's origin through CORS.

## Integration and recovery

Registration uses ALONA's published address/coverage directories, partner approval,
identity documents and conditional vehicle/license requirements. Pickup/delivery
addresses and proof state come from ALONA. Rider tools expose chat, contact/status,
in-app updates and owned delivery history. Earnings require agreed payout data/rules.

Start recovery with docs/integration/RESUME.md, CHECKPOINT.md and PROGRESS.md. Run
`node scripts/integration_checkpoint.cjs --verify`. If document bundle IDs disagree,
`--recover` verifies the previous bundle, archives interrupted notes and restores
documents only. Inspect RECOVERY_NOTES.md and source hash changes before continuing.
Use `--run rider|alona <check-command> <args>` to persist running/completed outcomes.
Laravel checks refuse cached config and force SQLite :memory:. No background restart
service, unsaved-edit recovery or off-device backup is provided.

Deployment requires HTTPS API_BASE_URL, working verification email/browser login,
approval/coverage and camera permissions. Check server/proxy limits against 20 MB proof
and multiple 5 MB registration files. See [current review](../../docs/testing/results/ORDER_FLOW_REVIEW.md)
and [team guide](../../docs/LOGISTICS_TEAM_UPDATE.md). Release identity/signing require
team configuration; web checks do not certify Android/iOS or actual target schemas.
No shared runtime settings were changed. Verify device networking and web CORS with
your real deployment. JavaScript web compile passed; optional WASM is not supported
by the current secure-storage web dependency.
