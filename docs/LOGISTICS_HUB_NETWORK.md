# Company hubs and connected delivery routes

Updated 2026-10-29. The existing company account manages its hubs and can provision
Hub Staff accounts assigned to one owned hub. Seller waybills, tracking references,
Rider pickup/delivery and existing colors remain in use. The original hub-network
migration is installed locally; the new staff-membership migration is prepared
but unapplied. No real hubs or staff accounts were created by this implementation.

## Model and ownership

- Existing `sorting_centers` IDs remain the company/account authorization boundary.
  Company registration still happens once. Existing accounts, parcel ownership,
  financial records and city coverage are retained.
- A company creates its physical locations in **Hubs & Routes**
  (`/sorting-center/hubs`). `logistics_hubs` stores a code, name, address, canonical
  location city, explicit pickup/delivery city lists and active state.
- **Hubs & Routes** is available in the Logistics menu during setup as well as
  operation. Management access is independent of required checkout routing.
  **Manage hubs, Riders & routes** configures the company network; **Hub work
  queue** handles physical movements. The same authenticated company account can
  switch between its own operating hubs without another login or hub-user role.
- **Hub workspace** (`/sorting-center/hub-workspace`) is the simpler scan/type
  entry point. Company administrators choose an owned hub; Hub Staff use only
  their assigned hub. **Hub Staff** (`/sorting-center/hub-staff`) lets the company
  administrator create, pause or reassign staff. See the usage and rollout below.
- **All company hubs** is a view-only selection. Choosing an operating hub scopes
  the queue and enables only that location's allowed receiving/dispatch actions.
  The page shows pickup arrivals, ready-to-dispatch parcels, incoming transfers,
  parcels currently at the hub and an out-for-delivery filter. Origin receiving
  is also available directly in this workspace. Switching hubs clears prior
  physical confirmations; queues/counts reject foreign-company hub IDs.
- Riders retain their company and existing delivery-area memberships. A nullable
  `riders.logistics_hub_id` identifies their explicitly assigned operating hub.
  Assigning a hub does not manufacture Rider approval, presence or city coverage.
  Existing active work prevents silent Rider relocation.
- `logistics_routes` connects two hubs in the same company, in one direction, with
  an explicitly chosen road/sea/air/mixed transport mode. A reverse route is
  separate. Different companies are never connected by an inferred partnership.

## Booking and delivery

Checkout route validation is staged behind
`LOGISTICS_HUB_ROUTES_REQUIRED` (default **false**). Enable it only after separately
approved migration/setup and verified actual routes. The portal states whether
booking validation is active. Enabling it without completed storage/setup blocks
new delivery bookings; it does not silently substitute city coverage for a route.

When enabled, each seller in a new delivery checkout must have an unambiguously
resolved collection city and recipient city, an active company with eligible
Riders/area coverage at both hubs, and a directed active route between those hubs.
A same-hub local delivery does not need cargo transport. Store pickup bypasses
hub delivery routing. Selection is deterministic: the first eligible source and
destination in ascending hub-ID order. There is no Buyer courier-selection UI or
rate comparison in this version; the selected company is shown at checkout.

Route validation occurs inside checkout before order/stock/voucher mutations.
Server-derived `order_items.hub_route_plan` snapshots the responsible company,
hub IDs/names, source/destination city codes and transport mode. Both ordinary and
per-seller split order creation retain it. A booked route cannot be rewritten by
normal model updates. A hub location used by an active booking cannot be moved.

Seller ready-for-pickup creates the existing item parcel/request, binds the booked
company and creates a `parcel_hub_journeys` record. A pickup-address city differing
from the booking is rejected instead of silently rerouted. Original orders without
a route snapshot continue using their original workflow; no historical physical
locations or journeys are guessed.

1. An approved Rider at the origin hub claims/acknowledges and physically collects
   the parcel using the existing Rider workflow.
2. Inbound receiving requires explicit physical confirmation at the booked origin
   hub. Local delivery becomes ready at this point.
3. For a nonlocal journey, company operations dispatches the received shipment
   from the origin hub with a real transport/manifest reference and physical
   confirmation. Existing seller-shipment members move atomically when applicable.
   The parcel becomes `IN_HUB_TRANSIT`; no current-hub custody is asserted in transit.
