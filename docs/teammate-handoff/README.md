# Alona teammate handoff

Prepared: 12 October 2026 (Asia/Manila)
Review baseline: c84636a5fcc1dbd8700b05e3e5c2bf318da2904e

This package documents the current local changes. Code has not been committed, pushed or merged by this export. Teammates should obtain the matching application branch before updating their database. The reference screenshots were used for behavior; Alona's existing branding and registration flows are retained.

## Package contents

- README.md: this complete implementation and setup guide.
- CHANGED_FILES.md: inventory of modified and added implementation files.
- ALL_CHANGES.patch: full tracked and new-file diff against the baseline above, for code review. Includes the PSGC snapshot. Prefer receiving the branch through Git; do not apply this patch over the same changes again.
- 00-mysql-preflight.sql: read-only database checks before a manual import.
- 01-mysql-manual-update.sql: alternative MySQL 8.0+ update for the three new migrations, including conservative legacy coverage backfill and Laravel migration records.
- PARTNER_ONBOARDING.md: detailed routes, validation, operational integration and test history.
- PSGC_DATA_README.md: address-data provenance and refresh instructions.

No real .env, database dump, uploaded identity documents, credentials, or development accounts are included. The patch includes .env.example placeholders and synthetic test fixtures. PHP upload configuration was not changed.

## What changed

### Signup entry points and existing design

- Header Sign Up offers Buyer and Seller only; Next requires a selection.
- Become a Seller opens the existing modal with Seller selected.
- Become a Logistics Partner opens /register/logistics directly.
- Become a Rider opens /register/rider directly.
- The three footer entry points appear side by side on desktop and stack on mobile.
- Login stays shared. The modal retains focus handling, body scroll locking, Google sign-in, and the exact Already have an Alona account? Log in text.

### Philippine address selection for all four roles

- One reusable Vue component serves Buyer, Seller, Rider and Logistics registration.
- Four tabs: Region, Province, City, Barangay. Selecting advances automatically; Barangay closes the panel and displays the complete selected location.
- Region options are exactly Metro Manila, Mindanao, North Luzon, South Luzon, Visayas, in that order.
- Metro Manila skips Province. Other independent cities use the clearly labeled Cities / municipalities without a province option.
- Parent changes reset children. Options are loaded dynamically using the existing Laravel /api/addresses API, with loading states, retry and protection from stale responses.
- Geographic manual/search inputs were removed from the address selector; house/street/building/subdivision fields remain. The separate service-coverage selector retains its own behavior.
- Official PSGC codes stay beneath these UI groups. The server validates group and every geographic ancestor, derives canonical names, and saves group/codes/version in users.address_geography. Partner applications also keep their structured address.
- Dataset: 18 official regions, 82 provinces, 1,642 cities/municipalities, 42,010 barangays. It is a complete Q2 2026 publication mirror with the authoritative Q3 2026 name correction, version 2026-Q3. See PSGC_DATA_README.md for the derivation and PSA download limitation.
- North Luzon: Ilocos, Cagayan Valley, Central Luzon, CAR. South Luzon: CALABARZON, MIMAROPA including Palawan, Bicol. Visayas: Western/Central/Eastern Visayas and NIR. Mindanao: Zamboanga, Northern Mindanao, Davao, SOCCSKSARGEN, Caraga, BARMM. Metro Manila: NCR. All official regions occur once.

### Logistics and Rider registration/integration

