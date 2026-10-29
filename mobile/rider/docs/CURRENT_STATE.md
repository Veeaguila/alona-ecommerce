# Current state

Verified 29 October 2026: active client is ALONA's mobile/rider, backend ../.. from
the app. No separate mobile database or nested Git repository. UPSTREAM.md records
the 223-file teammate import. Old sibling source is preserved, not a build dependency.

The teammate interface now uses real pickup/decline requests, canonical delivery
failure outcomes, consistent retry/RTS restrictions and validated physical handovers.
Removed sample customers, payment amounts/status, merchant/count/distance/earnings
and unsupported pickup photo persistence. Pending actions block parent-card taps.
Pickup/Delivery tab activation fetches current assignments; Delivery has Refresh.
Detail selections preserve actual failure reasons; product quantity is not box count.

Verification: 123 Rider tests PASS; final analyzer clean; profile web build PASS.
ALONA 948 backend tests / 56,958 assertions, 123 frontend tests and 7 checkpoint helper
tests passed across isolated batches/affected reruns. Tracked checkout journey covers
checkout through Seller preparation, Logistics and two Rider legs; photo/Buyer OTP,
physical RTS/restock, store pickup, and separate COD collection/remittance evidence.
Browser acceptance and current evidence: ../../../docs/testing/results/ORDER_FLOW_REVIEW.md.

Android debug build was attempted but local NDK 28.2.13676358 lacks source.properties.
No APK success is claimed. Device camera/scanner/GPS, iOS packaging, release identity/
signing and live operational acceptance remain pending. Upstream release identity/
signing placeholders require team configuration.

Current checkout uses buyer-funded configured shipping; historical seller-funded
earnings stay separate. Do not infer compensation/cash allocation/refund execution.
This mobile import adds no application migration. Teammate schema comparison,
pending migration rehearsal and real hub/route setup remain operator work. Follow
ALONA docs/LOGISTICS_TEAM_UPDATE.md. No automatic commits, pushes, resets or real DB
migrations. Next: repair Android toolchain, run device acceptance and rehearse against
the actual target schema. Older imported audit documents are historical.
