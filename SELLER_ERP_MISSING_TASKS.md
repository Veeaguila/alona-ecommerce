# Seller ERP Missing-Feature Tasks

Each checkbox is a separate task with a short description. Fully implemented functions such as basic product creation, single-image uploads, basic order status updating, store profile editing, and basic daily sales reporting are not repeated here.

## Dashboard and Overview

- [x] **SELLER-01: Order status metric counters.** Add dedicated metric cards for Pending, To Ship, In Transit, Delivered, Cancelled, and Returned orders.
- [x] **SELLER-02: Recent reviews widget.** Show the latest customer reviews and ratings feed directly on the dashboard.
- [x] **SELLER-03: Recent notifications widget.** Show real-time store alerts, new orders, and system announcements on the dashboard.
- [x] **SELLER-04: Out-of-stock & low-stock indicators.** Add an out-of-stock alert counter alongside the existing low-stock list.
- [x] **SELLER-05: Net profit & margin estimation.** Calculate estimated net profit margin alongside gross sales on the dashboard.

## Product Management

- [x] **SELLER-06: Delete and archive products.** Add permanent delete and soft-archive actions with confirmation modals.

## Inventory Management

- [ ] **SELLER-12: Reserved vs available stock tracking.** Separate reserved checkout stock from available inventory.
- [x] **SELLER-13: Stock adjustment audit logging.** Log stock-in, stock-out, damages, returns, and manual adjustments with notes.
- [ ] **SELLER-14: Inventory history and timeline.** Add an inventory movement ledger showing every quantity change over time.
- [ ] **SELLER-15: Bulk stock updates.** Add a multi-product and multi-variant quick stock adjustment modal or table.
- [ ] **SELLER-16: Customizable low-stock threshold.** Allow sellers to set custom low-stock alert thresholds per product or variant.

## Order Management and Fulfillment

- [x] **SELLER-17: Waybill / Shipping label printing.** Generate printable carrier waybills with barcodes and customer routing info.
- [x] **SELLER-19: Courier pickup scheduling.** Let sellers schedule courier pickup dates and assign preferred carrier partners.
- [ ] **SELLER-20: Order review link.** Add direct navigation from completed order items to corresponding customer reviews.

## Promotions and Vouchers

- [x] **SELLER-26: Percentage discount cap.** Add a maximum discount amount cap (`max_discount`) for percentage vouchers.
- [x] **SELLER-27: Per-customer usage limit.** Restrict voucher claims and uses per individual buyer account.
- [x] **SELLER-28: Product and category targeting.** Restrict vouchers to specific products or product categories.
- [x] **SELLER-29: Free shipping vouchers.** Support seller-funded free shipping discount vouchers.
- [x] **SELLER-30: Voucher analytics & revenue attribution.** Track total discount cost and gross revenue generated per voucher.
- [x] **SELLER-31: Voucher expiration countdown.** Show real-time active, expiring-soon, and expired status badges.

## Customer Feedback and Reviews

- [x] **SELLER-32: Report inappropriate review.** Allow sellers to report abusive or fraudulent reviews to platform admins.
- [x] **SELLER-33: Seller rating & review management.** View and manage direct store ratings alongside product reviews.
- [x] **SELLER-34: Review media viewer.** Let sellers view customer-uploaded photos and videos in a lightbox modal.
- [x] **SELLER-35: Rating distribution breakdown.** Display visual 1-star to 5-star rating breakdown graphs and statistics.

## Returns and Refunds

- [x] **SELLER-36: Dedicated returns center.** Create a dedicated `/seller/returns` page and controller linked to `OrderReturnRequest`.
- [x] **SELLER-37: Return request status filtering.** Filter requests by Pending, Approved, Rejected, Item Returned, and Refunded.
- [x] **SELLER-38: Seller return decisioning.** Allow sellers to approve or reject return requests with rejection reasons and instructions.
- [x] **SELLER-39: Return parcel tracking.** Track return shipping carrier milestones and return waybill tracking numbers.
- [x] **SELLER-40: Return item inspection & resolution.** Confirm returned item receipt and trigger refund resolution.
- [x] **SELLER-41: Refund history ledger.** Maintain an itemized ledger of all approved and issued refunds with transaction logs.

## Chat and Messaging

- [x] **SELLER-42: Media attachment in chat.** Support sending and viewing images and files in customer chat threads.
- [x] **SELLER-43: Order snippet in chat.** Embed an interactive order reference card in buyer-seller chat threads.
- [x] **SELLER-44: Seller-to-admin support chat.** Provide a direct chat channel between sellers and platform support admins.

## Notifications

- [x] **SELLER-58: Low-stock threshold alerts.** Send automated notifications when product stock falls below the threshold.
- [x] **SELLER-59: New review alerts.** Send automated notifications when a buyer submits a new product or store review.
- [x] **SELLER-60: Return and refund request alerts.** Send immediate notifications when a buyer requests a return or refund.
- [x] **SELLER-61: Payout status change alerts.** Notify sellers when payout requests are approved, processed, or rejected.

## Store Management

- [x] **SELLER-62: Store banner & branding upload.** Allow sellers to upload custom store cover banners and brand assets.
- [x] **SELLER-63: Operating hours schedule.** Configure store operating hours and response time schedules.
- [x] **SELLER-64: Store policies & terms.** Add customizable store shipping, warranty, and return policy pages.
- [x] **SELLER-65: Vacation mode.** Add a toggle to temporarily pause incoming orders and notify store visitors.

## Account and Security

- [ ] **SELLER-66: Login session management.** Let sellers view, monitor, and revoke active login sessions across devices.
- [ ] **SELLER-67: Business verification & document updates.** Allow re-uploading business permits and checking verification status.

## Help and Support

- [ ] **SELLER-68: Dedicated seller help center.** Add a dedicated `/seller/help` page with seller resources and guides.
- [ ] **SELLER-69: Seller FAQ & knowledge base.** Provide a searchable FAQ covering fulfillment, payouts, and penalties.
- [ ] **SELLER-70: Seller dispute & ticket filing.** Allow sellers to file dispute tickets against fraudulent buyers or carrier issues.
- [ ] **SELLER-71: Merchant terms & compliance viewer.** Display active merchant agreements, seller guidelines, and compliance rules.

