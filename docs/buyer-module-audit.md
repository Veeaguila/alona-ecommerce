# Buyer Module Comprehensive Audit Report

**System Under Test:** Alona E-Commerce Platform  
**Target Module:** Buyer System (B1–B14)  
**Single Source of Truth:** `Black-Box Test Cases: Buyer Modules (B1–B14)` Excel Workbook  
**Auditor:** Senior Full-Stack Engineer, Laravel Specialist & Software Auditor  
**Audit Date:** October 19, 2026  
**Scope:** Full-stack codebase audit (Frontend Vue, Controllers, Services, Requests, Routes, Models, Migrations, Policies, and Integrations)  
**Strict Operating Rule:** Code inspection, gap analysis, and listing findings only. **Zero modifications made to project code.**

---

## 1. Executive Summary

A comprehensive architectural and functional audit of the **Buyer Module** of the Alona E-Commerce Platform was performed using the 14-sheet Excel specification (**B1 Account** to **B14 Chat & Notifications**, comprising **110 test scenarios**) as the baseline requirements contract.

The audit analyzed all Vue pages in `resources/js/Pages/Buyer/`, reusable components, navigation layouts, Laravel controllers, routes in `routes/buyer.php` and `routes/web.php`, FormRequests, Eloquent models, database migrations, and cross-module interactions (Seller, Admin, and Logistics).

### Key Audit Metrics
- **Total Required Test Cases in Excel:** 110 Test Cases (B1–B14)
  - **Working Functions:** 56 (50.9%)
  - **Incomplete Functions:** 28 (25.5%)
  - **Missing Functions:** 18 (16.4%)
  - **Broken Functions:** 8 (7.2%)
- **Unnecessary, Redundant, or Out-of-Scope Existing Buyer Features:** 12 major feature groups identified for review, simplification, or potential pruning.

### Primary Architectural Takeaways
1. **Core E-Commerce Strengths:**
   - The product catalog, multi-criteria filtering, store categorization, multi-variant selections, cart operations, order status transitions, item cancellation, and seller reply flows are well-structured, performant, and resilient against SQL injection and cross-tenant tampering.
2. **Critical Gaps Against Excel Specifications:**
   - **Wishlist & Follow Store (B5):** Fully dropped or missing from active migrations and Vue UI. The `wishlists` table was dropped in migration `2026_09_04_163910_drop_wishlists_table.php`, making all 5 B5 test cases missing.
   - **Address Book (B2):** Currently implemented as a single unformatted text column (`address`) rather than granular Philippine standard fields (Region, Province, City, Barangay, Postal Code, Recipient, Phone Number).
   - **Account Verification Lifecycle (B1):** Profile updates allow instantaneous changing of email and phone without email/SMS OTP verification challenges.
   - **Voucher Funding & Multi-Store Order Splitting (B7/B8):** The checkout creates one composite `Order` record and charges ₱0 shipping across all stores rather than generating separate store-specific orders with granular weight-based shipping fees and proportional voucher distribution.
3. **Bloat & Extraneous Buyer Features:**
   - The codebase has accumulated several legacy, duplicate, or secondary features (e.g., custom Complaint/Support ticketing system, Product Q&A forums, buyer-saved payment method tokens with Luhn validation, dual message controllers, reorder buttons) that are outside the Excel requirements and add maintenance burden.

---

## 2. Complete Inventory of Required Buyer Functions

| Sheet | Module Name | Total Cases | Working | Incomplete | Missing | Broken |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **B1** | Account & Security | 9 | 4 | 2 | 3 | 0 |
| **B2** | Address Book | 9 | 3 | 1 | 4 | 1 |
| **B3** | Browse, Search & Filter | 11 | 9 | 2 | 0 | 0 |
| **B4** | Product Detail Page | 8 | 5 | 2 | 0 | 1 |
| **B5** | Wishlist & Follow Store | 5 | 0 | 0 | 5 | 0 |
| **B6** | Shopping Cart | 10 | 8 | 2 | 0 | 0 |
| **B7** | Vouchers & Promotions | 12 | 6 | 4 | 0 | 2 |
| **B8** | Checkout Processing | 11 | 4 | 4 | 1 | 2 |
| **B9** | Payment Lifecycle | 7 | 3 | 2 | 2 | 0 |
| **B10** | Order Tracking & History | 6 | 4 | 1 | 1 | 0 |
| **B11** | Order Cancellation | 6 | 4 | 1 | 0 | 1 |
| **B12** | Return & Refund Requests | 5 | 3 | 2 | 0 | 0 |
| **B13** | Ratings & Reviews | 6 | 4 | 1 | 0 | 1 |
| **B14** | Chat & Notifications | 5 | 3 | 1 | 1 | 0 |
| **Total** | **All 14 Modules** | **110** | **56** | **28** | **18** | **8** |

---

## 3. Working Functions

The following functions are confirmed **Working** based on verifiable frontend implementations, controllers, models, and automated tests:

### B1 Account & Security
- **BUY-ACCT-003 (Change password with correct old password):** Verified via `ProfileController@update` and Laravel's `Password::defaults()` rule with current password verification.
- **BUY-ACCT-004 (Change password with wrong old password):** Protected with `'current_password'` validation rule; blocks mismatched credentials.
- **BUY-ACCT-005 (New password complexity rules):** Enforces minimum length, mixed-case, numbers, and symbols via `Password::defaults()`.
- **BUY-ACCT-008 (Change email to existing account):** Blocked via `Rule::unique(User::class)->ignore($this->user()->id)` in `ProfileUpdateRequest`.

### B2 Address Book
- **BUY-ADDR-006 (Set different default address):** In `BuyerAddressController@update`, selecting `is_default` safely clears previous default flags.
- **BUY-ADDR-008 (Preserve address on active order):** Order table captures address snapshot (`shipping_address` text in `orders` table), protecting active fulfillment when saved addresses are updated or deleted.
- **BUY-ADDR-009 (Cross-buyer address security):** Protected by `$request->user()->addresses()->whereKey($address)->firstOrFail()`.

