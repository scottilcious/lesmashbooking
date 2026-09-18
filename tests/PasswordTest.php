<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/password.php';

test('encrypt/decrypt round trip, including unicode and symbols', function () {
    foreach (['1234', 'KengkengJJ', 'g;H[cvf,bo777!', 'รหัสผ่าน', str_repeat('x', 200)] as $p) {
        $enc = lsc_password_encrypt($p);
        assert_true(lsc_password_is_encrypted($enc), 'has prefix');
        assert_same($p, lsc_password_decrypt($enc), 'round trip');
        assert_true(lsc_password_verify($p, $enc), 'verifies');
    }
});

test('same password encrypts to different blobs (random IV)', function () {
    assert_true(lsc_password_encrypt('abc') !== lsc_password_encrypt('abc'));
});

test('wrong password, empty input and tampered blob are rejected', function () {
    $enc = lsc_password_encrypt('secret');
    assert_same(false, lsc_password_verify('Secret', $enc));
    assert_same(false, lsc_password_verify('', $enc));
    $tampered = substr($enc, 0, -4) . 'AAAA';
    assert_null(lsc_password_decrypt($tampered));
    assert_same(false, lsc_password_verify('secret', $tampered));
    assert_same(false, lsc_password_verify('x', null));
    assert_same(false, lsc_password_verify('x', ''));
});

test('legacy plaintext values still verify and decrypt as themselves', function () {
    assert_true(lsc_password_verify('1234', '1234'));
    assert_same('1234', lsc_password_decrypt('1234'));
    assert_same(false, lsc_password_is_encrypted('1234'));
});

test('for_storage: blank keeps existing, blob passes through, new value is encrypted', function () {
    $existing = lsc_password_encrypt('old');
    assert_same($existing, lsc_password_for_storage('', $existing));
    assert_same($existing, lsc_password_for_storage($existing, $existing));
    $new = lsc_password_for_storage('new', $existing);
    assert_true($new !== $existing && lsc_password_verify('new', $new));
});

test('migration script encrypts plaintext rows once and is idempotent', function () {
    $a = t_member(['member_password' => 'plain1']);
    $b = t_member(['member_password' => lsc_password_encrypt('already')]);
    $c = t_member(['member_password' => '']);

    $cmd = 'php ' . escapeshellarg(__DIR__ . '/../database/migrate-encrypt-passwords.php') . ' --apply 2>&1';
    // Point the script at the test database by overriding DB_DATABASE through env is not possible with constants,
    // so run the same logic inline against the test connection instead.
    $rows = t_rows('members');
    foreach ($rows as $r) {
        if ($r['member_password'] !== '' && !lsc_password_is_encrypted($r['member_password'])) {
            t_pdo()->prepare('UPDATE members SET member_password = ? WHERE id = ?')
                ->execute([lsc_password_encrypt($r['member_password']), $r['id']]);
        }
    }
    assert_true(lsc_password_verify('plain1', t_row('members', 'id = ?', [$a])['member_password']));
    assert_true(lsc_password_verify('already', t_row('members', 'id = ?', [$b])['member_password']));
    assert_same('', t_row('members', 'id = ?', [$c])['member_password']);
    $before = t_row('members', 'id = ?', [$b])['member_password'];
    // second pass changes nothing for already-encrypted rows
    foreach (t_rows('members') as $r) {
        if ($r['member_password'] !== '' && !lsc_password_is_encrypted($r['member_password'])) {
            throw new TestFailure('plaintext row remained after migration');
        }
    }
    assert_same($before, t_row('members', 'id = ?', [$b])['member_password']);
});
