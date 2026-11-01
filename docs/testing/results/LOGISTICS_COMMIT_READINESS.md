# Logistics commit-readiness review

Date: 29 October 2026 (Asia/Manila). Reviewed the current uncommitted ALONA release,
the original 44 Logistics module cases and 44 Logistics account cases. The workbooks
were read directly as ZIP/XML without editing their expected results or status cells.

Confirmed defects fixed: registration retry could silently omit all four documents;
Vite could serve ignored local recovery files. The owner explicitly resumed the
30-minute inactivity work, which is now enforced for Logistics owners and Hub Staff.
The reviewed source checks pass. Actual teammate-database compatibility and live/native
acceptance remain rollout gates; this report does not certify zero bugs or production.

## Verified changes

1. LogisticsRegistration.vue remounts its four native upload inputs when validation
   resets their model values. A retry is blocked until documents are selected again;
   selecting the same files sends all four multipart file parts and reaches OTP.
2. Vite retains its default sensitive-file protections and denies .local, .recovery,
   .checkpoints, agent configuration and database backup file types. The regression
   exercises both direct and /@fs/ requests against synthetic fixtures; no real backup
   was requested. Existing running development servers must be restarted.
3. LogisticsSessionLifetime is called by Sanctum before last_used_at is updated.
   An unused token expires from creation; an active token expires from its last
   authenticated request. At exactly 1,800 seconds it is rejected without revival.
   Each token is independent; expired/invalid base Sanctum credentials stay rejected.
4. Web middleware invalidates idle Logistics browser sessions and revokes only their
   bound token. Activity from that browser's API token keeps navigation active. Active
   web sessions rotate an idle embedded token without regaining an expired web session.
   Existing two-hour web-token maximums remain. Other-role lifetimes are preserved.
5. The portal clears the current Logistics credentials and returns to login on an owned
   same-origin API 401. A late response for an old login cannot clear a new login;
   forbidden, public and foreign requests cannot trigger this recovery.

No application migration, database connection switch, real account creation, package
installation, Git staging, commit or push was performed. No historical financial or
inventory rules were changed. Verified local built assets were installed after an
independent backup, retaining old chunks and replacing the manifest last.

## Fresh verification

| Check | Result |
|---|---|
| Logistics plus related account/authentication/Seller regression | 489 tests / 7089 assertions; zero failures/errors/skips; SQLite :memory: |
| Approved idle-session boundary and isolation subset (included above) | 9 tests / 56 assertions |
| Frontend including actual Vite middleware and session recovery | 123 tests; zero failures |
| Actual Vue/Inertia/Axios registration browser | 13 checks; zero defects / JavaScript exceptions |
| Production Vite build | PASS; isolated output under .local/commit-readiness-review/build |
| Logistics mobile API/session/forbidden-access/hub workspace | 44 tests PASS; synthetic HTTP |
| Portable teammate backend verifier | PASS; idle-session subset 9 tests / 56 assertions |
| PHP/JavaScript syntax and Git whitespace | PASS |

Browser scenarios include hidden-password toggles, invalid native input, valid
four-file multipart POST/CSRF/OTP redirect, validation retry, 413/419/429/500,
connection interruption, duplicate submissions, pre-network/start exceptions and a
real 60-second stalled-request cancellation. Only synthetic loopback HTTP was used.
Detailed local evidence: .local/commit-readiness-review and
.local/logistics-registration-browser/evidence.json. The portable safe backend
verifier is scripts/verify-logistics.cjs. Reproduction and upgrade instructions:
[Team update](../../LOGISTICS_TEAM_UPDATE.md).

## Module case mapping

Every class below executed in the passing backend suite. These are automated checks
of the approved current contracts, not newly executed manual sign-offs for every point
in the original Excel Expected Result. Frontend checks additionally cover stale reads,
edited tracking references, duplicate clicks, denial feedback and failed mutations.

