# Seller shipment delivery finance

Seller-funded ledger implemented 2026-10-15. On 2026-10-24 the user selected the
incoming buyer-paid shipping policy for new checkout orders. Historical records
and existing seller-funded settlements remain unchanged. Database rollout has not
been verified or performed as part of this merge.

## Scope and money

New checkout orders use `delivery_fee_payer=buyer`, with per-store shipping quotes,
and do not create seller-funded shipment memberships or seller delivery liabilities.
`SellerShipmentFinance::createMembership` retains its seller-funding guard, avoiding
an additional seller charge on an order whose buyer already pays shipping.
Parcels retain their item links and ITEM scan references. Existing orders retain
their original payer, totals and memberships; there is no historical backfill.

For an existing seller-funded successful shipment, `seller_delivery_charges` records PHP 50.00 seller
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

The merged checkout uses the incoming regional/weight rates, express pricing,
per-store order splitting, free-shipping eligibility and shipping-voucher handling.
New orders snapshot the actual `shipping_fee_before_discount`, `shipping_discount`
and net `shipping_fee`, with `delivery_fee_payer=buyer`. `orders.discount` remains
the merchandise discount. Standard/express metadata remains available to Seller
and Logistics workflows; store pickup follows the incoming courier-bypass flow.

The existing PHP 50/10/40 seller-funded ledger continues to apply only to its
seller-funded shipments. No new rule maps buyer shipping receipts to Rider earnings
or payouts. That allocation needs a separately defined policy; this merge does not
invent an allocation or rewrite historical settlements.

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
