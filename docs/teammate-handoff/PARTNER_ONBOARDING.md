# Logistics Partner and Rider onboarding

Implemented in the existing Laravel/Vue application. The screenshots' logo, teal/yellow palette, rounded cards, registration modal, and responsive layout are retained.

## User flows

- Footer: Seller opens the existing modal with Seller selected. Logistics Partner and Rider open their respective registration forms directly. The three footer options appear side by side on desktop and stack on mobile.
- Header Sign Up offers only Buyer and Seller, with Next disabled until a choice is made. Next continues the existing registration steps. The exact `Already have an Alona account? Log in` text appears below Google; Log in switches the existing modal. Partner forms retain their own Google and shared-login entry points.
- Logistics Partners use the existing `sorting_center` role, `SortingCenter` profile, dashboard, and scoped operational APIs. No Logistics dashboard was duplicated.
- Riders use the existing `rider` role, `Rider` profile, approval middleware, and delivery APIs. The basic new Rider dashboard shows actual assigned parcels and links to profile/application status.
- New partner accounts are pending. A normal email account receives a signed verification link; a verified Google identity does not need a password or another email verification. Approval and verification both gate operational access. Pending/rejected/suspended/inactive users remain on application status. Password and Google login also send pending Buyer/Seller accounts to their status page, while existing role middleware continues to block their operational dashboards.
- Riders select one or more cities served by eligible approved Logistics Partners, then choose a required partner that covers **every selected city**. Unsupported cities cannot be selected; if no single partner covers the selected combination, submission is blocked. Home address and delivery-area selections are independent.
- Logistics registration collects searchable PSGC cities for coverage. Initial coverage becomes selectable after application/account approval and, for new partner accounts, email verification. Approved partners can also publish cities from **Delivery Management → Service area coverage → Save service areas** in their existing dashboard.
- Rider registration saves the selected partner and links the rider to the existing service-area records used by parcel assignment. Approval remains required. Preferred schedule has been removed from Rider registration and profile editing; Logistics operating hours remain.

## Files and integration

| Files | Change |
| --- | --- |
| `resources/js/Layouts/GuestLayout.vue`, `Pages/Auth/AuthModal.vue` | Separate partner registration links, Buyer/Seller modal choices, Seller preselection, Google role preservation, login switch, focus trap, body scroll lock, dialog semantics. |
| `Pages/Auth/PartnerRegistration.vue`, `Components/PartnerField.vue`, `Components/PhilippineAddressSelector.vue`, `Components/DeliveryCoverageSelector.vue` | Shared branded form, searchable cascading home addresses, coded delivery-city selection, matching partners, retries, applicable documents, consent and validation. |
| `Pages/Auth/ApplicationStatus.vue`, `Pages/Rider/Dashboard.vue` | Application status, resend verification, contact/schedule updates, actual assigned rider parcels. |
| `app/Http/Requests/PartnerRegistrationRequest.php` | Server validation for contacts, age, documents, license eligibility, consent, unique email, geographic ancestry and served-city/partner relationships. Rider schedule input is excluded. |
| `PartnerRegistrationController.php`, `PartnerProfileController.php`, `Models/PartnerApplication.php` | Transactional user/profile/application creation, failed-upload cleanup, private document authorization, own profile retrieval and updates. |
| `app/Services/PhilippineAddressService.php`, `PhilippineAddressController.php`, `resources/data/*`, `scripts/build-psgc.mjs` | Complete versioned PSGC hierarchy, local cached lookups, server validation and reproducible conversion/provenance. |
| `app/Services/AccountDestination.php`, auth controllers and approval middleware | One backend destination decision; safe login/Google redirects, approval/email checks and signed verification return paths. |
| `AdminApplicationController.php`, `Pages/Admin/Applications.vue` | Existing application review shows all new profile details and authorized private document links; existing approval updates the actual Rider/SortingCenter profile. |
| `routes/logistics.php`, `routes/api.php`, `resources/views/sorting-center/*`, `resources/js/logistics.js`, `Logistics/components/ScLayout.vue` | Existing portal is protected and receives a two-hour Sanctum token from the authenticated web session. It keeps using its real, scoped APIs. Logout revokes the token and ends the web session. |
| `RegistrationDocumentStorage.php`, `.env.example`, tests | New documents are private; Google setup placeholders and behavioral/feature coverage. |
| `DeliveryCoverageService.php`, `DeliveryCoverageController.php`, `Area.php`, `SortingCenter.php`, `Logistics/components/DeliveryManagement.vue` | Published coverage uses existing service-area records with PSGC city codes. Owned coverage publishing, current approval/verification checks, Rider-area links, city/province disambiguation and tests. |