| Original case | Scenario | Passing backend evidence |
|---|---|---|
| LOG-DASH-001 | Counts match the records | ParcelSummaryTest |
| LOG-DASH-002 | Counts update after a status change | ParcelSummaryTest, CompleteWorkflowTest |
| LOG-DASH-003 | Date filter | DashboardDateFilterTest |
| LOG-DASH-004 | Only own company's data | ParcelSummaryTest, BatchOneAccessTest |
| LOG-RIDER-001 | Approve a rider | RiderDecisionAuditTest |
| LOG-RIDER-002 | Reject a rider with a reason | RiderRejectionTest |
| LOG-RIDER-003 | Suspend a rider with active jobs | SuspensionReleaseTest, RiderPresenceTest |
| LOG-RIDER-004 | Suspend without a reason | RiderSuspensionTest |
| LOG-RIDER-005 | Reactivate a rider | RiderSuspensionTest, RiderPresenceTest |
| LOG-RIDER-006 | Rider status and workload | RiderWorkloadTest, RiderPresenceTest |
| LOG-RIDER-007 | Cannot manage another company's rider | BatchOneAccessTest |
| LOG-DISP-001 | Auto-assign to the nearest eligible rider | NearestPickupOfferTest |
| LOG-DISP-002 | Manual assign | PickupDispatchTest, PickupTrackingTest |
| LOG-DISP-003 | Offline or suspended rider not assignable | PickupDispatchTest |
| LOG-DISP-004 | Rider at the job limit | PickupDispatchTest |
| LOG-DISP-005 | Reassign before pickup | PickupDispatchTest, PickupTrackingTest |
| LOG-DISP-006 | No reassign after pickup | PickupDispatchTest |
| LOG-DISP-007 | Bulk assignment over the limit | PickupDispatchTest |
| LOG-DISP-008 | Assign a parcel that is not ready | BatchOneAccessTest |
| LOG-HUB-001 | Inbound receiving by scan | IncomingParcelTest, PickupTrackingTest |
| LOG-HUB-002 | Sort by zone | ParcelSortingTest |
| LOG-HUB-003 | Outbound dispatch | OutboundParcelTest, BuyerOtpTest, CompleteWorkflowTest |
| LOG-HUB-004 | Duplicate scan | IncomingParcelTest |
| LOG-HUB-005 | Parcel not in the system | IncomingParcelTest |
| LOG-HUB-006 | Out-of-order scan | BatchOneAccessTest, OutboundParcelTest |
| LOG-HUB-007 | Another company's parcel | IncomingParcelTest |
| LOG-TRK-001 | Search by tracking number, order ID or buyer | ParcelMonitoringSearchTest |
| LOG-TRK-002 | Manual override needs a reason | ParcelOperationsTest |
| LOG-TRK-003 | Override leaves an audit log | ParcelOperationsTest |
| LOG-TRK-004 | Mark a parcel Lost | ParcelOperationsTest |
| LOG-TRK-005 | Mark a parcel Damaged with a photo | ParcelOperationsTest |
| LOG-RTS-001 | RTS queue after 3 failed attempts | ReturnToSellerTest |
| LOG-RTS-002 | Deliver back to the seller | ReturnToSellerTest |
| LOG-RTS-003 | Stock restored only when the seller confirms | ReturnToSellerTest |
| LOG-RTS-004 | Prepaid RTS refund and no earnings | ReturnToSellerTest |
| LOG-RATE-001 | Rate change shows at checkout | WorkbookRatesTest |
| LOG-RATE-002 | Invalid rate values | WorkbookRatesTest |
| LOG-RATE-003 | Unserviceable area blocked | WorkbookRatesTest, CheckoutCoverageTest |
| LOG-RATE-004 | Add a zone | WorkbookRatesTest |
| LOG-COD-001 | Remittance matches COD collected | CodLedgerTest |
| LOG-COD-002 | Short remittance flagged | CodLedgerTest |
| LOG-COD-003 | Confirm remittance | CodLedgerTest |
| LOG-COD-004 | Export reports | CodLedgerTest, ReportExportTest |
| LOG-COD-005 | Export with no data | ReportExportTest |

HubStaffWorkflowTest additionally exercises owned staff provisioning/login, restricted
hub lookup, origin receipt -> manifest dispatch -> destination receipt, wrong/foreign
hub denials, duplicate actions, audit rollback and missing-schema setup responses.
BuyerOtpTest verifies the five-failure/15-minute lockout and its renewal boundaries.

## Account case mapping

All backend methods below are in LogisticsRegistrationWorkbookTest and ran in the
passing suite. TC-LOG-018 is covered by the actual browser. TC-LOG-003/029 also have
the new actual multipart validation-error retry regression.

