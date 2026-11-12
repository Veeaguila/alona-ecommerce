# ALONA Logistics requirement conflicts and proposed implementation plan

Date: 2026-10-17 (Asia/Manila). Branch: `feature/sorting-center`; inspected source: `463f88443b13eb471db22ac31df4b8be50b465a4`.

Inputs: [test analysis](LOGISTICS_TEST_ANALYSIS.md), [missing features](LOGISTICS_MISSING_FEATURES.md), and the freshly re-read [original workbook](../Blackbox_Test_Cases_Logistics_Modules-1.xlsx) (11 worksheets,44 cases,37 assumptions). Workbook SHA-256: `0615000a6d58f8b3e5b6690fffb08e498f6095ecd51158715486ee286570c84f`.

**Status: recommendations only; all decisions below are Pending approval.** Requirement clarification does not authorize implementation. No application code, existing reports, database, migrations, .env or workbook changed; no application tests/migrations/DB connections/commits/pushes were run. All44 original execution outcomes remain **Not Run**. The earlier5 implemented/17 partial/22 missing classification is static analysis, not an accepted change of requirements or a Pass count.

## Recommended baseline to approve

1. **Preserve existing financial rules by default:** buyer shippingPHP0; Seller-shipment feePHP50, LogisticsPHP10/RiderPHP40; no new pickup fee; existing Admin settlement authority stays intact. Monetary rates/pay/COD changes require explicit Finance/product agreement.
2. **Preserve two dispatch legs and whole-shipment consistency:** recommend one shipment per active leg as a capacity job, not each item parcel. Retain existing API compatibility/history, and add agreed labels/events instead of blindly renaming states.
3. **Modify genuine missing Logistics behavior only after approval:** reasoned Rider management, capacity/reassignment, hub events, delivery OTP, audit, exceptions, attempts/RTS and optional separate COD/export workflows.
4. **Clarify ambiguous thresholds/permissions/data units first:** suspension/in-hand completion, nearest location source, OTP expiry/fallback, positive rate units/effective quotes, count/date basis, shared pickup pool and idle versus hard token expiry.
5. **Keep broader Commerce mismatches separate:** do not silently change vouchers, categories, reviews, address UX, global image rules or customer-return/payout behavior while fixing Logistics. Their owners must reconcile fixture/financial dependencies where relevant.

Preserving a conflicting rule does **not** satisfy the original expected result. Example: aPHP0 buyer-shipping policy cannot pass LOG-RATE-001'sPHP55 checkout assertion. An approved versioned acceptance supplement (or separately authorized redesign) is required. The original Excel file stays untouched; no original case becomes Pass, Fail, Blocked or out-of-scope merely from this document.

## How to read the conflicts

- **Confirmed rule/model conflict:** implemented source differs from the assumption. This is not proof of a live failing case or that the workbook is the authoritative product rule.
- **Capability gap:** a requested behavior is absent; that is different from an approved existing rule being wrong.
- **Ambiguity/unverified:** policy, deployment configuration, event semantics or fixture facts remain unresolved. Defaults are not proof of production settings.
- **Preserve / Modify / Clarify:** proposed action, not owner approval. Source links identify exact current implementations.

The workbook's Assumption Ref column does not contain every dependency. Each assumption below lists **exact direct IDs from that column**; additional impacts are explicitly identified separately. Empty direct references mean no original Logistics case explicitly cites that assumption, not that it has been tested. All37 assumptions were reviewed, including broader shared-workbook rules. Broader rows are contract findings/dependencies, not a request to expand Logistics implementation into those modules.

## Assumption-by-assumption register

