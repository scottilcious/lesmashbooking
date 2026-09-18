# Audit: credit damage from the waitlist confirmation bug

Run on 2026-09-18 against a pristine copy of the production dump
(`scottde_lscbooking_1789725404.sql`), before any of the fixes were deployed.

## What the bug did

When an admin confirmed a waitlist offer on the booking detail page, the credit
deduction ran against the logged-in admin's account instead of the waitlisted
member's. Fixed in commit "Fix waitlist credit deduction".

## Findings

| Item | Value |
|---|---|
| Waitlist bookings confirmed (all time) | 201 |
| Their total value | 47,880 THB |
| Date range | 2025-11-27 to 2026-09-22 |
| Deficit on the admin account (member number 2) | **-19,620 THB** |
| Ledger rows explaining that deficit | none |

The admin account `admin le smashclub` (id 1349, member number 2) holds 190
ledger rows: 185 cancellation records worth 0 THB and 5 legacy booking rows that
were not paid by credit. Its expected balance from the ledger is therefore 0.00.
It owns 104 active bookings, none paid by credit, and has never joined a
waitlist. The -19,620 THB has no legitimate source and is consistent with the
bug.

The other two admin accounts (Pierre, Joe) have a NULL balance and are unaffected.

## What cannot be determined

Which of the 201 confirmations were admin-confirmed (and so mischarged) cannot
be reconstructed, because the old code:

1. wrote no ledger row at confirmation time,
2. cleared `wait_list.waitlist_booking_id` on confirm, breaking the link between
   the waitlist entry and its booking, and
3. left most members with balances that the ledger does not explain anyway,
   because `admin-save-member.php` writes `members.credit` directly with no
   ledger row.

Roughly 82 of the 201 confirmations were affected (19,620 divided by the average
price of about 240 THB). Note that 19,620 is not an exact sum of 160 and 280
multiples, so at least part of it came from a manual credit edit rather than the
bug alone.

`waitlist-charge-audit.tsv` (scratch output, not committed) lists all 201
bookings with member, date, court and price if a manual review is wanted.

## Wider reconciliation

Comparing every member's balance against their ledger:

| Bucket | Members | Total |
|---|---|---|
| Balanced | 220 | |
| Balance higher than the ledger explains | 439 | +1,589,633 THB |
| Balance lower than the ledger explains | 27 | -51,300 THB |

The large positive figure is expected: opening balances and admin edits are
written straight to `members.credit`. It is not evidence of a bug. Of the 27
short accounts, the admin is -19,620 and the remaining 26 total -31,680.

## Recommendation

1. **Write off the admin deficit.** The members concerned received courts they
   were not charged for, months ago, and cannot be identified individually.
   Billing them now would be guesswork.

   ```sql
   UPDATE members SET credit = 0 WHERE id = 1349 AND credit = -19620.00;
   ```

   Run this once, after the fixes are deployed. It is written to be a no-op if
   the balance has moved since this audit.

2. **Consider a starting-balance ledger row.** To make future reconciliation
   possible, record adjustments as `Admin credit add` transactions rather than
   editing `members.credit` directly on the member page.

3. **Review the 26 short accounts** separately if members have complained about
   missing credit. That is a different problem from this bug.
