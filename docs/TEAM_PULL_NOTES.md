# Logistics and API integration handoff

Shared branch: `feature/sorting-center`. Published under the user's explicit
authorization on 2026-10-16. Includes Logistics registration/OTP/admin review,
Rider API/profile/notifications, delivery finance and Seller delivery integration,
plus shared Buyer authentication compatibility fixes and regression tests.

## Pull and build

Pull this branch using your normal Git workflow, preserving any local changes.
Keep your own `.env`; no environment file, private upload, local recovery backup
or database dump is included. Install dependencies from the existing lockfiles
when needed, then run `npm run build`; generated `public/build` files are ignored.

Three new migration files accompany this change:

- `2026_10_15_100000_create_seller_shipment_finance.php`
- `2026_10_16_100000_create_rider_notifications_table.php`
- `2026_10_16_110000_create_logistics_registrations_table.php`

Review your own database's migration status before applying pending migrations.
Do not rerun completed migrations, reset/reseed data or copy another developer's
configuration. The author reports these migrations already applied locally;
publishing this code does not run them on anyone's database. Fresh MySQL bootstrap
has a known existing migration-order issue described in DELIVERY_FINANCE_DEPLOYMENT.md.

## Verified evidence and remaining acceptance

Latest executed checks: Logistics60 tests/700 assertions, Buyer37/283,
frontend21, production build, real-transport browser11 and component UI8 PASS.
Database tests use isolated SQLite memory; they do not prove InnoDB concurrency.
Earlier finance/Rider evidence is recorded in DELIVERY_FINANCE_DEPLOYMENT.md and
RIDER_FUNCTIONALITY.md. No code changed after the latest verified checkpoint
except these pull notes.

Live SMTP, web upload limits and the original full-page registration stall still
need local acceptance. Registration should POST to `/register/logistics`, open
email OTP and enter Admin Registration Review only after email verification.
The page now shows transport/server errors and releases indefinitely pending
submissions. See LOGISTICS_REGISTRATION_HANDOFF.md and the 44-case test matrix.
