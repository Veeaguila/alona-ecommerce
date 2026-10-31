# ALONA architecture — scoped verified map

Order-flow / mobile placement verified 2026-10-29:

- Active Rider source is mobile/rider as ordinary parent Git files; API/backend is
  ../.. from that app. No submodule or new mobile database. Original teammate
  commit/provenance is recorded in mobile/rider/docs/UPSTREAM.md.
- Rider shell preserves tab state and refreshes pickup/delivery assignments on
  activation. Dashboard actions and details share retry/RTS restrictions and
  canonical failure outcomes; nearest decline invokes the real API.
- A real-checkout memory fixture now covers two Rider legs, proof/OTP completion,
  physical Seller return/restock, store pickup and separate COD cash/remittance.
- Safe full verifier: scripts/verify-system.cjs, optionally --mobile. Test/build
  evidence, repaired defects and native/target-schema limits:
  docs/testing/results/ORDER_FLOW_REVIEW.md. No new application migration.


Logistics commit-readiness changes verified 2026-10-29:

- LogisticsSessionLifetime validates owner/staff sorting_center tokens in Sanctum's
  authentication callback before last_used_at refresh. Thirty minutes without an
  authenticated request expires that token. Other-role lifetimes are unchanged.
- EnforceLogisticsIdleTimeout combines web-session activity with its bound API token,
  invalidates idle browsers and revokes only their token. Active web sessions rotate
  stale embedded tokens. Portal recovery handles current same-origin API401 and
  ignores old-login responses. Existing two-hour web-token maximums remain.
- Registration errors remount upload controls to match reset models. Vite denies
  private recovery directories and database backups, preserving default sensitive
  file denials. No new schema or application migration.
- Evidence: LogisticsIdleSessionTest, logistics-session.test.mjs,
  dev-server-security.test.mjs and tests/browser/logistics-registration.cjs.
  Current verification, workbook mappings and target-schema/live limits:
  docs/testing/results/LOGISTICS_COMMIT_READINESS.md; upgrade:
  docs/LOGISTICS_TEAM_UPDATE.md.


Verified 2026-10-15 against affected integration source. This map covers the
delivery-finance boundary, not a full storefront inventory.

- Laravel application, existing shared database/authentication. `routes/api.php`
  scopes Rider operations to Sanctum + approval, seller reads to Sanctum + seller;
  `routes/admin.php` uses existing session auth/admin and web CSRF.
- `BuyerOrderController` creates buyer-funded orders with the incoming regional/
  weight shipping rates and per-store order splitting, per the user's 2026-10-24
  decision. Method metadata, vouchers and historical totals are retained.
  `SellerShipmentFinance::createMembership` only creates memberships for seller-
  funded orders; new buyer-funded orders cannot incur a duplicate seller liability.
  Existing seller-funded shipments keep the ledger described below. No new rule
  allocates buyer shipping fees to Rider earnings or payouts.
- `Order` has many `OrderItem`; each item optionally belongs to `SellerShipment`
  and has one unique linked `Parcel`. Shipment seller is snapshotted. Parcel scan
  references are strings, not parent order IDs. Historical memberships remain null.
- Existing pickup/assignment/delivery controllers call `SellerShipmentFinance`.
  One center/rider and complete ready membership are required before dispatch;
  all noncancelled parcels need assigned-rider proofs before one atomic accrual.
  Locks run parent order → shipment → members/parcels, with current locking reads.
- `seller_shipments` has unique(order_id,seller_id); `seller_delivery_charges` has
  unique seller_shipment_id; `rider_delivery_earnings` has unique charge FK. Seller
  collection and rider payout statuses are independent. Financial FKs restrict
  deletion. The migration file is prepared, not applied outside isolated tests.
- `DeliveryFinanceController` exposes owned paginated reads and admin-only full
  manual settlements. No payment processor, shared buyer payment-status mutation,
  marketplace commission change, partial payout or automatic return clawback.
- Flutter is an API consumer; this change preserves its UI/history contract.
  Express remains checkout method metadata; no priority handler was found in scope.
- Evidence: migration `2026_10_15_100000_create_seller_shipment_finance.php`, models,
  `app/Services/SellerShipmentFinance.php`, `routes/api.php`, `routes/admin.php`,
  `tests/Feature/Logistics/SellerShipmentFinanceTest.php`. API and validation limits:
  `docs/DELIVERY_FINANCE.md`. Production-engine concurrency/live acceptance remains
  unverified. No new external service/dependency or schema deployment occurred.

Final API-consumer verification (2026-10-15): Rider now displays backend earnings
summary/history using its authenticated earnings view without financial arithmetic.
Twenty-one focused finance tests / 166 assertions pass, including Logistics denial,
approved-admin full settlements and web anti-forgery with the test bypass disabled.
Migration/release and disposable deployment-engine contention checks are documented,
not executed: docs/DELIVERY_FINANCE_DEPLOYMENT.md. Backend runtime code is unchanged
in this final UI integration; existing historical orders/financial contracts remain.


