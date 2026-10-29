# Phase 7 integration / Phase 10 readiness

Verified 2026-10-15. Current changes are local; no commit/push or production migration.

Latest parcel workflow implementation: [PARCEL_DELIVERY_IMPLEMENTATION.md](PARCEL_DELIVERY_IMPLEMENTATION.md).
Phone scanner/manual entry and owned lookup, PHOTO/private-file completion checks,
duplicate upload reuse, explicit confirmation/success, atomic Seller delivery notices
and private proof viewing are implemented locally without a schema change. Current
focused checks: 72 Flutter tests and 59 Laravel tests/537 assertions PASS; affected
Dart analysis and both changed Seller Vue components clean. Android debug build is
BLOCKED because no Android SDK is configured; physical Android/iOS and live four-role
acceptance remain pending. Later recovery checkpoints record the current web compile.

2026-10-16 functionality update: see [FUNCTIONALITY_AUDIT.md](FUNCTIONALITY_AUDIT.md).
Camera, address cards, persisted notifications and own profile/photo/address updates
are implemented locally. A second prepared migration now adds rider_notifications;
it has only been tested in SQLite memory. Backend/API must be deployed compatibly
after approved schema rehearsal/migration. Address updates require Logistics reapproval.
Prior web/MySQL results below predate these changes; new matching-engine/profile/
notification concurrency and device/live acceptance remain pending.

Latest release audit: [RELEASE_READINESS.md](RELEASE_READINESS.md). Disposable
MySQL 8.0.46 Phase 7 upgrade and 16 concurrency cases now PASS on an equivalent
existing-schema fixture. The populated ALONA website database is still unidentified:
local configuration points to an empty SQLite database. Deployment is BLOCKED until
the actual configuration/schema and approved clone rehearsal are verified. The
chronological MySQL bootstrap has a pre-existing variant-table dependency conflict;
no application migration was edited. The table below retains prior integration evidence.

| Status | Evidence / remaining work |
|---|---|
| PASSED | ProfitDashboardPage → RiderEarningsApi → existing guarded GET `/api/rider/earnings`. Total earned uses `summary.total`, pending payout uses `summary.outstanding`, paid earnings uses `summary.paid`; history uses returned shipment/amount/status/date rows. No Flutter financial arithmetic or fallback earnings. |
| PASSED | Existing Rider tools entry and history link open earnings without changing operational layouts, history endpoints or history pagination. Empty, malformed, expired-session, refresh/error and overlapping-response handling covered. |
| PASSED | 12 focused Flutter tests; clean targeted analysis of 6 Dart files; current JavaScript web release compile with a public placeholder URL. |
| PASSED | 21 focused Laravel tests/166 assertions: package proof completion, uniqueness, cancellation, assignment, independent full manual settlement and access/anti-forgery. Approved Logistics accounts cannot administer finance; settlement remains approved-admin-only. |
| PASSED | Scoped code review found no actionable defects. No Laravel runtime code changed in this final UI integration; only additional finance tests/docs. Prior 96-test Logistics regression evidence retained. |
| PENDING | Operator migration/schema release, real HTTPS API URL, web CORS/private proof storage/upload limits, live browser admin-session acceptance. No live API or database was contacted here. |
| PASSED / PENDING | 16 separate-process MySQL cases passed at REPEATABLE READ and READ COMMITTED; actual production isolation/schema, seller-collection and shared-parent/multiple-seller races still need verification. See latest audit for fixture limits. |
| PENDING | Android/iOS native/device/camera, real email verification/approval and authorized end-to-end workflow. |
| BLOCKED | Actual populated ALONA database configuration/schema not identified. Local SQLite inventory cannot establish production migration readiness. |

ALONA `docs/DELIVERY_FINANCE_DEPLOYMENT.md` is the release/migration and concurrency
checklist. It covers partial DDL recovery after shutdown, unique constraints, no
historical backfill, all-member completion, rider assignment races and settlement
idempotency. No production commands were run. The local web build uses the reserved
placeholder `https://alona.example/api`; a real API build/configuration is still needed.

The earnings screen displays delivery earnings, not net profit or an estimated
payout. Actual money transfers remain manual; the Rider screen cannot mark paid.