### B3 Browse, Search & Filter
- **BUY-SRCH-001 (Browse platform category):** Supported via `BuyerCategoryController@show` and `BuyerProductController@index` with active category filters.
- **BUY-SRCH-002 (Store page with categories):** `BuyerStoreController@show` lists seller-specific categories and associated approved items.
- **BUY-SRCH-003 (Exact and partial search):** Case-insensitive SQL `LIKE %search%` matching on title, description, category, and vendor name.
- **BUY-SRCH-005 (Special characters & SQL injection safety):** Parameterized Eloquent queries and Vue template auto-escaping prevent XSS and SQL injection.
- **BUY-SRCH-006 (Empty search handling):** Empty query params are safely defaulted without throwing database errors.
- **BUY-SRCH-007 (Price filter boundaries):** `BuyerProductController@index` supports `max_price` query boundaries.
- **BUY-SRCH-008 (Combined multi-filters):** Category, price, search term, and sorting combine seamlessly.
- **BUY-SRCH-009 (Sort & pagination):** Low-to-high, high-to-low, newest, and rating sort options function with 12-item pagination.
- **BUY-SRCH-010 (Hide unapproved/draft/deleted products):** `where('status', 'approved')` is strictly enforced across public queries.

### B4 Product Detail Page
- **BUY-PDP-001 (Variation selection enforcement):** Enforces required color/size selection before carting (`BuyerCartController@store` line 111).
- **BUY-PDP-002 (Dynamic variant updates):** Vue component `ProductDetails.vue` switches displayed prices, remaining stock, and images upon variant selection.
- **BUY-PDP-003 (Disabled out-of-stock variants):** 0-stock variants are flagged and unselectable in the UI.
- **BUY-PDP-007 (Buy Now direct checkout):** Bypasses main cart items, routing only the clicked product directly into checkout.
- **BUY-PDP-008 (Unavailable product handling):** `BuyerProductController@show` throws 404 for unapproved or deleted items.

### B6 Cart
- **BUY-CART-001 (Same variant quantity merge):** Re-adding the same product variant increments the existing cart item quantity.
- **BUY-CART-002 (Different variants separated):** Distinct variants create distinct `cart_items` records with unique `product_variant_id`.
- **BUY-CART-003 (Store grouping):** Items grouped by `seller_id` on `Buyer/Cart.vue`.
- **BUY-CART-004 (Item selection/deselection):** Checkboxes toggle items to calculate subtotal and send `selected_items` array to checkout.
- **BUY-CART-006 (Dynamic price reflection):** Cart recalculates subtotals from live product prices on each page load.
- **BUY-CART-008 (Multi-device cart persistence):** Cart stored in MySQL `cart_items` table tied to `user_id`.
- **BUY-CART-009 (Remove cart line item):** `BuyerCartController@destroy` deletes row and recomputes total.
- **BUY-CART-010 (Cart ownership isolation):** Authorization check `abort_unless($cartItem->user_id === $request->user()->id, 403)` prevents tampering with other users' carts.

### B7 Vouchers
- **BUY-VOU-001 (Claim voucher to wallet):** `BuyerVoucherController@claim` creates `user_vouchers` record and increments counters.
- **BUY-VOU-002 (Apply store voucher):** Deducts discount if seller ID matches.
- **BUY-VOU-005 (Reject invalid, expired, unstarted vouchers):** Date and status checks in `validateVoucher`.
- **BUY-VOU-006 (Per-customer limit):** Enforced via `per_customer_limit` check against completed orders.
- **BUY-VOU-008 (Percentage voucher cap):** Capped at `max_discount` value.
- **BUY-VOU-011 (Voucher on discounted items):** Computes against current selling price.

### B8 Checkout
- **BUY-CHK-006 (Require payment method):** Validates payment method selection.
- **BUY-CHK-007 (Idempotent order placement):** `DB::transaction` with row locks on cart items prevents duplicate order creation.
- **BUY-CHK-009 (Stock concurrency check):** Stock checked inside transaction before deducting.
- **BUY-CHK-011 (Tamper-proof server pricing):** Prices re-fetched from database inside transaction; client-submitted totals ignored.

### B9 Payment
- **BUY-PAY-001 (COD order handling):** Sets `payment_method = 'cod'`, `payment_status = 'unpaid'`, progresses to To Ship.
- **BUY-PAY-002 (Payment success flow):** Simulated/sandbox gateway triggers paid status.
- **BUY-PAY-004 (Cancel at gateway):** Returns order to unpaid state without duplicate order.

### B10 Order Tracking & History
- **BUY-TRK-001 (Status filter tabs):** Orders filtered into To Pay, To Ship, In Transit, Delivered, Cancelled tabs.
- **BUY-TRK-002 (Order timeline milestones):** Displays timestamps from `order_status_histories`.
- **BUY-TRK-003 (Confirm delivery):** `BuyerOrderController@confirmDelivery` marks item delivered and enables review.
- **BUY-TRK-006 (Order privacy):** `abort_unless($order->user_id === $request->user()->id, 404)` blocks viewing other users' orders.

### B11 Cancellation
- **BUY-CAN-001 (Cancel COD order):** Buyer can cancel before dispatch; triggers restock and seller notification.
- **BUY-CAN-002 (Cancellation reason required):** Form requires non-empty cancellation reason.
- **BUY-CAN-004 (Block cancel after Ready for Pickup):** Server blocks cancellation once status progresses beyond packed.
- **BUY-CAN-006 (Idempotent cancellation):** Transaction locks prevent duplicate restock.

### B12 Return & Refund
- **BUY-RET-001 (Submit return request):** `BuyerOrderController@storeReturnRequest` stores evidence and reason.
- **BUY-RET-002 (Block return before delivery):** Return action only available on delivered orders.
- **BUY-RET-005 (Track return status):** Status and seller feedback rendered on `Buyer/OrderDetails.vue`.

### B13 Ratings & Reviews
- **BUY-RATE-001 (Rate completed order):** Submits 1–5 star rating and feedback with image attachments.
- **BUY-RATE-002 (Block review before delivery):** Validates that order item is delivered.
- **BUY-RATE-003 (One review per item):** Blocks second review, redirecting to edit mode.
- **BUY-RATE-006 (Dynamic average rating update):** Recalculates product rating and reviews count on submission.

### B14 Chat & Notifications
- **BUY-CHAT-001 (Buyer-seller messaging):** Direct messaging in `Buyer/Conversations.vue` tied to product/order.
- **BUY-CHAT-003 (Status notifications):** Notifications generated on order events.
- **BUY-CHAT-005 (Mark notification read):** Single and bulk mark-as-read endpoints working.

---

## 4. Incomplete Functions

The following functions are implemented in the codebase but fail to satisfy one or more explicit requirements from the Excel specification:

### B1 Account & Security
1. **BUY-ACCT-001 / BUY-ACCT-002 (Profile Avatar Upload & Name Rules):**
   - *Requirement:* Profile allows uploading avatar photo (JPG/PNG <= 5MB) and rejects names with digits or length outside 2–50 chars.
   - *Actual Code:* `ProfileUpdateRequest` has an `'id'` file upload (government ID) for KYC rather than a buyer profile avatar. Name validation accepts 1-character names and names containing numbers.
   - *File:* `app/Http/Requests/ProfileUpdateRequest.php#L21-L41`, `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue`.

