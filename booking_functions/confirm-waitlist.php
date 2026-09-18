<?php
// booking_functions/confirm-waitlist.php
require_once __DIR__ . '/../config.php';

function reject($msg) {
  http_response_code(400);
  echo "<h3>{$msg}</h3>";
  exit;
}

try {
  $token = $_GET['token'] ?? '';
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) reject('Invalid link.');

  $pdo->beginTransaction();

  // 1) Load offer + lock it
  $stmt = $pdo->prepare('
    SELECT o.*, wl.member_id, wl.non_member_id
    FROM waitlist_offers o
    JOIN wait_list wl ON wl.wait_list_id = o.wait_list_id
    WHERE o.token = :token
    FOR UPDATE
  ');
  $stmt->execute([':token' => $token]);
  $offer = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$offer) reject('Offer not found.');
  if ($offer['status'] !== 'pending') reject('Offer is no longer available.');
  if (strtotime($offer['expires_at']) < time()) {
    // expire it
    $upd = $pdo->prepare('UPDATE waitlist_offers SET status="expired" WHERE offer_id = :id');
    $upd->execute([':id' => $offer['offer_id']]);
    $pdo->commit();
    reject('This offer has expired.');
  }

  // 2) Check slot availability (court/date/timeslot) to avoid double-booking
  $chk = $pdo->prepare('
    SELECT id FROM bookings
    WHERE court = :court AND date = :date AND timeslot = :ts AND booking_status IN ("pending","approved")
    LIMIT 1
  ');
  $chk->execute([
    ':court' => $offer['court'],
    ':date'  => $offer['date'],
    ':ts'    => $offer['timeslot']
  ]);
  if ($chk->fetch()) {
    // Someone else took it
    $pdo->prepare('UPDATE waitlist_offers SET status="canceled" WHERE offer_id = :id')
        ->execute([':id' => $offer['offer_id']]);
    $pdo->commit();
    reject('Sorry, this slot has just been taken.');
  }

  // 3) Create the booking (approved) for this member/non-member
  $ins = $pdo->prepare('
    INSERT INTO bookings (court, date, timeslot, member_id, non_member_id, booking_status, booking_type, created_at)
    VALUES (:court, :date, :ts, :mid, :nm, "approved", "waitlist", NOW())
  ');
  $ins->execute([
    ':court' => $offer['court'],
    ':date'  => $offer['date'],
    ':ts'    => $offer['timeslot'],
    ':mid'   => $offer['member_id'],
    ':nm'    => $offer['non_member_id']
  ]);

  // 4) Mark offer accepted and optionally remove/close wait_list row
  $pdo->prepare('UPDATE waitlist_offers SET status="accepted", accepted_at = NOW() WHERE offer_id = :id')
      ->execute([':id' => $offer['offer_id']]);

  // Optional: delete the waitlist row or mark it handled
  $pdo->prepare('DELETE FROM wait_list WHERE wait_list_id = :wid')
      ->execute([':wid' => $offer['wait_list_id']]);

  $pdo->commit();

  echo "<h3>Success! Your booking is confirmed.</h3>
        <p>Court: {$offer['court']} | Date: {$offer['date']} | Time: {$offer['timeslot']}</p>";
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  reject('Server error.');
}
