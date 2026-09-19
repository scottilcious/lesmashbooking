# CLAUDE.md

Guidance for AI assistants working on this repository.

## What this is

Le Smash Club tennis court booking system, live at https://booking.lesmashclub.com.
Plain PHP 7/8 with PDO against a MariaDB database. No framework, no Composer, no build step.
A small dependency-free test suite lives in `tests/`. Files were pulled as-is from the production server on 2026-09-18. This is a legacy
codebase; treat every change as a change to production.

See `project_spec.md` for the functional specification and `skills.md` for task recipes.

## Layout

| Path | Purpose |
|---|---|
| `config.php` | Timezone, loads `config.local.php` (secrets, gitignored), builds `$pdo` via `lsc_create_pdo()` |
| `includes/functions.php` | `lsc_log()`, LINE broadcast, SMS send, `get_member_info()` |
| `includes/credit.php` | **The only place a balance changes.** `lsc_credit_move()`, `lsc_credit_adjust()`, `lsc_actor()` |
| `includes/logging.php` | Audit log: monthly rotation, web-access guard, `lsc_log_write()` and `lsc_log_query()` |
| `includes/pricing.php` | **All prices.** Constants + `lsc_price_court/guests/daily_fee/coach()`; `menu.php` publishes them to JS as `window.LSC_PRICING` |
| `includes/auth.php` | `lsc_require_login/member/guest/admin()`: identity from the session; every handler and admin page calls one |
| `includes/password.php` | Encrypted password storage: `lsc_password_verify/encrypt/decrypt/for_storage` |
| `database/migrate-encrypt-passwords.php` | One-off CLI migration of plaintext passwords (idempotent) |
| `includes/booking-functions.php` | Booking window, advance limit, per-type rules, refund eligibility, system settings |
| `includes/member-functions.php` | Timeslot to time conversion, date helpers, expiry check |
| `menu.php` | Header include; also the session guard and defines `$userId`, `$memberType`, `$memberData` |
| `index.php` | Booking page (grid + summary card) |
| `modules/time-table.php` | Initial grid render for today |
| `check_availability.php` | AJAX re-render of the grid for a chosen date, plus the client-side rule JS |
| `book-member.php`, `book-non-member.php`, `book-admin-member.php`, `book-admin-non-member.php`, `book-academy.php` | Booking submit handlers, chosen by `app.js` |
| `cancel_booking.php`, `admin-cancel-booking.php` | Cancellation, refunds, waitlist promotion |
| `includes/booking-service.php` | Per-date lock + transaction for the `book-*.php` handlers: `lsc_booking_begin/commit/abort`, slot and credit re-checks, `LscBookingConflict` |
| `includes/waitlist-service.php` | **The** waitlist implementation: offer, confirm, decline, expire (see header comment) |
| `waitlist.php` | Member waitlist page; calls the service |
| `cron/waitlist-expire.php` | CLI sweep for stale offers; run every 5 min |
| `tests/` | `php tests/run.php` runs the suite against `DB_TEST_DATABASE` |
| `admin-*.php` | Admin screens and AJAX handlers |
| `app.js` | All shared front-end logic (grid selection, fee calc, submit routing, member search) |
| `logs/app-YYYY-MM.log.php` | JSON-lines audit log, one file per month (not committed). Each starts with a `<?php exit; ?>` guard because `logs/` sits in the web root |
| `uploads/` | Payment slip images (not committed) |

Superseded but still present: `extend-membership.php` (unlinked). The old `book.php`, `register.php`,
`line-*.php` and `booking_functions/*` scripts were removed; do not resurrect them.

## Conventions that are easy to get wrong

