# Logistics registration: 44-case verification — 2026-10-16

Scope: the dedicated web flow at /register/logistics on feature/sorting-center
(upstream baseline a52966b). Original workbook was read and left unchanged.

**Automated mapping: 44 PASS / 0 FAIL / 0 NOT RUN.** This combines 43 backend
workbook cases with browser case 018. It is isolated application evidence, not
44 deployed black-box passes. **Deployed/live workbook run: 44 NOT RUN.**

Prior status is the initial source audit (WORKING means a matching code rule,
not an executed manual pass). UNTESTED identifies environment-dependent upload
enforcement. Runtime/SMTP/concurrency limits are listed below.

Latest submission regression: Logistics60/700 assertions, Buyer37/283, frontend21,
production build, real Inertia/Axios browser11 and component UI8/9 screenshots PASS.
Real transport checks cover successful multipart/CSRF-header/OTP redirect, invalid
fields, HTTP errors, connection/pre-network exceptions and stalled-request recovery.
Earlier UI evidence mocked useForm and did not prove HTTP submission. New backend
tests enforce CSRF/session-key and OTP-to-admin stages in SQLite memory. The exact
original live stall was not reproduced; live acceptance remains NOT RUN. Details
and artifact locations: docs/LOGISTICS_REGISTRATION_HANDOFF.md.

| Case | Scenario | Prior audit | Automated result | Evidence |
|---|---|---|---|---|
| TC-LOG-001 | Valid registration | PARTIAL | PASS | test_tc_log_001_valid_registration |
| TC-LOG-002 | Empty form | PARTIAL | PASS | test_tc_log_002_empty_form |
| TC-LOG-003 | Each required field | PARTIAL | PASS | test_tc_log_003_each_required_field; native file select/reset |
| TC-LOG-004 | Numbers/symbols in names | MISSING | PASS | test_tc_log_004_invalid_names |
| TC-LOG-005 | Unicode and allowed punctuation | WORKING | PASS | test_tc_log_005_unicode_names |
| TC-LOG-006 | Name length boundaries | MISSING | PASS | test_tc_log_006_name_boundaries |
| TC-LOG-007 | Invalid email formats | PARTIAL | PASS | test_tc_log_007_invalid_emails |
| TC-LOG-008 | Duplicate email | WORKING | PASS | test_tc_log_008_duplicate_email |
| TC-LOG-009 | Duplicate email with uppercase | WORKING | PASS | test_tc_log_009_duplicate_email_case |
| TC-LOG-010 | Trim email spaces | WORKING | PASS | test_tc_log_010_email_trim |
| TC-LOG-011 | Invalid mobile formats | WORKING | PASS | test_tc_log_011_invalid_mobile |
| TC-LOG-012 | Mobile length boundaries | WORKING | PASS | test_tc_log_012_mobile_boundaries |
| TC-LOG-013 | Duplicate mobile | MISSING | PASS | test_tc_log_013_duplicate_mobile |
| TC-LOG-014 | Equivalent +63 duplicate | MISSING | PASS | test_tc_log_014_duplicate_mobile_international |
| TC-LOG-015 | Password length boundary | WORKING | PASS | test_tc_log_015_password_length |
| TC-LOG-016 | Missing password character types | MISSING | PASS | test_tc_log_016_password_character_types |
| TC-LOG-017 | Password confirmation mismatch | WORKING | PASS | test_tc_log_017_password_confirmation |
| TC-LOG-018 | Password visibility toggle | PARTIAL | PASS | Browser hide/reveal/hide |
| TC-LOG-019 | Missing consent | WORKING | PASS | test_tc_log_019_consent |
| TC-LOG-020 | Repeated registration submissions | PARTIAL | PASS | test_tc_log_020_duplicate_submission; same/different-key interleaving and browser guard |
| TC-LOG-021 | Duplicate company name | MISSING | PASS | test_tc_log_021_duplicate_company |
| TC-LOG-022 | Invalid SEC format | MISSING | PASS | test_tc_log_022_sec_format |
| TC-LOG-023 | Duplicate SEC number | MISSING | PASS | test_tc_log_023_duplicate_sec |
| TC-LOG-024 | Multiple service regions | PARTIAL | PASS | test_tc_log_024_multiple_regions |
| TC-LOG-025 | TIN format/length | MISSING | PASS | test_tc_log_025_tin_format |
| TC-LOG-026 | Duplicate TIN | MISSING | PASS | test_tc_log_026_duplicate_tin |
| TC-LOG-027 | Dependent address selections | PARTIAL | PASS | test_tc_log_027_address_parent_validation; actual browser select/clear/filter |
| TC-LOG-028 | Postal code format | WORKING | PASS | test_tc_log_028_postal_code |
| TC-LOG-029 | Accepted document types | PARTIAL | PASS | test_tc_log_029_document_types; native file select/reset |
| TC-LOG-030 | Rejected document types | WORKING | PASS | test_tc_log_030_rejected_document_types |
| TC-LOG-031 | Document size boundaries | UNTESTED (runtime) | PASS | test_tc_log_031_upload_boundaries; application rule only, live limits pending |
| TC-LOG-032 | Correct OTP | MISSING | PASS | test_tc_log_032_correct_otp |
| TC-LOG-033 | Incorrect OTP | MISSING | PASS | test_tc_log_033_incorrect_otp |
| TC-LOG-034 | Invalid OTP format | MISSING | PASS | test_tc_log_034_otp_format |
| TC-LOG-035 | Expired OTP | MISSING | PASS | test_tc_log_035_otp_expiry |
| TC-LOG-036 | Resend invalidates old OTP | MISSING | PASS | test_tc_log_036_otp_resend |
| TC-LOG-037 | Unverified login blocked | WORKING | PASS | test_tc_log_037_unverified_login |
| TC-LOG-038 | Pending login restricted | WORKING | PASS | test_tc_log_038_pending_login |
| TC-LOG-039 | Admin pending list/details/documents | PARTIAL | PASS | test_tc_log_039_admin_application |
| TC-LOG-040 | Admin approval | WORKING | PASS | test_tc_log_040_approval; mail call captured, not SMTP |
| TC-LOG-041 | Admin rejection/reason | WORKING | PASS | test_tc_log_041_rejection; mail call captured, not SMTP |
| TC-LOG-042 | Document replacement and resubmission | MISSING | PASS | test_tc_log_042_resubmission |
| TC-LOG-043 | Non-admin approval access denied | WORKING | PASS | test_tc_log_043_admin_only |
| TC-LOG-044 | Other-role dashboards denied | WORKING | PASS | test_tc_log_044_other_roles_denied |

