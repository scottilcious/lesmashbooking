<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/logging.php';

/** Point the log helpers at a scratch directory for the duration of a test. */
function t_log_dir(): string
{
    $dir = sys_get_temp_dir() . '/lsc-log-test';
    if (!defined('LOG_DIRECTORY')) {
        define('LOG_DIRECTORY', $dir);
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    foreach (glob($dir . '/*') ?: [] as $f) {
        unlink($f);
    }
    return $dir;
}

function t_log_entry(string $ts, string $action, string $desc = 'x', string $user = 'tester'): array
{
    return ['timestamp' => $ts, 'username' => $user, 'action' => $action, 'description' => $desc];
}

test('entries are written to a file per month, guarded against direct web access', function () {
    $dir = t_log_dir();
    lsc_log_write(t_log_entry('2026-08-31 23:59:00', 'August'));
    lsc_log_write(t_log_entry('2026-09-01 00:01:00', 'September'));
    lsc_log_write(t_log_entry('2026-09-15 12:00:00', 'AlsoSeptember'));

    assert_true(file_exists($dir . '/app-2026-08.log.php'), 'August file');
    assert_true(file_exists($dir . '/app-2026-09.log.php'), 'September file');

    $aug = file($dir . '/app-2026-08.log.php');
    assert_same(LSC_LOG_GUARD, trim($aug[0]), 'first line exits PHP so a direct request returns nothing');
    assert_eq(2, count($aug), 'guard + one entry');
    assert_eq(3, count(file($dir . '/app-2026-09.log.php')), 'guard + two entries');
});

test('the guard line is never returned as an entry', function () {
    t_log_dir();
    lsc_log_write(t_log_entry('2026-09-02 08:00:00', 'Login'));
    $r = lsc_log_query(['month' => '2026-09']);
    assert_eq(1, $r['total']);
    assert_eq('Login', $r['entries'][0]['action']);
    assert_null(lsc_log_decode_line(LSC_LOG_GUARD));
    assert_null(lsc_log_decode_line(''));
    assert_null(lsc_log_decode_line('not json'));
});

test('query defaults to the newest month and lists available months newest first', function () {
    t_log_dir();
    lsc_log_write(t_log_entry('2026-07-05 10:00:00', 'Old'));
    lsc_log_write(t_log_entry('2026-09-05 10:00:00', 'New'));
    $r = lsc_log_query([]);
    assert_eq(['2026-09', '2026-07'], $r['months']);
    assert_eq(1, $r['total'], 'only the newest month is read by default');
    assert_eq('New', $r['entries'][0]['action']);
});

test('a date range searches across months and returns newest first', function () {
    t_log_dir();
    lsc_log_write(t_log_entry('2026-07-05 10:00:00', 'July'));
    lsc_log_write(t_log_entry('2026-08-05 10:00:00', 'August'));
    lsc_log_write(t_log_entry('2026-09-05 10:00:00', 'September'));
    $r = lsc_log_query(['start_date' => '2026-07-01', 'end_date' => '2026-08-31']);
    assert_eq(2, $r['total']);
    assert_eq(['August', 'July'], array_column($r['entries'], 'action'), 'newest first');
    $r = lsc_log_query(['start_date' => '2026-08-06']);
    assert_eq(1, $r['total']);
    assert_eq('September', $r['entries'][0]['action']);
});

test('keyword search matches actor, action or details, case insensitively', function () {
    t_log_dir();
    lsc_log_write(t_log_entry('2026-09-01 10:00:00', 'Member Booking', 'Court 3', 'Alice'));
    lsc_log_write(t_log_entry('2026-09-02 10:00:00', 'Cancel Booking', 'Court 5', 'Bob'));
    assert_eq(1, lsc_log_query(['month' => '2026-09', 'search' => 'alice'])['total'], 'by actor');
    assert_eq(1, lsc_log_query(['month' => '2026-09', 'search' => 'cancel'])['total'], 'by action');
    assert_eq(1, lsc_log_query(['month' => '2026-09', 'search' => 'court 5'])['total'], 'by details');
    assert_eq(2, lsc_log_query(['month' => '2026-09', 'search' => 'court'])['total']);
    assert_eq(0, lsc_log_query(['month' => '2026-09', 'search' => 'nothing'])['total']);
});

test('pagination slices without losing entries', function () {
    t_log_dir();
    for ($i = 1; $i <= 12; $i++) {
        lsc_log_write(t_log_entry(sprintf('2026-09-%02d 10:00:00', $i), 'Action' . $i));
    }
    $p1 = lsc_log_query(['month' => '2026-09', 'limit' => 5, 'page' => 1]);
    $p3 = lsc_log_query(['month' => '2026-09', 'limit' => 5, 'page' => 3]);
    assert_eq(12, $p1['total']);
    assert_eq(3, $p1['pages']);
    assert_eq(5, count($p1['entries']));
    assert_eq('Action12', $p1['entries'][0]['action'], 'newest first');
    assert_eq(2, count($p3['entries']), 'last page has the remainder');
    assert_eq('Action1', $p3['entries'][1]['action']);
});

test('the legacy app.log is still readable until it is migrated', function () {
    $dir = t_log_dir();
    file_put_contents($dir . '/app.log', json_encode(t_log_entry('2026-05-01 09:00:00', 'LegacyLogin')) . "\n");
    lsc_log_write(t_log_entry('2026-09-01 09:00:00', 'NewLogin'));
    assert_true(in_array('legacy', lsc_log_query([])['months'], true), 'listed as a month option');
    $r = lsc_log_query(['month' => 'legacy']);
    assert_eq(1, $r['total']);
    assert_eq('LegacyLogin', $r['entries'][0]['action']);
});
