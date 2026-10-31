# ALONA teammate upgrade instructions for an AI agent

Read this entire file before changing the teammate's checkout or database. This is
an upgrade of an existing ALONA installation, with a Flutter Rider client included
in the same repository. The teammate's own records and existing schema are the
starting point. Do not replace them with the author's database or a full SQL dump.

## Release and sources of truth

- Repository: `https://github.com/Veeaguila/alona-ecommerce.git`
- Release branch: `feature/logistics-completion`
- Record the exact fetched/pulled commit with `git rev-parse HEAD`; do not assume
  that a branch name always points to the same release.
- Read applicable `AGENTS.md`, then [team update](docs/LOGISTICS_TEAM_UPDATE.md),
  [schema manifest](docs/upgrade/LOGISTICS_SCHEMA_MANIFEST.json),
  [order-flow review](docs/testing/results/ORDER_FLOW_REVIEW.md) and, if changing
  Rider, `mobile/rider/docs/CURRENT_STATE.md` and its integration bundle.
- Read [Rider pay implementation](docs/RIDER_PAY_IMPLEMENTATION.md). The latest
  addition is configured per-Seller-shipment job pay, explicit complete transfer
  manifests and grouped manual company payouts across web and Flutter. Rates start
  disabled and blank; do not seed PocketFinds/test rates or backfill past work.
  The new migration creates six empty payroll tables and adds nullable
  `hub_transfers.rider_pay_trip_id`. Preserve APP_KEY for encrypted instructions,
  private payout proof uploads, existing finance history and COD records. It has
  a preserving-rollback requirement; no automatic down/rollback.
- The manifest identifies the five migrations newly included in this release and
  fingerprints **all** migration files. Older migrations can also be missing on a
  teammate's machine. A fingerprint or the author's old migration status does not
  establish what has been applied to the teammate's database.
- Source and target-schema evidence take precedence over historical notes. Dates,
  local paths, old Git status and previous test counts in imported Rider documents
  describe the author's/upstream environment, not this teammate's installation.

## 1. Establish ownership, authorization and a safe checkout

1. Identify the checkout, current branch/HEAD, remote URL and all staged, unstaged
   and untracked changes. Preserve them. Do not reset, clean, force-pull, rebase,
   overwrite `.env`, or silently stash/commit the teammate's work.
2. Fetch `origin/feature/logistics-completion` and inspect the release before
   switching. A fresh checkout of that branch is also acceptable. Keep the current
   project path and private configuration intact. Use an isolated worktree if the
   current branch/worktree cannot be safely updated. Do not merge over conflicts
   automatically or guess which teammate's code to discard.
3. For an existing clean local release branch, use a fast-forward-only pull. If
   histories diverge, prepare a concrete conflict/merge plan preserving both sides.
   Do not treat a failed fast-forward as permission to reset or force anything.
4. Record the new commit. If the branch/commit is absent remotely, report that it
   must first be pushed by the author; do not substitute a different branch.
5. The copyable prompt in `docs/TEAMMATE_AI_PROMPT.md` grants a bounded upgrade of
   the teammate's **own local development database**, after the checks below.
   Merely reading this file grants no database-write permission. Production,
   shared/staging databases, destructive reconciliation and deployments require
   their owner's explicit authorization. Keep unaffected source/setup work moving.

## 2. Install locked dependencies and preserve configuration

- Check PHP/extensions, Node and Flutter/Dart against the committed manifests and
  lockfiles. Use `composer install --no-scripts`, `npm ci`, then `flutter pub get`
  inside `mobile/rider`. Do not run dependency upgrades or regenerate lockfiles
  merely to make installation work on an incompatible toolchain.
- Inspect Composer/application boot hooks before running package discovery. After
  verifying the intended local configuration, run `php artisan package:discover`.
  Do not use `composer run setup`: it generates keys and runs migrations.
- Preserve the current `.env`, `APP_KEY`, credentials, uploads, storage links and
  signing material. Do not copy the author's credentials, create a new app key on
  an existing installation, publish private backups, or print secret values.
- Inspect cached Laravel configuration as well as environment selection. Confirm
  the actual engine, database name, host and ownership using redacted output. Do
  not connect to an unexpected host. If local config cache must be cleared, verify
  it belongs to this local checkout and is authorized; never clear shared cache,
  sessions or Redis as a general repair.
- Existing defaults keep `LOGISTICS_HUB_ROUTES_REQUIRED` off. Do not enable required
  routing until real company hubs, directed routes and Rider coverage are configured.
  Do not disable a previously configured operational installation blindly either.

## 3. Inventory the existing schema before any migration

Use read-only metadata inspection on the confirmed target. Do not export customer
rows into this repository or include credentials in the upgrade report.

