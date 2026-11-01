# Final integration review — 2026-10-15

Current release findings, scoped commit boundaries and existing-database procedure:
[RELEASE_READINESS.md](RELEASE_READINESS.md). Sections below retain earlier review
history; their baseline/file lists are not the current commit plan.

PASSED: checkpoint bundle/source hashes; scoped uncommitted integration review;
credential-pattern and Git whitespace checks in both repositories.
UPDATED: Phase 7 backend and Rider earnings view integrated. Current validation:
12 focused Flutter tests and 21 Laravel tests/166 assertions PASS; targeted analysis
clean and current JavaScript web release compile PASS. Credential-pattern/whitespace
checks passed. Earlier evidence below is historical. Current API/rollout details:
PHASE10_READINESS.md and ALONA finance docs. No production migration or publication
occurred in this final integration; existing operational UI/history are preserved.
MANUAL TESTING: Phase 10 device, email, live API and deployment acceptance.

## Evidence and limits

- Rider: `master`, HEAD `99d7363`; ALONA: `feature/sorting-center`, HEAD `da3514f`.
  Both repositories retained all existing edits; neither index contained staged files.
- Reviewed changed integration controllers/models, Flutter request/session/workflow
  code, new history/portal pages and relevant tests. No actionable defects found
  within this scope; this is not a full-project or production security certification.
- Scanned full contents of 36 Rider and 15 ALONA changed/untracked files for private
  keys, common provider tokens, credential URLs, JWTs and literal environment secrets.
  No matches or binary files. Pattern checks cannot prove absence of every secret.
- No tracked sensitive-path candidates in Rider. ALONA's only candidate,
  `storage/certs/cacert.pem`, contains 121 public certificates and no private-key
  block; it is unchanged and outside the proposed commits.
- Unstaged and staged `git diff --check` passed in both repositories.
- Existing 60 Flutter, 119 Laravel and 7 recovery test passes, clean analyzer and
  release JavaScript web build remain prior evidence: recorded source hashes were
  unchanged on resume. These suites were not rerun during this review.
- No application source, database, dependencies, credentials or ALONA files changed
  during this review. No commit, staging, push, migration or live API call performed.

## Proposed recovery commit boundaries

These are reviewable file lists, not permission to commit or an ownership claim.
Keep one application commit per repository and a separate Rider recovery/docs commit.
Confirm teammate ownership of ALONA hunks before staging. Avoid blanket `git add .`.

Rider application and tests:

```text
ios/Runner/Info.plist
lib/pages/delivery_dashboard_page.dart
lib/pages/delivery_history_page.dart
lib/pages/login_page.dart
lib/pages/pickup_dashboard_page.dart
lib/pages/proof_capture_page.dart
lib/pages/registration_page.dart
lib/pages/rider_portal_page.dart
lib/services/api_error.dart
lib/services/registration_api.dart
lib/services/rider_portal_api.dart
lib/services/rider_session.dart
lib/services/rider_session_guard.dart
test/api_errors_test.dart
test/delivery_history_test.dart
test/delivery_workflow_test.dart
test/pickup_workflow_test.dart
test/rider_portal_test.dart
```

ALONA application, tests and contract documentation:

```text
app/Http/Controllers/Logistics/LoginRiderController.php
app/Http/Controllers/Logistics/MessageController.php
app/Http/Controllers/Logistics/ParcelAssignmentController.php
app/Http/Controllers/Logistics/RiderDeliveryController.php
app/Http/Controllers/PartnerProfileController.php
app/Http/Controllers/PartnerRegistrationController.php
app/Http/Requests/PartnerRegistrationRequest.php
app/Models/Rider.php
tests/Feature/Logistics/ConversationMessagingTest.php
tests/Feature/Logistics/ParcelAssignmentTest.php
tests/Feature/Logistics/RiderDeliveryCompletionTest.php
tests/Feature/Logistics/RiderSessionApiTest.php
tests/Feature/PartnerOnboardingTest.php
docs/SORTING_CENTER_INTEGRATION.md
```