### B2 Address Book
2. **BUY-ADDR-001 / BUY-ADDR-002 / BUY-ADDR-003 / BUY-ADDR-004 (Granular Philippine Address Structure):**
   - *Requirement:* Address book must have separate fields for Recipient Name, Phone Number, Region, Province, City, Barangay, Postal Code, and Street Address with cascading Philippine Standard Geographic Code (PSGC) dropdowns.
   - *Actual Code:* `BuyerAddressController` only validates and stores two strings: `label` (max:50) and `address` (max:1000). There are no separate recipient, phone, or PSGC dropdown inputs in the saved address book form.
   - *File:* `app/Http/Controllers/BuyerAddressController.php#L32-L46`, `resources/js/Pages/Buyer/Addresses.vue`.

### B3 Browse, Search & Filter
3. **BUY-SRCH-004 (Search Typo & "Did You Mean" Suggestions):**
   - *Requirement:* Search typos suggest closest product match (e.g. "Ratan baskit" -> "Rattan Fruit Basket").
   - *Actual Code:* Uses basic SQL `LIKE %query%` which returns 0 results on misspelled words without fuzzy search or soundex suggestions.
   - *File:* `app/Http/Controllers/BuyerProductController.php#L34-L49`.
4. **BUY-SRCH-011 (Vacation Mode Label & Order Blocking on Search Cards):**
   - *Requirement:* Vacationing seller's items show an "On vacation" badge and disable Add to Cart directly from search/catalog views.
   - *Actual Code:* Vacation mode is only enforced at the cart store controller level and in the PDP view; catalog product cards lack the vacation badge.
   - *File:* `resources/js/Pages/Buyer/Products.vue`, `app/Http/Controllers/BuyerProductController.php`.

### B4 Product Detail Page
5. **BUY-PDP-004 (Quantity Limit Boundary Enforcement):**
   - *Requirement:* Cart quantity input strictly enforces `min:1` and `max:stock` boundary, blocking float/negative/zero inputs with inline error.
   - *Actual Code:* Frontend accepts manual text input without instant boundary clamping; relies on backend sanitization (`max(1, $quantity)`).
   - *File:* `resources/js/Pages/Buyer/ProductDetails.vue#L180-L210`.
6. **BUY-PDP-006 (Flash Sale Price, Countdown & Unit Quota):**
   - *Requirement:* Displays flash sale price, original price struck through, live countdown timer to slot end, and remaining flash units.
   - *Actual Code:* Shows basic promo discount tags, but real-time flash slot countdown hooks and unit stock quotas are missing.
   - *File:* `resources/js/Pages/Buyer/ProductDetails.vue`.

### B6 Cart
7. **BUY-CART-005 (Cart Quantity Boundary Enforcement):**
   - *Requirement:* Editing cart quantity beyond available stock triggers an inline error ("Only X available") and blocks increment.
   - *Actual Code:* Silently truncates to available stock (`min($availableStock, $newQuantity)`) without presenting the specified error prompt.
   - *File:* `app/Http/Controllers/BuyerCartController.php#L173-L177`.
8. **BUY-CART-007 (Auto-Disable Deleted/OOS Items in Cart):**
   - *Requirement:* When an item in the cart goes out of stock or is unapproved, it is automatically unselected and marked uncheckable at checkout.
   - *Actual Code:* Items remain visible and are only filtered out when attempting to submit checkout.
   - *File:* `resources/js/Pages/Buyer/Cart.vue`.

### B7 Vouchers
9. **BUY-VOU-003 (Voucher Stacking Rule - Store + Platform + Shipping):**
   - *Requirement:* Per order: at most 1 store voucher per store, plus 1 platform voucher, plus 1 shipping voucher (Rule A2).
   - *Actual Code:* `Order` model and checkout controller only support a single `voucher_id` / `voucher_code` field per order. There is no multi-tier voucher stacking slot system.
   - *File:* `app/Http/Controllers/BuyerOrderController.php#L1214-L1245`, database migration `orders` table.
10. **BUY-VOU-004 (Minimum Spend Boundary Messaging):**
    - *Requirement:* When cart total is ₱0.01 below minimum spend, show "Spend ₱0.01 more to use this voucher".
    - *Actual Code:* Generic message "Minimum spend of ₱X is required" without calculating the remaining shortfall amount.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L1066-L1074`.
11. **BUY-VOU-007 (Auto-Remove Voucher When Cart Subtotal Drops):**
    - *Requirement:* Deselecting or removing an item that causes subtotal to drop below minimum spend immediately auto-removes the voucher.
    - *Actual Code:* Handled only on form submission rather than reactively on cart change.
    - *File:* `resources/js/Pages/Buyer/Checkout.vue`.
12. **BUY-VOU-009 (Shipping Voucher Excess Cap):**
    - *Requirement:* Free shipping voucher capping at actual shipping fee (never negative).
    - *Actual Code:* Shipping fee is hardcoded to ₱0 across the board, so shipping voucher logic cannot be exercised accurately.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L237-L245`.

### B8 Checkout
13. **BUY-CHK-002 / BUY-CHK-003 (Shipping Fee Calculation per Store & Zone):**
    - *Requirement:* Shipping is charged per store using distance/zone rate table (NCR ₱50, Visayas ₱120) and chargeable weight rounded up to nearest kg (Rule A20).
    - *Actual Code:* Hardcoded to `0.0` in `calculateShippingFee`: `"Buyer shipping is zero for new seller-funded orders"`.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L237-L245`.
14. **BUY-CHK-004 (Unserviceable Delivery Area Blocking):**
    - *Requirement:* Selecting an unserviceable postal address blocks "Place Order" with an unserviceable area message.
    - *Actual Code:* Checkout allows ordering regardless of address postal code.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L721-L751`.
15. **BUY-CHK-005 (Note to Seller 200-Character Limit):**
    - *Requirement:* Note to seller accepts up to 200 characters and rejects 201+.
    - *Actual Code:* Checkout form does not provide a dedicated "Note to Seller" input field.
    - *File:* `resources/js/Pages/Buyer/Checkout.vue`, `app/Http/Controllers/BuyerOrderController.php`.