## Routes and API contracts

| Method | Path | Purpose |
| --- | --- | --- |
| GET / POST | `/register/logistics`, `/register/rider` | Branded web registration forms and submission. POST is rate-limited to six requests/minute. |
| POST | `/api/sorting-center/register`, `/api/rider/register` | Existing endpoint paths now reuse the full validated registration service. Clients must send the complete new form fields, geographic codes and applicable documents. |
| GET | `/api/addresses/{groups\|regions\|provinces\|cities\|barangays}` | Cached geography; optional `group` filters provinces/cities by the custom group. `parent` is a region for provinces/cities, or city for barangays. Optional `province` filters cities; an empty value selects independent cities. |
| GET | `/api/delivery-coverage` | Current served cities and eligible approved partners for each city. No-store response; no profile emails or documents are exposed. |
| POST | `/api/sorting-center/coverage` | Approved/verified partner publishes owned city coverage using `coverage_city_codes`; rate-limited to 30 requests/minute. |
| GET | `/application/status` | Own application, account/approval state, verification and permitted destination. |
| GET / PATCH | `/api/partner/profile` | Own profile/status retrieval and contact update with Sanctum authentication; operating hours can be updated for Logistics Partners. No Rider schedule. |
| PATCH | `/partner/profile` | Same safe profile update using web session/CSRF. |
| GET | `/partner/documents/{user}/{document}` | Private file for its owner, approved administrator, or approved assigned center reviewer. No public file URL. |
| GET | `/api/user` | Authenticated current user's safe account fields. |
| GET | `/rider/dashboard` | Basic Rider dashboard; approved, active, verified accounts only. |
| GET | `/sorting-center/dashboard` and existing operational pages | Existing Logistics views; approved and verified account required. |
| GET | `/sorting-center/login`, `/sorting-center/register` | Compatibility redirects to existing login modal and partner registration. |
| Existing | `/login`, `/logout`, `/auth/google`, `/auth/google/callback`, email verification, admin approval, Rider/Logistics operational APIs | Reused; backend account role/status determines destination. Arbitrary cross-role intended URLs are discarded. |

## Addresses

All four registration forms use the reusable `PhilippineAddressSelector.vue` through the existing Laravel API. Its Region tab lists exactly **Metro Manila, Mindanao, North Luzon, South Luzon, Visayas**, in that order. Selection automatically advances through Province, City (including municipalities), and Barangay; the final choice closes the panel and shows the combined location. Previous tabs allow edits and clear dependent codes. Detailed street/house/building inputs remain. Geographic search/manual inputs are removed from the registration address selector; delivery service coverage is a separate feature.

These are Alona UI groups, not replacement PSGC regions. North Luzon includes Ilocos, Cagayan Valley, Central Luzon and CAR; South Luzon includes CALABARZON, MIMAROPA (including Palawan) and Bicol; Visayas includes Western, Central, Eastern Visayas and NIR; Mindanao includes Zamboanga, Northern Mindanao, Davao, SOCCSKSARGEN, Caraga and BARMM. Every official region appears once. Metro Manila skips Province; independent cities elsewhere use the clearly labeled ?Cities / municipalities without a province? option and store no fictional province.

Buyer/Seller now use the same hierarchy validation as partners. The backend derives names from codes and rejects cross-group, cross-province and cross-city barangays, rather than trusting submitted names. Additive migration `2026_10_12_000003_add_address_geography_to_users.php` stores canonical group/codes/version in nullable `users.address_geography` for new registrations. Existing addresses remain untouched. Partner applications also retain their existing structured address.

