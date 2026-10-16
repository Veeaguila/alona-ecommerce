# origin/main integration — 2026-10-16

feature/sorting-center fast-forwarded from a71cb07 to a52966b (eight upstream
commits). HEAD equals fetched origin/main. No commit or push was performed.

## Preservation

- Verified backup: .local/merge-main/before/ — 384 files, including 348 untracked,
  original index, staged/unstaged binary patches and manifest.
- Retained approved stash: 7afe4c3b9d23003c415ef351a4828173034dff39.
- Retained earlier stash: b2bde4e21ca55ed25fe6f6175f2a0bc4d170c180.
- Original staged patch replayed onto an isolated new-HEAD index: all 28 staged
  paths and mixed staged/unstaged separation retained. Untracked files restored.
- Five recovery files had only Git newline normalization; original bytes restored.
- Environment hash unchanged. No reset, clean, stash drop or existing-DB migration.

## Resolutions

PartnerField and ApplicationStatus retain upstream error styling and Logistics
password controls, OTP and resubmission. User retains Buyer fields/OTP/verification
and local mobile uniqueness. AppServiceProvider retains upstream password policy
and Rider observers. Finance routes and upstream dashboard aliases coexist.

Buyer OTP handlers now restrict challenges to Buyers; isolation tests prove other
roles cannot become verified through those endpoints. Observer timestamp handling
fixes Buyer logout when Laravel temporarily disables casts. Corrected local test
fixtures for SEC format and per-item duplicate-region error paths; validation kept.

## Executed results

| Suite | Tests | Assertions | Result |
|---|---:|---:|---|
| Buyer registration/address/OTP isolation | 37 | 283 | PASS |
| Logistics workbook/backend security | 54 | 604 | PASS |
| Partner/Rider onboarding and Google | 22 | 128 | PASS |
| Rider notifications | 5 | 49 | PASS |
| Existing frontend suite | 19 | — | PASS |
| Production Vite build | — | — | PASS |

Backend checks used SQLite :memory: through the checkpoint wrapper and a test-only
512 MB limit. No persistent configuration changed. Logs: .local/merge-main/test-logs/;
confirmed command results also recorded in Rider's integration checkpoint.
Earlier failures are superseded by the corrected reruns above.

## Remaining

Existing-database rollout remains unapproved, including incoming Buyer schema
additions and prepared Logistics/Finance/notification migrations. Logistics browser case 018 and the automated workbook matrix are now complete;
current evidence and remaining real SMTP/uploads/engine acceptance are recorded
in docs/LOGISTICS_REGISTRATION_TEST_MATRIX.md. Keep both
stashes and backups until the user confirms the integrated application works.
