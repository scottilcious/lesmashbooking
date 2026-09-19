<?php
/**
 * One-off: split the legacy logs/app.log into guarded monthly files.
 *
 *   php scripts/migrate-logs.php            (dry run: report only)
 *   php scripts/migrate-logs.php --apply    (write the monthly files)
 *
 * Idempotent in the sense that it rewrites the monthly files from scratch each
 * run, so running it twice does not duplicate entries. It never deletes
 * app.log; do that yourself once you have checked the output, because that file
 * is readable over the web.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logging.php';

$apply  = in_array('--apply', $argv, true);
$legacy = lsc_log_legacy_file();

if (!file_exists($legacy)) {
    exit("No legacy log at $legacy, nothing to do.\n");
}

$fh = fopen($legacy, 'r');
if ($fh === false) {
    fwrite(STDERR, "Cannot read $legacy\n");
    exit(1);
}

$byMonth = [];
$lines = $bad = 0;
while (($line = fgets($fh)) !== false) {
    $lines++;
    $e = lsc_log_decode_line($line);
    if ($e === null || empty($e['timestamp'])) {
        $bad++;
        continue;
    }
    $byMonth[substr((string) $e['timestamp'], 0, 7)][] = rtrim($line, "\n");
}
fclose($fh);

ksort($byMonth);
printf("%s: %d lines, %d unparseable, %d month(s)%s\n",
    basename($legacy), $lines, $bad, count($byMonth), $apply ? '' : ' (dry run, pass --apply to write)');
foreach ($byMonth as $ym => $rows) {
    printf("  %s  %6d entries -> %s\n", $ym, count($rows), basename(lsc_log_month_file($ym)));
}

if (!$apply) {
    exit(0);
}

foreach ($byMonth as $ym => $rows) {
    $path = lsc_log_month_file($ym);
    file_put_contents($path, LSC_LOG_GUARD . "\n" . implode("\n", $rows) . "\n", LOCK_EX);
    @chmod($path, 0640);
}
printf("Written. Now remove or move the legacy file:\n  rm %s\n", $legacy);
