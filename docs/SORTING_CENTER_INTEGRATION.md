# Sorting-center integration

Company-owned physical hubs and directed transport routes are prepared separately
from the legacy company/sorting-center account. See [Hub network setup](LOGISTICS_HUB_NETWORK.md)
for the migration review, explicit booking activation, transfer workflow and limits.
Existing coverage cities are not automatically converted into physical hubs.

Imported from `C:\Users\user\Logistics`, following its `docs/PICKUP_INTEGRATION.md`.

## Entry points and compatibility

- The storefront `/`, ecommerce `/login` and `/register`, Inertia entry point, and buyer/seller conversations retain their existing behavior.
- Sorting-center login and registration are at `/sorting-center/login` and `/sorting-center/register`. Their UI uses a separate Vite entry, `resources/js/logistics.js`; operational screens retain the imported layouts.
- Sorting screens serve a static UI shell. All operational data, messaging, reports, and proof files require Sanctum authentication and an approved sorting-center/rider profile. Account `usertype` and account availability are also checked.
- Controllers live under `App\Http\Controllers\Logistics`. Logistics chat uses `SortingCenterConversation` and `SortingCenterMessage`, with separate `sorting_center_*` tables. Existing `Conversation`, `ConversationMessage`, `Message`, and their tables are unchanged.
- `role` remains a compatibility field in logistics API user payloads, derived from `usertype`; no `users.role` column is introduced. Browser credentials use `logistics_auth_token` and `logistics_auth_session`. Logout revokes the bearer token.

## Pickup and order-item contract

Seller **Mark as Ready for Pickup** atomically advances the item and creates a parcel and pending pickup request. An optional collection address overrides the seller profile address. The item receives its parcel reference in `tracking_number` and `Alona Logistics` in `courier_name`, so the existing delivery section and the new courier banner agree. The seller details screen displays the courier reference and pickup/courier statuses.

For API clients, `POST /api/seller/pickup-requests` accepts `order_item_id` and optional `collection_address`. It verifies product ownership and requires `ready_for_pickup`. Repeated requests reuse the existing parcel and pickup. Buyer identity and recipient address come from the item's parent order; client-supplied buyer/order/destination values cannot override them.

`parcels.order_item_id` is unique and links to the existing item. The logistics `order_id` string is the stable scan reference `ITEM-{order_item_id}`, rather than the shared parent order ID. This avoids collisions for multi-item and multi-seller orders. Null item links support standalone legacy parcel records; new seller requests always have a link.

The ecommerce order's free-text `shipping_address` is preserved in `delivery_address`. Collection location is stored separately in `collection_address` and returned in rider pickup APIs. The short structured address fields remain available for legacy parcels. When no destination municipality is recorded, the sorting operator reads the full destination and explicitly selects or creates its city service area; sorting saves that municipality. It never substitutes the buyer's current profile address for the recipient address.

| Logistics transition | Ecommerce item behavior |
| --- | --- |
| Request confirmation | Remains `ready_for_pickup`; legacy `PENDING_PICKUP` becomes `PLACED` |
| Rider physical pickup (`PICKED_UP`) | `shipped` |
| Arrival, sorting, assignment | Remains `shipped`; parcel remains `AT_SORTING_CENTER` |
| Rider delivery start | `out_for_delivery` |
| Proof-backed delivery completion | `delivered` |
| `FAILED_RETURNED` | Retains the existing fulfillment status, records failure history, notifies the buyer, and exposes the parcel failure on seller details |

Successful item transitions use `OrderItem::moveTo()`, preserving timestamps, history, buyer notifications, and parent-order aggregation. A pending sibling item keeps the parent order pending. `delivered_at` records completion time for new deliveries; historical values remain null. Reports retain sorting-date grouping.

Existing ready-for-pickup items can be enrolled through the seller pickup API. No development accounts, parcels, or database changes were seeded during integration.

## Approval and storage

New center/rider registrations create pending accounts and profiles. Existing admin Applications now includes sorting centers; approval/rejection updates the linked logistics profile. A center can approve/reject only its own rider applications. Rider availability and pickup jobs remain scoped to the registered center, and delivery assignment requires matching rider areas.

Proof uploads remain on the private `local` disk. Authenticated file URLs and `ProtectedProofImage.vue` retain the imported protected-image behavior. Do not import proof files into public storage.

## Flutter Rider integration (2026-10-15)

The sibling Rider client uses this ALONA API/database with configurable URLs and
explicit HTTPS for release. Pickups use collection addresses; deliveries use the
order destination. Registration reuses canonical partner/address/coverage fields,
private documents, conditional motor paperwork and the existing optional sex field.

Sanctum logout is confirmed before local credentials clear; invalid tokens clear and
unconfirmed failures preserve credentials for retry. Current profile checks gate
operations. Assignment and messaging eligibility match role, approval, availability
and conditional verification rules, preserving the legacy verification exemption.
Login retains exact legacy email matches and supports canonical new-registration
emails. Buyer/Seller/Admin/Logistics interfaces and shared state remain in place.

