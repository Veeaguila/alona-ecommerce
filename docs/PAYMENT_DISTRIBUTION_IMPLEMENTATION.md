# Payment distribution implementation

Implemented in the existing Laravel/Inertia application on 10 November 2026. This document describes the new code, not a production deployment or completed money transfer. The existing `money_distribution_plan.md` remains a historical proposal; the user's current implementation request supplies the fixed percentages below.

## Scope and rollout

The new policy applies only to orders snapshotted inside checkout after the additive schema is installed and `PAYMENT_DISTRIBUTION_ENABLED=true`. Both web and mobile checkout use the same backend hook, including split store orders. Existing orders are not backfilled or repriced. Historical seller-funded shipments and company payroll remain intact. The old seller withdrawal balance excludes new-policy orders, and new delivery work cannot also create a legacy fixed delivery earning. Pickup and inter-hub transfer payroll remain separate company expenses; they are not additional deductions from these allocations.

No live database was queried or migrated, no payment was sent, and no Git commit/push/reset was performed. The user authorized a backend checkpoint log because `mobile/rider` and its checkpoint helper are absent. Source hashes, commands and outcomes are in `docs/payment-checkpoints/history.jsonl`; the synchronous helper is `scripts/payment_checkpoint.cjs`.

## Financial rules

All new arithmetic and ledger amounts use integer centavos. Monetary API input/output uses decimal strings. Floating-point money input is rejected. Product commission is 10%, rounded half up per immutable order item; the seller receives the exact remainder. Each seller has separate item lines. Shared shipping is allocated equally across sellers in stable seller-ID order, using largest remainder; each seller shipping portion then uses 70/20/10 largest remainder. This preserves every centavo without duplicating the charged shipping amount. Ordinary checkout already creates separate store orders and retains the existing shipping quote and rates (default Standard PHP 50, Express PHP 120).

| Buyer payment | Seller | Product commission | Rider | Logistics | Shipping commission |
| --- | ---: | ---: | ---: | ---: | ---: |
| PHP 500 + 50 | 450 | 50 | 35 | 10 | 5 |
| PHP 500 + 120 | 450 | 50 | 84 | 24 | 12 |

The product base is the recorded order-item price after price promotions and before order vouchers. Voucher and shipping discounts are separately snapshotted. They create a visible funding requirement, never a silent reduction in a participant's percentage. A receipt identifies the actual subsidy/fee funder. Gateway fees are separately recorded platform expenses and require funded replacement before full release. Checkout has no separate tax component; its snapshot records zero taxes, and any unexplained difference in the pricing identity blocks settlement. Introducing a tax regime requires an explicit checkout/accounting integration rather than treating the difference as commission.

Order snapshots and allocation lines are immutable through their models. A shipping beneficiary can be bound once, from the completed shipment and valid Rider photos. Corrections/refunds append audit events. Orders with missing delivery evidence, ambiguous shipping beneficiaries, incomplete or cancelled items, unverified payment, active returns/complaints, unresolved refund records, insufficient funding, or unexplained pricing cannot release money. The snapshotted waiting period defaults to the existing seven days after delivery, including orders marked completed early.

## Cash, COD and online payment

The states are deliberately separate:

1. **Calculated:** checkout records allocations; no money is available.
2. **Captured:** an authenticated provider adapter confirms the gross online payment. Fulfillment may proceed; this event creates no usable settlement cash.
3. **Collected (COD):** the existing COD ledger identifies the Rider, parcel, order, evidence and actual cash. New-policy collection totals cannot exceed the buyer's order total.
4. **Remitted (COD):** a reviewed match links a confirmed existing hub remittance to a specific collection. Hub custody is not platform funding. Collection, remittance and order caps apply across matches.
5. **Reconciled:** an authorized admin records an evidenced receipt in the configured platform account, or the authenticated adapter reports actual online settlement. COD receipts reference the matching event. Partial receipts remain held. Confirmation of a discrepancy never creates funding.
6. **Settled:** delivery and hold checks pass and all participant allocations are actually paid. A reservation is not a completed payout.