1. Record `php artisan migrate:status` and the existing `migrations` ledger. Compare
   every pending file and its prerequisites with `database/migrations` and the
   manifest. Verify migration contents against the release commit. Manifest hashes
   are SHA-256 of UTF-8 source with CRLF normalized to LF.
2. Inspect the actual tables/columns, types, nullability/defaults, keys, indexes and
   foreign keys. Include the existing `users`, `sorting_centers`, `riders`, `orders`,
   `order_items` and `parcels`, and all objects affected by pending files.
3. Classify each pending migration:

   | Ledger and actual schema | Required handling |
   |---|---|
   | Recorded as applied and objects match | Leave applied; do not rerun. |
   | Not recorded; affected objects absent; prerequisites match | Candidate for the reviewed upgrade plan. |
   | Not recorded but some/all objects already exist | Schema drift; inspect whether manually created or partially applied. No blind migration or fake ledger entry. |
   | Recorded as applied but objects missing/incompatible | Schema drift; prepare a separate repair plan. Do not edit old migration history to hide it. |

4. Check pending older migrations as well. Some older files seed/update data, so
   they must not be described as automatically safe or purely additive. Existing
   fresh MySQL chronology has a known `order_items`/`product_variants` FK ordering
   issue; do not run a fresh rebuild or disable FK checks to bypass it.
5. Produce an ordered plan of **only** the target's genuinely pending, compatible
   changes, with the exact migration paths, dependencies, data effects and risks.
   Do not infer that the teammate shares the author's local migration state.

The five newly included migrations create 21 tables. The hub migration also adds
`riders.logistics_hub_id` and `order_items.hub_route_plan`; payroll adds nullable
`hub_transfers.rider_pay_trip_id`. Full column/key definitions
remain in the migration files; the manifest is an index, not executable SQL or a
replacement for target inspection. The Rider import, inactivity timeout, registration
retry, dev-server protection and Buyer history read fix require no extra new schema.

## 4. Back up, rehearse, then apply the bounded local upgrade

Proceed only within the teammate owner's explicit authorization for that target.

1. Make a private backup of the confirmed existing database **and uploaded/private
   files**. Preserve `APP_KEY` securely with the existing installation. Verify the
   backup can be read/restored; record a redacted backup/restore receipt. Keep backups
   outside public directories and outside Git. A schema-only export cannot restore
   the teammate's data.
2. Rehearse the exact reviewed plan on a separate disposable copy using the target's
   actual engine/version. Do not run PHPUnit on the teammate's DB or that copied
   data: automated tests below use SQLite memory only. Confirm the rehearsal points
   to the copy, not the real target.
3. Read the migration PHP before using `--pretend`; it is not a sandbox for arbitrary
   PHP side effects. Use it only on the disposable rehearsal configuration. Do not
   run seeders, data backfills, `migrate:fresh`, `migrate:refresh`, automatic rollback,
   a schema replacement, or FK-check workarounds.
4. Compare schema and aggregate row counts/record integrity before and after the
   rehearsal. Confirm the original records/relationships and private uploads survive.
5. Once backup, rehearsal and the local-owner authorization are established, apply
   only the approved pending paths to that **same verified local development target**,
   in prerequisite/chronological order. For example, use an explicit
   `php artisan migrate --path=database/migrations/<reviewed-file>.php` per file.
   Do not run a blanket `migrate` that might include unreviewed older work. Do not
   use `--force` to bypass a production guard under local-only authorization.
6. If any step fails, stop further DB changes, record what actually completed and
   inspect for partial MySQL DDL. Do not automatically rerun the batch, mark it
   applied, drop existing tables, roll everything back or restore over current data.
   Prepare a specific preserving repair for the owner to approve where required.
7. Verify final ledger/schema and preservation against the recorded plan/backup.
   Already-applied migrations remain untouched. Never edit historical migration
   files to express a new repair; create a separately reviewed delta when needed.

Do not fabricate hubs, staff/Rider accounts, balances, GPS locations, shipment links,
historical membership or application records to satisfy tests or remove an error.

## 5. Finish application and mobile setup

The additional Buyer/Logistics Flutter source is now in `mobile/alona_mobile`
(upstream4277995). Install its locked dependencies separately; read its README and
`mobile/alona_mobile/docs/LOGISTICS_PARITY.md`. Logistics reuses the existing backend/process with no
new migration. Buyer checkout now shares canonical server quoting/booking; read
`docs/testing/results/MOBILE_ORDER_FLOW_REVIEW.md`. Update backend and mobile together,
use the same reachable API, and retain the configured shared lock-capable cache for
checkout retry receipts. Online payment completion remains in web and native/device
release verification is pending. No Seller Flutter
module is present. `verify-system.cjs --mobile` includes both Flutter applications.

The broader Buyer review also fixes OTP authentication, purchase reviews, voucher
eligibility, cart/reorder, shared Buyer/Seller chat and mobile lifecycle races.
Deploy matching backend/mobile changes together. Mobile Google sign-in currently
returns an honest unavailable notice/503: the imported email/Google-ID form did
not verify a provider identity and must not be re-enabled as authentication.