4. Each destination parcel is explicitly received at its booked destination hub.
   Only then does it return to `AT_SORTING_CENTER` and become eligible for local
   delivery assignment. Duplicate dispatch/receipt does not create another event.
5. Existing delivery-area, eligibility, workload, photo and Buyer OTP checks still
   apply. Pickup and delivery Riders must belong to their respective booked hubs.

Immutable Logistics audit events retain the operator/time/reference. Item history
records origin receipt, dispatch and destination receipt; Buyer order details show
these per item. Dashboard in-transit totals, monitoring, pickup history, incident
reporting and owned queue filters recognize hub transport.

## Local management and shared rollout

The first installation of `2026_10_29_100000_create_logistics_hub_network` completed
only on local `alona_restored_20261028`: 80 existing tables became 84, all original
business table row hashes remained unchanged, and all four new hub tables were
empty. No existing migration was repeated. Actual company locations and routes
must be entered by the operator; none were inferred from coverage or business names.

Open **Hubs & Routes**, use **Manage hubs, Riders & routes** to enter actual hubs,
assign local Riders and configure their city areas, then add supported directed
routes. In **Hub work queue**, select the actual operating hub before receiving or
dispatching. Audit events record the same company operator at origin and destination.
Legacy parcels keep their existing workflow and are not silently given hub journeys.

Required checkout route validation remains off until the physical network is ready.
This does not disable hub management. For separately authorized shared rollout:

1. Review `2026_10_29_100000_create_logistics_hub_network.php` and its schema impact;
   inventory the target schema and approve the actual rollout separately. Existing
   production migration ordering/engine concerns are not resolved by SQLite tests.
2. Apply the reviewed migration only on the approved target after its backup plan.
   The local installation above does not establish shared-database deployment.
3. Company operators enter actual hubs and limited coverage; assign approved local
   Riders and explicit city areas; create only routes supported by real transport.
4. Verify a representative full route and its ownership/turnover checks in the
   approved test environment. Enable booking validation through deployment config
   only after this acceptance. No environment file is changed automatically.

## Limits retained explicitly

- Company administrators retain all-company operations. Hub Staff can read and
  receive/dispatch relevant parcels at one assigned hub; existing company-wide
  operational and administration endpoints deny staff access.
- This version supports same-hub delivery or one directed hub-to-hub leg (whose
  actual transport may be road/sea/air/mixed). It does not create a carrier booking,
  transport vehicle, shipping contract, intermediate-hop planner or live GPS feed.
- Cross-company partnerships, liability/cost-sharing and cross-company transfers
  remain unimplemented. Foreign-company hubs, Riders and journeys are denied.
- An inter-hub return needs a reverse transport workflow. The existing local RTS
  initiation is explicitly blocked for these parcels so it cannot falsely confirm
  an inter-island Seller return. Local/legacy RTS remains unchanged.
- Shipping calculations, historical seller-funded fee/earnings, company-level COD
  collection/remittance and existing payout authority are preserved. This change
  does not invent per-hub fees, Rider allocations or inter-company settlement.
- Physical hubs and staff access are managed through the web portal. The Logistics
  app provides the same scoped Hub workspace with its existing camera scanner.
  Existing Rider pickup/delivery APIs are reused.
- Automated tests use SQLite `:memory:` and synthetic HTTP/browser fixtures.
  They do not establish physical-device, actual cargo, SMTP or MySQL concurrency
  acceptance. Verification evidence is retained under the ignored local checkpoint
  directory and the Rider checkpoint helper logs.

## Hub Staff usage and rollout

1. The company administrator configures actual hubs, Rider hub assignments and
   directed routes in Hubs & Routes. One staff account represents one person;
   a hub does not need another company registration.
2. In Hub Staff, the administrator enters a name, unique email, active owned hub
   and strong initial password with confirmation. Staff use existing Logistics
   sign-in. There is no public staff registration or automatic account creation.
3. Hub Staff land in Hub workspace. On the web, type the printed tracking code
   or use a USB barcode scanner. In the Logistics app, use Scan or type it.
   A scanned value is a reference, never a URL. Lookup does not move the parcel.
