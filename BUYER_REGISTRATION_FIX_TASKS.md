# Buyer Registration Black-Box Audit & Fix Tasks (`TC-BUY-001` to `TC-BUY-030`)

This document tracks the failed and blocked test cases from the **Buyer Registration** Black-Box Test Case suite (`TC-BUY-001` to `TC-BUY-030`) and the exact fixes required across the codebase.

---

## 1. Form Fields & Registration Flow (`TC-BUY-001`, `TC-BUY-021`)

- [x] **TC-BUY-001 — Register with all valid data (FIXED)**
  - **Expected:** Form accepted with the 8 fields in the test sheet; 6-digit OTP sent; email verification page opens; status saved as Unverified.
  - **Actual:**
    - Submitting only the test sheet fields fails unless `sex`, `age`, `address_group`, `region_code`, `city_code`, `barangay_code`, and `street_address` are also filled (required in `app/Http/Controllers/Auth/RegisteredUserController.php`).
    - Even with all fields filled, no 6-digit OTP is sent and no verification page opens. Instead, the account is saved with `status = 'pending'` and redirected to `/?auth=login` with *"Registration submitted. Please wait for administrator approval."*
  - **What to Fix:**
    - Make non-sheet fields (`sex`, Philippine address fields) optional/nullable on initial buyer registration (or align the form steps) and support full `middle_name` / `suffix`.
    - Trigger a 6-digit OTP email on registration and redirect to the OTP verification screen instead of Admin approval.
    - Configure **Gmail SMTP** in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_USERNAME`, and a 16-character Google App Password in `MAIL_PASSWORD`) instead of `MAIL_MAILER=log` so the 6-digit OTP is actually delivered to real Google/Gmail inboxes.

- [x] **TC-BUY-021 — Register without accepting Terms and Privacy Policy (FIXED)**
  - **Expected:** Registration is blocked with a message that Terms and Privacy Policy must be accepted.
  - **Actual:** There is no Terms & Privacy Policy checkbox in `resources/js/Pages/Auth/AuthModal.vue` and no `terms` validation rule in `app/Http/Controllers/Auth/RegisteredUserController.php`. Accounts are created without accepting terms.
  - **What to Fix:** Add a required Terms & Privacy Policy checkbox to `AuthModal.vue` and validate `'terms' => ['accepted']` in `RegisteredUserController.php`.

---

## 2. Name Validation (`TC-BUY-004`, `TC-BUY-006`)

- [x] **TC-BUY-004 — Name with numbers or symbols (FIXED)**
  - **Expected:** `Juan Miguel123` and `Dela Cruz@#` are rejected; only letters (including `ñ/Ñ`), spaces, hyphen (`-`), apostrophe (`'`), and period (`.`) allowed.
  - **Actual:** Accepted and saved. `RegisteredUserController.php` only validates `'required|string|max:255'` with no character pattern check.
  - **What to Fix:** Add `regex:/^[\pL\s\-\'.]+$/u` validation to `first_name` and `last_name` (and middle name) in `RegisteredUserController.php` and `AuthModal.vue`.

- [x] **TC-BUY-006 — Name length at the limits (FIXED)**
  - **Expected:** 1 character (`J`) and 51 characters are rejected; 2 and 50 characters are accepted.
  - **Actual:** 1-character (`J`) and 51-character names are both accepted. The backend rule is `max:255` with no `min:2` or `max:50` constraint.
  - **What to Fix:** Enforce `min:2|max:50` on `first_name` and `last_name` in `RegisteredUserController.php` and `AuthModal.vue`.

---

## 3. Date of Birth / Minimum Age (`TC-BUY-007`)

- [ ] **TC-BUY-007 — Age at the 18-year limit (FAIL)**
  - **Expected:** Born `10/17/2008` (17 years old) is rejected with a message that the buyer must be at least 18.
  - **Actual:** 17-year-old buyer is accepted. `RegisteredUserController.php` validates `'age' => 'required|integer|min:1|max:120'` and `AuthModal.vue` only checks `Number(registerForm.age) < 1`.
  - **What to Fix:** Enforce `'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()]` and `'age' => 'required|integer|min:18|max:120'` in `RegisteredUserController.php` and check `age >= 18` in `AuthModal.vue`.

