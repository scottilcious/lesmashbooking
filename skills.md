# skills.md

Task recipes for this codebase. Each entry says where to look and what to keep consistent.

## Run locally

1. Create a MariaDB/MySQL database and import the latest dump (`scottde_lscbooking_*.sql`).
2. Edit `config.php` with local credentials and set `DISABLE_NOTIFICATIONS` to `true` so no LINE or SMS is sent.
3. Serve the folder with PHP's built-in server:

```bash
php -S localhost:8000
```

4. Log in as any member from the dump. Passwords are plaintext in `members.member_password`.

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

Flow: cancellation -> `getLatestWaitlistEntryWithPhone()` -> placeholder booking with
`booking_note = 'waitlist-reserved'` and `payment_remark = 'Not paid yet'` -> SMS ->
member confirms on `waitlist.php` within 2 hours -> credit deducted, `waitlist_status = 'confirmed'`.
`expireOldPendingWaitlists()` runs lazily on the next cancellation, not on a cron.

Useful query:

```sql
SELECT * FROM wait_list WHERE date = ? AND timeslot = ? ORDER BY created_at;
```

## Add or read audit log entries

Call `lsc_log($action, $description)` from `includes/functions.php`. Entries are JSON lines in
`logs/app.log`. `admin-logs.php` loads the whole file into memory; consider rotating the file
when it grows past a few tens of MB.

## Send notifications

- LINE broadcast: `lsc_send_line_broadcast($message, $accessToken)`.
- SMS: `send_sms_mkt($phone, $message)` in `booking_functions/dynamic-waitlist.php`.
- Both are suppressed and logged when `DISABLE_NOTIFICATIONS` is true.

## Inspect the database offline

The dump can be loaded into SQLite for ad hoc queries with a small parser (regex over the
`INSERT INTO ... VALUES` lines); column order matches the `CREATE TABLE` statements.
Useful distribution checks: `booking_status`, `booking_type`, `daily_member_type`,
`transaction_type`, `waitlist_status`.

## Before deploying

- Diff against the live server; there is no CI and no staging.
- Confirm `DISABLE_NOTIFICATIONS` is `false` in the deployed `config.php`.
- Never upload `logs/`, `uploads/`, or a `.git` directory to the web root.