Rider recovery/docs: `AGENTS.md`, `README.md`, `docs/PROJECT_CONTEXT.md`,
`docs/CURRENT_STATE.md`, `docs/ARCHITECTURE.md`, this report,
`scripts/integration_checkpoint.cjs`, `scripts/checkpoint_recovery.cjs`,
`scripts/integration_checkpoint.test.cjs`, and the five generated integration bundle
files (`RESUME.md`, `CHECKPOINT.md`, `PROGRESS.md`, `CHANGELOG.md`,
`checkpoint-state.json`) with their five `.previous` backups. Preserve each complete
bundle together. ALONA's untracked `AGENTS.md` is a separate policy change to review.

Exclude `.recovery/`, command logs, temporary updates, `.env*`, signing keys,
private uploads, database files and generated builds. Keep backups on disk.
Before any later authorized commit, rerun scoped credential/whitespace checks and
review `git diff --cached` including every newly added file. Current indices are empty.

## Phase 7 financial evidence

Observed in ALONA:

- `app/Models/Commission.php:10` and
  `database/migrations/2026_09_01_000001_create_platform_admin_entities_table.php:29`
  associate commission amounts with an order and seller, with no rider association.
  `app/Http/Controllers/AdminReportingController.php:148` reports those seller records.
- `app/Models/Order.php:21` and
  `app/Http/Controllers/BuyerOrderController.php:1100` store the order shipping charge.
  No rule allocates this charge to a rider in the inspected financial paths.
- `database/migrations/2026_10_11_100000_create_logistics_tables.php:84` links a
  parcel uniquely to an optional order item; lines 97–98 distinguish assigned and
  pickup riders. `docs/SORTING_CENTER_INTEGRATION.md` documents `ITEM-{id}` scan
  references. A parcel reference cannot be treated as the numeric parent order ID.
- No rider payout model, earning ledger, expense source or payout API was found in
  the scoped model/service/migration/API inspection. Production data was not queried.

Planned minimum design, subject to approval: a server-owned rider earning ledger
linked to rider, parcel and eligible activity, with decimal money/currency, the
applied rule/rate snapshot, earned timestamp and settlement status. Enforce one
earning per eligible event and represent corrections explicitly; expose only the
authenticated rider's summaries and paginated entries. Keep expenses and settlement
records separate if the approved scope needs them. Flutter displays server totals.
No schema, route, rate or calculation was implemented.

Subsequent user approval defines seller-funded PHP 50 successful-delivery charges,
PHP 10 Logistics/PHP 40 Rider, no voucher reduction, no pickup fees/expenses and
manual collection/payout. Latest approval specifies per-shipment billing, new orders
only and removal of buyer/express fees. See ../DECISIONS.md and PHASE7_SHIPMENTS.md
for the approved shipment design. The backend implements it and Rider now consumes
its earnings API in a separate view. No extra package-design approval is outstanding.

## Manual acceptance still required

Use an authorized test environment and test identities/parcels. Record platform,
build, API origin, steps and redacted outcome; do not save tokens or personal data.

| Area | Required evidence |
|---|---|
| Android/iOS | Native release build/install on physical devices; narrow-screen layouts; camera grant/deny/retry/cancel; proof preview/upload; background/resume and interrupted connection behavior. iOS requires a macOS/Xcode build environment. |
| Email/approval | Real SMTP arrival and signed link through browser login; expired/reused link behavior; pending/unverified access denied; center approval enables operations; suspension/deactivation blocks them. |
| End-to-end API | Registration with private documents → verification/approval → login → pickup acceptance/confirmation → center arrival/sorting/assignment → start → private proof upload → deliver. Separately test failure, history pagination, messaging/unread, own contact update and confirmed logout. Verify buyer/seller tracking and cross-rider/center access denial. |
| Deployment | Actual reachable HTTPS `API_BASE_URL`; browser CORS/preflight and bearer calls; private storage access; multipart limits at PHP and proxy. Prior PHP limits were 2M/file and 8M/request, below 5 MB registration documents and 20 MB proofs; operator must size per-file and aggregate request limits and verify 413 handling. |

Next step: review the new backend migration for operator deployment, verify contention
on a disposable deployment-engine database, and run the acceptance matrix with an
authorized environment/operator. Phase 10 remains NEEDS VERIFICATION until evidence
is recorded; manual and financial blockers do not invalidate prior automated passes.
