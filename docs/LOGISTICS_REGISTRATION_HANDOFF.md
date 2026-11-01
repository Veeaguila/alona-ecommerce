# Logistics registration — current handoff

## Validation-error document retry (2026-10-29)

Fixed a reproduced mismatch between reset document model values and still-selected
native file inputs. Validation errors now remount all four file inputs alongside
the password/document reset. Selecting the same documents again emits fresh file
parts; submitting without reselecting them is blocked by native validation.
The actual Vue/Inertia/Axios regression is tests/browser/logistics-registration.cjs.
Current evidence and upgrade instructions: testing/results/LOGISTICS_COMMIT_READINESS.md
and LOGISTICS_TEAM_UPDATE.md.


2026-10-16, upstream baseline a52966b. All 44 workbook cases have passing automated
coverage. This is not a deployed/manual acceptance claim. Full case mapping,
source-audit classifications, evidence and live limits: docs/LOGISTICS_REGISTRATION_TEST_MATRIX.md.
Safe origin/main preservation receipt: docs/ORIGIN_MAIN_INTEGRATION.md.

## TIN field formatting (2026-10-28)

Correcting the TIN field clears its old error. Client and server accept the
existing twelve-digit value with or without spaces/hyphens, including copied
Unicode hyphens, and normalize it to `000-000-000-000`. The form validates before
leaving Company or submitting; incomplete/extra digits and letters remain invalid.
Existing duplicate checks run on the normalized value. This is format validation,
not verification of tax registration. No stored records or session handling changed.

## Mobile-number validation (2026-10-28)

The dedicated form checks the existing supported mobile formats before leaving
Account and before submission: `09` plus nine digits, or `+639` plus nine digits.
Native input limits are 11 and 13 characters respectively, with a format hint,
accessible inline errors and blur validation. Oversized/non-numeric pastes are
rejected without truncating them into a different number; full international
pastes remain intact. Backend validation retains both formats, adds an explicit
13-character maximum and gives matching format feedback. No database or stored
contact records changed; local numbers still normalize to international format.

## Latest: submission failure recovery

User confirmed the full /register/logistics page stalls. Exact live trigger was
not reproduced: real Vue/Inertia/Axios successfully POSTs multipart data and opens
OTP in isolation. APP_URL matches the reported origin. Earlier UI tests mocked
useForm and did not verify transport. Do not claim a proven URL/migration cause.

Fixed confirmed defects: only field validation had inline feedback; network/start
exceptions and HTML errors lacked it, and pending requests had no timeout. Added
scoped failure handlers, focused validation summaries, nested region errors,
same-origin POST and cancellation after 60s without upload progress. Layout/colors,
CSRF, required fields and OTP are preserved. Separately, the homepage Logistics
popup used the old payload without session key/TIN/SEC/BIR/regions; it now opens
the dedicated GET form. Rider steps are unchanged.

Files changed this session:
- resources/js/Pages/Auth/LogisticsRegistration.vue
- resources/js/Components/PartnerAuthModal.vue
- tests/Feature/Logistics/LogisticsRegistrationWorkbookTest.php
- tests/frontend/onboarding.test.mjs
- scripts/check-logistics-submission.cjs (new real-transport browser fixture)
- scripts/check-logistics-ui.cjs (mock router listener compatibility)
- docs/LOGISTICS_REGISTRATION_HANDOFF.md
- docs/LOGISTICS_REGISTRATION_TEST_MATRIX.md

Executed: Logistics60/700 assertions, Buyer37/283, frontend21, final production
build, actual-transport browser11, component UI8/9 screenshots/zero exceptions PASS.
New PHP tests enforce real CSRF and rendered session key, browser string-checkbox
payload, OTP screen and Admin Review visibility only after verification. Browser
checks include valid four-file POST, native/server validation, HTTP413/419/429/500,
connection interruption, pre-network Axios failure, synchronous start exception,
duplicate-click guard and accelerated-time stalled-request recovery.

Evidence: .local/merge-main/test-logs/ and
.local/logistics-submit/1792162968549/evidence.json. Source backups:
.local/logistics-submit/before/. Production assets rebuilt under public/build.
Original staged index matches integration receipt; both retained stashes intact.
No MySQL writes/migrations/.env edits/commits/pushes. User reports all migrations
applied to alona_development with database sessions; not independently audited.
Existing .env predates this task and differs from the old integration backup;
do not restore that older configuration.

Remaining: hard-refresh local page, verify real SMTP OTP and Admin Review. Exact
original live stall remains unconfirmed; use the now-visible error and first
browser Console error if it persists. Web upload limits/live engine races untested.

## Previous workbook implementation

- Prior 118 backend tests and integrated source evidence were reused.
- Finished browser: 8 checks / 9 screenshots / zero JS exceptions, including 018,
  native file selection/reset, 020 click guard and 027 dependent selections.
- Fixed native file selection loss and clarified pre-verification status.
- Fixed late duplicate-submit validation race; 3 focused tests / 33 assertions pass.
- Preserved Google signup with trusted server identity and required Logistics OTP;
  3 focused OAuth tests / 28 assertions and ordinary signup 1 / 9 pass.
- Latest 19 frontend tests and production asset build pass.
- Existing staged/unstaged/untracked work, both stashes, .env and backups retained.

## Task files modified

- app/Http/Controllers/AdminApplicationController.php
- app/Http/Controllers/Auth/EmailVerificationNotificationController.php
- app/Http/Controllers/Auth/GoogleController.php
- app/Http/Controllers/Auth/VerifyEmailController.php
- app/Http/Controllers/PartnerProfileController.php
- app/Models/User.php
- bootstrap/app.php
- resources/js/Components/PartnerField.vue
- resources/js/Pages/Auth/ApplicationStatus.vue
- routes/logistics.php
- tests/Feature/PartnerOnboardingTest.php

## Task files added

- app/Http/Controllers/LogisticsRegistrationController.php
- app/Http/Requests/LogisticsRegistrationRequest.php
- app/Models/LogisticsRegistration.php
- app/Notifications/LogisticsEmailOtp.php
- app/Services/LogisticsOnboarding.php
- database/migrations/2026_10_16_110000_create_logistics_registrations_table.php
- resources/js/Components/LogisticsAddressSelector.vue
- resources/js/Pages/Auth/LogisticsRegistration.vue
- scripts/check-logistics-assets.cjs
- scripts/check-logistics-ui.cjs
- tests/Feature/Logistics/LogisticsRegistrationWorkbookTest.php
- docs/LOGISTICS_REGISTRATION_HANDOFF.md

Local recovery artifacts are under .recovery/logistics/ (baseline copies,
checkpoint input, partial UI screenshots and pause hashes). The Rider checkpoint
helper also updated docs/integration checkpoint bundles and .recovery/logs.
Generated production assets are under public/build; no dependencies changed.


## Resume only the remaining approved work

1. Verify AGENTS.md, Rider checkpoint and changed source hashes. Read the matrix;
   do not repeat completed automated checks without a source change or new concern.
2. User reports alona_development with all migrations applied. Do not rerun them;
   isolated tests do not prove the existing database schema/state.
3. Approve appropriate web PHP/proxy upload limits (CLI currently 2M/file, 8M/post;
   web settings unknown), then perform real file-size and four-file request tests.
4. Real SMTP, browser-to-Laravel/admin acceptance and actual-engine concurrency
   remain NOT RUN. No API contract migration or new dependency is authorized.
5. Never commit/push/drop stashes or change .env automatically. All backups remain.