---

## 4. Email Validation (`TC-BUY-009`, `TC-BUY-011`, `TC-BUY-012`)

- [ ] **TC-BUY-009 — Email in an invalid format (FAIL on backend for missing TLD)**
  - **Expected:** All 5 invalid emails (including `juanmiguel.delacruz@example` with no `.com`) are rejected.
  - **Actual:** While the frontend regex in `AuthModal.vue` blocks it, the backend rule `'email'` in `RegisteredUserController.php` uses default RFC validation without a TLD regex, so `juanmiguel.delacruz@example` is accepted by the backend and creates an account.
  - **What to Fix:** Add `'regex:/^[^\s@]+@[^\s@]+\.[^\s@]+$/'` to the `email` validation rules in `RegisteredUserController.php`.

- [x] **TC-BUY-011 — Registered email typed in capital letters (FIXED)**
  - **Expected:** `JUANMIGUEL.DELACRUZ@EXAMPLE.COM` is recognized as a duplicate and fails with an *"email already in use"* message.
  - **Actual:** Fails with *"The email field must be lowercase."* instead of a duplicate-email message because `RegisteredUserController.php` uses Laravel's `'lowercase'` validation rule without lowercasing the input first. (Any new email typed with capital letters is also rejected).
  - **What to Fix:** Normalize `$request->merge(['email' => Str::lower(trim((string) $request->input('email')))])` before running `$request->validate(...)` in `RegisteredUserController.php`.

- [ ] **TC-BUY-012 — Email with spaces before and after (FAIL in UI)**
  - **Expected:** `"  buyer.test01@example.com  "` has spaces removed and is saved as `buyer.test01@example.com`.
  - **Actual:** Blocked in `AuthModal.vue` Step 1 with *"Please enter a valid email address."* because `emailPattern.test(registerForm.email)` tests the raw string before trimming spaces.
  - **What to Fix:** Trim and lowercase `registerForm.email` (`registerForm.email = registerForm.email.trim().toLowerCase()`) before validating in `AuthModal.vue`.

---

## 5. Mobile Number Validation (`TC-BUY-013`, `TC-BUY-014`, `TC-BUY-015`, `TC-BUY-016`)

- [ ] **TC-BUY-013 — Mobile number in an invalid format (FAIL)**
  - **Expected:** `0917ABC4567`, `0917-123-456`, `12345678901`, `02-8123-4567` are all rejected.
  - **Actual:** All 4 invalid values are accepted and saved. `RegisteredUserController.php` only has `'contact_no' => 'required|string|max:30'`.
  - **What to Fix:** Validate `contact_no` against Philippine mobile format (`09XXXXXXXXX` or `+639XXXXXXXXX`) in `RegisteredUserController.php` and `AuthModal.vue`.

- [ ] **TC-BUY-014 — Mobile number length at the limits (FAIL)**
  - **Expected:** 10 digits (`0917123456`) and 12 digits (`091712345678`) are rejected; 11 digits (`09170000001`) is accepted.
  - **Actual:** 10-digit and 12-digit numbers are both accepted because any string up to 30 characters is allowed.
  - **What to Fix:** Enforce exact 11-digit `09\d{9}` length after normalizing `+639` $\rightarrow$ `09`.

- [ ] **TC-BUY-015 — Mobile number that is already registered (FAIL)**
  - **Expected:** Duplicate mobile number `09171234567` is rejected with a message that the mobile number is already in use.
  - **Actual:** Accepted. There is no `unique:users,contact_no` validation rule on `contact_no`, so multiple accounts can register with the same mobile number.
  - **What to Fix:** Add `'unique:users,contact_no'` validation rule in `RegisteredUserController.php`.

- [ ] **TC-BUY-016 — Registered number entered in `+63` format (FAIL)**
  - **Expected:** `+639171234567` is normalized/recognized as identical to `09171234567` and rejected as a duplicate.
  - **Actual:** Accepted. Neither normalization (`+639...` $\rightarrow$ `09...`) nor uniqueness checking is implemented.
  - **What to Fix:** Normalize `+639XXXXXXXXX` to `09XXXXXXXXX` before validation so `unique:users,contact_no` catches duplicates across both formats.

