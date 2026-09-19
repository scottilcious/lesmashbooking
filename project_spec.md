# Le Smash Club Booking System – Project Specification

Reverse-engineered from the production code and database on 2026-09-18.
Describes what the system does today, not what it should do.

## 1. Overview

A web application for booking tennis courts at Le Smash Club (Bangkok). Seven courts, sixteen
one-hour slots per day from 06:00 to 22:00. Three user roles share one codebase:

| Role | How they log in | What they can do |
|---|---|---|
| Member | Member number + password | Book with prepaid credit or bank transfer, join waitlists, cancel, refill credit, edit profile |
| Non-member (guest) | Name + email + phone (auto-registered) | Book with bank transfer, pay a daily membership fee, add a coach, join waitlists, cancel |
| Admin | Member account with `member_type = 'admin'` | Everything above on behalf of others, plus manage members, approve payments, cancel with refunds, block courts for academy events, view ledger and audit log, toggle settings |

Stack: PHP with PDO, MariaDB 10.6, Bootstrap 5.3, jQuery 3.6, flatpickr. Hosted on shared
hosting (cPanel-style paths). Timezone Asia/Bangkok.

## 2. Data model

### members
Club members and admins. Notable columns: `member_number` (login), `member_password`
(encrypted `enc1:` blob, readable by admins via the server key), `member_type` (individual, couple, family, junior, 1 adult 1 child, corporate, admin;
free text with casing variants), `member_status` (active, expired), `member_since`,
`member_length` (1 year, 6 months, 1 month), `member_expiration`, `credit` decimal running balance.

### non_members
Guests. `guest_name`, `member_phone`, `member_email`. A guest is matched on all three fields at login.

### bookings
One row per court-hour. `court` 1–7, `date`, `timeslot` string, `member_id`, `non_member_id`,
`booking_status` (pending, approved, cancelled, not_paid), `booking_type` (member booking,
non-member, junior_academy, adult_clinic, tennis_camp, tournament), `daily_member_type`,
`payment` (credit, cash, qr, None, admin), `transaction_id`, `payment_remark` ("Not paid yet"),
`slip`, `coach`, `coach_extra_player`, `coach_name`, `booking_note`, `non_member_info`.

Sentinels: `90002` in `non_member_id` marks a member booking; `90002` in `member_id` marks a
guest booking; `90001` is used similarly in admin and academy flows and as a placeholder
`transaction_id` for academy bookings.

### transactions
Append-only ledger. `credit_delta` is the signed effect on `members.credit` (0 for rows recorded for information only), and `actor_type` / `actor_id` / `actor_name` record who performed it.
 `transaction_title`, `assoc_transaction_id` (child -> parent),
`member_id`, `non_member_id`, `transaction_amount` (integer THB), `transaction_type`,
`payment_type` (credit, cash, qr), `slip_url`, `transaction_note`.

Transaction types in use: `booking (member)`, `booking (non member)`, `cancelled`,
`cancelled-rain`, `cancelled-rain-half`, `Credit refill`, `Credit refill - Approved`,
`Admin credit add`.

### wait_list
`member_id`, `non_member_id`, `date`, `timeslot`, `member_type` (member booking / non-member),
`waitlist_note` (CSV of daily type, coach, guests), `waitlist_status` (NULL = waiting,
pending = offered, confirmed, expired), `free_court`, `waitlist_booking_id`.

### system_settings
Key/value. Only key today: `allow_midnight_booking`.

## 3. Business rules

### Pricing
| Item | Price (THB) |
|---|---|
| Court hour, 06:00–18:00 | 160 |
| Court hour, 18:00–22:00 | 280 |
| Guest player (member booking) | 200 per person per booking |
| Extra player (guest booking) | 200 per person per hour |
| Assistant coach (guest booking) | 750 per hour |
| Daily membership: Individual / Couple / Family / Junior / 1 adult 1 child | 500 / 800 / 950 / 350 / 650 |
| Academy, clinic, camp, tournament blocks | 0 |

### Booking limits
| Member type | Max hours per day | Max courts in one slot |
|---|---|---|
| individual, junior, unknown | 2 | 1 |
| couple, 1 adult 1 child | 3 | 2 |
| family | 4 | 2 |
| admin | 240 | 12 |
| non-member | 2 | 1 |

### Time windows
- Bookings allowed for today through 7 days ahead (admins unrestricted).
- Site closed to members and guests between 00:00 and 06:00 unless the admin toggle is on.
- Non-members cannot book peak slots more than 48 hours ahead. Peak: weekdays 06–09 and 16–22; weekends also 09–12.
- Hardcoded closures: courts 1–4 closed 21–28 Dec 2025; all courts closed 29 Dec 2025 – 1 Jan 2026.

### Payment and approval
- Credit payment: instant approval, balance reduced immediately.
- Bank transfer / QR: booking is "pending" with an uploaded slip (resized to max 1000px) until an admin approves it on the booking detail page.
- Admin cash bookings are approved immediately and may be flagged "Not paid yet".
- Credit adjustments: an admin changes a balance only through **Adjust credit** on the member page, which requires a written reason and records who did it. The credit field itself is read-only.
- Credit refill: member uploads a slip, a `Credit refill` transaction is created and LINE notified; admin approves from the transaction page, optionally adjusting the amount to match the slip. Approval **adds** the amount to the current balance atomically and can only happen once per refill.
- Admin "Save changes" on a booking edits court, date, time, status, coach and note only. It refuses to set "cancelled" (the Cancel buttons handle refunds and the waitlist) and refuses to move a booking onto an occupied court.

