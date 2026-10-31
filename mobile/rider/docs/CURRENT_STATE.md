# Current state

Verified 31 October 2026: active client is ALONA's mobile/rider, backend ../.. from
the app. No separate mobile database or nested Git repository. UPSTREAM.md records
the 223-file teammate import. Old sibling source is preserved, not a build dependency.

The teammate interface now uses real pickup/decline requests, canonical delivery
failure outcomes, consistent retry/RTS restrictions and validated physical handovers.
Removed sample customers, payment amounts/status, merchant/count/distance/earnings
and unsupported pickup photo persistence. Pending actions block parent-card taps.
Pickup/Delivery tab activation fetches current assignments; Delivery has Refresh.
Detail selections preserve actual failure reasons; product quantity is not box count.

Verification: 127 full Rider tests, then 16 affected tests after the final earnings
activation/stale-money guard PASS; final analyzer clean; profile web build PASS.
ALONA 997 backend tests / 57,614 assertions, 129 frontend tests and 7 checkpoint helper
tests passed across isolated batches/affected reruns. Tracked checkout journey covers
checkout through Seller preparation, Logistics and two Rider legs; photo/Buyer OTP,
physical RTS/restock, store pickup, and separate COD collection/remittance evidence.
Final compiled browser 1 test / 18 assertions PASS: Buyer order through delivery and
receipt, owner web grouped payout/proof/reference, Rider receipt confirmation and
the same payment in Logistics mobile. Synthetic only, no real funds sent. Current
evidence: ../../../docs/testing/results/POST_IMPLEMENTATION_REVIEW.md and the initial
../../../docs/testing/results/RIDER_PAY_REVIEW.md.

Android debug build was attempted but local NDK 28.2.13676358 lacks source.properties.
No APK success is claimed. Device camera/scanner/GPS, iOS packaging, release identity/
signing and live operational acceptance remain pending. Upstream release identity/
signing placeholders require team configuration.

31 October addition: current checkout uses buyer-funded configured shipping;
historical Seller-funded earnings stay separate. My Earnings now uses the approved
per-Seller-shipment job policy and grouped manual payout APIs; original teal/amber
theme remains. Rates start disabled/blank and are supplied by the company owner.
The new additive payroll migration is source only, not applied to application data.
Follow-up fixes preserve the agreed pending-leg rate through confirmed whole-job
Rider handover, allow clearing optional transfer pay and handle gallery failures.
Final source/design audits preserve all earlier
edits/assets; the 100-file migration manifest includes the unchanged original 99 sources.
Teammate schema comparison,
pending migration rehearsal and real hub/route setup remain operator work. Follow
ALONA docs/LOGISTICS_TEAM_UPDATE.md. No automatic commits, pushes, resets or real DB
migrations. Next: repair Android toolchain, run device acceptance and rehearse against
the actual target schema. Older imported audit documents are historical.
