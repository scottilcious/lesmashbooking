<?php
// booking_functions/process_waitlist.php
require_once __DIR__ . '/../config.php';

try {
  // 1) Fetch expired but still pending offers
  $stmt = $pdo->query('
    SELECT offer_id, wait_list_id, court, date, timeslot
    FROM waitlist_offers
    WHERE status = "pending" AND expires_at < NOW()
    ORDER BY expires_at ASC
    LIMIT 50
  ');
  $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($expired as $row) {
    $pdo->beginTransaction();

    // 2) Lock the offer again (double check still pending + expired)
    $lock = $pdo->prepare('SELECT status FROM waitlist_offers WHERE offer_id = :id FOR UPDATE');
    $lock->execute([':id' => $row['offer_id']]);
    $st = $lock->fetchColumn();
    if ($st !== 'pending') { $pdo->commit(); continue; }

    // Mark as expired
    $pdo->prepare('UPDATE waitlist_offers SET status="expired" WHERE offer_id = :id')
        ->execute([':id' => $row['offer_id']]);

    // 3) Find the NEXT waitlist entry by created_at that has NOT received an accepted offer for the same date/timeslot
    $next = $pdo->prepare('
      SELECT wl.wait_list_id, wl.member_id, wl.non_member_id, m.member_phone
      FROM wait_list wl
      LEFT JOIN members m ON m.id = wl.member_id
      WHERE wl.date = :d AND wl.timeslot = :t
        AND wl.wait_list_id > :current_wl  -- a simple way to move forward; or rely on created_at and NOT IN (already offered) if you prefer
      ORDER BY wl.created_at ASC
      LIMIT 1
    ');
    $next->execute([
      ':d' => $row['date'],
      ':t' => $row['timeslot'],
      ':current_wl' => $row['wait_list_id']
    ]);
    $n = $next->fetch(PDO::FETCH_ASSOC);

    if ($n && !empty($n['member_phone'])) {
      // Create a new offer for the next person
      $token = bin2hex(random_bytes(32));
      $offer = $pdo->prepare('
        INSERT INTO waitlist_offers (wait_list_id, court, date, timeslot, token, sms_to, status, sent_at, expires_at)
        VALUES (:wlid, :court, :date, :timeslot, :token, :sms, "pending", NOW(), DATE_ADD(NOW(), INTERVAL 2 HOUR))
      ');
      $offer->execute([
        ':wlid'     => $n['wait_list_id'],
        ':court'    => $row['court'],
        ':date'     => $row['date'],
        ':timeslot' => $row['timeslot'],
        ':token'    => $token,
        ':sms'      => $n['member_phone']
      ]);

      $link = sprintf('%s/booking_functions/confirm-waitlist.php?token=%s',
        rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'], '/'),
        $token
      );
      $msg = "A court is available!\nDate: {$row['date']}\nTime: {$row['timeslot']}\nCourt: {$row['court']}\nConfirm within 2 hours: {$link}";
      send_sms($n['member_phone'], $msg);
    }

    $pdo->commit();
  }

  echo "OK\n";
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo "ERROR\n";
}

function send_sms(string $to, string $body): void {
  // Integrate your provider here
  error_log("SMS to {$to}: {$body}");
}