All financial mutations use transactions with order/distribution locks. COD matching additionally locks source rows and uses current locking reads to cap reuse across orders under MySQL repeatable-read isolation. Unique request keys and references prevent duplicate effects; changed payloads with a reused key are conflicts. SQLite tests verify invariants and retries; they do not certify concurrent MySQL execution.

The legacy unsigned callback and browser success action cannot verify new-policy orders. Historical payment handling is preserved for the old cohort and must not be used as evidence for this ledger.

### Internal provider adapter contract

`POST /api/webhooks/settlement` is an **internal adapter endpoint**, not a ready-made PayMongo/GCash/Maya integration. The selected provider adapter must verify the provider's real signature, merchant, transaction, currency, amount and settlement account before sending an event. Do not give this secret to a browser or mobile app.

Configure:

```dotenv
PAYMENT_DISTRIBUTION_ENABLED=true
PAYMENT_SETTLEMENT_ACCOUNT=<verified platform bank/account identifier>
PAYMENT_SETTLEMENT_MERCHANT=<merchant identifier>
PAYMENT_SETTLEMENT_WEBHOOK_SECRET=<random secret of at least 32 characters>
```

The request contains `event_id`, `merchant`, `order_id`, `currency` (`PHP`), `status` (`captured` or `settled`), decimal-string `amount`, decimal-string `gateway_fee`, `reference`, and `account`. Capture reports the gross buyer amount and zero fee. Settlement reports the actual net receipt; net plus fee must equal the snapshotted buyer payment. Capture and settlement use distinct stable event IDs. Reuse the same event ID and payload on retry; generate a fresh request timestamp/signature.

Headers: `X-Alona-Timestamp` (Unix seconds, accepted within five minutes) and `X-Alona-Signature` (hex HMAC-SHA256 of `timestamp + '.' + exact_raw_request_body`). Unconfigured adapters return 503. Wrong signatures/merchant return 401; amount/currency mismatches are rejected. A receipt after cancellation is retained for reconciliation and does not revive fulfillment.

## Payouts and refunds

Beneficiaries request the remaining eligible amount of a specific allocation. Actual receiving details are required and encrypted at rest. Admins may prepare the same requests. Reservations and paid amounts share the order funding cap. Only an approved admin can record a transfer outcome, with a unique bank/processor reference and an evidence record reference. `reconciling` retains the reservation after an uncertain result; `cancelled` is for confirmed nonpayment. Operators must check eligibility before sending any manual transfer. The application does not initiate bank or wallet transfers.

Existing return/refund records block release until reconciled. The admin links a completed refund record, component, item, amount, liability decision and evidence. Product refunds cumulatively reverse the original 10% commission and seller allocation when the seller is liable; full reversal removes the original amounts exactly. Logistics/platform liability is recorded separately when applicable. Shipping refunds reverse the proportional original shipping commission, allocating its reversal across stable platform-line IDs; delivered Rider/Logistics work is preserved and the remaining loss is explicit. Rider wages and COD custody are never automatically offset.

Paid history stays paid. A post-payment refund exposes recovery and a funding deficit rather than erasing the transfer. Remaining release requires replacement funding where needed. Existing reservations must be resolved before refund reconciliation. Investigation determines liability; the system does not invent a legal conclusion or change existing buyer return rights.

## Dashboards and administration

Web: `/payments/distributions`, linked from Admin, Seller and Logistics navigation. Approved Riders can open the same page; mobile clients can consume `GET /api/payments/distributions` and `POST /api/payments/distributions/{id}/payouts` using existing Sanctum authentication. No Flutter source changes were made.

- **Admin:** product sales, shipping, captured/collected payments, reconciled cash, both commissions, calculated and recognized platform revenue, pending settlements, completed payouts, refunds, fees, discount funding, source references, payout receiving details and audit history.
- **Seller:** own product sales, 10% commission, 90% allocation, pending/available/reserved/paid amounts and reversals. The old report reads new policy amounts but cannot withdraw them through the historical payout pool.
- **Logistics:** own shipping allocations, platform/Rider split, pending COD remittances, payout and adjustment history.
- **Rider:** own pending/available/paid allocation, Standard/Express earnings, completed deliveries and COD custody obligation.