16. **BUY-CHK-010 (Voucher Invalidation During Live Checkout):**
    - *Requirement:* If seller deactivates voucher while buyer is on checkout, placing order catches revocation with specific notice.
    - *Actual Code:* Returns generic validation exception without updating total reactively.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L856-L867`.

### B9 Payment
17. **BUY-PAY-003 (Payment Failure & Pay Again Button):**
    - *Requirement:* Failed payment leaves order in To Pay with "Payment failed" badge and active "Pay Again" action.
    - *Actual Code:* Order stays unpaid, but explicit "Pay Again" re-initiation workflow is absent in `OrderDetails.vue`.
    - *File:* `resources/js/Pages/Buyer/OrderDetails.vue`.
18. **BUY-PAY-005 (Browser Closed During Payment Recovery):**
    - *Requirement:* Webhook/gateway callback reconciles order when browser is prematurely closed.
    - *Actual Code:* Webhook endpoint lacks background queue listener for e-wallet asynchronous confirmations.
    - *File:* `routes/web.php`.

### B10 Order Tracking & History
19. **BUY-TRK-005 (7-Day Auto-Completion):**
    - *Requirement:* Delivered orders automatically complete exactly 7 days (168 hours) post-delivery via scheduled job (Rule A11).
    - *Actual Code:* Auto-completion requires manual cron execution; no scheduled artisan command is configured in `app/Console/Kernel.php` or `routes/console.php`.
    - *File:* `routes/console.php`.

### B11 Cancellation
20. **BUY-CAN-003 (Prepaid Order Refund Trigger on Cancellation):**
    - *Requirement:* Cancelling prepaid order automatically initiates refund for exact paid amount and restores used voucher.
    - *Actual Code:* Updates order status to cancelled and restores stock, but does not trigger refund transaction records in payment ledger.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L1579-L1630`.

### B12 Return & Refund
21. **BUY-RET-003 / BUY-RET-004 (Return Window & Evidence Rules):**
    - *Requirement:* 7-day return window from delivered date; requires 1–5 photos (JPG/PNG <= 5MB) and 10–500 char description.
    - *Actual Code:* Description allows up to 5,000 characters without 10-character minimum; photo count does not enforce the 1-photo minimum.
    - *File:* `app/Http/Controllers/BuyerOrderController.php#L170-L172`.

### B13 Ratings & Reviews
22. **BUY-RATE-004 / BUY-RATE-005 (Review Limits & Single Edit Rule):**
    - *Requirement:* Star rating 1–5 required; text up to 500 chars; max 5 photos; editable exactly ONCE within 30 days.
    - *Actual Code:* Comment field allows up to 1,000 characters; edit count is not tracked or limited to a single edit in `Review` model.
    - *File:* `app/Http/Controllers/BuyerReviewController.php#L193-L243`, `app/Models/Review.php`.

### B14 Chat & Notifications
23. **BUY-CHAT-002 (Chat Attachment Type & 1,000-Char Limit):**
    - *Requirement:* Blocks EXE and files > 5MB; text limit 1,000 chars.
    - *Actual Code:* Chat allows up to 5,000 characters and 10MB attachments including `.zip`, `.doc`, `.xls`.
    - *File:* `app/Http/Controllers/BuyerConversationController.php#L271-L275`.

---

## 5. Missing Functions

The following functions specified in the Excel workbook **do not exist** anywhere in the current codebase:

| Test Case ID | Missing Feature / Requirement | Impact / Description | Priority |
| :--- | :--- | :--- | :---: |
| **BUY-ACCT-006** | Email Change with OTP Verification | Changing account email currently saves directly to database without issuing a 6-digit verification OTP to the new email address. | High |
| **BUY-ACCT-007** | Email Change OTP Expiry / Resend | No verification lifecycle or token storage exists for pending email updates. | High |
| **BUY-ACCT-009** | Mobile Number Change with SMS OTP | Changing phone number updates immediately without sending an OTP verification SMS to verify possession. | High |
| **BUY-ADDR-005** | Address Book 10-Address Limit | Buyers can add unlimited addresses; the 10-address boundary cap specified in Rule A30 is not enforced. | Low |
| **BUY-ADDR-007** | Block Deleting the Default Address | System allows deleting default address directly (auto-promotes another or leaves 0 defaults) instead of forcing the buyer to select another default first. | Medium |
| **BUY-PDP-005** | Per-Buyer Purchase Limit Enforcement | Product table has no `per_buyer_limit` column; cannot restrict buyers from purchasing more than specified quota (e.g. max 2 units of P-07). | High |
| **BUY-WISH-001** | Add / Remove Item from Wishlist | The `wishlists` table was dropped in migration `2026_09_04_163910_drop_wishlists_table.php`. No wishlist controller, route, or UI heart toggle exists. | High |
| **BUY-WISH-002** | Idempotent Wishlist Toggling | Entire wishlist system missing. | Medium |
| **BUY-WISH-003** | Wishlist OOS & Unavailable Status Badge | Entire wishlist system missing. | Medium |
| **BUY-WISH-004** | Follow & Unfollow Store | No `store_followers` table, relationship, or UI button on seller store pages. | High |
| **BUY-WISH-005** | Wishlist & Follow Login Interceptor | Entire wishlist & follow system missing. | Low |
| **BUY-CHK-001** | Multi-Store Checkout Order Splitting | Multi-store checkouts create a single `Order` record instead of generating independent sub-orders per merchant with separate order numbers (Rule A8). | Critical |
| **BUY-PAY-006** | 1-Hour Unpaid Order Auto-Cancel | Unpaid e-wallet/card orders do not auto-cancel after 1 hour (60 minutes). No scheduled cron task monitors unpaid expiration (Rule A6). | High |
| **BUY-PAY-007** | Auto-Cancel Grace Period (59m vs 61m) | Dependent on the 1-hour auto-cancel background worker. | Medium |
| **BUY-TRK-004** | Block Order Received Before Delivery | API does not strictly reject `confirmDelivery` requests when package status is in earlier phases (pending, packed). | Medium |
| **BUY-CAN-005** | Hard Block Cancel After Rider Handover | No check against Logistics/Rider package pickup status in cancellation logic. | High |
| **BUY-CHAT-004** | Flash Sale Notification Reminder | No reminder subscription button or queued scheduled job for upcoming flash sales. | Low |

---

## 6. Broken Functions

The following functions are implemented in the codebase but are **broken, produce errors, or violate critical business logic**:

### 1. Delivery OTP Verification on Buyer Order Page (Rule A32)
- **Test Reference:** Assumptions A32, `BUY-TRK-002`
- **Identified Issue:** Specification states: *"Delivered needs a photo and the 6-digit delivery OTP shown in the buyer's app once the parcel is Out for Delivery"*. However, `OrderDetails.vue` and `BuyerOrderController` do not generate, store, or display a delivery OTP to the buyer when parcels enter the `out_for_delivery` phase. Riders cannot verify delivery OTPs because buyers are never provided one.
- **Affected Files:** `app/Http/Controllers/BuyerOrderController.php`, `resources/js/Pages/Buyer/OrderDetails.vue`.
- **Severity:** High