If the old public OTP/Google endpoints were previously reachable/used, inventory
potentially affected Buyer sessions and prepare a scoped revocation/reauthentication
plan for explicit owner approval. The patch does not revoke old tokens. Google
tokens used `alona-buyer-mobile`; OTP and legitimate password tokens both used
`buyer-mobile-token`, so names alone cannot distinguish that exposure. Do not
mass-delete tokens or approve legacy Google-created `active` accounts automatically.
The bounded schema-upgrade authorization below/inside the teammate prompt does
not authorize session deletion or account-status changes.

- Run `npm run build` for the local installation. Preserve an existing build until
  a successful replacement is ready. Restart a running Vite server after its config
  changes. Use the normal local storage-link setup only after checking any existing
  link/path; do not delete uploaded files or recreate private proof storage as public.
- `mobile/rider` is normal parent-Git source. Do not clone a second Rider repository
  into it, initialize nested Git, move Flutter files into the Laravel backend, or add
  generated builds/cache/keys to Git. Its backend is `../..` from the app.
- Use `API_BASE_URL` build/run arguments ending in `/api`. Android emulator example:
  `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api`. A physical device
  needs the developer's reachable address. Release requires the actual HTTPS API.
  Do not hardcode the author's laptop address or guess production credentials.
- Read `mobile/README.md` and `mobile/rider/README.md`. Production application IDs and
  signing are still team choices; do not ship the upstream example identity/debug key.
  The author's Android debug check was blocked by incomplete NDK 28.2.13676358;
  inspect this teammate's own SDK rather than assuming the same failure or deleting it.
- Configure the scheduler for `logistics:advance-pickup-offers` in the intended local
  environment. Development can use `php artisan schedule:work` when authorized;
  production scheduling is an operator task. Nearest offers need consented fresh
  locations, Online Riders and coverage. Manual assignment remains available.
- Existing owner/Hub Staff sessions expire after 30 minutes without an authenticated
  request; background portal requests count as request activity. Other-role policies
  are unchanged. A validation error clears registration passwords/documents: select
  the documents again when retrying.
- Current new checkout uses configured buyer-funded shipping. Historical seller-
  funded ledgers stay separate. Do not invent Rider earnings allocations, automatic
  COD payment-state transitions, payout or refund execution rules.

## 6. Verify and leave a useful result

From the ALONA root, run:

```text
node scripts/verify-system.cjs
node scripts/verify-system.cjs --mobile
```

The first verifies backend/frontend/checkpoint-helper coverage. The optional second
adds Rider widget tests/analyzer; install Flutter dependencies first. Avoid repeating
the first when the combined command has already passed. These runners force SQLite
`:memory:` and array mail/cache/session, refuse cached Laravel config, and write
private logs under `.local/system-verification`. Never replace the memory DB with
the teammate's configured database to get a test to run. No test accounts/seeders
should be added to their real data.

Latest author evidence includes the full 980-test backend run (57,272 assertions),
48 combined Flutter tests, clean analyzer/rebuilt profile, 124 frontend tests,
7 checkpoint-helper tests and a successful compiled mobile Buyer checkout→Seller web→
Logistics web/mobile→Rider APIs→photo/Buyer mobile OTP→Delivered. Current mobile
counts/build evidence are in `mobile/alona_mobile/docs/CURRENT_STATE.md`; the prior
unchanged Rider suite passed 123 tests. Separate tests cover COD, returns/restock,
store pickup and Buyer activity history. These are reference results, not automatic certification
of a different laptop, real device, shared schema or actual payment service.

Complete local authorized acceptance with agreed accounts/test data: pickup, center
arrival, sorting, delivery assignment, photo/OTP, refused/retry/physical return,
one-time Seller restock, private-file access, COD/remittance, hub staff and idle login.
Do not place real orders, send real email, execute money transfers or create real
accounts without the appropriate permission.

Fresh clones can have relocated paths/EOL differences from the author's checkpoint.
Inspect changed hashes against the exact Git release; do not blindly restore the
author's `.previous` bundle or replay old migrations. Save a fresh local checkpoint
only after confirming the checkout and identifying legitimate differences.

Write a redacted result under ignored `.local/team-upgrade/RESULT.md`, covering:

- exact source commit/branch and preserved work/configuration;
- confirmed target/ownership, schema comparison, backup/rehearsal receipt;
- migrations skipped, applied, partially applied or blocked, with reasons;
- source/setup changes and dependency/build/test results;
- data-preservation checks and remaining native/live/finance blockers.

Report each item as verified PASS, FAIL or PENDING. Do not claim everything succeeded
merely because code pulled or tests passed. Do not automatically commit, push or deploy
the teammate's follow-up changes unless their own prompt separately authorizes it.
