# Logistics mobile process alignment

Source: Veeaguila/alona-mobile main `4277995`, imported 30 October 2026 as ordinary
`mobile/alona_mobile` source beside Rider. One ALONA backend/database remains the
authority for web and mobile. Buyer API adapters now share the canonical backend
checkout and order behavior. No migration, environment setting, production data,
commit or push was changed by this integration.

| Existing web process | Native mobile behavior |
|---|---|
| Company approval and Hub Staff boundaries | Current profile checked at entry/resume; Staff mount only the hub workspace |
| Accept Seller pickup request | Explicit acceptance; request confirmation does not mark collection or arrival |
| Pickup dispatch | Manual API-eligible Online Rider or nearest offer; Rider still acknowledges/collects in Rider app |
| Physical sorting-center receipt | Confirm the looked-up parcel reference after physical arrival; hub-routed parcels use the selected hub workspace |
| Hub transport | Server-selected next action, explicit operating hub/physical confirmation, actual transport reference and request key |
| Destination sorting | Actual existing city service areas; backend enforces destination/custody matching |
| Delivery assignment | Canonical sorted-unassigned queue and eligible-riders only; no arbitrary parcel/fleet fallback |
| Outbound readiness | Requires arrived/sorted/assigned parcel; records readiness without starting Rider delivery |
| Monitoring | Laravel paginated records, hub transit/lost/damaged statuses and private authenticated proof photos |
| Rider decisions/coverage | Real rejection/suspension reason, server approval/Online/capacity rules, existing delivery-area API |
| Supervised custody change | Both Rider acknowledgments must exist before Logistics confirmation |
| Delivery/incident exceptions | Existing privileged override and lost/damaged audit endpoints; damaged incident needs actual photo |
| Return to Seller | Current verified custodian, actual reason; only Seller physical receipt restocks inventory |
| COD | Actual cash amount plus receipt image; declared shortfalls go through existing Admin review; no payout inferred |
| 30-minute inactivity | Backend request-idle policy retained; scoped401 clears protected routes; no keep-alive heartbeat |

Phone-specific changes preserve the teammate's Flutter styling and use bottom tabs,
cards, scrollable queues, confirmations and appropriately sized narrow layouts.
Queues reload on tab activation. Reasons live in dialogs whose controllers are
disposed with the route; pending actions block duplicate submissions.

## Verification

- The earlier Logistics suite passed 29 mock HTTP/widget tests. Buyer alignment
  replaced its temporary checkout guard test with real quote/order/OTP checks;
  current final counts are in CURRENT_STATE.md and the integration checkpoint.
- 50 focused existing backend tests / 817 assertions passed in separate SQLite
  memory processes with a test-only 1024 MB PHP allowance.
- Flutter analysis: clean after the integration fixes. Profile web build passed.
- Tests never use the existing application database; backend verification uses
  SQLite `:memory:`. No development/production API was contacted.
- Android manifests and iOS plist parse as XML; native device behavior is unverified.
- Final compiled browser journey passed against actual Laravel controllers and
  SQLite memory (one browser fixture / 12 assertions): real mobile Buyer checkout,
  Seller web preparation, web Logistics visibility and mobile Logistics login/acceptance/pickup dispatch,
  physical arrival, sorting, eligible last-mile assignment and outbound readiness;
  existing Rider APIs upload a photo and complete with the Buyer mobile private OTP. Final
  mobile monitoring displays Delivered and the complete Buyer address. No browser
  JavaScript errors. Buyer mobile confirms receipt afterward; order/item become
  Completed while parcel stays Delivered. The fixture verifies one order/parcel/proof and stock 8.
- All 148 fetched upstream files remain present. Source-only audit verified all 99
  migration fingerprints; Git HEAD remains `d117595`. No nested Git metadata.

Private local evidence is under `.local/mobile-repo-inspection`: final browser
result/screenshot, per-file PHP summaries and source receipt. Tracked mobile tests
are in `test/`; backend fixture/contracts remain in the existing parent test suite.
The private browser driver is an observation harness, not a deployed server.

## Boundaries and pending work

- Buyer cart and Buy Now now use canonical quotes and booking. Seller uses the web
  portal; no Seller Flutter module exists in this upstream revision. Online payment
  completion remains in web; selecting a wallet/card never marks an order paid.
  See the parent `docs/testing/results/MOBILE_ORDER_FLOW_REVIEW.md`.
- Company setup (hubs/routes/staff/shipping tariffs), onboarding/resubmission, exports and full
  document/PDF review remain in the existing web portal. This is operational flow
  parity, not a claim that every administrative page has a native counterpart.
- Android/iOS packaging, camera/gallery behavior, native icon/signing configuration
  and real-device acceptance are pending. A web profile is not an APK/iOS result.
  The upstream revision omits platform launcher/icon PNGs; complete the native
  asset catalogs/resources before native packaging.
- MySQL concurrency, actual teammate schema/ledger rollout and real operational
  acceptance remain outside isolated evidence. The original import added no schema;
  the later approved payroll feature adds one unapplied additive migration. Owner
  Operations now includes job-rate setup, grouped manual payout review/recording
  and cancellation. See parent docs/RIDER_PAY_IMPLEMENTATION.md and the payroll
  review report. Preserve existing data and follow TEAMMATE_AI_HANDOFF.md.
- New source remains uncommitted until explicitly authorized. No automatic push.