| Original case | Scenario | Fresh evidence |
|---|---|---|
| TC-LOG-001 | Register with all valid data | test_tc_log_001_valid_registration |
| TC-LOG-002 | Submit a completely empty form | test_tc_log_002_empty_form |
| TC-LOG-003 | Leave one required field blank at a time | test_tc_log_003_each_required_field |
| TC-LOG-004 | Name with numbers or symbols | test_tc_log_004_invalid_names |
| TC-LOG-005 | Name with ñ and allowed punctuation | test_tc_log_005_unicode_names |
| TC-LOG-006 | Name length at the limits | test_tc_log_006_name_boundaries |
| TC-LOG-007 | Email in an invalid format | test_tc_log_007_invalid_emails |
| TC-LOG-008 | Email that is already registered | test_tc_log_008_duplicate_email |
| TC-LOG-009 | Registered email typed in capital letters | test_tc_log_009_duplicate_email_case |
| TC-LOG-010 | Email with spaces before and after | test_tc_log_010_email_trim |
| TC-LOG-011 | Mobile number in an invalid format | test_tc_log_011_invalid_mobile |
| TC-LOG-012 | Mobile number length at the limits | test_tc_log_012_mobile_boundaries |
| TC-LOG-013 | Mobile number that is already registered | test_tc_log_013_duplicate_mobile |
| TC-LOG-014 | Registered number entered in +63 format | test_tc_log_014_duplicate_mobile_international |
| TC-LOG-015 | Password length at the limit | test_tc_log_015_password_length |
| TC-LOG-016 | Password missing a required character type | test_tc_log_016_password_character_types |
| TC-LOG-017 | Confirm Password does not match | test_tc_log_017_password_confirmation |
| TC-LOG-018 | Password is hidden while typing | Actual browser: password/confirmation visibility |
| TC-LOG-019 | Register without accepting the terms | test_tc_log_019_consent |
| TC-LOG-020 | Click Register several times quickly | test_tc_log_020_duplicate_submission |
| TC-LOG-021 | Company name already registered | test_tc_log_021_duplicate_company |
| TC-LOG-022 | SEC registration number in an invalid format | test_tc_log_022_sec_format |
| TC-LOG-023 | SEC registration number already registered | test_tc_log_023_duplicate_sec |
| TC-LOG-024 | Select more than one service area | test_tc_log_024_multiple_regions |
| TC-LOG-025 | TIN in an invalid format or length | test_tc_log_025_tin_format |
| TC-LOG-026 | TIN that is already registered | test_tc_log_026_duplicate_tin |
| TC-LOG-027 | Address dropdowns depend on each other | test_tc_log_027_address_parent_validation |
| TC-LOG-028 | Postal code length and format | test_tc_log_028_postal_code |
| TC-LOG-029 | Upload documents in each accepted file type | test_tc_log_029_document_types |
| TC-LOG-030 | Upload a file type that is not accepted | test_tc_log_030_rejected_document_types |
| TC-LOG-031 | File size at the limit | test_tc_log_031_upload_boundaries |
| TC-LOG-032 | Verify with the correct OTP | test_tc_log_032_correct_otp |
| TC-LOG-033 | Verify with an incorrect OTP | test_tc_log_033_incorrect_otp |
| TC-LOG-034 | OTP in the wrong format | test_tc_log_034_otp_format |
| TC-LOG-035 | Verify with an expired OTP | test_tc_log_035_otp_expiry |
| TC-LOG-036 | Resend OTP | test_tc_log_036_otp_resend |
| TC-LOG-037 | Log in before verifying the email | test_tc_log_037_unverified_login |
| TC-LOG-038 | Pending logistics logs in | test_tc_log_038_pending_login |
| TC-LOG-039 | New application appears in the admin's pending list | test_tc_log_039_admin_application |
| TC-LOG-040 | Admin approves the application | test_tc_log_040_approval |
| TC-LOG-041 | Admin rejects the application with a reason | test_tc_log_041_rejection |
| TC-LOG-042 | Rejected logistics corrects and resubmits | test_tc_log_042_resubmission |
| TC-LOG-043 | Non-admin opens the approval page | test_tc_log_043_admin_only |
| TC-LOG-044 | Open another role's pages | test_tc_log_044_other_roles_denied |

## Contract and rollout limits

- The workbook labels many marketplace assumptions as proposals. This review did not
  invent Rider pickup pay, a new buyer-shipping earnings split, automatic refunds,
  cash adjustments, cross-company transport or reverse inter-hub RTS. Existing manual
  override is a privileged audited exception and does not create proof or earnings.
- Failed attempts, RTS eligibility, physical return/handover and Seller receipt remain
  separate milestones. Seller receipt restocks once. Prepaid returns open an authorized
  refund review; they do not claim a payment processor has issued a refund.
- The last recorded local inspection had the staff-membership migration unapplied and
  no operational hubs/routes configured. This review did not rerun application migrations
  or query/mutate the application database. Required hub routing remains gated by setup.
- Teammates' actual database schema has not been supplied. Compare it and migration
  history before upgrading an approved copy; never import a replacement full dump or
  mark migrations applied by guessing. A pre-existing fresh MySQL FK ordering issue
  still needs separate bootstrap work; no applied migration was rewritten here.
- Real SMTP, scheduler timing, private uploads on the actual host, native camera/GPS,
  physical cargo, live multi-role acceptance and MySQL concurrency remain unverified.
- Existing source edits were preserved. The original Git index, HEAD and .env are
  unchanged. Include intended unstaged edits and new source/migrations/tests in the
  eventual reviewed commit; the existing staged snapshot alone is incomplete.

Workbook fingerprints: modules 0615000a6d58f8b3e5b6690fffb08e498f6095ecd51158715486ee286570c84f; accounts c092c8f82b3f80a0de7ca15a46b4f50523501aeefa908fdeaff2cfb49f3a1aab.