- **Sentinel IDs.** `member_id` and `non_member_id` are both NOT NULL in practice. `90002` in `non_member_id` means "this is a member booking"; `90002` in `member_id` means "this is a guest booking". `90001` appears in the same role in some handlers and as the placeholder `transaction_id` for academy bookings. Never treat these as real IDs.
- **Timeslots are strings** exactly as in the `$times` array: `6-7am`, `11am-12pm`, `12-1pm`, `9-10pm`. Convert with `get_time_from_timeslot()`.
- **Prices live only in `includes/pricing.php`.** PHP calls `lsc_price_court($timeslot)` etc.; JavaScript reads `window.LSC_PRICING` (published by `menu.php`) through `lsc_court_price()` in `app.js`. Never write a price literal anywhere else.
- **Two transaction rows per booking.** A parent row titled `Booking_<member_id>` holds the total; one child row per court-hour references it via `assoc_transaction_id`. `bookings.transaction_id` points at the child row.
- **Member credit** changes only through `lsc_credit_move()` / `lsc_credit_adjust()` in `includes/credit.php`. Never write `UPDATE members SET credit` anywhere else. Each movement stamps `credit_delta` and the actor on its transaction row, so `SUM(credit_delta) = members.credit` for every member.
- **Cancelled bookings** stay in the table with `booking_status = 'cancelled'`; every availability query must exclude them.
- **Free-text enums.** `member_type`, `daily_member_type`, `booking_status`, `booking_type` are varchar with inconsistent casing in real data. Compare with `strtolower(trim(...))`.
- **Session.** `$_SESSION['user_id']`, `member_type`, `member_number`, `member_fullname`. Use `includes/auth.php` (`$lsc_me = lsc_require_admin()` etc.) at the top of every handler and admin page; `menu.php` still defines `$userId` / `$memberType` for templates.
- **AJAX handlers return HTML fragments**, not JSON, and the caller injects them into a loader overlay.

## Rules for changes

- Run `php tests/run.php` before committing. Add a test for every behaviour change in `includes/`.
- Waitlist behaviour goes through `includes/waitlist-service.php` only. Never deduct credit in a page script. Guest offers are admin-confirmed only.
- Include shared library files with `require_once`/`include_once` (pages mix orders; a plain `include` redeclares functions).
- **Never take the caller's identity from a form or query string.** Handlers use `$lsc_me['id']` from `includes/auth.php`. Admin-only endpoints call `lsc_require_admin()` (AJAX: `die` mode; pages: `'redirect'`).
- **`credit_delta` is the signed balance effect, not the amount.** Informational rows keep 0: the per-court child rows of a booking, and the `Guest transaction` row whose amount is already inside its parent. Counting `transaction_amount` instead double-counts.
- Passwords: never compare `member_password` in SQL. Look the member up, then `lsc_password_verify()`. Write with `lsc_password_for_storage()`; display to admins with `lsc_password_decrypt()`. The key is `PASSWORD_ENCRYPTION_KEY` in `config.local.php` and must be backed up.
- Timezone is set once in `config.php`; do not call `date_default_timezone_set` elsewhere.
- Use prepared statements. Never interpolate request data into SQL.
- Do not commit `config.php` credentials, `logs/`, `uploads/`, or SQL dumps.
- Booking writes go inside `lsc_booking_begin($pdo, $date)` ... `lsc_booking_commit()` from `includes/booking-service.php`, with `lsc_booking_assert_slots_free()` re-run inside. Throw `LscBookingConflict` to abort with a message; never `echo` an exception and carry on.
- Preserve the existing HTML fragment response shape for AJAX endpoints unless you also update the caller in `app.js`.
- Log user-visible state changes through `lsc_log()`. Never write to the log directory directly, and never rename a log file to a non-`.php` extension: the guard line is what stops it being downloaded.

## Known problems (do not "fix" silently; raise them)

- Passwords are encrypted (reversible, by design so reception staff can read them), not hashed. Anyone with both the DB and the server key can read them.
- Grid rendering is still duplicated between `modules/time-table.php`, `check_availability.php` and `app.js` (pricing is not).
- The cancellation handlers still write ledger rows and refunds as separate statements (no transaction yet).
- `uploads/` (payment slips) is served without authentication; anyone with a URL can read a slip.