Functionality update verified 2026-10-16 (backend runtime additions):

- RiderParcelDetails adds minimal party/contact/address DTOs to pickup/active-delivery
  responses, preserving seller collection and order delivery snapshots/history.
- rider_notifications migration/model + RiderNotificationObserver capture source facts
  and insert after outer commit for pickup/parcel/account/message/earning changes.
  Unique owned event keys/read-once timestamps; scoped Rider feed accessible with
  valid tokens while pending. Best effort/no durable outbox or background push.
- RiderProfileController permits own contact and canonical PSGC address edits; changed
  addresses enter existing Logistics approval, retaining vehicle/documents/areas.
  Existing users.avatar stores private owned photo paths, with file validation,
  rollback cleanup and after-commit old-file deletion. Google sign-in preserves these
  photos under a User lock; other roles retain existing behavior. No profile migration.
- Prepared notification schema has only SQLite-memory verification; finance MySQL
  results predate it. Actual populated database remains unidentified; no configuration
  switch, actual migration or record write. docs/RIDER_FUNCTIONALITY.md covers APIs,
  overlapping passing Laravel batches, approved-copy rollout and concurrency checks.

Parcel workflow update verified 2026-10-16:

- Approved Rider GET deliveries/lookup validates existing ITEM references and scopes
  assignment before returning the existing safe DTO. New has_valid_photo_proof checks
  assigned-rider PHOTO plus readable private image; historical status is unchanged.
- Existing locked proof upload deduplicates identical file retries. Completion requires
  valid PHOTO and adds SellerParcelDelivery notice inside the existing transaction;
  notice persistence failure rolls back status/history/financial recognition.
- Seller item details and session-protected private proof route verify product/item/
  parcel/shipment Seller and assigned Rider. Unverified legacy links are not guessed.
  Existing SellerNotification schema/read route reused; no new migration or finance rule.
- Evidence: 59 focused Laravel tests/537 assertions and Seller Vue compilation PASS.
  SQLite-memory evidence does not establish InnoDB contention or live browser results;
  actual-schema deployment and device/live checks remain pending. API notes:
  docs/RIDER_FUNCTIONALITY.md; Rider docs/integration/PARCEL_DELIVERY_IMPLEMENTATION.md.

Origin/main integration verified 2026-10-16 (a52966b): Buyer registration includes
middle name/suffix, strong password defaults and Buyer-only OTP routes. Logistics
retains its independent challenge, approval/resubmission and shared Rider observers.
Observer timestamps normalize correctly when Laravel disables casts during logout.
Evidence: 118 backend tests / 1,064 assertions, 19 frontend tests and production
build PASS. Existing-database migrations remain unapplied. Preservation receipts
and remaining acceptance: docs/ORIGIN_MAIN_INTEGRATION.md.

Logistics registration v2 verified 2026-10-16: dedicated LogisticsRegistrationController/
Request and LogisticsOnboarding reuse shared users/partner_applications/private
document storage and expand required regions to canonical city coverage. Prepared
logistics_registrations adds unique identities/submission key and hashed one-use
OTP (5-minute expiry, resend invalidation, attempt cap); existing DB rollout pending.
Rejected verified applicants replace documents and return to pending review.
Trusted Google signup retains required Logistics OTP. Shared native file inputs
retain selections and reset explicitly. Application evidence covers all 44 workbook
cases; live SMTP/uploads/engine acceptance remain unverified. Current evidence and
approval prerequisites: docs/LOGISTICS_REGISTRATION_TEST_MATRIX.md.


Company hub network prepared 2026-10-29:

- Existing sorting_centers remain company ownership boundaries. New logistics_hubs and directed logistics_routes are explicit physical locations and same-company transport; city coverage is not a hub. No inferred hub backfill.
- Server-derived immutable order_items.hub_route_plan binds new opted-in bookings to company/origin/destination. ParcelHubJourney and HubTransfer retain received/dispatched custody; destination receipt precedes hub-correct last-mile assignment. Buyer item history shows transfers.
- Checkout activation uses LOGISTICS_HUB_ROUTES_REQUIRED (default false). Migration 2026_10_29_100000 is prepared only; no local/shared application migration or activation performed. Existing orders, fee/COD rules and original staging remain preserved.
- Evidence: HubNetworkTest, frontend/hub-network.test.mjs, isolated production build and synthetic built-UI Edge checks. Actual rollout/cargo/device/MySQL contention remain unverified. Staff roles are now implemented below. Cross-company partnerships, intermediate hops and reverse hub RTS remain pending; inter-hub local RTS is explicitly blocked. Setup and limits: docs/LOGISTICS_HUB_NETWORK.md.


