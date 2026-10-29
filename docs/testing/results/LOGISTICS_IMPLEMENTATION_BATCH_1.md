# ALONA Logistics — Implementation Batch 1

Date: 2026-10-17 (Asia/Manila). Branch: `feature/sorting-center`; inspected HEAD: `463f88443b13eb471db22ac31df4b8be50b465a4`.

**Status: approved Batch 1 scope implemented and verified.** The user explicitly authorized this bounded batch after the proposal. See [execution evidence, modified files and remaining limitations](LOGISTICS_BATCH_1_EXECUTION.md). Inputs: [requirement conflicts](LOGISTICS_REQUIREMENT_CONFLICTS.md), [test analysis](LOGISTICS_TEST_ANALYSIS.md), [missing features](LOGISTICS_MISSING_FEATURES.md), and the previously extracted [original workbook](../Blackbox_Test_Cases_Logistics_Modules-1.xlsx). The workbook hash matches the analysis checkpoint: `0615000a6d58f8b3e5b6690fffb08e498f6095ecd51158715486ee286570c84f`.

## Completed implementation

| Approved task | Result |
|---|---|
| B1-01 — inbound reliability | Completed: reviewed reference bound to confirmation; edited/stale results invalidated; overlapping actions blocked; recoverable failures and readable errors. |
| B1-02 — company/document access | Completed: direct company/role/private-file regression gates; fixed Symfony file-response public cache directive with `setPrivate()`. |
| B1-03 — read-only Rider review | Completed: `/sorting-center/rider-management`, existing portal navigation/design, approval filters, protected User-ID document URLs/previews and unavailable/session-error states. No account action controls. |
| B1-04 — readiness and compatibility | Completed: current eligibility/readiness, distinct pickup/last-mile Riders, four-item whole-shipment binding and existing fee/proof/settlement regressions verified. Production workflow/finance rules unchanged. |

Final executed checks: **124 PHP tests / 898 assertions; 35 frontend tests; 8 synthetic-browser checks — all passed.** Final Vite build and changed-code review completed. Local generated assets were refreshed with the verified build while retaining all 109 earlier build files and a complete backup. Full files/bugs/tests and command evidence are in [LOGISTICS_BATCH_1_EXECUTION.md](LOGISTICS_BATCH_1_EXECUTION.md).

The original approved task specification follows. Its approval-before-coding notes record the scope boundary; that limited approval has now been received. Other requirement decisions remain pending.

## Boundaries and approval status

The user authorized implementation while preserving these constraints:

- Preserve current pricing: PHP0 shipping on new Buyer checkout; one PHP50 Seller-shipment delivery fee, split PHP10 Logistics / PHP40 Rider after qualifying delivery. Preserve historical orders and existing Admin settlement authority.
- Preserve pickup and last-mile as separate legs, their existing API/state contracts, and whole-shipment assignment/dispatch invariants.
- Keep COD cash collection/remittance separate from delivery-fee collection and Rider earnings. Batch 1 introduces no COD ledger or settlement action.

These constraints narrow the earlier report; they do not approve its proposed capacity-job unit, three-job cap, Online policy, status labels, audit design or other D01–D14 recommendations. All remaining decisions stay pending. No workbook acceptance result is rewritten to fit existing ALONA behavior.

Batch 1 proposes two bounded user-facing improvements plus tests of existing safeguards. It requires no new dependency, schema, migration, financial calculation, authentication scheme or Rider-app change. Existing page styling and shared registration flows remain intact. If implementation discovers a need to cross those boundaries, stop that task and present the concrete dependency for approval.

**Full original workbook execution: Passed 0 / Failed 0 / Not Run 44.** Scoped automated checks above do not prove every expected result of an original case. The earlier 5 implemented / 17 partial / 22 missing classifications remain the historical analysis baseline; the workbook and earlier reports were not rewritten.

## Ordered work and dependencies

