# CLAUDE.md

Guidance for AI assistants working on this repository.

## What this is

Le Smash Club tennis court booking system, live at https://booking.lesmashclub.com.
Plain PHP 7/8 with PDO against a MariaDB database. No framework, no Composer, no build step,
no tests. Files were pulled as-is from the production server on 2026-09-18. This is a legacy
codebase; treat every change as a change to production.

See `project_spec.md` for the functional specification and `skills.md` for task recipes.

## Layout

| Path | Purpose |
|---|---|
| `config.php` | PDO connection (`$pdo`) and `DISABLE_NOTIFICATIONS` flag |
| `includes/functions.php` | `lsc_log()`, LINE broadcast, SMS send, `get_member_info()` |
| `includes/booking-functions.php` | Booking window, advance limit, per-type rules, refund eligibility, system settings |
| `includes/member-functions.php` | Timeslot to time conversion, date helpers, expiry check |
| `menu.php` | Header include; also the session guard and defines `$userId`, `$memberType`, `$memberData` |
| `index.php` | Booking page (grid + summary card) |
| `modules/time-table.php` | Initial grid render for today |
| `check_availability.php` | AJAX re-render of the grid for a chosen date, plus the client-side rule JS |
| `book-member.php`, `book-non-member.php`, `book-admin-member.php`, `book-admin-non-member.php`, `book-academy.php` | Booking submit handlers, chosen by `app.js` |
| `cancel_booking.php`, `admin-cancel-booking.php` | Cancellation, refunds, waitlist promotion |
| `waitlist.php`, `booking_functions/dynamic-waitlist.php` | Waitlist confirm/expire and helper functions |
| `admin-*.php` | Admin screens and AJAX handlers |
| `app.js` | All shared front-end logic (grid selection, fee calc, submit routing, member search) |
| `logs/app.log` | JSON-lines audit log written by `lsc_log()` (not committed) |
| `uploads/` | Payment slip images (not committed) |

Dead or superseded files: `book.php`, `booking-process.php`, `register.php`,
`booking_functions/process_waitlist.php`, `booking_functions/confirm-waitlist.php`,
`booking_functions/testsms.php`, `line-push.php`, `line-api.php`, `extend-membership.php`.
Do not build on them.

## Conventions that are easy to get wrong

- **Sentinel IDs.** `member_id` and `non_member_id` are both NOT NULL in practice. `90002` in `non_member_id` means "this is a member booking"; `90002` in `member_id` means "this is a guest booking". `90001` appears in the same role in some handlers and as the placeholder `transaction_id` for academy bookings. Never treat these as real IDs.
- **Timeslots are strings** exactly as in the `$times` array: `6-7am`, `11am-12pm`, `12-1pm`, `9-10pm`. Convert with `get_time_from_timeslot()`.
- **Pricing is hardcoded** in several places (PHP and JS): 160 THB before 6pm, 280 THB from 6pm, 200 THB per guest. Change all copies together or centralise first.
- **Two transaction rows per booking.** A parent row titled `Booking_<member_id>` holds the total; one child row per court-hour references it via `assoc_transaction_id`. `bookings.transaction_id` points at the child row.
- **Member credit** is a running balance in `members.credit`, updated alongside a transaction row. Keep both in step.
- **Cancelled bookings** stay in the table with `booking_status = 'cancelled'`; every availability query must exclude them.
- **Free-text enums.** `member_type`, `daily_member_type`, `booking_status`, `booking_type` are varchar with inconsistent casing in real data. Compare with `strtolower(trim(...))`.
- **Session.** `$_SESSION['user_id']`, `member_type`, `member_number`, `member_fullname`. `member_type === 'admin'` is the only admin check. `menu.php` must be included before using `$userId` / `$memberType`.
- **AJAX handlers return HTML fragments**, not JSON, and the caller injects them into a loader overlay.

## Rules for changes

- Use prepared statements. Never interpolate request data into SQL.
- Do not commit `config.php` credentials, `logs/`, `uploads/`, or SQL dumps.
- Keep `date_default_timezone_set('Asia/Bangkok')` semantics; all times are club-local.
- When touching booking or cancellation flows, wrap multi-statement writes in a PDO transaction.
- Preserve the existing HTML fragment response shape for AJAX endpoints unless you also update the caller in `app.js`.
- Log user-visible state changes through `lsc_log()`.

## Known problems (do not "fix" silently; raise them)

- Passwords stored in plaintext and compared in SQL.
- LINE tokens, SMSMKT keys and the DB password are hardcoded in source.
- Most AJAX handlers only check that a session exists, not ownership or admin role.
- Grid rendering and pricing logic are duplicated across three files.