4. The server returns saved collection/delivery addresses, current location,
   next destination and the permitted action. Physical confirmation records origin
   receipt, dispatch or destination receipt. Dispatch also requires an actual
   transport/manifest reference. Staff cannot override the booked route. After
   destination receipt, the administrator uses existing sorting/Rider assignment.
5. Pausing or reassigning staff revokes their API tokens. Requests check current
   membership, company eligibility and active hub; writes recheck membership while
   locked. Stale hub IDs are denied. Uncertain writes are never replayed
   automatically; Refresh reads the recorded state before another action.

The additive `logistics_hub_staff` membership links an existing Logistics-type
`users` row to its company and hub. It grants no company ownership or fabricated
verified email. Existing own-password controls can change the initial password.

Authenticated APIs: `/api/sorting-center/hub-workspace` with GET `/`, `/queue`,
`/lookup` and POST `/actions`. Owner-only provisioning: GET/POST
`/api/sorting-center/hub-staff` and PUT `/{id}`. Passwords are never returned.
Existing company-wide operational and administration endpoints deny Hub Staff.

First-time staff setup requires only the prepared
`2026_10_29_150000_create_logistics_hub_staff_table.php` migration, after target
database, backup and hub schema verification under explicit rollout authorization.
It remains unapplied to application databases. Do not repeat the installed hub
migration or apply unrelated pending migrations. Missing staff storage shows an
unavailable setup state. No real hubs, staff or transport records were created.

Destinations use immutable booking snapshots. Routing remains optional by existing
configuration; legacy parcels without booked journeys are not assigned invented
hubs. Intermediate hops and cross-company transfers remain unimplemented.

Evidence in `.local/hub-operator-20261029`: 480 backend tests / 7,033 assertions
using SQLite memory, 118 frontend tests, 381 Flutter tests and seven built-web
walkthrough checks at 1440/390/320 px with zero JavaScript exceptions. Existing
logistics CSS, Seller waybill and Rider pickup/delivery controllers are preserved.
Synthetic checks do not establish native camera, cargo, SMTP, deployed schema or
MySQL contention acceptance.

## Initial source verification (before local installation)

- Full Logistics regression: **364 passed / 5,941 assertions**.
- Final focused hub, checkout, receiving, assignment and rescheduling checks:
  **47 passed / 1,145 assertions** (overlaps the full suite).
- Frontend: **95 passed**, including missing setup/session, stale responses,
  duplicate clicks, explicit physical receipt, wrong-hub denials and uncertain
  request recovery.
- Existing Seller processing/waybill/pickup checks: **13 passed / 276 assertions**.
  One legacy fixture now reuses `electronics`, which existing category migrations
  already seed; its original duplicate-slug setup errors are retained in local logs.
- PHP syntax and an isolated production build passed. Existing public assets were
  not replaced by the build.
- Actual built portal tested in isolated headless Edge using synthetic HTTP:
  two hub creations, explicit Rider assignments, directed route, physical dispatch
  and receipt; 1440/390/320 widths, no horizontal overflow or JavaScript exceptions.
- Existing source was independently backed up. Original paths, staged content and
  environment were checked for preservation. No application database migration,
  real operational mutation, commit or push was performed during that initial batch.

## Single-operator management verification

- Affected backend regressions: **57 PASS / 1,484 assertions**, isolated SQLite
  `:memory:` only. Tests cover the same company operator using both hubs,
  queue/count transitions, foreign-hub denial, missing schema and existing flows.
- Frontend: **106 PASS**, including operating-hub selection, scoped queue requests,
  cleared physical confirmations and guarded origin receipt.
- Production build and actual built-UI Edge checks passed at 1440/390/320 px with
  no horizontal overflow or JavaScript exceptions. Eight synthetic mutations cover
  two hubs, Rider assignments, a directed route, origin receiving, dispatch and
  destination receiving through the same login. These fixtures do not touch Laravel
  or application database records.
- Verified generated assets are installed in local `public/build`; the previous
  assets and SQL/source backups are retained in
  `.local/single-operator-hubs-20261029`. Existing staging and environment remain
  preserved. Native/real cargo, shared deployment and MySQL contention acceptance
  remain unverified.
