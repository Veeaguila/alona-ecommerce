# ALONA Logistics black-box test analysis

Date: 2026-10-17 (Asia/Manila). Source snapshot: `463f88443b13eb471db22ac31df4b8be50b465a4` on `feature/sorting-center`.
Workbook: [Blackbox_Test_Cases_Logistics_Modules-1.xlsx](../Blackbox_Test_Cases_Logistics_Modules-1.xlsx).
Workbook SHA-256: `0615000a6d58f8b3e5b6690fffb08e498f6095ecd51158715486ee286570c84f`.

## Result and interpretation

All **11 worksheets and 44 original Logistics case IDs** were analyzed. Static implementation assessment: **5 implemented, 17 partial, 22 missing, 0 unclassified/unverified**. An implemented classification means a matching source path was found, not a test Pass. Runtime behavior for **all 44 cases remains unverified**.

**This workbook run: Pass 0 / Fail 0 / Blocked 0 / Not Run 44.** Original Actual Result and Remarks fields are blank; every original Status is Not Run. No application/API/browser tests, PHP bootstrap, database connections or migrations were executed for this analysis. Existing test definitions were inspected, not rerun. Earlier registration workbook/test results concern different IDs and cannot pass these cases.

Workbook instructions to seed/reset data or amend rules are test-document content, not authorization to perform them. No fixtures were created, no source workbook cells were changed, and no application code, existing documents, .env, database or Git history was modified. New reports and ignored analysis scratch files only. Existing Rider checkpoint/source hashes were verified read-only; existing synchronized checkpoint/memory files remain unchanged under the latest no-overwrite instruction.

## Worksheet inventory

| Worksheet | Contents read | Analysis consequence |
|---|---|---|
| Summary | 38 nonempty rows: run metadata, module totals, strict pass rules and testing techniques | Run metadata is blank; a whole case requires every expected result and side effect. Totals are not executed evidence. |
| Assumptions | All 37 assumptions A1-A37 and blank actual-rule column | Assumptions are proposed rules, not approved ALONA requirements; conflicts are listed below. |
| Test Data | All 75 nonempty rows: 11 account fixtures, 4 addresses, 7 products, 8 vouchers, 4 shipping lanes, worked example and boundary/security inputs | Fictional fixture specifications only; no account, stock, rates, balances or coverage were queried/seeded. No fixture passwords are reproduced here. |
| L1 Dashboard | 4 cases: LOG-DASH-001 to LOG-DASH-004 | Every row mapped below; every case Not Run. |
| L2 Rider Mgmt | 7 cases: LOG-RIDER-001 to LOG-RIDER-007 | Every row mapped below; every case Not Run. |
| L3 Dispatch | 8 cases: LOG-DISP-001 to LOG-DISP-008 | Every row mapped below; every case Not Run. |
| L4 Hub Ops | 7 cases: LOG-HUB-001 to LOG-HUB-007 | Every row mapped below; every case Not Run. |
| L5 Tracking &amp; Exceptions | 5 cases: LOG-TRK-001 to LOG-TRK-005 | Every row mapped below; every case Not Run. |
| L6 Returns (RTS) | 4 cases: LOG-RTS-001 to LOG-RTS-004 | Every row mapped below; every case Not Run. |
| L7 Zones &amp; Rates | 4 cases: LOG-RATE-001 to LOG-RATE-004 | Every row mapped below; every case Not Run. |
| L8 COD &amp; Reports | 5 cases: LOG-COD-001 to LOG-COD-005 | Every row mapped below; every case Not Run. |

The Test Data worked checkout assumes PHP185 shipping, a PHP50 shipping voucher and PHP1083 payable. Those numbers cannot be reused unchanged with current zero buyer shipping. ADDR-4 is a deliberately uncovered Batanes address; P-02 has baseline stock 20 and the RTS case requires a controlled 19→20 receipt transition. COD fixtures require PHP1800 collected and PHP1800/PHP1750 remitted. These are specifications, not existing database facts.

## Classification by module

| Module | Cases | Implemented (source) | Partial | Missing | Unverified classification | Not Run |
|---|---:|---:|---:|---:|---:|---:|
| L1 Dashboard | 4 | 1 | 2 | 1 | 0 | 4 |
| L2 Rider Mgmt | 7 | 1 | 5 | 1 | 0 | 7 |
| L3 Dispatch | 8 | 1 | 3 | 4 | 0 | 8 |
| L4 Hub Ops | 7 | 2 | 5 | 0 | 0 | 7 |
| L5 Tracking &amp; Exceptions | 5 | 0 | 1 | 4 | 0 | 5 |
| L6 Returns (RTS) | 4 | 0 | 0 | 4 | 0 | 4 |
| L7 Zones &amp; Rates | 4 | 0 | 1 | 3 | 0 | 4 |
| L8 COD &amp; Reports | 5 | 0 | 0 | 5 | 0 | 5 |
| **Total** | **44** | **5** | **17** | **22** | **0** | **44** |

Classification definitions: **Implemented** = corresponding behavior/guards visible in source, still needing runtime verification; **Partial** = a related path exists but one or more expected results are absent/different; **Missing** = no usable path for the required operation in the inspected module; **Unverified** = insufficient source evidence to classify at all (none were left unclassified). Do not convert these labels to test outcomes.

## Observed architecture and data flow

