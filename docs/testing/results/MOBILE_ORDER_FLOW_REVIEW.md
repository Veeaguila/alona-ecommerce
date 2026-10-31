# Mobile order integration review

31 October 2026 (Asia/Manila). Active source: `mobile/alona_mobile`, beside `mobile/rider`.
Both clients use the existing ALONA backend and database. These changes are local
and uncommitted; the prior committed HEAD is `d117595`. No application migrations,
seeders, database replacements, push or deployment were performed.

## Verified process

| Step | Behavior |
|---|---|
| Buyer mobile cart / Buy Now | Preview uses canonical regional/weight tariffs, coverage, hub route planning, promotions and vouchers. Booking rechecks stock/prices/shipping/total in the existing transaction. |
| Seller web | Receives the same order and new-order notification. Seller processes, packs and marks Ready for Pickup. No Seller Flutter module exists in the imported app. |
| Logistics web and mobile | See the linked pickup after Seller readiness. Acceptance, eligible pickup dispatch and Rider acknowledgment precede physical collection and sorting-center receipt. |
| Sorting and outbound | Actual service area, eligible delivery Rider and physical readiness checks remain authoritative. |
| Rider | Existing Rider APIs start delivery, accept proof image and require the owning Buyer's private delivery OTP. |
| Buyer mobile and web | Read the same item history and Delivered state. Mobile exposes private OTP and receipt confirmation; cancellation uses the web stock/finance/history rules. |
| Retry | Pending taps are blocked; unchanged retries retain a UUID. The server lock/cache receipt prevents a second booking within its receipt lifetime. Cache/client state loss is not a durable idempotency guarantee. |

The compiled browser acceptance used actual mobile Buyer login/cart/checkout and
mobile Logistics screens, actual Seller/Buyer/Logistics web pages, and actual Rider
API controllers. It completed one order, one parcel and one proof, with stock
changing from 10 to 8. All views agreed on Delivered. One SQLite-memory browser
fixture passed 12 assertions, with no browser JavaScript errors. After verifying
Delivered across clients, Buyer mobile confirmed receipt; order/item became
Completed while the parcel remained Delivered. OTPs and tokens
were kept out of retained evidence. Browser-only input/merged-semantics selectors
were corrected during the check.

## Confirmed defects corrected

- Replaced the old mobile fixed-fee/duplicate booking path, including its incorrect
  price fields and automatic paid flag for non-COD orders, with canonical booking.
- Mobile address writes now use `recipient_phone`, `municipality`,
  `street_address` and rebuilt `address`; full delivery snapshots survive sorting.
- Buy Now works without consuming a pre-existing saved cart line. Failed booking
  rolls back its temporary line. Multi-store results expose all created orders.
- Quote errors block submission; late responses cannot overwrite a newer quote;
  invalid vouchers never display a false success. Shipping options display quoted
  fees, and the unsupported bank-transfer option was removed.
- Web/API preview now applies the free-shipping threshold separately to each store
  order, matching existing split-order persistence; combined subtotals no longer
  display free shipping while saved store orders charge it. Shipping vouchers use
  the same corrected pre-discount fee.
- Buyer APIs reject other roles, suspended and unverified accounts. Product
  variations must belong to the selected product, including the web stock lock.
- Mobile Buyer tracking uses recorded item events, private OTP and scoped order
  ownership. Order-tab activation refreshes data; paged history remains reachable.
- Original branding/theme/assets and checkout form are preserved. Added controls
  use existing components; shared button text fits narrow layouts. Delivered is
  distinct from Completed, displayed timestamps use the device timezone, and a
  late address response cannot update an already closed checkout screen.
- Closed two authentication bypasses: already-verified Buyers can no longer obtain
  a token by submitting an arbitrary OTP, and client-supplied Google email/identity
  claims can no longer authenticate or create an account. OTP consumption/token
  issuance is transactional, expires at the boundary and cannot reactivate a
  restricted account. Valid codes are single-use; verified users sign in normally.
- The imported Google form fabricated identity from an entered email. Its button
  now opens an honest unavailable notice in the existing visual style; the API
  returns 503 without account/token writes. Email/password, email verification and
  password reset remain available. Proper provider integration is still pending.
- Mobile reviews now require the Buyer's delivered/completed purchase, preserve
  the actual variation snapshot, prevent duplicate submission and use canonical
  rating counts/Seller notices. They no longer write nonexistent review columns.
- Voucher claims reject expired/future/ended/code-only or exhausted vouchers;
  duplicate claims do not cause a database error. Validation uses the actual
  destination/weight tariff and product eligibility instead of a fixed discount.
- Cart availability includes product approval, missing/foreign variations and
  Seller vacation status. Writes enforce purchase limits. Reorder adds to saved
  quantities and excludes unavailable/SKU stock instead of overwriting the cart.
- Added the missing mobile Buyer chat routes over the existing web conversations.
  List previews do not mark messages read. Both participants must belong to a
  shared order before attaching it; legacy foreign-order details are hidden on
  reads without deleting the message. Variant/parcel snippets use real columns.
- Mobile cart/order actions block pending duplicates and reload confirmed server
  quantities; stale order-filter responses cannot replace newer results. Login
  and OTP stay pending through session persistence. Closed screens/dialogs ignore
  late responses and own/dispose their input controllers with the route.
- Chat pauses polling in the background, rejects stale pre-send reads, preserves
  a failed draft without overwriting new typing, and rebuilds the send control
  for programmatically inserted emoji. Original screen styling is retained.

## Verification and limits

Final full backend regression passed **980 tests / 57,272 assertions** across 11
SQLite-memory batches, including 16 new auth/parity tests. All **48 combined Flutter
tests** passed, including 12 new delayed-response, pending-action, disposed-screen
and chat lifecycle cases. Final checks also passed: **124 frontend tests**, clean
Flutter analysis, rebuilt isolated profile web and the compiled browser fixture
**1 test / 12 assertions**, with no browser JavaScript errors. Checkpoint helper
**7 tests** passed. The earlier Vite production build remains valid; this follow-up
changed no frontend web source. All 148 original upstream files
remain; original theme/assets and all 99 migration fingerprints are unchanged.

All automated backend checks use SQLite `:memory:` with synthetic accounts, array
mail/cache and fake storage. No development/production data is used. Local private
receipts live under `.local/mobile-repo-inspection` and `.local/system-verification`.

Online payments currently require the web flow and remain unpaid until payment.
Real payment/email/push delivery, MySQL contention, actual company configuration,
teammate schema differences and Android/iOS device packaging are not certified by
these tests. Native launcher/icon assets and the known Android NDK setup remain
separate prerequisites. Clients must target the same reachable backend; update
the server and mobile source together. No new database migration is required by
the Buyer API alignment itself.

If the old public OTP/Google endpoints were previously reachable or used, the
deployment owner must assess existing Buyer tokens and approve scoped revocation
and reauthentication. This patch prevents new bypass issuance; it does not revoke
previously issued tokens. Google-issued `alona-buyer-mobile` tokens can be identified
by name, while `buyer-mobile-token` was also used by legitimate password logins;
name alone cannot distinguish insecure OTP tokens. Do not delete live tokens or
change legacy account approval states automatically. Review old Google-created
`active` accounts separately; never mass-convert them to approved.
