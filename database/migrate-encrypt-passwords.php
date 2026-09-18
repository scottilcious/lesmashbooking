<?php
/**
 * One-off migration: encrypt every plaintext member password in place.
 *
 *   php database/migrate-encrypt-passwords.php            (dry run: report only)
 *   php database/migrate-encrypt-passwords.php --apply    (write changes)
 *
 * Idempotent: rows already prefixed with enc1: are skipped. Safe to re-run.
 * Requires PASSWORD_ENCRYPTION_KEY in config.local.php. BACK UP THE KEY:
 * without it, encrypted passwords cannot be read and must be reset by an admin.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/password.php';

$apply = in_array('--apply', $argv, true);

$rows = $pdo->query('SELECT id, member_number, member_password FROM members')->fetchAll(PDO::FETCH_ASSOC);
$plain = $already = $empty = 0;
$toUpdate = [];
foreach ($rows as $r) {
    $p = $r['member_password'];
    if ($p === null || $p === '') {
        $empty++;
    } elseif (lsc_password_is_encrypted($p)) {
        $already++;
    } else {
        $plain++;
        $toUpdate[] = $r;
    }
}

printf("members: %d total, %d already encrypted, %d empty, %d plaintext%s\n",
    count($rows), $already, $empty, $plain, $apply ? '' : ' (dry run, pass --apply to encrypt)');

if (!$apply || $plain === 0) {
    exit(0);
}

$pdo->beginTransaction();
$st = $pdo->prepare('UPDATE members SET member_password = ? WHERE id = ? AND member_password = ?');
$done = 0;
foreach ($toUpdate as $r) {
    $enc = lsc_password_encrypt($r['member_password']);
    if (lsc_password_decrypt($enc) !== $r['member_password']) {
        $pdo->rollBack();
        fwrite(STDERR, "Round-trip check failed for member id {$r['id']}; aborted, nothing written.\n");
        exit(1);
    }
    $st->execute([$enc, $r['id'], $r['member_password']]);
    $done += $st->rowCount();
}
$pdo->commit();
printf("encrypted %d password(s)\n", $done);
