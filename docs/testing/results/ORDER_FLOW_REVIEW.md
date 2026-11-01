# Order flow and imported Rider review — 29 October 2026

The active Rider client is `mobile/rider`, imported as normal parent-repository files
from [Veeaguila/rider](https://github.com/Veeaguila/rider/tree/0ea8018db81287d82981c17b10d5551c7750dd2e).
The upstream commit is `0ea8018db81287d82981c17b10d5551c7750dd2e` (223 tracked files).
There is no nested Git repository or submodule. Future mobile clients can use other
directories under `mobile`. The independent old sibling Rider source was preserved.

## Confirmed defects repaired

| Trigger | Repair and evidence |
|---|---|
| New assignment arrives after Rider login while another tab is open | Pickup and Delivery queues refresh on tab activation without recreating unrelated tabs. Deliveries has an explicit Refresh control. `dashboard_activation_test.dart`; observed in compiled browser workflow. |
| Double tap on a disabled pending pickup button | Parent card navigation is also disabled during the action. It no longer opens details over a conflict response. `pickup_workflow_test.dart`. |
| Rider records failed delivery | The dialog uses backend outcomes `Buyer not home`, `Wrong address`, `Refused`; detail selections send the selected outcome directly. `delivery_workflow_test.dart`, `workbook_workflow_test.dart`, `order_detail_contract_test.dart`. |
| Rider opens details during pending retry or RTS | Normal delivery/photo/failure actions stay unavailable, matching the list and backend state. `order_detail_contract_test.dart`, `rider_rts_test.dart`. |
| Rider declines a nearest offer | The real parcel-scoped decline API executes and the queues refresh. The old snackbar-only sample action was removed. `workbook_workflow_test.dart`. |
| API omits recipient/payment information | Removed sample customer names, invented COD amount/prepaid status, pickup counts, merchant, distance and earnings. The pickup shortcut opens actual jobs. Money allocation is never inferred from an order-wide total. |
| Pickup details display an optional photo or product quantity | Removed a photo control that only retained a filename without uploading. Product quantity no longer claims to be a physical box count. |
| Handover refresh or acknowledgment has malformed/mismatched data | Validate ownership, IDs, parcel sets and timestamp before claiming success; clear stale private records on failure. `rider_handovers_test.dart`. |
| Scanner opens delivery details | Standard accessible Back control restores navigation. Hidden legacy text overlays were removed; tests now target visible order references/actions. |
| Buyer order has the normal initial checkout history | Merge the persisted parent and item histories rather than hiding item events. Distinct same-time item events retain product context; timestamps include their timezone. `BuyerOrderTrackingTest`, actual checkout assertions and final browser screenshot verify the activity log. |

The teammate's teal/amber interface remains the presentation base. Two focused
widgets and their regression coverage brought forward verified failure/handover
contracts from the preserved sibling. The old dashboard implementation was not
copied over the teammate's redesign.

## Backend journey coverage

`tests/Support/OrderJourney.php` creates synthetic users, coverage, a product with
stock 10 and a selected cart with quantity 2. `CheckoutToDeliveryTest.php` exercises
real Laravel routes rather than starting with a pre-inserted packed order:

1. Buyer places a COD order: merchandise 250 + shipping 50, stock 8, selected cart removed.
2. Seller processes, packs and marks ready; one linked parcel/pickup is created.
3. Logistics accepts; online pickup Rider is manually assigned, acknowledges, then
   confirms physical collection. Unauthorized and duplicate transitions are rejected.
4. Logistics confirms arrival, sorts to the configured destination and assigns an
   online delivery Rider with explicitly configured coverage.
5. Assigned Rider starts delivery. Completion requires a valid private photo and
   correct Buyer OTP. Duplicate completion is rejected; Buyer/Seller views agree.
6. Refused delivery requires Logistics return initiation, Rider physical pickup and
   handover, then Seller receipt. Stock returns to 10 once, only after Seller receipt.
7. In-store pickup checkout creates no courier parcel or dispatch.
8. Actual COD cash collection, Rider remittance submission and Logistics cash receipt
   use separate evidence/confirmations. Collection is idempotent and does not itself
   complete delivery or invent earnings. The submitted 300 in this fixture represents
   manually confirmed cash; it is not a new automatic allocation policy.

Existing tests also cover the other payment methods, variants, cancellation,
stock, refunds, notifications, handovers, hub custody, rate configuration, access
control, reports and the 30-minute Logistics inactivity rule.

Six test fixtures used a category slug now created by an existing category migration;
they now use independent slugs. The draft-validation test sends explicitly empty
weight: the existing API defaults an omitted field to 0.5 kg. Required-field assertions
remain. No production category, weight, payment or earnings rules were changed.

## Verification

| Check | Result |
|---|---|
| All 124 backend test files, split into 11 memory-only batches; affected failures rerun after fixture repairs; Buyer/history and updated 4-case checkout journey rerun | PASS: 948 tests, 56,958 assertions across the verified batch/focused results |
| Complete Rider suite after tab refresh fix | PASS: 123 tests, including narrow layouts and current API contracts |
| Complete frontend suite | PASS: 123 tests |
| Imported checkpoint helper tests | PASS: 7 tests |
| Final Flutter analyzer | PASS: no issues |
| Final Flutter profile web build for isolated loopback browser verification | PASS after tab refresh fix |
| Actual browser journey on the final source | PASS: checkout through Delivered, including repaired Buyer activity history; no browser JavaScript errors |
| Android debug APK | BLOCKED by local NDK 28.2.13676358 missing `source.properties`; no valid APK result |

The initial monolithic backend command hit a process-wide 120-second timer installed
by an AI request handler. Independent test batches avoid carrying that request timer
through the entire suite. Earlier failed/unknown commands are retained in local logs;
they are superseded only by the explicitly successful affected reruns.

Reproduce server/frontend/helper checks from the repository root:

```powershell
node scripts/verify-system.cjs
```

After `flutter pub get` in `mobile/rider`, include mobile checks with
`node scripts/verify-system.cjs --mobile`. The verifier forces SQLite `:memory:` and
array mail/cache/session, refuses cached application configuration and records each
running/result command under ignored `.local/system-verification`. Flutter widget
tests use mocked HTTP; a test API define is required, with no live API dependency.

Detailed local execution evidence is under `.local/rider-flow-review`, including
per-batch JUnit/XML, logs, upstream import hashes, pre-review source snapshots and
preservation checks. These private artifacts are not part of the release.

The browser run uses actual Buyer/Seller Inertia and Logistics Blade/Vue pages plus
the compiled imported Flutter client, against actual Laravel controllers in one
SQLite memory process. Synthetic web identities are supplied by Laravel test
authentication; the two Riders use separate real API logins. Manual pickup dispatch
and destination coverage setup use the APIs; checkout, Seller preparation, Logistics
acceptance/arrival/sorting/delivery assignment and Rider pickup/photo/OTP use the UI.
The final PHP fixture confirms one order, parcel and proof, Delivered, and stock8.
Final Rider, Buyer, Seller, Logistics and pickup-transit screenshots were retained;
Rider/Buyer/Logistics screenshots were visually inspected. The repaired Buyer log
shows preparation through delivery and explicit timezone dates. No existing account,
application database, shared service, real money or email was used.

## Pull and rollout limits

See [LOGISTICS_TEAM_UPDATE.md](../../LOGISTICS_TEAM_UPDATE.md) for the incremental
database upgrade procedure. This Rider import/review adds no application migration
or separate mobile database. A full schema dump must not replace teammates' records.
Their actual schema/migration ledger still needs comparison and an approved rehearsal
before applying pending existing migrations.

Real-device camera/scanner/GPS, production HTTPS/email, Android/iOS packaging and
real operational acceptance remain separate checks. The Android build failed before
native app compilation because the local NDK installation is incomplete; it was not
deleted, replaced or worked around by changing project versions. Release application
identity and signing still use upstream placeholders and require the team's choices.
Existing finance/physical hub rollout limits in the Logistics handoff remain in force;
passing isolated tests does not certify real-money execution or deployment readiness.

Before committing, review staged, unstaged and untracked files together: the existing
index is an older partial snapshot and does not include these new files. No commit,
push, index change, `.env` change or development database migration was performed.
