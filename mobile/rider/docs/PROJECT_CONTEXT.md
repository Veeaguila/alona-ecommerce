# Project context

Flutter Rider client for the existing ALONA Laravel API and shared database. The
parent repository tracks mobile/rider; future clients can use other mobile folders.
Source is the user-selected Veeaguila Rider repository; see UPSTREAM.md.

Riders register/check approval, explicitly go online, accept pickup work, physically
collect and deliver parcels with photo/Buyer OTP, and record separate returns,
handovers and cash evidence. ALONA owns authorization, coverage, state, stock and
money. The client never connects directly to SQL or invents operational records.

Authorization: order-flow review, Rider import and compatibility fixes. Preserve
the teammate interface and existing records/rules. Never substitute Buyer destination
for Seller collection, fabricate payment/earnings, or change historical totals.
Explicit build/run API URLs; HTTPS for release. Tests use synthetic HTTP or SQLite
:memory:. No automatic commit/push/reset, deployment or real database operation.
Read the current integration bundle before resuming; source/test evidence overrides
historical imported notes. Current review: ALONA docs/testing/results/ORDER_FLOW_REVIEW.md.