| Order | Task | Module / purpose | Dependencies | Complexity |
|---|---|---|---|---|
| 1 | B1-01 | Hub receiving: bind confirmation to the displayed lookup result and handle asynchronous failures safely | Reproduce current frontend behavior using mocked requests; retain existing incoming API contract | M |
| 2 | B1-02 | Establish company, approval and private-document regression gates | Existing synthetic factories, fake storage and isolated SQLite memory database; run before B1-03 | M |
| 3 | B1-03 | Rider Management: owned, read-only list and document review | B1-02 access tests; existing list API and session-protected document handler | M |
| 4 | B1-04 | Guard existing readiness, eligibility, two-leg and whole-shipment behavior | Existing sorting/assignment/pickup/finance tests; repeat affected gates after B1-01/B1-03 | M |

M means a bounded change with several state/access paths and focused integration tests, approximately 1–2 developer-days per task. Combined estimate: **4–7 developer-days**, including review and evidence, assuming the existing test toolchain works. Estimates are planning ranges, not a delivery commitment. There is no schema or unresolved policy dependency on the critical path; a newly discovered one pauses the affected task.

## B1-01 — Reliable inbound lookup and confirmation

**Related cases:** `LOG-HUB-001` (existing receiving step only), `LOG-HUB-004` (repeat-scan feedback only), `LOG-HUB-005`, `LOG-HUB-007`; tracking-reference subset of `LOG-TRK-001`.

