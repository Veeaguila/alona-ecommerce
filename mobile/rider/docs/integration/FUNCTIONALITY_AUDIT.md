# Rider functionality audit — 2026-10-16

WORKING means verified in scoped automated tests, not a live deployment. Existing
colors, operational rules, history and backend-only earnings are preserved. No
preview/demo accounts or runtime data were introduced; actual databases are untouched.

Latest parcel follow-up: [PARCEL_DELIVERY_IMPLEMENTATION.md](PARCEL_DELIVERY_IMPLEMENTATION.md).
72 Flutter tests and 59 Laravel tests/537 assertions PASS, affected Dart analysis
clean, Seller Vue and web compilation PASS. Android build blocked by missing SDK;
native scanning/camera and live role-to-role acceptance remain pending. Earlier
batch counts below remain historical evidence, not additional unique test totals.

| Feature | Result and evidence |
|---|---|
| Registration/login | WORKING canonical registration, role, password and legacy-email contracts; onboarding/session tests. Real email delivery PENDING. |
| Approval/readiness | WORKING approved/active/verified operational checks. Pending users can inspect their account with a valid token. Address changes require Logistics reapproval. Rejected fresh login remains restricted by existing policy. |
| Pickup requests/acceptance | WORKING own-center availability, single acceptance, conflict refresh and confirmed pickup; PHP/Flutter tests. Pickup failures now scroll into view. |
| Sorting-center handoff | WORKING backend workflow; Logistics owns arrival acknowledgement. Physical handoff REQUIRES DEVICE TESTING. |
| Delivery assignment/start | WORKING assigned rider/area and shipment consistency; assignment/finance/workflow tests. |
| Camera/proof | WORKING native entry starts camera; preview/retake, busy guards, permission errors and validated private upload tested. Native camera REQUIRES DEVICE TESTING. Web/desktop select real photos and explain camera limits. |
| Parcel scanning | IMPLEMENTED phone QR/barcode plus manual ITEM-reference lookup, own-assignment authorization and parcel-specific details; physical scanning REQUIRES DEVICE TESTING. See PARCEL_DELIVERY_IMPLEMENTATION.md. |
| Delivered action | WORKING in tests: mandatory PHOTO capture when absent, backend refresh, explicit confirmation and success page. Laravel requires accessible private PHOTO; duplicate upload/completion guarded. Historical deliveries and earnings rules unchanged. |
| Seller delivery notice/proof | WORKING in tests: atomic existing SellerNotification, owned order-detail link, private photo access and multi-Seller isolation. Unverified legacy links are safely omitted; live browser acceptance pending. |
| Failed delivery | WORKING existing reason submission and FAILED_RETURNED history. Separate failed/returned states and rescheduling NOT IMPLEMENTED in Rider; no business workflow invented. |
| Address details | WORKING Maps previews/buttons removed. Correct seller collection vs order delivery snapshot, party contacts and structured fields; Not provided for missing fields. No guessed postal/instructions or current buyer-address substitution. |
| Earnings/payout history | WORKING ALONA totals/pending/paid/history only; financial regressions passed. Settlement remains approved-admin-only; live acceptance PENDING. |
| Delivery History | WORKING owned history, pagination/status counts and earnings link preserved; regression tests. |
| Chat | WORKING existing recipients/conversations/send/read ownership, safe errors and unread messages. Refresh available; live multi-device chat PENDING. |
| Notifications | WORKING stored own Rider feed, read/unread/count, local timestamps, pagination/deduplication, tap targets and 30-second polling while its screen is foreground/current. Migration not deployed. |
| My Account | WORKING actual profile fields, private photo read/upload/replacement, contact updates and canonical address editor. Identity/email/birthday/vehicle/plate/role/approval/coverage/finance protected; missing fields explicit. |
| Logout/session | WORKING server confirmation, failed-logout retry, expired-token clearing, origin restrictions and readiness; regression tests. |
| Loading/empty/errors | WORKING scoped widget/API cases: busy guards, empty/error/retry states and sanitized errors. No fabricated earnings or unread count on initial failure. |
| API connectivity | WORKING contract/origin tests; live HTTPS/CORS/device reachability PENDING. Populated ALONA database still unidentified. |
| Complete live workflow | PARTIALLY WORKING: local API/widget integration verified; production schema, native device and live service acceptance remain pending. |
| Background/push | NOT IMPLEMENTED; no provider/background service/device-delivery evidence. No external service added. |

No unresolved confirmed BROKEN defect remains in the changed scope. This is not
full live-system acceptance. Buyer confirmation before parent-order completion in
the ERP flow remains a pre-existing workflow difference; this task preserves ALONA's
existing aggregation/financial rules.

## Notification event coverage

Previously notifications synthesized chat/active-task cards. The new feed uses
ALONA rider_notifications, linked to shared users; no separate backend/database.

