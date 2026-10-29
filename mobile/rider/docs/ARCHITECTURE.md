# Architecture

mobile/rider is a regular ALONA repository directory. Backend is ../.. from the
app; the checkpoint helper resolves that parent. No submodule or separate database.

- main.dart/app.dart open login. rider_shell_page.dart retains tab state through
  IndexedStack. Pickup and Delivery receive an active flag, fetch when shown, and
  provide Refresh. Pending mutations also disable parent-card navigation.
- rider_session.dart, rider_session_guard.dart and rider_portal_api.dart share
  Sanctum authentication/current approval checks. api_config.dart validates explicit
  API_BASE_URL and requires HTTPS for release. Web tokens use SharedPreferences;
  other platforms use secure storage.
- Registration uses ALONA address/coverage/partner directories and document uploads.
  parcel_address.dart/address_details.dart render scoped Seller collection and
  Buyer destination snapshots. Missing recipient/payment information stays unknown.
- Pickup APIs: GET /rider/pickups/available and /mine?view=active|transit|history;
  POST /rider/pickups/{id}/accept|decline|acknowledge|confirm. Only nearest offers
  expose decline. Physical center arrival belongs to Logistics.
- Delivery APIs: owned lookup, active/history lists, start, upload-proof, deliver,
  fail and retry. Completion needs private PHOTO and Buyer OTP; retry/RTS metadata
  gates list and detail actions. Native image_picker captures photos; web selects
  real images. mobile_scanner 7.4.2 resolves owned ITEM references on phones.
- order_detail_pages.dart receives callbacks from its owning dashboard. Selected
  failure reasons use backend enum values directly. Unsupported pickup photo
  persistence is absent. Map illustration is decorative, not live GPS routing.
- Returns, Rider handover, Seller receipt/restock, COD collection, remittance and
  Logistics receipt are separate persisted confirmations. rider_handovers.dart
  validates ownership, IDs, parcel sets/timestamp and clears stale rows on failure.
- rider_presence_session.dart shares foreground availability heartbeats. geolocator
  14.0.2 supports explicitly consented one-shot pickup locations, not background GPS.
- Earnings use typed backend decimal totals through RiderEarningsApi. Historical
  seller-funded earnings stay separate from new buyer-funded checkout shipping.
  The app never calculates compensation or assumes cash due from an order total.
- Account, notifications and chat use existing scoped ALONA endpoints.
- test/ uses synthetic HTTP. Parent OrderJourney uses actual routes and isolated
  SQLite memory. Safe verifier: node scripts/verify-system.cjs --mobile from ALONA.
  Checkpoints flush/atomically replace/verify bundles; recovery never resets source,
  Git or DB. The old sibling checkout is a preserved reference, not runtime input.

ALONA owns links, hub custody, stock, rates, coverage, OTP/proofs and access. Details:
../../../docs/SORTING_CENTER_INTEGRATION.md; financial policy:
../../../docs/DELIVERY_FINANCE.md; rollout: ../../../docs/LOGISTICS_TEAM_UPDATE.md.
