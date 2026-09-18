<?php
require_once __DIR__ . '/includes/auth.php';
$lsc_me = lsc_require_admin('redirect');
// Database connection
require 'config.php';
require_once 'includes/waitlist-service.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waitlist - Admin Le Smash Club</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"rel="stylesheet" />
    <link rel="stylesheet" href="style.css">
    <link href="bs-overwrite.css" rel="stylesheet">
</head>
<body  class="page-id-6">
    <?php include "menu.php";

    if ($memberType !== 'admin') {
        echo '<div class="container mt-5"><div class="alert alert-danger">This page is for admin only.</div></div></body></html>';
        exit;
    }

    include_once 'includes/member-functions.php';

    // Expire stale offers first so the list below is accurate (each expiry promotes the next entry).
    lsc_waitlist_expire_stale($pdo);

    $pending_offers = lsc_waitlist_pending_offers($pdo);
    $guest_offers   = array_values(array_filter($pending_offers, fn($o) => $o['is_guest']));
    $member_offers  = array_values(array_filter($pending_offers, fn($o) => !$o['is_guest']));

    $waitlist_date = $_GET['waitlist_date'] ?? '';
    $list_date = $waitlist_date ?: date('Y-m-d');
    $stmt = $pdo->prepare("SELECT * FROM wait_list WHERE date = ? ORDER BY timeslot, created_at ASC");
    $stmt->execute([$list_date]);
    $waitlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

    function lsc_admin_waitlist_status_badge(?string $status): string
    {
        switch ($status) {
            case 'pending':   return '<span class="badge text-bg-warning">Offered - awaiting confirmation</span>';
            case 'confirmed': return '<span class="badge text-bg-success">Confirmed</span>';
            case 'expired':   return '<span class="badge text-bg-secondary">Expired</span>';
            case 'declined':  return '<span class="badge text-bg-secondary">Declined</span>';
            default:          return '<span class="badge text-bg-dark">Waiting</span>';
        }
    }

    function lsc_admin_offer_rows(array $offers, bool $guest): void
    {
        foreach ($offers as $o):
            $p = $o['person'];
            $bookingUrl = 'admin-view-booking.php?booking_id=' . (int) $o['waitlist_booking_id'];
            $base = $bookingUrl . '&waitlist_id=' . (int) $o['wait_list_id'];
            ?>
            <tr>
                <td><?= format_date_to_readable($o['date']) ?><br><b><?= htmlspecialchars($o['timeslot']) ?></b></td>
                <td>Court <?= htmlspecialchars((string) $o['free_court']) ?></td>
                <td>
                    <?php if ($p): ?>
                        <b><?= htmlspecialchars($p['name']) ?></b>
                        <?php if ($guest): ?><span class="badge rounded-pill text-bg-dark">Guest</span>
                        <?php else: ?><span class="badge text-bg-light">(<?= htmlspecialchars((string) ($p['member_number'] ?? '')) ?>)</span> <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($p['member_type']) ?></span><?php endif; ?>
                        <br><small><?= htmlspecialchars($p['phone']) ?><?= $p['email'] ? ' &middot; ' . htmlspecialchars($p['email']) : '' ?></small>
                    <?php else: ?>
                        <span class="text-danger">Unknown person (waitlist <?= (int) $o['wait_list_id'] ?>)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php $n = lsc_waitlist_parse_note($o['waitlist_note']); ?>
                    <?= htmlspecialchars($n['daily_member_type'] ?: '-') ?>
                    <?= $n['coach'] ? '<br>' . htmlspecialchars($n['coach']) : '' ?>
                    <?= $n['extra_players'] ? '<br>+' . (int) $n['extra_players'] . ' extra player(s)' : '' ?>
                </td>
                <td class="fw-bold"><?= number_format($o['price']) ?> THB</td>
                <td><small>Offered <?= htmlspecialchars($o['updated_at']) ?><br>Expires <?= htmlspecialchars($o['expires_at']) ?></small></td>
                <td class="text-nowrap">
                    <?php if ($guest): ?>
                        <a class="btn btn-success btn-sm" href="<?= $base ?>&waitlist_action=confirm&payment=cash" onclick="return confirm('Confirm this guest booking as PAID IN CASH (<?= number_format($o['price']) ?> THB)?')">Confirm - cash</a>
                        <a class="btn btn-outline-success btn-sm" href="<?= $base ?>&waitlist_action=confirm&payment=qr" onclick="return confirm('Confirm this guest booking as PAID BY BANK TRANSFER (<?= number_format($o['price']) ?> THB)?')">Confirm - transfer</a>
                    <?php else: ?>
                        <a class="btn btn-success btn-sm" href="<?= $base ?>&waitlist_action=confirm" onclick="return confirm('Confirm on behalf of the member and deduct <?= number_format($o['price']) ?> THB from their credit?')">Confirm (credit)</a>
                    <?php endif; ?>
                    <a class="btn btn-outline-danger btn-sm" href="<?= $base ?>&waitlist_action=decline" onclick="return confirm('Decline this offer? The court goes to the next person on the waitlist.')">Decline</a>
                    <br><a class="btn btn-link btn-sm" href="<?= $bookingUrl ?>">View booking</a>
                </td>
            </tr>
        <?php endforeach;
    }
    ?>
    <div class="banner">
        <div class="container">
            <h1>Waitlist</h1>
        </div>
    </div>

    <div class="container mt-5">
        <h2 class="fs-3">Offers awaiting confirmation</h2>
        <p class="text-body-secondary">A court was freed and reserved for the first person on the waitlist. Offers expire 2 hours after they are sent, or when the session is less than 2 hours away; the court then goes to the next person automatically.</p>

        <div class="card border-warning mb-4">
            <div class="card-header bg-warning-subtle fw-bold">
                Guests - admin must confirm <span class="badge text-bg-dark"><?= count($guest_offers) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Date / Time</th><th>Court</th><th>Guest</th><th>Options</th><th>Price</th><th>Timing</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($guest_offers): lsc_admin_offer_rows($guest_offers, true); else: ?>
                            <tr><td colspan="7" class="text-center text-body-secondary">No guest offers waiting.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-5">
            <div class="card-header fw-bold">
                Members - waiting for the member to confirm <span class="badge text-bg-dark"><?= count($member_offers) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light"><tr><th>Date / Time</th><th>Court</th><th>Member</th><th>Options</th><th>Price</th><th>Timing</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($member_offers): lsc_admin_offer_rows($member_offers, false); else: ?>
                            <tr><td colspan="7" class="text-center text-body-secondary">No member offers waiting.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="container mt-3">
        <h2 class="fs-3">Waitlist by date</h2>
        <form action="admin-waitlist.php">
            <div class="input-group">
                <input type="text" class="form-control" id="waitlist_date" name="waitlist_date" aria-describedby="checkAvailability" required>
                <button class="btn btn-outline-primary" type="submit" id="checkAvailability">View</button>
            </div>
        </form>
    </div>

    <div class="container mt-3 mb-5">
        <div class="table-responsive">
        <table class="table table-bordered text-center align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Requested Date</th>
                    <th>Requested Time</th>
                    <th>Who</th>
                    <th>Type</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($waitlist) > 0): ?>
                    <?php foreach ($waitlist as $w):
                        $p = lsc_waitlist_resolve_person($pdo, $w);
                        $isGuest = lsc_waitlist_is_guest_entry($w);
                    ?>
                        <tr>
                            <td><?= format_date_to_readable($w['date']) ?></td>
                            <td><?= htmlspecialchars($w['timeslot']) ?></td>
                            <td>
                                <?php if ($p): ?>
                                    <?= htmlspecialchars($p['name']) ?><?= $isGuest ? '' : ' (' . htmlspecialchars((string) ($p['member_number'] ?? '')) . ')' ?><br>
                                    <small><?= htmlspecialchars($p['phone']) ?></small>
                                <?php else: ?><span class="text-body-secondary">unknown</span><?php endif; ?>
                            </td>
                            <td><?= $isGuest ? '<span class="badge rounded-pill text-bg-dark">Guest</span>' : '<span class="badge rounded-pill text-bg-primary">' . htmlspecialchars($p['member_type'] ?? 'member') . '</span>' ?></td>
                            <td><small><?= htmlspecialchars($w['created_at']) ?></small></td>
                            <td><?= lsc_admin_waitlist_status_badge($w['waitlist_status']) ?></td>
                            <td>
                                <?php if ($w['waitlist_status'] === 'pending' && $w['waitlist_booking_id']): ?>
                                    <a class="btn btn-primary btn-sm" href="admin-view-booking.php?booking_id=<?= (int) $w['waitlist_booking_id'] ?>">Open offer</a>
                                <?php elseif ($p && !$isGuest): ?>
                                    <a class="btn btn-outline-primary btn-sm" href="admin-view-member.php?member_id=<?= (int) $p['id'] ?>">View member</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">No waitlist entries for <?= htmlspecialchars($list_date) ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        flatpickr("#waitlist_date", {
            dateFormat: "Y-m-d",
            defaultDate: "<?= htmlspecialchars($list_date) ?>",
            altInput: true,
            altFormat: "j F, Y",
        });
    </script>
</body>
</html>
