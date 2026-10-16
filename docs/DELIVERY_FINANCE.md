# Seller shipment delivery finance

Implemented 2026-10-15. Migration file is prepared and exercised only by isolated
SQLite tests; it has not been applied to the development/production database.

## Scope and money

Checkout creates one `seller_shipments` row per seller/new order and assigns its
items through `order_items.seller_shipment_id`. Parcels retain their item links and
ITEM scan references. Historical orders remain buyer-funded, with null memberships
and unchanged totals. There is no historical backfill.

For a successful shipment, `seller_delivery_charges` records PHP 50.00 seller
liability, PHP 10.00 Logistics allocation and PHP 40.00 rider allocation.
`rider_delivery_earnings` separately records the completing rider's PHP 40.00.
Amounts are fixed decimal strings; allocation uses integer cents. The rule version
is stored, so future weight/quantity/size pricing must preserve old amounts.
Vouchers, merchandise discounts, buyer shipping and buyer payment status do not
change the delivery ledger. Existing marketplace commissions remain separate.

Collection and payout are independent, full-payment-only manual records. Each
records its actor, timestamp, reference and unique request key. There is no gateway,
partial payout, expense deduction, split shipment, multiple-rider allocation,
automatic reversal or extra pickup/return earning. Immutable amount/identity facts
and recorded settlements cannot be rewritten through the models or endpoints.

## Workflow and concurrency

`SellerShipmentFinance` is used by checkout and the existing seller/pickup,
assignment and delivery paths. All noncancelled items must have parcels ready at
the same center, pickup location and destination, assigned to the same rider,
before dispatch. Missing/late-created members block dispatch. Cancellation freezes
at dispatch. Fully cancelled shipments earn nothing.

The final assigned-rider, proof-backed parcel completion recognizes money only
when every noncancelled member parcel is DELIVERED with that rider's proof. Parcel
completion and both finance rows commit atomically. Failed/incomplete shipments,
pickup-only operations, proof upload alone and buyer confirmation do not accrue.
New shipment orders reject buyer confirmation before courier proof-backed delivery.
A later ecommerce return does not create additional earnings or rewrite earlier ones.

Lock order is parent order → seller shipment → item/parcel rows. Parent serialization
also protects shared order aggregation; locking member/proof reads avoid stale
REPEATABLE READ snapshots. Unique order/seller, shipment-charge and charge-earning
constraints provide a database backstop against duplicates. Existing parcel-level
API and Rider history data contracts remain. Rider now consumes the owned earnings
API in a separate delivery-earnings view; operational layouts/history are preserved.

## Checkout compatibility

New checkout quotes and created orders have zero buyer shipping, including express.
The selected express/standard method, labels, descriptions and existing operational
behavior remain. Legacy config and historical order totals are unchanged. Source
inspection found no express-priority Logistics handler; no new priority guarantee
or premium fee was introduced.

`orders.discount` remains merchandise-voucher discount. New orders snapshot
`shipping_fee_before_discount=0`, `shipping_discount=0`, `shipping_fee=0` and
`delivery_fee_payer=seller`. Seller funding is a payer change, not a shipping voucher.
Historical gross shipping/discount snapshots remain null. Existing fixed/percentage
vouchers remain merchandise-only; this work adds no shipping-voucher type.

## HTTP contracts

| Endpoint | Access / behavior |
|---|---|
| GET `/api/rider/earnings` | Sanctum + approved Rider; own rows, nested charge shipment/currency, total/paid/outstanding sums. |
| GET `/api/seller/delivery-charges` | Sanctum + seller; own shipment charges, total/collected/outstanding and Logistics/rider allocation sums. |
| GET `/admin/delivery-finance` | Existing approved-admin session; JSON `charges` and `earnings` reconciliation. |
| POST `/admin/delivery-charges/{id}/record-collection` | Approved-admin session + CSRF; record entire seller liability collected. |
| POST `/admin/rider-earnings/{id}/record-payout` | Approved-admin session + CSRF; record entire rider earning paid. |

Reads accept optional `from`, `to` (YYYY-MM-DD, application timezone), `page` and
`per_page` (1–100). Sums cover all matching owner/date rows, independently of page.
Settlement bodies contain `request_key` (UUID) and `reference` (1–150 characters).
No amount or status is accepted. Identical retries return the recorded result;
different attempts on a settled entry or reused keys return 409. No unpay/edit
endpoint or admin UI was added. Buyer payment_status is never used for settlement.

## Validation and rollout

Current focused finance verification: 21 tests/166 assertions passed, including
Logistics/admin denial, anti-forgery checks with the unit-test bypass disabled,
cancelled-member freeze and cross-entry request-key reuse. The prior Logistics suite
passed 96 tests/581 assertions; backend runtime code is unchanged in this final
Rider integration. Syntax/diff checks and Rider verification are recorded in the
integration checkpoint. Storage tests require writable fake test disks.

The migration/release order and disposable production-engine contention matrix
are in DELIVERY_FINANCE_DEPLOYMENT.md. Those operator/live checks remain pending.

Before deployment: review/apply the migration under the normal release process
before serving code expecting the new columns; coordinate new-order activation.
Do not run application tests on existing data or backfill old orders. Verify row
locking/concurrent sibling delivery and settlement requests on a disposable database
matching the production engine; SQLite tests do not reproduce MySQL contention.
Live checkout/admin-CSRF/API acceptance and existing device/email/upload acceptance
remain manual. No production migration, payment or database record was changed here.