**Existing code and reusable APIs:** [OrderManagement.vue](../../../resources/js/Pages/Logistics/OrderManagement.vue#L116) has lookup, confirmation, feedback and loading handling. [IncomingParcelController](../../../app/Http/Controllers/Logistics/IncomingParcelController.php#L14) implements `GET /api/sorting-center/parcels/lookup/{orderId}` and `POST /api/sorting-center/parcels/mark-arrived` with `{order_id}`. It already scopes access, locks arrival, and permits `PICKED_UP → AT_SORTING_CENTER`. Reuse [IncomingParcelTest.php](../../../tests/Feature/Logistics/IncomingParcelTest.php) and the Vue request-test approach in [data-flow.test.mjs](../../../tests/frontend/data-flow.test.mjs).

**Evidence qualification:** Earlier F01 is a bug candidate, not a reproduced failure. The template already uses `v-model.trim`, so ordinary whitespace input may be normalized before confirmation; Laravel request trimming must also be considered. The stronger source concern is that lookup displays `data.data`, but confirmation posts the separately editable `orderId.value`. Changing the field after a successful lookup can target a different reference from the record displayed. Inputs can also change while a request is pending. Reproduce these paths before editing; retain existing behavior where a suspected failure cannot be demonstrated.

**Required changes:**

1. Keep a canonical reference from the successful, current lookup result. Invalidate that result when the search input changes; require a fresh lookup before confirmation. Snapshot the displayed reference before starting the POST. Trim surrounding whitespace consistently without changing case, prefixes or internal characters.
2. Prevent overlapping lookup/confirm actions and discard stale or unmounted results. Preserve a clearly recoverable result/input after failure; do not imply success before the response succeeds.
3. Preserve backend status codes and validation rules. Show returned JSON errors; provide a readable fallback for network failures, unexpected response shape or non-JSON responses. Always release loading state.
4. Retain the existing already-at-center message and no-confirm-button behavior. Test this path and surface existing `current_status` errors; do not add a new successful replay response or a durable event ledger.

**Dependencies:** Existing Vue primitives, fetch, response envelopes and incoming controller. No shared API wrapper refactor is needed. No timestamp field or Buyer history is added.

**Risks:** A stale result could confirm the wrong parcel; an overbroad normalizer could corrupt valid references. Disabling UI actions must not replace backend authorization. UI duplicate suppression is not server idempotency or proof of exactly one At Hub event.

**Tests needed:**

- Lookup A, change input to B, then attempt confirmation: no POST for an unreviewed B. Lookup A, change input before the response returns: no stale confirmation result.
- Successful lookup/confirm sends the same canonical tracking reference, including scanner-style surrounding whitespace; blank and overlength handling stays consistent with existing rules.
- Double action, failed fetch, 401/403, 404/409/422, malformed/non-JSON and server-error responses show useful feedback and release loading. Preserve actionable retry behavior.
- Known picked-up parcel reaches hub once; already-arrived lookup shows existing feedback; a repeated POST retains the existing rejection and leaves state unchanged. Unknown and foreign references create/mutate nothing.

**Approval before coding:** Approve this bounded interaction fix. Durable hub receipt times, Buyer milestones and changed replay semantics remain outside Batch 1 under D03/D09/D14. `LOG-HUB-001` and `LOG-HUB-004` cannot be marked fully Pass from these changes; `LOG-TRK-001` still lacks parent-order/Buyer search.

## B1-02 — Company, approval and document access regression gates

**Related cases:** `LOG-DASH-004`, `LOG-RIDER-007`, `LOG-HUB-005`, `LOG-HUB-007`. These four are source-implemented candidates, not executed passes. The fifth source-implemented candidate, `LOG-DISP-008`, is handled in B1-04.

**Existing code and reusable APIs:** [API guards/routes](../../../routes/api.php#L66), [web guards/routes](../../../routes/logistics.php#L15), [RiderManagementController](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L18), [PartnerProfileController::document](../../../app/Http/Controllers/PartnerProfileController.php#L60), [DashboardController](../../../app/Http/Controllers/Logistics/DashboardController.php#L15) and IncomingParcelController. Reuse [RiderManagementScopeTest.php](../../../tests/Feature/Logistics/RiderManagementScopeTest.php), [PartnerOnboardingTest.php](../../../tests/Feature/PartnerOnboardingTest.php), [DashboardTest.php](../../../tests/Feature/Logistics/DashboardTest.php) and IncomingParcelTest.

**Required changes:** Add only missing behavioral tests, named or annotated with original IDs. Exercise two centers and direct requests; assert denied operations leave records and documents unchanged. Cover list isolation, all existing Rider mutation routes and private document access. No new permission policy or production controller change is proposed by this task. A newly failing access guard requires a targeted fix proposal rather than broadening permissions.

**Dependencies:** `RefreshDatabase` against explicitly forced SQLite `:memory:`, fake local/public storage, synthetic users/partner applications and existing approval middleware. Do not rely on deployed accounts, production files or an already logged-in browser session.

**Risks:** Tests that omit middleware or use only same-company fixtures can hide access failures. Existing unclaimed pickup-pool visibility is a pending D13 policy; test currently scoped parcel counters without silently restricting that shared pool or claiming every dashboard rule is resolved.

**Tests needed:** Owned document success; other-center/Buyer denial; unapproved-center denial; guest rejection/redirect according to the existing route contract; missing document 404; `private, no-store` and `nosniff` headers. Direct approve/reject/activate/deactivate attempts against foreign Riders must fail without state changes. Verify owned parcel counter isolation and unknown/foreign arrival has no inserts or updates. Preserve Admin and document-owner access.

**Approval before coding:** Approve isolated test additions. No suspension, rejection-reason, idle-session or shared-pool decision is requested or inferred here. A document ownership test does not satisfy approval audit, Online availability or suspension behavior.

## B1-03 — Read-only Rider list and protected document review

**Related cases:** `LOG-RIDER-001` (record/document review steps only), `LOG-RIDER-007`. Existing approval mutation endpoints remain unchanged and are not exposed as new controls in this batch.

**Existing code and reusable APIs:** `GET /api/sorting-center/riders?status=pending|approved|rejected` already returns owned Riders and user identity through [RiderManagementController::index](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L18). `GET /partner/documents/{user}/{document}` uses the existing authenticated web session and ownership checks. Reuse [ScLayout.vue](../../../resources/js/Layouts/ScLayout.vue), [logistics.js](../../../resources/js/logistics.js), [routes/logistics.php](../../../routes/logistics.php) and an existing [Blade shell](../../../resources/views/sorting-center/order-management.blade.php). Preserve the current colors, layout conventions and login/token bootstrap.

**Required changes:**

1. Add one read-only Rider Management page, navigation entry, Vue mount registration and protected web shell following the existing portal pattern. Show current identity, vehicle details, `approval_status` and `is_active` with accurate labels; do not label active as Online or inactive as workbook Suspended.
2. Provide existing approval-status filters, loading/error/empty states and owned record selection. No guessed workload counts, map locations or presence indicators.
3. Link document review only through the named protected route using the Rider's **user ID**, never its Rider ID or raw storage path. Use the existing keys `id_doc`, `license_doc`, `or_cr_doc`. For the workbook's motorized fixture, license and OR/CR can be reviewed; existing Bicycle registrations can legitimately lack motor-vehicle documents.
4. If needed, add allowlisted document descriptors (key, availability and protected route) to the owned list response using the existing PartnerApplication relation, eagerly loaded to avoid per-row queries. Retain existing response fields and endpoints. Missing/legacy documents remain unavailable; do not invent a public-URL fallback or change the shared document handler's authorization.
5. Offer no Approve, Reject, Suspend or Reactivate controls in this batch. Those complete workflows depend on unresolved reason, audit, presence and in-hand-job rules. The page should state clearly that it provides document review only.

**Dependencies:** B1-02 authorization tests must pass first. Existing document-key/storage mapping from [PartnerRegistrationController](../../../app/Http/Controllers/PartnerRegistrationController.php) and [PartnerRegistrationRequest](../../../app/Http/Requests/PartnerRegistrationRequest.php). Existing session plus Sanctum integration is reused without changing token lifetime or authentication.

**Risks:** Raw document paths in existing records must not become public links. Legacy Riders may lack PartnerApplication documents. Filter requests can finish out of order; a document/session failure must be visible and recoverable. Additive metadata must not remove fields consumed by other clients. No requirement to upload replacement documents or impose motorized-document rules on Bicycle Riders is implied.

**Tests needed:** Own versus foreign list/document access; pending/approved/rejected/empty filters; correct user-ID URLs; missing document and expired/unauthorized session feedback; motorized versus Bicycle applicability; no public storage URLs or new mutation controls. Mock out-of-order/failed list requests. Verify the new shell retains existing approval middleware, Vue mount and navigation styling.

**Approval before coding:** Explicitly approve the new **read-only** portal surface and, only if necessary, additive document metadata. D04/D09 still gate action controls. Completing the review steps alone cannot pass `LOG-RIDER-001`, whose original expected result also requires approval, Rider Online behavior and audit.

## B1-04 — Readiness, two-leg and whole-shipment compatibility gates

**Related cases:** `LOG-DISP-008`; existing manual-assignment/eligibility subsets of `LOG-DISP-002` and `LOG-DISP-003`; out-of-order guard subset of `LOG-HUB-006`. Financial and whole-shipment checks below are additional ALONA regression requirements, not replacement workbook expectations.

**Existing code and reusable APIs:** [ParcelAssignmentController](../../../app/Http/Controllers/Logistics/ParcelAssignmentController.php), [ParcelSortingController](../../../app/Http/Controllers/Logistics/ParcelSortingController.php), [RiderPickupController](../../../app/Http/Controllers/Logistics/RiderPickupController.php), [SellerShipmentFinance](../../../app/Services/SellerShipmentFinance.php). Reuse the current sorting-center queue, eligible-Rider and assign routes in [api.php](../../../routes/api.php); retain existing Rider pickup/start/complete APIs. Existing tests include [ParcelAssignmentTest.php](../../../tests/Feature/Logistics/ParcelAssignmentTest.php), [ParcelSortingTest.php](../../../tests/Feature/Logistics/ParcelSortingTest.php), [RiderPickupFlowTest.php](../../../tests/Feature/Logistics/RiderPickupFlowTest.php) and [SellerShipmentFinanceTest.php](../../../tests/Feature/Logistics/SellerShipmentFinanceTest.php).

**Required changes:** Run existing relevant regressions; add only missing original-ID-linked direct-request assertions and Batch 1 integration checks. Preserve readiness, city/area ownership, operational eligibility and whole-shipment guards. Do not implement new capacity, reassignment, live presence, state names, finance locks or financial arithmetic. Any failure predating Batch 1 must be distinguished from an introduced regression before proposing further work.

**Dependencies:** Existing models/factories and isolated memory database. The current parent Order → SellerShipment → members/Parcel lock order stays intact. No MySQL contention test is authorized or claimed.

**Risks:** Equating `is_active` with Online would falsely satisfy `LOG-DISP-003`. Applying a three-item cap could split a package. Treating pickup-to-hub-to-last-mile handover as forbidden reassignment would break the two-leg design. SQLite verifies functional contracts, not production row-lock interleavings; the suspected eligibility/deactivation race remains unverified and outside the fix scope.

**Tests needed:** Packed/not-ready excluded and direct assignment rejected; unsorted/not-arrived/foreign parcel or area denied; unapproved/inactive/wrong-area Rider rejected under current rules. A valid sorted hub parcel remains assignable with existing status semantics. Verify distinct pickup/last-mile Rider ownership, multi-item same-shipment Rider binding and readiness, private proof requirements, one package accrual only after complete proven delivery, unchanged PHP50/10/40 split, no pickup-only earnings and no COD offset. Reuse existing checkout zero-shipping/historical-price and Admin-only settlement regressions.

**Approval before coding:** Approve tests of current behavior only. D02/D03/D04/D05/D14 remain pending for capacity, Online policy, nearest offers, reassignment and replay. Neither current assignment status nor the current inactive-Rider guard proves the full workbook `LOG-DISP-002`/`LOG-DISP-003` expectations.

## Deferred work and decisions still needed

| Deferred capability / cases | Pending decision and reason |
|---|---|
| Four new dashboard counters and inclusive date filter: `LOG-DASH-001`, `LOG-DASH-002`, `LOG-DASH-003` | D02/D12: count unit, status membership, event timestamp and timezone. Preserve current operational cards. |
| Rider actions, reasons, Online/workload: `LOG-RIDER-001`, `LOG-RIDER-002`, `LOG-RIDER-003`, `LOG-RIDER-004`, `LOG-RIDER-005`, `LOG-RIDER-006` | D04/D09: presence versus approval, reason bounds, audit, notification delivery and in-hand permissions. Read-only review does not resolve them. |
| Nearest/capacity/reassignment/bulk and new Assigned milestones: `LOG-DISP-001`, `LOG-DISP-002`, `LOG-DISP-003`, `LOG-DISP-004`, `LOG-DISP-005`, `LOG-DISP-006`, `LOG-DISP-007` | D02–D05/D09/D14: capacity unit is still unapproved even though whole-shipment assignment is preserved. No guessed GPS, offers or pre-pickup cutoff. |
| Durable hub event/time, regional grouping, outbound OTP and full duplicate/out-of-order acceptance: `LOG-HUB-001`, `LOG-HUB-002`, `LOG-HUB-003`, `LOG-HUB-004`, `LOG-HUB-006` | D03/D08/D09/D11/D14: event/status design, region membership, delivery OTP/proof and compatible replay responses. Existing arrival UX can improve independently. |
| Parent-order/Buyer search, overrides, audit, Lost/Damaged: `LOG-TRK-001`, `LOG-TRK-002`, `LOG-TRK-003`, `LOG-TRK-004`, `LOG-TRK-005` | Search matching/scope must be agreed; D03/D07–D09 govern override authority, evidence, notifications and claims. No financial side effects inferred. |
| Attempts and RTS: `LOG-RTS-001`, `LOG-RTS-002`, `LOG-RTS-003`, `LOG-RTS-004` | D06/D07/D09: failure/refusal thresholds, reverse milestones, receipt/restock, refunds and earnings. No ordinary-cancellation reuse. |
| Regional serviceability, rates and checkout: `LOG-RATE-001`, `LOG-RATE-002`, `LOG-RATE-003`, `LOG-RATE-004` | D01/D11: pricing is preserved, but address/coverage acceptance and any adapted test contract still require approval. No new rate engine. |
| COD collection/remittance and exports: `LOG-COD-001`, `LOG-COD-002`, `LOG-COD-003`, `LOG-COD-004`, `LOG-COD-005` | D10/D12: distinct cash-custody authority/source, reconciliation and export dataset/date contract. COD separation alone does not approve implementing COD. |

No original case is silently removed. All 44 IDs are represented either in scoped tasks or deferrals; a partly covered case can appear in both. The original workbook remains unchanged. D13 session/shared-pool and D14 server replay decisions also remain pending wherever relevant.

## Verification and completion criteria after approval

1. Recheck HEAD, working changes and checkpoints before editing; preserve teammate work. Save a checkpoint before each meaningful batch and record commands before running them and confirmed results afterward.
2. First reproduce B1-01 using the existing Vue/Node harness. Reuse the installed toolchain; no dependency installation or browser-driven development-database writes. Add focused behavioral tests rather than source-text assertions or copies of implementation logic.
3. Before running PHP tests, verify no cached configuration can redirect tests to MySQL and explicitly force the PHPUnit testing settings: SQLite `:memory:`, empty DB URL, array session/cache/mail and synchronous fake-safe side effects. Reuse [phpunit.xml](../../../phpunit.xml). Stop if memory isolation cannot be established. Never run standalone migration, reset, seed or development-database commands; test schema setup is confined to the isolated in-memory suite.
4. Run affected classes by batch, then the existing combined IncomingParcel, RiderManagementScope, Dashboard, ParcelAssignment, ParcelSorting, RiderPickupFlow, PartnerOnboarding and SellerShipmentFinance regression gates. Run `npm run test:frontend` and `npm run build` using existing packages. Record actual totals, failures, commands and environment; do not describe planned commands as executed.
5. Review only changed code and regressions, including auth/privacy, request races and preserved financial/package contracts. Any needed schema, dependency, breaking API/auth or policy change returns for separate approval.
6. Check the receiving and read-only Rider screens visually with mocked/synthetic browser responses, preserving design. Such a smoke test does not prove cross-role live behavior. A full persistent cross-role acceptance environment needs separate agreement consistent with the in-memory-only test restriction.
7. Produce a separate execution record, retaining earlier reports. For each original ID distinguish full workbook verification, partial assertions, untested behavior and unresolved requirements. Passing a subset or automated regression does not promote the whole original case to Pass.

Batch completion means the approved scoped changes are reviewable, relevant isolated tests/build have recorded outcomes, existing safeguards remain intact, and remaining workbook gaps are explicitly retained. It does **not** mean all 44 tests pass. No commit, push, migration, `.env` change, source reset or existing-file deletion is authorized.

## Authorization boundary after Batch 1

The user approved B1-01 through B1-04, including the read-only Rider page, additive document metadata and isolated tests. That scope is complete. The approval does not accept deferred decisions, authorize database deployment or permit operations on existing ALONA records.

**Current checkpoint:** approved Batch 1 implemented, tested and documented; see the execution record. No existing database, migration, `.env` or original workbook changed. No commit or push. Wait for further authorization before expanding the scope or performing live tests that could modify existing records.
