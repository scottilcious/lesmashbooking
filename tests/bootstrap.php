<?php
/**
 * Minimal, dependency-free test harness.
 *
 * Usage:  php tests/run.php            (runs every tests/*Test.php)
 *         php tests/run.php Waitlist   (only files whose name contains "Waitlist")
 *
 * Requires DB_TEST_DATABASE in config.local.php. The test database is rebuilt
 * from database/schema.sql at the start of every run and truncated before each test.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!defined('DB_TEST_DATABASE')) {
    fwrite(STDERR, "DB_TEST_DATABASE is not defined in config.local.php\n");
    exit(2);
}
if (DB_TEST_DATABASE === DB_DATABASE) {
    fwrite(STDERR, "Refusing to run tests against the main database\n");
    exit(2);
}

/** @var PDO $testPdo */
$testPdo = lsc_create_pdo(DB_TEST_DATABASE);

function t_pdo(): PDO
{
    global $testPdo;
    return $testPdo;
}

function t_load_schema(): void
{
    $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql); // drop comment lines
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        t_pdo()->exec($stmt);
    }
}

function t_reset(): void
{
    $pdo = t_pdo();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (['bookings', 'members', 'non_members', 'system_settings', 'transactions', 'wait_list'] as $t) {
        $pdo->exec("TRUNCATE TABLE `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
}

/* ---------- fixtures ---------- */

function t_member(array $o = []): int
{
    $d = array_merge([
        'first_name' => 'Test', 'last_name' => 'Member', 'member_number' => rand(1000, 9999),
        'member_type' => 'individual', 'member_status' => 'active', 'member_phone' => '0800000000',
        'member_email' => 'test@example.com', 'member_password' => 'pw', 'member_since' => '2025-01-01',
        'member_length' => '1 year', 'member_expiration' => '2030-01-01', 'credit' => 1000,
    ], $o);
    $cols = implode(',', array_keys($d));
    $ph = implode(',', array_fill(0, count($d), '?'));
    t_pdo()->prepare("INSERT INTO members ($cols) VALUES ($ph)")->execute(array_values($d));
    return (int) t_pdo()->lastInsertId();
}

function t_guest(array $o = []): int
{
    $d = array_merge(['guest_name' => 'Guest', 'member_phone' => '0899999999', 'member_email' => 'guest@example.com'], $o);
    $cols = implode(',', array_keys($d));
    $ph = implode(',', array_fill(0, count($d), '?'));
    t_pdo()->prepare("INSERT INTO non_members ($cols) VALUES ($ph)")->execute(array_values($d));
    return (int) t_pdo()->lastInsertId();
}

function t_booking(array $o = []): int
{
    $d = array_merge([
        'court' => 1, 'date' => date('Y-m-d', strtotime('+2 days')), 'timeslot' => '7-8am',
        'member_id' => 0, 'non_member_id' => 90002, 'booking_status' => 'approved',
        'booking_type' => 'member booking', 'daily_member_type' => 'individual', 'payment' => 'credit',
        'transaction_id' => 0,
    ], $o);
    $cols = implode(',', array_keys($d));
    $ph = implode(',', array_fill(0, count($d), '?'));
    t_pdo()->prepare("INSERT INTO bookings ($cols) VALUES ($ph)")->execute(array_values($d));
    return (int) t_pdo()->lastInsertId();
}

function t_waitlist(array $o = []): int
{
    static $seq = 0; // insertion order == age order, independent of the real clock
    $seq++;
    $d = array_merge([
        'created_at' => date('Y-m-d H:i:s', strtotime('2026-10-01 00:00:00') + $seq),
        'member_id' => 0, 'non_member_id' => 90002, 'date' => date('Y-m-d', strtotime('+2 days')),
        'timeslot' => '7-8am', 'member_type' => 'member booking', 'waitlist_note' => ',,,individual,,0',
        'waitlist_status' => null,
    ], $o);
    $cols = implode(',', array_keys($d));
    $ph = implode(',', array_fill(0, count($d), '?'));
    t_pdo()->prepare("INSERT INTO wait_list ($cols) VALUES ($ph)")->execute(array_values($d));
    return (int) t_pdo()->lastInsertId();
}

function t_row(string $table, string $where, array $params = []): ?array
{
    $st = t_pdo()->prepare("SELECT * FROM `$table` WHERE $where LIMIT 1");
    $st->execute($params);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r === false ? null : $r;
}

function t_rows(string $table, string $where = '1', array $params = []): array
{
    $st = t_pdo()->prepare("SELECT * FROM `$table` WHERE $where");
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function t_credit(int $memberId): float
{
    return (float) t_row('members', 'id = ?', [$memberId])['credit'];
}

/* ---------- assertions & runner ---------- */

class TestFailure extends Exception {}

function assert_eq($expected, $actual, string $msg = ''): void
{
    if ($expected != $actual) {
        throw new TestFailure(($msg ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_same($expected, $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($msg ? $msg . ': ' : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_true($cond, string $msg = 'expected true'): void
{
    if (!$cond) {
        throw new TestFailure($msg);
    }
}

function assert_null($v, string $msg = 'expected null'): void
{
    if ($v !== null) {
        throw new TestFailure($msg . ', got ' . var_export($v, true));
    }
}

$GLOBALS['__tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['__tests'][] = [$name, $fn];
}

function run_tests(): int
{
    $pass = 0;
    $fail = 0;
    foreach ($GLOBALS['__tests'] as [$name, $fn]) {
        t_reset();
        try {
            $fn();
            $pass++;
            echo "  ok   $name\n";
        } catch (TestFailure $e) {
            $fail++;
            echo "  FAIL $name\n       " . $e->getMessage() . "\n";
        } catch (Throwable $e) {
            $fail++;
            echo "  ERR  $name\n       " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        }
    }
    echo "\n$pass passed, $fail failed\n";
    return $fail === 0 ? 0 : 1;
}