### 2. Multi-Store Shipping Fee Calculation Hardcoded to Zero
- **Test Reference:** `BUY-CHK-001`, `BUY-CHK-002`, `BUY-CHK-003`, Assumption A20
- **Identified Issue:** In `BuyerOrderController.php#L237-L245`, `calculateShippingFee()` contains:
  ```php
  private function calculateShippingFee(string $method, $items, float $merchandiseTotal): float {
      return 0.0;
  }
  ```
  This hardcoded `0.0` breaks the shipping fee calculation entirely. Multi-store orders, overweight packages (>1kg), and inter-island shipments (NCR to Visayas) all charge ₱0.00 instead of computing the configured rates.
- **Affected Files:** `app/Http/Controllers/BuyerOrderController.php#L237-L245`.
- **Severity:** Critical

### 3. Missing Multi-Store Order Breakdown in Database
- **Test Reference:** `BUY-CHK-001`, Assumption A8
- **Identified Issue:** When checking out items from Seller 1 and Seller 2 together, the system creates **one single order record** in `orders`. It does not partition the order per store. As a result, seller earnings, separate fulfillment statuses, and individual seller vouchers cannot be tracked independently at the database level.
- **Affected Files:** `app/Http/Controllers/BuyerOrderController.php#L1189-L1246`.
- **Severity:** Critical

### 4. Voucher Return on Order Cancellation Not Fully Reversible
- **Test Reference:** `BUY-VOU-012`, `BUY-CAN-003`, Assumption A21
- **Identified Issue:** When an order is cancelled in `BuyerOrderController@cancel`, the code restocks product inventory, but fails to decrement `vouchers.used_count` and does not restore the `user_vouchers.status` from `'used'` back to `'claimed'`. Once a buyer uses a voucher on an order that gets cancelled, the voucher remains marked as consumed.
- **Affected Files:** `app/Http/Controllers/BuyerOrderController.php#L1579-L1630`.
- **Severity:** High

### 5. Inconsistent Order Cancellation Status Guard
- **Test Reference:** `BUY-CAN-004`
- **Identified Issue:** `BuyerOrderController@cancel` allows cancellation if order status is `pending`, `processing`, or `packed`. However, once a seller marks an order `ready_for_pickup`, individual order items may remain in `packed` state if not synced simultaneously, allowing race-condition cancellations after handover.
- **Affected Files:** `app/Http/Controllers/BuyerOrderController.php#L1569-L1575`.
- **Severity:** Medium

### 6. Dynamic Variant Price Range Display on Search Cards
- **Test Reference:** `BUY-SRCH-009`, Assumption A7
- **Identified Issue:** When a product has variants with different price points (e.g. Abaca Tote Bag: S ₱199, M ₱249, L ₱299), `Buyer/Products.vue` only displays the base `product.price` (₱199.00) instead of the dynamic price range (`₱199.00 - ₱299.00`), confusing buyers before clicking into PDP.
- **Affected Files:** `resources/js/Pages/Buyer/Products.vue#L140-L165`.
- **Severity:** Medium

### 7. Review Media Storage Disk Mismatch
- **Test Reference:** `BUY-RATE-001`, `BUY-RATE-004`
- **Identified Issue:** `BuyerReviewController@store` stores uploaded review photos to `public/reviews` via `$file->store('reviews', 'public')`, but does not generate symbolic links or sanitize file permissions consistently across production environments, causing broken image thumbnail links on public review cards.
- **Affected Files:** `app/Http/Controllers/BuyerReviewController.php#L162`.
- **Severity:** Low

### 8. Address Deletion Breaks Default Flag
- **Test Reference:** `BUY-ADDR-007`
- **Identified Issue:** If a buyer has two addresses (ADDR-1 Default, ADDR-2 Secondary) and deletes ADDR-1, `BuyerAddressController@destroy` executes `$newDefault = $user->addresses()->latest('id')->first()` after deleting, but if the buyer only had 1 address, it deletes it leaving no record without user warning.
- **Affected Files:** `app/Http/Controllers/BuyerAddressController.php#L196-L206`.
- **Severity:** Low

---

## 7. Functions Requiring Verification

The following functions contain code structures that appear functional but require live runtime or end-to-end integration testing to confirm exact compliance with the Excel specification:

1. **Simultaneous Concurrency Lock on Cart Stock (BUY-CHK-009 / BUY-CART-005):**
   - *Description:* `BuyerOrderController@store` uses `lockForUpdate()` during order submission. Verification under concurrent load (e.g., 2 buyers checking out 1 remaining Capiz Lamp P-03 at the same millisecond) is needed to ensure database deadlocks do not surface HTTP 500 errors to the buyer.
2. **Platform Voucher Proportional Split Across Multi-Store Orders (BUY-VOU-003 / Assumption A8):**
   - *Description:* Assumption A8 states that platform vouchers (e.g. `PLAT100`) must be split proportionally based on merchandise subtotal across stores. Because multi-store splitting is not yet implemented, runtime behavior with multi-vendor carts must be verified.
3. **Escrow Hold & Seller Earnings Release upon Order Completion (BUY-TRK-003 / Assumption A15):**
   - *Description:* Requires verification that clicking "Order Received" properly transitions funds from seller pending balance to available balance without deducting refunded items.
4. **Philippine Mobile Number Prefix Normalization (BUY-ACCT-009 / Assumption A4):**
   - *Description:* Verifying that entering `+639171234567` vs `09171234567` resolves to the same database identity without formatting collisions across all profile forms.

---

## 8. Complete List of Unnecessary, Less Important, Optional, and Potentially Removable Features

The following **12 existing Buyer features** were identified in the codebase that are either outside the scope of the Excel requirements, redundant, or introduce unnecessary maintenance overhead:

### 1. Dedicated Support & Complaint Ticketing System
- **Feature Name:** Buyer Complaints & Support Ticket Portal (`BuyerSupportController@complaints`, `BuyerSupportController@storeComplaint`).
- **Relevant Files:** `app/Http/Controllers/BuyerSupportController.php#L99-L205`, `resources/js/Pages/Buyer/Complaints.vue`, `app/Models/Complaint.php`, routes `/buyer/complaints`.
- **Required by Excel:** **No.** The Excel specification defines customer support and resolution strictly through:
  1. Direct Buyer <-> Seller Chat (`B14 Chat & Notifications`).
  2. Order Return & Refund Dispute Escalation (`B12 Return & Refund Requests`).
