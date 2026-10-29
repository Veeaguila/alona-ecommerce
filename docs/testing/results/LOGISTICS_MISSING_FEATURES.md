# ALONA Logistics missing features and pre-test recommendations

Date: 2026-10-17 (Asia/Manila). Analysis only; source `463f884`.
Full original-ID/expected-result mapping: [LOGISTICS_TEST_ANALYSIS.md](LOGISTICS_TEST_ANALYSIS.md).

## Readiness

**5 source-implemented / 17 partial / 22 missing / 0 left unclassified** out of 44.
**Executed workbook outcomes: Pass 0 / Fail 0 / Blocked 0 / Not Run 44.**
All runtime behavior remains unverified. No application tests were run, no MySQL connection or migration was performed, and no application/.env/existing-document/Git changes were made. Worksheet guidance to seed/reset data was not executed.

The existing module has useful foundations: session/Sanctum approval guards, owned Rider/doc APIs, pickup confirmation, hub arrival, city sorting, manual last-mile assignment, photo-only completion protection, selected cross-role notifications, scoped JSON reports and seller-funded delivery finance. Preserve these while agreeing the workbook contract. Missing does not mean these existing features should be deleted/replaced.

## Decisions needed before development

- **Workflow:** A10 names Assigned/At Hub/RTS states; ALONA has distinct pickup/last-mile roles, assignment retained at hub status and terminal first-failure FAILED_RETURNED. Agree a state/label mapping and legal transitions.
- **Presence/dispatch:** A14/A34 require Online availability, nearest expiring offers, three active jobs and pre-pickup reassignment; current eligibility is approved + active + user/area checks with self-accepted pickup/manual last-mile. Agree job unit, distance source and suspension treatment of parcels in hand.
- **Money:** A20/A24/A33 assume weight-zone buyer shipping, COD remittance to Logistics and PHP50 delivery + PHP30 pickup pay. Current new buyer shipping is zero; delivered seller packages create PHP50 fee, PHP10 Logistics/PHP40 Rider, with Admin full settlements. This is a business-policy conflict, not permission to change financial rules.
- **Proof/audit:** Agree A16 5 MB JPG/PNG versus current image/20 MiB, A25 reason bounds, A32 delivery OTP and A35 immutable actor/status/reason logs. Registration OTP does not satisfy delivery OTP.
- **Fixture/side effects:** Approve isolated synthetic accounts, parcels, stock, notifications and ledgers. Original Test Data rates/worked checkout are incompatible with zero buyer shipping; no existing database row was inspected or seeded.

## Missing/partial feature backlog

Evidence identifiers below link to the precise file/line index in the analysis report. Ranges are original case-ID ranges, not new IDs.

