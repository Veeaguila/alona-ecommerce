# Phase 7 seller shipments — implemented

2026-10-15. User approved the grouping/completion design and schema/API work.
The ALONA backend now implements one seller shipment per seller per new order,
with fixed item membership, one delivery rider and one charge after all
noncancelled parcels are delivered with assigned-rider proof.

Seller charge PHP 50.00 splits to Logistics PHP 10.00 and Rider PHP 40.00.
Collection and payout are independent full manual settlements. New buyer shipping
is zero including express; existing method metadata and historical totals remain.
No splits, partial payouts or historical backfill. Existing operational layouts and
Delivery History data/pagination are preserved; a separate Rider earnings view now
consumes the backend summary and shipment history without financial arithmetic.

Implemented schema: seller_shipments, seller_delivery_charges,
rider_delivery_earnings; item membership and new-order funding/shipping snapshots.
Locks run parent order, shipment, then members/parcels with fresh locking reads.
Unique database constraints prevent package charge/earning duplication.

Current implementation/API/rollout details and evidence:
ALONA docs/DELIVERY_FINANCE.md and .agents/architecture/architecture.md.
Current focused verification: 12 Flutter tests and 21 Laravel finance tests /
166 assertions PASS; targeted analysis clean and current web compile PASS. Earlier
96-test Logistics regression retained. See PHASE10_READINESS.md for pending release
and production-engine checks. The old proposal approval gate is cleared.

Migration file created and tested only in isolated SQLite memory. No production
migration/data change, staging, commit or push. Deployment-engine contention and
live checkout/admin/API acceptance remain pending, along with Phase 10 device/email.
