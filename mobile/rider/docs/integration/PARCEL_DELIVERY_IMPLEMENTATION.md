# Parcel scanning, PHOTO completion and Seller delivery notices

2026-10-16 — implemented locally in six checkpointed batches. Existing ALONA API,
database, parcel references, UI colors/navigation and shipment finance retained.
No schema changes, actual migrations, historical backfill, commits or pushes.

## Capabilities

| Capability | Local result |
|---|---|
| Phone QR/barcode scanning | IMPLEMENTED; physical Android/iOS camera NEEDS DEVICE TESTING. Approved mobile_scanner pinned to 7.4.2, bundled on-device Android decoder. |
| Resolve scanned parcel | WORKING in tests: existing ITEM- reference → owned lookup → existing delivery screen filtered to that parcel. No new identifier/table. |
| Assignment authorization | WORKING in tests: Sanctum + approved Rider middleware, owned lookup and existing locked mutation checks. Unknown/unassigned return indistinguishable 404. |
| Capture then confirm | WORKING in tests: Mark Delivered opens capture when no valid PHOTO, upload returns to server revalidation, then explicit confirmation. Upload failure/cancellation never delivers. |
| Proof validation/storage | WORKING in tests: required PHOTO + accessible private image at completion; duplicate identical upload retries reuse proof/file. Invalid/missing/signature-only proof rejected. |
| Cross-role status | WORKING in integration tests: existing parcel → linked item/history → Buyer notice and parent aggregation; Seller notice added atomically. Rider refresh/success screen and Logistics shared status preserved. Live four-role acceptance pending. |
| Seller details/proof | WORKING in tests: owned delivery notice links to existing Seller item page; private photo route verifies seller/product/item/parcel/proof ownership. Other Sellers, including same-order Sellers, denied. |

Camera runs only in the native Android/iOS app in this implementation. Laptop Edge,
Windows/Linux and other targets use manual ITEM-reference entry; no browser barcode
decoder/CDN is started. Proof capture in Edge continues real-photo file selection.
Physical labels must encode the existing ITEM-reference; label generation is not added.

## Additive APIs / behavior

- GET /api/rider/deliveries/lookup?reference=ITEM-123: approved assigned Rider only;
  existing limited delivery/address DTO. 422 invalid/not-ready, 404 unknown/unassigned,
  409 already completed/returned. GET is read-only and rate limited.
- Active deliveries and lookup retain has_delivery_proof and add
  has_valid_photo_proof. The new flag requires a Rider-matching PHOTO whose private
  file is readable, nonempty, within existing size/dimension limits and recognized
  as an allowed image. Proof model relationships/file paths are not added to reads.
- Existing upload-proof and deliver POST routes retained. Upload validates image,
  size/dimensions, assigned Rider and OUT_FOR_DELIVERY under parcel lock. Matching
  type/file-content retries reuse the existing record, with updated notes. File
  cleanup on DB failure retained. Signatures remain storable for compatibility but
  cannot satisfy this photo-required completion. Failure code: valid_photo_required.
- Existing completion transaction now inserts one SellerNotification per item
  delivery, using existing type/url fields. Parcel lock/one-way transition and
  stable notification lookup prevent retry duplication. Notification persistence
  failure rolls back parcel/item/history/Buyer notice; no success/earnings is reported.
- GET /seller/orders/{orderItem}/delivery-proofs/{proof}: existing Seller web session,
  verified product Seller == parcel Seller == shipment Seller (when linked), matching
  item/parcel/current assigned-Rider PHOTO and delivered status. Private/no-store,
  nosniff response; no public storage URL. Invalid/missing/unowned files return 404.
- Existing Seller item details add deliveryDetails metadata/relative protected URLs
  and deliveryLinkUnverified; never a private filesystem path. Unverified parcel
  relationships are omitted from the response. Existing notification read route is
  reused before navigating to the owned item page.

