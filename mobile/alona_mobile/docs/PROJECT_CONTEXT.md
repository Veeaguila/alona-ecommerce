# Project context

Flutter Buyer/Logistics client imported into ALONA at `mobile/alona_mobile`.
Source/provenance: UPSTREAM.md. Rider remains `../rider`; Laravel API is `../..`.
One backend/database owns the process for both web and mobile Logistics. Mobile
uses cards, bottom navigation and confirmations suitable for a phone.

Current scope: Buyer checkout and Logistics operational alignment, preserving web
business rules and existing records. Buyer and web share canonical quote/booking,
address, cancellation and private delivery-OTP contracts. This source integration
does not certify a complete mobile release.
31 October payroll addition introduces a reviewed additive schema file, with no
application migration, live data write, deployment or commit performed. Owner
Rider payouts follows the user-approved per-Seller-shipment backend rules and
manual company-payroll batches. No numeric rates or provider transfers are assumed.

User design requirement: preserve the original Buyer and Logistics mobile look.
Use upstream branding, Figtree font, palette and established components/layouts.
New workflow controls must fit that design. Existing integration changes include
additional Logistics operations/stage filters and phone readability fixes; do not
describe the current UI as identical to upstream. The original Buyer checkout form
is active with quote validation and private delivery controls in the same style.
