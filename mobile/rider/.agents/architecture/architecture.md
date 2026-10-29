# Rider architecture — scoped verified map

Verified 2026-10-15 for the Phase 7 earnings integration. Overview: docs/ARCHITECTURE.md.

- Flutter Rider client consumes the existing ALONA API; it owns no financial
  database, pricing rule, payment collection or payout calculation.
- Pickup/delivery dashboards expose existing `riderTools`; Earnings opens
  `ProfitDashboardPage`. Delivery History retains its data/pagination and replaces
  its unavailable-earnings placeholder with a link to the same page.
- Flow: page → `RiderEarningsApi` → `RiderPortalApi` → guarded GET
  `/api/rider/earnings?page=...`. Profile/session checks and expired-token handling
  reuse `RiderSession`/`RiderSessionGuard`.
- `RiderEarningsPage`/`RiderEarning` validate PHP decimal strings, pagination,
  shipment IDs/status/dates. Total/pending/paid values come directly from server
  summary fields; history amounts come from server rows. No financial arithmetic.
- The earnings page uses existing Scaffold/Card/ListTile patterns, refresh/resume
  and revision checks. Failed/malformed responses show safe errors and hide stale
  amounts; no fabricated zero or cached earnings fallback.
- Evidence: lib/pages/profit_dashboard_page.dart, lib/services/rider_earnings_api.dart,
  lib/pages/rider_portal_page.dart, lib/pages/delivery_history_page.dart,
  test/rider_earnings_test.dart. Twelve focused widget tests, targeted static analysis
  and current JavaScript web compile passed. Live API/device acceptance remains pending.
- Runtime API origin comes from existing API_BASE_URL configuration. The passing
  build uses a reserved placeholder, not deployment configuration. ALONA migration
  and production-engine contention requirements are external rollout dependencies;
  see docs/integration/PHASE10_READINESS.md. No publication/deployment in this task.


Functionality update verified 2026-10-16:

- Native proof entry requests camera; unsupported targets select real photos. Existing
  ALONA private proof API/server completion guard retained; device acceptance pending.
- AddressDetails replaces Maps in dashboards and reads scoped collection/order address
  DTOs. Missing fields are explicit; current buyer profile never replaces destination.
- RiderNotificationsPage uses owned persisted notifications/read API, bounded paging,
  timestamps, tap targets and foreground/current-screen polling. ALONA after-commit
  event insertion is best effort without durable replay; no background/push provider.
- RiderAccountPage shows actual data and private photos; RiderAddressEditPage reuses
  PSGC directories. Own contact/photo edits preserve approval; changed address enters
  Logistics reapproval. Protected identity/vehicle/coverage/finance cannot be edited.
- Evidence: 63 focused Flutter tests PASS and analysis of 14 affected files clean.
  New ALONA notification migration/API deployment and live/device acceptance pending.
  Full audit: docs/integration/FUNCTIONALITY_AUDIT.md. No actual database writes.

Parcel workflow update verified 2026-10-16:

- Approved mobile_scanner 7.4.2 decodes Android/iOS QR/barcodes; other targets use
  manual ITEM reference entry. RiderParcelApi calls the owned, bounded lookup API;
  only authorized active parcels open the existing delivery details screen.
- Mark Delivered captures a PHOTO when absent, uploads, refreshes server eligibility
  and asks explicit confirmation. Backend validates the private image before delivery;
  duplicate actions/uploads and stale-file recapture are covered. History preserved.
- Evidence: 72 focused Flutter tests, affected Dart analysis and web compile PASS;
  Seller integration details: docs/integration/PARCEL_DELIVERY_IMPLEMENTATION.md.
  Android build blocked by missing SDK; iOS needs macOS; physical/live tests pending.