Only an approved admin can reconcile platform receipts, refund liability, transfer outcomes and delivery profitability rules. COD matching also accepts the owning approved Logistics account through the controller. Sellers can declare costs only for their own products. Policies can warn or block based on operating costs, minimum earnings/margins, shipping-option availability and blocked address zones. Existing shipping-rate administration remains the fee authority; percentages are not configurable.

Evidence fields store references and verification notes for retained financial evidence; they are not an automatic bank-verification mechanism. Retain source documents in controlled storage and reconcile the journal to actual statements. Dashboard histories show the latest 50 events per order; the database retains all events.

## Migration and deployment requirements

Created, **not applied to development/production**:

`database/migrations/2026_11_10_000001_create_payment_distribution_tables.php`

It adds seven tables: `payment_distributions`, `payment_distribution_lines`, `payment_distribution_events`, `payment_distribution_payouts`, `payment_profitability_rules`, `payment_product_costs`, `payment_policy_audits`. Existing financial tables are reused for COD, refunds and historical payroll. No backfill or destructive conversion is included.

Before any owner-authorized schema upgrade, follow `TEAMMATE_AI_HANDOFF.md` and `docs/upgrade/LOGISTICS_SCHEMA_MANIFEST.json`, review the target database delta and backup process. Missing schema keeps old checkout operational and new financial endpoints unavailable. Disabling the feature stops new snapshots and new releases while retaining financial records; reconciliation remains possible. Never roll back by deleting financial history.

Remaining external setup: apply the reviewed migration under explicit authorization; configure the real settlement account and provider adapter; retain/verify manual transfer evidence; set realistic delivery costs and zones; test MySQL contention in a disposable environment; then reconcile opening operations. Automated bank/wallet disbursement, provider-specific chargeback automation, historical balance migration and a new tax regime are not implemented or implied by a database status.

## Files delivered

New:

- `app/Services/Payments/DistributionLedger.php`, `Money.php`, `Profitability.php`
- `app/Models/PaymentDistribution.php`, `PaymentDistributionLine.php`, `PaymentDistributionPayout.php`
- `app/Http/Controllers/PaymentDistributionController.php`, `PaymentSettlementWebhookController.php`
- `config/distribution.php`, `routes/payments.php`
- `database/migrations/2026_11_10_000001_create_payment_distribution_tables.php`
- `resources/js/Pages/Payments/Distributions.vue`
- `tests/Feature/PaymentDistributionTest.php`
- `scripts/payment_checkpoint.cjs`, `docs/payment-checkpoints/CURRENT.json`, `docs/payment-checkpoints/history.jsonl`
- This implementation report.

Modified:

- `.env.example`, `routes/web.php`, `routes/api.php`
- `app/Http/Controllers/BuyerOrderController.php`, `BuyerComplaintController.php`, `SellerReportController.php`, `SellerReturnController.php`, `AdminPayoutStatusController.php`
- `app/Services/CodLedger.php`, `CommissionService.php`, `RiderJobPay.php`
- `resources/js/Layouts/AdminLayout.vue`, `SellerLayout.vue`, `ScLayout.vue`
- `tests/Feature/BuyerPaymentTest.php`, `tests/Feature/Logistics/RiderJobPayTest.php`

All pre-existing edits, deleted scripts, mobile changes, and `docs/money_distribution_plan.md` were preserved.

## Validation

All application tests use explicit `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, empty `DB_URL`, and a non-existent isolated `APP_CONFIG_CACHE` path. Database schema is exercised only inside disposable in-memory tests.

Final results are recorded below after the final run. Earlier checkpoints include 14 new tests / 382 assertions passing, 56 expanded integration tests / 1,073 assertions passing, Vue SFC compilation, and a successful production Vite build to ignored `.local/payment-build` (910 modules).

Two existing `BuyerMobileCheckoutTest` failures reproduced with `PAYMENT_DISTRIBUTION_ENABLED=false`: cart retry fails after the cart is consumed, and a missing reviewed quote is accepted. The feature-disabled run passed 14 of 16 tests. These existing mobile checkout defects were not silently weakened or removed from the suite.