| Item | Priority | Original IDs / assumptions | Needed capability | Scope and requirements | Evidence |
|---|---|---|---|---|---|
| MF01 | Decision gate | A10/A14/A20/A24/A32/A33/A34/A35 | Agree workflow and financial rules | Workbook nearest-offer/three-job/weight-rate/COD/payment assumptions conflict with existing two-leg manual dispatch, buyer-free shipping and PHP50 seller-package fee split PHP10/PHP40. Decide whether to develop these features or approve an adapted test contract; do not silently change either. | [DASH](LOGISTICS_TEST_ANALYSIS.md#evidence-dash), [ASSIGN](LOGISTICS_TEST_ANALYSIS.md#evidence-assign), [CHECKOUT](LOGISTICS_TEST_ANALYSIS.md#evidence-checkout), [FINANCE](LOGISTICS_TEST_ANALYSIS.md#evidence-finance), [SETTLE](LOGISTICS_TEST_ANALYSIS.md#evidence-settle) |
| MF02 | High | LOG-RIDER-001..006 | Rider management screen, reasons, presence and suspension | Reuse owned rider/doc APIs and notifications; add a real management/document-review UI, 10-300 reason validation/persistence after approval, Online state, current/today workload, suspension/release/reactivation policy and actor audit. Preserve cross-company denial. | [RIDER](LOGISTICS_TEST_ANALYSIS.md#evidence-rider), [REJECT](LOGISTICS_TEST_ANALYSIS.md#evidence-reject), [DEACTIVATE](LOGISTICS_TEST_ANALYSIS.md#evidence-deactivate), [DOCS](LOGISTICS_TEST_ANALYSIS.md#evidence-docs), [NOTIFY](LOGISTICS_TEST_ANALYSIS.md#evidence-notify), [NAV](LOGISTICS_TEST_ANALYSIS.md#evidence-nav) |
| MF03 | High | LOG-DISP-001..007 | Offer dispatch, capacity, bulk assignment and reassignment | Define job unit/legs; implement nearest eligibility, offer expiry/decline fallback, transactional capacity three, bulk three-assigned/two-skipped, pre-pickup reassignment and post-pickup rules. Reuse shipment locks/area scope, rather than replacing finance/pickup contracts. | [ASSIGN](LOGISTICS_TEST_ANALYSIS.md#evidence-assign), [ELIG](LOGISTICS_TEST_ANALYSIS.md#evidence-elig), [PICKUP](LOGISTICS_TEST_ANALYSIS.md#evidence-pickup), [RIDERMODEL](LOGISTICS_TEST_ANALYSIS.md#evidence-ridermodel) |
| MF04 | High | LOG-HUB-001..004,006; LOG-DASH-001..003 | Hub events, status labels and delivery OTP | Add durable receipt/outbound events and Buyer milestones, clear duplicate-scan feedback, consistent tracking normalization, agreed regional grouping and dedicated delivery OTP with replay/attempt controls. Do not repurpose registration OTP or sorted_at as a receipt timestamp. | [ARRIVE](LOGISTICS_TEST_ANALYSIS.md#evidence-arrive), [PARCEL](LOGISTICS_TEST_ANALYSIS.md#evidence-parcel), [SORT](LOGISTICS_TEST_ANALYSIS.md#evidence-sort), [DELIVER](LOGISTICS_TEST_ANALYSIS.md#evidence-deliver), [DASH](LOGISTICS_TEST_ANALYSIS.md#evidence-dash) |
| MF05 | High | LOG-TRK-001..005 | Unified shipment search and exception management | Search by tracking/parent order/buyer with tenant scope. Design reasoned authorized override and append-only actor/old-new audit. Add Lost/Damaged, private evidence, two-party notifications and claim/refund initiation with consistent transactional side effects. | [ARRIVE](LOGISTICS_TEST_ANALYSIS.md#evidence-arrive), [TRACK](LOGISTICS_TEST_ANALYSIS.md#evidence-track), [HISTORY](LOGISTICS_TEST_ANALYSIS.md#evidence-history), [ROUTES](LOGISTICS_TEST_ANALYSIS.md#evidence-routes), [PROOF](LOGISTICS_TEST_ANALYSIS.md#evidence-proof) |
| MF06 | High | LOG-RTS-001..004 | Attempt ledger and reverse logistics | Implement failure counts/reasons/refusal rule, RTS queue/assignment, return milestones, seller receipt confirmation with exactly-once restock, and prepaid refund/earning effects. Existing customer returns/FAILED_RETURNED are not this workflow. | [DELIVER](LOGISTICS_TEST_ANALYSIS.md#evidence-deliver), [RETURNS](LOGISTICS_TEST_ANALYSIS.md#evidence-returns), [PARCEL](LOGISTICS_TEST_ANALYSIS.md#evidence-parcel), [FINANCE](LOGISTICS_TEST_ANALYSIS.md#evidence-finance) |
| MF07 | Decision gate → High | LOG-RATE-001..004 | Zone/weight rate administration and checkout serviceability | Current city coverage has no prices and checkout ignores serviceability. Agree funding first, then versioned positive rates/round-up weight math, effective checkout snapshots, approved-area availability, old-order preservation and audit. Protect existing Buyer/Seller pricing behavior. | [COVERAGE](LOGISTICS_TEST_ANALYSIS.md#evidence-coverage), [CHECKOUT](LOGISTICS_TEST_ANALYSIS.md#evidence-checkout), [CHECKOUTSTORE](LOGISTICS_TEST_ANALYSIS.md#evidence-checkoutstore), [CONFIG](LOGISTICS_TEST_ANALYSIS.md#evidence-config), [FINANCE](LOGISTICS_TEST_ANALYSIS.md#evidence-finance) |
| MF08 | Decision gate → High | LOG-COD-001..003 | COD collections, remittances and reconciliation | Add a dedicated COD model/workflow only after authority is agreed: owned rider/day/order collections, remittance balance/shortfall, Logistics-only confirmation and idempotent audited transactions. Keep separate from Admin collection of seller delivery fees and Rider pay. | [SETTLE](LOGISTICS_TEST_ANALYSIS.md#evidence-settle), [ADMIN](LOGISTICS_TEST_ANALYSIS.md#evidence-admin), [FINANCESCHEMA](LOGISTICS_TEST_ANALYSIS.md#evidence-financeschema), [ROUTES](LOGISTICS_TEST_ANALYSIS.md#evidence-routes) |
| MF09 | Medium | LOG-COD-004..005 | CSV/XLSX delivery and COD exports | No current export implementation exists. Add both formats only after sources exist, enforce same date/company/filter semantics across all pages, reconcile totals and handle empty ranges. Test spreadsheet-formula-like strings in CSV/XLSX output. | [REPORT](LOGISTICS_TEST_ANALYSIS.md#evidence-report), [REPORTUI](LOGISTICS_TEST_ANALYSIS.md#evidence-reportui), [ROUTES](LOGISTICS_TEST_ANALYSIS.md#evidence-routes) |
| MF10 | Medium | LOG-HUB-001,004; LOG-RIDER-003; LOG-DISP-003,004; A16 | Validation/integration hardening | Reproduce whitespace lookup/confirm mismatch, rider-eligibility/deactivation race and missing reason bounds. Agree 5 MB JPG/PNG proof policy versus current generic 20 MiB rule. Preserve ownership, locks, private storage and exactly-once financial recognition. | [INBOUNDUI](LOGISTICS_TEST_ANALYSIS.md#evidence-inboundui), [CONFIRMUI](LOGISTICS_TEST_ANALYSIS.md#evidence-confirmui), [ASSIGN](LOGISTICS_TEST_ANALYSIS.md#evidence-assign), [DEACTIVATE](LOGISTICS_TEST_ANALYSIS.md#evidence-deactivate), [PROOF](LOGISTICS_TEST_ANALYSIS.md#evidence-proof) |

## Concrete bug candidates to reproduce first

1. **Whitespace reference mismatch (F01, Medium):** OrderManagement search trims tracking text; Confirm Arrival posts the original string. Backend exact lookup can reject a parcel just found. No runtime reproduction was attempted.
2. **Missing reason validation/persistence (F02, High against workbook):** reject/deactivate do not use the submitted reason; generic account notifications cannot contain the exact rejection reason.
3. **Deactivation/ongoing-work conflict (F03, High):** deactivation neither releases unpicked jobs nor provides a separate safe policy for in-hand jobs; operational middleware denies all actions for inactive riders.
4. **Eligibility race (F04, Medium, unverified interleaving):** rider eligibility read in assignment is not locked against independent deactivation. Verify under an approved isolated database engine before claiming a race result.
5. **Hub event/time gaps (F05, High against workbook):** arrival does not record a durable hub time/event or Buyer At Hub history. Duplicate status guard alone cannot prove exactly one required event.
6. **Photo validation mismatch (F06, Medium):** proof accepts image/max:20480 rather than explicit JPG/PNG≤5 MB. Actual web/PHP limits are unknown and may reject earlier.
7. **Immediate terminal failure (F07, High against workbook):** no three-attempt reasons/RTS chain; first fail writes FAILED_RETURNED.
8. **Dashboard/date mismatch (F08, Medium):** queue cards and sorted_at report filters are different from expected four shipment counters/dashboard dates.

These findings are static evidence, not a claim that a black-box case failed. Existing tests often intentionally assert the current rules; changing them requires approval, not merely updating expectations to hide differences.

## Proposed implementation/test sequence — not authorized or executed

1. Approve business-rule decisions and a case-by-case acceptance contract. Keep the original workbook untouched; record any agreed deviations separately.
2. Reproduce F01, reason-boundary, eligibility, duplicate-scan and private/tenant-access scenarios in an isolated environment. Start with the five source-complete ownership/readiness/not-found cases; record actual evidence.
3. Add Rider management/dispatch capacity/events/OTP/audit in small reviewed batches, preserving finance lock order and separate pickup/delivery ownership. Pair every change with original-ID-mapped regression tests.
4. Implement agreed exceptions and RTS before stock/refund/earning cases. Coordinate Buyer/Seller notifications and exactly-once side effects rather than reusing unrelated customer-return completion blindly.
5. Develop rate/serviceability and COD only after finance/product owners approve policy and authority. Avoid breaking existing seller-funded packages or Admin settlements.
6. Add CSV/XLSX only after delivery/COD source definitions stabilize. Verify multi-page totals, same filter/date semantics, empty output, formula-like text and company isolation.
7. Run the full 44-case workflow and cross-role checks in an approved disposable environment. Capture dates/browser/build, original IDs, requests/responses and before/after stock/status/notification/balance evidence. SQLite memory may verify contracts; MySQL contention/live device/SMTP/upload behavior requires separate acceptance.

## Evidence gaps and approval boundary

- Existing automated definitions were inspected but not run. Previous 44-case registration evidence concerns TC-LOG registration IDs, not this workbook's LOG-DASH/RIDER/DISP/HUB/TRK/RTS/RATE/COD cases.
- Populated MySQL schema/migration status, live authentication/session expiry, deployment route/assets cache, two-role browser consistency, scanner/device behavior and effective upload limits are unverified.
- Relevant source/tests/migrations support analysis, not proof that test fixtures exist or the browser meets every expected result. No source, .env, workbook, existing memory or teammate file was overwritten.
- **Wait for explicit approval before implementation.** Suggested first approval: rule reconciliation and an isolated reproduction/validation batch; financial/state-machine/database changes should receive a concrete reviewed scope before editing or execution.