### Cancellation and refunds
- Member self-cancel: full refund to credit only if 48 hours or more before the slot start and the booking was paid by credit or QR. Guest fees refunded alongside. Otherwise no refund. The status change, ledger row and refund commit together; cancelling twice refunds once.
- Guest self-cancel: never auto-refunded; told to contact admin.
- Admin cancel: optional credit refund; "rain/pollution" option refunds full or half amount regardless of timing, and does not trigger waitlist promotion.

### Waitlist
1. A member or guest ticks "Waitlist" on a slot; a `wait_list` row is created with NULL status. Payment option becomes "Pay at confirmation". For guests the note stores name, email, phone, daily type, coach and extra players.
2. When a booking in that date/time is cancelled (member self-cancel, or admin plain cancel; rain cancellations do not count) and the slot starts more than 2 hours from now, the **oldest waiting entry, member or guest,** is offered the freed court: a placeholder approved booking is created (`payment_remark = 'Not paid yet'`, `booking_note = 'waitlist-reserved'`), two ledger rows typed `waitlist offer` are written, `waitlist_status` becomes `pending` and `updated_at` records the offer time.
3. **Member offer:** the member gets an SMS and has 2 hours to confirm on the waitlist page, or an admin confirms from the booking page or the admin waitlist page. Confirmation charges the member the court fee plus 200 THB per guest, marks the booking paid by credit, promotes the ledger rows to `booking (member)` and sets `confirmed`. Insufficient credit leaves the offer open.
4. **Guest offer:** the guest gets an SMS saying staff will contact them; the club gets a LINE message. **Only an admin can confirm**, choosing cash or bank transfer, from the admin waitlist page (menu shows a red badge with the count) or the booking page. Price = court fee + daily membership fee + 750 coach + 200 per extra player. Ledger rows become `booking (non member)`. No credit is involved. The guest may decline their own offer.
5. Declining (member, guest or admin), the 2 hour timeout, or the slot coming within 2 hours releases the placeholder (`cancelled`), zeroes the ledger rows, sets `declined`/`expired`, and immediately offers the court to the next waiting entry. The sweep runs on the member and admin waitlist pages and from `cron/waitlist-expire.php`.
6. Members must confirm a phone number before joining a waitlist; a low-credit warning shows below 260 THB.

### Membership
- Expiry date shown in the header; expired members see a notice and cannot book.
- Renewal and extension are handled offline (contact reception, WhatsApp, LINE).

## 4. Notifications and logging

- **LINE**: broadcast to the club's official account on every new booking, waitlist join, cancellation and credit refill (Thai text with a deep link to the admin page).
- **SMS**: via SMSMKT, only for waitlist offers.
- **Audit log**: `logs/app-YYYY-MM.log.php`, one file per month, JSON lines with timestamp, actor, action, description. Each file opens with a `<?php exit; ?>` guard so it cannot be downloaded. Viewed on `admin-logs.php` with month, keyword and date filters, 50 per page; the default view reads only the current month.
- All external sends are suppressed when `DISABLE_NOTIFICATIONS` is true.

## 5. Screens

**Public**: `login.php` (member / non-member tabs), `change-password.php` (forced on first login with default password).

**Member / guest**: `index.php` (booking grid), `bookings.php` (my bookings, cancel), `waitlist.php` (my waitlist, confirm/decline), `add-credit.php`, `member-renew.php`, `user-edit.php`.

**Admin**: `admin-member-credit.php` (per-member credit activity: date, what for, who, in, out, balance after), `index.php` with admin toolbar (book for member, book for guest, academy blocks, court layout), `admin-bookings.php` (all bookings by date with filters), `admin-view-booking.php` (edit, approve, cancel, refund), `admin-members.php`, `admin-view-member.php`, `admin-add-member.php`, `admin-transactions.php`, `admin-view-transaction.php` (approve credit refill), `admin-add-credit.php`, `admin-waitlist.php` (offers awaiting confirmation, guest ones first, plus waitlist by date), `admin-logs.php`, `admin-settings.php`, `view_bookings_by_date.php`.

## 6. Request flow for a booking

1. `index.php` renders the grid for today via `modules/time-table.php`.
2. Date change -> AJAX POST to `check_availability.php` -> returns table rows.
3. Checkbox changes -> `app.js` validates rules, builds `all_selected_courts` / `all_selected_times` CSVs and computes the fee client-side.
4. "Make a booking" -> `app.js` picks the handler by role and booking type and POSTs multipart form data.
5. Handler re-validates window, advance limit, duplicates, per-day limit and credit balance, uploads the slip, then inside one database transaction under a per-date lock re-checks the slots and credit, writes parent and child transactions, bookings and the credit deduction, commits, logs, sends LINE, and returns an HTML success fragment. Any failure rolls everything back and returns HTTP 409 with a message.

## 7. Known gaps and risks

- Passwords are reversibly encrypted rather than hashed, so that staff can read them; the key on the server is the single secret protecting them.
- Uploaded payment slips in `uploads/` are served without authentication.
- All money paths are transactional. Bookings use a per-date lock; cancellations lock the booking row.
- Business constants duplicated across PHP and JS.
- Free-text enums with inconsistent values in production data.
- No staging environment or deployment pipeline. Tests cover the waitlist service, password storage and authorization (`tests/`).
