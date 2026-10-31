# Project guidance

For ALONA / Flutter Rider integration work, first verify the backend and active
`mobile/rider` directories and read that Rider project's `docs/integration/RESUME.md`,
`CHECKPOINT.md`, and `PROGRESS.md`, then relevant existing project memory.
Run Rider's `node scripts/integration_checkpoint.cjs --verify`; inspect only
changed or incomplete files. Do not repeat completed Phases 1-3 unnecessarily.

Save synchronous checkpoints before each phase, meaningful edit batch, risky edit,
long test/build, and after each phase, using the Rider checkpoint helper. Record
running commands before execution and confirmed outcomes afterward. Source state
and test evidence take precedence over documentation. Preserve existing edits.
Never commit, push, reset, clean, rebase, or repeat database migrations automatically.
Tests must use the isolated in-memory database, never development/production data.
Continue remaining integration phases where safe under the latest user authorization.

If checkpoint bundle IDs disagree, Rider's helper --recover verifies/restores the
previous complete documentation and archives interrupted notes. Read recovery notes
and inspect source hash changes; this never resets source, Git or database records.

The active Rider source is now tracked in `mobile/rider`, imported from the user's
selected Veeaguila/rider repository. Read its AGENTS.md and current memory before
mobile work. Its checkpoint helper resolves the backend at `../..`; keep future
mobile applications in separate directories under `mobile/`. Preserve the previous
sibling Rider checkout as recovery/reference; it is not required for teammates to
build the monorepo client. Do not add nested Git repositories or generated app output.

For a teammate pull/setup/database upgrade, read `TEAMMATE_AI_HANDOFF.md` and
`docs/upgrade/LOGISTICS_SCHEMA_MANIFEST.json`. Those documents do not independently
authorize migrations; follow the receiving owner's explicit scope and preserve data.