Legacy records without a valid item/product/Seller link are not guessed, notified or
given Seller proof access; the condition is logged and a safe page message explains
unverified links. Completed historical statuses/proofs are never rewritten. Parent
order completion after all active items are delivered remains existing ALONA policy.
Fees, split percentages, payout/collection rules and recognition formulas are unchanged.

## Changed files in this batch

- Rider: parcel_scanner_page.dart, delivery_success_page.dart, rider_parcel_api.dart;
  delivery_dashboard_page.dart, proof_capture_page.dart, api_error.dart;
  pubspec.yaml/lock, iOS camera description and generated macOS plugin registration.
- ALONA: RiderDeliveryController.php, DeliveryProof.php, SellerOrderController.php;
  new SellerParcelDelivery.php and SellerDeliveryProofController.php; api/seller routes;
  Seller/OrderDetails.vue and Seller/Notifications.vue.
- Tests: parcel_scanner_test.dart, delivery_confirmation_test.dart; updated delivery
  workflow tests; RiderParcelLookupTest, DeliveryPhotoIntegrityTest and
  SellerDeliveryNotificationTest; existing completion/finance proof fixtures now
  contain real isolated test image files instead of nonexistent paths.
- Recovery/architecture/current-state/readiness documentation updated separately.
  Preserve prior unrelated/Phase 7 local hunks when reviewing any later commit.

## Verification

- 72 focused Flutter tests PASS: scanner duplicates/manual/coded errors/ownership
  navigation, capture/upload/revalidation/confirmation/failure/lost-file retry, plus
  existing address/pickup/history/earnings/profile/feed/session/error behavior.
- Final 59 Laravel tests / 537 assertions PASS in enforced SQLite :memory:, including
  unchanged finance and Buyer/Logistics integration. No actual database was used.
- Affected Dart analysis is clean; Flutter web release compile PASS with a reserved
  placeholder API origin (not live API acceptance). Both changed Seller Vue
  script/templates compile. iOS camera/photo keys remain exactly one each.
- Final changed-file review: 52 Rider / 55 ALONA files; no credential-pattern
  matches, both whitespace checks PASS and both staging areas empty. Existing
  ALONA public CA bundle is unchanged. Scoped evidence is in ignored recovery logs.
- Android debug build BLOCKED: no Android SDK configured on this laptop. No SDK,
  Java/toolchain configuration or additional package was installed. iOS cannot build
  on this Windows host. Native build/camera behavior is not claimed as verified.

## Remaining acceptance / release

1. Configure an approved Android SDK/toolchain outside this task; build/debug on an
   actual Android phone. Package requires Android API 23+ and compile SDK 36/Java 17;
   app follows Flutter SDK defaults. iOS target is already 15; build on macOS/Xcode.
2. Test QR and Code128 ITEM labels, repeated/adjacent labels, manual entry, invalid,
   other-Rider/unassigned/completed codes, permission denial/settings/retry, rotation,
   background/resume/route return, camera cancellation and physical proof capture.
3. Test actual HTTPS API/session expiry/offline retries: failed upload never delivers;
   lost private photo forces recapture; repeated completion never duplicates finance.
4. With authorized nonproduction accounts, refresh Rider/Logistics/Buyer/Seller;
   inspect item history and Seller delivery notice, read/link, private photo success
   and another-Seller denial. Verify full manual settlement remains separate.
5. Identify the actual populated ALONA database before any approved deployment.
   This batch needs no new migration; previously prepared Phase 7/feed migrations
   still require actual-schema inventory and separately approved matching-engine
   rehearsal/migration. Deploy compatible backend before this client: PHOTO-only
   enforcement and the new eligibility flag are intentional requirements for new
   completion attempts. No historical completion backfill or return-policy change.
6. Production-engine races remain manual: same-parcel upload/complete/retry, Seller
   notice uniqueness under the parcel lock, and existing shared-shipment/settlement
   races. SQLite verifies contracts/rollback, not InnoDB locking behavior.

Seller notices are atomic in the existing transaction. The previously implemented
Rider feed remains best-effort after commit without durable replay or background push.
