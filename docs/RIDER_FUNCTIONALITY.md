# Rider functionality integration — 2026-10-16

Local implementation only. Shared ALONA Laravel API/database remains authoritative;
no application database configuration or records were changed by this work.
Rider docs/integration/FUNCTIONALITY_AUDIT.md contains the screen/manual checklist.

## Parcel delivery follow-up — 2026-10-16

No new schema or actual migration. Full scoped implementation/files/tests/manual
acceptance: Rider docs/integration/PARCEL_DELIVERY_IMPLEMENTATION.md.

- New GET /api/rider/deliveries/lookup?reference=ITEM-123 under existing Sanctum +
  approved Rider middleware. Resolves unique parcels.order_id, never a parent order
  ID; returns owned active delivery/address DTO. Invalid 422, unknown/unassigned 404,
  completed/returned 409. Sorting Center privileges/arrival lookup are unchanged.
- Existing list/lookup retain has_delivery_proof and add has_valid_photo_proof.
  DeliveryProof::isValidPhoto checks PHOTO, owned private location/readability and
  existing size/dimension/image limits. Current completion now rejects signature-only,
  missing/inaccessible/invalid files; no completed historical row is revalidated or
  rewritten. Identical upload retries reuse a record/file under the parcel lock.
- Existing delivery transaction retains parcel/item/history/Buyer updates and finance
  rules, adds SellerParcelDelivery::notifyDelivered. Existing SellerNotification
  type/url fields plus one-way locked completion provide retry deduplication.
  Persistence failure rolls back the delivery; no false success/accrual. Correct
  Seller requires item/product/order link, matching parcel and shipment Seller when
  present; missing/mismatched legacy ownership is logged/skipped, never guessed.
- Existing Seller item page adds safe deliveryDetails/proof metadata and notices.
  New session-authenticated GET /seller/orders/{orderItem}/delivery-proofs/{proof}
  verifies Seller/item/product/parcel/proof ownership and delivered/current Rider
  PHOTO, then serves private/no-store/nosniff bytes. No public URL/changed disk.
  Seller notification page reuses existing read route before opening the detail link.
- User explicitly approved only mobile_scanner 7.4.2 (pinned); no unrelated package
  upgrades or infrastructure introduced. Android debug build is blocked by missing
  Android SDK; current focused checks pass but native/live acceptance remains pending.
- Deploy compatible backend before the new client. PHOTO-only completion is an
  explicitly approved new-attempt requirement, not a historical backfill. Previously
  prepared finance/Rider-feed migrations still need approved actual-schema rehearsal;
  this scanner/Seller-proof batch introduces no migration.

## Additive API contract

Sanctum required; own Rider role/profile enforced even for pending/rejected users.
Existing login restrictions remain. Operational APIs still require approval/readiness.

- GET /api/rider/notifications?page=1&per_page=30: own persisted rows, unread_count,
  current_page/last_page. Per-page maximum 100, private/no-store.
- POST /api/rider/notifications/{uuid}/read: own row only, idempotent first read_at.
- GET /api/partner/profile: existing response preserved, adds rider_profile with
  real contact, birthday, address/address_fields, vehicle/plate, photo availability
  and optional existing HTTPS public avatar URL. No private file path exposed here.
- PATCH /api/rider/profile: contact_no and/or canonical address only. Address uses
  existing PSGC validation: region/province/city/barangay codes and manual house,
  street, subdivision, optional four-digit postal. A changed address sets existing
  user/rider approval pending; unchanged address does not. Vehicle, service areas,
  center, documents, identity, account roles and financial facts are not editable.
  Rider→User locks match Logistics approval order; application details/address merge.
- POST /api/rider/profile-photo: photo multipart JPEG/PNG/WebP, maximum 5 MB and
  2000×2000. Uses existing users.avatar, private local rider-avatars/{user-id} files;
  rollback removes the new file, prior owned file removed after commit. Owner-only
  GET /api/rider/profile-photo returns private/no-store/nosniff bytes. Google login
  serializes on User and preserves a Rider-selected private photo; other roles retain
  their existing avatar behavior. No extra profile schema/migration.
