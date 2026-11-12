# Logistics team update

This release upgrades the existing ALONA application and database. Keep your existing
records. Do not replace your database with another developer's full SQL dump.

AI operators: start with [the root handoff](../TEAMMATE_AI_HANDOFF.md) and
[the migration manifest](upgrade/LOGISTICS_SCHEMA_MANIFEST.json). Teammates can copy
[the bounded upgrade prompt](TEAMMATE_AI_PROMPT.md). Local commit creation does not
publish a branch: the author must push the release before a teammate can fetch it.

Before the author commits, review both staged and unstaged changes and include all
required untracked source, configuration, migrations and tests. Several existing
files have additional working-tree edits beyond their staged versions. Committing
the old index alone does not capture the complete implementation. Keep local backups,
`.env`, signing material and private runtime artifacts out of the commit.

## Before updating

1. Preserve your own working changes and back up the database and uploaded files.
2. Get the reviewed release branch/commit agreed by the team. Do not discard local edits
   or automatically resolve conflicts in favour of either copy.
3. Compare the target database's schema and `migrations` records with the release.
   A table created manually may already exist without a corresponding migration record.
   Resolve any mismatch before running migrations; do not fabricate migration history.
4. Rehearse the upgrade on a separate approved database copy. The teammates' actual
   schema has not been supplied for comparison, so compatibility with it is not yet
   certified. Fresh chronological MySQL bootstrap also has a known pre-existing
   `order_items` / `product_variants` foreign-key ordering issue.

## Install and verify the source

- Use PHP and Node versions compatible with `composer.json` and `package.json`.
- Run `composer install` and `npm ci` against the committed lockfiles.
- Keep your own `.env` and credentials. Compare required settings with `.env.example`.
- Run `node scripts/verify-logistics.cjs`. It forces SQLite `:memory:` and array
  mail/cache/session, and refuses cached application configuration. It does not run
  tests against your application database or clear its configuration.
- Run `npm run test:frontend` and `npm run build`.
- For the actual Vue/Inertia upload retry and transport checks, run
  `node tests/browser/logistics-registration.cjs`. Set `LOGISTICS_TEST_BROWSER` to an
  installed Edge/Chromium executable if it is not found automatically. This uses only
  a loopback HTTP fixture, synthetic files and a separate headless browser profile.
  It includes a real 60-second stalled-request timeout check.

Do not use `composer run setup` as an existing-database upgrade shortcut: it includes
automatic migrations and application-key generation.

## Apply database changes separately

Review `php artisan migrate:status` against the intended database before approving
`php artisan migrate`. These are application database operations, distinct from the
isolated verifier above. Apply only reviewed pending migrations after the backup and
copy rehearsal. Do not use `migrate:fresh`, `migrate:refresh` or a full-database import.
Do not edit already-applied migration files to express a new change.

The Logistics release additions include these migrations; earlier integration
migrations already present in the repository may also be pending on your database:

| Migration | Purpose |
|---|---|
| `2026_10_17_100000_create_rider_decision_audits_table` | Rider decision audit |
| `2026_10_20_120000_create_logistics_workflow_records` | Dispatch, delivery attempts, returns, cash and operation records |
| `2026_10_29_100000_create_logistics_hub_network` | Company hubs, directed routes and booked parcel custody |
| `2026_10_29_150000_create_logistics_hub_staff_table` | Staff membership scoped to an owned hub |
| `2026_10_31_000001_create_rider_job_payments` | Six empty payroll tables and nullable trip link on hub transfers; configured future shipment jobs and grouped manual payouts |

The October 29 review's local database previously had the hub network migration
applied, while the staff migration was still pending. That is a local observation,
not an instruction to mark the same migrations applied on another machine.
The registration retry, dev-server privacy and idle-session fixes need no new schema.
Accounts, real hubs/routes and uploaded documents are not supplied by these migrations.
The payroll addition supplies no rates or historical earnings; preserve APP_KEY and
private payment proofs. Read [Rider pay implementation](RIDER_PAY_IMPLEMENTATION.md)
before enabling company rates. It does not send provider funds or offset COD.

## Runtime setup and acceptance

- Run the Laravel scheduler for `logistics:advance-pickup-offers`. Offers expire after
  60 seconds and advance through at most three Riders. Nearest selection needs fresh,
  consented locations and available Online Riders; manual assignment remains available.
- Keep `LOGISTICS_HUB_ROUTES_REQUIRED` off until actual company hubs, directed transport
  routes and hub-assigned Rider coverage are configured. See `LOGISTICS_HUB_NETWORK.md`.
  Cross-company transfers and reverse inter-hub RTS transport remain unsupported.
- Logistics owners and Hub Staff must sign in again after 30 minutes without an
  authenticated request in that session. Active requests extend its idle window.
  Separate devices have separate token activity. Existing web tokens also retain
  their two-hour maximum. Other roles retain their existing timeout policies.
- A registration validation error clears passwords and all four document controls.
  Correct the fields and select the documents again before resubmitting.
- Restart any running Vite development server after updating its configuration.
  Keep SQL backups and private recovery artifacts outside public document roots.
- Complete local owner/staff, Buyer, Seller and Rider acceptance with the agreed test
  accounts: pickup, receiving, sorting, delivery proof/OTP, failure/return receipt,
  rates, COD and exports. Verify actual SMTP, scheduler, private-file access and device
  camera/GPS separately; automated fixtures do not certify these external services.

The original workbooks remain unchanged. Current automated coverage and remaining
limits are documented in `testing/results/LOGISTICS_COMMIT_READINESS.md`.
