## Sorting-center pickup integration

Seller order items marked **Ready for Pickup** previously had no handoff to the sorting-center module. They now create an owned parcel and pending pickup request atomically. Sorting-center confirmation makes the request available to the registered center's rider; physical pickup, arrival, sorting, assignment, and delivery retain the handoff contract from Logistics.

The integration uses `usertype`, links parcels to individual order items, derives buyers and recipient addresses from the owned order, and keeps collection addresses separate. Logistics controllers, messaging models/tables, and the frontend entry point are separate from the existing storefront, Inertia app, authentication, and buyer/seller conversations. Proof files remain private. Admin Applications includes sorting-center approval.

Browser verification identified two integration issues: the pickup-list API's explicit parcel field selection omitted the full delivery and collection addresses, and the existing seller delivery section still showed an unassigned tracking number despite the new parcel reference. Both addresses are now included, and pickup creation populates the order item's existing tracking number and courier fields. Regression tests cover both fixes.

## Validation

- All 66 logistics/integration tests pass.
- All five frontend tests pass; production build and `git diff --check` pass.
- Actual Edge browser walkthrough passed using temporary approved accounts and an isolated SQLite database: storefront seller login, seller Ready for Pickup with a collection-address override, center login, pending queue with both addresses, request confirmation and queue refresh, rider acceptance/physical pickup through the rider API, center lookup/arrival, city-area creation/sorting, and seller status refresh to Shipped.
- Confirmation leaves the parcel `PLACED`; arrival moves it to `AT_SORTING_CENTER`. No browser JavaScript errors were recorded. Screenshots and execution results are retained locally under `.local/browser/` for review.
- Rider actions were exercised through the real API because this repository does not include a rider web application. A walkthrough with the teammates' actual rider application is still outstanding.
- The full suite has 96 tests: 88 pass, seven fail, and one errors. The same eight failing/erroring tests were reproduced against unchanged application files at baseline commit `55feefef9ae89b54180c2486de56696e182420ac`, with integration migrations excluded and the same test runtime/encryption key. They are listed below; no new integration test failures remain.

## Existing test failures to address separately

| Test | Observed failure |
| --- | --- |
| `AuthenticationTest::test_login_screen_can_be_rendered` | Expects HTTP 200; existing `/login` redirects to the storefront with 302. |
| `RegistrationTest::test_seller_registration_can_be_created_with_pending_review` | Submitted registration payload does not create the expected seller row. |
| `ExampleTest::test_the_application_returns_a_successful_response` | In-memory database lacks `categories`; test does not migrate its database. |
| `BuyerGuestAccessTest::test_guests_are_redirected_to_login_from_buyer_account_pages` | References undefined `buyer.wishlist` route. |
| `GuestMarketplaceTest::test_a_seller_listing_is_publicly_browsable_and_searchable` | Seller fixture receives 403; fixture does not have loaded approval status. |
| `GuestMarketplaceTest::test_buyer_product_details_display_seller_and_stock_information` | Expects rendered product text in the server response for an Inertia page. |
| `OrderVariantFlowTest::test_buyer_checkout_creates_order_item_with_variant_metadata` | Omits required shipping method and selected items. |
| `SellerProductApprovalFlowTest::test_seller_products_require_admin_approval_before_buyer_visibility` | Submits an unsupported product status. |

These failures have not been fixed or waived by this PR. They were preserved to keep the current storefront/authentication behavior within the requested integration scope.

## Before merging

- [x] Fix the integration-caused pickup address omission and missing item tracking fields, then verify them in the browser and regression suite.
- [ ] Teammates independently confirm that all eight failures/errors occur on the baseline, with the same test prerequisites.
- [ ] Teammates agree to address them separately and record follow-up owners/issues.
- [ ] Complete the pickup handoff with the teammates' actual rider application.
- [ ] Fix and retest any further failure attributable to the integration before merging.

Five additive migrations must run before using the module. This description is prepared locally; nothing has been committed, pushed, published, or merged.