Workbook delivery corrections verified 2026-10-29:

- ShippingRates now supplies the same server-derived tariffs to checkout display and placement; expected_shipping_fee prevents silently accepting a stale displayed quote. Logistics DeliveryRateController exposes owned GET/POST/PUT delivery-rates with append-only audit, positive base rates and explicit active province coverage. Historical fees remain unchanged.
- NearestPickupOffer uses 60-second offers, durable tried-Rider audit, owned decline and next-nearest retries up to three Riders. routes/console.php adds scheduled logistics:advance-pickup-offers; queue reads/claims also advance. Fresh location/capacity/ownership and whole-shipment custody guards remain required.
- DeliveryAttempts uses the three workbook reasons and immediate Refused RTS eligibility. DeliveryOtp keeps its failure counter across renewal and blocks verification/renewal for fifteen minutes after five wrong entries. Rider dropdown and nearest-offer decline use the same API contract.
- Verified: Logistics374/6277 and Buyer/Seller36/385 in SQLite memory, frontend102, Rider115, isolated build plus built-UI Edge1440/390/320; generated assets installed locally with backup. Optional multi-hub mode stays off; real hubs/routes, native/device/live scheduler/production acceptance and reverse inter-hub RTS remain pending. No application migration, environment, earnings allocation, session-expiration change, commit or push. See docs/LOGISTICS_WORKBOOK_READINESS.md.


Single company operator hub management installed locally 2026-10-29:

- The approved company owner manages all own-company hubs. Scoped Hub Staff memberships now extend this original single-operator flow. Hubs & Routes navigation is available independently of required checkout routing. HubNetwork.vue separates setup from work queues and requires an explicit operating hub and physical confirmation for origin receiving, dispatch and destination receipt.
- HubOperations provides company-scoped per-hub queues/counts for pickup arrivals, ready dispatch, incoming transfers, current custody and delivery. Foreign hub IDs are denied; switching hubs clears confirmations and old queue rows. Incoming hub receipt now records the authenticated operator in the immutable audit. Partial hub schema returns a setup503.
- First-time migration2026_10_29_100000 was applied only to verified local alona_restored_20261028 after SQL backup and schema checks:80 original tables became84; original business-row hashes preserved, new hub tables empty, no guessed hubs/Riders/accounts. Shared deployment and required checkout route activation remain pending actual operating setup.
- Verified57 affected backend tests/1484 assertions in SQLite memory, frontend106, build/PHP syntax and synthetic built-UI Edge1440/390/320 with8 same-login setup/turnover mutations and zero exceptions. Generated local assets installed with backup. Source/staging/environment preserved; no commit/push. Details: docs/LOGISTICS_HUB_NETWORK.md and .local/single-operator-hubs-20261029.


Company tariff isolation and HTTP request keys verified 2026-10-29:

- ShippingRates resolves a hub-bound seller booking against its booked company, then administrator/global and regional defaults. Buyer checkout supplies per-seller rate cards and placement reuses its validated hub plans, including split orders; unbound legacy pricing and historical booked fees remain unchanged.
- resources/js/requestKey.js shares UUIDv4 generation across hub dispatch, supervised handover and parcel incident controls. getRandomValues supports ordinary HTTP/LAN when randomUUID is unavailable; no credential generation changed.
- Evidence:66 affected backend tests/1165 assertions in SQLite memory,109 frontend checks, syntax/build and actual built-UI8 actions on both localhost and insecure HTTP at1440/390/320 PASS. Original source/staging preserved outside the scoped fixes; no migrations or real database writes. MySQL concurrency and shared/native acceptance remain unverified.


Hub Staff workflow verified 2026-10-29:

- HubAccess resolves company ownership or an active logistics_hub_staff membership. Existing sorting_center usertype is reused without granting staff their own company. HubWorkspace/EnsureHubOperator APIs scope lookup, queues and physical actions to the assigned company hub; old company endpoints deny staff. HubStaffController provisions, pauses or reassigns staff and revokes API tokens on access changes.
- Existing tracking references and immutable booked journeys determine saved addresses/current hub/next destination/server action. Lookup does not move a parcel. Confirmed physical actions delegate to existing transport and Seller shipment finance. Owner sorting/Rider assignment and Rider pickup/delivery remain in use; no guessed legacy journeys or intermediate-hop planner.
- Web pages use existing styles. Logistics Flutter reuses its scanner; staff land in scoped workspace, owners open it from Work. Prepared migration2026_10_29_150000 remains unapplied; no real hubs or accounts created. Backend480/7033 SQLite-memory, frontend118, Flutter381, built-web7 checks/zero exceptions pass. Native/live/MySQL acceptance pending. Setup: docs/LOGISTICS_HUB_NETWORK.md.