| Assumption / type / scope | Workbook expects | Existing ALONA behavior and evidence | Direct affected original test IDs | Recommended decision |
|---|---|---|---|---|
| **A1 — Test accounts**<br>Fixture ambiguity<br>Logistics | Approved/Active fixture accounts; Rider 3 Suspended; fixtures reset before each module. | Deployment fixtures were not queried. Rider approval_status plus is_active are separate; approved+inactive can represent deactivation, not Online presence.<br>[SCHEMA](#evidence-schema), [RIDERMODEL](#evidence-ridermodel), [DEACTIVATE](#evidence-deactivate) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify fixture state mapping and isolated reset ownership; preserve real accounts/data. Do not seed/reset development MySQL. |
| **A2 — Voucher stacking (rule 1)**<br>Confirmed model conflict<br>Commerce / shared fixtures | One store voucher per store plus one platform voucher and one shipping voucher can coexist. | Checkout accepts one voucher_code; Order stores one voucher_id/code. The three-component stack is not represented by that path.<br>[CHECKOUTSTORE](#evidence-checkoutstore), [ORDERMODEL](#evidence-ordermodel) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve current checkout for this Logistics scope; Commerce must approve separate voucher redesign or a versioned acceptance deviation. Do not use the worked checkout totals as fixtures without reconciliation. |
| **A3 — Stock deduction (rule 2)**<br>RTS gap + return-policy difference<br>Logistics | Deduct stock at order placement; restore cancellations; RTS/returns restore exactly once only on seller receipt. | Cancellation restocks items; customer-return inspection optionally restocks when restock_inventory is true. No RTS receipt/attempt chain exists.<br>[BUYERCANCEL](#evidence-buyercancel), [SELLERRETURN](#evidence-sellerreturn), [RETURNS](#evidence-returns), [PARCEL](#evidence-parcel) | LOG-RTS-003 | Modify the future RTS path to record seller receipt and exactly-once stock restoration after approval. Preserve ordinary cancellation/customer returns; never reuse cancellation restock and then restock the same RTS again. |
| **A4 — Sale + voucher (rule 3)**<br>Partially compatible; unresolved<br>Commerce / shared fixtures | Vouchers use the actual sale/flash price for eligibility and minimum spend. | Checkout itemPrice uses product.price; Product has price/old_price. That may match ordinary displayed current price, but scheduled flash-price precedence and mixed-price units are not established.<br>[ITEMPRICE](#evidence-itemprice), [PRODUCT](#evidence-product) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve ordinary current-price handling; Commerce/QA must confirm sale/flash fixtures and eligibility separately. This is not a confirmed blanket sale/voucher failure. |
| **A5 — Flash sales (rule 4)**<br>Missing broader capability<br>Commerce | Scheduled flash sales override store sales while flash quantity lasts; remaining units use a separate normal-sale line. | No corresponding scheduled flash-sale or split-price quantity workflow was found in the inspected Product/checkout model/controller paths.<br>[PRODUCT](#evidence-product), [ITEMPRICE](#evidence-itemprice) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify with Commerce; defer separate promotion work outside Logistics. Replace incompatible fixtures only through an approved acceptance supplement. |
| **A6 — Cancellation window and auto-cancel (rule 5)**<br>Confirmed cutoff difference + scheduler gap<br>Commerce / RTS dependency | Buyer cancellation ends at Ready for Pickup; Seller may cancel until Picked Up; unpaid online orders cancel at exactly one hour. | Buyer and Seller cancellation paths accept pending/processing/packed; Seller already rejects ready_for_pickup. No one-hour cancellation schedule is declared in inspected app/bootstrap/routes.<br>[BUYERCANCEL](#evidence-buyercancel), [SELLERCANCEL](#evidence-sellercancel), [FINANCELOCK](#evidence-financelock), [CONSOLE](#evidence-console) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify Commerce cancellation authority and scheduler before connecting RTS cancellation. Preserve finance dispatch freeze and keep an RTS return distinct from ordinary cancellation. |
| **A7 — Variation limits and category depth (rule 6)**<br>Confirmed catalog-model difference<br>Commerce / shared fixtures | At most two variation types, 1-20 options each; store categories two levels and up to three categories per product. | Product has one category_id; ProductVariant stores color/size/stock. This is not the described multi-store-category/general option model or its stated limits.<br>[PRODUCT](#evidence-product), [VARIANT](#evidence-variant) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve catalog model for Logistics; route taxonomy/variation acceptance to Commerce. Product fixture representability is a prerequisite, not a Logistics rewrite. |
| **A8 — Voucher funding (rule 7)**<br>Funding/allocation contract unresolved<br>Finance / Commerce / RTS | Platform/shipping vouchers never reduce Seller earnings; mixed-store discount/refund allocations follow the specified centavo rules. | Single-voucher checkout and existing sales summaries do not establish this full allocation contract. Delivery fee recognition explicitly uses fixed money independent of vouchers/order total.<br>[ORDERMODEL](#evidence-ordermodel), [FINANCE](#evidence-finance), [SELLERDASH](#evidence-sellerdash) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve independent delivery-fee accounting. Finance/Commerce must approve merchandise discount/refund allocation and fixtures separately; no new commission/funding inference from this workbook. |
| **A9 — Platform commission and rounding**<br>Configuration/authority ambiguity<br>Finance / Commerce | Fixed 5% merchandise commission after Seller-funded discounts, rounded per order; exclude shipping. | Seller report has a placeholder 5%; Seller dashboard can read active marketplace_commission_rate, default 5%. Actual configured rate and authoritative discount-adjusted ledger were not queried.<br>[SELLERREPORT](#evidence-sellerreport), [SELLERDASH](#evidence-sellerdash), [FINANCE](#evidence-finance) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify authoritative commission source/base with Finance. Preserve courier fee allocation; its 20% Logistics share is not marketplace commission. Do not infer the deployed setting. |
| **A10 — Order status flow**<br>Confirmed state/leg conflict<br>Logistics | Ready → Assigned → Picked Up → At Hub → Out for Delivery → Delivered/Failed → RTS; no skipping/backtracking except logged override. | ALONA separates pickup_rider_id from last-mile assigned_rider_id. Pickup uses PLACED/PREPARING → PICKED_UP → AT_SORTING_CENTER; last-mile assignment keeps hub status; start sets OUT_FOR_DELIVERY; first failure is FAILED_RETURNED.<br>[PICKUP](#evidence-pickup), [ARRIVE](#evidence-arrive), [ASSIGN](#evidence-assign), [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel), [PICKUPCREATE](#evidence-pickupcreate) | LOG-DISP-002, LOG-DISP-008, LOG-HUB-001, LOG-HUB-003, LOG-HUB-006, LOG-TRK-002, LOG-RTS-002 | Preserve two legs, existing API compatibility and historical states; add explicit presentation/events for agreed stages. Clarify override authority and transitions; do not simply rename stored states or prohibit normal pickup-to-last-mile handover. |
| **A11 — Order completion and return window**<br>Confirmed return-window difference + automation gap<br>Commerce / RTS finance | Buyer receipt or 168 hours after delivery completes order; returns only while Delivered, never Completed. | Return eligibility accepts delivered or completed, uses Seller return_policy_days with default seven; no seven-day auto-completion schedule is declared.<br>[RETURNREQUEST](#evidence-returnrequest), [CONSOLE](#evidence-console) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify with Commerce. Preserve existing customer-return policy until approved; do not use it as automatic RTS/refund acceptance. Define Delivered versus Completed timestamps and earning vesting explicitly. |
| **A12 — Reviews**<br>Confirmed broader review-policy difference<br>Commerce | Completed-only reviews, 30-day window, text max500, JPG/PNG photos max5, one edit. | Buyer review store permits delivered/completed; text max1000; media allow images/video up to 10 MiB. Owner update path does not enforce the stated edit-once/window contract.<br>[BUYERREVIEW](#evidence-buyerreview), [REVIEWUPDATE](#evidence-reviewupdate) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve current review behavior in this Logistics plan; Commerce must separately reconcile eligibility/media/edit policies. No Logistics case directly tests these limits. |
| **A13 — Failed delivery**<br>Confirmed failure/RTS conflict<br>Logistics | Up to three failures with enumerated reasons; third failure or immediate Refused triggers RTS. | Rider fail requires a free-text string and immediately transitions OUT_FOR_DELIVERY to terminal FAILED_RETURNED, storing one reason.<br>[DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel), [SCHEMA](#evidence-schema) | LOG-RTS-001 | Modify future failure handling after policy approval: attempts, reasons and separate RTS milestones. Preserve historical FAILED_RETURNED records as legacy terminal facts; never invent earlier attempt history. |
| **A14 — Rider job rules**<br>Missing dispatch/presence/capacity contract<br>Logistics | Only Active+Online riders; offer expires60s, try three riders, capacity three active jobs. | Eligibility checks approval, is_active, user status/verification and service area; no live Online state, expiring offers or three-job guard. Workload counts item parcels.<br>[RIDERMODEL](#evidence-ridermodel), [ELIG](#evidence-elig), [ASSIGN](#evidence-assign), [PICKUP](#evidence-pickup), [SHIPDISPATCH](#evidence-shipdispatch) | LOG-RIDER-001, LOG-DISP-003, LOG-DISP-004, LOG-DISP-007 | Clarify job unit and freshness first. Recommend one seller shipment per active leg (legacy parcel fallback), with reserved offers counted; add the approved cap/presence/offer logic without splitting a bound shipment. |
| **A15 — Seller earnings and payout**<br>Confirmed guard gaps + accounting-boundary ambiguity<br>Finance / Commerce / RTS | Merchandise earnings available only on Completed; exclude returns/refunds; payout100 minimum, within available balance, one pending request. | Seller sales summaries include delivered/completed. Payout request validates min100 but does not check available balance or one-pending in that action. Rider delivery earnings accrue under a different package-completion contract.<br>[SELLERDASH](#evidence-sellerdash), [PAYOUT](#evidence-payout), [FINANCE](#evidence-finance) | LOG-RTS-004 | Clarify/modify Seller payout and refund guards only in a separately approved Finance scope. Preserve Rider fee accrual as a separate ledger; RTS must specify merchandise versus delivery-fee adjustments and must not delete paid entries. |
| **A16 — Image upload rule**<br>Confirmed upload-policy difference<br>Logistics | JPG/PNG content only, maximum5 MB per image; product images1-9. | Rider proof uses generic image/max:20480 (20 MiB); product permits WebP and up to10 image-array entries; review/store image types/limits also vary. Effective server limits are unverified.<br>[PROOF](#evidence-proof), [PRODUCTUPDATE](#evidence-productupdate), [BUYERREVIEW](#evidence-buyerreview), [STOREPROFILE](#evidence-storeprofile) | LOG-TRK-005 | Recommend approved new Logistics proof/damage validation at JPG/PNG &lt;=5 MiB (5120 KiB), private storage retained. Team must confirm MB versus MiB. Do not apply global upload restrictions to unrelated modules automatically. |
| **A17 — Price and quantity rule**<br>Confirmed broader numeric-validation difference<br>Commerce / prospective rates | Price1..1000000, two decimals; integer stock/quantity0..99999. | Seller Product update accepts numeric price min0.01 and integer stock min0 without the specified maxima/decimal bounds.<br>[PRODUCTUPDATE](#evidence-productupdate) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve current catalog pricing for Logistics; Commerce must reconcile global numeric bounds. Define separate positive-centavo rate validation if a rate module is approved. |
| **A18 — Store vacation and verification**<br>Core compatible; remaining behavior unverified<br>Commerce / fixture dependency | Vacation leaves products visible but blocks cart/checkout; unapproved sellers can draft but not publish. | Vacation guards exist in cart and checkout; Seller product status inputs restrict publishing. Full role/UI/draft behavior was not rerun.<br>[CARTVACATION](#evidence-cartvacation), [CHECKOUTVACATION](#evidence-checkoutvacation), [PRODUCTUPDATE](#evidence-productupdate) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve these guards. Verify eligible Seller fixtures for Logistics; do not claim full assumption compliance from this source scan. |
| **A19 — Deleting categories and products**<br>Confirmed broader deletion-contract gap<br>Commerce / shipment integrity | Unfinished-order products/variants cannot be deleted; other deletion is soft; store category moves/deletes follow specified rules. | Seller variant destroy calls delete without an unfinished-order guard in that action; Product archive changes status and likewise has no such guard there. The store-category contract is not established.<br>[VARIANTDELETE](#evidence-variantdelete), [PRODUCTARCHIVE](#evidence-productarchive), [VARIANT](#evidence-variant) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify with Commerce and protect historical shipment links. Defer catalog deletion changes; never detach active OrderItem/Parcel references to satisfy a Logistics fixture. |
| **A20 — Shipping fee**<br>Confirmed financial-policy conflict<br>Logistics | Buyer shipping charged per store by zone and ceil(actual kg); changed rates affect new checkouts only. | Buyer calculateShippingFee returns0 for new seller-funded orders. A fully delivered Seller shipment recognizes PHP50, allocated PHP10 Logistics/PHP40 Rider. Configured nominal standard/express metadata is not used to charge these buyers.<br>[CHECKOUT](#evidence-checkout), [FINANCE](#evidence-finance), [CONFIG](#evidence-config), [COVERAGE](#evidence-coverage) | LOG-RATE-001, LOG-RATE-004 | Recommend preserving current zero buyer shipping and fixed delivery-fee rule. Team must either approve a versioned L7 price-expectation deviation or authorize a separate pricing redesign; serviceability can be developed separately from charging. |
| **A21 — Voucher return on cancellation**<br>Confirmed cancellation-side-effect gap<br>Commerce / RTS | Unexpired cancelled-order voucher returns to wallet and usage decreases; no return for refunds. | Checkout increments/uses voucher state; inspected Buyer cancellation restocks/notifies but does not reverse voucher use. No complete voucher reinstatement contract is established for RTS.<br>[BUYERCANCEL](#evidence-buyercancel), [VOUCHERUSE](#evidence-voucheruse) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify Commerce voucher policy before classifying RTS as Cancelled. Preserve records; implement refund/return-specific voucher behavior only if explicitly approved and deduplicated. |
| **A22 — Session and access**<br>Ownership compatible; expiry/pool ambiguity<br>Logistics | Role/record isolation and30-minute inactivity expiry. | Owned Logistics/Rider guards exist. Web portal tokens are created for two hours; session lifetime is configuration-driven (default120). API login token calls have no explicit per-token expiry; effective global configuration was not inspected. Unclaimed pickup requests form a shared pool.<br>[WEB](#evidence-web), [SESSION](#evidence-session), [RIDERLOGIN](#evidence-riderlogin), [CENTERLOGIN](#evidence-centerlogin), [CENTERGUARD](#evidence-centerguard), [DASH](#evidence-dash) | LOG-DASH-004, LOG-RIDER-007, LOG-HUB-007 | Preserve tenant/approval/private-file boundaries. Security/team must decide idle versus absolute timeout and shared-pool scope. Do not change .env/authentication contracts or assume all sessions/tokens share one expiry. |
| **A23 — Notifications**<br>Notification coverage/reliability gap<br>Logistics | Every status change notifies exactly the involved roles. | Rider after-commit observer is best effort; selected Buyer item/Seller delivery events exist. At Hub, reassignment, RTS, Lost and Damaged chains are missing/incomplete.<br>[NOTIFY](#evidence-notify), [PARCEL](#evidence-parcel), [SELLERNOTICE](#evidence-sellernotice) | LOG-TRK-004 | Modify approved event coverage using existing models with deduplication and defined recipients. Agree delivery guarantees/retries; status success must not be reported failed merely because post-commit notification failed. |
| **A24 — COD remittance**<br>Missing separate COD domain<br>Logistics | Full COD remitted to Logistics by end of day; owning Logistics confirms; flag differences. | Existing Admin collections settle Seller delivery charges and Rider pay, not merchandise COD. There is no Rider COD collection/remittance ledger.<br>[SETTLE](#evidence-settle), [ADMIN](#evidence-admin), [FINANCE](#evidence-finance) | LOG-COD-001, LOG-COD-002, LOG-COD-003 | Clarify Finance ownership first. Recommend a separate owned COD ledger/workflow, keeping current Admin fee endpoints unchanged. Difference = remitted minus collected; Rider pay is never automatically netted against COD. |
| **A25 — Text field limits**<br>Confirmed validation differences + field-class ambiguity<br>Logistics | Free-text reasons10-300; chat1-1000; Store name3-30/description500 and Product name5-120/description20-3000. | Rider reject/deactivate validate no reason; fail only required string; Logistics chat max4000. Store profile name150/description2000; Product name255 and nullable unbounded description differ.<br>[REJECT](#evidence-reject), [DEACTIVATE](#evidence-deactivate), [DELIVER](#evidence-deliver), [CHAT](#evidence-chat), [STOREPROFILE](#evidence-storeprofile), [PRODUCTUPDATE](#evidence-productupdate) | LOG-RIDER-002, LOG-RIDER-004, LOG-TRK-002 | Recommend approved reason validation/persistence for Logistics; distinguish coded failure Refused (seven chars) from free-text explanations. Preserve existing chat/catalog limits until separate owner decisions; no global field-limit sweep. |
| **A26 — Voucher code rule**<br>Confirmed broader voucher-code difference<br>Commerce | 4-12 alphanumeric, globally unique, uppercase, case-insensitive entry. | Seller voucher validator uses max30/alpha_dash and Seller-scoped uniqueness; store/update uppercase. This differs in length/alphabet/scope even though casing partly agrees.<br>[VOUCHER](#evidence-voucher) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve current Commerce contract for Logistics; reconcile the global voucher namespace separately and do not assume Test Data codes prove validation compliance. |
| **A27 — Editing vouchers after claim**<br>Confirmed broader post-claim edit difference<br>Commerce | After claim only extend expiry/increase limit; ending invalidates unused wallets but keeps historical orders. | Seller voucher update accepts the general validated payload and destroy deletes the voucher; those actions do not enforce the described post-claim field locks.<br>[VOUCHERUPDATE](#evidence-voucherupdate), [VOUCHERDELETE](#evidence-voucherdelete) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify/modify through Commerce separately; preserve historical orders/RTS references and do not reset vouchers as part of Logistics test preparation. |
| **A28 — Store sales**<br>Missing broader promotion contract<br>Commerce | No overlapping store sales; active edits/new orders only; End Now preserves order snapshots. | Product price/old_price do not implement a scheduled store-sale/overlap engine in inspected Product/checkout paths.<br>[PRODUCT](#evidence-product), [ITEMPRICE](#evidence-itemprice) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Clarify with Commerce; defer promotion development. Preserve existing order snapshots when preparing any future approved price/rate change. |
| **A29 — Low-stock alert**<br>Confirmed broader alert-semantics difference<br>Commerce | Alert once crossing stock&lt;=5; rearm only after rising above5. | Seller low-stock service uses configurable threshold (default5) and12-hour duplicate suppression, not explicit threshold-crossing/rearm state.<br>[LOWSTOCK](#evidence-lowstock) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve existing alert service for Logistics; Commerce must decide crossing versus cooldown. RTS restock must not invent notifications or reset alert state to force test results. |
| **A30 — Address book**<br>Confirmed broader address-rule difference; snapshots compatible<br>Commerce / shipment destination | At most10 addresses; cannot delete default until choosing another; historical destinations fixed. | Address store has no10-address guard; deleting default chooses another automatically. Order shipping_address and Parcel delivery_address are snapshotted.<br>[ADDRESSSTORE](#evidence-addressstore), [ADDRESSDELETE](#evidence-addressdelete), [PICKUPCREATE](#evidence-pickupcreate) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve historical destination snapshots. Address limit/default UX need separate Buyer approval; do not use current profile address to reinterpret existing shipments. |
| **A31 — Return and refund requests**<br>Confirmed customer-return difference; separate RTS scope<br>Commerce / Finance / RTS | Coded reason,10-500 description,1-5 JPG/PNG5MB photos;3-day response/escalation; refund original method or COD refund account. | Buyer customer-return request permits arbitrary reason, optional details max1500 and optional evidence; Seller inspection offers several refund methods and optional restock. No complete specified3-day automation is declared.<br>[RETURNREQUEST](#evidence-returnrequest), [SELLERRETURN](#evidence-sellerreturn), [CONSOLE](#evidence-console) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Preserve customer-return UI/API for this scope. Clarify reuse versus a separate failed-delivery RTS adapter and payment authority; do not claim a refund ledger entry proves external money movement. |
| **A32 — Proof of delivery**<br>Missing delivery-verification contract<br>Logistics | Delivered needs photo plus buyer-visible six-digit OTP at Out for Delivery; five wrong attempts lock job15minutes. | Rider delivery requires a valid private photo, but no delivery OTP. Email registration OTP is a separate security domain.<br>[DELIVER](#evidence-deliver), [PROOF](#evidence-proof) | LOG-HUB-003 | Recommend modifying future delivery completion after approval: leg-scoped one-use OTP, photo retained, five-attempt/15-minute job lock. Clarify expiry/resend/offline fallback and package versus item OTP; never reuse registration challenge or bypass proof. |
| **A33 — Rider pay**<br>Confirmed Rider-pay policy conflict<br>Logistics | PHP50 per successful delivery, PHP30 per pickup, no failure pay; COD does not reduce pay. | Current seller-shipment fee allocates PHP40 to Rider only after all relevant parcels deliver; PHP10 goes to Logistics. No separate pickup earning is recognized here.<br>[FINANCE](#evidence-finance), [SHIPDISPATCH](#evidence-shipdispatch) | None directly referenced in this Logistics workbook; broader fixture/cross-module dependency only. | Recommend preserving existing PHP40 per Seller shipment, with no new pickup fee. Finance/team must approve any alternative pay unit/rate/reversal policy. Keep COD distinct; do not relabel package fee as per-item pay. |
| **A34 — Dispatch and reassignment**<br>Confirmed reassignment/suspension conflict + auto-dispatch gap<br>Logistics | Nearest eligible rider; reassign only before pickup; suspension releases unpicked jobs and retains already-picked-up work. | No reassignment action; assigned parcel guard rejects reassignment, shipment binding enforces same Rider, pickup and last-mile IDs differ. Deactivation retains all jobs and blocks operational middleware.<br>[ASSIGN](#evidence-assign), [SHIPASSIGN](#evidence-shipassign), [SHIPDISPATCH](#evidence-shipdispatch), [DEACTIVATE](#evidence-deactivate), [RIDERGUARD](#evidence-riderguard), [PICKUP](#evidence-pickup) | LOG-RIDER-003, LOG-DISP-001, LOG-DISP-005, LOG-DISP-006 | Clarify per-leg cutoff and emergency in-hand handover. Recommend preserving two legs and whole-shipment consistency, with explicit authorized atomic reassignment and a narrow approved completion policy for retained in-hand work. |
| **A35 — Audit log**<br>Missing full audit contract<br>Logistics | Overrides, Rider approvals/suspensions, rate changes and COD confirmations record actor/time/old-new/reason; UI cannot edit/delete. | Item histories have status/note/time; financial settlements have recorded_by/reference. No unified matching Logistics audit view/event contract. Approval/reactivation test steps supply no reason despite broad wording.<br>[HISTORY](#evidence-history), [REJECT](#evidence-reject), [SETTLE](#evidence-settle), [NAV](#evidence-nav) | LOG-RIDER-001, LOG-RIDER-003, LOG-RIDER-005, LOG-TRK-003, LOG-RATE-001, LOG-COD-003 | Recommend adding append-only application audit events/view after approval, without replacing existing histories. Decide which actions require user reason; do not fabricate an operator reason for simple Approve/Reactivate. |
| **A36 — Report export**<br>Missing export contract + dataset ambiguity<br>Logistics | Delivery/COD reports export CSV and XLSX with same-filter screen rows/totals. | Current delivery reports are sorted_at-based JSON/chart analytics; no exports/COD source. Paginated rows and full-filter totals are not the same dataset by default.<br>[REPORT](#evidence-report), [REPORTUI](#evidence-reportui), [TRACK](#evidence-track) | LOG-COD-004, LOG-COD-005 | Recommend both formats after source definitions; all matched rows across pages, agreed timestamps/timezone, consistent totals, empty output and safe spreadsheet text. XLSX writer/dependency requires separate technical approval if needed. |
| **A37 — Duplicate submissions**<br>Partial idempotency; replay-contract ambiguity<br>Logistics | Double-click/Enter/Back/refresh never duplicates creates/charges. | Locks/state guards, registration keys, proof reuse and financial settlement keys exist, but arrival replay returns status422 and no durable hub event; missing future COD/offer/RTS actions have no replay contract.<br>[ARRIVE](#evidence-arrive), [ASSIGN](#evidence-assign), [PROOF](#evidence-proof), [SETTLE](#evidence-settle) | LOG-HUB-004 | Preserve working guards/keys. Define per-operation scoped replay keys, stable success/already-done response and transactionally deduplicated events/charges; do not globally dedupe legitimate repeated attempts or reasons. |

## Proposed resolutions and questions requiring owner approval

A proposed default below is deliberately concrete enough to review. **No answer is assumed from silence.** Owners are project roles, not messages sent to anyone.

### D01 — Pricing, funding and Rider pay

- **Status:** Pending approval. **Owner:** Project owner + Finance + Commerce.
- **Assumptions:** A8, A9, A20, A33. **Affected IDs:** LOG-RATE-001, LOG-RATE-002, LOG-RATE-003, LOG-RATE-004.
- **Additional impact:** A33 has no direct Logistics case; it constrains any new pay/COD design and the Test Data values.
- **Recommended resolution:** Preserve buyer shipping PHP0 and the delivered Seller-shipment PHP50 fee, split PHP10 Logistics/PHP40 Rider. Keep marketplace commission and voucher accounting separate from the 20% courier share. Do not add a PHP30 pickup fee or change historical orders/ledgers.
- **Team must decide:** Must L7 implement buyer-paid weight/zone pricing, or will the team approve revised acceptance expectations while retaining seller funding? Is the current package pay model still authoritative?
- **Consequence / acceptance requirement:** Preserving pricing cannot make the original PHP55 buyer-checkout expectation true. Record an explicit approved deviation/new acceptance version; the original workbook stays unchanged and these cases stay Not Run until the chosen contract is tested.
- **Source:** [CHECKOUT](#evidence-checkout), [CONFIG](#evidence-config), [FINANCE](#evidence-finance), [SELLERDASH](#evidence-sellerdash), [SELLERREPORT](#evidence-sellerreport).

### D02 — What exactly is a shipment, parcel and active job?

- **Status:** Pending approval. **Owner:** Backend + Rider + Logistics + QA + Finance.
- **Assumptions:** A10, A14, A33, A34. **Affected IDs:** LOG-RIDER-006, LOG-DISP-002, LOG-DISP-004, LOG-DISP-005, LOG-DISP-006, LOG-DISP-007.
- **Additional impact:** Also governs dashboard count units and whether a package gets one or several OTPs/fees.
- **Recommended resolution:** Preserve the SellerShipment aggregate: order+seller owns item parcels, one center/rider per dispatched shipment. Recommend counting one shipment per active leg as a capacity job, with explicit legacy-parcel fallback. Use five distinct one-parcel shipments for the workbook 3-assigned/2-skipped fixture.
- **Team must decide:** Confirm package versus item capacity, whether pickup/delivery reservations count together, and when capacity is released. Does bulk assignment operate on whole packages?
- **Consequence / acceptance requirement:** A naive three-item-parcel cap can strand a four-item SellerShipment: dispatch requires every relevant member assigned/ready. Splitting members between riders conflicts with finance invariants. Bulk assignment/reassignment must preserve complete package consistency; this recommendation is not yet an accepted rule.
- **Source:** [SCHEMA](#evidence-schema), [PICKUPCREATE](#evidence-pickupcreate), [SHIPASSIGN](#evidence-shipassign), [SHIPDISPATCH](#evidence-shipdispatch), [FINANCE](#evidence-finance), [ELIG](#evidence-elig).

### D03 — Two legs, status names and reassignment cutoff

- **Status:** Pending approval. **Owner:** Logistics + Rider + Backend + QA.
- **Assumptions:** A10, A34. **Affected IDs:** LOG-DISP-002, LOG-DISP-005, LOG-DISP-006, LOG-DISP-008, LOG-HUB-001, LOG-HUB-003, LOG-HUB-006, LOG-RTS-002.
- **Recommended resolution:** Preserve separate pickup and last-mile riders and existing API state compatibility. Display Assigned from the relevant leg assignment and At Hub from AT_SORTING_CENTER plus durable events. Allow normal hub handover; reassignment cutoff applies within the agreed leg.
- **Team must decide:** Does before pickup mean Seller pickup or collection from hub for the last-mile leg? What may a privileged operator do after physical possession changes?
- **Consequence / acceptance requirement:** A global ban after the first pickup would block normal last-mile assignment. Reassignment must atomically update all affected undisbursed shipment members; never patch only one parcel/rider FK or rewrite old Delivered/FAILED_RETURNED history.
- **Source:** [PICKUP](#evidence-pickup), [ARRIVE](#evidence-arrive), [ASSIGN](#evidence-assign), [SHIPASSIGN](#evidence-shipassign), [SHIPDISPATCH](#evidence-shipdispatch), [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel).

### D04 — Online, suspension and safe handling of in-hand work

- **Status:** Pending approval. **Owner:** Logistics + Rider + Security/auth owner.
- **Assumptions:** A1, A14, A34. **Affected IDs:** LOG-RIDER-001, LOG-RIDER-002, LOG-RIDER-003, LOG-RIDER-004, LOG-RIDER-005, LOG-RIDER-006, LOG-DISP-003, LOG-DISP-004, LOG-DISP-005.
- **Recommended resolution:** Keep approval/is_active distinct from live Online presence. Suspend new offers/acceptance, release only uncollected work, and define a narrowly authorized route for completing or transferring already-held parcels. Preserve all tenant/verification checks.
- **Team must decide:** Can a suspended Rider finish an already-picked-up parcel, or must Logistics arrange an audited handover? How are reconnects/location freshness and reactivation represented?
- **Consequence / acceptance requirement:** Current is_active=false middleware blocks operational actions while deactivation leaves assignments attached. A completion exception is an authentication/authorization change requiring explicit review; do not globally weaken rider.approved.
- **Source:** [DEACTIVATE](#evidence-deactivate), [ACTIVATE](#evidence-activate), [RIDERMODEL](#evidence-ridermodel), [RIDERGUARD](#evidence-riderguard), [PICKUP](#evidence-pickup), [ASSIGN](#evidence-assign).

### D05 — Nearest offers, reservations and timeout mechanics

- **Status:** Pending approval. **Owner:** Logistics + Rider + Backend/operations.
- **Assumptions:** A14, A34, A37. **Affected IDs:** LOG-DISP-001, LOG-DISP-003, LOG-DISP-004, LOG-DISP-007.
- **Recommended resolution:** Add automatic offers only if explicitly in scope; retain manual dispatch fallback. Recommend authenticated same-company/area eligibility, reserved capacity, fresh coordinates, deterministic distance/tie rules, 60-second expiry and fallback after three distinct riders.
- **Team must decide:** Which pickup/delivery leg is auto-assigned; what supplies coordinates and nearest distance; what happens with missing/stale GPS, a tie, acceptance at expiry or no eligible rider? What worker/scheduler is supported?
- **Consequence / acceptance requirement:** Current eligible list is name-ordered, not nearest, and pickup is self-accepted. Offers need atomic expiry/acceptance/capacity semantics and an agreed operational worker; no geolocation service, dependency or scheduler is being installed.
- **Source:** [ELIG](#evidence-elig), [PICKUP](#evidence-pickup), [ROUTES](#evidence-routes), [RIDERMODEL](#evidence-ridermodel), [CONSOLE](#evidence-console).

### D06 — Failure attempts, refusals and truthful RTS milestones

- **Status:** Pending approval. **Owner:** Logistics + Rider + Buyer/Seller owners.
- **Assumptions:** A10, A13. **Affected IDs:** LOG-RTS-001, LOG-RTS-002, LOG-RTS-003, LOG-RTS-004, LOG-HUB-003, LOG-TRK-004.
- **Recommended resolution:** After approval, add per-leg/per-parcel attempt events, enumerated failure reasons, up to three failures, immediate refusal escalation and separate RTS requested/in-transit/received milestones. Keep legacy FAILED_RETURNED facts without inventing historical attempts.
- **Team must decide:** Does Refused immediately start physical RTS or merely enter an RTS queue; who authorizes retry and resets Out for Delivery; how do partial-package failures work?
- **Consequence / acceptance requirement:** A failure is not proof of return-to-Seller. Do not mark Returned to Sender, cancel/restock or start a refund solely because one fail request occurred. Financial behavior follows D07.
- **Source:** [DELIVER](#evidence-deliver), [PARCEL](#evidence-parcel), [SCHEMA](#evidence-schema), [RETURNS](#evidence-returns).

### D07 — RTS stock, refund, cancellation and earning boundaries

- **Status:** Pending approval. **Owner:** Finance + Commerce + Logistics.
- **Assumptions:** A3, A6, A8, A11, A15, A21, A31. **Affected IDs:** LOG-RTS-001, LOG-RTS-002, LOG-RTS-003, LOG-RTS-004.
- **Recommended resolution:** Recommend exactly-once Seller receipt/restock for the approved RTS path, no early stock restoration, and refunds based on the actual paid allocation of returned items. Preserve ordinary cancellation/customer returns and all posted financial history; use approved adjustment/reversal records if required.
- **Team must decide:** Is full amount an item amount, Seller package amount or entire multi-Seller order? Which discounts/taxes/payment charges are refundable; who initiates actual payment refund; what happens to paid courier fees, merchandise earnings, partial RTS and vouchers?
- **Consequence / acceptance requirement:** Ordinary cancellation already restocks; customer-return restock is optional. Reusing either blindly can double-restock or change unrelated policies. A local refund record is not proof of external payment movement. Buyer/Seller cancellation/return/payout gaps are separate owner work, not permission to rewrite them during Logistics implementation.
- **Source:** [BUYERCANCEL](#evidence-buyercancel), [SELLERCANCEL](#evidence-sellercancel), [RETURNREQUEST](#evidence-returnrequest), [SELLERRETURN](#evidence-sellerreturn), [PAYOUT](#evidence-payout), [FINANCE](#evidence-finance), [VOUCHERUSE](#evidence-voucheruse).

### D08 — Delivery OTP, proof image limits and offline delivery

- **Status:** Pending approval. **Owner:** Logistics + Buyer + Rider + Security.
- **Assumptions:** A16, A32. **Affected IDs:** LOG-HUB-003, LOG-TRK-005.
- **Recommended resolution:** Recommend preserving private accessible photo proof and adding a separate one-use delivery-leg OTP only after approval. Use five wrong attempts/15-minute job lock if accepted; recommend new Logistics images JPG/PNG up to5 MiB, with content validation. Never reuse registration OTP.
- **Team must decide:** OTP per package or item; generation/expiry/resend rules; who can view it; offline Buyer/Rider alternative; effect of reassignment/failed attempt; MB versus MiB? Any exception must be explicitly authorized/audited.
- **Consequence / acceptance requirement:** The workbook defines attempt lock but not OTP TTL/channel/resend/fallback. Registration five-minute TTL cannot be silently copied. Global image rules across Store/Review/Chat are separate policies; no automatic reduction of all modules to5 MiB.
- **Source:** [PROOF](#evidence-proof), [DELIVER](#evidence-deliver), [PRODUCTUPDATE](#evidence-productupdate), [BUYERREVIEW](#evidence-buyerreview).

### D09 — Reasons, coded failures, audit and notification delivery

- **Status:** Pending approval. **Owner:** Logistics + Backend + Security + QA.
- **Assumptions:** A13, A23, A25, A35, A37. **Affected IDs:** LOG-RIDER-001, LOG-RIDER-002, LOG-RIDER-003, LOG-RIDER-004, LOG-RIDER-005, LOG-TRK-002, LOG-TRK-003, LOG-TRK-004, LOG-TRK-005, LOG-RATE-001, LOG-COD-003.
- **Recommended resolution:** Recommend trimmed10-300-character free-text rejection/suspension/override reasons, safe text rendering, append-only actor/time/old-new/action/reason events, defined recipients and deduplicated notifications. Keep coded failure selection separate: Refused is seven characters, not an invalid free-text reason.
- **Team must decide:** Must Approve/Reactivate require typed reasons (their cases provide none)? Are failed attempts or only committed transitions audited? Is notification delivery best effort or reliably retried? Who may view/export audit data?
- **Consequence / acceptance requirement:** Current reasons/generic account notices and status-only histories do not meet the whole contract. Do not fabricate an operator reason, conflate notifications with audit, add UI edit/delete or fail an already-committed business action because post-commit notification delivery failed.
- **Source:** [REJECT](#evidence-reject), [DEACTIVATE](#evidence-deactivate), [DELIVER](#evidence-deliver), [NOTIFY](#evidence-notify), [HISTORY](#evidence-history), [SETTLE](#evidence-settle), [CHAT](#evidence-chat).

### D10 — COD ownership, money source, signed difference and cutoffs

- **Status:** Pending approval. **Owner:** Finance + Logistics + Security.
- **Assumptions:** A24, A33, A35, A37. **Affected IDs:** LOG-COD-001, LOG-COD-002, LOG-COD-003.
- **Recommended resolution:** Recommend a distinct owned Rider COD ledger; only approved owning Logistics confirms COD if that authority is accepted. Keep existing Admin fee collection/Rider payout intact. Report difference=remitted-collected (PHP1750-PHP1800=-PHP50); keep Rider pay separate from cash custody.
- **Team must decide:** What proves cash was collected (not merely Delivered); what day/timezone/cutoff; partial/late/over-remittance/refund handling; who resolves shortfall; how is actual received cash authenticated?
- **Consequence / acceptance requirement:** Never relabel Seller delivery-fee collection as COD or grant Logistics access to existing Admin settlement APIs. Confirmed balances/audit must be atomic/idempotent; cash deposits and payment-provider integrations are not implied by this workbook.
- **Source:** [SETTLE](#evidence-settle), [ADMIN](#evidence-admin), [FINANCE](#evidence-finance), [ROUTES](#evidence-routes).

### D11 — Region zones, city coverage, serviceability and rate versions

- **Status:** Pending approval. **Owner:** Logistics + Commerce + Finance + QA.
- **Assumptions:** A20, A35. **Affected IDs:** LOG-RATE-001, LOG-RATE-002, LOG-RATE-003, LOG-RATE-004, LOG-HUB-002.
- **Recommended resolution:** Preserve canonical city_code ownership/coverage. Recommend grouping cities into display zones and adding approved checkout serviceability independently of buyer pricing. If rates are approved, persist quote/version and effective-time snapshots rather than changing old orders.
- **Team must decide:** Is NCR a display group or a priced operational zone? Does coverage depend on both Seller pickup and Buyer destination/available partners? What is checkout started (quote creation), quote TTL and behavior when price/coverage changes mid-checkout?
- **Consequence / acceptance requirement:** Current Area is a city membership, not a priced regional zone. Checkout uses free-text address; require a reviewed canonical-address/legacy fallback design. No substring guess of city/region or retroactive order-price update. D01 decides whether any monetary rate engine is in scope.
- **Source:** [COVERAGE](#evidence-coverage), [COVERAGESCHEMA](#evidence-coverageschema), [SORT](#evidence-sort), [CHECKOUTSTORE](#evidence-checkoutstore), [CHECKOUT](#evidence-checkout), [CONFIG](#evidence-config).

### D12 — Dashboard/report count unit, date event and export dataset

- **Status:** Pending approval. **Owner:** Logistics + QA + Finance for COD.
- **Assumptions:** A10, A36. **Affected IDs:** LOG-DASH-001, LOG-DASH-002, LOG-DASH-003, LOG-DASH-004, LOG-COD-004, LOG-COD-005.
- **Recommended resolution:** Preserve useful operational queue cards and explicitly map/add approved shipment metrics. Recommend Asia/Manila inclusive business-day ranges, with named event time per metric. Export all matched filtered records across pages with totals from the same read model; support CSV/XLSX and empty output if accepted.
- **Team must decide:** Count parcels, package jobs or parent orders; Pending union of which legs; dashboard date by created/received/started/delivered; sorting-activity versus delivery-date reports; entire matched set versus visible page?
- **Consequence / acceptance requirement:** Current reports use sorted_at and dashboard has no range/Delivered/Failed card. Full-filter totals cannot be reconciled to only the first page. Keep sorting analytics clearly labelled; define formula-like spreadsheet text and exact money/time formats. XLSX writer/dependency choice is a separate approval if needed.
- **Source:** [DASH](#evidence-dash), [DASHUI](#evidence-dashui), [TRACK](#evidence-track), [REPORT](#evidence-report), [REPORTUI](#evidence-reportui), [SHIPDISPATCH](#evidence-shipdispatch).

### D13 — Session expiry and unclaimed-company pool

- **Status:** Pending approval. **Owner:** Security/auth owner + Logistics.
- **Assumptions:** A22. **Affected IDs:** LOG-DASH-004, LOG-RIDER-007, LOG-HUB-007.
- **Recommended resolution:** Preserve ownership, email verification, approval and private-document guards. Clarify shared unclaimed pickup visibility and idle/absolute expiry separately. Do not infer30-minute idle enforcement from a two-hour token cap.
- **Team must decide:** Is the30-minute policy for web sessions, API tokens, all clients or only operator UI? Do token requests refresh idle time? Can any center see an unclaimed pickup or only centers with matching coverage?
- **Consequence / acceptance requirement:** Effective session/global token settings were not read from .env/MySQL. API login creates tokens without explicit per-token expiry. The original three ownership cases do not test idle expiry; add separate acceptance checks, and do not call their Pass a timeout verification.
- **Source:** [SESSION](#evidence-session), [WEB](#evidence-web), [RIDERLOGIN](#evidence-riderlogin), [CENTERLOGIN](#evidence-centerlogin), [DASH](#evidence-dash), [CENTERGUARD](#evidence-centerguard).

### D14 — Replay semantics and legacy API compatibility

- **Status:** Pending approval. **Owner:** Backend + QA + Rider/Commerce consumers.
- **Assumptions:** A37. **Affected IDs:** LOG-HUB-004, LOG-DISP-005, LOG-DISP-007, LOG-RTS-003, LOG-COD-003.
- **Recommended resolution:** Keep existing transaction/locking/proof/settlement guards. Recommend operation+actor+entity-scoped request keys/event identities; safe replay returns existing result or a clear already-done message without another event/charge.
- **Team must decide:** Which existing response codes must stay compatible; does a scan replay return success/already received or a conflict; what denotes a legitimate next failure attempt rather than a replay; key expiry and concurrent request behavior?
- **Consequence / acceptance requirement:** Idempotency is not global payload deduplication. Repeated identical reason text can be a legitimate later attempt. Preserve legacy endpoints until clients accept any changed response/auth contract.
- **Source:** [ARRIVE](#evidence-arrive), [PROOF](#evidence-proof), [SETTLE](#evidence-settle), [ASSIGN](#evidence-assign).

## Worksheet requirements without a sufficient assumption reference

These also need resolution; the absence of an A-number is not approval.

| Topic | Affected IDs | Ambiguity and recommended resolution |
|---|---|---|
| Four shipment counters / range dates | LOG-DASH-001, LOG-DASH-002, LOG-DASH-003 | Current queue cards differ from workbook statuses. Define package/item count, Pending membership and event timestamp under D02/D12 before changing totals. Preserve useful existing queue views. |
| NCR zone versus canonical city area | LOG-HUB-002 | ADDR-1 is Quezon City, while workbook says NCR zone. Recommend approved regional grouping above existing city_code memberships; grouping is not a price or permission to assign a wrong destination city. D11. |
| Search by order/buyer versus tracking reference | LOG-TRK-001 | parcels.order_id is ITEM-&lt;item id&gt;, not the parent orders.id/order_number. Decide exact tracking/parent-order lookup, buyer matching and pagination with tenant scope; no fake TRK prefix requirement. D02/D03. |
| Manual Delivered override and damaged/lost evidence | LOG-TRK-002, LOG-TRK-003, LOG-TRK-004, LOG-TRK-005 | Who can bypass Rider completion, and is photo/OTP still mandatory? Recommend privileged explicit transitions with required reason/audit, private evidence and separately approved claims/refunds. D07/D08/D09. A phone statement is not automatically valid delivery proof. |
| Bulk partial success | LOG-DISP-007 | Exactly3 assigned/2 skipped only has meaning for five distinct capacity jobs; use five one-parcel shipments. Whole multi-item packages must not be partially assigned so they can never dispatch. D02/D05. |
| Coverage rejection before order mutation | LOG-RATE-003 | Current checkout free-text address has no coverage check. Agree pickup/destination/partner availability and legacy address mapping. Reject before order/stock/voucher mutations; serviceability can remain free shipping. D11. |
| Rate0 versus free shipping | LOG-RATE-002 | Workbook rejects a zero monetary rate, but current buyer shipping is legitimately zero. Distinguish a future positive tariff record from free buyer checkout/funding. Do not reject existing buyer orders merely because shipping isPHP0. D01/D11. |
| Shared fictional Test Data | All44 fixture setups; especially LOG-RATE-001, LOG-RATE-004, LOG-RTS-003, LOG-COD-001, LOG-COD-002 | Accounts/states/rates/variant prices are not existing DB facts. ProductVariant has no per-variant price field, and the worked checkout's shipping/voucher totals conflict with current pricing. Approve isolated representable fixtures/versioned expected totals; never reset live stock. |

## Prioritized implementation plan by Logistics module

This is a **conditional plan**, not permission to create migrations, change contracts or start development. Priority: **P0** owner decisions; **P1** core/safe integration foundations; **P2** new cross-role operational workflows; **P3** optional monetary redesign/exports once source data is stable. Priority is dependency order, not a promise that an optional feature is required.

Estimated complexity: **S** local validation/read change; **M** bounded UI/API/test integration; **L** shared state/auth/event/schema work; **XL** financial or asynchronous cross-role workflow with concurrency and recovery. These are relative estimates, not elapsed-time commitments; unresolved decisions can change scope.

### P0 — Requirement sign-off

Approve D01-D14 (or explicit alternatives), assign owners and publish a versioned acceptance supplement. Keep old workbook/test evidence intact. Select which broader Commerce issues are dependencies needing separate tickets and which are deferred. No financial, approval/auth or state-machine assumption becomes authoritative because a spreadsheet contains it.

### P1 — Shared foundation before dependent modules

- Specify identities (order/Seller shipment/item parcel/leg/job), legal transitions, event timestamps and original-ID traceability. Preserve current order→shipment→member/parcel finance lock order; any Rider/capacity locks need an explicitly reviewed compatible order.
- Design reused notification/read models plus actor audit, idempotency/replay and owned private evidence. Decide whether reliable notification delivery needs new persistence/worker support; do not introduce infrastructure implicitly.
- Propose only the schema changes needed by accepted decisions; review compatibility/backfill for legacy records. **Writing migration files and executing them are separate future actions needing approval; neither occurs here.**
- Add a small approved regression/reproduction tranche first: whitespace-normalized lookup/confirm, reason boundaries, owned-role denials and safe duplicate scans. Original workbook status stays Not Run until behavior is actually exercised and every expected result checked.

| Module / original case IDs | Priority / estimated complexity | Dependencies and decision gates | Proposed work after approval | Acceptance evidence required | Likely schema / approval impact |
|---|---|---|---|---|---|
| **L1 Dashboard**<br>LOG-DASH-001, LOG-DASH-002, LOG-DASH-003, LOG-DASH-004 | P1<br>**M** | D02, D03, D12, D13. Approved count/state/time contract; shared event/read model. Final reconciliation follows L3/L4 event wiring. | Map/add shipment counters and inclusive date filter while retaining operational cards. Scope parcel totals to company; shared unclaimed pickup pool needs explicit policy. Refresh through real delivery events. | Exact UI/API/list totals across all pages, same count unit/date basis, one status transition changes only relevant counters, zero/edge dates and two-company isolation. | May reuse timestamps/read models; new hub events come from shared/L4 design. |
| **L2 Rider Management**<br>LOG-RIDER-001, LOG-RIDER-002, LOG-RIDER-003, LOG-RIDER-004, LOG-RIDER-005, LOG-RIDER-006, LOG-RIDER-007 | P1<br>**L** | D04, D09, D13. Shared event/audit/eligibility contract; approved suspension completion policy. Release/reassignment integrates with L3. | Wire owned rider/document review screen; reason bounds/persistence; live presence/workload/today counts; approval/rejection/suspension/reactivation and exact notices/audit. | Owned review/denial,10/300 boundaries, approved versus Online distinction, in-hand/uncollected jobs, no new jobs on suspension and authorization race cases. | Presence, reasons/audit may require new fields/events. Auth changes and schema proposal require review before editing. |
| **L3 Dispatch**<br>LOG-DISP-001, LOG-DISP-002, LOG-DISP-003, LOG-DISP-004, LOG-DISP-005, LOG-DISP-006, LOG-DISP-007, LOG-DISP-008 | P1 manual/capacity/reassignment; P2 nearest offers<br>**L for manual tranche; XL for offer engine** | D02, D03, D04, D05, D09, D14. L2 canonical eligibility/presence; shared idempotency/events; whole-shipment lock order. Geo source and expiry worker approved before auto-dispatch. | First package-aware manual capacity/bulk/reassignment with ready guards; then nearest reservations/offers, expiry/decline/fallback. Preserve pickup self-accept/last-mile contracts until an approved replacement exists. | Exactly3/2 on five distinct test jobs, no split/stranded4-item package, concurrent capacity/expiry/suspension, both Rider timelines and denied post-cutoff mutations. | Likely jobs/reservations/offers and event storage; scheduling/runtime support is separate deployment work. |
| **L4 Hub Operations**<br>LOG-HUB-001, LOG-HUB-002, LOG-HUB-003, LOG-HUB-004, LOG-HUB-005, LOG-HUB-006, LOG-HUB-007 | P1<br>**L** | D03, D08, D09, D11, D14. Shared event/time/duplicate contract; existing/manual L3 assignment; canonical city-to-zone mapping; separate delivery OTP approval. | Normalize reference lookup/confirmation, persist received/outbound events and Buyer milestones, add clear duplicate feedback, agree zone display, wire outbound/start and delivery OTP. | Unknown/foreign/out-of-order scans unchanged, one receipt event/time on replays, matching city/region group, OTP visibility/replay/lock with accessible private photo. | Hub/attempt/OTP event fields or tables likely; preserve existing sorted_at meaning and legacy history. |
| **L5 Tracking & Exceptions**<br>LOG-TRK-001, LOG-TRK-002, LOG-TRK-003, LOG-TRK-004, LOG-TRK-005 | P1 search; P2 override/Lost/Damaged<br>**M search; XL exception/claim integration** | D03, D07, D08, D09, D11, D14. Shared actor audit/idempotency; parent order versus tracking reference contract. Money/claim authority must be agreed before exception side effects. | Unified owned tracking/order/buyer search with pagination; approved override transition matrix; Lost/Damaged evidence, notifications and claim/refund initiation. | Exact/unknown/SQL-like text and cross-company results, required reason/photo, safe override permission, immutable UI audit and exactly-once claim/refund side effects. | Exception/action/audit/claim metadata likely; reuse private file and existing notification/refund boundaries, not an unreviewed new payment integration. |
| **L6 Returns / RTS**<br>LOG-RTS-001, LOG-RTS-002, LOG-RTS-003, LOG-RTS-004 | P2 after state/finance agreement<br>**XL** | D02, D03, D06, D07, D09, D14. Approved attempts/return states, L2/L3 job handling, L4 milestones, Commerce stock/cancellation/voucher and Finance refund/earning contracts. | Attempt reasons/threshold/refusal, RTS queue/reverse assignment, Seller receipt confirmation, exactly-once restock and approved prepaid refund/earning adjustments. | Three attempt reasons or immediate Refused, all-role timelines, stock19 until receipt then20 once, partial multi-item/multi-Seller returns, denied/duplicate receipt and no duplicate refund. | Attempts/RTS receipt/adjustment events likely. Customer-return changes, financial schema and migration execution need separate explicit approval. |
| **L7 Zones & Rates**<br>LOG-RATE-001, LOG-RATE-002, LOG-RATE-003, LOG-RATE-004 | P1 coverage/serviceability; P3 monetary engine only if approved<br>**M/L coverage integration; XL pricing redesign** | D01, D02, D09, D11, D12. Canonical Buyer/Seller destination/pickup interpretation, city/region model, Commerce approval. Monetary work is gated by preservation versus buyer-paid pricing decision. | Reuse city coverage; define regional groups and checkout serviceability. Only if approved: positive centavo rates, ceil(weight), versioned start-of-checkout quote and old-order protection. | Covered/uncovered/direct checkout with no stock/voucher writes on rejection; correct region membership; approved rate boundaries/new-old quote behavior and immutable historical totals. | Rate/quote versions and possibly canonical checkout address fields if approved; do not alter existing fee split/history as a side effect. |
| **L8 COD & Reports**<br>LOG-COD-001, LOG-COD-002, LOG-COD-003, LOG-COD-004, LOG-COD-005 | P2 COD ledger; P3 exports after data contracts<br>**XL COD; M/L CSV+XLSX exports** | D01, D07, D09, D10, D12, D14. Distinct collection/remittance authority and cash source, event/audit/idempotency foundation. Exports depend on stable delivery/COD read models; payment execution is not implied. | Add owned COD collection/remittance/reconciliation and safe confirmation/balances; keep fee/payout ledger separate. Add both export formats with matched date/filter semantics and no-data behavior. | PHP1800/1800 -&gt;0 and1800/1750 -&gt;-50, role/company denial, partial/late/refund rules, atomic duplicate confirmation, full multi-page export totals and readable empty files. | COD ledger/confirmation records likely; any XLSX dependency/new schema needs approval. No automatic offset against Rider earnings. |

### Suggested delivery sequence

1. **D01-D14 agreement → shared foundation**, then the smallest approved normalization/reason/ownership regressions.
2. **L2 Rider state/presence → L3 manual capacity/reassignment**. L4 hub events/OTP can follow existing manual assignment without waiting for the full nearest-offer engine.
3. **L1 canonical dashboard + L5 search**, using the shared identity/date/read contracts; **L7 coverage/serviceability** can proceed independently of a monetary rate engine after Commerce approves address integration.
4. **L3 automatic offers**, after geometry/worker/reservation decisions. **L5 audited exceptions + L6 RTS**, after state/proof/stock/refund authorities are settled.
5. **L8 COD**, after a separate cash ledger/confirmation contract; **L7 monetary rates only if D01 opts into redesign**.
6. **L8 CSV/XLSX exports**, once delivery/COD filters/totals stabilize, then the complete44-case run and separately approved real-engine/device acceptance.

Preserving the current financial baseline reduces implementation risk but leaves the workbook's conflicting monetary expectations unresolved until the team approves an explicit deviation. Conversely, adopting the original weight-rate/pay/COD assumptions increases cross-role/schema/migration scope and must be approved as such. No fake Pass, hidden expected-result edit or historical ledger rewrite is an acceptable shortcut.

## Approval checklist and next action

| Gate | Recommended answer to review | Current status |
|---|---|---|
| Financial baseline (D01) | PreservePHP0 buyer shipping andPHP50 package fee splitPHP10/PHP40; defer new pickup pay and price engine unless explicitly approved. | Pending |
| Job unit / dispatch legs (D02/D03) | One Seller shipment per active leg; preserve two riders/legs and whole-package invariants; five distinct jobs for bulk fixture. | Pending |
| Rider state / nearest / in-hand work (D04/D05) | Distinct approval, suspension and live presence; explicit limited in-hand-work policy and approved geo/worker source. | Pending |
| RTS / stock / refunds (D06/D07) | Future attempt ledger and truthful return milestones; exactly-once Seller receipt/restock; explicit paid-amount allocation/financial authority. | Pending |
| OTP / proof / reasons / audit (D08/D09) | Separate delivery OTP; retained private photo; approved units/limits; reason/action-specific validation and audited events. | Pending |
| COD (D10) | New cash-custody domain if accepted; Logistics-only owned COD confirmation, no change to Admin fee settlements or Rider-pay offsets. | Pending |
| Zones / metrics / export (D11/D12) | Keep city memberships; explicit quote/serviceability/date/count/data-set contracts; both exports only after data sources exist. | Pending |
| Sessions / tenancy / replay (D13/D14) | Preserve access boundaries; decide idle versus hard expiry/shared pool and compatible idempotent responses. | Pending |

**Wait for the user's approval or requested corrections to this recommended baseline before implementation.** Approving this requirements direction does not itself authorize production MySQL changes, running migrations, changing .env, commits/pushes or unrelated Commerce rewrites. A future batch should list its exact code/schema/test scope before work begins.

## Evidence index

Evidence is from the current local source snapshot. User-reported database state is not an independently audited MySQL schema; configuration defaults are not actual .env values. No credentials or fixture passwords are reproduced.

<a id="evidence-activate"></a>**ACTIVATE** — [app/Http/Controllers/Logistics/RiderManagementController.php:116](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L116)

<a id="evidence-addressdelete"></a>**ADDRESSDELETE** — [app/Http/Controllers/BuyerAddressController.php:169](../../../app/Http/Controllers/BuyerAddressController.php#L169)

<a id="evidence-addressstore"></a>**ADDRESSSTORE** — [app/Http/Controllers/BuyerAddressController.php:30](../../../app/Http/Controllers/BuyerAddressController.php#L30)

<a id="evidence-admin"></a>**ADMIN** — [routes/admin.php:27](../../../routes/admin.php#L27)

<a id="evidence-arrive"></a>**ARRIVE** — [app/Http/Controllers/Logistics/IncomingParcelController.php:14](../../../app/Http/Controllers/Logistics/IncomingParcelController.php#L14)

<a id="evidence-assign"></a>**ASSIGN** — [app/Http/Controllers/Logistics/ParcelAssignmentController.php:91](../../../app/Http/Controllers/Logistics/ParcelAssignmentController.php#L91)

<a id="evidence-buyercancel"></a>**BUYERCANCEL** — [app/Http/Controllers/BuyerOrderController.php:279](../../../app/Http/Controllers/BuyerOrderController.php#L279)

<a id="evidence-buyerreview"></a>**BUYERREVIEW** — [app/Http/Controllers/BuyerReviewController.php:107](../../../app/Http/Controllers/BuyerReviewController.php#L107)

<a id="evidence-cartvacation"></a>**CARTVACATION** — [app/Http/Controllers/BuyerCartController.php:55](../../../app/Http/Controllers/BuyerCartController.php#L55)

<a id="evidence-centerguard"></a>**CENTERGUARD** — [app/Http/Middleware/EnsureSortingCenterApproved.php:19](../../../app/Http/Middleware/EnsureSortingCenterApproved.php#L19)

<a id="evidence-centerlogin"></a>**CENTERLOGIN** — [app/Http/Controllers/Logistics/LoginSortingCenterController.php:37](../../../app/Http/Controllers/Logistics/LoginSortingCenterController.php#L37)

<a id="evidence-chat"></a>**CHAT** — [app/Http/Controllers/Logistics/MessageController.php:108](../../../app/Http/Controllers/Logistics/MessageController.php#L108)

<a id="evidence-checkout"></a>**CHECKOUT** — [app/Http/Controllers/BuyerOrderController.php:237](../../../app/Http/Controllers/BuyerOrderController.php#L237)

<a id="evidence-checkoutstore"></a>**CHECKOUTSTORE** — [app/Http/Controllers/BuyerOrderController.php:660](../../../app/Http/Controllers/BuyerOrderController.php#L660)

<a id="evidence-checkoutvacation"></a>**CHECKOUTVACATION** — [app/Http/Controllers/BuyerOrderController.php:781](../../../app/Http/Controllers/BuyerOrderController.php#L781)

<a id="evidence-config"></a>**CONFIG** — [config/checkout.php:9](../../../config/checkout.php#L9)

<a id="evidence-console"></a>**CONSOLE** — [routes/console.php:8](../../../routes/console.php#L8)

<a id="evidence-coverage"></a>**COVERAGE** — [app/Http/Controllers/DeliveryCoverageController.php:16](../../../app/Http/Controllers/DeliveryCoverageController.php#L16)

<a id="evidence-coverageschema"></a>**COVERAGESCHEMA** — [database/migrations/2026_10_12_000002_add_city_codes_to_service_areas.php:12](../../../database/migrations/2026_10_12_000002_add_city_codes_to_service_areas.php#L12)

<a id="evidence-dash"></a>**DASH** — [app/Http/Controllers/Logistics/DashboardController.php:15](../../../app/Http/Controllers/Logistics/DashboardController.php#L15)

<a id="evidence-dashui"></a>**DASHUI** — [resources/js/Pages/Logistics/Dashboard.vue:72](../../../resources/js/Pages/Logistics/Dashboard.vue#L72)

<a id="evidence-deactivate"></a>**DEACTIVATE** — [app/Http/Controllers/Logistics/RiderManagementController.php:149](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L149)

<a id="evidence-deliver"></a>**DELIVER** — [app/Http/Controllers/Logistics/RiderDeliveryController.php:133](../../../app/Http/Controllers/Logistics/RiderDeliveryController.php#L133)

<a id="evidence-elig"></a>**ELIG** — [app/Http/Controllers/Logistics/ParcelAssignmentController.php:48](../../../app/Http/Controllers/Logistics/ParcelAssignmentController.php#L48)

<a id="evidence-finance"></a>**FINANCE** — [app/Services/SellerShipmentFinance.php:97](../../../app/Services/SellerShipmentFinance.php#L97)

<a id="evidence-financelock"></a>**FINANCELOCK** — [app/Services/SellerShipmentFinance.php:53](../../../app/Services/SellerShipmentFinance.php#L53)

<a id="evidence-history"></a>**HISTORY** — [database/migrations/2026_08_29_070001_create_order_item_status_histories_table.php:11](../../../database/migrations/2026_08_29_070001_create_order_item_status_histories_table.php#L11)

<a id="evidence-itemprice"></a>**ITEMPRICE** — [app/Http/Controllers/BuyerOrderController.php:2044](../../../app/Http/Controllers/BuyerOrderController.php#L2044)

<a id="evidence-lowstock"></a>**LOWSTOCK** — [app/Services/SellerNotificationService.php:19](../../../app/Services/SellerNotificationService.php#L19)

<a id="evidence-nav"></a>**NAV** — [resources/js/Layouts/ScLayout.vue:75](../../../resources/js/Layouts/ScLayout.vue#L75)

<a id="evidence-notify"></a>**NOTIFY** — [app/Observers/RiderNotificationObserver.php:78](../../../app/Observers/RiderNotificationObserver.php#L78)

<a id="evidence-ordermodel"></a>**ORDERMODEL** — [app/Models/Order.php:16](../../../app/Models/Order.php#L16)

<a id="evidence-parcel"></a>**PARCEL** — [app/Models/Parcel.php:43](../../../app/Models/Parcel.php#L43)

<a id="evidence-payout"></a>**PAYOUT** — [app/Http/Controllers/SellerNotificationController.php:34](../../../app/Http/Controllers/SellerNotificationController.php#L34)

<a id="evidence-pickup"></a>**PICKUP** — [app/Http/Controllers/Logistics/RiderPickupController.php:86](../../../app/Http/Controllers/Logistics/RiderPickupController.php#L86)

<a id="evidence-pickupcreate"></a>**PICKUPCREATE** — [app/Services/OrderItemPickup.php:12](../../../app/Services/OrderItemPickup.php#L12)

<a id="evidence-product"></a>**PRODUCT** — [app/Models/Product.php:15](../../../app/Models/Product.php#L15)

<a id="evidence-productarchive"></a>**PRODUCTARCHIVE** — [app/Http/Controllers/SellerProductController.php:1169](../../../app/Http/Controllers/SellerProductController.php#L1169)

<a id="evidence-productupdate"></a>**PRODUCTUPDATE** — [app/Http/Controllers/SellerProductController.php:426](../../../app/Http/Controllers/SellerProductController.php#L426)

<a id="evidence-proof"></a>**PROOF** — [app/Http/Controllers/Logistics/RiderDeliveryController.php:195](../../../app/Http/Controllers/Logistics/RiderDeliveryController.php#L195)

<a id="evidence-reject"></a>**REJECT** — [app/Http/Controllers/Logistics/RiderManagementController.php:80](../../../app/Http/Controllers/Logistics/RiderManagementController.php#L80)

<a id="evidence-report"></a>**REPORT** — [app/Http/Controllers/Logistics/ReportController.php:21](../../../app/Http/Controllers/Logistics/ReportController.php#L21)

<a id="evidence-reportui"></a>**REPORTUI** — [resources/js/Pages/Logistics/DeliveryReports.vue:326](../../../resources/js/Pages/Logistics/DeliveryReports.vue#L326)

<a id="evidence-returnrequest"></a>**RETURNREQUEST** — [app/Http/Controllers/BuyerOrderController.php:1796](../../../app/Http/Controllers/BuyerOrderController.php#L1796)

<a id="evidence-returns"></a>**RETURNS** — [app/Http/Controllers/SellerReturnController.php:188](../../../app/Http/Controllers/SellerReturnController.php#L188)

<a id="evidence-reviewupdate"></a>**REVIEWUPDATE** — [app/Http/Controllers/BuyerReviewController.php:187](../../../app/Http/Controllers/BuyerReviewController.php#L187)

<a id="evidence-riderguard"></a>**RIDERGUARD** — [app/Http/Middleware/EnsureRiderApproved.php:11](../../../app/Http/Middleware/EnsureRiderApproved.php#L11)

<a id="evidence-riderlogin"></a>**RIDERLOGIN** — [app/Http/Controllers/Logistics/LoginRiderController.php:37](../../../app/Http/Controllers/Logistics/LoginRiderController.php#L37)

<a id="evidence-ridermodel"></a>**RIDERMODEL** — [app/Models/Rider.php:57](../../../app/Models/Rider.php#L57)

<a id="evidence-routes"></a>**ROUTES** — [routes/api.php:65](../../../routes/api.php#L65)

<a id="evidence-schema"></a>**SCHEMA** — [database/migrations/2026_10_11_100000_create_logistics_tables.php:47](../../../database/migrations/2026_10_11_100000_create_logistics_tables.php#L47)

<a id="evidence-sellercancel"></a>**SELLERCANCEL** — [app/Http/Controllers/SellerOrderController.php:571](../../../app/Http/Controllers/SellerOrderController.php#L571)

<a id="evidence-sellerdash"></a>**SELLERDASH** — [app/Http/Controllers/SellerDashboardController.php:50](../../../app/Http/Controllers/SellerDashboardController.php#L50)

<a id="evidence-sellernotice"></a>**SELLERNOTICE** — [app/Services/SellerParcelDelivery.php:27](../../../app/Services/SellerParcelDelivery.php#L27)

<a id="evidence-sellerreport"></a>**SELLERREPORT** — [app/Http/Controllers/SellerReportController.php:16](../../../app/Http/Controllers/SellerReportController.php#L16)

<a id="evidence-sellerreturn"></a>**SELLERRETURN** — [app/Http/Controllers/SellerReturnController.php:378](../../../app/Http/Controllers/SellerReturnController.php#L378)

<a id="evidence-session"></a>**SESSION** — [config/session.php:35](../../../config/session.php#L35)

<a id="evidence-settle"></a>**SETTLE** — [app/Http/Controllers/DeliveryFinanceController.php:79](../../../app/Http/Controllers/DeliveryFinanceController.php#L79)

<a id="evidence-shipassign"></a>**SHIPASSIGN** — [app/Services/SellerShipmentFinance.php:58](../../../app/Services/SellerShipmentFinance.php#L58)

<a id="evidence-shipdispatch"></a>**SHIPDISPATCH** — [app/Services/SellerShipmentFinance.php:70](../../../app/Services/SellerShipmentFinance.php#L70)

<a id="evidence-sort"></a>**SORT** — [app/Http/Controllers/Logistics/ParcelSortingController.php:99](../../../app/Http/Controllers/Logistics/ParcelSortingController.php#L99)

<a id="evidence-storeprofile"></a>**STOREPROFILE** — [app/Http/Controllers/SellerStoreController.php:52](../../../app/Http/Controllers/SellerStoreController.php#L52)

<a id="evidence-track"></a>**TRACK** — [app/Http/Controllers/Logistics/ParcelMonitoringController.php:21](../../../app/Http/Controllers/Logistics/ParcelMonitoringController.php#L21)

<a id="evidence-variant"></a>**VARIANT** — [app/Models/ProductVariant.php:11](../../../app/Models/ProductVariant.php#L11)

<a id="evidence-variantdelete"></a>**VARIANTDELETE** — [app/Http/Controllers/SellerProductController.php:1133](../../../app/Http/Controllers/SellerProductController.php#L1133)

<a id="evidence-voucher"></a>**VOUCHER** — [app/Http/Controllers/SellerVoucherController.php:109](../../../app/Http/Controllers/SellerVoucherController.php#L109)

<a id="evidence-voucherdelete"></a>**VOUCHERDELETE** — [app/Http/Controllers/SellerVoucherController.php:98](../../../app/Http/Controllers/SellerVoucherController.php#L98)

<a id="evidence-voucherupdate"></a>**VOUCHERUPDATE** — [app/Http/Controllers/SellerVoucherController.php:75](../../../app/Http/Controllers/SellerVoucherController.php#L75)

<a id="evidence-voucheruse"></a>**VOUCHERUSE** — [app/Http/Controllers/BuyerOrderController.php:1124](../../../app/Http/Controllers/BuyerOrderController.php#L1124)

<a id="evidence-web"></a>**WEB** — [routes/logistics.php:15](../../../routes/logistics.php#L15)