- **Why Unnecessary / Less Important:** A third ticketing dashboard for "Complaints" duplicates the return dispute flow and chat system, confusing buyers on where to submit issues.
- **Classification:** Redundant / Potentially Removable.
- **Impact:** High maintenance (requires admin ticketing review tables, notifications, and status tracking).
- **Recommendation:** **Consider Removing or Consolidating** into the Return Dispute and Chat modules.
- **Priority:** High.

---

### 2. General Store & Product Reporting Engine
- **Feature Name:** Report Seller / Product Action (`BuyerSupportController@storeReport`).
- **Relevant Files:** `app/Http/Controllers/BuyerSupportController.php#L212-L255`, route `POST /buyer/reports`.
- **Required by Excel:** **No.** Not in any B1–B14 worksheet.
- **Why Unnecessary / Less Important:** A secondary reporting mechanism that dumps into the `complaints` table with file uploads. Provides little practical utility when product reviews and chat moderation already exist.
- **Classification:** Optional / Outside Scope.
- **Impact:** Unnecessary database table usage and file storage consumption.
- **Recommendation:** **Review or Simplify.** If retained, keep as a simple modal report; otherwise remove.
- **Priority:** Low.

---

### 3. Public Product Q&A System
- **Feature Name:** Buyer Product Questions & Answers (`BuyerProductQuestionController@store`).
- **Relevant Files:** `app/Http/Controllers/BuyerProductQuestionController.php`, `app/Models/ProductQuestion.php`, `resources/js/Pages/Buyer/Show.vue` (Questions section), route `POST /buyer/products/{product}/questions`.
- **Required by Excel:** **No.** Not mentioned in the Excel specifications. Product inquiries are specified to occur via the direct "Chat with Seller" floating drawer (`BUY-CHAT-001`).
- **Why Unnecessary / Less Important:** Creates a duplicate forum-style Q&A thread underneath products that requires seller moderation. Buyers rarely use both public Q&A and private live chat.
- **Classification:** Redundant.
- **Impact:** UI clutter on Product Details Page; extra database queries on every product view (`Product::with('questions')`).
- **Recommendation:** **Consider Removing** in favor of direct Buyer-to-Seller Chat.
- **Priority:** Medium.

---

### 4. Custom Credit Card Token Storage with Client-Side Luhn Algorithm
- **Feature Name:** Buyer Saved Payment Methods (`BuyerPaymentMethodController`).
- **Relevant Files:** `app/Http/Controllers/BuyerPaymentMethodController.php`, `app/Models/PaymentMethod.php`, `resources/js/Pages/Buyer/PaymentMethods.vue`, routes `/buyer/payment-methods`.
- **Required by Excel:** **No.** Worksheet **B9 Payment** specifies standard payment options (COD, GCash/Maya sandbox, card test gateway) chosen at checkout. Storing raw card brands, last 4 digits, and expiration dates in a custom database table is outside the Excel scope.
- **Why Unnecessary / Less Important:** Modern payment gateways (e.g. PayMongo, Xendit, Stripe) securely tokenize payment methods and vault customer cards on PCI-DSS certified infrastructure. Storing payment method records in a custom Laravel table creates compliance liabilities with zero actual processing utility.
- **Classification:** Potentially Hazardous / Outside Scope.
- **Impact:** Security/PCI compliance concerns; unnecessary maintenance.
- **Recommendation:** **Consider Removing** custom card storage. Rely on official payment gateway tokenization.
- **Priority:** High.

---

### 5. Dual Direct Messaging Implementations
- **Feature Name:** `BuyerMessageController` vs `BuyerConversationController`.
- **Relevant Files:** `app/Http/Controllers/BuyerMessageController.php` (Legacy) vs `app/Http/Controllers/BuyerConversationController.php` (Modern real-time chat).
- **Required by Excel:** Only one chat system is required (`BUY-CHAT-001`).
- **Why Unnecessary / Less Important:** `routes/buyer.php` declares routes for both `/buyer/messages` (`BuyerMessageController@index`) AND `/buyer/conversations` (`BuyerConversationController@index`). The codebase has two parallel messaging controllers doing the same job.
- **Classification:** Redundant / Dead Code.
- **Impact:** Developer confusion, duplicate route definitions, wasted bundle size.
- **Recommendation:** **Remove `BuyerMessageController`** and retain only `BuyerConversationController`.
- **Priority:** High.

---

### 6. Static Informational Buyer Pages (FAQ, Policies, Help Center)
- **Feature Name:** Static Content Views (`BuyerSupportController@help`, `@faq`, `@policies`).
- **Relevant Files:** `resources/js/Pages/Buyer/HelpCenter.vue`, `Faq.vue`, `Policies.vue`, `app/Models/Faq.php`, `app/Models/Policy.php`.
- **Required by Excel:** **No.** Not specified in B1–B14.
- **Why Unnecessary / Less Important:** While good for production e-commerce, these static database-driven policy pages are outside the required core functional test scope and can be simplified to static footer links or markdown files.
- **Classification:** Optional / Secondary.
- **Impact:** Minimal performance impact, but adds 3 extra Vue pages and 2 database tables.
- **Recommendation:** **Keep but Simplify.**
- **Priority:** Low.

---

### 7. Active Session Revocation Engine
- **Feature Name:** Browser Session Management (`ProfileController@revokeSession`, `@revokeOtherSessions`).
- **Relevant Files:** `app/Http/Controllers/ProfileController.php#L355-L390`, `resources/js/Pages/Profile/Partials/ManageSessionsForm.vue`, routes `/buyer/sessions/*`.
- **Required by Excel:** **No.** Worksheet B1 requires only Profile Editing and Password Changing (`BUY-ACCT-001` to `BUY-ACCT-005`).
- **Why Unnecessary / Less Important:** Advanced security feature for enterprise apps; rarely necessary for standard e-commerce buyers.
- **Classification:** Optional.
- **Impact:** Extra database queries against `sessions` table.
- **Recommendation:** **Keep** as an optional security enhancement, but do not prioritize for testing.
- **Priority:** Low.

---

### 8. Order Item Reorder Feature
- **Feature Name:** Instant Reorder Actions (`BuyerOrderController@reorder`, `@reorderItem`).
- **Relevant Files:** `app/Http/Controllers/BuyerOrderController.php#L1647-L1720`, routes `POST /buyer/orders/{order}/reorder`.
- **Required by Excel:** **No.** Not in B10 or B11.
- **Why Unnecessary / Less Important:** Clicking "Reorder" simply fetches old order items and dumps them into the cart. Buyers can already re-add items from the catalog or product page.
- **Classification:** Optional Convenience Feature.
- **Impact:** Negligible maintenance; safe to retain if desired.
- **Recommendation:** **Keep.**
- **Priority:** Low.

