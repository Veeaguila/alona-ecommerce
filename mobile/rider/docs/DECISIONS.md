# Decisions

## 29 October 2026 — Rider belongs in ALONA

Implemented per user request: teammate Rider interface under mobile/rider as ordinary
tracked files, with separate Flutter dependencies/platforms/tests/docs. Other clients
can use other mobile directories later. A nested repository/submodule would omit
source from a normal teammate pull. ALONA remains the only backend/database. The
old sibling checkout is preserved as reference; source provenance is in UPSTREAM.md.

## Financial boundary

Current source/checkout tests show buyer-funded configured shipping on new orders.
Historical seller-funded shipment earnings retain their recorded memberships/policy.
Do not apply historical 50/10/40 allocations to new buyer-funded orders or copy an
order total onto every parcel. Mobile displays backend totals and accepts explicit
cash evidence; it does not invent compensation, payouts or refund execution. Refer
to ALONA docs/DELIVERY_FINANCE.md for the authoritative boundary.

Older imported audits describe the upstream commit, not current schema observations,
source locations or test counts. Use CURRENT_STATE and current integration bundle.

## 31 October 2026 — Shipment jobs and manual grouped payroll

User explicitly selected per-Seller-shipment pickup/delivery work after the
PocketFinds source comparison (reference commit ca70ed1), keeping transfer pay once
per complete explicit manifest. Laravel snapshots owner-configured future rates;
none are assumed. Pickup needs all physical receipts and delivery all proof/OTP
completions. Grouped manual payouts reserve earned jobs and record private proof,
reference and recipient instructions; the Rider confirms actual receipt. Company
payroll funds this policy; COD and historical Seller-funded records stay separate.
This keeps one batch payment for many jobs without inventing a wallet or a provider
transfer. Exceptions/subsidies/settlements remain separate policy. Migration rollout,
real payments and numeric-rate approval remain operator responsibilities.
Follow-up handover fix: this remains one unfinished shipment job. Both Rider
acknowledgments and Logistics confirmation continue its existing rate/revision for
the receiving custodian; they do not generate another rate or rewrite earned pay.
