# Delivery finance deployment and concurrency acceptance

2026-10-15. **Documented, not executed against an application/production database.**
Use this checklist with the normal release operator and authorized test data.

2026-10-16: Rider functionality work adds a separate prepared notification-table
migration and own profile/photo/address APIs. Inventory/evidence below predates
that addition. See docs/RIDER_FUNCTIONALITY.md for existing-database rollout,
matching-engine rehearsal and profile/notification concurrency requirements.
No actual migration is authorized or executed by this work.

Latest release evidence is in Rider `docs/integration/RELEASE_READINESS.md`:
Phase 7 upgrade on an equivalent MySQL 8.0.46/InnoDB schema, 16 two-process races
at two isolation levels and rollback/retry PASS; disposable instances shut down.
Chronological fresh MySQL bootstrap fails on the pre-existing order-items FK to
the later product-variants table. The fixture orders that dependency explicitly;
application migrations are unchanged. This is not a clone of the populated website.
Read-only local ALONA inventory found empty SQLite with 51 migrations applied and
only Phase 7 pending. The actual populated website database/configuration remains
unidentified: no actual upgrade is authorized, and no further test databases or
configuration switches may be created without approval. The detailed matrix below
remains the acceptance plan; see the latest audit for which cases are now verified.

## Current evidence

- 21 focused `SellerShipmentFinanceTest` cases passed, 166 assertions, using SQLite
  memory through the checkpoint wrapper. Includes package completion, unique charge
  and earning constraints, cancellation freeze, same-rider assignment, rollback,
  legacy totals, full/idempotent settlement and owner isolation.
- Approved sorting-center/Logistics, Rider, seller, buyer and pending-admin accounts
  cannot read admin finance or record either settlement. Only approved admins can.
  Collection and payout remain separate; a request key cannot settle another earning.
- Web anti-forgery test disables Laravel's unit-test bypass: an admin request without
  valid origin/token proof returns 419 and leaves payout unpaid; matching session
  CSRF token records payment. Real browser Fetch Metadata/session behavior still
  requires live acceptance; no security middleware was changed to make tests pass.
- Prior affected Logistics suite passed 96 tests/581 assertions. Backend runtime
  code did not change in this final Rider integration; focused tests were extended.
- Rider's focused tests and current web compile are recorded in its checkpoint.
  This is API-contract/widget evidence, not a live deployment or live payment test.

## Migration and release order — operator work, PENDING

1. Package the reviewed ALONA changes, including migration
   `2026_10_15_100000_create_seller_shipment_finance.php`. Rider's previously published
   repository does not contain the ALONA implementation. This final integration
   batch remains local; no automatic commit/push is authorized.
2. Confirm the intended database/engine/version, migration history, backup and restore
   procedure, storage permissions and cached configuration without exposing secrets.
   Use a disposable matching-engine database for rehearsal; never run application
   tests on development/production records or automatically clear shared config.
3. Coordinate maintenance/write pause for checkout and affected courier routes.
   Apply the reviewed migration through the operator's normal release process
   before serving code that expects its columns. No migration command was executed
   here except the test framework's isolated SQLite setup.
4. Verify three tables (`seller_shipments`, `seller_delivery_charges`,
   `rider_delivery_earnings`), unique order/seller, shipment-charge and charge-earning
   constraints, restrictive FKs, nullable item membership, and order funding/shipping
   snapshot columns. Existing monetary totals must match the pre-release baseline;
   existing items remain ungrouped and no historical finance rows are generated.
5. For the 2026-10-24 merged checkout, validate buyer-paid regional/weight shipping,
   per-store order splitting and actual gross/discount/net shipping snapshots.
   New orders must have `delivery_fee_payer=buyer` and no seller-funded membership
   or duplicate seller liability. Existing seller-funded shipments retain their
   50/10/40 ledger and settlement rules. Buyer-fee allocation to Rider earnings is
   not defined by this merge. See DELIVERY_FINANCE.md for the current scope.
6. Build Rider with the real reachable HTTPS `API_BASE_URL` ending in `/api`.
   The passing local web compile used `https://alona.example/api`, a placeholder
   that is unsuitable for deployment. Check web CORS/preflight, real private proof
   storage and PHP/proxy upload sizing before live acceptance.
7. Smoke-test own Rider earnings and seller liabilities; approved-admin collection
   and payout through session/anti-forgery protection; denial for Logistics/other
   roles; manual references/request keys; independent paid/collected balances.
   All transfer/collection activity remains manual. No financial admin UI was added.

If deployment shuts down midway, label its outcome UNKNOWN. MySQL DDL may leave
partial schema changes: inspect actual tables/columns/constraints and migration
history with the operator before proceeding. Do not blindly rerun, reset or use
the down migration to repair interruption; it drops financial history. No automatic
backfill, rollback, money adjustment or production migration is part of this work.

## Disposable production-engine concurrency matrix — PENDING

Confirm the actual deployment engine/isolation level. SQLite cannot verify row-lock
contention. For MySQL/InnoDB, include the configured isolation level and REPEATABLE
READ. Use separate connections/processes and explicit barriers, not sequential HTTP
requests presented as a concurrency test. Use isolated test proof storage and fixtures.

| Race / interruption | Required outcome |
|---|---|
| Two final sibling parcels complete together | Parent order → shipment → member/parcel lock ordering; fresh locked reads see committed siblings. Both valid parcel transitions finish, one PHP 50 charge and one PHP 40 earning only, Logistics PHP 10. No orphan row. |
| Completion retry / replay | Existing API duplicate-action response is acceptable; totals and unique-row counts never increase again. |
| Final completion fails after charge insert | Delivery, charge, earning and completed_at roll back together. A later legitimate retry creates exactly one pair. |
| Cancellation versus dispatch | A pre-dispatch cancellation is consistently excluded, or a dispatched member cancellation is rejected. Never earn by deleting a required member; all-cancelled package earns nothing. |
| Two different riders assigned concurrently | One shipment rider wins; conflicting assignment fails. All assigned member parcels agree; no split earning. |
| Two settlement requests on one entry | Identical request/reference retries return the recorded result; conflicting attempts return 409. One immutable full settlement with original actor/time/reference. |
| Same settlement key on different entries | Database uniqueness admits one; the other returns 409 and remains unsettled. |
| Different seller packages in one parent order | No deadlock caused by reversed lock order; shared order status converges, with one charge per successfully completed seller shipment. |

Record engine/version/isolation, fixture IDs, barrier order, response codes, row
counts and redacted results. No credentials or real customer data in reports. Attach
results to the Rider checkpoint before claiming Phase 10 production-engine checks
passed. Native device/camera, SMTP verification, live API reachability and complete
real-world workflow acceptance also remain pending.