- Pickup available/mine and active deliveries add address_details. Seller collection
  snapshot stays authoritative; seller profile structured fields supplement only a
  matching location. Delivery uses parcel/order snapshots, never buyer current home.
  Party names/contacts use limited existing account fields. No guessed recipient,
  postal code or instructions; unavailable fields are null. History is unchanged.

## Notification persistence

New migration: 2026_10_16_100000_create_rider_notifications_table.php. Adds one table
with UUID id, users FK, unique(user_id,event_key), type/title/body/data, read_at and
timestamps/indexes. No existing records are rewritten and no backfill runs.

RiderNotificationObserver captures immutable source-event facts and publishes after
outer commit for PickupRequest, Parcel, Rider, User, SortingCenterMessage and
RiderDeliveryEarning. Confirmed pickups fan out only to eligible same-center riders;
assignment/status/account/chat/earnings events target associated riders. Paired
account updates and replayed source events deduplicate. Finance values come from
persisted backend records; no payment/provider logic changed.

Publication is best effort: failures log an exception class and do not make a
committed operational/financial request fail. There is no durable outbox/replay
worker; a process crash after commit can lose a notification. Operational/financial
records are authoritative. No background/push provider or delivery guarantee added.
Flutter polls every 30 seconds only on the foreground/current notification screen,
refreshes on resume/return, marks read before navigation and deduplicates paged IDs.

## Existing-database release procedure — PENDING operator approval

1. Identify the populated website's actual configuration/database; inspected local
   configuration previously selected empty SQLite. Do not guess/switch .env or
   connect to an unrelated Laragon/Herd schema. Confirm MySQL 8.0/InnoDB patch and
   isolation, migration history and schema with read-only inventory.
2. Obtain approval for a backup and disposable matching-engine copy/rehearsal.
   Restore-test the backup, inspect names/FKs/index collisions and rehearse pending
   migrations on that copy. No approval to migrate actual data is implied here.
3. Prepared migration files include Phase 7 finance plus the new notification table.
   Determine which are pending on the actual schema; do not replay completed ones.
   Prior finance MySQL fixture/race results do not cover the new table/profile changes.
4. Rehearse new notification uniqueness/read races, concurrent same-rider photo
   replacement and Google sign-in, address update versus Logistics approval, plus
   existing finance contention cases. Verify user/order/product/parcel/finance counts
   and historical totals/memberships unchanged. SQLite tests cannot prove row locking.
5. After separate approval for the actual migration, use the normal maintenance/write
   pause and reviewed operator migration process. Deploy schema before serving code
   that reads rider_notifications, then compatible backend and Rider app. In a crash,
   inspect migration rows and actual table/index state before retrying any MySQL DDL;
   do not assume MySQL DDL rolled back. Preserve backups.
6. Verify private storage, HTTPS/CORS, upload limits (PHP/web-server limits must allow
   5 MB avatars and existing 20 MB proof limit), actual email delivery and manual
   Rider/Logistics/Admin acceptance. Roll back compatible code first; do not drop
   financial/notification records automatically after traffic resumes.

Only enforced SQLite :memory: migration/test setup ran in this task. No new actual
or disposable MySQL database, actual migration, record write, commit or push occurred.
Existing finance deployment detail: docs/DELIVERY_FINANCE_DEPLOYMENT.md.

## Focused verification

- Rider: 63 focused Flutter tests covering changed features and preserved workflows,
  earnings/history/session guards PASS. Test photos exist only in tests.
- Laravel: 52 tests/411 assertions PASS for notification/profile/Google/finance/chat/
  assignment; final 52 tests/338 assertions PASS for expanded notifications/profile/
  Google/addresses/pickup/delivery/onboarding. These batches overlap; do not sum them.
- Live database identity, matching-engine rehearsal, physical camera, real SMTP,
  browser/device/API/manual settlement acceptance remain pending.
