# skills.md

Task recipes for this codebase. Each entry says where to look and what to keep consistent.

## Run locally

1. Create two MySQL databases (dev and test) and import the latest dump into the dev one.
2. Copy `config.example.php` to `config.local.php`; set `DB_*`, `DB_TEST_DATABASE`, and `DISABLE_NOTIFICATIONS` to `true`.
3. Serve the folder with PHP's built-in server (or use the `php-dev` entry in `.claude/launch.json`):

```bash
php -S localhost:8010
```

4. Log in as any member from the dump. Passwords are plaintext in `members.member_password`.

## Run the tests

```bash
php tests/run.php
```

`tests/bootstrap.php` rebuilds `DB_TEST_DATABASE` from `database/schema.sql` and truncates between tests.
Fixtures: `t_member()`, `t_booking()`, `t_waitlist()`; assertions: `assert_eq`, `assert_same`, `assert_true`, `assert_null`.
The waitlist tests pin the clock with `lsc_waitlist_now()` and capture SMS with `lsc_waitlist_set_notifier()`.
Pass a filter to run one file: `php tests/run.php Waitlist`.

## Change a price

Edit the constants at the top of `includes/pricing.php` and nothing else:

```php
const LSC_COURT_FEE_DAY     = 160;  // 06:00-18:00
const LSC_COURT_FEE_EVENING = 280;  // 18:00-22:00
const LSC_GUEST_FEE         = 200;  // per extra player
const LSC_COACH_FEE         = 750;  // assistant coach, guest bookings
const LSC_DAILY_FEES        = [...]; // guest daily membership
```

Everything downstream follows: the booking handlers, the waitlist service, the cancellation refunds,
the labels on the booking page, and the browser's fee calculator (`menu.php` emits the constants as
`window.LSC_PRICING`; `app.js` and the inline script in `check_availability.php` read it via
`lsc_court_price()`). Run `php tests/run.php Pricing` afterwards.

Prices NOT in this module: membership renewal amounts, which are hardcoded `<option>` values in the
unlinked `extend-membership.php`.

## Change booking rules (hours per day, courts per slot)

Server: `lsc_get_booking_rules_for_member_type()` in `includes/booking-functions.php`.
Client: `setupBookingRules()` inside `check_availability.php` and `app.js`.
Submit-time re-check: `$max_hour_compare` in `book-member.php` and the count checks in `book-admin-member.php`.

## Change the advance window or booking hours

`lsc_get_booking_advance_limit_days()` and `lsc_is_booking_window_open_for_member()` in
`includes/booking-functions.php`. The date picker limits live in `modules/select-date.php`.
The midnight toggle is stored in `system_settings` under `allow_midnight_booking` and edited on `admin-settings.php`.

## Close courts on specific dates

Two hardcoded arrays, `$court_exempt_dates` and `$court_closed_dates`, exist in both
`modules/time-table.php` and `check_availability.php`. Edit both.

## Add a new booking type (academy, tournament, ...)

- Add to `$allowed_academy_booking_types` in `book-academy.php`.
- Add a case in `get_admin_booking_type()` in `modules/time-table.php` and `check_availability.php`.
- Add the radio in `modules/admin-booking-menu.php`.

## How a booking is written

Each `book-*.php` handler: pre-checks (window, advance limit, duplicates, per-day limits, credit) ->
optional slip upload -> `lsc_booking_begin($pdo, $date)` (named lock `lsc_booking_<date>` + transaction) ->
`lsc_booking_assert_slots_free()` and, for credit payments, `lsc_booking_assert_credit()` (row lock) ->
parent transaction row, one child row + booking per court-hour, credit deduction -> `lsc_booking_commit()`.
Any exception rolls everything back and renders `lsc_booking_render_conflict()` with HTTP 409.
Two requests for the same date are serialised by the lock, so the second one sees the first one's rows.

## Approve a credit refill (how it works)

`check_member_credit.php` renders the approval panel inside `admin-view-transaction.php`; the admin may edit
`approved_amount`. `admin-approve-credit.php` locks the transaction row, requires `transaction_type = 'Credit refill'`,
flips it to `Credit refill - Approved` with the approved amount, and does `credit = credit + amount`. A second
approval returns 409. Never write an absolute balance.

## Trace a booking's money

```sql
SELECT * FROM bookings WHERE id = ?;
SELECT * FROM transactions WHERE transaction_id = ? OR assoc_transaction_id = ?;
```

The booking's `transaction_id` is the child row. Its `assoc_transaction_id` is the parent.
Cancellation and refund rows are separate rows with `transaction_type` in
`cancelled`, `cancelled-rain`, `cancelled-rain-half`.

## Debug the waitlist

All logic is in `includes/waitlist-service.php`:

- `lsc_waitlist_offer_slot($pdo, $court, $date, $timeslot)` is called by both cancel handlers. It creates a
  placeholder booking (`booking_note = 'waitlist-reserved'`, `payment_remark = 'Not paid yet'`), two
  `waitlist offer` transaction rows, sets `waitlist_status = 'pending'` and sends the SMS.