- Reuses existing sorting_center/rider roles, SortingCenter/Rider profiles and operational relationships. No replacement Logistics dashboard was created.
- Partner registration creates user, profile and application transactionally. Failed uploads are cleaned up.
- Contact, age, business applicability, document MIME/size, consent, addresses and applicable vehicle/license information are validated on the server.
- Partner documents are private; downloads require the owner or authorized reviewer. Historical public uploads are not relocated automatically.
- New Logistics accounts start pending. Approved account + approved center + verified email are required for dashboard access. Login goes to /sorting-center/dashboard only after these conditions are met; otherwise /application/status.
- Application Status exposes verification/resend, approval state, rejection reason and allowed profile updates.
- Logistics registration records actual coded service cities. An approved eligible partner can publish coverage in the existing Delivery Management page.
- Rider delivery cities must be served by eligible Logistics Partners; the chosen partner must cover every selected city. Unsupported combinations are blocked on frontend and backend. The home address is independent of delivery coverage.
- The chosen rider partner and existing rider_areas pivots are saved. Rider preferred schedule was removed; Logistics operating hours remain.
- Rider page expansion was explicitly deferred. The current branch contains a basic assigned-parcel page from earlier onboarding work; a complete teammate Rider UI/workflow still needs coordination. These latest reliability fixes do not expand that page.

### Authentication and Google

- Password/Google login destinations are determined by backend role and approval state; cross-role intended URLs are discarded.
- New Google identities remain in the server session until the chosen form is complete. Existing identities keep their role; identity collisions are rejected. Verified Google identities satisfy email verification and need no registration password.
- Operational Logistics pages and APIs enforce approval/role permissions. The web portal receives a two-hour scoped Sanctum identity from its authenticated web session.
- Admin review shows application details and authorized document links. Approval/rejection updates both user and actual partner profile.

### Reliability fixes

- Web portal Logout calls web logout directly, ending the session and revoking its token even if expired. Other sessions' tokens remain. API-only logout accepts an already-invalid token; failed web logout preserves credentials and displays an error.
- Approval/rejection email delivery exceptions are logged instead of returning HTTP 500 after the decision was already saved. This does not retry failed mail or guarantee delivery.
- User's existing rejection_reason field is now allowed for assignment, so applicants actually receive the saved reason. No new column was needed.
- Independent-city lookup is separate from province-specific lookup, so a quick province selection on a slow connection does not hide the independent-city option later.

## Database changes

| Migration | Additive change |
| --- | --- |
| 2026_10_12_000001_create_partner_applications_table | Creates partner_applications: unique user foreign key, role, details/address/documents JSON, consent timestamps, created/updated timestamps. |
| 2026_10_12_000002_add_city_codes_to_service_areas | Adds nullable areas.city_code, a lookup index, and unique center/city index. Backfills globally unambiguous normalized city names only, keeping first matching area per center/city. |
| 2026_10_12_000003_add_address_geography_to_users | Adds nullable users.address_geography JSON for group, official codes and dataset version. |

No existing address is rewritten, no account is automatically approved, and no rider/parcel relationship is deleted. Ambiguous/custom legacy service areas stay uncoded until the partner confirms cities through Delivery Management.

These three migrations require the existing application tables. The earlier five 2026_10_11_100000 through 2026_10_11_100004 Logistics migrations are already in the project baseline; a teammate who lacks them must run the project's migrations first. The manual SQL is an incremental update, not a replacement database dump.

## Recommended setup: use Laravel migrations

1. Obtain the matching application branch and back up the teammate database.
2. Keep the teammate's existing .env and APP_KEY. Configure their own MySQL DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD; do not copy someone else's credentials or rotate an existing key.
3. In the project directory run:

~~~powershell
composer install
npm.cmd ci
php artisan config:clear
php artisan migrate:status
php artisan migrate
npm.cmd run build
~~~

Use npm instead of npm.cmd outside Windows. If a deployment environment requires force, use php artisan migrate --force after reviewing pending migrations. Do not use migrate:fresh or migrate:refresh on a database containing teammate data.

For a brand-new local checkout only: create .env from .env.example if missing and generate APP_KEY with php artisan key:generate only if no existing key is configured. For live development run php artisan serve and npm.cmd run dev in separate terminals.

Laravel migrations are the preferred database update because they run the PHP normalization/backfill and record completion automatically. MySQL 5.7/MariaDB users should use the migrations with a database version supported by the project's Laravel version, rather than assuming the optional MySQL 8 script is portable.

## Alternative: manually import the MySQL SQL

Use this only when all three new migrations are unapplied. Choose ONE workflow: Laravel migrations OR the manual SQL, never both for the same changes.

