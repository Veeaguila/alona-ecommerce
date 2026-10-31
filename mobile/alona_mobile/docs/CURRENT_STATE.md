# Current state

31 October 2026 (Asia/Manila): upstream `4277995` is ordinary source in `mobile/alona_mobile`,
beside Rider. Buyer checkout and Logistics use the existing ALONA backend/data.
Cart and Buy Now share canonical quotes, promotions/vouchers, coverage/hub plans,
locked stock, buyer-funded booking, cancellation and order tracking. Original
mobile theme/assets and checkout form are preserved. No Seller Flutter module exists.

Verified outcomes:
- Final full backend regression: 997 tests / 57,614 assertions passed in SQLite
  memory, including new auth/parity coverage and the prior checkout/hub contracts.
- Final129 frontend tests passed serially and Vite production build passed; helper7 passed.
- Final combined Flutter51 tests passed, analyzer clean, rebuilt profile web passed.
- Final actual compiled browser journey passed:1 fixture /18 assertions, no JS
  errors. Mobile Buyer login/cart/checkout → Seller web → Logistics web/mobile →
  Rider APIs/photo/Buyer mobile OTP → Delivered in all views → mobile Buyer receipt
  confirmation. One order/parcel/proof, stock 10→8; order/item become Completed
  after receipt while parcel remains Delivered.
- That journey continued through compiled Rider earnings/receiving instructions,
  owner web grouped manual payout/proof/reference, Rider receipt confirmation and
  the same payment in Logistics mobile. Synthetic amounts only; no actual transfer.
- All 148 upstream files remain; original theme/assets unchanged; 99 migration
  original fingerprints match; the current manifest covers100 including the new
  unapplied payroll delta. No application DB write/migration, commit or push. Prior
  Git HEAD remains `d117595`; new integration work is uncommitted.

Details: parent `docs/testing/results/MOBILE_ORDER_FLOW_REVIEW.md` and
LOGISTICS_PARITY.md. Private test receipts are under `.local` in the parent.

31 October payroll addition: owner Operations now includes Rider payouts using
the same backend as the web owner page. It supports blank/disabled configured
future rates, completed-job review, grouped manual preparation, recipient details,
image proof/reference/actual-payroll confirmation and unsent cancellation. New
payroll migration remains unapplied to application data; original99 migration
sources remain unchanged. Combined Flutter51 tests passed. The gallery picker now
handles denial/failure and pending duplicate actions without recording a payment.
Shared regression/build/browser evidence: parent POST_IMPLEMENTATION_REVIEW.md and Rider's
active integration bundle. Preserve prior design/assets and all earlier fixes.

Broader review completed: OTP replay/already-verified token bypass and unverified
Google identity claims blocked; canonical purchase reviews, voucher eligibility,
purchase limits/reorder, missing Buyer chat routes and participant-scoped order
snippets fixed. Mobile actions guard pending writes, old responses and closed
screens; chat handles drafts, stale polls and foreground/background lifecycle.
Original theme/assets/layouts are preserved. The Google button now explains that
sign-in is unavailable instead of displaying the imported insecure email form.

Remaining operational acceptance:
- Proper mobile Google provider/token integration is pending; email/password and
  verification/reset remain available. If old vulnerable endpoints were used,
  the deployment owner must review/revoke affected existing Buyer sessions with
  explicit authorization; the patch does not revoke old tokens or approve legacy
  Google-created accounts automatically. See the parent report/handoff.
- Native Android/iOS packaging, launcher/icon resources, signing and real-device
  camera/gallery/lifecycle remain pending. Prior Android NDK setup is incomplete;
  browser acceptance does not certify a native release.
- Online wallet/card orders remain unpaid until actual payment through web.
  Actual payment/email/push delivery and MySQL concurrency are not certified here.
- Company setup/onboarding, exports and full document review still use web. Real
  hubs/routes/staff/coverage and scheduler must be configured by the operator.
- Compare teammates' actual schema/ledger and follow TEAMMATE_AI_HANDOFF.md for
  any approved incremental rollout. This Buyer alignment adds no migration.
- Deploy matching backend/mobile contracts and a reachable explicit API origin;
  checkout retry receipts require a shared lock-capable cache. Cache/client state
  loss is outside the receipt's idempotency guarantee.

Do not commit/push automatically. Checkpoint before future meaningful changes.
