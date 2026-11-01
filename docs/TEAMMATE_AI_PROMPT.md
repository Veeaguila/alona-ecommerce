# Prompt to send to a teammate's AI

The author must push `feature/logistics-completion` before teammates can fetch this
new release. The original request here authorized a local commit only. Use the
repository remote that actually contains that branch; do not substitute an old main.

Copy this prompt into the teammate's AI session inside their ALONA repository:

```text
Update this existing ALONA project using the reviewed feature/logistics-completion
branch from https://github.com/Veeaguila/alona-ecommerce.git. Preserve my current code,
database records, .env, APP_KEY and uploaded files. Check the current branch, remote
and all staged/unstaged/untracked work first. Do not reset, clean, rebase, overwrite
my work, replace my database, or commit/push/deploy automatically.

Fetch origin feature/logistics-completion. Before modifying my checkout, read its
TEAMMATE_AI_HANDOFF.md with `git show FETCH_HEAD:TEAMMATE_AI_HANDOFF.md`, plus the
applicable local AGENTS.md. Then safely get the release branch into this checkout
(fast-forward only where possible; preserve divergent work and handle conflicts
explicitly, or use an isolated worktree). If the new branch/commit is not on the
remote yet, report that it must first be pushed by the author.

After updating, read TEAMMATE_AI_HANDOFF.md, docs/LOGISTICS_TEAM_UPDATE.md,
docs/upgrade/LOGISTICS_SCHEMA_MANIFEST.json and docs/testing/results/ORDER_FLOW_REVIEW.md.
Flutter source is already included in mobile/rider and mobile/alona_mobile. Do not
clone another nested repository or create a separate mobile database. Install locked
dependencies in each app separately. Read mobile/alona_mobile/docs/LOGISTICS_PARITY.md:
its Logistics uses the current web process and Buyer checkout shares the canonical
server quote/booking flow. Read docs/testing/results/MOBILE_ORDER_FLOW_REVIEW.md.
Read docs/RIDER_PAY_IMPLEMENTATION.md and its current verification evidence.
The new payroll migration creates six empty tables plus nullable
hub_transfers.rider_pay_trip_id. Compare it with my actual schema and include only
genuinely pending compatible deltas. Keep rates disabled/unconfigured until my
company owner supplies approved amounts; do not copy example/PocketFinds rates,
guess past earnings, offset COD, or treat recorded payout proof as a provider
transfer. Preserve APP_KEY, encrypted receiving instructions and private proof files.
Update backend and mobile together; configure the same reachable API. Native device
release acceptance and actual online-payment completion remain separate checks.
Buyer chat shares Seller web threads. Mobile Google sign-in is currently unavailable
until a real provider integration exists; never restore the imported email/Google-ID
form as authentication. Read the handoff's OTP/Google session-exposure note. If those
old endpoints were reachable/used, report a scoped existing-token revocation and
reauthentication plan for explicit owner approval; do not delete tokens or approve
legacy Google-created accounts automatically under this schema-upgrade authorization.

I authorize upgrading ONLY MY OWN CONFIRMED LOCAL DEVELOPMENT DATABASE after you:
1. Verify the actual target/ownership and compare its schema AND migrations ledger
   against all pending files and prerequisites (not just the five release additions).
2. Make and verify a private database/upload backup and rehearse the exact reviewed
   plan on a separate disposable copy using the same database engine.
3. Confirm existing records survive and apply only genuinely pending compatible
   migrations, with explicit reviewed paths and the required order.

Never run migrate:fresh/refresh, seeders, an automatic rollback, a full schema/data
replacement, fake migration ledger entries, or edit historical applied migrations.
If existing objects conflict with the ledger, a migration partially ran, or the target
is shared/staging/production, stop those DB writes and prepare a specific preserving
plan for the owner's explicit approval; continue safe independent source/setup work.
Do not guess destructive fixes. Do not print credentials or create real accounts,
emails, orders, transfers or money transactions without separate authorization.

Install the locked dependencies, preserve configuration, build the web app and set
up mobile/rider against this local ALONA API per the handoff. Use explicit API_BASE_URL;
do not invent release identity/signing or Rider compensation/payment/refund rules.
Do not enable required hub routing until real hubs/routes/coverage are configured.

Run node scripts/verify-system.cjs --mobile when Flutter is available (the plain
command covers server/frontend/helper checks if mobile tooling is missing). All
automated backend tests must remain SQLite :memory:, never my application database.
Complete the authorized local upgrade and fixes described in the handoff. Keep going
through safe reversible work without stopping at a plan. Record exact blockers and
leave a redacted .local/team-upgrade/RESULT.md listing the source commit, target
schema comparison, backups/rehearsal, skipped/applied migrations, preserved records,
setup/build/tests and remaining PENDING items. Show me the final verified result.
```

The local database authorization in this prompt applies only when the teammate
owner actually sends it. It does not authorize changes to the author's database or
to anyone else's shared/production environment.
