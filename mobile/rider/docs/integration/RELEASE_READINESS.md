# Final release audit — 2026-10-15

**BLOCKED for deployment:** the populated ALONA database has not been identified.
Rider continues using ALONA's REST API and its existing shared database. Nothing
in this review changes application configuration or adds a runtime database.

Latest parcel workflow: [PARCEL_DELIVERY_IMPLEMENTATION.md](PARCEL_DELIVERY_IMPLEMENTATION.md).
Owned scan lookup, PHOTO-only completion and Seller notices/private proof are
implemented without additional schema. 72 Flutter tests, 59 Laravel/537 assertions,
affected Dart analysis, Seller Vue compilation and web build PASS. Android build
BLOCKED by missing SDK; native camera/scanning and live four-role acceptance PENDING.
The approved scanner dependency is pinned to 7.4.2; no other packages upgraded.

2026-10-16 update: functionality fixes now also prepare
`2026_10_16_100000_create_rider_notifications_table`. The historical inventory below
predates that file; it is not a current migration-status query. Both prepared migrations
must be checked against the actual schema before operator action. See
FUNCTIONALITY_AUDIT.md and ALONA docs/RIDER_FUNCTIONALITY.md for API/profile/storage,
test evidence and the additional matching-engine/manual acceptance requirements.

## Configuration and schema inventory

- **Observed:** ALONA `.env` selects `DB_CONNECTION=sqlite`; no active
  `DB_DATABASE` override or cached Laravel configuration. `config/database.php`
  resolves `database/database.sqlite`. Enforced read-only inspection found SQLite
  3.53.4, 51 applied migrations and zero users/products/orders/items/parcels.
- Only pending migration in **this local database**:
  `2026_10_15_100000_create_seller_shipment_finance`.
  Its finance tables and additional order/item columns are absent; existing
  users/orders/items/riders/centers/parcels and product-variant tables exist.
- **Observed:** Herd nginx is running. Saved site links refer to the older Logistics
  backend. Its separate MySQL configuration is not evidence of ALONA's database.
  Standard Laragon/install/configuration locations checked did not identify ALONA.
- **Unknown:** the populated website's Laravel root/configuration, database name,
  actual MySQL patch version, schema/migration history and transaction isolation.
  User selected MySQL 8.0/InnoDB as the intended production engine; this differs
  from the inspected local project. Do not switch `.env` to resolve this by guesswork.
- **Configuration follow-up, 2026-10-16:** user reconfirmed the current ALONA
  project root. `.env:23` actively selects SQLite; host/port/`laravel` MySQL
  settings on lines 24–26 are commented examples, not a detected ALONA schema.
  No process/user/machine DB override, active DB URL or config cache was found.
  `MySQL80` is running at localhost:3306; its service `my.ini` selects InnoDB.
  MySQL Workbench's local connection records host/port but no schema. Windows
  denies listing the service data directory; permissions were not changed.
  No verified MySQL ALONA database name was found. Need the actual schema name
  from the user's database manager or the original connection-config location;
  no passwords should be pasted into chat.
- No credentials, customer rows, actual database writes, configuration switches or
  actual migration commands were used. No further disposable databases may be
  created under the latest instruction without approval.

## Disposable verification — PASSED with explicit limits

Before the latest restriction, an isolated MySQL 8.0.46 instance used its own
temporary data directory, random loopback port and synthetic records. Every
instance was shut down; the installed/shared MySQL service was not used.

- **Confirmed existing migration issue:** chronological fresh MySQL setup fails
  at `2026_08_24_000005_create_order_items_table.php:15`: its variant FK references
  `product_variants`, created on August 29. This predates the reviewed finance
  changes. Existing databases with both tables need not replay that migration.
  A clean chronological bootstrap cannot currently be claimed to work.
- To test the existing-schema upgrade separately, the harness created the existing
  variant table before its dependent item table, only in the disposable fixture.
  No migration source or FK checking setting was changed. All 51 baseline
  migrations plus Phase 7 completed; the three finance tables use InnoDB with
  unique shipment/charge/earning/settlement keys and financial FKs.
- A synthetic historical order/item inserted before Phase 7 retained every original
  order field, buyer funding/default snapshots and null shipment membership. No
  historical shipment, charge or earning was backfilled. This is an equivalent
  schema rehearsal, **not a restored copy of the unidentified populated database**.
- **16 two-process concurrency cases passed:** sibling completion, same-parcel
  replay, conflicting rider assignment, dispatch-before-cancel, cancel-before-dispatch,
  identical payout retries, conflicting payout retries and shared payout-key races,
  each under REPEATABLE READ and READ COMMITTED. Shared-row tests observed actual
  InnoDB lock waits; shared-key tests synchronized both writes after key lookup.
  Expected 200/422/409 results, unique row counts, 50/10/40 amounts and affected
  statuses were checked. Controller/service calls bypass HTTP middleware; prior
  Laravel access/anti-forgery tests remain the evidence for that layer.
- Injected earning persistence failure rolled back final delivery, shipment
  completion and charge; retry accrued once. Separate seller-collection contention,
  different seller shipments sharing one parent order, actual production isolation
  and a restored production-schema rehearsal remain PENDING.
- Earlier 12 focused Flutter tests, 21 Laravel/166 assertions, targeted analysis
  and web compile are retained unchanged; completed suites were not rerun here.

Local ignored evidence: `.recovery/mysql-release-1792067407277/` contains migration,
concurrency and rollback results plus confirmed shutdown. The earlier chronological
failure is in `.recovery/mysql-release-1792067271100/`. Read-only inventory:
`.recovery/existing-db-inventory.json`. Do not publish logs/data directories.

