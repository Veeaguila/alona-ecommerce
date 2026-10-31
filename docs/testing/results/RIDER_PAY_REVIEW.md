# Rider payroll review — 31 October 2026

Latest fixes and verification: [follow-up review](POST_IMPLEMENTATION_REVIEW.md).
The results below retain the initial implementation and shutdown-review baseline.

User-approved unit: **per Seller-order shipment pickup/delivery leg**, regardless
of product/parcel count; transfer pay once per complete explicit manifest. The
reference comparison used PocketFinds commit
`ca70ed18054a722b50933c6188fc09b51dc53977`. Its example numeric rates were not adopted
as runtime defaults. Operational instructions and money boundaries are in
[RIDER_PAY_IMPLEMENTATION.md](../../RIDER_PAY_IMPLEMENTATION.md).

Implemented and reviewed: immutable future assignment snapshots, complete physical
pickup receipts, all-parcel delivery proof/Buyer OTP, explicit closed trip manifests,
integer-cent batch totals, duplicate/retry constraints, exact reviewed payments,
private proof/reference, encrypted append-only receiving instructions, company/Rider
scope, Hub Staff denial, unsent cancellation history, actual Rider receipt and audit
rollback. COD and the historical Seller-funded ledger remain separate. No company
wallet, provider cash transfer, shipping-revenue allocation or profit is fabricated.

Web owner and Logistics mobile use the same company payroll backend; Rider My
Earnings uses owned job/payout APIs and retains the historical Seller-funded page.
Original theme, assets and existing operational screens are preserved. Earnings
refresh on entry/resume; failed refresh hides stale amounts. Pending writes and
dialogs guard duplicates; mobile payment dialogs cannot be dismissed during writes.

Confirmed checks:

- Payroll backend: **14 tests / 240 assertions**, SQLite `:memory:` only. Covers
  multi-product Seller shipment paid once per leg, whole-trip receipt, rate changes,
  optional dates, large batch totals, held/revoked accounts, private data, foreign
  owners, Hub Staff denial, exact amounts/consent, repeated request/payment/reference,
  cancellation history, immutable snapshots and audit/file rollback.
- Full final backend regression: **994 tests / 57,512 assertions**, SQLite memory.
  This includes checkout/delivery, legacy Seller finance, workload, hub network,
  pickup dispatch, Buyer auth/parity and all 14 new payroll cases.
- Rider: **127 full tests** passed, then **16 affected tests** passed after adding
  the activation/stale-money guard. Final analyzer clean.
- Buyer/Logistics Flutter: **50 tests**, clean analyzer. Both Flutter profile web
  builds passed with explicit loopback targets; Vite production build passed.
- New web payout coverage: **5 tests** passed, including Enter-key payment consent,
  blank rates, pending-duplicate guard, retry key and disposed response.
- Complete frontend: **129 tests** passed serially; checkpoint helper **7** passed.
  The initial concurrent frontend run timed out in the Vite security fixture
  (128/129). The isolated security test and full serial suite passed. Both npm's
  frontend command and the system verifier now run files serially, retaining every
  security assertion and its timeout. Backend results did not require repeating.
  A later final rerun recorded 6893s elapsed and hit the same timeout; a fresh exact
  `npm run test:frontend` rerun then passed all 129 in 19.3s. Both failed and successful
  command outcomes remain in the checkpoint logs; no product assertions were removed.
- Source audit: all 148 upstream mobile files retained, original theme/assets
  unchanged. Original 99 migration sources match their fingerprints; updated manifest
  covers 100 files/five release additions. `git diff --check` passed.

- Actual compiled browser: **1 fixture / 18 assertions**, no JavaScript errors.
  Buyer mobile login/cart/checkout → Seller web preparation → Logistics web/mobile
  acceptance, pickup dispatch, physical center receipt, sorting, eligible delivery
  assignment and outbound → Rider photo/private Buyer mobile OTP → Delivered in
  all views → Buyer receipt/Completed. The same Rider did both legs; My Earnings
  displayed two earned jobs, saved receiving instructions, and the owner web page
  grouped them into one PHP 65.00 synthetic payout with private proof/reference. Rider
  confirmed receipt; Logistics mobile displayed the same paid PHP 65.00 and confirmation.
  The fixture verified one order/parcel/photo, stock10→8, two earned jobs, one paid
  batch and private proof. Rates 25/40/150 existed only in the disposable fixture.
  Multi-product/two-parcel whole-shipment and full-transfer manifests are exercised
  separately by the tracked backend tests, not inferred from this one-parcel browser.
- Browser harness corrections used actual keyboard input for Flutter reference
  fields, accessible combobox roles and merged-text accessibility assertions. Final
  receipts reflect a completed journey; earlier locator failures are not counted
  as successful acceptance or concealed by disabling product/security checks.

The active integration checkpoint records confirmed commands/outcomes. Private
screenshots of the compiled Rider and Logistics mobile payment states were visually
reviewed for phone layout; their existing themes/components remain consistent.

The new migration creates six empty tables and one nullable transfer foreign key;
it was exercised only in isolated test databases. No application database migration,
rate setup, real payment, commit, push or deployment has been performed. Target
schema/ledger comparison, verified private backup and matching-engine copy rehearsal
remain required before a receiving owner's local upgrade. SQLite tests do not prove
MySQL concurrency. Native packaging/device camera/gallery, real providers, SMTP,
company funding, disputed/RTS compensation and release acceptance remain separate.
The known incomplete local Android NDK and upstream missing launcher assets are
unchanged; no native release success is claimed.

Reproduction: `node scripts/verify-system.cjs` forces memory-only backend batches,
frontend and checkpoint checks. Use each Flutter app's existing test/analyze/build
commands with its own dependencies and explicit acceptance API URL. Private
synthetic receipts and loopback browser bridge live under `.local` and are excluded
from the teammate source; they contain no application database export.

Post-shutdown integrity check, 31 October 2026: the complete checkpoint bundle and
recorded source hashes matched the last completed baseline; no interrupted command
or temporary checkpoint file was recorded. No application source repair was needed.
Fresh memory-only payroll tests14/240 and web/checkpoint tests12 passed. The source
and design audits retained all148 upstream files, unchanged theme/assets and all100
migration fingerprints. Existing Flutter bundles parsed and referenced Vite assets
were present. Git remained on `feature/logistics-completion` at `d117595` with the
existing uncommitted changes. No application database access, commit or push.