| Event | Implemented source and target |
|---|---|
| New pickup | Confirmed PickupRequest → eligible riders at its parcel center → Pickups |
| Pickup acceptance | Parcel pickup-rider assignment → owning rider → Pickups |
| Delivery assignment/ready | Parcel delivery-rider assignment; AT_SORTING_CENTER adds ready title → Deliveries |
| Pickup/status/handoff | Parcel status change → associated pickup/delivery riders → relevant screen |
| Delivered/failed | DELIVERED / FAILED_RETURNED → associated riders → History |
| Approval/rejection/activation/verification | Changed User/Rider state → own My Account; paired transactional state updates deduplicate |
| Logistics message | New SortingCenterMessage → Rider recipient → conversation; message text stays in scoped chat |
| Earnings/payout | Earning creation/paid transition → owning Rider → Earnings; amounts come from backend records |

Facts are captured before later model saves and published after outer commit.
Rollback emits nothing. Unique(user_id,event_key) prevents duplicate event insertion;
read timestamps update once. Failures are logged without reporting a committed
financial/task action as failed. This is best-effort after-commit delivery, without
a durable outbox/replay worker: a process crash between commit and insertion can lose
a notice. Task/earnings records remain authoritative. No historical backfill.
Pending/rejected own account notices require a valid token. Fresh rejected login
remains disallowed by existing policy; no authentication bypass was introduced.

## Camera findings

The original dashboard opened the proof screen without opening its camera; another
button press was required. Desktop image_picker has no default live-camera delegate;
a browser picker cannot guarantee live camera capture. Native entry now requests
the camera automatically; unsupported targets explicitly select real photos.
Android needs no extra manifest permission for this picker path; iOS usage keys
already exist. User confirmed the reported failure occurred on a laptop in Microsoft
Edge. This browser flow selects a real photo; live webcam capture is NOT IMPLEMENTED.
The report does not establish an Android/iOS permission or hardware fault. Native
camera capture still requires physical-device acceptance.

## Verification and manual acceptance

- 63 focused Flutter tests PASS: proof, addresses, pickup/delivery, profile,
  notifications, chat, history, earnings and session/readiness/logout.
- Laravel: overlapping batches of 52 tests/411 assertions and 52 tests/338 assertions
  PASS. Analysis of all 14 affected Dart files is clean.
- Changed-file review: 40 Rider and 46 ALONA files, no credential-pattern matches;
  both Git whitespace checks pass, both indices empty. Existing public CA certificate
  is the only sensitive-extension tracked path; no private-key pattern found.
- Exact Laravel/static/security logs are recorded in the recovery checkpoint.
  Intermediate fixture/harness failures remain in logs, superseded by confirmed runs.
- Tests use mock HTTP or enforced SQLite :memory: only. No live account/database
  writes, commits, pushes, configuration switches or actual migrations.

Use authorized nonproduction accounts after operator deployment:

1. Android/iOS: allow/deny/retry camera, cancel/retake/preview/upload/return/deliver;
   invalid/missing proof must fail. Test interruption/activity recreation, gallery
   permissions, small screens, keyboard and large photos. Browser/desktop must show
   file-selection fallback, never promise live camera.
2. Real API: HTTPS/device reachability/CORS, expired token, private proofs/photos,
   another-rider denial, 413 limits and interrupted network/retry.
3. Logistics: confirm pickup, accept/confirm, mark arrived, assign/start, proof/deliver
   or fail. Inspect feed/read count/timestamps, refresh, tap targets and duplicates.
4. Email/account: verification email, approve/reject, deactivate/reactivate; update
   contact/photo and address/reapproval. Documents/vehicle/coverage stay unchanged;
   uploaded photo survives Google sign-in.
5. Finance/Admin: final noncancelled proven parcel creates one shipment earning;
   record full seller collection and rider payout separately; repeat requests must
   not duplicate. Rider refresh shows backend pending/paid and payout notice.

Deployment is BLOCKED on actual populated database identity/schema and an approved
matching-engine rehearsal. New notification migration/API rollout details are in
ALONA docs/RIDER_FUNCTIONALITY.md. Prior Phase 7 MySQL evidence does not validate
this new migration or current profile/notification concurrency on production MySQL.

## Safe review/commit boundaries — no commits made

1. ALONA: address DTO/controller additions; notification migration/model/observer/feed
   routes; private profile/photo/address API plus Google-photo preservation and tests.
   Deploy compatible backend/schema before the updated client. Preserve existing
   Phase 7/teammate hunks in shared controllers/routes; do not stage all blindly.
2. Rider: camera/proof, Address Details, notifications/account/address pages, focused
   tests and related docs. Existing finance/history changes remain separate local work.
3. Memory/recovery docs: architecture/current state/readiness plus generated atomic
   bundles and .previous backups. Keep recovery history; no actual secrets or database
   files belong in a later commit.