---

## 6. Password Complexity & UI Toggle (`TC-BUY-018`, `TC-BUY-020`)

- [ ] **TC-BUY-018 — Password missing a required character type (FAIL)**
  - **Expected:** Passwords missing uppercase (`mamimili@2026`), lowercase (`MAMIMILI@2026`), number (`Mamimili@abcd`), or special character (`Mamimili2026`) are rejected.
  - **Actual:** All 4 weak passwords are accepted. `Rules\Password::defaults()` is not configured with `->mixedCase()->numbers()->symbols()` in `app/Providers/AppServiceProvider.php`, so it only checks `min:8`.
  - **What to Fix:** Configure `Password::min(8)->max(64)->mixedCase()->numbers()->symbols()` in `AppServiceProvider.php` and validate complexity in `AuthModal.vue`.

- [ ] **TC-BUY-020 — Password is hidden while typing & show/hide icon (FAIL)**
  - **Expected:** Characters are hidden as dots by default; clicking the show/hide icon reveals the password, then hides it again.
  - **Actual:** Characters are hidden by default (`type="password"`), but no show/hide password eye icon exists in `AuthModal.vue`.
  - **What to Fix:** Add show/hide eye toggle buttons to the Password and Confirm Password fields in `AuthModal.vue`.

---

## 7. Email Verification (OTP) & Login State Transition (`TC-BUY-023` to `TC-BUY-029`)

- [ ] **TC-BUY-023 — Verify with the correct OTP (BLOCKED)**
- [ ] **TC-BUY-024 — Verify with an incorrect OTP (BLOCKED)**
- [ ] **TC-BUY-025 — OTP in the wrong format (BLOCKED)**
- [ ] **TC-BUY-026 — Verify with an expired OTP (BLOCKED)**
- [ ] **TC-BUY-027 — Resend OTP (BLOCKED)**
  - **Reason:** A 6-digit OTP email verification flow (with 5-minute expiry and OTP input screen) is not implemented (`MustVerifyEmail` is commented out in `app/Models/User.php`, and buyer registration uses Admin approval instead of OTP).
  - **What to Fix:**
    - Add `otp_code` and `otp_expires_at` (5-minute expiry) to `users`.
    - Send 6-digit OTP email upon buyer registration and show the OTP verification screen.
    - Implement `POST /verify-otp` (validates 6 numeric digits, checks expiry and code match, sets `email_verified_at = now()` and `status = 'approved'`) and `POST /verify-otp/resend` (invalidates old OTP and emails a new 6-digit OTP).

- [ ] **TC-BUY-028 — Log in before verifying the email (FAIL)**
  - **Expected:** User is denied access and shown the email verification page or asked to verify their email.
  - **Actual:**
    - If `status = 'pending'`, logging in redirects to `/application/status` stating the account awaits Administrator Approval (not email verification).
    - If an Admin sets `status = 'approved'` while `email_verified_at` is still `null`, `app/Services/AccountDestination.php` and `app/Http/Middleware/EnsureBuyer.php` do not check `hasVerifiedEmail()` for buyers, granting full access to `/buyer/dashboard` with an unverified email.
  - **What to Fix:** Check `hasVerifiedEmail()` for buyers in `AccountDestination.php` and `EnsureBuyer.php` and redirect unverified buyers to the OTP verification page.

- [ ] **TC-BUY-029 — Log in with the new verified account (FAIL without admin approval)**
  - **Expected:** After verifying OTP, buyer logs in immediately and lands on the buyer home page (`/buyer/dashboard`).
  - **Actual:** Because buyer accounts start with `status = 'pending'`, the buyer is redirected to `/application/status` instead of `/buyer/dashboard` until an Admin manually approves the buyer in `/admin/applications`.
  - **What to Fix:** Automatically set buyer `status = 'approved'` upon successful 6-digit OTP verification so verified buyers can immediately log in to `/buyer/dashboard`.