1. Back up the database. Select the correct existing database in MySQL Workbench or your database tool.
2. Execute 00-mysql-preflight.sql. Confirm MySQL 8.0+, existing users/areas/sorting_centers/migrations tables, users.id BIGINT UNSIGNED, and an existing rejection_reason column. Confirm zero rows for the three migration names and that partner_applications, areas.city_code and users.address_geography do not exist yet.
3. If any of the changes are already applied or partially applied, stop and use Laravel migration/status inspection; do not blindly import this one-time script.
4. Execute 01-mysql-manual-update.sql in one session with stop-on-error enabled. The backfill uses temporary tables, so keep the same connection. Do not use mysql --force or continue after an error.
5. The script creates all three additions, backfills only unambiguous service names and finally records these three migrations in a new Laravel batch. Only record them after every earlier statement has succeeded.
6. Verify its final result queries, then run php artisan migrate:status and php artisan config:clear in the matching code checkout. These three migrations should show Ran.

MySQL schema changes implicitly commit; this is not an all-or-nothing transaction. If an import fails, inspect/restore the backup before rerunning. The SQL was generated and reviewed against the migration files and bundled dataset; it was NOT executed against a live MySQL server here. Local migration tests used SQLite, so test the manual SQL on a disposable copy of the teammate's MySQL database first.

SQL references: [MySQL regular expression functions](https://dev.mysql.com/doc/refman/8.0/en/regexp.html) and [implicit commits](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html).

## Required account configuration

- Set a real mail transport for verification and approval notification delivery. MAIL_MAILER=log is useful locally but writes verification links to storage/logs rather than sending email.
- To enable Google, set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI and register the exact callback with Google. Keep localhost or 127.0.0.1 consistent for app URL/cookies/callback. Without credentials, email registration still works.
- After .env changes run php artisan config:clear.
- Publish valid Logistics coverage: register a center with cities, verify and approve it; or have an eligible existing partner save coverage through Delivery Management. No eligible published coverage means no served-city options for Rider registration.
- PHP upload limits were deliberately left unchanged. The application validates up to 5 MB per document; the review machine's PHP is still configured at 2 MB/file and 8 MB/post. This package does not modify php.ini.

## Verification and known limits

- Latest reliability regression check: 26/26 backend tests passed, including approval/rejection mail outages, rejection-reason persistence, expired-token web logout and existing login/address behavior.
- Shared registration/address/coverage check before these reliability fixes: 39/39 targeted PHP tests passed.
- Latest frontend suite: 19/19 passed, including slow independent-city lookup, expired-token logout and failed-logout handling.
- Production frontend build, PHP formatting and git diff --check passed.
- Actual address browser checks completed selections in every group and all four registration forms. At 390 px: no horizontal overflow or manual geographic inputs; no JavaScript exceptions.
- Most recent full-suite run BEFORE the added reliability regression tests: 131 tests, 125 passed, five failures and one error. Do not describe the full suite as green. Later targeted tests are separate counts, not a new full-suite run.
- Six known baseline problems remain: missing buyer.wishlist route in BuyerGuestAccessTest; ExampleTest lacks migrated tables; two seller-product tests submit unsupported status; GuestMarketplaceTest expects Vue-rendered stock text in initial Inertia HTML; OrderVariantFlowTest omits shipping_method and selected_items. Details appear in PARTNER_ONBOARDING.md.
- Google tests mock provider identity; real Google credentials and production mail delivery were not exercised here.

## Teammate review checklist

- Review CHANGED_FILES.md and ALL_CHANGES.patch alongside the matching branch.
- Apply pending migrations once using the chosen method.
- Build assets after pulling the Vue changes.
- Register Logistics, check pending status, verify email, approve from Admin, then log in to the real Logistics dashboard.
- Test service-city publishing and same-named cities without replacing operational area IDs.
- Confirm private document access permissions and invalid address rejection.
- Try Logout with an expired token; verify web session ends and another session's token survives.
- Coordinate merges where teammates are changing the same registration, approval or Logistics files.