Assigned deliveries expose has_delivery_proof. Proof uploads use private storage,
parcel locks and cleanup after record failure. Existing deliveries?view=history adds
owned bounded history/counts; default active lists are unchanged. Existing chat and
profile endpoints support Rider messaging and contact/status. The 2026-10-16 update
adds persistent owned Rider notifications and private photo/canonical-address APIs;
address changes require Logistics reapproval. Address Details DTOs preserve seller
collection and order delivery snapshots. See RIDER_FUNCTIONALITY.md for the new
notification migration, tests, deployment and remaining device checks.
Phase 7 shipment finance is implemented in this same backend;
see DELIVERY_FINANCE.md for the three new tables, migration and scoped APIs.
No separate backend/database was added. The migration is not applied outside tests.

Verification: 60 Rider tests, 119 relevant in-memory Laravel tests (45,938 assertions),
7 recovery/safety tests, clean Flutter analysis and release JavaScript web compile
passed. Device/camera, real SMTP/API/CORS and full real-world workflow remain manual.
PHP currently allows 2M/file and 8M/request; full application limits require operator
configuration. Optional WASM needs a compatible secure-storage dependency.

Durable phases, Git state, validation logs and next actions are in sibling Rider
docs/integration/RESUME.md and CHECKPOINT.md. Checkpoints flush/replace/verify files,
preserve previous bundles and can restore verified documentation without modifying
source/Git/database. No automatic commit, push or production-data operation occurred.

## Setup and verification

Install locked dependencies, configure the normal Laravel environment, and run `php artisan migrate` before using the module. Five new migrations dated `2026_10_11_100000` through `100004` create logistics tables, separate messaging tables, and `delivered_at`. They do not replace ecommerce schemas. Rollback retains the Sanctum token table because it may be shared by other API clients.

```powershell
php artisan test --compact --filter=Logistics
npm.cmd run test:frontend
npm.cmd run build
```

The logistics tests cover approval, pagination, rider pickup/arrival, sorting, assignment, monitoring, private proof access, delivery completion, failure, reports, login/logout, owned item handoff, mixed-order aggregation, and messaging isolation. Frontend tests cover response ordering, unmounts, concurrent pickup confirmation, pagination, and missing parcel/seller data.

Final verification: all 66 logistics/integration tests and all five frontend tests pass; the production build and `git diff --check` pass. The full `php artisan test` run has 96 tests, with 88 passing and eight pre-existing failures/errors reproduced against unchanged `HEAD` application files:

- `AuthenticationTest::test_login_screen_can_be_rendered`: expects a login page although `/login` redirects to the storefront.
- `RegistrationTest::test_seller_registration_can_be_created_with_pending_review`: stale registration payload.
- `ExampleTest::test_the_application_returns_a_successful_response`: does not migrate its in-memory database.
- `BuyerGuestAccessTest::test_guests_are_redirected_to_login_from_buyer_account_pages`: references undefined `buyer.wishlist` route.
- `GuestMarketplaceTest::test_a_seller_listing_is_publicly_browsable_and_searchable`: fixture account lacks loaded approval status.
- `GuestMarketplaceTest::test_buyer_product_details_display_seller_and_stock_information`: expects server-rendered text from an Inertia page.
- `OrderVariantFlowTest::test_buyer_checkout_creates_order_item_with_variant_metadata`: omits required shipping method and selected items.
- `SellerProductApprovalFlowTest::test_seller_products_require_admin_approval_before_buyer_visibility`: submits an unsupported product status.

These existing tests and storefront/auth behavior were preserved. The test suite has a test-only encryption key in `phpunit.xml`, so an unconfigured checkout can exercise web middleware without a development `.env`.

## Browser pickup verification (2026-10-11)

An actual headless Edge walkthrough passed with temporary approved accounts in `.local/sorting-browser.sqlite`. It exercised storefront seller login, Ready for Pickup with a collection-address override, center login, the pending queue, request confirmation and queue refresh, center lookup and arrival, city-area creation/sorting, and seller status refresh to Shipped. Rider acceptance and physical pickup used the actual rider API; no rider web UI exists in this repository.

The walkthrough found and fixed a parcel field-selection bug in the pickup-list API that omitted `delivery_address` and `collection_address`, plus missing order-item tracking fields in the existing seller delivery section. The API response now includes both addresses and `order_item_id`, and pickup creation populates the item's tracking reference and courier name. Regression tests cover these fixes. Confirmation was verified to leave the parcel `PLACED` and arrival to set `AT_SORTING_CENTER`. No browser JavaScript errors were recorded. Screenshots and results are under `.local/browser/`.

During setup, a blocked PowerShell environment script caused the temporary schema and fixtures to be created in the previously empty default SQLite file. Cleanup verified that only task-created tables/rows were present, moved them to the isolated database, and restored the default database file to its empty state. No existing development records were removed.

The [prepared PR description](PR_DESCRIPTION.md) lists the eight baseline failures and the required teammate confirmations. A walkthrough with the teammates' actual rider application remains necessary before merging. No commit or push was made.
