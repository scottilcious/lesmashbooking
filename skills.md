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

Court fee (160 / 280) and guest fee (200) are hardcoded in:

- `check_availability.php` (`calc_court_booking_fee`, `get_price_from_timeslot`)
- `book-member.php`, `book-non-member.php`, `book-admin-member.php`, `book-admin-non-member.php`
- `booking_functions/dynamic-waitlist.php` (`getTransactionPrice`)
- `booking_functions/bookings.js` (low-credit threshold)
- `modules/time-table.php` (display text)
- `modules/select-coach-option.php` (guest fee label)

Daily membership prices for non-members are `data-type-price` attributes in `modules/select-member-type.php`.
Change every copy in one commit.

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

## Passwords

Stored encrypted in `members.member_password` as `enc1:<base64>` (AES-256-GCM, key in `config.local.php`).

- Verify at login: `lsc_password_verify($input, $row['member_password'])`.
- Save from a form: `lsc_password_for_storage($input, $existingStored)` (blank input keeps the old value).
- Show to an admin or the member: `lsc_password_decrypt($stored)`.
- First deploy: copy the key into the server's `config.local.php`, deploy the code, then run
  `php database/migrate-encrypt-passwords.php --apply` once. Legacy plaintext rows keep working until then.
- Lost key = every member needs a password reset by an admin. Back the key up somewhere other than the server.

## Add or read audit log entries

Call `lsc_log($action, $description)` from `includes/functions.php`. Entries are JSON lines in
`logs/app.log`. `admin-logs.php` loads the whole file into memory; consider rotating the file
when it grows past a few tens of MB.

## Send notifications

- LINE broadcast: `lsc_send_line_broadcast($message, LINE_BROADCAST_TOKEN)`. Inside the waitlist service use `lsc_waitlist_notify('line', '', $msg)` so tests can capture it.
- SMS: `send_sms_mkt($phone, $message)` in `booking_functions/dynamic-waitlist.php`.
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
- Never upload `logs/`, `uploads/`, or a `.git` directory to the web root.