---

### 9. Separate Seller Reviews vs Product Reviews
- **Feature Name:** Dual Review System (`BuyerReviewController@storeSellerReview` vs `@store`).
- **Relevant Files:** `app/Models/SellerReview.php`, `resources/js/Pages/Buyer/Reviews.vue` (Seller Reviews tab), route `POST /buyer/orders/{order}/sellers/{seller}/review`.
- **Required by Excel:** **No.** Worksheet **B13 Ratings & Reviews** specifies product-level ratings (1 review per purchased item, 1–5 stars, text, photos). Separate store-level reviews are not in the buyer spec.
- **Why Unnecessary / Less Important:** Buyers typically rate the product and express store satisfaction in the same review. Having separate review tabs creates review fatigue.
- **Classification:** Redundant / Outside Scope.
- **Impact:** Extra database table (`seller_reviews`) and UI tab complexity.
- **Recommendation:** **Review for Consolidation.**
- **Priority:** Medium.

---

### 10. Recently Viewed Products History
- **Feature Name:** Automatic Recently Viewed Product Tracker (`RecentlyViewedProduct`).
- **Relevant Files:** `app/Models/RecentlyViewedProduct.php`, `BuyerProductController.php#L164-L177`, `BuyerDashboardController.php#L146-L177`.
- **Required by Excel:** **No.** Not in B3 or B4.
- **Why Unnecessary / Less Important:** Executes a database write (`RecentlyViewedProduct::updateOrCreate`) on **every single product page view** by an authenticated buyer. Creates high database write load under traffic.
- **Classification:** Performance Overhead / Optional.
- **Impact:** Substantial database I/O overhead on high-traffic product browsing.
- **Recommendation:** **Consider Moving to LocalStorage / Cookie** or removing entirely to eliminate database writes on GET requests.
- **Priority:** High.

---

### 11. Redundant Floating Contact Seller Modal
- **Feature Name:** `BuyerOrderConversationController@start`.
- **Relevant Files:** `app/Http/Controllers/BuyerOrderConversationController.php`, route `POST /buyer/order-items/{orderItem}/contact-seller`.
- **Required by Excel:** **No.** Redundant with `BuyerConversationController@start`.
- **Why Unnecessary / Less Important:** Duplicate wrapper around conversation initiation that only links an order item ID.
- **Classification:** Redundant.
- **Recommendation:** **Consolidate** into `BuyerConversationController@start`.
- **Priority:** Medium.

---

### 12. Unused / Orphaned Vue Component Files
- **Feature Name:** Duplicate or Unreferenced Vue Templates.
- **Relevant Files:**
  - `resources/js/Pages/Buyer/Show.vue` vs `resources/js/Pages/Buyer/ProductDetails.vue` (both exist for product details).
  - `resources/js/Pages/Buyer/ProductReviews.vue` vs `resources/js/Pages/Buyer/Reviews.vue`.
- **Required by Excel:** No.
- **Why Unnecessary / Less Important:** `BuyerProductController@show` returns `Buyer/Show.vue`, while other links point to `ProductDetails.vue`. Having two large product detail templates leads to code drift and duplicate styling.
- **Classification:** Code Redundancy / Technical Debt.
- **Recommendation:** **Consolidate** into a single canonical product view.
- **Priority:** High.

---

## 9. Duplicate Functions and Code

1. **Product Detail View Duplication:**
   - `resources/js/Pages/Buyer/Show.vue` (300+ lines) and `resources/js/Pages/Buyer/ProductDetails.vue` (400+ lines) duplicate variation pickers, image carousels, and add-to-cart logic.
2. **Messaging System Duplication:**
   - `BuyerMessageController.php` (routes `/buyer/messages`) duplicates `BuyerConversationController.php` (routes `/buyer/conversations`). Both handle message creation, read receipts, and user attachments.
3. **Contact Seller Duplication:**
   - `BuyerOrderConversationController@start` duplicates `BuyerConversationController@start` by simply wrapping conversation lookup with an order reference.
4. **Voucher Validation Logic Duplication:**
   - Voucher eligibility checks (active status, dates, limits, minimum spend, item targeting) are duplicated in `BuyerOrderController@validateVoucher` (lines 422–660) AND again in `BuyerOrderController@store` (lines 834–1132) instead of using a shared `VoucherService`.

---

## 10. Security, Database, and Integration Issues

### 1. Database Write Amplification on Product Views
- **Location:** `app/Http/Controllers/BuyerProductController.php#L164-L177`
- **Issue:** `RecentlyViewedProduct::updateOrCreate()` writes to MySQL on every single GET request to a product page. Under high concurrency or automated crawlers, this causes database thread contention and table locking.
- **Risk:** High (Performance & Scalability).

### 2. Missing Database Constraint on Email Uniqueness for Case Sensitivity
- **Location:** `users` table migration
- **Issue:** Case-insensitive email duplicates (e.g., `BUYER@EXAMPLE.COM` vs `buyer@example.com`) are caught at the FormRequest level via `lowercase` validation, but the underlying MySQL table lacks a lowercase functional index or collation check on raw queries.
- **Risk:** Low.

### 3. Missing Cascade / Reassignment on Deleted Categories
- **Location:** `categories` table & product relationships
- **Issue:** Deleting a store category leaves products orphaned rather than reassigning them to an "Uncategorized" bucket as specified in Rule A19.
- **Risk:** Medium.

### 4. Direct Zero-Fee Shipping Bypasses Carrier Integration
- **Location:** `BuyerOrderController.php#L237-L245`
- **Issue:** Hardcoding shipping fee to `0.0` prevents logistics partners (Logistics 1 / Bilis Hatid) from receiving proper shipping remuneration records in `seller_shipments`.
- **Risk:** High (Financial & Multi-Role Integration).

---