| Boundary | Source-backed flow | Important limitation |
|---|---|---|
| Web portal | Session-authenticated approved sorting-center routes render eight Blade shells; logistics.js mounts the matching Vue page and supplies a session-created Sanctum token through localStorage. [WEB](#evidence-web), [ENTRY](#evidence-entry), [NAV](#evidence-nav), [CENTERGUARD](#evidence-centerguard) | No dedicated Rider Management, RTS, Rates, COD or Audit page is registered in this portal. Inspecting a page declaration does not verify browser behavior. |
| API boundary | routes/api.php uses auth:sanctum + sorting_center.approved for company operations and rider.approved for rider workflows. [ROUTES](#evidence-routes), [RIDERGUARD](#evidence-riderguard) | Approval/active state is not live Online presence. Actual session/token timeout and deployed route cache are unverified. |
| Pickup → hub | Seller ready_for_pickup item creates/reuses ITEM-<item id> parcel and pickup request; confirmed pickup is self-accepted by an eligible rider, then marked PICKED_UP; incoming lookup/arrival moves it to AT_SORTING_CENTER. [PICKUPCREATE](#evidence-pickupcreate), [PICKUP](#evidence-pickup), [ARRIVE](#evidence-arrive), [INBOUNDUI](#evidence-inboundui) | No nearest offer or three-job limit. Arrival does not write a durable hub event/received_at or At Hub Buyer milestone. |
| Hub → last mile | Destination-matching owned city area is selected; eligible owned rider is manually assigned; rider starts OUT_FOR_DELIVERY. [SORT](#evidence-sort), [ELIG](#evidence-elig), [ASSIGN](#evidence-assign), [ASSIGNUI](#evidence-assignui), [DELIVER](#evidence-deliver) | Assignment does not change status to Assigned; pickup and last-mile rider identities are separate. No general reassignment/bulk endpoint. |
| Completion/failure | Assigned rider stores private image proof; valid photo is required for DELIVERED. Parcel model synchronizes selected item statuses; Seller notice and fee recognition run transactionally. Failure immediately writes FAILED_RETURNED. [PROOF](#evidence-proof), [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel), [FINANCE](#evidence-finance) | No delivery OTP/attempt ledger/RTS or Lost/Damaged pipeline. Registration OTP must not be confused with delivery OTP. |
| Analytics/finance | Scoped sorted_at-based JSON reports and charts; separate seller charge/Rider earning records with admin manual full settlement. [REPORT](#evidence-report), [REPORTUI](#evidence-reportui), [FINANCE](#evidence-finance), [SETTLE](#evidence-settle), [ADMIN](#evidence-admin) | Not CSV/XLSX, COD collection/remittance or editable zone/weight rates. |

### Declared schema and model relationships

This is a **migration/model inventory**, not a read of the populated MySQL schema. User reports alona_development and applied migrations; actual deployment/schema/data remain unverified. Historical architecture memory saying the populated database is unidentified is stale context, not current proof.

- users → one sorting_center or rider; sorting_centers → many areas/riders/parcels. rider_areas is the many-to-many membership used for eligibility. Core foreign keys and owned indexes: [SCHEMA](#evidence-schema), [RIDERMODEL](#evidence-ridermodel).
- areas has city_code and uniqueness per center/city as well as per center/name; city coverage is not a regional weight/rate table: [COVERAGESCHEMA](#evidence-coverageschema), [COVERAGE](#evidence-coverage).
- parcels.order_item_id is nullable/unique and refers to an order item; parcels.order_id is a unique tracking-reference string (new values ITEM-<id>), not a parent-order foreign key. pickup_rider_id and assigned_rider_id represent separate legs: [SCHEMA](#evidence-schema), [PICKUPCREATE](#evidence-pickupcreate), [PARCEL](#evidence-parcel).
- parcel → many delivery_proofs, each linked to a rider, file/type and captured_at; order_item_status_histories stores status/note/timestamps, not actor/old-new/reason hub/audit events: [PROOFSCHEMA](#evidence-proofschema), [HISTORY](#evidence-history).
- seller_shipments is unique per order/seller and owns item membership; seller_delivery_charges is unique per shipment; rider_delivery_earnings is unique per charge. These are delivery-fee ledgers, not COD remittances: [FINANCESCHEMA](#evidence-financeschema), [FINANCE](#evidence-finance), [SETTLE](#evidence-settle).
- Customer return requests/refund handling already exist, but are initiated/reviewed separately and do not implement a failed-delivery reverse-shipment/RTS chain: [RETURNS](#evidence-returns).
- No matching Logistics rate/offer/presence/attempt/COD-remittance/audit structures or actions were found in the inspected migrations/routes/models/controllers. Arbitrary status strings in a table do not implement missing transitions or side effects.

## Assumptions reviewed

The actual-rule column is blank for all assumptions. A2-A8 are explicitly labeled by the workbook as rules to agree before testing. This analysis does not approve replacing current ALONA rules.

| Ref | Workbook topic | ALONA assessment / testing requirement |
|---|---|---|
| A1 | Test accounts | Fixtures are specified, not verified/seeded. Workbook role labels must map to approved users/centers/riders; Suspended is not a separate implemented rider approval state. |
| A2 | Voucher stacking (rule 1) | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A3 | Stock deduction (rule 2) | RTS receipt-triggered exactly-once stock restoration is absent. Existing customer-return/cancellation stock paths are not proof of RTS. |
| A4 | Sale + voucher (rule 3) | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A5 | Flash sales (rule 4) | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A6 | Cancellation window and auto-cancel (rule 5) | Cross-role cancellation cutoffs are dependencies; the full Buyer/Seller cancellation policy was not black-box tested in this analysis. |
| A7 | Variation limits and category depth (rule 6) | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A8 | Voucher funding (rule 7) | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A9 | Platform commission and rounding | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A10 | Order status flow | Current pickup leg is PLACED/PREPARING → PICKED_UP → AT_SORTING_CENTER; delivery assignment retains hub status, then OUT_FOR_DELIVERY → DELIVERED or terminal FAILED_RETURNED. Agree state/label mapping first. |
| A11 | Order completion and return window | Order completion/return windows are broader Buyer/Seller dependencies, not verified by the Logistics module scan. |
| A12 | Reviews | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A13 | Failed delivery | Conflicts with current first-failure terminal FAILED_RETURNED and single reason; attempt ledger/refusal threshold not implemented. |
| A14 | Rider job rules | Online presence, nearest offers, 60-second expiry, three declines and three-job capacity are absent. |
| A15 | Seller earnings and payout | Seller payouts/customer refunds are separate existing flows; no prepaid RTS integration establishes this expected result. |
| A16 | Image upload rule | Delivery proof rule is image/max:20480 (20 MiB), not explicit JPG/PNG up to 5 MiB. Effective web upload limits are unverified. |
| A17 | Price and quantity rule | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A18 | Store vacation and verification | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A19 | Deleting categories and products | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A20 | Shipping fee | Major policy conflict: buyer shipping returns zero; seller-funded delivered package fee PHP50, Logistics PHP10/Rider PHP40. No weight-zone rate management. |
| A21 | Voucher return on cancellation | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A22 | Session and access | Ownership/approval boundaries are source-backed; every role/record attack and the assumed 30-minute idle expiry remain runtime-unverified. Web portal tokens explicitly expire after two hours. |
| A23 | Notifications | Rider notifications and selected Buyer/Seller notices exist, not every specified event for every role; At Hub/Assigned/RTS/Lost/Damaged event chains are incomplete. |
| A24 | COD remittance | COD remittance/reconciliation absent. Admin seller-delivery-fee collection and rider-pay settlement are different financial events. |
| A25 | Text field limits | Rider rejection/deactivation do not validate/store a reason; delivery failure only requires a string. The assumed 10-300 reason bounds are not established. |
| A26 | Voucher code rule | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A27 | Editing vouchers after claim | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A28 | Store sales | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A29 | Low-stock alert | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A30 | Address book | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A31 | Return and refund requests | Broader Buyer/Seller/catalog/marketing rule. Read for fixture/side-effect dependencies; not established by this scoped Logistics source analysis and not tested. Do not assume compliance or change the application from this spreadsheet alone. |
| A32 | Proof of delivery | No buyer-visible delivery OTP, completion OTP validation or five-attempt/15-minute lock. Registration email OTP is unrelated. |
| A33 | Rider pay | Current delivered seller-package allocation is PHP40 to the rider, not PHP50 per delivery plus PHP30 per pickup. Requires product/finance agreement. |
| A34 | Dispatch and reassignment | Pickup self-acceptance and later manual last-mile assignment exist. Nearest offers, presence, pre-pickup reassignment and suspension job release are missing. |
| A35 | Audit log | No matching centralized Logistics audit model/UI with actor/old-new/reason for these actions. Existing item histories and financial recorded_by fields are partial foundations. |
| A36 | Report export | JSON analytics/charts exist; CSV and XLSX export controls/endpoints are absent. |
| A37 | Duplicate submissions | Some transactions, locks and duplicate guards exist, including proof reuse. Full create/confirm/RTS/COD idempotency is not implemented or runtime-verified for this workbook. |

## Original case mapping

Original scenario and Expected Result below are copied from the workbook. Worksheet name/Excel row identify the original preconditions, steps, data and assumption references; those original steps remain required, supplemented by the testing requirements below. Evidence links identify implementations or inspected boundaries where a required path is absent. Every row has execution status **Not Run**.

### L1 Dashboard

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-DASH-001** — Counts match the records<br>Excel row 2; High; assumptions none | Each dashboard count equals the filtered list exactly. | **Partial**<br>Not Run | [DASH](#evidence-dash), [DASHUI](#evidence-dashui), [TRACK](#evidence-track)<br>Six API counters/four UI cards describe sorting/pickup/dispatch queues, not the workbook Pending/In Transit/Delivered/Failed set. | Agree status mapping; compare every card with all pages of its exact filtered queue, including zero records. |
| **LOG-DASH-002** — Counts update after a status change<br>Excel row 3; Medium; assumptions none | In Transit −1 and Delivered +1; other counts unchanged. | **Partial**<br>Not Run | [DASH](#evidence-dash), [DASHUI](#evidence-dashui), [DELIVER](#evidence-deliver)<br>Refresh reloads counts and delivery changes OUT_FOR_DELIVERY, but no Delivered dashboard card exists. | With an isolated assigned parcel and valid photo, complete delivery, refresh and compare all expected counters and unrelated company totals. |
| **LOG-DASH-003** — Date filter<br>Excel row 4; Medium; assumptions none | Shipments on the start and end dates are counted. | **Missing**<br>Not Run | [DASH](#evidence-dash), [DASHUI](#evidence-dashui), [REPORT](#evidence-report)<br>Dashboard accepts no date range. Inclusive sorted_at dates exist on a separate report, not dashboard shipment-date filtering. | Agree creation/sorting/delivery timestamp and Asia/Manila boundaries; exercise exact start/end instants and an empty range. |
| **LOG-DASH-004** — Only own company's data<br>Excel row 5; High; assumptions A22 | No Logistics 2 shipment is counted or listed. | **Implemented**<br>Not Run | [DASH](#evidence-dash), [PARCEL](#evidence-parcel), [TDASH](#evidence-tdash)<br>Parcel counters use the authenticated center workflow. Pending unclaimed pickup requests are intentionally a shared pool, not another center's owned shipments. | Use two companies with overlapping statuses; compare scoped parcel counts/lists, and separately verify the agreed shared-pickup policy. |

### L2 Rider Mgmt

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-RIDER-001** — Approve a rider<br>Excel row 2; High; assumptions A14, A35 | Status Active; the rider can go Online. The approval is in the audit log. | **Partial**<br>Not Run | [RIDER](#evidence-rider), [DOCS](#evidence-docs), [NAV](#evidence-nav), [NOTIFY](#evidence-notify)<br>Owned approval and private-document authorization exist. No Logistics rider-management/document-review screen, Online presence, or required audit log is wired. | Open both owned rider documents, approve, verify resulting eligibility/notification and attempt Online; verify actor/time/old-new audit entry once developed. |
| **LOG-RIDER-002** — Reject a rider with a reason<br>Excel row 3; Medium; assumptions A25 | The empty reason is blocked. After rejection the rider is notified with the exact reason and cannot go Online. | **Partial**<br>Not Run | [REJECT](#evidence-reject), [NOTIFY](#evidence-notify)<br>Rejection changes approval/user status but neither validates nor stores the supplied reason. Notification body is generic, not the exact reason. | Try blank, whitespace, 9/10/300/301 characters; check exact persisted/notified reason and denied operational access. |
| **LOG-RIDER-003** — Suspend a rider with active jobs<br>Excel row 4; High; assumptions A34, A35 | Rider 2 goes Offline and cannot go Online. The Assigned job moves to the Unassigned queue; the Picked Up job stays with Rider 2. The suspension is logged. | **Partial**<br>Not Run | [DEACTIVATE](#evidence-deactivate), [RIDERGUARD](#evidence-riderguard), [PICKUP](#evidence-pickup), [ASSIGN](#evidence-assign)<br>Deactivation only flips is_active. It does not release unpicked jobs or record a suspension reason/audit; middleware also blocks delivery actions for deactivated riders. | Create one accepted pickup and one picked-up parcel; deactivate and inspect ownership, queues, ongoing-work policy, notification and audit, including concurrent assignment. |
| **LOG-RIDER-004** — Suspend without a reason<br>Excel row 5; Medium; assumptions A25 | Blocked; rider unchanged. | **Missing**<br>Not Run | [DEACTIVATE](#evidence-deactivate)<br>No suspension-reason validation/persistence exists; an empty body can deactivate an owned rider. | Assert blank/whitespace/9-character reasons reject without any status/job changes; test 10 and 300 boundaries after policy approval. |
| **LOG-RIDER-005** — Reactivate a rider<br>Excel row 6; Medium; assumptions A35 | Rider 3 is Active and can go Online. | **Partial**<br>Not Run | [ACTIVATE](#evidence-activate), [NAV](#evidence-nav)<br>Activate restores is_active only for approved riders, but no Online presence or reactivation UI/audit exists. Deactivated is not a separate Suspended approval state. | Map Suspended to an agreed state; reactivate through UI/API, verify notifications/audit and Online eligibility without reviving rejected accounts. |
| **LOG-RIDER-006** — Rider status and workload<br>Excel row 7; Medium; assumptions none | Each rider shows Online/Offline, active jobs (0–3) and today's completed deliveries, matching their apps. | **Partial**<br>Not Run | [ELIG](#evidence-elig), [RIDER](#evidence-rider), [RIDERMODEL](#evidence-ridermodel)<br>Eligible-rider responses count assigned deliveries; there is no complete rider list UI, Online/Offline state, three-job cap or today-completed metric. | Compare list against rider app including pickup jobs, 0/1/3/4 workload, local-midnight completions and Offline/Suspended states. |
| **LOG-RIDER-007** — Cannot manage another company's rider<br>Excel row 8; High; assumptions A22 | Both are denied. Rider 1 is unchanged. | **Implemented**<br>Not Run | [RIDER](#evidence-rider), [REJECT](#evidence-reject), [DEACTIVATE](#evidence-deactivate), [TRIDERS](#evidence-triders), [DOCS](#evidence-docs)<br>List and mutation queries scope the rider to the authenticated center; document authorization checks ownership. | Use a second company to list, request documents and directly approve/reject/activate/deactivate the first company's rider; assert denial/no disclosure/no writes. |

### L3 Dispatch

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-DISP-001** — Auto-assign to the nearest eligible rider<br>Excel row 2; High; assumptions A34 | The offer goes to Rider 1 first. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [ELIG](#evidence-elig), [PICKUP](#evidence-pickup)<br>No nearest-rider ranking, offer/expiry/decline lifecycle or auto-assign endpoint/UI exists. Eligible last-mile riders are ordered by name; pickups are self-accepted. | After agreeing A14/A34, test distance ordering, ties, same-company presence, 60-second expiry, three declines and manual-queue fallback. |
| **LOG-DISP-002** — Manual assign<br>Excel row 3; High; assumptions A10 | Rider 2 receives it; status Assigned; the buyer timeline shows the assignment. | **Partial**<br>Not Run | [ASSIGN](#evidence-assign), [ASSIGNUI](#evidence-assignui), [PARCEL](#evidence-parcel), [NOTIFY](#evidence-notify)<br>Manual delivery assignment exists only after arrival and city sorting. It leaves AT_SORTING_CENTER and does not create the workbook Assigned buyer-timeline event. | Agree pickup versus last-mile assignment; confirm rider ownership/notice, displayed Assigned status and buyer timeline with no premature delivery-start timestamp. |
| **LOG-DISP-003** — Offline or suspended rider not assignable<br>Excel row 4; High; assumptions A14 | Neither rider is listed. The direct request is rejected. | **Partial**<br>Not Run | [ELIG](#evidence-elig), [RIDERMODEL](#evidence-ridermodel), [TASSIGN](#evidence-tassign)<br>Unapproved/inactive/account-ineligible riders are filtered and rejected. Availability Online/Offline is not tracked or enforced. | Distinguish approved-but-Offline from deactivated; test list/direct request for each state, area/company mismatch and eligibility races. |
| **LOG-DISP-004** — Rider at the job limit<br>Excel row 5; Medium; assumptions A14 | Blocked with a limit message. | **Missing**<br>Not Run | [ASSIGN](#evidence-assign), [ELIG](#evidence-elig), [PICKUP](#evidence-pickup)<br>No three-active-job limit in assignment or pickup acceptance; displayed workload is not a capacity guard. | Define a job across parcels/packages/pickups; test third/fourth jobs and simultaneous requests under the approved database engine. |
| **LOG-DISP-005** — Reassign before pickup<br>Excel row 6; High; assumptions A34 | Rider 1 loses the job immediately; Rider 2 has it. The timeline shows both events. | **Missing**<br>Not Run | [ASSIGN](#evidence-assign), [ROUTES](#evidence-routes)<br>The current assign action rejects any already assigned parcel. There is no pre-pickup reassignment action or old/new-rider timeline. | After agreeing dispatch legs, reassign an unpicked job atomically; verify removal/addition, both notifications and buyer/audit events, including stale clients. |
| **LOG-DISP-006** — No reassign after pickup<br>Excel row 7; High; assumptions A34 | Not available; the request is rejected. | **Partial**<br>Not Run | [ASSIGN](#evidence-assign), [PICKUP](#evidence-pickup), [DELIVER](#evidence-deliver)<br>Assigned/non-hub parcels are guarded, but no reassignment workflow exists. Pickup and last-mile rider IDs are separate; a new last-mile assignment after hub arrival is allowed by design. | Agree whether this prohibition applies within one leg or across pickup/delivery legs; test picked-up/out-for-delivery direct requests and ownership preservation. |
| **LOG-DISP-007** — Bulk assignment over the limit<br>Excel row 8; Medium; assumptions A14 | 3 are assigned and 2 are reported as skipped because of the 3-job limit. The 2 stay Unassigned. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [ASSIGN](#evidence-assign), [ASSIGNUI](#evidence-assignui)<br>UI/API assign one parcel at a time; no bulk action, three-job cap or assigned/skipped summary exists. | After capacity semantics are agreed, submit five jobs to a zero-job rider; require exactly three assignments/two unchanged skips and safe duplicate/concurrent requests. |
| **LOG-DISP-008** — Assign a parcel that is not ready<br>Excel row 9; Medium; assumptions A10 | It is not in the queue and the request is rejected. | **Implemented**<br>Not Run | [ASSIGN](#evidence-assign), [PICKUPCREATE](#evidence-pickupcreate), [TASSIGN](#evidence-tassign)<br>Dispatch queue and assign guard require arrived, sorted, unassigned parcels. Packed/not-ready items are not eligible; pickup creation separately requires ready_for_pickup. | Exercise a Packed order and unsorted/non-arrived parcel through UI and direct API; assert useful rejection, no assignment/history/finance changes. |

### L4 Hub Ops

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-HUB-001** — Inbound receiving by scan<br>Excel row 2; High; assumptions A10 | Status At Hub with the scan time, in Logistics and Buyer views. | **Partial**<br>Not Run | [ARRIVE](#evidence-arrive), [INBOUNDUI](#evidence-inboundui), [PARCEL](#evidence-parcel), [TINBOUND](#evidence-tinbound)<br>Tracking-text lookup/arrival transitions PICKED_UP to AT_SORTING_CENTER. No durable received_at/hub event or At Hub buyer milestone is written. | Exercise scanner-as-keyboard/manual input, exact scan timestamp and both roles' timelines; include whitespace-padded valid tracking reference. |
| **LOG-HUB-002** — Sort by zone<br>Excel row 3; Medium; assumptions none | The parcel is placed in the NCR zone list only. | **Partial**<br>Not Run | [SORT](#evidence-sort), [SORTUI](#evidence-sortui), [SCHEMA](#evidence-schema), [TSORT](#evidence-tsort)<br>Sorting validates and stores an owned destination city area. Workbook NCR regional zone grouping/list is not implemented as that model. | Agree region-zone versus city-area semantics; map ADDR-1, reject wrong city/province and assert it appears in only the intended grouping. |
| **LOG-HUB-003** — Outbound dispatch<br>Excel row 4; High; assumptions A10, A32 | Status Out for Delivery; the delivery OTP appears in Buyer 1's order. | **Partial**<br>Not Run | [ASSIGN](#evidence-assign), [DELIVER](#evidence-deliver), [PROOF](#evidence-proof), [ROUTES](#evidence-routes)<br>Last-mile assignment and rider start exist, but no outbound-scan stage or buyer-visible six-digit delivery OTP is generated/validated. Registration OTP is unrelated. | Verify inbound-before-outbound, assignment/start, buyer OTP display, expiry/replay and five-wrong-attempt 15-minute lockout before delivery with valid photo. |
| **LOG-HUB-004** — Duplicate scan<br>Excel row 5; Medium; assumptions A37 | The second scan warns 'already received'. Only one At Hub event exists. | **Partial**<br>Not Run | [ARRIVE](#evidence-arrive), [PARCEL](#evidence-parcel), [TINBOUND](#evidence-tinbound)<br>Transaction/status guard blocks the second arrival with a generic expected-PICKED_UP error. There is no durable At Hub event to count or specific already-received message. | Repeat and concurrently submit identical scans; require one hub event/timestamp and clear already-received feedback without duplicate notifications. |
| **LOG-HUB-005** — Parcel not in the system<br>Excel row 6; High; assumptions none | Rejected with 'not found'. No record is created. | **Implemented**<br>Not Run | [ARRIVE](#evidence-arrive), [INBOUNDUI](#evidence-inboundui), [TINBOUND](#evidence-tinbound)<br>Unknown lookup returns not found; arrival of a missing reference rejects without creating a parcel. | Enter/scan TRK-0000000 through UI and API; assert clear not-found feedback and unchanged parcel/history/stock/finance counts. |
| **LOG-HUB-006** — Out-of-order scan<br>Excel row 7; Medium; assumptions A10 | Blocked; status unchanged. | **Partial**<br>Not Run | [ARRIVE](#evidence-arrive), [ASSIGN](#evidence-assign), [DELIVER](#evidence-deliver)<br>Existing assignment/start guards require hub status, but a dedicated outbound-scan workflow and its event ordering are absent. | Once the scan contract exists, scan outbound before inbound and send direct requests; prove state/assignment/event/stock/finance remain unchanged. |
| **LOG-HUB-007** — Another company's parcel<br>Excel row 8; Medium; assumptions A22 | Rejected; Logistics 2's shipment unchanged. | **Implemented**<br>Not Run | [ARRIVE](#evidence-arrive), [SORT](#evidence-sort), [TINBOUND](#evidence-tinbound)<br>Lookup/arrival/sorting reject a parcel already owned by another sorting center. | Use a real second-company reference for lookup and arrival; assert denial, no other-company details and no changes to either company's parcel/events. |

### L5 Tracking &amp; Exceptions

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-TRK-001** — Search by tracking number, order ID or buyer<br>Excel row 2; High; assumptions none | Each valid search returns exactly the right shipment. Unknown values return no results with no error. | **Partial**<br>Not Run | [ARRIVE](#evidence-arrive), [TRACK](#evidence-track), [TRACKUI](#evidence-trackui), [PICKUPCREATE](#evidence-pickupcreate)<br>Incoming lookup supports the exact parcel tracking reference (ITEM-&lt;item id&gt;). Monitoring has status/page filters only; no parent-order/buyer-name shipment search. | Test tracking reference, real parent order ID/number and buyer name, unknown values, quoted/SQL-like strings and second-company matches with pagination. |
| **LOG-TRK-002** — Manual override needs a reason<br>Excel row 3; High; assumptions A10, A25 | The empty reason is blocked. With the reason, status becomes Delivered. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [TRACK](#evidence-track), [DELIVER](#evidence-deliver)<br>No Logistics override endpoint/UI. Rider delivery requires own assignment and a valid photo; an override reason cannot currently replace that flow. | Agree authorized exception/proof rules, reason 10-300 limits and allowed transitions; test rejected empty reason, successful audited override and rollback. |
| **LOG-TRK-003** — Override leaves an audit log<br>Excel row 4; High; assumptions A35 | The entry shows user, date and time, Out for Delivery → Delivered and the reason. There is no Edit or Delete. | **Missing**<br>Not Run | [HISTORY](#evidence-history), [PARCEL](#evidence-parcel), [NAV](#evidence-nav), [ROUTES](#evidence-routes)<br>Order-item histories store status/note/time, not a complete actor/old-new/reason Logistics audit log. Override and its read-only audit UI are absent. | After override, verify actor/time/old-new/reason, tenant scope, no UI edit/delete, immutable history behavior and duplicate-request handling. |
| **LOG-TRK-004** — Mark a parcel Lost<br>Excel row 5; Medium; assumptions A23 | Status Lost; Buyer 1 and Seller 1 are notified; the refund or claim process starts. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [TRACK](#evidence-track), [PARCEL](#evidence-parcel), [RETURNS](#evidence-returns)<br>No Lost action/status workflow or linked loss claim/refund initiation. Buyer-initiated customer returns are a separate process. | After policy approval, mark Lost with required reason; assert buyer/seller notifications, exactly one claim/refund and no unintended stock/earning movement. |
| **LOG-TRK-005** — Mark a parcel Damaged with a photo<br>Excel row 6; Medium; assumptions A16 | Blocked without a photo; with it, status and photo are saved and both parties notified. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [PROOF](#evidence-proof), [TRACK](#evidence-track)<br>Delivery photo proof exists for rider completion, not a Logistics Damaged action with photo and two-party notices. | Test missing/invalid/oversize image, successful private damage evidence, owned access, notifications and rollback when persistence fails. |

### L6 Returns (RTS)

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-RTS-001** — RTS queue after 3 failed attempts<br>Excel row 2; High; assumptions A13 | The parcel is listed with status Return to Sender and all 3 failure reasons. | **Missing**<br>Not Run | [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel), [SCHEMA](#evidence-schema)<br>One failure immediately writes terminal FAILED_RETURNED plus a single free-text reason. There is no three-attempt ledger, refusal rule or RTS queue. | After agreeing A13, record failures 1/2/3 and Refused; verify individual reasons/times, threshold transition, queue and retry idempotency. |
| **LOG-RTS-002** — Deliver back to the seller<br>Excel row 3; High; assumptions A10 | Status Returned to Sender. Buyer: order Cancelled (failed delivery). Seller: Returned, awaiting confirmation. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [NAV](#evidence-nav), [DELIVER](#evidence-deliver), [RETURNS](#evidence-returns)<br>No reverse assignment, Return to Sender/Returned to Sender transition or failed-delivery buyer cancellation/seller awaiting-confirmation flow. | Complete reverse shipment with assigned rider; inspect Logistics/Buyer/Seller views and financial state, including duplicate return confirmation. |
| **LOG-RTS-003** — Stock restored only when the seller confirms<br>Excel row 4; High; assumptions A3 | Stock stays 19 until confirmation, then becomes 20. | **Missing**<br>Not Run | [RETURNS](#evidence-returns), [PARCEL](#evidence-parcel), [HISTORY](#evidence-history)<br>Customer-return processing exists separately; no RTS receipt-confirmation path links failure to exactly-once stock restoration. | Use isolated P-02 stock 19 and quantity one; assert 19 before seller RTS receipt, 20 once after, unchanged on retries/concurrent confirmation. |
| **LOG-RTS-004** — Prepaid RTS refund and no earnings<br>Excel row 5; Medium; assumptions A15 | Buyer 1's refund for the full amount is started. Seller 1 has no earnings for the order. | **Missing**<br>Not Run | [RETURNS](#evidence-returns), [FINANCE](#evidence-finance), [PARCEL](#evidence-parcel)<br>No prepaid RTS completion/refund integration. Existing refunds and seller-funded delivery charge accounting do not establish this reverse-logistics behavior. | Agree refund/commission/delivery-charge effects; complete prepaid RTS and verify one full refund initiation, zero applicable seller merchandise earnings and preserved ledgers. |

### L7 Zones &amp; Rates

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-RATE-001** — Rate change shows at checkout<br>Excel row 2; High; assumptions A20, A35 | The new checkout shows ₱55.00. The earlier order still shows ₱50.00. The change is in the audit log. | **Missing**<br>Not Run | [CHECKOUT](#evidence-checkout), [FINANCE](#evidence-finance), [CONFIG](#evidence-config), [ROUTES](#evidence-routes)<br>No Logistics rate editor/versioned weight-zone pricing. New buyer shipping is deliberately zero; delivered seller packages use fixed PHP50, split PHP10/PHP40. | Approve A20 versus current seller-funded policy first; then test new/old checkout snapshots, first/additional kg rounding, effective time and audit. |
| **LOG-RATE-002** — Invalid rate values<br>Excel row 3; Medium; assumptions none | Only 0.01 saves. | **Missing**<br>Not Run | [ROUTES](#evidence-routes), [COVERAGE](#evidence-coverage), [SCHEMA](#evidence-schema)<br>No shipping-rate persistence/validation endpoint; creating a service area does not create a monetary rate. | After pricing is approved, test -1, 0, 0.01, abc, excessive decimals and boundaries through UI/API with no writes for rejected values. |
| **LOG-RATE-003** — Unserviceable area blocked<br>Excel row 4; High; assumptions none | Place Order is blocked with a not-serviceable message. | **Missing**<br>Not Run | [CHECKOUTSTORE](#evidence-checkoutstore), [COVERAGE](#evidence-coverage), [PICKUPCREATE](#evidence-pickupcreate)<br>Checkout validates a free-text address, not whether its destination belongs to an approved Logistics coverage zone. Coverage currently constrains partner/rider enrollment and sorting. | In isolated checkout, use uncovered ADDR-4 via UI and direct request; require a serviceability message and no order/stock/voucher changes. |
| **LOG-RATE-004** — Add a zone<br>Excel row 5; Medium; assumptions A20 | Checkout now works and uses the new rate. | **Partial**<br>Not Run | [COVERAGE](#evidence-coverage), [SORT](#evidence-sort), [CHECKOUTSTORE](#evidence-checkoutstore)<br>Owned city coverage can be added, but there is no zone rate or checkout pricing/serviceability link. | Add Batanes under the agreed zone model, verify boundary/canonical city coverage and that only subsequent eligible checkouts receive the approved rate. |

### L8 COD &amp; Reports

| Original ID / scenario | Original expected result | Source classification | Evidence and observed gap | Required verification (in addition to original steps) |
|---|---|---|---|---|
| **LOG-COD-001** — Remittance matches COD collected<br>Excel row 2; High; assumptions A24 | Collected ₱1,800.00, remitted ₱1,800.00, difference ₱0.00, with both orders listed. | **Missing**<br>Not Run | [SETTLE](#evidence-settle), [FINANCE](#evidence-finance), [SCHEMA](#evidence-schema), [ROUTES](#evidence-routes)<br>Existing collections settle seller delivery charges through Admin; no rider COD-collected/remitted ledger or Logistics reconciliation screen exists. | Create isolated COD collections totaling PHP1800 and matching remittance; verify totals, both orders, date/company scope and financial separation. |
| **LOG-COD-002** — Short remittance flagged<br>Excel row 3; High; assumptions A24 | Difference −₱50.00 is highlighted as a shortfall. | **Missing**<br>Not Run | [SETTLE](#evidence-settle), [ROUTES](#evidence-routes), [NAV](#evidence-nav)<br>No COD shortfall calculation or reconciliation UI; delivery fee collection is not COD remittance. | With PHP1800 collected/PHP1750 remitted, require -PHP50 shortfall; also test overpayment, fractional money and cross-company isolation. |
| **LOG-COD-003** — Confirm remittance<br>Excel row 4; Medium; assumptions A24, A35 | Rider 1's balance updates; the confirmation is logged. | **Missing**<br>Not Run | [SETTLE](#evidence-settle), [ADMIN](#evidence-admin), [ROUTES](#evidence-routes)<br>Current manual settlement is approved-admin-only for delivery charges/rider earnings, not Logistics confirmation of rider COD and balance updates. | After agreeing authority, confirm COD as owning Logistics only; test other roles, duplicate request/refresh, balance/audit atomicity and concurrent confirmation. |
| **LOG-COD-004** — Export reports<br>Excel row 5; Medium; assumptions A36 | Both files download and open. Rows and totals equal the screen for the same filter. | **Missing**<br>Not Run | [REPORT](#evidence-report), [REPORTUI](#evidence-reportui), [ROUTES](#evidence-routes)<br>Delivery JSON reports/charts exist; no CSV/XLSX export action/endpoint or COD report data source is implemented. | After building both report sources, export CSV and XLSX over multi-page/date-boundary data and compare every row/total against the same screen filters; test formula-like cells. |
| **LOG-COD-005** — Export with no data<br>Excel row 6; Low; assumptions A36 | A file with headers only, or a 'no data' message. No error. | **Missing**<br>Not Run | [REPORT](#evidence-report), [REPORTUI](#evidence-reportui), [ROUTES](#evidence-routes)<br>Report JSON can represent zero records, but no downloadable CSV/XLSX path or export-specific no-data feedback exists. | Export an empty range in both formats; require readable headers-only files or an explicit no-data message without exception or leaked records. |

## Review findings and pre-test priorities

These are source-review findings, not executed failing test results. Workbook-rule mismatches require product agreement before implementation.

| Finding | Severity / confidence | Evidence | Trigger and impact / recommendation |
|---|---|---|---|
| F01 — Arrival lookup/confirm normalize differently | Medium / high static confidence; runtime not run | [INBOUNDUI](#evidence-inboundui), [CONFIRMUI](#evidence-confirmui), [ARRIVE](#evidence-arrive) | Paste a valid reference with surrounding whitespace: lookup trims it, confirm sends the raw value, backend queries exact order_id. The displayed valid parcel can then fail confirmation. Recommend one agreed normalized identifier across both boundaries and a regression case. |
| F02 — Rejection/suspension reasons absent | High relative to A25 / confirmed source gap | [REJECT](#evidence-reject), [DEACTIVATE](#evidence-deactivate), [NOTIFY](#evidence-notify) | Blank/9-character reason is not validated; exact rejection reason is not stored/notified. Agree bounds, persist reason, use safe UI text and notify without weakening ownership guards. |
| F03 — Deactivation can strand work | High / confirmed source behavior; desired policy pending | [DEACTIVATE](#evidence-deactivate), [RIDERGUARD](#evidence-riderguard), [PICKUP](#evidence-pickup), [ASSIGN](#evidence-assign) | is_active becomes false without releasing unpicked jobs; rider middleware blocks ongoing operational APIs too. Agree what happens to in-hand parcels, then atomically release only the intended jobs and preserve safe completion rights. |
| F04 — Eligibility can change during assignment | Medium / possible concurrency defect; not executed | [ASSIGN](#evidence-assign), [DEACTIVATE](#evidence-deactivate), [RIDERMODEL](#evidence-ridermodel) | Assignment reads rider eligibility without a rider-row lock; deactivation independently saves that row. An interleaving can assign work to a just-deactivated rider. Design compatible locking/recheck without breaking existing order→shipment→parcel finance lock ordering, then verify on an approved test engine. |
| F05 — Hub receipt is not a durable buyer event | High relative to L4 / confirmed source gap | [ARRIVE](#evidence-arrive), [PARCEL](#evidence-parcel), [HISTORY](#evidence-history), [TINBOUND](#evidence-tinbound) | Arrival only changes center/status. Subsequent updated_at changes lose the receipt time; no At Hub item history is emitted. Existing arrival test even expects sorted_at null. Add a distinct event/time contract rather than misusing sorted_at. |
| F06 — Proof policy mismatch | Medium / confirmed rule mismatch | [PROOF](#evidence-proof) | Rule permits up to 20 MiB and generic images; A16 requires JPG/PNG ≤5 MB. Installed Laravel image validator also supports other types. Web effective limits remain unknown. Agree policy and test real content/extension/size boundaries; preserve private storage and photo checks. |
| F07 — Failure closes parcel on first attempt | High relative to A13/RTS / confirmed source behavior | [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel) | fail writes FAILED_RETURNED with one arbitrary string; later fail calls require OUT_FOR_DELIVERY and cannot build three reasons. Design explicit attempts/RTS transitions before running L6. |
| F08 — Dashboard/report semantics differ | Medium / confirmed source mismatch | [DASH](#evidence-dash), [DASHUI](#evidence-dashui), [REPORT](#evidence-report), [REPORTUI](#evidence-reportui) | Workbook four shipment counters/date filter differ from operational queue cards; report dates use sorted_at, not delivery date. Agree metrics/timestamps and reconcile all paginated records, not just the first page. |

Detailed missing features, decision gates and development order: [LOGISTICS_MISSING_FEATURES.md](LOGISTICS_MISSING_FEATURES.md).

## Evidence and proposed testing requirements

Existing test definitions found: DashboardTest (six current counters), RiderManagementScopeTest (owned approval), ParcelAssignmentTest (area/approval/active eligibility), IncomingParcelTest (arrival/status/ownership), ParcelSortingTest (city/province ownership), ParcelMonitoringTest (pagination/status), ReportTest (sorted_at counts/date validation), DeliveryPhotoIntegrityTest (owned private photo/retry), RiderDeliveryCompletionTest and finance tests. Their presence/previous historical passes are **not** execution evidence for this new workbook. [TDASH](#evidence-tdash), [TRIDERS](#evidence-triders), [TASSIGN](#evidence-tassign), [TINBOUND](#evidence-tinbound), [TSORT](#evidence-tsort), [TREPORT](#evidence-treport), [TPHOTO](#evidence-tphoto)

Recommended order after approval:

1. Agree the state-machine, pickup/delivery job unit, Online/capacity rules, financial authority/funding, proof/OTP policy and audit contract. Revise workbook expectations only with the owner's approval; preserve the original file.
2. In an isolated approved fixture, start with source-complete ownership/readiness/not-found cases LOG-DASH-004, LOG-RIDER-007, LOG-DISP-008, LOG-HUB-005 and LOG-HUB-007. Also reproduce F01 and negative reason/eligibility/scan guards. These are candidates to run, not Pass claims.
3. Add case-ID-mapped regression tests for missing/partial behavior only after its implementation is authorized. Use isolated SQLite memory for application tests; do not run against development/production. Composer setup/test scripts may perform migration/config work; none were invoked here.
4. For all 44 cases capture original ID, build, browser/device, fixture IDs, requests/responses, before/after state, local timestamps, screenshots and other-role views. Verify stock, vouchers, notifications, balances, histories, private proof access, errors and retry side effects explicitly.
5. After separate approval, verify genuine MySQL contention, browser-to-API/CSRF/token behavior, real device/scanner input, upload limits and notifications in a disposable environment. The populated MySQL database was not connected or inventoried here.

## Source evidence index

<a id="evidence-routes"></a>**ROUTES** — [routes/api.php:65](../../../routes/api.php#L65)

<a id="evidence-web"></a>**WEB** — [routes/logistics.php:15](../../../routes/logistics.php#L15)

<a id="evidence-entry"></a>**ENTRY** — [resources/js/logistics.js:12](../../../resources/js/logistics.js#L12)

<a id="evidence-nav"></a>**NAV** — [resources/js/Layouts/ScLayout.vue:75](../../../resources/js/Layouts/ScLayout.vue#L75)

<a id="evidence-dash"></a>**DASH** — [app/Http/Controllers/Logistics/DashboardController.php:15](../../../app/Http/Controllers/Logistics/DashboardController.php#L15)

<a id="evidence-dashui"></a>**DASHUI** — [resources/js/Pages/Logistics/Dashboard.vue:72](../../../resources/js/Pages/Logistics/Dashboard.vue#L72)

<a id="evidence-rider"></a>**RIDER** — [app/Http/Controllers/Logistics/RiderManagementController.php:41](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L41)

<a id="evidence-reject"></a>**REJECT** — [app/Http/Controllers/Logistics/RiderManagementController.php:80](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L80)

<a id="evidence-deactivate"></a>**DEACTIVATE** — [app/Http/Controllers/Logistics/RiderManagementController.php:149](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L149)

<a id="evidence-activate"></a>**ACTIVATE** — [app/Http/Controllers/Logistics/RiderManagementController.php:116](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L116)

<a id="evidence-docs"></a>**DOCS** — [app/Http/Controllers/PartnerProfileController.php:60](../../../app/Http/Controllers/PartnerProfileController.php#L60)

<a id="evidence-ridermodel"></a>**RIDERMODEL** — [app/Models/Rider.php:57](../../../app/Models/Rider.php#L57)

<a id="evidence-riderguard"></a>**RIDERGUARD** — [app/Http/Middleware/EnsureRiderApproved.php:11](../../../app/Http/Middleware/EnsureRiderApproved.php#L11)

<a id="evidence-centerguard"></a>**CENTERGUARD** — [app/Http/Middleware/EnsureSortingCenterApproved.php:19](../../../app/Http/Middleware/EnsureSortingCenterApproved.php#L19)

<a id="evidence-elig"></a>**ELIG** — [app/Http/Controllers/Logistics/ParcelAssignmentController.php:48](../../../app/Http/Controllers/Logistics/ParcelAssignmentController.php#L48)

<a id="evidence-assign"></a>**ASSIGN** — [app/Http/Controllers/Logistics/ParcelAssignmentController.php:91](../../../app/Http/Controllers/Logistics/ParcelAssignmentController.php#L91)

<a id="evidence-assignui"></a>**ASSIGNUI** — [resources/js/Pages/Logistics/RiderAssignment.vue:205](../../../resources/js/Pages/Logistics/RiderAssignment.vue#L205)

<a id="evidence-pickup"></a>**PICKUP** — [app/Http/Controllers/Logistics/RiderPickupController.php:86](../../../app/Http/Controllers/Logistics/RiderPickupController.php#L86)

<a id="evidence-pickupcreate"></a>**PICKUPCREATE** — [app/Services/OrderItemPickup.php:12](../../../app/Services/OrderItemPickup.php#L12)

<a id="evidence-arrive"></a>**ARRIVE** — [app/Http/Controllers/Logistics/IncomingParcelController.php:14](../../../app/Http/Controllers/Logistics/IncomingParcelController.php#L14)

<a id="evidence-inboundui"></a>**INBOUNDUI** — [resources/js/Pages/Logistics/OrderManagement.vue:116](../../../resources/js/Pages/Logistics/OrderManagement.vue#L116)

<a id="evidence-confirmui"></a>**CONFIRMUI** — [resources/js/Pages/Logistics/OrderManagement.vue:162](../../../resources/js/Pages/Logistics/OrderManagement.vue#L162)

<a id="evidence-sort"></a>**SORT** — [app/Http/Controllers/Logistics/ParcelSortingController.php:99](../../../app/Http/Controllers/Logistics/ParcelSortingController.php#L99)

<a id="evidence-sortui"></a>**SORTUI** — [resources/js/Pages/Logistics/DeliveryManagement.vue:194](../../../resources/js/Pages/Logistics/DeliveryManagement.vue#L194)

<a id="evidence-track"></a>**TRACK** — [app/Http/Controllers/Logistics/ParcelMonitoringController.php:21](../../../app/Http/Controllers/Logistics/ParcelMonitoringController.php#L21)

<a id="evidence-trackui"></a>**TRACKUI** — [resources/js/Pages/Logistics/DeliveryMonitoring.vue:170](../../../resources/js/Pages/Logistics/DeliveryMonitoring.vue#L170)

<a id="evidence-deliver"></a>**DELIVER** — [app/Http/Controllers/Logistics/RiderDeliveryController.php:133](../../../app/Http/Controllers/Logistics/RiderDeliveryController.php#L133)

<a id="evidence-proof"></a>**PROOF** — [app/Http/Controllers/Logistics/RiderDeliveryController.php:195](../../../app/Http/Controllers/Logistics/RiderDeliveryController.php#L195)

<a id="evidence-parcel"></a>**PARCEL** — [app/Models/Parcel.php:43](../../../app/Models/Parcel.php#L43)

<a id="evidence-notify"></a>**NOTIFY** — [app/Observers/RiderNotificationObserver.php:78](../../../app/Observers/RiderNotificationObserver.php#L78)

<a id="evidence-history"></a>**HISTORY** — [database/migrations/2026_08_29_070001_create_order_item_status_histories_table.php:11](../../../database/migrations/2026_08_29_070001_create_order_item_status_histories_table.php#L11)

<a id="evidence-schema"></a>**SCHEMA** — [database/migrations/2026_10_11_100000_create_logistics_tables.php:47](../../../database/migrations/2026_10_11_100000_create_logistics_tables.php#L47)

<a id="evidence-coverageschema"></a>**COVERAGESCHEMA** — [database/migrations/2026_10_12_000002_add_city_codes_to_service_areas.php:12](../../../database/migrations/2026_10_12_000002_add_city_codes_to_service_areas.php#L12)

<a id="evidence-proofschema"></a>**PROOFSCHEMA** — [database/migrations/2026_10_11_100001_create_delivery_proofs_table.php:14](../../../database/migrations/2026_10_11_100001_create_delivery_proofs_table.php#L14)

<a id="evidence-financeschema"></a>**FINANCESCHEMA** — [database/migrations/2026_10_15_100000_create_seller_shipment_finance.php:11](../../../database/migrations/2026_10_15_100000_create_seller_shipment_finance.php#L11)

<a id="evidence-report"></a>**REPORT** — [app/Http/Controllers/Logistics/ReportController.php:21](../../../app/Http/Controllers/Logistics/ReportController.php#L21)

<a id="evidence-reportui"></a>**REPORTUI** — [resources/js/Pages/Logistics/DeliveryReports.vue:326](../../../resources/js/Pages/Logistics/DeliveryReports.vue#L326)

<a id="evidence-coverage"></a>**COVERAGE** — [app/Http/Controllers/DeliveryCoverageController.php:16](../../../app/Http/Controllers/DeliveryCoverageController.php#L16)

<a id="evidence-checkout"></a>**CHECKOUT** — [app/Http/Controllers/BuyerOrderController.php:237](../../../app/Http/Controllers/BuyerOrderController.php#L237)

<a id="evidence-checkoutstore"></a>**CHECKOUTSTORE** — [app/Http/Controllers/BuyerOrderController.php:660](../../../app/Http/Controllers/BuyerOrderController.php#L660)

<a id="evidence-config"></a>**CONFIG** — [config/checkout.php:9](../../../config/checkout.php#L9)

<a id="evidence-finance"></a>**FINANCE** — [app/Services/SellerShipmentFinance.php:97](../../../app/Services/SellerShipmentFinance.php#L97)

<a id="evidence-settle"></a>**SETTLE** — [app/Http/Controllers/DeliveryFinanceController.php:79](../../../app/Http/Controllers/DeliveryFinanceController.php#L79)

<a id="evidence-admin"></a>**ADMIN** — [routes/admin.php:27](../../../routes/admin.php#L27)

<a id="evidence-returns"></a>**RETURNS** — [app/Http/Controllers/SellerReturnController.php:188](../../../app/Http/Controllers/SellerReturnController.php#L188)

<a id="evidence-tdash"></a>**TDASH** — [tests/Feature/Logistics/DashboardTest.php:19](../../../tests/Feature/Logistics/DashboardTest.php#L19)

<a id="evidence-triders"></a>**TRIDERS** — [tests/Feature/Logistics/RiderManagementScopeTest.php:16](../../../tests/Feature/Logistics/RiderManagementScopeTest.php#L16)

<a id="evidence-tassign"></a>**TASSIGN** — [tests/Feature/Logistics/ParcelAssignmentTest.php:20](../../../tests/Feature/Logistics/ParcelAssignmentTest.php#L20)

<a id="evidence-tinbound"></a>**TINBOUND** — [tests/Feature/Logistics/IncomingParcelTest.php:45](../../../tests/Feature/Logistics/IncomingParcelTest.php#L45)

<a id="evidence-tsort"></a>**TSORT** — [tests/Feature/Logistics/ParcelSortingTest.php:18](../../../tests/Feature/Logistics/ParcelSortingTest.php#L18)

<a id="evidence-treport"></a>**TREPORT** — [tests/Feature/Logistics/ReportTest.php:18](../../../tests/Feature/Logistics/ReportTest.php#L18)

<a id="evidence-tphoto"></a>**TPHOTO** — [tests/Feature/Logistics/DeliveryPhotoIntegrityTest.php:51](../../../tests/Feature/Logistics/DeliveryPhotoIntegrityTest.php#L51)
