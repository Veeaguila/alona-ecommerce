# Rider job earnings and grouped manual payouts

31 October 2026. Implementation authorized by the user after comparing
PocketFinds ca70ed1 and ALONA. Preserve historical seller-funded earnings and COD.

- No numeric rates are assumed. The approved company owner must explicitly enable
  and configure positive pickup and delivery rates. Transfer pay is optional until
  compensated trips are used. Changes affect future
  assignments only; no historical rates or paid records are manufactured.
- Confirmed work unit: one Seller-order shipment pickup/delivery leg, even with
  multiple parcels. User explicitly selected this over parcel pay. Transfer pay is
  once per explicit dispatched trip with a closed parcel
  manifest; it is earned only after every manifest parcel reaches the destination.
- Snapshot approved rates and recipient at assignment. Earn pickup at physical
  center receipt and delivery after proof/OTP completion. Cancel only unearned
  snapshots on reassignment. Existing seller-funded work stays in its original ledger.
- Group earned unpaid jobs for one Rider/company into a prepared payout. Preparation
  reserves earnings, not bank funds. Record actual full payment with private proof,
  reference, receiving-account snapshot and an explicit company-payroll attestation.
  No COD offset, implicit shipping deduction, payment provider or invented balance.
- Riders maintain their own receiving instructions and acknowledge actual payment.
  Owner actions are company-scoped; Hub Staff cannot configure or pay company funds.
- Existing cash collection/remittance remains separate. This version does not
  automate shipping subsidies, expenses, Seller settlements, refunds or cash-out.
- Additive schema only; no application migration will run automatically. Missing
  finance storage must not break existing operational routes. Tests use SQLite
  :memory: or mocked mobile HTTP, never application data.

## Operator flow

1. Apply only the reviewed pending schema delta after the receiving owner's
   target-schema comparison, private backup and matching-engine copy rehearsal.
   This source change has not migrated any application database.
2. The approved company owner opens **Rider payouts → Job rates** on the web, or
   **Operations → Rider payouts → Approved job rates** on Logistics mobile. Rates
   start blank/disabled. Enter approved amounts and enable future assignments.
3. Seller marks every item in a Seller-order shipment ready; Logistics accepts
   each pickup request. Assignment groups its parcels under one Rider and one
   immutable leg/rate snapshot. Every parcel still needs its physical confirmation.
4. Pickup becomes earned after all active shipment parcels reach the center.
   Delivery becomes earned after every active parcel is delivered with its assigned
   Rider's valid photo and the existing Buyer OTP. Partial completion earns neither
   a partial rate nor a duplicated full rate. Buyer receipt does not create more pay.
5. Rider opens **My Earnings → Add receiving method** and saves their own Cash,
   GCash or bank instructions. Instructions are encrypted with the existing APP_KEY;
   lists show masked labels. Preserve that key and private uploads during rollout.
6. Owner selects Rider and optional Manila dates, reviews completed unpaid jobs,
   chooses Rider-provided receiving instructions, then prepares a grouped payout.
   Preparation reserves these earnings; it does not reserve bank funds or send cash.
7. Owner reviews the prepared recipient details, makes the actual payment outside
   ALONA, then records the exact full amount using a unique reference, private image
   or PDF proof (up to 5 MB) and company-payroll/actual-payment confirmation. Mobile
   currently selects image proof; web supports PDF too. There is no provider transfer.
   A denied/unavailable gallery shows a retryable error. While the picker is opening,
   duplicate picks and payment submission are disabled; selecting an image does not
   record a payment.
8. Rider refreshes My Earnings and confirms receipt only after verifying the real
   payment. Owner sees the confirmation. An unsent prepared payout can be cancelled
   with a reason; its earnings become available again while history is retained.

## Transfers and exceptions

Compensated hub transfers require real origin/destination hubs, the existing route
and custody prerequisites, and an approved Online Rider based at the origin. Owner
creates an explicit complete trip manifest on the web Transfer trips tab; include
every parcel in each selected Seller shipment and use one destination. The original
hub workspace receives parcels individually. One snapshotted transfer rate becomes
earned only after every manifest transfer is physically received. Existing transfers
without a compensated trip retain their process and create no guessed transfer pay.
Local pickup/delivery continues through the existing routing configuration; enabling
required hub routing still needs actual hubs, directed routes and coverage first.
The owner can clear the optional transfer rate to stop new compensated trip
dispatches while retaining pickup/delivery rates. Existing trip snapshots keep
their agreed amounts; omission of the transfer field preserves its configured value.

Failed delivery, lost/damaged work, incomplete/RTS jobs and disputed money require
operator review. They do not automatically earn a new rate, deduct COD, rewrite a
paid batch or create clawbacks. Already earned/paid history survives later returns.
Historical assignments are not backfilled when rates are enabled. Reassigning an
unearned job cancels its old snapshot; already completed work cannot be reassigned
as a new payable job. Rate changes affect future assignments only.
For a supervised physical Rider handover, both Riders acknowledge and Logistics
confirms the complete shipment. Its pending leg continues for the receiving Rider
at the original agreed amount/revision, even if rates were later changed/disabled.
The former pending snapshot is retained as cancelled and linked to its replacement;
earned/paid legs are not moved or repriced. The job still earns only after all
required physical receipts or delivery proof/OTP. No split or additional rate is
created. See [the follow-up review](testing/results/POST_IMPLEMENTATION_REVIEW.md).

## Storage and interfaces

`2026_10_31_000001_create_rider_job_payments.php` creates six empty tables:
`rider_pay_settings`, `rider_pay_destinations`, `rider_pay_trips`,
`rider_pay_batches`, `rider_pay_earnings`, `rider_pay_batch_items`; it adds only
nullable `hub_transfers.rider_pay_trip_id` with a restricted foreign key. No seeding,
backfill, guessed rates or historical ledger mutation. Its down migration refuses
automatic destruction of payment history. This is the fifth release addition;
the existing 99 migration sources remain intact and the manifest now covers 100.

`RiderJobPay` handles integer cents, locked assignment/receipt snapshots, unique
work/payment/request keys, reserved earnings, append-only receiving instructions,
audit records and private-proof rollback cleanup. Existing operational controllers
call it inside their transactions. Without the complete schema, these hooks skip
new payroll and dedicated finance endpoints report 503; original work remains.

Owner API: `/api/sorting-center/payroll` (summary/settings, Rider review, payout
prepare/details/pay/cancel/private proof, trip dispatch/history). Approved Hub Staff
cannot use company payroll. Rider API: `/api/rider/job-pay` (own totals/history,
receiving instructions, own payout details/private proof and receipt confirmation).
Web `/sorting-center/payouts` and the two Flutter clients use the same backend.
Tests cover duplicate/retry, authorization, exact amounts, private evidence and
rollback; SQLite results do not establish live MySQL contention behavior.

## Funding boundary

This records company-payroll cost and payment evidence. Shipping receipts, shipping
discounts/subsidies, Seller settlements, expenses and actual company profit are not
newly calculated or distributed. There is no invented Logistics wallet balance.
The owner must fund the chosen rates. COD collection/remittance stays in Cash Desk;
historical Seller-funded 50/10/40 records stay in their existing separate ledger.
Preserving those ledgers prevents charging old Seller liabilities to new Buyer-paid
orders. A complete shipping-revenue/settlement/subsidy ledger is a separate policy.

Current verification is recorded in the integration checkpoint and the payroll
review report; native devices, target-schema rollout and real payments are separate
acceptance work. No application migration, commit, push or deployment is automatic.