- `lsc_waitlist_confirm($pdo, $waitlistId, $actor)` charges the waitlisted member (court fee + guests),
  promotes the ledger rows to `booking (member)`, marks the booking paid. Used by `waitlist.php`
  (member) and `admin-view-booking.php` (admin, `waitlist_action=confirm`).
- `lsc_waitlist_decline(...)` and `lsc_waitlist_expire_stale($pdo)` release the court and immediately
  offer it to the next waiting member. The sweep runs lazily on `waitlist.php` and from `cron/waitlist-expire.php`.
- Offers expire 2 h after `wait_list.updated_at` or when the slot is within 2 h of starting.
- Guest (non-member) entries share the queue. Their offers can only be confirmed by an admin
  (`$actor['is_admin']` with `payment` cash|qr); `lsc_waitlist_confirm` returns `admin_only` otherwise.
  `lsc_waitlist_pending_offers()` and `lsc_waitlist_pending_guest_count()` feed `admin-waitlist.php` and the menu badge.

Useful query:

```sql
SELECT * FROM wait_list WHERE date = ? AND timeslot = ? ORDER BY created_at;
```

## Add a page or AJAX handler

Start the file with the right guard from `includes/auth.php`:

```php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin();            // AJAX: 401/403 text
$lsc_me = lsc_require_admin('redirect');  // page: send to login.php
$lsc_me = lsc_require_member();           // member or admin
$lsc_me = lsc_require_guest();            // non-member
$lsc_me = lsc_require_login();            // anyone logged in
```

Use `$lsc_me['id']`, `['type']`, `['is_admin']`, `['is_guest']`. Never read `member_id` / `user_id` from the request
for the acting user. Admin screens may still take a *target* member id from the request.

## HTTP tests

`tests/http.php` starts `php -S 127.0.0.1:8099` with `LSC_DB_DATABASE=lsc_test` (honoured by `config.php`) and
gives `t_http_login($number, $password)`, `t_http_login_guest(...)`, `t_http_get()`, `t_http_post()`.
Errors from that server go to `/tmp/lsc-test-server.log`. See `tests/AuthHttpTest.php` for the pattern:
build fixtures with `t_member()` / `t_booking()`, call the real page, assert on the database.

## Passwords

Stored encrypted in `members.member_password` as `enc1:<base64>` (AES-256-GCM, key in `config.local.php`).

- Verify at login: `lsc_password_verify($input, $row['member_password'])`.
- Save from a form: `lsc_password_for_storage($input, $existingStored)` (blank input keeps the old value).
- Show to an admin or the member: `lsc_password_decrypt($stored)`.
- First deploy: copy the key into the server's `config.local.php`, deploy the code, then run
  `php database/migrate-encrypt-passwords.php --apply` once. Legacy plaintext rows keep working until then.
- Lost key = every member needs a password reset by an admin. Back the key up somewhere other than the server.

## Add or read audit log entries

Write with `lsc_log($action, $description)`. It appends to `logs/app-YYYY-MM.log.php`, creating the
month's file with a `<?php exit; ?>` guard line so a direct HTTP request returns nothing.

Read with `lsc_log_query(['search' => ..., 'start_date' => ..., 'end_date' => ..., 'month' => ...,
'page' => 1, 'limit' => 50])`, which returns `entries`, `total`, `pages` and the available `months`.
With no date range it reads only the newest month. `admin-logs.php` is the only caller.

To inspect on the server, ignore the first line:

```bash
tail -n +2 logs/app-2026-09.log.php | tail -50
```

Set `LOG_DIRECTORY` in `config.local.php` to a path outside the web root if the hosting allows it.

## Send notifications

- LINE broadcast: `lsc_send_line_broadcast($message, LINE_BROADCAST_TOKEN)`. Inside the waitlist service use `lsc_waitlist_notify('line', '', $msg)` so tests can capture it.
- SMS: `lsc_send_sms_notification($phone, $message, SMSMKT_API_KEY, SMSMKT_SECRET_KEY, SMSMKT_SENDER)`; inside the waitlist service use `lsc_waitlist_notify('sms', $phone, $msg)`.
- Both are suppressed and logged when `DISABLE_NOTIFICATIONS` is true.

## Inspect the database offline

The dump can be loaded into SQLite for ad hoc queries with a small parser (regex over the
`INSERT INTO ... VALUES` lines); column order matches the `CREATE TABLE` statements.
Useful distribution checks: `booking_status`, `booking_type`, `daily_member_type`,
`transaction_type`, `waitlist_status`.

## Before deploying

- Diff against the live server; there is no CI and no staging.
- Confirm `DISABLE_NOTIFICATIONS` is `false` and `PASSWORD_ENCRYPTION_KEY` is set in the server's `config.local.php`.
- After the first deploy of password encryption, run `php database/migrate-encrypt-passwords.php --apply` on the server.
- Add a cron entry for `cron/waitlist-expire.php` every 5 minutes.
- **Audit log exposure:** `logs/app.log` is downloadable over the web. After deploying, run
  `php scripts/migrate-logs.php --apply` to split it into guarded monthly files, then delete or move
  `logs/app.log`. Verify with `curl -I https://booking.lesmashclub.com/logs/app.log` (expect 404).
- Never upload `logs/`, `uploads/`, or a `.git` directory to the web root.