The complete dataset contains **18 regions, 82 provinces, 149 cities, 1,493 municipalities and 42,010 barangays**. It uses the complete PSA Q2 2026 publication mirror plus the authoritative Q3 2026 geographic name correction, released 12 October 2026. The direct latest PSA workbook download returned HTTP 403; the exact derivation and source links are recorded in [resources/data/README.md](../resources/data/README.md).

The old PSGC Cloud provider was verified but returned only 17 regions; the new partner forms therefore use the complete local snapshot. No API key or runtime external address service is needed. Laravel caches the versioned dataset, and the bundled file works during provider outages. NCR, HUC/ICC cities, Manila sub-municipalities, BARMM SGA and the Isabela statistical parent grouping are handled explicitly. Selected geographic codes are validated server-side and stored together with authoritative names and the version. Parent changes clear children and late network responses are ignored.

## Database and documents

The additive migration `2026_10_12_000001_create_partner_applications_table.php` creates one application per user with a foreign key, structured details/address/documents and consent timestamps. Existing `users`, `sorting_centers`, `riders`, roles, approval states and operational relationships are reused. Existing account, profile and operational data are preserved.

`2026_10_12_000002_add_city_codes_to_service_areas.php` adds an indexed nullable PSGC city code and a unique partner/city constraint to existing `areas`. Existing IDs, names, parcels and Rider-area links are preserved. Only globally unambiguous legacy city names are backfilled; custom or ambiguous names require explicit confirmation in the partner's coverage selector. Old free-text application coverage is not guessed into geographic codes.

Logistics submissions require `coverage_city_codes` (1–100 distinct cities) and `operating_hours`. Rider submissions require `delivery_city_codes` (1–20 distinct cities) and `sorting_center_id`. The server checks that the partner is currently eligible and serves every selected city, then repeats that check under transaction locks before account creation. Removed coverage or suspended partners cannot be accepted from a stale browser list. Successful registration attaches the existing `rider_areas` pivot records; it does not grant operational approval.

New verification uploads are MIME-validated JPG/PNG/PDF files, up to 5 MB each, under the private `local` disk. Bicycle riders need no vehicle/license paperwork. Motorized riders need applicable documents, an unexpired professional license and matching codes; manual review verifies document authenticity and vehicle eligibility. Business documentation is optional for individual operators and required for applicable registered business types. Legacy documents previously stored publicly are not relocated by this additive migration.

## Google and account identity

Socialite retains OAuth state validation. The selected role is stored server-side before redirect. Existing users are found by Google ID/email, identity collisions are rejected, their role is preserved, and their backend status decides access. New identities are held in the session for one hour until the chosen form is complete; no partial buyer account is created for a Logistics/Rider signup. Existing single-role architecture is preserved: an already-registered email cannot silently enroll as another role.

## Setup

The migration and production frontend build have been run locally. On another checkout run:

```powershell
php artisan migrate
npm.cmd run build
```

For local live development use `php artisan serve` and, in a second terminal, `npm.cmd run dev`. Windows PowerShell execution policy blocks `npm.ps1` here; `npm.cmd` works without changing the policy.

Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in `.env` and register that exact callback URI in Google. Use one consistent hostname (`localhost` or `127.0.0.1`) for application URL, cookies and callback. Configure a real mail transport for verification delivery; the current `.env.example` uses `MAIL_MAILER=log` for development. After configuration edits run `php artisan config:clear`. Without credentials, Google sign-in shows a clear setup message and email registration remains available.

The current PHP runtime has `upload_max_filesize=2M` and `post_max_size=8M`. To allow the application's full 5 MB per-file limit with multiple documents, set `upload_max_filesize=5M` (or higher) and `post_max_size=25M` (or higher) in the active `php.ini`, then restart `php artisan serve`. PHP can reject larger uploads before Laravel receives them.

## Verification

Latest address change: all **39 targeted PHP tests passed**, including two additional Buyer/Seller address integration tests after the full-suite run. The production build and PHP formatting passed. The additive address-geography migration was applied locally. Actual browser checks completed addresses in all five groups and rendered the shared selector in Buyer, Seller, Rider and Logistics forms. A 390 px check recorded no horizontal overflow, no geographic manual inputs and no JavaScript exceptions. Current preview: `.local/address-mobile.png`.

