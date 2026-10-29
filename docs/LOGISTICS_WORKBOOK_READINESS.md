# Logistics workbook corrections — 29 October 2026

Latest review: testing/results/LOGISTICS_COMMIT_READINESS.md maps the original
44 module and 44 account cases to fresh evidence. Document retry and dev-server
backup exposure are fixed; the explicitly resumed 30-minute Logistics owner/Hub
Staff inactivity rule is implemented. Upgrade: LOGISTICS_TEAM_UPDATE.md.
No application database changes were applied.


The authorized workbook gaps have been corrected in source. The normal flow uses
the existing company account and two Rider legs; the optional physical hub network
remains disabled by default. The original Excel workbook is unchanged.

## Operating flow

1. Logistics sets the provinces it can actually serve and its rates in **Zones & Rates**.
   A positive base rate covers the first kilogram per Seller; additional started
   kilograms use the configured extra rate. Configure actual city areas and Rider
   coverage separately before accepting delivery work.
2. Buyer checkout receives server rate cards. Placement recomputes the fee and
   rejects a stale displayed quote before committing an order or stock change.
   Existing booked orders retain their fees. Standard shipping's configured free
   threshold and store pickup remain supported.
3. Seller prepares the order and requests pickup. Logistics accepts the request.
   It can assign manually or offer to the nearest eligible Online Rider with a
   fresh, permission-authorized location.
4. A nearest offer lasts **60 seconds**. Declining or expiry offers the shipment to
   the next eligible Rider, excluding Riders already tried in that round. After
   three Riders, or when no reliable eligible next location exists, the shipment
   returns unassigned. An offer never establishes physical custody.
5. Pickup Rider collects; Logistics receives at the sorting center, sorts into an
   owned city area and assigns a delivery Rider. Outbound/start precedes delivery.
6. Delivery requires the current photo proof and Buyer's six-digit OTP. Five wrong
   codes lock OTP verification and renewal for **15 minutes**. Renewal before the
   limit preserves the job's failed-code counter.
7. Failed-delivery choices are **Buyer not home**, **Wrong address**, **Refused**.
   Three failures, or the first Refused outcome, make the parcel RTS-eligible.
   Logistics initiation, physical return custody, Seller handover and Seller receipt
   remain separate actions. Seller receipt restocks once; prepaid returns enter the
   existing refund-review workflow.

## Corrected requirements and evidence

| Workbook reference | Correction | Automated evidence |
|---|---|---|
| LOG-RATE-001 | Logistics can edit owned rates; checkout uses the server tariff; stale quotes are rejected; old fees and audit history remain intact | `WorkbookRatesTest`, `workbook-readiness.test.mjs` |
| LOG-RATE-002 | Base rate rejects -1, 0, text and excess precision; accepts 0.01. Extra-kg rate may be zero | `WorkbookRatesTest`; Admin validator aligned |
| LOG-RATE-003/004 | Remote destinations remain blocked without active approved-company coverage. An explicit active company zone enables checkout at its configured rate | `WorkbookRatesTest`, `CheckoutCoverageTest` |
| A14 / LOG-DISP-001 | 60-second offers; next-nearest retry on decline/expiry; three-Rider limit; durable round/attempt audit; shared-shipment consistency | `NearestPickupOfferTest`, including scheduled expiry and decline scenarios |
| A13 / LOG-RTS-001 | Enumerated reasons; immediate refusal escalation; no early restock or invented physical return | `ReturnToSellerTest`, Rider workflow tests |
| A32 / LOG-HUB-003 | Job OTP lockout survives renewal; exact 899/900-second boundaries; private Buyer ownership and photo requirement retained | `BuyerOtpTest` |
| A25 | Manual operation/reassignment reasons limited to 10–300 characters | Existing operation/dispatch validation and UI bounds |

Full Logistics suite: **374 tests / 6,277 assertions PASS** in isolated SQLite
`:memory:`. Affected Buyer checkout/vouchers and Seller processing/pickup regressions:
**36 tests / 385 assertions PASS**. Frontend checks: **102 PASS**. Final production
build passed and was installed into local `public/build`, preserving the previous
assets separately and retaining old chunks. Rider's complete synthetic suite:
**115 PASS**. Built-UI Edge checks passed at **1440, 390 and 320 px**, with zero
JavaScript exceptions. The final evidence and asset installation receipt are
recorded in `.local/workbook-ready-20261029` and the synchronized Rider checkpoint.

These are automated regression results. They are not a claim that all 44 original
Excel cases were freshly executed with real accounts, devices and external services.
The workbook and its original execution-status cells have not been changed.

## Runtime and rollout limits

- The source includes `logistics:advance-pickup-offers`, scheduled every minute.
  The host's existing Laravel scheduler must run for unattended advancement; Rider
  queue reads/claims also advance offers. Timer execution may occur after the exact
  deadline, but an expired recipient cannot accept the expired offer.
- Nearest selection needs fresh Seller/Rider location signals and reliable distance
  separation. Stale/ambiguous signals return work unassigned rather than fabricate a
  nearest Rider. Manual assignment remains available.
- Bookings with hub routes resolve tariffs from the company booked for each seller.
  Checkout fee cards expose per-seller weighted/unweighted rates and placement uses
  the same server-derived company plans, including split seller orders. A matching
  company rate takes priority; only administrator/global rates and existing regional
  defaults may be fallbacks. Unrelated company rates cannot price a bound booking.
  Within that scope, resolution prefers an exact zone-name address component, then
  an exact province component, with ascending zone ID for overlapping matches.
  Legacy bookings without a bound hub company retain their existing tariff policy.
  Regional fallback tariffs remain centralized on the server. A tariff is a price declaration;
  it does not prove physical transport capability or add a cross-company handover.
- Active province coverage declares the company's ability to serve that province.
  It does not automatically create physical hubs, city areas or Riders. Disabling a
  rate does not cancel existing orders or delete separately configured city areas.
- No application database migrations, account creation, environment switch, commits
  or pushes were performed for this correction batch. Tests use only disposable
  in-memory databases and synthetic HTTP/device fixtures.
- In the subsequent single-operator setup, the hub migration was installed on the
  verified local restored database and Hubs & Routes management became available
  through the existing Logistics account. Required checkout routing remains off
  until actual hubs, transport and Rider coverage are configured. Shared rollout,
  cross-company cargo handover and reverse inter-hub RTS remain pending. Current
  setup and evidence: [Company hubs](LOGISTICS_HUB_NETWORK.md).
- Logistics owner/Hub Staff web/API sessions expire after 30 minutes without an authenticated request. Other-role timeouts are unchanged. Rider earnings allocations and external
  refund execution are unchanged. Native APK/device, live scheduler, email, payment
  and shared/production database acceptance remain unverified.
- Hub dispatch, supervised handover and incident actions share a UUID request-key
  helper that uses randomUUID when available and a getRandomValues-based UUIDv4 on
  ordinary HTTP/LAN origins. UUIDs remain request identifiers, never credentials.
  Synthetic built-UI checks verify dispatch and destination receiving on localhost
  and ordinary HTTP, at desktop and mobile widths. These checks do not establish
  MySQL contention behavior or native-device acceptance.

Independent recovery copies preserve the pre-correction source, Git index and
generated assets under `.local/workbook-ready-20261029`. Existing staged content,
environment and workbook hashes are checked separately before completion.
