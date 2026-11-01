# Project guidance

This Rider app is tracked inside the ALONA repository at `mobile/rider`.
The backend is `../..`; do not look for or modify an unrelated sibling checkout.
Use this app's checkpoint helper, which resolves the parent backend explicitly.
Imported upstream integration notes describe historical work; verify current source
and read `docs/UPSTREAM.md` and the current integration bundle before continuing.

- Read `docs/PROJECT_CONTEXT.md` and `docs/CURRENT_STATE.md` before project work.
- For architecture, interfaces, or data-flow tasks, read `docs/ARCHITECTURE.md`
  before inspecting source; inspect only relevant files to confirm or update it.
- Keep these documents concise and update only the memory affected by verified
  changes.

For ALONA/Rider integration, verify both projects and read docs/integration/RESUME.md,
CHECKPOINT.md and PROGRESS.md before editing. Run the checkpoint helper --verify;
inspect only changed/incomplete source. Save synchronous checkpoints before each phase,
meaningful edit batch, risky edit, long check and phase completion. Use
node scripts/integration_checkpoint.cjs --update <JSON-file>, and --run rider|alona
<check-command> <args> for checks that automatically record running/confirmed results.
Treat interrupted operations as UNKNOWN. Do not redo completed Phases 1-3 or reset,
clean, commit, push, rebase or repeat migrations automatically. Latest authorization
permits Phases 4-10 where safe; unspecified earnings rules require clarification.

If checkpoint bundle IDs disagree, use the helper --recover to verify/restore the
previous complete documents and archive interrupted notes. Read RECOVERY_NOTES.md
and inspect changed source before editing; recovery never resets source or Git.