## Executed evidence

- Existing integration evidence reused: 54 Logistics tests / 604 assertions,
  37 Buyer / 283, 22 Partner/Google / 128, 5 Rider notifications / 49. Completed
  backend suites were not repeated. Backend file: tests/Feature/Logistics/LogisticsRegistrationWorkbookTest.php.
- Latest affected backend checks: case 020 = 3 tests / 33 assertions; Google
  signup/login = 3 / 28; ordinary registration = 1 / 9. These overlap older cases;
  do not sum them as separate workbook cases. Enforced SQLite :memory: only.
- Browser: 8 checks, 9 screenshots at 320/390/1440px, zero JS exceptions. Uses
  compiled real Vue components with mocked Inertia/network boundaries, not Laravel.
  Password toggle, dependent addresses, duplicate clicks, native file select/reset,
  Google continuation, OTP/resubmission screens and horizontal overflow checked.
- Latest frontend suite: 19 tests PASS; latest production Vite build PASS.
- Browser artifacts: .local/logistics-ui/1792154877287/evidence.json.
- Confirmed commands and immutable full logs: Rider integration checkpoint.

## Final fixes

The shared field used a value binding on file inputs, clearing selections during
Vue render. File inputs now retain native selection and clear only on model reset.
The unverified status explains that email verification precedes admin review.
A request committing during another request's uploads now resumes only its owned
submission key; different keys remain validation errors and temporary files clean
up. Both outcomes and the single-OTP count were tested. The existing Google signup
option is retained: trusted server identity, expired/wrong-role session rejection,
and a separate Logistics OTP still required before approval/operations.

Region coverage expands into existing canonical city areas to preserve Rider
enrollment/parcel integrations. NCR's required Province choice is displayed as
Metro Manila and stored using PSGC's canonical no-province value. Separate SEC,
BIR, business-permit and representative-ID files remain private.

## Pending approval and live acceptance

1. Prepared migration: database/migrations/2026_10_16_110000_create_logistics_registrations_table.php.
   No existing database was migrated by this task. User now reports all migrations
   applied to alona_development with database sessions; that existing schema was
   not inspected here. Do not rerun migrations. Obtain approval before additional
   schema changes or actual-engine rehearsals that write existing records.
2. Read-only CLI check: upload_max_filesize=2M, post_max_size=8M, memory_limit=128M.
   Web SAPI/proxy settings are UNTESTED. For four files up to 5 MiB each, approve
   web upload_max_filesize at least 5M and post/proxy request limits at least 24M,
   then execute real 4.9/5.0/5.1 MiB and four-document requests. No runtime settings
   or .env were changed here.
3. Real SMTP delivery, OTP timings across deployed workers, private-file serving,
   actual engine locking/competing registrations and full browser-to-server/admin
   flows remain UNTESTED. Deterministic memory interleavings are not InnoDB proof.
4. Existing /api/sorting-center/register and Rider contracts were retained. The
   workbook matrix covers the dedicated web flow; migrating legacy API clients
   to the new required fields/OTP contract would need separate approval.

Keep both retained stashes and all source/index backups until user confirmation.
No commits, pushes, resets, cleans, stash drops or existing-database writes occurred.