- `php artisan migrate --force`: both additive migrations applied to the existing local development database.
- `php vendor/bin/pint --dirty`: changed PHP files formatted successfully.
- `php vendor/phpunit/phpunit/phpunit --do-not-cache-result`: **131 tests, 125 passed, 5 failures and 1 error** after the coverage update. All 33 added onboarding/address/Google/coverage tests pass. The six remaining tests also fail in an isolated copy of the original HEAD, which had 96 tests and eight unsuccessful tests. No test run used the development SQLite database.
- Scoped onboarding/address/coverage/Logistics checks passed before the final added regression cases; the final full suite includes all of them. Coverage tests exercise unsupported areas, mismatched/multi-city partners, suspension and coverage removal during registration, duplicate/unknown codes, private-upload cleanup, owned publishing, preservation of legacy records, verification requirements and same-named cities in different provinces.
- `npm.cmd run test:frontend`: **19/19 passed**, including tab progression, NCR province skip, independent cities, stale responses, retries, modal selection retention and existing delivery coverage behavior.
- `npm.cmd run build`: production Vite build passed.
- `git diff --check`: passed.
- Earlier onboarding browser checks at 1366 px and 390 px confirmed responsive layouts, exact login text, required role selection, NCR skipping province and Quezon City's 142 barangays. The earlier four-card modal screenshots in `.local` precede the updated Buyer/Seller-only modal and direct partner registration links.
- HTTP smoke checks: Logistics registration page returned 200; geographic API returned 18 regions and version `2026-Q3`.
- Coverage browser checks: the real empty directory disables partner selection and Rider submission; Rider schedule is absent; Logistics operating hours remain; the Logistics city selector adds a real PSGC city. Browser-only approved-partner fixtures verified multi-city partner intersection and clearing an incompatible choice without inserting development accounts. At 390 px there was no horizontal overflow and no JavaScript exception was recorded.

The current local directory has zero published eligible coverage cities. This intentionally blocks Rider signup. Register a Logistics Partner with declared cities, verify its email, and approve its application; alternatively an already-approved partner can declare coverage in Delivery Management. Nothing is fabricated to populate the Rider dropdown.

Remaining baseline test issues (not changed as part of onboarding):

| Test | Existing issue |
| --- | --- |
| `BuyerGuestAccessTest::test_guests_are_redirected_to_login_from_buyer_account_pages` | Refers to the removed `buyer.wishlist` route. |
| `ExampleTest::test_the_application_returns_a_successful_response` | Does not create test database tables before querying the homepage. |
| `GuestMarketplaceTest::test_a_seller_listing_is_publicly_browsable_and_searchable` | Submits product status `approved`, which the existing seller product request rejects. |
| `GuestMarketplaceTest::test_buyer_product_details_display_seller_and_stock_information` | Expects rendered Vue text in the initial Inertia HTML response. |
| `OrderVariantFlowTest::test_buyer_checkout_creates_order_item_with_variant_metadata` | Omits required `shipping_method` and `selected_items`. |
| `SellerProductApprovalFlowTest::test_seller_products_require_admin_approval_before_buyer_visibility` | Submits a product status rejected by the existing request. |

The login/registration tests now check the intentional modal redirects; the seller registration fixture supplies the documents already required by its original controller. Logistics registration tests now send the complete new contract and check private storage.

Real Google consent/credential exchange and production email delivery require the above account configuration; OAuth tests mock the verified provider identity.

## Reliability fixes

The Logistics portal ends its authenticated web session directly on Logout; the backend revokes that session's token even when expired, preserving tokens from other sessions. API-only logout treats an already-invalid token as signed out. Failed web logout keeps local credentials and shows an error.

Approval/rejection email failures are logged without turning an already-saved application decision into an HTTP 500. This does not retry or guarantee email delivery; configured mail transport is still required.

The address selector tracks independent-city lookups separately from province-specific cities. Choosing a province before that lookup finishes no longer hides independent cities on returning to the Province tab. Regression coverage exercises the delayed response. PHP upload configuration was not modified.

Application decision regression tests also exposed a missing `rejection_reason` fillable field on User; it is now saved so rejected applicants can see the submitted reason. No database migration is needed for this existing column.