## Git and safe commit boundaries

Rider `master` / `9668e82`; ALONA `feature/sorting-center` / `da3514f`.
Both indices are empty. Existing teammate changes remain intact. Scoped scans of
changed/untracked files found no credential-pattern matches; both whitespace checks
passed. ALONA's unchanged CA bundle contains public certificates, not a private key.
Pattern scans are limited evidence, not proof that every possible secret is absent.

For a later explicitly authorized commit:

1. Rider application/test boundary: `lib/pages/profit_dashboard_page.dart`,
   `lib/services/rider_earnings_api.dart`, `lib/pages/rider_portal_page.dart`,
   `lib/pages/delivery_history_page.dart`, `test/rider_earnings_test.dart`,
   `test/delivery_history_test.dart`. Release backend API before exposing this client.
2. ALONA finance boundary must remain coherent: Phase 7 migration; three new finance
   models; `Order`/`OrderItem`; `SellerShipmentFinance`/`OrderItemPickup`;
   `DeliveryFinanceController`, buyer/seller order controllers and affected Logistics
   assignment/pickup/delivery controllers; API/admin routes and finance tests.
   These overlap earlier eligibility/history changes: review actual hunks and
   teammate ownership before deciding whether they can be split safely. Include
   required integration tests; do not publish finance controllers without their schema.
3. Keep project memory/reports and complete checkpoint bundles/backups reviewable
   separately. Review ALONA's untracked `AGENTS.md` as a policy change.
   Exclude `.recovery/`, environment files, database files, private uploads, keys,
   dependencies and build output. Never blanket-stage the worktrees.

No staging, commit, push or reset was performed. Historical file lists in
`FINAL_REVIEW.md` do not supersede these current boundaries.

## Existing-database migration procedure — approval required

These are operator instructions, not actions performed or permission to proceed.

1. Identify the website's actual Laravel root and effective configuration, including
   external environment overrides/cached config. Confirm driver, database name,
   MySQL version/isolation and migration history read-only. Do not reuse the old
   Logistics database or connect Rider directly to any database.
2. Take and verify a consistent backup of the **existing** database, migration table
   and private proof storage. Restore it to an approved isolated environment and
   rehearse there before touching the original. Check required parent tables use
   compatible unsigned BIGINT IDs/InnoDB and have no conflicting finance tables,
   columns, FK/index names or partially applied migration. Inspect the complete
   pending list; do not assume the local SQLite pending list matches production.
3. Review the single migration's generated SQL on the approved copy. Verify new
   finance tables, order columns and nullable item FK; compare historical order
   totals, counts and memberships before/after. No backfill. Run remaining contention
   and workflow acceptance against the copy using actual deployment isolation.
4. Obtain explicit approval for the actual database migration after the evidence
   and backup/restore procedure are reviewed. Pause checkout/courier writes, queues
   and scheduled writers through the release operator's normal maintenance process.
   Record migration/schema state before running anything.
5. From the confirmed website root, using its confirmed existing connection, the
   approved operator applies only this migration:

   ```powershell
   php artisan migrate --path=database/migrations/2026_10_15_100000_create_seller_shipment_finance.php --force
   ```

   This command has **not** been run on the application database. If prerequisites
   differ or other migrations are pending, stop for a reviewed plan; do not hide
   them with a blanket migration or change connection configuration automatically.
6. Verify the migration row, constraints, historical totals/counts/null memberships
   and empty historical finance records. Release matching ALONA code, smoke-test
   using authorized test data, then build Rider with the real HTTPS API URL.
   Resume writers after acceptance. Collection/payout remain manual admin actions.
7. If shutdown/error interrupts DDL, mark UNKNOWN, preserve logs and inspect actual
   columns/tables/constraints plus migration history before any retry. MySQL DDL
   can partially commit. Do not use `fresh`, reset, automatic rollback/down,
   duplicate retries or historical adjustments to repair an unknown state.

## Manual acceptance — PENDING

Record environment, tester/date, PASS/FAIL and redacted evidence for each row.

| Area | Required acceptance |
|---|---|
| Device | Real Android/iOS release build: registration, approval, login, pickup/delivery, resume and expired-token handling; existing Delivery History intact. |
| Camera | Deny then allow permission; capture/upload real proof, interruption/retry and oversized-image behavior; private proofs readable only by authorized users. |
| Email | Real SMTP verification and approval messages arrive; links use correct HTTPS host; unverified/pending/denied accounts cannot operate. |
| API | Real HTTPS origin reachable from device; web CORS/preflight, session/token ownership, error/retry behavior and proxy/PHP upload sizing meet app limits. Placeholder URL is replaced. |
| Shipment | Multi-item same-seller package and two sellers: one rider/package, proof on every noncancelled parcel, no earnings for incomplete/fully cancelled shipments, exactly 50/10/40 once, standard/express buyer fees zero and vouchers separate. Historical orders unchanged. |
| Admin | Approved admin session and real browser anti-forgery; Logistics/other roles denied. Record independent full collection/payout with real manual reference and stable UUID; identical retry succeeds, conflicting retry rejects; partial amounts rejected. The API is JSON: confirm an operator can use the approved browser/session workflow; no new finance admin page was implemented. |
| Earnings | Rider sees backend total/pending/paid/history; after authorized manual payout, refresh/resume reflects payment; another rider cannot view it. No real money transfer is automated. |

Production readiness remains BLOCKED until the actual database is identified and
its clone/schema rehearsal is verified; remaining live/manual checks are PENDING.