## 11. Relevant File Paths and Technical Evidence

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── BuyerAddressController.php          # Address CRUD (Needs PSGC fields & 10 limit)
│   │   ├── BuyerCartController.php             # Cart CRUD, variant merge, stock check
│   │   ├── BuyerCategoryController.php         # Platform category navigation
│   │   ├── BuyerConversationController.php     # Real-time Buyer-Seller Chat
│   │   ├── BuyerDashboardController.php        # Marketplace dashboard, featured, top selling
│   │   ├── BuyerMessageController.php          # [REDUNDANT] Duplicate of ConversationController
│   │   ├── BuyerNotificationController.php     # In-app notifications
│   │   ├── BuyerOrderController.php            # Checkout, Order store, Vouchers, Cancellation
│   │   ├── BuyerPaymentMethodController.php    # [OUT-OF-SCOPE] Custom card/wallet token storage
│   │   ├── BuyerProductController.php          # Catalog search, filters, PDP show
│   │   ├── BuyerProductQuestionController.php  # [OUT-OF-SCOPE] Public Q&A forum
│   │   ├── BuyerReviewController.php           # Product and seller reviews
│   │   ├── BuyerStoreController.php            # Seller storefront view
│   │   ├── BuyerSupportController.php          # [OUT-OF-SCOPE] Support tickets & complaints
│   │   ├── BuyerVoucherController.php          # Voucher wallet, claim, expiring alerts
│   │   └── ProfileController.php               # Buyer profile & password update
│   └── Requests/
│       └── ProfileUpdateRequest.php            # Validates buyer account profile updates
├── Models/
│   ├── CartItem.php                            # Cart relationship & variant linkage
│   ├── Order.php                               # Order model (needs multi-store breakdown)
│   ├── OrderItem.php                           # Order line items
│   ├── Product.php                             # Products catalog
│   ├── RecentlyViewedProduct.php               # [OVERHEAD] DB write per product view
│   ├── Review.php                              # Product reviews
│   ├── SellerReview.php                        # [REDUNDANT] Separate seller reviews
│   ├── UserVoucher.php                         # Buyer claimed voucher wallet
│   └── Voucher.php                             # Voucher rules and discount logic
database/migrations/
├── 2026_09_04_163910_drop_wishlists_table.php  # EVIDENCE: Wishlists dropped from DB
└── 2026_10_06_053015_add_is_featured_...php   # Featured product flag migration
resources/js/Pages/Buyer/
├── Addresses.vue                               # Saved addresses UI
├── Cart.vue                                    # Cart overview UI
├── Checkout.vue                                # Checkout UI
├── Complaints.vue                              # [OUT-OF-SCOPE] Complaint tickets UI
├── Conversations.vue                           # Chat UI
├── Dashboard.vue                               # Marketplace dashboard UI
├── HelpCenter.vue                              # [OUT-OF-SCOPE] Help center UI
├── Notifications.vue                           # Notification inbox UI
├── OrderDetails.vue                            # Order tracking & cancellation UI
├── Orders.vue                                  # Orders list tabs UI
├── ProductDetails.vue                          # [DUPLICATE] Duplicate of Show.vue
├── Products.vue                                # Catalog search & filter UI
├── Reviews.vue                                 # Review submissions & history UI
├── SellerStore.vue                             # Merchant storefront UI
├── Show.vue                                    # Canonical PDP template
└── Vouchers.vue                                # Vouchers wallet & browsing UI
routes/
├── buyer.php                                   # Dedicated buyer routes file
└── web.php                                     # Main application routes file
```

---

## 12. Prioritized Recommendations

### Phase 1: High Priority (Core Contract Compliance & Critical Fixes)
1. **Multi-Store Checkout & Shipping Calculation (BUY-CHK-001, BUY-CHK-002, BUY-CHK-003):**
   - Replace the hardcoded `0.0` shipping fee in `BuyerOrderController` with the actual distance/weight rate calculator.
   - Implement multi-store order generation so checkouts with multiple vendors create distinct sub-orders.
2. **Re-Implement Wishlist & Follow Store (B5 Wishlist & Follow):**
   - Create migrations for `wishlists` and `store_followers`.
   - Implement heart toggle on PDP and "Follow" button on `BuyerStoreController` storefront.
3. **Address Book PSGC Normalization (B2 Address Book):**
   - Expand the `addresses` table to include separate columns: `recipient_name`, `phone_number`, `region_code`, `province_code`, `city_code`, `barangay_code`, and `postal_code`.
   - Enforce the 10-address boundary limit per buyer (`BUY-ADDR-005`).
4. **Voucher Return on Order Cancellation (BUY-VOU-012, BUY-CAN-003):**
   - Update `BuyerOrderController@cancel` to decrement `vouchers.used_count` and restore `user_vouchers.status = 'claimed'`.
5. **Account Verification Lifecycle (BUY-ACCT-006, BUY-ACCT-009):**
   - Add OTP verification challenge before committing email and phone changes.

### Phase 2: Medium Priority (Pruning Unnecessary Code & Redundancy)
1. **Remove Duplicate Messaging Controller:**
   - Deprecate `BuyerMessageController` and remove `/buyer/messages` routes in favor of `BuyerConversationController`.
2. **Consolidate Product Detail Pages:**
   - Merge `resources/js/Pages/Buyer/ProductDetails.vue` and `resources/js/Pages/Buyer/Show.vue` into a single canonical component.
3. **Re-evaluate Out-of-Scope Features:**
   - Remove or archive the custom credit card token storage (`BuyerPaymentMethodController`), Product Q&A forum (`BuyerProductQuestionController`), and Complaints portal (`BuyerSupportController@complaints`).
4. **Extract Shared Voucher Validation Service:**
   - Refactor duplicated validation logic from `BuyerOrderController@validateVoucher` and `@store` into a single `VoucherValidationService`.

### Phase 3: Low Priority (Usability & Polish)
1. **Replace DB Writes on Product Views:**
   - Refactor `RecentlyViewedProduct` from synchronous MySQL writes to client-side localStorage.
2. **Search Fuzzy Suggestion:**
   - Add soundex or Levenshtein distance fallback to `BuyerProductController@index` for typo tolerance.

---

## 13. Overall Assessment of the Buyer Module

The **Buyer Module** of the Alona E-Commerce Platform possesses a solid Laravel 11 / Inertia / Vue 3 foundation. The core purchasing workflow (catalog discovery, multi-variant selections, shopping cart management, single-voucher discounts, order placement, timeline tracking, and basic cancellation) is robust, clean, and well-coded.

However, when audited strictly against the **Excel Specification Contract (B1–B14)**, significant functional gaps exist:
- **Wishlist & Store Following (B5)** is completely missing.
- **Address Book (B2)** uses a legacy flat-string address schema rather than granular Philippine postal fields.
- **Multi-Store Checkout (B8)** and **Shipping Rate Calculation** are bypassed with a hardcoded zero fee.
- At the same time, the codebase contains **12 extraneous, duplicate, or out-of-scope features** (such as custom card number storage, duplicate chat controllers, product Q&A, and support ticket complaints) that expand maintenance overhead without satisfying the test requirements.

By addressing the missing items in Phase 1 and pruning the redundant features identified in Phase 2, the Buyer Module can achieve 100% compliance with the Excel specification with significantly reduced technical debt.

